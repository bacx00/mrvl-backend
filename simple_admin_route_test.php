<?php
/**
 * Simple test to check admin routes
 */

// Get admin token for testing
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiNjFlNjNkOTNkMDQ2MTJjN2VjNGVhNWMxNDQ1NTNmNjY5NzgzYmRlMTM2ZjlhOTU3ZjUwOWNhODJhNjhiMzk3N2NkOWQ3NzNiZGZhZTBkMGQiLCJpYXQiOjE3NTUwNzI1NDcuNzg5ODczLCJuYmYiOjE3NTUwNzI1NDcuNzg5ODc1LCJleHAiOjE3ODY2MDg1NDcuNzg3Njk2LCJzdWIiOiIxNjIiLCJzY29wZXMiOltdfQ.kjyFSr4VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7NKb-j6VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7NKb-j6VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7N';

$endpoints_to_test = [
    'GET /api/admin/teams' => 'https://mrvl.gg/api/admin/teams',
    'GET /api/admin/players' => 'https://mrvl.gg/api/admin/players',
    'POST /api/admin/teams' => 'https://mrvl.gg/api/admin/teams',
    'POST /api/admin/players' => 'https://mrvl.gg/api/admin/players'
];

foreach ($endpoints_to_test as $description => $url) {
    echo "\n🔍 Testing: $description\n";
    
    $method = explode(' ', $description)[0];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => 'data']));
    }
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo "❌ CURL Error: $curl_error\n";
        continue;
    }
    
    echo "📊 HTTP Status: $http_code\n";
    
    if ($http_code === 404) {
        echo "❌ Route not found!\n";
    } elseif ($http_code === 500) {
        echo "❌ Server error!\n";
        // Show first 500 chars of response for debugging
        echo "🔍 Response snippet: " . substr($response, 0, 500) . "\n";
    } elseif ($http_code === 401 || $http_code === 403) {
        echo "🔐 Authentication/Authorization issue\n";
    } elseif ($http_code >= 200 && $http_code < 300) {
        echo "✅ Endpoint working!\n";
    } else {
        echo "⚠️ Unexpected status: $http_code\n";
    }
    
    echo "---\n";
}