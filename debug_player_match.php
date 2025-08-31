<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Debugging Player 405 Match Data...\n\n";

$match = DB::table('matches')->where('id', 7)->first();
$mapsData = json_decode($match->maps_data, true);

echo "Match 7 found: " . ($match ? 'Yes' : 'No') . "\n";
echo "Maps data count: " . count($mapsData) . "\n\n";

if (!empty($mapsData)) {
    foreach ($mapsData as $index => $map) {
        echo "=== Map " . ($index + 1) . " ===\n";
        echo "Map name: " . ($map['map_name'] ?? 'Unknown') . "\n";
        
        if (isset($map['team1_composition'])) {
            echo "Team1 players count: " . count($map['team1_composition']) . "\n";
            foreach ($map['team1_composition'] as $playerIndex => $player) {
                $playerId = $player['player_id'] ?? $player['id'] ?? 'no_id';
                $playerName = $player['name'] ?? $player['player_name'] ?? 'no_name';
                echo "  Player {$playerIndex}: ID={$playerId}, Name={$playerName}\n";
                
                if ($playerId == 405) {
                    echo "  *** FOUND PLAYER 405! ***\n";
                    echo "  Heroes played count: " . (isset($player['heroes_played']) ? count($player['heroes_played']) : 'no heroes_played field') . "\n";
                    if (isset($player['heroes_played'])) {
                        foreach ($player['heroes_played'] as $heroIndex => $hero) {
                            echo "    Hero {$heroIndex}: " . ($hero['hero'] ?? 'Unknown') . "\n";
                        }
                    }
                }
            }
        }
        
        if (isset($map['team2_composition'])) {
            echo "Team2 players count: " . count($map['team2_composition']) . "\n";
            foreach ($map['team2_composition'] as $playerIndex => $player) {
                $playerId = $player['player_id'] ?? $player['id'] ?? 'no_id';
                $playerName = $player['name'] ?? $player['player_name'] ?? 'no_name';
                echo "  Player {$playerIndex}: ID={$playerId}, Name={$playerName}\n";
            }
        }
        
        echo "\n";
    }
}