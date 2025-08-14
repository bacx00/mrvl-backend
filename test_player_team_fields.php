<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\Team;
use App\Models\PlayerTeamHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n========================================\n";
echo "COMPREHENSIVE PLAYER & TEAM FIELD TEST\n";
echo "========================================\n";

$testResults = [
    'player_fields' => [],
    'team_fields' => [],
    'player_crud' => [],
    'team_crud' => [],
    'relationships' => [],
    'transfers' => [],
    'coach_management' => [],
    'social_media' => [],
    'statistics' => []
];

// TEST 1: Player Model Fields Completeness
echo "\n[1] Testing Player Model Fields...\n";
echo "------------------------------------\n";

$playerColumns = Schema::getColumnListing('players');
$playerFillable = (new Player())->getFillable();

echo "Total columns in players table: " . count($playerColumns) . "\n";
echo "Fillable fields in Player model: " . count($playerFillable) . "\n";

// Check all expected player fields
$expectedPlayerFields = [
    // Basic Info
    'id', 'name', 'username', 'real_name', 'romanized_name', 'avatar',
    
    // Team Related
    'team_id', 'past_teams', 'role', 'team_position', 'position_order', 'jersey_number',
    
    // Hero/Game Related
    'hero_preferences', 'skill_rating', 'main_hero', 'alt_heroes', 'hero_pool', 'hero_statistics',
    'most_played_hero', 'best_winrate_hero',
    
    // Location
    'region', 'country', 'flag', 'country_flag', 'country_code', 'nationality', 'team_country',
    
    // Ratings & Rankings
    'rank', 'rating', 'elo_rating', 'peak_elo', 'elo_changes', 'last_elo_update', 'peak_rating',
    
    // Personal Info
    'age', 'birth_date',
    
    // Financial
    'earnings', 'earnings_amount', 'earnings_currency', 'total_earnings',
    
    // Statistics
    'wins', 'losses', 'kda', 'total_matches', 'tournaments_played',
    'total_eliminations', 'total_deaths', 'total_assists', 'overall_kda',
    'average_damage_per_match', 'average_healing_per_match', 'average_damage_blocked_per_match',
    'longest_win_streak', 'current_win_streak',
    
    // Social Media
    'social_media', 'twitter', 'instagram', 'twitch', 'tiktok', 'youtube', 
    'facebook', 'discord', 'liquipedia_url',
    
    // Other
    'biography', 'event_placements', 'status',
    'mention_count', 'last_mentioned_at',
    'created_at', 'updated_at'
];

$missingInTable = array_diff($expectedPlayerFields, $playerColumns);
$missingInFillable = array_diff($expectedPlayerFields, array_merge($playerFillable, ['id', 'created_at', 'updated_at']));

$testResults['player_fields']['total_columns'] = count($playerColumns);
$testResults['player_fields']['fillable_count'] = count($playerFillable);
$testResults['player_fields']['missing_in_table'] = $missingInTable;
$testResults['player_fields']['missing_in_fillable'] = $missingInFillable;

if (empty($missingInTable)) {
    echo "✅ All expected fields exist in players table\n";
} else {
    echo "⚠️ Missing fields in table: " . implode(', ', $missingInTable) . "\n";
}

// TEST 2: Team Model Fields Completeness
echo "\n[2] Testing Team Model Fields...\n";
echo "------------------------------------\n";

$teamColumns = Schema::getColumnListing('teams');
$teamFillable = (new Team())->getFillable();

echo "Total columns in teams table: " . count($teamColumns) . "\n";
echo "Fillable fields in Team model: " . count($teamFillable) . "\n";

$expectedTeamFields = [
    // Basic Info
    'id', 'name', 'short_name', 'slug', 'logo',
    
    // Location & Platform
    'region', 'platform', 'game', 'division', 'country', 'flag', 'country_code', 'country_flag',
    
    // Performance
    'rating', 'rank', 'win_rate', 'map_win_rate', 'recent_performance',
    'longest_win_streak', 'current_streak_count', 'current_streak_type',
    'points', 'record', 'wins', 'losses', 'matches_played', 'maps_won', 'maps_lost',
    'tournaments_won', 'peak', 'streak', 'last_match',
    
    // Organization
    'founded', 'founded_date', 'captain', 'coach', 'manager',
    'coach_picture', 'coach_image', 'coach_name', 'coach_nationality', 'coach_social_media',
    
    // Info & Social
    'description', 'website', 'liquipedia_url', 'twitter', 'instagram',
    'youtube', 'twitch', 'tiktok', 'discord', 'facebook', 'social_media', 'social_links',
    
    // Other
    'achievements', 'recent_form', 'player_count', 'status', 'earnings', 'owner',
    'elo_rating', 'peak_elo', 'elo_changes', 'last_elo_update', 'ranking',
    'mention_count', 'last_mentioned_at',
    'created_at', 'updated_at'
];

