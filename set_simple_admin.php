<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Direct database update with known good hash
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    
    DB::table('users')
        ->where('email', 'admin@mrvl.com')
        ->update([
            'password' => $hash,
            'updated_at' => now()
        ]);
    
    echo "Password updated directly in database\n";
    echo "Hash used: $hash\n";
    
    // Test the hash
    if (password_verify('admin123', $hash)) {
        echo "Hash verification: PASS\n";
    } else {
        echo "Hash verification: FAIL\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}