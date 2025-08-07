<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('user_activities')) {
            Schema::create('user_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('action'); // vote_added, vote_changed, vote_removed, etc.
                $table->text('content'); // Human-readable description
                $table->string('resource_type')->nullable(); // news, forum_thread, forum_post, etc.
                $table->unsignedBigInteger('resource_id')->nullable(); // ID of the subject
                $table->json('metadata')->nullable(); // Additional data about the activity
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                // Indexes for performance
                $table->index(['user_id', 'created_at']);
                $table->index(['action']);
                $table->index(['resource_type', 'resource_id']);
                $table->index(['created_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('user_activities');
    }
};