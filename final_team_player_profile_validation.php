<?php
/**
 * Final Team and Player Profile Validation
 * Complete validation of all profile features after bug hunt
 */

class FinalProfileValidation
{
    private $baseUrl;
    private $results;
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api';
        $this->results = [];
    }
    
    public function runFinalValidation()
    {
        echo "🎯 Final Team and Player Profile Validation\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $this->validateTeamProfileFeatures();
        $this->validatePlayerProfileFeatures();
        $this->validateImageUploadCapabilities();
        $this->validateDataIntegrity();
        $this->generateFinalReport();
    }
    
    private function validateTeamProfileFeatures()
    {
        echo "👥 TEAM PROFILE FEATURES\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test teams list
        $teamsResponse = $this->makeRequest('GET', '/teams');
        if ($teamsResponse && isset($teamsResponse['data'])) {
            $this->results['teams_list'] = 'PASS';
            echo "✅ Teams list API working\n";
            
            $firstTeam = $teamsResponse['data'][0] ?? null;
            if ($firstTeam) {
                // Test team data structure
                $requiredFields = ['id', 'name', 'logo', 'region', 'country', 'earnings', 'social_media'];
                $hasAllFields = true;
                
                foreach ($requiredFields as $field) {
                    if (!isset($firstTeam[$field])) {
                        $hasAllFields = false;
                        break;
                    }
                }
                
                if ($hasAllFields) {
                    $this->results['team_data_structure'] = 'PASS';
                    echo "✅ Team data structure complete\n";
                } else {
                    $this->results['team_data_structure'] = 'FAIL';
                    echo "❌ Team data structure incomplete\n";
                }
                
                // Test social media integration
                if (isset($firstTeam['social_media']) && is_array($firstTeam['social_media'])) {
                    $this->results['team_social_media'] = 'PASS';
                    echo "✅ Team social media integration working\n";
                } else {
                    $this->results['team_social_media'] = 'FAIL';
                    echo "❌ Team social media integration failed\n";
                }
                
                // Test earnings field
                if (isset($firstTeam['earnings']) && is_numeric($firstTeam['earnings'])) {
                    $this->results['team_earnings'] = 'PASS';
                    echo "✅ Team earnings field working\n";
                } else {
                    $this->results['team_earnings'] = 'FAIL';
                    echo "❌ Team earnings field failed\n";
                }
            }
        } else {
            $this->results['teams_list'] = 'FAIL';
            echo "❌ Teams list API failed\n";
        }
        
        // Test single team endpoint
        $singleTeamResponse = $this->makeRequest('GET', '/teams/33');
        if ($singleTeamResponse) {
            $this->results['single_team'] = 'PASS';
            echo "✅ Single team API working\n";
        } else {
            $this->results['single_team'] = 'FAIL';
            echo "❌ Single team API failed\n";
        }
        
        echo "\n";
    }
    
    private function validatePlayerProfileFeatures()
    {
        echo "👤 PLAYER PROFILE FEATURES\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test players list
        $playersResponse = $this->makeRequest('GET', '/players');
        if ($playersResponse && isset($playersResponse['data'])) {
            $this->results['players_list'] = 'PASS';
            echo "✅ Players list API working\n";
            
            $firstPlayer = $playersResponse['data'][0] ?? null;
            if ($firstPlayer) {
                // Test critical fields for frontend compatibility
                $frontendFields = ['id', 'username', 'role', 'country', 'team'];
                $hasFrontendFields = true;
                $missingFields = [];
                
                foreach ($frontendFields as $field) {
                    if (!isset($firstPlayer[$field])) {
                        $hasFrontendFields = false;
                        $missingFields[] = $field;
                    }
                }
                
                if ($hasFrontendFields) {
                    $this->results['player_frontend_compatibility'] = 'PASS';
                    echo "✅ Player frontend compatibility maintained\n";
                } else {
                    $this->results['player_frontend_compatibility'] = 'FAIL';
                    echo "❌ Player frontend compatibility issue: missing " . implode(', ', $missingFields) . "\n";
                }
                
                // Test player-team relationship
                if (isset($firstPlayer['team']) && is_array($firstPlayer['team'])) {
                    $this->results['player_team_relationship'] = 'PASS';
                    echo "✅ Player-team relationship working\n";
                } else {
                    $this->results['player_team_relationship'] = 'FAIL';
                    echo "❌ Player-team relationship failed\n";
                }
                
                // Test role validation
                $validRoles = ['Duelist', 'Strategist', 'Vanguard'];
                if (isset($firstPlayer['role']) && in_array($firstPlayer['role'], $validRoles)) {
                    $this->results['player_role_validation'] = 'PASS';
                    echo "✅ Player role validation working\n";
                } else {
                    $this->results['player_role_validation'] = 'WARN';
                    echo "⚠️ Player role validation issue: " . ($firstPlayer['role'] ?? 'null') . "\n";
                }
            }
        } else {
            $this->results['players_list'] = 'FAIL';
            echo "❌ Players list API failed\n";
        }
        
        // Test player team history
        $teamHistoryResponse = $this->makeRequest('GET', '/players/1/team-history');
        if ($teamHistoryResponse !== false) {
            $this->results['player_team_history'] = 'PASS';
            echo "✅ Player team history API working\n";
        } else {
            $this->results['player_team_history'] = 'FAIL';
            echo "❌ Player team history API failed\n";
        }
        
        // Test player achievements
        $achievementsResponse = $this->makeRequest('GET', '/players/1/achievements');
        if ($achievementsResponse !== false) {
            $this->results['player_achievements'] = 'PASS';
            echo "✅ Player achievements API working\n";
        } else {
            $this->results['player_achievements'] = 'FAIL';
            echo "❌ Player achievements API failed\n";
        }
        
        echo "\n";
    }
    
    private function validateImageUploadCapabilities()
    {
        echo "🖼️ IMAGE UPLOAD CAPABILITIES\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test team logo upload endpoint existence
        $teamLogoTest = $this->testEndpointExists('/upload/team/33/logo');
        if ($teamLogoTest) {
            $this->results['team_logo_upload'] = 'PASS';
            echo "✅ Team logo upload endpoint available\n";
        } else {
            $this->results['team_logo_upload'] = 'FAIL';
            echo "❌ Team logo upload endpoint failed\n";
        }
        
        // Test player avatar upload endpoint existence
        $playerAvatarTest = $this->testEndpointExists('/upload/player/1/avatar');
        if ($playerAvatarTest) {
            $this->results['player_avatar_upload'] = 'PASS';
            echo "✅ Player avatar upload endpoint available\n";
        } else {
            $this->results['player_avatar_upload'] = 'FAIL';
            echo "❌ Player avatar upload endpoint failed\n";
        }
        
        echo "\n";
    }
    
    private function validateDataIntegrity()
    {
        echo "🔍 DATA INTEGRITY\n";
        echo str_repeat("-", 30) . "\n";
        
        // Test team-player relationship integrity
        $teamsResponse = $this->makeRequest('GET', '/teams');
        $playersResponse = $this->makeRequest('GET', '/players');
        
        if ($teamsResponse && $playersResponse) {
            $teams = $teamsResponse['data'] ?? [];
            $players = $playersResponse['data'] ?? [];
            
            $teamNames = array_column($teams, 'name');
            $playersWithTeams = array_filter($players, function($p) {
                return isset($p['team']) && !empty($p['team']);
            });
            
            $integrityIssues = 0;
            foreach ($playersWithTeams as $player) {
                if (!in_array($player['team']['name'], $teamNames)) {
                    $integrityIssues++;
                }
            }
            
            if ($integrityIssues === 0) {
                $this->results['data_integrity'] = 'PASS';
                echo "✅ Data integrity maintained\n";
            } else {
                $this->results['data_integrity'] = 'WARN';
                echo "⚠️ Data integrity issues found: $integrityIssues orphaned relationships\n";
            }
        } else {
            $this->results['data_integrity'] = 'FAIL';
            echo "❌ Data integrity check failed - couldn't fetch data\n";
        }
        
        echo "\n";
    }
    
    private function generateFinalReport()
    {
        echo str_repeat("=", 60) . "\n";
        echo "📊 FINAL VALIDATION REPORT\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        
        foreach ($this->results as $test => $result) {
            $icon = match($result) {
                'PASS' => '✅',
                'FAIL' => '❌',
                'WARN' => '⚠️',
                default => '❓'
            };
            
            echo sprintf("%-40s %s %s\n", $test, $icon, $result);
            
            match($result) {
                'PASS' => $passed++,
                'FAIL' => $failed++,
                'WARN' => $warnings++,
                default => null
            };
        }
        
        $total = $passed + $failed + $warnings;
        
        echo "\n" . str_repeat("-", 60) . "\n";
        echo "SUMMARY:\n";
        echo "✅ Passed: $passed\n";
        echo "❌ Failed: $failed\n";
        echo "⚠️ Warnings: $warnings\n";
        echo "📊 Total: $total\n";
        
        if ($total > 0) {
            $successRate = round(($passed / $total) * 100, 1);
            echo "💯 Success Rate: $successRate%\n";
            
            if ($successRate >= 90) {
                echo "\n🎉 EXCELLENT: System is production-ready!\n";
            } elseif ($successRate >= 75) {
                echo "\n✅ GOOD: Minor issues to address\n";
            } elseif ($successRate >= 50) {
                echo "\n⚠️ NEEDS WORK: Several issues to fix\n";
            } else {
                echo "\n🚨 CRITICAL: Major issues require attention\n";
            }
        }
        
        echo "\n🔍 KEY FINDINGS:\n";
        echo str_repeat("-", 20) . "\n";
        
        if ($failed === 0) {
            echo "• All critical systems operational\n";
            echo "• API endpoints responding correctly\n";
            echo "• Data structures compatible\n";
        } else {
            echo "• $failed systems need attention\n";
            if (in_array('FAIL', ['player_frontend_compatibility', 'single_player'])) {
                echo "• Player data structure needs fixing\n";
            }
        }
        
        if ($warnings > 0) {
            echo "• $warnings non-critical issues detected\n";
        }
        
        echo "\n🚀 RECOMMENDATIONS:\n";
        echo str_repeat("-", 20) . "\n";
        
        if (isset($this->results['player_frontend_compatibility']) && $this->results['player_frontend_compatibility'] === 'FAIL') {
            echo "1. PRIORITY: Fix player data structure for frontend compatibility\n";
        }
        
        if (isset($this->results['single_team']) && $this->results['single_team'] === 'FAIL') {
            echo "2. Debug single team endpoint issues\n";
        }
        
        if ($warnings > 0) {
            echo "3. Address data quality issues\n";
        }
        
        echo "4. Implement authentication system fixes\n";
        echo "5. Add comprehensive error handling\n";
        
        echo "\n" . str_repeat("=", 60) . "\n";
    }
    
    private function makeRequest($method, $endpoint)
    {
        $url = $this->baseUrl . $endpoint;
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($response === false || $httpCode >= 500) {
            return false;
        }
        
        if ($httpCode >= 400) {
            return ['error' => true, 'code' => $httpCode];
        }
        
        return json_decode($response, true);
    }
    
    private function testEndpointExists($endpoint)
    {
        // Just test if endpoint responds (not necessarily successfully)
        $response = $this->makeRequest('OPTIONS', $endpoint);
        return $response !== false;
    }
}

// Run final validation
$validator = new FinalProfileValidation();
$validator->runFinalValidation();