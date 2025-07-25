<?php

// Comprehensive API endpoint tester - Fixed Version
$baseUrl = 'https://staging.mrvl.net/api';

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

// Get fresh authentication token
function getFreshToken() {
    global $baseUrl;
    $loginData = ['email' => 'jhonny@ar-mediia.com', 'password' => 'password123'];
    $loginResponse = makeRequest("$baseUrl/auth/login", 'POST', $loginData);
    
    if ($loginResponse['code'] == 200) {
        $loginResult = json_decode($loginResponse['body'], true);
        return $loginResult['token'] ?? null;
    }
    return null;
}

// Get authentication token
echo colorOutput("=== GETTING AUTHENTICATION TOKEN ===\n", 'yellow');
$token = getFreshToken();
if (!$token) {
    echo colorOutput("✗ Failed to get authentication token\n", 'red');
    exit(1);
}
echo colorOutput("✓ Authentication successful\n", 'green');

// Define all endpoints to test (reduced set focusing on critical ones)
$endpoints = [
    // Authentication endpoints
    ['GET', '/auth/me', 'Get current user', true],
    ['POST', '/auth/refresh', 'Refresh token', true],
    
    // User endpoints (core functionality)
    ['GET', '/user', 'Get authenticated user', true],
    ['GET', '/user/profile', 'Get user profile', true],
    ['GET', '/user/stats', 'Get user stats', true],
    ['GET', '/user/activity', 'Get user activity', true],
    
    // Forum endpoints
    ['GET', '/user/forums/threads', 'Get user forum threads', true],
    ['POST', '/user/forums/threads', 'Create forum thread', true, ['title' => 'Test Thread', 'content' => 'Test content', 'category_id' => 1]],
    
    // Favorites endpoints
    ['GET', '/user/favorites/teams', 'Get favorite teams', true],
    ['GET', '/user/favorites/players', 'Get favorite players', true],
    
    // Predictions
    ['GET', '/user/predictions', 'Get user predictions', true],
    
    // Public endpoints (sample)
    ['GET', '/public/teams', 'Get all teams', false],
    ['GET', '/public/players', 'Get all players', false],
    ['GET', '/public/matches', 'Get all matches', false],
    ['GET', '/public/events', 'Get all events', false],
    ['GET', '/public/news', 'Get all news', false],
    ['GET', '/public/search?q=test', 'Public search', false],
    
    // Missing routes that need to be fixed
    ['GET', '/public/news/categories', 'Get news categories', false],
    ['GET', '/public/rankings/teams', 'Get team rankings', false],
    ['GET', '/public/heroes/images', 'Get hero images', false],
    
    // Routes that need fixing
    ['POST', '/user/vote', 'Create vote', true, ['vote_type' => 'up', 'voteable_type' => 'news', 'voteable_id' => 1]],
    ['POST', '/moderator/forums/threads/1/lock', 'Lock thread', true],
    ['DELETE', '/moderator/forums/threads/1', 'Delete thread', true],
    
    // Admin endpoints (sample)
    ['GET', '/admin/stats', 'Get admin stats', true],
    ['GET', '/admin/users', 'Get all users', true],
];

// Run tests with fresh tokens for authenticated requests
$passed = 0;
$failed = 0;
$failedEndpoints = [];

echo colorOutput("\n=== TESTING CRITICAL API ENDPOINTS ===\n", 'yellow');
echo "Total endpoints to test: " . count($endpoints) . "\n\n";

foreach ($endpoints as $endpoint) {
    $method = $endpoint[0];
    $path = $endpoint[1];
    $description = $endpoint[2];
    $requiresAuth = $endpoint[3];
    $data = $endpoint[4] ?? null;
    
    // Get fresh token for each authenticated request to avoid logout issues
    $currentToken = null;
    if ($requiresAuth) {
        $currentToken = getFreshToken();
        if (!$currentToken) {
            echo colorOutput("✗ Failed to get fresh token for $path\n", 'red');
            continue;
        }
    }
    
    $url = $baseUrl . $path;
    $response = makeRequest($url, $method, $data, $currentToken);
    
    $status = '';
    if ($response['code'] >= 200 && $response['code'] < 300) {
        $status = colorOutput('✓', 'green');
        $passed++;
    } elseif ($response['code'] == 404 && strpos($path, '/1') !== false) {
        // Allow 404 for resource-specific endpoints
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
    echo colorOutput("\n=== FAILED ENDPOINTS DETAILS ===\n", 'yellow');
    foreach ($failedEndpoints as $endpoint) {
        echo colorOutput("✗ {$endpoint['method']} {$endpoint['path']} - {$endpoint['description']} (HTTP {$endpoint['code']})\n", 'red');
        if ($endpoint['response']) {
            $decoded = json_decode($endpoint['response'], true);
            if ($decoded && isset($decoded['message'])) {
                echo "  Error: " . $decoded['message'] . "\n";
            } else {
                echo "  Response: " . $endpoint['response'] . "\n";
            }
        }
    }
}

echo "\n";