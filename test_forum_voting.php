<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "Testing Forum Voting System\n";
echo "==========================\n";

// Clean up any existing test data first
DB::table('users')->where('email', 'like', 'testvote%@example.com')->delete();

// Create test user
$testUserId = DB::table('users')->insertGetId([
    'name' => 'TestVoteUser',
    'email' => 'testvote@example.com',
    'password' => Hash::make('password'),
    'role' => 'user',
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Created test user with ID: $testUserId\n";

// Create test thread
$testThreadId = DB::table('forum_threads')->insertGetId([
    'title' => 'Test Voting Thread',
    'content' => 'This thread is for testing voting functionality.',
    'category' => 'general',
    'user_id' => $testUserId,
    'replies_count' => 0,
    'views' => 0,
    'upvotes' => 0,
    'downvotes' => 0,
    'score' => 0,
    'last_reply_at' => now(),
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Created test thread with ID: $testThreadId\n";

// Create test post
$testPostId = DB::table('forum_posts')->insertGetId([
    'thread_id' => $testThreadId,
    'user_id' => $testUserId,
    'content' => 'This is a test post for voting.',
    'upvotes' => 0,
    'downvotes' => 0,
    'score' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Created test post with ID: $testPostId\n";

// Test thread voting
echo "\n--- Testing Thread Voting ---\n";

// Simulate thread upvote
DB::table('forum_votes')->insert([
    'thread_id' => $testThreadId,
    'user_id' => $testUserId,
    'vote_type' => 'upvote',
    'post_id' => null,
    'vote_key' => "thread_{$testThreadId}_{$testUserId}",
    'created_at' => now(),
    'updated_at' => now()
]);

// Update thread vote counts
$upvotes = DB::table('forum_votes')
    ->where('thread_id', $testThreadId)
    ->where('post_id', null)
    ->where('vote_type', 'upvote')
    ->count();

$downvotes = DB::table('forum_votes')
    ->where('thread_id', $testThreadId)
    ->where('post_id', null)
    ->where('vote_type', 'downvote')
    ->count();

DB::table('forum_threads')->where('id', $testThreadId)->update([
    'upvotes' => $upvotes,
    'downvotes' => $downvotes,
    'score' => $upvotes - $downvotes
]);

$thread = DB::table('forum_threads')->where('id', $testThreadId)->first();
echo "Thread votes - Upvotes: {$thread->upvotes}, Downvotes: {$thread->downvotes}, Score: {$thread->score}\n";

// Test post voting
echo "\n--- Testing Post Voting ---\n";

// Create second user for post voting first
$testUser2Id = DB::table('users')->insertGetId([
    'name' => 'TestVoteUser2',
    'email' => 'testvote2@example.com',
    'password' => Hash::make('password'),
    'role' => 'user',
    'created_at' => now(),
    'updated_at' => now()
]);

// Simulate post upvote with correct user ID
DB::table('forum_votes')->insert([
    'thread_id' => $testThreadId,
    'post_id' => $testPostId,
    'user_id' => $testUser2Id,
    'vote_type' => 'upvote',
    'vote_key' => "post_{$testPostId}_{$testUser2Id}",
    'created_at' => now(),
    'updated_at' => now()
]);

// Update post vote counts
$postUpvotes = DB::table('forum_votes')
    ->where('post_id', $testPostId)
    ->where('vote_type', 'upvote')
    ->count();

$postDownvotes = DB::table('forum_votes')
    ->where('post_id', $testPostId)
    ->where('vote_type', 'downvote')
    ->count();

DB::table('forum_posts')->where('id', $testPostId)->update([
    'upvotes' => $postUpvotes,
    'downvotes' => $postDownvotes,
    'score' => $postUpvotes - $postDownvotes
]);

$post = DB::table('forum_posts')->where('id', $testPostId)->first();
echo "Post votes - Upvotes: {$post->upvotes}, Downvotes: {$post->downvotes}, Score: {$post->score}\n";

// Test vote constraints - try to vote twice with same user
echo "\n--- Testing Vote Constraints ---\n";

try {
    DB::table('forum_votes')->insert([
        'thread_id' => $testThreadId,
        'user_id' => $testUserId,
        'vote_type' => 'downvote',
        'post_id' => null,
        'vote_key' => "thread_{$testThreadId}_{$testUserId}",
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "ERROR: Should not be able to vote twice on the same thread!\n";
} catch (\Exception $e) {
    echo "GOOD: Prevented duplicate thread vote: " . $e->getMessage() . "\n";
}

try {
    DB::table('forum_votes')->insert([
        'thread_id' => $testThreadId,
        'post_id' => $testPostId,
        'user_id' => $testUser2Id,
        'vote_type' => 'downvote',
        'vote_key' => "post_{$testPostId}_{$testUser2Id}",
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "ERROR: Should not be able to vote twice on the same post!\n";
} catch (\Exception $e) {
    echo "GOOD: Prevented duplicate post vote: " . $e->getMessage() . "\n";
}

// Clean up test data
echo "\n--- Cleaning Up Test Data ---\n";
DB::table('forum_votes')->where('thread_id', $testThreadId)->delete();
DB::table('forum_posts')->where('id', $testPostId)->delete();
DB::table('forum_threads')->where('id', $testThreadId)->delete();
DB::table('users')->where('id', $testUserId)->delete();
DB::table('users')->where('id', $testUser2Id)->delete();

echo "Test completed successfully!\n";