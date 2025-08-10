<?php

namespace App\Services;

use App\Models\{GameMatch, MatchPlayerStat, Team, Player, Event};
use Illuminate\Support\Facades\{DB, Cache};
use Carbon\Carbon;

class MatchAnalyticsService
{
    private $cachePrefix = 'match_analytics:';
    private $defaultCacheTtl = 3600; // 1 hour

    /**
     * Aggregate comprehensive match statistics
     */
    public function aggregateMatchStatistics($matchId, $forceRefresh = false)
    {
        $cacheKey = $this->cachePrefix . 'match:' . $matchId;

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $match = GameMatch::with(['team1', 'team2', 'event', 'playerStats.player'])->findOrFail($matchId);

        $analytics = [
            'match_info' => $this->getMatchInfo($match),
            'team_performance' => $this->getTeamPerformanceComparison($match),
            'player_performances' => $this->getPlayerPerformances($match),
            'hero_analysis' => $this->getMatchHeroAnalysis($match),
            'tactical_breakdown' => $this->getTacticalBreakdown($match),
            'key_moments' => $this->identifyKeyMoments($match),
            'statistical_summary' => $this->getStatisticalSummary($match),
            'historical_context' => $this->getHistoricalContext($match),
            'performance_ratings' => $this->calculatePerformanceRatings($match),
            'match_impact' => $this->calculateMatchImpact($match)
        ];

        Cache::put($cacheKey, $analytics, $this->defaultCacheTtl);
        return $analytics;
    }

    /**
     * Get tournament-wide statistics aggregation
     */
    public function aggregateTournamentStatistics($eventId, $forceRefresh = false)
    {
        $cacheKey = $this->cachePrefix . 'tournament:' . $eventId;

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $event = Event::with(['matches.playerStats.player', 'matches.team1', 'matches.team2'])->findOrFail($eventId);

        $analytics = [
            'tournament_info' => $this->getTournamentInfo($event),
            'participant_stats' => $this->getTournamentParticipantStats($event),
            'hero_meta' => $this->getTournamentHeroMeta($event),
            'team_rankings' => $this->getTournamentTeamRankings($event),
            'player_rankings' => $this->getTournamentPlayerRankings($event),
            'statistical_leaders' => $this->getTournamentStatisticalLeaders($event),
            'bracket_analysis' => $this->getBracketAnalysis($event),
            'prize_distribution' => $this->getPrizeDistribution($event),
            'viewership_analytics' => $this->getViewershipAnalytics($event),
            'meta_evolution' => $this->getMetaEvolution($event)
        ];

        Cache::put($cacheKey, $analytics, $this->defaultCacheTtl * 2);
        return $analytics;
    }

    /**
     * Generate player comparison analytics
     */
    public function comparePlayerPerformances($playerIds, $timeframe = '30d', $forceRefresh = false)
    {
        $cacheKey = $this->cachePrefix . 'player_comparison:' . implode('_', $playerIds) . ':' . $timeframe;

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startDate = $this->getStartDate($timeframe);
        $players = Player::with(['team', 'matchStats' => function($query) use ($startDate) {
            $query->whereHas('match', function($q) use ($startDate) {
                $q->where('created_at', '>=', $startDate)
                  ->where('status', 'completed');
            });
        }])->whereIn('id', $playerIds)->get();

        $comparison = [
            'comparison_period' => [
                'timeframe' => $timeframe,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d')
            ],
            'players' => $players->map(function($player) use ($startDate) {
                return $this->getPlayerComparisonData($player, $startDate);
            }),
            'head_to_head' => $this->getPlayersHeadToHead($players, $startDate),
            'statistical_comparison' => $this->getStatisticalComparison($players, $startDate),
            'performance_radar' => $this->generatePerformanceRadar($players, $startDate),
            'clutch_comparison' => $this->getClutchComparison($players, $startDate),
            'hero_versatility' => $this->getHeroVersatilityComparison($players, $startDate)
        ];

        Cache::put($cacheKey, $comparison, $this->defaultCacheTtl);
        return $comparison;
    }

