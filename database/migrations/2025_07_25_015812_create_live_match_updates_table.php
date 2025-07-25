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
        Schema::create('live_match_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id');
            $table->string('update_type', 50);
            $table->json('update_data');
            $table->boolean('processed')->default(false);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['match_id', 'processed', 'id']);
            $table->index(['created_at']);
            
            // Foreign key constraint
            $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_match_updates');
    }
};
