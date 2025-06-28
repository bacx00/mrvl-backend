<?php

// Simple script to get admin token for testing
$BASE_URL = "https://staging.mrvl.net/api";

function makeRequest($method, $url, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

echo "🔑 GETTING ADMIN TOKEN\n";
echo "=====================\n";

// Try to login with default admin credentials
$loginData = [
    'email' => 'admin@test.com',
    'password' => 'password123'
];

$result = makeRequest('POST', $BASE_URL . '/auth/login', $loginData);
echo "Login attempt: HTTP {$result['http_code']}\n";

if ($result['http_code'] === 200 && isset($result['data']['token'])) {
    $token = $result['data']['token'];
    echo "✅ SUCCESS! Admin token: {$token}\n";
    echo "\nUpdate your test files with this token:\n";
    echo "\$ADMIN_TOKEN = \"{$token}\";\n";
} else {
    echo "❌ Login failed. Response:\n";
    echo json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    
    echo "\n🔧 Trying to create admin user first...\n";
    
    $createData = [
        'name' => 'Admin Test',
        'email' => 'admin@test.com', 
        'password' => 'password123',
        'role' => 'admin'
    ];
    
    $result = makeRequest('POST', $BASE_URL . '/admin/users', $createData);
    echo "User creation: HTTP {$result['http_code']}\n";
    if (isset($result['data'])) {
        echo json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    }
    
    if ($result['http_code'] === 201) {
        echo "\n🔄 Retrying login...\n";
        $result = makeRequest('POST', $BASE_URL . '/auth/login', $loginData);
        if ($result['http_code'] === 200 && isset($result['data']['token'])) {
            $token = $result['data']['token'];
            echo "✅ SUCCESS! Admin token: {$token}\n";
        }
    }
}