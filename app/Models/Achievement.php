<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class Achievement extends Model
{
    protected $fillable = [
        'name',
        'slug', 
        'description',
        'icon',
        'badge_color',
        'category',
        'rarity',
        'points',
        'requirements',
        'is_secret',
        'is_repeatable',
        'max_completions',
        'available_from',
        'available_until',
        'is_active',
        'order'
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_secret' => 'boolean',
        'is_repeatable' => 'boolean',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get all user achievements for this achievement
     */
    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    /**
     * Get users who have earned this achievement
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot(['progress', 'current_count', 'required_count', 'is_completed', 'completed_at', 'completion_count'])
            ->withTimestamps();
    }

    /**
     * Check if achievement is currently available
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();
        
        if ($this->available_from && $now->isBefore($this->available_from)) {
            return false;
        }
        
        if ($this->available_until && $now->isAfter($this->available_until)) {
            return false;
        }
        
        return true;
    }

    /**
     * Get achievement rarity display info
     */
    public function getRarityInfo(): array
    {
        return match($this->rarity) {
            'common' => ['color' => '#6B7280', 'label' => 'Common'],
            'uncommon' => ['color' => '#10B981', 'label' => 'Uncommon'],
            'rare' => ['color' => '#3B82F6', 'label' => 'Rare'],
            'epic' => ['color' => '#8B5CF6', 'label' => 'Epic'],
            'legendary' => ['color' => '#F59E0B', 'label' => 'Legendary'],
            default => ['color' => '#6B7280', 'label' => 'Unknown']
        };
    }

    /**
     * Get achievement category display info
     */
    public function getCategoryInfo(): array
    {
        return match($this->category) {
            'social' => ['icon' => 'users', 'label' => 'Social'],
            'activity' => ['icon' => 'activity', 'label' => 'Activity'],
            'milestone' => ['icon' => 'flag', 'label' => 'Milestone'],
            'streak' => ['icon' => 'zap', 'label' => 'Streak'],
            'challenge' => ['icon' => 'target', 'label' => 'Challenge'],
            'special' => ['icon' => 'star', 'label' => 'Special'],
            default => ['icon' => 'award', 'label' => 'Achievement']
        };
    }

    /**
     * Get completion percentage for all users
     */
    public function getCompletionPercentage(): float
    {
        return Cache::remember("achievement_completion_{$this->id}", 300, function () {
            $totalUsers = User::count();
            if ($totalUsers === 0) return 0;
            
            $completedUsers = $this->userAchievements()->where('is_completed', true)->count();
            return round(($completedUsers / $totalUsers) * 100, 2);
        });
    }

    /**
     * Get total times this achievement has been earned
     */
    public function getTotalEarned(): int
    {
        return Cache::remember("achievement_total_earned_{$this->id}", 300, function () {
            return $this->userAchievements()
                ->where('is_completed', true)
                ->sum('completion_count');
        });
    }

    /**
     * Check if user has earned this achievement
     */
    public function isEarnedByUser(int $userId): bool
    {
        return $this->userAchievements()
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->exists();
    }

    /**
     * Get user's progress on this achievement
     */
    public function getUserProgress(int $userId): ?UserAchievement
    {
        return $this->userAchievements()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Scope for active achievements
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for available achievements (considering time constraints)
     */
    public function scopeAvailable($query)
    {
        $now = Carbon::now();
        
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('available_from')
                  ->orWhere('available_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('available_until')
                  ->orWhere('available_until', '>=', $now);
            });
    }

    /**
     * Scope for public achievements (not secret)
     */
    public function scopePublic($query)
    {
        return $query->where('is_secret', false);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by rarity
     */
    public function scopeByRarity($query, string $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    /**
     * Order by rarity (legendary first)
     */
    public function scopeOrderByRarity($query)
    {
        return $query->orderByRaw("
            CASE rarity 
                WHEN 'legendary' THEN 1 
                WHEN 'epic' THEN 2 
                WHEN 'rare' THEN 3 
                WHEN 'uncommon' THEN 4 
                WHEN 'common' THEN 5 
                ELSE 6 
            END
        ");
    }
}