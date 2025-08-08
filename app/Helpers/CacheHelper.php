<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheHelper
{
    /**
     * Clear cache with tag support fallback
     * 
     * @param array $tags Tags to clear (for Redis/Memcached)
     * @param array $fallbackKeys Specific keys to clear for file/database cache
     * @return bool
     */
    public static function clearWithTagFallback(array $tags, array $fallbackKeys = [])
    {
        try {
            // Try to use tags first (works with Redis, Memcached)
            Cache::tags($tags)->flush();
            Log::info('Cache cleared using tags', ['tags' => $tags]);
            return true;
        } catch (\Exception $e) {
            // Fall back to clearing specific keys (works with all drivers)
            Log::info('Cache tags not supported, using fallback keys', [
                'tags' => $tags, 
                'fallback_keys' => $fallbackKeys,
                'error' => $e->getMessage()
            ]);
            
            foreach ($fallbackKeys as $key) {
                Cache::forget($key);
                
                // Also clear paginated versions
                for ($page = 1; $page <= 20; $page++) {
                    Cache::forget($key . ':page:' . $page);
                    Cache::forget($key . '_page_' . $page);
                }
            }
            
            return true;
        }
    }
    
    /**
     * Check if the current cache driver supports tags
     * 
     * @return bool
     */
    public static function supportsTagging()
    {
        $driver = config('cache.default');
        $supportedDrivers = ['redis', 'memcached'];
        
        return in_array($driver, $supportedDrivers);
    }
    
    /**
     * Clear forum-related caches safely
     */
    public static function clearForumCaches()
    {
        $tags = ['forum', 'forum_threads', 'forum_search'];
        $fallbackKeys = [
            'forum:threads:all:latest',
            'forum:threads:general:latest',
            'forum:threads:tournaments:latest',
            'forum:threads:hero-discussion:latest',
            'forum:threads:strategy:latest',
            'forum:threads:esports:latest',
            'forum:threads:guides:latest',
            'forum:threads:patch-notes:latest',
            'forum:threads:bugs:latest',
            'forum:threads:feedback:latest',
            'forum:threads:team-recruitment:latest',
            'forum:threads:meta-discussion:latest',
            'forum:search:threads',
            'forum:search:posts',
            'forum:search:users',
            'forum:search:recent',
            'forum:search:popular',
            'forum:hot_threads',
            'forum:stats',
            'forum:categories'
        ];
        
        return self::clearWithTagFallback($tags, $fallbackKeys);
    }
}