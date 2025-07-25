<?php

// Test admin login for jhonny@ar-mediia.com

require_once 'vendor/autoload.php';

echo "🔐 Testing Admin Login for jhonny@ar-mediia.com\n";
echo "==============================================\n\n";

$data = [
    'email' => 'jhonny@ar-mediia.com',
    'password' => 'password123'
];

echo "📡 Making API login request...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";

if ($response) {
    $responseData = json_decode($response, true);
    
    if ($responseData) {
        echo "Response Success: " . ($responseData['success'] ? '✅ Yes' : '❌ No') . "\n";
        
        if (isset($responseData['user'])) {
            echo "\n👤 User Data:\n";
            echo "  - ID: " . $responseData['user']['id'] . "\n";
            echo "  - Name: " . $responseData['user']['name'] . "\n";
            echo "  - Email: " . $responseData['user']['email'] . "\n";
            echo "  - Roles: " . implode(', ', $responseData['user']['roles'] ?? []) . "\n";
            
            if (in_array('admin', $responseData['user']['roles'] ?? [])) {
                echo "\n🎉 SUCCESS! User has ADMIN role in login response!\n";
            } else {
                echo "\n⚠️ User does not have admin role in response\n";
            }
        }
        
        if (isset($responseData['token'])) {
            echo "\n🔑 Token: " . substr($responseData['token'], 0, 50) . "...\n";
            
            // Save token for future use
            file_put_contents('/tmp/admin_token.txt', $responseData['token']);
            echo "  Token saved to /tmp/admin_token.txt\n";
        }
        
        if (isset($responseData['message'])) {
            echo "\n📝 Message: " . $responseData['message'] . "\n";
        }
        
    } else {
        echo "❌ Invalid JSON response\n";
        echo "Raw response: " . $response . "\n";
    }
} else {
    echo "❌ No response received\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Login Test Complete!\n";