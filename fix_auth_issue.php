<?php

require_once 'bootstrap/app.php';
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "=== AUTHENTICATION DEBUGGING ===\n";
    
    // Find the user
    $user = User::where('email', 'jhonny@ar-mediia.com')->first();
    
    if (!$user) {
        echo "User not found! Creating user...\n";
        $user = User::create([
            'name' => 'Johnny',
            'email' => 'jhonny@ar-mediia.com',
            'password' => Hash::make('password123'),
            'role' => 'user'
        ]);
        echo "User created successfully\n";
    }
    
    echo "User ID: " . $user->id . "\n";
    echo "User Email: " . $user->email . "\n";
    echo "Current hash: " . $user->password . "\n";
    
    // Test current password
    $testPassword = 'password123';
    echo "\nTesting password: '$testPassword'\n";
    
    // Test with Laravel Hash
    $isValid = Hash::check($testPassword, $user->password);
    echo "Laravel Hash::check result: " . ($isValid ? 'VALID' : 'INVALID') . "\n";
    
    // Test with native PHP
    $isValidNative = password_verify($testPassword, $user->password);
    echo "Native password_verify result: " . ($isValidNative ? 'VALID' : 'INVALID') . "\n";
    
    // If invalid, let's reset the password
    if (!$isValid) {
        echo "\nPassword invalid, resetting...\n";
        
        // Create new hash
        $newHash = Hash::make($testPassword);
        echo "New hash created: $newHash\n";
        
        // Verify the new hash works
        $newHashValid = Hash::check($testPassword, $newHash);
        echo "New hash verification: " . ($newHashValid ? 'VALID' : 'INVALID') . "\n";
        
        if ($newHashValid) {
            // Update the user
            $user->update(['password' => $newHash]);
            echo "User password updated in database\n";
            
            // Verify the update worked
            $user->refresh();
            $finalCheck = Hash::check($testPassword, $user->password);
            echo "Final verification after update: " . ($finalCheck ? 'VALID' : 'INVALID') . "\n";
            
            if ($finalCheck) {
                echo "\n✅ SUCCESS: Authentication issue fixed!\n";
                echo "User 'jhonny@ar-mediia.com' can now login with password 'password123'\n";
            } else {
                echo "\n❌ ERROR: Update failed - hash still invalid\n";
            }
        } else {
            echo "\n❌ ERROR: Cannot create valid hash\n";
        }
    } else {
        echo "\n✅ Password is already valid\n";
    }
    
    // Test the authentication flow
    echo "\n=== TESTING AUTHENTICATION FLOW ===\n";
    $testUser = User::where('email', 'jhonny@ar-mediia.com')->first();
    
    if ($testUser && Hash::check('password123', $testUser->password)) {
        echo "✅ Authentication test PASSED\n";
        echo "The login should now work for jhonny@ar-mediia.com with password 'password123'\n";
    } else {
        echo "❌ Authentication test FAILED\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}