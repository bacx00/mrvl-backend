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
        // Create tournament followers pivot table
        if (!Schema::hasTable('tournament_followers')) {
            Schema::create('tournament_followers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                
                $table->unique(['user_id', 'tournament_id']);
                $table->index('user_id');
                $table->index('tournament_id');
            });
        }

        // Create team players pivot table if it doesn't exist
        if (!Schema::hasTable('team_players')) {
            Schema::create('team_players', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->string('position', 50)->nullable();
                $table->enum('status', ['active', 'inactive', 'substitute', 'coach', 'manager'])->default('active');
                $table->datetime('joined_at')->default(now());
                $table->timestamps();
                
                $table->unique(['user_id', 'team_id']);
                $table->index('user_id');
                $table->index('team_id');
                $table->index(['team_id', 'status']);
            });
        }

        // Add missing chat_messages table for tournament match chat
        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('context_type', 50); // 'tournament_match', 'team_chat', etc.
                $table->unsignedBigInteger('context_id'); // The ID of the context (match_id, team_id, etc.)
                $table->text('message');
                $table->boolean('is_system')->default(false);
                $table->json('metadata')->nullable(); // For additional data like mentions, formatting, etc.
                $table->timestamps();
                
                $table->index(['context_type', 'context_id']);
                $table->index('user_id');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('team_players');
        Schema::dropIfExists('tournament_followers');
    }
};