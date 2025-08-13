<?php

namespace App\Services;

use App\Models\TournamentRegistration;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class EnhancedRegistrationService
{
    /**
     * Register team with comprehensive validation and approval workflow
     */
    public function registerTeamWithApproval(array $registrationData): array
    {
        DB::beginTransaction();
        
        try {
            $event = Event::findOrFail($registrationData['event_id']);
            $team = Team::findOrFail($registrationData['team_id']);
            $user = User::findOrFail($registrationData['user_id']);

            // Comprehensive validation
            $validationResult = $this->validateTeamRegistration($event, $team, $registrationData);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors'],
                    'warnings' => $validationResult['warnings']
                ];
            }

            // Create registration record
            $registration = TournamentRegistration::create([
                'tournament_id' => $event->id,
                'team_id' => $team->id,
                'user_id' => $user->id,
                'status' => $this->determineInitialStatus($event),
                'registration_data' => $registrationData['data'] ?? [],
                'registered_at' => now(),
                'payment_status' => $this->determinePaymentStatus($event),
                'notes' => $registrationData['notes'] ?? '',
                'emergency_contact' => $registrationData['emergency_contact'] ?? [],
                'special_requirements' => $registrationData['special_requirements'] ?? [],
                'submission_ip' => request()->ip()
            ]);

            // Add to waiting list if event is full
            if ($event->current_team_count >= $event->max_teams) {
                $this->addToWaitingList($registration);
            }

            // Trigger approval workflow if required
            if ($event->requires_approval) {
                $this->triggerApprovalWorkflow($registration);
            } else {
                // Auto-approve if no approval required
                $registration->approve();
            }

            DB::commit();
            
            // Send confirmation notifications
            $this->sendRegistrationConfirmation($registration);
            
            Log::info('Team registration completed', [
                'registration_id' => $registration->id,
                'event_id' => $event->id,
                'team_id' => $team->id
            ]);

            return [
                'success' => true,
                'registration' => $registration,
                'requires_approval' => $event->requires_approval,
                'waiting_list' => $event->current_team_count >= $event->max_teams
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Team registration failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'errors' => ['Registration failed: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Implement waiting list functionality
     */
    public function addToWaitingList(TournamentRegistration $registration): void
    {
        $waitingListPosition = TournamentRegistration::where('tournament_id', $registration->tournament_id)
            ->where('status', 'waiting_list')
            ->count() + 1;

        $registration->update([
            'status' => 'waiting_list',
            'notes' => array_merge($registration->notes ?? [], [
                'waiting_list' => [
                    'position' => $waitingListPosition,
                    'added_at' => now()->toISOString()
                ]
            ])
        ]);
    }

    /**
     * Process waiting list when spots become available
     */
    public function processWaitingList(Event $event): array
    {
        $availableSpots = $event->max_teams - $event->current_team_count;
        if ($availableSpots <= 0) {
            return ['promoted' => 0, 'registrations' => []];
        }

        $waitingRegistrations = TournamentRegistration::where('tournament_id', $event->id)
            ->where('status', 'waiting_list')
            ->orderBy('registered_at')
            ->limit($availableSpots)
            ->get();

        $promoted = [];
        
        foreach ($waitingRegistrations as $registration) {
            if ($registration->approve()) {
                $promoted[] = $registration;
                $this->notifyWaitingListPromotion($registration);
            }
        }

        // Update remaining waiting list positions
        $this->updateWaitingListPositions($event);

        return [
            'promoted' => count($promoted),
            'registrations' => $promoted
        ];
    }

    /**
     * Implement check-in system with time windows
     */
    public function openCheckIn(Event $event, array $settings = []): bool
    {
        $checkInStart = Carbon::parse($settings['start_time'] ?? now());
        $checkInEnd = Carbon::parse($settings['end_time'] ?? $checkInStart->copy()->addHours(2));

        $event->update([
            'check_in_start' => $checkInStart,
            'check_in_end' => $checkInEnd,
            'current_phase' => 'check_in'
        ]);

        // Notify all approved teams
        $approvedRegistrations = TournamentRegistration::where('tournament_id', $event->id)
            ->where('status', 'approved')
            ->get();

        foreach ($approvedRegistrations as $registration) {
            $this->notifyCheckInOpen($registration, $checkInStart, $checkInEnd);
        }

        Log::info('Check-in opened for event', [
            'event_id' => $event->id,
            'start_time' => $checkInStart,
            'end_time' => $checkInEnd
        ]);

        return true;
    }

    /**
     * Process team check-in
     */
    public function checkInTeam(TournamentRegistration $registration, array $checkInData = []): array
    {
        $event = $registration->tournament;
        
        // Validate check-in window
        if (!$this->isCheckInOpen($event)) {
            return [
                'success' => false,
                'error' => 'Check-in is not currently open'
            ];
        }

        // Validate team eligibility
        if ($registration->status !== 'approved') {
            return [
                'success' => false,
                'error' => 'Team is not approved for check-in'
            ];
        }

        // Additional validations
        $validationResult = $this->validateCheckIn($registration, $checkInData);
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'errors' => $validationResult['errors']
            ];
        }

        // Process check-in
        $registration->checkIn();
        
        // Update registration data if provided
        if (!empty($checkInData)) {
            $registration->update([
                'registration_data' => array_merge($registration->registration_data ?? [], [
                    'check_in_data' => $checkInData
                ])
            ]);
        }

        Log::info('Team checked in successfully', [
            'registration_id' => $registration->id,
            'team_id' => $registration->team_id
        ]);

        return [
            'success' => true,
            'registration' => $registration->fresh()
        ];
    }

    /**
     * Handle registration fee processing
     */
    public function processRegistrationFee(TournamentRegistration $registration, array $paymentData): array
    {
        try {
            // Validate payment data
            $validationResult = $this->validatePaymentData($paymentData);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'errors' => $validationResult['errors']
                ];
            }

            // Process payment (integrate with payment processor)
            $paymentResult = $this->processPayment($registration, $paymentData);
            
            if ($paymentResult['success']) {
                $registration->processPayment($paymentResult['payment_info']);
                
                return [
                    'success' => true,
                    'transaction_id' => $paymentResult['transaction_id'],
                    'registration' => $registration->fresh()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $paymentResult['error']
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Payment processing failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Payment processing failed'
            ];
        }
    }

    /**
     * Team eligibility validation system
     */
    public function validateTeamEligibility(Event $event, Team $team): array
    {
        $errors = [];
        $warnings = [];
        
        $requirements = $event->registration_requirements ?? [];

        // Check player count requirements
        $playerCount = $team->players()->where('status', 'active')->count();
        if (isset($requirements['min_players']) && $playerCount < $requirements['min_players']) {
            $errors[] = "Team must have at least {$requirements['min_players']} active players (current: {$playerCount})";
        }
        
        if (isset($requirements['max_players']) && $playerCount > $requirements['max_players']) {
            $errors[] = "Team cannot have more than {$requirements['max_players']} active players (current: {$playerCount})";
        }

        // Check region restrictions
        if (isset($requirements['allowed_regions']) && !empty($requirements['allowed_regions'])) {
            if (!in_array($team->region, $requirements['allowed_regions'])) {
                $errors[] = "Team region '{$team->region}' is not allowed. Allowed regions: " . implode(', ', $requirements['allowed_regions']);
            }
        }

        // Check team rating/ELO requirements
        if (isset($requirements['min_rating']) && $team->rating < $requirements['min_rating']) {
            $errors[] = "Team rating must be at least {$requirements['min_rating']} (current: {$team->rating})";
        }

        if (isset($requirements['max_rating']) && $team->rating > $requirements['max_rating']) {
            $errors[] = "Team rating cannot exceed {$requirements['max_rating']} (current: {$team->rating})";
        }

        // Check team age/experience requirements
        if (isset($requirements['min_team_age_days'])) {
            $teamAge = $team->created_at->diffInDays(now());
            if ($teamAge < $requirements['min_team_age_days']) {
                $errors[] = "Team must be at least {$requirements['min_team_age_days']} days old (current: {$teamAge} days)";
            }
        }

        // Check player eligibility
        $ineligiblePlayers = $this->checkPlayerEligibility($team, $requirements);
        if (!empty($ineligiblePlayers)) {
            $errors[] = "Ineligible players: " . implode(', ', $ineligiblePlayers);
        }

        // Check for previous bans or violations
        $violations = $this->checkTeamViolations($team, $event);
        if (!empty($violations)) {
            $errors[] = "Team has unresolved violations: " . implode(', ', $violations);
        }

        // Check for conflicting registrations
        $conflicts = $this->checkRegistrationConflicts($team, $event);
        if (!empty($conflicts)) {
            $errors[] = "Team has conflicting registrations: " . implode(', ', $conflicts);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Get registration statistics for admin dashboard
     */
    public function getRegistrationStatistics(Event $event): array
    {
        $registrations = TournamentRegistration::where('tournament_id', $event->id)->get();
        
        $stats = [
            'total_registrations' => $registrations->count(),
            'by_status' => $registrations->groupBy('status')->map->count(),
            'by_payment_status' => $registrations->groupBy('payment_status')->map->count(),
            'registration_timeline' => $this->getRegistrationTimeline($registrations),
            'regional_distribution' => $this->getRegionalDistribution($registrations),
            'team_size_distribution' => $this->getTeamSizeDistribution($registrations),
            'approval_metrics' => $this->getApprovalMetrics($registrations),
            'check_in_metrics' => $this->getCheckInMetrics($registrations)
        ];

        return $stats;
    }

    /**
     * Bulk registration operations for administrators
     */
    public function bulkApproveRegistrations(array $registrationIds, User $admin): array
    {
        $results = ['approved' => 0, 'failed' => []];
        
        foreach ($registrationIds as $id) {
            try {
                $registration = TournamentRegistration::findOrFail($id);
                if ($registration->approve()) {
                    $results['approved']++;
                    $this->notifyRegistrationApproval($registration);
                }
            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        Log::info('Bulk registration approval completed', [
            'admin_id' => $admin->id,
            'approved' => $results['approved'],
            'failed' => count($results['failed'])
        ]);

        return $results;
    }

    // Helper methods
    private function validateTeamRegistration(Event $event, Team $team, array $registrationData): array
    {
        $errors = [];
        $warnings = [];

        // Check if registration is open
        if (!$event->registration_open) {
            $errors[] = 'Registration is not currently open for this event';
        }

        // Check team eligibility
        $eligibilityResult = $this->validateTeamEligibility($event, $team);
        $errors = array_merge($errors, $eligibilityResult['errors']);
        $warnings = array_merge($warnings, $eligibilityResult['warnings']);

        // Check for existing registration
        $existingRegistration = TournamentRegistration::where('tournament_id', $event->id)
            ->where('team_id', $team->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->first();

        if ($existingRegistration) {
            $errors[] = 'Team is already registered for this event';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    private function determineInitialStatus(Event $event): string
    {
        return $event->requires_approval ? 'pending' : 'approved';
    }

    private function determinePaymentStatus(Event $event): string
    {
        return isset($event->entry_fee) && $event->entry_fee > 0 ? 'pending' : 'not_required';
    }

    private function triggerApprovalWorkflow(TournamentRegistration $registration): void
    {
        // Notify administrators about pending registration
        $this->notifyAdminsOfPendingRegistration($registration);
    }

    private function isCheckInOpen(Event $event): bool
    {
        $now = now();
        return $event->check_in_start && $event->check_in_end &&
               $now->between($event->check_in_start, $event->check_in_end);
    }

    private function validateCheckIn(TournamentRegistration $registration, array $checkInData): array
    {
        $errors = [];

        // Validate required check-in data
        $requirements = $registration->tournament->registration_requirements['check_in'] ?? [];
        
        foreach ($requirements as $field => $config) {
            if ($config['required'] && empty($checkInData[$field])) {
                $errors[] = "Required check-in field '{$field}' is missing";
            }
        }

        // Validate team roster is complete
        $team = $registration->team;
        $activePlayerCount = $team->players()->where('status', 'active')->count();
        $minPlayers = $requirements['min_players'] ?? 5;
        
        if ($activePlayerCount < $minPlayers) {
            $errors[] = "Team must have at least {$minPlayers} active players for check-in";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function validatePaymentData(array $paymentData): array
    {
        $errors = [];

        $requiredFields = ['amount', 'currency', 'payment_method'];
        foreach ($requiredFields as $field) {
            if (empty($paymentData[$field])) {
                $errors[] = "Payment field '{$field}' is required";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function processPayment(TournamentRegistration $registration, array $paymentData): array
    {
        // Mock payment processing - integrate with actual payment processor
        return [
            'success' => true,
            'transaction_id' => 'txn_' . uniqid(),
            'payment_info' => $paymentData
        ];
    }

    private function checkPlayerEligibility(Team $team, array $requirements): array
    {
        $ineligiblePlayers = [];
        
        foreach ($team->players as $player) {
            // Check player age restrictions
            if (isset($requirements['min_player_age'])) {
                $age = $player->birth_date ? now()->diffInYears($player->birth_date) : null;
                if ($age && $age < $requirements['min_player_age']) {
                    $ineligiblePlayers[] = "{$player->username} (too young)";
                }
            }

            // Check player rating requirements
            if (isset($requirements['min_player_rating']) && $player->rating < $requirements['min_player_rating']) {
                $ineligiblePlayers[] = "{$player->username} (rating too low)";
            }
        }

        return $ineligiblePlayers;
    }

    private function checkTeamViolations(Team $team, Event $event): array
    {
        // Check for active bans, violations, etc.
        return [];
    }

    private function checkRegistrationConflicts(Team $team, Event $event): array
    {
        // Check for conflicting event registrations
        return [];
    }

    private function updateWaitingListPositions(Event $event): void
    {
        $waitingRegistrations = TournamentRegistration::where('tournament_id', $event->id)
            ->where('status', 'waiting_list')
            ->orderBy('registered_at')
            ->get();

        foreach ($waitingRegistrations as $index => $registration) {
            $registration->update([
                'notes' => array_merge($registration->notes ?? [], [
                    'waiting_list' => array_merge(
                        $registration->notes['waiting_list'] ?? [],
                        ['position' => $index + 1]
                    )
                ])
            ]);
        }
    }

    // Notification methods
    private function sendRegistrationConfirmation(TournamentRegistration $registration): void
    {
        // Implementation for sending registration confirmation
    }

    private function notifyWaitingListPromotion(TournamentRegistration $registration): void
    {
        // Implementation for notifying waiting list promotion
    }

    private function notifyCheckInOpen(TournamentRegistration $registration, Carbon $start, Carbon $end): void
    {
        // Implementation for notifying check-in is open
    }

    private function notifyAdminsOfPendingRegistration(TournamentRegistration $registration): void
    {
        // Implementation for notifying admins of pending registrations
    }

    private function notifyRegistrationApproval(TournamentRegistration $registration): void
    {
        // Implementation for notifying registration approval
    }

    // Statistics helper methods
    private function getRegistrationTimeline(Collection $registrations): array
    {
        return $registrations->groupBy(function($registration) {
            return $registration->registered_at->format('Y-m-d');
        })->map->count()->toArray();
    }

    private function getRegionalDistribution(Collection $registrations): array
    {
        return $registrations->map(function($registration) {
            return $registration->team->region;
        })->countBy()->toArray();
    }

    private function getTeamSizeDistribution(Collection $registrations): array
    {
        return $registrations->map(function($registration) {
            return $registration->team->players()->count();
        })->countBy()->toArray();
    }

    private function getApprovalMetrics(Collection $registrations): array
    {
        $pending = $registrations->where('status', 'pending');
        $approved = $registrations->where('status', 'approved');
        
        return [
            'pending_count' => $pending->count(),
            'approved_count' => $approved->count(),
            'average_approval_time' => $this->calculateAverageApprovalTime($approved)
        ];
    }

    private function getCheckInMetrics(Collection $registrations): array
    {
        $checkedIn = $registrations->where('status', 'checked_in');
        
        return [
            'checked_in_count' => $checkedIn->count(),
            'check_in_rate' => $registrations->where('status', 'approved')->count() > 0 ? 
                round(($checkedIn->count() / $registrations->where('status', 'approved')->count()) * 100, 2) : 0
        ];
    }

    private function calculateAverageApprovalTime(Collection $approvedRegistrations): float
    {
        if ($approvedRegistrations->isEmpty()) {
            return 0;
        }

        $totalTime = $approvedRegistrations->sum(function($registration) {
            return $registration->approved_at ? 
                $registration->registered_at->diffInHours($registration->approved_at) : 0;
        });

        return round($totalTime / $approvedRegistrations->count(), 2);
    }
}