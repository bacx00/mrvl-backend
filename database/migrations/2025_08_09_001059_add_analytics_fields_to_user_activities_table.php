<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            // Add enhanced analytics fields only if they don't exist
            if (!Schema::hasColumn('user_activities', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('user_activities', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('user_activities', 'session_id')) {
                $table->string('session_id')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('user_activities', 'url')) {
                $table->string('url')->nullable()->after('session_id');
            }
            if (!Schema::hasColumn('user_activities', 'referrer')) {
                $table->string('referrer')->nullable()->after('url');
            }
        });
        
        // Add indexes with existence checking using Laravel schema builder
        $indexes = [
            'user_activities_user_id_created_at_index' => ['user_id', 'created_at'],
            'user_activities_action_created_at_index' => ['action', 'created_at'],
            'user_activities_resource_type_resource_id_index' => ['resource_type', 'resource_id'],
            'user_activities_session_id_index' => ['session_id'],
            'user_activities_created_at_index' => ['created_at']
        ];
        
        foreach ($indexes as $indexName => $columns) {
            try {
                $exists = DB::select("SHOW INDEX FROM user_activities WHERE Key_name = ?", [$indexName]);
                if (empty($exists)) {
                    Schema::table('user_activities', function (Blueprint $table) use ($columns) {
                        $table->index($columns);
                    });
                }
            } catch (\Exception $e) {
                // Index creation failed, continue
            }
        }
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
