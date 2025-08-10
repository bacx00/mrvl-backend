<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $user = App\Models\User::where('email', 'admin@example.com')->first();
    if (!$user) {
        $user = App\Models\User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'status' => 'active'
        ]);
        
        // Assign role using Spatie
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('admin');
        }
        
        echo "Admin user created\n";
    } else {
        echo "Admin user already exists\n";
    }
    
    echo "User ID: " . $user->id . ", Role: " . $user->role . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}