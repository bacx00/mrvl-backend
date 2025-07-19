<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Create marvel_rivals_maps table if it doesn't exist
        if (!Schema::hasTable('marvel_rivals_maps')) {
            Schema::create('marvel_rivals_maps', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->enum('game_mode', ['Domination', 'Convoy', 'Convergence']);
                $table->boolean('is_competitive')->default(false);
                $table->string('season')->nullable();
                $table->enum('status', ['active', 'removed'])->default('active');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create game_modes table if it doesn't exist
        if (!Schema::hasTable('game_modes')) {
            Schema::create('game_modes', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('format');
                $table->integer('preparation_time');
                $table->text('description');
                $table->json('rules');
                $table->timestamps();
            });
        }

        // Create tournament_formats table if it doesn't exist
        if (!Schema::hasTable('tournament_formats')) {
            Schema::create('tournament_formats', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('description');
                $table->integer('maps_to_win');
                $table->integer('max_maps');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('marvel_rivals_maps');
        Schema::dropIfExists('game_modes');
        Schema::dropIfExists('tournament_formats');
    }
};