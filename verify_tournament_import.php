<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "Verifying tournament data import...\n\n";

// Check teams
$teams = Team::with(['players' => function($query) {
    $query->orderBy('position_order', 'asc');
}])->orderBy('region')->get();

echo "Total teams imported: " . $teams->count() . "\n\n";

foreach ($teams as $team) {
    echo "Team: {$team->name} ({$team->short_name})\n";
    echo "  Region: {$team->region}, Country: {$team->country} {$team->country_flag}\n";
    echo "  Roster ({$team->players->count()} members):\n";
    
    $players = $team->players->where('team_position', 'player');
    $coaches = $team->players->whereIn('team_position', ['coach', 'assistant_coach']);
    $staff = $team->players->whereIn('team_position', ['manager', 'analyst']);
    $bench = $team->players->whereIn('team_position', ['bench', 'substitute']);
    $inactive = $team->players->where('team_position', 'inactive');
    
    if ($players->count() > 0) {
        echo "    Active Players:\n";
        foreach ($players as $player) {
            echo "      - {$player->username}";
            if ($player->real_name && $player->real_name != $player->username) {
                echo " ({$player->real_name})";
            }
            echo " - {$player->role} {$player->country_flag}\n";
        }
    }
    
    if ($coaches->count() > 0) {
        echo "    Coaching Staff:\n";
        foreach ($coaches as $coach) {
            echo "      - {$coach->username} - {$coach->team_position}\n";
        }
    }
    
    if ($staff->count() > 0) {
        echo "    Support Staff:\n";
        foreach ($staff as $member) {
            echo "      - {$member->username} - {$member->team_position}\n";
        }
    }
    
    if ($bench->count() > 0) {
        echo "    Bench/Substitute:\n";
        foreach ($bench as $player) {
            echo "      - {$player->username} - {$player->role}\n";
        }
    }
    
    if ($inactive->count() > 0) {
        echo "    Inactive:\n";
        foreach ($inactive as $player) {
            echo "      - {$player->username} - {$player->role}\n";
        }
    }
    
    echo "\n";
}

// Summary
$totalPlayers = Player::count();
$activePlayers = Player::where('team_position', 'player')->count();
$coaches = Player::whereIn('team_position', ['coach', 'assistant_coach'])->count();
$staff = Player::whereIn('team_position', ['manager', 'analyst'])->count();
$bench = Player::whereIn('team_position', ['bench', 'substitute'])->count();
$inactive = Player::where('team_position', 'inactive')->count();

echo "\nSummary:\n";
echo "Total personnel: $totalPlayers\n";
echo "- Active players: $activePlayers\n";
echo "- Coaches: $coaches\n";
echo "- Support staff: $staff\n";
echo "- Bench/Substitute: $bench\n";
echo "- Inactive: $inactive\n";