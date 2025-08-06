<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

echo "Testing Video Embedding System\n";
echo "==============================\n\n";

// Get admin user
$admin = DB::table('users')->where('role', 'admin')->first();
if (!$admin) {
    die("No admin user found!\n");
}

// Create a test article with video embeds
$content = <<<EOT
Marvel Rivals Championship 2025 is heating up! Check out these amazing highlights:

[youtube:dQw4w9WgXcQ]

The tournament featured incredible plays from top teams. Here's a Twitch clip from the finals:

[twitch-clip:ExcitedSpicyGazellePJSalt]

And here's the full VOD of the grand finals:

[twitch-video:1234567890]

The community reaction was amazing:

[tweet:1234567890123456789]

You can also watch the highlight reel on YouTube: https://www.youtube.com/watch?v=ScMzIvxBSi4
EOT;

// Create the article
$newsId = DB::table('news')->insertGetId([
    'title' => 'Marvel Rivals Championship 2025 - Video Highlights',
    'slug' => 'marvel-rivals-championship-2025-video-highlights-' . time(),
    'excerpt' => 'Watch the best moments from the Marvel Rivals Championship 2025',
    'content' => $content,
    'category' => 'Tournament',
    'author_id' => $admin->id,
    'status' => 'published',
    'featured' => true,
    'published_at' => now(),
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Created test article ID: $newsId\n\n";

// Test the extraction
$controller = new \App\Http\Controllers\NewsController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('extractVideoEmbeds');
$method->setAccessible(true);

$videos = $method->invoke($controller, $content);

echo "Extracted videos:\n";
echo str_repeat('-', 50) . "\n";

foreach ($videos as $video) {
    echo "Platform: {$video['platform']}\n";
    echo "Video ID: {$video['video_id']}\n";
    echo "Embed URL: {$video['embed_url']}\n";
    echo "Type: " . ($video['type'] ?? 'N/A') . "\n";
    if ($video['thumbnail']) {
        echo "Thumbnail: {$video['thumbnail']}\n";
    }
    echo "\n";
}

echo "\nTotal videos found: " . count($videos) . "\n";

// Now fetch the article through the API
echo "\nFetching article through API...\n";
$response = app()->call('\App\Http\Controllers\NewsController@show', ['identifier' => $newsId]);
$articleData = json_decode($response->getContent(), true);

if ($articleData['success']) {
    echo "Article videos from API: " . count($articleData['data']['videos']) . "\n";
    echo "\nArticle URL: https://staging.mrvl.net/#news-detail/{$newsId}\n";
} else {
    echo "Error fetching article: " . $articleData['message'] . "\n";
}

echo "\nDone!\n";