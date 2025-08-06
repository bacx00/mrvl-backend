<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // First, remove any duplicate thread votes that might exist
        DB::statement("
            DELETE fv1 FROM forum_votes fv1
            INNER JOIN forum_votes fv2 
            WHERE fv1.id > fv2.id 
            AND fv1.thread_id = fv2.thread_id 
            AND fv1.user_id = fv2.user_id 
            AND fv1.post_id IS NULL 
            AND fv2.post_id IS NULL
        ");

        // Remove any duplicate post votes that might exist
        DB::statement("
            DELETE fv1 FROM forum_votes fv1
            INNER JOIN forum_votes fv2 
            WHERE fv1.id > fv2.id 
            AND fv1.post_id = fv2.post_id 
            AND fv1.user_id = fv2.user_id 
            AND fv1.post_id IS NOT NULL 
            AND fv2.post_id IS NOT NULL
        ");

        // Add application-level unique constraints using composite keys
        // Since MySQL doesn't support partial indexes well, we'll use a different approach
        
        // Create a computed column for thread votes
        Schema::table('forum_votes', function (Blueprint $table) {
            // Add a computed column that helps with uniqueness
            if (!Schema::hasColumn('forum_votes', 'vote_key')) {
                $table->string('vote_key', 100)->nullable()->after('vote_type');
            }
        });

        // Update existing records to set the vote_key
        DB::statement("
            UPDATE forum_votes 
            SET vote_key = CASE 
                WHEN post_id IS NULL THEN CONCAT('thread_', thread_id, '_', user_id)
                ELSE CONCAT('post_', post_id, '_', user_id)
            END
        ");

        // Make vote_key required and unique
        Schema::table('forum_votes', function (Blueprint $table) {
            $table->string('vote_key', 100)->nullable(false)->change();
            $table->unique('vote_key');
        });

        echo "Thread and post voting constraints fixed!\n";
    }

    public function down()
    {
        Schema::table('forum_votes', function (Blueprint $table) {
            $table->dropUnique(['vote_key']);
            $table->dropColumn('vote_key');
        });
    }
};