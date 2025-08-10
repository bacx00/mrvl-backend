<?php

echo "Authentication API Integration Test\n";
echo "==================================\n\n";

$baseUrl = 'http://localhost:8000/api';

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

try {
    // Test 1: Login functionality
    echo "1. TESTING LOGIN FUNCTIONALITY...\n";
    
    foreach ($testUsers as $role => $credentials) {
        echo "Testing {$role} login: ";
        
        $response = makeRequest(
            "{$baseUrl}/auth/login",
            'POST',
            $credentials
        );
        
        if ($response['http_code'] === 200 && isset($response['body']['token'])) {
            echo "✅ PASS - Token received\n";
            
            // Store token for further tests
            $testUsers[$role]['token'] = $response['body']['token'];
            $testUsers[$role]['user_data'] = $response['body']['user'];
            
            echo "  User ID: {$response['body']['user']['id']}\n";
            echo "  Role: {$response['body']['user']['role']}\n";
            echo "  Token length: " . strlen($response['body']['token']) . " chars\n";
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            echo "  Response: " . json_encode($response['body']) . "\n";
        }
        echo "\n";
    }

    // Test 2: Token-based authentication for profile endpoints
    echo "2. TESTING TOKEN-BASED AUTHENTICATION...\n";
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) {
            echo "Skipping {$role} - no valid token\n";
            continue;
        }
        
        echo "Testing {$role} profile access: ";
        
        $response = makeRequest(
            "{$baseUrl}/auth/user",
            'GET',
            null,
            ["Authorization: Bearer {$data['token']}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS - Profile data retrieved\n";
            echo "  User: {$response['body']['data']['name']}\n";
            echo "  Email: {$response['body']['data']['email']}\n";
            echo "  Role: {$response['body']['data']['role']}\n";
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            echo "  Response: " . json_encode($response['body']) . "\n";
        }
        echo "\n";
    }

    // Test 3: Role-based access control
    echo "3. TESTING ROLE-BASED ACCESS CONTROL...\n";
    
    $adminEndpoints = [
        '/admin/users' => 'GET',
        '/admin/stats' => 'GET'
    ];
    
    foreach ($testUsers as $role => $data) {
        if (!isset($data['token'])) continue;
        
        echo "Testing {$role} admin access: ";
        
        $response = makeRequest(
            "{$baseUrl}/admin/users",
            'GET',
            null,
            ["Authorization: Bearer {$data['token']}"]
        );
        
        if ($role === 'admin') {
            if ($response['http_code'] === 200) {
                echo "✅ PASS - Admin access granted\n";
            } else {
                echo "❌ FAIL - Admin denied access (HTTP {$response['http_code']})\n";
            }
        } else {
            if (in_array($response['http_code'], [401, 403])) {
                echo "✅ PASS - Non-admin access properly denied\n";
            } else {
                echo "❌ FAIL - Non-admin given improper access (HTTP {$response['http_code']})\n";
            }
        }
        echo "\n";
    }

    // Test 4: Password change security
    echo "4. TESTING PASSWORD CHANGE SECURITY...\n";
    
    $userToken = $testUsers['user']['token'] ?? null;
    if ($userToken) {
        echo "Testing password change: ";
        
        $response = makeRequest(
            "{$baseUrl}/auth/change-password",
            'POST',
            [
                'current_password' => 'testpass123',
                'new_password' => 'newpass123',
                'new_password_confirmation' => 'newpass123'
            ],
            ["Authorization: Bearer {$userToken}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS - Password change successful\n";
            
            // Test old password no longer works
            echo "Testing old password rejection: ";
            $loginResponse = makeRequest(
                "{$baseUrl}/auth/login",
                'POST',
                ['email' => 'user@test.com', 'password' => 'testpass123']
            );
            
            if ($loginResponse['http_code'] === 401) {
                echo "✅ PASS - Old password rejected\n";
            } else {
                echo "❌ FAIL - Old password still works\n";
            }
            
            // Test new password works
            echo "Testing new password acceptance: ";
            $loginResponse = makeRequest(
                "{$baseUrl}/auth/login",
                'POST',
                ['email' => 'user@test.com', 'password' => 'newpass123']
            );
            
            if ($loginResponse['http_code'] === 200) {
                echo "✅ PASS - New password works\n";
                // Update token for logout test
                $testUsers['user']['token'] = $loginResponse['body']['token'];
            } else {
                echo "❌ FAIL - New password doesn't work\n";
            }
            
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
            echo "  Response: " . json_encode($response['body']) . "\n";
        }
        echo "\n";
    }

    // Test 5: Session management and logout
    echo "5. TESTING SESSION MANAGEMENT...\n";
    
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
            echo "✅ PASS - Logout successful\n";
            
            // Test token is invalidated
            echo "Testing token invalidation: ";
            $testResponse = makeRequest(
                "{$baseUrl}/auth/user",
                'GET',
                null,
                ["Authorization: Bearer {$data['token']}"]
            );
            
            if ($testResponse['http_code'] === 401) {
                echo "✅ PASS - Token invalidated\n";
            } else {
                echo "❌ FAIL - Token still valid\n";
            }
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
        }
        echo "\n";
    }

    // Test 6: Profile endpoint security
    echo "6. TESTING PROFILE ENDPOINT SECURITY...\n";
    
    // Re-login admin for profile tests
    $adminLogin = makeRequest(
        "{$baseUrl}/auth/login",
        'POST',
        $testUsers['admin']
    );
    
    if ($adminLogin['http_code'] === 200) {
        $adminToken = $adminLogin['body']['token'];
        $adminUserId = $adminLogin['body']['user']['id'];
        
        echo "Testing profile stats endpoint: ";
        $response = makeRequest(
            "{$baseUrl}/user/{$adminUserId}/stats",
            'GET',
            null,
            ["Authorization: Bearer {$adminToken}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS - Profile stats accessible\n";
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
        }
        
        echo "Testing profile activity endpoint: ";
        $response = makeRequest(
            "{$baseUrl}/user/{$adminUserId}/activity",
            'GET',
            null,
            ["Authorization: Bearer {$adminToken}"]
        );
        
        if ($response['http_code'] === 200) {
            echo "✅ PASS - Profile activity accessible\n";
        } else {
            echo "❌ FAIL - HTTP {$response['http_code']}\n";
        }
    }

    echo "\n✅ Authentication API integration test completed!\n";
    echo "\nSUMMARY:\n";
    echo "- Login/logout functionality: Tested\n";
    echo "- Token-based authentication: Tested\n";
    echo "- Role-based access control: Tested\n";
    echo "- Password security: Tested\n";
    echo "- Session management: Tested\n";
    echo "- Profile endpoints: Tested\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}