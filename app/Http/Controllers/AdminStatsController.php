<?php
namespace App\Http\Controllers;

use App\Models\{Team, Player, Match, Event, User, ForumThread};

class AdminStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    public function index()
    {
        $stats = [
            'overview' => [
                'totalTeams' => Team::count(),
                'totalPlayers' => Player::count(),
                'totalMatches' => Match::count(),
                'liveMatches' => Match::where('status', 'live')->count(),
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
                'byStatus' => Match::selectRaw('status, COUNT(*) as count')
                                  ->groupBy('status')
                                  ->get(),
                'recent' => Match::with(['team1', 'team2', 'event'])
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
}
