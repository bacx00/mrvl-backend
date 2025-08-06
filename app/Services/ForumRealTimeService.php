<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Events\ForumThreadCreated;
use App\Events\ForumPostCreated;
use App\Events\ForumThreadUpdated;
use App\Events\ForumVoteUpdated;

class ForumRealTimeService
{
    private $redis;
    private $cacheService;

    public function __construct(ForumCacheService $cacheService)
    {
        $this->redis = Redis::connection();
        $this->cacheService = $cacheService;
    }

    /**
     * Broadcast thread creation to subscribers
     */
    public function broadcastThreadCreated($threadId, $threadData)
    {
        $event = [
            'type' => 'thread_created',
            'thread_id' => $threadId,
            'data' => $threadData,
            'timestamp' => Carbon::now()->toISOString()
        ];

        // Broadcast to all forum subscribers
        $this->broadcast('forum.threads', $event);
        
        // Broadcast to category subscribers
        if (isset($threadData['category'])) {
            $this->broadcast("forum.category.{$threadData['category']}", $event);
        }

        // Update real-time metrics
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

        // Broadcast to thread subscribers
        $this->broadcast("forum.thread.{$threadId}", $event);
        
        // Broadcast to general forum
        $this->broadcast('forum.activity', $event);

        // Update thread activity metrics
        $this->updateThreadMetrics($threadId, 'new_post');
        
        // Process mentions for real-time notifications
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
            $this->broadcast("forum.thread.{$targetId}.votes", $event);
        } else {
            // For posts, we need to get the thread ID
            $post = DB::table('forum_posts')->where('id', $targetId)->first();
            if ($post) {
                $this->broadcast("forum.thread.{$post->thread_id}.votes", $event);
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
            $this->broadcast("forum.thread.{$targetId}", $event);
        }
        
        // Broadcast to moderators channel
        $this->broadcast('forum.moderation', $event);

        // Cache invalidation
        $this->cacheService->invalidateThread($targetId);
    }

    /**
     * Send real-time notifications to users
     */
    public function sendUserNotification($userId, $type, $data)
    {
        $notification = [
            'type' => $type,
            'data' => $data,
            'timestamp' => Carbon::now()->toISOString(),
            'read' => false
        ];

        // Store notification in Redis for persistence
        $this->redis->lpush("user:{$userId}:notifications", json_encode($notification));
        $this->redis->ltrim("user:{$userId}:notifications", 0, 99); // Keep last 100

        // Broadcast to user's WebSocket connection
        $this->broadcast("user.{$userId}.notifications", $notification);

        // Update notification count
        $this->redis->incr("user:{$userId}:unread_notifications");
        $this->redis->expire("user:{$userId}:unread_notifications", 86400 * 7); // 7 days
    }

    /**
     * Process mention notifications in real-time
     */
    private function processMentionNotifications($content, $threadId, $postId = null)
    {
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
    }

    /**
     * Get user's unread notifications
     */
    public function getUserNotifications($userId, $limit = 20)
    {
        $notifications = $this->redis->lrange("user:{$userId}:notifications", 0, $limit - 1);
        
        return array_map(function($notification) {
            return json_decode($notification, true);
        }, $notifications);
    }

