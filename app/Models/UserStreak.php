<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserStreak extends Model
{
    protected $fillable = [
        'user_id',
        'streak_type',
        'current_count',
        'best_count',
        'last_activity_date',
        'streak_started_at',
        'streak_broken_at',
        'is_active'
    ];

    protected $casts = [
        'last_activity_date' => 'date',
        'streak_started_at' => 'datetime',
        'streak_broken_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get the user that owns this streak
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update streak activity
     */
    public function recordActivity(Carbon $activityDate = null): void
    {
        $activityDate = $activityDate ?? Carbon::today();
        
        // If no previous activity, start a new streak
        if (!$this->last_activity_date) {
            $this->startStreak($activityDate);
            return;
        }
        
        $daysSinceLastActivity = $this->last_activity_date->diffInDays($activityDate);
        
        if ($daysSinceLastActivity === 0) {
            // Same day activity, no change to streak
            return;
        } elseif ($daysSinceLastActivity === 1) {
            // Consecutive day, extend streak
            $this->extendStreak($activityDate);
        } else {
            // Gap in activity, break current streak and start new one
            $this->breakStreak();
            $this->startStreak($activityDate);
        }
    }

    /**
     * Start a new streak
     */
    private function startStreak(Carbon $date): void
    {
        $this->update([
            'current_count' => 1,
            'last_activity_date' => $date,
            'streak_started_at' => $date->startOfDay(),
            'is_active' => true
        ]);
    }

    /**
     * Extend current streak
     */
    private function extendStreak(Carbon $date): void
    {
        $newCount = $this->current_count + 1;
        
        $updates = [
            'current_count' => $newCount,
            'last_activity_date' => $date,
            'is_active' => true
        ];
        
        // Update best count if current is higher
        if ($newCount > $this->best_count) {
            $updates['best_count'] = $newCount;
        }
        
        $this->update($updates);
        
        // Check for streak milestone achievements
        $this->checkStreakMilestones($newCount);
    }

    /**
     * Break current streak
     */
    public function breakStreak(): void
    {
        $this->update([
            'current_count' => 0,
            'streak_broken_at' => Carbon::now(),
            'is_active' => false
        ]);
    }

    /**
     * Check if streak is at risk (no activity yesterday)
     */
    public function isAtRisk(): bool
    {
        if (!$this->is_active || !$this->last_activity_date) {
            return false;
        }
        
        return $this->last_activity_date->isBefore(Carbon::yesterday());
    }

    /**
     * Check if streak should be broken due to inactivity
     */
    public function checkForBreak(): bool
    {
        if (!$this->is_active || !$this->last_activity_date) {
            return false;
        }
        
        // Break streak if no activity for 2+ days
        $daysSinceLastActivity = $this->last_activity_date->diffInDays(Carbon::today());
        
        if ($daysSinceLastActivity >= 2) {
            $this->breakStreak();
            return true;
        }
        
        return false;
    }

    /**
     * Get streak display info
     */
    public function getStreakInfo(): array
    {
        return match($this->streak_type) {
            'login' => [
                'name' => 'Daily Login Streak',
                'icon' => 'calendar',
                'description' => 'Days logged in consecutively'
            ],
            'comment' => [
                'name' => 'Comment Streak',
                'icon' => 'message-circle',
                'description' => 'Days with comments posted'
            ],
            'forum_post' => [
                'name' => 'Forum Activity Streak',
                'icon' => 'users',
                'description' => 'Days with forum participation'
            ],
            'prediction' => [
                'name' => 'Prediction Streak',
                'icon' => 'target',
                'description' => 'Days with match predictions'
            ],
            'vote' => [
                'name' => 'Voting Streak',
                'icon' => 'thumbs-up',
                'description' => 'Days with votes cast'
            ],
            default => [
                'name' => 'Activity Streak',
                'icon' => 'activity',
                'description' => 'Days of activity'
            ]
        };
    }

    /**
     * Check for streak milestone achievements
     */
    private function checkStreakMilestones(int $streakCount): void
    {
        $milestones = [7, 14, 30, 50, 100, 365];
        
        if (in_array($streakCount, $milestones)) {
            // Fire streak milestone event
            event(new \App\Events\StreakMilestone($this, $streakCount));
        }
    }

    /**
     * Get days until streak expires (if at risk)
     */
    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->is_active || !$this->last_activity_date) {
            return null;
        }
        
        $daysSinceLastActivity = $this->last_activity_date->diffInDays(Carbon::today());
        return max(0, 2 - $daysSinceLastActivity);
    }

    /**
     * Scope for active streaks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for streaks by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('streak_type', $type);
    }

    /**
     * Scope for streaks at risk
     */
    public function scopeAtRisk($query)
    {
        $yesterday = Carbon::yesterday();
        
        return $query->where('is_active', true)
            ->where('last_activity_date', '<', $yesterday);
    }

    /**
     * Scope for long streaks (7+ days)
     */
    public function scopeLongStreaks($query, int $minDays = 7)
    {
        return $query->where('current_count', '>=', $minDays);
    }
}