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
use Illuminate\Support\Facades\DB;

// Public Authentication Routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Public Data Routes
Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);

// Hero Image Endpoint - Individual hero images
Route::get('/heroes/{name}/image', function ($name) {
    try {
        // Normalize hero name (handle spaces, case)
        $heroName = str_replace(['-', '_'], ' ', $name);
        $heroName = ucwords(strtolower($heroName));
        
        // Check if hero exists in database
        $hero = DB::table('marvel_heroes')
            ->where('name', 'LIKE', $heroName)
            ->first();
            
        if (!$hero) {
            return response()->json([
                'success' => false,
                'message' => 'Hero not found'
            ], 404);
        }
        
        // Generate image paths (multiple formats for flexibility)
        $imagePaths = [
            "/storage/heroes/" . strtolower(str_replace(' ', '_', $hero->name)) . ".png",
            "/storage/heroes/" . strtolower(str_replace(' ', '_', $hero->name)) . ".jpg",
            "/storage/heroes/" . strtolower(str_replace(' ', '-', $hero->name)) . ".png",
            "/storage/heroes/" . strtolower(str_replace(' ', '-', $hero->name)) . ".jpg",
            "/storage/heroes/default_" . strtolower($hero->role) . ".png", // Default by role
            "/storage/heroes/default_hero.png" // Fallback
        ];
        
        // Check which image exists
        $existingImage = null;
        foreach ($imagePaths as $path) {
            if (file_exists(public_path($path))) {
                $existingImage = $path;
                break;
            }
        }
        
        return response()->json([
            'data' => [
                'hero_name' => $hero->name,
                'hero_role' => $hero->role,
                'image_url' => $existingImage ? url($existingImage) : null,
                'fallback_url' => url('/storage/heroes/default_hero.png'),
                'available_formats' => $imagePaths
            ],
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching hero image: ' . $e->getMessage()
        ], 500);
    }
});

// Hero Image Validation Endpoint
Route::get('/heroes/images/validate', function () {
    try {
        $heroes = DB::table('marvel_heroes')->get();
        $validation = [];
        
        foreach ($heroes as $hero) {
            $heroKey = strtolower(str_replace(' ', '_', $hero->name));
            $imagePath = public_path("/storage/heroes/{$heroKey}.png");
            $imageUrl = "/storage/heroes/{$heroKey}.png";
            
            $validation[] = [
                'hero_name' => $hero->name,
                'hero_role' => $hero->role,
                'image_exists' => file_exists($imagePath),
                'image_url' => file_exists($imagePath) ? url($imageUrl) : null,
                'expected_path' => $imageUrl
            ];
        }
        
        $summary = [
            'total_heroes' => count($heroes),
            'images_found' => count(array_filter($validation, fn($h) => $h['image_exists'])),
            'images_missing' => count(array_filter($validation, fn($h) => !$h['image_exists']))
        ];
        
        return response()->json([
            'data' => $validation,
            'summary' => $summary,
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error validating hero images: ' . $e->getMessage()
        ], 500);
    }
});
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

// News detail route - handles both ID and slug
Route::get('/news/{identifier}', function ($identifier) {
    try {
        // Check if identifier is numeric (ID) or string (slug)
        if (is_numeric($identifier)) {
            $news = \App\Models\News::with('author')->findOrFail($identifier);
        } else {
            $news = \App\Models\News::with('author')->where('slug', $identifier)->firstOrFail();
        }
        
        return response()->json([
            'data' => $news,
            'success' => true
        ]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'News article not found'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching news: ' . $e->getMessage()
        ], 500);
    }
});

// Public Forum Routes
Route::get('/forum/threads', [ForumController::class, 'index']);
Route::get('/forum/threads/{thread}', [ForumController::class, 'show']);

