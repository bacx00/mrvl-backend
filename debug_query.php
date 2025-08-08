<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing match query...\n";

try {
    $match = DB::table('matches as m')
        ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
        ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
        ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
        ->where('m.id', 1)
        ->select([
            'm.*',
            't1.name as team1_name', 't1.short_name as team1_short',
            't1.logo as team1_logo', 't1.region as team1_region', 't1.rating as team1_rating',
            't2.name as team2_name', 't2.short_name as team2_short', 
            't2.logo as team2_logo', 't2.region as team2_region', 't2.rating as team2_rating',
            'e.name as event_name', 'e.type as event_type',
            'e.logo as event_logo', 'e.format as event_format'
        ])
        ->first();

    if ($match) {
        echo "Query successful!\n";
        echo "team1_score: " . ($match->team1_score ?? 'NULL') . "\n";
        echo "team2_score: " . ($match->team2_score ?? 'NULL') . "\n";
        echo "status: " . ($match->status ?? 'NULL') . "\n";
        echo "team1_id: " . ($match->team1_id ?? 'NULL') . "\n";
        echo "team2_id: " . ($match->team2_id ?? 'NULL') . "\n";
    } else {
        echo "No match found!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}