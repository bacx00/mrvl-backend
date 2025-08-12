<?php
namespace App\Http\Controllers;

use App\Models\ForumThread;
use App\Models\ForumPost;
use App\Models\ForumVote;
use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Services\ForumSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\MentionService;

class ForumController extends ApiResponseController
{
    private $searchService;
    private $mentionService;

    public function __construct(
        ForumSearchService $searchService,
        MentionService $mentionService
    ) {
        $this->searchService = $searchService;
        $this->mentionService = $mentionService;
    }

    public function index(Request $request)
    {
        try {
            $category = $request->get('category', 'all');
            $sortBy = $request->get('sort', 'latest');
            $page = $request->get('page', 1);

            // Use Laravel's built-in cache instead of Redis-based cache service
            $cacheKey = "forum:threads:{$category}:{$sortBy}:page:{$page}";
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return response()->json($cached);
            }

            // If search is provided, use search service
            if ($request->search) {
                $searchResults = $this->searchService->search($request->search, [
                    'category' => $category !== 'all' ? $category : null
                ], $page);
                
                return response()->json([
                    'data' => $searchResults['threads'],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => 20,
                        'total' => count($searchResults['threads'])
                    ],
                    'success' => true
                ]);
            }

            // Use optimized query with proper indexing and accurate reply counts
            $query = DB::table('forum_threads as ft')
                ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
                ->leftJoin(
                    DB::raw('(SELECT thread_id, COUNT(*) as actual_replies_count FROM forum_posts WHERE status = "active" GROUP BY thread_id) as reply_counts'),
                    'ft.id', '=', 'reply_counts.thread_id'
                )
                ->select([
                    'ft.id', 'ft.title', 'ft.content', 'ft.user_id', 'ft.category',
                    DB::raw('COALESCE(reply_counts.actual_replies_count, 0) as replies_count'),
                    'ft.views', 'ft.score', 'ft.upvotes', 'ft.downvotes',
                    'ft.pinned', 'ft.locked', 'ft.created_at', 'ft.last_reply_at',
                    'u.name as author_name', 'u.avatar as author_avatar', 
                    'u.hero_flair', 'u.team_flair_id',
                    'fc.name as category_name', 'fc.color as category_color'
                ])
                ->where('ft.status', 'active'); // Only active threads

            // Filter by category with index optimization
            if ($category && $category !== 'all') {
                $query->where('ft.category', $category);
            }

            // Optimized sort options with proper indexing
            switch ($sortBy) {
                case 'popular':
                    $query->orderBy('ft.pinned', 'desc')
                          ->orderBy('ft.score', 'desc')
                          ->orderBy('ft.created_at', 'desc');
                    break;
                case 'hot':
                    $query->orderBy('ft.pinned', 'desc')
                          ->orderByRaw('(ft.replies_count * 0.6 + ft.views * 0.001 + ft.score * 0.4) DESC')
                          ->orderBy('ft.last_reply_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('ft.pinned', 'desc')
                          ->orderBy('ft.created_at', 'asc');
                    break;
                default: // latest
                    $query->orderBy('ft.pinned', 'desc')
                          ->orderBy('ft.last_reply_at', 'desc');
            }

            // Use cursor pagination for better performance on large datasets
            $perPage = min($request->get('per_page', 20), 50); // Limit max per page
            $threads = $query->paginate($perPage);

            // Optimize data transformation - avoid N+1 queries
            $userIds = collect($threads->items())->pluck('user_id')->unique();
            $teamFlairs = $this->getTeamFlairsForUsers($userIds);

            $threadsData = collect($threads->items())->map(function($thread) use ($teamFlairs) {
                $teamFlair = $teamFlairs[$thread->team_flair_id] ?? null;
                
                return [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'content' => $thread->content,
                    'author' => [
                        'id' => $thread->user_id,
                        'name' => $thread->author_name,
                        'username' => $thread->author_name,
                        'avatar' => $thread->author_avatar,
                        'hero_flair' => $thread->hero_flair,
                        'show_hero_flair' => true,
                        'team_flair' => $teamFlair,
                        'show_team_flair' => (bool)$teamFlair,
                        'roles' => []
                    ],
                    'category' => [
                        'name' => $thread->category_name ?? $thread->category,
                        'color' => $thread->category_color ?? '#6b7280'
                    ],
                    'stats' => [
                        'views' => (int)$thread->views,
                        'replies' => (int)$thread->replies_count,
                        'score' => (int)$thread->score,
                        'upvotes' => (int)$thread->upvotes,
                        'downvotes' => (int)$thread->downvotes
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
                    ],
                    'mentions' => $this->mentionService->extractMentions($thread->content)
                ];
            });

            $result = [
                'data' => $threadsData,
                'pagination' => [
                    'current_page' => $threads->currentPage(),
                    'last_page' => $threads->lastPage(),
                    'per_page' => $threads->perPage(),
                    'total' => $threads->total()
                ],
                'success' => true
            ];

            // Cache the result using Laravel's cache
            $ttl = ($sortBy === 'hot' || $sortBy === 'latest') ? 60 : 300; // 1 min for hot/latest, 5 min for others
            Cache::put($cacheKey, $result, $ttl);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching forum threads: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($threadId)
    {
        try {
            // Get thread details
            $thread = DB::table('forum_threads as ft')
                ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                ->where('ft.id', $threadId)
                ->select([
                    'ft.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'ft.category as category_name'
                ])
                ->first();

            if (!$thread) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thread not found'
                ], 404);
            }

            // Get all posts/replies for this thread with nested structure
            $posts = $this->getThreadPosts($threadId);

            // Increment view count
            DB::table('forum_threads')->where('id', $threadId)->increment('views');

            // Get user's vote on thread (if authenticated)
            $userVote = null;
            if (auth('api')->check()) {
                $userVote = DB::table('forum_votes')
                    ->where('thread_id', $threadId)
                    ->where('user_id', auth('api')->id())
                    ->where('post_id', null)
                    ->value('vote_type');
            }

            $threadData = [
                'id' => $thread->id,
                'title' => $thread->title,
                'content' => $thread->content,
                'author' => $this->getUserWithFlairs($thread->user_id),
                'category' => [
                    'name' => $thread->category_name,
                    'color' => '#6b7280'
                ],
                'stats' => [
                    'views' => $thread->views ?? 0,
                    'replies' => $thread->replies_count ?? 0,
                    'score' => $thread->score ?? 0,
                    'upvotes' => $thread->upvotes ?? 0,
                    'downvotes' => $thread->downvotes ?? 0
                ],
                'meta' => [
                    'pinned' => (bool)$thread->pinned,
                    'locked' => (bool)$thread->locked,
                    'created_at' => $thread->created_at ? date('c', strtotime($thread->created_at)) : null,
                    'last_reply_at' => $thread->last_reply_at ? date('c', strtotime($thread->last_reply_at)) : null
                ],
                'mentions' => $this->mentionService->extractMentions($thread->content),
                'user_vote' => $userVote,
                'posts' => $posts
            ];

