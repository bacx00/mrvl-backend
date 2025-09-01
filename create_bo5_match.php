<?php
require_once __DIR__ . '/vendor/autoload.php';
use Illuminate\Support\Facades\DB;
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🎮 Creating BO5 Match: Sentinels vs G2 Esports\n";
echo "Event: Marvel Rivals Championship 2025\n";
echo "Format: BO5 (Best of 5)\n";
echo "Final Score: 2-3 (Sentinels loses)\n\n";

// Get the event
$event = DB::table('events')->where('id', 2)->first(); // Marvel Rivals Championship 2025
if (!$event) {
    die("Event not found!\n");
}

// Get teams - using Sentinels (ID: 1) and G2 Esports (ID: 2)
$team1 = DB::table('teams')->where('id', 1)->first(); // Sentinels
$team2 = DB::table('teams')->where('id', 2)->first(); // G2 Esports

if (!$team1 || !$team2) {
    die("Teams not found!\n");
}

echo "Team 1: {$team1->name} (ID: {$team1->id})\n";
echo "Team 2: {$team2->name} (ID: {$team2->id})\n\n";

// Maps for BO5
$maps = [
    'Midtown Metropolis: Spider-Islands',
    'Asgard: Rainbow Bridge', 
    'Tokyo 2099: Spider-Islands',
    'Wakanda: Djalia',
    'Klyntar: Symbiote Imperitive'
];

// Map results (Sentinels wins maps 1 and 3, G2 wins maps 2, 4, and 5)
$mapWinners = [1, 2, 1, 2, 2]; // 1 = Sentinels wins, 2 = G2 wins

// Hero pool for variety
$heroPool = [
    'Spider-Man', 'Iron Man', 'Hela', 'Doctor Strange', 'Black Widow',
    'Thor', 'Hulk', 'Captain America', 'Hawkeye', 'Scarlet Witch',
    'Venom', 'Rocket Raccoon', 'Groot', 'Star-Lord', 'Mantis',
    'Luna Snow', 'Jeff the Shark', 'Magneto', 'Storm', 'Wolverine'
];

// Create maps_data structure
$mapsData = [];

