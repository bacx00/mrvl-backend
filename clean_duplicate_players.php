<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Cleaning duplicate players...\n\n";

// First, let's identify duplicates by username
$duplicates = DB::table('players')
    ->select('username', DB::raw('COUNT(*) as count'))
    ->groupBy('username')
    ->having('count', '>', 1)
    ->get();

echo "Found " . $duplicates->count() . " usernames with duplicates\n";

foreach ($duplicates as $duplicate) {
    echo "\nProcessing username: {$duplicate->username} ({$duplicate->count} occurrences)\n";
    
    // Get all players with this username
    $players = DB::table('players')
        ->where('username', $duplicate->username)
        ->orderBy('rating', 'desc')
        ->orderBy('id', 'asc')
        ->get();
    
    // Keep the first one (highest rating, oldest ID)
    $keepPlayer = $players->first();
    echo "  Keeping player ID: {$keepPlayer->id} (Rating: {$keepPlayer->rating})\n";
    
    // Delete the rest
    foreach ($players->skip(1) as $player) {
        echo "  Deleting player ID: {$player->id} (Rating: {$player->rating})\n";
        DB::table('players')->where('id', $player->id)->delete();
    }
}

// Clean up players with no team
$orphanedPlayers = DB::table('players')
    ->whereNull('team_id')
    ->orWhere('team_id', 0)
    ->count();

echo "\nFound {$orphanedPlayers} orphaned players (no team)\n";

// Update team_position for existing players if null
$updatedPositions = DB::table('players')
    ->whereNull('team_position')
    ->where('status', 'active')
    ->update(['team_position' => 'player']);

echo "Updated {$updatedPositions} players with null team_position to 'player'\n";

// Fix any players with invalid team_position values
$invalidPositions = DB::table('players')
    ->whereNotIn('team_position', ['player', 'coach', 'assistant_coach', 'manager', 'analyst', 'bench', 'substitute', 'inactive'])
    ->whereNotNull('team_position')
    ->update(['team_position' => 'player']);

echo "Fixed {$invalidPositions} players with invalid team_position values\n";

echo "\nDuplicate cleanup complete!\n";