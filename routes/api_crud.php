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
    ImageUploadController,
    BracketController,
    RankingController,
    HeroController,
    UserProfileController,
    GameDataController
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| COMPREHENSIVE CRUD API ROUTES WITH ROLE-BASED PERMISSIONS
|--------------------------------------------------------------------------
| 🔴 Admin: Full system access, user management, all CRUD operations
| 🟡 Moderator: Content moderation, limited admin features
| 🟢 User: Basic access, forum participation, public data viewing
|--------------------------------------------------------------------------
*/

// ===================================
// AUTHENTICATION ROUTES
// ===================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::middleware('auth:api')->post('/logout', [AuthController::class, 'logout']);
    Route::middleware('auth:api')->get('/me', [AuthController::class, 'me']);
    Route::middleware('auth:api')->post('/refresh', [AuthController::class, 'refresh']);
});

// ===================================
// PUBLIC ROUTES (No Authentication Required)
// ===================================
Route::prefix('public')->group(function () {
    // Teams
    Route::get('/teams', [TeamController::class, 'index']);
    Route::get('/teams/{id}', [TeamController::class, 'show']);
    
    // Players
    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/players/{id}', [PlayerController::class, 'show']);
    
    // Events
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    
    // Matches
    Route::get('/matches', [MatchController::class, 'index']);
    Route::get('/matches/{id}', [MatchController::class, 'show']);
    
    // Forums (Read Only)
    Route::get('/forums/categories', [ForumController::class, 'getCategories']);
    Route::get('/forums/threads', [ForumController::class, 'index']);
    Route::get('/forums/threads/{id}', [ForumController::class, 'show']);
    
    // News (Read Only)
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/{id}', [NewsController::class, 'show']);
    
    // Rankings
    Route::get('/rankings', [RankingController::class, 'index']);
    Route::get('/rankings/{id}', [RankingController::class, 'show']);
    Route::get('/rankings/distribution', [RankingController::class, 'getRankDistribution']);
    
    // Heroes
    Route::get('/heroes', [HeroController::class, 'index']);
    Route::get('/heroes/{slug}', [HeroController::class, 'show']);
    Route::get('/heroes/roles', [HeroController::class, 'getRoles']);
    
    // Game Data
    Route::get('/game-data/maps', [GameDataController::class, 'getMaps']);
    Route::get('/game-data/modes', [GameDataController::class, 'getGameModes']);
    Route::get('/game-data/heroes', [GameDataController::class, 'getHeroRoster']);
    Route::get('/game-data/rankings', [GameDataController::class, 'getRankingInfo']);
    Route::get('/game-data/meta', [GameDataController::class, 'getCurrentMeta']);
    Route::get('/game-data/complete', [GameDataController::class, 'getCompleteGameData']);
    
    // Brackets
    Route::get('/events/{eventId}/bracket', [BracketController::class, 'show']);
    
    // Search
    Route::get('/search', [SearchController::class, 'search']);
});

