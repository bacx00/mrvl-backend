<?php

namespace App\Http\Controllers;

use App\Models\{Player, MatchPlayerStat, GameMatch, Team};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PlayerAnalyticsController extends Controller
{
    /**
     * Get comprehensive player analytics (VLR.gg style)
     */
    public function getPlayerAnalytics($playerId, Request $request)
    {
        try {
            $player = Player::with(['team', 'matchStats', 'teamHistory'])->findOrFail($playerId);
            
            $timeframe = $request->get('timeframe', '30d');
            $startDate = $this->getStartDate($timeframe);
            
            $analytics = [
                'player_info' => $this->getPlayerInfo($player),
                'overall_stats' => $this->getOverallStats($player, $startDate),
                'performance_trends' => $this->getPerformanceTrends($player, $startDate),
                'hero_statistics' => $this->getHeroStatistics($player, $startDate),
                'match_history' => $this->getRecentMatches($player, $request->get('limit', 20)),
                'team_performance' => $this->getTeamPerformanceWithPlayer($player, $startDate),
                'comparative_stats' => $this->getComparativeStats($player, $startDate),
                'form_analysis' => $this->getFormAnalysis($player, $startDate),
                'clutch_performance' => $this->getClutchPerformance($player, $startDate),
                'achievements_awards' => $this->getAchievementsAndAwards($player, $startDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'timeframe' => $timeframe,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get player info with current status
     */
    private function getPlayerInfo($player)
    {
        return [
            'id' => $player->id,
            'name' => $player->name,
            'username' => $player->username,
            'real_name' => $player->real_name,
            'avatar' => $player->avatar,
            'country' => $player->country,
            'country_code' => $player->country_code,
            'flag' => $player->flag,
            'role' => $player->role,
            'age' => $player->age,
            'current_team' => [
                'id' => $player->team?->id,
                'name' => $player->team?->name,
                'logo' => $player->team?->logo,
                'tenure' => $player->getCurrentTeamTenure()
            ],
            'status' => $player->status,
            'social_media' => $player->social_media ?? [],
            'earnings' => [
                'total' => $player->earnings ?? 0,
                'currency' => $player->earnings_currency ?? 'USD'
            ]
        ];
    }

    /**
     * Get overall performance statistics (VLR.gg style)
     */
    private function getOverallStats($player, $startDate)
    {
        $stats = $player->matchStats()
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->select([
                DB::raw('COUNT(*) as maps_played'),
                DB::raw('COUNT(DISTINCT match_id) as matches_played'),
                DB::raw('AVG(performance_rating) as avg_rating'),
                DB::raw('AVG(combat_score) as avg_acs'),
                DB::raw('AVG(kda) as avg_kda'),
                DB::raw('AVG(damage_per_round) as avg_adr'),
                DB::raw('AVG(kast_percentage) as avg_kast'),
                DB::raw('AVG(eliminations_per_round) as avg_kpr'),
                DB::raw('AVG(assists_per_round) as avg_apr'),
                DB::raw('AVG(first_kills / (SELECT COALESCE(NULLIF(team1_rounds + team2_rounds, 0), 1) FROM match_maps WHERE match_maps.match_id = player_match_stats.match_id LIMIT 1)) as avg_fkpr'),
                DB::raw('AVG(first_deaths / (SELECT COALESCE(NULLIF(team1_rounds + team2_rounds, 0), 1) FROM match_maps WHERE match_maps.match_id = player_match_stats.match_id LIMIT 1)) as avg_fdpr'),
                DB::raw('AVG(accuracy_percentage) as avg_hs'),
                DB::raw('SUM(eliminations) as total_kills'),
                DB::raw('SUM(deaths) as total_deaths'),
                DB::raw('SUM(assists) as total_assists'),
                DB::raw('SUM(damage_dealt) as total_damage'),
                DB::raw('SUM(healing_done) as total_healing'),
                DB::raw('MAX(best_killstreak) as longest_killstreak'),
                DB::raw('SUM(first_kills) as total_first_kills'),
                DB::raw('SUM(first_deaths) as total_first_deaths'),
                DB::raw('SUM(CASE WHEN player_of_the_match = 1 THEN 1 ELSE 0 END) as mvp_awards'),
                DB::raw('SUM(CASE WHEN player_of_the_map = 1 THEN 1 ELSE 0 END) as map_mvp_awards')
            ])
            ->first();

        if (!$stats || $stats->maps_played == 0) {
            return $this->getEmptyStats();
        }

        // Calculate win rate
        $wins = $player->matchStats()
            ->whereHas('match', function($query) use ($startDate, $player) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed')
                      ->where(function($q) use ($player) {
                          $q->where(function($subQ) use ($player) {
                              $subQ->where('team1_id', $player->team_id)
                                   ->whereColumn('team1_score', '>', 'team2_score');
                          })->orWhere(function($subQ) use ($player) {
                              $subQ->where('team2_id', $player->team_id)
                                   ->whereColumn('team2_score', '>', 'team1_score');
                          });
                      });
            })
            ->distinct('match_id')
            ->count();

        $winRate = $stats->matches_played > 0 ? ($wins / $stats->matches_played) * 100 : 0;

        return [
            'summary' => [
                'matches_played' => (int) $stats->matches_played,
                'maps_played' => (int) $stats->maps_played,
                'wins' => $wins,
                'win_rate' => round($winRate, 1),
                'mvp_awards' => (int) $stats->mvp_awards,
                'map_mvp_awards' => (int) $stats->map_mvp_awards
            ],
            'performance_metrics' => [
                'rating' => round($stats->avg_rating, 2),
                'acs' => round($stats->avg_acs, 1),
                'kd_ratio' => round($stats->avg_kda, 2),
                'adr' => round($stats->avg_adr, 1),
                'kast' => round($stats->avg_kast, 1),
                'kpr' => round($stats->avg_kpr, 2),
                'apr' => round($stats->avg_apr, 2),
                'fkpr' => round($stats->avg_fkpr, 2),
                'fdpr' => round($stats->avg_fdpr, 2),
                'hs_percentage' => round($stats->avg_hs, 1)
            ],
            'totals' => [
                'total_kills' => (int) $stats->total_kills,
                'total_deaths' => (int) $stats->total_deaths,
                'total_assists' => (int) $stats->total_assists,
                'total_damage' => (int) $stats->total_damage,
                'total_healing' => (int) $stats->total_healing,
                'total_first_kills' => (int) $stats->total_first_kills,
                'total_first_deaths' => (int) $stats->total_first_deaths,
                'longest_killstreak' => (int) $stats->longest_killstreak
            ]
        ];
    }

    /**
     * Get performance trends over time
     */
    private function getPerformanceTrends($player, $startDate)
    {
        $dailyStats = $player->matchStats()
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->join('matches', 'player_match_stats.match_id', '=', 'matches.id')
            ->selectRaw('
                DATE(matches.created_at) as date,
                AVG(performance_rating) as avg_rating,
                AVG(combat_score) as avg_acs,
                AVG(kda) as avg_kda,
                COUNT(*) as maps_played
            ')
            ->groupBy(DB::raw('DATE(matches.created_at)'))
            ->orderBy('date')
            ->get();

        // Calculate moving averages
        $trendData = [];
        $window = 5; // 5-day moving average
        
        foreach ($dailyStats as $index => $stat) {
            $windowStart = max(0, $index - $window + 1);
            $windowData = $dailyStats->slice($windowStart, $window);
            
            $trendData[] = [
                'date' => $stat->date,
                'rating' => round($stat->avg_rating, 2),
                'acs' => round($stat->avg_acs, 1),
                'kda' => round($stat->avg_kda, 2),
                'maps_played' => $stat->maps_played,
                'moving_avg_rating' => round($windowData->avg('avg_rating'), 2),
                'moving_avg_acs' => round($windowData->avg('avg_acs'), 1),
                'moving_avg_kda' => round($windowData->avg('avg_kda'), 2)
            ];
        }

        // Calculate trend direction
        $recentRating = collect($trendData)->last()['moving_avg_rating'] ?? 0;
        $previousRating = collect($trendData)->slice(-6, 1)->first()['moving_avg_rating'] ?? $recentRating;
        
        return [
            'daily_performance' => $trendData,
            'trend_analysis' => [
                'current_form' => $this->determineForm($recentRating),
                'rating_change' => round($recentRating - $previousRating, 2),
                'trend_direction' => $recentRating > $previousRating ? 'improving' : ($recentRating < $previousRating ? 'declining' : 'stable'),
                'consistency' => $this->calculateConsistency($trendData)
            ]
        ];
    }

    /**
     * Get hero-specific statistics
     */
    private function getHeroStatistics($player, $startDate)
    {
        $heroStats = $player->matchStats()
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->selectRaw('
                hero,
                hero_role,
                COUNT(*) as maps_played,
                AVG(performance_rating) as avg_rating,
                AVG(combat_score) as avg_acs,
                AVG(kda) as avg_kda,
                AVG(damage_per_round) as avg_adr,
                AVG(kast_percentage) as avg_kast,
                AVG(eliminations_per_round) as avg_kpr,
                AVG(assists_per_round) as avg_apr,
                SUM(eliminations) as total_kills,
                SUM(deaths) as total_deaths,
                SUM(assists) as total_assists,
                AVG(time_played_seconds) as avg_playtime
            ')
            ->whereNotNull('hero')
            ->groupBy('hero', 'hero_role')
            ->orderBy('maps_played', 'desc')
            ->get();

        // Calculate win rates per hero
        $heroWinRates = [];
        foreach ($heroStats as $heroStat) {
            $heroWins = $player->matchStats()
                ->where('hero', $heroStat->hero)
                ->whereHas('match', function($query) use ($startDate, $player) {
                    $query->where('created_at', '>=', $startDate)
                          ->where('status', 'completed')
                          ->where(function($q) use ($player) {
                              $q->where(function($subQ) use ($player) {
                                  $subQ->where('team1_id', $player->team_id)
                                       ->whereColumn('team1_score', '>', 'team2_score');
                              })->orWhere(function($subQ) use ($player) {
                                  $subQ->where('team2_id', $player->team_id)
                                       ->whereColumn('team2_score', '>', 'team1_score');
                              });
                          });
                })
                ->distinct('match_id')
                ->count();
            
            $heroWinRates[$heroStat->hero] = $heroStat->maps_played > 0 ? ($heroWins / $heroStat->maps_played) * 100 : 0;
        }

        return $heroStats->map(function($stat) use ($heroWinRates) {
            return [
                'hero' => $stat->hero,
                'hero_role' => $stat->hero_role,
                'maps_played' => (int) $stat->maps_played,
                'win_rate' => round($heroWinRates[$stat->hero] ?? 0, 1),
                'avg_rating' => round($stat->avg_rating, 2),
                'avg_acs' => round($stat->avg_acs, 1),
                'avg_kda' => round($stat->avg_kda, 2),
                'avg_adr' => round($stat->avg_adr, 1),
                'avg_kast' => round($stat->avg_kast, 1),
                'avg_kpr' => round($stat->avg_kpr, 2),
                'avg_apr' => round($stat->avg_apr, 2),
                'total_kills' => (int) $stat->total_kills,
                'total_deaths' => (int) $stat->total_deaths,
                'total_assists' => (int) $stat->total_assists,
                'avg_playtime_minutes' => round($stat->avg_playtime / 60, 1)
            ];
        })->values();
    }

    /**
     * Get recent match history with detailed stats
     */
    private function getRecentMatches($player, $limit = 20)
    {
        $matches = $player->matchStats()
            ->with(['match.team1', 'match.team2', 'match.event'])
            ->whereHas('match', function($query) {
                $query->where('status', 'completed');
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $matches->map(function($stat) use ($player) {
            $match = $stat->match;
            $isTeam1 = $match->team1_id == $player->team_id;
            $won = ($isTeam1 && $match->team1_score > $match->team2_score) || 
                   (!$isTeam1 && $match->team2_score > $match->team1_score);

            return [
                'match_id' => $match->id,
                'date' => $match->created_at->format('Y-m-d H:i'),
                'event' => [
                    'id' => $match->event?->id,
                    'name' => $match->event?->name ?? 'Match'
                ],
                'opponent' => [
                    'id' => $isTeam1 ? $match->team2?->id : $match->team1?->id,
                    'name' => $isTeam1 ? $match->team2?->name : $match->team1?->name,
                    'logo' => $isTeam1 ? $match->team2?->logo : $match->team1?->logo
                ],
                'result' => [
                    'won' => $won,
                    'score' => [$match->team1_score, $match->team2_score],
                    'team_score' => $isTeam1 ? $match->team1_score : $match->team2_score,
                    'opponent_score' => $isTeam1 ? $match->team2_score : $match->team1_score
                ],
                'performance' => [
                    'hero' => $stat->hero,
                    'rating' => round($stat->performance_rating, 2),
                    'acs' => round($stat->combat_score, 1),
                    'kda' => round($stat->kda, 2),
                    'kills' => $stat->eliminations,
                    'deaths' => $stat->deaths,
                    'assists' => $stat->assists,
                    'adr' => round($stat->damage_per_round, 1),
                    'kast' => round($stat->kast_percentage, 1),
                    'first_kills' => $stat->first_kills,
                    'first_deaths' => $stat->first_deaths,
                    'mvp' => $stat->player_of_the_match
                ]
            ];
        })->values();
    }

    /**
     * Get team performance when this player is playing
     */
    private function getTeamPerformanceWithPlayer($player, $startDate)
    {
        if (!$player->team) {
            return null;
        }

        $teamStats = GameMatch::where(function($query) use ($player) {
                $query->where('team1_id', $player->team_id)
                      ->orWhere('team2_id', $player->team_id);
            })
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->whereHas('playerStats', function($query) use ($player) {
                $query->where('player_id', $player->id);
            })
            ->selectRaw('
                COUNT(*) as matches_with_player,
                SUM(CASE 
                    WHEN (team1_id = ? AND team1_score > team2_score) OR 
                         (team2_id = ? AND team2_score > team1_score) 
                    THEN 1 ELSE 0 END) as wins_with_player
            ', [$player->team_id, $player->team_id])
            ->first();

        $totalTeamMatches = GameMatch::where(function($query) use ($player) {
                $query->where('team1_id', $player->team_id)
                      ->orWhere('team2_id', $player->team_id);
            })
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->count();

        $totalTeamWins = GameMatch::where(function($query) use ($player) {
                $query->where('team1_id', $player->team_id)
                      ->orWhere('team2_id', $player->team_id);
            })
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->where(function($query) use ($player) {
                $query->where(function($subQ) use ($player) {
                    $subQ->where('team1_id', $player->team_id)
                         ->whereColumn('team1_score', '>', 'team2_score');
                })->orWhere(function($subQ) use ($player) {
                    $subQ->where('team2_id', $player->team_id)
                         ->whereColumn('team2_score', '>', 'team1_score');
                });
            })
            ->count();

        $winRateWithPlayer = $teamStats->matches_with_player > 0 
            ? ($teamStats->wins_with_player / $teamStats->matches_with_player) * 100 
            : 0;

        $totalTeamWinRate = $totalTeamMatches > 0 
            ? ($totalTeamWins / $totalTeamMatches) * 100 
            : 0;

        return [
            'team' => [
                'id' => $player->team->id,
                'name' => $player->team->name,
                'logo' => $player->team->logo
            ],
            'with_player' => [
                'matches' => (int) $teamStats->matches_with_player,
                'wins' => (int) $teamStats->wins_with_player,
                'win_rate' => round($winRateWithPlayer, 1)
            ],
            'team_overall' => [
                'matches' => $totalTeamMatches,
                'wins' => $totalTeamWins,
                'win_rate' => round($totalTeamWinRate, 1)
            ],
            'impact' => [
                'win_rate_difference' => round($winRateWithPlayer - $totalTeamWinRate, 1),
                'participation_rate' => $totalTeamMatches > 0 
                    ? round(($teamStats->matches_with_player / $totalTeamMatches) * 100, 1) 
                    : 0
            ]
        ];
    }

    /**
     * Get comparative statistics against role peers
     */
    private function getComparativeStats($player, $startDate)
    {
        if (!$player->role) {
            return null;
        }

        // Get role averages
        $roleAverages = Player::where('role', $player->role)
            ->whereHas('matchStats', function($query) use ($startDate) {
                $query->whereHas('match', function($subQuery) use ($startDate) {
                    $subQuery->where('created_at', '>=', $startDate)
                             ->where('status', 'completed');
                });
            })
            ->with(['matchStats' => function($query) use ($startDate) {
                $query->whereHas('match', function($subQuery) use ($startDate) {
                    $subQuery->where('created_at', '>=', $startDate)
                             ->where('status', 'completed');
                });
            }])
            ->get()
            ->map(function($p) {
                $stats = $p->matchStats;
                return [
                    'rating' => $stats->avg('performance_rating'),
                    'acs' => $stats->avg('combat_score'),
                    'kda' => $stats->avg('kda'),
                    'adr' => $stats->avg('damage_per_round'),
                    'kast' => $stats->avg('kast_percentage'),
                    'kpr' => $stats->avg('eliminations_per_round'),
                    'apr' => $stats->avg('assists_per_round')
                ];
            });

        $playerStats = $this->getOverallStats($player, $startDate)['performance_metrics'];

        return [
            'role' => $player->role,
            'comparisons' => [
                'rating' => [
                    'player' => $playerStats['rating'],
                    'role_average' => round($roleAverages->avg('rating'), 2),
                    'percentile' => $this->calculatePercentile($playerStats['rating'], $roleAverages->pluck('rating'))
                ],
                'acs' => [
                    'player' => $playerStats['acs'],
                    'role_average' => round($roleAverages->avg('acs'), 1),
                    'percentile' => $this->calculatePercentile($playerStats['acs'], $roleAverages->pluck('acs'))
                ],
                'kda' => [
                    'player' => $playerStats['kd_ratio'],
                    'role_average' => round($roleAverages->avg('kda'), 2),
                    'percentile' => $this->calculatePercentile($playerStats['kd_ratio'], $roleAverages->pluck('kda'))
                ],
                'adr' => [
                    'player' => $playerStats['adr'],
                    'role_average' => round($roleAverages->avg('adr'), 1),
                    'percentile' => $this->calculatePercentile($playerStats['adr'], $roleAverages->pluck('adr'))
                ]
            ],
            'ranking' => [
                'among_role' => $this->getRoleRanking($player, $startDate),
                'total_players_in_role' => $roleAverages->count()
            ]
        ];
    }

    /**
     * Get form analysis (hot/cold streaks)
     */
    private function getFormAnalysis($player, $startDate)
    {
        $recentMatches = $player->matchStats()
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->with('match')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentMatches->isEmpty()) {
            return null;
        }

        // Calculate streaks
        $currentStreak = $this->calculateCurrentStreak($recentMatches, $player);
        $bestPerformances = $recentMatches->sortByDesc('performance_rating')->take(3);
        $worstPerformances = $recentMatches->sortBy('performance_rating')->take(3);

        return [
            'current_form' => $this->determineForm($recentMatches->avg('performance_rating')),
            'recent_rating_avg' => round($recentMatches->avg('performance_rating'), 2),
            'streak' => $currentStreak,
            'consistency' => [
                'rating_std_dev' => round($this->calculateStandardDeviation($recentMatches->pluck('performance_rating')), 2),
                'rating_range' => [
                    'min' => round($recentMatches->min('performance_rating'), 2),
                    'max' => round($recentMatches->max('performance_rating'), 2)
                ]
            ],
            'best_performances' => $bestPerformances->map(function($stat) {
                return [
                    'match_date' => $stat->match->created_at->format('Y-m-d'),
                    'rating' => round($stat->performance_rating, 2),
                    'acs' => round($stat->combat_score, 1),
                    'kda' => round($stat->kda, 2),
                    'hero' => $stat->hero
                ];
            })->values(),
            'improvements_needed' => $worstPerformances->map(function($stat) {
                return [
                    'match_date' => $stat->match->created_at->format('Y-m-d'),
                    'rating' => round($stat->performance_rating, 2),
                    'issues' => $this->identifyPerformanceIssues($stat)
                ];
            })->values()
        ];
    }

    /**
     * Get clutch performance statistics
     */
    private function getClutchPerformance($player, $startDate)
    {
        // This would require more detailed round-by-round data
        // For now, we'll use match-ending performance as a proxy
        
        $clutchStats = $player->matchStats()
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->selectRaw('
                COUNT(*) as total_maps,
                AVG(CASE WHEN first_kills > first_deaths THEN performance_rating END) as avg_rating_when_ahead,
                AVG(CASE WHEN first_kills <= first_deaths THEN performance_rating END) as avg_rating_when_behind,
                SUM(CASE WHEN first_kills > 0 THEN 1 ELSE 0 END) as maps_with_first_kills,
                AVG(best_killstreak) as avg_best_streak
            ')
            ->first();

        if (!$clutchStats || $clutchStats->total_maps == 0) {
            return null;
        }

        return [
            'pressure_performance' => [
                'rating_when_ahead' => round($clutchStats->avg_rating_when_ahead ?? 0, 2),
                'rating_when_behind' => round($clutchStats->avg_rating_when_behind ?? 0, 2),
                'pressure_differential' => round(($clutchStats->avg_rating_when_behind ?? 0) - ($clutchStats->avg_rating_when_ahead ?? 0), 2)
            ],
            'opening_impact' => [
                'first_kill_participation' => $clutchStats->total_maps > 0 
                    ? round(($clutchStats->maps_with_first_kills / $clutchStats->total_maps) * 100, 1) 
                    : 0,
                'avg_best_killstreak' => round($clutchStats->avg_best_streak, 1)
            ],
            'clutch_rating' => $this->calculateClutchRating($clutchStats)
        ];
    }

    /**
     * Get achievements and awards
     */
    private function getAchievementsAndAwards($player, $startDate)
    {
        $awards = $player->matchStats()
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->selectRaw('
                SUM(CASE WHEN player_of_the_match = 1 THEN 1 ELSE 0 END) as mvp_awards,
                SUM(CASE WHEN player_of_the_map = 1 THEN 1 ELSE 0 END) as map_mvp_awards,
                MAX(performance_rating) as highest_rating,
                MAX(combat_score) as highest_acs,
                MAX(eliminations) as most_kills_map,
                MAX(best_killstreak) as longest_killstreak
            ')
            ->first();

        return [
            'awards' => [
                'match_mvps' => (int) ($awards->mvp_awards ?? 0),
                'map_mvps' => (int) ($awards->map_mvp_awards ?? 0)
            ],
            'records' => [
                'highest_rating' => round($awards->highest_rating ?? 0, 2),
                'highest_acs' => round($awards->highest_acs ?? 0, 1),
                'most_kills_single_map' => (int) ($awards->most_kills_map ?? 0),
                'longest_killstreak' => (int) ($awards->longest_killstreak ?? 0)
            ],
            'achievements' => $player->achievements ?? []
        ];
    }

    // Helper methods

    private function getStartDate($timeframe)
    {
        return match($timeframe) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '6m' => now()->subMonths(6),
            '1y' => now()->subYear(),
            'all' => Carbon::create(2020, 1, 1), // Very early date
            default => now()->subDays(30)
        };
    }

    private function getEmptyStats()
    {
        return [
            'summary' => [
                'matches_played' => 0,
                'maps_played' => 0,
                'wins' => 0,
                'win_rate' => 0,
                'mvp_awards' => 0,
                'map_mvp_awards' => 0
            ],
            'performance_metrics' => [
                'rating' => 0,
                'acs' => 0,
                'kd_ratio' => 0,
                'adr' => 0,
                'kast' => 0,
                'kpr' => 0,
                'apr' => 0,
                'fkpr' => 0,
                'fdpr' => 0,
                'hs_percentage' => 0
            ],
            'totals' => [
                'total_kills' => 0,
                'total_deaths' => 0,
                'total_assists' => 0,
                'total_damage' => 0,
                'total_healing' => 0,
                'total_first_kills' => 0,
                'total_first_deaths' => 0,
                'longest_killstreak' => 0
            ]
        ];
    }

    private function determineForm($rating)
    {
        if ($rating >= 1.3) return 'excellent';
        if ($rating >= 1.1) return 'good';
        if ($rating >= 0.9) return 'average';
        if ($rating >= 0.7) return 'below_average';
        return 'poor';
    }

    private function calculateConsistency($trendData)
    {
        if (empty($trendData)) return 0;
        
        $ratings = collect($trendData)->pluck('rating');
        $stdDev = $this->calculateStandardDeviation($ratings);
        
        // Lower standard deviation = higher consistency
        return max(0, round(100 - ($stdDev * 50), 1));
    }

    private function calculateStandardDeviation($values)
    {
        $count = count($values);
        if ($count < 2) return 0;
        
        $mean = $values->avg();
        $sumSquares = $values->sum(function($value) use ($mean) {
            return pow($value - $mean, 2);
        });
        
        return sqrt($sumSquares / ($count - 1));
    }

    private function calculatePercentile($value, $dataset)
    {
        $sorted = $dataset->filter()->sort()->values();
        if ($sorted->isEmpty()) return 50;
        
        $count = $sorted->count();
        $rank = $sorted->search(function($item) use ($value) {
            return $item >= $value;
        });
        
        if ($rank === false) $rank = $count;
        
        return round(($rank / $count) * 100);
    }

    private function getRoleRanking($player, $startDate)
    {
        $playerRating = $this->getOverallStats($player, $startDate)['performance_metrics']['rating'];
        
        $betterPlayers = Player::where('role', $player->role)
            ->where('id', '!=', $player->id)
            ->whereHas('matchStats', function($query) use ($startDate, $playerRating) {
                $query->whereHas('match', function($subQuery) use ($startDate) {
                    $subQuery->where('created_at', '>=', $startDate)
                             ->where('status', 'completed');
                });
            })
            ->get()
            ->filter(function($p) use ($startDate, $playerRating) {
                return $this->getOverallStats($p, $startDate)['performance_metrics']['rating'] > $playerRating;
            })
            ->count();
            
        return $betterPlayers + 1;
    }

    private function calculateCurrentStreak($matches, $player)
    {
        $streak = 0;
        $type = null;
        
        foreach ($matches as $stat) {
            $match = $stat->match;
            $won = ($match->team1_id == $player->team_id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $player->team_id && $match->team2_score > $match->team1_score);
            
            if ($type === null) {
                $type = $won ? 'win' : 'loss';
                $streak = 1;
            } elseif (($type === 'win' && $won) || ($type === 'loss' && !$won)) {
                $streak++;
            } else {
                break;
            }
        }
        
        return [
            'type' => $type,
            'count' => $streak
        ];
    }

    private function identifyPerformanceIssues($stat)
    {
        $issues = [];
        
        if ($stat->kda < 0.8) $issues[] = 'Poor K/D ratio';
        if ($stat->combat_score < 150) $issues[] = 'Low combat score';
        if ($stat->kast_percentage < 60) $issues[] = 'Low KAST%';
        if ($stat->first_deaths > $stat->first_kills) $issues[] = 'Negative first kill/death ratio';
        if ($stat->damage_per_round < 100) $issues[] = 'Low damage output';
        
        return empty($issues) ? ['Inconsistent performance'] : $issues;
    }

    private function calculateClutchRating($stats)
    {
        $baseRating = 50;
        
        if ($stats->avg_rating_when_behind > $stats->avg_rating_when_ahead) {
            $baseRating += 25; // Performs better under pressure
        }
        
        if ($stats->avg_best_streak > 3) {
            $baseRating += 15; // Good at maintaining streaks
        }
        
        if (($stats->maps_with_first_kills / max($stats->total_maps, 1)) > 0.6) {
            $baseRating += 10; // Good opening impact
        }
        
        return min(100, $baseRating);
    }

    /**
     * Get player leaderboard for a specific timeframe
     */
    public function getPlayerLeaderboard(Request $request)
    {
        try {
            $timeframe = $request->get('timeframe', '30d');
            $role = $request->get('role');
            $region = $request->get('region');
            $limit = min($request->get('limit', 50), 100);
            $sortBy = $request->get('sort_by', 'rating');
            
            $startDate = $this->getStartDate($timeframe);
            
            $query = Player::whereHas('matchStats', function($query) use ($startDate) {
                $query->whereHas('match', function($subQuery) use ($startDate) {
                    $subQuery->where('created_at', '>=', $startDate)
                             ->where('status', 'completed');
                });
            });
            
            if ($role) {
                $query->where('role', $role);
            }
            
            if ($region) {
                $query->where('region', $region);
            }
            
            $players = $query->with(['team', 'matchStats' => function($query) use ($startDate) {
                $query->whereHas('match', function($subQuery) use ($startDate) {
                    $subQuery->where('created_at', '>=', $startDate)
                             ->where('status', 'completed');
                });
            }])
            ->get()
            ->map(function($player) use ($startDate) {
                $stats = $this->getOverallStats($player, $startDate);
                return array_merge([
                    'id' => $player->id,
                    'name' => $player->name,
                    'team' => [
                        'id' => $player->team?->id,
                        'name' => $player->team?->name,
                        'logo' => $player->team?->logo
                    ],
                    'role' => $player->role,
                    'region' => $player->region,
                    'country' => $player->country,
                    'flag' => $player->flag
                ], $stats['performance_metrics'], $stats['summary']);
            })
            ->sortByDesc($sortBy)
            ->take($limit)
            ->values();

            return response()->json([
                'success' => true,
                'data' => $players,
                'filters' => [
                    'timeframe' => $timeframe,
                    'role' => $role,
                    'region' => $region,
                    'sort_by' => $sortBy
                ],
                'total_players' => $players->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player leaderboard: ' . $e->getMessage()
            ], 500);
        }
    }
}