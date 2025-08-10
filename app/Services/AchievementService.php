<?php

namespace App\Services;

use App\Models\User;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Models\UserStreak;
use App\Models\Challenge;
use App\Models\UserChallenge;
use App\Models\AchievementNotification;
use App\Models\UserTitle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AchievementService
{
    /**
     * Track user activity and check for achievement progress
     */
    public function trackUserActivity(int $userId, string $activityType, array $metadata = []): void
    {
        DB::transaction(function () use ($userId, $activityType, $metadata) {
            // Update streaks
            $this->updateUserStreaks($userId, $activityType);
            
            // Check achievement progress
            $this->checkAchievementProgress($userId, $activityType, $metadata);
            
            // Update challenge progress
            $this->updateChallengeProgress($userId, $activityType, $metadata);
        });
    }

    /**
     * Update user streaks based on activity
     */
    private function updateUserStreaks(int $userId, string $activityType): void
    {
        $streakType = $this->mapActivityToStreakType($activityType);
        
        if (!$streakType) {
            return;
        }
        
        $streak = UserStreak::firstOrCreate([
            'user_id' => $userId,
            'streak_type' => $streakType
        ], [
            'current_count' => 0,
            'best_count' => 0,
            'is_active' => true
        ]);
        
        $streak->recordActivity();
    }

    /**
     * Map activity types to streak types
     */
    private function mapActivityToStreakType(string $activityType): ?string
    {
        return match($activityType) {
            'login' => 'login',
            'comment_posted', 'news_comment', 'match_comment' => 'comment',
            'thread_created', 'post_created' => 'forum_post',
            'match_prediction' => 'prediction',
            'vote_cast' => 'vote',
            default => null
        };
    }

    /**
     * Check and update achievement progress
     */
    public function checkAchievementProgress(int $userId, string $activityType, array $metadata = []): void
    {
        $achievements = Achievement::active()->available()->get();
        
        foreach ($achievements as $achievement) {
            $this->updateAchievementProgress($userId, $achievement, $activityType, $metadata);
        }
    }

    /**
     * Update specific achievement progress
     */
    private function updateAchievementProgress(int $userId, Achievement $achievement, string $activityType, array $metadata): void
    {
        $requirements = $achievement->requirements ?? [];
        $applicable = false;
        
        // Check if this activity is relevant to this achievement
        foreach ($requirements as $requirement) {
            if ($this->isActivityRelevant($activityType, $requirement, $metadata)) {
                $applicable = true;
                break;
            }
        }
        
        if (!$applicable) {
            return;
        }
        
        // Get or create user achievement progress
        $userAchievement = UserAchievement::firstOrCreate([
            'user_id' => $userId,
            'achievement_id' => $achievement->id
        ], [
            'current_count' => 0,
            'required_count' => $this->calculateRequiredCount($achievement),
            'is_completed' => false
        ]);
        
        // Don't update completed non-repeatable achievements
        if ($userAchievement->is_completed && !$achievement->is_repeatable) {
            return;
        }
        
        // Calculate progress increment
        $increment = $this->calculateProgressIncrement($achievement, $activityType, $metadata);
        
        if ($increment > 0) {
            $userAchievement->incrementProgress($increment, $metadata);
        }
    }

    /**
     * Check if activity is relevant to achievement requirement
     */
    private function isActivityRelevant(string $activityType, array $requirement, array $metadata): bool
    {
        $requiredType = $requirement['type'] ?? '';
        
        // Direct match
        if ($activityType === $requiredType) {
            return true;
        }
        
        // Category matches
        $categoryMatches = [
            'social' => ['comment_posted', 'thread_created', 'post_created', 'vote_cast'],
            'activity' => ['login', 'comment_posted', 'thread_created', 'match_view'],
            'engagement' => ['vote_cast', 'comment_posted', 'thread_created'],
            'forum' => ['thread_created', 'post_created', 'vote_cast']
        ];
        
        foreach ($categoryMatches as $category => $activities) {
            if ($requiredType === $category && in_array($activityType, $activities)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calculate required count for achievement completion
     */
    private function calculateRequiredCount(Achievement $achievement): int
    {
        $requirements = $achievement->requirements ?? [];
        
        // Find the highest target value in requirements
        $maxTarget = 1;
        foreach ($requirements as $requirement) {
            $target = $requirement['target'] ?? 1;
            $maxTarget = max($maxTarget, $target);
        }
        
        return $maxTarget;
    }

    /**
     * Calculate progress increment for activity
     */
    private function calculateProgressIncrement(Achievement $achievement, string $activityType, array $metadata): int
    {
        $requirements = $achievement->requirements ?? [];
        
        foreach ($requirements as $requirement) {
            if ($this->isActivityRelevant($activityType, $requirement, $metadata)) {
                return $requirement['increment'] ?? 1;
            }
        }
        
        return 1;
    }

    /**
     * Update challenge progress
     */
    private function updateChallengeProgress(int $userId, string $activityType, array $metadata): void
    {
        $activeChallenges = Challenge::active()->get();
        
        foreach ($activeChallenges as $challenge) {
            $userChallenge = UserChallenge::where('user_id', $userId)
                ->where('challenge_id', $challenge->id)
                ->first();
                
            if (!$userChallenge || $userChallenge->is_completed) {
                continue;
            }
            
            $this->updateSingleChallengeProgress($userChallenge, $activityType, $metadata);
        }
    }

    /**
     * Update single challenge progress
     */
    private function updateSingleChallengeProgress(UserChallenge $userChallenge, string $activityType, array $metadata): void
    {
        $challenge = $userChallenge->challenge;
        $requirements = $challenge->requirements ?? [];
        
        $progressUpdate = [];
        $scoreIncrease = 0;
        
        foreach ($requirements as $requirement) {
            $type = $requirement['type'] ?? '';
            
            if ($this->isActivityRelevant($activityType, ['type' => $type], $metadata)) {
                $currentProgress = $userChallenge->progress ?? [];
                $currentCount = $currentProgress[$type] ?? 0;
                
                $progressUpdate[$type] = $currentCount + ($requirement['increment'] ?? 1);
                $scoreIncrease += $requirement['points'] ?? 1;
            }
        }
        
        if (!empty($progressUpdate)) {
            $userChallenge->updateProgress($progressUpdate, $scoreIncrease);
        }
    }

    /**
     * Award achievement to user
     */
    public function awardAchievement(int $userId, int $achievementId): bool
    {
        $achievement = Achievement::find($achievementId);
        if (!$achievement || !$achievement->isAvailable()) {
            return false;
        }
        
        $userAchievement = UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achievementId)
            ->first();
            
        if (!$userAchievement) {
            $userAchievement = UserAchievement::create([
                'user_id' => $userId,
                'achievement_id' => $achievementId,
                'current_count' => $this->calculateRequiredCount($achievement),
                'required_count' => $this->calculateRequiredCount($achievement),
                'is_completed' => false
            ]);
        }
        
        if (!$userAchievement->is_completed) {
            $userAchievement->markCompleted();
            
            // Create notification
            AchievementNotification::createAchievementEarned($userId, $achievement);
            
            // Award title if achievement grants one
            $this->awardTitleFromAchievement($userId, $achievement);
            
            return true;
        }
        
        return false;
    }

    /**
     * Award title from achievement
     */
    private function awardTitleFromAchievement(int $userId, Achievement $achievement): void
    {
        $titleConfig = $achievement->requirements['title'] ?? null;
        
        if (!$titleConfig) {
            return;
        }
        
        UserTitle::create([
            'user_id' => $userId,
            'title' => $titleConfig['name'] ?? $achievement->name,
            'color' => $titleConfig['color'] ?? $achievement->badge_color,
            'achievement_id' => $achievement->id,
            'is_active' => false,
            'earned_at' => Carbon::now()
        ]);
    }

    /**
     * Get user achievement summary
     */
    public function getUserAchievementSummary(int $userId): array
    {
        return Cache::remember("user_achievement_summary_{$userId}", 300, function () use ($userId) {
            $completed = UserAchievement::forUser($userId)->completed()->count();
            $inProgress = UserAchievement::forUser($userId)->inProgress()->count();
            $totalPoints = $this->getUserTotalPoints($userId);
            $recentAchievements = $this->getUserRecentAchievements($userId, 5);
            $activeStreaks = UserStreak::where('user_id', $userId)->active()->get();
            $activeChallenges = $this->getUserActiveChallenges($userId);
            
            return [
                'completed_achievements' => $completed,
                'in_progress_achievements' => $inProgress,
                'total_points' => $totalPoints,
                'recent_achievements' => $recentAchievements,
                'active_streaks' => $activeStreaks->map(function ($streak) {
                    return array_merge($streak->toArray(), $streak->getStreakInfo());
                }),
                'active_challenges' => $activeChallenges
            ];
        });
    }

    /**
     * Get user total points
     */
    public function getUserTotalPoints(int $userId): int
    {
        return UserAchievement::join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->where('user_achievements.user_id', $userId)
            ->where('user_achievements.is_completed', true)
            ->sum(DB::raw('achievements.points * user_achievements.completion_count'));
    }

    /**
     * Get user recent achievements
     */
    public function getUserRecentAchievements(int $userId, int $limit = 5): array
    {
        return UserAchievement::with('achievement')
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get()
            ->map(function ($userAchievement) {
                return [
                    'achievement' => $userAchievement->achievement,
                    'completed_at' => $userAchievement->completed_at,
                    'completion_count' => $userAchievement->completion_count
                ];
            })
            ->toArray();
    }

    /**
     * Get user active challenges
     */
    public function getUserActiveChallenges(int $userId): array
    {
        return UserChallenge::with('challenge')
            ->where('user_id', $userId)
            ->where('is_completed', false)
            ->whereHas('challenge', function ($query) {
                $query->active();
            })
            ->get()
            ->map(function ($userChallenge) {
                return [
                    'challenge' => $userChallenge->challenge,
                    'progress' => $userChallenge->getDetailedProgress(),
                    'completion_percentage' => $userChallenge->getCompletionPercentage(),
                    'rank' => $userChallenge->getRank()
                ];
            })
            ->toArray();
    }

    /**
     * Join user to challenge
     */
    public function joinChallenge(int $userId, int $challengeId): bool
    {
        $challenge = Challenge::find($challengeId);
        
        if (!$challenge || !$challenge->canUserParticipate($userId)) {
            return false;
        }
        
        UserChallenge::create([
            'user_id' => $userId,
            'challenge_id' => $challengeId,
            'progress' => [],
            'current_score' => 0,
            'is_completed' => false,
            'started_at' => Carbon::now()
        ]);
        
        return true;
    }

    /**
     * Check and break expired streaks
     */
    public function checkExpiredStreaks(): void
    {
        $expiredStreaks = UserStreak::active()->atRisk()->get();
        
        foreach ($expiredStreaks as $streak) {
            $streak->checkForBreak();
        }
    }

    /**
     * Update leaderboards
     */
    public function updateLeaderboards(): void
    {
        $leaderboards = \App\Models\Leaderboard::active()->get();
        
        foreach ($leaderboards as $leaderboard) {
            if ($leaderboard->needsReset()) {
                $leaderboard->resetForNewPeriod();
            }
            
            $leaderboard->updateRankings();
        }
    }

    /**
     * Clean up expired notifications
     */
    public function cleanupExpiredNotifications(): void
    {
        AchievementNotification::expired()->delete();
    }

    /**
     * Get global achievement statistics
     */
    public function getGlobalAchievementStats(): array
    {
        return Cache::remember('global_achievement_stats', 3600, function () {
            return [
                'total_achievements' => Achievement::active()->count(),
                'total_earned' => UserAchievement::completed()->count(),
                'most_earned' => Achievement::with(['userAchievements' => function ($query) {
                    $query->where('is_completed', true);
                }])
                ->get()
                ->sortByDesc(function ($achievement) {
                    return $achievement->userAchievements->count();
                })
                ->take(5)
                ->values(),
                'rarest_achievements' => Achievement::with(['userAchievements' => function ($query) {
                    $query->where('is_completed', true);
                }])
                ->get()
                ->sortBy(function ($achievement) {
                    return $achievement->userAchievements->count();
                })
                ->take(5)
                ->values()
            ];
        });
    }
}