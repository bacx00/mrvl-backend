<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\Team;
use App\Models\Player;

echo "\n=== MARVEL RIVALS TOURNAMENT DATA VERIFICATION ===\n\n";

// Check Events
echo "EVENTS CREATED:\n";
$events = Event::all();
if ($events->count() === 0) {
    echo "- No events found\n";
} else {
    foreach ($events as $event) {
        echo "- {$event->name} ({$event->region}) - Prize Pool: \${$event->prize_pool}\n";
    }
}

echo "\nTEAMS CREATED:\n";
$teams = Team::orderBy('region')->orderBy('name')->get();
foreach ($teams as $team) {
    $playerCount = $team->players()->count();
    echo "- {$team->name} ({$team->short_name}) - Region: {$team->region} - Players: {$playerCount}\n";
}

echo "\nTOTAL STATS:\n";
echo "- Events: " . Event::count() . "\n";
echo "- Teams: " . Team::count() . "\n";
echo "- Players: " . Player::count() . "\n";

echo "\nTEAMS BY REGION:\n";
$teamsByRegion = Team::selectRaw('region, COUNT(*) as count')
    ->groupBy('region')
    ->get();
foreach ($teamsByRegion as $region) {
    echo "- {$region->region}: {$region->count} teams\n";
}

echo "\nMISSING DATA:\n";
$teamsWithoutPlayers = Team::has('players', '=', 0)->count();
echo "- Teams without players: {$teamsWithoutPlayers}\n";

$playersWithoutTeams = Player::whereNull('team_id')->count();
echo "- Players without teams: {$playersWithoutTeams}\n";

echo "\n=== VERIFICATION COMPLETE ===\n";