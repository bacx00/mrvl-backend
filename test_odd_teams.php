<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing Odd Team Count Handling\n";
echo "================================\n\n";

// Get admin user for API testing
$adminUser = DB::table('users')->where('role', 'admin')->first();
$user = App\Models\User::find($adminUser->id);
$token = $user->createToken('odd-team-test')->accessToken;

$eventId = 17;
$apiUrl = "http://localhost:8000/api/admin/events/{$eventId}/bracket/generate";

function makeApiRequest($url, $method = 'POST', $data = null, $token = null) {
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
    // Test with different odd team counts
    $testCounts = [5, 7, 9, 11];
    
    foreach ($testCounts as $teamCount) {
        echo "Testing with {$teamCount} teams:\n";
        
        // Set up teams for this test
        $teams = DB::table('teams')->limit($teamCount)->get();
        DB::table('event_teams')->where('event_id', $eventId)->delete();
        
        foreach ($teams as $index => $team) {
            DB::table('event_teams')->insert([
                'event_id' => $eventId,
                'team_id' => $team->id,
                'seed' => $index + 1,
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Generate bracket
        $response = makeApiRequest($apiUrl, 'POST', [
            'format' => 'single_elimination',
            'seeding_type' => 'rating',
            'match_format' => 'bo3',
            'finals_format' => 'bo5'
        ], $token);
        
        if ($response['status'] === 200 && $response['data']['success']) {
            echo "  ✓ SUCCESS: Generated bracket for {$teamCount} teams\n";
            echo "  ✓ Matches created: {$response['data']['data']['matches_created']}\n";
            
            // Check for bye matches
            $matches = DB::table('matches')->where('event_id', $eventId)->get();
            $byeMatches = $matches->filter(fn($m) => is_null($m->team2_id))->count();
            $normalMatches = $matches->filter(fn($m) => !is_null($m->team2_id))->count();
            
            echo "  ✓ Regular matches: {$normalMatches}\n";
            echo "  ✓ Bye matches: {$byeMatches}\n";
            
            // Calculate expected values
            $expectedTotalMatches = $teamCount - 1; // n-1 matches to eliminate n-1 teams
            echo "  ✓ Total matches: " . count($matches) . " (expected: {$expectedTotalMatches})\n";
            
        } else {
            echo "  ✗ FAILED: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
            echo "  ✗ Status: {$response['status']}\n";
            if (isset($response['data'])) {
                echo "  ✗ Response: " . json_encode($response['data']) . "\n";
            }
        }
        
        echo "\n";
    }
    
    echo "Testing bye match winner advancement logic:\n";
    
    // Set up 5 teams and generate bracket
    $teams = DB::table('teams')->limit(5)->get();
    DB::table('event_teams')->where('event_id', $eventId)->delete();
    
    foreach ($teams as $index => $team) {
        DB::table('event_teams')->insert([
            'event_id' => $eventId,
            'team_id' => $team->id,
            'seed' => $index + 1,
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    $response = makeApiRequest($apiUrl, 'POST', [
        'format' => 'single_elimination',
        'seeding_type' => 'rating',
        'match_format' => 'bo3',
        'finals_format' => 'bo5'
    ], $token);
    
    if ($response['status'] === 200) {
        // Check if bye teams should advance automatically
        $matches = DB::table('matches')->where('event_id', $eventId)->get();
        $byeMatches = $matches->filter(fn($m) => is_null($m->team2_id));
        
        echo "  ✓ Found " . count($byeMatches) . " bye matches\n";
        
        foreach ($byeMatches as $byeMatch) {
            if ($byeMatch->team1_id && is_null($byeMatch->team2_id)) {
                echo "  ✓ Bye match: Team {$byeMatch->team1_id} advances automatically\n";
            }
        }
        
        // Check bracket structure
        $bracket = $response['data']['data']['bracket'];
        echo "  ✓ Bracket has " . count($bracket) . " rounds\n";
        
        $firstRound = $bracket[0]['matches'] ?? [];
        $firstRoundWithByes = array_filter($firstRound, fn($m) => is_null($m['team2']));
        echo "  ✓ First round bye matches in response: " . count($firstRoundWithByes) . "\n";
    }
    
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}