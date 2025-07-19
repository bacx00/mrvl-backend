<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Fix avatar paths for users
$users = DB::table('users')
    ->whereNotNull('avatar')
    ->where('avatar', 'LIKE', '/storage/heroes/%')
    ->get();

echo "Found " . count($users) . " users with incorrect avatar paths\n";

foreach ($users as $user) {
    // Extract hero name from path
    preg_match('/\/storage\/heroes\/(.+)\.webp/', $user->avatar, $matches);
    if (isset($matches[1])) {
        $heroSlug = $matches[1];
        $newPath = "/images/heroes/portraits/{$heroSlug}.png";
        
        DB::table('users')
            ->where('id', $user->id)
            ->update(['avatar' => $newPath]);
            
        echo "Updated user {$user->id}: {$user->avatar} -> {$newPath}\n";
    }
}

// Create heroes directory structure
$publicPath = __DIR__ . '/public/images/heroes';
if (!file_exists($publicPath)) {
    mkdir($publicPath, 0755, true);
    mkdir($publicPath . '/portraits', 0755, true);
    mkdir($publicPath . '/icons', 0755, true);
    echo "\nCreated heroes directory structure\n";
}

echo "\nDone! Avatar paths have been fixed.\n";