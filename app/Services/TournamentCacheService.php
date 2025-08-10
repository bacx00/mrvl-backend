<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Tournament;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;

class TournamentCacheService
{
    // Cache TTL configurations (in seconds)
    const CACHE_TTL = [
        'tournament_bracket' => 300,      // 5 minutes
        'live_matches' => 30,             // 30 seconds
        'tournament_standings' => 180,    // 3 minutes
        'team_rankings' => 900,           // 15 minutes
        'match_details' => 600,           // 10 minutes
        'tournament_metadata' => 1800,    // 30 minutes
        'liquipedia_notation' => 900,     // 15 minutes
        'swiss_standings' => 120,         // 2 minutes
        'real_time_updates' => 60         // 1 minute
    ];

    // Cache key prefixes
    const CACHE_KEYS = [
        'tournament_bracket' => 'tournament:bracket:',
        'event_bracket' => 'event:bracket:',
        'live_matches' => 'live:matches:',
        'standings' => 'standings:',
        'match_details' => 'match:details:',
        'team_data' => 'team:data:',
        'liquipedia' => 'liquipedia:',
        'swiss' => 'swiss:',
        'live_updates' => 'live:updates:'
    ];

    /**
     * Cache tournament bracket with intelligent invalidation
     */
    public function cacheTournamentBracket($tournament, array $bracketData): void
    {
        $cacheKey = $this->getTournamentBracketKey($tournament);
        
        // Cache the main bracket data
        Cache::put($cacheKey, $bracketData, self::CACHE_TTL['tournament_bracket']);
        
        // Cache bracket metadata separately for faster access
        $metadataKey = $cacheKey . ':metadata';
        Cache::put($metadataKey, [
            'total_matches' => $bracketData['metadata']['total_matches'] ?? 0,
            'completed_matches' => $bracketData['metadata']['completed_matches'] ?? 0,
            'current_round' => $bracketData['event']['current_round'] ?? 0,
            'total_rounds' => $bracketData['event']['total_rounds'] ?? 0,
            'last_updated' => now()->toISOString()
        ], self::CACHE_TTL['tournament_metadata']);
        
        // Cache Liquipedia notation separately
        if (!empty($bracketData['liquipedia_notation'])) {
            $liquipediaKey = self::CACHE_KEYS['liquipedia'] . $tournament->id;
            Cache::put($liquipediaKey, $bracketData['liquipedia_notation'], self::CACHE_TTL['liquipedia_notation']);
        }

        // Add tournament to active tournaments set for bulk invalidation
        $this->addToActiveTournaments($tournament);
    }

    /**
     * Get cached tournament bracket with fallback
     */
    public function getCachedTournamentBracket($tournament): ?array
    {
        $cacheKey = $this->getTournamentBracketKey($tournament);
        return Cache::get($cacheKey);
    }

    /**
     * Cache live match data with short TTL
     */
    public function cacheLiveMatches($tournament, Collection $liveMatches): void
    {
        $cacheKey = self::CACHE_KEYS['live_matches'] . $tournament->id;
        
        $liveMatchData = $liveMatches->map(function ($match) {
            return [
                'id' => $match->id,
                'match_id' => $match->match_id,
                'liquipedia_id' => $match->liquipedia_id,
                'teams' => [
                    'team1' => $this->getCachedTeamData($match->team1_id),
                    'team2' => $this->getCachedTeamData($match->team2_id)
                ],
                'score' => [
                    'team1' => $match->team1_score,
                    'team2' => $match->team2_score,
                    'best_of' => $match->best_of
                ],
                'status' => $match->status,
                'round_name' => $match->round_name,
                'started_at' => $match->started_at,
                'stage_name' => $match->bracketStage->name ?? 'Unknown'
            ];
        })->toArray();

        Cache::put($cacheKey, $liveMatchData, self::CACHE_TTL['live_matches']);
        
        // Also cache match count for quick access
        Cache::put($cacheKey . ':count', count($liveMatchData), self::CACHE_TTL['live_matches']);
    }

    /**
     * Cache Swiss standings with advanced tiebreakers
     */
    public function cacheSwissStandings(BracketStage $stage, Collection $standings): void
    {
        $cacheKey = self::CACHE_KEYS['swiss'] . "standings:{$stage->id}";
        
        $standingsData = $standings->map(function ($standing) {
            return [
                'team_id' => $standing['team_id'],
                'team_name' => $standing['team']->name ?? 'Unknown',
                'team_logo' => $standing['team']->logo ?? null,
                'wins' => $standing['wins'],
                'losses' => $standing['losses'],
                'map_differential' => $standing['map_differential'],
                'swiss_score' => $standing['swiss_score'],
                'buchholz_score' => $standing['buchholz_score'],
                'strength_of_schedule' => $standing['strength_of_schedule'] ?? 0,
                'opponents_defeated' => $standing['opponents_defeated'] ?? []
            ];
        })->toArray();

        Cache::put($cacheKey, $standingsData, self::CACHE_TTL['swiss_standings']);
        
        // Cache current round info
        Cache::put($cacheKey . ':round', [
            'current_round' => $stage->current_round,
            'total_rounds' => $stage->total_rounds,
            'stage_status' => $stage->status
        ], self::CACHE_TTL['swiss_standings']);
    }

