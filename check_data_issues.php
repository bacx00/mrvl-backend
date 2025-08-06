<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "\n=== CHECKING FOR POTENTIAL ISSUES ===\n\n";

// Check for teams without players
$teamsWithoutPlayers = Team::where('status', 'active')
    ->whereDoesntHave('players', function($query) {
        $query->where('status', 'active');
    })->get();

if ($teamsWithoutPlayers->count() > 0) {
    echo "⚠️  Teams without active players:\n";
    foreach ($teamsWithoutPlayers as $team) {
        echo "   - {$team->name} (ID: {$team->id})\n";
    }
    echo "\n";
} else {
    echo "✅ All teams have active players\n\n";
}

// Check for players without teams
$playersWithoutTeams = Player::where('status', 'active')
    ->whereNull('team_id')
    ->count();

if ($playersWithoutTeams > 0) {
    echo "⚠️  Active players without teams: $playersWithoutTeams\n\n";
} else {
    echo "✅ All active players have teams\n\n";
}

// Check for missing required fields
$playersWithMissingData = Player::where('status', 'active')
    ->where(function($query) {
        $query->whereNull('role')
              ->orWhereNull('main_hero')
              ->orWhereNull('country')
              ->orWhereNull('region');
    })->get();

if ($playersWithMissingData->count() > 0) {
    echo "⚠️  Players with missing data:\n";
    foreach ($playersWithMissingData as $player) {
        $missing = [];
        if (!$player->role) $missing[] = 'role';
        if (!$player->main_hero) $missing[] = 'main_hero';
        if (!$player->country) $missing[] = 'country';
        if (!$player->region) $missing[] = 'region';
        echo "   - {$player->username} missing: " . implode(', ', $missing) . "\n";
    }
    echo "\n";
} else {
    echo "✅ All active players have required data\n\n";
}

// Check for teams with more than 6 active players (excluding coaches)
$teamsWithTooManyPlayers = [];
$teams = Team::where('status', 'active')->get();
foreach ($teams as $team) {
    $activePlayerCount = Player::where('team_id', $team->id)
        ->where('status', 'active')
        ->where(function($query) {
            $query->where('team_position', 'player')
                  ->orWhereNull('team_position');
        })->count();
    
    if ($activePlayerCount > 6) {
        $teamsWithTooManyPlayers[] = [
            'team' => $team,
            'count' => $activePlayerCount
        ];
    }
}

if (count($teamsWithTooManyPlayers) > 0) {
    echo "⚠️  Teams with more than 6 active players:\n";
    foreach ($teamsWithTooManyPlayers as $item) {
        echo "   - {$item['team']->name}: {$item['count']} players\n";
    }
    echo "\n";
} else {
    echo "✅ All teams have 6 or fewer active players\n\n";
}

// Check for duplicate usernames
$duplicates = Player::select('username', DB::raw('COUNT(*) as count'))
    ->groupBy('username')
    ->having('count', '>', 1)
    ->get();

if ($duplicates->count() > 0) {
    echo "⚠️  Duplicate player usernames found:\n";
    foreach ($duplicates as $dup) {
        echo "   - {$dup->username}: {$dup->count} occurrences\n";
    }
    echo "\n";
} else {
    echo "✅ No duplicate player usernames\n\n";
}

// Check for teams with invalid regions
$teamsWithInvalidRegions = Team::where('status', 'active')
    ->whereNotIn('region', ['NA', 'EU', 'APAC', 'OCE'])
    ->get();

if ($teamsWithInvalidRegions->count() > 0) {
    echo "⚠️  Teams with invalid regions:\n";
    foreach ($teamsWithInvalidRegions as $team) {
        echo "   - {$team->name}: {$team->region}\n";
    }
    echo "\n";
} else {
    echo "✅ All teams have valid regions\n\n";
}

// Check for invalid roles
$playersWithInvalidRoles = Player::where('status', 'active')
    ->whereNotIn('role', ['Duelist', 'Vanguard', 'Strategist', 'Support', 'Flex'])
    ->get();

if ($playersWithInvalidRoles->count() > 0) {
    echo "⚠️  Players with invalid roles:\n";
    foreach ($playersWithInvalidRoles as $player) {
        echo "   - {$player->username}: {$player->role}\n";
    }
    echo "\n";
} else {
    echo "✅ All players have valid roles\n\n";
}

// Check for missing country flags
$playersWithoutFlags = Player::where('status', 'active')
    ->whereNull('country_flag')
    ->count();

if ($playersWithoutFlags > 0) {
    echo "⚠️  Active players without country flags: $playersWithoutFlags\n\n";
} else {
    echo "✅ All active players have country flags\n\n";
}

// Check for inconsistent team positions
$invalidPositions = Player::where('status', 'active')
    ->whereNotNull('team_position')
    ->whereNotIn('team_position', ['player', 'coach', 'assistant_coach', 'manager', 'analyst', 'bench', 'substitute', 'inactive'])
    ->get();

if ($invalidPositions->count() > 0) {
    echo "⚠️  Players with invalid team positions:\n";
    foreach ($invalidPositions as $player) {
        echo "   - {$player->username}: {$player->team_position}\n";
    }
    echo "\n";
} else {
    echo "✅ All players have valid team positions\n\n";
}

// Summary
$totalIssues = 0;
$totalIssues += $teamsWithoutPlayers->count();
$totalIssues += $playersWithoutTeams;
$totalIssues += $playersWithMissingData->count();
$totalIssues += count($teamsWithTooManyPlayers);
$totalIssues += $duplicates->count();
$totalIssues += $teamsWithInvalidRegions->count();
$totalIssues += $playersWithInvalidRoles->count();
$totalIssues += $playersWithoutFlags;
$totalIssues += $invalidPositions->count();

echo "\n=== SUMMARY ===\n";
if ($totalIssues == 0) {
    echo "✅ No issues found! All data appears to be consistent.\n";
} else {
    echo "⚠️  Total issues found: $totalIssues\n";
    echo "Please review the issues above to ensure smooth testing.\n";
}

echo "\n=== CHECK COMPLETE ===\n";