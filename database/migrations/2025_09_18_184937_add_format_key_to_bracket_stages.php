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
            if (!Schema::hasColumn('bracket_stages', 'format_key')) {
                $table->string('format_key')->nullable()->after('type')
                      ->comment('Key reference to tournament format definition');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bracket_stages', function (Blueprint $table) {
            if (Schema::hasColumn('bracket_stages', 'format_key')) {
                $table->dropColumn('format_key');
            }
        });
    }
};
