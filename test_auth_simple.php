<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

echo "Testing MRVL Authentication..." . PHP_EOL;

// Create/update admin user
$admin = \App\Models\User::updateOrCreate(
    ['email' => 'admin@mrvl.net'],
    [
        'name' => 'Admin User',
        'password' => bcrypt('admin123'),
        'status' => 'active',
        'email_verified_at' => now()
    ]
);

echo "Admin user: {$admin->email}" . PHP_EOL;
echo "Password check: " . (Hash::check('admin123', $admin->password) ? 'PASS' : 'FAIL') . PHP_EOL;

// Test token creation
try {
    $token = $admin->createToken('test-token');
    echo "Token creation: PASS" . PHP_EOL;
    echo "Token: " . substr($token->accessToken, 0, 50) . "..." . PHP_EOL;
} catch (Exception $e) {
    echo "Token creation: FAIL - " . $e->getMessage() . PHP_EOL;
}

// Test API call
echo PHP_EOL . "Testing API call..." . PHP_EOL;

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/login');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'admin@mrvl.net', 
        'password' => 'admin123'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode" . PHP_EOL;
    if ($error) {
        echo "cURL Error: $error" . PHP_EOL;
    }
    echo "Response: $response" . PHP_EOL;
    
} catch (Exception $e) {
    echo "API Test Error: " . $e->getMessage() . PHP_EOL;
}