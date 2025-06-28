<?php
/**
 * MARVEL RIVALS PROFESSIONAL LIVE SCORING SYSTEM - COMPLETE TEST SUITE
 * 
 * Run this file to test all Marvel Rivals API endpoints
 * Usage: php test_marvel_rivals_api.php
 * 
 * Or access via web browser: http://your-domain.com/test_marvel_rivals_api.php
 */

// Configuration - Update these for your server
$BASE_URL = "https://staging.mrvl.net/api"; // Your staging server
$ADMIN_EMAIL = "admin@marvelrivals.com";
$ADMIN_PASSWORD = "password123";

// Alternative URLs to try if default fails
$ALTERNATIVE_URLS = [
    "http://staging.mrvl.net/api",  // HTTP version
    "https://staging.mrvl.net/api", // HTTPS version
];

// Test results tracking
$test_results = [
    'success' => [],
    'failure' => []
];

// Function to log test results
function logTest($name, $success, $message = '') {
    global $test_results;
    
    if ($success) {
        $test_results['success'][] = ['name' => $name, 'message' => $message];
        echo "✅ {$name}: {$message}\n";
    } else {
        $test_results['failure'][] = ['name' => $name, 'message' => $message];
        echo "❌ {$name}: {$message}\n";
    }
}

// Function to make HTTP requests
function makeRequest($method, $url, $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Set method
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
    
    // Set data
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }
    
    // Set headers
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

// Function to get admin token
function getAdminToken() {
    global $BASE_URL, $ADMIN_EMAIL, $ADMIN_PASSWORD;
    
    $result = makeRequest('POST', $BASE_URL . '/auth/login', [
        'email' => $ADMIN_EMAIL,
        'password' => $ADMIN_PASSWORD
    ]);
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        return $result['data']['token'] ?? null;
    }
    
    return null;
}

// Test 1: Create BO1 Competitive Match
function testCreateBO1Match() {
    global $BASE_URL;
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Create BO1 Match", false, "Failed to get admin token");
        return null;
    }
    
    $matchData = [
        "team1_id" => 87,  // Sentinels
        "team2_id" => 88,  // Another team
        "match_format" => "BO1",
        "map_pool" => [
            [
                "map_name" => "Yggsgard: Royal Palace",
                "game_mode" => "Domination"
            ]
        ],
        "scheduled_at" => date('c', strtotime('+1 hour'))
    ];
    
    $result = makeRequest('POST', $BASE_URL . '/admin/matches/create-competitive', $matchData, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($result['http_code'] === 201 && isset($result['data']['success']) && $result['data']['success']) {
        $matchId = $result['data']['data']['match']['id'] ?? null;
        $rounds = $result['data']['data']['rounds'] ?? [];
        
        if (count($rounds) === 1) {
            logTest("Create BO1 Match", true, "Successfully created BO1 match with ID {$matchId} and 1 round");
            return $matchId;
        } else {
            logTest("Create BO1 Match", false, "Expected 1 round for BO1, got " . count($rounds));
        }
    } else {
        logTest("Create BO1 Match", false, "Request failed: " . ($result['data']['message'] ?? 'Unknown error'));
    }
    
    return null;
}

// Test 2: Create BO3 Competitive Match
function testCreateBO3Match() {
    global $BASE_URL;
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Create BO3 Match", false, "Failed to get admin token");
        return null;
    }
    
    $matchData = [
        "team1_id" => 87,  // Sentinels
        "team2_id" => 86,  // T1
        "match_format" => "BO3",
        "map_pool" => [
            [
                "map_name" => "Yggsgard: Royal Palace",
                "game_mode" => "Domination"
            ],
            [
                "map_name" => "Tokyo 2099: Spider-Islands",
                "game_mode" => "Convoy"
            ],
            [
                "map_name" => "Wakanda: Birnin T'Challa",
                "game_mode" => "Domination"
            ]
        ],
        "scheduled_at" => date('c', strtotime('+2 hours'))
    ];
    
    $result = makeRequest('POST', $BASE_URL . '/admin/matches/create-competitive', $matchData, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($result['http_code'] === 201 && isset($result['data']['success']) && $result['data']['success']) {
        $matchId = $result['data']['data']['match']['id'] ?? null;
        $rounds = $result['data']['data']['rounds'] ?? [];
        
        if (count($rounds) === 3) {
            logTest("Create BO3 Match", true, "Successfully created BO3 match with ID {$matchId} and 3 rounds");
            return $matchId;
        } else {
            logTest("Create BO3 Match", false, "Expected 3 rounds for BO3, got " . count($rounds));
        }
    } else {
        logTest("Create BO3 Match", false, "Request failed: " . ($result['data']['message'] ?? 'Unknown error'));
    }
    
    return null;
}

