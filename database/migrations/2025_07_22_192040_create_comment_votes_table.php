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
        Schema::create('comment_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comment_id');
            $table->string('comment_type'); // 'match', 'forum', etc.
            $table->enum('vote_type', ['upvote', 'downvote']);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            
            $table->unique(['comment_id', 'comment_type', 'user_id']);
            $table->index(['comment_id', 'comment_type', 'vote_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_votes');
    }
};
