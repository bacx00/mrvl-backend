<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\{Team, Player, Event, MatchModel, News};
use Carbon\Carbon;

class TestDataController extends Controller
{
    /**
     * Get test data for various endpoints
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Test data controller is working',
            'endpoints' => [
                'teams' => '/api/test-data/teams',
                'players' => '/api/test-data/players', 
                'events' => '/api/test-data/events',
                'matches' => '/api/test-data/matches',
                'news' => '/api/test-data/news'
            ]
        ]);
    }

    /**
     * Get sample team data
     */
    public function teams(): JsonResponse
    {
        try {
            $teams = Team::with(['players'])
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $teams,
                'count' => $teams->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sample player data
     */
    public function players(): JsonResponse
    {
        try {
            $players = Player::with(['currentTeam'])
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $players,
                'count' => $players->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching players: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sample event data
     */
    public function events(): JsonResponse
    {
        try {
            $events = Event::with(['teams'])
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => $events->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching events: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sample match data
     */
    public function matches(): JsonResponse
    {
        try {
            $matches = MatchModel::with(['team1', 'team2', 'event'])
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $matches,
                'count' => $matches->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching matches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sample news data
     */
    public function news(): JsonResponse
    {
        try {
            $news = News::with(['author'])
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $news,
                'count' => $news->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching news: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check database connectivity and table status
     */
    public function status(): JsonResponse
    {
        try {
            $status = [
                'database_connected' => true,
                'tables' => []
            ];

            // Check key tables
            $tables = ['teams', 'players', 'events', 'matches', 'news', 'users'];
            
            foreach ($tables as $table) {
                try {
                    $count = \DB::table($table)->count();
                    $status['tables'][$table] = [
                        'exists' => true,
                        'count' => $count
                    ];
                } catch (\Exception $e) {
                    $status['tables'][$table] = [
                        'exists' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ], 500);
        }
    }
}