<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('marvel_rivals_heroes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->enum('role', ['Vanguard', 'Duelist', 'Strategist']);
            $table->string('image_url')->nullable();
            $table->string('icon_url')->nullable();
            $table->text('description')->nullable();
            $table->json('abilities')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('marvel_rivals_heroes');
    }
};