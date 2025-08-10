<?php
require_once 'vendor/autoload.php';

// Initialize Laravel app
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    // Get existing admin user
    $admin = User::where('email', 'admin@example.com')->first();
    
    if ($admin) {
        // Set a known password
        $admin->update(['password' => Hash::make('admin123')]);
        echo "✅ Admin password updated: admin@example.com / admin123\n";
        echo "Admin ID: " . $admin->id . "\n";
        echo "Admin Role: " . $admin->role . "\n";
    } else {
        echo "❌ Admin user not found\n";
    }
    
    // Also create a test admin if needed
    $testAdmin = User::where('email', 'test@mrvl.gg')->first();
    if (!$testAdmin) {
        $testAdmin = User::create([
            'name' => 'Test Admin',
            'email' => 'test@mrvl.gg',
            'password' => Hash::make('test123'),
            'role' => 'admin',
            'email_verified_at' => now()
        ]);
        echo "✅ Test admin created: test@mrvl.gg / test123\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}