// Test 3: Create BO5 Competitive Match
function testCreateBO5Match() {
    global $BASE_URL;
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Create BO5 Match", false, "Failed to get admin token");
        return null;
    }
    
    $matchData = [
        "team1_id" => 87,  // Sentinels
        "team2_id" => 86,  // T1
        "match_format" => "BO5",
        "map_pool" => [
            [
                "map_name" => "Yggsgard: Royal Palace",
                "game_mode" => "Domination"
            ],
            [
                "map_name" => "Tokyo 2099: Spider-Islands",
                "game_mode" => "Convoy"
            ],
            [
                "map_name" => "Wakanda: Birnin T'Challa",
                "game_mode" => "Domination"
            ],
            [
                "map_name" => "Hydra Charteris Base: Hell's Heaven",
                "game_mode" => "Domination"
            ],
            [
                "map_name" => "Tokyo 2099: Shin-Shibuya",
                "game_mode" => "Convergence"
            ]
        ],
        "scheduled_at" => date('c', strtotime('+3 hours'))
    ];
    
    $result = makeRequest('POST', $BASE_URL . '/admin/matches/create-competitive', $matchData, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($result['http_code'] === 201 && isset($result['data']['success']) && $result['data']['success']) {
        $matchId = $result['data']['data']['match']['id'] ?? null;
        $rounds = $result['data']['data']['rounds'] ?? [];
        
        if (count($rounds) === 5) {
            logTest("Create BO5 Match", true, "Successfully created BO5 match with ID {$matchId} and 5 rounds");
            return $matchId;
        } else {
            logTest("Create BO5 Match", false, "Expected 5 rounds for BO5, got " . count($rounds));
        }
    } else {
        logTest("Create BO5 Match", false, "Request failed: " . ($result['data']['message'] ?? 'Unknown error'));
    }
    
    return null;
}

// Test 4: Timer Management System
function testTimerManagement($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Timer Management", false, "No match ID provided");
        return false;
    }
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Timer Management", false, "Failed to get admin token");
        return false;
    }
    
    $headers = ['Authorization: Bearer ' . $token];
    
    // Test start preparation timer
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/timer/start-preparation", [
        'duration_seconds' => 45,
        'phase' => 'hero_selection'
    ], $headers);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Start Preparation Timer", true, "Successfully started preparation timer");
    } else {
        logTest("Start Preparation Timer", false, "Failed to start preparation timer");
        return false;
    }
    
    // Test start match timer
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/timer/start-match", [
        'duration_seconds' => 600
    ], $headers);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Start Match Timer", true, "Successfully started match timer");
    } else {
        logTest("Start Match Timer", false, "Failed to start match timer");
        return false;
    }
    
    // Test pause timer
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/timer/pause", null, $headers);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Pause Timer", true, "Successfully paused timer");
    } else {
        logTest("Pause Timer", false, "Failed to pause timer");
        return false;
    }
    
    // Test resume timer
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/timer/resume", null, $headers);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Resume Timer", true, "Successfully resumed timer");
    } else {
        logTest("Resume Timer", false, "Failed to resume timer");
        return false;
    }
    
    // Test overtime timer
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/timer/overtime", [
        'grace_period_ms' => 500,
        'extended_duration' => 180
    ], $headers);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Overtime Timer", true, "Successfully started overtime timer");
    } else {
        logTest("Overtime Timer", false, "Failed to start overtime timer");
        return false;
    }
    
    return true;
}

