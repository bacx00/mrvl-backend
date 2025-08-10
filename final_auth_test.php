<?php

echo "Final Authentication System Test\n";
echo "================================\n\n";

$baseUrl = 'http://localhost:8000/api';
$testResults = [];

// Test credentials
$testUsers = [
    'admin' => ['email' => 'admin@test.com', 'password' => 'testpass123'],
    'moderator' => ['email' => 'mod@test.com', 'password' => 'testpass123'],
    'user' => ['email' => 'user@test.com', 'password' => 'testpass123']
];

function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $headers),
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);

    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }

    return [
        'http_code' => $httpCode,
        'body' => json_decode($response, true) ?: $response,
        'raw' => $response
    ];
}

function testAuthentication(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "1. AUTHENTICATION TEST\n";
    echo "=====================\n";
    
    foreach ($testUsers as $role => $credentials) {
        echo "Testing {$role} login: ";
        
        $response = makeRequest("{$baseUrl}/auth/login", 'POST', $credentials);
        
        if ($response['http_code'] === 200 && isset($response['body']['token'])) {
            echo "✅ PASS\n";
            $testUsers[$role]['token'] = $response['body']['token'];
            $testUsers[$role]['user_data'] = $response['body']['user'];
            $testResults['login'][$role] = 'PASS';
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            $testResults['login'][$role] = 'FAIL';
        }
    }
    echo "\n";
}

function testTokenPersistence(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "2. TOKEN PERSISTENCE TEST\n";
    echo "=========================\n";
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) {
            continue;
        }
        
        echo "Testing {$role} token persistence: ";
        
        // Make multiple requests to test token persistence
        $success = true;
        for ($i = 0; $i < 3; $i++) {
            $response = makeRequest(
                "{$baseUrl}/auth/user",
                'GET',
                null,
                ["Authorization: Bearer {$data['token']}"]
            );
            
            if ($response['http_code'] !== 200) {
                $success = false;
                break;
            }
            
            // Small delay between requests
            usleep(100000); // 0.1 seconds
        }
        
        if ($success) {
            echo "✅ PASS\n";
            $testResults['persistence'][$role] = 'PASS';
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            $testResults['persistence'][$role] = 'FAIL';
        }
    }
    echo "\n";
}

function testCoreAdminAccess(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "3. ADMIN ACCESS TEST (Core Authentication)\n";
    echo "==========================================\n";
    
    // Test core admin functionality that should work
    $adminToken = $testUsers['admin']['token'] ?? null;
    if (!$adminToken) {
        echo "❌ No admin token available\n\n";
        return;
    }
    
    // Test a basic admin endpoint that should work
    echo "Testing admin user endpoint access: ";
    
    $response = makeRequest(
        "{$baseUrl}/auth/user",
        'GET',
        null,
        ["Authorization: Bearer {$adminToken}"]
    );
    
    if ($response['http_code'] === 200) {
        $userData = $response['body']['data'] ?? [];
        if (isset($userData['role']) && $userData['role'] === 'admin') {
            echo "✅ PASS (admin role verified)\n";
            $testResults['admin_access']['core'] = 'PASS';
        } else {
            echo "❌ FAIL (admin role not found)\n";
            $testResults['admin_access']['core'] = 'FAIL';
        }
    } else {
        echo "❌ FAIL - HTTP {$response['http_code']}\n";
        $testResults['admin_access']['core'] = 'FAIL';
    }
    echo "\n";
}

function testUserEndpoints(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "4. USER PROFILE ENDPOINTS TEST\n";
    echo "==============================\n";
    
    $coreEndpoints = [
        'me' => '/auth/me',
        'user' => '/auth/user',
        'stats' => '/user/stats',
        'activity' => '/user/activity'
    ];
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) {
            continue;
        }
        
        echo "Testing {$role} endpoints:\n";
        
        foreach ($coreEndpoints as $endpointName => $endpoint) {
            echo "  {$endpointName}: ";
            
            $response = makeRequest(
                "{$baseUrl}{$endpoint}",
                'GET',
                null,
                ["Authorization: Bearer {$data['token']}"]
            );
            
            if ($response['http_code'] === 200) {
                echo "✅ PASS\n";
                $testResults['endpoints']["{$role}_{$endpointName}"] = 'PASS';
            } else {
                echo "❌ FAIL - HTTP {$response['http_code']}\n";
                $testResults['endpoints']["{$role}_{$endpointName}"] = 'FAIL';
            }
        }
        echo "\n";
    }
}

