<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Challenge extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'banner_image',
        'requirements',
        'rewards',
        'starts_at',
        'ends_at',
        'difficulty',
        'max_participants',
        'is_active'
    ];

    protected $casts = [
        'requirements' => 'array',
        'rewards' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get all user challenges for this challenge
     */
    public function userChallenges(): HasMany
    {
        return $this->hasMany(UserChallenge::class);
    }

    /**
     * Get users participating in this challenge
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_challenges')
            ->withPivot(['progress', 'current_score', 'is_completed', 'started_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Check if challenge is currently active
     */
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        $now = Carbon::now();
        return $now->between($this->starts_at, $this->ends_at);
    }

    /**
     * Check if challenge is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->is_active && Carbon::now()->isBefore($this->starts_at);
    }

    /**
     * Check if challenge is completed/ended
     */
    public function hasEnded(): bool
    {
        return Carbon::now()->isAfter($this->ends_at);
    }

    /**
     * Get time remaining for challenge
     */
    public function getTimeRemaining(): ?string
    {
        if ($this->hasEnded()) {
            return null;
        }
        
        if ($this->isUpcoming()) {
            return $this->starts_at->diffForHumans();
        }
        
        return $this->ends_at->diffForHumans();
    }

    /**
     * Get challenge duration in days
     */
    public function getDurationDays(): int
    {
        return $this->starts_at->diffInDays($this->ends_at);
    }

    /**
     * Get difficulty info
     */
    public function getDifficultyInfo(): array
    {
        return match($this->difficulty) {
            'easy' => ['color' => '#10B981', 'label' => 'Easy'],
            'medium' => ['color' => '#F59E0B', 'label' => 'Medium'],
            'hard' => ['color' => '#EF4444', 'label' => 'Hard'],
            'extreme' => ['color' => '#7C2D12', 'label' => 'Extreme'],
            default => ['color' => '#6B7280', 'label' => 'Unknown']
        };
    }

    /**
     * Get participant count
     */
    public function getParticipantCount(): int
    {
        return $this->userChallenges()->count();
    }

    /**
     * Get completion count
     */
    public function getCompletionCount(): int
    {
        return $this->userChallenges()->where('is_completed', true)->count();
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(): float
    {
        $participants = $this->getParticipantCount();
        if ($participants === 0) return 0;
        
        $completed = $this->getCompletionCount();
        return round(($completed / $participants) * 100, 2);
    }

    /**
     * Check if user can participate
     */
    public function canUserParticipate(int $userId): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        // Check if user is already participating
        if ($this->userChallenges()->where('user_id', $userId)->exists()) {
            return false;
        }
        
        // Check participant limit
        if ($this->max_participants && $this->getParticipantCount() >= $this->max_participants) {
            return false;
        }
        
        return true;
    }

    /**
     * Get user's challenge progress
     */
    public function getUserProgress(int $userId): ?UserChallenge
    {
        return $this->userChallenges()->where('user_id', $userId)->first();
    }

    /**
     * Get leaderboard for this challenge
     */
    public function getLeaderboard(int $limit = 10): array
    {
        return $this->userChallenges()
            ->with('user:id,name,avatar')
            ->orderByDesc('current_score')
            ->orderBy('completed_at')
            ->limit($limit)
            ->get()
            ->map(function ($userChallenge, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $userChallenge->user,
                    'score' => $userChallenge->current_score,
                    'completed' => $userChallenge->is_completed,
                    'completed_at' => $userChallenge->completed_at
                ];
            })
            ->toArray();
    }

    /**
     * Scope for active challenges
     */
    public function scopeActive($query)
    {
        $now = Carbon::now();
        
        return $query->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    /**
     * Scope for upcoming challenges
     */
    public function scopeUpcoming($query)
    {
        $now = Carbon::now();
        
        return $query->where('is_active', true)
            ->where('starts_at', '>', $now);
    }

    /**
     * Scope for ended challenges
     */
    public function scopeEnded($query)
    {
        $now = Carbon::now();
        
        return $query->where('ends_at', '<', $now);
    }

    /**
     * Scope by difficulty
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }
}