<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class OptimizedAdminQueryService
{
    // Cache configuration
    const CACHE_TTL = [
        'dashboard_stats' => 300,    // 5 minutes
        'player_list' => 120,        // 2 minutes  
        'team_list' => 120,          // 2 minutes
        'live_matches' => 60,        // 1 minute
        'analytics' => 600,          // 10 minutes
    ];

    /**
     * Get optimized player list for admin dashboard
     * Eliminates N+1 queries and uses proper indexing
     */
    public function getOptimizedPlayerList($filters = [], $page = 1, $perPage = 20, $useCache = true)
    {
        $cacheKey = 'admin_players_' . md5(serialize($filters) . "_{$page}_{$perPage}");
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Single optimized query with proper joins and indexes
            $query = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->select([
                    // Player fields
                    'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role', 'p.main_hero',
                    'p.rating', 'p.elo_rating', 'p.peak_elo', 'p.country', 'p.age', 'p.status',
                    'p.total_matches', 'p.total_wins', 'p.total_eliminations', 'p.total_deaths',
                    'p.total_assists', 'p.overall_kda', 'p.earnings_amount', 'p.earnings_currency',
                    'p.created_at', 'p.updated_at',
                    
                    // Team fields (LEFT JOIN to avoid N+1)
                    't.name as team_name', 't.short_name as team_short_name', 't.logo as team_logo',
                    't.region as team_region', 't.rating as team_rating',
                    
                    // Calculate rank using window function
                    DB::raw('ROW_NUMBER() OVER (ORDER BY COALESCE(p.elo_rating, p.rating, 0) DESC) as calculated_rank')
                ]);

            // Apply filters using optimized indexes
            if (!empty($filters['search'])) {
                // Uses idx_players_search index
                $query->where(function($q) use ($filters) {
                    $search = '%' . $filters['search'] . '%';
                    $q->where('p.username', 'LIKE', $search)
                      ->orWhere('p.real_name', 'LIKE', $search);
                });
            }

            if (!empty($filters['role']) && $filters['role'] !== 'all') {
                // Uses idx_players_rating_role index
                $query->where('p.role', $filters['role']);
            }

            if (!empty($filters['team']) && $filters['team'] !== 'all') {
                // Uses idx_players_team_active index
                $query->where('p.team_id', $filters['team']);
            }

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                // Uses idx_players_admin_listing index
                $query->where('p.status', $filters['status']);
            }

            if (!empty($filters['region']) && $filters['region'] !== 'all') {
                // Uses idx_players_region_rating index
                $query->where('p.region', $filters['region']);
            }

            if (!empty($filters['min_rating'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('p.elo_rating', '>=', $filters['min_rating'])
                      ->orWhere(function($q2) use ($filters) {
                          $q2->whereNull('p.elo_rating')
                             ->where('p.rating', '>=', $filters['min_rating']);
                      });
                });
            }

            // Optimized sorting using proper indexes
            $sortBy = $filters['sort_by'] ?? 'rating';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            
            switch ($sortBy) {
                case 'rating':
                    $query->orderBy(DB::raw('COALESCE(p.elo_rating, p.rating, 0)'), $sortOrder);
                    break;
                case 'username':
                    $query->orderBy('p.username', $sortOrder);
                    break;
                case 'team':
                    $query->orderBy('t.name', $sortOrder);
                    break;
                case 'created_at':
                    // Uses idx_players_admin_pagination index
                    $query->orderBy('p.created_at', $sortOrder)
                          ->orderBy('p.id', $sortOrder);
                    break;
                default:
                    $query->orderBy(DB::raw('COALESCE(p.elo_rating, p.rating, 0)'), 'desc');
            }

            // Get total count for pagination (cached separately)
            $totalQuery = clone $query;
            $total = $totalQuery->count();

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $players = $query->offset($offset)->limit($perPage)->get();

            // Format results
            $formattedPlayers = $players->map(function($player) {
                return $this->formatPlayerForAdmin($player);
            });

            $result = [
                'data' => $formattedPlayers,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ],
                'success' => true,
                'query_time' => microtime(true) - LARAVEL_START,
                'cache_hit' => false
            ];

            if ($useCache) {
                Cache::put($cacheKey, $result, self::CACHE_TTL['player_list']);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('OptimizedAdminQueryService::getOptimizedPlayerList failed', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 0,
                    'from' => 0,
                    'to' => 0,
                ],
                'success' => false,
                'error' => 'Failed to fetch players: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get optimized team list for admin dashboard
     */
    public function getOptimizedTeamList($filters = [], $page = 1, $perPage = 20, $useCache = true)
    {
        $cacheKey = 'admin_teams_' . md5(serialize($filters) . "_{$page}_{$perPage}");
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Optimized query with player count subquery
            $query = DB::table('teams as t')
                ->leftJoin(
                    DB::raw('(SELECT team_id, COUNT(*) as active_player_count FROM players WHERE status = "active" GROUP BY team_id) as player_counts'),
                    't.id', '=', 'player_counts.team_id'
                )
                ->select([
                    // Team fields
                    't.id', 't.name', 't.short_name', 't.logo', 't.region', 't.platform',
                    't.country', 't.rating', 't.elo_rating', 't.peak_elo', 't.rank',
                    't.win_rate', 't.map_win_rate', 't.wins', 't.losses', 't.matches_played',
                    't.maps_won', 't.maps_lost', 't.earnings_amount', 't.earnings_currency',
                    't.status', 't.created_at', 't.updated_at',
                    
                    // Player count
                    DB::raw('COALESCE(player_counts.active_player_count, 0) as player_count'),
                    
                    // Calculate rank
                    DB::raw('ROW_NUMBER() OVER (ORDER BY COALESCE(t.elo_rating, t.rating, 0) DESC) as calculated_rank')
                ]);

            // Apply filters using optimized indexes
            if (!empty($filters['search'])) {
                // Uses idx_teams_search index
                $query->where(function($q) use ($filters) {
                    $search = '%' . $filters['search'] . '%';
                    $q->where('t.name', 'LIKE', $search)
                      ->orWhere('t.short_name', 'LIKE', $search);
                });
            }

            if (!empty($filters['region']) && $filters['region'] !== 'all') {
                // Uses idx_teams_region_platform_rating index
                $query->where('t.region', $filters['region']);
            }

            if (!empty($filters['platform']) && $filters['platform'] !== 'all') {
                // Uses idx_teams_region_platform_rating index
                $query->where('t.platform', $filters['platform']);
            }

            if (!empty($filters['country']) && $filters['country'] !== 'all') {
                // Uses idx_teams_country_rating index
                $query->where('t.country', $filters['country']);
            }

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                // Uses idx_teams_admin_listing index
                $query->where('t.status', $filters['status']);
            }

            if (!empty($filters['min_rating'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('t.elo_rating', '>=', $filters['min_rating'])
                      ->orWhere(function($q2) use ($filters) {
                          $q2->whereNull('t.elo_rating')
                             ->where('t.rating', '>=', $filters['min_rating']);
                      });
                });
            }

            if (!empty($filters['has_players'])) {
                if ($filters['has_players'] === 'yes') {
                    $query->having('player_count', '>', 0);
                } else {
                    $query->having('player_count', '=', 0);
                }
            }

            // Optimized sorting
            $sortBy = $filters['sort_by'] ?? 'rating';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            
            switch ($sortBy) {
                case 'rating':
                    $query->orderBy(DB::raw('COALESCE(t.elo_rating, t.rating, 0)'), $sortOrder);
                    break;
                case 'name':
                    $query->orderBy('t.name', $sortOrder);
                    break;
                case 'region':
                    $query->orderBy('t.region', $sortOrder);
                    break;
                case 'player_count':
                    $query->orderBy('player_count', $sortOrder);
                    break;
                case 'created_at':
                    // Uses idx_teams_admin_pagination index
                    $query->orderBy('t.created_at', $sortOrder)
                          ->orderBy('t.id', $sortOrder);
                    break;
                default:
                    $query->orderBy(DB::raw('COALESCE(t.elo_rating, t.rating, 0)'), 'desc');
            }

            // Get total count
            $totalQuery = clone $query;
            $total = $totalQuery->count();

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $teams = $query->offset($offset)->limit($perPage)->get();

            // Format results
            $formattedTeams = $teams->map(function($team) {
                return $this->formatTeamForAdmin($team);
            });

            $result = [
                'data' => $formattedTeams,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ],
                'success' => true,
                'query_time' => microtime(true) - LARAVEL_START,
                'cache_hit' => false
            ];

            if ($useCache) {
                Cache::put($cacheKey, $result, self::CACHE_TTL['team_list']);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('OptimizedAdminQueryService::getOptimizedTeamList failed', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 0,
                    'from' => 0,
                    'to' => 0,
                ],
                'success' => false,
                'error' => 'Failed to fetch teams: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get optimized live matches for admin dashboard
     */
    public function getOptimizedLiveMatches($useCache = true)
    {
        $cacheKey = 'admin_live_matches';
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Single optimized query using proper indexes
            $matches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    // Match fields
                    'm.id', 'm.status', 'm.format', 'm.current_map',
                    'm.team1_score', 'm.team2_score', 'm.scheduled_at',
                    'm.maps_data', 'm.live_data', 'm.stream_url',
                    
                    // Team 1 fields
                    'm.team1_id', 't1.name as team1_name', 't1.short_name as team1_short',
                    't1.logo as team1_logo', 't1.region as team1_region',
                    
                    // Team 2 fields
                    'm.team2_id', 't2.name as team2_name', 't2.short_name as team2_short',
                    't2.logo as team2_logo', 't2.region as team2_region',
                    
                    // Event fields
                    'e.name as event_name', 'e.type as event_type', 'e.tier as event_tier'
                ])
                ->whereIn('m.status', ['live', 'upcoming', 'paused'])
                ->orderByRaw("
                    CASE m.status 
                        WHEN 'live' THEN 1 
                        WHEN 'paused' THEN 2 
                        WHEN 'upcoming' THEN 3 
                        ELSE 4 
                    END
                ")
                ->orderBy('m.scheduled_at', 'asc')
                ->limit(50)
                ->get();

            // Format for admin dashboard
            $formattedMatches = $matches->map(function($match) {
                return $this->formatMatchForAdmin($match);
            });

            $result = [
                'data' => $formattedMatches,
                'total' => $formattedMatches->count(),
                'success' => true,
                'cache_hit' => false
            ];

            if ($useCache) {
                Cache::put($cacheKey, $result, self::CACHE_TTL['live_matches']);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('OptimizedAdminQueryService::getOptimizedLiveMatches failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'data' => [],
                'total' => 0,
                'success' => false,
                'error' => 'Failed to fetch live matches: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get optimized dashboard statistics using the materialized view
     */
    public function getOptimizedDashboardStats($useCache = true)
    {
        $cacheKey = 'admin_dashboard_stats';
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Use the materialized view created in the migration
            $stats = DB::table('admin_dashboard_stats')->get()->keyBy('metric_type');

            // Get additional real-time statistics
            $liveMatches = DB::table('matches')->where('status', 'live')->count();
            $upcomingMatches = DB::table('matches')->where('status', 'upcoming')->count();
            $completedToday = DB::table('matches')
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count();

            // Get user role distribution
            $roleDistribution = DB::table('model_has_roles as mr')
                ->leftJoin('roles as r', 'mr.role_id', '=', 'r.id')
                ->select('r.name', DB::raw('COUNT(mr.model_id) as count'))
                ->groupBy('r.name')
                ->get()
                ->keyBy('name');

            // Get recent activity
            $recentUsers = DB::table('users')
                ->whereNotNull('last_login')
                ->orderBy('last_login', 'desc')
                ->limit(10)
                ->select(['id', 'name', 'avatar', 'last_login'])
                ->get();

            $result = [
                'overview' => [
                    'users' => [
                        'total' => $stats->get('users')->total_count ?? 0,
                        'active_today' => $stats->get('users')->active_today ?? 0,
                        'new_this_week' => $stats->get('users')->new_this_week ?? 0,
                        'by_role' => $roleDistribution
                    ],
                    'teams' => [
                        'total' => $stats->get('teams')->total_count ?? 0,
                        'active' => $stats->get('teams')->active_today ?? 0,
                        'new_this_week' => $stats->get('teams')->new_this_week ?? 0
                    ],
                    'players' => [
                        'total' => $stats->get('players')->total_count ?? 0,
                        'active' => $stats->get('players')->active_today ?? 0,
                        'new_this_week' => $stats->get('players')->new_this_week ?? 0
                    ],
                    'matches' => [
                        'total' => $stats->get('matches')->total_count ?? 0,
                        'live' => $liveMatches,
                        'upcoming' => $upcomingMatches,
                        'completed_today' => $completedToday
                    ]
                ],
                'recent_activity' => [
                    'recent_logins' => $recentUsers
                ],
                'success' => true,
                'cache_hit' => false,
                'last_updated' => $stats->first()->last_updated ?? now()
            ];

            if ($useCache) {
                Cache::put($cacheKey, $result, self::CACHE_TTL['dashboard_stats']);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('OptimizedAdminQueryService::getOptimizedDashboardStats failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'overview' => [],
                'recent_activity' => [],
                'success' => false,
                'error' => 'Failed to fetch dashboard statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get bulk operation targets (optimized for large datasets)
     */
    public function getBulkOperationTargets($type, $filters = [], $limit = 1000)
    {
        try {
            if ($type === 'players') {
                $query = DB::table('players as p')
                    ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                    ->select([
                        'p.id', 'p.username', 'p.real_name', 'p.role', 'p.status',
                        't.name as team_name'
                    ]);
            } else {
                $query = DB::table('teams as t')
                    ->select([
                        't.id', 't.name', 't.short_name', 't.region', 't.status'
                    ]);
            }

            // Apply bulk operation filters
            if (!empty($filters['status'])) {
                $query->whereIn(($type === 'players' ? 'p' : 't') . '.status', (array) $filters['status']);
            }

            if (!empty($filters['region']) && $type === 'teams') {
                $query->whereIn('t.region', (array) $filters['region']);
            }

            if (!empty($filters['role']) && $type === 'players') {
                $query->whereIn('p.role', (array) $filters['role']);
            }

            $results = $query->limit($limit)->get();

            return [
                'data' => $results,
                'total' => $results->count(),
                'success' => true
            ];

        } catch (\Exception $e) {
            Log::error('OptimizedAdminQueryService::getBulkOperationTargets failed', [
                'error' => $e->getMessage(),
                'type' => $type,
                'filters' => $filters
            ]);

            return [
                'data' => [],
                'total' => 0,
                'success' => false,
                'error' => 'Failed to fetch bulk operation targets: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clear cache for admin dashboard
     */
    public function clearAdminCache($specific = null)
    {
        if ($specific) {
            $keys = is_array($specific) ? $specific : [$specific];
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        } else {
            // Clear all admin cache
            $patterns = [
                'admin_players_*',
                'admin_teams_*',
                'admin_live_matches',
                'admin_dashboard_stats'
            ];
            
            foreach ($patterns as $pattern) {
                Cache::forget($pattern);
            }
        }

        Log::info('Admin cache cleared', ['specific' => $specific]);
    }

    /**
     * Format player data for admin dashboard
     */
    private function formatPlayerForAdmin($player)
    {
        $rating = $player->elo_rating ?? $player->rating ?? 0;
        
        return [
            'id' => $player->id,
            'username' => $player->username,
            'real_name' => $player->real_name,
            'avatar' => $player->avatar,
            'role' => $player->role,
            'main_hero' => $player->main_hero,
            'rating' => (int) $rating,
            'rank' => $player->calculated_rank ?? 999,
            'country' => $player->country,
            'age' => $player->age,
            'status' => $player->status ?? 'active',
            'team' => $player->team_name ? [
                'name' => $player->team_name,
                'short_name' => $player->team_short_name,
                'logo' => $player->team_logo,
                'region' => $player->team_region,
                'rating' => $player->team_rating
            ] : null,
            'stats' => [
                'total_matches' => $player->total_matches ?? 0,
                'total_wins' => $player->total_wins ?? 0,
                'win_rate' => $player->total_matches > 0 
                    ? round(($player->total_wins / $player->total_matches) * 100, 1) 
                    : 0,
                'kda' => $player->overall_kda ?? 0,
                'eliminations' => $player->total_eliminations ?? 0,
                'deaths' => $player->total_deaths ?? 0,
                'assists' => $player->total_assists ?? 0
            ],
            'earnings' => [
                'amount' => $player->earnings_amount ?? 0,
                'currency' => $player->earnings_currency ?? 'USD'
            ],
            'created_at' => $player->created_at,
            'updated_at' => $player->updated_at
        ];
    }

    /**
     * Format team data for admin dashboard
     */
    private function formatTeamForAdmin($team)
    {
        $rating = $team->elo_rating ?? $team->rating ?? 0;
        $winRate = $team->matches_played > 0 
            ? round(($team->wins / $team->matches_played) * 100, 1) 
            : 0;
        
        return [
            'id' => $team->id,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo' => $team->logo,
            'region' => $team->region,
            'platform' => $team->platform ?? 'PC',
            'country' => $team->country,
            'rating' => (int) $rating,
            'rank' => $team->calculated_rank ?? 999,
            'status' => $team->status ?? 'active',
            'player_count' => $team->player_count ?? 0,
            'stats' => [
                'matches_played' => $team->matches_played ?? 0,
                'wins' => $team->wins ?? 0,
                'losses' => $team->losses ?? 0,
                'win_rate' => $winRate,
                'maps_won' => $team->maps_won ?? 0,
                'maps_lost' => $team->maps_lost ?? 0,
                'map_win_rate' => $team->map_win_rate ?? 0
            ],
            'earnings' => [
                'amount' => $team->earnings_amount ?? 0,
                'currency' => $team->earnings_currency ?? 'USD'
            ],
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at
        ];
    }

    /**
     * Format match data for admin dashboard
     */
    private function formatMatchForAdmin($match)
    {
        return [
            'id' => $match->id,
            'status' => $match->status,
            'format' => $match->format ?? 'BO3',
            'current_map' => $match->current_map,
            'scheduled_at' => $match->scheduled_at,
            'stream_url' => $match->stream_url,
            'team1' => [
                'id' => $match->team1_id,
                'name' => $match->team1_name,
                'short_name' => $match->team1_short,
                'logo' => $match->team1_logo,
                'region' => $match->team1_region,
                'score' => $match->team1_score ?? 0
            ],
            'team2' => [
                'id' => $match->team2_id,
                'name' => $match->team2_name,
                'short_name' => $match->team2_short,
                'logo' => $match->team2_logo,
                'region' => $match->team2_region,
                'score' => $match->team2_score ?? 0
            ],
            'event' => [
                'name' => $match->event_name,
                'type' => $match->event_type,
                'tier' => $match->event_tier
            ],
            'maps_data' => $match->maps_data ? json_decode($match->maps_data, true) : [],
            'live_data' => $match->live_data ? json_decode($match->live_data, true) : [],
            'actions' => [
                'can_start' => $match->status === 'upcoming',
                'can_update' => in_array($match->status, ['live', 'paused']),
                'can_complete' => in_array($match->status, ['live', 'paused']),
                'can_pause' => $match->status === 'live',
                'can_resume' => $match->status === 'paused'
            ]
        ];
    }
}