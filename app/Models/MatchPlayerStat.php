<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayerStat extends Model
{
    use HasFactory;

    protected $table = 'player_match_stats';

    protected $fillable = [
        'match_id',
        'map_id',
        'player_id',
        'team_id',
        'hero',
        'hero_role',
        'time_played_seconds',
        
        // Combat Stats (VLR.gg style)
        'eliminations',
        'assists', 
        'deaths',
        'kda',
        'damage_dealt',
        'damage_taken',
        'healing_done',
        'damage_blocked',
        
        // Per-Round/Average Stats (like VLR.gg KPR, APR)
        'eliminations_per_round',
        'assists_per_round',
        'deaths_per_round',
        'damage_per_round',
        'healing_per_round',
        
        // Objective Stats
        'objective_time',
        'objective_kills',
        'payload_distance',
        'capture_progress',
        
        // Ultimate Stats
        'ultimates_earned',
        'ultimates_used',
        'ultimate_eliminations',
        
        // Accuracy Stats
        'shots_fired',
        'shots_hit',
        'critical_hits',
        'accuracy_percentage',
        
        // Advanced Stats (VLR.gg style)
        'first_kills', // FKPR equivalent
        'first_deaths', // FDPR equivalent
        'best_killstreak',
        'solo_kills',
        'environmental_kills',
        'final_blows',
        'melee_final_blows',
        'multikills',
        
        // Hero-specific stats
        'hero_specific_stats',
        
        // Performance Rating (like VLR.gg Rating 2.0)
        'performance_rating',
        'combat_score', // ACS equivalent
        'econ_rating', // Economic efficiency
        'kast_percentage', // Kill, Assist, Survived, Traded %
        
        // Awards
        'player_of_the_match',
        'player_of_the_map'
    ];

    protected $casts = [
        'kda' => 'decimal:2',
        'payload_distance' => 'decimal:2',
        'accuracy_percentage' => 'decimal:2',
        'performance_rating' => 'decimal:2',
        'combat_score' => 'decimal:2',
        'econ_rating' => 'decimal:2',
        'kast_percentage' => 'decimal:2',
        'eliminations_per_round' => 'decimal:2',
        'assists_per_round' => 'decimal:2',
        'deaths_per_round' => 'decimal:2',
        'damage_per_round' => 'decimal:2',
        'healing_per_round' => 'decimal:2',
        'hero_specific_stats' => 'array',
        'player_of_the_match' => 'boolean',
        'player_of_the_map' => 'boolean'
    ];

    /**
     * Relationships
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(MatchMap::class, 'round_id', 'id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scopes
     */
    public function scopeForHero($query, $hero)
    {
        return $query->where('hero', $hero);
    }

    public function scopeForPlayer($query, $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Accessors
     */
    public function getKdaCalculatedAttribute(): float
    {
        if ($this->deaths === 0) {
            return $this->eliminations + $this->assists;
        }
        return round(($this->eliminations + $this->assists) / $this->deaths, 2);
    }

    public function getTimePlayedFormattedAttribute(): string
    {
        $minutes = floor($this->time_played_seconds / 60);
        $seconds = $this->time_played_seconds % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getKillParticipationAttribute(): float
    {
        $teamKills = $this->match->playerStats()
            ->where('team_id', $this->team_id)
            ->where('map_id', $this->map_id)
            ->sum('eliminations');
            
        if ($teamKills === 0) return 0;
        
        return round((($this->eliminations + $this->assists) / $teamKills) * 100, 1);
    }

    /**
     * Calculate advanced metrics
     */
    public function calculateAdvancedMetrics(): void
    {
        // Calculate KDA
        $this->kda = $this->kda_calculated;
        
        // Calculate per-round stats (assuming rounds from map data)
        $rounds = $this->map ? ($this->map->team1_rounds + $this->map->team2_rounds) : 1;
        if ($rounds > 0) {
            $this->eliminations_per_round = round($this->eliminations / $rounds, 2);
            $this->assists_per_round = round($this->assists / $rounds, 2);
            $this->deaths_per_round = round($this->deaths / $rounds, 2);
            $this->damage_per_round = round($this->damage_dealt / $rounds, 2);
            $this->healing_per_round = round($this->healing_done / $rounds, 2);
        }
        
        // Calculate accuracy
        if ($this->shots_fired > 0) {
            $this->accuracy_percentage = round(($this->shots_hit / $this->shots_fired) * 100, 2);
        }
        
        // Calculate Combat Score (similar to VLR.gg ACS)
        // Base formula: damage + (kills * 150) + (assists * 50) + (first kills * 100)
        $this->combat_score = round(
            ($this->damage_dealt / 10) + 
            ($this->eliminations * 150) + 
            ($this->assists * 50) + 
            ($this->first_kills * 100)
        , 2);
        
        // Calculate KAST% (Kill, Assist, Survived, Traded)
        // For now, simplified version based on participation
        $participated = ($this->eliminations > 0 || $this->assists > 0) ? 1 : 0;
        $survived = ($this->deaths === 0) ? 1 : 0;
        $this->kast_percentage = round((($participated + $survived) / 2) * 100, 2);
        
        // Calculate Performance Rating (similar to VLR.gg Rating 2.0)
        // Complex formula considering multiple factors
        $killWeight = 1.0;
        $deathWeight = -0.7;
        $assistWeight = 0.4;
        $firstKillWeight = 1.5;
        $kdaDiff = $this->kda_calculated - 1.0;
        
        $this->performance_rating = round(
            1.0 + // Base rating
            ($kdaDiff * 0.2) + // KDA influence
            (($this->first_kills / max($rounds, 1)) * $firstKillWeight) +
            (($this->combat_score / 300) * 0.3) // Combat score influence
        , 2);
        
        $this->save();
    }

    /**
     * Get hero-specific stat
     */
    public function getHeroStat($key)
    {
        return $this->hero_specific_stats[$key] ?? null;
    }

    /**
     * Set hero-specific stat
     */
    public function setHeroStat($key, $value): void
    {
        $stats = $this->hero_specific_stats ?? [];
        $stats[$key] = $value;
        $this->hero_specific_stats = $stats;
        $this->save();
    }

    /**
     * Get stats for player profile display (VLR.gg style)
     */
    public function getProfileDisplayStats()
    {
        return [
            'hero' => $this->hero,
            'matches_played' => 1,
            'rating' => $this->performance_rating,
            'acs' => $this->combat_score,
            'kd' => $this->kda_calculated,
            'adr' => $this->damage_per_round,
            'kast' => $this->kast_percentage,
            'kpr' => $this->eliminations_per_round,
            'apr' => $this->assists_per_round,
            'fkpr' => $this->first_kills / max(1, $this->map->team1_rounds + $this->map->team2_rounds),
            'fdpr' => $this->first_deaths / max(1, $this->map->team1_rounds + $this->map->team2_rounds),
            'hs' => $this->accuracy_percentage,
            'kills' => $this->eliminations,
            'deaths' => $this->deaths,
            'assists' => $this->assists
        ];
    }
}