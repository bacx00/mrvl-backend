<?php

// ==========================================
// MARVEL RIVALS LIVE MATCH SYNC TEST - CLEAN VERSION
// ==========================================

echo "🎮 MARVEL RIVALS LIVE MATCH SYNCHRONIZATION TEST\n";
echo "===============================================\n";
echo "🌐 Server: https://staging.mrvl.net/api\n";
echo "🕒 Started: " . date('Y-m-d H:i:s') . "\n";
echo "🎯 Focus: Real-time match creation and completion\n\n";

$BASE_URL = "https://staging.mrvl.net/api";
$ADMIN_TOKEN = "456|XgZ5PbsCpIVrcpjZgeRJAhHmf8RGJBNaaWXLeczI2a360ed8";

$test_results = ['success' => [], 'failure' => []];

function logTest($name, $success, $message = '') {
    global $test_results;
    
    $timestamp = date('H:i:s.') . substr(microtime(), 2, 3);
    $result = ['name' => $name, 'message' => $message, 'timestamp' => $timestamp];
    
    if ($success) {
        $test_results['success'][] = $result;
        echo "✅ [{$timestamp}] {$name}: {$message}\n";
    } else {
        $test_results['failure'][] = $result;
        echo "❌ [{$timestamp}] {$name}: {$message}\n";
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
// CORE TEST FUNCTIONS
// ==========================================

function testBasicSetup() {
    global $BASE_URL;
    
    echo "\n🔧 STEP 1: BASIC SETUP TEST\n";
    echo "===========================\n";
    
    // Test teams endpoint
    $result = makeRequest('GET', $BASE_URL . '/teams');
    if ($result['http_code'] === 200 && isset($result['data']['data'])) {
        $teams = $result['data']['data'];
        if (count($teams) >= 2) {
            logTest("Teams Available", true, "Found " . count($teams) . " teams");
            return $teams;
        } else {
            logTest("Teams Available", false, "Need at least 2 teams, found " . count($teams));
            return false;
        }
    } else {
        logTest("Teams Available", false, "Failed to fetch teams");
        return false;
    }
}

function testLiveMatchCreation($teams) {
    global $BASE_URL;
    
    echo "\n🎮 STEP 2: LIVE MATCH CREATION TEST\n";
    echo "===================================\n";
    
    $gameModes = [
        ['name' => 'Domination', 'map' => 'Yggsgard: Royal Palace'],
        ['name' => 'Convoy', 'map' => 'Tokyo 2099: Spider-Islands'],
        ['name' => 'Convergence', 'map' => 'Tokyo 2099: Shin-Shibuya'],
        ['name' => 'Conquest', 'map' => 'Tokyo 2099: Ninomaru']
    ];
    
    $createdMatches = [];
    
    foreach ($gameModes as $mode) {
        $matchData = [
            'team1_id' => $teams[0]['id'],
            'team2_id' => $teams[1]['id'],
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
                'overtime_enabled' => true
            ],
            'scheduled_at' => date('c')
        ];
        
        $result = makeRequest('POST', $BASE_URL . '/admin/matches/create-competitive', $matchData, getAuthHeaders());
        
        if ($result['http_code'] === 201 && isset($result['data']['success']) && $result['data']['success']) {
            $matchId = $result['data']['data']['match']['id'];
            $createdMatches[] = $matchId;
            logTest("Live Match Creation", true, 
                "Created {$mode['name']} match on {$mode['map']} - ID: {$matchId}");
        } else {
            $errorMsg = isset($result['data']['message']) ? $result['data']['message'] : 'Unknown error';
            logTest("Live Match Creation", false, "Failed to create {$mode['name']} match: {$errorMsg}");
        }
    }
    
    return $createdMatches;
}

function testMatchCompletion($matchIds, $teams) {
    global $BASE_URL;
    
    echo "\n🏁 STEP 3: MATCH COMPLETION TEST\n";
    echo "================================\n";
    
    foreach ($matchIds as $matchId) {
        $completionData = [
            'winner_team_id' => $teams[0]['id'],
            'final_score' => '2-1',
            'duration' => 1800,
            'mvp_player_id' => null
        ];
        
        $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/complete", $completionData, getAuthHeaders());
        
        if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
            logTest("Match Completion", true, "Successfully completed match {$matchId}");
        } else {
            $errorMsg = isset($result['data']['message']) ? $result['data']['message'] : 'Unknown error';
            logTest("Match Completion", false, "Failed to complete match {$matchId}: {$errorMsg}");
        }
    }
}

function testAnalyticsEndpoints() {
    global $BASE_URL;
    
    echo "\n📊 STEP 4: ANALYTICS ENDPOINTS TEST\n";
    echo "===================================\n";
    
    $endpoints = [
        '/analytics/teams/performance',
        '/analytics/players/leaderboards', 
        '/analytics/matches/recent'
    ];
    
    foreach ($endpoints as $endpoint) {
        $result = makeRequest('GET', $BASE_URL . $endpoint);
        
        if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
            logTest("Analytics Endpoint", true, "✅ {$endpoint}");
        } else {
            logTest("Analytics Endpoint", false, "❌ {$endpoint}");
        }
    }
}

// ==========================================
// RUN ALL TESTS
// ==========================================

$startTime = microtime(true);

// Step 1: Test basic setup
$teams = testBasicSetup();
if (!$teams) {
    echo "\n❌ CRITICAL ERROR: Cannot proceed without teams\n";
    exit(1);
}

// Step 2: Test live match creation
$matchIds = testLiveMatchCreation($teams);

// Step 3: Test match completion
if (!empty($matchIds)) {
    testMatchCompletion($matchIds, $teams);
}

// Step 4: Test analytics endpoints
testAnalyticsEndpoints();

// ==========================================
// FINAL REPORT
// ==========================================

$endTime = microtime(true);
$totalTime = round($endTime - $startTime, 2);

echo "\n🏆 LIVE SYNCHRONIZATION TEST - FINAL REPORT\n";
echo "==========================================\n";
echo "⏱️  Total Execution Time: {$totalTime} seconds\n";
echo "📊 Total Tests: " . (count($test_results['success']) + count($test_results['failure'])) . "\n";
echo "✅ Successful: " . count($test_results['success']) . "\n";
echo "❌ Failed: " . count($test_results['failure']) . "\n";

$successRate = count($test_results['success']) / (count($test_results['success']) + count($test_results['failure'])) * 100;
echo "📈 Success Rate: " . round($successRate, 2) . "%\n\n";

if (count($test_results['failure']) > 0) {
    echo "❌ FAILED TESTS:\n";
    echo "===============\n";
    foreach ($test_results['failure'] as $failure) {
        echo "- [{$failure['timestamp']}] {$failure['name']}: {$failure['message']}\n";
    }
} else {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "==================\n";
    echo "✅ Live match creation working perfectly\n";
    echo "✅ Match completion working perfectly\n";
    echo "✅ Analytics endpoints working perfectly\n";
    echo "✅ Marvel Rivals esports platform ready!\n";
}

echo "\n🎯 EXPECTED RESULTS:\n";
echo "===================\n";
echo "✅ Live Match Creation: 100% Success Rate\n";
echo "✅ Match Completion: 100% Success Rate\n";
echo "✅ All Analytics Endpoints: Working\n";
echo "✅ System Ready for Tournament Use\n";