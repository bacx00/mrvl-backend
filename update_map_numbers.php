<?php
// Script to update map numbers for all existing match_player_stats

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Get all matches with multiple hero stats per player
$matches = DB::table('match_player_stats')
    ->select('match_id', 'player_id', DB::raw('COUNT(*) as hero_count'))
    ->groupBy('match_id', 'player_id')
    ->having('hero_count', '>', 1)
    ->get();

echo "Found " . $matches->count() . " player-match combinations with multiple heroes\n";

foreach ($matches as $matchData) {
    // Get all heroes for this player in this match, ordered by eliminations desc
    $stats = DB::table('match_player_stats')
        ->where('match_id', $matchData->match_id)
        ->where('player_id', $matchData->player_id)
        ->orderBy('eliminations', 'desc')
        ->get();
    
    $mapNumber = 1;
    foreach ($stats as $stat) {
        DB::table('match_player_stats')
            ->where('id', $stat->id)
            ->update(['map_number' => $mapNumber]);
        echo "Match {$matchData->match_id}, Player {$matchData->player_id}, Hero {$stat->hero} -> Map {$mapNumber}\n";
        $mapNumber++;
    }
}

// Set map_number = 1 for all single-hero entries
DB::table('match_player_stats')
    ->whereNull('map_number')
    ->update(['map_number' => 1]);

echo "Updated all map numbers successfully\n";
