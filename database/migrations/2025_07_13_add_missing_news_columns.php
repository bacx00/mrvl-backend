<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'video_url')) {
                $table->string('video_url')->nullable()->after('featured_image');
            }
            if (!Schema::hasColumn('news', 'region')) {
                $table->string('region', 10)->default('INTL')->after('category');
            }
            if (!Schema::hasColumn('news', 'comments_count')) {
                $table->integer('comments_count')->default(0)->after('views');
            }
            if (!Schema::hasColumn('news', 'upvotes')) {
                $table->integer('upvotes')->default(0)->after('comments_count');
            }
            if (!Schema::hasColumn('news', 'downvotes')) {
                $table->integer('downvotes')->default(0)->after('upvotes');
            }
        });
    }

    public function down()
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'region', 'comments_count', 'upvotes', 'downvotes']);
        });
    }
};