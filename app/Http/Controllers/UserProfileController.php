<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\Team;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;

class UserProfileController extends Controller
{
    /**
     * Get current user's complete profile with stats (Optimized)
     */
    public function show()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid Bearer token.'
                ], 401);
            }
            
            // Use optimized profile loading with caching
            $user = $user->getProfileWithCache();
            
            // Fix avatar if it's using old PNG path
            $this->fixUserAvatar($user);
            
            // Get cached user statistics
            $stats = $user->getStatsWithCache();
            
            // Get recent activity with optimized query
            $recentActivity = $this->getRecentActivityOptimized($user->id, 5);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'team_flair' => $user->teamFlair,
                    'team_flair_id' => $user->team_flair_id,
                    'show_hero_flair' => (bool)$user->show_hero_flair,
                    'show_team_flair' => (bool)$user->show_team_flair,
                    'use_hero_as_avatar' => (bool)$user->use_hero_as_avatar,
                    'status' => $user->status,
                    'last_login' => $user->last_login,
                    'created_at' => $user->created_at,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'stats' => $stats,
                    'recent_activity' => $recentActivity,
                    'display_avatar' => $this->getDisplayAvatar($user),
                    'display_flairs' => $this->getDisplayFlairs($user)
                ]
            ]);
        } catch (AuthenticationException $e) {
            Log::warning('Authentication failed in user profile fetch', [
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
                'user_agent' => request()->header('User-Agent')
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please log in again.',
                'error_code' => 'AUTH_REQUIRED'
            ], 401);
        } catch (Exception $e) {
            Log::error('Error fetching user profile', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth('api')->id()
            ]);
            
            // Return more detailed error in development
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile loading error: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine()
                ], 500);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to load profile data. Please try again later.',
                'error_code' => 'PROFILE_FETCH_ERROR'
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid Bearer token.'
                ], 401);
            }
            
            $request->validate([
                'name' => 'sometimes|string|max:255|unique:users,name,' . $user->id,
                'avatar' => 'nullable|string|max:500',
                'hero_flair' => 'nullable|string|exists:marvel_rivals_heroes,name',
                'team_flair_id' => 'nullable|exists:teams,id',
                'show_hero_flair' => 'boolean',
                'show_team_flair' => 'boolean',
                'use_hero_as_avatar' => 'boolean'
            ]);

            $updateData = [];

            // Handle name update
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            // Handle avatar update
            if ($request->has('avatar')) {
                $updateData['avatar'] = $request->avatar;
                $updateData['use_hero_as_avatar'] = false;
            }

            // Handle hero flair
            if ($request->has('hero_flair')) {
                $updateData['hero_flair'] = $request->hero_flair;
                
                // If using hero as avatar
                if ($request->use_hero_as_avatar) {
                    $heroImagePath = $this->getHeroImagePath($request->hero_flair);
                    if ($heroImagePath) {
                        $updateData['avatar'] = $heroImagePath;
                        $updateData['use_hero_as_avatar'] = true;
                    }
                }
            }

            // Handle team flair
            if ($request->has('team_flair_id')) {
                $updateData['team_flair_id'] = $request->team_flair_id;
            }

            // Handle display preferences
            if ($request->has('show_hero_flair')) {
                $updateData['show_hero_flair'] = $request->show_hero_flair;
            }
            if ($request->has('show_team_flair')) {
                $updateData['show_team_flair'] = $request->show_team_flair;
            }

            $user->update($updateData);
            $user->refresh()->load('teamFlair');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user,
                    'display_avatar' => $this->getDisplayAvatar($user),
                    'display_flairs' => $this->getDisplayFlairs($user)
                ]
            ]);
        } catch (ValidationException $e) {
            Log::info('Profile update validation failed', [
                'errors' => $e->errors(),
                'user_id' => auth('api')->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Please check your input and try again.',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422);
        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please log in again.',
                'error_code' => 'AUTH_REQUIRED'
            ], 401);
        } catch (Exception $e) {
            Log::error('Error updating profile', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth('api')->id(),
                'request_data' => request()->except(['password', 'current_password'])
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to update profile. Please try again later.',
                'error_code' => 'PROFILE_UPDATE_ERROR'
            ], 500);
        }
    }

    /**
     * Update user flairs specifically
     */
    public function updateFlairs(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid Bearer token.'
                ], 401);
            }
            
            $request->validate([
                'hero_flair' => 'nullable|string',
                'team_flair_id' => 'nullable|integer',
                'show_hero_flair' => 'boolean',
                'show_team_flair' => 'boolean'
            ]);
            
            // Use the model method for proper validation and tracking
            $updatedUser = $user->updateFlairs(
                $request->hero_flair,
                $request->team_flair_id,
                $request->boolean('show_hero_flair'),
                $request->boolean('show_team_flair')
            );

            return response()->json([
                'success' => true,
                'message' => 'Flairs updated successfully',
                'data' => [
                    'hero_flair' => $updatedUser->hero_flair,
                    'team_flair' => $updatedUser->teamFlair,
                    'team_flair_id' => $updatedUser->team_flair_id,
                    'show_hero_flair' => (bool)$updatedUser->show_hero_flair,
                    'show_team_flair' => (bool)$updatedUser->show_team_flair,
                    'display_flairs' => $this->getDisplayFlairs($updatedUser),
                    'hero_flair_image' => $updatedUser->hero_flair_image
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Please check your flair selections and try again.',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422);
        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please log in again.',
                'error_code' => 'AUTH_REQUIRED'
            ], 401);
        } catch (Exception $e) {
            Log::error('Error updating flairs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth('api')->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to update flairs. Please try again later.',
                'error_code' => 'FLAIR_UPDATE_ERROR'
            ], 500);
        }
    }

    /**
     * Get available flairs (heroes and teams) with caching
     */
    public function getAvailableFlairs()
    {
        try {
            // Cache the entire flairs data for better performance
            $flairData = Cache::remember('available_flairs', 3600, function () {
                // Get all heroes with optimized query using indexes
                $heroes = DB::table('marvel_rivals_heroes')
                    ->select(['id', 'name', 'slug', 'role', 'season_added', 'is_new'])
                    ->where('active', true)
                    ->orderBy('role')
                    ->orderBy('name')
                    ->get()
                    ->map(function($hero) {
                        $imagePath = $this->getHeroImagePath($hero->name);
                        $hasImage = $imagePath !== null;
                        
                        return [
                            'id' => $hero->id,
                            'name' => $hero->name,
                            'slug' => $hero->slug,
                            'role' => $hero->role,
                            'season_added' => $hero->season_added ?? 'Launch',
                            'is_new' => (bool)$hero->is_new,
                            'has_image' => $hasImage,
                            'image_path' => $imagePath,
                            'display' => [
                                'name' => $hero->name,
                                'role' => $hero->role,
                                'role_color' => $this->getRoleColor($hero->role),
                                'hero_color' => $this->getHeroColor($hero->name),
                                'initials' => $this->getHeroInitials($hero->name)
                            ]
                        ];
                    })
                    ->groupBy('role');

                // Get all teams with optimized query using indexes
                $teams = DB::table('teams')
                    ->select(['id', 'name', 'short_name', 'logo', 'region'])
                    ->whereNotNull('name')
                    ->orderBy('region')
                    ->orderBy('name')
                    ->get()
                    ->map(function($team) {
                        return [
                            'id' => $team->id,
                            'name' => $team->name,
                            'short_name' => $team->short_name,
                            'logo' => $team->logo,
                            'region' => $team->region,
                            'has_logo' => !empty($team->logo),
                            'display' => [
                                'name' => $team->name,
                                'short_name' => $team->short_name ?: strtoupper(substr($team->name, 0, 3)),
                                'region_color' => $this->getRegionColor($team->region)
                            ]
                        ];
                    })
                    ->groupBy('region');
                    
                return [
                    'heroes' => $heroes,
                    'teams' => $teams,
                    'stats' => [
                        'total_heroes' => $heroes->flatten()->count(),
                        'heroes_with_images' => $heroes->flatten()->where('has_image', true)->count(),
                        'total_teams' => $teams->flatten()->count(),
                        'teams_with_logos' => $teams->flatten()->where('has_logo', true)->count()
                    ]
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $flairData
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching available flairs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available flairs'
            ], 500);
        }
    }

    /**
     * Set hero as avatar
     */
    public function setHeroAsAvatar(Request $request)
    {
        try {
            $request->validate([
                'hero_name' => 'required|string|exists:marvel_rivals_heroes,name'
            ]);

            $user = auth('api')->user();
            $heroImagePath = $this->getHeroImagePath($request->hero_name);
            
            if (!$heroImagePath || !file_exists(public_path($heroImagePath))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero image not available'
                ], 400);
            }
            
            $user->update([
                'avatar' => $heroImagePath,
                'hero_flair' => $request->hero_name,
                'use_hero_as_avatar' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hero avatar set successfully',
                'data' => [
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'use_hero_as_avatar' => $user->use_hero_as_avatar,
                    'display_avatar' => $this->getDisplayAvatar($user)
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error setting hero avatar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set hero avatar'
            ], 500);
        }
    }

    /**
     * Get user display data (for showing in comments, forums, etc) with caching
     */
    public function getUserWithAvatarAndFlairs($userId)
    {
        try {
            // Cache user display data for better performance
            $userData = Cache::remember(
                "user_display_{$userId}",
                1800, // 30 minutes
                function () use ($userId) {
                    return User::with([
                        'teamFlair' => function ($query) {
                            $query->select(['id', 'name', 'short_name', 'logo', 'region']);
                        }
                    ])->find($userId);
                }
            );
            
            if (!$userData) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            $user = $userData;

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $this->getDisplayAvatar($user),
                    'flairs' => $this->getDisplayFlairs($user),
                    'roles' => $user->getRoleNames(),
                    'is_admin' => $user->hasRole('admin'),
                    'is_moderator' => $user->hasRole('moderator')
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching user display data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user data'
            ], 500);
        }
    }

    /**
     * Get user activity
     */
    public function getUserActivity(Request $request)
    {
        try {
            $userId = auth('api')->id();
            $limit = $request->get('limit', 50);
            $offset = $request->get('offset', 0);
            
            $activities = $this->getDetailedUserActivity($userId, $limit, $offset);
            $stats = $this->getUserStats($userId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => $activities,
                    'stats' => $stats,
                    'has_more' => count($activities) === $limit
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
     * Change password
     */
    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string|min:1',
                'new_password' => 'required|string|min:8|max:255|confirmed|different:current_password|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'new_password_confirmation' => 'required|string'
            ], [
                'new_password.regex' => 'New password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character.'
            ]);

            $user = auth('api')->user();

            // Rate limiting: 5 attempts per minute per user
            $key = 'password_change_profile_' . $user->id;
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => 'Too many password change attempts. Please try again in ' . $seconds . ' seconds.'
                ], 429);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                \Illuminate\Support\Facades\RateLimiter::hit($key, 60); // 1 minute decay
                
                // Log failed password change attempt
                Log::warning('Failed password change attempt via profile', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent')
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Clear rate limit on successful current password verification
            \Illuminate\Support\Facades\RateLimiter::clear($key);

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Log successful password change
            Log::info('Password changed successfully via profile', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent')
            ]);

            // Revoke all existing tokens to force re-login
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully. Please log in again with your new password.',
                'requires_reauth' => true
            ]);
        } catch (Exception $e) {
            Log::error('Error changing password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    }

    /**
     * Change email
     */
    public function changeEmail(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string',
                'new_email' => 'required|email|unique:users,email,' . auth('api')->id(),
            ]);

            $user = auth('api')->user();

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            $oldEmail = $user->email;
            $user->update([
                'email' => $request->new_email,
                'email_verified_at' => null
            ]);

            // Log email change for security
            Log::info('User email changed', [
                'user_id' => $user->id,
                'old_email' => $oldEmail,
                'new_email' => $request->new_email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email changed successfully. Please verify your new email address.',
                'data' => [
                    'email' => $user->email
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error changing email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change email'
            ], 500);
        }
    }

    /**
     * Change username
     */
    public function changeUsername(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string',
                'new_name' => 'required|string|min:3|max:255|unique:users,name,' . Auth::id() . '|regex:/^[a-zA-Z0-9_-]+$/',
            ]);

            $user = auth('api')->user();

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            $oldName = $user->name;
            $user->update([
                'name' => $request->new_name
            ]);

            // Log username change
            Log::info('Username changed', [
                'user_id' => $user->id,
                'old_name' => $oldName,
                'new_name' => $request->new_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Username changed successfully',
                'data' => [
                    'name' => $user->name
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error changing username: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change username'
            ], 500);
        }
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(Request $request)
    {
        try {
            $request->validate([
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048|dimensions:min_width=100,min_height=100'
            ]);

            $user = auth('api')->user();

            // Delete old avatar if it exists and is custom
            if ($user->avatar && !$user->use_hero_as_avatar) {
                $oldPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $filename, 'public');
            
            $user->update([
                'avatar' => '/storage/' . $path,
                'use_hero_as_avatar' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar' => $user->avatar,
                    'display_avatar' => $this->getDisplayAvatar($user)
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
     * Delete avatar
     */
    public function deleteAvatar()
    {
        try {
            $user = auth('api')->user();

            // Only delete if it's a custom avatar
            if ($user->avatar && !$user->use_hero_as_avatar) {
                $avatarPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($avatarPath);
            }

            $user->update([
                'avatar' => null,
                'use_hero_as_avatar' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully',
                'data' => [
                    'avatar' => null,
                    'display_avatar' => $this->getDisplayAvatar($user)
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting avatar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar'
            ], 500);
        }
    }

    /**
     * Helper: Get hero image path
     */
    private function getHeroImagePath($heroName, $type = 'portrait')
    {
        $slug = $this->createHeroSlug($heroName);
        
        // Always use the webp images
        $webpPath = "/images/heroes/{$slug}-headbig.webp";
        
        if (file_exists(public_path($webpPath))) {
            return $webpPath;
        }
        
        // Check for duplicates (like adam-warlock has two files)
        $altWebpPath = "/images/heroes/{$slug}-headbig (1).webp";
        if (file_exists(public_path($altWebpPath))) {
            return $webpPath; // Return the original path, not the duplicate
        }
        
        return null;
    }

    /**
     * Helper: Create hero slug
     */
    private function createHeroSlug($heroName)
    {
        $slug = strtolower($heroName);
        
        // Special cases
        $specialCases = [
            'cloak & dagger' => 'cloak-dagger',
            'mr. fantastic' => 'mister-fantastic',
            'the punisher' => 'the-punisher',
            'the thing' => 'the-thing',
            'hulk' => 'bruce-banner'
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
     * Helper: Get display avatar
     */
    private function getDisplayAvatar($user)
    {
        if ($user->avatar) {
            return [
                'type' => $user->use_hero_as_avatar ? 'hero' : 'custom',
                'url' => $user->avatar,
                'fallback' => $this->getFallbackAvatar($user)
            ];
        }
        
        return [
            'type' => 'fallback',
            'url' => null,
            'fallback' => $this->getFallbackAvatar($user)
        ];
    }

    /**
     * Helper: Get display flairs
     */
    private function getDisplayFlairs($user)
    {
        $flairs = [];
        
        if ($user->show_hero_flair && $user->hero_flair) {
            $heroImagePath = $this->getHeroImagePath($user->hero_flair);
            $flairs['hero'] = [
                'type' => 'hero',
                'name' => $user->hero_flair,
                'has_image' => $heroImagePath !== null,
                'image' => $heroImagePath,
                'color' => $this->getHeroColor($user->hero_flair),
                'initials' => $this->getHeroInitials($user->hero_flair)
            ];
        }
        
        if ($user->show_team_flair && $user->teamFlair) {
            $flairs['team'] = [
                'type' => 'team',
                'id' => $user->teamFlair->id,
                'name' => $user->teamFlair->name,
                'short_name' => $user->teamFlair->short_name,
                'logo' => $user->teamFlair->logo,
                'has_logo' => !empty($user->teamFlair->logo),
                'region' => $user->teamFlair->region,
                'initials' => $user->teamFlair->short_name ?: strtoupper(substr($user->teamFlair->name, 0, 3))
            ];
        }
        
        return $flairs;
    }

    /**
     * Helper: Get fallback avatar
     */
    private function getFallbackAvatar($user)
    {
        return [
            'text' => strtoupper(substr($user->name, 0, 2)),
            'color' => $this->generateColorFromName($user->name),
            'background' => $this->generateBackgroundFromName($user->name)
        ];
    }

    /**
     * Helper: Get user stats
     */
    private function getUserStats($userId)
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
                'downvotes_received' => 0,
                'reputation_score' => 0
            ],
            'mentions' => [
                'given' => 0,
                'received' => 0,
                'player_mentions' => 0,
                'team_mentions' => 0,
                'user_mentions' => 0
            ],
            'activity' => [
                'total_actions' => 0,
                'last_activity' => null,
                'activity_score' => 0
            ],
            'account' => [
                'days_active' => 0,
                'last_seen' => null,
                'join_date' => null
            ]
        ];
        
        // Calculate totals
        $stats['comments']['total'] = $stats['comments']['news'] + $stats['comments']['matches'];
        $stats['forum']['total'] = $stats['forum']['threads'] + $stats['forum']['posts'];
        
        // Get unified vote counts from votes table (vote: 1 = upvote, -1 = downvote)
        $votesGiven = DB::table('votes')
            ->where('user_id', $userId)
            ->selectRaw('
                SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as upvotes,
                SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as downvotes
            ')
            ->first();
            
        $stats['votes']['upvotes_given'] = $votesGiven->upvotes ?? 0;
        $stats['votes']['downvotes_given'] = $votesGiven->downvotes ?? 0;
        
        // Get received votes on user's content
        $userContent = [
            'forum_threads' => DB::table('forum_threads')->where('user_id', $userId)->pluck('id'),
            'forum_posts' => DB::table('forum_posts')->where('user_id', $userId)->pluck('id'),
            'news_comments' => DB::table('news_comments')->where('user_id', $userId)->pluck('id'),
        ];

        $votesReceived = ['upvote' => 0, 'downvote' => 0];
        foreach ($userContent as $type => $ids) {
            if ($ids->isNotEmpty()) {
                $typeVotes = DB::table('votes')
                    ->where('voteable_type', $type)
                    ->whereIn('voteable_id', $ids)
                    ->selectRaw('
                        SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as upvotes,
                        SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as downvotes
                    ')
                    ->first();
                
                if ($typeVotes) {
                    $votesReceived['upvote'] += $typeVotes->upvotes ?? 0;
                    $votesReceived['downvote'] += $typeVotes->downvotes ?? 0;
                }
            }
        }
        
        $stats['votes']['upvotes_received'] = $votesReceived['upvote'];
        $stats['votes']['downvotes_received'] = $votesReceived['downvote'];
        $stats['votes']['reputation_score'] = $votesReceived['upvote'] - $votesReceived['downvote'];
        
        // Get mention stats
        $stats['mentions']['given'] = DB::table('mentions')->where('mentioned_by', $userId)->count();
        $stats['mentions']['received'] = DB::table('mentions')
            ->where('mentioned_type', 'user')
            ->where('mentioned_id', $userId)
            ->count();
        
        $mentionsByType = DB::table('mentions')
            ->where('mentioned_by', $userId)
            ->selectRaw('mentioned_type, COUNT(*) as count')
            ->groupBy('mentioned_type')
            ->get()
            ->keyBy('mentioned_type');
            
        $stats['mentions']['player_mentions'] = $mentionsByType->get('player')->count ?? 0;
        $stats['mentions']['team_mentions'] = $mentionsByType->get('team')->count ?? 0;
        $stats['mentions']['user_mentions'] = $mentionsByType->get('user')->count ?? 0;
        
        // Get activity stats (calculate from existing data since user_activities table doesn't exist)
        $totalActions = $stats['comments']['total'] + $stats['forum']['total'] + 
                       $stats['votes']['upvotes_given'] + $stats['votes']['downvotes_given'] + 
                       $stats['mentions']['given'];
        $stats['activity']['total_actions'] = $totalActions;
        
        // Get last activity from most recent action across all tables
        $lastActivity = null;
        $activitySources = [
            DB::table('news_comments')->where('user_id', $userId)->orderBy('created_at', 'desc')->first(),
            DB::table('match_comments')->where('user_id', $userId)->orderBy('created_at', 'desc')->first(),
            DB::table('forum_threads')->where('user_id', $userId)->orderBy('created_at', 'desc')->first(),
            DB::table('forum_posts')->where('user_id', $userId)->orderBy('created_at', 'desc')->first(),
            DB::table('votes')->where('user_id', $userId)->orderBy('created_at', 'desc')->first(),
            DB::table('mentions')->where('mentioned_by', $userId)->orderBy('created_at', 'desc')->first(),
        ];
        
        foreach ($activitySources as $source) {
            if ($source && (!$lastActivity || $source->created_at > $lastActivity)) {
                $lastActivity = $source->created_at;
            }
        }
        
        $stats['activity']['last_activity'] = $lastActivity;
        
        // Calculate activity score based on various factors
        $activityScore = 
            ($stats['forum']['posts'] * 2) + 
            ($stats['forum']['threads'] * 5) + 
            ($stats['comments']['total'] * 1) + 
            ($stats['votes']['upvotes_given'] * 1) + 
            ($stats['votes']['upvotes_received'] * 3) + 
            ($stats['mentions']['given'] * 1) + 
            ($stats['mentions']['received'] * 2);
        
        $stats['activity']['activity_score'] = $activityScore;
        
        // Get account stats
        $user = User::find($userId);
        if ($user) {
            $stats['account']['days_active'] = $user->created_at->diffInDays(now());
            $stats['account']['last_seen'] = $user->last_login;
            $stats['account']['join_date'] = $user->created_at;
        }
        
        return $stats;
    }

    /**
     * Helper: Get detailed user activity with optimized queries
     */
    private function getDetailedUserActivity($userId, $limit = 50, $offset = 0)
    {
        // Cache recent activity for better performance
        return Cache::remember(
            "user_activity_{$userId}_{$limit}_{$offset}",
            900, // 15 minutes
            function () use ($userId, $limit, $offset) {
                // Highly optimized single query using UNION ALL with indexed columns
                $activities = DB::select("
                    SELECT * FROM (
                        SELECT 
                            nc.id,
                            nc.news_id as item_id,
                            LEFT(nc.content, 200) as content,
                            nc.created_at,
                            n.title as item_title,
                            'news_comment' as type,
                            'news_comment' as resource_type,
                            'Commented on news' as action,
                            JSON_OBJECT('news_title', n.title, 'news_slug', n.slug) as metadata
                        FROM news_comments nc
                        FORCE INDEX (idx_news_comments_user_created)
                        INNER JOIN news n ON nc.news_id = n.id
                        WHERE nc.user_id = ?
                        
                        UNION ALL
                        
                        SELECT 
                            mc.id,
                            mc.match_id as item_id,
                            LEFT(mc.content, 200) as content,
                            mc.created_at,
                            CONCAT(COALESCE(t1.short_name, t1.name, 'T1'), ' vs ', COALESCE(t2.short_name, t2.name, 'T2')) as item_title,
                            'match_comment' as type,
                            'match_comment' as resource_type,
                            'Commented on match' as action,
                            JSON_OBJECT('team1', t1.name, 'team2', t2.name) as metadata
                        FROM match_comments mc
                        FORCE INDEX (idx_match_comments_user_created)
                        INNER JOIN matches m ON mc.match_id = m.id
                        LEFT JOIN teams t1 ON m.team1_id = t1.id
                        LEFT JOIN teams t2 ON m.team2_id = t2.id
                        WHERE mc.user_id = ?
                        
                        UNION ALL
                        
                        SELECT 
                            ft.id,
                            ft.id as item_id,
                            LEFT(ft.title, 200) as content,
                            ft.created_at,
                            ft.title as item_title,
                            'forum_thread' as type,
                            'forum_thread' as resource_type,
                            'Created thread' as action,
                            JSON_OBJECT('category', COALESCE(fc.name, 'General'), 'views', COALESCE(ft.views, 0)) as metadata
                        FROM forum_threads ft
                        FORCE INDEX (idx_forum_threads_user_created)
                        LEFT JOIN forum_categories fc ON ft.category_id = fc.id
                        WHERE ft.user_id = ?
                        
                        UNION ALL
                        
                        SELECT 
                            fp.id,
                            fp.thread_id as item_id,
                            LEFT(fp.content, 200) as content,
                            fp.created_at,
                            ft.title as item_title,
                            'forum_post' as type,
                            'forum_post' as resource_type,
                            'Posted in thread' as action,
                            JSON_OBJECT('thread_title', ft.title, 'category', COALESCE(fc.name, 'General')) as metadata
                        FROM forum_posts fp
                        FORCE INDEX (idx_forum_posts_user_created)
                        INNER JOIN forum_threads ft ON fp.thread_id = ft.id
                        LEFT JOIN forum_categories fc ON ft.category_id = fc.id
                        WHERE fp.user_id = ?
                        
                    ) combined_activities
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?
                ", [$userId, $userId, $userId, $userId, $limit, $offset]);
                
                return collect($activities)->map(function($activity) {
                    $activity->time_ago = $this->getTimeAgo($activity->created_at);
                    $activity->content_preview = strlen($activity->content) > 100 
                        ? substr($activity->content, 0, 100) . '...' 
                        : $activity->content;
                    
                    // Parse metadata if it's a JSON string
                    if (is_string($activity->metadata ?? null)) {
                        $activity->metadata = json_decode($activity->metadata, true);
                    }
                    
                    return $activity;
                });
            }
        );
            
        // Match comments
        $matchComments = DB::table('match_comments as mc')
            ->join('matches as m', 'mc.match_id', '=', 'm.id')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('mc.user_id', $userId)
            ->select([
                'mc.id',
                'mc.match_id as item_id',
                'mc.content',
                'mc.created_at',
                DB::raw("CONCAT(COALESCE(t1.name, 'Team 1'), ' vs ', COALESCE(t2.name, 'Team 2')) as item_title"),
                DB::raw("'match_comment' as type"),
                DB::raw("'match_comment' as resource_type"),
                DB::raw("'Commented on match' as action"),
                DB::raw("JSON_OBJECT('team1', t1.name, 'team2', t2.name) as metadata")
            ]);
            
        // Forum threads
        $forumThreads = DB::table('forum_threads as ft')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->where('ft.user_id', $userId)
            ->select([
                'ft.id',
                'ft.id as item_id',
                'ft.title as content',
                'ft.created_at',
                'ft.title as item_title',
                DB::raw("'forum_thread' as type"),
                DB::raw("'forum_thread' as resource_type"),
                DB::raw("'Created thread' as action"),
                DB::raw("JSON_OBJECT('category', fc.name, 'views', ft.views) as metadata")
            ]);
            
        // Forum posts
        $forumPosts = DB::table('forum_posts as fp')
            ->join('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
            ->leftJoin('forum_categories as fc', 'ft.category_id', '=', 'fc.id')
            ->where('fp.user_id', $userId)
            ->select([
                'fp.id',
                'fp.thread_id as item_id',
                'fp.content',
                'fp.created_at',
                'ft.title as item_title',
                DB::raw("'forum_post' as type"),
                DB::raw("'forum_post' as resource_type"),
                DB::raw("'Posted in thread' as action"),
                DB::raw("JSON_OBJECT('thread_title', ft.title, 'category', fc.name) as metadata")
            ]);
            
        // Votes given
        $votesGiven = DB::table('votes as v')
            ->leftJoin('news as n', function($join) {
                $join->on('v.voteable_id', '=', 'n.id')
                     ->where('v.voteable_type', '=', 'news');
            })
            ->leftJoin('forum_threads as ft', function($join) {
                $join->on('v.voteable_id', '=', 'ft.id')
                     ->where('v.voteable_type', '=', 'forum_threads');
            })
            ->leftJoin('forum_posts as p', function($join) {
                $join->on('v.voteable_id', '=', 'p.id')
                     ->where('v.voteable_type', '=', 'forum_posts');
            })
            ->where('v.user_id', $userId)
            ->select([
                'v.id',
                'v.voteable_id as item_id',
                DB::raw("CONCAT(CASE WHEN v.vote = 1 THEN 'UPVOTE' ELSE 'DOWNVOTE' END, ' on ', v.voteable_type) as content"),
                'v.created_at',
                DB::raw("COALESCE(n.title, ft.title, 'Forum Post') as item_title"),
                DB::raw("'vote' as type"),
                'v.voteable_type as resource_type',
                DB::raw("CONCAT(CASE WHEN v.vote = 1 THEN 'Upvoted' ELSE 'Downvoted' END, ' content') as action"),
                DB::raw("JSON_OBJECT('vote_value', v.vote, 'voteable_type', v.voteable_type) as metadata")
            ]);
            
        // Mentions given
        $mentionsGiven = DB::table('mentions as m')
            ->leftJoin('users as u', function($join) {
                $join->on('m.mentioned_id', '=', 'u.id')
                     ->where('m.mentioned_type', '=', 'user');
            })
            ->leftJoin('players as pl', function($join) {
                $join->on('m.mentioned_id', '=', 'pl.id')
                     ->where('m.mentioned_type', '=', 'player');
            })
            ->leftJoin('teams as t', function($join) {
                $join->on('m.mentioned_id', '=', 't.id')
                     ->where('m.mentioned_type', '=', 'team');
            })
            ->where('m.mentioned_by', $userId)
            ->select([
                'm.id',
                'm.mentioned_id as item_id',
                DB::raw("CONCAT('Mentioned ', m.mentioned_type, ' ', m.mention_text) as content"),
                'm.created_at',
                DB::raw("COALESCE(u.name, pl.username, t.name, 'Unknown') as item_title"),
                DB::raw("'mention' as type"),
                'm.mentioned_type as resource_type',
                DB::raw("CONCAT('Mentioned ', m.mentioned_type) as action"),
                DB::raw("JSON_OBJECT('mention_text', m.mention_text, 'mentioned_type', m.mentioned_type, 'context', m.context) as metadata")
            ]);
            
        // Combine and sort all activities
        $activities = $newsComments
            ->union($matchComments)
            ->union($forumThreads)
            ->union($forumPosts)
            ->union($votesGiven)
            ->union($mentionsGiven)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();
            
        return $activities->map(function($activity) {
            $activity->time_ago = $this->getTimeAgo($activity->created_at);
            $activity->content_preview = strlen($activity->content) > 100 
                ? substr($activity->content, 0, 100) . '...' 
                : $activity->content;
            
            // Parse metadata if it's a JSON string
            if (is_string($activity->metadata ?? null)) {
                $activity->metadata = json_decode($activity->metadata, true);
            }
            
            return $activity;
        });
    }

    /**
     * Helper: Get recent activity (simplified and optimized)
     */
    private function getRecentActivity($userId, $limit = 5)
    {
        return $this->getDetailedUserActivity($userId, $limit, 0);
    }
    
    /**
     * Helper: Get recent activity with even more optimization
     */
    private function getRecentActivityOptimized($userId, $limit = 5)
    {
        return Cache::remember(
            "user_recent_activity_{$userId}_{$limit}",
            600, // 10 minutes
            function () use ($userId, $limit) {
                // Single optimized query for recent activity
                return DB::select("
                    SELECT * FROM (
                        SELECT 
                            'comment' as activity_type,
                            created_at,
                            LEFT(content, 100) as preview,
                            'news' as context
                        FROM news_comments 
                        WHERE user_id = ?
                        ORDER BY created_at DESC
                        LIMIT 3
                    ) news_activity
                    UNION ALL
                    SELECT * FROM (
                        SELECT 
                            'thread' as activity_type,
                            created_at,
                            LEFT(title, 100) as preview,
                            'forum' as context
                        FROM forum_threads 
                        WHERE user_id = ?
                        ORDER BY created_at DESC
                        LIMIT 3
                    ) forum_activity
                    ORDER BY created_at DESC
                    LIMIT ?
                ", [$userId, $userId, $limit]);
            }
        );
    }

    /**
     * Helper: Get time ago string
     */
    private function getTimeAgo($datetime)
    {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }

    /**
     * Helper: Get hero color
     */
    private function getHeroColor($heroName)
    {
        $colors = [
            'Spider-Man' => '#dc2626',
            'Iron Man' => '#f59e0b',
            'Captain America' => '#2563eb',
            'Thor' => '#7c3aed',
            'Hulk' => '#16a34a',
            'Black Widow' => '#1f2937',
            'Hawkeye' => '#7c2d12',
            'Doctor Strange' => '#db2777',
            'Scarlet Witch' => '#dc2626',
            'Loki' => '#16a34a',
            'Venom' => '#1f2937',
            'Magneto' => '#7c3aed',
            'Storm' => '#6b7280',
            'Wolverine' => '#f59e0b',
            'Groot' => '#16a34a',
            'Rocket Raccoon' => '#f59e0b',
            'Star-Lord' => '#dc2626',
            'Mantis' => '#16a34a',
            'Adam Warlock' => '#f59e0b',
            'Luna Snow' => '#3b82f6',
            'Jeff the Land Shark' => '#3b82f6',
            'Cloak & Dagger' => '#6b7280',
            'Emma Frost' => '#e5e7eb',
            'Bruce Banner' => '#16a34a',
            'Mr. Fantastic' => '#3b82f6',
            'Mister Fantastic' => '#3b82f6',
            'Black Panther' => '#7c3aed',
            'Hela' => '#16a34a',
            'Magik' => '#db2777',
            'Moon Knight' => '#e5e7eb',
            'Namor' => '#3b82f6',
            'Psylocke' => '#7c3aed',
            'Punisher' => '#1f2937',
            'The Punisher' => '#1f2937',
            'Winter Soldier' => '#6b7280',
            'Iron Fist' => '#f59e0b',
            'Squirrel Girl' => '#7c2d12',
            'Peni Parker' => '#db2777',
            'The Thing' => '#f59e0b',
            'Human Torch' => '#dc2626',
            'Invisible Woman' => '#3b82f6',
            'Ultron' => '#dc2626'
        ];
        
        return $colors[$heroName] ?? '#6b7280';
    }

    /**
     * Helper: Get hero initials
     */
    private function getHeroInitials($heroName)
    {
        $parts = explode(' ', $heroName);
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($heroName, 0, 2));
    }

    /**
     * Helper: Get role color
     */
    private function getRoleColor($role)
    {
        $colors = [
            'Vanguard' => '#3b82f6',
            'Duelist' => '#dc2626',
            'Strategist' => '#16a34a'
        ];
        
        return $colors[$role] ?? '#6b7280';
    }

    /**
     * Helper: Get region color
     */
    private function getRegionColor($region)
    {
        $colors = [
            'Americas' => '#dc2626',
            'EMEA' => '#3b82f6',
            'Pacific' => '#16a34a',
            'China' => '#f59e0b'
        ];
        
        return $colors[$region] ?? '#6b7280';
    }

    /**
     * Helper: Generate color from name
     */
    private function generateColorFromName($name)
    {
        $colors = [
            '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
            '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9',
            '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
            '#ec4899', '#f43f5e'
        ];
        
        $hash = 0;
        for ($i = 0; $i < strlen($name); $i++) {
            $hash = ord($name[$i]) + (($hash << 5) - $hash);
        }
        
        return $colors[abs($hash) % count($colors)];
    }

    /**
     * Helper: Generate background from name
     */
    private function generateBackgroundFromName($name)
    {
        $backgrounds = [
            '#fef2f2', '#fff7ed', '#fffbeb', '#fefce8', '#f7fee7',
            '#f0fdf4', '#ecfdf5', '#f0fdfa', '#ecfeff', '#f0f9ff',
            '#eff6ff', '#eef2ff', '#f5f3ff', '#faf5ff', '#fdf4ff',
            '#fdf2f8', '#fff1f2'
        ];
        
        $hash = 0;
        for ($i = 0; $i < strlen($name); $i++) {
            $hash = ord($name[$i]) + (($hash << 5) - $hash);
        }
        
        return $backgrounds[abs($hash) % count($backgrounds)];
    }

    /**
     * Helper: Fix user avatar if using old PNG path
     */
    private function fixUserAvatar($user)
    {
        // If user has use_hero_as_avatar set and hero_flair, generate avatar path
        if ($user->use_hero_as_avatar && $user->hero_flair) {
            // Generate the hero image path
            $heroImagePath = $this->getHeroImagePath($user->hero_flair);
            if ($heroImagePath && file_exists(public_path($heroImagePath))) {
                $user->avatar = $heroImagePath;
            }
        }
        // If avatar contains old portrait PNG path
        else if ($user->avatar && strpos($user->avatar, '/portraits/') !== false) {
            // Map specific portrait names to hero names
            $portraitToHero = [
                'venom' => 'Venom',
                'spider-man' => 'Spider-Man',
                'iron-man' => 'Iron Man',
                'captain-america' => 'Captain America',
                'thor' => 'Thor',
                'hulk' => 'Hulk'
            ];
            
            // Extract filename from path
            preg_match('/\/([^\/]+)\.(png|webp)$/', $user->avatar, $matches);
            if (isset($matches[1])) {
                $filename = $matches[1];
                
                // Get hero name from mapping or hero_flair
                $heroName = isset($portraitToHero[$filename]) ? $portraitToHero[$filename] : $user->hero_flair;
                
                if ($heroName) {
                    $newPath = $this->getHeroImagePath($heroName);
                    if ($newPath) {
                        $user->update([
                            'avatar' => $newPath,
                            'use_hero_as_avatar' => true
                        ]);
                    }
                }
            }
        }
    }
    
    /**
     * Get user profile with details (public endpoint)
     */
    public function getUserWithDetails($userId)
    {
        try {
            $user = User::with(['teamFlair'])->findOrFail($userId);
            
            // Fix avatar if needed
            $this->fixUserAvatar($user);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'team_flair' => $user->teamFlair,
                    'show_hero_flair' => (bool)$user->show_hero_flair,
                    'show_team_flair' => (bool)$user->show_team_flair,
                    'use_hero_as_avatar' => (bool)$user->use_hero_as_avatar,
                    'created_at' => $user->created_at,
                    'role' => $user->role ?? 'user'
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user profile'
            ], 500);
        }
    }
    
    /**
     * Get user statistics (public endpoint)
     */
    public function getUserStatsPublic($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $stats = $user->getStatsWithCache();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user statistics',
                'data' => [
                    'news_comments' => 0,
                    'match_comments' => 0,
                    'forum_threads' => 0,
                    'forum_posts' => 0,
                    'upvotes_given' => 0,
                    'downvotes_given' => 0,
                    'upvotes_received' => 0,
                    'downvotes_received' => 0
                ]
            ], 200);
        }
    }
    
    /**
     * Get user activities
     */
    public function getUserActivities($userId)
    {
        try {
            $activities = $this->getRecentActivityOptimized($userId, 20);
            
            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user activities',
                'data' => []
            ], 200);
        }
    }
    
    /**
     * Get user achievements
     */
    public function getUserAchievements($userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Get achievements from cache if available
            $achievements = Cache::remember(
                "user_achievements_{$userId}",
                300,
                function () use ($user) {
                    if (method_exists($user, 'achievements')) {
                        return $user->achievements()->get();
                    }
                    return [];
                }
            );
            
            return response()->json([
                'success' => true,
                'data' => $achievements
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load achievements',
                'data' => []
            ], 200);
        }
    }
    
    /**
     * Get user forum statistics
     */
    public function getUserForumStats($userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            $forumStats = Cache::remember(
                "user_forum_stats_{$userId}",
                300,
                function () use ($user) {
                    return [
                        'threads_created' => $user->forumThreads()->count(),
                        'posts_created' => $user->forumPosts()->count(),
                        'total_thread_views' => $user->forumThreads()->sum('views'),
                        'total_thread_replies' => $user->forumThreads()->sum('replies')
                    ];
                }
            );
            
            return response()->json([
                'success' => true,
                'data' => $forumStats
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load forum stats',
                'data' => [
                    'threads_created' => 0,
                    'posts_created' => 0,
                    'total_thread_views' => 0,
                    'total_thread_replies' => 0
                ]
            ], 200);
        }
    }
    
    /**
     * Get user match history
     */
    public function getUserMatches($userId)
    {
        try {
            // For now, return empty as match history is player-based, not user-based
            // This can be expanded if users are linked to players
            
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Match history is available for players, not users'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load match history',
                'data' => []
            ], 200);
        }
    }
}