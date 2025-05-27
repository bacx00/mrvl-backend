<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username');
            $table->string('real_name')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('role', ['Duelist', 'Tank', 'Support', 'Controller']);
            $table->string('main_hero');
            $table->json('alt_heroes')->nullable();
            $table->string('region', 10);
            $table->string('country');
            $table->string('rank')->nullable();
            $table->float('rating')->default(0);
            $table->integer('age')->nullable();
            $table->string('earnings')->nullable();
            $table->json('social_media')->nullable();
            $table->text('biography')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'rating']);
            $table->unique(['username']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('players');
    }
};
