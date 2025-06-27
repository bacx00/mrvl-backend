<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Update the ENUM to use proper Marvel Rivals roles
        DB::statement("ALTER TABLE players MODIFY COLUMN role ENUM('Vanguard', 'Duelist', 'Strategist') NOT NULL");
        
        // Update existing data to use new role names
        DB::table('players')->where('role', 'Tank')->update(['role' => 'Vanguard']);
        DB::table('players')->where('role', 'Support')->update(['role' => 'Strategist']);
        // Duelist stays the same
    }

    public function down()
    {
        // Revert to old roles
        DB::table('players')->where('role', 'Vanguard')->update(['role' => 'Tank']);
        DB::table('players')->where('role', 'Strategist')->update(['role' => 'Support']);
        
        DB::statement("ALTER TABLE players MODIFY COLUMN role ENUM('Tank', 'Duelist', 'Support') NOT NULL");
    }
};
