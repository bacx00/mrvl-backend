<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('matches')) {
            Schema::create('matches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team1_id')->constrained('teams');
                $table->foreignId('team2_id')->constrained('teams');
                $table->foreignId('event_id')->nullable()->constrained('tournaments');
                $table->integer('team1_score')->default(0);
                $table->integer('team2_score')->default(0);
                $table->integer('series_score_team1')->default(0);
                $table->integer('series_score_team2')->default(0);
                $table->foreignId('winner_id')->nullable()->constrained('teams');
                $table->string('status')->default('upcoming');
                $table->string('format')->default('BO3');
                $table->datetime('scheduled_at')->nullable();
                $table->datetime('started_at')->nullable();
                $table->datetime('ended_at')->nullable();
                $table->integer('viewers')->default(0);
                $table->string('stream_url')->nullable();
                $table->text('vod_url')->nullable();
                $table->string('stage')->nullable();
                $table->string('round')->nullable();
                $table->boolean('is_live')->default(false);
                $table->json('maps_played')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('matches');
    }
};