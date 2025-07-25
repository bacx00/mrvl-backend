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
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('matches', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('match_timer');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('matches', 'allow_past_date')) {
                $table->boolean('allow_past_date')->default(false)->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            if (Schema::hasColumn('matches', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            
            if (Schema::hasColumn('matches', 'allow_past_date')) {
                $table->dropColumn('allow_past_date');
            }
        });
    }
};