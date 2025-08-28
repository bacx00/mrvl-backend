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
        Schema::table('match_player_stats', function (Blueprint $table) {
            $table->integer('map_number')->nullable()->after('team_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_player_stats', function (Blueprint $table) {
            $table->dropColumn('map_number');
        });
    }
};
