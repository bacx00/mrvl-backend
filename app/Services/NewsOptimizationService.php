<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\MentionService;
use Carbon\Carbon;

class NewsOptimizationService
{
    private const CACHE_TTL = 1800; // 30 minutes
    private const IMAGE_CACHE_TTL = 86400; // 24 hours
    private const SEO_CACHE_TTL = 3600; // 1 hour
    
    private $mentionService;

    public function __construct(MentionService $mentionService)
    {
        $this->mentionService = $mentionService;
    }

    /**
     * Fix uploaded images not showing in homepage cards
     */
    public function fixNewsImageDisplay()
    {
        Log::info('NewsOptimizationService: Starting image display fix');
        
        try {
            // Get all news articles with missing or broken image URLs
            $newsWithImages = DB::table('news')
                ->where('status', 'published')
                ->whereNotNull('featured_image')
                ->select(['id', 'title', 'featured_image'])
                ->get();

            $fixedCount = 0;
            $fallbackCount = 0;

            foreach ($newsWithImages as $news) {
                $imagePath = $news->featured_image;
                $fixedPath = $this->optimizeImagePath($imagePath);
                
                if ($fixedPath !== $imagePath) {
                    DB::table('news')
                        ->where('id', $news->id)
                        ->update(['featured_image' => $fixedPath]);
                    $fixedCount++;
                }
                
                // Check if image exists, if not use fallback
                if (!$this->imageExists($fixedPath)) {
                    $fallbackPath = $this->getNewsFallbackImage($news->title);
                    DB::table('news')
                        ->where('id', $news->id)
                        ->update(['featured_image' => $fallbackPath]);
                    $fallbackCount++;
                }
            }

            Log::info('NewsOptimizationService: Image fix completed', [
                'total_checked' => $newsWithImages->count(),
                'paths_fixed' => $fixedCount,
                'fallbacks_applied' => $fallbackCount
            ]);

            return [
                'status' => 'success',
                'total_checked' => $newsWithImages->count(),
                'paths_fixed' => $fixedCount,
                'fallbacks_applied' => $fallbackCount
            ];

        } catch (\Exception $e) {
            Log::error('NewsOptimizationService: Image fix failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Optimize news article SEO meta tags
     */
    public function optimizeNewsSEO()
    {
        Log::info('NewsOptimizationService: Starting SEO optimization');
        
        try {
            $newsArticles = DB::table('news')
                ->where('status', 'published')
                ->select(['id', 'title', 'excerpt', 'content', 'meta_data', 'slug'])
                ->get();

            $optimizedCount = 0;

            foreach ($newsArticles as $article) {
                $metaData = $this->generateOptimizedMetaData($article);
                
                DB::table('news')
                    ->where('id', $article->id)
                    ->update([
                        'meta_data' => json_encode($metaData),
                        'updated_at' => now()
                    ]);
                
                $optimizedCount++;
            }

            Log::info('NewsOptimizationService: SEO optimization completed', [
                'articles_optimized' => $optimizedCount
            ]);

            return [
                'status' => 'success',
                'articles_optimized' => $optimizedCount
            ];

        } catch (\Exception $e) {
            Log::error('NewsOptimizationService: SEO optimization failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Implement news scheduling system
     */
    public function implementNewsScheduling()
    {
        Log::info('NewsOptimizationService: Processing scheduled news');
        
        try {
            // Find articles scheduled to be published
            $scheduledNews = DB::table('news')
                ->where('status', 'scheduled')
                ->where('published_at', '<=', now())
                ->get(['id', 'title', 'published_at']);

            $publishedCount = 0;

            foreach ($scheduledNews as $news) {
                DB::table('news')
                    ->where('id', $news->id)
                    ->update([
                        'status' => 'published',
                        'updated_at' => now()
                    ]);
                
                // Clear related caches
                $this->clearNewsCache($news->id);
                $publishedCount++;
                
                Log::info('NewsOptimizationService: Published scheduled article', [
                    'id' => $news->id,
                    'title' => $news->title
                ]);
            }

            return [
                'status' => 'success',
                'published_count' => $publishedCount
            ];

        } catch (\Exception $e) {
            Log::error('NewsOptimizationService: Scheduling failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Add news content preview and excerpts
     */
    public function enhanceNewsExcerpts()
    {
        Log::info('NewsOptimizationService: Enhancing news excerpts');
        
        try {
            $newsWithoutExcerpts = DB::table('news')
                ->where('status', 'published')
                ->where(function($query) {
                    $query->whereNull('excerpt')
                          ->orWhere('excerpt', '')
                          ->orWhere('excerpt', 'like', 'Lorem ipsum%');
                })
                ->select(['id', 'title', 'content', 'excerpt'])
                ->get();

            $enhancedCount = 0;

            foreach ($newsWithoutExcerpts as $news) {
                $optimizedExcerpt = $this->generateOptimizedExcerpt($news->content, $news->title);
                
                DB::table('news')
                    ->where('id', $news->id)
                    ->update([
                        'excerpt' => $optimizedExcerpt,
                        'updated_at' => now()
                    ]);
                
                $enhancedCount++;
            }

            return [
                'status' => 'success',
                'enhanced_count' => $enhancedCount
            ];

        } catch (\Exception $e) {
            Log::error('NewsOptimizationService: Excerpt enhancement failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Optimize news search with full-text indexing
     */
    public function optimizeNewsSearch()
    {
        Log::info('NewsOptimizationService: Optimizing news search');
        
        try {
            // Create full-text indexes for better search performance
            $searchOptimizations = [];

            // Add MySQL full-text index if using MySQL
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE news ADD FULLTEXT(title, excerpt, content)');
                $searchOptimizations['fulltext_index'] = 'created';
            }

            // Create search-optimized indexes
            DB::statement('CREATE INDEX IF NOT EXISTS idx_news_search_published ON news(status, published_at DESC, featured DESC) WHERE status = "published"');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_news_search_category ON news(category_id, status, published_at DESC) WHERE status = "published"');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_news_search_tags ON news(tags, status) WHERE status = "published"');
            
            $searchOptimizations['indexes'] = 'optimized';

            // Create search cache warming
            $this->warmSearchCache();
            $searchOptimizations['cache'] = 'warmed';

            return [
                'status' => 'success',
                'optimizations' => $searchOptimizations
            ];

        } catch (\Exception $e) {
            Log::error('NewsOptimizationService: Search optimization failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Implement news analytics and view tracking
     */
    public function implementNewsAnalytics()
    {
        Log::info('NewsOptimizationService: Implementing news analytics');
        
        try {
            // Update view counts and engagement metrics
            $analyticsData = $this->calculateNewsAnalytics();
            
            // Cache analytics for dashboard
            Cache::put('news_analytics', $analyticsData, self::CACHE_TTL);
            
            return [
                'status' => 'success',
                'analytics' => $analyticsData
            ];

        } catch (\Exception $e) {
            Log::error('NewsOptimizationService: Analytics implementation failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Add related articles recommendations
     */
    public function addRelatedArticlesRecommendations()
    {
        Log::info('NewsOptimizationService: Adding related articles recommendations');
        
        try {
            $newsArticles = DB::table('news')
                ->where('status', 'published')
                ->select(['id', 'category_id', 'tags', 'title'])
                ->get();

            $recommendationCount = 0;

            foreach ($newsArticles as $article) {
                $relatedIds = $this->findRelatedArticles($article);
                
                // Cache related articles for quick access
                Cache::put(
                    "related_articles:{$article->id}", 
                    $relatedIds, 
                    self::CACHE_TTL
                );
                
                $recommendationCount++;
            }

            return [
                'status' => 'success',
                'recommendations_generated' => $recommendationCount
            ];

        } catch (\Exception $e) {
            Log::error('NewsOptimizationService: Related articles failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Optimize news loading performance
     */
    public function optimizeNewsLoadingPerformance()
    {
        Log::info('NewsOptimizationService: Optimizing news loading performance');
        
        try {
            $optimizations = [];

            // Pre-warm popular news cache
            $optimizations['cache_warming'] = $this->warmPopularNewsCache();
            
            // Optimize database queries
            $optimizations['query_optimization'] = $this->optimizeNewsQueries();
            
            // Optimize image loading
            $optimizations['image_optimization'] = $this->optimizeNewsImages();
            
            return [
                'status' => 'success',
                'optimizations' => $optimizations
            ];

        } catch (\Exception $e) {
            Log::error('NewsOptimizationService: Performance optimization failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Run comprehensive news optimization
     */
    public function runComprehensiveOptimization()
    {
        Log::info('NewsOptimizationService: Starting comprehensive optimization');
        
        $startTime = microtime(true);
        $results = [];

        // Run all optimizations
        $results['image_display'] = $this->fixNewsImageDisplay();
        $results['seo_optimization'] = $this->optimizeNewsSEO();
        $results['excerpt_enhancement'] = $this->enhanceNewsExcerpts();
        $results['search_optimization'] = $this->optimizeNewsSearch();
        $results['analytics_implementation'] = $this->implementNewsAnalytics();
        $results['related_articles'] = $this->addRelatedArticlesRecommendations();
        $results['performance_optimization'] = $this->optimizeNewsLoadingPerformance();
        $results['scheduling_check'] = $this->implementNewsScheduling();

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $summary = [
            'status' => 'completed',
            'execution_time' => "{$executionTime}ms",
            'completed_at' => now(),
            'optimizations' => $results
        ];

        Log::info('NewsOptimizationService: Comprehensive optimization completed', $summary);
        
        return $summary;
    }

    // =====================================
    // PRIVATE HELPER METHODS
    // =====================================

    /**
     * Optimize image path for proper URL construction
     */
    private function optimizeImagePath($imagePath)
    {
        if (!$imagePath) return null;

        // Remove external URLs that shouldn't be used
        if (preg_match('/https?:\/\/(?!staging\.mrvl\.net|mrvl\.net)/', $imagePath)) {
            return null;
        }

        // Fix old domain references
        if (strpos($imagePath, '1039tfjgievqa983.mrvl.net') !== false) {
            return str_replace('https://1039tfjgievqa983.mrvl.net', '', $imagePath);
        }

        // Normalize storage paths
        if (strpos($imagePath, 'storage/') === 0) {
            return $imagePath;
        }

        if (strpos($imagePath, '/storage/') === 0) {
            return substr($imagePath, 1);
        }

        return $imagePath;
    }

    /**
     * Check if image exists in storage
     */
    private function imageExists($imagePath)
    {
        if (!$imagePath) return false;

        // Check if it's a URL
        if (preg_match('/^https?:\/\//', $imagePath)) {
            return true; // Assume external URLs are valid
        }

        // Check if file exists in storage
        $fullPath = storage_path('app/public/' . ltrim($imagePath, 'storage/'));
        return file_exists($fullPath);
    }

    /**
     * Get fallback image for news
     */
    private function getNewsFallbackImage($title = 'News')
    {
        // Return a path to a reliable placeholder
        return 'images/news-placeholder.svg';
    }

    /**
     * Generate optimized meta data for SEO
     */
    private function generateOptimizedMetaData($article)
    {
        $existingMeta = $article->meta_data ? json_decode($article->meta_data, true) : [];
        
        $optimizedMeta = [
            'title' => $this->generateOptimizedTitle($article->title),
            'description' => $this->generateOptimizedDescription($article->excerpt, $article->content),
            'keywords' => $this->extractKeywords($article->title, $article->content),
            'canonical_url' => url("/news/{$article->slug}"),
            'og_title' => $article->title,
            'og_description' => $this->generateOptimizedDescription($article->excerpt, $article->content),
            'og_type' => 'article',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $article->title,
            'twitter_description' => $this->generateOptimizedDescription($article->excerpt, $article->content),
            'generated_at' => now()->toISOString()
        ];

        return array_merge($existingMeta, $optimizedMeta);
    }

    /**
     * Generate optimized title for SEO
     */
    private function generateOptimizedTitle($title)
    {
        $maxLength = 60;
        $suffix = ' | MRVL';
        
        if (strlen($title . $suffix) <= $maxLength) {
            return $title . $suffix;
        }
        
        return substr($title, 0, $maxLength - strlen($suffix)) . $suffix;
    }

    /**
     * Generate optimized description for SEO
     */
    private function generateOptimizedDescription($excerpt, $content)
    {
        $maxLength = 160;
        
        if ($excerpt && strlen($excerpt) <= $maxLength) {
            return $excerpt;
        }
        
        if ($excerpt && strlen($excerpt) > $maxLength) {
            return substr($excerpt, 0, $maxLength - 3) . '...';
        }
        
        // Generate from content
        $cleanContent = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $cleanContent);
        $description = '';
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($description . $sentence) <= $maxLength - 3) {
                $description .= $sentence . '. ';
            } else {
                break;
            }
        }
        
        return trim($description) ?: substr($cleanContent, 0, $maxLength - 3) . '...';
    }

    /**
     * Extract keywords from content
     */
    private function extractKeywords($title, $content)
    {
        $keywords = ['Marvel Rivals', 'MRVL', 'esports', 'gaming'];
        
        // Extract keywords from title
        $titleWords = array_filter(
            explode(' ', strtolower($title)),
            function($word) { return strlen($word) > 3; }
        );
        
        // Add relevant title words
        foreach ($titleWords as $word) {
            if (!in_array($word, ['the', 'and', 'for', 'with', 'from'])) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }

    /**
     * Generate optimized excerpt from content
     */
    private function generateOptimizedExcerpt($content, $title = '')
    {
        if (!$content) return '';
        
        $maxLength = 200;
        $cleanContent = strip_tags($content);
        
        // Find the first complete sentence that makes sense
        $sentences = preg_split('/[.!?]+/', $cleanContent);
        $excerpt = '';
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) < 20) continue; // Skip very short sentences
            
            if (strlen($excerpt . $sentence) <= $maxLength - 3) {
                $excerpt .= $sentence . '. ';
            } else {
                break;
            }
        }
        
        $excerpt = trim($excerpt);
        
        // If no good excerpt found, use first N characters
        if (!$excerpt) {
            $excerpt = substr($cleanContent, 0, $maxLength - 3) . '...';
        }
        
        return $excerpt;
    }

    /**
     * Warm search cache with popular queries
     */
    private function warmSearchCache()
    {
        $popularSearches = [
            'tournament', 'match', 'player', 'team', 'update', 'patch',
            'marvel rivals', 'championship', 'results', 'news'
        ];
        
        foreach ($popularSearches as $search) {
            $results = DB::table('news')
                ->where('status', 'published')
                ->where(function($query) use ($search) {
                    $query->where('title', 'LIKE', "%{$search}%")
                          ->orWhere('content', 'LIKE', "%{$search}%")
                          ->orWhere('excerpt', 'LIKE', "%{$search}%");
                })
                ->select(['id', 'title', 'slug', 'excerpt', 'published_at'])
                ->orderBy('published_at', 'desc')
                ->limit(10)
                ->get();
            
            Cache::put("news_search:{$search}", $results, self::CACHE_TTL);
        }
    }

    /**
     * Calculate news analytics
     */
    private function calculateNewsAnalytics()
    {
        return [
            'total_articles' => DB::table('news')->where('status', 'published')->count(),
            'total_views' => DB::table('news')->where('status', 'published')->sum('views'),
            'total_comments' => DB::table('news_comments')->where('status', 'active')->count(),
            'avg_engagement' => DB::table('news')->where('status', 'published')->avg('score'),
            'top_categories' => DB::table('news as n')
                ->join('news_categories as nc', 'n.category_id', '=', 'nc.id')
                ->where('n.status', 'published')
                ->select('nc.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(n.views) as total_views'))
                ->groupBy('nc.id', 'nc.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'recent_activity' => [
                'articles_today' => DB::table('news')
                    ->where('status', 'published')
                    ->whereDate('published_at', today())
                    ->count(),
                'comments_today' => DB::table('news_comments')
                    ->where('status', 'active')
                    ->whereDate('created_at', today())
                    ->count()
            ]
        ];
    }

    /**
     * Find related articles based on category and tags
     */
    private function findRelatedArticles($article)
    {
        $related = collect();
        
        // Find by same category
        $categoryRelated = DB::table('news')
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->limit(3)
            ->pluck('id');
        
        $related = $related->merge($categoryRelated);
        
        // Find by similar tags if available
        if ($article->tags) {
            $tags = json_decode($article->tags, true);
            if (is_array($tags) && count($tags) > 0) {
                foreach ($tags as $tag) {
                    $tagRelated = DB::table('news')
                        ->where('tags', 'LIKE', "%{$tag}%")
                        ->where('id', '!=', $article->id)
                        ->where('status', 'published')
                        ->orderBy('published_at', 'desc')
                        ->limit(2)
                        ->pluck('id');
                    
                    $related = $related->merge($tagRelated);
                }
            }
        }
        
        // Remove duplicates and limit to 5
        return $related->unique()->take(5)->values()->toArray();
    }

    /**
     * Warm popular news cache
     */
    private function warmPopularNewsCache()
    {
        $popularNews = DB::table('news')
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByRaw('(views * 0.3 + comments_count * 2 + score * 1.5) DESC')
            ->limit(20)
            ->get();
        
        Cache::put('popular_news', $popularNews, self::CACHE_TTL);
        
        return 'cached_' . $popularNews->count() . '_articles';
    }

    /**
     * Optimize news queries with indexes
     */
    private function optimizeNewsQueries()
    {
        $optimizations = [];
        
        try {
            // Create optimized indexes for common query patterns
            DB::statement('CREATE INDEX IF NOT EXISTS idx_news_homepage ON news(status, featured DESC, published_at DESC) WHERE status = "published"');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_news_category_published ON news(category_id, status, published_at DESC) WHERE status = "published"');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_news_author_published ON news(author_id, status, published_at DESC) WHERE status = "published"');
            
            $optimizations['indexes'] = 'created';
            
            // Update table statistics for better query planning
            DB::statement('ANALYZE TABLE news');
            $optimizations['statistics'] = 'updated';
            
        } catch (\Exception $e) {
            $optimizations['error'] = $e->getMessage();
        }
        
        return $optimizations;
    }

    /**
     * Optimize news images for faster loading
     */
    private function optimizeNewsImages()
    {
        $optimizations = [];
        
        try {
            // Cache image URLs for faster access
            $newsImages = DB::table('news')
                ->where('status', 'published')
                ->whereNotNull('featured_image')
                ->select(['id', 'featured_image'])
                ->get();
            
            $optimizedCount = 0;
            
            foreach ($newsImages as $news) {
                $optimizedPath = $this->optimizeImagePath($news->featured_image);
                Cache::put("news_image:{$news->id}", $optimizedPath, self::IMAGE_CACHE_TTL);
                $optimizedCount++;
            }
            
            $optimizations['cached_images'] = $optimizedCount;
            
        } catch (\Exception $e) {
            $optimizations['error'] = $e->getMessage();
        }
        
        return $optimizations;
    }

    /**
     * Clear news cache
     */
    private function clearNewsCache($newsId = null)
    {
        if ($newsId) {
            Cache::forget("news:{$newsId}");
            Cache::forget("news_image:{$newsId}");
            Cache::forget("related_articles:{$newsId}");
        } else {
            Cache::flush();
        }
    }
}