<?php
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔧 FORCE FIX MATCH 7\n";
echo "====================\n\n";

// Get match 7
$match = DB::table('matches')->where('id', 7)->first();
$mapsData = json_decode($match->maps_data, true);

// FIX MAP 1 - Player 405 should have Hela
echo "Fixing Map 1...\n";
foreach ($mapsData[0]['team1_composition'] as &$player) {
    if ($player['player_id'] == 405) {
        $player['hero'] = 'Hela';
        $player['eliminations'] = 121;
        $player['deaths'] = 10;
        $player['assists'] = 24;
        $player['damage'] = 10000;
        $player['healing'] = 0;
        $player['damage_blocked'] = 11200;
        echo "  ✅ Set player 405 to Hela with 121 elims\n";
    }
}

// FIX MAP 2 - Player 405 should have Iron Man
echo "Fixing Map 2...\n";
foreach ($mapsData[1]['team1_composition'] as &$player) {
    if ($player['player_id'] == 405) {
        $player['hero'] = 'Iron Man';
        $player['eliminations'] = 12;
        $player['deaths'] = 5;
        $player['assists'] = 3;
        $player['damage'] = 2852;
        $player['healing'] = 0;
        $player['damage_blocked'] = 0;
        echo "  ✅ Set player 405 to Iron Man with 12 elims\n";
    }
}

// FIX MAP 3 - Player 405 should have Rocket Raccoon
echo "Fixing Map 3...\n";
foreach ($mapsData[2]['team1_composition'] as &$player) {
    if ($player['player_id'] == 405) {
        $player['hero'] = 'Rocket Raccoon';
        $player['eliminations'] = 4;
        $player['deaths'] = 1;
        $player['assists'] = 19;
        $player['damage'] = 825;
        $player['healing'] = 4191;
        $player['damage_blocked'] = 0;
        echo "  ✅ Set player 405 to Rocket Raccoon with 4 elims\n";
    }
}

// Update the match
DB::table('matches')->where('id', 7)->update([
    'maps_data' => json_encode($mapsData),
    'updated_at' => now()
]);

echo "\n✅ MATCH 7 FIXED!\n";
echo "Player 405 now shows:\n";
echo "  Map 1: Hela (121 elims)\n";
echo "  Map 2: Iron Man (12 elims)\n";
echo "  Map 3: Rocket Raccoon (4 elims)\n";