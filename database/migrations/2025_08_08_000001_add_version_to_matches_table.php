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
        Schema::table('matches', function (Blueprint $table) {
            // Only add version column if it doesn't exist
            if (!Schema::hasColumn('matches', 'version')) {
                $table->integer('version')->default(0)->after('updated_at')->comment('Version for optimistic locking');
            }
            
            // Only add completed_at if it doesn't exist
            if (!Schema::hasColumn('matches', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('version');
            }
            
            // Add index for version-based queries if it doesn't exist
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('matches');
            $indexExists = false;
            foreach ($indexesFound as $index) {
                if ($index->hasColumnNames(['id', 'version'])) {
                    $indexExists = true;
                    break;
                }
            }
            if (!$indexExists) {
                $table->index(['id', 'version']);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['id', 'version']);
            $table->dropColumn(['version', 'completed_at']);
        });
    }
};