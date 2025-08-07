<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\UserActivity;

class TrackUserActivity
{
    /**
     * Handle an incoming request and track user activity.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track for authenticated users and successful responses
        if (Auth::check() && $response->getStatusCode() < 400) {
            $this->trackActivity($request, $response);
        }

        return $response;
    }

    /**
     * Track specific user activities based on route and method
     */
    private function trackActivity(Request $request, $response)
    {
        $user = Auth::user();
        $route = $request->route();
        $method = $request->method();
        $routeName = $route ? $route->getName() : null;
        $uri = $request->getRequestUri();

        // Skip tracking for certain routes
        if ($this->shouldSkipTracking($uri, $method)) {
            return;
        }

        try {
            $activityData = $this->determineActivityData($request, $response, $uri, $method);
            
            if ($activityData) {
                UserActivity::track(
                    $user->id,
                    $activityData['action'],
                    $activityData['content'],
                    $activityData['resource_type'] ?? null,
                    $activityData['resource_id'] ?? null,
                    $activityData['metadata'] ?? []
                );
            }
        } catch (\Exception $e) {
            // Don't let activity tracking break the application
            Log::warning('Failed to track user activity: ' . $e->getMessage());
        }
    }

    /**
     * Determine if we should skip tracking for this request
     */
    private function shouldSkipTracking($uri, $method)
    {
        $skipPatterns = [
            '/api/auth/me',
            '/api/user/profile',
            '/api/user/stats',
            '/api/user/activity',
            '/api/mentions/search',
            '/api/mentions/popular',
            '/api/votes/stats',
            '/api/votes/user',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($uri, $pattern)) {
                return true;
            }
        }

