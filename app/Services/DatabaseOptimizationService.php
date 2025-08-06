<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DatabaseOptimizationService
{
    // Cache keys and TTLs
    const CACHE_KEYS = [
        'team_rankings' => 'team_rankings',
        'player_rankings' => 'player_rankings',
        'match_stats' => 'match_stats',
        'earnings_leaderboard' => 'earnings_leaderboard',
        'hero_meta' => 'hero_meta_stats',
    ];
    
    const CACHE_TTL = [
        'rankings' => 300,      // 5 minutes for rankings
        'stats' => 600,         // 10 minutes for stats
        'leaderboards' => 900,  // 15 minutes for leaderboards
        'meta' => 1800,         // 30 minutes for hero meta
    ];

    /**
     * Get optimized team rankings with caching
     */
    public function getTeamRankings($region = 'all', $platform = 'all', $limit = 50)
    {
        $cacheKey = self::CACHE_KEYS['team_rankings'] . "_{$region}_{$platform}_{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL['rankings'], function () use ($region, $platform, $limit) {
            $query = DB::table('teams as t')
                ->select([
                    't.id', 't.name', 't.short_name', 't.logo', 't.region', 't.platform',
                    't.country', 't.flag', 't.elo_rating as rating', 't.peak_elo as peak_rating',
                    't.wins', 't.losses', 't.matches_played', 't.win_rate', 't.maps_won', 't.maps_lost',
                    't.map_win_rate', 't.earnings_amount', 't.earnings_currency', 't.recent_performance',
                    't.longest_win_streak', 't.current_streak_count', 't.current_streak_type',
                    DB::raw('ROW_NUMBER() OVER (ORDER BY t.elo_rating DESC) as rank')
                ])
                ->where('t.matches_played', '>', 0); // Only include teams that have played matches
            
            if ($region !== 'all') {
                $query->where('t.region', $region);
            }
            
            if ($platform !== 'all') {
                $query->where('t.platform', $platform);
            }
            
            return $query->orderBy('t.elo_rating', 'desc')
                        ->limit($limit)
                        ->get()
                        ->map(function ($team) {
                            return $this->formatTeamForRankings($team);
                        });
        });
    }

    /**
     * Get optimized player rankings with caching
     */
    public function getPlayerRankings($role = 'all', $region = 'all', $limit = 100)
    {
        $cacheKey = self::CACHE_KEYS['player_rankings'] . "_{$role}_{$region}_{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL['rankings'], function () use ($role, $region, $limit) {
            $query = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role', 'p.main_hero',
                    'p.country', 'p.age', 'p.elo_rating as rating', 'p.peak_elo as peak_rating',
                    'p.total_matches', 'p.total_wins', 'p.total_eliminations', 'p.total_deaths',
                    'p.total_assists', 'p.overall_kda', 'p.earnings_amount', 'p.earnings_currency',
                    'p.most_played_hero', 'p.longest_win_streak', 'p.current_win_streak',
                    't.name as team_name', 't.short_name as team_short', 't.logo as team_logo',
                    DB::raw('ROW_NUMBER() OVER (ORDER BY p.elo_rating DESC) as rank')
                ])
                ->where('p.total_matches', '>', 0) // Only include players with match history
                ->where('p.status', 'active');
            
            if ($role !== 'all') {
                $query->where('p.role', $role);
            }
            
            if ($region !== 'all') {
                $query->where('p.region', $region);
            }
            
            return $query->orderBy('p.elo_rating', 'desc')
                        ->limit($limit)
                        ->get()
                        ->map(function ($player) {
                            return $this->formatPlayerForRankings($player);
                        });
        });
    }

    /**
     * Get comprehensive match statistics with optimized queries
     */
    public function getMatchStatistics($teamId = null, $playerId = null, $period = 'all', $eventTier = 'all')
    {
        $cacheKey = self::CACHE_KEYS['match_stats'] . "_{$teamId}_{$playerId}_{$period}_{$eventTier}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL['stats'], function () use ($teamId, $playerId, $period, $eventTier) {
            // Use match results cache for faster queries
            $query = DB::table('match_results_cache as mrc')
                ->leftJoin('matches as m', 'mrc.match_id', '=', 'm.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'mrc.*', 
                    'm.format', 'm.tournament_round', 'm.maps_data',
                    'e.name as event_name', 'e.type as event_type', 'e.tier as event_tier'
                ]);
            
            if ($teamId) {
                $query->where('mrc.team_id', $teamId);
            }
            
            if ($playerId) {
                $query->where('mrc.player_id', $playerId);
            }
            
            if ($eventTier !== 'all') {
                $query->where('e.tier', $eventTier);
            }
            
            // Apply date filters
            if ($period !== 'all') {
                $date = $this->getDateFromPeriod($period);
                if ($date) {
                    $query->where('mrc.match_date', '>=', $date);
                }
            }
            
            $results = $query->orderBy('mrc.match_date', 'desc')->get();
            
            return $this->calculateComprehensiveStats($results);
        });
    }

    /**
     * Get earnings leaderboard with proper data types
     */
    public function getEarningsLeaderboard($type = 'teams', $currency = 'USD', $period = 'all', $limit = 50)
    {
        $cacheKey = self::CACHE_KEYS['earnings_leaderboard'] . "_{$type}_{$currency}_{$period}_{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL['leaderboards'], function () use ($type, $currency, $period, $limit) {
            $table = $type === 'teams' ? 'teams' : 'players';
            $nameField = $type === 'teams' ? 'name' : 'username';
            
            $query = DB::table("{$table} as main")
                ->select([
                    'main.id', 
                    "main.{$nameField} as name",
                    'main.earnings_amount',
                    'main.earnings_currency',
                    DB::raw('ROW_NUMBER() OVER (ORDER BY main.earnings_amount DESC) as rank')
                ])
                ->where('main.earnings_amount', '>', 0)
                ->where('main.earnings_currency', $currency);
            
            if ($type === 'players') {
                $query->leftJoin('teams as t', 'main.team_id', '=', 't.id')
                      ->addSelect(['t.name as team_name', 't.short_name as team_short', 't.logo as team_logo'])
                      ->where('main.status', 'active');
            }
            
            // Filter by period if specified
            if ($period !== 'all') {
                $date = $this->getDateFromPeriod($period);
                if ($date) {
                    // Sum earnings from earnings_history for the period
                    $earningsSubquery = DB::table('earnings_history as eh')
                        ->selectRaw('SUM(eh.amount) as period_earnings')
                        ->where('eh.earnable_type', $type === 'teams' ? 'App\\Models\\Team' : 'App\\Models\\Player')
                        ->whereColumn('eh.earnable_id', 'main.id')
                        ->where('eh.awarded_at', '>=', $date)
                        ->where('eh.currency', $currency);
                    
                    $query->selectSub($earningsSubquery, 'period_earnings')
                          ->havingRaw('period_earnings > 0')
                          ->orderBy('period_earnings', 'desc');
                } else {
                    $query->orderBy('main.earnings_amount', 'desc');
                }
            } else {
                $query->orderBy('main.earnings_amount', 'desc');
            }
            
            return $query->limit($limit)->get()->map(function ($item) use ($type, $period) {
                return $this->formatEarningsEntry($item, $type, $period);
            });
        });
    }

    /**
     * Get hero meta statistics
     */
    public function getHeroMetaStatistics($role = 'all', $tier = 'all', $period = 'last_30_days')
    {
        $cacheKey = self::CACHE_KEYS['hero_meta'] . "_{$role}_{$tier}_{$period}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL['meta'], function () use ($role, $tier, $period) {
            $date = $this->getDateFromPeriod($period);
            
            $query = DB::table('match_player_stats as mps')
                ->leftJoin('players as p', 'mps.player_id', '=', 'p.id')
                ->leftJoin('matches as m', 'mps.match_id', '=', 'm.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->leftJoin('match_results_cache as mrc', function($join) {
                    $join->on('mrc.match_id', '=', 'mps.match_id')
                         ->on('mrc.player_id', '=', 'mps.player_id');
                })
                ->select([
                    'mps.hero_played as hero',
                    'p.role',
                    DB::raw('COUNT(*) as times_played'),
                    DB::raw('SUM(CASE WHEN mrc.result = "win" THEN 1 ELSE 0 END) as wins'),
                    DB::raw('AVG(mps.eliminations) as avg_eliminations'),
                    DB::raw('AVG(mps.deaths) as avg_deaths'),
                    DB::raw('AVG(mps.assists) as avg_assists'),
                    DB::raw('AVG(mps.damage_dealt) as avg_damage'),
                    DB::raw('AVG(mps.healing_done) as avg_healing'),
                    DB::raw('AVG(mps.damage_blocked) as avg_damage_blocked')
                ])
                ->whereNotNull('mps.hero_played')
                ->where('m.status', 'completed');
            
            if ($date) {
                $query->where('m.scheduled_at', '>=', $date);
            }
            
            if ($role !== 'all') {
                $query->where('p.role', $role);
            }
            
            if ($tier !== 'all') {
                $query->where('e.tier', $tier);
            }
            
            return $query->groupBy(['mps.hero_played', 'p.role'])
                        ->having('times_played', '>=', 10) // Minimum sample size
                        ->orderBy('times_played', 'desc')
                        ->get()
                        ->map(function ($hero) {
                            return $this->formatHeroMetaStats($hero);
                        });
        });
    }

    /**
     * Optimize database with proper indexes and maintenance
     */
    public function optimizeDatabase()
    {
        try {
            // Create additional performance indexes if they don't exist
            $this->createOptimizedIndexes();
            
            // Clean up old cache entries and temporary data
            $this->cleanupOldData();
            
            // Update team and player rankings
            $this->updateAllRankings();
            
            // Refresh materialized statistics
            $this->refreshStatistics();
            
            Log::info('Database optimization completed successfully');
            
            return [
                'status' => 'success',
                'message' => 'Database optimization completed',
                'timestamp' => now()
            ];
            
        } catch (\Exception $e) {
            Log::error('Database optimization failed: ' . $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => 'Database optimization failed: ' . $e->getMessage(),
                'timestamp' => now()
            ];
        }
    }

    /**
     * Create optimized database indexes for frequent queries
     */
    private function createOptimizedIndexes()
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_teams_performance ON teams(elo_rating DESC, matches_played, region)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_players_performance ON players(elo_rating DESC, total_matches, role, status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_matches_completion ON matches(status, scheduled_at DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_match_stats_hero ON match_player_stats(hero_played, match_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_earnings_history_date ON earnings_history(awarded_at DESC, earnable_type, earnable_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_elo_history_date ON elo_history(changed_at DESC, ratable_type, ratable_id)');
        
        // Composite indexes for complex queries
        DB::statement('CREATE INDEX IF NOT EXISTS idx_teams_region_rating ON teams(region, elo_rating DESC) WHERE matches_played > 0');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_players_role_rating ON players(role, elo_rating DESC) WHERE status = "active"');
    }

    /**
     * Clean up old data and temporary entries
     */
    private function cleanupOldData()
    {
        // Remove old match results cache entries (older than 1 year)
        $oldDate = now()->subYear();
        DB::table('match_results_cache')->where('match_date', '<', $oldDate)->delete();
        
        // Archive old ELO history (older than 2 years)
        $archiveDate = now()->subYears(2);
        $oldEloRecords = DB::table('elo_history')->where('changed_at', '<', $archiveDate)->count();
        if ($oldEloRecords > 10000) {
            // Archive to separate table if needed
            Log::info("Found {$oldEloRecords} old ELO history records that could be archived");
        }
        
        // Clear expired cache entries
        Cache::flush(); // For simplicity, flush all cache - could be more selective
    }

    /**
     * Update all rankings with proper ordering
     */
    private function updateAllRankings()
    {
        // Update team rankings
        $teams = DB::table('teams')
            ->where('matches_played', '>', 0)
            ->orderBy('elo_rating', 'desc')
            ->select('id')
            ->get();
        
        foreach ($teams as $index => $team) {
            DB::table('teams')
                ->where('id', $team->id)
                ->update(['rank' => $index + 1]);
        }
        
        // Update player rankings by role
        $roles = ['Vanguard', 'Duelist', 'Strategist'];
        
        foreach ($roles as $role) {
            $players = DB::table('players')
                ->where('total_matches', '>', 0)
                ->where('role', $role)
                ->where('status', 'active')
                ->orderBy('elo_rating', 'desc')
                ->select('id')
                ->get();
            
            foreach ($players as $index => $player) {
                DB::table('players')
                    ->where('id', $player->id)
                    ->update(['rank' => $index + 1]);
            }
        }
    }

    /**
     * Refresh materialized statistics
     */
    private function refreshStatistics()
    {
        // Update team win rates and statistics
        DB::statement('
            UPDATE teams 
            SET win_rate = CASE 
                WHEN matches_played > 0 THEN ROUND((wins * 100.0) / matches_played, 2)
                ELSE 0 
            END,
            map_win_rate = CASE 
                WHEN (maps_won + maps_lost) > 0 THEN ROUND((maps_won * 100.0) / (maps_won + maps_lost), 2)
                ELSE 0 
            END
            WHERE matches_played > 0
        ');
        
        // Update player KDA ratios
        DB::statement('
            UPDATE players 
            SET overall_kda = CASE 
                WHEN total_deaths > 0 THEN ROUND((total_eliminations + total_assists) / total_deaths, 2)
                ELSE total_eliminations + total_assists
            END
            WHERE total_matches > 0
        ');
        
        // Update hero statistics for players
        $this->updatePlayerHeroStatistics();
    }

    /**
     * Update player hero statistics from match data
     */
    private function updatePlayerHeroStatistics()
    {
        $players = DB::table('players')
            ->where('total_matches', '>', 0)
            ->select('id')
            ->get();
        
        foreach ($players as $player) {
            // Get hero statistics from match player stats
            $heroStats = DB::table('match_player_stats as mps')
                ->leftJoin('match_results_cache as mrc', function($join) use ($player) {
                    $join->on('mrc.match_id', '=', 'mps.match_id')
                         ->where('mrc.player_id', $player->id);
                })
                ->select([
                    'mps.hero_played as hero',
                    DB::raw('COUNT(*) as times_played'),
                    DB::raw('SUM(CASE WHEN mrc.result = "win" THEN 1 ELSE 0 END) as wins'),
                    DB::raw('AVG(mps.eliminations) as avg_eliminations'),
                    DB::raw('AVG(mps.deaths) as avg_deaths'),
                    DB::raw('AVG(mps.assists) as avg_assists')
                ])
                ->where('mps.player_id', $player->id)
                ->whereNotNull('mps.hero_played')
                ->groupBy('mps.hero_played')
                ->get();
            
            if ($heroStats->isNotEmpty()) {
                // Find most played and best winrate heroes
                $mostPlayed = $heroStats->sortByDesc('times_played')->first();
                $bestWinrate = $heroStats->where('times_played', '>=', 3)
                                        ->sortByDesc(function($hero) {
                                            return $hero->times_played > 0 ? ($hero->wins / $hero->times_played) : 0;
                                        })->first();
                
                DB::table('players')
                    ->where('id', $player->id)
                    ->update([
                        'hero_statistics' => json_encode($heroStats->toArray()),
                        'most_played_hero' => $mostPlayed->hero ?? null,
                        'best_winrate_hero' => $bestWinrate->hero ?? null
                    ]);
            }
        }
    }

    /**
     * Format team data for rankings display
     */
    private function formatTeamForRankings($team)
    {
        return [
            'id' => $team->id,
            'rank' => $team->rank,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo' => $team->logo,
            'region' => $team->region,
            'platform' => $team->platform,
            'country' => $team->country,
            'flag' => $team->flag,
            'rating' => $team->rating,
            'peak_rating' => $team->peak_rating,
            'matches_played' => $team->matches_played,
            'record' => "{$team->wins}-{$team->losses}",
            'win_rate' => round($team->win_rate, 1),
            'map_record' => "{$team->maps_won}-{$team->maps_lost}",
            'map_win_rate' => round($team->map_win_rate, 1),
            'earnings' => $this->formatEarnings($team->earnings_amount, $team->earnings_currency),
            'current_streak' => $this->formatStreak($team->current_streak_count, $team->current_streak_type),
            'longest_win_streak' => $team->longest_win_streak,
            'recent_form' => $team->recent_performance ? json_decode($team->recent_performance, true) : []
        ];
    }

    /**
     * Format player data for rankings display
     */
    private function formatPlayerForRankings($player)
    {
        return [
            'id' => $player->id,
            'rank' => $player->rank,
            'username' => $player->username,
            'real_name' => $player->real_name,
            'avatar' => $player->avatar,
            'role' => $player->role,
            'main_hero' => $player->main_hero,
            'most_played_hero' => $player->most_played_hero,
            'country' => $player->country,
            'age' => $player->age,
            'rating' => $player->rating,
            'peak_rating' => $player->peak_rating,
            'matches_played' => $player->total_matches,
            'record' => "{$player->total_wins}-" . ($player->total_matches - $player->total_wins),
            'win_rate' => $player->total_matches > 0 ? round(($player->total_wins / $player->total_matches) * 100, 1) : 0,
            'kda' => $player->overall_kda,
            'earnings' => $this->formatEarnings($player->earnings_amount, $player->earnings_currency),
            'longest_win_streak' => $player->longest_win_streak,
            'current_win_streak' => $player->current_win_streak,
            'team' => $player->team_name ? [
                'name' => $player->team_name,
                'short_name' => $player->team_short,
                'logo' => $player->team_logo
            ] : null
        ];
    }

    /**
     * Calculate comprehensive statistics from match results
     */
    private function calculateComprehensiveStats($results)
    {
        if ($results->isEmpty()) {
            return $this->getEmptyStats();
        }
        
        $wins = $results->where('result', 'win')->count();
        $losses = $results->where('result', 'loss')->count();
        $totalMatches = $wins + $losses;
        
        $totalMapsWon = $results->sum('team_score');
        $totalMapsLost = $results->sum('opponent_score');
        $totalMaps = $totalMapsWon + $totalMapsLost;
        
        $avgEloGain = $results->where('elo_change', '>', 0)->avg('elo_change') ?? 0;
        $avgEloLoss = $results->where('elo_change', '<', 0)->avg('elo_change') ?? 0;
        
        return [
            'overview' => [
                'total_matches' => $totalMatches,
                'wins' => $wins,
                'losses' => $losses,
                'win_rate' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0,
                'maps_won' => $totalMapsWon,
                'maps_lost' => $totalMapsLost,
                'map_win_rate' => $totalMaps > 0 ? round(($totalMapsWon / $totalMaps) * 100, 1) : 0,
                'map_differential' => $totalMapsWon - $totalMapsLost,
                'avg_elo_gain' => round($avgEloGain, 1),
                'avg_elo_loss' => round($avgEloLoss, 1)
            ],
            'recent_form' => $this->calculateRecentForm($results, 10),
            'performance_by_format' => $this->analyzePerformanceByFormat($results),
            'elo_progression' => $this->calculateEloProgression($results)
        ];
    }

    /**
     * Helper methods
     */
    private function getDateFromPeriod($period)
    {
        switch ($period) {
            case 'last_7_days':
                return now()->subDays(7);
            case 'last_30_days':
                return now()->subDays(30);
            case 'last_90_days':
                return now()->subDays(90);
            case 'this_year':
                return now()->startOfYear();
            default:
                return null;
        }
    }

    private function formatEarnings($amount, $currency)
    {
        if ($amount == 0) return '$0';
        
        $formatted = number_format($amount, 2);
        switch ($currency) {
            case 'USD': return '$' . $formatted;
            case 'EUR': return '€' . $formatted;
            case 'GBP': return '£' . $formatted;
            default: return $currency . ' ' . $formatted;
        }
    }

    private function formatStreak($count, $type)
    {
        if ($count == 0 || $type === 'none') return 'N/A';
        return $count . strtoupper($type[0]);
    }

    private function formatEarningsEntry($item, $type, $period)
    {
        $earnings = $period !== 'all' && isset($item->period_earnings) 
            ? $item->period_earnings 
            : $item->earnings_amount;
        
        $result = [
            'id' => $item->id,
            'rank' => $item->rank,
            'name' => $item->name,
            'earnings' => $this->formatEarnings($earnings, $item->earnings_currency),
            'earnings_amount' => $earnings,
            'currency' => $item->earnings_currency
        ];
        
        if ($type === 'players' && isset($item->team_name)) {
            $result['team'] = [
                'name' => $item->team_name,
                'short_name' => $item->team_short,
                'logo' => $item->team_logo
            ];
        }
        
        return $result;
    }

    private function formatHeroMetaStats($hero)
    {
        $winRate = $hero->times_played > 0 ? round(($hero->wins / $hero->times_played) * 100, 1) : 0;
        
        return [
            'hero' => $hero->hero,
            'role' => $hero->role,
            'times_played' => $hero->times_played,
            'wins' => $hero->wins,
            'losses' => $hero->times_played - $hero->wins,
            'win_rate' => $winRate,
            'avg_eliminations' => round($hero->avg_eliminations, 1),
            'avg_deaths' => round($hero->avg_deaths, 1),
            'avg_assists' => round($hero->avg_assists, 1),
            'avg_damage' => round($hero->avg_damage, 0),
            'avg_healing' => round($hero->avg_healing, 0),
            'avg_damage_blocked' => round($hero->avg_damage_blocked, 0),
            'kda_ratio' => $hero->avg_deaths > 0 ? round(($hero->avg_eliminations + $hero->avg_assists) / $hero->avg_deaths, 2) : 0
        ];
    }

    private function calculateRecentForm($results, $limit)
    {
        return $results->sortByDesc('match_date')
                      ->take($limit)
                      ->map(function ($result) {
                          return [
                              'result' => strtoupper($result->result[0]),
                              'elo_change' => $result->elo_change,
                              'date' => $result->match_date
                          ];
                      })
                      ->values()
                      ->toArray();
    }

    private function analyzePerformanceByFormat($results)
    {
        return $results->groupBy('format')
                      ->map(function ($formatResults, $format) {
                          $wins = $formatResults->where('result', 'win')->count();
                          $total = $formatResults->count();
                          
                          return [
                              'format' => $format ?? 'BO3',
                              'matches' => $total,
                              'wins' => $wins,
                              'losses' => $total - $wins,
                              'win_rate' => $total > 0 ? round(($wins / $total) * 100, 1) : 0
                          ];
                      })
                      ->values()
                      ->toArray();
    }

    private function calculateEloProgression($results)
    {
        return $results->sortBy('match_date')
                      ->map(function ($result) {
                          return [
                              'date' => $result->match_date,
                              'rating_before' => $result->elo_before,
                              'rating_after' => $result->elo_after,
                              'change' => $result->elo_change
                          ];
                      })
                      ->values()
                      ->toArray();
    }

    private function getEmptyStats()
    {
        return [
            'overview' => [
                'total_matches' => 0,
                'wins' => 0,
                'losses' => 0,
                'win_rate' => 0,
                'maps_won' => 0,
                'maps_lost' => 0,
                'map_win_rate' => 0,
                'map_differential' => 0,
                'avg_elo_gain' => 0,
                'avg_elo_loss' => 0
            ],
            'recent_form' => [],
            'performance_by_format' => [],
            'elo_progression' => []
        ];
    }

    /**
     * Clear all cached data
     */
    public function clearCache()
    {
        Cache::flush();
        Log::info('All cached data cleared');
    }

    /**
     * Clear specific cache keys
     */
    public function clearSpecificCache($keys = [])
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Log::info('Specific cache keys cleared: ' . implode(', ', $keys));
    }
}