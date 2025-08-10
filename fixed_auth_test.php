<?php

echo "Fixed Comprehensive Authentication Test\n";
echo "======================================\n\n";

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

function testRoleBasedAccess(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "2. ROLE-BASED ACCESS CONTROL TEST\n";
    echo "=================================\n";
    
    $roleTests = [
        'admin_users' => [
            'url' => '/admin/users',
            'expected_roles' => ['admin']
        ],
        'admin_stats' => [
            'url' => '/admin/stats', 
            'expected_roles' => ['admin', 'moderator']
        ]
    ];
    
    foreach ($roleTests as $testName => $testConfig) {
        echo "Testing {$testName}:\n";
        
        foreach ($testUsers as $role => $data) {
            if (!isset($data['token'])) {
                continue;
            }
            
            $response = makeRequest(
                "{$baseUrl}{$testConfig['url']}",
                'GET',
                null,
                ["Authorization: Bearer {$data['token']}"]
            );
            
            $shouldHaveAccess = in_array($role, $testConfig['expected_roles']);
            
            echo "  {$role}: ";
            
            if ($shouldHaveAccess) {
                if ($response['http_code'] === 200) {
                    echo "✅ PASS (access granted)\n";
                    $testResults['rbac']["{$testName}_{$role}"] = 'PASS';
                } else {
                    echo "❌ FAIL (access denied - HTTP {$response['http_code']})\n";
                    $testResults['rbac']["{$testName}_{$role}"] = 'FAIL';
                }
            } else {
                if ($response['http_code'] === 403) {
                    echo "✅ PASS (access denied)\n";
                    $testResults['rbac']["{$testName}_{$role}"] = 'PASS';
                } else {
                    echo "❌ FAIL (access granted - HTTP {$response['http_code']})\n";
                    $testResults['rbac']["{$testName}_{$role}"] = 'FAIL';
                }
            }
        }
        echo "\n";
    }
}

function testUserEndpoints(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "3. USER PROFILE ENDPOINTS TEST\n";
    echo "==============================\n";
    
    $userEndpoints = [
        'me' => '/auth/me',
        'user' => '/auth/user',
        'user_stats' => '/user/stats',  // Changed from /auth/user-stats
        'user_activity' => '/user/activity'  // Changed from /auth/user-activity
    ];
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) {
            continue;
        }
        
        echo "Testing {$role} user endpoints:\n";
        
        foreach ($userEndpoints as $endpointName => $endpoint) {
            echo "  {$endpointName}: ";
            
            $response = makeRequest(
                "{$baseUrl}{$endpoint}",
                'GET',
                null,
                ["Authorization: Bearer {$data['token']}"]
            );
            
            if ($response['http_code'] === 200) {
                echo "✅ PASS\n";
                $testResults['user_endpoints']["{$role}_{$endpointName}"] = 'PASS';
            } else {
                echo "❌ FAIL - HTTP {$response['http_code']}\n";
                $testResults['user_endpoints']["{$role}_{$endpointName}"] = 'FAIL';
            }
        }
        echo "\n";
    }
}

function testPasswordChange(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "4. PASSWORD SECURITY TEST\n";
    echo "========================\n";
    
    $userToken = $testUsers['user']['token'] ?? null;
    if (!$userToken) {
        echo "❌ No user token available for password test\n\n";
        return;
    }
    
    echo "Testing password change: ";
    
    $passwordData = [
        'current_password' => 'testpass123',
        'new_password' => 'NewTestPass123!@#',
        'new_password_confirmation' => 'NewTestPass123!@#'
    ];
    
    $response = makeRequest(
        "{$baseUrl}/user/change-password",  // Changed from /profile/change-password
        'POST',
        $passwordData,
        ["Authorization: Bearer {$userToken}"]
    );
    
    if ($response['http_code'] === 200) {
        echo "✅ PASS\n";
        $testResults['password']['change'] = 'PASS';
    } else {
        echo "❌ FAIL - HTTP {$response['http_code']}\n";
        if (isset($response['body']['message'])) {
            echo "  Error: {$response['body']['message']}\n";
        }
        $testResults['password']['change'] = 'FAIL';
    }
    echo "\n";
}

function testSessionManagement(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "5. SESSION MANAGEMENT TEST\n";
    echo "=========================\n";
    
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
            $testResults['session']["{$role}_logout"] = 'PASS';
            
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
                $testResults['session']["{$role}_invalidation"] = 'PASS';
            } else {
                echo "❌ FAIL\n";
                $testResults['session']["{$role}_invalidation"] = 'FAIL';
            }
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            $testResults['session']["{$role}_logout"] = 'FAIL';
        }
    }
    echo "\n";
}

// Run all tests
testAuthentication($testResults);
testRoleBasedAccess($testResults);
testUserEndpoints($testResults);
testPasswordChange($testResults);
testSessionManagement($testResults);

// Calculate scores
function calculateScore($results) {
    if (empty($results)) return 0;
    $passed = array_filter($results, fn($result) => $result === 'PASS');
    return count($passed) . '/' . count($results) . ' (' . round(count($passed)/count($results) * 100) . '%)';
}

echo "SECURITY ASSESSMENT REPORT\n";
echo "==========================\n\n";

echo "Login Functionality:\n";
foreach ($testResults['login'] ?? [] as $role => $result) {
    echo "  " . ($result === 'PASS' ? '✅' : '❌') . " {$role}\n";
}
echo "  Score: " . calculateScore($testResults['login'] ?? []) . "\n\n";

echo "Role-Based Access Control:\n";
foreach ($testResults['rbac'] ?? [] as $test => $result) {
    echo "  " . ($result === 'PASS' ? '✅' : '❌') . " {$test}\n";
}
echo "  Score: " . calculateScore($testResults['rbac'] ?? []) . "\n\n";

echo "User Endpoints:\n";
foreach ($testResults['user_endpoints'] ?? [] as $test => $result) {
    echo "  " . ($result === 'PASS' ? '✅' : '❌') . " {$test}\n";
}
echo "  Score: " . calculateScore($testResults['user_endpoints'] ?? []) . "\n\n";

echo "Password Security:\n";
foreach ($testResults['password'] ?? [] as $test => $result) {
    echo "  " . ($result === 'PASS' ? '✅' : '❌') . " {$test}\n";
}
echo "  Score: " . calculateScore($testResults['password'] ?? []) . "\n\n";

echo "Session Management:\n";
foreach ($testResults['session'] ?? [] as $test => $result) {
    echo "  " . ($result === 'PASS' ? '✅' : '❌') . " {$test}\n";
}
echo "  Score: " . calculateScore($testResults['session'] ?? []) . "\n\n";

// Calculate overall score
$allResults = [];
foreach ($testResults as $category) {
    $allResults = array_merge($allResults, $category);
}

$overallScore = calculateScore($allResults);
echo "OVERALL SECURITY SCORE: {$overallScore}\n\n";

$percentage = count(array_filter($allResults, fn($r) => $r === 'PASS')) / count($allResults) * 100;

echo "SECURITY RECOMMENDATIONS:\n";
echo "========================\n";
if ($percentage >= 90) {
    echo "✅ Excellent security posture!\n";
} elseif ($percentage >= 70) {
    echo "🟡 Good security - minor improvements needed\n";
} else {
    echo "❌ Poor security - immediate action required!\n";
}

echo "\n✅ Authentication system integration test completed!\n";