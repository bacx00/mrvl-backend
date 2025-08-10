<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserAchievement extends Model
{
    protected $fillable = [
        'user_id',
        'achievement_id',
        'progress',
        'current_count',
        'required_count',
        'is_completed',
        'completed_at',
        'completion_count',
        'metadata'
    ];

    protected $casts = [
        'progress' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the user that owns this achievement
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the achievement definition
     */
    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->required_count <= 0) {
            return 0;
        }
        
        return min(100, round(($this->current_count / $this->required_count) * 100, 2));
    }

    /**
     * Check if achievement is ready to be completed
     */
    public function isReadyToComplete(): bool
    {
        return !$this->is_completed && $this->current_count >= $this->required_count;
    }

    /**
     * Mark achievement as completed
     */
    public function markCompleted(): void
    {
        if (!$this->is_completed) {
            $this->update([
                'is_completed' => true,
                'completed_at' => Carbon::now(),
                'completion_count' => $this->completion_count + 1
            ]);
            
            // Fire achievement earned event
            event(new \App\Events\AchievementEarned($this));
        }
    }

    /**
     * Increment progress towards achievement
     */
    public function incrementProgress(int $amount = 1, array $metadata = null): void
    {
        $newCount = $this->current_count + $amount;
        
        $updates = [
            'current_count' => min($newCount, $this->required_count)
        ];
        
        if ($metadata) {
            $updates['metadata'] = array_merge($this->metadata ?? [], $metadata);
        }
        
        $this->update($updates);
        
        // Check if achievement is now completed
        if ($this->fresh()->isReadyToComplete()) {
            $this->markCompleted();
        }
    }

    /**
     * Reset progress (for repeatable achievements)
     */
    public function resetProgress(): void
    {
        $this->update([
            'current_count' => 0,
            'is_completed' => false,
            'completed_at' => null,
            'progress' => null,
            'metadata' => null
        ]);
    }

    /**
     * Get time since completion
     */
    public function getTimeSinceCompletion(): ?string
    {
        if (!$this->completed_at) {
            return null;
        }
        
        return $this->completed_at->diffForHumans();
    }

    /**
     * Scope for completed achievements
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope for in-progress achievements
     */
    public function scopeInProgress($query)
    {
        return $query->where('is_completed', false)
            ->where('current_count', '>', 0);
    }

    /**
     * Scope for achievements by user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for achievements completed within timeframe
     */
    public function scopeCompletedWithin($query, Carbon $start, Carbon $end)
    {
        return $query->where('is_completed', true)
            ->whereBetween('completed_at', [$start, $end]);
    }
}