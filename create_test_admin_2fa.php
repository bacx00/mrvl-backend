<?php
require_once "vendor/autoload.php";

$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Create test admin user
$admin = User::updateOrCreate(
    ["email" => "admin@test.com"],
    [
        "name" => "Test Admin",
        "email" => "admin@test.com", 
        "password" => Hash::make("password123"),
        "role" => "admin"
    ]
);

echo "✅ Test admin user created/updated:\n";
echo "Email: admin@test.com\n";
echo "Password: password123\n";
echo "Role: admin\n";
echo "ID: " . $admin->id . "\n";
