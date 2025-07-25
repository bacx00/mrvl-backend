<?php

// This script will debug the exact Eloquent issue causing wrong user to be returned

require_once 'vendor/autoload.php';

// Bootstrap Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "🔍 Debugging Eloquent vs Direct DB Query Issue for jhonny@ar-mediia.com\n";
echo "=====================================================================\n\n";

$email = 'jhonny@ar-mediia.com';

echo "1️⃣ Direct SQLite Query:\n";
$directUser = DB::table('users')->where('email', $email)->first();
if ($directUser) {
    echo "  ✅ User found: ID={$directUser->id}, Name='{$directUser->name}', Email='{$directUser->email}'\n\n";
} else {
    echo "  ❌ No user found with direct query\n\n";
}

echo "2️⃣ Eloquent Query:\n";
$eloquentUser = User::where('email', $email)->first();
if ($eloquentUser) {
    echo "  ✅ User found: ID={$eloquentUser->id}, Name='{$eloquentUser->name}', Email='{$eloquentUser->email}'\n\n";
} else {
    echo "  ❌ No user found with Eloquent query\n\n";
}

echo "3️⃣ Compare Results:\n";
if ($directUser && $eloquentUser) {
    if ($directUser->id === $eloquentUser->id) {
        echo "  ✅ Both queries return the same user ID\n";
    } else {
        echo "  ❌ MISMATCH! Direct query returns ID {$directUser->id}, Eloquent returns ID {$eloquentUser->id}\n";
        echo "     This is the root cause of the authentication issue!\n";
    }
} else {
    echo "  ⚠️ One or both queries returned null\n";
}

echo "\n4️⃣ Check Database Connection:\n";
$config = config('database.connections.sqlite');
$dbPath = $config['database'];
echo "  Database path: $dbPath\n";
echo "  File exists: " . (file_exists($dbPath) ? 'Yes' : 'No') . "\n";

echo "\n5️⃣ All Users in Database (Direct Query):\n";
$allUsersDirectQuery = DB::table('users')->select('id', 'name', 'email')->get();
foreach ($allUsersDirectQuery as $user) {
    echo "  - ID={$user->id}, Name='{$user->name}', Email='{$user->email}'\n";
}

echo "\n6️⃣ All Users via Eloquent:\n";
$allUsersEloquent = User::select('id', 'name', 'email')->get();
foreach ($allUsersEloquent as $user) {
    echo "  - ID={$user->id}, Name='{$user->name}', Email='{$user->email}'\n";
}

echo "\n7️⃣ Check if there are duplicate emails:\n";
$duplicateEmails = DB::table('users')
    ->select('email', DB::raw('COUNT(*) as count'))
    ->groupBy('email')
    ->having('count', '>', 1)
    ->get();

if ($duplicateEmails->count() > 0) {
    echo "  ❌ Duplicate emails found:\n";
    foreach ($duplicateEmails as $duplicate) {
        echo "    - Email: '{$duplicate->email}' appears {$duplicate->count} times\n";
        
        // Show all users with this email
        $usersWithEmail = DB::table('users')->where('email', $duplicate->email)->get();
        foreach ($usersWithEmail as $userWithEmail) {
            echo "      * ID={$userWithEmail->id}, Name='{$userWithEmail->name}'\n";
        }
    }
} else {
    echo "  ✅ No duplicate emails found\n";
}

echo "\n8️⃣ Test Login Process Step by Step:\n";
echo "  Step 1: Find user with email '$email'\n";
$loginUser = User::where('email', $email)->first();
if ($loginUser) {
    echo "    ✅ User found: ID={$loginUser->id}, Name='{$loginUser->name}'\n";
    
    echo "  Step 2: Test password verification\n";
    $testPassword = 'password123';
    $passwordMatch = password_verify($testPassword, $loginUser->password);
    echo "    Password match: " . ($passwordMatch ? 'Yes' : 'No') . "\n";
    
    echo "  Step 3: Check roles\n";
    $roles = $loginUser->getRoleNames()->toArray();
    echo "    User roles: " . implode(', ', $roles) . "\n";
    
} else {
    echo "    ❌ User not found in login process\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Analysis Complete!\n";