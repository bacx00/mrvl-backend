<?php


use App\Http\Controllers\{
    AuthController,
    TeamController,
    Admin\AdminTeamController,
    PlayerController,
    MatchController,
    MatchCommentController,
    EventController,
    EventControllerTemp,
    SearchController,
    ForumController,
    AdminStatsController,
    AnalyticsController,
    PlayerAnalyticsController,
    TeamAnalyticsController,
    HeroAnalyticsController,
    RealTimeAnalyticsController,
    UserActivityController,
    ResourceAnalyticsController,
    AdminUserController,
    AdminMatchController,
    LiveUpdateController,
    NewsController,
    ImageUploadController,
    BracketController,
    ComprehensiveBracketController,
    RankingController,
    TeamRankingController,
    HeroController,
    UserProfileController,
    GameDataController,
    AdminController,
    OptimizedAdminController,
    BulkOperationController,
    MentionController,
    TestDataController,
    VoteController,
    AchievementController,
    ChallengeController,
    LeaderboardController,
    StreakController,
    UserTitleController,
    AchievementNotificationController,
    TournamentController,
    TournamentPhaseController,
    TournamentRegistrationController,
    TournamentBracketController,
    TournamentMatchController,
    PlayerMatchHistoryController,
    TournamentProgressionController,
    TournamentAnalyticsController,
    TournamentSettingsController,
    SwissController,
    TournamentBroadcastController
};
use App\Services\MatchAnalyticsService;

