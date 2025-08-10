<?php

/**
 * API Integration Test Script
 * Tests the fixed API endpoints for forums and news systems
 */

require_once 'vendor/autoload.php';

class APIIntegrationTester
{
    private $baseUrl;
    private $testToken;
    private $testResults = [];

    public function __construct($baseUrl = 'http://127.0.0.1:8000')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Run all API tests
     */
    public function runAllTests()
    {
        echo "🚀 Starting API Integration Tests for Forums and News Systems\n";
        echo "=================================================================\n\n";

        // Test CORS and basic connectivity
        $this->testCORS();
        
        // Test authentication
        $this->testAuthentication();
        
        // Test news comment posting
        $this->testNewsCommentPosting();
        
        // Test forum post creation
        $this->testForumPostCreation();
        
        // Test response formats
        $this->testResponseFormats();
        
        // Generate report
        $this->generateReport();
    }

    /**
     * Test CORS configuration
     */
    private function testCORS()
    {
        echo "📡 Testing CORS Configuration...\n";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/public/news',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_HTTPHEADER => [
                'Origin: http://localhost:3000',
                'Access-Control-Request-Method: POST',
                'Access-Control-Request-Headers: Content-Type, Authorization'
            ],
            CURLOPT_CUSTOMREQUEST => 'OPTIONS'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->testResults['cors'] = [
            'status' => $httpCode === 200 ? 'PASS' : 'FAIL',
            'http_code' => $httpCode,
            'cors_headers_present' => strpos($response, 'Access-Control-Allow-Origin') !== false
        ];

