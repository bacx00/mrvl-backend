<?php
require_once __DIR__ . '/vendor/autoload.php';
use Illuminate\Support\Facades\DB;
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🎮 Creating Epic BO9 Rematch: 100 Thieves vs BOOM Esports\n";
echo "Event: Marvel Rivals Championship 2025 - Grand Finals\n";
echo "Format: BO9 (Best of 9) - First to 5 wins\n";
echo "Status: LIVE - Currently on Map 5\n\n";

// Get the event
$event = DB::table('events')->where('id', 2)->first(); // Marvel Rivals Championship 2025
if (!$event) {
    die("Event not found!\n");
}

// Get teams - 100 Thieves and BOOM Esports
$team1 = DB::table('teams')->where('id', 4)->first(); // 100 Thieves (ID: 4)
$team2 = DB::table('teams')->where('id', 32)->first(); // BOOM Esports (ID: 32)

if (!$team1) {
    echo "100 Thieves not found with ID 4, searching by name...\n";
    $team1 = DB::table('teams')->where('name', 'like', '%100%Thieves%')->first();
}

if (!$team2) {
    echo "BOOM Esports not found with ID 32, searching by name...\n";
    $team2 = DB::table('teams')->where('name', 'like', '%BOOM%')->first();
}

if (!$team1 || !$team2) {
    // Create them if they don't exist
    if (!$team1) {
        $team1Id = DB::table('teams')->insertGetId([
            'name' => '100 Thieves',
            'short_name' => '100T',
            'region' => 'NA',
            'rating' => 2650,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $team1 = DB::table('teams')->where('id', $team1Id)->first();
        echo "Created 100 Thieves with ID: {$team1Id}\n";
    }
    
    if (!$team2) {
        $team2Id = DB::table('teams')->insertGetId([
            'name' => 'BOOM Esports',
            'short_name' => 'BOOM',
            'region' => 'SEA',
            'rating' => 2600,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $team2 = DB::table('teams')->where('id', $team2Id)->first();
        echo "Created BOOM Esports with ID: {$team2Id}\n";
    }
}

echo "Team 1: {$team1->name} (ID: {$team1->id})\n";
echo "Team 2: {$team2->name} (ID: {$team2->id})\n\n";

// 9 Maps for BO9
$maps = [
    'Tokyo 2099: Spider-Islands',          // Map 1 - 100T wins
    'Wakanda: Djalia',                     // Map 2 - BOOM wins
    'Asgard: Rainbow Bridge',              // Map 3 - 100T wins
    'Midtown Metropolis: Spider-Islands',  // Map 4 - BOOM wins
    'Klyntar: Symbiote Imperative',       // Map 5 - 100T leading (LIVE)
    'Hell\'s Kitchen: Midnight',          // Map 6 - TBD
    'Olympus: Throne Room',                // Map 7 - TBD
    'Savage Land: Prehistoric Jungle',     // Map 8 - TBD
    'Atlantis: Deep Sea Palace'           // Map 9 - TBD
];

// Map results (100T leads 3-2, currently on Map 5)
$mapWinners = [1, 2, 1, 2, 1, null, null, null, null]; // 1 = 100T, 2 = BOOM, null = not played yet
$mapScores = [
    [13, 10], // Map 1: 100T wins 13-10
    [11, 13], // Map 2: BOOM wins 13-11
    [13, 7],  // Map 3: 100T wins 13-7
    [9, 13],  // Map 4: BOOM wins 13-9
    [8, 5],   // Map 5: 100T leading 8-5 (LIVE)
    [0, 0],   // Map 6: Not started
    [0, 0],   // Map 7: Not started
    [0, 0],   // Map 8: Not started
    [0, 0]    // Map 9: Not started
];

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

// Track used heroes per player
$playerHeroHistory = [];

// Get or create players for 100 Thieves
$team1Players = DB::table('players')->where('team_id', $team1->id)->limit(6)->get();
if ($team1Players->count() < 6) {
    echo "Creating players for 100 Thieves...\n";
    $playerNames = ['Abbedagge', 'Closer', 'FBI', 'huhi', 'Ssumday', 'Tenacity'];
    foreach ($playerNames as $i => $name) {
        if ($i >= $team1Players->count()) {
            DB::table('players')->insert([
                'name' => $name,
                'team_id' => $team1->id,
                'role' => $i < 2 ? 'Vanguard' : ($i < 4 ? 'Duelist' : 'Strategist'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    $team1Players = DB::table('players')->where('team_id', $team1->id)->limit(6)->get();
}

// Get or create players for BOOM Esports  
$team2Players = DB::table('players')->where('team_id', $team2->id)->limit(6)->get();
if ($team2Players->count() < 6) {
    echo "Creating players for BOOM Esports...\n";
    $playerNames = ['Tino', 'Jackky', 'FBZ', 'Topoon', 'Sawo', 'SanTaX'];
    foreach ($playerNames as $i => $name) {
        if ($i >= $team2Players->count()) {
            DB::table('players')->insert([
                'name' => $name,
                'team_id' => $team2->id,
                'role' => $i < 2 ? 'Vanguard' : ($i < 4 ? 'Duelist' : 'Strategist'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    $team2Players = DB::table('players')->where('team_id', $team2->id)->limit(6)->get();
}

// Create maps_data structure
$mapsData = [];

foreach ($maps as $mapIndex => $mapName) {
    $mapNumber = $mapIndex + 1;
    $winner = $mapWinners[$mapIndex];
    $isLive = ($mapNumber == 5); // Map 5 is currently live
    $isPlayed = ($mapNumber <= 5); // Maps 1-5 have been played/are being played
    
    echo "Creating Map {$mapNumber}: {$mapName}";
    if ($isLive) {
        echo " [🔴 LIVE]";
    } elseif (!$isPlayed) {
        echo " [Not played yet]";
    }
    echo "\n";
    
    if ($winner !== null) {
        echo "  Winner: " . ($winner == 1 ? "100 Thieves" : "BOOM Esports") . "\n";
    }
    
    $mapData = [
        'map_name' => $mapName,
        'map_number' => $mapNumber,
        'winner' => $winner ? ($winner == 1 ? 'team1' : 'team2') : null,
        'team1_score' => $mapScores[$mapIndex][0],
        'team2_score' => $mapScores[$mapIndex][1],
        'is_live' => $isLive,
        'team1_composition' => [],
        'team2_composition' => []
    ];
    
    // Only generate stats for played maps
    if ($isPlayed) {
        // Add Team 1 (100 Thieves) players with unique heroes
        foreach ($team1Players as $playerIndex => $player) {
            // Initialize player hero history if not exists
            if (!isset($playerHeroHistory['team1_' . $player->id])) {
                $playerHeroHistory['team1_' . $player->id] = [];
            }
            
            // Get a unique hero for this player on this map
            $availableHeroes = array_diff($allHeroes, $playerHeroHistory['team1_' . $player->id]);
            if (empty($availableHeroes)) {
                $availableHeroes = $allHeroes; // Reset if we somehow run out
            }
            
            // Use deterministic selection based on player and map
            $heroIndex = ($playerIndex + $mapIndex * 6) % count($availableHeroes);
            $heroName = array_values($availableHeroes)[$heroIndex];
            $playerHeroHistory['team1_' . $player->id][] = $heroName;
            
            // Generate realistic stats based on map outcome and current state
            $isWinner = ($winner == 1);
            $statsMultiplier = $isLive ? 0.6 : 1.0; // Lower stats for ongoing map
            
            $eliminations = round(($isWinner ? rand(18, 35) : rand(10, 25)) * $statsMultiplier);
            $deaths = round(($isWinner ? rand(4, 12) : rand(8, 18)) * $statsMultiplier);
            $assists = round(rand(8, 25) * $statsMultiplier);
            $damage = round(rand(12000, 35000) * $statsMultiplier);
            $healing = in_array($heroName, ['Mantis', 'Luna Snow', 'Jeff the Shark', 'Adam Warlock', 'Invisible Woman']) 
                ? round(rand(15000, 40000) * $statsMultiplier) : round(rand(0, 8000) * $statsMultiplier);
            $damageBlocked = in_array($heroName, ['Doctor Strange', 'Magneto', 'Groot', 'Captain America', 'Thor']) 
                ? round(rand(8000, 25000) * $statsMultiplier) : round(rand(0, 5000) * $statsMultiplier);
            
            $mapData['team1_composition'][] = [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'name' => $player->name,
                'hero' => $heroName,
                'heroes_played' => [[
                    'hero' => $heroName,
                    'eliminations' => $eliminations,
                    'deaths' => $deaths,
                    'assists' => $assists,
                    'damage' => $damage,
                    'healing' => $healing,
                    'damage_blocked' => $damageBlocked
                ]],
                'eliminations' => $eliminations,
                'deaths' => $deaths,
                'assists' => $assists,
                'damage' => $damage,
                'healing' => $healing,
                'damage_blocked' => $damageBlocked
            ];
        }
        
        // Add Team 2 (BOOM Esports) players with unique heroes
        foreach ($team2Players as $playerIndex => $player) {
            // Initialize player hero history if not exists
            if (!isset($playerHeroHistory['team2_' . $player->id])) {
                $playerHeroHistory['team2_' . $player->id] = [];
            }
            
            // Get a unique hero for this player on this map
            $availableHeroes = array_diff($allHeroes, $playerHeroHistory['team2_' . $player->id]);
            if (empty($availableHeroes)) {
                $availableHeroes = $allHeroes;
            }
            
            // Use different selection pattern for team 2
            $heroIndex = (($playerIndex + 3) + $mapIndex * 7) % count($availableHeroes);
            $heroName = array_values($availableHeroes)[$heroIndex];
            $playerHeroHistory['team2_' . $player->id][] = $heroName;
            
            // Generate realistic stats
            $isWinner = ($winner == 2);
            $statsMultiplier = $isLive ? 0.6 : 1.0;
            
            $eliminations = round(($isWinner ? rand(18, 35) : rand(10, 25)) * $statsMultiplier);
            $deaths = round(($isWinner ? rand(4, 12) : rand(8, 18)) * $statsMultiplier);
            $assists = round(rand(8, 25) * $statsMultiplier);
            $damage = round(rand(12000, 35000) * $statsMultiplier);
            $healing = in_array($heroName, ['Mantis', 'Luna Snow', 'Jeff the Shark', 'Adam Warlock', 'Invisible Woman']) 
                ? round(rand(15000, 40000) * $statsMultiplier) : round(rand(0, 8000) * $statsMultiplier);
            $damageBlocked = in_array($heroName, ['Doctor Strange', 'Magneto', 'Groot', 'Captain America', 'Thor']) 
                ? round(rand(8000, 25000) * $statsMultiplier) : round(rand(0, 5000) * $statsMultiplier);
            
            $mapData['team2_composition'][] = [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'name' => $player->name,
                'hero' => $heroName,
                'heroes_played' => [[
                    'hero' => $heroName,
                    'eliminations' => $eliminations,
                    'deaths' => $deaths,
                    'assists' => $assists,
                    'damage' => $damage,
                    'healing' => $healing,
                    'damage_blocked' => $damageBlocked
                ]],
                'eliminations' => $eliminations,
                'deaths' => $deaths,
                'assists' => $assists,
                'damage' => $damage,
                'healing' => $healing,
                'damage_blocked' => $damageBlocked
            ];
        }
        
        echo "  ✓ Generated stats for " . count($mapData['team1_composition']) . " vs " . count($mapData['team2_composition']) . " players\n";
    }
    
    $mapsData[] = $mapData;
}

// Create the match as LIVE on map 5
$matchId = DB::table('matches')->insertGetId([
    'event_id' => $event->id,
    'team1_id' => $team1->id,
    'team2_id' => $team2->id,
    'team1_score' => 3, // 100 Thieves winning 3 maps
    'team2_score' => 2, // BOOM winning 2 maps
    'series_score_team1' => 3,
    'series_score_team2' => 2,
    'status' => 'live',
    'format' => 'BO9',
    'scheduled_at' => now()->subHours(3),
    'started_at' => now()->subHours(3),
    'ended_at' => null,
    'current_map' => 5, // Currently on Map 5
    'maps_data' => json_encode($mapsData),
    'stream_urls' => json_encode([
        'https://www.twitch.tv/marvel_rivals',
        'https://www.youtube.com/watch?v=live_stream_1'
    ]),
    'betting_urls' => json_encode([
        'https://www.bet365.com/esports/100t-vs-boom',
        'https://www.draftkings.com/marvel-rivals-grand-finals'
    ]),
    'vod_urls' => json_encode([]),
    'created_at' => now(),
    'updated_at' => now()
]);

echo "\n✅ EPIC BO9 REMATCH created successfully!\n";
echo "Match ID: {$matchId}\n";
echo "URL: https://staging.mrvl.net/#match-detail/{$matchId}\n";
echo "\nMatch Summary:\n";
echo "- Status: 🔴 LIVE (Currently on Map 5)\n";
echo "- Event: Marvel Rivals Championship 2025 - Grand Finals\n";
echo "- Teams: {$team1->name} vs {$team2->name}\n";
echo "- Format: BO9 (First to 5 wins)\n";
echo "- Current Score: 3-2 (100 Thieves leading)\n";
echo "- Map 5 Score: 8-5 (100 Thieves leading)\n";
echo "- Maps played: 5 of 9\n";
echo "- Total unique heroes used: " . count(array_unique(array_merge(...array_values($playerHeroHistory)))) . "\n";
echo "\nMap Results:\n";
foreach ($maps as $i => $map) {
    $mapNum = $i + 1;
    echo "  Map $mapNum - $map: ";
    if ($mapWinners[$i] === null) {
        echo "Not played yet\n";
    } else {
        $winner = $mapWinners[$i] == 1 ? '100 Thieves' : 'BOOM Esports';
        echo "{$mapScores[$i][0]}-{$mapScores[$i][1]} ($winner wins)";
        if ($mapNum == 5) echo " [LIVE]";
        echo "\n";
    }
}