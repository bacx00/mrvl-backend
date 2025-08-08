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
    AnalyticsController,
    AdminUserController,
    AdminMatchController,
    LiveUpdateController,
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
    VoteController,
    ImageTestController
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
    Route::middleware('auth:api')->get('/user', [AuthController::class, 'user']);
    Route::middleware('auth:api')->post('/refresh', [AuthController::class, 'refresh']);
});

// ===================================
// PUBLIC ROUTES (No Authentication Required)
// ===================================
Route::prefix('public')->group(function () {
    // Teams
    Route::get('/teams', [TeamController::class, 'index']);
    Route::get('/teams/{id}', [TeamController::class, 'show']);
    Route::get('/teams/{id}/achievements', [TeamController::class, 'getAchievements']);
    
    // Players
    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/players/{id}', [PlayerController::class, 'show']);
    
    // New Player Profile Endpoints
    Route::get('/players/{id}/team-history', [PlayerController::class, 'getTeamHistory']);
    Route::get('/players/{id}/matches', [PlayerController::class, 'getMatches']);
    Route::get('/players/{id}/stats', [PlayerController::class, 'getStats']);
    
    // Player Statistics (Legacy - for backward compatibility)
    Route::get('/players/{player}/match-history', [PlayerController::class, 'getMatchHistory']);
    Route::get('/players/{player}/hero-stats', [PlayerController::class, 'getHeroStats']);
    Route::get('/players/{player}/performance-stats', [PlayerController::class, 'getPerformanceStats']);
    Route::get('/players/{player}/map-stats', [PlayerController::class, 'getMapStats']);
    Route::get('/players/{player}/event-stats', [PlayerController::class, 'getEventStats']);
    
    // Events - Using main event controller
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    
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
    Route::get('/forums/search', [SearchController::class, 'search']);
    Route::get('/forums/search/suggestions', [SearchController::class, 'searchSuggestions']);
    Route::get('/forums/trending', [ForumController::class, 'getTrendingThreads']);
    Route::get('/forums/hot', [ForumController::class, 'getHotThreads']);
    Route::get('/forums/overview', [ForumController::class, 'getForumOverview']);
    
    // Forum thread existence check (for 404 prevention)
    Route::get('/forums/threads/{id}/exists', [ForumController::class, 'checkThreadExists']);
    
    // News (Read Only)
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/categories', [NewsController::class, 'getCategories']);
    Route::get('/news/{id}', [NewsController::class, 'show']);
    
    // News View Tracking (no auth required for view tracking)
    Route::post('/news/{id}/view', [NewsController::class, 'trackView']);
    
    // News Comments and Voting (require authentication)
    Route::middleware('auth:api')->group(function () {
        Route::post('/news/{newsId}/comments', [NewsController::class, 'comment']);
        Route::post('/news/{newsId}/vote', [NewsController::class, 'vote']);
        Route::put('/news/comments/{commentId}', [NewsController::class, 'updateComment']);
        Route::delete('/news/comments/{commentId}', [NewsController::class, 'destroyComment']);
        Route::post('/news/comments/{commentId}/vote', [NewsController::class, 'voteComment']);
    });
    
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
    
    // Comprehensive Bracket System - Public Access
    Route::get('/tournaments/{tournamentId}/bracket', [ComprehensiveBracketController::class, 'getBracket']);
    Route::get('/events/{eventId}/bracket-comprehensive', [ComprehensiveBracketController::class, 'getBracket']);
    Route::get('/tournaments/{tournamentId}/bracket-visualization', [ComprehensiveBracketController::class, 'getBracketVisualization']);
    Route::get('/events/{eventId}/bracket-visualization', [ComprehensiveBracketController::class, 'getBracketVisualization']);
    Route::get('/tournaments/{tournamentId}/standings', [ComprehensiveBracketController::class, 'getStandings']);
    Route::get('/events/{eventId}/standings', [ComprehensiveBracketController::class, 'getStandings']);
    Route::get('/tournaments/{tournamentId}/swiss-standings', [ComprehensiveBracketController::class, 'getSwissStandings']);
    Route::get('/events/{eventId}/swiss-standings', [ComprehensiveBracketController::class, 'getSwissStandings']);
    Route::get('/live-matches', [ComprehensiveBracketController::class, 'getLiveMatches']);
    
    // Search
    Route::get('/search', [SearchController::class, 'search']);
    
    // Search for mentions (public access for autocomplete)
    Route::get('/search/users', [SearchController::class, 'searchUsersForMentions']);
    Route::get('/search/teams', [SearchController::class, 'searchTeamsForMentions']);
    Route::get('/search/players', [SearchController::class, 'searchPlayersForMentions']);
    
    // Mentions autocomplete (public access for better UX)
    Route::get('/mentions/search', [MentionController::class, 'searchMentions']);
    Route::get('/mentions/popular', [MentionController::class, 'getPopularMentions']);
    
    // Public mentions routes for consistent API
    Route::get('/public/mentions/search', [MentionController::class, 'searchMentions']);
    Route::get('/public/mentions/popular', [MentionController::class, 'getPopularMentions']);
    
    // Public user profiles
    Route::get('/users/{userId}/profile', [UserProfileController::class, 'getUserWithAvatarAndFlairs']);
});

