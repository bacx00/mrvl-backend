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
        Schema::create('news_video_embeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained('news')->onDelete('cascade');
            $table->string('platform', 50); // youtube, twitch-clip, twitch-video, twitter, vlrgg
            $table->string('video_id')->nullable();
            $table->text('embed_url')->nullable();
            $table->text('original_url');
            $table->string('title')->nullable();
            $table->string('thumbnail')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->json('metadata')->nullable(); // additional platform-specific data
            $table->timestamps();
            
            $table->index(['news_id', 'platform']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_video_embeds');
    }
};
