<?php

echo "🔍 DEBUGGING ULTIMATE TEST FAILURES\n";
echo "===================================\n";

$BASE_URL = "https://staging.mrvl.net/api";
$ADMIN_TOKEN = "456|XgZ5PbsCpIVrcpjZgeRJAhHmf8RGJBNaaWXLeczI2a360ed8";

function makeRequest($method, $url, $data = null, $headers = []) {
    $start_time = microtime(true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
        case 'PATCH':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            break;
        case 'OPTIONS':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
            break;
    }
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseTime = microtime(true) - $start_time;
    $error = curl_error($ch);
    
    // Split headers and body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers_response = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    return [
        'response' => $body,
        'headers' => $headers_response,
        'http_code' => $httpCode,
        'response_time' => $responseTime,
        'data' => json_decode($body, true),
        'error' => $error
    ];
}

function getAuthHeaders() {
    global $ADMIN_TOKEN;
    return ['Authorization: Bearer ' . $ADMIN_TOKEN];
}

// Test 1: Valid admin token (the exact test that's failing)
echo "🔐 Test 1: Valid Admin Token Access\n";
$result = makeRequest('GET', $BASE_URL . '/user', null, getAuthHeaders());
echo "HTTP Code: {$result['http_code']}\n";
echo "Response Time: {$result['response_time']}s\n";
echo "Headers: " . substr($result['headers'], 0, 200) . "...\n";
echo "Body: " . substr($result['response'], 0, 200) . "...\n";
echo "Data: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
echo "Success: " . ($result['http_code'] === 200 ? 'YES' : 'NO') . "\n\n";

// Test 2: Invalid Token
echo "🔐 Test 2: Invalid Token Rejection\n";
$result = makeRequest('GET', $BASE_URL . '/user', null, ['Authorization: Bearer invalid_token']);
echo "HTTP Code: {$result['http_code']}\n";
echo "Success: " . ($result['http_code'] === 401 ? 'YES' : 'NO') . "\n\n";

// Test 3: No Token
echo "🔐 Test 3: No Token Rejection\n";
$result = makeRequest('GET', $BASE_URL . '/user', null, []);
echo "HTTP Code: {$result['http_code']}\n";
echo "Success: " . ($result['http_code'] === 401 ? 'YES' : 'NO') . "\n\n";

// Test 4: CORS
echo "🌐 Test 4: CORS Headers\n";
$result = makeRequest('OPTIONS', $BASE_URL . '/teams');
echo "HTTP Code: {$result['http_code']}\n";
echo "Headers: " . $result['headers'] . "\n";
echo "Has CORS: " . (strpos($result['headers'], 'Access-Control') !== false ? 'YES' : 'NO') . "\n\n";

// Test 5: Basic API endpoints
echo "📡 Test 5: Basic API Endpoints\n";
$endpoints = [
    '/teams',
    '/game-data/heroes',
    '/game-data/maps',
    '/game-data/modes'
];

foreach ($endpoints as $endpoint) {
    $result = makeRequest('GET', $BASE_URL . $endpoint);
    echo "  {$endpoint}: HTTP {$result['http_code']} (" . ($result['http_code'] === 200 ? 'PASS' : 'FAIL') . ")\n";
}

echo "\n🔍 DEBUG COMPLETE\n";