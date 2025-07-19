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
        Schema::create('player_hero_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->string('hero_name');
            $table->integer('matches_played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->decimal('win_rate', 5, 2)->default(0);
            
            // Performance metrics (similar to vlr.gg)
            $table->decimal('rating', 4, 2)->default(0); // Rating 2.0 equivalent
            $table->decimal('acs', 5, 1)->default(0); // Average Combat Score
            $table->decimal('kd_ratio', 4, 2)->default(0); // K:D
            $table->decimal('kpr', 4, 2)->default(0); // Kills per Round
            $table->decimal('apr', 4, 2)->default(0); // Assists per Round
            $table->decimal('dpr', 4, 2)->default(0); // Deaths per Round
            $table->decimal('adr', 5, 1)->default(0); // Average Damage per Round
            $table->decimal('ahr', 5, 1)->default(0); // Average Healing per Round
            $table->decimal('kast', 5, 2)->default(0); // Kill, Assist, Survive, Trade %
            $table->decimal('fkpr', 4, 2)->default(0); // First Kill per Round
            $table->decimal('fdpr', 4, 2)->default(0); // First Death per Round
            
            // Totals
            $table->integer('total_kills')->default(0);
            $table->integer('total_deaths')->default(0);
            $table->integer('total_assists')->default(0);
            $table->bigInteger('total_damage')->default(0);
            $table->bigInteger('total_healing')->default(0);
            $table->bigInteger('total_damage_blocked')->default(0);
            $table->integer('total_ultimate_usage')->default(0);
            $table->integer('total_objective_time')->default(0);
            $table->integer('total_rounds_played')->default(0);
            
            // Hero-specific data
            $table->string('hero_role')->nullable(); // Vanguard, Duelist, Strategist
            $table->decimal('usage_rate', 5, 2)->default(0); // % of total matches
            $table->timestamp('last_played')->nullable();
            $table->timestamp('first_played')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['player_id', 'hero_name']);
            $table->index('hero_name');
            $table->index('matches_played');
            $table->index('win_rate');
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_hero_stats');
    }
};