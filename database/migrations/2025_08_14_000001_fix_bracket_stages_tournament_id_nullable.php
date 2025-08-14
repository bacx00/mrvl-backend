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
        Schema::table('bracket_stages', function (Blueprint $table) {
            // Make tournament_id nullable
            $table->foreignId('tournament_id')->nullable()->change();
        });
        
        Schema::table('bracket_matches', function (Blueprint $table) {
            // Make tournament_id nullable  
            $table->foreignId('tournament_id')->nullable()->change();
        });
        
        Schema::table('bracket_seedings', function (Blueprint $table) {
            // Make tournament_id nullable
            $table->foreignId('tournament_id')->nullable()->change();
        });
        
        Schema::table('bracket_standings', function (Blueprint $table) {
            // Make tournament_id nullable
            $table->foreignId('tournament_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't revert as this would break existing data
        // Schema::table('bracket_stages', function (Blueprint $table) {
        //     $table->foreignId('tournament_id')->nullable(false)->change();
        // });
    }
};