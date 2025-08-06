<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Load environment variables
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            putenv($line);
        }
    }
}

// Set up database connection
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_DATABASE') ?: 'mrvl_db',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Check team roster completeness
$team_counts = DB::table('teams')
    ->leftJoin('players', 'teams.id', '=', 'players.team_id')
    ->select('teams.id', 'teams.name', 'teams.short_name', DB::raw('COUNT(players.id) as player_count'))
    ->groupBy('teams.id', 'teams.name', 'teams.short_name')
    ->orderBy('player_count', 'desc')
    ->get();

echo "Team Roster Analysis:\n";
echo "====================\n";

$complete_teams = 0;
$incomplete_teams = 0;
$total_players = 0;

foreach ($team_counts as $team) {
    echo sprintf("%-25s (%4s): %d players\n", $team->name, $team->short_name, $team->player_count);
    
    if ($team->player_count == 6) {
        $complete_teams++;
    } else {
        $incomplete_teams++;
    }
    
    $total_players += $team->player_count;
}

echo "\nSummary:\n";
echo "========\n";
echo "Total teams: " . count($team_counts) . "\n";
echo "Teams with complete rosters (6 players): $complete_teams\n";
echo "Teams with incomplete rosters: $incomplete_teams\n";
echo "Total players across all teams: $total_players\n";

// Check if we have the minimum required teams
if (count($team_counts) >= 30) {
    echo "✓ PASS: We have " . count($team_counts) . " teams (requirement: 30+)\n";
} else {
    echo "✗ FAIL: We only have " . count($team_counts) . " teams (requirement: 30+)\n";
}

if ($complete_teams >= 30) {
    echo "✓ PASS: We have $complete_teams teams with complete rosters\n";
} else {
    echo "✗ FAIL: We only have $complete_teams teams with complete rosters (need 30+ for production)\n";
}

?>