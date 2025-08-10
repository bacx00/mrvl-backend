<?php

namespace App\Http\Controllers;

use App\Models\{Team, Player, GameMatch, Event, MatchPlayerStat};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TeamAnalyticsController extends Controller
{
    /**
     * Get comprehensive team analytics
     */
    public function getTeamAnalytics($teamId, Request $request)
    {
        try {
            $team = Team::with(['players', 'homeMatches', 'awayMatches'])->findOrFail($teamId);
            
            $timeframe = $request->get('timeframe', '30d');
            $startDate = $this->getStartDate($timeframe);
            
            $analytics = [
                'team_info' => $this->getTeamInfo($team),
                'overall_performance' => $this->getOverallPerformance($team, $startDate),
                'recent_form' => $this->getRecentForm($team, $startDate),
                'head_to_head_records' => $this->getHeadToHeadRecords($team, $startDate),
                'player_contributions' => $this->getPlayerContributions($team, $startDate),
                'map_performance' => $this->getMapPerformance($team, $startDate),
                'tactical_analysis' => $this->getTacticalAnalysis($team, $startDate),
                'tournament_history' => $this->getTournamentHistory($team, $startDate),
                'comparative_standings' => $this->getComparativeStandings($team, $startDate),
                'performance_trends' => $this->getPerformanceTrends($team, $startDate)
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
                'message' => 'Error fetching team analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team information
     */
    private function getTeamInfo($team)
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo' => $team->logo,
            'region' => $team->region,
            'country' => $team->country,
            'flag' => $team->flag,
            'founded' => $team->founded_date,
            'current_rating' => $team->rating ?? 1500,
            'current_rank' => $team->rank,
            'peak_rating' => $team->peak_elo ?? $team->rating,
            'coach' => [
                'name' => $team->coach_name,
                'picture' => $team->coach_image,
                'nationality' => $team->coach_nationality
            ],
            'roster' => $team->players->map(function($player) {
                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'username' => $player->username,
                    'role' => $player->role,
                    'avatar' => $player->avatar,
                    'rating' => $player->rating ?? 1500
                ];
            })->values(),
            'social_media' => $team->social_media ?? [],
            'achievements' => $team->achievements ?? []
        ];
    }

    /**
     * Get overall team performance metrics
     */
    private function getOverallPerformance($team, $startDate)
    {
        $matches = GameMatch::where(function($query) use ($team) {
                $query->where('team1_id', $team->id)
                      ->orWhere('team2_id', $team->id);
            })
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->get();

        if ($matches->isEmpty()) {
            return $this->getEmptyTeamStats();
        }

        $wins = $matches->filter(function($match) use ($team) {
            return ($match->team1_id == $team->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $team->id && $match->team2_score > $match->team1_score);
        });

        $totalMaps = $matches->sum(function($match) use ($team) {
            return $match->team1_id == $team->id ? $match->team1_score + $match->team2_score : 
                   $match->team2_score + $match->team1_score;
        });

        $mapsWon = $matches->sum(function($match) use ($team) {
            return $match->team1_id == $team->id ? $match->team1_score : $match->team2_score;
        });

        // Get detailed player statistics for team aggregation
        $teamStats = MatchPlayerStat::where('team_id', $team->id)
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->selectRaw('
                COUNT(*) as total_player_performances,
                AVG(performance_rating) as avg_team_rating,
                AVG(combat_score) as avg_team_acs,
                AVG(kda) as avg_team_kda,
                SUM(eliminations) as total_kills,
                SUM(deaths) as total_deaths,
                SUM(assists) as total_assists,
                SUM(damage_dealt) as total_damage,
                SUM(healing_done) as total_healing,
                SUM(first_kills) as total_first_kills,
                SUM(first_deaths) as total_first_deaths,
                AVG(kast_percentage) as avg_kast
            ')
            ->first();

        $avgRoundsPerMap = $this->getAverageRoundsPerMap($team, $startDate);

        return [
            'match_record' => [
                'matches_played' => $matches->count(),
                'wins' => $wins->count(),
                'losses' => $matches->count() - $wins->count(),
                'win_rate' => $matches->count() > 0 ? round(($wins->count() / $matches->count()) * 100, 1) : 0,
                'maps_played' => $totalMaps,
                'maps_won' => $mapsWon,
                'maps_lost' => $totalMaps - $mapsWon,
                'map_win_rate' => $totalMaps > 0 ? round(($mapsWon / $totalMaps) * 100, 1) : 0
            ],
            'performance_metrics' => [
                'team_rating' => round($teamStats->avg_team_rating ?? 0, 2),
                'team_acs' => round($teamStats->avg_team_acs ?? 0, 1),
                'team_kda' => round($teamStats->avg_team_kda ?? 0, 2),
                'kast_percentage' => round($teamStats->avg_kast ?? 0, 1),
                'kills_per_round' => $avgRoundsPerMap > 0 ? round($teamStats->total_kills / ($matches->count() * $avgRoundsPerMap), 2) : 0,
                'deaths_per_round' => $avgRoundsPerMap > 0 ? round($teamStats->total_deaths / ($matches->count() * $avgRoundsPerMap), 2) : 0,
                'first_kill_rate' => ($teamStats->total_first_kills + $teamStats->total_first_deaths) > 0 
                    ? round(($teamStats->total_first_kills / ($teamStats->total_first_kills + $teamStats->total_first_deaths)) * 100, 1) : 0
            ],
            'totals' => [
                'total_kills' => (int) ($teamStats->total_kills ?? 0),
                'total_deaths' => (int) ($teamStats->total_deaths ?? 0),
                'total_assists' => (int) ($teamStats->total_assists ?? 0),
                'total_damage' => (int) ($teamStats->total_damage ?? 0),
                'total_healing' => (int) ($teamStats->total_healing ?? 0),
                'total_first_kills' => (int) ($teamStats->total_first_kills ?? 0),
                'total_first_deaths' => (int) ($teamStats->total_first_deaths ?? 0)
            ]
        ];
    }

    /**
     * Get recent form analysis (win/loss streaks, performance trends)
     */
    private function getRecentForm($team, $startDate)
    {
        $recentMatches = GameMatch::where(function($query) use ($team) {
                $query->where('team1_id', $team->id)
                      ->orWhere('team2_id', $team->id);
            })
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentMatches->isEmpty()) {
            return null;
        }

        $form = $recentMatches->map(function($match) use ($team) {
            $won = ($match->team1_id == $team->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $team->id && $match->team2_score > $match->team1_score);
            return $won ? 'W' : 'L';
        });

        $currentStreak = $this->calculateTeamStreak($recentMatches, $team);
        
        return [
            'recent_form' => $form->take(5)->values()->toArray(),
            'extended_form' => $form->values()->toArray(),
            'current_streak' => $currentStreak,
            'form_rating' => $this->calculateFormRating($form),
            'momentum' => $this->calculateMomentum($recentMatches, $team)
        ];
    }

    /**
     * Get head-to-head records against other teams
     */
    private function getHeadToHeadRecords($team, $startDate)
    {
        $headToHeads = GameMatch::where(function($query) use ($team) {
                $query->where('team1_id', $team->id)
                      ->orWhere('team2_id', $team->id);
            })
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->with(['team1', 'team2'])
            ->get()
            ->groupBy(function($match) use ($team) {
                return $match->team1_id == $team->id ? $match->team2_id : $match->team1_id;
            })
            ->map(function($matches) use ($team) {
                $opponent = $matches->first()->team1_id == $team->id 
                    ? $matches->first()->team2 
                    : $matches->first()->team1;

                $wins = $matches->filter(function($match) use ($team) {
                    return ($match->team1_id == $team->id && $match->team1_score > $match->team2_score) ||
                           ($match->team2_id == $team->id && $match->team2_score > $match->team1_score);
                })->count();

                $totalMaps = $matches->sum(function($match) use ($team) {
                    return $match->team1_id == $team->id 
                        ? $match->team1_score + $match->team2_score
                        : $match->team2_score + $match->team1_score;
                });

                $mapsWon = $matches->sum(function($match) use ($team) {
                    return $match->team1_id == $team->id 
                        ? $match->team1_score 
                        : $match->team2_score;
                });

                return [
                    'opponent' => [
                        'id' => $opponent->id,
                        'name' => $opponent->name,
                        'logo' => $opponent->logo,
                        'region' => $opponent->region
                    ],
                    'matches_played' => $matches->count(),
                    'wins' => $wins,
                    'losses' => $matches->count() - $wins,
                    'win_rate' => $matches->count() > 0 ? round(($wins / $matches->count()) * 100, 1) : 0,
                    'maps_played' => $totalMaps,
                    'maps_won' => $mapsWon,
                    'maps_lost' => $totalMaps - $mapsWon,
                    'map_win_rate' => $totalMaps > 0 ? round(($mapsWon / $totalMaps) * 100, 1) : 0,
                    'last_played' => $matches->max('created_at'),
                    'dominance' => $this->calculateDominance($wins, $matches->count())
                ];
            })
            ->sortByDesc('matches_played')
            ->values();

        return [
            'total_opponents' => $headToHeads->count(),
            'dominant_matchups' => $headToHeads->where('win_rate', '>=', 70)->count(),
            'competitive_matchups' => $headToHeads->where('win_rate', '>=', 40)->where('win_rate', '<', 70)->count(),
            'struggling_matchups' => $headToHeads->where('win_rate', '<', 40)->count(),
            'detailed_records' => $headToHeads->take(15)->values()
        ];
    }

    /**
     * Get individual player contributions to team success
     */
    private function getPlayerContributions($team, $startDate)
    {
        $playerStats = MatchPlayerStat::where('team_id', $team->id)
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->with('player')
            ->selectRaw('
                player_id,
                COUNT(*) as maps_played,
                AVG(performance_rating) as avg_rating,
                AVG(combat_score) as avg_acs,
                AVG(kda) as avg_kda,
                SUM(eliminations) as total_kills,
                SUM(deaths) as total_deaths,
                SUM(assists) as total_assists,
                AVG(kast_percentage) as avg_kast,
                SUM(CASE WHEN player_of_the_match = 1 THEN 1 ELSE 0 END) as mvp_awards,
                MAX(performance_rating) as peak_rating
            ')
            ->groupBy('player_id')
            ->get();

        // Calculate win rates for each player
        $playerContributions = $playerStats->map(function($stat) use ($team) {
            $player = $stat->player;
            
            // Count wins when this player participated
            $winsWithPlayer = MatchPlayerStat::where('player_id', $stat->player_id)
                ->where('team_id', $team->id)
                ->whereHas('match', function($query) use ($team) {
                    $query->where('status', 'completed')
                          ->where(function($q) use ($team) {
                              $q->where(function($subQ) use ($team) {
                                  $subQ->where('team1_id', $team->id)
                                       ->whereColumn('team1_score', '>', 'team2_score');
                              })->orWhere(function($subQ) use ($team) {
                                  $subQ->where('team2_id', $team->id)
                                       ->whereColumn('team2_score', '>', 'team1_score');
                              });
                          });
                })
                ->distinct('match_id')
                ->count();

            $totalMatchesWithPlayer = MatchPlayerStat::where('player_id', $stat->player_id)
                ->where('team_id', $team->id)
                ->whereHas('match', function($query) {
                    $query->where('status', 'completed');
                })
                ->distinct('match_id')
                ->count();

            $winRateWithPlayer = $totalMatchesWithPlayer > 0 
                ? ($winsWithPlayer / $totalMatchesWithPlayer) * 100 
                : 0;

            return [
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'username' => $player->username,
                    'role' => $player->role,
                    'avatar' => $player->avatar
                ],
                'participation' => [
                    'maps_played' => (int) $stat->maps_played,
                    'win_rate' => round($winRateWithPlayer, 1),
                    'mvp_awards' => (int) $stat->mvp_awards
                ],
                'performance' => [
                    'avg_rating' => round($stat->avg_rating, 2),
                    'avg_acs' => round($stat->avg_acs, 1),
                    'avg_kda' => round($stat->avg_kda, 2),
                    'avg_kast' => round($stat->avg_kast, 1),
                    'peak_rating' => round($stat->peak_rating, 2)
                ],
                'impact_score' => $this->calculatePlayerImpactScore($stat, $winRateWithPlayer)
            ];
        })->sortByDesc('impact_score')->values();

        return $playerContributions;
    }

    /**
     * Get map-specific performance
     */
    private function getMapPerformance($team, $startDate)
    {
        $mapStats = DB::table('match_maps as mm')
            ->join('matches as m', 'mm.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->where('m.status', 'completed')
            ->where(function($query) use ($team) {
                $query->where('m.team1_id', $team->id)
                      ->orWhere('m.team2_id', $team->id);
            })
            ->selectRaw('
                mm.map_name,
                COUNT(*) as times_played,
                SUM(CASE 
                    WHEN (m.team1_id = ? AND mm.team1_score > mm.team2_score) OR 
                         (m.team2_id = ? AND mm.team2_score > mm.team1_score)
                    THEN 1 ELSE 0 END) as wins,
                AVG(CASE 
                    WHEN m.team1_id = ? THEN mm.team1_score 
                    ELSE mm.team2_score END) as avg_team_score,
                AVG(CASE 
                    WHEN m.team1_id = ? THEN mm.team2_score 
                    ELSE mm.team1_score END) as avg_opponent_score,
                AVG(mm.duration_seconds) as avg_duration
            ', [$team->id, $team->id, $team->id, $team->id])
            ->groupBy('mm.map_name')
            ->orderBy('times_played', 'desc')
            ->get();

        return $mapStats->map(function($stat) {
            $winRate = $stat->times_played > 0 ? ($stat->wins / $stat->times_played) * 100 : 0;
            
            return [
                'map_name' => $stat->map_name,
                'times_played' => (int) $stat->times_played,
                'wins' => (int) $stat->wins,
                'losses' => (int) $stat->times_played - (int) $stat->wins,
                'win_rate' => round($winRate, 1),
                'avg_team_score' => round($stat->avg_team_score, 1),
                'avg_opponent_score' => round($stat->avg_opponent_score, 1),
                'avg_duration_minutes' => round($stat->avg_duration / 60, 1),
                'comfort_level' => $this->determineMapComfort($winRate, $stat->times_played)
            ];
        })->values();
    }

    /**
     * Get tactical analysis (hero usage, compositions, etc.)
     */
    private function getTacticalAnalysis($team, $startDate)
    {
        $heroUsage = MatchPlayerStat::where('team_id', $team->id)
            ->whereHas('match', function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            })
            ->selectRaw('
                hero,
                hero_role,
                COUNT(*) as pick_count,
                AVG(performance_rating) as avg_rating_on_hero,
                COUNT(DISTINCT player_id) as players_used
            ')
            ->whereNotNull('hero')
            ->groupBy('hero', 'hero_role')
            ->orderBy('pick_count', 'desc')
            ->get();

        $roleDistribution = $heroUsage->groupBy('hero_role')->map(function($heroes) {
            return [
                'total_picks' => $heroes->sum('pick_count'),
                'unique_heroes' => $heroes->count(),
                'avg_performance' => round($heroes->avg('avg_rating_on_hero'), 2)
            ];
        });

        return [
            'hero_preferences' => $heroUsage->map(function($hero) {
                return [
                    'hero' => $hero->hero,
                    'role' => $hero->hero_role,
                    'pick_count' => (int) $hero->pick_count,
                    'pick_rate' => 0, // Would need total maps to calculate
                    'avg_performance' => round($hero->avg_rating_on_hero, 2),
                    'players_used' => (int) $hero->players_used,
                    'versatility' => $hero->players_used > 1 ? 'flexible' : 'specialized'
                ];
            })->take(15)->values(),
            'role_distribution' => $roleDistribution,
            'tactical_flexibility' => [
                'unique_heroes_used' => $heroUsage->count(),
                'role_diversity' => $roleDistribution->count(),
                'adaptability_score' => $this->calculateAdaptabilityScore($heroUsage)
            ]
        ];
    }

    /**
     * Get tournament performance history
     */
    private function getTournamentHistory($team, $startDate)
    {
        $tournaments = GameMatch::where(function($query) use ($team) {
                $query->where('team1_id', $team->id)
                      ->orWhere('team2_id', $team->id);
            })
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('event_id')
            ->with('event')
            ->get()
            ->groupBy('event_id')
            ->map(function($matches) use ($team) {
                $event = $matches->first()->event;
                $completedMatches = $matches->where('status', 'completed');
                
                $wins = $completedMatches->filter(function($match) use ($team) {
                    return ($match->team1_id == $team->id && $match->team1_score > $match->team2_score) ||
                           ($match->team2_id == $team->id && $match->team2_score > $match->team1_score);
                })->count();

                return [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'tier' => $event->tier,
                        'prize_pool' => $event->prize_pool,
                        'start_date' => $event->start_date,
                        'end_date' => $event->end_date
                    ],
                    'performance' => [
                        'matches_played' => $completedMatches->count(),
                        'wins' => $wins,
                        'losses' => $completedMatches->count() - $wins,
                        'win_rate' => $completedMatches->count() > 0 
                            ? round(($wins / $completedMatches->count()) * 100, 1) 
                            : 0
                    ],
                    'placement' => null, // Would need bracket/standings data
                    'prize_earned' => null // Would need placement data
                ];
            })
            ->sortByDesc('event.start_date')
            ->values();

        return $tournaments;
    }

    /**
     * Get comparative standings within region/division
     */
    private function getComparativeStandings($team, $startDate)
    {
        $regionalTeams = Team::where('region', $team->region)
            ->where('status', 'active')
            ->get();

        $standings = $regionalTeams->map(function($t) use ($startDate) {
            $matches = GameMatch::where(function($query) use ($t) {
                    $query->where('team1_id', $t->id)
                          ->orWhere('team2_id', $t->id);
                })
                ->where('created_at', '>=', $startDate)
                ->where('status', 'completed')
                ->get();

            $wins = $matches->filter(function($match) use ($t) {
                return ($match->team1_id == $t->id && $match->team1_score > $match->team2_score) ||
                       ($match->team2_id == $t->id && $match->team2_score > $match->team1_score);
            })->count();

            $winRate = $matches->count() > 0 ? ($wins / $matches->count()) * 100 : 0;

            return [
                'team' => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'logo' => $t->logo
                ],
                'rating' => $t->rating ?? 1500,
                'matches_played' => $matches->count(),
                'wins' => $wins,
                'losses' => $matches->count() - $wins,
                'win_rate' => round($winRate, 1)
            ];
        })
        ->sortByDesc('rating')
        ->values();

        $teamPosition = $standings->search(function($item) use ($team) {
            return $item['team']['id'] == $team->id;
        });

        return [
            'regional_standings' => $standings,
            'team_position' => $teamPosition !== false ? $teamPosition + 1 : null,
            'total_teams' => $standings->count(),
            'percentile' => $teamPosition !== false && $standings->count() > 0 
                ? round((1 - ($teamPosition / $standings->count())) * 100, 1) 
                : null
        ];
    }

    /**
     * Get performance trends over time
     */
    private function getPerformanceTrends($team, $startDate)
    {
        $dailyStats = GameMatch::where(function($query) use ($team) {
                $query->where('team1_id', $team->id)
                      ->orWhere('team2_id', $team->id);
            })
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as matches_played,
                SUM(CASE 
                    WHEN (team1_id = ? AND team1_score > team2_score) OR 
                         (team2_id = ? AND team2_score > team1_score)
                    THEN 1 ELSE 0 END) as wins
            ', [$team->id, $team->id])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Calculate rolling averages
        $trendData = [];
        $window = 7; // 7-day rolling average
        
        foreach ($dailyStats as $index => $stat) {
            $windowStart = max(0, $index - $window + 1);
            $windowData = $dailyStats->slice($windowStart, $window);
            
            $winRate = $stat->matches_played > 0 ? ($stat->wins / $stat->matches_played) * 100 : 0;
            $rollingWinRate = $windowData->sum('wins') > 0 && $windowData->sum('matches_played') > 0 
                ? ($windowData->sum('wins') / $windowData->sum('matches_played')) * 100 
                : 0;

            $trendData[] = [
                'date' => $stat->date,
                'matches_played' => $stat->matches_played,
                'wins' => $stat->wins,
                'win_rate' => round($winRate, 1),
                'rolling_win_rate' => round($rollingWinRate, 1)
            ];
        }

        return [
            'daily_performance' => $trendData,
            'trend_analysis' => [
                'current_trajectory' => $this->determineTrendDirection($trendData),
                'consistency' => $this->calculateTeamConsistency($trendData),
                'momentum_score' => $this->calculateTeamMomentum($trendData)
            ]
        ];
    }

    /**
     * Get team vs team comparison
     */
    public function getTeamComparison(Request $request)
    {
        try {
            $team1Id = $request->get('team1_id');
            $team2Id = $request->get('team2_id');
            $timeframe = $request->get('timeframe', '90d');

            if (!$team1Id || !$team2Id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Both team1_id and team2_id are required'
                ], 400);
            }

            $team1 = Team::findOrFail($team1Id);
            $team2 = Team::findOrFail($team2Id);
            $startDate = $this->getStartDate($timeframe);

            $comparison = [
                'teams' => [
                    'team1' => $this->getTeamInfo($team1),
                    'team2' => $this->getTeamInfo($team2)
                ],
                'head_to_head' => $this->getHeadToHeadComparison($team1, $team2, $startDate),
                'performance_comparison' => $this->getPerformanceComparison($team1, $team2, $startDate),
                'player_matchups' => $this->getPlayerMatchups($team1, $team2, $startDate),
                'tactical_comparison' => $this->getTacticalComparison($team1, $team2, $startDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $comparison,
                'timeframe' => $timeframe,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating team comparison: ' . $e->getMessage()
            ], 500);
        }
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
            'all' => Carbon::create(2020, 1, 1),
            default => now()->subDays(30)
        };
    }

    private function getEmptyTeamStats()
    {
        return [
            'match_record' => [
                'matches_played' => 0,
                'wins' => 0,
                'losses' => 0,
                'win_rate' => 0,
                'maps_played' => 0,
                'maps_won' => 0,
                'maps_lost' => 0,
                'map_win_rate' => 0
            ],
            'performance_metrics' => [
                'team_rating' => 0,
                'team_acs' => 0,
                'team_kda' => 0,
                'kast_percentage' => 0,
                'kills_per_round' => 0,
                'deaths_per_round' => 0,
                'first_kill_rate' => 0
            ],
            'totals' => [
                'total_kills' => 0,
                'total_deaths' => 0,
                'total_assists' => 0,
                'total_damage' => 0,
                'total_healing' => 0,
                'total_first_kills' => 0,
                'total_first_deaths' => 0
            ]
        ];
    }

    private function getAverageRoundsPerMap($team, $startDate)
    {
        $avgRounds = DB::table('match_maps as mm')
            ->join('matches as m', 'mm.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->where('m.status', 'completed')
            ->where(function($query) use ($team) {
                $query->where('m.team1_id', $team->id)
                      ->orWhere('m.team2_id', $team->id);
            })
            ->avg(DB::raw('mm.team1_score + mm.team2_score'));

        return $avgRounds ?? 24; // Default to 24 rounds if no data
    }

    private function calculateTeamStreak($matches, $team)
    {
        $streak = 0;
        $type = null;

        foreach ($matches as $match) {
            $won = ($match->team1_id == $team->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $team->id && $match->team2_score > $match->team1_score);

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

    private function calculateFormRating($form)
    {
        $wins = $form->filter(function($result) { return $result === 'W'; })->count();
        $total = $form->count();
        
        if ($total === 0) return 50;
        
        $winRate = ($wins / $total) * 100;
        
        // Weight recent matches more heavily
        $weightedScore = 0;
        $totalWeight = 0;
        
        foreach ($form as $index => $result) {
            $weight = $index + 1; // More recent = higher weight
            $score = $result === 'W' ? 100 : 0;
            $weightedScore += $score * $weight;
            $totalWeight += $weight;
        }
        
        return $totalWeight > 0 ? round($weightedScore / $totalWeight, 1) : 50;
    }

    private function calculateMomentum($matches, $team)
    {
        if ($matches->count() < 3) return 'neutral';
        
        $recentWins = $matches->take(3)->filter(function($match) use ($team) {
            return ($match->team1_id == $team->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $team->id && $match->team2_score > $match->team1_score);
        })->count();
        
        if ($recentWins >= 2) return 'positive';
        if ($recentWins <= 1) return 'negative';
        return 'neutral';
    }

    private function calculateDominance($wins, $total)
    {
        if ($total === 0) return 'unknown';
        
        $winRate = ($wins / $total) * 100;
        
        if ($winRate >= 80) return 'dominant';
        if ($winRate >= 60) return 'favored';
        if ($winRate >= 40) return 'competitive';
        return 'struggling';
    }

    private function calculatePlayerImpactScore($stat, $winRate)
    {
        $baseScore = $stat->avg_rating * 10;
        $winBonus = ($winRate / 100) * 20;
        $mvpBonus = $stat->mvp_awards * 5;
        
        return round($baseScore + $winBonus + $mvpBonus, 1);
    }

    private function determineMapComfort($winRate, $timesPlayed)
    {
        if ($timesPlayed < 3) return 'unknown';
        if ($winRate >= 70) return 'strong';
        if ($winRate >= 50) return 'comfortable';
        if ($winRate >= 30) return 'struggling';
        return 'weak';
    }

    private function calculateAdaptabilityScore($heroUsage)
    {
        $uniqueHeroes = $heroUsage->count();
        $rolesDiversity = $heroUsage->groupBy('hero_role')->count();
        $flexibilityScore = $heroUsage->where('players_used', '>', 1)->count();
        
        return round(($uniqueHeroes * 2) + ($rolesDiversity * 5) + ($flexibilityScore * 3), 1);
    }

    private function determineTrendDirection($trendData)
    {
        if (count($trendData) < 3) return 'stable';
        
        $recent = collect($trendData)->takeLast(3);
        $earlier = collect($trendData)->slice(-6, 3);
        
        $recentAvg = $recent->avg('rolling_win_rate');
        $earlierAvg = $earlier->avg('rolling_win_rate');
        
        $difference = $recentAvg - $earlierAvg;
        
        if ($difference > 5) return 'improving';
        if ($difference < -5) return 'declining';
        return 'stable';
    }

    private function calculateTeamConsistency($trendData)
    {
        if (empty($trendData)) return 0;
        
        $winRates = collect($trendData)->pluck('rolling_win_rate')->filter();
        if ($winRates->isEmpty()) return 0;
        
        $stdDev = $this->calculateStandardDeviation($winRates);
        return max(0, round(100 - ($stdDev * 2), 1));
    }

    private function calculateTeamMomentum($trendData)
    {
        if (count($trendData) < 5) return 50;
        
        $recent = collect($trendData)->takeLast(5);
        $slope = $this->calculateSlope($recent->pluck('rolling_win_rate'));
        
        return round(50 + ($slope * 10), 1);
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

    private function calculateSlope($values)
    {
        $n = count($values);
        if ($n < 2) return 0;
        
        $x_values = range(1, $n);
        $xy_sum = 0;
        $x_sum = array_sum($x_values);
        $y_sum = $values->sum();
        $x_sq_sum = array_sum(array_map(function($x) { return $x * $x; }, $x_values));
        
        foreach ($x_values as $i => $x) {
            $xy_sum += $x * $values[$i];
        }
        
        $denominator = ($n * $x_sq_sum) - ($x_sum * $x_sum);
        
        if ($denominator == 0) return 0;
        
        return (($n * $xy_sum) - ($x_sum * $y_sum)) / $denominator;
    }

    private function getHeadToHeadComparison($team1, $team2, $startDate)
    {
        $matches = GameMatch::where(function($query) use ($team1, $team2) {
                $query->where(function($q) use ($team1, $team2) {
                    $q->where('team1_id', $team1->id)->where('team2_id', $team2->id);
                })->orWhere(function($q) use ($team1, $team2) {
                    $q->where('team1_id', $team2->id)->where('team2_id', $team1->id);
                });
            })
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($matches->isEmpty()) {
            return [
                'total_matches' => 0,
                'team1_wins' => 0,
                'team2_wins' => 0,
                'last_meeting' => null,
                'match_history' => []
            ];
        }

        $team1Wins = $matches->filter(function($match) use ($team1) {
            return ($match->team1_id == $team1->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $team1->id && $match->team2_score > $match->team1_score);
        })->count();

        return [
            'total_matches' => $matches->count(),
            'team1_wins' => $team1Wins,
            'team2_wins' => $matches->count() - $team1Wins,
            'last_meeting' => $matches->first()->created_at ?? null,
            'match_history' => $matches->map(function($match) use ($team1, $team2) {
                $team1IsFirst = $match->team1_id == $team1->id;
                $team1Score = $team1IsFirst ? $match->team1_score : $match->team2_score;
                $team2Score = $team1IsFirst ? $match->team2_score : $match->team1_score;
                
                return [
                    'date' => $match->created_at->format('Y-m-d'),
                    'team1_score' => $team1Score,
                    'team2_score' => $team2Score,
                    'winner' => $team1Score > $team2Score ? 'team1' : 'team2',
                    'event_name' => $match->event?->name
                ];
            })->values()
        ];
    }

    private function getPerformanceComparison($team1, $team2, $startDate)
    {
        $team1Stats = $this->getOverallPerformance($team1, $startDate);
        $team2Stats = $this->getOverallPerformance($team2, $startDate);

        return [
            'team1' => $team1Stats,
            'team2' => $team2Stats,
            'advantages' => [
                'team1' => $this->findAdvantages($team1Stats, $team2Stats),
                'team2' => $this->findAdvantages($team2Stats, $team1Stats)
            ]
        ];
    }

    private function getPlayerMatchups($team1, $team2, $startDate)
    {
        $team1Players = $this->getPlayerContributions($team1, $startDate);
        $team2Players = $this->getPlayerContributions($team2, $startDate);

        return [
            'role_matchups' => $this->compareByRole($team1Players, $team2Players),
            'star_players' => [
                'team1' => $team1Players->take(2),
                'team2' => $team2Players->take(2)
            ]
        ];
    }

    private function getTacticalComparison($team1, $team2, $startDate)
    {
        $team1Tactics = $this->getTacticalAnalysis($team1, $startDate);
        $team2Tactics = $this->getTacticalAnalysis($team2, $startDate);

        return [
            'team1' => $team1Tactics,
            'team2' => $team2Tactics,
            'tactical_edges' => [
                'adaptability' => $team1Tactics['tactical_flexibility']['adaptability_score'] > $team2Tactics['tactical_flexibility']['adaptability_score'] ? 'team1' : 'team2',
                'hero_diversity' => $team1Tactics['tactical_flexibility']['unique_heroes_used'] > $team2Tactics['tactical_flexibility']['unique_heroes_used'] ? 'team1' : 'team2'
            ]
        ];
    }

    private function findAdvantages($stats1, $stats2)
    {
        $advantages = [];
        
        if ($stats1['match_record']['win_rate'] > $stats2['match_record']['win_rate']) {
            $advantages[] = 'Higher win rate';
        }
        
        if ($stats1['performance_metrics']['team_rating'] > $stats2['performance_metrics']['team_rating']) {
            $advantages[] = 'Better team rating';
        }
        
        if ($stats1['performance_metrics']['first_kill_rate'] > $stats2['performance_metrics']['first_kill_rate']) {
            $advantages[] = 'Superior opening rounds';
        }

        return $advantages;
    }

    private function compareByRole($team1Players, $team2Players)
    {
        $roles = ['Vanguard', 'Duelist', 'Strategist'];
        $matchups = [];

        foreach ($roles as $role) {
            $team1Player = $team1Players->where('player.role', $role)->first();
            $team2Player = $team2Players->where('player.role', $role)->first();

            if ($team1Player && $team2Player) {
                $matchups[] = [
                    'role' => $role,
                    'team1_player' => $team1Player,
                    'team2_player' => $team2Player,
                    'advantage' => $team1Player['impact_score'] > $team2Player['impact_score'] ? 'team1' : 'team2'
                ];
            }
        }

        return $matchups;
    }
}