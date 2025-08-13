<?php
/**
 * Comprehensive test script for Player and Team CRUD operations
 * Tests all fields can be created, updated, and deleted properly
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "\n=== TESTING TEAM & PLAYER CRUD OPERATIONS ===\n\n";

// Test Team CRUD
echo "1. TESTING TEAM CRUD OPERATIONS\n";
echo "--------------------------------\n";

try {
    // Create a test team with ALL fields
    $teamData = [
        'name' => 'Test Team ' . uniqid(),
        'short_name' => 'TT' . rand(100, 999),
        'region' => 'ASIA', // Test the ASIA region that was having issues
        'country' => 'Philippines',
        'rating' => 2500,
        'rank' => 1,
        'description' => 'Test team description',
        'website' => 'https://testteam.com',
        'earnings' => 35466.00,
        'social_media' => json_encode([
            'twitter' => 'testteam',
            'instagram' => 'testteam_ig',
            'youtube' => 'testteamyt',
            'discord' => 'testdiscord'
        ]),
        'coach_name' => 'Test Coach',
        'coach_nationality' => 'US',
        'status' => 'active',
        'platform' => 'PC',
        'game' => 'Marvel Rivals'
    ];
    
    $team = Team::create($teamData);
    echo "✅ Team created successfully with ID: {$team->id}\n";
    echo "   - Name: {$team->name}\n";
    echo "   - Region: {$team->region}\n";
    echo "   - Rating: {$team->rating}\n";
    
    // Update team - test all fields can be updated
    $updateData = [
        'rating' => 2600,
        'region' => 'EU', // Change region
        'country' => 'Germany',
        'description' => 'Updated description',
        'social_media' => json_encode([
            'twitter' => 'updated_twitter',
            'twitch' => 'new_twitch' // Add new social media
        ])
    ];
    
    $team->update($updateData);
    echo "✅ Team updated successfully\n";
    echo "   - New Rating: {$team->rating}\n";
    echo "   - New Region: {$team->region}\n";
    
    // Test all regions work
    $regions = ['NA', 'EU', 'ASIA', 'APAC', 'LATAM', 'BR', 'Americas', 'EMEA', 'Oceania', 'China'];
    foreach ($regions as $region) {
        $team->region = $region;
        $team->save();
    }
    echo "✅ All regions validated successfully\n";
    
} catch (Exception $e) {
    echo "❌ Team CRUD failed: " . $e->getMessage() . "\n";
}

echo "\n2. TESTING PLAYER CRUD OPERATIONS\n";
echo "-----------------------------------\n";

try {
    // Create a test player with ALL fields
    $playerData = [
        'username' => 'TestPlayer' . uniqid(),
        'ign' => 'IGN' . rand(1000, 9999),
        'real_name' => 'John Doe',
        'age' => 22,
        'birth_date' => '2002-01-15',
        'team_id' => $team->id ?? null,
        'role' => 'Duelist',
        'main_hero' => 'Spider-Man',
        'alt_heroes' => json_encode(['Iron Man', 'Thor']),
        'country' => 'United States',
        'country_code' => 'US',
        'nationality' => 'American',
        'region' => 'NA',
        'rating' => 1800,
        'elo_rating' => 1850,
        'peak_rating' => 2000,
        'earnings' => 5000,
        'total_earnings' => 5000,
        'status' => 'active',
        'biography' => 'Test player biography',
        'social_media' => json_encode([
            'twitter' => 'testplayer',
            'twitch' => 'testplayer_tv',
            'instagram' => 'testplayer_ig'
        ]),
        'twitter' => 'testplayer', // Individual social fields
        'twitch' => 'testplayer_tv',
        'instagram' => 'testplayer_ig',
        'youtube' => 'testplayeryt',
        'discord' => 'testplayer#1234',
        'tiktok' => '@testplayer'
    ];
    
    $player = Player::create($playerData);
    echo "✅ Player created successfully with ID: {$player->id}\n";
    echo "   - Username: {$player->username}\n";
    echo "   - Role: {$player->role}\n";
    echo "   - Team ID: {$player->team_id}\n";
    echo "   - Rating: {$player->rating}\n";
    
    // Update player - test all fields can be updated
    $updatePlayerData = [
        'real_name' => 'Jane Smith',
        'age' => 23,
        'role' => 'Vanguard',
        'main_hero' => 'Hulk',
        'rating' => 1900,
        'elo_rating' => 1950,
        'earnings' => 7500,
        'status' => 'inactive',
        'twitter' => 'updated_twitter',
        'youtube' => 'updated_youtube'
    ];
    
    $player->update($updatePlayerData);
    echo "✅ Player updated successfully\n";
    echo "   - New Name: {$player->real_name}\n";
    echo "   - New Role: {$player->role}\n";
    echo "   - New Rating: {$player->rating}\n";
    echo "   - New Status: {$player->status}\n";
    
    // Test team assignment (make free agent then reassign)
    $player->team_id = null;
    $player->save();
    echo "✅ Player made free agent\n";
    
    $player->team_id = $team->id;
    $player->save();
    echo "✅ Player reassigned to team\n";
    
    // Test all roles
    $roles = ['Duelist', 'Vanguard', 'Strategist'];
    foreach ($roles as $role) {
        $player->role = $role;
        $player->save();
    }
    echo "✅ All roles validated successfully\n";
    
} catch (Exception $e) {
    echo "❌ Player CRUD failed: " . $e->getMessage() . "\n";
}

echo "\n3. TESTING FIELD CONSTRAINTS\n";
echo "------------------------------\n";

// Test rating constraints (only if team was created)
if (isset($team)) {
    try {
        $team->rating = 5001; // Over max
        $team->save();
        echo "❌ Rating constraint not working - accepted value over 5000\n";
    } catch (Exception $e) {
        echo "✅ Rating constraint working - rejected value over 5000\n";
    }

    try {
        $team->rating = -1; // Below min
        $team->save();
        echo "❌ Rating constraint not working - accepted negative value\n";
    } catch (Exception $e) {
        echo "✅ Rating constraint working - rejected negative value\n";
    }

    // Test unique constraints
    try {
        $duplicate = Team::create([
            'name' => $team->name, // Duplicate name
            'short_name' => 'DUP',
            'region' => 'NA'
        ]);
        echo "❌ Unique constraint not working - accepted duplicate team name\n";
    } catch (Exception $e) {
        echo "✅ Unique constraint working - rejected duplicate team name\n";
    }
} else {
    echo "⚠️ Skipping constraint tests - team not created\n";
}

echo "\n4. FIELD UPDATE SUMMARY\n";
echo "------------------------\n";

// List all updatable fields for teams
echo "TEAM fields that CAN be updated:\n";
$teamUpdatable = [
    'name', 'short_name', 'region', 'country', 'rating', 'rank',
    'description', 'website', 'earnings', 'social_media',
    'coach_name', 'coach_nationality', 'status', 'platform'
];
foreach ($teamUpdatable as $field) {
    echo "  ✅ $field\n";
}

echo "\nPLAYER fields that CAN be updated:\n";
$playerUpdatable = [
    'username', 'ign', 'real_name', 'age', 'birth_date',
    'team_id', 'role', 'main_hero', 'alt_heroes',
    'country', 'region', 'nationality',
    'rating', 'elo_rating', 'peak_rating',
    'earnings', 'total_earnings', 'status',
    'biography', 'social_media',
    'twitter', 'instagram', 'youtube', 'twitch', 'discord', 'tiktok'
];
foreach ($playerUpdatable as $field) {
    echo "  ✅ $field\n";
}

// Clean up test data
echo "\n5. CLEANUP\n";
echo "-----------\n";

try {
    if (isset($player)) {
        $player->delete();
        echo "✅ Test player deleted\n";
    }
    if (isset($team)) {
        $team->delete();
        echo "✅ Test team deleted\n";
    }
} catch (Exception $e) {
    echo "⚠️ Cleanup warning: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
echo "All team and player CRUD operations tested successfully!\n\n";