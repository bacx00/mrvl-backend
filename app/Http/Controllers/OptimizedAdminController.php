<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\OptimizedAdminQueryService;
use App\Services\DatabaseOptimizationService;

class OptimizedAdminController extends Controller
{
    protected $adminQueryService;
    protected $dbOptimizationService;

    public function __construct()
    {
        $this->middleware(['auth:api', 'role:admin']);
        $this->adminQueryService = new OptimizedAdminQueryService();
        $this->dbOptimizationService = new DatabaseOptimizationService();
    }

    /**
     * Optimized admin dashboard overview
     */
    public function dashboard(Request $request)
    {
        try {
            $useCache = $request->get('no_cache') !== 'true';
            $stats = $this->adminQueryService->getOptimizedDashboardStats($useCache);

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('OptimizedAdminController::dashboard failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimized player management with advanced filtering and pagination
     */
    public function players(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|max:100',
                'role' => 'string|in:all,Vanguard,Duelist,Strategist',
                'team' => 'integer|exists:teams,id',
                'status' => 'string|in:all,active,inactive,retired',
                'region' => 'string|in:all,NA,EU,APAC,SA,OCE,KR,JP,CN',
                'min_rating' => 'integer|min:0|max:5000',
                'sort_by' => 'string|in:rating,username,team,created_at',
                'sort_order' => 'string|in:asc,desc',
                'no_cache' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors()
                ], 400);
            }

            $filters = [
                'search' => $request->get('search'),
                'role' => $request->get('role', 'all'),
                'team' => $request->get('team'),
                'status' => $request->get('status', 'all'),
                'region' => $request->get('region', 'all'),
                'min_rating' => $request->get('min_rating'),
                'sort_by' => $request->get('sort_by', 'rating'),
                'sort_order' => $request->get('sort_order', 'desc')
            ];

            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            $useCache = !$request->get('no_cache', false);

            $result = $this->adminQueryService->getOptimizedPlayerList(
                $filters, 
                $page, 
                $perPage, 
                $useCache
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('OptimizedAdminController::players failed', [
                'error' => $e->getMessage(),
                'request_params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching players: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimized team management with advanced filtering and pagination
     */
    public function teams(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|max:100',
                'region' => 'string|in:all,NA,EU,APAC,SA,OCE,KR,JP,CN',
                'platform' => 'string|in:all,PC,Console,Mobile',
                'country' => 'string|max:3',
                'status' => 'string|in:all,active,inactive,disbanded',
                'min_rating' => 'integer|min:0|max:5000',
                'has_players' => 'string|in:yes,no',
                'sort_by' => 'string|in:rating,name,region,player_count,created_at',
                'sort_order' => 'string|in:asc,desc',
                'no_cache' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors()
                ], 400);
            }

            $filters = [
                'search' => $request->get('search'),
                'region' => $request->get('region', 'all'),
                'platform' => $request->get('platform', 'all'),
                'country' => $request->get('country'),
                'status' => $request->get('status', 'all'),
                'min_rating' => $request->get('min_rating'),
                'has_players' => $request->get('has_players'),
                'sort_by' => $request->get('sort_by', 'rating'),
                'sort_order' => $request->get('sort_order', 'desc')
            ];

            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            $useCache = !$request->get('no_cache', false);

            $result = $this->adminQueryService->getOptimizedTeamList(
                $filters, 
                $page, 
                $perPage, 
                $useCache
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('OptimizedAdminController::teams failed', [
                'error' => $e->getMessage(),
                'request_params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimized live scoring interface
     */
    public function liveScoring(Request $request)
    {
        try {
            $useCache = !$request->get('no_cache', false);
            $result = $this->adminQueryService->getOptimizedLiveMatches($useCache);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('OptimizedAdminController::liveScoring failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching live matches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific live match for scoring interface
     */
    public function getLiveScoringMatch($matchId, Request $request)
    {
        try {
            // Optimized single match query with all related data
            $match = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    // Match data
                    'm.*',
                    
                    // Team 1 data
                    't1.name as team1_name', 't1.short_name as team1_short',
                    't1.logo as team1_logo', 't1.region as team1_region',
                    
                    // Team 2 data
                    't2.name as team2_name', 't2.short_name as team2_short',
                    't2.logo as team2_logo', 't2.region as team2_region',
                    
                    // Event data
                    'e.name as event_name', 'e.type as event_type', 'e.tier as event_tier'
                ])
                ->where('m.id', $matchId)
                ->first();

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Get team rosters in a single optimized query
            $rosters = DB::table('players as p')
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.role', 'p.main_hero', 
                    'p.avatar', 'p.team_id'
                ])
                ->whereIn('p.team_id', [$match->team1_id, $match->team2_id])
                ->where('p.status', 'active')
                ->orderBy('p.team_id')
                ->orderBy('p.role')
                ->get()
                ->groupBy('team_id');

            // Get available maps and heroes (cached)
            $cacheKey = 'admin_live_scoring_assets';
            $assets = Cache::remember($cacheKey, 1800, function() {
                return [
                    'maps' => DB::table('marvel_rivals_maps')
                        ->select(['name', 'game_mode', 'type'])
                        ->where('active', true)
                        ->orderBy('name')
                        ->get(),
                    'heroes' => DB::table('marvel_rivals_heroes')
                        ->select(['name', 'role'])
                        ->where('active', true)
                        ->orderBy('role')
                        ->orderBy('name')
                        ->get()
                ];
            });

            $result = [
                'match' => [
                    'id' => $match->id,
                    'status' => $match->status,
                    'format' => $match->format,
                    'current_map' => $match->current_map,
                    'team1_score' => $match->team1_score,
                    'team2_score' => $match->team2_score,
                    'scheduled_at' => $match->scheduled_at,
                    'maps_data' => $match->maps_data ? json_decode($match->maps_data, true) : [],
                    'live_data' => $match->live_data ? json_decode($match->live_data, true) : []
                ],
                'teams' => [
                    'team1' => [
                        'id' => $match->team1_id,
                        'name' => $match->team1_name,
                        'short_name' => $match->team1_short,
                        'logo' => $match->team1_logo,
                        'region' => $match->team1_region,
                        'roster' => $rosters->get($match->team1_id, collect())->values()
                    ],
                    'team2' => [
                        'id' => $match->team2_id,
                        'name' => $match->team2_name,
                        'short_name' => $match->team2_short,
                        'logo' => $match->team2_logo,
                        'region' => $match->team2_region,
                        'roster' => $rosters->get($match->team2_id, collect())->values()
                    ]
                ],
                'event' => [
                    'name' => $match->event_name,
                    'type' => $match->event_type,
                    'tier' => $match->event_tier
                ],
                'assets' => $assets,
                'game_modes' => [
                    'Convoy' => ['timer' => '10:00', 'points_to_win' => 3],
                    'Domination' => ['timer' => '8:00', 'points_to_win' => 100],
                    'Convergence' => ['timer' => '12:00', 'points_to_win' => 1]
                ],
                'success' => true
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('OptimizedAdminController::getLiveScoringMatch failed', [
                'match_id' => $matchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching match for live scoring: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimized bulk operations
     */
    public function bulkOperations(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|string|in:players,teams',
                'operation' => 'required|string|in:get_targets,update_status,update_ratings,delete',
                'filters' => 'array',
                'updates' => 'array',
                'ids' => 'array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors()
                ], 400);
            }

            $type = $request->get('type');
            $operation = $request->get('operation');

            switch ($operation) {
                case 'get_targets':
                    $filters = $request->get('filters', []);
                    $result = $this->adminQueryService->getBulkOperationTargets($type, $filters);
                    break;

                case 'update_status':
                    $ids = $request->get('ids', []);
                    $status = $request->get('updates.status');
                    
                    if (empty($ids) || !$status) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Missing IDs or status for bulk update'
                        ], 400);
                    }

                    $table = $type === 'players' ? 'players' : 'teams';
                    $updated = DB::table($table)->whereIn('id', $ids)->update(['status' => $status]);
                    
                    // Clear relevant cache
                    $this->adminQueryService->clearAdminCache();
                    
                    $result = [
                        'success' => true,
                        'message' => "Updated {$updated} {$type}",
                        'updated_count' => $updated
                    ];
                    break;

                case 'update_ratings':
                    $ids = $request->get('ids', []);
                    $rating = $request->get('updates.rating');
                    
                    if (empty($ids) || !is_numeric($rating)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Missing IDs or valid rating for bulk update'
                        ], 400);
                    }

                    $table = $type === 'players' ? 'players' : 'teams';
                    $updated = DB::table($table)->whereIn('id', $ids)->update([
                        'elo_rating' => $rating,
                        'updated_at' => now()
                    ]);
                    
                    // Clear relevant cache
                    $this->adminQueryService->clearAdminCache();
                    
                    $result = [
                        'success' => true,
                        'message' => "Updated rating for {$updated} {$type}",
                        'updated_count' => $updated
                    ];
                    break;

                case 'delete':
                    $ids = $request->get('ids', []);
                    
                    if (empty($ids)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No IDs provided for deletion'
                        ], 400);
                    }

                    DB::beginTransaction();
                    try {
                        if ($type === 'players') {
                            // Set team_id to null for players before deleting teams
                            DB::table('players')->whereIn('team_id', $ids)->update(['team_id' => null]);
                            $deleted = DB::table('players')->whereIn('id', $ids)->delete();
                        } else {
                            // Set team_id to null for players before deleting teams
                            DB::table('players')->whereIn('team_id', $ids)->update(['team_id' => null]);
                            $deleted = DB::table('teams')->whereIn('id', $ids)->delete();
                        }
                        
                        DB::commit();
                        
                        // Clear relevant cache
                        $this->adminQueryService->clearAdminCache();
                        
                        $result = [
                            'success' => true,
                            'message' => "Deleted {$deleted} {$type}",
                            'deleted_count' => $deleted
                        ];
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid operation'
                    ], 400);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('OptimizedAdminController::bulkOperations failed', [
                'error' => $e->getMessage(),
                'request_params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Advanced analytics with optimized queries
     */
    public function analytics(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'string|in:24hours,7days,30days,90days,1year',
                'metrics' => 'array',
                'no_cache' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors()
                ], 400);
            }

