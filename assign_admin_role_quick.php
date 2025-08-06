<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

try {
    $user = User::where('email', 'admin@mrvl.net')->first();
    if (!$user) {
        echo "User not found\n";
        exit(1);
    }

    // Create admin role if it doesn't exist
    if (!Role::where('name', 'admin')->where('guard_name', 'api')->exists()) {
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        echo "Admin role created\n";
    }

    // Assign role
    $user->assignRole('admin');
    echo "Admin role assigned to {$user->email}\n";
    
    // Verify
    $roles = $user->roles->pluck('name')->toArray();
    echo "User roles: " . implode(', ', $roles) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}