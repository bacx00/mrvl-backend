<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check and create missing essential tables
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('user');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('abbreviation', 10)->nullable();
                $table->string('region');
                $table->string('logo_url')->nullable();
                $table->text('description')->nullable();
                $table->decimal('earnings', 15, 2)->default(0);
                $table->integer('ranking')->default(0);
                $table->string('tier')->default('Unranked');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tournaments')) {
            Schema::create('tournaments', function (Blueprint $table) {
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
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('news')) {
            Schema::create('news', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->string('author')->default('Admin');
                $table->string('category')->default('news');
                $table->string('image_url')->nullable();
                $table->boolean('featured')->default(false);
                $table->boolean('published')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_marvel_rivals_tables');
    }
};
