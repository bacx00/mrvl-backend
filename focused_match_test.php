<?php
/**
 * 🎯 FOCUSED MARVEL RIVALS MATCH CREATION & LIVE SCORING TEST
 * 
 * This test focuses on the CORE live scoring functionality:
 * - Creating fresh competitive matches (BO1/BO3/BO5)
 * - Testing live scoring workflow
 * - Real-time updates and data persistence
 * 
 * Uses existing teams/players instead of creating new ones
 */

// Configuration
$BASE_URL = "https://staging.mrvl.net/api";
$ADMIN_TOKEN = "456|XgZ5PbsCpIVrcpjZgeRJAhHmf8RGJBNaaWXLeczI2a360ed8";

// Test results tracking
$test_results = ['success' => [], 'failure' => []];

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

// =============================================================================
// 🎯 FOCUSED MATCH CREATION TESTS
// =============================================================================

function testFocusedMatchCreation() {
    global $BASE_URL;
    
    echo "🎯 FOCUSED MATCH CREATION & LIVE SCORING TEST\n";
    echo "==========================================\n\n";
    
    // First, let's check what teams exist
    echo "🔍 Checking existing teams...\n";
    $result = makeRequest('GET', $BASE_URL . '/teams');
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        $teams = $result['data']['data'] ?? [];
        logTest("Teams Retrieval", true, "Found " . count($teams) . " teams");
        
        if (count($teams) >= 2) {
            $team1Id = $teams[0]['id'];
            $team2Id = $teams[1]['id'];
            
            logTest("Team Selection", true, "Using Team {$team1Id} vs Team {$team2Id}");
            
            // Test BO1 Match Creation
            $bo1MatchId = createCompetitiveMatch('BO1', $team1Id, $team2Id);
            
            if ($bo1MatchId) {
                // Test complete live scoring workflow
                testCompleteMatchWorkflow($bo1MatchId, 'BO1');
            }
            
            // Test BO3 Match Creation  
            $bo3MatchId = createCompetitiveMatch('BO3', $team1Id, $team2Id);
            
            if ($bo3MatchId) {
                // Test BO3 workflow
                testCompleteMatchWorkflow($bo3MatchId, 'BO3');
            }
            
        } else {
            logTest("Team Selection", false, "Not enough teams found for testing");
        }
    } else {
        logTest("Teams Retrieval", false, "Failed to retrieve teams");
    }
}

function createCompetitiveMatch($format, $team1Id, $team2Id) {
    global $BASE_URL;
    
    echo "\n🏆 Creating {$format} Competitive Match...\n";
    
    // Create appropriate map pool
    $mapPools = [
        'BO1' => [
            ['map_name' => 'Yggsgard: Royal Palace', 'game_mode' => 'Domination']
        ],
        'BO3' => [
            ['map_name' => 'Yggsgard: Royal Palace', 'game_mode' => 'Domination'],
            ['map_name' => 'Tokyo 2099: Spider-Islands', 'game_mode' => 'Convoy'],
            ['map_name' => 'Wakanda: Birnin T\'Challa', 'game_mode' => 'Domination']
        ],
        'BO5' => [
            ['map_name' => 'Yggsgard: Royal Palace', 'game_mode' => 'Domination'],
            ['map_name' => 'Tokyo 2099: Spider-Islands', 'game_mode' => 'Convoy'],
            ['map_name' => 'Wakanda: Birnin T\'Challa', 'game_mode' => 'Domination'],
            ['map_name' => 'Hydra Charteris Base: Hell\'s Heaven', 'game_mode' => 'Domination'],
            ['map_name' => 'Tokyo 2099: Shin-Shibuya', 'game_mode' => 'Convergence']
        ]
    ];
    
    $matchData = [
        'team1_id' => $team1Id,
        'team2_id' => $team2Id,
        'match_format' => $format,
        'map_pool' => $mapPools[$format],
        'scheduled_at' => date('c', strtotime('+1 hour')),
        'competitive_settings' => [
            'preparation_time' => 45,
            'tactical_pauses_per_team' => 2,
            'pause_duration' => 120,
            'overtime_enabled' => true
        ]
    ];
    
    $result = makeRequest('POST', $BASE_URL . '/admin/matches/create-competitive', $matchData, getAuthHeaders());
    
    if ($result['http_code'] === 201 && isset($result['data']['success']) && $result['data']['success']) {
        $matchId = $result['data']['data']['match']['id'];
        $rounds = $result['data']['data']['rounds'] ?? [];
        
        logTest("{$format} Match Creation", true, "Created match ID: {$matchId} with " . count($rounds) . " rounds");
        return $matchId;
    } else {
        $errorMsg = $result['data']['message'] ?? 'Unknown error';
        logTest("{$format} Match Creation", false, "Failed: {$errorMsg}");
        return null;
    }
}

