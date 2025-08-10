<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardEntry extends Model
{
    protected $fillable = [
        'leaderboard_id',
        'user_id',
        'rank',
        'score',
        'details',
        'period_date'
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'details' => 'array',
        'period_date' => 'date'
    ];

    /**
     * Get the leaderboard this entry belongs to
     */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /**
     * Get the user for this entry
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get rank change from previous period
     */
    public function getRankChange(): ?int
    {
        $previousEntry = $this->getPreviousPeriodEntry();
        
        if (!$previousEntry) {
            return null;
        }
        
        // Negative = rank improved (went up), Positive = rank worsened (went down)
        return $previousEntry->rank - $this->rank;
    }

    /**
     * Get score change from previous period
     */
    public function getScoreChange(): ?float
    {
        $previousEntry = $this->getPreviousPeriodEntry();
        
        if (!$previousEntry) {
            return null;
        }
        
        return $this->score - $previousEntry->score;
    }

    /**
     * Get previous period entry for same user and leaderboard
     */
    private function getPreviousPeriodEntry(): ?LeaderboardEntry
    {
        $previousDate = $this->getPreviousPeriodDate();
        
        if (!$previousDate) {
            return null;
        }
        
        return LeaderboardEntry::where('leaderboard_id', $this->leaderboard_id)
            ->where('user_id', $this->user_id)
            ->where('period_date', $previousDate)
            ->first();
    }

    /**
     * Calculate previous period date
     */
    private function getPreviousPeriodDate(): ?string
    {
        if (!$this->leaderboard) {
            return null;
        }
        
        return match($this->leaderboard->period) {
            'daily' => $this->period_date->subDay()->format('Y-m-d'),
            'weekly' => $this->period_date->subWeek()->format('Y-m-d'),
            'monthly' => $this->period_date->subMonth()->format('Y-m-d'),
            default => null
        };
    }

    /**
     * Get rank badge/medal
     */
    public function getRankBadge(): ?array
    {
        return match($this->rank) {
            1 => ['icon' => '🥇', 'color' => '#FFD700', 'label' => '1st'],
            2 => ['icon' => '🥈', 'color' => '#C0C0C0', 'label' => '2nd'],
            3 => ['icon' => '🥉', 'color' => '#CD7F32', 'label' => '3rd'],
            default => null
        };
    }

    /**
     * Check if this is a top position
     */
    public function isTopPosition(int $threshold = 3): bool
    {
        return $this->rank <= $threshold;
    }

    /**
     * Get formatted score
     */
    public function getFormattedScore(): string
    {
        if ($this->score >= 1000000) {
            return number_format($this->score / 1000000, 1) . 'M';
        } elseif ($this->score >= 1000) {
            return number_format($this->score / 1000, 1) . 'K';
        } else {
            return number_format($this->score, 0);
        }
    }

    /**
     * Scope for top entries
     */
    public function scopeTopRanks($query, int $limit = 10)
    {
        return $query->where('rank', '<=', $limit)
            ->orderBy('rank');
    }

    /**
     * Scope for specific period
     */
    public function scopeForPeriod($query, string $date)
    {
        return $query->where('period_date', $date);
    }

    /**
     * Scope for user entries
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for leaderboard entries
     */
    public function scopeForLeaderboard($query, int $leaderboardId)
    {
        return $query->where('leaderboard_id', $leaderboardId);
    }
}