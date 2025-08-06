<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\MvrlMatch;
use App\Http\Controllers\LiveUpdateController;
use Illuminate\Http\Request;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$matchId = 683;
$liveController = new LiveUpdateController();

echo "🧪 SINGLE UPDATE TEST - Testing immediate synchronization\n";
echo "📺 Please open: http://localhost:3000/matches/$matchId\n";
echo "🎛️  And open: http://localhost:3000/admin (navigate to Live Scoring)\n\n";

echo "Press ENTER to send a test score update (will update Team 1 score to 2)...";
fgets(STDIN);

$request = new Request([
    'type' => 'score-update',
    'data' => [
        'match_id' => $matchId,
        'map_index' => 0,
        'team1_score' => 2,
        'team2_score' => 1,
        'map_name' => 'Tokyo 2099: Shibuya Sky',
        'game_mode' => 'Convoy'
    ],
    'timestamp' => now()->toIso8601String()
]);

try {
    $response = $liveController->update($request, $matchId);
    $responseData = json_decode($response->getContent(), true);
    
    if ($responseData['success']) {
        echo "✅ Score update sent successfully!\n";
        echo "📊 Team 1: 2, Team 2: 1\n";
        echo "💾 Update cached for SSE pickup\n\n";
        
        echo "🔍 Check the Match Detail Page - the score should update immediately!\n";
        echo "📡 SSE clients should receive this update within 50ms\n\n";
        
    } else {
        echo "❌ Update failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\nPress ENTER to send a hero change (Danteh -> Black Panther)...";
fgets(STDIN);

$request = new Request([
    'type' => 'hero-update',
    'data' => [
        'match_id' => $matchId,
        'map_index' => 0,
        'player_id' => 1,
        'player_name' => 'Danteh',
        'hero' => 'Black Panther',
        'role' => 'Duelist',
        'team' => 1
    ],
    'timestamp' => now()->toIso8601String()
]);

try {
    $response = $liveController->update($request, $matchId);
    $responseData = json_decode($response->getContent(), true);
    
    if ($responseData['success']) {
        echo "✅ Hero change sent successfully!\n";
        echo "🦸 Danteh -> Black Panther\n";
        echo "💾 Update cached for SSE pickup\n\n";
        
        echo "🔍 Check the Match Detail Page - Danteh's hero should change immediately!\n\n";
        
    } else {
        echo "❌ Update failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\nPress ENTER to send player stats update (Danteh: 7 kills, 2 deaths, 3 assists)...";
fgets(STDIN);

// Send multiple stat updates
$stats = [
    ['stat_type' => 'eliminations', 'value' => 7],
    ['stat_type' => 'deaths', 'value' => 2],
    ['stat_type' => 'assists', 'value' => 3],
    ['stat_type' => 'damage', 'value' => 1850]
];

foreach ($stats as $stat) {
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
        $responseData = json_decode($response->getContent(), true);
        
        if ($responseData['success']) {
            echo "✅ {$stat['stat_type']}: {$stat['value']}\n";
        } else {
            echo "❌ Failed to update {$stat['stat_type']}\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception updating {$stat['stat_type']}: " . $e->getMessage() . "\n";
    }
    
    usleep(200000); // 200ms delay between stats
}

echo "\n🔍 Check the Match Detail Page - Danteh's stats should update in real-time!\n";
echo "📈 Expected: 7K/2D/3A, 1850 damage\n\n";

echo "🎉 Test completed! Check both pages for synchronization.\n";