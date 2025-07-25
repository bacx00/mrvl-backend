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
        Schema::create('match_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            
            // Map Information
            $table->integer('map_number');
            $table->string('map_name');
            $table->string('game_mode'); // Domination, Convoy, Convergence
            $table->string('status', 20)->default('upcoming');
            
            // Scores
            $table->integer('team1_score')->default(0);
            $table->integer('team2_score')->default(0);
            $table->integer('team1_rounds')->default(0); // For Domination
            $table->integer('team2_rounds')->default(0);
            $table->foreignId('winner_id')->nullable()->constrained('teams');
            
            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('overtime')->default(false);
            $table->integer('overtime_duration')->nullable();
            
            // Map-specific data
            $table->json('checkpoints_reached')->nullable(); // For Convoy
            $table->json('objectives_captured')->nullable(); // For Domination
            $table->json('additional_stats')->nullable(); // Any other map-specific data
            
            $table->timestamps();
            
            // Indexes
            $table->index(['match_id', 'map_number']);
            $table->index('winner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_maps');
    }
};
