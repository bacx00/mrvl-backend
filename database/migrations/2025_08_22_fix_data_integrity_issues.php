<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Fix invalid player roles
        DB::table('players')
            ->where('role', 'DPS')
            ->update(['role' => 'Duelist']);

        // Set default main_hero for players missing it
        DB::table('players')
            ->where(function($query) {
                $query->whereNull('main_hero')
                      ->orWhere('main_hero', '');
            })
            ->update(['main_hero' => 'Spider-Man']);

        // Fix inconsistent match statistics (where wins + losses != total_matches)
        $inconsistentPlayers = DB::table('players')
            ->whereNotNull('wins')
            ->whereNotNull('losses')
            ->whereNotNull('total_matches')
            ->whereRaw('(wins + losses) != total_matches')
            ->get();

        foreach ($inconsistentPlayers as $player) {
            $correctTotal = $player->wins + $player->losses;
            DB::table('players')
                ->where('id', $player->id)
                ->update(['total_matches' => $correctTotal]);
        }

        // Add indexes for frequently queried fields if they don't exist
        if (!Schema::hasIndex('players', 'idx_players_jersey_number')) {
            Schema::table('players', function (Blueprint $table) {
                $table->index('jersey_number', 'idx_players_jersey_number');
            });
        }

        if (!Schema::hasIndex('players', 'idx_players_wins_losses')) {
            Schema::table('players', function (Blueprint $table) {
                $table->index(['wins', 'losses'], 'idx_players_wins_losses');
            });
        }

        if (!Schema::hasIndex('players', 'idx_players_kda')) {
            Schema::table('players', function (Blueprint $table) {
                $table->index('kda', 'idx_players_kda');
            });
        }
    }

    public function down()
    {
        // Reverse the role fixes
        DB::table('players')
            ->where('role', 'Duelist')
            ->whereIn('username', ['shroud', 'ninja', 'caps'])
            ->update(['role' => 'DPS']);

        // Drop indexes
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex('idx_players_jersey_number');
            $table->dropIndex('idx_players_wins_losses');
            $table->dropIndex('idx_players_kda');
        });
    }
};