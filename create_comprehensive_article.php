<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\News;
use App\Models\User;

// Comprehensive article content with mentions and video embeds
$comprehensiveContent = "The Marvel Rivals competitive scene reached new heights this weekend as @team:1:Luminosity Gaming secured a decisive victory over @team:2:NRG Esports in the Grand Finals of the Marvel Rivals Championship 2025.

## Match Highlights

The series started with an explosive first map where @player:1:Danteh showcased exceptional Spider-Man gameplay. Watch this incredible play that turned the tide:

[youtube:xQl0zxdhE5s]

@team:1:Luminosity Gaming's coordination was on full display, with @player:3:Punk leading the charge on Captain America. The synergy between @player:1:Danteh and @player:4:Poko was particularly noteworthy, creating space for their DPS players to dominate.

## Turning Point in Map 3

The pivotal moment came during the third map on Asgard. Here's the full match VOD from the official stream:

https://www.youtube.com/watch?v=dQw4w9WgXcQ

@team:2:NRG Esports attempted a comeback with @player:2:nero switching to Iron Man, but the momentum had already shifted. The crowd went wild when @player:5:UltraViolet landed a game-changing Doctor Strange ultimate that caught three members of NRG.

## Community Reactions

The community response has been overwhelming. Pro player tweets have been pouring in:

[tweet:1821924055662305488]

Popular streamer shroud shared his analysis of the final teamfight:

[twitch-clip:ExcitedSpicyGazellePJSalt]

## Post-Match Interview

In the post-match interview, @player:1:Danteh spoke about their preparation: \"We knew @team:2:NRG Esports would come prepared, especially with @player:2:nero's flexibility. Our coach helped us identify key weaknesses in their defensive setups.\"

Watch the full winner's interview here:

[twitch-video:2316823598]

## Looking Ahead

With this victory, @team:1:Luminosity Gaming has secured their spot in the international finals. @team:3:TSM and @team:4:Oxygen Esports will face off next week to determine the second finalist from the region.

@player:3:Punk summed it up perfectly: \"This is just the beginning. We're hungry for more, and we know teams like @team:5:Cloud9 are watching and preparing. The competition is only getting fiercer.\"

## Statistical Breakdown

The match statistics tell the full story:

https://youtu.be/oHDx1-rN89U

@player:1:Danteh finished with the highest damage output across all five maps, while @player:4:Poko's tank play created the foundation for their success. On the other side, despite the loss, @player:2:nero's performance on Magneto in Map 2 was a masterclass in positioning.

## Fan Reactions and Clips

The community has been creating incredible highlight compilations:

[youtube:PLHQyGGzRHYIbN8X4xkPaWLuPnZhYGXmms]

This tournament has proven that Marvel Rivals has truly arrived as a premier esports title. With teams like @team:1:Luminosity Gaming setting the bar high, and rising stars like @player:5:UltraViolet making their mark, the future of competitive Marvel Rivals looks brighter than ever.

Stay tuned for more coverage as we head into the international stage, where @team:1:Luminosity Gaming will represent North America against the world's best teams.";

try {
    // Get or create author
    $author = User::where('role', 'admin')->first();
    if (!$author) {
        $author = User::first();
    }
    
    // Get or create news category
    $categoryId = DB::table('news_categories')
        ->where('name', 'Tournament')
        ->value('id');
    
    if (!$categoryId) {
        $categoryId = DB::table('news_categories')->insertGetId([
            'name' => 'Tournament',
            'slug' => 'tournament',
            'color' => '#FF4655',
            'icon' => '🏆',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    // Create the comprehensive article
    $article = News::create([
        'title' => 'Luminosity Gaming Dominates NRG Esports in Marvel Rivals Championship 2025 Grand Finals',
        'slug' => 'luminosity-gaming-dominates-nrg-esports-mrvl-championship-2025-' . time(),
        'content' => $comprehensiveContent,
        'excerpt' => 'Luminosity Gaming showcased exceptional teamwork and individual skill to defeat NRG Esports 3-1 in the Grand Finals, securing their spot in the international championship.',
        'author_id' => $author->id,
        'category_id' => $categoryId,
        'region' => 'NA',
        'featured_image' => '/images/news/championship-finals.jpg',
        'meta' => json_encode([
            'featured' => true,
            'read_time' => 8,
            'views' => 0,
            'keywords' => ['championship', 'finals', 'luminosity', 'nrg', 'tournament']
        ]),
        'published_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // Extract and save mentions
    preg_match_all('/@(team|player):(\d+):([^@\s]+(?:\s+[^@\s]+)*?)(?=\s|$|@|\.|,|\!|\?|\'s)/i', $comprehensiveContent, $matches);
    
    $mentions = [];
    $position = 0;
    
    for ($i = 0; $i < count($matches[0]); $i++) {
        $type = strtolower($matches[1][$i]);
        $id = $matches[2][$i];
        $fullMatch = $matches[0][$i];
        $name = $matches[3][$i];
        
        // Find position in content
        $position = strpos($comprehensiveContent, $fullMatch, $position);
        
        // Verify the entity exists
        if ($type === 'team') {
            $exists = DB::table('teams')->where('id', $id)->exists();
        } else {
            $exists = DB::table('players')->where('id', $id)->exists();
        }
        
        if ($exists) {
            $mentions[] = [
                'mentionable_type' => 'App\\Models\\News',
                'mentionable_id' => $article->id,
                'mentioned_type' => $type === 'team' ? 'App\\Models\\Team' : 'App\\Models\\Player',
                'mentioned_id' => $id,
                'context' => 'news_article',
                'mention_text' => $fullMatch,
                'position_start' => $position,
                'position_end' => $position + strlen($fullMatch),
                'mentioned_by' => $author->id,
                'mentioned_at' => now(),
                'is_active' => true,
                'metadata' => json_encode(['name' => $name]),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        $position += strlen($fullMatch);
    }
    
    if (!empty($mentions)) {
        DB::table('mentions')->insert($mentions);
    }

    echo "✅ Comprehensive article created successfully!\n";
    echo "📰 Article ID: {$article->id}\n";
    echo "📝 Title: {$article->title}\n";
    echo "🔗 Slug: {$article->slug}\n";
    echo "👥 Mentions added: " . count($mentions) . "\n";
    echo "📹 Video embeds: Multiple YouTube, Twitch, and Twitter embeds included\n";
    echo "\n";
    echo "View at: https://staging.mrvl.net/#news-detail?id={$article->id}\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}