<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team1_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('team2_id')->constrained('teams')->cascadeOnDelete();
            $table->integer('score1')->default(0);
            $table->integer('score2')->default(0);
            $table->dateTime('date');
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('matches');
    }
};