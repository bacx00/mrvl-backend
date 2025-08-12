<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MvrlMatch;

echo "Checking match 25 data structure...\n";

$match = MvrlMatch::find(25);
if (!$match) {
    echo "Match 25 not found!\n";
    exit(1);
}

echo "Match found: " . $match->team1->name . " vs " . $match->team2->name . "\n";
echo "Maps data count: " . count($match->maps_data) . "\n\n";

// Check the player data structure
$firstMap = $match->maps_data[0] ?? null;
if ($firstMap) {
    $team1Comp = $firstMap['team1_composition'] ?? [];
    if (!empty($team1Comp)) {
        $firstPlayer = $team1Comp[0];
        echo "First player data:\n";
        echo "  - Name: " . ($firstPlayer['name'] ?? 'not found') . "\n";
        echo "  - player_id field: " . ($firstPlayer['player_id'] ?? 'not found') . "\n";
        echo "  - id field: " . ($firstPlayer['id'] ?? 'not found') . "\n\n";
    }
}

// The issue is that we have BOTH player_id AND id fields
// Let's update the controller to check both

echo "Testing query for player 410...\n";

// Original query
$matches1 = MvrlMatch::where(function($query) {
    $query->whereJsonContains('maps_data', ['team1_composition' => [['player_id' => 410]]])
          ->orWhereJsonContains('maps_data', ['team2_composition' => [['player_id' => 410]]]);
})->count();
echo "Matches found with player_id=410: $matches1\n";

// Also check with id field
$matches2 = MvrlMatch::where(function($query) {
    $query->whereJsonContains('maps_data', ['team1_composition' => [['id' => 410]]])
          ->orWhereJsonContains('maps_data', ['team2_composition' => [['id' => 410]]]);
})->count();
echo "Matches found with id=410: $matches2\n";

// Try a different approach - search in the JSON directly
$matches3 = MvrlMatch::whereRaw("JSON_SEARCH(maps_data, 'one', '410', NULL, '$[*].team1_composition[*].player_id') IS NOT NULL")
    ->orWhereRaw("JSON_SEARCH(maps_data, 'one', '410', NULL, '$[*].team2_composition[*].player_id') IS NOT NULL")
    ->orWhereRaw("JSON_SEARCH(maps_data, 'one', '410', NULL, '$[*].team1_composition[*].id') IS NOT NULL")
    ->orWhereRaw("JSON_SEARCH(maps_data, 'one', '410', NULL, '$[*].team2_composition[*].id') IS NOT NULL")
    ->count();
echo "Matches found with JSON_SEARCH: $matches3\n";

// Let's just check if match 25 has player 410
$hasPlayer = false;
foreach ($match->maps_data as $map) {
    $team1Players = $map['team1_composition'] ?? [];
    $team2Players = $map['team2_composition'] ?? [];
    
    foreach ($team1Players as $player) {
        if (($player['player_id'] ?? null) == 410 || ($player['id'] ?? null) == 410) {
            $hasPlayer = true;
            echo "Found player 410 in team1_composition with data: " . json_encode($player) . "\n";
            break 2;
        }
    }
    
    foreach ($team2Players as $player) {
        if (($player['player_id'] ?? null) == 410 || ($player['id'] ?? null) == 410) {
            $hasPlayer = true;
            echo "Found player 410 in team2_composition\n";
            break 2;
        }
    }
}

if (!$hasPlayer) {
    echo "Player 410 NOT found in match 25!\n";
} else {
    echo "\nPlayer 410 is in match 25 but query is not finding it.\n";
    echo "This is likely due to the JSON query syntax.\n";
}