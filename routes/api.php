<?php
use App\Http\Controllers\{
    AuthController,
    TeamController,
    PlayerController,
    MatchController,
    EventController,
    SearchController,
    ForumController,
    AdminStatsController,
    NewsController,
    ImageUploadController
};
use Illuminate\Http\Request;

// Public Authentication Routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Public Data Routes
Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
// Public Player Detail
Route::get('/players/{playerId}', function (Request $request, $playerId) {
    try {
        $player = DB::table('players as p')
            ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
            ->select([
                'p.id', 'p.name', 'p.username', 'p.real_name', 'p.role', 
                'p.main_hero', 'p.alt_heroes', 'p.region', 'p.country', 
                'p.rating', 'p.age', 'p.earnings', 'p.social_media', 
                'p.biography', 'p.avatar', 'p.team_id',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ])
            ->where('p.id', $playerId)
            ->first();

        if (!$player) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found'
            ], 404);
        }

        // Format player data
        $formattedPlayer = [
            'id' => $player->id,
            'name' => $player->name,
            'username' => $player->username,
            'real_name' => $player->real_name,
            'role' => $player->role,
            'main_hero' => $player->main_hero,
            'alt_heroes' => $player->alt_heroes ? json_decode($player->alt_heroes, true) : [],
            'region' => $player->region,
            'country' => $player->country,
            'rating' => $player->rating ?? 1000,
            'age' => $player->age,
            'earnings' => $player->earnings ?? '$0',
            'social_media' => $player->social_media ? json_decode($player->social_media, true) : [],
            'biography' => $player->biography,
            'avatar' => $player->avatar,
            'team_id' => $player->team_id,
            'team' => $player->team_id ? [
                'id' => $player->team_id,
                'name' => $player->team_name,
                'short_name' => $player->team_short,
                'logo' => $player->team_logo
            ] : null
        ];

        return response()->json([
            'data' => $formattedPlayer,
            'success' => true
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching player: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{player}', [PlayerController::class, 'show']);
Route::get('/matches', [MatchController::class, 'index']);
Route::get('/matches/live', [MatchController::class, 'live']);
Route::get('/matches/{gameMatch}', [MatchController::class, 'show']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/rankings', [TeamController::class, 'rankings']);
Route::get('/search', [SearchController::class, 'search']);

// Public News Routes
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/categories', [NewsController::class, 'categories']);
Route::get('/news/{slug}', [NewsController::class, 'show']);

// Public Forum Routes
Route::get('/forum/threads', [ForumController::class, 'index']);
Route::get('/forum/threads/{thread}', [ForumController::class, 'show']);

// Test authentication endpoint
Route::middleware('auth:sanctum')->get('/test-auth', function (Request $request) {
    return response()->json([
        'message' => 'Authentication working!',
        'user_id' => $request->user()->id,
        'success' => true
    ]);
});

// Direct authenticated routes
Route::middleware('auth:sanctum')->get('/user-direct', function (Request $request) {
    return response()->json([
        'data' => [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ],
        'success' => true
    ]);
});

// Working authenticated routes using closures
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();
    return response()->json([
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'avatar' => $user->avatar,
            'created_at' => $user->created_at->toISOString()
        ],
        'success' => true
    ]);
});

Route::middleware('auth:sanctum')->post('/auth/logout', function (Request $request) {
    try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        // Delete the current access token
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }
        
        return response()->json([
            'message' => 'Successfully logged out',
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Logout error: ' . $e->getMessage()
        ], 500);
    }
});

// Authenticated Routes  
// Route::get('/user', [AuthController::class, 'user'])->middleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
// Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

// Forum Read Operations - GET THREADS LIST
Route::get('/forums/threads', function (Request $request) {
    try {
        $query = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.category', 
                'ft.views', 'ft.replies', 'ft.pinned', 'ft.locked',
                'ft.created_at', 'ft.updated_at',
                'u.id as user_id', 'u.name as user_name', 'u.avatar as user_avatar'
            ]);

        if ($request->category && $request->category !== 'all') {
            $query->where('ft.category', $request->category);
        }

        $threads = $query->orderBy('ft.pinned', 'desc')
                         ->orderBy('ft.created_at', 'desc')
                         ->limit(50)
                         ->get();

        return response()->json([
            'data' => $threads,
            'total' => $threads->count(),
            'success' => true
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching threads: ' . $e->getMessage()
        ], 500);
    }
});

