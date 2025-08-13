<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$admin = User::where('role', 'admin')->first();
if ($admin) {
    echo "Found admin: " . $admin->email . "\n";
    $tokenResult = $admin->createToken('tournament-live-scoring-test');
    $token = $tokenResult->accessToken;
    echo "Generated token: " . $token . "\n";
} else {
    echo "No admin user found\n";
}