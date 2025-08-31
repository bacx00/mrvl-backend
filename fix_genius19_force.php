<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔧 Force removing genius19 from Match 7...\n\n";

$match = DB::table('matches')->where('id', 7)->first();
$maps_data = json_decode($match->maps_data, true);

// Process each map
foreach($maps_data as $mapIndex => &$map) {
    $filtered = [];
    foreach($map['team1_composition'] as $player) {
        // Skip genius19 (ID 659) or any player named genius19
        if($player['player_id'] != 659 && 
           strtolower($player['name']) != 'genius19' && 
           strtolower($player['player_name'] ?? '') != 'genius19') {
            $filtered[] = $player;
        } else {
            echo "  Removing player: {$player['name']} (ID: {$player['player_id']}) from Map " . ($mapIndex + 1) . "\n";
        }
    }
    $map['team1_composition'] = array_values($filtered);
    echo "  Map " . ($mapIndex + 1) . " - Team 1 now has " . count($map['team1_composition']) . " players\n";
}

// Update the database
DB::table('matches')
    ->where('id', 7)
    ->update([
        'maps_data' => json_encode($maps_data),
        'updated_at' => now()
    ]);

echo "\n✅ Database updated!\n";

// Clear cache
Artisan::call('cache:clear');
echo "✅ Cache cleared!\n";

// Verify the fix
$match = DB::table('matches')->where('id', 7)->first();
$maps_data = json_decode($match->maps_data, true);

echo "\n📊 Final Verification:\n";
foreach($maps_data as $mapIndex => $map) {
    echo "  Map " . ($mapIndex + 1) . ":\n";
    echo "    Team 1: " . count($map['team1_composition']) . " players - ";
    $names = array_map(fn($p) => $p['name'], $map['team1_composition']);
    echo implode(', ', $names) . "\n";
    echo "    Team 2: " . count($map['team2_composition']) . " players\n";
}