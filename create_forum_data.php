<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Create Forum Categories
    $categories = [
        [
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
            'description' => 'General Marvel Rivals discussion and community chat',
            'color' => '#3B82F6',
            'icon' => '💬',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Strategy & Tips',
            'slug' => 'strategy-tips',
            'description' => 'Share strategies, tips, and gameplay guides',
            'color' => '#10B981',
            'icon' => '🧠',
            'is_active' => true,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Tournament Discussion',
            'slug' => 'tournament-discussion',
            'description' => 'Discuss tournaments, matches, and competitive play',
            'color' => '#F59E0B',
            'icon' => '🏆',
            'is_active' => true,
            'sort_order' => 3,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Hero Discussion',
            'slug' => 'hero-discussion', 
            'description' => 'Discuss heroes, abilities, and balance changes',
            'color' => '#EF4444',
            'icon' => '🦸',
            'is_active' => true,
            'sort_order' => 4,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Team Recruitment',
            'slug' => 'team-recruitment',
            'description' => 'Find teammates and recruit for your team',
            'color' => '#8B5CF6',
            'icon' => '👥',
            'is_active' => true,
            'sort_order' => 5,
            'created_at' => now(),
            'updated_at' => now()
        ]
    ];

    // Insert categories
    foreach ($categories as $category) {
        DB::table('forum_categories')->insertOrIgnore($category);
    }

    // Get category IDs
    $generalCat = DB::table('forum_categories')->where('slug', 'general-discussion')->first();
    $strategyCat = DB::table('forum_categories')->where('slug', 'strategy-tips')->first();
    $tournamentCat = DB::table('forum_categories')->where('slug', 'tournament-discussion')->first();
    $heroCat = DB::table('forum_categories')->where('slug', 'hero-discussion')->first();
    $recruitCat = DB::table('forum_categories')->where('slug', 'team-recruitment')->first();

    // Get admin user
    $adminUser = DB::table('users')->where('role', 'admin')->first();
    if (!$adminUser) {
        $adminUser = DB::table('users')->first();
    }

    // Create sample threads
    $threads = [
        [
            'title' => 'Welcome to Marvel Rivals Forums!',
            'slug' => 'welcome-to-marvel-rivals-forums',
            'content' => "Welcome to the official Marvel Rivals community forums!\n\nHere you can:\n- Discuss strategies and gameplay\n- Find teammates for competitive play\n- Stay updated on tournaments and events\n- Share your favorite hero builds\n\nPlease be respectful and follow the community guidelines. Let's build an amazing Marvel Rivals community together!",
            'category_id' => $generalCat->id,
            'user_id' => $adminUser->id,
            'status' => 'published',
            'pinned' => true,
            'views' => 156,
            'replies_count' => 23,
            'upvotes' => 45,
            'downvotes' => 2,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5)
        ],
        [
            'title' => 'Best Spider-Man Build for Ranked Play?',
            'slug' => 'best-spider-man-build-ranked-play',
            'content' => "I've been maining Spider-Man in ranked and looking for the optimal build. Currently running:\n\n- Enhanced Web-Shooters\n- Spider-Sense Amplifier\n- Wall-Crawler Mobility\n\nWhat builds are you all using? Any tips for climbing ranks with Spider-Man?",
            'category_id' => $strategyCat->id,
            'author_id' => $adminUser->id,
            'status' => 'published',
            'is_pinned' => false,
            'is_featured' => false,
            'view_count' => 89,
            'reply_count' => 12,
            'upvotes' => 18,
            'downvotes' => 1,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(1)
        ],
        [
            'title' => 'Marvel Rivals Championship 2025 - Predictions?',
            'slug' => 'marvel-rivals-championship-2025-predictions',
            'content' => "The Marvel Rivals Championship 2025 is coming up fast! Looking at the teams registered:\n\n- Luminosity Gaming\n- NRG Esports  \n- TSM\n- Cloud9\n- Team Liquid\n\nWho do you think will take home the championship? I'm rooting for LG but NRG looks strong this season.",
            'category_id' => $tournamentCat->id,
            'author_id' => $adminUser->id,
            'status' => 'published',
            'is_pinned' => false,
            'is_featured' => true,
            'view_count' => 234,
            'reply_count' => 34,
            'upvotes' => 67,
            'downvotes' => 8,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subHours(6)
        ],
        [
            'title' => 'Iron Man vs Doctor Strange - Who\'s Better?',
            'slug' => 'iron-man-vs-doctor-strange-whos-better',
            'content' => "Eternal debate: Iron Man or Doctor Strange for DPS?\n\nIron Man Pros:\n+ High sustained damage\n+ Good mobility with flight\n+ Versatile range options\n\nDoctor Strange Pros:\n+ Incredible burst damage\n+ Portal utility for team\n+ Strong crowd control\n\nWhich hero do you prefer and why?",
            'category_id' => $heroCat->id,
            'author_id' => $adminUser->id,
            'status' => 'published',
            'is_pinned' => false,
            'is_featured' => false,
            'view_count' => 145,
            'reply_count' => 28,
            'upvotes' => 31,
            'downvotes' => 5,
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subHours(3)
        ],
        [
            'title' => '[LFT] Diamond Tank Player Looking for Team',
            'slug' => 'lft-diamond-tank-player-looking-team',
            'content' => "Diamond rank tank main looking for a competitive team!\n\n**About me:**\n- Current rank: Diamond 2\n- Main heroes: Captain America, Hulk, Groot\n- Available: Evenings EST, weekends\n- Experience: 2 years competitive gaming\n\n**Looking for:**\n- Semi-professional team\n- Regular practice schedule\n- Tournament participation\n\nDM me if interested!",
            'category_id' => $recruitCat->id,
            'author_id' => $adminUser->id,
            'status' => 'published',
            'is_pinned' => false,
            'is_featured' => false,
            'view_count' => 78,
            'reply_count' => 7,
            'upvotes' => 12,
            'downvotes' => 0,
            'created_at' => now()->subHours(12),
            'updated_at' => now()->subHours(2)
        ]
    ];

    // Insert threads
    foreach ($threads as $thread) {
        DB::table('forum_threads')->insertOrIgnore($thread);
    }

    // Create sample posts for some threads
    $welcomeThread = DB::table('forum_threads')->where('slug', 'welcome-to-marvel-rivals-forums')->first();
    $spiderManThread = DB::table('forum_threads')->where('slug', 'best-spider-man-build-ranked-play')->first();

    $posts = [
        [
            'thread_id' => $welcomeThread->id,
            'author_id' => $adminUser->id,
            'content' => "Thanks for the warm welcome! Excited to be part of this community. Already learned so much from the strategy discussions.",
            'status' => 'published',
            'upvotes' => 8,
            'downvotes' => 0,
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4)
        ],
        [
            'thread_id' => $welcomeThread->id,
            'author_id' => $adminUser->id,
            'content' => "Love seeing how active this community is! The tournament discussions are especially helpful for following the competitive scene.",
            'status' => 'published',
            'upvotes' => 12,
            'downvotes' => 1,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3)
        ],
        [
            'thread_id' => $spiderManThread->id,
            'author_id' => $adminUser->id,
            'content' => "I've had great success with a more mobility-focused build:\n\n- Advanced Web-Slinging\n- Spider-Sense Reflexes  \n- Acrobatic Combat\n\nThe extra mobility helps a lot in higher ranks where positioning is key.",
            'status' => 'published',
            'upvotes' => 15,
            'downvotes' => 2,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2)
        ]
    ];

    // Insert posts
    foreach ($posts as $post) {
        DB::table('forum_posts')->insertOrIgnore($post);
    }

    echo "✅ Forum system restored successfully!\n";
    echo "📊 Created:\n";
    echo "  - " . count($categories) . " forum categories\n";
    echo "  - " . count($threads) . " forum threads\n";
    echo "  - " . count($posts) . " forum posts\n";
    echo "\n🔗 Categories created:\n";
    foreach ($categories as $cat) {
        echo "  - {$cat['icon']} {$cat['name']}\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}