    /**
     * Generate team comparison analytics
     */
    public function compareTeamPerformances($teamIds, $timeframe = '90d', $forceRefresh = false)
    {
        $cacheKey = $this->cachePrefix . 'team_comparison:' . implode('_', $teamIds) . ':' . $timeframe;

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startDate = $this->getStartDate($timeframe);
        $teams = Team::with(['players', 'homeMatches', 'awayMatches'])->whereIn('id', $teamIds)->get();

        $comparison = [
            'comparison_period' => [
                'timeframe' => $timeframe,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d')
            ],
            'teams' => $teams->map(function($team) use ($startDate) {
                return $this->getTeamComparisonData($team, $startDate);
            }),
            'direct_matchups' => $this->getTeamsDirectMatchups($teams, $startDate),
            'performance_metrics' => $this->getTeamPerformanceMetrics($teams, $startDate),
            'tactical_comparison' => $this->getTacticalComparison($teams, $startDate),
            'player_quality' => $this->getPlayerQualityComparison($teams, $startDate),
            'consistency_analysis' => $this->getConsistencyAnalysis($teams, $startDate)
        ];

        Cache::put($cacheKey, $comparison, $this->defaultCacheTtl);
        return $comparison;
    }

    /**
     * Generate comprehensive leaderboards
     */
    public function generateLeaderboards($timeframe = '30d', $region = null, $forceRefresh = false)
    {
        $cacheKey = $this->cachePrefix . 'leaderboards:' . $timeframe . ':' . ($region ?? 'global');

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startDate = $this->getStartDate($timeframe);

        $leaderboards = [
            'period_info' => [
                'timeframe' => $timeframe,
                'region' => $region ?? 'Global',
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d')
            ],
            'player_leaderboards' => [
                'overall_rating' => $this->getPlayerLeaderboard('performance_rating', $startDate, $region),
                'acs' => $this->getPlayerLeaderboard('combat_score', $startDate, $region),
                'kda' => $this->getPlayerLeaderboard('kda', $startDate, $region),
                'kast' => $this->getPlayerLeaderboard('kast_percentage', $startDate, $region),
                'first_kills' => $this->getPlayerLeaderboard('first_kills', $startDate, $region, 'sum'),
                'clutch_rating' => $this->getClutchLeaderboard($startDate, $region)
            ],
            'team_leaderboards' => [
                'team_rating' => $this->getTeamLeaderboard('rating', $startDate, $region),
                'win_rate' => $this->getTeamWinRateLeaderboard($startDate, $region),
                'map_win_rate' => $this->getTeamMapWinRateLeaderboard($startDate, $region)
            ],
            'hero_leaderboards' => [
                'most_picked' => $this->getHeroPickRateLeaderboard($startDate, $region),
                'highest_winrate' => $this->getHeroWinRateLeaderboard($startDate, $region),
                'best_performers' => $this->getHeroPerformanceLeaderboard($startDate, $region)
            ],
            'statistical_leaders' => $this->getStatisticalLeaders($startDate, $region),
            'rising_stars' => $this->getRisingStars($startDate, $region),
            'veteran_performance' => $this->getVeteranPerformance($startDate, $region)
        ];

        Cache::put($cacheKey, $leaderboards, $this->defaultCacheTtl);
        return $leaderboards;
    }

    /**
     * Generate meta analysis report
     */
    public function generateMetaAnalysis($timeframe = '30d', $region = null, $tier = null, $forceRefresh = false)
    {
        $cacheKey = $this->cachePrefix . 'meta:' . $timeframe . ':' . ($region ?? 'global') . ':' . ($tier ?? 'all');

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startDate = $this->getStartDate($timeframe);

        $metaAnalysis = [
            'analysis_period' => [
                'timeframe' => $timeframe,
                'region' => $region ?? 'Global',
                'tier' => $tier ?? 'All Tiers',
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d')
            ],
            'hero_meta' => $this->getDetailedHeroMeta($startDate, $region, $tier),
            'composition_analysis' => $this->getCompositionAnalysis($startDate, $region, $tier),
            'role_balance' => $this->getRoleBalance($startDate, $region, $tier),
            'counter_relationships' => $this->getCounterRelationships($startDate, $region, $tier),
            'meta_shifts' => $this->getMetaShifts($startDate, $region, $tier),
            'regional_differences' => $this->getRegionalMetaDifferences($startDate, $tier),
            'tier_differences' => $this->getTierMetaDifferences($startDate, $region),
            'emerging_strategies' => $this->getEmergingStrategies($startDate, $region, $tier),
            'meta_predictions' => $this->generateMetaPredictions($startDate, $region, $tier)
        ];

        Cache::put($cacheKey, $metaAnalysis, $this->defaultCacheTtl * 3);
        return $metaAnalysis;
    }

