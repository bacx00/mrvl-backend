<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== 2025 MARVEL RIVALS DATA VERIFICATION ===\n";
echo str_repeat("=", 50) . "\n\n";

// Check teams with incomplete rosters
echo "🔍 TEAMS WITH INCOMPLETE ROSTERS (less than 6 players):\n";
echo str_repeat("-", 40) . "\n";
$incompleteTeams = Team::with('players')->get()->filter(function($team) {
    return $team->players->count() < 6;
});

if ($incompleteTeams->count() > 0) {
    foreach ($incompleteTeams as $team) {
        echo "❌ {$team->name}: {$team->players->count()} players\n";
    }
} else {
    echo "✅ All teams have complete 6-player rosters!\n";
}

echo "\n";

// Check teams with missing critical information
echo "🔍 TEAMS WITH MISSING INFORMATION:\n";
echo str_repeat("-", 40) . "\n";
$teamsWithMissingInfo = Team::where(function($query) {
    $query->whereNull('earnings')
          ->orWhereNull('ranking')
          ->orWhere('earnings', 0)
          ->orWhere('ranking', 0);
})->get();

if ($teamsWithMissingInfo->count() > 0) {
    foreach ($teamsWithMissingInfo as $team) {
        echo "❌ {$team->name} - Missing: ";
        if (!$team->earnings || $team->earnings == 0) echo "earnings ";
        if (!$team->ranking || $team->ranking == 0) echo "ranking ";
        echo "\n";
    }
} else {
    echo "✅ All teams have complete information!\n";
}

echo "\n";

// Check players with missing information
echo "🔍 PLAYERS WITH MISSING INFORMATION:\n";
echo str_repeat("-", 40) . "\n";
$playersWithMissingInfo = Player::where(function($query) {
    $query->whereNull('real_name')
          ->orWhereNull('country') 
          ->orWhereNull('role')
          ->orWhereNull('earnings')
          ->orWhere('real_name', '')
          ->orWhere('country', '')
          ->orWhere('role', '');
})->get();

if ($playersWithMissingInfo->count() > 0) {
    foreach ($playersWithMissingInfo as $player) {
        echo "❌ {$player->username} - Missing: ";
        if (!$player->real_name || $player->real_name == '') echo "real_name ";
        if (!$player->country || $player->country == '') echo "country ";
        if (!$player->role || $player->role == '') echo "role ";
        if (!$player->earnings) echo "earnings ";
        echo "\n";
    }
} else {
    echo "✅ All players have complete information!\n";
}

echo "\n";

// Check ranking distribution
echo "📊 RANKING VERIFICATION:\n";
echo str_repeat("-", 40) . "\n";
$regionalRankings = Team::selectRaw('region, MIN(ranking) as best_rank, MAX(ranking) as worst_rank, COUNT(*) as team_count')
    ->where('ranking', '>', 0)
    ->groupBy('region')
    ->orderBy('best_rank')
    ->get();

foreach ($regionalRankings as $ranking) {
    echo "🏆 {$ranking->region}: {$ranking->team_count} teams (Ranks #{$ranking->best_rank}-#{$ranking->worst_rank})\n";
}

echo "\n";

// Check earnings distribution
echo "💰 EARNINGS VERIFICATION:\n";
echo str_repeat("-", 40) . "\n";
$totalEarnings = Team::sum('earnings');
$avgEarnings = Team::avg('earnings');
$topEarner = Team::orderBy('earnings', 'desc')->first();
$bottomEarner = Team::where('earnings', '>', 0)->orderBy('earnings', 'asc')->first();

echo "Total Prize Pool: $" . number_format($totalEarnings) . "\n";
echo "Average Team Earnings: $" . number_format($avgEarnings) . "\n";
echo "Highest Earner: {$topEarner->name} - $" . number_format($topEarner->earnings) . "\n";
echo "Lowest Earner: {$bottomEarner->name} - $" . number_format($bottomEarner->earnings) . "\n";

echo "\n";

// Final summary
echo "📈 FINAL SUMMARY:\n";
echo str_repeat("-", 40) . "\n";
$totalTeams = Team::count();
$totalPlayers = Player::count();
$teamsWithRankings = Team::where('ranking', '>', 0)->count();
$playersWithEarnings = Player::where('earnings', '>', 0)->count();

echo "✅ Total Teams: $totalTeams\n";
echo "✅ Total Players: $totalPlayers\n";
echo "✅ Teams with Rankings: $teamsWithRankings/$totalTeams (" . round(($teamsWithRankings/$totalTeams)*100, 1) . "%)\n";
echo "✅ Players with Earnings: $playersWithEarnings/$totalPlayers (" . round(($playersWithEarnings/$totalPlayers)*100, 1) . "%)\n";

echo "\n🎯 DATA QUALITY SCORE: ";
$completenessScore = 0;
if ($incompleteTeams->count() == 0) $completenessScore += 25;
if ($teamsWithMissingInfo->count() == 0) $completenessScore += 25;
if ($playersWithMissingInfo->count() == 0) $completenessScore += 25;
if (($teamsWithRankings/$totalTeams) > 0.8) $completenessScore += 25;

echo "$completenessScore/100\n";

if ($completenessScore == 100) {
    echo "🏆 PERFECT! All data is complete and accurate!\n";
} elseif ($completenessScore >= 75) {
    echo "✅ EXCELLENT! Data is nearly complete!\n";
} elseif ($completenessScore >= 50) {
    echo "⚠️  GOOD! Some data needs attention!\n";
} else {
    echo "❌ NEEDS WORK! Significant data gaps found!\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "VERIFICATION COMPLETE\n";