foreach ($maps as $mapIndex => $mapName) {
    $mapNumber = $mapIndex + 1;
    $winner = $mapWinners[$mapIndex];
    
    echo "Creating Map {$mapNumber}: {$mapName}\n";
    echo "  Winner: " . ($winner == 1 ? "Sentinels" : "G2 Esports") . "\n";
    
    $mapData = [
        'map_name' => $mapName,
        'map_number' => $mapNumber,
        'winner' => $winner == 1 ? 'team1' : 'team2',
        'team1_score' => $winner == 1 ? 13 : rand(8, 11),
        'team2_score' => $winner == 2 ? 13 : rand(8, 11),
        'team1_composition' => [],
        'team2_composition' => []
    ];
    
    // Get Sentinels players
    $team1Players = DB::table('players')->where('team_id', 1)->limit(6)->get();
    if ($team1Players->count() < 6) {
        // If not enough players, get some from other teams
        $additionalPlayers = DB::table('players')
            ->whereNotIn('id', $team1Players->pluck('id'))
            ->limit(6 - $team1Players->count())
            ->get();
        $team1Players = $team1Players->concat($additionalPlayers);
    }
    
    // Get G2 players
    $team2Players = DB::table('players')->where('team_id', 2)->limit(6)->get();
    if ($team2Players->count() < 6) {
        // If not enough players, get some from other teams
        $additionalPlayers = DB::table('players')
            ->whereNotIn('id', $team2Players->pluck('id'))
            ->whereNotIn('id', $team1Players->pluck('id'))
            ->limit(6 - $team2Players->count())
            ->get();
        $team2Players = $team2Players->concat($additionalPlayers);
    }
    
    // Add Team 1 players with 5 heroes each
    foreach ($team1Players as $playerIndex => $player) {
        $heroesPlayed = [];
        
        // Shuffle hero pool and pick 5 different heroes for this player
        $playerHeroes = array_slice($heroPool, $playerIndex * 3, 5);
        if (count($playerHeroes) < 5) {
            $playerHeroes = array_slice($heroPool, 0, 5);
        }
        
        foreach ($playerHeroes as $heroIndex => $hero) {
            // Generate realistic stats for each hero
            $isWinner = ($winner == 1);
            $heroesPlayed[] = [
                'hero' => $hero,
                'eliminations' => $isWinner ? rand(15, 35) : rand(8, 20),
                'deaths' => $isWinner ? rand(3, 10) : rand(8, 15),
                'assists' => rand(5, 20),
                'damage' => rand(8000, 25000),
                'healing' => in_array($hero, ['Mantis', 'Luna Snow', 'Jeff the Shark']) ? rand(10000, 30000) : rand(0, 5000),
                'damage_blocked' => in_array($hero, ['Doctor Strange', 'Magneto', 'Groot']) ? rand(5000, 15000) : rand(0, 3000)
            ];
        }
        
        $mapData['team1_composition'][] = [
            'player_id' => $player->id,
            'player_name' => $player->name,
            'name' => $player->name,
            'hero' => $playerHeroes[0], // Primary hero for display
            'heroes_played' => $heroesPlayed,
            'eliminations' => array_sum(array_column($heroesPlayed, 'eliminations')),
            'deaths' => array_sum(array_column($heroesPlayed, 'deaths')),
            'assists' => array_sum(array_column($heroesPlayed, 'assists')),
            'damage' => array_sum(array_column($heroesPlayed, 'damage')),
            'healing' => array_sum(array_column($heroesPlayed, 'healing')),
            'damage_blocked' => array_sum(array_column($heroesPlayed, 'damage_blocked'))
        ];
    }
    
    // Add Team 2 players with 5 heroes each
    foreach ($team2Players as $playerIndex => $player) {
        $heroesPlayed = [];
        
        // Shuffle hero pool and pick 5 different heroes for this player
        $playerHeroes = array_slice(array_reverse($heroPool), $playerIndex * 3, 5);
        if (count($playerHeroes) < 5) {
            $playerHeroes = array_slice(array_reverse($heroPool), 0, 5);
        }
        
        foreach ($playerHeroes as $heroIndex => $hero) {
            // Generate realistic stats for each hero
            $isWinner = ($winner == 2);
            $heroesPlayed[] = [
                'hero' => $hero,
                'eliminations' => $isWinner ? rand(15, 35) : rand(8, 20),
                'deaths' => $isWinner ? rand(3, 10) : rand(8, 15),
                'assists' => rand(5, 20),
                'damage' => rand(8000, 25000),
                'healing' => in_array($hero, ['Mantis', 'Luna Snow', 'Jeff the Shark']) ? rand(10000, 30000) : rand(0, 5000),
                'damage_blocked' => in_array($hero, ['Doctor Strange', 'Magneto', 'Groot']) ? rand(5000, 15000) : rand(0, 3000)
            ];
        }
        
        $mapData['team2_composition'][] = [
            'player_id' => $player->id,
            'player_name' => $player->name,
            'name' => $player->name,
            'hero' => $playerHeroes[0], // Primary hero for display
            'heroes_played' => $heroesPlayed,
            'eliminations' => array_sum(array_column($heroesPlayed, 'eliminations')),
            'deaths' => array_sum(array_column($heroesPlayed, 'deaths')),
            'assists' => array_sum(array_column($heroesPlayed, 'assists')),
            'damage' => array_sum(array_column($heroesPlayed, 'damage')),
            'healing' => array_sum(array_column($heroesPlayed, 'healing')),
            'damage_blocked' => array_sum(array_column($heroesPlayed, 'damage_blocked'))
        ];
    }
    
    $mapsData[] = $mapData;
    echo "  ✓ Added " . count($mapData['team1_composition']) . " Team 1 players with 5 heroes each\n";
    echo "  ✓ Added " . count($mapData['team2_composition']) . " Team 2 players with 5 heroes each\n";
}

// Create the match as LIVE on map 3
$matchId = DB::table('matches')->insertGetId([
    'event_id' => $event->id,
    'team1_id' => $team1->id,
    'team2_id' => $team2->id,
    'team1_score' => 2, // Sentinels winning 2 maps
    'team2_score' => 1, // G2 winning 1 map
    'series_score_team1' => 2,
    'series_score_team2' => 1,
    'status' => 'live',
    'format' => 'BO5',
    'scheduled_at' => now()->subHours(2),
    'started_at' => now()->subHours(2),
    'ended_at' => null,
    'current_map' => 3, // Currently on Map 3
    'maps_data' => json_encode($mapsData),
    'created_at' => now(),
    'updated_at' => now()
]);

echo "\n✅ LIVE BO5 Match created successfully!\n";
echo "Match ID: {$matchId}\n";
echo "URL: https://staging.mrvl.net/#match-detail/{$matchId}\n";
echo "\nMatch Summary:\n";
echo "- Status: 🔴 LIVE (Currently on Map 3)\n";
echo "- Event: Marvel Rivals Championship 2025\n";
echo "- Teams: Sentinels vs NRG Esports\n";
echo "- Format: BO5\n";
echo "- Current Score: 2-1 (Sentinels leading)\n";
echo "- Maps to play: 5 total\n";
echo "- Heroes per player per map: 5\n";
echo "- Total unique heroes in pool: " . count($heroPool) . "\n";