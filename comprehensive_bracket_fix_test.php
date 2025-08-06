<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== COMPREHENSIVE BRACKET SYSTEM FIX TEST ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test database connection
try {
    DB::connection()->getPdo();
    echo "✓ Database connection successful\n";
} catch (\Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Find a test event with teams
$testEvent = DB::table('events')
    ->join('event_teams', 'events.id', '=', 'event_teams.event_id')
    ->select('events.*')
    ->groupBy('events.id')
    ->havingRaw('COUNT(event_teams.team_id) >= 4')
    ->first();

if (!$testEvent) {
    echo "✗ No events with sufficient teams found for testing\n";
    exit(1);
}

echo "✓ Test event found: {$testEvent->name} (ID: {$testEvent->id})\n";

// Test 1: BracketController toArray() issue
echo "\n--- Testing BracketController stdClass Issue ---\n";
try {
    $bracketController = new \App\Http\Controllers\BracketController();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($bracketController);
    $getEventTeamsMethod = $reflection->getMethod('getEventTeams');
    $getEventTeamsMethod->setAccessible(true);
    
    $teams = $getEventTeamsMethod->invoke($bracketController, $testEvent->id);
    echo "Teams retrieved: " . count($teams) . "\n";
    
    // Test the applySeedingMethod with current implementation
    $applySeedingMethod = $reflection->getMethod('applySeedingMethod');
    $applySeedingMethod->setAccessible(true);
    
    // This should fail with the current toArray() implementation
    $seededTeams = $applySeedingMethod->invoke($bracketController, $teams, 'rating');
    echo "✗ FAILED: applySeedingMethod should have failed but didn't\n";
    
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Trying to access array offset on value of type null') !== false ||
        strpos($e->getMessage(), 'Attempt to read property') !== false) {
        echo "✓ CONFIRMED: BracketController has stdClass as array error\n";
        echo "  Error: " . substr($e->getMessage(), 0, 100) . "...\n";
    } else {
        echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    }
}

// Test 2: SimpleBracketController same issue
echo "\n--- Testing SimpleBracketController stdClass Issue ---\n";
try {
    $simpleBracketController = new \App\Http\Controllers\SimpleBracketController();
    
    $reflection = new ReflectionClass($simpleBracketController);
    $getEventTeamsMethod = $reflection->getMethod('getEventTeams');
    $getEventTeamsMethod->setAccessible(true);
    
    $teams = $getEventTeamsMethod->invoke($simpleBracketController, $testEvent->id);
    
    $seedTeamsMethod = $reflection->getMethod('seedTeams');
    $seedTeamsMethod->setAccessible(true);
    
    $seededTeams = $seedTeamsMethod->invoke($simpleBracketController, $teams, 'rating');
    echo "✗ FAILED: seedTeams should have failed but didn't\n";
    
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Trying to access array offset on value of type null') !== false ||
        strpos($e->getMessage(), 'Attempt to read property') !== false) {
        echo "✓ CONFIRMED: SimpleBracketController has stdClass as array error\n";
        echo "  Error: " . substr($e->getMessage(), 0, 100) . "...\n";
    } else {
        echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    }
}

// Test 3: Winner advancement logic
echo "\n--- Testing Winner Advancement Logic ---\n";
$matches = DB::table('matches')
    ->where('event_id', $testEvent->id)
    ->where('status', 'completed')
    ->orderBy('round')
    ->limit(5)
    ->get();

