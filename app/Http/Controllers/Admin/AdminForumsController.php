<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Models\ForumCategory;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminForumsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api', 'role:admin|moderator']);
    }

    // ===================================
    // DASHBOARD AND OVERVIEW
    // ===================================
    
    public function dashboard(Request $request)
    {
        try {
            $stats = Cache::remember('admin_forum_dashboard', 300, function () {
                $stats = [];
                
                try {
                    $stats['total_threads'] = ForumThread::count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count forum threads: ' . $e->getMessage());
                    $stats['total_threads'] = 0;
                }
                
                try {
                    $stats['total_posts'] = Post::count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count posts: ' . $e->getMessage());
                    $stats['total_posts'] = 0;
                }
                
                try {
                    $stats['total_categories'] = ForumCategory::count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count categories: ' . $e->getMessage());
                    $stats['total_categories'] = 0;
                }
                
                try {
                    $stats['active_users'] = User::where('last_activity', '>=', Carbon::now()->subDays(7))->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count active users: ' . $e->getMessage());
                    $stats['active_users'] = 0;
                }
                
                try {
                    $stats['pending_reports'] = $this->getPendingReportsCount();
                } catch (\Exception $e) {
                    Log::warning('Failed to get pending reports: ' . $e->getMessage());
                    $stats['pending_reports'] = 0;
                }
                
                try {
                    $stats['flagged_content'] = $this->getFlaggedContentCount();
                } catch (\Exception $e) {
                    Log::warning('Failed to get flagged content: ' . $e->getMessage());
                    $stats['flagged_content'] = 0;
                }
                
                try {
                    $stats['recent_activity'] = $this->getRecentModerationActivity();
                } catch (\Exception $e) {
                    Log::warning('Failed to get recent activity: ' . $e->getMessage());
                    $stats['recent_activity'] = [];
                }
                
                try {
                    $stats['top_categories'] = $this->getTopCategories();
                } catch (\Exception $e) {
                    Log::warning('Failed to get top categories: ' . $e->getMessage());
                    $stats['top_categories'] = [];
                }
                
                try {
                    $stats['moderator_actions_today'] = $this->getTodayModeratorActions();
                } catch (\Exception $e) {
                    Log::warning('Failed to get moderator actions: ' . $e->getMessage());
                    $stats['moderator_actions_today'] = 0;
                }
                
                return $stats;
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Admin forum dashboard error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    // ===================================
    // FORUM THREADS MANAGEMENT
    // ===================================
    
    public function getThreads(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'status' => 'string|in:all,active,locked,pinned,reported,flagged',
                'category_id' => 'integer|exists:forum_categories,id',
                'user_id' => 'integer|exists:users,id',
                'search' => 'string|max:255',
                'sort_by' => 'string|in:created_at,updated_at,views,replies,title',
                'sort_direction' => 'string|in:asc,desc',
                'date_from' => 'date',
                'date_to' => 'date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = ForumThread::with(['user:id,name,email', 'category:id,name,color']);

            // Apply filters
            if ($request->filled('status')) {
                switch ($request->status) {
                    case 'locked':
                        $query->where('locked', true);
                        break;
                    case 'pinned':
                        $query->where('pinned', true);
                        break;
                    case 'reported':
                        $query->whereHas('reports', function ($q) {
                            $q->where('status', 'pending');
                        });
                        break;
                    case 'flagged':
                        $query->where('is_flagged', true);
                        break;
                }
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('content', 'like', '%' . $request->search . '%');
                });
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $threads = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $threads,
                'meta' => [
                    'total' => $threads->total(),
                    'current_page' => $threads->currentPage(),
                    'last_page' => $threads->lastPage(),
                    'per_page' => $threads->perPage()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get threads error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch threads'
            ], 500);
        }
    }

    public function showThread($id)
    {
        try {
            $thread = ForumThread::with([
                'user:id,name,email,avatar,team_flair_id',
                'category:id,name,color,icon',
                'posts' => function ($query) {
                    $query->with('user:id,name,email,avatar,team_flair_id')
                          ->orderBy('created_at', 'asc');
                },
                'reports' => function ($query) {
                    $query->with('reporter:id,name')
                          ->where('status', 'pending');
                }
            ])->findOrFail($id);

            // Get moderation history
            $moderationHistory = UserActivity::where('subject_type', 'forum_thread')
                ->where('subject_id', $id)
                ->where('activity_type', 'like', '%moderation%')
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'thread' => $thread,
                    'moderation_history' => $moderationHistory
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Show thread error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }
    }

    public function createThread(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'category_id' => 'required|integer|exists:forum_categories,id',
                'user_id' => 'required|integer|exists:users,id',
                'pinned' => 'boolean',
                'locked' => 'boolean',
                'tags' => 'array|max:10',
                'tags.*' => 'string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $thread = ForumThread::create([
                'title' => $request->title,
                'content' => $request->content,
                'category_id' => $request->category_id,
                'user_id' => $request->user_id,
                'pinned' => $request->get('pinned', false),
                'locked' => $request->get('locked', false),
                'views' => 0,
                'replies' => 0
            ]);

            // Log moderation action
            $this->logModerationAction('thread_created', 'forum_thread', $thread->id, [
                'created_by_admin' => true,
                'target_user' => $request->user_id
            ]);

            DB::commit();

            $thread->load(['user:id,name,email', 'category:id,name,color']);

            return response()->json([
                'success' => true,
                'message' => 'Thread created successfully',
                'data' => $thread
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create thread error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create thread'
            ], 500);
        }
    }

    public function updateThread(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'string|max:255',
                'content' => 'string',
                'category_id' => 'integer|exists:forum_categories,id',
                'pinned' => 'boolean',
                'locked' => 'boolean',
                'is_flagged' => 'boolean',
                'moderation_note' => 'string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $thread = ForumThread::findOrFail($id);
            $originalData = $thread->toArray();

            $thread->update($request->only([
                'title', 'content', 'category_id', 'pinned', 'locked', 'is_flagged'
            ]));

            // Log moderation action
            $this->logModerationAction('thread_updated', 'forum_thread', $thread->id, [
                'original_data' => $originalData,
                'updated_data' => $thread->fresh()->toArray(),
                'moderation_note' => $request->moderation_note
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thread updated successfully',
                'data' => $thread->fresh(['user:id,name,email', 'category:id,name,color'])
            ]);
        } catch (\Exception $e) {
            Log::error('Update thread error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update thread'
            ], 500);
        }
    }

    public function deleteThread($id)
    {
        try {
            $thread = ForumThread::findOrFail($id);
            $threadData = $thread->toArray();

            DB::beginTransaction();

            // Soft delete related posts
            Post::where('thread_id', $id)->delete();

            // Delete the thread
            $thread->delete();

            // Log moderation action
            $this->logModerationAction('thread_deleted', 'forum_thread', $id, [
                'deleted_thread' => $threadData
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thread deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete thread error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete thread'
            ], 500);
        }
    }

    // ===================================
    // THREAD CONTROL ACTIONS
    // ===================================
    
    public function pinThread($id)
    {
        return $this->toggleThreadStatus($id, 'pinned', true, 'Thread pinned successfully');
    }

    public function unpinThread($id)
    {
        return $this->toggleThreadStatus($id, 'pinned', false, 'Thread unpinned successfully');
    }

    public function lockThread($id)
    {
        return $this->toggleThreadStatus($id, 'locked', true, 'Thread locked successfully');
    }

    public function unlockThread($id)
    {
        return $this->toggleThreadStatus($id, 'locked', false, 'Thread unlocked successfully');
    }

    public function stickyThread($id)
    {
        return $this->toggleThreadStatus($id, 'sticky', true, 'Thread marked as sticky');
    }

    public function unstickyThread($id)
    {
        return $this->toggleThreadStatus($id, 'sticky', false, 'Thread unsticky removed');
    }

    private function toggleThreadStatus($id, $field, $value, $message)
    {
        try {
            $thread = ForumThread::findOrFail($id);
            $thread->update([$field => $value]);

            $this->logModerationAction("thread_{$field}_" . ($value ? 'enabled' : 'disabled'), 'forum_thread', $id);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $thread
            ]);
        } catch (\Exception $e) {
            Log::error("Toggle thread {$field} error", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => "Failed to update thread"
            ], 500);
        }
    }

    // ===================================
    // FORUM CATEGORIES MANAGEMENT
    // ===================================
    
    public function getCategories(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'include_stats' => 'boolean',
                'status' => 'string|in:all,active,inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = ForumCategory::query();

            if ($request->get('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->get('status') === 'inactive') {
                $query->where('is_active', false);
            }

            if ($request->get('include_stats', false)) {
                $query->withCount(['threads', 'posts']);
            }

            $categories = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            Log::error('Get categories error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    public function createCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:forum_categories,name',
                'slug' => 'string|max:255|unique:forum_categories,slug',
                'description' => 'string|max:1000',
                'color' => 'string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'icon' => 'string|max:50',
                'is_active' => 'boolean',
                'sort_order' => 'integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = ForumCategory::create([
                'name' => $request->name,
                'slug' => $request->slug ?? \Str::slug($request->name),
                'description' => $request->description,
                'color' => $request->get('color', '#3B82F6'),
                'icon' => $request->icon,
                'is_active' => $request->get('is_active', true),
                'sort_order' => $request->get('sort_order', 0)
            ]);

            $this->logModerationAction('category_created', 'forum_category', $category->id);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);
        } catch (\Exception $e) {
            Log::error('Create category error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category'
            ], 500);
        }
    }

    public function updateCategory(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255|unique:forum_categories,name,' . $id,
                'slug' => 'string|max:255|unique:forum_categories,slug,' . $id,
                'description' => 'string|max:1000',
                'color' => 'string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'icon' => 'string|max:50',
                'is_active' => 'boolean',
                'sort_order' => 'integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = ForumCategory::findOrFail($id);
            $originalData = $category->toArray();

            $category->update($request->only([
                'name', 'slug', 'description', 'color', 'icon', 'is_active', 'sort_order'
            ]));

            $this->logModerationAction('category_updated', 'forum_category', $category->id, [
                'original_data' => $originalData,
                'updated_data' => $category->fresh()->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            Log::error('Update category error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category'
            ], 500);
        }
    }

    public function deleteCategory($id)
    {
        try {
            $category = ForumCategory::findOrFail($id);
            
            // Check if category has threads
            $threadCount = ForumThread::where('category_id', $id)->count();
            if ($threadCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete category with {$threadCount} threads. Please move or delete threads first."
                ], 422);
            }

            $categoryData = $category->toArray();
            $category->delete();

            $this->logModerationAction('category_deleted', 'forum_category', $id, [
                'deleted_category' => $categoryData
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete category error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category'
            ], 500);
        }
    }

    public function reorderCategories(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'categories' => 'required|array',
                'categories.*.id' => 'required|integer|exists:forum_categories,id',
                'categories.*.sort_order' => 'required|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            foreach ($request->categories as $categoryData) {
                ForumCategory::where('id', $categoryData['id'])
                    ->update(['sort_order' => $categoryData['sort_order']]);
            }

            $this->logModerationAction('categories_reordered', 'forum_category', null, [
                'new_order' => $request->categories
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Categories reordered successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reorder categories error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder categories'
            ], 500);
        }
    }

    // ===================================
    // POSTS MANAGEMENT
    // ===================================
    
    public function getPosts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'thread_id' => 'integer|exists:forum_threads,id',
                'user_id' => 'integer|exists:users,id',
                'status' => 'string|in:all,reported,flagged',
                'search' => 'string|max:255',
                'date_from' => 'date',
                'date_to' => 'date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Post::with([
                'user:id,name,email,avatar',
                'thread:id,title,category_id',
                'thread.category:id,name,color'
            ]);

            if ($request->filled('thread_id')) {
                $query->where('thread_id', $request->thread_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('search')) {
                $query->where('content', 'like', '%' . $request->search . '%');
            }

            if ($request->filled('status')) {
                switch ($request->status) {
                    case 'reported':
                        $query->whereHas('reports', function ($q) {
                            $q->where('status', 'pending');
                        });
                        break;
                    case 'flagged':
                        $query->where('is_flagged', true);
                        break;
                }
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $posts = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Exception $e) {
            Log::error('Get posts error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch posts'
            ], 500);
        }
    }

    public function updatePost(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'content' => 'string',
                'is_flagged' => 'boolean',
                'moderation_note' => 'string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $post = Post::findOrFail($id);
            $originalData = $post->toArray();

            $post->update($request->only(['content', 'is_flagged']));

            $this->logModerationAction('post_updated', 'forum_post', $post->id, [
                'original_data' => $originalData,
                'updated_data' => $post->fresh()->toArray(),
                'moderation_note' => $request->moderation_note
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully',
                'data' => $post->fresh(['user:id,name,email', 'thread:id,title'])
            ]);
        } catch (\Exception $e) {
            Log::error('Update post error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update post'
            ], 500);
        }
    }

    public function deletePost($id)
    {
        try {
            $post = Post::findOrFail($id);
            $postData = $post->toArray();

            $post->delete();

            $this->logModerationAction('post_deleted', 'forum_post', $id, [
                'deleted_post' => $postData
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete post error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete post'
            ], 500);
        }
    }

    // ===================================
    // USER MODERATION
    // ===================================
    
    public function getUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|max:255',
                'status' => 'string|in:all,active,banned,warned,muted',
                'role' => 'string|in:all,user,moderator,admin',
                'sort_by' => 'string|in:name,email,created_at,last_activity',
                'sort_direction' => 'string|in:asc,desc'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = User::query();

            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }

            if ($request->filled('status')) {
                switch ($request->status) {
                    case 'banned':
                        $query->whereNotNull('banned_at');
                        break;
                    case 'warned':
                        $query->whereHas('warnings', function ($q) {
                            $q->where('expires_at', '>', now())
                              ->orWhereNull('expires_at');
                        });
                        break;
                    case 'muted':
                        $query->where('muted_until', '>', now());
                        break;
                    case 'active':
                        $query->whereNull('banned_at')
                              ->where(function ($q) {
                                  $q->where('muted_until', '<=', now())
                                    ->orWhereNull('muted_until');
                              });
                        break;
                }
            }

            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $users = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Get users error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users'
            ], 500);
        }
    }

    public function warnUser(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000',
                'expires_at' => 'nullable|date|after:now',
                'severity' => 'required|string|in:low,medium,high,critical'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            DB::beginTransaction();

            // Create warning record (user_warnings table may have different structure)
            $warning = DB::table('user_warnings')->insert([
                'user_id' => $userId,
                'moderator_id' => Auth::id(),
                'reason' => $request->reason,
                'severity' => $request->severity,
                'expires_at' => $request->expires_at,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->logModerationAction('user_warned', 'user', $userId, [
                'reason' => $request->reason,
                'severity' => $request->severity,
                'expires_at' => $request->expires_at
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User warned successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Warn user error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to warn user'
            ], 500);
        }
    }

    public function timeoutUser(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000',
                'duration_minutes' => 'required|integer|min:1|max:10080', // Max 1 week
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);
            $mutedUntil = now()->addMinutes($request->duration_minutes);

            $user->update(['muted_until' => $mutedUntil]);

            $this->logModerationAction('user_timeout', 'user', $userId, [
                'reason' => $request->reason,
                'duration_minutes' => $request->duration_minutes,
                'muted_until' => $mutedUntil
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User timed out successfully',
                'data' => ['muted_until' => $mutedUntil]
            ]);
        } catch (\Exception $e) {
            Log::error('Timeout user error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to timeout user'
            ], 500);
        }
    }

    public function banUser(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000',
                'permanent' => 'boolean',
                'expires_at' => 'nullable|required_if:permanent,false|date|after:now'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            $banData = [
                'banned_at' => now(),
                'ban_reason' => $request->reason
            ];

            if (!$request->get('permanent', false)) {
                $banData['ban_expires_at'] = $request->expires_at;
            }

            $user->update($banData);

            $this->logModerationAction('user_banned', 'user', $userId, [
                'reason' => $request->reason,
                'permanent' => $request->get('permanent', false),
                'expires_at' => $request->expires_at
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User banned successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Ban user error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to ban user'
            ], 500);
        }
    }

    public function unbanUser($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $user->update([
                'banned_at' => null,
                'ban_reason' => null,
                'ban_expires_at' => null
            ]);

            $this->logModerationAction('user_unbanned', 'user', $userId);

            return response()->json([
                'success' => true,
                'message' => 'User unbanned successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Unban user error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to unban user'
            ], 500);
        }
    }

    // ===================================
    // BULK MODERATION ACTIONS
    // ===================================
    
    public function bulkActions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:delete,lock,unlock,pin,unpin,move_category,flag,unflag',
                'type' => 'required|string|in:threads,posts,users',
                'ids' => 'required|array|min:1|max:100',
                'ids.*' => 'integer',
                'category_id' => 'nullable|required_if:action,move_category|integer|exists:forum_categories,id',
                'reason' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($request->ids as $id) {
                try {
                    $result = $this->executeBulkAction(
                        $request->action,
                        $request->type,
                        $id,
                        $request->only(['category_id', 'reason'])
                    );

                    if ($result) {
                        $successCount++;
                        $results[] = ['id' => $id, 'status' => 'success'];
                    } else {
                        $failureCount++;
                        $results[] = ['id' => $id, 'status' => 'failed'];
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    $results[] = ['id' => $id, 'status' => 'error', 'message' => $e->getMessage()];
                }
            }

            $this->logModerationAction("bulk_{$request->action}", $request->type, null, [
                'action' => $request->action,
                'type' => $request->type,
                'ids' => $request->ids,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'reason' => $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk action completed. Success: {$successCount}, Failed: {$failureCount}",
                'data' => [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'results' => $results
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk actions error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Bulk action failed'
            ], 500);
        }
    }

    private function executeBulkAction($action, $type, $id, $options = [])
    {
        switch ($type) {
            case 'threads':
                return $this->executeBulkThreadAction($action, $id, $options);
            case 'posts':
                return $this->executeBulkPostAction($action, $id, $options);
            case 'users':
                return $this->executeBulkUserAction($action, $id, $options);
            default:
                return false;
        }
    }

    private function executeBulkThreadAction($action, $threadId, $options = [])
    {
        $thread = ForumThread::find($threadId);
        if (!$thread) return false;

        switch ($action) {
            case 'delete':
                $thread->delete();
                return true;
            case 'lock':
                $thread->update(['locked' => true]);
                return true;
            case 'unlock':
                $thread->update(['locked' => false]);
                return true;
            case 'pin':
                $thread->update(['pinned' => true]);
                return true;
            case 'unpin':
                $thread->update(['pinned' => false]);
                return true;
            case 'move_category':
                if (isset($options['category_id'])) {
                    $thread->update(['category_id' => $options['category_id']]);
                    return true;
                }
                return false;
            case 'flag':
                $thread->update(['is_flagged' => true]);
                return true;
            case 'unflag':
                $thread->update(['is_flagged' => false]);
                return true;
            default:
                return false;
        }
    }

    private function executeBulkPostAction($action, $postId, $options = [])
    {
        $post = Post::find($postId);
        if (!$post) return false;

        switch ($action) {
            case 'delete':
                $post->delete();
                return true;
            case 'flag':
                $post->update(['is_flagged' => true]);
                return true;
            case 'unflag':
                $post->update(['is_flagged' => false]);
                return true;
            default:
                return false;
        }
    }

    private function executeBulkUserAction($action, $userId, $options = [])
    {
        $user = User::find($userId);
        if (!$user) return false;

        switch ($action) {
            case 'ban':
                $user->update(['banned_at' => now(), 'ban_reason' => $options['reason'] ?? 'Bulk action']);
                return true;
            case 'unban':
                $user->update(['banned_at' => null, 'ban_reason' => null, 'ban_expires_at' => null]);
                return true;
            case 'timeout':
                $user->update(['muted_until' => now()->addHours(24)]);
                return true;
            default:
                return false;
        }
    }

    // ===================================
    // ADVANCED SEARCH AND FILTERING
    // ===================================
    
    public function advancedSearch(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'nullable|string|max:255',
                'type' => 'required|string|in:threads,posts,users,all',
                'category_ids' => 'array',
                'category_ids.*' => 'integer|exists:forum_categories,id',
                'user_ids' => 'array',
                'user_ids.*' => 'integer|exists:users,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'min_replies' => 'nullable|integer|min:0',
                'max_replies' => 'nullable|integer|min:0',
                'min_views' => 'nullable|integer|min:0',
                'max_views' => 'nullable|integer|min:0',
                'has_reports' => 'boolean',
                'is_flagged' => 'boolean',
                'is_locked' => 'boolean',
                'is_pinned' => 'boolean',
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = [];

            if (in_array($request->type, ['threads', 'all'])) {
                $results['threads'] = $this->searchThreads($request);
            }

            if (in_array($request->type, ['posts', 'all'])) {
                $results['posts'] = $this->searchPosts($request);
            }

            if (in_array($request->type, ['users', 'all'])) {
                $results['users'] = $this->searchUsers($request);
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Advanced search error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Search failed'
            ], 500);
        }
    }

    private function searchThreads($request)
    {
        $query = ForumThread::with(['user:id,name,email', 'category:id,name,color']);

        if ($request->filled('query')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->query . '%')
                  ->orWhere('content', 'like', '%' . $request->query . '%');
            });
        }

        if ($request->filled('category_ids')) {
            $query->whereIn('category_id', $request->category_ids);
        }

        if ($request->filled('user_ids')) {
            $query->whereIn('user_id', $request->user_ids);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        if ($request->filled('min_replies')) {
            $query->where('replies', '>=', $request->min_replies);
        }

        if ($request->filled('max_replies')) {
            $query->where('replies', '<=', $request->max_replies);
        }

        if ($request->filled('min_views')) {
            $query->where('views', '>=', $request->min_views);
        }

        if ($request->filled('max_views')) {
            $query->where('views', '<=', $request->max_views);
        }

        if ($request->get('has_reports')) {
            $query->whereHas('reports', function ($q) {
                $q->where('status', 'pending');
            });
        }

        if ($request->filled('is_flagged')) {
            $query->where('is_flagged', $request->is_flagged);
        }

        if ($request->filled('is_locked')) {
            $query->where('locked', $request->is_locked);
        }

        if ($request->filled('is_pinned')) {
            $query->where('pinned', $request->is_pinned);
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($request->get('per_page', 20));
    }

    private function searchPosts($request)
    {
        $query = Post::with(['user:id,name,email', 'thread:id,title']);

        if ($request->filled('query')) {
            $query->where('content', 'like', '%' . $request->query . '%');
        }

        if ($request->filled('user_ids')) {
            $query->whereIn('user_id', $request->user_ids);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        if ($request->get('has_reports')) {
            $query->whereHas('reports', function ($q) {
                $q->where('status', 'pending');
            });
        }

        if ($request->filled('is_flagged')) {
            $query->where('is_flagged', $request->is_flagged);
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($request->get('per_page', 20));
    }

    private function searchUsers($request)
    {
        $query = User::select(['id', 'name', 'email', 'avatar', 'role', 'created_at', 'banned_at']);

        if ($request->filled('query')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->query . '%')
                  ->orWhere('email', 'like', '%' . $request->query . '%');
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($request->get('per_page', 20));
    }

    // ===================================
    // REPORT MANAGEMENT SYSTEM
    // ===================================
    
    public function getReports(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'string|in:all,pending,resolved,dismissed',
                'type' => 'string|in:all,thread,post,user',
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = DB::table('reports')
                ->select([
                    'reports.*',
                    'reporter.name as reporter_name',
                    'reporter.email as reporter_email',
                    'moderator.name as moderator_name'
                ])
                ->leftJoin('users as reporter', 'reports.reporter_id', '=', 'reporter.id')
                ->leftJoin('users as moderator', 'reports.resolved_by', '=', 'moderator.id');

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('reports.status', $request->status);
            }

            if ($request->filled('type') && $request->type !== 'all') {
                $query->where('reports.reportable_type', 'like', '%' . $request->type . '%');
            }

            $reports = $query->orderBy('reports.created_at', 'desc')
                           ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            Log::error('Get reports error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reports'
            ], 500);
        }
    }

    public function resolveReport(Request $request, $reportId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:dismiss,warn_user,timeout_user,ban_user,delete_content,flag_content',
                'reason' => 'nullable|string|max:1000',
                'duration_minutes' => 'nullable|integer|min:1|max:10080'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $report = DB::table('reports')->where('id', $reportId)->first();
            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report not found'
                ], 404);
            }

            // Execute the action
            $actionResult = $this->executeReportAction($request->action, $report, $request->all());

            // Update report status
            DB::table('reports')
                ->where('id', $reportId)
                ->update([
                    'status' => 'resolved',
                    'resolved_by' => Auth::id(),
                    'resolution_notes' => $request->reason,
                    'resolved_at' => now(),
                    'updated_at' => now()
                ]);

            $this->logModerationAction('report_resolved', 'report', $reportId, [
                'action' => $request->action,
                'reason' => $request->reason,
                'report_type' => $report->reportable_type
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report resolved successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Resolve report error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve report'
            ], 500);
        }
    }

    public function dismissReport(Request $request, $reportId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::table('reports')
                ->where('id', $reportId)
                ->update([
                    'status' => 'dismissed',
                    'resolved_by' => Auth::id(),
                    'resolution_notes' => $request->reason,
                    'resolved_at' => now(),
                    'updated_at' => now()
                ]);

            $this->logModerationAction('report_dismissed', 'report', $reportId, [
                'reason' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report dismissed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Dismiss report error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to dismiss report'
            ], 500);
        }
    }

    private function executeReportAction($action, $report, $params)
    {
        switch ($action) {
            case 'dismiss':
                // No additional action needed
                return true;

            case 'warn_user':
                if ($report->reportable_type === 'user') {
                    return $this->warnUser(new Request($params), $report->reportable_id);
                }
                return true;

            case 'timeout_user':
                if ($report->reportable_type === 'user') {
                    return $this->timeoutUser(new Request($params), $report->reportable_id);
                }
                return true;

            case 'ban_user':
                if ($report->reportable_type === 'user') {
                    return $this->banUser(new Request($params), $report->reportable_id);
                }
                return true;

            case 'delete_content':
                return $this->deleteReportedContent($report);

            case 'flag_content':
                return $this->flagReportedContent($report);

            default:
                return false;
        }
    }

    private function deleteReportedContent($report)
    {
        try {
            if (strpos($report->reportable_type, 'ForumThread') !== false) {
                ForumThread::find($report->reportable_id)?->delete();
            } elseif (strpos($report->reportable_type, 'Post') !== false) {
                Post::find($report->reportable_id)?->delete();
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Delete reported content error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function flagReportedContent($report)
    {
        try {
            if (strpos($report->reportable_type, 'ForumThread') !== false) {
                ForumThread::find($report->reportable_id)?->update(['is_flagged' => true]);
            } elseif (strpos($report->reportable_type, 'Post') !== false) {
                Post::find($report->reportable_id)?->update(['is_flagged' => true]);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Flag reported content error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ===================================
    // MODERATION LOGGING SYSTEM
    // ===================================
    
    private function logModerationAction($action, $subjectType, $subjectId, $metadata = [])
    {
        try {
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity_type' => "moderation.{$action}",
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'metadata' => json_encode(array_merge($metadata, [
                    'moderator_id' => Auth::id(),
                    'moderator_name' => Auth::user()->name,
                    'timestamp' => now()->toISOString()
                ])),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Log moderation action error', ['error' => $e->getMessage()]);
        }
    }

    public function getModerationLogs(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'moderator_id' => 'integer|exists:users,id',
                'action_type' => 'string',
                'subject_type' => 'string',
                'date_from' => 'date',
                'date_to' => 'date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = UserActivity::with('user:id,name,email')
                ->where('activity_type', 'like', 'moderation.%');

            if ($request->filled('moderator_id')) {
                $query->where('user_id', $request->moderator_id);
            }

            if ($request->filled('action_type')) {
                $query->where('activity_type', 'like', '%' . $request->action_type . '%');
            }

            if ($request->filled('subject_type')) {
                $query->where('subject_type', $request->subject_type);
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
        } catch (\Exception $e) {
            Log::error('Get moderation logs error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch moderation logs'
            ], 500);
        }
    }

    // ===================================
    // STATISTICS AND ANALYTICS
    // ===================================
    
    public function getStatistics(Request $request)
    {
        try {
            $period = $request->get('period', '7'); // days
            $startDate = Carbon::now()->subDays($period);

            $stats = Cache::remember("forum_admin_stats_{$period}", 600, function () use ($startDate) {
                $stats = [];
                
                // Overview stats with error handling
                $stats['overview'] = [];
                try {
                    $stats['overview']['total_threads'] = ForumThread::count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count forum threads in stats: ' . $e->getMessage());
                    $stats['overview']['total_threads'] = 0;
                }
                
                try {
                    $stats['overview']['total_posts'] = Post::count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count posts in stats: ' . $e->getMessage());
                    $stats['overview']['total_posts'] = 0;
                }
                
                try {
                    $stats['overview']['total_categories'] = ForumCategory::count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count categories in stats: ' . $e->getMessage());
                    $stats['overview']['total_categories'] = 0;
                }
                
                try {
                    $stats['overview']['active_users'] = User::where('last_activity', '>=', $startDate)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count active users in stats: ' . $e->getMessage());
                    $stats['overview']['active_users'] = 0;
                }

                // Recent activity stats with error handling
                $stats['recent_activity'] = [];
                try {
                    $stats['recent_activity']['new_threads'] = ForumThread::where('created_at', '>=', $startDate)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count new threads: ' . $e->getMessage());
                    $stats['recent_activity']['new_threads'] = 0;
                }
                
                try {
                    $stats['recent_activity']['new_posts'] = Post::where('created_at', '>=', $startDate)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count new posts: ' . $e->getMessage());
                    $stats['recent_activity']['new_posts'] = 0;
                }
                
                try {
                    if (class_exists('\\App\\Models\\UserActivity')) {
                        $stats['recent_activity']['moderation_actions'] = UserActivity::where('activity_type', 'like', 'moderation.%')
                                                               ->where('created_at', '>=', $startDate)
                                                               ->count();
                    } else {
                        $stats['recent_activity']['moderation_actions'] = 0;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to count moderation actions: ' . $e->getMessage());
                    $stats['recent_activity']['moderation_actions'] = 0;
                }
                
                try {
                    $stats['recent_activity']['reports_filed'] = DB::table('reports')->where('created_at', '>=', $startDate)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count reports: ' . $e->getMessage());
                    $stats['recent_activity']['reports_filed'] = 0;
                }

                // Moderation stats with error handling
                $stats['moderation'] = [];
                try {
                    $stats['moderation']['pending_reports'] = DB::table('reports')->where('status', 'pending')->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count pending reports: ' . $e->getMessage());
                    $stats['moderation']['pending_reports'] = 0;
                }
                
                try {
                    $stats['moderation']['flagged_threads'] = ForumThread::where('is_flagged', true)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count flagged threads: ' . $e->getMessage());
                    $stats['moderation']['flagged_threads'] = 0;
                }
                
                try {
                    $stats['moderation']['flagged_posts'] = Post::where('is_flagged', true)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count flagged posts: ' . $e->getMessage());
                    $stats['moderation']['flagged_posts'] = 0;
                }
                
                try {
                    $stats['moderation']['banned_users'] = User::whereNotNull('banned_at')->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count banned users: ' . $e->getMessage());
                    $stats['moderation']['banned_users'] = 0;
                }
                
                try {
                    $stats['moderation']['locked_threads'] = ForumThread::where('locked', true)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count locked threads: ' . $e->getMessage());
                    $stats['moderation']['locked_threads'] = 0;
                }

                // Top categories with error handling
                try {
                    $stats['top_categories'] = ForumCategory::withCount('threads')
                                                       ->orderBy('threads_count', 'desc')
                                                       ->limit(10)
                                                       ->get();
                } catch (\Exception $e) {
                    Log::warning('Failed to get top categories: ' . $e->getMessage());
                    $stats['top_categories'] = [];
                }

                // Most active users with error handling
                try {
                    $stats['most_active_users'] = User::withCount(['forumThreads', 'forumPosts'])
                                                  ->orderByRaw('forum_threads_count + forum_posts_count DESC')
                                                  ->limit(10)
                                                  ->get();
                } catch (\Exception $e) {
                    Log::warning('Failed to get most active users: ' . $e->getMessage());
                    $stats['most_active_users'] = [];
                }
                
                return $stats;
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => $period
            ]);
        } catch (\Exception $e) {
            Log::error('Get statistics error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    // ===================================
    // HELPER METHODS
    // ===================================
    
    private function getPendingReportsCount()
    {
        return DB::table('reports')->where('status', 'pending')->count();
    }

    private function getFlaggedContentCount()
    {
        return ForumThread::where('is_flagged', true)->count() + 
               Post::where('is_flagged', true)->count();
    }

    private function getRecentModerationActivity()
    {
        return UserActivity::with('user:id,name')
            ->where('activity_type', 'like', 'moderation.%')
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    private function getTopCategories()
    {
        return ForumCategory::withCount('threads')
            ->orderBy('threads_count', 'desc')
            ->limit(5)
            ->get();
    }

    private function getTodayModeratorActions()
    {
        return UserActivity::where('activity_type', 'like', 'moderation.%')
            ->where('created_at', '>=', Carbon::today())
            ->count();
    }
}