<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "CLEANING DUPLICATE PLAYER NAMES\n";
echo "========================================\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

// Get all duplicate names
$duplicateNames = DB::table('players')
    ->select('name', DB::raw('COUNT(*) as count'))
    ->whereNotNull('name')
    ->where('name', '!=', '')
    ->groupBy('name')
    ->having('count', '>', 1)
    ->get();

echo "Found {$duplicateNames->count()} sets of duplicate names to fix...\n\n";

$totalFixed = 0;

foreach ($duplicateNames as $duplicate) {
    echo "Processing duplicate name: '{$duplicate->name}'\n";
    
    // Get all players with this name
    $players = DB::table('players')
        ->where('name', $duplicate->name)
        ->orderBy('id')
        ->get();
    
    echo "  Found {$players->count()} players with this name\n";
    
    // Keep the first player's name unchanged, modify others
    foreach ($players as $index => $player) {
        if ($index === 0) {
            echo "    Keeping: ID {$player->id} - {$player->username} ({$player->name})\n";
            continue;
        }
        
        // Generate a unique name by adding username suffix
        $newName = $duplicate->name . " (" . $player->username . ")";
        
        // Update the player's name
        DB::table('players')
            ->where('id', $player->id)
            ->update(['name' => $newName]);
        
        echo "    Updated: ID {$player->id} - {$player->username} -> '{$newName}'\n";
        $totalFixed++;
    }
    
    echo "\n";
}

echo "========================================\n";
echo "CLEANUP COMPLETE\n";
echo "Total names fixed: {$totalFixed}\n";
echo "========================================\n";

// Verify no duplicates remain
echo "\nVerifying cleanup...\n";

$remainingDuplicates = DB::table('players')
    ->select('name', DB::raw('COUNT(*) as count'))
    ->whereNotNull('name')
    ->where('name', '!=', '')
    ->groupBy('name')
    ->having('count', '>', 1)
    ->get();

if ($remainingDuplicates->count() === 0) {
    echo "✅ All duplicate names have been cleaned up!\n";
} else {
    echo "⚠️  Still have {$remainingDuplicates->count()} duplicate names:\n";
    foreach ($remainingDuplicates as $dup) {
        echo "   - {$dup->name} ({$dup->count} times)\n";
    }
}

echo "\nDone!\n";