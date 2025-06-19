<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Marvel Heroes Table
        Schema::create('marvel_heroes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('role', ['Duelist', 'Tank', 'Support']);
            $table->text('abilities')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->integer('difficulty')->default(3); // 1-5 scale
            $table->json('stats')->nullable(); // HP, Damage, etc.
            $table->timestamps();
        });

        // Live Match Events Table
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->string('type'); // kill, death, objective, round_end
            $table->string('player_name')->nullable();
            $table->string('hero')->nullable();
            $table->string('victim')->nullable();
            $table->text('description');
            $table->integer('round_number')->default(1);
            $table->timestamp('event_time');
            $table->timestamps();
        });

        // Team Compositions Table
        Schema::create('team_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('match_id')->nullable()->constrained('matches')->onDelete('cascade');
            $table->string('map_name');
            $table->json('heroes'); // Array of hero names
            $table->enum('side', ['attack', 'defense'])->nullable();
            $table->boolean('won')->default(false);
            $table->timestamps();
        });

        // Player Statistics Table
        Schema::create('player_match_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->string('hero_played');
            $table->integer('eliminations')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('damage_dealt')->default(0);
            $table->integer('healing_done')->default(0);
            $table->integer('damage_blocked')->default(0);
            $table->decimal('time_played', 8, 2)->default(0); // minutes
            $table->timestamps();
        });

        // Tournament Brackets Table
        Schema::create('tournament_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->integer('round_number');
            $table->integer('match_number');
            $table->foreignId('team1_id')->nullable()->constrained('teams');
            $table->foreignId('team2_id')->nullable()->constrained('teams');
            $table->foreignId('winner_id')->nullable()->constrained('teams');
            $table->enum('bracket_type', ['upper', 'lower', 'grand_final']);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tournament_brackets');
        Schema::dropIfExists('player_match_stats');
        Schema::dropIfExists('team_compositions');
        Schema::dropIfExists('match_events');
        Schema::dropIfExists('marvel_heroes');
    }
};