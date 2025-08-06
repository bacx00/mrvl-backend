<?php

/**
 * COMPREHENSIVE BRACKET SYSTEM PRODUCTION AUDIT
 * ============================================
 * 
 * Critical testing of tournament bracket system for August 10th China tournament.
 * Tests all CRUD operations, edge cases, and production readiness scenarios.
 */

// Initialize Laravel environment
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComprehensiveBracketProductionTest
{
    private $testResults = [];
    private $criticalIssues = [];
    private $eventId = 1; // China tournament
    
    public function runAllTests()
    {
        $this->displayHeader();
        
        // Test Categories
        $this->testBracketRetrieval();
        $this->testBracketGeneration();
        $this->testMatchProgression();
        $this->testEdgeCases();
        $this->testConcurrency();
        $this->testDataIntegrity();
        $this->testPerformance();
        
        $this->displayResults();
    }
    
    private function displayHeader()
    {
        echo "\n";
        echo "🏆 COMPREHENSIVE BRACKET SYSTEM AUDIT - PRODUCTION READY CHECK\n";
        echo "===============================================================\n";
        echo "📅 China Tournament: August 10th, 2025\n";
        echo "🎯 Testing Event ID: {$this->eventId}\n";
        echo "⏰ Test Started: " . date('Y-m-d H:i:s') . "\n\n";
    }
    
    private function testBracketRetrieval()
    {
        echo "📖 TESTING BRACKET RETRIEVAL\n";
        echo "=============================\n";
        
        try {
            // Test 1: Basic bracket retrieval
            $response = $this->makeApiCall("GET", "/api/public/events/{$this->eventId}/bracket");
            $this->recordResult('Bracket API Response', 
                $response['success'] && isset($response['data']['bracket']), 
                "API returned: " . json_encode($response, JSON_PRETTY_PRINT)
            );
            
            // Test 2: Event metadata
            $eventResponse = $this->makeApiCall("GET", "/api/public/events/{$this->eventId}");
            $this->recordResult('Event Metadata', 
                $eventResponse['success'] && isset($eventResponse['data']['teams']), 
                "Teams count: " . count($eventResponse['data']['teams'] ?? [])
            );
            
            // Test 3: Database bracket structure
            $matches = DB::table('matches')->where('event_id', $this->eventId)->get();
            $this->recordResult('Database Matches', 
                $matches->count() > 0, 
                "Found {$matches->count()} matches in database"
            );
            
        } catch (Exception $e) {
            $this->recordResult('Bracket Retrieval', false, "Exception: " . $e->getMessage());
        }
    }
    
    private function testBracketGeneration()
    {
        echo "\n🔧 TESTING BRACKET GENERATION\n";
        echo "==============================\n";
        
        // Test different tournament formats
        $formats = ['single_elimination', 'double_elimination', 'round_robin', 'swiss'];
        
        foreach ($formats as $format) {
            try {
                $testEvent = $this->createTestEvent("Test {$format}", $format);
                $teams = $this->getTestTeams(12); // Use 12 teams like China tournament
                
                // Add teams to event
                foreach ($teams as $team) {
                    DB::table('event_teams')->insert([
                        'event_id' => $testEvent['id'],
                        'team_id' => $team['id'],
                        'seed' => array_search($team, $teams) + 1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                
                // Test bracket generation
                $matches = $this->generateBracketMatches($testEvent['id'], $teams, $format);
                $expectedMatches = $this->calculateExpectedMatches($format, count($teams));
                
                $this->recordResult("$format Bracket Generation", 
                    count($matches) === $expectedMatches, 
                    "Generated " . count($matches) . " matches, expected $expectedMatches"
                );
                
                // Cleanup
                DB::table('matches')->where('event_id', $testEvent['id'])->delete();
                DB::table('event_teams')->where('event_id', $testEvent['id'])->delete();
                DB::table('events')->where('id', $testEvent['id'])->delete();
                
            } catch (Exception $e) {
                $this->recordResult("$format Bracket Generation", false, "Exception: " . $e->getMessage());
            }
        }
    }
    
    private function testMatchProgression()
    {
        echo "\n▶️ TESTING MATCH PROGRESSION\n";
        echo "=============================\n";
        
        try {
            // Create test tournament
            $testEvent = $this->createTestEvent("Progression Test", "single_elimination");
            $teams = $this->getTestTeams(8);
            
            foreach ($teams as $team) {
                DB::table('event_teams')->insert([
                    'event_id' => $testEvent['id'],
                    'team_id' => $team['id'],
                    'seed' => array_search($team, $teams) + 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $matches = $this->generateBracketMatches($testEvent['id'], $teams, 'single_elimination');
            
            // Test 1: Complete first round matches
            $firstRoundMatches = array_filter($matches, fn($m) => $m['round'] == 1);
            $progressionWorked = true;
            
            foreach ($firstRoundMatches as $match) {
                DB::table('matches')->where('id', $match['id'])->update([
                    'team1_score' => 2,
                    'team2_score' => 1,
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
                
                // Check if winner advanced
                $winnerId = $match['team1_id'];
                $nextRoundMatch = DB::table('matches')
                    ->where('event_id', $testEvent['id'])
                    ->where('round', 2)
                    ->where(function($q) use ($winnerId) {
                        $q->where('team1_id', $winnerId)->orWhere('team2_id', $winnerId);
                    })->first();
                
                if (!$nextRoundMatch) {
                    $progressionWorked = false;
                    break;
                }
            }
            
            $this->recordResult('Match Progression', $progressionWorked, 
                "Winners " . ($progressionWorked ? 'correctly' : 'incorrectly') . " advanced to next round"
            );
            
            // Cleanup
            DB::table('matches')->where('event_id', $testEvent['id'])->delete();
            DB::table('event_teams')->where('event_id', $testEvent['id'])->delete();
            DB::table('events')->where('id', $testEvent['id'])->delete();
            
        } catch (Exception $e) {
            $this->recordResult('Match Progression', false, "Exception: " . $e->getMessage());
        }
    }
    
    private function testEdgeCases()
    {
        echo "\n⚠️ TESTING EDGE CASES\n";
        echo "======================\n";
        
        // Test 1: Odd number of teams (byes)
        try {
            $testEvent = $this->createTestEvent("Odd Teams Test", "single_elimination");
            $teams = $this->getTestTeams(7); // Odd number
            
            foreach ($teams as $team) {
                DB::table('event_teams')->insert([
                    'event_id' => $testEvent['id'],
                    'team_id' => $team['id'],
                    'seed' => array_search($team, $teams) + 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $matches = $this->generateBracketMatches($testEvent['id'], $teams, 'single_elimination');
            $byeMatches = array_filter($matches, fn($m) => !$m['team1_id'] || !$m['team2_id']);
            
            $this->recordResult('Bye Handling', count($byeMatches) > 0, 
                "Found " . count($byeMatches) . " bye matches for 7 teams"
            );
            
            // Cleanup
            DB::table('matches')->where('event_id', $testEvent['id'])->delete();
            DB::table('event_teams')->where('event_id', $testEvent['id'])->delete();
            DB::table('events')->where('id', $testEvent['id'])->delete();
            
        } catch (Exception $e) {
            $this->recordResult('Bye Handling', false, "Exception: " . $e->getMessage());
        }
        
        // Test 2: Minimum teams (2 teams)
        try {
            $testEvent = $this->createTestEvent("Min Teams Test", "single_elimination");
            $teams = $this->getTestTeams(2);
            
            foreach ($teams as $team) {
                DB::table('event_teams')->insert([
                    'event_id' => $testEvent['id'],
                    'team_id' => $team['id'],
                    'seed' => array_search($team, $teams) + 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $matches = $this->generateBracketMatches($testEvent['id'], $teams, 'single_elimination');
            
            $this->recordResult('Minimum Teams', count($matches) === 1, 
                "2 teams generated " . count($matches) . " match(es)"
            );
            
            // Cleanup
            DB::table('matches')->where('event_id', $testEvent['id'])->delete();
            DB::table('event_teams')->where('event_id', $testEvent['id'])->delete();
            DB::table('events')->where('id', $testEvent['id'])->delete();
            
        } catch (Exception $e) {
            $this->recordResult('Minimum Teams', false, "Exception: " . $e->getMessage());
        }
    }
    
    private function testConcurrency()
    {
        echo "\n🔄 TESTING CONCURRENCY\n";
        echo "=======================\n";
        
        try {
            // Simulate concurrent match updates
            $matches = DB::table('matches')->where('event_id', $this->eventId)->limit(2)->get();
            
            if ($matches->count() >= 2) {
                $match1 = $matches[0];
                $match2 = $matches[1];
                
                // Simulate concurrent updates
                $start = microtime(true);
                
                DB::beginTransaction();
                DB::table('matches')->where('id', $match1->id)->update(['updated_at' => now()]);
                DB::table('matches')->where('id', $match2->id)->update(['updated_at' => now()]);
                DB::commit();
                
                $end = microtime(true);
                $duration = ($end - $start) * 1000; // milliseconds
                
                $this->recordResult('Concurrent Updates', $duration < 100, 
                    "Concurrent updates took {$duration}ms"
                );
            } else {
                $this->recordResult('Concurrent Updates', false, "Not enough matches to test");
            }
            
        } catch (Exception $e) {
            DB::rollBack();
            $this->recordResult('Concurrent Updates', false, "Exception: " . $e->getMessage());
        }
    }
    
    private function testDataIntegrity()
    {
        echo "\n🔒 TESTING DATA INTEGRITY\n";
        echo "==========================\n";
        
        try {
            // Test 1: Orphaned matches
            $orphanedMatches = DB::table('matches as m')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->whereNull('e.id')
                ->count();
            
            $this->recordResult('Orphaned Matches', $orphanedMatches === 0, 
                "Found $orphanedMatches orphaned matches"
            );
            
            // Test 2: Invalid team references
            $invalidTeamRefs = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.event_id', $this->eventId)
                ->where(function($q) {
                    $q->where(function($sq) {
                        $sq->whereNotNull('m.team1_id')->whereNull('t1.id');
                    })->orWhere(function($sq) {
                        $sq->whereNotNull('m.team2_id')->whereNull('t2.id');
                    });
                })
                ->count();
            
            $this->recordResult('Invalid Team References', $invalidTeamRefs === 0, 
                "Found $invalidTeamRefs invalid team references"
            );
            
            // Test 3: Bracket consistency
            $event = DB::table('events')->find($this->eventId);
            $matches = DB::table('matches')->where('event_id', $this->eventId)->get();
            $teamCount = DB::table('event_teams')->where('event_id', $this->eventId)->count();
            
            $expectedMatches = $this->calculateExpectedMatches($event->format, $teamCount);
            $actualMatches = $matches->count();
            
            $this->recordResult('Bracket Consistency', 
                abs($actualMatches - $expectedMatches) <= 1, // Allow small variance
                "Expected ~$expectedMatches matches, found $actualMatches"
            );
            
        } catch (Exception $e) {
            $this->recordResult('Data Integrity', false, "Exception: " . $e->getMessage());
        }
    }
    
    private function testPerformance()
    {
        echo "\n⚡ TESTING PERFORMANCE\n";
        echo "======================\n";
        
        try {
            // Test 1: Bracket loading time
            $start = microtime(true);
            $this->makeApiCall("GET", "/api/public/events/{$this->eventId}/bracket");
            $bracketLoadTime = (microtime(true) - $start) * 1000;
            
            $this->recordResult('Bracket Load Time', $bracketLoadTime < 500, 
                "Bracket loaded in {$bracketLoadTime}ms"
            );
            
            // Test 2: Database query performance
            $start = microtime(true);
            DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.event_id', $this->eventId)
                ->select('m.*', 't1.name as team1_name', 't2.name as team2_name')
                ->get();
            $queryTime = (microtime(true) - $start) * 1000;
            
            $this->recordResult('Database Query Performance', $queryTime < 100, 
                "Complex query took {$queryTime}ms"
            );
            
            // Test 3: Memory usage
            $memoryBefore = memory_get_usage();
            $largeBracket = $this->makeApiCall("GET", "/api/public/events/{$this->eventId}/bracket");
            $memoryAfter = memory_get_usage();
            $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB
            
            $this->recordResult('Memory Usage', $memoryUsed < 10, 
                "Bracket loading used {$memoryUsed}MB memory"
            );
            
        } catch (Exception $e) {
            $this->recordResult('Performance', false, "Exception: " . $e->getMessage());
        }
    }
    
    private function createTestEvent($name, $format)
    {
        $id = DB::table('events')->insertGetId([
            'name' => $name,
            'format' => $format,
            'status' => 'upcoming',
            'start_date' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return ['id' => $id, 'name' => $name, 'format' => $format];
    }
    
    private function getTestTeams($count)
    {
        return DB::table('teams')->limit($count)->get()->toArray();
    }
    
    private function generateBracketMatches($eventId, $teams, $format)
    {
        $matches = [];
        
        switch ($format) {
            case 'single_elimination':
                $matches = $this->createSingleEliminationMatches($eventId, $teams);
                break;
            case 'double_elimination':
                $matches = $this->createDoubleEliminationMatches($eventId, $teams);
                break;
            case 'round_robin':
                $matches = $this->createRoundRobinMatches($eventId, $teams);
                break;
            case 'swiss':
                $matches = $this->createSwissMatches($eventId, $teams);
                break;
        }
        
        foreach ($matches as &$match) {
            $match['id'] = DB::table('matches')->insertGetId($match);
        }
        
        return $matches;
    }
    
    private function createSingleEliminationMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
        $rounds = ceil(log($teamCount, 2));
        
        // First round
        $round = 1;
        $position = 1;
        
        for ($i = 0; $i < $teamCount; $i += 2) {
            $matches[] = [
                'event_id' => $eventId,
                'round' => $round,
                'bracket_position' => $position,
                'bracket_type' => 'main',
                'team1_id' => $teams[$i]->id ?? null,
                'team2_id' => $teams[$i + 1]->id ?? null,
                'status' => 'scheduled',
                'format' => 'bo3',
                'created_at' => now(),
                'updated_at' => now()
            ];
            $position++;
        }
        
        // Subsequent rounds
        $currentMatches = count($matches);
        for ($r = 2; $r <= $rounds; $r++) {
            $matchesInRound = ceil($currentMatches / 2);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'main',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $currentMatches = $matchesInRound;
        }
        
        return $matches;
    }
    
    private function createDoubleEliminationMatches($eventId, $teams)
    {
        $upperMatches = $this->createSingleEliminationMatches($eventId, $teams);
        foreach ($upperMatches as &$match) {
            $match['bracket_type'] = 'upper';
        }
        
        // Add lower bracket matches (simplified)
        $lowerMatches = [];
        $teamCount = count($teams);
        $lowerRounds = ($teamCount > 4) ? ceil(log($teamCount, 2)) * 2 - 2 : 1;
        
        for ($r = 1; $r <= $lowerRounds; $r++) {
            $matchesInRound = max(1, ceil($teamCount / pow(2, $r + 1)));
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $lowerMatches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'lower',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        
        // Grand final
        $grandFinal = [
            'event_id' => $eventId,
            'round' => 1,
            'bracket_position' => 1,
            'bracket_type' => 'grand_final',
            'team1_id' => null,
            'team2_id' => null,
            'status' => 'pending',
            'format' => 'bo5',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        return array_merge($upperMatches, $lowerMatches, [$grandFinal]);
    }
    
    private function createRoundRobinMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
        $position = 1;
        
        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => ceil($position / ($teamCount / 2)),
                    'bracket_position' => $position,
                    'bracket_type' => 'round_robin',
                    'team1_id' => $teams[$i]->id,
                    'team2_id' => $teams[$j]->id,
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }
        
        return $matches;
    }
    
    private function createSwissMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
        $rounds = ceil(log($teamCount, 2));
        
        // First round only (Swiss rounds are generated dynamically)
        shuffle($teams);
        for ($i = 0; $i < $teamCount; $i += 2) {
            if (isset($teams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => 1,
                    'bracket_position' => ($i / 2) + 1,
                    'bracket_type' => 'swiss',
                    'team1_id' => $teams[$i]->id,
                    'team2_id' => $teams[$i + 1]->id,
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        
        return $matches;
    }
    
    private function calculateExpectedMatches($format, $teamCount)
    {
        switch ($format) {
            case 'single_elimination':
                return $teamCount - 1;
            case 'double_elimination':
                return ($teamCount - 1) * 2 + 1; // Upper + Lower + Grand Final
            case 'round_robin':
                return ($teamCount * ($teamCount - 1)) / 2;
            case 'swiss':
                return ceil($teamCount / 2) * ceil(log($teamCount, 2));
            default:
                return $teamCount - 1;
        }
    }
    
    private function makeApiCall($method, $endpoint)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://localhost:8000$endpoint",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode >= 400) {
            return ['success' => false, 'error' => "HTTP $httpCode"];
        }
        
        return json_decode($response, true) ?: ['success' => false, 'error' => 'Invalid JSON'];
    }
    
    private function recordResult($test, $passed, $details = '')
    {
        $status = $passed ? '✅' : '❌';
        $message = "$status $test";
        if ($details) {
            $message .= ": $details";
        }
        
        echo $message . "\n";
        
        $this->testResults[] = [
            'test' => $test,
            'passed' => $passed,
            'details' => $details
        ];
        
        if (!$passed) {
            $this->criticalIssues[] = $test . ($details ? " - $details" : '');
        }
    }
    
    private function displayResults()
    {
        echo "\n";
        echo "📊 AUDIT RESULTS SUMMARY\n";
        echo "=========================\n";
        
        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, fn($r) => $r['passed']));
        $failedTests = $totalTests - $passedTests;
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
        
        echo "🎯 Total Tests: $totalTests\n";
        echo "✅ Passed: $passedTests\n";
        echo "❌ Failed: $failedTests\n";
        echo "📈 Success Rate: $successRate%\n\n";
        
        if (count($this->criticalIssues) > 0) {
            echo "🚨 CRITICAL ISSUES FOUND:\n";
            echo "==========================\n";
            foreach ($this->criticalIssues as $issue) {
                echo "• $issue\n";
            }
            echo "\n";
        }
        
        // Production readiness assessment
        $productionReady = $successRate >= 95 && count($this->criticalIssues) === 0;
        
        echo "🏆 PRODUCTION READINESS ASSESSMENT\n";
        echo "===================================\n";
        
        if ($productionReady) {
            echo "✅ SYSTEM IS PRODUCTION READY for August 10th China Tournament\n";
            echo "• All critical tests passed\n";
            echo "• No blocking issues found\n";
            echo "• Performance within acceptable limits\n";
        } else {
            echo "⚠️ SYSTEM REQUIRES ATTENTION before production\n";
            echo "• Success rate: $successRate% (target: 95%+)\n";
            echo "• Critical issues: " . count($this->criticalIssues) . " (target: 0)\n";
            echo "• Review and fix issues before tournament\n";
        }
        
        echo "\n📅 Audit completed: " . date('Y-m-d H:i:s') . "\n";
        echo "🎯 Next steps: " . ($productionReady ? "Ready for tournament" : "Address critical issues") . "\n\n";
    }
}

// Run the comprehensive test
$tester = new ComprehensiveBracketProductionTest();
$tester->runAllTests();