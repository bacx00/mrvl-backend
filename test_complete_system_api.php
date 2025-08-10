<?php
echo "\n=====================================\n";
echo "COMPLETE FORUM & NEWS API TEST\n";
echo "=====================================\n\n";

$baseUrl = 'http://localhost';
$results = [];

// First, get auth token
echo "🔐 Getting auth token...\n";
$authResponse = shell_exec("curl -s -X POST {$baseUrl}/api/login -H 'Content-Type: application/json' -d '{\"email\":\"admin@mrvl.net\",\"password\":\"admin123\"}'");
$authData = json_decode($authResponse, true);
$token = $authData['access_token'] ?? 'test-token';
echo "Token obtained: " . substr($token, 0, 20) . "...\n\n";

// ============ FORUM TESTS ============
echo "📋 TESTING FORUM SYSTEM APIs\n";
echo "------------------------\n";

// 1. Get Forum Categories
echo "1. Testing GET /api/forums/categories\n";
$response = shell_exec("curl -s -X GET {$baseUrl}/api/forums/categories");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ Categories retrieved: " . count($data['data']) . " categories\n";
    $results['forum_categories'] = true;
} else {
    echo "❌ Failed to get categories\n";
    $results['forum_categories'] = false;
}

// 2. Get Forum Threads
echo "\n2. Testing GET /api/forums/threads\n";
$response = shell_exec("curl -s -X GET '{$baseUrl}/api/forums/threads?sort=latest'");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ Threads retrieved: " . count($data['data']) . " threads\n";
    $results['forum_threads'] = true;
    
    // Check date format
    if (!empty($data['data'])) {
        $thread = $data['data'][0];
        $hasProperDate = isset($thread['meta']['created_at']) && strpos($thread['meta']['created_at'], 'T') !== false;
        echo ($hasProperDate ? "✅" : "❌") . " Date format check: " . ($thread['meta']['created_at'] ?? 'N/A') . "\n";
        $results['forum_date_format'] = $hasProperDate;
    }
} else {
    echo "❌ Failed to get threads\n";
    $results['forum_threads'] = false;
}

// 3. Create Forum Thread
echo "\n3. Testing POST /api/user/forums/threads\n";
$threadData = json_encode([
    'title' => 'API Test Thread ' . time(),
    'content' => 'Testing with @Jhonny AR Media and @team:NE mentions',
    'category_id' => 1
]);
$response = shell_exec("curl -s -X POST {$baseUrl}/api/user/forums/threads -H 'Content-Type: application/json' -H 'Authorization: Bearer {$token}' -d '{$threadData}'");
$data = json_decode($response, true);
if ($data && isset($data['success']) && $data['success']) {
    echo "✅ Thread created successfully\n";
    $results['create_thread'] = true;
    $threadId = $data['data']['id'] ?? null;
} else {
    echo "❌ Failed to create thread: " . ($data['message'] ?? 'Unknown error') . "\n";
    $results['create_thread'] = false;
    $threadId = 2; // Use existing thread
}

// 4. Post Reply
echo "\n4. Testing POST /api/user/forums/threads/{id}/posts\n";
$replyData = json_encode([
    'content' => 'Test reply with @player:rymazing mention',
    'parent_id' => null
]);
$response = shell_exec("curl -s -X POST {$baseUrl}/api/user/forums/threads/{$threadId}/posts -H 'Content-Type: application/json' -H 'Authorization: Bearer {$token}' -d '{$replyData}'");
$data = json_decode($response, true);
if ($data && isset($data['success']) && $data['success']) {
    echo "✅ Reply posted successfully\n";
    $results['post_reply'] = true;
    $postId = $data['data']['post']['id'] ?? null;
} else {
    echo "❌ Failed to post reply: " . ($data['message'] ?? 'Unknown error') . "\n";
    $results['post_reply'] = false;
    $postId = 3; // Use existing post
}

