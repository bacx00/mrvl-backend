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
        // Create user_favorite_teams table
        Schema::create('user_favorite_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'team_id']);
        });

        // Create user_favorite_players table
        Schema::create('user_favorite_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'player_id']);
        });

        // Create match_predictions table
        Schema::create('match_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->json('prediction_data'); // Store prediction details as JSON
            $table->string('predicted_winner')->nullable(); // team1, team2, or draw
            $table->integer('predicted_score_team1')->nullable();
            $table->integer('predicted_score_team2')->nullable();
            $table->decimal('confidence', 3, 2)->nullable(); // 0.00 to 1.00
            $table->boolean('is_correct')->nullable(); // null until match is finished
            $table->integer('points_earned')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'match_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_predictions');
        Schema::dropIfExists('user_favorite_players');
        Schema::dropIfExists('user_favorite_teams');
    }
};
