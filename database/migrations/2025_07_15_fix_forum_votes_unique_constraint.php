<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Skip this migration for now - we'll handle uniqueness in the application logic
        // The current unique constraint might be causing issues with NULL values
    }

    public function down()
    {
        // Nothing to rollback
    }
};