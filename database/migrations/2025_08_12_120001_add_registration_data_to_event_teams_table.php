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
        Schema::table('event_teams', function (Blueprint $table) {
            if (!Schema::hasColumn('event_teams', 'registration_data')) {
                $table->json('registration_data')->nullable()->after('registered_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_teams', function (Blueprint $table) {
            if (Schema::hasColumn('event_teams', 'registration_data')) {
                $table->dropColumn('registration_data');
            }
        });
    }
};