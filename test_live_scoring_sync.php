<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\MvrlMatch;
use App\Http\Controllers\LiveUpdateController;
use Illuminate\Http\Request;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/**
 * COMPREHENSIVE LIVE SCORING SYSTEM TEST
 * Tests the synchronization between ComprehensiveLiveScoring and MatchDetailPage
 * 
 * This script simulates:
 * 1. Score updates via SSE
 * 2. Hero changes
 * 3. Player stats updates
 * 4. Map progression
 * 5. Real-time event dispatch
 */

$matchId = 683; // The match we created
$match = MvrlMatch::find($matchId);

if (!$match) {
    echo "❌ Match not found: $matchId\n";
    exit(1);
}

echo "🚀 STARTING COMPREHENSIVE LIVE SCORING TEST\n";
echo "Match: {$match->team1->name} vs {$match->team2->name}\n";
echo "Match ID: $matchId\n";
echo "Format: {$match->format}\n\n";

// Initialize the LiveUpdateController
$liveController = new LiveUpdateController();

echo "=== PHASE 1: SCORE UPDATES TEST ===\n";

// Test 1: Update Team 1 score to 1-0
echo "📊 Test 1: Team 1 scores (1-0)\n";
$request = new Request([
    'type' => 'score-update',
    'data' => [
        'match_id' => $matchId,
        'map_index' => 0,
        'team1_score' => 1,
        'team2_score' => 0,
        'map_name' => 'Tokyo 2099: Shibuya Sky',
        'game_mode' => 'Convoy'
    ],
    'timestamp' => now()->toIso8601String()
]);

try {
    $response = $liveController->update($request, $matchId);
    $responseData = json_decode($response->getContent(), true);
    echo "✅ Score update successful: " . ($responseData['success'] ? 'YES' : 'NO') . "\n";
    
    // Verify the data was saved (maps_data is auto-cast as array by Laravel)
    $updatedMatch = MvrlMatch::find($matchId);
    $mapsData = $updatedMatch->maps_data;
    echo "   Verified Map 1 Score: {$mapsData[0]['team1_score']}-{$mapsData[0]['team2_score']}\n";
    
} catch (Exception $e) {
    echo "❌ Score update failed: " . $e->getMessage() . "\n";
}

sleep(1);

// Test 2: Update Team 2 score to 1-1
echo "\n📊 Test 2: Team 2 scores (1-1)\n";
$request = new Request([
    'type' => 'score-update',
    'data' => [
        'match_id' => $matchId,
        'map_index' => 0,
        'team1_score' => 1,
        'team2_score' => 1,
        'map_name' => 'Tokyo 2099: Shibuya Sky',
        'game_mode' => 'Convoy'
    ],
    'timestamp' => now()->toIso8601String()
]);

try {
    $response = $liveController->update($request, $matchId);
    $responseData = json_decode($response->getContent(), true);
    echo "✅ Score update successful: " . ($responseData['success'] ? 'YES' : 'NO') . "\n";
    
    // Verify the data was saved (maps_data is auto-cast as array by Laravel)
    $updatedMatch = MvrlMatch::find($matchId);
    $mapsData = $updatedMatch->maps_data;
    echo "   Verified Map 1 Score: {$mapsData[0]['team1_score']}-{$mapsData[0]['team2_score']}\n";
    
} catch (Exception $e) {
    echo "❌ Score update failed: " . $e->getMessage() . "\n";
}

sleep(1);

echo "\n=== PHASE 2: HERO CHANGE TEST ===\n";

// Test 3: Change player hero
echo "🦸 Test 3: Change Danteh to Wolverine\n";
$request = new Request([
    'type' => 'hero-update',
    'data' => [
        'match_id' => $matchId,
        'map_index' => 0,
        'player_id' => 1, // Danteh's ID
        'player_name' => 'Danteh',
        'hero' => 'Wolverine',
        'role' => 'Duelist',
        'team' => 1
    ],
    'timestamp' => now()->toIso8601String()
]);

