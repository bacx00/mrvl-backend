<?php

require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Team;
use App\Models\Player;

// Disable foreign key checks
\DB::statement('SET FOREIGN_KEY_CHECKS=0;');

// Clear existing data
Player::truncate();
Team::truncate();

// Re-enable foreign key checks
\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "Creating Marvel Rivals teams and players...\n";

// Team 1: 100 Thieves
$team1 = Team::create([
    'name' => '100 Thieves',
    'short_name' => '100T',
    'logo' => 'https://liquipedia.net/commons/2/26/100_Thieves_lightmode.png',
    'region' => 'Americas',
    'country' => 'US',
    'flag' => 'https://flagcdn.com/16x12/us.png',
    'platform' => 'PC',
    'game' => 'Marvel Rivals',
    'division' => 'Professional',
    'rating' => 1850,
    'rank' => 3,
    'win_rate' => 0.68,
    'points' => 85,
    'record' => '12-5',
    'peak' => 1920,
    'streak' => 3,
    'founded' => '2017-04-01',
    'captain' => 'delenaa',
    'coach' => 'Zikz',
    'website' => 'https://100thieves.com',
    'earnings' => 75000.0,
    'social_media' => json_encode([
        'twitter' => 'https://twitter.com/100thieves',
        'instagram' => 'https://instagram.com/100thieves'
    ]),
    'achievements' => json_encode(['Tournament Wins' => 3]),
    'recent_form' => json_encode(['W', 'W', 'L', 'W', 'W']),
    'player_count' => 6
]);

$team1_players = [
    ['name' => 'delenaa', 'real_name' => 'John Delena', 'role' => 'duelist', 'country' => 'US', 'age' => 23, 'rating' => 1880.5],
    ['name' => 'hxrvey', 'real_name' => 'Harvey Chen', 'role' => 'duelist', 'country' => 'CA', 'age' => 21, 'rating' => 1840.2],
    ['name' => 'SJP', 'real_name' => 'Samuel Peterson', 'role' => 'vanguard', 'country' => 'US', 'age' => 25, 'rating' => 1820.8],
    ['name' => 'TTK', 'real_name' => 'Tyler Kim', 'role' => 'vanguard', 'country' => 'US', 'age' => 22, 'rating' => 1830.1],
    ['name' => 'Terra', 'real_name' => 'Marcus Davis', 'role' => 'strategist', 'country' => 'US', 'age' => 26, 'rating' => 1860.9],
    ['name' => 'Vinnie', 'real_name' => 'Vincent Rodriguez', 'role' => 'strategist', 'country' => 'US', 'age' => 24, 'rating' => 1845.3]
];

foreach ($team1_players as $p) {
    Player::create([
        'name' => $p['name'],
        'username' => $p['name'],
        'real_name' => $p['real_name'],
        'team_id' => $team1->id,
        'role' => $p['role'],
        'main_hero' => 'Spider-Man',
        'region' => 'Americas',
        'country' => $p['country'],
        'country_code' => $p['country'],
        'rating' => $p['rating'],
        'elo_rating' => $p['rating'],
        'age' => $p['age'],
        'earnings' => 12500.0,
        'twitter' => 'https://twitter.com/' . strtolower($p['name']),
        'twitch' => 'https://twitch.tv/' . strtolower($p['name']),
        'biography' => 'Professional Marvel Rivals ' . $p['role'] . ' for 100 Thieves.',
        'total_matches' => rand(50, 100),
        'tournaments_played' => rand(8, 20),
        'overall_kda' => round(rand(80, 150) / 100, 2)
    ]);
}

echo "Created 100 Thieves with 6 players\n";

// Team 2: Sentinels
$team2 = Team::create([
    'name' => 'Sentinels',
    'short_name' => 'SEN', 
    'logo' => 'https://liquipedia.net/commons/2/2a/Sentinels_lightmode.png',
    'region' => 'Americas',
    'country' => 'US',
    'flag' => 'https://flagcdn.com/16x12/us.png',
    'platform' => 'PC',
    'game' => 'Marvel Rivals',
    'division' => 'Professional',
    'rating' => 1920,
    'rank' => 1,
    'win_rate' => 0.78,
    'points' => 95,
    'record' => '15-2',
    'peak' => 1950,
    'streak' => 5,
    'founded' => '2018-02-15',
    'captain' => 'TenZ',
    'coach' => 'Kaplan',
    'website' => 'https://sentinels.gg',
    'earnings' => 125000.0,
    'social_media' => json_encode([
        'twitter' => 'https://twitter.com/sentinels',
        'instagram' => 'https://instagram.com/sentinels'
    ]),
    'achievements' => json_encode(['Tournament Wins' => 5]),
    'recent_form' => json_encode(['W', 'W', 'W', 'W', 'W']),
    'player_count' => 6
]);

