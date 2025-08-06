<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// Get an admin user
$adminUser = DB::table('users')->where('email', 'admin@mrvl.net')->first();
if (!$adminUser) {
    die("Admin user not found\n");
}

// Login as admin
Auth::loginUsingId($adminUser->id);

echo "Creating test news article with mentions...\n";

// Create a test news article
$title = "Exciting Match: @team:LG defeats @team:TD in thriller";
$excerpt = "In an intense showdown, @player:Vestola led Luminosity Gaming to victory against Toronto Defiant";
$content = <<<EOT
<p>The match between @team:LG and @team:TD was one for the ages. @player:Vestola showcased incredible skill as a tank player, leading his team to a decisive victory.</p>

<p>The first map saw @team:TD take an early lead, but @team:LG quickly adapted their strategy. @player:False from Oxygen Esports, who was spectating, commented on the exceptional gameplay.</p>

<p>Key highlights:</p>
<ul>
<li>@player:Vestola's amazing tank plays</li>
<li>@team:LG's coordinated team fights</li>
<li>The comeback on map 3</li>
</ul>

<p>This victory puts @team:LG in a strong position for the upcoming playoffs.</p>
EOT;

$slug = Str::slug($title);

// Insert the article
$newsId = DB::table('news')->insertGetId([
    'title' => $title,
    'slug' => $slug,
    'excerpt' => $excerpt,
    'content' => $content,
    'category' => 'esports',
    'author_id' => $adminUser->id,
    'status' => 'published',
    'published_at' => now(),
    'featured' => true,
    'views' => 0,
    'comments_count' => 0,
    'upvotes' => 0,
    'downvotes' => 0,
    'score' => 0,
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Created news article with ID: $newsId\n";

// Now process mentions using the controller method
$controller = new \App\Http\Controllers\NewsController();

// Use reflection to access private method
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('processMentions');
$method->setAccessible(true);

// Process mentions for title, excerpt and content
echo "Processing mentions...\n";
$method->invoke($controller, $title, $newsId);
$method->invoke($controller, $excerpt, $newsId);
$method->invoke($controller, $content, $newsId);

// Verify mentions were created
$mentionCount = DB::table('mentions')
    ->where('mentionable_type', 'news')
    ->where('mentionable_id', $newsId)
    ->count();

echo "Created $mentionCount mentions for the article\n";

// Show the mentions
$mentions = DB::table('mentions')
    ->where('mentionable_type', 'news')
    ->where('mentionable_id', $newsId)
    ->get();

echo "\nMentions created:\n";
foreach ($mentions as $mention) {
    echo "- {$mention->mention_text} (Type: {$mention->mentioned_type}, ID: {$mention->mentioned_id})\n";
}

echo "\nTest article created successfully!\n";
echo "View it at: https://staging.mrvl.net/#news-detail?id=$newsId\n";