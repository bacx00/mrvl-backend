<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ForumSearchService
{
    private $cacheService;

    public function __construct(ForumCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Enhanced forum search with multiple strategies
     */
    public function search($query, $filters = [], $page = 1, $perPage = 20)
    {
        // Check cache first
        $cacheKey = $this->getCacheKey($query, $filters, $page);
        $cached = $this->cacheService->getCachedSearchResults($query, array_merge($filters, ['page' => $page]));
        
        if ($cached) {
            return $cached;
        }

        $results = [
            'threads' => [],
            'posts' => [],
            'users' => [],
            'categories' => [],
            'stats' => [
                'total_results' => 0,
                'search_time' => 0,
                'query' => $query
            ]
        ];

        $startTime = microtime(true);

        // Determine search strategy based on query characteristics
        $strategy = $this->getSearchStrategy($query, $filters);

        switch ($strategy) {
            case 'fulltext':
                $results = $this->fullTextSearch($query, $filters, $page, $perPage);
                break;
            case 'fuzzy':
                $results = $this->fuzzySearch($query, $filters, $page, $perPage);
                break;
            case 'exact':
                $results = $this->exactSearch($query, $filters, $page, $perPage);
                break;
            case 'advanced':
                $results = $this->advancedSearch($query, $filters, $page, $perPage);
                break;
            default:
                $results = $this->hybridSearch($query, $filters, $page, $perPage);
        }

        $results['stats']['search_time'] = round((microtime(true) - $startTime) * 1000, 2);

        // Cache results
        $this->cacheService->cacheSearchResults($query, array_merge($filters, ['page' => $page]), $results);

        return $results;
    }

    /**
     * Full-text search using MySQL MATCH AGAINST
     */
    private function fullTextSearch($query, $filters, $page, $perPage)
    {
        $offset = ($page - 1) * $perPage;
        
        // Search threads
        $threadsQuery = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.created_at', 'ft.score', 'ft.replies_count',
                'u.name as author_name', 'u.avatar as author_avatar',
                'fc.name as category_name', 'fc.color as category_color'
            ])
            ->where('ft.status', 'active');

        if (DB::getDriverName() === 'mysql' && strlen($query) >= 3) {
            $threadsQuery->whereRaw("MATCH(ft.title, ft.content) AGAINST(? IN BOOLEAN MODE)", ["+{$query}*"])
                        ->selectRaw("MATCH(ft.title, ft.content) AGAINST(? IN BOOLEAN MODE) as relevance_score", ["+{$query}*"]);
        } else {
            $threadsQuery->where(function($q) use ($query) {
                $q->where('ft.title', 'LIKE', "%{$query}%")
                  ->orWhere('ft.content', 'LIKE', "%{$query}%");
            });
        }

        $this->applyFilters($threadsQuery, $filters);
        
        $threads = $threadsQuery->orderByRaw(DB::getDriverName() === 'mysql' ? 'relevance_score DESC' : 'ft.score DESC')
                               ->orderBy('ft.created_at', 'desc')
                               ->offset($offset)
                               ->limit($perPage)
                               ->get();

        // Search posts
        $postsQuery = DB::table('forum_posts as fp')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->leftJoin('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
            ->select([
                'fp.id', 'fp.content', 'fp.created_at', 'fp.score',
                'u.name as author_name', 'u.avatar as author_avatar',
                'ft.id as thread_id', 'ft.title as thread_title'
            ])
            ->where('fp.status', 'active');

        if (DB::getDriverName() === 'mysql' && strlen($query) >= 3) {
            $postsQuery->whereRaw("MATCH(fp.content) AGAINST(? IN BOOLEAN MODE)", ["+{$query}*"]);
        } else {
            $postsQuery->where('fp.content', 'LIKE', "%{$query}%");
        }

        $posts = $postsQuery->orderBy('fp.created_at', 'desc')
                           ->limit($perPage)
                           ->get();

        return [
            'threads' => $this->formatThreadResults($threads),
            'posts' => $this->formatPostResults($posts),
            'users' => [],
            'categories' => [],
            'stats' => [
                'total_results' => count($threads) + count($posts),
                'threads_count' => count($threads),
                'posts_count' => count($posts),
                'query' => $query
            ]
        ];
    }

    /**
     * Fuzzy search for typo tolerance
     */
    private function fuzzySearch($query, $filters, $page, $perPage)
    {
        // Generate fuzzy variations of the search term
        $variations = $this->generateFuzzyVariations($query);
        
        $threadsQuery = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select(['ft.id', 'ft.title', 'ft.content', 'ft.created_at', 'u.name as author_name'])
            ->where('ft.status', 'active');

        $threadsQuery->where(function($q) use ($variations) {
            foreach ($variations as $variation) {
                $q->orWhere('ft.title', 'LIKE', "%{$variation}%")
                  ->orWhere('ft.content', 'LIKE', "%{$variation}%");
            }
        });

        $threads = $threadsQuery->orderBy('ft.score', 'desc')
                               ->limit($perPage)
                               ->get();

        return [
            'threads' => $this->formatThreadResults($threads),
            'posts' => [],
            'users' => [],
            'categories' => [],
            'stats' => [
                'total_results' => count($threads),
                'query' => $query,
                'variations_used' => $variations
            ]
        ];
    }

    /**
     * Exact phrase search
     */
    private function exactSearch($query, $filters, $page, $perPage)
    {
        $offset = ($page - 1) * $perPage;
        
        $threadsQuery = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select(['ft.id', 'ft.title', 'ft.content', 'ft.created_at', 'u.name as author_name'])
            ->where('ft.status', 'active')
            ->where(function($q) use ($query) {
                $q->where('ft.title', 'LIKE', "%{$query}%")
                  ->orWhere('ft.content', 'LIKE', "%{$query}%");
            });

        $threads = $threadsQuery->orderBy('ft.created_at', 'desc')
                               ->offset($offset)
                               ->limit($perPage)
                               ->get();

        return [
            'threads' => $this->formatThreadResults($threads),
            'posts' => [],
            'users' => [],
            'categories' => [],
            'stats' => [
                'total_results' => count($threads),
                'query' => $query
            ]
        ];
    }

    /**
     * Advanced search with multiple criteria
     */
    private function advancedSearch($query, $filters, $page, $perPage)
    {
        $offset = ($page - 1) * $perPage;
        
        $threadsQuery = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.created_at', 'ft.score',
                'u.name as author_name', 'fc.name as category_name'
            ])
            ->where('ft.status', 'active');

        // Apply text search if provided
        if ($query) {
            $threadsQuery->where(function($q) use ($query) {
                $q->where('ft.title', 'LIKE', "%{$query}%")
                  ->orWhere('ft.content', 'LIKE', "%{$query}%");
            });
        }

        $this->applyFilters($threadsQuery, $filters);
        
        $threads = $threadsQuery->orderBy('ft.created_at', 'desc')
                               ->offset($offset)
                               ->limit($perPage)
                               ->get();

        return [
            'threads' => $this->formatThreadResults($threads),
            'posts' => [],
            'users' => [],
            'categories' => [],
            'stats' => [
                'total_results' => count($threads),
                'query' => $query,
                'filters_applied' => array_keys($filters)
            ]
        ];
    }

    /**
     * Hybrid search combining multiple strategies
     */
    private function hybridSearch($query, $filters, $page, $perPage)
    {
        $results = [
            'threads' => [],
            'posts' => [],
            'users' => [],
            'categories' => [],
            'stats' => ['total_results' => 0, 'query' => $query]
        ];

        // Start with full-text search
        $fullTextResults = $this->fullTextSearch($query, $filters, $page, $perPage);
        
        // If we don't have enough results, try fuzzy search
        if (count($fullTextResults['threads']) < $perPage / 2) {
            $fuzzyResults = $this->fuzzySearch($query, $filters, 1, $perPage - count($fullTextResults['threads']));
            $fullTextResults['threads'] = array_merge($fullTextResults['threads'], $fuzzyResults['threads']);
        }

        // Add user search if query looks like a username
        if ($this->looksLikeUsername($query)) {
            $userResults = $this->searchUsers($query, 5);
            $fullTextResults['users'] = $userResults;
        }

        return $fullTextResults;
    }

    /**
     * Search users by username
     */
    private function searchUsers($query, $limit = 10)
    {
        return DB::table('users')
            ->select(['id', 'name', 'avatar', 'hero_flair'])
            ->where('name', 'LIKE', "%{$query}%")
            ->where('status', 'active')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'type' => 'user'
                ];
            });
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, $filters)
    {
        if (isset($filters['category']) && $filters['category'] !== 'all') {
            $query->where('ft.category', $filters['category']);
        }

        if (isset($filters['author_id'])) {
            $query->where('ft.user_id', $filters['author_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('ft.created_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (isset($filters['date_to'])) {
            $query->where('ft.created_at', '<=', Carbon::parse($filters['date_to']));
        }

        if (isset($filters['min_score'])) {
            $query->where('ft.score', '>=', $filters['min_score']);
        }

        if (isset($filters['has_replies']) && $filters['has_replies']) {
            $query->where('ft.replies_count', '>', 0);
        }

        if (isset($filters['is_pinned'])) {
            $query->where('ft.pinned', $filters['is_pinned']);
        }
    }

    /**
     * Format thread results for consistent output
     */
    private function formatThreadResults($threads)
    {
        return $threads->map(function($thread) {
            return [
                'id' => $thread->id,
                'title' => $thread->title,
                'content' => $this->truncateContent($thread->content, 200),
                'author' => [
                    'name' => $thread->author_name,
                    'avatar' => $thread->author_avatar ?? null
                ],
                'category' => [
                    'name' => $thread->category_name ?? 'General',
                    'color' => $thread->category_color ?? '#6b7280'
                ],
                'stats' => [
                    'score' => $thread->score ?? 0,
                    'replies' => $thread->replies_count ?? 0
                ],
                'created_at' => $thread->created_at,
                'type' => 'thread',
                'url' => "/forums/threads/{$thread->id}"
            ];
        });
    }

    /**
     * Format post results for consistent output
     */
    private function formatPostResults($posts)
    {
        return $posts->map(function($post) {
            return [
                'id' => $post->id,
                'content' => $this->truncateContent($post->content, 200),
                'author' => [
                    'name' => $post->author_name,
                    'avatar' => $post->author_avatar ?? null
                ],
                'thread' => [
                    'id' => $post->thread_id,
                    'title' => $post->thread_title
                ],
                'score' => $post->score ?? 0,
                'created_at' => $post->created_at,
                'type' => 'post',
                'url' => "/forums/threads/{$post->thread_id}#post-{$post->id}"
            ];
        });
    }

    /**
     * Determine the best search strategy based on query
     */
    private function getSearchStrategy($query, $filters)
    {
        // If query is quoted, use exact search
        if (preg_match('/^".*"$/', $query)) {
            return 'exact';
        }

        // If many filters are provided, use advanced search
        if (count($filters) > 2) {
            return 'advanced';
        }

        // If query is short or contains special characters, use fuzzy search
        if (strlen($query) <= 3 || preg_match('/[^a-zA-Z0-9\s]/', $query)) {
            return 'fuzzy';
        }

        // If MySQL and query is long enough, use full-text search
        if (DB::getDriverName() === 'mysql' && strlen($query) >= 3) {
            return 'fulltext';
        }

        // Default to hybrid approach
        return 'hybrid';
    }

    /**
     * Generate fuzzy variations of a search term
     */
    private function generateFuzzyVariations($query)
    {
        $variations = [$query];

        // Add common typo patterns
        $patterns = [
            '/tion$/' => 'sion',
            '/sion$/' => 'tion',
            '/er$/' => 'or',
            '/or$/' => 'er',
            '/ie/' => 'ei',
            '/ei/' => 'ie'
        ];

        foreach ($patterns as $pattern => $replacement) {
            $variation = preg_replace($pattern, $replacement, $query);
            if ($variation !== $query) {
                $variations[] = $variation;
            }
        }

        // Add wildcard variations
        if (strlen($query) > 3) {
            $variations[] = substr($query, 0, -1) . '%'; // Remove last character
            $variations[] = substr($query, 1) . '%'; // Remove first character
        }

        return array_unique($variations);
    }

    /**
     * Check if query looks like a username
     */
    private function looksLikeUsername($query)
    {
        return preg_match('/^@?[a-zA-Z0-9_]+$/', $query) && strlen($query) <= 20;
    }

    /**
     * Truncate content for search results
     */
    private function truncateContent($content, $length = 200)
    {
        if (strlen($content) <= $length) {
            return $content;
        }

        return substr($content, 0, $length) . '...';
    }

    /**
     * Generate cache key for search results
     */
    private function getCacheKey($query, $filters, $page)
    {
        return 'forum_search:' . md5($query . serialize($filters) . $page);
    }

    /**
     * Get search suggestions based on partial input
     */
    public function getSuggestions($partial, $limit = 10)
    {
        $cacheKey = "forum_suggestions:" . md5($partial);
        
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $suggestions = [];

        // Get popular thread titles that match
        $threadTitles = DB::table('forum_threads')
            ->select('title')
            ->where('title', 'LIKE', "%{$partial}%")
            ->where('status', 'active')
            ->orderBy('score', 'desc')
            ->limit($limit / 2)
            ->pluck('title');

        foreach ($threadTitles as $title) {
            $suggestions[] = [
                'text' => $title,
                'type' => 'thread_title'
            ];
        }

        // Get matching usernames
        $usernames = DB::table('users')
            ->select('name')
            ->where('name', 'LIKE', "%{$partial}%")
            ->where('status', 'active')
            ->orderBy('name')
            ->limit($limit / 2)
            ->pluck('name');

        foreach ($usernames as $username) {
            $suggestions[] = [
                'text' => '@' . $username,
                'type' => 'user_mention'
            ];
        }

        // Cache for 5 minutes
        Cache::put($cacheKey, $suggestions, 300);

        return $suggestions;
    }

    /**
     * Get popular search terms
     */
    public function getPopularSearchTerms($limit = 10)
    {
        $cacheKey = "forum_popular_searches";
        
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // This would typically come from search analytics
        // For now, return some common terms
        $popular = [
            'strategy', 'tips', 'team', 'competitive', 'patch', 'update',
            'hero', 'build', 'meta', 'tournament'
        ];

        Cache::put($cacheKey, $popular, 3600); // Cache for 1 hour

        return array_slice($popular, 0, $limit);
    }
}