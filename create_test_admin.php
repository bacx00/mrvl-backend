<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    echo "Creating test admin user...\n";
    
    // Create admin user
    $admin = User::updateOrCreate(
        ['email' => 'admin@mrvl.gg'],
        [
            'name' => 'Test Admin',
            'email' => 'admin@mrvl.gg',
            'password' => 'Admin123!@#', // Will be hashed by User model
            'role' => 'admin',
            'status' => 'active'
        ]
    );
    
    echo "✅ Admin user created/updated:\n";
    echo "   ID: {$admin->id}\n";
    echo "   Name: {$admin->name}\n"; 
    echo "   Email: {$admin->email}\n";
    echo "   Role: {$admin->role}\n";
    
    // Create moderator user
    $moderator = User::updateOrCreate(
        ['email' => 'moderator@mrvl.gg'],
        [
            'name' => 'Test Moderator',
            'email' => 'moderator@mrvl.gg', 
            'password' => 'Mod123!@#', // Will be hashed by User model
            'role' => 'moderator',
            'status' => 'active'
        ]
    );
    
    echo "✅ Moderator user created/updated:\n";
    echo "   ID: {$moderator->id}\n";
    echo "   Name: {$moderator->name}\n";
    echo "   Email: {$moderator->email}\n";
    echo "   Role: {$moderator->role}\n";
    
    // Create regular user
    $user = User::updateOrCreate(
        ['email' => 'user@mrvl.gg'],
        [
            'name' => 'Test User',
            'email' => 'user@mrvl.gg',
            'password' => 'User123!@#', // Will be hashed by User model
            'role' => 'user',
            'status' => 'active'
        ]
    );
    
    echo "✅ Regular user created/updated:\n";
    echo "   ID: {$user->id}\n";
    echo "   Name: {$user->name}\n";
    echo "   Email: {$user->email}\n";
    echo "   Role: {$user->role}\n";
    
    echo "\n🔐 Test credentials:\n";
    echo "Admin: admin@mrvl.gg / Admin123!@#\n";
    echo "Moderator: moderator@mrvl.gg / Mod123!@#\n";
    echo "User: user@mrvl.gg / User123!@#\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}