<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('player_match_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->integer('eliminations')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('damage')->default(0);
            $table->integer('healing')->default(0);
            $table->integer('damage_blocked')->default(0);
            $table->integer('ultimate_usage')->default(0);
            $table->integer('objective_time')->default(0);
            $table->string('hero_played')->nullable();
            $table->string('current_map')->nullable();
            $table->timestamps();
            
            $table->unique(['player_id', 'match_id']);
            $table->index(['match_id', 'player_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_match_stats');
    }
};
