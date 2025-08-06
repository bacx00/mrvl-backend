<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting player data fixes...\n\n";

// 1. Fix Hero Names
echo "=== FIXING HERO NAMES ===\n";

$heroMappings = [
    // Fix lowercase and hyphenated names
    'luna-snow' => 'Luna Snow',
    'luna snow' => 'Luna Snow',
    'spider-man' => 'Spider-Man',
    'spider man' => 'Spider-Man',
    'iron-man' => 'Iron Man',
    'iron man' => 'Iron Man',
    'captain-america' => 'Captain America',
    'captain america' => 'Captain America',
    'black-widow' => 'Black Widow',
    'black widow' => 'Black Widow',
    'black-panther' => 'Black Panther',
    'black panther' => 'Black Panther',
    'doctor-strange' => 'Doctor Strange',
    'doctor strange' => 'Doctor Strange',
    'scarlet-witch' => 'Scarlet Witch',
    'scarlet witch' => 'Scarlet Witch',
    'star-lord' => 'Star-Lord',
    'star lord' => 'Star-Lord',
    'rocket-raccoon' => 'Rocket Raccoon',
    'rocket raccoon' => 'Rocket Raccoon',
    'winter-soldier' => 'Winter Soldier',
    'winter soldier' => 'Winter Soldier',
    'peni-parker' => 'Peni Parker',
    'peni parker' => 'Peni Parker',
    'jeff-the-land-shark' => 'Jeff the Land Shark',
    'jeff' => 'Jeff the Land Shark',
    'squirrel-girl' => 'Squirrel Girl',
    'squirrel girl' => 'Squirrel Girl',
    'moon-knight' => 'Moon Knight',
    'moon knight' => 'Moon Knight',
    'adam-warlock' => 'Adam Warlock',
    'adam warlock' => 'Adam Warlock',
    'cloak-and-dagger' => 'Cloak & Dagger',
    'cloak and dagger' => 'Cloak & Dagger',
    'cloak-dagger' => 'Cloak & Dagger',
    'iron-fist' => 'Iron Fist',
    'iron fist' => 'Iron Fist',
    'mr-fantastic' => 'Mister Fantastic',
    'mister-fantastic' => 'Mister Fantastic',
    'mister fantastic' => 'Mister Fantastic',
    'the-punisher' => 'The Punisher',
    'punisher' => 'The Punisher',
    'the-thing' => 'The Thing',
    'thing' => 'The Thing',
    'human-torch' => 'Human Torch',
    'human torch' => 'Human Torch',
    'invisible-woman' => 'Invisible Woman',
    'invisible woman' => 'Invisible Woman',
    'emma-frost' => 'Emma Frost',
    'emma frost' => 'Emma Frost',
    
    // Fix case sensitivity for simple names
    'hulk' => 'Hulk',
    'thor' => 'Thor',
    'loki' => 'Loki',
    'hela' => 'Hela',
    'venom' => 'Venom',
    'groot' => 'Groot',
    'mantis' => 'Mantis',
    'storm' => 'Storm',
    'psylocke' => 'Psylocke',
    'wolverine' => 'Wolverine',
    'magneto' => 'Magneto',
    'hawkeye' => 'Hawkeye',
    'namor' => 'Namor',
    'magik' => 'Magik',
    'ultron' => 'Ultron',
    
    // Fix role placeholders
    'duelist' => null,  // Will be removed
    'vanguard' => null, // Will be removed
    'strategist' => null, // Will be removed
    'tank' => null, // Will be removed
    'support' => null, // Will be removed
    'damage' => null, // Will be removed
];

foreach ($heroMappings as $incorrect => $correct) {
    if ($correct === null) {
        // Clear invalid hero names that are actually role names
        $affected = DB::table('players')
            ->where('main_hero', $incorrect)
            ->update(['main_hero' => null]);
        if ($affected > 0) {
            echo "Cleared invalid hero name '$incorrect' from $affected players\n";
        }
    } else {
        // Update hero names
        $affected = DB::table('players')
            ->where('main_hero', $incorrect)
            ->update(['main_hero' => $correct]);
        if ($affected > 0) {
            echo "Updated '$incorrect' to '$correct' for $affected players\n";
        }
    }
}

// Also fix alt_heroes JSON field
$players = DB::table('players')->whereNotNull('alt_heroes')->get();
foreach ($players as $player) {
    $altHeroes = json_decode($player->alt_heroes, true);
    if (is_array($altHeroes)) {
        $updated = false;
        foreach ($altHeroes as &$hero) {
            $lowerHero = strtolower($hero);
            if (isset($heroMappings[$lowerHero]) && $heroMappings[$lowerHero] !== null) {
                $hero = $heroMappings[$lowerHero];
                $updated = true;
            }
        }
        if ($updated) {
            DB::table('players')
                ->where('id', $player->id)
                ->update(['alt_heroes' => json_encode($altHeroes)]);
            echo "Updated alt_heroes for player ID {$player->id}\n";
        }
    }
}

