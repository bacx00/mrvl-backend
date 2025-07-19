<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('news_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('news_comments', 'is_edited')) {
                $table->boolean('is_edited')->default(false)->after('downvotes');
            }
            if (!Schema::hasColumn('news_comments', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('is_edited');
            }
            if (!Schema::hasColumn('news_comments', 'status')) {
                $table->enum('status', ['active', 'deleted', 'moderated'])->default('active')->after('edited_at');
            }
        });
        
        // Create news comment votes table if it doesn't exist
        if (!Schema::hasTable('news_comment_votes')) {
            Schema::create('news_comment_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('comment_id')->constrained('news_comments')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                $table->unique(['comment_id', 'user_id']); // One vote per user per comment
            });
        }
    }

    public function down()
    {
        Schema::table('news_comments', function (Blueprint $table) {
            $table->dropColumn(['is_edited', 'edited_at', 'status']);
        });
        
        Schema::dropIfExists('news_comment_votes');
    }
};