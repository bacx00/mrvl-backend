<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Debug Authentication Fix\n";
echo "========================\n\n";

// Test user credentials
$email = 'admin@mrvl.gg';
$password = 'testpass123';

echo "1. Finding user: {$email}\n";
$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ User found: ID {$user->id}, Role: {$user->role}\n\n";

echo "2. Checking password...\n";
if (!Hash::check($password, $user->password)) {
    echo "❌ Password check failed\n";
    echo "Password in database: " . $user->password . "\n";
    echo "Hash check result: " . (Hash::check($password, $user->password) ? 'true' : 'false') . "\n";
    exit(1);
}

echo "✅ Password check passed\n\n";

echo "3. Attempting to create token...\n";

try {
    // Try creating a personal access token
    $tokenResult = $user->createToken('auth-token');
    $token = $tokenResult->accessToken;
    
    echo "✅ Token created successfully\n";
    echo "Token: " . substr($token, 0, 20) . "...\n";
    echo "Token length: " . strlen($token) . "\n";
    
    // Test the token by making a request
    echo "\n4. Testing token authentication...\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://localhost:8000/api/auth/user',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: {$httpCode}\n";
    echo "Response: " . substr($response, 0, 200) . "\n";
    
    if ($httpCode === 200) {
        echo "✅ Token authentication working\n";
    } else {
        echo "❌ Token authentication failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Token creation failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n5. Checking OAuth tables...\n";

try {
    // Check if oauth tables exist and have data
    $clientsCount = DB::table('oauth_clients')->count();
    $tokensCount = DB::table('oauth_access_tokens')->count();
    
    echo "OAuth clients: {$clientsCount}\n";
    echo "Access tokens: {$tokensCount}\n";
    
    if ($clientsCount === 0) {
        echo "❌ No OAuth clients found - this might be the issue\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking OAuth tables: " . $e->getMessage() . "\n";
}

echo "\nDebugging completed.\n";