// ===================================
// USER ROUTES (🟢 User Role + Authentication)
// ===================================
Route::middleware(['auth:api', 'role:user|moderator|admin'])->prefix('user')->group(function () {
    
    // Profile Management
    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::put('/profile', [UserProfileController::class, 'updateProfile']);
    Route::put('/profile/flairs', [UserProfileController::class, 'updateFlairs']);
    Route::get('/profile/available-flairs', [UserProfileController::class, 'getAvailableFlairs']);
    
    // Forum CRUD Operations
    Route::prefix('forums')->group(function () {
        // Threads
        Route::post('/threads', [ForumController::class, 'store']);
        Route::put('/threads/{id}', [ForumController::class, 'update'])->middleware('can:update,thread');
        Route::delete('/threads/{id}', [ForumController::class, 'destroy'])->middleware('can:delete,thread');
        
        // Posts/Replies
        Route::post('/threads/{threadId}/posts', [ForumController::class, 'storePost']);
        Route::put('/posts/{postId}', [ForumController::class, 'updatePost'])->middleware('can:update,post');
        Route::delete('/posts/{postId}', [ForumController::class, 'destroyPost'])->middleware('can:delete,post');
        
        // Voting
        Route::post('/threads/{threadId}/vote', [ForumController::class, 'voteThread']);
        Route::post('/posts/{postId}/vote', [ForumController::class, 'votePost']);
    });
    
    // News Comments
    Route::prefix('news')->group(function () {
        Route::post('/{newsId}/comments', [NewsController::class, 'storeComment']);
        Route::put('/comments/{commentId}', [NewsController::class, 'updateComment'])->middleware('can:update,comment');
        Route::delete('/comments/{commentId}', [NewsController::class, 'destroyComment'])->middleware('can:delete,comment');
        Route::post('/comments/{commentId}/vote', [NewsController::class, 'voteComment']);
    });
    
    // Match Comments
    Route::prefix('matches')->group(function () {
        Route::post('/{matchId}/comments', [MatchController::class, 'storeComment']);
        Route::put('/comments/{commentId}', [MatchController::class, 'updateComment'])->middleware('can:update,comment');
        Route::delete('/comments/{commentId}', [MatchController::class, 'destroyComment'])->middleware('can:delete,comment');
        Route::post('/comments/{commentId}/vote', [MatchController::class, 'voteComment']);
    });
    
    // User Predictions & Favorites
    Route::prefix('predictions')->group(function () {
        Route::get('/', [MatchController::class, 'getUserPredictions']);
        Route::post('/matches/{matchId}', [MatchController::class, 'storePrediction']);
        Route::put('/{predictionId}', [MatchController::class, 'updatePrediction']);
        Route::delete('/{predictionId}', [MatchController::class, 'destroyPrediction']);
    });
    
    Route::prefix('favorites')->group(function () {
        Route::get('/teams', [TeamController::class, 'getUserFavoriteTeams']);
        Route::post('/teams/{teamId}', [TeamController::class, 'addFavoriteTeam']);
        Route::delete('/teams/{teamId}', [TeamController::class, 'removeFavoriteTeam']);
        
        Route::get('/players', [PlayerController::class, 'getUserFavoritePlayers']);
        Route::post('/players/{playerId}', [PlayerController::class, 'addFavoritePlayer']);
        Route::delete('/players/{playerId}', [PlayerController::class, 'removeFavoritePlayer']);
    });
    
    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [AuthController::class, 'getUserNotifications']);
        Route::put('/{notificationId}/read', [AuthController::class, 'markNotificationRead']);
        Route::put('/mark-all-read', [AuthController::class, 'markAllNotificationsRead']);
        Route::delete('/{notificationId}', [AuthController::class, 'deleteNotification']);
    });
});