use App\Http\Controllers\Admin\AdminTournamentController;
use App\Http\Controllers\Admin\AdminForumsController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Admin\AdminMatchesController;
use App\Http\Controllers\Admin\AdminEventsController;
use App\Http\Controllers\Api\TwoFactorController;
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
    // Temporarily removed middleware for testing - TODO: Fix middleware registration
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLinkEmail']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware('auth:api')->post('/logout', [AuthController::class, 'logout']);
    Route::middleware('auth:api')->get('/me', [AuthController::class, 'me']);
    Route::middleware('auth:api')->get('/user', [AuthController::class, 'user']);
    Route::middleware('auth:api')->post('/refresh', [AuthController::class, 'refresh']);
    
    // 2FA Login Flow routes (no auth required - use temp tokens)
    Route::post('/2fa/verify-login', [AuthController::class, 'verify2FALogin']);
    Route::post('/2fa/setup-login', [AuthController::class, 'setup2FALogin']);
    Route::post('/2fa/enable-login', [AuthController::class, 'enable2FALogin']);
    
    // Two-Factor Authentication routes (authenticated users)
    Route::middleware('auth:api')->prefix('2fa')->group(function () {
        Route::get('/status', [TwoFactorController::class, 'status']);
        Route::get('/needs-verification', [TwoFactorController::class, 'needsVerification']);
        Route::post('/setup', [TwoFactorController::class, 'setup']);
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
        Route::post('/verify', [TwoFactorController::class, 'verify']);
        Route::get('/recovery-codes', [TwoFactorController::class, 'getRecoveryCodes']);
        Route::post('/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    });
});

// ===================================
// ANALYTICS ROUTES (For tracking user activity)
// ===================================
Route::prefix('analytics')->group(function () {
    Route::post('/events', function (Request $request) {
        // Simple analytics endpoint that accepts events but doesn't require processing
        return response()->json(['success' => true, 'message' => 'Event tracked']);
    });
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
    Route::get('/players/{id}/matches', [PlayerMatchHistoryController::class, 'getPlayerMatches']); // Use correct controller
    Route::get('/player-profile/{id}', [PlayerMatchHistoryController::class, 'getPlayerProfile']);
    Route::get('/match/{id}/player-stats', [PlayerMatchHistoryController::class, 'getMatchPlayerStats']);
    
    // New Player Profile Endpoints
    Route::get('/players/{id}/team-history', [PlayerController::class, 'getTeamHistory']);
    // Route removed - was duplicate: Route::get('/players/{id}/matches', [PlayerController::class, 'getMatches']);
    Route::get('/players/{id}/stats', [PlayerController::class, 'getStats']);
    
    // Player Statistics (Legacy - for backward compatibility)
    Route::get('/players/{player}/match-history', [PlayerController::class, 'getMatchHistory']);
    Route::get('/players/{player}/detailed-match-history', [PlayerController::class, 'getDetailedMatchHistory']);
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
    Route::get('/rankings/distribution', [RankingController::class, 'getRankDistribution']);
    Route::get('/rankings/marvel-rivals-info', [RankingController::class, 'getMarvelRivalsInfo']);
    Route::get('/rankings/{id}', [RankingController::class, 'show']);
    
    // Team Rankings
    Route::get('/team-rankings', [TeamRankingController::class, 'index']);
    Route::get('/team-rankings/top-earners', [TeamRankingController::class, 'getTopEarners']);
    Route::get('/team-rankings/{id}', [TeamRankingController::class, 'show']);
    
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
    
    // Manual Bracket Public Views
    Route::get('/manual-bracket/{stageId}', [\App\Http\Controllers\ManualBracketController::class, 'getBracket']);
    Route::get('/manual-bracket/formats', [\App\Http\Controllers\ManualBracketController::class, 'getFormats']);

    // ===================================
    // COMPREHENSIVE TOURNAMENT SYSTEM API
    // ===================================
    
    // Tournament Public Routes
    Route::prefix('tournaments')->group(function () {
        // Public tournament browsing
        Route::get('/', [\App\Http\Controllers\TournamentController::class, 'index']);
        Route::get('/{tournament}', [\App\Http\Controllers\TournamentController::class, 'show']);
        Route::get('/{tournament}/standings', [\App\Http\Controllers\TournamentController::class, 'standings']);
        Route::get('/{tournament}/bracket', [\App\Http\Controllers\TournamentController::class, 'bracket']);
        
        // Tournament Phase Information
        Route::get('/{tournament}/phases', [\App\Http\Controllers\TournamentPhaseController::class, 'index']);
        Route::get('/{tournament}/phases/{phase}', [\App\Http\Controllers\TournamentPhaseController::class, 'show']);
        Route::get('/{tournament}/phases/{phase}/matches', [\App\Http\Controllers\TournamentPhaseController::class, 'getMatches']);
        
        // Registration Information (public)
        Route::get('/{tournament}/registrations', [\App\Http\Controllers\TournamentRegistrationController::class, 'index']);
        Route::get('/{tournament}/registrations/stats', [\App\Http\Controllers\TournamentRegistrationController::class, 'getStats']);
        
        // Swiss System Public Data
        Route::get('/{tournament}/swiss/standings', [\App\Http\Controllers\SwissController::class, 'getStandings']);
        Route::get('/{tournament}/swiss/stats', [\App\Http\Controllers\SwissController::class, 'getStats']);
        Route::get('/{tournament}/swiss/pairings/{round}', [\App\Http\Controllers\SwissController::class, 'getRoundPairings']);
    });
    
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
Route::get('/teams/{team}/coach', [TeamController::class, 'getCoach']);
Route::put('/teams/{team}/coach', [TeamController::class, 'updateCoach']);
Route::post('/teams/{team}/coach/image', [TeamController::class, 'uploadCoachImage']);
Route::get('/teams/logos/test', [TeamController::class, 'testTeamLogos']);
Route::get('/teams/logos/all', [TeamController::class, 'getAllTeamLogos']);
Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/free-agents', [PlayerController::class, 'getFreeAgents']);
Route::get('/players/{player}', [PlayerController::class, 'show']);
Route::get('/players/{player}/mentions', [PlayerController::class, 'getMentions']);
Route::get('/players/{player}/transfer-history', [PlayerController::class, 'getTransferHistory']);
Route::post('/players/{player}/transfer', [PlayerController::class, 'recordTransfer']);
Route::get('/players/{player}/match-history', [PlayerController::class, 'getMatchHistory']);
Route::get('/players/{player}/hero-stats', [PlayerController::class, 'getHeroStats']);
Route::get('/players/{player}/performance-stats', [PlayerController::class, 'getPerformanceStats']);
Route::get('/players/{player}/hero-performance', [PlayerController::class, 'getHeroPerformance']);
Route::get('/players/{player}/map-stats', [PlayerController::class, 'getMapStats']);
Route::get('/players/{player}/event-stats', [PlayerController::class, 'getEventStats']);

// User Profile Routes (Public viewing)
Route::get('/users/{userId}', [UserProfileController::class, 'getUserWithDetails']);
Route::get('/users/{userId}/stats', [UserProfileController::class, 'getUserStatsPublic']);
Route::get('/users/{userId}/activities', [UserProfileController::class, 'getUserActivities']);
Route::get('/users/{userId}/achievements', [UserProfileController::class, 'getUserAchievements']);
Route::get('/users/{userId}/forum-stats', [UserProfileController::class, 'getUserForumStats']);
Route::get('/users/{userId}/matches', [UserProfileController::class, 'getUserMatches']);

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/matches', [MatchController::class, 'index']);
Route::get('/matches/live', [MatchController::class, 'live']);
Route::get('/matches/{match}', [MatchController::class, 'show']);
Route::get('/matches/{match}/live-scoreboard', [MatchController::class, 'liveScoreboard']);
// Match Comments - Forum-style with voting and nested replies
Route::get('/matches/{match}/comments', [MatchCommentController::class, 'index']);
Route::middleware('auth:api')->group(function () {
    Route::post('/matches/{match}/comments', [MatchCommentController::class, 'store']);
    Route::put('/match-comments/{comment}', [MatchCommentController::class, 'update']);
    Route::delete('/match-comments/{comment}', [MatchCommentController::class, 'destroy']);
    Route::post('/match-comments/{comment}/vote', [MatchCommentController::class, 'vote']);
    Route::post('/match-comments/{comment}/flag', [MatchCommentController::class, 'flag']);
});
Route::get('/matches/{match}/timeline', [MatchController::class, 'getMatchTimeline']);
Route::get('/matches/head-to-head/{team1Id}/{team2Id}', [MatchController::class, 'getHeadToHead']);

// Live Updates SSE Stream (No auth required for public viewing)
Route::get('/live-updates/{matchId}/stream', [LiveUpdateController::class, 'stream']);
// Live Updates Polling Status (No auth required for public viewing)
Route::get('/live-updates/status/{matchId}', [LiveUpdateController::class, 'status']);
// Live Updates POST endpoint (No auth required for live scoring)
Route::post('/matches/{matchId}/live-update', [LiveUpdateController::class, 'update']);

// News comments route (public viewing)
Route::get('/news/{newsId}/comments', [NewsController::class, 'getCommentsWithNesting']);

// ===================================
// STANDARDIZED API ENDPOINTS FOR FRONTEND COMPATIBILITY
// ===================================

// Brackets - Standardized endpoints
Route::get('/brackets', function () {
    $events = \App\Models\Event::whereNotNull('bracket_data')->get();
    $brackets = [];
    foreach ($events as $event) {
        $brackets[] = [
            'id' => $event->id,
            'name' => $event->name,
            'type' => $event->type,
            'status' => $event->status,
            'participants' => $event->teams()->count()
        ];
    }
    return response()->json($brackets);
});
Route::get('/brackets/{id}', function ($id) {
    $event = \App\Models\Event::find($id);
    if (!$event) {
        return response()->json(['error' => 'Bracket not found'], 404);
    }
    return response()->json([
        'id' => $event->id,
        'name' => $event->name,
        'matches' => $event->matches()->with(['team1', 'team2'])->get(),
        'teams' => $event->teams()->get(),
        'bracket_data' => $event->bracket_data
    ]);
});

// Rankings - Standardized endpoints with backward compatibility
Route::get('/rankings', function() {
    return app(App\Http\Controllers\RankingController::class)->index(request());
});
Route::get('/rankings/teams', [App\Http\Controllers\TeamRankingController::class, 'index']);
Route::get('/rankings/players', [App\Http\Controllers\RankingController::class, 'index']);

// Brackets - Direct access (outside public prefix for backward compatibility)
Route::get('/events/{eventId}/bracket', [BracketController::class, 'show']);
Route::get('/events/{eventId}/comprehensive-bracket', [ComprehensiveBracketController::class, 'getBracket']);
Route::get('/live-matches', [ComprehensiveBracketController::class, 'getLiveMatches']);

// ===================================
// TOURNAMENT AUTHENTICATED ROUTES
// ===================================

// Tournament Team Registration and Management (Authenticated Users)
Route::middleware('auth:api')->prefix('tournaments')->group(function () {
    // Team registration
    Route::post('/{tournament}/register', [\App\Http\Controllers\TournamentController::class, 'registerTeam']);
    Route::post('/{tournament}/check-in', [\App\Http\Controllers\TournamentController::class, 'checkInTeam']);
    Route::post('/{tournament}/withdraw', [\App\Http\Controllers\TournamentRegistrationController::class, 'withdrawTeam']);
    
    // User's tournament registrations
    Route::get('/my-registrations', [\App\Http\Controllers\TournamentRegistrationController::class, 'getUserRegistrations']);
    Route::get('/{tournament}/my-registration', [\App\Http\Controllers\TournamentRegistrationController::class, 'getUserRegistration']);
    
    // Tournament following/favorites
    Route::post('/{tournament}/follow', [\App\Http\Controllers\TournamentController::class, 'followTournament']);
    Route::delete('/{tournament}/unfollow', [\App\Http\Controllers\TournamentController::class, 'unfollowTournament']);
    Route::get('/following', [\App\Http\Controllers\TournamentController::class, 'getFollowedTournaments']);
    
    // Tournament real-time broadcasting
    Route::get('/{tournament}/broadcast/channels', [\App\Http\Controllers\TournamentBroadcastController::class, 'getChannels']);
    Route::post('/{tournament}/broadcast/message', [\App\Http\Controllers\TournamentBroadcastController::class, 'sendTournamentMessage']);
    Route::get('/{tournament}/broadcast/chat', [\App\Http\Controllers\TournamentBroadcastController::class, 'getTournamentChat']);
});

// Tournament Match Reporting (for participants)
Route::middleware('auth:api')->prefix('tournaments/{tournament}/matches')->group(function () {
    Route::post('/{match}/report-score', [\App\Http\Controllers\TournamentMatchController::class, 'reportScore']);
    Route::post('/{match}/dispute', [\App\Http\Controllers\TournamentMatchController::class, 'disputeResult']);
    Route::post('/{match}/submit-screenshot', [\App\Http\Controllers\TournamentMatchController::class, 'submitScreenshot']);
    Route::get('/{match}/chat', [\App\Http\Controllers\TournamentMatchController::class, 'getMatchChat']);
    Route::post('/{match}/chat', [\App\Http\Controllers\TournamentMatchController::class, 'sendChatMessage']);
});

// Tournament Broadcasting Routes
Route::prefix('tournaments/broadcast')->group(function () {
    Route::get('/channels', [\App\Http\Controllers\TournamentBroadcastController::class, 'getPublicChannels']);
});

Route::middleware('auth:api')->prefix('matches')->group(function () {
    Route::post('/{match}/broadcast/message', [\App\Http\Controllers\TournamentBroadcastController::class, 'sendMatchMessage']);
    Route::get('/{match}/broadcast/chat', [\App\Http\Controllers\TournamentBroadcastController::class, 'getMatchChat']);
});

// Admin Broadcasting Routes
Route::middleware(['auth:api', 'admin'])->prefix('tournaments/{tournament}/admin/broadcast')->group(function () {
    Route::post('/matches/{match}/live-score', [\App\Http\Controllers\TournamentBroadcastController::class, 'triggerLiveScoreUpdate']);
});

// Heroes routes already defined in public group above - removed duplicates

// Mention routes (for frontend compatibility - matches expected API calls)
Route::get('/mentions/search', [MentionController::class, 'searchMentions']);
Route::get('/mentions/popular', [MentionController::class, 'getPopularMentions']);

// Real-time mention routes
Route::get('/mentions/{type}/{id}/counts', [MentionController::class, 'getMentionCounts']);
Route::get('/mentions/{type}/{id}/recent', [MentionController::class, 'getRecentMentions']);
Route::post('/mentions/create', [MentionController::class, 'createMentionsFromContent'])->middleware('auth:api');
Route::delete('/mentions/delete', [MentionController::class, 'deleteMentionsFromContent'])->middleware('auth:api');

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
    Route::middleware('sensitive.rate:profile_password_change,5,60')->post('/profile/change-password', [UserProfileController::class, 'changePassword']);
    Route::middleware('sensitive.rate:profile_email_change,3,60')->post('/profile/change-email', [UserProfileController::class, 'changeEmail']);
    Route::middleware('sensitive.rate:profile_username_change,3,60')->post('/profile/change-username', [UserProfileController::class, 'changeUsername']);
    Route::post('/profile/upload-avatar', [UserProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/delete-avatar', [UserProfileController::class, 'deleteAvatar']);
    
    // User Stats and Activity
    Route::get('/stats', [AuthController::class, 'getUserStats']);
    Route::get('/activity', [AuthController::class, 'getUserProfileActivity']);
    
    // Authentication Management (with enhanced rate limiting)
    Route::middleware('sensitive.rate:password_change,5,60')->post('/change-password', [AuthController::class, 'changePassword']);
    Route::middleware('sensitive.rate:email_change,3,60')->post('/change-email', [AuthController::class, 'changeEmail']);
    Route::middleware('sensitive.rate:username_change,3,60')->post('/change-username', [AuthController::class, 'changeUsername']);
    
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
        Route::post('/threads/{threadId}/reply', [ForumController::class, 'createReply']); // Alias route
        Route::put('/posts/{postId}', [ForumController::class, 'updatePost']);
        Route::delete('/posts/{postId}', [ForumController::class, 'deletePost']);
        
        // Voting
        Route::post('/threads/{threadId}/vote', [ForumController::class, 'voteThread']);
        Route::post('/posts/{postId}/vote', [ForumController::class, 'votePost']);
    });
    
    // News Comments and Voting
    Route::prefix('news')->group(function () {
        Route::post('/{newsId}/comments', [NewsController::class, 'createComment']);
        Route::post('/{newsId}/vote', [NewsController::class, 'vote']);
        Route::put('/comments/{commentId}', [NewsController::class, 'updateComment']);
        Route::delete('/comments/{commentId}', [NewsController::class, 'deleteComment']);
        Route::post('/comments/{commentId}/vote', [NewsController::class, 'voteComment']);
    });
    
    // Match Comments
    Route::prefix('matches')->group(function () {
        Route::post('/{matchId}/comments', [MatchController::class, 'createComment']);
        Route::put('/comments/{commentId}', [MatchController::class, 'updateComment']);
        Route::delete('/comments/{commentId}', [MatchController::class, 'deleteComment']);
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
// MENTION ENDPOINTS
// ===================================
Route::prefix('users')->group(function () {
    Route::get('/{id}/mentions', [MentionController::class, 'getUserMentions']);
});

Route::prefix('teams')->group(function () {
    Route::get('/{id}/mentions', [MentionController::class, 'getTeamMentions']);
});

Route::prefix('players')->group(function () {
    Route::get('/{id}/mentions', [MentionController::class, 'getPlayerMentions']);
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

// Fix for frontend double /api/api/ routing issue - Add aliases for admin routes
Route::middleware(['auth:api', 'role:admin|moderator'])->prefix('api/admin')->group(function () {
    // Core Admin Stats Endpoints - Available to both admin and moderator
    Route::get('/stats', [AdminStatsController::class, 'index']);
    Route::get('/analytics', [AdminStatsController::class, 'analytics']);
    Route::get('/performance-metrics', [AdminStatsController::class, 'getPerformanceMetrics']);
    
    // Admin matches moderation routes (handling double /api/ path)
    Route::prefix('matches-moderation')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'destroy']);
        
        // Match control actions
        Route::post('/{id}/reschedule', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'reschedule']);
        Route::post('/{id}/control', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'controlMatch']);
        Route::post('/{id}/live-scoring', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'updateLiveScoring']);
        Route::post('/{id}/teams/manage', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'manageTeams']);
        Route::put('/{id}/maps', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'updateMaps']);
        Route::put('/{id}/player-stats', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'updatePlayerStats']);
        Route::post('/bulk-operation', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'bulkOperation']);
        Route::get('/statistics', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'getStatistics']);
        Route::get('/live-matches', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'getLiveMatches']);
        Route::get('/export', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'exportMatches']);
    });
    
    // Forums moderation routes (handling double /api/ path)
    Route::prefix('forums-moderation')->group(function () {
        // Dashboard and Overview
        Route::get('/dashboard', [AdminForumsController::class, 'dashboard']);
        Route::get('/statistics', [AdminForumsController::class, 'getStatistics']);
        
        // Thread Management - Full CRUD
        Route::get('/threads', [AdminForumsController::class, 'getThreads']);
        Route::get('/threads/{id}', [AdminForumsController::class, 'showThread']);
        Route::post('/threads', [AdminForumsController::class, 'createThread']);
        Route::put('/threads/{id}', [AdminForumsController::class, 'updateThread']);
        Route::delete('/threads/{id}', [AdminForumsController::class, 'deleteThread']);
        
        // Thread Control Actions
        Route::post('/threads/{id}/pin', [AdminForumsController::class, 'pinThread']);
        Route::post('/threads/{id}/unpin', [AdminForumsController::class, 'unpinThread']);
        Route::post('/threads/{id}/lock', [AdminForumsController::class, 'lockThread']);
        Route::post('/threads/{id}/unlock', [AdminForumsController::class, 'unlockThread']);
        
        // Category Management - Full CRUD
        Route::get('/categories', [AdminForumsController::class, 'getCategories']);
        Route::post('/categories', [AdminForumsController::class, 'createCategory']);
        Route::put('/categories/{id}', [AdminForumsController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [AdminForumsController::class, 'deleteCategory']);
        Route::post('/categories/reorder', [AdminForumsController::class, 'reorderCategories']);
        
        // Posts Management
        Route::get('/posts', [AdminForumsController::class, 'getPosts']);
        Route::put('/posts/{id}', [AdminForumsController::class, 'updatePost']);
        Route::delete('/posts/{id}', [AdminForumsController::class, 'deletePost']);
        
        // User Moderation
        Route::get('/users', [AdminForumsController::class, 'getUsers']);
        Route::post('/users/{userId}/warn', [AdminForumsController::class, 'warnUser']);
        Route::post('/users/{userId}/ban', [AdminForumsController::class, 'banUser']);
        Route::post('/users/{userId}/unban', [AdminForumsController::class, 'unbanUser']);
        
        // Reports Management
        Route::get('/reports', [AdminForumsController::class, 'getReports']);
        Route::post('/reports/{reportId}/resolve', [AdminForumsController::class, 'resolveReport']);
        Route::post('/reports/{reportId}/dismiss', [AdminForumsController::class, 'dismissReport']);
        
        // Bulk Actions
        Route::post('/bulk-actions', [AdminForumsController::class, 'bulkActions']);
        
        // Search and Logs
        Route::get('/search', [AdminForumsController::class, 'adminSearch']);
        Route::get('/moderation-logs', [AdminForumsController::class, 'getModerationLogs']);
    });
    
    // News moderation routes (handling double /api/ path)
    Route::prefix('news-moderation')->group(function () {
        // Categories endpoint that frontend expects - MUST come before {newsId} routes
        Route::get('/categories', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'index']);
        Route::post('/categories', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'store']);
        Route::put('/categories/{id}', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'destroy']);
        
        // News Statistics and Analytics - also before {newsId}
        Route::get('/stats/overview', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getStatistics']);
        
        // Bulk Operations - before {newsId}
        Route::post('/bulk', [\App\Http\Controllers\Admin\AdminNewsController::class, 'bulkOperation']);
        Route::post('/bulk-delete', [\App\Http\Controllers\Admin\AdminNewsController::class, 'bulkDelete']);
        
        // Content Moderation Features - specific routes before {newsId}
        Route::get('/pending/all', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getPendingNews']);
        Route::get('/flags/all', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getFlaggedContent']);
        Route::post('/flags/{flagId}/resolve', [\App\Http\Controllers\Admin\AdminNewsController::class, 'resolveFlag']);
        
        // Comments moderation routes
        Route::get('/comments', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getNewsComments']);
        Route::get('/comments/reported', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getReportedComments']);
        Route::put('/comments/{commentId}/moderate', [\App\Http\Controllers\Admin\AdminNewsController::class, 'moderateComment']);
        Route::delete('/comments/{commentId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'deleteComment']);
        Route::post('/comments/bulk', [\App\Http\Controllers\Admin\AdminNewsController::class, 'bulkModerateComments']);
        
        // Search functionality
        Route::get('/search', [\App\Http\Controllers\Admin\AdminNewsController::class, 'search']);
        
        // Media Management - specific routes before {newsId}
        Route::post('/media/featured-image', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'uploadFeaturedImage']);
        Route::post('/media/gallery', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'uploadGalleryImages']);
        Route::post('/media/video-thumbnail', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'uploadVideoThumbnail']);
        Route::get('/media/library', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'getMediaLibrary']);
        Route::post('/media/cleanup', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'cleanupUnusedMedia']);
        
        // Core CRUD Operations - {newsId} routes MUST come last
        Route::get('/', [\App\Http\Controllers\Admin\AdminNewsController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\AdminNewsController::class, 'store']);
        Route::get('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'show']);
        Route::put('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'update']);
        Route::delete('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'destroy']);
        
        // Specific newsId operations
        Route::post('/{newsId}/approve', [\App\Http\Controllers\Admin\AdminNewsController::class, 'approveNews']);
        Route::post('/{newsId}/reject', [\App\Http\Controllers\Admin\AdminNewsController::class, 'rejectNews']);
        Route::post('/{newsId}/flag', [\App\Http\Controllers\Admin\AdminNewsController::class, 'flagNews']);
        Route::post('/{newsId}/toggle-feature', [\App\Http\Controllers\Admin\AdminNewsController::class, 'toggleFeature']);
        Route::post('/{newsId}/toggle-publish', [\App\Http\Controllers\Admin\AdminNewsController::class, 'togglePublish']);
        Route::get('/{newsId}/moderation-history', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getModerationHistory']);
        Route::delete('/{newsId}/media', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'deleteImage']);
    });
    
    // Removed duplicate users routes - consolidated into comprehensive system below at line 930+
    
    // Events management routes (handling double /api/ path)  
    Route::prefix('events')->group(function () {
        Route::get('/', [AdminEventsController::class, 'index']);
        Route::post('/', [AdminEventsController::class, 'store']);
        Route::get('/{id}', [AdminEventsController::class, 'show']);
        Route::put('/{id}', [AdminEventsController::class, 'update']);
        Route::delete('/{id}', [AdminEventsController::class, 'destroy']);
        Route::post('/{id}/status', [AdminEventsController::class, 'updateStatus']);
        Route::get('/{eventId}/teams', [AdminEventsController::class, 'getEventTeams']);
        Route::post('/{eventId}/teams', [AdminEventsController::class, 'addTeamToEvent']);
        Route::delete('/{eventId}/teams/{teamId}', [AdminEventsController::class, 'removeTeamFromEvent']);
        Route::post('/{id}/generate-bracket', [AdminEventsController::class, 'generateBracket']);
        Route::get('/{id}/statistics', [AdminEventsController::class, 'getEventStatistics']);
        Route::post('/bulk-operation', [AdminEventsController::class, 'bulkOperation']);
        Route::get('/dashboard/analytics', [AdminEventsController::class, 'getAnalyticsDashboard']);
    });
});

