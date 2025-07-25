<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ProfileController extends Controller
{
    /**
     * Get user profile with comprehensive analytics
     */
    public function getProfile($userId)
    {
        $user = User::with(['teamFlair', 'roles'])->find($userId);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        // Cache key for profile data
        $cacheKey = "profile:{$userId}";
        $cacheTime = 300; // 5 minutes
        
        $profileData = Cache::remember($cacheKey, $cacheTime, function() use ($user, $userId) {
            return [
                'user' => $this->getUserInfo($user),
                'stats' => $this->getUserStats($userId),
                'activity' => $this->getUserActivity($userId),
                'achievements' => $this->getUserAchievements($userId),
                'interactions' => $this->getUserInteractions($userId),
                'content' => $this->getUserContent($userId),
                'favorites' => $this->getUserFavorites($userId),
                'social' => $this->getUserSocialStats($userId),
                'performance' => $this->getUserPerformance($userId)
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $profileData
        ]);
    }

    /**
     * Get basic user information
     */
    private function getUserInfo($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'hero_flair' => $user->hero_flair,
            'team_flair' => $user->teamFlair,
            'show_hero_flair' => $user->show_hero_flair,
            'show_team_flair' => $user->show_team_flair,
            'roles' => $user->getRoleNames(),
            'status' => $user->status,
            'joined' => $user->created_at,
            'last_seen' => $user->last_login ?? $user->updated_at,
            'profile_views' => $this->getProfileViews($user->id),
            'reputation' => $user->reputation ?? 0,
            'level' => $this->calculateUserLevel($user->id)
        ];
    }

    /**
     * Get comprehensive user statistics
     */
    private function getUserStats($userId)
    {
        $user = User::find($userId);
        $joinDate = $user ? $user->created_at : now();
        
        return [
            'overview' => [
                'days_active' => $joinDate->diffInDays(now()),
                'total_contributions' => $this->getTotalContributions($userId),
                'engagement_score' => $this->calculateEngagementScore($userId),
                'activity_streak' => $this->getActivityStreak($userId),
                'rank' => $this->getUserRank($userId)
            ],
            'content' => [
                'news_comments' => DB::table('news_comments')->where('user_id', $userId)->count(),
                'match_comments' => DB::table('match_comments')->where('user_id', $userId)->count(),
                'forum_threads' => DB::table('forum_threads')->where('user_id', $userId)->count(),
                'forum_posts' => DB::table('forum_posts')->where('user_id', $userId)->count(),
                'total_comments' => DB::table('news_comments')->where('user_id', $userId)->count() + 
                                   DB::table('match_comments')->where('user_id', $userId)->count(),
                'avg_comment_length' => $this->getAverageCommentLength($userId),
                'most_active_category' => $this->getMostActiveCategory($userId)
            ],
            'engagement' => [
                'votes_given' => $this->getVotesGiven($userId),
                'votes_received' => $this->getVotesReceived($userId),
                'mentions_made' => $this->getMentionsMade($userId),
                'mentions_received' => $this->getMentionsReceived($userId),
                'replies_received' => $this->getRepliesReceived($userId),
                'helpful_votes' => $this->getHelpfulVotes($userId)
            ],
            'time_stats' => [
                'most_active_hour' => $this->getMostActiveHour($userId),
                'most_active_day' => $this->getMostActiveDay($userId),
                'avg_session_duration' => $this->getAverageSessionDuration($userId),
                'total_time_spent' => $this->getTotalTimeSpent($userId)
            ]
        ];
    }

    /**
     * Get user activity timeline
     */
    private function getUserActivity($userId)
    {
        $activities = [];
        
        // Get recent comments on news
        $newsComments = DB::table('news_comments as nc')
            ->join('news as n', 'nc.news_id', '=', 'n.id')
            ->where('nc.user_id', $userId)
            ->orderBy('nc.created_at', 'desc')
            ->limit(10)
            ->select([
                'nc.id',
                'nc.content',
                'nc.created_at',
                'n.id as item_id',
                'n.title as item_title',
                DB::raw("'news_comment' as type"),
                DB::raw("'Commented on news article' as action")
            ])
            ->get();
            
        // Get recent match comments
        $matchComments = DB::table('match_comments as mc')
            ->join('matches as m', 'mc.match_id', '=', 'm.id')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('mc.user_id', $userId)
            ->orderBy('mc.created_at', 'desc')
            ->limit(10)
            ->select([
                'mc.id',
                'mc.content',
                'mc.created_at',
                'm.id as item_id',
                DB::raw("CONCAT(t1.name, ' vs ', t2.name) as item_title"),
                DB::raw("'match_comment' as type"),
                DB::raw("'Commented on match' as action")
            ])
            ->get();
            
        // Get recent forum activity
        $forumThreads = DB::table('forum_threads')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->select([
                'id',
                'title as content',
                'created_at',
                'id as item_id',
                'title as item_title',
                DB::raw("'forum_thread' as type"),
                DB::raw("'Created forum thread' as action")
            ])
            ->get();
            
        $forumPosts = DB::table('forum_posts as fp')
            ->join('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
            ->where('fp.user_id', $userId)
            ->orderBy('fp.created_at', 'desc')
            ->limit(10)
            ->select([
                'fp.id',
                'fp.content',
                'fp.created_at',
                'ft.id as item_id',
                'ft.title as item_title',
                DB::raw("'forum_post' as type"),
                DB::raw("'Posted in forum thread' as action")
            ])
            ->get();
            
        // Merge and sort all activities
        $activities = collect()
            ->merge($newsComments)
            ->merge($matchComments)
            ->merge($forumThreads)
            ->merge($forumPosts)
            ->sortByDesc('created_at')
            ->take(20)
            ->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'action' => $activity->action,
                    'content' => \Str::limit($activity->content, 150),
                    'item_id' => $activity->item_id,
                    'item_title' => $activity->item_title,
                    'created_at' => $activity->created_at,
                    'time_ago' => Carbon::parse($activity->created_at)->diffForHumans()
                ];
            })
            ->values();
            
        // Get activity heatmap data
        $heatmap = $this->getActivityHeatmap($userId);
        
        return [
            'recent' => $activities,
            'heatmap' => $heatmap,
            'summary' => [
                'today' => $this->getTodayActivityCount($userId),
                'this_week' => $this->getWeekActivityCount($userId),
                'this_month' => $this->getMonthActivityCount($userId)
            ]
        ];
    }

    /**
     * Get user achievements
     */
    private function getUserAchievements($userId)
    {
        $achievements = [];
        
        // Comment achievements
        $commentCount = DB::table('news_comments')->where('user_id', $userId)->count() + 
                       DB::table('match_comments')->where('user_id', $userId)->count();
        
        if ($commentCount >= 1) $achievements[] = ['id' => 'first_comment', 'name' => 'First Comment', 'icon' => 'comment', 'unlocked_at' => $this->getFirstCommentDate($userId)];
        if ($commentCount >= 10) $achievements[] = ['id' => 'commentator', 'name' => 'Commentator', 'icon' => 'comments', 'unlocked_at' => $this->getNthCommentDate($userId, 10)];
        if ($commentCount >= 50) $achievements[] = ['id' => 'active_commentator', 'name' => 'Active Commentator', 'icon' => 'star', 'unlocked_at' => $this->getNthCommentDate($userId, 50)];
        if ($commentCount >= 100) $achievements[] = ['id' => 'super_commentator', 'name' => 'Super Commentator', 'icon' => 'trophy', 'unlocked_at' => $this->getNthCommentDate($userId, 100)];
        
        // Forum achievements
        $threadCount = DB::table('forum_threads')->where('user_id', $userId)->count();
        $postCount = DB::table('forum_posts')->where('user_id', $userId)->count();
        
        if ($threadCount >= 1) $achievements[] = ['id' => 'first_thread', 'name' => 'Thread Starter', 'icon' => 'plus', 'unlocked_at' => $this->getFirstThreadDate($userId)];
        if ($threadCount >= 10) $achievements[] = ['id' => 'thread_master', 'name' => 'Thread Master', 'icon' => 'fire', 'unlocked_at' => $this->getNthThreadDate($userId, 10)];
        if ($postCount >= 50) $achievements[] = ['id' => 'forum_regular', 'name' => 'Forum Regular', 'icon' => 'user-check', 'unlocked_at' => $this->getNthPostDate($userId, 50)];
        
        // Engagement achievements
        $votesGiven = $this->getVotesGiven($userId)['total'];
        if ($votesGiven >= 10) $achievements[] = ['id' => 'voter', 'name' => 'Active Voter', 'icon' => 'thumbs-up', 'unlocked_at' => now()];
        if ($votesGiven >= 50) $achievements[] = ['id' => 'super_voter', 'name' => 'Super Voter', 'icon' => 'award', 'unlocked_at' => now()];
        
        // Time-based achievements
        $user = User::find($userId);
        $daysActive = $user ? $user->created_at->diffInDays(now()) : 0;
        
        if ($daysActive >= 7) $achievements[] = ['id' => 'week_member', 'name' => 'Week Warrior', 'icon' => 'calendar', 'unlocked_at' => $user->created_at->addDays(7)];
        if ($daysActive >= 30) $achievements[] = ['id' => 'month_member', 'name' => 'Monthly Regular', 'icon' => 'calendar-check', 'unlocked_at' => $user->created_at->addDays(30)];
        if ($daysActive >= 365) $achievements[] = ['id' => 'year_member', 'name' => 'Yearly Veteran', 'icon' => 'medal', 'unlocked_at' => $user->created_at->addDays(365)];
        
        // Special achievements
        $mentionsReceived = $this->getMentionsReceived($userId);
        if ($mentionsReceived >= 10) $achievements[] = ['id' => 'popular', 'name' => 'Popular Member', 'icon' => 'users', 'unlocked_at' => now()];
        
        return [
            'unlocked' => $achievements,
            'total_unlocked' => count($achievements),
            'recent' => array_slice($achievements, -3),
            'categories' => [
                'commenting' => count(array_filter($achievements, fn($a) => str_contains($a['id'], 'comment'))),
                'forum' => count(array_filter($achievements, fn($a) => str_contains($a['id'], 'thread') || str_contains($a['id'], 'forum'))),
                'engagement' => count(array_filter($achievements, fn($a) => str_contains($a['id'], 'voter') || str_contains($a['id'], 'popular'))),
                'loyalty' => count(array_filter($achievements, fn($a) => str_contains($a['id'], 'member')))
            ]
        ];
    }

    /**
     * Get user interactions
     */
    private function getUserInteractions($userId)
    {
        return [
            'followers' => $this->getUserFollowers($userId),
            'following' => $this->getUserFollowing($userId),
            'recent_interactions' => $this->getRecentInteractions($userId),
            'top_interactions' => $this->getTopInteractions($userId),
            'interaction_graph' => $this->getInteractionGraph($userId)
        ];
    }

    /**
     * Get user content breakdown
     */
    private function getUserContent($userId)
    {
        return [
            'most_discussed_topics' => $this->getMostDiscussedTopics($userId),
            'content_quality' => $this->getContentQualityMetrics($userId),
            'popular_posts' => $this->getPopularPosts($userId),
            'content_timeline' => $this->getContentTimeline($userId),
            'word_cloud' => $this->generateWordCloud($userId)
        ];
    }

    /**
     * Get user favorites
     */
    private function getUserFavorites($userId)
    {
        $favoriteTeams = DB::table('user_favorite_teams as uft')
            ->join('teams as t', 'uft.team_id', '=', 't.id')
            ->where('uft.user_id', $userId)
            ->select(['t.id', 't.name', 't.logo', 't.region', 'uft.created_at as favorited_at'])
            ->get();
            
        $favoritePlayers = DB::table('user_favorite_players as ufp')
            ->join('players as p', 'ufp.player_id', '=', 'p.id')
            ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
            ->where('ufp.user_id', $userId)
            ->select([
                'p.id', 
                'p.username', 
                'p.real_name', 
                'p.avatar', 
                'p.role',
                't.name as team_name',
                'ufp.created_at as favorited_at'
            ])
            ->get();
            
        return [
            'teams' => $favoriteTeams,
            'players' => $favoritePlayers,
            'team_count' => $favoriteTeams->count(),
            'player_count' => $favoritePlayers->count()
        ];
    }

    /**
     * Get user social statistics
     */
    private function getUserSocialStats($userId)
    {
        return [
            'influence_score' => $this->calculateInfluenceScore($userId),
            'network_size' => $this->getNetworkSize($userId),
            'engagement_rate' => $this->getEngagementRate($userId),
            'response_rate' => $this->getResponseRate($userId),
            'mention_reach' => $this->getMentionReach($userId),
            'community_standing' => $this->getCommunityStanding($userId)
        ];
    }

    /**
     * Get user performance metrics
     */
    private function getUserPerformance($userId)
    {
        return [
            'prediction_accuracy' => $this->getPredictionAccuracy($userId),
            'content_performance' => $this->getContentPerformance($userId),
            'contribution_trends' => $this->getContributionTrends($userId),
            'expertise_areas' => $this->getExpertiseAreas($userId),
            'improvement_suggestions' => $this->getImprovementSuggestions($userId)
        ];
    }

    // Helper methods

    private function getProfileViews($userId)
    {
        return DB::table('profile_views')
            ->where('profile_id', $userId)
            ->count();
    }

    private function calculateUserLevel($userId)
    {
        $totalContributions = $this->getTotalContributions($userId);
        
        if ($totalContributions < 10) return 1;
        if ($totalContributions < 50) return 2;
        if ($totalContributions < 100) return 3;
        if ($totalContributions < 250) return 4;
        if ($totalContributions < 500) return 5;
        if ($totalContributions < 1000) return 6;
        if ($totalContributions < 2500) return 7;
        if ($totalContributions < 5000) return 8;
        if ($totalContributions < 10000) return 9;
        return 10;
    }

    private function getTotalContributions($userId)
    {
        return DB::table('news_comments')->where('user_id', $userId)->count() +
               DB::table('match_comments')->where('user_id', $userId)->count() +
               DB::table('forum_threads')->where('user_id', $userId)->count() +
               DB::table('forum_posts')->where('user_id', $userId)->count();
    }

    private function calculateEngagementScore($userId)
    {
        $comments = DB::table('news_comments')->where('user_id', $userId)->count();
        $posts = DB::table('forum_posts')->where('user_id', $userId)->count();
        $votes = $this->getVotesGiven($userId)['total'];
        $mentions = $this->getMentionsMade($userId);
        
        return round(($comments * 1) + ($posts * 2) + ($votes * 0.5) + ($mentions * 1.5));
    }

    private function getActivityStreak($userId)
    {
        $dates = DB::table('news_comments')
            ->where('user_id', $userId)
            ->selectRaw('DATE(created_at) as date')
            ->union(
                DB::table('forum_posts')
                    ->where('user_id', $userId)
                    ->selectRaw('DATE(created_at) as date')
            )
            ->orderBy('date', 'desc')
            ->pluck('date')
            ->unique();
            
        $streak = 0;
        $currentDate = now()->format('Y-m-d');
        
        foreach ($dates as $date) {
            if ($date == $currentDate) {
                $streak++;
                $currentDate = Carbon::parse($currentDate)->subDay()->format('Y-m-d');
            } else {
                break;
            }
        }
        
        return $streak;
    }

    private function getUserRank($userId)
    {
        $userScore = $this->calculateEngagementScore($userId);
        $higherScoreCount = DB::table('users as u')
            ->selectRaw('
                (SELECT COUNT(*) FROM news_comments WHERE user_id = u.id) +
                (SELECT COUNT(*) FROM forum_posts WHERE user_id = u.id) * 2 as score
            ')
            ->havingRaw('score > ?', [$userScore])
            ->count();
            
        return $higherScoreCount + 1;
    }

    private function getAverageCommentLength($userId)
    {
        $avgLength = DB::table('news_comments')
            ->where('user_id', $userId)
            ->selectRaw('AVG(CHAR_LENGTH(content)) as avg_length')
            ->value('avg_length');
            
        return round($avgLength ?? 0);
    }

    private function getMostActiveCategory($userId)
    {
        $category = DB::table('forum_threads')
            ->where('user_id', $userId)
            ->groupBy('category')
            ->selectRaw('category, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->first();
            
        return $category ? $category->category : 'General';
    }

    private function getVotesGiven($userId)
    {
        $upvotes = DB::table('forum_post_votes')
            ->where('user_id', $userId)
            ->where('vote_type', 'upvote')
            ->count() +
            DB::table('forum_thread_votes')
            ->where('user_id', $userId)
            ->where('vote_type', 'upvote')
            ->count();
            
        $downvotes = DB::table('forum_post_votes')
            ->where('user_id', $userId)
            ->where('vote_type', 'downvote')
            ->count() +
            DB::table('forum_thread_votes')
            ->where('user_id', $userId)
            ->where('vote_type', 'downvote')
            ->count();
            
        return [
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'total' => $upvotes + $downvotes
        ];
    }

    private function getVotesReceived($userId)
    {
        $userThreads = DB::table('forum_threads')->where('user_id', $userId)->pluck('id');
        $userPosts = DB::table('forum_posts')->where('user_id', $userId)->pluck('id');
        
        $upvotes = DB::table('forum_thread_votes')
            ->whereIn('thread_id', $userThreads)
            ->where('vote_type', 'upvote')
            ->count() +
            DB::table('forum_post_votes')
            ->whereIn('post_id', $userPosts)
            ->where('vote_type', 'upvote')
            ->count();
            
        $downvotes = DB::table('forum_thread_votes')
            ->whereIn('thread_id', $userThreads)
            ->where('vote_type', 'downvote')
            ->count() +
            DB::table('forum_post_votes')
            ->whereIn('post_id', $userPosts)
            ->where('vote_type', 'downvote')
            ->count();
            
        return [
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'total' => $upvotes + $downvotes,
            'ratio' => $downvotes > 0 ? round($upvotes / $downvotes, 2) : $upvotes
        ];
    }

    private function getMentionsMade($userId)
    {
        return DB::table('mentions')
            ->where('mentioned_by', $userId)
            ->count();
    }

    private function getMentionsReceived($userId)
    {
        return DB::table('mentions')
            ->where('mentioned_id', $userId)
            ->where('mentioned_type', 'user')
            ->count();
    }

    private function getRepliesReceived($userId)
    {
        $userPosts = DB::table('forum_posts')->where('user_id', $userId)->pluck('id');
        
        return DB::table('forum_posts')
            ->whereIn('parent_id', $userPosts)
            ->count();
    }

    private function getHelpfulVotes($userId)
    {
        $userPosts = DB::table('forum_posts')->where('user_id', $userId)->pluck('id');
        
        return DB::table('forum_post_votes')
            ->whereIn('post_id', $userPosts)
            ->where('vote_type', 'helpful')
            ->count();
    }

    private function getMostActiveHour($userId)
    {
        $hour = DB::table('news_comments')
            ->where('user_id', $userId)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('count', 'desc')
            ->first();
            
        return $hour ? $hour->hour : 0;
    }

    private function getMostActiveDay($userId)
    {
        $day = DB::table('news_comments')
            ->where('user_id', $userId)
            ->selectRaw('DAYNAME(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('count', 'desc')
            ->first();
            
        return $day ? $day->day : 'Monday';
    }

    private function getAverageSessionDuration($userId)
    {
        // Placeholder - would need session tracking
        return '15 minutes';
    }

    private function getTotalTimeSpent($userId)
    {
        // Placeholder - would need session tracking
        return '24 hours';
    }

    private function getActivityHeatmap($userId)
    {
        $heatmapData = [];
        
        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 24; $j++) {
                $count = DB::table('news_comments')
                    ->where('user_id', $userId)
                    ->whereRaw('DAYOFWEEK(created_at) = ? AND HOUR(created_at) = ?', [$i + 1, $j])
                    ->count();
                    
                $heatmapData[] = [
                    'day' => $i,
                    'hour' => $j,
                    'value' => $count
                ];
            }
        }
        
        return $heatmapData;
    }

    private function getTodayActivityCount($userId)
    {
        return DB::table('news_comments')
            ->where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count() +
            DB::table('forum_posts')
            ->where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();
    }

    private function getWeekActivityCount($userId)
    {
        return DB::table('news_comments')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count() +
            DB::table('forum_posts')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    private function getMonthActivityCount($userId)
    {
        return DB::table('news_comments')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count() +
            DB::table('forum_posts')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }

    private function getFirstCommentDate($userId)
    {
        $comment = DB::table('news_comments')
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->first();
            
        return $comment ? $comment->created_at : now();
    }

    private function getNthCommentDate($userId, $n)
    {
        $comment = DB::table('news_comments')
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->skip($n - 1)
            ->first();
            
        return $comment ? $comment->created_at : now();
    }

    private function getFirstThreadDate($userId)
    {
        $thread = DB::table('forum_threads')
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->first();
            
        return $thread ? $thread->created_at : now();
    }

    private function getNthThreadDate($userId, $n)
    {
        $thread = DB::table('forum_threads')
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->skip($n - 1)
            ->first();
            
        return $thread ? $thread->created_at : now();
    }

    private function getNthPostDate($userId, $n)
    {
        $post = DB::table('forum_posts')
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->skip($n - 1)
            ->first();
            
        return $post ? $post->created_at : now();
    }

    private function getUserFollowers($userId)
    {
        // Placeholder - would need followers table
        return [
            'count' => rand(0, 100),
            'recent' => []
        ];
    }

    private function getUserFollowing($userId)
    {
        // Placeholder - would need following table
        return [
            'count' => rand(0, 50),
            'recent' => []
        ];
    }

    private function getRecentInteractions($userId)
    {
        return DB::table('mentions')
            ->where('mentioned_by', $userId)
            ->orWhere(function($query) use ($userId) {
                $query->where('mentioned_id', $userId)
                      ->where('mentioned_type', 'user');
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    private function getTopInteractions($userId)
    {
        return DB::table('mentions')
            ->where('mentioned_by', $userId)
            ->groupBy('mentioned_id', 'mentioned_type')
            ->selectRaw('mentioned_id, mentioned_type, COUNT(*) as interaction_count')
            ->orderBy('interaction_count', 'desc')
            ->limit(5)
            ->get();
    }

    private function getInteractionGraph($userId)
    {
        // Returns data for visualizing user interaction network
        return [
            'nodes' => [],
            'edges' => []
        ];
    }

    private function getMostDiscussedTopics($userId)
    {
        return DB::table('forum_threads')
            ->where('forum_threads.user_id', $userId)
            ->leftJoin('forum_posts', 'forum_threads.id', '=', 'forum_posts.thread_id')
            ->groupBy('forum_threads.id', 'forum_threads.title')
            ->selectRaw('forum_threads.title, COUNT(forum_posts.id) as discussion_count')
            ->orderBy('discussion_count', 'desc')
            ->limit(5)
            ->get();
    }

    private function getContentQualityMetrics($userId)
    {
        $votes = $this->getVotesReceived($userId);
        
        return [
            'quality_score' => $votes['ratio'] * 10,
            'avg_upvotes_per_post' => $this->getTotalContributions($userId) > 0 
                ? round($votes['upvotes'] / $this->getTotalContributions($userId), 2) 
                : 0,
            'controversial_score' => $votes['downvotes'] > 0 
                ? round($votes['downvotes'] / ($votes['upvotes'] + $votes['downvotes']) * 100, 2)
                : 0
        ];
    }

    private function getPopularPosts($userId)
    {
        return DB::table('forum_posts as fp')
            ->join('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
            ->leftJoin('forum_post_votes as fpv', 'fp.id', '=', 'fpv.post_id')
            ->where('fp.user_id', $userId)
            ->groupBy(['fp.id', 'fp.content', 'ft.title', 'fp.created_at'])
            ->selectRaw('
                fp.id,
                fp.content,
                ft.title as thread_title,
                fp.created_at,
                COUNT(CASE WHEN fpv.vote_type = "upvote" THEN 1 END) as upvotes
            ')
            ->orderBy('upvotes', 'desc')
            ->limit(5)
            ->get();
    }

    private function getContentTimeline($userId)
    {
        $timeline = [];
        $currentMonth = now()->startOfMonth();
        
        for ($i = 0; $i < 12; $i++) {
            $month = $currentMonth->copy()->subMonths($i);
            $timeline[] = [
                'month' => $month->format('Y-m'),
                'comments' => DB::table('news_comments')
                    ->where('user_id', $userId)
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count(),
                'posts' => DB::table('forum_posts')
                    ->where('user_id', $userId)
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count()
            ];
        }
        
        return array_reverse($timeline);
    }

    private function generateWordCloud($userId)
    {
        // Get user's content
        $content = DB::table('news_comments')
            ->where('user_id', $userId)
            ->pluck('content')
            ->concat(
                DB::table('forum_posts')
                    ->where('user_id', $userId)
                    ->pluck('content')
            )
            ->implode(' ');
            
        // Simple word frequency analysis
        $words = str_word_count(strtolower($content), 1);
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'and', 'a', 'an', 'as', 'are', 'was', 'were', 'to', 'of', 'for', 'in', 'with'];
        $words = array_diff($words, $stopWords);
        $wordCounts = array_count_values($words);
        arsort($wordCounts);
        
        $wordCloud = [];
        foreach (array_slice($wordCounts, 0, 30) as $word => $count) {
            if (strlen($word) > 3) {
                $wordCloud[] = [
                    'text' => $word,
                    'value' => $count
                ];
            }
        }
        
        return $wordCloud;
    }

    private function calculateInfluenceScore($userId)
    {
        $mentionsReceived = $this->getMentionsReceived($userId);
        $votesReceived = $this->getVotesReceived($userId)['upvotes'];
        $repliesReceived = $this->getRepliesReceived($userId);
        
        return round(($mentionsReceived * 3) + ($votesReceived * 1) + ($repliesReceived * 2));
    }

    private function getNetworkSize($userId)
    {
        return DB::table('mentions')
            ->where('mentioned_by', $userId)
            ->distinct('mentioned_id')
            ->count();
    }

    private function getEngagementRate($userId)
    {
        $totalContent = $this->getTotalContributions($userId);
        $totalEngagement = $this->getVotesReceived($userId)['total'] + 
                          $this->getRepliesReceived($userId) +
                          $this->getMentionsReceived($userId);
                          
        return $totalContent > 0 ? round(($totalEngagement / $totalContent) * 100, 2) : 0;
    }

    private function getResponseRate($userId)
    {
        $mentionsReceived = $this->getMentionsReceived($userId);
        $repliesMade = DB::table('forum_posts')
            ->where('user_id', $userId)
            ->whereNotNull('parent_id')
            ->count();
            
        return $mentionsReceived > 0 ? round(($repliesMade / $mentionsReceived) * 100, 2) : 0;
    }

    private function getMentionReach($userId)
    {
        // Count unique users who have seen content where this user was mentioned
        return DB::table('mentions')
            ->where('mentioned_id', $userId)
            ->where('mentioned_type', 'user')
            ->distinct('mentionable_id')
            ->count() * 10; // Estimated reach multiplier
    }

    private function getCommunityStanding($userId)
    {
        $score = $this->calculateInfluenceScore($userId);
        
        if ($score < 10) return 'Newcomer';
        if ($score < 50) return 'Member';
        if ($score < 100) return 'Regular';
        if ($score < 250) return 'Contributor';
        if ($score < 500) return 'Influencer';
        if ($score < 1000) return 'Leader';
        return 'Legend';
    }

    private function getPredictionAccuracy($userId)
    {
        // Placeholder - would need match predictions table
        return [
            'accuracy' => rand(40, 80),
            'predictions_made' => rand(0, 50),
            'correct_predictions' => rand(0, 40)
        ];
    }

    private function getContentPerformance($userId)
    {
        $totalContent = $this->getTotalContributions($userId);
        $votes = $this->getVotesReceived($userId);
        
        return [
            'avg_performance' => $totalContent > 0 ? round($votes['upvotes'] / $totalContent, 2) : 0,
            'best_performing_type' => 'forum_posts',
            'engagement_trend' => 'increasing'
        ];
    }

    private function getContributionTrends($userId)
    {
        $trends = [];
        
        for ($i = 0; $i < 6; $i++) {
            $month = now()->subMonths($i);
            $trends[] = [
                'month' => $month->format('M'),
                'contributions' => DB::table('news_comments')
                    ->where('user_id', $userId)
                    ->whereMonth('created_at', $month->month)
                    ->whereYear('created_at', $month->year)
                    ->count() +
                    DB::table('forum_posts')
                    ->where('user_id', $userId)
                    ->whereMonth('created_at', $month->month)
                    ->whereYear('created_at', $month->year)
                    ->count()
            ];
        }
        
        return array_reverse($trends);
    }

    private function getExpertiseAreas($userId)
    {
        $areas = [];
        
        // Based on forum categories
        $categories = DB::table('forum_threads')
            ->where('user_id', $userId)
            ->groupBy('category')
            ->selectRaw('category, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->limit(3)
            ->get();
            
        foreach ($categories as $category) {
            $areas[] = [
                'area' => $category->category,
                'score' => min($category->count * 10, 100)
            ];
        }
        
        return $areas;
    }

    private function getImprovementSuggestions($userId)
    {
        $suggestions = [];
        
        $stats = $this->getUserStats($userId);
        
        if ($stats['content']['forum_threads'] < 5) {
            $suggestions[] = 'Create more forum threads to engage the community';
        }
        
        if ($this->getVotesGiven($userId)['total'] < 10) {
            $suggestions[] = 'Vote on more posts to help curate quality content';
        }
        
        if ($this->getMentionsMade($userId) < 5) {
            $suggestions[] = 'Mention other users to build your network';
        }
        
        if (empty($this->getUserFavorites($userId)['teams'])) {
            $suggestions[] = 'Follow some teams to personalize your experience';
        }
        
        return $suggestions;
    }

    /**
     * Update profile view count
     */
    public function trackProfileView(Request $request, $userId)
    {
        $viewer = auth()->user();
        
        if ($viewer && $viewer->id != $userId) {
            DB::table('profile_views')->insert([
                'profile_id' => $userId,
                'viewer_id' => $viewer->id,
                'created_at' => now()
            ]);
        }
        
        return response()->json(['success' => true]);
    }
}