<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Update last login
            try {
                $user->update(['last_login' => now()]);
            } catch (\Exception $e) {
                // Log but don't fail the login
                \Log::warning('Failed to update last_login: ' . $e->getMessage());
            }

            $token = $user->createToken('auth-token')->accessToken;
            
            // Load team flair relationship
            $user->load('teamFlair');

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'user',
                    'roles' => [$user->role ?? 'user'], // For frontend compatibility
                    'role_display_name' => $user->getRoleDisplayName(),
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'team_flair' => $user->teamFlair,
                    'team_flair_id' => $user->team_flair_id,
                    'show_hero_flair' => (bool)$user->show_hero_flair,
                    'show_team_flair' => (bool)$user->show_team_flair,
                    'use_hero_as_avatar' => (bool)$user->use_hero_as_avatar,
                    'created_at' => $user->created_at->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'An error occurred during login'
            ], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password), // Hash the password
            ]);

            // Assign role with error handling
            try {
                $user->assignRole('user');
            } catch (\Exception $e) {
                \Log::warning('Could not assign role to user: ' . $e->getMessage());
            }

            // Create token
            $token = $user->createToken('auth-token')->accessToken;
            
            // Load team flair relationship
            $user->load('teamFlair');

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'user',
                    'roles' => [$user->role ?? 'user'], // For frontend compatibility
                    'role_display_name' => $user->getRoleDisplayName(),
                    'avatar' => $user->avatar,
                    'hero_flair' => $user->hero_flair,
                    'team_flair' => $user->teamFlair,
                    'team_flair_id' => $user->team_flair_id,
                    'show_hero_flair' => (bool)$user->show_hero_flair,
                    'show_team_flair' => (bool)$user->show_team_flair,
                    'use_hero_as_avatar' => (bool)$user->use_hero_as_avatar,
                    'created_at' => $user->created_at->toISOString()
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Registration error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'An error occurred during registration'
            ], 500);
        }
    }

    public function user()
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // Load team flair relationship
        $user->load('teamFlair');
        
        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'user',
                'roles' => [$user->role ?? 'user'], // For frontend compatibility
                'role_display_name' => $user->getRoleDisplayName(),
                'avatar' => $user->avatar,
                'hero_flair' => $user->hero_flair,
                'team_flair' => $user->teamFlair,
                'team_flair_id' => $user->team_flair_id,
                'show_hero_flair' => (bool)$user->show_hero_flair,
                'show_team_flair' => (bool)$user->show_team_flair,
                'use_hero_as_avatar' => (bool)$user->use_hero_as_avatar,
                'created_at' => $user->created_at->toISOString()
            ],
            'success' => true
        ]);
    }

    public function logout()
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        $user->token()->revoke();
        
        return response()->json([
            'message' => 'Successfully logged out',
            'success' => true
        ]);
    }

    public function me()
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'user',
                'roles' => [$user->role ?? 'user'], // For frontend compatibility
                'role_display_name' => $user->getRoleDisplayName(),
                'avatar' => $user->avatar,
                'created_at' => $user->created_at->toISOString()
            ],
            'success' => true
        ]);
    }

    public function refresh()
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // Revoke current token
        $user->token()->revoke();
        
        // Create new token
        $token = $user->createToken('auth-token')->accessToken;
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'message' => 'Token refreshed successfully'
        ]);
    }

    public function getUserStats()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Get real user statistics with safe table checks
            $stats = [];
            
            // Comments count (safe query)
            $commentsCount = 0;
            if (\Schema::hasTable('news_comments')) {
                $commentsCount += \DB::table('news_comments')->where('user_id', $user->id)->count();
            }
            if (\Schema::hasTable('match_comments')) {
                $commentsCount += \DB::table('match_comments')->where('user_id', $user->id)->count();
            }
            $stats['total_comments'] = $commentsCount;
            
            // Forum activity
            $stats['total_forum_posts'] = \Schema::hasTable('posts') ? 
                \DB::table('posts')->where('user_id', $user->id)->count() : 0;
            $stats['total_forum_threads'] = \Schema::hasTable('threads') ? 
                \DB::table('threads')->where('user_id', $user->id)->count() : 0;
            
            // Voting activity
            $votesCount = 0;
            $upvotesGiven = 0;
            $downvotesGiven = 0;
            
            $voteTables = ['thread_votes', 'post_votes', 'news_comment_votes', 'match_comment_votes'];
            foreach ($voteTables as $table) {
                if (\Schema::hasTable($table)) {
                    $votesCount += \DB::table($table)->where('user_id', $user->id)->count();
                    $upvotesGiven += \DB::table($table)->where('user_id', $user->id)->where('type', 'up')->count();
                    $downvotesGiven += \DB::table($table)->where('user_id', $user->id)->where('type', 'down')->count();
                }
            }
            $stats['total_votes'] = $votesCount;
            $stats['upvotes_given'] = $upvotesGiven;
            $stats['downvotes_given'] = $downvotesGiven;
            
            // Votes received (more complex queries, safely handled)
            $upvotesReceived = 0;
            $downvotesReceived = 0;
            
            if (\Schema::hasTable('post_votes') && \Schema::hasTable('posts')) {
                $upvotesReceived += \DB::table('post_votes')
                    ->join('posts', 'posts.id', '=', 'post_votes.post_id')
                    ->where('posts.user_id', $user->id)
                    ->where('post_votes.type', 'up')
                    ->count();
                $downvotesReceived += \DB::table('post_votes')
                    ->join('posts', 'posts.id', '=', 'post_votes.post_id')
                    ->where('posts.user_id', $user->id)
                    ->where('post_votes.type', 'down')
                    ->count();
            }
            
            if (\Schema::hasTable('thread_votes') && \Schema::hasTable('threads')) {
                $upvotesReceived += \DB::table('thread_votes')
                    ->join('threads', 'threads.id', '=', 'thread_votes.thread_id')
                    ->where('threads.user_id', $user->id)
                    ->where('thread_votes.type', 'up')
                    ->count();
                $downvotesReceived += \DB::table('thread_votes')
                    ->join('threads', 'threads.id', '=', 'thread_votes.thread_id')
                    ->where('threads.user_id', $user->id)
                    ->where('thread_votes.type', 'down')
                    ->count();
            }
            
            $stats['upvotes_received'] = $upvotesReceived;
            $stats['downvotes_received'] = $downvotesReceived;
            $stats['days_active'] = $user->created_at ? $user->created_at->diffInDays(now()) : 0;

            return response()->json([
                'data' => [
                    'stats' => $stats
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            \Log::error('User stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user stats',
                'error' => app()->environment('local') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    public function getUserProfileActivity()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Get user activities from the user_activities table first (if it exists)
            $activities = collect();
            
            // Check if user_activities table exists
            if (\Schema::hasTable('user_activities')) {
                $userActivities = \DB::table('user_activities')
                    ->where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($activity) {
                        return [
                            'action' => $activity->action,
                            'content' => $activity->content,
                            'created_at' => $activity->created_at
                        ];
                    });
                $activities = $activities->merge($userActivities);
            }

            // Also get real user activity from various tables (with safe checks)
            // Forum threads
            if (\Schema::hasTable('threads')) {
                $threads = \DB::table('threads')
                    ->where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($thread) {
                        return [
                            'action' => 'Created forum thread',
                            'content' => $thread->title ?? 'Thread',
                            'created_at' => $thread->created_at
                        ];
                    });
                $activities = $activities->merge($threads);
            }

            // Forum posts
            if (\Schema::hasTable('posts')) {
                $posts = \DB::table('posts')
                    ->where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($post) {
                        return [
                            'action' => 'Posted in forum',
                            'content' => \Str::limit($post->content ?? 'Post', 100),
                            'created_at' => $post->created_at
                        ];
                    });
                $activities = $activities->merge($posts);
            }

            // News comments (check if table exists)
            if (\Schema::hasTable('news_comments')) {
                $newsComments = \DB::table('news_comments')
                    ->where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($comment) {
                        return [
                            'action' => 'Commented on news',
                            'content' => \Str::limit($comment->content ?? 'Comment', 100),
                            'created_at' => $comment->created_at
                        ];
                    });
                $activities = $activities->merge($newsComments);
            }

            // Match comments (check if table exists)
            if (\Schema::hasTable('match_comments')) {
                $matchComments = \DB::table('match_comments')
                    ->where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($comment) {
                        return [
                            'action' => 'Commented on match',
                            'content' => \Str::limit($comment->content ?? 'Comment', 100),
                            'created_at' => $comment->created_at
                        ];
                    });
                $activities = $activities->merge($matchComments);
            }

            // Sort all activities by date and take the most recent 10
            $activities = $activities->sortByDesc('created_at')->take(10)->values();

            return response()->json([
                'data' => [
                    'activities' => $activities
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            \Log::error('User activity error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user activity',
                'error' => app()->environment('local') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    public function updateProfileFlairs(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $validated = $request->validate([
                'hero_flair' => 'nullable|string',
                'team_flair_id' => 'nullable|integer',
                'show_hero_flair' => 'nullable|boolean',
                'show_team_flair' => 'nullable|boolean'
            ]);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Flairs updated successfully',
                'data' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            \Log::error('Update flairs error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating flairs'
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $validated = $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:8',
                'new_password_confirmation' => 'required|same:new_password'
            ]);

            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            $user->update(['password' => Hash::make($validated['new_password'])]);

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Change password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error changing password'
            ], 500);
        }
    }

    public function changeEmail(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $validated = $request->validate([
                'new_email' => 'required|email|unique:users,email,' . $user->id,
                'password' => 'required'
            ]);

            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 422);
            }

            $user->update(['email' => $validated['new_email']]);

            return response()->json([
                'success' => true,
                'message' => 'Email updated successfully',
                'data' => ['email' => $user->email]
            ]);

        } catch (\Exception $e) {
            \Log::error('Change email error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error changing email'
            ], 500);
        }
    }

    public function changeUsername(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $validated = $request->validate([
                'new_name' => 'required|string|max:255|unique:users,name,' . $user->id,
                'password' => 'required'
            ]);

            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 422);
            }

            $user->update(['name' => $validated['new_name']]);

            return response()->json([
                'success' => true,
                'message' => 'Username updated successfully',
                'data' => ['name' => $user->name]
            ]);

        } catch (\Exception $e) {
            \Log::error('Change username error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error changing username'
            ], 500);
        }
    }

    public function uploadAvatar(Request $request)
    {
        // Custom avatar uploads are disabled - only hero avatars are allowed
        return response()->json([
            'success' => false,
            'message' => 'Custom avatar uploads are disabled. Please use hero avatars only.'
        ], 403);
    }

    public function sendPasswordResetLinkEmail(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email address'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link. Please try again later.'
            ], 500);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Password reset request error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed'
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Your password has been reset successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. The reset link may be invalid or expired.'
            ], 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Password reset error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while resetting your password'
            ], 500);
        }
    }
}