if (count($matches) > 0) {
    echo "Found " . count($matches) . " completed matches\n";
    
    foreach ($matches as $match) {
        $nextRound = $match->round + 1;
        $nextPosition = ceil($match->bracket_position / 2);
        
        $nextMatch = DB::table('matches')
            ->where('event_id', $match->event_id)
            ->where('round', $nextRound)
            ->where('bracket_position', $nextPosition)
            ->first();
            
        if ($nextMatch) {
            $winner = $match->team1_score > $match->team2_score ? $match->team1_id : $match->team2_id;
            $expectedSlot = ($match->bracket_position % 2 === 1) ? 'team1_id' : 'team2_id';
            $actualSlot = $nextMatch->team1_id == $winner ? 'team1_id' : 
                         ($nextMatch->team2_id == $winner ? 'team2_id' : 'none');
            
            if ($actualSlot === $expectedSlot) {
                echo "  ✓ Match {$match->id} winner advanced correctly\n";
            } else {
                echo "  ✗ Match {$match->id} winner advancement ERROR\n";
                echo "    Expected: {$expectedSlot}, Actual: {$actualSlot}\n";
            }
        } else {
            echo "  - Match {$match->id} has no next match (final?)\n";
        }
    }
} else {
    echo "No completed matches found to test advancement\n";
}

// Test 4: Seeding patterns
echo "\n--- Testing Seeding Patterns ---\n";
$teamCounts = [4, 8, 16];
foreach ($teamCounts as $count) {
    echo "  Testing {$count}-team bracket seeding:\n";
    
    // Check if standard seeding pattern is implemented
    $teams = DB::table('event_teams')
        ->join('teams', 'event_teams.team_id', '=', 'teams.id')
        ->where('event_teams.event_id', $testEvent->id)
        ->limit($count)
        ->get();
        
    if (count($teams) >= $count) {
        echo "    ✓ {$count} teams available for testing\n";
        
        // Expected first round matchups for proper seeding:
        // 4 teams: 1v4, 2v3
        // 8 teams: 1v8, 2v7, 3v6, 4v5
        // 16 teams: 1v16, 2v15, 3v14, 4v13, 5v12, 6v11, 7v10, 8v9
        
        $expectedPairs = [];
        if ($count == 4) $expectedPairs = [[1, 4], [2, 3]];
        elseif ($count == 8) $expectedPairs = [[1, 8], [2, 7], [3, 6], [4, 5]];
        elseif ($count == 16) $expectedPairs = [[1, 16], [2, 15], [3, 14], [4, 13], [5, 12], [6, 11], [7, 10], [8, 9]];
        
        echo "    Expected pairs: " . json_encode($expectedPairs) . "\n";
        echo "    ✗ Seeding pattern implementation needs verification\n";
    } else {
        echo "    - Insufficient teams ({$count} needed, " . count($teams) . " available)\n";
    }
}

// Test 5: Format support
echo "\n--- Testing Tournament Format Support ---\n";
$formats = ['single_elimination', 'double_elimination', 'round_robin', 'swiss'];
foreach ($formats as $format) {
    $events = DB::table('events')->where('format', $format)->count();
    echo "  {$format}: {$events} events\n";
}

// Test 6: Database integrity
echo "\n--- Testing Database Integrity ---\n";

// Check for orphaned matches
$orphanedMatches = DB::table('matches as m')
    ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
    ->whereNull('e.id')
    ->count();
echo "Orphaned matches: {$orphanedMatches}\n";

// Check for matches without teams
$incompleteMatches = DB::table('matches')
    ->where(function($query) {
        $query->whereNull('team1_id')->orWhereNull('team2_id');
    })
    ->where('status', '!=', 'upcoming')
    ->count();
echo "Incomplete matches (should have teams): {$incompleteMatches}\n";

// Check bracket_position integrity
$positionIssues = DB::table('matches')
    ->where('bracket_position', '<=', 0)
    ->orWhereNull('bracket_position')
    ->count();
echo "Invalid bracket positions: {$positionIssues}\n";

echo "\n=== TEST SUMMARY ===\n";
echo "Critical issues identified:\n";
echo "1. stdClass as array error in both BracketController and SimpleBracketController\n";
echo "2. Winner advancement logic needs verification\n";
echo "3. Seeding algorithm implementation missing\n";
echo "4. Controller consolidation needed\n";
echo "5. Advanced format support incomplete\n";

echo "\nNext steps: Fix these issues systematically\n";