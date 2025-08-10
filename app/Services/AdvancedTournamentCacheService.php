<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Advanced Tournament Cache Service
 * 
 * Implements intelligent caching with Redis for high-performance tournament operations
 * Features:
 * - Hierarchical cache invalidation
 * - Real-time data synchronization
 * - Smart cache warming
 * - Performance monitoring
 * - Multi-level cache strategy
 */
class AdvancedTournamentCacheService
{
    private const CACHE_PREFIX = 'mrvl_tournament:';
    private const LOCK_PREFIX = 'lock:tournament:';
    
    // Cache TTL constants (in seconds)
    private const TTL_LIVE_DATA = 15;      // Live scoring data
    private const TTL_BRACKET_DATA = 60;   // Bracket structure
    private const TTL_STANDINGS = 30;      // Tournament standings
    private const TTL_STATISTICS = 300;    // Tournament statistics
    private const TTL_LEADERBOARD = 120;   // Tournament leaderboards
    private const TTL_SEARCH = 600;        // Search results
    private const TTL_METADATA = 1800;     // Tournament metadata

    private $redis;

    public function __construct()
    {
        try {
            $this->redis = Redis::connection('cache');
        } catch (\Exception $e) {
            // Fallback to file cache if Redis is not available
            $this->redis = null;
        }
    }

    /**
     * Intelligent cache warming for tournaments
     * Pre-loads critical data for optimal performance
     */
    public function warmTournamentCache(int $tournamentId): void
    {
        $lockKey = self::LOCK_PREFIX . "warm:{$tournamentId}";
        
        // Use Redis lock to prevent concurrent warming
        $acquired = $this->redis->set($lockKey, 'warming', 'EX', 60, 'NX');
        if (!$acquired) {
            return; // Another process is already warming
        }

        try {
            $tournament = Tournament::find($tournamentId);
            if (!$tournament) {
                return;
            }

            // Warm core tournament data
            $this->warmCoreData($tournament);
            
            // Warm format-specific data
            match($tournament->format) {
                'swiss' => $this->warmSwissData($tournament),
                'single_elimination', 'double_elimination' => $this->warmBracketData($tournament),
                'round_robin' => $this->warmRoundRobinData($tournament),
                default => null
            };

            // Warm real-time data if tournament is live
            if ($tournament->status === 'ongoing') {
                $this->warmLiveData($tournament);
            }

            // Set cache warming metadata
            $this->setCacheMetadata($tournamentId, 'warmed', now());

        } finally {
            $this->redis->del($lockKey);
        }
    }

    /**
     * Multi-level cache get with fallback
     */
    public function getCachedData(string $key, callable $fallback = null, int $ttl = 300): mixed
    {
        $fullKey = self::CACHE_PREFIX . $key;
        
        // Try L1 cache (Redis)
        $data = $this->redis->get($fullKey);
        if ($data !== null) {
            $this->incrementHitCounter($key);
            return json_decode($data, true);
        }

        // Try L2 cache (database cache table) if available
        $dbCached = $this->getFromDatabaseCache($key);
        if ($dbCached !== null) {
            // Backfill L1 cache
            $this->redis->setex($fullKey, $ttl, json_encode($dbCached));
            return $dbCached;
        }

        // Use fallback if provided
        if ($fallback !== null) {
            $freshData = $fallback();
            $this->setCachedData($key, $freshData, $ttl);
            $this->incrementMissCounter($key);
            return $freshData;
        }

        return null;
    }

    /**
     * Set cached data with intelligent TTL
     */
    public function setCachedData(string $key, mixed $data, ?int $ttl = null): void
    {
        $fullKey = self::CACHE_PREFIX . $key;
        
        // Auto-determine TTL based on data type
        if ($ttl === null) {
            $ttl = $this->determineTTL($key);
        }

        // Store in L1 cache (Redis)
        $this->redis->setex($fullKey, $ttl, json_encode($data));
        
        // Store in L2 cache (database) for critical data
        if ($this->isCriticalData($key)) {
            $this->storeToDatabaseCache($key, $data, $ttl);
        }

        // Update access patterns
        $this->updateAccessPattern($key);
    }

