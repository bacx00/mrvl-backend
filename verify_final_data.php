<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== VERIFIED MARVEL RIVALS TOURNAMENT DATA ===\n\n";

// Count summary
$totalTeams = Team::where('status', 'active')->count();
$totalPlayers = Player::where('status', 'active')->count();
$coaches = Player::where('team_position', 'coach')->count();
$activePlayers = Player::where('team_position', 'player')->where('status', 'active')->count();

echo "SUMMARY:\n";
echo "Total Teams: $totalTeams\n";
echo "Total Players: $totalPlayers\n";
echo "Active Players: $activePlayers\n";
echo "Coaches: $coaches\n\n";

// Show teams by region
$regions = ['NA', 'EU', 'APAC', 'OCE'];

foreach ($regions as $region) {
    echo "\n=== $region REGION ===\n";
    $teams = Team::where('region', $region)->where('status', 'active')->get();
    
    foreach ($teams as $team) {
        echo "\n{$team->name} ({$team->short_name}) - {$team->country} {$team->country_flag}\n";
        
        // Get roster
        $players = Player::where('team_id', $team->id)
            ->whereIn('team_position', ['player', null])
            ->where('status', 'active')
            ->orderBy('position_order')
            ->limit(6)
            ->get();
            
        foreach ($players as $player) {
            echo "  - {$player->username}";
            if ($player->real_name) {
                echo " ({$player->real_name})";
            }
            echo " - {$player->role}";
            if ($player->country_flag) {
                echo " {$player->country_flag}";
            }
            echo "\n";
        }
        
        // Get coach
        $coach = Player::where('team_id', $team->id)
            ->where('team_position', 'coach')
            ->first();
            
        if ($coach) {
            echo "  Coach: {$coach->username}";
            if ($coach->real_name) {
                echo " ({$coach->real_name})";
            }
            echo "\n";
        }
    }
}

echo "\n=== DATA VERIFICATION COMPLETE ===\n";