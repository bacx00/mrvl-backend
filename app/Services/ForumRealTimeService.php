<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ForumRealTimeService
{
    private $cacheService;

    public function __construct(ForumCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Broadcast thread creation to subscribers (cache-based approach)
     */
    public function broadcastThreadCreated($threadId, $threadData)
    {
        $event = [
            'type' => 'thread_created',
            'thread_id' => $threadId,
            'data' => $threadData,
            'timestamp' => Carbon::now()->toISOString()
        ];

        // Store in activity feed (cache-based)
        $this->addToActivityFeed($event);
        
        // Store thread-specific update
        $this->storeThreadUpdate($threadId, $event);

        // Update forum metrics
        $this->updateForumMetrics('new_thread');
        
        // Cache invalidation
        $this->cacheService->invalidateThreadListings();
    }

    /**
     * Broadcast post creation to subscribers
     */
    public function broadcastPostCreated($threadId, $postId, $postData)
    {
        $event = [
            'type' => 'post_created',
            'thread_id' => $threadId,
            'post_id' => $postId,
            'data' => $postData,
            'timestamp' => Carbon::now()->toISOString()
        ];

        // Store in activity feed
        $this->addToActivityFeed($event);
        
        // Store thread-specific update
        $this->storeThreadUpdate($threadId, $event);

        // Update thread activity metrics
        $this->updateThreadMetrics($threadId, 'new_post');
        
        // Process mentions for notifications
        $this->processMentionNotifications($postData['content'] ?? '', $threadId, $postId);
    }

    /**
     * Broadcast vote updates
     */
    public function broadcastVoteUpdate($type, $targetId, $voteData)
    {
        $event = [
            'type' => 'vote_updated',
            'target_type' => $type, // 'thread' or 'post'
            'target_id' => $targetId,
            'data' => $voteData,
            'timestamp' => Carbon::now()->toISOString()
        ];

        if ($type === 'thread') {
            $this->storeThreadUpdate($targetId, $event);
        } else {
            // For posts, we need to get the thread ID
            $post = DB::table('forum_posts')->where('id', $targetId)->first();
            if ($post) {
                $this->storeThreadUpdate($post->thread_id, $event);
            }
        }

        // Update metrics
        $this->updateForumMetrics('vote_cast');
    }

    /**
     * Broadcast moderation actions
     */
    public function broadcastModerationAction($action, $targetType, $targetId, $moderatorData)
    {
        $event = [
            'type' => 'moderation_action',
            'action' => $action, // 'pin', 'unpin', 'lock', 'unlock', 'delete'
            'target_type' => $targetType,
            'target_id' => $targetId,
            'moderator' => $moderatorData,
            'timestamp' => Carbon::now()->toISOString()
        ];

        if ($targetType === 'thread') {
            $this->storeThreadUpdate($targetId, $event);
        }
        
        // Store in moderation activity feed
        $this->addToModerationFeed($event);

        // Cache invalidation
        $this->cacheService->invalidateThread($targetId);
    }

    /**
     * Send notifications to users (cache-based)
     */
    public function sendUserNotification($userId, $type, $data)
    {
        $notification = [
            'type' => $type,
            'data' => $data,
            'timestamp' => Carbon::now()->toISOString(),
            'read' => false,
            'id' => uniqid('notif_')
        ];

        // Store notification in cache
        $notificationsKey = "user:{$userId}:notifications";
        $notifications = Cache::get($notificationsKey, []);
        
        // Add new notification to the beginning
        array_unshift($notifications, $notification);
        
        // Keep only last 100 notifications
        $notifications = array_slice($notifications, 0, 100);
        
        // Store back in cache (keep for 7 days)
        Cache::put($notificationsKey, $notifications, 60 * 24 * 7);

        // Update notification count
        $countKey = "user:{$userId}:unread_notifications";
        $currentCount = Cache::get($countKey, 0);
        Cache::put($countKey, $currentCount + 1, 60 * 24 * 7);

        // Store live notification update
        $liveKey = "user:{$userId}:live_notification:" . time();
        Cache::put($liveKey, $notification, 5); // Keep for 5 minutes
    }

    /**
     * Process mention notifications
     */
    private function processMentionNotifications($content, $threadId, $postId = null)
    {
        try {
            // Extract @username mentions
            preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $userMatches);
            foreach ($userMatches[1] as $username) {
                $user = DB::table('users')->where('name', $username)->first();
                if ($user && $user->id !== Auth::id()) {
                    $this->sendUserNotification($user->id, 'mention', [
                        'type' => $postId ? 'post' : 'thread',
                        'thread_id' => $threadId,
                        'post_id' => $postId,
                        'mentioned_by' => [
                            'id' => Auth::id(),
                            'name' => Auth::user()->name ?? 'User'
                        ],
                        'preview' => substr($content, 0, 100) . '...'
                    ]);
                }
            }

            // Extract @team:shortname mentions
            preg_match_all('/@team:([a-zA-Z0-9_]+)/', $content, $teamMatches);
            foreach ($teamMatches[1] as $teamShort) {
                // Notify team members
                $teamMembers = DB::table('users as u')
                    ->join('teams as t', 'u.team_flair_id', '=', 't.id')
                    ->where('t.short_name', $teamShort)
                    ->where('u.id', '!=', Auth::id())
                    ->pluck('u.id');

                foreach ($teamMembers as $memberId) {
                    $this->sendUserNotification($memberId, 'team_mention', [
                        'type' => $postId ? 'post' : 'thread',
                        'thread_id' => $threadId,
                        'post_id' => $postId,
                        'team_short' => $teamShort,
                        'mentioned_by' => [
                            'id' => Auth::id(),
                            'name' => Auth::user()->name ?? 'User'
                        ]
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to process mention notifications', [
                'error' => $e->getMessage(),
                'thread_id' => $threadId,
                'post_id' => $postId
            ]);
        }
    }

    /**
     * Get user's notifications
     */
    public function getUserNotifications($userId, $limit = 20)
    {
        $notificationsKey = "user:{$userId}:notifications";
        $notifications = Cache::get($notificationsKey, []);
        
        return array_slice($notifications, 0, $limit);
    }

    /**
     * Mark notifications as read
     */
    public function markNotificationsRead($userId, $notificationIds = null)
    {
        $countKey = "user:{$userId}:unread_notifications";
        
        if ($notificationIds === null) {
            // Mark all as read
            Cache::forget($countKey);
        } else {
            // Mark specific notifications as read
            $count = Cache::get($countKey, 0);
            $newCount = max(0, $count - count($notificationIds));
            Cache::put($countKey, $newCount, 60 * 24 * 7);
        }

        // Store update notification
        $updateKey = "user:{$userId}:notifications_read:" . time();
        Cache::put($updateKey, [
            'type' => 'notifications_read',
            'timestamp' => Carbon::now()->toISOString()
        ], 2); // Keep for 2 minutes
    }

    /**
     * Get live forum activity feed
     */
    public function getActivityFeed($limit = 50)
    {
        $activityKey = 'forum:activity_feed';
        $events = Cache::get($activityKey, []);
        
        return array_slice($events, 0, $limit);
    }

    /**
     * Get online users count (simplified approach)
     */
    public function getOnlineUsersCount()
    {
        // Use cache to track online users
        $onlineKey = 'forum:online_users';
        $onlineUsers = Cache::get($onlineKey, []);
        
        // Filter users who have been active in the last 5 minutes
        $fiveMinutesAgo = time() - 300;
        $activeUsers = array_filter($onlineUsers, function($timestamp) use ($fiveMinutesAgo) {
            return $timestamp >= $fiveMinutesAgo;
        });
        
        // Update the cache with only active users
        Cache::put($onlineKey, $activeUsers, 60);
        
        return count($activeUsers);
    }

    /**
     * Mark user as online
     */
    public function markUserOnline($userId)
    {
        $onlineKey = 'forum:online_users';
        $onlineUsers = Cache::get($onlineKey, []);
        
        // Update user's last activity time
        $onlineUsers[$userId] = time();
        
        // Store back in cache
        Cache::put($onlineKey, $onlineUsers, 60);
    }

    /**
     * Get trending threads (database-based calculation)
     */
    public function getTrendingThreads($limit = 10)
    {
        $cacheKey = 'forum:trending_threads';
        
        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Calculate trending threads based on recent activity
        $trending = DB::select("
            SELECT 
                t.id,
                t.title,
                t.score,
                t.replies_count,
                t.views,
                t.created_at,
                (
                    (t.score * 0.3) +
                    (t.replies_count * 0.4) +
                    (t.views * 0.0001) +
                    CASE 
                        WHEN t.created_at > NOW() - INTERVAL 1 DAY THEN 20
                        WHEN t.created_at > NOW() - INTERVAL 3 DAY THEN 10
                        WHEN t.created_at > NOW() - INTERVAL 7 DAY THEN 5
                        ELSE 0
                    END +
                    CASE 
                        WHEN t.last_reply_at > NOW() - INTERVAL 1 HOUR THEN 15
                        WHEN t.last_reply_at > NOW() - INTERVAL 6 HOUR THEN 10
                        WHEN t.last_reply_at > NOW() - INTERVAL 1 DAY THEN 5
                        ELSE 0
                    END
                ) as trend_score
            FROM forum_threads t
            WHERE t.status = 'active'
            ORDER BY trend_score DESC, t.last_reply_at DESC
            LIMIT ?
        ", [$limit]);

        // Cache for 2 minutes
        Cache::put($cacheKey, $trending, 2);

        return $trending;
    }

    /**
     * Add event to activity feed
     */
    private function addToActivityFeed($event)
    {
        try {
            $activityKey = 'forum:activity_feed';
            $events = Cache::get($activityKey, []);
            
            // Add new event to the beginning
            array_unshift($events, $event);
            
            // Keep only last 200 events
            $events = array_slice($events, 0, 200);
            
            // Store back in cache (keep for 1 hour)
            Cache::put($activityKey, $events, 60);
        } catch (\Exception $e) {
            Log::warning('Failed to add to activity feed', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
        }
    }

    /**
     * Store thread-specific updates
     */
    private function storeThreadUpdate($threadId, $event)
    {
        try {
            $threadUpdatesKey = "forum:thread:{$threadId}:updates";
            $updates = Cache::get($threadUpdatesKey, []);
            
            // Add new update
            array_unshift($updates, $event);
            
            // Keep only last 50 updates per thread
            $updates = array_slice($updates, 0, 50);
            
            // Store for 30 minutes
            Cache::put($threadUpdatesKey, $updates, 30);
        } catch (\Exception $e) {
            Log::warning('Failed to store thread update', [
                'error' => $e->getMessage(),
                'thread_id' => $threadId
            ]);
        }
    }

    /**
     * Add event to moderation feed
     */
    private function addToModerationFeed($event)
    {
        try {
            $moderationKey = 'forum:moderation_feed';
            $events = Cache::get($moderationKey, []);
            
            // Add new event
            array_unshift($events, $event);
            
            // Keep only last 100 moderation events
            $events = array_slice($events, 0, 100);
            
            // Store for 24 hours
            Cache::put($moderationKey, $events, 60 * 24);
        } catch (\Exception $e) {
            Log::warning('Failed to add to moderation feed', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
        }
    }

    /**
     * Update forum-wide metrics
     */
    private function updateForumMetrics($action)
    {
        try {
            $metricsKey = 'forum:metrics:' . date('Y-m-d-H'); // Hourly metrics
            $metrics = Cache::get($metricsKey, []);
            
            // Increment action count
            $metrics[$action] = ($metrics[$action] ?? 0) + 1;
            $metrics['total_activity'] = ($metrics['total_activity'] ?? 0) + 1;
            
            // Store for 7 days
            Cache::put($metricsKey, $metrics, 60 * 24 * 7);

            // Update activity feed
            $this->addToActivityFeed([
                'action' => $action,
                'timestamp' => Carbon::now()->toISOString(),
                'user_id' => Auth::id()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update forum metrics', [
                'error' => $e->getMessage(),
                'action' => $action
            ]);
        }
    }

    /**
     * Update thread-specific metrics
     */
    private function updateThreadMetrics($threadId, $action)
    {
        try {
            $metricsKey = "forum:thread:{$threadId}:metrics";
            $metrics = Cache::get($metricsKey, []);
            
            // Update metrics
            $metrics[$action] = ($metrics[$action] ?? 0) + 1;
            $metrics['last_activity'] = time();
            
            // Store for 30 days
            Cache::put($metricsKey, $metrics, 60 * 24 * 30);
        } catch (\Exception $e) {
            Log::warning('Failed to update thread metrics', [
                'error' => $e->getMessage(),
                'thread_id' => $threadId
            ]);
        }
    }

    /**
     * Get forum metrics for analytics
     */
    public function getForumMetrics($hours = 24)
    {
        $metrics = [];
        $now = Carbon::now();
        
        for ($i = 0; $i < $hours; $i++) {
            $hour = $now->copy()->subHours($i)->format('Y-m-d-H');
            $key = "forum:metrics:{$hour}";
            $hourData = Cache::get($key, []);
            
            if (!empty($hourData)) {
                $metrics[$hour] = $hourData;
            }
        }
        
        return $metrics;
    }

    /**
     * Cleanup old cached data
     */
    public function cleanup()
    {
        try {
            // Clean up old activity feeds, notifications, etc.
            // This is a simplified cleanup since we can't pattern match keys easily
            
            // Clean up online users (remove inactive ones)
            $this->getOnlineUsersCount(); // This will clean up inactive users
            
            Log::info('Forum real-time service cleanup completed');
        } catch (\Exception $e) {
            Log::error('Failed to cleanup forum real-time data', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Placeholder methods for WebSocket compatibility (no-op without Redis)
     */
    public function subscribeToChannels($channels, $callback)
    {
        // Without Redis pub/sub, this becomes a no-op
        // In a real implementation, you might use Laravel's broadcasting
        Log::info('WebSocket channel subscription requested but not implemented without Redis', [
            'channels' => $channels
        ]);
    }

    /**
     * Generic broadcast method (simplified for cache-based approach)
     */
    private function broadcast($channel, $data)
    {
        try {
            // Store the broadcast data in cache for potential polling
            $broadcastKey = "forum:broadcast:" . str_replace('.', '_', $channel) . ':' . time();
            Cache::put($broadcastKey, $data, 5); // Keep for 5 minutes
            
            Log::debug('Broadcast stored in cache', [
                'channel' => $channel,
                'key' => $broadcastKey
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast forum event', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
        }
    }
}