$missingTeamInTable = array_diff($expectedTeamFields, $teamColumns);
$missingTeamInFillable = array_diff($expectedTeamFields, array_merge($teamFillable, ['id', 'created_at', 'updated_at']));

$testResults['team_fields']['total_columns'] = count($teamColumns);
$testResults['team_fields']['fillable_count'] = count($teamFillable);
$testResults['team_fields']['missing_in_table'] = $missingTeamInTable;
$testResults['team_fields']['missing_in_fillable'] = $missingTeamInFillable;

if (empty($missingTeamInTable)) {
    echo "✅ All expected fields exist in teams table\n";
} else {
    echo "⚠️ Missing fields in table: " . implode(', ', $missingTeamInTable) . "\n";
}

// TEST 3: Player CRUD Operations
echo "\n[3] Testing Player CRUD Operations...\n";
echo "------------------------------------\n";

try {
    // Create test player with ALL fields
    $testPlayerData = [
        'username' => 'test_player_' . time(),
        'name' => 'Test Player Full',
        'real_name' => 'John Test Doe',
        'romanized_name' => 'John Doe',
        'avatar' => '/images/test-avatar.png',
        'team_id' => 1,
        'past_teams' => json_encode([1, 2, 3]),
        'role' => 'Duelist',
        'team_position' => 'player',
        'position_order' => 1,
        'jersey_number' => 99,
        'hero_preferences' => json_encode(['Spider-Man', 'Iron Man']),
        'skill_rating' => 3500,
        'main_hero' => 'Spider-Man',
        'alt_heroes' => json_encode(['Iron Man', 'Black Widow']),
        'region' => 'NA',
        'country' => 'United States',
        'flag' => '🇺🇸',
        'country_flag' => '/flags/us.png',
        'country_code' => 'US',
        'nationality' => 'American',
        'team_country' => 'United States',
        'rank' => 100,
        'rating' => 2500,
        'elo_rating' => 2600,
        'peak_elo' => 2800,
        'elo_changes' => 50,
        'last_elo_update' => now(),
        'peak_rating' => 2700,
        'age' => 22,
        'birth_date' => '2002-01-15',
        'earnings' => 50000.00,
        'earnings_amount' => 50000.00,
        'earnings_currency' => 'USD',
        'total_earnings' => 75000.00,
        'wins' => 150,
        'losses' => 50,
        'kda' => 3.5,
        'total_matches' => 200,
        'tournaments_played' => 15,
        'social_media' => json_encode([
            'twitter' => 'testplayer',
            'twitch' => 'testplayer_tv',
            'youtube' => 'testplayeryt'
        ]),
        'twitter' => 'testplayer',
        'instagram' => 'testplayer_ig',
        'twitch' => 'testplayer_tv',
        'tiktok' => 'testplayer_tok',
        'youtube' => 'testplayeryt',
        'facebook' => 'testplayer_fb',
        'discord' => 'testplayer#1234',
        'liquipedia_url' => 'https://liquipedia.net/testplayer',
        'biography' => 'Professional esports player with extensive experience.',
        'event_placements' => json_encode(['1st - Tournament A', '2nd - Tournament B']),
        'hero_pool' => json_encode(['Spider-Man', 'Iron Man', 'Black Widow']),
        'status' => 'active',
        'total_eliminations' => 5000,
        'total_deaths' => 1500,
        'total_assists' => 3000,
        'overall_kda' => 3.5,
        'average_damage_per_match' => 15000,
        'average_healing_per_match' => 0,
        'average_damage_blocked_per_match' => 0,
        'hero_statistics' => json_encode(['Spider-Man' => ['matches' => 100, 'wins' => 75]]),
        'most_played_hero' => 'Spider-Man',
        'best_winrate_hero' => 'Iron Man',
        'longest_win_streak' => 15,
        'current_win_streak' => 5,
        'mention_count' => 0,
        'last_mentioned_at' => null
    ];
    
    $testPlayer = Player::create($testPlayerData);
    echo "✅ Player created with ID: " . $testPlayer->id . "\n";
    $testResults['player_crud']['create'] = 'success';
    
    // Update test
    $testPlayer->update([
        'rating' => 2600,
        'wins' => 151,
        'kda' => 3.6
    ]);
    echo "✅ Player updated successfully\n";
    $testResults['player_crud']['update'] = 'success';
    
    // Read test
    $readPlayer = Player::find($testPlayer->id);
    if ($readPlayer && $readPlayer->rating == 2600) {
        echo "✅ Player read successfully\n";
        $testResults['player_crud']['read'] = 'success';
    }
    
    // Delete test
    $testPlayer->delete();
    echo "✅ Player deleted successfully\n";
    $testResults['player_crud']['delete'] = 'success';
    
} catch (\Exception $e) {
    echo "❌ Player CRUD Error: " . $e->getMessage() . "\n";
    $testResults['player_crud']['error'] = $e->getMessage();
}

