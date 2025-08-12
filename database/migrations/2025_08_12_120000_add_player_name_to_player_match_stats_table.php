<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('player_match_stats', function (Blueprint $table) {
            // Add player_name column after player_id for live scoring compatibility
            if (!Schema::hasColumn('player_match_stats', 'player_name')) {
                $table->string('player_name')->nullable()->after('player_id');
            }
        });
    }

    public function down()
    {
        Schema::table('player_match_stats', function (Blueprint $table) {
            $table->dropColumn('player_name');
        });
    }
};