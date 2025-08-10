<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class OptimizedForumQueryService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const HOT_CACHE_TTL = 60; // 1 minute for hot content

    /**
     * Get forum threads with optimized queries to prevent N+1 problems
     */
    public function getThreadsOptimized(array $params = [])
    {
        $category = $params['category'] ?? 'all';
        $sortBy = $params['sort'] ?? 'latest';
        $page = $params['page'] ?? 1;
        $perPage = min($params['per_page'] ?? 20, 50);
        $search = $params['search'] ?? null;

        // Use hot view for hot sorting to improve performance
        if ($sortBy === 'hot') {
            return $this->getHotThreadsFromView($page, $perPage, $category, $search);
        }

        // Build optimized query with single JOIN operation
        $query = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->select([
                // Thread data
                'ft.id', 'ft.title', 'ft.content', 'ft.user_id', 
                'ft.replies_count', 'ft.views', 'ft.score', 'ft.upvotes', 'ft.downvotes',
                'ft.pinned', 'ft.locked', 'ft.created_at', 'ft.last_reply_at',
                // User data (avoid N+1)
                'u.name as author_name', 'u.avatar as author_avatar', 
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                // Team flair data (avoid N+1)
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo',
                // Category data (avoid N+1)
                'fc.name as category_name', 'fc.color as category_color', 'fc.slug as category_slug'
            ])
            ->where('ft.status', 'active');

        // Apply category filter with optimized index usage
        if ($category && $category !== 'all') {
            if (is_numeric($category)) {
                $query->where('ft.category_id', $category);
            } else {
                $query->where('fc.slug', $category);
            }
        }

        // Apply search with full-text when available
        if ($search) {
            if (DB::getDriverName() === 'mysql') {
                $query->whereRaw("MATCH(ft.title, ft.content) AGAINST(? IN BOOLEAN MODE)", [$search . '*']);
            } else {
                $query->where(function($q) use ($search) {
                    $q->where('ft.title', 'LIKE', "%{$search}%")
                      ->orWhere('ft.content', 'LIKE', "%{$search}%");
                });
            }
        }

        // Apply sorting with optimized indexes
        switch ($sortBy) {
            case 'popular':
                $query->orderBy('ft.pinned', 'desc')
                      ->orderBy('ft.score', 'desc')
                      ->orderBy('ft.created_at', 'desc');
                break;
            case 'trending':
                $query->where('ft.created_at', '>=', now()->subDays(7))
                      ->orderBy('ft.pinned', 'desc')
                      ->orderByRaw('(ft.views * 0.1 + ft.replies_count * 2 + ft.score * 1.5) DESC')
                      ->orderBy('ft.created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('ft.pinned', 'desc')
                      ->orderBy('ft.created_at', 'asc');
                break;
            default: // latest
                $query->orderBy('ft.pinned', 'desc')
                      ->orderBy('ft.last_reply_at', 'desc');
        }

        // Execute paginated query
        $threads = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform data to avoid additional queries
        $threadsData = collect($threads->items())->map(function($thread) {
            return $this->transformThreadData($thread);
        });

        return [
            'data' => $threadsData,
            'pagination' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total()
            ]
        ];
    }

    /**
     * Get single thread with all posts optimized for performance
     */
    public function getThreadWithPostsOptimized($threadId, $userId = null)
    {
        // Get thread data with user info in single query
        $thread = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('ft.id', $threadId)
            ->where('ft.status', 'active')
            ->select([
                'ft.*',
                'u.name as author_name', 'u.avatar as author_avatar',
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo',
                'fc.name as category_name', 'fc.color as category_color'
            ])
            ->first();

        if (!$thread) {
            return null;
        }

        // Get all posts with user data in single optimized query
        $posts = $this->getThreadPostsOptimized($threadId, $userId);

        // Get user vote if authenticated
        $userVote = null;
        if ($userId) {
            $userVote = DB::table('forum_votes')
                ->where('thread_id', $threadId)
                ->where('user_id', $userId)
                ->whereNull('post_id')
                ->value('vote_type');
        }

        return [
            'thread' => $this->transformThreadData($thread),
            'posts' => $posts,
            'user_vote' => $userVote
        ];
    }

    /**
     * Get thread posts with nested structure optimized
     */
    public function getThreadPostsOptimized($threadId, $userId = null)
    {
        // Get all posts with user and team data in single query
        $posts = DB::table('forum_posts as fp')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('fp.thread_id', $threadId)
            ->where('fp.status', 'active')
            ->select([
                'fp.*',
                'u.name as author_name', 'u.avatar as author_avatar',
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ])
            ->orderBy('fp.created_at', 'asc')
            ->get();

        // Get user votes for all posts in single query if authenticated
        $userVotes = [];
        if ($userId && $posts->count() > 0) {
            $postIds = $posts->pluck('id');
            $userVotes = DB::table('forum_votes')
                ->whereIn('post_id', $postIds)
                ->where('user_id', $userId)
                ->pluck('vote_type', 'post_id')
                ->toArray();
        }

        // Build nested structure efficiently
        $postMap = [];
        $topLevelPosts = [];

        foreach ($posts as $post) {
            $postData = [
                'id' => $post->id,
                'content' => $post->content,
                'author' => $this->transformUserData($post),
                'stats' => [
                    'score' => $post->score ?? 0,
                    'upvotes' => $post->upvotes ?? 0,
                    'downvotes' => $post->downvotes ?? 0
                ],
                'meta' => [
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                    'edited' => (bool)$post->is_edited,
                    'edited_at' => $post->edited_at
                ],
                'user_vote' => $userVotes[$post->id] ?? null,
                'parent_id' => $post->parent_id,
                'replies' => []
            ];

            $postMap[$post->id] = $postData;

            if ($post->parent_id) {
                // This is a reply - will be added to parent later
                continue;
            } else {
                // This is a top-level post
                $topLevelPosts[] = &$postMap[$post->id];
            }
        }

        // Add replies to their parents
        foreach ($posts as $post) {
            if ($post->parent_id && isset($postMap[$post->parent_id])) {
                $postMap[$post->parent_id]['replies'][] = &$postMap[$post->id];
            }
        }

        return array_values($topLevelPosts);
    }

    /**
     * Get hot threads from materialized view for better performance
     */
    private function getHotThreadsFromView($page, $perPage, $category = 'all', $search = null)
    {
        $query = DB::table('hot_forum_threads');

        // Apply category filter
        if ($category && $category !== 'all') {
            if (is_numeric($category)) {
                $query->where('category_id', $category);
            } else {
                $query->where('category_name', $category);
            }
        }

        // Apply search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%");
            });
        }

        $threads = $query->orderBy('pinned', 'desc')
                        ->orderBy('hot_score', 'desc')
                        ->paginate($perPage, ['*'], 'page', $page);

        $threadsData = collect($threads->items())->map(function($thread) {
            return $this->transformHotThreadData($thread);
        });

        return [
            'data' => $threadsData,
            'pagination' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total()
            ]
        ];
    }

    /**
     * Transform thread data to standardized format
     */
    private function transformThreadData($thread)
    {
        return [
            'id' => $thread->id,
            'title' => $thread->title,
            'content' => $thread->content,
            'author' => $this->transformUserData($thread),
            'category' => [
                'id' => $thread->category_id ?? null,
                'name' => $thread->category_name ?? 'General',
                'slug' => $thread->category_slug ?? 'general',
                'color' => $thread->category_color ?? '#6b7280'
            ],
            'stats' => [
                'views' => (int)($thread->views ?? 0),
                'replies' => (int)($thread->replies_count ?? 0),
                'score' => (int)($thread->score ?? 0),
                'upvotes' => (int)($thread->upvotes ?? 0),
                'downvotes' => (int)($thread->downvotes ?? 0)
            ],
            'meta' => [
                'pinned' => (bool)$thread->pinned,
                'locked' => (bool)$thread->locked,
                'created_at' => $thread->created_at,
                'created_at_formatted' => \Carbon\Carbon::parse($thread->created_at)->format('M j, Y g:i A'),
                'created_at_relative' => \Carbon\Carbon::parse($thread->created_at)->diffForHumans(),
                'last_reply_at' => $thread->last_reply_at,
                'last_reply_at_formatted' => $thread->last_reply_at ? \Carbon\Carbon::parse($thread->last_reply_at)->format('M j, Y g:i A') : null,
                'last_reply_at_relative' => $thread->last_reply_at ? \Carbon\Carbon::parse($thread->last_reply_at)->diffForHumans() : null
            ]
        ];
    }

    /**
     * Transform hot thread data (from view)
     */
    private function transformHotThreadData($thread)
    {
        return [
            'id' => $thread->id,
            'title' => $thread->title,
            'content' => substr($thread->content, 0, 200) . (strlen($thread->content) > 200 ? '...' : ''),
            'author' => [
                'id' => $thread->user_id,
                'name' => $thread->author_name,
                'avatar' => $thread->author_avatar
            ],
            'category' => [
                'name' => $thread->category_name ?? 'General',
                'color' => $thread->category_color ?? '#6b7280'
            ],
            'stats' => [
                'views' => (int)($thread->views ?? 0),
                'replies' => (int)($thread->replies_count ?? 0),
                'score' => (int)($thread->score ?? 0),
                'upvotes' => (int)($thread->upvotes ?? 0),
                'downvotes' => (int)($thread->downvotes ?? 0),
                'hot_score' => round($thread->hot_score ?? 0, 2)
            ],
            'meta' => [
                'pinned' => (bool)$thread->pinned,
                'locked' => (bool)$thread->locked,
                'created_at' => $thread->created_at,
                'last_reply_at' => $thread->last_reply_at,
                'is_hot' => true
            ]
        ];
    }

    /**
     * Transform user data with flairs
     */
    private function transformUserData($userData)
    {
        $flairs = [];
        
        // Add hero flair if enabled
        if ($userData->show_hero_flair && $userData->hero_flair) {
            $flairs['hero'] = [
                'type' => 'hero',
                'name' => $userData->hero_flair,
                'image' => $this->getHeroImagePath($userData->hero_flair),
                'fallback_text' => $userData->hero_flair
            ];
        }
        
        // Add team flair if enabled
        if ($userData->show_team_flair && $userData->team_name) {
            $flairs['team'] = [
                'type' => 'team',
                'name' => $userData->team_name,
                'short_name' => $userData->team_short,
                'image' => $userData->team_logo ? asset('storage/' . $userData->team_logo) : null,
                'fallback_text' => $userData->team_short
            ];
        }

        // Determine avatar
        $avatar = $userData->avatar;
        if ($userData->use_hero_as_avatar && $userData->hero_flair) {
            $avatar = $this->getHeroImagePath($userData->hero_flair);
        } else if ($userData->avatar) {
            $avatar = str_starts_with($userData->avatar, 'http') 
                ? $userData->avatar 
                : asset('storage/avatars/' . $userData->avatar);
        }

        return [
            'id' => $userData->user_id,
            'name' => $userData->author_name,
            'username' => $userData->author_name,
            'avatar' => $avatar,
            'flairs' => $flairs,
            'use_hero_as_avatar' => (bool)$userData->use_hero_as_avatar
        ];
    }

    /**
     * Get hero image path with fallback
     */
    private function getHeroImagePath($heroName)
    {
        $heroSlug = str_replace([' ', '&', '.', "'"], ['-', 'and', '', ''], strtolower($heroName));
        $possiblePaths = [
            "/images/heroes/{$heroSlug}-headbig.webp",
            "/images/heroes/{$heroSlug}.png",
            "/images/heroes/{$heroSlug}.webp",
            "/images/heroes/{$heroSlug}.jpg"
        ];

        foreach ($possiblePaths as $path) {
            $fullPath = public_path($path);
            if (file_exists($fullPath)) {
                return asset($path);
            }
        }

        return asset('/images/heroes/question-mark.png');
    }

    /**
     * Batch update vote counts efficiently
     */
    public function updateVoteCountsBatch(array $threadIds = [], array $postIds = [])
    {
        if (!empty($threadIds)) {
            // Update thread vote counts in batch
            DB::statement("
                UPDATE forum_threads ft
                LEFT JOIN (
                    SELECT 
                        thread_id,
                        SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                        SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes
                    FROM forum_votes 
                    WHERE thread_id IN (" . implode(',', array_map('intval', $threadIds)) . ")
                        AND post_id IS NULL
                    GROUP BY thread_id
                ) as vote_counts ON ft.id = vote_counts.thread_id
                SET 
                    ft.upvotes = COALESCE(vote_counts.upvotes, 0),
                    ft.downvotes = COALESCE(vote_counts.downvotes, 0),
                    ft.score = COALESCE(vote_counts.upvotes, 0) - COALESCE(vote_counts.downvotes, 0),
                    ft.updated_at = NOW()
                WHERE ft.id IN (" . implode(',', array_map('intval', $threadIds)) . ")
            ");
        }

        if (!empty($postIds)) {
            // Update post vote counts in batch
            DB::statement("
                UPDATE forum_posts fp
                LEFT JOIN (
                    SELECT 
                        post_id,
                        SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                        SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes
                    FROM forum_votes 
                    WHERE post_id IN (" . implode(',', array_map('intval', $postIds)) . ")
                    GROUP BY post_id
                ) as vote_counts ON fp.id = vote_counts.post_id
                SET 
                    fp.upvotes = COALESCE(vote_counts.upvotes, 0),
                    fp.downvotes = COALESCE(vote_counts.downvotes, 0),
                    fp.score = COALESCE(vote_counts.upvotes, 0) - COALESCE(vote_counts.downvotes, 0),
                    fp.updated_at = NOW()
                WHERE fp.id IN (" . implode(',', array_map('intval', $postIds)) . ")
            ");
        }
    }

    /**
     * Get forum statistics efficiently
     */
    public function getForumStats()
    {
        $cacheKey = 'forum:stats';
        return Cache::remember($cacheKey, self::CACHE_TTL, function() {
            return DB::selectOne("
                SELECT 
                    (SELECT COUNT(*) FROM forum_threads WHERE status = 'active') as total_threads,
                    (SELECT COUNT(*) FROM forum_posts WHERE status = 'active') as total_posts,
                    (SELECT COUNT(DISTINCT user_id) FROM forum_threads WHERE status = 'active') as active_users,
                    (SELECT COUNT(*) FROM forum_threads WHERE status = 'active' AND DATE(created_at) = CURDATE()) +
                    (SELECT COUNT(*) FROM forum_posts WHERE status = 'active' AND DATE(created_at) = CURDATE()) as today_activity
            ");
        });
    }
}