<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'short_name', 'slug', 'logo', 'region', 'platform', 'game', 'division',
        'country', 'flag', 'country_code', 'country_flag', 'rating', 'rank', 'win_rate', 
        'map_win_rate', 'recent_performance', 'longest_win_streak', 'current_streak_count',
        'current_streak_type', 'points', 'record', 'wins', 'losses', 'matches_played',
        'maps_won', 'maps_lost', 'tournaments_won', 'peak', 'streak', 'last_match', 
        'founded', 'founded_date', 'captain', 'coach', 'manager', 'coach_picture', 
        'coach_image', 'coach_name', 'coach_nationality', 'coach_social_media',
        'description', 'website', 'liquipedia_url', 'twitter', 'instagram',
        'youtube', 'twitch', 'tiktok', 'discord', 'facebook', 'social_media', 
        'social_links', 'achievements', 'recent_form', 'player_count', 'status',
        'earnings', 'owner', 'elo_rating', 'peak_elo', 'elo_changes', 'last_elo_update',
        'ranking'
    ];

    protected $casts = [
        'rating' => 'integer',
        'rank' => 'integer',
        'win_rate' => 'float',
        'points' => 'integer',
        'peak' => 'integer',
        'player_count' => 'integer',
        'social_media' => 'array',
        'coach_social_media' => 'array',
        'achievements' => 'array',
        'recent_form' => 'array'
    ];

    protected $appends = []; // Removed problematic accessors

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function homeMatches()
    {
        return $this->hasMany(GameMatch::class, 'team1_id');
    }

    public function awayMatches()
    {
        return $this->hasMany(GameMatch::class, 'team2_id');
    }

    /**
     * Get all matches (both home and away)
     */
    public function allMatches()
    {
        return GameMatch::where(function($query) {
            $query->where('team1_id', $this->id)
                  ->orWhere('team2_id', $this->id);
        });
    }

    /**
     * Get head-to-head record against another team
     */
    public function getHeadToHeadRecord($opponentTeam, $startDate = null)
    {
        $query = GameMatch::where(function($q) use ($opponentTeam) {
                $q->where(function($subQ) use ($opponentTeam) {
                    $subQ->where('team1_id', $this->id)
                         ->where('team2_id', $opponentTeam->id);
                })->orWhere(function($subQ) use ($opponentTeam) {
                    $subQ->where('team1_id', $opponentTeam->id)
                         ->where('team2_id', $this->id);
                });
            })
            ->where('status', 'completed');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $matches = $query->get();
        
        if ($matches->isEmpty()) {
            return [
                'total_matches' => 0,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'win_rate' => 0,
                'maps_won' => 0,
                'maps_lost' => 0,
                'map_win_rate' => 0,
                'dominance' => 'unknown',
                'last_meeting' => null,
                'current_streak' => null
            ];
        }

        $wins = $matches->filter(function($match) use ($opponentTeam) {
            return ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);
        })->count();

        $draws = $matches->filter(function($match) {
            return $match->team1_score == $match->team2_score;
        })->count();

        $losses = $matches->count() - $wins - $draws;

        $mapsWon = $matches->sum(function($match) {
            return $match->team1_id == $this->id ? $match->team1_score : $match->team2_score;
        });

        $mapsLost = $matches->sum(function($match) {
            return $match->team1_id == $this->id ? $match->team2_score : $match->team1_score;
        });

        $winRate = $matches->count() > 0 ? ($wins / $matches->count()) * 100 : 0;
        $mapWinRate = ($mapsWon + $mapsLost) > 0 ? ($mapsWon / ($mapsWon + $mapsLost)) * 100 : 0;

        return [
            'total_matches' => $matches->count(),
            'wins' => $wins,
            'losses' => $losses,
            'draws' => $draws,
            'win_rate' => round($winRate, 1),
            'maps_won' => $mapsWon,
            'maps_lost' => $mapsLost,
            'map_win_rate' => round($mapWinRate, 1),
            'dominance' => $this->calculateDominance($winRate),
            'last_meeting' => $matches->sortByDesc('created_at')->first(),
            'current_streak' => $this->calculateHeadToHeadStreak($matches, $opponentTeam)
        ];
    }

    /**
     * Get recent form (W/L/D pattern)
     */
    public function getRecentForm($limit = 10, $startDate = null)
    {
        $query = $this->allMatches()
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $matches = $query->get();

        $form = $matches->map(function($match) {
            if ($match->team1_score > $match->team2_score) {
                return $match->team1_id == $this->id ? 'W' : 'L';
            } elseif ($match->team2_score > $match->team1_score) {
                return $match->team2_id == $this->id ? 'W' : 'L';
            }
            return 'D';
        })->values();

        return [
            'form_string' => $form->implode(''),
            'form_array' => $form->toArray(),
            'wins' => $form->filter(fn($result) => $result === 'W')->count(),
            'losses' => $form->filter(fn($result) => $result === 'L')->count(),
            'draws' => $form->filter(fn($result) => $result === 'D')->count(),
            'points' => $form->sum(fn($result) => $result === 'W' ? 3 : ($result === 'D' ? 1 : 0)),
            'form_rating' => $this->calculateFormRating($form)
        ];
    }

    /**
     * Get performance trends over time
     */
    public function getPerformanceTrends($days = 30)
    {
        $startDate = now()->subDays($days);
        
        $matches = $this->allMatches()
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->get();

        if ($matches->isEmpty()) {
            return [
                'trend_direction' => 'stable',
                'rating_change' => 0,
                'form_change' => 0,
                'momentum' => 'neutral',
                'consistency' => 0
            ];
        }

        $dailyPerformance = $matches->groupBy(function($match) {
                return $match->created_at->format('Y-m-d');
            })
            ->map(function($dayMatches) {
                $wins = $dayMatches->filter(function($match) {
                    return ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                           ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);
                })->count();

                $winRate = $dayMatches->count() > 0 ? ($wins / $dayMatches->count()) * 100 : 0;

                return [
                    'date' => $dayMatches->first()->created_at->format('Y-m-d'),
                    'matches' => $dayMatches->count(),
                    'wins' => $wins,
                    'win_rate' => $winRate
                ];
            });

        return [
            'daily_performance' => $dailyPerformance->values(),
            'trend_direction' => $this->calculateTrendDirection($dailyPerformance),
            'momentum' => $this->calculateMomentum($matches),
            'consistency' => $this->calculateConsistency($dailyPerformance)
        ];
    }

    /**
     * Get match statistics aggregated
     */
    public function getMatchStatistics($startDate = null)
    {
        $query = $this->allMatches()
            ->where('status', 'completed');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            return $this->getEmptyMatchStats();
        }

        $wins = $matches->filter(function($match) {
            return ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);
        });

        $totalMapsPlayed = $matches->sum(function($match) {
            return $match->team1_score + $match->team2_score;
        });

        $mapsWon = $matches->sum(function($match) {
            return $match->team1_id == $this->id ? $match->team1_score : $match->team2_score;
        });

        return [
            'matches_played' => $matches->count(),
            'wins' => $wins->count(),
            'losses' => $matches->count() - $wins->count(),
            'win_rate' => round(($wins->count() / $matches->count()) * 100, 1),
            'maps_played' => $totalMapsPlayed,
            'maps_won' => $mapsWon,
            'maps_lost' => $totalMapsPlayed - $mapsWon,
            'map_win_rate' => $totalMapsPlayed > 0 ? round(($mapsWon / $totalMapsPlayed) * 100, 1) : 0,
            'average_match_duration' => $this->getAverageMatchDuration($matches),
            'longest_win_streak' => $this->calculateLongestStreak($matches, 'win'),
            'longest_loss_streak' => $this->calculateLongestStreak($matches, 'loss'),
            'current_streak' => $this->calculateCurrentStreak($matches)
        ];
    }

    /**
     * Get performance by tournament tier
     */
    public function getPerformanceByTier($startDate = null)
    {
        $query = $this->allMatches()
            ->whereNotNull('event_id')
            ->with('event')
            ->where('status', 'completed');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $matches = $query->get();

        return $matches->groupBy(function($match) {
                return $match->event?->tier ?? 'Unknown';
            })
            ->map(function($tierMatches) {
                $wins = $tierMatches->filter(function($match) {
                    return ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                           ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);
                })->count();

                return [
                    'matches_played' => $tierMatches->count(),
                    'wins' => $wins,
                    'losses' => $tierMatches->count() - $wins,
                    'win_rate' => $tierMatches->count() > 0 ? round(($wins / $tierMatches->count()) * 100, 1) : 0,
                    'avg_prize_pool' => $tierMatches->avg(function($match) {
                        return $match->event?->prize_pool ?? 0;
                    })
                ];
            });
    }

    /**
     * Get regional performance comparison
     */
    public function getRegionalPerformance($startDate = null)
    {
        $query = $this->allMatches()
            ->with(['team1', 'team2'])
            ->where('status', 'completed');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $matches = $query->get();

        return $matches->groupBy(function($match) {
                $opponent = $match->team1_id == $this->id ? $match->team2 : $match->team1;
                return $opponent?->region ?? 'Unknown';
            })
            ->map(function($regionMatches) {
                $wins = $regionMatches->filter(function($match) {
                    return ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                           ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);
                })->count();

                return [
                    'matches_played' => $regionMatches->count(),
                    'wins' => $wins,
                    'losses' => $regionMatches->count() - $wins,
                    'win_rate' => $regionMatches->count() > 0 ? round(($wins / $regionMatches->count()) * 100, 1) : 0
                ];
            });
    }

    /**
     * Get team's strongest and weakest matchups
     */
    public function getBestAndWorstMatchups($limit = 5, $minMatches = 3)
    {
        $opponents = GameMatch::where(function($query) {
                $query->where('team1_id', $this->id)
                      ->orWhere('team2_id', $this->id);
            })
            ->where('status', 'completed')
            ->with(['team1', 'team2'])
            ->get()
            ->groupBy(function($match) {
                return $match->team1_id == $this->id ? $match->team2_id : $match->team1_id;
            })
            ->filter(function($matches) use ($minMatches) {
                return $matches->count() >= $minMatches;
            })
            ->map(function($matches) {
                $opponent = $matches->first()->team1_id == $this->id 
                    ? $matches->first()->team2 
                    : $matches->first()->team1;

                $wins = $matches->filter(function($match) {
                    return ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                           ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);
                })->count();

                $winRate = ($wins / $matches->count()) * 100;

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
                    'win_rate' => round($winRate, 1)
                ];
            });

        $strongest = $opponents->sortByDesc('win_rate')->take($limit)->values();
        $weakest = $opponents->sortBy('win_rate')->take($limit)->values();

        return [
            'strongest_matchups' => $strongest,
            'weakest_matchups' => $weakest
        ];
    }

    // Helper methods

    private function calculateDominance($winRate)
    {
        if ($winRate >= 80) return 'dominant';
        if ($winRate >= 60) return 'favored';
        if ($winRate >= 40) return 'competitive';
        if ($winRate >= 20) return 'struggling';
        return 'dominated';
    }

    private function calculateHeadToHeadStreak($matches, $opponentTeam)
    {
        if ($matches->isEmpty()) return null;

        $sortedMatches = $matches->sortByDesc('created_at');
        $streak = 0;
        $type = null;

        foreach ($sortedMatches as $match) {
            $won = ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);

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
            'count' => $streak,
            'since' => $sortedMatches->first()->created_at
        ];
    }

    private function calculateFormRating($form)
    {
        if ($form->isEmpty()) return 50;

        $points = $form->sum(fn($result) => $result === 'W' ? 3 : ($result === 'D' ? 1 : 0));
        $maxPoints = $form->count() * 3;

        return round(($points / $maxPoints) * 100, 1);
    }

    private function calculateTrendDirection($dailyPerformance)
    {
        if ($dailyPerformance->count() < 3) return 'stable';

        $values = $dailyPerformance->pluck('win_rate');
        $recent = $values->slice(-3)->avg();
        $earlier = $values->slice(0, 3)->avg();

        $difference = $recent - $earlier;

        if ($difference > 10) return 'improving';
        if ($difference < -10) return 'declining';
        return 'stable';
    }

    private function calculateMomentum($matches)
    {
        if ($matches->count() < 3) return 'neutral';

        $recentMatches = $matches->sortByDesc('created_at')->take(3);
        $wins = $recentMatches->filter(function($match) {
            return ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);
        })->count();

        if ($wins >= 2) return 'positive';
        if ($wins <= 1) return 'negative';
        return 'neutral';
    }

    private function calculateConsistency($dailyPerformance)
    {
        if ($dailyPerformance->count() < 2) return 0;

        $winRates = $dailyPerformance->pluck('win_rate');
        $mean = $winRates->avg();
        $variance = $winRates->map(function($rate) use ($mean) {
            return pow($rate - $mean, 2);
        })->avg();

        $standardDeviation = sqrt($variance);
        
        // Convert to consistency score (lower deviation = higher consistency)
        return max(0, round(100 - ($standardDeviation * 2), 1));
    }

    private function getAverageMatchDuration($matches)
    {
        $durations = $matches->filter(function($match) {
            return $match->started_at && $match->completed_at;
        })->map(function($match) {
            return $match->started_at->diffInMinutes($match->completed_at);
        });

        if ($durations->isEmpty()) return 0;

        return round($durations->avg(), 1);
    }

    private function calculateLongestStreak($matches, $type)
    {
        if ($matches->isEmpty()) return 0;

        $sortedMatches = $matches->sortBy('created_at');
        $longestStreak = 0;
        $currentStreak = 0;

        foreach ($sortedMatches as $match) {
            $won = ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);

            $isTarget = ($type === 'win' && $won) || ($type === 'loss' && !$won);

            if ($isTarget) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return $longestStreak;
    }

    private function calculateCurrentStreak($matches)
    {
        if ($matches->isEmpty()) return null;

        $sortedMatches = $matches->sortByDesc('created_at');
        $streak = 0;
        $type = null;

        foreach ($sortedMatches as $match) {
            $won = ($match->team1_id == $this->id && $match->team1_score > $match->team2_score) ||
                   ($match->team2_id == $this->id && $match->team2_score > $match->team1_score);

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
            'count' => $streak,
            'since' => $sortedMatches->first()->created_at
        ];
    }

    private function getEmptyMatchStats()
    {
        return [
            'matches_played' => 0,
            'wins' => 0,
            'losses' => 0,
            'win_rate' => 0,
            'maps_played' => 0,
            'maps_won' => 0,
            'maps_lost' => 0,
            'map_win_rate' => 0,
            'average_match_duration' => 0,
            'longest_win_streak' => 0,
            'longest_loss_streak' => 0,
            'current_streak' => null
        ];
    }
}
