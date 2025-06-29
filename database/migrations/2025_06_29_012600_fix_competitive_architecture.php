<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add missing columns to matches table
        if (!Schema::hasColumn('matches', 'winner_team_id')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->unsignedBigInteger('winner_team_id')->nullable()->after('team2_score');
                $table->foreign('winner_team_id')->references('id')->on('teams')->onDelete('set null');
            });
        }

        // Create match_rounds table if it doesn't exist
        if (!Schema::hasTable('match_rounds')) {
            Schema::create('match_rounds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->integer('round_number');
                $table->string('map_name');
                $table->string('game_mode');
                $table->enum('status', ['upcoming', 'live', 'completed'])->default('upcoming');
                $table->integer('team1_score')->default(0);
                $table->integer('team2_score')->default(0);
                $table->unsignedBigInteger('winner_team_id')->nullable();
                $table->integer('round_duration')->default(0);
                $table->boolean('overtime_used')->default(false);
                $table->json('team1_composition')->nullable();
                $table->json('team2_composition')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
                $table->foreign('winner_team_id')->references('id')->on('teams')->onDelete('set null');
            });
        }

        // Create competitive_timers table if it doesn't exist
        if (!Schema::hasTable('competitive_timers')) {
            Schema::create('competitive_timers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('round_id')->nullable();
                $table->string('timer_type'); // preparation, match, overtime, pause
                $table->integer('duration_seconds');
                $table->integer('remaining_seconds');
                $table->enum('status', ['running', 'paused', 'completed'])->default('running');
                $table->json('timer_config')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
                $table->foreign('round_id')->references('id')->on('match_rounds')->onDelete('cascade');
            });
        }

        // Create match_history table if it doesn't exist
        if (!Schema::hasTable('match_history')) {
            Schema::create('match_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('player_id')->nullable();
                $table->enum('result', ['win', 'loss', 'draw']);
                $table->json('performance_data')->nullable();
                $table->decimal('performance_rating', 8, 2)->nullable();
                $table->boolean('mvp')->default(false);
                $table->timestamps();

                $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
                $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
            });
        }

        // Create live_events table if it doesn't exist
        if (!Schema::hasTable('live_events')) {
            Schema::create('live_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('round_id')->nullable();
                $table->string('event_type'); // elimination, death, objective, ultimate
                $table->unsignedBigInteger('player_id')->nullable();
                $table->string('hero_involved')->nullable();
                $table->json('event_data')->nullable();
                $table->timestamp('event_timestamp');
                $table->integer('match_time_seconds');
                $table->timestamps();

                $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
                $table->foreign('round_id')->references('id')->on('match_rounds')->onDelete('cascade');
                $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
            });
        }

        // Update player_match_stats table if needed
        if (Schema::hasTable('player_match_stats')) {
            Schema::table('player_match_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('player_match_stats', 'round_id')) {
                    $table->unsignedBigInteger('round_id')->nullable()->after('match_id');
                    $table->foreign('round_id')->references('id')->on('match_rounds')->onDelete('cascade');
                }
                
                if (!Schema::hasColumn('player_match_stats', 'hero_playtime_seconds')) {
                    $table->integer('hero_playtime_seconds')->default(0)->after('hero_played');
                }
                
                if (!Schema::hasColumn('player_match_stats', 'role_played')) {
                    $table->string('role_played')->nullable()->after('hero_played');
                }
                
                if (!Schema::hasColumn('player_match_stats', 'damage_blocked')) {
                    $table->integer('damage_blocked')->default(0)->after('healing');
                }
                
                if (!Schema::hasColumn('player_match_stats', 'ultimate_usage')) {
                    $table->integer('ultimate_usage')->default(0)->after('damage_blocked');
                }
                
                if (!Schema::hasColumn('player_match_stats', 'final_blows')) {
                    $table->integer('final_blows')->default(0)->after('ultimate_usage');
                }
                
                if (!Schema::hasColumn('player_match_stats', 'environmental_kills')) {
                    $table->integer('environmental_kills')->default(0)->after('final_blows');
                }
                
                if (!Schema::hasColumn('player_match_stats', 'accuracy_percentage')) {
                    $table->decimal('accuracy_percentage', 5, 2)->default(0)->after('environmental_kills');
                }
                
                if (!Schema::hasColumn('player_match_stats', 'critical_hits')) {
                    $table->integer('critical_hits')->default(0)->after('accuracy_percentage');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_events');
        Schema::dropIfExists('match_history');
        Schema::dropIfExists('competitive_timers');
        Schema::dropIfExists('match_rounds');
        
        if (Schema::hasColumn('matches', 'winner_team_id')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->dropForeign(['winner_team_id']);
                $table->dropColumn('winner_team_id');
            });
        }
    }
};