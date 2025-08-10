<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== Marvel Rivals Database Fix Testing ===" . PHP_EOL;

// Test 1: Database Schema
echo PHP_EOL . "TEST 1: Database Schema Verification" . PHP_EOL;

$teamColumns = collect(\Schema::getColumnListing('teams'));
$expectedCoachFields = ['coach_name', 'coach_nationality', 'coach_social_media'];

foreach ($expectedCoachFields as $field) {
    if ($teamColumns->contains($field)) {
        echo "✅ {$field} field exists" . PHP_EOL;
    } else {
        echo "❌ {$field} field missing" . PHP_EOL;
    }
}

// Test 2: Team Count and Coach Assignment
echo PHP_EOL . "TEST 2: Teams and Coaches" . PHP_EOL;

$totalTeams = Team::count();
$teamsWithCoaches = Team::whereNotNull('coach_name')->count();

echo "Total teams: {$totalTeams}" . PHP_EOL;
echo "Teams with coaches: {$teamsWithCoaches}" . PHP_EOL;

if ($totalTeams == 61 && $teamsWithCoaches == 61) {
    echo "✅ All 61 teams have coaches assigned" . PHP_EOL;
} else {
    echo "❌ Team/coach assignment issue" . PHP_EOL;
}

// Test 3: Player Roster Balance
echo PHP_EOL . "TEST 3: Player Roster Balance" . PHP_EOL;

$totalPlayers = Player::count();
$expectedPlayers = 61 * 6; // 61 teams × 6 players

echo "Total players: {$totalPlayers} (expected: {$expectedPlayers})" . PHP_EOL;

$roleStats = [
    'Duelist' => Player::where('role', 'Duelist')->count(),
    'Strategist' => Player::where('role', 'Strategist')->count(),
    'Vanguard' => Player::where('role', 'Vanguard')->count()
];

foreach ($roleStats as $role => $count) {
    $expected = 122; // 61 teams × 2 players per role
    echo "{$role}: {$count} (expected: {$expected}) ";
    if ($count == $expected) {
        echo "✅" . PHP_EOL;
    } else {
        echo "❌" . PHP_EOL;
    }
}

// Test 4: Team Roster Distribution
echo PHP_EOL . "TEST 4: Team Roster Distribution" . PHP_EOL;

$teamRosterIssues = 0;
$teams = Team::with('players')->get();

foreach ($teams as $team) {
    $roles = $team->players->countBy('role');
    $duelists = $roles->get('Duelist', 0);
    $strategists = $roles->get('Strategist', 0);
    $vanguards = $roles->get('Vanguard', 0);
    $totalPlayers = $team->players->count();
    
    if ($totalPlayers != 6 || $duelists != 2 || $strategists != 2 || $vanguards != 2) {
        echo "❌ {$team->name}: {$totalPlayers} players (D:{$duelists}, S:{$strategists}, V:{$vanguards})" . PHP_EOL;
        $teamRosterIssues++;
    }
}

if ($teamRosterIssues == 0) {
    echo "✅ All teams have balanced rosters (6 players: 2D, 2S, 2V)" . PHP_EOL;
} else {
    echo "❌ Found {$teamRosterIssues} teams with roster issues" . PHP_EOL;
}

// Test 5: Reference Teams Verification
echo PHP_EOL . "TEST 5: Reference Teams Verification" . PHP_EOL;

$referenceTeams = [
    '100 Thieves' => [
        'coach' => 'Tensa',
        'players' => ['delenaa', 'Terra', 'hxrvey', 'SJP', 'TTK', 'Vinnie']
    ],
    'Sentinels' => [
        'coach' => 'Crimzo',
        'players' => ['Rymazing', 'SuperGomez', 'aramori', 'Karova', 'Coluge', 'Hogz']
    ]
];