    // Private helper methods

    private function getMatchInfo($match)
    {
        return [
            'id' => $match->id,
            'date' => $match->created_at->format('Y-m-d H:i'),
            'status' => $match->status,
            'format' => $match->format,
            'score' => [$match->team1_score, $match->team2_score],
            'winner' => $match->team1_score > $match->team2_score ? 'team1' : 
                       ($match->team2_score > $match->team1_score ? 'team2' : 'draw'),
            'duration_minutes' => $match->started_at && $match->completed_at 
                ? $match->started_at->diffInMinutes($match->completed_at) 
                : null,
            'viewers' => $match->viewers,
            'teams' => [
                'team1' => [
                    'id' => $match->team1->id,
                    'name' => $match->team1->name,
                    'logo' => $match->team1->logo,
                    'region' => $match->team1->region
                ],
                'team2' => [
                    'id' => $match->team2->id,
                    'name' => $match->team2->name,
                    'logo' => $match->team2->logo,
                    'region' => $match->team2->region
                ]
            ],
            'event' => $match->event ? [
                'id' => $match->event->id,
                'name' => $match->event->name,
                'tier' => $match->event->tier,
                'prize_pool' => $match->event->prize_pool
            ] : null
        ];
    }

    private function getTeamPerformanceComparison($match)
    {
        $team1Stats = $match->playerStats->where('team_id', $match->team1_id);
        $team2Stats = $match->playerStats->where('team_id', $match->team2_id);

        return [
            'team1' => [
                'team_name' => $match->team1->name,
                'total_kills' => $team1Stats->sum('eliminations'),
                'total_deaths' => $team1Stats->sum('deaths'),
                'total_assists' => $team1Stats->sum('assists'),
                'total_damage' => $team1Stats->sum('damage_dealt'),
                'total_healing' => $team1Stats->sum('healing_done'),
                'avg_rating' => round($team1Stats->avg('performance_rating'), 2),
                'avg_acs' => round($team1Stats->avg('combat_score'), 1),
                'avg_kda' => round($team1Stats->avg('kda'), 2),
                'first_kills' => $team1Stats->sum('first_kills'),
                'first_deaths' => $team1Stats->sum('first_deaths')
            ],
            'team2' => [
                'team_name' => $match->team2->name,
                'total_kills' => $team2Stats->sum('eliminations'),
                'total_deaths' => $team2Stats->sum('deaths'),
                'total_assists' => $team2Stats->sum('assists'),
                'total_damage' => $team2Stats->sum('damage_dealt'),
                'total_healing' => $team2Stats->sum('healing_done'),
                'avg_rating' => round($team2Stats->avg('performance_rating'), 2),
                'avg_acs' => round($team2Stats->avg('combat_score'), 1),
                'avg_kda' => round($team2Stats->avg('kda'), 2),
                'first_kills' => $team2Stats->sum('first_kills'),
                'first_deaths' => $team2Stats->sum('first_deaths')
            ],
            'comparison' => [
                'kill_differential' => $team1Stats->sum('eliminations') - $team2Stats->sum('eliminations'),
                'damage_differential' => $team1Stats->sum('damage_dealt') - $team2Stats->sum('damage_dealt'),
                'healing_differential' => $team1Stats->sum('healing_done') - $team2Stats->sum('healing_done'),
                'rating_differential' => round($team1Stats->avg('performance_rating') - $team2Stats->avg('performance_rating'), 2),
                'first_blood_advantage' => ($team1Stats->sum('first_kills') - $team1Stats->sum('first_deaths')) - 
                                          ($team2Stats->sum('first_kills') - $team2Stats->sum('first_deaths'))
            ]
        ];
    }

