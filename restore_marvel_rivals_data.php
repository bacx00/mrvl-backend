<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Restoring Marvel Rivals Teams and Players...\n";

// Essential Marvel Rivals teams data
$teams = [
    // North America
    ['name' => 'Sentinels', 'short_name' => 'SEN', 'region' => 'NA', 'country' => 'US', 'rating' => 2195],
    ['name' => 'Cloud9', 'short_name' => 'C9', 'region' => 'NA', 'country' => 'US', 'rating' => 2185],
    ['name' => '100 Thieves', 'short_name' => '100T', 'region' => 'NA', 'country' => 'US', 'rating' => 2080],
    ['name' => 'Team Liquid', 'short_name' => 'TL', 'region' => 'NA', 'country' => 'US', 'rating' => 2165],
    ['name' => 'OpTic Gaming', 'short_name' => 'OG', 'region' => 'NA', 'country' => 'US', 'rating' => 2150],
    ['name' => 'FaZe Clan', 'short_name' => 'FAZE', 'region' => 'NA', 'country' => 'US', 'rating' => 2135],
    ['name' => 'NRG Esports', 'short_name' => 'NRG', 'region' => 'NA', 'country' => 'US', 'rating' => 2125],
    ['name' => 'TSM', 'short_name' => 'TSM', 'region' => 'NA', 'country' => 'US', 'rating' => 2090],
    
    // China
    ['name' => 'Nova Esports', 'short_name' => 'NOVA', 'region' => 'CN', 'country' => 'CN', 'rating' => 2200],
    ['name' => 'FunPlus Phoenix', 'short_name' => 'FPX', 'region' => 'CN', 'country' => 'CN', 'rating' => 2190],
    ['name' => 'Edward Gaming', 'short_name' => 'EDG', 'region' => 'CN', 'country' => 'CN', 'rating' => 2180],
    ['name' => 'JD Gaming', 'short_name' => 'JDG', 'region' => 'CN', 'country' => 'CN', 'rating' => 2160],
    ['name' => 'Bilibili Gaming', 'short_name' => 'BLG', 'region' => 'CN', 'country' => 'CN', 'rating' => 2145],
    
    // EMEA
    ['name' => 'Fnatic', 'short_name' => 'FNC', 'region' => 'EMEA', 'country' => 'GB', 'rating' => 2050],
    ['name' => 'G2 Esports', 'short_name' => 'G2', 'region' => 'EMEA', 'country' => 'DE', 'rating' => 2170],
    ['name' => 'Team Liquid EU', 'short_name' => 'TL EU', 'region' => 'EMEA', 'country' => 'NL', 'rating' => 2010],
    ['name' => 'Virtus.pro', 'short_name' => 'VP', 'region' => 'EMEA', 'country' => 'RU', 'rating' => 2085],
    ['name' => 'NAVI', 'short_name' => 'NAVI', 'region' => 'EMEA', 'country' => 'UA', 'rating' => 1990],
    
    // Asia
    ['name' => 'Gen.G', 'short_name' => 'GENG', 'region' => 'ASIA', 'country' => 'KR', 'rating' => 2070],
    ['name' => 'T1', 'short_name' => 'T1', 'region' => 'ASIA', 'country' => 'KR', 'rating' => 1970],
    ['name' => 'DRX', 'short_name' => 'DRX', 'region' => 'ASIA', 'country' => 'KR', 'rating' => 2005],
    ['name' => 'Paper Rex', 'short_name' => 'PRX', 'region' => 'ASIA', 'country' => 'SG', 'rating' => 1915],
    
    // Oceania
    ['name' => 'Ground Zero Gaming', 'short_name' => 'GZ', 'region' => 'OCE', 'country' => 'AU', 'rating' => 2105],
    ['name' => 'Chiefs Esports Club', 'short_name' => 'CHF', 'region' => 'OCE', 'country' => 'AU', 'rating' => 2025],
    ['name' => 'Mindfreak', 'short_name' => 'MF', 'region' => 'OCE', 'country' => 'AU', 'rating' => 1995],
    ['name' => 'ORDER', 'short_name' => 'ORD', 'region' => 'OCE', 'country' => 'AU', 'rating' => 1975],
    
    // Americas
    ['name' => 'LOUD', 'short_name' => 'LOUD', 'region' => 'AMERICAS', 'country' => 'BR', 'rating' => 2045],
    ['name' => 'FURIA', 'short_name' => 'FUR', 'region' => 'AMERICAS', 'country' => 'BR', 'rating' => 2020],
    ['name' => 'KRÜ Esports', 'short_name' => 'KRU', 'region' => 'AMERICAS', 'country' => 'AR', 'rating' => 1980],
    ['name' => 'Leviatán', 'short_name' => 'LEV', 'region' => 'AMERICAS', 'country' => 'CL', 'rating' => 2000],
];

// Marvel Rivals heroes
$heroes = [
    'Duelist' => ['Spider-Man', 'Iron Man', 'Black Panther', 'Star-Lord', 'Scarlet Witch', 'Storm', 'Winter Soldier', 'Hawkeye'],
    'Vanguard' => ['Hulk', 'Thor', 'Captain America', 'Doctor Strange', 'Magneto', 'Venom', 'Groot', 'Peni Parker'],
    'Strategist' => ['Luna Snow', 'Rocket Raccoon', 'Jeff the Land Shark', 'Adam Warlock', 'Mantis', 'Loki', 'Cloak & Dagger']
];

$roleDistribution = ['Duelist', 'Duelist', 'Vanguard', 'Vanguard', 'Strategist', 'Strategist'];

DB::beginTransaction();
try {
    foreach ($teams as $teamData) {
        $teamData['status'] = 'active';
        $teamData['platform'] = 'PC';
        $teamData['game'] = 'marvel_rivals';
        $teamData['wins'] = rand(30, 45);
        $teamData['losses'] = rand(5, 15);
        $teamData['founded'] = '2025-01-01';
        $teamData['created_at'] = now();
        $teamData['updated_at'] = now();
        
        $teamId = DB::table('teams')->insertGetId($teamData);
        echo "Created team: {$teamData['name']} (ID: $teamId)\n";
        
        // Create 6 players for each team
        for ($i = 1; $i <= 6; $i++) {
            $role = $roleDistribution[$i - 1];
            $heroesForRole = $heroes[$role];
            $mainHero = $heroesForRole[array_rand($heroesForRole)];
            
            $playerData = [
                'username' => $teamData['short_name'] . '_Player' . $i,
                'real_name' => 'Player ' . $i,
                'team_id' => $teamId,
                'role' => $role,
                'main_hero' => $mainHero,
                'country' => $teamData['country'],
                'region' => $teamData['region'],
                'rating' => $teamData['rating'] + rand(-100, 100),
                'peak_rating' => $teamData['rating'] + rand(0, 200),
                'status' => 'active',
                'age' => rand(18, 28),
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            DB::table('players')->insert($playerData);
        }
    }
    
    DB::commit();
    echo "\nSuccessfully restored " . count($teams) . " teams with 6 players each!\n";
    
} catch (Exception $e) {
    DB::rollback();
    echo "Error: " . $e->getMessage() . "\n";
}