<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\OptimizedUserProfileService;

echo "=== TESTING USER STATS CALCULATION ===\n\n";

// Get user ID 1
$user = User::find(1);

if (!$user) {
    echo "User ID 1 not found!\n";
    exit;
}

echo "Testing stats for: {$user->name} (ID: {$user->id})\n";
echo "User created at: {$user->created_at}\n\n";

// Clear cache
Cache::forget("user_stats_optimized_{$user->id}");

// Get stats using the service
$service = new OptimizedUserProfileService();

try {
    $stats = $service->getUserStatisticsOptimized($user->id);
    
    echo "=== STATS RETRIEVED ===\n";
    echo json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";
    
    echo "=== KEY METRICS ===\n";
    echo "Forum Threads: " . ($stats['forum_threads'] ?? 0) . "\n";
    echo "Forum Posts: " . ($stats['forum_posts'] ?? 0) . "\n";
    echo "News Comments: " . ($stats['news_comments'] ?? 0) . "\n";
    echo "Match Comments: " . ($stats['match_comments'] ?? 0) . "\n";
    echo "Total Comments: " . ($stats['total_comments'] ?? 0) . "\n";
    echo "Days Active: " . ($stats['days_active'] ?? 0) . "\n";
    echo "Activity Score: " . ($stats['activity_score'] ?? 0) . "\n";
    echo "Reputation Score: " . ($stats['reputation_score'] ?? 0) . "\n";
    echo "Total Actions: " . ($stats['total_actions'] ?? 0) . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

// Check raw counts
echo "\n=== RAW DATABASE COUNTS ===\n";
echo "Forum Threads: " . DB::table('forum_threads')->where('user_id', 1)->count() . "\n";
echo "Forum Posts: " . DB::table('forum_posts')->where('user_id', 1)->count() . "\n";
echo "News Comments: " . DB::table('news_comments')->where('user_id', 1)->count() . "\n";
echo "Match Comments: " . DB::table('match_comments')->where('user_id', 1)->count() . "\n";
echo "Mentions Received: " . DB::table('mentions')->where('mentioned_user_id', 1)->count() . "\n";