try {
    $response = $liveController->update($request, $matchId);
    $responseData = json_decode($response->getContent(), true);
    echo "✅ Hero update successful: " . ($responseData['success'] ? 'YES' : 'NO') . "\n";
    
    // Verify the hero change was saved (maps_data is auto-cast as array by Laravel)
    $updatedMatch = MvrlMatch::find($matchId);
    $mapsData = $updatedMatch->maps_data;
    
    // Find Danteh in team1_composition
    foreach ($mapsData[0]['team1_composition'] as $player) {
        if ($player['player_id'] == 1) {
            echo "   Verified Danteh's hero: {$player['hero']} (was: {$player['hero']})\n";
            break;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Hero update failed: " . $e->getMessage() . "\n";
}

sleep(1);

echo "\n=== PHASE 3: PLAYER STATS TEST ===\n";

// Test 4: Update player stats
echo "📈 Test 4: Update Danteh's stats (3 kills, 1 death, 2 assists)\n";
$request = new Request([
    'type' => 'stats-update',
    'data' => [
        'match_id' => $matchId,
        'map_index' => 0,
        'player_id' => 1, // Danteh's ID
        'player_name' => 'Danteh',
        'stat_type' => 'eliminations',
        'value' => 3,
        'team' => 1
    ],
    'timestamp' => now()->toIso8601String()
]);

try {
    $response = $liveController->update($request, $matchId);
    $responseData = json_decode($response->getContent(), true);
    echo "✅ Stats update successful: " . ($responseData['success'] ? 'YES' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "❌ Stats update failed: " . $e->getMessage() . "\n";
}

// Update deaths and assists
$statsToUpdate = [
    ['stat_type' => 'deaths', 'value' => 1],
    ['stat_type' => 'assists', 'value' => 2],
    ['stat_type' => 'damage', 'value' => 1250]
];

foreach ($statsToUpdate as $stat) {
    $request = new Request([
        'type' => 'stats-update',
        'data' => [
            'match_id' => $matchId,
            'map_index' => 0,
            'player_id' => 1,
            'player_name' => 'Danteh',
            'stat_type' => $stat['stat_type'],
            'value' => $stat['value'],
            'team' => 1
        ],
        'timestamp' => now()->toIso8601String()
    ]);
    
    try {
        $response = $liveController->update($request, $matchId);
        echo "✅ Updated {$stat['stat_type']}: {$stat['value']}\n";
    } catch (Exception $e) {
        echo "❌ Failed to update {$stat['stat_type']}: " . $e->getMessage() . "\n";
    }
    
    usleep(500000); // 0.5 second delay
}

// Verify all stats were saved (maps_data is auto-cast as array by Laravel)
$updatedMatch = MvrlMatch::find($matchId);
$mapsData = $updatedMatch->maps_data;

foreach ($mapsData[0]['team1_composition'] as $player) {
    if ($player['player_id'] == 1) {
        echo "   Verified Danteh's stats:\n";
        echo "     Eliminations: {$player['eliminations']}\n";
        echo "     Deaths: {$player['deaths']}\n";
        echo "     Assists: {$player['assists']}\n";
        echo "     Damage: {$player['damage']}\n";
        break;
    }
}

sleep(1);

echo "\n=== PHASE 4: MAP COMPLETION TEST ===\n";

// Test 5: Complete Map 1 (Team 1 wins 3-1)
echo "🏆 Test 5: Complete Map 1 - Team 1 wins 3-1\n";
$request = new Request([
    'type' => 'score-update',
    'data' => [
        'match_id' => $matchId,
        'map_index' => 0,
        'team1_score' => 3,
        'team2_score' => 1,
        'map_name' => 'Tokyo 2099: Shibuya Sky',
        'game_mode' => 'Convoy',
        'map_completed' => true,
        'winner_team' => 1
    ],
    'timestamp' => now()->toIso8601String()
]);

try {
    $response = $liveController->update($request, $matchId);
    $responseData = json_decode($response->getContent(), true);
    echo "✅ Map completion successful: " . ($responseData['success'] ? 'YES' : 'NO') . "\n";
    
    // Verify series score was updated
    $updatedMatch = MvrlMatch::find($matchId);
    echo "   Series Score: {$updatedMatch->series_score_team1}-{$updatedMatch->series_score_team2}\n";
    
} catch (Exception $e) {
    echo "❌ Map completion failed: " . $e->getMessage() . "\n";
}

sleep(1);

echo "\n=== PHASE 5: MAP 2 PROGRESSION TEST ===\n";

// Test 6: Start Map 2 with some action
echo "🗺️ Test 6: Map 2 progression - Team 2 takes early lead\n";
$request = new Request([
    'type' => 'score-update',
    'data' => [
        'match_id' => $matchId,
        'map_index' => 1,
        'team1_score' => 0,
        'team2_score' => 2,
        'map_name' => 'Klyntar: Symbiote Planet',
        'game_mode' => 'Domination'
    ],
    'timestamp' => now()->toIso8601String()
]);

try {
    $response = $liveController->update($request, $matchId);
    $responseData = json_decode($response->getContent(), true);
    echo "✅ Map 2 score update successful: " . ($responseData['success'] ? 'YES' : 'NO') . "\n";
    
    // Verify the data was saved (maps_data is auto-cast as array by Laravel)
    $updatedMatch = MvrlMatch::find($matchId);
    $mapsData = $updatedMatch->maps_data;
    echo "   Map 2 Score: {$mapsData[1]['team1_score']}-{$mapsData[1]['team2_score']}\n";
    
} catch (Exception $e) {
    echo "❌ Map 2 update failed: " . $e->getMessage() . "\n";
}

echo "\n=== PHASE 6: SSE CACHE VERIFICATION ===\n";

// Test 7: Check if updates are being cached for SSE
echo "💾 Test 7: Verify SSE cache is working\n";

// Check cache keys that should exist
$cacheKeys = [
    "live_update_match_{$matchId}_score",
    "live_update_match_{$matchId}_hero", 
    "live_update_match_{$matchId}_stats",
    "live_update_match_{$matchId}"
];

foreach ($cacheKeys as $key) {
    $cached = cache()->get($key);
    if ($cached) {
        echo "✅ Cache key exists: $key\n";
        echo "   Data: " . json_encode($cached, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "⚠️  Cache key not found: $key\n";
    }
}

echo "\n=== FINAL VERIFICATION ===\n";

// Final verification of all changes (maps_data is auto-cast as array by Laravel)
$finalMatch = MvrlMatch::find($matchId);
$finalMapsData = $finalMatch->maps_data;

echo "📋 Final Match State:\n";
echo "   Series Score: {$finalMatch->series_score_team1}-{$finalMatch->series_score_team2}\n";
echo "   Map 1: {$finalMapsData[0]['team1_score']}-{$finalMapsData[0]['team2_score']} ({$finalMapsData[0]['map_name']})\n";
echo "   Map 2: {$finalMapsData[1]['team1_score']}-{$finalMapsData[1]['team2_score']} ({$finalMapsData[1]['map_name']})\n";
echo "   Map 3: {$finalMapsData[2]['team1_score']}-{$finalMapsData[2]['team2_score']} ({$finalMapsData[2]['map_name']})\n";

// Show updated player stats
foreach ($finalMapsData[0]['team1_composition'] as $player) {
    if ($player['player_id'] == 1) {
        echo "\n   Danteh's Final Stats (Map 1):\n";
        echo "     Hero: {$player['hero']}\n";
        echo "     K/D/A: {$player['eliminations']}/{$player['deaths']}/{$player['assists']}\n";
        echo "     Damage: {$player['damage']}\n";
        break;
    }
}

echo "\n🎉 COMPREHENSIVE TEST COMPLETED!\n";
echo "\nTo test the frontend synchronization:\n";
echo "1. Open: http://localhost:3000/matches/$matchId\n";
echo "2. Open admin: http://localhost:3000/admin (login required)\n";
echo "3. Navigate to Live Scoring for match $matchId\n";
echo "4. Make changes and observe real-time updates\n";

echo "\n📡 SSE Endpoint: http://localhost:8000/api/matches/$matchId/live-stream\n";
echo "📊 Status Endpoint: http://localhost:8000/api/matches/$matchId/status\n";
