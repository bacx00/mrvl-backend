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
            // Make team IDs nullable to support bracket progression
            $table->unsignedBigInteger('team1_id')->nullable()->change();
            $table->unsignedBigInteger('team2_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This might fail if there are null values in the database
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedBigInteger('team1_id')->nullable(false)->change();
            $table->unsignedBigInteger('team2_id')->nullable(false)->change();
        });
    }
};