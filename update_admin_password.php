<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    $user = User::where('email', 'admin@mrvl.net')->first();
    if (!$user) {
        echo "Admin user not found\n";
        exit(1);
    }

    $user->password = Hash::make('adminpassword');
    $user->save();
    
    echo "Admin password updated for {$user->email}\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}