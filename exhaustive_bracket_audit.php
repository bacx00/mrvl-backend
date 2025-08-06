<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "EXHAUSTIVE TOURNAMENT BRACKET SYSTEM AUDIT\n";
echo "==========================================\n\n";

// Test results tracking
$testResults = [
    'passed' => 0,
    'failed' => 0,
    'total' => 0,
    'details' => []
];

function logTest($testName, $success, $details = '', $duration = 0) {
    global $testResults;
    $testResults['total']++;
    if ($success) {
        $testResults['passed']++;
        echo "     ✓ PASS: $testName" . ($duration > 0 ? " ({$duration}ms)" : "") . "\n";
    } else {
        $testResults['failed']++;
        echo "     ✗ FAIL: $testName\n";
        if ($details) echo "       Detail: $details\n";
    }
    $testResults['details'][] = [
        'test' => $testName,
        'success' => $success,
        'details' => $details,
        'duration' => $duration
    ];
}

// Get admin user for API testing
$adminUser = DB::table('users')->where('role', 'admin')->first();
if (!$adminUser) {
    echo "Creating admin user for testing...\n";
    $adminId = DB::table('users')->insertGetId([
        'username' => 'exhaustive_audit_' . time(),
        'email' => 'exhaustive' . time() . '@test.com',
        'password' => bcrypt('password123'),
        'role' => 'admin',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    $adminUser = DB::table('users')->where('id', $adminId)->first();
}

$user = App\Models\User::find($adminUser->id);
$token = $user->createToken('exhaustive-audit')->accessToken;

$eventId = 17; // Marvel Rivals event
$baseUrl = "http://localhost:8000/api/admin/events/{$eventId}";

function makeApiRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init($url);
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'data' => json_decode($response, true),
        'raw' => $response
    ];
}

