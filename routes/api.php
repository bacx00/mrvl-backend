<?php


use App\Http\Controllers\{
    AuthController,
    TeamController,
    PlayerController,
    MatchController,
    EventController,
    EventControllerTemp,
    SearchController,
    ForumController,
    AdminStatsController,
    AdminUserController,
    AdminMatchController,
    NewsController,
    ImageUploadController,
    BracketController,
    ComprehensiveBracketController,
    RankingController,
    HeroController,
    UserProfileController,
    GameDataController,
    AdminController,
    BulkOperationController,
    MentionController,
    TestDataController,
    VoteController
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

// Add missing login route to prevent 500 errors
Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Authentication required. Please provide a valid Bearer token.',
        'error' => 'Unauthenticated'
    ], 401);
})->name('login');

// ===================================
// AUTHENTICATION ROUTES
// ===================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLinkEmail']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
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
    
    // Player Statistics
    Route::get('/players/{player}/match-history', [PlayerController::class, 'getMatchHistory']);
    Route::get('/players/{player}/hero-stats', [PlayerController::class, 'getHeroStats']);
    Route::get('/players/{player}/performance-stats', [PlayerController::class, 'getPerformanceStats']);
    Route::get('/players/{player}/map-stats', [PlayerController::class, 'getMapStats']);
    Route::get('/players/{player}/event-stats', [PlayerController::class, 'getEventStats']);
    
    // Events - Using temporary controller
    Route::get('/events', [EventControllerTemp::class, 'index']);
    Route::get('/events/{id}', [EventControllerTemp::class, 'show']);
    
    // Matches
    Route::get('/matches', [MatchController::class, 'index']);
    Route::get('/matches/{id}', [MatchController::class, 'show']);
    Route::get('/matches/{id}/live-scoreboard', [MatchController::class, 'liveScoreboard']);
    Route::get('/matches/{id}/live-stream', [MatchController::class, 'liveStream']);
    
    // Forums (Read Only)
    Route::get('/forums/categories', [ForumController::class, 'getCategories']);
    Route::get('/forums/threads', [ForumController::class, 'index']);
    Route::get('/forums/threads/{id}', [ForumController::class, 'show']);
    Route::get('/forums/threads/{id}/posts', [ForumController::class, 'getPosts']);
    Route::get('/forums/search', [SearchController::class, 'searchForums']);
    Route::get('/forums/search/suggestions', [SearchController::class, 'searchSuggestions']);
    Route::get('/forums/trending', [ForumController::class, 'getTrendingThreads']);
    Route::get('/forums/hot', [ForumController::class, 'getHotThreads']);
    
    // News (Read Only)
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/{id}', [NewsController::class, 'show']);
    Route::get('/news/categories', [NewsController::class, 'getCategories']);
    
    // Rankings
    Route::get('/rankings', [RankingController::class, 'index']);
    Route::get('/rankings/{id}', [RankingController::class, 'show']);
    Route::get('/rankings/distribution', [RankingController::class, 'getRankDistribution']);
    Route::get('/rankings/marvel-rivals-info', [RankingController::class, 'getMarvelRivalsInfo']);
    
    // Heroes
    Route::get('/heroes', [HeroController::class, 'index']);
    Route::get('/heroes/roles', [HeroController::class, 'getRoles']);
    Route::get('/heroes/season-2', [HeroController::class, 'getSeasonTwoHeroes']);
    Route::get('/heroes/images', [HeroController::class, 'getHeroImages']);
    Route::get('/heroes/images/all', [HeroController::class, 'getAllHeroImages']);
    Route::get('/heroes/images/{slug}', [HeroController::class, 'getHeroImageBySlug']);
    Route::get('/heroes/{slug}', [HeroController::class, 'show']);
    
    // Game Data
    Route::get('/game-data/maps', [GameDataController::class, 'getMaps']);
    Route::get('/game-data/modes', [GameDataController::class, 'getGameModes']);
    Route::get('/game-data/heroes', [GameDataController::class, 'getHeroRoster']);
    Route::get('/game-data/rankings', [GameDataController::class, 'getRankingInfo']);
    Route::get('/game-data/meta', [GameDataController::class, 'getCurrentMeta']);
    Route::get('/game-data/tournaments', [GameDataController::class, 'getTournamentFormats']);
    Route::get('/game-data/timers', [GameDataController::class, 'getMatchTimers']);
    Route::get('/game-data/technical', [GameDataController::class, 'getTechnicalSpecs']);
    Route::get('/game-data/complete', [GameDataController::class, 'getCompleteGameData']);
    
    // Brackets - Public Access
    Route::get('/events/{eventId}/bracket', [BracketController::class, 'show']);
    Route::get('/events/{eventId}/comprehensive-bracket', [ComprehensiveBracketController::class, 'show']);
    Route::get('/events/{eventId}/bracket-analysis', [ComprehensiveBracketController::class, 'getBracketAnalysis']);
    
    // Search
    Route::get('/search', [SearchController::class, 'search']);
    
    // Mentions autocomplete (public access for better UX)
    Route::get('/mentions/search', [MentionController::class, 'searchMentions']);
    Route::get('/mentions/popular', [MentionController::class, 'getPopularMentions']);
    
    // Public user profiles
    Route::get('/users/{userId}/profile', [UserProfileController::class, 'getUserWithAvatarAndFlairs']);
});

