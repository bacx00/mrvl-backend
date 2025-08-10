<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Authentication System Integration Test with User Profile System\n";
echo "==============================================================\n\n";

try {
    // Test 1: Check existing users
    echo "1. CHECKING EXISTING USERS...\n";
    $users = DB::table('users')->select('id', 'name', 'email', 'role')->limit(5)->get();
    echo "Found " . count($users) . " users:\n";
    foreach($users as $user) {
        echo "  - ID: {$user->id}, Email: {$user->email}, Role: " . ($user->role ?? 'user') . "\n";
    }
    echo "\n";

    // Test 2: Create test users if needed
    echo "2. CREATING TEST USERS...\n";
    
    $testUsers = [
        ['name' => 'Test Admin', 'email' => 'admin@test.com', 'role' => 'admin'],
        ['name' => 'Test Moderator', 'email' => 'mod@test.com', 'role' => 'moderator'],
        ['name' => 'Test User', 'email' => 'user@test.com', 'role' => 'user']
    ];
    
    foreach($testUsers as $userData) {
        $existing = DB::table('users')->where('email', $userData['email'])->first();
        if (!$existing) {
            $userId = DB::table('users')->insertGetId([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => bcrypt('testpass123'),
                'role' => $userData['role'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "Created {$userData['role']} user: {$userData['email']} (ID: {$userId})\n";
        } else {
            echo "User already exists: {$userData['email']} (ID: {$existing->id})\n";
        }
    }

    // Test 3: Password security test
    echo "\n3. PASSWORD SECURITY TEST...\n";
    $plainPassword = 'testpass123';
    $hashedPassword = bcrypt($plainPassword);
    $isValid = Hash::check($plainPassword, $hashedPassword);
    echo "Password hashing: " . ($isValid ? 'PASS' : 'FAIL') . "\n";
    
    // Test different password attempts
    $wrongPassword = 'wrongpass';
    $isWrongValid = Hash::check($wrongPassword, $hashedPassword);
    echo "Wrong password rejection: " . (!$isWrongValid ? 'PASS' : 'FAIL') . "\n";

    // Test 4: Database schema validation
    echo "\n4. DATABASE SCHEMA VALIDATION...\n";
    $requiredTables = ['users', 'oauth_access_tokens', 'oauth_clients'];
    $optionalTables = ['password_resets', 'personal_access_tokens'];
    
    echo "Required tables:\n";
    foreach($requiredTables as $table) {
        $exists = Schema::hasTable($table);
        echo "  {$table}: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
    }
    
    echo "Optional tables:\n";
    foreach($optionalTables as $table) {
        $exists = Schema::hasTable($table);
        echo "  {$table}: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
    }

    // Test 5: User table columns
    echo "\nUser table columns:\n";
    if (Schema::hasTable('users')) {
        $requiredColumns = ['name', 'email', 'password', 'role'];
        $profileColumns = ['avatar', 'last_login', 'hero_flair', 'team_flair_id', 'show_hero_flair', 'show_team_flair'];
        
        echo "  Required auth columns:\n";
        foreach($requiredColumns as $column) {
            $hasColumn = Schema::hasColumn('users', $column);
            echo "    {$column}: " . ($hasColumn ? 'EXISTS' : 'MISSING') . "\n";
        }
        
        echo "  Profile integration columns:\n";
        foreach($profileColumns as $column) {
            $hasColumn = Schema::hasColumn('users', $column);
            echo "    {$column}: " . ($hasColumn ? 'EXISTS' : 'MISSING') . "\n";
        }
    }

    // Test 6: Role-based access control data
    echo "\n5. ROLE-BASED ACCESS CONTROL TEST...\n";
    $roleStats = DB::table('users')
        ->select('role', DB::raw('COUNT(*) as count'))
        ->groupBy('role')
        ->get();
    
    echo "User role distribution:\n";
    foreach($roleStats as $stat) {
        echo "  {$stat->role}: {$stat->count} users\n";
    }

    // Test 7: Profile system integration
    echo "\n6. PROFILE SYSTEM INTEGRATION...\n";
    $profileTables = ['teams', 'marvel_rivals_heroes'];
    foreach($profileTables as $table) {
        $exists = Schema::hasTable($table);
        echo "  {$table} (for profile flairs): " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
    }

    // Test 8: Session and token management tables
    echo "\n7. SESSION AND TOKEN MANAGEMENT...\n";
    if (Schema::hasTable('oauth_access_tokens')) {
        $activeTokens = DB::table('oauth_access_tokens')
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->count();
        echo "Active OAuth tokens: {$activeTokens}\n";
    }

    echo "\n✅ Authentication system integration test completed successfully!\n";
    echo "Test users created with password: 'testpass123'\n";
    echo "- admin@test.com (admin role)\n";
    echo "- mod@test.com (moderator role)\n";
    echo "- user@test.com (user role)\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}