<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            // Add all social media columns
            if (!Schema::hasColumn('teams', 'twitter')) {
                $table->string('twitter')->nullable();
            }
            if (!Schema::hasColumn('teams', 'instagram')) {
                $table->string('instagram')->nullable();
            }
            if (!Schema::hasColumn('teams', 'youtube')) {
                $table->string('youtube')->nullable();
            }
            if (!Schema::hasColumn('teams', 'twitch')) {
                $table->string('twitch')->nullable();
            }
            if (!Schema::hasColumn('teams', 'tiktok')) {
                $table->string('tiktok')->nullable();
            }
            if (!Schema::hasColumn('teams', 'discord')) {
                $table->string('discord')->nullable();
            }
            if (!Schema::hasColumn('teams', 'facebook')) {
                $table->string('facebook')->nullable();
            }
            if (!Schema::hasColumn('teams', 'country')) {
                $table->string('country')->nullable();
            }
            if (!Schema::hasColumn('teams', 'founded_date')) {
                $table->date('founded_date')->nullable();
            }
            if (!Schema::hasColumn('teams', 'owner')) {
                $table->string('owner')->nullable();
            }
            if (!Schema::hasColumn('teams', 'coach')) {
                $table->string('coach')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['twitter', 'instagram', 'youtube', 'twitch', 
                               'tiktok', 'discord', 'facebook', 'country',
                               'founded_date', 'owner', 'coach']);
        });
    }
};