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
        // Enhance bracket_stages table
        if (!Schema::hasTable('bracket_stages')) {
            Schema::create('bracket_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('name'); // Upper Bracket, Lower Bracket, Swiss Stage, etc.
                $table->enum('type', ['upper_bracket', 'lower_bracket', 'swiss', 'round_robin', 'group_stage', 'third_place', 'grand_final'])->default('upper_bracket');
                $table->integer('stage_order')->default(1);
                $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
                $table->json('settings')->nullable(); // Format-specific settings
                $table->integer('max_teams')->nullable();
                $table->integer('current_round')->default(0);
                $table->integer('total_rounds')->default(0);
                $table->timestamps();
                
                $table->index(['tournament_id', 'type', 'stage_order']);
                $table->index(['event_id', 'type', 'stage_order']);
            });
        } else {
            Schema::table('bracket_stages', function (Blueprint $table) {
                if (!Schema::hasColumn('bracket_stages', 'event_id')) {
                    $table->foreignId('event_id')->nullable()->after('tournament_id')->constrained()->onDelete('cascade');
                }
                if (!Schema::hasColumn('bracket_stages', 'max_teams')) {
                    $table->integer('max_teams')->nullable()->after('settings');
                }
                if (!Schema::hasColumn('bracket_stages', 'current_round')) {
                    $table->integer('current_round')->default(0)->after('max_teams');
                }
                if (!Schema::hasColumn('bracket_stages', 'total_rounds')) {
                    $table->integer('total_rounds')->default(0)->after('current_round');
                }
            });
        }

        // Enhance bracket_matches table
        if (!Schema::hasTable('bracket_matches')) {
            Schema::create('bracket_matches', function (Blueprint $table) {
                $table->id();
                $table->string('match_id')->unique(); // Custom match identifier like "UB1-1", "LB2-3"
                $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('bracket_stage_id')->constrained()->onDelete('cascade');
                
                // Match details
                $table->string('round_name'); // "Upper Bracket Quarterfinals", "Lower Bracket Round 1"
                $table->integer('round_number');
                $table->integer('match_number');
                
                // Teams
                $table->foreignId('team1_id')->nullable()->constrained('teams')->onDelete('set null');
                $table->foreignId('team2_id')->nullable()->constrained('teams')->onDelete('set null');
                $table->string('team1_source')->nullable(); // "Seed #1", "Winner of UB1-1"
                $table->string('team2_source')->nullable(); // "Seed #2", "Loser of UB2-1"
                
                // Scores and results
                $table->integer('team1_score')->default(0);
                $table->integer('team2_score')->default(0);
                $table->foreignId('winner_id')->nullable()->constrained('teams')->onDelete('set null');
                $table->foreignId('loser_id')->nullable()->constrained('teams')->onDelete('set null');
                
                // Match configuration
                $table->enum('best_of', ['1', '3', '5', '7'])->default('3');
                $table->enum('status', ['pending', 'ready', 'live', 'completed', 'forfeit', 'cancelled'])->default('pending');
                
                // Scheduling
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                
                // Progression
                $table->string('winner_advances_to')->nullable(); // Next match ID
                $table->string('loser_advances_to')->nullable(); // Next match ID for double elimination
                
                // Additional data
                $table->json('vods')->nullable(); // Video links
                $table->json('interviews')->nullable(); // Interview links
                $table->text('notes')->nullable();
                $table->boolean('bracket_reset')->default(false); // For grand finals
                
                $table->timestamps();
                
                $table->index(['bracket_stage_id', 'round_number', 'match_number']);
                $table->index(['tournament_id', 'status']);
                $table->index(['event_id', 'status']);
                $table->index(['winner_advances_to']);
                $table->index(['loser_advances_to']);
            });
        } else {
            Schema::table('bracket_matches', function (Blueprint $table) {
                if (!Schema::hasColumn('bracket_matches', 'event_id')) {
                    $table->foreignId('event_id')->nullable()->after('tournament_id')->constrained()->onDelete('cascade');
                }
                if (!Schema::hasColumn('bracket_matches', 'bracket_reset')) {
                    $table->boolean('bracket_reset')->default(false)->after('notes');
                }
            });
        }

        // Enhance bracket_positions table for visual layout
        if (!Schema::hasTable('bracket_positions')) {
            Schema::create('bracket_positions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bracket_match_id')->constrained()->onDelete('cascade');
                $table->foreignId('bracket_stage_id')->constrained()->onDelete('cascade');
                
                // Grid positioning for visual layout
                $table->integer('column_position'); // Column in bracket grid
                $table->integer('row_position'); // Row in bracket grid
                $table->integer('tier'); // Visual tier/level (0 = first round, 1 = second round, etc.)
                
                // Visual settings for rendering
                $table->json('visual_settings')->nullable(); // CSS positioning, colors, etc.
                
                $table->timestamps();
                
                $table->unique(['bracket_stage_id', 'column_position', 'row_position']);
                $table->index(['bracket_stage_id', 'tier']);
            });
        }

        // Create bracket_seedings table for initial tournament seeding
        if (!Schema::hasTable('bracket_seedings')) {
            Schema::create('bracket_seedings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('bracket_stage_id')->constrained()->onDelete('cascade');
                $table->foreignId('team_id')->constrained()->onDelete('cascade');
                
                $table->integer('seed')->unsigned(); // 1, 2, 3, etc.
                $table->string('seeding_method')->default('manual'); // manual, random, rating, previous_results
                $table->json('seeding_data')->nullable(); // Additional seeding information
                $table->timestamp('seeded_at')->nullable();
                
                $table->timestamps();
                
                $table->unique(['bracket_stage_id', 'seed']);
                $table->unique(['bracket_stage_id', 'team_id']);
                $table->index(['tournament_id', 'seed']);
                $table->index(['event_id', 'seed']);
            });
        }

        // Create bracket_games table for individual games within matches
        if (!Schema::hasTable('bracket_games')) {
            Schema::create('bracket_games', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bracket_match_id')->constrained()->onDelete('cascade');
                $table->integer('game_number'); // Game 1, 2, 3 within a match
                
                // Game details
                $table->string('map_name')->nullable();
                $table->enum('map_type', ['control', 'escort', 'hybrid', 'flashpoint', 'push'])->nullable();
                
                // Game results
                $table->integer('team1_score')->default(0);
                $table->integer('team2_score')->default(0);
                $table->foreignId('winner_id')->nullable()->constrained('teams')->onDelete('set null');
                
                // Game timing
                $table->integer('duration_seconds')->nullable(); // Game duration
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                
                // Game data
                $table->json('game_data')->nullable(); // Detailed stats, hero picks, etc.
                $table->string('vod_url')->nullable();
                
                $table->timestamps();
                
                $table->unique(['bracket_match_id', 'game_number']);
                $table->index(['bracket_match_id', 'winner_id']);
            });
        }

        // Create bracket_standings table for final placements
        if (!Schema::hasTable('bracket_standings')) {
            Schema::create('bracket_standings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('team_id')->constrained()->onDelete('cascade');
                
                // Final placement
                $table->integer('final_placement'); // 1st, 2nd, 3rd, etc.
                $table->string('placement_range')->nullable(); // "3rd-4th", "5th-8th"
                $table->decimal('prize_money', 10, 2)->nullable();
                
                // Tournament stats
                $table->integer('total_matches_played')->default(0);
                $table->integer('matches_won')->default(0);
                $table->integer('matches_lost')->default(0);
                $table->integer('games_won')->default(0);
                $table->integer('games_lost')->default(0);
                
                // Swiss system specific
                $table->decimal('swiss_score', 4, 2)->nullable();
                $table->integer('buchholz_score')->nullable(); // Tiebreaker
                
                // Additional data
                $table->json('placement_data')->nullable(); // Additional tournament-specific data
                $table->timestamp('eliminated_at')->nullable();
                
                $table->timestamps();
                
                $table->unique(['tournament_id', 'team_id']);
                $table->unique(['event_id', 'team_id']);
                $table->index(['final_placement']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bracket_standings');
        Schema::dropIfExists('bracket_games');
        Schema::dropIfExists('bracket_seedings');
        Schema::dropIfExists('bracket_positions');
        Schema::dropIfExists('bracket_matches');
        Schema::dropIfExists('bracket_stages');
    }
};