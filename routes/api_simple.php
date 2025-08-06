<?php

use App\Http\Controllers\{
    TeamController,
    PlayerController,
    MatchController,
    EventController,
    NewsController,
    SimpleController
};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SIMPLE CRUD API ROUTES - PRODUCTION READY
|--------------------------------------------------------------------------
| Basic Create, Read, Update, Delete operations
| No complex middleware - just working endpoints
|--------------------------------------------------------------------------
*/

// ===================================
// SIMPLE CRUD - No Auth, No Errors
// ===================================
Route::prefix('simple')->group(function () {
    // Teams
    Route::get('/teams', [SimpleController::class, 'getTeams']);
    Route::post('/teams', [SimpleController::class, 'createTeam']);
    Route::put('/teams/{id}', [SimpleController::class, 'updateTeam']);
    Route::delete('/teams/{id}', [SimpleController::class, 'deleteTeam']);
    
    // Players
    Route::get('/players', [SimpleController::class, 'getPlayers']);
    Route::post('/players', [SimpleController::class, 'createPlayer']);
    Route::put('/players/{id}', [SimpleController::class, 'updatePlayer']);
    Route::delete('/players/{id}', [SimpleController::class, 'deletePlayer']);
    
    // Matches
    Route::get('/matches', [SimpleController::class, 'getMatches']);
    Route::post('/matches', [SimpleController::class, 'createMatch']);
    Route::put('/matches/{id}', [SimpleController::class, 'updateMatch']);
    Route::delete('/matches/{id}', [SimpleController::class, 'deleteMatch']);
});

// ===================================
// TEAMS - Basic CRUD
// ===================================
Route::prefix('teams')->group(function () {
    Route::get('/', [TeamController::class, 'index']);
    Route::post('/', [TeamController::class, 'store']);
    Route::get('/{id}', [TeamController::class, 'show']);
    Route::put('/{id}', [TeamController::class, 'update']);
    Route::delete('/{id}', [TeamController::class, 'destroy']);
});

// ===================================
// PLAYERS - Basic CRUD
// ===================================
Route::prefix('players')->group(function () {
    Route::get('/', [PlayerController::class, 'index']);
    Route::post('/', [PlayerController::class, 'store']);
    Route::get('/{id}', [PlayerController::class, 'show']);
    Route::put('/{id}', [PlayerController::class, 'update']);
    Route::delete('/{id}', [PlayerController::class, 'destroy']);
});

// ===================================
// MATCHES - Basic CRUD
// ===================================
Route::prefix('matches')->group(function () {
    Route::get('/', [MatchController::class, 'index']);
    Route::post('/', [MatchController::class, 'store']);
    Route::get('/{id}', [MatchController::class, 'show']);
    Route::put('/{id}', [MatchController::class, 'update']);
    Route::delete('/{id}', [MatchController::class, 'destroy']);
    
    // Live scoring
    Route::post('/{id}/update-score', [MatchController::class, 'updateScore']);
    Route::post('/{id}/start', [MatchController::class, 'startMatch']);
    Route::post('/{id}/complete', [MatchController::class, 'completeMatch']);
});

// ===================================
// EVENTS - Basic CRUD
// ===================================
Route::prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::post('/', [EventController::class, 'store']);
    Route::get('/{id}', [EventController::class, 'show']);
    Route::put('/{id}', [EventController::class, 'update']);
    Route::delete('/{id}', [EventController::class, 'destroy']);
});

// ===================================
// NEWS - Basic CRUD
// ===================================
Route::prefix('news')->group(function () {
    Route::get('/', [NewsController::class, 'index']);
    Route::post('/', [NewsController::class, 'store']);
    Route::get('/{id}', [NewsController::class, 'show']);
    Route::put('/{id}', [NewsController::class, 'update']);
    Route::delete('/{id}', [NewsController::class, 'destroy']);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is working',
        'timestamp' => now()
    ]);
});