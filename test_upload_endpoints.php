<?php
/**
 * Test image upload endpoints with real HTTP requests
 */

echo "🌐 Testing Image Upload Endpoints with HTTP Requests\n\n";

// Configuration
$baseUrl = 'http://localhost:8000/api';
$testImageDir = 'storage/app/public/test-images';

class UploadEndpointTester 
{
    private $baseUrl;
    private $token;
    private $testResults = [];
    
    public function __construct($baseUrl) 
    {
        $this->baseUrl = $baseUrl;
        echo "🔧 Initializing Upload Endpoint Tester\n";
        echo "Base URL: {$this->baseUrl}\n\n";
        
        // Try to get authentication token
        $this->token = $this->getAuthToken();
    }
    
    private function getAuthToken()
    {
        echo "🔐 Attempting to get authentication token...\n";
        
        // Try to login with common admin credentials
        $credentials = [
            ['email' => 'admin@admin.com', 'password' => 'password'],
            ['email' => 'admin@example.com', 'password' => 'admin123'],
            ['email' => 'test@example.com', 'password' => 'password']
        ];
        
        foreach ($credentials as $cred) {
            $response = $this->makeRequest('POST', '/login', $cred);
            if ($response && isset($response['data']['token'])) {
                echo "✅ Authentication successful with {$cred['email']}\n";
                return $response['data']['token'];
            } elseif ($response && isset($response['token'])) {
                echo "✅ Authentication successful with {$cred['email']}\n";
                return $response['token'];
            }
        }
        
        echo "⚠️ No valid authentication found, testing without auth\n";
        return null;
    }
    
    public function testAllEndpoints()
    {
        echo "🚀 Starting endpoint tests...\n\n";
        
        // Test team logo upload
        $this->testTeamLogoUpload();
        
        // Test player avatar upload  
        $this->testPlayerAvatarUpload();
        
        // Test various file formats
        $this->testFileFormats();
        
        // Test file size limits
        $this->testFileSizeLimits();
        
        // Print results
        $this->printResults();
    }
    
    private function testTeamLogoUpload()
    {
        echo "🏀 Testing Team Logo Upload...\n";
        
        try {
            // First create a test team
            $teamData = [
                'name' => 'Test Upload Team',
                'short_name' => 'TUT', 
                'region' => 'NA'
            ];
            
            $teamResponse = $this->makeRequest('POST', '/admin/teams', $teamData);
            
            if (!$teamResponse || !isset($teamResponse['data']['id'])) {
                echo "❌ Failed to create test team for logo upload\n";
                $this->testResults['team_logo_upload'] = false;
                return;
            }
            
            $teamId = $teamResponse['data']['id'];
            echo "✅ Created test team with ID: {$teamId}\n";
            
            // Test logo upload
            $logoFile = 'storage/app/public/test-images/test-logo.png';
            if (!file_exists($logoFile)) {
                echo "❌ Test logo file not found: {$logoFile}\n";
                $this->testResults['team_logo_upload'] = false;
                return;
            }
            
            $uploadResponse = $this->uploadFile("/upload/team/{$teamId}/logo", $logoFile, 'logo');
            
            if ($uploadResponse && isset($uploadResponse['success'])) {
                echo "✅ Team logo upload successful\n";
                echo "   Response: " . json_encode($uploadResponse, JSON_PRETTY_PRINT) . "\n";
                $this->testResults['team_logo_upload'] = true;
            } else {
                echo "❌ Team logo upload failed\n";
                if ($uploadResponse) {
                    echo "   Response: " . json_encode($uploadResponse, JSON_PRETTY_PRINT) . "\n";
                }
                $this->testResults['team_logo_upload'] = false;
            }
            
            // Cleanup
            $this->makeRequest('DELETE', "/admin/teams/{$teamId}");
            
        } catch (Exception $e) {
            echo "❌ Team logo upload test failed: " . $e->getMessage() . "\n";
            $this->testResults['team_logo_upload'] = false;
        }
        
        echo "\n";
    }
    
    private function testPlayerAvatarUpload()
    {
        echo "👤 Testing Player Avatar Upload...\n";
        
        try {
            // First create a test player
            $playerData = [
                'username' => 'testuploadplayer',
                'real_name' => 'Test Upload Player',
                'role' => 'Duelist',
                'region' => 'NA'
            ];
            
            $playerResponse = $this->makeRequest('POST', '/admin/players', $playerData);
            
            if (!$playerResponse || !isset($playerResponse['data']['id'])) {
                echo "❌ Failed to create test player for avatar upload\n";
                $this->testResults['player_avatar_upload'] = false;
                return;
            }
            
            $playerId = $playerResponse['data']['id'];
            echo "✅ Created test player with ID: {$playerId}\n";
            
            // Test avatar upload
            $avatarFile = 'storage/app/public/test-images/test-avatar.png';
            if (!file_exists($avatarFile)) {
                echo "❌ Test avatar file not found: {$avatarFile}\n";
                $this->testResults['player_avatar_upload'] = false;
                return;
            }
            
            $uploadResponse = $this->uploadFile("/upload/player/{$playerId}/avatar", $avatarFile, 'avatar');
            
            if ($uploadResponse && isset($uploadResponse['success'])) {
                echo "✅ Player avatar upload successful\n";
                echo "   Response: " . json_encode($uploadResponse, JSON_PRETTY_PRINT) . "\n";
                $this->testResults['player_avatar_upload'] = true;
            } else {
                echo "❌ Player avatar upload failed\n";
                if ($uploadResponse) {
                    echo "   Response: " . json_encode($uploadResponse, JSON_PRETTY_PRINT) . "\n";
                }
                $this->testResults['player_avatar_upload'] = false;
            }
            
            // Cleanup
            $this->makeRequest('DELETE', "/admin/players/{$playerId}");
            
        } catch (Exception $e) {
            echo "❌ Player avatar upload test failed: " . $e->getMessage() . "\n";
            $this->testResults['player_avatar_upload'] = false;
        }
        
        echo "\n";
    }
    
