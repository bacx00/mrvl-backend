<?php

// Assign admin role to jhonny@ar-mediia.com in MySQL database

require_once 'vendor/autoload.php';

// Bootstrap Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

echo "🔧 Assigning Admin Role to jhonny@ar-mediia.com\n";
echo "===============================================\n\n";

try {
    $email = 'jhonny@ar-mediia.com';
    
    echo "1️⃣ Finding user...\n";
    $user = User::where('email', $email)->first();
    
    if (!$user) {
        echo "  ❌ User not found!\n";
        exit(1);
    }
    
    echo "  ✅ User found: ID={$user->id}, Name='{$user->name}'\n\n";
    
    echo "2️⃣ Current roles:\n";
    $currentRoles = $user->getRoleNames()->toArray();
    if (count($currentRoles) > 0) {
        foreach ($currentRoles as $role) {
            echo "    * $role\n";
        }
    } else {
        echo "    * No roles assigned\n";
    }
    
    echo "\n3️⃣ Assigning admin role with API guard...\n";
    
    // Remove existing roles first to avoid conflicts
    $user->syncRoles([]);
    echo "  ✅ Cleared existing roles\n";
    
    // Assign admin role with api guard
    $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
    if ($adminRole) {
        $user->assignRole($adminRole);
        echo "  ✅ Assigned admin role (api guard)\n";
    } else {
        echo "  ❌ Admin role with api guard not found\n";
    }
    
    echo "\n4️⃣ Verifying role assignment...\n";
    $user->refresh();
    $newRoles = $user->getRoleNames('api')->toArray();
    
    if (count($newRoles) > 0) {
        echo "  ✅ User roles after assignment:\n";
        foreach ($newRoles as $role) {
            echo "    * $role\n";
        }
        
        if (in_array('admin', $newRoles)) {
            echo "\n  🎉 SUCCESS! User now has admin role!\n";
        } else {
            echo "\n  ⚠️ Admin role not found in user roles\n";
        }
    } else {
        echo "  ❌ No roles found after assignment\n";
    }
    
    echo "\n5️⃣ Testing login with admin role...\n";
    if ($user->hasRole('admin', 'api')) {
        echo "  ✅ User has admin role (API guard)\n";
    } else {
        echo "  ❌ User does not have admin role (API guard)\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Role Assignment Complete!\n";