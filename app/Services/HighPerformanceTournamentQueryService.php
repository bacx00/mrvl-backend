<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\BracketMatch;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

/**
 * High-Performance Tournament Query Service
 * 
 * Optimized queries for tournament operations with sub-second response times
 * Designed for high-load scenarios with thousands of participants
 */
class HighPerformanceTournamentQueryService
{
    private const CACHE_PREFIX = 'tournament_hp:';
    private const DEFAULT_CACHE_TTL = 300; // 5 minutes
    private const LIVE_DATA_TTL = 30; // 30 seconds for live data
    private const BRACKET_CACHE_TTL = 120; // 2 minutes for bracket data

    /**
     * Get tournament list with optimized filtering
     * Uses covering indexes and materialized aggregations
     */
    public function getTournamentList(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $cacheKey = self::CACHE_PREFIX . 'list:' . md5(serialize($filters) . ":{$limit}:{$offset}");
        
        return Cache::remember($cacheKey, self::DEFAULT_CACHE_TTL, function () use ($filters, $limit, $offset) {
            $query = Tournament::select([
                'id', 'name', 'type', 'format', 'status', 'region', 'featured', 'public',
                'prize_pool', 'currency', 'team_count', 'max_teams', 'start_date', 'end_date',
                'registration_start', 'registration_end', 'current_phase', 'views', 'logo', 'banner'
            ]);

            // Apply filters using optimized indexes
            if (!empty($filters['status'])) {
                $query->whereIn('status', (array) $filters['status']);
            }

            if (!empty($filters['type'])) {
                $query->whereIn('type', (array) $filters['type']);
            }

            if (!empty($filters['format'])) {
                $query->whereIn('format', (array) $filters['format']);
            }

            if (!empty($filters['region'])) {
                $query->whereIn('region', (array) $filters['region']);
            }

            if (!empty($filters['featured'])) {
                $query->where('featured', true);
            }

            if (!empty($filters['public'])) {
                $query->where('public', true);
            }

            if (!empty($filters['date_range'])) {
                $query->whereBetween('start_date', [
                    $filters['date_range']['start'],
                    $filters['date_range']['end']
                ]);
            }

            // Optimize ordering for different scenarios
            if (isset($filters['live_first']) && $filters['live_first']) {
                $query->orderByRaw("FIELD(status, 'ongoing', 'check_in', 'registration_open') DESC")
                      ->orderBy('start_date', 'asc');
            } else {
                $query->orderBy('featured', 'desc')
                      ->orderBy('start_date', 'desc');
            }

            $tournaments = $query->offset($offset)->limit($limit)->get();

            // Get team counts in a single optimized query
            if ($tournaments->isNotEmpty()) {
                $tournamentIds = $tournaments->pluck('id');
                $teamCounts = $this->getBulkTeamCounts($tournamentIds->toArray());
                
                $tournaments->transform(function ($tournament) use ($teamCounts) {
                    $tournament->current_team_count = $teamCounts[$tournament->id] ?? 0;
                    return $tournament;
                });
            }

            return $tournaments->toArray();
        });
    }

    /**
     * Get live tournament data with real-time optimization
     * Critical for live streaming and real-time updates
     */
    public function getLiveTournamentData(int $tournamentId): array
    {
        $cacheKey = self::CACHE_PREFIX . "live:{$tournamentId}";
        
        return Cache::remember($cacheKey, self::LIVE_DATA_TTL, function () use ($tournamentId) {
            // Use covering index for live tournament query
            $tournament = Tournament::select([
                'id', 'name', 'status', 'current_phase', 'format', 'type',
                'team_count', 'max_teams', 'start_date', 'end_date',
                'bracket_data', 'phase_data', 'stream_urls'
            ])->find($tournamentId);

            if (!$tournament) {
                return [];
            }

            $liveData = [
                'tournament' => $tournament->toArray(),
                'live_matches' => $this->getLiveMatches($tournamentId),
                'current_standings' => $this->getCurrentStandings($tournamentId),
                'recent_results' => $this->getRecentResults($tournamentId, 10),
                'next_matches' => $this->getNextMatches($tournamentId, 5),
                'phase_progress' => $this->getPhaseProgress($tournamentId),
                'live_stats' => $this->getLiveStatistics($tournamentId)
            ];

            return $liveData;
        });
    }

