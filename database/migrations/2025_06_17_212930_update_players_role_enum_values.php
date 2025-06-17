<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Update the enum column to support Marvel Rivals roles
        DB::statement("ALTER TABLE players MODIFY COLUMN role ENUM('Duelist', 'Tank', 'Support', 'Flex', 'Sub')");
    }

    public function down()
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE players MODIFY COLUMN role ENUM('Duelist', 'Tank', 'Support', 'Controller')");
    }
};