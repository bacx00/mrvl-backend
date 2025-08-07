<?php

/**
 * API Integration Test Suite for Players and Teams Profile Systems
 * 
 * This script tests all the fixed API endpoints to ensure they work correctly
 */

require_once 'vendor/autoload.php';

// Test configuration
$baseUrl = 'https://staging.mrvl.net/api';
$adminToken = ''; // You need to provide a valid admin JWT token

class APITester
{
    private $baseUrl;
    private $token;
    private $results = [];

    public function __construct($baseUrl, $token)
    {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    private function makeRequest($method, $endpoint, $data = null, $headers = [])
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->token) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->token;
        }

        $headers = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status_code' => $httpCode,
            'body' => $response ? json_decode($response, true) : null,
            'error' => $error,
            'raw_response' => $response
        ];
    }

    private function log($test, $status, $message, $details = null)
    {
        $this->results[] = [
            'test' => $test,
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $color = $status === 'PASS' ? "\033[32m" : ($status === 'FAIL' ? "\033[31m" : "\033[33m");
        echo "{$color}[{$status}]\033[0m {$test}: {$message}\n";
        
        if ($details && $status === 'FAIL') {
            echo "  Details: " . json_encode($details, JSON_PRETTY_PRINT) . "\n";
        }
    }

    public function testPlayerEndpoints()
    {
        echo "\n=== TESTING PLAYER ENDPOINTS ===\n";

        // Test 1: Get all players
        $response = $this->makeRequest('GET', '/public/players');
        if ($response['status_code'] === 200 && $response['body']['success']) {
            $this->log('GET /public/players', 'PASS', 'Successfully retrieved players list');
            $players = $response['body']['data'];
            $testPlayerId = count($players) > 0 ? $players[0]['id'] : null;
        } else {
            $this->log('GET /public/players', 'FAIL', 'Failed to get players', $response);
            return;
        }

        if (!$testPlayerId) {
            $this->log('Player Tests', 'SKIP', 'No players found in database');
            return;
        }

        // Test 2: Get specific player
        $response = $this->makeRequest('GET', "/public/players/{$testPlayerId}");
        if ($response['status_code'] === 200 && $response['body']['success']) {
            $this->log('GET /public/players/{id}', 'PASS', "Successfully retrieved player {$testPlayerId}");
            $playerData = $response['body']['data'];
        } else {
            $this->log('GET /public/players/{id}', 'FAIL', "Failed to get player {$testPlayerId}", $response);
        }

        // Test 3: Get player mentions
        $response = $this->makeRequest('GET', "/players/{$testPlayerId}/mentions");
        if ($response['status_code'] === 200 && $response['body']['success']) {
            $this->log('GET /players/{id}/mentions', 'PASS', "Successfully retrieved mentions for player {$testPlayerId}");
        } else {
            $this->log('GET /players/{id}/mentions', 'FAIL', "Failed to get mentions for player {$testPlayerId}", $response);
        }

        // Test 4: Admin get player (requires auth)
        if ($this->token) {
            $response = $this->makeRequest('GET', "/admin/players/{$testPlayerId}");
            if ($response['status_code'] === 200 && $response['body']['success']) {
                $this->log('GET /admin/players/{id}', 'PASS', "Admin successfully retrieved player {$testPlayerId}");
            } else {
                $this->log('GET /admin/players/{id}', 'FAIL', "Admin failed to get player {$testPlayerId}", $response);
            }

            // Test 5: Update player (requires auth)
            $updateData = [
                'real_name' => 'Test Player Updated',
                'age' => 25,
                'rating' => 1800,
                'social_media' => [
                    'twitter' => 'https://twitter.com/testplayer',
                    'twitch' => 'https://twitch.tv/testplayer',
                    'instagram' => 'https://instagram.com/testplayer'
                ],
                'biography' => 'Updated test biography'
            ];

            $response = $this->makeRequest('PUT', "/admin/players/{$testPlayerId}", $updateData);
            if ($response['status_code'] === 200 && $response['body']['success']) {
                $this->log('PUT /admin/players/{id}', 'PASS', "Successfully updated player {$testPlayerId}");
            } else {
                $this->log('PUT /admin/players/{id}', 'FAIL', "Failed to update player {$testPlayerId}", $response);
            }
        }
    }

    public function testTeamEndpoints()
    {
        echo "\n=== TESTING TEAM ENDPOINTS ===\n";

        // Test 1: Get all teams
        $response = $this->makeRequest('GET', '/public/teams');
        if ($response['status_code'] === 200 && $response['body']['success']) {
            $this->log('GET /public/teams', 'PASS', 'Successfully retrieved teams list');
            $teams = $response['body']['data'];
            $testTeamId = count($teams) > 0 ? $teams[0]['id'] : null;
        } else {
            $this->log('GET /public/teams', 'FAIL', 'Failed to get teams', $response);
            return;
        }

        if (!$testTeamId) {
            $this->log('Team Tests', 'SKIP', 'No teams found in database');
            return;
        }

        // Test 2: Get specific team
        $response = $this->makeRequest('GET', "/public/teams/{$testTeamId}");
        if ($response['status_code'] === 200 && $response['body']['success']) {
            $this->log('GET /public/teams/{id}', 'PASS', "Successfully retrieved team {$testTeamId}");
            $teamData = $response['body']['data'];
        } else {
            $this->log('GET /public/teams/{id}', 'FAIL', "Failed to get team {$testTeamId}", $response);
        }

        // Test 3: Get team mentions
        $response = $this->makeRequest('GET', "/teams/{$testTeamId}/mentions");
        if ($response['status_code'] === 200 && $response['body']['success']) {
            $this->log('GET /teams/{id}/mentions', 'PASS', "Successfully retrieved mentions for team {$testTeamId}");
        } else {
            $this->log('GET /teams/{id}/mentions', 'FAIL', "Failed to get mentions for team {$testTeamId}", $response);
        }

        // Test 4: Admin get team (requires auth)
        if ($this->token) {
            $response = $this->makeRequest('GET', "/admin/teams/{$testTeamId}");
            if ($response['status_code'] === 200 && $response['body']['success']) {
                $this->log('GET /admin/teams/{id}', 'PASS', "Admin successfully retrieved team {$testTeamId}");
            } else {
                $this->log('GET /admin/teams/{id}', 'FAIL', "Admin failed to get team {$testTeamId}", $response);
            }

            // Test 5: Update team (requires auth)
            $updateData = [
                'name' => 'Test Team Updated',
                'description' => 'Updated test team description',
                'earnings' => 50000,
                'coach' => 'Test Coach',
                'coach_picture' => 'https://example.com/coach.jpg',
                'social_media' => [
                    'twitter' => 'https://twitter.com/testteam',
                    'youtube' => 'https://youtube.com/testteam',
                    'instagram' => 'https://instagram.com/testteam',
                    'twitch' => 'https://twitch.tv/testteam'
                ]
            ];

            $response = $this->makeRequest('PUT', "/admin/teams/{$testTeamId}", $updateData);
            if ($response['status_code'] === 200 && $response['body']['success']) {
                $this->log('PUT /admin/teams/{id}', 'PASS', "Successfully updated team {$testTeamId}");
            } else {
                $this->log('PUT /admin/teams/{id}', 'FAIL', "Failed to update team {$testTeamId}", $response);
            }
        }
    }

    public function testMentionEndpoints()
    {
        echo "\n=== TESTING MENTION ENDPOINTS ===\n";

        // Test 1: Search mentions
        $response = $this->makeRequest('GET', '/public/mentions/search?q=test&limit=5');
        if ($response['status_code'] === 200 && $response['body']['success']) {
            $this->log('GET /public/mentions/search', 'PASS', 'Successfully searched mentions');
        } else {
            $this->log('GET /public/mentions/search', 'FAIL', 'Failed to search mentions', $response);
        }

        // Test 2: Get popular mentions
        $response = $this->makeRequest('GET', '/public/mentions/popular?limit=10');
        if ($response['status_code'] === 200 && $response['body']['success']) {
            $this->log('GET /public/mentions/popular', 'PASS', 'Successfully retrieved popular mentions');
        } else {
            $this->log('GET /public/mentions/popular', 'FAIL', 'Failed to get popular mentions', $response);
        }
    }

    public function testSocialLinksIntegration()
    {
        echo "\n=== TESTING SOCIAL LINKS INTEGRATION ===\n";

        // Test various social media platform URLs
        $socialPlatforms = [
            'twitter' => 'https://twitter.com/testuser',
            'instagram' => 'https://instagram.com/testuser',
            'youtube' => 'https://youtube.com/c/testuser',
            'twitch' => 'https://twitch.tv/testuser',
            'tiktok' => 'https://tiktok.com/@testuser',
            'discord' => 'testuser#1234',
            'facebook' => 'https://facebook.com/testuser'
        ];

        foreach ($socialPlatforms as $platform => $url) {
            // We'd need to create a test entity to verify social links work
            $this->log("Social Links - {$platform}", 'INFO', "Would test {$platform}: {$url}");
        }
    }

    public function testErrorHandling()
    {
        echo "\n=== TESTING ERROR HANDLING ===\n";

        // Test 1: Non-existent player
        $response = $this->makeRequest('GET', '/public/players/999999');
        if ($response['status_code'] === 404) {
            $this->log('404 Error Handling', 'PASS', 'Correctly returns 404 for non-existent player');
        } else {
            $this->log('404 Error Handling', 'FAIL', 'Does not return 404 for non-existent player', $response);
        }

        // Test 2: Invalid data validation
        if ($this->token) {
            $invalidData = [
                'age' => 200, // Invalid age
                'rating' => -100, // Invalid rating
                'social_media' => 'not_an_array' // Invalid format
            ];

            $response = $this->makeRequest('PUT', '/admin/players/1', $invalidData);
            if ($response['status_code'] === 422) {
                $this->log('Validation Error Handling', 'PASS', 'Correctly returns 422 for invalid data');
            } else {
                $this->log('Validation Error Handling', 'FAIL', 'Does not properly validate data', $response);
            }
        }
    }

    public function runAllTests()
    {
        echo "Starting API Integration Tests...\n";
        echo "Base URL: {$this->baseUrl}\n";
        echo "Token: " . ($this->token ? "Provided" : "Not provided (skipping auth tests)") . "\n";

        $this->testPlayerEndpoints();
        $this->testTeamEndpoints();
        $this->testMentionEndpoints();
        $this->testSocialLinksIntegration();
        $this->testErrorHandling();

        $this->printResults();
    }

    private function printResults()
    {
        echo "\n=== TEST RESULTS SUMMARY ===\n";
        
        $passed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->results as $result) {
            switch ($result['status']) {
                case 'PASS':
                    $passed++;
                    break;
                case 'FAIL':
                    $failed++;
                    break;
                case 'SKIP':
                case 'INFO':
                    $skipped++;
                    break;
            }
        }

        $total = count($this->results);
        echo "Total Tests: {$total}\n";
        echo "\033[32mPassed: {$passed}\033[0m\n";
        echo "\033[31mFailed: {$failed}\033[0m\n";
        echo "\033[33mSkipped/Info: {$skipped}\033[0m\n";

        if ($failed > 0) {
            echo "\n=== FAILED TESTS ===\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "❌ {$result['test']}: {$result['message']}\n";
                }
            }
        }
    }
}

// Run the tests
if (!$adminToken) {
    echo "⚠️  WARNING: No admin token provided. Some tests will be skipped.\n";
    echo "To run full tests, provide a valid JWT token in the \$adminToken variable.\n\n";
}

$tester = new APITester($baseUrl, $adminToken);
$tester->runAllTests();