// News view tracking (legacy compatibility)
Route::post('/news/{news}/view', [NewsController::class, 'trackView']);

// Legacy public routes (for compatibility)
Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
Route::get('/teams/{team}/mentions', [TeamController::class, 'getMentions']);

// Team match-related endpoints
Route::get('/teams/{team}/matches/upcoming', [TeamController::class, 'getUpcomingMatches']);
Route::get('/teams/{team}/matches/live', [TeamController::class, 'getLiveMatches']);
Route::get('/teams/{team}/matches/recent', [TeamController::class, 'getRecentResults']);
Route::get('/teams/{team}/matches/stats', [TeamController::class, 'getMatchStats']);
Route::get('/teams/logos/test', [TeamController::class, 'testTeamLogos']);
Route::get('/teams/logos/all', [TeamController::class, 'getAllTeamLogos']);
Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{player}', [PlayerController::class, 'show']);
Route::get('/players/{player}/mentions', [PlayerController::class, 'getMentions']);
Route::get('/players/{player}/match-history', [PlayerController::class, 'getMatchHistory']);
Route::get('/players/{player}/hero-stats', [PlayerController::class, 'getHeroStats']);
Route::get('/players/{player}/performance-stats', [PlayerController::class, 'getPerformanceStats']);
Route::get('/players/{player}/hero-performance', [PlayerController::class, 'getHeroPerformance']);
Route::get('/players/{player}/map-stats', [PlayerController::class, 'getMapStats']);
Route::get('/players/{player}/event-stats', [PlayerController::class, 'getEventStats']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/matches', [MatchController::class, 'index']);
Route::get('/matches/live', [MatchController::class, 'live']);
Route::get('/matches/{match}', [MatchController::class, 'show']);
Route::get('/matches/{match}/live-scoreboard', [MatchController::class, 'liveScoreboard']);
Route::get('/matches/{match}/comments', [MatchController::class, 'getComments']);
Route::middleware('auth:api')->post('/matches/{match}/comments', [MatchController::class, 'storeComment']);
Route::get('/matches/{match}/timeline', [MatchController::class, 'getMatchTimeline']);
Route::get('/matches/head-to-head/{team1Id}/{team2Id}', [MatchController::class, 'getHeadToHead']);

// Live Updates SSE Stream (No auth required for public viewing)
Route::get('/live-updates/{matchId}/stream', [LiveUpdateController::class, 'stream']);

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/categories', [NewsController::class, 'getCategories']);
Route::get('/news/{news}', [NewsController::class, 'show']);
Route::get('/news/{newsId}/comments', [NewsController::class, 'getCommentsWithNesting']);

// Missing direct news comment routes for frontend compatibility
Route::middleware('auth:api')->group(function () {
    Route::post('/news/{newsId}/comments', [NewsController::class, 'comment']);
    Route::put('/news/comments/{commentId}', [NewsController::class, 'updateComment']);
    Route::delete('/news/comments/{commentId}', [NewsController::class, 'destroyComment']);
    Route::post('/news/comments/{commentId}/vote', [NewsController::class, 'voteComment']);
    Route::post('/news/{newsId}/vote', [NewsController::class, 'vote']);
});

// Heroes routes (for frontend compatibility with direct /api/heroes path)
Route::get('/heroes', [HeroController::class, 'index']);
Route::get('/heroes/roles', [HeroController::class, 'getRoles']);
Route::get('/heroes/season-2', [HeroController::class, 'getSeasonTwoHeroes']);
Route::get('/heroes/images', [HeroController::class, 'getHeroImages']);
Route::get('/heroes/images/all', [HeroController::class, 'getAllHeroImages']);
Route::get('/heroes/images/{slug}', [HeroController::class, 'getHeroImageBySlug']);
Route::get('/heroes/{slug}', [HeroController::class, 'show']);

// Forum routes (for frontend compatibility)
Route::get('/forums/categories', [ForumController::class, 'getCategories']);
Route::get('/forums/threads', [ForumController::class, 'index']);
Route::get('/forums/threads/{id}', [ForumController::class, 'show']);
Route::get('/forums/threads/{id}/posts', [ForumController::class, 'getPosts']);
Route::get('/forums/overview', [ForumController::class, 'getForumOverview']);

// Mention routes (for frontend compatibility - matches expected API calls)
Route::get('/mentions/search', [MentionController::class, 'searchMentions']);
Route::get('/mentions/popular', [MentionController::class, 'getPopularMentions']);

// Search for mentions (legacy route compatibility - moved to public section above)

// FIXED: Add voting compatibility routes that frontend expects
Route::middleware('auth:api')->group(function () {
    Route::post('/forums/threads/{threadId}/vote', [ForumController::class, 'voteThread']);
    Route::post('/forums/posts/{postId}/vote', [ForumController::class, 'votePost']);
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
        
        // Specific voting routes for easier frontend integration
        Route::post('/news/{newsId}', [VoteController::class, 'voteNews']);
        Route::post('/news/{newsId}/comments/{commentId}', [VoteController::class, 'voteNewsComment']);
        Route::post('/forums/threads/{threadId}', [VoteController::class, 'voteThread']);
        Route::post('/forums/posts/{postId}', [VoteController::class, 'votePost']);
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
    
    // Forum Engagement Stats
    Route::get('/forums/engagement-stats/{userId?}', [ForumController::class, 'getUserEngagementStats']);
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
    
    // Moderator Dashboard & Analytics
    Route::get('/dashboard/stats', [AdminStatsController::class, 'getModeratorStats']);
    Route::get('/dashboard/recent-activity', [AdminStatsController::class, 'getRecentModerationActivity']);
    
    // Moderator Analytics - Limited Access
    Route::prefix('analytics')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index']); // Role-based analytics (moderator gets limited view)
        Route::get('/moderation', [AdminStatsController::class, 'analytics']); // Moderation-focused analytics
    });
});

