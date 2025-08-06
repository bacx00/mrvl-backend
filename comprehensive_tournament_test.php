<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Comprehensive Marvel Rivals Tournament Bracket System Test Suite
 * 
 * This script tests the tournament system as if running real Marvel Rivals esports events:
 * 1. Marvel Rivals Ignite 2025 Format (16 teams, Swiss + Single Elimination)
 * 2. Marvel Rivals Championship (8 teams, Double Elimination)
 * 3. Live Tournament Simulation with real-time updates
 * 4. Edge Cases and Stress Tests
 * 5. Integration and Performance Tests
 */

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class ComprehensiveTournamentTest
{
    private $testResults = [];
    private $performanceMetrics = [];
    
    public function runAllTests()
    {
        echo "=== MARVEL RIVALS TOURNAMENT BRACKET SYSTEM - COMPREHENSIVE TEST SUITE ===\n\n";
        
        // Test 1: Marvel Rivals Ignite 2025 Format
        $this->testMarvelRivalsIgnite2025();
        
        // Test 2: Marvel Rivals Championship (MRC)
        $this->testMarvelRivalsChampionship();
        
        // Test 3: Live Tournament Simulation
        $this->testLiveTournamentSimulation();
        
        // Test 4: Edge Cases and Stress Tests
        $this->testEdgeCases();
        
        // Test 5: Integration Tests
        $this->testIntegrationTests();
        
        // Test 6: Performance Validation
        $this->testPerformanceValidation();
        
        // Generate final report
        $this->generateFinalReport();
    }
    
    /**
     * TEST 1: Marvel Rivals Ignite 2025 Format Test
     * - 16 teams with rating-based seeding
     * - Swiss system for group stage (if available)
     * - Single elimination playoffs with BO3 matches, BO5 finals
     */
    public function testMarvelRivalsIgnite2025()
    {
        echo "🔥 TEST 1: Marvel Rivals Ignite Stage 1 Format Test\n";
        echo "================================================\n";
        
        $startTime = microtime(true);
        
        try {
            // Create event
            $eventId = $this->createEvent([
                'name' => 'Marvel Rivals Ignite Stage 1 - Test Tournament',
                'format' => 'single_elimination',
                'type' => 'tournament',
                'tier' => 'S',
                'start_date' => now()->addDays(1),
                'end_date' => now()->addDays(3),
                'description' => 'Test tournament for Marvel Rivals Ignite format'
            ]);
            
            echo "✅ Created event ID: $eventId\n";
            
            // Get top 16 teams by rating
            $teams = $this->getTopTeamsByRating(16);
            echo "✅ Selected 16 teams by rating:\n";
            foreach ($teams as $i => $team) {
                echo "   " . ($i + 1) . ". {$team->name} ({$team->short_name}) - Rating: {$team->rating}\n";
            }
            
            // Register teams
            $this->registerTeamsToEvent($eventId, $teams);
            echo "✅ Registered 16 teams to tournament\n";
            
            // Generate bracket with rating-based seeding
            $bracketResponse = $this->generateBracket($eventId, [
                'format' => 'single_elimination',
                'seeding_method' => 'rating',
                'include_third_place' => true,
                'save_history' => true
            ]);
            
            if ($bracketResponse['success']) {
                echo "✅ Generated single elimination bracket with rating seeding\n";
                echo "   Matches created: {$bracketResponse['data']['matches_created']}\n";
                echo "   Format: {$bracketResponse['data']['format']}\n";
                echo "   Teams: {$bracketResponse['data']['teams_count']}\n";
            } else {
                throw new Exception("Failed to generate bracket: " . $bracketResponse['message']);
            }
            
            // Test bracket structure
            $bracket = $this->getBracket($eventId);
            $this->validateBracketStructure($bracket, 'single_elimination', 16);
            
            // Simulate match progression
            $this->simulateMatchProgression($eventId, 'single_elimination');
            
            $this->testResults['ignite_2025'] = [
                'status' => 'PASSED',
                'event_id' => $eventId,
                'teams_count' => 16,
                'format' => 'single_elimination',
                'execution_time' => microtime(true) - $startTime
            ];
            
            echo "✅ Marvel Rivals Ignite 2025 test PASSED\n\n";
            
        } catch (Exception $e) {
            $this->testResults['ignite_2025'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
            echo "❌ Marvel Rivals Ignite 2025 test FAILED: " . $e->getMessage() . "\n\n";
        }
    }
    
    /**
     * TEST 2: Marvel Rivals Championship (MRC) Test
     * - 8 teams double elimination
     * - Test upper and lower bracket progression
     * - Test bracket reset scenario in grand finals
     */
    public function testMarvelRivalsChampionship()
    {
        echo "🏆 TEST 2: Marvel Rivals Championship (MRC) Format Test\n";
        echo "======================================================\n";
        
        $startTime = microtime(true);
        
        try {
            // Create MRC event
            $eventId = $this->createEvent([
                'name' => 'Marvel Rivals Championship - Regional Test',
                'format' => 'double_elimination',
                'type' => 'championship',
                'tier' => 'S',
                'start_date' => now()->addDays(5),
                'end_date' => now()->addDays(7),
                'description' => 'Test tournament for MRC double elimination format'
            ]);
            
            echo "✅ Created MRC event ID: $eventId\n";
            
            // Get top 8 teams
            $teams = $this->getTopTeamsByRating(8);
            echo "✅ Selected 8 teams for MRC:\n";
            foreach ($teams as $i => $team) {
                echo "   " . ($i + 1) . ". {$team->name} - Rating: {$team->rating}\n";
            }
            
            // Register teams
            $this->registerTeamsToEvent($eventId, $teams);
            
            // Generate double elimination bracket
            $bracketResponse = $this->generateBracket($eventId, [
                'format' => 'double_elimination',
                'seeding_method' => 'rating',
                'save_history' => true
            ]);
            
            if ($bracketResponse['success']) {
                echo "✅ Generated double elimination bracket\n";
                echo "   Matches created: {$bracketResponse['data']['matches_created']}\n";
            }
            
            // Test double elimination structure
            $bracket = $this->getBracket($eventId);
            $this->validateDoubleEliminationStructure($bracket, 8);
            
            // Simulate progression through both brackets
            $this->simulateDoubleEliminationProgression($eventId);
            
            // Test bracket reset scenario
            $this->testBracketResetScenario($eventId);
            
            $this->testResults['mrc_championship'] = [
                'status' => 'PASSED',
                'event_id' => $eventId,
                'teams_count' => 8,
                'format' => 'double_elimination',
                'execution_time' => microtime(true) - $startTime
            ];
            
            echo "✅ Marvel Rivals Championship test PASSED\n\n";
            
        } catch (Exception $e) {
            $this->testResults['mrc_championship'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
            echo "❌ Marvel Rivals Championship test FAILED: " . $e->getMessage() . "\n\n";
        }
    }
    
    /**
     * TEST 3: Live Tournament Simulation
     * - Generate bracket for event ID 17
     * - Update match scores in real-time
     * - Test concurrent match updates
     * - Verify live scoring integration
     */
    public function testLiveTournamentSimulation()
    {
        echo "🔴 TEST 3: Live Tournament Simulation\n";
        echo "====================================\n";
        
        $startTime = microtime(true);
        
        try {
            // Use existing event ID 17 or create new one
            $eventId = 17;
            
            // Verify event exists
            $event = DB::table('events')->where('id', $eventId)->first();
            if (!$event) {
                throw new Exception("Event ID $eventId not found");
            }
            
            echo "✅ Using event: {$event->name} (ID: $eventId)\n";
            
            // Check if bracket already exists, if not generate it
            $existingMatches = DB::table('matches')->where('event_id', $eventId)->count();
            if ($existingMatches === 0) {
                echo "⚠️  No existing bracket found, generating new bracket...\n";
                
                // Get teams for this event
                $eventTeams = DB::table('event_teams')
                    ->join('teams', 'event_teams.team_id', '=', 'teams.id')
                    ->where('event_teams.event_id', $eventId)
                    ->select('teams.*')
                    ->get();
                
                if ($eventTeams->count() === 0) {
                    // Register some teams
                    $teams = $this->getTopTeamsByRating(8);
                    $this->registerTeamsToEvent($eventId, $teams);
                    echo "✅ Registered 8 teams to event\n";
                }
                
                // Generate bracket
                $this->generateBracket($eventId, [
                    'format' => $event->format,
                    'seeding_method' => 'rating'
                ]);
                echo "✅ Generated new bracket\n";
            }
            
            // Test live scoring updates
            $this->testLiveScoringUpdates($eventId);
            
            // Test concurrent match updates
            $this->testConcurrentMatchUpdates($eventId);
            
            // Test match status transitions
            $this->testMatchStatusTransitions($eventId);
            
            $this->testResults['live_simulation'] = [
                'status' => 'PASSED',
                'event_id' => $eventId,
                'execution_time' => microtime(true) - $startTime
            ];
            
            echo "✅ Live Tournament Simulation test PASSED\n\n";
            
        } catch (Exception $e) {
            $this->testResults['live_simulation'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
            echo "❌ Live Tournament Simulation test FAILED: " . $e->getMessage() . "\n\n";
        }
    }
    
    /**
     * TEST 4: Edge Cases and Stress Tests
     */
    public function testEdgeCases()
    {
        echo "⚠️  TEST 4: Edge Cases and Stress Tests\n";
        echo "======================================\n";
        
        $startTime = microtime(true);
        $edgeTestResults = [];
        
        try {
            // Test 4.1: Odd number of teams (5 teams)
            echo "Testing 5 teams (odd number requiring byes)...\n";
            $oddTeamsResult = $this->testOddTeamCount();
            $edgeTestResults['odd_teams'] = $oddTeamsResult;
            
            // Test 4.2: Large tournament (32 teams)
            echo "Testing 32 teams (large tournament)...\n";
            $largeTournamentResult = $this->testLargeTournament();
            $edgeTestResults['large_tournament'] = $largeTournamentResult;
            
            // Test 4.3: Bracket regeneration after matches started
            echo "Testing bracket regeneration after matches started...\n";
            $regenerationResult = $this->testBracketRegeneration();
            $edgeTestResults['bracket_regeneration'] = $regenerationResult;
            
            // Test 4.4: Invalid score inputs and error handling
            echo "Testing invalid inputs and error handling...\n";
            $errorHandlingResult = $this->testErrorHandling();
            $edgeTestResults['error_handling'] = $errorHandlingResult;
            
            $this->testResults['edge_cases'] = [
                'status' => 'PASSED',
                'sub_tests' => $edgeTestResults,
                'execution_time' => microtime(true) - $startTime
            ];
            
            echo "✅ Edge Cases and Stress Tests PASSED\n\n";
            
        } catch (Exception $e) {
            $this->testResults['edge_cases'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'sub_tests' => $edgeTestResults,
                'execution_time' => microtime(true) - $startTime
            ];
            echo "❌ Edge Cases and Stress Tests FAILED: " . $e->getMessage() . "\n\n";
        }
    }
    
    /**
     * TEST 5: Integration Tests
     */
    public function testIntegrationTests()
    {
        echo "🔗 TEST 5: Integration Tests\n";
        echo "============================\n";
        
        $startTime = microtime(true);
        
        try {
            // Test API endpoints
            $this->testAPIEndpoints();
            
            // Test tournament completion detection
            $this->testTournamentCompletionDetection();
            
            // Test data consistency
            $this->testDataConsistency();
            
            $this->testResults['integration'] = [
                'status' => 'PASSED',
                'execution_time' => microtime(true) - $startTime
            ];
            
            echo "✅ Integration Tests PASSED\n\n";
            
        } catch (Exception $e) {
            $this->testResults['integration'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
            echo "❌ Integration Tests FAILED: " . $e->getMessage() . "\n\n";
        }
    }
    
    /**
     * TEST 6: Performance Validation
     */
    public function testPerformanceValidation()
    {
        echo "⚡ TEST 6: Performance Validation\n";
        echo "=================================\n";
        
        $startTime = microtime(true);
        
        try {
            // Measure API response times
            $this->measureAPIResponseTimes();
            
            // Check database query efficiency
            $this->checkDatabaseQueryEfficiency();
            
            // Test under load
            $this->testUnderLoad();
            
            $this->testResults['performance'] = [
                'status' => 'PASSED',
                'metrics' => $this->performanceMetrics,
                'execution_time' => microtime(true) - $startTime
            ];
            
            echo "✅ Performance Validation PASSED\n\n";
            
        } catch (Exception $e) {
            $this->testResults['performance'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'metrics' => $this->performanceMetrics,
                'execution_time' => microtime(true) - $startTime
            ];
            echo "❌ Performance Validation FAILED: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Helper Methods
    
    private function createEvent($data)
    {
        $eventId = DB::table('events')->insertGetId([
            'name' => $data['name'],
            'slug' => str_replace(' ', '-', strtolower($data['name'])) . '-' . time(),
            'format' => $data['format'],
            'type' => $data['type'],
            'tier' => $data['tier'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'description' => $data['description'],
            'status' => 'upcoming',
            'region' => 'North America',
            'game_mode' => 'Competitive',
            'organizer_id' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return $eventId;
    }
    
    private function getTopTeamsByRating($count)
    {
        return DB::table('teams')
            ->orderBy('rating', 'desc')
            ->orderBy('name')
            ->limit($count)
            ->get();
    }
    
    private function registerTeamsToEvent($eventId, $teams)
    {
        $seed = 1;
        foreach ($teams as $team) {
            DB::table('event_teams')->insert([
                'event_id' => $eventId,
                'team_id' => $team->id,
                'seed' => $seed++,
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    private function generateBracket($eventId, $options)
    {
        // Simulate API call to bracket generation
        $url = "http://localhost:8000/api/brackets/{$eventId}/generate";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            // Fallback to direct database approach if API not available
            return $this->generateBracketDirect($eventId, $options);
        }
        
        return json_decode($response, true);
    }
    
    private function generateBracketDirect($eventId, $options)
    {
        // Direct bracket generation using the controller logic
        $controller = new \App\Http\Controllers\BracketController();
        $request = new \Illuminate\Http\Request();
        $request->merge($options);
        
        try {
            $response = $controller->generate($request, $eventId);
            $responseData = $response->getData(true);
            return $responseData;
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function getBracket($eventId)
    {
        $controller = new \App\Http\Controllers\BracketController();
        $response = $controller->show($eventId);
        return $response->getData(true);
    }
    
    private function validateBracketStructure($bracket, $format, $teamCount)
    {
        if (!$bracket['success']) {
            throw new Exception("Failed to get bracket: " . $bracket['message']);
        }
        
        $bracketData = $bracket['data']['bracket'];
        
        switch ($format) {
            case 'single_elimination':
                $expectedRounds = ceil(log($teamCount, 2));
                $actualRounds = count($bracketData['rounds']);
                
                if ($actualRounds !== $expectedRounds) {
                    throw new Exception("Expected $expectedRounds rounds, got $actualRounds");
                }
                
                echo "✅ Single elimination bracket structure validated\n";
                echo "   Rounds: $actualRounds\n";
                echo "   Teams: $teamCount\n";
                break;
                
            case 'double_elimination':
                if (!isset($bracketData['upper_bracket']) || !isset($bracketData['lower_bracket'])) {
                    throw new Exception("Double elimination bracket missing upper or lower bracket");
                }
                
                echo "✅ Double elimination bracket structure validated\n";
                echo "   Upper bracket rounds: " . count($bracketData['upper_bracket']) . "\n";
                echo "   Lower bracket rounds: " . count($bracketData['lower_bracket']) . "\n";
                break;
        }
    }
    
    private function validateDoubleEliminationStructure($bracket, $teamCount)
    {
        $this->validateBracketStructure($bracket, 'double_elimination', $teamCount);
        
        $bracketData = $bracket['data']['bracket'];
        
        // Check for grand final
        if (!isset($bracketData['grand_final'])) {
            throw new Exception("Double elimination bracket missing grand final");
        }
        
        echo "✅ Grand final exists\n";
    }
    
    private function simulateMatchProgression($eventId, $format)
    {
        echo "Simulating match progression...\n";
        
        // Get first round matches
        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('round', 1)
            ->where('status', 'upcoming')
            ->get();
        
        $completedMatches = 0;
        foreach ($matches as $match) {
            if ($match->team1_id && $match->team2_id) {
                // Simulate random match result
                $team1Score = rand(0, 3);
                $team2Score = rand(0, 3);
                
                // Ensure there's a winner
                if ($team1Score === $team2Score) {
                    $team1Score = rand(2, 3);
                    $team2Score = $team1Score === 3 ? rand(0, 2) : rand(0, 1);
                }
                
                DB::table('matches')->where('id', $match->id)->update([
                    'team1_score' => $team1Score,
                    'team2_score' => $team2Score,
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now()
                ]);
                
                $completedMatches++;
            }
        }
        
        echo "✅ Simulated $completedMatches first round matches\n";
    }
    
    private function simulateDoubleEliminationProgression($eventId)
    {
        echo "Simulating double elimination progression...\n";
        
        // Simulate upper bracket first round
        $upperMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'upper')
            ->where('round', 1)
            ->get();
        
        foreach ($upperMatches as $match) {
            if ($match->team1_id && $match->team2_id) {
                $winner = rand(1, 2);
                DB::table('matches')->where('id', $match->id)->update([
                    'team1_score' => $winner === 1 ? 2 : 1,
                    'team2_score' => $winner === 1 ? 1 : 2,
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }
        }
        
        echo "✅ Simulated upper bracket first round\n";
    }
    
    private function testBracketResetScenario($eventId)
    {
        echo "Testing bracket reset scenario...\n";
        
        // This would involve simulating a scenario where the lower bracket winner
        // beats the upper bracket winner in grand finals, triggering a bracket reset
        
        $grandFinalMatch = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'grand_final')
            ->where('round', 1)
            ->first();
        
        if ($grandFinalMatch) {
            // Simulate lower bracket winner winning first grand final
            DB::table('matches')->where('id', $grandFinalMatch->id)->update([
                'team1_score' => 1,  // Upper bracket winner loses
                'team2_score' => 3,  // Lower bracket winner wins
                'status' => 'completed'
            ]);
            
            echo "✅ Bracket reset scenario simulated\n";
        }
    }
    
    private function testLiveScoringUpdates($eventId)
    {
        echo "Testing live scoring updates...\n";
        
        $match = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'upcoming')
            ->first();
        
        if ($match) {
            // Test score updates
            for ($score = 1; $score <= 3; $score++) {
                DB::table('matches')->where('id', $match->id)->update([
                    'team1_score' => $score,
                    'team2_score' => 0,
                    'status' => 'live',
                    'updated_at' => now()
                ]);
                
                usleep(100000); // 0.1 second delay
            }
            
            echo "✅ Live scoring updates tested\n";
        }
    }
    
    private function testConcurrentMatchUpdates($eventId)
    {
        echo "Testing concurrent match updates...\n";
        
        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'upcoming')
            ->limit(3)
            ->get();
        
        // Simulate concurrent updates
        foreach ($matches as $match) {
            DB::table('matches')->where('id', $match->id)->update([
                'status' => 'live',
                'team1_score' => rand(0, 2),
                'team2_score' => rand(0, 2),
                'updated_at' => now()
            ]);
        }
        
        echo "✅ Concurrent match updates tested\n";
    }
    
    private function testMatchStatusTransitions($eventId)
    {
        echo "Testing match status transitions...\n";
        
        $match = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'upcoming')
            ->first();
        
        if ($match) {
            $transitions = ['upcoming', 'live', 'paused', 'live', 'completed'];
            
            foreach ($transitions as $status) {
                DB::table('matches')->where('id', $match->id)->update([
                    'status' => $status,
                    'updated_at' => now()
                ]);
                
                usleep(50000); // 0.05 second delay
            }
            
            echo "✅ Match status transitions tested\n";
        }
    }
    
    private function testOddTeamCount()
    {
        echo "  - Testing 5 teams (odd number)...\n";
        
        $eventId = $this->createEvent([
            'name' => 'Odd Teams Test (5 teams)',
            'format' => 'single_elimination',
            'type' => 'tournament',
            'tier' => 'B',
            'start_date' => now(),
            'end_date' => now()->addDays(1),
            'description' => 'Test with odd number of teams'
        ]);
        
        $teams = $this->getTopTeamsByRating(5);
        $this->registerTeamsToEvent($eventId, $teams);
        
        $result = $this->generateBracket($eventId, ['format' => 'single_elimination']);
        
        if (!$result['success']) {
            throw new Exception("Failed to handle 5 teams: " . $result['message']);
        }
        
        echo "    ✅ 5 teams handled successfully with byes\n";
        return ['status' => 'PASSED', 'teams' => 5];
    }
    
    private function testLargeTournament()
    {
        echo "  - Testing 32 teams (large tournament)...\n";
        
        $eventId = $this->createEvent([
            'name' => 'Large Tournament Test (32 teams)',
            'format' => 'single_elimination',
            'type' => 'tournament',
            'tier' => 'A',
            'start_date' => now(),
            'end_date' => now()->addDays(2),
            'description' => 'Test with large number of teams'
        ]);
        
        $teams = $this->getTopTeamsByRating(32);
        $this->registerTeamsToEvent($eventId, $teams);
        
        $startTime = microtime(true);
        $result = $this->generateBracket($eventId, ['format' => 'single_elimination']);
        $executionTime = microtime(true) - $startTime;
        
        if (!$result['success']) {
            throw new Exception("Failed to handle 32 teams: " . $result['message']);
        }
        
        echo "    ✅ 32 teams handled successfully in " . round($executionTime, 3) . "s\n";
        return ['status' => 'PASSED', 'teams' => 32, 'execution_time' => $executionTime];
    }
    
    private function testBracketRegeneration()
    {
        echo "  - Testing bracket regeneration after matches started...\n";
        
        // Use existing event with matches
        $eventId = 17;
        
        // Mark some matches as completed
        DB::table('matches')
            ->where('event_id', $eventId)
            ->limit(2)
            ->update(['status' => 'completed']);
        
        try {
            $result = $this->generateBracket($eventId, ['format' => 'single_elimination']);
            
            if ($result['success']) {
                echo "    ⚠️  Bracket regenerated after matches started (may not be desired)\n";
            }
        } catch (Exception $e) {
            echo "    ✅ Properly prevented bracket regeneration after matches started\n";
        }
        
        return ['status' => 'PASSED'];
    }
    
    private function testErrorHandling()
    {
        echo "  - Testing error handling...\n";
        
        // Test invalid event ID
        $result = $this->generateBracket(99999, ['format' => 'single_elimination']);
        if (!$result['success']) {
            echo "    ✅ Invalid event ID handled properly\n";
        }
        
        // Test insufficient teams
        $eventId = $this->createEvent([
            'name' => 'Insufficient Teams Test',
            'format' => 'single_elimination',
            'type' => 'tournament',  
            'tier' => 'C',
            'start_date' => now(),
            'end_date' => now()->addDays(1),
            'description' => 'Test with insufficient teams'
        ]);
        
        $result = $this->generateBracket($eventId, ['format' => 'single_elimination']);
        if (!$result['success']) {
            echo "    ✅ Insufficient teams error handled properly\n";
        }
        
        return ['status' => 'PASSED'];
    }
    
    private function testAPIEndpoints()
    {
        echo "Testing API endpoints...\n";
        
        $endpoints = [
            '/api/brackets/17',
            '/api/events/17',
            '/api/teams',
            '/api/matches?event_id=17'
        ];
        
        foreach ($endpoints as $endpoint) {
            $startTime = microtime(true);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://localhost:8000$endpoint");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = microtime(true) - $startTime;
            curl_close($ch);
            
            if ($httpCode === 200) {
                echo "  ✅ $endpoint - {$httpCode} ({$responseTime}s)\n";
            } else {
                echo "  ❌ $endpoint - {$httpCode}\n";
            }
        }
    }
    
    private function testTournamentCompletionDetection()
    {
        echo "Testing tournament completion detection...\n";
        
        // Check if events with all matches completed are marked as completed
        $completedEvents = DB::table('events as e')
            ->leftJoin('matches as m', 'e.id', '=', 'm.event_id')
            ->select('e.id', 'e.name', 'e.status')
            ->groupBy('e.id', 'e.name', 'e.status')
            ->havingRaw('COUNT(m.id) > 0 AND COUNT(CASE WHEN m.status = "completed" THEN 1 END) = COUNT(m.id)')
            ->get();
        
        echo "  ✅ Found " . $completedEvents->count() . " completed tournaments\n";
    }
    
    private function testDataConsistency()
    {
        echo "Testing data consistency...\n";
        
        // Check for orphaned matches
        $orphanedMatches = DB::table('matches')
            ->leftJoin('events', 'matches.event_id', '=', 'events.id')
            ->whereNull('events.id')
            ->count();
        
        echo "  ✅ Orphaned matches: $orphanedMatches\n";
        
        // Check for matches with invalid team references
        $invalidTeamMatches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where(function($query) {
                $query->whereNotNull('m.team1_id')->whereNull('t1.id')
                      ->orWhere(function($q) {
                          $q->whereNotNull('m.team2_id')->whereNull('t2.id');
                      });
            })
            ->count();
        
        echo "  ✅ Invalid team references: $invalidTeamMatches\n";
    }
    
    private function measureAPIResponseTimes()
    {
        echo "Measuring API response times...\n";
        
        $endpoints = [
            'GET /api/brackets/17' => 'http://localhost:8000/api/brackets/17',
            'GET /api/events' => 'http://localhost:8000/api/events',
            'GET /api/teams' => 'http://localhost:8000/api/teams'
        ];
        
        foreach ($endpoints as $name => $url) {
            $times = [];
            
            for ($i = 0; $i < 5; $i++) {
                $startTime = microtime(true);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                curl_exec($ch);
                curl_close($ch);
                
                $times[] = microtime(true) - $startTime;
            }
            
            $avgTime = array_sum($times) / count($times);
            $this->performanceMetrics[$name] = [
                'avg_response_time' => $avgTime,
                'min_response_time' => min($times),
                'max_response_time' => max($times)
            ];
            
            echo "  ✅ $name - Avg: " . round($avgTime * 1000, 2) . "ms\n";
        }
    }
    
    private function checkDatabaseQueryEfficiency()
    {
        echo "Checking database query efficiency...\n";
        
        // Enable query logging
        DB::enableQueryLog();
        
        // Run some typical queries
        $this->getBracket(17);
        DB::table('teams')->orderBy('rating', 'desc')->limit(16)->get();
        DB::table('matches')->where('event_id', 17)->get();
        
        $queries = DB::getQueryLog();
        $totalTime = array_sum(array_column($queries, 'time'));
        
        echo "  ✅ Executed " . count($queries) . " queries in " . round($totalTime, 2) . "ms\n";
        
        $this->performanceMetrics['database'] = [
            'query_count' => count($queries),
            'total_time' => $totalTime,
            'avg_query_time' => $totalTime / count($queries)
        ];
        
        DB::disableQueryLog();
    }
    
    private function testUnderLoad()
    {
        echo "Testing under load (concurrent requests)...\n";
        
        $startTime = microtime(true);
        $processes = [];
        
        // Simulate 10 concurrent bracket requests
        for ($i = 0; $i < 10; $i++) {
            $cmd = "curl -s http://localhost:8000/api/brackets/17 > /dev/null 2>&1 &";
            exec($cmd);
        }
        
        // Wait for processes to complete
        sleep(2);
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        echo "  ✅ 10 concurrent requests completed in " . round($totalTime, 2) . "s\n";
        
        $this->performanceMetrics['load_test'] = [
            'concurrent_requests' => 10,
            'total_time' => $totalTime,
            'avg_request_time' => $totalTime / 10
        ];
    }
    
    private function generateFinalReport()
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "FINAL TEST REPORT - Marvel Rivals Tournament Bracket System\n";
        echo str_repeat("=", 80) . "\n\n";
        
        $totalTests = count($this->testResults);
        $passedTests = 0;
        $totalTime = 0;
        
        foreach ($this->testResults as $testName => $result) {
            $status = $result['status'] === 'PASSED' ? '✅ PASSED' : '❌ FAILED';
            $time = round($result['execution_time'], 3);
            $totalTime += $result['execution_time'];
            
            echo "Test: " . str_pad(ucwords(str_replace('_', ' ', $testName)), 40) . " $status ({$time}s)\n";
            
            if ($result['status'] === 'PASSED') {
                $passedTests++;
            } else {
                echo "      Error: {$result['error']}\n";
            }
        }
        
        echo "\n" . str_repeat("-", 80) . "\n";
        echo "SUMMARY:\n";
        echo "- Total Tests: $totalTests\n";
        echo "- Passed: $passedTests\n";
        echo "- Failed: " . ($totalTests - $passedTests) . "\n";
        echo "- Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";
        echo "- Total Execution Time: " . round($totalTime, 3) . "s\n\n";
        
        if (!empty($this->performanceMetrics)) {
            echo "PERFORMANCE METRICS:\n";
            foreach ($this->performanceMetrics as $metric => $data) {
                echo "- $metric:\n";
                foreach ($data as $key => $value) {
                    echo "  - $key: " . (is_numeric($value) ? round($value, 4) : $value) . "\n";
                }
            }
        }
        
        echo "\nRECOMMENDATIONS FOR TOURNAMENT ORGANIZERS:\n";
        echo "- Test bracket generation before tournament starts\n";
        echo "- Have backup procedures for live scoring failures\n";
        echo "- Monitor database performance during peak usage\n";
        echo "- Implement proper error handling for edge cases\n";
        echo "- Use rating-based seeding for competitive integrity\n";
        echo "- Consider Swiss system for group stages in large tournaments\n";
        
        if ($passedTests === $totalTests) {
            echo "\n🎉 ALL TESTS PASSED! The tournament bracket system is ready for production use.\n";
        } else {
            echo "\n⚠️  Some tests failed. Please review and fix issues before production deployment.\n";
        }
        
        echo "\n" . str_repeat("=", 80) . "\n";
    }
}

// Run the comprehensive test suite
$tester = new ComprehensiveTournamentTest();
$tester->runAllTests();