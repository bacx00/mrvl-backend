<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "Starting database cleanup...\n";
    
    // Disable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    
    // Get all tables that might contain player/team data
    $tables = DB::select('SHOW TABLES');
    $database = config('database.connections.mysql.database');
    
    foreach ($tables as $table) {
        $tableName = $table->{"Tables_in_{$database}"};
        echo "Table: $tableName\n";
    }
    
    // Truncate relevant tables
    $tablesToClear = [
        'player_team_history',
        'players', 
        'teams',
        'match_player_stats',
        'event_teams'
    ];
    
    foreach ($tablesToClear as $table) {
        try {
            DB::table($table)->truncate();
            echo "Cleared table: $table\n";
        } catch (\Exception $e) {
            echo "Warning: Could not clear $table - " . $e->getMessage() . "\n";
        }
    }
    
    // Re-enable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    
    echo "\nDatabase cleanup completed!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}