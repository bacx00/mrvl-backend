<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\MvrlMatch;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Define hero pools for variety
$heroPool = [
    'Duelist' => ['Spider-Man', 'Iron Man', 'Star-Lord', 'Hela', 'Black Widow', 'Hawkeye', 'Winter Soldier', 'Psylocke', 'Moon Knight', 'Punisher', 'Scarlet Witch', 'Storm'],
    'Vanguard' => ['Captain America', 'Thor', 'Hulk', 'Venom', 'Groot', 'Magneto', 'Doctor Strange', 'Peni Parker'],
    'Strategist' => ['Mantis', 'Luna Snow', 'Rocket Raccoon', 'Loki', 'Jeff the Land Shark', 'Adam Warlock', 'Cloak & Dagger', 'Invisible Woman']
];

// Map pool
$maps = [
    'Yggsgard: Yggdrasill Path',
    'Tokyo 2099: Shin-Shibuya',
    'Klyntar: Symbiotic Surface',
    'Wakanda: Birnin D\'Jata',
    'Midtown Manhattan: Spider-Islands'
];

$modes = ['Convoy', 'Domination', 'Convergence', 'Convoy', 'Domination'];

// Function to generate realistic stats based on hero role
function generateHeroStats($hero, $isWinningTeam = false) {
    $baseStats = [
        'Spider-Man' => ['eliminations' => rand(15, 35), 'deaths' => rand(3, 12), 'assists' => rand(8, 20), 'damage' => rand(8000, 18000), 'healing' => 0, 'damage_blocked' => rand(500, 2000)],
        'Iron Man' => ['eliminations' => rand(18, 40), 'deaths' => rand(4, 15), 'assists' => rand(5, 15), 'damage' => rand(12000, 25000), 'healing' => 0, 'damage_blocked' => rand(200, 800)],
        'Star-Lord' => ['eliminations' => rand(20, 38), 'deaths' => rand(3, 12), 'assists' => rand(10, 18), 'damage' => rand(11000, 22000), 'healing' => 0, 'damage_blocked' => rand(300, 1000)],
        'Hela' => ['eliminations' => rand(22, 45), 'deaths' => rand(2, 10), 'assists' => rand(8, 16), 'damage' => rand(15000, 28000), 'healing' => 0, 'damage_blocked' => rand(100, 500)],
        'Black Widow' => ['eliminations' => rand(18, 35), 'deaths' => rand(4, 14), 'assists' => rand(12, 22), 'damage' => rand(10000, 20000), 'healing' => 0, 'damage_blocked' => rand(200, 800)],
        'Hawkeye' => ['eliminations' => rand(16, 32), 'deaths' => rand(3, 11), 'assists' => rand(15, 25), 'damage' => rand(9000, 18000), 'healing' => 0, 'damage_blocked' => rand(150, 600)],
        'Winter Soldier' => ['eliminations' => rand(19, 36), 'deaths' => rand(4, 13), 'assists' => rand(10, 18), 'damage' => rand(11000, 21000), 'healing' => 0, 'damage_blocked' => rand(400, 1200)],
        'Psylocke' => ['eliminations' => rand(21, 38), 'deaths' => rand(5, 15), 'assists' => rand(8, 16), 'damage' => rand(13000, 24000), 'healing' => 0, 'damage_blocked' => rand(300, 900)],
        'Moon Knight' => ['eliminations' => rand(17, 33), 'deaths' => rand(4, 12), 'assists' => rand(11, 20), 'damage' => rand(10000, 19000), 'healing' => 0, 'damage_blocked' => rand(250, 750)],
        'Punisher' => ['eliminations' => rand(20, 40), 'deaths' => rand(3, 11), 'assists' => rand(6, 14), 'damage' => rand(14000, 26000), 'healing' => 0, 'damage_blocked' => rand(200, 700)],
        'Scarlet Witch' => ['eliminations' => rand(23, 42), 'deaths' => rand(4, 13), 'assists' => rand(9, 17), 'damage' => rand(16000, 30000), 'healing' => 0, 'damage_blocked' => rand(150, 500)],
        'Storm' => ['eliminations' => rand(19, 35), 'deaths' => rand(3, 12), 'assists' => rand(12, 22), 'damage' => rand(12000, 23000), 'healing' => 0, 'damage_blocked' => rand(200, 600)],
        'Captain America' => ['eliminations' => rand(8, 18), 'deaths' => rand(2, 8), 'assists' => rand(20, 35), 'damage' => rand(5000, 12000), 'healing' => 0, 'damage_blocked' => rand(15000, 30000)],
        'Thor' => ['eliminations' => rand(10, 20), 'deaths' => rand(3, 10), 'assists' => rand(18, 30), 'damage' => rand(7000, 14000), 'healing' => rand(2000, 5000), 'damage_blocked' => rand(12000, 25000)],
        'Hulk' => ['eliminations' => rand(12, 22), 'deaths' => rand(4, 12), 'assists' => rand(15, 28), 'damage' => rand(8000, 16000), 'healing' => 0, 'damage_blocked' => rand(18000, 35000)],
        'Venom' => ['eliminations' => rand(14, 25), 'deaths' => rand(3, 10), 'assists' => rand(16, 28), 'damage' => rand(9000, 17000), 'healing' => rand(1000, 3000), 'damage_blocked' => rand(14000, 28000)],
        'Groot' => ['eliminations' => rand(6, 15), 'deaths' => rand(2, 7), 'assists' => rand(25, 40), 'damage' => rand(4000, 10000), 'healing' => 0, 'damage_blocked' => rand(20000, 40000)],
        'Magneto' => ['eliminations' => rand(9, 19), 'deaths' => rand(3, 9), 'assists' => rand(22, 35), 'damage' => rand(6000, 13000), 'healing' => 0, 'damage_blocked' => rand(16000, 32000)],
        'Doctor Strange' => ['eliminations' => rand(7, 16), 'deaths' => rand(2, 8), 'assists' => rand(24, 38), 'damage' => rand(5000, 11000), 'healing' => 0, 'damage_blocked' => rand(14000, 28000)],
        'Peni Parker' => ['eliminations' => rand(11, 21), 'deaths' => rand(3, 10), 'assists' => rand(19, 32), 'damage' => rand(7000, 15000), 'healing' => 0, 'damage_blocked' => rand(17000, 34000)],
        'Mantis' => ['eliminations' => rand(3, 10), 'deaths' => rand(2, 7), 'assists' => rand(30, 45), 'damage' => rand(2000, 6000), 'healing' => rand(12000, 25000), 'damage_blocked' => rand(500, 2000)],
        'Luna Snow' => ['eliminations' => rand(4, 12), 'deaths' => rand(2, 8), 'assists' => rand(28, 42), 'damage' => rand(3000, 7000), 'healing' => rand(10000, 22000), 'damage_blocked' => rand(600, 2500)],
        'Rocket Raccoon' => ['eliminations' => rand(5, 14), 'deaths' => rand(3, 9), 'assists' => rand(32, 48), 'damage' => rand(4000, 9000), 'healing' => rand(8000, 18000), 'damage_blocked' => rand(400, 1800)],
        'Loki' => ['eliminations' => rand(6, 15), 'deaths' => rand(3, 10), 'assists' => rand(26, 40), 'damage' => rand(5000, 10000), 'healing' => rand(9000, 20000), 'damage_blocked' => rand(700, 2200)],
        'Jeff the Land Shark' => ['eliminations' => rand(2, 8), 'deaths' => rand(1, 5), 'assists' => rand(35, 50), 'damage' => rand(1000, 4000), 'healing' => rand(14000, 28000), 'damage_blocked' => rand(300, 1200)],
        'Adam Warlock' => ['eliminations' => rand(4, 11), 'deaths' => rand(2, 7), 'assists' => rand(33, 47), 'damage' => rand(3000, 7000), 'healing' => rand(11000, 23000), 'damage_blocked' => rand(500, 1800)],
        'Cloak & Dagger' => ['eliminations' => rand(5, 13), 'deaths' => rand(2, 8), 'assists' => rand(31, 45), 'damage' => rand(3500, 8000), 'healing' => rand(10000, 21000), 'damage_blocked' => rand(600, 2000)],
        'Invisible Woman' => ['eliminations' => rand(3, 10), 'deaths' => rand(2, 6), 'assists' => rand(34, 48), 'damage' => rand(2500, 6500), 'healing' => rand(12000, 24000), 'damage_blocked' => rand(8000, 18000)]
    ];
    
    $stats = $baseStats[$hero] ?? [
        'eliminations' => rand(10, 25),
        'deaths' => rand(3, 10),
        'assists' => rand(10, 25),
        'damage' => rand(5000, 15000),
        'healing' => rand(0, 5000),
        'damage_blocked' => rand(500, 5000)
    ];
    
    // Boost stats slightly for winning team
    if ($isWinningTeam) {
        $stats['eliminations'] = intval($stats['eliminations'] * 1.2);
        $stats['deaths'] = intval($stats['deaths'] * 0.8);
        $stats['assists'] = intval($stats['assists'] * 1.1);
    }
    
    $stats['kda_ratio'] = $stats['deaths'] > 0 ? 
        number_format(($stats['eliminations'] + $stats['assists']) / $stats['deaths'], 2) : 
        number_format($stats['eliminations'] + $stats['assists'], 2);
    
    return $stats;
}

