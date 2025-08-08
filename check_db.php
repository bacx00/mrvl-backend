<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$match = DB::table('matches')->where('id', 1)->first();

echo "Match ID 1 from database:\n";
echo "team1_score: " . ($match->team1_score ?? 'NULL') . "\n";
echo "team2_score: " . ($match->team2_score ?? 'NULL') . "\n";
echo "status: " . $match->status . "\n";
echo "maps_data sample: " . substr($match->maps_data, 0, 200) . "...\n";