$team2_players = [
    ['name' => 'TenZ', 'real_name' => 'Tyson Ngo', 'role' => 'duelist', 'country' => 'CA', 'age' => 23, 'rating' => 1950.8],
    ['name' => 'zekken', 'real_name' => 'Zachary Patrone', 'role' => 'duelist', 'country' => 'US', 'age' => 21, 'rating' => 1920.5],
    ['name' => 'johnqt', 'real_name' => 'John Larsen', 'role' => 'vanguard', 'country' => 'US', 'age' => 25, 'rating' => 1890.2],
    ['name' => 'Sacy', 'real_name' => 'Gustavo Rossi', 'role' => 'vanguard', 'country' => 'BR', 'age' => 27, 'rating' => 1885.9],
    ['name' => 'pancada', 'real_name' => 'Bryan Luna', 'role' => 'strategist', 'country' => 'BR', 'age' => 22, 'rating' => 1900.3],
    ['name' => 'zellsis', 'real_name' => 'Jordan Montemurro', 'role' => 'strategist', 'country' => 'US', 'age' => 26, 'rating' => 1875.1]
];

foreach ($team2_players as $p) {
    Player::create([
        'name' => $p['name'],
        'username' => $p['name'],
        'real_name' => $p['real_name'],
        'team_id' => $team2->id,
        'role' => $p['role'],
        'main_hero' => 'Iron Man',
        'region' => 'Americas',
        'country' => $p['country'],
        'country_code' => $p['country'],
        'rating' => $p['rating'],
        'elo_rating' => $p['rating'],
        'age' => $p['age'],
        'earnings' => 20833.0,
        'twitter' => 'https://twitter.com/' . strtolower($p['name']),
        'twitch' => 'https://twitch.tv/' . strtolower($p['name']),
        'biography' => 'Professional Marvel Rivals ' . $p['role'] . ' for Sentinels.',
        'total_matches' => rand(60, 120),
        'tournaments_played' => rand(12, 25),
        'overall_kda' => round(rand(90, 170) / 100, 2)
    ]);
}

echo "Created Sentinels with 6 players\n";

// Team 3: G2 Esports
$team3 = Team::create([
    'name' => 'G2 Esports',
    'short_name' => 'G2',
    'logo' => 'https://liquipedia.net/commons/thumb/d/da/G2_Esports_lightmode.png',
    'region' => 'EMEA',
    'country' => 'DE',
    'flag' => 'https://flagcdn.com/16x12/de.png',
    'platform' => 'PC',
    'game' => 'Marvel Rivals',
    'division' => 'Professional',
    'rating' => 1870,
    'rank' => 2,
    'win_rate' => 0.72,
    'points' => 82,
    'record' => '11-4',
    'peak' => 1920,
    'streak' => 2,
    'founded' => '2014-02-24',
    'captain' => 'leaf',
    'coach' => 'ReynAD27',
    'website' => 'https://g2esports.com',
    'earnings' => 80000.0,
    'social_media' => json_encode([
        'twitter' => 'https://twitter.com/G2esports',
        'instagram' => 'https://instagram.com/g2esports'
    ]),
    'achievements' => json_encode(['Tournament Wins' => 2]),
    'recent_form' => json_encode(['W', 'L', 'W', 'W', 'W']),
    'player_count' => 6
]);

