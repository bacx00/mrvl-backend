<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Step 1: Add temporary column with new roles
        Schema::table('players', function (Blueprint $table) {
            $table->enum('role_new', ['Vanguard', 'Duelist', 'Strategist'])->nullable()->after('role');
        });
        
        // Step 2: Migrate data to new column
        DB::table('players')->where('role', 'Tank')->update(['role_new' => 'Vanguard']);
        DB::table('players')->where('role', 'Support')->update(['role_new' => 'Strategist']);
        DB::table('players')->where('role', 'Duelist')->update(['role_new' => 'Duelist']);
        
        // Step 3: Drop old column and rename new one
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        Schema::table('players', function (Blueprint $table) {
            $table->renameColumn('role_new', 'role');
        });
    }

    public function down()
    {
        // Revert process
        Schema::table('players', function (Blueprint $table) {
            $table->enum('role_old', ['Tank', 'Duelist', 'Support'])->nullable()->after('role');
        });
        
        DB::table('players')->where('role', 'Vanguard')->update(['role_old' => 'Tank']);
        DB::table('players')->where('role', 'Strategist')->update(['role_old' => 'Support']);
        DB::table('players')->where('role', 'Duelist')->update(['role_old' => 'Duelist']);
        
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        Schema::table('players', function (Blueprint $table) {
            $table->renameColumn('role_old', 'role');
        });
    }
};