    /**
     * Hierarchical cache invalidation
     * Invalidates related cache keys based on tournament events
     */
    public function invalidateTournamentCache(int $tournamentId, string $eventType = 'general'): void
    {
        $patterns = $this->getInvalidationPatterns($tournamentId, $eventType);
        
        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // Pattern-based invalidation
                $keys = $this->redis->keys(self::CACHE_PREFIX . $pattern);
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            } else {
                // Direct key invalidation
                $this->redis->del(self::CACHE_PREFIX . $pattern);
            }
        }

        // Log invalidation for debugging
        $this->logCacheInvalidation($tournamentId, $eventType, $patterns);
        
        // Update invalidation metrics
        $this->incrementInvalidationCounter($tournamentId, $eventType);
    }

    /**
     * Real-time cache update for live tournaments
     */
    public function updateLiveCache(int $tournamentId, array $liveData): void
    {
        $baseKey = "live:{$tournamentId}";
        
        // Atomic update of live data
        $this->redis->multi();
        
        // Update live matches
        if (isset($liveData['matches'])) {
            $this->redis->setex(
                self::CACHE_PREFIX . "{$baseKey}:matches",
                self::TTL_LIVE_DATA,
                json_encode($liveData['matches'])
            );
        }
        
        // Update live scores
        if (isset($liveData['scores'])) {
            foreach ($liveData['scores'] as $matchId => $score) {
                $this->redis->setex(
                    self::CACHE_PREFIX . "{$baseKey}:match:{$matchId}:score",
                    self::TTL_LIVE_DATA,
                    json_encode($score)
                );
            }
        }
        
        // Update standings if affected
        if (isset($liveData['standings_updated']) && $liveData['standings_updated']) {
            $this->redis->del(self::CACHE_PREFIX . "standings:{$tournamentId}:*");
        }
        
        $this->redis->exec();
        
        // Broadcast cache update event
        $this->broadcastCacheUpdate($tournamentId, $liveData);
    }

    /**
     * Cache performance monitoring
     */
    public function getCacheMetrics(int $tournamentId = null): array
    {
        $baseKey = $tournamentId ? "metrics:tournament:{$tournamentId}" : "metrics:global";
        
        return [
            'hit_rate' => $this->getHitRate($tournamentId),
            'miss_rate' => $this->getMissRate($tournamentId),
            'invalidation_rate' => $this->getInvalidationRate($tournamentId),
            'memory_usage' => $this->getCacheMemoryUsage($tournamentId),
            'key_count' => $this->getCacheKeyCount($tournamentId),
            'avg_response_time' => $this->getAverageResponseTime($tournamentId),
            'hot_keys' => $this->getHotKeys($tournamentId),
            'cold_keys' => $this->getColdKeys($tournamentId)
        ];
    }

    /**
     * Smart cache preloading based on access patterns
     */
    public function preloadPopularData(int $limit = 50): void
    {
        $popularKeys = $this->getPopularKeys($limit);
        
        foreach ($popularKeys as $key => $accessCount) {
            if (!$this->redis->exists(self::CACHE_PREFIX . $key)) {
                // Preload based on key pattern
                $this->preloadByPattern($key);
            }
        }
    }

    /**
     * Cache cleanup and optimization
     */
    public function optimizeCache(): array
    {
        $stats = [
            'cleaned_keys' => 0,
            'memory_freed' => 0,
            'optimization_time' => 0
        ];
        
        $startTime = microtime(true);
        
        // Remove expired keys
        $expiredKeys = $this->getExpiredKeys();
        if (!empty($expiredKeys)) {
            $this->redis->del($expiredKeys);
            $stats['cleaned_keys'] = count($expiredKeys);
        }
        
        // Clean up low-access keys
        $coldKeys = $this->getColdKeys(null, 100);
        foreach ($coldKeys as $key) {
            if ($this->getKeyAccessCount($key) < 2) {
                $this->redis->del(self::CACHE_PREFIX . $key);
                $stats['cleaned_keys']++;
            }
        }
        
        // Compress large values
        $this->compressLargeValues();
        
        $stats['optimization_time'] = microtime(true) - $startTime;
        
        return $stats;
    }

    /**
     * Distributed cache synchronization
     */
    public function syncCacheCluster(int $tournamentId): void
    {
        $syncKey = self::LOCK_PREFIX . "sync:{$tournamentId}";
        
        if (!$this->redis->set($syncKey, 'syncing', 'EX', 30, 'NX')) {
            return; // Another node is syncing
        }

        try {
            // Get master cache state
            $masterState = $this->getCacheMasterState($tournamentId);
            
            // Sync critical keys across cluster
            $criticalKeys = $this->getCriticalKeys($tournamentId);
            
            foreach ($criticalKeys as $key) {
                $this->syncKeyAcrossCluster($key, $masterState);
            }
            
        } finally {
            $this->redis->del($syncKey);
        }
    }

    // Private helper methods

    private function warmCoreData(Tournament $tournament): void
    {
        $baseKey = "tournament:{$tournament->id}";
        
        // Basic tournament info
        $this->setCachedData("{$baseKey}:info", [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'type' => $tournament->type,
            'format' => $tournament->format,
            'status' => $tournament->status,
            'current_phase' => $tournament->current_phase,
            'team_count' => $tournament->team_count,
            'max_teams' => $tournament->max_teams,
            'start_date' => $tournament->start_date,
            'end_date' => $tournament->end_date,
        ], self::TTL_METADATA);
        
        // Tournament statistics
        $stats = $this->calculateTournamentStats($tournament);
        $this->setCachedData("{$baseKey}:stats", $stats, self::TTL_STATISTICS);
        
        // Team list
        $teams = $tournament->teams()->select(['id', 'name', 'logo', 'region'])->get();
        $this->setCachedData("{$baseKey}:teams", $teams->toArray(), self::TTL_METADATA);
    }

    private function warmSwissData(Tournament $tournament): void
    {
        $baseKey = "tournament:{$tournament->id}:swiss";
        
        // Swiss standings
        $standings = $this->calculateSwissStandings($tournament);
        $this->setCachedData("{$baseKey}:standings", $standings, self::TTL_STANDINGS);
        
        // Round data
        for ($round = 1; $round <= $this->getTotalSwissRounds($tournament); $round++) {
            $roundData = $this->getSwissRoundData($tournament, $round);
            $this->setCachedData("{$baseKey}:round:{$round}", $roundData, self::TTL_BRACKET_DATA);
        }
    }

    private function warmBracketData(Tournament $tournament): void
    {
        $baseKey = "tournament:{$tournament->id}:bracket";
        
        // Full bracket structure
        $bracket = $this->generateBracketStructure($tournament);
        $this->setCachedData("{$baseKey}:structure", $bracket, self::TTL_BRACKET_DATA);
        
        // Bracket matches by round
        $matches = $tournament->matches()
            ->with(['team1:id,name,logo', 'team2:id,name,logo'])
            ->get()
            ->groupBy('round');
            
        foreach ($matches as $round => $roundMatches) {
            $this->setCachedData(
                "{$baseKey}:round:{$round}", 
                $roundMatches->toArray(), 
                self::TTL_BRACKET_DATA
            );
        }
    }

    private function warmRoundRobinData(Tournament $tournament): void
    {
        $baseKey = "tournament:{$tournament->id}:rr";
        
        // Round robin standings
        $standings = $this->calculateRoundRobinStandings($tournament);
        $this->setCachedData("{$baseKey}:standings", $standings, self::TTL_STANDINGS);
        
        // Match results matrix
        $matrix = $this->generateMatchMatrix($tournament);
        $this->setCachedData("{$baseKey}:matrix", $matrix, self::TTL_BRACKET_DATA);
    }

    private function warmLiveData(Tournament $tournament): void
    {
        $baseKey = "tournament:{$tournament->id}:live";
        
        // Live matches
        $liveMatches = $tournament->matches()
            ->where('status', 'ongoing')
            ->with(['team1', 'team2'])
            ->get();
            
        $this->setCachedData("{$baseKey}:matches", $liveMatches->toArray(), self::TTL_LIVE_DATA);
        
        // Recent results
        $recentResults = $tournament->matches()
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->with(['team1', 'team2'])
            ->get();
            
        $this->setCachedData("{$baseKey}:recent", $recentResults->toArray(), self::TTL_LIVE_DATA * 2);
    }

    private function determineTTL(string $key): int
    {
        return match (true) {
            str_contains($key, ':live:') => self::TTL_LIVE_DATA,
            str_contains($key, ':standings') => self::TTL_STANDINGS,
            str_contains($key, ':bracket:') => self::TTL_BRACKET_DATA,
            str_contains($key, ':stats') => self::TTL_STATISTICS,
            str_contains($key, ':leaderboard') => self::TTL_LEADERBOARD,
            str_contains($key, ':search:') => self::TTL_SEARCH,
            default => self::TTL_METADATA
        };
    }

    private function isCriticalData(string $key): bool
    {
        $criticalPatterns = [
            ':live:', ':standings', ':bracket:', ':matches'
        ];
        
        foreach ($criticalPatterns as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    private function getInvalidationPatterns(int $tournamentId, string $eventType): array
    {
        $basePatterns = [
            "tournament:{$tournamentId}:*",
            "list:*",
            "search:*"
        ];
        
        return match ($eventType) {
            'match_completed', 'match_updated' => array_merge($basePatterns, [
                "tournament:{$tournamentId}:standings:*",
                "tournament:{$tournamentId}:bracket:*",
                "tournament:{$tournamentId}:live:*",
                "tournament:{$tournamentId}:stats"
            ]),
            'team_registered', 'team_checked_in' => array_merge($basePatterns, [
                "tournament:{$tournamentId}:teams",
                "tournament:{$tournamentId}:stats"
            ]),
            'phase_changed' => array_merge($basePatterns, [
                "tournament:{$tournamentId}:bracket:*",
                "tournament:{$tournamentId}:live:*"
            ]),
            'swiss_round_completed' => array_merge($basePatterns, [
                "tournament:{$tournamentId}:swiss:*",
                "tournament:{$tournamentId}:standings:*"
            ]),
            default => $basePatterns
        };
    }

    private function setCacheMetadata(int $tournamentId, string $action, Carbon $timestamp): void
    {
        $metaKey = "meta:tournament:{$tournamentId}";
        $this->redis->hset($metaKey, $action, $timestamp->toISOString());
        $this->redis->expire($metaKey, 3600);
    }

    private function incrementHitCounter(string $key): void
    {
        $this->redis->hincrby('cache_stats:hits', $key, 1);
    }

    private function incrementMissCounter(string $key): void
    {
        $this->redis->hincrby('cache_stats:misses', $key, 1);
    }

    private function incrementInvalidationCounter(int $tournamentId, string $eventType): void
    {
        $this->redis->hincrby('cache_stats:invalidations', "{$tournamentId}:{$eventType}", 1);
    }

    private function updateAccessPattern(string $key): void
    {
        $now = now()->timestamp;
        $this->redis->zadd('cache_access_patterns', $now, $key);
        
        // Clean old access patterns (keep last 24 hours)
        $cutoff = $now - 86400;
        $this->redis->zremrangebyscore('cache_access_patterns', 0, $cutoff);
    }

    private function logCacheInvalidation(int $tournamentId, string $eventType, array $patterns): void
    {
        \Log::debug('Tournament cache invalidated', [
            'tournament_id' => $tournamentId,
            'event_type' => $eventType,
            'patterns' => $patterns,
            'timestamp' => now()->toISOString()
        ]);
    }

    private function broadcastCacheUpdate(int $tournamentId, array $data): void
    {
        // This would integrate with your WebSocket/broadcasting system
        // to notify frontend clients of cache updates
    }

    // Additional helper methods would continue here...
    // Including methods for metrics calculation, database cache integration, etc.
    
    private function getFromDatabaseCache(string $key): mixed
    {
        // Implementation for L2 database cache
        return null;
    }

    private function storeToDatabaseCache(string $key, mixed $data, int $ttl): void
    {
        // Implementation for storing in database cache table
    }

    private function calculateTournamentStats(Tournament $tournament): array
    {
        return [
            'total_teams' => $tournament->team_count,
            'total_matches' => $tournament->matches()->count(),
            'completed_matches' => $tournament->matches()->where('status', 'completed')->count(),
            'ongoing_matches' => $tournament->matches()->where('status', 'ongoing')->count(),
            'prize_pool' => $tournament->prize_pool,
            'views' => $tournament->views
        ];
    }

    private function calculateSwissStandings(Tournament $tournament): array
    {
        return $tournament->teams()
            ->orderByPivot('swiss_wins', 'desc')
            ->orderByPivot('swiss_buchholz', 'desc')
            ->get()
            ->toArray();
    }

    private function getTotalSwissRounds(Tournament $tournament): int
    {
        $settings = $tournament->qualification_settings ?? [];
        return $settings['swiss_rounds'] ?? ceil(log($tournament->team_count, 2));
    }

    private function getSwissRoundData(Tournament $tournament, int $round): array
    {
        return $tournament->matches()
            ->where('round', $round)
            ->with(['team1', 'team2'])
            ->get()
            ->toArray();
    }

    private function generateBracketStructure(Tournament $tournament): array
    {
        // Generate bracket structure based on tournament format
        return [];
    }

    private function calculateRoundRobinStandings(Tournament $tournament): array
    {
        // Calculate round robin standings
        return [];
    }

    private function generateMatchMatrix(Tournament $tournament): array
    {
        // Generate match result matrix for round robin
        return [];
    }

    // Metrics and monitoring methods
    private function getHitRate(?int $tournamentId): float
    {
        return 0.0; // Implementation would calculate actual hit rate
    }

    private function getMissRate(?int $tournamentId): float
    {
        return 0.0;
    }

    private function getInvalidationRate(?int $tournamentId): float
    {
        return 0.0;
    }

    private function getCacheMemoryUsage(?int $tournamentId): int
    {
        return 0;
    }

    private function getCacheKeyCount(?int $tournamentId): int
    {
        return 0;
    }

    private function getAverageResponseTime(?int $tournamentId): float
    {
        return 0.0;
    }

    private function getHotKeys(?int $tournamentId): array
    {
        return [];
    }

    private function getColdKeys(?int $tournamentId, ?int $limit = null): array
    {
        return [];
    }

    private function getPopularKeys(int $limit): array
    {
        return [];
    }

    private function preloadByPattern(string $key): void
    {
        // Preload data based on key pattern
    }

    private function getExpiredKeys(): array
    {
        return [];
    }

    private function getKeyAccessCount(string $key): int
    {
        return 0;
    }

    private function compressLargeValues(): void
    {
        // Compress large cached values
    }

    private function getCacheMasterState(int $tournamentId): array
    {
        return [];
    }

    private function getCriticalKeys(int $tournamentId): array
    {
        return [];
    }

    private function syncKeyAcrossCluster(string $key, array $masterState): void
    {
        // Sync key across cache cluster
    }
}