// Forum Write Operations - CREATE THREAD
Route::middleware('auth:sanctum')->post('/forums/threads', function (Request $request) {
    try {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'category' => 'nullable|string|in:general,strategies,team-recruitment,announcements,bugs,feedback,discussion,guides'
        ]);
        
        $validated['user_id'] = $request->user()->id;
        $validated['category'] = $validated['category'] ?? 'general';
        $validated['pinned'] = false;  // Fixed: use 'pinned' not 'is_pinned'
        $validated['locked'] = false;  // Fixed: use 'locked' not 'is_locked'
        $validated['views'] = 0;
        $validated['replies'] = 0;
        
        $threadId = DB::table('forum_threads')->insertGetId($validated);
        
        // Get the created thread with user info
        $thread = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.category', 
                'ft.views', 'ft.replies', 'ft.pinned', 'ft.locked',
                'ft.created_at', 'ft.updated_at',
                'u.id as user_id', 'u.name as user_name', 'u.avatar as user_avatar'
            ])
            ->where('ft.id', $threadId)
            ->first();
        
        return response()->json([
            'data' => $thread,
            'success' => true,
            'message' => 'Thread created successfully'
        ], 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// Forum Write Operations - EXISTING
Route::post('/forum/threads', [ForumController::class, 'store'])->middleware('auth:sanctum');

// Test admin endpoint
Route::middleware(['auth:sanctum', 'role:admin'])->get('/test-admin', function (Request $request) {
    return response()->json([
        'message' => 'Admin access working!',
        'user' => $request->user()->name,
        'roles' => $request->user()->getRoleNames(),
        'success' => true
    ]);
});

// Working admin routes using closures
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/stats', function () {
    $stats = [
        'overview' => [
            'totalTeams' => \App\Models\Team::count(),
            'totalPlayers' => \App\Models\Player::count(),
            'totalMatches' => \App\Models\GameMatch::count(),
            'liveMatches' => \App\Models\GameMatch::where('status', 'live')->count(),
            'totalEvents' => \App\Models\Event::count(),
            'activeEvents' => \App\Models\Event::where('status', 'live')->count(),
            'totalUsers' => \App\Models\User::count(),
            'totalThreads' => \App\Models\ForumThread::count(),
        ],
    ];
    
    return response()->json([
        'data' => $stats,
        'success' => true
    ]);
});

// Working admin CRUD routes
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/teams', function (Request $request) {
    try {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:10',
            'region' => 'required|string',
            'country' => 'nullable|string',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);
        
        // Set default values for required fields
        $validated['short_name'] = $validated['short_name'] ?? strtoupper(substr($validated['name'], 0, 3));
        $validated['rating'] = 1000; // Default rating
        $validated['rank'] = 999; // Default rank
        
        $team = \App\Models\Team::create($validated);
        
        return response()->json([
            'data' => $team,
            'success' => true,
            'message' => 'Team created successfully'
        ], 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/teams/{team}', function (Request $request, $teamId) {
    $team = \App\Models\Team::findOrFail($teamId);
    
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'region' => 'required|string',
        'description' => 'nullable|string',
        'logo' => 'nullable|string',
    ]);
    
    $team->update($validated);
    
    return response()->json([
        'data' => $team->fresh(),
        'success' => true,
        'message' => 'Team updated successfully'
    ]);
});

Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/teams/{team}', function (Request $request, $teamId) {
    $team = \App\Models\Team::findOrFail($teamId);
    $teamName = $team->name;
    $team->delete();
    
    return response()->json([
        'success' => true,
        'message' => "Team '{$teamName}' deleted successfully"
    ]);
});

Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/players', function (Request $request) {
    try {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:players',
            'real_name' => 'nullable|string|max:255',
            'role' => 'required|string|in:Duelist,Tank,Support,Controller,Initiator,Coach,IGL,Flex,Sub',
            'team_id' => 'nullable|exists:teams,id',
            'main_hero' => 'nullable|string',
            'alt_heroes' => 'nullable|array',
            'region' => 'nullable|string|max:10',
            'country' => 'nullable|string',
            'rating' => 'nullable|numeric|min:0|max:5000',
            'age' => 'nullable|integer|min:13|max:50',
            'social_media' => 'nullable|array',
            'biography' => 'nullable|string',
            'avatar' => 'nullable|string'
        ]);
        
        // Set name to username if name is not provided
        if (empty($validated['name'])) {
            $validated['name'] = $validated['username'];
        }
        
        // Set default values for required fields that might be missing
        $validated['rating'] = $validated['rating'] ?? 1000;
        $validated['region'] = $validated['region'] ?? 'NA';
        $validated['country'] = $validated['country'] ?? 'Unknown';
        $validated['main_hero'] = $validated['main_hero'] ?? 'Phoenix';
        
        $player = \App\Models\Player::create($validated);
        
        return response()->json([
            'data' => $player->load('team'),
            'success' => true,
            'message' => 'Player created successfully'
        ], 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/players/{playerId}', function (Request $request, $playerId) {
    try {
        $player = \App\Models\Player::findOrFail($playerId);
        
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:players,username,' . $playerId,
            'real_name' => 'nullable|string|max:255',
            'role' => 'required|string|in:Duelist,Tank,Support,Controller,Initiator,Coach,IGL,Flex,Sub',
            'team_id' => 'nullable|exists:teams,id',
            'main_hero' => 'nullable|string',
            'alt_heroes' => 'nullable|array',
            'region' => 'nullable|string|max:10',
            'country' => 'nullable|string',
            'rating' => 'nullable|numeric|min:0|max:5000',
            'age' => 'nullable|integer|min:13|max:50',
            'social_media' => 'nullable|array',
            'biography' => 'nullable|string',
            'avatar' => 'nullable|string'
        ]);
        
        // Set name to username if name is not provided
        if (empty($validated['name'])) {
            $validated['name'] = $validated['username'];
        }
        
        $player->update($validated);
        
        return response()->json([
            'data' => $player->fresh()->load('team'),
            'success' => true,
            'message' => 'Player updated successfully'
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/players/{player}', function (Request $request, $playerId) {
    $player = \App\Models\Player::findOrFail($playerId);
    $playerName = $player->name;
    $player->delete();
    
    return response()->json([
        'success' => true,
        'message' => "Player '{$playerName}' deleted successfully"
    ]);
});