$team3_players = [
    ['name' => 'leaf', 'real_name' => 'Nathan Orf', 'role' => 'duelist', 'country' => 'US', 'age' => 21, 'rating' => 1910.4],
    ['name' => 'trent', 'real_name' => 'Trent Cairns', 'role' => 'duelist', 'country' => 'US', 'age' => 20, 'rating' => 1880.7],
    ['name' => 'valyn', 'real_name' => 'Jacob Batio', 'role' => 'vanguard', 'country' => 'US', 'age' => 22, 'rating' => 1850.2],
    ['name' => 'JonahP', 'real_name' => 'Jonah Pulice', 'role' => 'vanguard', 'country' => 'US', 'age' => 21, 'rating' => 1845.8],
    ['name' => 'neT', 'real_name' => 'Josh Seangpan', 'role' => 'strategist', 'country' => 'US', 'age' => 22, 'rating' => 1870.9],
    ['name' => 'icy', 'real_name' => 'Ian Baker', 'role' => 'strategist', 'country' => 'US', 'age' => 19, 'rating' => 1860.1]
];

foreach ($team3_players as $p) {
    Player::create([
        'name' => $p['name'],
        'username' => $p['name'],
        'real_name' => $p['real_name'],
        'team_id' => $team3->id,
        'role' => $p['role'],
        'main_hero' => 'Captain America',
        'region' => 'EMEA',
        'country' => $p['country'],
        'country_code' => $p['country'],
        'rating' => $p['rating'],
        'elo_rating' => $p['rating'],
        'age' => $p['age'],
        'earnings' => 13333.0,
        'twitter' => 'https://twitter.com/' . strtolower($p['name']),
        'twitch' => 'https://twitch.tv/' . strtolower($p['name']),
        'biography' => 'Professional Marvel Rivals ' . $p['role'] . ' for G2 Esports.',
        'total_matches' => rand(50, 100),
        'tournaments_played' => rand(10, 22),
        'overall_kda' => round(rand(85, 155) / 100, 2)
    ]);
}

echo "Created G2 Esports with 6 players\n";

// Team 4: Paper Rex
$team4 = Team::create([
    'name' => 'Paper Rex',
    'short_name' => 'PRX',
    'logo' => 'https://liquipedia.net/commons/thumb/b/b0/Paper_Rex_lightmode.png',
    'region' => 'Asia Pacific',
    'country' => 'SG',
    'flag' => 'https://flagcdn.com/16x12/sg.png',
    'platform' => 'PC',
    'game' => 'Marvel Rivals',
    'division' => 'Professional',
    'rating' => 1800,
    'rank' => 4,
    'win_rate' => 0.65,
    'points' => 75,
    'record' => '10-5',
    'peak' => 1850,
    'streak' => 1,
    'founded' => '2020-09-20',
    'captain' => 'Jinggg',
    'coach' => 'alecks',
    'website' => 'https://paperrex.gg',
    'earnings' => 65000.0,
    'social_media' => json_encode([
        'twitter' => 'https://twitter.com/paperrex',
        'instagram' => 'https://instagram.com/paperrex'
    ]),
    'achievements' => json_encode(['Tournament Wins' => 2]),
    'recent_form' => json_encode(['W', 'W', 'L', 'W', 'L']),
    'player_count' => 6
]);

$team4_players = [
    ['name' => 'Jinggg', 'real_name' => 'Wang Jing Jie', 'role' => 'duelist', 'country' => 'SG', 'age' => 21, 'rating' => 1860.5],
    ['name' => 'f0rsakeN', 'real_name' => 'Jason Susanto', 'role' => 'duelist', 'country' => 'ID', 'age' => 22, 'rating' => 1840.8],
    ['name' => 'mindfreak', 'real_name' => 'Aaron Leonhart', 'role' => 'vanguard', 'country' => 'ID', 'age' => 25, 'rating' => 1790.3],
    ['name' => 'Benkai', 'real_name' => 'Benedict Tan', 'role' => 'vanguard', 'country' => 'SG', 'age' => 28, 'rating' => 1780.7],
    ['name' => 'marved', 'real_name' => 'Jimmy Nguyen', 'role' => 'strategist', 'country' => 'VN', 'age' => 24, 'rating' => 1810.2],
    ['name' => 'd4v41', 'real_name' => 'Davai Kunthara', 'role' => 'strategist', 'country' => 'TH', 'age' => 21, 'rating' => 1795.9]
];

