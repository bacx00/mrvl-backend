<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('hero_flair')->nullable()->after('avatar')->comment('Selected Marvel Rivals hero for flair display');
            $table->unsignedBigInteger('team_flair_id')->nullable()->after('hero_flair')->comment('Selected team for flair display');
            $table->boolean('show_hero_flair')->default(true)->after('team_flair_id');
            $table->boolean('show_team_flair')->default(false)->after('show_hero_flair');
            
            $table->foreign('team_flair_id')->references('id')->on('teams')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_flair_id']);
            $table->dropColumn(['hero_flair', 'team_flair_id', 'show_hero_flair', 'show_team_flair']);
        });
    }
};