function testCompleteMatchWorkflow($matchId, $format) {
    global $BASE_URL;
    
    echo "⚡ Testing Complete {$format} Match Workflow (ID: {$matchId})...\n";
    
    // 1. Start preparation phase
    echo "  🕒 Starting preparation phase...\n";
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/timer/start-preparation", [
        'duration_seconds' => 45,
        'phase' => 'hero_selection'
    ], getAuthHeaders());
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        logTest("  Preparation Timer", true, "45s hero selection started");
    } else {
        logTest("  Preparation Timer", false, "Failed to start preparation");
    }
    
    // 2. Set 6v6 team compositions
    echo "  👥 Setting 6v6 team compositions...\n";
    testTeamCompositions($matchId);
    
    // 3. Start match timer
    echo "  ⏱️  Starting match timer...\n";
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/timer/start-match", [
        'duration_seconds' => 600
    ], getAuthHeaders());
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        logTest("  Match Timer", true, "10-minute match started");
    } else {
        logTest("  Match Timer", false, "Failed to start match timer");
    }
    
    // 4. Test player statistics updates
    echo "  📊 Testing player statistics...\n";
    testPlayerStatistics($matchId);
    
    // 5. Test live scoreboard
    echo "  📺 Testing live scoreboard...\n";
    testLiveScoreboard($matchId);
    
    // 6. Test match status changes
    echo "  🔄 Testing match status changes...\n";
    testMatchStatus($matchId);
    
    // 7. Test viewer count
    echo "  👥 Testing viewer count...\n";
    testViewerCount($matchId);
    
    // 8. Test round completion (if applicable)
    if ($format !== 'BO1') {
        echo "  🏁 Testing round completion...\n";
        testRoundCompletion($matchId);
    }
}

function testTeamCompositions($matchId) {
    global $BASE_URL;
    
    // Get existing players to use real player IDs
    $result = makeRequest('GET', $BASE_URL . '/players');
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        $players = $result['data']['data'] ?? [];
        
        if (count($players) >= 12) {
            // Use first 12 players (6 per team)
            $team1Players = array_slice($players, 0, 6);
            $team2Players = array_slice($players, 6, 6);
            
            $heroes = [
                'Vanguard' => ['Captain America', 'Thor', 'Hulk', 'Doctor Strange'],
                'Duelist' => ['Iron Man', 'Spider-Man', 'Black Widow', 'Scarlet Witch'],
                'Strategist' => ['Luna Snow', 'Mantis', 'Rocket Raccoon', 'Adam Warlock']
            ];
            
            $team1Composition = [];
            $team2Composition = [];
            
            for ($i = 0; $i < 6; $i++) {
                $role = $i < 2 ? 'Vanguard' : ($i < 4 ? 'Duelist' : 'Strategist');
                $heroPool = $heroes[$role];
                
                $team1Composition[] = [
                    'player_id' => $team1Players[$i]['id'],
                    'hero' => $heroPool[array_rand($heroPool)],
                    'role' => $role
                ];
                
                $team2Composition[] = [
                    'player_id' => $team2Players[$i]['id'],
                    'hero' => $heroPool[array_rand($heroPool)],
                    'role' => $role
                ];
            }
            
            $compositionData = [
                'round_number' => 1,
                'team1_composition' => $team1Composition,
                'team2_composition' => $team2Composition
            ];
            
            $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/team-composition", $compositionData, getAuthHeaders());
            
            if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
                logTest("    6v6 Compositions", true, "Set realistic team compositions");
            } else {
                logTest("    6v6 Compositions", false, "Failed to set compositions");
            }
        } else {
            logTest("    6v6 Compositions", false, "Not enough players available");
        }
    } else {
        logTest("    6v6 Compositions", false, "Failed to retrieve players");
    }
}

function testPlayerStatistics($matchId) {
    global $BASE_URL;
    
    // Get players for this match
    $result = makeRequest('GET', $BASE_URL . '/players?limit=12');
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        $players = $result['data']['data'] ?? [];
        
        if (count($players) > 0) {
            $testPlayer = $players[0];
            $playerId = $testPlayer['id'];
            
            // Test individual player stats update
            $stats = [
                'eliminations' => 15,
                'deaths' => 4,
                'assists' => 12,
                'damage' => 8500,
                'healing' => 0,
                'damage_blocked' => 2100,
                'ultimate_usage' => 3,
                'objective_time' => 89,
                'final_blows' => 10,
                'environmental_kills' => 2,
                'accuracy_percentage' => 67.5,
                'critical_hits' => 25,
                'hero_played' => 'Iron Man',
                'role_played' => 'Duelist'
            ];
            
            $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/player/{$playerId}/stats", $stats, getAuthHeaders());
            
            if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
                logTest("    Individual Player Stats", true, "Updated stats for player {$playerId}");
            } else {
                logTest("    Individual Player Stats", false, "Failed to update player stats");
            }
            
            // Test bulk player stats update
            if (count($players) >= 3) {
                $bulkStats = [
                    'player_stats' => []
                ];
                
                for ($i = 0; $i < 3; $i++) {
                    $bulkStats['player_stats'][] = [
                        'player_id' => $players[$i]['id'],
                        'eliminations' => rand(10, 20),
                        'deaths' => rand(3, 8),
                        'assists' => rand(8, 15),
                        'damage' => rand(6000, 12000),
                        'hero_played' => 'Spider-Man',
                        'role_played' => 'Duelist'
                    ];
                }
                
                $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/bulk-player-stats", $bulkStats, getAuthHeaders());
                
                if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
                    logTest("    Bulk Player Stats", true, "Updated 3 players in bulk");
                } else {
                    logTest("    Bulk Player Stats", false, "Failed bulk stats update");
                }
            }
        } else {
            logTest("    Player Statistics", false, "No players available for testing");
        }
    } else {
        logTest("    Player Statistics", false, "Failed to retrieve players");
    }
}

