<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== FIXING NORTH AMERICA FLAGS ===\n\n";

// First, let's see what flags are currently set for NA teams
echo "1. Checking current NA team flags...\n";
$naTeams = Team::where('region', 'NA')->get();

foreach ($naTeams as $team) {
    echo "   {$team->name}: {$team->country} - {$team->country_flag}\n";
}

// Fix teams without proper country assignment
echo "\n2. Fixing NA teams without proper countries...\n";

// Map of known NA teams to their countries
$teamCountryMap = [
    '100 Thieves' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'Luminosity Gaming NA' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'Sentinels' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'DarkZero' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'FlyQuest' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'Steam Engines' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'Team Nemesis' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'ENVY' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'Solaris' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'YFP' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'InControl' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'Supernova' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'NTMR' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'Arrival Seven' => ['country' => 'Canada', 'flag' => '🇨🇦'],
    'Cumberland University' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'St. Clair College' => ['country' => 'Canada', 'flag' => '🇨🇦'],
    'Cafe Noir' => ['country' => 'Canada', 'flag' => '🇨🇦'],
    'Citadel Gaming' => ['country' => 'United States', 'flag' => '🇺🇸'],
    'DUSTY' => ['country' => 'United States', 'flag' => '🇺🇸'],
];

foreach ($naTeams as $team) {
    if (isset($teamCountryMap[$team->name])) {
        $countryData = $teamCountryMap[$team->name];
        $team->update([
            'country' => $countryData['country'],
            'country_flag' => $countryData['flag']
        ]);
        echo "   ✓ Updated {$team->name} to {$countryData['country']} {$countryData['flag']}\n";
    } elseif (empty($team->country) || $team->country === 'International' || $team->country === 'North America') {
        // Default NA teams to United States if not specified
        $team->update([
            'country' => 'United States',
            'country_flag' => '🇺🇸'
        ]);
        echo "   ✓ Updated {$team->name} to United States 🇺🇸 (default)\n";
    }
}

// Fix NA players
echo "\n3. Fixing NA player flags...\n";
$naPlayers = Player::where('region', 'NA')
    ->where(function($query) {
        $query->whereNull('country')
            ->orWhere('country', 'North America')
            ->orWhere('country', 'International')
            ->orWhere('country_flag', '');
    })
    ->get();

$updatedCount = 0;
foreach ($naPlayers as $player) {
    $team = Team::find($player->team_id);
    if ($team && $team->country && $team->country !== 'North America') {
        $player->update([
            'country' => $team->country,
            'country_flag' => $team->country_flag
        ]);
        $updatedCount++;
    } else {
        // Default to United States for NA players without country
        $player->update([
            'country' => 'United States',
            'country_flag' => '🇺🇸'
        ]);
        $updatedCount++;
    }
}

echo "   ✓ Updated $updatedCount NA players\n";

// Verify no "North America" flags remain
echo "\n4. Verifying all flags are correct...\n";

$badTeamFlags = Team::where('country', 'North America')
    ->orWhere('country_flag', 'LIKE', '%North%')
    ->count();

$badPlayerFlags = Player::where('country', 'North America')
    ->orWhere('country_flag', 'LIKE', '%North%')
    ->count();

echo "   Teams with 'North America' country: $badTeamFlags\n";
echo "   Players with 'North America' country: $badPlayerFlags\n";

// Show distribution of NA teams by country
echo "\n5. NA Teams by Country:\n";
Team::where('region', 'NA')
    ->selectRaw('country, country_flag, count(*) as count')
    ->groupBy('country', 'country_flag')
    ->get()
    ->each(function($group) {
        echo "   {$group->country} {$group->country_flag}: {$group->count} teams\n";
    });

echo "\n✓ North America flags fixed!\n";