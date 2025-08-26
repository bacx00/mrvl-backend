<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\OptimizedUserProfileService;

echo "=== CHECKING USER DATA ===\n";

// Get first 5 users
$users = User::orderBy('id')->limit(5)->get();

echo "Found " . User::count() . " total users\n\n";

if ($users->isEmpty()) {
    echo "No users found in the database!\n";
    
    // Create a test user
    echo "Creating test user...\n";
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'email_verified_at' => now()
    ]);
    echo "Created user with ID: " . $user->id . "\n";
    $users = collect([$user]);
}

// Create some test data for the first user
$firstUser = $users->first();
echo "\nAdding test data for user ID {$firstUser->id}...\n";

// Add forum threads
DB::table('forum_threads')->insertOrIgnore([
    'user_id' => $firstUser->id,
    'category_id' => 1,
    'title' => 'Test Thread ' . time(),
    'content' => 'This is a test thread',
    'is_pinned' => false,
    'is_locked' => false,
    'created_at' => now(),
    'updated_at' => now()
]);

// Add forum posts
$threadId = DB::table('forum_threads')->where('user_id', $firstUser->id)->first()->id ?? null;
if ($threadId) {
    DB::table('forum_posts')->insertOrIgnore([
        'thread_id' => $threadId,
        'user_id' => $firstUser->id,
        'content' => 'This is a test post',
        'created_at' => now(),
        'updated_at' => now()
    ]);
}

// Add news comments
$newsId = DB::table('news')->first()->id ?? null;
if ($newsId) {
    DB::table('news_comments')->insertOrIgnore([
        'news_id' => $newsId,
        'user_id' => $firstUser->id,
        'content' => 'This is a test comment',
        'created_at' => now(),
        'updated_at' => now()
    ]);
}

echo "\nTesting stats for each user:\n";
echo str_repeat('-', 80) . "\n";

$service = new OptimizedUserProfileService();

foreach ($users as $user) {
    echo "\nUser: {$user->name} (ID: {$user->id})\n";
    
    try {
        // Clear cache first
        Cache::forget("user_stats_optimized_{$user->id}");
        
        $stats = $service->getUserStatisticsOptimized($user->id);
        
        echo "  Forum Threads: " . ($stats['forum_threads'] ?? 0) . "\n";
        echo "  Forum Posts: " . ($stats['forum_posts'] ?? 0) . "\n";
        echo "  News Comments: " . ($stats['news_comments'] ?? 0) . "\n";
        echo "  Match Comments: " . ($stats['match_comments'] ?? 0) . "\n";
        echo "  Total Comments: " . ($stats['total_comments'] ?? 0) . "\n";
        echo "  Days Active: " . ($stats['days_active'] ?? 0) . "\n";
        echo "  Activity Score: " . ($stats['activity_score'] ?? 0) . "\n";
        echo "  Total Actions: " . ($stats['total_actions'] ?? 0) . "\n";
        echo "  Last Activity: " . ($stats['last_activity'] ?? 'Never') . "\n";
        
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n";