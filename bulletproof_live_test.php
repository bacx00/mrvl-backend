<?php

// ==========================================
// MARVEL RIVALS LIVE MATCH SYNC TEST - BULLETPROOF VERSION
// ==========================================

echo "🎮 MARVEL RIVALS LIVE MATCH SYNCHRONIZATION TEST\n";
echo "===============================================\n";
echo "🌐 Server: https://staging.mrvl.net/api\n";
echo "🕒 Started: " . date('Y-m-d H:i:s') . "\n";
echo "🎯 Focus: Real-time match creation and completion\n\n";

$BASE_URL = "https://staging.mrvl.net/api";
$ADMIN_TOKEN = "456|XgZ5PbsCpIVrcpjZgeRJAhHmf8RGJBNaaWXLeczI2a360ed8";

$test_results = ['success' => [], 'failure' => []];

function logTest($name, $success, $message = '', $data = null) {
    global $test_results;
    
    $timestamp = date('H:i:s.') . substr(microtime(), 2, 3);
    $result = ['name' => $name, 'message' => $message, 'timestamp' => $timestamp];
    if ($data) $result['data'] = $data;
    
    if ($success) {
        $test_results['success'][] = $result;
        echo "✅ [{$timestamp}] {$name}: {$message}\n";
    } else {
        $test_results['failure'][] = $result;
        echo "❌ [{$timestamp}] {$name}: {$message}\n";
        if ($data) {
            echo "   Debug: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
    }
}

function makeRequest($method, $url, $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

function getAuthHeaders() {
    global $ADMIN_TOKEN;
    return ['Authorization: Bearer ' . $ADMIN_TOKEN];
}

// ==========================================
// STEP 1: AUTHENTICATION TEST
// ==========================================

function testAuthentication() {
    global $BASE_URL, $ADMIN_TOKEN;
    
    echo "\n🔐 STEP 1: AUTHENTICATION TEST\n";
    echo "==============================\n";
    
    $result = makeRequest('GET', $BASE_URL . '/user', null, getAuthHeaders());
    
    if ($result['http_code'] === 200 && isset($result['data']['success'])) {
        logTest("Authentication", true, "Admin token valid");
        return true;
    } else {
        logTest("Authentication", false, "Invalid admin token", $result['data']);
        
        // Try to get new token
        echo "🔄 Attempting to login...\n";
        $loginData = [
            'email' => 'admin@test.com',
            'password' => 'password123'
        ];
        
        $loginResult = makeRequest('POST', $BASE_URL . '/auth/login', $loginData);
        if ($loginResult['http_code'] === 200 && isset($loginResult['data']['token'])) {
            $ADMIN_TOKEN = $loginResult['data']['token'];
            logTest("Token Refresh", true, "Got new token: " . substr($ADMIN_TOKEN, 0, 20) . "...");
            return true;
        } else {
            logTest("Token Refresh", false, "Could not get new token", $loginResult['data']);
            return false;
        }
    }
}

// ==========================================
// STEP 2: DATA VALIDATION TEST  
// ==========================================

function testDataSetup() {
    global $BASE_URL;
    
    echo "\n🔧 STEP 2: DATA SETUP VALIDATION\n";
    echo "================================\n";
    
    // Test teams
    $result = makeRequest('GET', $BASE_URL . '/teams');
    if ($result['http_code'] !== 200 || !isset($result['data']['data'])) {
        logTest("Teams Data", false, "Cannot fetch teams", $result['data']);
        return false;
    }
    
    $teams = $result['data']['data'];
    if (count($teams) < 2) {
        logTest("Teams Data", false, "Need at least 2 teams, found " . count($teams));
        return false;
    }
    
    logTest("Teams Data", true, "Found " . count($teams) . " teams");
    
    // Validate first 2 teams have required fields
    $team1 = $teams[0];
    $team2 = $teams[1];
    
    if (!isset($team1['id']) || !isset($team2['id'])) {
        logTest("Team IDs", false, "Teams missing ID field");
        return false;
    }
    
    logTest("Team IDs", true, "Team1: {$team1['id']}, Team2: {$team2['id']}");
    
    // Test events (optional but good to have)
    $result = makeRequest('GET', $BASE_URL . '/events');
    $events = [];
    if ($result['http_code'] === 200 && isset($result['data']['data'])) {
        $events = $result['data']['data'];
        logTest("Events Data", true, "Found " . count($events) . " events");
    } else {
        logTest("Events Data", false, "No events found (optional)");
    }
    
    return [
        'teams' => [$team1, $team2],
        'events' => $events
    ];
}

// ==========================================
// STEP 3: LIVE MATCH CREATION TEST
// ==========================================

function testLiveMatchCreation($testData) {
    global $BASE_URL;
    
    echo "\n🎮 STEP 3: LIVE MATCH CREATION TEST\n";
    echo "===================================\n";
    
    $teams = $testData['teams'];
    $events = $testData['events'];
    
    // Use exact game mode names from validation rules
    $gameModes = [
        ['name' => 'Domination', 'map' => 'Yggsgard: Royal Palace'],
        ['name' => 'Convoy', 'map' => 'Tokyo 2099: Spider-Islands'],
        ['name' => 'Convergence', 'map' => 'Tokyo 2099: Shin-Shibuya'],
        ['name' => 'Conquest', 'map' => 'Tokyo 2099: Ninomaru']
    ];
    
    $createdMatches = [];
    
    foreach ($gameModes as $mode) {
        // Create comprehensive match data
        $matchData = [
            'team1_id' => (int)$teams[0]['id'],
            'team2_id' => (int)$teams[1]['id'],
            'match_format' => 'BO1',
            'map_pool' => [
                [
                    'map_name' => $mode['map'],
                    'game_mode' => $mode['name']
                ]
            ],
            'competitive_settings' => [
                'preparation_time' => 45,
                'tactical_pauses_per_team' => 2,
                'pause_duration' => 120,
                'overtime_enabled' => true,
                'hero_selection_time' => 30
            ],
            'scheduled_at' => date('c')
        ];
        
        // Add event if available
        if (!empty($events)) {
            $matchData['event_id'] = (int)$events[0]['id'];
        }
        
        echo "Creating {$mode['name']} match...\n";
        $result = makeRequest('POST', $BASE_URL . '/admin/matches/create-competitive', $matchData, getAuthHeaders());
        
        if ($result['http_code'] === 201 && isset($result['data']['success']) && $result['data']['success']) {
            $matchId = $result['data']['data']['match']['id'];
            $createdMatches[] = $matchId;
            logTest("Live Match Creation", true, 
                "Created {$mode['name']} match on {$mode['map']} - ID: {$matchId}");
        } else {
            $errorMsg = 'Unknown error';
            $debugData = null;
            
            if (isset($result['data']['message'])) {
                $errorMsg = $result['data']['message'];
            }
            if (isset($result['data']['errors'])) {
                $debugData = $result['data']['errors'];
                $errorMsg .= ' - See debug data';
            }
            
            logTest("Live Match Creation", false, 
                "Failed to create {$mode['name']} match: {$errorMsg}", $debugData);
        }
    }
    
    return $createdMatches;
}

// ==========================================
// STEP 4: MATCH COMPLETION TEST
// ==========================================

function testMatchCompletion($matchIds, $teams) {
    global $BASE_URL;
    
    if (empty($matchIds)) {
        echo "\n⚠️  STEP 4: SKIPPING MATCH COMPLETION (No matches created)\n";
        return;
    }
    
    echo "\n🏁 STEP 4: MATCH COMPLETION TEST\n";
    echo "================================\n";
    
    foreach ($matchIds as $index => $matchId) {
        $completionData = [
            'winner_team_id' => (int)$teams[$index % 2]['id'], // Alternate winners
            'final_score' => '2-1',
            'duration' => 1800 + ($index * 300), // Vary duration
            'mvp_player_id' => null
        ];
        
        echo "Completing match {$matchId}...\n";
        $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/complete", $completionData, getAuthHeaders());
        
        if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
            logTest("Match Completion", true, "Successfully completed match {$matchId}");
        } else {
            $errorMsg = isset($result['data']['message']) ? $result['data']['message'] : 'Unknown error';
            $debugData = isset($result['data']['errors']) ? $result['data']['errors'] : null;
            logTest("Match Completion", false, "Failed to complete match {$matchId}: {$errorMsg}", $debugData);
        }
    }
}

// ==========================================
// STEP 5: ANALYTICS ENDPOINTS TEST
// ==========================================

function testAnalyticsEndpoints() {
    global $BASE_URL;
    
    echo "\n📊 STEP 5: ANALYTICS ENDPOINTS TEST\n";
    echo "===================================\n";
    
    $endpoints = [
        [
            'url' => '/analytics/teams/performance',
            'name' => 'Team Performance',
            'params' => '?timeframe=month&limit=10'
        ],
        [
            'url' => '/analytics/players/leaderboards', 
            'name' => 'Player Leaderboards',
            'params' => '?timeframe=all&limit=20'
        ],
        [
            'url' => '/analytics/matches/recent',
            'name' => 'Recent Matches',
            'params' => '?limit=10&status=all'
        ]
    ];
    
    foreach ($endpoints as $endpoint) {
        $fullUrl = $BASE_URL . $endpoint['url'] . $endpoint['params'];
        $result = makeRequest('GET', $fullUrl);
        
        if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
            logTest("Analytics Endpoint", true, "✅ {$endpoint['name']}");
        } else {
            $errorMsg = isset($result['data']['message']) ? $result['data']['message'] : "HTTP {$result['http_code']}";
            logTest("Analytics Endpoint", false, "❌ {$endpoint['name']}: {$errorMsg}");
        }
    }
}

