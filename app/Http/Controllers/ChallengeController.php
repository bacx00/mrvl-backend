<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\UserChallenge;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChallengeController extends Controller
{
    protected AchievementService $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        $this->achievementService = $achievementService;
    }

    /**
     * Get all challenges
     */
    public function index(Request $request): JsonResponse
    {
        $query = Challenge::query();

        // Filter by status
        $status = $request->get('status', 'all');
        switch ($status) {
            case 'active':
                $query->active();
                break;
            case 'upcoming':
                $query->upcoming();
                break;
            case 'ended':
                $query->ended();
                break;
        }

        // Filter by difficulty
        if ($request->filled('difficulty')) {
            $query->byDifficulty($request->difficulty);
        }

        // Sort
        $sortBy = $request->get('sort', 'starts_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $challenges = $query->paginate($request->get('per_page', 20));

        // Add statistics
        $challenges->getCollection()->transform(function ($challenge) {
            return array_merge($challenge->toArray(), [
                'is_active' => $challenge->isActive(),
                'is_upcoming' => $challenge->isUpcoming(),
                'has_ended' => $challenge->hasEnded(),
                'time_remaining' => $challenge->getTimeRemaining(),
                'duration_days' => $challenge->getDurationDays(),
                'difficulty_info' => $challenge->getDifficultyInfo(),
                'participant_count' => $challenge->getParticipantCount(),
                'completion_count' => $challenge->getCompletionCount(),
                'completion_percentage' => $challenge->getCompletionPercentage(),
                'can_join' => Auth::id() ? $challenge->canUserParticipate(Auth::id()) : false
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $challenges
        ]);
    }

    /**
     * Get specific challenge details
     */
    public function show(Challenge $challenge): JsonResponse
    {
        $data = array_merge($challenge->toArray(), [
            'is_active' => $challenge->isActive(),
            'is_upcoming' => $challenge->isUpcoming(),
            'has_ended' => $challenge->hasEnded(),
            'time_remaining' => $challenge->getTimeRemaining(),
            'duration_days' => $challenge->getDurationDays(),
            'difficulty_info' => $challenge->getDifficultyInfo(),
            'participant_count' => $challenge->getParticipantCount(),
            'completion_count' => $challenge->getCompletionCount(),
            'completion_percentage' => $challenge->getCompletionPercentage(),
            'leaderboard' => $challenge->getLeaderboard(10),
            'can_join' => Auth::id() ? $challenge->canUserParticipate(Auth::id()) : false,
            'user_progress' => Auth::id() ? $challenge->getUserProgress(Auth::id()) : null
        ]);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Join a challenge
     */
    public function join(Challenge $challenge): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        if (!$challenge->isActive()) {
            return response()->json(['error' => 'Challenge is not active'], 400);
        }

        $success = $this->achievementService->joinChallenge($userId, $challenge->id);

        if (!$success) {
            return response()->json(['error' => 'Cannot join challenge'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined challenge',
            'data' => $challenge->getUserProgress($userId)
        ]);
    }

    /**
     * Get challenge leaderboard
     */
    public function leaderboard(Challenge $challenge, Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 50), 100);
        $leaderboard = $challenge->getLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'challenge' => $challenge,
                'leaderboard' => $leaderboard,
                'user_position' => Auth::id() ? $this->getUserChallengePosition($challenge, Auth::id()) : null
            ]
        ]);
    }

    /**
     * Get user's challenge progress
     */
    public function userProgress(Challenge $challenge, User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $userChallenge = $challenge->getUserProgress($userId);
        
        if (!$userChallenge) {
            return response()->json(['error' => 'User not participating in challenge'], 404);
        }

        $data = array_merge($userChallenge->toArray(), [
            'detailed_progress' => $userChallenge->getDetailedProgress(),
            'completion_percentage' => $userChallenge->getCompletionPercentage(),
            'time_spent' => $userChallenge->getTimeSpent(),
            'rank' => $userChallenge->getRank()
        ]);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get user's challenges
     */
    public function userChallenges(Request $request, User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $query = UserChallenge::with('challenge')
            ->where('user_id', $userId);

        // Filter by status
        $status = $request->get('status', 'all');
        switch ($status) {
            case 'active':
                $query->active()->whereHas('challenge', function ($q) {
                    $q->active();
                });
                break;
            case 'completed':
                $query->completed();
                break;
            case 'ended':
                $query->whereHas('challenge', function ($q) {
                    $q->ended();
                });
                break;
        }

        $userChallenges = $query->orderByDesc('started_at')
            ->paginate($request->get('per_page', 20));

        // Add progress info
        $userChallenges->getCollection()->transform(function ($uc) {
            return array_merge($uc->toArray(), [
                'detailed_progress' => $uc->getDetailedProgress(),
                'completion_percentage' => $uc->getCompletionPercentage(),
                'time_spent' => $uc->getTimeSpent(),
                'rank' => $uc->getRank()
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $userChallenges
        ]);
    }

    /**
     * Get challenge difficulties
     */
    public function difficulties(): JsonResponse
    {
        $difficulties = [
            'easy' => ['color' => '#10B981', 'label' => 'Easy'],
            'medium' => ['color' => '#F59E0B', 'label' => 'Medium'],
            'hard' => ['color' => '#EF4444', 'label' => 'Hard'],
            'extreme' => ['color' => '#7C2D12', 'label' => 'Extreme']
        ];

        return response()->json([
            'success' => true,
            'data' => $difficulties
        ]);
    }

    /**
     * Get user's position in challenge
     */
    private function getUserChallengePosition(Challenge $challenge, int $userId): ?array
    {
        $userChallenge = UserChallenge::where('challenge_id', $challenge->id)
            ->where('user_id', $userId)
            ->with('user:id,name,avatar')
            ->first();

        if (!$userChallenge) {
            return null;
        }

        return [
            'rank' => $userChallenge->getRank(),
            'score' => $userChallenge->current_score,
            'completed' => $userChallenge->is_completed,
            'completion_percentage' => $userChallenge->getCompletionPercentage()
        ];
    }
}