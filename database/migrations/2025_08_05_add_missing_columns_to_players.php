<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('players', function (Blueprint $table) {
            // Add all missing columns based on Player model fillable
            if (!Schema::hasColumn('players', 'earnings')) {
                $table->decimal('earnings', 15, 2)->default(0)->after('age');
            }
            if (!Schema::hasColumn('players', 'twitter')) {
                $table->string('twitter')->nullable();
            }
            if (!Schema::hasColumn('players', 'instagram')) {
                $table->string('instagram')->nullable();
            }
            if (!Schema::hasColumn('players', 'youtube')) {
                $table->string('youtube')->nullable();
            }
            if (!Schema::hasColumn('players', 'twitch')) {
                $table->string('twitch')->nullable();
            }
            if (!Schema::hasColumn('players', 'tiktok')) {
                $table->string('tiktok')->nullable();
            }
            if (!Schema::hasColumn('players', 'discord')) {
                $table->string('discord')->nullable();
            }
            if (!Schema::hasColumn('players', 'facebook')) {
                $table->string('facebook')->nullable();
            }
            if (!Schema::hasColumn('players', 'total_earnings')) {
                $table->decimal('total_earnings', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('players', 'team_position')) {
                $table->string('team_position')->default('player');
            }
            if (!Schema::hasColumn('players', 'position_order')) {
                $table->integer('position_order')->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['earnings', 'twitter', 'instagram', 'youtube', 'twitch', 
                               'tiktok', 'discord', 'facebook', 'total_earnings', 
                               'team_position', 'position_order']);
        });
    }
};