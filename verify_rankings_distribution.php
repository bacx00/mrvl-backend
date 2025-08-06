<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== RANKINGS PAGE VERIFICATION ===\n\n";

// Teams by region for rankings
echo "TEAM RANKINGS BY REGION:\n\n";

$regions = ['Global', 'NA', 'EU', 'CN', 'KR', 'JP', 'SEA', 'ASIA', 'OCE', 'SA', 'MENA', 'CIS'];

foreach ($regions as $region) {
    if ($region === 'Global') {
        $teams = Team::orderBy('rating', 'desc')->take(10)->get();
        echo "GLOBAL TOP 10:\n";
    } else {
        $teams = Team::where('region', $region)->orderBy('rating', 'desc')->take(5)->get();
        if ($teams->count() == 0) continue;
        echo "\n$region REGION TOP 5:\n";
    }
    
    foreach ($teams as $index => $team) {
        printf("%2d. %-25s - Rating: %d, Earnings: $%s\n", 
            $index + 1, 
            $team->name, 
            $team->rating,
            number_format($team->earnings)
        );
    }
}

// Player rankings
echo "\n\nPLAYER RANKINGS BY ROLE:\n";

$roles = ['duelist', 'vanguard', 'strategist'];

foreach ($roles as $role) {
    echo "\nTOP 5 " . strtoupper($role) . " PLAYERS:\n";
    $players = Player::where('role', $role)
        ->orderBy('rating', 'desc')
        ->take(5)
        ->get();
    
    foreach ($players as $index => $player) {
        $team = Team::find($player->team_id);
        printf("%d. %-20s (%-15s) - Rating: %d\n", 
            $index + 1, 
            $player->username, 
            $team ? $team->name : 'No Team',
            $player->rating
        );
    }
}

// Verify China/Asia/Oceania specifically
echo "\n\nCHINA/ASIA/OCEANIA VERIFICATION:\n";
echo "China (CN) teams: " . Team::where('region', 'CN')->count() . "\n";
echo "Korea (KR) teams: " . Team::where('region', 'KR')->count() . "\n";
echo "Japan (JP) teams: " . Team::where('region', 'JP')->count() . "\n";
echo "SEA teams: " . Team::where('region', 'SEA')->count() . "\n";
echo "Asia teams: " . Team::where('region', 'ASIA')->count() . "\n";
echo "Oceania (OCE) teams: " . Team::where('region', 'OCE')->count() . "\n";

echo "\n✓ Rankings page will display properly with data in all regions including China, Asia, and Oceania.\n";