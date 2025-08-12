<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Cache, DB, Log};
use Carbon\Carbon;

class UserActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'content',
        'resource_type',
        'resource_id',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
        'url',
        'referrer'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Activity type constants
    const TYPE_PAGE_VIEW = 'page_view';
    const TYPE_USER_REGISTRATION = 'user_registration';
    const TYPE_USER_LOGIN = 'user_login';
    const TYPE_USER_LOGOUT = 'user_logout';
    const TYPE_PROFILE_UPDATE = 'profile_update';
    const TYPE_FORUM_POST = 'forum_post';
    const TYPE_FORUM_THREAD = 'forum_thread';
    const TYPE_NEWS_COMMENT = 'news_comment';
    const TYPE_MATCH_VIEW = 'match_view';
    const TYPE_TEAM_VIEW = 'team_view';
    const TYPE_PLAYER_VIEW = 'player_view';
    const TYPE_EVENT_VIEW = 'event_view';
    const TYPE_SEARCH = 'search';
    const TYPE_VOTE = 'vote';
    const TYPE_FOLLOW = 'follow';
    const TYPE_CONVERSION = 'conversion';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enhanced activity tracking with real-time updates
     */
    public static function track($userId, $action, $content, $resourceType = null, $resourceId = null, $metadata = [])
    {
        try {
            // Add additional context to metadata
            $enhancedMetadata = array_merge($metadata, [
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
                'url' => request()->url(),
                'referrer' => request()->header('referer')
            ]);

            $activity = self::create([
                'user_id' => $userId,
                'action' => $action,
                'content' => $content,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'metadata' => $enhancedMetadata,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
                'url' => request()->url(),
                'referrer' => request()->header('referer')
            ]);

            // Update real-time analytics caches
            self::updateAnalyticsCache($action, $resourceType, $userId);

            // Broadcast to real-time analytics if configured
            self::broadcastActivityUpdate($activity);

            return $activity;

        } catch (\Exception $e) {
            Log::error('Activity tracking error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Track page view with enhanced metadata
     */
    public static function trackPageView($userId, $url, $resourceType = null, $resourceId = null)
    {
        return self::track($userId, self::TYPE_PAGE_VIEW, "Viewed: {$url}", $resourceType, $resourceId, [
            'page_url' => $url,
            'page_title' => self::extractPageTitle($url),
            'load_time' => request()->header('X-Load-Time'),
            'screen_resolution' => request()->header('X-Screen-Resolution')
        ]);
    }

    /**
     * Track user engagement events
     */
    public static function trackEngagement($userId, $engagementType, $details = [])
    {
        $engagementActions = [
            'scroll_depth' => 'Scrolled to depth',
            'time_on_page' => 'Spent time on page',
            'click_interaction' => 'Clicked element',
            'form_submission' => 'Submitted form',
            'video_play' => 'Played video',
            'download' => 'Downloaded file'
        ];

        $action = $engagementActions[$engagementType] ?? $engagementType;
        
        return self::track($userId, "engagement_{$engagementType}", $action, 'engagement', null, $details);
    }

    /**
     * Track conversion events
     */
    public static function trackConversion($userId, $conversionType, $value = null, $metadata = [])
    {
        return self::track($userId, self::TYPE_CONVERSION, "Conversion: {$conversionType}", 'conversion', null, 
            array_merge($metadata, [
                'conversion_type' => $conversionType,
                'conversion_value' => $value,
                'conversion_time' => now()->toISOString()
            ])
        );
    }

    /**
     * Get activity analytics for a specific period
     */
    public static function getAnalytics($startDate = null, $endDate = null, $userId = null)
    {
        $query = self::query();
        
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return [
            'total_activities' => $query->count(),
            'unique_users' => $query->distinct('user_id')->count('user_id'),
            'activity_breakdown' => $query->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->get(),
            'hourly_distribution' => self::getHourlyDistribution($query),
            'top_pages' => self::getTopPages($query),
            'user_engagement' => self::getUserEngagementMetrics($query)
        ];
    }

    /**
     * Get real-time activity feed
     */
    public static function getRealTimeFeed($limit = 50)
    {
        return Cache::remember('analytics:real_time_feed', 30, function() use ($limit) {
            return self::with('user:id,name,avatar')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'user' => $activity->user,
                        'action' => $activity->action,
                        'content' => $activity->content,
                        'resource_type' => $activity->resource_type,
                        'resource_id' => $activity->resource_id,
                        'timestamp' => $activity->created_at->toISOString(),
                        'time_ago' => $activity->created_at->diffForHumans()
                    ];
                });
        });
    }

    /**
     * Get user activity timeline
     */
    public function getTimelineAttribute()
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'content' => $this->content,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'timestamp' => $this->created_at->toISOString(),
            'time_ago' => $this->created_at->diffForHumans(),
            'metadata' => $this->metadata
        ];
    }

    /**
     * Scope for specific activity types
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('action', $type);
    }

    /**
     * Scope for activities within date range
     */
    public function scopeInPeriod($query, $startDate, $endDate = null)
    {
        $query->where('created_at', '>=', $startDate);
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Scope for resource-specific activities
     */
    public function scopeForResource($query, $resourceType, $resourceId = null)
    {
        $query->where('resource_type', $resourceType);
        
        if ($resourceId) {
            $query->where('resource_id', $resourceId);
        }
        
        return $query;
    }

    // Private helper methods
    private static function updateAnalyticsCache($action, $resourceType, $userId)
    {
        // Update various cache counters
        $cacheKeys = [
            'analytics:total_activities',
            'analytics:daily_activities:' . now()->format('Y-m-d'),
            'analytics:hourly_activities:' . now()->format('Y-m-d-H'),
            "analytics:action_count:{$action}",
            'analytics:active_users:' . now()->format('Y-m-d')
        ];

        foreach ($cacheKeys as $key) {
            $currentValue = Cache::get($key, 0);
            
            // Set expiration based on key type and use put with TTL
            if (str_contains($key, 'daily')) {
                Cache::put($key, $currentValue + 1, now()->endOfDay()->diffInSeconds());
            } elseif (str_contains($key, 'hourly')) {
                Cache::put($key, $currentValue + 1, now()->endOfHour()->diffInSeconds());
            } else {
                Cache::increment($key);
            }
        }

        // Track unique active users - store as serialized array for database cache
        $uniqueUsersKey = 'analytics:unique_users:' . now()->format('Y-m-d');
        $uniqueUsers = Cache::get($uniqueUsersKey, []);
        if (!in_array($userId, $uniqueUsers)) {
            $uniqueUsers[] = $userId;
            Cache::put($uniqueUsersKey, $uniqueUsers, now()->endOfDay());
        }
    }

    private static function broadcastActivityUpdate($activity)
    {
        try {
            // Broadcast to WebSocket/Redis for real-time updates
            if (function_exists('redis') && config('broadcasting.default') === 'redis') {
                $payload = [
                    'type' => 'user_activity',
                    'data' => $activity->timeline,
                    'timestamp' => now()->toISOString()
                ];

                \Redis::publish('analytics_updates', json_encode($payload));
            }
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast activity update: ' . $e->getMessage());
        }
    }

    private static function extractPageTitle($url)
    {
        // Extract a human-readable title from URL
        $pathSegments = explode('/', parse_url($url, PHP_URL_PATH));
        $lastSegment = end($pathSegments);
        
        return ucwords(str_replace(['-', '_'], ' ', $lastSegment)) ?: 'Page';
    }

    private static function getHourlyDistribution($query)
    {
        return $query->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour');
    }

    private static function getTopPages($query)
    {
        return $query->where('action', self::TYPE_PAGE_VIEW)
            ->selectRaw('url, COUNT(*) as visits')
            ->groupBy('url')
            ->orderBy('visits', 'desc')
            ->limit(10)
            ->get();
    }

    private static function getUserEngagementMetrics($query)
    {
        $totalUsers = $query->distinct('user_id')->count('user_id');
        $avgActivitiesPerUser = $totalUsers > 0 ? $query->count() / $totalUsers : 0;
        
        return [
            'total_users' => $totalUsers,
            'avg_activities_per_user' => round($avgActivitiesPerUser, 2),
            'engagement_score' => min(round($avgActivitiesPerUser * 10, 2), 100)
        ];
    }
}