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
        // Player rating history table
        Schema::create('player_rating_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->integer('old_rating');
            $table->integer('new_rating');
            $table->integer('rating_change');
            $table->unsignedBigInteger('match_id')->nullable();
            $table->string('reason')->default('match_result');
            $table->json('additional_data')->nullable(); // For storing extra context
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['player_id', 'created_at'], 'idx_player_date');
            $table->index('match_id', 'idx_match');
            $table->index('rating_change', 'idx_change');
            
            // Foreign keys
            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
            $table->foreign('match_id')->references('id')->on('matches')->onDelete('set null');
        });

        // Team rating history table
        Schema::create('team_rating_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->integer('old_rating');
            $table->integer('new_rating');
            $table->integer('rating_change');
            $table->unsignedBigInteger('match_id')->nullable();
            $table->string('reason')->default('match_result');
            $table->json('additional_data')->nullable(); // For storing extra context
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['team_id', 'created_at'], 'idx_team_date');
            $table->index('match_id', 'idx_match');
            $table->index('rating_change', 'idx_change');
            
            // Foreign keys
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('match_id')->references('id')->on('matches')->onDelete('set null');
        });

        // Rating snapshots for daily/weekly leaderboards
        Schema::create('rating_snapshots', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['player', 'team']);
            $table->unsignedBigInteger('entity_id'); // player_id or team_id
            $table->integer('rating');
            $table->integer('global_rank');
            $table->integer('region_rank')->nullable();
            $table->string('region', 10);
            $table->enum('period', ['daily', 'weekly', 'monthly']);
            $table->date('snapshot_date');
            $table->timestamps();
            
            // Indexes for leaderboard queries
            $table->index(['type', 'period', 'snapshot_date'], 'idx_type_period_date');
            $table->index(['entity_id', 'type', 'period'], 'idx_entity_type_period');
            $table->index(['rating', 'type', 'period'], 'idx_rating_type_period');
            
            // Unique constraint to prevent duplicate snapshots
            $table->unique(['type', 'entity_id', 'period', 'snapshot_date'], 'unique_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_snapshots');
        Schema::dropIfExists('team_rating_history');
        Schema::dropIfExists('player_rating_history');
    }
};