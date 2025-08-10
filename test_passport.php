<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Test direct token creation
    $user = App\Models\User::where('email', 'admin@example.com')->first();
    
    if (!$user) {
        echo "User not found\n";
        exit(1);
    }
    
    echo "User found: {$user->name} ({$user->email})\n";
    echo "Password check with 'admin123': " . (Hash::check('admin123', $user->password) ? 'PASS' : 'FAIL') . "\n";
    
    // Try to create a token
    try {
        $token = $user->createToken('test-token');
        echo "Token created successfully\n";
        echo "Access token exists: " . (isset($token->accessToken) ? 'YES' : 'NO') . "\n";
        
        // Test the full auth flow
        $credentials = [
            'email' => 'admin@example.com',
            'password' => 'admin123'
        ];
        
        if (auth()->attempt($credentials)) {
            echo "Auth attempt successful\n";
        } else {
            echo "Auth attempt failed\n";
        }
        
    } catch (Exception $e) {
        echo "Error creating token: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}