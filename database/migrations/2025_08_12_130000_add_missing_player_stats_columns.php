<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('players', function (Blueprint $table) {
            // Add missing stats columns if they don't exist
            if (!Schema::hasColumn('players', 'wins')) {
                $table->integer('wins')->default(0)->after('rating');
            }
            if (!Schema::hasColumn('players', 'losses')) {
                $table->integer('losses')->default(0)->after('wins');
            }
            if (!Schema::hasColumn('players', 'kda')) {
                $table->decimal('kda', 4, 2)->default(0)->after('losses');
            }
            if (!Schema::hasColumn('players', 'nationality')) {
                $table->string('nationality')->nullable()->after('country');
            }
            
            // Ensure earnings is decimal type
            if (!Schema::hasColumn('players', 'earnings')) {
                $table->decimal('earnings', 15, 2)->default(0)->after('age');
            }
            
            // Add indexes for performance
            if (!Schema::hasIndex('players', 'idx_players_rating')) {
                $table->index('rating', 'idx_players_rating');
            }
            if (!Schema::hasIndex('players', 'idx_players_team_id')) {
                $table->index('team_id', 'idx_players_team_id');
            }
        });
    }

    public function down()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['wins', 'losses', 'kda', 'nationality']);
            $table->dropIndex('idx_players_rating');
            $table->dropIndex('idx_players_team_id');
        });
    }
};