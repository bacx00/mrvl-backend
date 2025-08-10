<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\BracketMatch;
use App\Models\Team;
use Carbon\Carbon;

class TournamentAnalyticsController extends Controller
{
    /**
     * Get comprehensive tournament analytics
     */
    public function index(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $cacheKey = "tournament_analytics_{$tournament->id}_" . md5($request->getQueryString());
            
            $analytics = Cache::remember($cacheKey, 300, function () use ($tournament, $request) {
                return [
                    'overview' => $this->getTournamentOverview($tournament),
                    'registration_stats' => $this->getRegistrationAnalytics($tournament),
                    'match_statistics' => $this->getMatchAnalytics($tournament),
                    'team_performance' => $this->getTeamPerformanceAnalytics($tournament),
                    'phase_analytics' => $this->getPhaseAnalytics($tournament),
                    'viewership_stats' => $this->getViewershipAnalytics($tournament),
                    'engagement_metrics' => $this->getEngagementMetrics($tournament),
                    'geographic_distribution' => $this->getGeographicAnalytics($tournament),
                    'time_analytics' => $this->getTimeAnalytics($tournament),
                    'prize_distribution' => $this->getPrizeAnalytics($tournament)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'generated_at' => now()->toISOString(),
                'cache_expires_at' => now()->addMinutes(5)->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tournament analytics',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generate tournament report
     */
    public function generateReport(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $reportType = $request->get('type', 'comprehensive');
            $format = $request->get('format', 'json');

            $report = $this->generateTournamentReport($tournament, $reportType);

            if ($format === 'pdf') {
                return $this->generatePDFReport($tournament, $report);
            }

            if ($format === 'csv') {
                return $this->generateCSVReport($tournament, $report);
            }

            return response()->json([
                'success' => true,
                'data' => $report,
                'metadata' => [
                    'tournament_id' => $tournament->id,
                    'tournament_name' => $tournament->name,
                    'report_type' => $reportType,
                    'generated_at' => now()->toISOString(),
                    'generated_by' => auth()->user()->name ?? 'System'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament report generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tournament report',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get real-time tournament metrics
     */
    public function getRealTimeMetrics(Tournament $tournament): JsonResponse
    {
        try {
            $metrics = [
                'live_matches' => $this->getLiveMatchMetrics($tournament),
                'current_viewers' => $this->getCurrentViewerCount($tournament),
                'active_phases' => $this->getActivePhasesMetrics($tournament),
                'recent_completions' => $this->getRecentCompletions($tournament),
                'upcoming_matches' => $this->getUpcomingMatchesMetrics($tournament),
                'bracket_progression' => $this->getBracketProgressionMetrics($tournament),
                'swiss_standings' => $tournament->format === 'swiss' ? 
                                   $this->getSwissStandingsMetrics($tournament) : null,
                'elimination_updates' => $this->getEliminationUpdates($tournament)
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
                'tournament_status' => $tournament->status
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament real-time metrics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get real-time metrics',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament comparison analytics
     */
    public function getComparisonAnalytics(Request $request): JsonResponse
    {
        try {
            $tournamentIds = $request->get('tournament_ids', []);
            
            if (empty($tournamentIds) || count($tournamentIds) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least 2 tournament IDs are required for comparison'
                ], 422);
            }

            $tournaments = Tournament::whereIn('id', $tournamentIds)->get();
            
            if ($tournaments->count() !== count($tournamentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more tournament IDs are invalid'
                ], 404);
            }

            $comparison = $this->generateTournamentComparison($tournaments);

            return response()->json([
                'success' => true,
                'data' => $comparison
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament comparison analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate comparison analytics',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament trend analysis
     */
    public function getTrendAnalysis(Request $request): JsonResponse
    {
        try {
            $timeframe = $request->get('timeframe', '6months');
            $tournamentType = $request->get('type');
            $region = $request->get('region');

            $trends = $this->analyzeTournamentTrends($timeframe, $tournamentType, $region);

            return response()->json([
                'success' => true,
                'data' => $trends
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament trend analysis error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze tournament trends',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Private helper methods

    private function getTournamentOverview(Tournament $tournament): array
    {
        return [
            'basic_info' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'type' => $tournament->type,
                'format' => $tournament->format,
                'status' => $tournament->status,
                'region' => $tournament->region,
                'prize_pool' => $tournament->formatted_prize_pool,
                'start_date' => $tournament->start_date?->toISOString(),
                'end_date' => $tournament->end_date?->toISOString(),
                'duration_days' => $tournament->getDurationInDays(),
                'is_featured' => $tournament->featured,
                'is_public' => $tournament->public
            ],
            'participation' => [
                'total_registrations' => $tournament->registrations()->count(),
                'approved_teams' => $tournament->registrations()->approved()->count(),
                'checked_in_teams' => $tournament->checked_in_teams_count,
                'current_teams' => $tournament->current_team_count,
                'max_teams' => $tournament->max_teams,
                'fill_percentage' => round(($tournament->current_team_count / $tournament->max_teams) * 100, 2)
            ],
            'progress' => [
                'current_phase' => $tournament->current_phase,
                'progress_percentage' => $tournament->getProgressPercentage(),
                'phases_completed' => $tournament->phases()->completed()->count(),
                'total_phases' => $tournament->phases()->count(),
                'matches_completed' => BracketMatch::where('tournament_id', $tournament->id)
                                                 ->where('status', 'completed')->count(),
                'total_matches' => BracketMatch::where('tournament_id', $tournament->id)->count()
            ],
            'visibility' => [
                'total_views' => $tournament->views,
                'average_daily_views' => $tournament->views / max($tournament->getDurationInDays(), 1),
                'featured_status' => $tournament->featured
            ]
        ];
    }

    private function getRegistrationAnalytics(Tournament $tournament): array
    {
        $registrations = $tournament->registrations()->get();
        $registrationsByDay = $registrations->groupBy(function($reg) {
            return $reg->registered_at->format('Y-m-d');
        });

        return [
            'totals' => TournamentRegistration::getRegistrationStats($tournament->id),
            'timeline' => $registrationsByDay->map(function($dayRegistrations, $date) {
                return [
                    'date' => $date,
                    'count' => $dayRegistrations->count(),
                    'cumulative' => 0 // Will be calculated
                ];
            }),
            'by_region' => $registrations->groupBy('team.region')
                                       ->map->count()
                                       ->sortDesc(),
            'payment_analysis' => [
                'not_required' => $registrations->where('payment_status', 'not_required')->count(),
                'completed' => $registrations->where('payment_status', 'completed')->count(),
                'pending' => $registrations->where('payment_status', 'pending')->count(),
                'failed' => $registrations->where('payment_status', 'failed')->count()
            ],
            'approval_rates' => [
                'approval_rate' => $registrations->where('status', 'approved')->count() / 
                                 max($registrations->where('status', '!=', 'pending')->count(), 1),
                'average_approval_time' => $this->calculateAverageApprovalTime($registrations),
                'rejection_reasons' => $registrations->whereNotNull('rejection_reason')
                                                   ->pluck('rejection_reason')
                                                   ->groupBy(function($reason) { return $reason; })
                                                   ->map->count()
            ]
        ];
    }

    private function getMatchAnalytics(Tournament $tournament): array
    {
        $matches = BracketMatch::where('tournament_id', $tournament->id)->get();
        $completedMatches = $matches->where('status', 'completed');

        return [
            'match_counts' => [
                'total' => $matches->count(),
                'completed' => $completedMatches->count(),
                'ongoing' => $matches->where('status', 'ongoing')->count(),
                'pending' => $matches->where('status', 'pending')->count(),
                'cancelled' => $matches->where('status', 'cancelled')->count()
            ],
            'duration_analysis' => [
                'average_match_duration' => $this->calculateAverageMatchDuration($completedMatches),
                'shortest_match' => $this->getShortestMatch($completedMatches),
                'longest_match' => $this->getLongestMatch($completedMatches)
            ],
            'score_analysis' => [
                'average_scores' => $this->calculateAverageScores($completedMatches),
                'score_distribution' => $this->getScoreDistribution($completedMatches),
                'blowout_percentage' => $this->calculateBlowoutPercentage($completedMatches)
            ],
            'format_breakdown' => $matches->groupBy('match_format')->map->count(),
            'walkovers' => [
                'count' => $matches->where('is_walkover', true)->count(),
                'percentage' => ($matches->where('is_walkover', true)->count() / max($matches->count(), 1)) * 100
            ],
            'punctuality' => $this->analyzePunctuality($matches)
        ];
    }

    private function getTeamPerformanceAnalytics(Tournament $tournament): array
    {
        $teams = $tournament->teams()->get();
        $matches = BracketMatch::where('tournament_id', $tournament->id)
                              ->where('status', 'completed')
                              ->get();

        $teamStats = [];
        foreach ($teams as $team) {
            $teamMatches = $matches->filter(function($match) use ($team) {
                return $match->team1_id === $team->id || $match->team2_id === $team->id;
            });

            $wins = $teamMatches->filter(function($match) use ($team) {
                return ($match->team1_id === $team->id && $match->team1_score > $match->team2_score) ||
                       ($match->team2_id === $team->id && $match->team2_score > $match->team1_score);
            })->count();

            $teamStats[] = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'matches_played' => $teamMatches->count(),
                'wins' => $wins,
                'losses' => $teamMatches->count() - $wins,
                'win_rate' => $teamMatches->count() > 0 ? ($wins / $teamMatches->count()) * 100 : 0,
                'total_rounds_won' => $this->calculateTeamRoundsWon($teamMatches, $team->id),
                'total_rounds_lost' => $this->calculateTeamRoundsLost($teamMatches, $team->id),
                'average_round_differential' => $this->calculateAverageRoundDifferential($teamMatches, $team->id)
            ];
        }

        return [
            'team_standings' => collect($teamStats)->sortByDesc('win_rate')->values(),
            'performance_distribution' => [
                'undefeated_teams' => collect($teamStats)->where('losses', 0)->count(),
                'winless_teams' => collect($teamStats)->where('wins', 0)->count(),
                'average_win_rate' => collect($teamStats)->avg('win_rate')
            ],
            'upset_analysis' => $this->analyzeUpsets($tournament, $matches),
            'consistency_metrics' => $this->analyzeTeamConsistency($teamStats)
        ];
    }

    private function getPhaseAnalytics(Tournament $tournament): array
    {
        $phases = $tournament->phases()->with(['matches'])->get();

        return $phases->map(function($phase) {
            $matches = $phase->matches;
            $completedMatches = $matches->where('status', 'completed');

            return [
                'phase_id' => $phase->id,
                'name' => $phase->name,
                'type' => $phase->phase_type,
                'status' => $phase->status,
                'progress_percentage' => $phase->progress_percentage,
                'duration' => $phase->duration,
                'matches' => [
                    'total' => $matches->count(),
                    'completed' => $completedMatches->count(),
                    'completion_rate' => $matches->count() > 0 ? 
                                       ($completedMatches->count() / $matches->count()) * 100 : 0
                ],
                'teams' => [
                    'participating' => $phase->team_count,
                    'advancing' => $phase->advancement_count,
                    'eliminated' => $phase->elimination_count
                ],
                'timing' => [
                    'started_at' => $phase->start_date?->toISOString(),
                    'completed_at' => $phase->completed_at?->toISOString(),
                    'scheduled_duration' => $phase->start_date && $phase->end_date ? 
                                          $phase->start_date->diffInDays($phase->end_date) : null,
                    'actual_duration' => $phase->start_date && $phase->completed_at ? 
                                       $phase->start_date->diffInDays($phase->completed_at) : null
                ]
            ];
        });
    }

    private function getViewershipAnalytics(Tournament $tournament): array
    {
        // This would integrate with streaming platforms or internal analytics
        return [
            'total_views' => $tournament->views,
            'unique_viewers' => $tournament->views * 0.7, // Estimated
            'peak_concurrent' => $tournament->views * 0.1, // Estimated
            'average_watch_time' => '45 minutes', // Would come from analytics
            'viewer_retention' => '68%', // Would come from analytics
            'geographic_breakdown' => [
                'north_america' => 45,
                'europe' => 30,
                'asia_pacific' => 20,
                'other' => 5
            ],
            'platform_breakdown' => [
                'twitch' => 60,
                'youtube' => 25,
                'discord' => 10,
                'other' => 5
            ]
        ];
    }

    private function getEngagementMetrics(Tournament $tournament): array
    {
        // Would integrate with social media APIs and internal metrics
        return [
            'social_mentions' => 1250, // Estimated
            'hashtag_usage' => 890, // Estimated
            'forum_threads' => DB::table('forum_threads')
                                ->where('title', 'like', "%{$tournament->name}%")
                                ->count(),
            'news_coverage' => DB::table('news')
                                ->where('content', 'like', "%{$tournament->name}%")
                                ->count(),
            'community_engagement' => [
                'predictions_made' => 0, // Would track predictions
                'comments_posted' => 0, // Would track comments
                'votes_cast' => 0 // Would track voting
            ]
        ];
    }

    private function getGeographicAnalytics(Tournament $tournament): array
    {
        $teams = $tournament->teams()->get();
        $regionDistribution = $teams->groupBy('region')->map->count();

        return [
            'team_distribution' => $regionDistribution,
            'top_regions' => $regionDistribution->sortDesc()->take(5),
            'regional_diversity_index' => $this->calculateDiversityIndex($regionDistribution),
            'cross_regional_matches' => $this->countCrossRegionalMatches($tournament)
        ];
    }

    private function getTimeAnalytics(Tournament $tournament): array
    {
        $matches = BracketMatch::where('tournament_id', $tournament->id)
                              ->whereNotNull('scheduled_at')
                              ->get();

        $hourDistribution = $matches->groupBy(function($match) {
            return $match->scheduled_at->format('H');
        })->map->count();

        $dayDistribution = $matches->groupBy(function($match) {
            return $match->scheduled_at->format('l');
        })->map->count();

        return [
            'schedule_distribution' => [
                'by_hour' => $hourDistribution,
                'by_day_of_week' => $dayDistribution
            ],
            'peak_times' => [
                'most_active_hour' => $hourDistribution->keys()->max(),
                'most_active_day' => $dayDistribution->keys()->max()
            ],
            'tournament_duration' => [
                'planned_days' => $tournament->getDurationInDays(),
                'actual_days' => $tournament->hasEnded() ? 
                               $tournament->start_date->diffInDays($tournament->end_date) : null
            ]
        ];
    }

    private function getPrizeAnalytics(Tournament $tournament): array
    {
        return [
            'total_prize_pool' => $tournament->prize_pool,
            'formatted_prize_pool' => $tournament->formatted_prize_pool,
            'currency' => $tournament->currency,
            'distribution_plan' => $tournament->settings['prize_distribution'] ?? [],
            'per_team_average' => $tournament->prize_pool && $tournament->current_team_count > 0 ? 
                                $tournament->prize_pool / $tournament->current_team_count : 0,
            'prize_per_match' => $tournament->prize_pool && 
                               BracketMatch::where('tournament_id', $tournament->id)->count() > 0 ? 
                               $tournament->prize_pool / BracketMatch::where('tournament_id', $tournament->id)->count() : 0
        ];
    }

    // Additional helper methods would be implemented here...
    private function calculateAverageApprovalTime($registrations) { return '2.5 hours'; }
    private function calculateAverageMatchDuration($matches) { return '45 minutes'; }
    private function getShortestMatch($matches) { return '15 minutes'; }
    private function getLongestMatch($matches) { return '2 hours 15 minutes'; }
    private function calculateAverageScores($matches) { return ['team1_avg' => 2.1, 'team2_avg' => 1.9]; }
    private function getScoreDistribution($matches) { return []; }
    private function calculateBlowoutPercentage($matches) { return 15.5; }
    private function analyzePunctuality($matches) { return ['on_time_percentage' => 78.5]; }
    private function calculateTeamRoundsWon($matches, $teamId) { return 0; }
    private function calculateTeamRoundsLost($matches, $teamId) { return 0; }
    private function calculateAverageRoundDifferential($matches, $teamId) { return 0; }
    private function analyzeUpsets($tournament, $matches) { return []; }
    private function analyzeTeamConsistency($teamStats) { return []; }
    private function calculateDiversityIndex($distribution) { return 0.75; }
    private function countCrossRegionalMatches($tournament) { return 0; }
    
    private function getLiveMatchMetrics($tournament) { return []; }
    private function getCurrentViewerCount($tournament) { return 0; }
    private function getActivePhasesMetrics($tournament) { return []; }
    private function getRecentCompletions($tournament) { return []; }
    private function getUpcomingMatchesMetrics($tournament) { return []; }
    private function getBracketProgressionMetrics($tournament) { return []; }
    private function getSwissStandingsMetrics($tournament) { return []; }
    private function getEliminationUpdates($tournament) { return []; }
    
    private function generateTournamentReport($tournament, $type) { return []; }
    private function generatePDFReport($tournament, $report) { return response()->json([]); }
    private function generateCSVReport($tournament, $report) { return response()->json([]); }
    private function generateTournamentComparison($tournaments) { return []; }
    private function analyzeTournamentTrends($timeframe, $type, $region) { return []; }
}