<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n=== TESTING NEWS SYSTEM ===\n\n";

// Test 1: Check if news_categories table has required columns
echo "1. Checking news_categories table structure...\n";
if (Schema::hasTable('news_categories')) {
    $columns = Schema::getColumnListing('news_categories');
    $requiredColumns = ['id', 'name', 'slug', 'description', 'icon', 'color', 'sort_order'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (empty($missingColumns)) {
        echo "✅ All required columns exist\n";
        echo "   Columns: " . implode(', ', $columns) . "\n";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
    }
} else {
    echo "❌ news_categories table does not exist\n";
}

// Test 2: Check mentions table
echo "\n2. Checking mentions table...\n";
if (Schema::hasTable('mentions')) {
    $mentionCount = DB::table('mentions')->where('mentionable_type', 'news')->count();
    echo "✅ Mentions table exists\n";
    echo "   News mentions count: $mentionCount\n";
    
    // Show sample mentions
    $sampleMentions = DB::table('mentions')
        ->where('mentionable_type', 'news')
        ->limit(3)
        ->get();
    
    if ($sampleMentions->count() > 0) {
        echo "   Sample mentions:\n";
        foreach ($sampleMentions as $mention) {
            echo "   - {$mention->mention_text} (Type: {$mention->mentioned_type}, ID: {$mention->mentioned_id})\n";
        }
    }
} else {
    echo "❌ mentions table does not exist\n";
}

// Test 3: Check news articles with mentions
echo "\n3. Checking news articles...\n";
$newsArticles = DB::table('news')
    ->where('status', 'published')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "Found " . $newsArticles->count() . " published articles\n";

foreach ($newsArticles as $article) {
    echo "\n   Article: " . substr($article->title, 0, 50) . "...\n";
    
    // Check mentions for this article
    $mentions = DB::table('mentions')
        ->where('mentionable_type', 'news')
        ->where('mentionable_id', $article->id)
        ->get();
    
    echo "   - Mentions: " . $mentions->count() . "\n";
    echo "   - Category: " . $article->category . "\n";
    echo "   - Published: " . $article->published_at . "\n";
    echo "   - Read time: " . calculateReadTime($article->content) . " min\n";
}

// Test 4: Check categories
echo "\n4. Checking news categories...\n";
$categories = DB::table('news_categories')->orderBy('sort_order')->get();
echo "Found " . $categories->count() . " categories:\n";
foreach ($categories as $cat) {
    echo "   - {$cat->name} (slug: {$cat->slug}, color: {$cat->color})\n";
}

// Test 5: Test API endpoints
echo "\n5. Testing API endpoints...\n";

// Test public news endpoint
$ch = curl_init('http://localhost/api/public/news');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   GET /api/public/news - Status: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['data']) && is_array($data['data'])) {
        echo "   ✅ Response contains " . count($data['data']) . " articles\n";
        
        // Check if articles have mentions
        $articlesWithMentions = 0;
        foreach ($data['data'] as $article) {
            if (isset($article['mentions']) && count($article['mentions']) > 0) {
                $articlesWithMentions++;
            }
        }
        echo "   ✅ Articles with mentions: $articlesWithMentions\n";
    }
} else {
    echo "   ❌ Failed to fetch news\n";
}

// Test categories endpoint
$ch = curl_init('http://localhost/api/public/news/categories');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   GET /api/public/news/categories - Status: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['data']) && is_array($data['data'])) {
        echo "   ✅ Response contains " . count($data['data']) . " categories\n";
    }
}

// Test 6: Create a test article with mentions
echo "\n6. Testing mention extraction...\n";
$testContent = "Great match between @team:LG and @team:TD today! @player:Vestola played amazingly.";
$mentions = extractTestMentions($testContent);
echo "   Test content: $testContent\n";
echo "   Extracted " . count($mentions) . " mentions\n";

echo "\n=== TEST COMPLETE ===\n";

function calculateReadTime($content) {
    $wordCount = str_word_count(strip_tags($content));
    return max(1, ceil($wordCount / 200));
}

function extractTestMentions($content) {
    $mentions = [];
    
    // Extract team mentions
    preg_match_all('/@team:([A-Z]+)/', $content, $teamMatches);
    foreach ($teamMatches[1] as $team) {
        $mentions[] = ['type' => 'team', 'name' => $team];
    }
    
    // Extract player mentions
    preg_match_all('/@player:([A-Za-z]+)/', $content, $playerMatches);
    foreach ($playerMatches[1] as $player) {
        $mentions[] = ['type' => 'player', 'name' => $player];
    }
    
    return $mentions;
}