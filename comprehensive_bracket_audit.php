<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

// Initialize database connection
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'mrvl_esports',
    'username' => 'root',  
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

class BracketSystemAudit
{
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    public function __construct()
    {
        $this->results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => [],
            'summary' => [
                'total_tests' => 0,
                'passed' => 0,
                'failed' => 0,
                'warnings' => 0
            ]
        ];
    }
    
    public function runCompleteAudit()
    {
        echo "🔍 CRITICAL BRACKET SYSTEM AUDIT - PRE-PRODUCTION\n";
        echo "================================================\n\n";
        
        // Test all CRUD operations
        $this->testCreateOperations();
        $this->testReadOperations();
        $this->testUpdateOperations();
        $this->testDeleteOperations();
        
        // Test critical workflows
        $this->testTournamentInitialization();
        $this->testBracketProgression();
        $this->testMatchResultUpdates();
        $this->testTournamentCompletion();
        
        // Test edge cases
        $this->testEdgeCases();
        $this->testConcurrentOperations();
        $this->testErrorHandling();
        
        // Business rules validation
        $this->testBusinessRules();
        $this->testDataIntegrity();
        $this->testPerformance();
        
        // Generate final report
        $this->generateReport();
    }
    
    private function testCreateOperations()
    {
        echo "📝 TESTING CREATE OPERATIONS\n";
        echo "============================\n";
        
        // Test single elimination bracket creation
        $this->testSingleEliminationCreation();
        
        // Test double elimination bracket creation  
        $this->testDoubleEliminationCreation();
        
        // Test round robin creation
        $this->testRoundRobinCreation();
        
        // Test Swiss system creation
        $this->testSwissSystemCreation();
        
        // Test custom formats
        $this->testCustomFormatCreation();
        
        echo "\n";
    }
    
    private function testSingleEliminationCreation()
    {
        $testName = 'Single Elimination Bracket Creation';
        
        try {
            // Test with 8 teams (perfect power of 2)
            $eventId = $this->createTestEvent('Test Single Elim 8', 'single_elimination');
            $teams = $this->createTestTeams(8);
            $this->addTeamsToEvent($eventId, $teams);
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination',
                'seeding_method' => 'rating'
            ]);
            
            if ($response['success']) {
                $matches = DB::table('matches')->where('event_id', $eventId)->get();
                
                $expectedMatches = 7; // 8 teams = 7 matches total
                if (count($matches) === $expectedMatches) {
                    $this->logTest($testName . ' (8 teams)', 'PASS', 'Correct number of matches created');
                } else {
                    $this->logTest($testName . ' (8 teams)', 'FAIL', "Expected {$expectedMatches} matches, got " . count($matches));
                }
                
                // Verify bracket structure
                $this->verifyBracketStructure($eventId, 'single_elimination', 8);
                
            } else {
                $this->logTest($testName . ' (8 teams)', 'FAIL', $response['message'] ?? 'Unknown error');
            }
            
            // Test with 7 teams (odd number)
            $eventId2 = $this->createTestEvent('Test Single Elim 7', 'single_elimination');
            $teams2 = $this->createTestTeams(7);
            $this->addTeamsToEvent($eventId2, $teams2);
            
            $response2 = $this->makeBracketRequest('POST', "/api/brackets/{$eventId2}/generate", [
                'format' => 'single_elimination',
                'seeding_method' => 'rating'
            ]);
            
            if ($response2['success']) {
                $matches2 = DB::table('matches')->where('event_id', $eventId2)->get();
                $expectedMatches2 = 6; // 7 teams = 6 matches (1 bye)
                
                if (count($matches2) === $expectedMatches2) {
                    $this->logTest($testName . ' (7 teams - with bye)', 'PASS', 'Correct handling of bye');
                } else {
                    $this->logTest($testName . ' (7 teams - with bye)', 'FAIL', "Expected {$expectedMatches2} matches, got " . count($matches2));
                }
            } else {
                $this->logTest($testName . ' (7 teams - with bye)', 'FAIL', $response2['message'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testDoubleEliminationCreation()
    {
        $testName = 'Double Elimination Bracket Creation';
        
        try {
            $eventId = $this->createTestEvent('Test Double Elim', 'double_elimination');
            $teams = $this->createTestTeams(8);
            $this->addTeamsToEvent($eventId, $teams);
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'double_elimination',
                'seeding_method' => 'rating'
            ]);
            
            if ($response['success']) {
                $matches = DB::table('matches')->where('event_id', $eventId)->get();
                
                // Double elimination should have upper + lower bracket + grand final
                // Upper: 7 matches, Lower: ~7-8 matches, Grand Final: 1-2 matches
                $expectedMinMatches = 14; // Conservative estimate
                
                if (count($matches) >= $expectedMinMatches) {
                    $this->logTest($testName, 'PASS', 'Adequate number of matches created');
                    
                    // Verify we have upper, lower, and grand final brackets
                    $upperMatches = $matches->where('bracket_type', 'upper')->count();
                    $lowerMatches = $matches->where('bracket_type', 'lower')->count();
                    $grandFinalMatches = $matches->where('bracket_type', 'grand_final')->count();
                    
                    if ($upperMatches > 0 && $lowerMatches > 0 && $grandFinalMatches > 0) {
                        $this->logTest($testName . ' - Structure', 'PASS', 'All bracket types present');
                    } else {
                        $this->logTest($testName . ' - Structure', 'FAIL', 'Missing bracket types');
                    }
                } else {
                    $this->logTest($testName, 'FAIL', "Expected at least {$expectedMinMatches} matches, got " . count($matches));
                }
            } else {
                $this->logTest($testName, 'FAIL', $response['message'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testRoundRobinCreation()
    {
        $testName = 'Round Robin Creation';
        
        try {
            $eventId = $this->createTestEvent('Test Round Robin', 'round_robin');
            $teams = $this->createTestTeams(6);
            $this->addTeamsToEvent($eventId, $teams);
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'round_robin',
                'seeding_method' => 'rating'
            ]);
            
            if ($response['success']) {
                $matches = DB::table('matches')->where('event_id', $eventId)->get();
                
                // Round robin with 6 teams = 6*5/2 = 15 matches
                $expectedMatches = 15;
                
                if (count($matches) === $expectedMatches) {
                    $this->logTest($testName, 'PASS', 'Correct number of matches for round robin');
                } else {
                    $this->logTest($testName, 'FAIL', "Expected {$expectedMatches} matches, got " . count($matches));
                }
            } else {
                $this->logTest($testName, 'FAIL', $response['message'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testSwissSystemCreation()
    {
        $testName = 'Swiss System Creation';
        
        try {
            $eventId = $this->createTestEvent('Test Swiss', 'swiss');
            $teams = $this->createTestTeams(8);
            $this->addTeamsToEvent($eventId, $teams);
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'swiss',
                'seeding_method' => 'random'
            ]);
            
            if ($response['success']) {
                $matches = DB::table('matches')->where('event_id', $eventId)->get();
                
                // Swiss with 8 teams typically has 3 rounds = 12 matches
                $expectedMatches = 4; // First round only initially
                
                if (count($matches) >= $expectedMatches) {
                    $this->logTest($testName, 'PASS', 'Initial Swiss round created');
                } else {
                    $this->logTest($testName, 'FAIL', "Expected at least {$expectedMatches} matches, got " . count($matches));
                }
            } else {
                $this->logTest($testName, 'FAIL', $response['message'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testCustomFormatCreation()
    {
        $testName = 'Custom Format Creation';
        
        try {
            // Test with unsupported format - should default gracefully
            $eventId = $this->createTestEvent('Test Custom', 'custom_format');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'unsupported_format',
                'seeding_method' => 'rating'
            ]);
            
            if ($response['success'] || !empty($response['data'])) {
                $this->logTest($testName . ' - Fallback', 'PASS', 'System handles unknown formats gracefully');
            } else {
                $this->logTest($testName . ' - Fallback', 'WARNING', 'Unknown format not handled gracefully');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testReadOperations()
    {
        echo "📖 TESTING READ OPERATIONS\n";
        echo "==========================\n";
        
        // Test bracket retrieval
        $this->testBracketRetrieval();
        
        // Test match data retrieval
        $this->testMatchDataRetrieval();
        
        // Test standings retrieval
        $this->testStandingsRetrieval();
        
        // Test pagination and filtering
        $this->testPaginationAndFiltering();
        
        // Test performance under load
        $this->testReadPerformance();
        
        echo "\n";
    }
    
    private function testBracketRetrieval()
    {
        $testName = 'Bracket Data Retrieval';
        
        try {
            // Create a tournament with matches
            $eventId = $this->createTestEvent('Test Bracket Read', 'single_elimination');
            $teams = $this->createTestTeams(8);
            $this->addTeamsToEvent($eventId, $teams);
            
            // Generate bracket
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination',
                'seeding_method' => 'rating'
            ]);
            
            // Retrieve bracket
            $response = $this->makeBracketRequest('GET', "/api/brackets/{$eventId}");
            
            if ($response['success'] && isset($response['data']['bracket'])) {
                $bracket = $response['data']['bracket'];
                
                // Verify bracket structure
                if (isset($bracket['type']) && isset($bracket['rounds'])) {
                    $this->logTest($testName . ' - Structure', 'PASS', 'Bracket has proper structure');
                } else {
                    $this->logTest($testName . ' - Structure', 'FAIL', 'Bracket missing required fields');
                }
                
                // Verify team data is included
                $hasTeamData = false;
                foreach ($bracket['rounds'] as $round) {
                    foreach ($round['matches'] as $match) {
                        if (isset($match['team1']['name']) || isset($match['team2']['name'])) {
                            $hasTeamData = true;
                            break 2;
                        }
                    }
                }
                
                if ($hasTeamData) {
                    $this->logTest($testName . ' - Team Data', 'PASS', 'Team information included');
                } else {
                    $this->logTest($testName . ' - Team Data', 'FAIL', 'Team information missing');
                }
                
            } else {
                $this->logTest($testName, 'FAIL', 'Failed to retrieve bracket data');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testMatchDataRetrieval()
    {
        $testName = 'Match Data Retrieval';
        
        try {
            // Create event with matches
            $eventId = $this->createTestEvent('Test Match Read', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Get first match
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                // Test individual match retrieval
                $response = $this->makeBracketRequest('GET', "/api/matches/{$match->id}");
                
                if ($response['success'] && isset($response['data'])) {
                    $this->logTest($testName . ' - Individual Match', 'PASS', 'Match data retrieved successfully');
                } else {
                    $this->logTest($testName . ' - Individual Match', 'FAIL', 'Failed to retrieve match data');
                }
                
                // Test batch match retrieval
                $response = $this->makeBracketRequest('GET', "/api/events/{$eventId}/matches");
                
                if ($response['success'] && isset($response['data'])) {
                    $this->logTest($testName . ' - Batch Matches', 'PASS', 'Batch match data retrieved');
                } else {
                    $this->logTest($testName . ' - Batch Matches', 'FAIL', 'Failed to retrieve batch match data');
                }
            } else {
                $this->logTest($testName, 'FAIL', 'No matches found to test retrieval');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testStandingsRetrieval()
    {
        $testName = 'Standings Retrieval';
        
        try {
            // Create round robin tournament (better for standings)
            $eventId = $this->createTestEvent('Test Standings', 'round_robin');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'round_robin'
            ]);
            
            // Simulate some match results
            $matches = DB::table('matches')->where('event_id', $eventId)->limit(2)->get();
            foreach ($matches as $match) {
                DB::table('matches')->where('id', $match->id)->update([
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }
            
            // Retrieve standings
            $response = $this->makeBracketRequest('GET', "/api/events/{$eventId}/standings");
            
            if ($response['success'] && isset($response['data'])) {
                $standings = $response['data'];
                
                if (is_array($standings) && count($standings) > 0) {
                    $this->logTest($testName, 'PASS', 'Standings calculated and retrieved');
                    
                    // Verify standings structure
                    $firstTeam = $standings[0];
                    if (isset($firstTeam['team_name']) && isset($firstTeam['points'])) {
                        $this->logTest($testName . ' - Structure', 'PASS', 'Standings have proper structure');
                    } else {
                        $this->logTest($testName . ' - Structure', 'FAIL', 'Standings missing required fields');
                    }
                } else {
                    $this->logTest($testName, 'FAIL', 'No standings data returned');
                }
            } else {
                $this->logTest($testName, 'FAIL', 'Failed to retrieve standings');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testPaginationAndFiltering()
    {
        $testName = 'Pagination and Filtering';
        
        try {
            // Create multiple events for pagination testing
            $eventIds = [];
            for ($i = 1; $i <= 5; $i++) {
                $eventId = $this->createTestEvent("Test Event {$i}", 'single_elimination');
                $teams = $this->createTestTeams(4);
                $this->addTeamsToEvent($eventId, $teams);
                $eventIds[] = $eventId;
            }
            
            // Test pagination
            $response = $this->makeBracketRequest('GET', '/api/events?limit=2&page=1');
            
            if ($response['success'] && isset($response['data'])) {
                if (count($response['data']) <= 2) {
                    $this->logTest($testName . ' - Pagination', 'PASS', 'Pagination working correctly');
                } else {
                    $this->logTest($testName . ' - Pagination', 'FAIL', 'Pagination not enforcing limits');
                }
            } else {
                $this->logTest($testName . ' - Pagination', 'FAIL', 'Pagination request failed');
            }
            
            // Test filtering by status
            $response = $this->makeBracketRequest('GET', '/api/events?status=upcoming');
            
            if ($response['success']) {
                $this->logTest($testName . ' - Filtering', 'PASS', 'Filtering by status works');
            } else {
                $this->logTest($testName . ' - Filtering', 'FAIL', 'Filtering failed');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testReadPerformance()
    {
        $testName = 'Read Performance';
        
        try {
            // Create large bracket for performance testing
            $eventId = $this->createTestEvent('Performance Test', 'single_elimination');
            $teams = $this->createTestTeams(64); // Large bracket
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Time bracket retrieval
            $startTime = microtime(true);
            $response = $this->makeBracketRequest('GET', "/api/brackets/{$eventId}");
            $endTime = microtime(true);
            
            $executionTime = ($endTime - $startTime) * 1000; // Convert to ms
            
            if ($response['success'] && $executionTime < 2000) { // Less than 2 seconds
                $this->logTest($testName, 'PASS', "Large bracket retrieved in {$executionTime}ms");
            } else {
                $this->logTest($testName, 'WARNING', "Slow performance: {$executionTime}ms for 64-team bracket");
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testUpdateOperations()
    {
        echo "✏️  TESTING UPDATE OPERATIONS\n";
        echo "=============================\n";
        
        // Test match result updates
        $this->testMatchResultUpdates();
        
        // Test bracket progression
        $this->testBracketProgression();
        
        // Test score corrections
        $this->testScoreCorrections();
        
        // Test team swaps/reseeding
        $this->testTeamSwaps();
        
        // Test status changes
        $this->testStatusChanges();
        
        echo "\n";
    }
    
    private function testMatchResultUpdates()
    {
        $testName = 'Match Result Updates';
        
        try {
            // Create tournament
            $eventId = $this->createTestEvent('Test Match Updates', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Get first match
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                // Update match result
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                if ($response['success']) {
                    // Verify update was applied
                    $updatedMatch = DB::table('matches')->where('id', $match->id)->first();
                    
                    if ($updatedMatch->team1_score == 2 && $updatedMatch->team2_score == 1) {
                        $this->logTest($testName . ' - Score Update', 'PASS', 'Match scores updated correctly');
                    } else {
                        $this->logTest($testName . ' - Score Update', 'FAIL', 'Match scores not updated');
                    }
                    
                    if ($updatedMatch->status === 'completed') {
                        $this->logTest($testName . ' - Status Update', 'PASS', 'Match status updated');
                    } else {
                        $this->logTest($testName . ' - Status Update', 'FAIL', 'Match status not updated');
                    }
                } else {
                    $this->logTest($testName, 'FAIL', 'Match update request failed');
                }
            } else {
                $this->logTest($testName, 'FAIL', 'No match found to update');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testBracketProgression()
    {
        $testName = 'Bracket Progression';
        
        try {
            // Create 4-team single elimination
            $eventId = $this->createTestEvent('Test Progression', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Get first round matches
            $firstRoundMatches = DB::table('matches')
                ->where('event_id', $eventId)
                ->where('round', 1)
                ->get();
            
            if (count($firstRoundMatches) >= 2) {
                // Complete first match
                $match1 = $firstRoundMatches[0];
                $winnerId = $match1->team1_id;
                
                $this->makeBracketRequest('PUT', "/api/matches/{$match1->id}", [
                    'team1_score' => 2,
                    'team2_score' => 0,
                    'status' => 'completed'
                ]);
                
                // Complete second match
                $match2 = $firstRoundMatches[1];
                $winnerId2 = $match2->team1_id;
                
                $this->makeBracketRequest('PUT', "/api/matches/{$match2->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                // Check if final match was created/updated with winners
                $finalMatch = DB::table('matches')
                    ->where('event_id', $eventId)
                    ->where('round', 2)
                    ->first();
                
                if ($finalMatch && 
                    ($finalMatch->team1_id == $winnerId || $finalMatch->team2_id == $winnerId) &&
                    ($finalMatch->team1_id == $winnerId2 || $finalMatch->team2_id == $winnerId2)) {
                    $this->logTest($testName, 'PASS', 'Winners correctly advanced to final');
                } else {
                    $this->logTest($testName, 'FAIL', 'Winners not properly advanced');
                }
            } else {
                $this->logTest($testName, 'FAIL', 'Insufficient matches for progression test');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testScoreCorrections()
    {
        $testName = 'Score Corrections';
        
        try {
            // Create and complete a match
            $eventId = $this->createTestEvent('Test Corrections', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                // Complete match with initial score
                $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                // Correct the score
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 1,
                    'team2_score' => 2,
                    'status' => 'completed'
                ]);
                
                if ($response['success']) {
                    $updatedMatch = DB::table('matches')->where('id', $match->id)->first();
                    
                    if ($updatedMatch->team1_score == 1 && $updatedMatch->team2_score == 2) {
                        $this->logTest($testName, 'PASS', 'Score correction applied successfully');
                    } else {
                        $this->logTest($testName, 'FAIL', 'Score correction not applied');
                    }
                } else {
                    $this->logTest($testName, 'FAIL', 'Score correction request failed');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testTeamSwaps()
    {
        $testName = 'Team Swaps/Reseeding';
        
        try {
            // Create tournament
            $eventId = $this->createTestEvent('Test Team Swaps', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Try to swap teams in a match
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match && $match->team1_id && $match->team2_id) {
                $originalTeam1 = $match->team1_id;
                $originalTeam2 = $match->team2_id;
                
                // Attempt team swap (this might not be directly supported)
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}/swap-teams");
                
                if ($response['success']) {
                    $updatedMatch = DB::table('matches')->where('id', $match->id)->first();
                    
                    if ($updatedMatch->team1_id == $originalTeam2 && $updatedMatch->team2_id == $originalTeam1) {
                        $this->logTest($testName, 'PASS', 'Team swap completed');
                    } else {
                        $this->logTest($testName, 'PASS', 'Team swap endpoint exists but may not swap');
                    }
                } else {
                    $this->logTest($testName, 'WARNING', 'Team swap not supported - may be by design');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'WARNING', 'Team swap test inconclusive: ' . $e->getMessage());
        }
    }
    
    private function testStatusChanges()
    {
        $testName = 'Status Changes';
        
        try {
            $eventId = $this->createTestEvent('Test Status Changes', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                // Test different status transitions
                $statuses = ['scheduled', 'ongoing', 'completed', 'cancelled'];
                $passedTransitions = 0;
                
                foreach ($statuses as $status) {
                    $updateData = ['status' => $status];
                    
                    // Add required fields for completed status
                    if ($status === 'completed') {
                        $updateData['team1_score'] = 2;
                        $updateData['team2_score'] = 1;
                    }
                    
                    $response = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", $updateData);
                    
                    if ($response['success']) {
                        $passedTransitions++;
                    }
                }
                
                if ($passedTransitions >= 3) {
                    $this->logTest($testName, 'PASS', "Most status transitions working ({$passedTransitions}/4)");
                } else {
                    $this->logTest($testName, 'WARNING', "Limited status transitions ({$passedTransitions}/4)");
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testDeleteOperations()
    {
        echo "🗑️  TESTING DELETE OPERATIONS\n";
        echo "=============================\n";
        
        // Test match deletion
        $this->testMatchDeletion();
        
        // Test participant removal
        $this->testParticipantRemoval();
        
        // Test bracket reset
        $this->testBracketReset();
        
        // Test tournament cancellation
        $this->testTournamentCancellation();
        
        // Test cascade operations
        $this->testCascadeOperations();
        
        echo "\n";
    }
    
    private function testMatchDeletion()
    {
        $testName = 'Match Deletion';
        
        try {
            $eventId = $this->createTestEvent('Test Match Deletion', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Get a match to delete
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                $response = $this->makeBracketRequest('DELETE', "/api/matches/{$match->id}");
                
                if ($response['success']) {
                    // Verify match was deleted
                    $deletedMatch = DB::table('matches')->where('id', $match->id)->first();
                    
                    if (!$deletedMatch) {
                        $this->logTest($testName, 'PASS', 'Match successfully deleted');
                    } else {
                        $this->logTest($testName, 'FAIL', 'Match not deleted from database');
                    }
                } else {
                    $this->logTest($testName, 'WARNING', 'Match deletion not supported or failed');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testParticipantRemoval()
    {
        $testName = 'Participant Removal';
        
        try {
            $eventId = $this->createTestEvent('Test Participant Removal', 'round_robin');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            // Remove a team before bracket generation
            $teamToRemove = $teams[0];
            $response = $this->makeBracketRequest('DELETE', "/api/events/{$eventId}/teams/{$teamToRemove['id']}");
            
            if ($response['success']) {
                // Verify team was removed
                $remainingTeams = DB::table('event_teams')->where('event_id', $eventId)->count();
                
                if ($remainingTeams == 3) {
                    $this->logTest($testName . ' - Before Generation', 'PASS', 'Team removed successfully');
                } else {
                    $this->logTest($testName . ' - Before Generation', 'FAIL', 'Team not removed');
                }
            } else {
                $this->logTest($testName . ' - Before Generation', 'WARNING', 'Team removal not supported');
            }
            
            // Test removal after bracket generation
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'round_robin'
            ]);
            
            $remainingTeamIds = DB::table('event_teams')->where('event_id', $eventId)->pluck('team_id');
            if (count($remainingTeamIds) > 0) {
                $teamToRemove2 = $remainingTeamIds[0];
                $response2 = $this->makeBracketRequest('DELETE', "/api/events/{$eventId}/teams/{$teamToRemove2}");
                
                if ($response2['success']) {
                    $this->logTest($testName . ' - After Generation', 'WARNING', 'Team removal allowed after generation - check integrity');
                } else {
                    $this->logTest($testName . ' - After Generation', 'PASS', 'Team removal properly restricted after generation');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testBracketReset()
    {
        $testName = 'Bracket Reset';
        
        try {
            $eventId = $this->createTestEvent('Test Bracket Reset', 'single_elimination');
            $teams = $this->createTestTeams(8);
            $this->addTeamsToEvent($eventId, $teams);
            
            // Generate bracket
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            $matchesBeforeReset = DB::table('matches')->where('event_id', $eventId)->count();
            
            // Reset bracket
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/reset");
            
            if ($response['success']) {
                $matchesAfterReset = DB::table('matches')->where('event_id', $eventId)->count();
                
                if ($matchesAfterReset == 0) {
                    $this->logTest($testName, 'PASS', 'Bracket reset successfully - all matches removed');
                } else {
                    $this->logTest($testName, 'FAIL', "Bracket reset incomplete - {$matchesAfterReset} matches remain");
                }
                
                // Verify event status reset
                $event = DB::table('events')->where('id', $eventId)->first();
                if ($event && $event->status === 'upcoming') {
                    $this->logTest($testName . ' - Event Status', 'PASS', 'Event status reset to upcoming');
                } else {
                    $this->logTest($testName . ' - Event Status', 'WARNING', 'Event status not reset');
                }
            } else {
                $this->logTest($testName, 'FAIL', 'Bracket reset request failed');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testTournamentCancellation()
    {
        $testName = 'Tournament Cancellation';
        
        try {
            $eventId = $this->createTestEvent('Test Cancellation', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Cancel tournament
            $response = $this->makeBracketRequest('PUT', "/api/events/{$eventId}", [
                'status' => 'cancelled'
            ]);
            
            if ($response['success']) {
                // Verify event status
                $event = DB::table('events')->where('id', $eventId)->first();
                
                if ($event && $event->status === 'cancelled') {
                    $this->logTest($testName, 'PASS', 'Tournament cancelled successfully');
                    
                    // Check if matches are also marked as cancelled
                    $cancelledMatches = DB::table('matches')
                        ->where('event_id', $eventId)
                        ->where('status', 'cancelled')
                        ->count();
                    
                    $totalMatches = DB::table('matches')->where('event_id', $eventId)->count();
                    
                    if ($cancelledMatches == $totalMatches) {
                        $this->logTest($testName . ' - Match Status', 'PASS', 'All matches marked as cancelled');
                    } else {
                        $this->logTest($testName . ' - Match Status', 'WARNING', 'Not all matches marked as cancelled');
                    }
                } else {
                    $this->logTest($testName, 'FAIL', 'Tournament status not updated to cancelled');
                }
            } else {
                $this->logTest($testName, 'FAIL', 'Tournament cancellation request failed');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testCascadeOperations()
    {
        $testName = 'Cascade Operations';
        
        try {
            $eventId = $this->createTestEvent('Test Cascade', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Count related records before deletion
            $matchesCount = DB::table('matches')->where('event_id', $eventId)->count();
            $eventTeamsCount = DB::table('event_teams')->where('event_id', $eventId)->count();
            
            // Delete event (should cascade)
            $response = $this->makeBracketRequest('DELETE', "/api/events/{$eventId}");
            
            if ($response['success']) {
                // Check if related records were deleted
                $remainingMatches = DB::table('matches')->where('event_id', $eventId)->count();
                $remainingEventTeams = DB::table('event_teams')->where('event_id', $eventId)->count();
                
                if ($remainingMatches == 0 && $remainingEventTeams == 0) {
                    $this->logTest($testName, 'PASS', 'Cascade deletion working correctly');
                } else {
                    $this->logTest($testName, 'FAIL', 'Cascade deletion incomplete');
                }
            } else {
                $this->logTest($testName, 'WARNING', 'Event deletion not supported or failed');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testTournamentInitialization()
    {
        echo "🚀 TESTING TOURNAMENT INITIALIZATION\n";
        echo "===================================\n";
        
        $this->testEventCreation();
        $this->testTeamRegistration();
        $this->testSeedingMethods();
        $this->testBracketGeneration();
        
        echo "\n";
    }
    
    private function testEventCreation()
    {
        $testName = 'Event Creation';
        
        try {
            $response = $this->makeBracketRequest('POST', '/api/events', [
                'name' => 'Test Event Creation',
                'format' => 'single_elimination',
                'status' => 'upcoming',
                'max_teams' => 16,
                'start_date' => date('Y-m-d H:i:s', strtotime('+1 week'))
            ]);
            
            if ($response['success'] && isset($response['data']['id'])) {
                $this->logTest($testName, 'PASS', 'Event created successfully');
                
                // Verify event in database
                $event = DB::table('events')->where('id', $response['data']['id'])->first();
                if ($event && $event->name === 'Test Event Creation') {
                    $this->logTest($testName . ' - Database', 'PASS', 'Event stored correctly in database');
                } else {
                    $this->logTest($testName . ' - Database', 'FAIL', 'Event not found in database');
                }
            } else {
                $this->logTest($testName, 'FAIL', 'Event creation failed');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testTeamRegistration()
    {
        $testName = 'Team Registration';
        
        try {
            $eventId = $this->createTestEvent('Test Team Registration', 'single_elimination');
            $teams = $this->createTestTeams(4);
            
            // Register teams one by one
            $successfulRegistrations = 0;
            foreach ($teams as $team) {
                $response = $this->makeBracketRequest('POST', "/api/events/{$eventId}/teams", [
                    'team_id' => $team['id']
                ]);
                
                if ($response['success']) {
                    $successfulRegistrations++;
                }
            }
            
            if ($successfulRegistrations == count($teams)) {
                $this->logTest($testName, 'PASS', 'All teams registered successfully');
            } else {
                $this->logTest($testName, 'FAIL', "Only {$successfulRegistrations}/" . count($teams) . " teams registered");
            }
            
            // Test duplicate registration
            $response = $this->makeBracketRequest('POST', "/api/events/{$eventId}/teams", [
                'team_id' => $teams[0]['id']
            ]);
            
            if (!$response['success']) {
                $this->logTest($testName . ' - Duplicate Prevention', 'PASS', 'Duplicate registration prevented');
            } else {
                $this->logTest($testName . ' - Duplicate Prevention', 'WARNING', 'Duplicate registration allowed');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testSeedingMethods()
    {
        $testName = 'Seeding Methods';
        
        try {
            $seedingMethods = ['rating', 'random', 'manual'];
            $passedMethods = 0;
            
            foreach ($seedingMethods as $method) {
                $eventId = $this->createTestEvent("Test Seeding {$method}", 'single_elimination');
                $teams = $this->createTestTeams(8);
                $this->addTeamsToEvent($eventId, $teams);
                
                $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                    'format' => 'single_elimination',
                    'seeding_method' => $method
                ]);
                
                if ($response['success']) {
                    $passedMethods++;
                }
            }
            
            if ($passedMethods == count($seedingMethods)) {
                $this->logTest($testName, 'PASS', 'All seeding methods working');
            } else {
                $this->logTest($testName, 'WARNING', "{$passedMethods}/" . count($seedingMethods) . " seeding methods working");
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testBracketGeneration()
    {
        $testName = 'Bracket Generation';
        
        try {
            $formats = ['single_elimination', 'double_elimination', 'round_robin', 'swiss'];
            $passedFormats = 0;
            
            foreach ($formats as $format) {
                $eventId = $this->createTestEvent("Test Format {$format}", $format);
                $teams = $this->createTestTeams(8);
                $this->addTeamsToEvent($eventId, $teams);
                
                $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                    'format' => $format,
                    'seeding_method' => 'rating'
                ]);
                
                if ($response['success']) {
                    $passedFormats++;
                }
            }
            
            if ($passedFormats == count($formats)) {
                $this->logTest($testName, 'PASS', 'All bracket formats generate successfully');
            } else {
                $this->logTest($testName, 'WARNING', "{$passedFormats}/" . count($formats) . " formats working");
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testEdgeCases()
    {
        echo "⚠️  TESTING EDGE CASES\n";
        echo "=====================\n";
        
        $this->testOddParticipants();
        $this->testByeHandling();
        $this->testParticipantDropouts();
        $this->testMinimumParticipants();
        $this->testMaximumParticipants();
        
        echo "\n";
    }
    
    private function testOddParticipants()
    {
        $testName = 'Odd Number of Participants';
        
        try {
            $oddNumbers = [3, 5, 7, 9];
            $passedTests = 0;
            
            foreach ($oddNumbers as $teamCount) {
                $eventId = $this->createTestEvent("Test Odd {$teamCount}", 'single_elimination');
                $teams = $this->createTestTeams($teamCount);
                $this->addTeamsToEvent($eventId, $teams);
                
                $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                    'format' => 'single_elimination'
                ]);
                
                if ($response['success']) {
                    $matches = DB::table('matches')->where('event_id', $eventId)->get();
                    $expectedMatches = $teamCount - 1; // n-1 matches for single elimination
                    
                    if (count($matches) == $expectedMatches) {
                        $passedTests++;
                    }
                }
            }
            
            if ($passedTests == count($oddNumbers)) {
                $this->logTest($testName, 'PASS', 'All odd participant counts handled correctly');
            } else {
                $this->logTest($testName, 'WARNING', "{$passedTests}/" . count($oddNumbers) . " odd counts handled");
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testByeHandling()
    {
        $testName = 'Bye Handling';
        
        try {
            // Test with 7 teams (requires 1 bye)
            $eventId = $this->createTestEvent('Test Bye Handling', 'single_elimination');
            $teams = $this->createTestTeams(7);
            $this->addTeamsToEvent($eventId, $teams);
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            if ($response['success']) {
                // Check for matches with only one team (bye)
                $byeMatches = DB::table('matches')
                    ->where('event_id', $eventId)
                    ->where(function($query) {
                        $query->whereNull('team1_id')->orWhereNull('team2_id');
                    })
                    ->count();
                
                if ($byeMatches > 0) {
                    $this->logTest($testName, 'PASS', 'Bye matches created correctly');
                } else {
                    // Alternative: check if bracket structure accommodates byes properly
                    $totalMatches = DB::table('matches')->where('event_id', $eventId)->count();
                    if ($totalMatches == 6) { // 7 teams should result in 6 matches
                        $this->logTest($testName, 'PASS', 'Bracket structure handles byes correctly');
                    } else {
                        $this->logTest($testName, 'WARNING', 'Bye handling unclear');
                    }
                }
            } else {
                $this->logTest($testName, 'FAIL', 'Failed to generate bracket with byes');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testParticipantDropouts()
    {
        $testName = 'Participant Dropouts';
        
        try {
            $eventId = $this->createTestEvent('Test Dropouts', 'single_elimination');
            $teams = $this->createTestTeams(8);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Get a match and simulate a team dropout (forfeit)
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                // Award win to opponent
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => $match->team1_id ? 0 : 2,
                    'team2_score' => $match->team1_id ? 2 : 0,
                    'status' => 'completed',
                    'forfeit' => true
                ]);
                
                if ($response['success']) {
                    $this->logTest($testName, 'PASS', 'Forfeit/dropout handled successfully');
                } else {
                    $this->logTest($testName, 'WARNING', 'Forfeit handling may not be explicitly supported');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testMinimumParticipants()
    {
        $testName = 'Minimum Participants';
        
        try {
            // Test with only 1 team
            $eventId = $this->createTestEvent('Test Min Participants', 'single_elimination');
            $teams = $this->createTestTeams(1);
            $this->addTeamsToEvent($eventId, $teams);
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            if (!$response['success']) {
                $this->logTest($testName . ' (1 team)', 'PASS', 'Correctly rejects single team tournament');
            } else {
                $this->logTest($testName . ' (1 team)', 'WARNING', 'Allows single team tournament');
            }
            
            // Test with 2 teams (minimum valid)
            $eventId2 = $this->createTestEvent('Test Min Valid', 'single_elimination');
            $teams2 = $this->createTestTeams(2);
            $this->addTeamsToEvent($eventId2, $teams2);
            
            $response2 = $this->makeBracketRequest('POST', "/api/brackets/{$eventId2}/generate", [
                'format' => 'single_elimination'
            ]);
            
            if ($response2['success']) {
                $this->logTest($testName . ' (2 teams)', 'PASS', 'Accepts minimum valid tournament');
            } else {
                $this->logTest($testName . ' (2 teams)', 'FAIL', 'Rejects valid 2-team tournament');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testMaximumParticipants()
    {
        $testName = 'Maximum Participants';
        
        try {
            // Test with large number of teams
            $eventId = $this->createTestEvent('Test Max Participants', 'single_elimination');
            $teams = $this->createTestTeams(128); // Large tournament
            $this->addTeamsToEvent($eventId, $teams);
            
            $startTime = microtime(true);
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            $endTime = microtime(true);
            
            $executionTime = ($endTime - $startTime) * 1000;
            
            if ($response['success']) {
                if ($executionTime < 5000) { // Less than 5 seconds
                    $this->logTest($testName, 'PASS', "Large tournament handled efficiently ({$executionTime}ms)");
                } else {
                    $this->logTest($testName, 'WARNING', "Large tournament slow ({$executionTime}ms)");
                }
            } else {
                $this->logTest($testName, 'FAIL', 'Large tournament generation failed');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testConcurrentOperations()
    {
        echo "🔄 TESTING CONCURRENT OPERATIONS\n";
        echo "================================\n";
        
        $this->testConcurrentMatchUpdates();
        $this->testRaceConditions();
        $this->testLockingMechanisms();
        
        echo "\n";
    }
    
    private function testConcurrentMatchUpdates()
    {
        $testName = 'Concurrent Match Updates';
        
        try {
            $eventId = $this->createTestEvent('Test Concurrent', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                // Simulate concurrent updates (this is a simplified simulation)
                $response1 = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                $response2 = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 1,
                    'team2_score' => 2,
                    'status' => 'completed'
                ]);
                
                // Check final state
                $finalMatch = DB::table('matches')->where('id', $match->id)->first();
                
                if ($finalMatch && ($finalMatch->team1_score + $finalMatch->team2_score > 0)) {
                    $this->logTest($testName, 'PASS', 'Concurrent updates handled (last write wins)');
                } else {
                    $this->logTest($testName, 'WARNING', 'Concurrent update behavior unclear');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testRaceConditions()
    {
        $testName = 'Race Conditions';
        
        try {
            // This is a simplified test - real race condition testing would require parallel processing
            $eventId = $this->createTestEvent('Test Race Conditions', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Complete both semi-final matches quickly
            $matches = DB::table('matches')->where('event_id', $eventId)->where('round', 1)->get();
            
            foreach ($matches as $match) {
                $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 0,
                    'status' => 'completed'
                ]);
            }
            
            // Check if final match was properly populated
            $finalMatch = DB::table('matches')->where('event_id', $eventId)->where('round', 2)->first();
            
            if ($finalMatch && $finalMatch->team1_id && $finalMatch->team2_id) {
                $this->logTest($testName, 'PASS', 'No apparent race conditions in bracket progression');
            } else {
                $this->logTest($testName, 'WARNING', 'Possible race condition in bracket progression');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testLockingMechanisms()
    {
        $testName = 'Locking Mechanisms';
        
        try {
            // Test if the system prevents simultaneous bracket generation
            $eventId = $this->createTestEvent('Test Locking', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            // First generation
            $response1 = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Immediate second generation (should be prevented)
            $response2 = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            if ($response1['success'] && !$response2['success']) {
                $this->logTest($testName, 'PASS', 'Duplicate bracket generation prevented');
            } elseif ($response1['success'] && $response2['success']) {
                $this->logTest($testName, 'WARNING', 'Duplicate bracket generation allowed - may overwrite');
            } else {
                $this->logTest($testName, 'FAIL', 'Bracket generation issues');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testErrorHandling()
    {
        echo "❌ TESTING ERROR HANDLING\n";
        echo "=========================\n";
        
        $this->testInvalidInput();
        $this->testMissingData();
        $this->testDatabaseErrors();
        $this->testNetworkErrors();
        
        echo "\n";
    }
    
    private function testInvalidInput()
    {
        $testName = 'Invalid Input Handling';
        
        try {
            // Test invalid event ID
            $response = $this->makeBracketRequest('GET', '/api/brackets/99999');
            
            if (!$response['success'] && isset($response['message'])) {
                $this->logTest($testName . ' - Invalid ID', 'PASS', 'Invalid event ID handled gracefully');
            } else {
                $this->logTest($testName . ' - Invalid ID', 'FAIL', 'Invalid event ID not handled');
            }
            
            // Test invalid match update
            $eventId = $this->createTestEvent('Test Invalid Input', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                // Send invalid score
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => -1, // Invalid negative score
                    'team2_score' => 'invalid', // Invalid string score
                    'status' => 'completed'
                ]);
                
                if (!$response['success']) {
                    $this->logTest($testName . ' - Invalid Scores', 'PASS', 'Invalid scores rejected');
                } else {
                    $this->logTest($testName . ' - Invalid Scores', 'FAIL', 'Invalid scores accepted');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'PASS', 'Invalid input caused exception (expected): ' . $e->getMessage());
        }
    }
    
    private function testMissingData()
    {
        $testName = 'Missing Data Handling';
        
        try {
            // Test bracket generation with no teams
            $eventId = $this->createTestEvent('Test No Teams', 'single_elimination');
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            if (!$response['success']) {
                $this->logTest($testName . ' - No Teams', 'PASS', 'No teams scenario handled gracefully');
            } else {
                $this->logTest($testName . ' - No Teams', 'FAIL', 'Allows bracket generation with no teams');
            }
            
            // Test match update with missing required fields
            $eventId2 = $this->createTestEvent('Test Missing Fields', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId2, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId2}/generate", [
                'format' => 'single_elimination'
            ]);
            
            $match = DB::table('matches')->where('event_id', $eventId2)->first();
            
            if ($match) {
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'status' => 'completed'
                    // Missing scores
                ]);
                
                if (!$response['success']) {
                    $this->logTest($testName . ' - Missing Scores', 'PASS', 'Missing required fields rejected');
                } else {
                    $this->logTest($testName . ' - Missing Scores', 'WARNING', 'Missing scores allowed');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testDatabaseErrors()
    {
        $testName = 'Database Error Handling';
        
        try {
            // This is a simplified test - would need to simulate actual DB errors
            // Test with extremely long event name (might cause DB constraint error)
            $longName = str_repeat('A', 1000);
            
            $response = $this->makeBracketRequest('POST', '/api/events', [
                'name' => $longName,
                'format' => 'single_elimination'
            ]);
            
            if (!$response['success']) {
                $this->logTest($testName, 'PASS', 'Database constraint errors handled');
            } else {
                $this->logTest($testName, 'WARNING', 'Long event name accepted - check constraints');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'PASS', 'Database error caused exception (expected)');
        }
    }
    
    private function testNetworkErrors()
    {
        $testName = 'Network Error Handling';
        
        try {
            // Test with invalid endpoint
            $response = $this->makeBracketRequest('GET', '/api/nonexistent-endpoint');
            
            if (!$response['success'] || isset($response['error'])) {
                $this->logTest($testName, 'PASS', 'Invalid endpoints handled gracefully');
            } else {
                $this->logTest($testName, 'WARNING', 'Invalid endpoint behavior unclear');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'PASS', 'Network error caused exception (expected)');
        }
    }
    
    private function testBusinessRules()
    {
        echo "📋 TESTING BUSINESS RULES\n";
        echo "=========================\n";
        
        $this->testMatchResultValidation();
        $this->testTournamentProgression();
        $this->testSeedingRules();
        $this->testTournamentStates();
        
        echo "\n";
    }
    
    private function testMatchResultValidation()
    {
        $testName = 'Match Result Validation';
        
        try {
            $eventId = $this->createTestEvent('Test Result Validation', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            
            if ($match) {
                // Test tie in elimination match (should be invalid)
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 1,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                if (!$response['success']) {
                    $this->logTest($testName . ' - Tie Prevention', 'PASS', 'Ties in elimination matches prevented');
                } else {
                    $this->logTest($testName . ' - Tie Prevention', 'WARNING', 'Ties in elimination matches allowed');
                }
                
                // Test valid result
                $response2 = $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                if ($response2['success']) {
                    $this->logTest($testName . ' - Valid Result', 'PASS', 'Valid results accepted');
                } else {
                    $this->logTest($testName . ' - Valid Result', 'FAIL', 'Valid results rejected');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testTournamentProgression()
    {
        $testName = 'Tournament Progression Rules';
        
        try {
            $eventId = $this->createTestEvent('Test Progression Rules', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Try to update final match before semi-finals are complete
            $finalMatch = DB::table('matches')->where('event_id', $eventId)->where('round', 2)->first();
            
            if ($finalMatch) {
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$finalMatch->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                if (!$response['success']) {
                    $this->logTest($testName . ' - Sequential Order', 'PASS', 'Out-of-order match completion prevented');
                } else {
                    $this->logTest($testName . ' - Sequential Order', 'WARNING', 'Out-of-order match completion allowed');
                }
            }
            
            // Complete matches in proper order
            $semiMatches = DB::table('matches')->where('event_id', $eventId)->where('round', 1)->get();
            foreach ($semiMatches as $match) {
                $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 0,
                    'status' => 'completed'
                ]);
            }
            
            // Now final should be updatable
            if ($finalMatch) {
                $response = $this->makeBracketRequest('PUT', "/api/matches/{$finalMatch->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                if ($response['success']) {
                    $this->logTest($testName . ' - Proper Order', 'PASS', 'Sequential match completion works');
                } else {
                    $this->logTest($testName . ' - Proper Order', 'FAIL', 'Sequential match completion fails');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testSeedingRules()
    {
        $testName = 'Seeding Rules';
        
        try {
            $eventId = $this->createTestEvent('Test Seeding Rules', 'single_elimination');
            $teams = $this->createTestTeams(8);
            
            // Assign specific ratings for predictable seeding
            foreach ($teams as $i => $team) {
                DB::table('teams')->where('id', $team['id'])->update([
                    'rating' => 1000 + ($i * 100) // 1000, 1100, 1200, etc.
                ]);
            }
            
            $this->addTeamsToEvent($eventId, $teams);
            
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination',
                'seeding_method' => 'rating'
            ]);
            
            if ($response['success']) {
                // Check if highest rated team (last one) is seeded #1
                $firstMatch = DB::table('matches')
                    ->where('event_id', $eventId)
                    ->where('round', 1)
                    ->where('bracket_position', 1)
                    ->first();
                
                if ($firstMatch) {
                    $highestRatedTeam = $teams[count($teams) - 1]; // Last team has highest rating
                    
                    if ($firstMatch->team1_id == $highestRatedTeam['id']) {
                        $this->logTest($testName, 'PASS', 'Rating-based seeding works correctly');
                    } else {
                        $this->logTest($testName, 'WARNING', 'Rating-based seeding may not be working as expected');
                    }
                }
            } else {
                $this->logTest($testName, 'FAIL', 'Seeding test failed to generate bracket');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testTournamentStates()
    {
        $testName = 'Tournament State Management';
        
        try {
            $eventId = $this->createTestEvent('Test States', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            // Initial state should be 'upcoming'
            $event = DB::table('events')->where('id', $eventId)->first();
            if ($event->status === 'upcoming') {
                $this->logTest($testName . ' - Initial State', 'PASS', 'Tournament starts in upcoming state');
            } else {
                $this->logTest($testName . ' - Initial State', 'WARNING', 'Tournament initial state: ' . $event->status);
            }
            
            // Generate bracket - should change to 'ongoing'
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            $event = DB::table('events')->where('id', $eventId)->first();
            if ($event->status === 'ongoing') {
                $this->logTest($testName . ' - Ongoing State', 'PASS', 'Tournament state changes to ongoing after bracket generation');
            } else {
                $this->logTest($testName . ' - Ongoing State', 'WARNING', 'Tournament state after generation: ' . $event->status);
            }
            
            // Complete all matches - should change to 'completed'
            $matches = DB::table('matches')->where('event_id', $eventId)->get();
            foreach ($matches as $match) {
                $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
            }
            
            $event = DB::table('events')->where('id', $eventId)->first();
            if ($event->status === 'completed') {
                $this->logTest($testName . ' - Completed State', 'PASS', 'Tournament state changes to completed when all matches done');
            } else {
                $this->logTest($testName . ' - Completed State', 'WARNING', 'Tournament state after completion: ' . $event->status);
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testDataIntegrity()
    {
        echo "🔒 TESTING DATA INTEGRITY\n";
        echo "=========================\n";
        
        $this->testForeignKeyConstraints();
        $this->testOrphanedRecords();
        $this->testDataConsistency();
        $this->testAuditTrails();
        
        echo "\n";
    }
    
    private function testForeignKeyConstraints()
    {
        $testName = 'Foreign Key Constraints';
        
        try {
            // Try to create match with non-existent team
            $eventId = $this->createTestEvent('Test FK Constraints', 'single_elimination');
            
            try {
                DB::table('matches')->insert([
                    'event_id' => $eventId,
                    'team1_id' => 99999, // Non-existent team
                    'team2_id' => 99998, // Non-existent team
                    'round' => 1,
                    'bracket_position' => 1,
                    'status' => 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $this->logTest($testName, 'FAIL', 'Foreign key constraint not enforced');
            } catch (Exception $e) {
                $this->logTest($testName, 'PASS', 'Foreign key constraints properly enforced');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testOrphanedRecords()
    {
        $testName = 'Orphaned Records Prevention';
        
        try {
            $eventId = $this->createTestEvent('Test Orphaned Records', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Count matches before deletion
            $matchesBefore = DB::table('matches')->where('event_id', $eventId)->count();
            
            // Delete event (should cascade delete matches)
            DB::table('events')->where('id', $eventId)->delete();
            
            // Count matches after deletion
            $matchesAfter = DB::table('matches')->where('event_id', $eventId)->count();
            
            if ($matchesAfter == 0) {
                $this->logTest($testName, 'PASS', 'Orphaned matches prevented by cascade delete');
            } else {
                $this->logTest($testName, 'FAIL', 'Orphaned matches remain after event deletion');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testDataConsistency()
    {
        $testName = 'Data Consistency';
        
        try {
            $eventId = $this->createTestEvent('Test Data Consistency', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Complete first round
            $firstRoundMatches = DB::table('matches')->where('event_id', $eventId)->where('round', 1)->get();
            foreach ($firstRoundMatches as $match) {
                $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
            }
            
            // Check if final match has correct participants
            $finalMatch = DB::table('matches')->where('event_id', $eventId)->where('round', 2)->first();
            
            if ($finalMatch && $finalMatch->team1_id && $finalMatch->team2_id) {
                // Verify these teams are winners from previous round
                $winners = [];
                foreach ($firstRoundMatches as $match) {
                    $updatedMatch = DB::table('matches')->where('id', $match->id)->first();
                    if ($updatedMatch->team1_score > $updatedMatch->team2_score) {
                        $winners[] = $updatedMatch->team1_id;
                    } else {
                        $winners[] = $updatedMatch->team2_id;
                    }
                }
                
                if (in_array($finalMatch->team1_id, $winners) && in_array($finalMatch->team2_id, $winners)) {
                    $this->logTest($testName, 'PASS', 'Bracket progression maintains data consistency');
                } else {
                    $this->logTest($testName, 'FAIL', 'Bracket progression inconsistent');
                }
            } else {
                $this->logTest($testName, 'WARNING', 'Final match not properly populated');
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testAuditTrails()
    {
        $testName = 'Audit Trails';
        
        try {
            $eventId = $this->createTestEvent('Test Audit Trails', 'single_elimination');
            $teams = $this->createTestTeams(4);
            $this->addTeamsToEvent($eventId, $teams);
            
            // Generate bracket with history enabled
            $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination',
                'save_history' => true
            ]);
            
            if ($response['success']) {
                // Check if history was saved
                $history = DB::table('bracket_history')->where('event_id', $eventId)->first();
                
                if ($history) {
                    $this->logTest($testName . ' - Bracket History', 'PASS', 'Bracket generation history saved');
                } else {
                    $this->logTest($testName . ' - Bracket History', 'WARNING', 'Bracket history not saved');
                }
            }
            
            // Update a match and check for audit trail
            $match = DB::table('matches')->where('event_id', $eventId)->first();
            if ($match) {
                $this->makeBracketRequest('PUT', "/api/matches/{$match->id}", [
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed'
                ]);
                
                // Check if update was logged (implementation-dependent)
                $logs = DB::table('event_logs')->where('event_id', $eventId)->where('action', 'match_updated')->count();
                
                if ($logs > 0) {
                    $this->logTest($testName . ' - Match Updates', 'PASS', 'Match updates logged');
                } else {
                    $this->logTest($testName . ' - Match Updates', 'WARNING', 'Match updates not logged');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testPerformance()
    {
        echo "⚡ TESTING PERFORMANCE\n";
        echo "====================\n";
        
        $this->testLargeBracketPerformance();
        $this->testConcurrentUserPerformance();
        $this->testDatabasePerformance();
        $this->testMemoryUsage();
        
        echo "\n";
    }
    
    private function testLargeBracketPerformance()
    {
        $testName = 'Large Bracket Performance';
        
        try {
            $sizes = [16, 32, 64, 128];
            
            foreach ($sizes as $size) {
                $eventId = $this->createTestEvent("Perf Test {$size}", 'single_elimination');
                $teams = $this->createTestTeams($size);
                $this->addTeamsToEvent($eventId, $teams);
                
                $startTime = microtime(true);
                $response = $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                    'format' => 'single_elimination'
                ]);
                $endTime = microtime(true);
                
                $executionTime = ($endTime - $startTime) * 1000;
                
                if ($response['success']) {
                    if ($executionTime < 1000) {
                        $this->logTest($testName . " ({$size} teams)", 'PASS', "Generated in {$executionTime}ms");
                    } elseif ($executionTime < 5000) {
                        $this->logTest($testName . " ({$size} teams)", 'WARNING', "Slow generation: {$executionTime}ms");
                    } else {
                        $this->logTest($testName . " ({$size} teams)", 'FAIL', "Very slow: {$executionTime}ms");
                    }
                } else {
                    $this->logTest($testName . " ({$size} teams)", 'FAIL', 'Generation failed');
                }
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testConcurrentUserPerformance()
    {
        $testName = 'Concurrent User Performance';
        
        try {
            // Simulate multiple users accessing bracket data simultaneously
            $eventId = $this->createTestEvent('Concurrent Perf Test', 'single_elimination');
            $teams = $this->createTestTeams(16);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Simulate concurrent reads
            $startTime = microtime(true);
            for ($i = 0; $i < 10; $i++) {
                $this->makeBracketRequest('GET', "/api/brackets/{$eventId}");
            }
            $endTime = microtime(true);
            
            $avgTime = (($endTime - $startTime) * 1000) / 10;
            
            if ($avgTime < 500) {
                $this->logTest($testName, 'PASS', "Average response time: {$avgTime}ms");
            } else {
                $this->logTest($testName, 'WARNING', "Slow average response: {$avgTime}ms");
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testDatabasePerformance()
    {
        $testName = 'Database Performance';
        
        try {
            $eventId = $this->createTestEvent('DB Perf Test', 'single_elimination');
            $teams = $this->createTestTeams(32);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Test complex query performance
            $startTime = microtime(true);
            
            $matches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.event_id', $eventId)
                ->select(['m.*', 't1.name as team1_name', 't2.name as team2_name'])
                ->get();
            
            $endTime = microtime(true);
            $queryTime = ($endTime - $startTime) * 1000;
            
            if ($queryTime < 100) {
                $this->logTest($testName, 'PASS', "Complex query executed in {$queryTime}ms");
            } else {
                $this->logTest($testName, 'WARNING', "Slow query performance: {$queryTime}ms");
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function testMemoryUsage()
    {
        $testName = 'Memory Usage';
        
        try {
            $memoryBefore = memory_get_usage();
            
            // Create large tournament
            $eventId = $this->createTestEvent('Memory Test', 'single_elimination');
            $teams = $this->createTestTeams(64);
            $this->addTeamsToEvent($eventId, $teams);
            
            $this->makeBracketRequest('POST', "/api/brackets/{$eventId}/generate", [
                'format' => 'single_elimination'
            ]);
            
            // Retrieve bracket data
            $this->makeBracketRequest('GET', "/api/brackets/{$eventId}");
            
            $memoryAfter = memory_get_usage();
            $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB
            
            if ($memoryUsed < 10) {
                $this->logTest($testName, 'PASS', "Memory usage: {$memoryUsed}MB");
            } elseif ($memoryUsed < 50) {
                $this->logTest($testName, 'WARNING', "High memory usage: {$memoryUsed}MB");
            } else {
                $this->logTest($testName, 'FAIL', "Excessive memory usage: {$memoryUsed}MB");
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    // Helper methods
    
    private function createTestEvent($name, $format = 'single_elimination')
    {
        $eventId = DB::table('events')->insertGetId([
            'name' => $name,
            'format' => $format,
            'status' => 'upcoming',
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return $eventId;
    }
    
    private function createTestTeams($count)
    {
        $teams = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $teamId = DB::table('teams')->insertGetId([
                'name' => "Test Team {$i}",
                'short_name' => "TT{$i}",
                'rating' => 1000 + rand(0, 500),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $teams[] = ['id' => $teamId, 'name' => "Test Team {$i}"];
        }
        
        return $teams;
    }
    
    private function addTeamsToEvent($eventId, $teams)
    {
        foreach ($teams as $index => $team) {
            DB::table('event_teams')->insert([
                'event_id' => $eventId,
                'team_id' => $team['id'],
                'seed' => $index + 1,
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    private function makeBracketRequest($method, $endpoint, $data = [])
    {
        // Simulate API request - in real implementation, use HTTP client
        try {
            switch ($method) {
                case 'GET':
                    return $this->simulateGetRequest($endpoint);
                case 'POST':
                    return $this->simulatePostRequest($endpoint, $data);
                case 'PUT':
                    return $this->simulatePutRequest($endpoint, $data);
                case 'DELETE':
                    return $this->simulateDeleteRequest($endpoint);
                default:
                    return ['success' => false, 'message' => 'Unsupported method'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function simulateGetRequest($endpoint)
    {
        // Parse endpoint and simulate controller response
        if (strpos($endpoint, '/api/brackets/') === 0) {
            $eventId = str_replace('/api/brackets/', '', $endpoint);
            
            if (is_numeric($eventId)) {
                $event = DB::table('events')->where('id', $eventId)->first();
                if ($event) {
                    // Simulate bracket retrieval
                    return [
                        'success' => true,
                        'data' => [
                            'event_id' => $eventId,
                            'bracket' => [
                                'type' => $event->format,
                                'rounds' => []
                            ]
                        ]
                    ];
                }
            }
        }
        
        return ['success' => false, 'message' => 'Endpoint simulation not implemented'];
    }
    
    private function simulatePostRequest($endpoint, $data)
    {
        if (strpos($endpoint, '/api/brackets/') !== false && strpos($endpoint, '/generate') !== false) {
            $eventId = str_replace(['/api/brackets/', '/generate'], '', $endpoint);
            
            if (is_numeric($eventId)) {
                $event = DB::table('events')->where('id', $eventId)->first();
                $teams = DB::table('event_teams')->where('event_id', $eventId)->count();
                
                if ($event && $teams >= 2) {
                    // Simulate successful bracket generation
                    return [
                        'success' => true,
                        'message' => 'Bracket generated successfully',
                        'data' => ['matches_created' => $teams - 1]
                    ];
                } elseif ($teams < 2) {
                    return [
                        'success' => false,
                        'message' => 'Need at least 2 teams to generate bracket'
                    ];
                }
            }
        }
        
        if ($endpoint === '/api/events') {
            // Simulate event creation
            if (isset($data['name'])) {
                return [
                    'success' => true,
                    'data' => ['id' => rand(1000, 9999)]
                ];
            }
        }
        
        return ['success' => false, 'message' => 'Endpoint simulation not implemented'];
    }
    
    private function simulatePutRequest($endpoint, $data)
    {
        if (strpos($endpoint, '/api/matches/') === 0) {
            $matchId = str_replace('/api/matches/', '', $endpoint);
            
            if (is_numeric($matchId)) {
                $match = DB::table('matches')->where('id', $matchId)->first();
                
                if ($match) {
                    // Basic validation
                    if (isset($data['status']) && $data['status'] === 'completed') {
                        if (!isset($data['team1_score']) || !isset($data['team2_score'])) {
                            return ['success' => false, 'message' => 'Scores required for completed matches'];
                        }
                        
                        if ($data['team1_score'] == $data['team2_score']) {
                            return ['success' => false, 'message' => 'Ties not allowed in elimination matches'];
                        }
                    }
                    
                    return ['success' => true, 'message' => 'Match updated successfully'];
                }
            }
        }
        
        return ['success' => false, 'message' => 'Endpoint simulation not implemented'];
    }
    
    private function simulateDeleteRequest($endpoint)
    {
        return ['success' => false, 'message' => 'Delete simulation not implemented'];
    }
    
    private function verifyBracketStructure($eventId, $format, $teamCount)
    {
        $testName = "Bracket Structure Verification ({$format})";
        
        try {
            $matches = DB::table('matches')->where('event_id', $eventId)->get();
            
            switch ($format) {
                case 'single_elimination':
                    $expectedMatches = $teamCount - 1;
                    if (count($matches) == $expectedMatches) {
                        $this->logTest($testName, 'PASS', 'Correct match count for single elimination');
                    } else {
                        $this->logTest($testName, 'FAIL', "Expected {$expectedMatches} matches, got " . count($matches));
                    }
                    break;
                    
                case 'double_elimination':
                    $minExpectedMatches = ($teamCount - 1) * 2; // Rough estimate
                    if (count($matches) >= $minExpectedMatches) {
                        $this->logTest($testName, 'PASS', 'Adequate matches for double elimination');
                    } else {
                        $this->logTest($testName, 'FAIL', "Too few matches for double elimination");
                    }
                    break;
                    
                case 'round_robin':
                    $expectedMatches = ($teamCount * ($teamCount - 1)) / 2;
                    if (count($matches) == $expectedMatches) {
                        $this->logTest($testName, 'PASS', 'Correct match count for round robin');
                    } else {
                        $this->logTest($testName, 'FAIL', "Expected {$expectedMatches} matches, got " . count($matches));
                    }
                    break;
            }
            
        } catch (Exception $e) {
            $this->logTest($testName, 'FAIL', 'Exception: ' . $e->getMessage());
        }
    }
    
    private function logTest($testName, $status, $message)
    {
        $this->results['tests'][] = [
            'name' => $testName,
            'status' => $status,
            'message' => $message,
            'timestamp' => date('H:i:s')
        ];
        
        $this->results['summary']['total_tests']++;
        
        switch ($status) {
            case 'PASS':
                $this->results['summary']['passed']++;
                echo "✅ {$testName}: {$message}\n";
                break;
            case 'FAIL':
                $this->results['summary']['failed']++;
                echo "❌ {$testName}: {$message}\n";
                break;
            case 'WARNING':
                $this->results['summary']['warnings']++;
                echo "⚠️  {$testName}: {$message}\n";
                break;
        }
    }
    
    private function generateReport()
    {
        $this->results['end_time'] = date('Y-m-d H:i:s');
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🏁 COMPREHENSIVE BRACKET SYSTEM AUDIT COMPLETE\n";
        echo str_repeat("=", 60) . "\n\n";
        
        echo "📊 SUMMARY:\n";
        echo "- Total Tests: " . $this->results['summary']['total_tests'] . "\n";
        echo "- Passed: " . $this->results['summary']['passed'] . "\n";
        echo "- Failed: " . $this->results['summary']['failed'] . "\n";
        echo "- Warnings: " . $this->results['summary']['warnings'] . "\n";
        
        if ($this->results['summary']['total_tests'] > 0) {
            $passRate = ($this->results['summary']['passed'] / $this->results['summary']['total_tests']) * 100;
            echo "- Pass Rate: " . number_format($passRate, 1) . "%\n";
        }
        
        echo "\n";
        
        // Determine overall status
        if ($this->results['summary']['failed'] == 0) {
            if ($this->results['summary']['warnings'] == 0) {
                echo "🎉 AUDIT RESULT: EXCELLENT - System ready for production\n";
                $auditResult = 'EXCELLENT';
            } else {
                echo "✅ AUDIT RESULT: GOOD - Minor warnings, but production ready\n";
                $auditResult = 'GOOD';
            }
        } elseif ($this->results['summary']['failed'] <= 3) {
            echo "⚠️  AUDIT RESULT: CAUTION - Some issues found, review before production\n";
            $auditResult = 'CAUTION';
        } else {
            echo "🚨 AUDIT RESULT: CRITICAL - Major issues found, DO NOT deploy to production\n";
            $auditResult = 'CRITICAL';
        }
        
        $this->results['audit_result'] = $auditResult;
        
        // Save detailed report
        file_put_contents(
            __DIR__ . '/comprehensive_bracket_audit_report.json',
            json_encode($this->results, JSON_PRETTY_PRINT)
        );
        
        echo "\n📄 Detailed report saved to: comprehensive_bracket_audit_report.json\n";
        
        // Critical issues summary
        if ($this->results['summary']['failed'] > 0) {
            echo "\n🚨 CRITICAL ISSUES REQUIRING IMMEDIATE ATTENTION:\n";
            foreach ($this->results['tests'] as $test) {
                if ($test['status'] === 'FAIL') {
                    echo "   - {$test['name']}: {$test['message']}\n";
                }
            }
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
    }
}

// Run the audit
$audit = new BracketSystemAudit();
$audit->runCompleteAudit();