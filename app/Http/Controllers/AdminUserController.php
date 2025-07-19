<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Exception;

class AdminUserController extends Controller
{
    /**
     * Get all users with complete profile data
     */
    public function index()
    {
        try {
            $users = User::with(['roles', 'teamFlair'])
                ->orderBy('created_at', 'desc')
                ->get();
                
            $result = $users->map(function($user) {
                // Get user stats
                $stats = $this->getUserQuickStats($user->id);
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'roles' => $user->getRoleNames(),
                    'role' => $user->getRoleNames()->first() ?? 'user',
                    'status' => $user->status ?? 'active',
                    'last_login' => $user->last_login,
                    'created_at' => $user->created_at,
                    'hero_flair' => $user->hero_flair,
                    'team_flair' => $user->teamFlair ? [
                        'id' => $user->teamFlair->id,
                        'name' => $user->teamFlair->name,
                        'logo' => $user->teamFlair->logo
                    ] : null,
                    'show_hero_flair' => $user->show_hero_flair,
                    'show_team_flair' => $user->show_team_flair,
                    'profile_picture_type' => $user->profile_picture_type,
                    'stats' => $stats
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'total' => $users->count(),
                'summary' => [
                    'total_users' => $users->count(),
                    'admins' => $users->filter(fn($u) => $u->hasRole('admin'))->count(),
                    'moderators' => $users->filter(fn($u) => $u->hasRole('moderator'))->count(),
                    'regular_users' => $users->filter(fn($u) => $u->hasRole('user'))->count(),
                    'active' => $users->where('status', 'active')->count(),
                    'banned' => $users->where('status', 'banned')->count()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users'
            ], 500);
        }
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255|unique:users',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:8',
                'role' => 'required|string|in:admin,moderator,user',
                'status' => 'string|in:active,inactive,banned',
                'hero_flair' => 'nullable|string|exists:marvel_rivals_heroes,name',
                'team_flair_id' => 'nullable|exists:teams,id',
                'show_hero_flair' => 'boolean',
                'show_team_flair' => 'boolean'
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? 'active',
                'hero_flair' => $data['hero_flair'] ?? null,
                'team_flair_id' => $data['team_flair_id'] ?? null,
                'show_hero_flair' => $data['show_hero_flair'] ?? false,
                'show_team_flair' => $data['show_team_flair'] ?? false
            ]);