// ==========================================
// STEP 6: HEROES COUNT VALIDATION
// ==========================================

function testHeroesCount() {
    global $BASE_URL;
    
    echo "\n🦸 STEP 6: HEROES COUNT VALIDATION\n";
    echo "==================================\n";
    
    $result = makeRequest('GET', $BASE_URL . '/game-data/all-heroes');
    
    if ($result['http_code'] === 200 && isset($result['data']['data'])) {
        $heroes = $result['data']['data'];
        $heroCount = count($heroes);
        
        if ($heroCount === 39) {
            logTest("Heroes Count", true, "✅ Found all 39 Marvel Rivals heroes");
        } else {
            logTest("Heroes Count", false, "❌ Expected 39 heroes, found {$heroCount}");
        }
        
        // Count by role
        $roleCount = [];
        foreach ($heroes as $hero) {
            $role = $hero['role'];
            $roleCount[$role] = ($roleCount[$role] ?? 0) + 1;
        }
        
        foreach ($roleCount as $role => $count) {
            logTest("Heroes by Role", true, "{$role}: {$count} heroes");
        }
    } else {
        logTest("Heroes Count", false, "Failed to fetch heroes data");
    }
}

// ==========================================
// RUN ALL TESTS
// ==========================================

$startTime = microtime(true);

