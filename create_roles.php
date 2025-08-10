<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;

try {
    // Create roles if they don't exist
    $roles = ['admin', 'moderator', 'user'];
    
    foreach ($roles as $roleName) {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
        echo "Role '{$roleName}' ensured for guard 'api'\n";
        
        $webRole = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        echo "Role '{$roleName}' ensured for guard 'web'\n";
    }
    
    echo "All roles created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}