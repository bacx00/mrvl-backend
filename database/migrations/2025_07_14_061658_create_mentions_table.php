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
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            
            // What content mentioned the entity
            $table->morphs('mentionable'); // news, match, forum_thread, forum_post, news_comment, match_comment
            
            // What was mentioned (player, team, user)
            $table->string('mentioned_type'); // 'player', 'team', 'user'
            $table->unsignedBigInteger('mentioned_id');
            
            // Context information
            $table->text('context')->nullable(); // Surrounding text for context
            $table->string('mention_text'); // The actual mention text (@username, #teamname, etc.)
            $table->integer('position_start')->nullable(); // Start position in content
            $table->integer('position_end')->nullable(); // End position in content
            
            // Metadata
            $table->foreignId('mentioned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('mentioned_at');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional data like mention type, etc.
            
            $table->timestamps();
            
            // Indexes
            $table->index(['mentioned_type', 'mentioned_id']);
            $table->index(['mentionable_type', 'mentionable_id']);
            $table->index('mentioned_at');
            $table->index('is_active');
            
            // Ensure uniqueness to prevent duplicate mentions
            $table->unique([
                'mentionable_type', 
                'mentionable_id', 
                'mentioned_type', 
                'mentioned_id',
                'mention_text'
            ], 'unique_mention');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