    /**
     * Get tournament bracket with optimized loading
     * Uses covering indexes and smart caching
     */
    public function getTournamentBracket(int $tournamentId, ?string $phaseId = null): array
    {
        $cacheKey = self::CACHE_PREFIX . "bracket:{$tournamentId}:" . ($phaseId ?? 'all');
        
        return Cache::remember($cacheKey, self::BRACKET_CACHE_TTL, function () use ($tournamentId, $phaseId) {
            $tournament = Tournament::find($tournamentId);
            if (!$tournament) {
                return [];
            }

            return match($tournament->format) {
                'swiss' => $this->getSwissBracket($tournamentId, $phaseId),
                'single_elimination' => $this->getSingleEliminationBracket($tournamentId, $phaseId),
                'double_elimination' => $this->getDoubleEliminationBracket($tournamentId, $phaseId),
                'round_robin' => $this->getRoundRobinBracket($tournamentId, $phaseId),
                'group_stage_playoffs' => $this->getGroupStageBracket($tournamentId, $phaseId),
                default => []
            };
        });
    }

    /**
     * Get Swiss standings with optimized calculation
     * Most performance-critical query for Swiss tournaments
     */
    public function getSwissStandings(int $tournamentId, ?int $round = null): array
    {
        $cacheKey = self::CACHE_PREFIX . "swiss_standings:{$tournamentId}:" . ($round ?? 'current');
        
        return Cache::remember($cacheKey, 60, function () use ($tournamentId, $round) {
            // Use optimized covering index query
            $standings = DB::select("
                SELECT 
                    tt.team_id,
                    tt.seed,
                    tt.swiss_wins,
                    tt.swiss_losses,
                    tt.swiss_score,
                    tt.swiss_buchholz,
                    tt.status,
                    tt.placement,
                    t.name as team_name,
                    t.logo,
                    t.region,
                    COALESCE(
                        (tt.swiss_wins / NULLIF(tt.swiss_wins + tt.swiss_losses, 0)) * 100, 
                        0
                    ) as win_percentage,
                    (
                        SELECT COUNT(*) 
                        FROM bracket_matches bm 
                        WHERE (bm.team1_id = tt.team_id OR bm.team2_id = tt.team_id) 
                        AND bm.tournament_id = tt.tournament_id 
                        AND bm.status = 'completed'
                        " . ($round ? "AND bm.round <= {$round}" : "") . "
                    ) as matches_played
                FROM tournament_teams tt
                INNER JOIN teams t ON tt.team_id = t.id
                WHERE tt.tournament_id = ?
                AND tt.status IN ('checked_in', 'advanced', 'eliminated')
                ORDER BY 
                    tt.swiss_wins DESC,
                    tt.swiss_buchholz DESC,
                    tt.swiss_score DESC,
                    tt.swiss_losses ASC,
                    tt.seed ASC
            ", [$tournamentId]);

            return array_map(function ($row) {
                return (array) $row;
            }, $standings);
        });
    }

    /**
     * Get live matches with real-time updates
     * Optimized for minimal latency
     */
    public function getLiveMatches(int $tournamentId): array
    {
        $cacheKey = self::CACHE_PREFIX . "live_matches:{$tournamentId}";
        
        return Cache::remember($cacheKey, 15, function () use ($tournamentId) {
            // Use covering index for live matches
            return DB::select("
                SELECT 
                    bm.id,
                    bm.round,
                    bm.match_number,
                    bm.team1_id,
                    bm.team2_id,
                    bm.team1_score,
                    bm.team2_score,
                    bm.status,
                    bm.started_at,
                    bm.scheduled_at,
                    bm.stream_url,
                    bm.match_format,
                    t1.name as team1_name,
                    t1.logo as team1_logo,
                    t2.name as team2_name,
                    t2.logo as team2_logo,
                    TIMESTAMPDIFF(MINUTE, bm.started_at, NOW()) as elapsed_minutes
                FROM bracket_matches bm
                LEFT JOIN teams t1 ON bm.team1_id = t1.id
                LEFT JOIN teams t2 ON bm.team2_id = t2.id
                WHERE bm.tournament_id = ?
                AND bm.status IN ('ongoing', 'ready')
                ORDER BY bm.started_at DESC, bm.scheduled_at ASC
                LIMIT 10
            ", [$tournamentId]);
        });
    }

    /**
     * Get team performance in tournament
     * Optimized for team profile pages
     */
    public function getTeamTournamentPerformance(int $teamId, int $tournamentId): array
    {
        $cacheKey = self::CACHE_PREFIX . "team_performance:{$teamId}:{$tournamentId}";
        
        return Cache::remember($cacheKey, 300, function () use ($teamId, $tournamentId) {
            // Single optimized query for all team performance data
            $performance = DB::select("
                SELECT 
                    tt.*,
                    t.name as team_name,
                    t.logo,
                    tour.name as tournament_name,
                    tour.format,
                    tour.status as tournament_status,
                    (
                        SELECT COUNT(*) 
                        FROM bracket_matches bm 
                        WHERE (bm.team1_id = ? OR bm.team2_id = ?) 
                        AND bm.tournament_id = ? 
                        AND bm.status = 'completed'
                    ) as matches_played,
                    (
                        SELECT COUNT(*) 
                        FROM bracket_matches bm 
                        WHERE ((bm.team1_id = ? AND bm.team1_score > bm.team2_score) 
                            OR (bm.team2_id = ? AND bm.team2_score > bm.team1_score))
                        AND bm.tournament_id = ? 
                        AND bm.status = 'completed'
                        AND bm.winner_id = ?
                    ) as matches_won,
                    COALESCE(tt.swiss_wins / NULLIF(tt.swiss_wins + tt.swiss_losses, 0) * 100, 0) as win_rate
                FROM tournament_teams tt
                INNER JOIN teams t ON tt.team_id = t.id
                INNER JOIN tournaments tour ON tt.tournament_id = tour.id
                WHERE tt.team_id = ? AND tt.tournament_id = ?
            ", [$teamId, $teamId, $tournamentId, $teamId, $teamId, $tournamentId, $teamId, $teamId, $tournamentId]);

            return $performance ? (array) $performance[0] : [];
        });
    }

    /**
     * Get tournament statistics with aggregated data
     * Optimized for admin dashboards
     */
    public function getTournamentAnalytics(int $tournamentId): array
    {
        $cacheKey = self::CACHE_PREFIX . "analytics:{$tournamentId}";
        
        return Cache::remember($cacheKey, 600, function () use ($tournamentId) {
            // Single comprehensive analytics query
            $analytics = DB::select("
                SELECT 
                    t.id,
                    t.name,
                    t.type,
                    t.format,
                    t.status,
                    t.prize_pool,
                    t.team_count,
                    t.views,
                    COUNT(DISTINCT tt.team_id) as registered_teams,
                    COUNT(DISTINCT CASE WHEN tt.status = 'checked_in' THEN tt.team_id END) as checked_in_teams,
                    COUNT(DISTINCT bm.id) as total_matches,
                    COUNT(DISTINCT CASE WHEN bm.status = 'completed' THEN bm.id END) as completed_matches,
                    COUNT(DISTINCT CASE WHEN bm.status = 'ongoing' THEN bm.id END) as ongoing_matches,
                    AVG(CASE WHEN bm.status = 'completed' 
                        THEN TIMESTAMPDIFF(MINUTE, bm.started_at, bm.completed_at) END) as avg_match_duration,
                    SUM(CASE WHEN bm.status = 'completed' 
                        THEN bm.team1_score + bm.team2_score END) as total_games_played
                FROM tournaments t
                LEFT JOIN tournament_teams tt ON t.id = tt.tournament_id
                LEFT JOIN bracket_matches bm ON t.id = bm.tournament_id
                WHERE t.id = ?
                GROUP BY t.id
            ", [$tournamentId]);

            return $analytics ? (array) $analytics[0] : [];
        });
    }

    /**
     * Search tournaments with full-text optimization
     */
    public function searchTournaments(string $query, array $filters = [], int $limit = 20): array
    {
        $cacheKey = self::CACHE_PREFIX . 'search:' . md5($query . serialize($filters) . $limit);
        
        return Cache::remember($cacheKey, 300, function () use ($query, $filters, $limit) {
            $searchQuery = Tournament::select([
                'id', 'name', 'type', 'format', 'status', 'region', 'prize_pool',
                'start_date', 'end_date', 'logo', 'featured'
            ]);

            // Use full-text search if available, otherwise use LIKE optimization
            if (strlen($query) >= 3) {
                $searchQuery->where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('description', 'LIKE', "%{$query}%");
                });
            }

            // Apply additional filters
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    $searchQuery->where($key, $value);
                }
            }

            return $searchQuery->orderBy('featured', 'desc')
                              ->orderBy('start_date', 'desc')
                              ->limit($limit)
                              ->get()
                              ->toArray();
        });
    }