echo "\n=== FIXING PLAYER ROLES ===\n";

// 2. Fix Player Roles
$roleMappings = [
    // Old system to Marvel Rivals system
    'Tank' => 'Vanguard',
    'tank' => 'Vanguard',
    'TANK' => 'Vanguard',
    'Support' => 'Strategist',
    'support' => 'Strategist',
    'SUPPORT' => 'Strategist',
    'Damage' => 'Duelist',
    'damage' => 'Duelist',
    'DAMAGE' => 'Duelist',
    'DPS' => 'Duelist',
    'dps' => 'Duelist',
    'Dps' => 'Duelist',
    
    // Ensure proper capitalization
    'vanguard' => 'Vanguard',
    'strategist' => 'Strategist',
    'duelist' => 'Duelist',
];

foreach ($roleMappings as $incorrect => $correct) {
    $affected = DB::table('players')
        ->where('role', $incorrect)
        ->update(['role' => $correct]);
    if ($affected > 0) {
        echo "Updated role '$incorrect' to '$correct' for $affected players\n";
    }
}

// 3. Check for any remaining invalid data
echo "\n=== CHECKING FOR REMAINING ISSUES ===\n";

// Check for invalid roles
$invalidRoles = DB::table('players')
    ->whereNotIn('role', ['Vanguard', 'Strategist', 'Duelist'])
    ->whereNotNull('role')
    ->select('role', DB::raw('count(*) as count'))
    ->groupBy('role')
    ->get();

if ($invalidRoles->count() > 0) {
    echo "WARNING: Found invalid roles:\n";
    foreach ($invalidRoles as $role) {
        echo "- '{$role->role}': {$role->count} players\n";
    }
} else {
    echo "✓ All player roles are valid\n";
}

// Check for potentially invalid hero names
$validHeroes = [
    'Spider-Man', 'Luna Snow', 'Iron Man', 'Captain America', 'Black Widow',
    'Black Panther', 'Doctor Strange', 'Scarlet Witch', 'Star-Lord', 
    'Rocket Raccoon', 'Winter Soldier', 'Peni Parker', 'Jeff the Land Shark',
    'Squirrel Girl', 'Moon Knight', 'Adam Warlock', 'Cloak & Dagger',
    'Iron Fist', 'Mister Fantastic', 'The Punisher', 'The Thing',
    'Human Torch', 'Invisible Woman', 'Emma Frost', 'Hulk', 'Thor', 'Loki',
    'Hela', 'Venom', 'Groot', 'Mantis', 'Storm', 'Psylocke', 'Wolverine',
    'Magneto', 'Hawkeye', 'Namor', 'Magik', 'Ultron'
];

$invalidHeroes = DB::table('players')
    ->whereNotNull('main_hero')
    ->whereNotIn('main_hero', $validHeroes)
    ->select('main_hero', DB::raw('count(*) as count'))
    ->groupBy('main_hero')
    ->get();

if ($invalidHeroes->count() > 0) {
    echo "\nWARNING: Found potentially invalid hero names:\n";
    foreach ($invalidHeroes as $hero) {
        echo "- '{$hero->main_hero}': {$hero->count} players\n";
    }
} else {
    echo "✓ All hero names are valid\n";
}

// 4. Update player stats to ensure consistency
echo "\n=== UPDATING PLAYER STATS ===\n";

// Ensure all players have valid default values
$updatedStats = DB::table('players')
    ->whereNull('rating')
    ->update(['rating' => 1000]);
echo "Set default rating (1000) for $updatedStats players\n";

$updatedEarnings = DB::table('players')
    ->whereNull('earnings')
    ->update(['earnings' => 0]);
echo "Set default earnings (0) for $updatedEarnings players\n";

// Update teams to ensure proper formatting
echo "\n=== UPDATING TEAM DATA ===\n";

// Ensure team regions are properly set
$regionMappings = [
    'NA' => 'Americas',
    'North America' => 'Americas',
    'SA' => 'Americas',
    'South America' => 'Americas',
    'BR' => 'Americas',
    'Brazil' => 'Americas',
    'EU' => 'EMEA',
    'Europe' => 'EMEA',
    'MENA' => 'EMEA',
    'Middle East' => 'EMEA',
    'Africa' => 'EMEA',
    'CN' => 'China',
    'AS' => 'Asia',
    'KR' => 'Asia',
    'Korea' => 'Asia',
    'JP' => 'Asia',
    'Japan' => 'Asia',
    'SEA' => 'Asia',
    'Southeast Asia' => 'Asia',
    'OCE' => 'Oceania',
    'AU' => 'Oceania',
    'Australia' => 'Oceania',
];

foreach ($regionMappings as $old => $new) {
    $affected = DB::table('teams')
        ->where('region', $old)
        ->update(['region' => $new]);
    if ($affected > 0) {
        echo "Updated region '$old' to '$new' for $affected teams\n";
    }
}

echo "\n=== DATA FIXES COMPLETED ===\n";
echo "All player and team data has been cleaned up!\n";