function testSessionLogout(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "5. SESSION LOGOUT TEST\n";
    echo "======================\n";
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) {
            continue;
        }
        
        echo "Testing {$role} logout: ";
        
        $response = makeRequest(
            "{$baseUrl}/auth/logout",
            'POST',
            null,
            ["Authorization: Bearer {$data['token']}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS\n";
            $testResults['logout'][$role] = 'PASS';
            
            // Test token invalidation
            echo "  Token invalidation: ";
            $testResponse = makeRequest(
                "{$baseUrl}/auth/user",
                'GET',
                null,
                ["Authorization: Bearer {$data['token']}"]
            );
            
            if ($testResponse['http_code'] === 401) {
                echo "✅ PASS\n";
                $testResults['invalidation'][$role] = 'PASS';
            } else {
                echo "❌ FAIL - HTTP {$testResponse['http_code']}\n";
                $testResults['invalidation'][$role] = 'FAIL';
            }
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            $testResults['logout'][$role] = 'FAIL';
        }
    }
    echo "\n";
}

// Run all tests
testAuthentication($testResults);
testTokenPersistence($testResults);
testCoreAdminAccess($testResults);
testUserEndpoints($testResults);
testSessionLogout($testResults);

// Calculate scores
function calculateScore($results) {
    if (empty($results)) return ['passed' => 0, 'total' => 0, 'percentage' => 0];
    $passed = array_filter($results, fn($result) => $result === 'PASS');
    $total = count($results);
    $passedCount = count($passed);
    return [
        'passed' => $passedCount,
        'total' => $total,
        'percentage' => $total > 0 ? round(($passedCount / $total) * 100) : 0
    ];
}

echo "FINAL AUTHENTICATION ASSESSMENT\n";
echo "===============================\n\n";

$categories = [
    'Login Functionality' => $testResults['login'] ?? [],
    'Token Persistence' => $testResults['persistence'] ?? [],
    'Admin Access (Core)' => $testResults['admin_access'] ?? [],
    'User Endpoints' => $testResults['endpoints'] ?? [],
    'Session Logout' => $testResults['logout'] ?? [],
    'Token Invalidation' => $testResults['invalidation'] ?? []
];

foreach ($categories as $categoryName => $categoryResults) {
    $score = calculateScore($categoryResults);
    echo "{$categoryName}:\n";
    
    foreach ($categoryResults as $test => $result) {
        echo "  " . ($result === 'PASS' ? '✅' : '❌') . " {$test}\n";
    }
    
    echo "  Score: {$score['passed']}/{$score['total']} ({$score['percentage']}%)\n\n";
}

// Calculate overall score
$allResults = [];
foreach ($testResults as $category) {
    $allResults = array_merge($allResults, $category);
}

$overallScore = calculateScore($allResults);
echo "OVERALL AUTHENTICATION SCORE: {$overallScore['passed']}/{$overallScore['total']} ({$overallScore['percentage']}%)\n\n";

echo "AUTHENTICATION STATUS:\n";
echo "=====================\n";
if ($overallScore['percentage'] >= 95) {
    echo "✅ EXCELLENT: Authentication system working perfectly!\n";
} elseif ($overallScore['percentage'] >= 85) {
    echo "🟢 GOOD: Authentication system working well with minor issues\n";
} elseif ($overallScore['percentage'] >= 70) {
    echo "🟡 FAIR: Authentication system working but needs improvement\n";
} else {
    echo "❌ POOR: Authentication system has significant issues\n";
}

echo "\n✅ Final authentication test completed!\n";