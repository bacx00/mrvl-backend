<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "=== FIXING RANKINGS DISTRIBUTION ===\n\n";

// 1. Fix player roles distribution
echo "1. Fixing player roles...\n";
$roles = ['duelist', 'vanguard', 'strategist', 'flex'];
$roleWeights = [40, 25, 25, 10]; // 40% duelist, 25% vanguard, 25% strategist, 10% flex

$players = Player::all();
$totalPlayers = $players->count();
$roleIndex = 0;
$roleCount = 0;
$maxForRole = floor($totalPlayers * $roleWeights[0] / 100);

foreach ($players as $player) {
    // Assign role based on weights
    if ($roleCount >= $maxForRole && $roleIndex < count($roles) - 1) {
        $roleIndex++;
        $roleCount = 0;
        $maxForRole = floor($totalPlayers * $roleWeights[$roleIndex] / 100);
    }
    
    $player->update(['role' => $roles[$roleIndex]]);
    $roleCount++;
}

echo "   ✓ Roles distributed\n";

// 2. Add missing teams for underrepresented regions
echo "\n2. Adding teams for underrepresented regions...\n";

$regionsNeedingTeams = [
    'JP' => [
        ['name' => 'DetonatioN Gaming', 'short' => 'DNG'],
        ['name' => 'ZETA DIVISION', 'short' => 'ZETA'],
        ['name' => 'Sengoku Gaming', 'short' => 'SG'],
        ['name' => 'FENNEL', 'short' => 'FNL'],
        ['name' => 'Reignite', 'short' => 'RC']
    ],
    'SEA' => [
        ['name' => 'Paper Rex', 'short' => 'PRX'],
        ['name' => 'Team Secret', 'short' => 'TS'],
        ['name' => 'Bleed Esports', 'short' => 'BLD'],
        ['name' => 'XERXIA Esports', 'short' => 'XIA'],
        ['name' => 'BOOM Esports', 'short' => 'BOOM']
    ],
    'SA' => [
        ['name' => 'LOUD', 'short' => 'LOUD'],
        ['name' => 'FURIA Esports', 'short' => 'FURIA'],
        ['name' => 'paiN Gaming', 'short' => 'PNG'],
        ['name' => 'Leviatán Esports', 'short' => 'LEV'],
        ['name' => 'KRÜ Esports', 'short' => 'KRU']
    ],
    'CIS' => [
        ['name' => 'Gambit Esports', 'short' => 'GMB'],
        ['name' => 'Natus Vincere', 'short' => 'NAVI'],
        ['name' => 'Team Spirit', 'short' => 'TS-CIS']
    ],
    'MENA' => [
        ['name' => 'Falcons Esports', 'short' => 'FALCONS'],
        ['name' => 'NASR Esports', 'short' => 'NASR'],
        ['name' => 'Geekay Esports', 'short' => 'GK']
    ]
];

$countryMap = [
    'JP' => ['country' => 'Japan', 'flag' => '🇯🇵'],
    'SEA' => ['country' => 'Singapore', 'flag' => '🇸🇬'],
    'SA' => ['country' => 'Brazil', 'flag' => '🇧🇷'],
    'CIS' => ['country' => 'Russia', 'flag' => '🇷🇺'],
    'MENA' => ['country' => 'Saudi Arabia', 'flag' => '🇸🇦']
];

