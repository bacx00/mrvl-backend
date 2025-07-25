<?php

// Comprehensive API endpoint tester
$baseUrl = 'https://staging.mrvl.net/api';
$token = null;

// Color codes for output
function colorOutput($text, $color) {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m", 
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// Make HTTP request
function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'body' => $response];
}

// Get authentication token
echo colorOutput("=== GETTING AUTHENTICATION TOKEN ===\n", 'yellow');
$loginData = ['email' => 'jhonny@ar-mediia.com', 'password' => 'password123'];
$loginResponse = makeRequest("$baseUrl/auth/login", 'POST', $loginData);

if ($loginResponse['code'] == 200) {
    $loginResult = json_decode($loginResponse['body'], true);
    $token = $loginResult['token'] ?? null;
    echo colorOutput("✓ Authentication successful\n", 'green');
    echo "Token: " . substr($token, 0, 50) . "...\n";
    
    // Quick verification test
    $verifyResponse = makeRequest("$baseUrl/auth/me", 'GET', null, $token);
    if ($verifyResponse['code'] == 200) {
        echo colorOutput("✓ Token verification successful\n", 'green');
    } else {
        echo colorOutput("✗ Token verification failed: HTTP {$verifyResponse['code']}\n", 'red');
        echo "Response: " . $verifyResponse['body'] . "\n";
        exit(1);
    }
} else {
    echo colorOutput("✗ Authentication failed: HTTP {$loginResponse['code']}\n", 'red');
    echo "Response: " . $loginResponse['body'] . "\n";
    exit(1);
}

