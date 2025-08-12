<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$admin = User::where('role', 'admin')->first();

if (!$admin) {
    $admin = User::create([
        'username' => 'admin',
        'email' => 'admin@mrvl.net',
        'password' => bcrypt('admin123'),
        'role' => 'admin',
        'status' => 'active'
    ]);
    echo "Admin user created\n";
} else {
    echo "Using existing admin: " . $admin->email . "\n";
}

$token = $admin->createToken('Admin-Test')->accessToken;
file_put_contents('/tmp/admin_token.txt', $token);
echo "Token saved to /tmp/admin_token.txt\n";
echo "Token (first 30 chars): " . substr($token, 0, 30) . "...\n";