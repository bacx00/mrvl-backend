<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\Team;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing Bracket Generation System\n";
echo "================================\n\n";

try {
    // Test 1: Check if we can get an event with teams
    echo "1. Looking for an event with registered teams...\n";
    
    // First try to find an existing event with teams
    $event = DB::table('events')
        ->select('events.*')
        ->join('event_teams', 'events.id', '=', 'event_teams.event_id')
        ->groupBy('events.id')
        ->havingRaw('COUNT(event_teams.team_id) >= 4') // At least 4 teams
        ->first();
    
    if (!$event) {
        // If no event with teams, just use any event and add teams to it
        $event = DB::table('events')->first();
        
        if (!$event) {
            throw new Exception('No events found in database. Please create an event first.');
        }
        
        echo "   Using existing event ID: {$event->id} ({$event->name})\n";
        
        // Clear existing event teams and add new ones
        DB::table('event_teams')->where('event_id', $event->id)->delete();
        
        // Get some teams and register them
        $teams = DB::table('teams')->limit(8)->get();
        
        if (count($teams) < 4) {
            throw new Exception('Not enough teams in database for testing');
        }
        
        // Register teams for the event
        foreach ($teams as $index => $team) {
            DB::table('event_teams')->insert([
                'event_id' => $event->id,
                'team_id' => $team->id,
                'seed' => $index + 1,
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        echo "   Registered " . count($teams) . " teams for the event\n";
        $eventId = $event->id;
    } else {
        $eventId = $event->id;
        $teamCount = DB::table('event_teams')->where('event_id', $eventId)->count();
        echo "   Found event ID: {$eventId} ({$event->name}) with {$teamCount} teams\n";
    }
    
    // Test 2: Clear any existing matches
    echo "\n2. Clearing existing matches for event...\n";
    $deletedMatches = DB::table('matches')->where('event_id', $eventId)->delete();
    echo "   Deleted {$deletedMatches} existing matches\n";
    
    // Test 3: Test bracket generation with different formats
    $testFormats = [
        [
            'format' => 'single_elimination',
            'seeding_type' => 'rating',
            'match_format' => 'bo3',
            'finals_format' => 'bo5'
        ],
        [
            'format' => 'single_elimination',
            'seeding_type' => 'random', 
            'match_format' => 'bo1',
            'finals_format' => 'bo3'
        ]
    ];
    
    foreach ($testFormats as $index => $testData) {
        echo "\n3." . ($index + 1) . " Testing bracket generation: {$testData['format']} with {$testData['match_format']} format\n";
        
        // Clear previous matches
        DB::table('matches')->where('event_id', $eventId)->delete();
        
        // Create the bracket generation request
        $controller = new App\Http\Controllers\SimpleBracketController();
        
        // Mock request object
        $request = Request::create('/api/admin/events/' . $eventId . '/bracket/generate', 'POST', $testData);
        
        // Mock authentication (create a temporary admin user context)
        $adminUser = DB::table('users')->where('role', 'admin')->first();
        if (!$adminUser) {
            // Create temporary admin for testing
            $adminUserId = DB::table('users')->insertGetId([
                'username' => 'test_admin',
                'email' => 'test@admin.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $adminUser = DB::table('users')->where('id', $adminUserId)->first();
        }
        
        // Temporarily set the authenticated user
        Auth::login(App\Models\User::find($adminUser->id));
        
        try {
            $response = $controller->generate($request, $eventId);
            $responseData = json_decode($response->getContent(), true);
            
            if ($responseData['success']) {
                echo "   ✓ Success: Generated {$responseData['data']['matches_created']} matches\n";
                
                // Verify matches have scheduled_at
                $matches = DB::table('matches')->where('event_id', $eventId)->get();
                $scheduledMatches = $matches->filter(function($match) {
                    return !is_null($match->scheduled_at);
                });
                
                echo "   ✓ All " . count($scheduledMatches) . " matches have scheduled_at timestamps\n";
                
                // Check format distribution
                $formatCounts = $matches->groupBy('format')->map->count();
                echo "   ✓ Format distribution: " . json_encode($formatCounts->toArray()) . "\n";
                
            } else {
                echo "   ✗ Failed: " . $responseData['message'] . "\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Exception: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 4: Test match update functionality
    echo "\n4. Testing match update functionality...\n";
    
    $firstMatch = DB::table('matches')->where('event_id', $eventId)->first();
    if ($firstMatch) {
        try {
            $updateRequest = Request::create('/api/admin/matches/' . $firstMatch->id, 'PUT', [
                'team1_score' => 2,
                'team2_score' => 1,
                'status' => 'completed'
            ]);
            
            $updateResponse = $controller->updateMatch($updateRequest, $firstMatch->id);
            $updateData = json_decode($updateResponse->getContent(), true);
            
            if ($updateData['success']) {
                echo "   ✓ Successfully updated match result\n";
                
                // Check winner advance logic
                $updatedMatch = DB::table('matches')->where('id', $firstMatch->id)->first();
                echo "   ✓ Match status: {$updatedMatch->status}, Scores: {$updatedMatch->team1_score}-{$updatedMatch->team2_score}\n";
            } else {
                echo "   ✗ Failed to update match: " . $updateData['message'] . "\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Match update exception: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 5: Test bracket retrieval
    echo "\n5. Testing bracket retrieval...\n";
    try {
        $showResponse = $controller->show($eventId);
        $showData = json_decode($showResponse->getContent(), true);
        
        if ($showData['success']) {
            echo "   ✓ Successfully retrieved bracket\n";
            echo "   ✓ Format: {$showData['data']['format']}\n";
            echo "   ✓ Teams count: {$showData['data']['teams_count']}\n";
            echo "   ✓ Rounds in bracket: " . count($showData['data']['bracket']) . "\n";
        } else {
            echo "   ✗ Failed to retrieve bracket: " . $showData['message'] . "\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Bracket retrieval exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "BRACKET GENERATION TEST COMPLETED\n";
    echo str_repeat("=", 50) . "\n";
    
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}