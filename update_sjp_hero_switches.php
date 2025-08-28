<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Get SJP's player ID
$sjp = DB::table('players')->where('username', 'SJP')->first();
echo "SJP Player ID: {$sjp->id}\n";

// Get current match data
$match = DB::table('matches')->where('id', 7)->first();
$mapsData = json_decode($match->maps_data, true);

echo "\n🎮 Simulating SJP hero switches on Map 1...\n";

// Map 1 - SJP switches heroes multiple times (realistic scenario)
// Start with Spider-Man, then switch to other heroes

// Clear existing player_match_stats for SJP in match 7
DB::table('player_match_stats')
    ->where('match_id', 7)
    ->where('player_id', $sjp->id)
    ->delete();

// Hero 1: Spider-Man (early game - 5 minutes)
DB::table('player_match_stats')->insert([
    'match_id' => 7,
    'player_id' => $sjp->id,
    'map_id' => 1,
    'hero_played' => 'Spider-Man',
    'eliminations' => 3,
    'deaths' => 2,
    'assists' => 8,
    'damage' => 1200,
    'healing' => 0,
    'damage_blocked' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ Map 1 - Hero 1: Spider-Man (3/2/8) - Early game tank\n";

// Hero 2: Iron Man (switched for damage - next 4 minutes)
DB::table('player_match_stats')->insert([
    'match_id' => 7,
    'player_id' => $sjp->id,
    'map_id' => 1,
    'hero_played' => 'Iron Man',
    'eliminations' => 5,
    'deaths' => 1,
    'assists' => 3,
    'damage' => 2800,
    'healing' => 0,
    'damage_blocked' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ Map 1 - Hero 2: Iron Man (5/1/3) - Switched for DPS\n";

// Hero 3: Mantis (switched to support - next 3 minutes)
DB::table('player_match_stats')->insert([
    'match_id' => 7,
    'player_id' => $sjp->id,
    'map_id' => 1,
    'hero_played' => 'Mantis',
    'eliminations' => 1,
    'deaths' => 0,
    'assists' => 12,
    'damage' => 400,
    'healing' => 3500,
    'damage_blocked' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ Map 1 - Hero 3: Mantis (1/0/12) - Team needed healing\n";

// Hero 4: Venom (final push - last 3 minutes)
DB::table('player_match_stats')->insert([
    'match_id' => 7,
    'player_id' => $sjp->id,
    'map_id' => 1,
    'hero_played' => 'Venom',
    'eliminations' => 2,
    'deaths' => 1,
    'assists' => 15,
    'damage' => 1100,
    'healing' => 0,
    'damage_blocked' => 800,
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ Map 1 - Hero 4: Venom (2/1/15) - Final push tank\n";

// Map 2 - Keep original Spider-Man stats
DB::table('player_match_stats')->insert([
    'match_id' => 7,
    'player_id' => $sjp->id,
    'map_id' => 2,
    'hero_played' => 'Spider-Man',
    'eliminations' => 8,
    'deaths' => 3,
    'assists' => 10,
    'damage' => 2200,
    'healing' => 0,
    'damage_blocked' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ Map 2: Spider-Man (8/3/10) - Consistent performance\n";

// Map 3 - Keep original Spider-Man stats
DB::table('player_match_stats')->insert([
    'match_id' => 7,
    'player_id' => $sjp->id,
    'map_id' => 3,
    'hero_played' => 'Spider-Man',
    'eliminations' => 5,
    'deaths' => 2,
    'assists' => 12,
    'damage' => 1800,
    'healing' => 0,
    'damage_blocked' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ Map 3: Spider-Man (5/2/12) - Closing out the match\n";

// Calculate totals
$totalStats = DB::table('player_match_stats')
    ->where('match_id', 7)
    ->where('player_id', $sjp->id)
    ->select(
        DB::raw('SUM(eliminations) as total_eliminations'),
        DB::raw('SUM(deaths) as total_deaths'),
        DB::raw('SUM(assists) as total_assists'),
        DB::raw('GROUP_CONCAT(DISTINCT hero_played) as heroes_played')
    )
    ->first();

echo "\n📊 SJP Total Stats for Match 7:\n";
echo "Heroes played: {$totalStats->heroes_played}\n";
echo "Total K/D/A: {$totalStats->total_eliminations}/{$totalStats->total_deaths}/{$totalStats->total_assists}\n";

echo "\n✨ Live scoring data updated! The player history should now show multiple heroes for SJP.\n";
