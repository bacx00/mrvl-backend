<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'username', 'alternate_ids', 'real_name', 'romanized_name', 'avatar', 
        'team_id', 'past_teams', 'role', 'team_position', 'position_order', 'jersey_number',
        'hero_preferences', 'skill_rating', 'main_hero', 'alt_heroes', 'region', 'country', 
        'flag', 'country_flag', 'country_code', 'nationality', 'team_country', 'rank', 
        'rating', 'elo_rating', 'peak_elo', 'elo_changes', 'last_elo_update', 'peak_rating',
        'age', 'birth_date', 'earnings', 'earnings_amount', 'earnings_currency', 'total_earnings',
        'wins', 'losses', 'kda', // Added missing stats fields
        'total_matches', 'tournaments_played', 'social_media', 'twitter', 'instagram', 
        'twitch', 'tiktok', 'youtube', 'facebook', 'discord', 'liquipedia_url', 
        'biography', 'event_placements', 'hero_pool', 'status',
        'total_eliminations', 'total_deaths', 'total_assists', 'overall_kda',
        'average_damage_per_match', 'average_healing_per_match', 'average_damage_blocked_per_match',
        'hero_statistics', 'most_played_hero', 'best_winrate_hero', 'longest_win_streak',
        'current_win_streak', 'achievements', 'mention_count', 'last_mentioned_at'
    ];

    protected $casts = [
        'rating' => 'float',
        'age' => 'integer',
        'earnings' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'wins' => 'integer',
        'losses' => 'integer',
        'kda' => 'decimal:2',
        'alt_heroes' => 'array',
        'social_media' => 'array',
        'past_teams' => 'array',
        'total_matches' => 'integer',
        'total_wins' => 'integer',
        'total_maps_played' => 'integer',
        'avg_rating' => 'decimal:2',
        'avg_combat_score' => 'decimal:2',
        'avg_kda' => 'decimal:2',
        'avg_damage_per_round' => 'decimal:2',
        'avg_kast' => 'decimal:2',
        'avg_kills_per_round' => 'decimal:2',
        'avg_assists_per_round' => 'decimal:2',
        'avg_first_kills_per_round' => 'decimal:2',
        'avg_first_deaths_per_round' => 'decimal:2',
        'hero_pool' => 'array',
        'career_stats' => 'array',
        'achievements' => 'array',
        'mention_count' => 'integer',
        'last_mentioned_at' => 'datetime'
    ];

    protected $appends = []; // Removed problematic accessors

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function matches()
    {
        return $this->belongsToMany(GameMatch::class, 'match_player', 'player_id', 'match_id')
                   ->withPivot(['kills', 'deaths', 'assists', 'damage', 'healing']);
    }

    public function matchStats()
    {
        return $this->hasMany(MatchPlayerStat::class);
    }

    public function recentMatches($limit = 10)
    {
        return $this->matchStats()
            ->with(['match', 'match.team1', 'match.team2', 'match.event'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function teamHistory()
    {
        return $this->hasMany(PlayerTeamHistory::class)->orderBy('change_date', 'desc');
    }

    // Boot method to track team changes
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($player) {
            if ($player->isDirty('team_id')) {
                $originalTeamId = $player->getOriginal('team_id');
                $newTeamId = $player->team_id;

                // Create team history record
                PlayerTeamHistory::create([
                    'player_id' => $player->id,
                    'from_team_id' => $originalTeamId,
                    'to_team_id' => $newTeamId,
                    'change_date' => now(),
                    'change_type' => $player->determineChangeType($originalTeamId, $newTeamId),
                    'is_official' => true,
                    'announced_by' => auth()->id()
                ]);
            }
        });
    }

    private function determineChangeType($fromTeamId, $toTeamId)
    {
        if (!$fromTeamId && $toTeamId) {
            return 'joined';
        } elseif ($fromTeamId && !$toTeamId) {
            return 'left';
        } elseif ($fromTeamId && $toTeamId) {
            return 'transferred';
        }
        return 'transferred';
    }

    public function getCurrentTeamTenure()
    {
        $lastChange = $this->teamHistory()
            ->where('to_team_id', $this->team_id)
            ->latest('change_date')
            ->first();

        if ($lastChange) {
            return $lastChange->change_date->diffForHumans();
        }

        return 'Unknown';
    }

    /**
     * Get performance trends over time period
     */
    public function getPerformanceTrends($days = 30)
    {
        $startDate = now()->subDays($days);
        
        $dailyStats = $this->matchStats()
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
                AVG(kast_percentage) as avg_kast,
                COUNT(*) as maps_played
            ')
            ->groupBy(DB::raw('DATE(matches.created_at)'))
            ->orderBy('date')
            ->get();

        if ($dailyStats->isEmpty()) {
            return [
                'trend_direction' => 'stable',
                'rating_change' => 0,
                'consistency_score' => 0,
                'momentum' => 'neutral',
                'daily_data' => []
            ];
        }

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
                'kast' => round($stat->avg_kast, 1),
                'maps_played' => (int) $stat->maps_played,
                'moving_avg_rating' => round($windowData->avg('avg_rating'), 2),
                'moving_avg_acs' => round($windowData->avg('avg_acs'), 1)
            ];
        }

        return [
            'daily_data' => $trendData,
            'trend_direction' => $this->calculateTrendDirection($trendData),
            'rating_change' => $this->calculateRatingChange($trendData),
            'consistency_score' => $this->calculateConsistency($dailyStats->pluck('avg_rating')),
            'momentum' => $this->calculatePlayerMomentum($trendData)
        ];
    }

    /**
     * Get current form analysis
     */
    public function getCurrentForm($matchLimit = 10)
    {
        $recentMatches = $this->matchStats()
            ->whereHas('match', function($query) {
                $query->where('status', 'completed');
            })
            ->with(['match.team1', 'match.team2'])
            ->orderBy('created_at', 'desc')
            ->limit($matchLimit)
            ->get();

        if ($recentMatches->isEmpty()) {
            return [
                'form_rating' => 'unknown',
                'streak' => null,
                'recent_performance' => [],
                'consistency' => 0,
                'improvement_trend' => 'stable'
            ];
        }

        // Calculate match results
        $results = $recentMatches->map(function($stat) {
            $match = $stat->match;
            $won = ($match->team1_id == $this->team_id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $this->team_id && $match->team2_score > $match->team1_score);
            
            return [
                'result' => $won ? 'W' : 'L',
                'rating' => round($stat->performance_rating, 2),
                'acs' => round($stat->combat_score, 1),
                'kda' => round($stat->kda, 2),
                'date' => $match->created_at->format('Y-m-d'),
                'opponent' => $match->team1_id == $this->team_id 
                    ? $match->team2?->name 
                    : $match->team1?->name
            ];
        });

        return [
            'form_rating' => $this->determineFormRating($results->pluck('rating')),
            'streak' => $this->calculatePlayerStreak($results),
            'recent_performance' => $results->values(),
            'consistency' => $this->calculateConsistency($results->pluck('rating')),
            'improvement_trend' => $this->calculateImprovementTrend($results),
            'avg_recent_rating' => round($results->avg('rating'), 2),
            'best_recent_performance' => $results->sortByDesc('rating')->first(),
            'worst_recent_performance' => $results->sortBy('rating')->first()
        ];
    }

    /**
     * Get head-to-head performance against specific teams
     */
    public function getHeadToHeadPerformance($opponentTeamId = null, $startDate = null)
    {
        $query = $this->matchStats()
            ->whereHas('match', function($matchQuery) use ($opponentTeamId, $startDate) {
                $matchQuery->where('status', 'completed');
                
                if ($opponentTeamId) {
                    $matchQuery->where(function($q) use ($opponentTeamId) {
                        $q->where(function($subQ) use ($opponentTeamId) {
                            $subQ->where('team1_id', $this->team_id)
                                 ->where('team2_id', $opponentTeamId);
                        })->orWhere(function($subQ) use ($opponentTeamId) {
                            $subQ->where('team2_id', $this->team_id)
                                 ->where('team1_id', $opponentTeamId);
                        });
                    });
                }
                
                if ($startDate) {
                    $matchQuery->where('created_at', '>=', $startDate);
                }
            })
            ->with(['match.team1', 'match.team2']);

        if ($opponentTeamId) {
            $stats = $query->get();
            
            if ($stats->isEmpty()) {
                return [
                    'total_matches' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'avg_performance' => [],
                    'best_performance' => null,
                    'head_to_head_record' => []
                ];
            }

            $wins = $stats->filter(function($stat) {
                $match = $stat->match;
                return ($match->team1_id == $this->team_id && $match->team1_score > $match->team2_score) ||
                       ($match->team2_id == $this->team_id && $match->team2_score > $match->team1_score);
            })->count();

            return [
                'total_matches' => $stats->count(),
                'wins' => $wins,
                'losses' => $stats->count() - $wins,
                'avg_performance' => [
                    'rating' => round($stats->avg('performance_rating'), 2),
                    'acs' => round($stats->avg('combat_score'), 1),
                    'kda' => round($stats->avg('kda'), 2),
                    'kast' => round($stats->avg('kast_percentage'), 1)
                ],
                'best_performance' => $stats->sortByDesc('performance_rating')->first(),
                'head_to_head_record' => $stats->map(function($stat) {
                    $match = $stat->match;
                    $won = ($match->team1_id == $this->team_id && $match->team1_score > $match->team2_score) ||
                           ($match->team2_id == $this->team_id && $match->team2_score > $match->team1_score);
                    
                    return [
                        'date' => $match->created_at->format('Y-m-d'),
                        'result' => $won ? 'W' : 'L',
                        'rating' => round($stat->performance_rating, 2),
                        'hero' => $stat->hero,
                        'score' => [$match->team1_score, $match->team2_score]
                    ];
                })->values()
            ];
        }

        // Return performance vs all teams
        return $query->get()
            ->groupBy(function($stat) {
                $match = $stat->match;
                $opponentId = $match->team1_id == $this->team_id ? $match->team2_id : $match->team1_id;
                return $opponentId;
            })
            ->map(function($teamStats) {
                $firstMatch = $teamStats->first()->match;
                $opponent = $firstMatch->team1_id == $this->team_id 
                    ? $firstMatch->team1 
                    : $firstMatch->team2;

                $wins = $teamStats->filter(function($stat) {
                    $match = $stat->match;
                    return ($match->team1_id == $this->team_id && $match->team1_score > $match->team2_score) ||
                           ($match->team2_id == $this->team_id && $match->team2_score > $match->team1_score);
                })->count();

                return [
                    'opponent' => [
                        'id' => $opponent->id,
                        'name' => $opponent->name,
                        'logo' => $opponent->logo
                    ],
                    'matches' => $teamStats->count(),
                    'wins' => $wins,
                    'losses' => $teamStats->count() - $wins,
                    'avg_rating' => round($teamStats->avg('performance_rating'), 2),
                    'best_rating' => round($teamStats->max('performance_rating'), 2)
                ];
            })
            ->sortByDesc('avg_rating')
            ->values();
    }

    /**
     * Get clutch performance metrics
     */
    public function getClutchPerformance($startDate = null)
    {
        $query = $this->matchStats()
            ->whereHas('match', function($matchQuery) use ($startDate) {
                $matchQuery->where('status', 'completed');
                if ($startDate) {
                    $matchQuery->where('created_at', '>=', $startDate);
                }
            });

        $stats = $query->get();

        if ($stats->isEmpty()) {
            return [
                'clutch_rating' => 0,
                'pressure_performance' => [],
                'comeback_performance' => [],
                'decisive_moments' => []
            ];
        }

        // Analyze performance in different scenarios
        $highStakeMatches = $stats->filter(function($stat) {
            return $stat->match->event_id !== null; // Tournament matches
        });

        $closeMatches = $stats->filter(function($stat) {
            $match = $stat->match;
            $scoreDiff = abs($match->team1_score - $match->team2_score);
            return $scoreDiff <= 1; // Close matches (within 1 map)
        });

        return [
            'clutch_rating' => $this->calculateClutchRating($stats),
            'pressure_performance' => [
                'tournament_rating' => $highStakeMatches->isNotEmpty() 
                    ? round($highStakeMatches->avg('performance_rating'), 2) 
                    : 0,
                'regular_rating' => $stats->whereNull('match.event_id')->avg('performance_rating') ?? 0,
                'pressure_differential' => $highStakeMatches->isNotEmpty() 
                    ? round($highStakeMatches->avg('performance_rating') - $stats->avg('performance_rating'), 2)
                    : 0
            ],
            'close_match_performance' => [
                'close_match_rating' => $closeMatches->isNotEmpty() 
                    ? round($closeMatches->avg('performance_rating'), 2)
                    : 0,
                'close_match_count' => $closeMatches->count(),
                'clutch_factor' => $this->calculateClutchFactor($closeMatches)
            ],
            'mvp_frequency' => [
                'match_mvps' => $stats->where('player_of_the_match', true)->count(),
                'map_mvps' => $stats->where('player_of_the_map', true)->count(),
                'mvp_rate' => $stats->count() > 0 
                    ? round(($stats->where('player_of_the_match', true)->count() / $stats->count()) * 100, 1)
                    : 0
            ]
        ];
    }

    /**
     * Get career progression and milestones
     */
    public function getCareerProgression()
    {
        $allStats = $this->matchStats()
            ->whereHas('match', function($query) {
                $query->where('status', 'completed');
            })
            ->with('match')
            ->orderBy('created_at')
            ->get();

        if ($allStats->isEmpty()) {
            return [
                'career_timeline' => [],
                'milestones' => [],
                'peak_performance' => null,
                'improvement_areas' => []
            ];
        }

        $monthlyStats = $allStats->groupBy(function($stat) {
            return $stat->match->created_at->format('Y-m');
        })->map(function($monthStats) {
            return [
                'month' => $monthStats->first()->match->created_at->format('Y-m'),
                'matches' => $monthStats->count(),
                'avg_rating' => round($monthStats->avg('performance_rating'), 2),
                'avg_acs' => round($monthStats->avg('combat_score'), 1),
                'avg_kda' => round($monthStats->avg('kda'), 2),
                'mvp_count' => $monthStats->where('player_of_the_match', true)->count()
            ];
        });

        return [
            'career_timeline' => $monthlyStats->values(),
            'milestones' => $this->identifyMilestones($allStats),
            'peak_performance' => $this->findPeakPerformance($allStats),
            'improvement_trajectory' => $this->calculateImprovementTrajectory($monthlyStats),
            'consistency_over_time' => $this->calculateCareerConsistency($monthlyStats)
        ];
    }

    /**
     * Get hero mastery and specialization
     */
    public function getHeroMastery($startDate = null)
    {
        $query = $this->matchStats()
            ->whereHas('match', function($matchQuery) use ($startDate) {
                $matchQuery->where('status', 'completed');
                if ($startDate) {
                    $matchQuery->where('created_at', '>=', $startDate);
                }
            })
            ->whereNotNull('hero');

        $heroStats = $query->selectRaw('
                hero,
                COUNT(*) as matches_played,
                AVG(performance_rating) as avg_rating,
                AVG(combat_score) as avg_acs,
                AVG(kda) as avg_kda,
                MAX(performance_rating) as peak_rating,
                SUM(CASE WHEN player_of_the_match = 1 THEN 1 ELSE 0 END) as mvp_count
            ')
            ->groupBy('hero')
            ->orderBy('matches_played', 'desc')
            ->get();

        if ($heroStats->isEmpty()) {
            return [
                'hero_pool' => [],
                'specializations' => [],
                'versatility_score' => 0,
                'signature_heroes' => []
            ];
        }

        $totalMatches = $heroStats->sum('matches_played');

        $heroData = $heroStats->map(function($hero) use ($totalMatches) {
            $playRate = $totalMatches > 0 ? ($hero->matches_played / $totalMatches) * 100 : 0;
            
            return [
                'hero' => $hero->hero,
                'matches_played' => (int) $hero->matches_played,
                'play_rate' => round($playRate, 1),
                'avg_rating' => round($hero->avg_rating, 2),
                'avg_acs' => round($hero->avg_acs, 1),
                'avg_kda' => round($hero->avg_kda, 2),
                'peak_rating' => round($hero->peak_rating, 2),
                'mvp_count' => (int) $hero->mvp_count,
                'mastery_level' => $this->calculateHeroMastery($hero, $playRate),
                'effectiveness_score' => $this->calculateHeroEffectiveness($hero)
            ];
        });

        return [
            'hero_pool' => $heroData->values(),
            'signature_heroes' => $heroData->where('mastery_level', 'expert')->take(3)->values(),
            'versatility_score' => $this->calculateVersatilityScore($heroData),
            'role_distribution' => $this->calculateRoleDistribution($heroData),
            'improvement_recommendations' => $this->getHeroImprovementRecommendations($heroData)
        ];
    }

    // Helper methods

    private function calculateTrendDirection($trendData)
    {
        if (count($trendData) < 3) return 'stable';
        
        $recent = collect($trendData)->takeLast(3);
        $earlier = collect($trendData)->slice(0, 3);
        
        $recentAvg = $recent->avg('moving_avg_rating');
        $earlierAvg = $earlier->avg('moving_avg_rating');
        
        $difference = $recentAvg - $earlierAvg;
        
        if ($difference > 0.1) return 'improving';
        if ($difference < -0.1) return 'declining';
        return 'stable';
    }

    private function calculateRatingChange($trendData)
    {
        if (empty($trendData)) return 0;
        
        $first = collect($trendData)->first()['rating'] ?? 0;
        $last = collect($trendData)->last()['rating'] ?? 0;
        
        return round($last - $first, 2);
    }

    private function calculateConsistency($ratings)
    {
        if ($ratings->count() < 2) return 0;
        
        $mean = $ratings->avg();
        $variance = $ratings->map(function($rating) use ($mean) {
            return pow($rating - $mean, 2);
        })->avg();
        
        $standardDeviation = sqrt($variance);
        
        // Convert to consistency score (lower deviation = higher consistency)
        return max(0, round(100 - ($standardDeviation * 50), 1));
    }

    private function calculatePlayerMomentum($trendData)
    {
        if (count($trendData) < 3) return 'neutral';
        
        $recent = collect($trendData)->takeLast(3);
        $trend = $recent->pluck('rating');
        
        $isImproving = $trend->last() > $trend->first();
        $variance = $this->calculateConsistency($trend);
        
        if ($isImproving && $variance > 70) return 'strong_positive';
        if ($isImproving) return 'positive';
        if (!$isImproving && $variance > 70) return 'strong_negative';
        if (!$isImproving) return 'negative';
        return 'neutral';
    }

    private function determineFormRating($ratings)
    {
        if ($ratings->isEmpty()) return 'unknown';
        
        $avgRating = $ratings->avg();
        
        if ($avgRating >= 1.3) return 'excellent';
        if ($avgRating >= 1.1) return 'good';
        if ($avgRating >= 0.9) return 'average';
        if ($avgRating >= 0.7) return 'below_average';
        return 'poor';
    }

    private function calculatePlayerStreak($results)
    {
        if ($results->isEmpty()) return null;
        
        $streak = 0;
        $type = null;
        
        foreach ($results as $result) {
            if ($type === null) {
                $type = $result['result'] === 'W' ? 'win' : 'loss';
                $streak = 1;
            } elseif (($type === 'win' && $result['result'] === 'W') || 
                      ($type === 'loss' && $result['result'] === 'L')) {
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

    private function calculateImprovementTrend($results)
    {
        if ($results->count() < 4) return 'insufficient_data';
        
        $recent = $results->take(3);
        $older = $results->skip(3)->take(3);
        
        $recentAvg = $recent->avg('rating');
        $olderAvg = $older->avg('rating');
        
        $improvement = $recentAvg - $olderAvg;
        
        if ($improvement > 0.15) return 'strong_improvement';
        if ($improvement > 0.05) return 'improving';
        if ($improvement < -0.15) return 'declining';
        if ($improvement < -0.05) return 'slight_decline';
        return 'stable';
    }

    private function calculateClutchRating($stats)
    {
        if ($stats->isEmpty()) return 0;
        
        $baseRating = 50;
        
        // Factor in MVP performances
        $mvpRate = $stats->where('player_of_the_match', true)->count() / $stats->count();
        $baseRating += $mvpRate * 30;
        
        // Factor in first kill performance
        $firstKillRate = $stats->avg('first_kills') / max($stats->avg('eliminations'), 1);
        $baseRating += $firstKillRate * 15;
        
        // Factor in KDA in high-pressure situations
        $avgKDA = $stats->avg('kda');
        if ($avgKDA > 1.5) $baseRating += 15;
        elseif ($avgKDA > 1.0) $baseRating += 10;
        
        return min(100, round($baseRating, 1));
    }

    private function calculateClutchFactor($closeMatches)
    {
        if ($closeMatches->isEmpty()) return 0;
        
        $avgRating = $closeMatches->avg('performance_rating');
        $mvpCount = $closeMatches->where('player_of_the_match', true)->count();
        
        return round(($avgRating * 50) + ($mvpCount * 10), 1);
    }

    private function identifyMilestones($allStats)
    {
        $milestones = [];
        
        // Career highs
        $highestRating = $allStats->max('performance_rating');
        $highestRatingMatch = $allStats->where('performance_rating', $highestRating)->first();
        
        if ($highestRatingMatch) {
            $milestones[] = [
                'type' => 'career_high_rating',
                'value' => round($highestRating, 2),
                'date' => $highestRatingMatch->match->created_at->format('Y-m-d'),
                'description' => "Career high rating of {$highestRating}"
            ];
        }
        
        // First MVP
        $firstMVP = $allStats->where('player_of_the_match', true)->first();
        if ($firstMVP) {
            $milestones[] = [
                'type' => 'first_mvp',
                'date' => $firstMVP->match->created_at->format('Y-m-d'),
                'description' => 'First Match MVP award'
            ];
        }
        
        // Match count milestones
        $totalMatches = $allStats->count();
        $milestoneMatches = [100, 250, 500, 1000];
        
        foreach ($milestoneMatches as $milestone) {
            if ($totalMatches >= $milestone) {
                $milestoneMatch = $allStats->skip($milestone - 1)->first();
                if ($milestoneMatch) {
                    $milestones[] = [
                        'type' => 'match_milestone',
                        'value' => $milestone,
                        'date' => $milestoneMatch->match->created_at->format('Y-m-d'),
                        'description' => "{$milestone}th professional match"
                    ];
                }
            }
        }
        
        return $milestones;
    }

    private function findPeakPerformance($allStats)
    {
        $bestPerformance = $allStats->sortByDesc('performance_rating')->first();
        
        if (!$bestPerformance) return null;
        
        return [
            'rating' => round($bestPerformance->performance_rating, 2),
            'acs' => round($bestPerformance->combat_score, 1),
            'kda' => round($bestPerformance->kda, 2),
            'hero' => $bestPerformance->hero,
            'date' => $bestPerformance->match->created_at->format('Y-m-d'),
            'opponent' => $bestPerformance->match->team1_id == $this->team_id 
                ? $bestPerformance->match->team2?->name 
                : $bestPerformance->match->team1?->name
        ];
    }

    private function calculateImprovementTrajectory($monthlyStats)
    {
        if ($monthlyStats->count() < 3) return 'insufficient_data';
        
        $ratings = $monthlyStats->pluck('avg_rating');
        $months = $ratings->keys();
        
        // Simple linear regression
        $n = $ratings->count();
        $sumX = $months->sum();
        $sumY = $ratings->sum();
        $sumXY = $months->zip($ratings)->sum(function($pair) {
            return $pair[0] * $pair[1];
        });
        $sumXX = $months->sum(function($x) { return $x * $x; });
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        
        if ($slope > 0.02) return 'strong_upward';
        if ($slope > 0.01) return 'upward';
        if ($slope < -0.02) return 'strong_downward';
        if ($slope < -0.01) return 'downward';
        return 'stable';
    }

    private function calculateCareerConsistency($monthlyStats)
    {
        if ($monthlyStats->count() < 3) return 0;
        
        $ratings = $monthlyStats->pluck('avg_rating');
        return $this->calculateConsistency($ratings);
    }

    private function calculateHeroMastery($hero, $playRate)
    {
        $matches = $hero->matches_played;
        $rating = $hero->avg_rating;
        
        if ($matches >= 50 && $rating >= 1.2 && $playRate >= 15) return 'expert';
        if ($matches >= 25 && $rating >= 1.0 && $playRate >= 10) return 'proficient';
        if ($matches >= 10 && $rating >= 0.8) return 'competent';
        if ($matches >= 5) return 'learning';
        return 'novice';
    }

    private function calculateHeroEffectiveness($hero)
    {
        return round(($hero->avg_rating * 40) + ($hero->matches_played * 0.5), 1);
    }

    private function calculateVersatilityScore($heroData)
    {
        $uniqueHeroes = $heroData->count();
        $playRateVariance = $this->calculateConsistency($heroData->pluck('play_rate'));
        
        return round(($uniqueHeroes * 5) + ($playRateVariance * 0.3), 1);
    }

    private function calculateRoleDistribution($heroData)
    {
        // This would require hero role data
        return [
            'Vanguard' => $heroData->where('hero', 'like', '%tank%')->count(),
            'Duelist' => $heroData->where('hero', 'like', '%dps%')->count(),
            'Strategist' => $heroData->where('hero', 'like', '%support%')->count()
        ];
    }

    private function getHeroImprovementRecommendations($heroData)
    {
        $recommendations = [];
        
        $lowPerformanceHeroes = $heroData->where('avg_rating', '<', 0.9)
                                         ->where('matches_played', '>=', 5);
        
        foreach ($lowPerformanceHeroes as $hero) {
            $recommendations[] = [
                'type' => 'improve_hero_performance',
                'hero' => $hero['hero'],
                'current_rating' => $hero['avg_rating'],
                'suggestion' => "Focus on improving {$hero['hero']} gameplay - current rating below average"
            ];
        }
        
        if ($heroData->count() < 5) {
            $recommendations[] = [
                'type' => 'expand_hero_pool',
                'suggestion' => 'Consider expanding hero pool for better team flexibility'
            ];
        }
        
        return $recommendations;
    }

    // Remove problematic accessor - handled in frontend

    /**
     * Get player stats by hero (VLR.gg style)
     */
    public function getStatsByHero()
    {
        return $this->matchStats()
            ->select('hero', 
                DB::raw('COUNT(*) as matches_played'),
                DB::raw('AVG(performance_rating) as avg_rating'),
                DB::raw('AVG(combat_score) as avg_acs'),
                DB::raw('AVG(kda) as avg_kd'),
                DB::raw('AVG(damage_per_round) as avg_adr'),
                DB::raw('AVG(kast_percentage) as avg_kast'),
                DB::raw('AVG(eliminations_per_round) as avg_kpr'),
                DB::raw('AVG(assists_per_round) as avg_apr'),
                DB::raw('SUM(eliminations) as total_kills'),
                DB::raw('SUM(deaths) as total_deaths'),
                DB::raw('SUM(assists) as total_assists')
            )
            ->groupBy('hero')
            ->orderBy('matches_played', 'desc')
            ->get();
    }

    /**
     * Update career averages (called after each match)
     */
    public function updateCareerStats()
    {
        $stats = $this->matchStats;
        
        if ($stats->isEmpty()) return;
        
        $this->update([
            'total_matches' => $stats->pluck('match_id')->unique()->count(),
            'total_maps_played' => $stats->count(),
            'total_wins' => $stats->whereHas('match', function($q) {
                $q->where(function($query) {
                    $query->where('winner_id', $this->team_id)
                          ->orWhere(function($q2) {
                              $q2->where('team1_id', $this->team_id)
                                 ->whereColumn('team1_score', '>', 'team2_score');
                          })
                          ->orWhere(function($q2) {
                              $q2->where('team2_id', $this->team_id)
                                 ->whereColumn('team2_score', '>', 'team1_score');
                          });
                });
            })->count(),
            'avg_rating' => round($stats->avg('performance_rating'), 2),
            'avg_combat_score' => round($stats->avg('combat_score'), 2),
            'avg_kda' => round($stats->avg('kda'), 2),
            'avg_damage_per_round' => round($stats->avg('damage_per_round'), 2),
            'avg_kast' => round($stats->avg('kast_percentage'), 2),
            'avg_kills_per_round' => round($stats->avg('eliminations_per_round'), 2),
            'avg_assists_per_round' => round($stats->avg('assists_per_round'), 2),
            'avg_first_kills_per_round' => round($stats->avg('first_kills') / max($stats->avg('map.total_rounds'), 1), 2),
            'avg_first_deaths_per_round' => round($stats->avg('first_deaths') / max($stats->avg('map.total_rounds'), 1), 2),
            'hero_pool' => $stats->pluck('hero')->unique()->values()->toArray()
        ]);
    }

    /**
     * Get win rate
     */
    public function getWinRateAttribute()
    {
        if ($this->total_matches == 0) return 0;
        return round(($this->total_wins / $this->total_matches) * 100, 1);
    }

    /**
     * Mention-related relationships and methods
     */

    /**
     * Get mentions of this player
     */
    public function mentions()
    {
        return $this->morphMany(Mention::class, 'mentioned', 'mentioned_type', 'mentioned_id');
    }

    /**
     * Get active mentions of this player with optimized query
     */
    public function activeMentions()
    {
        return $this->mentions()->where('is_active', true);
    }

    /**
     * Get recent mentions with pagination support
     */
    public function getRecentMentions($limit = 10, $offset = 0)
    {
        return Cache::remember(
            "player_mentions_{$this->id}_{$limit}_{$offset}",
            300, // 5 minutes cache
            function () use ($limit, $offset) {
                return $this->activeMentions()
                    ->with(['mentionable', 'mentionedBy'])
                    ->orderBy('mentioned_at', 'desc')
                    ->offset($offset)
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Get mention count efficiently
     */
    public function getMentionCount(): int
    {
        // Use the denormalized column if available
        if (isset($this->attributes['mention_count'])) {
            return (int) $this->attributes['mention_count'];
        }
        
        // Fallback to counting if column doesn't exist
        return $this->activeMentions()->count();
    }

    /**
     * Scope for players with mentions above threshold
     */
    public function scopePopularMentions($query, $threshold = 5)
    {
        if (\Schema::hasColumn('players', 'mention_count')) {
            return $query->where('mention_count', '>=', $threshold);
        }
        
        return $query->whereHas('mentions', function ($q) use ($threshold) {
            $q->where('is_active', true)
              ->havingRaw('COUNT(*) >= ?', [$threshold]);
        });
    }

    /**
     * Scope for recently mentioned players
     */
    public function scopeRecentlyMentioned($query, $days = 7)
    {
        if (\Schema::hasColumn('players', 'last_mentioned_at')) {
            return $query->where('last_mentioned_at', '>=', now()->subDays($days));
        }
        
        return $query->whereHas('mentions', function ($q) use ($days) {
            $q->where('is_active', true)
              ->where('mentioned_at', '>=', now()->subDays($days));
        });
    }

    /**
     * Get mention analytics data
     */
    public function getMentionAnalytics(): array
    {
        return Cache::remember(
            "player_mention_analytics_{$this->id}",
            3600, // 1 hour cache
            function () {
                $mentions = $this->activeMentions()
                    ->selectRaw('
                        COUNT(*) as total_mentions,
                        COUNT(DISTINCT mentionable_type) as content_types,
                        COUNT(DISTINCT mentioned_by) as unique_mentioners,
                        MAX(mentioned_at) as last_mention,
                        MIN(mentioned_at) as first_mention
                    ')
                    ->first();

                $contentBreakdown = $this->activeMentions()
                    ->selectRaw('mentionable_type, COUNT(*) as count')
                    ->groupBy('mentionable_type')
                    ->pluck('count', 'mentionable_type')
                    ->toArray();

                return [
                    'total_mentions' => $mentions->total_mentions ?? 0,
                    'content_types' => $mentions->content_types ?? 0,
                    'unique_mentioners' => $mentions->unique_mentioners ?? 0,
                    'last_mention' => $mentions->last_mention,
                    'first_mention' => $mentions->first_mention,
                    'content_breakdown' => $contentBreakdown
                ];
            }
        );
    }
}
