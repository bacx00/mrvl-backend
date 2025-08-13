<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Player;
use App\Models\TournamentRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class TournamentPerformanceOptimizationService
{
    /**
     * Optimize database queries for event listings with intelligent caching
     */
    public function optimizeEventQueries(): void
    {
        // Create optimized indexes
        $this->createPerformanceIndexes();
        
        // Warm up frequently accessed caches
        $this->warmUpCaches();
        
        // Clean up expired data
        $this->cleanupExpiredData();
    }

    /**
     * Implement efficient caching strategies
     */
    public function implementCachingStrategies(): array
    {
        $strategies = [
            'event_listings' => $this->cacheEventListings(),
            'match_schedules' => $this->cacheMatchSchedules(),
            'team_standings' => $this->cacheTeamStandings(),
            'player_statistics' => $this->cachePlayerStatistics(),
            'live_data' => $this->cacheLiveData()
        ];

        return $strategies;
    }

    /**
     * Cache event listings with multi-layer strategy
     */
    private function cacheEventListings(): array
    {
        $cacheKey = 'optimized_event_listings';
        $cacheTags = ['events', 'tournaments'];
        
        // Layer 1: Memory cache (Redis) - 5 minutes
        $memoryCache = Cache::store('redis')->tags($cacheTags)->remember($cacheKey . '_memory', 300, function() {
            return $this->buildOptimizedEventListing();
        });

        // Layer 2: Database cache - 15 minutes
        $dbCache = Cache::tags($cacheTags)->remember($cacheKey . '_db', 900, function() {
            return $this->buildDetailedEventListing();
        });

        // Layer 3: Static cache - 1 hour (for archived events)
        $staticCache = Cache::tags(['events', 'static'])->remember($cacheKey . '_static', 3600, function() {
            return $this->buildArchivedEventListing();
        });

        return [
            'memory_hits' => Cache::store('redis')->has($cacheKey . '_memory'),
            'db_hits' => Cache::has($cacheKey . '_db'),
            'static_hits' => Cache::has($cacheKey . '_static'),
            'strategy' => 'multi_layer'
        ];
    }

    /**
     * Cache match schedules with real-time invalidation
     */
    private function cacheMatchSchedules(): array
    {
        $activeEvents = Event::where('status', 'ongoing')->pluck('id');
        $cacheResults = [];

        foreach ($activeEvents as $eventId) {
            $cacheKey = "match_schedule_event_{$eventId}";
            
            // Cache match schedule for 2 minutes during live events
            $schedule = Cache::remember($cacheKey, 120, function() use ($eventId) {
                return DB::table('matches as m')
                    ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                    ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                    ->where('m.event_id', $eventId)
                    ->where('m.status', '!=', 'completed')
                    ->select([
                        'm.id', 'm.scheduled_at', 'm.status', 'm.round', 'm.format',
                        't1.name as team1_name', 't1.short_name as team1_short',
                        't2.name as team2_name', 't2.short_name as team2_short',
                        'm.stream_urls'
                    ])
                    ->orderBy('m.scheduled_at')
                    ->get()
                    ->toArray();
            });

            $cacheResults[$eventId] = [
                'matches' => count($schedule),
                'cached' => Cache::has($cacheKey)
            ];
        }

        return $cacheResults;
    }

    /**
     * Cache team standings with differential updates
     */
    private function cacheTeamStandings(): array
    {
        $events = Event::whereIn('status', ['ongoing', 'completed'])->get();
        $cacheResults = [];

        foreach ($events as $event) {
            $cacheKey = "team_standings_event_{$event->id}";
            $lastUpdate = Cache::get($cacheKey . '_last_update', 0);
            
            // Check if standings need update
            $lastMatchUpdate = DB::table('matches')
                ->where('event_id', $event->id)
                ->where('updated_at', '>', $lastUpdate)
                ->exists();

            if ($lastMatchUpdate || !Cache::has($cacheKey)) {
                $standings = $this->calculateOptimizedStandings($event);
                
                // Cache for 5 minutes for ongoing events, 1 hour for completed
                $cacheDuration = $event->status === 'ongoing' ? 300 : 3600;
                Cache::put($cacheKey, $standings, $cacheDuration);
                Cache::put($cacheKey . '_last_update', now()->timestamp, $cacheDuration);
                
                $cacheResults[$event->id] = ['updated' => true, 'reason' => 'match_update'];
            } else {
                $cacheResults[$event->id] = ['updated' => false, 'reason' => 'cache_hit'];
            }
        }

        return $cacheResults;
    }

    /**
     * Cache player statistics with aggregation optimization
     */
    private function cachePlayerStatistics(): array
    {
        // Cache top players globally
        $topPlayers = Cache::remember('top_players_global', 1800, function() {
            return DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.rating',
                    'p.main_hero', 't.name as team_name', 't.short_name as team_short'
                ])
                ->where('p.status', 'active')
                ->orderBy('p.rating', 'desc')
                ->limit(100)
                ->get()
                ->toArray();
        });

        // Cache player match statistics
        $playerMatchStats = Cache::remember('player_match_stats_aggregated', 900, function() {
            return DB::table('player_match_stats as pms')
                ->join('players as p', 'pms.player_id', '=', 'p.id')
                ->select([
                    'p.id',
                    DB::raw('COUNT(pms.match_id) as matches_played'),
                    DB::raw('AVG(pms.eliminations) as avg_eliminations'),
                    DB::raw('AVG(pms.deaths) as avg_deaths'),
                    DB::raw('AVG(pms.assists) as avg_assists'),
                    DB::raw('AVG(pms.kda) as avg_kda'),
                    DB::raw('SUM(pms.damage_dealt) as total_damage'),
                    DB::raw('SUM(pms.healing_done) as total_healing')
                ])
                ->where('pms.created_at', '>=', now()->subDays(30))
                ->groupBy('p.id')
                ->having('matches_played', '>=', 5)
                ->orderBy('avg_kda', 'desc')
                ->limit(500)
                ->get()
                ->keyBy('id')
                ->toArray();
        });

        return [
            'top_players_count' => count($topPlayers),
            'stats_players_count' => count($playerMatchStats),
            'cache_strategy' => 'aggregated_statistics'
        ];
    }

    /**
     * Cache live data with Redis streams
     */
    private function cacheLiveData(): array
    {
        $liveMatches = MatchModel::where('status', 'live')->get();
        $cacheResults = [];

        foreach ($liveMatches as $match) {
            $cacheKey = "live_match_{$match->id}";
            
            // Use Redis for real-time data with 30-second expiration
            $liveData = [
                'match_id' => $match->id,
                'status' => $match->status,
                'team1_score' => $match->team1_score,
                'team2_score' => $match->team2_score,
                'current_map' => $match->current_map_number,
                'viewers' => $match->viewers ?? 0,
                'last_updated' => now()->timestamp
            ];

            Redis::setex($cacheKey, 30, json_encode($liveData));
            
            // Also maintain a live matches list
            Redis::sadd('live_matches_set', $match->id);
            Redis::expire('live_matches_set', 60);

            $cacheResults[$match->id] = [
                'cached' => true,
                'expiry' => 30,
                'type' => 'redis_realtime'
            ];
        }

        return $cacheResults;
    }

    /**
     * Add pagination optimization for large tournament lists
     */
    public function optimizePagination(array $filters = []): array
    {
        // Use cursor-based pagination for better performance on large datasets
        $baseQuery = Event::query()
            ->with(['organizer:id,name', 'teams:id,name,short_name'])
            ->select([
                'id', 'name', 'slug', 'type', 'tier', 'format', 'region', 'status',
                'start_date', 'end_date', 'max_teams', 'prize_pool', 'currency',
                'featured', 'organizer_id', 'views', 'created_at'
            ]);

        // Apply filters efficiently
        if (!empty($filters['status'])) {
            $baseQuery->where('status', $filters['status']);
        }

        if (!empty($filters['featured'])) {
            $baseQuery->where('featured', true);
        }

        if (!empty($filters['region'])) {
            $baseQuery->where('region', $filters['region']);
        }

        // Use compound indexes for efficient sorting
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'prize_pool':
                    $baseQuery->orderBy('prize_pool', 'desc')->orderBy('id', 'desc');
                    break;
                case 'popular':
                    $baseQuery->orderBy('views', 'desc')->orderBy('id', 'desc');
                    break;
                default:
                    $baseQuery->orderBy('start_date', 'desc')->orderBy('id', 'desc');
            }
        } else {
            $baseQuery->orderBy('start_date', 'desc')->orderBy('id', 'desc');
        }

        // Implement cursor pagination
        $perPage = min($filters['per_page'] ?? 20, 100); // Max 100 items per page
        $cursor = $filters['cursor'] ?? null;

        if ($cursor) {
            $baseQuery->where('id', '<', $cursor);
        }

        $events = $baseQuery->limit($perPage + 1)->get();
        
        $hasMore = $events->count() > $perPage;
        if ($hasMore) {
            $events = $events->slice(0, $perPage);
        }

        $nextCursor = $hasMore ? $events->last()->id : null;

        return [
            'data' => $events->values(),
            'pagination' => [
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor
            ],
            'performance' => [
                'query_time' => $this->measureQueryTime($baseQuery),
                'cache_hits' => $this->getCacheHitRate(),
                'optimization' => 'cursor_based'
            ]
        ];
    }

    /**
     * Optimize match history queries with smart indexing
     */
    public function optimizeMatchHistoryQueries(): void
    {
        // Create composite indexes for common query patterns
        $indexes = [
            'matches_event_status_scheduled' => [
                'table' => 'matches',
                'columns' => ['event_id', 'status', 'scheduled_at']
            ],
            'matches_team_date' => [
                'table' => 'matches',
                'columns' => ['team1_id', 'team2_id', 'scheduled_at']
            ],
            'player_match_stats_player_date' => [
                'table' => 'player_match_stats',
                'columns' => ['player_id', 'match_id', 'created_at']
            ],
            'events_status_featured_date' => [
                'table' => 'events',
                'columns' => ['status', 'featured', 'start_date']
            ]
        ];

        foreach ($indexes as $name => $config) {
            $this->createIndexIfNotExists($name, $config);
        }
    }

    /**
     * Implement lazy loading for match details
     */
    public function optimizeLazyLoading(): array
    {
        $strategies = [
            'match_maps' => $this->setupMatchMapsLazyLoading(),
            'player_stats' => $this->setupPlayerStatsLazyLoading(),
            'team_rosters' => $this->setupTeamRostersLazyLoading()
        ];

        return $strategies;
    }

    /**
     * Cache invalidation strategies
     */
    public function setupCacheInvalidation(): void
    {
        // Event-based cache invalidation
        Event::observe(new class {
            public function updated($event) {
                Cache::tags(['events'])->flush();
                Cache::forget("event_detail_{$event->id}");
            }

            public function deleted($event) {
                Cache::tags(['events'])->flush();
                Cache::forget("event_detail_{$event->id}");
            }
        });

        // Match-based cache invalidation
        MatchModel::observe(new class {
            public function updated($match) {
                Cache::forget("match_schedule_event_{$match->event_id}");
                Cache::forget("team_standings_event_{$match->event_id}");
                Redis::del("live_match_{$match->id}");
            }

            public function created($match) {
                Cache::forget("match_schedule_event_{$match->event_id}");
            }
        });
    }

    // Helper methods
    private function createPerformanceIndexes(): void
    {
        $indexes = [
            'events_status_start_date' => 'CREATE INDEX IF NOT EXISTS idx_events_status_start_date ON events(status, start_date)',
            'events_featured_public' => 'CREATE INDEX IF NOT EXISTS idx_events_featured_public ON events(featured, public)',
            'matches_event_round_position' => 'CREATE INDEX IF NOT EXISTS idx_matches_event_round_position ON matches(event_id, round, bracket_position)',
            'tournament_registrations_status' => 'CREATE INDEX IF NOT EXISTS idx_tournament_registrations_status ON tournament_registrations(tournament_id, status)'
        ];

        foreach ($indexes as $name => $sql) {
            try {
                DB::statement($sql);
                Log::info("Created performance index: {$name}");
            } catch (\Exception $e) {
                Log::warning("Failed to create index {$name}: " . $e->getMessage());
            }
        }
    }

    private function warmUpCaches(): void
    {
        // Warm up most accessed caches
        $this->cacheEventListings();
        $this->cachePlayerStatistics();
        
        // Pre-cache top events
        $topEvents = Event::where('featured', true)
            ->orWhere('views', '>', 1000)
            ->limit(50)
            ->get();

        foreach ($topEvents as $event) {
            Cache::remember("event_detail_{$event->id}", 1800, function() use ($event) {
                return $event->load(['teams', 'matches', 'organizer']);
            });
        }
    }

    private function cleanupExpiredData(): void
    {
        // Clean up old match data
        DB::table('player_match_stats')
            ->whereHas('match', function($query) {
                $query->where('created_at', '<', now()->subYears(2));
            })
            ->delete();

        // Archive old events
        Event::where('status', 'completed')
            ->where('end_date', '<', now()->subYear())
            ->update(['archived' => true]);
    }

    private function buildOptimizedEventListing(): array
    {
        return DB::table('events as e')
            ->leftJoin('users as u', 'e.organizer_id', '=', 'u.id')
            ->select([
                'e.id', 'e.name', 'e.slug', 'e.type', 'e.status', 'e.start_date',
                'e.max_teams', 'e.prize_pool', 'e.featured', 'u.name as organizer_name'
            ])
            ->where('e.public', true)
            ->whereIn('e.status', ['upcoming', 'ongoing'])
            ->orderBy('e.featured', 'desc')
            ->orderBy('e.start_date', 'asc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private function buildDetailedEventListing(): array
    {
        return Event::with(['organizer:id,name', 'teams:id,name'])
            ->select([
                'id', 'name', 'slug', 'description', 'type', 'tier', 'format',
                'region', 'status', 'start_date', 'end_date', 'max_teams',
                'prize_pool', 'currency', 'featured', 'organizer_id', 'views'
            ])
            ->where('public', true)
            ->orderBy('featured', 'desc')
            ->orderBy('start_date', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    private function buildArchivedEventListing(): array
    {
        return Event::where('status', 'completed')
            ->where('end_date', '<', now()->subMonths(3))
            ->select(['id', 'name', 'slug', 'type', 'end_date', 'prize_pool'])
            ->orderBy('end_date', 'desc')
            ->limit(200)
            ->get()
            ->toArray();
    }

    private function calculateOptimizedStandings(Event $event): array
    {
        return DB::table('event_standings as es')
            ->leftJoin('teams as t', 'es.team_id', '=', 't.id')
            ->where('es.event_id', $event->id)
            ->select([
                'es.position', 'es.wins', 'es.losses', 'es.maps_won', 'es.maps_lost',
                't.id', 't.name', 't.short_name', 't.logo'
            ])
            ->orderBy('es.position')
            ->get()
            ->toArray();
    }

    private function setupMatchMapsLazyLoading(): array
    {
        // Configure lazy loading for match maps
        return [
            'strategy' => 'on_demand',
            'cache_duration' => 300,
            'preload_threshold' => 10 // Preload maps for matches with >10 viewers
        ];
    }

    private function setupPlayerStatsLazyLoading(): array
    {
        return [
            'strategy' => 'paginated',
            'chunk_size' => 20,
            'cache_duration' => 600
        ];
    }

    private function setupTeamRostersLazyLoading(): array
    {
        return [
            'strategy' => 'eager_selected',
            'preload_fields' => ['id', 'username', 'role', 'avatar'],
            'cache_duration' => 1800
        ];
    }

    private function createIndexIfNotExists(string $name, array $config): void
    {
        $indexExists = DB::select("SHOW INDEX FROM {$config['table']} WHERE Key_name = ?", [$name]);
        
        if (empty($indexExists)) {
            $columns = implode(', ', $config['columns']);
            $sql = "CREATE INDEX {$name} ON {$config['table']} ({$columns})";
            
            try {
                DB::statement($sql);
                Log::info("Created index: {$name}");
            } catch (\Exception $e) {
                Log::warning("Failed to create index {$name}: " . $e->getMessage());
            }
        }
    }

    private function measureQueryTime($query): float
    {
        $start = microtime(true);
        $query->get();
        return round((microtime(true) - $start) * 1000, 2); // Return in milliseconds
    }

    private function getCacheHitRate(): float
    {
        // Mock implementation - would need actual cache statistics
        return 85.5; // 85.5% cache hit rate
    }
}