// Step 1: Test authentication
if (!testAuthentication()) {
    echo "\n❌ CRITICAL ERROR: Cannot proceed without authentication\n";
    exit(1);
}

// Step 2: Test data setup
$testData = testDataSetup();
if (!$testData) {
    echo "\n❌ CRITICAL ERROR: Cannot proceed without valid test data\n";
    exit(1);
}

// Step 3: Test live match creation
$matchIds = testLiveMatchCreation($testData);

// Step 4: Test match completion
testMatchCompletion($matchIds, $testData['teams']);

// Step 5: Test analytics endpoints
testAnalyticsEndpoints();

// Step 6: Test heroes count
testHeroesCount();

// ==========================================
// FINAL REPORT
// ==========================================

$endTime = microtime(true);
$totalTime = round($endTime - $startTime, 2);

echo "\n🏆 MARVEL RIVALS LIVE SYNC TEST - FINAL REPORT\n";
echo "============================================\n";
echo "⏱️  Total Execution Time: {$totalTime} seconds\n";
echo "📊 Total Tests: " . (count($test_results['success']) + count($test_results['failure'])) . "\n";
echo "✅ Successful: " . count($test_results['success']) . "\n";
echo "❌ Failed: " . count($test_results['failure']) . "\n";

if (count($test_results['success']) + count($test_results['failure']) > 0) {
    $successRate = count($test_results['success']) / (count($test_results['success']) + count($test_results['failure'])) * 100;
    echo "📈 Success Rate: " . round($successRate, 2) . "%\n\n";
} else {
    echo "📈 Success Rate: 0%\n\n";
}

if (count($test_results['failure']) > 0) {
    echo "❌ FAILED TESTS SUMMARY:\n";
    echo "========================\n";
    foreach ($test_results['failure'] as $failure) {
        echo "- [{$failure['timestamp']}] {$failure['name']}: {$failure['message']}\n";
    }
    echo "\n🔧 FIXES NEEDED:\n";
    echo "================\n";
    echo "1. Check validation errors in match creation\n";
    echo "2. Verify team analytics endpoint\n";
    echo "3. Ensure all database tables exist\n";
    echo "4. Verify heroes data is properly seeded\n";
} else {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "===================\n";
    echo "✅ Authentication working perfectly\n";
    echo "✅ Live match creation working perfectly\n";
    echo "✅ Match completion working perfectly\n";
    echo "✅ Analytics endpoints working perfectly\n";
    echo "✅ Heroes data complete (39 heroes)\n";
    echo "✅ Marvel Rivals esports platform READY!\n";
}

echo "\n🚀 SYSTEM STATUS:\n";
echo "=================\n";
if (count($test_results['failure']) === 0) {
    echo "🟢 LIVE SCORING SYSTEM: FULLY OPERATIONAL\n";
    echo "🟢 MATCH LIFECYCLE: FULLY OPERATIONAL\n";
    echo "🟢 READY FOR TOURNAMENT USE\n";
} else {
    echo "🟡 LIVE SCORING SYSTEM: NEEDS FIXES\n";
    echo "🟡 MATCH LIFECYCLE: PARTIAL FUNCTIONALITY\n";
    echo "🟡 REQUIRES DEBUGGING BEFORE TOURNAMENT USE\n";
}