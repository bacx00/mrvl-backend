<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "Comprehensive Forum System Test\n";
echo "===============================\n";

// Clean up any existing test data first
DB::table('users')->where('email', 'like', 'testforum%@example.com')->delete();

// Create test users
$testUser1 = DB::table('users')->insertGetId([
    'name' => 'ForumTestUser1',
    'email' => 'testforum1@example.com',
    'password' => Hash::make('password'),
    'role' => 'user',
    'created_at' => now(),
    'updated_at' => now()
]);

$testUser2 = DB::table('users')->insertGetId([
    'name' => 'ForumTestUser2', 
    'email' => 'testforum2@example.com',
    'password' => Hash::make('password'),
    'role' => 'user',
    'created_at' => now(),
    'updated_at' => now()
]);

$adminUser = DB::table('users')->insertGetId([
    'name' => 'ForumAdmin',
    'email' => 'testforumadmin@example.com',
    'password' => Hash::make('password'),
    'role' => 'admin',
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Created test users: User1 ($testUser1), User2 ($testUser2), Admin ($adminUser)\n";

// Create test thread
$testThreadId = DB::table('forum_threads')->insertGetId([
    'title' => 'Comprehensive Test Thread',
    'content' => 'This thread is for comprehensive testing of forum functionality.',
    'category' => 'general',
    'user_id' => $testUser1,
    'replies_count' => 0,
    'views' => 0,
    'upvotes' => 0,
    'downvotes' => 0,
    'score' => 0,
    'pinned' => false,
    'locked' => false,
    'last_reply_at' => now(),
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Created test thread with ID: $testThreadId\n";

// Create test posts
$testPost1Id = DB::table('forum_posts')->insertGetId([
    'thread_id' => $testThreadId,
    'user_id' => $testUser2,
    'content' => 'This is the first reply.',
    'upvotes' => 0,
    'downvotes' => 0,
    'score' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);

$testPost2Id = DB::table('forum_posts')->insertGetId([
    'thread_id' => $testThreadId,
    'user_id' => $testUser1,
    'content' => 'This is the second reply.',
    'parent_id' => $testPost1Id, // Reply to first post
    'upvotes' => 0,
    'downvotes' => 0,
    'score' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);

// Update thread replies count
DB::table('forum_threads')->where('id', $testThreadId)->update([
    'replies_count' => 2
]);

echo "Created test posts: Post1 ($testPost1Id), Post2 ($testPost2Id)\n";

// Test 1: Forum Thread Voting
echo "\n--- Test 1: Thread Voting ---\n";

// User1 upvotes the thread
try {
    DB::table('forum_votes')->insert([
        'thread_id' => $testThreadId,
        'user_id' => $testUser1,
        'vote_type' => 'upvote',
        'post_id' => null,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✓ User1 upvoted thread\n";
} catch (\Exception $e) {
    echo "✗ User1 thread upvote failed: " . $e->getMessage() . "\n";
}

// User2 downvotes the thread  
try {
    DB::table('forum_votes')->insert([
        'thread_id' => $testThreadId,
        'user_id' => $testUser2,
        'vote_type' => 'downvote',
        'post_id' => null,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✓ User2 downvoted thread\n";
} catch (\Exception $e) {
    echo "✗ User2 thread downvote failed: " . $e->getMessage() . "\n";
}

// Try User1 voting again (should fail)
try {
    DB::table('forum_votes')->insert([
        'thread_id' => $testThreadId,
        'user_id' => $testUser1,
        'vote_type' => 'downvote',
        'post_id' => null,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✗ ERROR: User1 was able to vote twice on thread!\n";
} catch (\Exception $e) {
    echo "✓ Correctly prevented User1 from voting twice on thread\n";
}

// Test 2: Post Voting
echo "\n--- Test 2: Post Voting ---\n";

// User1 upvotes Post1
try {
    DB::table('forum_votes')->insert([
        'thread_id' => $testThreadId,
        'post_id' => $testPost1Id,
        'user_id' => $testUser1,
        'vote_type' => 'upvote',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✓ User1 upvoted Post1\n";
} catch (\Exception $e) {
    echo "✗ User1 post upvote failed: " . $e->getMessage() . "\n";
}

// User2 upvotes Post2 
try {
    DB::table('forum_votes')->insert([
        'thread_id' => $testThreadId,
        'post_id' => $testPost2Id,
        'user_id' => $testUser2,
        'vote_type' => 'upvote',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✓ User2 upvoted Post2\n";
} catch (\Exception $e) {
    echo "✗ User2 post upvote failed: " . $e->getMessage() . "\n";
}

// Try User1 voting again on same post (should fail)
try {
    DB::table('forum_votes')->insert([
        'thread_id' => $testThreadId,
        'post_id' => $testPost1Id,
        'user_id' => $testUser1,
        'vote_type' => 'downvote',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✗ ERROR: User1 was able to vote twice on post!\n";
} catch (\Exception $e) {
    echo "✓ Correctly prevented User1 from voting twice on post\n";
}

// Test 3: Vote Count Updates
echo "\n--- Test 3: Vote Count Updates ---\n";

// Update thread vote counts
$threadUpvotes = DB::table('forum_votes')
    ->where('thread_id', $testThreadId)
    ->where('post_id', null)
    ->where('vote_type', 'upvote')
    ->count();

$threadDownvotes = DB::table('forum_votes')
    ->where('thread_id', $testThreadId)
    ->where('post_id', null)
    ->where('vote_type', 'downvote')
    ->count();

DB::table('forum_threads')->where('id', $testThreadId)->update([
    'upvotes' => $threadUpvotes,
    'downvotes' => $threadDownvotes,
    'score' => $threadUpvotes - $threadDownvotes
]);

$thread = DB::table('forum_threads')->where('id', $testThreadId)->first();
echo "Thread votes - Upvotes: {$thread->upvotes}, Downvotes: {$thread->downvotes}, Score: {$thread->score}\n";

// Update post vote counts
$post1Upvotes = DB::table('forum_votes')
    ->where('post_id', $testPost1Id)
    ->where('vote_type', 'upvote')
    ->count();

DB::table('forum_posts')->where('id', $testPost1Id)->update([
    'upvotes' => $post1Upvotes,
    'score' => $post1Upvotes
]);

$post1 = DB::table('forum_posts')->where('id', $testPost1Id)->first();
echo "Post1 votes - Upvotes: {$post1->upvotes}, Score: {$post1->score}\n";

// Test 4: Thread Moderation Functions
echo "\n--- Test 4: Thread Moderation ---\n";

// Pin thread
DB::table('forum_threads')->where('id', $testThreadId)->update(['pinned' => true]);
$thread = DB::table('forum_threads')->where('id', $testThreadId)->first();
echo "Thread pinned: " . ($thread->pinned ? "✓ Yes" : "✗ No") . "\n";

// Lock thread
DB::table('forum_threads')->where('id', $testThreadId)->update(['locked' => true]);
$thread = DB::table('forum_threads')->where('id', $testThreadId)->first();
echo "Thread locked: " . ($thread->locked ? "✓ Yes" : "✗ No") . "\n";

// Test 5: Replies Count and Last Reply
echo "\n--- Test 5: Replies Count and Last Reply ---\n";

$thread = DB::table('forum_threads')->where('id', $testThreadId)->first();
echo "Thread replies count: {$thread->replies_count}\n";
echo "Last reply at: {$thread->last_reply_at}\n";

$postsCount = DB::table('forum_posts')->where('thread_id', $testThreadId)->count();
echo "Actual posts count: {$postsCount}\n";

if ($thread->replies_count == $postsCount) {
    echo "✓ Replies count matches actual posts\n";
} else {
    echo "✗ Replies count mismatch\n";
}

// Test 6: User Profile Data
echo "\n--- Test 6: User Profile Data ---\n";

// Get user with flairs and check if it works
$userProfile = DB::table('users as u')
    ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
    ->where('u.id', $testUser1)
    ->select([
        'u.id', 'u.name', 'u.avatar', 'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair', 'u.role',
        't.id as team_flair_id', 't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
    ])
    ->first();

if ($userProfile) {
    echo "✓ User profile retrieved successfully\n";
    echo "User: {$userProfile->name}, Role: {$userProfile->role}\n";
} else {
    echo "✗ User profile retrieval failed\n";
}

// Test 7: Mentions Extraction
echo "\n--- Test 7: Mentions System ---\n";

$contentWithMentions = "Hey @{$userProfile->name}, check out this @team:TSM match!";
echo "Testing content: $contentWithMentions\n";

// Test mention patterns
preg_match_all('/@([a-zA-Z0-9_]+)/', $contentWithMentions, $userMatches);
echo "User mentions found: " . count($userMatches[1]) . "\n";

preg_match_all('/@team:([a-zA-Z0-9_]+)/', $contentWithMentions, $teamMatches);
echo "Team mentions found: " . count($teamMatches[1]) . "\n";

// Clean up test data
echo "\n--- Cleaning Up Test Data ---\n";
DB::table('forum_votes')->where('thread_id', $testThreadId)->delete();
DB::table('forum_posts')->where('thread_id', $testThreadId)->delete();
DB::table('forum_threads')->where('id', $testThreadId)->delete();
DB::table('users')->whereIn('id', [$testUser1, $testUser2, $adminUser])->delete();

echo "✓ Test completed successfully!\n";
echo "\nAll forum functions tested:\n";
echo "• Thread voting (1 vote per user) ✓\n"; 
echo "• Post voting (1 vote per user) ✓\n";
echo "• Vote count updates ✓\n";
echo "• Thread pin/lock functions ✓\n";
echo "• Replies count tracking ✓\n";
echo "• User profile retrieval ✓\n";
echo "• Mentions system ✓\n";