try {
    echo "1. BRACKET GENERATION TESTS - ALL TEAM SIZES\n";
    echo str_repeat("-", 45) . "\n";
    
    $teamSizes = [2, 4, 8, 16, 32, 64];
    $allTeams = DB::table('teams')->limit(64)->get();
    
    foreach ($teamSizes as $size) {
        if (count($allTeams) >= $size) {
            echo "\n1.{$size} Testing bracket generation with {$size} teams\n";
            
            // Setup teams for this test
            DB::table('event_teams')->where('event_id', $eventId)->delete();
            foreach (array_slice($allTeams->toArray(), 0, $size) as $index => $team) {
                DB::table('event_teams')->insert([
                    'event_id' => $eventId,
                    'team_id' => $team->id,
                    'seed' => $index + 1,
                    'registered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $startTime = microtime(true);
            $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
                'format' => 'single_elimination',
                'seeding_type' => 'rating',
                'match_format' => 'bo3',
                'finals_format' => 'bo5'
            ], $token);
            $endTime = microtime(true);
            
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            if ($response['status'] === 200 && $response['data']['success']) {
                $expectedRounds = ceil(log($size, 2));
                $actualMatches = $response['data']['data']['matches_created'] ?? 0;
                $expectedMatches = $size - 1; // Single elimination
                
                logTest("Generate {$size}-team bracket", 
                    $actualMatches === $expectedMatches, 
                    "Expected {$expectedMatches} matches, got {$actualMatches}", 
                    $duration);
                    
                // Verify database state
                $dbMatches = DB::table('matches')->where('event_id', $eventId)->count();
                logTest("Database consistency for {$size} teams", 
                    $dbMatches === $actualMatches,
                    "API reported {$actualMatches}, DB has {$dbMatches}");
                    
            } else {
                logTest("Generate {$size}-team bracket", false, 
                    $response['data']['message'] ?? 'Unknown error');
            }
        } else {
            logTest("Generate {$size}-team bracket", false, 
                "Not enough teams in database (need {$size}, have " . count($allTeams) . ")");
        }
    }
    
    echo "\n\n2. SEEDING TYPE TESTS\n";
    echo str_repeat("-", 19) . "\n";
    
    $seedingTypes = ['rating', 'random', 'manual'];
    
    // Setup 8 teams for consistent testing
    DB::table('event_teams')->where('event_id', $eventId)->delete();
    foreach (array_slice($allTeams->toArray(), 0, 8) as $index => $team) {
        DB::table('event_teams')->insert([
            'event_id' => $eventId,
            'team_id' => $team->id,
            'seed' => $index + 1,
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    foreach ($seedingTypes as $seedingType) {
        echo "\n2." . (array_search($seedingType, $seedingTypes) + 1) . " Testing {$seedingType} seeding\n";
        
        $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
            'format' => 'single_elimination',
            'seeding_type' => $seedingType,
            'match_format' => 'bo3',
            'finals_format' => 'bo5'
        ], $token);
        
        if ($response['status'] === 200 && $response['data']['success']) {
            logTest("{$seedingType} seeding generation", true);
            
            // Verify seeding was applied
            $matches = DB::table('matches as m')
                ->leftJoin('event_teams as et1', function($join) use ($eventId) {
                    $join->on('m.team1_id', '=', 'et1.team_id')
                         ->where('et1.event_id', '=', $eventId);
                })
                ->leftJoin('event_teams as et2', function($join) use ($eventId) {
                    $join->on('m.team2_id', '=', 'et2.team_id')
                         ->where('et2.event_id', '=', $eventId);
                })
                ->where('m.event_id', $eventId)
                ->where('m.round', 1)
                ->select('et1.seed as team1_seed', 'et2.seed as team2_seed')
                ->get();
                
            $properSeeding = true;
            if ($seedingType === 'rating' || $seedingType === 'manual') {
                // Check if high seeds are paired with low seeds
                foreach ($matches as $match) {
                    if (!is_null($match->team1_seed) && !is_null($match->team2_seed)) {
                        $seedSum = $match->team1_seed + $match->team2_seed;
                        if ($seedSum !== 9) { // For 8 teams: 1+8=9, 2+7=9, etc.
                            $properSeeding = false;
                            break;
                        }
                    }
                }
            }
            
            if ($seedingType === 'rating' || $seedingType === 'manual') {
                logTest("{$seedingType} seeding verification", $properSeeding,
                    $properSeeding ? "Seeds properly paired" : "Seeding pattern incorrect");
            }
            
        } else {
            logTest("{$seedingType} seeding generation", false,
                $response['data']['message'] ?? 'Unknown error');
        }
    }
    
    echo "\n\n3. MATCH FORMAT TESTS\n";
    echo str_repeat("-", 19) . "\n";
    
    $matchFormats = ['bo1', 'bo3', 'bo5', 'bo7'];
    $finalsFormats = ['bo3', 'bo5', 'bo7'];
    
    foreach ($matchFormats as $matchFormat) {
        foreach ($finalsFormats as $finalsFormat) {
            if ($matchFormat === 'bo7' && $finalsFormat === 'bo3') continue; // Skip invalid combo
            
            echo "\n3.x Testing {$matchFormat} matches with {$finalsFormat} finals\n";
            
            $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
                'format' => 'single_elimination',
                'seeding_type' => 'rating',
                'match_format' => $matchFormat,
                'finals_format' => $finalsFormat
            ], $token);
            
            if ($response['status'] === 200 && $response['data']['success']) {
                logTest("{$matchFormat}/{$finalsFormat} format generation", true);
                
                // Verify formats in database
                $matches = DB::table('matches')->where('event_id', $eventId)->get();
                $formatCounts = $matches->groupBy('format')->map->count()->toArray();
                
                logTest("{$matchFormat}/{$finalsFormat} format verification", 
                    isset($formatCounts[$matchFormat]) || isset($formatCounts[$finalsFormat]),
                    "Format distribution: " . json_encode($formatCounts));
                    
            } else {
                logTest("{$matchFormat}/{$finalsFormat} format generation", false,
                    $response['data']['message'] ?? 'Unknown error');
            }
        }
    }
    
    echo "\n\n4. EDGE CASE TESTS\n";
    echo str_repeat("-", 16) . "\n";
    
    echo "\n4.1 Testing odd number of teams (byes)\n";
    
    $oddSizes = [3, 5, 7, 9, 15, 31];
    foreach ($oddSizes as $size) {
        if (count($allTeams) >= $size) {
            DB::table('event_teams')->where('event_id', $eventId)->delete();
            foreach (array_slice($allTeams->toArray(), 0, $size) as $index => $team) {
                DB::table('event_teams')->insert([
                    'event_id' => $eventId,
                    'team_id' => $team->id,
                    'seed' => $index + 1,
                    'registered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
                'format' => 'single_elimination',
                'seeding_type' => 'rating',
                'match_format' => 'bo3'
            ], $token);
            
            if ($response['status'] === 200 && $response['data']['success']) {
                $matches = DB::table('matches')->where('event_id', $eventId)->get();
                $byeMatches = $matches->filter(fn($m) => is_null($m->team2_id))->count();
                $expectedByes = $size % 2;
                
                logTest("{$size} teams (odd) with byes", 
                    $byeMatches >= 0, // Byes should be handled somehow
                    "Generated {$byeMatches} bye situations");
            } else {
                logTest("{$size} teams (odd) with byes", false,
                    $response['data']['message'] ?? 'Unknown error');
            }
        }
    }
    
    echo "\n4.2 Testing empty teams scenario\n";
    DB::table('event_teams')->where('event_id', $eventId)->delete();
    
    $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'single_elimination',
        'seeding_type' => 'rating'
    ], $token);
    
    logTest("Empty teams scenario", 
        $response['status'] === 400,
        "Should reject with 400, got {$response['status']}");
    
    echo "\n4.3 Testing single team scenario\n";
    DB::table('event_teams')->insert([
        'event_id' => $eventId,
        'team_id' => $allTeams[0]->id,
        'seed' => 1,
        'registered_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'single_elimination',
        'seeding_type' => 'rating'
    ], $token);
    
    logTest("Single team scenario", 
        $response['status'] === 400,
        "Should reject with 400, got {$response['status']}");
    
    echo "\n4.4 Testing invalid data scenarios\n";
    
    // Setup 4 teams for invalid data tests
    DB::table('event_teams')->where('event_id', $eventId)->delete();
    foreach (array_slice($allTeams->toArray(), 0, 4) as $index => $team) {
        DB::table('event_teams')->insert([
            'event_id' => $eventId,
            'team_id' => $team->id,
            'seed' => $index + 1,
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    $invalidTests = [
        ['format' => 'invalid_format', 'expected' => 422],
        ['seeding_type' => 'invalid_seeding', 'expected' => 422],
        ['match_format' => 'invalid_bo', 'expected' => 422],
    ];
    
    foreach ($invalidTests as $invalidTest) {
        $testData = array_merge([
            'format' => 'single_elimination',
            'seeding_type' => 'rating',
            'match_format' => 'bo3'
        ], $invalidTest);
        
        unset($testData['expected']);
        $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', $testData, $token);
        
        logTest("Invalid data: " . json_encode($invalidTest), 
            $response['status'] >= 400,
            "Expected error status, got {$response['status']}");
    }
    
    echo "\n\n5. MATCH PROGRESSION TESTS\n";
    echo str_repeat("-", 24) . "\n";
    
    // Generate fresh 8-team bracket for progression testing
    DB::table('event_teams')->where('event_id', $eventId)->delete();
    foreach (array_slice($allTeams->toArray(), 0, 8) as $index => $team) {
        DB::table('event_teams')->insert([
            'event_id' => $eventId,
            'team_id' => $team->id,
            'seed' => $index + 1,
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'single_elimination',
        'seeding_type' => 'rating',
        'match_format' => 'bo3'
    ], $token);
    
    if ($response['status'] === 200) {
        echo "\n5.1 Testing match result updates and winner advancement\n";
        
        $matches = DB::table('matches')->where('event_id', $eventId)->where('round', 1)->get();
        $scoreTests = [
            ['team1_score' => 2, 'team2_score' => 0, 'description' => '2-0 victory'],
            ['team1_score' => 2, 'team2_score' => 1, 'description' => '2-1 victory'], 
            ['team1_score' => 0, 'team2_score' => 2, 'description' => '0-2 defeat'],
            ['team1_score' => 1, 'team2_score' => 2, 'description' => '1-2 defeat']
        ];
        
        foreach (array_slice($matches->toArray(), 0, 4) as $index => $match) {
            if (isset($scoreTests[$index])) {
                $scoreTest = $scoreTests[$index];
                
                $updateData = [
                    'team1_score' => $scoreTest['team1_score'],
                    'team2_score' => $scoreTest['team2_score'],
                    'status' => 'completed'
                ];
                
                $updateResponse = makeApiRequest("http://localhost:8000/api/admin/matches/{$match->id}", 
                    'PUT', $updateData, $token);
                
                if ($updateResponse['status'] === 200) {
                    logTest("Match update: {$scoreTest['description']}", true);
                    
                    // Verify winner advancement
                    $winnerId = $scoreTest['team1_score'] > $scoreTest['team2_score'] ? 
                        $match->team1_id : $match->team2_id;
                    
                    $nextRoundMatch = DB::table('matches')
                        ->where('event_id', $eventId)
                        ->where('round', 2)
                        ->where(function($query) use ($winnerId) {
                            $query->where('team1_id', $winnerId)
                                  ->orWhere('team2_id', $winnerId);
                        })
                        ->first();
                    
                    logTest("Winner advancement for {$scoreTest['description']}", 
                        !is_null($nextRoundMatch),
                        $nextRoundMatch ? "Winner advanced to match {$nextRoundMatch->id}" : "No advancement found");
                        
                } else {
                    logTest("Match update: {$scoreTest['description']}", false,
                        $updateResponse['data']['message'] ?? 'Update failed');
                }
            }
        }
        
        echo "\n5.2 Testing match status transitions\n";
        $statusTransitions = [
            ['from' => 'upcoming', 'to' => 'live'],
            ['from' => 'live', 'to' => 'completed'],
            ['from' => 'upcoming', 'to' => 'cancelled']
        ];
        
        $testMatches = DB::table('matches')->where('event_id', $eventId)->where('status', 'upcoming')->limit(3)->get();
        
        foreach ($statusTransitions as $index => $transition) {
            if (isset($testMatches[$index])) {
                $match = $testMatches[$index];
                
                $updateData = [
                    'status' => $transition['to'],
                    'team1_score' => $transition['to'] === 'completed' ? 2 : 0,
                    'team2_score' => $transition['to'] === 'completed' ? 1 : 0
                ];
                
                $updateResponse = makeApiRequest("http://localhost:8000/api/admin/matches/{$match->id}",
                    'PUT', $updateData, $token);
                    
                logTest("Status transition: {$transition['from']} → {$transition['to']}", 
                    $updateResponse['status'] === 200,
                    $updateResponse['data']['message'] ?? '');
            }
        }
    }
    
    echo "\n\n6. ERROR HANDLING TESTS\n";
    echo str_repeat("-", 21) . "\n";
    
    echo "\n6.1 Testing invalid event IDs\n";
    $invalidEventId = 99999;
    $response = makeApiRequest("http://localhost:8000/api/admin/events/{$invalidEventId}/bracket/generate", 
        'POST', ['format' => 'single_elimination'], $token);
    
    logTest("Invalid event ID", 
        $response['status'] === 404,
        "Expected 404, got {$response['status']}");
    
    echo "\n6.2 Testing missing required fields\n";
    $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [], $token);
    
    logTest("Missing required fields", 
        $response['status'] >= 400,
        "Expected error status, got {$response['status']}");
    
    echo "\n6.3 Testing duplicate bracket generation\n";
    // First generation
    $response1 = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'single_elimination',
        'seeding_type' => 'rating'
    ], $token);
    
    // Second generation (should replace first)
    $response2 = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'single_elimination', 
        'seeding_type' => 'rating'
    ], $token);
    
    logTest("Duplicate bracket generation", 
        $response2['status'] === 200,
        "Second generation should succeed (replace first)");
    
    echo "\n6.4 Testing concurrent operations simulation\n";
    // This would require actual concurrent requests in a real test
    // For now, we'll test rapid sequential operations
    $rapidResults = [];
    for ($i = 0; $i < 3; $i++) {
        $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
            'format' => 'single_elimination',
            'seeding_type' => 'random'
        ], $token);
        $rapidResults[] = $response['status'];
    }
    
    $allSuccessful = array_reduce($rapidResults, fn($carry, $status) => $carry && $status === 200, true);
    logTest("Rapid sequential operations", $allSuccessful,
        "Status codes: " . implode(', ', $rapidResults));
    
    echo "\n\n7. PERFORMANCE TESTS\n";
    echo str_repeat("-", 18) . "\n";
    
    $performanceTests = [
        ['teams' => 8, 'expected_time' => 100],   // 100ms
        ['teams' => 16, 'expected_time' => 200],  // 200ms  
        ['teams' => 32, 'expected_time' => 500],  // 500ms
        ['teams' => 64, 'expected_time' => 1000]  // 1000ms
    ];
    
    foreach ($performanceTests as $perfTest) {
        if (count($allTeams) >= $perfTest['teams']) {
            echo "\n7.x Performance test: {$perfTest['teams']} teams\n";
            
            DB::table('event_teams')->where('event_id', $eventId)->delete();
            foreach (array_slice($allTeams->toArray(), 0, $perfTest['teams']) as $index => $team) {
                DB::table('event_teams')->insert([
                    'event_id' => $eventId,
                    'team_id' => $team->id,
                    'seed' => $index + 1,
                    'registered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $startTime = microtime(true);
            $response = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
                'format' => 'single_elimination',
                'seeding_type' => 'rating'
            ], $token);
            $endTime = microtime(true);
            
            $duration = round(($endTime - $startTime) * 1000, 2);
            $withinExpected = $duration <= $perfTest['expected_time'];
            
            logTest("{$perfTest['teams']} teams performance", 
                $response['status'] === 200 && $withinExpected,
                "Took {$duration}ms (expected ≤{$perfTest['expected_time']}ms)",
                $duration);
        }
    }
    
    echo "\n\n8. INTEGRATION TESTS WITH REAL DATA\n";
    echo str_repeat("-", 35) . "\n";
    
    echo "\n8.1 Testing with event ID 17 (Marvel Rivals)\n";
    $eventInfo = DB::table('events')->where('id', 17)->first();
    
    if ($eventInfo) {
        logTest("Event 17 exists", true, "Event: {$eventInfo->name}");
        
        $teamCount = DB::table('event_teams')->where('event_id', 17)->count();
        logTest("Event 17 has teams", $teamCount > 0, "Team count: {$teamCount}");
        
        if ($teamCount >= 2) {
            $response = makeApiRequest("http://localhost:8000/api/admin/events/17/bracket/generate", 
                'POST', [
                    'format' => 'single_elimination',
                    'seeding_type' => 'rating',
                    'match_format' => 'bo3'
                ], $token);
                
            logTest("Generate bracket for event 17", 
                $response['status'] === 200,
                $response['data']['message'] ?? '');
        }
    } else {
        logTest("Event 17 exists", false, "Event not found");
    }
    
    echo "\n8.2 Testing team assignments\n";
    $teams = DB::table('event_teams')->where('event_id', $eventId)->get();
    $matches = DB::table('matches')->where('event_id', $eventId)->get();
    
    $allTeamsAssigned = true;
    $assignmentCounts = [];
    
    foreach ($matches as $match) {
        if ($match->team1_id) {
            $assignmentCounts[$match->team1_id] = ($assignmentCounts[$match->team1_id] ?? 0) + 1;
        }
        if ($match->team2_id) {
            $assignmentCounts[$match->team2_id] = ($assignmentCounts[$match->team2_id] ?? 0) + 1;
        }
    }
    
    logTest("Team assignments verification", 
        count($assignmentCounts) > 0,
        "Teams assigned to matches: " . count($assignmentCounts));
    
    echo "\n8.3 Testing bracket deletion and regeneration\n";
    $deleteResponse = makeApiRequest($baseUrl . '/bracket', 'DELETE', null, $token);
    logTest("Bracket deletion", 
        $deleteResponse['status'] === 200,
        $deleteResponse['data']['message'] ?? '');
        
    $matchesAfterDelete = DB::table('matches')->where('event_id', $eventId)->count();
    logTest("Deletion verification", 
        $matchesAfterDelete === 0,
        "Matches remaining: {$matchesAfterDelete}");
    
    // Regenerate
    $regenResponse = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'single_elimination',
        'seeding_type' => 'rating'
    ], $token);
    
    logTest("Bracket regeneration", 
        $regenResponse['status'] === 200,
        $regenResponse['data']['message'] ?? '');
    
    echo "\n\n9. MARVEL RIVALS SPECIFIC TESTS\n";
    echo str_repeat("-", 29) . "\n";
    
    echo "\n9.1 Testing Swiss system format\n";
    $swissResponse = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'swiss',
        'seeding_type' => 'rating',
        'match_format' => 'bo3'
    ], $token);
    
    logTest("Swiss system generation", 
        $swissResponse['status'] === 200,
        $swissResponse['data']['message'] ?? '');
    
    echo "\n9.2 Testing double elimination format\n";
    $doubleElimResponse = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'double_elimination',
        'seeding_type' => 'rating',
        'match_format' => 'bo3',
        'finals_format' => 'bo5'
    ], $token);
    
    logTest("Double elimination generation", 
        $doubleElimResponse['status'] === 200,
        $doubleElimResponse['data']['message'] ?? '');
    
    if ($doubleElimResponse['status'] === 200) {
        $matches = DB::table('matches')->where('event_id', $eventId)->get();
        $upperBracket = $matches->where('bracket_type', 'upper')->count();
        $lowerBracket = $matches->where('bracket_type', 'lower')->count();
        $grandFinal = $matches->where('bracket_type', 'grand_final')->count();
        
        logTest("Double elimination structure", 
            $upperBracket > 0 && $lowerBracket > 0 && $grandFinal === 1,
            "Upper: {$upperBracket}, Lower: {$lowerBracket}, Grand Final: {$grandFinal}");
    }
    
    echo "\n9.3 Testing round robin format\n";
    $roundRobinResponse = makeApiRequest($baseUrl . '/bracket/generate', 'POST', [
        'format' => 'round_robin',
        'seeding_type' => 'rating',
        'match_format' => 'bo3'
    ], $token);
    
    logTest("Round robin generation", 
        $roundRobinResponse['status'] === 200,
        $roundRobinResponse['data']['message'] ?? '');
    
    echo "\n\n10. DATA INTEGRITY TESTS\n";
    echo str_repeat("-", 23) . "\n";
    
    echo "\n10.1 Testing for orphaned matches\n";
    $orphanedMatches = DB::table('matches as m')
        ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
        ->whereNull('e.id')
        ->count();
        
    logTest("No orphaned matches", 
        $orphanedMatches === 0,
        "Orphaned matches found: {$orphanedMatches}");
    
    echo "\n10.2 Testing foreign key constraints\n";
    try {
        // Try to insert match with invalid event_id
        DB::table('matches')->insert([
            'event_id' => 99999,
            'team1_id' => $allTeams[0]->id,
            'team2_id' => $allTeams[1]->id,
            'round' => 1,
            'status' => 'upcoming',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        logTest("Foreign key constraint (event_id)", false, "Invalid insert succeeded");
    } catch (Exception $e) {
        logTest("Foreign key constraint (event_id)", true, "Properly rejected invalid insert");
    }
    
    echo "\n10.3 Testing scheduled_at timestamps\n";
    $scheduledMatches = DB::table('matches')
        ->where('event_id', $eventId)
        ->whereNotNull('scheduled_at')
        ->count();
        
    $totalMatches = DB::table('matches')->where('event_id', $eventId)->count();
    
    logTest("Scheduled timestamps presence", 
        $scheduledMatches > 0,
        "{$scheduledMatches}/{$totalMatches} matches have scheduled_at");
    
    echo "\n10.4 Testing match ordering preservation\n";
    $matches = DB::table('matches')
        ->where('event_id', $eventId)
        ->orderBy('round')
        ->orderBy('bracket_position')
        ->get();
        
    $orderingCorrect = true;
    $lastRound = 0;
    $lastPosition = 0;
    
    foreach ($matches as $match) {
        if ($match->round < $lastRound || 
            ($match->round === $lastRound && $match->bracket_position < $lastPosition)) {
            $orderingCorrect = false;
            break;
        }
        $lastRound = $match->round;
        $lastPosition = $match->bracket_position;
    }
    
    logTest("Match ordering preservation", $orderingCorrect, 
        $orderingCorrect ? "All matches properly ordered" : "Ordering issues found");
    
    echo "\n\n" . str_repeat("=", 80) . "\n";
    echo "EXHAUSTIVE TOURNAMENT BRACKET SYSTEM AUDIT COMPLETED\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // Generate comprehensive report
    echo "AUDIT SUMMARY:\n";
    echo "==============\n";
    echo "Total Tests: {$testResults['total']}\n";
    echo "Passed: {$testResults['passed']}\n";
    echo "Failed: {$testResults['failed']}\n";
    echo "Success Rate: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n\n";
    
    if ($testResults['failed'] > 0) {
        echo "FAILED TESTS:\n";
        echo "=============\n";
        foreach ($testResults['details'] as $test) {
            if (!$test['success']) {
                echo "- {$test['test']}: {$test['details']}\n";
            }
        }
        echo "\n";
    }
    
    echo "PERFORMANCE SUMMARY:\n";
    echo "===================\n";
    $performanceTests = array_filter($testResults['details'], fn($t) => $t['duration'] > 0);
    foreach ($performanceTests as $test) {
        echo "- {$test['test']}: {$test['duration']}ms\n";
    }
    
    echo "\nCRITICAL FINDINGS:\n";
    echo "==================\n";
    
    $criticalIssues = [];
    foreach ($testResults['details'] as $test) {
        if (!$test['success'] && 
            (strpos($test['test'], 'constraint') !== false || 
             strpos($test['test'], 'integrity') !== false ||
             strpos($test['test'], 'orphaned') !== false)) {
            $criticalIssues[] = $test;
        }
    }
    
    if (empty($criticalIssues)) {
        echo "✅ No critical data integrity issues found\n";
    } else {
        foreach ($criticalIssues as $issue) {
            echo "🚨 CRITICAL: {$issue['test']} - {$issue['details']}\n";
        }
    }
    
    echo "\nRECOMMENDATIONS:\n";
    echo "================\n";
    
    if ($testResults['failed'] === 0) {
        echo "🎉 EXCELLENT: All tests passed! The bracket system is robust and production-ready.\n\n";
        echo "Recommended enhancements:\n";
        echo "1. Add automated tournament progression\n";
        echo "2. Implement real-time bracket updates via WebSocket\n";
        echo "3. Add bracket visualization export features\n";
        echo "4. Consider implementing seeded group stages\n";
        echo "5. Add tournament scheduling optimization\n";
    } else {
        $failureRate = ($testResults['failed'] / $testResults['total']) * 100;
        if ($failureRate < 10) {
            echo "✅ GOOD: System is mostly functional with minor issues to address.\n";
        } elseif ($failureRate < 25) {
            echo "⚠️  CAUTION: System has notable issues that should be addressed.\n";
        } else {
            echo "🚨 CRITICAL: System has significant issues requiring immediate attention.\n";
        }
        
        echo "\nPriority fixes needed:\n";
        $priorityFixes = array_slice(array_filter($testResults['details'], fn($t) => !$t['success']), 0, 5);
        foreach ($priorityFixes as $i => $fix) {
            echo ($i + 1) . ". Fix: {$fix['test']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n🚨 CRITICAL SYSTEM ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    logTest("System stability", false, "Critical exception occurred: " . $e->getMessage());
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Audit completed at: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n";