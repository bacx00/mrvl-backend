<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $user = App\Models\User::where('email', 'admin@example.com')->first();
    
    if ($user) {
        $user->update([
            'password' => Hash::make('admin123')
        ]);
        echo "Admin password reset to 'admin123'\n";
        echo "User: " . $user->name . " (" . $user->email . ")\n";
        echo "Role: " . $user->role . "\n";
    } else {
        echo "Admin user not found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}