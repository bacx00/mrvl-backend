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
        Schema::table('matches', function (Blueprint $table) {
            // Add missing URL columns if they don't exist
            if (!Schema::hasColumn('matches', 'stream_urls')) {
                $table->json('stream_urls')->nullable()->after('stream_url');
            }
            
            if (!Schema::hasColumn('matches', 'vod_urls')) {
                $table->json('vod_urls')->nullable()->after('stream_urls');
            }
            
            if (!Schema::hasColumn('matches', 'betting_urls')) {
                $table->json('betting_urls')->nullable()->after('vod_urls');
            }
            
            // Add additional match control columns
            if (!Schema::hasColumn('matches', 'is_paused')) {
                $table->boolean('is_paused')->default(false)->after('status');
            }
            
            if (!Schema::hasColumn('matches', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('is_paused');
            }
            
            if (!Schema::hasColumn('matches', 'timer_running')) {
                $table->boolean('timer_running')->default(false);
            }
            
            if (!Schema::hasColumn('matches', 'is_preparation_phase')) {
                $table->boolean('is_preparation_phase')->default(false);
            }
            
            if (!Schema::hasColumn('matches', 'preparation_timer')) {
                $table->integer('preparation_timer')->default(45);
            }
        });
        
        // Create match_logs table if it doesn't exist
        if (!Schema::hasTable('match_logs')) {
            Schema::create('match_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->string('action');
                $table->text('reason')->nullable();
                $table->json('data')->nullable();
                $table->unsignedBigInteger('performed_by');
                $table->timestamps();
                
                $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
                $table->foreign('performed_by')->references('id')->on('users')->onDelete('cascade');
                $table->index(['match_id', 'action']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['stream_urls', 'vod_urls', 'betting_urls', 'is_paused', 'paused_at', 'timer_running', 'is_preparation_phase', 'preparation_timer']);
        });
        
        Schema::dropIfExists('match_logs');
    }
};