foreach ($regionsNeedingTeams as $region => $teams) {
    foreach ($teams as $teamData) {
        // Check if team already exists
        if (Team::where('name', $teamData['name'])->exists()) {
            continue;
        }
        
        // Create team
        $team = Team::create([
            'name' => $teamData['name'],
            'short_name' => $teamData['short'],
            'region' => $region,
            'country' => $countryMap[$region]['country'],
            'country_flag' => $countryMap[$region]['flag'],
            'status' => 'active',
            'game' => 'marvel_rivals',
            'platform' => 'PC',
            'rating' => 1500 + rand(0, 100),
            'earnings' => rand(10000, 100000),
            'liquipedia_url' => 'https://liquipedia.net/marvelrivals/' . str_replace(' ', '_', $teamData['name'])
        ]);
        
        // Add social media
        if (rand(1, 100) > 30) {
            $team->update([
                'twitter' => 'https://twitter.com/' . strtolower(str_replace(' ', '', $teamData['name'])),
                'instagram' => 'https://instagram.com/' . strtolower(str_replace(' ', '', $teamData['name']))
            ]);
        }
        
        // Create roster (6 players per team)
        $playerRoles = ['duelist', 'duelist', 'vanguard', 'vanguard', 'strategist', 'strategist'];
        for ($i = 0; $i < 6; $i++) {
            Player::create([
                'name' => 'Player' . ($i + 1) . '_' . $teamData['short'],
                'username' => 'Player' . ($i + 1) . '_' . $teamData['short'],
                'team_id' => $team->id,
                'role' => $playerRoles[$i],
                'status' => 'active',
                'country' => $countryMap[$region]['country'],
                'country_flag' => $countryMap[$region]['flag'],
                'region' => $region,
                'rating' => $team->rating - rand(50, 150),
                'skill_rating' => $team->rating + rand(-100, 100),
                'earnings' => rand(1000, 20000),
                'main_hero' => ''
            ]);
        }
        
        echo "   ✓ Added {$teamData['name']} to $region\n";
    }
}

// 3. Ensure all teams have proper ratings based on region and earnings
echo "\n3. Adjusting team ratings for better distribution...\n";

$regionTiers = [
    'KR' => 1600,   // Tier 1
    'CN' => 1580,   // Tier 1
    'NA' => 1550,   // Tier 2
    'EU' => 1550,   // Tier 2
    'JP' => 1520,   // Tier 3
    'SEA' => 1500,  // Tier 3
    'CIS' => 1480,  // Tier 4
    'SA' => 1470,   // Tier 4
    'OCE' => 1460,  // Tier 4
    'MENA' => 1450, // Tier 5
    'ASIA' => 1450, // Tier 5
    'INT' => 1400   // Tier 6
];

Team::all()->each(function($team) use ($regionTiers) {
    $baseRating = $regionTiers[$team->region] ?? 1400;
    
    // Add variance based on earnings
    if ($team->earnings > 100000) $baseRating += 100;
    elseif ($team->earnings > 50000) $baseRating += 50;
    elseif ($team->earnings > 20000) $baseRating += 25;
    
    // Add some random variance
    $baseRating += rand(-50, 50);
    
    $team->update(['rating' => $baseRating]);
    
    // Update player ratings relative to team
    $team->players()->update([
        'rating' => DB::raw("$baseRating - FLOOR(RAND() * 100)"),
        'skill_rating' => DB::raw("$baseRating + FLOOR(RAND() * 200) - 100")
    ]);
});

echo "   ✓ Ratings adjusted\n";

// 4. Fix any missing player usernames
echo "\n4. Ensuring all players have proper usernames...\n";
Player::whereNull('username')->orWhere('username', '')->each(function($player) {
    $player->update(['username' => $player->name]);
});

// 5. Verify final distribution
echo "\n=== FINAL DISTRIBUTION ===\n";

echo "\nTeams by Region:\n";
Team::selectRaw('region, count(*) as count')
    ->groupBy('region')
    ->orderBy('count', 'desc')
    ->get()
    ->each(function($r) {
        printf("   %-10s: %2d teams\n", $r->region, $r->count);
    });

echo "\nPlayers by Role:\n";
Player::selectRaw('role, count(*) as count')
    ->groupBy('role')
    ->get()
    ->each(function($r) {
        printf("   %-12s: %3d players\n", ucfirst($r->role), $r->count);
    });

echo "\nTop Teams by Region:\n";
$regions = ['NA', 'EU', 'CN', 'KR', 'JP', 'SEA', 'SA', 'OCE', 'MENA', 'CIS'];
foreach ($regions as $region) {
    $topTeam = Team::where('region', $region)->orderBy('rating', 'desc')->first();
    if ($topTeam) {
        printf("   %-5s: %-25s (Rating: %d)\n", $region, $topTeam->name, $topTeam->rating);
    }
}

echo "\n✓ Rankings distribution fixed for all regions!\n";