<?php

// Check MySQL database for users

require_once 'vendor/autoload.php';

// Bootstrap Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "🔍 Checking MySQL Database Users\n";
echo "================================\n\n";

try {
    echo "1️⃣ Database Connection Info:\n";
    $config = config('database.connections.' . config('database.default'));
    echo "  Connection: " . config('database.default') . "\n";
    echo "  Driver: " . $config['driver'] . "\n";
    echo "  Host: " . $config['host'] . "\n";
    echo "  Database: " . $config['database'] . "\n";
    echo "  Username: " . $config['username'] . "\n\n";

    echo "2️⃣ Test Database Connection:\n";
    DB::connection()->getPdo();
    echo "  ✅ Database connection successful\n\n";

    echo "3️⃣ All Users in MySQL Database:\n";
    $users = User::select('id', 'name', 'email', 'created_at')->get();
    
    if ($users->count() > 0) {
        foreach ($users as $user) {
            echo "  - ID={$user->id}, Name='{$user->name}', Email='{$user->email}', Created={$user->created_at}\n";
        }
    } else {
        echo "  ❌ No users found in database\n";
    }

    echo "\n4️⃣ Search for jhonny@ar-mediia.com:\n";
    $targetUser = User::where('email', 'jhonny@ar-mediia.com')->first();
    if ($targetUser) {
        echo "  ✅ User found: ID={$targetUser->id}, Name='{$targetUser->name}'\n";
        
        echo "  🔍 User roles:\n";
        $roles = $targetUser->getRoleNames()->toArray();
        if (count($roles) > 0) {
            foreach ($roles as $role) {
                echo "    * $role\n";
            }
        } else {
            echo "    * No roles assigned\n";
        }
    } else {
        echo "  ❌ User jhonny@ar-mediia.com not found in MySQL database\n";
    }

    echo "\n5️⃣ Check Available Roles:\n";
    $roles = DB::table('roles')->get();
    foreach ($roles as $role) {
        echo "  - Role: '{$role->name}' (Guard: {$role->guard_name})\n";
    }

} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "MySQL Database Check Complete!\n";