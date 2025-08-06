<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "=== DATA VERIFICATION REPORT ===\n\n";

// 1. Total counts
$totalTeams = Team::count();
$totalPlayers = Player::count();

echo "TOTAL COUNTS:\n";
echo "- Teams: $totalTeams\n";
echo "- Players: $totalPlayers\n\n";

// 2. By region
echo "BY REGION:\n";
$regions = Team::select('region', DB::raw('COUNT(*) as team_count'))
    ->groupBy('region')
    ->orderBy('region')
    ->get();

foreach ($regions as $region) {
    $playerCount = Player::where('region', $region->region)->count();
    echo "- {$region->region}: {$region->team_count} teams, $playerCount players\n";
}

// 3. Check for missing data
echo "\nDATA QUALITY CHECKS:\n";

// Players without real names
$playersNoRealName = Player::whereNull('real_name')->orWhere('real_name', '')->count();
echo "- Players without real names: $playersNoRealName\n";

// Players without countries
$playersNoCountry = Player::whereNull('country')->orWhere('country', '')->count();
echo "- Players without country: $playersNoCountry\n";

// Teams without coaches
$teamsNoCoach = Team::whereNull('coach')->orWhere('coach', '')->count();
echo "- Teams without coaches: $teamsNoCoach\n";

// Players per role
echo "\nPLAYERS BY ROLE:\n";
$roles = Player::select('role', DB::raw('COUNT(*) as count'))
    ->groupBy('role')
    ->orderBy('count', 'desc')
    ->get();

foreach ($roles as $role) {
    echo "- {$role->role}: {$role->count} players\n";
}

// 4. Sample data
echo "\nSAMPLE TEAMS:\n";
$sampleTeams = Team::inRandomOrder()->limit(5)->get();
foreach ($sampleTeams as $team) {
    $playerCount = $team->players()->count();
    echo "- {$team->name} ({$team->short_name}) - {$team->region} - {$playerCount} players\n";
}

echo "\nSAMPLE PLAYERS:\n";
$samplePlayers = Player::with('team')->inRandomOrder()->limit(10)->get();
foreach ($samplePlayers as $player) {
    $teamName = $player->team ? $player->team->name : 'No Team';
    echo "- {$player->username} ({$player->real_name}) - {$player->role} - {$teamName} [{$player->country}]\n";
}

// 5. Check team history
echo "\nTEAM HISTORY RECORDS:\n";
$historyCount = DB::table('player_team_history')->count();
echo "- Total history records: $historyCount\n";

// 6. Countries distribution
echo "\nCOUNTRIES REPRESENTED:\n";
$countries = Player::select('country', DB::raw('COUNT(*) as count'))
    ->groupBy('country')
    ->orderBy('count', 'desc')
    ->limit(10)
    ->get();

foreach ($countries as $country) {
    echo "- {$country->country}: {$country->count} players\n";
}

echo "\n=== END OF REPORT ===\n";