// User Management for Admin
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/users', function () {
    $users = \App\Models\User::with('roles')->paginate(15);
    
    return response()->json([
        'data' => $users->items(),
        'meta' => [
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total()
        ],
        'success' => true
    ]);
});

Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/users', function (Request $request) {
    try {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|string',
            'status' => 'nullable|string|in:active,inactive,banned',
            'avatar' => 'nullable|string'
        ]);
        
        // Normalize role to lowercase and validate
        $role = strtolower(trim($validated['role']));
        $allowedRoles = ['admin', 'moderator', 'user'];
        
        if (!in_array($role, $allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role. Allowed roles: ' . implode(', ', $allowedRoles),
                'errors' => ['role' => ['The selected role is invalid.']]
            ], 422);
        }
        
        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // Model handles hashing
            'status' => $validated['status'] ?? 'active',
            'avatar' => $validated['avatar'] ?? null
        ]);
        
        $user->assignRole($role);
        
        return response()->json([
            'data' => $user->load('roles'),
            'success' => true,
            'message' => 'User created successfully'
        ], 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/users/{userId}', function (Request $request, $userId) {
    try {
        $user = \App\Models\User::findOrFail($userId);
        
        // Get current user data to fill in missing fields
        $currentData = [
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status ?? 'active'
        ];
        
        // Merge request data with current data
        $requestData = $request->all();
        $updateData = array_merge($currentData, $requestData);
        
        $validated = validator($updateData, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $userId,
            'password' => 'nullable|min:8',
            'role' => 'required|string',
            'status' => 'nullable|string|in:active,inactive,banned',
            'avatar' => 'nullable|string'
        ])->validate();
        
        // Normalize role to lowercase and validate
        $role = strtolower(trim($validated['role']));
        $allowedRoles = ['admin', 'moderator', 'user'];
        
        if (!in_array($role, $allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role. Allowed roles: ' . implode(', ', $allowedRoles),
                'errors' => ['role' => ['The selected role is invalid.']]
            ], 422);
        }
        
        $updateFields = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'] ?? $user->status ?? 'active',
            'avatar' => $validated['avatar'] ?? $user->avatar
        ];
        
        if (!empty($validated['password'])) {
            $updateFields['password'] = $validated['password']; // Model handles hashing
        }
        
        $user->update($updateFields);
        $user->syncRoles([$role]);
        
        return response()->json([
            'data' => $user->fresh()->load('roles'),
            'success' => true,
            'message' => 'User updated successfully'
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// Add PATCH route for partial user updates (role/status only)
Route::middleware(['auth:sanctum', 'role:admin'])->patch('/admin/users/{userId}', function (Request $request, $userId) {
    try {
        $user = \App\Models\User::findOrFail($userId);
        
        $validated = $request->validate([
            'role' => 'nullable|string|in:admin,moderator,user',
            'status' => 'nullable|string|in:active,inactive,banned'
        ]);
        
        // Update only provided fields
        if (isset($validated['status'])) {
            $user->update(['status' => $validated['status']]);
        }
        
        if (isset($validated['role'])) {
            $role = strtolower(trim($validated['role']));
            $user->syncRoles([$role]);
        }
        
        return response()->json([
            'data' => $user->fresh()->load('roles'),
            'success' => true,
            'message' => 'User updated successfully'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/users/{user}', function (Request $request, $userId) {
    $user = \App\Models\User::findOrFail($userId);
    $userName = $user->name;
    
    // Prevent admin from deleting themselves
    if ($user->id === $request->user()->id) {
        return response()->json([
            'success' => false,
            'message' => 'You cannot delete your own account'
        ], 403);
    }
    
    $user->delete();
    
    return response()->json([
        'success' => true,
        'message' => "User '{$userName}' deleted successfully"
    ]);
});

// ==========================================
// MISSING ADMIN GET ROUTES - ADDING NOW
// ==========================================

// Admin Team GET for editing
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/teams/{teamId}', function (Request $request, $teamId) {
    try {
        $team = \App\Models\Team::findOrFail($teamId);
        
        return response()->json([
            'data' => $team,
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Team not found: ' . $e->getMessage()
        ], 404);
    }
});

// Admin Player GET for editing
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/players/{playerId}', function (Request $request, $playerId) {
    try {
        $player = \App\Models\Player::with('team')->findOrFail($playerId);
        
        return response()->json([
            'data' => $player,
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Player not found: ' . $e->getMessage()
        ], 404);
    }
});

// Admin Match GET for editing
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/matches/{matchId}', function (Request $request, $matchId) {
    try {
        $match = \App\Models\GameMatch::with(['team1', 'team2', 'event'])->findOrFail($matchId);
        
        return response()->json([
            'data' => $match,
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Match not found: ' . $e->getMessage()
        ], 404);
    }
});

