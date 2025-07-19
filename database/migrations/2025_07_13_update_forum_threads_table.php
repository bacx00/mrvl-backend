<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('forum_threads', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_threads', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('category')->constrained('forum_categories')->onDelete('set null');
            }
            if (!Schema::hasColumn('forum_threads', 'upvotes')) {
                $table->integer('upvotes')->default(0)->after('views');
            }
            if (!Schema::hasColumn('forum_threads', 'downvotes')) {
                $table->integer('downvotes')->default(0)->after('upvotes');
            }
            if (!Schema::hasColumn('forum_threads', 'score')) {
                $table->integer('score')->default(0)->after('downvotes');
            }
            if (!Schema::hasColumn('forum_threads', 'slug')) {
                $table->string('slug')->nullable()->after('title');
            }
        });
    }

    public function down()
    {
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'upvotes', 'downvotes', 'score', 'slug']);
        });
    }
};