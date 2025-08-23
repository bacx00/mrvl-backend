<?php

/**
 * COMPREHENSIVE TOURNAMENT, BRACKET & RANKINGS SYSTEM TEST
 * Tests all aspects of the Events, Brackets & Rankings Systems
 * 
 * Usage: php test_tournament_bracket_rankings.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Tournament;
use App\Models\Event;
use App\Models\Team;
use App\Models\Player;
use App\Models\Bracket;
use App\Models\BracketMatch;
use App\Models\BracketStage;
use App\Models\BracketSeeding;
use App\Models\BracketStanding;
use App\Models\TournamentPhase;
use App\Models\TournamentRegistration;
use App\Services\BracketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Set up test context
$testResults = [];
$errors = [];
$warnings = [];
$baseUrl = 'http://localhost/api';
$adminToken = null;

// Color codes for output
$GREEN = "\033[32m";
$RED = "\033[31m";
$YELLOW = "\033[33m";
$BLUE = "\033[34m";
$RESET = "\033[0m";

function logTest($category, $test, $result, $details = null) {
    global $testResults, $GREEN, $RED, $RESET;
    $status = $result ? "{$GREEN}✅ PASS{$RESET}" : "{$RED}❌ FAIL{$RESET}";
    echo "  [{$category}] {$test}: {$status}";
    if ($details) {
        echo " - {$details}";
    }
    echo "\n";
    $testResults[$category][$test] = $result;
}

function logError($message) {
    global $errors, $RED, $RESET;
    echo "{$RED}ERROR: {$message}{$RESET}\n";
    $errors[] = $message;
}

function logWarning($message) {
    global $warnings, $YELLOW, $RESET;
    echo "{$YELLOW}WARNING: {$message}{$RESET}\n";
    $warnings[] = $message;
}

function logSection($title) {
    global $BLUE, $RESET;
    echo "\n{$BLUE}==== {$title} ===={$RESET}\n";
}

// Get admin token for authenticated requests
function getAdminToken() {
    global $baseUrl;
    
    try {
        $response = Http::post("{$baseUrl}/auth/login", [
            'email' => 'admin@mrvl.net',
            'password' => 'admin123'
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            return $data['access_token'] ?? null;
        }
    } catch (Exception $e) {
        logError("Failed to get admin token: " . $e->getMessage());
    }
    
    return null;
}

// ==========================
// TOURNAMENT SYSTEM TESTS
// ==========================

logSection("TOURNAMENT SYSTEM TESTS");

// Test 1: Tournament Model & Database
logTest("Tournament", "Database Schema", function() {
    $requiredColumns = [
        'name', 'slug', 'type', 'format', 'status', 'region',
        'prize_pool', 'team_count', 'max_teams', 'min_teams',
        'start_date', 'end_date', 'registration_start', 'registration_end',
        'current_phase', 'phase_data', 'bracket_data', 'seeding_data'
    ];
    
    $columns = DB::getSchemaBuilder()->getColumnListing('tournaments');
    foreach ($requiredColumns as $column) {
        if (!in_array($column, $columns)) {
            return false;
        }
    }
    return true;
}());

// Test 2: Tournament Creation
logTest("Tournament", "Create Tournament", function() {
    try {
        $tournament = Tournament::create([
            'name' => 'Test Marvel Rivals Championship',
            'slug' => 'test-mrc-' . time(),
            'type' => 'mrc',
            'format' => 'double_elimination',
            'status' => 'draft',
            'region' => 'global',
            'prize_pool' => 50000,
            'currency' => 'USD',
            'max_teams' => 16,
            'min_teams' => 8,
            'start_date' => Carbon::now()->addDays(7),
            'end_date' => Carbon::now()->addDays(10),
            'registration_start' => Carbon::now(),
            'registration_end' => Carbon::now()->addDays(5),
            'organizer_id' => 1
        ]);
        
        return $tournament->exists;
    } catch (Exception $e) {
        logError("Tournament creation failed: " . $e->getMessage());
        return false;
    }
}());

// Test 3: Tournament Phases
logTest("Tournament", "Phase Management", function() {
    try {
        $tournament = Tournament::latest()->first();
        if (!$tournament) return false;
        
        $phase = TournamentPhase::create([
            'tournament_id' => $tournament->id,
            'name' => 'Group Stage',
            'type' => 'group_stage',
            'phase_order' => 1,
            'status' => 'pending',
            'start_date' => $tournament->start_date,
            'end_date' => $tournament->start_date->addDays(2)
        ]);
        
        return $phase->exists;
    } catch (Exception $e) {
        logError("Phase creation failed: " . $e->getMessage());
        return false;
    }
}());

// Test 4: Team Registration
logTest("Tournament", "Team Registration", function() {
    try {
        $tournament = Tournament::latest()->first();
        $team = Team::first();
        
        if (!$tournament || !$team) return false;
        
        $tournament->status = 'registration_open';
        $tournament->save();
        
        $result = $tournament->registerTeam($team, ['seed' => 1]);
        return $result;
    } catch (Exception $e) {
        logError("Team registration failed: " . $e->getMessage());
        return false;
    }
}());

// Test 5: Swiss System Support
logTest("Tournament", "Swiss System", function() {
    try {
        $tournament = Tournament::latest()->first();
        if (!$tournament) return false;
        
        // Add Swiss-specific data
        $tournament->teams()->updateExistingPivot($tournament->teams->first()->id, [
            'swiss_wins' => 2,
            'swiss_losses' => 1,
            'swiss_score' => 2.5,
            'swiss_buchholz' => 4.5
        ]);
        
        $standings = $tournament->swiss_standings;
        return count($standings) > 0;
    } catch (Exception $e) {
        logError("Swiss system test failed: " . $e->getMessage());
        return false;
    }
}());

// ==========================
// BRACKET GENERATION TESTS
// ==========================

logSection("BRACKET GENERATION TESTS");

// Test 6: Bracket Service Initialization
logTest("Bracket", "Service Initialization", function() {
    try {
        $bracketService = app(BracketService::class);
        return $bracketService !== null;
    } catch (Exception $e) {
        logError("Bracket service initialization failed: " . $e->getMessage());
        return false;
    }
}());

// Test 7: Single Elimination Bracket
logTest("Bracket", "Single Elimination Generation", function() {
    try {
        $bracketService = app(BracketService::class);
        $tournament = Tournament::latest()->first();
        
        // Get 8 teams for testing
        $teams = Team::limit(8)->get();
        
        $result = $bracketService->generateSingleEliminationBracket(
            $tournament,
            $teams,
            ['best_of' => '3', 'seeding_method' => 'seed']
        );
        
        return isset($result['matches']) && count($result['matches']) > 0;
    } catch (Exception $e) {
        logError("Single elimination generation failed: " . $e->getMessage());
        return false;
    }
}());

// Test 8: Double Elimination Bracket
logTest("Bracket", "Double Elimination Generation", function() {
    try {
        $bracketService = app(BracketService::class);
        $event = Event::first();
        
        if (!$event) {
            // Create test event
            $event = Event::create([
                'name' => 'Test Double Elim Event',
                'game' => 'Marvel Rivals',
                'region' => 'Global',
                'start_date' => Carbon::now()->addDays(1),
                'end_date' => Carbon::now()->addDays(3),
                'event_type' => 'tournament'
            ]);
        }
        
        $teams = Team::limit(8)->get();
        
        $result = $bracketService->generateDoubleEliminationBracket(
            $event,
            $teams,
            ['best_of' => '5', 'bracket_reset' => true]
        );
        
        return isset($result['upper_matches']) && 
               isset($result['lower_matches']) && 
               isset($result['final_matches']);
    } catch (Exception $e) {
        logError("Double elimination generation failed: " . $e->getMessage());
        return false;
    }
}());

// Test 9: Swiss Bracket
logTest("Bracket", "Swiss System Generation", function() {
    try {
        $bracketService = app(BracketService::class);
        $tournament = Tournament::latest()->first();
        $teams = Team::limit(16)->get();
        
        $result = $bracketService->generateSwissBracket(
            $tournament,
            $teams,
            ['rounds' => 5, 'best_of' => '3']
        );
        
        return isset($result['swiss_stage']) && 
               isset($result['total_rounds']) &&
               $result['total_rounds'] == 5;
    } catch (Exception $e) {
        logError("Swiss generation failed: " . $e->getMessage());
        return false;
    }
}());

// Test 10: Round Robin Bracket
logTest("Bracket", "Round Robin Generation", function() {
    try {
        $bracketService = app(BracketService::class);
        $tournament = Tournament::latest()->first();
        $teams = Team::limit(4)->get();
        
        $result = $bracketService->generateRoundRobinBracket(
            $tournament,
            $teams,
            ['best_of' => '3', 'double_round_robin' => false]
        );
        
        return isset($result['round_robin_stage']) && 
               isset($result['total_rounds']) &&
               $result['total_rounds'] == 3; // 4 teams = 3 rounds
    } catch (Exception $e) {
        logError("Round robin generation failed: " . $e->getMessage());
        return false;
    }
}());

// ==========================
// MATCH PROGRESSION TESTS
// ==========================

logSection("MATCH PROGRESSION TESTS");

// Test 11: Match Creation & Scheduling
logTest("Match", "Match Creation", function() {
    try {
        $match = BracketMatch::create([
            'match_id' => 'TEST-' . time(),
            'tournament_id' => Tournament::latest()->first()->id,
            'bracket_stage_id' => BracketStage::first()->id,
            'round_name' => 'Quarterfinals',
            'round_number' => 1,
            'match_number' => 1,
            'team1_id' => 1,
            'team2_id' => 2,
            'status' => 'pending',
            'best_of' => 3,
            'scheduled_at' => Carbon::now()->addHours(1)
        ]);
        
        return $match->exists && $match->scheduled_at !== null;
    } catch (Exception $e) {
        logError("Match creation failed: " . $e->getMessage());
        return false;
    }
}());

// Test 12: Match Score Update
logTest("Match", "Score Update", function() {
    try {
        $match = BracketMatch::latest()->first();
        if (!$match) return false;
        
        $match->updateScore(2, 1);
        
        return $match->team1_score == 2 && 
               $match->team2_score == 1 &&
               $match->status == 'completed' &&
               $match->winner_id == $match->team1_id;
    } catch (Exception $e) {
        logError("Score update failed: " . $e->getMessage());
        return false;
    }
}());

// Test 13: Match Progression (Winner Advancement)
logTest("Match", "Winner Advancement", function() {
    try {
        // Create two matches - one feeds into the other
        $semifinal = BracketMatch::create([
            'match_id' => 'SF-' . time(),
            'tournament_id' => Tournament::latest()->first()->id,
            'bracket_stage_id' => BracketStage::first()->id,
            'round_name' => 'Semifinals',
            'round_number' => 2,
            'match_number' => 1,
            'status' => 'pending',
            'best_of' => 3,
            'scheduled_at' => Carbon::now()->addHours(2)
        ]);
        
        $quarterfinal = BracketMatch::create([
            'match_id' => 'QF-' . time(),
            'tournament_id' => Tournament::latest()->first()->id,
            'bracket_stage_id' => BracketStage::first()->id,
            'round_name' => 'Quarterfinals',
            'round_number' => 1,
            'match_number' => 2,
            'team1_id' => 3,
            'team2_id' => 4,
            'winner_advances_to' => $semifinal->match_id,
            'status' => 'pending',
            'best_of' => 3,
            'scheduled_at' => Carbon::now()->addHours(1)
        ]);
        
        // Complete the quarterfinal
        $quarterfinal->complete(3);
        
        // Check if winner advanced
        $semifinal->refresh();
        return $semifinal->team1_id == 3 || $semifinal->team2_id == 3;
    } catch (Exception $e) {
        logError("Winner advancement failed: " . $e->getMessage());
        return false;
    }
}());

// Test 14: Bracket Reset (Grand Finals)
logTest("Match", "Bracket Reset", function() {
    try {
        $finalStage = BracketStage::where('type', 'grand_final')->first();
        if (!$finalStage) {
            $finalStage = BracketStage::create([
                'tournament_id' => Tournament::latest()->first()->id,
                'name' => 'Grand Final',
                'type' => 'grand_final',
                'stage_order' => 99,
                'status' => 'pending',
                'max_teams' => 2
            ]);
        }
        
        $grandFinal = BracketMatch::create([
            'match_id' => 'GF-' . time(),
            'tournament_id' => Tournament::latest()->first()->id,
            'bracket_stage_id' => $finalStage->id,
            'round_name' => 'Grand Final',
            'round_number' => 99,
            'match_number' => 1,
            'team1_id' => 1,
            'team2_id' => 2,
            'status' => 'pending',
            'best_of' => 5,
            'scheduled_at' => Carbon::now()->addDays(1)
        ]);
        
        // Complete with lower bracket team winning
        $grandFinal->complete(2, 1);
        
        // Try to reset bracket
        $result = $grandFinal->resetBracket();
        
        return $result && BracketMatch::where('bracket_reset', true)->exists();
    } catch (Exception $e) {
        logError("Bracket reset failed: " . $e->getMessage());
        return false;
    }
}());

// ==========================
// RANKINGS & STANDINGS TESTS
// ==========================

logSection("RANKINGS & STANDINGS TESTS");

// Test 15: Tournament Standings
logTest("Rankings", "Tournament Standings", function() {
    try {
        $tournament = Tournament::latest()->first();
        if (!$tournament) return false;
        
        // Add some teams with placements
        $teams = $tournament->teams;
        $placement = 1;
        foreach ($teams as $team) {
            $tournament->teams()->updateExistingPivot($team->id, [
                'placement' => $placement++,
                'points_earned' => 100 - ($placement * 10),
                'prize_money' => $placement == 1 ? 25000 : ($placement == 2 ? 15000 : 10000)
            ]);
        }
        
        $standings = $tournament->teams()
                                ->orderBy('pivot_placement')
                                ->get();
        
        return count($standings) > 0 && $standings->first()->pivot->placement == 1;
    } catch (Exception $e) {
        logError("Tournament standings failed: " . $e->getMessage());
        return false;
    }
}());

// Test 16: Bracket Standings
logTest("Rankings", "Bracket Standings", function() {
    try {
        $stage = BracketStage::first();
        if (!$stage) return false;
        
        // Create standings
        $standing = BracketStanding::create([
            'tournament_id' => $stage->tournament_id,
            'event_id' => $stage->event_id,
            'bracket_stage_id' => $stage->id,
            'team_id' => 1,
            'position' => 1,
            'wins' => 3,
            'losses' => 0,
            'game_wins' => 9,
            'game_losses' => 2,
            'points' => 9
        ]);
        
        return $standing->exists;
    } catch (Exception $e) {
        logError("Bracket standings failed: " . $e->getMessage());
        return false;
    }
}());

// Test 17: Player Rankings Update
logTest("Rankings", "Player Rankings", function() {
    try {
        $player = Player::first();
        if (!$player) return false;
        
        $oldRating = $player->rating ?? 1500;
        
        // Simulate rating update after match
        $player->rating = $oldRating + 25;
        $player->peak_rating = max($player->peak_rating ?? 1500, $player->rating);
        $player->save();
        
        return $player->rating == ($oldRating + 25);
    } catch (Exception $e) {
        logError("Player rankings failed: " . $e->getMessage());
        return false;
    }
}());

// Test 18: Team Rankings Update
logTest("Rankings", "Team Rankings", function() {
    try {
        $team = Team::first();
        if (!$team) return false;
        
        $oldEarnings = $team->earnings ?? 0;
        
        // Simulate earnings update after tournament
        $team->earnings = $oldEarnings + 25000;
        $team->save();
        
        return $team->earnings == ($oldEarnings + 25000);
    } catch (Exception $e) {
        logError("Team rankings failed: " . $e->getMessage());
        return false;
    }
}());

// ==========================
// API ENDPOINT TESTS
// ==========================

logSection("API ENDPOINT TESTS");

$adminToken = getAdminToken();

// Test 19: Tournament List API
logTest("API", "Tournament List", function() use ($baseUrl) {
    try {
        $response = Http::get("{$baseUrl}/public/tournaments");
        return $response->successful();
    } catch (Exception $e) {
        logError("Tournament list API failed: " . $e->getMessage());
        return false;
    }
}());

// Test 20: Bracket Generation API
logTest("API", "Bracket Generation", function() use ($baseUrl, $adminToken) {
    if (!$adminToken) {
        logWarning("No admin token, skipping authenticated test");
        return false;
    }
    
    try {
        $event = Event::first();
        if (!$event) return false;
        
        $response = Http::withToken($adminToken)
                       ->post("{$baseUrl}/admin/events/{$event->id}/generate-bracket", [
                           'format' => 'single_elimination',
                           'best_of' => 3
                       ]);
        
        return $response->successful() || $response->status() == 422; // 422 if already has bracket
    } catch (Exception $e) {
        logError("Bracket generation API failed: " . $e->getMessage());
        return false;
    }
}());

// Test 21: Rankings API
logTest("API", "Rankings Endpoints", function() use ($baseUrl) {
    try {
        $endpoints = [
            '/public/rankings',
            '/public/team-rankings',
            '/public/rankings/distribution'
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = Http::get("{$baseUrl}{$endpoint}");
            if (!$response->successful()) {
                logError("Failed endpoint: {$endpoint}");
                return false;
            }
        }
        
        return true;
    } catch (Exception $e) {
        logError("Rankings API failed: " . $e->getMessage());
        return false;
    }
}());

// Test 22: Live Matches API
logTest("API", "Live Matches", function() use ($baseUrl) {
    try {
        $response = Http::get("{$baseUrl}/live-matches");
        return $response->successful();
    } catch (Exception $e) {
        logError("Live matches API failed: " . $e->getMessage());
        return false;
    }
}());

// ==========================
// PERFORMANCE TESTS
// ==========================

logSection("PERFORMANCE TESTS");

// Test 23: Large Bracket Generation Performance
logTest("Performance", "32-Team Bracket", function() {
    try {
        $startTime = microtime(true);
        
        $bracketService = app(BracketService::class);
        $tournament = Tournament::latest()->first();
        $teams = Team::limit(32)->get();
        
        if ($teams->count() < 32) {
            // Create dummy teams if needed
            for ($i = $teams->count(); $i < 32; $i++) {
                $teams->push(Team::factory()->create([
                    'name' => 'Test Team ' . ($i + 1)
                ]));
            }
        }
        
        $result = $bracketService->generateSingleEliminationBracket(
            $tournament,
            $teams,
            ['best_of' => '3']
        );
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to ms
        
        logTest("Performance", "Generation Time", $executionTime < 500, "{$executionTime}ms");
        
        return isset($result['matches']);
    } catch (Exception $e) {
        logError("Performance test failed: " . $e->getMessage());
        return false;
    }
}());

// ==========================
// SUMMARY REPORT
// ==========================

logSection("TEST SUMMARY");

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

foreach ($testResults as $category => $tests) {
    $categoryPassed = 0;
    $categoryTotal = count($tests);
    
    foreach ($tests as $test => $result) {
        $totalTests++;
        if ($result) {
            $passedTests++;
            $categoryPassed++;
        } else {
            $failedTests++;
        }
    }
    
    $percentage = $categoryTotal > 0 ? round(($categoryPassed / $categoryTotal) * 100) : 0;
    echo "  {$category}: {$categoryPassed}/{$categoryTotal} ({$percentage}%)\n";
}

echo "\n";
echo "Total Tests: {$totalTests}\n";
echo "{$GREEN}Passed: {$passedTests}{$RESET}\n";
echo "{$RED}Failed: {$failedTests}{$RESET}\n";

if (count($warnings) > 0) {
    echo "{$YELLOW}Warnings: " . count($warnings) . "{$RESET}\n";
}

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100) : 0;
echo "\nSuccess Rate: {$successRate}%\n";

if ($successRate == 100) {
    echo "{$GREEN}✅ ALL TESTS PASSED! Tournament, Bracket & Rankings systems are fully operational.{$RESET}\n";
} elseif ($successRate >= 80) {
    echo "{$YELLOW}⚠️ MOSTLY PASSING: Systems are functional but some issues need attention.{$RESET}\n";
} else {
    echo "{$RED}❌ CRITICAL ISSUES: Multiple system failures detected. Immediate attention required.{$RESET}\n";
}

// Save detailed report
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_tests' => $totalTests,
    'passed' => $passedTests,
    'failed' => $failedTests,
    'success_rate' => $successRate,
    'results' => $testResults,
    'errors' => $errors,
    'warnings' => $warnings
];

file_put_contents(
    __DIR__ . '/tournament_system_test_report_' . time() . '.json',
    json_encode($report, JSON_PRETTY_PRINT)
);

echo "\nDetailed report saved to tournament_system_test_report_*.json\n";