// TEST 4: Team CRUD Operations
echo "\n[4] Testing Team CRUD Operations...\n";
echo "------------------------------------\n";

try {
    // Create test team with ALL fields
    $testTeamData = [
        'name' => 'Test Team ' . time(),
        'short_name' => 'TT' . rand(100, 999),
        'slug' => 'test-team-' . time(),
        'logo' => '/images/test-logo.png',
        'region' => 'NA',
        'platform' => 'PC',
        'game' => 'Marvel Rivals',
        'division' => 'Premier',
        'country' => 'United States',
        'flag' => '🇺🇸',
        'country_code' => 'US',
        'country_flag' => '/flags/us.png',
        'rating' => 2000,
        'rank' => 10,
        'win_rate' => 65.5,
        'map_win_rate' => 62.3,
        'recent_performance' => json_encode(['W', 'W', 'L', 'W', 'W']),
        'longest_win_streak' => 8,
        'current_streak_count' => 3,
        'current_streak_type' => 'win',
        'points' => 150,
        'record' => '15-5',
        'wins' => 15,
        'losses' => 5,
        'matches_played' => 20,
        'maps_won' => 45,
        'maps_lost' => 20,
        'tournaments_won' => 2,
        'peak' => 2200,
        'streak' => '3W',
        'last_match' => now(),
        'founded' => '2023',
        'founded_date' => '2023-01-01',
        'captain' => 'Player1',
        'coach' => 'Coach Name',
        'manager' => 'Manager Name',
        'coach_picture' => '/images/coach.jpg',
        'coach_image' => '/images/coach.jpg',
        'coach_name' => 'John Coach',
        'coach_nationality' => 'American',
        'coach_social_media' => json_encode(['twitter' => 'coach_tw']),
        'description' => 'Professional esports organization',
        'website' => 'https://testteam.com',
        'liquipedia_url' => 'https://liquipedia.net/testteam',
        'twitter' => 'testteam',
        'instagram' => 'testteam_ig',
        'youtube' => 'testteamyt',
        'twitch' => 'testteam_tv',
        'tiktok' => 'testteam_tok',
        'discord' => 'discord.gg/testteam',
        'facebook' => 'testteam_fb',
        'social_media' => json_encode(['twitter' => 'testteam', 'twitch' => 'testteam_tv']),
        'social_links' => json_encode(['website' => 'https://testteam.com']),
        'achievements' => json_encode(['Champion 2023', 'Runner-up 2024']),
        'recent_form' => json_encode(['W', 'W', 'L', 'W', 'W']),
        'player_count' => 6,
        'status' => 'active',
        'earnings' => 250000.00,
        'owner' => 'Test Organization LLC',
        'elo_rating' => 2100,
        'peak_elo' => 2300,
        'elo_changes' => json_encode(['+50', '-30', '+40']),
        'last_elo_update' => now(),
        'ranking' => 10,
        'mention_count' => 0,
        'last_mentioned_at' => null
    ];
    
    $testTeam = Team::create($testTeamData);
    echo "✅ Team created with ID: " . $testTeam->id . "\n";
    $testResults['team_crud']['create'] = 'success';
    
    // Update test
    $testTeam->update([
        'rating' => 2100,
        'wins' => 16,
        'win_rate' => 68.0
    ]);
    echo "✅ Team updated successfully\n";
    $testResults['team_crud']['update'] = 'success';
    
    // Read test
    $readTeam = Team::find($testTeam->id);
    if ($readTeam && $readTeam->rating == 2100) {
        echo "✅ Team read successfully\n";
        $testResults['team_crud']['read'] = 'success';
    }
    
    // Delete test
    $testTeam->delete();
    echo "✅ Team deleted successfully\n";
    $testResults['team_crud']['delete'] = 'success';
    
} catch (\Exception $e) {
    echo "❌ Team CRUD Error: " . $e->getMessage() . "\n";
    $testResults['team_crud']['error'] = $e->getMessage();
}

