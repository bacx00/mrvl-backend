<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\Event;
use App\Models\TournamentRegistration;
use App\Models\BracketMatch;
use App\Events\TournamentPhaseChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TournamentPhaseManagementService
{
    protected $liveUpdateService;
    
    public function __construct(TournamentLiveUpdateService $liveUpdateService)
    {
        $this->liveUpdateService = $liveUpdateService;
    }

    /**
     * Define tournament phases and their progression logic
     */
    public function createTournamentPhases(Event $event, array $phaseDefinitions = []): array
    {
        DB::beginTransaction();
        
        try {
            $phases = [];
            $defaultPhases = $this->getDefaultPhaseDefinitions($event);
            $phasesToCreate = !empty($phaseDefinitions) ? $phaseDefinitions : $defaultPhases;
            
            foreach ($phasesToCreate as $index => $phaseData) {
                $phase = TournamentPhase::create([
                    'tournament_id' => $event->id,
                    'name' => $phaseData['name'],
                    'slug' => \Str::slug($phaseData['name']),
                    'phase_type' => $phaseData['type'],
                    'phase_order' => $index + 1,
                    'description' => $phaseData['description'] ?? null,
                    'start_date' => isset($phaseData['start_date']) ? Carbon::parse($phaseData['start_date']) : null,
                    'end_date' => isset($phaseData['end_date']) ? Carbon::parse($phaseData['end_date']) : null,
                    'settings' => $phaseData['settings'] ?? [],
                    'seeding_method' => $phaseData['seeding_method'] ?? 'random',
                    'team_count' => $phaseData['team_count'] ?? 0,
                    'advancement_count' => $phaseData['advancement_count'] ?? 0,
                    'elimination_count' => $phaseData['elimination_count'] ?? 0,
                    'match_format' => $phaseData['match_format'] ?? 'bo3',
                    'map_pool' => $phaseData['map_pool'] ?? [],
                    'status' => $index === 0 ? 'active' : 'pending'
                ]);
                
                $phases[] = $phase;
            }
            
            // Set initial phase
            $event->update([
                'current_phase' => $phases[0]->phase_type,
                'phase_data' => [
                    'current_phase_id' => $phases[0]->id,
                    'total_phases' => count($phases)
                ]
            ]);
            
            DB::commit();
            Log::info('Tournament phases created', ['event_id' => $event->id, 'phases' => count($phases)]);
            
            return $phases;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create tournament phases: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Automatic phase progression based on schedule and completion
     */
    public function checkAndProgressPhases(): array
    {
        $progressedEvents = [];
        
        // Get events that might need phase progression
        $events = Event::where('status', 'ongoing')
            ->whereHas('phases', function($query) {
                $query->where('status', 'active');
            })
            ->with(['phases', 'matches'])
            ->get();

        foreach ($events as $event) {
            $progression = $this->evaluatePhaseProgression($event);
            if ($progression['should_progress']) {
                $result = $this->progressToNextPhase($event, $progression['reason']);
                if ($result['success']) {
                    $progressedEvents[] = [
                        'event_id' => $event->id,
                        'from_phase' => $progression['current_phase'],
                        'to_phase' => $result['new_phase'],
                        'reason' => $progression['reason']
                    ];
                }
            }
        }

        return $progressedEvents;
    }

    /**
     * Manual phase progression with validation
     */
    public function progressToNextPhase(Event $event, string $reason = 'manual'): array
    {
        DB::beginTransaction();
        
        try {
            $currentPhase = $this->getCurrentPhase($event);
            if (!$currentPhase) {
                return ['success' => false, 'error' => 'No active phase found'];
            }

            $nextPhase = $this->getNextPhase($event, $currentPhase);
            if (!$nextPhase) {
                // Tournament is complete
                return $this->completeTournament($event);
            }

            // Validate progression requirements
            $validation = $this->validatePhaseProgression($currentPhase, $nextPhase);
            if (!$validation['valid']) {
                return ['success' => false, 'errors' => $validation['errors']];
            }

            // Complete current phase
            $this->completePhase($currentPhase);
            
            // Start next phase
            $this->startPhase($nextPhase, $currentPhase);
            
            // Update event
            $event->update([
                'current_phase' => $nextPhase->phase_type,
                'phase_data' => array_merge($event->phase_data ?? [], [
                    'current_phase_id' => $nextPhase->id,
                    'progression_reason' => $reason,
                    'progressed_at' => now()->toISOString()
                ])
            ]);

            // Broadcast phase change
            $this->liveUpdateService->broadcastPhaseChange($event, $nextPhase, $currentPhase, [
                'reason' => $reason
            ]);

            DB::commit();
            
            Log::info('Tournament phase progressed', [
                'event_id' => $event->id,
                'from_phase' => $currentPhase->phase_type,
                'to_phase' => $nextPhase->phase_type,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'new_phase' => $nextPhase->phase_type,
                'previous_phase' => $currentPhase->phase_type
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to progress tournament phase: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Phase progression failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Rollback phase transition with data preservation
     */
    public function rollbackPhaseTransition(Event $event, int $targetPhaseId): array
    {
        DB::beginTransaction();
        
        try {
            $currentPhase = $this->getCurrentPhase($event);
            $targetPhase = TournamentPhase::findOrFail($targetPhaseId);
            
            // Validate rollback is possible
            if ($targetPhase->phase_order >= $currentPhase->phase_order) {
                return ['success' => false, 'error' => 'Cannot rollback to same or later phase'];
            }

            // Create backup of current state
            $backup = $this->createPhaseBackup($event, $currentPhase);
            
            // Reset phases
            $this->resetPhasesToTarget($event, $targetPhase);
            
            // Update event
            $event->update([
                'current_phase' => $targetPhase->phase_type,
                'phase_data' => array_merge($event->phase_data ?? [], [
                    'current_phase_id' => $targetPhase->id,
                    'rollback_performed' => true,
                    'rollback_backup_id' => $backup['id'],
                    'rolled_back_at' => now()->toISOString()
                ])
            ]);

            DB::commit();
            
            Log::info('Tournament phase rolled back', [
                'event_id' => $event->id,
                'from_phase' => $currentPhase->phase_type,
                'to_phase' => $targetPhase->phase_type,
                'backup_id' => $backup['id']
            ]);

            return [
                'success' => true,
                'target_phase' => $targetPhase->phase_type,
                'backup_id' => $backup['id']
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to rollback tournament phase: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Phase rollback failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Phase-specific permissions and actions
     */
    public function getPhasePermissions(Event $event, string $userRole): array
    {
        $currentPhase = $this->getCurrentPhase($event);
        if (!$currentPhase) {
            return [];
        }

        $basePermissions = [
            'admin' => ['view', 'edit', 'progress', 'rollback', 'settings'],
            'moderator' => ['view', 'edit', 'progress'],
            'organizer' => ['view', 'edit', 'progress'],
            'user' => ['view']
        ];

        $phaseSpecificPermissions = [
            'registration' => [
                'admin' => ['approve_registrations', 'manage_teams', 'edit_requirements'],
                'moderator' => ['approve_registrations', 'manage_teams'],
                'organizer' => ['approve_registrations', 'manage_teams']
            ],
            'check_in' => [
                'admin' => ['open_check_in', 'check_in_teams', 'extend_check_in'],
                'moderator' => ['check_in_teams'],
                'organizer' => ['check_in_teams']
            ],
            'bracket_generation' => [
                'admin' => ['generate_bracket', 'edit_seeding', 'manual_bracket'],
                'organizer' => ['generate_bracket', 'edit_seeding']
            ],
            'group_stage' => [
                'admin' => ['create_matches', 'edit_matches', 'advance_teams'],
                'moderator' => ['edit_matches'],
                'organizer' => ['create_matches', 'edit_matches']
            ],
            'playoffs' => [
                'admin' => ['create_matches', 'edit_matches', 'resolve_disputes'],
                'moderator' => ['edit_matches', 'resolve_disputes'],
                'organizer' => ['create_matches', 'edit_matches']
            ]
        ];

        $permissions = $basePermissions[$userRole] ?? [];
        $phasePermissions = $phaseSpecificPermissions[$currentPhase->phase_type][$userRole] ?? [];

        return array_merge($permissions, $phasePermissions);
    }

    /**
     * Get available actions for current phase
     */
    public function getPhaseActions(Event $event): array
    {
        $currentPhase = $this->getCurrentPhase($event);
        if (!$currentPhase) {
            return [];
        }

        $actions = [];

        switch ($currentPhase->phase_type) {
            case 'registration':
                $actions = [
                    'open_registration' => !$event->registration_open,
                    'close_registration' => $event->registration_open,
                    'approve_registrations' => $this->hasPendingRegistrations($event),
                    'extend_deadline' => $event->registration_open,
                    'progress_to_check_in' => $this->canProgressFromRegistration($event)
                ];
                break;

            case 'check_in':
                $actions = [
                    'open_check_in' => !$this->isCheckInOpen($event),
                    'close_check_in' => $this->isCheckInOpen($event),
                    'check_in_team' => true,
                    'progress_to_bracket' => $this->canProgressFromCheckIn($event)
                ];
                break;

            case 'bracket_generation':
                $actions = [
                    'generate_bracket' => !$this->hasBracket($event),
                    'edit_seeding' => true,
                    'finalize_bracket' => $this->hasBracket($event),
                    'progress_to_matches' => $this->canProgressFromBracket($event)
                ];
                break;

            case 'group_stage':
                $actions = [
                    'create_matches' => true,
                    'start_round' => $this->canStartNextRound($event),
                    'complete_round' => $this->canCompleteCurrentRound($event),
                    'progress_to_playoffs' => $this->canProgressFromGroupStage($event)
                ];
                break;

            case 'playoffs':
                $actions = [
                    'advance_winners' => $this->canAdvanceWinners($event),
                    'create_final_matches' => $this->canCreateFinalMatches($event),
                    'complete_tournament' => $this->canCompleteTournament($event)
                ];
                break;
        }

        return $actions;
    }

    /**
     * Get comprehensive phase status
     */
    public function getPhaseStatus(Event $event): array
    {
        $phases = $event->phases()->orderBy('phase_order')->get();
        $currentPhase = $this->getCurrentPhase($event);

        return [
            'current_phase' => $currentPhase ? [
                'id' => $currentPhase->id,
                'name' => $currentPhase->name,
                'type' => $currentPhase->phase_type,
                'status' => $currentPhase->status,
                'progress' => $this->calculatePhaseProgress($currentPhase),
                'estimated_completion' => $this->estimatePhaseCompletion($currentPhase),
                'actions' => $this->getPhaseActions($event)
            ] : null,
            'all_phases' => $phases->map(function($phase) {
                return [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'type' => $phase->phase_type,
                    'order' => $phase->phase_order,
                    'status' => $phase->status,
                    'start_date' => $phase->start_date,
                    'end_date' => $phase->end_date,
                    'completed_at' => $phase->completed_at,
                    'progress' => $this->calculatePhaseProgress($phase)
                ];
            }),
            'overall_progress' => $this->calculateTournamentProgress($event)
        ];
    }

    // Helper methods
    private function getDefaultPhaseDefinitions(Event $event): array
    {
        $basePhases = [
            [
                'name' => 'Registration',
                'type' => 'registration',
                'description' => 'Team registration and approval phase',
                'settings' => ['auto_approve' => false]
            ],
            [
                'name' => 'Check-in',
                'type' => 'check_in',
                'description' => 'Team check-in before tournament starts',
                'settings' => ['check_in_window_hours' => 2]
            ]
        ];

        // Add format-specific phases
        switch ($event->format) {
            case 'single_elimination':
            case 'double_elimination':
                $basePhases[] = [
                    'name' => 'Bracket Generation',
                    'type' => 'bracket_generation',
                    'description' => 'Generate tournament bracket and seeding',
                    'seeding_method' => 'rating'
                ];
                $basePhases[] = [
                    'name' => 'Playoffs',
                    'type' => 'playoffs',
                    'description' => 'Elimination matches',
                    'match_format' => 'bo3'
                ];
                break;

            case 'round_robin':
                $basePhases[] = [
                    'name' => 'Round Robin',
                    'type' => 'round_robin',
                    'description' => 'Round robin group stage',
                    'match_format' => 'bo3'
                ];
                break;

            case 'swiss':
                $basePhases[] = [
                    'name' => 'Swiss Rounds',
                    'type' => 'swiss',
                    'description' => 'Swiss system rounds',
                    'match_format' => 'bo3'
                ];
                break;

            default:
                $basePhases[] = [
                    'name' => 'Group Stage',
                    'type' => 'group_stage',
                    'description' => 'Initial group stage matches',
                    'advancement_count' => 8
                ];
                $basePhases[] = [
                    'name' => 'Playoffs',
                    'type' => 'playoffs',
                    'description' => 'Playoff elimination matches',
                    'match_format' => 'bo5'
                ];
        }

        return $basePhases;
    }

    private function getCurrentPhase(Event $event): ?TournamentPhase
    {
        return $event->phases()->where('status', 'active')->first();
    }

    private function getNextPhase(Event $event, TournamentPhase $currentPhase): ?TournamentPhase
    {
        return $event->phases()
            ->where('phase_order', '>', $currentPhase->phase_order)
            ->orderBy('phase_order')
            ->first();
    }

    private function evaluatePhaseProgression(Event $event): array
    {
        $currentPhase = $this->getCurrentPhase($event);
        if (!$currentPhase) {
            return ['should_progress' => false];
        }

        $reasons = [];

        // Check time-based progression
        if ($currentPhase->end_date && now()->gte($currentPhase->end_date)) {
            $reasons[] = 'Phase end time reached';
        }

        // Check completion-based progression
        switch ($currentPhase->phase_type) {
            case 'registration':
                if ($this->canProgressFromRegistration($event)) {
                    $reasons[] = 'Registration requirements met';
                }
                break;

            case 'check_in':
                if ($this->canProgressFromCheckIn($event)) {
                    $reasons[] = 'Check-in phase complete';
                }
                break;

            case 'group_stage':
                if ($this->canProgressFromGroupStage($event)) {
                    $reasons[] = 'Group stage matches complete';
                }
                break;
        }

        return [
            'should_progress' => !empty($reasons),
            'current_phase' => $currentPhase->phase_type,
            'reason' => implode(', ', $reasons)
        ];
    }

    private function validatePhaseProgression(TournamentPhase $currentPhase, TournamentPhase $nextPhase): array
    {
        $errors = [];

        // Phase-specific validation
        switch ($nextPhase->phase_type) {
            case 'check_in':
                if (!$this->hasMinimumTeams($currentPhase->tournament)) {
                    $errors[] = 'Minimum number of teams not reached';
                }
                break;

            case 'bracket_generation':
                if (!$this->allTeamsCheckedIn($currentPhase->tournament)) {
                    $errors[] = 'Not all teams have checked in';
                }
                break;

            case 'playoffs':
                if (!$this->groupStageComplete($currentPhase->tournament)) {
                    $errors[] = 'Group stage matches not complete';
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function completePhase(TournamentPhase $phase): void
    {
        $phase->update([
            'status' => 'completed',
            'completed_at' => now(),
            'results_data' => $this->collectPhaseResults($phase)
        ]);
    }

    private function startPhase(TournamentPhase $phase, TournamentPhase $previousPhase = null): void
    {
        $phase->update([
            'status' => 'active',
            'is_active' => true,
            'start_date' => now()
        ]);

        // Execute phase-specific initialization
        $this->initializePhase($phase, $previousPhase);
    }

    private function initializePhase(TournamentPhase $phase, TournamentPhase $previousPhase = null): void
    {
        switch ($phase->phase_type) {
            case 'check_in':
                // Open check-in window
                break;

            case 'bracket_generation':
                // Prepare for bracket generation
                break;

            case 'group_stage':
                // Create group stage matches
                break;

            case 'playoffs':
                // Create playoff bracket
                break;
        }
    }

    private function completeTournament(Event $event): array
    {
        $event->update([
            'status' => 'completed',
            'current_phase' => 'completed',
            'end_date' => now()
        ]);

        return [
            'success' => true,
            'tournament_complete' => true
        ];
    }

    private function createPhaseBackup(Event $event, TournamentPhase $phase): array
    {
        $backupId = uniqid('backup_');
        
        $backup = [
            'id' => $backupId,
            'event_id' => $event->id,
            'phase_id' => $phase->id,
            'created_at' => now()->toISOString(),
            'data' => [
                'phase_data' => $phase->toArray(),
                'matches' => $event->matches()->get()->toArray(),
                'registrations' => TournamentRegistration::where('tournament_id', $event->id)->get()->toArray()
            ]
        ];

        Cache::put("phase_backup_{$backupId}", $backup, now()->addDays(30));

        return $backup;
    }

    private function resetPhasesToTarget(Event $event, TournamentPhase $targetPhase): void
    {
        // Reset all phases after target back to pending
        $event->phases()
            ->where('phase_order', '>', $targetPhase->phase_order)
            ->update([
                'status' => 'pending',
                'is_active' => false,
                'completed_at' => null,
                'results_data' => null
            ]);

        // Activate target phase
        $targetPhase->update([
            'status' => 'active',
            'is_active' => true
        ]);
    }

    private function collectPhaseResults(TournamentPhase $phase): array
    {
        // Collect phase-specific results data
        return [
            'completed_at' => now()->toISOString(),
            'duration' => $phase->start_date ? now()->diffInHours($phase->start_date) : 0,
            'teams_advanced' => $phase->advancement_count,
            'teams_eliminated' => $phase->elimination_count
        ];
    }

    private function calculatePhaseProgress(TournamentPhase $phase): float
    {
        switch ($phase->phase_type) {
            case 'registration':
                $current = TournamentRegistration::where('tournament_id', $phase->tournament_id)
                    ->whereIn('status', ['approved', 'checked_in'])
                    ->count();
                $target = $phase->tournament->max_teams;
                return $target > 0 ? min(100, ($current / $target) * 100) : 0;

            case 'group_stage':
            case 'playoffs':
                $totalMatches = BracketMatch::where('tournament_id', $phase->tournament_id)->count();
                $completedMatches = BracketMatch::where('tournament_id', $phase->tournament_id)
                    ->where('status', 'completed')
                    ->count();
                return $totalMatches > 0 ? ($completedMatches / $totalMatches) * 100 : 0;

            default:
                return $phase->status === 'completed' ? 100 : 0;
        }
    }

    private function calculateTournamentProgress(Event $event): float
    {
        $phases = $event->phases;
        if ($phases->isEmpty()) return 0;

        $totalProgress = $phases->sum(function($phase) {
            return $this->calculatePhaseProgress($phase);
        });

        return $totalProgress / $phases->count();
    }

    private function estimatePhaseCompletion(TournamentPhase $phase): ?Carbon
    {
        // Implement estimation logic based on phase type and current progress
        return null;
    }

    // Phase-specific validation methods
    private function hasPendingRegistrations(Event $event): bool
    {
        return TournamentRegistration::where('tournament_id', $event->id)
            ->where('status', 'pending')
            ->exists();
    }

    private function canProgressFromRegistration(Event $event): bool
    {
        $approvedCount = TournamentRegistration::where('tournament_id', $event->id)
            ->where('status', 'approved')
            ->count();
        
        return $approvedCount >= ($event->min_teams ?? 4);
    }

    private function canProgressFromCheckIn(Event $event): bool
    {
        $checkedInCount = TournamentRegistration::where('tournament_id', $event->id)
            ->where('status', 'checked_in')
            ->count();
        
        return $checkedInCount >= ($event->min_teams ?? 4);
    }

    private function canProgressFromBracket(Event $event): bool
    {
        return $this->hasBracket($event);
    }

    private function canProgressFromGroupStage(Event $event): bool
    {
        return $this->groupStageComplete($event);
    }

    private function isCheckInOpen(Event $event): bool
    {
        return $event->check_in_start && $event->check_in_end &&
               now()->between($event->check_in_start, $event->check_in_end);
    }

    private function hasBracket(Event $event): bool
    {
        return BracketMatch::where('tournament_id', $event->id)->exists();
    }

    private function hasMinimumTeams(Event $event): bool
    {
        return $event->current_team_count >= ($event->min_teams ?? 4);
    }

    private function allTeamsCheckedIn(Event $event): bool
    {
        $approvedCount = TournamentRegistration::where('tournament_id', $event->id)
            ->where('status', 'approved')
            ->count();
        
        $checkedInCount = TournamentRegistration::where('tournament_id', $event->id)
            ->where('status', 'checked_in')
            ->count();
        
        return $checkedInCount >= $approvedCount;
    }

    private function groupStageComplete(Event $event): bool
    {
        $groupMatches = BracketMatch::where('tournament_id', $event->id)
            ->where('bracket_type', 'group')
            ->count();
        
        $completedGroupMatches = BracketMatch::where('tournament_id', $event->id)
            ->where('bracket_type', 'group')
            ->where('status', 'completed')
            ->count();
        
        return $groupMatches > 0 && $groupMatches === $completedGroupMatches;
    }

    private function canStartNextRound(Event $event): bool
    {
        // Implementation for round progression logic
        return true;
    }

    private function canCompleteCurrentRound(Event $event): bool
    {
        // Implementation for round completion logic
        return true;
    }

    private function canAdvanceWinners(Event $event): bool
    {
        // Implementation for winner advancement logic
        return true;
    }

    private function canCreateFinalMatches(Event $event): bool
    {
        // Implementation for final match creation logic
        return true;
    }

    private function canCompleteTournament(Event $event): bool
    {
        // Check if final match is complete
        $finalMatch = BracketMatch::where('tournament_id', $event->id)
            ->where('round', 'final')
            ->first();
        
        return $finalMatch && $finalMatch->status === 'completed';
    }
}