// 5. Vote on Forum Post
echo "\n5. Testing POST /api/forums/posts/{id}/vote\n";
$voteData = json_encode(['vote_type' => 'upvote']);
$response = shell_exec("curl -s -X POST {$baseUrl}/api/forums/posts/{$postId}/vote -H 'Content-Type: application/json' -H 'Authorization: Bearer {$token}' -d '{$voteData}'");
$data = json_decode($response, true);
if ($data && isset($data['success'])) {
    echo "✅ Vote action completed: " . ($data['message'] ?? 'Success') . "\n";
    $results['forum_vote'] = true;
} else {
    echo "❌ Failed to vote\n";
    $results['forum_vote'] = false;
}

// ============ NEWS TESTS ============
echo "\n📰 TESTING NEWS SYSTEM APIs\n";
echo "------------------------\n";

// 6. Get News Categories
echo "6. Testing GET /api/news/categories\n";
$response = shell_exec("curl -s -X GET {$baseUrl}/api/news/categories");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ News categories retrieved: " . count($data['data']) . " categories\n";
    $results['news_categories'] = true;
} else {
    echo "❌ Failed to get news categories\n";
    $results['news_categories'] = false;
}

// 7. Get News Articles
echo "\n7. Testing GET /api/news\n";
$response = shell_exec("curl -s -X GET '{$baseUrl}/api/news?category=all&sort=latest'");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ News articles retrieved: " . count($data['data']) . " articles\n";
    $results['news_articles'] = true;
    
    // Check date format
    if (!empty($data['data'])) {
        $article = $data['data'][0];
        $hasProperDate = isset($article['meta']['published_at']) && strpos($article['meta']['published_at'], 'T') !== false;
        echo ($hasProperDate ? "✅" : "❌") . " Date format check: " . ($article['meta']['published_at'] ?? 'N/A') . "\n";
        $results['news_date_format'] = $hasProperDate;
    }
} else {
    echo "❌ Failed to get news articles\n";
    $results['news_articles'] = false;
}

// 8. Get Single News Article
echo "\n8. Testing GET /api/news/{id}\n";
$response = shell_exec("curl -s -X GET {$baseUrl}/api/news/1");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ News article retrieved: " . $data['data']['title'] . "\n";
    $results['single_news'] = true;
    $newsId = $data['data']['id'];
} else {
    echo "❌ Failed to get news article\n";
    $results['single_news'] = false;
    $newsId = 1;
}

// 9. Track News View
echo "\n9. Testing POST /api/news/{id}/view\n";
$response = shell_exec("curl -s -X POST {$baseUrl}/api/news/{$newsId}/view -H 'Authorization: Bearer {$token}'");
$data = json_decode($response, true);
if ($data && isset($data['success']) && $data['success']) {
    echo "✅ View tracked: " . $data['views'] . " views\n";
    $results['track_view'] = true;
} else {
    echo "❌ Failed to track view\n";
    $results['track_view'] = false;
}

// 10. Post News Comment
echo "\n10. Testing POST /api/news/{id}/comments\n";
$commentData = json_encode([
    'content' => 'Test comment with @team:TL mention',
    'parent_id' => null
]);
$response = shell_exec("curl -s -X POST {$baseUrl}/api/news/{$newsId}/comments -H 'Content-Type: application/json' -H 'Authorization: Bearer {$token}' -d '{$commentData}'");
$data = json_decode($response, true);
if ($data && isset($data['success']) && $data['success']) {
    echo "✅ Comment posted successfully\n";
    $results['post_comment'] = true;
    $commentId = $data['data']['id'] ?? 1;
} else {
    echo "❌ Failed to post comment: " . ($data['message'] ?? 'Unknown error') . "\n";
    $results['post_comment'] = false;
    $commentId = 1;
}

