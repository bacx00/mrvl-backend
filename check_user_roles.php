<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

echo "🔍 Checking user roles for jhonny@ar-mediia.com...\n";

try {
    $user = App\Models\User::where('email', 'jhonny@ar-mediia.com')->first();
    
    if (!$user) {
        echo "❌ User not found\n";
        exit(1);
    }
    
    echo "✅ User found: {$user->name} (ID: {$user->id})\n";
    
    // Check if user has roles
    echo "📋 Checking roles...\n";
    $hasAdmin = $user->hasRole('admin');
    echo "Has admin role: " . ($hasAdmin ? 'Yes' : 'No') . "\n";
    
    $roleNames = $user->getRoleNames();
    echo "All roles: " . implode(', ', $roleNames->toArray()) . "\n";
    
    $rolesCount = $user->roles()->count();
    echo "Roles count: $rolesCount\n";
    
    // Check database directly
    $roleAssignments = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->where('model_has_roles.model_id', $user->id)
        ->select('roles.name', 'roles.guard_name')
        ->get();
    
    echo "Direct DB roles:\n";
    foreach ($roleAssignments as $role) {
        echo "  - {$role->name} (guard: {$role->guard_name})\n";
    }
    
    // Check guard name issue
    echo "\n🔧 Checking guard names...\n";
    $webRoles = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->where('model_has_roles.model_id', $user->id)
        ->where('roles.guard_name', 'web')
        ->count();
    
    $apiRoles = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->where('model_has_roles.model_id', $user->id)
        ->where('roles.guard_name', 'api')
        ->count();
    
    echo "Web guard roles: $webRoles\n";
    echo "API guard roles: $apiRoles\n";
    
    // Try to fix guard issue
    if ($webRoles > 0 && $apiRoles == 0) {
        echo "\n🔧 Converting web guard roles to api guard...\n";
        
        // Create API guard roles if they don't exist
        $webAdminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->first();
        if ($webAdminRole) {
            // Create or get API admin role
            $apiAdminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->first();
            if (!$apiAdminRole) {
                DB::table('roles')->insert([
                    'name' => 'admin',
                    'guard_name' => 'api',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $apiAdminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->first();
            }
            
            // Assign API admin role to user
            DB::table('model_has_roles')->insert([
                'role_id' => $apiAdminRole->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id
            ]);
            
            echo "✅ Added API admin role to user\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📋 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}