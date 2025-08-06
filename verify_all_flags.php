<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== VERIFYING ALL FLAGS ===\n\n";

// 1. Check for any missing flags
echo "1. Checking for missing flags...\n";
$teamsWithoutFlags = Team::where(function($query) {
    $query->whereNull('country_flag')
        ->orWhere('country_flag', '');
})->count();

$playersWithoutFlags = Player::where(function($query) {
    $query->whereNull('country_flag')
        ->orWhere('country_flag', '');
})->count();

echo "   Teams without flags: $teamsWithoutFlags\n";
echo "   Players without flags: $playersWithoutFlags\n";

// 2. Show team flag distribution
echo "\n2. Team Flag Distribution:\n";
Team::selectRaw('country, country_flag, COUNT(*) as count')
    ->whereNotNull('country_flag')
    ->where('country_flag', '!=', '')
    ->groupBy('country', 'country_flag')
    ->orderBy('count', 'desc')
    ->get()
    ->each(function($group) {
        printf("   %-25s %s : %2d teams\n", $group->country, $group->country_flag, $group->count);
    });

// 3. Show regional coverage
echo "\n3. Regional Flag Coverage:\n";
$regions = ['NA', 'EU', 'CN', 'KR', 'JP', 'SEA', 'OCE', 'SA', 'MENA', 'CIS', 'ASIA'];
foreach ($regions as $region) {
    $totalTeams = Team::where('region', $region)->count();
    $teamsWithFlags = Team::where('region', $region)
        ->whereNotNull('country_flag')
        ->where('country_flag', '!=', '')
        ->count();
    
    printf("   %-5s: %2d/%2d teams have flags", $region, $teamsWithFlags, $totalTeams);
    
    if ($teamsWithFlags < $totalTeams) {
        echo " ⚠️";
    } else {
        echo " ✓";
    }
    echo "\n";
}

// 4. Check for specific important teams
echo "\n4. Important Teams Verification:\n";
$importantTeams = [
    'Virtus.pro' => 'Russia 🇷🇺',
    '100 Thieves' => 'United States 🇺🇸',
    'Fnatic' => 'United Kingdom 🇬🇧',
    'LGD Gaming' => 'China 🇨🇳',
    'Crazy Raccoon' => 'South Korea 🇰🇷',
    'Gen.G Esports' => 'South Korea 🇰🇷',
    'Paper Rex' => 'Singapore 🇸🇬',
    'LOUD' => 'Brazil 🇧🇷'
];

foreach ($importantTeams as $teamName => $expectedFlag) {
    $team = Team::where('name', $teamName)->first();
    if ($team) {
        $actualFlag = $team->country . ' ' . $team->country_flag;
        $status = ($actualFlag === $expectedFlag) ? '✓' : '✗';
        echo "   {$teamName}: {$actualFlag} {$status}\n";
    } else {
        echo "   {$teamName}: NOT FOUND ✗\n";
    }
}

// 5. Show player nationality distribution (top 10)
echo "\n5. Player Nationality Distribution (Top 10):\n";
Player::selectRaw('country, country_flag, COUNT(*) as count')
    ->whereNotNull('country_flag')
    ->where('country_flag', '!=', '')
    ->groupBy('country', 'country_flag')
    ->orderBy('count', 'desc')
    ->limit(10)
    ->get()
    ->each(function($group) {
        printf("   %-25s %s : %3d players\n", $group->country, $group->country_flag, $group->count);
    });

// 6. Final summary
echo "\n6. Summary:\n";
$totalTeams = Team::count();
$totalPlayers = Player::count();
$teamsWithProperFlags = Team::whereNotNull('country_flag')
    ->where('country_flag', '!=', '')
    ->count();
$playersWithProperFlags = Player::whereNotNull('country_flag')
    ->where('country_flag', '!=', '')
    ->count();

echo "   Total teams: $totalTeams\n";
echo "   Teams with flags: $teamsWithProperFlags (" . round($teamsWithProperFlags/$totalTeams*100) . "%)\n";
echo "   Total players: $totalPlayers\n";
echo "   Players with flags: $playersWithProperFlags (" . round($playersWithProperFlags/$totalPlayers*100) . "%)\n";

if ($teamsWithProperFlags == $totalTeams && $playersWithProperFlags == $totalPlayers) {
    echo "\n✅ ALL FLAGS ARE PROPERLY SET!\n";
} else {
    echo "\n⚠️  Some flags are still missing.\n";
}

echo "\n=== FLAG VERIFICATION COMPLETE ===\n";