<?php

namespace App\Services;

use App\Models\Vote;
use App\Models\ForumThread;
use App\Models\Post;
use App\Models\News;
use App\Models\NewsComment;
use App\Models\MatchComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OptimizedVotingService
{
    private $cacheService;
    
    private const VOTE_CACHE_TTL = 3600; // 1 hour
    private const SCORE_CACHE_TTL = 1800; // 30 minutes
    private const USER_VOTE_CACHE_TTL = 7200; // 2 hours
    
    // Vote types mapping for polymorphic relationships
    private const TYPE_MAPPING = [
        'forum_thread' => ForumThread::class,
        'forum_post' => Post::class,
        'post' => Post::class,
        'thread' => ForumThread::class,
        'news' => News::class,
        'news_comment' => NewsComment::class,
        'match_comment' => MatchComment::class,
    ];

    public function __construct(ForumCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Process a vote with intelligent caching and duplicate prevention
     */
    public function processVote($itemType, $itemId, $voteType, $userId = null)
    {
        $userId = $userId ?? Auth::id();
        
        if (!$userId) {
            throw new \Exception('User must be authenticated to vote');
        }

        // Validate vote type
        if (!in_array($voteType, ['upvote', 'downvote'])) {
            throw new \Exception('Invalid vote type');
        }

        // Check for cached user vote to prevent duplicates
        $existingVote = $this->getCachedUserVote($userId, $itemType, $itemId);
        
        try {
            DB::beginTransaction();
            
            // Get or create vote record
            $voteModel = $this->getModelClass($itemType);
            $vote = Vote::where([
                'user_id' => $userId,
                'voteable_type' => $voteModel,
                'voteable_id' => $itemId,
            ])->first();

            $action = 'created';
            $voteValue = $voteType === 'upvote' ? 1 : -1;
            
            if ($vote) {
                if ($vote->vote == $voteValue) {
                    // User is trying to vote the same way - remove vote
                    $vote->delete();
                    $action = 'removed';
                    $voteValue = null;
                } else {
                    // User is changing their vote
                    $vote->vote = $voteValue;
                    $vote->save();
                    $action = 'changed';
                }
            } else {
                // Create new vote
                Vote::create([
                    'user_id' => $userId,
                    'voteable_type' => $voteModel,
                    'voteable_id' => $itemId,
                    'vote' => $voteValue,
                ]);
                $action = 'created';
            }

            // Update cached vote counts
            $voteCounts = $this->updateVoteCounts($itemType, $itemId);
            
            // Cache user's vote status
            $this->cacheUserVote($userId, $itemType, $itemId, $voteValue);
            
            // Update item's score in database for sorting
            $this->updateItemScore($itemType, $itemId, $voteCounts);
            
            DB::commit();
            
            // Invalidate related caches
            $this->cacheService->invalidateVoteCaches($itemType, $itemId, $userId);
            
            // Update engagement metrics
            $this->updateEngagementMetrics($itemType, $itemId, $action);
            
            return [
                'success' => true,
                'action' => $action,
                'vote_counts' => $voteCounts,
                'user_vote' => $voteValue === 1 ? 'upvote' : ($voteValue === -1 ? 'downvote' : null),
                'score' => $voteCounts['upvotes'] - $voteCounts['downvotes']
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vote processing failed', [
                'user_id' => $userId,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'vote_type' => $voteType,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to process vote: ' . $e->getMessage());
        }
    }

    /**
     * Get vote counts for an item with caching
     */
    public function getVoteCounts($itemType, $itemId, $useCache = true)
    {
        if ($useCache) {
            $cached = $this->cacheService->getCachedVoteCounts($itemType, $itemId);
            if ($cached !== null) {
                return $cached;
            }
        }

        $voteModel = $this->getModelClass($itemType);
        
        $counts = Vote::where('voteable_type', $voteModel)
            ->where('voteable_id', $itemId)
            ->selectRaw('
                SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as upvotes,
                SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as downvotes,
                COUNT(*) as total_votes
            ')
            ->first();

        $voteCounts = [
            'upvotes' => (int) ($counts->upvotes ?? 0),
            'downvotes' => (int) ($counts->downvotes ?? 0),
            'total_votes' => (int) ($counts->total_votes ?? 0),
            'score' => (int) (($counts->upvotes ?? 0) - ($counts->downvotes ?? 0))
        ];

        // Cache the result
        $this->cacheService->cacheVoteCounts($itemType, $itemId, $voteCounts);
        
        return $voteCounts;
    }

    /**
     * Get user's vote for an item with caching
     */
    public function getUserVote($userId, $itemType, $itemId, $useCache = true)
    {
        if ($useCache) {
            $cached = $this->getCachedUserVote($userId, $itemType, $itemId);
            if ($cached !== null) {
                return $cached === 1 ? 'upvote' : ($cached === -1 ? 'downvote' : null);
            }
        }

        $voteModel = $this->getModelClass($itemType);
        
        $vote = Vote::where([
            'user_id' => $userId,
            'voteable_type' => $voteModel,
            'voteable_id' => $itemId,
        ])->first();

        $voteValue = $vote ? $vote->vote : null;
        
        // Cache the result
        $this->cacheUserVote($userId, $itemType, $itemId, $voteValue);
        
        return $voteValue === 1 ? 'upvote' : ($voteValue === -1 ? 'downvote' : null);
    }

    /**
     * Get trending content based on vote engagement
     */
    public function getTrendingContent($type = 'threads', $limit = 10, $timeframe = 24)
    {
        $cacheKey = "trending_{$type}_{$timeframe}h";
        $cached = $this->cacheService->getCachedTrendingContent($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $since = Carbon::now()->subHours($timeframe);
        
        if ($type === 'threads') {
            $trending = ForumThread::select([
                'forum_threads.*',
                DB::raw('COALESCE(vote_counts.upvotes, 0) as upvotes'),
                DB::raw('COALESCE(vote_counts.downvotes, 0) as downvotes'),
                DB::raw('COALESCE(vote_counts.upvotes, 0) - COALESCE(vote_counts.downvotes, 0) as score'),
                DB::raw('
                    (COALESCE(vote_counts.upvotes, 0) * 2 + 
                     forum_threads.views * 0.1 + 
                     forum_threads.replies * 1.5 +
                     CASE 
                        WHEN forum_threads.created_at > ? THEN 10
                        WHEN forum_threads.last_reply_at > ? THEN 5
                        ELSE 0
                     END
                    ) as trending_score
                ')
            ])
            ->leftJoin(DB::raw('(
                SELECT voteable_id,
                       SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as upvotes,
                       SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as downvotes
                FROM votes 
                WHERE voteable_type = ? 
                AND created_at > ?
                GROUP BY voteable_id
            ) as vote_counts'), 'forum_threads.id', '=', 'vote_counts.voteable_id')
            ->where('forum_threads.status', 'active')
            ->whereNotNull('forum_threads.title')
            ->orderBy('trending_score', 'desc')
            ->limit($limit)
            ->setBindings([
                $since,
                $since->copy()->subHours(6),
                ForumThread::class,
                $since
            ])
            ->get();
            
        } else {
            $trending = collect(); // Implement for other types as needed
        }

        // Cache the result
        $this->cacheService->cacheTrendingContent($trending, $cacheKey);
        
        return $trending;
    }

    /**
     * Bulk get vote data for multiple items (for lists)
     */
    public function bulkGetVoteData($items, $itemType, $userId = null)
    {
        $itemIds = collect($items)->pluck('id')->toArray();
        $voteData = [];
        
        // Get cached vote counts
        $cachedCounts = [];
        $uncachedIds = [];
        
        foreach ($itemIds as $itemId) {
            $cached = $this->cacheService->getCachedVoteCounts($itemType, $itemId);
            if ($cached !== null) {
                $cachedCounts[$itemId] = $cached;
            } else {
                $uncachedIds[] = $itemId;
            }
        }
        
        // Get uncached vote counts from database
        $voteModel = $this->getModelClass($itemType);
        if (!empty($uncachedIds)) {
            $dbCounts = Vote::where('voteable_type', $voteModel)
                ->whereIn('voteable_id', $uncachedIds)
                ->selectRaw('
                    voteable_id,
                    SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as upvotes,
                    SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as downvotes,
                    COUNT(*) as total_votes
                ')
                ->groupBy('voteable_id')
                ->get()
                ->keyBy('voteable_id');
                
            // Process and cache the results
            foreach ($uncachedIds as $itemId) {
                $counts = $dbCounts->get($itemId);
                $voteCounts = [
                    'upvotes' => (int) ($counts->upvotes ?? 0),
                    'downvotes' => (int) ($counts->downvotes ?? 0),
                    'total_votes' => (int) ($counts->total_votes ?? 0),
                    'score' => (int) (($counts->upvotes ?? 0) - ($counts->downvotes ?? 0))
                ];
                
                $cachedCounts[$itemId] = $voteCounts;
                $this->cacheService->cacheVoteCounts($itemType, $itemId, $voteCounts);
            }
        }
        
        // Get user votes if user is provided
        $userVotes = [];
        if ($userId) {
            $userVoteRecords = Vote::where('user_id', $userId)
                ->where('voteable_type', $voteModel)
                ->whereIn('voteable_id', $itemIds)
                ->get()
                ->keyBy('voteable_id');
                
            foreach ($itemIds as $itemId) {
                $vote = $userVoteRecords->get($itemId);
                $voteValue = $vote ? $vote->vote : null;
                $userVotes[$itemId] = $voteValue === 1 ? 'upvote' : ($voteValue === -1 ? 'downvote' : null);
                
                // Cache user vote
                $this->cacheUserVote($userId, $itemType, $itemId, $voteValue);
            }
        }
        
        // Combine all data
        foreach ($itemIds as $itemId) {
            $voteData[$itemId] = [
                'vote_counts' => $cachedCounts[$itemId] ?? [
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'total_votes' => 0,
                    'score' => 0
                ],
                'user_vote' => $userVotes[$itemId] ?? null
            ];
        }
        
        return $voteData;
    }

    /**
     * Get vote analytics for engagement metrics
     */
    public function getVoteAnalytics($timeframe = 24, $itemType = null)
    {
        $since = Carbon::now()->subHours($timeframe);
        
        $query = Vote::where('created_at', '>=', $since);
        
        if ($itemType) {
            $voteModel = $this->getModelClass($itemType);
            $query->where('voteable_type', $voteModel);
        }
        
        $analytics = $query->selectRaw('
            COUNT(*) as total_votes,
            SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as total_upvotes,
            SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as total_downvotes,
            COUNT(DISTINCT user_id) as unique_voters,
            COUNT(DISTINCT voteable_id) as items_voted_on,
            AVG(vote) as avg_vote_sentiment
        ')
        ->first();
        
        return [
            'total_votes' => (int) $analytics->total_votes,
            'total_upvotes' => (int) $analytics->total_upvotes,
            'total_downvotes' => (int) $analytics->total_downvotes,
            'unique_voters' => (int) $analytics->unique_voters,
            'items_voted_on' => (int) $analytics->items_voted_on,
            'avg_sentiment' => round($analytics->avg_vote_sentiment, 3),
            'engagement_rate' => $analytics->unique_voters > 0 ? 
                round(($analytics->total_votes / $analytics->unique_voters), 2) : 0
        ];
    }

    /**
     * Private helper methods
     */
    
    private function getModelClass($itemType)
    {
        return self::TYPE_MAPPING[$itemType] ?? throw new \Exception("Invalid item type: {$itemType}");
    }
    
    private function getCachedUserVote($userId, $itemType, $itemId)
    {
        return $this->cacheService->getCachedUserVote($userId, $itemType, $itemId);
    }
    
    private function cacheUserVote($userId, $itemType, $itemId, $voteValue)
    {
        return $this->cacheService->cacheUserVote($userId, $itemType, $itemId, $voteValue);
    }
    
    private function updateVoteCounts($itemType, $itemId)
    {
        // Force refresh of vote counts
        return $this->getVoteCounts($itemType, $itemId, false);
    }
    
    private function updateItemScore($itemType, $itemId, $voteCounts)
    {
        $score = $voteCounts['upvotes'] - $voteCounts['downvotes'];
        
        try {
            if ($itemType === 'forum_thread' || $itemType === 'thread') {
                ForumThread::where('id', $itemId)->update([
                    'score' => $score,
                    'upvotes' => $voteCounts['upvotes'],
                    'downvotes' => $voteCounts['downvotes']
                ]);
            } elseif ($itemType === 'forum_post' || $itemType === 'post') {
                Post::where('id', $itemId)->update([
                    'score' => $score,
                    'upvotes' => $voteCounts['upvotes'],
                    'downvotes' => $voteCounts['downvotes']
                ]);
            } elseif ($itemType === 'news') {
                News::where('id', $itemId)->update([
                    'score' => $score,
                    'upvotes' => $voteCounts['upvotes'],
                    'downvotes' => $voteCounts['downvotes']
                ]);
            }
            // Add other types as needed
        } catch (\Exception $e) {
            Log::warning('Failed to update item score', [
                'item_type' => $itemType,
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function updateEngagementMetrics($itemType, $itemId, $action)
    {
        try {
            $hour = date('Y-m-d-H');
            $metricsKey = "engagement_metrics_{$hour}";
            
            $metrics = Cache::get($metricsKey, [
                'votes_created' => 0,
                'votes_changed' => 0,
                'votes_removed' => 0,
                'unique_items' => [],
                'unique_users' => []
            ]);
            
            $metrics["votes_{$action}"]++;
            $metrics['unique_items'][$itemId] = true;
            $metrics['unique_users'][Auth::id()] = true;
            
            $this->cacheService->cacheEngagementMetrics($metrics);
        } catch (\Exception $e) {
            Log::debug('Failed to update engagement metrics', ['error' => $e->getMessage()]);
        }
    }
}