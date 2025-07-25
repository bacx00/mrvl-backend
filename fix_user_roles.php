<?php

// Simple script to fix user roles
require_once 'vendor/autoload.php';

// Load Laravel environment
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = __DIR__ . '/database/database.sqlite';

use Illuminate\Database\Capsule\Manager as DB;
use PDO;

// Set up database connection
$capsule = new DB;

$capsule->addConnection([
    'driver'    => 'sqlite',
    'database'  => __DIR__ . '/database/database.sqlite',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "🔧 Fixing user roles...\n";

try {
    // First, check if tables exist
    $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table';");
    $tableNames = array_column($tables, 'name');
    
    echo "📋 Available tables: " . implode(', ', $tableNames) . "\n";
    
    // Check users table
    if (!in_array('users', $tableNames)) {
        echo "❌ Users table not found!\n";
        exit(1);
    }
    
    // Find the user
    $users = DB::table('users')->select('id', 'name', 'email')->get();
    echo "📋 Available users:\n";
    foreach ($users as $user) {
        echo "  - {$user->id}: {$user->name} ({$user->email})\n";
    }
    
    $user = DB::table('users')->where('email', 'jhonny@ar-mediia.com')->first();
    if (!$user) {
        echo "❌ User jhonny@ar-mediia.com not found!\n";
        exit(1);
    }
    
    echo "✅ Found user: {$user->name} (ID: {$user->id})\n";
    
    // Check if roles table exists
    if (!in_array('roles', $tableNames)) {
        echo "📝 Creating roles table...\n";
        DB::statement("
            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(125) NOT NULL,
                guard_name VARCHAR(125) NOT NULL DEFAULT 'api',
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                UNIQUE(name, guard_name)
            )
        ");
    }
    
    // Check if model_has_roles table exists
    if (!in_array('model_has_roles', $tableNames)) {
        echo "📝 Creating model_has_roles table...\n";
        DB::statement("
            CREATE TABLE IF NOT EXISTS model_has_roles (
                role_id INTEGER NOT NULL,
                model_type VARCHAR(125) NOT NULL,
                model_id INTEGER NOT NULL,
                PRIMARY KEY (role_id, model_id, model_type),
                FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
            )
        ");
    }
    
    // Create roles
    $roles = ['admin', 'moderator', 'user'];
    foreach ($roles as $roleName) {
        DB::table('roles')->insertOrIgnore([
            'name' => $roleName,
            'guard_name' => 'api',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "✅ Role '{$roleName}' created/exists\n";
    }
    
    // Get admin role ID
    $adminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->first();
    if (!$adminRole) {
        echo "❌ Admin role not found!\n";
        exit(1);
    }
    
    // Remove existing role assignments for this user
    DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->where('model_id', $user->id)->delete();
    
    // Assign admin role
    DB::table('model_has_roles')->insert([
        'role_id' => $adminRole->id,
        'model_type' => 'App\\Models\\User',
        'model_id' => $user->id
    ]);
    
    echo "🔑 Assigned admin role to {$user->name}\n";
    
    // Verify assignment
    $userRoles = DB::table('model_has_roles as mhr')
        ->join('roles as r', 'mhr.role_id', '=', 'r.id')
        ->where('mhr.model_type', 'App\\Models\\User')
        ->where('mhr.model_id', $user->id)
        ->pluck('r.name');
    
    echo "✅ User roles: " . implode(', ', $userRoles->toArray()) . "\n";
    echo "🎉 Role assignment completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📋 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}