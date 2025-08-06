<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;

$team = Team::where('name', '100 Thieves')->first();
if ($team) {
    echo "Before increment - 100 Thieves earnings: " . $team->earnings . "\n";
    $team->increment('earnings', 40000);
    $team->refresh();
    echo "After increment - 100 Thieves earnings: " . $team->earnings . "\n";
    
    // Also check LOUD
    $loud = Team::where('name', 'LOUD')->first();
    if ($loud) {
        echo "\nLOUD earnings: " . $loud->earnings . "\n";
    }
}