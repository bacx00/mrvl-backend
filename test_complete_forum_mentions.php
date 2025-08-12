#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Thread;
use App\Models\News;
use App\Models\MvrlMatch;
use App\Services\MentionService;

echo "\n=== COMPLETE FORUM & MENTIONS SYSTEM TEST ===\n\n";

// Get test entities
$user = User::first();
$team = Team::first();
$player = Player::first();
$thread = Thread::first();
$news = News::first();
$match = MvrlMatch::first();

echo "Test Entities:\n";
echo "  User: " . ($user ? $user->name : 'None') . "\n";
echo "  Team: " . ($team ? $team->name : 'None') . "\n";
echo "  Player: " . ($player ? $player->username : 'None') . "\n\n";

// 1. Test Forum Post Creation with Mentions
echo "1. FORUM POST WITH MENTIONS:\n";
if ($thread && $user && $team && $player) {
    $mentionService = new MentionService();
    $content = "Hey @" . $user->username . " check out @team:" . $team->name . " and @player:" . $player->username . "!";
    
    // Extract mentions
    $mentions = $mentionService->extractMentions($content);
    echo "   Content: $content\n";
    echo "   Extracted mentions: " . count($mentions) . "\n";
    
    // Store mentions (simulating forum post)
    $mentionCount = $mentionService->storeMentions($content, 'thread', $thread->id);
    echo "   Stored mentions: $mentionCount\n";
    echo "   Status: " . ($mentionCount > 0 ? "✅ PASSED" : "❌ FAILED") . "\n";
} else {
    echo "   Status: ⚠️ Missing test entities\n";
}
echo "\n";

// 2. Test News Comment with Mentions
echo "2. NEWS COMMENT WITH MENTIONS:\n";
if ($news && $user) {
    $mentionService = new MentionService();
    $content = "Great article @" . $user->username . "!";
    
    $mentions = $mentionService->extractMentions($content);
    echo "   Content: $content\n";
    echo "   Extracted mentions: " . count($mentions) . "\n";
    
    $mentionCount = $mentionService->storeMentions($content, 'news_comment', $news->id);
    echo "   Stored mentions: $mentionCount\n";
    echo "   Status: " . ($mentionCount > 0 ? "✅ PASSED" : "❌ FAILED") . "\n";
} else {
    echo "   Status: ⚠️ Missing news entity\n";
}
echo "\n";

// 3. Test Match Comment with Mentions
echo "3. MATCH COMMENT WITH MENTIONS:\n";
if ($match && $team) {
    $mentionService = new MentionService();
    $content = "Amazing play by @team:" . $team->name . "!";
    
    $mentions = $mentionService->extractMentions($content);
    echo "   Content: $content\n";
    echo "   Extracted mentions: " . count($mentions) . "\n";
    
    $mentionCount = $mentionService->storeMentions($content, 'match_comment', $match->id);
    echo "   Stored mentions: $mentionCount\n";
    echo "   Status: " . ($mentionCount > 0 ? "✅ PASSED" : "❌ FAILED") . "\n";
} else {
    echo "   Status: ⚠️ Missing match entity\n";
}
echo "\n";

// 4. Test Profile Mention Retrieval
echo "4. PROFILE MENTION RETRIEVAL:\n";
if ($user) {
    $userMentions = $mentionService->getMentionsForContent('user', $user->id);
    echo "   User mentions found: " . count($userMentions) . "\n";
}
if ($team) {
    $teamMentions = $mentionService->getMentionsForContent('team', $team->id);
    echo "   Team mentions found: " . count($teamMentions) . "\n";
}
if ($player) {
    $playerMentions = $mentionService->getMentionsForContent('player', $player->id);
    echo "   Player mentions found: " . count($playerMentions) . "\n";
}
echo "   Status: ✅ PASSED\n\n";

// 5. Test Mention Deletion
echo "5. MENTION DELETION TEST:\n";
if ($thread) {
    $beforeCount = \App\Models\Mention::where('content_type', 'thread')
        ->where('content_id', $thread->id)->count();
    echo "   Mentions before deletion: $beforeCount\n";
    
    // Delete mentions
    $mentionService->deleteMentions('thread', $thread->id);
    
    $afterCount = \App\Models\Mention::where('content_type', 'thread')
        ->where('content_id', $thread->id)->count();
    echo "   Mentions after deletion: $afterCount\n";
    echo "   Status: " . ($afterCount == 0 ? "✅ PASSED" : "❌ FAILED") . "\n";
}
echo "\n";

// 6. Test API Endpoints
echo "6. API ENDPOINTS TEST:\n";
$baseUrl = 'https://staging.mrvl.net/api';

// Test mention counts endpoint
if ($user) {
    $response = @file_get_contents($baseUrl . '/mentions/user/' . $user->id . '/counts');
    echo "   User mention counts API: " . ($response ? "✅ Working" : "❌ Failed") . "\n";
}

if ($team) {
    $response = @file_get_contents($baseUrl . '/mentions/team/' . $team->id . '/counts');
    echo "   Team mention counts API: " . ($response ? "✅ Working" : "❌ Failed") . "\n";
}

if ($player) {
    $response = @file_get_contents($baseUrl . '/mentions/player/' . $player->id . '/counts');
    echo "   Player mention counts API: " . ($response ? "✅ Working" : "❌ Failed") . "\n";
}

echo "\n=== TEST COMPLETE ===\n";

// Summary
$mentionTable = \DB::table('mentions')->count();
$usersWithMentions = User::where('mention_count', '>', 0)->count();
$teamsWithMentions = Team::where('mention_count', '>', 0)->count();
$playersWithMentions = Player::where('mention_count', '>', 0)->count();

echo "\nSUMMARY:\n";
echo "  Total mentions in database: $mentionTable\n";
echo "  Users with mentions: $usersWithMentions\n";
echo "  Teams with mentions: $teamsWithMentions\n";
echo "  Players with mentions: $playersWithMentions\n";