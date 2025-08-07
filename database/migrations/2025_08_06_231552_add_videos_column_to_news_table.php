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
        Schema::table('news', function (Blueprint $table) {
            // Add videos JSON column for storing video embed data
            if (!Schema::hasColumn('news', 'videos')) {
                $table->json('videos')->nullable()->after('video_url');
            }
            
            // Add missing columns that NewsController expects
            if (!Schema::hasColumn('news', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('category');
                $table->foreign('category_id')->references('id')->on('news_categories')->onDelete('set null');
            }
            
            // Add breaking news column
            if (!Schema::hasColumn('news', 'breaking')) {
                $table->boolean('breaking')->default(false)->after('featured');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Drop foreign key constraint first
            if (Schema::hasColumn('news', 'category_id')) {
                $table->dropForeign(['category_id']);
            }
            
            // Drop the added columns
            $table->dropColumn(['videos', 'category_id', 'breaking']);
        });
    }
};
