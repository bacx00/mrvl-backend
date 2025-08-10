<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AchievementService;
use Symfony\Component\HttpFoundation\Response;

class TrackAchievementActivity
{
    protected AchievementService $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        $this->achievementService = $achievementService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track for authenticated users with successful responses
        if (!Auth::check() || !$response->isSuccessful()) {
            return $response;
        }

        $this->trackActivityBasedOnRoute($request);

        return $response;
    }

    private function trackActivityBasedOnRoute(Request $request): void
    {
        $userId = Auth::id();
        $route = $request->route();
        
        if (!$route) {
            return;
        }

        $routeName = $route->getName();
        $method = $request->method();
        
        // Track different activities based on routes and methods
        $activityType = $this->determineActivityType($routeName, $method, $request);
        
        if ($activityType) {
            $metadata = $this->extractMetadata($request, $activityType);
            
            // Track activity asynchronously to avoid blocking the response
            dispatch(function () use ($userId, $activityType, $metadata) {
                $this->achievementService->trackUserActivity($userId, $activityType, $metadata);
            })->afterResponse();
        }
    }

    private function determineActivityType(?string $routeName, string $method, Request $request): ?string
    {
        // Map routes to activity types
        $routeActivityMap = [
            // Authentication
            'login' => 'login',
            
            // Comments
            'news.comments.store' => 'comment_posted',
            'matches.comments.store' => 'comment_posted',
            
            // Forum activities
            'forum.threads.store' => 'thread_created',
            'forum.posts.store' => 'post_created',
            
            // Voting
            'votes.store' => 'vote_cast',
            'forum.votes.store' => 'vote_cast',
            
            // Profile updates
            'profile.update' => 'profile_updated',
            'user.flairs.update' => 'profile_updated',
            
            // Match activities
            'matches.show' => 'match_view',
            'matches.predictions.store' => 'match_prediction',
            
            // News activities
            'news.show' => 'news_view',
        ];

        // Check for exact route name match
        if (isset($routeActivityMap[$routeName])) {
            return $routeActivityMap[$routeName];
        }

        // Fallback: Check route patterns
        if ($method === 'POST') {
            if (str_contains($request->path(), 'comments')) {
                return 'comment_posted';
            }
            if (str_contains($request->path(), 'threads')) {
                return 'thread_created';
            }
            if (str_contains($request->path(), 'posts')) {
                return 'post_created';
            }
            if (str_contains($request->path(), 'votes')) {
                return 'vote_cast';
            }
        }

        if ($method === 'GET') {
            if (str_contains($request->path(), 'matches/') && !str_contains($request->path(), 'api')) {
                return 'match_view';
            }
            if (str_contains($request->path(), 'news/') && !str_contains($request->path(), 'api')) {
                return 'news_view';
            }
        }

        return null;
    }

    private function extractMetadata(Request $request, string $activityType): array
    {
        $metadata = [];

        // Add common metadata
        $metadata['user_agent'] = $request->userAgent();
        $metadata['ip_address'] = $request->ip();
        $metadata['timestamp'] = now()->toISOString();

        // Add activity-specific metadata
        switch ($activityType) {
            case 'comment_posted':
                $metadata['content_length'] = strlen($request->input('content', ''));
                if ($request->has('match_id')) {
                    $metadata['match_id'] = $request->input('match_id');
                }
                if ($request->has('news_id')) {
                    $metadata['news_id'] = $request->input('news_id');
                }
                break;

            case 'thread_created':
            case 'post_created':
                $metadata['title_length'] = strlen($request->input('title', ''));
                $metadata['content_length'] = strlen($request->input('content', ''));
                if ($request->has('category_id')) {
                    $metadata['category_id'] = $request->input('category_id');
                }
                break;

            case 'vote_cast':
                $metadata['vote_type'] = $request->input('vote', 'unknown');
                if ($request->has('votable_type')) {
                    $metadata['votable_type'] = $request->input('votable_type');
                }
                break;

            case 'match_view':
                $metadata['match_id'] = $request->route('match');
                break;

            case 'news_view':
                $metadata['news_id'] = $request->route('news') ?? $request->route('id');
                break;

            case 'match_prediction':
                $metadata['match_id'] = $request->input('match_id');
                $metadata['prediction_type'] = $request->input('type', 'unknown');
                break;
        }

        return $metadata;
    }
}