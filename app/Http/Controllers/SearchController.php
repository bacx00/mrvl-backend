<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $type = $request->get('type', 'all'); // all, teams, players, matches, events, news, forums, users
        $limit = $request->get('limit', 10);
        $page = $request->get('page', 1);
        
        // Allow single character searches with limited results
        if (strlen($query) < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Search query cannot be empty'
            ], 400);
        }
        
        // For single character searches, limit to exact matches and user searches
        if (strlen($query) === 1) {
            $results = $this->searchSingleCharacter($query, $limit);
            return response()->json([
                'data' => $results,
                'query' => $query,
                'type' => $type,
                'success' => true,
                'message' => 'Limited results for single character search'
            ]);
        }

        $results = [];
        
        switch ($type) {
            case 'all':
                $results = $this->searchAll($query, $limit);
                break;
            case 'teams':
                $results = $this->searchTeams($query, $limit, $page);
                break;
            case 'players':
                $results = $this->searchPlayers($query, $limit, $page);
                break;
            case 'matches':
                $results = $this->searchMatches($query, $limit, $page);
                break;
            case 'events':
                $results = $this->searchEvents($query, $limit, $page);
                break;
            case 'news':
                $results = $this->searchNews($query, $limit, $page);
                break;
            case 'forums':
                $results = $this->searchForums($query, $limit, $page);
                break;
            case 'users':
                $results = $this->searchUsers($query, $limit, $page);
                break;
            case 'mentions':
                $results = $this->searchMentions($query, $limit, $page);
                break;
            case 'heroes':
                $results = $this->searchHeroes($query, $limit, $page);
                break;
            default:
                $results = $this->searchAll($query, $limit);
        }

        return response()->json([
            'data' => $results,
            'query' => $query,
            'type' => $type,
            'success' => true
        ]);
    }

    /**
     * Handle single character searches with limited scope
     */
    private function searchSingleCharacter($query, $limit)
    {
        $results = [
            'users' => [],
            'teams' => [],
            'total_results' => 0
        ];
        
        // Search users whose names start with the character
        $users = DB::table('users')
            ->select(['id', 'name', 'avatar', 'hero_flair'])
            ->where('name', 'LIKE', $query . '%')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(5)
            ->get();
            
        foreach ($users as $user) {
            $results['users'][] = [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'hero_flair' => $user->hero_flair,
                'type' => 'user',
                'url' => "/users/{$user->id}"
            ];
        }
        
        // Search team short names that start with the character
        $teams = DB::table('teams')
            ->select(['id', 'name', 'short_name', 'logo', 'region'])
            ->where(function($q) use ($query) {
                $q->where('short_name', 'LIKE', $query . '%')
                  ->orWhere('name', 'LIKE', $query . '%');
            })
            ->orderBy('name')
            ->limit(5)
            ->get();
            
        foreach ($teams as $team) {
            $results['teams'][] = [
                'id' => $team->id,
                'name' => $team->name,
                'short_name' => $team->short_name,
                'logo' => $team->logo,
                'region' => $team->region,
                'type' => 'team',
                'url' => "/teams/{$team->id}"
            ];
        }
        
        $results['total_results'] = count($results['users']) + count($results['teams']);
        
        return $results;
    }

    public function advancedSearch(Request $request)
    {
        $query = $request->get('q', '');
        $filters = $request->get('filters', []);
        
        // Advanced search with multiple filters
        $results = [
            'teams' => [],
            'players' => [],
            'matches' => [],
            'events' => [],
            'news' => [],
            'forums' => [],
            'users' => [],
            'mentions' => [],
            'heroes' => [],
            'stats' => [
                'total_results' => 0,
                'results_by_type' => []
            ]
        ];

        // Apply filters
        $teamFilters = $filters['teams'] ?? [];
        $playerFilters = $filters['players'] ?? [];
        $matchFilters = $filters['matches'] ?? [];
        $eventFilters = $filters['events'] ?? [];
        $newsFilters = $filters['news'] ?? [];
        $forumFilters = $filters['forums'] ?? [];
        $dateRange = $filters['date_range'] ?? null;
        $region = $filters['region'] ?? null;

        // Search each category with filters
        if (!isset($filters['exclude']) || !in_array('teams', $filters['exclude'])) {
            $results['teams'] = $this->searchTeamsAdvanced($query, $teamFilters, $region);
        }
        
        if (!isset($filters['exclude']) || !in_array('players', $filters['exclude'])) {
            $results['players'] = $this->searchPlayersAdvanced($query, $playerFilters, $region);
        }
        
        if (!isset($filters['exclude']) || !in_array('matches', $filters['exclude'])) {
            $results['matches'] = $this->searchMatchesAdvanced($query, $matchFilters, $dateRange);
        }
        
        if (!isset($filters['exclude']) || !in_array('events', $filters['exclude'])) {
            $results['events'] = $this->searchEventsAdvanced($query, $eventFilters, $dateRange);
        }
        
        if (!isset($filters['exclude']) || !in_array('news', $filters['exclude'])) {
            $results['news'] = $this->searchNewsAdvanced($query, $newsFilters, $dateRange);
        }
        
        if (!isset($filters['exclude']) || !in_array('forums', $filters['exclude'])) {
            $results['forums'] = $this->searchForumsAdvanced($query, $forumFilters, $dateRange);
        }
        
        if (!isset($filters['exclude']) || !in_array('users', $filters['exclude'])) {
            $results['users'] = $this->searchUsersAdvanced($query, $region);
        }
        
        if (!isset($filters['exclude']) || !in_array('mentions', $filters['exclude'])) {
            $results['mentions'] = $this->searchMentionsAdvanced($query, $dateRange);
        }
        
        if (!isset($filters['exclude']) || !in_array('heroes', $filters['exclude'])) {
            $results['heroes'] = $this->searchHeroesAdvanced($query, $filters['heroes'] ?? []);
        }

        // Calculate stats
        foreach ($results as $key => $data) {
            if ($key !== 'stats' && is_array($data)) {
                $count = count($data);
                $results['stats']['results_by_type'][$key] = $count;
                $results['stats']['total_results'] += $count;
            }
        }

        return response()->json([
            'data' => $results,
            'query' => $query,
            'filters' => $filters,
            'success' => true
        ]);
    }

    private function searchAll($query, $limit = 5)
    {
        return [
            'teams' => $this->searchTeams($query, $limit),
            'players' => $this->searchPlayers($query, $limit),
            'matches' => $this->searchMatches($query, $limit),
            'events' => $this->searchEvents($query, $limit),
            'news' => $this->searchNews($query, $limit),
            'forums' => $this->searchForums($query, $limit),
            'heroes' => $this->searchHeroes($query, $limit),
            'mentions' => $this->searchMentions($query, $limit),
        ];
    }

    public function searchTeams($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        
        return DB::table('teams as t')
            ->leftJoin('players as p', 't.id', '=', 'p.team_id')
            ->select([
                't.id', 't.name', 't.short_name', 't.logo', 't.region', 't.country',
                't.rating', 't.created_at',
                DB::raw('COUNT(p.id) as player_count'),
                DB::raw('AVG(p.rating) as avg_player_rating')
            ])
            ->where(function($q) use ($query) {
                $q->where('t.name', 'LIKE', "%{$query}%")
                  ->orWhere('t.short_name', 'LIKE', "%{$query}%")
                  ->orWhere('t.description', 'LIKE', "%{$query}%")
                  ->orWhere('t.region', 'LIKE', "%{$query}%")
                  ->orWhere('t.country', 'LIKE', "%{$query}%");
            })
            ->groupBy('t.id', 't.name', 't.short_name', 't.logo', 't.region', 't.country', 't.rating', 't.created_at')
            ->orderByRaw("
                CASE 
                    WHEN t.name LIKE ? THEN 1
                    WHEN t.short_name LIKE ? THEN 2
                    WHEN t.name LIKE ? THEN 3
                    ELSE 4
                END
            ", ["{$query}%", "{$query}%", "%{$query}%"])
            ->orderBy('t.rating', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $team->logo,
                    'region' => $team->region,
                    'country' => $team->country,
                    'rating' => $team->rating,
                    'player_count' => $team->player_count,
                    'avg_player_rating' => round($team->avg_player_rating, 0),
                    'type' => 'team',
                    'url' => "/teams/{$team->id}",
                    'created_at' => $team->created_at
                ];
            });
    }

    public function searchPlayers($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        
        return DB::table('players as p')
            ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
            ->select([
                'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role', 'p.main_hero',
                'p.region', 'p.country', 'p.rating', 'p.peak_rating', 'p.created_at',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ])
            ->where(function($q) use ($query) {
                $q->where('p.username', 'LIKE', "%{$query}%")
                  ->orWhere('p.real_name', 'LIKE', "%{$query}%")
                  ->orWhere('p.main_hero', 'LIKE', "%{$query}%")
                  ->orWhere('p.region', 'LIKE', "%{$query}%")
                  ->orWhere('p.country', 'LIKE', "%{$query}%")
                  ->orWhere('p.role', 'LIKE', "%{$query}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN p.username LIKE ? THEN 1
                    WHEN p.real_name LIKE ? THEN 2
                    WHEN p.username LIKE ? THEN 3
                    ELSE 4
                END
            ", ["{$query}%", "{$query}%", "%{$query}%"])
            ->orderBy('p.rating', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($player) {
                return [
                    'id' => $player->id,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'avatar' => $player->avatar,
                    'role' => $player->role,
                    'main_hero' => $player->main_hero,
                    'region' => $player->region,
                    'country' => $player->country,
                    'rating' => $player->rating,
                    'peak_rating' => $player->peak_rating,
                    'team' => $player->team_name ? [
                        'name' => $player->team_name,
                        'short_name' => $player->team_short,
                        'logo' => $player->team_logo
                    ] : null,
                    'type' => 'player',
                    'url' => "/players/{$player->id}",
                    'created_at' => $player->created_at
                ];
            });
    }

    public function searchMatches($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        
        return DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'm.id', 'm.scheduled_at', 'm.status', 'm.team1_score', 'm.team2_score',
                'm.format', 'm.current_map', 'm.viewers', 'm.created_at',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                'e.name as event_name', 'e.type as event_type'
            ])
            ->where(function($q) use ($query) {
                $q->where('t1.name', 'LIKE', "%{$query}%")
                  ->orWhere('t2.name', 'LIKE', "%{$query}%")
                  ->orWhere('t1.short_name', 'LIKE', "%{$query}%")
                  ->orWhere('t2.short_name', 'LIKE', "%{$query}%")
                  ->orWhere('e.name', 'LIKE', "%{$query}%")
                  ->orWhere('m.current_map', 'LIKE', "%{$query}%")
                  ->orWhere('m.format', 'LIKE', "%{$query}%");
            })
            ->orderBy('m.scheduled_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($match) {
                return [
                    'id' => $match->id,
                    'scheduled_at' => $match->scheduled_at,
                    'status' => $match->status,
                    'format' => $match->format,
                    'current_map' => $match->current_map,
                    'viewers' => $match->viewers,
                    'team1' => [
                        'name' => $match->team1_name,
                        'short_name' => $match->team1_short,
                        'logo' => $match->team1_logo,
                        'score' => $match->team1_score
                    ],
                    'team2' => [
                        'name' => $match->team2_name,
                        'short_name' => $match->team2_short,
                        'logo' => $match->team2_logo,
                        'score' => $match->team2_score
                    ],
                    'event' => $match->event_name ? [
                        'name' => $match->event_name,
                        'type' => $match->event_type
                    ] : null,
                    'type' => 'match',
                    'url' => "/matches/{$match->id}",
                    'created_at' => $match->created_at
                ];
            });
    }

    public function searchEvents($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        
        return DB::table('events as e')
            ->leftJoin('event_teams as et', 'e.id', '=', 'et.event_id')
            ->select([
                'e.id', 'e.name', 'e.type', 'e.status', 'e.start_date', 'e.end_date',
                'e.prize_pool', 'e.team_count', 'e.location', 'e.organizer', 'e.image',
                'e.created_at',
                DB::raw('COUNT(et.team_id) as registered_teams')
            ])
            ->where(function($q) use ($query) {
                $q->where('e.name', 'LIKE', "%{$query}%")
                  ->orWhere('e.type', 'LIKE', "%{$query}%")
                  ->orWhere('e.location', 'LIKE', "%{$query}%")
                  ->orWhere('e.organizer', 'LIKE', "%{$query}%")
                  ->orWhere('e.description', 'LIKE', "%{$query}%");
            })
            ->groupBy('e.id', 'e.name', 'e.type', 'e.status', 'e.start_date', 'e.end_date', 'e.prize_pool', 'e.team_count', 'e.location', 'e.organizer', 'e.image', 'e.created_at')
            ->orderBy('e.start_date', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'type' => $event->type,
                    'status' => $event->status,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'prize_pool' => $event->prize_pool,
                    'team_count' => $event->team_count,
                    'registered_teams' => $event->registered_teams,
                    'location' => $event->location,
                    'organizer' => $event->organizer,
                    'image' => $event->image,
                    'type' => 'event',
                    'url' => "/events/{$event->id}",
                    'created_at' => $event->created_at
                ];
            });
    }

    public function searchNews($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        
        return DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->select([
                'n.id', 'n.title', 'n.content', 'n.excerpt', 'n.featured_image',
                'n.status', 'n.published_at', 'n.views', 'n.created_at',
                'u.name as author_name', 'u.avatar as author_avatar'
            ])
            ->where(function($q) use ($query) {
                $q->where('n.title', 'LIKE', "%{$query}%")
                  ->orWhere('n.content', 'LIKE', "%{$query}%")
                  ->orWhere('n.excerpt', 'LIKE', "%{$query}%")
                  ->orWhere('u.name', 'LIKE', "%{$query}%");
            })
            ->where('n.status', 'published')
            ->orderBy('n.published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($news) {
                return [
                    'id' => $news->id,
                    'title' => $news->title,
                    'excerpt' => $news->excerpt,
                    'featured_image' => $news->featured_image,
                    'status' => $news->status,
                    'published_at' => $news->published_at,
                    'views' => $news->views,
                    'author' => [
                        'name' => $news->author_name,
                        'avatar' => $news->author_avatar
                    ],
                    'type' => 'news',
                    'url' => "/news/{$news->id}",
                    'created_at' => $news->created_at
                ];
            });
    }

    public function searchForums($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        
        $threads = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.upvotes', 'ft.downvotes',
                'ft.pinned', 'ft.locked', 'ft.created_at',
                'u.name as author_name', 'u.avatar as author_avatar', 'u.hero_flair',
                'fc.name as category_name', 'fc.color as category_color'
            ])
            ->where(function($q) use ($query) {
                $q->where('ft.title', 'LIKE', "%{$query}%")
                  ->orWhere('ft.content', 'LIKE', "%{$query}%")
                  ->orWhere('u.name', 'LIKE', "%{$query}%")
                  ->orWhere('fc.name', 'LIKE', "%{$query}%");
            })
            ->orderBy('ft.pinned', 'desc')
            ->orderBy('ft.created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($thread) {
                return [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'content' => substr($thread->content, 0, 200) . '...',
                    'upvotes' => $thread->upvotes,
                    'downvotes' => $thread->downvotes,
                    'is_pinned' => $thread->pinned,
                    'is_locked' => $thread->locked,
                    'author' => [
                        'name' => $thread->author_name,
                        'avatar' => $thread->author_avatar,
                        'hero_flair' => $thread->hero_flair
                    ],
                    'category' => [
                        'name' => $thread->category_name,
                        'color' => $thread->category_color
                    ],
                    'type' => 'forum_thread',
                    'url' => "/forums/threads/{$thread->id}",
                    'created_at' => $thread->created_at
                ];
            });

        $posts = DB::table('forum_posts as fp')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->leftJoin('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
            ->select([
                'fp.id', 'fp.content', 'fp.upvotes', 'fp.downvotes', 'fp.created_at',
                'u.name as author_name', 'u.avatar as author_avatar', 'u.hero_flair',
                'ft.id as thread_id', 'ft.title as thread_title'
            ])
            ->where('fp.content', 'LIKE', "%{$query}%")
            ->orderBy('fp.created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'content' => substr($post->content, 0, 200) . '...',
                    'upvotes' => $post->upvotes,
                    'downvotes' => $post->downvotes,
                    'author' => [
                        'name' => $post->author_name,
                        'avatar' => $post->author_avatar,
                        'hero_flair' => $post->hero_flair
                    ],
                    'thread' => [
                        'id' => $post->thread_id,
                        'title' => $post->thread_title
                    ],
                    'type' => 'forum_post',
                    'url' => "/forums/threads/{$post->thread_id}#{$post->id}",
                    'created_at' => $post->created_at
                ];
            });

        return $threads->merge($posts)->sortByDesc('created_at')->values();
    }

    public function searchUsers($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        
        return DB::table('users as u')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->leftJoin('model_has_roles as mhr', 'u.id', '=', 'mhr.model_id')
            ->leftJoin('roles as r', 'mhr.role_id', '=', 'r.id')
            ->select([
                'u.id', 'u.name', 'u.email', 'u.avatar', 'u.hero_flair',
                'u.show_hero_flair', 'u.show_team_flair', 'u.status', 'u.last_login',
                'u.created_at',
                't.name as team_flair_name', 't.short_name as team_flair_short', 't.logo as team_flair_logo',
                'r.name as role_name'
            ])
            ->where(function($q) use ($query) {
                $q->where('u.name', 'LIKE', "%{$query}%")
                  ->orWhere('u.email', 'LIKE', "%{$query}%")
                  ->orWhere('u.hero_flair', 'LIKE', "%{$query}%");
            })
            ->where('mhr.model_type', 'App\\Models\\User')
            ->groupBy('u.id', 'u.name', 'u.email', 'u.avatar', 'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.status', 'u.last_login', 'u.created_at', 't.name', 't.short_name', 't.logo', 'r.name')
            ->orderBy('u.last_login', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'show_hero_flair' => $user->show_hero_flair,
                    'show_team_flair' => $user->show_team_flair,
                    'status' => $user->status,
                    'last_login' => $user->last_login,
                    'role' => $user->role_name,
                    'team_flair' => $user->team_flair_name ? [
                        'name' => $user->team_flair_name,
                        'short_name' => $user->team_flair_short,
                        'logo' => $user->team_flair_logo
                    ] : null,
                    'type' => 'user',
                    'url' => "/users/{$user->id}",
                    'created_at' => $user->created_at
                ];
            });
    }

    public function searchMentions($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        $mentions = [];

        // Search in forum threads
        $forumMentions = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.mentions', 'ft.created_at',
                'u.name as author_name', 'u.avatar as author_avatar',
                DB::raw("'forum_thread' as source_type")
            ])
            ->whereNotNull('ft.mentions')
            ->where('ft.mentions', 'LIKE', "%{$query}%")
            ->get();

        // Search in forum posts
        $postMentions = DB::table('forum_posts as fp')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->leftJoin('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
            ->select([
                'fp.id', 'fp.content', 'fp.mentions', 'fp.created_at',
                'u.name as author_name', 'u.avatar as author_avatar',
                'ft.title as thread_title',
                DB::raw("'forum_post' as source_type")
            ])
            ->whereNotNull('fp.mentions')
            ->where('fp.mentions', 'LIKE', "%{$query}%")
            ->get();

        // Search in news comments
        $newsCommentMentions = DB::table('news_comments as nc')
            ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
            ->leftJoin('news as n', 'nc.news_id', '=', 'n.id')
            ->select([
                'nc.id', 'nc.content', 'nc.mentions', 'nc.created_at',
                'u.name as author_name', 'u.avatar as author_avatar',
                'n.title as news_title',
                DB::raw("'news_comment' as source_type")
            ])
            ->whereNotNull('nc.mentions')
            ->where('nc.mentions', 'LIKE', "%{$query}%")
            ->get();

        // Combine and format all mentions
        $allMentions = $forumMentions->merge($postMentions)->merge($newsCommentMentions);

        return $allMentions->map(function($mention) use ($query) {
            $mentionsArray = json_decode($mention->mentions, true) ?? [];
            $relevantMentions = array_filter($mentionsArray, function($m) use ($query) {
                return stripos($m['text'] ?? '', $query) !== false;
            });

            return [
                'id' => $mention->id,
                'content' => isset($mention->title) ? $mention->title : substr($mention->content, 0, 200) . '...',
                'mentions' => $relevantMentions,
                'source_type' => $mention->source_type,
                'author' => [
                    'name' => $mention->author_name,
                    'avatar' => $mention->author_avatar
                ],
                'context' => [
                    'thread_title' => $mention->thread_title ?? null,
                    'news_title' => $mention->news_title ?? null
                ],
                'type' => 'mention',
                'created_at' => $mention->created_at
            ];
        })->sortByDesc('created_at')->slice($offset, $limit)->values();
    }

    public function searchHeroes($query, $limit = 10, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        
        return DB::table('marvel_rivals_heroes as h')
            ->select([
                'h.id', 'h.name', 'h.slug', 'h.role', 'h.description', 'h.lore',
                'h.season_added', 'h.is_new', 'h.difficulty', 'h.usage_rate',
                'h.win_rate', 'h.pick_rate', 'h.ban_rate', 'h.active',
                'h.created_at'
            ])
            ->where(function($q) use ($query) {
                $q->where('h.name', 'LIKE', "%{$query}%")
                  ->orWhere('h.role', 'LIKE', "%{$query}%")
                  ->orWhere('h.description', 'LIKE', "%{$query}%")
                  ->orWhere('h.lore', 'LIKE', "%{$query}%")
                  ->orWhere('h.season_added', 'LIKE', "%{$query}%");
            })
            ->where('h.active', true)
            ->orderByRaw("
                CASE 
                    WHEN h.name LIKE ? THEN 1
                    WHEN h.role LIKE ? THEN 2
                    ELSE 3
                END
            ", ["{$query}%", "{$query}%"])
            ->orderBy('h.usage_rate', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function($hero) {
                return [
                    'id' => $hero->id,
                    'name' => $hero->name,
                    'slug' => $hero->slug,
                    'role' => $hero->role,
                    'description' => $hero->description,
                    'season_added' => $hero->season_added,
                    'is_new' => $hero->is_new,
                    'difficulty' => $hero->difficulty,
                    'stats' => [
                        'usage_rate' => $hero->usage_rate,
                        'win_rate' => $hero->win_rate,
                        'pick_rate' => $hero->pick_rate,
                        'ban_rate' => $hero->ban_rate
                    ],
                    'images' => [
                        'portrait' => "/images/heroes/portraits/{$hero->slug}.png",
                        'icon' => "/images/heroes/icons/{$hero->slug}.png"
                    ],
                    'fallback' => [
                        'text' => $hero->name,
                        'color' => $this->getHeroColor($hero->name)
                    ],
                    'type' => 'hero',
                    'url' => "/heroes/{$hero->slug}",
                    'created_at' => $hero->created_at
                ];
            });
    }

    // Advanced search methods with filters
    private function searchTeamsAdvanced($query, $filters, $region)
    {
        $queryBuilder = DB::table('teams as t')
            ->leftJoin('players as p', 't.id', '=', 'p.team_id')
            ->select([
                't.id', 't.name', 't.short_name', 't.logo', 't.region', 't.country',
                't.rating', 't.created_at',
                DB::raw('COUNT(p.id) as player_count'),
                DB::raw('AVG(p.rating) as avg_player_rating')
            ]);

        if ($query) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('t.name', 'LIKE', "%{$query}%")
                  ->orWhere('t.short_name', 'LIKE', "%{$query}%");
            });
        }

        if ($region) {
            $queryBuilder->where('t.region', $region);
        }

        if (isset($filters['min_rating'])) {
            $queryBuilder->where('t.rating', '>=', $filters['min_rating']);
        }

        if (isset($filters['max_rating'])) {
            $queryBuilder->where('t.rating', '<=', $filters['max_rating']);
        }

        if (isset($filters['country'])) {
            $queryBuilder->where('t.country', $filters['country']);
        }

        return $queryBuilder->groupBy('t.id', 't.name', 't.short_name', 't.logo', 't.region', 't.country', 't.rating', 't.created_at')
            ->orderBy('t.rating', 'desc')
            ->limit(20)
            ->get()
            ->map(function($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $team->logo,
                    'region' => $team->region,
                    'country' => $team->country,
                    'rating' => $team->rating,
                    'player_count' => $team->player_count,
                    'avg_player_rating' => round($team->avg_player_rating, 0),
                    'type' => 'team'
                ];
            });
    }

    private function searchPlayersAdvanced($query, $filters, $region)
    {
        $queryBuilder = DB::table('players as p')
            ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
            ->select([
                'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role', 'p.main_hero',
                'p.region', 'p.country', 'p.rating', 'p.peak_rating',
                't.name as team_name', 't.short_name as team_short'
            ]);

        if ($query) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('p.username', 'LIKE', "%{$query}%")
                  ->orWhere('p.real_name', 'LIKE', "%{$query}%");
            });
        }

        if ($region) {
            $queryBuilder->where('p.region', $region);
        }

        if (isset($filters['role'])) {
            $queryBuilder->where('p.role', $filters['role']);
        }

        if (isset($filters['hero'])) {
            $queryBuilder->where('p.main_hero', $filters['hero']);
        }

        if (isset($filters['min_rating'])) {
            $queryBuilder->where('p.rating', '>=', $filters['min_rating']);
        }

        if (isset($filters['team_id'])) {
            $queryBuilder->where('p.team_id', $filters['team_id']);
        }

        return $queryBuilder->orderBy('p.rating', 'desc')
            ->limit(20)
            ->get()
            ->map(function($player) {
                return [
                    'id' => $player->id,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'avatar' => $player->avatar,
                    'role' => $player->role,
                    'main_hero' => $player->main_hero,
                    'region' => $player->region,
                    'country' => $player->country,
                    'rating' => $player->rating,
                    'peak_rating' => $player->peak_rating,
                    'team' => $player->team_name ? [
                        'name' => $player->team_name,
                        'short_name' => $player->team_short
                    ] : null,
                    'type' => 'player'
                ];
            });
    }

    private function searchMatchesAdvanced($query, $filters, $dateRange)
    {
        $queryBuilder = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'm.id', 'm.scheduled_at', 'm.status', 'm.team1_score', 'm.team2_score',
                'm.format', 't1.name as team1_name', 't2.name as team2_name',
                'e.name as event_name'
            ]);

        if ($query) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('t1.name', 'LIKE', "%{$query}%")
                  ->orWhere('t2.name', 'LIKE', "%{$query}%")
                  ->orWhere('e.name', 'LIKE', "%{$query}%");
            });
        }

        if (isset($filters['status'])) {
            $queryBuilder->where('m.status', $filters['status']);
        }

        if (isset($filters['format'])) {
            $queryBuilder->where('m.format', $filters['format']);
        }

        if ($dateRange) {
            if (isset($dateRange['start'])) {
                $queryBuilder->where('m.scheduled_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end'])) {
                $queryBuilder->where('m.scheduled_at', '<=', $dateRange['end']);
            }
        }

        return $queryBuilder->orderBy('m.scheduled_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($match) {
                return [
                    'id' => $match->id,
                    'scheduled_at' => $match->scheduled_at,
                    'status' => $match->status,
                    'format' => $match->format,
                    'team1' => ['name' => $match->team1_name, 'score' => $match->team1_score],
                    'team2' => ['name' => $match->team2_name, 'score' => $match->team2_score],
                    'event' => ['name' => $match->event_name],
                    'type' => 'match'
                ];
            });
    }

    private function searchEventsAdvanced($query, $filters, $dateRange)
    {
        $queryBuilder = DB::table('events as e')
            ->select(['e.id', 'e.name', 'e.type', 'e.status', 'e.start_date', 'e.prize_pool']);

        if ($query) {
            $queryBuilder->where('e.name', 'LIKE', "%{$query}%");
        }

        if (isset($filters['type'])) {
            $queryBuilder->where('e.type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $queryBuilder->where('e.status', $filters['status']);
        }

        if ($dateRange) {
            if (isset($dateRange['start'])) {
                $queryBuilder->where('e.start_date', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end'])) {
                $queryBuilder->where('e.end_date', '<=', $dateRange['end']);
            }
        }

        return $queryBuilder->orderBy('e.start_date', 'desc')
            ->limit(20)
            ->get()
            ->map(function($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'type' => $event->type,
                    'status' => $event->status,
                    'start_date' => $event->start_date,
                    'prize_pool' => $event->prize_pool,
                    'type' => 'event'
                ];
            });
    }

    private function searchNewsAdvanced($query, $filters, $dateRange)
    {
        $queryBuilder = DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->select(['n.id', 'n.title', 'n.excerpt', 'n.published_at', 'u.name as author_name']);

        if ($query) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('n.title', 'LIKE', "%{$query}%")
                  ->orWhere('n.content', 'LIKE', "%{$query}%");
            });
        }

        $queryBuilder->where('n.status', 'published');

        if (isset($filters['author_id'])) {
            $queryBuilder->where('n.author_id', $filters['author_id']);
        }

        if ($dateRange) {
            if (isset($dateRange['start'])) {
                $queryBuilder->where('n.published_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end'])) {
                $queryBuilder->where('n.published_at', '<=', $dateRange['end']);
            }
        }

        return $queryBuilder->orderBy('n.published_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($news) {
                return [
                    'id' => $news->id,
                    'title' => $news->title,
                    'excerpt' => $news->excerpt,
                    'published_at' => $news->published_at,
                    'author' => ['name' => $news->author_name],
                    'type' => 'news'
                ];
            });
    }

    private function searchForumsAdvanced($query, $filters, $dateRange)
    {
        $queryBuilder = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->select(['ft.id', 'ft.title', 'ft.upvotes', 'ft.downvotes', 'ft.created_at', 'u.name as author_name', 'fc.name as category_name']);

        if ($query) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('ft.title', 'LIKE', "%{$query}%")
                  ->orWhere('ft.content', 'LIKE', "%{$query}%");
            });
        }

        if (isset($filters['category_id'])) {
            $queryBuilder->where('ft.category_id', $filters['category_id']);
        }

        if (isset($filters['is_pinned'])) {
            $queryBuilder->where('ft.pinned', $filters['is_pinned']);
        }

        if ($dateRange) {
            if (isset($dateRange['start'])) {
                $queryBuilder->where('ft.created_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end'])) {
                $queryBuilder->where('ft.created_at', '<=', $dateRange['end']);
            }
        }

        return $queryBuilder->orderBy('ft.created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($thread) {
                return [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'upvotes' => $thread->upvotes,
                    'downvotes' => $thread->downvotes,
                    'author' => ['name' => $thread->author_name],
                    'category' => ['name' => $thread->category_name],
                    'created_at' => $thread->created_at,
                    'type' => 'forum_thread'
                ];
            });
    }

    private function searchUsersAdvanced($query, $region)
    {
        $queryBuilder = DB::table('users as u')
            ->select(['u.id', 'u.name', 'u.avatar', 'u.hero_flair', 'u.status']);

        if ($query) {
            $queryBuilder->where('u.name', 'LIKE', "%{$query}%");
        }

        $queryBuilder->where('u.status', 'active');

        return $queryBuilder->orderBy('u.last_login', 'desc')
            ->limit(20)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'status' => $user->status,
                    'type' => 'user'
                ];
            });
    }

    private function searchMentionsAdvanced($query, $dateRange)
    {
        // Similar to regular mentions but with date filtering
        return $this->searchMentions($query);
    }

    private function searchHeroesAdvanced($query, $filters)
    {
        $queryBuilder = DB::table('marvel_rivals_heroes as h')
            ->select(['h.id', 'h.name', 'h.role', 'h.usage_rate', 'h.win_rate'])
            ->where('h.active', true);

        if ($query) {
            $queryBuilder->where('h.name', 'LIKE', "%{$query}%");
        }

        if (isset($filters['role'])) {
            $queryBuilder->where('h.role', $filters['role']);
        }

        if (isset($filters['season'])) {
            $queryBuilder->where('h.season_added', $filters['season']);
        }

        return $queryBuilder->orderBy('h.usage_rate', 'desc')
            ->limit(20)
            ->get()
            ->map(function($hero) {
                return [
                    'id' => $hero->id,
                    'name' => $hero->name,
                    'role' => $hero->role,
                    'usage_rate' => $hero->usage_rate,
                    'win_rate' => $hero->win_rate,
                    'type' => 'hero'
                ];
            });
    }

    private function getHeroColor($heroName)
    {
        $colors = [
            'Spider-Man' => '#dc2626',
            'Iron Man' => '#f59e0b',
            'Captain America' => '#2563eb',
            'Thor' => '#7c3aed',
            'Hulk' => '#16a34a',
            'Black Widow' => '#000000',
            'Hawkeye' => '#7c2d12',
            'Doctor Strange' => '#db2777',
            'Scarlet Witch' => '#dc2626',
            'Loki' => '#16a34a',
            'Venom' => '#000000',
            'Magneto' => '#7c3aed',
            'Storm' => '#6b7280',
            'Wolverine' => '#f59e0b'
        ];
        
        return $colors[$heroName] ?? '#6b7280';
    }

    /**
     * Search users for mention purposes (public access)
     * This endpoint is specifically for autocomplete in mention systems
     */
    public function searchUsersForMentions(Request $request)
    {
        try {
            $validated = $request->validate([
                'q' => 'nullable|string|max:100',
                'limit' => 'nullable|integer|min:1|max:20'
            ]);

            $query = $validated['q'] ?? '';
            $limit = min($validated['limit'] ?? 10, 20);

            if (strlen($query) < 1) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'query' => $query,
                    'message' => 'Query too short for user search'
                ]);
            }

            // Search active users only
            $users = DB::table('users')
                ->where('name', 'LIKE', "%{$query}%")
                ->where('status', 'active')
                ->select(['id', 'name', 'avatar', 'hero_flair'])
                ->orderByRaw("
                    CASE 
                        WHEN name LIKE ? THEN 1
                        ELSE 2
                    END
                ", ["{$query}%"])
                ->orderBy('name')
                ->limit($limit)
                ->get();

            $results = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'type' => 'user',
                    'mention_text' => "@{$user->name}",
                    'display_name' => $user->name,
                    'subtitle' => 'User',
                    'url' => "/users/{$user->id}"
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $results,
                'query' => $query,
                'total_results' => count($results)
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('SearchController@searchUsersForMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching users for mentions',
                'data' => []
            ], 500);
        }
    }

    /**
     * Search teams for mention purposes (public access)
     * This endpoint is specifically for autocomplete in mention systems
     */
    public function searchTeamsForMentions(Request $request)
    {
        try {
            $validated = $request->validate([
                'q' => 'nullable|string|max:100',
                'limit' => 'nullable|integer|min:1|max:20'
            ]);

            $query = $validated['q'] ?? '';
            $limit = min($validated['limit'] ?? 10, 20);

            if (strlen($query) < 1) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'query' => $query,
                    'message' => 'Query too short for team search'
                ]);
            }

            // Search teams
            $teams = DB::table('teams')
                ->where(function($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('short_name', 'LIKE', "%{$query}%");
                })
                ->select(['id', 'name', 'short_name', 'logo', 'region', 'country'])
                ->orderByRaw("
                    CASE 
                        WHEN name LIKE ? THEN 1
                        WHEN short_name LIKE ? THEN 2
                        ELSE 3
                    END
                ", ["{$query}%", "{$query}%"])
                ->orderBy('name')
                ->limit($limit)
                ->get();

            $results = $teams->map(function($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $team->logo,
                    'region' => $team->region,
                    'country' => $team->country,
                    'type' => 'team',
                    'mention_text' => "@team:{$team->short_name}",
                    'display_name' => $team->name,
                    'subtitle' => "Team • {$team->region}",
                    'url' => "/teams/{$team->id}"
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $results,
                'query' => $query,
                'total_results' => count($results)
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('SearchController@searchTeamsForMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching teams for mentions',
                'data' => []
            ], 500);
        }
    }

    /**
     * Search players for mention purposes (public access)
     * This endpoint is specifically for autocomplete in mention systems
     */
    public function searchPlayersForMentions(Request $request)
    {
        try {
            $validated = $request->validate([
                'q' => 'nullable|string|max:100',
                'limit' => 'nullable|integer|min:1|max:20'
            ]);

            $query = $validated['q'] ?? '';
            $limit = min($validated['limit'] ?? 10, 20);

            if (strlen($query) < 1) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'query' => $query,
                    'message' => 'Query too short for player search'
                ]);
            }

            // Search players
            $players = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->where(function($q) use ($query) {
                    $q->where('p.username', 'LIKE', "%{$query}%")
                      ->orWhere('p.real_name', 'LIKE', "%{$query}%");
                })
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role',
                    't.name as team_name', 't.short_name as team_short'
                ])
                ->orderByRaw("
                    CASE 
                        WHEN p.username LIKE ? THEN 1
                        WHEN p.real_name LIKE ? THEN 2
                        ELSE 3
                    END
                ", ["{$query}%", "{$query}%"])
                ->orderBy('p.username')
                ->limit($limit)
                ->get();

            $results = $players->map(function($player) {
                $subtitle = $player->role;
                if ($player->team_name) {
                    $subtitle .= " • {$player->team_name}";
                }

                return [
                    'id' => $player->id,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'avatar' => $player->avatar,
                    'role' => $player->role,
                    'team_name' => $player->team_name,
                    'team_short' => $player->team_short,
                    'type' => 'player',
                    'mention_text' => "@player:{$player->username}",
                    'display_name' => $player->real_name ?: $player->username,
                    'subtitle' => $subtitle,
                    'url' => "/players/{$player->id}"
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $results,
                'query' => $query,
                'total_results' => count($results)
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('SearchController@searchPlayersForMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching players for mentions',
                'data' => []
            ], 500);
        }
    }
    
    /**
     * Get search suggestions for forum search
     */
    public function searchSuggestions(Request $request)
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 10), 20);
        
        if (strlen($query) < 1) {
            // Return popular search terms when no query
            $popular = ['tips', 'strategy', 'guide', 'meta', 'tournament', 'patch', 'heroes', 'teams'];
            return response()->json([
                'data' => array_map(function($term) {
                    return [
                        'text' => $term,
                        'type' => 'popular_term'
                    ];
                }, $popular),
                'success' => true
            ]);
        }
        
        $suggestions = [];
        
        // Get matching thread titles
        $threadTitles = DB::table('forum_threads')
            ->select('title')
            ->where('title', 'LIKE', "%{$query}%")
            ->where('status', 'active')
            ->orderBy('score', 'desc')
            ->limit($limit / 2)
            ->pluck('title');
            
        foreach ($threadTitles as $title) {
            $suggestions[] = [
                'text' => $title,
                'type' => 'thread_title',
                'category' => 'forums'
            ];
        }
        
        // Get matching usernames
        $usernames = DB::table('users')
            ->select('name')
            ->where('name', 'LIKE', "%{$query}%")
            ->where('status', 'active')
            ->orderBy('name')
            ->limit($limit / 2)
            ->pluck('name');
            
        foreach ($usernames as $username) {
            $suggestions[] = [
                'text' => $username,
                'type' => 'username',
                'category' => 'users'
            ];
        }
        
        // Get matching team names
        if (strlen($query) > 1) {
            $teams = DB::table('teams')
                ->select(['name', 'short_name'])
                ->where(function($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('short_name', 'LIKE', "%{$query}%");
                })
                ->limit(3)
                ->get();
                
            foreach ($teams as $team) {
                $suggestions[] = [
                    'text' => $team->name,
                    'type' => 'team_name',
                    'category' => 'teams'
                ];
            }
        }
        
        return response()->json([
            'data' => $suggestions,
            'query' => $query,
            'success' => true
        ]);
    }
}