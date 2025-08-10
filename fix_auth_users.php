<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Fix Authentication Users\n";
echo "========================\n\n";

$testUsers = [
    [
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'password' => 'testpass123',
        'role' => 'admin'
    ],
    [
        'name' => 'Test Moderator', 
        'email' => 'mod@test.com',
        'password' => 'testpass123',
        'role' => 'moderator'
    ],
    [
        'name' => 'Test User',
        'email' => 'user@test.com', 
        'password' => 'testpass123',
        'role' => 'user'
    ]
];

foreach ($testUsers as $userData) {
    echo "Creating/updating user: {$userData['email']}\n";
    
    $user = User::where('email', $userData['email'])->first();
    
    if (!$user) {
        $user = new User();
        $user->email = $userData['email'];
        echo "  → Creating new user\n";
    } else {
        echo "  → Updating existing user (ID: {$user->id})\n";
    }
    
    $user->name = $userData['name'];
    $user->password = $userData['password']; // This will trigger bcrypt in setPasswordAttribute
    $user->role = $userData['role'];
    $user->email_verified_at = now();
    $user->save();
    
    echo "  ✅ User saved: ID {$user->id}\n";
    
    // Test password
    if (Hash::check($userData['password'], $user->password)) {
        echo "  ✅ Password verification working\n";
    } else {
        echo "  ❌ Password verification failed\n";
    }
    
    echo "\n";
}

echo "All authentication users created/updated successfully!\n";