// Test 5: 6v6 Team Composition Management
function testTeamComposition($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Team Composition", false, "No match ID provided");
        return false;
    }
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Team Composition", false, "Failed to get admin token");
        return false;
    }
    
    $compositionData = [
        "round_number" => 1,
        "team1_composition" => [
            ["player_id" => 183, "hero" => "Iron Man", "role" => "Duelist"],
            ["player_id" => 184, "hero" => "Black Panther", "role" => "Duelist"],
            ["player_id" => 185, "hero" => "Thor", "role" => "Vanguard"],
            ["player_id" => 186, "hero" => "Doctor Strange", "role" => "Vanguard"],
            ["player_id" => 187, "hero" => "Luna Snow", "role" => "Strategist"],
            ["player_id" => 188, "hero" => "Rocket Raccoon", "role" => "Strategist"]
        ],
        "team2_composition" => [
            ["player_id" => 189, "hero" => "Scarlet Witch", "role" => "Duelist"],
            ["player_id" => 190, "hero" => "Spider-Man", "role" => "Duelist"],
            ["player_id" => 191, "hero" => "Hulk", "role" => "Vanguard"],
            ["player_id" => 192, "hero" => "Captain America", "role" => "Vanguard"],
            ["player_id" => 193, "hero" => "Mantis", "role" => "Strategist"],
            ["player_id" => 194, "hero" => "Adam Warlock", "role" => "Strategist"]
        ]
    ];
    
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/team-composition", $compositionData, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Team Composition", true, "Successfully updated 6v6 team compositions");
        return true;
    } else {
        logTest("Team Composition", false, "Failed to update team compositions: " . ($result['data']['message'] ?? 'Unknown error'));
        return false;
    }
}

// Test 6: Player Statistics Update
function testPlayerStatsUpdate($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Player Stats Update", false, "No match ID provided");
        return false;
    }
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Player Stats Update", false, "Failed to get admin token");
        return false;
    }
    
    $playerId = 183; // SicK from Sentinels
    $statsData = [
        "eliminations" => 15,
        "deaths" => 4,
        "assists" => 12,
        "damage" => 8500,
        "healing" => 0,
        "damage_blocked" => 2100,
        "final_blows" => 10,
        "environmental_kills" => 2,
        "accuracy_percentage" => 67.5,
        "critical_hits" => 25,
        "hero_played" => "Iron Man",
        "role_played" => "Duelist"
    ];
    
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/player/{$playerId}/stats", $statsData, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Player Stats Update", true, "Successfully updated stats for player {$playerId}");
        return true;
    } else {
        logTest("Player Stats Update", false, "Failed to update player stats: " . ($result['data']['message'] ?? 'Unknown error'));
        return false;
    }
}

// Test 7: Bulk Player Statistics Update
function testBulkPlayerStatsUpdate($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Bulk Player Stats Update", false, "No match ID provided");
        return false;
    }
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Bulk Player Stats Update", false, "Failed to get admin token");
        return false;
    }
    
    $bulkStatsData = [
        "player_stats" => [
            [
                "player_id" => 183,
                "eliminations" => 18,
                "deaths" => 5,
                "assists" => 10,
                "damage" => 9500,
                "hero_played" => "Iron Man",
                "role_played" => "Duelist"
            ],
            [
                "player_id" => 184,
                "eliminations" => 16,
                "deaths" => 6,
                "assists" => 8,
                "damage" => 8200,
                "hero_played" => "Black Panther",
                "role_played" => "Duelist"
            ],
            [
                "player_id" => 185,
                "eliminations" => 12,
                "deaths" => 7,
                "assists" => 15,
                "damage" => 7500,
                "damage_blocked" => 5000,
                "hero_played" => "Thor",
                "role_played" => "Vanguard"
            ]
        ]
    ];
    
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/bulk-player-stats", $bulkStatsData, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        $playersUpdated = $result['data']['data']['players_updated'] ?? 0;
        logTest("Bulk Player Stats Update", true, "Successfully updated stats for {$playersUpdated} players");
        return true;
    } else {
        logTest("Bulk Player Stats Update", false, "Failed to update bulk player stats: " . ($result['data']['message'] ?? 'Unknown error'));
        return false;
    }
}

// Test 8: Live Scoreboard
function testLiveScoreboard($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Live Scoreboard", false, "No match ID provided");
        return false;
    }
    
    $result = makeRequest('GET', $BASE_URL . "/matches/{$matchId}/live-scoreboard");
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Live Scoreboard", true, "Successfully retrieved live scoreboard data");
        return true;
    } else {
        logTest("Live Scoreboard", false, "Failed to retrieve live scoreboard: " . ($result['data']['message'] ?? 'Unknown error'));
        return false;
    }
}

