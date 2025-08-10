<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Debug Specific Authentication Errors\n";
echo "====================================\n\n";

// Get test users
$users = [
    'admin' => User::where('email', 'admin@test.com')->first(),
    'moderator' => User::where('email', 'mod@test.com')->first(),
    'user' => User::where('email', 'user@test.com')->first(),
];

$tokens = [];
foreach ($users as $role => $user) {
    if ($user) {
        $tokens[$role] = $user->createToken('debug-test')->accessToken;
        echo "✅ {$role} token created\n";
    } else {
        echo "❌ {$role} user not found\n";
    }
}

echo "\n1. Testing admin endpoints with different roles:\n";
echo "===============================================\n";

$adminEndpoints = [
    '/admin/users' => 'GET',
    '/admin/stats' => 'GET'
];

foreach ($adminEndpoints as $endpoint => $method) {
    echo "\nTesting {$endpoint}:\n";
    
    foreach ($tokens as $role => $token) {
        echo "  {$role}: ";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://localhost:8000/api{$endpoint}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "HTTP {$httpCode}";
        
        if ($httpCode >= 400) {
            $responseData = json_decode($response, true);
            if (isset($responseData['message'])) {
                echo " - {$responseData['message']}";
            }
        }
        echo "\n";
    }
}

echo "\n2. Testing password change endpoint:\n";
echo "===================================\n";

$userToken = $tokens['user'] ?? null;
if ($userToken) {
    $passwordData = [
        'current_password' => 'testpass123',
        'new_password' => 'NewTestPass123!@#',
        'new_password_confirmation' => 'NewTestPass123!@#'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://localhost:8000/api/user/change-password',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($passwordData),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$userToken}",
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "Password change: HTTP {$httpCode}\n";
    
    if ($response) {
        $responseData = json_decode($response, true);
        if (isset($responseData['message'])) {
            echo "Message: {$responseData['message']}\n";
        }
        if (isset($responseData['error'])) {
            echo "Error: {$responseData['error']}\n";
        }
        if (isset($responseData['errors'])) {
            echo "Validation errors:\n";
            foreach ($responseData['errors'] as $field => $errors) {
                echo "  {$field}: " . implode(', ', $errors) . "\n";
            }
        }
    }
} else {
    echo "❌ No user token available\n";
}

echo "\nDebugging completed.\n";