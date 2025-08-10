<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            // Add missing coach fields
            $table->string('coach_name')->nullable()->after('coach_image');
            $table->string('coach_nationality')->nullable()->after('coach_name'); 
            $table->json('coach_social_media')->nullable()->after('coach_nationality');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['coach_name', 'coach_nationality', 'coach_social_media']);
        });
    }
};