// ===================================
// ADMIN ROUTES (🔴 Admin Role - Full Access)
// ===================================
Route::middleware(['auth:api', 'role:admin,moderator'])->prefix('admin')->group(function () {
    
    // Core Admin Endpoints - Available to both admin and moderator
    Route::get('/stats', [AdminStatsController::class, 'index']);
    Route::get('/analytics', [AdminStatsController::class, 'analytics']);
    
    // Resource Management
    Route::get('/teams', [TeamController::class, 'index']);
    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/matches', [MatchController::class, 'index']);
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/news', [NewsController::class, 'index']);
    
    // Admin-only routes (checked internally)
    Route::get('/users', [AdminUserController::class, 'getAllUsers']);
});

Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
    
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
        
        // Advanced Bracket Management - New System
        Route::post('/{eventId}/bracket/generate', [ComprehensiveBracketController::class, 'generateBracket']);
        Route::put('/bracket/matches/{matchId}', [ComprehensiveBracketController::class, 'updateMatch']);
        Route::put('/bracket/matches/{matchId}/games/{gameNumber}', [ComprehensiveBracketController::class, 'updateGame']);
        Route::post('/bracket/matches/{matchId}/reset-bracket', [ComprehensiveBracketController::class, 'resetBracket']);
        Route::post('/{eventId}/swiss/generate-round', [ComprehensiveBracketController::class, 'generateSwissRound']);
        
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
        Route::put('/{matchId}/complete-update', [MatchController::class, 'completeUpdate']);
        Route::delete('/{matchId}', [MatchController::class, 'destroy']);
        
        // Match Stats Management
        Route::post('/{matchId}/stats/bulk', [MatchController::class, 'bulkUpdateStats']);
        Route::put('/{matchId}/players/{playerId}/stats', [MatchController::class, 'updatePlayerStats']);
        Route::post('/{matchId}/events', [MatchController::class, 'addMatchEvent']);
        Route::put('/{matchId}/live-data', [MatchController::class, 'updateLiveData']);
        
        // Live Scoring Management
        Route::post('/{matchId}/live-scoring', [AdminMatchController::class, 'updateLiveScoring']);
        Route::post('/{matchId}/control', [AdminMatchController::class, 'controlMatch']);
        
        // Real-Time Live Scoring API Endpoints
        Route::post('/{matchId}/live-update', [MatchController::class, 'liveUpdate']);
        Route::post('/{matchId}/player-stats', [MatchController::class, 'playerStatsUpdate']);
        Route::post('/{matchId}/hero-update', [MatchController::class, 'heroUpdate']);
        Route::post('/{matchId}/map-result', [MatchController::class, 'mapResult']);

        // Simple Real-Time Scoring Synchronization (API calls only)
        Route::post('/{matchId}/update-score', [MatchController::class, 'updateScore']);
        Route::post('/{matchId}/update-player-stats', [MatchController::class, 'updatePlayerStatsSimple']);
        Route::post('/{matchId}/update-live-stats', [MatchController::class, 'updateLiveStatsComprehensive']);
        Route::post('/{matchId}/team-wins-map', [MatchController::class, 'teamWinsMap']);
        Route::post('/{matchId}/update-heroes', [MatchController::class, 'updateHeroes']);
        Route::get('/{matchId}/live-data', [MatchController::class, 'getLiveData']);
        
        // Legacy Hero Selection Updates (for compatibility)
        Route::post('/{matchId}/hero-update-legacy', [MatchController::class, 'updateHeroSelection']);
        
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
        // FIXED: Add direct DELETE route for admin thread deletion compatibility
        Route::delete('/threads/{threadId}', [ForumController::class, 'deleteThread']);
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
    
    // Analytics - Admin Only (Full System Access)
    Route::prefix('analytics')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index']); // Role-based analytics endpoint
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
            'role' => $user->role ?? 'user',
            'roles' => [$user->role ?? 'user'], // For frontend compatibility
            'role_display_name' => $user->getRoleDisplayName(),
            'avatar' => $user->avatar,
            'hero_flair' => $user->hero_flair,
            'team_flair' => $user->teamFlair,
            'team_flair_id' => $user->team_flair_id,
            'show_hero_flair' => (bool)$user->show_hero_flair,
            'show_team_flair' => (bool)$user->show_team_flair,
            'use_hero_as_avatar' => (bool)$user->use_hero_as_avatar,
            'status' => $user->status,
            'last_login' => $user->last_login,
            'spatie_roles' => $user->getRoleNames(), // Keep Spatie roles for compatibility
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'created_at' => $user->created_at->toISOString()
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

// ===================================
// OPTIMIZED MATCH SCORING API V2
// ===================================
Route::prefix('v2/matches')->group(function () {
    // Public endpoints
    Route::get('/{matchId}', [\App\Http\Controllers\OptimizedMatchController::class, 'show']);
    Route::get('/{matchId}/live', [\App\Http\Controllers\OptimizedMatchController::class, 'getLiveData']);
    
    // Admin/authenticated endpoints for score updates
    Route::middleware('auth:api')->group(function () {
        Route::post('/{matchId}/scores', [\App\Http\Controllers\OptimizedMatchController::class, 'updateMatchScore']);
        Route::post('/{matchId}/maps/{mapNumber}/scores', [\App\Http\Controllers\OptimizedMatchController::class, 'updateMapScore']);
    });
});

// ===================================
// IMAGE TESTING ROUTES
// ===================================
Route::prefix('images')->group(function () {
    Route::get('/test-all', [ImageTestController::class, 'testAllImages']);
    Route::get('/test-url', [ImageTestController::class, 'testImageUrl']);
});
