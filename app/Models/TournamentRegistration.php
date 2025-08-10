<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TournamentRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'team_id', 'user_id', 'status', 'registration_data',
        'registered_at', 'checked_in_at', 'approved_at', 'rejected_at',
        'rejection_reason', 'payment_status', 'payment_data', 'notes',
        'emergency_contact', 'special_requirements', 'submission_ip',
        'approval_notes', 'seed', 'group_assignment', 'bracket_position'
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'registration_data' => 'array',
        'payment_data' => 'array',
        'emergency_contact' => 'array',
        'special_requirements' => 'array',
        'seed' => 'integer'
    ];

    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'not_required'
    ];

    // Registration Status
    public const STATUSES = [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'checked_in' => 'Checked In',
        'disqualified' => 'Disqualified',
        'withdrawn' => 'Withdrawn'
    ];

    // Payment Status
    public const PAYMENT_STATUSES = [
        'not_required' => 'Not Required',
        'pending' => 'Payment Pending',
        'completed' => 'Payment Completed',
        'failed' => 'Payment Failed',
        'refunded' => 'Refunded'
    ];

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class); // User who registered the team
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCheckedIn($query)
    {
        return $query->where('status', 'checked_in');
    }

    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    public function scopeForTournament($query, $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeRegisteredBetween($query, $start, $end)
    {
        return $query->whereBetween('registered_at', [$start, $end]);
    }

    // Accessors
    public function getFormattedStatusAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getFormattedPaymentStatusAttribute()
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    public function getCanCheckInAttribute()
    {
        return $this->status === 'approved' && 
               $this->tournament->check_in_open &&
               ($this->payment_status === 'not_required' || $this->payment_status === 'completed');
    }

    public function getCanWithdrawAttribute()
    {
        return in_array($this->status, ['pending', 'approved']) && 
               !$this->tournament->hasStarted();
    }

    public function getRegistrationTimeAttribute()
    {
        return $this->registered_at ? $this->registered_at->diffForHumans() : null;
    }

    public function getIsLateRegistrationAttribute()
    {
        if (!$this->tournament->registration_end || !$this->registered_at) return false;
        
        return $this->registered_at->gt($this->tournament->registration_end);
    }

    // Registration Management Methods
    public function approve(array $approvalData = []): bool
    {
        if ($this->status !== 'pending') return false;

        // Check if tournament has space
        if (!$this->tournament->canRegisterTeam()) return false;

        $this->status = 'approved';
        $this->approved_at = now();
        $this->approval_notes = $approvalData['notes'] ?? null;
        $this->seed = $approvalData['seed'] ?? null;
        $this->group_assignment = $approvalData['group_assignment'] ?? null;
        $this->save();

        // Add team to tournament
        $this->tournament->teams()->attach($this->team_id, [
            'status' => 'registered',
            'registered_at' => $this->registered_at,
            'seed' => $this->seed
        ]);

        return true;
    }

    public function reject(string $reason = ''): bool
    {
        if ($this->status !== 'pending') return false;

        $this->status = 'rejected';
        $this->rejected_at = now();
        $this->rejection_reason = $reason;
        $this->save();

        return true;
    }

    public function checkIn(): bool
    {
        if (!$this->can_check_in) return false;

        $this->status = 'checked_in';
        $this->checked_in_at = now();
        $this->save();

        // Update tournament team status
        $this->tournament->teams()->updateExistingPivot($this->team_id, [
            'status' => 'checked_in',
            'checked_in_at' => now()
        ]);

        return true;
    }

    public function withdraw(): bool
    {
        if (!$this->can_withdraw) return false;

        $originalStatus = $this->status;
        $this->status = 'withdrawn';
        $this->save();

        // Remove from tournament if was approved
        if ($originalStatus === 'approved') {
            $this->tournament->teams()->detach($this->team_id);
            $this->tournament->decrement('team_count');
        }

        return true;
    }

    public function disqualify(string $reason = ''): bool
    {
        if ($this->status === 'withdrawn') return false;

        $this->status = 'disqualified';
        $this->rejection_reason = $reason;
        $this->save();

        // Update tournament team status
        $this->tournament->teams()->updateExistingPivot($this->team_id, [
            'status' => 'disqualified'
        ]);

        return true;
    }

    public function processPayment(array $paymentData): bool
    {
        if ($this->payment_status !== 'pending') return false;

        $this->payment_status = 'completed';
        $this->payment_data = array_merge($this->payment_data ?? [], [
            'completed_at' => now()->toDateTimeString(),
            'payment_info' => $paymentData
        ]);
        $this->save();

        return true;
    }

    public function refundPayment(array $refundData = []): bool
    {
        if ($this->payment_status !== 'completed') return false;

        $this->payment_status = 'refunded';
        $this->payment_data = array_merge($this->payment_data ?? [], [
            'refunded_at' => now()->toDateTimeString(),
            'refund_info' => $refundData
        ]);
        $this->save();

        return true;
    }

    // Validation Methods
    public function validateRegistrationData(): array
    {
        $errors = [];
        $requirements = $this->tournament->registration_requirements ?? [];

        // Check team player count
        if (isset($requirements['min_players'])) {
            $playerCount = $this->team->players()->count();
            if ($playerCount < $requirements['min_players']) {
                $errors[] = "Team must have at least {$requirements['min_players']} players";
            }
        }

        if (isset($requirements['max_players'])) {
            $playerCount = $this->team->players()->count();
            if ($playerCount > $requirements['max_players']) {
                $errors[] = "Team cannot have more than {$requirements['max_players']} players";
            }
        }

        // Check region restrictions
        if (isset($requirements['allowed_regions']) && !empty($requirements['allowed_regions'])) {
            if (!in_array($this->team->region, $requirements['allowed_regions'])) {
                $errors[] = "Team region '{$this->team->region}' is not allowed for this tournament";
            }
        }

        // Check minimum ELO rating
        if (isset($requirements['min_elo']) && $this->team->elo_rating < $requirements['min_elo']) {
            $errors[] = "Team ELO rating must be at least {$requirements['min_elo']}";
        }

        // Check required fields in registration data
        $requiredFields = $requirements['required_fields'] ?? [];
        foreach ($requiredFields as $field) {
            if (!isset($this->registration_data[$field]) || empty($this->registration_data[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }

        // Check for existing registration
        $existingRegistration = self::where('tournament_id', $this->tournament_id)
                                   ->where('team_id', $this->team_id)
                                   ->where('id', '!=', $this->id)
                                   ->whereNotIn('status', ['rejected', 'withdrawn'])
                                   ->first();

        if ($existingRegistration) {
            $errors[] = "Team is already registered for this tournament";
        }

        return $errors;
    }

    public function getRegistrationSummary(): array
    {
        return [
            'tournament' => [
                'id' => $this->tournament->id,
                'name' => $this->tournament->name,
                'type' => $this->tournament->type,
                'format' => $this->tournament->format,
                'start_date' => $this->tournament->start_date->toDateTimeString(),
                'prize_pool' => $this->tournament->formatted_prize_pool
            ],
            'team' => [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'short_name' => $this->team->short_name,
                'region' => $this->team->region,
                'logo' => $this->team->logo,
                'player_count' => $this->team->players()->count()
            ],
            'registration' => [
                'status' => $this->formatted_status,
                'registered_at' => $this->registration_time,
                'payment_status' => $this->formatted_payment_status,
                'seed' => $this->seed,
                'can_check_in' => $this->can_check_in,
                'can_withdraw' => $this->can_withdraw
            ],
            'tournament_info' => [
                'registration_open' => $this->tournament->registration_open,
                'check_in_open' => $this->tournament->check_in_open,
                'current_teams' => $this->tournament->current_team_count,
                'max_teams' => $this->tournament->max_teams,
                'spots_remaining' => $this->tournament->max_teams - $this->tournament->current_team_count
            ]
        ];
    }

    // Static Helper Methods
    public static function getRegistrationStats($tournamentId): array
    {
        $registrations = self::where('tournament_id', $tournamentId)->get();

        $stats = [
            'total' => $registrations->count(),
            'by_status' => [],
            'by_payment_status' => [],
            'checked_in_count' => 0,
            'recent_registrations' => 0,
            'pending_review' => 0
        ];

        // Count by status
        foreach (self::STATUSES as $key => $label) {
            $stats['by_status'][$key] = $registrations->where('status', $key)->count();
        }

        // Count by payment status
        foreach (self::PAYMENT_STATUSES as $key => $label) {
            $stats['by_payment_status'][$key] = $registrations->where('payment_status', $key)->count();
        }

        $stats['checked_in_count'] = $stats['by_status']['checked_in'];
        $stats['pending_review'] = $stats['by_status']['pending'];
        
        // Recent registrations (last 24 hours)
        $stats['recent_registrations'] = $registrations
            ->where('registered_at', '>=', now()->subDay())
            ->count();

        return $stats;
    }

    public static function createRegistration($tournamentId, $teamId, $userId, $registrationData = []): ?self
    {
        $tournament = Tournament::find($tournamentId);
        $team = Team::find($teamId);

        if (!$tournament || !$team) return null;

        // Check if registration is open
        if (!$tournament->registration_open) return null;

        // Check if team can register
        if (!$tournament->canRegisterTeam()) return null;

        $registration = new self([
            'tournament_id' => $tournamentId,
            'team_id' => $teamId,
            'user_id' => $userId,
            'registration_data' => $registrationData,
            'registered_at' => now(),
            'submission_ip' => request()->ip()
        ]);

        // Validate registration data
        $errors = $registration->validateRegistrationData();
        if (!empty($errors)) {
            // Handle validation errors (could throw exception or return error)
            return null;
        }

        $registration->save();
        
        return $registration;
    }
}