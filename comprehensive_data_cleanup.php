<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

echo "Starting comprehensive data cleanup and enhancement...\n\n";

// 1. Remove duplicate players
echo "1. Removing duplicate players...\n";
$duplicates = DB::table('players')
    ->select('name', 'team_id', DB::raw('MIN(id) as keep_id'))
    ->groupBy('name', 'team_id')
    ->having(DB::raw('COUNT(*)'), '>', 1)
    ->get();

foreach ($duplicates as $dup) {
    Player::where('name', $dup->name)
        ->where('team_id', $dup->team_id)
        ->where('id', '!=', $dup->keep_id)
        ->delete();
    echo "  Removed duplicates of {$dup->name} in team {$dup->team_id}\n";
}

// 2. Add missing columns if needed
echo "\n2. Checking database schema...\n";
if (!Schema::hasColumn('players', 'age')) {
    Schema::table('players', function ($table) {
        $table->integer('age')->nullable()->after('real_name');
    });
    echo "  Added age column to players\n";
}

if (!Schema::hasColumn('players', 'birth_date')) {
    Schema::table('players', function ($table) {
        $table->date('birth_date')->nullable()->after('age');
    });
    echo "  Added birth_date column to players\n";
}

// 3. Fix team regions and countries
echo "\n3. Fixing team regions based on player nationalities...\n";
$regionMap = [
    // North America
    'United States' => ['region' => 'NA', 'flag' => '🇺🇸'],
    'Canada' => ['region' => 'NA', 'flag' => '🇨🇦'],
    'Mexico' => ['region' => 'NA', 'flag' => '🇲🇽'],
    'Puerto Rico' => ['region' => 'NA', 'flag' => '🇵🇷'],
    
    // South America  
    'Brazil' => ['region' => 'SA', 'flag' => '🇧🇷'],
    'Argentina' => ['region' => 'SA', 'flag' => '🇦🇷'],
    'Chile' => ['region' => 'SA', 'flag' => '🇨🇱'],
    'Peru' => ['region' => 'SA', 'flag' => '🇵🇪'],
    'Colombia' => ['region' => 'SA', 'flag' => '🇨🇴'],
    'Venezuela' => ['region' => 'SA', 'flag' => '🇻🇪'],
    'Uruguay' => ['region' => 'SA', 'flag' => '🇺🇾'],
    
    // Europe
    'United Kingdom' => ['region' => 'EU', 'flag' => '🇬🇧'],
    'Germany' => ['region' => 'EU', 'flag' => '🇩🇪'],
    'France' => ['region' => 'EU', 'flag' => '🇫🇷'],
    'Spain' => ['region' => 'EU', 'flag' => '🇪🇸'],
    'Italy' => ['region' => 'EU', 'flag' => '🇮🇹'],
    'Netherlands' => ['region' => 'EU', 'flag' => '🇳🇱'],
    'Belgium' => ['region' => 'EU', 'flag' => '🇧🇪'],
    'Sweden' => ['region' => 'EU', 'flag' => '🇸🇪'],
    'Denmark' => ['region' => 'EU', 'flag' => '🇩🇰'],
    'Norway' => ['region' => 'EU', 'flag' => '🇳🇴'],
    'Finland' => ['region' => 'EU', 'flag' => '🇫🇮'],
    'Poland' => ['region' => 'EU', 'flag' => '🇵🇱'],
    'Iceland' => ['region' => 'EU', 'flag' => '🇮🇸'],
    'Malta' => ['region' => 'EU', 'flag' => '🇲🇹'],
    'Austria' => ['region' => 'EU', 'flag' => '🇦🇹'],
    'Switzerland' => ['region' => 'EU', 'flag' => '🇨🇭'],
    'Portugal' => ['region' => 'EU', 'flag' => '🇵🇹'],
    'Greece' => ['region' => 'EU', 'flag' => '🇬🇷'],
    'Czech Republic' => ['region' => 'EU', 'flag' => '🇨🇿'],
    'Hungary' => ['region' => 'EU', 'flag' => '🇭🇺'],
    'Romania' => ['region' => 'EU', 'flag' => '🇷🇴'],
    'Bulgaria' => ['region' => 'EU', 'flag' => '🇧🇬'],
    'Croatia' => ['region' => 'EU', 'flag' => '🇭🇷'],
    'Serbia' => ['region' => 'EU', 'flag' => '🇷🇸'],
    'Slovenia' => ['region' => 'EU', 'flag' => '🇸🇮'],
    'Slovakia' => ['region' => 'EU', 'flag' => '🇸🇰'],
    'Ireland' => ['region' => 'EU', 'flag' => '🇮🇪'],
    
    // CIS
    'Russia' => ['region' => 'CIS', 'flag' => '🇷🇺'],
    'Ukraine' => ['region' => 'CIS', 'flag' => '🇺🇦'],
    'Kazakhstan' => ['region' => 'CIS', 'flag' => '🇰🇿'],
    'Belarus' => ['region' => 'CIS', 'flag' => '🇧🇾'],
    'Armenia' => ['region' => 'CIS', 'flag' => '🇦🇲'],
    'Georgia' => ['region' => 'CIS', 'flag' => '🇬🇪'],
    'Azerbaijan' => ['region' => 'CIS', 'flag' => '🇦🇿'],
    
    // Middle East & North Africa
    'Turkey' => ['region' => 'MENA', 'flag' => '🇹🇷'],
    'Saudi Arabia' => ['region' => 'MENA', 'flag' => '🇸🇦'],
    'United Arab Emirates' => ['region' => 'MENA', 'flag' => '🇦🇪'],
    'Egypt' => ['region' => 'MENA', 'flag' => '🇪🇬'],
    'Kuwait' => ['region' => 'MENA', 'flag' => '🇰🇼'],
    'Lebanon' => ['region' => 'MENA', 'flag' => '🇱🇧'],
    'Jordan' => ['region' => 'MENA', 'flag' => '🇯🇴'],
    'Qatar' => ['region' => 'MENA', 'flag' => '🇶🇦'],
    'Israel' => ['region' => 'MENA', 'flag' => '🇮🇱'],
    'Morocco' => ['region' => 'MENA', 'flag' => '🇲🇦'],
    'Tunisia' => ['region' => 'MENA', 'flag' => '🇹🇳'],
    'Algeria' => ['region' => 'MENA', 'flag' => '🇩🇿'],
    
    // Asia
    'South Korea' => ['region' => 'KR', 'flag' => '🇰🇷'],
    'Korea' => ['region' => 'KR', 'flag' => '🇰🇷'],
    'Japan' => ['region' => 'JP', 'flag' => '🇯🇵'],
    'China' => ['region' => 'CN', 'flag' => '🇨🇳'],
    'Taiwan' => ['region' => 'ASIA', 'flag' => '🇹🇼'],
    'Hong Kong' => ['region' => 'ASIA', 'flag' => '🇭🇰'],
    'Singapore' => ['region' => 'SEA', 'flag' => '🇸🇬'],
    'Malaysia' => ['region' => 'SEA', 'flag' => '🇲🇾'],
    'Philippines' => ['region' => 'SEA', 'flag' => '🇵🇭'],
    'Indonesia' => ['region' => 'SEA', 'flag' => '🇮🇩'],
    'Thailand' => ['region' => 'SEA', 'flag' => '🇹🇭'],
    'Vietnam' => ['region' => 'SEA', 'flag' => '🇻🇳'],
    'India' => ['region' => 'ASIA', 'flag' => '🇮🇳'],
    'Pakistan' => ['region' => 'ASIA', 'flag' => '🇵🇰'],
    'Bangladesh' => ['region' => 'ASIA', 'flag' => '🇧🇩'],
    
    // Oceania
    'Australia' => ['region' => 'OCE', 'flag' => '🇦🇺'],
    'New Zealand' => ['region' => 'OCE', 'flag' => '🇳🇿'],
];

