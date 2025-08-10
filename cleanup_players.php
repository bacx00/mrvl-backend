<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== Player Cleanup ===" . PHP_EOL;

// Delete all players without team assignments
$orphanedPlayers = Player::whereNull('team_id')->count();
if ($orphanedPlayers > 0) {
    Player::whereNull('team_id')->delete();
    echo "Deleted {$orphanedPlayers} orphaned players" . PHP_EOL;
}

// Now verify we have exactly 6 players per team
$teams = Team::withCount('players')->get();
$expectedTotal = 61 * 6;
$actualTotal = Player::count();

echo "Expected total players: {$expectedTotal}" . PHP_EOL;
echo "Actual total players: {$actualTotal}" . PHP_EOL;

if ($actualTotal == $expectedTotal) {
    echo "✅ Player count is correct!" . PHP_EOL;
} else {
    echo "❌ Player count mismatch" . PHP_EOL;
}

// Final role check
$roleStats = [
    'duelists' => Player::where('role', 'Duelist')->count(),
    'strategists' => Player::where('role', 'Strategist')->count(),
    'vanguards' => Player::where('role', 'Vanguard')->count()
];

echo PHP_EOL . "Final role distribution:" . PHP_EOL;
echo "Duelists: {$roleStats['duelists']} (expected: 122)" . PHP_EOL;
echo "Strategists: {$roleStats['strategists']} (expected: 122)" . PHP_EOL;
echo "Vanguards: {$roleStats['vanguards']} (expected: 122)" . PHP_EOL;

echo PHP_EOL . "Player cleanup completed!" . PHP_EOL;