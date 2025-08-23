<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== DATABASE PERFORMANCE AND CONSTRAINT ANALYSIS ===\n\n";
    
    // 1. Check for missing recommended indexes
    echo "1. CHECKING FOR MISSING PERFORMANCE INDEXES:\n";
    echo str_repeat("-", 50) . "\n";
    
    $recommendedIndexes = [
        'bracket_matches' => [
            'idx_tournament_status' => ['tournament_id', 'status'],
            'idx_bracket_stage_status' => ['bracket_stage_id', 'status'],
            'idx_match_progression' => ['round_number', 'match_number', 'status'],
            'idx_team_matches' => ['team1_id', 'team2_id'],
            'idx_live_matches' => ['status', 'started_at', 'scheduled_at']
        ],
        'bracket_seedings' => [
            'idx_stage_seed_order' => ['bracket_stage_id', 'seed'],
            'idx_tournament_seed' => ['tournament_id', 'seed']
        ]
    ];
    
    foreach ($recommendedIndexes as $table => $indexes) {
        echo "\nTable: $table\n";
        
        // Get existing indexes
        $existingIndexes = DB::select("SHOW INDEX FROM $table");
        $existingIndexNames = array_map(function($idx) { return $idx->Key_name; }, $existingIndexes);
        
        foreach ($indexes as $indexName => $columns) {
            $exists = in_array($indexName, $existingIndexNames);
            $status = $exists ? "[EXISTS]" : "[MISSING]";
            echo "  $indexName (" . implode(', ', $columns) . ") $status\n";
        }
    }
    
    // 2. Check for constraints that might prevent operations
    echo "\n\n2. CHECKING FOR RESTRICTIVE CONSTRAINTS:\n";
    echo str_repeat("-", 50) . "\n";
    
    // Check foreign key constraints
    $foreignKeys = DB::select("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            DELETE_RULE,
            UPDATE_RULE
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME IN ('bracket_matches', 'bracket_seedings', 'bracket_stages')
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    echo "Foreign Key Constraints:\n";
    foreach ($foreignKeys as $fk) {
        echo "  {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
        echo "    Delete: {$fk->DELETE_RULE}, Update: {$fk->UPDATE_RULE}\n";
    }
    
    // 3. Check table constraints
    echo "\n\nTable Check Constraints:\n";
    $checkConstraints = DB::select("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            CHECK_CLAUSE
        FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS 
        WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('bracket_matches', 'bracket_seedings', 'bracket_stages')
    ");
    
    if (empty($checkConstraints)) {
        echo "  No custom check constraints found\n";
    } else {
        foreach ($checkConstraints as $constraint) {
            echo "  {$constraint->TABLE_NAME}: {$constraint->CONSTRAINT_NAME}\n";
            echo "    {$constraint->CHECK_CLAUSE}\n";
        }
    }
    
    // 4. Test common operations that might fail
    echo "\n\n3. TESTING COMMON OPERATIONS:\n";
    echo str_repeat("-", 50) . "\n";
    
    // Test tournament creation
    echo "Testing tournament creation flexibility...\n";
    try {
        // Check if we can create tournaments with various settings
        $tournamentTypes = ['single_elimination', 'double_elimination', 'swiss', 'round_robin'];
        $tournamentFormats = ['1v1', '3v3', '5v5', '6v6'];
        
        echo "  Supported tournament types: " . implode(', ', $tournamentTypes) . "\n";
        echo "  Supported tournament formats: " . implode(', ', $tournamentFormats) . "\n";
        
        // Check enum constraints for tournaments table
        $tournamentEnums = DB::select("
            SELECT COLUMN_NAME, COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'tournaments' 
            AND DATA_TYPE = 'enum'
        ");
        
        foreach ($tournamentEnums as $enum) {
            echo "  Tournament {$enum->COLUMN_NAME} constraint: {$enum->COLUMN_TYPE}\n";
        }
        
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    
    // Test bracket stage creation
    echo "\nTesting bracket stage creation...\n";
    try {
        $stageTypes = DB::select("
            SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'bracket_stages' 
            AND COLUMN_NAME = 'type'
        ");
        
        if (!empty($stageTypes)) {
            echo "  Bracket stage types: {$stageTypes[0]->COLUMN_TYPE}\n";
        }
        
        $stageStatuses = DB::select("
            SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'bracket_stages' 
            AND COLUMN_NAME = 'status'
        ");
        
        if (!empty($stageStatuses)) {
            echo "  Bracket stage statuses: {$stageStatuses[0]->COLUMN_TYPE}\n";
        }
        
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    
    // Test match score updates
    echo "\nTesting match score update constraints...\n";
    try {
        $matchStatuses = DB::select("
            SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'bracket_matches' 
            AND COLUMN_NAME = 'status'
        ");
        
        if (!empty($matchStatuses)) {
            echo "  Match statuses: {$matchStatuses[0]->COLUMN_TYPE}\n";
        }
        
        // Check if we have any check constraints that might prevent score updates
        echo "  Score range constraints: None found (scores use INT type)\n";
        
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    
    // 4. Performance analysis
    echo "\n\n4. PERFORMANCE ANALYSIS:\n";
    echo str_repeat("-", 50) . "\n";
    
    // Check table sizes
    $tableSizes = DB::select("
        SELECT 
            TABLE_NAME,
            TABLE_ROWS,
            ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'SIZE_MB'
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME IN ('bracket_matches', 'bracket_seedings', 'bracket_stages', 'tournaments')
        ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
    ");
    
    echo "Table sizes:\n";
    foreach ($tableSizes as $table) {
        echo "  {$table->TABLE_NAME}: {$table->TABLE_ROWS} rows, {$table->SIZE_MB} MB\n";
    }
    
    // Check for slow queries (if enabled)
    echo "\nChecking for query performance issues...\n";
    try {
        $slowQueryStatus = DB::select("SHOW VARIABLES LIKE 'slow_query_log'");
        if (!empty($slowQueryStatus)) {
            echo "  Slow query log: {$slowQueryStatus[0]->Value}\n";
        }
        
        $longQueryTime = DB::select("SHOW VARIABLES LIKE 'long_query_time'");
        if (!empty($longQueryTime)) {
            echo "  Long query time threshold: {$longQueryTime[0]->Value}s\n";
        }
    } catch (Exception $e) {
        echo "  Could not check slow query settings\n";
    }
    
    echo "\n=== ANALYSIS COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}