foreach ($team4_players as $p) {
    Player::create([
        'name' => $p['name'],
        'username' => $p['name'],
        'real_name' => $p['real_name'],
        'team_id' => $team4->id,
        'role' => $p['role'],
        'main_hero' => 'Thor',
        'region' => 'Asia Pacific',
        'country' => $p['country'],
        'country_code' => $p['country'],
        'rating' => $p['rating'],
        'elo_rating' => $p['rating'],
        'age' => $p['age'],
        'earnings' => 10833.0,
        'twitter' => 'https://twitter.com/' . strtolower($p['name']),
        'twitch' => 'https://twitch.tv/' . strtolower($p['name']),
        'biography' => 'Professional Marvel Rivals ' . $p['role'] . ' for Paper Rex.',
        'total_matches' => rand(40, 90),
        'tournaments_played' => rand(8, 18),
        'overall_kda' => round(rand(75, 145) / 100, 2)
    ]);
}

echo "Created Paper Rex with 6 players\n";

// Team 5: GenG
$team5 = Team::create([
    'name' => 'GenG',
    'short_name' => 'GEN',
    'logo' => 'https://liquipedia.net/commons/thumb/1/1c/Gen.G_lightmode.png',
    'region' => 'Asia Pacific',
    'country' => 'KR',
    'flag' => 'https://flagcdn.com/16x12/kr.png',
    'platform' => 'PC',
    'game' => 'Marvel Rivals',
    'division' => 'Professional',
    'rating' => 1770,
    'rank' => 5,
    'win_rate' => 0.62,
    'points' => 68,
    'record' => '9-6',
    'peak' => 1820,
    'streak' => -1,
    'founded' => '2017-05-01',
    'captain' => 't3xture',
    'coach' => 'glow',
    'website' => 'https://geng.gg',
    'earnings' => 35000.0,
    'social_media' => json_encode([
        'twitter' => 'https://twitter.com/geng',
        'instagram' => 'https://instagram.com/geng'
    ]),
    'achievements' => json_encode(['Tournament Wins' => 1]),
    'recent_form' => json_encode(['L', 'W', 'W', 'L', 'W']),
    'player_count' => 6
]);

$team5_players = [
    ['name' => 't3xture', 'real_name' => 'Kim Na-ra', 'role' => 'duelist', 'country' => 'KR', 'age' => 22, 'rating' => 1810.6],
    ['name' => 'Meteor', 'real_name' => 'Kim Tae-O', 'role' => 'duelist', 'country' => 'KR', 'age' => 21, 'rating' => 1790.4],
    ['name' => 'Munchkin', 'real_name' => 'Byeon Sang-beom', 'role' => 'vanguard', 'country' => 'KR', 'age' => 25, 'rating' => 1760.8],
    ['name' => 'flashback', 'real_name' => 'Lee Min-hyeok', 'role' => 'vanguard', 'country' => 'KR', 'age' => 20, 'rating' => 1755.2],
    ['name' => 'Karon', 'real_name' => 'Kim Won-tae', 'role' => 'strategist', 'country' => 'KR', 'age' => 19, 'rating' => 1780.7],
    ['name' => 'Lakia', 'real_name' => 'Kim Jong-min', 'role' => 'strategist', 'country' => 'KR', 'age' => 23, 'rating' => 1765.3]
];

foreach ($team5_players as $p) {
    Player::create([
        'name' => $p['name'],
        'username' => $p['name'],
        'real_name' => $p['real_name'],
        'team_id' => $team5->id,
        'role' => $p['role'],
        'main_hero' => 'Hulk',
        'region' => 'Asia Pacific',
        'country' => $p['country'],
        'country_code' => $p['country'],
        'rating' => $p['rating'],
        'elo_rating' => $p['rating'],
        'age' => $p['age'],
        'earnings' => 5833.0,
        'twitter' => 'https://twitter.com/' . strtolower($p['name']),
        'twitch' => 'https://twitch.tv/' . strtolower($p['name']),
        'biography' => 'Professional Marvel Rivals ' . $p['role'] . ' for GenG.',
        'total_matches' => rand(30, 80),
        'tournaments_played' => rand(6, 15),
        'overall_kda' => round(rand(70, 135) / 100, 2)
    ]);
}

echo "Created GenG with 6 players\n";

echo "\n=== DATA IMPORT COMPLETED ===\n";
echo "Total teams created: " . Team::count() . "\n";
echo "Total players created: " . Player::count() . "\n";
echo "All teams have 6 players, coaches, proper ELO ratings, social media, and country data\n";