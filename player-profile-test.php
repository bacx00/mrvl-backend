<?php
/**
 * Test script to verify player profile display fixes
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::createFromGlobals()
);

use App\Http\Controllers\PlayerController;
use Illuminate\Http\Request;

echo "Testing Player Profile Display Fixes\n";
echo "====================================\n\n";

try {
    $controller = new PlayerController();
    
    // Test with a sample player ID (assuming ID 1 exists)
    $testPlayerId = 1;
    
    echo "Testing player profile display for Player ID: {$testPlayerId}\n";
    
    $response = $controller->show($testPlayerId);
    $data = json_decode($response->getContent(), true);
    
    if (isset($data['success']) && $data['success']) {
        $player = $data['data'];
        
        echo "✓ Player found: " . $player['username'] . "\n";
        
        // Check current team display
        if (isset($player['current_team']) && $player['current_team']) {
            echo "✓ Current team displayed: " . $player['current_team']['name'] . "\n";
        } else {
            echo "! No current team (expected if player has no team_id)\n";
        }
        
        // Check that team history is removed
        if (!isset($player['team_history'])) {
            echo "✓ Team history section successfully removed\n";
        } else {
            echo "✗ Team history section still present\n";
        }
        
        // Check recent matches
        if (isset($player['recent_matches'])) {
            $matchCount = count($player['recent_matches']);
            echo "✓ Recent matches available: {$matchCount} matches\n";
            
            if ($matchCount > 0) {
                $firstMatch = $player['recent_matches'][0];
                echo "  - First match: vs " . $firstMatch['opponent_name'] . " (" . $firstMatch['result'] . ")\n";
            }
        } else {
            echo "! No recent matches data\n";
        }
        
        echo "\nProfile data structure:\n";
        echo "- ID: " . $player['id'] . "\n";
        echo "- Username: " . $player['username'] . "\n";
        echo "- Real Name: " . ($player['real_name'] ?? 'Not set') . "\n";
        echo "- Role: " . ($player['role'] ?? 'Not set') . "\n";
        echo "- Rating: " . ($player['rating'] ?? 'Not set') . "\n";
        echo "- Current Team: " . ($player['current_team']['name'] ?? 'No current team') . "\n";
        echo "- Match Count: " . ($player['total_matches'] ?? 0) . "\n";
        
    } else {
        echo "✗ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";