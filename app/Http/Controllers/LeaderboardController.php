<?php

namespace App\Http\Controllers;

use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LeaderboardController extends Controller
{
    /**
     * Get all active leaderboards
     */
    public function index(): JsonResponse
    {
        $leaderboards = Leaderboard::active()
            ->orderBy('name')
            ->get()
            ->map(function ($leaderboard) {
                return array_merge($leaderboard->toArray(), [
                    'current_period_date' => $leaderboard->getCurrentPeriodDate(),
                    'needs_reset' => $leaderboard->needsReset()
                ]);
            });

        return response()->json([
            'success' => true,
            'data' => $leaderboards
        ]);
    }

    /**
     * Get specific leaderboard with rankings
     */
    public function show(Leaderboard $leaderboard, Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 50), 100);
        
        $entries = $leaderboard->getTopEntries($limit);
        
        // Add rank changes and badges
        $entries = collect($entries)->map(function ($entry, $index) {
            $leaderboardEntry = LeaderboardEntry::where('leaderboard_id', request()->route('leaderboard')->id)
                ->where('user_id', $entry['user']['id'])
                ->first();
                
            if ($leaderboardEntry) {
                $entry['rank_change'] = $leaderboardEntry->getRankChange();
                $entry['score_change'] = $leaderboardEntry->getScoreChange();
                $entry['rank_badge'] = $leaderboardEntry->getRankBadge();
                $entry['formatted_score'] = $leaderboardEntry->getFormattedScore();
            }
            
            return $entry;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard,
                'entries' => $entries,
                'user_position' => Auth::id() ? $leaderboard->getUserPosition(Auth::id()) : null
            ]
        ]);
    }

    /**
     * Get user's position across all leaderboards
     */
    public function userPositions(User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $leaderboards = Leaderboard::active()->get();
        $positions = [];

        foreach ($leaderboards as $leaderboard) {
            $position = $leaderboard->getUserPosition($userId);
            if ($position) {
                $positions[] = array_merge([
                    'leaderboard' => $leaderboard
                ], $position);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $positions
        ]);
    }

    /**
     * Get leaderboard history for user
     */
    public function userHistory(Leaderboard $leaderboard, Request $request, User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $days = min($request->get('days', 30), 90);
        $startDate = now()->subDays($days);

        $entries = LeaderboardEntry::forLeaderboard($leaderboard->id)
            ->forUser($userId)
            ->where('period_date', '>=', $startDate)
            ->orderBy('period_date')
            ->get()
            ->map(function ($entry) {
                return [
                    'date' => $entry->period_date,
                    'rank' => $entry->rank,
                    'score' => $entry->score,
                    'formatted_score' => $entry->getFormattedScore(),
                    'rank_change' => $entry->getRankChange(),
                    'score_change' => $entry->getScoreChange()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard,
                'entries' => $entries,
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => now()->format('Y-m-d'),
                    'days' => $days
                ]
            ]
        ]);
    }

    /**
     * Get nearby rankings (users close to current user's rank)
     */
    public function nearbyRankings(Leaderboard $leaderboard, Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $userPosition = $leaderboard->getUserPosition($userId);
        if (!$userPosition) {
            return response()->json(['error' => 'User not found on leaderboard'], 404);
        }

        $range = min($request->get('range', 10), 25);
        $userRank = $userPosition['rank'];
        
        $startRank = max(1, $userRank - $range);
        $endRank = $userRank + $range;

        $entries = $leaderboard->currentEntries()
            ->with('user:id,name,avatar')
            ->whereBetween('rank', [$startRank, $endRank])
            ->get()
            ->map(function ($entry) use ($userId) {
                return [
                    'rank' => $entry->rank,
                    'user' => $entry->user,
                    'score' => $entry->score,
                    'formatted_score' => $entry->getFormattedScore(),
                    'is_current_user' => $entry->user_id === $userId,
                    'rank_badge' => $entry->getRankBadge()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard,
                'entries' => $entries,
                'user_rank' => $userRank,
                'range' => $range
            ]
        ]);
    }

    /**
     * Get leaderboard types and periods
     */
    public function metadata(): JsonResponse
    {
        $types = [
            'points' => 'Achievement Points',
            'achievements' => 'Total Achievements',
            'streak' => 'Best Streaks',
            'activity' => 'User Activity',
            'custom' => 'Custom Metrics'
        ];

        $periods = [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'all_time' => 'All Time'
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types,
                'periods' => $periods
            ]
        ]);
    }
}