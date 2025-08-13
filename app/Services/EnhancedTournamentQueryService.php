<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Team;
use App\Models\Player;
use App\Models\BracketMatch;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Enhanced Tournament Query Service for MRVL Platform
 * 
 * Performance optimizations:
 * - Efficient database queries with proper indexing
 * - Query result caching with smart invalidation
 * - Optimized joins and subqueries
 * - Bulk operations for large datasets
 * - Memory-efficient data retrieval
 * - Pagination optimization
 * - Aggregation query optimization
 */
class EnhancedTournamentQueryService
{
    private $cachePrefix = 'mrvl_enhanced_query_';
    private $cacheTtl = 1800; // 30 minutes

    /**
     * Optimized tournament search with filtering and pagination
     */
    public function searchTournaments(array $filters = [], int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = $this->buildCacheKey('tournament_search', $filters, $page, $perPage);
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($filters, $page, $perPage) {
            $query = Tournament::query()
                              ->select([
                                  'id', 'name', 'slug', 'type', 'format', 'status', 'region',
                                  'prize_pool', 'currency', 'team_count', 'max_teams',
                                  'start_date', 'end_date', 'featured', 'public', 'logo',
                                  'current_phase', 'created_at'
                              ]);

            // Apply filters with optimized queries
            $this->applyTournamentFilters($query, $filters);

            // Optimized ordering using indexes
            $this->applyTournamentOrdering($query, $filters['sort'] ?? 'relevance');

            return $query->paginate($perPage, ['*'], 'page', $page);
        });
    }

    /**
     * Apply filters to tournament query
     */
    private function applyTournamentFilters(Builder $query, array $filters): void
    {
        // Status filter - uses idx_tournaments_hot_path
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Region filter - uses idx_tournaments_search
        if (!empty($filters['region'])) {
            if (is_array($filters['region'])) {
                $query->whereIn('region', $filters['region']);
            } else {
                $query->where('region', $filters['region']);
            }
        }

        // Type filter - uses idx_tournaments_search
        if (!empty($filters['type'])) {
            if (is_array($filters['type'])) {
                $query->whereIn('type', $filters['type']);
            } else {
                $query->where('type', $filters['type']);
            }
        }

        // Format filter
        if (!empty($filters['format'])) {
            $query->where('format', $filters['format']);
        }

        // Featured filter
        if (!empty($filters['featured'])) {
            $query->where('featured', true);
        }

        // Public filter
        if (isset($filters['public'])) {
            $query->where('public', $filters['public']);
        }

        // Date range filters
        if (!empty($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }

        if (!empty($filters['start_date_to'])) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }

        // Prize pool filter
        if (!empty($filters['min_prize_pool'])) {
            $query->where('prize_pool', '>=', $filters['min_prize_pool']);
        }

        // Team count filter
        if (!empty($filters['min_teams'])) {
            $query->where('team_count', '>=', $filters['min_teams']);
        }

        if (!empty($filters['max_teams'])) {
            $query->where('team_count', '<=', $filters['max_teams']);
        }

        // Search by name
        if (!empty($filters['search'])) {
            $query->where(function ($subQuery) use ($filters) {
                $searchTerm = '%' . $filters['search'] . '%';
                $subQuery->where('name', 'LIKE', $searchTerm)
                         ->orWhere('slug', 'LIKE', $searchTerm);
            });
        }

        // Quick filters for common use cases
        if (!empty($filters['upcoming'])) {
            $query->whereIn('status', ['registration_open', 'registration_closed', 'check_in'])
                  ->where('start_date', '>', now());
        }

        if (!empty($filters['live'])) {
            $query->where('status', 'ongoing');
        }

        if (!empty($filters['completed'])) {
            $query->where('status', 'completed');
        }

        if (!empty($filters['open_registration'])) {
            $query->where('status', 'registration_open')
                  ->whereRaw('team_count < max_teams');
        }
    }

    /**
     * Apply ordering to tournament query
     */
    private function applyTournamentOrdering(Builder $query, string $sort): void
    {
        switch ($sort) {
            case 'relevance':
                // Optimized relevance ordering using indexed columns
                $query->orderByRaw('
                    CASE 
                        WHEN status = "ongoing" THEN 1
                        WHEN featured = 1 AND status IN ("registration_open", "check_in") THEN 2
                        WHEN status = "registration_open" THEN 3
                        WHEN status = "check_in" THEN 4
                        WHEN featured = 1 THEN 5
                        ELSE 6
                    END
                ')->orderBy('start_date', 'desc');
                break;

            case 'start_date_asc':
                $query->orderBy('start_date', 'asc');
                break;

            case 'start_date_desc':
                $query->orderBy('start_date', 'desc');
                break;

            case 'prize_pool_desc':
                $query->orderBy('prize_pool', 'desc')->orderBy('start_date', 'desc');
                break;

            case 'team_count_desc':
                $query->orderBy('team_count', 'desc')->orderBy('start_date', 'desc');
                break;

            case 'created_desc':
                $query->orderBy('created_at', 'desc');
                break;

            default:
                $query->orderBy('start_date', 'desc');
        }
    }

    /**
     * Optimized bracket matches query with team information
     */
    public function getBracketMatches(int $tournamentId, ?int $round = null): array
    {
        $cacheKey = $this->buildCacheKey('bracket_matches', ['tournament_id' => $tournamentId, 'round' => $round]);
        
        return Cache::remember($cacheKey, 600, function () use ($tournamentId, $round) { // 10 minutes cache
            $query = BracketMatch::query()
                                ->select([
                                    'bracket_matches.*',
                                    't1.name as team1_name', 't1.short_name as team1_short_name', 't1.logo as team1_logo',
                                    't2.name as team2_name', 't2.short_name as team2_short_name', 't2.logo as team2_logo'
                                ])
                                ->leftJoin('teams as t1', 'bracket_matches.team1_id', '=', 't1.id')
                                ->leftJoin('teams as t2', 'bracket_matches.team2_id', '=', 't2.id')
                                ->where('bracket_matches.tournament_id', $tournamentId);

            if ($round !== null) {
                $query->where('bracket_matches.round', $round);
            }

            // Use optimized index for ordering
            $matches = $query->orderBy('bracket_matches.round')
                            ->orderBy('bracket_matches.match_number')
                            ->get();

            // Group by rounds for efficient display
            return $matches->groupBy('round')->map(function ($roundMatches, $roundNumber) {
                return [
                    'round' => $roundNumber,
                    'matches' => $roundMatches->map(function ($match) {
                        return [
                            'id' => $match->id,
                            'match_number' => $match->match_number,
                            'team1' => $match->team1_id ? [
                                'id' => $match->team1_id,
                                'name' => $match->team1_name,
                                'short_name' => $match->team1_short_name,
                                'logo' => $match->team1_logo
                            ] : null,
                            'team2' => $match->team2_id ? [
                                'id' => $match->team2_id,
                                'name' => $match->team2_name,
                                'short_name' => $match->team2_short_name,
                                'logo' => $match->team2_logo
                            ] : null,
                            'score' => [
                                'team1_score' => $match->team1_score,
                                'team2_score' => $match->team2_score
                            ],
                            'status' => $match->status,
                            'winner_id' => $match->winner_id,
                            'scheduled_at' => $match->scheduled_at,
                            'started_at' => $match->started_at,
                            'completed_at' => $match->completed_at
                        ];
                    })
                ];
            })->values();
        });
    }

    /**
     * Optimized tournament standings query
     */
    public function getTournamentStandings(int $tournamentId, string $type = 'auto'): array
    {
        $cacheKey = $this->buildCacheKey('tournament_standings', ['tournament_id' => $tournamentId, 'type' => $type]);
        
        return Cache::remember($cacheKey, 300, function () use ($tournamentId, $type) { // 5 minutes cache
            $tournament = Tournament::findOrFail($tournamentId);
            
            if ($type === 'auto') {
                $type = $tournament->format === 'swiss' ? 'swiss' : 'bracket';
            }

            if ($type === 'swiss') {
                return $this->getSwissStandings($tournamentId);
            } else {
                return $this->getBracketStandings($tournamentId);
            }
        });
    }

    /**
     * Optimized Swiss standings query
     */
    private function getSwissStandings(int $tournamentId): array
    {
        // Use optimized index for Swiss standings
        $standings = DB::table('tournament_teams')
                      ->select([
                          'tournament_teams.*',
                          'teams.name', 'teams.short_name', 'teams.logo', 'teams.region'
                      ])
                      ->join('teams', 'tournament_teams.team_id', '=', 'teams.id')
                      ->where('tournament_teams.tournament_id', $tournamentId)
                      ->where('tournament_teams.status', '!=', 'disqualified')
                      ->orderByDesc('tournament_teams.swiss_wins')
                      ->orderByDesc('tournament_teams.swiss_buchholz')
                      ->orderBy('tournament_teams.swiss_losses')
                      ->orderBy('tournament_teams.seed')
                      ->get();

        return [
            'type' => 'swiss',
            'standings' => $standings->map(function ($team, $index) {
                return [
                    'position' => $index + 1,
                    'team' => [
                        'id' => $team->team_id,
                        'name' => $team->name,
                        'short_name' => $team->short_name,
                        'logo' => $team->logo,
                        'region' => $team->region
                    ],
                    'wins' => $team->swiss_wins ?? 0,
                    'losses' => $team->swiss_losses ?? 0,
                    'score' => $team->swiss_score ?? 0,
                    'buchholz' => $team->swiss_buchholz ?? 0,
                    'seed' => $team->seed,
                    'status' => $team->status
                ];
            })
        ];
    }

    /**
     * Optimized bracket standings query
     */
    private function getBracketStandings(int $tournamentId): array
    {
        // Use optimized index for results leaderboard
        $standings = DB::table('tournament_teams')
                      ->select([
                          'tournament_teams.*',
                          'teams.name', 'teams.short_name', 'teams.logo', 'teams.region'
                      ])
                      ->join('teams', 'tournament_teams.team_id', '=', 'teams.id')
                      ->where('tournament_teams.tournament_id', $tournamentId)
                      ->whereNotNull('tournament_teams.placement')
                      ->orderBy('tournament_teams.placement')
                      ->get();

        return [
            'type' => 'bracket',
            'standings' => $standings->map(function ($team) {
                return [
                    'position' => $team->placement,
                    'team' => [
                        'id' => $team->team_id,
                        'name' => $team->name,
                        'short_name' => $team->short_name,
                        'logo' => $team->logo,
                        'region' => $team->region
                    ],
                    'prize_money' => $team->prize_money,
                    'points_earned' => $team->points_earned,
                    'seed' => $team->seed,
                    'status' => $team->status
                ];
            })
        ];
    }

    /**
     * Optimized team rankings query
     */
    public function getTeamRankings(?string $region = null, ?string $game = 'marvel_rivals', int $limit = 50): array
    {
        $cacheKey = $this->buildCacheKey('team_rankings', ['region' => $region, 'game' => $game, 'limit' => $limit]);
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($region, $game, $limit) {
            $query = Team::query()
                        ->select([
                            'id', 'name', 'short_name', 'logo', 'region', 'country',
                            'elo_rating', 'wins', 'losses', 'earnings', 'ranking'
                        ])
                        ->where('status', 'active');

            if ($game) {
                $query->where('game', $game);
            }

            if ($region) {
                $query->where('region', $region);
            }

            // Use optimized index for rankings
            $teams = $query->orderByDesc('elo_rating')
                          ->orderByDesc('wins')
                          ->limit($limit)
                          ->get();

            return $teams->map(function ($team, $index) {
                return array_merge($team->toArray(), [
                    'rank' => $index + 1,
                    'win_rate' => $team->wins + $team->losses > 0 ? 
                        round(($team->wins / ($team->wins + $team->losses)) * 100, 1) : 0
                ]);
            });
        });
    }

    /**
     * Optimized player rankings query by role
     */
    public function getPlayerRankingsByRole(string $role, ?string $region = null, int $limit = 50): array
    {
        $cacheKey = $this->buildCacheKey('player_rankings', ['role' => $role, 'region' => $region, 'limit' => $limit]);
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($role, $region, $limit) {
            $query = Player::query()
                          ->select([
                              'players.id', 'players.name', 'players.username', 'players.avatar',
                              'players.role', 'players.elo_rating', 'players.peak_elo',
                              'teams.id as team_id', 'teams.name as team_name', 
                              'teams.short_name as team_short_name', 'teams.logo as team_logo'
                          ])
                          ->leftJoin('teams', 'players.team_id', '=', 'teams.id')
                          ->where('players.role', $role)
                          ->where('players.status', 'active');

            if ($region) {
                $query->where('teams.region', $region);
            }

            // Use optimized index for player rating role
            $players = $query->orderByDesc('players.elo_rating')
                            ->limit($limit)
                            ->get();

            return $players->map(function ($player, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $player->id,
                    'name' => $player->name,
                    'username' => $player->username,
                    'avatar' => $player->avatar,
                    'role' => $player->role,
                    'elo_rating' => $player->elo_rating,
                    'peak_elo' => $player->peak_elo,
                    'team' => $player->team_id ? [
                        'id' => $player->team_id,
                        'name' => $player->team_name,
                        'short_name' => $player->team_short_name,
                        'logo' => $player->team_logo
                    ] : null
                ];
            });
        });
    }

    /**
     * Optimized live matches query
     */
    public function getLiveMatches(?int $tournamentId = null): array
    {
        $cacheKey = $this->buildCacheKey('live_matches', ['tournament_id' => $tournamentId]);
        
        return Cache::remember($cacheKey, 60, function () use ($tournamentId) { // 1 minute cache for live data
            $query = BracketMatch::query()
                                ->select([
                                    'bracket_matches.*',
                                    't1.name as team1_name', 't1.short_name as team1_short_name', 't1.logo as team1_logo',
                                    't2.name as team2_name', 't2.short_name as team2_short_name', 't2.logo as team2_logo',
                                    'tournaments.name as tournament_name', 'tournaments.format as tournament_format'
                                ])
                                ->leftJoin('teams as t1', 'bracket_matches.team1_id', '=', 't1.id')
                                ->leftJoin('teams as t2', 'bracket_matches.team2_id', '=', 't2.id')
                                ->leftJoin('tournaments', 'bracket_matches.tournament_id', '=', 'tournaments.id')
                                ->where('bracket_matches.status', 'live');

            if ($tournamentId) {
                $query->where('bracket_matches.tournament_id', $tournamentId);
            }

            // Use optimized index for live scoring
            $matches = $query->orderBy('bracket_matches.started_at', 'desc')->get();

            return $matches->map(function ($match) {
                return [
                    'id' => $match->id,
                    'tournament' => [
                        'id' => $match->tournament_id,
                        'name' => $match->tournament_name,
                        'format' => $match->tournament_format
                    ],
                    'teams' => [
                        'team1' => $match->team1_id ? [
                            'id' => $match->team1_id,
                            'name' => $match->team1_name,
                            'short_name' => $match->team1_short_name,
                            'logo' => $match->team1_logo
                        ] : null,
                        'team2' => $match->team2_id ? [
                            'id' => $match->team2_id,
                            'name' => $match->team2_name,
                            'short_name' => $match->team2_short_name,
                            'logo' => $match->team2_logo
                        ] : null
                    ],
                    'score' => [
                        'team1_score' => $match->team1_score,
                        'team2_score' => $match->team2_score
                    ],
                    'round' => $match->round,
                    'match_number' => $match->match_number,
                    'started_at' => $match->started_at
                ];
            });
        });
    }

    /**
     * Build cache key for queries
     */
    private function buildCacheKey(string $type, array $params, ...$additional): string
    {
        $allParams = array_merge($params, $additional);
        $paramHash = md5(serialize($allParams));
        return $this->cachePrefix . "{$type}_{$paramHash}";
    }

    /**
     * Invalidate query caches by pattern
     */
    public function invalidateQueryCaches(string $pattern): void
    {
        $keys = Cache::getRedis()->keys($this->cachePrefix . $pattern . '*');
        if (!empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }

    /**
     * Clear all query caches
     */
    public function clearAllQueryCaches(): void
    {
        $this->invalidateQueryCaches('');
    }
}