<?php
require_once __DIR__ . '/vendor/autoload.php';
use Illuminate\Support\Facades\DB;
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔧 Fixing BO9 Match 15: Adding complete data for all 9 maps with 5 heroes per player\n\n";

// Get the match
$match = DB::table('matches')->where('id', 15)->first();
if (!$match) {
    die("Match 15 not found!\n");
}

// Decode existing maps data
$mapsData = json_decode($match->maps_data, true);

echo "Current maps count: " . count($mapsData) . "\n";
echo "Maps with data: ";
foreach ($mapsData as $i => $map) {
    if (!empty($map['team1_composition'])) {
        echo ($i + 1) . " ";
    }
}
echo "\n\n";

// Comprehensive hero pool
$allHeroes = [
    // Vanguards
    'Captain America', 'Doctor Strange', 'Groot', 'Hulk', 'Magneto', 'Thor', 'Venom', 'Peni Parker',
    
    // Duelists  
    'Black Widow', 'Hawkeye', 'Iron Man', 'Punisher', 'Spider-Man', 'Squirrel Girl', 'Star-Lord',
    'Winter Soldier', 'Storm', 'Scarlet Witch', 'Moon Knight', 'Psylocke', 'Black Panther',
    'Iron Fist', 'Magik', 'Namor', 'Wolverine', 'Hela',
    
    // Strategists
    'Adam Warlock', 'Cloak & Dagger', 'Jeff the Shark', 'Loki', 'Luna Snow', 'Mantis',
    'Rocket Raccoon', 'Invisible Woman', 'Mister Fantastic', 'Human Torch', 'The Thing'
];

// Get players
$team1Players = DB::table('players')->where('team_id', 4)->limit(6)->get(); // 100 Thieves
$team2Players = DB::table('players')->where('team_id', 32)->limit(6)->get(); // BOOM Esports

// Track hero usage per player across all maps
$playerHeroUsage = [];

// Function to get 5 unique heroes for a player on a specific map
function get5UniqueHeroesForPlayer($playerId, $mapIndex, &$playerHeroUsage, $allHeroes) {
    if (!isset($playerHeroUsage[$playerId])) {
        $playerHeroUsage[$playerId] = [];
    }
    
    // Get available heroes (not used by this player yet)
    $availableHeroes = array_diff($allHeroes, $playerHeroUsage[$playerId]);
    
    // If we don't have enough heroes, allow reuse from early maps
    if (count($availableHeroes) < 5) {
        // Reset but keep recent maps unique
        $recentMaps = array_slice($playerHeroUsage[$playerId], -15); // Keep last 3 maps (3*5=15 heroes)
        $availableHeroes = array_diff($allHeroes, $recentMaps);
        
        if (count($availableHeroes) < 5) {
            $availableHeroes = $allHeroes; // Full reset if needed
        }
    }
    
    // Shuffle and pick 5
    shuffle($availableHeroes);
    $selectedHeroes = array_slice($availableHeroes, 0, 5);
    
    // Add to usage history
    $playerHeroUsage[$playerId] = array_merge($playerHeroUsage[$playerId], $selectedHeroes);
    
    return $selectedHeroes;
}

// Map winners and scores
$mapWinners = [1, 2, 1, 2, 1, 2, 1, 1, 2]; // 100T wins 5-4 in the end
$mapScores = [
    [13, 10], // Map 1: 100T wins
    [11, 13], // Map 2: BOOM wins
    [13, 7],  // Map 3: 100T wins
    [9, 13],  // Map 4: BOOM wins
    [13, 11], // Map 5: 100T wins (change from live to completed)
    [10, 13], // Map 6: BOOM wins
    [13, 8],  // Map 7: 100T wins
    [13, 12], // Map 8: 100T wins (close out)
    [11, 13]  // Map 9: BOOM wins (but too late)
];

