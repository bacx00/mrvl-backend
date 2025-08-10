<?php

namespace App\Http\Controllers;

use App\Models\{MatchPlayerStat, GameMatch, Player, Team};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HeroAnalyticsController extends Controller
{
    /**
     * Get comprehensive hero meta analytics
     */
    public function getHeroMetaAnalysis(Request $request)
    {
        try {
            $timeframe = $request->get('timeframe', '30d');
            $region = $request->get('region');
            $tier = $request->get('tier'); // Tournament tier
            $role = $request->get('role'); // Hero role filter
            
            $startDate = $this->getStartDate($timeframe);
            
            $analytics = [
                'meta_overview' => $this->getMetaOverview($startDate, $region, $tier, $role),
                'hero_statistics' => $this->getDetailedHeroStats($startDate, $region, $tier, $role),
                'role_analysis' => $this->getRoleAnalysis($startDate, $region, $tier),
                'synergy_analysis' => $this->getHeroSynergies($startDate, $region, $tier),
                'counter_analysis' => $this->getHeroCounters($startDate, $region, $tier),
                'map_preferences' => $this->getHeroMapPreferences($startDate, $region, $tier, $role),
                'meta_trends' => $this->getMetaTrends($startDate, $region, $tier),
                'regional_differences' => $this->getRegionalMetaDifferences($startDate, $tier),
                'tier_analysis' => $this->getTierAnalysis($startDate, $region, $role),
                'emerging_picks' => $this->getEmergingPicks($startDate, $region, $tier)
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'filters' => [
                    'timeframe' => $timeframe,
                    'region' => $region,
                    'tier' => $tier,
                    'role' => $role
                ],
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching hero meta analysis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific hero detailed analytics
     */
    public function getHeroAnalytics($heroName, Request $request)
    {
        try {
            $timeframe = $request->get('timeframe', '30d');
            $region = $request->get('region');
            $tier = $request->get('tier');
            
            $startDate = $this->getStartDate($timeframe);
            
            $analytics = [
                'hero_info' => $this->getHeroInfo($heroName),
                'performance_stats' => $this->getHeroPerformanceStats($heroName, $startDate, $region, $tier),
                'usage_trends' => $this->getHeroUsageTrends($heroName, $startDate, $region, $tier),
                'player_specialists' => $this->getHeroSpecialists($heroName, $startDate, $region),
                'matchup_analysis' => $this->getHeroMatchups($heroName, $startDate, $region, $tier),
                'map_performance' => $this->getHeroMapPerformance($heroName, $startDate, $region, $tier),
                'team_usage' => $this->getHeroTeamUsage($heroName, $startDate, $region, $tier),
                'situational_usage' => $this->getHeroSituationalUsage($heroName, $startDate, $region, $tier),
                'win_conditions' => $this->getHeroWinConditions($heroName, $startDate, $region, $tier),
                'comparative_analysis' => $this->getHeroComparative($heroName, $startDate, $region, $tier)
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'hero' => $heroName,
                'filters' => [
                    'timeframe' => $timeframe,
                    'region' => $region,
                    'tier' => $tier
                ],
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching hero analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get meta overview statistics
     */
    private function getMetaOverview($startDate, $region = null, $tier = null, $role = null)
    {
        $query = MatchPlayerStat::whereHas('match', function($q) use ($startDate, $region, $tier) {
                $q->where('created_at', '>=', $startDate)
                  ->where('status', 'completed');
                  
                if ($region) {
                    $q->whereHas('team1', function($teamQuery) use ($region) {
                        $teamQuery->where('region', $region);
                    })->orWhereHas('team2', function($teamQuery) use ($region) {
                        $teamQuery->where('region', $region);
                    });
                }
                
                if ($tier) {
                    $q->whereHas('event', function($eventQuery) use ($tier) {
                        $eventQuery->where('tier', $tier);
                    });
                }
            })
            ->whereNotNull('hero');

        if ($role) {
            $query->where('hero_role', $role);
        }

        $totalPicks = $query->count();
        $uniqueHeroes = $query->distinct('hero')->count('hero');
        $totalMatches = $query->distinct('match_id')->count('match_id');

        $topHeroes = $query->selectRaw('
                hero,
                hero_role,
                COUNT(*) as pick_count,
                AVG(performance_rating) as avg_rating,
                AVG(kda) as avg_kda,
                AVG(combat_score) as avg_acs,
                COUNT(DISTINCT player_id) as unique_players
            ')
            ->groupBy('hero', 'hero_role')
            ->orderBy('pick_count', 'desc')
            ->limit(10)
            ->get();

        $diversity = $this->calculateMetaDiversity($query);

        return [
            'meta_snapshot' => [
                'total_picks' => $totalPicks,
                'unique_heroes' => $uniqueHeroes,
                'total_matches' => $totalMatches,
                'picks_per_match' => $totalMatches > 0 ? round($totalPicks / $totalMatches, 1) : 0,
                'meta_diversity_score' => $diversity
            ],
            'top_heroes' => $topHeroes->map(function($hero) use ($totalPicks) {
                return [
                    'hero' => $hero->hero,
                    'role' => $hero->hero_role,
                    'pick_count' => (int) $hero->pick_count,
                    'pick_rate' => $totalPicks > 0 ? round(($hero->pick_count / $totalPicks) * 100, 2) : 0,
                    'avg_rating' => round($hero->avg_rating, 2),
                    'avg_kda' => round($hero->avg_kda, 2),
                    'avg_acs' => round($hero->avg_acs, 1),
                    'unique_players' => (int) $hero->unique_players,
                    'tier_rating' => $this->calculateHeroTierRating($hero)
                ];
            })->values(),
            'role_distribution' => $this->getRoleDistribution($query),
            'meta_health' => $this->assessMetaHealth($topHeroes, $totalPicks)
        ];
    }

    /**
     * Get detailed hero statistics
     */
    private function getDetailedHeroStats($startDate, $region = null, $tier = null, $role = null)
    {
        $query = MatchPlayerStat::whereHas('match', function($q) use ($startDate, $region, $tier) {
                $q->where('created_at', '>=', $startDate)
                  ->where('status', 'completed');
                  
                if ($region) {
                    $q->whereHas('team1', function($teamQuery) use ($region) {
                        $teamQuery->where('region', $region);
                    })->orWhereHas('team2', function($teamQuery) use ($region) {
                        $teamQuery->where('region', $region);
                    });
                }
                
                if ($tier) {
                    $q->whereHas('event', function($eventQuery) use ($tier) {
                        $eventQuery->where('tier', $tier);
                    });
                }
            })
            ->whereNotNull('hero');

        if ($role) {
            $query->where('hero_role', $role);
        }

        $heroStats = $query->selectRaw('
                hero,
                hero_role,
                COUNT(*) as pick_count,
                AVG(performance_rating) as avg_rating,
                AVG(combat_score) as avg_acs,
                AVG(kda) as avg_kda,
                AVG(damage_per_round) as avg_adr,
                AVG(kast_percentage) as avg_kast,
                AVG(eliminations_per_round) as avg_kpr,
                AVG(assists_per_round) as avg_apr,
                AVG(damage_dealt) as avg_damage,
                AVG(healing_done) as avg_healing,
                AVG(damage_blocked) as avg_blocked,
                SUM(eliminations) as total_kills,
                SUM(deaths) as total_deaths,
                SUM(assists) as total_assists,
                MAX(performance_rating) as peak_rating,
                MIN(performance_rating) as lowest_rating,
                COUNT(DISTINCT player_id) as unique_players,
                COUNT(DISTINCT team_id) as unique_teams,
                SUM(CASE WHEN player_of_the_match = 1 THEN 1 ELSE 0 END) as mvp_awards
            ')
            ->groupBy('hero', 'hero_role')
            ->orderBy('pick_count', 'desc')
            ->get();

        $totalPicks = $heroStats->sum('pick_count');

        // Calculate win rates for each hero
        $heroWinRates = $this->calculateHeroWinRates($heroStats, $startDate, $region, $tier);

        return $heroStats->map(function($hero) use ($totalPicks, $heroWinRates) {
            $pickRate = $totalPicks > 0 ? round(($hero->pick_count / $totalPicks) * 100, 2) : 0;
            $winRate = $heroWinRates[$hero->hero] ?? 0;

            return [
                'hero' => $hero->hero,
                'role' => $hero->hero_role,
                'usage' => [
                    'pick_count' => (int) $hero->pick_count,
                    'pick_rate' => $pickRate,
                    'ban_rate' => 0, // Would need ban data
                    'presence' => $pickRate, // pick_rate + ban_rate
                    'unique_players' => (int) $hero->unique_players,
                    'unique_teams' => (int) $hero->unique_teams
                ],
                'performance' => [
                    'win_rate' => round($winRate, 1),
                    'avg_rating' => round($hero->avg_rating, 2),
                    'avg_acs' => round($hero->avg_acs, 1),
                    'avg_kda' => round($hero->avg_kda, 2),
                    'avg_adr' => round($hero->avg_adr, 1),
                    'avg_kast' => round($hero->avg_kast, 1),
                    'avg_kpr' => round($hero->avg_kpr, 2),
                    'avg_apr' => round($hero->avg_apr, 2)
                ],
                'statistics' => [
                    'avg_damage' => round($hero->avg_damage, 0),
                    'avg_healing' => round($hero->avg_healing, 0),
                    'avg_blocked' => round($hero->avg_blocked, 0),
                    'total_kills' => (int) $hero->total_kills,
                    'total_deaths' => (int) $hero->total_deaths,
                    'total_assists' => (int) $hero->total_assists,
                    'mvp_awards' => (int) $hero->mvp_awards
                ],
                'consistency' => [
                    'peak_rating' => round($hero->peak_rating, 2),
                    'lowest_rating' => round($hero->lowest_rating, 2),
                    'rating_variance' => round($hero->peak_rating - $hero->lowest_rating, 2),
                    'consistency_score' => $this->calculateConsistencyScore($hero)
                ],
                'meta_status' => [
                    'tier' => $this->determineHeroTier($pickRate, $winRate),
                    'trending' => $this->getHeroTrending($hero->hero, $startDate),
                    'power_level' => $this->calculatePowerLevel($hero, $winRate)
                ]
            ];
        })->values();
    }

    /**
     * Get role-based analysis
     */
    private function getRoleAnalysis($startDate, $region = null, $tier = null)
    {
        $query = MatchPlayerStat::whereHas('match', function($q) use ($startDate, $region, $tier) {
                $q->where('created_at', '>=', $startDate)
                  ->where('status', 'completed');
                  
                if ($region) {
                    $q->whereHas('team1', function($teamQuery) use ($region) {
                        $teamQuery->where('region', $region);
                    })->orWhereHas('team2', function($teamQuery) use ($region) {
                        $teamQuery->where('region', $region);
                    });
                }
                
                if ($tier) {
                    $q->whereHas('event', function($eventQuery) use ($tier) {
                        $eventQuery->where('tier', $tier);
                    });
                }
            })
            ->whereNotNull('hero')
            ->whereNotNull('hero_role');

        $roleStats = $query->selectRaw('
                hero_role,
                COUNT(*) as total_picks,
                COUNT(DISTINCT hero) as unique_heroes,
                AVG(performance_rating) as avg_rating,
                AVG(combat_score) as avg_acs,
                AVG(kda) as avg_kda,
                AVG(damage_dealt) as avg_damage,
                AVG(healing_done) as avg_healing,
                AVG(damage_blocked) as avg_blocked
            ')
            ->groupBy('hero_role')
            ->get();

        $totalPicks = $roleStats->sum('total_picks');

        return $roleStats->map(function($role) use ($totalPicks) {
            return [
                'role' => $role->hero_role,
                'usage' => [
                    'total_picks' => (int) $role->total_picks,
                    'pick_share' => $totalPicks > 0 ? round(($role->total_picks / $totalPicks) * 100, 1) : 0,
                    'unique_heroes' => (int) $role->unique_heroes,
                    'diversity_score' => $this->calculateRoleDiversity($role)
                ],
                'performance' => [
                    'avg_rating' => round($role->avg_rating, 2),
                    'avg_acs' => round($role->avg_acs, 1),
                    'avg_kda' => round($role->avg_kda, 2),
                    'avg_damage' => round($role->avg_damage, 0),
                    'avg_healing' => round($role->avg_healing, 0),
                    'avg_blocked' => round($role->avg_blocked, 0)
                ],
                'meta_balance' => [
                    'role_health' => $this->assessRoleHealth($role),
                    'dominant_hero' => $this->getDominantHeroForRole($role->hero_role, $startDate)
                ]
            ];
        })->values();
    }

    /**
     * Get hero synergy analysis
     */
    private function getHeroSynergies($startDate, $region = null, $tier = null)
    {
        // Get team compositions and their success rates
        $teamComps = GameMatch::where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->with(['playerStats' => function($query) {
                $query->whereNotNull('hero');
            }])
            ->get()
            ->flatMap(function($match) {
                // Get compositions for both teams
                $team1Heroes = $match->playerStats->where('team_id', $match->team1_id)
                    ->pluck('hero')
                    ->sort()
                    ->values();
                    
                $team2Heroes = $match->playerStats->where('team_id', $match->team2_id)
                    ->pluck('hero')
                    ->sort()
                    ->values();

                $team1Won = $match->team1_score > $match->team2_score;

                return [
                    [
                        'heroes' => $team1Heroes,
                        'won' => $team1Won,
                        'match_id' => $match->id
                    ],
                    [
                        'heroes' => $team2Heroes,
                        'won' => !$team1Won,
                        'match_id' => $match->id
                    ]
                ];
            })
            ->filter(function($comp) {
                return $comp['heroes']->count() >= 3; // At least 3 heroes for synergy analysis
            });

        // Find hero pairs with high synergy
        $heroPairs = [];
        
        foreach ($teamComps as $comp) {
            $heroes = $comp['heroes'];
            for ($i = 0; $i < count($heroes); $i++) {
                for ($j = $i + 1; $j < count($heroes); $j++) {
                    $pair = $heroes[$i] . ' + ' . $heroes[$j];
                    if (!isset($heroPairs[$pair])) {
                        $heroPairs[$pair] = [
                            'hero1' => $heroes[$i],
                            'hero2' => $heroes[$j],
                            'games' => 0,
                            'wins' => 0
                        ];
                    }
                    $heroPairs[$pair]['games']++;
                    if ($comp['won']) {
                        $heroPairs[$pair]['wins']++;
                    }
                }
            }
        }

        // Calculate synergy scores and filter
        $synergies = collect($heroPairs)
            ->filter(function($pair) {
                return $pair['games'] >= 5; // Minimum sample size
            })
            ->map(function($pair) {
                $winRate = $pair['games'] > 0 ? ($pair['wins'] / $pair['games']) * 100 : 0;
                return [
                    'hero1' => $pair['hero1'],
                    'hero2' => $pair['hero2'],
                    'games_together' => $pair['games'],
                    'wins_together' => $pair['wins'],
                    'win_rate' => round($winRate, 1),
                    'synergy_score' => $this->calculateSynergyScore($pair, $winRate),
                    'synergy_type' => $this->determineSynergyType($pair['hero1'], $pair['hero2'])
                ];
            })
            ->sortByDesc('synergy_score')
            ->take(20)
            ->values();

        return [
            'top_synergies' => $synergies,
            'synergy_insights' => $this->generateSynergyInsights($synergies),
            'anti_synergies' => $synergies->where('win_rate', '<', 45)->take(10)->values()
        ];
    }

    /**
     * Get hero counter analysis
     */
    private function getHeroCounters($startDate, $region = null, $tier = null)
    {
        // This is complex - simplified version tracking win rates when heroes face each other
        $matchups = [];
        
        $matches = GameMatch::where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->with(['playerStats' => function($query) {
                $query->whereNotNull('hero');
            }])
            ->get();

        foreach ($matches as $match) {
            $team1Heroes = $match->playerStats->where('team_id', $match->team1_id)->pluck('hero');
            $team2Heroes = $match->playerStats->where('team_id', $match->team2_id)->pluck('hero');
            $team1Won = $match->team1_score > $match->team2_score;

            foreach ($team1Heroes as $hero1) {
                foreach ($team2Heroes as $hero2) {
                    $matchupKey = $hero1 . ' vs ' . $hero2;
                    
                    if (!isset($matchups[$matchupKey])) {
                        $matchups[$matchupKey] = [
                            'hero1' => $hero1,
                            'hero2' => $hero2,
                            'games' => 0,
                            'hero1_wins' => 0
                        ];
                    }
                    
                    $matchups[$matchupKey]['games']++;
                    if ($team1Won) {
                        $matchups[$matchupKey]['hero1_wins']++;
                    }
                }
            }
        }

        $counters = collect($matchups)
            ->filter(function($matchup) {
                return $matchup['games'] >= 10; // Minimum sample size
            })
            ->map(function($matchup) {
                $hero1WinRate = $matchup['games'] > 0 ? ($matchup['hero1_wins'] / $matchup['games']) * 100 : 50;
                $hero2WinRate = 100 - $hero1WinRate;
                
                return [
                    'hero1' => $matchup['hero1'],
                    'hero2' => $matchup['hero2'],
                    'total_games' => $matchup['games'],
                    'hero1_wins' => $matchup['hero1_wins'],
                    'hero2_wins' => $matchup['games'] - $matchup['hero1_wins'],
                    'hero1_win_rate' => round($hero1WinRate, 1),
                    'hero2_win_rate' => round($hero2WinRate, 1),
                    'advantage' => abs($hero1WinRate - 50) > 10 ? 
                        ($hero1WinRate > 50 ? 'hero1' : 'hero2') : 'neutral',
                    'confidence' => $this->calculateMatchupConfidence($matchup['games'])
                ];
            })
            ->sortByDesc(function($matchup) {
                return abs($matchup['hero1_win_rate'] - 50);
            })
            ->take(50)
            ->values();

        return [
            'matchup_data' => $counters,
            'hard_counters' => $counters->where('advantage', '!=', 'neutral')
                ->where('confidence', '>=', 0.7)
                ->take(20)
                ->values(),
            'counter_insights' => $this->generateCounterInsights($counters)
        ];
    }

    /**
     * Get hero map preferences
     */
    private function getHeroMapPreferences($startDate, $region = null, $tier = null, $role = null)
    {
        $query = DB::table('match_player_stats as mps')
            ->join('matches as m', 'mps.match_id', '=', 'm.id')
            ->join('match_maps as mm', 'm.id', '=', 'mm.match_id')
            ->where('m.created_at', '>=', $startDate)
            ->where('m.status', 'completed')
            ->whereNotNull('mps.hero')
            ->whereNotNull('mm.map_name');

        if ($role) {
            $query->where('mps.hero_role', $role);
        }

        $mapStats = $query->selectRaw('
                mps.hero,
                mm.map_name,
                COUNT(*) as pick_count,
                AVG(mps.performance_rating) as avg_rating,
                AVG(mps.kda) as avg_kda,
                AVG(mps.combat_score) as avg_acs
            ')
            ->groupBy('mps.hero', 'mm.map_name')
            ->orderBy('pick_count', 'desc')
            ->get();

        // Calculate win rates by hero and map
        $heroMapWinRates = $this->calculateHeroMapWinRates($mapStats, $startDate);

        $preferences = $mapStats->groupBy('hero')->map(function($heroMaps, $heroName) use ($heroMapWinRates) {
            $totalPicks = $heroMaps->sum('pick_count');
            
            $mapData = $heroMaps->map(function($map) use ($heroName, $totalPicks, $heroMapWinRates) {
                $winRate = $heroMapWinRates[$heroName . '_' . $map->map_name] ?? 0;
                
                return [
                    'map_name' => $map->map_name,
                    'pick_count' => (int) $map->pick_count,
                    'pick_rate' => $totalPicks > 0 ? round(($map->pick_count / $totalPicks) * 100, 1) : 0,
                    'win_rate' => round($winRate, 1),
                    'avg_rating' => round($map->avg_rating, 2),
                    'avg_kda' => round($map->avg_kda, 2),
                    'avg_acs' => round($map->avg_acs, 1),
                    'effectiveness' => $this->calculateMapEffectiveness($winRate, $map->avg_rating)
                ];
            })->sortByDesc('effectiveness');

            return [
                'hero' => $heroName,
                'total_picks' => $totalPicks,
                'best_maps' => $mapData->take(3)->values(),
                'worst_maps' => $mapData->sortBy('effectiveness')->take(3)->values(),
                'all_maps' => $mapData->values(),
                'map_versatility' => $this->calculateMapVersatility($mapData)
            ];
        })->values();

        return $preferences;
    }

    /**
     * Get meta trends over time
     */
    private function getMetaTrends($startDate, $region = null, $tier = null)
    {
        $weeks = [];
        $current = $startDate->copy();
        $end = now();

        while ($current->lt($end)) {
            $weekEnd = $current->copy()->addWeek();
            
            $weekStats = MatchPlayerStat::whereHas('match', function($q) use ($current, $weekEnd) {
                    $q->where('created_at', '>=', $current)
                      ->where('created_at', '<', $weekEnd)
                      ->where('status', 'completed');
                })
                ->whereNotNull('hero')
                ->selectRaw('
                    hero,
                    COUNT(*) as pick_count,
                    AVG(performance_rating) as avg_rating
                ')
                ->groupBy('hero')
                ->orderBy('pick_count', 'desc')
                ->limit(10)
                ->get();

            $weeks[] = [
                'week_start' => $current->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'top_heroes' => $weekStats->map(function($hero) {
                    return [
                        'hero' => $hero->hero,
                        'pick_count' => (int) $hero->pick_count,
                        'avg_rating' => round($hero->avg_rating, 2)
                    ];
                })->values()
            ];

            $current->addWeek();
        }

        return [
            'weekly_trends' => $weeks,
            'trending_up' => $this->findTrendingUpHeroes($weeks),
            'trending_down' => $this->findTrendingDownHeroes($weeks),
            'meta_shifts' => $this->identifyMetaShifts($weeks)
        ];
    }

    /**
     * Get regional meta differences
     */
    private function getRegionalMetaDifferences($startDate, $tier = null)
    {
        $regions = ['NA', 'EU', 'ASIA', 'CN'];
        $regionalMeta = [];

        foreach ($regions as $region) {
            $heroStats = MatchPlayerStat::whereHas('match', function($q) use ($startDate, $region, $tier) {
                    $q->where('created_at', '>=', $startDate)
                      ->where('status', 'completed')
                      ->whereHas('team1', function($teamQuery) use ($region) {
                          $teamQuery->where('region', $region);
                      });
                      
                    if ($tier) {
                        $q->whereHas('event', function($eventQuery) use ($tier) {
                            $eventQuery->where('tier', $tier);
                        });
                    }
                })
                ->whereNotNull('hero')
                ->selectRaw('
                    hero,
                    COUNT(*) as pick_count,
                    AVG(performance_rating) as avg_rating
                ')
                ->groupBy('hero')
                ->orderBy('pick_count', 'desc')
                ->limit(15)
                ->get();

            $totalPicks = $heroStats->sum('pick_count');

            $regionalMeta[$region] = [
                'region' => $region,
                'total_picks' => $totalPicks,
                'top_heroes' => $heroStats->map(function($hero) use ($totalPicks) {
                    return [
                        'hero' => $hero->hero,
                        'pick_count' => (int) $hero->pick_count,
                        'pick_rate' => $totalPicks > 0 ? round(($hero->pick_count / $totalPicks) * 100, 2) : 0,
                        'avg_rating' => round($hero->avg_rating, 2)
                    ];
                })->values()
            ];
        }

        return [
            'regional_data' => $regionalMeta,
            'unique_preferences' => $this->findRegionalUniqueness($regionalMeta),
            'global_vs_regional' => $this->compareGlobalVsRegional($regionalMeta)
        ];
    }

    /**
     * Get tier-based analysis
     */
    private function getTierAnalysis($startDate, $region = null, $role = null)
    {
        $tiers = ['S', 'A', 'B', 'C'];
        $tierAnalysis = [];

        foreach ($tiers as $tier) {
            $query = MatchPlayerStat::whereHas('match', function($q) use ($startDate, $region, $tier) {
                    $q->where('created_at', '>=', $startDate)
                      ->where('status', 'completed')
                      ->whereHas('event', function($eventQuery) use ($tier) {
                          $eventQuery->where('tier', $tier);
                      });
                      
                    if ($region) {
                        $q->whereHas('team1', function($teamQuery) use ($region) {
                            $teamQuery->where('region', $region);
                        });
                    }
                })
                ->whereNotNull('hero');

            if ($role) {
                $query->where('hero_role', $role);
            }

            $tierStats = $query->selectRaw('
                    hero,
                    COUNT(*) as pick_count,
                    AVG(performance_rating) as avg_rating
                ')
                ->groupBy('hero')
                ->orderBy('pick_count', 'desc')
                ->limit(10)
                ->get();

            $tierAnalysis[$tier] = [
                'tier' => $tier,
                'total_picks' => $tierStats->sum('pick_count'),
                'unique_heroes' => $tierStats->count(),
                'top_heroes' => $tierStats->map(function($hero) {
                    return [
                        'hero' => $hero->hero,
                        'pick_count' => (int) $hero->pick_count,
                        'avg_rating' => round($hero->avg_rating, 2)
                    ];
                })->values()
            ];
        }

        return [
            'tier_data' => $tierAnalysis,
            'tier_differences' => $this->analyzeTierDifferences($tierAnalysis),
            'skill_ceiling_analysis' => $this->analyzeSkillCeiling($tierAnalysis)
        ];
    }

    /**
     * Get emerging picks
     */
    private function getEmergingPicks($startDate, $region = null, $tier = null)
    {
        // Compare recent period vs earlier period
        $recentDate = now()->subDays(7);
        $earlierDate = $startDate;

        $recentPicks = $this->getHeroPicksInPeriod($recentDate, now(), $region, $tier);
        $earlierPicks = $this->getHeroPicksInPeriod($earlierDate, $recentDate, $region, $tier);

        $emerging = [];

        foreach ($recentPicks as $heroName => $recentData) {
            $earlierData = $earlierPicks[$heroName] ?? ['pick_count' => 0, 'pick_rate' => 0];
            
            $pickRateIncrease = $recentData['pick_rate'] - $earlierData['pick_rate'];
            
            if ($pickRateIncrease > 1 && $recentData['pick_count'] >= 5) { // Significant increase
                $emerging[] = [
                    'hero' => $heroName,
                    'recent_picks' => $recentData['pick_count'],
                    'earlier_picks' => $earlierData['pick_count'],
                    'recent_pick_rate' => $recentData['pick_rate'],
                    'earlier_pick_rate' => $earlierData['pick_rate'],
                    'pick_rate_change' => round($pickRateIncrease, 2),
                    'growth_percentage' => $earlierData['pick_rate'] > 0 
                        ? round((($recentData['pick_rate'] - $earlierData['pick_rate']) / $earlierData['pick_rate']) * 100, 1)
                        : 0,
                    'emergence_score' => $this->calculateEmergenceScore($recentData, $earlierData)
                ];
            }
        }

        usort($emerging, function($a, $b) {
            return $b['emergence_score'] <=> $a['emergence_score'];
        });

        return [
            'emerging_heroes' => array_slice($emerging, 0, 10),
            'sleeper_picks' => $this->findSleeperPicks($recentPicks, $earlierPicks),
            'declining_picks' => $this->findDecliningPicks($recentPicks, $earlierPicks)
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
            'all' => Carbon::create(2020, 1, 1),
            default => now()->subDays(30)
        };
    }

    private function getHeroInfo($heroName)
    {
        // This would come from a heroes table if available
        return [
            'name' => $heroName,
            'role' => null, // Would be fetched from hero data
            'abilities' => [],
            'difficulty' => 'Medium',
            'release_date' => null
        ];
    }

    private function calculateMetaDiversity($query)
    {
        $heroPickCounts = $query->selectRaw('hero, COUNT(*) as picks')
            ->groupBy('hero')
            ->get()
            ->pluck('picks');

        if ($heroPickCounts->isEmpty()) return 0;

        $total = $heroPickCounts->sum();
        $shannon = 0;

        foreach ($heroPickCounts as $picks) {
            $proportion = $picks / $total;
            $shannon -= $proportion * log($proportion, 2);
        }

        // Normalize to 0-100 scale
        $maxShannon = log(count($heroPickCounts), 2);
        return $maxShannon > 0 ? round(($shannon / $maxShannon) * 100, 1) : 0;
    }

    private function calculateHeroTierRating($hero)
    {
        $pickRate = $hero->pick_count;
        $performance = $hero->avg_rating;
        $versatility = $hero->unique_players;

        // Simple tier calculation
        $score = ($pickRate * 0.4) + ($performance * 30) + ($versatility * 0.3);

        if ($score >= 80) return 'S';
        if ($score >= 60) return 'A';
        if ($score >= 40) return 'B';
        return 'C';
    }

    private function getRoleDistribution($query)
    {
        return $query->selectRaw('hero_role, COUNT(*) as count')
            ->whereNotNull('hero_role')
            ->groupBy('hero_role')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->hero_role => $item->count];
            });
    }

    private function assessMetaHealth($topHeroes, $totalPicks)
    {
        if ($totalPicks == 0) return 'Unknown';

        $topThreeShare = $topHeroes->take(3)->sum('pick_count') / $totalPicks;

        if ($topThreeShare > 0.6) return 'Centralized';
        if ($topThreeShare > 0.4) return 'Moderate';
        return 'Diverse';
    }

    private function calculateHeroWinRates($heroStats, $startDate, $region, $tier)
    {
        $winRates = [];

        foreach ($heroStats as $hero) {
            $query = MatchPlayerStat::where('hero', $hero->hero)
                ->whereHas('match', function($q) use ($startDate, $region, $tier) {
                    $q->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
                      
                    if ($region) {
                        $q->whereHas('team1', function($teamQuery) use ($region) {
                            $teamQuery->where('region', $region);
                        });
                    }
                    
                    if ($tier) {
                        $q->whereHas('event', function($eventQuery) use ($tier) {
                            $eventQuery->where('tier', $tier);
                        });
                    }
                });

            $totalMatches = $query->distinct('match_id')->count();
            
            $wins = $query->whereHas('match', function($matchQuery) {
                    $matchQuery->where(function($q) {
                        $q->whereColumn('team1_score', '>', 'team2_score')
                          ->whereRaw('team1_id IN (SELECT team_id FROM match_player_stats WHERE match_id = matches.id AND hero = ?)', [$hero->hero]);
                    })->orWhere(function($q) {
                        $q->whereColumn('team2_score', '>', 'team1_score')
                          ->whereRaw('team2_id IN (SELECT team_id FROM match_player_stats WHERE match_id = matches.id AND hero = ?)', [$hero->hero]);
                    });
                })
                ->distinct('match_id')
                ->count();

            $winRates[$hero->hero] = $totalMatches > 0 ? ($wins / $totalMatches) * 100 : 0;
        }

        return $winRates;
    }

    // Additional helper methods would continue here...
    // For brevity, I'm including the key calculation methods

    private function determineHeroTier($pickRate, $winRate)
    {
        $score = ($pickRate * 0.6) + ($winRate * 0.4);
        
        if ($score >= 75) return 'S';
        if ($score >= 60) return 'A';
        if ($score >= 45) return 'B';
        if ($score >= 30) return 'C';
        return 'D';
    }

    private function calculatePowerLevel($hero, $winRate)
    {
        return round(($hero->avg_rating * 40) + ($winRate * 0.6), 1);
    }

    private function getHeroTrending($heroName, $startDate)
    {
        // Simplified trending calculation
        $recentPicks = MatchPlayerStat::where('hero', $heroName)
            ->whereHas('match', function($q) {
                $q->where('created_at', '>=', now()->subDays(7))
                  ->where('status', 'completed');
            })
            ->count();

        $olderPicks = MatchPlayerStat::where('hero', $heroName)
            ->whereHas('match', function($q) {
                $q->where('created_at', '>=', now()->subDays(14))
                  ->where('created_at', '<', now()->subDays(7))
                  ->where('status', 'completed');
            })
            ->count();

        if ($recentPicks > $olderPicks * 1.2) return 'up';
        if ($recentPicks < $olderPicks * 0.8) return 'down';
        return 'stable';
    }

    // Continuing with other essential helper methods...
    
    private function calculateConsistencyScore($hero)
    {
        $variance = $hero->peak_rating - $hero->lowest_rating;
        return max(0, round(100 - ($variance * 20), 1));
    }

    private function calculateSynergyScore($pair, $winRate)
    {
        $baseScore = $winRate - 50; // Above/below 50% baseline
        $sampleBonus = min($pair['games'] / 10, 5); // Bonus for larger sample size
        
        return round($baseScore + $sampleBonus, 2);
    }

    private function determineSynergyType($hero1, $hero2)
    {
        // This would be more sophisticated with actual hero role data
        return 'gameplay'; // Could be 'combo', 'utility', 'protection', etc.
    }

    private function generateSynergyInsights($synergies)
    {
        return [
            'strongest_synergy' => $synergies->first(),
            'most_popular_synergy' => $synergies->sortByDesc('games_together')->first(),
            'role_synergies' => $synergies->groupBy('synergy_type')->map->count()
        ];
    }

    private function calculateMatchupConfidence($games)
    {
        // Simple confidence based on sample size
        if ($games >= 50) return 1.0;
        if ($games >= 20) return 0.8;
        if ($games >= 10) return 0.6;
        return 0.4;
    }

    private function generateCounterInsights($counters)
    {
        return [
            'hardest_counter' => $counters->sortByDesc(function($counter) {
                return abs($counter['hero1_win_rate'] - 50);
            })->first(),
            'most_balanced_matchup' => $counters->sortBy(function($counter) {
                return abs($counter['hero1_win_rate'] - 50);
            })->first()
        ];
    }

    private function calculateMapEffectiveness($winRate, $avgRating)
    {
        return ($winRate * 0.6) + ($avgRating * 20);
    }

    private function calculateMapVersatility($mapData)
    {
        $effectiveness = $mapData->pluck('effectiveness');
        $stdDev = $effectiveness->count() > 1 ? 
            sqrt($effectiveness->map(function($eff) use ($effectiveness) {
                return pow($eff - $effectiveness->avg(), 2);
            })->sum() / ($effectiveness->count() - 1)) : 0;
        
        return max(0, round(100 - ($stdDev * 2), 1));
    }

    private function getHeroPicksInPeriod($startDate, $endDate, $region, $tier)
    {
        $query = MatchPlayerStat::whereHas('match', function($q) use ($startDate, $endDate, $region, $tier) {
                $q->where('created_at', '>=', $startDate)
                  ->where('created_at', '<', $endDate)
                  ->where('status', 'completed');
                  
                if ($region) {
                    $q->whereHas('team1', function($teamQuery) use ($region) {
                        $teamQuery->where('region', $region);
                    });
                }
                
                if ($tier) {
                    $q->whereHas('event', function($eventQuery) use ($tier) {
                        $eventQuery->where('tier', $tier);
                    });
                }
            })
            ->whereNotNull('hero');

        $picks = $query->selectRaw('hero, COUNT(*) as pick_count')
            ->groupBy('hero')
            ->get();

        $totalPicks = $picks->sum('pick_count');

        return $picks->mapWithKeys(function($pick) use ($totalPicks) {
            return [
                $pick->hero => [
                    'pick_count' => $pick->pick_count,
                    'pick_rate' => $totalPicks > 0 ? round(($pick->pick_count / $totalPicks) * 100, 2) : 0
                ]
            ];
        })->toArray();
    }

    private function calculateEmergenceScore($recentData, $earlierData)
    {
        $pickIncrease = $recentData['pick_count'] - $earlierData['pick_count'];
        $rateIncrease = $recentData['pick_rate'] - $earlierData['pick_rate'];
        
        return ($pickIncrease * 0.4) + ($rateIncrease * 2);
    }

    private function findSleeperPicks($recentPicks, $earlierPicks)
    {
        $sleepers = [];
        
        foreach ($recentPicks as $heroName => $recentData) {
            if ($recentData['pick_rate'] < 5 && $recentData['pick_rate'] > 1) { // Low but present
                $earlierData = $earlierPicks[$heroName] ?? ['pick_rate' => 0];
                
                if ($recentData['pick_rate'] > $earlierData['pick_rate']) {
                    $sleepers[] = [
                        'hero' => $heroName,
                        'pick_rate' => $recentData['pick_rate'],
                        'growth' => $recentData['pick_rate'] - $earlierData['pick_rate']
                    ];
                }
            }
        }
        
        return array_slice($sleepers, 0, 5);
    }

    private function findDecliningPicks($recentPicks, $earlierPicks)
    {
        $declining = [];
        
        foreach ($earlierPicks as $heroName => $earlierData) {
            $recentData = $recentPicks[$heroName] ?? ['pick_rate' => 0];
            
            $decline = $earlierData['pick_rate'] - $recentData['pick_rate'];
            
            if ($decline > 2) { // Significant decline
                $declining[] = [
                    'hero' => $heroName,
                    'earlier_pick_rate' => $earlierData['pick_rate'],
                    'recent_pick_rate' => $recentData['pick_rate'],
                    'decline' => round($decline, 2)
                ];
            }
        }
        
        usort($declining, function($a, $b) {
            return $b['decline'] <=> $a['decline'];
        });
        
        return array_slice($declining, 0, 10);
    }

    // Additional sophisticated analysis methods would continue...
    // These provide the core framework for comprehensive hero analytics

    private function findTrendingUpHeroes($weeks)
    {
        // Implementation for finding heroes trending upward
        return [];
    }

    private function findTrendingDownHeroes($weeks)
    {
        // Implementation for finding heroes trending downward  
        return [];
    }

    private function identifyMetaShifts($weeks)
    {
        // Implementation for identifying significant meta changes
        return [];
    }

    private function findRegionalUniqueness($regionalMeta)
    {
        // Implementation for finding region-specific hero preferences
        return [];
    }

    private function compareGlobalVsRegional($regionalMeta)
    {
        // Implementation for global vs regional comparison
        return [];
    }

    private function analyzeTierDifferences($tierAnalysis)
    {
        // Implementation for analyzing differences between competitive tiers
        return [];
    }

    private function analyzeSkillCeiling($tierAnalysis)
    {
        // Implementation for skill ceiling analysis
        return [];
    }

    // Additional helper methods for role analysis
    private function calculateRoleDiversity($role)
    {
        return round($role->unique_heroes * 10, 1);
    }

    private function assessRoleHealth($role)
    {
        if ($role->unique_heroes >= 8) return 'healthy';
        if ($role->unique_heroes >= 5) return 'moderate';
        return 'limited';
    }

    private function getDominantHeroForRole($roleName, $startDate)
    {
        $dominantHero = MatchPlayerStat::where('hero_role', $roleName)
            ->whereHas('match', function($q) use ($startDate) {
                $q->where('created_at', '>=', $startDate)
                  ->where('status', 'completed');
            })
            ->selectRaw('hero, COUNT(*) as picks')
            ->groupBy('hero')
            ->orderBy('picks', 'desc')
            ->first();

        return $dominantHero ? $dominantHero->hero : null;
    }

    private function calculateHeroMapWinRates($mapStats, $startDate)
    {
        // Simplified implementation
        $winRates = [];
        
        foreach ($mapStats as $stat) {
            $key = $stat->hero . '_' . $stat->map_name;
            
            // This would need more complex win rate calculation
            // For now, using performance rating as proxy
            $winRate = max(0, min(100, ($stat->avg_rating - 0.5) * 100));
            $winRates[$key] = $winRate;
        }
        
        return $winRates;
    }
}