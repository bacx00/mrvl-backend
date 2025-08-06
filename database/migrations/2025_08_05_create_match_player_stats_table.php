<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('match_player_stats')) {
            Schema::create('match_player_stats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
                $table->foreignId('map_id')->nullable()->constrained('match_maps')->onDelete('cascade');
                $table->foreignId('player_id')->constrained('players');
                $table->foreignId('team_id')->constrained('teams');
                $table->string('hero');
                $table->integer('eliminations')->default(0);
                $table->integer('deaths')->default(0);
                $table->integer('assists')->default(0);
                $table->integer('damage')->default(0);
                $table->integer('healing')->default(0);
                $table->decimal('kda', 5, 2)->default(0);
                $table->integer('combat_score')->default(0);
                $table->decimal('performance_rating', 4, 2)->default(0);
                $table->integer('first_kills')->default(0);
                $table->integer('first_deaths')->default(0);
                $table->decimal('kast_percentage', 5, 2)->default(0);
                $table->decimal('damage_per_round', 8, 2)->default(0);
                $table->decimal('eliminations_per_round', 5, 2)->default(0);
                $table->decimal('assists_per_round', 5, 2)->default(0);
                $table->integer('multi_kills')->default(0);
                $table->integer('clutches')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('match_player_stats');
    }
};