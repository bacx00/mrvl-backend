<?php
namespace App\Http\Controllers;

use App\Models\ForumThread;
use App\Models\ForumPost;
use App\Models\ForumVote;
use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ForumController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('forum_threads as ft')
                ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                ->select([
                    'ft.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'ft.category as category_name'
                ]);

            // Filter by category
            if ($request->category && $request->category !== 'all') {
                $query->where('ft.category', $request->category);
            }

            // Search functionality
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('ft.title', 'LIKE', "%{$request->search}%")
                      ->orWhere('ft.content', 'LIKE', "%{$request->search}%");
                });
            }

            // Sort options
            $sortBy = $request->get('sort', 'latest');
            switch ($sortBy) {
                case 'popular':
                    $query->orderBy('ft.score', 'desc');
                    break;
                case 'hot':
                    $query->orderBy('ft.replies_count', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('ft.created_at', 'asc');
                    break;
                default: // latest
                    $query->orderBy('ft.pinned', 'desc')
                          ->orderBy('ft.last_reply_at', 'desc');
            }

            $threads = $query->paginate(20);

            // Add additional data for each thread with proper flairs
            $threadsData = collect($threads->items())->map(function($thread) {
                $author = $this->getUserWithFlairs($thread->user_id);
                return [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'content' => $thread->content,
                    'author' => $author,
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
                        'created_at' => $thread->created_at,
                        'last_reply_at' => $thread->last_reply_at
                    ],
                    'mentions' => $this->extractMentions($thread->content)
                ];
            });

            return response()->json([
                'data' => $threadsData,
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
            if (Auth::check()) {
                $userVote = DB::table('forum_votes')
                    ->where('thread_id', $threadId)
                    ->where('user_id', Auth::id())
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
                    'created_at' => $thread->created_at,
                    'last_reply_at' => $thread->last_reply_at
                ],
                'mentions' => $this->extractMentions($thread->content),
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
        if (!Auth::check()) {
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
            $thread = DB::table('forum_threads')->insertGetId([
                'title' => $request->title,
                'content' => $request->content,
                'category' => 'general', // Default category since we're not using category selection
                'user_id' => Auth::id(),
                'last_reply_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Process mentions
            $this->processMentions($request->content, $thread);

            return response()->json([
                'data' => ['id' => $thread],
                'success' => true,
                'message' => 'Thread created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating thread: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storePost(Request $request, $threadId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'content' => 'required|string|min:1',
            'parent_id' => 'nullable|exists:forum_posts,id'
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
                'user_id' => Auth::id(),
                'content' => $request->content,
                'parent_id' => $request->parent_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update thread reply count and last reply time
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->increment('replies_count');
            
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->update(['last_reply_at' => now()]);

            // Process mentions
            $this->processMentions($request->content, $threadId, $postId);

            return response()->json([
                'data' => ['id' => $postId],
                'success' => true,
                'message' => 'Reply posted successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error posting reply: ' . $e->getMessage()
            ], 500);
        }
    }

    public function voteThread(Request $request, $threadId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote',
            'post_id' => 'nullable|exists:forum_posts,id'
        ]);

        try {
            $userId = Auth::id();
            $voteType = $request->vote_type;
            $postId = $request->post_id;

            // Check for existing vote
            $existingVoteQuery = DB::table('forum_votes')
                ->where('thread_id', $threadId)
                ->where('user_id', $userId);
            
            if ($postId) {
                $existingVoteQuery->where('post_id', $postId);
            } else {
                $existingVoteQuery->whereNull('post_id');
            }
            
            $existingVote = $existingVoteQuery->first();

            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Remove vote if same type
                    DB::table('forum_votes')->where('id', $existingVote->id)->delete();
                    $this->updateVoteCounts($threadId, $postId);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote removed',
                        'action' => 'removed'
                    ]);
                } else {
                    // Update vote if different type
                    DB::table('forum_votes')
                        ->where('id', $existingVote->id)
                        ->update(['vote_type' => $voteType, 'updated_at' => now()]);
                }
            } else {
                // Create new vote
                $voteData = [
                    'thread_id' => $threadId,
                    'user_id' => $userId,
                    'vote_type' => $voteType,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                // Only add post_id if it's not null
                if ($postId !== null) {
                    $voteData['post_id'] = $postId;
                }
                
                DB::table('forum_votes')->insert($voteData);
            }

            // Update vote counts
            $this->updateVoteCounts($threadId, $postId);

            return response()->json([
                'success' => true,
                'message' => 'Vote recorded',
                'action' => 'voted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording vote: ' . $e->getMessage()
            ], 500);
        }
    }

    public function votePost(Request $request, $postId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        try {
            $userId = Auth::id();
            $voteType = $request->vote_type;

            // Get the thread ID for this post
            $post = DB::table('forum_posts')->where('id', $postId)->first();
            if (!$post) {
                return response()->json(['success' => false, 'message' => 'Post not found'], 404);
            }

            // Check for existing vote (by thread and user due to unique constraint)
            $existingVote = DB::table('forum_votes')
                ->where('thread_id', $post->thread_id)
                ->where('user_id', $userId)
                ->first();

            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Remove vote if same type
                    DB::table('forum_votes')->where('id', $existingVote->id)->delete();
                    $this->updateVoteCounts($post->thread_id, $postId);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote removed',
                        'action' => 'removed'
                    ]);
                } else {
                    // Update vote if different type
                    DB::table('forum_votes')
                        ->where('id', $existingVote->id)
                        ->update(['vote_type' => $voteType, 'updated_at' => now()]);
                }
            } else {
                // Create new vote
                DB::table('forum_votes')->insert([
                    'thread_id' => $post->thread_id,
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'vote_type' => $voteType,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Update vote counts
            $this->updateVoteCounts($post->thread_id, $postId);

            return response()->json([
                'success' => true,
                'message' => 'Vote recorded',
                'action' => 'voted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording vote: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $threadId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
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
            $user = Auth::user();
            if ($thread->user_id !== Auth::id() && (!$user || !in_array($user->role, ['admin', 'moderator']))) {
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
        if (!Auth::check()) {
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
            if ($thread->user_id !== Auth::id() && !in_array(Auth::user()->role, ['admin', 'moderator'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Actually delete the thread record
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

    public function updatePost(Request $request, $postId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
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
            if ($post->user_id !== Auth::id() && !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check()) {
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
            if ($post->user_id !== Auth::id() && !in_array(Auth::user()->role, ['admin', 'moderator'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Actually delete the post record
            DB::table('forum_posts')->where('id', $postId)->delete();

            // Update thread reply count
            DB::table('forum_threads')
                ->where('id', $post->thread_id)
                ->decrement('replies_count');

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
        if (!Auth::check()) {
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
        if (!Auth::check()) {
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
        if (!Auth::check() || Auth::user()->role !== 'admin') {
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
        if (!Auth::check() || Auth::user()->role !== 'admin') {
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
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                    'edited' => $post->created_at !== $post->updated_at
                ],
                'mentions' => $this->extractMentions($post->content),
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
        if (!Auth::check()) {
            return null;
        }

        return DB::table('forum_votes')
            ->where('post_id', $postId)
            ->where('user_id', Auth::id())
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
                'score' => $upvotes - $downvotes
            ]);
        }
    }

    private function extractMentions($content)
    {
        $mentions = [];
        
        // Extract @username mentions
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $userMatches);
        foreach ($userMatches[1] as $username) {
            $user = DB::table('users')->where('name', $username)->first();
            if ($user) {
                $mentions[] = [
                    'type' => 'user',
                    'id' => $user->id,
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => '@' . $username
                ];
            }
        }

        // Extract @team:teamname mentions
        preg_match_all('/@team:([a-zA-Z0-9_]+)/', $content, $teamMatches);
        foreach ($teamMatches[1] as $teamName) {
            $team = DB::table('teams')->where('short_name', $teamName)->first();
            if ($team) {
                $mentions[] = [
                    'type' => 'team',
                    'id' => $team->id,
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => '@team:' . $teamName
                ];
            }
        }

        // Extract @player:playername mentions
        preg_match_all('/@player:([a-zA-Z0-9_]+)/', $content, $playerMatches);
        foreach ($playerMatches[1] as $playerName) {
            $player = DB::table('players')->where('username', $playerName)->first();
            if ($player) {
                $mentions[] = [
                    'type' => 'player',
                    'id' => $player->id,
                    'name' => $player->username,
                    'display_name' => $player->real_name ?? $player->username,
                    'mention_text' => '@player:' . $playerName
                ];
            }
        }

        return $mentions;
    }

    private function processMentions($content, $threadId, $postId = null)
    {
        $mentions = $this->extractMentions($content);
        
        foreach ($mentions as $mention) {
            // Store mention in database for notifications
            try {
                DB::table('mentions')->insert([
                    'mentionable_type' => $postId ? 'forum_post' : 'forum_thread',
                    'mentionable_id' => $postId ?: $threadId,
                    'mentioned_type' => $mention['type'],
                    'mentioned_id' => $mention['id'],
                    'user_id' => Auth::id(),
                    'mentioned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $e) {
                // Log error but don't fail the request
                \Log::error('Failed to save mention: ' . $e->getMessage());
            }
        }
    }

    public function getCategories()
    {
        try {
            $categories = DB::table('forum_categories')
                ->select(['id', 'name', 'slug', 'description', 'color', 'icon'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check() || Auth::user()->role !== 'admin') {
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
        if (!Auth::check() || Auth::user()->role !== 'admin') {
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
        if (!Auth::check() || Auth::user()->role !== 'admin') {
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check() || Auth::user()->role !== 'admin') {
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
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->name, // Add username field for compatibility
            'avatar' => $user->avatar,
            'hero_flair' => $user->hero_flair,
            'show_hero_flair' => (bool)$user->show_hero_flair,
            'team_flair' => $teamFlair,
            'show_team_flair' => (bool)$user->show_team_flair,
            'roles' => $roles
        ];
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
                'moderated_by' => Auth::id(),
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
                'moderated_by' => Auth::id(),
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
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
}