            return response()->json([
                'data' => $threadData,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPosts($threadId)
    {
        try {
            // Check if thread exists
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thread not found'
                ], 404);
            }

            // Get posts with nested structure
            $posts = $this->getThreadPosts($threadId);

            return response()->json([
                'data' => $posts,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching posts: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10'
        ]);

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string|min:10',
                'category' => 'nullable|string|exists:forum_categories,slug'
            ]);

            $thread = DB::table('forum_threads')->insertGetId([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'category' => $validated['category'] ?? 'general',
                'user_id' => auth('api')->id(),
                'last_reply_at' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Process mentions with better error handling
            $mentionCount = $this->mentionService->storeMentions($validated['content'], 'forum_thread', $thread);
            
            // Clear relevant caches
            Cache::forget("forum:threads:all:latest:page:1");
            Cache::forget("forum:threads:general:latest:page:1");

            return $this->createdResponse([
                'thread' => [
                    'id' => $thread,
                    'title' => $validated['title'],
                    'content' => $validated['content'],
                    'category' => $validated['category'] ?? 'general',
                    'mentions_processed' => $mentionCount
                ],
                'instant_update' => true
            ], 'Thread created successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error creating thread: ' . $e->getMessage());
        }
    }

    public function storePost(Request $request, $threadId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return $this->unauthorizedResponse('Please log in to reply to threads.');
        }
        
        $validated = $request->validate([
            'content' => 'required|string|min:1|max:10000',
            'parent_id' => 'nullable|exists:forum_posts,id'
        ], [
            'content.required' => 'Reply content is required.',
            'content.min' => 'Reply must contain at least 1 character.',
            'content.max' => 'Reply cannot exceed 10,000 characters.',
            'parent_id.exists' => 'Invalid parent post specified.'
        ]);

        try {
            // Check if thread exists and is not locked
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }
            if ($thread->locked == 1 || $thread->locked === true) {
                return response()->json(['success' => false, 'message' => 'Thread is locked'], 403);
            }

            $postId = DB::table('forum_posts')->insertGetId([
                'thread_id' => $threadId,
                'user_id' => auth('api')->id(),
                'content' => $validated['content'],
                'parent_id' => $validated['parent_id'] ?? null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update thread reply count and last reply time in one query
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->update([
                    'replies_count' => DB::raw('replies_count + 1'),
                    'last_reply_at' => now()
                ]);

            // Process mentions and get mention count
            $mentionCount = $this->mentionService->storeMentions($validated['content'], 'forum_post', $postId);
            
            // Get the created post with user details
            $createdPost = DB::table('forum_posts as fp')
                ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
                ->where('fp.id', $postId)
                ->select([
                    'fp.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar'
                ])
                ->first();
                
            $userWithFlairs = $this->getUserWithFlairs($createdPost->user_id);
            $mentions = $this->mentionService->extractMentions($createdPost->content);
            
            $responseData = [
                'id' => (int)$postId,
                'content' => (string)$createdPost->content,
                'author' => $userWithFlairs ?: [
                    'id' => (int)$createdPost->user_id,
                    'name' => (string)$createdPost->author_name,
                    'username' => (string)$createdPost->author_name,
                    'avatar' => $createdPost->author_avatar,
                    'hero_flair' => null,
                    'show_hero_flair' => false,
                    'team_flair' => null,
                    'show_team_flair' => false,
                    'roles' => []
                ],
                'stats' => [
                    'score' => 0,
                    'upvotes' => 0,
                    'downvotes' => 0
                ],
                'meta' => [
                    'created_at' => (string)$createdPost->created_at,
                    'created_at_formatted' => \Carbon\Carbon::parse($createdPost->created_at)->format('M j, Y g:i A'),
                    'created_at_relative' => \Carbon\Carbon::parse($createdPost->created_at)->diffForHumans(),
                    'updated_at' => (string)$createdPost->updated_at,
                    'edited' => false
                ],
                'mentions' => is_array($mentions) ? $mentions : [],
                'user_vote' => null,
                'replies' => [],
                'parent_id' => $createdPost->parent_id ? (int)$createdPost->parent_id : null
            ];

            // Clear relevant caches
            Cache::forget("forum:threads:all:latest:page:1");
            Cache::forget("forum:threads:general:latest:page:1");
            Cache::forget("forum:thread:{$threadId}");
            
            return $this->createdResponse([
                'post' => $responseData,
                'mentions_processed' => $mentionCount,
                'instant_update' => true
            ], 'Reply posted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error posting reply: ' . $e->getMessage());
        }
    }

    public function voteThread(Request $request, $threadId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        try {
            $userId = auth('api')->id();
            $voteType = $request->vote_type;

            // Check if thread exists
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }

            // Use DB transaction for thread voting with improved error handling
            return DB::transaction(function() use ($threadId, $userId, $voteType) {
                // Check for existing thread vote (where post_id is NULL) with proper locking
                $existingVote = DB::table('forum_votes')
                    ->where('thread_id', $threadId)
                    ->where('user_id', $userId)
                    ->whereNull('post_id')
                    ->lockForUpdate()
                    ->first();

                if ($existingVote) {
                    if ($existingVote->vote_type === $voteType) {
                        // Remove vote if same type (toggle off)
                        DB::table('forum_votes')->where('id', $existingVote->id)->delete();
                        $this->updateVoteCounts($threadId);
                        
                        // Get updated vote counts for immediate response
                        $updatedThread = DB::table('forum_threads')
                            ->where('id', $threadId)
                            ->select(['upvotes', 'downvotes', 'score'])
                            ->first();
                            
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote removed',
                            'action' => 'removed',
                            'updated_stats' => [
                                'upvotes' => (int)($updatedThread->upvotes ?? 0),
                                'downvotes' => (int)($updatedThread->downvotes ?? 0),
                                'score' => (int)($updatedThread->score ?? 0)
                            ],
                            'user_vote' => null,
                            'instant_update' => true
                        ]);
                    } else {
                        // Update vote if different type (upvote to downvote or vice versa)
                        DB::table('forum_votes')
                            ->where('id', $existingVote->id)
                            ->update([
                                'vote_type' => $voteType, 
                                'updated_at' => now()
                            ]);
                        
                        $this->updateVoteCounts($threadId);
                        
                        // Get updated vote counts for immediate response
                        $updatedThread = DB::table('forum_threads')
                            ->where('id', $threadId)
                            ->select(['upvotes', 'downvotes', 'score'])
                            ->first();
                            
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote changed successfully',
                            'action' => 'updated',
                            'updated_stats' => [
                                'upvotes' => (int)($updatedThread->upvotes ?? 0),
                                'downvotes' => (int)($updatedThread->downvotes ?? 0),
                                'score' => (int)($updatedThread->score ?? 0)
                            ],
                            'user_vote' => $voteType,
                            'instant_update' => true
                        ]);
                    }
                } else {
                    // Create new thread vote - handle potential race conditions
                    try {
                        DB::table('forum_votes')->insert([
                            'thread_id' => $threadId,
                            'user_id' => $userId,
                            'vote_type' => $voteType,
                            'post_id' => null, // Explicitly set to null for thread votes
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        
                        $this->updateVoteCounts($threadId);
                        
                        // Get updated vote counts for immediate response
                        $updatedThread = DB::table('forum_threads')
                            ->where('id', $threadId)
                            ->select(['upvotes', 'downvotes', 'score'])
                            ->first();
                            
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote recorded successfully',
                            'action' => 'voted',
                            'updated_stats' => [
                                'upvotes' => (int)($updatedThread->upvotes ?? 0),
                                'downvotes' => (int)($updatedThread->downvotes ?? 0),
                                'score' => (int)($updatedThread->score ?? 0)
                            ],
                            'user_vote' => $voteType,
                            'instant_update' => true
                        ]);
                        
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Handle duplicate entry gracefully - user may have double-clicked
                        if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() === '23000') {
                            // Try to get existing vote and update it instead
                            $duplicateVote = DB::table('forum_votes')
                                ->where('thread_id', $threadId)
                                ->where('user_id', $userId)
                                ->whereNull('post_id')
                                ->first();
                            
                            if ($duplicateVote && $duplicateVote->vote_type !== $voteType) {
                                // Update the existing vote
                                DB::table('forum_votes')
                                    ->where('id', $duplicateVote->id)
                                    ->update([
                                        'vote_type' => $voteType,
                                        'updated_at' => now()
                                    ]);
                                
                                $this->updateVoteCounts($threadId);
                                
                                $updatedThread = DB::table('forum_threads')
                                    ->where('id', $threadId)
                                    ->select(['upvotes', 'downvotes', 'score'])
                                    ->first();
                                    
                                return response()->json([
                                    'success' => true,
                                    'message' => 'Vote updated successfully',
                                    'action' => 'updated',
                                    'updated_stats' => [
                                        'upvotes' => (int)($updatedThread->upvotes ?? 0),
                                        'downvotes' => (int)($updatedThread->downvotes ?? 0),
                                        'score' => (int)($updatedThread->score ?? 0)
                                    ],
                                    'user_vote' => $voteType,
                                    'instant_update' => true
                                ]);
                            } else {
                                // Same vote type already exists
                                return response()->json([
                                    'success' => false,
                                    'message' => 'You have already cast this vote on this thread',
                                    'code' => 'VOTE_ALREADY_EXISTS'
                                ], 409);
                            }
                        }
                        throw $e; // Re-throw if not a duplicate entry error
                    }
                }
            });

        } catch (\Exception $e) {
            Log::error('Forum thread voting error', [
                'thread_id' => $threadId,
                'user_id' => auth('api')->id(),
                'vote_type' => $request->vote_type ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing vote. Please try again.',
                'code' => 'VOTE_PROCESSING_ERROR'
            ], 500);
        }
    }

    public function votePost(Request $request, $postId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        try {
            $userId = auth('api')->id();
            $voteType = $request->vote_type;

            // Get the thread ID for this post
            $post = DB::table('forum_posts')->where('id', $postId)->first();
            if (!$post) {
                return response()->json(['success' => false, 'message' => 'Post not found'], 404);
            }

            // Use DB transaction for post voting with improved error handling
            return DB::transaction(function() use ($postId, $post, $userId, $voteType) {
                // Check for existing post vote (where post_id matches) with proper locking
                $existingVote = DB::table('forum_votes')
                    ->where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if ($existingVote) {
                    if ($existingVote->vote_type === $voteType) {
                        // Remove vote if same type (toggle off)
                        DB::table('forum_votes')->where('id', $existingVote->id)->delete();
                        $this->updateVoteCounts($post->thread_id, $postId);
                        
                        // Get updated vote counts for immediate response
                        $updatedPost = DB::table('forum_posts')
                            ->where('id', $postId)
                            ->select(['upvotes', 'downvotes', 'score'])
                            ->first();
                            
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote removed',
                            'action' => 'removed',
                            'updated_stats' => [
                                'upvotes' => (int)($updatedPost->upvotes ?? 0),
                                'downvotes' => (int)($updatedPost->downvotes ?? 0),
                                'score' => (int)($updatedPost->score ?? 0)
                            ],
                            'user_vote' => null,
                            'instant_update' => true
                        ]);
                    } else {
                        // Update vote if different type (upvote to downvote or vice versa)
                        DB::table('forum_votes')
                            ->where('id', $existingVote->id)
                            ->update([
                                'vote_type' => $voteType, 
                                'updated_at' => now()
                            ]);
                        
                        $this->updateVoteCounts($post->thread_id, $postId);
                        
                        // Get updated vote counts for immediate response
                        $updatedPost = DB::table('forum_posts')
                            ->where('id', $postId)
                            ->select(['upvotes', 'downvotes', 'score'])
                            ->first();
                            
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote changed successfully',
                            'action' => 'updated',
                            'updated_stats' => [
                                'upvotes' => (int)($updatedPost->upvotes ?? 0),
                                'downvotes' => (int)($updatedPost->downvotes ?? 0),
                                'score' => (int)($updatedPost->score ?? 0)
                            ],
                            'user_vote' => $voteType,
                            'instant_update' => true
                        ]);
                    }
                } else {
                    // Create new post vote - handle potential race conditions
                    try {
                        DB::table('forum_votes')->insert([
                            'thread_id' => $post->thread_id,
                            'post_id' => $postId,
                            'user_id' => $userId,
                            'vote_type' => $voteType,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        
                        $this->updateVoteCounts($post->thread_id, $postId);
                        
                        // Get updated vote counts for immediate response
                        $updatedPost = DB::table('forum_posts')
                            ->where('id', $postId)
                            ->select(['upvotes', 'downvotes', 'score'])
                            ->first();
                            
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote recorded successfully',
                            'action' => 'voted',
                            'updated_stats' => [
                                'upvotes' => (int)($updatedPost->upvotes ?? 0),
                                'downvotes' => (int)($updatedPost->downvotes ?? 0),
                                'score' => (int)($updatedPost->score ?? 0)
                            ],
                            'user_vote' => $voteType,
                            'instant_update' => true
                        ]);
                        
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Handle duplicate entry gracefully - user may have double-clicked
                        if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() === '23000') {
                            // Try to get existing vote and update it instead
                            $duplicateVote = DB::table('forum_votes')
                                ->where('post_id', $postId)
                                ->where('user_id', $userId)
                                ->first();
                            
                            if ($duplicateVote && $duplicateVote->vote_type !== $voteType) {
                                // Update the existing vote
                                DB::table('forum_votes')
                                    ->where('id', $duplicateVote->id)
                                    ->update([
                                        'vote_type' => $voteType,
                                        'updated_at' => now()
                                    ]);
                                
                                $this->updateVoteCounts($post->thread_id, $postId);
                                
                                $updatedPost = DB::table('forum_posts')
                                    ->where('id', $postId)
                                    ->select(['upvotes', 'downvotes', 'score'])
                                    ->first();
                                    
                                return response()->json([
                                    'success' => true,
                                    'message' => 'Vote updated successfully',
                                    'action' => 'updated',
                                    'updated_stats' => [
                                        'upvotes' => (int)($updatedPost->upvotes ?? 0),
                                        'downvotes' => (int)($updatedPost->downvotes ?? 0),
                                        'score' => (int)($updatedPost->score ?? 0)
                                    ],
                                    'user_vote' => $voteType,
                                    'instant_update' => true
                                ]);
                            } else {
                                // Same vote type already exists
                                return response()->json([
                                    'success' => false,
                                    'message' => 'You have already cast this vote on this post',
                                    'code' => 'VOTE_ALREADY_EXISTS'
                                ], 409);
                            }
                        }
                        throw $e; // Re-throw if not a duplicate entry error
                    }
                }
            });

        } catch (\Exception $e) {
            Log::error('Forum post voting error', [
                'post_id' => $postId,
                'user_id' => auth('api')->id(),
                'vote_type' => $request->vote_type ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing vote. Please try again.',
                'code' => 'VOTE_PROCESSING_ERROR'
            ], 500);
        }
    }

    public function update(Request $request, $threadId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10'
        ]);

        try {
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }

            // Check if user owns the thread
            $user = auth('api')->user();
            if ($thread->user_id !== auth('api')->id() && (!$user || !in_array($user->role, ['admin', 'moderator']))) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            DB::table('forum_threads')->where('id', $threadId)->update([
                'title' => $request->title,
                'content' => $request->content,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thread updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($threadId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }

            // Check if user owns the thread or is admin/moderator
            if ($thread->user_id !== auth('api')->id() && !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Actually delete the thread record
            DB::table('forum_threads')->where('id', $threadId)->delete();
            
            // Clear relevant caches for instant UI update
            Cache::forget("forum:threads:all:latest:page:1");
            Cache::forget("forum:threads:general:latest:page:1");
            
            // Clear forum caches using helper (compatible with all cache drivers)
            \App\Helpers\CacheHelper::clearForumCaches();

            return response()->json([
                'success' => true,
                'message' => 'Thread deleted successfully',
                'deleted_thread_id' => $threadId,
                'instant_update' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updatePost(Request $request, $postId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'content' => 'required|string|min:1'
        ]);

        try {
            $post = DB::table('forum_posts')->where('id', $postId)->first();
            if (!$post) {
                return response()->json(['success' => false, 'message' => 'Post not found'], 404);
            }

            // Check if user owns the post
            if ($post->user_id !== auth('api')->id() && !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            DB::table('forum_posts')->where('id', $postId)->update([
                'content' => $request->content,
                'is_edited' => true,
                'edited_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating post: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyPost($postId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            $post = DB::table('forum_posts')->where('id', $postId)->first();
            if (!$post) {
                return response()->json(['success' => false, 'message' => 'Post not found'], 404);
            }

            // Check if user owns the post or is admin/moderator
            if ($post->user_id !== auth('api')->id() && !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Actually delete the post record
            DB::table('forum_posts')->where('id', $postId)->delete();

            // Update thread reply count and potentially last reply time
            $remainingPosts = DB::table('forum_posts')
                ->where('thread_id', $post->thread_id)
                ->orderBy('created_at', 'desc')
                ->first();
            
            $updateData = [
                'replies_count' => DB::raw('GREATEST(replies_count - 1, 0)')
            ];
            
            // Update last_reply_at if there are remaining posts
            if ($remainingPosts) {
                $updateData['last_reply_at'] = $remainingPosts->created_at;
            }
            
            DB::table('forum_threads')
                ->where('id', $post->thread_id)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting post: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reportThread(Request $request, $threadId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }

            // Update thread as reported
            DB::table('forum_threads')->where('id', $threadId)->update([
                'reported' => true,
                'status' => 'reported',
                'updated_at' => now()
            ]);

            // You could also create a reports table to track individual reports
            // For now, we'll just mark the thread as reported

            return response()->json([
                'success' => true,
                'message' => 'Thread reported successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reporting thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reportPost(Request $request, $postId)
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $post = DB::table('forum_posts')->where('id', $postId)->first();
            if (!$post) {
                return response()->json(['success' => false, 'message' => 'Post not found'], 404);
            }

            // Update post as reported
            DB::table('forum_posts')->where('id', $postId)->update([
                'reported' => true,
                'status' => 'reported',
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post reported successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reporting post: ' . $e->getMessage()
            ], 500);
        }
    }

    public function featureThread($threadId)
    {
        // Check if user is authenticated and has admin role
        if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        try {
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }

            // For now, we'll use the pinned column to represent featured threads
            DB::table('forum_threads')->where('id', $threadId)->update([
                'pinned' => true,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thread featured successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error featuring thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unfeatureThread($threadId)
    {
        // Check if user is authenticated and has admin role
        if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        try {
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }

            DB::table('forum_threads')->where('id', $threadId)->update([
                'pinned' => false,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thread unfeatured successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error unfeaturing thread: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for VLR.gg-style forum functionality

    private function getThreadPosts($threadId)
    {
        $posts = DB::table('forum_posts as fp')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->where('fp.thread_id', $threadId)
            ->select([
                'fp.*',
                'u.name as author_name',
                'u.avatar as author_avatar'
            ])
            ->orderBy('fp.created_at', 'asc')
            ->get();

        // Build nested structure
        $nestedPosts = [];
        $postMap = [];

        foreach ($posts as $post) {
            $postData = [
                'id' => $post->id,
                'content' => $post->content,
                'author' => $this->getUserWithFlairs($post->user_id),
                'stats' => [
                    'score' => $post->score ?? 0,
                    'upvotes' => $post->upvotes ?? 0,
                    'downvotes' => $post->downvotes ?? 0
                ],
                'meta' => [
                    'created_at' => $post->created_at ? date('c', strtotime($post->created_at)) : null,
                    'updated_at' => $post->updated_at ? date('c', strtotime($post->updated_at)) : null,
                    'edited' => $post->created_at !== $post->updated_at
                ],
                'mentions' => $this->mentionService->extractMentions($post->content),
                'user_vote' => $this->getUserPostVote($post->id),
                'replies' => []
            ];

            $postMap[$post->id] = $postData;

            if ($post->parent_id) {
                // This is a reply
                if (isset($postMap[$post->parent_id])) {
                    $postMap[$post->parent_id]['replies'][] = &$postMap[$post->id];
                }
            } else {
                // This is a top-level post
                $nestedPosts[] = &$postMap[$post->id];
            }
        }

        return $nestedPosts;
    }

    private function getUserPostVote($postId)
    {
        if (!auth('api')->check()) {
            return null;
        }

        return DB::table('forum_votes')
            ->where('post_id', $postId)
            ->where('user_id', auth('api')->id())
            ->value('vote_type');
    }

    private function updateVoteCounts($threadId, $postId = null)
    {
        if ($postId) {
            // Update post vote counts
            $upvotes = DB::table('forum_votes')
                ->where('post_id', $postId)
                ->where('vote_type', 'upvote')
                ->count();

            $downvotes = DB::table('forum_votes')
                ->where('post_id', $postId)
                ->where('vote_type', 'downvote')
                ->count();

            DB::table('forum_posts')->where('id', $postId)->update([
                'upvotes' => $upvotes,
                'downvotes' => $downvotes,
                'score' => $upvotes - $downvotes
            ]);
        } else {
            // Update thread vote counts
            $upvotes = DB::table('forum_votes')
                ->where('thread_id', $threadId)
                ->where('post_id', null)
                ->where('vote_type', 'upvote')
                ->count();

            $downvotes = DB::table('forum_votes')
                ->where('thread_id', $threadId)
                ->where('post_id', null)
                ->where('vote_type', 'downvote')
                ->count();

            DB::table('forum_threads')->where('id', $threadId)->update([
                'upvotes' => $upvotes,
                'downvotes' => $downvotes,
                'score' => $upvotes - $downvotes,
                'updated_at' => now()
            ]);
        }
    }

    private function updateThreadRepliesCount($threadId)
    {
        try {
            // Count all active posts in the thread
            $repliesCount = DB::table('forum_posts')
                ->where('thread_id', $threadId)
                ->where('status', 'active')
                ->count();

            // Get the most recent post's created date for last_reply_at
            $lastReplyAt = DB::table('forum_posts')
                ->where('thread_id', $threadId)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->value('created_at');

            // Update thread
            DB::table('forum_threads')->where('id', $threadId)->update([
                'replies_count' => $repliesCount,
                'last_reply_at' => $lastReplyAt ?: now(),
                'updated_at' => now()
            ]);

            return $repliesCount;
        } catch (\Exception $e) {
            Log::warning('Failed to update thread replies count', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }


    public function checkThreadExists($threadId)
    {
        try {
            $exists = DB::table('forum_threads')
                ->where('id', $threadId)
                ->where('status', 'active')
                ->exists();

            return response()->json([
                'exists' => $exists,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking thread existence: ' . $e->getMessage(),
                'exists' => false
            ], 500);
        }
    }

    public function getCategories()
    {
        try {
            $categories = DB::table('forum_categories')
                ->select(['id', 'name', 'slug', 'description', 'color', 'icon'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->unique('slug') // Remove duplicates by slug
                ->map(function($category) {
                    // Clean up category names - remove emojis and normalize
                    $cleanName = preg_replace('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', '', $category->name);
                    $cleanName = trim($cleanName);
                    
                    return [
                        'id' => $category->id,
                        'name' => $cleanName ?: $category->name, // Fallback to original if completely empty
                        'slug' => $category->slug,
                        'description' => $category->description,
                        'color' => $category->color ?? '#6b7280',
                        'icon' => null // Remove emoji icons, use null for frontend to handle
                    ];
                })
                ->values(); // Re-index array

            return response()->json([
                'data' => $categories,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching categories: ' . $e->getMessage()
            ], 500);
        }
    }

    // Admin-only category management methods
    public function getAllCategories()
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }
        
        try {
            $categories = DB::table('forum_categories as fc')
                ->leftJoin(DB::raw('(SELECT category_id, COUNT(*) as threads_count FROM forum_threads GROUP BY category_id) as tc'), 'fc.id', '=', 'tc.category_id')
                ->select([
                    'fc.*',
                    DB::raw('COALESCE(tc.threads_count, 0) as threads_count')
                ])
                ->orderBy('fc.sort_order')
                ->get();

            return response()->json([
                'data' => $categories,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching admin categories: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeCategory(Request $request)
    {
        // Check if user is authenticated and has admin role
        if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:100|unique:forum_categories',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:50',
            'slug' => 'nullable|string|max:100|unique:forum_categories'
        ]);

        try {
            $slug = $request->slug ?: \Str::slug($request->name);
            
            $categoryId = DB::table('forum_categories')->insertGetId([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'color' => $request->color ?: '#6b7280',
                'icon' => $request->icon ?: '📝',
                'is_active' => true,
                'sort_order' => DB::table('forum_categories')->max('sort_order') + 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'data' => ['id' => $categoryId],
                'success' => true,
                'message' => 'Category created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateCategory(Request $request, $categoryId)
    {
        // Check if user is authenticated and has admin role
        if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:100|unique:forum_categories,name,' . $categoryId,
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:50',
            'slug' => 'nullable|string|max:100|unique:forum_categories,slug,' . $categoryId
        ]);

        try {
            $category = DB::table('forum_categories')->where('id', $categoryId)->first();
            if (!$category) {
                return response()->json(['success' => false, 'message' => 'Category not found'], 404);
            }

            $slug = $request->slug ?: \Str::slug($request->name);
            
            DB::table('forum_categories')->where('id', $categoryId)->update([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'color' => $request->color ?: $category->color,
                'icon' => $request->icon ?: $category->icon,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyCategory($categoryId)
    {
        // Check if user is authenticated and has admin role
        if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
        
        try {
            $category = DB::table('forum_categories')->where('id', $categoryId)->first();
            if (!$category) {
                return response()->json(['success' => false, 'message' => 'Category not found'], 404);
            }

            // Move threads to general category before deleting
            $generalCategory = DB::table('forum_categories')->where('slug', 'general')->first();
            if ($generalCategory) {
                DB::table('forum_threads')
                    ->where('category_id', $categoryId)
                    ->update(['category_id' => $generalCategory->id]);
            }

            DB::table('forum_categories')->where('id', $categoryId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllThreads()
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }
        
        try {
            $threads = DB::table('forum_threads as ft')
                ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
                ->select([
                    'ft.*',
                    'u.name as user_name',
                    'u.avatar as user_avatar',
                    'fc.name as category_name',
                    'fc.color as category_color',
                    'fc.slug as category_slug'
                ])
                ->orderBy('ft.created_at', 'desc')
                ->get();

            $threadsData = $threads->map(function($thread) {
                return [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'content' => $thread->content,
                    'user_name' => $thread->user_name,
                    'user_avatar' => $thread->user_avatar,
                    'category' => $thread->category_name,
                    'category_slug' => $thread->category_slug,
                    'replies' => $thread->replies_count ?? 0,
                    'views' => $thread->views ?? 0,
                    'created_at' => $thread->created_at,
                    'pinned' => (bool)$thread->pinned,
                    'locked' => (bool)$thread->locked,
                    'status' => 'active'
                ];
            });

            return response()->json([
                'data' => $threadsData,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching admin threads: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateThreadAdmin(Request $request, $threadId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }
        
        try {
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }

            $action = $request->get('action');
            $updateData = ['updated_at' => now()];

            switch ($action) {
                case 'pin':
                    $updateData['pinned'] = true;
                    break;
                case 'unpin':
                    $updateData['pinned'] = false;
                    break;
                case 'lock':
                    $updateData['locked'] = true;
                    break;
                case 'unlock':
                    $updateData['locked'] = false;
                    break;
            }

            DB::table('forum_threads')->where('id', $threadId)->update($updateData);

            return response()->json([
                'success' => true,
                'message' => "Thread {$action}ed successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forceDeleteThread($threadId)
    {
        // Check if user is authenticated and has admin role
        if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
        
        try {
            $thread = DB::table('forum_threads')->where('id', $threadId)->first();
            if (!$thread) {
                return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
            }

            // Delete all posts and votes for this thread
            DB::table('forum_votes')->where('thread_id', $threadId)->delete();
            DB::table('forum_posts')->where('thread_id', $threadId)->delete();
            DB::table('forum_threads')->where('id', $threadId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Thread deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting thread: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper method to get user with flairs (VLR.gg style)
    private function getUserWithFlairs($userId)
    {
        $user = DB::table('users as u')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('u.id', $userId)
            ->select([
                'u.id', 'u.name', 'u.avatar', 'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.role',
                't.id as team_flair_id', 't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ])
            ->first();

        if (!$user) {
            return null;
        }

        // Prepare team flair object if user has one
        $teamFlair = null;
        if ($user->team_flair_id) {
            $teamFlair = [
                'id' => $user->team_flair_id,
                'name' => $user->team_name,
                'short_name' => $user->team_short,
                'logo' => $user->team_logo
            ];
        }

        // Get user roles
        $roles = [];
        if ($user->role) {
            $roles[] = $user->role;
        }

        return [
            'id' => (int)$user->id,
            'name' => (string)$user->name,
            'username' => (string)$user->name, // Add username field for compatibility
            'avatar' => $user->avatar,
            'hero_flair' => $user->hero_flair,
            'show_hero_flair' => (bool)$user->show_hero_flair,
            'team_flair' => $teamFlair,
            'show_team_flair' => (bool)$user->show_team_flair && $teamFlair !== null,
            'roles' => is_array($roles) ? $roles : []
        ];
    }

    // Optimized helper method to get team flairs for multiple users at once
    private function getTeamFlairsForUsers($userIds)
    {
        if (empty($userIds) || $userIds->isEmpty()) {
            return [];
        }

        return DB::table('teams as t')
            ->join('users as u', 't.id', '=', 'u.team_flair_id')
            ->whereIn('u.id', $userIds->toArray())
            ->select([
                'u.team_flair_id',
                't.id',
                't.name',
                't.short_name',
                't.logo'
            ])
            ->get()
            ->keyBy('team_flair_id')
            ->map(function($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $team->logo
                ];
            })
            ->toArray();
    }

    // Moderator Methods
    public function getReportedThreads()
    {
        $threads = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->where('ft.reported', true)
            ->orWhere('ft.status', 'reported')
            ->select([
                'ft.*',
                'u.name as author_name',
                'u.avatar as author_avatar'
            ])
            ->orderBy('ft.created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $threads,
            'success' => true
        ]);
    }

    public function getReportedPosts()
    {
        $posts = DB::table('forum_posts as fp')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->leftJoin('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
            ->where('fp.reported', true)
            ->orWhere('fp.status', 'reported')
            ->select([
                'fp.*',
                'u.name as author_name',
                'u.avatar as author_avatar',
                'ft.title as thread_title'
            ])
            ->orderBy('fp.created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $posts,
            'success' => true
        ]);
    }

    public function moderateThread(Request $request, $threadId)
    {
        $action = $request->validate([
            'action' => 'required|string|in:approve,reject,delete',
            'reason' => 'nullable|string'
        ]);

        $updated = DB::table('forum_threads')
            ->where('id', $threadId)
            ->update([
                'status' => $action['action'] === 'approve' ? 'active' : 'moderated',
                'moderated_at' => now(),
                'moderated_by' => auth('api')->id(),
                'moderation_reason' => $action['reason']
            ]);

        return response()->json([
            'success' => $updated > 0,
            'message' => 'Thread moderated successfully'
        ]);
    }

    public function moderatePost(Request $request, $postId)
    {
        $action = $request->validate([
            'action' => 'required|string|in:approve,reject,delete',
            'reason' => 'nullable|string'
        ]);

        $updated = DB::table('forum_posts')
            ->where('id', $postId)
            ->update([
                'status' => $action['action'] === 'approve' ? 'active' : 'moderated',
                'moderated_at' => now(),
                'moderated_by' => auth('api')->id(),
                'moderation_reason' => $action['reason']
            ]);

        return response()->json([
            'success' => $updated > 0,
            'message' => 'Post moderated successfully'
        ]);
    }

    public function pinThread($threadId)
    {
        try {
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->update(['pinned' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Thread pinned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to pin thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unpinThread($threadId)
    {
        try {
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->update(['pinned' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'Thread unpinned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unpin thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function lockThread($threadId)
    {
        try {
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->update(['locked' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Thread locked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to lock thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unlockThread($threadId)
    {
        try {
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->update(['locked' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'Thread unlocked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unlock thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forceDeletePost($postId)
    {
        $deleted = DB::table('forum_posts')->where('id', $postId)->delete();

        return response()->json([
            'success' => $deleted > 0,
            'message' => 'Post deleted successfully'
        ]);
    }

    // Admin moderation methods
    public function getAllThreadsForModeration(Request $request)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }
        
        try {
            $query = DB::table('forum_threads as ft')
                ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                ->select([
                    'ft.id',
                    'ft.title',
                    'ft.content',
                    'ft.views',
                    'ft.replies_count',
                    'ft.upvotes',
                    'ft.downvotes',
                    'ft.pinned',
                    'ft.locked',
                    'ft.status',
                    'ft.created_at',
                    'u.id as user_id',
                    'u.name as user_name',
                    'u.avatar as user_avatar',
                    'u.hero_flair as user_hero_flair',
                    'u.team_flair_id as user_team_flair_id'
                ])
                ->orderBy('ft.created_at', 'desc');

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('ft.status', $request->status);
            }

            $threads = $query->paginate(20);

            // Get user flairs
            $threads->getCollection()->transform(function($thread) {
                $thread->user = [
                    'id' => $thread->user_id,
                    'username' => $thread->user_name,
                    'avatar' => $thread->user_avatar,
                    'flair' => $thread->user_hero_flair,
                    'team_flair_id' => $thread->user_team_flair_id
                ];
                unset($thread->user_id, $thread->user_name, $thread->user_avatar, 
                      $thread->user_hero_flair, $thread->user_team_flair_id);
                return $thread;
            });

            return response()->json([
                'data' => $threads->items(),
                'pagination' => [
                    'current_page' => $threads->currentPage(),
                    'last_page' => $threads->lastPage(),
                    'per_page' => $threads->perPage(),
                    'total' => $threads->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching threads: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllPostsForModeration(Request $request)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }
        
        try {
            $query = DB::table('forum_posts as fp')
                ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
                ->leftJoin('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
                ->select([
                    'fp.id',
                    'fp.content',
                    'fp.upvotes',
                    'fp.downvotes',
                    'fp.status',
                    'fp.created_at',
                    'ft.id as thread_id',
                    'ft.title as thread_title',
                    'u.id as user_id',
                    'u.name as user_name',
                    'u.avatar as user_avatar',
                    'u.hero_flair as user_hero_flair',
                    'u.team_flair_id as user_team_flair_id'
                ])
                ->orderBy('fp.created_at', 'desc');

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('fp.status', $request->status);
            }

            $posts = $query->paginate(20);

            // Transform posts
            $posts->getCollection()->transform(function($post) {
                $post->user = [
                    'id' => $post->user_id,
                    'username' => $post->user_name,
                    'avatar' => $post->user_avatar,
                    'flair' => $post->user_hero_flair,
                    'team_flair_id' => $post->user_team_flair_id
                ];
                unset($post->user_id, $post->user_name, $post->user_avatar, 
                      $post->user_hero_flair, $post->user_team_flair_id);
                return $post;
            });

            return response()->json([
                'data' => $posts->items(),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching posts: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllForumReports(Request $request)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }
        
        try {
            // For now, return empty array as reports table doesn't exist yet
            return response()->json([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 0
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching reports: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteThread($threadId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            // Actually delete the thread record
            $updated = DB::table('forum_threads')
                ->where('id', $threadId)
                ->delete();

            return response()->json([
                'success' => $updated > 0,
                'message' => 'Thread deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deletePost($postId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            // Delete mentions for this post
            $this->mentionService->deleteMentions('forum_post', $postId);
            
            // Actually delete the post record
            $updated = DB::table('forum_posts')
                ->where('id', $postId)
                ->delete();

            return response()->json([
                'success' => $updated > 0,
                'message' => 'Post deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting post: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resolveReport($reportId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            // Placeholder for when reports table is created
            return response()->json([
                'success' => true,
                'message' => 'Report resolved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resolving report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function dismissReport($reportId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            // Placeholder for when reports table is created
            return response()->json([
                'success' => true,
                'message' => 'Report dismissed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error dismissing report: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get trending threads based on engagement
     */
    public function getTrendingThreads(Request $request)
    {
        $limit = min($request->get('limit', 10), 20);
        $timeFrame = $request->get('timeframe', '24h'); // 24h, 7d, 30d
        
        $hoursBack = match($timeFrame) {
            '7d' => 168,
            '30d' => 720,
            default => 24
        };
        
        $threads = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.replies_count', 'ft.views', 
                'ft.score', 'ft.upvotes', 'ft.downvotes', 'ft.created_at', 'ft.last_reply_at',
                'u.name as author_name', 'u.avatar as author_avatar',
                'fc.name as category_name', 'fc.color as category_color'
            ])
            ->where('ft.status', 'active')
            ->where('ft.created_at', '>=', now()->subHours($hoursBack))
            ->orderByRaw('(ft.replies_count * 2 + ft.views * 0.1 + ft.score * 3) DESC')
            ->orderBy('ft.created_at', 'desc')
            ->limit($limit)
            ->get();
            
        $threadsData = $threads->map(function($thread) {
            return [
                'id' => $thread->id,
                'title' => $thread->title,
                'content' => substr($thread->content, 0, 200) . (strlen($thread->content) > 200 ? '...' : ''),
                'author' => [
                    'name' => $thread->author_name,
                    'avatar' => $thread->author_avatar
                ],
                'category' => [
                    'name' => $thread->category_name ?? 'General',
                    'color' => $thread->category_color ?? '#6b7280'
                ],
                'stats' => [
                    'views' => $thread->views ?? 0,
                    'replies' => $thread->replies_count ?? 0,
                    'score' => $thread->score ?? 0,
                    'upvotes' => $thread->upvotes ?? 0,
                    'downvotes' => $thread->downvotes ?? 0
                ],
                'meta' => [
                    'created_at' => $thread->created_at,
                    'last_reply_at' => $thread->last_reply_at
                ]
            ];
        });
        
        return response()->json([
            'data' => $threadsData,
            'timeframe' => $timeFrame,
            'success' => true
        ]);
    }
    
    /**
     * Get hot threads based on recent activity
     */
    public function getHotThreads(Request $request)
    {
        $limit = min($request->get('limit', 10), 20);
        
        $threads = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.replies_count', 'ft.views', 
                'ft.score', 'ft.upvotes', 'ft.downvotes', 'ft.created_at', 'ft.last_reply_at',
                'u.name as author_name', 'u.avatar as author_avatar',
                'fc.name as category_name', 'fc.color as category_color'
            ])
            ->where('ft.status', 'active')
            ->where('ft.last_reply_at', '>=', now()->subHours(6)) // Recent activity in last 6 hours
            ->orderByRaw('(ft.replies_count * 1.5 + ft.score * 2 + TIMESTAMPDIFF(MINUTE, ft.last_reply_at, NOW()) * -0.1) DESC')
            ->limit($limit)
            ->get();
            
        $threadsData = $threads->map(function($thread) {
            return [
                'id' => $thread->id,
                'title' => $thread->title,
                'content' => substr($thread->content, 0, 200) . (strlen($thread->content) > 200 ? '...' : ''),
                'author' => [
                    'name' => $thread->author_name,
                    'avatar' => $thread->author_avatar
                ],
                'category' => [
                    'name' => $thread->category_name ?? 'General',
                    'color' => $thread->category_color ?? '#6b7280'
                ],
                'stats' => [
                    'views' => $thread->views ?? 0,
                    'replies' => $thread->replies_count ?? 0,
                    'score' => $thread->score ?? 0,
                    'upvotes' => $thread->upvotes ?? 0,
                    'downvotes' => $thread->downvotes ?? 0
                ],
                'meta' => [
                    'created_at' => $thread->created_at,
                    'last_reply_at' => $thread->last_reply_at,
                    'is_hot' => true
                ]
            ];
        });
        
        return response()->json([
            'data' => $threadsData,
            'success' => true
        ]);
    }
    
    /**
     * Get user's thread participation stats
     */
    public function getUserEngagementStats($userId = null)
    {
        $userId = $userId ?? auth('api')->id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $stats = [
            'threads_created' => DB::table('forum_threads')->where('user_id', $userId)->count(),
            'posts_made' => DB::table('forum_posts')->where('user_id', $userId)->count(),
            'upvotes_received' => DB::table('forum_votes')
                ->join('forum_threads', 'forum_votes.thread_id', '=', 'forum_threads.id')
                ->where('forum_threads.user_id', $userId)
                ->where('forum_votes.vote_type', 'upvote')
                ->count(),
            'total_thread_views' => DB::table('forum_threads')->where('user_id', $userId)->sum('views') ?? 0,
            'total_thread_score' => DB::table('forum_threads')->where('user_id', $userId)->sum('score') ?? 0,
            'mentions_count' => DB::table('mentions')
                ->where('mentioned_type', 'user')
                ->where('mentioned_id', $userId)
                ->count(),
            'most_popular_thread' => DB::table('forum_threads')
                ->where('user_id', $userId)
                ->select(['id', 'title', 'views', 'score'])
                ->orderBy('score', 'desc')
                ->first(),
            'recent_activity' => DB::table('forum_posts')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'thread_id', 'created_at'])
        ];
        
        return response()->json([
            'data' => $stats,
            'success' => true
        ]);
    }
    
    /**
     * Helper method to safely check if user has a specific role
     */
    private function userHasRole($roles)
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        
        if (is_string($roles)) {
            return $user->role === $roles;
        }
        
        if (is_array($roles)) {
            return in_array($user->role, $roles);
        }
        
        return false;
    }
    
    /**
     * Get forum overview data for homepage cards
     */
    public function getForumOverview()
    {
        try {
            // Get latest threads with accurate data
            $latestThreads = DB::table('forum_threads as ft')
                ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                ->leftJoin(
                    DB::raw('(SELECT thread_id, COUNT(*) as actual_replies_count FROM forum_posts WHERE status = "active" GROUP BY thread_id) as reply_counts'),
                    'ft.id', '=', 'reply_counts.thread_id'
                )
                ->select([
                    'ft.id', 'ft.title', 'ft.created_at', 'ft.last_reply_at',
                    DB::raw('COALESCE(reply_counts.actual_replies_count, 0) as replies_count'),
                    'ft.views', 'ft.score', 'ft.category',
                    'u.name as author_name', 'u.avatar as author_avatar'
                ])
                ->where('ft.status', 'active')
                ->orderBy('ft.last_reply_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($thread) {
                    return [
                        'id' => $thread->id,
                        'title' => $thread->title,
                        'author' => [
                            'name' => $thread->author_name,
                            'avatar' => $thread->author_avatar
                        ],
                        'stats' => [
                            'replies' => (int)$thread->replies_count,
                            'views' => (int)$thread->views,
                            'score' => (int)$thread->score
                        ],
                        'meta' => [
                            'category' => $thread->category,
                            'created_at' => $thread->created_at,
                            'created_at_relative' => \Carbon\Carbon::parse($thread->created_at)->diffForHumans(),
                            'last_reply_at' => $thread->last_reply_at,
                            'last_reply_at_relative' => $thread->last_reply_at ? \Carbon\Carbon::parse($thread->last_reply_at)->diffForHumans() : null
                        ]
                    ];
                });
            
            // Get forum statistics
            $stats = [
                'total_threads' => DB::table('forum_threads')->where('status', 'active')->count(),
                'total_posts' => DB::table('forum_posts')->where('status', 'active')->count(),
                'total_users' => DB::table('users')->where('status', 'active')->count(),
                'active_today' => DB::table('forum_threads')
                    ->where('status', 'active')
                    ->whereDate('created_at', today())
                    ->count() + DB::table('forum_posts')
                    ->where('status', 'active')
                    ->whereDate('created_at', today())
                    ->count()
            ];
            
            return response()->json([
                'data' => [
                    'latest_threads' => $latestThreads,
                    'stats' => $stats
                ],
                'success' => true,
                'last_updated' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching forum overview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new thread (alias for store method)
     */
    public function createThread(Request $request)
    {
        return $this->store($request);
    }

    /**
     * Create a reply to a thread (alias for storePost method)
     */
    public function createReply(Request $request, $threadId)
    {
        return $this->storePost($request, $threadId);
    }
}
