<?php

namespace App\Http\Controllers;

use App\Models\AchievementNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AchievementNotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    public function index(Request $request, User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $query = AchievementNotification::forUser($userId)->active();

        // Filter by type
        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        // Filter by read status
        $readStatus = $request->get('read_status', 'all');
        switch ($readStatus) {
            case 'unread':
                $query->unread();
                break;
            case 'read':
                $query->read();
                break;
        }

        $notifications = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        // Add formatted data
        $notifications->getCollection()->transform(function ($notification) {
            return $notification->getFormattedNotification();
        });

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $count = AchievementNotification::forUser($userId)
            ->unread()
            ->active()
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $count]
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(AchievementNotification $notification): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        if ($notification->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(AchievementNotification $notification): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        if ($notification->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->markAsUnread();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as unread'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $count = AchievementNotification::forUser($userId)
            ->unread()
            ->active()
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read"
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy(AchievementNotification $notification): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        if ($notification->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Get notification types
     */
    public function types(): JsonResponse
    {
        $types = [
            'achievement_earned' => ['icon' => '🏆', 'label' => 'Achievement Earned'],
            'streak_milestone' => ['icon' => '🔥', 'label' => 'Streak Milestone'],
            'challenge_completed' => ['icon' => '🎯', 'label' => 'Challenge Completed'],
            'leaderboard_rank' => ['icon' => '📊', 'label' => 'Leaderboard Rank']
        ];

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }
}