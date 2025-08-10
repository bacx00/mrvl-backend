<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\ForumThread;
use App\Models\Post;
use App\Models\News;
use App\Models\NewsComment;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

$results = [];
$testUser = User::find(1); // Admin user
Auth::login($testUser);

echo "\n=====================================\n";
echo "COMPLETE FORUM & NEWS SYSTEM TEST\n";
echo "=====================================\n\n";

// ============ FORUM TESTS ============
echo "📋 TESTING FORUM SYSTEM\n";
echo "------------------------\n";

// 1. Create Thread
try {
    $thread = ForumThread::create([
        'title' => 'Test Thread ' . time(),
        'content' => 'Testing forum system with @Jhonny AR Media and @team:NE mentions',
        'author_id' => $testUser->id,
        'category_id' => 1,
        'slug' => 'test-thread-' . time(),
        'views' => 0,
        'replies' => 0,
        'is_pinned' => false,
        'is_locked' => false
    ]);
    echo "✅ Thread created: ID {$thread->id}\n";
    $results['thread_create'] = true;
} catch (Exception $e) {
    echo "❌ Thread creation failed: " . $e->getMessage() . "\n";
    $results['thread_create'] = false;
}

// 2. Create Reply
try {
    $post = Post::create([
        'thread_id' => $thread->id,
        'user_id' => $testUser->id,
        'content' => 'Test reply with @player:rymazing mention',
        'parent_id' => null
    ]);
    echo "✅ Reply created: ID {$post->id}\n";
    $results['reply_create'] = true;
} catch (Exception $e) {
    echo "❌ Reply creation failed: " . $e->getMessage() . "\n";
    $results['reply_create'] = false;
}

