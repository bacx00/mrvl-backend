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
        // Bracket generation history
        if (!Schema::hasTable('bracket_history')) {
            Schema::create('bracket_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->string('seeding_method');
            $table->integer('teams_count');
            $table->string('format');
            $table->json('bracket_data');
            $table->timestamps();
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['event_id', 'created_at']);
            });
        }

        // Event standings/leaderboard - skip if already exists
        if (!Schema::hasTable('event_standings')) {
            Schema::create('event_standings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_id');
            $table->integer('position');
            $table->integer('matches_played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('maps_won')->default(0);
            $table->integer('maps_lost')->default(0);
            $table->integer('rounds_won')->default(0);
            $table->integer('rounds_lost')->default(0);
            $table->integer('points')->default(0);
            $table->decimal('map_differential', 8, 2)->default(0);
            $table->decimal('round_differential', 8, 2)->default(0);
            $table->string('form')->nullable(); // Last 5 matches (W/L/D)
            $table->json('additional_stats')->nullable();
            $table->timestamps();
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->unique(['event_id', 'team_id']);
            $table->index(['event_id', 'position']);
            });
        }

        // Event activity logs
        if (!Schema::hasTable('event_logs')) {
            Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['event_id', 'created_at']);
            });
        }

        // Add missing columns to matches table if they don't exist
        Schema::table('matches', function (Blueprint $table) {
            if (!Schema::hasColumn('matches', 'is_third_place')) {
                $table->boolean('is_third_place')->default(false);
            }
            if (!Schema::hasColumn('matches', 'round')) {
                $table->string('round')->nullable();
            }
            if (!Schema::hasColumn('matches', 'round_match_number')) {
                $table->integer('round_match_number')->nullable();
            }
            if (!Schema::hasColumn('matches', 'next_match_id')) {
                $table->unsignedBigInteger('next_match_id')->nullable();
                $table->foreign('next_match_id')->references('id')->on('matches')->onDelete('set null');
            }
        });
        
        // Add index if it doesn't exist
        if (!Schema::hasIndex('matches', 'matches_event_id_round_index')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->index(['event_id', 'round']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_logs');
        Schema::dropIfExists('event_standings');
        Schema::dropIfExists('bracket_history');
        
        if (Schema::hasColumn('matches', 'is_third_place')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->dropColumn(['is_third_place', 'round', 'round_match_number', 'next_match_id']);
            });
        }
    }
};