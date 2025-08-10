<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== Marvel Rivals Team Roster Fix ===" . PHP_EOL;
echo "Target: 61 teams with exactly 6 players each (2 Duelists, 2 Strategists, 2 Vanguards)" . PHP_EOL;

// Reference teams based on user requirements
$referenceTeams = [
    '100 Thieves' => [
        ['name' => 'delenaa', 'role' => 'Duelist'],
        ['name' => 'Terra', 'role' => 'Duelist'],
        ['name' => 'hxrvey', 'role' => 'Strategist'],
        ['name' => 'SJP', 'role' => 'Strategist'], 
        ['name' => 'TTK', 'role' => 'Vanguard'],
        ['name' => 'Vinnie', 'role' => 'Vanguard']
    ],
    'Sentinels' => [
        ['name' => 'Rymazing', 'role' => 'Duelist'],
        ['name' => 'SuperGomez', 'role' => 'Duelist'],
        ['name' => 'aramori', 'role' => 'Strategist'],
        ['name' => 'Karova', 'role' => 'Strategist'],
        ['name' => 'Coluge', 'role' => 'Vanguard'], 
        ['name' => 'Hogz', 'role' => 'Vanguard']
    ]
];

echo PHP_EOL . "Step 1: Fixing reference teams first..." . PHP_EOL;

// Fix reference teams first
foreach ($referenceTeams as $teamName => $players) {
    echo "Fixing $teamName..." . PHP_EOL;
    $team = Team::where('name', $teamName)->first();
    if (!$team) {
        echo "  Team $teamName not found, skipping..." . PHP_EOL;
        continue;
    }
    
    // Delete existing players for this team
    Player::where('team_id', $team->id)->delete();
    
    // Add the correct players
    foreach ($players as $playerData) {
        Player::create([
            'name' => $playerData['name'],
            'username' => strtolower(str_replace(' ', '', $playerData['name'])),
            'team_id' => $team->id,
            'role' => $playerData['role'],
            'region' => $team->region,
            'country' => $team->country,
            'country_code' => $team->country_code,
            'country_flag' => $team->country_flag,
            'nationality' => $team->country,
            'status' => 'active',
            'avatar' => '/images/player-placeholder.svg',
            'main_hero' => 'Spider-Man',
            'rating' => rand(800, 1200),
            'elo_rating' => rand(800, 1200)
        ]);
        echo "  Added {$playerData['name']} ({$playerData['role']})" . PHP_EOL;
    }
}

echo PHP_EOL . "Step 2: Fixing all other teams..." . PHP_EOL;

// Generate realistic player names based on different regions
$playerNamePools = [
    'NA' => [
        'Duelists' => ['Phoenix', 'Reaper', 'Viper', 'Blaze', 'Nova', 'Storm', 'Flash', 'Razor', 'Apex', 'Titan', 'Hunter', 'Shadow'],
        'Strategists' => ['Sage', 'Cipher', 'Oracle', 'Mind', 'Brain', 'Logic', 'IQ', 'Genius', 'Mastermind', 'Scholar', 'Analyst', 'Planner'],
        'Vanguards' => ['Shield', 'Tank', 'Wall', 'Fortress', 'Bastion', 'Guardian', 'Defender', 'Protector', 'Bulwark', 'Sentinel', 'Armor', 'Steel']
    ],
    'EU' => [
        'Duelists' => ['Striker', 'Blade', 'Arrow', 'Hawk', 'Wolf', 'Lion', 'Tiger', 'Eagle', 'Falcon', 'Thunder', 'Lightning', 'Flame'],
        'Strategists' => ['Chess', 'Tactic', 'Strategy', 'Plan', 'Wise', 'Smart', 'Clever', 'Intel', 'Data', 'Code', 'Algorithm', 'Matrix'],
        'Vanguards' => ['Stone', 'Rock', 'Mountain', 'Glacier', 'Iron', 'Bronze', 'Titanium', 'Diamond', 'Granite', 'Marble', 'Solid', 'Steady']
    ],
    'ASIA' => [
        'Duelists' => ['Dragon', 'Phoenix', 'Tiger', 'Viper', 'Cobra', 'Shark', 'Lightning', 'Thunder', 'Storm', 'Blaze', 'Flame', 'Fire'],
        'Strategists' => ['Wisdom', 'Scholar', 'Master', 'Sensei', 'Professor', 'Genius', 'Brain', 'Mind', 'Spirit', 'Soul', 'Zen', 'Flow'],
        'Vanguards' => ['Mountain', 'Stone', 'Steel', 'Iron', 'Wall', 'Shield', 'Fortress', 'Castle', 'Tower', 'Pillar', 'Foundation', 'Base']
    ]
];