    /**
     * Get tournament leaderboard with caching
     */
    public function getTournamentLeaderboard(int $tournamentId, int $limit = 20): array
    {
        $cacheKey = self::CACHE_PREFIX . "leaderboard:{$tournamentId}:{$limit}";
        
        return Cache::remember($cacheKey, 180, function () use ($tournamentId, $limit) {
            return DB::select("
                SELECT 
                    tt.placement,
                    tt.team_id,
                    t.name as team_name,
                    t.logo,
                    t.region,
                    tt.prize_money,
                    tt.points_earned,
                    tt.swiss_wins,
                    tt.swiss_losses,
                    COALESCE(tt.swiss_wins / NULLIF(tt.swiss_wins + tt.swiss_losses, 0) * 100, 0) as win_rate
                FROM tournament_teams tt
                INNER JOIN teams t ON tt.team_id = t.id
                WHERE tt.tournament_id = ?
                AND tt.placement IS NOT NULL
                ORDER BY tt.placement ASC
                LIMIT ?
            ", [$tournamentId, $limit]);
        });
    }

    /**
     * Bulk invalidate tournament cache
     */
    public function invalidateTournamentCache(int $tournamentId): void
    {
        $patterns = [
            self::CACHE_PREFIX . "live:{$tournamentId}",
            self::CACHE_PREFIX . "bracket:{$tournamentId}:*",
            self::CACHE_PREFIX . "swiss_standings:{$tournamentId}:*",
            self::CACHE_PREFIX . "live_matches:{$tournamentId}",
            self::CACHE_PREFIX . "analytics:{$tournamentId}",
            self::CACHE_PREFIX . "leaderboard:{$tournamentId}:*"
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $keys = Cache::getRedis()->keys($pattern);
                if (!empty($keys)) {
                    Cache::getRedis()->del($keys);
                }
            } else {
                Cache::forget($pattern);
            }
        }
    }

