<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "COMPREHENSIVE MARVEL RIVALS DATABASE AUDIT\n";
echo "========================================\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

// 1. DATABASE CONNECTION TEST
echo "1. DATABASE CONNECTION TEST\n";
echo "----------------------------------------\n";
try {
    $connectionTest = DB::connection()->getPdo();
    echo "✅ Database connection: SUCCESS\n";
} catch (Exception $e) {
    echo "❌ Database connection: FAILED - " . $e->getMessage() . "\n";
    exit(1);
}

// 2. BASIC STATISTICS
echo "\n2. DATABASE STATISTICS\n";
echo "----------------------------------------\n";

$totalPlayers = DB::table('players')->count();
$totalTeams = DB::table('teams')->count();
$totalEvents = DB::table('events')->count();
$totalMatches = DB::table('matches')->count();
$totalNews = DB::table('news')->count();
$totalForumThreads = DB::table('forum_threads')->count();

echo "Total Players: {$totalPlayers}\n";
echo "Total Teams: {$totalTeams}\n";
echo "Total Events: {$totalEvents}\n";
echo "Total Matches: {$totalMatches}\n";
echo "Total News Articles: {$totalNews}\n";
echo "Total Forum Threads: {$totalForumThreads}\n";

// 3. CHECK FOR DUPLICATE PLAYERS
echo "\n3. DUPLICATE PLAYER ANALYSIS\n";
echo "----------------------------------------\n";

// Check duplicates by username
$duplicateUsernames = DB::table('players')
    ->select('username', DB::raw('COUNT(*) as count'))
    ->whereNotNull('username')
    ->where('username', '!=', '')
    ->groupBy('username')
    ->having('count', '>', 1)
    ->get();

if ($duplicateUsernames->count() > 0) {
    echo "⚠️  DUPLICATE USERNAMES FOUND ({$duplicateUsernames->count()}):\n";
    foreach ($duplicateUsernames as $dup) {
        echo "   - Username: '{$dup->username}' appears {$dup->count} times\n";
        
        $dupDetails = DB::table('players')
            ->leftJoin('teams', 'players.team_id', '=', 'teams.id')
            ->where('players.username', $dup->username)
            ->select('players.id', 'players.name', 'players.team_id', 'teams.name as team_name')
            ->get();
            
        foreach ($dupDetails as $player) {
            $teamInfo = $player->team_name ? "Team: {$player->team_name}" : "No team";
            echo "     ID: {$player->id}, Name: '{$player->name}', {$teamInfo}\n";
        }
        echo "\n";
    }
} else {
    echo "✅ No duplicate player usernames found!\n";
}

// Check duplicates by name
$duplicateNames = DB::table('players')
    ->select('name', DB::raw('COUNT(*) as count'))
    ->whereNotNull('name')
    ->where('name', '!=', '')
    ->groupBy('name')
    ->having('count', '>', 1)
    ->get();

if ($duplicateNames->count() > 0) {
    echo "\n⚠️  DUPLICATE PLAYER NAMES FOUND ({$duplicateNames->count()}):\n";
    foreach ($duplicateNames as $dup) {
        echo "   - Name: '{$dup->name}' appears {$dup->count} times\n";
        
        $dupDetails = DB::table('players')
            ->where('name', $dup->name)
            ->select('id', 'username', 'team_id')
            ->get();
            
        foreach ($dupDetails as $player) {
            echo "     ID: {$player->id}, Username: '{$player->username}', Team ID: {$player->team_id}\n";
        }
    }
} else {
    echo "✅ No duplicate player names found!\n";
}

// 4. CHECK FOR DUPLICATE TEAMS
echo "\n4. DUPLICATE TEAM ANALYSIS\n";
echo "----------------------------------------\n";

$duplicateTeams = DB::table('teams')
    ->select('name', DB::raw('COUNT(*) as count'))
    ->whereNotNull('name')
    ->where('name', '!=', '')
    ->groupBy('name')
    ->having('count', '>', 1)
    ->get();

