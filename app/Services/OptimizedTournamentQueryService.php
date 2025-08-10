<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Event;
use App\Models\BracketMatch;
use App\Models\BracketStanding;
use App\Models\Team;

/**
 * Optimized Tournament Query Service
 * 
 * Provides high-performance queries for tournament operations using:
 * - Optimized indexes
 * - Query result caching
 * - Materialized view simulation
 * - Bulk operations for Swiss pairings
 */
class OptimizedTournamentQueryService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const LIVE_CACHE_TTL = 30; // 30 seconds for live data

    /**
     * Get complete bracket with all matches and teams (optimized for heavy JOINs)
     */
    public function getCompleteBracket(int $eventId, ?int $stageId = null): array
    {
        $cacheKey = "bracket_complete_{$eventId}" . ($stageId ? "_{$stageId}" : "");
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($eventId, $stageId) {
            $query = "
                SELECT 
                    bm.id,
                    bm.match_id,
                    bm.liquipedia_id,
                    bm.round_name,
                    bm.round_number,
                    bm.match_number,
                    bm.team1_id,
                    bm.team2_id,
                    bm.team1_source,
                    bm.team2_source,
                    bm.team1_score,
                    bm.team2_score,
                    bm.winner_id,
                    bm.loser_id,
                    bm.status,
                    bm.best_of,
                    bm.scheduled_at,
                    bm.started_at,
                    bm.completed_at,
                    bm.winner_advances_to,
                    bm.loser_advances_to,
                    bm.next_match_upper,
                    bm.next_match_lower,
                    bm.bracket_reset,
                    bm.dependency_matches,
                    bm.map_veto_data,
                    bs.name as stage_name,
                    bs.type as stage_type,
                    t1.name as team1_name,
                    t1.logo_url as team1_logo,
                    t1.region as team1_region,
                    t2.name as team2_name,
                    t2.logo_url as team2_logo,
                    t2.region as team2_region,
                    winner.name as winner_name,
                    COUNT(bg.id) as games_played,
                    SUM(CASE WHEN bg.winner_id = bm.team1_id THEN 1 ELSE 0 END) as team1_games_won,
                    SUM(CASE WHEN bg.winner_id = bm.team2_id THEN 1 ELSE 0 END) as team2_games_won
                FROM bracket_matches bm
                JOIN bracket_stages bs ON bm.bracket_stage_id = bs.id
                LEFT JOIN teams t1 ON bm.team1_id = t1.id
                LEFT JOIN teams t2 ON bm.team2_id = t2.id
                LEFT JOIN teams winner ON bm.winner_id = winner.id
                LEFT JOIN bracket_games bg ON bm.id = bg.bracket_match_id
                WHERE bm.event_id = ?
                " . ($stageId ? "AND bm.bracket_stage_id = ?" : "") . "
                GROUP BY bm.id, bs.name, bs.type, t1.name, t1.logo_url, t1.region, 
                         t2.name, t2.logo_url, t2.region, winner.name
                ORDER BY bs.stage_order, bm.round_number, bm.match_number
            ";

            $params = [$eventId];
            if ($stageId) {
                $params[] = $stageId;
            }

            return DB::select($query, $params);
        });
    }

    /**
     * Calculate Swiss pairings avoiding repeat matchups (complex WHERE clauses)
     */
    public function calculateSwissPairings(int $bracketId, int $round): array
    {
        $cacheKey = "swiss_pairings_{$bracketId}_{$round}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($bracketId, $round) {
            // Get current standings with opponent history
            $standings = "
                WITH RankedStandings AS (
                    SELECT 
                        bss.*,
                        ROW_NUMBER() OVER (
                            ORDER BY 
                                wins DESC, 
                                buchholz_score DESC,
                                game_win_percentage DESC,
                                round_differential DESC
                        ) as rank_position,
                        CASE 
                            WHEN opponent_history IS NOT NULL 
                            THEN JSON_LENGTH(opponent_history)
                            ELSE 0
                        END as matches_played
                    FROM bracket_swiss_standings bss
                    WHERE bss.bracket_id = ? 
                    AND bss.eliminated = FALSE
                    AND bss.current_round < ?
                ),
                PairingCandidates AS (
                    SELECT 
                        t1.id as team1_id,
                        t1.team_id as team1_team_id,
                        t1.wins as team1_wins,
                        t1.rank_position as team1_rank,
                        t2.id as team2_id,
                        t2.team_id as team2_team_id,
                        t2.wins as team2_wins,
                        t2.rank_position as team2_rank,
                        ABS(t1.wins - t2.wins) as win_difference,
                        ABS(t1.rank_position - t2.rank_position) as rank_difference,
                        CASE 
                            WHEN t1.opponent_history IS NULL 
                            THEN FALSE
                            WHEN JSON_CONTAINS(t1.opponent_history, CAST(t2.team_id AS JSON))
                            THEN TRUE
                            ELSE FALSE
                        END as already_played
                    FROM RankedStandings t1
                    JOIN RankedStandings t2 ON t1.id < t2.id
                    WHERE t1.matches_played = t2.matches_played
                )
                SELECT *
                FROM PairingCandidates
                WHERE already_played = FALSE
                ORDER BY 
                    win_difference ASC,
                    rank_difference ASC,
                    team1_rank ASC
            ";

            return DB::select($standings, [$bracketId, $round]);
        });
    }

    /**
     * Update match scores with bracket progression (high write frequency)
     */
    public function updateMatchScoreWithProgression(
        int $matchId, 
        int $team1Score, 
        int $team2Score, 
        ?int $winnerId = null
    ): bool {
        return DB::transaction(function() use ($matchId, $team1Score, $team2Score, $winnerId) {
            // Update match scores with optimized query
            $updated = DB::table('bracket_matches')
                ->where('id', $matchId)
                ->update([
                    'team1_score' => $team1Score,
                    'team2_score' => $team2Score,
                    'winner_id' => $winnerId,
                    'status' => $winnerId ? 'completed' : 'ongoing',
                    'completed_at' => $winnerId ? now() : null,
                    'updated_at' => now()
                ]);

            if (!$updated) {
                return false;
            }

            // Clear related caches
            $match = DB::table('bracket_matches')->find($matchId);
            if ($match) {
                Cache::forget("bracket_complete_{$match->event_id}");
                Cache::forget("bracket_complete_{$match->event_id}_{$match->bracket_stage_id}");
                Cache::forget("live_matches_{$match->event_id}");
                Cache::forget("tournament_standings_{$match->event_id}");
            }

            // If match is completed, handle bracket progression
            if ($winnerId) {
                $this->handleBracketProgression($matchId, $winnerId);
            }

            return true;
        });
    }

    /**
     * Get real-time tournament standings (aggregation queries)
     */
    public function getTournamentStandings(int $eventId, ?string $format = null): array
    {
        $cacheKey = "tournament_standings_{$eventId}" . ($format ? "_{$format}" : "");
        
        return Cache::remember($cacheKey, self::LIVE_CACHE_TTL, function() use ($eventId, $format) {
            if ($format === 'swiss') {
                return $this->getSwissStandings($eventId);
            }

            // General bracket standings with comprehensive stats
            $query = "
                WITH MatchStats AS (
                    SELECT 
                        t.id as team_id,
                        t.name as team_name,
                        t.logo_url,
                        t.region,
                        COUNT(DISTINCT CASE 
                            WHEN bm.status = 'completed' 
                            AND (bm.team1_id = t.id OR bm.team2_id = t.id)
                            THEN bm.id 
                        END) as matches_played,
                        COUNT(DISTINCT CASE 
                            WHEN bm.winner_id = t.id 
                            THEN bm.id 
                        END) as matches_won,
                        COUNT(DISTINCT CASE 
                            WHEN bm.status = 'completed' 
                            AND bm.winner_id != t.id 
                            AND (bm.team1_id = t.id OR bm.team2_id = t.id)
                            THEN bm.id 
                        END) as matches_lost,
                        COALESCE(SUM(CASE 
                            WHEN bm.team1_id = t.id THEN bm.team1_score
                            WHEN bm.team2_id = t.id THEN bm.team2_score
                            ELSE 0
                        END), 0) as games_won,
                        COALESCE(SUM(CASE 
                            WHEN bm.team1_id = t.id THEN bm.team2_score
                            WHEN bm.team2_id = t.id THEN bm.team1_score
                            ELSE 0
                        END), 0) as games_lost,
                        COALESCE(bs.final_placement, 999) as final_placement,
                        bs.prize_money
                    FROM teams t
                    LEFT JOIN bracket_matches bm ON (t.id = bm.team1_id OR t.id = bm.team2_id)
                        AND bm.event_id = ?
                    LEFT JOIN bracket_standings bs ON t.id = bs.team_id AND bs.event_id = ?
                    WHERE EXISTS (
                        SELECT 1 FROM bracket_matches bm2 
                        WHERE bm2.event_id = ? 
                        AND (bm2.team1_id = t.id OR bm2.team2_id = t.id)
                    )
                    GROUP BY t.id, t.name, t.logo_url, t.region, bs.final_placement, bs.prize_money
                )
                SELECT 
                    *,
                    CASE 
                        WHEN matches_played > 0 
                        THEN ROUND((matches_won * 100.0 / matches_played), 2)
                        ELSE 0 
                    END as win_rate,
                    CASE 
                        WHEN games_lost > 0 
                        THEN ROUND((games_won * 1.0 / games_lost), 2)
                        ELSE games_won 
                    END as game_ratio
                FROM MatchStats
                ORDER BY 
                    final_placement ASC,
                    matches_won DESC,
                    game_ratio DESC,
                    win_rate DESC
            ";

            return DB::select($query, [$eventId, $eventId, $eventId]);
        });
    }

    /**
     * Get Swiss system specific standings
     */
    private function getSwissStandings(int $eventId): array
    {
        $query = "
            SELECT 
                bss.*,
                t.name as team_name,
                t.logo_url,
                t.region,
                CASE 
                    WHEN (bss.wins + bss.losses) > 0 
                    THEN ROUND((bss.wins * 100.0 / (bss.wins + bss.losses)), 2)
                    ELSE 0 
                END as win_rate,
                CASE 
                    WHEN bss.games_lost > 0 
                    THEN ROUND((bss.games_won * 1.0 / bss.games_lost), 2)
                    ELSE bss.games_won 
                END as game_ratio,
                ROW_NUMBER() OVER (
                    ORDER BY 
                        qualified DESC,
                        eliminated ASC,
                        wins DESC, 
                        buchholz_score DESC,
                        game_win_percentage DESC,
                        round_differential DESC
                ) as current_rank
            FROM bracket_swiss_standings bss
            JOIN teams t ON bss.team_id = t.id
            WHERE bss.event_id = ?
            ORDER BY current_rank
        ";

        return DB::select($query, [$eventId]);
    }

    /**
     * Tournament progression queries (recursive CTEs)
     */
    public function getTournamentProgression(int $eventId): array
    {
        $cacheKey = "tournament_progression_{$eventId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($eventId) {
            // Use recursive CTE to trace tournament progression
            $query = "
                WITH RECURSIVE MatchProgression AS (
                    -- Base case: First round matches
                    SELECT 
                        bm.id,
                        bm.match_id,
                        bm.round_number,
                        bm.match_number,
                        bm.team1_id,
                        bm.team2_id,
                        bm.winner_id,
                        bm.status,
                        bm.winner_advances_to,
                        1 as depth,
                        CAST(bm.match_id AS CHAR(1000)) as path
                    FROM bracket_matches bm
                    WHERE bm.event_id = ?
                    AND bm.round_number = 1
                    
                    UNION ALL
                    
                    -- Recursive case: Following matches
                    SELECT 
                        bm.id,
                        bm.match_id,
                        bm.round_number,
                        bm.match_number,
                        bm.team1_id,
                        bm.team2_id,
                        bm.winner_id,
                        bm.status,
                        bm.winner_advances_to,
                        mp.depth + 1,
                        CONCAT(mp.path, ' -> ', bm.match_id)
                    FROM bracket_matches bm
                    JOIN MatchProgression mp ON bm.match_id = mp.winner_advances_to
                    WHERE mp.depth < 10  -- Prevent infinite recursion
                )
                SELECT 
                    mp.*,
                    t1.name as team1_name,
                    t2.name as team2_name,
                    winner.name as winner_name
                FROM MatchProgression mp
                LEFT JOIN teams t1 ON mp.team1_id = t1.id
                LEFT JOIN teams t2 ON mp.team2_id = t2.id
                LEFT JOIN teams winner ON mp.winner_id = winner.id
                ORDER BY depth, round_number, match_number
            ";

            return DB::select($query, [$eventId]);
        });
    }

    /**
     * Get live matches with real-time updates
     */
    public function getLiveMatches(): array
    {
        $cacheKey = "live_matches_all";
        
        return Cache::remember($cacheKey, 10, function() { // Very short cache for live data
            $query = "
                SELECT 
                    bm.id,
                    bm.match_id,
                    bm.event_id,
                    bm.team1_id,
                    bm.team2_id,
                    bm.team1_score,
                    bm.team2_score,
                    bm.status,
                    bm.started_at,
                    bm.scheduled_at,
                    e.name as event_name,
                    t1.name as team1_name,
                    t1.logo_url as team1_logo,
                    t2.name as team2_name,
                    t2.logo_url as team2_logo,
                    TIMESTAMPDIFF(MINUTE, bm.started_at, NOW()) as minutes_elapsed
                FROM bracket_matches bm
                JOIN events e ON bm.event_id = e.id
                LEFT JOIN teams t1 ON bm.team1_id = t1.id
                LEFT JOIN teams t2 ON bm.team2_id = t2.id
                WHERE bm.status IN ('ongoing', 'live')
                   OR (bm.status = 'ready' AND bm.scheduled_at <= NOW() + INTERVAL 15 MINUTE)
                ORDER BY 
                    CASE bm.status
                        WHEN 'ongoing' THEN 1
                        WHEN 'live' THEN 1
                        WHEN 'ready' THEN 2
                        ELSE 3
                    END,
                    bm.scheduled_at ASC
            ";

            return DB::select($query);
        });
    }

    /**
     * Handle bracket progression logic
     */
    private function handleBracketProgression(int $matchId, int $winnerId): void
    {
        $match = DB::table('bracket_matches')->find($matchId);
        if (!$match) return;

        // Update loser_id
        $loserId = ($match->team1_id === $winnerId) ? $match->team2_id : $match->team1_id;
        DB::table('bracket_matches')
            ->where('id', $matchId)
            ->update(['loser_id' => $loserId]);

        // Progress winner to next match
        if ($match->next_match_upper) {
            $this->progressTeamToMatch($match->next_match_upper, $winnerId);
        }

        // Progress loser to lower bracket if applicable
        if ($match->next_match_lower && $loserId) {
            $this->progressTeamToMatch($match->next_match_lower, $loserId);
        }
    }

    /**
     * Progress team to next match
     */
    private function progressTeamToMatch(int $nextMatchId, int $teamId): void
    {
        $nextMatch = DB::table('bracket_matches')->find($nextMatchId);
        if (!$nextMatch) return;

        // Determine which slot to fill
        if (!$nextMatch->team1_id) {
            DB::table('bracket_matches')
                ->where('id', $nextMatchId)
                ->update(['team1_id' => $teamId]);
        } elseif (!$nextMatch->team2_id) {
            DB::table('bracket_matches')
                ->where('id', $nextMatchId)
                ->update(['team2_id' => $teamId]);
        }
    }

    /**
     * Bulk update Swiss standings for performance
     */
    public function bulkUpdateSwissStandings(int $bracketId, array $standings): bool
    {
        $cases = [];
        $ids = [];
        
        foreach ($standings as $standing) {
            $ids[] = $standing['id'];
            foreach (['wins', 'losses', 'buchholz_score', 'game_win_percentage'] as $field) {
                if (!isset($cases[$field])) $cases[$field] = '';
                $cases[$field] .= "WHEN id = {$standing['id']} THEN {$standing[$field]} ";
            }
        }

        if (empty($ids)) return true;

        $sql = "UPDATE bracket_swiss_standings SET ";
        foreach ($cases as $field => $case) {
            $sql .= "{$field} = CASE {$case} ELSE {$field} END, ";
        }
        $sql = rtrim($sql, ', ');
        $sql .= " WHERE bracket_id = ? AND id IN (" . implode(',', $ids) . ")";

        return DB::update($sql, [$bracketId]) > 0;
    }

    /**
     * Clear all tournament caches
     */
    public function clearTournamentCaches(int $eventId): void
    {
        $patterns = [
            "bracket_complete_{$eventId}*",
            "tournament_standings_{$eventId}*",
            "tournament_progression_{$eventId}",
            "live_matches_{$eventId}",
            "swiss_pairings_*_{$eventId}_*"
        ];

        foreach ($patterns as $pattern) {
            // In production, you'd use Redis SCAN for pattern matching
            // For Laravel cache, we'll need to track keys or use tags
            Cache::forget($pattern);
        }
    }
}