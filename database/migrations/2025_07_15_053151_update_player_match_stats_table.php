<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('player_match_stats', function (Blueprint $table) {
            // Add missing columns that are referenced in the controller
            if (!Schema::hasColumn('player_match_stats', 'team_id')) {
                $table->foreignId('team_id')->nullable()->constrained('teams')->after('player_id');
            }
            if (!Schema::hasColumn('player_match_stats', 'performance_rating')) {
                $table->decimal('performance_rating', 4, 2)->nullable()->after('hero_switches');
            }
            if (!Schema::hasColumn('player_match_stats', 'damage_taken')) {
                $table->integer('damage_taken')->default(0)->after('damage');
            }
            if (!Schema::hasColumn('player_match_stats', 'kda')) {
                $table->decimal('kda', 5, 2)->nullable()->after('assists');
            }
            if (!Schema::hasColumn('player_match_stats', 'hero_role')) {
                $table->string('hero_role')->nullable()->after('hero_played');
            }
            if (!Schema::hasColumn('player_match_stats', 'time_played_seconds')) {
                $table->integer('time_played_seconds')->default(0)->after('hero_playtime_seconds');
            }
            
            // Add more comprehensive stats
            if (!Schema::hasColumn('player_match_stats', 'solo_kills')) {
                $table->integer('solo_kills')->default(0)->after('final_blows');
            }
            if (!Schema::hasColumn('player_match_stats', 'best_killstreak')) {
                $table->integer('best_killstreak')->default(0)->after('solo_kills');
            }
            if (!Schema::hasColumn('player_match_stats', 'ultimates_earned')) {
                $table->integer('ultimates_earned')->default(0)->after('ultimate_usage');
            }
            if (!Schema::hasColumn('player_match_stats', 'ultimates_used')) {
                $table->integer('ultimates_used')->default(0)->after('ultimates_earned');
            }
            if (!Schema::hasColumn('player_match_stats', 'ultimate_eliminations')) {
                $table->integer('ultimate_eliminations')->default(0)->after('ultimates_used');
            }
            if (!Schema::hasColumn('player_match_stats', 'shots_fired')) {
                $table->integer('shots_fired')->default(0)->after('accuracy_percentage');
            }
            if (!Schema::hasColumn('player_match_stats', 'shots_hit')) {
                $table->integer('shots_hit')->default(0)->after('shots_fired');
            }
            if (!Schema::hasColumn('player_match_stats', 'melee_final_blows')) {
                $table->integer('melee_final_blows')->default(0)->after('environmental_kills');
            }
            if (!Schema::hasColumn('player_match_stats', 'hero_specific_stats')) {
                $table->json('hero_specific_stats')->nullable()->after('current_map');
            }
            if (!Schema::hasColumn('player_match_stats', 'player_of_the_match')) {
                $table->boolean('player_of_the_match')->default(false)->after('performance_rating');
            }
            if (!Schema::hasColumn('player_match_stats', 'player_of_the_map')) {
                $table->boolean('player_of_the_map')->default(false)->after('player_of_the_match');
            }
            
            // Add indexes for better performance
            $table->index(['player_id', 'hero_played']);
            $table->index(['team_id', 'match_id']);
            $table->index(['match_id', 'performance_rating']);
        });
    }

    public function down()
    {
        Schema::table('player_match_stats', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn([
                'team_id', 'performance_rating', 'damage_taken', 'kda', 'hero_role',
                'time_played_seconds', 'solo_kills', 'best_killstreak', 'ultimates_earned',
                'ultimates_used', 'ultimate_eliminations', 'shots_fired', 'shots_hit',
                'melee_final_blows', 'hero_specific_stats', 'player_of_the_match', 'player_of_the_map'
            ]);
        });
    }
};