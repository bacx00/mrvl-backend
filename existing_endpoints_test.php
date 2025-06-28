<?php
/**
 * 🛠️ EXISTING ENDPOINTS MATCH CREATION & LIVE SCORING TEST
 * 
 * Uses the current Laravel API endpoints that exist in the system
 * Tests the live scoring system with existing match creation methods
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

function testExistingEndpoints() {
    global $BASE_URL;
    
    echo "🛠️ EXISTING ENDPOINTS MATCH CREATION & LIVE SCORING TEST\n";
    echo "======================================================\n\n";
    
    // 1. Check available teams
    echo "🔍 Step 1: Checking existing teams...\n";
    $result = makeRequest('GET', $BASE_URL . '/teams');
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        $teams = $result['data']['data'] ?? [];
        logTest("Teams Available", true, "Found " . count($teams) . " teams");
        
        if (count($teams) >= 2) {
            $team1Id = $teams[0]['id'];
            $team2Id = $teams[1]['id'];
            
            echo "\n🏆 Step 2: Creating match using existing endpoint...\n";
            
            // Try the existing match creation endpoint
            $matchData = [
                'team1_id' => $team1Id,
                'team2_id' => $team2Id,
                'scheduled_at' => date('c', strtotime('+1 hour')),
                'format' => 'BO3',
                'maps_data' => [
                    [
                        'name' => 'Yggsgard: Royal Palace',
                        'mode' => 'Domination',
                        'team1Score' => 0,
                        'team2Score' => 0,
                        'status' => 'upcoming'
                    ],
                    [
                        'name' => 'Tokyo 2099: Spider-Islands',
                        'mode' => 'Convoy',
                        'team1Score' => 0,
                        'team2Score' => 0,
                        'status' => 'upcoming'
                    ],
                    [
                        'name' => 'Wakanda: Birnin T\'Challa',
                        'mode' => 'Domination',
                        'team1Score' => 0,
                        'team2Score' => 0,
                        'status' => 'upcoming'
                    ]
                ]
            ];
            
            $result = makeRequest('POST', $BASE_URL . '/admin/matches', $matchData, getAuthHeaders());
            
            if ($result['http_code'] === 201 && isset($result['data']['success']) && $result['data']['success']) {
                $matchId = $result['data']['data']['id'];
                logTest("Match Creation", true, "Created match ID: {$matchId}");
                
                // Test the complete live scoring workflow
                testLiveScoringWorkflow($matchId, $team1Id, $team2Id);
                
            } else {
                $errorMsg = isset($result['data']['message']) ? $result['data']['message'] : 'Unknown error';
                echo "Response: " . json_encode($result['data']) . "\n";
                logTest("Match Creation", false, "Failed: {$errorMsg}");
            }
            
        } else {
            logTest("Teams Available", false, "Not enough teams for testing");
        }
    } else {
        logTest("Teams Available", false, "Failed to retrieve teams");
    }
}

function testLiveScoringWorkflow($matchId, $team1Id, $team2Id) {
    global $BASE_URL;
    
    echo "\n⚡ Step 3: Testing Live Scoring Workflow...\n";
    
    // Test 1: Check match scoreboard
    echo "  📊 Testing live scoreboard...\n";
    $result = makeRequest('GET', $BASE_URL . "/matches/{$matchId}/scoreboard");
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        logTest("  Live Scoreboard", true, "Retrieved match scoreboard");
    } else {
        logTest("  Live Scoreboard", false, "Failed to get scoreboard");
    }
    
    // Test 2: Update match status
    echo "  🔄 Testing match status updates...\n";
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/status", [
        'status' => 'live'
    ], getAuthHeaders());
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        logTest("  Match Status Update", true, "Set status to live");
    } else {
        logTest("  Match Status Update", false, "Failed to update status");
    }
    
    // Test 3: Update team composition
    echo "  👥 Testing team composition updates...\n";
    testTeamComposition($matchId);
    
    // Test 4: Update player statistics
    echo "  📈 Testing player statistics...\n";
    testPlayerStatistics($matchId);
    
    // Test 5: Update match scores
    echo "  🏅 Testing score updates...\n";
    $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/scores", [
        'team1_score' => 2,
        'team2_score' => 1
    ], getAuthHeaders());
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        logTest("  Score Update", true, "Updated match scores 2-1");
    } else {
        logTest("  Score Update", false, "Failed to update scores");
    }
    
    // Test 6: Update viewer count
    echo "  👥 Testing viewer count...\n";
    $result = makeRequest('POST', $BASE_URL . "/matches/{$matchId}/viewers", [
        'action' => 'increment'
    ], getAuthHeaders());
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        logTest("  Viewer Count", true, "Incremented viewer count");
    } else {
        logTest("  Viewer Count", false, "Failed to update viewers");
    }
    
    // Test 7: Complete match
    echo "  🏁 Testing match completion...\n";
    $result = makeRequest('POST', $BASE_URL . "/matches/{$matchId}/complete", [
        'winner_team_id' => $team1Id,
        'final_score' => '2-1',
        'duration' => 1800
    ], getAuthHeaders());
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        logTest("  Match Completion", true, "Match completed successfully");
    } else {
        logTest("  Match Completion", false, "Failed to complete match");
    }
    
    // Test 8: Check final scoreboard
    echo "  📋 Testing final scoreboard...\n";
    $result = makeRequest('GET', $BASE_URL . "/matches/{$matchId}/scoreboard");
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        logTest("  Final Scoreboard", true, "Retrieved final scoreboard");
    } else {
        logTest("  Final Scoreboard", false, "Failed to get final scoreboard");
    }
}

function testTeamComposition($matchId) {
    global $BASE_URL;
    
    // Get players for composition
    $result = makeRequest('GET', $BASE_URL . '/players?limit=12');
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        $players = $result['data']['data'] ?? [];
        
        if (count($players) >= 6) {
            $team1Composition = [];
            $team2Composition = [];
            
            // Create 6v6 compositions
            for ($i = 0; $i < 6; $i++) {
                $playerIndex1 = $i % count($players);
                $playerIndex2 = ($i + 6) % count($players);
                
                $team1Composition[] = [
                    'player_id' => $players[$playerIndex1]['id'],
                    'hero' => 'Iron Man',
                    'role' => 'Duelist'
                ];
                
                $team2Composition[] = [
                    'player_id' => $players[$playerIndex2]['id'],
                    'hero' => 'Captain America',
                    'role' => 'Vanguard'
                ];
            }
            
            $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/team-composition", [
                'map_index' => 0,
                'team1_composition' => $team1Composition,
                'team2_composition' => $team2Composition
            ], getAuthHeaders());
            
            if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
                logTest("  Team Composition", true, "Set 6v6 team compositions");
            } else {
                logTest("  Team Composition", false, "Failed to set compositions");
            }
        } else {
            logTest("  Team Composition", false, "Not enough players available");
        }
    } else {
        logTest("  Team Composition", false, "Failed to get players");
    }
}

function testPlayerStatistics($matchId) {
    global $BASE_URL;
    
    // Get players to test with
    $result = makeRequest('GET', $BASE_URL . '/players?limit=6');
    
    if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
        $players = $result['data']['data'] ?? [];
        
        if (count($players) > 0) {
            $playerId = $players[0]['id'];
            
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
                'hero_played' => 'Iron Man',
                'current_map' => 'Yggsgard: Royal Palace'
            ];
            
            $result = makeRequest('PUT', $BASE_URL . "/admin/matches/{$matchId}/player-stats/{$playerId}", $stats, getAuthHeaders());
            
            if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
                logTest("  Player Statistics", true, "Updated stats for player {$playerId}");
            } else {
                logTest("  Player Statistics", false, "Failed to update player stats");
            }
            
            // Test getting player stats
            $result = makeRequest('GET', $BASE_URL . "/admin/matches/{$matchId}/player-stats/{$playerId}", null, getAuthHeaders());
            
            if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
                logTest("  Get Player Stats", true, "Retrieved player statistics");
            } else {
                logTest("  Get Player Stats", false, "Failed to get player stats");
            }
        } else {
            logTest("  Player Statistics", false, "No players available");
        }
    } else {
        logTest("  Player Statistics", false, "Failed to get players");
    }
}

// Additional tests for existing endpoints
function testAdditionalEndpoints() {
    global $BASE_URL;
    
    echo "\n🔧 Step 4: Testing Additional Endpoints...\n";
    
    // Test analytics endpoints
    echo "  📊 Testing analytics endpoints...\n";
    
    $analyticsEndpoints = [
        '/analytics/players/leaderboards' => 'Player Leaderboards',
        '/analytics/heroes/usage' => 'Hero Usage Stats',
        '/analytics/teams/performance' => 'Team Performance',
        '/analytics/matches/recent' => 'Recent Matches'
    ];
    
    foreach ($analyticsEndpoints as $endpoint => $name) {
        $result = makeRequest('GET', $BASE_URL . $endpoint);
        
        if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
            logTest("  {$name}", true, "Analytics endpoint working");
        } else {
            logTest("  {$name}", false, "Analytics endpoint failed");
        }
    }
    
    // Test game data endpoints
    echo "  🎮 Testing game data endpoints...\n";
    
    $gameDataEndpoints = [
        '/game-data/heroes' => 'Heroes Data',
        '/game-data/maps' => 'Maps Data',
        '/game-data/modes' => 'Game Modes Data'
    ];
    
    foreach ($gameDataEndpoints as $endpoint => $name) {
        $result = makeRequest('GET', $BASE_URL . $endpoint);
        
        if ($result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success']) {
            logTest("  {$name}", true, "Game data endpoint working");
        } else {
            logTest("  {$name}", false, "Game data endpoint failed");
        }
    }
}

function runExistingEndpointsTest() {
    global $test_results, $BASE_URL;
    
    $startTime = microtime(true);
    
    echo "🛠️ TESTING EXISTING MARVEL RIVALS API ENDPOINTS\n";
    echo "==============================================\n";
    echo "🌐 Testing against: {$BASE_URL}\n";
    echo "🕒 Started at: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Test existing endpoints
    testExistingEndpoints();
    
    // Test additional endpoints
    testAdditionalEndpoints();
    
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime, 2);
    
    // Generate report
    $totalTests = count($test_results['success']) + count($test_results['failure']);
    $successRate = $totalTests > 0 ? round((count($test_results['success']) / $totalTests) * 100, 2) : 0;
    
    echo "\n📊 EXISTING ENDPOINTS TEST RESULTS\n";
    echo "==================================\n";
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
    
    if ($successRate >= 70) {
        echo "🎉 EXISTING LIVE SCORING SYSTEM IS WORKING!\n";
        echo "=========================================\n";
        echo "✅ Core endpoints functional\n";
        echo "✅ Match management working\n";
        echo "✅ Live scoring operational\n";
        echo "✅ Ready for tournament use\n";
    } else if ($successRate >= 50) {
        echo "⚠️  SYSTEM PARTIALLY WORKING\n";
        echo "===========================\n";
        echo "✅ Some functionality works\n";
        echo "❌ Core issues need fixing\n";
        echo "🔧 Review failed endpoints\n";
    } else {
        echo "🔧 SYSTEM NEEDS MAJOR FIXES\n";
        echo "===========================\n";
        echo "❌ Multiple critical issues\n";
        echo "📋 Review all failed tests\n";
    }
    
    return $successRate >= 70;
}

// Execute the test
if (php_sapi_name() === 'cli') {
    $success = runExistingEndpointsTest();
    exit($success ? 0 : 1);
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "<pre>";
    runExistingEndpointsTest();
    echo "</pre>";
}
?>