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
        if (!Schema::hasTable('brackets')) {
            Schema::create('brackets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->onDelete('cascade');
                $table->string('type')->default('main'); // bracket_type in the model
                $table->string('stage')->default('1');
                $table->integer('round')->default(1);
                $table->integer('position')->default(1);
                $table->string('round_name')->nullable();
                $table->foreignId('match_id')->nullable()->constrained('matches')->onDelete('set null');
                $table->json('bracket_data')->nullable();
                $table->timestamps();
                
                // Indexes for performance
                $table->index(['event_id', 'type']);
                $table->index(['event_id', 'round', 'position']);
                $table->index(['type', 'round']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brackets');
    }
};