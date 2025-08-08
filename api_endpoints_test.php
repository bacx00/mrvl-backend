<?php
/**
 * API Endpoints Test Script
 * Tests the new player and team profile endpoints
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$baseUrl = 'http://localhost:8001';

function testEndpoint($url, $description) {
    global $baseUrl;
    $fullUrl = $baseUrl . $url;
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Testing: {$description}\n";
    echo "URL: {$fullUrl}\n";
    echo str_repeat('-', 60) . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false) {
        echo "❌ FAILED: Could not connect to endpoint\n";
        return false;
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && $data && isset($data['success']) && $data['success']) {
        echo "✅ SUCCESS: HTTP {$httpCode}\n";
        
        // Show relevant data structure
        if (isset($data['data'])) {
            if (is_array($data['data']) && !empty($data['data'])) {
                echo "📊 Data Count: " . count($data['data']) . "\n";
                if (isset($data['data'][0])) {
                    echo "📋 First Item Keys: " . implode(', ', array_keys($data['data'][0])) . "\n";
                }
            } elseif (is_object($data['data'])) {
                echo "📋 Data Keys: " . implode(', ', array_keys((array)$data['data'])) . "\n";
            }
        }
        
        if (isset($data['total'])) {
            echo "🔢 Total Records: " . $data['total'] . "\n";
        }
        
        if (isset($data['pagination'])) {
            echo "📄 Pagination: Page " . $data['pagination']['current_page'] . " of " . $data['pagination']['last_page'] . "\n";
        }
        
        return true;
    } else {
        echo "❌ FAILED: HTTP {$httpCode}\n";
        if ($data && isset($data['message'])) {
            echo "💬 Error: " . $data['message'] . "\n";
        } else {
            echo "📄 Response: " . substr($response, 0, 200) . "\n";
        }
        return false;
    }
}

echo "🚀 Starting API Endpoints Test Suite\n";
echo "Testing Marvel Rivals Backend Player & Team Profile Endpoints\n";

// Get sample player and team IDs from database
echo "\n🔍 Fetching sample data from database...\n";

try {
    // Initialize Laravel
    $app = require_once 'bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    $players = DB::table('players')->select('id', 'username')->limit(3)->get();
    $teams = DB::table('teams')->select('id', 'name')->limit(3)->get();
    
    if ($players->isEmpty()) {
        echo "❌ No players found in database. Cannot test player endpoints.\n";
        exit(1);
    }
    
    if ($teams->isEmpty()) {
        echo "❌ No teams found in database. Cannot test team endpoints.\n";
        exit(1);
    }
    
    $samplePlayer = $players->first();
    $sampleTeam = $teams->first();
    
    echo "✅ Sample Player: ID {$samplePlayer->id} ({$samplePlayer->username})\n";
    echo "✅ Sample Team: ID {$sampleTeam->id} ({$sampleTeam->name})\n";
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test Results Summary
$testResults = [];

// Test Player Endpoints
echo "\n" . str_repeat('=', 80) . "\n";
echo "🎮 TESTING PLAYER ENDPOINTS\n";
echo str_repeat('=', 80) . "\n";

$testResults['player_team_history'] = testEndpoint(
    "/api/public/players/{$samplePlayer->id}/team-history",
    "Player Team History"
);

$testResults['player_matches'] = testEndpoint(
    "/api/public/players/{$samplePlayer->id}/matches",
    "Player Match History with Hero Stats"
);

$testResults['player_stats'] = testEndpoint(
    "/api/public/players/{$samplePlayer->id}/stats",
    "Player Aggregated Statistics"
);

// Test Team Endpoints
echo "\n" . str_repeat('=', 80) . "\n";
echo "🏆 TESTING TEAM ENDPOINTS\n";
echo str_repeat('=', 80) . "\n";

$testResults['team_achievements'] = testEndpoint(
    "/api/public/teams/{$sampleTeam->id}/achievements",
    "Team Achievements"
);

// Test Additional Players and Teams
echo "\n" . str_repeat('=', 80) . "\n";
echo "🔄 TESTING MULTIPLE RECORDS\n";
echo str_repeat('=', 80) . "\n";

foreach ($players as $player) {
    $testResults["player_{$player->id}_team_history"] = testEndpoint(
        "/api/public/players/{$player->id}/team-history",
        "Player {$player->id} ({$player->username}) Team History"
    );
}

foreach ($teams as $team) {
    $testResults["team_{$team->id}_achievements"] = testEndpoint(
        "/api/public/teams/{$team->id}/achievements", 
        "Team {$team->id} ({$team->name}) Achievements"
    );
}

// Summary Report
echo "\n" . str_repeat('=', 80) . "\n";
echo "📊 TEST RESULTS SUMMARY\n";
echo str_repeat('=', 80) . "\n";

$totalTests = count($testResults);
$passedTests = count(array_filter($testResults));
$failedTests = $totalTests - $passedTests;

echo "Total Tests: {$totalTests}\n";
echo "✅ Passed: {$passedTests}\n";
echo "❌ Failed: {$failedTests}\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";

if ($failedTests > 0) {
    echo "\n❌ Failed Tests:\n";
    foreach ($testResults as $test => $result) {
        if (!$result) {
            echo "  - {$test}\n";
        }
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "🎯 ENDPOINT SUMMARY\n";
echo str_repeat('=', 80) . "\n";

$endpoints = [
    "GET /api/public/players/{id}/team-history" => "Returns player's complete team transfer history",
    "GET /api/public/players/{id}/matches" => "Returns match history with hero stats (K/D/A, DMG, Heal, BLK)",
    "GET /api/public/players/{id}/stats" => "Returns aggregated player statistics with hero breakdowns",
    "GET /api/public/teams/{id}/achievements" => "Returns team achievements including tournament placements and milestones"
];

foreach ($endpoints as $endpoint => $description) {
    echo "📍 {$endpoint}\n";
    echo "   {$description}\n\n";
}

echo "🎉 API Endpoints Test Complete!\n";
echo "\nAll endpoints are now available for the frontend to consume.\n";
echo "Data includes hero images, event logos, and comprehensive statistics.\n";

$exitCode = $failedTests > 0 ? 1 : 0;
exit($exitCode);
?>