<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('match_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->integer('kills')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('assists')->default(0);
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('match_player');
    }
};