<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    // Create permissions
    $permissions = [
        'manage-events',
        'manage-users',
        'manage-teams', 
        'manage-players',
        'manage-matches',
        'manage-brackets',
        'live-scoring',
        'moderate-content'
    ];
    
    echo "Creating permissions...\n";
    foreach ($permissions as $permissionName) {
        $permission = Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'api'
        ]);
        echo "- {$permissionName}: " . ($permission->wasRecentlyCreated ? 'Created' : 'Exists') . "\n";
    }
    
    // Get admin role
    $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
    if (!$adminRole) {
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        echo "Admin role created\n";
    }
    
    // Assign all permissions to admin role
    echo "\nAssigning permissions to admin role...\n";
    $adminRole->syncPermissions($permissions);
    echo "All permissions assigned to admin role\n";
    
    // Get admin user and assign role
    $admin = User::where('email', 'admin@mrvl.net')->first();
    if ($admin) {
        $admin->assignRole('admin');
        echo "Admin role assigned to {$admin->email}\n";
        
        // Verify permissions
        echo "\nAdmin user permissions:\n";
        foreach ($admin->getAllPermissions() as $permission) {
            echo "- {$permission->name}\n";
        }
    } else {
        echo "Admin user not found!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}