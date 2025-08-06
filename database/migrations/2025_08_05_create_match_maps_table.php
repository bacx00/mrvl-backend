<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('match_maps')) {
            Schema::create('match_maps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
                $table->integer('map_number');
                $table->string('map_name');
                $table->integer('team1_score')->default(0);
                $table->integer('team2_score')->default(0);
                $table->foreignId('winner_id')->nullable()->constrained('teams');
                $table->string('status')->default('upcoming');
                $table->datetime('started_at')->nullable();
                $table->datetime('ended_at')->nullable();
                $table->integer('duration')->nullable();
                $table->json('round_scores')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('match_maps');
    }
};