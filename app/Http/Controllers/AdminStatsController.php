<?php
namespace App\Http\Controllers;

use App\Models\{Team, Player, GameMatch, Event, User, ForumThread};
use Illuminate\Http\Request;

class AdminStatsController extends Controller
{
    public function index()
    {
        $stats = [
            'overview' => [
                'totalTeams' => Team::count(),
                'totalPlayers' => Player::count(),
                'totalMatches' => GameMatch::count(),
                'liveMatches' => GameMatch::where('status', 'live')->count(),
                'totalEvents' => Event::count(),
                'activeEvents' => Event::where('status', 'live')->count(),
                'totalUsers' => User::count(),
                'totalThreads' => ForumThread::count(),
            ],
            'teams' => [
                'byRegion' => Team::selectRaw('region, COUNT(*) as count')
                                 ->groupBy('region')
                                 ->get(),
                'topRated' => Team::orderBy('rating', 'desc')->limit(10)->get(),
            ],
            'players' => [
                'byRole' => Player::selectRaw('role, COUNT(*) as count')
                                 ->groupBy('role')
                                 ->get(),
                'topRated' => Player::with('team')->orderBy('rating', 'desc')->limit(10)->get(),
            ],
            'matches' => [
                'byStatus' => GameMatch::selectRaw('status, COUNT(*) as count')
                                  ->groupBy('status')
                                  ->get(),
                'recent' => GameMatch::with(['team1', 'team2', 'event'])
                                ->orderBy('scheduled_at', 'desc')
                                ->limit(10)
                                ->get(),
            ],
            'events' => [
                'byType' => Event::selectRaw('type, COUNT(*) as count')
                                ->groupBy('type')
                                ->get(),
                'upcoming' => Event::where('status', 'upcoming')
                                  ->orderBy('start_date', 'asc')
                                  ->limit(5)
                                  ->get(),
            ]
        ];

        return response()->json([
            'data' => $stats,
            'success' => true
        ]);
    }

    public function analytics(Request $request)
    {
        $period = $request->get('period', '30d');
        
        // Calculate date range based on period
        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30
        };
        
        $startDate = now()->subDays($days);
        
        $analytics = [
            'period' => $period,
            'user_activity' => [
                'new_users' => User::where('created_at', '>=', $startDate)->count(),
                'active_users' => User::where('last_login', '>=', $startDate)->count(),
                'total_users' => User::count(),
            ],
            'content_activity' => [
                'new_threads' => ForumThread::where('created_at', '>=', $startDate)->count(),
                'new_matches' => GameMatch::where('created_at', '>=', $startDate)->count(),
                'new_events' => Event::where('created_at', '>=', $startDate)->count(),
            ],
            'engagement' => [
                'matches_today' => GameMatch::whereDate('created_at', today())->count(),
                'live_matches' => GameMatch::where('status', 'live')->count(),
                'upcoming_events' => Event::where('status', 'upcoming')->count(),
            ]
        ];

        return response()->json([
            'data' => $analytics,
            'success' => true
        ]);
    }
}
