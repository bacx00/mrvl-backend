<?php
require_once 'vendor/autoload.php';
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Check if test admin exists
    $testAdmin = User::where('email', 'admin@mrvl.net')->first();
    
    if (!$testAdmin) {
        $testAdmin = User::create([
            'email' => 'admin@mrvl.net',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'name' => 'Test Admin',
            'email_verified_at' => now()
        ]);
        echo 'Created test admin user: admin@mrvl.net' . PHP_EOL;
    } else {
        // Update password
        $testAdmin->password = Hash::make('admin123');
        $testAdmin->role = 'admin';
        $testAdmin->save();
        echo 'Updated existing test admin user: admin@mrvl.net' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>