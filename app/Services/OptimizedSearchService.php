<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OptimizedSearchService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const SUGGESTION_CACHE_TTL = 900; // 15 minutes
    private const POPULAR_TERMS_CACHE_TTL = 3600; // 1 hour

    /**
     * Universal search across forum and news content
     */
    public function universalSearch($query, $options = [])
    {
        $startTime = microtime(true);
        
        // Normalize query
        $query = trim($query);
        if (empty($query) || strlen($query) < 2) {
            return $this->emptySearchResult($query);
        }

        // Parse search options
        $page = $options['page'] ?? 1;
        $perPage = min($options['per_page'] ?? 20, 50);
        $types = $options['types'] ?? ['threads', 'posts', 'news', 'users'];
        $filters = $options['filters'] ?? [];
        $sortBy = $options['sort'] ?? 'relevance';

        // Check cache
        $cacheKey = $this->getCacheKey($query, $options);
        if ($cached = Cache::get($cacheKey)) {
            $cached['stats']['cached'] = true;
            return $cached;
        }

        $results = [
            'query' => $query,
            'threads' => [],
            'posts' => [],
            'news' => [],
            'users' => [],
            'stats' => [
                'total_results' => 0,
                'search_time' => 0,
                'strategy' => $this->getSearchStrategy($query),
                'cached' => false
            ]
        ];

        // Execute searches based on requested types
        if (in_array('threads', $types)) {
            $results['threads'] = $this->searchForumThreads($query, $filters, $page, $perPage, $sortBy);
        }

        if (in_array('posts', $types)) {
            $results['posts'] = $this->searchForumPosts($query, $filters, $page, $perPage, $sortBy);
        }

        if (in_array('news', $types)) {
            $results['news'] = $this->searchNews($query, $filters, $page, $perPage, $sortBy);
        }

        if (in_array('users', $types)) {
            $results['users'] = $this->searchUsers($query, $filters, min($perPage, 10));
        }

        // Calculate total results
        $results['stats']['total_results'] = 
            count($results['threads']) + 
            count($results['posts']) + 
            count($results['news']) + 
            count($results['users']);

        $results['stats']['search_time'] = round((microtime(true) - $startTime) * 1000, 2);

        // Cache results
        Cache::put($cacheKey, $results, self::CACHE_TTL);

        return $results;
    }

    /**
     * Search forum threads with optimized queries
     */
    private function searchForumThreads($query, $filters, $page, $perPage, $sortBy)
    {
        $offset = ($page - 1) * $perPage;
        
        $threadsQuery = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('ft.status', 'active')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.score', 'ft.replies_count', 
                'ft.views', 'ft.created_at', 'ft.last_reply_at',
                'u.name as author_name', 'u.avatar as author_avatar',
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo',
                'fc.name as category_name', 'fc.color as category_color'
            ]);

        // Apply search conditions with full-text when available
        if (DB::getDriverName() === 'mysql' && strlen($query) >= 3) {
            $threadsQuery->whereRaw("MATCH(ft.title, ft.content) AGAINST(? IN BOOLEAN MODE)", ["+{$query}*"])
                        ->selectRaw("MATCH(ft.title, ft.content) AGAINST(? IN BOOLEAN MODE) as relevance_score", ["+{$query}*"]);
        } else {
            $threadsQuery->where(function($q) use ($query) {
                $q->where('ft.title', 'LIKE', "%{$query}%")
                  ->orWhere('ft.content', 'LIKE', "%{$query}%");
            });
        }

        // Apply filters
        $this->applyForumFilters($threadsQuery, $filters);

        // Apply sorting
        $this->applySorting($threadsQuery, $sortBy, 'forum_thread');

        $threads = $threadsQuery->offset($offset)->limit($perPage)->get();

        return $threads->map(function($thread) {
            return [
                'id' => $thread->id,
                'title' => $thread->title,
                'excerpt' => $this->extractSearchExcerpt($thread->content, 150),
                'author' => $this->formatUserData($thread),
                'category' => [
                    'name' => $thread->category_name ?? 'General',
                    'color' => $thread->category_color ?? '#6b7280'
                ],
                'stats' => [
                    'score' => $thread->score ?? 0,
                    'replies' => $thread->replies_count ?? 0,
                    'views' => $thread->views ?? 0
                ],
                'meta' => [
                    'created_at' => $thread->created_at,
                    'created_at_relative' => \Carbon\Carbon::parse($thread->created_at)->diffForHumans(),
                    'last_reply_at' => $thread->last_reply_at,
                    'relevance_score' => $thread->relevance_score ?? null
                ],
                'type' => 'forum_thread',
                'url' => "/forum/threads/{$thread->id}"
            ];
        })->toArray();
    }

    /**
     * Search forum posts with optimized queries
     */
    private function searchForumPosts($query, $filters, $page, $perPage, $sortBy)
    {
        $offset = ($page - 1) * $perPage;
        
        $postsQuery = DB::table('forum_posts as fp')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->leftJoin('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('fp.status', 'active')
            ->where('ft.status', 'active')
            ->select([
                'fp.id', 'fp.content', 'fp.score', 'fp.created_at',
                'u.name as author_name', 'u.avatar as author_avatar',
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo',
                'ft.id as thread_id', 'ft.title as thread_title'
            ]);

        // Apply search conditions
        if (DB::getDriverName() === 'mysql' && strlen($query) >= 3) {
            $postsQuery->whereRaw("MATCH(fp.content) AGAINST(? IN BOOLEAN MODE)", ["+{$query}*"])
                      ->selectRaw("MATCH(fp.content) AGAINST(? IN BOOLEAN MODE) as relevance_score", ["+{$query}*"]);
        } else {
            $postsQuery->where('fp.content', 'LIKE', "%{$query}%");
        }

        // Apply sorting
        $this->applySorting($postsQuery, $sortBy, 'forum_post');

        $posts = $postsQuery->offset($offset)->limit($perPage)->get();

        return $posts->map(function($post) {
            return [
                'id' => $post->id,
                'content' => $this->extractSearchExcerpt($post->content, 200),
                'author' => $this->formatUserData($post),
                'thread' => [
                    'id' => $post->thread_id,
                    'title' => $post->thread_title
                ],
                'stats' => [
                    'score' => $post->score ?? 0
                ],
                'meta' => [
                    'created_at' => $post->created_at,
                    'created_at_relative' => \Carbon\Carbon::parse($post->created_at)->diffForHumans(),
                    'relevance_score' => $post->relevance_score ?? null
                ],
                'type' => 'forum_post',
                'url' => "/forum/threads/{$post->thread_id}#post-{$post->id}"
            ];
        })->toArray();
    }

    /**
     * Search news articles with optimized queries
     */
    private function searchNews($query, $filters, $page, $perPage, $sortBy)
    {
        $offset = ($page - 1) * $perPage;
        
        $newsQuery = DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->leftJoin('news_categories as nc', 'n.category_id', '=', 'nc.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('n.status', 'published')
            ->where('n.published_at', '<=', now())
            ->select([
                'n.id', 'n.title', 'n.slug', 'n.excerpt', 'n.featured_image',
                'n.views', 'n.comments_count', 'n.score', 'n.published_at',
                'n.featured', 'n.breaking',
                'u.name as author_name', 'u.avatar as author_avatar',
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo',
                'nc.name as category_name', 'nc.color as category_color'
            ]);

        // Apply search conditions
        if (DB::getDriverName() === 'mysql' && strlen($query) >= 3) {
            $newsQuery->whereRaw("MATCH(n.title, n.excerpt, n.content) AGAINST(? IN BOOLEAN MODE)", ["+{$query}*"])
                     ->selectRaw("MATCH(n.title, n.excerpt, n.content) AGAINST(? IN BOOLEAN MODE) as relevance_score", ["+{$query}*"]);
        } else {
            $newsQuery->where(function($q) use ($query) {
                $q->where('n.title', 'LIKE', "%{$query}%")
                  ->orWhere('n.excerpt', 'LIKE', "%{$query}%")
                  ->orWhere('n.content', 'LIKE', "%{$query}%");
            });
        }

        // Apply filters
        $this->applyNewsFilters($newsQuery, $filters);

        // Apply sorting
        $this->applySorting($newsQuery, $sortBy, 'news');

        $news = $newsQuery->offset($offset)->limit($perPage)->get();

        return $news->map(function($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'featured_image' => $this->getImagePath($article->featured_image, $article->title),
                'author' => $this->formatUserData($article),
                'category' => [
                    'name' => $article->category_name ?? 'General',
                    'color' => $article->category_color ?? '#6b7280'
                ],
                'stats' => [
                    'views' => $article->views ?? 0,
                    'comments' => $article->comments_count ?? 0,
                    'score' => $article->score ?? 0
                ],
                'meta' => [
                    'featured' => (bool)$article->featured,
                    'breaking' => (bool)$article->breaking,
                    'published_at' => $article->published_at,
                    'published_at_relative' => \Carbon\Carbon::parse($article->published_at)->diffForHumans(),
                    'relevance_score' => $article->relevance_score ?? null
                ],
                'type' => 'news',
                'url' => "/news/{$article->slug}"
            ];
        })->toArray();
    }

    /**
     * Search users with optimized queries
     */
    private function searchUsers($query, $filters, $limit)
    {
        $usersQuery = DB::table('users as u')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('u.status', 'active')
            ->select([
                'u.id', 'u.name', 'u.avatar', 'u.hero_flair', 
                'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ]);

        // Search by name
        $usersQuery->where('u.name', 'LIKE', "%{$query}%");

        $users = $usersQuery->orderBy('u.name')->limit($limit)->get();

        return $users->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $this->getUserAvatar($user),
                'flairs' => $this->getUserFlairs($user),
                'type' => 'user',
                'url' => "/users/{$user->id}"
            ];
        })->toArray();
    }

    /**
     * Get search suggestions for autocomplete
     */
    public function getSearchSuggestions($partial, $limit = 10)
    {
        $cacheKey = "search_suggestions:" . md5($partial);
        
        return Cache::remember($cacheKey, self::SUGGESTION_CACHE_TTL, function() use ($partial, $limit) {
            $suggestions = [];

            // Forum thread titles
            $threadTitles = DB::table('forum_threads')
                ->select('title')
                ->where('title', 'LIKE', "%{$partial}%")
                ->where('status', 'active')
                ->orderBy('score', 'desc')
                ->limit($limit / 3)
                ->pluck('title');

            foreach ($threadTitles as $title) {
                $suggestions[] = [
                    'text' => $title,
                    'type' => 'thread_title',
                    'category' => 'Forum'
                ];
            }

            // News titles
            $newsTitles = DB::table('news')
                ->select('title')
                ->where('title', 'LIKE', "%{$partial}%")
                ->where('status', 'published')
                ->orderBy('published_at', 'desc')
                ->limit($limit / 3)
                ->pluck('title');

            foreach ($newsTitles as $title) {
                $suggestions[] = [
                    'text' => $title,
                    'type' => 'news_title',
                    'category' => 'News'
                ];
            }

            // Users
            if ($this->looksLikeUsername($partial)) {
                $users = DB::table('users')
                    ->select('name')
                    ->where('name', 'LIKE', "%{$partial}%")
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->limit($limit / 3)
                    ->pluck('name');

                foreach ($users as $username) {
                    $suggestions[] = [
                        'text' => '@' . $username,
                        'type' => 'user',
                        'category' => 'Users'
                    ];
                }
            }

            return array_slice($suggestions, 0, $limit);
        });
    }

    /**
     * Get popular search terms
     */
    public function getPopularSearchTerms($limit = 10)
    {
        $cacheKey = "popular_search_terms";
        
        return Cache::remember($cacheKey, self::POPULAR_TERMS_CACHE_TTL, function() use ($limit) {
            // In a real implementation, this would come from search analytics
            $terms = [
                'strategy', 'tips', 'competitive', 'tournament', 'patch notes',
                'meta', 'team comp', 'hero guide', 'builds', 'updates'
            ];
            
            return array_slice($terms, 0, $limit);
        });
    }

    /**
     * Get trending search queries
     */
    public function getTrendingSearches($limit = 10)
    {
        $cacheKey = "trending_searches";
        
        return Cache::remember($cacheKey, 300, function() use ($limit) {
            // This would typically analyze recent search patterns
            // For now, return some trending terms
            return [
                'latest patch', 'new hero', 'tournament results',
                'meta changes', 'tier list', 'pro teams'
            ];
        });
    }

    /**
     * Helper methods
     */
    private function getSearchStrategy($query)
    {
        if (preg_match('/^".*"$/', $query)) {
            return 'exact_phrase';
        }
        
        if (strlen($query) >= 3 && DB::getDriverName() === 'mysql') {
            return 'fulltext';
        }
        
        if (strlen($query) <= 3) {
            return 'partial';
        }
        
        return 'standard';
    }

    private function applyForumFilters($query, $filters)
    {
        if (isset($filters['category'])) {
            $query->where('fc.slug', $filters['category']);
        }

        if (isset($filters['author'])) {
            $query->where('u.name', 'LIKE', "%{$filters['author']}%");
        }

        if (isset($filters['date_from'])) {
            $query->where('ft.created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('ft.created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['min_score'])) {
            $query->where('ft.score', '>=', $filters['min_score']);
        }
    }

    private function applyNewsFilters($query, $filters)
    {
        if (isset($filters['category'])) {
            $query->where('nc.slug', $filters['category']);
        }

        if (isset($filters['author'])) {
            $query->where('u.name', 'LIKE', "%{$filters['author']}%");
        }

        if (isset($filters['date_from'])) {
            $query->where('n.published_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('n.published_at', '<=', $filters['date_to']);
        }

        if (isset($filters['featured']) && $filters['featured']) {
            $query->where('n.featured', true);
        }

        if (isset($filters['breaking']) && $filters['breaking']) {
            $query->where('n.breaking', true);
        }
    }

    private function applySorting($query, $sortBy, $type)
    {
        switch ($sortBy) {
            case 'relevance':
                if (DB::getDriverName() === 'mysql') {
                    $query->orderByRaw('relevance_score DESC');
                }
                $query->orderBy($type === 'news' ? 'n.published_at' : 'created_at', 'desc');
                break;
                
            case 'date':
                $query->orderBy($type === 'news' ? 'n.published_at' : 'created_at', 'desc');
                break;
                
            case 'score':
                $query->orderBy('score', 'desc');
                break;
                
            case 'popular':
                if ($type === 'news') {
                    $query->orderBy('n.views', 'desc');
                } else {
                    $query->orderBy('views', 'desc');
                }
                break;
                
            default:
                $query->orderBy($type === 'news' ? 'n.published_at' : 'created_at', 'desc');
        }
    }

    private function formatUserData($userData)
    {
        $flairs = [];
        
        if (($userData->show_hero_flair ?? false) && $userData->hero_flair) {
            $flairs['hero'] = [
                'name' => $userData->hero_flair,
                'image' => $this->getHeroImagePath($userData->hero_flair)
            ];
        }
        
        if (($userData->show_team_flair ?? false) && $userData->team_name) {
            $flairs['team'] = [
                'name' => $userData->team_name,
                'short_name' => $userData->team_short,
                'image' => $userData->team_logo
            ];
        }

        $avatar = $userData->avatar ?? $userData->author_avatar ?? null;
        if (($userData->use_hero_as_avatar ?? false) && $userData->hero_flair) {
            $avatar = $this->getHeroImagePath($userData->hero_flair);
        }

        return [
            'name' => $userData->author_name ?? $userData->name,
            'avatar' => $avatar,
            'flairs' => $flairs
        ];
    }

    private function getUserAvatar($user)
    {
        if ($user->use_hero_as_avatar && $user->hero_flair) {
            return $this->getHeroImagePath($user->hero_flair);
        }
        
        return $user->avatar;
    }

    private function getUserFlairs($user)
    {
        $flairs = [];
        
        if ($user->show_hero_flair && $user->hero_flair) {
            $flairs['hero'] = [
                'name' => $user->hero_flair,
                'image' => $this->getHeroImagePath($user->hero_flair)
            ];
        }
        
        if ($user->show_team_flair && $user->team_name) {
            $flairs['team'] = [
                'name' => $user->team_name,
                'short_name' => $user->team_short,
                'image' => $user->team_logo
            ];
        }
        
        return $flairs;
    }

    private function getImagePath($imagePath, $title = 'Content')
    {
        if (!$imagePath) {
            return asset('images/placeholder.svg');
        }

        if (str_starts_with($imagePath, 'http')) {
            return $imagePath;
        }

        return asset('storage/' . $imagePath);
    }

    private function getHeroImagePath($heroName)
    {
        $heroSlug = Str::slug($heroName);
        $paths = [
            "/images/heroes/{$heroSlug}-headbig.webp",
            "/images/heroes/{$heroSlug}.png",
            "/images/heroes/{$heroSlug}.webp"
        ];

        foreach ($paths as $path) {
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        return asset('/images/heroes/question-mark.png');
    }

    private function extractSearchExcerpt($content, $maxLength = 150)
    {
        $content = strip_tags($content);
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        
        return Str::limit($content, $maxLength);
    }

    private function looksLikeUsername($query)
    {
        return preg_match('/^@?[a-zA-Z0-9_]+$/', $query) && strlen($query) <= 20;
    }

    private function getCacheKey($query, $options)
    {
        return 'universal_search:' . md5($query . serialize($options));
    }

    private function emptySearchResult($query)
    {
        return [
            'query' => $query,
            'threads' => [],
            'posts' => [],
            'news' => [],
            'users' => [],
            'stats' => [
                'total_results' => 0,
                'search_time' => 0,
                'strategy' => 'empty',
                'cached' => false
            ]
        ];
    }

    /**
     * Clear search caches
     */
    public function clearSearchCaches()
    {
        // Clear all search-related cache keys
        $patterns = [
            'universal_search:*',
            'search_suggestions:*',
            'popular_search_terms',
            'trending_searches'
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}