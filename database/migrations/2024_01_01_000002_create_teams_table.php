<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 10);
            $table->string('logo')->nullable();
            $table->string('region', 10);
            $table->string('country');
            $table->string('flag')->nullable();
            $table->integer('rating')->default(0);
            $table->integer('rank')->default(0);
            $table->float('win_rate')->default(0);
            $table->integer('points')->default(0);
            $table->string('record')->nullable();
            $table->integer('peak')->default(0);
            $table->string('streak')->nullable();
            $table->string('last_match')->nullable();
            $table->string('founded')->nullable();
            $table->string('captain')->nullable();
            $table->string('coach')->nullable();
            $table->string('website')->nullable();
            $table->string('earnings')->nullable();
            $table->json('social_media')->nullable();
            $table->json('achievements')->nullable();
            $table->timestamps();

            $table->index(['region', 'rating']);
            $table->unique(['short_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('teams');
    }
};
