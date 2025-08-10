<?php
/**
 * Comprehensive Team and Player Profile Bug Hunt Test Suite
 * Testing all CRUD operations, image uploads, field updates, and frontend integration
 * 
 * Areas tested:
 * 1. Image Upload (team logos, player avatars)
 * 2. Field Updates (elo ratings, earnings, age, country)
 * 3. Past/Current Teams Tracking
 * 4. Achievements System
 * 5. Social Media Links
 */

require_once 'vendor/autoload.php';

class TeamPlayerProfileBugHunter
{
    private $baseUrl;
    private $results;
    private $errors;
    private $authToken;
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api';
        $this->results = [];
        $this->errors = [];
        $this->authToken = null;
    }
    
    public function runAllTests()
    {
        echo "🔍 Starting Comprehensive Team and Player Profile Bug Hunt...\n\n";
        
        // Test authentication first
        $this->testAuthentication();
        
        if (!$this->authToken) {
            echo "❌ CRITICAL: Cannot proceed without authentication\n";
            return;
        }
        
        // Core CRUD Tests
        $this->testTeamCRUDOperations();
        $this->testPlayerCRUDOperations();
        
        // Feature-specific tests
        $this->testImageUploadFunctionality();
        $this->testFieldUpdateOperations();
        $this->testTeamHistoryTracking();
        $this->testAchievementSystem();
        $this->testSocialMediaLinks();
        
        // API Integration tests
        $this->testAPIEndpoints();
        
        // Frontend Integration tests
        $this->testFrontendIntegration();
        
        $this->generateReport();
    }
    
    private function testAuthentication()
    {
        echo "🔐 Testing Authentication...\n";
        
        // Test admin login
        $loginData = [
            'email' => 'admin@mrvl.gg',
            'password' => 'Password123!'
        ];
        
        $response = $this->makeRequest('POST', '/auth/login', $loginData);
        
        if ($response && isset($response['access_token'])) {
            $this->authToken = $response['access_token'];
            $this->results['authentication'] = ['status' => 'PASS', 'message' => 'Authentication successful'];
            echo "✅ Authentication successful\n";
        } else {
            $this->errors[] = "Authentication failed";
            $this->results['authentication'] = ['status' => 'FAIL', 'message' => 'Authentication failed'];
            echo "❌ Authentication failed\n";
        }
        echo "\n";
    }
    
    private function testTeamCRUDOperations()
    {
        echo "👥 Testing Team CRUD Operations...\n";
        
        // Test team creation
        $teamData = [
            'name' => 'Test Team Bug Hunt',
            'short_name' => 'TTBH',
            'region' => 'North America',
            'country' => 'United States',
            'description' => 'A test team for bug hunting',
            'website' => 'https://example.com',
            'twitter' => 'testteam',
            'instagram' => 'testteam_ig',
            'earnings' => 50000.00,
            'elo_rating' => 1200.5
        ];
        
        $createResponse = $this->makeRequest('POST', '/admin/teams', $teamData);
        
        if ($createResponse && isset($createResponse['id'])) {
            $teamId = $createResponse['id'];
            $this->results['team_creation'] = ['status' => 'PASS', 'team_id' => $teamId];
            echo "✅ Team creation successful (ID: $teamId)\n";
            
            // Test team retrieval
            $getResponse = $this->makeRequest('GET', "/teams/$teamId");
            if ($getResponse && $getResponse['name'] === $teamData['name']) {
                $this->results['team_retrieval'] = ['status' => 'PASS'];
                echo "✅ Team retrieval successful\n";
            } else {
                $this->errors[] = "Team retrieval failed";
                $this->results['team_retrieval'] = ['status' => 'FAIL'];
                echo "❌ Team retrieval failed\n";
            }
            
            // Test team update
            $updateData = [
                'name' => 'Updated Test Team',
                'elo_rating' => 1350.0,
                'earnings' => 75000.00
            ];
            
            $updateResponse = $this->makeRequest('PUT', "/admin/teams/$teamId", $updateData);
            if ($updateResponse) {
                $this->results['team_update'] = ['status' => 'PASS'];
                echo "✅ Team update successful\n";
            } else {
                $this->errors[] = "Team update failed";
                $this->results['team_update'] = ['status' => 'FAIL'];
                echo "❌ Team update failed\n";
            }
            
        } else {
            $this->errors[] = "Team creation failed";
            $this->results['team_creation'] = ['status' => 'FAIL'];
            echo "❌ Team creation failed\n";
        }
        echo "\n";
    }
    
    private function testPlayerCRUDOperations()
    {
        echo "👤 Testing Player CRUD Operations...\n";
        
        // Test player creation
        $playerData = [
            'name' => 'TestPlayer',
            'username' => 'test_player_bh',
            'real_name' => 'John Test Player',
            'age' => 22,
            'country' => 'United States',
            'role' => 'Duelist',
            'elo_rating' => 1100.0,
            'earnings' => 25000.00,
            'twitter' => 'testplayer',
            'twitch' => 'testplayer_ttv',
            'biography' => 'A test player for bug hunting'
        ];
        
        $createResponse = $this->makeRequest('POST', '/admin/players', $playerData);
        
        if ($createResponse && isset($createResponse['id'])) {
            $playerId = $createResponse['id'];
            $this->results['player_creation'] = ['status' => 'PASS', 'player_id' => $playerId];
            echo "✅ Player creation successful (ID: $playerId)\n";
            
            // Test player retrieval
            $getResponse = $this->makeRequest('GET', "/players/$playerId");
            if ($getResponse && $getResponse['name'] === $playerData['name']) {
                $this->results['player_retrieval'] = ['status' => 'PASS'];
                echo "✅ Player retrieval successful\n";
            } else {
                $this->errors[] = "Player retrieval failed";
                $this->results['player_retrieval'] = ['status' => 'FAIL'];
                echo "❌ Player retrieval failed\n";
            }
            
            // Test player update
            $updateData = [
                'age' => 23,
                'elo_rating' => 1250.0,
                'earnings' => 35000.00,
                'role' => 'Strategist'
            ];
            
            $updateResponse = $this->makeRequest('PUT', "/admin/players/$playerId", $updateData);
            if ($updateResponse) {
                $this->results['player_update'] = ['status' => 'PASS'];
                echo "✅ Player update successful\n";
            } else {
                $this->errors[] = "Player update failed";
                $this->results['player_update'] = ['status' => 'FAIL'];
                echo "❌ Player update failed\n";
            }
            
        } else {
            $this->errors[] = "Player creation failed";
            $this->results['player_creation'] = ['status' => 'FAIL'];
            echo "❌ Player creation failed\n";
        }
        echo "\n";
    }
    
    private function testImageUploadFunctionality()
    {
        echo "🖼️ Testing Image Upload Functionality...\n";
        
        // Create test images
        $this->createTestImages();
        
        // Test team logo upload
        $this->testTeamLogoUpload();
        
        // Test player avatar upload
        $this->testPlayerAvatarUpload();
        
        echo "\n";
    }
    
    private function createTestImages()
    {
        // Create a simple test image
        $testLogoPath = '/tmp/test_team_logo.png';
        $testAvatarPath = '/tmp/test_player_avatar.png';
        
        // Create simple 100x100 PNG images
        $image = imagecreate(100, 100);
        $blue = imagecolorallocate($image, 0, 100, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        
        // Team logo (blue background)
        imagefill($image, 0, 0, $blue);
        imagestring($image, 5, 30, 40, 'TEAM', $white);
        imagepng($image, $testLogoPath);
        
        // Player avatar (different color)
        $red = imagecolorallocate($image, 200, 50, 50);
        imagefill($image, 0, 0, $red);
        imagestring($image, 5, 25, 40, 'PLAYER', $white);
        imagepng($image, $testAvatarPath);
        
        imagedestroy($image);
        
        $this->results['test_images_created'] = ['status' => 'PASS'];
        echo "✅ Test images created\n";
    }
    
    private function testTeamLogoUpload()
    {
        // Get a test team ID
        $teamsResponse = $this->makeRequest('GET', '/teams');
        if (!$teamsResponse || empty($teamsResponse['data'])) {
            $this->errors[] = "No teams available for logo upload test";
            $this->results['team_logo_upload'] = ['status' => 'FAIL', 'message' => 'No teams available'];
            echo "❌ No teams available for logo upload test\n";
            return;
        }
        
        $teamId = $teamsResponse['data'][0]['id'];
        $logoPath = '/tmp/test_team_logo.png';
        
        // Test image upload
        $uploadResponse = $this->uploadFile("/upload/team/$teamId/logo", $logoPath, 'logo');
        
        if ($uploadResponse && isset($uploadResponse['success']) && $uploadResponse['success']) {
            $this->results['team_logo_upload'] = ['status' => 'PASS'];
            echo "✅ Team logo upload successful\n";
            
            // Verify the logo was set
            $teamResponse = $this->makeRequest('GET', "/teams/$teamId");
            if ($teamResponse && !empty($teamResponse['logo'])) {
                $this->results['team_logo_verification'] = ['status' => 'PASS'];
                echo "✅ Team logo verification successful\n";
            } else {
                $this->errors[] = "Team logo not set after upload";
                $this->results['team_logo_verification'] = ['status' => 'FAIL'];
                echo "❌ Team logo not set after upload\n";
            }
        } else {
            $this->errors[] = "Team logo upload failed";
            $this->results['team_logo_upload'] = ['status' => 'FAIL'];
            echo "❌ Team logo upload failed\n";
        }
    }
    
    private function testPlayerAvatarUpload()
    {
        // Get a test player ID
        $playersResponse = $this->makeRequest('GET', '/players');
        if (!$playersResponse || empty($playersResponse['data'])) {
            $this->errors[] = "No players available for avatar upload test";
            $this->results['player_avatar_upload'] = ['status' => 'FAIL', 'message' => 'No players available'];
            echo "❌ No players available for avatar upload test\n";
            return;
        }
        
        $playerId = $playersResponse['data'][0]['id'];
        $avatarPath = '/tmp/test_player_avatar.png';
        
        // Test image upload
        $uploadResponse = $this->uploadFile("/upload/player/$playerId/avatar", $avatarPath, 'avatar');
        
        if ($uploadResponse && isset($uploadResponse['success']) && $uploadResponse['success']) {
            $this->results['player_avatar_upload'] = ['status' => 'PASS'];
            echo "✅ Player avatar upload successful\n";
            
            // Verify the avatar was set
            $playerResponse = $this->makeRequest('GET', "/players/$playerId");
            if ($playerResponse && !empty($playerResponse['avatar'])) {
                $this->results['player_avatar_verification'] = ['status' => 'PASS'];
                echo "✅ Player avatar verification successful\n";
            } else {
                $this->errors[] = "Player avatar not set after upload";
                $this->results['player_avatar_verification'] = ['status' => 'FAIL'];
                echo "❌ Player avatar not set after upload\n";
            }
        } else {
            $this->errors[] = "Player avatar upload failed";
            $this->results['player_avatar_upload'] = ['status' => 'FAIL'];
            echo "❌ Player avatar upload failed\n";
        }
    }
    
    private function testFieldUpdateOperations()
    {
        echo "🔄 Testing Field Update Operations...\n";
        
        // Get test team and player
        $teamsResponse = $this->makeRequest('GET', '/teams');
        $playersResponse = $this->makeRequest('GET', '/players');
        
        if ($teamsResponse && !empty($teamsResponse['data'])) {
            $teamId = $teamsResponse['data'][0]['id'];
            
            // Test elo rating update
            $eloUpdateData = ['elo_rating' => 1500.75];
            $eloResponse = $this->makeRequest('PUT', "/admin/teams/$teamId", $eloUpdateData);
            
            if ($eloResponse) {
                $this->results['team_elo_update'] = ['status' => 'PASS'];
                echo "✅ Team ELO rating update successful\n";
            } else {
                $this->errors[] = "Team ELO rating update failed";
                $this->results['team_elo_update'] = ['status' => 'FAIL'];
                echo "❌ Team ELO rating update failed\n";
            }
            
            // Test earnings update
            $earningsUpdateData = ['earnings' => 125000.50];
            $earningsResponse = $this->makeRequest('PUT', "/admin/teams/$teamId", $earningsUpdateData);
            
            if ($earningsResponse) {
                $this->results['team_earnings_update'] = ['status' => 'PASS'];
                echo "✅ Team earnings update successful\n";
            } else {
                $this->errors[] = "Team earnings update failed";
                $this->results['team_earnings_update'] = ['status' => 'FAIL'];
                echo "❌ Team earnings update failed\n";
            }
        }
        
        if ($playersResponse && !empty($playersResponse['data'])) {
            $playerId = $playersResponse['data'][0]['id'];
            
            // Test age update
            $ageUpdateData = ['age' => 24];
            $ageResponse = $this->makeRequest('PUT', "/admin/players/$playerId", $ageUpdateData);
            
            if ($ageResponse) {
                $this->results['player_age_update'] = ['status' => 'PASS'];
                echo "✅ Player age update successful\n";
            } else {
                $this->errors[] = "Player age update failed";
                $this->results['player_age_update'] = ['status' => 'FAIL'];
                echo "❌ Player age update failed\n";
            }
            
            // Test country update
            $countryUpdateData = ['country' => 'Canada'];
            $countryResponse = $this->makeRequest('PUT', "/admin/players/$playerId", $countryUpdateData);
            
            if ($countryResponse) {
                $this->results['player_country_update'] = ['status' => 'PASS'];
                echo "✅ Player country update successful\n";
            } else {
                $this->errors[] = "Player country update failed";
                $this->results['player_country_update'] = ['status' => 'FAIL'];
                echo "❌ Player country update failed\n";
            }
        }
        
        echo "\n";
    }
    
    private function testTeamHistoryTracking()
    {
        echo "📊 Testing Team History Tracking...\n";
        
        // Get test players and teams
        $playersResponse = $this->makeRequest('GET', '/players');
        $teamsResponse = $this->makeRequest('GET', '/teams');
        
        if ($playersResponse && !empty($playersResponse['data']) && 
            $teamsResponse && !empty($teamsResponse['data'])) {
            
            $playerId = $playersResponse['data'][0]['id'];
            $teamId = $teamsResponse['data'][0]['id'];
            
            // Test team assignment
            $assignmentData = ['team_id' => $teamId];
            $assignResponse = $this->makeRequest('PUT', "/admin/players/$playerId", $assignmentData);
            
            if ($assignResponse) {
                $this->results['team_assignment'] = ['status' => 'PASS'];
                echo "✅ Player team assignment successful\n";
                
                // Check team history
                $historyResponse = $this->makeRequest('GET', "/players/$playerId/team-history");
                
                if ($historyResponse && is_array($historyResponse)) {
                    $this->results['team_history_tracking'] = ['status' => 'PASS'];
                    echo "✅ Team history tracking working\n";
                } else {
                    $this->errors[] = "Team history tracking failed";
                    $this->results['team_history_tracking'] = ['status' => 'FAIL'];
                    echo "❌ Team history tracking failed\n";
                }
                
            } else {
                $this->errors[] = "Player team assignment failed";
                $this->results['team_assignment'] = ['status' => 'FAIL'];
                echo "❌ Player team assignment failed\n";
            }
        } else {
            $this->errors[] = "No players or teams available for history tracking test";
            $this->results['team_history_tracking'] = ['status' => 'FAIL'];
            echo "❌ No players or teams available for history tracking test\n";
        }
        
        echo "\n";
    }
    
    private function testAchievementSystem()
    {
        echo "🏆 Testing Achievement System...\n";
        
        // Test achievement endpoints
        $achievementsResponse = $this->makeRequest('GET', '/achievements');
        
        if ($achievementsResponse) {
            $this->results['achievements_list'] = ['status' => 'PASS'];
            echo "✅ Achievements list endpoint working\n";
        } else {
            $this->errors[] = "Achievements list endpoint failed";
            $this->results['achievements_list'] = ['status' => 'FAIL'];
            echo "❌ Achievements list endpoint failed\n";
        }
        
        // Test player achievements
        $playersResponse = $this->makeRequest('GET', '/players');
        if ($playersResponse && !empty($playersResponse['data'])) {
            $playerId = $playersResponse['data'][0]['id'];
            
            $playerAchievementsResponse = $this->makeRequest('GET', "/players/$playerId/achievements");
            
            if ($playerAchievementsResponse !== false) {
                $this->results['player_achievements'] = ['status' => 'PASS'];
                echo "✅ Player achievements endpoint working\n";
            } else {
                $this->errors[] = "Player achievements endpoint failed";
                $this->results['player_achievements'] = ['status' => 'FAIL'];
                echo "❌ Player achievements endpoint failed\n";
            }
        }
        
        echo "\n";
    }
    
    private function testSocialMediaLinks()
    {
        echo "📱 Testing Social Media Links...\n";
        
        // Get test team and player
        $teamsResponse = $this->makeRequest('GET', '/teams');
        $playersResponse = $this->makeRequest('GET', '/players');
        
        // Test team social media updates
        if ($teamsResponse && !empty($teamsResponse['data'])) {
            $teamId = $teamsResponse['data'][0]['id'];
            
            $socialData = [
                'twitter' => 'test_team_twitter',
                'instagram' => 'test_team_instagram',
                'youtube' => 'test_team_youtube',
                'twitch' => 'test_team_twitch',
                'discord' => 'https://discord.gg/testteam'
            ];
            
            $socialResponse = $this->makeRequest('PUT', "/admin/teams/$teamId", $socialData);
            
            if ($socialResponse) {
                $this->results['team_social_media'] = ['status' => 'PASS'];
                echo "✅ Team social media update successful\n";
                
                // Verify the social links were saved
                $verifyResponse = $this->makeRequest('GET', "/teams/$teamId");
                if ($verifyResponse && $verifyResponse['twitter'] === $socialData['twitter']) {
                    $this->results['team_social_verification'] = ['status' => 'PASS'];
                    echo "✅ Team social media verification successful\n";
                } else {
                    $this->errors[] = "Team social media not saved correctly";
                    $this->results['team_social_verification'] = ['status' => 'FAIL'];
                    echo "❌ Team social media not saved correctly\n";
                }
            } else {
                $this->errors[] = "Team social media update failed";
                $this->results['team_social_media'] = ['status' => 'FAIL'];
                echo "❌ Team social media update failed\n";
            }
        }
        
        // Test player social media updates
        if ($playersResponse && !empty($playersResponse['data'])) {
            $playerId = $playersResponse['data'][0]['id'];
            
            $socialData = [
                'twitter' => 'test_player_twitter',
                'twitch' => 'test_player_twitch',
                'instagram' => 'test_player_instagram',
                'youtube' => 'test_player_youtube'
            ];
            
            $socialResponse = $this->makeRequest('PUT', "/admin/players/$playerId", $socialData);
            
            if ($socialResponse) {
                $this->results['player_social_media'] = ['status' => 'PASS'];
                echo "✅ Player social media update successful\n";
                
                // Verify the social links were saved
                $verifyResponse = $this->makeRequest('GET', "/players/$playerId");
                if ($verifyResponse && $verifyResponse['twitter'] === $socialData['twitter']) {
                    $this->results['player_social_verification'] = ['status' => 'PASS'];
                    echo "✅ Player social media verification successful\n";
                } else {
                    $this->errors[] = "Player social media not saved correctly";
                    $this->results['player_social_verification'] = ['status' => 'FAIL'];
                    echo "❌ Player social media not saved correctly\n";
                }
            } else {
                $this->errors[] = "Player social media update failed";
                $this->results['player_social_media'] = ['status' => 'FAIL'];
                echo "❌ Player social media update failed\n";
            }
        }
        
        echo "\n";
    }
    
    private function testAPIEndpoints()
    {
        echo "🔌 Testing API Endpoints...\n";
        
        // Test key API endpoints
        $endpoints = [
            '/teams' => 'Teams list',
            '/players' => 'Players list',
            '/search/teams' => 'Teams search',
            '/search/players' => 'Players search'
        ];
        
        foreach ($endpoints as $endpoint => $description) {
            $response = $this->makeRequest('GET', $endpoint);
            
            if ($response !== false) {
                $this->results["endpoint_$endpoint"] = ['status' => 'PASS'];
                echo "✅ $description endpoint working\n";
            } else {
                $this->errors[] = "$description endpoint failed";
                $this->results["endpoint_$endpoint"] = ['status' => 'FAIL'];
                echo "❌ $description endpoint failed\n";
            }
        }
        
        echo "\n";
    }
    
    private function testFrontendIntegration()
    {
        echo "🎨 Testing Frontend Integration...\n";
        
        // Check if frontend components exist
        $frontendPath = '/var/www/mrvl-frontend/frontend/src/components';
        
        $componentChecks = [
            'pages/TeamDetailPage.js' => 'Team Detail Page',
            'pages/PlayerDetailPage.js' => 'Player Detail Page',
            'shared/ImageUpload.js' => 'Image Upload Component'
        ];
        
        foreach ($componentChecks as $file => $description) {
            $fullPath = $frontendPath . '/' . $file;
            
            if (file_exists($fullPath)) {
                $this->results["frontend_$file"] = ['status' => 'PASS'];
                echo "✅ $description exists\n";
            } else {
                $this->errors[] = "$description missing";
                $this->results["frontend_$file"] = ['status' => 'FAIL'];
                echo "❌ $description missing\n";
            }
        }
        
        echo "\n";
    }
    
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $curl = curl_init();
        
        $headers = ['Content-Type: application/json'];
        if ($this->authToken) {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($response === false || $httpCode >= 400) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    private function uploadFile($endpoint, $filePath, $fieldName)
    {
        $url = $this->baseUrl . $endpoint;
        $curl = curl_init();
        
        $headers = [];
        if ($this->authToken) {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }
        
        $postFields = [
            $fieldName => new CURLFile($filePath)
        ];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($response === false || $httpCode >= 400) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    private function generateReport()
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📋 COMPREHENSIVE BUG HUNT REPORT\n";
        echo str_repeat("=", 80) . "\n\n";
        
        $passCount = 0;
        $failCount = 0;
        
        foreach ($this->results as $test => $result) {
            $status = $result['status'] === 'PASS' ? '✅ PASS' : '❌ FAIL';
            echo sprintf("%-50s %s\n", $test, $status);
            
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
        echo "💯 Success Rate: " . round(($passCount / ($passCount + $failCount)) * 100, 1) . "%\n";
        
        if (!empty($this->errors)) {
            echo "\n🐛 BUGS DETECTED:\n";
            foreach ($this->errors as $error) {
                echo "• $error\n";
            }
        }
        
        // Save detailed report
        $reportData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_tests' => $passCount + $failCount,
                'passed' => $passCount,
                'failed' => $failCount,
                'success_rate' => round(($passCount / ($passCount + $failCount)) * 100, 1)
            ],
            'detailed_results' => $this->results,
            'errors' => $this->errors
        ];
        
        $reportFile = 'team_player_profile_bug_hunt_report_' . time() . '.json';
        file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
        
        echo "\n📄 Detailed report saved to: $reportFile\n";
        echo str_repeat("=", 80) . "\n";
    }
}

// Run the comprehensive bug hunt
$bugHunter = new TeamPlayerProfileBugHunter();
$bugHunter->runAllTests();