if ($duplicateTeams->count() > 0) {
    echo "⚠️  DUPLICATE TEAMS FOUND ({$duplicateTeams->count()}):\n";
    foreach ($duplicateTeams as $dup) {
        echo "   - Team: '{$dup->name}' appears {$dup->count} times\n";
        
        $dupDetails = DB::table('teams')
            ->where('name', $dup->name)
            ->select('id', 'region', 'country', 'founded')
            ->get();
            
        foreach ($dupDetails as $team) {
            echo "     ID: {$team->id}, Region: {$team->region}, Country: {$team->country}, Founded: {$team->founded}\n";
        }
        echo "\n";
    }
} else {
    echo "✅ No duplicate teams found!\n";
}

// 5. PLAYERS BY ROLE DISTRIBUTION
echo "\n5. PLAYER ROLE DISTRIBUTION\n";
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

// 6. TEAMS BY REGION
echo "\n6. TEAM DISTRIBUTION BY REGION\n";
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

// 7. ROSTER COMPLETENESS ANALYSIS
echo "\n7. ROSTER COMPLETENESS ANALYSIS\n";
echo "----------------------------------------\n";

$teamRosterSizes = DB::table('teams')
    ->leftJoin('players', 'teams.id', '=', 'players.team_id')
    ->select('teams.id', 'teams.name', 'teams.region', DB::raw('COUNT(players.id) as player_count'))
    ->groupBy('teams.id', 'teams.name', 'teams.region')
    ->orderBy('player_count', 'desc')
    ->get();

$complete6Plus = 0;
$complete5 = 0;
$incomplete = 0;
$empty = 0;

foreach ($teamRosterSizes as $team) {
    if ($team->player_count >= 6) {
        $complete6Plus++;
    } elseif ($team->player_count == 5) {
        $complete5++;
    } elseif ($team->player_count > 0) {
        $incomplete++;
    } else {
        $empty++;
    }
}

echo "Teams with 6+ players: {$complete6Plus}\n";
echo "Teams with exactly 5 players: {$complete5}\n";
echo "Teams with 1-4 players: {$incomplete}\n";
echo "Teams with no players: {$empty}\n";

// Show teams with complete rosters
echo "\nTeams with Complete Rosters (6+ players):\n";
foreach ($teamRosterSizes as $team) {
    if ($team->player_count >= 6) {
        echo "✅ {$team->name} ({$team->region}): {$team->player_count} players\n";
    }
}

// Show teams that need players
echo "\nTeams Needing Players:\n";
foreach ($teamRosterSizes as $team) {
    if ($team->player_count > 0 && $team->player_count < 6) {
        $needed = 6 - $team->player_count;
        echo "⚠️  {$team->name} ({$team->region}): {$team->player_count} players (needs {$needed} more)\n";
    }
}

// 8. DATA QUALITY METRICS
echo "\n8. DATA QUALITY METRICS\n";
echo "----------------------------------------\n";

$playersWithoutName = DB::table('players')->whereNull('name')->orWhere('name', '')->count();
$playersWithoutRole = DB::table('players')->whereNull('role')->orWhere('role', '')->count();
$playersWithoutNationality = DB::table('players')->whereNull('nationality')->orWhere('nationality', '')->count();
$playersWithoutTeam = DB::table('players')->whereNull('team_id')->count();

$teamsWithoutRegion = DB::table('teams')->whereNull('region')->orWhere('region', '')->count();
$teamsWithoutCountry = DB::table('teams')->whereNull('country')->orWhere('country', '')->count();
$teamsWithoutLogo = DB::table('teams')->whereNull('logo')->orWhere('logo', '')->count();

