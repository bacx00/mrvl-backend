<?php

echo "Comprehensive Authentication Security Test\n";
echo "==========================================\n\n";

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

function testTokenAuthentication(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "2. TOKEN AUTHENTICATION TEST\n";
    echo "===========================\n";
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) {
            continue;
        }
        
        echo "Testing {$role} token access: ";
        
        $response = makeRequest(
            "{$baseUrl}/auth/user",
            'GET',
            null,
            ["Authorization: Bearer {$data['token']}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS\n";
            $testResults['token_auth'][$role] = 'PASS';
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            $testResults['token_auth'][$role] = 'FAIL';
        }
    }
    echo "\n";
}

function testRoleBasedAccess(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "3. ROLE-BASED ACCESS CONTROL TEST\n";
    echo "=================================\n";
    
    $accessTests = [
        'admin_users' => ['endpoint' => '/admin/users', 'allowed' => ['admin']],
        'admin_stats' => ['endpoint' => '/admin/stats', 'allowed' => ['admin']],
        'profile_access' => ['endpoint' => '/profile', 'allowed' => ['admin', 'moderator', 'user']],
        'user_stats' => ['endpoint' => '/auth/user-stats', 'allowed' => ['admin', 'moderator', 'user']]
    ];
    
    foreach ($accessTests as $testName => $config) {
        echo "Testing {$testName}:\n";
        
        foreach ($testUsers as $role => $data) {
            if (!isset($data['token'])) continue;
            
            echo "  {$role}: ";
            
            $response = makeRequest(
                "{$baseUrl}{$config['endpoint']}",
                'GET',
                null,
                ["Authorization: Bearer {$data['token']}"]
            );
            
            $shouldHaveAccess = in_array($role, $config['allowed']);
            
            if ($shouldHaveAccess) {
                if ($response['http_code'] === 200) {
                    echo "✅ PASS (access granted)\n";
                    $testResults['rbac'][$testName][$role] = 'PASS';
                } else {
                    echo "❌ FAIL (access denied - HTTP {$response['http_code']})\n";
                    $testResults['rbac'][$testName][$role] = 'FAIL';
                }
            } else {
                if (in_array($response['http_code'], [401, 403])) {
                    echo "✅ PASS (access denied)\n";
                    $testResults['rbac'][$testName][$role] = 'PASS';
                } else {
                    echo "❌ FAIL (access granted - HTTP {$response['http_code']})\n";
                    $testResults['rbac'][$testName][$role] = 'FAIL';
                }
            }
        }
        echo "\n";
    }
}

function testPasswordSecurity(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "4. PASSWORD SECURITY TEST\n";
    echo "========================\n";
    
    $userToken = $testUsers['user']['token'] ?? null;
    if (!$userToken) {
        echo "❌ No user token available for password test\n";
        return;
    }
    
    // Test password change with correct endpoint
    echo "Testing password change: ";
    
    $response = makeRequest(
        "{$baseUrl}/profile/change-password",
        'POST',
        [
            'current_password' => 'testpass123',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123'
        ],
        ["Authorization: Bearer {$userToken}"]
    );
    
    if ($response['http_code'] === 200) {
        echo "✅ PASS\n";
        $testResults['password']['change'] = 'PASS';
        
        // Test old password rejection
        echo "Testing old password rejection: ";
        $loginResponse = makeRequest(
            "{$baseUrl}/auth/login",
            'POST',
            ['email' => 'user@test.com', 'password' => 'testpass123']
        );
        
        if ($loginResponse['http_code'] === 401) {
            echo "✅ PASS\n";
            $testResults['password']['old_rejected'] = 'PASS';
        } else {
            echo "❌ FAIL\n";
            $testResults['password']['old_rejected'] = 'FAIL';
        }
        
        // Test new password works
        echo "Testing new password acceptance: ";
        $loginResponse = makeRequest(
            "{$baseUrl}/auth/login",
            'POST',
            ['email' => 'user@test.com', 'password' => 'newpass123']
        );
        
        if ($loginResponse['http_code'] === 200) {
            echo "✅ PASS\n";
            $testResults['password']['new_accepted'] = 'PASS';
            $testUsers['user']['token'] = $loginResponse['body']['token'];
        } else {
            echo "❌ FAIL\n";
            $testResults['password']['new_accepted'] = 'FAIL';
        }
        
    } else {
        echo "❌ FAIL - HTTP {$response['http_code']}\n";
        $testResults['password']['change'] = 'FAIL';
        if (isset($response['body']['message'])) {
            echo "  Error: {$response['body']['message']}\n";
        }
    }
    echo "\n";
}

