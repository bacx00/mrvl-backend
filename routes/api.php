<?php
use App\Http\Controllers\{
    AuthController,
    TeamController,
    PlayerController,
    MatchController,
    EventController,
    SearchController,
    ForumController,
    AdminStatsController
};

// Public Authentication Routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Public Data Routes
Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{player}', [PlayerController::class, 'show']);
Route::get('/matches', [MatchController::class, 'index']);
Route::get('/matches/{match}', [MatchController::class, 'show']);
Route::get('/matches/live', [MatchController::class, 'live']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/rankings', [TeamController::class, 'rankings']);
Route::get('/search', [SearchController::class, 'search']);

// Public Forum Routes
Route::get('/forum/threads', [ForumController::class, 'index']);
Route::get('/forum/threads/{thread}', [ForumController::class, 'show']);

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth Routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Forum Write Operations
    Route::post('/forum/threads', [ForumController::class, 'store']);
    
    // Admin Routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/stats', [AdminStatsController::class, 'index']);
        
        // Full CRUD for all resources
        Route::apiResource('/admin/teams', TeamController::class)->except(['index', 'show']);
        Route::apiResource('/admin/players', PlayerController::class)->except(['index', 'show']);
        Route::apiResource('/admin/matches', MatchController::class)->except(['index', 'show']);
        Route::apiResource('/admin/events', EventController::class)->except(['index', 'show']);
    });
});