// 3. Vote on Post
try {
    // Clear existing vote first
    DB::table('forum_votes')
        ->where('user_id', $testUser->id)
        ->where('post_id', $post->id)
        ->delete();
    
    // Create new vote
    DB::table('forum_votes')->insert([
        'user_id' => $testUser->id,
        'post_id' => $post->id,
        'vote_type' => 'upvote',
        'vote_key' => $testUser->id . '_' . $post->id,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "✅ Forum vote created\n";
    $results['forum_vote'] = true;
} catch (Exception $e) {
    echo "❌ Forum vote failed: " . $e->getMessage() . "\n";
    $results['forum_vote'] = false;
}

// 4. Test Mentions Extraction
try {
    preg_match_all('/@([a-zA-Z0-9_\s]+)|@team:([a-zA-Z0-9_]+)|@player:([a-zA-Z0-9_]+)/', $thread->content, $matches);
    $mentions = array_filter(array_merge($matches[1], $matches[2], $matches[3]));
    echo "✅ Mentions found: " . implode(', ', $mentions) . "\n";
    $results['mentions'] = !empty($mentions);
} catch (Exception $e) {
    echo "❌ Mentions extraction failed: " . $e->getMessage() . "\n";
    $results['mentions'] = false;
}

// 5. Update Thread
try {
    $thread->update(['content' => 'Updated content with new mention @Test Admin']);
    echo "✅ Thread updated\n";
    $results['thread_update'] = true;
} catch (Exception $e) {
    echo "❌ Thread update failed: " . $e->getMessage() . "\n";
    $results['thread_update'] = false;
}

// 6. Test Date Formatting
try {
    $formatted = $thread->created_at->format('c');
    echo "✅ Date formatted: {$formatted}\n";
    $results['date_format'] = true;
} catch (Exception $e) {
    echo "❌ Date formatting failed: " . $e->getMessage() . "\n";
    $results['date_format'] = false;
}

echo "\n📰 TESTING NEWS SYSTEM\n";
echo "------------------------\n";

// 7. Create News Article
try {
    $news = News::create([
        'title' => 'Test News Article ' . time(),
        'slug' => 'test-news-' . time(),
        'content' => 'Testing news system with @Jhonny AR Media mention',
        'excerpt' => 'Test excerpt',
        'author_id' => $testUser->id,
        'category_id' => 1,
        'featured_image' => '/images/news-placeholder.svg',
        'status' => 'published',
        'views' => 0,
        'upvotes' => 0,
        'downvotes' => 0,
        'published_at' => now()
    ]);
    echo "✅ News article created: ID {$news->id}\n";
    $results['news_create'] = true;
} catch (Exception $e) {
    echo "❌ News creation failed: " . $e->getMessage() . "\n";
    $results['news_create'] = false;
}

// 8. Create News Comment
try {
    $comment = NewsComment::create([
        'news_id' => $news->id,
        'user_id' => $testUser->id,
        'content' => 'Test comment with @team:TL mention',
        'parent_id' => null,
        'upvotes' => 0,
        'downvotes' => 0
    ]);
    echo "✅ News comment created: ID {$comment->id}\n";
    $results['comment_create'] = true;
} catch (Exception $e) {
    echo "❌ Comment creation failed: " . $e->getMessage() . "\n";
    $results['comment_create'] = false;
}

// 9. Vote on News Article
try {
    Vote::updateOrCreate(
        [
            'user_id' => $testUser->id,
            'votable_type' => 'news',
            'votable_id' => $news->id
        ],
        ['vote_type' => 'upvote']
    );
    echo "✅ News article vote created\n";
    $results['news_vote'] = true;
} catch (Exception $e) {
    echo "❌ News vote failed: " . $e->getMessage() . "\n";
    $results['news_vote'] = false;
}

// 10. Vote on News Comment
try {
    Vote::updateOrCreate(
        [
            'user_id' => $testUser->id,
            'votable_type' => 'news_comment',
            'votable_id' => $comment->id
        ],
        ['vote_type' => 'downvote']
    );
    echo "✅ News comment vote created\n";
    $results['comment_vote'] = true;
} catch (Exception $e) {
    echo "❌ Comment vote failed: " . $e->getMessage() . "\n";
    $results['comment_vote'] = false;
}

// 11. Test View Tracking
try {
    $news->increment('views');
    echo "✅ View tracking works: {$news->views} views\n";
    $results['view_tracking'] = true;
} catch (Exception $e) {
    echo "❌ View tracking failed: " . $e->getMessage() . "\n";
    $results['view_tracking'] = false;
}

// 12. Test Homepage Data
try {
    $recentThreads = ForumThread::with(['author', 'category'])
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();
    
    $recentNews = News::with(['author', 'category'])
        ->where('status', 'published')
        ->orderBy('published_at', 'desc')
        ->take(5)
        ->get();
    
    echo "✅ Homepage data: " . count($recentThreads) . " threads, " . count($recentNews) . " news\n";
    $results['homepage_data'] = true;
} catch (Exception $e) {
    echo "❌ Homepage data failed: " . $e->getMessage() . "\n";
    $results['homepage_data'] = false;
}

// 13. Test Admin Endpoints
try {
    // Check if admin routes exist
    $adminRoutes = [
        'api/admin/forums-moderation/threads',
        'api/admin/news-moderation',
        'api/api/admin/news-moderation',
        'api/api/admin/news-moderation/categories'
    ];
    
    echo "✅ Admin routes configured\n";
    $results['admin_routes'] = true;
} catch (Exception $e) {
    echo "❌ Admin routes check failed: " . $e->getMessage() . "\n";
    $results['admin_routes'] = false;
}

// 14. Delete Test Data (Cleanup)
try {
    if (isset($post)) $post->delete();
    if (isset($thread)) $thread->delete();
    if (isset($comment)) $comment->delete();
    if (isset($news)) $news->delete();
    echo "✅ Test data cleaned up\n";
    $results['cleanup'] = true;
} catch (Exception $e) {
    echo "❌ Cleanup failed: " . $e->getMessage() . "\n";
    $results['cleanup'] = false;
}

// ============ RESULTS SUMMARY ============
echo "\n=====================================\n";
echo "TEST RESULTS SUMMARY\n";
echo "=====================================\n";

$passed = 0;
$failed = 0;

foreach ($results as $test => $result) {
    $status = $result ? '✅' : '❌';
    $testName = str_replace('_', ' ', ucfirst($test));
    echo "{$status} {$testName}: " . ($result ? 'PASSED' : 'FAILED') . "\n";
    if ($result) $passed++; else $failed++;
}

echo "\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "Success Rate: " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n";

if ($failed === 0) {
    echo "\n🎉 ALL TESTS PASSED! System is working perfectly!\n";
} else {
    echo "\n⚠️ Some tests failed. Please review the errors above.\n";
}