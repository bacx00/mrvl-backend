<?php
/**
 * Debug middleware and authentication issues
 */

$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiNjFlNjNkOTNkMDQ2MTJjN2VjNGVhNWMxNDQ1NTNmNjY5NzgzYmRlMTM2ZjlhOTU3ZjUwOWNhODJhNjhiMzk3N2NkOWQ3NzNiZGZhZTBkMGQiLCJpYXQiOjE3NTUwNzI1NDcuNzg5ODczLCJuYmYiOjE3NTUwNzI1NDcuNzg5ODc1LCJleHAiOjE3ODY2MDg1NDcuNzg3Njk2LCJzdWIiOiIxNjIiLCJzY29wZXMiOltdfQ.kjyFSr4VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7NKb-j6VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7NKb-j6VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7N';

echo "🔧 Testing Authentication and Routes\n";
echo "=====================================\n\n";

// Test 1: Check if the user exists and has admin role
echo "1. Testing authentication...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://mrvl.gg/api/auth/me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $http_code\n";
if ($http_code === 200) {
    $data = json_decode($response, true);
    echo "   ✅ Authentication successful\n";
    echo "   User ID: " . ($data['id'] ?? 'N/A') . "\n";
    echo "   Username: " . ($data['username'] ?? 'N/A') . "\n";
    echo "   Role: " . ($data['role'] ?? 'N/A') . "\n";
} else {
    echo "   ❌ Authentication failed\n";
    echo "   Response: " . substr($response, 0, 200) . "\n";
}

echo "\n";

// Test 2: Check a simple non-admin endpoint
echo "2. Testing non-admin endpoint...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://mrvl.gg/api/teams');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $http_code\n";
if ($http_code === 200) {
    echo "   ✅ Regular API endpoint working\n";
} else {
    echo "   ❌ Regular API endpoint failed\n";
    echo "   Response: " . substr($response, 0, 200) . "\n";
}

echo "\n";

// Test 3: Check another admin endpoint for pattern
echo "3. Testing admin stats endpoint...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://mrvl.gg/api/admin/stats');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $http_code\n";
if ($http_code === 200) {
    echo "   ✅ Admin stats endpoint working\n";
} elseif ($http_code === 500) {
    echo "   ❌ Admin stats endpoint has 500 error\n";
} else {
    echo "   ⚠️ Admin stats endpoint returned: $http_code\n";
    echo "   Response: " . substr($response, 0, 200) . "\n";
}

echo "\n";

// Test 4: Test a working admin endpoint to see what's different
echo "4. Testing admin news endpoint...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://mrvl.gg/api/admin/news');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $http_code\n";
if ($http_code === 200) {
    echo "   ✅ Admin news endpoint working\n";
} elseif ($http_code === 500) {
    echo "   ❌ Admin news endpoint has 500 error\n";
} else {
    echo "   ⚠️ Admin news endpoint returned: $http_code\n";
}

echo "\nTest complete!\n";