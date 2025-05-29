<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image')->nullable();
            $table->json('gallery')->nullable(); // For multiple images
            $table->string('category')->default('general');
            $table->json('tags')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->datetime('published_at')->nullable();
            $table->integer('views')->default(0);
            $table->boolean('featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('meta_data')->nullable(); // For SEO and extra data
            $table->timestamps();

            // Indexes for performance
            $table->index(['status', 'published_at']);
            $table->index(['category', 'status']);
            $table->index(['featured', 'status']);
            $table->index(['author_id']);
            $table->fullText(['title', 'content', 'excerpt']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('news');
    }
};
