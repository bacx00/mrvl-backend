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
            $table->integer('placement')->nullable()->after('status');
            $table->decimal('prize_money', 15, 2)->nullable()->after('placement');
            $table->integer('points')->nullable()->after('prize_money');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_teams', function (Blueprint $table) {
            $table->dropColumn(['placement', 'prize_money', 'points']);
        });
    }
};
