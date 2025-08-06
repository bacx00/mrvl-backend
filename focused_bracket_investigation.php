<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "FOCUSED BRACKET SYSTEM INVESTIGATION\n";
echo "====================================\n\n";

// Get admin user for API testing
$adminUser = DB::table('users')->where('role', 'admin')->first();
if (!$adminUser) {
    echo "Creating admin user for testing...\n";
    $adminId = DB::table('users')->insertGetId([
        'username' => 'focused_test_' . time(),
        'email' => 'focused' . time() . '@test.com',
        'password' => bcrypt('password123'),
        'role' => 'admin',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    $adminUser = DB::table('users')->where('id', $adminId)->first();
}

$user = App\Models\User::find($adminUser->id);
$token = $user->createToken('focused-test')->accessToken;

$eventId = 17;

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

echo "1. INVESTIGATING ENDPOINT ISSUES\n";
echo "================================\n";

$endpoints = [
    "http://localhost:8000/api/admin/events/{$eventId}/bracket/generate",
    "http://localhost:8000/api/admin/events/{$eventId}/generate-bracket",
    "http://localhost:8000/api/admin/events/{$eventId}/generate-swiss-double-elim"
];

foreach ($endpoints as $endpoint) {
    echo "Testing endpoint: " . substr($endpoint, strrpos($endpoint, '/') + 1) . "\n";
    
    $response = makeApiRequest($endpoint, 'POST', [
        'format' => 'single_elimination',
        'seeding_type' => 'rating'
    ], $token);
    
    echo "  Status: {$response['status']}\n";
    if ($response['status'] !== 200 && $response['data']) {
        echo "  Error: " . ($response['data']['message'] ?? 'Unknown') . "\n";
    }
    echo "\n";
}

echo "2. INVESTIGATING FORMAT SUPPORT\n";
echo "===============================\n";

$formats = ['single_elimination', 'double_elimination', 'round_robin', 'swiss'];

foreach ($formats as $format) {
    echo "Testing format: {$format}\n";
    
    $response = makeApiRequest("http://localhost:8000/api/admin/events/{$eventId}/generate-bracket", 'POST', [
        'format' => $format,
        'seeding_type' => 'rating'
    ], $token);
    
    echo "  Status: {$response['status']}\n";
    if ($response['status'] !== 200 && $response['data']) {
        echo "  Error: " . ($response['data']['message'] ?? 'Unknown') . "\n";
    }
    echo "\n";
}

echo "3. INVESTIGATING SEEDING PATTERNS\n";
echo "=================================\n";

// Setup 8 teams with specific ratings for testing
$allTeams = DB::table('teams')->limit(8)->get();
DB::table('event_teams')->where('event_id', $eventId)->delete();

foreach ($allTeams as $index => $team) {
    DB::table('event_teams')->insert([
        'event_id' => $eventId,
        'team_id' => $team->id,
        'seed' => $index + 1,
        'created_at' => now(),
        'updated_at' => now()
    ]);
}

$response = makeApiRequest("http://localhost:8000/api/admin/events/{$eventId}/generate-bracket", 'POST', [
    'format' => 'single_elimination',
    'seeding_type' => 'rating'
], $token);

if ($response['status'] === 200) {
    echo "Generated bracket successfully\n";
    
    // Check first round matches
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
        ->select('m.id', 'et1.seed as team1_seed', 'et2.seed as team2_seed')
        ->get();
    
    echo "First round matchups:\n";
    foreach ($matches as $match) {
        $seed1 = $match->team1_seed ?? 'BYE';
        $seed2 = $match->team2_seed ?? 'BYE';
        echo "  Seed {$seed1} vs Seed {$seed2}\n";
    }
    
    echo "\nExpected for proper seeding (8 teams): 1v8, 2v7, 3v6, 4v5\n";
}

echo "\n4. INVESTIGATING WINNER ADVANCEMENT\n";
echo "===================================\n";

// Complete a match and check advancement
$firstMatch = DB::table('matches')->where('event_id', $eventId)->where('round', 1)->first();

if ($firstMatch) {
    echo "Updating match {$firstMatch->id}\n";
    
    $updateResponse = makeApiRequest("http://localhost:8000/api/admin/matches/{$firstMatch->id}", 'PUT', [
        'team1_score' => 2,
        'team2_score' => 1,
        'status' => 'completed'
    ], $token);
    
    echo "Update status: {$updateResponse['status']}\n";
    
    if ($updateResponse['status'] === 200) {
        $winnerId = $firstMatch->team1_score > $firstMatch->team2_score ? 
            $firstMatch->team1_id : $firstMatch->team2_id;
            
        // Actually, we need to determine winner from the scores we just set
        $winnerId = $firstMatch->team1_id; // Since we set team1_score = 2 > team2_score = 1
        
        $nextRoundMatch = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('round', 2)
            ->where(function($query) use ($winnerId) {
                $query->where('team1_id', $winnerId)
                      ->orWhere('team2_id', $winnerId);
            })
            ->first();
            
        if ($nextRoundMatch) {
            echo "✓ Winner advanced to round 2 match {$nextRoundMatch->id}\n";
        } else {
            echo "✗ Winner not found in round 2\n";
            
            // Check what's in round 2
            $round2Matches = DB::table('matches')
                ->where('event_id', $eventId)
                ->where('round', 2)
                ->get();
            
            echo "Round 2 matches:\n";
            foreach ($round2Matches as $match) {
                echo "  Match {$match->id}: Team {$match->team1_id} vs Team {$match->team2_id}\n";
            }
        }
    }
}

echo "\n5. INVESTIGATING DOUBLE ELIMINATION STRUCTURE\n";
echo "=============================================\n";

$response = makeApiRequest("http://localhost:8000/api/admin/events/{$eventId}/generate-bracket", 'POST', [
    'format' => 'double_elimination',
    'seeding_type' => 'rating'
], $token);

if ($response['status'] === 200) {
    echo "Generated double elimination bracket\n";
    
    $bracketTypes = DB::table('matches')
        ->where('event_id', $eventId)
        ->select('bracket_type', DB::raw('count(*) as count'))
        ->groupBy('bracket_type')
        ->get();
    
    echo "Bracket structure:\n";
    foreach ($bracketTypes as $type) {
        echo "  {$type->bracket_type}: {$type->count} matches\n";
    }
} else {
    echo "Failed to generate double elimination: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
}

echo "\n6. CLEANING UP ORPHANED MATCHES\n";
echo "===============================\n";

$orphaned = DB::table('matches as m')
    ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
    ->whereNull('e.id')
    ->select('m.id', 'm.event_id')
    ->get();

echo "Found " . count($orphaned) . " orphaned matches\n";

foreach ($orphaned as $match) {
    echo "Deleting orphaned match {$match->id} (event {$match->event_id})\n";
    DB::table('matches')->where('id', $match->id)->delete();
}

echo "Cleanup complete\n";

echo "\n7. TESTING MATCH FORMAT ASSIGNMENT\n";
echo "==================================\n";

// Test different match formats
$formatTests = [
    ['match_format' => 'bo1', 'expected_format' => 'BO1'],
    ['match_format' => 'bo3', 'expected_format' => 'BO3'], 
    ['match_format' => 'bo5', 'expected_format' => 'BO5'],
    ['match_format' => 'bo7', 'expected_format' => 'BO7']
];

foreach ($formatTests as $test) {
    echo "Testing match format: {$test['match_format']}\n";
    
    $response = makeApiRequest("http://localhost:8000/api/admin/events/{$eventId}/generate-bracket", 'POST', [
        'format' => 'single_elimination',
        'seeding_type' => 'rating',
        'match_format' => $test['match_format']
    ], $token);
    
    if ($response['status'] === 200) {
        $formats = DB::table('matches')
            ->where('event_id', $eventId)
            ->select('format', DB::raw('count(*) as count'))
            ->groupBy('format')
            ->get();
            
        echo "  Formats in DB: ";
        foreach ($formats as $format) {
            echo "{$format->format}({$format->count}) ";
        }
        echo "\n";
        
        // Check if we got the expected format
        $hasExpected = $formats->contains('format', $test['expected_format']);
        echo "  Expected {$test['expected_format']}? " . ($hasExpected ? "✓" : "✗") . "\n";
    } else {
        echo "  Failed: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
    }
    echo "\n";
}

echo "Investigation complete.\n";