Route::middleware(['auth:api', 'role:admin|moderator'])->prefix('admin')->group(function () {
    
    // Core Admin Endpoints - Available to both admin and moderator
    Route::get('/stats', [AdminStatsController::class, 'index']);
    Route::get('/analytics', [AdminStatsController::class, 'analytics']);
    Route::get('/performance-metrics', [AdminStatsController::class, 'getPerformanceMetrics']);
    
    // Resource Management - Admin and Moderator access
    Route::get('/teams', [\App\Http\Controllers\Admin\AdminTeamController::class, 'index']);
    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/matches', [MatchController::class, 'index']);
    Route::get('/events', [EventController::class, 'index']);
    
    // News moderation routes for frontend compatibility (handling double /api/api/ path)
    Route::prefix('news-moderation')->group(function () {
        // Categories endpoint that frontend expects - MUST come before {newsId} routes
        Route::get('/categories', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'index']);
        Route::post('/categories', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'store']);
        Route::put('/categories/{id}', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'destroy']);
        
        // News Statistics and Analytics - also before {newsId}
        Route::get('/stats/overview', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getStatistics']);
        
        // Bulk Operations - before {newsId}
        Route::post('/bulk', [\App\Http\Controllers\Admin\AdminNewsController::class, 'bulkOperation']);
        Route::post('/bulk-delete', [\App\Http\Controllers\Admin\AdminNewsController::class, 'bulkDelete']);
        
        // Content Moderation Features - specific routes before {newsId}
        Route::get('/pending/all', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getPendingNews']);
        Route::get('/flags/all', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getFlaggedContent']);
        Route::post('/flags/{flagId}/resolve', [\App\Http\Controllers\Admin\AdminNewsController::class, 'resolveFlag']);
        
        // Comments moderation routes
        Route::get('/comments', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getNewsComments']);
        Route::get('/comments/reported', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getReportedComments']);
        Route::put('/comments/{commentId}/moderate', [\App\Http\Controllers\Admin\AdminNewsController::class, 'moderateComment']);
        Route::delete('/comments/{commentId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'deleteComment']);
        Route::post('/comments/bulk', [\App\Http\Controllers\Admin\AdminNewsController::class, 'bulkModerateComments']);
        
        // Search functionality
        Route::get('/search', [\App\Http\Controllers\Admin\AdminNewsController::class, 'search']);
        
        // Core CRUD Operations - {newsId} routes MUST come last
        Route::get('/', [\App\Http\Controllers\Admin\AdminNewsController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\AdminNewsController::class, 'store']);
        Route::get('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'show']);
        Route::put('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'update']);
        Route::delete('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'destroy']);
        
        // Specific newsId operations
        Route::post('/{newsId}/approve', [\App\Http\Controllers\Admin\AdminNewsController::class, 'approveNews']);
        Route::post('/{newsId}/reject', [\App\Http\Controllers\Admin\AdminNewsController::class, 'rejectNews']);
        Route::post('/{newsId}/flag', [\App\Http\Controllers\Admin\AdminNewsController::class, 'flagNews']);
        Route::post('/{newsId}/toggle-feature', [\App\Http\Controllers\Admin\AdminNewsController::class, 'toggleFeature']);
        Route::post('/{newsId}/toggle-publish', [\App\Http\Controllers\Admin\AdminNewsController::class, 'togglePublish']);
        Route::get('/{newsId}/moderation-history', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getModerationHistory']);
    });
    
    // ===================================
    // ADMIN MATCHES LIVE SCORING ROUTES
    // ===================================
    Route::prefix('matches')->group(function () {
        // Basic CRUD operations
        Route::get('/', [MatchController::class, 'index']);
        Route::post('/', [MatchController::class, 'store']);
        Route::get('/{matchId}', [MatchController::class, 'show']);
        Route::put('/{matchId}', [MatchController::class, 'update']);
        Route::delete('/{matchId}', [MatchController::class, 'destroy']);
        
        // Live scoring control endpoints (frontend expected routes)
        Route::put('/{matchId}/live-control', [MatchController::class, 'liveControl']);
        Route::post('/{matchId}/live-control', [MatchController::class, 'liveControl']); // Support both PUT and POST
        Route::post('/{matchId}/update-live-stats', [MatchController::class, 'updateLiveStatsComprehensive']);
        Route::put('/{matchId}/update-live-stats', [MatchController::class, 'updateLiveStatsComprehensive']); // Support both PUT and POST
        
        // Additional live scoring endpoints
        Route::post('/{matchId}/start', [MatchController::class, 'startMatch']);
        Route::post('/{matchId}/complete', [MatchController::class, 'completeMatch']);
        Route::post('/{matchId}/reset', [MatchController::class, 'resetMatch']);
    });
    
    // Admin-only routes (checked internally)
    Route::get('/users', [AdminUserController::class, 'getAllUsers']);
    
    // Bracket Match Score Management - Available to admin and moderator
    Route::put('/bracket-matches/{matchId}/score', [AdminEventsController::class, 'updateBracketMatchScore']);
    
    // Bracket Management - Clear/Reset bracket
    Route::delete('/events/{eventId}/bracket', [AdminEventsController::class, 'clearBracket']);
    Route::post('/events/{eventId}/bracket/clear', [AdminEventsController::class, 'clearBracket']);
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
    
    // ===================================
    // COMPREHENSIVE USER MANAGEMENT SYSTEM
    // ===================================
    Route::prefix('users')->group(function () {
        // Core CRUD Operations
        Route::get('/', [\App\Http\Controllers\Admin\AdminUsersController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\AdminUsersController::class, 'store']);
        Route::get('/{userId}', [\App\Http\Controllers\Admin\AdminUsersController::class, 'show']);
        Route::put('/{userId}', [\App\Http\Controllers\Admin\AdminUsersController::class, 'update']);
        Route::delete('/{userId}', [\App\Http\Controllers\Admin\AdminUsersController::class, 'destroy']);
        
        // Advanced Search and Filtering
        Route::get('/search/advanced', [\App\Http\Controllers\Admin\AdminUsersController::class, 'search']);
        
        // User Activity and Monitoring
        Route::get('/{userId}/activity', [\App\Http\Controllers\Admin\AdminUsersController::class, 'getActivity']);
        Route::get('/{userId}/login-history', [\App\Http\Controllers\Admin\AdminUsersController::class, 'getLoginHistory']);
        
        // Password Management
        Route::post('/{userId}/reset-password', [\App\Http\Controllers\Admin\AdminUsersController::class, 'resetPassword']);
        Route::post('/{userId}/force-password-change', [\App\Http\Controllers\Admin\AdminUsersController::class, 'forcePasswordChange']);
        
        // Account Status Management
        Route::post('/{userId}/ban', [\App\Http\Controllers\Admin\AdminUsersController::class, 'manageBan']);
        Route::post('/{userId}/mute', [\App\Http\Controllers\Admin\AdminUsersController::class, 'manageMute']);
        Route::post('/{userId}/suspend', [\App\Http\Controllers\Admin\AdminUsersController::class, 'manageSuspension']);
        
        // User Warnings System
        Route::post('/{userId}/warnings', [\App\Http\Controllers\Admin\AdminUsersController::class, 'issueWarning']);
        Route::get('/{userId}/warnings', [\App\Http\Controllers\Admin\AdminUsersController::class, 'getWarnings']);
        Route::delete('/warnings/{warningId}', [\App\Http\Controllers\Admin\AdminUsersController::class, 'removeWarning']);
        
        // Email Verification Management
        Route::post('/{userId}/email-verification', [\App\Http\Controllers\Admin\AdminUsersController::class, 'manageEmailVerification']);
        Route::post('/{userId}/resend-verification', [\App\Http\Controllers\Admin\AdminUsersController::class, 'resendVerification']);
        
        // Two-Factor Authentication Management (Placeholder for future implementation)
        Route::get('/{userId}/2fa-status', [\App\Http\Controllers\Admin\AdminUsersController::class, 'get2FAStatus']);
        Route::post('/{userId}/2fa/disable', [\App\Http\Controllers\Admin\AdminUsersController::class, 'disable2FA']);
        Route::post('/{userId}/2fa/reset', [\App\Http\Controllers\Admin\AdminUsersController::class, 'reset2FA']);
        
        // Profile Management
        Route::post('/{userId}/upload-avatar', [AdminUserController::class, 'uploadUserAvatar']);
        Route::delete('/{userId}/remove-avatar', [AdminUserController::class, 'removeUserAvatar']);
        Route::post('/{userId}/moderate-profile', [\App\Http\Controllers\Admin\AdminUsersController::class, 'moderateProfile']);
        
        // Session Management
        Route::post('/{userId}/revoke-sessions', [\App\Http\Controllers\Admin\AdminUsersController::class, 'revokeSessions']);
        Route::get('/{userId}/active-sessions', [\App\Http\Controllers\Admin\AdminUsersController::class, 'getActiveSessions']);
        
        // Bulk Operations
        Route::post('/bulk-operations', [\App\Http\Controllers\Admin\AdminUsersController::class, 'bulkOperation']);
        Route::post('/bulk-delete', [\App\Http\Controllers\Admin\AdminUsersController::class, 'bulkDelete']);
        Route::post('/bulk-export', [\App\Http\Controllers\Admin\AdminUsersController::class, 'bulkExport']);
        
        // User Statistics and Reports
        Route::get('/statistics', [\App\Http\Controllers\Admin\AdminUsersController::class, 'getUserStatistics']);
        Route::get('/analytics', [\App\Http\Controllers\Admin\AdminUsersController::class, 'getUserAnalytics']);
        Route::post('/generate-report', [\App\Http\Controllers\Admin\AdminUsersController::class, 'generateReport']);
        
        // Legacy routes for backward compatibility
        Route::get('/all', [AdminUserController::class, 'getAllUsers']);
        Route::post('/create', [AdminUserController::class, 'createUser']);
        Route::get('/{userId}/profile', [AdminUserController::class, 'getUser']);
        Route::put('/{userId}/update', [AdminUserController::class, 'updateUser']);
        Route::delete('/{userId}/delete', [AdminUserController::class, 'deleteUser']);
        Route::post('/{userId}/toggle-ban', [AdminUserController::class, 'toggleBan']);
    });
    
    // ===================================
    // TOURNAMENT ADMIN MANAGEMENT
    // ===================================
    
    // Tournament CRUD Operations (Admin Only)
    Route::prefix('tournaments')->group(function () {
        Route::get('/', [\App\Http\Controllers\TournamentController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\TournamentController::class, 'store']);
        Route::get('/{tournament}', [\App\Http\Controllers\TournamentController::class, 'show']);
        Route::put('/{tournament}', [\App\Http\Controllers\TournamentController::class, 'update']);
        Route::delete('/{tournament}', [\App\Http\Controllers\TournamentController::class, 'destroy']);
        
        // Tournament Management
        Route::post('/{tournament}/start', [\App\Http\Controllers\TournamentController::class, 'startTournament']);
        Route::post('/{tournament}/generate-bracket', [\App\Http\Controllers\TournamentController::class, 'generateBracket']);
        Route::post('/{tournament}/complete', [\App\Http\Controllers\TournamentProgressionController::class, 'completeTournament']);
        Route::post('/{tournament}/cancel', [\App\Http\Controllers\TournamentController::class, 'cancelTournament']);
        
        // Manual Bracket Management (NEW)
        Route::post('/{tournament}/manual-bracket', [\App\Http\Controllers\ManualBracketController::class, 'createManualBracket']);
        Route::get('/manual-bracket/formats', [\App\Http\Controllers\ManualBracketController::class, 'getFormats']);
        Route::get('/manual-bracket/{stageId}', [\App\Http\Controllers\ManualBracketController::class, 'getBracket']);
        Route::put('/manual-bracket/matches/{matchId}/score', [\App\Http\Controllers\ManualBracketController::class, 'updateMatchScore']);
        Route::post('/manual-bracket/{stageId}/reset', [\App\Http\Controllers\ManualBracketController::class, 'resetBracket']);
        
        // Registration Management
        Route::get('/{tournament}/registrations', [\App\Http\Controllers\TournamentRegistrationController::class, 'index']);
        Route::post('/{tournament}/registrations/{registration}/approve', [\App\Http\Controllers\TournamentRegistrationController::class, 'approve']);
        Route::post('/{tournament}/registrations/{registration}/reject', [\App\Http\Controllers\TournamentRegistrationController::class, 'reject']);
        Route::delete('/{tournament}/registrations/{registration}', [\App\Http\Controllers\TournamentRegistrationController::class, 'destroy']);
        
        // Phase Management
        Route::get('/{tournament}/phases', [\App\Http\Controllers\TournamentPhaseController::class, 'index']);
        Route::post('/{tournament}/phases', [\App\Http\Controllers\TournamentPhaseController::class, 'store']);
        Route::put('/{tournament}/phases/{phase}', [\App\Http\Controllers\TournamentPhaseController::class, 'update']);
        Route::post('/{tournament}/phases/{phase}/start', [\App\Http\Controllers\TournamentPhaseController::class, 'startPhase']);
        Route::post('/{tournament}/phases/{phase}/complete', [\App\Http\Controllers\TournamentPhaseController::class, 'completePhase']);
        Route::delete('/{tournament}/phases/{phase}', [\App\Http\Controllers\TournamentPhaseController::class, 'destroy']);
        
        // Bracket Management
        Route::get('/{tournament}/brackets', [\App\Http\Controllers\TournamentBracketController::class, 'index']);
        Route::post('/{tournament}/brackets/{bracket}/reset', [\App\Http\Controllers\TournamentBracketController::class, 'resetBracket']);
        Route::put('/{tournament}/brackets/{bracket}', [\App\Http\Controllers\TournamentBracketController::class, 'update']);
        
        // Swiss System Management
        Route::post('/{tournament}/swiss/generate-round', [\App\Http\Controllers\SwissController::class, 'generateNextRound']);
        Route::post('/{tournament}/swiss/complete', [\App\Http\Controllers\SwissController::class, 'completeSwiss']);
        Route::put('/{tournament}/swiss/pairings/{round}', [\App\Http\Controllers\SwissController::class, 'updatePairings']);
        
        // Match Management
        Route::get('/{tournament}/matches', [\App\Http\Controllers\TournamentMatchController::class, 'index']);
        Route::post('/{tournament}/matches', [\App\Http\Controllers\TournamentMatchController::class, 'store']);
        Route::put('/{tournament}/matches/{match}', [\App\Http\Controllers\TournamentMatchController::class, 'update']);
        Route::post('/{tournament}/matches/{match}/complete', [\App\Http\Controllers\TournamentMatchController::class, 'completeMatch']);
        Route::post('/{tournament}/matches/{match}/walkover', [\App\Http\Controllers\TournamentMatchController::class, 'setWalkover']);
        Route::post('/{tournament}/matches/{match}/dispute/resolve', [\App\Http\Controllers\TournamentMatchController::class, 'resolveDispute']);
        
        // Team Management within Tournament
        Route::post('/{tournament}/teams/{team}/disqualify', [\App\Http\Controllers\TournamentController::class, 'disqualifyTeam']);
        Route::post('/{tournament}/teams/{team}/reinstate', [\App\Http\Controllers\TournamentController::class, 'reinstateTeam']);
        Route::put('/{tournament}/teams/{team}/seed', [\App\Http\Controllers\TournamentController::class, 'updateTeamSeed']);
        
        // Tournament Analytics
        Route::get('/{tournament}/analytics', [\App\Http\Controllers\TournamentAnalyticsController::class, 'index']);
        Route::get('/{tournament}/reports', [\App\Http\Controllers\TournamentAnalyticsController::class, 'generateReport']);
    });
    
    // Tournament System Settings
    Route::prefix('tournament-settings')->group(function () {
        Route::get('/', [\App\Http\Controllers\TournamentSettingsController::class, 'index']);
        Route::put('/', [\App\Http\Controllers\TournamentSettingsController::class, 'update']);
        Route::get('/formats', [\App\Http\Controllers\TournamentSettingsController::class, 'getFormats']);
        Route::get('/templates', [\App\Http\Controllers\TournamentSettingsController::class, 'getTemplates']);
        Route::post('/templates', [\App\Http\Controllers\TournamentSettingsController::class, 'createTemplate']);
    });
    
    // Team Management - Full CRUD
    Route::prefix('teams')->group(function () {
        Route::get('/', [AdminTeamController::class, 'index']);
        Route::post('/', [AdminTeamController::class, 'store']);
        Route::post('/bulk-delete', [TeamController::class, 'bulkDelete']); // Bulk delete teams
        Route::get('/{teamId}', [AdminTeamController::class, 'show']);
        Route::put('/{teamId}', [AdminTeamController::class, 'update']);
        Route::delete('/{teamId}', [AdminTeamController::class, 'destroy']);
        
        // Team Roster Management
        Route::post('/{teamId}/players', [TeamController::class, 'addPlayer']);
        Route::delete('/{teamId}/players/{playerId}', [TeamController::class, 'removePlayer']);
        Route::put('/{teamId}/players/{playerId}/role', [TeamController::class, 'updatePlayerRole']);
        Route::post('/{teamId}/transfer-player', [TeamController::class, 'transferPlayer']);
// Coach image upload
Route::post('/teams/{teamId}/coach/upload', [TeamController::class, 'uploadCoachImage']);

        
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
        Route::post('/bulk-delete', [PlayerController::class, 'bulkDelete']);
        Route::get('/{playerId}', [PlayerController::class, 'getPlayerAdmin']);
        Route::get('/{playerId}/matches', [PlayerMatchHistoryController::class, 'getPlayerMatches']);
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
    
    // ===================================
    // COMPREHENSIVE EVENTS MODERATION PANEL
    // ===================================
    Route::prefix('events')->group(function () {
        // Core CRUD Operations
        Route::get('/', [AdminEventsController::class, 'index']);
        Route::post('/', [AdminEventsController::class, 'store']);
        Route::get('/{id}', [AdminEventsController::class, 'show']);
        Route::put('/{id}', [AdminEventsController::class, 'update']);
        Route::delete('/{id}', [AdminEventsController::class, 'destroy']);
        
        // Event Status Management
        Route::post('/{id}/status', [AdminEventsController::class, 'updateStatus']);
        
        // Team Registration Management
        Route::get('/{eventId}/teams', [AdminEventsController::class, 'getEventTeams']);
        Route::post('/{eventId}/teams', [AdminEventsController::class, 'addTeamToEvent']);
        Route::delete('/{eventId}/teams/{teamId}', [AdminEventsController::class, 'removeTeamFromEvent']);
        Route::put('/{eventId}/teams/{teamId}/seed', [AdminEventsController::class, 'updateTeamSeed']);
        
        // Bracket Generation and Management
        Route::post('/{id}/generate-bracket', [AdminEventsController::class, 'generateBracket']);
        Route::get('/{id}/bracket', [AdminEventsController::class, 'getEventBracket']);
        
        // Manual Bracket Management System
        Route::post('/{eventId}/bracket/manual/stage', [\App\Http\Controllers\Admin\ManualBracketController::class, 'createBracketStage']);
        Route::post('/{eventId}/bracket/manual/match', [\App\Http\Controllers\Admin\ManualBracketController::class, 'createMatch']);
        Route::put('/{eventId}/bracket/manual/match/{matchId}', [\App\Http\Controllers\Admin\ManualBracketController::class, 'updateMatch']);
        Route::post('/{eventId}/bracket/manual/match/{matchId}/scores', [\App\Http\Controllers\Admin\ManualBracketController::class, 'setMatchScores']);
        Route::delete('/{eventId}/bracket/manual/match/{matchId}', [\App\Http\Controllers\Admin\ManualBracketController::class, 'deleteMatch']);
        Route::get('/{eventId}/bracket/manual/matches', [\App\Http\Controllers\Admin\ManualBracketController::class, 'getMatches']);
        Route::post('/{eventId}/bracket/manual/matches/bulk', [\App\Http\Controllers\Admin\ManualBracketController::class, 'bulkCreateMatches']);
        Route::post('/{eventId}/bracket/manual/reset', [\App\Http\Controllers\Admin\ManualBracketController::class, 'resetBracket']);
        
        // Event Statistics and Analytics
        Route::get('/{id}/statistics', [AdminEventsController::class, 'getEventStatistics']);
        
        // Bulk Operations
        Route::post('/bulk-operation', [AdminEventsController::class, 'bulkOperation']);
        
        // Analytics Dashboard
        Route::get('/dashboard/analytics', [AdminEventsController::class, 'getAnalyticsDashboard']);
        
        // Legacy Event Images (keeping for backward compatibility)
        Route::post('/{eventId}/logo', [ImageUploadController::class, 'uploadEventLogo']);
        Route::post('/{eventId}/banner', [ImageUploadController::class, 'uploadEventBanner']);
        Route::delete('/{eventId}/banner', [ImageUploadController::class, 'deleteEventBanner']);
        
        // Legacy routes for backward compatibility
        Route::get('/all', [EventController::class, 'getAllEvents']);
        Route::get('/{eventId}/admin', [EventController::class, 'getEventAdmin']);
        Route::post('/{eventId}/force-destroy', [EventController::class, 'forceDestroy']);
        Route::post('/{eventId}/teams/admin-add', [EventController::class, 'adminAddTeamToEvent']);
        Route::delete('/{eventId}/teams/{teamId}/admin', [EventController::class, 'adminRemoveTeamFromEvent']);
        
        // Legacy Bracket Management (keeping for existing integrations)
        Route::put('/{eventId}/matches/{matchId}/score', [EventController::class, 'updateMatchScore']);
        Route::put('/{eventId}/bracket/matches/{matchId}', [BracketController::class, 'updateMatch']);
        Route::post('/{eventId}/comprehensive-bracket', [ComprehensiveBracketController::class, 'generate']);
        Route::put('/{eventId}/comprehensive-bracket/matches/{matchId}', [ComprehensiveBracketController::class, 'updateMatch']);
        Route::post('/{eventId}/swiss/next-round', [ComprehensiveBracketController::class, 'generateNextSwissRound']);
        Route::put('/bracket/matches/{matchId}', [ComprehensiveBracketController::class, 'updateMatch']);
        Route::put('/bracket/matches/{matchId}/games/{gameNumber}', [ComprehensiveBracketController::class, 'updateGame']);
        Route::post('/bracket/matches/{matchId}/reset-bracket', [ComprehensiveBracketController::class, 'resetBracket']);
        Route::post('/{eventId}/swiss/generate-round', [ComprehensiveBracketController::class, 'generateSwissRound']);
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
    
    // ===================================
    // COMPREHENSIVE MATCHES MODERATION PANEL
    // ===================================
    Route::prefix('matches-moderation')->group(function () {
        // Core CRUD Operations
        Route::get('/', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'destroy']);
        
        // Match Scheduling and Rescheduling
        Route::post('/{id}/reschedule', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'reschedule']);
        
        // Match Status Control
        Route::post('/{id}/control', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'controlMatch']);
        
        // Live Scoring Management
        Route::post('/{id}/live-scoring', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'updateLiveScoring']);
        
        // Team and Player Management
        Route::post('/{id}/teams/manage', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'manageTeams']);
        
        // Map and Game Mode Management
        Route::put('/{id}/maps', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'updateMaps']);
        
        // Player Statistics Management
        Route::put('/{id}/player-stats', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'updatePlayerStats']);
        Route::put('/{matchId}/player/{playerId}/stats', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'updateSinglePlayerStats']);
        
        // Bulk Operations
        Route::post('/bulk-operation', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'bulkOperation']);
        
        // Statistics and Analytics
        Route::get('/statistics', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'getStatistics']);
        Route::get('/live-matches', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'getLiveMatches']);
        
        // Export and Reporting
        Route::get('/export', [\App\Http\Controllers\Admin\AdminMatchesController::class, 'exportMatches']);
    });
    
    // News Management - Full CRUD with Admin Features
    Route::prefix('news')->group(function () {
        // Core CRUD Operations
        Route::get('/', [\App\Http\Controllers\Admin\AdminNewsController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\AdminNewsController::class, 'store']);
        Route::get('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'show']);
        Route::put('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'update']);
        Route::delete('/{newsId}', [\App\Http\Controllers\Admin\AdminNewsController::class, 'destroy']);
        
        // News Statistics and Analytics
        Route::get('/stats/overview', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getStatistics']);
        
        // Bulk Operations
        Route::post('/bulk', [\App\Http\Controllers\Admin\AdminNewsController::class, 'bulkOperation']);
        Route::post('/bulk-delete', [\App\Http\Controllers\Admin\AdminNewsController::class, 'bulkDelete']);
        
        // Content Moderation Features
        Route::get('/pending/all', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getPendingNews']);
        Route::post('/{newsId}/approve', [\App\Http\Controllers\Admin\AdminNewsController::class, 'approveNews']);
        Route::post('/{newsId}/reject', [\App\Http\Controllers\Admin\AdminNewsController::class, 'rejectNews']);
        Route::post('/{newsId}/flag', [\App\Http\Controllers\Admin\AdminNewsController::class, 'flagNews']);
        Route::post('/{newsId}/toggle-feature', [\App\Http\Controllers\Admin\AdminNewsController::class, 'toggleFeature']);
        
        // Flag Management
        Route::get('/flags/all', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getFlaggedContent']);
        Route::post('/flags/{flagId}/resolve', [\App\Http\Controllers\Admin\AdminNewsController::class, 'resolveFlag']);
        
        // Moderation History
        Route::get('/{newsId}/moderation-history', [\App\Http\Controllers\Admin\AdminNewsController::class, 'getModerationHistory']);
        
        // Media Management
        Route::post('/media/featured-image', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'uploadFeaturedImage']);
        Route::post('/media/gallery', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'uploadGalleryImages']);
        Route::post('/media/video-thumbnail', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'uploadVideoThumbnail']);
        Route::delete('/{newsId}/media', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'deleteImage']);
        Route::get('/media/library', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'getMediaLibrary']);
        Route::post('/media/cleanup', [\App\Http\Controllers\Admin\AdminNewsMediaController::class, 'cleanupUnusedMedia']);
        
        // Legacy routes for backwards compatibility
        Route::get('/admin', [NewsController::class, 'adminIndex']);
        Route::post('/admin', [NewsController::class, 'store']);
        Route::get('/admin/{newsId}', [NewsController::class, 'getNewsAdmin']);
        Route::put('/admin/{newsId}', [NewsController::class, 'update']);
        Route::delete('/admin/{newsId}', [NewsController::class, 'destroy']);
        
        // Legacy Images
        Route::post('/{newsId}/featured-image', [ImageUploadController::class, 'uploadNewsFeaturedImage']);
        Route::post('/{newsId}/images', [ImageUploadController::class, 'uploadNewsImages']);
    });
    
    // News Categories Management - Full CRUD
    Route::prefix('news-categories')->group(function () {
        // Core CRUD Operations
        Route::get('/', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'store']);
        Route::get('/{categoryId}', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'show']);
        Route::put('/{categoryId}', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'update']);
        Route::delete('/{categoryId}', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'destroy']);
        
        // Category Management Features
        Route::post('/bulk', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'bulkOperation']);
        Route::post('/reorder', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'reorder']);
        Route::get('/stats/overview', [\App\Http\Controllers\Admin\AdminNewsCategoryController::class, 'getStatistics']);
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

    // ===================================
    // COMPREHENSIVE FORUMS MODERATION PANEL
    // ===================================
    Route::prefix('forums-moderation')->group(function () {
        // Dashboard and Overview
        Route::get('/dashboard', [AdminForumsController::class, 'dashboard']);
        Route::get('/statistics', [AdminForumsController::class, 'getStatistics']);
        
        // Thread Management - Full CRUD
        Route::get('/threads', [AdminForumsController::class, 'getThreads']);
        Route::get('/threads/{id}', [AdminForumsController::class, 'showThread']);
        Route::post('/threads', [AdminForumsController::class, 'createThread']);
        Route::put('/threads/{id}', [AdminForumsController::class, 'updateThread']);
        Route::delete('/threads/{id}', [AdminForumsController::class, 'deleteThread']);
        
        // Thread Control Actions
        Route::post('/threads/{id}/pin', [AdminForumsController::class, 'pinThread']);
        Route::post('/threads/{id}/unpin', [AdminForumsController::class, 'unpinThread']);
        Route::post('/threads/{id}/lock', [AdminForumsController::class, 'lockThread']);
        Route::post('/threads/{id}/unlock', [AdminForumsController::class, 'unlockThread']);
        Route::post('/threads/{id}/sticky', [AdminForumsController::class, 'stickyThread']);
        Route::post('/threads/{id}/unsticky', [AdminForumsController::class, 'unstickyThread']);
        
        // Category Management - Full CRUD
        Route::get('/categories', [AdminForumsController::class, 'getCategories']);
        Route::post('/categories', [AdminForumsController::class, 'createCategory']);
        Route::put('/categories/{id}', [AdminForumsController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [AdminForumsController::class, 'deleteCategory']);
        Route::post('/categories/reorder', [AdminForumsController::class, 'reorderCategories']);
        
        // Posts Management
        Route::get('/posts', [AdminForumsController::class, 'getPosts']);
        Route::put('/posts/{id}', [AdminForumsController::class, 'updatePost']);
        Route::delete('/posts/{id}', [AdminForumsController::class, 'deletePost']);
        
        // User Moderation
        Route::get('/users', [AdminForumsController::class, 'getUsers']);
        Route::post('/users/{userId}/warn', [AdminForumsController::class, 'warnUser']);
        Route::post('/users/{userId}/timeout', [AdminForumsController::class, 'timeoutUser']);
        Route::post('/users/{userId}/ban', [AdminForumsController::class, 'banUser']);
        Route::post('/users/{userId}/unban', [AdminForumsController::class, 'unbanUser']);
        
        // Bulk Moderation Actions
        Route::post('/bulk-actions', [AdminForumsController::class, 'bulkActions']);
        
        // Advanced Search and Filtering
        Route::get('/search', [AdminForumsController::class, 'advancedSearch']);
        
        // Report Management System
        Route::get('/reports', [AdminForumsController::class, 'getReports']);
        Route::post('/reports/{reportId}/resolve', [AdminForumsController::class, 'resolveReport']);
        Route::post('/reports/{reportId}/dismiss', [AdminForumsController::class, 'dismissReport']);
        
        // Moderation Logs
        Route::get('/moderation-logs', [AdminForumsController::class, 'getModerationLogs']);
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
    
    // ===================================================================
    // OPTIMIZED ADMIN DASHBOARD ROUTES - High Performance CRUD Operations
    // ===================================================================
    Route::prefix('optimized')->group(function () {
        // Optimized Dashboard
        Route::get('/dashboard', [OptimizedAdminController::class, 'dashboard']);
        
        // Optimized Player Management
        Route::get('/players', [OptimizedAdminController::class, 'players']);
        
        // Optimized Team Management
        Route::get('/teams', [OptimizedAdminController::class, 'teams']);
        
        // Optimized Live Scoring
        Route::get('/live-scoring', [OptimizedAdminController::class, 'liveScoring']);
        Route::get('/live-scoring/match/{matchId}', [OptimizedAdminController::class, 'getLiveScoringMatch']);
        
        // Bulk Operations with Performance Optimization
        Route::prefix('bulk')->group(function () {
            Route::post('/operations', [OptimizedAdminController::class, 'bulkOperations']);
        });
        
        // Advanced Analytics with Caching
        Route::get('/analytics', [OptimizedAdminController::class, 'analytics']);
        
        // Performance Monitoring
        Route::get('/performance', [OptimizedAdminController::class, 'performanceMetrics']);
    });
    
    // ===================================================================
    // ADMIN TOURNAMENT MANAGEMENT ROUTES
    // ===================================================================
    Route::prefix('tournaments')->group(function () {
        // Tournament Dashboard and Overview
        Route::get('/dashboard', [AdminTournamentController::class, 'getDashboard']);
        Route::get('/{tournament}/overview', [AdminTournamentController::class, 'getManagementOverview']);
        
        // Tournament Control
        Route::post('/{tournament}/force-start', [AdminTournamentController::class, 'forceStartTournament']);
        Route::post('/{tournament}/force-complete', [AdminTournamentController::class, 'forceCompleteTournament']);
        
        // Registration Management
        Route::get('/{tournament}/registrations', [AdminTournamentController::class, 'manageRegistrations']);
        Route::post('/{tournament}/registrations/bulk', [AdminTournamentController::class, 'bulkManageRegistrations']);
        
        // Phase Management
        Route::post('/{tournament}/phases', [AdminTournamentController::class, 'managePhases']);
        
        // Match Dispute Resolution
        Route::get('/disputes', [AdminTournamentController::class, 'getDisputedMatches']);
        Route::post('/matches/{match}/resolve', [AdminTournamentController::class, 'resolveDispute']);
        
        // Cache Management
        Route::post('/cache/clear', [OptimizedAdminController::class, 'clearCache']);
        
        // Database Optimization
        Route::post('/database/optimize', [OptimizedAdminController::class, 'optimizeDatabase']);
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

// ===================================
// ACHIEVEMENT SYSTEM ROUTES
// ===================================

// Public achievement routes (no auth required)
Route::prefix('achievements')->group(function () {
    Route::get('/', [AchievementController::class, 'index']);
    Route::get('/{achievement}', [AchievementController::class, 'show']);
    Route::get('/categories', [AchievementController::class, 'categories']);
    Route::get('/rarities', [AchievementController::class, 'rarities']);
    Route::get('/stats/global', [AchievementController::class, 'globalStats']);
});

// Public leaderboard routes (no auth required)
Route::prefix('leaderboards')->group(function () {
    Route::get('/', [LeaderboardController::class, 'index']);
    Route::get('/{leaderboard}', [LeaderboardController::class, 'show']);
    Route::get('/{leaderboard}/leaderboard', [LeaderboardController::class, 'show']);
    Route::get('/metadata', [LeaderboardController::class, 'metadata']);
});

// Public challenge routes (no auth required)
Route::prefix('challenges')->group(function () {
    Route::get('/', [ChallengeController::class, 'index']);
    Route::get('/{challenge}', [ChallengeController::class, 'show']);
    Route::get('/{challenge}/leaderboard', [ChallengeController::class, 'leaderboard']);
    Route::get('/difficulties', [ChallengeController::class, 'difficulties']);
});

// Public streak routes (no auth required)
Route::prefix('streaks')->group(function () {
    Route::get('/leaderboard', [StreakController::class, 'leaderboard']);
    Route::get('/at-risk', [StreakController::class, 'atRisk']);
    Route::get('/types', [StreakController::class, 'types']);
    Route::get('/statistics', [StreakController::class, 'statistics']);
});

// Authenticated achievement routes
Route::middleware('auth:api')->group(function () {
    // User achievements
    Route::prefix('achievements')->group(function () {
        Route::get('/user/{user?}', [AchievementController::class, 'userAchievements']);
        Route::get('/summary/{user?}', [AchievementController::class, 'userSummary']);
        Route::post('/track', [AchievementController::class, 'trackActivity']);
    });

    // User leaderboards
    Route::prefix('leaderboards')->group(function () {
        Route::get('/user/{user?}', [LeaderboardController::class, 'userPositions']);
        Route::get('/{leaderboard}/user/{user?}', [LeaderboardController::class, 'userHistory']);
        Route::get('/{leaderboard}/nearby', [LeaderboardController::class, 'nearbyRankings']);
    });

    // User challenges
    Route::prefix('challenges')->group(function () {
        Route::post('/{challenge}/join', [ChallengeController::class, 'join']);
        Route::get('/{challenge}/progress/{user?}', [ChallengeController::class, 'userProgress']);
        Route::get('/user/{user?}', [ChallengeController::class, 'userChallenges']);
    });

    // User streaks
    Route::prefix('streaks')->group(function () {
        Route::get('/user/{user?}', [StreakController::class, 'index']);
        Route::get('/{streak}', [StreakController::class, 'show']);
    });

    // User titles
    Route::prefix('titles')->group(function () {
        Route::get('/user/{user?}', [UserTitleController::class, 'index']);
        Route::get('/active/{user?}', [UserTitleController::class, 'active']);
        Route::post('/{userTitle}/activate', [UserTitleController::class, 'setActive']);
        Route::delete('/active', [UserTitleController::class, 'removeActive']);
    });

    // Achievement notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/user/{user?}', [AchievementNotificationController::class, 'index']);
        Route::get('/unread-count/{user?}', [AchievementNotificationController::class, 'unreadCount']);
        Route::post('/{notification}/read', [AchievementNotificationController::class, 'markAsRead']);
        Route::post('/{notification}/unread', [AchievementNotificationController::class, 'markAsUnread']);
        Route::post('/read-all', [AchievementNotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [AchievementNotificationController::class, 'destroy']);
        Route::get('/types', [AchievementNotificationController::class, 'types']);
    });
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
// Route::get('/system-test', [\App\Http\Controllers\SystemTestController::class, 'testAllSystems']);

// Upload route aliases for frontend compatibility
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::post('/upload/team/{teamId}/logo', [ImageUploadController::class, 'uploadTeamLogo']);
    Route::post('/upload/team/{teamId}/banner', [ImageUploadController::class, 'uploadTeamBanner']);
    Route::post('/upload/team/{teamId}/coach-avatar', [TeamController::class, 'uploadCoachImage']);
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
// COMPREHENSIVE ANALYTICS ROUTES
// ===================================

Route::middleware(['auth:api', 'role:admin|moderator'])->prefix('analytics')->group(function () {
    // Main Analytics Dashboard
    Route::get('/', [AnalyticsController::class, 'index']);
    
    // Advanced Player Analytics (Admin/Moderator)
    Route::prefix('players')->group(function () {
        Route::get('/advanced/{playerId}', [PlayerAnalyticsController::class, 'getPlayerAnalytics']);
        Route::get('/leaderboards', [PlayerAnalyticsController::class, 'getPlayerLeaderboard']);
        Route::get('/compare', function(Request $request, MatchAnalyticsService $service) {
            $playerIds = $request->get('players', []);
            $timeframe = $request->get('timeframe', '30d');
            return $service->comparePlayerPerformances($playerIds, $timeframe);
        });
        Route::get('/{playerId}/detailed-trends', [PlayerAnalyticsController::class, 'getPlayerAnalytics']);
        Route::get('/{playerId}/career-progression', [PlayerAnalyticsController::class, 'getPlayerAnalytics']);
    });
    
    // Advanced Team Analytics (Admin/Moderator)
    Route::prefix('teams')->group(function () {
        Route::get('/advanced/{teamId}', [TeamAnalyticsController::class, 'getTeamAnalytics']);
        Route::get('/compare', function(Request $request, MatchAnalyticsService $service) {
            $teamIds = $request->get('teams', []);
            $timeframe = $request->get('timeframe', '90d');
            return $service->compareTeamPerformances($teamIds, $timeframe);
        });
        Route::get('/{teamId}/performance-breakdown', [TeamAnalyticsController::class, 'getTeamAnalytics']);
        Route::get('/{teamId}/vs/{opponentId}/detailed', [TeamAnalyticsController::class, 'getTeamComparison']);
    });
    
    // Advanced Hero Meta Analytics (Admin/Moderator)
    Route::prefix('heroes')->group(function () {
        Route::get('/meta/detailed', [HeroAnalyticsController::class, 'getHeroMetaAnalysis']);
        Route::get('/meta/generate-report', function(Request $request, MatchAnalyticsService $service) {
            $timeframe = $request->get('timeframe', '30d');
            $region = $request->get('region');
            $tier = $request->get('tier');
            return $service->generateMetaAnalysis($timeframe, $region, $tier);
        });
        Route::get('/{heroName}/detailed', [HeroAnalyticsController::class, 'getHeroAnalytics']);
        Route::get('/trends/analysis', [HeroAnalyticsController::class, 'getHeroMetaAnalysis']);
    });
    
    // Advanced Match Analytics (Admin/Moderator)
    Route::prefix('matches')->group(function () {
        Route::get('/{matchId}/comprehensive', function($matchId, MatchAnalyticsService $service) {
            return $service->aggregateMatchStatistics($matchId, true);
        });
        Route::get('/batch-analyze', function(Request $request, MatchAnalyticsService $service) {
            $matchIds = $request->get('match_ids', []);
            return $service->batchProcessMatches($matchIds);
        });
        Route::get('/{matchId}/live', function($matchId, MatchAnalyticsService $service) {
            return $service->getLiveMatchAnalytics($matchId);
        });
    });
    
    // Advanced Tournament Analytics (Admin/Moderator)
    Route::prefix('tournaments')->group(function () {
        Route::get('/{eventId}/comprehensive', function($eventId, MatchAnalyticsService $service) {
            return $service->aggregateTournamentStatistics($eventId, true);
        });
        Route::get('/leaderboards/generate', function(Request $request, MatchAnalyticsService $service) {
            $timeframe = $request->get('timeframe', '30d');
            $region = $request->get('region');
            return $service->generateLeaderboards($timeframe, $region, true);
        });
    });
    
    // Real-time Analytics
    Route::prefix('real-time')->group(function () {
        Route::get('/', [RealTimeAnalyticsController::class, 'index']);
        Route::post('/broadcast', [RealTimeAnalyticsController::class, 'broadcastUpdate']);
        Route::get('/stream', [RealTimeAnalyticsController::class, 'streamData']);
    });
    
    // User Activity Analytics
    Route::prefix('activity')->group(function () {
        Route::get('/', [UserActivityController::class, 'index']);
        Route::post('/track', [UserActivityController::class, 'track']);
    });
    
    // Resource-Specific Analytics
    Route::prefix('resources')->group(function () {
        Route::get('/teams/{teamId}', [ResourceAnalyticsController::class, 'team']);
        Route::get('/players/{playerId}', [ResourceAnalyticsController::class, 'player']);
        Route::get('/matches/{matchId}', [ResourceAnalyticsController::class, 'match']);
        Route::get('/events/{eventId}', [ResourceAnalyticsController::class, 'event']);
        Route::get('/news/{newsId}', [ResourceAnalyticsController::class, 'news']);
        Route::get('/forum/{threadId}', [ResourceAnalyticsController::class, 'forum']);
    });
});

// ===================================
// COMPREHENSIVE ANALYTICS SYSTEM
// ===================================

// Public Analytics Endpoints (No Authentication Required)
Route::prefix('analytics')->group(function () {
    // Event tracking for user actions
    Route::post('/events', [AnalyticsController::class, 'trackEvent']);
    
    // Basic public analytics data
    Route::get('/public/overview', [AnalyticsController::class, 'getPublicOverview']);
    Route::get('/public/trending', [AnalyticsController::class, 'getTrendingContent']);
    Route::get('/public/live-stats', [RealTimeAnalyticsController::class, 'getPublicLiveStats']);
    
    // Player Analytics (Public)
    Route::prefix('players')->group(function () {
        Route::get('/leaderboard', [PlayerAnalyticsController::class, 'getPlayerLeaderboard']);
        Route::get('/{playerId}', [PlayerAnalyticsController::class, 'getPlayerAnalytics']);
    });
    
    // Team Analytics (Public)
    Route::prefix('teams')->group(function () {
        Route::get('/{teamId}', [TeamAnalyticsController::class, 'getTeamAnalytics']);
        Route::get('/{teamId}/vs/{opponentId}', [TeamAnalyticsController::class, 'getTeamComparison']);
    });
    
    // Hero Meta Analytics (Public)
    Route::prefix('heroes')->group(function () {
        Route::get('/meta', [HeroAnalyticsController::class, 'getHeroMetaAnalysis']);
        Route::get('/{heroName}', [HeroAnalyticsController::class, 'getHeroAnalytics']);
    });
    
    // Match Analytics (Public)
    Route::prefix('matches')->group(function () {
        Route::get('/{matchId}/analytics', function($matchId, MatchAnalyticsService $service) {
            return $service->aggregateMatchStatistics($matchId);
        });
        Route::get('/{matchId}/detailed', function($matchId, MatchAnalyticsService $service) {
            return $service->aggregateMatchStatistics($matchId);
        });
    });
    
    // Tournament Analytics (Public)
    Route::prefix('tournaments')->group(function () {
        Route::get('/{eventId}/analytics', function($eventId, MatchAnalyticsService $service) {
            return $service->aggregateTournamentStatistics($eventId);
        });
        Route::get('/{eventId}/leaderboards', function($eventId, MatchAnalyticsService $service) {
            return $service->generateLeaderboards('tournament', null, false);
        });
    });
    
    // Player Profile & Match History (Public endpoints)
    Route::get('/player-profile/{id}', [PlayerMatchHistoryController::class, 'getPlayerProfile']);
    Route::get('/players/{id}/matches', [PlayerMatchHistoryController::class, 'getPlayerMatches']);
});

// ===================================
// IMAGE TESTING ROUTES
// ===================================
// Temporarily removed ImageTestController routes due to missing controller
// Route::prefix('images')->group(function () {
//     Route::get('/test-all', [ImageTestController::class, 'testAllImages']);
//     Route::get('/test-url', [ImageTestController::class, 'testImageUrl']);
// });


// ===================================
// STATUS MONITORING API ROUTES
// ===================================
Route::prefix('status')->group(function () {
    Route::get('/health', [\App\Http\Controllers\StatusController::class, 'health']);
    Route::get('/metrics', [\App\Http\Controllers\StatusController::class, 'metrics']);
    Route::get('/uptime', [\App\Http\Controllers\StatusController::class, 'uptime']);
    Route::get('/incidents', [\App\Http\Controllers\StatusController::class, 'incidents']);
    Route::get('/response-times', [\App\Http\Controllers\StatusController::class, 'responseTimes']);
    Route::get('/maintenance', [\App\Http\Controllers\StatusController::class, 'maintenance']);
    Route::post('/report', [\App\Http\Controllers\StatusController::class, 'reportIssue']);
});
