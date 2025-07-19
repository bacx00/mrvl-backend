<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // SQLite doesn't support MODIFY COLUMN, so we skip this for SQLite
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE players MODIFY COLUMN role ENUM('Vanguard', 'Duelist', 'Strategist', 'Tank', 'Support', 'Flex', 'Sub')");
        }
    }

    public function down()
    {
        // SQLite doesn't support MODIFY COLUMN, so we skip this for SQLite
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE players MODIFY COLUMN role ENUM('Duelist', 'Tank', 'Support', 'Controller')");
        }
    }
};