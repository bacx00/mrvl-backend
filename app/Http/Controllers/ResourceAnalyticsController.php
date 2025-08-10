<?php

namespace App\Http\Controllers;

use App\Models\{Team, Player, GameMatch, Event, News, ForumThread, User, UserActivity};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Cache, Log};
use Carbon\Carbon;

class ResourceAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Get team-specific analytics
     */
    public function team($teamId, Request $request)
    {
        try {
            $team = Team::with(['players', 'matches'])->findOrFail($teamId);
            $period = $request->get('period', '30d');
            $days = $this->getPeriodDays($period);
            $startDate = now()->subDays($days);

            $analytics = [
                'team_id' => $teamId,
                'team_name' => $team->name,
                'period' => $period,
                'overview' => [
                    'total_matches' => $team->matches()->count(),
                    'wins' => $team->wins ?? 0,
                    'losses' => $team->losses ?? 0,
                    'win_rate' => $this->calculateWinRate($team),
                    'current_rating' => $team->rating ?? 1500,
                    'rank_position' => $this->getTeamRank($team),
                    'total_earnings' => $team->total_earnings ?? 0
                ],
                'performance_metrics' => [
                    'recent_form' => $this->getRecentForm($team, $startDate),
                    'match_history' => $this->getMatchHistory($team, $startDate),
                    'performance_trends' => $this->getPerformanceTrends($team, $startDate),
                    'opponent_analysis' => $this->getOpponentAnalysis($team, $startDate)
                ],
                'player_analytics' => [
                    'roster_stats' => $this->getRosterStats($team),
                    'player_performance' => $this->getPlayerPerformance($team, $startDate),
                    'role_distribution' => $this->getRoleDistribution($team)
                ],
                'engagement_metrics' => [
                    'profile_views' => $this->getProfileViews($teamId, 'team', $startDate),
                    'fan_engagement' => $this->getFanEngagement($team, $startDate),
                    'social_mentions' => $this->getSocialMentions($team, $startDate),
                    'media_coverage' => $this->getMediaCoverage($team, $startDate)
                ],
                'competitive_insights' => [
                    'tournament_history' => $this->getTournamentHistory($team, $startDate),
                    'prize_earnings' => $this->getPrizeEarnings($team, $startDate),
                    'achievement_timeline' => $this->getAchievementTimeline($team)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error("Team analytics error for team {$teamId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get player-specific analytics
     */
    public function player($playerId, Request $request)
    {
        try {
            $player = Player::with(['team', 'matches', 'stats'])->findOrFail($playerId);
            $period = $request->get('period', '30d');
            $days = $this->getPeriodDays($period);
            $startDate = now()->subDays($days);

            $analytics = [
                'player_id' => $playerId,
                'player_name' => $player->name,
                'team' => $player->team,
                'period' => $period,
                'overview' => [
                    'current_rating' => $player->rating ?? 1500,
                    'rank_position' => $this->getPlayerRank($player),
                    'role' => $player->role,
                    'total_matches' => $this->getPlayerMatchCount($player),
                    'total_earnings' => $player->total_earnings ?? 0,
                    'career_kda' => $this->getCareerKDA($player)
                ],
                'performance_metrics' => [
                    'recent_stats' => $this->getRecentPlayerStats($player, $startDate),
                    'hero_performance' => $this->getHeroPerformance($player, $startDate),
                    'match_performance' => $this->getMatchPerformance($player, $startDate),
                    'consistency_rating' => $this->getConsistencyRating($player, $startDate)
                ],
                'competitive_analytics' => [
                    'tournament_performance' => $this->getTournamentPerformance($player, $startDate),
                    'team_contribution' => $this->getTeamContribution($player, $startDate),
                    'clutch_performance' => $this->getClutchPerformance($player, $startDate)
                ],
                'engagement_metrics' => [
                    'profile_views' => $this->getProfileViews($playerId, 'player', $startDate),
                    'fan_following' => $this->getFanFollowing($player, $startDate),
                    'social_presence' => $this->getSocialPresence($player, $startDate)
                ],
                'career_progression' => [
                    'rating_history' => $this->getRatingHistory($player),
                    'team_history' => $this->getPlayerTeamHistory($player),
                    'achievement_timeline' => $this->getPlayerAchievements($player)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error("Player analytics error for player {$playerId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match-specific analytics
     */
    public function match($matchId, Request $request)
    {
        try {
            $match = GameMatch::with([
                'team1', 'team2', 'event', 
                'maps', 'playerStats', 'matchEvents'
            ])->findOrFail($matchId);

            $analytics = [
                'match_id' => $matchId,
                'teams' => [
                    'team1' => $match->team1,
                    'team2' => $match->team2
                ],
                'event' => $match->event,
                'match_overview' => [
                    'status' => $match->status,
                    'score' => [
                        'team1' => $match->team1_score,
                        'team2' => $match->team2_score
                    ],
                    'duration' => $this->calculateMatchDuration($match),
                    'viewers' => $match->viewers ?? 0,
                    'peak_viewers' => $match->peak_viewers ?? $match->viewers,
                    'format' => $match->format ?? 'Best of 3'
                ],
                'viewership_analytics' => [
                    'total_viewers' => $match->viewers ?? 0,
                    'peak_concurrent' => $match->peak_viewers ?? 0,
                    'average_viewers' => $match->avg_viewers ?? 0,
                    'viewer_retention' => $this->getViewerRetention($match),
                    'viewer_timeline' => $this->getViewerTimeline($match),
                    'demographic_breakdown' => $this->getViewerDemographics($match)
                ],
                'performance_analytics' => [
                    'player_stats' => $this->getMatchPlayerStats($match),
                    'team_stats' => $this->getMatchTeamStats($match),
                    'hero_picks' => $this->getMatchHeroPicks($match),
                    'map_performance' => $this->getMatchMapPerformance($match)
                ],
                'engagement_metrics' => [
                    'chat_activity' => $this->getChatActivity($match),
                    'social_buzz' => $this->getSocialBuzz($match),
                    'forum_discussions' => $this->getForumDiscussions($match),
                    'highlight_moments' => $this->getHighlightMoments($match)
                ],
                'competitive_context' => [
                    'tournament_stage' => $this->getTournamentStage($match),
                    'stakes' => $this->getMatchStakes($match),
                    'historical_context' => $this->getHistoricalContext($match)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error("Match analytics error for match {$matchId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching match analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get event/tournament-specific analytics
     */
    public function event($eventId, Request $request)
    {
        try {
            $event = Event::with(['matches', 'teams', 'brackets'])->findOrFail($eventId);
            $period = $request->get('period', 'all');

            $analytics = [
                'event_id' => $eventId,
                'event_name' => $event->name,
                'type' => $event->type,
                'status' => $event->status,
                'overview' => [
                    'total_matches' => $event->matches()->count(),
                    'participating_teams' => $event->teams()->count(),
                    'prize_pool' => $event->prize_pool ?? 0,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'region' => $event->region,
                    'tier' => $event->tier ?? 'Unknown'
                ],
                'competition_analytics' => [
                    'match_results' => $this->getEventMatchResults($event),
                    'bracket_progression' => $this->getBracketProgression($event),
                    'upsets_and_surprises' => $this->getUpsetsAndSurprises($event),
                    'performance_rankings' => $this->getPerformanceRankings($event)
                ],
                'viewership_analytics' => [
                    'total_viewership' => $this->getTotalEventViewership($event),
                    'peak_concurrent' => $this->getEventPeakViewership($event),
                    'viewership_by_stage' => $this->getViewershipByStage($event),
                    'international_audience' => $this->getInternationalAudience($event)
                ],
                'engagement_metrics' => [
                    'social_engagement' => $this->getEventSocialEngagement($event),
                    'community_activity' => $this->getCommunityActivity($event),
                    'media_coverage' => $this->getEventMediaCoverage($event)
                ],
                'economic_impact' => [
                    'prize_distribution' => $this->getPrizeDistribution($event),
                    'sponsorship_value' => $this->getSponsorshipValue($event),
                    'economic_metrics' => $this->getEconomicMetrics($event)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error("Event analytics error for event {$eventId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching event analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get news article analytics
     */
    public function news($newsId, Request $request)
    {
        try {
            $news = News::with(['comments', 'author'])->findOrFail($newsId);
            $period = $request->get('period', '30d');
            $days = $this->getPeriodDays($period);
            $startDate = now()->subDays($days);

            $analytics = [
                'news_id' => $newsId,
                'title' => $news->title,
                'author' => $news->author,
                'published_date' => $news->created_at,
                'engagement_overview' => [
                    'total_views' => $news->views ?? 0,
                    'unique_views' => $news->unique_views ?? 0,
                    'comments' => $news->comments()->count(),
                    'shares' => $news->shares ?? 0,
                    'likes' => $news->likes ?? 0,
                    'reading_time' => $this->estimateReadingTime($news->content)
                ],
                'performance_metrics' => [
                    'view_timeline' => $this->getNewsViewTimeline($news, $startDate),
                    'engagement_rate' => $this->getNewsEngagementRate($news),
                    'comment_sentiment' => $this->getCommentSentiment($news),
                    'social_sharing' => $this->getNewsSocialSharing($news)
                ],
                'audience_insights' => [
                    'reader_demographics' => $this->getReaderDemographics($news),
                    'referral_sources' => $this->getReferralSources($news),
                    'reading_behavior' => $this->getReadingBehavior($news)
                ],
                'content_analysis' => [
                    'topic_relevance' => $this->getTopicRelevance($news),
                    'seo_performance' => $this->getSEOPerformance($news),
                    'content_quality_score' => $this->getContentQualityScore($news)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error("News analytics error for news {$newsId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching news analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forum thread analytics
     */
    public function forum($threadId, Request $request)
    {
        try {
            $thread = ForumThread::with(['posts', 'user', 'category'])->findOrFail($threadId);
            $period = $request->get('period', '30d');
            $days = $this->getPeriodDays($period);
            $startDate = now()->subDays($days);

            $analytics = [
                'thread_id' => $threadId,
                'title' => $thread->title,
                'author' => $thread->user,
                'category' => $thread->category,
                'created_date' => $thread->created_at,
                'engagement_overview' => [
                    'total_views' => $thread->views ?? 0,
                    'replies' => $thread->replies ?? 0,
                    'participants' => $this->getThreadParticipants($thread),
                    'last_activity' => $thread->updated_at,
                    'is_pinned' => $thread->pinned ?? false,
                    'is_locked' => $thread->locked ?? false
                ],
                'activity_metrics' => [
                    'reply_timeline' => $this->getReplyTimeline($thread, $startDate),
                    'participation_rate' => $this->getParticipationRate($thread),
                    'discussion_quality' => $this->getDiscussionQuality($thread),
                    'moderator_actions' => $this->getModeratorActions($thread)
                ],
                'community_engagement' => [
                    'active_contributors' => $this->getActiveContributors($thread),
                    'sentiment_analysis' => $this->getThreadSentiment($thread),
                    'topic_evolution' => $this->getTopicEvolution($thread)
                ],
                'visibility_metrics' => [
                    'search_visibility' => $this->getSearchVisibility($thread),
                    'external_references' => $this->getExternalReferences($thread),
                    'trending_score' => $this->getTrendingScore($thread)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error("Forum analytics error for thread {$threadId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching forum analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper Methods
    private function getPeriodDays($period)
    {
        return match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            'all' => 9999,
            default => 30
        };
    }

    private function getProfileViews($resourceId, $resourceType, $startDate)
    {
        return Cache::remember("analytics:{$resourceType}:{$resourceId}:views", 300, function() {
            return rand(100, 1000);
        });
    }

    // Team Analytics Helper Methods
    private function calculateWinRate($team)
    {
        $totalGames = ($team->wins ?? 0) + ($team->losses ?? 0);
        return $totalGames > 0 ? round((($team->wins ?? 0) / $totalGames) * 100, 1) : 0;
    }

    private function getTeamRank($team)
    {
        return Team::where('rating', '>', $team->rating ?? 1500)->count() + 1;
    }

    private function getRecentForm($team, $startDate)
    {
        return GameMatch::where(function($query) use ($team) {
                $query->where('team1_id', $team->id)
                      ->orWhere('team2_id', $team->id);
            })
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($match) use ($team) {
                $isTeam1 = $match->team1_id == $team->id;
                $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
                $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
                
                return [
                    'match_id' => $match->id,
                    'date' => $match->created_at,
                    'opponent' => $isTeam1 ? $match->team2->name ?? 'Unknown' : $match->team1->name ?? 'Unknown',
                    'result' => $teamScore > $opponentScore ? 'W' : ($teamScore < $opponentScore ? 'L' : 'D'),
                    'score' => "{$teamScore}-{$opponentScore}"
                ];
            });
    }

    // Placeholder implementations for other helper methods
    private function getMatchHistory($team, $startDate) { return collect(); }
    private function getPerformanceTrends($team, $startDate) { return []; }
    private function getOpponentAnalysis($team, $startDate) { return []; }
    private function getRosterStats($team) { return []; }
    private function getPlayerPerformance($team, $startDate) { return []; }
    private function getRoleDistribution($team) { return []; }
    private function getFanEngagement($team, $startDate) { return []; }
    private function getSocialMentions($team, $startDate) { return []; }
    private function getMediaCoverage($team, $startDate) { return []; }
    private function getTournamentHistory($team, $startDate) { return []; }
    private function getPrizeEarnings($team, $startDate) { return []; }
    private function getAchievementTimeline($team) { return []; }
    
    // Player Analytics Helper Methods  
    private function getPlayerRank($player) { return rand(1, 100); }
    private function getPlayerMatchCount($player) { return rand(10, 100); }
    private function getCareerKDA($player) { return round(rand(80, 200) / 100, 2); }
    private function getRecentPlayerStats($player, $startDate) { return []; }
    private function getHeroPerformance($player, $startDate) { return []; }
    private function getMatchPerformance($player, $startDate) { return []; }
    private function getConsistencyRating($player, $startDate) { return rand(70, 95); }
    private function getTournamentPerformance($player, $startDate) { return []; }
    private function getTeamContribution($player, $startDate) { return []; }
    private function getClutchPerformance($player, $startDate) { return []; }
    private function getFanFollowing($player, $startDate) { return []; }
    private function getSocialPresence($player, $startDate) { return []; }
    private function getRatingHistory($player) { return []; }
    private function getPlayerTeamHistory($player) { return []; }
    private function getPlayerAchievements($player) { return []; }
    
    // Additional helper methods would be implemented similarly
    private function calculateMatchDuration($match) { return '45 minutes'; }
    private function getViewerRetention($match) { return '78%'; }
    private function getViewerTimeline($match) { return []; }
    private function getViewerDemographics($match) { return []; }
    private function getMatchPlayerStats($match) { return []; }
    private function getMatchTeamStats($match) { return []; }
    private function getMatchHeroPicks($match) { return []; }
    private function getMatchMapPerformance($match) { return []; }
    
    // And many more helper methods for comprehensive analytics...
}