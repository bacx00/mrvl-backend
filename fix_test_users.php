<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Fixing test user credentials...\n";

// Update admin user password and role
$adminUpdated = DB::table('users')->where('email', 'admin@test.com')->update([
    'password' => bcrypt('testpass123'),
    'role' => 'admin',
    'updated_at' => now()
]);

echo "Admin user updated: $adminUpdated\n";

// Verify admin credentials
$admin = DB::table('users')->where('email', 'admin@test.com')->first();
if ($admin) {
    $isValid = Hash::check('testpass123', $admin->password);
    echo "Admin password verification: " . ($isValid ? 'VALID' : 'INVALID') . "\n";
    echo "Admin role: {$admin->role}\n";
}

// Update other test users
$modUpdated = DB::table('users')->where('email', 'mod@test.com')->update([
    'password' => bcrypt('testpass123'),
    'role' => 'moderator',
    'updated_at' => now()
]);

$userUpdated = DB::table('users')->where('email', 'user@test.com')->update([
    'password' => bcrypt('testpass123'),
    'role' => 'user',
    'updated_at' => now()
]);

echo "Moderator user updated: $modUpdated\n";
echo "Regular user updated: $userUpdated\n";

echo "Test users fixed!\n";