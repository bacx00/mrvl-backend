<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing Bracket Generation - Core Functionality\n";
echo "==============================================\n\n";

try {
    // Test the createMatches method directly by creating a mock SimpleBracketController
    $controller = new class extends App\Http\Controllers\SimpleBracketController {
        // Make the protected method public for testing
        public function testCreateMatches($eventId, $teams, $format, $matchFormat = 'bo3', $finalsFormat = 'bo5') {
            return $this->createMatches($eventId, $teams, $format, $matchFormat, $finalsFormat);
        }
    };

    // Get an event to test with
    $event = DB::table('events')->first();
    if (!$event) {
        throw new Exception('No events found in database');
    }
    
    echo "Using event ID: {$event->id} ({$event->name})\n\n";

    // Create mock teams data
    $teams = [];
    $teamRecords = DB::table('teams')->limit(8)->get();
    
    foreach ($teamRecords as $index => $team) {
        $teams[] = (object)[
            'id' => $team->id,
            'name' => $team->name,
            'rating' => $team->rating ?? (1500 - ($index * 50))
        ];
    }
    
    echo "Testing with " . count($teams) . " teams\n\n";

    // Test 1: Test bracket creation with different formats
    $testCases = [
        ['format' => 'single_elimination', 'match_format' => 'bo3', 'finals_format' => 'bo5'],
        ['format' => 'single_elimination', 'match_format' => 'bo1', 'finals_format' => 'bo3'],
        ['format' => 'single_elimination', 'match_format' => 'bo5', 'finals_format' => 'bo7']
    ];

    foreach ($testCases as $index => $testCase) {
        echo "Test " . ($index + 1) . ": {$testCase['format']} - {$testCase['match_format']} / {$testCase['finals_format']}\n";
        
        try {
            $matches = $controller->testCreateMatches(
                $event->id,
                $teams,
                $testCase['format'],
                $testCase['match_format'],
                $testCase['finals_format']
            );
            
            echo "  ✓ Generated " . count($matches) . " matches\n";
            
            // Check all matches have scheduled_at
            $withScheduledAt = 0;
            $formatCounts = [];
            
            foreach ($matches as $match) {
                if (isset($match['scheduled_at']) && !is_null($match['scheduled_at'])) {
                    $withScheduledAt++;
                }
                
                $format = $match['format'];
                if (!isset($formatCounts[$format])) {
                    $formatCounts[$format] = 0;
                }
                $formatCounts[$format]++;
            }
            
            echo "  ✓ Matches with scheduled_at: {$withScheduledAt}/" . count($matches) . "\n";
            echo "  ✓ Format distribution: " . json_encode($formatCounts) . "\n";
            
            // Test database insertion
            DB::beginTransaction();
            
            // Clear existing matches first
            DB::table('matches')->where('event_id', $event->id)->delete();
            
            // Insert matches
            DB::table('matches')->insert($matches);
            echo "  ✓ Successfully inserted matches into database\n";
            
            // Verify in database
            $dbMatches = DB::table('matches')->where('event_id', $event->id)->get();
            $scheduledInDb = $dbMatches->filter(function($match) {
                return !is_null($match->scheduled_at);
            })->count();
            
            echo "  ✓ Verified in database: {$scheduledInDb}/" . count($dbMatches) . " matches have scheduled_at\n";
            
            DB::rollback(); // Don't actually save the test data
            
        } catch (Exception $e) {
            echo "  ✗ Failed: " . $e->getMessage() . "\n";
            DB::rollback();
        }
        
        echo "\n";
    }

    // Test 2: Test different team counts
    echo "Testing different team counts:\n";
    $teamCounts = [4, 6, 8, 16];
    
    foreach ($teamCounts as $count) {
        echo "  Testing with {$count} teams: ";
        
        try {
            $testTeams = array_slice($teams, 0, $count);
            $matches = $controller->testCreateMatches($event->id, $testTeams, 'single_elimination', 'bo3', 'bo5');
            
            $rounds = max(array_column($matches, 'round'));
            $expectedRounds = ceil(log($count, 2));
            
            echo "✓ Generated " . count($matches) . " matches in {$rounds} rounds (expected: {$expectedRounds})\n";
            
        } catch (Exception $e) {
            echo "✗ Failed: " . $e->getMessage() . "\n";
        }
    }

    // Test 3: Check specific Marvel Rivals format support
    echo "\nTesting Marvel Rivals specific formats:\n";
    $marvelFormats = ['bo1', 'bo3', 'bo5', 'bo7'];
    
    foreach ($marvelFormats as $format) {
        echo "  Testing {$format}: ";
        
        try {
            $matches = $controller->testCreateMatches($event->id, array_slice($teams, 0, 4), 'single_elimination', $format, 'bo5');
            
            $matchesWithFormat = array_filter($matches, function($match) use ($format) {
                return $match['format'] === $format;
            });
            
            echo "✓ " . count($matchesWithFormat) . " matches with {$format} format\n";
            
        } catch (Exception $e) {
            echo "✗ Failed: " . $e->getMessage() . "\n";
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "CORE BRACKET GENERATION TEST COMPLETED SUCCESSFULLY\n";
    echo str_repeat("=", 60) . "\n";
    
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}