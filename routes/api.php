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

// Authenticated Routes
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

// Forum Write Operations
Route::post('/forum/threads', [ForumController::class, 'store'])->middleware('auth:sanctum');

// Original grouped routes
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
