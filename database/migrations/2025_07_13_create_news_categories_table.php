<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6b7280'); // Hex color for category
            $table->string('icon')->nullable(); // Icon class or emoji
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        
        // Add foreign key to news table
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('category')->constrained('news_categories')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
        
        Schema::dropIfExists('news_categories');
    }
};