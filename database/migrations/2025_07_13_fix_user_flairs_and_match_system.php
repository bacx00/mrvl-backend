<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add missing columns to users table for profile pictures and flairs
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_picture_type')) {
                $table->enum('profile_picture_type', ['custom', 'hero'])->default('custom')->after('avatar');
            }
            if (!Schema::hasColumn('users', 'use_hero_as_avatar')) {
                $table->boolean('use_hero_as_avatar')->default(false)->after('profile_picture_type');
            }
            if (!Schema::hasColumn('users', 'display_flairs')) {
                $table->boolean('display_flairs')->default(true)->after('show_team_flair');
            }
        });

        // Add missing columns to matches table for live scoring
        Schema::table('matches', function (Blueprint $table) {
            if (!Schema::hasColumn('matches', 'maps_data')) {
                $table->json('maps_data')->nullable()->after('current_map');
            }
            if (!Schema::hasColumn('matches', 'live_data')) {
                $table->json('live_data')->nullable()->after('maps_data');
            }
            if (!Schema::hasColumn('matches', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('matches', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('matches', 'winner_id')) {
                $table->unsignedBigInteger('winner_id')->nullable()->after('team2_score');
            }
            if (!Schema::hasColumn('matches', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('stream_url');
            }
        });

        // Create match_player_stats table if not exists
        if (!Schema::hasTable('match_player_stats')) {
            Schema::create('match_player_stats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('player_id');
                $table->integer('map_number')->default(1);
                $table->string('hero_played')->nullable();
                $table->integer('kills')->default(0);
                $table->integer('deaths')->default(0);
                $table->integer('assists')->default(0);
                $table->integer('damage_dealt')->default(0);
                $table->integer('healing_done')->default(0);
                $table->integer('damage_blocked')->default(0);
                $table->integer('ultimates_used')->default(0);
                $table->integer('final_blows')->default(0);
                $table->integer('objective_time')->default(0);
                $table->timestamps();
                
                $table->unique(['match_id', 'player_id', 'map_number']);
                $table->index('match_id');
                $table->index('player_id');
            });
        }

        // Create match_events table if not exists
        if (!Schema::hasTable('match_events')) {
            Schema::create('match_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->string('event_type');
                $table->text('description');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('player_id')->nullable();
                $table->string('timestamp');
                $table->timestamps();
                
                $table->index('match_id');
            });
        }

        // Create match_comments table if not exists
        if (!Schema::hasTable('match_comments')) {
            Schema::create('match_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->text('content');
                $table->json('mentions')->nullable();
                $table->timestamps();
                
                $table->index('match_id');
                $table->index('user_id');
                $table->index('parent_id');
            });
        }

        // Create comment_votes table if not exists
        if (!Schema::hasTable('comment_votes')) {
            Schema::create('comment_votes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('comment_id');
                $table->string('comment_type'); // 'news', 'match', 'forum'
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                $table->unique(['user_id', 'comment_id', 'comment_type']);
                $table->index(['comment_id', 'comment_type']);
            });
        }

        // Add country_flag to teams table
        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'country_flag')) {
                $table->string('country_flag')->nullable()->after('region');
            }
        });

        // Add country_flag to players table
        Schema::table('players', function (Blueprint $table) {
            if (!Schema::hasColumn('players', 'country_flag')) {
                $table->string('country_flag')->nullable()->after('country');
            }
        });

        // Create reports table if not exists
        if (!Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reporter_id');
                $table->string('reportable_type');
                $table->unsignedBigInteger('reportable_id');
                $table->text('reason');
                $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                
                $table->index(['reportable_type', 'reportable_id']);
                $table->index('status');
            });
        }

        // Create moderation_logs table if not exists
        if (!Schema::hasTable('moderation_logs')) {
            Schema::create('moderation_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('moderator_id');
                $table->string('action');
                $table->string('target_type');
                $table->unsignedBigInteger('target_id');
                $table->text('reason')->nullable();
                $table->json('details')->nullable();
                $table->timestamps();
                
                $table->index('moderator_id');
                $table->index(['target_type', 'target_id']);
            });
        }

        // Add missing columns to events table
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'views')) {
                $table->integer('views')->default(0)->after('status');
            }
        });

        // Add missing columns to event_teams table
        Schema::table('event_teams', function (Blueprint $table) {
            if (!Schema::hasColumn('event_teams', 'placement')) {
                $table->integer('placement')->nullable();
            }
            if (!Schema::hasColumn('event_teams', 'prize_money')) {
                $table->decimal('prize_money', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('event_teams', 'points')) {
                $table->integer('points')->default(0);
            }
        });

        // Create marvel_rivals_maps table if not exists
        if (!Schema::hasTable('marvel_rivals_maps')) {
            Schema::create('marvel_rivals_maps', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->string('game_mode');
                $table->string('type');
                $table->string('location')->nullable();
                $table->text('description')->nullable();
                $table->string('image_path')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                
                $table->unique('slug');
                $table->index('game_mode');
            });
        }

        // Insert Marvel Rivals maps data
        $maps = [
            ['name' => 'Asgard: Royal Palace', 'slug' => 'asgard-royal-palace', 'game_mode' => 'Convergence', 'type' => 'Capture Point'],
            ['name' => 'Asgard: Throne Room', 'slug' => 'asgard-throne-room', 'game_mode' => 'Domination', 'type' => 'King of the Hill'],
            ['name' => 'Asgard: Yggdrasill Path', 'slug' => 'asgard-yggdrasill-path', 'game_mode' => 'Convoy', 'type' => 'Payload'],
            ['name' => 'Tokyo 2099: Shinbuya', 'slug' => 'tokyo-2099-shinbuya', 'game_mode' => 'Convergence', 'type' => 'Capture Point'],
            ['name' => 'Tokyo 2099: Spider Islands', 'slug' => 'tokyo-2099-spider-islands', 'game_mode' => 'Domination', 'type' => 'King of the Hill'],
            ['name' => 'Tokyo 2099: Shin-Shibuya', 'slug' => 'tokyo-2099-shin-shibuya', 'game_mode' => 'Convoy', 'type' => 'Payload'],
            ['name' => 'Hydra Base: Hell\'s Heaven', 'slug' => 'hydra-base-hells-heaven', 'game_mode' => 'Convergence', 'type' => 'Capture Point'],
            ['name' => 'Hydra Base: Charteris Base', 'slug' => 'hydra-base-charteris-base', 'game_mode' => 'Domination', 'type' => 'King of the Hill'],
            ['name' => 'Hydra Base: Siberia', 'slug' => 'hydra-base-siberia', 'game_mode' => 'Convoy', 'type' => 'Payload'],
            ['name' => 'Klyntar: Symbiotic Surface', 'slug' => 'klyntar-symbiotic-surface', 'game_mode' => 'Domination', 'type' => 'King of the Hill'],
            ['name' => 'Klyntar: Spore Colony', 'slug' => 'klyntar-spore-colony', 'game_mode' => 'Convoy', 'type' => 'Payload'],
            ['name' => 'Midtown Manhattan', 'slug' => 'midtown-manhattan', 'game_mode' => 'Convoy', 'type' => 'Payload'],
            ['name' => 'Helicarrier: Command', 'slug' => 'helicarrier-command', 'game_mode' => 'Domination', 'type' => 'King of the Hill'],
            ['name' => 'Sanctum Sanctorum', 'slug' => 'sanctum-sanctorum', 'game_mode' => 'Convergence', 'type' => 'Capture Point'],
            ['name' => 'Wakanda: Birnin T\'Challa', 'slug' => 'wakanda-birnin-tchalla', 'game_mode' => 'Convoy', 'type' => 'Payload']
        ];

        foreach ($maps as $map) {
            DB::table('marvel_rivals_maps')->updateOrInsert(
                ['slug' => $map['slug']],
                array_merge($map, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down()
    {
        // Remove added columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_picture_type', 'use_hero_as_avatar', 'display_flairs']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['maps_data', 'live_data', 'started_at', 'completed_at', 'winner_id', 'created_by']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('country_flag');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('country_flag');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('views');
        });

        Schema::table('event_teams', function (Blueprint $table) {
            $table->dropColumn(['placement', 'prize_money', 'points']);
        });

        // Drop created tables
        Schema::dropIfExists('match_player_stats');
        Schema::dropIfExists('match_events');
        Schema::dropIfExists('match_comments');
        Schema::dropIfExists('comment_votes');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('marvel_rivals_maps');
    }
};