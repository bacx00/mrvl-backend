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
        Schema::create('content_flags', function (Blueprint $table) {
            $table->id();
            $table->string('flaggable_type');
            $table->unsignedBigInteger('flaggable_id');
            $table->unsignedBigInteger('flagger_id');
            $table->enum('flag_type', ['inappropriate', 'spam', 'misleading', 'copyright', 'other']);
            $table->text('reason');
            $table->enum('priority', ['low', 'medium', 'high', 'critical']);
            $table->enum('status', ['pending', 'dismissed', 'upheld', 'escalated'])->default('pending');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            
            $table->index(['flaggable_type', 'flaggable_id']);
            $table->foreign('flagger_id')->references('id')->on('users');
            $table->foreign('resolved_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_flags');
    }
};
