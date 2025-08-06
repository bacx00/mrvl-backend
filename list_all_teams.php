<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$teams = DB::table('teams')->orderBy('rating', 'desc')->get();

echo "\n==========================================\n";
echo "ALL MARVEL RIVALS TEAMS & ROSTERS\n";
echo "==========================================\n\n";

$count = 1;
foreach($teams as $team) {
    echo "#{$count} {$team->name}";
    if($team->short_name && $team->short_name != $team->name) {
        echo " ({$team->short_name})";
    }
    echo " - {$team->region} - Rating: {$team->rating}\n";
    
    if($team->coach) {
        echo "   Coach: {$team->coach}\n";
    }
    
    echo "   Players:\n";
    $players = DB::table('players')->where('team_id', $team->id)->orderBy('role')->get();
    foreach($players as $player) {
        echo "     • {$player->username} ({$player->role} - {$player->main_hero})\n";
    }
    echo "\n";
    $count++;
}

echo "==========================================\n";
echo "Total: " . $teams->count() . " teams\n";