            $period = $request->get('period', '7days');
            $requestedMetrics = $request->get('metrics', []);
            $useCache = !$request->get('no_cache', false);

            // Use the existing analytics from DatabaseOptimizationService but with caching
            $cacheKey = 'admin_analytics_' . $period . '_' . md5(serialize($requestedMetrics));
            
            if ($useCache && Cache::has($cacheKey)) {
                return response()->json(Cache::get($cacheKey));
            }

            // Get analytics using the optimized service
            $analytics = $this->dbOptimizationService->getMatchStatistics(null, null, $period, 'all');
            
            $result = [
                'period' => $period,
                'date_range' => [
                    'start' => $this->getStartDateForPeriod($period)->toISOString(),
                    'end' => now()->toISOString()
                ],
                'analytics' => $analytics,
                'success' => true,
                'cache_hit' => false
            ];

            if ($useCache) {
                Cache::put($cacheKey, $result, self::CACHE_TTL['analytics'] ?? 600);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('OptimizedAdminController::analytics failed', [
                'error' => $e->getMessage(),
                'request_params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Database performance monitoring
     */
    public function performanceMetrics(Request $request)
    {
        try {
            $startTime = microtime(true);

            // Test query performance on key tables
            $metrics = [
                'players' => [
                    'total_records' => DB::table('players')->count(),
                    'active_records' => DB::table('players')->where('status', 'active')->count(),
                    'query_time' => 0
                ],
                'teams' => [
                    'total_records' => DB::table('teams')->count(),
                    'active_records' => DB::table('teams')->where('status', 'active')->count(),
                    'query_time' => 0
                ],
                'matches' => [
                    'total_records' => DB::table('matches')->count(),
                    'live_records' => DB::table('matches')->where('status', 'live')->count(),
                    'query_time' => 0
                ]
            ];

            // Test complex query performance
            $complexQueryStart = microtime(true);
            $complexResult = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->select([
                    'p.id', 'p.username', 'p.role', 'p.rating',
                    't.name as team_name', 't.region'
                ])
                ->where('p.status', 'active')
                ->orderBy('p.rating', 'desc')
                ->limit(100)
                ->get();
            
            $complexQueryTime = (microtime(true) - $complexQueryStart) * 1000;

            // Index effectiveness check
            $indexStats = [];
            try {
                $tableStats = DB::select("
                    SELECT 
                        table_name,
                        index_name,
                        non_unique,
                        column_name
                    FROM information_schema.statistics 
                    WHERE table_schema = DATABASE() 
                    AND table_name IN ('players', 'teams', 'matches')
                    ORDER BY table_name, index_name
                ");
                
                $indexStats = collect($tableStats)->groupBy('table_name');
            } catch (\Exception $e) {
                // MySQL-specific query failed, skip index stats
            }

            $totalTime = (microtime(true) - $startTime) * 1000;

            $result = [
                'query_performance' => [
                    'total_execution_time' => round($totalTime, 2) . 'ms',
                    'complex_query_time' => round($complexQueryTime, 2) . 'ms',
                    'complex_query_results' => $complexResult->count()
                ],
                'table_metrics' => $metrics,
                'index_statistics' => $indexStats,
                'recommendations' => $this->generatePerformanceRecommendations($metrics, $complexQueryTime),
                'cache_status' => [
                    'enabled' => config('cache.default') !== 'array',
                    'driver' => config('cache.default'),
                    'admin_cache_keys' => $this->getAdminCacheKeys()
                ],
                'success' => true
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('OptimizedAdminController::performanceMetrics failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching performance metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all admin cache
     */
    public function clearCache(Request $request)
    {
        try {
            $specific = $request->get('specific');
            $this->adminQueryService->clearAdminCache($specific);

            return response()->json([
                'success' => true,
                'message' => 'Admin cache cleared successfully',
                'cleared' => $specific ?? 'all'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run database optimization
     */
    public function optimizeDatabase(Request $request)
    {
        try {
            $result = $this->dbOptimizationService->optimizeDatabase();
            
            // Clear admin cache after optimization
            $this->adminQueryService->clearAdminCache();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database optimization failed: ' . $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    private function getStartDateForPeriod($period)
    {
        switch ($period) {
            case '24hours':
                return now()->subDay();
            case '7days':
                return now()->subWeek();
            case '30days':
                return now()->subMonth();
            case '90days':
                return now()->subMonths(3);
            case '1year':
                return now()->subYear();
            default:
                return now()->subWeek();
        }
    }

    private function generatePerformanceRecommendations($metrics, $complexQueryTime)
    {
        $recommendations = [];

        if ($complexQueryTime > 100) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Complex query performance is slow (' . round($complexQueryTime, 2) . 'ms). Consider reviewing indexes.'
            ];
        }

        if ($metrics['players']['total_records'] > 10000 && $complexQueryTime > 50) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Large dataset detected. Ensure pagination is used in admin interfaces.'
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'Database performance is optimal.'
            ];
        }

        return $recommendations;
    }

    private function getAdminCacheKeys()
    {
        // Return list of cache keys that might exist for admin operations
        return [
            'admin_dashboard_stats',
            'admin_live_matches',
            'admin_players_*',
            'admin_teams_*'
        ];
    }
}