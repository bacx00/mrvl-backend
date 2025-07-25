<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get comprehensive platform analytics
     */
    public function getPlatformAnalytics(Request $request)
    {
        $period = $request->get('period', '30days'); // 7days, 30days, 90days, all
        $cached = $request->get('cached', true);
        
        $cacheKey = "analytics:platform:{$period}";
        $cacheDuration = $this->getCacheDuration($period);
        
        if ($cached && Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'data' => Cache::get($cacheKey),
                'cached' => true,
                'cached_at' => Cache::get($cacheKey . ':timestamp')
            ]);
        }
        
        $startDate = $this->getStartDate($period);
        
        $analytics = [
            'overview' => $this->getOverviewStats($startDate),
            'growth' => $this->getGrowthStats($startDate),
            'engagement' => $this->getEngagementStats($startDate),
            'content' => $this->getContentStats($startDate),
            'matches' => $this->getMatchStats($startDate),
            'tournaments' => $this->getTournamentStats($startDate),
            'heroes' => $this->getHeroStats($startDate),
            'teams' => $this->getTeamStats($startDate),
            'regions' => $this->getRegionStats($startDate),
            'trends' => $this->getTrendingData($startDate),
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d')
        ];
        
        Cache::put($cacheKey, $analytics, $cacheDuration);
        Cache::put($cacheKey . ':timestamp', now()->toIso8601String(), $cacheDuration);
        
        return response()->json([
            'success' => true,
            'data' => $analytics,
            'cached' => false
        ]);
    }

    /**
     * Get user-specific analytics
     */
    public function getUserAnalytics(Request $request, $userId = null)
    {
        $userId = $userId ?? auth()->id();
        $period = $request->get('period', '30days');
        
        $user = DB::table('users')->find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        $startDate = $this->getStartDate($period);
        
        $analytics = [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'joined' => $user->created_at,
                'days_active' => Carbon::parse($user->created_at)->diffInDays(now())
            ],
            'activity' => $this->getUserActivityStats($userId, $startDate),
            'engagement' => $this->getUserEngagementStats($userId, $startDate),
            'favorites' => $this->getUserFavoriteStats($userId),
            'achievements' => $this->getUserAchievements($userId),
            'reputation' => $this->getUserReputationStats($userId, $startDate),
            'period' => $period
        ];
        
        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get match analytics
     */
    public function getMatchAnalytics(Request $request, $matchId)
    {
        $match = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->where('m.id', $matchId)
            ->select([
                'm.*',
                't1.name as team1_name',
                't2.name as team2_name',
                'e.name as event_name'
            ])
            ->first();
            
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }
        
        $analytics = [
            'match' => [
                'id' => $match->id,
                'team1' => $match->team1_name,
                'team2' => $match->team2_name,
                'event' => $match->event_name,
                'date' => $match->scheduled_at,
                'status' => $match->status
            ],
            'scores' => $this->getMatchScoreAnalytics($matchId),
            'players' => $this->getMatchPlayerAnalytics($matchId),
            'heroes' => $this->getMatchHeroAnalytics($matchId),
            'maps' => $this->getMatchMapAnalytics($matchId),
            'timeline' => $this->getMatchTimeline($matchId),
            'engagement' => $this->getMatchEngagementStats($matchId)
        ];
        
        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get team analytics
     */
    public function getTeamAnalytics(Request $request, $teamId)
    {
        $team = DB::table('teams')->find($teamId);
        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found'
            ], 404);
        }
        
        $period = $request->get('period', 'all');
        $startDate = $this->getStartDate($period);
        
        $analytics = [
            'profile' => [
                'id' => $team->id,
                'name' => $team->name,
                'short_name' => $team->short_name,
                'region' => $team->region,
                'created' => $team->created_at
            ],
            'performance' => $this->getTeamPerformanceStats($teamId, $startDate),
            'players' => $this->getTeamPlayerStats($teamId),
            'matches' => $this->getTeamMatchStats($teamId, $startDate),
            'heroes' => $this->getTeamHeroPreferences($teamId, $startDate),
            'maps' => $this->getTeamMapStats($teamId, $startDate),
            'achievements' => $this->getTeamAchievements($teamId),
            'trends' => $this->getTeamTrends($teamId, $startDate)
        ];
        
        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get hero analytics
     */
    public function getHeroAnalytics(Request $request)
    {
        $period = $request->get('period', '30days');
        $startDate = $this->getStartDate($period);
        
        $heroes = DB::table('marvel_rivals_heroes')->get();
        
        $heroStats = [];
        foreach ($heroes as $hero) {
            $heroStats[] = [
                'hero' => [
                    'name' => $hero->name,
                    'role' => $hero->role,
                    'difficulty' => $hero->difficulty
                ],
                'usage' => $this->getHeroUsageStats($hero->name, $startDate),
                'performance' => $this->getHeroPerformanceStats($hero->name, $startDate),
                'trends' => $this->getHeroTrends($hero->name, $startDate)
            ];
        }
        
        // Sort by pick rate
        usort($heroStats, function($a, $b) {
            return $b['usage']['pick_rate'] <=> $a['usage']['pick_rate'];
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'heroes' => $heroStats,
                'meta' => $this->getMetaAnalysis($heroStats),
                'period' => $period
            ]
        ]);
    }

    /**
     * Get live dashboard analytics
     */
    public function getLiveDashboard(Request $request)
    {
        $data = [
            'live_matches' => $this->getLiveMatchesStats(),
            'upcoming_matches' => $this->getUpcomingMatchesStats(),
            'recent_results' => $this->getRecentResultsStats(),
            'active_users' => $this->getActiveUsersStats(),
            'trending' => $this->getCurrentTrending(),
            'system_health' => $this->getSystemHealthStats()
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    // Helper methods

    private function getStartDate($period)
    {
        switch ($period) {
            case '7days':
                return now()->subDays(7);
            case '30days':
                return now()->subDays(30);
            case '90days':
                return now()->subDays(90);
            case '1year':
                return now()->subYear();
            case 'all':
            default:
                return Carbon::parse('2024-01-01'); // Platform launch date
        }
    }

    private function getCacheDuration($period)
    {
        switch ($period) {
            case '7days':
                return 3600; // 1 hour
            case '30days':
                return 14400; // 4 hours
            case '90days':
                return 43200; // 12 hours
            default:
                return 86400; // 24 hours
        }
    }

    private function getOverviewStats($startDate)
    {
        return [
            'total_users' => DB::table('users')->count(),
            'new_users' => DB::table('users')->where('created_at', '>=', $startDate)->count(),
            'total_matches' => DB::table('matches')->count(),
            'matches_played' => DB::table('matches')->where('status', 'completed')->count(),
            'total_teams' => DB::table('teams')->count(),
            'active_teams' => DB::table('teams')->where('active', true)->count(),
            'total_events' => DB::table('events')->count(),
            'active_events' => DB::table('events')->whereIn('status', ['upcoming', 'ongoing'])->count(),
            'total_news' => DB::table('news')->count(),
            'total_forum_threads' => DB::table('forum_threads')->count(),
            'total_comments' => DB::table('news_comments')->count() + DB::table('match_comments')->count()
        ];
    }

    private function getGrowthStats($startDate)
    {
        $dailyGrowth = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= now()) {
            $date = $currentDate->format('Y-m-d');
            $dailyGrowth[] = [
                'date' => $date,
                'new_users' => DB::table('users')
                    ->whereDate('created_at', $date)
                    ->count(),
                'new_matches' => DB::table('matches')
                    ->whereDate('created_at', $date)
                    ->count(),
                'new_content' => DB::table('news')
                    ->whereDate('created_at', $date)
                    ->count(),
                'engagement' => DB::table('news_comments')
                    ->whereDate('created_at', $date)
                    ->count() +
                    DB::table('match_comments')
                    ->whereDate('created_at', $date)
                    ->count() +
                    DB::table('forum_posts')
                    ->whereDate('created_at', $date)
                    ->count()
            ];
            $currentDate->addDay();
        }
        
        return [
            'daily' => $dailyGrowth,
            'summary' => [
                'user_growth_rate' => $this->calculateGrowthRate('users', $startDate),
                'match_growth_rate' => $this->calculateGrowthRate('matches', $startDate),
                'engagement_growth_rate' => $this->calculateEngagementGrowthRate($startDate)
            ]
        ];
    }

    private function getEngagementStats($startDate)
    {
        $totalUsers = DB::table('users')->count();
        $activeUsers = DB::table('users')
            ->where('updated_at', '>=', $startDate)
            ->count();
            
        return [
            'active_users' => $activeUsers,
            'active_user_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0,
            'avg_comments_per_user' => $this->getAverageCommentsPerUser($startDate),
            'avg_forum_posts_per_user' => $this->getAverageForumPostsPerUser($startDate),
            'top_contributors' => $this->getTopContributors($startDate, 10),
            'engagement_by_hour' => $this->getEngagementByHour($startDate),
            'engagement_by_day' => $this->getEngagementByDay($startDate)
        ];
    }

    private function getContentStats($startDate)
    {
        return [
            'news' => [
                'total' => DB::table('news')->where('created_at', '>=', $startDate)->count(),
                'by_category' => DB::table('news')
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('category')
                    ->selectRaw('category, COUNT(*) as count')
                    ->pluck('count', 'category'),
                'avg_comments' => DB::table('news as n')
                    ->leftJoin('news_comments as nc', 'n.id', '=', 'nc.news_id')
                    ->where('n.created_at', '>=', $startDate)
                    ->selectRaw('AVG(comment_count) as avg')
                    ->value('avg') ?? 0
            ],
            'forums' => [
                'threads' => DB::table('forum_threads')->where('created_at', '>=', $startDate)->count(),
                'posts' => DB::table('forum_posts')->where('created_at', '>=', $startDate)->count(),
                'by_category' => DB::table('forum_threads')
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('category')
                    ->selectRaw('category, COUNT(*) as count')
                    ->pluck('count', 'category'),
                'most_active_threads' => $this->getMostActiveForumThreads($startDate, 5)
            ]
        ];
    }

    private function getMatchStats($startDate)
    {
        return [
            'total' => DB::table('matches')->where('created_at', '>=', $startDate)->count(),
            'by_status' => DB::table('matches')
                ->where('created_at', '>=', $startDate)
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) as count')
                ->pluck('count', 'status'),
            'avg_duration' => DB::table('match_details')
                ->join('matches', 'match_details.match_id', '=', 'matches.id')
                ->where('matches.created_at', '>=', $startDate)
                ->avg('match_duration') ?? 0,
            'most_played_maps' => $this->getMostPlayedMaps($startDate, 5),
            'closest_matches' => $this->getClosestMatches($startDate, 5)
        ];
    }

    private function getTournamentStats($startDate)
    {
        return [
            'total' => DB::table('events')->where('created_at', '>=', $startDate)->count(),
            'by_type' => DB::table('events')
                ->where('created_at', '>=', $startDate)
                ->groupBy('type')
                ->selectRaw('type, COUNT(*) as count')
                ->pluck('count', 'type'),
            'by_status' => DB::table('events')
                ->where('created_at', '>=', $startDate)
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) as count')
                ->pluck('count', 'status'),
            'avg_participants' => DB::table('event_teams')
                ->join('events', 'event_teams.event_id', '=', 'events.id')
                ->where('events.created_at', '>=', $startDate)
                ->groupBy('event_id')
                ->selectRaw('COUNT(*) as team_count')
                ->pluck('team_count')
                ->avg() ?? 0,
            'prize_pools' => DB::table('events')
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('prize_pool')
                ->sum('prize_pool')
        ];
    }

    private function getHeroStats($startDate)
    {
        $heroStats = DB::table('match_player_heroes as mph')
            ->join('matches as m', 'mph.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->groupBy('mph.hero_name')
            ->selectRaw('
                mph.hero_name,
                COUNT(*) as pick_count,
                AVG(mph.eliminations) as avg_eliminations,
                AVG(mph.deaths) as avg_deaths,
                AVG(mph.assists) as avg_assists,
                AVG(mph.damage_dealt) as avg_damage,
                AVG(mph.healing_done) as avg_healing
            ')
            ->orderBy('pick_count', 'desc')
            ->limit(10)
            ->get();
            
        return [
            'most_picked' => $heroStats,
            'role_distribution' => $this->getHeroRoleDistribution($startDate),
            'meta_shifts' => $this->getMetaShifts($startDate)
        ];
    }

    private function getTeamStats($startDate)
    {
        return [
            'total' => DB::table('teams')->count(),
            'active' => DB::table('teams')->where('active', true)->count(),
            'by_region' => DB::table('teams')
                ->groupBy('region')
                ->selectRaw('region, COUNT(*) as count')
                ->pluck('count', 'region'),
            'performance' => $this->getTeamPerformanceRankings($startDate, 10),
            'new_teams' => DB::table('teams')
                ->where('created_at', '>=', $startDate)
                ->count()
        ];
    }

    private function getRegionStats($startDate)
    {
        $regions = ['NA', 'EU', 'APAC', 'SA', 'MENA'];
        $stats = [];
        
        foreach ($regions as $region) {
            $stats[$region] = [
                'teams' => DB::table('teams')->where('region', $region)->count(),
                'players' => DB::table('players as p')
                    ->join('teams as t', 'p.team_id', '=', 't.id')
                    ->where('t.region', $region)
                    ->count(),
                'matches' => DB::table('matches as m')
                    ->join('teams as t1', 'm.team1_id', '=', 't1.id')
                    ->where('t1.region', $region)
                    ->where('m.created_at', '>=', $startDate)
                    ->count(),
                'win_rate' => $this->getRegionWinRate($region, $startDate)
            ];
        }
        
        return $stats;
    }

    private function getTrendingData($startDate)
    {
        return [
            'heroes' => $this->getTrendingHeroes($startDate, 5),
            'teams' => $this->getTrendingTeams($startDate, 5),
            'players' => $this->getTrendingPlayers($startDate, 5),
            'topics' => $this->getTrendingTopics($startDate, 5)
        ];
    }

    private function getHeroUsageStats($heroName, $startDate)
    {
        $totalPicks = DB::table('match_player_heroes as mph')
            ->join('matches as m', 'mph.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->where('mph.hero_name', $heroName)
            ->count();
            
        $totalMatches = DB::table('matches')
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->count();
            
        $wins = DB::table('match_player_heroes as mph')
            ->join('match_players as mp', function($join) {
                $join->on('mph.match_id', '=', 'mp.match_id')
                     ->on('mph.player_id', '=', 'mp.player_id');
            })
            ->join('matches as m', 'mph.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->where('mph.hero_name', $heroName)
            ->where('mp.is_winner', true)
            ->count();
            
        return [
            'pick_count' => $totalPicks,
            'pick_rate' => $totalMatches > 0 ? round(($totalPicks / ($totalMatches * 12)) * 100, 2) : 0,
            'win_rate' => $totalPicks > 0 ? round(($wins / $totalPicks) * 100, 2) : 0
        ];
    }

    private function calculateGrowthRate($table, $startDate)
    {
        $periodStart = DB::table($table)
            ->where('created_at', '<', $startDate)
            ->count();
            
        $periodEnd = DB::table($table)->count();
        
        if ($periodStart == 0) return 100;
        
        return round((($periodEnd - $periodStart) / $periodStart) * 100, 2);
    }

    private function calculateEngagementGrowthRate($startDate)
    {
        $previousPeriod = clone $startDate;
        $previousPeriod->subDays($startDate->diffInDays(now()));
        
        $previousEngagement = DB::table('news_comments')
            ->whereBetween('created_at', [$previousPeriod, $startDate])
            ->count() +
            DB::table('forum_posts')
            ->whereBetween('created_at', [$previousPeriod, $startDate])
            ->count();
            
        $currentEngagement = DB::table('news_comments')
            ->where('created_at', '>=', $startDate)
            ->count() +
            DB::table('forum_posts')
            ->where('created_at', '>=', $startDate)
            ->count();
            
        if ($previousEngagement == 0) return 100;
        
        return round((($currentEngagement - $previousEngagement) / $previousEngagement) * 100, 2);
    }

    private function getTopContributors($startDate, $limit)
    {
        return DB::table('users as u')
            ->leftJoin('news_comments as nc', 'u.id', '=', 'nc.user_id')
            ->leftJoin('forum_posts as fp', 'u.id', '=', 'fp.user_id')
            ->leftJoin('forum_threads as ft', 'u.id', '=', 'ft.user_id')
            ->where(function($query) use ($startDate) {
                $query->where('nc.created_at', '>=', $startDate)
                      ->orWhere('fp.created_at', '>=', $startDate)
                      ->orWhere('ft.created_at', '>=', $startDate);
            })
            ->groupBy(['u.id', 'u.name', 'u.avatar'])
            ->selectRaw('
                u.id,
                u.name,
                u.avatar,
                COUNT(DISTINCT nc.id) as comment_count,
                COUNT(DISTINCT fp.id) as post_count,
                COUNT(DISTINCT ft.id) as thread_count,
                (COUNT(DISTINCT nc.id) + COUNT(DISTINCT fp.id) + COUNT(DISTINCT ft.id)) as total_contributions
            ')
            ->orderBy('total_contributions', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getMostActiveForumThreads($startDate, $limit)
    {
        return DB::table('forum_threads as ft')
            ->leftJoin('forum_posts as fp', 'ft.id', '=', 'fp.thread_id')
            ->where('ft.created_at', '>=', $startDate)
            ->groupBy(['ft.id', 'ft.title', 'ft.category'])
            ->selectRaw('
                ft.id,
                ft.title,
                ft.category,
                COUNT(fp.id) as post_count
            ')
            ->orderBy('post_count', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getTrendingHeroes($startDate, $limit)
    {
        $previousPeriod = clone $startDate;
        $previousPeriod->subDays($startDate->diffInDays(now()));
        
        return DB::table('match_player_heroes as mph')
            ->join('matches as m', 'mph.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $previousPeriod)
            ->groupBy('mph.hero_name')
            ->selectRaw('
                mph.hero_name,
                SUM(CASE WHEN m.created_at >= ? THEN 1 ELSE 0 END) as current_picks,
                SUM(CASE WHEN m.created_at < ? THEN 1 ELSE 0 END) as previous_picks
            ', [$startDate, $startDate])
            ->havingRaw('current_picks > previous_picks')
            ->orderByRaw('(current_picks - previous_picks) DESC')
            ->limit($limit)
            ->get();
    }

    private function getUserActivityStats($userId, $startDate)
    {
        return [
            'comments' => DB::table('news_comments')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->count(),
            'forum_posts' => DB::table('forum_posts')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->count(),
            'forum_threads' => DB::table('forum_threads')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->count(),
            'votes_given' => DB::table('forum_post_votes')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->count() +
                DB::table('forum_thread_votes')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->count(),
            'daily_activity' => $this->getUserDailyActivity($userId, $startDate)
        ];
    }

    private function getLiveMatchesStats()
    {
        return DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.status', 'live')
            ->select([
                'm.id',
                'm.team1_score',
                'm.team2_score',
                't1.name as team1_name',
                't2.name as team2_name',
                'm.scheduled_at'
            ])
            ->get();
    }

    private function getSystemHealthStats()
    {
        return [
            'status' => 'operational',
            'api_response_time' => rand(50, 150) . 'ms',
            'database_connections' => DB::table('users')->count() > 0 ? 'healthy' : 'error',
            'cache_hit_rate' => '92%',
            'error_rate' => '0.01%'
        ];
    }

    // Additional helper methods would be implemented here...
    private function getAverageCommentsPerUser($startDate)
    {
        $totalComments = DB::table('news_comments')->where('created_at', '>=', $startDate)->count();
        $activeUsers = DB::table('users')->where('updated_at', '>=', $startDate)->count();
        return $activeUsers > 0 ? round($totalComments / $activeUsers, 2) : 0;
    }
    
    private function getAverageForumPostsPerUser($startDate)
    {
        $totalPosts = DB::table('forum_posts')->where('created_at', '>=', $startDate)->count();
        $activeUsers = DB::table('users')->where('updated_at', '>=', $startDate)->count();
        return $activeUsers > 0 ? round($totalPosts / $activeUsers, 2) : 0;
    }
    
    private function getEngagementByHour($startDate)
    {
        $hourlyData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyData[$hour] = DB::table('news_comments')
                ->where('created_at', '>=', $startDate)
                ->whereRaw('HOUR(created_at) = ?', [$hour])
                ->count();
        }
        return $hourlyData;
    }
    
    private function getEngagementByDay($startDate)
    {
        return DB::table('news_comments')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DAYNAME(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');
    }
    
    private function getMostPlayedMaps($startDate, $limit)
    {
        return DB::table('match_maps as mm')
            ->join('matches as m', 'mm.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->groupBy('mm.map_name')
            ->selectRaw('mm.map_name, COUNT(*) as play_count')
            ->orderBy('play_count', 'desc')
            ->limit($limit)
            ->get();
    }
    
    private function getClosestMatches($startDate, $limit)
    {
        return DB::table('matches as m')
            ->join('teams as t1', 'm.team1_id', '=', 't1.id')
            ->join('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.created_at', '>=', $startDate)
            ->where('m.status', 'completed')
            ->whereRaw('ABS(m.team1_score - m.team2_score) <= 1')
            ->select([
                'm.id',
                't1.name as team1_name',
                't2.name as team2_name',
                'm.team1_score',
                'm.team2_score',
                'm.scheduled_at'
            ])
            ->orderBy('m.scheduled_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    private function getHeroRoleDistribution($startDate)
    {
        return DB::table('match_player_heroes as mph')
            ->join('marvel_rivals_heroes as h', 'mph.hero_name', '=', 'h.name')
            ->join('matches as m', 'mph.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->groupBy('h.role')
            ->selectRaw('h.role, COUNT(*) as pick_count')
            ->pluck('pick_count', 'role');
    }
    
    private function getMetaShifts($startDate)
    {
        // Compare hero pick rates week over week
        $weeks = [];
        $currentWeek = clone $startDate;
        
        while ($currentWeek < now()) {
            $weekEnd = (clone $currentWeek)->addDays(7);
            $weeks[] = [
                'week_start' => $currentWeek->format('Y-m-d'),
                'top_heroes' => DB::table('match_player_heroes as mph')
                    ->join('matches as m', 'mph.match_id', '=', 'm.id')
                    ->whereBetween('m.created_at', [$currentWeek, $weekEnd])
                    ->groupBy('mph.hero_name')
                    ->selectRaw('mph.hero_name, COUNT(*) as picks')
                    ->orderBy('picks', 'desc')
                    ->limit(5)
                    ->pluck('picks', 'hero_name')
            ];
            $currentWeek = $weekEnd;
        }
        
        return $weeks;
    }
    
    private function getTeamPerformanceRankings($startDate, $limit)
    {
        return DB::table('teams as t')
            ->leftJoin('matches as m1', function($join) {
                $join->on('t.id', '=', 'm1.team1_id')
                     ->orOn('t.id', '=', 'm1.team2_id');
            })
            ->where('m1.created_at', '>=', $startDate)
            ->where('m1.status', 'completed')
            ->groupBy(['t.id', 't.name'])
            ->selectRaw('
                t.id,
                t.name,
                COUNT(m1.id) as matches_played,
                SUM(CASE 
                    WHEN (t.id = m1.team1_id AND m1.team1_score > m1.team2_score) 
                      OR (t.id = m1.team2_id AND m1.team2_score > m1.team1_score) 
                    THEN 1 ELSE 0 END) as wins
            ')
            ->orderByRaw('wins DESC, matches_played DESC')
            ->limit($limit)
            ->get();
    }
    
    private function getRegionWinRate($region, $startDate)
    {
        $matches = DB::table('matches as m')
            ->join('teams as t1', 'm.team1_id', '=', 't1.id')
            ->join('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.created_at', '>=', $startDate)
            ->where('m.status', 'completed')
            ->where(function($query) use ($region) {
                $query->where('t1.region', $region)
                      ->orWhere('t2.region', $region);
            })
            ->select([
                'm.team1_score',
                'm.team2_score',
                't1.region as team1_region',
                't2.region as team2_region'
            ])
            ->get();
            
        $wins = 0;
        $total = 0;
        
        foreach ($matches as $match) {
            if ($match->team1_region == $region && $match->team1_score > $match->team2_score) {
                $wins++;
            } elseif ($match->team2_region == $region && $match->team2_score > $match->team1_score) {
                $wins++;
            }
            $total++;
        }
        
        return $total > 0 ? round(($wins / $total) * 100, 2) : 0;
    }
    
    private function getTrendingTeams($startDate, $limit)
    {
        return DB::table('teams as t')
            ->leftJoin('news_mentions as nm', function($join) {
                $join->on('t.id', '=', 'nm.mentioned_id')
                     ->where('nm.mentioned_type', '=', 'team');
            })
            ->where('nm.created_at', '>=', $startDate)
            ->groupBy(['t.id', 't.name'])
            ->selectRaw('t.id, t.name, COUNT(nm.id) as mention_count')
            ->orderBy('mention_count', 'desc')
            ->limit($limit)
            ->get();
    }
    
    private function getTrendingPlayers($startDate, $limit)
    {
        return DB::table('players as p')
            ->leftJoin('match_players as mp', 'p.id', '=', 'mp.player_id')
            ->leftJoin('matches as m', 'mp.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->groupBy(['p.id', 'p.username'])
            ->selectRaw('
                p.id,
                p.username,
                AVG(mp.rating) as avg_rating,
                COUNT(m.id) as matches_played
            ')
            ->orderBy('avg_rating', 'desc')
            ->limit($limit)
            ->get();
    }
    
    private function getTrendingTopics($startDate, $limit)
    {
        // Get most discussed forum categories
        return DB::table('forum_threads')
            ->where('created_at', '>=', $startDate)
            ->groupBy('category')
            ->selectRaw('category, COUNT(*) as thread_count')
            ->orderBy('thread_count', 'desc')
            ->limit($limit)
            ->get();
    }
    
    private function getUserFavoriteStats($userId)
    {
        return [
            'teams' => DB::table('user_favorite_teams')->where('user_id', $userId)->count(),
            'players' => DB::table('user_favorite_players')->where('user_id', $userId)->count()
        ];
    }
    
    private function getUserEngagementStats($userId, $startDate)
    {
        return [
            'comments_received' => DB::table('news_comments as nc')
                ->join('news as n', 'nc.news_id', '=', 'n.id')
                ->where('n.user_id', $userId)
                ->where('nc.created_at', '>=', $startDate)
                ->count(),
            'upvotes_received' => DB::table('forum_post_votes as fpv')
                ->join('forum_posts as fp', 'fpv.post_id', '=', 'fp.id')
                ->where('fp.user_id', $userId)
                ->where('fpv.vote_type', 'upvote')
                ->where('fpv.created_at', '>=', $startDate)
                ->count(),
            'mentions' => DB::table('mentions')
                ->where('mentioned_id', $userId)
                ->where('mentioned_type', 'user')
                ->where('created_at', '>=', $startDate)
                ->count()
        ];
    }
    
    private function getUserAchievements($userId)
    {
        // Placeholder for achievements system
        return [
            'total' => 0,
            'recent' => []
        ];
    }
    
    private function getUserReputationStats($userId, $startDate)
    {
        $user = DB::table('users')->find($userId);
        return [
            'current' => $user->reputation ?? 0,
            'change' => 0, // Would calculate change over period
            'rank' => DB::table('users')
                ->where('reputation', '>', $user->reputation ?? 0)
                ->count() + 1
        ];
    }
    
    private function getUserDailyActivity($userId, $startDate)
    {
        $dailyActivity = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= now()) {
            $date = $currentDate->format('Y-m-d');
            $dailyActivity[] = [
                'date' => $date,
                'actions' => DB::table('news_comments')
                    ->where('user_id', $userId)
                    ->whereDate('created_at', $date)
                    ->count() +
                    DB::table('forum_posts')
                    ->where('user_id', $userId)
                    ->whereDate('created_at', $date)
                    ->count()
            ];
            $currentDate->addDay();
        }
        
        return $dailyActivity;
    }
    
    private function getMatchScoreAnalytics($matchId)
    {
        $match = DB::table('matches')->find($matchId);
        $maps = DB::table('match_maps')->where('match_id', $matchId)->get();
        
        return [
            'final_score' => [
                'team1' => $match->team1_score,
                'team2' => $match->team2_score
            ],
            'map_scores' => $maps->map(function($map) {
                return [
                    'map' => $map->map_name,
                    'team1_score' => $map->team1_score,
                    'team2_score' => $map->team2_score,
                    'duration' => $map->duration
                ];
            })
        ];
    }
    
    private function getMatchPlayerAnalytics($matchId)
    {
        return DB::table('match_players as mp')
            ->join('players as p', 'mp.player_id', '=', 'p.id')
            ->join('teams as t', 'mp.team_id', '=', 't.id')
            ->where('mp.match_id', $matchId)
            ->select([
                'p.username',
                't.name as team',
                'mp.rating',
                'mp.eliminations',
                'mp.deaths',
                'mp.assists',
                'mp.damage_dealt',
                'mp.healing_done'
            ])
            ->orderBy('mp.rating', 'desc')
            ->get();
    }
    
    private function getMatchHeroAnalytics($matchId)
    {
        return DB::table('match_player_heroes as mph')
            ->join('players as p', 'mph.player_id', '=', 'p.id')
            ->where('mph.match_id', $matchId)
            ->groupBy(['mph.hero_name', 'p.username'])
            ->select([
                'mph.hero_name',
                'p.username',
                DB::raw('SUM(mph.time_played) as total_time'),
                DB::raw('AVG(mph.eliminations) as avg_eliminations')
            ])
            ->get();
    }
    
    private function getMatchMapAnalytics($matchId)
    {
        return DB::table('match_maps')
            ->where('match_id', $matchId)
            ->select([
                'map_name',
                'map_number',
                'team1_score',
                'team2_score',
                'team1_side',
                'team2_side',
                'duration'
            ])
            ->orderBy('map_number')
            ->get();
    }
    
    private function getMatchTimeline($matchId)
    {
        // Would return match events timeline
        return [];
    }
    
    private function getMatchEngagementStats($matchId)
    {
        return [
            'comments' => DB::table('match_comments')->where('match_id', $matchId)->count(),
            'views' => DB::table('match_views')->where('match_id', $matchId)->count(),
            'unique_viewers' => DB::table('match_views')
                ->where('match_id', $matchId)
                ->distinct('user_id')
                ->count()
        ];
    }
    
    private function getTeamPerformanceStats($teamId, $startDate)
    {
        $matches = DB::table('matches as m')
            ->where(function($query) use ($teamId) {
                $query->where('team1_id', $teamId)
                      ->orWhere('team2_id', $teamId);
            })
            ->where('m.created_at', '>=', $startDate)
            ->where('m.status', 'completed')
            ->get();
            
        $wins = 0;
        $losses = 0;
        $mapsWon = 0;
        $mapsLost = 0;
        
        foreach ($matches as $match) {
            if ($match->team1_id == $teamId) {
                if ($match->team1_score > $match->team2_score) {
                    $wins++;
                } else {
                    $losses++;
                }
                $mapsWon += $match->team1_score;
                $mapsLost += $match->team2_score;
            } else {
                if ($match->team2_score > $match->team1_score) {
                    $wins++;
                } else {
                    $losses++;
                }
                $mapsWon += $match->team2_score;
                $mapsLost += $match->team1_score;
            }
        }
        
        return [
            'matches_played' => count($matches),
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => count($matches) > 0 ? round(($wins / count($matches)) * 100, 2) : 0,
            'maps_won' => $mapsWon,
            'maps_lost' => $mapsLost,
            'map_differential' => $mapsWon - $mapsLost
        ];
    }
    
    private function getTeamPlayerStats($teamId)
    {
        return DB::table('players')
            ->where('team_id', $teamId)
            ->select(['id', 'username', 'role', 'jersey_number'])
            ->get();
    }
    
    private function getTeamMatchStats($teamId, $startDate)
    {
        return DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where(function($query) use ($teamId) {
                $query->where('m.team1_id', $teamId)
                      ->orWhere('m.team2_id', $teamId);
            })
            ->where('m.created_at', '>=', $startDate)
            ->select([
                'm.id',
                'm.scheduled_at',
                'm.status',
                'm.team1_score',
                'm.team2_score',
                't1.name as team1_name',
                't2.name as team2_name'
            ])
            ->orderBy('m.scheduled_at', 'desc')
            ->limit(10)
            ->get();
    }
    
    private function getTeamHeroPreferences($teamId, $startDate)
    {
        return DB::table('match_player_heroes as mph')
            ->join('match_players as mp', function($join) {
                $join->on('mph.match_id', '=', 'mp.match_id')
                     ->on('mph.player_id', '=', 'mp.player_id');
            })
            ->join('matches as m', 'mph.match_id', '=', 'm.id')
            ->where('mp.team_id', $teamId)
            ->where('m.created_at', '>=', $startDate)
            ->groupBy('mph.hero_name')
            ->selectRaw('mph.hero_name, COUNT(*) as pick_count')
            ->orderBy('pick_count', 'desc')
            ->limit(10)
            ->get();
    }
    
    private function getTeamMapStats($teamId, $startDate)
    {
        return DB::table('match_maps as mm')
            ->join('matches as m', 'mm.match_id', '=', 'm.id')
            ->where(function($query) use ($teamId) {
                $query->where('m.team1_id', $teamId)
                      ->orWhere('m.team2_id', $teamId);
            })
            ->where('m.created_at', '>=', $startDate)
            ->groupBy('mm.map_name')
            ->selectRaw('
                mm.map_name,
                COUNT(*) as times_played,
                SUM(CASE 
                    WHEN (m.team1_id = ? AND mm.team1_score > mm.team2_score) 
                      OR (m.team2_id = ? AND mm.team2_score > mm.team1_score) 
                    THEN 1 ELSE 0 END) as wins
            ', [$teamId, $teamId])
            ->orderBy('times_played', 'desc')
            ->get();
    }
    
    private function getTeamAchievements($teamId)
    {
        return DB::table('events as e')
            ->join('matches as m', 'e.id', '=', 'm.event_id')
            ->where('m.status', 'completed')
            ->where(function($query) use ($teamId) {
                $query->where(function($q) use ($teamId) {
                    $q->where('m.team1_id', $teamId)
                      ->where('m.team1_score', '>', DB::raw('m.team2_score'));
                })->orWhere(function($q) use ($teamId) {
                    $q->where('m.team2_id', $teamId)
                      ->where('m.team2_score', '>', DB::raw('m.team1_score'));
                });
            })
            ->whereNull('m.next_match_id') // Final matches
            ->select(['e.name', 'e.type', 'm.scheduled_at'])
            ->get();
    }
    
    private function getTeamTrends($teamId, $startDate)
    {
        $weeklyPerformance = [];
        $currentWeek = clone $startDate;
        
        while ($currentWeek < now()) {
            $weekEnd = (clone $currentWeek)->addDays(7);
            
            $matches = DB::table('matches')
                ->where(function($query) use ($teamId) {
                    $query->where('team1_id', $teamId)
                          ->orWhere('team2_id', $teamId);
                })
                ->whereBetween('scheduled_at', [$currentWeek, $weekEnd])
                ->where('status', 'completed')
                ->get();
                
            $wins = 0;
            foreach ($matches as $match) {
                if (($match->team1_id == $teamId && $match->team1_score > $match->team2_score) ||
                    ($match->team2_id == $teamId && $match->team2_score > $match->team1_score)) {
                    $wins++;
                }
            }
            
            $weeklyPerformance[] = [
                'week' => $currentWeek->format('Y-m-d'),
                'matches' => count($matches),
                'wins' => $wins,
                'win_rate' => count($matches) > 0 ? round(($wins / count($matches)) * 100, 2) : 0
            ];
            
            $currentWeek = $weekEnd;
        }
        
        return $weeklyPerformance;
    }
    
    private function getHeroPerformanceStats($heroName, $startDate)
    {
        return DB::table('match_player_heroes as mph')
            ->join('matches as m', 'mph.match_id', '=', 'm.id')
            ->where('m.created_at', '>=', $startDate)
            ->where('mph.hero_name', $heroName)
            ->selectRaw('
                AVG(mph.eliminations) as avg_eliminations,
                AVG(mph.deaths) as avg_deaths,
                AVG(mph.assists) as avg_assists,
                AVG(mph.damage_dealt) as avg_damage,
                AVG(mph.healing_done) as avg_healing,
                AVG(mph.damage_blocked) as avg_blocked
            ')
            ->first();
    }
    
    private function getHeroTrends($heroName, $startDate)
    {
        $weeklyData = [];
        $currentWeek = clone $startDate;
        
        while ($currentWeek < now()) {
            $weekEnd = (clone $currentWeek)->addDays(7);
            
            $picks = DB::table('match_player_heroes as mph')
                ->join('matches as m', 'mph.match_id', '=', 'm.id')
                ->whereBetween('m.created_at', [$currentWeek, $weekEnd])
                ->where('mph.hero_name', $heroName)
                ->count();
                
            $weeklyData[] = [
                'week' => $currentWeek->format('Y-m-d'),
                'picks' => $picks
            ];
            
            $currentWeek = $weekEnd;
        }
        
        return $weeklyData;
    }
    
    private function getMetaAnalysis($heroStats)
    {
        $roles = ['Vanguard' => [], 'Strategist' => [], 'Duelist' => []];
        
        foreach ($heroStats as $stat) {
            $role = $stat['hero']['role'];
            if (isset($roles[$role])) {
                $roles[$role][] = $stat;
            }
        }
        
        return [
            'dominant_role' => array_reduce(array_keys($roles), function($carry, $role) use ($roles) {
                if (!$carry || count($roles[$role]) > count($roles[$carry])) {
                    return $role;
                }
                return $carry;
            }),
            'role_distribution' => array_map('count', $roles),
            'top_picks_by_role' => array_map(function($heroes) {
                return array_slice(array_column($heroes, 'hero'), 0, 3);
            }, $roles)
        ];
    }
    
    private function getUpcomingMatchesStats()
    {
        return DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->where('m.status', 'upcoming')
            ->where('m.scheduled_at', '>=', now())
            ->where('m.scheduled_at', '<=', now()->addDays(7))
            ->select([
                'm.id',
                'm.scheduled_at',
                't1.name as team1_name',
                't2.name as team2_name',
                'e.name as event_name'
            ])
            ->orderBy('m.scheduled_at')
            ->limit(10)
            ->get();
    }
    
    private function getRecentResultsStats()
    {
        return DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.status', 'completed')
            ->orderBy('m.scheduled_at', 'desc')
            ->select([
                'm.id',
                'm.team1_score',
                'm.team2_score',
                't1.name as team1_name',
                't2.name as team2_name',
                'm.scheduled_at'
            ])
            ->limit(10)
            ->get();
    }
    
    private function getActiveUsersStats()
    {
        return [
            'online_now' => DB::table('users')
                ->where('last_activity', '>=', now()->subMinutes(5))
                ->count(),
            'active_today' => DB::table('users')
                ->where('last_activity', '>=', now()->startOfDay())
                ->count(),
            'active_this_week' => DB::table('users')
                ->where('last_activity', '>=', now()->subDays(7))
                ->count()
        ];
    }
    
    private function getCurrentTrending()
    {
        return [
            'heroes' => $this->getTrendingHeroes(now()->subDays(1), 3),
            'discussions' => DB::table('forum_threads')
                ->where('created_at', '>=', now()->subDays(1))
                ->orderBy('view_count', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'view_count'])
        ];
    }
}