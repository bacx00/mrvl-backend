<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Create match_rounds table for BO3/BO5 support
        Schema::create('match_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->integer('round_number')->default(1);
            $table->string('map_name')->nullable();
            $table->enum('game_mode', ['Domination', 'Convoy', 'Convergence', 'Conquest', 'Doom Match'])->default('Domination');
            $table->enum('status', ['upcoming', 'live', 'paused', 'completed', 'cancelled'])->default('upcoming');
            $table->integer('team1_score')->default(0);
            $table->integer('team2_score')->default(0);
            $table->integer('round_duration')->default(0); // seconds
            $table->boolean('overtime_used')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->json('team1_composition')->nullable(); // 6v6 hero compositions
            $table->json('team2_composition')->nullable();
            $table->json('objective_progress')->nullable(); // mode-specific progress
            $table->timestamps();
            
            $table->index(['match_id', 'round_number']);
            $table->index(['status', 'started_at']);
            $table->unique(['match_id', 'round_number']);
        });

        // Create competitive_timers table for real-time management
        Schema::create('competitive_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('round_id')->nullable()->constrained('match_rounds')->onDelete('cascade');
            $table->enum('timer_type', ['preparation', 'match', 'overtime', 'break', 'tactical_pause', 'hero_selection'])->default('match');
            $table->integer('duration_seconds')->default(600); // 10 minutes default
            $table->integer('remaining_seconds')->default(600);
            $table->enum('status', ['running', 'paused', 'completed'])->default('running');
            $table->timestamp('started_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('timer_config')->nullable(); // mode-specific timer settings
            $table->timestamps();
            
            $table->index(['match_id', 'timer_type']);
            $table->index(['status', 'started_at']);
        });

        // Update matches table for competitive features
        Schema::table('matches', function (Blueprint $table) {
            $table->enum('match_format', ['BO1', 'BO3', 'BO5'])->default('BO1')->after('format');
            $table->json('competitive_settings')->nullable()->after('timer_data');
            $table->json('preparation_phase')->nullable()->after('competitive_settings');
            $table->json('overtime_data')->nullable()->after('preparation_phase');
            $table->integer('current_round')->default(1)->after('current_map');
            $table->string('current_mode')->nullable()->after('current_round');
            $table->boolean('series_completed')->default(false)->after('current_mode');
            $table->foreignId('series_winner_id')->nullable()->constrained('teams')->onDelete('set null')->after('series_completed');
            
            // Add index for performance
            $table->index(['status', 'current_round']);
            $table->index(['match_format', 'series_completed']);
        });

        // Update player_match_stats for round-based tracking
        Schema::table('player_match_stats', function (Blueprint $table) {
            $table->foreignId('round_id')->nullable()->constrained('match_rounds')->onDelete('cascade')->after('match_id');
            $table->integer('hero_playtime_seconds')->default(0)->after('hero_played');
            $table->enum('role_played', ['Vanguard', 'Duelist', 'Strategist'])->default('Duelist')->after('hero_playtime_seconds');
            $table->json('hero_switches')->nullable()->after('role_played'); // track hero changes
            $table->integer('final_blows')->default(0)->after('eliminations'); // different from eliminations
            $table->integer('environmental_kills')->default(0)->after('final_blows');
            $table->decimal('accuracy_percentage', 5, 2)->default(0)->after('environmental_kills');
            $table->integer('critical_hits')->default(0)->after('accuracy_percentage');
            $table->integer('team_damage_amplified')->default(0)->after('damage_blocked');
            $table->integer('cc_time_applied')->default(0)->after('team_damage_amplified'); // crowd control
            
            // Drop old unique constraint and create new one with round_id
            $table->dropUnique(['player_id', 'match_id']);
            $table->unique(['player_id', 'match_id', 'round_id']);
            $table->index(['round_id', 'role_played']);
        });

        // Create match_history table for archived data
        Schema::create('match_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('player_id')->nullable()->constrained('players')->onDelete('cascade');
            $table->enum('result', ['win', 'loss', 'draw'])->default('loss');
            $table->json('performance_data')->nullable(); // comprehensive stats
            $table->json('achievements')->nullable(); // match achievements
            $table->decimal('performance_rating', 5, 2)->default(0);
            $table->boolean('mvp')->default(false);
            $table->timestamps();
            
            $table->index(['team_id', 'result']);
            $table->index(['player_id', 'performance_rating']);
            $table->index(['match_id', 'result']);
        });

        // Create live_events table for real-time event streaming
        Schema::create('live_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('round_id')->nullable()->constrained('match_rounds')->onDelete('cascade');
            $table->enum('event_type', ['elimination', 'death', 'assist', 'ultimate', 'objective', 'hero_switch', 'timeout'])->default('elimination');
            $table->foreignId('player_id')->nullable()->constrained('players')->onDelete('cascade');
            $table->foreignId('target_player_id')->nullable()->constrained('players')->onDelete('cascade');
            $table->string('hero_involved')->nullable();
            $table->json('event_data')->nullable(); // detailed event information
            $table->timestamp('event_timestamp')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('match_time_seconds')->default(0); // time in match when event occurred
            $table->timestamps();
            
            $table->index(['match_id', 'event_timestamp']);
            $table->index(['event_type', 'player_id']);
            $table->index(['round_id', 'event_timestamp']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('live_events');
        Schema::dropIfExists('match_history');
        
        Schema::table('player_match_stats', function (Blueprint $table) {
            $table->dropForeign(['round_id']);
            $table->dropUnique(['player_id', 'match_id', 'round_id']);
            $table->dropColumn([
                'round_id', 'hero_playtime_seconds', 'role_played', 'hero_switches',
                'final_blows', 'environmental_kills', 'accuracy_percentage', 'critical_hits',
                'team_damage_amplified', 'cc_time_applied'
            ]);
            $table->unique(['player_id', 'match_id']);
        });
        
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['series_winner_id']);
            $table->dropColumn([
                'match_format', 'competitive_settings', 'preparation_phase', 'overtime_data',
                'current_round', 'current_mode', 'series_completed', 'series_winner_id'
            ]);
        });
        
        Schema::dropIfExists('competitive_timers');
        Schema::dropIfExists('match_rounds');
    }
};