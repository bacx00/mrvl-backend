<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🎮 Adding 7 more heroes to all players on all maps for Match 7...\n\n";

try {
    // Get current match data
    $match = DB::table('matches')->where('id', 7)->first();
    if (!$match) {
        throw new Exception('Match 7 not found');
    }

    $mapsData = json_decode($match->maps_data, true);
    
    // Additional heroes pool
    $additionalHeroes = [
        'Black Widow', 'Captain America', 'Doctor Strange', 'Hawkeye', 
        'Hulk', 'Thor', 'Wolverine'
    ];
    
    // Function to generate random stats
    function generateRandomStats($heroIndex) {
        $baseStats = [
            ['damage' => rand(800, 1200), 'deaths' => rand(0, 3), 'assists' => rand(15, 25), 'healing' => rand(0, 500), 'eliminations' => rand(3, 8), 'damage_blocked' => rand(0, 2000)],
            ['damage' => rand(2500, 3500), 'deaths' => rand(2, 6), 'assists' => rand(8, 15), 'healing' => rand(0, 100), 'eliminations' => rand(8, 15), 'damage_blocked' => rand(0, 1000)],
            ['damage' => rand(1500, 2500), 'deaths' => rand(1, 4), 'assists' => rand(10, 20), 'healing' => rand(2000, 4000), 'eliminations' => rand(2, 6), 'damage_blocked' => rand(0, 500)]
        ];
        return $baseStats[$heroIndex % 3];
    }
    
    // Update each map
    foreach ($mapsData as $mapIndex => &$map) {
        echo "📍 Processing Map " . ($mapIndex + 1) . ": {$map['map_name']}\n";
        
        // Update team1_composition
        foreach ($map['team1_composition'] as &$player) {
            $originalHero = $player['hero'];
            $originalStats = [
                'damage' => $player['damage'],
                'deaths' => $player['deaths'],
                'assists' => $player['assists'],
                'healing' => $player['healing'],
                'eliminations' => $player['eliminations'],
                'damage_blocked' => $player['damage_blocked']
            ];
            
            // Reset heroes_played array and add original hero first
            $player['heroes_played'] = [
                array_merge([
                    'hero' => $originalHero
                ], $originalStats)
            ];
            
            // Add 7 additional heroes with unique stats
            foreach ($additionalHeroes as $heroIndex => $hero) {
                $stats = generateRandomStats($heroIndex);
                $player['heroes_played'][] = array_merge([
                    'hero' => $hero
                ], $stats);
            }
            
            // Update hero_count
            $player['hero_count'] = count($player['heroes_played']);
            
            echo "  👤 {$player['name']}: Added " . count($additionalHeroes) . " heroes (Total: {$player['hero_count']})\n";
        }
        
        // Update team2_composition
        foreach ($map['team2_composition'] as &$player) {
            $originalHero = $player['hero'];
            $originalStats = [
                'damage' => $player['damage'],
                'deaths' => $player['deaths'],
                'assists' => $player['assists'],
                'healing' => $player['healing'],
                'eliminations' => $player['eliminations'],
                'damage_blocked' => $player['damage_blocked']
            ];
            
            // Reset heroes_played array and add original hero first
            $player['heroes_played'] = [
                array_merge([
                    'hero' => $originalHero
                ], $originalStats)
            ];
            
            // Add 7 additional heroes with unique stats
            foreach ($additionalHeroes as $heroIndex => $hero) {
                $stats = generateRandomStats($heroIndex);
                $player['heroes_played'][] = array_merge([
                    'hero' => $hero
                ], $stats);
            }
            
            // Update hero_count
            $player['hero_count'] = count($player['heroes_played']);
            
            echo "  👤 {$player['name']}: Added " . count($additionalHeroes) . " heroes (Total: {$player['hero_count']})\n";
        }
        
        echo "  ✅ Map " . ($mapIndex + 1) . " updated successfully\n\n";
    }
    
    // Update the match with new maps data
    DB::table('matches')->where('id', 7)->update([
        'maps_data' => json_encode($mapsData),
        'updated_at' => now()
    ]);
    
    echo "🎉 SUCCESS: Added 7 additional heroes to all players on all 3 maps!\n";
    echo "📊 Each player now has 8 heroes total per map with unique stats\n";
    echo "🔄 Match 7 data updated and ready for testing\n\n";
    
    // Summary
    $totalPlayers = 0;
    $totalHeroesAdded = 0;
    
    foreach ($mapsData as $map) {
        $playersInMap = count($map['team1_composition']) + count($map['team2_composition']);
        $totalPlayers += $playersInMap;
        $totalHeroesAdded += $playersInMap * 7; // 7 heroes added per player
    }
    
    echo "📈 SUMMARY:\n";
    echo "  - Maps processed: " . count($mapsData) . "\n";
    echo "  - Total players updated: {$totalPlayers}\n";
    echo "  - Total heroes added: {$totalHeroesAdded}\n";
    echo "  - Heroes per player per map: 8 (1 original + 7 new)\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}