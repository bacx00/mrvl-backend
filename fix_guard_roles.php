<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Set up database connection
$capsule = new DB;

$capsule->addConnection([
    'driver'    => 'sqlite',
    'database'  => __DIR__ . '/database/database.sqlite',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "🔧 Fixing guard name issues...\n";

try {
    // Find the user
    $user = DB::table('users')->where('email', 'jhonny@ar-mediia.com')->first();
    if (!$user) {
        echo "❌ User not found!\n";
        exit(1);
    }
    
    echo "✅ Found user: {$user->name} (ID: {$user->id})\n";
    
    // Check current roles
    $currentRoles = DB::table('model_has_roles as mhr')
        ->join('roles as r', 'mhr.role_id', '=', 'r.id')
        ->where('mhr.model_type', 'App\\Models\\User')
        ->where('mhr.model_id', $user->id)
        ->select('r.name', 'r.guard_name', 'r.id')
        ->get();
    
    echo "📋 Current roles:\n";
    foreach ($currentRoles as $role) {
        echo "  - {$role->name} (guard: {$role->guard_name})\n";
    }
    
    // Check if we need to create API guard roles
    $apiAdminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->first();
    
    if (!$apiAdminRole) {
        echo "🔧 Creating API guard admin role...\n";
        DB::table('roles')->insert([
            'name' => 'admin',
            'guard_name' => 'api',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $apiAdminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->first();
    }
    
    // Same for moderator and user roles
    $apiModeratorRole = DB::table('roles')->where('name', 'moderator')->where('guard_name', 'api')->first();
    if (!$apiModeratorRole) {
        echo "🔧 Creating API guard moderator role...\n";
        DB::table('roles')->insert([
            'name' => 'moderator',
            'guard_name' => 'api',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    $apiUserRole = DB::table('roles')->where('name', 'user')->where('guard_name', 'api')->first();
    if (!$apiUserRole) {
        echo "🔧 Creating API guard user role...\n";
        DB::table('roles')->insert([
            'name' => 'user',
            'guard_name' => 'api',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Clear existing role assignments
    echo "🧹 Clearing existing role assignments...\n";
    DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->where('model_id', $user->id)->delete();
    
    // Assign API admin role
    echo "🔑 Assigning API admin role...\n";
    $apiAdminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->first();
    DB::table('model_has_roles')->insert([
        'role_id' => $apiAdminRole->id,
        'model_type' => 'App\\Models\\User',
        'model_id' => $user->id
    ]);
    
    // Verify assignment
    echo "✅ Verifying role assignment...\n";
    $newRoles = DB::table('model_has_roles as mhr')
        ->join('roles as r', 'mhr.role_id', '=', 'r.id')
        ->where('mhr.model_type', 'App\\Models\\User')
        ->where('mhr.model_id', $user->id)
        ->select('r.name', 'r.guard_name')
        ->get();
    
    echo "📋 New roles:\n";
    foreach ($newRoles as $role) {
        echo "  - {$role->name} (guard: {$role->guard_name})\n";
    }
    
    echo "🎉 Admin role with API guard assigned successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}