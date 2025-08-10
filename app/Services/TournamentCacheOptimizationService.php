<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Tournament Cache Optimization Service
 * 
 * Implements advanced caching strategies:
 * - Redis for live bracket states
 * - Materialized views simulation for tournament standings
 * - Query result caching for complex calculations
 * - Denormalization for frequently accessed data
 */
class TournamentCacheOptimizationService
{
    private const REDIS_PREFIX = 'mrvl:tournament:';
    private const MATERIALIZED_VIEW_TTL = 1800; // 30 minutes
    private const LIVE_STATE_TTL = 30; // 30 seconds
    private const DENORMALIZED_TTL = 3600; // 1 hour

    /**
     * Redis-based live bracket state management
     */
    public function cacheLiveBracketState(int $eventId, array $bracketData): void
    {
        $key = self::REDIS_PREFIX . "live_bracket:{$eventId}";
        
        Redis::setex(
            $key,
            self::LIVE_STATE_TTL,
            json_encode([
                'timestamp' => time(),
                'event_id' => $eventId,
                'bracket_data' => $bracketData,
                'live_matches' => $this->extractLiveMatches($bracketData),
                'scores_updated' => $this->extractScoreUpdates($bracketData)
            ])
        );

        // Also cache individual match states for quick lookups
        foreach ($bracketData as $match) {
            if (in_array($match['status'], ['live', 'ongoing'])) {
                $matchKey = self::REDIS_PREFIX . "live_match:{$match['id']}";
                Redis::setex(
                    $matchKey,
                    self::LIVE_STATE_TTL,
                    json_encode([
                        'match_id' => $match['id'],
                        'team1_score' => $match['team1_score'],
                        'team2_score' => $match['team2_score'],
                        'status' => $match['status'],
                        'last_updated' => time()
                    ])
                );
            }
        }
    }

