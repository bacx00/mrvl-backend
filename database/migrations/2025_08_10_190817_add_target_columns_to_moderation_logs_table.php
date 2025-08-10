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
        Schema::table('moderation_logs', function (Blueprint $table) {
            $table->string('target_type')->nullable()->after('moderator_id');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->string('ip_address')->nullable()->after('duration');
            $table->text('user_agent')->nullable()->after('ip_address');
            
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moderation_logs', function (Blueprint $table) {
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['target_type', 'target_id', 'ip_address', 'user_agent']);
        });
    }
};