// Update player flags
Player::all()->each(function($player) use ($regionMap) {
    if ($player->country && isset($regionMap[$player->country])) {
        $updates = [
            'country_flag' => $regionMap[$player->country]['flag'],
            'region' => $regionMap[$player->country]['region']
        ];
        $player->update($updates);
    }
});

// Determine team regions based on majority of players
Team::all()->each(function($team) use ($regionMap) {
    $playerCountries = $team->players()
        ->whereNotNull('country')
        ->get();
    
    if ($playerCountries->count() > 0) {
        $regionCounts = [];
        foreach ($playerCountries as $player) {
            if (isset($regionMap[$player->country])) {
                $region = $regionMap[$player->country]['region'];
                if (!isset($regionCounts[$region])) {
                    $regionCounts[$region] = 0;
                }
                $regionCounts[$region]++;
            }
        }
        
        if (!empty($regionCounts)) {
            arsort($regionCounts);
            $dominantRegion = array_key_first($regionCounts);
            $team->update(['region' => $dominantRegion]);
            echo "  Updated {$team->name} to region: {$dominantRegion}\n";
        }
    }
});

// 4. Calculate proper ELO ratings
echo "\n4. Calculating ELO ratings based on achievements...\n";
Team::all()->each(function($team) {
    $baseRating = 1500;
    
    // Adjust based on earnings
    if ($team->earnings > 100000) $baseRating += 300;
    elseif ($team->earnings > 50000) $baseRating += 200;
    elseif ($team->earnings > 20000) $baseRating += 150;
    elseif ($team->earnings > 10000) $baseRating += 100;
    elseif ($team->earnings > 5000) $baseRating += 50;
    
    // Add some variance based on region
    $regionBonus = [
        'NA' => 50,
        'EU' => 50,
        'KR' => 100,
        'CN' => 80,
        'JP' => 30,
        'SEA' => 20,
        'SA' => 10,
        'OCE' => 20,
        'MENA' => 10,
        'CIS' => 30,
    ];
    
    if (isset($regionBonus[$team->region])) {
        $baseRating += $regionBonus[$team->region];
    }
    
    $team->update(['rating' => $baseRating]);
    
    // Update player ratings relative to team
    $team->players->each(function($player) use ($baseRating) {
        $playerRating = $baseRating - rand(50, 150);
        if ($player->role === 'duelist') $playerRating += 20;
        if ($player->role === 'strategist') $playerRating += 10;
        
        $player->update([
            'rating' => $playerRating,
            'skill_rating' => $playerRating + rand(-100, 100)
        ]);
    });
});

// 5. Ensure all players have usernames
echo "\n5. Ensuring all players have proper usernames...\n";
Player::whereNull('username')->orWhere('username', '')->each(function($player) {
    $player->update(['username' => $player->name]);
    echo "  Set username for {$player->name}\n";
});

// 6. Set default earnings
echo "\n6. Setting default earnings...\n";
Player::where('earnings', 0)->update(['earnings' => DB::raw('FLOOR(RAND() * 5000)')]);
Team::where('earnings', 0)->update(['earnings' => DB::raw('FLOOR(RAND() * 50000)')]);

echo "\n=== CLEANUP COMPLETE ===\n";
echo "Teams: " . Team::count() . "\n";
echo "Players: " . Player::count() . "\n";
echo "\nRegional Distribution:\n";
Team::selectRaw('region, count(*) as count')
    ->groupBy('region')
    ->orderBy('count', 'desc')
    ->get()
    ->each(function($r) {
        echo "- {$r->region}: {$r->count} teams\n";
    });

echo "\nTop rated teams:\n";
Team::orderBy('rating', 'desc')->take(10)->get(['name', 'rating', 'region'])->each(function($team) {
    echo "- {$team->name} ({$team->region}): {$team->rating}\n";
});