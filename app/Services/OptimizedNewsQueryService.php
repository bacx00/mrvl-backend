<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\MentionService;

class OptimizedNewsQueryService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const TRENDING_CACHE_TTL = 60; // 1 minute for trending content

    private $mentionService;

    public function __construct(MentionService $mentionService)
    {
        $this->mentionService = $mentionService;
    }

    /**
     * Get news articles with optimized queries to prevent N+1 problems
     */
    public function getNewsOptimized(array $params = [])
    {
        $category = $params['category'] ?? 'all';
        $sortBy = $params['sort'] ?? 'latest';
        $page = $params['page'] ?? 1;
        $perPage = min($params['per_page'] ?? 15, 50);
        $search = $params['search'] ?? null;

        // Use trending view for trending sorting
        if ($sortBy === 'trending') {
            return $this->getTrendingNewsFromView($page, $perPage, $category, $search);
        }

        // Build optimized query with single JOIN operation
        $query = DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->leftJoin('news_categories as nc', 'n.category_id', '=', 'nc.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->select([
                // News data
                'n.id', 'n.title', 'n.slug', 'n.excerpt', 'n.featured_image',
                'n.views', 'n.comments_count', 'n.score', 'n.upvotes', 'n.downvotes',
                'n.featured', 'n.breaking', 'n.published_at', 'n.created_at', 'n.updated_at',
                'n.tags', 'n.videos',
                // Author data (avoid N+1)
                'u.name as author_name', 'u.avatar as author_avatar',
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                // Team flair data (avoid N+1)
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo',
                // Category data (avoid N+1)
                'nc.name as category_name', 'nc.slug as category_slug', 'nc.color as category_color'
            ])
            ->where('n.status', 'published')
            ->where('n.published_at', '<=', now());

        // Apply category filter with optimized index usage
        if ($category && $category !== 'all') {
            if (is_numeric($category)) {
                $query->where('n.category_id', $category);
            } else {
                $query->where('nc.slug', $category);
            }
        }

        // Apply search with full-text when available
        if ($search) {
            if (DB::getDriverName() === 'mysql') {
                $query->whereRaw("MATCH(n.title, n.excerpt, n.content) AGAINST(? IN BOOLEAN MODE)", [$search . '*']);
            } else {
                $query->where(function($q) use ($search) {
                    $q->where('n.title', 'LIKE', "%{$search}%")
                      ->orWhere('n.content', 'LIKE', "%{$search}%")
                      ->orWhere('n.excerpt', 'LIKE', "%{$search}%");
                });
            }
        }

        // Apply sorting with optimized indexes
        switch ($sortBy) {
            case 'popular':
                $query->orderBy('n.featured', 'desc')
                      ->orderBy('n.score', 'desc')
                      ->orderBy('n.published_at', 'desc');
                break;
            case 'hot':
                $query->orderBy('n.featured', 'desc')
                      ->orderBy('n.comments_count', 'desc')
                      ->orderBy('n.published_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('n.featured', 'desc')
                      ->orderBy('n.published_at', 'asc');
                break;
            default: // latest
                $query->orderBy('n.featured', 'desc')
                      ->orderBy('n.published_at', 'desc');
        }

        // Execute paginated query
        $news = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform data to avoid additional queries
        $newsData = collect($news->items())->map(function($article) {
            return $this->transformNewsData($article);
        });

        return [
            'data' => $newsData,
            'pagination' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total()
            ]
        ];
    }

    /**
     * Get single news article with all comments optimized
     */
    public function getNewsWithCommentsOptimized($identifier, $userId = null)
    {
        // Get news article with author info in single query
        $query = DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->leftJoin('news_categories as nc', 'n.category_id', '=', 'nc.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('n.status', 'published')
            ->where('n.published_at', '<=', now())
            ->select([
                'n.*',
                'u.name as author_name', 'u.avatar as author_avatar',
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo',
                'nc.name as category_name', 'nc.slug as category_slug', 'nc.color as category_color'
            ]);

        // Check if identifier is numeric (ID) or string (slug)
        if (is_numeric($identifier)) {
            $query->where('n.id', $identifier);
        } else {
            $query->where('n.slug', $identifier);
        }

        $article = $query->first();

        if (!$article) {
            return null;
        }

        // Get all comments with user data in single optimized query
        $comments = $this->getNewsCommentsOptimized($article->id, $userId);

        // Get user vote if authenticated
        $userVote = null;
        if ($userId) {
            $userVote = DB::table('news_votes')
                ->where('news_id', $article->id)
                ->where('user_id', $userId)
                ->whereNull('comment_id')
                ->value('vote_type');
        }

        // Get related articles
        $relatedArticles = $this->getRelatedArticlesOptimized($article->id, $article->category_id);

        return [
            'article' => $this->transformNewsData($article, true),
            'comments' => $comments,
            'user_vote' => $userVote,
            'related_articles' => $relatedArticles
        ];
    }

    /**
     * Get news comments with nested structure optimized
     */
    public function getNewsCommentsOptimized($newsId, $userId = null, $sortBy = 'newest')
    {
        // Get all comments with user and team data in single query
        $comments = DB::table('news_comments as nc')
            ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('nc.news_id', $newsId)
            ->where('nc.status', 'active')
            ->select([
                'nc.*',
                'u.name as author_name', 'u.avatar as author_avatar',
                'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ])
            ->orderBy('nc.created_at', $sortBy === 'oldest' ? 'asc' : 'desc')
            ->get();

        // Get user votes for all comments in single query if authenticated
        $userVotes = [];
        if ($userId && $comments->count() > 0) {
            $commentIds = $comments->pluck('id');
            $userVotes = DB::table('news_votes')
                ->where('news_id', $newsId)
                ->whereIn('comment_id', $commentIds)
                ->where('user_id', $userId)
                ->pluck('vote_type', 'comment_id')
                ->toArray();
        }

        // Build nested structure efficiently
        $commentMap = [];
        $topLevelComments = [];

        foreach ($comments as $comment) {
            $commentData = [
                'id' => $comment->id,
                'content' => $comment->content,
                'author' => $this->transformUserData($comment),
                'stats' => [
                    'score' => $comment->score ?? 0,
                    'upvotes' => $comment->upvotes ?? 0,
                    'downvotes' => $comment->downvotes ?? 0
                ],
                'meta' => [
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'edited' => (bool)$comment->is_edited,
                    'edited_at' => $comment->edited_at
                ],
                'mentions' => $this->mentionService->extractMentions($comment->content),
                'user_vote' => $userVotes[$comment->id] ?? null,
                'parent_id' => $comment->parent_id,
                'replies' => []
            ];

            $commentMap[$comment->id] = $commentData;

            if ($comment->parent_id) {
                // This is a reply - will be added to parent later
                continue;
            } else {
                // This is a top-level comment
                $topLevelComments[] = &$commentMap[$comment->id];
            }
        }

        // Add replies to their parents
        foreach ($comments as $comment) {
            if ($comment->parent_id && isset($commentMap[$comment->parent_id])) {
                $commentMap[$comment->parent_id]['replies'][] = &$commentMap[$comment->id];
            }
        }

        // Sort by score if requested
        if ($sortBy === 'best') {
            usort($topLevelComments, function($a, $b) {
                return $b['stats']['score'] - $a['stats']['score'];
            });
        }

        return array_values($topLevelComments);
    }

    /**
     * Get trending news from materialized view for better performance
     */
    private function getTrendingNewsFromView($page, $perPage, $category = 'all', $search = null)
    {
        $query = DB::table('trending_news');

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
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('excerpt', 'LIKE', "%{$search}%");
            });
        }

        $news = $query->orderBy('trending_score', 'desc')
                     ->orderBy('published_at', 'desc')
                     ->paginate($perPage, ['*'], 'page', $page);

        $newsData = collect($news->items())->map(function($article) {
            return $this->transformTrendingNewsData($article);
        });

        return [
            'data' => $newsData,
            'pagination' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total()
            ]
        ];
    }

    /**
     * Get related articles optimized
     */
    private function getRelatedArticlesOptimized($currentId, $categoryId, $limit = 5)
    {
        return DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->where('n.id', '!=', $currentId)
            ->where('n.category_id', $categoryId)
            ->where('n.status', 'published')
            ->where('n.published_at', '<=', now())
            ->select([
                'n.id', 'n.title', 'n.slug', 'n.excerpt', 'n.featured_image',
                'n.published_at', 'n.views', 'u.name as author_name'
            ])
            ->orderBy('n.published_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'featured_image' => $this->getImagePath($article->featured_image, $article->title),
                    'author_name' => $article->author_name,
                    'published_at' => $article->published_at,
                    'published_at_relative' => \Carbon\Carbon::parse($article->published_at)->diffForHumans(),
                    'views' => $article->views
                ];
            });
    }

    /**
     * Transform news data to standardized format
     */
    private function transformNewsData($article, $includeContent = false)
    {
        $data = [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'featured_image' => $this->getImagePath($article->featured_image, $article->title),
            'author' => $this->transformUserData($article),
            'category' => [
                'id' => $article->category_id ?? null,
                'name' => $article->category_name ?? 'General',
                'slug' => $article->category_slug ?? 'general',
                'color' => $article->category_color ?? '#6b7280'
            ],
            'stats' => [
                'views' => (int)($article->views ?? 0),
                'comments' => (int)($article->comments_count ?? 0),
                'score' => (int)($article->score ?? 0),
                'upvotes' => (int)($article->upvotes ?? 0),
                'downvotes' => (int)($article->downvotes ?? 0)
            ],
            'meta' => [
                'featured' => (bool)$article->featured,
                'breaking' => (bool)$article->breaking,
                'published_at' => $article->published_at,
                'published_at_formatted' => \Carbon\Carbon::parse($article->published_at)->format('M j, Y g:i A'),
                'published_at_relative' => \Carbon\Carbon::parse($article->published_at)->diffForHumans(),
                'created_at' => $article->created_at,
                'updated_at' => $article->updated_at,
                'read_time' => $this->calculateReadTime($article->content ?? $article->excerpt)
            ],
            'tags' => $article->tags ? json_decode($article->tags, true) : [],
            'videos' => $this->getArticleVideos($article)
        ];

        if ($includeContent) {
            $data['content'] = $article->content;
            $data['mentions'] = array_merge(
                $this->mentionService->extractMentions($article->title),
                $this->mentionService->extractMentions($article->excerpt ?: ''),
                $this->mentionService->extractMentions($article->content)
            );
        }

        return $data;
    }

    /**
     * Transform trending news data (from view)
     */
    private function transformTrendingNewsData($article)
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'featured_image' => $this->getImagePath($article->featured_image, $article->title),
            'author' => [
                'id' => $article->author_id,
                'name' => $article->author_name,
                'avatar' => $article->author_avatar
            ],
            'category' => [
                'id' => $article->category_id,
                'name' => $article->category_name ?? 'General',
                'color' => $article->category_color ?? '#6b7280'
            ],
            'stats' => [
                'views' => (int)($article->views ?? 0),
                'comments' => (int)($article->comments_count ?? 0),
                'score' => (int)($article->score ?? 0),
                'upvotes' => (int)($article->upvotes ?? 0),
                'downvotes' => (int)($article->downvotes ?? 0),
                'trending_score' => round($article->trending_score ?? 0, 2)
            ],
            'meta' => [
                'featured' => (bool)$article->featured,
                'breaking' => (bool)$article->breaking,
                'published_at' => $article->published_at,
                'created_at' => $article->created_at,
                'is_trending' => true
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
        if (($userData->show_hero_flair ?? false) && $userData->hero_flair) {
            $flairs['hero'] = [
                'type' => 'hero',
                'name' => $userData->hero_flair,
                'image' => $this->getHeroImagePath($userData->hero_flair),
                'fallback_text' => $userData->hero_flair
            ];
        }
        
        // Add team flair if enabled
        if (($userData->show_team_flair ?? false) && $userData->team_name) {
            $flairs['team'] = [
                'type' => 'team',
                'name' => $userData->team_name,
                'short_name' => $userData->team_short,
                'image' => $userData->team_logo ? asset('storage/' . $userData->team_logo) : null,
                'fallback_text' => $userData->team_short
            ];
        }

        // Determine avatar
        $avatar = $userData->avatar ?? $userData->author_avatar ?? null;
        if (($userData->use_hero_as_avatar ?? false) && $userData->hero_flair) {
            $avatar = $this->getHeroImagePath($userData->hero_flair);
        } else if ($avatar) {
            $avatar = str_starts_with($avatar, 'http') 
                ? $avatar 
                : asset('storage/avatars/' . $avatar);
        }

        return [
            'id' => $userData->user_id ?? $userData->author_id ?? null,
            'name' => $userData->author_name,
            'username' => $userData->author_name,
            'avatar' => $avatar,
            'flairs' => $flairs,
            'use_hero_as_avatar' => (bool)($userData->use_hero_as_avatar ?? false)
        ];
    }

    /**
     * Get optimized image path with fallbacks
     */
    private function getImagePath($imagePath, $title = 'News')
    {
        if (!$imagePath) {
            return asset('images/news-placeholder.svg');
        }

        if (str_starts_with($imagePath, 'http')) {
            return $imagePath;
        }

        $fullPath = public_path('storage/' . $imagePath);
        if (file_exists($fullPath)) {
            return asset('storage/' . $imagePath);
        }

        return asset('images/news-placeholder.svg');
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
     * Get article videos (combined from database and content extraction)
     */
    private function getArticleVideos($article)
    {
        $videos = [];

        // First, try to get videos from the dedicated videos column
        if (!empty($article->videos)) {
            try {
                $storedVideos = json_decode($article->videos, true);
                if (is_array($storedVideos)) {
                    $videos = array_merge($videos, $storedVideos);
                }
            } catch (\Exception $e) {
                // Ignore parsing errors
            }
        }

        return $videos;
    }

    /**
     * Calculate reading time
     */
    private function calculateReadTime($content)
    {
        if (!$content) return 1;
        
        $wordCount = str_word_count(strip_tags($content));
        $readingSpeed = 200; // words per minute
        return max(1, round($wordCount / $readingSpeed));
    }

    /**
     * Batch update vote counts efficiently for news
     */
    public function updateNewsVoteCountsBatch(array $newsIds = [], array $commentIds = [])
    {
        if (!empty($newsIds)) {
            // Update news vote counts in batch
            DB::statement("
                UPDATE news n
                LEFT JOIN (
                    SELECT 
                        news_id,
                        SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                        SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes
                    FROM news_votes 
                    WHERE news_id IN (" . implode(',', array_map('intval', $newsIds)) . ")
                        AND comment_id IS NULL
                    GROUP BY news_id
                ) as vote_counts ON n.id = vote_counts.news_id
                SET 
                    n.upvotes = COALESCE(vote_counts.upvotes, 0),
                    n.downvotes = COALESCE(vote_counts.downvotes, 0),
                    n.score = COALESCE(vote_counts.upvotes, 0) - COALESCE(vote_counts.downvotes, 0),
                    n.updated_at = NOW()
                WHERE n.id IN (" . implode(',', array_map('intval', $newsIds)) . ")
            ");
        }

        if (!empty($commentIds)) {
            // Update comment vote counts in batch
            DB::statement("
                UPDATE news_comments nc
                LEFT JOIN (
                    SELECT 
                        comment_id,
                        SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                        SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes
                    FROM news_votes 
                    WHERE comment_id IN (" . implode(',', array_map('intval', $commentIds)) . ")
                    GROUP BY comment_id
                ) as vote_counts ON nc.id = vote_counts.comment_id
                SET 
                    nc.upvotes = COALESCE(vote_counts.upvotes, 0),
                    nc.downvotes = COALESCE(vote_counts.downvotes, 0),
                    nc.score = COALESCE(vote_counts.upvotes, 0) - COALESCE(vote_counts.downvotes, 0),
                    nc.updated_at = NOW()
                WHERE nc.id IN (" . implode(',', array_map('intval', $commentIds)) . ")
            ");
        }
    }

    /**
     * Get news statistics efficiently
     */
    public function getNewsStats()
    {
        $cacheKey = 'news:stats';
        return Cache::remember($cacheKey, self::CACHE_TTL, function() {
            return DB::selectOne("
                SELECT 
                    (SELECT COUNT(*) FROM news WHERE status = 'published') as total_articles,
                    (SELECT COUNT(*) FROM news_comments WHERE status = 'active') as total_comments,
                    (SELECT COUNT(DISTINCT author_id) FROM news WHERE status = 'published') as active_authors,
                    (SELECT COUNT(*) FROM news WHERE status = 'published' AND DATE(published_at) = CURDATE()) as today_articles,
                    (SELECT COUNT(*) FROM news WHERE status = 'published' AND featured = 1) as featured_articles
            ");
        });
    }
}