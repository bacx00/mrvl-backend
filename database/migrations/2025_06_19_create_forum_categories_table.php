<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Create forum_categories table
        Schema::create('forum_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable(); // hex color
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Insert default Marvel Rivals categories
        $categories = [
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

        foreach ($categories as $category) {
            DB::table('forum_categories')->insert($category);
        }
    }

    public function down()
    {
        Schema::dropIfExists('forum_categories');
    }
};