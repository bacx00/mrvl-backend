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
        Schema::table('match_comments', function (Blueprint $table) {
            // Add missing columns for forum-style functionality
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('content');
            $table->boolean('is_edited')->default(false)->after('status');
            $table->timestamp('edited_at')->nullable()->after('is_edited');
            $table->integer('upvotes')->default(0)->after('edited_at');
            $table->integer('downvotes')->default(0)->after('upvotes');
            $table->integer('score')->default(0)->after('downvotes');
            $table->boolean('is_flagged')->default(false)->after('dislikes');
            $table->text('moderation_note')->nullable()->after('is_flagged');
            $table->timestamp('last_moderated_at')->nullable()->after('moderation_note');
            $table->unsignedBigInteger('last_moderated_by')->nullable()->after('last_moderated_at');
            $table->softDeletes()->after('updated_at');

            // Add foreign key for last_moderated_by
            $table->foreign('last_moderated_by')->references('id')->on('users')->onDelete('set null');

            // Add indexes for performance
            $table->index(['match_id', 'created_at']);
            $table->index(['parent_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['is_flagged', 'created_at']);
            $table->index(['score', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_comments', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['last_moderated_by']);
            
            // Drop indexes
            $table->dropIndex(['match_id', 'created_at']);
            $table->dropIndex(['parent_id', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['is_flagged', 'created_at']);
            $table->dropIndex(['score', 'created_at']);
            
            // Drop columns
            $table->dropColumn([
                'status', 'is_edited', 'edited_at', 'upvotes', 'downvotes', 
                'score', 'is_flagged', 'moderation_note', 'last_moderated_at', 
                'last_moderated_by', 'deleted_at'
            ]);
        });
    }
};
