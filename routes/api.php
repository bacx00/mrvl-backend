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
    $request->user()->currentAccessToken()->delete();
    return response()->json([
        'message' => 'Successfully logged out',
        'success' => true
    ]);
});

// Authenticated Routes  
// Route::get('/user', [AuthController::class, 'user'])->middleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
// Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

// Forum Write Operations
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
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'region' => 'required|string',
        'description' => 'nullable|string',
        'logo' => 'nullable|string',
    ]);
    
    $team = \App\Models\Team::create($validated);
    
    return response()->json([
        'data' => $team,
        'success' => true,
        'message' => 'Team created successfully'
    ], 201);
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
            'role' => 'required|string|in:Duelist,Tank,Support,Controller,Initiator',
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

Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/players/{player}', function (Request $request, $playerId) {
    $player = \App\Models\Player::findOrFail($playerId);
    
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'username' => 'required|string|unique:players,username,' . $playerId,
        'role' => 'required|string',
        'team_id' => 'nullable|exists:teams,id',
    ]);
    
    $player->update($validated);
    
    return response()->json([
        'data' => $player->fresh()->load('team'),
        'success' => true,
        'message' => 'Player updated successfully'
    ]);
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

Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/users/{user}', function (Request $request, $userId) {
    try {
        $user = \App\Models\User::findOrFail($userId);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $userId,
            'password' => 'nullable|min:8',
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
        
        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'] ?? $user->status ?? 'active',
            'avatar' => $validated['avatar'] ?? $user->avatar
        ];
        
        if (!empty($validated['password'])) {
            $updateData['password'] = $validated['password']; // Model handles hashing
        }
        
        $user->update($updateData);
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

Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/news/{newsId}', function (Request $request, $newsId) {
    $news = \App\Models\News::findOrFail($newsId);
    $newsTitle = $news->title;
    $news->delete();
    
    return response()->json([
        'success' => true,
        'message' => "News article '{$newsTitle}' deleted successfully"
    ]);
});

// Admin Event Management
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/events/{eventId}', function (Request $request, $eventId) {
    $event = \App\Models\Event::findOrFail($eventId);
    $eventName = $event->name;
    
    // Check if event has matches
    $matchCount = $event->matches()->count();
    if ($matchCount > 0) {
        return response()->json([
            'success' => false,
            'message' => "Cannot delete event '{$eventName}' because it has {$matchCount} associated matches. Delete matches first."
        ], 422);
    }
    
    $event->delete();
    
    return response()->json([
        'success' => true,
        'message' => "Event '{$eventName}' deleted successfully"
    ]);
});

// Original grouped routes (commented out for now)
/*
Route::middleware('auth:sanctum')->group(function () {
    // Admin Routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/stats', [AdminStatsController::class, 'index']);
        
        // Full CRUD for all resources
        Route::apiResource('/admin/teams', TeamController::class)->except(['index', 'show']);
        Route::apiResource('/admin/players', PlayerController::class)->except(['index', 'show']);
        Route::apiResource('/admin/matches', MatchController::class)->except(['index', 'show']);
        Route::apiResource('/admin/events', EventController::class)->except(['index', 'show']);
        
        // News Management
        Route::get('/admin/news', [NewsController::class, 'adminIndex']);
        Route::apiResource('/admin/news', NewsController::class)->except(['index']);
        
        // Image Upload Routes (Admin Only)
        Route::post('/upload/team/{team}/logo', [ImageUploadController::class, 'uploadTeamLogo']);
        Route::post('/upload/team/{team}/flag', [ImageUploadController::class, 'uploadTeamFlag']);
        Route::post('/upload/player/{player}/avatar', [ImageUploadController::class, 'uploadPlayerAvatar']);
        Route::post('/upload/news/{news}/featured-image', [ImageUploadController::class, 'uploadNewsFeaturedImage']);
        Route::post('/upload/news/{news}/gallery', [ImageUploadController::class, 'uploadNewsGalleryImages']);
        Route::delete('/upload/news/{news}/gallery', [ImageUploadController::class, 'removeNewsGalleryImage']);
    });
});
*/
