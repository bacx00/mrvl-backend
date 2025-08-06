<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$results = DB::select('SELECT id, name, earnings FROM teams LIMIT 10');
foreach ($results as $team) {
    echo "Team: {$team->name}, Earnings: '{$team->earnings}' (type: " . gettype($team->earnings) . ")\n";
}

// Check unique earnings values
$unique = DB::select('SELECT DISTINCT earnings FROM teams');
echo "\nUnique earnings values:\n";
foreach ($unique as $value) {
    echo "'{$value->earnings}'\n";
}