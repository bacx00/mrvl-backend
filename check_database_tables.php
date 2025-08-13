<?php
require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Set up database connection using Laravel's database configuration
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Get all tables
    $tables = DB::select('SHOW TABLES');
    
    echo "=== TOURNAMENT-RELATED TABLES ===\n";
    foreach($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if(strpos($tableName, 'tournament') !== false || 
           strpos($tableName, 'team') !== false || 
           strpos($tableName, 'player') !== false || 
           strpos($tableName, 'match') !== false || 
           strpos($tableName, 'bracket') !== false) {
            echo $tableName . "\n";
        }
    }
    
    echo "\n=== INDEX ANALYSIS FOR KEY TABLES ===\n";
    
    $keyTables = ['teams', 'players', 'matches', 'tournaments', 'bracket_matches'];
    
    foreach($keyTables as $table) {
        try {
            echo "\n--- INDEXES FOR $table ---\n";
            $indexes = DB::select("SHOW INDEX FROM `$table`");
            foreach($indexes as $index) {
                echo sprintf("%-20s %-15s %s\n", 
                    $index->Key_name, 
                    $index->Column_name, 
                    $index->Index_type
                );
            }
        } catch (Exception $e) {
            echo "Table $table does not exist\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}