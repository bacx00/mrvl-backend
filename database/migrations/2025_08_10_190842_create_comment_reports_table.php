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
        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comment_id');
            $table->unsignedBigInteger('reporter_id');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
            $table->timestamps();
            
            $table->foreign('comment_id')->references('id')->on('news_comments')->onDelete('cascade');
            $table->foreign('reporter_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
    }
};
