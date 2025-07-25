<?php

// Simple authentication test
$baseUrl = 'https://staging.mrvl.net/api';

function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'body' => $response];
}

echo "=== STEP 1: LOGIN ===\n";
$loginData = ['email' => 'jhonny@ar-mediia.com', 'password' => 'password123'];
$loginResponse = makeRequest("$baseUrl/auth/login", 'POST', $loginData);

echo "Login Response Code: " . $loginResponse['code'] . "\n";
echo "Login Response Body: " . $loginResponse['body'] . "\n\n";

if ($loginResponse['code'] == 200) {
    $loginResult = json_decode($loginResponse['body'], true);
    $token = $loginResult['token'] ?? null;
    
    echo "=== STEP 2: TEST TOKEN FORMATS ===\n";
    echo "Token: " . substr($token, 0, 50) . "...\n";
    echo "Token Length: " . strlen($token) . "\n";
    echo "Token Type: " . (str_contains($token, '.') ? 'JWT' : 'Passport') . "\n\n";
    
    echo "=== STEP 3: TEST AUTH/ME ===\n";
    $meResponse = makeRequest("$baseUrl/auth/me", 'GET', null, $token);
    echo "Auth/Me Response Code: " . $meResponse['code'] . "\n";
    echo "Auth/Me Response Body: " . $meResponse['body'] . "\n\n";
    
    echo "=== STEP 4: TEST USER ENDPOINT ===\n";
    $userResponse = makeRequest("$baseUrl/user", 'GET', null, $token);
    echo "User Response Code: " . $userResponse['code'] . "\n";
    echo "User Response Body: " . $userResponse['body'] . "\n\n";
    
    echo "=== STEP 5: TEST DIFFERENT AUTH HEADERS ===\n";
    
    // Test with different header formats
    $formats = [
        "Bearer $token",
        $token,
        "JWT $token"
    ];
    
    foreach ($formats as $i => $format) {
        echo "Format " . ($i + 1) . ": $format\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$baseUrl/auth/me");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            "Authorization: $format"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Code: $httpCode\n";
        if ($httpCode != 200) {
            echo "Body: " . substr($response, 0, 100) . "\n";
        }
        echo "\n";
    }
} else {
    echo "Login failed, cannot proceed with token tests\n";
}