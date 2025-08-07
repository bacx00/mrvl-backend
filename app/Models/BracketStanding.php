<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BracketStanding extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'event_id', 'team_id', 'final_placement', 'placement_range',
        'prize_money', 'total_matches_played', 'matches_won', 'matches_lost',
        'games_won', 'games_lost', 'swiss_score', 'buchholz_score',
        'placement_data', 'eliminated_at'
    ];

    protected $casts = [
        'prize_money' => 'decimal:2',
        'final_placement' => 'integer',
        'total_matches_played' => 'integer',
        'matches_won' => 'integer',
        'matches_lost' => 'integer',
        'games_won' => 'integer',
        'games_lost' => 'integer',
        'swiss_score' => 'decimal:2',
        'buchholz_score' => 'integer',
        'placement_data' => 'array',
        'eliminated_at' => 'datetime'
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Computed attributes
    public function getPlacementDisplayAttribute(): string
    {
        return $this->placement_range ?? $this->getOrdinalPlacement($this->final_placement);
    }

    public function getMatchWinRateAttribute(): float
    {
        if ($this->total_matches_played === 0) return 0.0;
        return round(($this->matches_won / $this->total_matches_played) * 100, 1);
    }

    public function getGameWinRateAttribute(): float
    {
        $totalGames = $this->games_won + $this->games_lost;
        if ($totalGames === 0) return 0.0;
        return round(($this->games_won / $totalGames) * 100, 1);
    }

    public function getPrizeMoneyDisplayAttribute(): ?string
    {
        if (!$this->prize_money) return null;
        return '$' . number_format($this->prize_money, 0);
    }

    public function getIsEliminatedAttribute(): bool
    {
        return $this->eliminated_at !== null;
    }

    public function getPerformanceGradeAttribute(): string
    {
        $placement = $this->final_placement;
        return match(true) {
            $placement === 1 => 'Champion',
            $placement <= 2 => 'Finalist',
            $placement <= 4 => 'Semi-Finalist',
            $placement <= 8 => 'Quarter-Finalist',
            $placement <= 16 => 'Round of 16',
            default => 'Group Stage'
        };
    }

    // Scopes
    public function scopeByPlacement($query, $placement)
    {
        return $query->where('final_placement', $placement);
    }

    public function scopeTop($query, $limit = 8)
    {
        return $query->orderBy('final_placement')->limit($limit);
    }

    public function scopeWithPrizeMoney($query)
    {
        return $query->whereNotNull('prize_money')->where('prize_money', '>', 0);
    }

    public function scopeByTournament($query, $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    public function scopeByEvent($query, $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('final_placement');
    }

    // Helper methods
    private function getOrdinalPlacement(int $placement): string
    {
        $suffix = match($placement % 10) {
            1 => $placement % 100 === 11 ? 'th' : 'st',
            2 => $placement % 100 === 12 ? 'th' : 'nd', 
            3 => $placement % 100 === 13 ? 'th' : 'rd',
            default => 'th'
        };
        
        return $placement . $suffix;
    }

    public function updateStats(array $stats): bool
    {
        return $this->update($stats);
    }

    public function eliminate(int $placement, ?string $placementRange = null): bool
    {
        return $this->update([
            'final_placement' => $placement,
            'placement_range' => $placementRange,
            'eliminated_at' => now()
        ]);
    }

    public function awardPrize(float $amount): bool
    {
        return $this->update(['prize_money' => $amount]);
    }

    // Constants for prize distribution
    public const STANDARD_PRIZE_DISTRIBUTION = [
        1 => 0.50, // 50% for 1st place
        2 => 0.25, // 25% for 2nd place
        3 => 0.15, // 15% for 3rd place
        4 => 0.10  // 10% for 4th place
    ];

    public const PLACEMENT_RANGES = [
        '3rd-4th' => [3, 4],
        '5th-8th' => [5, 6, 7, 8],
        '9th-12th' => [9, 10, 11, 12],
        '13th-16th' => [13, 14, 15, 16]
    ];
}