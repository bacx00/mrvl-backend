<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Fix players table - update role enum to use Marvel Rivals roles
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE players MODIFY COLUMN role ENUM('Vanguard', 'Duelist', 'Strategist', 'Flex', 'Sub') NOT NULL");
        }

        // Fix events table - update type enum to include all event types
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE events MODIFY COLUMN type ENUM(
                'championship',
                'tournament', 
                'scrim',
                'qualifier',
                'regional',
                'international',
                'invitational',
                'community',
                'friendly',
                'practice',
                'exhibition'
            ) NOT NULL DEFAULT 'tournament'");
        }

        // Add missing columns to players table
        if (!Schema::hasColumn('players', 'peak_rating')) {
            Schema::table('players', function (Blueprint $table) {
                $table->float('peak_rating')->default(0)->after('rating');
            });
        }

        // Add missing columns to marvel_rivals_heroes table
        if (Schema::hasTable('marvel_rivals_heroes')) {
            Schema::table('marvel_rivals_heroes', function (Blueprint $table) {
                if (!Schema::hasColumn('marvel_rivals_heroes', 'season_added')) {
                    $table->string('season_added')->default('Launch')->after('active');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'is_new')) {
                    $table->boolean('is_new')->default(false)->after('season_added');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'release_date')) {
                    $table->date('release_date')->nullable()->after('is_new');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'difficulty')) {
                    $table->enum('difficulty', ['Easy', 'Medium', 'Hard'])->default('Medium')->after('release_date');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'usage_rate')) {
                    $table->float('usage_rate')->default(0)->after('difficulty');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'win_rate')) {
                    $table->float('win_rate')->default(0)->after('usage_rate');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'pick_rate')) {
                    $table->float('pick_rate')->default(0)->after('win_rate');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'ban_rate')) {
                    $table->float('ban_rate')->default(0)->after('pick_rate');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'lore')) {
                    $table->text('lore')->nullable()->after('description');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'voice_actor')) {
                    $table->string('voice_actor')->nullable()->after('lore');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'height')) {
                    $table->string('height')->nullable()->after('voice_actor');
                }
                if (!Schema::hasColumn('marvel_rivals_heroes', 'universe')) {
                    $table->string('universe')->default('Marvel')->after('height');
                }
            });
        }

        // Create missing tables if they don't exist
        if (!Schema::hasTable('forum_posts')) {
            Schema::create('forum_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->constrained('forum_threads')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->text('content');
                $table->integer('upvotes')->default(0);
                $table->integer('downvotes')->default(0);
                $table->json('mentions')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->onDelete('cascade');
                $table->timestamps();
                
                $table->index(['thread_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('forum_votes')) {
            Schema::create('forum_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->morphs('votable'); // Can vote on threads or posts
                $table->enum('type', ['upvote', 'downvote']);
                $table->timestamps();
                
                $table->unique(['user_id', 'votable_id', 'votable_type']);
            });
        }

        if (!Schema::hasTable('news_comments')) {
            Schema::create('news_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('news_id')->constrained('news')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->text('content');
                $table->integer('upvotes')->default(0);
                $table->integer('downvotes')->default(0);
                $table->json('mentions')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('news_comments')->onDelete('cascade');
                $table->timestamps();
                
                $table->index(['news_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('event_teams')) {
            Schema::create('event_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
                $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
                $table->integer('seed')->nullable();
                $table->enum('status', ['registered', 'confirmed', 'eliminated', 'withdrawn'])->default('registered');
                $table->timestamps();
                
                $table->unique(['event_id', 'team_id']);
            });
        }

        // Add missing columns to matches table
        if (Schema::hasTable('matches')) {
            Schema::table('matches', function (Blueprint $table) {
                if (!Schema::hasColumn('matches', 'bracket_type')) {
                    $table->enum('bracket_type', ['main', 'upper', 'lower', 'grand_final', 'round_robin', 'swiss'])->default('main')->after('event_id');
                }
                if (!Schema::hasColumn('matches', 'bracket_position')) {
                    $table->integer('bracket_position')->default(1)->after('bracket_type');
                }
                if (!Schema::hasColumn('matches', 'completed_at')) {
                    $table->datetime('completed_at')->nullable()->after('maps_data');
                }
            });
        }
    }

    public function down()
    {
        // Revert changes - in reverse order
        if (Schema::hasTable('matches')) {
            Schema::table('matches', function (Blueprint $table) {
                if (Schema::hasColumn('matches', 'completed_at')) {
                    $table->dropColumn('completed_at');
                }
                if (Schema::hasColumn('matches', 'bracket_position')) {
                    $table->dropColumn('bracket_position');
                }
                if (Schema::hasColumn('matches', 'bracket_type')) {
                    $table->dropColumn('bracket_type');
                }
            });
        }

        Schema::dropIfExists('event_teams');
        Schema::dropIfExists('news_comments');
        Schema::dropIfExists('forum_votes');
        Schema::dropIfExists('forum_posts');

        if (Schema::hasTable('marvel_rivals_heroes')) {
            Schema::table('marvel_rivals_heroes', function (Blueprint $table) {
                $table->dropColumn([
                    'season_added', 'is_new', 'release_date', 'difficulty',
                    'usage_rate', 'win_rate', 'pick_rate', 'ban_rate',
                    'lore', 'voice_actor', 'height', 'universe'
                ]);
            });
        }

        if (Schema::hasTable('players')) {
            Schema::table('players', function (Blueprint $table) {
                $table->dropColumn('peak_rating');
            });
        }

        // Revert enum changes
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE events MODIFY COLUMN type ENUM('International', 'Regional', 'Qualifier', 'Community') NOT NULL DEFAULT 'Tournament'");
            DB::statement("ALTER TABLE players MODIFY COLUMN role ENUM('Duelist', 'Tank', 'Support', 'Controller') NOT NULL");
        }
    }
};