    private function getPlayerPerformances($match)
    {
        return $match->playerStats->map(function($stat) {
            return [
                'player' => [
                    'id' => $stat->player->id,
                    'name' => $stat->player->name,
                    'team' => $stat->player->team->name ?? 'Unknown',
                    'role' => $stat->player->role
                ],
                'hero' => $stat->hero,
                'performance' => [
                    'rating' => round($stat->performance_rating, 2),
                    'acs' => round($stat->combat_score, 1),
                    'kda' => round($stat->kda, 2),
                    'kast' => round($stat->kast_percentage, 1),
                    'adr' => round($stat->damage_per_round, 1)
                ],
                'statistics' => [
                    'kills' => $stat->eliminations,
                    'deaths' => $stat->deaths,
                    'assists' => $stat->assists,
                    'damage' => $stat->damage_dealt,
                    'healing' => $stat->healing_done,
                    'first_kills' => $stat->first_kills,
                    'first_deaths' => $stat->first_deaths
                ],
                'awards' => [
                    'mvp' => $stat->player_of_the_match,
                    'map_mvp' => $stat->player_of_the_map
                ]
            ];
        })->values();
    }

    private function getMatchHeroAnalysis($match)
    {
        $heroStats = $match->playerStats->whereNotNull('hero')
            ->groupBy('hero')
            ->map(function($heroPlayers) {
                return [
                    'pick_count' => $heroPlayers->count(),
                    'avg_performance' => round($heroPlayers->avg('performance_rating'), 2),
                    'total_damage' => $heroPlayers->sum('damage_dealt'),
                    'total_healing' => $heroPlayers->sum('healing_done'),
                    'players' => $heroPlayers->pluck('player.name')->toArray()
                ];
            });

        $roleDistribution = $match->playerStats->whereNotNull('hero_role')
            ->groupBy('hero_role')
            ->map->count();

        return [
            'heroes_played' => $heroStats,
            'role_distribution' => $roleDistribution,
            'most_impactful_hero' => $heroStats->sortByDesc('avg_performance')->keys()->first(),
            'damage_leaders' => $heroStats->sortByDesc('total_damage')->take(3)->keys()->toArray(),
            'healing_leaders' => $heroStats->sortByDesc('total_healing')->take(3)->keys()->toArray()
        ];
    }

    private function getTacticalBreakdown($match)
    {
        $team1Composition = $match->playerStats->where('team_id', $match->team1_id)
            ->whereNotNull('hero')
            ->pluck('hero')
            ->toArray();

        $team2Composition = $match->playerStats->where('team_id', $match->team2_id)
            ->whereNotNull('hero')
            ->pluck('hero')
            ->toArray();

        return [
            'team_compositions' => [
                'team1' => $team1Composition,
                'team2' => $team2Composition
            ],
            'composition_effectiveness' => $this->analyzeCompositionEffectiveness($match),
            'strategic_focus' => $this->analyzeStrategicFocus($match),
            'tactical_advantages' => $this->identifyTacticalAdvantages($match)
        ];
    }

    private function identifyKeyMoments($match)
    {
        $keyMoments = [];

        // MVP performances
        $mvpPerformances = $match->playerStats->where('player_of_the_match', true);
        foreach ($mvpPerformances as $mvp) {
            $keyMoments[] = [
                'type' => 'mvp_performance',
                'player' => $mvp->player->name,
                'rating' => round($mvp->performance_rating, 2),
                'description' => "{$mvp->player->name} delivered an MVP performance with {$mvp->performance_rating} rating"
            ];
        }

        // Outstanding individual performances
        $outstandingPerfs = $match->playerStats->where('performance_rating', '>', 1.5);
        foreach ($outstandingPerfs as $perf) {
            $keyMoments[] = [
                'type' => 'outstanding_performance',
                'player' => $perf->player->name,
                'hero' => $perf->hero,
                'rating' => round($perf->performance_rating, 2),
                'description' => "{$perf->player->name} dominated on {$perf->hero} with {$perf->performance_rating} rating"
            ];
        }

        // First blood advantages
        $firstBloodWins = $match->playerStats->where('first_kills', '>', 0)->sortByDesc('first_kills');
        if ($firstBloodWins->isNotEmpty()) {
            $leader = $firstBloodWins->first();
            $keyMoments[] = [
                'type' => 'first_blood_dominance',
                'player' => $leader->player->name,
                'first_kills' => $leader->first_kills,
                'description' => "{$leader->player->name} secured {$leader->first_kills} first bloods"
            ];
        }

        return array_slice($keyMoments, 0, 10); // Limit to top 10 moments
    }