// 11. Vote on News Article
echo "\n11. Testing POST /api/user/votes (news article)\n";
$voteData = json_encode([
    'votable_type' => 'news',
    'votable_id' => $newsId,
    'vote_type' => 'upvote'
]);
$response = shell_exec("curl -s -X POST {$baseUrl}/api/user/votes/ -H 'Content-Type: application/json' -H 'Authorization: Bearer {$token}' -d '{$voteData}'");
$data = json_decode($response, true);
if ($data && isset($data['success'])) {
    echo "✅ News vote completed: " . ($data['message'] ?? 'Success') . "\n";
    $results['news_vote'] = true;
} else {
    echo "❌ Failed to vote on news\n";
    $results['news_vote'] = false;
}

// 12. Vote on News Comment
echo "\n12. Testing POST /api/user/votes (news comment)\n";
$voteData = json_encode([
    'votable_type' => 'news_comment',
    'votable_id' => $commentId,
    'vote_type' => 'downvote'
]);
$response = shell_exec("curl -s -X POST {$baseUrl}/api/user/votes/ -H 'Content-Type: application/json' -H 'Authorization: Bearer {$token}' -d '{$voteData}'");
$data = json_decode($response, true);
if ($data && isset($data['success'])) {
    echo "✅ Comment vote completed: " . ($data['message'] ?? 'Success') . "\n";
    $results['comment_vote'] = true;
} else {
    echo "❌ Failed to vote on comment\n";
    $results['comment_vote'] = false;
}

// ============ MENTIONS TESTS ============
echo "\n👥 TESTING MENTIONS SYSTEM\n";
echo "------------------------\n";

// 13. Search Mentions
echo "13. Testing GET /api/public/mentions/search\n";
$response = shell_exec("curl -s -X GET '{$baseUrl}/api/public/mentions/search?q=a&type=all&limit=10'");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ Mention search working: " . count($data['data']) . " results\n";
    $results['mention_search'] = true;
} else {
    echo "❌ Failed to search mentions\n";
    $results['mention_search'] = false;
}

// 14. Popular Mentions
echo "\n14. Testing GET /api/public/mentions/popular\n";
$response = shell_exec("curl -s -X GET '{$baseUrl}/api/public/mentions/popular?limit=8'");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ Popular mentions retrieved: " . count($data['data']) . " mentions\n";
    $results['popular_mentions'] = true;
} else {
    echo "❌ Failed to get popular mentions\n";
    $results['popular_mentions'] = false;
}

// ============ ADMIN TESTS ============
echo "\n🔧 TESTING ADMIN ENDPOINTS\n";
echo "------------------------\n";

// 15. Admin News Moderation
echo "15. Testing GET /api/api/admin/news-moderation\n";
$response = shell_exec("curl -s -X GET '{$baseUrl}/api/api/admin/news-moderation?page=1&limit=50' -H 'Authorization: Bearer {$token}'");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ Admin news moderation working\n";
    $results['admin_news'] = true;
} else {
    echo "❌ Admin news moderation failed\n";
    $results['admin_news'] = false;
}

// 16. Admin News Categories
echo "\n16. Testing GET /api/api/admin/news-moderation/categories\n";
$response = shell_exec("curl -s -X GET '{$baseUrl}/api/api/admin/news-moderation/categories' -H 'Authorization: Bearer {$token}'");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ Admin news categories working\n";
    $results['admin_categories'] = true;
} else {
    echo "❌ Admin news categories failed\n";
    $results['admin_categories'] = false;
}

// 17. Admin Forum Moderation
echo "\n17. Testing GET /api/admin/forums-moderation/threads\n";
$response = shell_exec("curl -s -X GET '{$baseUrl}/api/admin/forums-moderation/threads' -H 'Authorization: Bearer {$token}'");
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "✅ Admin forum moderation working\n";
    $results['admin_forums'] = true;
} else {
    echo "❌ Admin forum moderation failed\n";
    $results['admin_forums'] = false;
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
    echo "\n🎉 ALL TESTS PASSED! Both forums and news systems are working perfectly!\n";
} else {
    echo "\n⚠️ Some tests failed. Please review the errors above.\n";
}