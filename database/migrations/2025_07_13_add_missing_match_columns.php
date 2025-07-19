<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('matches', function (Blueprint $table) {
            // Only add columns that don't exist yet
            if (!Schema::hasColumn('matches', 'player_stats')) {
                $table->json('player_stats')->nullable()->after('hero_data');
            }
            if (!Schema::hasColumn('matches', 'actual_start_time')) {
                $table->timestamp('actual_start_time')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('matches', 'actual_end_time')) {
                $table->timestamp('actual_end_time')->nullable()->after('actual_start_time');
            }
            if (!Schema::hasColumn('matches', 'round')) {
                $table->string('round')->nullable()->after('event_id');
            }
            if (!Schema::hasColumn('matches', 'series_score_team1')) {
                $table->integer('series_score_team1')->default(0)->after('team2_score');
            }
            if (!Schema::hasColumn('matches', 'series_score_team2')) {
                $table->integer('series_score_team2')->default(0)->after('series_score_team1');
            }
            if (!Schema::hasColumn('matches', 'current_map_number')) {
                $table->integer('current_map_number')->default(1)->after('current_map');
            }
            if (!Schema::hasColumn('matches', 'current_game_mode')) {
                $table->string('current_game_mode')->nullable()->after('current_map_number');
            }
            if (!Schema::hasColumn('matches', 'match_timer')) {
                $table->json('match_timer')->nullable()->after('current_game_mode');
            }
            if (!Schema::hasColumn('matches', 'overtime')) {
                $table->boolean('overtime')->default(false)->after('match_timer');
            }
            if (!Schema::hasColumn('matches', 'allow_past_date')) {
                $table->boolean('allow_past_date')->default(false)->after('created_by');
            }
        });
    }

    public function down()
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'player_stats', 'actual_start_time', 'actual_end_time', 'round',
                'series_score_team1', 'series_score_team2', 'current_map_number',
                'current_game_mode', 'match_timer', 'overtime', 'allow_past_date'
            ]);
        });
    }
};