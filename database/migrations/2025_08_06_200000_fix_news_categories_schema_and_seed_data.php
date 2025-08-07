<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Fix news_categories table structure
        Schema::table('news_categories', function (Blueprint $table) {
            // Add active column if it doesn't exist
            if (!Schema::hasColumn('news_categories', 'active')) {
                $table->boolean('active')->default(true)->after('sort_order');
            }
        });

        // Seed news_categories with default data
        $newsCategories = [
            [
                'name' => 'Game Updates',
                'slug' => 'game-updates',
                'description' => 'Official game patches, balance changes, and new features',
                'color' => '#3b82f6',
                'icon' => '🎮',
                'sort_order' => 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Esports',
                'slug' => 'esports',
                'description' => 'Tournament news, team announcements, and competitive play',
                'color' => '#8b5cf6',
                'icon' => '🏆',
                'sort_order' => 2,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Hero Balance',
                'slug' => 'hero-balance',
                'description' => 'Hero nerfs, buffs, and ability changes',
                'color' => '#f59e0b',
                'icon' => '⚖️',
                'sort_order' => 3,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Community',
                'slug' => 'community',
                'description' => 'Community highlights, fan content, and events',
                'color' => '#10b981',
                'icon' => '👥',
                'sort_order' => 4,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Dev Insights',
                'slug' => 'dev-insights',
                'description' => 'Developer blogs, behind-the-scenes content',
                'color' => '#6366f1',
                'icon' => '💭',
                'sort_order' => 5,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Analysis',
                'slug' => 'analysis',
                'description' => 'Meta analysis, strategy guides, and gameplay tips',
                'color' => '#ef4444',
                'icon' => '📊',
                'sort_order' => 6,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($newsCategories as $category) {
            DB::table('news_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                $category
            );
        }

        // Seed forum_categories with default data if empty
        if (DB::table('forum_categories')->count() == 0) {
            $forumCategories = [
                [
                    'name' => 'General Discussion',
                    'slug' => 'general',
                    'description' => 'General Marvel Rivals discussion and community chat',
                    'color' => '#3B82F6',
                    'icon' => 'chat',
                    'is_active' => true,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Strategies & Tips',
                    'slug' => 'strategies',
                    'description' => 'Team compositions, tactics, and gameplay strategies',
                    'color' => '#10B981',
                    'icon' => 'strategy',
                    'is_active' => true,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Team Recruitment',
                    'slug' => 'team-recruitment',
                    'description' => 'Looking for team members or players',
                    'color' => '#F59E0B',
                    'icon' => 'users',
                    'is_active' => true,
                    'sort_order' => 3,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Tournament News',
                    'slug' => 'tournaments',
                    'description' => 'Official tournament announcements and updates',
                    'color' => '#EF4444',
                    'icon' => 'trophy',
                    'is_active' => true,
                    'sort_order' => 4,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Bug Reports',
                    'slug' => 'bugs',
                    'description' => 'Game issues and technical problems',
                    'color' => '#8B5CF6',
                    'icon' => 'bug',
                    'is_active' => true,
                    'sort_order' => 5,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Feedback',
                    'slug' => 'feedback',
                    'description' => 'Platform feedback and suggestions',
                    'color' => '#06B6D4',
                    'icon' => 'feedback',
                    'is_active' => true,
                    'sort_order' => 6,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ];

            foreach ($forumCategories as $category) {
                DB::table('forum_categories')->insert($category);
            }
        }
    }

    public function down()
    {
        // Remove the added active column from news_categories
        Schema::table('news_categories', function (Blueprint $table) {
            if (Schema::hasColumn('news_categories', 'active')) {
                $table->dropColumn('active');
            }
        });

        // Clean up seeded data
        DB::table('news_categories')->truncate();
        DB::table('forum_categories')->truncate();
    }
};