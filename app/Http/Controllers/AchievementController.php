<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AchievementController extends Controller
{
    protected AchievementService $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        $this->achievementService = $achievementService;
    }

    /**
     * Get all available achievements
     */
    public function index(Request $request): JsonResponse
    {
        $query = Achievement::with(['userAchievements' => function ($q) {
            $q->where('is_completed', true);
        }])->public()->available();

        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        // Filter by rarity
        if ($request->filled('rarity')) {
            $query->byRarity($request->rarity);
        }

        // Sort options
        $sortBy = $request->get('sort', 'order');
        switch ($sortBy) {
            case 'rarity':
                $query->orderByRarity();
                break;
            case 'points':
                $query->orderByDesc('points');
                break;
            case 'name':
                $query->orderBy('name');
                break;
            default:
                $query->orderBy('order')->orderBy('name');
                break;
        }

        $achievements = $query->paginate($request->get('per_page', 20));

        // Add completion stats
        $achievements->getCollection()->transform(function ($achievement) {
            return array_merge($achievement->toArray(), [
                'completion_percentage' => $achievement->getCompletionPercentage(),
                'total_earned' => $achievement->getTotalEarned(),
                'rarity_info' => $achievement->getRarityInfo(),
                'category_info' => $achievement->getCategoryInfo()
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $achievements
        ]);
    }

    /**
     * Get specific achievement details
     */
    public function show(Achievement $achievement): JsonResponse
    {
        $achievement->load(['userAchievements' => function ($query) {
            $query->where('is_completed', true)
                ->with('user:id,name,avatar')
                ->orderByDesc('completed_at')
                ->limit(10);
        }]);

        $data = array_merge($achievement->toArray(), [
            'completion_percentage' => $achievement->getCompletionPercentage(),
            'total_earned' => $achievement->getTotalEarned(),
            'rarity_info' => $achievement->getRarityInfo(),
            'category_info' => $achievement->getCategoryInfo(),
            'recent_earners' => $achievement->userAchievements->map(function ($ua) {
                return [
                    'user' => $ua->user,
                    'completed_at' => $ua->completed_at
                ];
            })
        ]);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get user's achievements
     */
    public function userAchievements(Request $request, User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $query = UserAchievement::with('achievement')
            ->where('user_id', $userId);

        // Filter by status
        $status = $request->get('status', 'all');
        switch ($status) {
            case 'completed':
                $query->completed();
                break;
            case 'in_progress':
                $query->inProgress();
                break;
            case 'available':
                // Show achievements user hasn't started yet
                $earnedIds = UserAchievement::where('user_id', $userId)
                    ->pluck('achievement_id');
                $availableAchievements = Achievement::public()->available()
                    ->whereNotIn('id', $earnedIds)
                    ->get()
                    ->map(function ($achievement) use ($userId) {
                        return [
                            'id' => null,
                            'user_id' => $userId,
                            'achievement_id' => $achievement->id,
                            'progress' => null,
                            'current_count' => 0,
                            'required_count' => 1,
                            'is_completed' => false,
                            'completed_at' => null,
                            'completion_count' => 0,
                            'achievement' => $achievement
                        ];
                    });
                
                return response()->json([
                    'success' => true,
                    'data' => $availableAchievements
                ]);
        }

        $userAchievements = $query->orderByDesc('completed_at')
            ->orderByDesc('current_count')
            ->paginate($request->get('per_page', 20));

        // Add progress info
        $userAchievements->getCollection()->transform(function ($ua) {
            return array_merge($ua->toArray(), [
                'progress_percentage' => $ua->getProgressPercentage(),
                'time_since_completion' => $ua->getTimeSinceCompletion(),
                'is_ready_to_complete' => $ua->isReadyToComplete()
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $userAchievements
        ]);
    }

    /**
     * Get user's achievement summary
     */
    public function userSummary(User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $summary = $this->achievementService->getUserAchievementSummary($userId);

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Get global achievement statistics
     */
    public function globalStats(): JsonResponse
    {
        $stats = $this->achievementService->getGlobalAchievementStats();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Track user activity (for testing)
     */
    public function trackActivity(Request $request): JsonResponse
    {
        $request->validate([
            'activity_type' => 'required|string',
            'metadata' => 'array'
        ]);

        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $this->achievementService->trackUserActivity(
            $userId,
            $request->activity_type,
            $request->get('metadata', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Activity tracked successfully'
        ]);
    }

    /**
     * Get achievement categories
     */
    public function categories(): JsonResponse
    {
        $categories = [
            'social' => ['icon' => 'users', 'label' => 'Social'],
            'activity' => ['icon' => 'activity', 'label' => 'Activity'],
            'milestone' => ['icon' => 'flag', 'label' => 'Milestone'],
            'streak' => ['icon' => 'zap', 'label' => 'Streak'],
            'challenge' => ['icon' => 'target', 'label' => 'Challenge'],
            'special' => ['icon' => 'star', 'label' => 'Special']
        ];

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get achievement rarities
     */
    public function rarities(): JsonResponse
    {
        $rarities = [
            'common' => ['color' => '#6B7280', 'label' => 'Common'],
            'uncommon' => ['color' => '#10B981', 'label' => 'Uncommon'],
            'rare' => ['color' => '#3B82F6', 'label' => 'Rare'],
            'epic' => ['color' => '#8B5CF6', 'label' => 'Epic'],
            'legendary' => ['color' => '#F59E0B', 'label' => 'Legendary']
        ];

        return response()->json([
            'success' => true,
            'data' => $rarities
        ]);
    }
}