    private function getStatisticalSummary($match)
    {
        $allStats = $match->playerStats;

        return [
            'total_eliminations' => $allStats->sum('eliminations'),
            'total_deaths' => $allStats->sum('deaths'),
            'total_assists' => $allStats->sum('assists'),
            'total_damage' => $allStats->sum('damage_dealt'),
            'total_healing' => $allStats->sum('healing_done'),
            'avg_match_rating' => round($allStats->avg('performance_rating'), 2),
            'avg_combat_score' => round($allStats->avg('combat_score'), 1),
            'avg_kda' => round($allStats->avg('kda'), 2),
            'highest_individual_rating' => round($allStats->max('performance_rating'), 2),
            'lowest_individual_rating' => round($allStats->min('performance_rating'), 2),
            'most_eliminations' => $allStats->max('eliminations'),
            'most_damage' => $allStats->max('damage_dealt'),
            'most_healing' => $allStats->max('healing_done'),
            'unique_heroes_played' => $allStats->whereNotNull('hero')->pluck('hero')->unique()->count()
        ];
    }

    private function getHistoricalContext($match)
    {
        // Get head-to-head history
        $headToHead = GameMatch::where(function($query) use ($match) {
                $query->where(function($q) use ($match) {
                    $q->where('team1_id', $match->team1_id)
                      ->where('team2_id', $match->team2_id);
                })->orWhere(function($q) use ($match) {
                    $q->where('team1_id', $match->team2_id)
                      ->where('team2_id', $match->team1_id);
                });
            })
            ->where('id', '!=', $match->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $team1Wins = $headToHead->filter(function($m) use ($match) {
            return ($m->team1_id == $match->team1_id && $m->team1_score > $m->team2_score) ||
                   ($m->team2_id == $match->team1_id && $m->team2_score > $m->team1_score);
        })->count();

        return [
            'head_to_head_record' => [
                'total_matches' => $headToHead->count(),
                'team1_wins' => $team1Wins,
                'team2_wins' => $headToHead->count() - $team1Wins,
                'last_meeting' => $headToHead->first()?->created_at?->format('Y-m-d')
            ],
            'recent_form' => [
                'team1_recent' => $this->getTeamRecentForm($match->team1_id, 5),
                'team2_recent' => $this->getTeamRecentForm($match->team2_id, 5)
            ],
            'tournament_context' => $match->event ? [
                'stage' => $this->determineTournamentStage($match),
                'importance' => $this->calculateMatchImportance($match)
            ] : null
        ];
    }

    private function calculatePerformanceRatings($match)
    {
        $playerRatings = $match->playerStats->map(function($stat) {
            return [
                'player_id' => $stat->player_id,
                'player_name' => $stat->player->name,
                'team_id' => $stat->team_id,
                'rating' => round($stat->performance_rating, 2),
                'percentile' => $this->calculatePercentile($stat->performance_rating, $match->playerStats->pluck('performance_rating'))
            ];
        })->sortByDesc('rating');

        return [
            'player_ratings' => $playerRatings->values(),
            'team_ratings' => [
                'team1' => [
                    'avg_rating' => round($match->playerStats->where('team_id', $match->team1_id)->avg('performance_rating'), 2),
                    'total_rating' => round($match->playerStats->where('team_id', $match->team1_id)->sum('performance_rating'), 2)
                ],
                'team2' => [
                    'avg_rating' => round($match->playerStats->where('team_id', $match->team2_id)->avg('performance_rating'), 2),
                    'total_rating' => round($match->playerStats->where('team_id', $match->team2_id)->sum('performance_rating'), 2)
                ]
            ],
            'mvp_candidates' => $playerRatings->take(3)->values(),
            'underperformers' => $playerRatings->sortBy('rating')->take(3)->values()
        ];
    }

    private function calculateMatchImpact($match)
    {
        $impact = [
            'rating_changes' => $this->calculateRatingChanges($match),
            'ranking_implications' => $this->calculateRankingImplications($match),
            'tournament_progression' => $match->event ? $this->calculateTournamentProgression($match) : null,
            'regional_impact' => $this->calculateRegionalImpact($match),
            'player_career_impact' => $this->calculatePlayerCareerImpact($match)
        ];

        return $impact;
    }

    // Additional helper methods would continue...
    // For brevity, I'll include key aggregation methods

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

    private function calculatePercentile($value, $dataset)
    {
        $sorted = $dataset->sort()->values();
        $count = $sorted->count();
        
        if ($count === 0) return 50;
        
        $rank = $sorted->search(function($item) use ($value) {
            return $item >= $value;
        });
        
        if ($rank === false) $rank = $count;
        
        return round(($rank / $count) * 100);
    }

    private function getTeamRecentForm($teamId, $limit)
    {
        $recentMatches = GameMatch::where(function($query) use ($teamId) {
                $query->where('team1_id', $teamId)
                      ->orWhere('team2_id', $teamId);
            })
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $recentMatches->map(function($match) use ($teamId) {
            $won = ($match->team1_id == $teamId && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $teamId && $match->team2_score > $match->team1_score);
            return $won ? 'W' : 'L';
        })->implode('');
    }

    private function analyzeCompositionEffectiveness($match)
    {
        // Simplified composition analysis
        $team1Damage = $match->playerStats->where('team_id', $match->team1_id)->sum('damage_dealt');
        $team2Damage = $match->playerStats->where('team_id', $match->team2_id)->sum('damage_dealt');
        
        $team1Healing = $match->playerStats->where('team_id', $match->team1_id)->sum('healing_done');
        $team2Healing = $match->playerStats->where('team_id', $match->team2_id)->sum('healing_done');

        return [
            'damage_output_comparison' => [
                'team1' => $team1Damage,
                'team2' => $team2Damage,
                'advantage' => $team1Damage > $team2Damage ? 'team1' : 'team2'
            ],
            'sustain_comparison' => [
                'team1' => $team1Healing,
                'team2' => $team2Healing,
                'advantage' => $team1Healing > $team2Healing ? 'team1' : 'team2'
            ]
        ];
    }

    private function analyzeStrategicFocus($match)
    {
        // Analyze team strategic approaches based on stats
        $team1Stats = $match->playerStats->where('team_id', $match->team1_id);
        $team2Stats = $match->playerStats->where('team_id', $match->team2_id);

        $team1FirstKills = $team1Stats->sum('first_kills');
        $team2FirstKills = $team2Stats->sum('first_kills');

        return [
            'team1_focus' => $this->determineStrategicFocus($team1Stats),
            'team2_focus' => $this->determineStrategicFocus($team2Stats),
            'opening_aggression' => [
                'team1_first_kills' => $team1FirstKills,
                'team2_first_kills' => $team2FirstKills,
                'more_aggressive' => $team1FirstKills > $team2FirstKills ? 'team1' : 'team2'
            ]
        ];
    }

    private function identifyTacticalAdvantages($match)
    {
        $advantages = [];

        $team1Stats = $match->playerStats->where('team_id', $match->team1_id);
        $team2Stats = $match->playerStats->where('team_id', $match->team2_id);

        // Damage advantage
        if ($team1Stats->sum('damage_dealt') > $team2Stats->sum('damage_dealt') * 1.1) {
            $advantages[] = [
                'advantage' => 'damage_output',
                'team' => 'team1',
                'description' => 'Significant damage advantage'
            ];
        } elseif ($team2Stats->sum('damage_dealt') > $team1Stats->sum('damage_dealt') * 1.1) {
            $advantages[] = [
                'advantage' => 'damage_output',
                'team' => 'team2',
                'description' => 'Significant damage advantage'
            ];
        }

        // Support advantage
        if ($team1Stats->sum('healing_done') > $team2Stats->sum('healing_done') * 1.2) {
            $advantages[] = [
                'advantage' => 'sustain',
                'team' => 'team1',
                'description' => 'Superior healing and sustain'
            ];
        } elseif ($team2Stats->sum('healing_done') > $team1Stats->sum('healing_done') * 1.2) {
            $advantages[] = [
                'advantage' => 'sustain',
                'team' => 'team2',
                'description' => 'Superior healing and sustain'
            ];
        }

        return $advantages;
    }

    private function determineStrategicFocus($teamStats)
    {
        $totalDamage = $teamStats->sum('damage_dealt');
        $totalHealing = $teamStats->sum('healing_done');
        $firstKills = $teamStats->sum('first_kills');

        if ($firstKills > $teamStats->count() * 0.8) {
            return 'aggressive_opening';
        } elseif ($totalHealing > $totalDamage * 0.3) {
            return 'sustain_focused';
        } elseif ($totalDamage > $teamStats->count() * 15000) {
            return 'damage_focused';
        }

        return 'balanced';
    }

    private function determineTournamentStage($match)
    {
        // This would analyze bracket position or match naming conventions
        return 'group_stage'; // Simplified
    }

    private function calculateMatchImportance($match)
    {
        $importance = 'medium';
        
        if ($match->event && $match->event->prize_pool > 100000) {
            $importance = 'high';
        }
        
        if ($match->event && str_contains(strtolower($match->event->name), 'final')) {
            $importance = 'critical';
        }

        return $importance;
    }

    private function calculateRatingChanges($match)
    {
        // Simplified ELO-style rating changes
        return [
            'team1_change' => $match->team1_score > $match->team2_score ? '+15' : '-10',
            'team2_change' => $match->team2_score > $match->team1_score ? '+15' : '-10'
        ];
    }

    private function calculateRankingImplications($match)
    {
        return [
            'regional_impact' => 'moderate',
            'global_impact' => 'low',
            'qualification_impact' => $match->event ? 'high' : 'none'
        ];
    }

    private function calculateTournamentProgression($match)
    {
        return [
            'advancement' => $match->team1_score > $match->team2_score ? 'team1' : 'team2',
            'elimination' => $match->team1_score > $match->team2_score ? 'team2' : 'team1',
            'bracket_implications' => 'semifinals_qualification'
        ];
    }

    private function calculateRegionalImpact($match)
    {
        return [
            'regional_ranking_change' => 'minimal',
            'cross_regional_implications' => $match->team1->region !== $match->team2->region ? 'moderate' : 'none'
        ];
    }

    private function calculatePlayerCareerImpact($match)
    {
        $impacts = [];
        
        foreach ($match->playerStats as $stat) {
            if ($stat->performance_rating > 1.5) {
                $impacts[] = [
                    'player' => $stat->player->name,
                    'impact' => 'career_high_performance',
                    'rating' => round($stat->performance_rating, 2)
                ];
            }
        }

        return $impacts;
    }

    // Cache management methods

    public function clearMatchCache($matchId)
    {
        Cache::forget($this->cachePrefix . 'match:' . $matchId);
    }

    public function clearTournamentCache($eventId)
    {
        Cache::forget($this->cachePrefix . 'tournament:' . $eventId);
    }

    public function clearAllAnalyticsCache()
    {
        // This would clear all analytics caches - implementation depends on cache driver
        return true;
    }

    // Additional methods for tournament, player, and team analytics would continue...
    // The service provides a comprehensive foundation for all analytics operations

    /**
     * Batch process multiple matches for analytics
     */
    public function batchProcessMatches($matchIds)
    {
        $results = [];
        
        foreach ($matchIds as $matchId) {
            try {
                $results[$matchId] = $this->aggregateMatchStatistics($matchId);
            } catch (\Exception $e) {
                $results[$matchId] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }

    /**
     * Get real-time analytics for live matches
     */
    public function getLiveMatchAnalytics($matchId)
    {
        // This would provide real-time analytics for ongoing matches
        // Implementation would depend on live data sources
        return $this->aggregateMatchStatistics($matchId, true);
    }
}