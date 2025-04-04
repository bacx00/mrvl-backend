<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\PostController;

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