echo "Data Completeness Issues:\n";
echo "Players without real name: {$playersWithoutName}\n";
echo "Players without role: {$playersWithoutRole}\n";
echo "Players without nationality: {$playersWithoutNationality}\n";
echo "Players without team (Free Agents): {$playersWithoutTeam}\n";
echo "Teams without region: {$teamsWithoutRegion}\n";
echo "Teams without country: {$teamsWithoutCountry}\n";
echo "Teams without logo: {$teamsWithoutLogo}\n";

// Calculate data completeness percentages
$playerDataCompleteness = (($totalPlayers - $playersWithoutName - $playersWithoutRole - $playersWithoutNationality) / ($totalPlayers * 3)) * 100;
$teamDataCompleteness = (($totalTeams - $teamsWithoutRegion - $teamsWithoutCountry - $teamsWithoutLogo) / ($totalTeams * 3)) * 100;

echo "\nData Quality Scores:\n";
echo "Player Data Completeness: " . round($playerDataCompleteness, 1) . "%\n";
echo "Team Data Completeness: " . round($teamDataCompleteness, 1) . "%\n";

// 9. SAVE DETAILED REPORTS TO FILES
echo "\n9. GENERATING DETAILED REPORTS\n";
echo "----------------------------------------\n";

// Generate complete team listing
$allTeams = DB::table('teams')
    ->orderBy('region')
    ->orderBy('name')
    ->get();

$teamReportFile = fopen('/var/www/mrvl-backend/COMPLETE_TEAMS_REPORT.txt', 'w');
fwrite($teamReportFile, "COMPLETE MARVEL RIVALS TEAMS REPORT\n");
fwrite($teamReportFile, "Generated: " . date('Y-m-d H:i:s') . "\n\n");

foreach ($allTeams as $index => $team) {
    $teamNum = $index + 1;
    fwrite($teamReportFile, "========================================\n");
    fwrite($teamReportFile, "TEAM #{$teamNum}: {$team->name}\n");
    fwrite($teamReportFile, "========================================\n");
    fwrite($teamReportFile, "ID: {$team->id}\n");
    fwrite($teamReportFile, "Region: " . ($team->region ?: 'Not Specified') . "\n");
    fwrite($teamReportFile, "Country: " . ($team->country ?: 'Not Specified') . "\n");
    fwrite($teamReportFile, "Founded: " . ($team->founded ?: 'Not Specified') . "\n");
    fwrite($teamReportFile, "Status: " . ($team->status ?: 'Active') . "\n");
    fwrite($teamReportFile, "Platform: " . ($team->platform ?: 'PC') . "\n");
    fwrite($teamReportFile, "Total Earnings: $" . number_format($team->earnings ?: 0, 2) . "\n");
    fwrite($teamReportFile, "Wins: " . ($team->wins ?: 0) . "\n");
    fwrite($teamReportFile, "Losses: " . ($team->losses ?: 0) . "\n");
    fwrite($teamReportFile, "ELO Rating: " . ($team->elo_rating ?: 'Unrated') . "\n");
    fwrite($teamReportFile, "Logo: " . ($team->logo ?: 'No logo') . "\n");
    
    // Get roster
    $roster = DB::table('players')
        ->where('team_id', $team->id)
        ->orderBy('role')
        ->orderBy('username')
        ->get();
    
    fwrite($teamReportFile, "\nROSTER ({$roster->count()}/6 players):\n");
    if ($roster->count() > 0) {
        foreach ($roster as $player) {
            $role = $player->role ?: 'Flex';
            $name = $player->name ? " ({$player->name})" : "";
            $nationality = $player->nationality ? " [{$player->nationality}]" : "";
            $earnings = $player->earnings > 0 ? " - \$" . number_format($player->earnings, 0) : "";
            
            fwrite($teamReportFile, "  • {$player->username}{$name} - {$role}{$nationality}{$earnings}\n");
        }
    } else {
        fwrite($teamReportFile, "  (No players assigned)\n");
    }
    
    fwrite($teamReportFile, "\n");
}