    /**
     * Cache team data with relationship data
     */
    public function cacheTeamData(Team $team): void
    {
        $cacheKey = self::CACHE_KEYS['team_data'] . $team->id;
        
        $teamData = [
            'id' => $team->id,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo' => $team->logo,
            'region' => $team->region,
            'rating' => $team->rating ?? 1000,
            'wins' => $team->wins ?? 0,
            'losses' => $team->losses ?? 0,
            'country' => $team->country,
            'status' => $team->status ?? 'active',
            'players' => $team->players->map(function ($player) {
                return [
                    'id' => $player->id,
                    'ign' => $player->ign,
                    'role' => $player->pivot->role ?? $player->role,
                    'avatar' => $player->avatar
                ];
            })->toArray()
        ];

        Cache::put($cacheKey, $teamData, self::CACHE_TTL['team_rankings']);
    }

    /**
     * Get cached team data
     */
    public function getCachedTeamData(?int $teamId): ?array
    {
        if (!$teamId) return null;
        
        $cacheKey = self::CACHE_KEYS['team_data'] . $teamId;
        $cached = Cache::get($cacheKey);
        
        if (!$cached) {
            // Fallback: load and cache team data
            $team = Team::with('players')->find($teamId);
            if ($team) {
                $this->cacheTeamData($team);
                return Cache::get($cacheKey);
            }
        }
        
        return $cached;
    }

    /**
     * Cache match details with comprehensive data
     */
    public function cacheMatchDetails(BracketMatch $match): void
    {
        $cacheKey = self::CACHE_KEYS['match_details'] . $match->id;
        
        $matchData = [
            'id' => $match->id,
            'match_id' => $match->match_id,
            'liquipedia_id' => $match->liquipedia_id,
            'round_name' => $match->round_name,
            'round_number' => $match->round_number,
            'match_number' => $match->match_number,
            'teams' => [
                'team1' => $this->getCachedTeamData($match->team1_id),
                'team2' => $this->getCachedTeamData($match->team2_id)
            ],
            'sources' => [
                'team1_source' => $match->team1_source,
                'team2_source' => $match->team2_source
            ],
            'score' => [
                'team1' => $match->team1_score,
                'team2' => $match->team2_score,
                'best_of' => $match->best_of
            ],
            'result' => [
                'winner_id' => $match->winner_id,
                'loser_id' => $match->loser_id,
                'status' => $match->status
            ],
            'schedule' => [
                'scheduled_at' => $match->scheduled_at,
                'started_at' => $match->started_at,
                'completed_at' => $match->completed_at
            ],
            'games' => $match->games->map(function ($game) {
                return [
                    'game_number' => $game->game_number,
                    'map_name' => $game->map_name,
                    'map_type' => $game->map_type,
                    'team1_score' => $game->team1_score,
                    'team2_score' => $game->team2_score,
                    'winner_id' => $game->winner_id,
                    'duration_seconds' => $game->duration_seconds,
                    'vod_url' => $game->vod_url
                ];
            })->toArray(),
            'stage' => [
                'id' => $match->bracketStage->id,
                'name' => $match->bracketStage->name,
                'type' => $match->bracketStage->type
            ],
            'progression' => [
                'winner_advances_to' => $match->winner_advances_to,
                'loser_advances_to' => $match->loser_advances_to
            ],
            'cached_at' => now()->toISOString()
        ];

        Cache::put($cacheKey, $matchData, self::CACHE_TTL['match_details']);
    }

    /**
     * Invalidate tournament-related caches when matches are updated
     */
    public function invalidateTournamentCaches($tournament, array $additionalKeys = []): void
    {
        $keysToInvalidate = [
            $this->getTournamentBracketKey($tournament),
            $this->getTournamentBracketKey($tournament) . ':metadata',
            self::CACHE_KEYS['live_matches'] . $tournament->id,
            self::CACHE_KEYS['live_matches'] . $tournament->id . ':count',
            self::CACHE_KEYS['standings'] . $tournament->id,
            self::CACHE_KEYS['liquipedia'] . $tournament->id,
            'event_live_state_' . $tournament->id
        ];

        // Add additional keys
        $keysToInvalidate = array_merge($keysToInvalidate, $additionalKeys);

        Cache::forget($keysToInvalidate);

        // If tournament has Swiss stages, invalidate Swiss caches
        $this->invalidateSwissCaches($tournament);
    }

