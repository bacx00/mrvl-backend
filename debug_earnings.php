<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventStanding;
use App\Models\Team;

echo "Debug Earnings Data\n";
echo "===================\n\n";

// Check EventStandings
echo "Event Standings:\n";
$standings = EventStanding::with(['team', 'event'])->take(10)->get();
foreach ($standings as $standing) {
    echo "Event: " . $standing->event->name . "\n";
    echo "Team: " . $standing->team->name . "\n";
    echo "Position: " . $standing->position . "\n";
    echo "Prize Money: $" . number_format($standing->prize_money) . "\n\n";
}

// Check Team earnings
echo "\nTeam Earnings:\n";
$teams = Team::orderBy('earnings', 'desc')->take(10)->get();
foreach ($teams as $team) {
    echo $team->name . ": $" . number_format($team->earnings) . "\n";
}