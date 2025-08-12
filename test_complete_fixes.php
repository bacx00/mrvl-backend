#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Player;
use App\Services\MentionService;

echo "\n=== TESTING ALL FIXES ===\n\n";

// 1. Test Liquipedia URLs removal
echo "1. LIQUIPEDIA URL TEST:\n";
$teamsWithLiquipedia = Team::where('logo', 'LIKE', '%liquipedia%')->count();
$playersWithLiquipedia = Player::where('avatar', 'LIKE', '%liquipedia%')->count();
echo "   Teams with Liquipedia URLs: $teamsWithLiquipedia\n";
echo "   Players with Liquipedia URLs: $playersWithLiquipedia\n";
echo "   Status: " . (($teamsWithLiquipedia + $playersWithLiquipedia) == 0 ? "✅ PASSED" : "❌ FAILED") . "\n\n";

// 2. Test placeholder images
echo "2. PLACEHOLDER IMAGES TEST:\n";
$teamPlaceholder = file_exists(__DIR__ . '/public/images/team-placeholder.svg');
$playerPlaceholder = file_exists(__DIR__ . '/public/images/player-placeholder.svg');
echo "   Team placeholder exists: " . ($teamPlaceholder ? "✅" : "❌") . "\n";
echo "   Player placeholder exists: " . ($playerPlaceholder ? "✅" : "❌") . "\n";

// Check if placeholders have question mark
if ($teamPlaceholder) {
    $teamContent = file_get_contents(__DIR__ . '/public/images/team-placeholder.svg');
    $hasQuestionMark = strpos($teamContent, '?') !== false || strpos($teamContent, 'M12 4') !== false;
    echo "   Team placeholder has '?': " . ($hasQuestionMark ? "✅" : "❌") . "\n";
}
echo "\n";

// 3. Test mentions system
echo "3. MENTIONS SYSTEM TEST:\n";
try {
    $mentionService = new MentionService();
    $testContent = "Hey @admin check this @team:Sentinels and @player:delenaa";
    
    // Check if parse method exists
    $methods = get_class_methods($mentionService);
    echo "   Available methods: " . implode(', ', array_slice($methods, 0, 5)) . "...\n";
    
    // Test extracting mentions
    $extractedMentions = $mentionService->extractMentions($testContent);
    echo "   Extracted mentions: " . (count($extractedMentions) > 0 ? "✅ Found " . count($extractedMentions) : "❌ None found") . "\n";
    
    // Test storing mentions (correct parameters)
    $thread = \App\Models\Thread::first();
    if ($thread) {
        $mentionCount = $mentionService->storeMentions($testContent, 'thread', $thread->id);
        echo "   Stored mentions: " . ($mentionCount > 0 ? "✅ Stored " . $mentionCount : "⚠️ None stored") . "\n";
    } else {
        echo "   Store mentions: ⚠️  No thread to test with\n";
    }
} catch (\Exception $e) {
    echo "   Mention system: ❌ Error - " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Test API endpoints
echo "4. API ENDPOINTS TEST:\n";
$baseUrl = 'https://staging.mrvl.net/api';

// Test teams endpoint
$teamsResponse = @file_get_contents($baseUrl . '/teams?limit=1');
if ($teamsResponse) {
    $data = json_decode($teamsResponse, true);
    $hasData = isset($data['data']) && count($data['data']) > 0;
    $noLiquipedia = !strpos($teamsResponse, 'liquipedia');
    echo "   Teams API: " . ($hasData ? "✅" : "❌") . "\n";
    echo "   No Liquipedia in response: " . ($noLiquipedia ? "✅" : "❌") . "\n";
}

// Test players endpoint
$playersResponse = @file_get_contents($baseUrl . '/players?limit=1');
if ($playersResponse) {
    $data = json_decode($playersResponse, true);
    $hasData = isset($data['data']) && count($data['data']) > 0;
    $noLiquipedia = !strpos($playersResponse, 'liquipedia');
    echo "   Players API: " . ($hasData ? "✅" : "❌") . "\n";
    echo "   No Liquipedia in response: " . ($noLiquipedia ? "✅" : "❌") . "\n";
}
echo "\n";

// 5. Test database fields
echo "5. DATABASE FIELDS TEST:\n";
$team = Team::first();
if ($team) {
    $teamFields = array_keys($team->getAttributes());
    $requiredTeamFields = ['name', 'short_name', 'logo', 'region', 'platform', 'coach'];
    $hasAllFields = count(array_intersect($requiredTeamFields, $teamFields)) == count($requiredTeamFields);
    echo "   Team has all required fields: " . ($hasAllFields ? "✅" : "❌") . "\n";
}

$player = Player::first();
if ($player) {
    $playerFields = array_keys($player->getAttributes());
    $requiredPlayerFields = ['username', 'real_name', 'role', 'avatar', 'nationality', 'team_id'];
    $hasAllFields = count(array_intersect($requiredPlayerFields, $playerFields)) == count($requiredPlayerFields);
    echo "   Player has all required fields: " . ($hasAllFields ? "✅" : "❌") . "\n";
}

echo "\n=== TEST COMPLETE ===\n";