// Admin News GET for editing
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/news/{newsId}', function (Request $request, $newsId) {
    try {
        $news = \App\Models\News::with('author')->findOrFail($newsId);
        
        return response()->json([
            'data' => $news,
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'News not found: ' . $e->getMessage()
        ], 404);
    }
});

// Admin User GET for editing
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/users/{userId}', function (Request $request, $userId) {
    try {
        $user = \App\Models\User::with('roles')->findOrFail($userId);
        
        return response()->json([
            'data' => $user,
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'User not found: ' . $e->getMessage()
        ], 404);
    }
});

// ==========================================
// MISSING ADMIN ROUTES - ADDING NOW
// ==========================================

// Admin Match Management - CREATE
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/matches', function (Request $request) {
    try {
        $validated = $request->validate([
            'team1_id' => 'required|exists:teams,id',
            'team2_id' => 'required|exists:teams,id|different:team1_id',
            'event_id' => 'required|exists:events,id',
            'scheduled_at' => 'required|date|after:now',
            'format' => 'required|string|in:BO1,BO3,BO5',
            'status' => 'nullable|string|in:upcoming,live,completed',
            'stream_url' => 'nullable|url'
        ]);
        
        $validated['status'] = $validated['status'] ?? 'upcoming';
        
        $match = \App\Models\GameMatch::create($validated);
        
        return response()->json([
            'data' => $match->load(['team1', 'team2', 'event']),
            'success' => true,
            'message' => 'Match created successfully'
        ], 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// Admin Match Management - UPDATE
Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/matches/{matchId}', function (Request $request, $matchId) {
    try {
        $match = \App\Models\GameMatch::findOrFail($matchId);
        
        $validated = $request->validate([
            'status' => 'nullable|string|in:upcoming,live,completed',
            'team1_score' => 'nullable|integer|min:0',
            'team2_score' => 'nullable|integer|min:0',
            'stream_url' => 'nullable|url',
            'scheduled_at' => 'nullable|date'
        ]);
        
        $match->update($validated);
        
        return response()->json([
            'data' => $match->fresh()->load(['team1', 'team2', 'event']),
            'success' => true,
            'message' => 'Match updated successfully'
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// Admin Match Management - DELETE
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/matches/{matchId}', function (Request $request, $matchId) {
    try {
        $match = \App\Models\GameMatch::findOrFail($matchId);
        $matchInfo = $match->team1->name . ' vs ' . $match->team2->name;
        $match->delete();
        
        return response()->json([
            'success' => true,
            'message' => "Match '{$matchInfo}' deleted successfully"
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// Admin News Management
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/news', function () {
    $news = \App\Models\News::with('author')
        ->orderBy('created_at', 'desc')
        ->paginate(15);
    
    return response()->json([
        'data' => $news->items(),
        'meta' => [
            'current_page' => $news->currentPage(),
            'last_page' => $news->lastPage(),
            'per_page' => $news->perPage(),
            'total' => $news->total()
        ],
        'success' => true
    ]);
});

Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/news', function (Request $request) {
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'excerpt' => 'required|string|max:500',
        'content' => 'required|string',
        'category' => 'required|string|in:updates,tournaments,content,community,esports',
        'status' => 'required|string|in:draft,published,archived',
        'featured' => 'nullable|boolean',
        'tags' => 'nullable|array',
        'featured_image' => 'nullable|string'
    ]);
    
    $validated['author_id'] = $request->user()->id;
    $validated['published_at'] = $validated['status'] === 'published' ? now() : null;
    $validated['featured'] = $validated['featured'] ?? false;
    
    $news = \App\Models\News::create($validated);
    
    return response()->json([
        'data' => $news->load('author'),
        'success' => true,
        'message' => 'News article created successfully'
    ], 201);
});

Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/news/{newsId}', function (Request $request, $newsId) {
    try {
        $news = \App\Models\News::findOrFail($newsId);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string|max:500',
            'content' => 'required|string',
            'category' => 'required|string|in:updates,tournaments,content,community,esports',
            'status' => 'required|string|in:draft,published,archived',
            'featured' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'featured_image' => 'nullable|string'
        ]);
        
        $validated['featured'] = $validated['featured'] ?? false;
        if ($validated['status'] === 'published' && $news->status !== 'published') {
            $validated['published_at'] = now();
        }
        
        $news->update($validated);
        
        return response()->json([
            'data' => $news->fresh()->load('author'),
            'success' => true,
            'message' => 'News article updated successfully'
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/news/{newsId}', function (Request $request, $newsId) {
    $news = \App\Models\News::findOrFail($newsId);
    $newsTitle = $news->title;
    $news->delete();
    
    return response()->json([
        'success' => true,
        'message' => "News article '{$newsTitle}' deleted successfully"
    ]);
});

// Admin Event Management - GET individual event for editing
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/events/{eventId}', function (Request $request, $eventId) {
    try {
        $event = \App\Models\Event::findOrFail($eventId);
        
        return response()->json([
            'data' => $event,
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Event not found: ' . $e->getMessage()
        ], 404);
    }
});

