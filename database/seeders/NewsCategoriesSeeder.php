<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NewsCategoriesSeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Game Updates',
                'slug' => 'game-updates',
                'description' => 'Official game patches, balance changes, and new features',
                'color' => '#3b82f6',
                'icon' => '🎮',
                'sort_order' => 1,
                'active' => true,
            ],
            [
                'name' => 'Esports',
                'slug' => 'esports',
                'description' => 'Tournament news, team announcements, and competitive play',
                'color' => '#8b5cf6',
                'icon' => '🏆',
                'sort_order' => 2,
                'active' => true,
            ],
            [
                'name' => 'Hero Balance',
                'slug' => 'hero-balance',
                'description' => 'Hero nerfs, buffs, and ability changes',
                'color' => '#f59e0b',
                'icon' => '⚖️',
                'sort_order' => 3,
                'active' => true,
            ],
            [
                'name' => 'Community',
                'slug' => 'community',
                'description' => 'Community highlights, fan content, and events',
                'color' => '#10b981',
                'icon' => '👥',
                'sort_order' => 4,
                'active' => true,
            ],
            [
                'name' => 'Dev Insights',
                'slug' => 'dev-insights',
                'description' => 'Developer blogs, behind-the-scenes content',
                'color' => '#6366f1',
                'icon' => '💭',
                'sort_order' => 5,
                'active' => true,
            ],
            [
                'name' => 'Analysis',
                'slug' => 'analysis',
                'description' => 'Meta analysis, strategy guides, and gameplay tips',
                'color' => '#ef4444',
                'icon' => '📊',
                'sort_order' => 6,
                'active' => true,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('news_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                array_merge($category, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}