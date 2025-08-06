<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type')->default('tournament');
                $table->string('status')->default('upcoming');
                $table->decimal('prize_pool', 15, 2)->default(0);
                $table->datetime('start_date');
                $table->datetime('end_date')->nullable();
                $table->string('region')->nullable();
                $table->string('organizer')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('tier')->default('S');
                $table->string('location')->nullable();
                $table->integer('num_teams')->default(0);
                $table->boolean('is_featured')->default(false);
                $table->json('prize_distribution')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('events');
    }
};