// Legacy public routes (for compatibility)
Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
Route::get('/teams/{team}/mentions', [TeamController::class, 'getMentions']);

// Team match-related endpoints
Route::get('/teams/{team}/matches/upcoming', [TeamController::class, 'getUpcomingMatches']);
Route::get('/teams/{team}/matches/live', [TeamController::class, 'getLiveMatches']);
Route::get('/teams/{team}/matches/recent', [TeamController::class, 'getRecentResults']);
Route::get('/teams/{team}/matches/stats', [TeamController::class, 'getMatchStats']);
Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{player}', [PlayerController::class, 'show']);
Route::get('/players/{player}/mentions', [PlayerController::class, 'getMentions']);
Route::get('/players/{player}/match-history', [PlayerController::class, 'getMatchHistory']);
Route::get('/players/{player}/hero-stats', [PlayerController::class, 'getHeroStats']);
Route::get('/players/{player}/performance-stats', [PlayerController::class, 'getPerformanceStats']);
Route::get('/players/{player}/hero-performance', [PlayerController::class, 'getHeroPerformance']);
Route::get('/players/{player}/map-stats', [PlayerController::class, 'getMapStats']);
Route::get('/players/{player}/event-stats', [PlayerController::class, 'getEventStats']);
Route::get('/events', [EventControllerTemp::class, 'index']);
Route::get('/events/{event}', [EventControllerTemp::class, 'show']);
Route::get('/matches', [MatchController::class, 'index']);
Route::get('/matches/live', [MatchController::class, 'live']);
Route::get('/matches/{match}', [MatchController::class, 'show']);
Route::get('/matches/{match}/live-scoreboard', [MatchController::class, 'liveScoreboard']);
Route::get('/matches/{match}/comments', [MatchController::class, 'getComments']);
Route::get('/matches/{match}/timeline', [MatchController::class, 'getMatchTimeline']);
Route::get('/matches/head-to-head/{team1Id}/{team2Id}', [MatchController::class, 'getHeadToHead']);
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{news}', [NewsController::class, 'show']);
Route::get('/news/{newsId}/comments', [NewsController::class, 'getCommentsWithNesting']);
Route::get('/news/categories', [NewsController::class, 'getCategories']);

// Forum routes (for frontend compatibility)
Route::get('/forums/categories', [ForumController::class, 'getCategories']);
Route::get('/forums/threads', [ForumController::class, 'index']);
Route::get('/forums/threads/{id}', [ForumController::class, 'show']);
Route::get('/forums/threads/{id}/posts', [ForumController::class, 'getPosts']);