    /**
     * Get live bracket state from Redis
     */
    public function getLiveBracketState(int $eventId): ?array
    {
        $key = self::REDIS_PREFIX . "live_bracket:{$eventId}";
        $data = Redis::get($key);
        
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Create materialized view for tournament standings
     */
    public function refreshTournamentStandingsView(int $eventId): void
    {
        $standingsData = $this->calculateTournamentStandings($eventId);
        
        // Store as cached materialized view
        $key = self::REDIS_PREFIX . "standings_view:{$eventId}";
        Redis::setex(
            $key,
            self::MATERIALIZED_VIEW_TTL,
            json_encode([
                'generated_at' => time(),
                'event_id' => $eventId,
                'standings' => $standingsData,
                'metadata' => [
                    'total_teams' => count($standingsData),
                    'completed_matches' => $this->getCompletedMatchesCount($eventId),
                    'ongoing_matches' => $this->getOngoingMatchesCount($eventId),
                    'next_refresh' => time() + self::MATERIALIZED_VIEW_TTL
                ]
            ])
        );

        // Create indexes for common queries
        $this->createStandingsIndexes($eventId, $standingsData);
    }

    /**
     * Get tournament standings from materialized view
     */
    public function getTournamentStandingsView(int $eventId): ?array
    {
        $key = self::REDIS_PREFIX . "standings_view:{$eventId}";
        $data = Redis::get($key);
        
        if (!$data) {
            // Refresh if expired
            $this->refreshTournamentStandingsView($eventId);
            $data = Redis::get($key);
        }
        
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Cache complex bracket calculations
     */
    public function cacheSwissPairingCalculation(int $bracketId, int $round, array $pairings): void
    {
        $key = self::REDIS_PREFIX . "swiss_pairings:{$bracketId}:{$round}";
        
        Redis::setex(
            $key,
            300, // 5 minutes - pairings change frequently
            json_encode([
                'bracket_id' => $bracketId,
                'round' => $round,
                'pairings' => $pairings,
                'calculated_at' => time(),
                'pairing_quality' => $this->calculatePairingQuality($pairings)
            ])
        );
    }

    /**
     * Get cached Swiss pairings
     */
    public function getCachedSwissPairings(int $bracketId, int $round): ?array
    {
        $key = self::REDIS_PREFIX . "swiss_pairings:{$bracketId}:{$round}";
        $data = Redis::get($key);
        
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Denormalized team performance cache
     */
    public function cacheDenormalizedTeamPerformance(int $teamId): void
    {
        $performance = $this->calculateTeamPerformance($teamId);
        
        $key = self::REDIS_PREFIX . "team_performance:{$teamId}";
        Redis::setex(
            $key,
            self::DENORMALIZED_TTL,
            json_encode([
                'team_id' => $teamId,
                'last_30_days' => $performance['last_30_days'],
                'all_time' => $performance['all_time'],
                'by_format' => $performance['by_format'],
                'head_to_head' => $performance['head_to_head'],
                'map_statistics' => $performance['map_statistics'],
                'cached_at' => time()
            ])
        );
    }

    /**
     * Get denormalized team performance
     */
    public function getDenormalizedTeamPerformance(int $teamId): ?array
    {
        $key = self::REDIS_PREFIX . "team_performance:{$teamId}";
        $data = Redis::get($key);
        
        if (!$data) {
            $this->cacheDenormalizedTeamPerformance($teamId);
            $data = Redis::get($key);
        }
        
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Cache R#M# notation lookups for quick access
     */
    public function cacheMatchNotationLookup(int $eventId): void
    {
        $matches = DB::select("
            SELECT 
                bm.id,
                bm.match_id,
                bm.round_number,
                bm.match_number,
                bs.type as stage_type,
                CONCAT(
                    CASE bs.type
                        WHEN 'upper_bracket' THEN 'UB'
                        WHEN 'lower_bracket' THEN 'LB'
                        WHEN 'swiss' THEN 'SW'
                        ELSE 'R'
                    END,
                    bm.round_number,
                    '-',
                    bm.match_number
                ) as notation
            FROM bracket_matches bm
            JOIN bracket_stages bs ON bm.bracket_stage_id = bs.id
            WHERE bm.event_id = ?
        ", [$eventId]);

        $notationMap = [];
        foreach ($matches as $match) {
            $notationMap[$match->notation] = [
                'match_id' => $match->id,
                'internal_match_id' => $match->match_id,
                'round_number' => $match->round_number,
                'match_number' => $match->match_number,
                'stage_type' => $match->stage_type
            ];
        }

        $key = self::REDIS_PREFIX . "match_notation:{$eventId}";
        Redis::setex(
            $key,
            self::MATERIALIZED_VIEW_TTL,
            json_encode($notationMap)
        );
    }

    /**
     * Get match by R#M# notation
     */
    public function getMatchByNotation(int $eventId, string $notation): ?array
    {
        $key = self::REDIS_PREFIX . "match_notation:{$eventId}";
        $data = Redis::get($key);
        
        if (!$data) {
            $this->cacheMatchNotationLookup($eventId);
            $data = Redis::get($key);
        }
        
        $notationMap = json_decode($data, true);
        return $notationMap[$notation] ?? null;
    }

    /**
     * Cache aggregated tournament statistics
     */
    public function cacheAggregatedTournamentStats(int $eventId): void
    {
        $stats = DB::select("
            SELECT 
                COUNT(DISTINCT bm.id) as total_matches,
                COUNT(DISTINCT CASE WHEN bm.status = 'completed' THEN bm.id END) as completed_matches,
                COUNT(DISTINCT CASE WHEN bm.status IN ('live', 'ongoing') THEN bm.id END) as live_matches,
                COUNT(DISTINCT CASE WHEN bm.status = 'pending' THEN bm.id END) as pending_matches,
                COUNT(DISTINCT bm.team1_id) + COUNT(DISTINCT bm.team2_id) as total_teams,
                AVG(CASE WHEN bm.status = 'completed' THEN bm.team1_score + bm.team2_score END) as avg_games_per_match,
                SUM(CASE WHEN bm.status = 'completed' THEN bm.team1_score + bm.team2_score END) as total_games_played,
                COUNT(DISTINCT bg.id) as total_individual_games,
                AVG(CASE WHEN bg.duration_seconds IS NOT NULL THEN bg.duration_seconds END) as avg_game_duration,
                (SELECT COUNT(*) FROM events WHERE id = ?) as tournament_exists
            FROM bracket_matches bm
            LEFT JOIN bracket_games bg ON bm.id = bg.bracket_match_id
            WHERE bm.event_id = ?
        ", [$eventId, $eventId]);

        $key = self::REDIS_PREFIX . "tournament_stats:{$eventId}";
        Redis::setex(
            $key,
            600, // 10 minutes
            json_encode([
                'event_id' => $eventId,
                'stats' => $stats[0] ?? null,
                'last_updated' => time()
            ])
        );
    }

    /**
     * Get cached tournament statistics
     */
    public function getCachedTournamentStats(int $eventId): ?array
    {
        $key = self::REDIS_PREFIX . "tournament_stats:{$eventId}";
        $data = Redis::get($key);
        
        if (!$data) {
            $this->cacheAggregatedTournamentStats($eventId);
            $data = Redis::get($key);
        }
        
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Invalidate tournament caches when data changes
     */
    public function invalidateTournamentCaches(int $eventId, array $contexts = ['all']): void
    {
        $patterns = [];
        
        if (in_array('all', $contexts) || in_array('bracket', $contexts)) {
            $patterns[] = "live_bracket:{$eventId}";
            $patterns[] = "standings_view:{$eventId}";
            $patterns[] = "match_notation:{$eventId}";
        }
        
        if (in_array('all', $contexts) || in_array('stats', $contexts)) {
            $patterns[] = "tournament_stats:{$eventId}";
        }
        
        if (in_array('all', $contexts) || in_array('matches', $contexts)) {
            // Invalidate all live match caches for this event
            $this->invalidateLiveMatchCaches($eventId);
        }

        foreach ($patterns as $pattern) {
            Redis::del(self::REDIS_PREFIX . $pattern);
        }

        // Log cache invalidation for monitoring
        Log::info("Tournament caches invalidated", [
            'event_id' => $eventId,
            'contexts' => $contexts,
            'patterns_cleared' => count($patterns)
        ]);
    }

    /**
     * Warm up tournament caches for better performance
     */
    public function warmupTournamentCaches(int $eventId): void
    {
        try {
            // Warm up in order of dependency
            $this->cacheMatchNotationLookup($eventId);
            $this->refreshTournamentStandingsView($eventId);
            $this->cacheAggregatedTournamentStats($eventId);
            
            // Get live bracket data and cache it
            $liveMatches = DB::select("
                SELECT * FROM bracket_matches 
                WHERE event_id = ? AND status IN ('live', 'ongoing', 'ready')
            ", [$eventId]);
            
            if (!empty($liveMatches)) {
                $this->cacheLiveBracketState($eventId, $liveMatches);
            }
            
            Log::info("Tournament caches warmed up", ['event_id' => $eventId]);
        } catch (\Exception $e) {
            Log::error("Failed to warm up tournament caches", [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cache performance monitoring
     */
    public function getCachePerformanceMetrics(): array
    {
        $keys = Redis::keys(self::REDIS_PREFIX . '*');
        $totalKeys = count($keys);
        $totalMemory = 0;
        $hitRate = 0;

        // Get memory usage for tournament caches
        if ($totalKeys > 0) {
            foreach ($keys as $key) {
                $memory = Redis::memory('usage', $key);
                if ($memory) {
                    $totalMemory += $memory;
                }
            }
        }

        return [
            'total_cache_keys' => $totalKeys,
            'total_memory_bytes' => $totalMemory,
            'total_memory_mb' => round($totalMemory / (1024 * 1024), 2),
            'cache_prefix' => self::REDIS_PREFIX,
            'active_tournaments' => $this->getActiveTournamentCount(),
            'avg_memory_per_tournament' => $this->getActiveTournamentCount() > 0 
                ? round($totalMemory / $this->getActiveTournamentCount() / (1024 * 1024), 2) 
                : 0
        ];
    }

    // Private helper methods

    private function extractLiveMatches(array $bracketData): array
    {
        return array_filter($bracketData, function($match) {
            return in_array($match['status'], ['live', 'ongoing']);
        });
    }

    private function extractScoreUpdates(array $bracketData): array
    {
        $updates = [];
        foreach ($bracketData as $match) {
            if (isset($match['team1_score']) && isset($match['team2_score'])) {
                $updates[] = [
                    'match_id' => $match['id'],
                    'team1_score' => $match['team1_score'],
                    'team2_score' => $match['team2_score']
                ];
            }
        }
        return $updates;
    }

    private function calculateTournamentStandings(int $eventId): array
    {
        return DB::select("
            SELECT 
                t.id,
                t.name,
                t.logo_url,
                t.region,
                COUNT(DISTINCT CASE WHEN bm.winner_id = t.id THEN bm.id END) as wins,
                COUNT(DISTINCT CASE 
                    WHEN bm.status = 'completed' 
                    AND bm.winner_id != t.id 
                    AND (bm.team1_id = t.id OR bm.team2_id = t.id)
                    THEN bm.id 
                END) as losses,
                COALESCE(bs.final_placement, 999) as placement
            FROM teams t
            LEFT JOIN bracket_matches bm ON (t.id = bm.team1_id OR t.id = bm.team2_id)
                AND bm.event_id = ?
            LEFT JOIN bracket_standings bs ON t.id = bs.team_id AND bs.event_id = ?
            WHERE EXISTS (
                SELECT 1 FROM bracket_matches bm2 
                WHERE bm2.event_id = ? 
                AND (bm2.team1_id = t.id OR bm2.team2_id = t.id)
            )
            GROUP BY t.id, t.name, t.logo_url, t.region, bs.final_placement
            ORDER BY placement, wins DESC
        ", [$eventId, $eventId, $eventId]);
    }

    private function createStandingsIndexes(int $eventId, array $standingsData): void
    {
        // Create Redis sorted sets for quick rankings
        $winsKey = self::REDIS_PREFIX . "rankings_by_wins:{$eventId}";
        $placementKey = self::REDIS_PREFIX . "rankings_by_placement:{$eventId}";

        Redis::del($winsKey);
        Redis::del($placementKey);

        foreach ($standingsData as $standing) {
            Redis::zadd($winsKey, [$standing['id'] => $standing['wins']]);
            Redis::zadd($placementKey, [$standing['id'] => $standing['placement']]);
        }

        Redis::expire($winsKey, self::MATERIALIZED_VIEW_TTL);
        Redis::expire($placementKey, self::MATERIALIZED_VIEW_TTL);
    }

    private function calculatePairingQuality(array $pairings): float
    {
        if (empty($pairings)) return 0.0;

        $totalQuality = 0;
        foreach ($pairings as $pairing) {
            // Lower win difference and rank difference = higher quality
            $quality = 1.0 / (1.0 + $pairing['win_difference'] + ($pairing['rank_difference'] * 0.1));
            $totalQuality += $quality;
        }

        return $totalQuality / count($pairings);
    }

    private function calculateTeamPerformance(int $teamId): array
    {
        // This would contain complex performance calculations
        // Simplified version for demonstration
        return [
            'last_30_days' => ['wins' => 0, 'losses' => 0],
            'all_time' => ['wins' => 0, 'losses' => 0],
            'by_format' => [],
            'head_to_head' => [],
            'map_statistics' => []
        ];
    }

    private function getCompletedMatchesCount(int $eventId): int
    {
        return DB::scalar("
            SELECT COUNT(*) FROM bracket_matches 
            WHERE event_id = ? AND status = 'completed'
        ", [$eventId]);
    }

    private function getOngoingMatchesCount(int $eventId): int
    {
        return DB::scalar("
            SELECT COUNT(*) FROM bracket_matches 
            WHERE event_id = ? AND status IN ('live', 'ongoing')
        ", [$eventId]);
    }

    private function invalidateLiveMatchCaches(int $eventId): void
    {
        $matchIds = DB::select("
            SELECT id FROM bracket_matches WHERE event_id = ?
        ", [$eventId]);

        foreach ($matchIds as $match) {
            Redis::del(self::REDIS_PREFIX . "live_match:{$match->id}");
        }
    }

    private function getActiveTournamentCount(): int
    {
        return DB::scalar("
            SELECT COUNT(DISTINCT event_id) 
            FROM bracket_matches 
            WHERE status IN ('pending', 'ready', 'live', 'ongoing')
        ");
    }
}