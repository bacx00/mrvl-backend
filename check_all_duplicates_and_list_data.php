<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "MARVEL RIVALS DATABASE AUDIT REPORT\n";
echo "========================================\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

// 1. CHECK FOR DUPLICATE PLAYERS
echo "1. CHECKING FOR DUPLICATE PLAYERS\n";
echo "----------------------------------------\n";

$duplicatePlayers = DB::table('players')
    ->select('handle', DB::raw('COUNT(*) as count'))
    ->groupBy('handle')
    ->having('count', '>', 1)
    ->get();

if ($duplicatePlayers->count() > 0) {
    echo "⚠️  DUPLICATES FOUND:\n";
    foreach ($duplicatePlayers as $dup) {
        echo "   - Handle: {$dup->handle} appears {$dup->count} times\n";
        
        // Show details of duplicates
        $dupDetails = DB::table('players')->where('handle', $dup->handle)->get();
        foreach ($dupDetails as $player) {
            echo "     ID: {$player->id}, Name: {$player->name}, Team ID: {$player->team_id}\n";
        }
    }
} else {
    echo "✅ No duplicate players found!\n";
}

// 2. CHECK FOR DUPLICATE TEAMS
echo "\n2. CHECKING FOR DUPLICATE TEAMS\n";
echo "----------------------------------------\n";

$duplicateTeams = DB::table('teams')
    ->select('name', DB::raw('COUNT(*) as count'))
    ->groupBy('name')
    ->having('count', '>', 1)
    ->get();

if ($duplicateTeams->count() > 0) {
    echo "⚠️  DUPLICATES FOUND:\n";
    foreach ($duplicateTeams as $dup) {
        echo "   - Team: {$dup->name} appears {$dup->count} times\n";
        
        // Show details of duplicates
        $dupDetails = DB::table('teams')->where('name', $dup->name)->get();
        foreach ($dupDetails as $team) {
            echo "     ID: {$team->id}, Region: {$team->region}, Country: {$team->country}\n";
        }
    }
} else {
    echo "✅ No duplicate teams found!\n";
}

// 3. DATABASE STATISTICS
echo "\n3. DATABASE STATISTICS\n";
echo "----------------------------------------\n";

$totalPlayers = DB::table('players')->count();
$totalTeams = DB::table('teams')->count();
$playersWithTeams = DB::table('players')->whereNotNull('team_id')->count();
$playersWithoutTeams = DB::table('players')->whereNull('team_id')->count();
$teamsWithLogos = DB::table('teams')->whereNotNull('logo')->where('logo', '!=', '')->count();
$playersWithEarnings = DB::table('players')->where('total_earnings', '>', 0)->count();

echo "Total Players: {$totalPlayers}\n";
echo "Total Teams: {$totalTeams}\n";
echo "Players with Teams: {$playersWithTeams}\n";
echo "Players without Teams: {$playersWithoutTeams}\n";
echo "Teams with Logos: {$teamsWithLogos}\n";
echo "Players with Earnings: {$playersWithEarnings}\n";

// 4. PLAYERS BY ROLE
echo "\n4. PLAYERS BY ROLE\n";
echo "----------------------------------------\n";

$roleDistribution = DB::table('players')
    ->select('role', DB::raw('COUNT(*) as count'))
    ->groupBy('role')
    ->orderBy('count', 'desc')
    ->get();

foreach ($roleDistribution as $role) {
    $roleName = $role->role ?: 'Not Specified';
    echo "{$roleName}: {$role->count} players\n";
}

// 5. TEAMS BY REGION
echo "\n5. TEAMS BY REGION\n";
echo "----------------------------------------\n";

$regionDistribution = DB::table('teams')
    ->select('region', DB::raw('COUNT(*) as count'))
    ->groupBy('region')
    ->orderBy('count', 'desc')
    ->get();

foreach ($regionDistribution as $region) {
    $regionName = $region->region ?: 'Not Specified';
    echo "{$regionName}: {$region->count} teams\n";
}

// 6. LIST ALL TEAMS WITH ROSTERS
echo "\n6. ALL TEAMS WITH ROSTERS\n";
echo "========================================\n\n";

$teams = DB::table('teams')
    ->orderBy('region')
    ->orderBy('name')
    ->get();

foreach ($teams as $index => $team) {
    echo "TEAM #" . ($index + 1) . ": {$team->name}\n";
    echo "----------------------------------------\n";
    echo "ID: {$team->id}\n";
    echo "Region: " . ($team->region ?: 'N/A') . "\n";
    echo "Country: " . ($team->country ?: 'N/A') . "\n";
    echo "Founded: " . ($team->founded ?: 'N/A') . "\n";
    echo "Earnings: $" . number_format($team->total_earnings ?: 0, 2) . "\n";
    echo "Logo: " . ($team->logo ?: 'No logo') . "\n";
    
    // Social Media
    if ($team->social_media) {
        $social = json_decode($team->social_media, true);
        if ($social && count($social) > 0) {
            echo "Social Media:\n";
            foreach ($social as $platform => $link) {
                echo "  - {$platform}: {$link}\n";
            }
        }
    }
    
    // Roster
    $players = DB::table('players')
        ->where('team_id', $team->id)
        ->orderBy('role')
        ->orderBy('handle')
        ->get();
    
    echo "\nRoster ({$players->count()} players):\n";
    if ($players->count() > 0) {
        foreach ($players as $player) {
            $role = $player->role ?: 'Flex';
            $earnings = number_format($player->total_earnings ?: 0, 0);
            echo "  • {$player->handle} ({$player->name}) - {$role}";
            if ($player->nationality) {
                echo " - {$player->nationality}";
            }
            if ($player->total_earnings > 0) {
                echo " - \${$earnings}";
            }
            echo "\n";
        }
    } else {
        echo "  (No players assigned)\n";
    }
    echo "\n";
}