// ===================================
// MODERATOR ROUTES (🟡 Moderator Role + Admin Permissions)
// ===================================
Route::middleware(['auth:api', 'role:moderator|admin'])->prefix('moderator')->group(function () {
    
    // Content Moderation
    Route::prefix('forums')->group(function () {
        Route::get('/threads/reported', [ForumController::class, 'getReportedThreads']);
        Route::get('/posts/reported', [ForumController::class, 'getReportedPosts']);
        Route::put('/threads/{threadId}/moderate', [ForumController::class, 'moderateThread']);
        Route::put('/posts/{postId}/moderate', [ForumController::class, 'moderatePost']);
        Route::post('/threads/{threadId}/pin', [ForumController::class, 'pinThread']);
        Route::post('/threads/{threadId}/unpin', [ForumController::class, 'unpinThread']);
        Route::post('/threads/{threadId}/lock', [ForumController::class, 'lockThread']);
        Route::post('/threads/{threadId}/unlock', [ForumController::class, 'unlockThread']);
        Route::delete('/threads/{threadId}/force', [ForumController::class, 'forceDeleteThread']);
        Route::delete('/posts/{postId}/force', [ForumController::class, 'forceDeletePost']);
    });
    
    // News Moderation
    Route::prefix('news')->group(function () {
        Route::get('/pending', [NewsController::class, 'getPendingNews']);
        Route::put('/{newsId}/approve', [NewsController::class, 'approveNews']);
        Route::put('/{newsId}/reject', [NewsController::class, 'rejectNews']);
        Route::put('/{newsId}/feature', [NewsController::class, 'featureNews']);
        Route::put('/{newsId}/unfeature', [NewsController::class, 'unfeatureNews']);
        Route::delete('/{newsId}/force', [NewsController::class, 'forceDeleteNews']);
        
        // Comments moderation
        Route::get('/comments/reported', [NewsController::class, 'getReportedComments']);
        Route::put('/comments/{commentId}/moderate', [NewsController::class, 'moderateComment']);
        Route::delete('/comments/{commentId}/force', [NewsController::class, 'forceDeleteComment']);
    });
    
    // Match Moderation
    Route::prefix('matches')->group(function () {
        Route::put('/{matchId}/status', [MatchController::class, 'updateMatchStatus']);
        Route::put('/{matchId}/score', [MatchController::class, 'updateMatchScore']);
        Route::post('/{matchId}/set-live', [MatchController::class, 'setMatchLive']);
        Route::post('/{matchId}/complete', [MatchController::class, 'completeMatch']);
        
        // Comments moderation
        Route::get('/comments/reported', [MatchController::class, 'getReportedComments']);
        Route::put('/comments/{commentId}/moderate', [MatchController::class, 'moderateComment']);
        Route::delete('/comments/{commentId}/force', [MatchController::class, 'forceDeleteComment']);
    });
    
    // User Moderation
    Route::prefix('users')->group(function () {
        Route::get('/reported', [AuthController::class, 'getReportedUsers']);
        Route::post('/{userId}/warn', [AuthController::class, 'warnUser']);
        Route::post('/{userId}/mute', [AuthController::class, 'muteUser']);
        Route::post('/{userId}/unmute', [AuthController::class, 'unmuteUser']);
        Route::post('/{userId}/ban', [AuthController::class, 'banUser']);
        Route::post('/{userId}/unban', [AuthController::class, 'unbanUser']);
    });
    
    // Event Management (Limited)
    Route::prefix('events')->group(function () {
        Route::put('/{eventId}/status', [EventController::class, 'updateEventStatus']);
        Route::post('/{eventId}/teams/{teamId}/approve', [EventController::class, 'approveTeamRegistration']);
        Route::post('/{eventId}/teams/{teamId}/reject', [EventController::class, 'rejectTeamRegistration']);
    });
    
    // Moderator Dashboard
    Route::get('/dashboard/stats', [AdminStatsController::class, 'getModeratorStats']);
    Route::get('/dashboard/recent-activity', [AdminStatsController::class, 'getRecentModerationActivity']);
});

