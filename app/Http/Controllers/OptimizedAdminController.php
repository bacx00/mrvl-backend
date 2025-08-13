<?php
namespace App\Http\Controllers;

use App\Services\OptimizedQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OptimizedAdminController extends Controller
{
    protected OptimizedQueryService $queryService;

    public function __construct(OptimizedQueryService $queryService)
    {
        $this->middleware(['auth:api', 'role:admin']);
        $this->queryService = $queryService;
    }

    /**
     * Optimized admin dashboard with single efficient queries
     */
    public function dashboard()
    {
        try {
            $stats = $this->queryService->getAdminDashboardStats();
            
            // Format the response
            $formattedStats = [
                'users' => [
                    'total' => $stats['users']->total ?? 0,
                    'active_today' => $stats['users']->active_today ?? 0,
                    'new_this_week' => $stats['users']->new_this_week ?? 0
                ],
                'teams' => [
                    'total' => $stats['teams']->total ?? 0,
                    'active' => $stats['teams']->active ?? 0,
                    'by_region' => $this->formatDistribution($stats['teams_by_region'] ?? [])
                ],
                'players' => [
                    'total' => $stats['players']->total ?? 0,
                    'active' => $stats['players']->active ?? 0,
                    'by_role' => $this->formatDistribution($stats['players_by_role'] ?? [])
                ],
                'matches' => [
                    'total' => $stats['matches']->total ?? 0,
                    'live' => $stats['matches']->live ?? 0,
                    'upcoming' => $stats['matches']->upcoming ?? 0,
                    'completed' => $stats['matches']->completed ?? 0,
                    'today' => $stats['matches']->today ?? 0
                ]
            ];

            return response()->json([
                'data' => $formattedStats,
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching admin dashboard stats', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard stats'
            ], 500);
        }
    }

    /**
     * Optimized live scoring management
     */
    public function liveScoring()
    {
        try {
            $liveMatches = $this->queryService->getLiveMatches();

            $formattedMatches = $liveMatches->map(function($match) {
                return [
                    'id' => $match->id,
                    'team1' => [
                        'id' => $match->team1->id,
                        'name' => $match->team1->name,
                        'short_name' => $match->team1->short_name,
                        'logo' => $match->team1->logo,
                        'score' => $match->team1_score
                    ],
                    'team2' => [
                        'id' => $match->team2->id,
                        'name' => $match->team2->name,
                        'short_name' => $match->team2->short_name,
                        'logo' => $match->team2->logo,
                        'score' => $match->team2_score
                    ],
                    'event' => $match->event ? $match->event->name : null,
                    'status' => $match->status,
                    'format' => $match->format,
                    'current_map' => $match->current_map,
                    'scheduled_at' => $match->scheduled_at,
                    'viewers' => $match->viewers ?? 0
                ];
            });

            return response()->json([
                'data' => $formattedMatches,
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching live scoring data', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching live scoring data'
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    private function formatDistribution($data)
    {
        if (is_array($data)) {
            return collect($data)->map(function($item) {
                return [
                    'name' => $item->name ?? $item->region ?? $item->role ?? 'Unknown',
                    'count' => $item->count
                ];
            })->toArray();
        }
        return [];
    }
}