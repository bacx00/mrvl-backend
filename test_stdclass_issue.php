<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TESTING STDCLASS ARRAY ISSUE ===\n";

// Get some teams to test with
$teams = DB::table('event_teams as et')
    ->leftJoin('teams as t', 'et.team_id', '=', 't.id')  
    ->where('et.event_id', 17)
    ->select(['t.id', 't.name', 't.short_name', 't.logo', 't.rating', 'et.seed'])
    ->orderBy('et.seed')
    ->get();

echo "Teams as Collection objects:\n";
foreach ($teams as $team) {
    echo "- {$team->name} (rating: {$team->rating})\n";
}

echo "\nConverting to array with toArray():\n";
$teamsArray = $teams->toArray();

echo "Teams as arrays:\n";
foreach ($teamsArray as $team) {
    if (is_array($team)) {
        echo "- {$team['name']} (rating: {$team['rating']})\n";
    } else {
        echo "- Still an object: {$team->name}\n";
    }
}

echo "\nTesting seeding function with array data:\n";
try {
    // This simulates what happens in the BracketController
    usort($teamsArray, function($a, $b) {
        // This line will fail if $a and $b are arrays instead of objects
        return $b->rating <=> $a->rating;  // Trying to access ->rating on array
    });
    echo "✓ Seeding worked (shouldn't happen)\n";
} catch (\Exception $e) {
    echo "✗ CONFIRMED ISSUE: " . $e->getMessage() . "\n";
}

echo "\nTesting correct approach without toArray():\n";
try {
    $teamsCorrect = $teams->toArray(); // Convert to array but handle objects properly
    
    // Convert stdClass objects to arrays properly
    $teamsArray = [];
    foreach ($teams as $team) {
        $teamsArray[] = (array) $team;
    }
    
    usort($teamsArray, function($a, $b) {
        $ratingA = $a['rating'] ?? 1000;
        $ratingB = $b['rating'] ?? 1000;
        return $ratingB <=> $ratingA;  // Array access
    });
    echo "✓ Fixed approach works\n";
} catch (\Exception $e) {
    echo "✗ Fixed approach failed: " . $e->getMessage() . "\n";
}

echo "\nBest approach - keep as objects:\n";
try {
    $teamsObjects = $teams->all(); // Keep as objects
    
    usort($teamsObjects, function($a, $b) {
        $ratingA = $a->rating ?? 1000;
        $ratingB = $b->rating ?? 1000;
        return $ratingB <=> $ratingA;  // Object access
    });
    echo "✓ Object approach works perfectly\n";
    
    foreach ($teamsObjects as $team) {
        echo "- {$team->name} (rating: {$team->rating})\n";
    }
} catch (\Exception $e) {
    echo "✗ Object approach failed: " . $e->getMessage() . "\n";
}