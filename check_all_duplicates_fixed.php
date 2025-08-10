<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

// Check duplicates by username
$duplicatePlayers = DB::table('players')
    ->select('username', DB::raw('COUNT(*) as count'))
    ->whereNotNull('username')
    ->groupBy('username')
    ->having('count', '>', 1)
    ->get();

if ($duplicatePlayers->count() > 0) {
    echo "⚠️  DUPLICATE USERNAMES FOUND:\n";
    foreach ($duplicatePlayers as $dup) {
        echo "   - Username: {$dup->username} appears {$dup->count} times\n";
        
        // Show details of duplicates
        $dupDetails = DB::table('players')->where('username', $dup->username)->get();
        foreach ($dupDetails as $player) {
            echo "     ID: {$player->id}, Name: {$player->name}, Team ID: {$player->team_id}\n";
        }
    }
} else {
    echo "✅ No duplicate player usernames found!\n";
}

// Check duplicates by name
$duplicateNames = DB::table('players')
    ->select('name', DB::raw('COUNT(*) as count'))
    ->whereNotNull('name')
    ->groupBy('name')
    ->having('count', '>', 1)
    ->get();

if ($duplicateNames->count() > 0) {
    echo "\n⚠️  DUPLICATE NAMES FOUND:\n";
    foreach ($duplicateNames as $dup) {
        echo "   - Name: {$dup->name} appears {$dup->count} times\n";
    }
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
echo "Players without Teams (Free Agents): {$playersWithoutTeams}\n";
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

// 6. LIST ALL TEAMS WITH ROSTERS (Limited output for readability)
echo "\n6. ALL TEAMS WITH ROSTERS (SUMMARY)\n";
echo "========================================\n\n";

$teams = DB::table('teams')
    ->orderBy('region')
    ->orderBy('name')
    ->get();

$outputFile = fopen('/var/www/mrvl-backend/FULL_TEAMS_LIST.txt', 'w');

foreach ($teams as $index => $team) {
    $output = "TEAM #" . ($index + 1) . ": {$team->name}\n";
    $output .= "----------------------------------------\n";
    $output .= "ID: {$team->id}\n";
    $output .= "Region: " . ($team->region ?: 'N/A') . "\n";
    $output .= "Country: " . ($team->country ?: 'N/A') . "\n";
    $output .= "Founded: " . ($team->founded ?: 'N/A') . "\n";
    $output .= "Total Earnings: $" . number_format($team->total_earnings ?: 0, 2) . "\n";
    $output .= "Logo: " . ($team->logo ?: 'No logo') . "\n";
    
    // Social Media
    if ($team->social_media) {
        $social = json_decode($team->social_media, true);
        if ($social && count($social) > 0) {
            $output .= "Social Media:\n";
            foreach ($social as $platform => $link) {
                $output .= "  - {$platform}: {$link}\n";
            }
        }
    }
    
    // Roster
    $players = DB::table('players')
        ->where('team_id', $team->id)
        ->orderBy('role')
        ->orderBy('username')
        ->get();
    
    $output .= "\nRoster ({$players->count()} players):\n";
    if ($players->count() > 0) {
        foreach ($players as $player) {
            $role = $player->role ?: 'Flex';
            $earnings = number_format($player->total_earnings ?: 0, 0);
            $output .= "  • {$player->username}";
            if ($player->name) {
                $output .= " ({$player->name})";
            }
            $output .= " - {$role}";
            if ($player->nationality) {
                $output .= " - {$player->nationality}";
            }
            if ($player->total_earnings > 0) {
                $output .= " - \${$earnings}";
            }
            $output .= "\n";
        }
    } else {
        $output .= "  (No players assigned)\n";
    }
    $output .= "\n";
    
    // Write to file
    fwrite($outputFile, $output);
    
    // Also output summary to console
    echo "Team #{$index + 1}: {$team->name} ({$team->region}) - {$players->count()} players\n";
}

fclose($outputFile);
echo "\nFull team details saved to: /var/www/mrvl-backend/FULL_TEAMS_LIST.txt\n";

// 7. LIST ALL PLAYERS (Summary only, full list to file)
echo "\n7. ALL PLAYERS SUMMARY\n";
echo "========================================\n\n";

$players = DB::table('players')
    ->leftJoin('teams', 'players.team_id', '=', 'teams.id')
    ->select('players.*', 'teams.name as team_name')
    ->orderBy('players.total_earnings', 'desc')
    ->orderBy('players.username')
    ->get();

$outputFile = fopen('/var/www/mrvl-backend/FULL_PLAYERS_LIST.txt', 'w');

foreach ($players as $index => $player) {
    $output = "PLAYER #" . ($index + 1) . ": {$player->username}\n";
    $output .= "----------------------------------------\n";
    $output .= "ID: {$player->id}\n";
    $output .= "Real Name: " . ($player->name ?: 'Unknown') . "\n";
    $output .= "Username: {$player->username}\n";
    $output .= "Alternate IDs: " . ($player->alternate_ids ?: 'None') . "\n";
    $output .= "Nationality: " . ($player->nationality ?: 'Unknown') . "\n";
    $output .= "Birth Date: " . ($player->birth_date ?: 'Unknown') . "\n";
    
    if ($player->birth_date) {
        $birthDate = new DateTime($player->birth_date);
        $now = new DateTime();
        $age = $now->diff($birthDate)->y;
        $output .= "Age: {$age}\n";
    }
    
    $output .= "Region: " . ($player->region ?: 'Unknown') . "\n";
    $output .= "Status: " . ($player->status ?: 'Unknown') . "\n";
    $output .= "Role: " . ($player->role ?: 'Flex') . "\n";
    $output .= "Current Team: " . ($player->team_name ?: 'Free Agent') . "\n";
    $output .= "Total Earnings: $" . number_format($player->total_earnings ?: 0, 2) . "\n";
    
    if ($player->hero_pool) {
        $heroes = json_decode($player->hero_pool, true);
        if ($heroes && count($heroes) > 0) {
            $output .= "Hero Pool: " . implode(', ', $heroes) . "\n";
        }
    }
    
    if ($player->social_media) {
        $social = json_decode($player->social_media, true);
        if ($social && count($social) > 0) {
            $output .= "Social Media:\n";
            foreach ($social as $platform => $link) {
                $output .= "  - {$platform}: {$link}\n";
            }
        }
    }
    
    // Check team history
    $history = DB::table('player_team_history')
        ->where('player_id', $player->id)
        ->orderBy('joined_at', 'desc')
        ->get();
    
    if ($history->count() > 0) {
        $output .= "Team History:\n";
        foreach ($history as $record) {
            $histTeam = DB::table('teams')->find($record->team_id);
            if ($histTeam) {
                $output .= "  • {$histTeam->name}: {$record->joined_at} to " . ($record->left_at ?: 'Present') . "\n";
            }
        }
    }
    
    $output .= "\n";
    
    // Write to file
    fwrite($outputFile, $output);
}

fclose($outputFile);

// Output summary to console
echo "Top 10 Players by Earnings:\n";
$topPlayers = DB::table('players')
    ->leftJoin('teams', 'players.team_id', '=', 'teams.id')
    ->select('players.username', 'players.name', 'players.total_earnings', 'teams.name as team_name')
    ->orderBy('players.total_earnings', 'desc')
    ->limit(10)
    ->get();

foreach ($topPlayers as $i => $player) {
    echo ($i + 1) . ". {$player->username} ({$player->team_name}) - $" . number_format($player->total_earnings, 0) . "\n";
}

echo "\nFull player details saved to: /var/www/mrvl-backend/FULL_PLAYERS_LIST.txt\n";

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
$complete5Plus = 0;
$incomplete = 0;
$empty = 0;

echo "\nTeams with Complete/Nearly Complete Rosters:\n";
foreach ($teamRosterSizes as $team) {
    if ($team->player_count >= 6) {
        $complete6++;
        echo "✅ {$team->name}: {$team->player_count} players\n";
    } elseif ($team->player_count >= 5) {
        $complete5Plus++;
        echo "⚠️  {$team->name}: {$team->player_count} players (needs 1 more)\n";
    } elseif ($team->player_count == 0) {
        $empty++;
    } else {
        $incomplete++;
    }
}

echo "\nSummary:\n";
echo "Teams with 6+ players: {$complete6}\n";
echo "Teams with 5 players: {$complete5Plus}\n";
echo "Teams with 1-4 players: {$incomplete}\n";
echo "Teams with no players: {$empty}\n";

// 10. CLEAN UP DUPLICATES IF ANY
echo "\n10. DUPLICATE CLEANUP RECOMMENDATIONS\n";
echo "----------------------------------------\n";

if ($duplicatePlayers->count() > 0 || $duplicateTeams->count() > 0) {
    echo "⚠️  Duplicates detected. Run cleanup script to resolve.\n";
} else {
    echo "✅ No duplicates found. Database is clean!\n";
}

echo "\n========================================\n";
echo "AUDIT COMPLETE - " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";
echo "\nDetailed lists saved to:\n";
echo "- /var/www/mrvl-backend/FULL_TEAMS_LIST.txt\n";
echo "- /var/www/mrvl-backend/FULL_PLAYERS_LIST.txt\n";