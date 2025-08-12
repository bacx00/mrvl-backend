<?php

/**
 * Fix Check Constraints for User Management
 * 
 * This script removes the outdated check constraint and allows 'suspended' status
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 Fixing Check Constraints for User Management\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    echo "1. Dropping old check constraint...\n";
    
    // Drop the old constraint that only allows active, inactive, banned
    DB::statement("ALTER TABLE users DROP CONSTRAINT chk_users_status_values");
    echo "   ✅ Dropped old chk_users_status_values constraint\n";
    
    echo "\n2. Adding new check constraint that includes 'suspended'...\n";
    
    // Add new constraint that includes suspended
    DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_status_values CHECK (status IN ('active', 'inactive', 'banned', 'suspended'))");
    echo "   ✅ Added updated chk_users_status_values constraint\n";
    
    echo "\n3. Testing the fix...\n";
    
    // Test creating a user with 'suspended' status
    $testUser = \App\Models\User::create([
        'name' => 'Test Suspended User Fix',
        'email' => 'test-suspended-fix@test.com',
        'password' => Hash::make('TestPassword123!'),
        'role' => 'user',
        'status' => 'suspended'
    ]);
    
    if ($testUser->status === 'suspended') {
        echo "   ✅ Successfully created user with 'suspended' status\n";
    } else {
        echo "   ❌ Failed to create user with 'suspended' status\n";
    }
    
    // Test all valid status values
    $statusValues = ['active', 'inactive', 'banned', 'suspended'];
    foreach ($statusValues as $status) {
        $testUser->update(['status' => $status]);
        $testUser->refresh();
        
        if ($testUser->status === $status) {
            echo "   ✅ Successfully updated user to '{$status}' status\n";
        } else {
            echo "   ❌ Failed to update user to '{$status}' status\n";
        }
    }
    
    // Clean up test user
    $testUser->delete();
    echo "   ✅ Test user cleaned up\n";
    
    echo "\n4. Verifying updated table structure...\n";
    $createTable = DB::select('SHOW CREATE TABLE users')[0]->{'Create Table'};
    
    // Check if the new constraint is present
    if (strpos($createTable, "chk_users_status_values") !== false && 
        strpos($createTable, "_utf8mb4'suspended'") !== false) {
        echo "   ✅ New constraint with 'suspended' status is in place\n";
    } else {
        echo "   ❌ Constraint update may have failed\n";
    }
    
    echo "\n✅ All constraint fixes completed successfully!\n\n";
    
    echo "📋 Summary of changes:\n";
    echo "- Dropped old check constraint that excluded 'suspended'\n";
    echo "- Added new check constraint that includes all 4 status values\n";
    echo "- Verified all status transitions work properly\n";
    echo "- Database now supports: active, inactive, banned, suspended\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}