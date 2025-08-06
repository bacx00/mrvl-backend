<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== UPDATING ALL FLAGS ===\n\n";

// Country to flag mapping
$countryFlags = [
    'United States' => '🇺🇸',
    'Canada' => '🇨🇦',
    'Mexico' => '🇲🇽',
    'Brazil' => '🇧🇷',
    'Argentina' => '🇦🇷',
    'Chile' => '🇨🇱',
    'United Kingdom' => '🇬🇧',
    'Germany' => '🇩🇪',
    'France' => '🇫🇷',
    'Spain' => '🇪🇸',
    'Italy' => '🇮🇹',
    'Netherlands' => '🇳🇱',
    'Belgium' => '🇧🇪',
    'Sweden' => '🇸🇪',
    'Denmark' => '🇩🇰',
    'Norway' => '🇳🇴',
    'Finland' => '🇫🇮',
    'Poland' => '🇵🇱',
    'Russia' => '🇷🇺',
    'Ukraine' => '🇺🇦',
    'Turkey' => '🇹🇷',
    'Armenia' => '🇦🇲',
    'Iceland' => '🇮🇸',
    'South Korea' => '🇰🇷',
    'Japan' => '🇯🇵',
    'China' => '🇨🇳',
    'Taiwan' => '🇹🇼',
    'Singapore' => '🇸🇬',
    'Australia' => '🇦🇺',
    'New Zealand' => '🇳🇿',
    'Saudi Arabia' => '🇸🇦',
    'United Arab Emirates' => '🇦🇪',
    'Egypt' => '🇪🇬',
    'Malta' => '🇲🇹',
    'Malaysia' => '🇲🇾',
    'Philippines' => '🇵🇭',
    'Indonesia' => '🇮🇩',
    'Thailand' => '🇹🇭',
    'Vietnam' => '🇻🇳',
    'India' => '🇮🇳',
    'Qatar' => '🇶🇦',
    'Kuwait' => '🇰🇼',
    'Lebanon' => '🇱🇧',
    'Jordan' => '🇯🇴',
    'Peru' => '🇵🇪',
    'Colombia' => '🇨🇴',
    'Venezuela' => '🇻🇪',
    'Uruguay' => '🇺🇾',
    'Puerto Rico' => '🇵🇷',
    'International' => '🌍'
];

// Update team flags based on country
echo "1. Updating team flags...\n";
$teams = Team::whereNotNull('country')->where('country', '!=', '')->get();
$teamCount = 0;

foreach ($teams as $team) {
    if (isset($countryFlags[$team->country])) {
        $flag = $countryFlags[$team->country];
        $team->country_flag = $flag;
        $team->save();
        echo "   ✓ {$team->name} ({$team->country}): {$flag}\n";
        $teamCount++;
    }
}

echo "   Updated $teamCount team flags\n\n";

// Update player flags based on country
echo "2. Updating player flags...\n";
$players = Player::whereNotNull('country')->where('country', '!=', '')->get();
$playerCount = 0;

foreach ($players as $player) {
    if (isset($countryFlags[$player->country])) {
        $flag = $countryFlags[$player->country];
        $player->country_flag = $flag;
        $player->save();
        $playerCount++;
    }
}

echo "   Updated $playerCount player flags\n\n";

// Verify specific teams
echo "3. Verifying key teams:\n";
$keyTeams = ['Virtus.pro', '100 Thieves', 'Fnatic', 'LGD Gaming', 'Crazy Raccoon'];
foreach ($keyTeams as $teamName) {
    $team = Team::where('name', $teamName)->first();
    if ($team) {
        echo "   {$team->name}: {$team->country} {$team->country_flag}\n";
    }
}

// Show final statistics
echo "\n4. Final Statistics:\n";
$teamsWithFlags = Team::whereNotNull('country_flag')->where('country_flag', '!=', '')->count();
$playersWithFlags = Player::whereNotNull('country_flag')->where('country_flag', '!=', '')->count();

echo "   Teams with flags: $teamsWithFlags/" . Team::count() . "\n";
echo "   Players with flags: $playersWithFlags/" . Player::count() . "\n";

echo "\n✓ All flags updated!\n";