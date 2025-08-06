<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Simple forum categories
    $categories = [
        ['name' => 'General Discussion', 'slug' => 'general', 'description' => 'General discussion', 'color' => '#3B82F6', 'icon' => '💬', 'is_active' => true, 'sort_order' => 1],
        ['name' => 'Strategy', 'slug' => 'strategy', 'description' => 'Strategy discussion', 'color' => '#10B981', 'icon' => '🧠', 'is_active' => true, 'sort_order' => 2],
        ['name' => 'Tournaments', 'slug' => 'tournaments', 'description' => 'Tournament discussion', 'color' => '#F59E0B', 'icon' => '🏆', 'is_active' => true, 'sort_order' => 3]
    ];

    // Insert categories (skip if exists)
    foreach ($categories as $category) {
        DB::table('forum_categories')->updateOrInsert(
            ['slug' => $category['slug']], 
            $category
        );
    }
    
    $generalCat = DB::table('forum_categories')->where('slug', 'general')->first();
    $adminUser = DB::table('users')->first();
    
    if (!$adminUser) {
        echo "No users found - creating demo user\n";
        DB::table('users')->insert([
            'name' => 'Admin',
            'email' => 'admin@mrvl.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);
        $adminUser = DB::table('users')->first();
    }

    // Simple threads
    $threads = [
        [
            'title' => 'Welcome to Forums',
            'slug' => 'welcome',
            'content' => 'Welcome to the Marvel Rivals forums!',
            'category_id' => $generalCat->id,
            'user_id' => $adminUser->id,
            'status' => 'active',
            'pinned' => true,
            'views' => 100,
            'replies_count' => 5,
            'upvotes' => 10,
            'downvotes' => 0
        ]
    ];

    // Insert threads (skip if exists)
    foreach ($threads as $thread) {
        DB::table('forum_threads')->updateOrInsert(
            ['slug' => $thread['slug']], 
            $thread
        );
    }
    
    $welcomeThread = DB::table('forum_threads')->where('slug', 'welcome')->first();
    
    // Simple posts
    if ($welcomeThread) {
        $posts = [
            [
                'thread_id' => $welcomeThread->id,
                'user_id' => $adminUser->id,
                'content' => 'Thanks for the welcome!',
                'status' => 'active',
                'upvotes' => 5,
                'downvotes' => 0
            ]
        ];

        // Insert posts (skip if exists)
        foreach ($posts as $post) {
            DB::table('forum_posts')->updateOrInsert(
                ['thread_id' => $post['thread_id'], 'content' => $post['content']], 
                $post
            );
        }
    }

    echo "✅ Forum data created successfully!\n";
    echo "📊 Created: 3 categories, 1 thread, 1 post\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}