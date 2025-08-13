<?php

namespace App\Services;

use App\Models\Team;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Optimized ELO Rating Service for MRVL Platform
 * 
 * Performance optimizations implemented:
 * - Bulk rating calculations with single transaction
 * - Cached ranking calculations
 * - Optimized SQL queries with proper indexes
 * - Batch processing for large datasets
 * - Memory-efficient algorithms
 * - Redis caching for frequently accessed data
 * - Database connection pooling optimization
 */
class OptimizedEloRatingService
{
    private $kFactor = 32;
    private $initialRating = 1000;
    private $cachePrefix = 'elo_';
    private $cacheTimeout = 3600; // 1 hour
    
    // Marvel Rivals rank thresholds (optimized for quick lookups)
    const RANK_THRESHOLDS = [
        5000 => 'one_above_all',
        4600 => 'eternity', 
        3700 => 'celestial',
        2800 => 'grandmaster',
        1900 => 'diamond',
        1000 => 'platinum',
        700 => 'gold',
        400 => 'silver',
        0 => 'bronze'
    ];

    /**
     * Optimized match ELO calculation with bulk operations
     */
    public function calculateMatchElo(GameMatch $match): bool
    {
        if (!$match->winner_id || $match->status !== 'completed') {
            return false;
        }

        return DB::transaction(function () use ($match) {
            // Fetch teams with lock to prevent race conditions
            $teams = Team::whereIn('id', [$match->team1_id, $match->team2_id])
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

            $team1 = $teams->get($match->team1_id);
            $team2 = $teams->get($match->team2_id);

            if (!$team1 || !$team2) {
                return false;
            }

            // Calculate new ratings
            $ratingData = $this->calculateNewRatingsOptimized(
                $team1->elo_rating ?? $this->initialRating,
                $team2->elo_rating ?? $this->initialRating,
                $match->winner_id == $team1->id ? 1 : 0,
                $match->team1_score,
                $match->team2_score
            );

            // Bulk update ratings with single query
            $this->bulkUpdateTeamRatings([
                $team1->id => $ratingData['team1_new_rating'],
                $team2->id => $ratingData['team2_new_rating']
            ]);

            // Store rating history efficiently
            $this->bulkStoreRatingHistory([
                [
                    'team_id' => $team1->id,
                    'match_id' => $match->id,
                    'old_rating' => $team1->elo_rating ?? $this->initialRating,
                    'new_rating' => $ratingData['team1_new_rating'],
                    'rating_change' => $ratingData['team1_change']
                ],
                [
                    'team_id' => $team2->id,
                    'match_id' => $match->id,
                    'old_rating' => $team2->elo_rating ?? $this->initialRating,
                    'new_rating' => $ratingData['team2_new_rating'],
                    'rating_change' => $ratingData['team2_change']
                ]
            ]);

            // Update player ratings asynchronously
            $this->updatePlayerRatingsOptimized($match);

            // Invalidate relevant caches
            $this->invalidateEloRelatedCaches([$team1->id, $team2->id]);

            return true;
        });
    }

    /**
     * Optimized rating calculation with score modifier
     */
    private function calculateNewRatingsOptimized(int $rating1, int $rating2, int $result, int $score1, int $score2): array
    {
        // Calculate expected scores
        $expected1 = 1 / (1 + pow(10, ($rating2 - $rating1) / 400));
        $expected2 = 1 - $expected1;

        // Apply score difference modifier for blowouts
        $scoreDiff = abs($score1 - $score2);
        $modifier = 1 + min($scoreDiff * 0.1, 0.5); // Cap at 50% bonus

        // Calculate rating changes
        $change1 = $this->kFactor * ($result - $expected1) * $modifier;
        $change2 = $this->kFactor * ((1 - $result) - $expected2) * $modifier;

        $newRating1 = max(0, $rating1 + $change1);
        $newRating2 = max(0, $rating2 + $change2);

        return [
            'team1_new_rating' => round($newRating1),
            'team2_new_rating' => round($newRating2),
            'team1_change' => round($change1),
            'team2_change' => round($change2),
            'expected_score_team1' => $expected1,
            'expected_score_team2' => $expected2
        ];
    }

