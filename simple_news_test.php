<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "=== SIMPLE NEWS TEST ===\n\n";

// Get first user
$user = DB::table('users')->first();
if (!$user) {
    // Create a test user
    $userId = DB::table('users')->insertGetId([
        'name' => 'Test Admin',
        'email' => 'test@mrvl.net',
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "Created test user with ID: $userId\n";
} else {
    $userId = $user->id;
    echo "Using existing user: {$user->name} (ID: $userId)\n";
}

// Create news article
$title = "Test Article with Mentions";
$newsId = DB::table('news')->insertGetId([
    'title' => $title,
    'slug' => Str::slug($title),
    'excerpt' => 'This is a test excerpt',
    'content' => '<p>This is test content</p>',
    'category' => 'news',
    'author_id' => $userId,
    'status' => 'published',
    'published_at' => now(),
    'featured' => false,
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Created news article ID: $newsId\n";

// Test the API
echo "\nTesting API endpoints:\n";

// Test categories
$response = file_get_contents('https://staging.mrvl.net/api/public/news/categories');
$data = json_decode($response, true);
echo "Categories: " . count($data['data'] ?? []) . " found\n";

// Test news list
$response = file_get_contents('https://staging.mrvl.net/api/public/news');
$data = json_decode($response, true);
echo "News articles: " . count($data['data'] ?? []) . " found\n";

if (!empty($data['data'])) {
    $article = $data['data'][0];
    echo "\nFirst article:\n";
    echo "- Title: " . $article['title'] . "\n";
    echo "- Category: " . ($article['category']['name'] ?? 'N/A') . "\n";
    echo "- Read time: " . ($article['meta']['read_time'] ?? 'N/A') . " min\n";
    echo "- Mentions: " . count($article['mentions'] ?? []) . "\n";
}

echo "\n=== TEST COMPLETE ===\n";