            $user->assignRole($data['role']);
            $user->load('teamFlair');

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'status' => $user->status,
                    'hero_flair' => $user->hero_flair,
                    'team_flair' => $user->teamFlair
                ]
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a user
     */
    public function update(Request $request, User $user)
    {
        try {
            $data = $request->validate([
                'role' => 'string|in:admin,moderator,user',
                'status' => 'string|in:active,inactive,banned',
                'name' => 'string|max:255|unique:users,name,' . $user->id,
                'email' => 'email|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8',
                'hero_flair' => 'nullable|string|exists:marvel_rivals_heroes,name',
                'team_flair_id' => 'nullable|exists:teams,id',
                'show_hero_flair' => 'boolean',
                'show_team_flair' => 'boolean',
                'avatar' => 'nullable|string|max:500',
                'profile_picture_type' => 'in:custom,hero',
                'use_hero_as_avatar' => 'boolean'
            ]);

            // Check if trying to remove last admin
            if (isset($data['role']) && $data['role'] !== 'admin' && $user->hasRole('admin')) {
                $adminCount = User::role('admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot remove admin role from the last admin user'
                    ], 400);
                }
            }

            // Update basic info
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['status'])) $updateData['status'] = $data['status'];
            if (isset($data['password'])) $updateData['password'] = Hash::make($data['password']);
            
            // Update profile fields
            if (isset($data['hero_flair'])) $updateData['hero_flair'] = $data['hero_flair'];
            if (isset($data['team_flair_id'])) $updateData['team_flair_id'] = $data['team_flair_id'];
            if (isset($data['show_hero_flair'])) $updateData['show_hero_flair'] = $data['show_hero_flair'];
            if (isset($data['show_team_flair'])) $updateData['show_team_flair'] = $data['show_team_flair'];
            if (isset($data['avatar'])) $updateData['avatar'] = $data['avatar'];
            if (isset($data['profile_picture_type'])) $updateData['profile_picture_type'] = $data['profile_picture_type'];
            if (isset($data['use_hero_as_avatar'])) $updateData['use_hero_as_avatar'] = $data['use_hero_as_avatar'];
            
            // Handle hero as avatar
            if (isset($data['use_hero_as_avatar']) && $data['use_hero_as_avatar'] && isset($data['hero_flair'])) {
                $heroImagePath = $this->getHeroImagePath($data['hero_flair']);
                if ($heroImagePath) {
                    $updateData['avatar'] = $heroImagePath;
                    $updateData['profile_picture_type'] = 'hero';
                }
            }
            
            $user->update($updateData);

            // Update role if provided
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            $user->refresh()->load('teamFlair');

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'status' => $user->status,
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'team_flair' => $user->teamFlair,
                    'show_hero_flair' => $user->show_hero_flair,
                    'show_team_flair' => $user->show_team_flair,
                    'profile_picture_type' => $user->profile_picture_type
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function destroy(User $user)
    {
        try {
            // Prevent deleting the last admin
            if ($user->hasRole('admin')) {
                $adminCount = User::role('admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete the last admin user'
                    ], 400);
                }
            }

            // Delete user avatar if custom
            if ($user->avatar && $user->profile_picture_type === 'custom') {
                $avatarPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($avatarPath);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user'
            ], 500);
        }
    }

    /**
     * Get single user with full profile details
     */
    public function getUser($userId)
    {
        try {
            $user = User::with(['roles', 'teamFlair'])->findOrFail($userId);
            
            // Get detailed stats
            $stats = $this->getUserDetailedStats($userId);
            
            // Get recent activity
            $recentActivity = $this->getUserRecentActivity($userId, 10);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'avatar' => $user->avatar,
                    'roles' => $user->getRoleNames(),
                    'role' => $user->getRoleNames()->first() ?? 'user',
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'status' => $user->status ?? 'active',
                    'last_login' => $user->last_login,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'hero_flair' => $user->hero_flair,
                    'team_flair' => $user->teamFlair,
                    'team_flair_id' => $user->team_flair_id,
                    'show_hero_flair' => $user->show_hero_flair,
                    'show_team_flair' => $user->show_team_flair,
                    'profile_picture_type' => $user->profile_picture_type,
                    'use_hero_as_avatar' => $user->use_hero_as_avatar,
                    'stats' => $stats,
                    'recent_activity' => $recentActivity
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Reset user password (admin action)
     */
    public function resetUserPassword(Request $request, $userId)
    {
        try {
            $request->validate([
                'new_password' => 'required|string|min:8'
            ]);

            $user = User::findOrFail($userId);
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Log the action
            Log::info('Admin password reset', [
                'admin_id' => auth()->id(),
                'user_id' => $userId,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error resetting password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }

    /**
     * Ban/Unban user
     */
    public function toggleBan(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Prevent banning admins
            if ($user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot ban admin users'
                ], 400);
            }

            $newStatus = $user->status === 'banned' ? 'active' : 'banned';
            $user->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => $newStatus === 'banned' ? 'User banned successfully' : 'User unbanned successfully',
                'data' => [
                    'status' => $newStatus
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error toggling ban: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status'
            ], 500);
        }
    }

    /**
     * Upload user avatar (admin action)
     */
    public function uploadUserAvatar(Request $request, $userId)
    {
        try {
            $request->validate([
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
            ]);

            $user = User::findOrFail($userId);

            // Delete old avatar if custom
            if ($user->avatar && $user->profile_picture_type === 'custom') {
                $oldPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $filename, 'public');
            
            $user->update([
                'avatar' => '/storage/' . $path,
                'profile_picture_type' => 'custom',
                'use_hero_as_avatar' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar' => $user->avatar
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error uploading avatar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar'
            ], 500);
        }
    }

    /**
     * Remove user avatar
     */
    public function removeUserAvatar($userId)
    {
        try {
            $user = User::findOrFail($userId);

            if ($user->avatar && $user->profile_picture_type === 'custom') {
                $avatarPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($avatarPath);
            }

            $user->update([
                'avatar' => null,
                'profile_picture_type' => null,
                'use_hero_as_avatar' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar removed successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error removing avatar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove avatar'
            ], 500);
        }
    }

    /**
     * Get user activity log
     */
    public function getUserActivity($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $activities = $this->getUserDetailedActivity($userId, 100);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ],
                    'activities' => $activities
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching user activity: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user activity'
            ], 500);
        }
    }

    /**
     * Helper: Get user quick stats
     */
    private function getUserQuickStats($userId)
    {
        return [
            'total_comments' => DB::table('news_comments')->where('user_id', $userId)->count() + 
                              DB::table('match_comments')->where('user_id', $userId)->count(),
            'total_forum_posts' => DB::table('forum_posts')->where('user_id', $userId)->count(),
            'total_forum_threads' => DB::table('forum_threads')->where('user_id', $userId)->count()
        ];
    }

    /**
     * Helper: Get user detailed stats
     */
    private function getUserDetailedStats($userId)
    {
        $stats = [
            'comments' => [
                'news' => DB::table('news_comments')->where('user_id', $userId)->count(),
                'matches' => DB::table('match_comments')->where('user_id', $userId)->count(),
                'total' => 0
            ],
            'forum' => [
                'threads' => DB::table('forum_threads')->where('user_id', $userId)->count(),
                'posts' => DB::table('forum_posts')->where('user_id', $userId)->count(),
                'total' => 0
            ],
            'votes' => [
                'upvotes_given' => 0,
                'downvotes_given' => 0,
                'upvotes_received' => 0,
                'downvotes_received' => 0
            ],
            'favorites' => [
                'teams' => 0,
                'players' => 0
            ]
        ];
        
        // Calculate totals
        $stats['comments']['total'] = $stats['comments']['news'] + $stats['comments']['matches'];
        $stats['forum']['total'] = $stats['forum']['threads'] + $stats['forum']['posts'];
        
        // Get vote counts
        if (DB::getSchemaBuilder()->hasTable('forum_post_votes')) {
            $stats['votes']['upvotes_given'] = DB::table('forum_post_votes')
                ->where('user_id', $userId)
                ->where('vote_type', 'upvote')
                ->count();
                
            $stats['votes']['downvotes_given'] = DB::table('forum_post_votes')
                ->where('user_id', $userId)
                ->where('vote_type', 'downvote')
                ->count();
        }
        
        return $stats;
    }

    /**
     * Helper: Get user recent activity
     */
    private function getUserRecentActivity($userId, $limit = 10)
    {
        $activities = [];
        
        // News comments
        $newsComments = DB::table('news_comments as nc')
            ->join('news as n', 'nc.news_id', '=', 'n.id')
            ->where('nc.user_id', $userId)
            ->select([
                'nc.id',
                'nc.content',
                'nc.created_at',
                'n.title as item_title',
                DB::raw("'news_comment' as type"),
                DB::raw("'Commented on news' as action")
            ])
            ->orderBy('nc.created_at', 'desc')
            ->limit($limit);
            
        // Forum threads
        $forumThreads = DB::table('forum_threads')
            ->where('user_id', $userId)
            ->select([
                'id',
                'title as content',
                'created_at',
                'title as item_title',
                DB::raw("'forum_thread' as type"),
                DB::raw("'Created thread' as action")
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit);
            
        // Combine
        $activities = $newsComments
            ->union($forumThreads)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
            
        return $activities;
    }

    /**
     * Helper: Get user detailed activity
     */
    private function getUserDetailedActivity($userId, $limit = 50)
    {
        // Similar to getUserRecentActivity but with more detail
        return $this->getUserRecentActivity($userId, $limit);
    }

    /**
     * Helper: Get hero image path
     */
    private function getHeroImagePath($heroName)
    {
        $slug = $this->createHeroSlug($heroName);
        $webpPath = "/images/heroes/{$slug}-headbig.webp";
        
        if (file_exists(public_path($webpPath))) {
            return $webpPath;
        }
        
        return null;
    }

    /**
     * Helper: Create hero slug
     */
    private function createHeroSlug($heroName)
    {
        $slug = strtolower($heroName);
        
        $specialCases = [
            'cloak & dagger' => 'cloak-dagger',
            'mr. fantastic' => 'mister-fantastic',
            'the punisher' => 'the-punisher',
            'the thing' => 'the-thing'
        ];
        
        if (isset($specialCases[$slug])) {
            return $specialCases[$slug];
        }
        
        $slug = str_replace([' ', '&', '.', "'", '-'], ['-', '-', '', '', '-'], $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Route aliases for compatibility
     */
    public function getAllUsers()
    {
        return $this->index();
    }

    public function createUser(Request $request)
    {
        return $this->store($request);
    }

    public function updateUser(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        return $this->update($request, $user);
    }

    public function deleteUser($userId)
    {
        $user = User::findOrFail($userId);
        return $this->destroy($user);
    }
}