fclose($teamReportFile);

// Generate complete player listing
$allPlayers = DB::table('players')
    ->leftJoin('teams', 'players.team_id', '=', 'teams.id')
    ->select('players.*', 'teams.name as team_name', 'teams.region as team_region')
    ->orderBy('players.earnings', 'desc')
    ->orderBy('players.username')
    ->get();

$playerReportFile = fopen('/var/www/mrvl-backend/COMPLETE_PLAYERS_REPORT.txt', 'w');
fwrite($playerReportFile, "COMPLETE MARVEL RIVALS PLAYERS REPORT\n");
fwrite($playerReportFile, "Generated: " . date('Y-m-d H:i:s') . "\n\n");

foreach ($allPlayers as $index => $player) {
    $playerNum = $index + 1;
    fwrite($playerReportFile, "========================================\n");
    fwrite($playerReportFile, "PLAYER #{$playerNum}: {$player->username}\n");
    fwrite($playerReportFile, "========================================\n");
    fwrite($playerReportFile, "ID: {$player->id}\n");
    fwrite($playerReportFile, "Username: {$player->username}\n");
    fwrite($playerReportFile, "Real Name: " . ($player->name ?: 'Unknown') . "\n");
    fwrite($playerReportFile, "Alternate IDs: " . ($player->alternate_ids ?: 'None') . "\n");
    fwrite($playerReportFile, "Role: " . ($player->role ?: 'Flex') . "\n");
    fwrite($playerReportFile, "Nationality: " . ($player->nationality ?: 'Unknown') . "\n");
    fwrite($playerReportFile, "Birth Date: " . ($player->birth_date ?: 'Unknown') . "\n");
    fwrite($playerReportFile, "Region: " . ($player->region ?: 'Unknown') . "\n");
    fwrite($playerReportFile, "Status: " . ($player->status ?: 'Active') . "\n");
    fwrite($playerReportFile, "Current Team: " . ($player->team_name ? "{$player->team_name} ({$player->team_region})" : 'Free Agent') . "\n");
    fwrite($playerReportFile, "Total Earnings: $" . number_format($player->earnings ?: 0, 2) . "\n");
    fwrite($playerReportFile, "ELO Rating: " . ($player->elo_rating ?: 'Unrated') . "\n");
    
    if ($player->hero_pool) {
        $heroes = json_decode($player->hero_pool, true);
        if ($heroes && is_array($heroes) && count($heroes) > 0) {
            fwrite($playerReportFile, "Hero Pool: " . implode(', ', $heroes) . "\n");
        }
    }
    
    fwrite($playerReportFile, "\n");
}

fclose($playerReportFile);

echo "✅ Detailed reports saved:\n";
echo "   - /var/www/mrvl-backend/COMPLETE_TEAMS_REPORT.txt\n";
echo "   - /var/www/mrvl-backend/COMPLETE_PLAYERS_REPORT.txt\n";

// 10. FINAL SUMMARY
echo "\n10. AUDIT SUMMARY\n";
echo "========================================\n";

$duplicateCount = $duplicateUsernames->count() + $duplicateNames->count() + $duplicateTeams->count();

if ($duplicateCount > 0) {
    echo "❌ DUPLICATES FOUND: {$duplicateCount} issues need attention\n";
} else {
    echo "✅ NO DUPLICATES FOUND: Database is clean!\n";
}

echo "\nDatabase Health Score:\n";
$healthScore = 100;
if ($duplicateCount > 0) $healthScore -= ($duplicateCount * 5);
if ($playersWithoutName > ($totalPlayers * 0.1)) $healthScore -= 10;
if ($playersWithoutRole > ($totalPlayers * 0.1)) $healthScore -= 10;
if ($empty > ($totalTeams * 0.2)) $healthScore -= 5;

echo "Overall Health: {$healthScore}%\n";

echo "\n========================================\n";
echo "AUDIT COMPLETE - " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";