foreach ($referenceTeams as $teamName => $expected) {
    $team = Team::where('name', $teamName)->with('players')->first();
    
    if (!$team) {
        echo "❌ {$teamName}: Team not found" . PHP_EOL;
        continue;
    }
    
    // Check coach
    if ($team->coach_name == $expected['coach']) {
        echo "✅ {$teamName}: Coach {$expected['coach']} assigned" . PHP_EOL;
    } else {
        echo "❌ {$teamName}: Expected coach {$expected['coach']}, got {$team->coach_name}" . PHP_EOL;
    }
    
    // Check players
    $actualPlayers = $team->players->pluck('name')->sort()->values()->toArray();
    $expectedPlayers = collect($expected['players'])->sort()->values()->toArray();
    
    if ($actualPlayers == $expectedPlayers) {
        echo "✅ {$teamName}: All players correct" . PHP_EOL;
    } else {
        echo "❌ {$teamName}: Player mismatch" . PHP_EOL;
        echo "  Expected: " . implode(', ', $expectedPlayers) . PHP_EOL;
        echo "  Actual: " . implode(', ', $actualPlayers) . PHP_EOL;
    }
}

// Test 6: Model Fillable Fields
echo PHP_EOL . "TEST 6: Model Fillable Fields" . PHP_EOL;

$teamModel = new Team();
$fillableFields = $teamModel->getFillable();

$requiredCoachFields = ['coach_name', 'coach_nationality', 'coach_social_media'];
$allFieldsPresent = true;

foreach ($requiredCoachFields as $field) {
    if (in_array($field, $fillableFields)) {
        echo "✅ {$field} is fillable" . PHP_EOL;
    } else {
        echo "❌ {$field} is not fillable" . PHP_EOL;
        $allFieldsPresent = false;
    }
}

if ($allFieldsPresent) {
    echo "✅ All coach fields are fillable in Team model" . PHP_EOL;
}

// Test 7: Sample Coach Data
echo PHP_EOL . "TEST 7: Sample Coach Data" . PHP_EOL;

$sampleTeams = Team::whereNotNull('coach_name')->take(3)->get();

foreach ($sampleTeams as $team) {
    echo "✅ {$team->name}:" . PHP_EOL;
    echo "   Coach: {$team->coach_name}" . PHP_EOL;
    echo "   Nationality: {$team->coach_nationality}" . PHP_EOL;
    if ($team->coach_social_media) {
        $socialMedia = is_string($team->coach_social_media) ? 
            json_decode($team->coach_social_media, true) : $team->coach_social_media;
        echo "   Social: " . json_encode($socialMedia) . PHP_EOL;
    }
}

// Final Summary
echo PHP_EOL . "=== FINAL SUMMARY ===" . PHP_EOL;

$testResults = [
    'Schema' => $teamColumns->contains('coach_name') && $teamColumns->contains('coach_nationality') && $teamColumns->contains('coach_social_media'),
    'Teams/Coaches' => $totalTeams == 61 && $teamsWithCoaches == 61,
    'Player Count' => $totalPlayers == 366,
    'Role Balance' => $roleStats['Duelist'] == 122 && $roleStats['Strategist'] == 122 && $roleStats['Vanguard'] == 122,
    'Team Rosters' => $teamRosterIssues == 0,
    'Model Fields' => $allFieldsPresent
];

$passedTests = 0;
$totalTests = count($testResults);

foreach ($testResults as $test => $passed) {
    if ($passed) {
        echo "✅ {$test}: PASSED" . PHP_EOL;
        $passedTests++;
    } else {
        echo "❌ {$test}: FAILED" . PHP_EOL;
    }
}

echo PHP_EOL;
if ($passedTests == $totalTests) {
    echo "🎉 ALL TESTS PASSED! Database fix is complete and working correctly." . PHP_EOL;
} else {
    echo "⚠️  {$passedTests}/{$totalTests} tests passed. Some issues need attention." . PHP_EOL;
}

echo PHP_EOL . "Marvel Rivals Database Fix Testing Completed!" . PHP_EOL;