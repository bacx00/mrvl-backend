<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Set up database connection
$capsule = new DB;

$capsule->addConnection([
    'driver'    => 'sqlite',
    'database'  => __DIR__ . '/database/database.sqlite',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "🔍 Debug login for jhonny@ar-mediia.com...\n";

// Check the user directly
$user = DB::table('users')->where('email', 'jhonny@ar-mediia.com')->first();
if ($user) {
    echo "✅ User found in database:\n";
    echo "  - ID: {$user->id}\n";
    echo "  - Name: {$user->name}\n";
    echo "  - Email: {$user->email}\n";
    
    // Check password
    $password = 'password123';
    $hash = $user->password;
    echo "  - Password hash: " . substr($hash, 0, 20) . "...\n";
    
    // Check roles
    $roles = DB::table('model_has_roles as mhr')
        ->join('roles as r', 'mhr.role_id', '=', 'r.id')
        ->where('mhr.model_type', 'App\\Models\\User')
        ->where('mhr.model_id', $user->id)
        ->select('r.name', 'r.guard_name')
        ->get();
    
    echo "  - Roles:\n";
    foreach ($roles as $role) {
        echo "    * {$role->name} (guard: {$role->guard_name})\n";
    }
    
    // Test the actual login API call
    echo "\n🧪 Testing API login call...\n";
    
    $data = [
        'email' => 'jhonny@ar-mediia.com',
        'password' => 'password123'
    ];
    
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
    
    echo "HTTP Code: $httpCode\n";
    
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "Response success: " . ($responseData['success'] ? 'Yes' : 'No') . "\n";
        if (isset($responseData['user'])) {
            echo "Returned user ID: " . $responseData['user']['id'] . "\n";
            echo "Returned user name: " . $responseData['user']['name'] . "\n";
            echo "Returned user roles: " . implode(', ', $responseData['user']['roles'] ?? []) . "\n";
        }
        if (isset($responseData['token'])) {
            // Decode JWT
            $tokenParts = explode('.', $responseData['token']);
            if (count($tokenParts) >= 2) {
                $payload = json_decode(base64_decode($tokenParts[1]), true);
                echo "Token user ID (sub): " . ($payload['sub'] ?? 'Not found') . "\n";
            }
        }
    } else {
        echo "Invalid JSON response\n";
        echo "Raw response: " . substr($response, 0, 200) . "...\n";
    }
    
} else {
    echo "❌ User not found in database!\n";
}