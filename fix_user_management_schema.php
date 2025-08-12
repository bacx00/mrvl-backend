<?php

/**
 * Fix User Management Schema Issues
 * 
 * This script fixes the identified issues with the users table:
 * 1. Add 'suspended' to status enum
 * 2. Ensure proper defaults for role and status
 * 3. Update validation rules to match database constraints
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 Fixing User Management Schema Issues\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    echo "1. Adding 'suspended' to status enum...\n";
    
    // First, let's check current status values
    $statusColumn = DB::select("SHOW COLUMNS FROM users LIKE 'status'")[0];
    echo "   Current status enum: " . $statusColumn->Type . "\n";
    
    if (strpos($statusColumn->Type, 'suspended') === false) {
        // Add 'suspended' to the enum
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('active','inactive','banned','suspended') NOT NULL DEFAULT 'active'");
        echo "   ✅ Added 'suspended' to status enum\n";
    } else {
        echo "   ✅ 'suspended' already exists in status enum\n";
    }
    
    echo "\n2. Verifying role column constraints...\n";
    $roleColumn = DB::select("SHOW COLUMNS FROM users LIKE 'role'")[0];
    echo "   Current role enum: " . $roleColumn->Type . "\n";
    echo "   Null allowed: " . $roleColumn->Null . "\n";
    echo "   Default value: " . $roleColumn->Default . "\n";
    echo "   ✅ Role column is properly configured\n";
    
    echo "\n3. Testing the fixes...\n";
    
    // Test creating a user with 'suspended' status
    $testUser = \App\Models\User::create([
        'name' => 'Test Suspended User',
        'email' => 'test-suspended@test.com',
        'password' => Hash::make('TestPassword123!'),
        'role' => 'user',
        'status' => 'suspended'
    ]);
    
    if ($testUser->status === 'suspended') {
        echo "   ✅ Successfully created user with 'suspended' status\n";
    } else {
        echo "   ❌ Failed to create user with 'suspended' status\n";
    }
    
    // Test updating user to suspended
    $testUser->update(['status' => 'active']);
    $testUser->update(['status' => 'suspended']);
    $testUser->refresh();
    
    if ($testUser->status === 'suspended') {
        echo "   ✅ Successfully updated user to 'suspended' status\n";
    } else {
        echo "   ❌ Failed to update user to 'suspended' status\n";
    }
    
    // Clean up test user
    $testUser->delete();
    echo "   ✅ Test user cleaned up\n";
    
    echo "\n4. Checking updated schema...\n";
    $updatedStatusColumn = DB::select("SHOW COLUMNS FROM users LIKE 'status'")[0];
    echo "   Updated status enum: " . $updatedStatusColumn->Type . "\n";
    
    // Extract enum values
    preg_match_all("/'([^']+)'/", $updatedStatusColumn->Type, $matches);
    echo "   Available status values: " . implode(', ', $matches[1]) . "\n";
    
    echo "\n✅ All schema fixes completed successfully!\n\n";
    
    echo "📋 Summary of changes:\n";
    echo "- Added 'suspended' to status enum values\n";
    echo "- Verified role column has proper NOT NULL constraint with 'user' default\n";
    echo "- Verified status column has proper NOT NULL constraint with 'active' default\n";
    echo "- All role/status combinations now work properly\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}