// Process all 9 maps
foreach ($mapsData as $mapIndex => &$mapData) {
    $mapNumber = $mapIndex + 1;
    $winner = $mapWinners[$mapIndex];
    
    echo "Processing Map $mapNumber: " . $mapData['map_name'] . "\n";
    
    // Update map scores and winner
    $mapData['winner'] = $winner == 1 ? 'team1' : 'team2';
    $mapData['team1_score'] = $mapScores[$mapIndex][0];
    $mapData['team2_score'] = $mapScores[$mapIndex][1];
    $mapData['is_live'] = false; // All maps completed now
    
    // Clear existing composition
    $mapData['team1_composition'] = [];
    $mapData['team2_composition'] = [];
    
    // Add Team 1 (100 Thieves) players with 5 heroes each
    foreach ($team1Players as $player) {
        $heroes = get5UniqueHeroesForPlayer('t1_' . $player->id, $mapIndex, $playerHeroUsage, $allHeroes);
        $heroesPlayed = [];
        $totalElims = 0;
        $totalDeaths = 0;
        $totalAssists = 0;
        $totalDamage = 0;
        $totalHealing = 0;
        $totalBlocked = 0;
        
        foreach ($heroes as $heroIdx => $heroName) {
            $isWinner = ($winner == 1);
            
            // Generate stats for each hero
            $elims = $isWinner ? rand(4, 9) : rand(2, 6);
            $deaths = $isWinner ? rand(1, 3) : rand(2, 5);
            $assists = rand(2, 6);
            $damage = rand(3000, 8000);
            $healing = in_array($heroName, ['Mantis', 'Luna Snow', 'Jeff the Shark', 'Adam Warlock', 'Invisible Woman'])
                ? rand(3000, 10000) : rand(0, 2000);
            $blocked = in_array($heroName, ['Doctor Strange', 'Magneto', 'Groot', 'Captain America', 'Thor'])
                ? rand(2000, 6000) : rand(0, 1500);
            
            $heroesPlayed[] = [
                'hero' => $heroName,
                'eliminations' => $elims,
                'deaths' => $deaths,
                'assists' => $assists,
                'damage' => $damage,
                'healing' => $healing,
                'damage_blocked' => $blocked
            ];
            
            $totalElims += $elims;
            $totalDeaths += $deaths;
            $totalAssists += $assists;
            $totalDamage += $damage;
            $totalHealing += $healing;
            $totalBlocked += $blocked;
        }
        
        $mapData['team1_composition'][] = [
            'player_id' => $player->id,
            'player_name' => $player->name,
            'name' => $player->name,
            'hero' => $heroes[0], // Primary hero for display
            'heroes_played' => $heroesPlayed,
            'eliminations' => $totalElims,
            'deaths' => $totalDeaths,
            'assists' => $totalAssists,
            'damage' => $totalDamage,
            'healing' => $totalHealing,
            'damage_blocked' => $totalBlocked
        ];
    }
    
    // Add Team 2 (BOOM Esports) players with 5 heroes each
    foreach ($team2Players as $player) {
        $heroes = get5UniqueHeroesForPlayer('t2_' . $player->id, $mapIndex, $playerHeroUsage, $allHeroes);
        $heroesPlayed = [];
        $totalElims = 0;
        $totalDeaths = 0;
        $totalAssists = 0;
        $totalDamage = 0;
        $totalHealing = 0;
        $totalBlocked = 0;
        
        foreach ($heroes as $heroIdx => $heroName) {
            $isWinner = ($winner == 2);
            
            // Generate stats for each hero
            $elims = $isWinner ? rand(4, 9) : rand(2, 6);
            $deaths = $isWinner ? rand(1, 3) : rand(2, 5);
            $assists = rand(2, 6);
            $damage = rand(3000, 8000);
            $healing = in_array($heroName, ['Mantis', 'Luna Snow', 'Jeff the Shark', 'Adam Warlock', 'Invisible Woman'])
                ? rand(3000, 10000) : rand(0, 2000);
            $blocked = in_array($heroName, ['Doctor Strange', 'Magneto', 'Groot', 'Captain America', 'Thor'])
                ? rand(2000, 6000) : rand(0, 1500);
            
            $heroesPlayed[] = [
                'hero' => $heroName,
                'eliminations' => $elims,
                'deaths' => $deaths,
                'assists' => $assists,
                'damage' => $damage,
                'healing' => $healing,
                'damage_blocked' => $blocked
            ];
            
            $totalElims += $elims;
            $totalDeaths += $deaths;
            $totalAssists += $assists;
            $totalDamage += $damage;
            $totalHealing += $healing;
            $totalBlocked += $blocked;
        }
        
        $mapData['team2_composition'][] = [
            'player_id' => $player->id,
            'player_name' => $player->name,
            'name' => $player->name,
            'hero' => $heroes[0], // Primary hero for display
            'heroes_played' => $heroesPlayed,
            'eliminations' => $totalElims,
            'deaths' => $totalDeaths,
            'assists' => $totalAssists,
            'damage' => $totalDamage,
            'healing' => $totalHealing,
            'damage_blocked' => $totalBlocked
        ];
    }
    
    echo "  ✓ Generated data for 6 vs 6 players with 5 heroes each\n";
}

// Update the match with complete data
DB::table('matches')->where('id', 15)->update([
    'team1_score' => 5, // 100 Thieves wins 5 maps
    'team2_score' => 4, // BOOM wins 4 maps
    'series_score_team1' => 5,
    'series_score_team2' => 4,
    'status' => 'completed', // Match is now completed
    'current_map' => 9, // All 9 maps played
    'ended_at' => now(),
    'maps_data' => json_encode($mapsData),
    'updated_at' => now()
]);

echo "\n✅ BO9 Match 15 successfully updated!\n";
echo "- All 9 maps now have complete data\n";
echo "- Each player has 5 different heroes per map\n";
echo "- Final Score: 100 Thieves 5-4 BOOM Esports\n";
echo "- Status: Completed\n";
echo "\nMap Results:\n";
foreach ($mapWinners as $i => $winner) {
    $mapNum = $i + 1;
    $winnerName = $winner == 1 ? '100 Thieves' : 'BOOM Esports';
    echo "  Map $mapNum: {$mapScores[$i][0]}-{$mapScores[$i][1]} ($winnerName wins)\n";
}

// Count total unique heroes used
$totalUniqueHeroes = count(array_unique(array_merge(...array_values($playerHeroUsage))));
echo "\nTotal unique heroes used: $totalUniqueHeroes\n";
echo "URL: https://staging.mrvl.net/#match-detail/15\n";