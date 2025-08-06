<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ForumModerationService
{
    private $cacheService;
    private $realTimeService;

    public function __construct(ForumCacheService $cacheService, ForumRealTimeService $realTimeService)
    {
        $this->cacheService = $cacheService;
        $this->realTimeService = $realTimeService;
    }

    /**
     * Pin/Unpin a thread with proper permissions and notifications
     */
    public function toggleThreadPin($threadId, $pin = true)
    {
        if (!$this->canModerate()) {
            throw new \Exception('Insufficient permissions for moderation');
        }

        $thread = DB::table('forum_threads')->where('id', $threadId)->first();
        if (!$thread) {
            throw new \Exception('Thread not found');
        }

        DB::beginTransaction();
        try {
            // Update thread status
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->update([
                    'pinned' => $pin,
                    'updated_at' => now()
                ]);

            // Log moderation action
            $this->logModerationAction('thread', $threadId, $pin ? 'pin' : 'unpin', [
                'thread_title' => $thread->title,
                'previous_state' => (bool)$thread->pinned
            ]);

            // Broadcast real-time update
            $this->realTimeService->broadcastModerationAction(
                $pin ? 'pin' : 'unpin',
                'thread',
                $threadId,
                $this->getModeratorData()
            );

            // Invalidate caches
            $this->cacheService->invalidateThread($threadId);
            $this->cacheService->invalidateThreadListings();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Lock/Unlock a thread
     */
    public function toggleThreadLock($threadId, $lock = true)
    {
        if (!$this->canModerate()) {
            throw new \Exception('Insufficient permissions for moderation');
        }

        $thread = DB::table('forum_threads')->where('id', $threadId)->first();
        if (!$thread) {
            throw new \Exception('Thread not found');
        }

        DB::beginTransaction();
        try {
            // Update thread status
            DB::table('forum_threads')
                ->where('id', $threadId)
                ->update([
                    'locked' => $lock,
                    'updated_at' => now()
                ]);

            // Log moderation action
            $this->logModerationAction('thread', $threadId, $lock ? 'lock' : 'unlock', [
                'thread_title' => $thread->title,
                'previous_state' => (bool)$thread->locked
            ]);

            // Broadcast real-time update
            $this->realTimeService->broadcastModerationAction(
                $lock ? 'lock' : 'unlock',
                'thread',
                $threadId,
                $this->getModeratorData()
            );

            // Invalidate caches
            $this->cacheService->invalidateThread($threadId);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Delete a thread with cascade deletion
     */
    public function deleteThread($threadId, $reason = null)
    {
        if (!$this->canModerate()) {
            throw new \Exception('Insufficient permissions for moderation');
        }

        $thread = DB::table('forum_threads')->where('id', $threadId)->first();
        if (!$thread) {
            throw new \Exception('Thread not found');
        }

        DB::beginTransaction();
        try {
            // Soft delete or hard delete based on policy
            if ($this->shouldSoftDelete()) {
                DB::table('forum_threads')
                    ->where('id', $threadId)
                    ->update([
                        'status' => 'deleted',
                        'deleted_at' => now(),
                        'deleted_by' => Auth::id(),
                        'deletion_reason' => $reason,
                        'updated_at' => now()
                    ]);

                // Mark all posts as deleted too
                DB::table('forum_posts')
                    ->where('thread_id', $threadId)
                    ->update([
                        'status' => 'deleted',
                        'deleted_at' => now(),
                        'updated_at' => now()
                    ]);
            } else {
                // Hard delete - remove everything
                DB::table('forum_votes')->where('thread_id', $threadId)->delete();
                DB::table('mentions')
                    ->where('mentionable_type', 'forum_thread')
                    ->where('mentionable_id', $threadId)
                    ->delete();
                DB::table('mentions')
                    ->where('mentionable_type', 'forum_post')
                    ->whereIn('mentionable_id', function($query) use ($threadId) {
                        $query->select('id')->from('forum_posts')->where('thread_id', $threadId);
                    })
                    ->delete();
                DB::table('forum_posts')->where('thread_id', $threadId)->delete();
                DB::table('forum_threads')->where('id', $threadId)->delete();
            }

            // Log moderation action
            $this->logModerationAction('thread', $threadId, 'delete', [
                'thread_title' => $thread->title,
                'reason' => $reason,
                'soft_delete' => $this->shouldSoftDelete()
            ]);

            // Broadcast real-time update
            $this->realTimeService->broadcastModerationAction(
                'delete',
                'thread',
                $threadId,
                $this->getModeratorData()
            );

            // Invalidate caches
            $this->cacheService->invalidateThread($threadId);
            $this->cacheService->invalidateThreadListings();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Delete a post
     */
    public function deletePost($postId, $reason = null)
    {
        if (!$this->canModerate()) {
            throw new \Exception('Insufficient permissions for moderation');
        }

        $post = DB::table('forum_posts')->where('id', $postId)->first();
        if (!$post) {
            throw new \Exception('Post not found');
        }

        DB::beginTransaction();
        try {
            if ($this->shouldSoftDelete()) {
                DB::table('forum_posts')
                    ->where('id', $postId)
                    ->update([
                        'status' => 'deleted',
                        'deleted_at' => now(),
                        'deleted_by' => Auth::id(),
                        'deletion_reason' => $reason,
                        'updated_at' => now()
                    ]);
            } else {
                // Remove votes and mentions first
                DB::table('forum_votes')->where('post_id', $postId)->delete();
                DB::table('mentions')
                    ->where('mentionable_type', 'forum_post')
                    ->where('mentionable_id', $postId)
                    ->delete();
                DB::table('forum_posts')->where('id', $postId)->delete();
            }

            // Update thread reply count
            if (!$this->shouldSoftDelete()) {
                $this->updateThreadRepliesCount($post->thread_id);
            }

            // Log moderation action
            $this->logModerationAction('post', $postId, 'delete', [
                'thread_id' => $post->thread_id,
                'reason' => $reason,
                'soft_delete' => $this->shouldSoftDelete()
            ]);

            // Invalidate caches
            $this->cacheService->invalidateThread($post->thread_id);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Bulk moderation actions
     */
    public function bulkAction($action, $type, $ids, $reason = null)
    {
        if (!$this->canModerate()) {
            throw new \Exception('Insufficient permissions for moderation');
        }

        if (!in_array($action, ['delete', 'pin', 'unpin', 'lock', 'unlock'])) {
            throw new \Exception('Invalid bulk action');
        }

        if (!in_array($type, ['thread', 'post'])) {
            throw new \Exception('Invalid target type');
        }

        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                switch ($action) {
                    case 'delete':
                        if ($type === 'thread') {
                            $this->deleteThread($id, $reason);
                        } else {
                            $this->deletePost($id, $reason);
                        }
                        break;
                    case 'pin':
                        $this->toggleThreadPin($id, true);
                        break;
                    case 'unpin':
                        $this->toggleThreadPin($id, false);
                        break;
                    case 'lock':
                        $this->toggleThreadLock($id, true);
                        break;
                    case 'unlock':
                        $this->toggleThreadLock($id, false);
                        break;
                }
                
                $results[$id] = ['success' => true];
                $successful++;

            } catch (\Exception $e) {
                $results[$id] = ['success' => false, 'error' => $e->getMessage()];
                $failed++;
            }
        }

        // Log bulk action
        $this->logModerationAction('bulk', null, $action, [
            'type' => $type,
            'ids' => $ids,
            'reason' => $reason,
            'successful' => $successful,
            'failed' => $failed
        ]);

        return [
            'results' => $results,
            'summary' => [
                'successful' => $successful,
                'failed' => $failed,
                'total' => count($ids)
            ]
        ];
    }

    /**
     * Get moderation queue with filtering
     */
    public function getModerationQueue($type = 'all', $status = 'pending', $page = 1, $perPage = 20)
    {
        if (!$this->canModerate()) {
            throw new \Exception('Insufficient permissions for moderation');
        }

        $offset = ($page - 1) * $perPage;
        $results = [];

        if ($type === 'all' || $type === 'threads') {
            $threads = DB::table('forum_threads as ft')
                ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
                ->select([
                    'ft.id', 'ft.title', 'ft.content', 'ft.status', 'ft.reported',
                    'ft.created_at', 'ft.updated_at',
                    'u.name as author_name', 'u.avatar as author_avatar',
                    'fc.name as category_name'
                ])
                ->where(function($query) use ($status) {
                    if ($status === 'reported') {
                        $query->where('ft.reported', true)
                              ->orWhere('ft.status', 'reported');
                    } elseif ($status === 'deleted') {
                        $query->where('ft.status', 'deleted');
                    } else {
                        $query->where('ft.status', $status);
                    }
                })
                ->orderBy('ft.updated_at', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(function($thread) {
                    return array_merge((array)$thread, ['type' => 'thread']);
                });

            $results = array_merge($results, $threads->toArray());
        }

        if ($type === 'all' || $type === 'posts') {
            $posts = DB::table('forum_posts as fp')
                ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
                ->leftJoin('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
                ->select([
                    'fp.id', 'fp.content', 'fp.status', 'fp.reported',
                    'fp.created_at', 'fp.updated_at',
                    'u.name as author_name', 'u.avatar as author_avatar',
                    'ft.id as thread_id', 'ft.title as thread_title'
                ])
                ->where(function($query) use ($status) {
                    if ($status === 'reported') {
                        $query->where('fp.reported', true)
                              ->orWhere('fp.status', 'reported');
                    } elseif ($status === 'deleted') {
                        $query->where('fp.status', 'deleted');
                    } else {
                        $query->where('fp.status', $status);
                    }
                })
                ->orderBy('fp.updated_at', 'desc')
                ->limit($perPage - count($results))
                ->get()
                ->map(function($post) {
                    return array_merge((array)$post, ['type' => 'post']);
                });

            $results = array_merge($results, $posts->toArray());
        }

        return [
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($results)
            ]
        ];
    }

    /**
     * Get moderation statistics
     */
    public function getModerationStats($period = '24h')
    {
        if (!$this->canModerate()) {
            throw new \Exception('Insufficient permissions for moderation');
        }

        $since = match($period) {
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay()
        };

        $stats = [
            'period' => $period,
            'since' => $since->toISOString(),
            'reported_threads' => DB::table('forum_threads')
                ->where('reported', true)
                ->where('updated_at', '>=', $since)
                ->count(),
            'reported_posts' => DB::table('forum_posts')
                ->where('reported', true)
                ->where('updated_at', '>=', $since)
                ->count(),
            'deleted_threads' => DB::table('forum_threads')
                ->where('status', 'deleted')
                ->where('deleted_at', '>=', $since)
                ->count(),
            'deleted_posts' => DB::table('forum_posts')
                ->where('status', 'deleted')
                ->where('deleted_at', '>=', $since)
                ->count(),
            'moderation_actions' => DB::table('moderation_log')
                ->where('created_at', '>=', $since)
                ->count(),
            'active_moderators' => DB::table('moderation_log')
                ->where('created_at', '>=', $since)
                ->distinct('moderator_id')
                ->count()
        ];

        // Get top moderators
        $stats['top_moderators'] = DB::table('moderation_log as ml')
            ->leftJoin('users as u', 'ml.moderator_id', '=', 'u.id')
            ->select(['u.name', 'u.avatar', DB::raw('COUNT(*) as actions')])
            ->where('ml.created_at', '>=', $since)
            ->groupBy('ml.moderator_id', 'u.name', 'u.avatar')
            ->orderBy('actions', 'desc')
            ->limit(5)
            ->get();

        // Get action breakdown
        $stats['action_breakdown'] = DB::table('moderation_log')
            ->select(['action', DB::raw('COUNT(*) as count')])
            ->where('created_at', '>=', $since)
            ->groupBy('action')
            ->get()
            ->keyBy('action')
            ->map(function($item) { return $item->count; });

        return $stats;
    }

    /**
     * Approve or reject reported content
     */
    public function moderateReportedContent($type, $id, $action, $reason = null)
    {
        if (!$this->canModerate()) {
            throw new \Exception('Insufficient permissions for moderation');
        }

        if (!in_array($action, ['approve', 'delete', 'warn'])) {
            throw new \Exception('Invalid moderation action');
        }

        DB::beginTransaction();
        try {
            $table = $type === 'thread' ? 'forum_threads' : 'forum_posts';
            
            // Update content status
            $updateData = [
                'reported' => false,
                'status' => $action === 'delete' ? 'deleted' : 'active',
                'moderated_at' => now(),
                'moderated_by' => Auth::id(),
                'moderation_reason' => $reason,
                'updated_at' => now()
            ];

            if ($action === 'delete') {
                $updateData['deleted_at'] = now();
                $updateData['deleted_by'] = Auth::id();
                $updateData['deletion_reason'] = $reason;
            }

            DB::table($table)->where('id', $id)->update($updateData);

            // Log moderation action
            $this->logModerationAction($type, $id, "report_{$action}", [
                'reason' => $reason,
                'original_action' => 'moderate_report'
            ]);

            // If it's a post deletion, update thread reply count
            if ($action === 'delete' && $type === 'post') {
                $post = DB::table('forum_posts')->where('id', $id)->first();
                if ($post) {
                    $this->updateThreadRepliesCount($post->thread_id);
                }
            }

            // Invalidate caches
            if ($type === 'thread') {
                $this->cacheService->invalidateThread($id);
                $this->cacheService->invalidateThreadListings();
            } else {
                $post = DB::table('forum_posts')->where('id', $id)->first();
                if ($post) {
                    $this->cacheService->invalidateThread($post->thread_id);
                }
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Check if current user can moderate
     */
    private function canModerate()
    {
        $user = Auth::user();
        return $user && in_array($user->role, ['admin', 'moderator']);
    }

    /**
     * Check if we should use soft delete
     */
    private function shouldSoftDelete()
    {
        return config('forum.moderation.soft_delete', true);
    }

    /**
     * Log moderation action
     */
    private function logModerationAction($targetType, $targetId, $action, $details = [])
    {
        try {
            DB::table('moderation_log')->insert([
                'moderator_id' => Auth::id(),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'action' => $action,
                'details' => json_encode($details),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log moderation action', [
                'error' => $e->getMessage(),
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId
            ]);
        }
    }

    /**
     * Get moderator data for broadcasting
     */
    private function getModeratorData()
    {
        $user = Auth::user();
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role
        ];
    }

    /**
     * Update thread reply count after post deletion
     */
    private function updateThreadRepliesCount($threadId)
    {
        $count = DB::table('forum_posts')
            ->where('thread_id', $threadId)
            ->where('status', 'active')
            ->count();

        $lastReplyAt = DB::table('forum_posts')
            ->where('thread_id', $threadId)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        DB::table('forum_threads')
            ->where('id', $threadId)
            ->update([
                'replies_count' => $count,
                'last_reply_at' => $lastReplyAt ?: now(),
                'updated_at' => now()
            ]);
    }

    /**
     * Create moderation log table if it doesn't exist
     */
    public function ensureModerationLogTable()
    {
        if (!Schema::hasTable('moderation_log')) {
            Schema::create('moderation_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('moderator_id')->constrained('users')->onDelete('cascade');
                $table->string('target_type'); // 'thread', 'post', 'user', 'bulk'
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('action'); // 'delete', 'pin', 'lock', etc.
                $table->json('details')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at');

                $table->index(['target_type', 'target_id']);
                $table->index(['moderator_id', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        }
    }
}