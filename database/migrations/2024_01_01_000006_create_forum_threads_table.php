<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('category')->default('General');
            $table->integer('replies')->default(0);
            $table->integer('views')->default(0);
            $table->boolean('pinned')->default(false);
            $table->boolean('locked')->default(false);
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'pinned', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('forum_threads');
    }
};