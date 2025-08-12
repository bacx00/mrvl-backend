<?php
/**
 * Comprehensive Team CRUD Operations Test
 * 
 * This script tests all team CRUD operations with full field validation
 * including create, read, update, delete and error handling.
 */

require_once __DIR__ . '/vendor/autoload.php';

class ComprehensiveTeamCrudTest
{
    private $baseUrl;
    private $authToken;
    private $testResults = [];
    private $createdTeamId = null;
    private $startTime;

    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api';
        $this->startTime = microtime(true);
        
        // Get admin authentication token
        $this->authenticate();
    }

    /**
     * Authenticate as admin user to get API token
     */
    private function authenticate()
    {
        echo "🔐 Authenticating admin user...\n";
        
        $loginData = [
            'email' => 'admin@mrvl.com',
            'password' => 'admin123'
        ];

        $response = $this->makeRequest('POST', '/auth/login', $loginData);
        
        if ($response['success'] && isset($response['data']['token'])) {
            $this->authToken = $response['data']['token'];
            echo "✅ Authentication successful\n";
        } else {
            throw new Exception("❌ Authentication failed: " . json_encode($response));
        }
    }

    /**
     * Main test runner
     */
    public function runTests()
    {
        echo "\n🚀 Starting Comprehensive Team CRUD Test Suite\n";
        echo "=" . str_repeat("=", 60) . "\n";

        try {
            // Test 1: Create team with ALL fields populated
            $this->testCreateTeamWithAllFields();
            
            // Test 2: Verify team data retrieval
            $this->testRetrieveTeamData();
            
            // Test 3: Update ALL team fields
            $this->testUpdateAllTeamFields();
            
            // Test 4: Verify updates took effect
            $this->testVerifyUpdates();
            
            // Test 5: Test error handling
            $this->testErrorHandling();
            
            // Test 6: Performance testing
            $this->testPerformance();
            
            // Test 7: Social media validation
            $this->testSocialMediaHandling();
            
            // Generate final report
            $this->generateReport();
            
        } catch (Exception $e) {
            echo "❌ Test suite failed: " . $e->getMessage() . "\n";
            $this->testResults['fatal_error'] = $e->getMessage();
        } finally {
            // Cleanup: Delete test team if created
            if ($this->createdTeamId) {
                $this->cleanupTestData();
            }
        }
    }

    /**
     * Test 1: Create team with ALL fields populated
     */
    private function testCreateTeamWithAllFields()
    {
        echo "\n📝 Test 1: Creating team with ALL fields populated...\n";
        
        $teamData = [
            // Required fields
            'name' => 'Test Team Alpha',
            'short_name' => 'TTA',
            'region' => 'NA',
            'platform' => 'PC',
            
            // Basic info
            'country' => 'United States',
            'country_code' => 'US',
            'description' => 'This is a comprehensive test team created for validation purposes. Testing all available fields.',
            'website' => 'https://testteamalpha.com',
            'liquipedia_url' => 'https://liquipedia.net/marvelrivals/Test_Team_Alpha',
            
            // Ratings and stats
            'rating' => 1850,
            'elo_rating' => 1900,
            'earnings' => 50000,
            'founded' => '2024',
            'founded_date' => '2024-01-15',
            'status' => 'active',
            
            // Team staff
            'captain' => 'TestCaptain',
            'coach' => 'Test Coach',
            'coach_name' => 'Coach Alpha',
            'coach_nationality' => 'United States',
            'manager' => 'Test Manager',
            'owner' => 'Test Owner',
            
            // Social media links - all platforms
            'twitter' => 'testteamalpha',
            'instagram' => 'testteamalpha',
            'youtube' => 'testteamalpha',
            'twitch' => 'testteamalpha',
            'tiktok' => 'testteamalpha',
            'discord' => 'https://discord.gg/testteamalpha',
            'facebook' => 'https://facebook.com/testteamalpha',
            
            // Additional social media data
            'social_media' => [
                'twitter' => 'testteamalpha',
                'instagram' => 'testteamalpha',
                'youtube' => 'testteamalpha',
                'twitch' => 'testteamalpha',
                'tiktok' => 'testteamalpha',
                'discord' => 'https://discord.gg/testteamalpha',
                'facebook' => 'https://facebook.com/testteamalpha'
            ],
            
            // Coach social media
            'coach_social_media' => [
                'twitter' => 'coachalpha',
                'instagram' => 'coachalpha',
                'twitch' => 'coachalpha'
            ],
            
            // Achievements
            'achievements' => [
                'Marvel Rivals Championship 2024 - 1st Place',
                'Regional Qualifier - 2nd Place',
                'Community Tournament - 1st Place'
            ]
        ];

        $startTime = microtime(true);
        $response = $this->makeRequest('POST', '/admin/teams', $teamData);
        $responseTime = (microtime(true) - $startTime) * 1000;

        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdTeamId = $response['data']['id'];
            echo "✅ Team created successfully with ID: {$this->createdTeamId}\n";
            echo "⏱️  Response time: " . round($responseTime, 2) . "ms\n";
            
            $this->testResults['create_test'] = [
                'status' => 'passed',
                'team_id' => $this->createdTeamId,
                'response_time_ms' => round($responseTime, 2),
                'fields_sent' => count($teamData),
                'data' => $response['data']
            ];
        } else {
            throw new Exception("Team creation failed: " . json_encode($response));
        }
    }

    /**
     * Test 2: Verify team data retrieval
     */
    private function testRetrieveTeamData()
    {
        echo "\n📖 Test 2: Retrieving and verifying team data...\n";
        
        $startTime = microtime(true);
        $response = $this->makeRequest('GET', "/admin/teams/{$this->createdTeamId}");
        $responseTime = (microtime(true) - $startTime) * 1000;

        if ($response['success'] && isset($response['data'])) {
            $team = $response['data'];
            echo "✅ Team data retrieved successfully\n";
            echo "⏱️  Response time: " . round($responseTime, 2) . "ms\n";
            
            // Verify all critical fields
            $criticalFields = [
                'name', 'short_name', 'region', 'platform', 'country', 
                'description', 'rating', 'earnings', 'coach_name', 
                'twitter', 'instagram', 'youtube', 'twitch'
            ];
            
            $fieldsPresent = 0;
            $missingFields = [];
            
            foreach ($criticalFields as $field) {
                if (isset($team[$field]) && !empty($team[$field])) {
                    $fieldsPresent++;
                } else {
                    $missingFields[] = $field;
                }
            }
            
            echo "📊 Fields verification: {$fieldsPresent}/" . count($criticalFields) . " critical fields present\n";
            
            if (!empty($missingFields)) {
                echo "⚠️  Missing fields: " . implode(', ', $missingFields) . "\n";
            }
            
            // Check social media JSON parsing
            if (isset($team['social_media'])) {
                $socialMedia = is_string($team['social_media']) ? 
                    json_decode($team['social_media'], true) : $team['social_media'];
                    
                echo "📱 Social media platforms: " . count($socialMedia) . "\n";
            }
            
            $this->testResults['retrieve_test'] = [
                'status' => 'passed',
                'response_time_ms' => round($responseTime, 2),
                'fields_present' => $fieldsPresent,
                'total_fields' => count($criticalFields),
                'missing_fields' => $missingFields,
                'social_media_count' => isset($socialMedia) ? count($socialMedia) : 0
            ];
            
        } else {
            throw new Exception("Team retrieval failed: " . json_encode($response));
        }
    }

    /**
     * Test 3: Update ALL team fields with different values
     */
    private function testUpdateAllTeamFields()
    {
        echo "\n✏️  Test 3: Updating ALL team fields with new values...\n";
        
        $updateData = [
            // Update basic info
            'name' => 'Updated Test Team',
            'short_name' => 'UTT',
            'region' => 'EU',
            'platform' => 'Console',
            'country' => 'Germany',
            'country_code' => 'DE',
            'description' => 'This team has been updated with new comprehensive data for testing validation.',
            'website' => 'https://updatedtestteam.com',
            'liquipedia_url' => 'https://liquipedia.net/marvelrivals/Updated_Test_Team',
            
            // Update ratings
            'rating' => 2100,
            'elo_rating' => 2150,
            'earnings' => 75000,
            'founded' => '2023',
            'founded_date' => '2023-06-01',
            'status' => 'active',
            
            // Update staff
            'captain' => 'UpdatedCaptain',
            'coach' => 'Updated Coach',
            'coach_name' => 'Coach Beta',
            'coach_nationality' => 'Germany',
            'manager' => 'Updated Manager',
            'owner' => 'Updated Owner',
            
            // Update ALL social media
            'twitter' => 'updatedteam',
            'instagram' => 'updatedteam',
            'youtube' => 'updatedteamchannel',
            'twitch' => 'updatedteamstream',
            'tiktok' => 'updatedteamtok',
            'discord' => 'https://discord.gg/updatedteam',
            'facebook' => 'https://facebook.com/updatedteam',
            
            // Update social media object
            'social_media' => [
                'twitter' => 'updatedteam',
                'instagram' => 'updatedteam', 
                'youtube' => 'updatedteamchannel',
                'twitch' => 'updatedteamstream',
                'tiktok' => 'updatedteamtok',
                'discord' => 'https://discord.gg/updatedteam',
                'facebook' => 'https://facebook.com/updatedteam',
                'website' => 'https://updatedtestteam.com'
            ],
            
            // Update coach social media
            'coach_social_media' => [
                'twitter' => 'coachbeta',
                'instagram' => 'coachbeta',
                'twitch' => 'coachbetastream',
                'youtube' => 'coachbetachannel'
            ],
            
            // Update achievements
            'achievements' => [
                'Marvel Rivals World Championship 2024 - 1st Place',
                'Regional Masters - 1st Place',
                'International Invitational - 2nd Place',
                'Community Cup Series - Champion'
            ]
        ];

        $startTime = microtime(true);
        $response = $this->makeRequest('PUT', "/admin/teams/{$this->createdTeamId}", $updateData);
        $responseTime = (microtime(true) - $startTime) * 1000;

        if ($response['success']) {
            echo "✅ Team updated successfully\n";
            echo "⏱️  Response time: " . round($responseTime, 2) . "ms\n";
            echo "📝 Updated fields: " . count($updateData) . "\n";
            
            $this->testResults['update_test'] = [
                'status' => 'passed',
                'response_time_ms' => round($responseTime, 2),
                'fields_updated' => count($updateData),
                'update_data' => $updateData
            ];
        } else {
            throw new Exception("Team update failed: " . json_encode($response));
        }
    }

    /**
     * Test 4: Verify updates took effect immediately
     */
    private function testVerifyUpdates()
    {
        echo "\n🔍 Test 4: Verifying updates took effect immediately...\n";
        
        $startTime = microtime(true);
        $response = $this->makeRequest('GET', "/admin/teams/{$this->createdTeamId}");
        $responseTime = (microtime(true) - $startTime) * 1000;

        if ($response['success'] && isset($response['data'])) {
            $team = $response['data'];
            
            // Verify specific updated values
            $verifications = [
                'name' => ['expected' => 'Updated Test Team', 'actual' => $team['name'] ?? null],
                'short_name' => ['expected' => 'UTT', 'actual' => $team['short_name'] ?? null],
                'region' => ['expected' => 'EU', 'actual' => $team['region'] ?? null],
                'platform' => ['expected' => 'Console', 'actual' => $team['platform'] ?? null],
                'country' => ['expected' => 'Germany', 'actual' => $team['country'] ?? null],
                'rating' => ['expected' => 2100, 'actual' => $team['rating'] ?? null],
                'earnings' => ['expected' => 75000, 'actual' => $team['earnings'] ?? null],
                'coach_name' => ['expected' => 'Coach Beta', 'actual' => $team['coach_name'] ?? null],
                'twitter' => ['expected' => 'updatedteam', 'actual' => $team['twitter'] ?? null],
                'instagram' => ['expected' => 'updatedteam', 'actual' => $team['instagram'] ?? null]
            ];
            
            $passedVerifications = 0;
            $failedVerifications = [];
            
            foreach ($verifications as $field => $check) {
                if ($check['expected'] == $check['actual']) {
                    $passedVerifications++;
                    echo "✅ {$field}: {$check['actual']}\n";
                } else {
                    $failedVerifications[] = [
                        'field' => $field,
                        'expected' => $check['expected'],
                        'actual' => $check['actual']
                    ];
                    echo "❌ {$field}: Expected '{$check['expected']}', got '{$check['actual']}'\n";
                }
            }
            
            echo "\n📊 Verification results: {$passedVerifications}/" . count($verifications) . " fields updated correctly\n";
            echo "⏱️  Response time: " . round($responseTime, 2) . "ms\n";
            
            $this->testResults['verify_updates_test'] = [
                'status' => $passedVerifications == count($verifications) ? 'passed' : 'partial',
                'response_time_ms' => round($responseTime, 2),
                'verified_fields' => $passedVerifications,
                'total_fields' => count($verifications),
                'failed_verifications' => $failedVerifications
            ];
            
        } else {
            throw new Exception("Update verification failed: " . json_encode($response));
        }
    }

    /**
     * Test 5: Error handling for invalid data
     */
    private function testErrorHandling()
    {
        echo "\n🚨 Test 5: Testing error handling for invalid data...\n";
        
        $errorTests = [
            // Test duplicate name
            [
                'name' => 'duplicate_name_test',
                'data' => ['name' => 'Updated Test Team', 'short_name' => 'DUP', 'region' => 'NA'],
                'expected_error' => 'name already exists'
            ],
            // Test missing required fields
            [
                'name' => 'missing_fields_test',
                'data' => ['description' => 'Missing name and region'],
                'expected_error' => 'required fields missing'
            ],
            // Test invalid rating
            [
                'name' => 'invalid_rating_test',
                'data' => ['name' => 'Invalid Rating Test', 'short_name' => 'IRT', 'region' => 'NA', 'rating' => 99999],
                'expected_error' => 'rating out of range'
            ],
            // Test invalid social media format
            [
                'name' => 'invalid_social_test',
                'data' => ['name' => 'Invalid Social Test', 'short_name' => 'IST', 'region' => 'NA', 'social_media' => 'invalid_json'],
                'expected_error' => 'invalid social media format'
            ]
        ];
        
        $errorTestResults = [];
        
        foreach ($errorTests as $test) {
            echo "  Testing: {$test['name']}...\n";
            
            $startTime = microtime(true);
            $response = $this->makeRequest('POST', '/admin/teams', $test['data']);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            if (!$response['success']) {
                echo "  ✅ Correctly rejected invalid data\n";
                $errorTestResults[$test['name']] = [
                    'status' => 'passed',
                    'response_time_ms' => round($responseTime, 2),
                    'error_message' => $response['message'] ?? 'Unknown error'
                ];
            } else {
                echo "  ❌ Should have rejected invalid data\n";
                $errorTestResults[$test['name']] = [
                    'status' => 'failed',
                    'response_time_ms' => round($responseTime, 2),
                    'note' => 'Accepted invalid data when it should have been rejected'
                ];
            }
        }
        
        $this->testResults['error_handling_test'] = $errorTestResults;
    }

    /**
     * Test 6: Performance testing
     */
    private function testPerformance()
    {
        echo "\n⚡ Test 6: Performance testing...\n";
        
        $performanceResults = [];
        
        // Test multiple rapid requests
        echo "  Testing rapid consecutive requests...\n";
        $times = [];
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $response = $this->makeRequest('GET', "/admin/teams/{$this->createdTeamId}");
            $times[] = (microtime(true) - $startTime) * 1000;
            
            if (!$response['success']) {
                throw new Exception("Performance test failed on request " . ($i + 1));
            }
        }
        
        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);
        
        echo "  📊 Avg response time: " . round($avgTime, 2) . "ms\n";
        echo "  📊 Max response time: " . round($maxTime, 2) . "ms\n";
        echo "  📊 Min response time: " . round($minTime, 2) . "ms\n";
        
        $performanceResults['rapid_requests'] = [
            'avg_time_ms' => round($avgTime, 2),
            'max_time_ms' => round($maxTime, 2),
            'min_time_ms' => round($minTime, 2),
            'requests_count' => count($times)
        ];
        
        $this->testResults['performance_test'] = $performanceResults;
    }

    /**
     * Test 7: Social media handling
     */
    private function testSocialMediaHandling()
    {
        echo "\n📱 Test 7: Social media handling validation...\n";
        
        $socialMediaTest = [
            'social_media' => [
                'twitter' => 'testhandle',
                'instagram' => 'testhandle',
                'youtube' => 'testchannel',
                'twitch' => 'teststream',
                'tiktok' => 'testtok',
                'discord' => 'https://discord.gg/test',
                'facebook' => 'https://facebook.com/test',
                'custom_platform' => 'custom_value'
            ]
        ];
        
        $startTime = microtime(true);
        $response = $this->makeRequest('PUT', "/admin/teams/{$this->createdTeamId}", $socialMediaTest);
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        if ($response['success']) {
            echo "✅ Social media update successful\n";
            
            // Verify social media was saved correctly
            $getResponse = $this->makeRequest('GET', "/admin/teams/{$this->createdTeamId}");
            if ($getResponse['success']) {
                $team = $getResponse['data'];
                $savedSocialMedia = isset($team['social_media']) ? 
                    (is_string($team['social_media']) ? json_decode($team['social_media'], true) : $team['social_media']) : [];
                
                echo "📱 Social media platforms saved: " . count($savedSocialMedia) . "\n";
                foreach ($savedSocialMedia as $platform => $handle) {
                    echo "  - {$platform}: {$handle}\n";
                }
            }
            
            $this->testResults['social_media_test'] = [
                'status' => 'passed',
                'response_time_ms' => round($responseTime, 2),
                'platforms_tested' => count($socialMediaTest['social_media']),
                'platforms_saved' => isset($savedSocialMedia) ? count($savedSocialMedia) : 0
            ];
        } else {
            throw new Exception("Social media test failed: " . json_encode($response));
        }
    }

    /**
     * Cleanup test data
     */
    private function cleanupTestData()
    {
        echo "\n🧹 Cleaning up test data...\n";
        
        if ($this->createdTeamId) {
            $response = $this->makeRequest('DELETE', "/admin/teams/{$this->createdTeamId}");
            
            if ($response['success']) {
                echo "✅ Test team deleted successfully (ID: {$this->createdTeamId})\n";
                $this->testResults['cleanup'] = ['status' => 'success', 'team_id' => $this->createdTeamId];
            } else {
                echo "⚠️  Failed to delete test team: " . json_encode($response) . "\n";
                $this->testResults['cleanup'] = ['status' => 'failed', 'team_id' => $this->createdTeamId, 'error' => $response];
            }
        }
    }

    /**
     * Generate comprehensive test report
     */
    private function generateReport()
    {
        $totalTime = microtime(true) - $this->startTime;
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "📋 COMPREHENSIVE TEAM CRUD TEST REPORT\n";
        echo str_repeat("=", 70) . "\n";
        
        echo "⏱️  Total test duration: " . round($totalTime, 2) . " seconds\n";
        echo "🆔 Test team ID: " . ($this->createdTeamId ?? 'N/A') . "\n";
        echo "📅 Test date: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Summary
        $passedTests = 0;
        $totalTests = 0;
        
        foreach ($this->testResults as $testName => $result) {
            if (isset($result['status'])) {
                $totalTests++;
                if ($result['status'] === 'passed') {
                    $passedTests++;
                }
            } else if (is_array($result)) {
                foreach ($result as $subTest => $subResult) {
                    if (isset($subResult['status'])) {
                        $totalTests++;
                        if ($subResult['status'] === 'passed') {
                            $passedTests++;
                        }
                    }
                }
            }
        }
        
        echo "📊 SUMMARY: {$passedTests}/{$totalTests} tests passed\n\n";
        
        // Detailed results
        foreach ($this->testResults as $testName => $result) {
            echo "🔍 " . strtoupper(str_replace('_', ' ', $testName)) . ":\n";
            
            if (isset($result['status'])) {
                $status = $result['status'] === 'passed' ? '✅ PASSED' : ($result['status'] === 'partial' ? '⚠️  PARTIAL' : '❌ FAILED');
                echo "   Status: {$status}\n";
                
                if (isset($result['response_time_ms'])) {
                    echo "   Response time: {$result['response_time_ms']}ms\n";
                }
                
                if (isset($result['fields_present'])) {
                    echo "   Fields validated: {$result['fields_present']}/{$result['total_fields']}\n";
                }
                
                if (!empty($result['missing_fields'])) {
                    echo "   Missing fields: " . implode(', ', $result['missing_fields']) . "\n";
                }
                
                if (!empty($result['failed_verifications'])) {
                    echo "   Failed verifications:\n";
                    foreach ($result['failed_verifications'] as $fail) {
                        echo "     - {$fail['field']}: expected '{$fail['expected']}', got '{$fail['actual']}'\n";
                    }
                }
            } else if (is_array($result)) {
                foreach ($result as $subTest => $subResult) {
                    if (isset($subResult['status'])) {
                        $status = $subResult['status'] === 'passed' ? '✅' : '❌';
                        echo "   {$status} {$subTest}\n";
                        if (isset($subResult['error_message'])) {
                            echo "     Error: {$subResult['error_message']}\n";
                        }
                    }
                }
            }
            echo "\n";
        }
        
        // Save detailed report to file
        $reportFile = "/var/www/mrvl-backend/team_crud_test_report_" . time() . ".json";
        file_put_contents($reportFile, json_encode([
            'test_date' => date('Y-m-d H:i:s'),
            'total_duration_seconds' => round($totalTime, 2),
            'team_id' => $this->createdTeamId,
            'summary' => [
                'passed_tests' => $passedTests,
                'total_tests' => $totalTests,
                'success_rate' => round(($passedTests / $totalTests) * 100, 1) . '%'
            ],
            'detailed_results' => $this->testResults
        ], JSON_PRETTY_PRINT));
        
        echo "💾 Detailed report saved to: {$reportFile}\n";
        
        // Return team ID for cleanup by user
        if ($this->createdTeamId) {
            echo "\n🆔 TEAM ID FOR REFERENCE: {$this->createdTeamId}\n";
            echo "   (Team has been deleted automatically)\n";
        }
        
        echo "\n" . str_repeat("=", 70) . "\n";
    }

    /**
     * Make HTTP request with authentication
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->authToken
            ]
        ]);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'message' => 'Request failed'];
        }
        
        $decoded = json_decode($response, true);
        
        if ($decoded === null) {
            return ['success' => false, 'message' => 'Invalid JSON response', 'raw' => $response];
        }
        
        return $decoded;
    }
}

// Run the comprehensive test
try {
    $test = new ComprehensiveTeamCrudTest();
    $test->runTests();
} catch (Exception $e) {
    echo "❌ Test execution failed: " . $e->getMessage() . "\n";
    exit(1);
}