// Admin Event Management - CREATE
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/events', function (Request $request) {
    try {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:International,Regional,Qualifier,Community',
            'status' => 'required|string|in:upcoming,live,completed',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'prize_pool' => 'nullable|string',
            'location' => 'nullable|string',
            'organizer' => 'nullable|string',
            'format' => 'nullable|string',
            'team_count' => 'nullable|integer|min:2',
            'registration_open' => 'nullable|boolean'
        ]);
        
        $validated['registration_open'] = $validated['registration_open'] ?? true;
        $validated['team_count'] = $validated['team_count'] ?? 32;
        
        $event = \App\Models\Event::create($validated);
        
        return response()->json([
            'data' => $event,
            'success' => true,
            'message' => 'Event created successfully'
        ], 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// Admin Event Management - UPDATE
Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/events/{eventId}', function (Request $request, $eventId) {
    try {
        $event = \App\Models\Event::findOrFail($eventId);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:International,Regional,Qualifier,Community',
            'status' => 'required|string|in:upcoming,live,completed',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'prize_pool' => 'nullable|string',
            'location' => 'nullable|string',
            'organizer' => 'nullable|string',
            'format' => 'nullable|string',
            'team_count' => 'nullable|integer|min:2',
            'registration_open' => 'nullable|boolean'
        ]);
        
        $event->update($validated);
        
        return response()->json([
            'data' => $event->fresh(),
            'success' => true,
            'message' => 'Event updated successfully'
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// Admin Event Management - DELETE (already exists but keeping here for completeness)
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/events/{eventId}', function (Request $request, $eventId) {
    try {
        $event = \App\Models\Event::findOrFail($eventId);
        $eventName = $event->name;
        
        // Check if force delete is requested
        $forceDelete = $request->boolean('force', false);
        
        // Check if event has matches
        $matchCount = \App\Models\GameMatch::where('event_id', $eventId)->count();
        if ($matchCount > 0 && !$forceDelete) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete event '{$eventName}' because it has {$matchCount} associated matches. Delete matches first or use force delete.",
                'can_force_delete' => true,
                'match_count' => $matchCount
            ], 422);
        }
        
        // If force delete, remove associated matches first
        if ($forceDelete && $matchCount > 0) {
            \App\Models\GameMatch::where('event_id', $eventId)->delete();
        }
        
        $event->delete();
        
        return response()->json([
            'success' => true,
            'message' => $forceDelete 
                ? "Event '{$eventName}' and {$matchCount} associated matches deleted successfully" 
                : "Event '{$eventName}' deleted successfully"
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// Admin Event Force Delete (Alternative endpoint for cascading delete)
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/events/{eventId}/force', function (Request $request, $eventId) {
    try {
        $event = \App\Models\Event::findOrFail($eventId);
        $eventName = $event->name;
        
        // Count and delete associated matches
        $matchCount = \App\Models\GameMatch::where('event_id', $eventId)->count();
        if ($matchCount > 0) {
            \App\Models\GameMatch::where('event_id', $eventId)->delete();
        }
        
        $event->delete();
        
        return response()->json([
            'success' => true,
            'message' => "Event '{$eventName}' and {$matchCount} associated matches deleted successfully"
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// FILE UPLOAD ROUTES
// ==========================================

// Team Logo Upload
Route::middleware(['auth:sanctum', 'role:admin'])->post('/upload/team/{teamId}/logo', function (Request $request, $teamId) {
    try {
        $team = \App\Models\Team::findOrFail($teamId);
        
        $request->validate([
            'logo' => 'required|file|mimes:jpeg,jpg,png,gif,svg|max:2048'
        ]);
        
        if (!$request->hasFile('logo')) {
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
        }
        
        $file = $request->file('logo');
        $fileName = 'team_' . $teamId . '_logo_' . time() . '.' . $file->getClientOriginalExtension();
        
        // Store file in public/uploads/teams directory
        $path = $file->storeAs('teams', $fileName, 'public');
        
        // Update team logo path
        $team->update(['logo' => '/storage/' . $path]);
        
        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'data' => [
                'logo_url' => '/storage/' . $path,
                'team' => $team->fresh()
            ]
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Upload error: ' . $e->getMessage()
        ], 500);
    }
});

// Player Avatar Upload
Route::middleware(['auth:sanctum', 'role:admin'])->post('/upload/player/{playerId}/avatar', function (Request $request, $playerId) {
    try {
        $player = \App\Models\Player::findOrFail($playerId);
        
        $request->validate([
            'avatar' => 'required|file|mimes:jpeg,jpg,png,gif|max:2048'
        ]);
        
        if (!$request->hasFile('avatar')) {
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
        }
        
        $file = $request->file('avatar');
        $fileName = 'player_' . $playerId . '_avatar_' . time() . '.' . $file->getClientOriginalExtension();
        
        // Store file in public/uploads/players directory
        $path = $file->storeAs('players', $fileName, 'public');
        
        // Update player avatar path
        $player->update(['avatar' => '/storage/' . $path]);
        
        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar_url' => '/storage/' . $path,
                'player' => $player->fresh()
            ]
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Upload error: ' . $e->getMessage()
        ], 500);
    }
});

