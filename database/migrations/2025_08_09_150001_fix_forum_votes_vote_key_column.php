<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('forum_votes', function (Blueprint $table) {
            // Check if vote_key column exists and make it nullable or remove it
            if (Schema::hasColumn('forum_votes', 'vote_key')) {
                $table->string('vote_key')->nullable()->change();
            }
        });
        
        // Alternative: Drop the vote_key column if it's not needed
        // Schema::table('forum_votes', function (Blueprint $table) {
        //     if (Schema::hasColumn('forum_votes', 'vote_key')) {
        //         $table->dropColumn('vote_key');
        //     }
        // });
    }

    public function down()
    {
        Schema::table('forum_votes', function (Blueprint $table) {
            if (Schema::hasColumn('forum_votes', 'vote_key')) {
                $table->string('vote_key')->nullable(false)->change();
            }
        });
    }
};