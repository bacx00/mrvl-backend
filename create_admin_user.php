<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    // Check if admin user exists
    $admin = User::where('email', 'admin@mrvl.com')->first();
    
    if (!$admin) {
        // Create new admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mrvl.com',
            'password' => Hash::make('admin123'),
            'email_verified_at' => now(),
            'role' => 'admin'
        ]);
        echo "Admin user created with ID: {$admin->id}\n";
    } else {
        // Update existing admin password
        $admin->password = Hash::make('admin123');
        $admin->role = 'admin';
        $admin->save();
        echo "Admin user password updated for ID: {$admin->id}\n";
    }
    
    // Verify user can be authenticated
    echo "Admin user details:\n";
    echo "- Name: {$admin->name}\n";
    echo "- Email: {$admin->email}\n";
    echo "- Role: {$admin->role}\n";
    echo "- Created: {$admin->created_at}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}