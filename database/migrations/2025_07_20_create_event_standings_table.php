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
        Schema::create('event_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->integer('position')->default(0);
            $table->integer('points')->default(0);
            $table->integer('matches_played')->default(0);
            $table->integer('matches_won')->default(0);
            $table->integer('matches_lost')->default(0);
            $table->integer('maps_won')->default(0);
            $table->integer('maps_lost')->default(0);
            $table->integer('rounds_won')->default(0);
            $table->integer('rounds_lost')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('map_differential')->default(0);
            $table->decimal('win_rate', 5, 2)->default(0);
            $table->enum('status', ['active', 'eliminated', 'qualified'])->default('active');
            $table->decimal('prize_won', 10, 2)->nullable();
            $table->timestamps();
            
            $table->unique(['event_id', 'team_id']);
            $table->index(['event_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_standings');
    }
};