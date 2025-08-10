<?php

require_once 'vendor/autoload.php';

// Simple test script to verify user API endpoints
$baseUrl = 'http://127.0.0.1:8001/api';

// Test user credentials (assuming we have an admin user)
$testEmail = 'admin@example.com';
$testPassword = 'admin123';

// Function to make API requests
function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $data ? json_encode($data) : null,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

echo "Testing MRVL User Management API\n";
echo "================================\n\n";

// 1. Test login
echo "1. Testing login...\n";
$loginResponse = makeRequest($baseUrl . '/auth/login', 'POST', [
    'email' => $testEmail,
    'password' => $testPassword
]);

if ($loginResponse['status_code'] === 200 && isset($loginResponse['body']['access_token'])) {
    $token = $loginResponse['body']['access_token'];
    echo "✓ Login successful\n";
} else {
    echo "✗ Login failed: " . ($loginResponse['body']['message'] ?? 'Unknown error') . "\n";
    echo "Response: " . $loginResponse['raw'] . "\n";
    exit(1);
}

// 2. Test getting all users
echo "\n2. Testing get all users...\n";
$usersResponse = makeRequest($baseUrl . '/admin/users', 'GET', null, $token);

if ($usersResponse['status_code'] === 200) {
    $userCount = count($usersResponse['body']['data'] ?? []);
    echo "✓ Retrieved {$userCount} users\n";
} else {
    echo "✗ Failed to get users: " . ($usersResponse['body']['message'] ?? 'Unknown error') . "\n";
    echo "Status code: " . $usersResponse['status_code'] . "\n";
    echo "Response: " . $usersResponse['raw'] . "\n";
}

// 3. Test creating a new user
echo "\n3. Testing create user...\n";
$newUser = [
    'name' => 'Test User ' . time(),
    'email' => 'testuser' . time() . '@example.com',
    'password' => 'testpass123',
    'role' => 'user',
    'status' => 'active'
];

$createResponse = makeRequest($baseUrl . '/admin/users', 'POST', $newUser, $token);

if ($createResponse['status_code'] === 201) {
    $createdUserId = $createResponse['body']['data']['id'];
    echo "✓ User created successfully with ID: {$createdUserId}\n";
} else {
    echo "✗ Failed to create user: " . ($createResponse['body']['message'] ?? 'Unknown error') . "\n";
    echo "Status code: " . $createResponse['status_code'] . "\n";
    echo "Response: " . $createResponse['raw'] . "\n";
    $createdUserId = null;
}

// 4. Test updating user (if created successfully)
if ($createdUserId) {
    echo "\n4. Testing update user...\n";
    $updateData = [
        'role' => 'moderator',
        'status' => 'active'
    ];
    
    $updateResponse = makeRequest($baseUrl . "/admin/users/{$createdUserId}", 'PUT', $updateData, $token);
    
    if ($updateResponse['status_code'] === 200) {
        echo "✓ User updated successfully\n";
    } else {
        echo "✗ Failed to update user: " . ($updateResponse['body']['message'] ?? 'Unknown error') . "\n";
        echo "Status code: " . $updateResponse['status_code'] . "\n";
        echo "Response: " . $updateResponse['raw'] . "\n";
    }
    
    // 5. Test getting single user
    echo "\n5. Testing get single user...\n";
    $getUserResponse = makeRequest($baseUrl . "/admin/users/{$createdUserId}", 'GET', null, $token);
    
    if ($getUserResponse['status_code'] === 200) {
        $userData = $getUserResponse['body']['data'];
        echo "✓ Retrieved user: {$userData['name']} ({$userData['email']}) - Role: {$userData['role']}\n";
    } else {
        echo "✗ Failed to get user: " . ($getUserResponse['body']['message'] ?? 'Unknown error') . "\n";
    }
    
    // 6. Test deleting user
    echo "\n6. Testing delete user...\n";
    $deleteResponse = makeRequest($baseUrl . "/admin/users/{$createdUserId}", 'DELETE', null, $token);
    
    if ($deleteResponse['status_code'] === 200) {
        echo "✓ User deleted successfully\n";
    } else {
        echo "✗ Failed to delete user: " . ($deleteResponse['body']['message'] ?? 'Unknown error') . "\n";
        echo "Status code: " . $deleteResponse['status_code'] . "\n";
        echo "Response: " . $deleteResponse['raw'] . "\n";
    }
}

echo "\nTest completed!\n";