        echo $this->testResults['cors']['status'] === 'PASS' ? "✅" : "❌";
        echo " CORS Test: {$this->testResults['cors']['status']}\n\n";
    }

    /**
     * Test authentication flow
     */
    private function testAuthentication()
    {
        echo "🔐 Testing Authentication Flow...\n";
        
        // Try to access protected endpoint without token
        $response = $this->makeRequest('/api/user', 'GET');
        
        $this->testResults['auth_without_token'] = [
            'status' => $response['http_code'] === 401 ? 'PASS' : 'FAIL',
            'http_code' => $response['http_code'],
            'response_structure' => $this->validateResponseStructure($response['body'])
        ];

        echo $this->testResults['auth_without_token']['status'] === 'PASS' ? "✅" : "❌";
        echo " Unauthorized Access Test: {$this->testResults['auth_without_token']['status']}\n";

        // Test with invalid token
        $response = $this->makeRequest('/api/user', 'GET', null, ['Authorization: Bearer invalid_token_123']);
        
        $this->testResults['auth_invalid_token'] = [
            'status' => $response['http_code'] === 401 ? 'PASS' : 'FAIL',
            'http_code' => $response['http_code'],
            'response_structure' => $this->validateResponseStructure($response['body'])
        ];

        echo $this->testResults['auth_invalid_token']['status'] === 'PASS' ? "✅" : "❌";
        echo " Invalid Token Test: {$this->testResults['auth_invalid_token']['status']}\n\n";
    }

    /**
     * Test news comment posting response format
     */
    private function testNewsCommentPosting()
    {
        echo "📰 Testing News Comment API Response Format...\n";
        
        // Test posting comment without authentication
        $response = $this->makeRequest('/api/news/1/comments', 'POST', [
            'content' => 'Test comment for API integration testing'
        ]);

        $this->testResults['news_comment_unauth'] = [
            'status' => $response['http_code'] === 401 ? 'PASS' : 'FAIL',
            'http_code' => $response['http_code'],
            'response_structure' => $this->validateResponseStructure($response['body'])
        ];

        echo $this->testResults['news_comment_unauth']['status'] === 'PASS' ? "✅" : "❌";
        echo " News Comment Unauthorized Test: {$this->testResults['news_comment_unauth']['status']}\n";

        // Test getting news comments (should work without auth)
        $response = $this->makeRequest('/api/news/1/comments', 'GET');
        
        $this->testResults['news_comments_get'] = [
            'status' => in_array($response['http_code'], [200, 404]) ? 'PASS' : 'FAIL',
            'http_code' => $response['http_code'],
            'response_structure' => $this->validateResponseStructure($response['body'])
        ];

        echo $this->testResults['news_comments_get']['status'] === 'PASS' ? "✅" : "❌";
        echo " News Comments GET Test: {$this->testResults['news_comments_get']['status']}\n\n";
    }

    /**
     * Test forum post creation response format
     */
    private function testForumPostCreation()
    {
        echo "💬 Testing Forum Post API Response Format...\n";
        
        // Test creating forum post without authentication
        $response = $this->makeRequest('/api/forums/threads/1/posts', 'POST', [
            'content' => 'Test forum post for API integration testing'
        ]);

        $this->testResults['forum_post_unauth'] = [
            'status' => $response['http_code'] === 401 ? 'PASS' : 'FAIL',
            'http_code' => $response['http_code'],
            'response_structure' => $this->validateResponseStructure($response['body'])
        ];

        echo $this->testResults['forum_post_unauth']['status'] === 'PASS' ? "✅" : "❌";
        echo " Forum Post Unauthorized Test: {$this->testResults['forum_post_unauth']['status']}\n";

        // Test getting forum threads (should work without auth)
        $response = $this->makeRequest('/api/forums/threads', 'GET');
        
        $this->testResults['forum_threads_get'] = [
            'status' => $response['http_code'] === 200 ? 'PASS' : 'FAIL',
            'http_code' => $response['http_code'],
            'response_structure' => $this->validateResponseStructure($response['body'])
        ];

        echo $this->testResults['forum_threads_get']['status'] === 'PASS' ? "✅" : "❌";
        echo " Forum Threads GET Test: {$this->testResults['forum_threads_get']['status']}\n\n";
    }

    /**
     * Test response formats for consistency
     */
    private function testResponseFormats()
    {
        echo "📋 Testing API Response Format Consistency...\n";
        
        $endpoints = [
            '/api/public/news' => 'GET',
            '/api/public/teams' => 'GET',
            '/api/public/players' => 'GET',
            '/api/forums/categories' => 'GET'
        ];

        foreach ($endpoints as $endpoint => $method) {
            $response = $this->makeRequest($endpoint, $method);
            $isValidStructure = $this->validateResponseStructure($response['body']);
            
            $this->testResults['format_' . str_replace('/', '_', $endpoint)] = [
                'endpoint' => $endpoint,
                'status' => ($response['http_code'] === 200 && $isValidStructure) ? 'PASS' : 'FAIL',
                'http_code' => $response['http_code'],
                'has_success_field' => $isValidStructure
            ];

            echo $this->testResults['format_' . str_replace('/', '_', $endpoint)]['status'] === 'PASS' ? "✅" : "❌";
            echo " {$endpoint}: {$this->testResults['format_' . str_replace('/', '_', $endpoint)]['status']}\n";
        }
        echo "\n";
    }

    /**
     * Make HTTP request
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null, $headers = [])
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'body' => $response,
            'http_code' => $httpCode
        ];
    }

    /**
     * Validate API response structure
     */
    private function validateResponseStructure($responseBody)
    {
        $data = json_decode($responseBody, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Check for required fields in standardized API response
        return isset($data['success']) && isset($data['timestamp']);
    }

    /**
     * Generate comprehensive test report
     */
    private function generateReport()
    {
        echo "📊 COMPREHENSIVE API INTEGRATION TEST REPORT\n";
        echo "==============================================\n\n";

        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, function($result) {
            return $result['status'] === 'PASS';
        }));

        echo "📈 Summary: {$passedTests}/{$totalTests} tests passed (" . round(($passedTests/$totalTests)*100) . "%)\n\n";

        // Detailed results
        echo "📋 Detailed Results:\n";
        echo "-------------------\n";
        
        foreach ($this->testResults as $testName => $result) {
            $icon = $result['status'] === 'PASS' ? '✅' : '❌';
            echo "{$icon} {$testName}: {$result['status']} (HTTP {$result['http_code']})\n";
        }

        // Recommendations
        echo "\n🔧 API Integration Status:\n";
        echo "-------------------------\n";
        
        if ($passedTests === $totalTests) {
            echo "✅ All API integration fixes are working correctly!\n";
            echo "✅ Response formats are standardized\n";
            echo "✅ Authentication flow is working properly\n";
            echo "✅ CORS configuration is correct\n";
        } else {
            echo "⚠️  Some API integration issues remain:\n";
            
            $failedTests = array_filter($this->testResults, function($result) {
                return $result['status'] === 'FAIL';
            });
            
            foreach ($failedTests as $testName => $result) {
                echo "   • {$testName}: {$result['status']} (HTTP {$result['http_code']})\n";
            }
        }

        // Save detailed report
        $reportData = [
            'test_summary' => [
                'total_tests' => $totalTests,
                'passed_tests' => $passedTests,
                'success_rate' => round(($passedTests/$totalTests)*100, 2) . '%',
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'detailed_results' => $this->testResults
        ];

        file_put_contents('api_integration_test_report.json', json_encode($reportData, JSON_PRETTY_PRINT));
        echo "\n💾 Detailed report saved to: api_integration_test_report.json\n";
    }
}

// Run the tests
$tester = new APIIntegrationTester();
$tester->runAllTests();