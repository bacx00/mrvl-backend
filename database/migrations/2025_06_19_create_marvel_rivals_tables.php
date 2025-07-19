<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // SQLite doesn't support MODIFY COLUMN, so we skip this for SQLite
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE events MODIFY COLUMN type ENUM(
                'championship',
                'tournament', 
                'scrim',
                'qualifier',
                'regional',
                'international',
                'invitational',
                'community',
                'friendly',
                'practice',
                'exhibition'
            ) NOT NULL");
        }
    }

    public function down()
    {
        // Revert to original ENUM values
        DB::statement("ALTER TABLE events MODIFY COLUMN type ENUM(
            'championship',
            'tournament',
            'scrim', 
            'qualifier',
            'regional',
            'international',
            'International',
            'Regional',
            'Qualifier',
            'Community'
        ) NOT NULL");
    }
};