// ===================================
// ADMIN ROUTES (🔴 Admin Role - Full Access)
// ===================================
Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
    
    // User Management - Full CRUD
    Route::prefix('users')->group(function () {
        Route::get('/', [AuthController::class, 'getAllUsers']);
        Route::post('/', [AuthController::class, 'createUser']);
        Route::get('/{userId}', [AuthController::class, 'getUser']);
        Route::put('/{userId}', [AuthController::class, 'updateUser']);
        Route::delete('/{userId}', [AuthController::class, 'deleteUser']);
        Route::post('/{userId}/roles', [AuthController::class, 'assignUserRoles']);
        Route::delete('/{userId}/roles/{roleId}', [AuthController::class, 'removeUserRole']);
        Route::get('/{userId}/activity', [AuthController::class, 'getUserActivity']);
        Route::post('/{userId}/reset-password', [AuthController::class, 'resetUserPassword']);
    });
    
    // Team Management - Full CRUD
    Route::prefix('teams')->group(function () {
        Route::get('/', [TeamController::class, 'getAllTeams']);
        Route::post('/', [TeamController::class, 'store']);
        Route::get('/{teamId}', [TeamController::class, 'getTeamAdmin']);
        Route::put('/{teamId}', [TeamController::class, 'update']);
        Route::delete('/{teamId}', [TeamController::class, 'destroy']);
        
        // Team Roster Management
        Route::post('/{teamId}/players', [TeamController::class, 'addPlayer']);
        Route::delete('/{teamId}/players/{playerId}', [TeamController::class, 'removePlayer']);
        Route::put('/{teamId}/players/{playerId}/role', [TeamController::class, 'updatePlayerRole']);
        Route::post('/{teamId}/transfer-player', [TeamController::class, 'transferPlayer']);
        
        // Team Images
        Route::post('/{teamId}/logo', [ImageUploadController::class, 'uploadTeamLogo']);
        Route::post('/{teamId}/banner', [ImageUploadController::class, 'uploadTeamBanner']);
    });
    
    // Player Management - Full CRUD
    Route::prefix('players')->group(function () {
        Route::get('/', [PlayerController::class, 'getAllPlayers']);
        Route::post('/', [PlayerController::class, 'store']);
        Route::post('/bulk-delete', [PlayerController::class, 'bulkDelete']);
        Route::get('/{playerId}', [PlayerController::class, 'getPlayerAdmin']);
        Route::put('/{playerId}', [PlayerController::class, 'update']);
        Route::delete('/{playerId}', [PlayerController::class, 'destroy']);
        
        // Player Stats Management
        Route::put('/{playerId}/stats', [PlayerController::class, 'updateStats']);
        Route::post('/{playerId}/achievements', [PlayerController::class, 'addAchievement']);
        Route::delete('/{playerId}/achievements/{achievementId}', [PlayerController::class, 'removeAchievement']);
        
        // Player Images
        Route::post('/{playerId}/avatar', [ImageUploadController::class, 'uploadPlayerAvatar']);
    });
    
    // Event Management - Full CRUD
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'getAllEvents']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{eventId}', [EventController::class, 'getEventAdmin']);
        Route::put('/{eventId}', [EventController::class, 'update']);
        Route::delete('/{eventId}', [EventController::class, 'destroy']);
        Route::delete('/{eventId}/force', [EventController::class, 'forceDestroy']);
        
        // Event Team Management
        Route::get('/{eventId}/teams', [EventController::class, 'getEventTeams']);
        Route::post('/{eventId}/teams/{teamId}', [EventController::class, 'addTeamToEvent']);
        Route::delete('/{eventId}/teams/{teamId}', [EventController::class, 'removeTeamFromEvent']);
        Route::put('/{eventId}/teams/{teamId}/seed', [EventController::class, 'updateTeamSeed']);
        
        // Bracket Management
        Route::post('/{eventId}/generate-bracket', [BracketController::class, 'generate']);
        Route::put('/{eventId}/bracket/matches/{matchId}', [BracketController::class, 'updateMatch']);
        
        // Event Images
        Route::post('/{eventId}/banner', [ImageUploadController::class, 'uploadEventBanner']);
    });
    
    // Match Management - Full CRUD
    Route::prefix('matches')->group(function () {
        Route::get('/', [MatchController::class, 'getAllMatches']);
        Route::post('/', [MatchController::class, 'store']);
        Route::get('/{matchId}', [MatchController::class, 'getMatchAdmin']);
        Route::put('/{matchId}', [MatchController::class, 'update']);
        Route::delete('/{matchId}', [MatchController::class, 'destroy']);
        
        // Match Stats Management
        Route::post('/{matchId}/stats/bulk', [MatchController::class, 'bulkUpdateStats']);
        Route::put('/{matchId}/players/{playerId}/stats', [MatchController::class, 'updatePlayerStats']);
        Route::post('/{matchId}/events', [MatchController::class, 'addMatchEvent']);
        Route::put('/{matchId}/live-data', [MatchController::class, 'updateLiveData']);
    });
    
    // News Management - Full CRUD
    Route::prefix('news')->group(function () {
        Route::get('/', [NewsController::class, 'getAllNews']);
        Route::post('/', [NewsController::class, 'store']);
        Route::get('/{newsId}', [NewsController::class, 'getNewsAdmin']);
        Route::put('/{newsId}', [NewsController::class, 'update']);
        Route::delete('/{newsId}', [NewsController::class, 'destroy']);
        
        // News Images
        Route::post('/{newsId}/featured-image', [ImageUploadController::class, 'uploadNewsFeaturedImage']);
        Route::post('/{newsId}/images', [ImageUploadController::class, 'uploadNewsImages']);
    });
    
    // Forum Management - Full CRUD
    Route::prefix('forums')->group(function () {
        // Categories
        Route::get('/categories', [ForumController::class, 'getAllCategories']);
        Route::post('/categories', [ForumController::class, 'storeCategory']);
        Route::put('/categories/{categoryId}', [ForumController::class, 'updateCategory']);
        Route::delete('/categories/{categoryId}', [ForumController::class, 'destroyCategory']);
        
        // Threads (Admin powers)
        Route::get('/threads', [ForumController::class, 'getAllThreads']);
        Route::put('/threads/{threadId}', [ForumController::class, 'updateThreadAdmin']);
        Route::delete('/threads/{threadId}/force', [ForumController::class, 'forceDeleteThread']);
        Route::post('/threads/{threadId}/pin', [ForumController::class, 'pinThread']);
        Route::post('/threads/{threadId}/unpin', [ForumController::class, 'unpinThread']);
        Route::post('/threads/{threadId}/lock', [ForumController::class, 'lockThread']);
        Route::post('/threads/{threadId}/unlock', [ForumController::class, 'unlockThread']);
        Route::post('/threads/{threadId}/feature', [ForumController::class, 'featureThread']);
        Route::post('/threads/{threadId}/unfeature', [ForumController::class, 'unfeatureThread']);
    });
    
    // Hero Management - Full CRUD
    Route::prefix('heroes')->group(function () {
        Route::post('/', [HeroController::class, 'store']);
        Route::put('/{heroId}', [HeroController::class, 'update']);
        Route::delete('/{heroId}', [HeroController::class, 'destroy']);
        Route::put('/{heroId}/stats', [HeroController::class, 'updateHeroStats']);
        Route::post('/{heroId}/images', [ImageUploadController::class, 'uploadHeroImages']);
    });
    
    // Rankings Management
    Route::prefix('rankings')->group(function () {
        Route::put('/recalculate', [RankingController::class, 'recalculateRankings']);
        Route::post('/season-reset', [RankingController::class, 'performSeasonReset']);
        Route::put('/players/{playerId}/rating', [RankingController::class, 'updatePlayerRating']);
    });
    
    // System Management
    Route::prefix('system')->group(function () {
        Route::get('/stats', [AdminStatsController::class, 'getSystemStats']);
        Route::get('/health', [AdminStatsController::class, 'getSystemHealth']);
        Route::post('/cache/clear', [AdminStatsController::class, 'clearCache']);
        Route::post('/maintenance/toggle', [AdminStatsController::class, 'toggleMaintenanceMode']);
        Route::get('/logs', [AdminStatsController::class, 'getSystemLogs']);
    });
    
    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/overview', [AdminStatsController::class, 'getAnalyticsOverview']);
        Route::get('/users', [AdminStatsController::class, 'getUserAnalytics']);
        Route::get('/content', [AdminStatsController::class, 'getContentAnalytics']);
        Route::get('/engagement', [AdminStatsController::class, 'getEngagementAnalytics']);
    });
});

