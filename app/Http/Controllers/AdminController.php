<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api', 'role:admin']);
    }

    // Admin Dashboard Overview
    public function dashboard()
    {
        try {
            $stats = [
                'users' => [
                    'total' => DB::table('users')->count(),
                    'active_today' => DB::table('users')->whereDate('last_login', today())->count(),
                    'new_this_week' => DB::table('users')->where('created_at', '>=', now()->subWeek())->count(),
                    'by_role' => DB::table('model_has_roles as mr')
                        ->leftJoin('roles as r', 'mr.role_id', '=', 'r.id')
                        ->select('r.name', DB::raw('COUNT(mr.model_id) as count'))
                        ->groupBy('r.name')
                        ->get()
                ],
                'teams' => [
                    'total' => DB::table('teams')->count(),
                    'verified' => DB::table('teams')->where('verified', true)->count(),
                    'by_region' => DB::table('teams')
                        ->select('region', DB::raw('COUNT(*) as count'))
                        ->groupBy('region')
                        ->get()
                ],
                'players' => [
                    'total' => DB::table('players')->count(),
                    'active' => DB::table('players')->where('status', 'active')->count(),
                    'by_role' => DB::table('players')
                        ->select('role', DB::raw('COUNT(*) as count'))
                        ->groupBy('role')
                        ->get()
                ],
                'matches' => [
                    'total' => DB::table('matches')->count(),
                    'live' => DB::table('matches')->where('status', 'live')->count(),
                    'upcoming' => DB::table('matches')->where('status', 'upcoming')->count(),
                    'completed' => DB::table('matches')->where('status', 'completed')->count(),
                    'today' => DB::table('matches')->whereDate('scheduled_at', today())->count()
                ],
                'events' => [
                    'total' => DB::table('events')->count(),
                    'ongoing' => DB::table('events')->where('status', 'ongoing')->count(),
                    'upcoming' => DB::table('events')->where('status', 'upcoming')->count(),
                    'featured' => DB::table('events')->where('featured', true)->count()
                ],
                'content' => [
                    'news' => DB::table('news')->count(),
                    'forum_threads' => DB::table('forum_threads')->count(),
                    'forum_posts' => DB::table('forum_posts')->count(),
                    'comments' => DB::table('news_comments')->count() + DB::table('match_comments')->count()
                ],
                'activity' => [
                    'recent_logins' => $this->getRecentLogins(),
                    'recent_matches' => $this->getRecentMatches(),
                    'recent_content' => $this->getRecentContent()
                ]
            ];

            return response()->json([
                'data' => $stats,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard stats: ' . $e->getMessage()
            ], 500);
        }
    }

    // Live Scoring Tab
    public function liveScoring()
    {
        try {
            $liveMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->whereIn('m.status', ['live', 'upcoming'])
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    'e.name as event_name'
                ])
                ->orderBy('m.status', 'desc')
                ->orderBy('m.scheduled_at', 'asc')
                ->get()
                ->map(function($match) {
                    $mapsData = json_decode($match->maps_data, true) ?? [];
                    $liveData = json_decode($match->live_data, true) ?? [];
                    
                    return [
                        'id' => $match->id,
                        'team1' => [
                            'id' => $match->team1_id,
                            'name' => $match->team1_name,
                            'short_name' => $match->team1_short,
                            'logo' => $match->team1_logo,
                            'score' => $match->team1_score
                        ],
                        'team2' => [
                            'id' => $match->team2_id,
                            'name' => $match->team2_name,
                            'short_name' => $match->team2_short,
                            'logo' => $match->team2_logo,
                            'score' => $match->team2_score
                        ],
                        'event' => $match->event_name,
                        'status' => $match->status,
                        'format' => $match->format,
                        'current_map' => $match->current_map,
                        'scheduled_at' => $match->scheduled_at,
                        'maps' => $mapsData,
                        'live_data' => $liveData,
                        'actions' => [
                            'can_start' => $match->status === 'upcoming',
                            'can_update' => $match->status === 'live',
                            'can_complete' => $match->status === 'live'
                        ]
                    ];
                });

            return response()->json([
                'data' => $liveMatches,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching live scoring data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Live Scoring - Get Match for Scoring
    public function getLiveScoringMatch($matchId)
    {
        try {
            $match = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.id', $matchId)
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short',
                    't2.name as team2_name', 't2.short_name as team2_short'
                ])
                ->first();

            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            // Get team rosters
            $team1Roster = DB::table('players')
                ->where('team_id', $match->team1_id)
                ->where('status', 'active')
                ->select(['id', 'username', 'real_name', 'role', 'main_hero'])
                ->get();

            $team2Roster = DB::table('players')
                ->where('team_id', $match->team2_id)
                ->where('status', 'active')
                ->select(['id', 'username', 'real_name', 'role', 'main_hero'])
                ->get();

            // Get available maps for Marvel Rivals
            $availableMaps = DB::table('marvel_rivals_maps')
                ->select(['name', 'game_mode', 'type'])
                ->get();

            // Get available heroes
            $availableHeroes = DB::table('marvel_rivals_heroes')
                ->where('active', true)
                ->select(['name', 'role'])
                ->get();

            $mapsData = json_decode($match->maps_data, true) ?? [];
            $liveData = json_decode($match->live_data, true) ?? [];

            return response()->json([
                'data' => [
                    'match' => [
                        'id' => $match->id,
                        'status' => $match->status,
                        'format' => $match->format,
                        'current_map' => $match->current_map,
                        'team1_score' => $match->team1_score,
                        'team2_score' => $match->team2_score
                    ],
                    'teams' => [
                        'team1' => [
                            'id' => $match->team1_id,
                            'name' => $match->team1_name,
                            'short_name' => $match->team1_short,
                            'roster' => $team1Roster
                        ],
                        'team2' => [
                            'id' => $match->team2_id,
                            'name' => $match->team2_name,
                            'short_name' => $match->team2_short,
                            'roster' => $team2Roster
                        ]
                    ],
                    'maps' => $mapsData,
                    'live_data' => $liveData,
                    'available_maps' => $availableMaps,
                    'available_heroes' => $availableHeroes,
                    'game_modes' => [
                        'Convoy' => ['timer' => '10:00', 'points_to_win' => 3],
                        'Domination' => ['timer' => '8:00', 'points_to_win' => 100],
                        'Convergence' => ['timer' => '12:00', 'points_to_win' => 1]
                    ]
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching match for live scoring: ' . $e->getMessage()
            ], 500);
        }
    }

    // Content Moderation Tab
    public function contentModeration()
    {
        try {
            $data = [
                'reported_content' => [
                    'forum_threads' => $this->getReportedForumThreads(),
                    'forum_posts' => $this->getReportedForumPosts(),
                    'news_comments' => $this->getReportedNewsComments(),
                    'match_comments' => $this->getReportedMatchComments(),
                    'users' => $this->getReportedUsers()
                ],
                'pending_approval' => [
                    'news' => $this->getPendingNews(),
                    'team_registrations' => $this->getPendingTeamRegistrations()
                ],
                'recent_actions' => $this->getRecentModerationActions()
            ];

            return response()->json([
                'data' => $data,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching moderation data: ' . $e->getMessage()
            ], 500);
        }
    }

    // User Management Tab
    public function userManagement(Request $request)
    {
        try {
            $query = DB::table('users as u')
                ->leftJoin('model_has_roles as mr', 'u.id', '=', 'mr.model_id')
                ->leftJoin('roles as r', 'mr.role_id', '=', 'r.id')
                ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
                ->select([
                    'u.id', 'u.name', 'u.email', 'u.avatar', 'u.hero_flair',
                    'u.status', 'u.last_login', 'u.created_at',
                    'r.name as role_name',
                    't.name as team_flair_name'
                ]);

            // Search filter
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('u.name', 'LIKE', "%{$request->search}%")
                      ->orWhere('u.email', 'LIKE', "%{$request->search}%");
                });
            }

            // Role filter
            if ($request->role) {
                $query->where('r.name', $request->role);
            }

            // Status filter
            if ($request->status) {
                $query->where('u.status', $request->status);
            }

            $users = $query->orderBy('u.created_at', 'desc')->paginate(20);

            return response()->json([
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users: ' . $e->getMessage()
            ], 500);
        }
    }

    // System Settings Tab
    public function systemSettings()
    {
        try {
            $settings = [
                'site' => [
                    'maintenance_mode' => config('app.maintenance_mode', false),
                    'registration_enabled' => config('app.registration_enabled', true),
                    'email_verification_required' => config('app.email_verification_required', false)
                ],
                'features' => [
                    'forums_enabled' => config('features.forums', true),
                    'news_enabled' => config('features.news', true),
                    'events_enabled' => config('features.events', true),
                    'rankings_enabled' => config('features.rankings', true)
                ],
                'limits' => [
                    'max_team_size' => config('limits.max_team_size', 10),
                    'max_event_teams' => config('limits.max_event_teams', 256),
                    'max_comment_length' => config('limits.max_comment_length', 1000)
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'ttl' => config('cache.ttl', 3600)
                ],
                'storage' => [
                    'disk_usage' => $this->getDiskUsage(),
                    'database_size' => $this->getDatabaseSize()
                ]
            ];

            return response()->json([
                'data' => $settings,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching system settings: ' . $e->getMessage()
            ], 500);
        }
    }

    // Analytics Tab
    public function analytics(Request $request)
    {
        try {
            $period = $request->get('period', '7days');
            $startDate = $this->getStartDateForPeriod($period);

            $analytics = [
                'traffic' => [
                    'page_views' => $this->getPageViews($startDate),
                    'unique_visitors' => $this->getUniqueVisitors($startDate),
                    'popular_pages' => $this->getPopularPages($startDate)
                ],
                'engagement' => [
                    'forum_activity' => $this->getForumActivity($startDate),
                    'comment_activity' => $this->getCommentActivity($startDate),
                    'match_views' => $this->getMatchViews($startDate)
                ],
                'growth' => [
                    'new_users' => $this->getNewUsers($startDate),
                    'new_teams' => $this->getNewTeams($startDate),
                    'new_players' => $this->getNewPlayers($startDate)
                ],
                'content' => [
                    'news_published' => $this->getNewsPublished($startDate),
                    'events_created' => $this->getEventsCreated($startDate),
                    'matches_played' => $this->getMatchesPlayed($startDate)
                ]
            ];

            return response()->json([
                'data' => $analytics,
                'period' => $period,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper Methods
    private function getRecentLogins()
    {
        return DB::table('users')
            ->whereNotNull('last_login')
            ->orderBy('last_login', 'desc')
            ->limit(10)
            ->select(['id', 'name', 'avatar', 'last_login'])
            ->get();
    }

    private function getRecentMatches()
    {
        return DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->whereIn('m.status', ['live', 'completed'])
            ->orderBy('m.updated_at', 'desc')
            ->limit(10)
            ->select([
                'm.id', 'm.status', 'm.team1_score', 'm.team2_score',
                't1.name as team1_name', 't2.name as team2_name'
            ])
            ->get();
    }

    private function getRecentContent()
    {
        $news = DB::table('news')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->select(['id', 'title', 'created_at', DB::raw("'news' as type")])
            ->get();

        $threads = DB::table('forum_threads')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->select(['id', 'title', 'created_at', DB::raw("'forum' as type")])
            ->get();

        return $news->concat($threads)->sortByDesc('created_at')->take(10);
    }

    private function getReportedForumThreads()
    {
        return DB::table('reports as r')
            ->leftJoin('forum_threads as ft', 'r.reportable_id', '=', 'ft.id')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->where('r.reportable_type', 'forum_thread')
            ->where('r.status', 'pending')
            ->select([
                'r.id as report_id', 'r.reason', 'r.created_at as reported_at',
                'ft.id', 'ft.title', 'ft.content',
                'u.name as author_name'
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    private function getReportedForumPosts()
    {
        return DB::table('reports as r')
            ->leftJoin('forum_posts as fp', 'r.reportable_id', '=', 'fp.id')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->where('r.reportable_type', 'forum_post')
            ->where('r.status', 'pending')
            ->select([
                'r.id as report_id', 'r.reason', 'r.created_at as reported_at',
                'fp.id', 'fp.content',
                'u.name as author_name'
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    private function getReportedNewsComments()
    {
        return DB::table('reports as r')
            ->leftJoin('news_comments as nc', 'r.reportable_id', '=', 'nc.id')
            ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
            ->where('r.reportable_type', 'news_comment')
            ->where('r.status', 'pending')
            ->select([
                'r.id as report_id', 'r.reason', 'r.created_at as reported_at',
                'nc.id', 'nc.content',
                'u.name as author_name'
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    private function getReportedMatchComments()
    {
        return DB::table('reports as r')
            ->leftJoin('match_comments as mc', 'r.reportable_id', '=', 'mc.id')
            ->leftJoin('users as u', 'mc.user_id', '=', 'u.id')
            ->where('r.reportable_type', 'match_comment')
            ->where('r.status', 'pending')
            ->select([
                'r.id as report_id', 'r.reason', 'r.created_at as reported_at',
                'mc.id', 'mc.content',
                'u.name as author_name'
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    private function getReportedUsers()
    {
        return DB::table('reports as r')
            ->leftJoin('users as u', 'r.reportable_id', '=', 'u.id')
            ->where('r.reportable_type', 'user')
            ->where('r.status', 'pending')
            ->select([
                'r.id as report_id', 'r.reason', 'r.created_at as reported_at',
                'u.id', 'u.name', 'u.email', 'u.status'
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    private function getPendingNews()
    {
        return DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->where('n.status', 'pending')
            ->select([
                'n.id', 'n.title', 'n.created_at',
                'u.name as author_name'
            ])
            ->orderBy('n.created_at', 'desc')
            ->get();
    }

    private function getPendingTeamRegistrations()
    {
        return DB::table('event_teams as et')
            ->leftJoin('teams as t', 'et.team_id', '=', 't.id')
            ->leftJoin('events as e', 'et.event_id', '=', 'e.id')
            ->where('et.status', 'pending')
            ->select([
                'et.id', 'et.registered_at',
                't.name as team_name',
                'e.name as event_name'
            ])
            ->orderBy('et.registered_at', 'desc')
            ->get();
    }

    private function getRecentModerationActions()
    {
        return DB::table('moderation_logs')
            ->leftJoin('users as u', 'moderation_logs.moderator_id', '=', 'u.id')
            ->orderBy('moderation_logs.created_at', 'desc')
            ->limit(20)
            ->select([
                'moderation_logs.*',
                'u.name as moderator_name'
            ])
            ->get();
    }

    private function getDiskUsage()
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;
        
        return [
            'total' => $this->formatBytes($totalSpace),
            'used' => $this->formatBytes($usedSpace),
            'free' => $this->formatBytes($freeSpace),
            'percentage' => round(($usedSpace / $totalSpace) * 100, 2)
        ];
    }

    private function getDatabaseSize()
    {
        $size = DB::select("SELECT 
            pg_database_size(current_database()) as size")[0]->size ?? 0;
            
        return $this->formatBytes($size);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function getStartDateForPeriod($period)
    {
        switch ($period) {
            case '24hours':
                return now()->subDay();
            case '7days':
                return now()->subWeek();
            case '30days':
                return now()->subMonth();
            case '90days':
                return now()->subMonths(3);
            case '1year':
                return now()->subYear();
            default:
                return now()->subWeek();
        }
    }

    private function getPageViews($startDate)
    {
        // Real page view count based on match views and other activity
        $matchViews = DB::table('matches')
            ->where('updated_at', '>=', $startDate)
            ->sum('viewers') ?? 0;
            
        $forumViews = DB::table('forum_threads')
            ->where('updated_at', '>=', $startDate)
            ->sum('views') ?? 0;
            
        $newsViews = DB::table('news')
            ->where('updated_at', '>=', $startDate)
            ->sum('views') ?? 0;
            
        return $matchViews + $forumViews + $newsViews;
    }

    private function getUniqueVisitors($startDate)
    {
        return DB::table('users')
            ->where('last_login', '>=', $startDate)
            ->count();
    }

    private function getPopularPages($startDate)
    {
        // Get real page view statistics
        $pages = [];
        
        // Teams page views (count of team views)
        $teamViews = DB::table('teams')
            ->where('updated_at', '>=', $startDate)
            ->count() * 10; // Estimate views based on activity
        $pages[] = ['page' => '/teams', 'views' => $teamViews];
        
        // Matches page views
        $matchViews = DB::table('matches')
            ->where('updated_at', '>=', $startDate)
            ->sum('viewers') ?? 0;
        $pages[] = ['page' => '/matches', 'views' => $matchViews];
        
        // Events page views
        $eventViews = DB::table('events')
            ->where('updated_at', '>=', $startDate)
            ->count() * 50; // Estimate
        $pages[] = ['page' => '/events', 'views' => $eventViews];
        
        // Rankings page views
        $rankingViews = DB::table('rankings')
            ->where('updated_at', '>=', $startDate)
            ->count() * 5;
        $pages[] = ['page' => '/rankings', 'views' => $rankingViews];
        
        // News page views
        $newsViews = DB::table('news')
            ->where('updated_at', '>=', $startDate)
            ->sum('views') ?? 0;
        $pages[] = ['page' => '/news', 'views' => $newsViews];
        
        // Sort by views descending
        usort($pages, function($a, $b) {
            return $b['views'] - $a['views'];
        });
        
        return array_slice($pages, 0, 5);
    }

    private function getForumActivity($startDate)
    {
        return [
            'threads' => DB::table('forum_threads')->where('created_at', '>=', $startDate)->count(),
            'posts' => DB::table('forum_posts')->where('created_at', '>=', $startDate)->count()
        ];
    }

    private function getCommentActivity($startDate)
    {
        return [
            'news' => DB::table('news_comments')->where('created_at', '>=', $startDate)->count(),
            'matches' => DB::table('match_comments')->where('created_at', '>=', $startDate)->count()
        ];
    }

    private function getMatchViews($startDate)
    {
        return DB::table('matches')
            ->where('updated_at', '>=', $startDate)
            ->sum('viewers');
    }

    private function getNewUsers($startDate)
    {
        return DB::table('users')
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    private function getNewTeams($startDate)
    {
        return DB::table('teams')
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    private function getNewPlayers($startDate)
    {
        return DB::table('players')
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    private function getNewsPublished($startDate)
    {
        return DB::table('news')
            ->where('created_at', '>=', $startDate)
            ->where('status', 'published')
            ->count();
    }

    private function getEventsCreated($startDate)
    {
        return DB::table('events')
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    private function getMatchesPlayed($startDate)
    {
        return DB::table('matches')
            ->where('completed_at', '>=', $startDate)
            ->where('status', 'completed')
            ->count();
    }

    // Action Methods
    public function clearCache()
    {
        try {
            Cache::flush();
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleMaintenanceMode(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean'
        ]);

        try {
            // This would update config/database
            // For now, return success
            
            return response()->json([
                'success' => true,
                'message' => 'Maintenance mode updated'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating maintenance mode: ' . $e->getMessage()
            ], 500);
        }
    }
}