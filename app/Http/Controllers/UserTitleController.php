<?php

namespace App\Http\Controllers;

use App\Models\UserTitle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserTitleController extends Controller
{
    /**
     * Get user's titles
     */
    public function index(User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $titles = UserTitle::forUser($userId)
            ->with('achievement:id,name,description')
            ->orderByDesc('earned_at')
            ->get()
            ->map(function ($title) {
                return array_merge($title->toArray(), [
                    'formatted_title' => $title->getFormattedTitle()
                ]);
            });

        $activeTitle = $titles->where('is_active', true)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'titles' => $titles,
                'active_title' => $activeTitle
            ]
        ]);
    }

    /**
     * Set active title
     */
    public function setActive(UserTitle $userTitle): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        if ($userTitle->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $userTitle->activate();

        return response()->json([
            'success' => true,
            'message' => 'Title activated successfully',
            'data' => $userTitle->fresh()
        ]);
    }

    /**
     * Remove active title
     */
    public function removeActive(): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        UserTitle::forUser($userId)
            ->active()
            ->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Active title removed successfully'
        ]);
    }

    /**
     * Get user's active title (for display)
     */
    public function active(User $user = null): JsonResponse
    {
        $userId = $user ? $user->id : Auth::id();
        
        if (!$userId) {
            return response()->json(['error' => 'User not specified'], 400);
        }

        $activeTitle = UserTitle::forUser($userId)
            ->active()
            ->with('achievement:id,name')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $activeTitle ? $activeTitle->getFormattedTitle() : null
        ]);
    }
}