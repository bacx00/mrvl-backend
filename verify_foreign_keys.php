<?php

/**
 * VERIFY AND FIX FOREIGN KEY CONSTRAINTS
 */

// Change to the Laravel directory
chdir('/var/www/mrvl-backend');

// Include Laravel bootstrap
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== VERIFYING AND FIXING FOREIGN KEY CONSTRAINTS ===\n\n";

try {
    // Get current database name
    $database = DB::connection()->getDatabaseName();
    
    // Helper function to check if foreign key exists
    function foreignKeyExists($table, $constraint_name) {
        global $database;
        $result = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$database, $table, $constraint_name]);
        return !empty($result);
    }
    
    // Check existing foreign keys
    echo "1. CHECKING EXISTING FOREIGN KEYS...\n";
    
    $existing_fks = DB::select("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = ? 
        AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY TABLE_NAME, COLUMN_NAME
    ", [$database]);
    
    if (empty($existing_fks)) {
        echo "   - No existing foreign keys found\n";
    } else {
        foreach ($existing_fks as $fk) {
            echo "   ✓ {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME} ({$fk->CONSTRAINT_NAME})\n";
        }
    }
    
    echo "\n2. ADDING MISSING FOREIGN KEY CONSTRAINTS...\n";
    
    // Define the foreign keys that should exist
    $required_foreign_keys = [
        // Players table
        [
            'table' => 'players',
            'column' => 'team_id',
            'references_table' => 'teams',
            'references_column' => 'id',
            'constraint_name' => 'players_team_id_foreign',
            'on_delete' => 'SET NULL'
        ],
        
        // Matches table
        [
            'table' => 'matches',
            'column' => 'team1_id',
            'references_table' => 'teams',
            'references_column' => 'id',
            'constraint_name' => 'matches_team1_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        [
            'table' => 'matches',
            'column' => 'team2_id',
            'references_table' => 'teams',
            'references_column' => 'id',
            'constraint_name' => 'matches_team2_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        [
            'table' => 'matches',
            'column' => 'event_id',
            'references_table' => 'events',
            'references_column' => 'id',
            'constraint_name' => 'matches_event_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        [
            'table' => 'matches',
            'column' => 'winner_id',
            'references_table' => 'teams',
            'references_column' => 'id',
            'constraint_name' => 'matches_winner_id_foreign',
            'on_delete' => 'SET NULL'
        ],
        
        // Player match stats table
        [
            'table' => 'player_match_stats',
            'column' => 'match_id',
            'references_table' => 'matches',
            'references_column' => 'id',
            'constraint_name' => 'player_match_stats_match_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        [
            'table' => 'player_match_stats',
            'column' => 'player_id',
            'references_table' => 'players',
            'references_column' => 'id',
            'constraint_name' => 'player_match_stats_player_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        
        // Match maps table
        [
            'table' => 'match_maps',
            'column' => 'match_id',
            'references_table' => 'matches',
            'references_column' => 'id',
            'constraint_name' => 'match_maps_match_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        
        // Player team history table
        [
            'table' => 'player_team_history',
            'column' => 'player_id',
            'references_table' => 'players',
            'references_column' => 'id',
            'constraint_name' => 'player_team_history_player_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        [
            'table' => 'player_team_history',
            'column' => 'team_id',
            'references_table' => 'teams',
            'references_column' => 'id',
            'constraint_name' => 'player_team_history_team_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        
        // Event teams table
        [
            'table' => 'event_teams',
            'column' => 'event_id',
            'references_table' => 'events',
            'references_column' => 'id',
            'constraint_name' => 'event_teams_event_id_foreign',
            'on_delete' => 'CASCADE'
        ],
        [
            'table' => 'event_teams',
            'column' => 'team_id',
            'references_table' => 'teams',
            'references_column' => 'id',
            'constraint_name' => 'event_teams_team_id_foreign',
            'on_delete' => 'CASCADE'
        ]
    ];
    
    $created_count = 0;
    $skipped_count = 0;
    
    foreach ($required_foreign_keys as $fk) {
        $table = $fk['table'];
        $column = $fk['column'];
        $ref_table = $fk['references_table'];
        $ref_column = $fk['references_column'];
        $constraint = $fk['constraint_name'];
        $on_delete = $fk['on_delete'];
        
        // Check if table and column exist
        if (!Schema::hasTable($table)) {
            echo "   - Table {$table} does not exist, skipping constraint {$constraint}\n";
            continue;
        }
        
        if (!Schema::hasColumn($table, $column)) {
            echo "   - Column {$table}.{$column} does not exist, skipping constraint {$constraint}\n";
            continue;
        }
        
        if (!Schema::hasTable($ref_table)) {
            echo "   - Referenced table {$ref_table} does not exist, skipping constraint {$constraint}\n";
            continue;
        }
        
        // Check if foreign key already exists
        if (foreignKeyExists($table, $constraint)) {
            echo "   - Foreign key {$constraint} already exists on {$table}\n";
            $skipped_count++;
            continue;
        }
        
        try {
            // Add foreign key constraint
            DB::statement("
                ALTER TABLE {$table} 
                ADD CONSTRAINT {$constraint} 
                FOREIGN KEY ({$column}) 
                REFERENCES {$ref_table}({$ref_column}) 
                ON DELETE {$on_delete}
            ");
            echo "   ✓ Added foreign key {$constraint}: {$table}.{$column} -> {$ref_table}.{$ref_column}\n";
            $created_count++;
        } catch (Exception $e) {
            echo "   ✗ Failed to add foreign key {$constraint}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n3. FINAL FOREIGN KEY VERIFICATION...\n";
    
    $final_fks = DB::select("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = ? 
        AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY TABLE_NAME, COLUMN_NAME
    ", [$database]);
    
    echo "   Current foreign key constraints:\n";
    foreach ($final_fks as $fk) {
        echo "   ✓ {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
    }
    
    echo "\n=== FOREIGN KEY SETUP COMPLETED ===\n";
    echo "Created: {$created_count} constraints\n";
    echo "Skipped: {$skipped_count} constraints (already existed)\n";
    echo "Total: " . count($final_fks) . " foreign key constraints active\n\n";
    
    echo "Database integrity constraints are now properly configured!\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}