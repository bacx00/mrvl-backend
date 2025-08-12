<?php

/**
 * Player API Data Verification Test Script
 * 
 * This script tests the Player API to ensure all required fields are being returned
 * properly from the database and that the data structure is complete.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use App\Http\Controllers\PlayerController;
use App\Models\Player;
use App\Models\Team;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Player API Data Structure Test ===\n\n";

// Test configuration
$testPlayerId = 405; // Testing with existing player 'delenaa'

try {
    echo "1. Testing Player Model direct access...\n";
    
    // Test 1: Direct model access
    $player = Player::with(['team'])->find($testPlayerId);
    
    if (!$player) {
        echo "❌ Player with ID {$testPlayerId} not found. Creating test player...\n";
        
        // Create a test player with all required fields
        $player = Player::create([
            'username' => 'testplayer_' . time(),
            'real_name' => 'Test Player',
            'role' => 'Duelist',
            'main_hero' => 'Spider-Man',
            'country' => 'US',
            'nationality' => 'American',
            'region' => 'NA',
            'rating' => 2500,
            'age' => 25,
            'earnings' => 50000.00,
            'total_earnings' => 50000.00,
            'wins' => 45,
            'losses' => 15,
            'kda' => 1.8,
            'twitter' => 'testplayer',
            'twitch' => 'testplayer_stream',
            'biography' => 'Professional Marvel Rivals player specializing in Duelist role.'
        ]);
        
        $testPlayerId = $player->id;
        echo "✅ Created test player with ID: {$testPlayerId}\n";
    }
    
    echo "✅ Found player: {$player->username}\n\n";
    
    // Test 2: Check all required fields exist in model
    echo "2. Checking required fields in Player model...\n";
    
    $requiredFields = [
        'username', 'real_name', 'earnings', 'nationality', 'country',
        'twitter', 'instagram', 'youtube', 'twitch', 'discord', 'tiktok',
        'rating', 'wins', 'losses', 'kda', 'team_id', 'role'
    ];
    
    $missingFields = [];
    $presentFields = [];
    
    foreach ($requiredFields as $field) {
        if (array_key_exists($field, $player->getAttributes()) || isset($player->{$field})) {
            $presentFields[] = $field;
            echo "  ✅ {$field}: " . ($player->{$field} ?? 'NULL') . "\n";
        } else {
            $missingFields[] = $field;
            echo "  ❌ {$field}: MISSING\n";
        }
    }
    
    echo "\nPresent fields: " . count($presentFields) . "/" . count($requiredFields) . "\n";
    if (!empty($missingFields)) {
        echo "Missing fields: " . implode(', ', $missingFields) . "\n";
    }
    
    echo "\n3. Testing PlayerController API response...\n";
    
    // Test 3: API Controller response
    $controller = new PlayerController();
    $request = Request::create("/api/players/{$testPlayerId}", 'GET');
    
    $response = $controller->show($testPlayerId);
    $responseData = json_decode($response->getContent(), true);
    
    if ($response->getStatusCode() === 200 && $responseData['success']) {
        echo "✅ API request successful\n";
        
        $playerData = $responseData['data'];
        
        // Check API response structure
        $expectedApiFields = [
            'id', 'username', 'real_name', 'avatar', 'country', 'nationality',
            'age', 'region', 'role', 'main_hero', 'rating', 'rank', 'division',
            'wins', 'losses', 'kda', 'total_matches', 'earnings', 'total_earnings',
            'twitter', 'instagram', 'youtube', 'twitch', 'discord', 'tiktok',
            'team_id', 'current_team', 'social_media', 'created_at', 'updated_at'
        ];
        
        echo "\n4. Verifying API response fields...\n";
        
        $apiMissingFields = [];
        $apiPresentFields = [];
        
        foreach ($expectedApiFields as $field) {
            if (array_key_exists($field, $playerData)) {
                $apiPresentFields[] = $field;
                $value = $playerData[$field];
                $displayValue = is_array($value) ? '[ARRAY]' : (is_null($value) ? 'NULL' : $value);
                echo "  ✅ {$field}: {$displayValue}\n";
            } else {
                $apiMissingFields[] = $field;
                echo "  ❌ {$field}: MISSING FROM API\n";
            }
        }
        
        echo "\nAPI fields present: " . count($apiPresentFields) . "/" . count($expectedApiFields) . "\n";
        
        if (!empty($apiMissingFields)) {
            echo "❌ Missing API fields: " . implode(', ', $apiMissingFields) . "\n";
        } else {
            echo "✅ All expected API fields are present!\n";
        }
        
        // Test 5: Data types and validation
        echo "\n5. Validating data types...\n";
        
        $typeValidations = [
            'id' => 'integer',
            'username' => 'string',
            'rating' => 'numeric',
            'wins' => 'integer',
            'losses' => 'integer',
            'kda' => 'numeric',
            'earnings' => 'numeric',
            'social_media' => 'array'
        ];
        
        foreach ($typeValidations as $field => $expectedType) {
            if (isset($playerData[$field])) {
                $value = $playerData[$field];
                $actualType = gettype($value);
                
                $valid = match($expectedType) {
                    'integer' => is_int($value),
                    'numeric' => is_numeric($value),
                    'string' => is_string($value),
                    'array' => is_array($value),
                    default => true
                };
                
                $status = $valid ? '✅' : '❌';
                echo "  {$status} {$field}: {$actualType} (expected: {$expectedType})\n";
            }
        }
        
        // Test 6: Social media fields
        echo "\n6. Testing social media fields...\n";
        
        $socialFields = ['twitter', 'instagram', 'youtube', 'twitch', 'discord', 'tiktok'];
        foreach ($socialFields as $field) {
            $individualField = $playerData[$field] ?? null;
            $socialMediaArray = $playerData['social_media'][$field] ?? null;
            
            echo "  {$field}: individual='{$individualField}', array='{$socialMediaArray}'\n";
        }
        
    } else {
        echo "❌ API request failed\n";
        echo "Status: " . $response->getStatusCode() . "\n";
        echo "Response: " . $response->getContent() . "\n";
    }
    
    echo "\n=== Test Summary ===\n";
    echo "✅ Player model fields: " . count($presentFields) . "/" . count($requiredFields) . " present\n";
    echo "✅ API response fields: " . count($apiPresentFields) . "/" . count($expectedApiFields) . " present\n";
    
    if (empty($missingFields) && empty($apiMissingFields)) {
        echo "🎉 ALL TESTS PASSED! Player API is returning complete data structure.\n";
    } else {
        echo "⚠️  Some issues found. Check missing fields above.\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== End of Test ===\n";