    // Private helper methods for specific bracket formats

    private function getBulkTeamCounts(array $tournamentIds): array
    {
        if (empty($tournamentIds)) {
            return [];
        }

        $counts = DB::select("
            SELECT tournament_id, COUNT(*) as team_count
            FROM tournament_teams 
            WHERE tournament_id IN (" . implode(',', $tournamentIds) . ")
            AND status NOT IN ('disqualified', 'withdrawn')
            GROUP BY tournament_id
        ");

        return collect($counts)->pluck('team_count', 'tournament_id')->toArray();
    }

    private function getSwissBracket(int $tournamentId, ?string $phaseId): array
    {
        $standings = $this->getSwissStandings($tournamentId);
        $matches = $this->getSwissMatches($tournamentId);

        return [
            'type' => 'swiss',
            'standings' => $standings,
            'matches' => $matches,
            'current_round' => $this->getCurrentRound($tournamentId),
            'total_rounds' => $this->getTotalSwissRounds($tournamentId)
        ];
    }

    private function getSingleEliminationBracket(int $tournamentId, ?string $phaseId): array
    {
        $matches = $this->getBracketMatches($tournamentId, 'single_elimination');
        return [
            'type' => 'single_elimination',
            'matches' => $matches,
            'rounds' => $this->organizeBracketRounds($matches)
        ];
    }

    private function getDoubleEliminationBracket(int $tournamentId, ?string $phaseId): array
    {
        $matches = $this->getBracketMatches($tournamentId, 'double_elimination');
        return [
            'type' => 'double_elimination',
            'upper_bracket' => $this->getUpperBracketMatches($matches),
            'lower_bracket' => $this->getLowerBracketMatches($matches),
            'grand_final' => $this->getGrandFinalMatches($matches)
        ];
    }

    private function getRoundRobinBracket(int $tournamentId, ?string $phaseId): array
    {
        $matches = $this->getBracketMatches($tournamentId, 'round_robin');
        $standings = $this->getRoundRobinStandings($tournamentId);

        return [
            'type' => 'round_robin',
            'matches' => $matches,
            'standings' => $standings
        ];
    }

    private function getGroupStageBracket(int $tournamentId, ?string $phaseId): array
    {
        // Implementation for group stage + playoffs format
        return [
            'type' => 'group_stage_playoffs',
            'groups' => [],
            'playoffs' => []
        ];
    }

    private function getCurrentStandings(int $tournamentId): array
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) return [];