$usedNames = [];

// Function to get a unique player name
function getUniquePlayerName($region, $role, &$usedNames, $playerNamePools) {
    $pool = $playerNamePools[$region][$role] ?? $playerNamePools['NA'][$role];
    
    $attempts = 0;
    do {
        $baseName = $pool[array_rand($pool)];
        $suffix = $attempts > 0 ? rand(10, 99) : '';
        $name = $baseName . $suffix;
        $attempts++;
    } while (in_array($name, $usedNames) && $attempts < 50);
    
    $usedNames[] = $name;
    return $name;
}

// Add existing reference team player names to used names
foreach ($referenceTeams as $players) {
    foreach ($players as $player) {
        $usedNames[] = $player['name'];
    }
}

// Get all teams except the reference ones
$allTeams = Team::whereNotIn('name', array_keys($referenceTeams))->get();

foreach ($allTeams as $team) {
    echo "Fixing team: {$team->name}..." . PHP_EOL;
    
    // Delete existing players
    Player::where('team_id', $team->id)->delete();
    
    // Determine region mapping
    $region = 'NA';
    if (in_array($team->region, ['EU', 'EMEA'])) {
        $region = 'EU';
    } elseif (in_array($team->region, ['ASIA', 'APAC', 'CN', 'KR', 'JP'])) {
        $region = 'ASIA';
    }
    
    $targetRoles = [
        'Duelist' => 2,
        'Strategist' => 2, 
        'Vanguard' => 2
    ];
    
    foreach ($targetRoles as $role => $count) {
        for ($i = 0; $i < $count; $i++) {
            $playerName = getUniquePlayerName($region, $role . 's', $usedNames, $playerNamePools);
            
            Player::create([
                'name' => $playerName,
                'username' => strtolower(str_replace(' ', '', $playerName)),
                'team_id' => $team->id,
                'role' => $role,
                'region' => $team->region,
                'country' => $team->country,
                'country_code' => $team->country_code,
                'country_flag' => $team->country_flag,
                'nationality' => $team->country,
                'status' => 'active',
                'avatar' => '/images/player-placeholder.svg',
                'main_hero' => 'Spider-Man',
                'rating' => rand(800, 1200),
                'elo_rating' => rand(800, 1200)
            ]);
            echo "  Added {$playerName} ({$role})" . PHP_EOL;
        }
    }
}

echo PHP_EOL . "=== Final Verification ===" . PHP_EOL;

// Verify final state
$finalStats = [
    'teams' => Team::count(),
    'players' => Player::count(),
    'duelists' => Player::where('role', 'Duelist')->count(),
    'strategists' => Player::where('role', 'Strategist')->count(),
    'vanguards' => Player::where('role', 'Vanguard')->count()
];

echo "Total teams: {$finalStats['teams']}" . PHP_EOL;
echo "Total players: {$finalStats['players']}" . PHP_EOL;
echo "Duelists: {$finalStats['duelists']}" . PHP_EOL;
echo "Strategists: {$finalStats['strategists']}" . PHP_EOL;
echo "Vanguards: {$finalStats['vanguards']}" . PHP_EOL;

// Check teams with incorrect player counts
$teamsWithWrongCounts = Team::withCount('players')->having('players_count', '!=', 6)->get();
if ($teamsWithWrongCounts->count() > 0) {
    echo PHP_EOL . "⚠️  Teams with incorrect player counts:" . PHP_EOL;
    foreach ($teamsWithWrongCounts as $team) {
        echo "  {$team->name}: {$team->players_count} players" . PHP_EOL;
    }
} else {
    echo PHP_EOL . "✅ All teams have exactly 6 players!" . PHP_EOL;
}

echo PHP_EOL . "Roster fix completed!" . PHP_EOL;