// News Featured Image Upload
Route::middleware(['auth:sanctum', 'role:admin'])->post('/upload/news/{newsId}/featured-image', function (Request $request, $newsId) {
    try {
        $news = \App\Models\News::findOrFail($newsId);
        
        $request->validate([
            'featured_image' => 'required|file|mimes:jpeg,jpg,png,gif|max:4096'
        ]);
        
        if (!$request->hasFile('featured_image')) {
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
        }
        
        $file = $request->file('featured_image');
        $fileName = 'news_' . $newsId . '_featured_' . time() . '.' . $file->getClientOriginalExtension();
        
        // Store file in public/uploads/news directory
        $path = $file->storeAs('news', $fileName, 'public');
        
        // Update news featured image path
        $news->update(['featured_image' => '/storage/' . $path]);
        
        return response()->json([
            'success' => true,
            'message' => 'Featured image uploaded successfully',
            'data' => [
                'featured_image_url' => '/storage/' . $path,
                'news' => $news->fresh()
            ]
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Upload error: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// MODERATOR API SYSTEM - PRODUCTION READY
// ==========================================

// Simple moderator test route
Route::middleware(['auth:sanctum', 'role:moderator'])->get('/moderator/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Moderator access working!',
        'user' => auth()->user()->name
    ]);
});

// ==========================================
// FORUMS MANAGEMENT API - FOR ADMIN PANEL
// ==========================================

// List all forum threads for moderation
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/forums/threads', function (Request $request) {
    try {
        $query = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.category', 
                'ft.views', 'ft.replies', 'ft.pinned', 'ft.locked',
                'ft.created_at', 'ft.updated_at',
                'u.id as user_id', 'u.name as user_name', 'u.avatar as user_avatar'
            ]);

        // Filter by category if provided
        if ($request->category && $request->category !== 'all') {
            $query->where('ft.category', $request->category);
        }

        // Filter by status
        if ($request->status) {
            if ($request->status === 'pinned') {
                $query->where('ft.pinned', true);
            } elseif ($request->status === 'locked') {
                $query->where('ft.locked', true);
            }
        }

        $threads = $query->orderBy('ft.pinned', 'desc')
                         ->orderBy('ft.created_at', 'desc')
                         ->paginate(20);

        return response()->json([
            'data' => $threads->items(),
            'meta' => [
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
});

// Edit forum thread
Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/forums/threads/{threadId}', function (Request $request, $threadId) {
    try {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|in:general,strategies,team-recruitment,announcements,bugs,feedback,discussion,guides',
            'pinned' => 'nullable|boolean',
            'locked' => 'nullable|boolean'
        ]);

        $updated = DB::table('forum_threads')
            ->where('id', $threadId)
            ->update(array_filter($validated, function($value) {
                return !is_null($value);
            }));

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        // Get updated thread with user info
        $thread = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.category', 
                'ft.views', 'ft.replies', 'ft.pinned', 'ft.locked',
                'ft.created_at', 'ft.updated_at',
                'u.id as user_id', 'u.name as user_name', 'u.avatar as user_avatar'
            ])
            ->where('ft.id', $threadId)
            ->first();

        return response()->json([
            'data' => $thread,
            'success' => true,
            'message' => 'Thread updated successfully'
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating thread: ' . $e->getMessage()
        ], 500);
    }
});

