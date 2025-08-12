<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('players', function (Blueprint $table) {
            // Make main_hero nullable or set a default
            $table->string('main_hero')->nullable()->default('Spider-Man')->change();
        });
    }

    public function down()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('main_hero')->nullable(false)->change();
        });
    }
};