<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Achievement definitions table
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('icon')->nullable();
            $table->string('badge_color', 20)->default('#3B82F6'); // Tailwind blue-500
            $table->enum('category', ['social', 'activity', 'milestone', 'streak', 'challenge', 'special']);
            $table->enum('rarity', ['common', 'uncommon', 'rare', 'epic', 'legendary']);
            $table->integer('points')->default(10);
            $table->json('requirements')->nullable(); // Flexible requirement structure
            $table->boolean('is_secret')->default(false); // Hidden achievements
            $table->boolean('is_repeatable')->default(false);
            $table->integer('max_completions')->nullable(); // For repeatable achievements
            $table->datetime('available_from')->nullable();
            $table->datetime('available_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // User achievements (earned achievements)
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained()->onDelete('cascade');
            $table->json('progress')->nullable(); // Track progress towards completion
            $table->integer('current_count')->default(0);
            $table->integer('required_count')->default(1);
            $table->boolean('is_completed')->default(false);
            $table->datetime('completed_at')->nullable();
            $table->integer('completion_count')->default(0); // For repeatable achievements
            $table->json('metadata')->nullable(); // Extra data (match_id, event_id, etc.)
            $table->timestamps();
            
            $table->index(['user_id', 'achievement_id']);
            $table->index(['user_id', 'is_completed']);
            $table->index(['achievement_id', 'is_completed']);
        });

        // User streaks
        Schema::create('user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('streak_type', ['login', 'comment', 'forum_post', 'prediction', 'vote']);
            $table->integer('current_count')->default(0);
            $table->integer('best_count')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->datetime('streak_started_at')->nullable();
            $table->datetime('streak_broken_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['user_id', 'streak_type']);
            $table->index(['user_id', 'is_active']);
        });

        // Challenges (time-limited achievements)
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('icon')->nullable();
            $table->string('banner_image')->nullable();
            $table->json('requirements');
            $table->json('rewards'); // Points, badges, titles, etc.
            $table->datetime('starts_at');
            $table->datetime('ends_at');
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'extreme']);
            $table->integer('max_participants')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // User challenge participation
        Schema::create('user_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->json('progress')->nullable();
            $table->integer('current_score')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->datetime('started_at');
            $table->datetime('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'challenge_id']);
            $table->index(['challenge_id', 'is_completed']);
        });

        // Leaderboards
        Schema::create('leaderboards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['points', 'achievements', 'streak', 'activity', 'custom']);
            $table->enum('period', ['daily', 'weekly', 'monthly', 'all_time']);
            $table->json('criteria'); // How to calculate rankings
            $table->boolean('is_active')->default(true);
            $table->datetime('reset_at')->nullable(); // When leaderboard resets
            $table->timestamps();
        });

        // Leaderboard entries
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('rank');
            $table->decimal('score', 10, 2);
            $table->json('details')->nullable(); // Additional ranking info
            $table->date('period_date'); // For time-based leaderboards
            $table->timestamps();
            
            $table->unique(['leaderboard_id', 'user_id', 'period_date']);
            $table->index(['leaderboard_id', 'period_date', 'rank']);
        });

        // User titles (earned from achievements)
        Schema::create('user_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('color', 20)->default('#3B82F6');
            $table->foreignId('achievement_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_active')->default(false); // Only one active title per user
            $table->datetime('earned_at');
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
        });

        // Notifications for achievements
        Schema::create('achievement_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['achievement_earned', 'streak_milestone', 'challenge_completed', 'leaderboard_rank']);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Achievement ID, streak info, etc.
            $table->boolean('is_read')->default(false);
            $table->datetime('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('achievement_notifications');
        Schema::dropIfExists('user_titles');
        Schema::dropIfExists('leaderboard_entries');
        Schema::dropIfExists('leaderboards');
        Schema::dropIfExists('user_challenges');
        Schema::dropIfExists('challenges');
        Schema::dropIfExists('user_streaks');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};