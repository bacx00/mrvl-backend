<?php
require_once 'vendor/autoload.php';

// Initialize Laravel app
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    // Check if admin exists
    $admin = User::where('email', 'admin@mrvl.gg')->first();
    
    if (!$admin) {
        $admin = User::create([
            'name' => 'MRVL Admin',
            'email' => 'admin@mrvl.gg',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'email_verified_at' => now()
        ]);
        echo "✅ Admin user created: " . $admin->email . "\n";
    } else {
        // Update password just in case
        $admin->update(['password' => Hash::make('Password123!')]);
        echo "✅ Admin user exists: " . $admin->email . " (password updated)\n";
    }
    
    echo "Admin ID: " . $admin->id . "\n";
    echo "Admin Role: " . $admin->role . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}