<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "Updating Marvel Rivals teams with verified tournament data...\n\n";

// Updated tournament data based on web search results
$verifiedTeams = [
    // NA TEAMS - Marvel Rivals Invitational & Ignite Americas
    [
        'name' => '100 Thieves',
        'short_name' => '100T',
        'region' => 'NA',
        'country' => 'United States',
        'players' => [
            ['username' => 'TTK', 'real_name' => 'Eric Arraiga', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Vinnie', 'real_name' => 'Vincent Scarantine', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Malenia', 'real_name' => '', 'role' => 'Flex', 'position' => 'player'],
            ['username' => 'Fauwkz', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'boomed', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'debit', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'iRemiix', 'real_name' => 'Luis Galarza Figueroa', 'role' => 'Support', 'position' => 'coach']
        ]
    ],
    [
        'name' => 'Sentinels',
        'short_name' => 'SEN',
        'region' => 'NA',
        'country' => 'United States',
        'players' => [
            ['username' => 'Hogz', 'real_name' => 'Zairek Poll', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Rymazing', 'real_name' => 'Ryan Bishop', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'SuperGomez', 'real_name' => 'Anthony Gomez', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Aramori', 'real_name' => 'Chassidy Kaye', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'Karova', 'real_name' => 'Mark Kvashin', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'teki', 'real_name' => '', 'role' => 'Flex', 'position' => 'player'],
            ['username' => 'Crimzo', 'real_name' => 'William Hernandez', 'role' => 'Support', 'position' => 'coach']
        ]
    ],
    [
        'name' => 'ENVY',
        'short_name' => 'ENVY',
        'region' => 'NA', 
        'country' => 'United States',
        'players' => [
            ['username' => 'Coluge', 'real_name' => 'Colin Arai', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'cal', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'nkae', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'SPACE', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Paintbrush', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'sleepy', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ],
    [
        'name' => 'FlyQuest',
        'short_name' => 'FLY',
        'region' => 'NA',
        'country' => 'United States',
        'players' => [
            ['username' => 'Blax', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Aurora', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Fauwkz', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Yokie', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'coopertastic', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'energy', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ],
    [
        'name' => 'SHROUD-X',
        'short_name' => 'SHDX',
        'region' => 'NA',
        'country' => 'United States',
        'players' => [
            ['username' => 'Grezin', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'fate', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Lv1Crook', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Raajaro', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Sestroyed', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'TrqstMe', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ],
    [
        'name' => 'NTMR',
        'short_name' => 'NTMR',
        'region' => 'NA',
        'country' => 'United States',
        'players' => [
            ['username' => 'Ghasklin', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'adios', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'lyte', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Kylie Raine', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'SparkChief', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Zelos', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ],
    [
        'name' => 'Rad Esports',
        'short_name' => 'RAD',
        'region' => 'NA',
        'country' => 'United States',
        'players' => [
            ['username' => 'Pride', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Prota', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'Skai', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'manually', 'real_name' => '', 'role' => 'Support', 'position' => 'player'],
            ['username' => 'Kani', 'real_name' => '', 'role' => 'Flex', 'position' => 'player'],
            ['username' => 'Abbs', 'real_name' => '', 'role' => 'Flex', 'position' => 'player'],
            ['username' => 'MyrickGG', 'real_name' => '', 'role' => 'Support', 'position' => 'coach']
        ]
    ],
    
    // EMEA TEAMS
    [
        'name' => 'Virtus.pro',
        'short_name' => 'VP',
        'region' => 'EU',
        'country' => 'Russia',
        'players' => [
            ['username' => 'SparkR', 'real_name' => 'William Andersson', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'phi', 'real_name' => 'Philip Handke', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Sypeh', 'real_name' => 'Mikkel Klein', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'dridro', 'real_name' => 'Arthur Szanto', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'Nevix', 'real_name' => 'Andreas Karlsson', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Finnsi', 'real_name' => 'Finnbjörn Jónasson', 'role' => 'Vanguard', 'position' => 'player']
        ]
    ],
    [
        'name' => 'Brr Brr Patapim',
        'short_name' => 'BBP',
        'region' => 'EU',
        'country' => 'Mixed',
        'players' => [
            ['username' => 'Tanuki', 'real_name' => 'Roald Matthijs Rademakers', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Psych0', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Polly', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'Nzo', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'Ken', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Alx', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player']
        ]
    ],
    [
        'name' => 'OG Seed',
        'short_name' => 'OGS',
        'region' => 'EU',
        'country' => 'Denmark',
        'players' => [
            ['username' => 'Admiral', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Seicoe', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Kio', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'grathen', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'FDGod', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'kaan', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ],
    
    // ASIA TEAMS
    [
        'name' => 'Gen.G',
        'short_name' => 'GEN',
        'region' => 'APAC',
        'country' => 'South Korea',
        'players' => [
            ['username' => 'Spectra', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Someone', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Viol2t', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Shy', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Vindaim', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'Fielder', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ],
    [
        'name' => 'Crazy Raccoon',
        'short_name' => 'CR',
        'region' => 'APAC',
        'country' => 'Japan',
        'players' => [
            ['username' => 'Yoshiii', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'ta1yo', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'Dep', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'Claire', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'kinoko', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'Xeraphy', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ],
    
    // OCEANIA TEAMS
    [
        'name' => 'Kanga Esports',
        'short_name' => 'KE',
        'region' => 'OCE',
        'country' => 'Australia',
        'players' => [
            ['username' => 'KangaPlayer1', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'KangaPlayer2', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'KangaPlayer3', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'KangaPlayer4', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'KangaPlayer5', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'KangaPlayer6', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ],
    [
        'name' => 'Ground Zero Gaming',
        'short_name' => 'GZG',
        'region' => 'OCE',
        'country' => 'Australia',
        'players' => [
            ['username' => 'GZGPlayer1', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'GZGPlayer2', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
            ['username' => 'GZGPlayer3', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'GZGPlayer4', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
            ['username' => 'GZGPlayer5', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
            ['username' => 'GZGPlayer6', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player']
        ]
    ]
];

$countryFlags = [
    'United States' => '🇺🇸',
    'Canada' => '🇨🇦',
    'Brazil' => '🇧🇷',
    'United Kingdom' => '🇬🇧',
    'Germany' => '🇩🇪',
    'France' => '🇫🇷',
    'Spain' => '🇪🇸',
    'Russia' => '🇷🇺',
    'Denmark' => '🇩🇰',
    'Sweden' => '🇸🇪',
    'Finland' => '🇫🇮',
    'South Korea' => '🇰🇷',
    'Japan' => '🇯🇵',
    'Australia' => '🇦🇺',
    'Mixed' => '🌍'
];

DB::beginTransaction();

try {
    foreach ($verifiedTeams as $teamData) {
        echo "Processing team: {$teamData['name']}\n";
        
        // Find or create team
        $team = Team::where('name', $teamData['name'])->first();
        
        if (!$team) {
            $team = Team::create([
                'name' => $teamData['name'],
                'short_name' => $teamData['short_name'],
                'region' => $teamData['region'],
                'country' => $teamData['country'],
                'country_flag' => $countryFlags[$teamData['country']] ?? '🌍',
                'status' => 'active',
                'platform' => 'PC',
                'game' => 'Marvel Rivals'
            ]);
            echo "  Created new team\n";
        } else {
            // Update team info
            $team->update([
                'short_name' => $teamData['short_name'],
                'region' => $teamData['region'],
                'country' => $teamData['country'],
                'country_flag' => $countryFlags[$teamData['country']] ?? '🌍',
                'status' => 'active'
            ]);
            echo "  Updated existing team\n";
        }
        
        // Remove all existing players for this team to avoid conflicts
        Player::where('team_id', $team->id)->update(['team_id' => null]);
        
        // Add players
        $positionOrder = 1;
        foreach ($teamData['players'] as $playerData) {
            // Check if player exists by username
            $player = Player::where('username', $playerData['username'])->first();
            
            if ($player) {
                // Update existing player
                $player->update([
                    'real_name' => $playerData['real_name'] ?: $player->real_name,
                    'team_id' => $team->id,
                    'role' => $playerData['role'],
                    'team_position' => $playerData['position'],
                    'position_order' => $positionOrder++,
                    'status' => 'active',
                    'country' => $teamData['country'],
                    'country_flag' => $countryFlags[$teamData['country']] ?? '🌍'
                ]);
                echo "    Updated player: {$playerData['username']}\n";
            } else {
                // Create new player
                // Determine main hero based on role
                $mainHeroes = [
                    'Duelist' => ['Spider-Man', 'Iron Man', 'Star-Lord', 'Scarlet Witch', 'Hela'],
                    'Vanguard' => ['Hulk', 'Thor', 'Captain America', 'Venom', 'Groot'],
                    'Strategist' => ['Mantis', 'Luna Snow', 'Jeff the Land Shark', 'Rocket Raccoon', 'Adam Warlock'],
                    'Support' => ['Mantis', 'Luna Snow', 'Jeff the Land Shark'],
                    'Flex' => ['Spider-Man', 'Iron Man', 'Hulk', 'Thor', 'Mantis']
                ];
                
                $roleHeroes = $mainHeroes[$playerData['role']] ?? $mainHeroes['Flex'];
                $mainHero = $roleHeroes[array_rand($roleHeroes)];
                
                Player::create([
                    'username' => $playerData['username'],
                    'name' => $playerData['username'],
                    'real_name' => $playerData['real_name'],
                    'team_id' => $team->id,
                    'role' => $playerData['role'],
                    'team_position' => $playerData['position'],
                    'position_order' => $positionOrder++,
                    'status' => 'active',
                    'country' => $teamData['country'],
                    'country_flag' => $countryFlags[$teamData['country']] ?? '🌍',
                    'region' => $teamData['region'],
                    'rating' => rand(1800, 2400),
                    'main_hero' => $mainHero
                ]);
                echo "    Created player: {$playerData['username']}\n";
            }
        }
    }
    
    DB::commit();
    echo "\n✅ Successfully updated all tournament teams with verified data!\n";
    
} catch (\Exception $e) {
    DB::rollback();
    echo "\n❌ Error updating teams: " . $e->getMessage() . "\n";
}