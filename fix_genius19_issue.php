<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Investigating genius19 issue in Match 7...\n\n";

$match = DB::table('matches')->where('id', 7)->first();
$maps_data = json_decode($match->maps_data, true);

echo "Map 1 - Team 1 Players: " . count($maps_data[0]['team1_composition']) . "\n";
echo "Map 1 - Team 2 Players: " . count($maps_data[0]['team2_composition']) . "\n\n";

// List all Team 1 players on Map 1
echo "Team 1 Players on Map 1:\n";
foreach($maps_data[0]['team1_composition'] as $index => $player) {
    echo "  [{$index}] {$player['name']} (ID: {$player['player_id']})\n";
    if($player['name'] == 'genius19') {
        echo "    ⚠️ This is the extra player!\n";
    }
}

echo "\n🔧 Removing genius19 from Team 1...\n";

// Remove genius19 from all maps
foreach($maps_data as $mapIndex => &$map) {
    $originalCount = count($map['team1_composition']);
    $map['team1_composition'] = array_values(array_filter($map['team1_composition'], function($player) {
        return $player['name'] != 'genius19';
    }));
    $newCount = count($map['team1_composition']);
    
    if($originalCount != $newCount) {
        echo "  Map " . ($mapIndex + 1) . ": Removed genius19 (was {$originalCount} players, now {$newCount})\n";
    }
}

// Update the database
DB::table('matches')
    ->where('id', 7)
    ->update(['maps_data' => json_encode($maps_data)]);

echo "\n✅ Fixed! genius19 has been removed from Team 1 composition.\n";

// Verify the fix
$match = DB::table('matches')->where('id', 7)->first();
$maps_data = json_decode($match->maps_data, true);
echo "\nVerification:\n";
echo "  Map 1 - Team 1 Players: " . count($maps_data[0]['team1_composition']) . " (should be 6)\n";
echo "  Map 2 - Team 1 Players: " . count($maps_data[1]['team1_composition']) . " (should be 6)\n";
echo "  Map 3 - Team 1 Players: " . count($maps_data[2]['team1_composition']) . " (should be 6)\n";