// 7. LIST ALL PLAYERS WITH DETAILS
echo "\n7. ALL PLAYERS DETAILED LIST\n";
echo "========================================\n\n";

$players = DB::table('players')
    ->leftJoin('teams', 'players.team_id', '=', 'teams.id')
    ->select('players.*', 'teams.name as team_name')
    ->orderBy('players.total_earnings', 'desc')
    ->orderBy('players.handle')
    ->get();

foreach ($players as $index => $player) {
    echo "PLAYER #" . ($index + 1) . ": {$player->handle}\n";
    echo "----------------------------------------\n";
    echo "ID: {$player->id}\n";
    echo "Real Name: " . ($player->name ?: 'Unknown') . "\n";
    echo "Handle: {$player->handle}\n";
    echo "Alternate IDs: " . ($player->alternate_ids ?: 'None') . "\n";
    echo "Nationality: " . ($player->nationality ?: 'Unknown') . "\n";
    echo "Birth Date: " . ($player->birth_date ?: 'Unknown') . "\n";
    
    if ($player->birth_date) {
        $age = date_diff(date_create($player->birth_date), date_create('today'))->y;
        echo "Age: {$age}\n";
    }
    
    echo "Region: " . ($player->region ?: 'Unknown') . "\n";
    echo "Status: " . ($player->status ?: 'Unknown') . "\n";
    echo "Role: " . ($player->role ?: 'Flex') . "\n";
    echo "Current Team: " . ($player->team_name ?: 'Free Agent') . "\n";
    echo "Total Earnings: $" . number_format($player->total_earnings ?: 0, 2) . "\n";
    
    if ($player->signature_heroes) {
        $heroes = json_decode($player->signature_heroes, true);
        if ($heroes && count($heroes) > 0) {
            echo "Signature Heroes: " . implode(', ', $heroes) . "\n";
        }
    }
    
    if ($player->social_media) {
        $social = json_decode($player->social_media, true);
        if ($social && count($social) > 0) {
            echo "Social Media:\n";
            foreach ($social as $platform => $link) {
                echo "  - {$platform}: {$link}\n";
            }
        }
    }
    
    if ($player->achievements) {
        $achievements = json_decode($player->achievements, true);
        if ($achievements && count($achievements) > 0) {
            echo "Achievements:\n";
            foreach ($achievements as $achievement) {
                echo "  • {$achievement}\n";
            }
        }
    }
    
    // Check team history
    $history = DB::table('player_team_history')
        ->where('player_id', $player->id)
        ->orderBy('joined_at', 'desc')
        ->get();
    
    if ($history->count() > 0) {
        echo "Team History:\n";
        foreach ($history as $record) {
            $histTeam = DB::table('teams')->find($record->team_id);
            if ($histTeam) {
                echo "  • {$histTeam->name}: {$record->joined_at} to " . ($record->left_at ?: 'Present') . "\n";
            }
        }
    }
    
    echo "\n";
}

// 8. DATA QUALITY REPORT
echo "\n8. DATA QUALITY REPORT\n";
echo "========================================\n";

$playersWithoutName = DB::table('players')->whereNull('name')->orWhere('name', '')->count();
$playersWithoutRole = DB::table('players')->whereNull('role')->orWhere('role', '')->count();
$playersWithoutNationality = DB::table('players')->whereNull('nationality')->orWhere('nationality', '')->count();
$teamsWithoutRegion = DB::table('teams')->whereNull('region')->orWhere('region', '')->count();
$teamsWithoutCountry = DB::table('teams')->whereNull('country')->orWhere('country', '')->count();

echo "Players without real name: {$playersWithoutName}\n";
echo "Players without role: {$playersWithoutRole}\n";
echo "Players without nationality: {$playersWithoutNationality}\n";
echo "Teams without region: {$teamsWithoutRegion}\n";
echo "Teams without country: {$teamsWithoutCountry}\n";

// 9. ROSTER COMPLETENESS
echo "\n9. ROSTER COMPLETENESS\n";
echo "----------------------------------------\n";

$teamRosterSizes = DB::table('teams')
    ->leftJoin('players', 'teams.id', '=', 'players.team_id')
    ->select('teams.name', 'teams.id', DB::raw('COUNT(players.id) as player_count'))
    ->groupBy('teams.id', 'teams.name')
    ->orderBy('player_count', 'desc')
    ->get();

$complete6 = 0;
$incomplete = 0;
$empty = 0;

foreach ($teamRosterSizes as $team) {
    if ($team->player_count == 6) {
        $complete6++;
    } elseif ($team->player_count == 0) {
        $empty++;
        echo "⚠️  {$team->name}: No players\n";
    } else {
        $incomplete++;
        echo "⚠️  {$team->name}: {$team->player_count} players (needs 6)\n";
    }
}

echo "\nSummary:\n";
echo "Teams with 6 players: {$complete6}\n";
echo "Teams with incomplete rosters: {$incomplete}\n";
echo "Teams with no players: {$empty}\n";

echo "\n========================================\n";
echo "AUDIT COMPLETE - " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";