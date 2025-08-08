<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // For file/database cache, we'll use a simple approach to clear search-related cache
        // This is less efficient than Redis pattern matching but works without Redis
        // Use cache helper for safe clearing with tag fallback
        $searchCacheKeys = [
            'forum:search:threads',
            'forum:search:posts', 
            'forum:search:users',
            'forum:search:recent',
            'forum:search:popular'
        ];
        
        \App\Helpers\CacheHelper::clearWithTagFallback(['forum_search'], $searchCacheKeys);
    }

    /**
     * Update real-time metrics using database/cache (Redis replacement)
     */
    public function updateRealTimeMetrics($event, $resourceId, $data = [])
    {
        try {
            $eventData = [
                'event' => $event,
                'resource_id' => $resourceId,
                'timestamp' => Carbon::now()->toISOString(),
                'data' => $data
            ];
            
            // Store in cache instead of Redis sorted set
            $eventsKey = 'forum:realtime:events';
            $events = Cache::get($eventsKey, []);
            
            // Add new event with timestamp as key for sorting
            $events[time() . '_' . uniqid()] = $eventData;
            
            // Keep only last 1000 events by removing oldest ones
            if (count($events) > 1000) {
                // Sort by key (timestamp) and keep only the latest 1000
                ksort($events);
                $events = array_slice($events, -1000, null, true);
            }
            
            // Store back in cache (keep for 24 hours)
            Cache::put($eventsKey, $events, 1440); // 24 hours in minutes
            
            // For real-time updates, we'll use a simple cache-based approach instead of Redis pub/sub
            $updateKey = "forum:live_update:" . time() . ':' . uniqid();
            Cache::put($updateKey, $eventData, 5); // Keep for 5 minutes
            
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
            // Get events from cache instead of Redis
            $eventsKey = 'forum:realtime:events';
            $events = Cache::get($eventsKey, []);
            
            // Filter events from last 24 hours
            $since = time() - 86400;
            $recentEvents = [];
            
            foreach ($events as $key => $eventData) {
                // Extract timestamp from key
                $timestamp = (int) explode('_', $key)[0];
                if ($timestamp >= $since) {
                    $recentEvents[$timestamp] = $eventData;
                }
            }
            
            // Sort by timestamp (descending) and limit
            krsort($recentEvents);
            return array_slice(array_values($recentEvents), 0, $limit);
            
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
            // For file/database cache, we'll use a more general approach
            // Clear specific known cache keys rather than pattern matching
            $cacheKeys = [
                'forum:categories',
                'forum:hot_threads',
                'forum:stats',
                'forum:realtime:events'
            ];
            
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
            
            // Use cache helper for comprehensive cache clearing
            \App\Helpers\CacheHelper::clearForumCaches();
            
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
            // For file/database cache, we can't easily get pattern-based statistics
            // Return basic information about known cache keys instead
            $knownKeys = [
                'forum:categories',
                'forum:hot_threads', 
                'forum:stats',
                'forum:realtime:events'
            ];
            
            $stats = [];
            foreach ($knownKeys as $key) {
                $exists = Cache::has($key);
                $stats[$key] = $exists ? 1 : 0;
            }
            
            // Add basic statistics
            $stats['cache_driver'] = config('cache.default');
            $stats['total_known_keys'] = count($knownKeys);
            $stats['active_keys'] = array_sum($stats);
            
            return $stats;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}