// Function to get a unique hero for a player
function getUniqueHero(&$usedHeroes, $playerName, $mapNumber) {
    global $heroPool;
    
    if (!isset($usedHeroes[$playerName])) {
        $usedHeroes[$playerName] = [];
    }
    
    // Define role preferences for each player
    $rolePreferences = [
        'delenaa' => ['Duelist', 'Duelist', 'Vanguard'],
        'terra' => ['Duelist', 'Duelist', 'Strategist'],
        'hxrvey' => ['Duelist', 'Duelist', 'Vanguard'],
        'sjp' => ['Vanguard', 'Duelist', 'Vanguard'],
        'ttk' => ['Strategist', 'Strategist', 'Duelist'],
        'vinnie' => ['Strategist', 'Vanguard', 'Strategist'],
        'dragon97' => ['Duelist', 'Duelist', 'Vanguard'],
        'thunder99' => ['Duelist', 'Duelist', 'Strategist'],
        'genius83' => ['Vanguard', 'Vanguard', 'Duelist'],
        'scholar50' => ['Vanguard', 'Duelist', 'Vanguard'],
        'castle50' => ['Strategist', 'Strategist', 'Duelist'],
        'fortress99' => ['Strategist', 'Vanguard', 'Strategist']
    ];
    
    $preferredRoles = $rolePreferences[$playerName] ?? ['Duelist', 'Vanguard', 'Strategist'];
    $selectedRole = $preferredRoles[array_rand($preferredRoles)];
    
    $availableHeroes = array_diff($heroPool[$selectedRole], $usedHeroes[$playerName]);
    
    if (empty($availableHeroes)) {
        // If all heroes in preferred role are used, pick from other roles
        foreach ($heroPool as $role => $heroes) {
            $availableHeroes = array_diff($heroes, $usedHeroes[$playerName]);
            if (!empty($availableHeroes)) {
                $selectedRole = $role;
                break;
            }
        }
    }
    
    if (empty($availableHeroes)) {
        // Fallback - reuse a hero if necessary
        $availableHeroes = $heroPool[$selectedRole];
    }
    
    $hero = $availableHeroes[array_rand($availableHeroes)];
    $usedHeroes[$playerName][] = $hero;
    
    return ['hero' => $hero, 'role' => $selectedRole];
}