    /**
     * Mark notifications as read
     */
    public function markNotificationsRead($userId, $notificationIds = null)
    {
        if ($notificationIds === null) {
            // Mark all as read
            $this->redis->del("user:{$userId}:unread_notifications");
        } else {
            // Mark specific notifications as read (simplified implementation)
            $count = $this->redis->get("user:{$userId}:unread_notifications") ?? 0;
            $newCount = max(0, $count - count($notificationIds));
            $this->redis->set("user:{$userId}:unread_notifications", $newCount);
        }

        // Broadcast update to user
        $this->broadcast("user.{$userId}.notifications", [
            'type' => 'notifications_read',
            'timestamp' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Get live forum activity feed
     */
    public function getActivityFeed($limit = 50)
    {
        $events = $this->redis->lrange('forum:activity_feed', 0, $limit - 1);
        
        return array_map(function($event) {
            return json_decode($event, true);
        }, $events);
    }

    /**
     * Get online users count
     */
    public function getOnlineUsersCount()
    {
        // Users who have been active in the last 5 minutes
        $onlineKey = 'forum:online_users';
        $fiveMinutesAgo = Carbon::now()->subMinutes(5)->timestamp;
        
        // Clean up old entries
        $this->redis->zremrangebyscore($onlineKey, 0, $fiveMinutesAgo);
        
        return $this->redis->zcard($onlineKey);
    }

    /**
     * Mark user as online
     */
    public function markUserOnline($userId)
    {
        $onlineKey = 'forum:online_users';
        $this->redis->zadd($onlineKey, time(), $userId);
        $this->redis->expire($onlineKey, 3600); // Expire after 1 hour of inactivity
    }

    /**
     * Get trending threads in real-time
     */
    public function getTrendingThreads($limit = 10)
    {
        $cacheKey = 'forum:trending_threads';
        
        // Check cache first
        $cached = $this->redis->get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
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
        $this->redis->setex($cacheKey, 120, json_encode($trending));

        return $trending;
    }

    /**
     * Update forum-wide metrics
     */
    private function updateForumMetrics($action)
    {
        $metricsKey = 'forum:metrics:' . date('Y-m-d-H'); // Hourly metrics
        
        $this->redis->hincrby($metricsKey, $action, 1);
        $this->redis->hincrby($metricsKey, 'total_activity', 1);
        $this->redis->expire($metricsKey, 86400 * 7); // Keep for 7 days

        // Update activity feed
        $activity = [
            'action' => $action,
            'timestamp' => Carbon::now()->toISOString(),
            'user_id' => Auth::id()
        ];
        
        $this->redis->lpush('forum:activity_feed', json_encode($activity));
        $this->redis->ltrim('forum:activity_feed', 0, 999); // Keep last 1000
    }

    /**
     * Update thread-specific metrics
     */
    private function updateThreadMetrics($threadId, $action)
    {
        $metricsKey = "forum:thread:{$threadId}:metrics";
        
        $this->redis->hincrby($metricsKey, $action, 1);
        $this->redis->hincrby($metricsKey, 'last_activity', time());
        $this->redis->expire($metricsKey, 86400 * 30); // Keep for 30 days
    }

    /**
     * Generic broadcast method
     */
    private function broadcast($channel, $data)
    {
        try {
            // Use Redis pub/sub for WebSocket broadcasting
            $this->redis->publish($channel, json_encode($data));
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast forum event', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Subscribe to forum channels (for WebSocket server)
     */
    public function subscribeToChannels($channels, $callback)
    {
        try {
            $this->redis->subscribe($channels, $callback);
        } catch (\Exception $e) {
            \Log::error('Failed to subscribe to forum channels', [
                'channels' => $channels,
                'error' => $e->getMessage()
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
            $hourData = $this->redis->hgetall($key);
            
            if (!empty($hourData)) {
                $metrics[$hour] = $hourData;
            }
        }
        
        return $metrics;
    }

    /**
     * Cleanup old real-time data
     */
    public function cleanup()
    {
        // Remove old activity feed entries
        $this->redis->ltrim('forum:activity_feed', 0, 999);
        
        // Remove old online users
        $fiveMinutesAgo = Carbon::now()->subMinutes(5)->timestamp;
        $this->redis->zremrangebyscore('forum:online_users', 0, $fiveMinutesAgo);
        
        // Remove old notification entries (older than 30 days)
        $patterns = $this->redis->keys('user:*:notifications');
        foreach ($patterns as $pattern) {
            $this->redis->ltrim($pattern, 0, 99);
        }
    }
}