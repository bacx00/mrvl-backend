<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    $user = User::where('email', 'admin@mrvl.com')->first();
    
    if (!$user) {
        echo "User not found\n";
        exit;
    }
    
    // Set new password using bcrypt (like in register method)
    $user->password = bcrypt('admin123');
    $user->save();
    
    echo "Password updated for user ID: {$user->id}\n";
    
    // Verify password works
    $check = Hash::check('admin123', $user->password);
    echo "Password verification: " . ($check ? 'PASS' : 'FAIL') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}