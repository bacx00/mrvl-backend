<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class ApiTestController extends Controller
{
    public function testEndpoints()
    {
        $results = [];
        $testUser = User::where('email', 'jhonny@ar-mediia.com')->first();
        
        if (!$testUser) {
            return response()->json([
                'error' => 'Test user not found',
                'message' => 'Please ensure jhonny@ar-mediia.com exists in the database'
            ], 404);
        }
        
        // Get auth token
        $token = $testUser->createToken('test-api')->accessToken;
        
        // Define test endpoints
        $endpoints = [
            // Public endpoints (no auth)
            ['method' => 'GET', 'url' => '/api/public/heroes', 'auth' => false, 'name' => 'Get Heroes'],
            ['method' => 'GET', 'url' => '/api/public/maps', 'auth' => false, 'name' => 'Get Maps'],
            ['method' => 'GET', 'url' => '/api/public/teams', 'auth' => false, 'name' => 'Get Teams'],
            ['method' => 'GET', 'url' => '/api/public/players', 'auth' => false, 'name' => 'Get Players'],
            ['method' => 'GET', 'url' => '/api/public/matches/upcoming', 'auth' => false, 'name' => 'Get Upcoming Matches'],
            ['method' => 'GET', 'url' => '/api/public/events', 'auth' => false, 'name' => 'Get Events'],
            ['method' => 'GET', 'url' => '/api/public/news/latest', 'auth' => false, 'name' => 'Get Latest News'],
            ['method' => 'GET', 'url' => '/api/public/rankings/teams', 'auth' => false, 'name' => 'Get Team Rankings'],
            
            // Search endpoints (public)
            ['method' => 'GET', 'url' => '/api/search?q=test', 'auth' => false, 'name' => 'Search All'],
            ['method' => 'GET', 'url' => '/api/search/teams?q=test', 'auth' => false, 'name' => 'Search Teams'],
            ['method' => 'GET', 'url' => '/api/search/players?q=test', 'auth' => false, 'name' => 'Search Players'],
            ['method' => 'GET', 'url' => '/api/search/heroes?q=spider', 'auth' => false, 'name' => 'Search Heroes'],
            
            // Auth endpoints
            ['method' => 'GET', 'url' => '/api/auth/me', 'auth' => true, 'name' => 'Get Current User'],
            ['method' => 'GET', 'url' => '/api/user/favorites', 'auth' => true, 'name' => 'Get User Favorites'],
            ['method' => 'GET', 'url' => '/api/user/stats', 'auth' => true, 'name' => 'Get User Stats'],
            
            // Profile endpoints
            ['method' => 'GET', 'url' => '/api/profile/1', 'auth' => false, 'name' => 'Get User Profile'],
            
            // Analytics endpoints
            ['method' => 'GET', 'url' => '/api/analytics/user', 'auth' => true, 'name' => 'Get User Analytics'],
            ['method' => 'GET', 'url' => '/api/analytics/heroes', 'auth' => true, 'name' => 'Get Hero Analytics'],
            
            // Forums endpoints
            ['method' => 'GET', 'url' => '/api/forums/categories', 'auth' => false, 'name' => 'Get Forum Categories'],
            ['method' => 'GET', 'url' => '/api/forums/threads', 'auth' => false, 'name' => 'Get Forum Threads'],
            
            // Live scoring
            ['method' => 'GET', 'url' => '/api/matches/live', 'auth' => false, 'name' => 'Get Live Matches'],
            
            // Mentions
            ['method' => 'GET', 'url' => '/api/mentions/search?q=test', 'auth' => true, 'name' => 'Search Mentions']
        ];
        
        $baseUrl = config('app.url');
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($endpoints as $endpoint) {
            $url = $baseUrl . $endpoint['url'];
            $headers = [];
            
            if ($endpoint['auth']) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
            
            try {
                $response = Http::withHeaders($headers)->timeout(5)->get($url);
                
                $results[] = [
                    'name' => $endpoint['name'],
                    'method' => $endpoint['method'],
                    'url' => $endpoint['url'],
                    'auth_required' => $endpoint['auth'],
                    'status' => $response->status(),
                    'success' => $response->successful(),
                    'response_time' => $response->handlerStats()['total_time'] ?? null,
                    'error' => $response->failed() ? $response->json()['message'] ?? 'Unknown error' : null
                ];
                
                if ($response->successful()) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $results[] = [
                    'name' => $endpoint['name'],
                    'method' => $endpoint['method'],
                    'url' => $endpoint['url'],
                    'auth_required' => $endpoint['auth'],
                    'status' => 0,
                    'success' => false,
                    'response_time' => null,
                    'error' => $e->getMessage()
                ];
                $failureCount++;
            }
        }
        
        // Database stats
        $dbStats = [
            'users' => DB::table('users')->count(),
            'teams' => DB::table('teams')->count(),
            'players' => DB::table('players')->count(),
            'matches' => DB::table('matches')->count(),
            'events' => DB::table('events')->count(),
            'news' => DB::table('news')->count(),
            'forum_threads' => DB::table('forum_threads')->count(),
            'heroes' => DB::table('marvel_rivals_heroes')->count(),
            'maps' => DB::table('marvel_rivals_maps')->count()
        ];
        
        return response()->json([
            'summary' => [
                'total_tests' => count($endpoints),
                'successful' => $successCount,
                'failed' => $failureCount,
                'success_rate' => count($endpoints) > 0 ? round(($successCount / count($endpoints)) * 100, 2) . '%' : '0%'
            ],
            'database_stats' => $dbStats,
            'test_results' => $results,
            'timestamp' => now()->toIso8601String()
        ]);
    }
}