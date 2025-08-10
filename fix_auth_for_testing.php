<?php
/**
 * Authentication Fix Script for Match Moderation Testing
 * 
 * This script diagnoses and fixes common authentication issues
 * that prevent admin users from logging in for testing purposes.
 */

require_once 'vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 MRVL Authentication Diagnostic and Fix Tool\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // Step 1: Check if users table exists and has data
    echo "1️⃣ Checking Users Table...\n";
    $userCount = User::count();
    echo "   Total users in database: {$userCount}\n";
    
    if ($userCount === 0) {
        echo "   ❌ No users found in database!\n";
        return;
    }

    // Step 2: Check admin users
    echo "\n2️⃣ Checking Admin Users...\n";
    $adminUsers = User::where('role', 'admin')->get();
    echo "   Admin users found: " . $adminUsers->count() . "\n";
    
    foreach ($adminUsers as $admin) {
        echo "   - {$admin->email} (ID: {$admin->id})\n";
    }

    // Step 3: Create/update test admin user
    echo "\n3️⃣ Creating/Updating Test Admin User...\n";
    
    $testAdmin = User::where('email', 'test@mrvl.net')->first();
    
    if (!$testAdmin) {
        $testAdmin = User::create([
            'name' => 'Test Admin',
            'email' => 'test@mrvl.net',
            'password' => Hash::make('test123'),
            'role' => 'admin',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "   ✅ Created new test admin: test@mrvl.net / test123\n";
    } else {
        $testAdmin->password = Hash::make('test123');
        $testAdmin->role = 'admin';
        $testAdmin->email_verified_at = now();
        $testAdmin->save();
        echo "   ✅ Updated existing test admin: test@mrvl.net / test123\n";
    }

    // Step 4: Update existing admin password
    echo "\n4️⃣ Updating Existing Admin Password...\n";
    $existingAdmin = User::where('email', 'jhonny@ar-mediia.com')->first();
    
    if ($existingAdmin) {
        $existingAdmin->password = Hash::make('admin123');
        $existingAdmin->role = 'admin';
        $existingAdmin->email_verified_at = now();
        $existingAdmin->save();
        echo "   ✅ Updated existing admin: jhonny@ar-mediia.com / admin123\n";
    }

    // Step 5: Verify password hashing
    echo "\n5️⃣ Testing Password Hashing...\n";
    $testPassword = 'test123';
    $hashedPassword = Hash::make($testPassword);
    $isValid = Hash::check($testPassword, $hashedPassword);
    echo "   Hash check result: " . ($isValid ? "✅ PASS" : "❌ FAIL") . "\n";

    // Step 6: Check authentication configuration
    echo "\n6️⃣ Checking Authentication Configuration...\n";
    
    // Check if auth guard is properly configured
    $guard = config('auth.defaults.guard');
    echo "   Default guard: {$guard}\n";
    
    $driver = config('auth.guards.' . $guard . '.driver');
    echo "   Guard driver: {$driver}\n";
    
    $provider = config('auth.guards.' . $guard . '.provider');
    echo "   Auth provider: {$provider}\n";

    // Step 7: Test database connection
    echo "\n7️⃣ Testing Database Connection...\n";
    $dbTest = DB::table('users')->where('email', 'test@mrvl.net')->first();
    if ($dbTest) {
        echo "   ✅ Database connection working\n";
        echo "   Test user found: {$dbTest->email}\n";
    } else {
        echo "   ❌ Database connection issue\n";
    }

    // Step 8: Generate test login command
    echo "\n8️⃣ Testing Credentials...\n";
    echo "   Test these credentials:\n";
    echo "   📧 Email: test@mrvl.net\n";
    echo "   🔐 Password: test123\n";
    echo "   🔧 Alternative: jhonny@ar-mediia.com / admin123\n";

    echo "\n✅ Authentication fix completed!\n";
    echo "\n🧪 Test with curl:\n";
    echo "curl -X POST \"https://staging.mrvl.net/api/auth/login\" \\\n";
    echo "  -H \"Content-Type: application/json\" \\\n";
    echo "  -d '{\"email\": \"test@mrvl.net\", \"password\": \"test123\"}'\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Fix completed. Try running the match moderation tests again.\n";
?>