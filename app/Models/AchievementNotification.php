<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AchievementNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'expires_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'expires_at' => 'datetime'
    ];

    /**
     * Get the user that owns this notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): void
    {
        $this->update(['is_read' => false]);
    }

    /**
     * Check if notification has expired
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && Carbon::now()->isAfter($this->expires_at);
    }

    /**
     * Get notification icon based on type
     */
    public function getIcon(): string
    {
        return match($this->type) {
            'achievement_earned' => '🏆',
            'streak_milestone' => '🔥',
            'challenge_completed' => '🎯',
            'leaderboard_rank' => '📊',
            default => '🎉'
        };
    }

    /**
     * Get notification color based on type
     */
    public function getColor(): string
    {
        return match($this->type) {
            'achievement_earned' => '#F59E0B',
            'streak_milestone' => '#EF4444',
            'challenge_completed' => '#8B5CF6',
            'leaderboard_rank' => '#3B82F6',
            default => '#10B981'
        };
    }

    /**
     * Get formatted notification data
     */
    public function getFormattedNotification(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'data' => $this->data,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'has_expired' => $this->hasExpired()
        ];
    }

    /**
     * Create achievement earned notification
     */
    public static function createAchievementEarned(int $userId, Achievement $achievement): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'achievement_earned',
            'title' => 'Achievement Unlocked!',
            'message' => "You've earned the '{$achievement->name}' achievement!",
            'data' => [
                'achievement_id' => $achievement->id,
                'achievement_name' => $achievement->name,
                'points' => $achievement->points,
                'rarity' => $achievement->rarity
            ],
            'expires_at' => Carbon::now()->addDays(7)
        ]);
    }

    /**
     * Create streak milestone notification
     */
    public static function createStreakMilestone(int $userId, UserStreak $streak, int $milestone): self
    {
        $streakInfo = $streak->getStreakInfo();
        
        return self::create([
            'user_id' => $userId,
            'type' => 'streak_milestone',
            'title' => 'Streak Milestone!',
            'message' => "You've reached a {$milestone}-day {$streakInfo['name']}!",
            'data' => [
                'streak_type' => $streak->streak_type,
                'milestone' => $milestone,
                'current_count' => $streak->current_count
            ],
            'expires_at' => Carbon::now()->addDays(3)
        ]);
    }

    /**
     * Create challenge completed notification
     */
    public static function createChallengeCompleted(int $userId, Challenge $challenge, int $rank = null): self
    {
        $message = "You've completed the '{$challenge->name}' challenge!";
        if ($rank) {
            $message .= " You finished in position #{$rank}.";
        }
        
        return self::create([
            'user_id' => $userId,
            'type' => 'challenge_completed',
            'title' => 'Challenge Complete!',
            'message' => $message,
            'data' => [
                'challenge_id' => $challenge->id,
                'challenge_name' => $challenge->name,
                'rank' => $rank,
                'rewards' => $challenge->rewards
            ],
            'expires_at' => Carbon::now()->addDays(5)
        ]);
    }

    /**
     * Create leaderboard rank notification
     */
    public static function createLeaderboardRank(int $userId, Leaderboard $leaderboard, int $rank, float $score): self
    {
        $rankText = match($rank) {
            1 => '1st place',
            2 => '2nd place', 
            3 => '3rd place',
            default => "#{$rank}"
        };
        
        return self::create([
            'user_id' => $userId,
            'type' => 'leaderboard_rank',
            'title' => 'Leaderboard Achievement!',
            'message' => "You're in {$rankText} on the {$leaderboard->name} leaderboard!",
            'data' => [
                'leaderboard_id' => $leaderboard->id,
                'leaderboard_name' => $leaderboard->name,
                'rank' => $rank,
                'score' => $score
            ],
            'expires_at' => Carbon::now()->addDays(3)
        ]);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for non-expired notifications
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', Carbon::now());
        });
    }

    /**
     * Scope for expired notifications
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }

    /**
     * Scope for notifications by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for user notifications
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for recent notifications
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }
}