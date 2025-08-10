<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Starting forum voting system fix...\n";

// Step 1: Remove all existing problematic unique constraints
echo "Removing problematic unique constraints...\n";

$constraintsToRemove = [
    'forum_votes_user_thread_post_unique',
    'forum_votes_user_thread_unique',
    'forum_votes_user_post_unique',
    'forum_votes_user_thread_null_unique'
];

foreach ($constraintsToRemove as $constraint) {
    try {
        DB::statement("ALTER TABLE forum_votes DROP INDEX IF EXISTS `{$constraint}`");
        echo "Removed constraint: {$constraint}\n";
    } catch (\Exception $e) {
        echo "Could not remove constraint {$constraint}: " . $e->getMessage() . "\n";
    }
}

// Step 2: Clean up any duplicate votes
echo "Cleaning up duplicate votes...\n";

// Remove duplicate thread votes (keep the most recent)
$duplicateThreadVotes = DB::statement('
    DELETE v1 FROM forum_votes v1
    INNER JOIN forum_votes v2
    WHERE v1.id < v2.id
    AND v1.user_id = v2.user_id
    AND v1.thread_id = v2.thread_id
    AND v1.post_id IS NULL
    AND v2.post_id IS NULL
');

echo "Removed duplicate thread votes\n";

// Remove duplicate post votes (keep the most recent)
$duplicatePostVotes = DB::statement('
    DELETE v1 FROM forum_votes v1
    INNER JOIN forum_votes v2
    WHERE v1.id < v2.id
    AND v1.user_id = v2.user_id
    AND v1.post_id = v2.post_id
    AND v1.post_id IS NOT NULL
    AND v2.post_id IS NOT NULL
');

echo "Removed duplicate post votes\n";

// Step 3: Add proper unique constraints using vote_key
echo "Adding proper unique constraints using vote_key...\n";

// Ensure vote_key column exists and is properly indexed
try {
    if (!Schema::hasColumn('forum_votes', 'vote_key')) {
        Schema::table('forum_votes', function ($table) {
            $table->string('vote_key')->unique()->nullable();
        });
    }
} catch (\Exception $e) {
    echo "vote_key column already exists or could not be added\n";
}

// Update vote_key for existing records
DB::statement("
    UPDATE forum_votes 
    SET vote_key = CONCAT(
        'user:', user_id, 
        CASE 
            WHEN post_id IS NOT NULL THEN CONCAT(':post:', post_id)
            ELSE CONCAT(':thread:', thread_id)
        END
    )
    WHERE vote_key IS NULL
");

echo "Updated vote_key for existing records\n";

// Step 4: Update vote counts for all threads and posts
echo "Updating vote counts...\n";

// Update thread vote counts
DB::statement('
    UPDATE forum_threads ft
    SET 
        upvotes = (
            SELECT COUNT(*) 
            FROM forum_votes fv 
            WHERE fv.thread_id = ft.id 
            AND fv.post_id IS NULL 
            AND fv.vote_type = "upvote"
        ),
        downvotes = (
            SELECT COUNT(*) 
            FROM forum_votes fv 
            WHERE fv.thread_id = ft.id 
            AND fv.post_id IS NULL 
            AND fv.vote_type = "downvote"
        ),
        score = (
            SELECT 
                COALESCE(
                    SUM(CASE WHEN fv.vote_type = "upvote" THEN 1 WHEN fv.vote_type = "downvote" THEN -1 ELSE 0 END), 
                    0
                )
            FROM forum_votes fv 
            WHERE fv.thread_id = ft.id 
            AND fv.post_id IS NULL
        )
');

echo "Updated thread vote counts\n";

// Update post vote counts
DB::statement('
    UPDATE forum_posts fp
    SET 
        upvotes = (
            SELECT COUNT(*) 
            FROM forum_votes fv 
            WHERE fv.post_id = fp.id 
            AND fv.vote_type = "upvote"
        ),
        downvotes = (
            SELECT COUNT(*) 
            FROM forum_votes fv 
            WHERE fv.post_id = fp.id 
            AND fv.vote_type = "downvote"
        ),
        score = (
            SELECT 
                COALESCE(
                    SUM(CASE WHEN fv.vote_type = "upvote" THEN 1 WHEN fv.vote_type = "downvote" THEN -1 ELSE 0 END), 
                    0
                )
            FROM forum_votes fv 
            WHERE fv.post_id = fp.id
        )
');

echo "Updated post vote counts\n";

echo "Forum voting system fix completed successfully!\n";