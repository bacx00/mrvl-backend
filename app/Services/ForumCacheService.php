<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ForumCacheService
{
    private const CACHE_TTL = 300; // 5 minutes for hot data
    private const LONG_CACHE_TTL = 3600; // 1 hour for cold data
    private const REALTIME_TTL = 60; // 1 minute for real-time data

    /**
     * Cache forum threads with intelligent TTL
     */
    public function cacheThreads($category, $sortBy, $page, $threads)
    {
        $key = "forum:threads:{$category}:{$sortBy}:page:{$page}";
        
        // Hot threads get shorter TTL for fresh content
        $ttl = ($sortBy === 'hot' || $sortBy === 'latest') ? self::REALTIME_TTL : self::CACHE_TTL;
        
        Cache::put($key, $threads, $ttl);
        
        // Also cache the thread IDs separately for invalidation
        $threadIds = collect($threads['data'])->pluck('id')->toArray();
        Cache::put($key . ':ids', $threadIds, $ttl);
        
        return $threads;
    }

    /**
     * Get cached threads
     */
    public function getCachedThreads($category, $sortBy, $page)
    {
        $key = "forum:threads:{$category}:{$sortBy}:page:{$page}";
        return Cache::get($key);
    }

    /**
     * Cache individual thread with posts
     */
    public function cacheThread($threadId, $threadData)
    {
        $key = "forum:thread:{$threadId}";
        Cache::put($key, $threadData, self::CACHE_TTL);
        
        // Cache thread metadata separately for quick access
        $metadata = [
            'id' => $threadData['data']['id'],
            'title' => $threadData['data']['title'],
            'replies' => $threadData['data']['stats']['replies'],
            'views' => $threadData['data']['stats']['views'],
            'last_reply_at' => $threadData['data']['meta']['last_reply_at']
        ];
        Cache::put($key . ':meta', $metadata, self::LONG_CACHE_TTL);
        
        return $threadData;
    }

    /**
     * Get cached thread
     */
    public function getCachedThread($threadId)
    {
        $key = "forum:thread:{$threadId}";
        return Cache::get($key);
    }

    /**
     * Cache forum categories
     */
    public function cacheCategories($categories)
    {
        $key = "forum:categories";
        Cache::put($key, $categories, self::LONG_CACHE_TTL);
        return $categories;
    }

    /**
     * Get cached categories
     */
    public function getCachedCategories()
    {
        $key = "forum:categories";
        return Cache::get($key);
    }

    /**
     * Cache user flair data to avoid repeated queries
     */
    public function cacheUserFlairs($userIds, $flairs)
    {
        foreach ($flairs as $userId => $flair) {
            $key = "user:flair:{$userId}";
            Cache::put($key, $flair, self::LONG_CACHE_TTL);
        }
        return $flairs;
    }

    /**
     * Get cached user flairs
     */
    public function getCachedUserFlairs($userIds)
    {
        $cached = [];
        $missing = [];
        
        foreach ($userIds as $userId) {
            $key = "user:flair:{$userId}";
            $flair = Cache::get($key);
            
            if ($flair !== null) {
                $cached[$userId] = $flair;
            } else {
                $missing[] = $userId;
            }
        }
        
        return ['cached' => $cached, 'missing' => $missing];
    }

    /**
     * Cache hot threads for quick access
     */
    public function cacheHotThreads($threads)
    {
        $key = "forum:hot_threads";
        Cache::put($key, $threads, self::REALTIME_TTL);
        return $threads;
    }

    /**
     * Get cached hot threads
     */
    public function getCachedHotThreads()
    {
        return Cache::get("forum:hot_threads");
    }

    /**
     * Cache search results
     */
    public function cacheSearchResults($query, $filters, $results)
    {
        $key = "forum:search:" . md5($query . serialize($filters));
        Cache::put($key, $results, self::CACHE_TTL);
        return $results;
    }

    /**
     * Get cached search results
     */
    public function getCachedSearchResults($query, $filters)
    {
        $key = "forum:search:" . md5($query . serialize($filters));
        return Cache::get($key);
    }

    /**
     * Cache forum statistics
     */
    public function cacheForumStats($stats)
    {
        $key = "forum:stats";
        Cache::put($key, $stats, self::CACHE_TTL);
        return $stats;
    }

    /**
     * Get cached forum statistics
     */
    public function getCachedForumStats()
    {
        return Cache::get("forum:stats");
    }

    /**
     * Invalidate thread caches when updated
     */
    public function invalidateThread($threadId)
    {
        // Remove specific thread cache
        Cache::forget("forum:thread:{$threadId}");
        Cache::forget("forum:thread:{$threadId}:meta");
        
        // Remove from listings that might contain this thread
        $this->invalidateThreadListings();
        
        // Update real-time metrics
        $this->updateRealTimeMetrics('thread_updated', $threadId);
    }

    /**
     * Invalidate thread listing caches
     */
    public function invalidateThreadListings()
    {
        $categories = ['all', 'general', 'strategy', 'competitive', 'recruitment', 'bugs'];
        $sorts = ['latest', 'hot', 'popular', 'oldest'];
        
        foreach ($categories as $category) {
            foreach ($sorts as $sort) {
                // Clear first few pages (most commonly accessed)
                for ($page = 1; $page <= 5; $page++) {
                    Cache::forget("forum:threads:{$category}:{$sort}:page:{$page}");
                }
            }
        }
        
        // Clear hot threads
        Cache::forget("forum:hot_threads");
    }

    /**
     * Invalidate user-related caches
     */
    public function invalidateUser($userId)
    {
        Cache::forget("user:flair:{$userId}");
        
        // Get threads/posts by this user and invalidate them
        $threadIds = DB::table('forum_threads')->where('user_id', $userId)->pluck('id');
        foreach ($threadIds as $threadId) {
            $this->invalidateThread($threadId);
        }
    }

    /**
     * Invalidate search caches
     */
    public function invalidateSearchCaches()
    {
        // This is a simplified approach - in production you'd want more targeted invalidation
        $keys = Cache::getRedis()->keys('forum:search:*');
        if (!empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }

    /**
     * Update real-time metrics using Redis
     */
    public function updateRealTimeMetrics($event, $resourceId, $data = [])
    {
        try {
            $redis = Redis::connection();
            
            $eventData = [
                'event' => $event,
                'resource_id' => $resourceId,
                'timestamp' => Carbon::now()->toISOString(),
                'data' => $data
            ];
            
            // Store in a sorted set for time-based queries
            $redis->zadd('forum:realtime:events', time(), json_encode($eventData));
            
            // Keep only last 1000 events
            $redis->zremrangebyrank('forum:realtime:events', 0, -1001);
            
            // Publish to WebSocket channel for real-time updates
            $redis->publish('forum:updates', json_encode($eventData));
            
        } catch (\Exception $e) {
            // Log but don't fail the main operation
            \Log::warning('Failed to update real-time metrics', [
                'event' => $event,
                'resource_id' => $resourceId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get real-time forum activity
     */
    public function getRecentActivity($limit = 50)
    {
        try {
            $redis = Redis::connection();
            
            // Get recent events from last 24 hours
            $since = time() - 86400;
            $events = $redis->zrevrangebyscore('forum:realtime:events', time(), $since, [
                'LIMIT' => [0, $limit]
            ]);
            
            return array_map(function($event) {
                return json_decode($event, true);
            }, $events);
            
        } catch (\Exception $e) {
            \Log::error('Failed to get recent forum activity', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Warm up essential caches
     */
    public function warmUpCaches()
    {
        try {
            // Cache categories
            $categories = DB::table('forum_categories')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            $this->cacheCategories(['data' => $categories, 'success' => true]);
            
            // Cache hot threads
            $hotThreads = DB::table('forum_threads')
                ->where('status', 'active')
                ->orderBy('pinned', 'desc')
                ->orderByRaw('(replies_count * 0.6 + views * 0.001 + score * 0.4) DESC')
                ->limit(20)
                ->get();
            $this->cacheHotThreads(['data' => $hotThreads, 'success' => true]);
            
            // Cache forum stats
            $stats = [
                'total_threads' => DB::table('forum_threads')->where('status', 'active')->count(),
                'total_posts' => DB::table('forum_posts')->where('status', 'active')->count(),
                'active_users' => DB::table('users')->where('last_login', '>=', Carbon::now()->subDays(7))->count()
            ];
            $this->cacheForumStats($stats);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to warm up forum caches', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Clear all forum caches (use with caution)
     */
    public function clearAllCaches()
    {
        try {
            $patterns = [
                'forum:*',
                'user:flair:*'
            ];
            
            foreach ($patterns as $pattern) {
                $keys = Cache::getRedis()->keys($pattern);
                if (!empty($keys)) {
                    Cache::getRedis()->del($keys);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to clear forum caches', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats()
    {
        try {
            $patterns = [
                'forum:threads:*',
                'forum:thread:*',
                'forum:search:*',
                'user:flair:*'
            ];
            
            $stats = [];
            foreach ($patterns as $pattern) {
                $keys = Cache::getRedis()->keys($pattern);
                $stats[str_replace('*', 'count', $pattern)] = count($keys);
            }
            
            return $stats;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}