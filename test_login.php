<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = 'jhonny@ar-mediia.com';
$password = 'password123';

$user = User::where('email', $email)->first();

if (!$user) {
    echo "User not found\n";
    exit;
}

echo "User found: " . $user->email . "\n";
echo "Role: " . $user->role . "\n";

if (Hash::check($password, $user->password)) {
    echo "Password is correct\n";
    
    try {
        // Try to create a token
        $token = $user->createToken('auth-token')->accessToken;
        echo "Token created successfully: " . substr($token, 0, 20) . "...\n";
    } catch (Exception $e) {
        echo "Error creating token: " . $e->getMessage() . "\n";
    }
} else {
    echo "Password is incorrect\n";
}