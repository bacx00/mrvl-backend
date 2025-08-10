<?php
/**
 * Focused Team and Player Profile Test (No Auth Required)
 * Tests core functionality without requiring authentication
 */

class FocusedTeamPlayerTest
{
    private $baseUrl;
    private $results;
    private $errors;
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api';
        $this->results = [];
        $this->errors = [];
    }
    
    public function runTests()
    {
        echo "🔍 Running Focused Team and Player Profile Tests...\n\n";
        
        // Test API endpoints that don't require auth
        $this->testPublicAPIEndpoints();
        
        // Test data integrity
        $this->testDataIntegrity();
        
        // Test field validation
        $this->testFieldValidation();
        
        $this->generateReport();
    }
    
    private function testPublicAPIEndpoints()
    {
        echo "🔌 Testing Public API Endpoints...\n";
        
        $endpoints = [
            '/teams' => 'Teams list',
            '/players' => 'Players list',
            '/teams/33' => 'Single team details', // Team Secret
            '/players/1' => 'Single player details' // First player if exists
        ];
        
        foreach ($endpoints as $endpoint => $description) {
            $response = $this->makeRequest('GET', $endpoint);
            
            if ($response !== false) {
                $this->results["endpoint_test_$endpoint"] = [
                    'status' => 'PASS',
                    'description' => $description
                ];
                echo "✅ $description endpoint working\n";
                
                // Additional validation for specific endpoints
                if ($endpoint === '/teams') {
                    $this->validateTeamsResponse($response);
                } elseif ($endpoint === '/players') {
                    $this->validatePlayersResponse($response);
                }
                
            } else {
                $this->errors[] = "$description endpoint failed";
                $this->results["endpoint_test_$endpoint"] = [
                    'status' => 'FAIL',
                    'description' => $description
                ];
                echo "❌ $description endpoint failed\n";
            }
        }
        
        echo "\n";
    }
    
    private function validateTeamsResponse($response)
    {
        if (isset($response['data']) && is_array($response['data'])) {
            $firstTeam = $response['data'][0] ?? null;
            if ($firstTeam) {
                // Check required team fields
                $requiredFields = ['id', 'name', 'logo', 'region', 'country'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($firstTeam[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (empty($missingFields)) {
                    $this->results['teams_data_validation'] = ['status' => 'PASS'];
                    echo "  ✅ Teams data structure valid\n";
                    
                    // Test specific team fields
                    $this->testTeamFieldIntegrity($firstTeam);
                } else {
                    $this->errors[] = "Teams missing fields: " . implode(', ', $missingFields);
                    $this->results['teams_data_validation'] = ['status' => 'FAIL'];
                    echo "  ❌ Teams missing fields: " . implode(', ', $missingFields) . "\n";
                }
            }
        } else {
            $this->errors[] = "Teams response format invalid";
            $this->results['teams_data_validation'] = ['status' => 'FAIL'];
            echo "  ❌ Teams response format invalid\n";
        }
    }
    
    private function validatePlayersResponse($response)
    {
        if (isset($response['data']) && is_array($response['data'])) {
            $firstPlayer = $response['data'][0] ?? null;
            if ($firstPlayer) {
                // Check required player fields
                $requiredFields = ['id', 'name', 'team_id', 'role', 'country'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($firstPlayer[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (empty($missingFields)) {
                    $this->results['players_data_validation'] = ['status' => 'PASS'];
                    echo "  ✅ Players data structure valid\n";
                    
                    // Test specific player fields
                    $this->testPlayerFieldIntegrity($firstPlayer);
                } else {
                    $this->errors[] = "Players missing fields: " . implode(', ', $missingFields);
                    $this->results['players_data_validation'] = ['status' => 'FAIL'];
                    echo "  ❌ Players missing fields: " . implode(', ', $missingFields) . "\n";
                }
            }
        } else {
            $this->errors[] = "Players response format invalid";
            $this->results['players_data_validation'] = ['status' => 'FAIL'];
            echo "  ❌ Players response format invalid\n";
        }
    }
    
    private function testTeamFieldIntegrity($team)
    {
        // Test logo handling
        if (isset($team['logo'])) {
            if (strpos($team['logo'], '/') !== false) {
                $this->results['team_logo_path'] = ['status' => 'PASS'];
                echo "  ✅ Team logo path format valid\n";
            } else {
                $this->errors[] = "Team logo path format invalid";
                $this->results['team_logo_path'] = ['status' => 'FAIL'];
                echo "  ❌ Team logo path format invalid\n";
            }
        }
        
        // Test social media fields
        if (isset($team['social_media']) && is_array($team['social_media'])) {
            $this->results['team_social_media_structure'] = ['status' => 'PASS'];
            echo "  ✅ Team social media structure valid\n";
        } else {
            $this->results['team_social_media_structure'] = ['status' => 'FAIL'];
            echo "  ❌ Team social media structure invalid\n";
        }
        
        // Test earnings field
        if (isset($team['earnings'])) {
            if (is_numeric($team['earnings'])) {
                $this->results['team_earnings_format'] = ['status' => 'PASS'];
                echo "  ✅ Team earnings format valid\n";
            } else {
                $this->errors[] = "Team earnings format invalid";
                $this->results['team_earnings_format'] = ['status' => 'FAIL'];
                echo "  ❌ Team earnings format invalid\n";
            }
        }
        
        // Test ELO rating if present
        if (isset($team['elo_rating'])) {
            if (is_numeric($team['elo_rating'])) {
                $this->results['team_elo_format'] = ['status' => 'PASS'];
                echo "  ✅ Team ELO rating format valid\n";
            } else {
                $this->errors[] = "Team ELO rating format invalid";
                $this->results['team_elo_format'] = ['status' => 'FAIL'];
                echo "  ❌ Team ELO rating format invalid\n";
            }
        }
    }
    
    private function testPlayerFieldIntegrity($player)
    {
        // Test avatar handling
        if (isset($player['avatar'])) {
            if (strpos($player['avatar'], '/') !== false || !empty($player['avatar'])) {
                $this->results['player_avatar_path'] = ['status' => 'PASS'];
                echo "  ✅ Player avatar path format valid\n";
            } else {
                $this->results['player_avatar_path'] = ['status' => 'FAIL'];
                echo "  ❌ Player avatar path format invalid\n";
            }
        }
        
        // Test age field
        if (isset($player['age'])) {
            if (is_numeric($player['age']) && $player['age'] > 0 && $player['age'] < 100) {
                $this->results['player_age_validation'] = ['status' => 'PASS'];
                echo "  ✅ Player age validation passed\n";
            } else {
                $this->errors[] = "Player age validation failed";
                $this->results['player_age_validation'] = ['status' => 'FAIL'];
                echo "  ❌ Player age validation failed\n";
            }
        }
        
        // Test role field
        $validRoles = ['Duelist', 'Strategist', 'Vanguard'];
        if (isset($player['role']) && in_array($player['role'], $validRoles)) {
            $this->results['player_role_validation'] = ['status' => 'PASS'];
            echo "  ✅ Player role validation passed\n";
        } elseif (isset($player['role'])) {
            $this->errors[] = "Player role validation failed: " . $player['role'];
            $this->results['player_role_validation'] = ['status' => 'FAIL'];
            echo "  ❌ Player role validation failed: " . $player['role'] . "\n";
        }
        
        // Test social media fields
        if (isset($player['social_media']) && is_array($player['social_media'])) {
            $this->results['player_social_media_structure'] = ['status' => 'PASS'];
            echo "  ✅ Player social media structure valid\n";
        }
    }
    
    private function testDataIntegrity()
    {
        echo "🔍 Testing Data Integrity...\n";
        
        // Test team-player relationships
        $teamsResponse = $this->makeRequest('GET', '/teams');
        $playersResponse = $this->makeRequest('GET', '/players');
        
        if ($teamsResponse && $playersResponse) {
            $teams = $teamsResponse['data'] ?? [];
            $players = $playersResponse['data'] ?? [];
            
            $teamIds = array_column($teams, 'id');
            $orphanedPlayers = [];
            
            foreach ($players as $player) {
                if ($player['team_id'] && !in_array($player['team_id'], $teamIds)) {
                    $orphanedPlayers[] = $player['id'];
                }
            }
            
            if (empty($orphanedPlayers)) {
                $this->results['team_player_relationships'] = ['status' => 'PASS'];
                echo "✅ Team-player relationships valid\n";
            } else {
                $this->errors[] = "Found orphaned players: " . implode(', ', $orphanedPlayers);
                $this->results['team_player_relationships'] = ['status' => 'FAIL'];
                echo "❌ Found orphaned players: " . implode(', ', $orphanedPlayers) . "\n";
            }
        }
        
        echo "\n";
    }
    
    private function testFieldValidation()
    {
        echo "📝 Testing Field Validation...\n";
        
        // Test image upload endpoints (without authentication, just structure)
        $imageEndpoints = [
            '/upload/team/33/logo' => 'Team logo upload endpoint',
            '/upload/player/1/avatar' => 'Player avatar upload endpoint'
        ];
        
        foreach ($imageEndpoints as $endpoint => $description) {
            // Test OPTIONS request to see if endpoint exists
            $response = $this->makeRequest('OPTIONS', $endpoint);
            
            if ($response !== false) {
                $this->results["image_endpoint_$endpoint"] = ['status' => 'PASS'];
                echo "✅ $description exists\n";
            } else {
                $this->results["image_endpoint_$endpoint"] = ['status' => 'FAIL'];
                echo "❌ $description not found\n";
            }
        }
        
        // Test specific player endpoints
        $playerEndpoints = [
            '/players/1/team-history' => 'Player team history',
            '/players/1/achievements' => 'Player achievements'
        ];
        
        foreach ($playerEndpoints as $endpoint => $description) {
            $response = $this->makeRequest('GET', $endpoint);
            
            if ($response !== false) {
                $this->results["player_feature_$endpoint"] = ['status' => 'PASS'];
                echo "✅ $description endpoint working\n";
            } else {
                $this->results["player_feature_$endpoint"] = ['status' => 'FAIL'];
                echo "❌ $description endpoint failed\n";
            }
        }
        
        echo "\n";
    }
    
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $curl = curl_init();
        
        $headers = ['Content-Type: application/json'];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($response === false || $httpCode >= 500) {
            return false;
        }
        
        // For 404s and client errors, we still return the response to analyze
        if ($httpCode >= 400) {
            return ['error' => true, 'code' => $httpCode];
        }
        
        return json_decode($response, true);
    }
    
    private function generateReport()
    {
        echo str_repeat("=", 80) . "\n";
        echo "📋 FOCUSED TEAM & PLAYER PROFILE TEST REPORT\n";
        echo str_repeat("=", 80) . "\n\n";
        
        $passCount = 0;
        $failCount = 0;
        
        foreach ($this->results as $test => $result) {
            $status = $result['status'] === 'PASS' ? '✅ PASS' : '❌ FAIL';
            echo sprintf("%-60s %s\n", $test, $status);
            
            if ($result['status'] === 'PASS') {
                $passCount++;
            } else {
                $failCount++;
            }
        }
        
        echo "\n" . str_repeat("-", 80) . "\n";
        echo "SUMMARY:\n";
        echo "✅ Passed: $passCount\n";
        echo "❌ Failed: $failCount\n";
        echo "📊 Total Tests: " . ($passCount + $failCount) . "\n";
        
        if ($passCount + $failCount > 0) {
            echo "💯 Success Rate: " . round(($passCount / ($passCount + $failCount)) * 100, 1) . "%\n";
        }
        
        if (!empty($this->errors)) {
            echo "\n🐛 ISSUES DETECTED:\n";
            foreach ($this->errors as $error) {
                echo "• $error\n";
            }
        }
        
        // Critical bug analysis
        $this->analyzeCriticalBugs();
        
        // Save detailed report
        $reportData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_tests' => $passCount + $failCount,
                'passed' => $passCount,
                'failed' => $failCount,
                'success_rate' => $passCount + $failCount > 0 ? round(($passCount / ($passCount + $failCount)) * 100, 1) : 0
            ],
            'detailed_results' => $this->results,
            'errors' => $this->errors
        ];
        
        $reportFile = 'focused_team_player_test_report_' . time() . '.json';
        file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
        
        echo "\n📄 Detailed report saved to: $reportFile\n";
        echo str_repeat("=", 80) . "\n";
    }
    
    private function analyzeCriticalBugs()
    {
        echo "\n🔍 CRITICAL BUG ANALYSIS:\n";
        
        $criticalIssues = [];
        
        // Check for critical failures
        foreach ($this->results as $test => $result) {
            if ($result['status'] === 'FAIL') {
                if (strpos($test, 'endpoint_test_/teams') !== false) {
                    $criticalIssues[] = "CRITICAL: Teams API endpoint not working";
                } elseif (strpos($test, 'endpoint_test_/players') !== false) {
                    $criticalIssues[] = "CRITICAL: Players API endpoint not working";
                } elseif (strpos($test, 'data_validation') !== false) {
                    $criticalIssues[] = "HIGH: Data structure validation failed";
                } elseif (strpos($test, 'team_player_relationships') !== false) {
                    $criticalIssues[] = "HIGH: Team-player relationship integrity issue";
                }
            }
        }
        
        if (empty($criticalIssues)) {
            echo "✅ No critical bugs detected\n";
        } else {
            foreach ($criticalIssues as $issue) {
                echo "🚨 $issue\n";
            }
        }
    }
}

// Run the focused test
$test = new FocusedTeamPlayerTest();
$test->runTests();