// Delete forum thread
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/forums/threads/{threadId}', function (Request $request, $threadId) {
    try {
        // Get thread title before deletion
        $thread = DB::table('forum_threads')->where('id', $threadId)->first();
        
        if (!$thread) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        DB::table('forum_threads')->where('id', $threadId)->delete();

        return response()->json([
            'success' => true,
            'message' => "Thread '{$thread->title}' deleted successfully"
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error deleting thread: ' . $e->getMessage()
        ], 500);
    }
});

// Pin/Unpin thread
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/forums/threads/{threadId}/pin', function (Request $request, $threadId) {
    try {
        $pin = $request->boolean('pin', true);
        
        $updated = DB::table('forum_threads')
            ->where('id', $threadId)
            ->update(['pinned' => $pin]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $pin ? 'Thread pinned successfully' : 'Thread unpinned successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating thread pin status: ' . $e->getMessage()
        ], 500);
    }
});

// Lock/Unlock thread
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/forums/threads/{threadId}/lock', function (Request $request, $threadId) {
    try {
        $lock = $request->boolean('lock', true);
        
        $updated = DB::table('forum_threads')
            ->where('id', $threadId)
            ->update(['locked' => $lock]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $lock ? 'Thread locked successfully' : 'Thread unlocked successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating thread lock status: ' . $e->getMessage()
        ], 500);
    }
});

// List forum categories for management
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/forums/categories', function () {
    $categories = [
        ['id' => 'general', 'name' => 'General Discussion', 'description' => 'General Marvel Rivals discussion'],
        ['id' => 'strategies', 'name' => 'Strategies & Tactics', 'description' => 'Team compositions and tactics'],
        ['id' => 'team-recruitment', 'name' => 'Team Recruitment', 'description' => 'Looking for team/players'],
        ['id' => 'announcements', 'name' => 'Announcements', 'description' => 'Official tournament news'],
        ['id' => 'bugs', 'name' => 'Bugs & Issues', 'description' => 'Game issues and feedback'],
        ['id' => 'feedback', 'name' => 'Feedback', 'description' => 'Platform feedback'],
        ['id' => 'discussion', 'name' => 'Discussion', 'description' => 'General discussion'],
        ['id' => 'guides', 'name' => 'Guides', 'description' => 'Player guides and tutorials']
    ];

    return response()->json([
        'data' => $categories,
        'success' => true
    ]);
});