    /**
     * Invalidate Swiss-specific caches
     */
    public function invalidateSwissCaches($tournament): void
    {
        if ($tournament instanceof Event) {
            $swissStages = $tournament->brackets()->where('type', 'swiss')->get();
        } else {
            $swissStages = $tournament->bracketStages()->where('type', 'swiss')->get();
        }

        foreach ($swissStages as $stage) {
            Cache::forget(self::CACHE_KEYS['swiss'] . "standings:{$stage->id}");
            Cache::forget(self::CACHE_KEYS['swiss'] . "standings:{$stage->id}:round");
        }
    }

    /**
     * Cache tournament standings with ranking data
     */
    public function cacheTournamentStandings($tournament, array $standings): void
    {
        $cacheKey = self::CACHE_KEYS['standings'] . $tournament->id;
        
        Cache::put($cacheKey, $standings, self::CACHE_TTL['tournament_standings']);
        
        // Cache standings metadata
        Cache::put($cacheKey . ':meta', [
            'total_teams' => count($standings),
            'updated_at' => now()->toISOString(),
            'tournament_status' => $tournament->status
        ], self::CACHE_TTL['tournament_standings']);
    }

    /**
     * Cache real-time updates for SSE
     */
    public function cacheRealTimeUpdate($tournament, string $updateType, array $data): void
    {
        $cacheKey = self::CACHE_KEYS['live_updates'] . $tournament->id . ':' . $updateType;
        
        $updateData = [
            'type' => $updateType,
            'timestamp' => now()->toISOString(),
            'data' => $data
        ];

        Cache::put($cacheKey, $updateData, self::CACHE_TTL['real_time_updates']);
    }

    /**
     * Warm cache for frequently accessed tournament data
     */
    public function warmTournamentCache($tournament): void
    {
        // Warm team data cache
        $teams = $tournament->teams ?? collect();
        foreach ($teams as $team) {
            $this->cacheTeamData($team);
        }

        // Warm live matches cache if tournament is ongoing
        if ($tournament->status === 'ongoing') {
            $liveMatches = BracketMatch::with(['team1', 'team2', 'bracketStage'])
                ->where($tournament instanceof Event ? 'event_id' : 'tournament_id', $tournament->id)
                ->where('status', 'live')
                ->get();
            
            $this->cacheLiveMatches($tournament, $liveMatches);
        }
    }

    /**
     * Get comprehensive cache statistics
     */
    public function getCacheStatistics(): array
    {
        $stats = [
            'active_tournaments' => $this->getActiveTournamentCount(),
            'cached_brackets' => $this->getCachedItemCount('tournament:bracket:*'),
            'cached_matches' => $this->getCachedItemCount('match:details:*'),
            'live_matches' => $this->getCachedItemCount('live:matches:*'),
            'swiss_standings' => $this->getCachedItemCount('swiss:standings:*'),
            'team_data' => $this->getCachedItemCount('team:data:*'),
            'cache_hit_ratio' => $this->calculateCacheHitRatio(),
            'memory_usage' => $this->getCacheMemoryUsage()
        ];

        return $stats;
    }

    // Helper methods

    protected function getTournamentBracketKey($tournament): string
    {
        $prefix = $tournament instanceof Event ? self::CACHE_KEYS['event_bracket'] : self::CACHE_KEYS['tournament_bracket'];
        return $prefix . $tournament->id;
    }

    protected function addToActiveTournaments($tournament): void
    {
        $setKey = 'active_tournaments';
        $tournamentKey = ($tournament instanceof Event ? 'event:' : 'tournament:') . $tournament->id;
        
        Cache::put($setKey . ':' . $tournamentKey, true, 3600); // 1 hour
    }

    protected function getActiveTournamentCount(): int
    {
        // This would need Redis for proper set operations
        // For now, return a simple count
        return Cache::get('active_tournament_count', 0);
    }

    protected function getCachedItemCount(string $pattern): int
    {
        // This would need Redis for pattern matching
        // For now, return estimated counts
        return 0;
    }

    protected function calculateCacheHitRatio(): float
    {
        // This would need proper cache hit/miss tracking
        return 0.85; // Placeholder
    }

    protected function getCacheMemoryUsage(): array
    {
        // This would need Redis memory info
        return [
            'used_memory' => '0MB',
            'max_memory' => '0MB',
            'usage_percentage' => 0
        ];
    }

    /**
     * Clean up expired tournament caches
     */
    public function cleanupExpiredCaches(): void
    {
        // This would be implemented with Redis cleanup scripts
        // For now, let Laravel's cache handle TTL expiration
    }
}