// TEST 5: Player-Team Relationships
echo "\n[5] Testing Player-Team Relationships...\n";
echo "------------------------------------\n";

try {
    $team = Team::first();
    $player = Player::where('team_id', $team->id)->first();
    
    if ($player) {
        // Test relationship
        $playerTeam = $player->team;
        if ($playerTeam && $playerTeam->id == $team->id) {
            echo "✅ Player->Team relationship works\n";
            $testResults['relationships']['player_team'] = 'success';
        }
        
        // Test reverse relationship
        $teamPlayers = $team->players;
        if ($teamPlayers->contains('id', $player->id)) {
            echo "✅ Team->Players relationship works\n";
            $testResults['relationships']['team_players'] = 'success';
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Relationship Error: " . $e->getMessage() . "\n";
    $testResults['relationships']['error'] = $e->getMessage();
}

// TEST 6: Transfer History
echo "\n[6] Testing Transfer History...\n";
echo "------------------------------------\n";

try {
    // Create a test transfer
    $player = Player::whereNotNull('team_id')->first();
    if ($player) {
        $oldTeamId = $player->team_id;
        $newTeamId = Team::where('id', '!=', $oldTeamId)->first()->id;
        
        $transfer = PlayerTeamHistory::create([
            'player_id' => $player->id,
            'from_team_id' => $oldTeamId,
            'to_team_id' => $newTeamId,
            'change_date' => now(),
            'change_type' => 'transferred',
            'reason' => 'Test transfer',
            'is_official' => true
        ]);
        
        echo "✅ Transfer history created with ID: " . $transfer->id . "\n";
        $testResults['transfers']['create'] = 'success';
        
        // Test relationships
        if ($transfer->player && $transfer->fromTeam && $transfer->toTeam) {
            echo "✅ Transfer relationships work\n";
            $testResults['transfers']['relationships'] = 'success';
        }
        
        // Clean up
        $transfer->delete();
    }
    
} catch (\Exception $e) {
    echo "❌ Transfer History Error: " . $e->getMessage() . "\n";
    $testResults['transfers']['error'] = $e->getMessage();
}

// TEST 7: Coach Management
echo "\n[7] Testing Coach Management...\n";
echo "------------------------------------\n";

try {
    $team = Team::first();
    
    // Update coach info
    $team->update([
        'coach_name' => 'Test Coach',
        'coach_nationality' => 'Canadian',
        'coach_social_media' => json_encode(['twitter' => 'coach_test'])
    ]);
    
    echo "✅ Coach information updated\n";
    $testResults['coach_management']['update'] = 'success';
    
    // Check coach fields
    $team->refresh();
    if ($team->coach_name == 'Test Coach') {
        echo "✅ Coach fields accessible\n";
        $testResults['coach_management']['fields'] = 'success';
    }
    
} catch (\Exception $e) {
    echo "❌ Coach Management Error: " . $e->getMessage() . "\n";
    $testResults['coach_management']['error'] = $e->getMessage();
}

// TEST 8: Social Media Fields
echo "\n[8] Testing Social Media Fields...\n";
echo "------------------------------------\n";

try {
    $player = Player::first();
    $team = Team::first();
    
    // Test player social media
    $playerSocial = [
        'twitter' => 'player_twitter',
        'instagram' => 'player_insta',
        'twitch' => 'player_twitch',
        'youtube' => 'player_yt'
    ];
    
    $player->update([
        'social_media' => json_encode($playerSocial),
        'twitter' => 'player_twitter',
        'instagram' => 'player_insta'
    ]);
    
    echo "✅ Player social media fields updated\n";
    $testResults['social_media']['player'] = 'success';
    
    // Test team social media
    $teamSocial = [
        'twitter' => 'team_twitter',
        'discord' => 'discord.gg/team',
        'website' => 'https://team.com'
    ];
    
    $team->update([
        'social_media' => json_encode($teamSocial),
        'twitter' => 'team_twitter',
        'discord' => 'discord.gg/team'
    ]);
    
    echo "✅ Team social media fields updated\n";
    $testResults['social_media']['team'] = 'success';
    
} catch (\Exception $e) {
    echo "❌ Social Media Error: " . $e->getMessage() . "\n";
    $testResults['social_media']['error'] = $e->getMessage();
}

// TEST 9: Statistics and Analytics
echo "\n[9] Testing Statistics & Analytics...\n";
echo "------------------------------------\n";

try {
    $player = Player::first();
    
    // Test statistical fields
    $stats = [
        'total_eliminations' => 1000,
        'total_deaths' => 300,
        'total_assists' => 500,
        'overall_kda' => 3.33,
        'average_damage_per_match' => 12000,
        'wins' => 75,
        'losses' => 25,
        'total_matches' => 100
    ];
    
    $player->update($stats);
    echo "✅ Player statistics updated\n";
    $testResults['statistics']['player_stats'] = 'success';
    
    // Test team statistics
    $team = Team::first();
    $teamStats = [
        'wins' => 50,
        'losses' => 20,
        'win_rate' => 71.4,
        'maps_won' => 150,
        'maps_lost' => 75,
        'map_win_rate' => 66.7
    ];
    
    $team->update($teamStats);
    echo "✅ Team statistics updated\n";
    $testResults['statistics']['team_stats'] = 'success';
    
    // Test ELO ratings
    $player->update(['elo_rating' => 2500, 'peak_elo' => 2650]);
    $team->update(['elo_rating' => 2400, 'peak_elo' => 2550]);
    
    echo "✅ ELO ratings updated\n";
    $testResults['statistics']['elo_ratings'] = 'success';
    
} catch (\Exception $e) {
    echo "❌ Statistics Error: " . $e->getMessage() . "\n";
    $testResults['statistics']['error'] = $e->getMessage();
}

// Generate Summary Report
echo "\n========================================\n";
echo "TEST SUMMARY REPORT\n";
echo "========================================\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

foreach ($testResults as $category => $results) {
    $categoryPassed = 0;
    $categoryFailed = 0;
    
    foreach ($results as $test => $result) {
        $totalTests++;
        if ($result === 'success') {
            $passedTests++;
            $categoryPassed++;
        } elseif (is_array($result) && empty($result)) {
            $passedTests++;
            $categoryPassed++;
        } else {
            $failedTests++;
            $categoryFailed++;
        }
    }
    
    $status = $categoryFailed > 0 ? '⚠️' : '✅';
    echo "$status " . ucfirst(str_replace('_', ' ', $category)) . ": $categoryPassed passed, $categoryFailed failed\n";
}

echo "\n------------------------------------\n";
echo "Overall Results:\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: $failedTests\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n";

// Save detailed report
$reportFile = '/var/www/mrvl-backend/player_team_test_report_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($reportFile, json_encode($testResults, JSON_PRETTY_PRINT));
echo "\nDetailed report saved to: $reportFile\n";

// Show field coverage
echo "\n========================================\n";
echo "FIELD COVERAGE ANALYSIS\n";
echo "========================================\n";

echo "\nPlayer Model Coverage:\n";
echo "- Database columns: " . count($playerColumns) . "\n";
echo "- Fillable fields: " . count($playerFillable) . "\n";
echo "- Coverage: " . round((count($playerFillable) / count($playerColumns)) * 100, 2) . "%\n";

echo "\nTeam Model Coverage:\n";
echo "- Database columns: " . count($teamColumns) . "\n";
echo "- Fillable fields: " . count($teamFillable) . "\n";
echo "- Coverage: " . round((count($teamFillable) / count($teamColumns)) * 100, 2) . "%\n";

echo "\n========================================\n";
echo "TEST COMPLETE\n";
echo "========================================\n\n";