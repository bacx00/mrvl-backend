<?php

namespace App\Http\Controllers;

use App\Services\OptimizedVotingService;
use App\Services\ForumCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class OptimizedVotingController extends ApiResponseController
{
    private $votingService;
    private $cacheService;
    
    public function __construct(
        OptimizedVotingService $votingService,
        ForumCacheService $cacheService
    ) {
        $this->votingService = $votingService;
        $this->cacheService = $cacheService;
    }

    /**
     * Unified voting endpoint for all content types
     */
    public function vote(Request $request)
    {
        // Rate limiting to prevent spam
        $key = 'vote:' . ($request->ip() ?? 'unknown') . ':' . (Auth::id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) { // 30 votes per minute
            return $this->errorResponse('Too many vote attempts. Please slow down.', 429);
        }
        
        RateLimiter::hit($key, 60); // Reset every minute
        
        $request->validate([
            'votable_type' => 'required|string|in:forum_thread,forum_post,thread,post,news,news_comment,match_comment',
            'votable_id' => 'required|integer|min:1',
            'vote_type' => 'required|string|in:upvote,downvote',
            'parent_id' => 'nullable|integer|min:1', // For nested comments
        ]);

        if (!Auth::check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        try {
            $result = $this->votingService->processVote(
                $request->votable_type,
                $request->votable_id,
                $request->vote_type,
                Auth::id()
            );

            // Log successful vote for analytics
            Log::info('Vote processed successfully', [
                'user_id' => Auth::id(),
                'votable_type' => $request->votable_type,
                'votable_id' => $request->votable_id,
                'vote_type' => $request->vote_type,
                'action' => $result['action']
            ]);

            return $this->successResponse($result, 'Vote processed successfully');

        } catch (\Exception $e) {
            Log::error('Vote processing failed', [
                'user_id' => Auth::id(),
                'votable_type' => $request->votable_type,
                'votable_id' => $request->votable_id,
                'vote_type' => $request->vote_type,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Get vote counts for a specific item
     */
    public function getVoteCounts(Request $request, $type, $id)
    {
        $request->validate([
            'type' => 'sometimes|string|in:forum_thread,forum_post,thread,post,news,news_comment,match_comment',
        ]);

        $itemType = $request->input('type', $type);
        
        try {
            $voteCounts = $this->votingService->getVoteCounts($itemType, $id);
            
            // Also get user's vote if authenticated
            $userVote = null;
            if (Auth::check()) {
                $userVote = $this->votingService->getUserVote(Auth::id(), $itemType, $id);
            }

            return $this->successResponse([
                'vote_counts' => $voteCounts,
                'user_vote' => $userVote,
                'score' => $voteCounts['score']
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get vote counts: ' . $e->getMessage());
        }
    }

    /**
     * Get trending content based on voting engagement
     */
    public function getTrending(Request $request)
    {
        $request->validate([
            'type' => 'sometimes|string|in:threads,posts,news',
            'limit' => 'sometimes|integer|min:1|max:50',
            'timeframe' => 'sometimes|integer|min:1|max:168', // Max 7 days
        ]);

        $type = $request->input('type', 'threads');
        $limit = $request->input('limit', 10);
        $timeframe = $request->input('timeframe', 24);

        try {
            $trending = $this->votingService->getTrendingContent($type, $limit, $timeframe);
            
            return $this->successResponse([
                'trending' => $trending,
                'type' => $type,
                'timeframe' => $timeframe,
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get trending content: ' . $e->getMessage());
        }
    }

    /**
     * Get bulk vote data for multiple items (optimized for lists)
     */
    public function getBulkVoteData(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.id' => 'required|integer|min:1',
            'type' => 'required|string|in:forum_thread,forum_post,thread,post,news,news_comment,match_comment',
        ]);

        try {
            $items = $request->input('items');
            $type = $request->input('type');
            $userId = Auth::id();

            $voteData = $this->votingService->bulkGetVoteData($items, $type, $userId);

            return $this->successResponse([
                'vote_data' => $voteData,
                'type' => $type,
                'count' => count($items)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get bulk vote data: ' . $e->getMessage());
        }
    }

    /**
     * Get voting analytics (admin/moderator only)
     */
    public function getAnalytics(Request $request)
    {
        // Check if user has permission to view analytics
        if (!Auth::user()->hasRole(['admin', 'moderator'])) {
            return $this->errorResponse('Insufficient permissions', 403);
        }

        $request->validate([
            'timeframe' => 'sometimes|integer|min:1|max:168',
            'item_type' => 'sometimes|string|in:forum_thread,forum_post,thread,post,news,news_comment,match_comment',
        ]);

        $timeframe = $request->input('timeframe', 24);
        $itemType = $request->input('item_type');

        try {
            $analytics = $this->votingService->getVoteAnalytics($timeframe, $itemType);

            return $this->successResponse([
                'analytics' => $analytics,
                'timeframe' => $timeframe,
                'item_type' => $itemType
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get vote analytics: ' . $e->getMessage());
        }
    }

    /**
     * Get user's voting history (for the authenticated user)
     */
    public function getUserVoteHistory(Request $request)
    {
        if (!Auth::check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'type' => 'sometimes|string|in:forum_thread,forum_post,thread,post,news,news_comment,match_comment',
        ]);

        $limit = $request->input('limit', 20);
        $type = $request->input('type');

        try {
            $query = \App\Models\Vote::where('user_id', Auth::id())
                ->with(['voteable'])
                ->orderBy('created_at', 'desc')
                ->limit($limit);

            if ($type) {
                $modelClass = $this->getModelClass($type);
                $query->where('voteable_type', $modelClass);
            }

            $votes = $query->get()->map(function ($vote) {
                return [
                    'id' => $vote->id,
                    'vote_type' => $vote->vote === 1 ? 'upvote' : 'downvote',
                    'voteable_type' => $vote->voteable_type,
                    'voteable_id' => $vote->voteable_id,
                    'created_at' => $vote->created_at->toISOString(),
                    'voteable' => $vote->voteable ? [
                        'id' => $vote->voteable->id,
                        'title' => $vote->voteable->title ?? $vote->voteable->content ?? 'Content',
                    ] : null
                ];
            });

            return $this->successResponse([
                'votes' => $votes,
                'count' => $votes->count(),
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get vote history: ' . $e->getMessage());
        }
    }

    /**
     * Cache warming endpoint (admin only)
     */
    public function warmCaches(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return $this->errorResponse('Admin access required', 403);
        }

        try {
            // Warm up trending content caches
            $this->votingService->getTrendingContent('threads', 20, 24);
            $this->votingService->getTrendingContent('threads', 10, 168);
            
            // Warm up forum caches
            $this->cacheService->warmUpCaches();

            return $this->successResponse([
                'cache_warmed' => true,
                'timestamp' => now()->toISOString()
            ], 'Caches warmed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to warm caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear vote caches (admin only)
     */
    public function clearVoteCaches(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return $this->errorResponse('Admin access required', 403);
        }

        $request->validate([
            'type' => 'sometimes|string|in:votes,trending,all',
        ]);

        $type = $request->input('type', 'votes');

        try {
            if ($type === 'all' || $type === 'votes') {
                // Clear vote-related caches
                // Note: This is a simplified approach for file-based cache
                \Cache::flush(); // Only use if safe in your environment
            }

            if ($type === 'all' || $type === 'trending') {
                // Clear trending caches
                \Cache::forget('forum:trending:threads');
                \Cache::forget('forum:trending:posts');
            }

            return $this->successResponse([
                'caches_cleared' => $type,
                'timestamp' => now()->toISOString()
            ], 'Vote caches cleared successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to clear caches: ' . $e->getMessage());
        }
    }

    /**
     * Private helper methods
     */
    private function getModelClass($itemType)
    {
        $mapping = [
            'forum_thread' => \App\Models\ForumThread::class,
            'forum_post' => \App\Models\Post::class,
            'thread' => \App\Models\ForumThread::class,
            'post' => \App\Models\Post::class,
            'news' => \App\Models\News::class,
            'news_comment' => \App\Models\NewsComment::class,
            'match_comment' => \App\Models\MatchComment::class,
        ];

        return $mapping[$itemType] ?? throw new \Exception("Invalid item type: {$itemType}");
    }
}