// ===================================
// SEARCH ROUTES (All Authenticated Users)
// ===================================
Route::middleware('auth:api')->prefix('search')->group(function () {
    Route::get('/advanced', [SearchController::class, 'advancedSearch']);
    Route::get('/teams', [SearchController::class, 'searchTeams']);
    Route::get('/players', [SearchController::class, 'searchPlayers']);
    Route::get('/matches', [SearchController::class, 'searchMatches']);
    Route::get('/events', [SearchController::class, 'searchEvents']);
    Route::get('/news', [SearchController::class, 'searchNews']);
    Route::get('/forums', [SearchController::class, 'searchForums']);
    Route::get('/users', [SearchController::class, 'searchUsers'])->middleware('role:moderator|admin');
});

// ===================================
// REPORTING ROUTES (All Authenticated Users)
// ===================================
Route::middleware('auth:api')->prefix('reports')->group(function () {
    Route::post('/forums/threads/{threadId}', [ForumController::class, 'reportThread']);
    Route::post('/forums/posts/{postId}', [ForumController::class, 'reportPost']);
    Route::post('/news/comments/{commentId}', [NewsController::class, 'reportComment']);
    Route::post('/matches/comments/{commentId}', [MatchController::class, 'reportComment']);
    Route::post('/users/{userId}', [AuthController::class, 'reportUser']);
});