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
        Schema::table('users', function (Blueprint $table) {
            // First, drop the existing role column if it exists
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            // Add the new enum role column with proper values
            $table->enum('role', ['user', 'moderator', 'admin'])
                  ->default('user')
                  ->after('email')
                  ->comment('User role: user, moderator, admin');
        });

        // Set default role for existing users
        DB::table('users')->whereNull('role')->update(['role' => 'user']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};