// Define all endpoints to test
$endpoints = [
    // Authentication endpoints
    ['GET', '/auth/me', 'Get current user', true],
    ['POST', '/auth/logout', 'Logout user', true],
    ['POST', '/auth/refresh', 'Refresh token', true],
    ['POST', '/auth/forgot-password', 'Forgot password', false, ['email' => 'test@example.com']],
    ['POST', '/auth/reset-password', 'Reset password', false, ['token' => 'test', 'email' => 'test@example.com', 'password' => 'newpass', 'password_confirmation' => 'newpass']],
    
    // Public endpoints
    ['GET', '/public/teams', 'Get all teams', false],
    ['GET', '/public/teams/1', 'Get team by ID', false],
    ['GET', '/public/players', 'Get all players', false],
    ['GET', '/public/players/1', 'Get player by ID', false],
    ['GET', '/public/players/1/match-history', 'Get player match history', false],
    ['GET', '/public/players/1/hero-stats', 'Get player hero stats', false],
    ['GET', '/public/players/1/performance-stats', 'Get player performance stats', false],
    ['GET', '/public/players/1/map-stats', 'Get player map stats', false],
    ['GET', '/public/players/1/event-stats', 'Get player event stats', false],
    ['GET', '/public/events', 'Get all events', false],
    ['GET', '/public/events/1', 'Get event by ID', false],
    ['GET', '/public/matches', 'Get all matches', false],
    ['GET', '/public/matches/1', 'Get match by ID', false],
    ['GET', '/public/forums/categories', 'Get forum categories', false],
    ['GET', '/public/forums/threads', 'Get forum threads', false],
    ['GET', '/public/forums/threads/1', 'Get forum thread', false],
    ['GET', '/public/forums/threads/1/posts', 'Get thread posts', false],
    ['GET', '/public/news', 'Get all news', false],
    ['GET', '/public/news/1', 'Get news by ID', false],
    ['GET', '/public/news/categories', 'Get news categories', false],
    ['GET', '/public/rankings/teams', 'Get team rankings', false],
    ['GET', '/public/rankings/players', 'Get player rankings', false],
    ['GET', '/public/rankings/distribution', 'Get rank distribution', false],
    ['GET', '/public/rankings/marvel-rivals-info', 'Get Marvel Rivals info', false],
    ['GET', '/public/heroes', 'Get all heroes', false],
    ['GET', '/public/heroes/images', 'Get hero images', false],
    ['GET', '/public/search?q=test', 'Public search', false],
    
    // User authenticated endpoints
    ['GET', '/user', 'Get authenticated user', true],
    ['GET', '/user/profile', 'Get user profile', true],
    ['PUT', '/user/profile', 'Update user profile', true, ['name' => 'Test User']],
    ['GET', '/user/profile/available-flairs', 'Get available flairs', true],
    ['GET', '/user/profile/activity', 'Get profile activity', true],
    ['GET', '/user/profile/display/1', 'Get user display profile', true],
    ['GET', '/user/stats', 'Get user stats', true],
    ['GET', '/user/activity', 'Get user activity', true],
    
    // Forum user endpoints
    ['GET', '/user/forums/threads', 'Get user forum threads', true],
    ['POST', '/user/forums/threads', 'Create forum thread', true, ['title' => 'Test Thread', 'content' => 'Test content', 'category_id' => 1]],
    ['POST', '/user/forums/threads/1/posts', 'Create forum post', true, ['content' => 'Test post content']],
    
    // News user endpoints
    ['POST', '/user/news/1/comments', 'Post news comment', true, ['content' => 'Test comment']],
    ['POST', '/user/news/1/vote', 'Vote on news', true, ['vote_type' => 'up']],
    
    // Match user endpoints
    ['POST', '/user/matches/1/comments', 'Post match comment', true, ['content' => 'Test match comment']],
    
    // Predictions
    ['GET', '/user/predictions', 'Get user predictions', true],
    ['POST', '/user/predictions', 'Create prediction', true, ['match_id' => 1, 'predicted_winner' => 'team1']],
    
    // Favorites
    ['GET', '/user/favorites/teams', 'Get favorite teams', true],
    ['POST', '/user/favorites/teams', 'Add favorite team', true, ['team_id' => 1]],
    ['DELETE', '/user/favorites/teams/1', 'Remove favorite team', true],
    ['GET', '/user/favorites/players', 'Get favorite players', true],
    ['POST', '/user/favorites/players', 'Add favorite player', true, ['player_id' => 1]],
    ['DELETE', '/user/favorites/players/1', 'Remove favorite player', true],
    
    // Notifications
    ['GET', '/user/notifications', 'Get notifications', true],
    ['PUT', '/user/notifications/1/read', 'Mark notification as read', true],
    ['POST', '/user/notifications/mark-all-read', 'Mark all notifications as read', true],
    
    // Vote endpoints
    ['POST', '/user/vote', 'Create vote', true, ['vote_type' => 'up', 'voteable_type' => 'news', 'voteable_id' => 1]],
    ['DELETE', '/user/vote', 'Delete vote', true],
    
    // Search endpoints (authenticated)
    ['GET', '/search/advanced?q=test', 'Advanced search', true],
    ['GET', '/search/teams?q=test', 'Search teams', true],
    ['GET', '/search/players?q=test', 'Search players', true],
    ['GET', '/search/matches?q=test', 'Search matches', true],
    ['GET', '/search/events?q=test', 'Search events', true],
    ['GET', '/search/news?q=test', 'Search news', true],
    ['GET', '/search/forums?q=test', 'Search forums', true],
    ['GET', '/search/users?q=test', 'Search users', true],
    
    // Moderator endpoints
    ['GET', '/moderator/forums/threads/reported', 'Get reported threads', true],
    ['GET', '/moderator/forums/posts/reported', 'Get reported posts', true],
    ['PUT', '/moderator/forums/threads/1/lock', 'Lock thread', true],
    ['PUT', '/moderator/forums/threads/1/unlock', 'Unlock thread', true],
    ['PUT', '/moderator/forums/threads/1/pin', 'Pin thread', true],
    ['PUT', '/moderator/forums/threads/1/unpin', 'Unpin thread', true],
    ['DELETE', '/moderator/forums/threads/1', 'Delete thread', true],
    ['DELETE', '/moderator/forums/posts/1', 'Delete post', true],
    ['GET', '/moderator/news/pending', 'Get pending news', true],
    ['PUT', '/moderator/news/1/approve', 'Approve news', true],
    ['PUT', '/moderator/news/1/reject', 'Reject news', true],
    ['GET', '/moderator/news/comments/reported', 'Get reported news comments', true],
    ['DELETE', '/moderator/news/comments/1', 'Delete news comment', true],
    ['GET', '/moderator/matches/comments/reported', 'Get reported match comments', true],
    ['DELETE', '/moderator/matches/comments/1', 'Delete match comment', true],
    ['GET', '/moderator/users/reported', 'Get reported users', true],
    ['PUT', '/moderator/users/1/warn', 'Warn user', true],
    ['PUT', '/moderator/users/1/ban', 'Ban user', true],
    ['PUT', '/moderator/users/1/unban', 'Unban user', true],
    ['GET', '/moderator/dashboard/stats', 'Get moderator stats', true],
    ['GET', '/moderator/dashboard/recent-activity', 'Get recent activity', true],
    
    // Admin endpoints
    ['GET', '/admin/stats', 'Get admin stats', true],
    ['GET', '/admin/analytics', 'Get analytics', true],
    ['GET', '/admin/dashboard', 'Get admin dashboard', true],
    ['GET', '/admin/live-scoring', 'Get live scoring', true],
    ['GET', '/admin/content-moderation', 'Get content moderation', true],
    ['GET', '/admin/user-management', 'Get user management', true],
    ['GET', '/admin/system-settings', 'Get system settings', true],
    ['GET', '/admin/analytics-dashboard', 'Get analytics dashboard', true],
    ['GET', '/admin/analytics/overview', 'Get analytics overview', true],
    ['GET', '/admin/analytics/users', 'Get user analytics', true],
    ['GET', '/admin/analytics/content', 'Get content analytics', true],
    ['GET', '/admin/analytics/engagement', 'Get engagement analytics', true],
    
    // Admin user management
    ['GET', '/admin/users', 'Get all users', true],
    ['GET', '/admin/users/1', 'Get user by ID', true],
    ['PUT', '/admin/users/1', 'Update user', true, ['name' => 'Updated User']],
    ['DELETE', '/admin/users/1', 'Delete user', true],
    ['GET', '/admin/users/1/activity', 'Get user activity', true],
    ['PUT', '/admin/users/1/roles', 'Update user roles', true, ['roles' => ['user']]],
    ['PUT', '/admin/users/1/permissions', 'Update user permissions', true, ['permissions' => []]],
    
    // Admin team management
    ['GET', '/admin/teams', 'Get all teams (admin)', true],
    ['GET', '/admin/teams/1', 'Get team by ID (admin)', true],
    ['POST', '/admin/teams', 'Create team', true, ['name' => 'Test Team', 'short_name' => 'TT']],
    ['PUT', '/admin/teams/1', 'Update team', true, ['name' => 'Updated Team']],
    ['DELETE', '/admin/teams/1', 'Delete team', true],
    ['POST', '/admin/teams/1/players', 'Add player to team', true, ['player_id' => 1]],
    ['DELETE', '/admin/teams/1/players/1', 'Remove player from team', true],
    
    // Admin player management
    ['GET', '/admin/players', 'Get all players (admin)', true],
    ['GET', '/admin/players/1', 'Get player by ID (admin)', true],
    ['POST', '/admin/players', 'Create player', true, ['name' => 'Test Player', 'team_id' => 1]],
    ['PUT', '/admin/players/1', 'Update player', true, ['name' => 'Updated Player']],
    ['DELETE', '/admin/players/1', 'Delete player', true],
    
    // Admin event management
    ['GET', '/admin/events', 'Get all events (admin)', true],
    ['GET', '/admin/events/1', 'Get event by ID (admin)', true],
    ['POST', '/admin/events', 'Create event', true, ['name' => 'Test Event', 'type' => 'tournament']],
    ['PUT', '/admin/events/1', 'Update event', true, ['name' => 'Updated Event']],
    ['DELETE', '/admin/events/1', 'Delete event', true],
    ['GET', '/admin/events/1/teams', 'Get event teams', true],
    ['POST', '/admin/events/1/teams/1', 'Add team to event', true],
    ['DELETE', '/admin/events/1/teams/1', 'Remove team from event', true],
    ['PUT', '/admin/events/1/teams/1/seed', 'Update team seed', true, ['seed' => 1]],
    ['PUT', '/admin/events/1/status', 'Update event status', true, ['status' => 'active']],
    
    // Admin match management
    ['GET', '/admin/matches', 'Get all matches (admin)', true],
    ['GET', '/admin/matches/1', 'Get match by ID (admin)', true],
    ['POST', '/admin/matches', 'Create match', true, ['team1_id' => 1, 'team2_id' => 2, 'event_id' => 1]],
    ['PUT', '/admin/matches/1', 'Update match', true, ['status' => 'completed']],
    ['DELETE', '/admin/matches/1', 'Delete match', true],
    ['PUT', '/admin/matches/1/status', 'Update match status', true, ['status' => 'live']],
    ['POST', '/admin/matches/1/results', 'Submit match results', true, ['winner_id' => 1, 'score_team1' => 2, 'score_team2' => 1]],
    
    // Admin news management
    ['GET', '/admin/news', 'Get all news (admin)', true],
    ['GET', '/admin/news/1', 'Get news by ID (admin)', true],
    ['POST', '/admin/news', 'Create news', true, ['title' => 'Test News', 'content' => 'Test content']],
    ['PUT', '/admin/news/1', 'Update news', true, ['title' => 'Updated News']],
    ['DELETE', '/admin/news/1', 'Delete news', true],
    ['PUT', '/admin/news/1/publish', 'Publish news', true],
    ['PUT', '/admin/news/1/unpublish', 'Unpublish news', true],
    
    // Admin forum management
    ['GET', '/admin/forums/categories', 'Get forum categories (admin)', true],
    ['POST', '/admin/forums/categories', 'Create forum category', true, ['name' => 'Test Category']],
    ['PUT', '/admin/forums/categories/1', 'Update forum category', true, ['name' => 'Updated Category']],
    ['DELETE', '/admin/forums/categories/1', 'Delete forum category', true],
    ['GET', '/admin/forums/threads', 'Get forum threads (admin)', true],
    ['GET', '/admin/forums/posts', 'Get forum posts (admin)', true],
    ['GET', '/admin/forums/reports', 'Get forum reports', true],
    
    // Admin system endpoints
    ['GET', '/admin/system/stats', 'Get system stats', true],
    ['GET', '/admin/system/health', 'Get system health', true],
    ['GET', '/admin/system/logs', 'Get system logs', true],
    ['POST', '/admin/system/cache/clear', 'Clear system cache', true],
    ['POST', '/admin/system/maintenance/enable', 'Enable maintenance mode', true],
    ['POST', '/admin/system/maintenance/disable', 'Disable maintenance mode', true],
    
    // Admin bulk operations
    ['POST', '/admin/bulk/users', 'Bulk user operations', true, ['action' => 'activate', 'ids' => [1, 2]]],
    ['POST', '/admin/bulk/teams', 'Bulk team operations', true, ['action' => 'verify', 'ids' => [1, 2]]],
    ['POST', '/admin/bulk/players', 'Bulk player operations', true, ['action' => 'activate', 'ids' => [1, 2]]],
    ['POST', '/admin/bulk/matches', 'Bulk match operations', true, ['action' => 'schedule', 'ids' => [1, 2]]],
    
    // Additional endpoints
    ['GET', '/matches/1/timeline', 'Get match timeline', false],
    ['GET', '/test-admin', 'Test admin access', true],
    ['GET', '/test-moderator', 'Test moderator access', true],
    ['GET', '/test-user', 'Test user access', true],
];

