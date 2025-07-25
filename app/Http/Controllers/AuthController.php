<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed'
        ]);
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            // password will be hashed by the model mutator
            'password' => $data['password']
        ]);
        $user->assignRole('user');
        $token = $user->createToken('auth_token')->accessToken;
        
        // Load user relationships
        $user->load('teamFlair');

        return response()->json([
            'message' => 'User registered successfully',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'hero_flair' => $user->hero_flair,
                'team_flair_id' => $user->team_flair_id,
                'team_flair' => $user->teamFlair,
                'show_hero_flair' => $user->show_hero_flair,
                'show_team_flair' => $user->show_team_flair,
                'use_hero_as_avatar' => $user->use_hero_as_avatar,
                'roles' => $user->getRoleNames()
            ],
            'token'   => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string'
        ]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        $token = $user->createToken('auth_token')->accessToken;
        
        // Load user relationships
        $user->load('teamFlair');
        
        return response()->json([
            'message' => 'Login successful',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'hero_flair' => $user->hero_flair,
                'team_flair_id' => $user->team_flair_id,
                'team_flair' => $user->teamFlair,
                'show_hero_flair' => $user->show_hero_flair,
                'show_team_flair' => $user->show_team_flair,
                'use_hero_as_avatar' => $user->use_hero_as_avatar,
                'roles' => $user->getRoleNames()
            ],
            'token'   => $token
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->token()->revoke();
        }
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        
        // Load user relationships
        $user->load('teamFlair');
        
        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'hero_flair' => $user->hero_flair,
            'team_flair_id' => $user->team_flair_id,
            'team_flair' => $user->teamFlair,
            'show_hero_flair' => $user->show_hero_flair,
            'show_team_flair' => $user->show_team_flair,
            'use_hero_as_avatar' => $user->use_hero_as_avatar,
            'roles' => $user->getRoleNames()
        ]);
    }

    public function getUserStats(Request $request)
    {
        try {
            $user = $request->user();
            
            $stats = [
                'total_posts' => \DB::table('forum_posts')->where('user_id', $user->id)->count(),
                'total_threads' => \DB::table('forum_threads')->where('user_id', $user->id)->count(),
                'total_comments' => \DB::table('news_comments')->where('user_id', $user->id)->count() + 
                                  \DB::table('match_comments')->where('user_id', $user->id)->count(),
                'joined_date' => $user->created_at->format('Y-m-d'),
                'last_active' => $user->updated_at->format('Y-m-d H:i:s'),
                'reputation' => $user->reputation ?? 0,
                'achievements' => 0, // No achievements table
                'favorite_teams' => \DB::table('user_favorite_teams')->where('user_id', $user->id)->count(),
                'favorite_players' => \DB::table('user_favorite_players')->where('user_id', $user->id)->count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            // Fallback stats if tables don't exist
            $user = $request->user();
            $stats = [
                'total_posts' => 0,
                'total_threads' => 0,
                'total_comments' => 0,
                'joined_date' => $user->created_at->format('Y-m-d'),
                'last_active' => $user->updated_at->format('Y-m-d H:i:s'),
                'reputation' => 0,
                'achievements' => 0,
                'favorite_teams' => 0,
                'favorite_players' => 0
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        }
    }

    public function getUserProfileActivity(Request $request)
    {
        try {
            $user = $request->user();
            $limit = $request->input('limit', 20);
            
            // Get recent forum activity using direct DB queries
            $forumPosts = \DB::table('forum_posts as fp')
                ->leftJoin('forum_threads as ft', 'fp.thread_id', '=', 'ft.id')
                ->where('fp.user_id', $user->id)
                ->select([
                    'fp.id',
                    'fp.content',
                    'fp.created_at',
                    'ft.id as thread_id',
                    'ft.title as thread_title',
                    'ft.category as thread_category'
                ])
                ->orderBy('fp.created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($post) {
                    return [
                        'type' => 'forum_post',
                        'content' => Str::limit($post->content, 150),
                        'thread' => [
                            'id' => $post->thread_id,
                            'title' => $post->thread_title,
                            'category' => $post->thread_category
                        ],
                        'created_at' => $post->created_at,
                        'created_at_human' => \Carbon\Carbon::parse($post->created_at)->diffForHumans()
                    ];
                });
                
            // Get recent forum threads using direct DB query
            $forumThreads = \DB::table('forum_threads')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($thread) {
                    return [
                        'type' => 'forum_thread',
                        'content' => Str::limit($thread->content, 150),
                        'thread' => [
                            'id' => $thread->id,
                            'title' => $thread->title,
                            'category' => $thread->category
                        ],
                        'created_at' => $thread->created_at,
                        'created_at_human' => \Carbon\Carbon::parse($thread->created_at)->diffForHumans()
                    ];
                });
                
            // Combine and sort by date
            $activity = $forumPosts->concat($forumThreads)
                ->sortByDesc('created_at')
                ->take($limit)
                ->values();
                
            return response()->json([
                'success' => true,
                'data' => $activity
            ]);
        } catch (\Exception $e) {
            // Fallback to empty activity if tables don't exist
            $user = $request->user();
            $limit = $request->input('limit', 20);
            
            $activity = collect([]);
                
            return response()->json([
                'success' => true,
                'data' => $activity
            ]);
        }
    }

    public function getUserNotifications(Request $request)
    {
        try {
            $user = $request->user();
            $notifications = $user->notifications()
                ->latest()
                ->paginate(20);
                
            return response()->json([
                'success' => true,
                'data' => $notifications->items(),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total()
                ]
            ]);
        } catch (\Exception $e) {
            // Notifications table doesn't exist, return empty array
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 0
                ],
                'message' => 'No notifications found'
            ]);
        }
    }

    public function markNotificationRead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $notification = $user->notifications()->find($id);
            
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }
            
            $notification->markAsRead();
            
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notifications not available'
            ], 404);
        }
    }

    public function markAllNotificationsRead(Request $request)
    {
        try {
            $user = $request->user();
            $user->unreadNotifications->markAsRead();
            
            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'No notifications to mark as read'
            ]);
        }
    }

    public function sendPasswordResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        
        $status = Password::sendResetLink(
            $request->only('email')
        );
        
        return $status === Password::RESET_LINK_SENT
            ? response()->json(['success' => true, 'message' => __($status)])
            : response()->json(['success' => false, 'message' => __($status)], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);
        
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password
                ])->setRememberToken(Str::random(60));
                
                $user->save();
            }
        );
        
        return $status === Password::PASSWORD_RESET
            ? response()->json(['success' => true, 'message' => __($status)])
            : response()->json(['success' => false, 'message' => __($status)], 400);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        
        // Revoke current token
        $user->token()->revoke();
        
        // Create new token
        $token = $user->createToken('auth_token')->accessToken;
        
        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()
            ]
        ]);
    }

}