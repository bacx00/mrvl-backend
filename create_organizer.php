<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

// Create NetEase organizer account
$organizer = User::updateOrCreate(
    ['email' => 'organizer@netease.com'],
    [
        'name' => 'NetEase',
        'username' => 'netease',
        'password' => bcrypt('netease2025'),
        'role' => 'organizer',
        'email_verified_at' => now()
    ]
);

echo "Created organizer user: {$organizer->name} (ID: {$organizer->id})\n";