// ===================================
// USER ROUTES (🟢 User Role + Authentication)
// ===================================
Route::middleware(['auth:api', 'role:user|moderator|admin'])->prefix('user')->group(function () {
    
    // Profile Management
    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::put('/profile', [UserProfileController::class, 'updateProfile']);
    Route::put('/profile/flairs', [UserProfileController::class, 'updateFlairs']);
    Route::get('/profile/available-flairs', [UserProfileController::class, 'getAvailableFlairs']);
    Route::post('/profile/set-hero-avatar', [UserProfileController::class, 'setHeroAsAvatar']);
    Route::get('/profile/display/{userId}', [UserProfileController::class, 'getUserWithAvatarAndFlairs']);
    Route::get('/profile/activity', [UserProfileController::class, 'getUserActivity']);
    Route::post('/profile/change-password', [UserProfileController::class, 'changePassword']);
    Route::post('/profile/change-email', [UserProfileController::class, 'changeEmail']);
    Route::post('/profile/change-username', [UserProfileController::class, 'changeUsername']);
    Route::post('/profile/upload-avatar', [UserProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/delete-avatar', [UserProfileController::class, 'deleteAvatar']);
    
    // User Stats and Activity
    Route::get('/stats', [AuthController::class, 'getUserStats']);
    Route::get('/activity', [AuthController::class, 'getUserProfileActivity']);
    
    // Voting System
    Route::prefix('votes')->group(function () {
        Route::post('/', [VoteController::class, 'vote']);
        Route::get('/', [VoteController::class, 'getVotes']);
        Route::get('/user', [VoteController::class, 'getUserVotes']);
        Route::get('/stats', [VoteController::class, 'getVoteStats']);
    });
    
    // Forum CRUD Operations
    Route::prefix('forums')->group(function () {
        // Threads
        Route::post('/threads', [ForumController::class, 'store']);
        Route::put('/threads/{id}', [ForumController::class, 'update']);
        Route::delete('/threads/{id}', [ForumController::class, 'destroy']);
        
        // Posts/Replies
        Route::post('/threads/{threadId}/posts', [ForumController::class, 'storePost']);
        Route::put('/posts/{postId}', [ForumController::class, 'updatePost']);
        Route::delete('/posts/{postId}', [ForumController::class, 'destroyPost']);
        
        // Voting
        Route::post('/threads/{threadId}/vote', [ForumController::class, 'voteThread']);
        Route::post('/posts/{postId}/vote', [ForumController::class, 'votePost']);
    });
    
    // News Comments and Voting
    Route::prefix('news')->group(function () {
        Route::post('/{newsId}/comments', [NewsController::class, 'comment']);
        Route::post('/{newsId}/vote', [NewsController::class, 'vote']);
        Route::put('/comments/{commentId}', [NewsController::class, 'updateComment']);
        Route::delete('/comments/{commentId}', [NewsController::class, 'destroyComment']);
        Route::post('/comments/{commentId}/vote', [NewsController::class, 'voteComment']);
    });
    
    // Match Comments
    Route::prefix('matches')->group(function () {
        Route::post('/{matchId}/comments', [MatchController::class, 'storeComment']);
        Route::put('/comments/{commentId}', [MatchController::class, 'updateComment']);
        Route::delete('/comments/{commentId}', [MatchController::class, 'destroyComment']);
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
    
    // Admin Statistics
    Route::get('/stats', [AdminStatsController::class, 'index']);
    Route::get('/analytics', [AdminStatsController::class, 'analytics']);
    
    // Bulk Operations
    Route::prefix('bulk')->group(function () {
        Route::post('/{type}/delete', [BulkOperationController::class, 'bulkDelete']);
        Route::post('/{type}/update', [BulkOperationController::class, 'bulkUpdate']);
        Route::post('/{type}/archive', [BulkOperationController::class, 'bulkArchive']);
        Route::post('/{type}/activate', [BulkOperationController::class, 'bulkActivate']);
        Route::post('/{type}/deactivate', [BulkOperationController::class, 'bulkDeactivate']);
    });
    
    // User Management - Full CRUD
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'getAllUsers']);
        Route::post('/', [AdminUserController::class, 'createUser']);
        Route::get('/{userId}', [AdminUserController::class, 'getUser']);
        Route::put('/{userId}', [AdminUserController::class, 'updateUser']);
        Route::delete('/{userId}', [AdminUserController::class, 'deleteUser']);
        Route::post('/{userId}/roles', [AuthController::class, 'assignUserRoles']);
        Route::delete('/{userId}/roles/{roleId}', [AuthController::class, 'removeUserRole']);
        Route::get('/{userId}/activity', [AdminUserController::class, 'getUserActivity']);
        Route::post('/{userId}/reset-password', [AdminUserController::class, 'resetUserPassword']);
        Route::post('/{userId}/toggle-ban', [AdminUserController::class, 'toggleBan']);
        Route::post('/{userId}/upload-avatar', [AdminUserController::class, 'uploadUserAvatar']);
        Route::delete('/{userId}/remove-avatar', [AdminUserController::class, 'removeUserAvatar']);
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
        Route::post('/{teamId}/flag', [ImageUploadController::class, 'uploadTeamFlag']);
        Route::delete('/{teamId}/logo', [ImageUploadController::class, 'deleteTeamLogo']);
        Route::delete('/{teamId}/banner', [ImageUploadController::class, 'deleteTeamBanner']);
        Route::delete('/{teamId}/flag', [ImageUploadController::class, 'deleteTeamFlag']);
    });
    
    // Player Management - Full CRUD
    Route::prefix('players')->group(function () {
        Route::get('/', [PlayerController::class, 'getAllPlayers']);
        Route::post('/', [PlayerController::class, 'store']);
        Route::get('/{playerId}', [PlayerController::class, 'getPlayerAdmin']);
        Route::put('/{playerId}', [PlayerController::class, 'update']);
        Route::delete('/{playerId}', [PlayerController::class, 'destroy']);
        
        // Player Stats Management
        Route::put('/{playerId}/stats', [PlayerController::class, 'updateStats']);
        Route::post('/{playerId}/achievements', [PlayerController::class, 'addAchievement']);
        Route::delete('/{playerId}/achievements/{achievementId}', [PlayerController::class, 'removeAchievement']);
        
        // Player Images
        Route::post('/{playerId}/avatar', [ImageUploadController::class, 'uploadPlayerAvatar']);
        Route::delete('/{playerId}/avatar', [ImageUploadController::class, 'deletePlayerAvatar']);
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
        
        // Admin direct team management (no authorization checks)
        Route::post('/{eventId}/teams', [EventController::class, 'adminAddTeamToEvent']);
        Route::delete('/{eventId}/teams/{teamId}/admin', [EventController::class, 'adminRemoveTeamFromEvent']);
        
        // Bracket Management - Enhanced
        Route::post('/{eventId}/generate-bracket', [BracketController::class, 'generate']);
        Route::put('/{eventId}/bracket/matches/{matchId}', [BracketController::class, 'updateMatch']);
        
        // Comprehensive Bracket Management
        Route::post('/{eventId}/comprehensive-bracket', [ComprehensiveBracketController::class, 'generate']);
        Route::put('/{eventId}/comprehensive-bracket/matches/{matchId}', [ComprehensiveBracketController::class, 'updateMatch']);
        Route::post('/{eventId}/swiss/next-round', [ComprehensiveBracketController::class, 'generateNextSwissRound']);
        
        // Event Images
        Route::post('/{eventId}/logo', [ImageUploadController::class, 'uploadEventLogo']);
        Route::post('/{eventId}/banner', [ImageUploadController::class, 'uploadEventBanner']);
        Route::delete('/{eventId}/banner', [ImageUploadController::class, 'deleteEventBanner']);
    });
    
    // Match Management - Full CRUD
    Route::prefix('matches')->group(function () {
        Route::get('/', [MatchController::class, 'getAllMatches']);
        Route::post('/', [MatchController::class, 'store']);
        Route::get('/{matchId}', [MatchController::class, 'getMatchAdmin']);
        Route::put('/{matchId}', [MatchController::class, 'update']);
        Route::put('/{matchId}/complete-update', [MatchController::class, 'update']);
        Route::delete('/{matchId}', [MatchController::class, 'destroy']);
        
        // Match Stats Management
        Route::post('/{matchId}/stats/bulk', [MatchController::class, 'bulkUpdateStats']);
        Route::put('/{matchId}/players/{playerId}/stats', [MatchController::class, 'updatePlayerStats']);
        Route::post('/{matchId}/events', [MatchController::class, 'addMatchEvent']);
        Route::put('/{matchId}/live-data', [MatchController::class, 'updateLiveData']);
        
        // Live Scoring Management
        Route::post('/{matchId}/live-scoring', [AdminMatchController::class, 'updateLiveScoring']);
        Route::post('/{matchId}/control', [AdminMatchController::class, 'controlMatch']);
        
        // Hero Selection Updates
        Route::post('/{matchId}/hero-update', [MatchController::class, 'updateHeroSelection']);
        
        // Enhanced Live Scoring Routes
        Route::post('/{matchId}/maps/{mapNumber}/start', [MatchController::class, 'startMap']);
        Route::post('/{matchId}/maps/{mapNumber}/end', [MatchController::class, 'endMap']);
        Route::post('/{matchId}/kill-event', [MatchController::class, 'addKillEvent']);
        Route::put('/{matchId}/maps/{mapNumber}/objective', [MatchController::class, 'updateObjective']);
        Route::post('/{matchId}/start', [MatchController::class, 'startMatch']);
        Route::post('/{matchId}/restart', [MatchController::class, 'restartMatch']);
        Route::post('/{matchId}/complete', [MatchController::class, 'completeMatch']);
        Route::delete('/{matchId}', [MatchController::class, 'deleteMatch']);
        Route::post('/{matchId}/transition-map', [MatchController::class, 'transitionToNextMap']);
        Route::put('/{matchId}/live-score', [MatchController::class, 'updateLiveScore']);
        Route::put('/{matchId}/live-timer', [MatchController::class, 'updateLiveTimer']);
        
        // Comprehensive live control endpoint
        Route::post('/{matchId}/live-control', [MatchController::class, 'liveControl']);
        
        // Live scoreboard endpoint for frontend synchronization
        Route::get('/{matchId}/live-scoreboard', [MatchController::class, 'liveScoreboard']);
    });
    
    // News Management - Full CRUD
    Route::prefix('news')->group(function () {
        Route::get('/', [NewsController::class, 'adminIndex']);
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
        
        // Threads Management & Moderation
        Route::get('/threads', [ForumController::class, 'getAllThreadsForModeration']);
        Route::put('/threads/{threadId}', [ForumController::class, 'updateThreadAdmin']);
        Route::post('/threads/{threadId}/delete', [ForumController::class, 'deleteThread']);
        Route::delete('/threads/{threadId}/force', [ForumController::class, 'forceDeleteThread']);
        Route::post('/threads/{threadId}/pin', [ForumController::class, 'pinThread']);
        Route::post('/threads/{threadId}/unpin', [ForumController::class, 'unpinThread']);
        Route::post('/threads/{threadId}/lock', [ForumController::class, 'lockThread']);
        Route::post('/threads/{threadId}/unlock', [ForumController::class, 'unlockThread']);
        Route::post('/threads/{threadId}/feature', [ForumController::class, 'featureThread']);
        Route::post('/threads/{threadId}/unfeature', [ForumController::class, 'unfeatureThread']);
        
        // Posts Moderation
        Route::get('/posts', [ForumController::class, 'getAllPostsForModeration']);
        Route::post('/posts/{id}/delete', [ForumController::class, 'deletePost']);
        
        // Reports Management
        Route::get('/reports', [ForumController::class, 'getAllForumReports']);
        Route::post('/reports/{id}/resolve', [ForumController::class, 'resolveReport']);
        Route::post('/reports/{id}/dismiss', [ForumController::class, 'dismissReport']);
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
    
    // Admin Dashboard Routes
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/live-scoring', [AdminController::class, 'liveScoring']);
    Route::get('/live-scoring/match/{matchId}', [AdminController::class, 'getLiveScoringMatch']);
    Route::get('/content-moderation', [AdminController::class, 'contentModeration']);
    Route::get('/user-management', [AdminController::class, 'userManagement']);
    Route::get('/system-settings', [AdminController::class, 'systemSettings']);
    Route::get('/analytics-dashboard', [AdminController::class, 'analytics']);
    Route::post('/clear-cache', [AdminController::class, 'clearCache']);
    Route::post('/maintenance-mode', [AdminController::class, 'toggleMaintenanceMode']);
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

// ===================================
// AUTHENTICATED USER INFO ROUTES
// ===================================
Route::middleware('auth:api')->get('/user', function (Request $request) {
    $user = $request->user();
    $user->load('teamFlair');
    
    return response()->json([
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'hero_flair' => $user->hero_flair,
            'team_flair' => $user->teamFlair,
            'show_hero_flair' => $user->show_hero_flair,
            'show_team_flair' => $user->show_team_flair,
            'status' => $user->status,
            'last_login' => $user->last_login,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')
        ],
        'success' => true
    ]);
});

// Test Data Routes (Non-production only)
if (!app()->environment('production')) {
    Route::prefix('test')->group(function () {
        Route::post('/matches', [TestDataController::class, 'createTestMatches']);
        Route::post('/matches/{match}/simulate', [TestDataController::class, 'simulateLiveUpdate']);
        Route::get('/matches/{match}/data', [TestDataController::class, 'getLiveMatchData']);
    });
}

// Test routes for role verification
Route::middleware(['auth:api', 'role:admin'])->get('/test-admin', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Admin access confirmed',
        'user' => $request->user()->name,
        'roles' => $request->user()->getRoleNames()
    ]);
});

