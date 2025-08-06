<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    // Create additional permissions
    $permissions = [
        'moderate-matches',
        'create-matches',
        'update-matches',
        'delete-matches'
    ];
    
    echo "Creating match permissions...\n";
    foreach ($permissions as $permissionName) {
        $permission = Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'api'
        ]);
        echo "- {$permissionName}: " . ($permission->wasRecentlyCreated ? 'Created' : 'Exists') . "\n";
    }
    
    // Get admin role and assign permissions
    $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
    if ($adminRole) {
        $adminRole->givePermissionTo($permissions);
        echo "Match permissions assigned to admin role\n";
        
        // Verify admin user has permissions
        $admin = User::where('email', 'admin@mrvl.net')->first();
        if ($admin) {
            echo "\nAdmin user permissions:\n";
            foreach ($admin->getAllPermissions() as $permission) {
                echo "- {$permission->name}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}