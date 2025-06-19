<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update the events table 'type' column to include all valid event types
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