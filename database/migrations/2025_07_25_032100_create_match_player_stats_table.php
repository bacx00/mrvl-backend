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
        if (!Schema::hasTable('match_player_stats')) {
            Schema::create('match_player_stats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('player_id');
                $table->unsignedBigInteger('team_id');
                $table->string('hero');
                $table->integer('eliminations')->default(0);
                $table->integer('assists')->default(0);
                $table->integer('deaths')->default(0);
                $table->integer('damage_dealt')->default(0);
                $table->integer('damage_taken')->default(0);
                $table->integer('healing_done')->default(0);
                $table->integer('healing_received')->default(0);
                $table->integer('damage_blocked')->default(0);
                $table->integer('ultimates_used')->default(0);
                $table->integer('time_played')->default(0);
                $table->integer('objective_time')->default(0);
                $table->decimal('kda_ratio', 5, 2)->default(0);
                $table->integer('mvp_score')->default(0);
                $table->boolean('is_mvp')->default(false);
                $table->timestamps();
                
                $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
                $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
                
                $table->index(['match_id', 'player_id']);
                $table->index(['player_id', 'hero']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_player_stats');
    }
};