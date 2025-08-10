<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== Role Balance Check ===" . PHP_EOL;

$teams = Team::with('players')->get();
$totalIssues = 0;

foreach($teams as $team) {
    $roles = $team->players->countBy('role');
    $duelists = $roles->get('Duelist', 0);
    $strategists = $roles->get('Strategist', 0);
    $vanguards = $roles->get('Vanguard', 0);
    
    if ($duelists != 2 || $strategists != 2 || $vanguards != 2) {
        echo "{$team->name}: D{$duelists} S{$strategists} V{$vanguards}" . PHP_EOL;
        $totalIssues++;
    }
}

if ($totalIssues == 0) {
    echo "✅ All teams have balanced roles!" . PHP_EOL;
} else {
    echo "❌ Found {$totalIssues} teams with role imbalances" . PHP_EOL;
}

$overallStats = [
    'duelists' => Player::where('role', 'Duelist')->count(),
    'strategists' => Player::where('role', 'Strategist')->count(), 
    'vanguards' => Player::where('role', 'Vanguard')->count()
];

echo PHP_EOL . "Overall role distribution:" . PHP_EOL;
echo "Duelists: {$overallStats['duelists']} (expected: 122)" . PHP_EOL;
echo "Strategists: {$overallStats['strategists']} (expected: 122)" . PHP_EOL;
echo "Vanguards: {$overallStats['vanguards']} (expected: 122)" . PHP_EOL;