// Test 9: Admin Live Control Dashboard
function testAdminLiveControl($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Admin Live Control", false, "No match ID provided");
        return false;
    }
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Admin Live Control", false, "Failed to get admin token");
        return false;
    }
    
    $result = makeRequest('GET', $BASE_URL . "/admin/matches/{$matchId}/live-control", null, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        $controlCapabilities = $result['data']['data']['control_capabilities'] ?? [];
        if (!empty($controlCapabilities)) {
            logTest("Admin Live Control", true, "Successfully retrieved admin live control dashboard");
            return true;
        } else {
            logTest("Admin Live Control", false, "Missing control capabilities in response");
        }
    } else {
        logTest("Admin Live Control", false, "Failed to retrieve admin control: " . ($result['data']['message'] ?? 'Unknown error'));
    }
    
    return false;
}

// Test 10: Round Transition System
function testRoundTransition($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Round Transition", false, "No match ID provided");
        return false;
    }
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Round Transition", false, "Failed to get admin token");
        return false;
    }
    
    $headers = ['Authorization: Bearer ' . $token];
    
    // Complete current round
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/round-transition", [
        "action" => "complete_round",
        "winner_team_id" => 87,  // Sentinels
        "round_scores" => [
            "team1" => 3,
            "team2" => 2
        ]
    ], $headers);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Complete Round", true, "Successfully completed round");
    } else {
        logTest("Complete Round", false, "Failed to complete round");
        return false;
    }
    
    // Start next round (for BO3/BO5)
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/round-transition", [
        "action" => "start_next_round"
    ], $headers);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Start Next Round", true, "Successfully started next round");
    } else {
        logTest("Start Next Round", false, "Failed to start next round (this is expected for BO1)");
    }
    
    // Complete match
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/round-transition", [
        "action" => "complete_match"
    ], $headers);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Complete Match", true, "Successfully completed match");
        return true;
    } else {
        logTest("Complete Match", false, "Failed to complete match");
        return false;
    }
}

// Test 11: Match Status Management
function testMatchStatusUpdate($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Match Status Update", false, "No match ID provided");
        return false;
    }
    
    $token = getAdminToken();
    if (!$token) {
        logTest("Match Status Update", false, "Failed to get admin token");
        return false;
    }
    
    $headers = ['Authorization: Bearer ' . $token];
    $statuses = ['live', 'paused', 'live', 'completed'];
    
    foreach ($statuses as $status) {
        $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/status", [
            "status" => $status,
            "reason" => "Testing {$status} status"
        ], $headers);
        
        if ($result['http_code'] === 200 && $result['data']['success']) {
            logTest("Match Status - {$status}", true, "Successfully updated status to {$status}");
        } else {
            logTest("Match Status - {$status}", false, "Failed to update status to {$status}");
            return false;
        }
    }
    
    return true;
}

// Test 12: Viewer Count Management
function testViewerCountUpdate($matchId) {
    global $BASE_URL;
    
    if (!$matchId) {
        logTest("Viewer Count Update", false, "No match ID provided");
        return false;
    }
    
    // Test increment
    $result = makeRequest('POST', $BASE_URL . "/matches/{$matchId}/viewers/update", [
        "action" => "increment"
    ]);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Viewer Count Increment", true, "Successfully incremented viewer count");
    } else {
        logTest("Viewer Count Increment", false, "Failed to increment viewer count");
        return false;
    }
    
    // Test set
    $result = makeRequest('POST', $BASE_URL . "/matches/{$matchId}/viewers/update", [
        "action" => "set",
        "count" => 1500
    ]);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Viewer Count Set", true, "Successfully set viewer count to 1500");
    } else {
        logTest("Viewer Count Set", false, "Failed to set viewer count");
        return false;
    }
    
    // Test decrement
    $result = makeRequest('POST', $BASE_URL . "/matches/{$matchId}/viewers/update", [
        "action" => "decrement"
    ]);
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Viewer Count Decrement", true, "Successfully decremented viewer count");
        return true;
    } else {
        logTest("Viewer Count Decrement", false, "Failed to decrement viewer count");
        return false;
    }
}

