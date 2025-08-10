<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'challenge_id',
        'progress',
        'current_score',
        'is_completed',
        'started_at',
        'completed_at',
        'metadata'
    ];

    protected $casts = [
        'progress' => 'array',
        'is_completed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the user participating in the challenge
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the challenge
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /**
     * Update challenge progress
     */
    public function updateProgress(array $progressData, int $scoreIncrease = 0): void
    {
        $currentProgress = $this->progress ?? [];
        $newProgress = array_merge($currentProgress, $progressData);
        
        $updates = [
            'progress' => $newProgress,
            'current_score' => $this->current_score + $scoreIncrease
        ];
        
        $this->update($updates);
        
        // Check if challenge requirements are met
        $this->checkCompletion();
    }

    /**
     * Check if challenge requirements are met and mark as completed
     */
    public function checkCompletion(): void
    {
        if ($this->is_completed || !$this->challenge) {
            return;
        }
        
        $requirements = $this->challenge->requirements;
        $progress = $this->progress ?? [];
        
        $isCompleted = $this->evaluateRequirements($requirements, $progress);
        
        if ($isCompleted) {
            $this->markCompleted();
        }
    }

    /**
     * Evaluate if requirements are met
     */
    private function evaluateRequirements(array $requirements, array $progress): bool
    {
        foreach ($requirements as $requirement) {
            $type = $requirement['type'] ?? '';
            $target = $requirement['target'] ?? 0;
            $current = $progress[$type] ?? 0;
            
            if ($current < $target) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Mark challenge as completed
     */
    public function markCompleted(): void
    {
        if (!$this->is_completed) {
            $this->update([
                'is_completed' => true,
                'completed_at' => Carbon::now()
            ]);
            
            // Fire challenge completed event
            event(new \App\Events\ChallengeCompleted($this));
        }
    }

    /**
     * Get completion percentage for this challenge
     */
    public function getCompletionPercentage(): float
    {
        if (!$this->challenge || $this->is_completed) {
            return 100;
        }
        
        $requirements = $this->challenge->requirements;
        $progress = $this->progress ?? [];
        $totalPercentage = 0;
        $requirementCount = count($requirements);
        
        if ($requirementCount === 0) {
            return 0;
        }
        
        foreach ($requirements as $requirement) {
            $type = $requirement['type'] ?? '';
            $target = $requirement['target'] ?? 0;
            $current = $progress[$type] ?? 0;
            
            if ($target > 0) {
                $percentage = min(100, ($current / $target) * 100);
                $totalPercentage += $percentage;
            }
        }
        
        return round($totalPercentage / $requirementCount, 2);
    }

    /**
     * Get detailed progress information
     */
    public function getDetailedProgress(): array
    {
        if (!$this->challenge) {
            return [];
        }
        
        $requirements = $this->challenge->requirements;
        $progress = $this->progress ?? [];
        $details = [];
        
        foreach ($requirements as $requirement) {
            $type = $requirement['type'] ?? '';
            $target = $requirement['target'] ?? 0;
            $current = $progress[$type] ?? 0;
            $label = $requirement['label'] ?? ucfirst(str_replace('_', ' ', $type));
            
            $details[] = [
                'type' => $type,
                'label' => $label,
                'current' => $current,
                'target' => $target,
                'percentage' => $target > 0 ? min(100, ($current / $target) * 100) : 0,
                'completed' => $current >= $target
            ];
        }
        
        return $details;
    }

    /**
     * Get time spent on challenge
     */
    public function getTimeSpent(): string
    {
        $endTime = $this->completed_at ?? Carbon::now();
        return $this->started_at->diff($endTime)->format('%d days, %h hours');
    }

    /**
     * Get user's rank in challenge
     */
    public function getRank(): int
    {
        return UserChallenge::where('challenge_id', $this->challenge_id)
            ->where(function ($query) {
                $query->where('current_score', '>', $this->current_score)
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('current_score', '=', $this->current_score)
                            ->where('completed_at', '<', $this->completed_at ?? Carbon::now());
                    });
            })
            ->count() + 1;
    }

    /**
     * Scope for completed challenges
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope for active challenges
     */
    public function scopeActive($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope for challenges by user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for top performers
     */
    public function scopeTopPerformers($query, int $limit = 10)
    {
        return $query->orderByDesc('current_score')
            ->orderBy('completed_at')
            ->limit($limit);
    }
}