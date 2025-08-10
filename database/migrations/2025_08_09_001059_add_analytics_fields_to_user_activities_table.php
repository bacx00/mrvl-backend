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
        Schema::table('user_activities', function (Blueprint $table) {
            // Add enhanced analytics fields
            $table->string('ip_address')->nullable()->after('metadata');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('session_id')->nullable()->after('user_agent');
            $table->string('url')->nullable()->after('session_id');
            $table->string('referrer')->nullable()->after('url');
            
            // Add indexes for better query performance
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('session_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['resource_type', 'resource_id']);
            $table->dropIndex(['session_id']);
            $table->dropIndex(['created_at']);
            
            // Drop columns
            $table->dropColumn([
                'ip_address',
                'user_agent', 
                'session_id',
                'url',
                'referrer'
            ]);
        });
    }
};