// Test 13: Match History Integration
function testMatchHistory() {
    global $BASE_URL;
    
    // Test team match history
    $result = makeRequest('GET', $BASE_URL . "/teams/87/match-history");
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Team Match History", true, "Successfully retrieved team match history");
    } else {
        logTest("Team Match History", false, "Failed to retrieve team match history");
    }
    
    // Test player match history
    $result = makeRequest('GET', $BASE_URL . "/players/183/match-history");
    
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Player Match History", true, "Successfully retrieved player match history");
        return true;
    } else {
        logTest("Player Match History", false, "Failed to retrieve player match history");
        return false;
    }
}

// Test 14: Game Data Endpoints
function testGameDataEndpoints() {
    global $BASE_URL;
    
    // Test heroes endpoint
    $result = makeRequest('GET', $BASE_URL . "/game-data/heroes");
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Game Data - Heroes", true, "Successfully retrieved heroes data");
    } else {
        logTest("Game Data - Heroes", false, "Failed to retrieve heroes data");
    }
    
    // Test maps endpoint
    $result = makeRequest('GET', $BASE_URL . "/game-data/maps");
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Game Data - Maps", true, "Successfully retrieved maps data");
    } else {
        logTest("Game Data - Maps", false, "Failed to retrieve maps data");
    }
    
    // Test modes endpoint
    $result = makeRequest('GET', $BASE_URL . "/game-data/modes");
    if ($result['http_code'] === 200 && $result['data']['success']) {
        logTest("Game Data - Modes", true, "Successfully retrieved game modes data");
    } else {
        logTest("Game Data - Modes", false, "Failed to retrieve game modes data");
    }
}

// Main execution function
function runAllTests() {
    global $test_results;
    
    echo "🚀 MARVEL RIVALS PROFESSIONAL LIVE SCORING SYSTEM - COMPLETE TEST SUITE\n";
    echo "=============================================================================\n\n";
    
    // Test game data endpoints first
    echo "🎮 Testing Game Data Endpoints...\n";
    testGameDataEndpoints();
    echo "\n";
    
    // Test competitive match creation
    echo "🏆 Testing Competitive Match Creation...\n";
    
    $bo1MatchId = testCreateBO1Match();
    $bo3MatchId = testCreateBO3Match();
    $bo5MatchId = testCreateBO5Match();
    echo "\n";
    
    // Use BO3 match for comprehensive testing
    if ($bo3MatchId) {
        echo "⚡ Testing Live Scoring System with BO3 Match (ID: {$bo3MatchId})...\n";
        
        testTimerManagement($bo3MatchId);
        testTeamComposition($bo3MatchId);
        testPlayerStatsUpdate($bo3MatchId);
        testBulkPlayerStatsUpdate($bo3MatchId);
        testLiveScoreboard($bo3MatchId);
        testAdminLiveControl($bo3MatchId);
        testMatchStatusUpdate($bo3MatchId);
        testViewerCountUpdate($bo3MatchId);
        testRoundTransition($bo3MatchId);
        echo "\n";
    }
    
    // Test match history
    echo "📚 Testing Match History Integration...\n";
    testMatchHistory();
    echo "\n";
    
    // Print summary
    echo "📊 TEST SUMMARY\n";
    echo "===============\n";
    echo "✅ Passed: " . count($test_results['success']) . "\n";
    echo "❌ Failed: " . count($test_results['failure']) . "\n\n";
    
    if (!empty($test_results['failure'])) {
        echo "❌ FAILED TESTS:\n";
        foreach ($test_results['failure'] as $failure) {
            echo "- {$failure['name']}: {$failure['message']}\n";
        }
        echo "\n";
    }
    
    if (empty($test_results['failure'])) {
        echo "🎉 ALL TESTS PASSED! Marvel Rivals Live Scoring System is working perfectly!\n";
        return true;
    } else {
        echo "⚠️  Some tests failed. Please check the errors above.\n";
        return false;
    }
}

// Check if running from command line or web browser
if (php_sapi_name() === 'cli') {
    // Command line execution
    $success = runAllTests();
    exit($success ? 0 : 1);
} else {
    // Web browser execution
    header('Content-Type: text/plain; charset=utf-8');
    echo "<pre>";
    runAllTests();
    echo "</pre>";
}
?>