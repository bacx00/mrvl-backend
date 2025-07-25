<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'score')) {
                $table->integer('score')->default(0)->after('downvotes');
            }
            if (!Schema::hasColumn('news', 'breaking')) {
                $table->boolean('breaking')->default(false)->after('featured');
            }
        });
    }

    public function down()
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['score', 'breaking']);
        });
    }
};