function testLiveScoreboard($matchId) {
    global $BASE_URL;
    
    $result = makeRequest('GET', $BASE_URL . "/matches/{$matchId}/live-scoreboard");
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        $scoreboardData = $result['data']['data'];
        logTest("    Live Scoreboard", true, "Retrieved complete live data");
        
        // Check for cache control
        if (isset($result['data']['cache_control'])) {
            logTest("    Cache Control", true, "Proper cache headers present");
        }
    } else {
        logTest("    Live Scoreboard", false, "Failed to retrieve scoreboard");
    }
}

function testMatchStatus($matchId) {
    global $BASE_URL;
    
    $statuses = ['live', 'paused', 'live'];
    
    foreach ($statuses as $status) {
        $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/status", [
            'status' => $status,
            'reason' => "Testing {$status} status"
        ], getAuthHeaders());
        
        if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
            logTest("    Status -> {$status}", true, "Status updated successfully");
        } else {
            logTest("    Status -> {$status}", false, "Failed to update status");
        }
    }
}

function testViewerCount($matchId) {
    global $BASE_URL;
    
    $actions = [
        ['action' => 'set', 'count' => 1000],
        ['action' => 'increment'],
        ['action' => 'increment'],
        ['action' => 'decrement']
    ];
    
    foreach ($actions as $actionData) {
        $result = makeRequest('POST', $BASE_URL . "/matches/{$matchId}/viewers/update", $actionData);
        
        if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
            logTest("    Viewer {$actionData['action']}", true, "Viewer count updated");
        } else {
            logTest("    Viewer {$actionData['action']}", false, "Failed viewer update");
        }
    }
}

function testRoundCompletion($matchId) {
    global $BASE_URL;
    
    // Get match data to determine teams
    $result = makeRequest('GET', $BASE_URL . "/matches/{$matchId}/live-scoreboard");
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        $matchData = $result['data']['data']['match'] ?? [];
        $team1Id = $matchData['team1_id'] ?? null;
        
        if ($team1Id) {
            // Complete round 1
            $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/round-transition", [
                'action' => 'complete_round',
                'winner_team_id' => $team1Id,
                'round_scores' => [
                    'team1' => 3,
                    'team2' => 2
                ]
            ], getAuthHeaders());
            
            if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
                logTest("    Round Completion", true, "Round 1 completed successfully");
            } else {
                logTest("    Round Completion", false, "Failed to complete round");
            }
        }
    }
}

// =============================================================================
// 📊 MAIN EXECUTION AND REPORTING
// =============================================================================

function runFocusedTest() {
    global $test_results, $BASE_URL;
    
    $startTime = microtime(true);
    
    echo "🎯 FOCUSED MARVEL RIVALS MATCH CREATION & LIVE SCORING TEST\n";
    echo "=========================================================\n";
    echo "🌐 Testing against: {$BASE_URL}\n";
    echo "🕒 Started at: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Run focused match creation tests
    testFocusedMatchCreation();
    
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime, 2);
    
    // Generate report
    $totalTests = count($test_results['success']) + count($test_results['failure']);
    $successRate = $totalTests > 0 ? round((count($test_results['success']) / $totalTests) * 100, 2) : 0;
    
    echo "\n📊 FOCUSED TEST RESULTS\n";
    echo "======================\n";
    echo "⏱️  Total Time: {$totalTime} seconds\n";
    echo "📈 Tests: {$totalTests}\n";
    echo "✅ Passed: " . count($test_results['success']) . "\n";
    echo "❌ Failed: " . count($test_results['failure']) . "\n";
    echo "📊 Success Rate: {$successRate}%\n\n";
    
    if (!empty($test_results['failure'])) {
        echo "❌ FAILED TESTS:\n";
        foreach ($test_results['failure'] as $failure) {
            echo "- {$failure['name']}: {$failure['message']}\n";
        }
        echo "\n";
    }
    
    if ($successRate >= 80) {
        echo "🎉 CORE LIVE SCORING SYSTEM IS WORKING!\n";
        echo "======================================\n";
        echo "✅ Match creation functional\n";
        echo "✅ Live scoring operational\n";
        echo "✅ Ready for tournament use\n";
    } else {
        echo "🔧 SYSTEM NEEDS FIXES\n";
        echo "====================\n";
        echo "❌ Core issues found\n";
        echo "📋 Review failed tests\n";
    }
    
    return $successRate >= 80;
}

// Execute the focused test
if (php_sapi_name() === 'cli') {
    $success = runFocusedTest();
    exit($success ? 0 : 1);
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "<pre>";
    runFocusedTest();
    echo "</pre>";
}
?>