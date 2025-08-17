<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchMap extends Model
{
    use HasFactory;

    protected $table = 'match_maps';

    protected $fillable = [
        'match_id',
        'map_number',
        'map_name',
        'game_mode',
        'status',
        'team1_score',
        'team2_score',
        'team1_rounds',
        'team2_rounds',
        'winner_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'overtime',
        'overtime_duration',
        'checkpoints_reached',
        'capture_progress',
        'payload_distance',
        'team1_composition',
        'team2_composition',
        'hero_swaps',
        'live_events',
        'current_round',
        'current_phase'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'overtime' => 'boolean',
        'checkpoints_reached' => 'array',
        'capture_progress' => 'array',
        'team1_composition' => 'array',
        'team2_composition' => 'array',
        'hero_swaps' => 'array',
        'live_events' => 'array'
    ];

    /**
     * Relationships
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(MvrlMatch::class, 'match_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(MatchPlayerStat::class, 'map_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'map_id');
    }

    /**
     * Scopes
     */
    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Accessors
     */
    public function getIsLiveAttribute(): bool
    {
        return $this->status === 'live';
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getDurationFormattedAttribute(): string
    {
        if (!$this->duration_seconds) {
            return '00:00';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get mode-specific timer settings
     */
    public function getModeTimerAttribute()
    {
        $timers = [
            'Domination' => [
                'preparation' => 30,
                'rounds' => 3,
                'capture_rate' => 1.2 // seconds per 1%
            ],
            'Convoy' => [
                'preparation' => 30,
                'base_time' => 300,
                'checkpoint_bonus' => [180, 90] // +3 min, +1.5 min
            ],
            'Convergence' => [
                'preparation' => 30,
                'capture_phase' => 240,
                'escort_phase' => 90
            ]
        ];

        return $timers[$this->game_mode] ?? null;
    }

    /**
     * Methods
     */
    public function startMap(): void
    {
        $this->update([
            'status' => 'live',
            'started_at' => now()
        ]);
    }

    public function endMap(): void
    {
        // Determine winner
        $winnerId = null;
        if ($this->game_mode === 'Domination') {
            // Best of rounds
            if ($this->team1_rounds > $this->team2_rounds) {
                $winnerId = $this->match->team1_id;
            } elseif ($this->team2_rounds > $this->team1_rounds) {
                $winnerId = $this->match->team2_id;
            }
        } else {
            // Score based
            if ($this->team1_score > $this->team2_score) {
                $winnerId = $this->match->team1_id;
            } elseif ($this->team2_score > $this->team1_score) {
                $winnerId = $this->match->team2_id;
            }
        }

        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'winner_id' => $winnerId,
            'duration_seconds' => $duration
        ]);

        // Update match series scores
        $this->match->updateSeriesScore();
    }

    /**
     * Update round score for Domination mode
     */
    public function updateRoundScore(int $team1Rounds, int $team2Rounds): void
    {
        $this->update([
            'team1_rounds' => $team1Rounds,
            'team2_rounds' => $team2Rounds
        ]);
    }

    /**
     * Update map score
     */
    public function updateScore(int $team1Score, int $team2Score): void
    {
        $this->update([
            'team1_score' => $team1Score,
            'team2_score' => $team2Score
        ]);
    }

    /**
     * Update team composition
     */
    public function updateComposition(int $teamNumber, array $composition): void
    {
        $field = $teamNumber === 1 ? 'team1_composition' : 'team2_composition';
        $this->update([$field => $composition]);
    }

    /**
     * Add hero swap event
     */
    public function addHeroSwap(int $playerId, string $fromHero, string $toHero, int $gameTime): void
    {
        $swaps = $this->hero_swaps ?? [];
        $swaps[] = [
            'player_id' => $playerId,
            'from_hero' => $fromHero,
            'to_hero' => $toHero,
            'game_time' => $gameTime,
            'timestamp' => now()
        ];
        
        $this->update(['hero_swaps' => $swaps]);
    }

    /**
     * Add live event
     */
    public function addLiveEvent(array $eventData): void
    {
        $events = $this->live_events ?? [];
        $events[] = array_merge($eventData, ['timestamp' => now()]);
        
        $this->update(['live_events' => $events]);
    }

    /**
     * Get live data for real-time updates
     */
    public function getLiveData()
    {
        return [
            'map_id' => $this->id,
            'map_number' => $this->map_number,
            'map_name' => $this->map_name,
            'game_mode' => $this->game_mode,
            'status' => $this->status,
            'team1_score' => $this->team1_score,
            'team2_score' => $this->team2_score,
            'team1_rounds' => $this->team1_rounds,
            'team2_rounds' => $this->team2_rounds,
            'current_round' => $this->current_round,
            'current_phase' => $this->current_phase,
            'duration' => $this->duration_formatted,
            'overtime' => $this->overtime,
            'team1_composition' => $this->team1_composition,
            'team2_composition' => $this->team2_composition
        ];
    }
}