    private function testFileFormats()
    {
        echo "🎨 Testing Different File Formats...\n";
        
        $formats = [
            'png' => 'storage/app/public/test-images/test-logo.png',
            'jpg' => 'storage/app/public/test-images/test-logo.jpg',
            'svg' => 'storage/app/public/test-images/test-logo.svg'
        ];
        
        $formatResults = [];
        
        foreach ($formats as $format => $file) {
            if (!file_exists($file)) {
                echo "⚠️ Test file not found for {$format}: {$file}\n";
                $formatResults[$format] = false;
                continue;
            }
            
            echo "Testing {$format} format...\n";
            
            // Create test team for each format
            $teamResponse = $this->makeRequest('POST', '/admin/teams', [
                'name' => "Test {$format} Team",
                'short_name' => strtoupper($format),
                'region' => 'NA'
            ]);
            
            if ($teamResponse && isset($teamResponse['data']['id'])) {
                $teamId = $teamResponse['data']['id'];
                $uploadResponse = $this->uploadFile("/upload/team/{$teamId}/logo", $file, 'logo');
                
                if ($uploadResponse && isset($uploadResponse['success'])) {
                    echo "✅ {$format} format upload: SUCCESS\n";
                    $formatResults[$format] = true;
                } else {
                    echo "❌ {$format} format upload: FAILED\n";
                    $formatResults[$format] = false;
                }
                
                // Cleanup
                $this->makeRequest('DELETE', "/admin/teams/{$teamId}");
            } else {
                echo "❌ Could not create test team for {$format}\n";
                $formatResults[$format] = false;
            }
        }
        
        $this->testResults['file_formats'] = $formatResults;
        echo "\n";
    }
    
    private function testFileSizeLimits()
    {
        echo "📏 Testing File Size Limits...\n";
        
        $largeFile = 'storage/app/public/test-images/test-large.png';
        if (!file_exists($largeFile)) {
            echo "⚠️ Large test file not found: {$largeFile}\n";
            $this->testResults['file_size_limits'] = false;
            return;
        }
        
        $fileSize = filesize($largeFile);
        echo "Testing with file size: " . round($fileSize / 1024, 2) . " KB\n";
        
        // Create test team
        $teamResponse = $this->makeRequest('POST', '/admin/teams', [
            'name' => 'Test Large File Team',
            'short_name' => 'TLF',
            'region' => 'NA'
        ]);
        
        if ($teamResponse && isset($teamResponse['data']['id'])) {
            $teamId = $teamResponse['data']['id'];
            $uploadResponse = $this->uploadFile("/upload/team/{$teamId}/logo", $largeFile, 'logo');
            
            if ($uploadResponse && isset($uploadResponse['success'])) {
                echo "✅ Large file upload: ACCEPTED\n";
                $this->testResults['file_size_limits'] = true;
            } else {
                echo "❌ Large file upload: REJECTED (this might be expected if file is too large)\n";
                if ($uploadResponse && isset($uploadResponse['message'])) {
                    echo "   Message: {$uploadResponse['message']}\n";
                }
                $this->testResults['file_size_limits'] = 'rejected';
            }
            
            // Cleanup
            $this->makeRequest('DELETE', "/admin/teams/{$teamId}");
        } else {
            echo "❌ Could not create test team for large file test\n";
            $this->testResults['file_size_limits'] = false;
        }
        
        echo "\n";
    }
    
    private function uploadFile($endpoint, $filePath, $fieldName)
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Prepare multipart form data
        $postFields = [
            $fieldName => new CURLFile($filePath)
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        
        $headers = [];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "cURL Error: {$error}\n";
            return false;
        }
        
        echo "HTTP Response Code: {$httpCode}\n";
        
        if ($response === false || $httpCode >= 500) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $headers = ['Content-Type: application/json'];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode >= 500) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    private function printResults()
    {
        echo str_repeat("=", 60) . "\n";
        echo "📊 UPLOAD ENDPOINT TEST RESULTS\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($this->testResults as $test => $result) {
            if (is_array($result)) {
                // Handle format tests
                foreach ($result as $format => $formatResult) {
                    $totalTests++;
                    if ($formatResult) $passedTests++;
                    $status = $formatResult ? "✅ PASSED" : "❌ FAILED";
                    echo "• {$format} format upload: {$status}\n";
                }
            } else {
                $totalTests++;
                if ($result === true) $passedTests++;
                $status = $result === true ? "✅ PASSED" : ($result === 'rejected' ? "⚠️ REJECTED" : "❌ FAILED");
                echo "• " . ucwords(str_replace('_', ' ', $test)) . ": {$status}\n";
            }
        }
        
        echo "\nOverall Results:\n";
        echo "✅ Passed: {$passedTests}/{$totalTests}\n";
        echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";
        
        if ($passedTests === $totalTests) {
            echo "🎉 EXCELLENT! All upload endpoint tests passed!\n";
        } elseif ($passedTests >= $totalTests * 0.8) {
            echo "✅ GOOD! Most upload endpoint tests passed.\n";
        } else {
            echo "⚠️ ATTENTION! Several upload endpoint tests failed.\n";
        }
    }
}

// Run the tests
try {
    $tester = new UploadEndpointTester($baseUrl);
    $tester->testAllEndpoints();
} catch (Exception $e) {
    echo "❌ Critical error: " . $e->getMessage() . "\n";
}