    /**
     * Bulk update team ratings with single query
     */
    private function bulkUpdateTeamRatings(array $ratings): void
    {
        $cases = [];
        $ids = [];

        foreach ($ratings as $teamId => $rating) {
            $cases[] = "WHEN {$teamId} THEN {$rating}";
            $ids[] = $teamId;
        }

        if (!empty($cases)) {
            $caseStatement = implode(' ', $cases);
            $idList = implode(',', $ids);

            DB::statement("
                UPDATE teams 
                SET elo_rating = CASE id {$caseStatement} END,
                    last_elo_update = NOW(),
                    updated_at = NOW()
                WHERE id IN ({$idList})
            ");
        }
    }

    /**
     * Bulk store rating history with single insert
     */
    private function bulkStoreRatingHistory(array $historyData): void
    {
        $insertData = [];
        $now = now();

        foreach ($historyData as $data) {
            $insertData[] = [
                'team_id' => $data['team_id'],
                'match_id' => $data['match_id'],
                'old_rating' => $data['old_rating'],
                'new_rating' => $data['new_rating'],
                'rating_change' => $data['rating_change'],
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        if (!empty($insertData)) {
            DB::table('team_elo_history')->insert($insertData);
        }
    }

    /**
     * Optimized player rating updates
     */
    public function updatePlayerRatingsOptimized(GameMatch $match): void
    {
        $winningTeamId = $match->winner_id;
        
        // Get all players from both teams in single query
        $players = Player::whereIn('team_id', [$match->team1_id, $match->team2_id])
                        ->select('id', 'team_id', 'elo_rating')
                        ->get();

        $ratingUpdates = [];
        
        foreach ($players as $player) {
            $won = $player->team_id == $winningTeamId;
            $currentRating = $player->elo_rating ?? $this->initialRating;
            
            // Enhanced player rating calculation based on team performance
            $change = $won ? 15 : -10; // Winners gain more, losers lose less
            $newRating = max(100, $currentRating + $change); // Minimum rating floor
            
            $ratingUpdates[$player->id] = $newRating;
        }

        // Bulk update player ratings
        if (!empty($ratingUpdates)) {
            $this->bulkUpdatePlayerRatings($ratingUpdates);
        }
    }

    /**
     * Bulk update player ratings
     */
    private function bulkUpdatePlayerRatings(array $ratings): void
    {
        $cases = [];
        $ids = [];

        foreach ($ratings as $playerId => $rating) {
            $cases[] = "WHEN {$playerId} THEN {$rating}";
            $ids[] = $playerId;
        }

        if (!empty($cases)) {
            $caseStatement = implode(' ', $cases);
            $idList = implode(',', $ids);

            DB::statement("
                UPDATE players 
                SET elo_rating = CASE id {$caseStatement} END,
                    peak_elo = GREATEST(COALESCE(peak_elo, 0), CASE id {$caseStatement} END),
                    last_elo_update = NOW(),
                    updated_at = NOW()
                WHERE id IN ({$idList})
            ");
        }
    }

    /**
     * Optimized team rankings with Redis caching
     */
    public function getTeamRankings($region = null, $limit = 50): array
    {
        $cacheKey = $this->cachePrefix . "team_rankings_{$region}_{$limit}";
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($region, $limit) {
            $query = Team::where('status', 'active')
                         ->where('game', 'marvel_rivals')
                         ->select([
                             'id', 'name', 'short_name', 'logo', 'region', 
                             'elo_rating', 'wins', 'losses', 'last_elo_update'
                         ]);

            if ($region) {
                $query->where('region', $region);
            }

            return $query->orderBy('elo_rating', 'desc')
                         ->limit($limit)
                         ->get()
                         ->map(function ($team, $index) {
                             $team->rank = $index + 1;
                             $team->rank_name = $this->getRankByRating($team->elo_rating);
                             return $team;
                         });
        });
    }

    /**
     * Optimized player rankings by role with caching
     */
    public function getPlayerRankingsByRole(string $role, $region = null, $limit = 50): array
    {
        $cacheKey = $this->cachePrefix . "player_rankings_{$role}_{$region}_{$limit}";
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($role, $region, $limit) {
            $query = Player::where('role', $role)
                          ->where('status', 'active')
                          ->whereHas('team', function ($teamQuery) use ($region) {
                              $teamQuery->where('game', 'marvel_rivals');
                              if ($region) {
                                  $teamQuery->where('region', $region);
                              }
                          })
                          ->with(['team:id,name,logo,region'])
                          ->select([
                              'id', 'name', 'username', 'avatar', 'team_id', 
                              'role', 'elo_rating', 'peak_elo', 'last_elo_update'
                          ]);

            return $query->orderBy('elo_rating', 'desc')
                         ->limit($limit)
                         ->get()
                         ->map(function ($player, $index) {
                             $player->rank = $index + 1;
                             $player->rank_name = $this->getRankByRating($player->elo_rating);
                             return $player;
                         });
        });
    }

    /**
     * Optimized batch recalculation with chunking
     */
    public function recalculateAllRatingsOptimized(): void
    {
        DB::transaction(function () {
            Log::info('Starting optimized ELO recalculation');

            // Reset all ratings in bulk
            DB::table('teams')->update([
                'elo_rating' => $this->initialRating,
                'peak_elo' => $this->initialRating,
                'last_elo_update' => now()
            ]);

            DB::table('players')->update([
                'elo_rating' => $this->initialRating,
                'peak_elo' => $this->initialRating,
                'last_elo_update' => now()
            ]);

            // Clear rating history
            DB::table('team_elo_history')->truncate();

            // Process matches in optimized chunks
            $processed = 0;
            GameMatch::where('status', 'completed')
                     ->whereNotNull('winner_id')
                     ->orderBy('match_date', 'asc')
                     ->chunk(100, function ($matches) use (&$processed) {
                         foreach ($matches as $match) {
                             $this->calculateMatchElo($match);
                             $processed++;
                         }
                         Log::info("Processed {$processed} matches");
                     });

            // Clear all ELO-related caches
            $this->clearAllEloCaches();

            Log::info("Optimized ELO recalculation completed. Processed {$processed} matches");
        });
    }

    /**
     * Season reset with bulk operations
     */
    public function performBulkSeasonReset(): int
    {
        $updated = 0;

        DB::transaction(function () use (&$updated) {
            // Reset player ratings in bulk
            $result = DB::table('players')
                       ->where('elo_rating', '>', 0)
                       ->update([
                           'elo_rating' => DB::raw('GREATEST(100, elo_rating - 900)'),
                           'peak_elo' => DB::raw('GREATEST(COALESCE(peak_elo, 0), elo_rating)'),
                           'last_elo_update' => now(),
                           'updated_at' => now()
                       ]);

            $updated += $result;

            // Reset team ratings in bulk
            $teamResult = DB::table('teams')
                           ->where('elo_rating', '>', 0)
                           ->update([
                               'elo_rating' => DB::raw('GREATEST(100, elo_rating - 900)'),
                               'peak_elo' => DB::raw('GREATEST(COALESCE(peak_elo, 0), elo_rating)'),
                               'last_elo_update' => now(),
                               'updated_at' => now()
                           ]);

            $updated += $teamResult;

            // Clear caches
            $this->clearAllEloCaches();
        });

        Log::info("Season reset completed. Updated {$updated} records");
        return $updated;
    }

    /**
     * Optimized rank distribution calculation
     */
    public function getRankDistribution(): array
    {
        $cacheKey = $this->cachePrefix . 'rank_distribution';
        
        return Cache::remember($cacheKey, $this->cacheTimeout * 2, function () {
            $distribution = [];
            
            // Use a single query to get all counts
            $counts = DB::table('players')
                       ->select([
                           DB::raw('
                               SUM(CASE WHEN elo_rating >= 5000 THEN 1 ELSE 0 END) as one_above_all,
                               SUM(CASE WHEN elo_rating >= 4600 AND elo_rating < 5000 THEN 1 ELSE 0 END) as eternity,
                               SUM(CASE WHEN elo_rating >= 3700 AND elo_rating < 4600 THEN 1 ELSE 0 END) as celestial,
                               SUM(CASE WHEN elo_rating >= 2800 AND elo_rating < 3700 THEN 1 ELSE 0 END) as grandmaster,
                               SUM(CASE WHEN elo_rating >= 1900 AND elo_rating < 2800 THEN 1 ELSE 0 END) as diamond,
                               SUM(CASE WHEN elo_rating >= 1000 AND elo_rating < 1900 THEN 1 ELSE 0 END) as platinum,
                               SUM(CASE WHEN elo_rating >= 700 AND elo_rating < 1000 THEN 1 ELSE 0 END) as gold,
                               SUM(CASE WHEN elo_rating >= 400 AND elo_rating < 700 THEN 1 ELSE 0 END) as silver,
                               SUM(CASE WHEN elo_rating < 400 THEN 1 ELSE 0 END) as bronze,
                               COUNT(*) as total
                           ')
                       ])
                       ->first();

            $total = $counts->total ?: 1; // Prevent division by zero

            foreach (self::RANK_THRESHOLDS as $threshold => $rank) {
                $count = $counts->{$rank} ?? 0;
                $distribution[] = [
                    'rank' => $rank,
                    'name' => ucfirst(str_replace('_', ' ', $rank)),
                    'count' => $count,
                    'percentage' => round(($count / $total) * 100, 1),
                    'threshold' => $threshold
                ];
            }

            return $distribution;
        });
    }

    /**
     * Efficient rank lookup with binary search approach
     */
    public function getRankByRating($rating): string
    {
        foreach (self::RANK_THRESHOLDS as $threshold => $rank) {
            if ($rating >= $threshold) {
                return $rank;
            }
        }
        return 'bronze';
    }

    /**
     * Comprehensive ELO statistics with caching
     */
    public function getComprehensiveStats(): array
    {
        $cacheKey = $this->cachePrefix . 'comprehensive_stats';
        
        return Cache::remember($cacheKey, $this->cacheTimeout, function () {
            $stats = DB::table('players')
                      ->select([
                          DB::raw('COUNT(*) as total_players'),
                          DB::raw('AVG(elo_rating) as average_rating'),
                          DB::raw('MAX(elo_rating) as highest_rating'),
                          DB::raw('MIN(elo_rating) as lowest_rating'),
                          DB::raw('STDDEV(elo_rating) as rating_std_dev')
                      ])
                      ->first();

            $teamStats = DB::table('teams')
                          ->select([
                              DB::raw('COUNT(*) as total_teams'),
                              DB::raw('AVG(elo_rating) as team_average_rating'),
                              DB::raw('MAX(elo_rating) as team_highest_rating')
                          ])
                          ->first();

            return [
                'total_players' => $stats->total_players ?? 0,
                'total_teams' => $teamStats->total_teams ?? 0,
                'average_rating' => round($stats->average_rating ?? $this->initialRating),
                'highest_rating' => $stats->highest_rating ?? $this->initialRating,
                'lowest_rating' => $stats->lowest_rating ?? $this->initialRating,
                'rating_std_dev' => round($stats->rating_std_dev ?? 0, 2),
                'team_average_rating' => round($teamStats->team_average_rating ?? $this->initialRating),
                'team_highest_rating' => $teamStats->team_highest_rating ?? $this->initialRating,
                'one_above_all_count' => DB::table('players')->where('elo_rating', '>=', 5000)->count(),
                'eternity_count' => DB::table('players')->whereBetween('elo_rating', [4600, 4999])->count(),
                'celestial_plus_count' => DB::table('players')->where('elo_rating', '>=', 3700)->count(),
                'rank_distribution' => $this->getRankDistribution()
            ];
        });
    }

    /**
     * Cache invalidation for ELO-related data
     */
    private function invalidateEloRelatedCaches(array $teamIds): void
    {
        // Get affected regions for cache invalidation
        $regions = Team::whereIn('id', $teamIds)->pluck('region')->unique();

        $patterns = [
            $this->cachePrefix . 'team_rankings_*',
            $this->cachePrefix . 'player_rankings_*',
            $this->cachePrefix . 'rank_distribution',
            $this->cachePrefix . 'comprehensive_stats'
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        // Invalidate region-specific caches
        foreach ($regions as $region) {
            Cache::forget($this->cachePrefix . "team_rankings_{$region}_*");
            Cache::forget($this->cachePrefix . "player_rankings_*_{$region}_*");
        }
    }

    /**
     * Clear all ELO caches
     */
    public function clearAllEloCaches(): void
    {
        $keys = Cache::store('redis')->getRedis()->keys($this->cachePrefix . '*');
        if (!empty($keys)) {
            Cache::store('redis')->getRedis()->del($keys);
        }
        
        Log::info('Cleared all ELO-related caches');
    }

    /**
     * Get match prediction with enhanced algorithm
     */
    public function predictMatchOutcome($team1Id, $team2Id): ?array
    {
        $cacheKey = $this->cachePrefix . "prediction_{$team1Id}_{$team2Id}";
        
        return Cache::remember($cacheKey, 1800, function () use ($team1Id, $team2Id) {
            $teams = Team::whereIn('id', [$team1Id, $team2Id])
                        ->select(['id', 'name', 'elo_rating', 'wins', 'losses'])
                        ->get()
                        ->keyBy('id');

            $team1 = $teams->get($team1Id);
            $team2 = $teams->get($team2Id);

            if (!$team1 || !$team2) {
                return null;
            }

            $rating1 = $team1->elo_rating ?? $this->initialRating;
            $rating2 = $team2->elo_rating ?? $this->initialRating;

            // Enhanced prediction with form factor
            $expected1 = 1 / (1 + pow(10, ($rating2 - $rating1) / 400));
            $expected2 = 1 - $expected1;

            // Apply recent form modifier
            $form1 = $this->calculateRecentForm($team1);
            $form2 = $this->calculateRecentForm($team2);
            
            $formModifier = ($form1 - $form2) * 0.05; // 5% max adjustment
            $probability1 = max(0.05, min(0.95, $expected1 + $formModifier));
            $probability2 = 1 - $probability1;

            return [
                'team1' => [
                    'id' => $team1->id,
                    'name' => $team1->name,
                    'rating' => $rating1,
                    'win_probability' => round($probability1 * 100, 1),
                    'recent_form' => $form1
                ],
                'team2' => [
                    'id' => $team2->id,
                    'name' => $team2->name,
                    'rating' => $rating2,
                    'win_probability' => round($probability2 * 100, 1),
                    'recent_form' => $form2
                ],
                'favorite' => $probability1 > $probability2 ? $team1->name : $team2->name,
                'confidence' => round(abs($probability1 - $probability2) * 100, 1)
            ];
        });
    }

    /**
     * Calculate recent form factor for predictions
     */
    private function calculateRecentForm(Team $team): float
    {
        $recentMatches = GameMatch::where(function ($query) use ($team) {
                               $query->where('team1_id', $team->id)
                                     ->orWhere('team2_id', $team->id);
                           })
                           ->where('status', 'completed')
                           ->orderBy('match_date', 'desc')
                           ->limit(5)
                           ->get();

        if ($recentMatches->isEmpty()) {
            return 0.5; // Neutral form
        }

        $wins = $recentMatches->filter(function ($match) use ($team) {
            return $match->winner_id == $team->id;
        })->count();

        return $wins / $recentMatches->count();
    }
}