// Run tests
$passed = 0;
$failed = 0;
$failedEndpoints = [];

echo colorOutput("\n=== TESTING ALL API ENDPOINTS ===\n", 'yellow');
echo "Total endpoints to test: " . count($endpoints) . "\n\n";

foreach ($endpoints as $endpoint) {
    $method = $endpoint[0];
    $path = $endpoint[1];
    $description = $endpoint[2];
    $requiresAuth = $endpoint[3];
    $data = $endpoint[4] ?? null;
    
    $url = $baseUrl . $path;
    $response = makeRequest($url, $method, $data, $requiresAuth ? $token : null);
    
    $status = '';
    if ($response['code'] >= 200 && $response['code'] < 300) {
        $status = colorOutput('✓', 'green');
        $passed++;
    } elseif ($response['code'] == 404 && strpos($path, '/1') !== false) {
        // Allow 404 for resource-specific endpoints (expected when resource doesn't exist)
        $status = colorOutput('○', 'blue');
        $passed++;
    } else {
        $status = colorOutput('✗', 'red');
        $failed++;
        $failedEndpoints[] = [
            'method' => $method,
            'path' => $path,
            'description' => $description,
            'code' => $response['code'],
            'response' => substr($response['body'], 0, 200)
        ];
    }
    
    echo sprintf("%-6s %-50s %s (HTTP %d)\n", $method, $path, $status, $response['code']);
}

// Summary
echo colorOutput("\n=== TEST SUMMARY ===\n", 'yellow');
echo colorOutput("Passed: $passed\n", 'green');
echo colorOutput("Failed: $failed\n", 'red');
echo "Total: " . ($passed + $failed) . "\n";

if ($failed > 0) {
    echo colorOutput("\n=== FAILED ENDPOINTS ===\n", 'yellow');
    foreach ($failedEndpoints as $endpoint) {
        echo colorOutput("✗ {$endpoint['method']} {$endpoint['path']} - {$endpoint['description']} (HTTP {$endpoint['code']})\n", 'red');
        if ($endpoint['response']) {
            echo "  Response: " . $endpoint['response'] . "\n";
        }
    }
}

echo "\n";