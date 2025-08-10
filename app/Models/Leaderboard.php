<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class Leaderboard extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'period',
        'criteria',
        'is_active',
        'reset_at'
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
        'reset_at' => 'datetime'
    ];

    /**
     * Get all entries for this leaderboard
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /**
     * Get current period entries
     */
    public function currentEntries()
    {
        $periodDate = $this->getCurrentPeriodDate();
        
        return $this->entries()
            ->where('period_date', $periodDate)
            ->orderBy('rank');
    }

    /**
     * Get top entries for current period
     */
    public function getTopEntries(int $limit = 10): array
    {
        return $this->currentEntries()
            ->with('user:id,name,avatar')
            ->limit($limit)
            ->get()
            ->map(function ($entry) {
                return [
                    'rank' => $entry->rank,
                    'user' => $entry->user,
                    'score' => $entry->score,
                    'details' => $entry->details,
                    'updated_at' => $entry->updated_at
                ];
            })
            ->toArray();
    }

    /**
     * Get user's position on this leaderboard
     */
    public function getUserPosition(int $userId): ?array
    {
        $periodDate = $this->getCurrentPeriodDate();
        
        $entry = $this->entries()
            ->where('user_id', $userId)
            ->where('period_date', $periodDate)
            ->with('user:id,name,avatar')
            ->first();
            
        if (!$entry) {
            return null;
        }
        
        return [
            'rank' => $entry->rank,
            'score' => $entry->score,
            'details' => $entry->details,
            'updated_at' => $entry->updated_at
        ];
    }

    /**
     * Update leaderboard rankings
     */
    public function updateRankings(): void
    {
        $periodDate = $this->getCurrentPeriodDate();
        $rankings = $this->calculateRankings();
        
        DB::transaction(function () use ($rankings, $periodDate) {
            // Clear existing entries for this period
            $this->entries()->where('period_date', $periodDate)->delete();
            
            // Insert new rankings
            foreach ($rankings as $rank => $data) {
                LeaderboardEntry::create([
                    'leaderboard_id' => $this->id,
                    'user_id' => $data['user_id'],
                    'rank' => $rank + 1,
                    'score' => $data['score'],
                    'details' => $data['details'] ?? null,
                    'period_date' => $periodDate
                ]);
            }
        });
    }

    /**
     * Calculate rankings based on leaderboard type and criteria
     */
    private function calculateRankings(): array
    {
        $cacheKey = "leaderboard_rankings_{$this->id}_{$this->getCurrentPeriodDate()}";
        
        return Cache::remember($cacheKey, 300, function () {
            return match($this->type) {
                'points' => $this->calculatePointsRankings(),
                'achievements' => $this->calculateAchievementsRankings(),
                'streak' => $this->calculateStreakRankings(),
                'activity' => $this->calculateActivityRankings(),
                'custom' => $this->calculateCustomRankings(),
                default => []
            };
        });
    }

    /**
     * Calculate points-based rankings
     */
    private function calculatePointsRankings(): array
    {
        $dateFilter = $this->getDateFilter();
        
        $query = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->join('users', 'user_achievements.user_id', '=', 'users.id')
            ->where('user_achievements.is_completed', true)
            ->select([
                'users.id as user_id',
                DB::raw('SUM(achievements.points * user_achievements.completion_count) as total_points')
            ])
            ->groupBy('users.id');
            
        if ($dateFilter) {
            $query->whereBetween('user_achievements.completed_at', $dateFilter);
        }
        
        return $query->orderByDesc('total_points')
            ->limit(100)
            ->get()
            ->map(function ($result) {
                return [
                    'user_id' => $result->user_id,
                    'score' => $result->total_points,
                    'details' => ['total_points' => $result->total_points]
                ];
            })
            ->toArray();
    }

    /**
     * Calculate achievements-based rankings
     */
    private function calculateAchievementsRankings(): array
    {
        $dateFilter = $this->getDateFilter();
        
        $query = DB::table('user_achievements')
            ->join('users', 'user_achievements.user_id', '=', 'users.id')
            ->where('user_achievements.is_completed', true)
            ->select([
                'users.id as user_id',
                DB::raw('COUNT(*) as achievement_count')
            ])
            ->groupBy('users.id');
            
        if ($dateFilter) {
            $query->whereBetween('user_achievements.completed_at', $dateFilter);
        }
        
        return $query->orderByDesc('achievement_count')
            ->limit(100)
            ->get()
            ->map(function ($result) {
                return [
                    'user_id' => $result->user_id,
                    'score' => $result->achievement_count,
                    'details' => ['achievement_count' => $result->achievement_count]
                ];
            })
            ->toArray();
    }

    /**
     * Calculate streak-based rankings
     */
    private function calculateStreakRankings(): array
    {
        $streakType = $this->criteria['streak_type'] ?? 'login';
        
        return DB::table('user_streaks')
            ->join('users', 'user_streaks.user_id', '=', 'users.id')
            ->where('user_streaks.streak_type', $streakType)
            ->select([
                'users.id as user_id',
                'user_streaks.best_count as best_streak'
            ])
            ->orderByDesc('user_streaks.best_count')
            ->limit(100)
            ->get()
            ->map(function ($result) {
                return [
                    'user_id' => $result->user_id,
                    'score' => $result->best_streak,
                    'details' => ['best_streak' => $result->best_streak]
                ];
            })
            ->toArray();
    }

    /**
     * Calculate activity-based rankings
     */
    private function calculateActivityRankings(): array
    {
        $dateFilter = $this->getDateFilter();
        
        $query = DB::table('user_activities')
            ->join('users', 'user_activities.user_id', '=', 'users.id')
            ->select([
                'users.id as user_id',
                DB::raw('COUNT(*) as activity_count')
            ])
            ->groupBy('users.id');
            
        if ($dateFilter) {
            $query->whereBetween('user_activities.created_at', $dateFilter);
        }
        
        return $query->orderByDesc('activity_count')
            ->limit(100)
            ->get()
            ->map(function ($result) {
                return [
                    'user_id' => $result->user_id,
                    'score' => $result->activity_count,
                    'details' => ['activity_count' => $result->activity_count]
                ];
            })
            ->toArray();
    }

    /**
     * Calculate custom rankings based on criteria
     */
    private function calculateCustomRankings(): array
    {
        // Implement custom logic based on criteria
        // This can be extended for specific business requirements
        return [];
    }

    /**
     * Get current period date based on leaderboard period
     */
    public function getCurrentPeriodDate(): Carbon
    {
        $now = Carbon::now();
        
        return match($this->period) {
            'daily' => $now->startOfDay(),
            'weekly' => $now->startOfWeek(),
            'monthly' => $now->startOfMonth(),
            'all_time' => Carbon::create(2020, 1, 1), // Arbitrary start date
            default => $now->startOfDay()
        };
    }

    /**
     * Get date filter for period-based queries
     */
    private function getDateFilter(): ?array
    {
        if ($this->period === 'all_time') {
            return null;
        }
        
        $now = Carbon::now();
        
        return match($this->period) {
            'daily' => [$now->startOfDay(), $now->endOfDay()],
            'weekly' => [$now->startOfWeek(), $now->endOfWeek()],
            'monthly' => [$now->startOfMonth(), $now->endOfMonth()],
            default => null
        };
    }

    /**
     * Check if leaderboard needs reset
     */
    public function needsReset(): bool
    {
        if (!$this->reset_at) {
            return false;
        }
        
        return Carbon::now()->isAfter($this->reset_at);
    }

    /**
     * Reset leaderboard for new period
     */
    public function resetForNewPeriod(): void
    {
        $newResetDate = $this->calculateNextResetDate();
        
        $this->update([
            'reset_at' => $newResetDate
        ]);
        
        // Clear old entries if needed (keep recent periods for history)
        $this->cleanupOldEntries();
    }

    /**
     * Calculate next reset date
     */
    private function calculateNextResetDate(): Carbon
    {
        $now = Carbon::now();
        
        return match($this->period) {
            'daily' => $now->addDay()->startOfDay(),
            'weekly' => $now->addWeek()->startOfWeek(),
            'monthly' => $now->addMonth()->startOfMonth(),
            default => $now->addDay()
        };
    }

    /**
     * Clean up old entries
     */
    private function cleanupOldEntries(): void
    {
        $cutoffDate = match($this->period) {
            'daily' => Carbon::now()->subDays(30), // Keep 30 days
            'weekly' => Carbon::now()->subWeeks(12), // Keep 12 weeks
            'monthly' => Carbon::now()->subMonths(12), // Keep 12 months
            default => Carbon::now()->subDays(90)
        };
        
        $this->entries()
            ->where('period_date', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Scope for active leaderboards
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by period
     */
    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }
}