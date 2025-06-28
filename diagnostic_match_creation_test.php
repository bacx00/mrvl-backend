<?php

// ==========================================
// DIAGNOSTIC TEST FOR MATCH CREATION ISSUES
// ==========================================

echo "🔍 MARVEL RIVALS MATCH CREATION DIAGNOSTIC\n";
echo "==========================================\n";

$BASE_URL = 'https://staging.mrvl.net/api';
$ADMIN_TOKEN = 'replace_with_actual_token'; // We'll need to get this

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

echo "\n🔧 STEP 1: CREATE ADMIN USER & TOKEN\n";
echo "====================================\n";

// Try to create admin user first
$adminData = [
    'name' => 'Admin Test',
    'email' => 'admin@test.com',
    'password' => 'password123',
    'role' => 'admin'
];

$result = makeRequest('POST', $BASE_URL . '/admin/users', $adminData);
echo "Admin user creation: HTTP {$result['http_code']}\n";
if (isset($result['data'])) {
    echo "Response: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
}

// Try to login and get token
$loginData = [
    'email' => 'admin@test.com',
    'password' => 'password123'
];

$result = makeRequest('POST', $BASE_URL . '/auth/login', $loginData);
echo "\nLogin attempt: HTTP {$result['http_code']}\n";
if (isset($result['data'])) {
    echo "Response: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    if (isset($result['data']['token'])) {
        $ADMIN_TOKEN = $result['data']['token'];
        echo "✅ Got admin token: " . substr($ADMIN_TOKEN, 0, 20) . "...\n";
    }
}

echo "\n🔧 STEP 2: TEST BASIC ENDPOINTS\n";
echo "===============================\n";

// Test teams endpoint
$result = makeRequest('GET', $BASE_URL . '/teams');
echo "Teams endpoint: HTTP {$result['http_code']}\n";
if (isset($result['data']['data'])) {
    echo "Found " . count($result['data']['data']) . " teams\n";
    $teams = $result['data']['data'];
    if (count($teams) >= 2) {
        $team1 = $teams[0];
        $team2 = $teams[1];
        echo "✅ Team 1: {$team1['name']} (ID: {$team1['id']})\n";
        echo "✅ Team 2: {$team2['name']} (ID: {$team2['id']})\n";
    } else {
        echo "❌ Need at least 2 teams for match creation\n";
        exit(1);
    }
} else {
    echo "❌ Failed to get teams data\n";
    exit(1);
}

// Test events endpoint
$result = makeRequest('GET', $BASE_URL . '/events');
echo "\nEvents endpoint: HTTP {$result['http_code']}\n";
if (isset($result['data']['data'])) {
    echo "Found " . count($result['data']['data']) . " events\n";
    $events = $result['data']['data'];
    $eventId = !empty($events) ? $events[0]['id'] : null;
    if ($eventId) {
        echo "✅ Using event ID: {$eventId}\n";
    }
}

echo "\n🔧 STEP 3: TEST MATCH CREATION ENDPOINTS\n";
echo "========================================\n";

if ($ADMIN_TOKEN === 'replace_with_actual_token') {
    echo "⚠️ No admin token - skipping authenticated tests\n";
    exit(1);
}

// Test 1: Basic /admin/matches endpoint (old way)
echo "\n🧪 Test 1: Basic /admin/matches endpoint\n";
$basicMatchData = [
    'team1_id' => $team1['id'],
    'team2_id' => $team2['id'],
    'event_id' => $eventId,
    'scheduled_at' => date('c'),
    'format' => 'BO1',
    'status' => 'upcoming'
];

$result = makeRequest('POST', $BASE_URL . '/admin/matches', $basicMatchData, getAuthHeaders());
echo "Basic match creation: HTTP {$result['http_code']}\n";
if (isset($result['data'])) {
    echo "Response: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    if ($result['http_code'] === 201 && isset($result['data']['data']['id'])) {
        echo "✅ Basic match created with ID: {$result['data']['data']['id']}\n";
    }
}

// Test 2: Advanced /admin/matches/create-competitive endpoint (new way)
echo "\n🧪 Test 2: Advanced /admin/matches/create-competitive endpoint\n";
$competitiveMatchData = [
    'team1_id' => $team1['id'],
    'team2_id' => $team2['id'],
    'event_id' => $eventId,
    'match_format' => 'BO1',
    'map_pool' => [
        [
            'map_name' => 'Yggsgard: Royal Palace',
            'game_mode' => 'Domination'
        ]
    ],
    'competitive_settings' => [
        'preparation_time' => 45,
        'tactical_pauses_per_team' => 2,
        'overtime_enabled' => true
    ],
    'scheduled_at' => date('c')
];

$result = makeRequest('POST', $BASE_URL . '/admin/matches/create-competitive', $competitiveMatchData, getAuthHeaders());
echo "Competitive match creation: HTTP {$result['http_code']}\n";
if (isset($result['data'])) {
    echo "Response: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    if ($result['http_code'] === 201 && isset($result['data']['data']['match']['id'])) {
        echo "✅ Competitive match created with ID: {$result['data']['data']['match']['id']}\n";
        $competitiveMatchId = $result['data']['data']['match']['id'];
        
        echo "\n🧪 Test 3: Match Completion Endpoint\n";
        $completionData = [
            'winner_team_id' => $team1['id'],
            'final_score' => [
                'team1' => 2,
                'team2' => 1
            ],
            'match_duration' => '25:30',
            'mvp_player_id' => null
        ];
        
        $result = makeRequest('POST', $BASE_URL . "/matches/{$competitiveMatchId}/complete", $completionData);
        echo "Match completion: HTTP {$result['http_code']}\n";
        if (isset($result['data'])) {
            echo "Response: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
        }
    }
}

echo "\n🔧 STEP 4: DATABASE STRUCTURE CHECK\n";
echo "===================================\n";

// Check if required tables exist by checking a known match
$result = makeRequest('GET', $BASE_URL . '/matches');
echo "Matches list: HTTP {$result['http_code']}\n";
if (isset($result['data']['data'])) {
    echo "Found " . count($result['data']['data']) . " matches in database\n";
    if (!empty($result['data']['data'])) {
        $sampleMatch = $result['data']['data'][0];
        echo "Sample match structure:\n";
        echo json_encode($sampleMatch, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n🎯 DIAGNOSTIC COMPLETE\n";
echo "=====================\n";
echo "Run this test to identify the exact match creation issues.\n";