<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'wins')) {
                $table->integer('wins')->default(0)->after('record');
            }
            if (!Schema::hasColumn('teams', 'losses')) {
                $table->integer('losses')->default(0)->after('wins');
            }
            if (!Schema::hasColumn('teams', 'description')) {
                $table->text('description')->nullable()->after('coach');
            }
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['wins', 'losses', 'description']);
        });
    }
};