Route::middleware(['auth:api', 'role:moderator'])->get('/test-moderator', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Moderator access confirmed',
        'user' => $request->user()->name,
        'roles' => $request->user()->getRoleNames()
    ]);
});

Route::middleware(['auth:api', 'role:user'])->get('/test-user', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'User access confirmed',
        'user' => $request->user()->name,
        'roles' => $request->user()->getRoleNames()
    ]);
});

// System test route
Route::get('/system-test', [\App\Http\Controllers\SystemTestController::class, 'testAllSystems']);

// Upload route aliases for frontend compatibility
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::post('/upload/team/{teamId}/logo', [ImageUploadController::class, 'uploadTeamLogo']);
    Route::post('/upload/team/{teamId}/banner', [ImageUploadController::class, 'uploadTeamBanner']);
    Route::post('/upload/player/{playerId}/avatar', [ImageUploadController::class, 'uploadPlayerAvatar']);
});

// Forum test route
Route::get('/test-forum', function() {
    return response()->json([
        'success' => true,
        'tables' => [
            'forum_threads' => Schema::hasTable('forum_threads'),
            'forum_posts' => Schema::hasTable('forum_posts'),
            'forum_votes' => Schema::hasTable('forum_votes'),
            'forum_categories' => Schema::hasTable('forum_categories')
        ],
        'thread_count' => DB::table('forum_threads')->count(),
        'post_count' => DB::table('forum_posts')->count(),
        'vote_count' => DB::table('forum_votes')->count()
    ]);
});

// Temporary direct event/match creation routes (bypass admin middleware for testing)
Route::middleware('auth:api')->group(function() {
    Route::post('/create-event-direct', [EventController::class, 'createEventDirect']);
    Route::post('/create-match-direct', [MatchController::class, 'createMatchDirect']);
});
// Test auth endpoint
Route::middleware('auth:api')->get('/test-auth', function () {
    $user = auth('api')->user();
    return response()->json([
        'success' => true,
        'message' => 'Authentication working',
        'user_id' => $user->id,
        'user_name' => $user->name,
        'is_admin' => $user->hasRole(['admin', 'super_admin'])
    ]);
});

// ===================================
// MATCH INGESTION SERVICE
// ===================================
Route::prefix('ingestion')->group(function () {
    // Health check endpoint (public)
    Route::get('/health', [App\Http\Controllers\MatchIngestionController::class, 'healthCheck']);
    
    // Match report ingestion (requires authentication)
    Route::middleware('auth:api')->group(function () {
        Route::post('/matches', [App\Http\Controllers\MatchIngestionController::class, 'ingestMatchReport']);
        Route::get('/status/{requestId}', [App\Http\Controllers\MatchIngestionController::class, 'getIngestionStatus']);
    });
});
