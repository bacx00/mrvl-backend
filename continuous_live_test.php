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
 * CONTINUOUS LIVE UPDATE TEST
 * 
 * This script continuously sends live updates to test real-time synchronization
 * between the admin panel and the match detail page.
 * 
 * Run this script while watching:
 * - http://localhost:3000/matches/683 (Match Detail Page)
 * - http://localhost:3000/admin (Admin Panel Live Scoring)
 */

$matchId = 683;
$match = MvrlMatch::find($matchId);

if (!$match) {
    echo "❌ Match not found: $matchId\n";
    exit(1);
}

echo "🚀 STARTING CONTINUOUS LIVE UPDATE TEST\n";
echo "Match: {$match->team1->name} vs {$match->team2->name}\n";
echo "📺 Open: http://localhost:3000/matches/$matchId\n";
echo "🎛️  Admin: http://localhost:3000/admin\n";
echo "Press Ctrl+C to stop...\n\n";

// Initialize the LiveUpdateController
$liveController = new LiveUpdateController();

// Test scenarios
$scenarios = [
    // Map 1 progression
    ['type' => 'score-update', 'data' => ['match_id' => $matchId, 'map_index' => 0, 'team1_score' => 2, 'team2_score' => 1, 'map_name' => 'Tokyo 2099: Shibuya Sky', 'game_mode' => 'Convoy'], 'message' => '📊 Map 1: Team 1 leads 2-1'],
    ['type' => 'hero-update', 'data' => ['match_id' => $matchId, 'map_index' => 0, 'player_id' => 7, 'player_name' => 'Surefour', 'hero' => 'Spider-Man', 'role' => 'Duelist', 'team' => 2], 'message' => '🦸 Surefour switches to Spider-Man'],
    ['type' => 'stats-update', 'data' => ['match_id' => $matchId, 'map_index' => 0, 'player_id' => 1, 'player_name' => 'Danteh', 'stat_type' => 'eliminations', 'value' => 5, 'team' => 1], 'message' => '📈 Danteh: 5 eliminations'],
    ['type' => 'score-update', 'data' => ['match_id' => $matchId, 'map_index' => 0, 'team1_score' => 3, 'team2_score' => 1, 'map_name' => 'Tokyo 2099: Shibuya Sky', 'game_mode' => 'Convoy'], 'message' => '📊 Map 1: Final score 3-1 (Team 1 wins)'],
    
    // Map 2 action  
    ['type' => 'score-update', 'data' => ['match_id' => $matchId, 'map_index' => 1, 'team1_score' => 1, 'team2_score' => 2, 'map_name' => 'Klyntar: Symbiote Planet', 'game_mode' => 'Domination'], 'message' => '📊 Map 2: Team 2 leads 2-1'],
    ['type' => 'hero-update', 'data' => ['match_id' => $matchId, 'map_index' => 1, 'player_id' => 8, 'player_name' => 'Kevster', 'hero' => 'Iron Man', 'role' => 'Duelist', 'team' => 2], 'message' => '🦸 Kevster switches to Iron Man'],
    ['type' => 'stats-update', 'data' => ['match_id' => $matchId, 'map_index' => 1, 'player_id' => 8, 'player_name' => 'Kevster', 'stat_type' => 'damage', 'value' => 2100, 'team' => 2], 'message' => '📈 Kevster: 2100 damage'],
    ['type' => 'score-update', 'data' => ['match_id' => $matchId, 'map_index' => 1, 'team1_score' => 1, 'team2_score' => 3, 'map_name' => 'Klyntar: Symbiote Planet', 'game_mode' => 'Domination'], 'message' => '📊 Map 2: Final score 1-3 (Team 2 wins)'],
    
    // Map 3 deciding match
    ['type' => 'score-update', 'data' => ['match_id' => $matchId, 'map_index' => 2, 'team1_score' => 1, 'team2_score' => 0, 'map_name' => 'Asgard: Royal Palace', 'game_mode' => 'Convergence'], 'message' => '📊 Map 3: Team 1 scores first 1-0'],
    ['type' => 'hero-update', 'data' => ['match_id' => $matchId, 'map_index' => 2, 'player_id' => 3, 'player_name' => 'Punk', 'hero' => 'Thor', 'role' => 'Vanguard', 'team' => 1], 'message' => '🦸 Punk switches to Thor'],
    ['type' => 'stats-update', 'data' => ['match_id' => $matchId, 'map_index' => 2, 'player_id' => 3, 'player_name' => 'Punk', 'stat_type' => 'assists', 'value' => 4, 'team' => 1], 'message' => '📈 Punk: 4 assists'],
    ['type' => 'score-update', 'data' => ['match_id' => $matchId, 'map_index' => 2, 'team1_score' => 2, 'team2_score' => 2, 'map_name' => 'Asgard: Royal Palace', 'game_mode' => 'Convergence'], 'message' => '📊 Map 3: Tied 2-2 - clutch time!'],
    ['type' => 'score-update', 'data' => ['match_id' => $matchId, 'map_index' => 2, 'team1_score' => 3, 'team2_score' => 2, 'map_name' => 'Asgard: Royal Palace', 'game_mode' => 'Convergence'], 'message' => '🏆 Map 3: Team 1 wins 3-2! Series 2-1!'],
];

$currentScenario = 0;
$updateCount = 0;

echo "⏰ Starting updates in 5 seconds...\n";
sleep(5);

while (true) {
    $scenario = $scenarios[$currentScenario % count($scenarios)];
    
    echo "\n" . ($updateCount + 1) . ". " . $scenario['message'] . "\n";
    
    $request = new Request([
        'type' => $scenario['type'],
        'data' => $scenario['data'],
        'timestamp' => now()->toIso8601String()
    ]);

    try {
        $response = $liveController->update($request, $matchId);
        $responseData = json_decode($response->getContent(), true);
        
        if ($responseData['success']) {
            echo "   ✅ Update sent successfully\n";
            
            // Show cache status
            $cacheKey = "live_update_match_{$matchId}_" . str_replace('-update', '', $scenario['type']);
            $cached = cache()->get($cacheKey);
            if ($cached) {
                echo "   📦 Cached for SSE pickup\n";
            }
            
        } else {
            echo "   ❌ Update failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
    }
    
    $updateCount++;
    $currentScenario++;
    
    // Wait between updates (adjust this for desired pace)
    echo "   ⏳ Waiting 8 seconds before next update...\n";
    sleep(8);
    
    // Optional: Stop after all scenarios
    if ($currentScenario >= count($scenarios)) {
        echo "\n🎉 All scenarios completed! Starting over...\n";
        $currentScenario = 0;
        sleep(5);
    }
}