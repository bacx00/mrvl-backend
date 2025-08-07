<?php
/**
 * Apply Database Optimization Fixes
 * Executes the critical constraint fixes identified in the analysis
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== APPLYING DATABASE OPTIMIZATION FIXES ===\n\n";

$fixes = [
    'Drop problematic forum votes constraint' => "DROP INDEX IF EXISTS forum_votes_user_thread_unique",
    'Drop problematic news votes constraint' => "DROP INDEX IF EXISTS news_votes_user_id_news_id_unique",
    'Create forum votes NULL-safe constraint' => "CREATE UNIQUE INDEX idx_forum_votes_user_thread_post ON forum_votes (user_id, thread_id, COALESCE(post_id, 0))",
    'Create news votes NULL-safe constraint' => "CREATE UNIQUE INDEX idx_news_votes_user_news_comment ON news_votes (user_id, news_id, COALESCE(comment_id, 0))",
    'Add forum threads performance index' => "CREATE INDEX IF NOT EXISTS idx_forum_threads_status_pinned_last_reply ON forum_threads (status, pinned, last_reply_at DESC)",
    'Add forum posts performance index' => "CREATE INDEX IF NOT EXISTS idx_forum_posts_thread_status_created ON forum_posts (thread_id, status, created_at)",
    'Add news performance index' => "CREATE INDEX IF NOT EXISTS idx_news_status_featured_published ON news (status, featured, published_at DESC)",
    'Add news comments performance index' => "CREATE INDEX IF NOT EXISTS idx_news_comments_news_status_created ON news_comments (news_id, status, created_at)"
];

$successCount = 0;
$errorCount = 0;

foreach ($fixes as $description => $sql) {
    try {
        DB::statement($sql);
        echo "✓ {$description}\n";
        $successCount++;
    } catch (Exception $e) {
        echo "✗ {$description}: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n=== VERIFICATION ===\n";

// Test that voting now works properly
try {
    // Test forum voting (should not throw constraint error)
    DB::beginTransaction();
    
    // Simulate user changing vote type
    $testUserId = 1;
    $testThreadId = 1;
    
    // First vote
    DB::table('forum_votes')->insert([
        'user_id' => $testUserId,
        'thread_id' => $testThreadId,
        'vote_type' => 'upvote',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Change vote (should work now)
    DB::table('forum_votes')
        ->where('user_id', $testUserId)
        ->where('thread_id', $testThreadId)
        ->update(['vote_type' => 'downvote']);
    
    DB::rollback(); // Don't save test data
    echo "✓ Forum voting constraint fix verified\n";
    
} catch (Exception $e) {
    DB::rollback();
    echo "✗ Forum voting still has issues: " . $e->getMessage() . "\n";
}

try {
    // Test news voting (should not throw constraint error)  
    DB::beginTransaction();
    
    $testUserId = 1;
    $testNewsId = 1;
    
    // First vote
    DB::table('news_votes')->insert([
        'user_id' => $testUserId,
        'news_id' => $testNewsId,
        'vote_type' => 'upvote',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Change vote (should work now)
    DB::table('news_votes')
        ->where('user_id', $testUserId)
        ->where('news_id', $testNewsId)
        ->update(['vote_type' => 'downvote']);
    
    DB::rollback(); // Don't save test data
    echo "✓ News voting constraint fix verified\n";
    
} catch (Exception $e) {
    DB::rollback();
    echo "✗ News voting still has issues: " . $e->getMessage() . "\n";
}

echo "\n=== SUMMARY ===\n";
echo "Fixes Applied: {$successCount}\n";
echo "Errors: {$errorCount}\n";

if ($errorCount === 0) {
    echo "\n🎉 ALL DATABASE FIXES APPLIED SUCCESSFULLY!\n";
    echo "Your forum and news voting systems should now work correctly.\n";
} else {
    echo "\n⚠️  Some fixes failed - please review the errors above.\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Test voting functionality in your application\n";
echo "2. Monitor query performance with the new indexes\n";
echo "3. Consider implementing the medium/low priority optimizations\n";
echo "4. Set up database monitoring for production use\n";

echo "\nFor the complete analysis, see: DATABASE_OPTIMIZATION_REPORT.md\n";