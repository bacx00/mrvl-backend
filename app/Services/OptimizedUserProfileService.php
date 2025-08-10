<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class OptimizedUserProfileService
{
    /**
     * Cache duration constants
     */
    const PROFILE_CACHE_DURATION = 1800; // 30 minutes
    const STATS_CACHE_DURATION = 900;    // 15 minutes
    const ACTIVITY_CACHE_DURATION = 600; // 10 minutes
    
    /**
     * Get user profile with all related data in a single optimized query
     */
    public function getCompleteUserProfile($userId)
    {
        return Cache::remember(
            "complete_profile_{$userId}",
            self::PROFILE_CACHE_DURATION,
            function () use ($userId) {
                // Single query to get user with team flair data
                return DB::selectOne("
                    SELECT 
                        u.id,
                        u.name,
                        u.email,
                        u.avatar,
                        u.hero_flair,
                        u.team_flair_id,
                        u.show_hero_flair,
                        u.show_team_flair,
                        u.use_hero_as_avatar,
                        u.status,
                        u.last_login,
                        u.created_at,
                        u.role,
                        
                        -- Team flair data
                        t.name as team_name,
                        t.short_name as team_short_name,
                        t.logo as team_logo,
                        t.region as team_region,
                        
                        -- Hero flair data (if exists in heroes table)
                        h.role as hero_role,
                        h.image_url as hero_image_url
                        
                    FROM users u
                    LEFT JOIN teams t ON u.team_flair_id = t.id
                    LEFT JOIN marvel_rivals_heroes h ON u.hero_flair = h.name
                    WHERE u.id = ?
                ", [$userId]);
            }
        );
    }
    
    /**
     * Get user statistics with ultra-optimized single query
     */
    public function getUserStatisticsOptimized($userId)
    {
        return Cache::remember(
            "user_stats_optimized_{$userId}",
            self::STATS_CACHE_DURATION,
            function () use ($userId) {
                // Single query aggregating all user statistics
                $stats = DB::selectOne("
                    SELECT 
                        -- Comment statistics
                        COALESCE(comment_stats.news_comments, 0) as news_comments,
                        COALESCE(comment_stats.match_comments, 0) as match_comments,
                        
                        -- Forum statistics
                        COALESCE(forum_stats.threads, 0) as forum_threads,
                        COALESCE(forum_stats.posts, 0) as forum_posts,
                        
                        -- Vote statistics
                        COALESCE(vote_stats.upvotes_given, 0) as upvotes_given,
                        COALESCE(vote_stats.downvotes_given, 0) as downvotes_given,
                        COALESCE(vote_stats.upvotes_received, 0) as upvotes_received,
                        COALESCE(vote_stats.downvotes_received, 0) as downvotes_received,
                        
                        -- Activity timestamps
                        GREATEST(
                            COALESCE(comment_stats.latest_comment, '1970-01-01'),
                            COALESCE(forum_stats.latest_forum_activity, '1970-01-01'),
                            COALESCE(vote_stats.latest_vote, '1970-01-01')
                        ) as last_activity
                        
                    FROM (SELECT 1) dummy
                    
                    LEFT JOIN (
                        SELECT 
                            SUM(CASE WHEN source = 'news' THEN count_val ELSE 0 END) as news_comments,
                            SUM(CASE WHEN source = 'match' THEN count_val ELSE 0 END) as match_comments,
                            MAX(latest_created) as latest_comment
                        FROM (
                            SELECT 'news' as source, COUNT(*) as count_val, MAX(created_at) as latest_created
                            FROM news_comments WHERE user_id = ?
                            UNION ALL
                            SELECT 'match' as source, COUNT(*) as count_val, MAX(created_at) as latest_created  
                            FROM match_comments WHERE user_id = ?
                        ) comment_union
                    ) comment_stats ON 1=1
                    
                    LEFT JOIN (
                        SELECT 
                            SUM(CASE WHEN source = 'threads' THEN count_val ELSE 0 END) as threads,
                            SUM(CASE WHEN source = 'posts' THEN count_val ELSE 0 END) as posts,
                            MAX(latest_created) as latest_forum_activity
                        FROM (
                            SELECT 'threads' as source, COUNT(*) as count_val, MAX(created_at) as latest_created
                            FROM forum_threads WHERE user_id = ?
                            UNION ALL
                            SELECT 'posts' as source, COUNT(*) as count_val, MAX(created_at) as latest_created
                            FROM forum_posts WHERE user_id = ?
                        ) forum_union
                    ) forum_stats ON 1=1
                    
                    LEFT JOIN (
                        SELECT 
                            SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as upvotes_given,
                            SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as downvotes_given,
                            COALESCE(received.upvotes, 0) as upvotes_received,
                            COALESCE(received.downvotes, 0) as downvotes_received,
                            MAX(votes.created_at) as latest_vote
                        FROM votes
                        LEFT JOIN (
                            SELECT 
                                SUM(CASE WHEN v.vote = 1 THEN 1 ELSE 0 END) as upvotes,
                                SUM(CASE WHEN v.vote = -1 THEN 1 ELSE 0 END) as downvotes
                            FROM votes v
                            INNER JOIN (
                                SELECT 'news_comments' as table_name, id, user_id FROM news_comments WHERE user_id = ?
                                UNION ALL
                                SELECT 'match_comments' as table_name, id, user_id FROM match_comments WHERE user_id = ?
                                UNION ALL
                                SELECT 'forum_threads' as table_name, id, user_id FROM forum_threads WHERE user_id = ?
                                UNION ALL
                                SELECT 'forum_posts' as table_name, id, user_id FROM forum_posts WHERE user_id = ?
                            ) user_content ON v.voteable_type = user_content.table_name AND v.voteable_id = user_content.id
                        ) received ON 1=1
                        WHERE votes.user_id = ?
                    ) vote_stats ON 1=1
                ", [
                    $userId, $userId,  // comment stats
                    $userId, $userId,  // forum stats  
                    $userId, $userId, $userId, $userId, $userId  // vote stats
                ]);
                
                if (!$stats) {
                    return $this->getEmptyStats();
                }
                
                return [
                    'comments' => [
                        'news' => (int) $stats->news_comments,
                        'matches' => (int) $stats->match_comments,
                        'total' => (int) ($stats->news_comments + $stats->match_comments)
                    ],
                    'forum' => [
                        'threads' => (int) $stats->forum_threads,
                        'posts' => (int) $stats->forum_posts,
                        'total' => (int) ($stats->forum_threads + $stats->forum_posts)
                    ],
                    'votes' => [
                        'upvotes_given' => (int) $stats->upvotes_given,
                        'downvotes_given' => (int) $stats->downvotes_given,
                        'upvotes_received' => (int) $stats->upvotes_received,
                        'downvotes_received' => (int) $stats->downvotes_received,
                        'reputation_score' => (int) ($stats->upvotes_received - $stats->downvotes_received)
                    ],
                    'activity' => [
                        'last_activity' => $stats->last_activity,
                        'total_actions' => (int) ($stats->news_comments + $stats->match_comments + 
                                                 $stats->forum_threads + $stats->forum_posts + 
                                                 $stats->upvotes_given + $stats->downvotes_given)
                    ]
                ];
            }
        );
    }
    
    /**
     * Get recent user activity with pagination and optimal performance
     */
    public function getRecentActivityOptimized($userId, $limit = 10, $offset = 0)
    {
        return Cache::remember(
            "recent_activity_v2_{$userId}_{$limit}_{$offset}",
            self::ACTIVITY_CACHE_DURATION,
            function () use ($userId, $limit, $offset) {
                // Optimized query for recent activity with proper indexing
                $activities = DB::select("
                    SELECT * FROM (
                        (
                            SELECT 
                                'comment' as activity_type,
                                'news' as context,
                                nc.created_at,
                                LEFT(nc.content, 150) as preview,
                                n.title as target_title,
                                n.id as target_id,
                                n.slug as target_slug
                            FROM news_comments nc
                            USE INDEX (idx_news_comments_user_created)
                            INNER JOIN news n ON nc.news_id = n.id
                            WHERE nc.user_id = ?
                            ORDER BY nc.created_at DESC
                            LIMIT ?
                        )
                        UNION ALL
                        (
                            SELECT 
                                'comment' as activity_type,
                                'match' as context,
                                mc.created_at,
                                LEFT(mc.content, 150) as preview,
                                CONCAT(COALESCE(t1.short_name, 'T1'), ' vs ', COALESCE(t2.short_name, 'T2')) as target_title,
                                m.id as target_id,
                                NULL as target_slug
                            FROM match_comments mc
                            USE INDEX (idx_match_comments_user_created)
                            INNER JOIN matches m ON mc.match_id = m.id
                            LEFT JOIN teams t1 ON m.team1_id = t1.id
                            LEFT JOIN teams t2 ON m.team2_id = t2.id
                            WHERE mc.user_id = ?
                            ORDER BY mc.created_at DESC
                            LIMIT ?
                        )
                        UNION ALL
                        (
                            SELECT 
                                'thread' as activity_type,
                                'forum' as context,
                                ft.created_at,
                                ft.title as preview,
                                ft.title as target_title,
                                ft.id as target_id,
                                NULL as target_slug
                            FROM forum_threads ft
                            USE INDEX (idx_forum_threads_user_created)
                            WHERE ft.user_id = ?
                            ORDER BY ft.created_at DESC
                            LIMIT ?
                        )
                        UNION ALL
                        (
                            SELECT 
                                'post' as activity_type,
                                'forum' as context,
                                fp.created_at,
                                LEFT(fp.content, 150) as preview,
                                ft.title as target_title,
                                ft.id as target_id,
                                NULL as target_slug
                            FROM forum_posts fp
                            USE INDEX (idx_forum_posts_user_created)
                            INNER JOIN forum_threads ft ON fp.thread_id = ft.id
                            WHERE fp.user_id = ?
                            ORDER BY fp.created_at DESC
                            LIMIT ?
                        )
                    ) combined
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?
                ", [
                    $userId, $limit,    // news comments
                    $userId, $limit,    // match comments  
                    $userId, $limit,    // forum threads
                    $userId, $limit,    // forum posts
                    $limit, $offset     // final limit/offset
                ]);
                
                return collect($activities)->map(function($activity) {
                    $activity->time_ago = $this->getTimeAgo($activity->created_at);
                    return $activity;
                });
            }
        );
    }
    
    /**
     * Batch load multiple user profiles efficiently
     */
    public function batchLoadUserProfiles(array $userIds)
    {
        if (empty($userIds)) {
            return collect();
        }
        
        $cacheKey = 'batch_profiles_' . md5(implode(',', sort($userIds)));
        
        return Cache::remember(
            $cacheKey,
            self::PROFILE_CACHE_DURATION,
            function () use ($userIds) {
                $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                
                $profiles = DB::select("
                    SELECT 
                        u.id,
                        u.name,
                        u.avatar,
                        u.hero_flair,
                        u.show_hero_flair,
                        u.show_team_flair,
                        u.use_hero_as_avatar,
                        
                        -- Team flair data
                        t.name as team_name,
                        t.short_name as team_short_name,
                        t.logo as team_logo,
                        t.region as team_region
                        
                    FROM users u
                    LEFT JOIN teams t ON u.team_flair_id = t.id
                    WHERE u.id IN ({$placeholders})
                    ORDER BY FIELD(u.id, {$placeholders})
                ", array_merge($userIds, $userIds));
                
                return collect($profiles)->keyBy('id');
            }
        );
    }
    
    /**
     * Clear all caches for a specific user
     */
    public function clearUserCaches($userId)
    {
        $patterns = [
            "complete_profile_{$userId}",
            "user_stats_optimized_{$userId}",
            "recent_activity_v2_{$userId}_*",
            "user_display_{$userId}"
        ];
        
        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need to implement cache tag-based clearing
                // or use Redis SCAN for pattern-based deletion
                continue;
            }
            Cache::forget($pattern);
        }
    }
    
    /**
     * Get empty statistics structure
     */
    private function getEmptyStats()
    {
        return [
            'comments' => ['news' => 0, 'matches' => 0, 'total' => 0],
            'forum' => ['threads' => 0, 'posts' => 0, 'total' => 0],
            'votes' => [
                'upvotes_given' => 0, 'downvotes_given' => 0,
                'upvotes_received' => 0, 'downvotes_received' => 0,
                'reputation_score' => 0
            ],
            'activity' => ['last_activity' => null, 'total_actions' => 0]
        ];
    }
    
    /**
     * Get time ago string
     */
    private function getTimeAgo($datetime)
    {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j', $time);
    }
}