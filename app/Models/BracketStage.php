<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BracketStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'event_id', 'name', 'type', 'stage_order', 'status', 'settings',
        'max_teams', 'current_round', 'total_rounds'
    ];

    protected $casts = [
        'settings' => 'array',
        'max_teams' => 'integer',
        'current_round' => 'integer',
        'total_rounds' => 'integer'
    ];

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BracketMatch::class)->orderBy('round_number')->orderBy('match_number');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(BracketPosition::class);
    }

    public function seedings(): HasMany
    {
        return $this->hasMany(BracketSeeding::class)->orderBy('seed');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(BracketStanding::class)->orderBy('final_placement');
    }

    // Computed attributes
    public function getMatchesByRoundAttribute()
    {
        return $this->matches->groupBy('round_number');
    }

    public function getCurrentRoundAttribute()
    {
        return $this->matches()
                    ->where('status', '!=', 'completed')
                    ->orderBy('round_number')
                    ->first()?->round_number ?? 1;
    }

    public function getCompletedMatchesAttribute()
    {
        return $this->matches()->where('status', 'completed')->get();
    }

    public function getPendingMatchesAttribute()
    {
        return $this->matches()->where('status', 'pending')->get();
    }

    public function getLiveMatchesAttribute()
    {
        return $this->matches()->where('status', 'live')->get();
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->status === 'completed' || 
               $this->matches()->where('status', '!=', 'completed')->count() === 0;
    }

    public function getProgressPercentageAttribute(): int
    {
        $totalMatches = $this->matches()->count();
        if ($totalMatches === 0) return 0;
        
        $completedMatches = $this->matches()->where('status', 'completed')->count();
        return round(($completedMatches / $totalMatches) * 100);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('stage_order');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Helper methods
    public function calculateTotalRounds(int $teamCount = null): int
    {
        $teams = $teamCount ?? $this->max_teams ?? 0;
        
        switch ($this->type) {
            case 'upper_bracket':
            case 'lower_bracket':
                return $teams > 0 ? ceil(log($teams, 2)) : 0;
            
            case 'swiss':
                return $teams > 0 ? ceil(log($teams, 2)) : 0;
            
            case 'round_robin':
                return $teams > 0 ? $teams - 1 : 0;
            
            case 'group_stage':
                // Assuming groups of 4
                return 3;
            
            case 'grand_final':
            case 'third_place':
                return 1;
            
            default:
                return 0;
        }
    }

    public function canStart(): bool
    {
        return $this->status === 'pending' && 
               $this->seedings()->count() >= 2;
    }

    public function start(): bool
    {
        if (!$this->canStart()) {
            return false;
        }

        $this->update([
            'status' => 'active',
            'current_round' => 1
        ]);

        return true;
    }

    public function complete(): bool
    {
        if (!$this->is_complete) {
            return false;
        }

        $this->update(['status' => 'completed']);
        return true;
    }

    // Constants
    public const TYPES = [
        'upper_bracket' => 'Upper Bracket',
        'lower_bracket' => 'Lower Bracket',
        'swiss' => 'Swiss System',
        'round_robin' => 'Round Robin',
        'group_stage' => 'Group Stage',
        'third_place' => 'Third Place Match',
        'grand_final' => 'Grand Final'
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
}