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

        // Skip GET requests for most read operations
        if ($method === 'GET' && !str_contains($uri, '/activity')) {
            return true;
        }

        return false;
    }

    /**
     * Determine activity data based on request
     */
    private function determineActivityData(Request $request, $response, $uri, $method)
    {
        // Forum activities
        if (str_contains($uri, '/api/user/forums')) {
            return $this->handleForumActivity($request, $uri, $method);
        }

        // News activities
        if (str_contains($uri, '/api/user/news') || str_contains($uri, '/api/news')) {
            return $this->handleNewsActivity($request, $uri, $method);
        }

        // Match activities
        if (str_contains($uri, '/api/matches') && str_contains($uri, '/comments')) {
            return $this->handleMatchActivity($request, $uri, $method);
        }

        // Voting activities
        if (str_contains($uri, '/api/user/votes') && $method === 'POST') {
            return $this->handleVotingActivity($request, $response);
        }

        // Profile updates
        if (str_contains($uri, '/api/user/profile') && $method !== 'GET') {
            return $this->handleProfileActivity($request, $uri, $method);
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
}