function testProfileEndpoints(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "5. PROFILE ENDPOINTS TEST\n";
    echo "========================\n";
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) continue;
        
        echo "Testing {$role} profile endpoints:\n";
        
        // Test profile access
        echo "  Profile view: ";
        $response = makeRequest(
            "{$baseUrl}/profile",
            'GET',
            null,
            ["Authorization: Bearer {$data['token']}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS\n";
            $testResults['profile']['view'][$role] = 'PASS';
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            $testResults['profile']['view'][$role] = 'FAIL';
        }
        
        // Test profile activity
        echo "  Profile activity: ";
        $response = makeRequest(
            "{$baseUrl}/profile/activity",
            'GET',
            null,
            ["Authorization: Bearer {$data['token']}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS\n";
            $testResults['profile']['activity'][$role] = 'PASS';
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            $testResults['profile']['activity'][$role] = 'FAIL';
        }
    }
    echo "\n";
}

function testSessionManagement(&$testResults) {
    global $baseUrl, $testUsers;
    
    echo "6. SESSION MANAGEMENT TEST\n";
    echo "=========================\n";
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) continue;
        
        echo "Testing {$role} logout: ";
        
        $response = makeRequest(
            "{$baseUrl}/auth/logout",
            'POST',
            null,
            ["Authorization: Bearer {$data['token']}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS\n";
            $testResults['session']['logout'][$role] = 'PASS';
            
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
                $testResults['session']['invalidation'][$role] = 'PASS';
            } else {
                echo "❌ FAIL\n";
                $testResults['session']['invalidation'][$role] = 'FAIL';
            }
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            $testResults['session']['logout'][$role] = 'FAIL';
        }
    }
    echo "\n";
}

function generateSecurityReport($testResults) {
    echo "SECURITY ASSESSMENT REPORT\n";
    echo "==========================\n\n";
    
    $sections = [
        'login' => 'Login Functionality',
        'token_auth' => 'Token Authentication',
        'rbac' => 'Role-Based Access Control',
        'password' => 'Password Security',
        'profile' => 'Profile System',
        'session' => 'Session Management'
    ];
    
    $overallScore = 0;
    $totalTests = 0;
    
    foreach ($sections as $key => $title) {
        echo "{$title}:\n";
        
        if (!isset($testResults[$key])) {
            echo "  ❌ NOT TESTED\n\n";
            continue;
        }
        
        $sectionPassed = 0;
        $sectionTotal = 0;
        
        foreach ($testResults[$key] as $testName => $result) {
            if (is_array($result)) {
                foreach ($result as $subTest => $subResult) {
                    $status = $subResult === 'PASS' ? '✅' : '❌';
                    echo "  {$status} {$testName} ({$subTest})\n";
                    $sectionTotal++;
                    if ($subResult === 'PASS') $sectionPassed++;
                }
            } else {
                $status = $result === 'PASS' ? '✅' : '❌';
                echo "  {$status} {$testName}\n";
                $sectionTotal++;
                if ($result === 'PASS') $sectionPassed++;
            }
        }
        
        $percentage = $sectionTotal > 0 ? round(($sectionPassed / $sectionTotal) * 100) : 0;
        echo "  Score: {$sectionPassed}/{$sectionTotal} ({$percentage}%)\n\n";
        
        $overallScore += $sectionPassed;
        $totalTests += $sectionTotal;
    }
    
    $overallPercentage = $totalTests > 0 ? round(($overallScore / $totalTests) * 100) : 0;
    echo "OVERALL SECURITY SCORE: {$overallScore}/{$totalTests} ({$overallPercentage}%)\n\n";
    
    // Security recommendations
    echo "SECURITY RECOMMENDATIONS:\n";
    echo "========================\n";
    
    if ($overallPercentage >= 90) {
        echo "✅ Excellent security implementation!\n";
    } elseif ($overallPercentage >= 75) {
        echo "⚠️ Good security with minor improvements needed.\n";
    } elseif ($overallPercentage >= 60) {
        echo "⚠️ Moderate security - several issues need attention.\n";
    } else {
        echo "❌ Poor security - immediate action required!\n";
    }
    
    // Specific recommendations based on failed tests
    if (isset($testResults['password']['change']) && $testResults['password']['change'] === 'FAIL') {
        echo "- Fix password change functionality\n";
    }
    
    if (isset($testResults['rbac'])) {
        foreach ($testResults['rbac'] as $testName => $roles) {
            foreach ($roles as $role => $result) {
                if ($result === 'FAIL') {
                    echo "- Review role-based access control for {$testName}\n";
                    break 2;
                }
            }
        }
    }
    
    echo "\n✅ Authentication system integration test completed!\n";
}

try {
    testAuthentication($testResults);
    testTokenAuthentication($testResults);
    testRoleBasedAccess($testResults);
    testPasswordSecurity($testResults);
    testProfileEndpoints($testResults);
    testSessionManagement($testResults);
    
    generateSecurityReport($testResults);

} catch (Exception $e) {
    echo "❌ Test Error: " . $e->getMessage() . "\n";
}