<?php

namespace App\Http\Controllers;

use App\Models\UserStreak;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StreakController extends Controller
{
    /**
     * Get user's streaks
     */
    public function index(User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $streaks = UserStreak::where('user_id', $userId)
            ->orderByDesc('current_count')
            ->get()
            ->map(function ($streak) {
                return array_merge($streak->toArray(), [
                    'streak_info' => $streak->getStreakInfo(),
                    'is_at_risk' => $streak->isAtRisk(),
                    'days_until_expiry' => $streak->getDaysUntilExpiry()
                ]);
            });

        return response()->json([
            'success' => true,
            'data' => $streaks
        ]);
    }

    /**
     * Get specific streak
     */
    public function show(UserStreak $streak): JsonResponse
    {
        $userId = Auth::id();
        if ($userId && $streak->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = array_merge($streak->toArray(), [
            'streak_info' => $streak->getStreakInfo(),
            'is_at_risk' => $streak->isAtRisk(),
            'days_until_expiry' => $streak->getDaysUntilExpiry()
        ]);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get all active streaks (leaderboard)
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $streakType = $request->get('type', 'login');
        $limit = min($request->get('limit', 50), 100);

        $streaks = UserStreak::with('user:id,name,avatar')
            ->byType($streakType)
            ->active()
            ->orderByDesc('current_count')
            ->limit($limit)
            ->get()
            ->map(function ($streak, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $streak->user,
                    'current_count' => $streak->current_count,
                    'best_count' => $streak->best_count,
                    'streak_info' => $streak->getStreakInfo(),
                    'is_at_risk' => $streak->isAtRisk()
                ];
            });

        // Get user's position if authenticated
        $userPosition = null;
        if (Auth::id()) {
            $userStreak = UserStreak::where('user_id', Auth::id())
                ->byType($streakType)
                ->first();
                
            if ($userStreak) {
                $betterStreaks = UserStreak::byType($streakType)
                    ->active()
                    ->where('current_count', '>', $userStreak->current_count)
                    ->count();
                    
                $userPosition = [
                    'rank' => $betterStreaks + 1,
                    'current_count' => $userStreak->current_count,
                    'best_count' => $userStreak->best_count,
                    'is_at_risk' => $userStreak->isAtRisk()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'streaks' => $streaks,
                'user_position' => $userPosition,
                'streak_type' => $streakType
            ]
        ]);
    }

    /**
     * Get streaks at risk (about to expire)
     */
    public function atRisk(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 20), 50);

        $streaks = UserStreak::with('user:id,name,avatar')
            ->atRisk()
            ->longStreaks(3) // Only show streaks worth preserving
            ->orderByDesc('current_count')
            ->limit($limit)
            ->get()
            ->map(function ($streak) {
                return array_merge($streak->toArray(), [
                    'streak_info' => $streak->getStreakInfo(),
                    'days_until_expiry' => $streak->getDaysUntilExpiry()
                ]);
            });

        return response()->json([
            'success' => true,
            'data' => $streaks
        ]);
    }

    /**
     * Get streak types
     */
    public function types(): JsonResponse
    {
        $types = [
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
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Get streak statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_active_streaks' => UserStreak::active()->count(),
            'streaks_at_risk' => UserStreak::atRisk()->count(),
            'longest_current_streak' => UserStreak::active()->max('current_count') ?? 0,
            'longest_all_time_streak' => UserStreak::max('best_count') ?? 0,
            'by_type' => UserStreak::selectRaw('streak_type, COUNT(*) as count, MAX(current_count) as longest')
                ->active()
                ->groupBy('streak_type')
                ->get()
                ->keyBy('streak_type')
                ->map(function ($item) {
                    return [
                        'active_count' => $item->count,
                        'longest_current' => $item->longest
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}