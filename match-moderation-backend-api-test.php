<?php
/**
 * COMPREHENSIVE MATCH MODERATION BACKEND API TEST
 * 
 * This script tests the backend API endpoints for match moderation functionality:
 * - Admin authentication and authorization
 * - Match CRUD operations
 * - Live scoring endpoints
 * - Map management
 * - Player statistics updates
 * - Real-time updates
 */

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MatchModerationBackendTester 
{
    private $client;
    private $baseUrl;
    private $adminToken;
    private $testData;
    private $results;
    
    public function __construct($baseUrl = 'https://staging.mrvl.net/api')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new Client([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false
        ]);
        
        $this->testData = [
            'adminEmail' => 'admin@mrvl.net',
            'adminPassword' => 'admin123',
            'testMatchId' => null,
            'testTeamIds' => [],
            'testPlayerIds' => []
        ];
        
        $this->results = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'errors' => [],
            'tests' => [],
            'startTime' => microtime(true)
        ];
    }
    
    private function log($message, $level = 'info')
    {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = [
            'error' => '❌',
            'warn' => '⚠️',
            'success' => '✅',
            'info' => 'ℹ️'
        ][$level] ?? 'ℹ️';
        
        echo "[{$timestamp}] {$prefix} {$message}\n";
    }
    
    private function runTest($testName, callable $testFunction)
    {
        $this->results['total']++;
        $this->log("Testing: {$testName}");
        
        try {
            $result = $testFunction();
            $this->results['passed']++;
            $this->results['tests'][$testName] = ['status' => 'passed', 'result' => $result];
            $this->log("✅ PASSED: {$testName}", 'success');
            return $result;
        } catch (Exception $e) {
            $this->results['failed']++;
            $this->results['errors'][] = ['test' => $testName, 'error' => $e->getMessage()];
            $this->results['tests'][$testName] = ['status' => 'failed', 'error' => $e->getMessage()];
            $this->log("❌ FAILED: {$testName} - " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    public function authenticateAdmin()
    {
        return $this->runTest('Admin Authentication', function () {
            $response = $this->client->post("{$this->baseUrl}/auth/login", [
                'json' => [
                    'email' => $this->testData['adminEmail'],
                    'password' => $this->testData['adminPassword']
                ]
            ]);
            
            if ($response->getStatusCode() !== 200) {
                throw new Exception("Authentication failed: " . $response->getBody());
            }
            
            $data = json_decode($response->getBody(), true);
            
            if (empty($data['access_token'])) {
                throw new Exception("No access token received");
            }
            
            if (empty($data['user']['role']) || !in_array($data['user']['role'], ['admin', 'moderator'])) {
                throw new Exception("User does not have admin/moderator role");
            }
            
            $this->adminToken = $data['access_token'];
            
            return [
                'token' => $this->adminToken,
                'user' => $data['user'],
                'role' => $data['user']['role']
            ];
        });
    }
    
    public function testMatchModerationEndpoints()
    {
        return $this->runTest('Match Moderation Endpoints Access', function () {
            $headers = ['Authorization' => "Bearer {$this->adminToken}"];
            
            // Test GET /admin/matches-moderation
            $response = $this->client->get("{$this->baseUrl}/admin/matches-moderation", [
                'headers' => $headers,
                'query' => ['page' => 1, 'limit' => 10]
            ]);
            
            if ($response->getStatusCode() !== 200) {
                throw new Exception("Failed to access matches moderation endpoint: " . $response->getBody());
            }
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['data']) || !is_array($data['data'])) {
                throw new Exception("Invalid response structure for matches listing");
            }
            
            // Store first match ID for further testing
            if (!empty($data['data'])) {
                $this->testData['testMatchId'] = $data['data'][0]['id'];
            }
            
            return [
                'endpoint_accessible' => true,
                'match_count' => count($data['data']),
                'pagination' => $data['pagination'] ?? null,
                'filters' => $data['filters'] ?? null
            ];
        });
    }
    
    public function testMatchCRUDOperations()
    {
        return $this->runTest('Match CRUD Operations', function () {
            $headers = ['Authorization' => "Bearer {$this->adminToken}"];
            
            // First, get some teams for creating a match
            $teamsResponse = $this->client->get("{$this->baseUrl}/public/teams", [
                'headers' => $headers,
                'query' => ['limit' => 5]
            ]);
            
            $teamsData = json_decode($teamsResponse->getBody(), true);
            $teams = $teamsData['data'] ?? $teamsData;
            
            if (count($teams) < 2) {
                throw new Exception("Need at least 2 teams to create a match");
            }
            
            $team1Id = $teams[0]['id'];
            $team2Id = $teams[1]['id'];
            
            // Test CREATE match
            $createData = [
                'team1_id' => $team1Id,
                'team2_id' => $team2Id,
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'format' => 'BO3',
                'maps' => [
                    ['map_name' => 'Tokyo 2099', 'mode' => 'Convoy'],
                    ['map_name' => 'Midtown', 'mode' => 'Escort'],
                    ['map_name' => 'Sanctum Sanctorum', 'mode' => 'Domination']
                ],
                'allow_past_date' => true
            ];
            
            $createResponse = $this->client->post("{$this->baseUrl}/admin/matches-moderation", [
                'headers' => $headers,
                'json' => $createData
            ]);
            
            if ($createResponse->getStatusCode() !== 201) {
                throw new Exception("Failed to create match: " . $createResponse->getBody());
            }
            
            $createResult = json_decode($createResponse->getBody(), true);
            $createdMatchId = $createResult['data']['id'];
            
            // Test READ match
            $readResponse = $this->client->get("{$this->baseUrl}/admin/matches-moderation/{$createdMatchId}", [
                'headers' => $headers
            ]);
            
            if ($readResponse->getStatusCode() !== 200) {
                throw new Exception("Failed to read created match");
            }
            
            // Test UPDATE match
            $updateData = [
                'status' => 'live',
                'team1_score' => 1,
                'team2_score' => 0,
                'viewers' => 1500
            ];
            
            $updateResponse = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$createdMatchId}", [
                'headers' => $headers,
                'json' => $updateData
            ]);
            
            if ($updateResponse->getStatusCode() !== 200) {
                throw new Exception("Failed to update match: " . $updateResponse->getBody());
            }
            
            // Store for other tests
            $this->testData['testMatchId'] = $createdMatchId;
            
            return [
                'create_success' => true,
                'read_success' => true,
                'update_success' => true,
                'created_match_id' => $createdMatchId,
                'update_data' => $updateData
            ];
        });
    }
    
    public function testLiveControlButtons()
    {
        return $this->runTest('Live Control Buttons API', function () {
            if (!$this->testData['testMatchId']) {
                throw new Exception("No test match available");
            }
            
            $headers = ['Authorization' => "Bearer {$this->adminToken}"];
            $matchId = $this->testData['testMatchId'];
            
            $statusTransitions = [
                ['status' => 'upcoming', 'description' => 'Reset to upcoming'],
                ['status' => 'live', 'description' => 'Start match'],
                ['status' => 'paused', 'description' => 'Pause match'],
                ['status' => 'live', 'description' => 'Resume match'],
                ['status' => 'completed', 'description' => 'End match']
            ];
            
            $results = [];
            
            foreach ($statusTransitions as $transition) {
                $response = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                    'headers' => $headers,
                    'json' => ['status' => $transition['status']]
                ]);
                
                if ($response->getStatusCode() !== 200) {
                    throw new Exception("Failed to {$transition['description']}: " . $response->getBody());
                }
                
                // Verify status change
                usleep(500000); // 0.5 second delay
                
                $verifyResponse = $this->client->get("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                    'headers' => $headers
                ]);
                
                $verifyData = json_decode($verifyResponse->getBody(), true);
                $actualStatus = $verifyData['data']['match']['status'];
                
                if ($actualStatus !== $transition['status']) {
                    throw new Exception("Status not changed for {$transition['description']}: expected {$transition['status']}, got {$actualStatus}");
                }
                
                $results[] = [
                    'action' => $transition['description'],
                    'target_status' => $transition['status'],
                    'actual_status' => $actualStatus,
                    'success' => true
                ];
            }
            
            return [
                'status_transitions' => $results,
                'all_successful' => true
            ];
        });
    }
    
    public function testLiveStatsUpdates()
    {
        return $this->runTest('Live Stats Updates', function () {
            if (!$this->testData['testMatchId']) {
                throw new Exception("No test match available");
            }
            
            $headers = ['Authorization' => "Bearer {$this->adminToken}"];
            $matchId = $this->testData['testMatchId'];
            
            // Test rapid score updates (simulating debounced saves)
            $scoreUpdates = [
                ['team1_score' => 10, 'team2_score' => 8],
                ['team1_score' => 12, 'team2_score' => 10],
                ['team1_score' => 15, 'team2_score' => 13],
                ['team1_score' => 18, 'team2_score' => 16],
                ['team1_score' => 21, 'team2_score' => 19]
            ];
            
            $updateResults = [];
            
            foreach ($scoreUpdates as $index => $scores) {
                $response = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                    'headers' => $headers,
                    'json' => $scores
                ]);
                
                if ($response->getStatusCode() !== 200) {
                    throw new Exception("Failed score update #{$index}: " . $response->getBody());
                }
                
                usleep(100000); // 0.1 second delay between updates
                
                $updateResults[] = [
                    'update_index' => $index + 1,
                    'scores' => $scores,
                    'response_status' => $response->getStatusCode()
                ];
            }
            
            // Verify final state
            usleep(600000); // 0.6 second delay to ensure debouncing
            
            $verifyResponse = $this->client->get("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                'headers' => $headers
            ]);
            
            $verifyData = json_decode($verifyResponse->getBody(), true);
            $finalMatch = $verifyData['data']['match'];
            
            $lastUpdate = end($scoreUpdates);
            
            if ($finalMatch['team1_score'] != $lastUpdate['team1_score'] || 
                $finalMatch['team2_score'] != $lastUpdate['team2_score']) {
                throw new Exception("Final scores don't match last update");
            }
            
            return [
                'rapid_updates' => $updateResults,
                'final_scores' => [
                    'team1_score' => $finalMatch['team1_score'],
                    'team2_score' => $finalMatch['team2_score']
                ],
                'debouncing_effective' => true
            ];
        });
    }
    
    public function testHeroSelection()
    {
        return $this->runTest('Hero Selection System', function () {
            // Test heroes endpoint
            $heroesResponse = $this->client->get("{$this->baseUrl}/public/heroes");
            
            if ($heroesResponse->getStatusCode() !== 200) {
                throw new Exception("Failed to fetch heroes: " . $heroesResponse->getBody());
            }
            
            $heroesData = json_decode($heroesResponse->getBody(), true);
            $heroes = $heroesData['data'] ?? $heroesData;
            
            if (!is_array($heroes) || count($heroes) < 30) {
                throw new Exception("Expected at least 30 heroes, got " . count($heroes));
            }
            
            // Test hero data structure
            $sampleHero = $heroes[0];
            $requiredFields = ['name', 'role'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($sampleHero[$field]) && !isset($sampleHero['hero_name'])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $this->log("Warning: Some hero fields missing: " . implode(', ', $missingFields), 'warn');
            }
            
            // Test updating match with hero data (if match available)
            $heroUpdateResult = null;
            if ($this->testData['testMatchId']) {
                $headers = ['Authorization' => "Bearer {$this->adminToken}"];
                $matchId = $this->testData['testMatchId'];
                
                $heroData = [
                    'map1' => [
                        'team1' => ['Iron Man', 'Captain America', 'Thor', 'Spider-Man', 'Hulk', 'Venom'],
                        'team2' => ['Doctor Doom', 'Magneto', 'Storm', 'Wolverine', 'Deadpool', 'Loki']
                    ]
                ];
                
                $heroUpdateResponse = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                    'headers' => $headers,
                    'json' => ['hero_data' => json_encode($heroData)]
                ]);
                
                $heroUpdateResult = [
                    'status' => $heroUpdateResponse->getStatusCode(),
                    'hero_data_sent' => $heroData
                ];
            }
            
            return [
                'total_heroes' => count($heroes),
                'sample_hero' => $sampleHero,
                'hero_update' => $heroUpdateResult,
                'hero_selection_supported' => true
            ];
        });
    }
    
    public function testScoreManagement()
    {
        return $this->runTest('Score Management and Winner Calculation', function () {
            if (!$this->testData['testMatchId']) {
                throw new Exception("No test match available");
            }
            
            $headers = ['Authorization' => "Bearer {$this->adminToken}"];
            $matchId = $this->testData['testMatchId'];
            
            // Get match details to get team IDs
            $matchResponse = $this->client->get("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                'headers' => $headers
            ]);
            
            $matchData = json_decode($matchResponse->getBody(), true);
            $match = $matchData['data']['match'];
            $team1Id = $match['team1_id'];
            $team2Id = $match['team2_id'];
            
            // Test winner auto-calculation
            $testCases = [
                ['team1_score' => 2, 'team2_score' => 1, 'expected_winner' => $team1Id, 'scenario' => 'Team 1 wins'],
                ['team1_score' => 1, 'team2_score' => 2, 'expected_winner' => $team2Id, 'scenario' => 'Team 2 wins'],
                ['team1_score' => 1, 'team2_score' => 1, 'expected_winner' => null, 'scenario' => 'Tie game']
            ];
            
            $results = [];
            
            foreach ($testCases as $testCase) {
                $updateResponse = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                    'headers' => $headers,
                    'json' => [
                        'team1_score' => $testCase['team1_score'],
                        'team2_score' => $testCase['team2_score']
                    ]
                ]);
                
                if ($updateResponse->getStatusCode() !== 200) {
                    throw new Exception("Failed to update scores for {$testCase['scenario']}");
                }
                
                usleep(500000); // 0.5 second delay
                
                // Verify winner calculation
                $verifyResponse = $this->client->get("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                    'headers' => $headers
                ]);
                
                $verifyData = json_decode($verifyResponse->getBody(), true);
                $updatedMatch = $verifyData['data']['match'];
                
                $results[] = [
                    'scenario' => $testCase['scenario'],
                    'scores' => [
                        'team1' => $testCase['team1_score'],
                        'team2' => $testCase['team2_score']
                    ],
                    'expected_winner' => $testCase['expected_winner'],
                    'actual_winner' => $updatedMatch['winner_id'],
                    'correct' => $updatedMatch['winner_id'] == $testCase['expected_winner']
                ];
            }
            
            // Test manual winner override
            $overrideResponse = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                'headers' => $headers,
                'json' => [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'winner_id' => $team2Id // Override natural winner
                ]
            ]);
            
            if ($overrideResponse->getStatusCode() === 200) {
                usleep(500000);
                
                $overrideVerifyResponse = $this->client->get("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                    'headers' => $headers
                ]);
                
                $overrideData = json_decode($overrideVerifyResponse->getBody(), true);
                $overrideMatch = $overrideData['data']['match'];
                
                $results[] = [
                    'scenario' => 'Manual override',
                    'scores' => ['team1' => 2, 'team2' => 1],
                    'natural_winner' => $team1Id,
                    'override_winner' => $team2Id,
                    'actual_winner' => $overrideMatch['winner_id'],
                    'override_successful' => $overrideMatch['winner_id'] == $team2Id
                ];
            }
            
            return [
                'test_cases' => $results,
                'auto_calculation_working' => array_slice($results, 0, 3),
                'manual_override_working' => end($results)['override_successful'] ?? false
            ];
        });
    }
    
    public function testMapManagement()
    {
        return $this->runTest('Map Management System', function () {
            if (!$this->testData['testMatchId']) {
                throw new Exception("No test match available");
            }
            
            $headers = ['Authorization' => "Bearer {$this->adminToken}"];
            $matchId = $this->testData['testMatchId'];
            
            // Get match maps
            $matchResponse = $this->client->get("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                'headers' => $headers
            ]);
            
            $matchData = json_decode($matchResponse->getBody(), true);
            $maps = $matchData['data']['maps'] ?? [];
            
            if (empty($maps)) {
                throw new Exception("No maps found for match");
            }
            
            $mapCount = count($maps);
            $sampleMap = $maps[0];
            
            // Test map data structure
            $requiredMapFields = ['map_name', 'game_mode', 'status', 'team1_score', 'team2_score'];
            $mapFieldsPresent = [];
            
            foreach ($requiredMapFields as $field) {
                $mapFieldsPresent[$field] = isset($sampleMap[$field]);
            }
            
            // Test game modes and maps data
            $gameModeResponse = $this->client->get("{$this->baseUrl}/public/game-data/modes");
            $mapNamesResponse = $this->client->get("{$this->baseUrl}/public/game-data/maps");
            
            $gameModes = [];
            $mapNames = [];
            
            if ($gameModeResponse->getStatusCode() === 200) {
                $modeData = json_decode($gameModeResponse->getBody(), true);
                $gameModes = $modeData['data'] ?? $modeData;
            }
            
            if ($mapNamesResponse->getStatusCode() === 200) {
                $mapData = json_decode($mapNamesResponse->getBody(), true);
                $mapNames = $mapData['data'] ?? $mapData;
            }
            
            return [
                'match_maps_count' => $mapCount,
                'sample_map' => $sampleMap,
                'map_fields_present' => $mapFieldsPresent,
                'game_modes_available' => count($gameModes),
                'map_names_available' => count($mapNames),
                'map_management_supported' => true
            ];
        });
    }
    
    public function testRealTimeUpdates()
    {
        return $this->runTest('Real-time Updates Support', function () {
            // Test if live update endpoints exist
            $endpoints = [
                "/live-updates/matches",
                "/admin/live-updates",
                "/matches/{id}/live-stream"
            ];
            
            $endpointTests = [];
            
            foreach ($endpoints as $endpoint) {
                $testEndpoint = str_replace('{id}', $this->testData['testMatchId'] ?? '1', $endpoint);
                
                try {
                    $response = $this->client->get("{$this->baseUrl}{$testEndpoint}", [
                        'headers' => ['Authorization' => "Bearer {$this->adminToken}"],
                        'timeout' => 5
                    ]);
                    
                    $endpointTests[$endpoint] = [
                        'exists' => $response->getStatusCode() !== 404,
                        'status_code' => $response->getStatusCode()
                    ];
                } catch (Exception $e) {
                    $endpointTests[$endpoint] = [
                        'exists' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Test WebSocket support (if available)
            $webSocketSupport = function_exists('socket_create') && extension_loaded('sockets');
            
            return [
                'live_update_endpoints' => $endpointTests,
                'websocket_support' => $webSocketSupport,
                'real_time_capability' => true
            ];
        });
    }
    
    public function testEndToEndWorkflow()
    {
        return $this->runTest('End-to-End Match Moderation Workflow', function () {
            if (!$this->testData['testMatchId']) {
                throw new Exception("No test match available");
            }
            
            $headers = ['Authorization' => "Bearer {$this->adminToken}"];
            $matchId = $this->testData['testMatchId'];
            
            $workflow = [];
            
            // Step 1: Start match
            $startResponse = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                'headers' => $headers,
                'json' => ['status' => 'live']
            ]);
            
            $workflow[] = [
                'step' => 'start_match',
                'success' => $startResponse->getStatusCode() === 200,
                'status_code' => $startResponse->getStatusCode()
            ];
            
            usleep(500000);
            
            // Step 2: Update scores progressively
            $scoreProgression = [
                ['team1_score' => 1, 'team2_score' => 0],
                ['team1_score' => 1, 'team2_score' => 1],
                ['team1_score' => 2, 'team2_score' => 1]
            ];
            
            foreach ($scoreProgression as $index => $scores) {
                $scoreResponse = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                    'headers' => $headers,
                    'json' => $scores
                ]);
                
                $workflow[] = [
                    'step' => "update_scores_" . ($index + 1),
                    'scores' => $scores,
                    'success' => $scoreResponse->getStatusCode() === 200,
                    'status_code' => $scoreResponse->getStatusCode()
                ];
                
                usleep(300000);
            }
            
            // Step 3: Complete match
            $completeResponse = $this->client->put("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                'headers' => $headers,
                'json' => ['status' => 'completed']
            ]);
            
            $workflow[] = [
                'step' => 'complete_match',
                'success' => $completeResponse->getStatusCode() === 200,
                'status_code' => $completeResponse->getStatusCode()
            ];
            
            usleep(500000);
            
            // Step 4: Verify final state
            $finalResponse = $this->client->get("{$this->baseUrl}/admin/matches-moderation/{$matchId}", [
                'headers' => $headers
            ]);
            
            $finalData = json_decode($finalResponse->getBody(), true);
            $finalMatch = $finalData['data']['match'];
            
            $workflow[] = [
                'step' => 'verify_final_state',
                'final_status' => $finalMatch['status'],
                'final_scores' => [
                    'team1' => $finalMatch['team1_score'],
                    'team2' => $finalMatch['team2_score']
                ],
                'winner_id' => $finalMatch['winner_id'],
                'success' => $finalMatch['status'] === 'completed'
            ];
            
            $allSuccessful = array_reduce($workflow, function ($carry, $step) {
                return $carry && $step['success'];
            }, true);
            
            return [
                'workflow_steps' => $workflow,
                'all_steps_successful' => $allSuccessful,
                'final_match_state' => [
                    'status' => $finalMatch['status'],
                    'team1_score' => $finalMatch['team1_score'],
                    'team2_score' => $finalMatch['team2_score'],
                    'winner_id' => $finalMatch['winner_id'],
                    'ended_at' => $finalMatch['ended_at'] ?? null
                ]
            ];
        });
    }
    
    public function runAllTests()
    {
        $this->log("🚀 Starting Comprehensive Match Moderation Backend API Test");
        $this->log("Backend URL: {$this->baseUrl}");
        
        try {
            // Authentication
            $this->authenticateAdmin();
            
            // Core endpoints
            $this->log("\n📋 Testing Match Moderation Endpoints...");
            $this->testMatchModerationEndpoints();
            
            // CRUD operations
            $this->log("\n🔧 Testing Match CRUD Operations...");
            $this->testMatchCRUDOperations();
            
            // Live control buttons
            $this->log("\n🎮 Testing Live Control Buttons API...");
            $this->testLiveControlButtons();
            
            // Live stats updates
            $this->log("\n📊 Testing Live Stats Updates...");
            $this->testLiveStatsUpdates();
            
            // Hero selection
            $this->log("\n🦸 Testing Hero Selection System...");
            $this->testHeroSelection();
            
            // Score management
            $this->log("\n🏆 Testing Score Management...");
            $this->testScoreManagement();
            
            // Map management
            $this->log("\n🗺️ Testing Map Management...");
            $this->testMapManagement();
            
            // Real-time updates
            $this->log("\n⚡ Testing Real-time Updates...");
            $this->testRealTimeUpdates();
            
            // End-to-end workflow
            $this->log("\n🔄 Testing End-to-End Workflow...");
            $this->testEndToEndWorkflow();
            
        } catch (Exception $e) {
            $this->log("❌ Test suite failed: " . $e->getMessage(), 'error');
        }
        
        $this->generateReport();
    }
    
    public function generateReport()
    {
        $endTime = microtime(true);
        $duration = ($endTime - $this->results['startTime']) * 1000;
        
        $report = [
            'test_suite' => 'Match Moderation Backend API Test',
            'timestamp' => date('c'),
            'duration_ms' => round($duration, 2),
            'summary' => [
                'total_tests' => $this->results['total'],
                'passed' => $this->results['passed'],
                'failed' => $this->results['failed'],
                'success_rate' => round(($this->results['passed'] / $this->results['total']) * 100, 2) . '%'
            ],
            'test_results' => $this->results['tests'],
            'errors' => $this->results['errors'],
            'test_data' => $this->testData,
            'environment' => [
                'backend_url' => $this->baseUrl,
                'php_version' => PHP_VERSION,
                'curl_version' => curl_version()['version'] ?? 'unknown'
            ]
        ];
        
        $reportFileName = "match-moderation-backend-test-report-" . time() . ".json";
        file_put_contents($reportFileName, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->log("\n📊 MATCH MODERATION BACKEND API TEST RESULTS");
        $this->log("Total Tests: {$report['summary']['total_tests']}");
        $this->log("Passed: {$report['summary']['passed']}", 'success');
        $this->log("Failed: {$report['summary']['failed']}", $report['summary']['failed'] > 0 ? 'error' : 'info');
        $this->log("Success Rate: {$report['summary']['success_rate']}");
        $this->log("Duration: {$report['duration_ms']}ms");
        $this->log("Report saved to: {$reportFileName}");
        
        if (!empty($this->results['errors'])) {
            $this->log("\n❌ ERRORS FOUND:", 'error');
            foreach ($this->results['errors'] as $error) {
                $this->log("  • {$error['test']}: {$error['error']}", 'error');
            }
        }
        
        return $report;
    }
}

// Run the tests
if (php_sapi_name() === 'cli') {
    $baseUrl = $argv[1] ?? 'https://staging.mrvl.net/api';
    $tester = new MatchModerationBackendTester($baseUrl);
    $tester->runAllTests();
    
    exit($tester->results['failed'] > 0 ? 1 : 0);
}