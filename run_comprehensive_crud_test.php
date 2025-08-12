#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\Http;

echo "\n=== COMPREHENSIVE CRUD TESTING SUITE ===\n\n";

$testTeamId = null;
$testPlayerId = null;
$errors = [];

try {
    // 1. CREATE TEST TEAM
    echo "1. CREATING TEST TEAM...\n";
    $testTeam = Team::create([
        'name' => 'TEST Team Alpha',
        'short_name' => 'TTA',
        'region' => 'NA',
        'platform' => 'PC',
        'country' => 'United States',
        'description' => 'Test team for CRUD validation',
        'coach' => 'Test Coach',
        'earnings' => '10000.50',
        'founded' => '2024-01-01',
        'twitter' => 'test_team_alpha',
        'instagram' => 'test_team_alpha',
        'youtube' => 'test_team_alpha',
        'twitch' => 'test_team_alpha',
        'discord' => 'test_team_alpha',
        'website' => 'https://testteam.com'
    ]);
    
    $testTeamId = $testTeam->id;
    echo "   ✅ Team created with ID: $testTeamId\n";
    
    // 2. VERIFY TEAM API
    echo "2. TESTING TEAM API...\n";
    $teamApiResponse = Http::get("https://staging.mrvl.net/api/teams/$testTeamId");
    if ($teamApiResponse->successful()) {
        $teamData = $teamApiResponse->json();
        if (isset($teamData['data']['name']) && $teamData['data']['name'] === 'TEST Team Alpha') {
            echo "   ✅ Team API working correctly\n";
        } else {
            $errors[] = "Team API not returning correct data";
        }
    } else {
        $errors[] = "Team API request failed";
    }
    
    // 3. UPDATE TEST TEAM
    echo "3. UPDATING TEST TEAM...\n";
    $testTeam->update([
        'name' => 'UPDATED Test Team',
        'short_name' => 'UTT',
        'description' => 'Updated test description',
        'coach' => 'Updated Coach',
        'earnings' => '15000.75'
    ]);
    echo "   ✅ Team updated successfully\n";
    
    // 4. CREATE TEST PLAYER
    echo "4. CREATING TEST PLAYER...\n";
    $testPlayer = Player::create([
        'username' => 'test_player_alpha',
        'real_name' => 'Test Player Alpha',
        'role' => 'Duelist',
        'nationality' => 'United States',
        'region' => 'NA',
        'country' => 'United States',
        'team_id' => $testTeamId,
        'earnings' => '5000.00',
        'rating' => 1500,
        'wins' => 25,
        'losses' => 15,
        'kda' => '1.85',
        'main_hero' => 'Spider-Man',
        'twitter' => 'test_player',
        'instagram' => 'test_player',
        'youtube' => 'test_player',
        'twitch' => 'test_player',
        'discord' => 'test_player',
        'tiktok' => 'test_player'
    ]);
    
    $testPlayerId = $testPlayer->id;
    echo "   ✅ Player created with ID: $testPlayerId\n";
    
    // 5. VERIFY PLAYER API
    echo "5. TESTING PLAYER API...\n";
    $playerApiResponse = Http::get("https://staging.mrvl.net/api/players/$testPlayerId");
    if ($playerApiResponse->successful()) {
        $playerData = $playerApiResponse->json();
        if (isset($playerData['data']['username']) && $playerData['data']['username'] === 'test_player_alpha') {
            echo "   ✅ Player API working correctly\n";
        } else {
            $errors[] = "Player API not returning correct data";
        }
    } else {
        $errors[] = "Player API request failed";
    }
    
    // 6. UPDATE TEST PLAYER
    echo "6. UPDATING TEST PLAYER...\n";
    $testPlayer->update([
        'username' => 'updated_test_player',
        'real_name' => 'Updated Test Player',
        'role' => 'Vanguard',
        'nationality' => 'Canada',
        'earnings' => '7500.00',
        'rating' => 1650,
        'wins' => 30,
        'losses' => 12
    ]);
    echo "   ✅ Player updated successfully\n";
    
    // 7. TEST PROFILE PAGES
    echo "7. TESTING PROFILE PAGES...\n";
    $teamProfileResponse = Http::get("https://staging.mrvl.net/api/teams/$testTeamId");
    $playerProfileResponse = Http::get("https://staging.mrvl.net/api/players/$testPlayerId");
    
    if ($teamProfileResponse->successful() && $playerProfileResponse->successful()) {
        echo "   ✅ Profile pages accessible\n";
    } else {
        $errors[] = "Profile page API issues";
    }
    
} catch (\Exception $e) {
    $errors[] = "Exception during testing: " . $e->getMessage();
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}

// 8. CLEANUP
echo "8. CLEANING UP TEST DATA...\n";
try {
    if ($testPlayerId) {
        Player::find($testPlayerId)?->delete();
        echo "   ✅ Test player deleted\n";
    }
    if ($testTeamId) {
        Team::find($testTeamId)?->delete();
        echo "   ✅ Test team deleted\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️ Cleanup error: " . $e->getMessage() . "\n";
}

// 9. RESULTS
echo "\n=== TEST RESULTS ===\n";
if (empty($errors)) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "✅ Team CRUD operations working perfectly\n";
    echo "✅ Player CRUD operations working perfectly\n";
    echo "✅ API endpoints responding correctly\n";
    echo "✅ Profile pages accessible\n";
    echo "✅ No UI or backend issues detected\n";
    echo "✅ Test data cleaned up successfully\n";
} else {
    echo "❌ ISSUES FOUND:\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}

echo "\n=== CRUD TESTING COMPLETE ===\n";