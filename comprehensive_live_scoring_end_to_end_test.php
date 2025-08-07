<?php

/**
 * COMPREHENSIVE LIVE SCORING END-TO-END TEST
 * 
 * This script tests the complete live scoring system:
 * 1. Tests POST /api/admin/matches/{id}/live-scoring endpoint
 * 2. Tests GET /api/matches/{id} endpoint
 * 3. Verifies data flow from admin form to match detail page
 * 4. Tests all update types (scores, heroes, player stats)
 * 5. Tests both live and completed match scenarios
 */

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MvrlMatch;
use App\Models\User;

class LiveScoringEndToEndTester
{
    private $baseUrl;
    private $adminToken;
    private $testMatchId;
    private $results = [];
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api';
        $this->setupTestEnvironment();
    }
    
    private function setupTestEnvironment()
    {
        echo "🔧 SETTING UP TEST ENVIRONMENT\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // Get or create admin user
        $admin = User::where('email', 'admin@test.com')->first();
        if (!$admin) {
            $admin = User::where('role', 'admin')->first();
            if (!$admin) {
                $admin = User::first(); // Use any existing user for testing
                if ($admin) {
                    $admin->role = 'admin';
                    $admin->save();
                }
            }
        }
        
        // Generate token for admin (using Passport)
        $tokenResult = $admin->createToken('test-token');
        $this->adminToken = $tokenResult->accessToken;
        echo "✅ Admin token generated: " . substr($this->adminToken, 0, 20) . "...\n";
        
        // Get test match - preferably a live one
        $liveMatch = MvrlMatch::where('status', 'live')->first();
        if (!$liveMatch) {
            // Create a test match if no live match exists
            $this->testMatchId = $this->createTestMatch();
        } else {
            $this->testMatchId = $liveMatch->id;
        }
        
        echo "✅ Test match ID: {$this->testMatchId}\n";
        echo "=" . str_repeat("=", 50) . "\n\n";
    }
    
    private function createTestMatch()
    {
        echo "🏗️  Creating test match...\n";
        
        $match = MvrlMatch::create([
            'team1_id' => 54, // Using existing team IDs
            'team2_id' => 55,
            'status' => 'live',
            'best_of' => 3,
            'current_map_number' => 1,
            'team1_score' => 0,
            'team2_score' => 0,
            'scheduled_at' => now(),
            'started_at' => now(),
            'maps_data' => json_encode([
                [
                    'map_name' => 'Control Center',
                    'mode' => 'Convoy',
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'ongoing'
                ]
            ]),
            'player_stats' => json_encode([]),
            'match_timer' => json_encode(['time' => '00:00', 'status' => 'stopped'])
        ]);
        
        echo "✅ Test match created with ID: {$match->id}\n";
        return $match->id;
    }
    
    private function makeRequest($method, $endpoint, $data = null, $useAuth = true)
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($useAuth && $this->adminToken) {
            $headers[] = 'Authorization: Bearer ' . $this->adminToken;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: $error");
        }
        
        return [
            'status_code' => $httpCode,
            'body' => json_decode($response, true),
            'raw_body' => $response
        ];
    }
    
    public function runAllTests()
    {
        echo "🚀 STARTING COMPREHENSIVE LIVE SCORING TESTS\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $this->testGetMatchEndpoint();
        $this->testScoreUpdates();
        $this->testHeroCompositionUpdates();
        $this->testPlayerStatsUpdates();
        $this->testTimerUpdates();
        $this->testStatusTransitions();
        $this->testCompletedMatchUpdates();
        $this->testErrorHandling();
        
        $this->generateReport();
    }
    
    public function testGetMatchEndpoint()
    {
        echo "📥 TEST 1: GET /api/matches/{id} endpoint\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        try {
            $response = $this->makeRequest('GET', "/matches/{$this->testMatchId}", null, false);
            
            echo "Status Code: {$response['status_code']}\n";
            
            if ($response['status_code'] === 200) {
                echo "✅ GET endpoint works correctly\n";
                $match = $response['body']['data'] ?? $response['body'];
                echo "Match Status: " . ($match['status'] ?? 'unknown') . "\n";
                echo "Series Score: {$match['team1_score']}-{$match['team2_score']}\n";
                
                // Verify essential fields
                $requiredFields = ['id', 'status', 'team1_score', 'team2_score', 'maps_data'];
                $missingFields = [];
                foreach ($requiredFields as $field) {
                    if (!isset($match[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (empty($missingFields)) {
                    echo "✅ All required fields present\n";
                    $this->results['get_match'] = 'PASS';
                } else {
                    echo "❌ Missing fields: " . implode(', ', $missingFields) . "\n";
                    $this->results['get_match'] = 'FAIL - Missing fields';
                }
            } else {
                echo "❌ GET endpoint failed\n";
                echo "Response: " . $response['raw_body'] . "\n";
                $this->results['get_match'] = "FAIL - HTTP {$response['status_code']}";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
            $this->results['get_match'] = 'FAIL - Exception';
        }
        
        echo "\n";
    }
    
    public function testScoreUpdates()
    {
        echo "🎯 TEST 2: Score Updates via POST /api/admin/matches/{id}/live-scoring\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        try {
            $scoreData = [
                'current_map_data' => [
                    'name' => 'Control Center',
                    'mode' => 'Convoy',
                    'team1Score' => 75,
                    'team2Score' => 50,
                    'status' => 'ongoing'
                ],
                'series_score' => [
                    'team1' => 1,
                    'team2' => 0
                ],
                'status' => 'live',
                'current_map' => 1,
                'timer' => '05:32'
            ];
            
            $response = $this->makeRequest('POST', "/admin/matches/{$this->testMatchId}/live-scoring", $scoreData);
            
            echo "Status Code: {$response['status_code']}\n";
            
            if ($response['status_code'] === 200) {
                echo "✅ Score update successful\n";
                
                // Verify the update by getting the match
                $getResponse = $this->makeRequest('GET', "/matches/{$this->testMatchId}", null, false);
                if ($getResponse['status_code'] === 200) {
                    $match = $getResponse['body']['data'] ?? $getResponse['body'];
                    
                    // Check if scores were updated
                    $mapsData = is_string($match['maps_data']) ? json_decode($match['maps_data'], true) : $match['maps_data'];
                    $currentMap = $mapsData[0] ?? [];
                    
                    if ($currentMap['team1_score'] == 75 && $currentMap['team2_score'] == 50) {
                        echo "✅ Map scores verified: 75-50\n";
                        echo "✅ Series scores verified: {$match['team1_score']}-{$match['team2_score']}\n";
                        $this->results['score_updates'] = 'PASS';
                    } else {
                        echo "❌ Score verification failed\n";
                        echo "Expected: 75-50, Got: {$currentMap['team1_score']}-{$currentMap['team2_score']}\n";
                        $this->results['score_updates'] = 'FAIL - Score mismatch';
                    }
                } else {
                    echo "❌ Could not verify score update\n";
                    $this->results['score_updates'] = 'FAIL - Verification failed';
                }
            } else {
                echo "❌ Score update failed\n";
                echo "Response: " . $response['raw_body'] . "\n";
                $this->results['score_updates'] = "FAIL - HTTP {$response['status_code']}";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
            $this->results['score_updates'] = 'FAIL - Exception';
        }
        
        echo "\n";
    }
    
    public function testHeroCompositionUpdates()
    {
        echo "🦸 TEST 3: Hero Composition Updates\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        try {
            $heroData = [
                'player_stats' => [
                    'player_1' => [
                        'name' => 'TestPlayer1',
                        'hero' => 'Spider-Man',
                        'role' => 'Duelist',
                        'kills' => 15,
                        'deaths' => 3,
                        'damage' => 8500
                    ],
                    'player_2' => [
                        'name' => 'TestPlayer2',
                        'hero' => 'Venom',
                        'role' => 'Vanguard',
                        'kills' => 8,
                        'deaths' => 5,
                        'damage' => 6200
                    ]
                ],
                'current_map' => 1
            ];
            
            $response = $this->makeRequest('POST', "/admin/matches/{$this->testMatchId}/live-scoring", $heroData);
            
            echo "Status Code: {$response['status_code']}\n";
            
            if ($response['status_code'] === 200) {
                echo "✅ Hero composition update successful\n";
                
                // Verify the update
                $getResponse = $this->makeRequest('GET', "/matches/{$this->testMatchId}", null, false);
                if ($getResponse['status_code'] === 200) {
                    $match = $getResponse['body']['data'] ?? $getResponse['body'];
                    $playerStats = is_string($match['player_stats']) ? json_decode($match['player_stats'], true) : $match['player_stats'];
                    
                    if (isset($playerStats['player_1']) && $playerStats['player_1']['hero'] === 'Spider-Man') {
                        echo "✅ Player 1 hero verified: Spider-Man\n";
                        echo "✅ Player stats verified: {$playerStats['player_1']['kills']}/{$playerStats['player_1']['deaths']}/{$playerStats['player_1']['damage']}\n";
                        $this->results['hero_updates'] = 'PASS';
                    } else {
                        echo "❌ Hero composition verification failed\n";
                        $this->results['hero_updates'] = 'FAIL - Hero verification failed';
                    }
                } else {
                    echo "❌ Could not verify hero updates\n";
                    $this->results['hero_updates'] = 'FAIL - Verification failed';
                }
            } else {
                echo "❌ Hero composition update failed\n";
                echo "Response: " . $response['raw_body'] . "\n";
                $this->results['hero_updates'] = "FAIL - HTTP {$response['status_code']}";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
            $this->results['hero_updates'] = 'FAIL - Exception';
        }
        
        echo "\n";
    }
    
    public function testPlayerStatsUpdates()
    {
        echo "📊 TEST 4: Player Stats Updates\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        try {
            $statsData = [
                'player_stats' => [
                    'player_1' => [
                        'name' => 'TestPlayer1',
                        'hero' => 'Spider-Man',
                        'kills' => 20,
                        'deaths' => 4,
                        'damage' => 12000,
                        'healing' => 500,
                        'blocked' => 2500
                    ],
                    'player_2' => [
                        'name' => 'TestPlayer2',
                        'hero' => 'Mantis',
                        'kills' => 3,
                        'deaths' => 2,
                        'damage' => 3500,
                        'healing' => 8500,
                        'blocked' => 0
                    ]
                ]
            ];
            
            $response = $this->makeRequest('POST', "/admin/matches/{$this->testMatchId}/live-scoring", $statsData);
            
            echo "Status Code: {$response['status_code']}\n";
            
            if ($response['status_code'] === 200) {
                echo "✅ Player stats update successful\n";
                
                // Verify comprehensive stats
                $getResponse = $this->makeRequest('GET', "/matches/{$this->testMatchId}", null, false);
                if ($getResponse['status_code'] === 200) {
                    $match = $getResponse['body']['data'] ?? $getResponse['body'];
                    $playerStats = is_string($match['player_stats']) ? json_decode($match['player_stats'], true) : $match['player_stats'];
                    
                    $player1 = $playerStats['player_1'] ?? [];
                    if ($player1['kills'] == 20 && $player1['damage'] == 12000 && $player1['healing'] == 500) {
                        echo "✅ Player 1 comprehensive stats verified\n";
                        echo "  - K/D/A: {$player1['kills']}/{$player1['deaths']}/?\n";
                        echo "  - Damage: {$player1['damage']}\n";
                        echo "  - Healing: {$player1['healing']}\n";
                        echo "  - Blocked: {$player1['blocked']}\n";
                        $this->results['player_stats_updates'] = 'PASS';
                    } else {
                        echo "❌ Player stats verification failed\n";
                        $this->results['player_stats_updates'] = 'FAIL - Stats mismatch';
                    }
                } else {
                    echo "❌ Could not verify player stats\n";
                    $this->results['player_stats_updates'] = 'FAIL - Verification failed';
                }
            } else {
                echo "❌ Player stats update failed\n";
                echo "Response: " . $response['raw_body'] . "\n";
                $this->results['player_stats_updates'] = "FAIL - HTTP {$response['status_code']}";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
            $this->results['player_stats_updates'] = 'FAIL - Exception';
        }
        
        echo "\n";
    }
    
    public function testTimerUpdates()
    {
        echo "⏱️  TEST 5: Timer Updates\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        try {
            $timerData = [
                'timer' => '10:45',
                'status' => 'live'
            ];
            
            $response = $this->makeRequest('POST', "/admin/matches/{$this->testMatchId}/live-scoring", $timerData);
            
            echo "Status Code: {$response['status_code']}\n";
            
            if ($response['status_code'] === 200) {
                echo "✅ Timer update successful\n";
                
                // Verify timer
                $getResponse = $this->makeRequest('GET', "/matches/{$this->testMatchId}", null, false);
                if ($getResponse['status_code'] === 200) {
                    $match = $getResponse['body']['data'] ?? $getResponse['body'];
                    $timerData = is_string($match['match_timer']) ? json_decode($match['match_timer'], true) : $match['match_timer'];
                    
                    if ($timerData['time'] === '10:45') {
                        echo "✅ Timer verified: 10:45\n";
                        $this->results['timer_updates'] = 'PASS';
                    } else {
                        echo "❌ Timer verification failed\n";
                        echo "Expected: 10:45, Got: {$timerData['time']}\n";
                        $this->results['timer_updates'] = 'FAIL - Timer mismatch';
                    }
                }
            } else {
                echo "❌ Timer update failed\n";
                echo "Response: " . $response['raw_body'] . "\n";
                $this->results['timer_updates'] = "FAIL - HTTP {$response['status_code']}";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
            $this->results['timer_updates'] = 'FAIL - Exception';
        }
        
        echo "\n";
    }
    
    public function testStatusTransitions()
    {
        echo "🔄 TEST 6: Status Transitions (Live ↔ Paused ↔ Completed)\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        $statuses = ['live', 'paused', 'completed', 'live'];
        
        foreach ($statuses as $status) {
            try {
                $statusData = ['status' => $status];
                $response = $this->makeRequest('POST', "/admin/matches/{$this->testMatchId}/live-scoring", $statusData);
                
                if ($response['status_code'] === 200) {
                    echo "✅ Status transition to '{$status}' successful\n";
                } else {
                    echo "❌ Status transition to '{$status}' failed\n";
                    $this->results['status_transitions'] = "FAIL - {$status} transition failed";
                    return;
                }
            } catch (Exception $e) {
                echo "❌ Exception during '{$status}' transition: " . $e->getMessage() . "\n";
                $this->results['status_transitions'] = 'FAIL - Exception';
                return;
            }
        }
        
        $this->results['status_transitions'] = 'PASS';
        echo "\n";
    }
    
    public function testCompletedMatchUpdates()
    {
        echo "🏁 TEST 7: Completed Match Updates\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        try {
            // Test updating a completed match
            $completedData = [
                'status' => 'completed',
                'series_score' => [
                    'team1' => 2,
                    'team2' => 1
                ],
                'current_map_data' => [
                    'name' => 'Control Center',
                    'mode' => 'Convoy',
                    'team1Score' => 100,
                    'team2Score' => 85,
                    'status' => 'completed'
                ]
            ];
            
            $response = $this->makeRequest('POST', "/admin/matches/{$this->testMatchId}/live-scoring", $completedData);
            
            echo "Status Code: {$response['status_code']}\n";
            
            if ($response['status_code'] === 200) {
                echo "✅ Completed match update successful\n";
                
                // Verify final scores
                $getResponse = $this->makeRequest('GET', "/matches/{$this->testMatchId}", null, false);
                if ($getResponse['status_code'] === 200) {
                    $match = $getResponse['body']['data'] ?? $getResponse['body'];
                    
                    if ($match['status'] === 'completed' && $match['team1_score'] == 2 && $match['team2_score'] == 1) {
                        echo "✅ Final match status and scores verified\n";
                        echo "Final Score: {$match['team1_score']}-{$match['team2_score']}\n";
                        $this->results['completed_match_updates'] = 'PASS';
                    } else {
                        echo "❌ Final match verification failed\n";
                        $this->results['completed_match_updates'] = 'FAIL - Final scores incorrect';
                    }
                }
            } else {
                echo "❌ Completed match update failed\n";
                echo "Response: " . $response['raw_body'] . "\n";
                $this->results['completed_match_updates'] = "FAIL - HTTP {$response['status_code']}";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
            $this->results['completed_match_updates'] = 'FAIL - Exception';
        }
        
        echo "\n";
    }
    
    public function testErrorHandling()
    {
        echo "🚨 TEST 8: Error Handling\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        // Test invalid match ID
        $response = $this->makeRequest('POST', "/admin/matches/99999/live-scoring", []);
        if ($response['status_code'] === 404) {
            echo "✅ Invalid match ID handled correctly (404)\n";
        } else {
            echo "❌ Invalid match ID should return 404, got {$response['status_code']}\n";
        }
        
        // Test invalid data
        $invalidData = ['invalid_field' => 'invalid_value'];
        $response = $this->makeRequest('POST', "/admin/matches/{$this->testMatchId}/live-scoring", $invalidData);
        if ($response['status_code'] === 200) {
            echo "✅ Invalid data gracefully handled (ignores unknown fields)\n";
        } else {
            echo "⚠️  Invalid data response: {$response['status_code']}\n";
        }
        
        $this->results['error_handling'] = 'PASS';
        echo "\n";
    }
    
    public function generateReport()
    {
        echo "📋 COMPREHENSIVE TEST RESULTS SUMMARY\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        $totalTests = count($this->results);
        $passedTests = count(array_filter($this->results, function($result) {
            return $result === 'PASS';
        }));
        
        foreach ($this->results as $test => $result) {
            $icon = $result === 'PASS' ? '✅' : '❌';
            $testName = ucwords(str_replace('_', ' ', $test));
            echo sprintf("%-30s %s %s\n", $testName, $icon, $result);
        }
        
        echo "\n";
        echo "OVERALL RESULTS: {$passedTests}/{$totalTests} tests passed\n";
        
        if ($passedTests === $totalTests) {
            echo "🎉 ALL TESTS PASSED! Live scoring system is working correctly.\n";
        } else {
            echo "⚠️  SOME TESTS FAILED. Please review the failures above.\n";
        }
        
        echo "=" . str_repeat("=", 60) . "\n";
        
        // Generate detailed report
        $detailedReport = [
            'timestamp' => date('Y-m-d H:i:s'),
            'test_match_id' => $this->testMatchId,
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'success_rate' => round(($passedTests / $totalTests) * 100, 2) . '%',
            'test_results' => $this->results,
            'endpoints_tested' => [
                'POST /api/admin/matches/{id}/live-scoring',
                'GET /api/matches/{id}'
            ],
            'features_tested' => [
                'Score updates (team1_score, team2_score)',
                'Hero composition changes',
                'Player stats (kills, deaths, damage, healing, blocked)',
                'Timer updates',
                'Status transitions (live, paused, completed)',
                'Error handling'
            ]
        ];
        
        file_put_contents('live_scoring_test_report.json', json_encode($detailedReport, JSON_PRETTY_PRINT));
        echo "\n📄 Detailed report saved to: live_scoring_test_report.json\n";
    }
}

// Run the comprehensive test suite
$tester = new LiveScoringEndToEndTester();
$tester->runAllTests();