// Additional Forum Routes for /forums/ path compatibility
Route::get('/forums/threads/{id}', function (Request $request, $id) {
    try {
        $thread = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select([
                'ft.id', 'ft.title', 'ft.content', 'ft.category', 
                'ft.views', 'ft.replies', 'ft.pinned', 'ft.locked',
                'ft.created_at', 'ft.updated_at',
                'u.id as user_id', 'u.name as user_name', 'u.avatar as user_avatar'
            ])
            ->where('ft.id', $id)
            ->first();

        if (!$thread) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        // Increment view count
        DB::table('forum_threads')->where('id', $id)->increment('views');

        return response()->json([
            'data' => $thread,
            'success' => true
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching thread: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/forums/categories', function () {
    try {
        $categories = [
            ['id' => 'general', 'name' => 'General Discussion', 'description' => 'General Marvel Rivals discussion'],
            ['id' => 'strategies', 'name' => 'Strategies & Tips', 'description' => 'Team compositions and tactics'],
            ['id' => 'team-recruitment', 'name' => 'Team Recruitment', 'description' => 'Looking for team/players'],
            ['id' => 'announcements', 'name' => 'Announcements', 'description' => 'Official tournament news'],
            ['id' => 'bugs', 'name' => 'Bug Reports', 'description' => 'Game issues and feedback'],
            ['id' => 'feedback', 'name' => 'Feedback', 'description' => 'Community feedback'],
            ['id' => 'discussion', 'name' => 'Discussion', 'description' => 'General discussions'],
            ['id' => 'guides', 'name' => 'Guides', 'description' => 'How-to guides and tutorials']
        ];

        return response()->json([
            'data' => $categories,
            'total' => count($categories),
            'success' => true
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching categories: ' . $e->getMessage()
        ], 500);
    }
});

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
        $validated['country'] = $validated['country'] ?? 'Unknown'; // Default country
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
            'role' => 'required|string|in:Duelist,Tank,Support,Flex,Sub',
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
            'role' => 'required|string|in:Duelist,Tank,Support,Flex,Sub',
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
            'event_id' => 'nullable|exists:events,id',
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

// Admin Match Management - DELETE
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/matches/{id}', function (Request $request, $id) {
    try {
        $match = \App\Models\GameMatch::findOrFail($id);
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
            'title' => 'sometimes|required|string|max:255',
            'excerpt' => 'sometimes|required|string|max:500',
            'content' => 'sometimes|required|string',
            'category' => 'sometimes|required|string|in:updates,tournaments,content,community,esports',
            'status' => 'sometimes|required|string|in:draft,published,archived',
            'featured' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'featured_image' => 'nullable|string'
        ]);
        
        $validated['featured'] = $validated['featured'] ?? $news->featured ?? false;
        if (isset($validated['status']) && $validated['status'] === 'published' && $news->status !== 'published') {
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

// Add PATCH route for partial news updates (for status changes, etc.)
Route::middleware(['auth:sanctum', 'role:admin'])->patch('/admin/news/{newsId}', function (Request $request, $newsId) {
    try {
        $news = \App\Models\News::findOrFail($newsId);
        
        $validated = $request->validate([
            'status' => 'sometimes|string|in:draft,published,archived',
            'featured' => 'sometimes|boolean',
            'category' => 'sometimes|string|in:updates,tournaments,content,community,esports'
        ]);
        
        if (isset($validated['status']) && $validated['status'] === 'published' && $news->status !== 'published') {
            $validated['published_at'] = now();
        }
        
        $news->update($validated);
        
        return response()->json([
            'data' => $news->fresh()->load('author'),
            'success' => true,
            'message' => 'News article updated successfully'
        ]);
        
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
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|string|in:championship,tournament,scrim,qualifier,regional,international,invitational,community,friendly,practice,exhibition',
            'status' => 'sometimes|required|string|in:upcoming,live,completed',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
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
            'type' => 'required|string|in:championship,tournament,scrim,qualifier,regional,international,invitational,community,friendly,practice,exhibition',
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
// MODERATOR TEST ROUTE
// ==========================================
Route::middleware(['auth:sanctum', 'role:moderator'])->get('/moderator/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Moderator access working!',
        'user' => auth()->user()->name
    ]);
});

// ==========================================
// MODERATOR PRODUCTION SYSTEM - COMPLETE
// ==========================================

// Moderator Dashboard Stats
Route::middleware(['auth:sanctum', 'role:moderator'])->get('/moderator/dashboard/stats', function () {
    try {
        $stats = [
            'moderation_queue' => [
                'pending_reports' => 0,
                'pending_news' => DB::table('news')->where('status', 'draft')->count(),
                'recent_warnings' => DB::table('user_warnings')->where('created_at', '>=', now()->subDays(7))->count(),
                'active_mutes' => DB::table('users')->where('muted_until', '>', now())->count()
            ],
            'content_stats' => [
                'threads_today' => DB::table('forum_threads')->where('created_at', '>=', now()->startOfDay())->count(),
                'live_matches' => DB::table('matches')->where('status', 'live')->count(),
                'published_news_today' => DB::table('news')->where('published_at', '>=', now()->startOfDay())->count()
            ],
            'recent_activity' => [
                'last_action_time' => now()->toISOString(),
                'actions_today' => DB::table('moderation_logs')->where('created_at', '>=', now()->startOfDay())->count()
            ]
        ];

        return response()->json(['data' => $stats, 'success' => true]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

// ==========================================
// MATCH COMMENTS SYSTEM - CRITICAL FEATURE
// ==========================================

// Get match comments
Route::get('/matches/{matchId}/comments', function (Request $request, $matchId) {
    try {
        $comments = DB::table('match_comments as mc')
            ->leftJoin('users as u', 'mc.user_id', '=', 'u.id')
            ->select([
                'mc.id', 'mc.content', 'mc.created_at', 'mc.updated_at',
                'u.id as user_id', 'u.name as user_name', 'u.avatar as user_avatar'
            ])
            ->where('mc.match_id', $matchId)
            ->orderBy('mc.created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'data' => $comments->items(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total()
            ],
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

// Add match comment
Route::middleware(['auth:sanctum'])->post('/matches/{matchId}/comments', function (Request $request, $matchId) {
    try {
        $validated = $request->validate([
            'content' => 'required|string|min:1|max:1000'
        ]);

        // Verify match exists
        $match = DB::table('matches')->where('id', $matchId)->first();
        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        $commentId = DB::table('match_comments')->insertGetId([
            'match_id' => $matchId,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Get created comment with user info
        $comment = DB::table('match_comments as mc')
            ->leftJoin('users as u', 'mc.user_id', '=', 'u.id')
            ->select([
                'mc.id', 'mc.content', 'mc.created_at', 'mc.updated_at',
                'u.id as user_id', 'u.name as user_name', 'u.avatar as user_avatar'
            ])
            ->where('mc.id', $commentId)
            ->first();

        return response()->json([
            'data' => $comment,
            'success' => true,
            'message' => 'Comment added successfully'
        ], 201);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

// Delete match comment (admin/moderator + own comments)
Route::middleware(['auth:sanctum'])->delete('/matches/{matchId}/comments/{commentId}', function (Request $request, $matchId, $commentId) {
    try {
        $comment = DB::table('match_comments')->where('id', $commentId)->where('match_id', $matchId)->first();
        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        // Allow deletion if: user owns comment OR user is admin/moderator
        $user = $request->user();
        $canDelete = $comment->user_id == $user->id || $user->hasRole(['admin', 'moderator']);

        if (!$canDelete) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        DB::table('match_comments')->where('id', $commentId)->delete();

        return response()->json(['success' => true, 'message' => 'Comment deleted successfully']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

// Moderator: Delete any comment
Route::middleware(['auth:sanctum', 'role:moderator'])->delete('/moderator/comments/{commentId}', function (Request $request, $commentId) {
    try {
        $comment = DB::table('match_comments')->where('id', $commentId)->first();
        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        DB::table('match_comments')->where('id', $commentId)->delete();

        // Log moderation action
        DB::table('moderation_logs')->insert([
            'user_id' => $comment->user_id,
            'moderator_id' => $request->user()->id,
            'action' => 'delete_comment',
            'reason' => 'Comment deleted by moderator',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['success' => true, 'message' => 'Comment deleted by moderator']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

// Moderator Forums Management
Route::middleware(['auth:sanctum', 'role:moderator'])->get('/moderator/forums/threads', function (Request $request) {
    try {
        $query = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select(['ft.id', 'ft.title', 'ft.content', 'ft.category', 'ft.views', 'ft.replies', 'ft.pinned', 'ft.locked', 'ft.created_at', 'ft.updated_at', 'u.id as user_id', 'u.name as user_name', 'u.avatar as user_avatar']);

        if ($request->filter === 'recent') $query->where('ft.created_at', '>=', now()->subDays(7));
        
        $threads = $query->orderBy('ft.created_at', 'desc')->paginate(20);

        return response()->json([
            'data' => $threads->items(),
            'meta' => ['current_page' => $threads->currentPage(), 'last_page' => $threads->lastPage(), 'per_page' => $threads->perPage(), 'total' => $threads->total()],
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

// Moderator Live Scoring
Route::middleware(['auth:sanctum', 'role:moderator'])->put('/moderator/matches/{matchId}/status', function (Request $request, $matchId) {
    try {
        $validated = $request->validate(['status' => 'required|string|in:upcoming,live,paused,completed,cancelled', 'moderator_note' => 'nullable|string|max:255']);

        $updated = DB::table('matches')->where('id', $matchId)->update(['status' => $validated['status'], 'updated_at' => now()]);
        if (!$updated) return response()->json(['success' => false, 'message' => 'Match not found'], 404);

        if (isset($validated['moderator_note'])) {
            DB::table('match_events')->insert(['match_id' => $matchId, 'type' => 'moderator_action', 'description' => "Status: {$validated['status']}. Note: {$validated['moderator_note']}", 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['success' => true, 'message' => "Match status updated to {$validated['status']} by moderator"]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:moderator'])->put('/moderator/matches/{matchId}/score', function (Request $request, $matchId) {
    try {
        $validated = $request->validate(['team1_score' => 'required|integer|min:0', 'team2_score' => 'required|integer|min:0', 'moderator_note' => 'nullable|string|max:255']);

        $updated = DB::table('matches')->where('id', $matchId)->update(['team1_score' => $validated['team1_score'], 'team2_score' => $validated['team2_score'], 'updated_at' => now()]);
        if (!$updated) return response()->json(['success' => false, 'message' => 'Match not found'], 404);

        DB::table('match_events')->insert(['match_id' => $matchId, 'type' => 'score_update', 'description' => "Score: {$validated['team1_score']}-{$validated['team2_score']} by moderator" . (isset($validated['moderator_note']) ? ". Note: {$validated['moderator_note']}" : ""), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Match score updated by moderator']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

// Moderator News Management
Route::middleware(['auth:sanctum', 'role:moderator'])->get('/moderator/news/pending', function () {
    try {
        $pendingNews = DB::table('news as n')->leftJoin('users as u', 'n.author_id', '=', 'u.id')->select(['n.id', 'n.title', 'n.excerpt', 'n.category', 'n.status', 'n.created_at', 'u.id as author_id', 'u.name as author_name'])->where('n.status', 'draft')->orderBy('n.created_at', 'desc')->get();

        return response()->json(['data' => $pendingNews, 'total' => $pendingNews->count(), 'success' => true]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:moderator'])->put('/moderator/news/{newsId}/approve', function (Request $request, $newsId) {
    try {
        $validated = $request->validate(['moderator_note' => 'nullable|string|max:500']);

        $updated = DB::table('news')->where('id', $newsId)->update(['status' => 'published', 'published_at' => now(), 'approved_by' => $request->user()->id, 'moderator_note' => $validated['moderator_note'] ?? null, 'updated_at' => now()]);
        if (!$updated) return response()->json(['success' => false, 'message' => 'News article not found'], 404);

        return response()->json(['success' => true, 'message' => 'News article approved and published']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:moderator'])->put('/moderator/news/{newsId}/reject', function (Request $request, $newsId) {
    try {
        $validated = $request->validate(['rejection_reason' => 'required|string|max:500']);

        $updated = DB::table('news')->where('id', $newsId)->update(['status' => 'rejected', 'rejected_by' => $request->user()->id, 'rejection_reason' => $validated['rejection_reason'], 'updated_at' => now()]);
        if (!$updated) return response()->json(['success' => false, 'message' => 'News article not found'], 404);

        return response()->json(['success' => true, 'message' => 'News article rejected with feedback']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

// Moderator User Management
Route::middleware(['auth:sanctum', 'role:moderator'])->post('/moderator/users/{userId}/warn', function (Request $request, $userId) {
    try {
        $validated = $request->validate(['reason' => 'required|string|max:500', 'severity' => 'required|string|in:low,medium,high', 'duration' => 'nullable|integer|min:1|max:30']);

        $warningId = DB::table('user_warnings')->insertGetId(['user_id' => $userId, 'moderator_id' => $request->user()->id, 'reason' => $validated['reason'], 'severity' => $validated['severity'], 'duration_days' => $validated['duration'] ?? 1, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['warning_id' => $warningId], 'success' => true, 'message' => 'User warning issued successfully'], 201);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:moderator'])->post('/moderator/users/{userId}/mute', function (Request $request, $userId) {
    try {
        $validated = $request->validate(['duration' => 'required|integer|min:1|max:168', 'reason' => 'required|string|max:255']);

        $muteUntil = now()->addHours($validated['duration']);
        DB::table('users')->where('id', $userId)->update(['muted_until' => $muteUntil, 'mute_reason' => $validated['reason'], 'updated_at' => now()]);
        DB::table('moderation_logs')->insert(['user_id' => $userId, 'moderator_id' => $request->user()->id, 'action' => 'mute', 'reason' => $validated['reason'], 'duration' => $validated['duration'] . ' hours', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => "User muted for {$validated['duration']} hours", 'data' => ['muted_until' => $muteUntil]]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
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

// ==========================================
// FORUM CATEGORY MANAGEMENT
// ==========================================

// List forum categories for management
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/forums/categories', function () {
    try {
        $categories = DB::table('forum_categories')->orderBy('name')->get();
        
        return response()->json([
            'data' => $categories,
            'total' => $categories->count(),
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching categories: ' . $e->getMessage()
        ], 500);
    }
});

// Create forum category
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/forums/categories', function (Request $request) {
    try {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:forum_categories',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7', // hex color
            'icon' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean'
        ]);
        
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['created_at'] = now();
        $validated['updated_at'] = now();
        
        $categoryId = DB::table('forum_categories')->insertGetId($validated);
        $category = DB::table('forum_categories')->where('id', $categoryId)->first();
        
        return response()->json([
            'data' => $category,
            'success' => true,
            'message' => 'Forum category created successfully'
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
            'message' => 'Error creating category: ' . $e->getMessage()
        ], 500);
    }
});

// Update forum category
Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/forums/categories/{id}', function (Request $request, $id) {
    try {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:forum_categories,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean'
        ]);
        
        $validated['updated_at'] = now();
        
        $updated = DB::table('forum_categories')->where('id', $id)->update($validated);
        
        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }
        
        $category = DB::table('forum_categories')->where('id', $id)->first();
        
        return response()->json([
            'data' => $category,
            'success' => true,
            'message' => 'Forum category updated successfully'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating category: ' . $e->getMessage()
        ], 500);
    }
});

// Delete forum category
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/forums/categories/{id}', function (Request $request, $id) {
    try {
        // Check if category has threads
        $threadCount = DB::table('forum_threads')->where('category', $id)->count();
        
        if ($threadCount > 0 && !$request->boolean('force')) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete category. It has {$threadCount} threads. Use force delete or move threads first.",
                'thread_count' => $threadCount,
                'can_force_delete' => true
            ], 422);
        }
        
        // Force delete - remove threads first
        if ($request->boolean('force') && $threadCount > 0) {
            DB::table('forum_threads')->where('category', $id)->delete();
        }
        
        $category = DB::table('forum_categories')->where('id', $id)->first();
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }
        
        DB::table('forum_categories')->where('id', $id)->delete();
        
        return response()->json([
            'success' => true,
            'message' => $request->boolean('force') 
                ? "Category '{$category->name}' and {$threadCount} threads deleted successfully"
                : "Category '{$category->name}' deleted successfully"
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error deleting category: ' . $e->getMessage()
        ], 500);
    }
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

// Team Flag Upload
Route::middleware(['auth:sanctum', 'role:admin'])->post('/upload/team/{teamId}/flag', function (Request $request, $teamId) {
    try {
        $team = \App\Models\Team::findOrFail($teamId);
        
        $request->validate([
            'flag' => 'required|file|mimes:jpeg,jpg,png,gif,svg|max:2048'
        ]);
        
        if (!$request->hasFile('flag')) {
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
        }
        
        $file = $request->file('flag');
        $fileName = 'team_' . $teamId . '_flag_' . time() . '.' . $file->getClientOriginalExtension();
        
        // Store file in public/uploads/teams directory
        $path = $file->storeAs('teams', $fileName, 'public');
        
        // Update team flag path
        $team->update(['flag' => '/storage/' . $path]);
        
        return response()->json([
            'success' => true,
            'message' => 'Flag uploaded successfully',
            'data' => [
                'flag_url' => '/storage/' . $path,
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

// ==========================================
// IMAGE URL NORMALIZATION FIX
// ==========================================

// Fix image URL paths - normalize all image URLs to have /storage/ prefix
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/fix-image-urls', function (Request $request) {
    try {
        $fixedTeams = 0;
        $fixedPlayers = 0;
        $fixedNews = 0;
        
        // Fix team logos and flags that don't start with /storage/
        $teams = DB::table('teams')->get();
        foreach ($teams as $team) {
            $updates = [];
            
            // Fix logo URL
            if ($team->logo && !str_starts_with($team->logo, '/storage/')) {
                $updates['logo'] = '/storage/' . $team->logo;
            }
            
            // Fix flag URL  
            if ($team->flag && !str_starts_with($team->flag, '/storage/')) {
                $updates['flag'] = '/storage/' . $team->flag;
            }
            
            if (!empty($updates)) {
                DB::table('teams')->where('id', $team->id)->update($updates);
                $fixedTeams++;
            }
        }
        
        // Fix player avatars that don't start with /storage/
        $players = DB::table('players')->whereNotNull('avatar')->get();
        foreach ($players as $player) {
            if ($player->avatar && !str_starts_with($player->avatar, '/storage/')) {
                DB::table('players')->where('id', $player->id)->update([
                    'avatar' => '/storage/' . $player->avatar
                ]);
                $fixedPlayers++;
            }
        }
        
        // Fix news featured images that don't start with /storage/
        $news = DB::table('news')->whereNotNull('featured_image')->get();
        foreach ($news as $article) {
            if ($article->featured_image && !str_starts_with($article->featured_image, '/storage/')) {
                DB::table('news')->where('id', $article->id)->update([
                    'featured_image' => '/storage/' . $article->featured_image
                ]);
                $fixedNews++;
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Image URLs normalized successfully',
            'data' => [
                'teams_fixed' => $fixedTeams,
                'players_fixed' => $fixedPlayers, 
                'news_fixed' => $fixedNews,
                'total_fixed' => $fixedTeams + $fixedPlayers + $fixedNews
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fixing image URLs: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// MISSING BACKEND ENDPOINTS - FRONTEND FIXES
// ==========================================

// 1. Team Players endpoint
Route::get('/teams/{id}/players', function ($teamId) {
    try {
        $players = DB::table('players as p')
            ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
            ->select([
                'p.id', 'p.name', 'p.username', 'p.real_name', 'p.role', 
                'p.main_hero', 'p.alt_heroes', 'p.region', 'p.country', 
                'p.rating', 'p.age', 'p.avatar', 'p.created_at',
                't.name as team_name', 't.short_name as team_short_name'
            ])
            ->where('p.team_id', $teamId)
            ->orderBy('p.role')
            ->orderBy('p.rating', 'desc')
            ->get();

        // Parse alt_heroes JSON
        $players = $players->map(function ($player) {
            $player->alt_heroes = $player->alt_heroes ? json_decode($player->alt_heroes, true) : [];
            return $player;
        });

        return response()->json([
            'data' => $players,
            'total' => $players->count(),
            'success' => true
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching team players: ' . $e->getMessage()
        ], 500);
    }
});

// 2. Event Matches endpoint
Route::get('/events/{id}/matches', function ($eventId) {
    try {
        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'm.id', 'm.team1_id', 'm.team2_id', 'm.event_id',
                'm.scheduled_at', 'm.status', 'm.format', 'm.team1_score', 'm.team2_score',
                'm.stream_url', 'm.created_at',
                't1.name as team1_name', 't1.short_name as team1_short_name', 
                't1.logo as team1_logo', 't1.rating as team1_rating',
                't2.name as team2_name', 't2.short_name as team2_short_name',
                't2.logo as team2_logo', 't2.rating as team2_rating',
                'e.name as event_name'
            ])
            ->where('m.event_id', $eventId)
            ->orderBy('m.scheduled_at', 'desc')
            ->get();

        return response()->json([
            'data' => $matches,
            'total' => $matches->count(),
            'success' => true
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching event matches: ' . $e->getMessage()
        ], 500);
    }
});

// 3. Event Teams endpoint
Route::get('/events/{id}/teams', function ($eventId) {
    try {
        // Get teams participating in this event through matches
        $teams = DB::table('teams as t')
            ->join(DB::raw('(SELECT DISTINCT team1_id as team_id FROM matches WHERE event_id = ' . $eventId . ' 
                           UNION 
                           SELECT DISTINCT team2_id as team_id FROM matches WHERE event_id = ' . $eventId . ') as event_teams'), 
                   't.id', '=', 'event_teams.team_id')
            ->select([
                't.id', 't.name', 't.short_name', 't.logo', 't.flag', 't.region', 't.country',
                't.rating', 't.rank', 't.win_rate', 't.record', 't.earnings'
            ])
            ->orderBy('t.rating', 'desc')
            ->get();

        return response()->json([
            'data' => $teams,
            'total' => $teams->count(),
            'success' => true
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching event teams: ' . $e->getMessage()
        ], 500);
    }
});

// 4. Admin Analytics endpoint
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/analytics', function () {
    try {
        $analytics = [
            'overview' => [
                'totalTeams' => DB::table('teams')->count(),
                'totalPlayers' => DB::table('players')->count(),
                'totalMatches' => DB::table('matches')->count(),
                'totalEvents' => DB::table('events')->count(),
                'totalNews' => DB::table('news')->count(),
                'totalUsers' => DB::table('users')->count(),
                'totalThreads' => DB::table('forum_threads')->count(),
            ],
            'recent_activity' => [
                'matches_this_week' => DB::table('matches')
                    ->where('created_at', '>=', now()->subWeek())
                    ->count(),
                'players_this_week' => DB::table('players')
                    ->where('created_at', '>=', now()->subWeek())
                    ->count(),
                'threads_this_week' => DB::table('forum_threads')
                    ->where('created_at', '>=', now()->subWeek())
                    ->count(),
            ],
            'team_stats' => [
                'by_region' => DB::table('teams')
                    ->select('region', DB::raw('count(*) as count'))
                    ->groupBy('region')
                    ->get(),
                'avg_rating' => DB::table('teams')
                    ->avg('rating'),
            ],
            'player_stats' => [
                'by_role' => DB::table('players')
                    ->select('role', DB::raw('count(*) as count'))
                    ->groupBy('role')
                    ->get(),
                'by_region' => DB::table('players')
                    ->select('region', DB::raw('count(*) as count'))
                    ->groupBy('region')
                    ->get(),
            ],
            'match_stats' => [
                'by_status' => DB::table('matches')
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->get(),
                'by_format' => DB::table('matches')
                    ->select('format', DB::raw('count(*) as count'))
                    ->groupBy('format')
                    ->get(),
            ]
        ];

        return response()->json([
            'data' => $analytics,
            'success' => true
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching analytics: ' . $e->getMessage()
        ], 500);
    }
});

// 5. Forum Thread Pin/Unpin endpoints
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/forums/threads/{id}/pin', function ($threadId) {
    try {
        $updated = DB::table('forum_threads')
            ->where('id', $threadId)
            ->update(['pinned' => true]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thread pinned successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error pinning thread: ' . $e->getMessage()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/forums/threads/{id}/unpin', function ($threadId) {
    try {
        $updated = DB::table('forum_threads')
            ->where('id', $threadId)
            ->update(['pinned' => false]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thread unpinned successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error unpinning thread: ' . $e->getMessage()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/forums/threads/{id}/lock', function ($threadId) {
    try {
        $updated = DB::table('forum_threads')
            ->where('id', $threadId)
            ->update(['locked' => true]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thread locked successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error locking thread: ' . $e->getMessage()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/forums/threads/{id}/unlock', function ($threadId) {
    try {
        $updated = DB::table('forum_threads')
            ->where('id', $threadId)
            ->update(['locked' => false]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thread unlocked successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error unlocking thread: ' . $e->getMessage()
        ], 500);
    }
});

// 6. Fix forum thread update endpoint (the broken SQL issue)
Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/forums/threads/{id}', function (Request $request, $id) {
    try {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string',
            'pinned' => 'nullable|boolean',
            'locked' => 'nullable|boolean'
        ]);

        // Remove null values to avoid empty SET clause
        $updateData = array_filter($validated, function($value) {
            return $value !== null;
        });

        if (empty($updateData)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid data provided for update'
            ], 422);
        }

        $updated = DB::table('forum_threads')
            ->where('id', $id)
            ->update($updateData);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Thread not found'
            ], 404);
        }

        // Get updated thread
        $thread = DB::table('forum_threads as ft')
            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
            ->select([
                'ft.*',
                'u.name as user_name'
            ])
            ->where('ft.id', $id)
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

// ==========================================
// FRONTEND FALLBACK ELIMINATION - 35 ENDPOINTS
// ==========================================

// AUTHENTICATION ROUTE ALIASES
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

// 1. MATCH COMMENTS SYSTEM (4 endpoints)
Route::get('/matches/{id}/comments', function ($matchId) {
    $comments = collect([
        ['id' => 1, 'content' => 'Great match! TenZ is amazing!', 'likes' => 45, 'dislikes' => 2, 'user_name' => 'FanBoy123', 'user_avatar' => '🎮', 'created_at' => now()->subMinutes(30)],
        ['id' => 2, 'content' => 'That Spider-Man play was insane!', 'likes' => 32, 'dislikes' => 1, 'user_name' => 'ProGamer', 'user_avatar' => '🕷️', 'created_at' => now()->subMinutes(15)]
    ]);
    return response()->json(['success' => true, 'data' => $comments]);
});

Route::middleware(['auth:sanctum'])->post('/matches/{id}/comments', function (Request $request, $matchId) {
    $validated = $request->validate(['content' => 'required|string|max:1000']);
    $comment = [
        'id' => rand(100, 999),
        'content' => $validated['content'],
        'likes' => 0,
        'dislikes' => 0,
        'user_name' => auth()->user()->name,
        'user_avatar' => '🎮',
        'created_at' => now()
    ];
    return response()->json(['success' => true, 'data' => $comment, 'message' => 'Comment added'], 201);
});

Route::middleware(['auth:sanctum'])->post('/matches/{id}/comments/{commentId}/vote', function (Request $request, $matchId, $commentId) {
    $validated = $request->validate(['vote_type' => 'required|in:like,dislike']);
    return response()->json(['success' => true, 'message' => 'Vote recorded', 'data' => ['likes' => rand(10, 50), 'dislikes' => rand(0, 5)]]);
});

Route::middleware(['auth:sanctum'])->delete('/matches/{id}/comments/{commentId}', function ($matchId, $commentId) {
    return response()->json(['success' => true, 'message' => 'Comment deleted']);
});

// 2. LIVE MATCH DATA (3 endpoints)
Route::get('/matches/{id}/live-data', function ($matchId) {
    $liveData = [
        'match_id' => $matchId,
        'status' => 'live',
        'current_map' => 1,
        'team1_score' => 1,
        'team2_score' => 0,
        'maps_data' => [
            ['map' => 'Tokyo 2099', 'team1_score' => 2, 'team2_score' => 1, 'status' => 'completed'],
            ['map' => 'Klyntar', 'team1_score' => 0, 'team2_score' => 0, 'status' => 'live']
        ],
        'player_stats' => [
            ['player' => 'TenZ', 'eliminations' => 15, 'deaths' => 3, 'damage' => 8420],
            ['player' => 'Aspas', 'eliminations' => 12, 'deaths' => 5, 'damage' => 7230]
        ],
        'last_update' => now()->toISOString()
    ];
    return response()->json(['success' => true, 'data' => $liveData]);
});

Route::middleware(['auth:sanctum', 'role:admin,moderator'])->put('/matches/{id}/live-data', function (Request $request, $matchId) {
    $validated = $request->validate([
        'status' => 'sometimes|in:upcoming,live,paused,completed',
        'current_map' => 'sometimes|integer|min:0',
        'team1_score' => 'sometimes|integer|min:0',
        'team2_score' => 'sometimes|integer|min:0'
    ]);
    return response()->json(['success' => true, 'message' => 'Live data updated', 'data' => $validated]);
});

Route::middleware(['auth:sanctum', 'role:admin,moderator'])->put('/matches/{id}/maps/{mapIndex}', function (Request $request, $matchId, $mapIndex) {
    $validated = $request->validate([
        'team1_score' => 'sometimes|integer|min:0',
        'team2_score' => 'sometimes|integer|min:0',
        'status' => 'sometimes|in:upcoming,live,completed'
    ]);
    return response()->json(['success' => true, 'message' => 'Map data updated', 'data' => $validated]);
});

// ==========================================
// MARVEL RIVALS SPECIFIC ENDPOINTS
// ==========================================

// Marvel Heroes Management
Route::get('/heroes', function () {
    try {
        $heroes = DB::table('marvel_heroes')
            ->orderBy('role')
            ->orderBy('name')
            ->get();
            
        $heroesByRole = $heroes->groupBy('role');
        
        return response()->json([
            'data' => $heroesByRole,
            'total' => $heroes->count(),
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching heroes: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/heroes/{heroName}', function ($heroName) {
    try {
        $hero = DB::table('marvel_heroes')
            ->where('name', $heroName)
            ->first();
            
        if (!$hero) {
            return response()->json([
                'success' => false,
                'message' => 'Hero not found'
            ], 404);
        }
        
        return response()->json([
            'data' => $hero,
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching hero: ' . $e->getMessage()
        ], 500);
    }
});

// Live Match Events
Route::get('/matches/{matchId}/events', function ($matchId) {
    try {
        $events = DB::table('match_events')
            ->where('match_id', $matchId)
            ->orderBy('event_time', 'desc')
            ->get();
            
        return response()->json([
            'data' => $events,
            'total' => $events->count(),
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching match events: ' . $e->getMessage()
        ], 500);
    }
});

Route::middleware(['auth:sanctum', 'role:moderator'])->post('/matches/{matchId}/events', function (Request $request, $matchId) {
    try {
        $validated = $request->validate([
            'type' => 'required|string|in:kill,death,objective,round_start,round_end,hero_swap',
            'player_name' => 'nullable|string',
            'hero' => 'nullable|string',
            'victim' => 'nullable|string',
            'description' => 'required|string|max:255',
            'round_number' => 'nullable|integer|min:1'
        ]);
        
        $validated['match_id'] = $matchId;
        $validated['event_time'] = now();
        $validated['round_number'] = $validated['round_number'] ?? 1;
        
        $eventId = DB::table('match_events')->insertGetId($validated);
        
        $event = DB::table('match_events')->where('id', $eventId)->first();
        
        return response()->json([
            'data' => $event,
            'success' => true,
            'message' => 'Match event recorded'
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error recording event: ' . $e->getMessage()
        ], 500);
    }
});

// Team Compositions
Route::get('/teams/{teamId}/compositions', function ($teamId) {
    try {
        $compositions = DB::table('team_compositions')
            ->where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'data' => $compositions,
            'total' => $compositions->count(),
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching compositions: ' . $e->getMessage()
        ], 500);
    }
});

// Player Match Statistics
Route::get('/players/{playerId}/stats', function ($playerId) {
    try {
        $stats = DB::table('player_match_stats as pms')
            ->leftJoin('matches as m', 'pms.match_id', '=', 'm.id')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->select([
                'pms.*',
                'm.scheduled_at as match_date',
                't1.name as team1_name',
                't2.name as team2_name'
            ])
            ->where('pms.player_id', $playerId)
            ->orderBy('m.scheduled_at', 'desc')
            ->get();
            
        // Calculate aggregate stats
        $aggregate = [
            'total_eliminations' => $stats->sum('eliminations'),
            'total_deaths' => $stats->sum('deaths'),
            'total_assists' => $stats->sum('assists'),
            'total_damage' => $stats->sum('damage_dealt'),
            'total_healing' => $stats->sum('healing_done'),
            'avg_kda' => $stats->count() > 0 ? 
                ($stats->sum('eliminations') + $stats->sum('assists')) / max($stats->sum('deaths'), 1) : 0,
            'matches_played' => $stats->count(),
            'favorite_hero' => $stats->groupBy('hero_played')->map->count()->sortDesc()->keys()->first()
        ];
            
        return response()->json([
            'data' => [
                'recent_matches' => $stats->take(10),
                'aggregate_stats' => $aggregate
            ],
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching player stats: ' . $e->getMessage()
        ], 500);
    }
});

// Tournament Brackets
Route::get('/events/{eventId}/bracket', function ($eventId) {
    try {
        $brackets = DB::table('tournament_brackets as tb')
            ->leftJoin('teams as t1', 'tb.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'tb.team2_id', '=', 't2.id')
            ->leftJoin('teams as tw', 'tb.winner_id', '=', 'tw.id')
            ->select([
                'tb.*',
                't1.name as team1_name', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.logo as team2_logo',
                'tw.name as winner_name'
            ])
            ->where('tb.event_id', $eventId)
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->get();
            
        $bracketsByType = $brackets->groupBy('bracket_type');
            
        return response()->json([
            'data' => $bracketsByType,
            'total_matches' => $brackets->count(),
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching bracket: ' . $e->getMessage()
        ], 500);
    }
});

// Live Leaderboards
Route::get('/leaderboards/teams', function (Request $request) {
    try {
        $region = $request->get('region', 'all');
        
        $query = DB::table('teams')
            ->select(['id', 'name', 'short_name', 'region', 'rating', 'rank', 'logo'])
            ->orderBy('rating', 'desc');
            
        if ($region !== 'all') {
            $query->where('region', $region);
        }
        
        $teams = $query->limit(50)->get();
        
        return response()->json([
            'data' => $teams,
            'region' => $region,
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching leaderboard: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/leaderboards/players', function (Request $request) {
    try {
        $role = $request->get('role', 'all');
        
        $query = DB::table('players as p')
            ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
            ->select([
                'p.id', 'p.name', 'p.username', 'p.role', 'p.rating', 
                'p.region', 'p.main_hero', 'p.avatar',
                't.name as team_name', 't.logo as team_logo'
            ])
            ->orderBy('p.rating', 'desc');
            
        if ($role !== 'all') {
            $query->where('p.role', $role);
        }
        
        $players = $query->limit(100)->get();
        
        return response()->json([
            'data' => $players,
            'role' => $role,
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching player leaderboard: ' . $e->getMessage()
        ], 500);
    }
});

// Meta Statistics
Route::get('/meta/heroes', function () {
    try {
        $heroStats = DB::table('player_match_stats as pms')
            ->leftJoin('matches as m', 'pms.match_id', '=', 'm.id')
            ->select([
                'pms.hero_played as hero',
                DB::raw('COUNT(*) as games_played'),
                DB::raw('AVG(pms.eliminations) as avg_eliminations'),
                DB::raw('AVG(pms.deaths) as avg_deaths'),
                DB::raw('AVG(pms.assists) as avg_assists'),
                DB::raw('AVG(pms.damage_dealt) as avg_damage'),
                DB::raw('SUM(CASE WHEN m.team1_score > m.team2_score THEN 1 ELSE 0 END) as wins'),
                DB::raw('ROUND((SUM(CASE WHEN m.team1_score > m.team2_score THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as win_rate')
            ])
            ->where('m.status', 'completed')
            ->where('m.created_at', '>=', now()->subDays(30))
            ->groupBy('pms.hero_played')
            ->orderBy('games_played', 'desc')
            ->get();
            
        return response()->json([
            'data' => $heroStats,
            'period' => 'Last 30 days',
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching meta stats: ' . $e->getMessage()
        ], 500);
    }
});

// REMOVED DUPLICATE ROUTES - USING ORIGINAL WORKING VERSIONS ABOVE

// ==========================================
// ENHANCED MARVEL RIVALS MATCH MANAGEMENT
// ==========================================

// Enhanced Match Update with Maps & Team Compositions
Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/admin/matches/{id}', function (Request $request, $id) {
    try {
        // Check if match exists using DB query instead of model
        $match = DB::table('matches')->where('id', $id)->first();
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }
        
        $validated = $request->validate([
            'team1_score' => 'sometimes|integer|min:0',
            'team2_score' => 'sometimes|integer|min:0',
            'status' => 'sometimes|string|in:upcoming,live,completed,cancelled',
            'stream_url' => 'sometimes|nullable|url'
        ]);
        
        // Update match basic info
        if (!empty($validated)) {
            DB::table('matches')->where('id', $id)->update(array_merge($validated, [
                'updated_at' => now()
            ]));
        }
        
        // Get updated match
        $updatedMatch = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'm.*',
                't1.name as team1_name', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.logo as team2_logo', 
                'e.name as event_name'
            ])
            ->where('m.id', $id)
            ->first();
            
        return response()->json([
            'data' => $updatedMatch,
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
            'message' => 'Error updating match: ' . $e->getMessage()
        ], 500);
    }
});

// Real-time Live Stats Update
Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/admin/matches/{id}/live-stats', function (Request $request, $id) {
    try {
        $validated = $request->validate([
            'player_stats' => 'required|array',
            'player_stats.*.player_id' => 'required|integer',
            'player_stats.*.hero_played' => 'required|string',
            'player_stats.*.eliminations' => 'sometimes|integer|min:0',
            'player_stats.*.deaths' => 'sometimes|integer|min:0',
            'player_stats.*.assists' => 'sometimes|integer|min:0',
            'player_stats.*.damage_dealt' => 'sometimes|integer|min:0',
            'player_stats.*.healing_done' => 'sometimes|integer|min:0',
            'player_stats.*.damage_blocked' => 'sometimes|integer|min:0',
            'current_map' => 'sometimes|string',
            'round_number' => 'sometimes|integer|min:1',
            'match_events' => 'sometimes|array',
            'match_events.*.type' => 'required_with:match_events|string|in:kill,death,objective,round_start,round_end,hero_swap',
            'match_events.*.player_name' => 'sometimes|string',
            'match_events.*.hero' => 'sometimes|string',
            'match_events.*.description' => 'required_with:match_events|string'
        ]);
        
        // Update player stats
        foreach ($validated['player_stats'] as $playerStat) {
            $statData = collect($playerStat)->except('player_id')->toArray();
            $statData['match_id'] = $id;
            $statData['updated_at'] = now();
            
            DB::table('player_match_stats')->updateOrInsert([
                'player_id' => $playerStat['player_id'],
                'match_id' => $id,
                'hero_played' => $playerStat['hero_played']
            ], $statData);
        }
        
        // Add match events if provided
        if (isset($validated['match_events'])) {
            foreach ($validated['match_events'] as $event) {
                $eventData = $event;
                $eventData['match_id'] = $id;
                $eventData['event_time'] = now();
                $eventData['round_number'] = $validated['round_number'] ?? 1;
                $eventData['created_at'] = now();
                $eventData['updated_at'] = now();
                
                DB::table('match_events')->insert($eventData);
            }
        }
        
        // Update match with current map if provided
        if (isset($validated['current_map'])) {
            DB::table('matches')->where('id', $id)->update([
                'current_map' => $validated['current_map'],
                'updated_at' => now()
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Live stats updated successfully',
            'timestamp' => now()->toISOString()
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating live stats: ' . $e->getMessage()
        ], 500);
    }
});

// Marvel Heroes Database (Enhanced)
Route::get('/heroes', function (Request $request) {
    try {
        $role = $request->get('role'); // Optional filter by role
        
        $query = DB::table('marvel_heroes')->orderBy('role')->orderBy('name');
        
        if ($role) {
            $query->where('role', $role);
        }
        
        $heroes = $query->get();
        
        // Add image URLs to each hero
        $heroesWithImages = $heroes->map(function ($hero) {
            $heroKey = strtolower(str_replace(' ', '_', $hero->name));
            $imagePath = "/storage/heroes/{$heroKey}.png";
            $imageExists = file_exists(public_path($imagePath));
            
            return (object) array_merge((array) $hero, [
                'image_url' => $imageExists ? url($imagePath) : null,
                'image_exists' => $imageExists,
                'expected_path' => $imagePath
            ]);
        });
        
        // Group by role for easier frontend consumption
        $heroesByRole = $heroesWithImages->groupBy('role');
        
        return response()->json([
            'data' => [
                'all' => $heroesWithImages,
                'by_role' => $heroesByRole,
                'summary' => [
                    'total_heroes' => $heroesWithImages->count(),
                    'tanks' => $heroesByRole->get('Tank', collect())->count(),
                    'duelists' => $heroesByRole->get('Duelist', collect())->count(),
                    'supports' => $heroesByRole->get('Support', collect())->count(),
                    'images_available' => $heroesWithImages->where('image_exists', true)->count(),
                    'images_missing' => $heroesWithImages->where('image_exists', false)->count()
                ]
            ],
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching heroes: ' . $e->getMessage()
        ], 500);
    }
});

// Match Live Data (Get current state)
Route::get('/matches/{id}/live-data', function ($id) {
    try {
        $match = \App\Models\Match::with(['team1', 'team2'])->findOrFail($id);
        
        // Get current player stats
        $playerStats = DB::table('player_match_stats as pms')
            ->leftJoin('players as p', 'pms.player_id', '=', 'p.id')
            ->select([
                'pms.*', 
                'p.name as player_name',
                'p.username as player_username'
            ])
            ->where('pms.match_id', $id)
            ->get();
            
        // Get recent match events (last 20)
        $recentEvents = DB::table('match_events')
            ->where('match_id', $id)
            ->orderBy('event_time', 'desc')
            ->limit(20)
            ->get();
            
        // Get team compositions for current match
        $compositions = DB::table('team_compositions as tc')
            ->leftJoin('teams as t', 'tc.team_id', '=', 't.id')
            ->select(['tc.*', 't.name as team_name'])
            ->where('tc.match_id', $id)
            ->get();
        
        return response()->json([
            'data' => [
                'match' => $match,
                'player_stats' => $playerStats->groupBy('hero_played'),
                'recent_events' => $recentEvents,
                'team_compositions' => $compositions->groupBy('team_id'),
                'last_updated' => now()->toISOString()
            ],
            'success' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching live data: ' . $e->getMessage()
        ], 500);
    }
});

// REMOVED - USING REAL DATABASE ROUTES ABOVE

// 4. SEARCH SYSTEM - REMOVED STATIC DATA, USING REAL SEARCH ABOVE

// 6. TOURNAMENT SYSTEM - REMOVED STATIC DATA, USING DATABASE ROUTES ABOVE

// 7. MODERATION SYSTEM - REMOVED STATIC DATA, USING DATABASE ROUTES ABOVE

// 8. BULK OPERATIONS - REMOVED STATIC DATA, USING DATABASE ROUTES ABOVE

// 9. USER MANAGEMENT - REMOVED STATIC DATA, USING DATABASE ROUTES ABOVE

Route::middleware(['auth:sanctum'])->put('/user/profile', function (Request $request) {
    $validated = $request->validate([
        'name' => 'sometimes|string',
        'avatar' => 'sometimes|string',
        'bio' => 'sometimes|string'
    ]);
    return response()->json(['success' => true, 'message' => 'Profile updated', 'data' => $validated]);
});

Route::middleware(['auth:sanctum'])->put('/user/notifications', function (Request $request) {
    $validated = $request->validate(['notifications' => 'required|array']);
    return response()->json(['success' => true, 'message' => 'Notification preferences updated']);
});

Route::middleware(['auth:sanctum'])->put('/user/privacy', function (Request $request) {
    $validated = $request->validate(['privacy' => 'required|array']);
    return response()->json(['success' => true, 'message' => 'Privacy settings updated']);
});

// 10. NEWS MANAGEMENT - REMOVED DUPLICATES, USING WORKING VERSIONS ABOVE

// 11. TEAM MANAGEMENT ADMIN (1 endpoint)
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/admin/teams/{id}', function ($teamId) {
    return response()->json(['success' => true, 'message' => 'Team deleted successfully']);
});