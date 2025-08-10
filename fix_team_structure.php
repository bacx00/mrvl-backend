<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "Starting Marvel Rivals Database Fix Script\n";
echo "========================================\n\n";

// Step 1: Analyze current state
echo "1. Analyzing current team structure...\n";
$teams = Team::withCount('players')->get();
$teamsNeedingAdjustment = [];

foreach ($teams as $team) {
    $playerCount = $team->players_count;
    echo "   {$team->name}: {$playerCount} players\n";
    
    if ($playerCount !== 6) {
        $teamsNeedingAdjustment[] = [
            'team' => $team,
            'current_count' => $playerCount,
            'needed_action' => $playerCount > 6 ? 'remove' : 'add'
        ];
    }
}

echo "\nTeams needing adjustment: " . count($teamsNeedingAdjustment) . "\n\n";

// Step 2: Handle teams with more than 6 players
echo "2. Handling teams with more than 6 players...\n";
foreach ($teamsNeedingAdjustment as $adjustment) {
    if ($adjustment['needed_action'] === 'remove') {
        $team = $adjustment['team'];
        $excessCount = $adjustment['current_count'] - 6;
        
        echo "   Processing {$team->name} (removing {$excessCount} players)...\n";
        
        // Get players sorted by earnings (keep highest earners)
        $playersToRemove = $team->players()
            ->orderBy('earnings', 'asc')
            ->limit($excessCount)
            ->get();
        
        foreach ($playersToRemove as $player) {
            $player->team_id = null;
            $player->save();
            echo "     - Moved {$player->name} to free agents\n";
        }
    }
}

// Step 3: Create additional players for teams with less than 6
echo "\n3. Adding players to teams with less than 6...\n";
$playerCounter = 1;

foreach ($teamsNeedingAdjustment as $adjustment) {
    if ($adjustment['needed_action'] === 'add') {
        $team = $adjustment['team'];
        $neededPlayers = 6 - $adjustment['current_count'];
        
        echo "   Adding {$neededPlayers} players to {$team->name}...\n";
        
        // Define role distribution for balanced teams
        $roles = ['Duelist', 'Tank', 'Support', 'Controller'];
        $heroes = [
            'Duelist' => ['Spider-Man', 'Iron Man', 'Star-Lord', 'Black Panther'],
            'Tank' => ['Hulk', 'Captain America', 'Groot', 'Thor'],
            'Support' => ['Luna Snow', 'Mantis', 'Adam Warlock', 'Cloak & Dagger'],
            'Controller' => ['Scarlet Witch', 'Doctor Strange', 'Loki', 'Storm']
        ];
        
        for ($i = 0; $i < $neededPlayers; $i++) {
            $role = $roles[array_rand($roles)];
            $hero = $heroes[$role][array_rand($heroes[$role])];
            
            $player = new Player();
            $player->name = "Player " . $playerCounter;
            $player->username = strtolower(str_replace(' ', '_', $team->short_name)) . "_player_" . $playerCounter;
            $player->real_name = "Generated Player " . $playerCounter;
            $player->team_id = $team->id;
            $player->role = $role;
            $player->main_hero = $hero;
            $player->region = $team->region;
            $player->country = $team->country;
            $player->rating = rand(1500, 2500);
            $player->age = rand(18, 28);
            $player->earnings = rand(1000, 50000);
            $player->biography = "Professional Marvel Rivals player for " . $team->name;
            $player->save();
            
            echo "     + Added {$player->name} ({$role} - {$hero})\n";
            $playerCounter++;
        }
    }
}

// Step 4: Add coaches to all teams
echo "\n4. Adding coaches to all teams...\n";
$coaches = [
    'Marcus "Strategy" Johnson',
    'Sarah "Tactical" Chen',
    'David "Coach" Rodriguez',
    'Emma "Guide" Thompson',
    'Alex "Leader" Kim',
    'Jordan "Mentor" Williams',
    'Casey "Director" Brown',
    'Taylor "Advisor" Davis',
    'Morgan "Instructor" Wilson',
    'Riley "Strategist" Garcia',
    'Avery "Commander" Miller',
    'Phoenix "Coach" Anderson',
    'Sage "Mastermind" Taylor',
    'Dakota "Planner" Moore',
    'River "Tactician" Jackson',
    'Skyler "Guide" White',
    'Rowan "Leader" Harris',
    'Quinn "Director" Martin',
    'Ash "Mentor" Thompson',
    'Blake "Coach" Clark'
];

$coachIndex = 0;
$allTeams = Team::all();

foreach ($allTeams as $team) {
    if (empty($team->coach)) {
        $coachName = $coaches[$coachIndex % count($coaches)];
        $team->coach = $coachName;
        $team->save();
        echo "   Added coach '{$coachName}' to {$team->name}\n";
        $coachIndex++;
    } else {
        echo "   {$team->name} already has coach: {$team->coach}\n";
    }
}

// Step 5: Verify final structure
echo "\n5. Verifying final team structure...\n";
$finalTeams = Team::withCount('players')->get();
$allValid = true;

foreach ($finalTeams as $team) {
    $playerCount = $team->players_count;
    $hasCoach = !empty($team->coach);
    $status = ($playerCount === 6 && $hasCoach) ? "✓ GOOD" : "✗ ISSUE";
    
    echo "   {$team->name}: {$playerCount} players, Coach: " . ($hasCoach ? $team->coach : 'MISSING') . " [{$status}]\n";
    
    if ($playerCount !== 6 || !$hasCoach) {
        $allValid = false;
    }
}

echo "\n========================================\n";
if ($allValid) {
    echo "SUCCESS: All teams now have exactly 6 players and 1 coach!\n";
} else {
    echo "WARNING: Some teams still have issues. Manual review required.\n";
}

echo "\nScript completed.\n";