// Create match data
$matchData = [
    'team1_id' => 4, // 100 Thieves
    'team2_id' => 32, // BOOM Esports
    'event_id' => 2,
    'bracket_type' => 'upper',
    'stage_type' => 'semi_final',
    'scheduled_at' => now()->subDays(1),
    'status' => 'completed',
    'format' => 'BO5',
    'best_of' => 5,
    'maps_required_to_win' => 3,
    'total_maps_played' => 5,
    'team1_score' => 3,
    'team2_score' => 2,
    'winner_team_id' => 4,
    'current_map' => 5,
    'viewers' => rand(45000, 65000),
    'maps_data' => []
];

// Track used heroes for each player
$usedHeroes = [];

// Generate 5 maps with 100 Thieves winning 3-2
$mapWinners = [4, 32, 4, 32, 4]; // 100T wins maps 1, 3, 5; BOOM wins maps 2, 4

for ($mapIndex = 0; $mapIndex < 5; $mapIndex++) {
    $mapNumber = $mapIndex + 1;
    $winnerId = $mapWinners[$mapIndex];
    $isTeam1Winner = ($winnerId == 4);
    
    // Team 1 (100 Thieves) composition
    $team1Composition = [];
    $team1Players = [
        ['id' => 405, 'name' => 'delenaa', 'country' => 'CA'],
        ['id' => 406, 'name' => 'terra', 'country' => 'Mexico'],
        ['id' => 407, 'name' => 'hxrvey', 'country' => 'Mexico'],
        ['id' => 408, 'name' => 'sjp', 'country' => 'Mexico'],
        ['id' => 409, 'name' => 'ttk', 'country' => 'Mexico'],
        ['id' => 410, 'name' => 'vinnie', 'country' => 'Mexico']
    ];
    
    foreach ($team1Players as $player) {
        $heroData = getUniqueHero($usedHeroes, $player['name'], $mapNumber);
        $stats = generateHeroStats($heroData['hero'], $isTeam1Winner);
        
        $team1Composition[] = [
            'player_id' => $player['id'],
            'player_name' => $player['name'],
            'username' => $player['name'],
            'name' => $player['name'],
            'country' => $player['country'],
            'nationality' => $player['country'] == 'CA' ? 'Canada' : 'Mexico',
            'hero' => $heroData['hero'],
            'role' => $heroData['role'],
            'eliminations' => $stats['eliminations'],
            'deaths' => $stats['deaths'],
            'assists' => $stats['assists'],
            'damage' => $stats['damage'],
            'healing' => $stats['healing'],
            'damage_blocked' => $stats['damage_blocked'],
            'kda_ratio' => $stats['kda_ratio']
        ];
    }
    
    // Team 2 (BOOM Esports) composition
    $team2Composition = [];
    $team2Players = [
        ['id' => 591, 'name' => 'dragon97', 'country' => 'Malaysia'],
        ['id' => 592, 'name' => 'thunder99', 'country' => 'Malaysia'],
        ['id' => 593, 'name' => 'genius83', 'country' => 'Malaysia'],
        ['id' => 594, 'name' => 'scholar50', 'country' => 'Malaysia'],
        ['id' => 595, 'name' => 'castle50', 'country' => 'Malaysia'],
        ['id' => 596, 'name' => 'fortress99', 'country' => 'Malaysia']
    ];
    
    foreach ($team2Players as $player) {
        $heroData = getUniqueHero($usedHeroes, $player['name'], $mapNumber);
        $stats = generateHeroStats($heroData['hero'], !$isTeam1Winner);
        
        $team2Composition[] = [
            'player_id' => $player['id'],
            'player_name' => $player['name'],
            'username' => $player['name'],
            'name' => $player['name'],
            'country' => $player['country'],
            'nationality' => 'Malaysia',
            'hero' => $heroData['hero'],
            'role' => $heroData['role'],
            'eliminations' => $stats['eliminations'],
            'deaths' => $stats['deaths'],
            'assists' => $stats['assists'],
            'damage' => $stats['damage'],
            'healing' => $stats['healing'],
            'damage_blocked' => $stats['damage_blocked'],
            'kda_ratio' => $stats['kda_ratio']
        ];
    }
    
    // Create map data
    $mapData = [
        'map_number' => $mapNumber,
        'map_name' => $maps[$mapIndex],
        'mode' => $modes[$mapIndex],
        'game_mode' => $modes[$mapIndex],
        'status' => 'completed',
        'winner_id' => $winnerId,
        'team1_score' => $isTeam1Winner ? 2 : 1,
        'team2_score' => $isTeam1Winner ? 1 : 2,
        'duration' => sprintf('%02d:%02d', rand(8, 15), rand(0, 59)),
        'started_at' => now()->subDays(1)->addMinutes($mapIndex * 20),
        'completed_at' => now()->subDays(1)->addMinutes($mapIndex * 20 + 15),
        'overtime' => rand(0, 1) == 1,
        'team1_composition' => $team1Composition,
        'team2_composition' => $team2Composition,
        'team1_heroes' => array_map(fn($p) => $p['hero'], $team1Composition),
        'team2_heroes' => array_map(fn($p) => $p['hero'], $team2Composition),
        'hero_changes' => [] // Could add hero swaps here if needed
    ];
    
    $matchData['maps_data'][] = $mapData;
}

// Insert into database
try {
    DB::beginTransaction();
    
    $match = MvrlMatch::create($matchData);
    
    echo "BO5 match created successfully!\n";
    echo "Match ID: " . $match->id . "\n";
    echo "Format: " . $match->format . "\n";
    echo "Score: 100 Thieves " . $match->team1_score . " - " . $match->team2_score . " BOOM Esports\n";
    echo "Maps played: " . count($match->maps_data) . "\n";
    
    foreach ($match->maps_data as $index => $map) {
        echo "\nMap " . ($index + 1) . ": " . $map['map_name'] . " (" . $map['mode'] . ")\n";
        echo "Winner: " . ($map['winner_id'] == 4 ? '100 Thieves' : 'BOOM Esports') . "\n";
        echo "Duration: " . $map['duration'] . "\n";
    }
    
    DB::commit();
} catch (Exception $e) {
    DB::rollback();
    echo "Error creating match: " . $e->getMessage() . "\n";
}