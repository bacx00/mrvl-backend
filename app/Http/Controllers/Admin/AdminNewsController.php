<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiResponseController;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\NewsComment;
use App\Models\User;
use App\Helpers\ImageHelper;
use App\Services\MentionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AdminNewsController extends ApiResponseController
{
    private $mentionService;

    public function __construct(MentionService $mentionService)
    {
        $this->mentionService = $mentionService;
    }

    /**
     * Display a paginated list of all news articles with admin filters
     */
    public function index(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $query = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->leftJoin('news_categories as nc', 'n.category_id', '=', 'nc.id')
                ->select([
                    'n.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'nc.name as category_name',
                    'nc.color as category_color'
                ]);

            // Advanced Filtering
            if ($request->filled('status')) {
                if ($request->status === 'all') {
                    // Show all statuses
                } else {
                    $query->where('n.status', $request->status);
                }
            }

            if ($request->filled('category_id') && $request->category_id !== 'all') {
                $query->where('n.category_id', $request->category_id);
            }

            if ($request->filled('author_id')) {
                $query->where('n.author_id', $request->author_id);
            }

            if ($request->filled('featured')) {
                $query->where('n.featured', $request->boolean('featured'));
            }

            if ($request->filled('breaking')) {
                $query->where('n.breaking', $request->boolean('breaking'));
            }

            // Date range filtering
            if ($request->filled('date_from')) {
                $query->where('n.created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('n.created_at', '<=', $request->date_to . ' 23:59:59');
            }

            // Search functionality
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('n.title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('n.content', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('n.excerpt', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('n.tags', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            $allowedSortFields = ['created_at', 'updated_at', 'published_at', 'title', 'views', 'score', 'comments_count'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'desc';
            }

            $query->orderBy("n.{$sortBy}", $sortOrder);

            // Pagination
            $perPage = min($request->get('per_page', 20), 100); // Limit max items per page
            $news = $query->paginate($perPage);

            // Transform data with additional admin information
            $newsData = collect($news->items())->map(function($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'status' => $article->status,
                    'featured' => (bool)$article->featured,
                    'breaking' => (bool)$article->breaking,
                    'featured_image' => $article->featured_image ? asset('storage/' . $article->featured_image) : null,
                    'author' => [
                        'id' => $article->author_id,
                        'name' => $article->author_name,
                        'avatar' => $this->formatAvatarUrl($article->author_avatar)
                    ],
                    'category' => [
                        'id' => $article->category_id,
                        'name' => $article->category_name,
                        'color' => $article->category_color
                    ],
                    'stats' => [
                        'views' => $article->views ?? 0,
                        'comments' => $article->comments_count ?? 0,
                        'score' => $article->score ?? 0,
                        'upvotes' => $article->upvotes ?? 0,
                        'downvotes' => $article->downvotes ?? 0
                    ],
                    'dates' => [
                        'created_at' => $article->created_at,
                        'updated_at' => $article->updated_at,
                        'published_at' => $article->published_at,
                        'featured_at' => $article->featured_at
                    ],
                    'tags' => $article->tags ? json_decode($article->tags, true) : [],
                    'has_videos' => !empty($article->videos),
                    'sort_order' => $article->sort_order
                ];
            });

            return $this->successResponse([
                'data' => $newsData,
                'pagination' => [
                    'current_page' => $news->currentPage(),
                    'last_page' => $news->lastPage(),
                    'per_page' => $news->perPage(),
                    'total' => $news->total(),
                    'from' => $news->firstItem(),
                    'to' => $news->lastItem()
                ]
            ], 'News articles retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching news articles: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created news article
     */
    public function store(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            // Validation rules
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:news,slug',
                'content' => 'required|string|min:50',
                'excerpt' => 'nullable|string|max:500',
                'category_id' => 'required|exists:news_categories,id',
                'featured_image' => 'nullable|string|max:500',
                'gallery' => 'nullable|array',
                'videos' => 'nullable|array',
                'videos.*.platform' => 'required_with:videos|string|in:youtube,twitch-clip,twitch-video,twitter,vlrgg,generic',
                'videos.*.video_id' => 'nullable|string',
                'videos.*.embed_url' => 'nullable|url',
                'videos.*.original_url' => 'required_with:videos|url',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'status' => 'required|in:draft,published,scheduled,archived,rejected',
                'published_at' => 'nullable|date',
                'featured' => 'boolean',
                'breaking' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
                'region' => 'nullable|string|max:50',
                // SEO metadata
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:320',
                'meta_keywords' => 'nullable|string|max:255',
                'canonical_url' => 'nullable|url'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $data = $validator->validated();

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['title']);
            } else {
                $data['slug'] = Str::slug($data['slug']);
            }

            // Handle publication date
            if ($data['status'] === 'published' && empty($data['published_at'])) {
                $data['published_at'] = now();
            } elseif ($data['status'] === 'scheduled' && empty($data['published_at'])) {
                return $this->errorResponse('Published date is required for scheduled articles', 422);
            }

            // Set author
            $data['author_id'] = auth('api')->id();

            // Prepare JSON fields
            $data['gallery'] = isset($data['gallery']) ? json_encode($data['gallery']) : null;
            $data['videos'] = isset($data['videos']) ? json_encode($data['videos']) : null;
            $data['tags'] = isset($data['tags']) ? json_encode($data['tags']) : null;

            // Handle metadata
            $metaData = [];
            if (!empty($data['meta_title'])) $metaData['title'] = $data['meta_title'];
            if (!empty($data['meta_description'])) $metaData['description'] = $data['meta_description'];
            if (!empty($data['meta_keywords'])) $metaData['keywords'] = $data['meta_keywords'];
            if (!empty($data['canonical_url'])) $metaData['canonical_url'] = $data['canonical_url'];
            
            $data['meta_data'] = !empty($metaData) ? json_encode($metaData) : null;

            // Remove individual meta fields from data array
            unset($data['meta_title'], $data['meta_description'], $data['meta_keywords'], $data['canonical_url']);

            // Set timestamps
            $data['created_at'] = now();
            $data['updated_at'] = now();

            DB::beginTransaction();

            try {
                // Insert news article
                $newsId = DB::table('news')->insertGetId($data);

                // Process mentions
                $this->mentionService->storeMentions($data['title'], 'news', $newsId);
                if (!empty($data['excerpt'])) {
                    $this->mentionService->storeMentions($data['excerpt'], 'news', $newsId);
                }
                $this->mentionService->storeMentions($data['content'], 'news', $newsId);

                // Process video embeds if provided
                if (!empty($data['videos'])) {
                    $this->processVideoEmbeds(json_decode($data['videos'], true), $newsId);
                }

                DB::commit();

                // Fetch the created article with relations
                $createdArticle = $this->getNewsArticle($newsId);

                return $this->createdResponse($createdArticle, 'News article created successfully');

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error creating news article: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified news article
     */
    public function show($newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $article = $this->getNewsArticle($newsId);

            if (!$article) {
                return $this->errorResponse('News article not found', 404);
            }

            return $this->successResponse($article, 'News article retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching news article: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified news article
     */
    public function update(Request $request, $newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            // Check if article exists
            $existingArticle = DB::table('news')->where('id', $newsId)->first();
            if (!$existingArticle) {
                return $this->errorResponse('News article not found', 404);
            }

            // Validation rules
            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'slug' => 'nullable|string|max:255|unique:news,slug,' . $newsId,
                'content' => 'nullable|string|min:50',
                'excerpt' => 'nullable|string|max:500',
                'category_id' => 'nullable|exists:news_categories,id',
                'featured_image' => 'nullable|string|max:500',
                'gallery' => 'nullable|array',
                'videos' => 'nullable|array',
                'videos.*.platform' => 'required_with:videos|string|in:youtube,twitch-clip,twitch-video,twitter,vlrgg,generic',
                'videos.*.video_id' => 'nullable|string',
                'videos.*.embed_url' => 'nullable|url',
                'videos.*.original_url' => 'required_with:videos|url',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'status' => 'nullable|in:draft,published,scheduled,archived,rejected',
                'published_at' => 'nullable|date',
                'featured' => 'nullable|boolean',
                'breaking' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0',
                'region' => 'nullable|string|max:50',
                // SEO metadata
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:320',
                'meta_keywords' => 'nullable|string|max:255',
                'canonical_url' => 'nullable|url'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $data = $validator->validated();

            // Remove null values to avoid overwriting existing data
            $data = array_filter($data, function($value) {
                return $value !== null;
            });

            if (empty($data)) {
                return $this->errorResponse('No valid data provided for update', 422);
            }

            // Generate slug if title is updated
            if (isset($data['title']) && empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $newsId);
            } elseif (isset($data['slug'])) {
                $data['slug'] = Str::slug($data['slug']);
            }

            // Handle status changes
            if (isset($data['status'])) {
                if ($data['status'] === 'published' && empty($data['published_at']) && empty($existingArticle->published_at)) {
                    $data['published_at'] = now();
                } elseif ($data['status'] === 'scheduled' && empty($data['published_at'])) {
                    return $this->errorResponse('Published date is required for scheduled articles', 422);
                }
            }

            // Prepare JSON fields
            if (isset($data['gallery'])) {
                $data['gallery'] = json_encode($data['gallery']);
            }
            if (isset($data['videos'])) {
                $data['videos'] = json_encode($data['videos']);
            }
            if (isset($data['tags'])) {
                $data['tags'] = json_encode($data['tags']);
            }

            // Handle metadata updates
            $existingMeta = $existingArticle->meta_data ? json_decode($existingArticle->meta_data, true) : [];
            
            if (isset($data['meta_title']) || isset($data['meta_description']) || isset($data['meta_keywords']) || isset($data['canonical_url'])) {
                if (isset($data['meta_title'])) $existingMeta['title'] = $data['meta_title'];
                if (isset($data['meta_description'])) $existingMeta['description'] = $data['meta_description'];
                if (isset($data['meta_keywords'])) $existingMeta['keywords'] = $data['meta_keywords'];
                if (isset($data['canonical_url'])) $existingMeta['canonical_url'] = $data['canonical_url'];
                
                $data['meta_data'] = json_encode($existingMeta);
            }

            // Remove individual meta fields from data array
            unset($data['meta_title'], $data['meta_description'], $data['meta_keywords'], $data['canonical_url']);

            // Set updated timestamp
            $data['updated_at'] = now();

            DB::beginTransaction();

            try {
                // Update news article
                DB::table('news')->where('id', $newsId)->update($data);

                // Update mentions if content fields changed
                if (isset($data['title']) || isset($data['excerpt']) || isset($data['content'])) {
                    // Clear existing mentions for this article
                    DB::table('mentions')->where('mentionable_type', 'news')->where('mentionable_id', $newsId)->delete();
                    
                    // Get updated article data for mention processing
                    $updatedArticle = DB::table('news')->where('id', $newsId)->first();
                    
                    $this->mentionService->storeMentions($updatedArticle->title, 'news', $newsId);
                    if (!empty($updatedArticle->excerpt)) {
                        $this->mentionService->storeMentions($updatedArticle->excerpt, 'news', $newsId);
                    }
                    $this->mentionService->storeMentions($updatedArticle->content, 'news', $newsId);
                }

                // Process video embeds if videos were updated
                if (isset($data['videos'])) {
                    $this->processVideoEmbeds(json_decode($data['videos'], true), $newsId);
                }

                DB::commit();

                // Fetch the updated article with relations
                $updatedArticle = $this->getNewsArticle($newsId);

                return $this->successResponse($updatedArticle, 'News article updated successfully');

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error updating news article: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified news article
     */
    public function destroy($newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
                return $this->errorResponse('Unauthorized - Admin access required', 403);
            }

            $article = DB::table('news')->where('id', $newsId)->first();
            if (!$article) {
                return $this->errorResponse('News article not found', 404);
            }

            DB::beginTransaction();

            try {
                // Delete related data
                DB::table('news_comments')->where('news_id', $newsId)->delete();
                DB::table('news_votes')->where('news_id', $newsId)->delete();
                DB::table('news_video_embeds')->where('news_id', $newsId)->delete();
                DB::table('mentions')->where('mentionable_type', 'news')->where('mentionable_id', $newsId)->delete();
                
                // Delete the article
                DB::table('news')->where('id', $newsId)->delete();

                DB::commit();

                return $this->successResponse(null, 'News article deleted successfully');

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting news article: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get news articles statistics
     */
    public function getStatistics()
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $stats = [
                'total_articles' => DB::table('news')->count(),
                'published_articles' => DB::table('news')->where('status', 'published')->count(),
                'draft_articles' => DB::table('news')->where('status', 'draft')->count(),
                'scheduled_articles' => DB::table('news')->where('status', 'scheduled')->count(),
                'featured_articles' => DB::table('news')->where('featured', true)->count(),
                'breaking_articles' => DB::table('news')->where('breaking', true)->count(),
                'total_comments' => DB::table('news_comments')->count(),
                'total_views' => DB::table('news')->sum('views') ?? 0,
                'articles_this_month' => DB::table('news')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                'top_categories' => DB::table('news as n')
                    ->join('news_categories as nc', 'n.category_id', '=', 'nc.id')
                    ->select('nc.name', DB::raw('COUNT(*) as count'))
                    ->groupBy('nc.name')
                    ->orderBy('count', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return $this->successResponse($stats, 'News statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk operations on news articles
     */
    public function bulkOperation(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:delete,publish,unpublish,feature,unfeature,archive,change_category,change_author',
                'news_ids' => 'required|array|min:1',
                'news_ids.*' => 'integer|exists:news,id',
                'category_id' => 'required_if:action,change_category|exists:news_categories,id',
                'author_id' => 'required_if:action,change_author|exists:users,id'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $action = $request->action;
            $newsIds = $request->news_ids;
            $affected = 0;

            DB::beginTransaction();

            try {
                switch ($action) {
                    case 'delete':
                        if (!auth('api')->user()->hasRole('admin')) {
                            return $this->errorResponse('Unauthorized - Admin access required for bulk delete', 403);
                        }
                        
                        // Delete related data first
                        DB::table('news_comments')->whereIn('news_id', $newsIds)->delete();
                        DB::table('news_votes')->whereIn('news_id', $newsIds)->delete();
                        DB::table('news_video_embeds')->whereIn('news_id', $newsIds)->delete();
                        DB::table('mentions')
                            ->where('mentionable_type', 'news')
                            ->whereIn('mentionable_id', $newsIds)
                            ->delete();
                        
                        $affected = DB::table('news')->whereIn('id', $newsIds)->delete();
                        break;

                    case 'publish':
                        $affected = DB::table('news')
                            ->whereIn('id', $newsIds)
                            ->update([
                                'status' => 'published',
                                'published_at' => now(),
                                'updated_at' => now()
                            ]);
                        break;

                    case 'unpublish':
                        $affected = DB::table('news')
                            ->whereIn('id', $newsIds)
                            ->update([
                                'status' => 'draft',
                                'updated_at' => now()
                            ]);
                        break;

                    case 'feature':
                        $affected = DB::table('news')
                            ->whereIn('id', $newsIds)
                            ->update([
                                'featured' => true,
                                'featured_at' => now(),
                                'updated_at' => now()
                            ]);
                        break;

                    case 'unfeature':
                        $affected = DB::table('news')
                            ->whereIn('id', $newsIds)
                            ->update([
                                'featured' => false,
                                'featured_at' => null,
                                'updated_at' => now()
                            ]);
                        break;

                    case 'archive':
                        $affected = DB::table('news')
                            ->whereIn('id', $newsIds)
                            ->update([
                                'status' => 'archived',
                                'updated_at' => now()
                            ]);
                        break;

                    case 'change_category':
                        $affected = DB::table('news')
                            ->whereIn('id', $newsIds)
                            ->update([
                                'category_id' => $request->category_id,
                                'updated_at' => now()
                            ]);
                        break;

                    case 'change_author':
                        $affected = DB::table('news')
                            ->whereIn('id', $newsIds)
                            ->update([
                                'author_id' => $request->author_id,
                                'updated_at' => now()
                            ]);
                        break;
                }

                DB::commit();

                return $this->successResponse(
                    ['affected_count' => $affected], 
                    "Bulk {$action} operation completed successfully. {$affected} articles affected."
                );

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error performing bulk operation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific news article with full admin details
     */
    private function getNewsArticle($newsId)
    {
        $article = DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->leftJoin('news_categories as nc', 'n.category_id', '=', 'nc.id')
            ->where('n.id', $newsId)
            ->select([
                'n.*',
                'u.name as author_name',
                'u.avatar as author_avatar',
                'nc.name as category_name',
                'nc.color as category_color'
            ])
            ->first();

        if (!$article) {
            return null;
        }

        // Get video embeds
        $videos = $this->getArticleVideos($article);

        // Get comments count and recent comments
        $commentsCount = DB::table('news_comments')->where('news_id', $newsId)->where('status', 'active')->count();
        $recentComments = DB::table('news_comments as nc')
            ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
            ->where('nc.news_id', $newsId)
            ->where('nc.status', 'active')
            ->select(['nc.*', 'u.name as author_name'])
            ->orderBy('nc.created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'content' => $article->content,
            'excerpt' => $article->excerpt,
            'status' => $article->status,
            'featured' => (bool)$article->featured,
            'breaking' => (bool)$article->breaking,
            'featured_image' => $article->featured_image ? asset('storage/' . $article->featured_image) : null,
            'gallery' => $article->gallery ? json_decode($article->gallery, true) : [],
            'videos' => $videos,
            'author' => [
                'id' => $article->author_id,
                'name' => $article->author_name,
                'avatar' => $this->formatAvatarUrl($article->author_avatar)
            ],
            'category' => [
                'id' => $article->category_id,
                'name' => $article->category_name,
                'color' => $article->category_color
            ],
            'stats' => [
                'views' => $article->views ?? 0,
                'comments' => $commentsCount,
                'score' => $article->score ?? 0,
                'upvotes' => $article->upvotes ?? 0,
                'downvotes' => $article->downvotes ?? 0
            ],
            'dates' => [
                'created_at' => $article->created_at,
                'updated_at' => $article->updated_at,
                'published_at' => $article->published_at,
                'featured_at' => $article->featured_at
            ],
            'tags' => $article->tags ? json_decode($article->tags, true) : [],
            'meta_data' => $article->meta_data ? json_decode($article->meta_data, true) : [],
            'region' => $article->region,
            'sort_order' => $article->sort_order,
            'recent_comments' => $recentComments
        ];
    }

    /**
     * Generate a unique slug for the article
     */
    private function generateUniqueSlug($title, $excludeId = null)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        $query = DB::table('news')->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
            
            $query = DB::table('news')->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Process video embeds for an article
     */
    private function processVideoEmbeds($videos, $newsId)
    {
        if (!$videos || empty($videos)) {
            return;
        }

        // Clear existing video embeds
        DB::table('news_video_embeds')->where('news_id', $newsId)->delete();

        // Insert new video embeds
        foreach ($videos as $video) {
            try {
                DB::table('news_video_embeds')->insert([
                    'news_id' => $newsId,
                    'platform' => $video['platform'],
                    'video_id' => $video['video_id'] ?? null,
                    'embed_url' => $video['embed_url'] ?? null,
                    'original_url' => $video['original_url'],
                    'title' => $video['title'] ?? null,
                    'thumbnail' => $video['thumbnail'] ?? null,
                    'duration' => $video['duration'] ?? null,
                    'metadata' => isset($video['metadata']) ? json_encode($video['metadata']) : null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $e) {
                \Log::error('Error processing video embed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get video data for an article
     */
    private function getArticleVideos($article)
    {
        $videos = [];

        // Get videos from JSON column
        if (!empty($article->videos)) {
            try {
                $storedVideos = json_decode($article->videos, true);
                if (is_array($storedVideos)) {
                    $videos = array_merge($videos, $storedVideos);
                }
            } catch (\Exception $e) {
                \Log::error('Error parsing stored videos: ' . $e->getMessage());
            }
        }

        // Get videos from separate table
        try {
            $dbVideos = DB::table('news_video_embeds')
                ->where('news_id', $article->id)
                ->get()
                ->map(function($video) {
                    return [
                        'platform' => $video->platform,
                        'video_id' => $video->video_id,
                        'embed_url' => $video->embed_url,
                        'original_url' => $video->original_url,
                        'title' => $video->title,
                        'thumbnail' => $video->thumbnail,
                        'duration' => $video->duration,
                        'metadata' => $video->metadata ? json_decode($video->metadata, true) : null
                    ];
                })
                ->toArray();

            $videos = array_merge($videos, $dbVideos);
        } catch (\Exception $e) {
            \Log::error('Error fetching video embeds: ' . $e->getMessage());
        }

        return $videos;
    }

    // ===================================
    // CONTENT MODERATION FEATURES
    // ===================================

    /**
     * Get pending news articles for moderation
     */
    public function getPendingNews(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $query = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->leftJoin('news_categories as nc', 'n.category_id', '=', 'nc.id')
                ->whereIn('n.status', ['draft', 'scheduled', 'rejected'])
                ->select([
                    'n.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'nc.name as category_name',
                    'nc.color as category_color'
                ]);

            // Filter by specific status
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('n.status', $request->status);
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->where('n.created_at', '>=', $request->date_from);
            }

            $query->orderBy('n.created_at', 'desc');

            $perPage = min($request->get('per_page', 20), 100);
            $news = $query->paginate($perPage);

            $newsData = collect($news->items())->map(function($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'status' => $article->status,
                    'featured' => (bool)$article->featured,
                    'breaking' => (bool)$article->breaking,
                    'featured_image' => $article->featured_image ? asset('storage/' . $article->featured_image) : null,
                    'author' => [
                        'id' => $article->author_id,
                        'name' => $article->author_name,
                        'avatar' => $this->formatAvatarUrl($article->author_avatar)
                    ],
                    'category' => [
                        'id' => $article->category_id,
                        'name' => $article->category_name,
                        'color' => $article->category_color
                    ],
                    'dates' => [
                        'created_at' => $article->created_at,
                        'updated_at' => $article->updated_at,
                        'published_at' => $article->published_at
                    ],
                    'tags' => $article->tags ? json_decode($article->tags, true) : []
                ];
            });

            return $this->successResponse([
                'data' => $newsData,
                'pagination' => [
                    'current_page' => $news->currentPage(),
                    'last_page' => $news->lastPage(),
                    'per_page' => $news->perPage(),
                    'total' => $news->total()
                ]
            ], 'Pending news articles retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching pending news: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve a news article
     */
    public function approveNews(Request $request, $newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'publish_immediately' => 'boolean',
                'published_at' => 'nullable|date|after_or_equal:now',
                'featured' => 'boolean',
                'breaking' => 'boolean',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $article = DB::table('news')->where('id', $newsId)->first();
            if (!$article) {
                return $this->errorResponse('News article not found', 404);
            }

            if ($article->status === 'published') {
                return $this->errorResponse('Article is already published', 422);
            }

            $updateData = [
                'status' => 'published',
                'updated_at' => now()
            ];

            // Handle publication timing
            if ($request->boolean('publish_immediately', true)) {
                $updateData['published_at'] = now();
            } elseif ($request->filled('published_at')) {
                $updateData['published_at'] = $request->published_at;
                $updateData['status'] = 'scheduled';
            } else {
                $updateData['published_at'] = now();
            }

            // Handle featured/breaking status
            if ($request->has('featured')) {
                $updateData['featured'] = $request->boolean('featured');
                if ($updateData['featured']) {
                    $updateData['featured_at'] = now();
                }
            }

            if ($request->has('breaking')) {
                $updateData['breaking'] = $request->boolean('breaking');
            }

            DB::beginTransaction();

            try {
                // Update the article
                DB::table('news')->where('id', $newsId)->update($updateData);

                // Log the moderation action
                $this->logModerationAction('approve_news', 'news', $newsId, $request->notes);

                DB::commit();

                $updatedArticle = $this->getNewsArticle($newsId);

                return $this->successResponse($updatedArticle, 'News article approved successfully');

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error approving news article: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject a news article
     */
    public function rejectNews(Request $request, $newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
                'notify_author' => 'boolean'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $article = DB::table('news')->where('id', $newsId)->first();
            if (!$article) {
                return $this->errorResponse('News article not found', 404);
            }

            if ($article->status === 'published') {
                return $this->errorResponse('Cannot reject published article', 422);
            }

            DB::beginTransaction();

            try {
                // Update article status
                DB::table('news')->where('id', $newsId)->update([
                    'status' => 'rejected',
                    'updated_at' => now()
                ]);

                // Log the moderation action
                $this->logModerationAction('reject_news', 'news', $newsId, $request->reason);

                // TODO: Implement notification system for author if notify_author is true

                DB::commit();

                return $this->successResponse(null, 'News article rejected successfully');

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error rejecting news article: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Flag a news article for review
     */
    public function flagNews(Request $request, $newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'flag_type' => 'required|in:inappropriate,spam,misleading,copyright,other',
                'reason' => 'required|string|max:500',
                'priority' => 'required|in:low,medium,high,critical'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $article = DB::table('news')->where('id', $newsId)->first();
            if (!$article) {
                return $this->errorResponse('News article not found', 404);
            }

            DB::beginTransaction();

            try {
                // Create flag record
                $flagId = DB::table('content_flags')->insertGetId([
                    'flaggable_type' => 'news',
                    'flaggable_id' => $newsId,
                    'flagger_id' => auth('api')->id(),
                    'flag_type' => $request->flag_type,
                    'reason' => $request->reason,
                    'priority' => $request->priority,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Log the moderation action
                $this->logModerationAction('flag_news', 'news', $newsId, "Flag: {$request->flag_type} - {$request->reason}");

                DB::commit();

                return $this->createdResponse(['flag_id' => $flagId], 'News article flagged successfully');

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error flagging news article: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get flagged content for review
     */
    public function getFlaggedContent(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $query = DB::table('content_flags as cf')
                ->join('news as n', 'cf.flaggable_id', '=', 'n.id')
                ->leftJoin('users as flagger', 'cf.flagger_id', '=', 'flagger.id')
                ->leftJoin('users as author', 'n.author_id', '=', 'author.id')
                ->where('cf.flaggable_type', 'news')
                ->select([
                    'cf.*',
                    'n.title as news_title',
                    'n.slug as news_slug',
                    'n.status as news_status',
                    'flagger.name as flagger_name',
                    'author.name as author_name'
                ]);

            // Filter by status
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('cf.status', $request->status);
            }

            // Filter by flag type
            if ($request->filled('flag_type') && $request->flag_type !== 'all') {
                $query->where('cf.flag_type', $request->flag_type);
            }

            // Filter by priority
            if ($request->filled('priority') && $request->priority !== 'all') {
                $query->where('cf.priority', $request->priority);
            }

            // Sort by priority and creation date
            $query->orderByRaw("FIELD(cf.priority, 'critical', 'high', 'medium', 'low')")
                  ->orderBy('cf.created_at', 'desc');

            $perPage = min($request->get('per_page', 20), 100);
            $flags = $query->paginate($perPage);

            return $this->successResponse([
                'data' => $flags->items(),
                'pagination' => [
                    'current_page' => $flags->currentPage(),
                    'last_page' => $flags->lastPage(),
                    'per_page' => $flags->perPage(),
                    'total' => $flags->total()
                ]
            ], 'Flagged content retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching flagged content: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Resolve a content flag
     */
    public function resolveFlag(Request $request, $flagId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:dismiss,uphold,escalate',
                'resolution_notes' => 'nullable|string|max:500',
                'content_action' => 'nullable|in:none,edit,unpublish,delete'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $flag = DB::table('content_flags')->where('id', $flagId)->first();
            if (!$flag) {
                return $this->errorResponse('Flag not found', 404);
            }

            DB::beginTransaction();

            try {
                // Update flag status
                DB::table('content_flags')->where('id', $flagId)->update([
                    'status' => $request->action === 'dismiss' ? 'dismissed' : 
                               ($request->action === 'uphold' ? 'upheld' : 'escalated'),
                    'resolved_by' => auth('api')->id(),
                    'resolved_at' => now(),
                    'resolution_notes' => $request->resolution_notes,
                    'updated_at' => now()
                ]);

                // Take content action if specified
                if ($request->filled('content_action') && $request->content_action !== 'none') {
                    switch ($request->content_action) {
                        case 'unpublish':
                            DB::table('news')
                                ->where('id', $flag->flaggable_id)
                                ->update(['status' => 'draft', 'updated_at' => now()]);
                            break;
                        
                        case 'delete':
                            if (auth('api')->user()->hasRole('admin')) {
                                DB::table('news')->where('id', $flag->flaggable_id)->delete();
                            }
                            break;
                    }
                }

                // Log the moderation action
                $this->logModerationAction(
                    'resolve_flag', 
                    'content_flag', 
                    $flagId, 
                    "Action: {$request->action} - {$request->resolution_notes}"
                );

                DB::commit();

                return $this->successResponse(null, 'Flag resolved successfully');

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error resolving flag: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Feature/unfeature a news article
     */
    public function toggleFeature(Request $request, $newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $article = DB::table('news')->where('id', $newsId)->first();
            if (!$article) {
                return $this->errorResponse('News article not found', 404);
            }

            $newFeaturedStatus = !$article->featured;
            
            $updateData = [
                'featured' => $newFeaturedStatus,
                'updated_at' => now()
            ];

            if ($newFeaturedStatus) {
                $updateData['featured_at'] = now();
            } else {
                $updateData['featured_at'] = null;
            }

            DB::table('news')->where('id', $newsId)->update($updateData);

            $action = $newFeaturedStatus ? 'featured' : 'unfeatured';
            $this->logModerationAction($action . '_news', 'news', $newsId);

            return $this->successResponse(
                ['featured' => $newFeaturedStatus], 
                "News article {$action} successfully"
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error toggling feature status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get moderation history for a news article
     */
    public function getModerationHistory($newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $history = DB::table('moderation_logs as ml')
                ->leftJoin('users as u', 'ml.moderator_id', '=', 'u.id')
                ->where('ml.target_type', 'news')
                ->where('ml.target_id', $newsId)
                ->select([
                    'ml.*',
                    'u.name as moderator_name',
                    'u.avatar as moderator_avatar'
                ])
                ->orderBy('ml.created_at', 'desc')
                ->get();

            return $this->successResponse($history, 'Moderation history retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching moderation history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle publish/unpublish a news article
     */
    public function togglePublish(Request $request, $newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $article = DB::table('news')->where('id', $newsId)->first();
            if (!$article) {
                return $this->errorResponse('News article not found', 404);
            }

            $newStatus = $article->status === 'published' ? 'draft' : 'published';
            
            $updateData = [
                'status' => $newStatus,
                'updated_at' => now()
            ];

            if ($newStatus === 'published' && empty($article->published_at)) {
                $updateData['published_at'] = now();
            }

            DB::table('news')->where('id', $newsId)->update($updateData);

            $action = $newStatus === 'published' ? 'published' : 'unpublished';
            $this->logModerationAction($action . '_news', 'news', $newsId);

            return $this->successResponse(
                ['status' => $newStatus], 
                "News article {$action} successfully"
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error toggling publish status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search news articles
     */
    public function search(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2',
                'category_id' => 'nullable|exists:news_categories,id',
                'status' => 'nullable|in:draft,published,scheduled,archived,rejected',
                'featured' => 'nullable|boolean',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $query = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->leftJoin('news_categories as nc', 'n.category_id', '=', 'nc.id')
                ->select([
                    'n.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'nc.name as category_name',
                    'nc.color as category_color'
                ]);

            // Search in title, content, excerpt, and tags
            $searchTerm = $request->input('query');
            $query->where(function($q) use ($searchTerm) {
                $q->where('n.title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('n.content', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('n.excerpt', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('n.tags', 'LIKE', "%{$searchTerm}%");
            });

            // Apply filters
            if ($request->filled('category_id')) {
                $query->where('n.category_id', $request->input('category_id'));
            }

            if ($request->filled('status')) {
                $query->where('n.status', $request->input('status'));
            }

            if ($request->filled('featured')) {
                $query->where('n.featured', $request->boolean('featured'));
            }

            if ($request->filled('date_from')) {
                $query->where('n.created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->where('n.created_at', '<=', $request->input('date_to') . ' 23:59:59');
            }

            $query->orderBy('n.created_at', 'desc');

            $perPage = min($request->get('per_page', 20), 100);
            $results = $query->paginate($perPage);

            $newsData = collect($results->items())->map(function($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'status' => $article->status,
                    'featured' => (bool)$article->featured,
                    'breaking' => (bool)$article->breaking,
                    'featured_image' => $article->featured_image ? asset('storage/' . $article->featured_image) : null,
                    'author' => [
                        'id' => $article->author_id,
                        'name' => $article->author_name,
                        'avatar' => $this->formatAvatarUrl($article->author_avatar)
                    ],
                    'category' => [
                        'id' => $article->category_id,
                        'name' => $article->category_name,
                        'color' => $article->category_color
                    ],
                    'stats' => [
                        'views' => $article->views ?? 0,
                        'comments' => $article->comments_count ?? 0,
                        'score' => $article->score ?? 0
                    ],
                    'dates' => [
                        'created_at' => $article->created_at,
                        'updated_at' => $article->updated_at,
                        'published_at' => $article->published_at
                    ],
                    'tags' => $article->tags ? json_decode($article->tags, true) : []
                ];
            });

            return $this->successResponse([
                'data' => $newsData,
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total()
                ]
            ], 'Search results retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error performing search: ' . $e->getMessage(), 500);
        }
    }

    // ===================================
    // COMMENTS MODERATION FUNCTIONALITY
    // ===================================

    /**
     * Get news comments for moderation
     */
    public function getNewsComments(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $query = DB::table('news_comments as nc')
                ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
                ->leftJoin('news as n', 'nc.news_id', '=', 'n.id')
                ->select([
                    'nc.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'n.title as news_title',
                    'n.slug as news_slug'
                ]);

            // Filter by status
            if ($request->filled('status')) {
                $query->where('nc.status', $request->status);
            }

            // Filter by news article
            if ($request->filled('news_id')) {
                $query->where('nc.news_id', $request->news_id);
            }

            // Search in content
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where('nc.content', 'LIKE', "%{$searchTerm}%");
            }

            // Date range filtering
            if ($request->filled('date_from')) {
                $query->where('nc.created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('nc.created_at', '<=', $request->date_to . ' 23:59:59');
            }

            $query->orderBy('nc.created_at', 'desc');

            $perPage = min($request->get('per_page', 20), 100);
            $comments = $query->paginate($perPage);

            return $this->successResponse([
                'data' => $comments->items(),
                'pagination' => [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total()
                ]
            ], 'News comments retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching comments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get reported comments
     */
    public function getReportedComments(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $query = DB::table('news_comments as nc')
                ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
                ->leftJoin('news as n', 'nc.news_id', '=', 'n.id')
                ->leftJoin('comment_reports as cr', 'nc.id', '=', 'cr.comment_id')
                ->leftJoin('users as reporter', 'cr.reporter_id', '=', 'reporter.id')
                ->where('nc.status', 'flagged')
                ->orWhere('cr.status', 'pending')
                ->select([
                    'nc.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'n.title as news_title',
                    'n.slug as news_slug',
                    'cr.reason as report_reason',
                    'cr.created_at as reported_at',
                    'reporter.name as reporter_name'
                ])
                ->orderBy('cr.created_at', 'desc');

            $perPage = min($request->get('per_page', 20), 100);
            $reportedComments = $query->paginate($perPage);

            return $this->successResponse([
                'data' => $reportedComments->items(),
                'pagination' => [
                    'current_page' => $reportedComments->currentPage(),
                    'last_page' => $reportedComments->lastPage(),
                    'per_page' => $reportedComments->perPage(),
                    'total' => $reportedComments->total()
                ]
            ], 'Reported comments retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching reported comments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Moderate a comment
     */
    public function moderateComment(Request $request, $commentId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,hide,delete,flag',
                'reason' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $comment = DB::table('news_comments')->where('id', $commentId)->first();
            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            DB::beginTransaction();

            try {
                switch ($request->action) {
                    case 'approve':
                        DB::table('news_comments')
                            ->where('id', $commentId)
                            ->update(['status' => 'active', 'updated_at' => now()]);
                        break;

                    case 'hide':
                        DB::table('news_comments')
                            ->where('id', $commentId)
                            ->update(['status' => 'hidden', 'updated_at' => now()]);
                        break;

                    case 'delete':
                        if (!auth('api')->user()->hasRole('admin')) {
                            return $this->errorResponse('Unauthorized - Admin access required for delete', 403);
                        }
                        DB::table('news_comments')->where('id', $commentId)->delete();
                        break;

                    case 'flag':
                        DB::table('news_comments')
                            ->where('id', $commentId)
                            ->update(['status' => 'flagged', 'updated_at' => now()]);
                        break;
                }

                // Log the moderation action
                $this->logModerationAction(
                    $request->action . '_comment',
                    'news_comment',
                    $commentId,
                    $request->reason
                );

                DB::commit();

                return $this->successResponse(null, "Comment {$request->action}d successfully");

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error moderating comment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a comment (admin only)
     */
    public function deleteComment($commentId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
                return $this->errorResponse('Unauthorized - Admin access required', 403);
            }

            $comment = DB::table('news_comments')->where('id', $commentId)->first();
            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            DB::table('news_comments')->where('id', $commentId)->delete();

            // Log the moderation action
            $this->logModerationAction('delete_comment', 'news_comment', $commentId);

            return $this->successResponse(null, 'Comment deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting comment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk moderate comments
     */
    public function bulkModerateComments(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,hide,delete,flag',
                'comment_ids' => 'required|array|min:1',
                'comment_ids.*' => 'integer|exists:news_comments,id',
                'reason' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $action = $request->action;
            $commentIds = $request->comment_ids;
            $affected = 0;

            DB::beginTransaction();

            try {
                switch ($action) {
                    case 'approve':
                        $affected = DB::table('news_comments')
                            ->whereIn('id', $commentIds)
                            ->update(['status' => 'active', 'updated_at' => now()]);
                        break;

                    case 'hide':
                        $affected = DB::table('news_comments')
                            ->whereIn('id', $commentIds)
                            ->update(['status' => 'hidden', 'updated_at' => now()]);
                        break;

                    case 'delete':
                        if (!auth('api')->user()->hasRole('admin')) {
                            return $this->errorResponse('Unauthorized - Admin access required for bulk delete', 403);
                        }
                        $affected = DB::table('news_comments')->whereIn('id', $commentIds)->delete();
                        break;

                    case 'flag':
                        $affected = DB::table('news_comments')
                            ->whereIn('id', $commentIds)
                            ->update(['status' => 'flagged', 'updated_at' => now()]);
                        break;
                }

                // Log each moderation action
                foreach ($commentIds as $commentId) {
                    $this->logModerationAction(
                        'bulk_' . $action . '_comment',
                        'news_comment',
                        $commentId,
                        $request->reason
                    );
                }

                DB::commit();

                return $this->successResponse(
                    ['affected_count' => $affected],
                    "Bulk {$action} operation completed successfully. {$affected} comments affected."
                );

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error performing bulk comment moderation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk delete news articles
     */
    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'news_ids' => 'required|array|min:1',
                'news_ids.*' => 'required|integer|exists:news,id'
            ]);

            $newsIds = $request->news_ids;
            
            DB::beginTransaction();
            
            try {
                // Get articles before deletion for logging
                $articles = News::whereIn('id', $newsIds)->get();
                
                // Delete articles
                $deletedCount = News::whereIn('id', $newsIds)->delete();
                
                // Log the action
                foreach ($articles as $article) {
                    $this->logModerationAction('bulk_delete_article', 'news', $article->id, 'Bulk delete operation');
                }
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => "Successfully deleted {$deletedCount} articles",
                    'deleted_count' => $deletedCount
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            \Log::error('Bulk delete news error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete articles'
            ], 500);
        }
    }

    /**
     * Format avatar URL properly
     */
    private function formatAvatarUrl($avatar)
    {
        if (!$avatar) {
            return null;
        }
        
        // If it's already a full URL, return as is
        if (str_starts_with($avatar, 'http')) {
            return $avatar;
        }
        
        // If it's a hero image path, return with proper URL
        if (str_contains($avatar, '/images/heroes/')) {
            return url($avatar);
        }
        
        // Otherwise assume it's a regular uploaded avatar
        return asset('storage/avatars/' . $avatar);
    }

    /**
     * Log moderation actions
     */
    private function logModerationAction($action, $targetType, $targetId, $reason = null)
    {
        try {
            DB::table('moderation_logs')->insert([
                'moderator_id' => auth('api')->id(),
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error logging moderation action: ' . $e->getMessage());
        }
    }
}