// ==========================================
// LIVE SCORING API - FOR MARVEL RIVALS MATCHES
// ==========================================

// Update match status (live, completed, etc.)
Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/matches/{matchId}/status', function (Request $request, $matchId) {
    try {
        $validated = $request->validate([
            'status' => 'required|string|in:upcoming,live,paused,completed,cancelled',
            'pause_reason' => 'nullable|string'
        ]);

        $updated = DB::table('matches')
            ->where('id', $matchId)
            ->update([
                'status' => $validated['status'],
                'updated_at' => now()
            ]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        // Add match event for status changes
        if ($validated['status'] === 'paused' && isset($validated['pause_reason'])) {
            DB::table('match_events')->insert([
                'match_id' => $matchId,
                'type' => 'pause',
                'description' => $validated['pause_reason'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Match status updated to {$validated['status']}"
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating match status: ' . $e->getMessage()
        ], 500);
    }
});

// Update overall match score
Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/matches/{matchId}/score', function (Request $request, $matchId) {
    try {
        $validated = $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0'
        ]);

        $updated = DB::table('matches')
            ->where('id', $matchId)
            ->update([
                'team1_score' => $validated['team1_score'],
                'team2_score' => $validated['team2_score'],
                'updated_at' => now()
            ]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Match score updated successfully'
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating match score: ' . $e->getMessage()
        ], 500);
    }
});

// Update individual map scores
Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/matches/{matchId}/maps/{mapId}', function (Request $request, $matchId, $mapId) {
    try {
        $validated = $request->validate([
            'team1_score' => 'nullable|integer|min:0',
            'team2_score' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:upcoming,live,completed',
            'map_name' => 'nullable|string',
            'game_mode' => 'nullable|string|in:Convoy,Domination,Control'
        ]);

        // For now, store map data in JSON format in the match record
        // In a real implementation, you'd have a separate maps table
        $match = DB::table('matches')->where('id', $matchId)->first();
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $maps = $match->maps ? json_decode($match->maps, true) : [];
        
        // Update or create map entry
        $maps[$mapId] = array_merge($maps[$mapId] ?? [], array_filter($validated, function($value) {
            return !is_null($value);
        }));

        DB::table('matches')
            ->where('id', $matchId)
            ->update([
                'maps' => json_encode($maps),
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Map score updated successfully',
            'data' => $maps[$mapId]
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating map score: ' . $e->getMessage()
        ], 500);
    }
});

// Add match events (pauses, notes)
Route::middleware(['auth:sanctum', 'role:admin|moderator'])->post('/matches/{matchId}/events', function (Request $request, $matchId) {
    try {
        $validated = $request->validate([
            'type' => 'required|string|in:pause,resume,note,technical_timeout,hero_substitution',
            'description' => 'required|string',
            'timestamp' => 'nullable|string'
        ]);

        // Verify match exists
        $match = DB::table('matches')->where('id', $matchId)->first();
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $eventId = DB::table('match_events')->insertGetId([
            'match_id' => $matchId,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'timestamp' => $validated['timestamp'] ?? now()->toTimeString(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $event = DB::table('match_events')->where('id', $eventId)->first();

        return response()->json([
            'data' => $event,
            'success' => true,
            'message' => 'Match event added successfully'
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error adding match event: ' . $e->getMessage()
        ], 500);
    }
});

// Update live broadcast data
Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/matches/{matchId}/live-data', function (Request $request, $matchId) {
    try {
        $validated = $request->validate([
            'viewers' => 'nullable|integer|min:0',
            'stream_url' => 'nullable|url',
            'stream_title' => 'nullable|string',
            'broadcaster' => 'nullable|string',
            'languages' => 'nullable|array'
        ]);

        $match = DB::table('matches')->where('id', $matchId)->first();
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $broadcast = $match->broadcast ? json_decode($match->broadcast, true) : [];
        
        // Update broadcast data
        $broadcast = array_merge($broadcast, array_filter($validated, function($value) {
            return !is_null($value);
        }));

        DB::table('matches')
            ->where('id', $matchId)
            ->update([
                'broadcast' => json_encode($broadcast),
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Live broadcast data updated successfully',
            'data' => $broadcast
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating live data: ' . $e->getMessage()
        ], 500);
    }
});
