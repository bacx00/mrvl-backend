<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    $email = 'admin@mrvl.com';
    $password = 'admin123';
    
    $user = User::where('email', $email)->first();
    
    if (!$user) {
        echo "User not found with email: $email\n";
        exit;
    }
    
    echo "User found:\n";
    echo "- ID: {$user->id}\n";
    echo "- Name: {$user->name}\n";
    echo "- Email: {$user->email}\n";
    echo "- Role: {$user->role}\n";
    echo "- Password hash: " . substr($user->password, 0, 20) . "...\n";
    
    $passwordCheck = Hash::check($password, $user->password);
    echo "- Password check: " . ($passwordCheck ? 'PASS' : 'FAIL') . "\n";
    
    // Try creating a token
    try {
        $token = $user->createToken('test-token')->accessToken;
        echo "- Token created successfully: " . substr($token, 0, 20) . "...\n";
    } catch (Exception $e) {
        echo "- Token creation failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}