        return match($tournament->format) {
            'swiss' => $this->getSwissStandings($tournamentId),
            'round_robin' => $this->getRoundRobinStandings($tournamentId),
            default => []
        };
    }

    private function getRecentResults(int $tournamentId, int $limit): array
    {
        return DB::select("
            SELECT 
                bm.id,
                bm.round,
                bm.team1_id,
                bm.team2_id,
                bm.team1_score,
                bm.team2_score,
                bm.winner_id,
                bm.completed_at,
                t1.name as team1_name,
                t1.logo as team1_logo,
                t2.name as team2_name,
                t2.logo as team2_logo
            FROM bracket_matches bm
            LEFT JOIN teams t1 ON bm.team1_id = t1.id
            LEFT JOIN teams t2 ON bm.team2_id = t2.id
            WHERE bm.tournament_id = ?
            AND bm.status = 'completed'
            ORDER BY bm.completed_at DESC
            LIMIT ?
        ", [$tournamentId, $limit]);
    }

    private function getNextMatches(int $tournamentId, int $limit): array
    {
        return DB::select("
            SELECT 
                bm.id,
                bm.round,
                bm.match_number,
                bm.team1_id,
                bm.team2_id,
                bm.scheduled_at,
                bm.match_format,
                t1.name as team1_name,
                t1.logo as team1_logo,
                t2.name as team2_name,
                t2.logo as team2_logo
            FROM bracket_matches bm
            LEFT JOIN teams t1 ON bm.team1_id = t1.id
            LEFT JOIN teams t2 ON bm.team2_id = t2.id
            WHERE bm.tournament_id = ?
            AND bm.status IN ('pending', 'ready')
            AND bm.scheduled_at > NOW()
            ORDER BY bm.scheduled_at ASC
            LIMIT ?
        ", [$tournamentId, $limit]);
    }

    private function getPhaseProgress(int $tournamentId): array
    {
        return DB::select("
            SELECT 
                tp.id,
                tp.name,
                tp.phase_type,
                tp.phase_order,
                tp.status,
                tp.start_date,
                tp.end_date,
                tp.is_active
            FROM tournament_phases tp
            WHERE tp.tournament_id = ?
            ORDER BY tp.phase_order ASC
        ", [$tournamentId]);
    }

    private function getLiveStatistics(int $tournamentId): array
    {
        return [
            'total_matches_today' => $this->getTodayMatchCount($tournamentId),
            'active_viewers' => $this->getActiveViewerCount($tournamentId),
            'peak_concurrent_matches' => $this->getPeakConcurrentMatches($tournamentId)
        ];
    }

    // Additional helper methods would go here...
    
    private function getSwissMatches(int $tournamentId): array
    {
        return DB::select("
            SELECT 
                bm.*,
                t1.name as team1_name,
                t1.logo as team1_logo,
                t2.name as team2_name,
                t2.logo as team2_logo
            FROM bracket_matches bm
            LEFT JOIN teams t1 ON bm.team1_id = t1.id
            LEFT JOIN teams t2 ON bm.team2_id = t2.id
            WHERE bm.tournament_id = ?
            ORDER BY bm.round ASC, bm.match_number ASC
        ", [$tournamentId]);
    }

    private function getCurrentRound(int $tournamentId): int
    {
        $result = DB::select("
            SELECT MAX(round) as current_round
            FROM bracket_matches 
            WHERE tournament_id = ? 
            AND status IN ('ongoing', 'completed')
        ", [$tournamentId]);

        return $result[0]->current_round ?? 1;
    }

    private function getTotalSwissRounds(int $tournamentId): int
    {
        $tournament = Tournament::find($tournamentId);
        $settings = $tournament->qualification_settings ?? [];
        return $settings['swiss_rounds'] ?? ceil(log($tournament->team_count ?? 8, 2));
    }

    private function getBracketMatches(int $tournamentId, string $format): array
    {
        return DB::select("
            SELECT 
                bm.*,
                t1.name as team1_name,
                t1.logo as team1_logo,
                t2.name as team2_name,
                t2.logo as team2_logo
            FROM bracket_matches bm
            LEFT JOIN teams t1 ON bm.team1_id = t1.id
            LEFT JOIN teams t2 ON bm.team2_id = t2.id
            WHERE bm.tournament_id = ?
            ORDER BY bm.round ASC, bm.match_number ASC
        ", [$tournamentId]);
    }

    private function organizeBracketRounds(array $matches): array
    {
        $rounds = [];
        foreach ($matches as $match) {
            $rounds[$match->round][] = $match;
        }
        return $rounds;
    }

    private function getUpperBracketMatches(array $matches): array
    {
        return array_filter($matches, function($match) {
            return $match->bracket_type === 'upper' || !isset($match->bracket_type);
        });
    }

    private function getLowerBracketMatches(array $matches): array
    {
        return array_filter($matches, function($match) {
            return $match->bracket_type === 'lower';
        });
    }

    private function getGrandFinalMatches(array $matches): array
    {
        return array_filter($matches, function($match) {
            return $match->bracket_type === 'grand_final' || $match->round === 'grand_final';
        });
    }

    private function getRoundRobinStandings(int $tournamentId): array
    {
        return DB::select("
            SELECT 
                tt.team_id,
                t.name as team_name,
                t.logo,
                tt.swiss_wins as wins,
                tt.swiss_losses as losses,
                tt.swiss_score as points,
                COALESCE(tt.swiss_wins / NULLIF(tt.swiss_wins + tt.swiss_losses, 0) * 100, 0) as win_percentage
            FROM tournament_teams tt
            INNER JOIN teams t ON tt.team_id = t.id
            WHERE tt.tournament_id = ?
            ORDER BY tt.swiss_score DESC, tt.swiss_wins DESC, tt.swiss_losses ASC
        ", [$tournamentId]);
    }

    private function getTodayMatchCount(int $tournamentId): int
    {
        $result = DB::select("
            SELECT COUNT(*) as count
            FROM bracket_matches 
            WHERE tournament_id = ? 
            AND DATE(scheduled_at) = CURDATE()
        ", [$tournamentId]);

        return $result[0]->count ?? 0;
    }

    private function getActiveViewerCount(int $tournamentId): int
    {
        // This would integrate with your streaming/viewing tracking system
        return 0;
    }

    private function getPeakConcurrentMatches(int $tournamentId): int
    {
        // This would track historical concurrent match data
        return 0;
    }
}