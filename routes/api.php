<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AdminStatsController;
use App\Http\Controllers\AdminUserController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'me']);

// Forum routes
Route::prefix('forum')->group(function () {
    Route::get('/threads', [ThreadController::class, 'index']);
    Route::get('/threads/{thread}', [ThreadController::class, 'show']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/threads', [ThreadController::class, 'store']);
        Route::post('/threads/{thread}/posts', [PostController::class, 'store']);
        Route::delete('/threads/{thread}', [ThreadController::class, 'destroy']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
    });
});

// Matches routes
Route::get('/matches', [MatchController::class, 'index']);
Route::get('/matches/{match}', [MatchController::class, 'show']);


// Ranking routes
Route::get('/rankings', [RankingController::class, 'index']);

//Event routes
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);

//Chat routes

Route::middleware('auth:sanctum')->get('/chat', [ChatController::class, 'index']);
Route::middleware('auth:sanctum')->post('/chat', [ChatController::class, 'store']);

// Admin routes

Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/stats', [AdminStatsController::class, 'index']);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
});