        // Track important GET requests (page views)
        if ($method === 'GET') {
            // Track page views for important content
            $trackViewsFor = [
                '/api/news/',
                '/api/matches/',
                '/api/events/',
                '/api/teams/',
                '/api/players/',
                '/api/forum/threads/',
                '/api/rankings'
            ];
            
            $shouldTrackView = false;
            foreach ($trackViewsFor as $pattern) {
                if (str_contains($uri, $pattern)) {
                    $shouldTrackView = true;
                    break;
                }
            }
            
            if (!$shouldTrackView) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine activity data based on request
     */
    private function determineActivityData(Request $request, $response, $uri, $method)
    {
        // Page view tracking for GET requests
        if ($method === 'GET') {
            return $this->handlePageViewActivity($request, $uri);
        }

        // Forum activities
        if (str_contains($uri, '/api/user/forums') || str_contains($uri, '/api/forum')) {
            return $this->handleForumActivity($request, $uri, $method);
        }

        // News activities
        if (str_contains($uri, '/api/user/news') || str_contains($uri, '/api/news')) {
            return $this->handleNewsActivity($request, $uri, $method);
        }

        // Match activities
        if (str_contains($uri, '/api/matches')) {
            return $this->handleMatchActivity($request, $uri, $method);
        }

        // Event activities
        if (str_contains($uri, '/api/events')) {
            return $this->handleEventActivity($request, $uri, $method);
        }

        // Team activities
        if (str_contains($uri, '/api/teams')) {
            return $this->handleTeamActivity($request, $uri, $method);
        }

        // Voting activities
        if (str_contains($uri, '/api/user/votes') && $method === 'POST') {
            return $this->handleVotingActivity($request, $response);
        }

        // Profile updates
        if (str_contains($uri, '/api/user/profile') && $method !== 'GET') {
            return $this->handleProfileActivity($request, $uri, $method);
        }

        // Search activities
        if (str_contains($uri, '/api/search')) {
            return $this->handleSearchActivity($request, $uri, $method);
        }

        return null;
    }

    /**
     * Handle forum-related activities
     */
    private function handleForumActivity(Request $request, $uri, $method)
    {
        if ($method === 'POST' && str_contains($uri, '/threads')) {
            return [
                'action' => 'forum_thread_created',
                'content' => 'Created new forum thread: ' . ($request->input('title') ?? 'Untitled'),
                'resource_type' => 'forum_thread',
                'resource_id' => null, // Will be set after creation if available
                'metadata' => [
                    'title' => $request->input('title'),
                    'category_id' => $request->input('category_id')
                ]
            ];
        }

        if ($method === 'POST' && str_contains($uri, '/posts')) {
            preg_match('/threads\/(\d+)\/posts/', $uri, $matches);
            $threadId = $matches[1] ?? null;
            
            return [
                'action' => 'forum_post_created',
                'content' => 'Posted in forum thread',
                'resource_type' => 'forum_post',
                'resource_id' => null,
                'metadata' => [
                    'thread_id' => $threadId,
                    'content_preview' => substr($request->input('content', ''), 0, 100)
                ]
            ];
        }

        return null;
    }

    /**
     * Handle news-related activities
     */
    private function handleNewsActivity(Request $request, $uri, $method)
    {
        if ($method === 'POST' && str_contains($uri, '/comments')) {
            preg_match('/news\/(\d+)\/comments/', $uri, $matches);
            $newsId = $matches[1] ?? null;
            
            return [
                'action' => 'news_comment_created',
                'content' => 'Commented on news article',
                'resource_type' => 'news_comment',
                'resource_id' => null,
                'metadata' => [
                    'news_id' => $newsId,
                    'content_preview' => substr($request->input('content', ''), 0, 100)
                ]
            ];
        }

        return null;
    }

    /**
     * Handle match-related activities
     */
    private function handleMatchActivity(Request $request, $uri, $method)
    {
        if ($method === 'POST') {
            preg_match('/matches\/(\d+)\/comments/', $uri, $matches);
            $matchId = $matches[1] ?? null;
            
            return [
                'action' => 'match_comment_created',
                'content' => 'Commented on match',
                'resource_type' => 'match_comment',
                'resource_id' => null,
                'metadata' => [
                    'match_id' => $matchId,
                    'content_preview' => substr($request->input('content', ''), 0, 100)
                ]
            ];
        }

        return null;
    }

    /**
     * Handle voting activities
     */
    private function handleVotingActivity(Request $request, $response)
    {
        $voteType = $request->input('vote_type');
        $votableType = $request->input('votable_type');
        $votableId = $request->input('votable_id');
        
        $responseData = json_decode($response->getContent(), true);
        $action = $responseData['action'] ?? 'vote_cast';
        
        return [
            'action' => 'vote_' . $action,
            'content' => ucfirst($action) . " {$voteType} on {$votableType}",
            'resource_type' => $votableType,
            'resource_id' => $votableId,
            'metadata' => [
                'vote_type' => $voteType,
                'votable_type' => $votableType,
                'action' => $action
            ]
        ];
    }

    /**
     * Handle profile-related activities
     */
    private function handleProfileActivity(Request $request, $uri, $method)
    {
        if (str_contains($uri, '/flairs')) {
            return [
                'action' => 'profile_flairs_updated',
                'content' => 'Updated profile flairs',
                'resource_type' => 'user',
                'resource_id' => Auth::id(),
                'metadata' => [
                    'hero_flair' => $request->input('hero_flair'),
                    'team_flair_id' => $request->input('team_flair_id'),
                    'show_hero_flair' => $request->input('show_hero_flair'),
                    'show_team_flair' => $request->input('show_team_flair')
                ]
            ];
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            return [
                'action' => 'profile_updated',
                'content' => 'Updated profile information',
                'resource_type' => 'user',
                'resource_id' => Auth::id(),
                'metadata' => array_intersect_key($request->all(), array_flip([
                    'name', 'hero_flair', 'team_flair_id'
                ]))
            ];
        }

        return null;
    }

    /**
     * Handle page view activities
     */
    private function handlePageViewActivity(Request $request, $uri)
    {
        // Extract resource type and ID from URI
        $resourceType = null;
        $resourceId = null;
        $action = 'page_viewed';
        $content = 'Viewed page';

        if (preg_match('/\/api\/news\/(\d+)/', $uri, $matches)) {
            $resourceType = 'news';
            $resourceId = $matches[1];
            $action = 'news_viewed';
            $content = 'Viewed news article';
        } elseif (preg_match('/\/api\/matches\/(\d+)/', $uri, $matches)) {
            $resourceType = 'match';
            $resourceId = $matches[1];
            $action = 'match_viewed';
            $content = 'Viewed match details';
        } elseif (preg_match('/\/api\/events\/(\d+)/', $uri, $matches)) {
            $resourceType = 'event';
            $resourceId = $matches[1];
            $action = 'event_viewed';
            $content = 'Viewed event details';
        } elseif (preg_match('/\/api\/teams\/(\d+)/', $uri, $matches)) {
            $resourceType = 'team';
            $resourceId = $matches[1];
            $action = 'team_viewed';
            $content = 'Viewed team profile';
        } elseif (preg_match('/\/api\/players\/(\d+)/', $uri, $matches)) {
            $resourceType = 'player';
            $resourceId = $matches[1];
            $action = 'player_viewed';
            $content = 'Viewed player profile';
        } elseif (preg_match('/\/api\/forum\/threads\/(\d+)/', $uri, $matches)) {
            $resourceType = 'forum_thread';
            $resourceId = $matches[1];
            $action = 'thread_viewed';
            $content = 'Viewed forum thread';
        } elseif (str_contains($uri, '/api/rankings')) {
            $action = 'rankings_viewed';
            $content = 'Viewed rankings page';
        }

        return [
            'action' => $action,
            'content' => $content,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => [
                'uri' => $uri,
                'user_agent' => $request->header('User-Agent'),
                'referer' => $request->header('Referer')
            ]
        ];
    }

    /**
     * Handle event-related activities
     */
    private function handleEventActivity(Request $request, $uri, $method)
    {
        if ($method === 'POST' && str_contains($uri, '/register')) {
            preg_match('/events\/(\d+)\/register/', $uri, $matches);
            $eventId = $matches[1] ?? null;
            
            return [
                'action' => 'event_registered',
                'content' => 'Registered for event',
                'resource_type' => 'event',
                'resource_id' => $eventId,
                'metadata' => [
                    'event_id' => $eventId,
                    'team_id' => $request->input('team_id')
                ]
            ];
        }

        return null;
    }

    /**
     * Handle team-related activities
     */
    private function handleTeamActivity(Request $request, $uri, $method)
    {
        if ($method === 'POST' && str_contains($uri, '/follow')) {
            preg_match('/teams\/(\d+)\/follow/', $uri, $matches);
            $teamId = $matches[1] ?? null;
            
            return [
                'action' => 'team_followed',
                'content' => 'Followed team',
                'resource_type' => 'team',
                'resource_id' => $teamId,
                'metadata' => [
                    'team_id' => $teamId
                ]
            ];
        }

        if ($method === 'DELETE' && str_contains($uri, '/follow')) {
            preg_match('/teams\/(\d+)\/follow/', $uri, $matches);
            $teamId = $matches[1] ?? null;
            
            return [
                'action' => 'team_unfollowed',
                'content' => 'Unfollowed team',
                'resource_type' => 'team',
                'resource_id' => $teamId,
                'metadata' => [
                    'team_id' => $teamId
                ]
            ];
        }

        return null;
    }

    /**
     * Handle search activities
     */
    private function handleSearchActivity(Request $request, $uri, $method)
    {
        if ($method === 'GET') {
            $query = $request->input('q', '');
            $type = $request->input('type', 'all');
            
            return [
                'action' => 'search_performed',
                'content' => "Searched for: {$query}",
                'resource_type' => 'search',
                'resource_id' => null,
                'metadata' => [
                    'query' => $query,
                    'search_type' => $type,
                    'filters' => $request->except(['q', 'type'])
                ]
            ];
        }

        return null;
    }
}