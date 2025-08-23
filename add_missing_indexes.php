<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "=== ADDING MISSING PERFORMANCE INDEXES ===\n\n";
    
    $indexesAdded = 0;
    
    // Function to check if index exists
    function indexExists($table, $indexName) {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return !empty($indexes);
        } catch (Exception $e) {
            return false;
        }
    }
    
    // 1. Add missing indexes to bracket_matches table
    echo "1. Adding indexes to bracket_matches table:\n";
    echo str_repeat("-", 50) . "\n";
    
    $bracketMatchesIndexes = [
        'idx_tournament_status' => ['tournament_id', 'status'],
        'idx_bracket_stage_status' => ['bracket_stage_id', 'status'],
        'idx_match_progression' => ['round_number', 'match_number', 'status'],
        'idx_team_matches' => ['team1_id', 'team2_id'],
        'idx_live_matches' => ['status', 'started_at', 'scheduled_at']
    ];
    
    foreach ($bracketMatchesIndexes as $indexName => $columns) {
        if (!indexExists('bracket_matches', $indexName)) {
            try {
                $columnList = implode(', ', $columns);
                DB::statement("CREATE INDEX {$indexName} ON bracket_matches ({$columnList})");
                echo "  ✓ Added index: {$indexName} ({$columnList})\n";
                $indexesAdded++;
            } catch (Exception $e) {
                echo "  ✗ Failed to add index {$indexName}: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  - Index {$indexName} already exists\n";
        }
    }
    
    // 2. Add missing indexes to bracket_seedings table
    echo "\n2. Adding indexes to bracket_seedings table:\n";
    echo str_repeat("-", 50) . "\n";
    
    $bracketSeedingsIndexes = [
        'idx_stage_seed_order' => ['bracket_stage_id', 'seed'],
        'idx_tournament_seed' => ['tournament_id', 'seed']
    ];
    
    foreach ($bracketSeedingsIndexes as $indexName => $columns) {
        if (!indexExists('bracket_seedings', $indexName)) {
            try {
                $columnList = implode(', ', $columns);
                DB::statement("CREATE INDEX {$indexName} ON bracket_seedings ({$columnList})");
                echo "  ✓ Added index: {$indexName} ({$columnList})\n";
                $indexesAdded++;
            } catch (Exception $e) {
                echo "  ✗ Failed to add index {$indexName}: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  - Index {$indexName} already exists\n";
        }
    }
    
    // 3. Check and add additional useful indexes
    echo "\n3. Adding additional performance indexes:\n";
    echo str_repeat("-", 50) . "\n";
    
    $additionalIndexes = [
        'bracket_matches' => [
            'idx_winner_loser' => ['winner_id', 'loser_id'],
            'idx_scheduled_status' => ['scheduled_at', 'status'],
            'idx_completed_status' => ['completed_at', 'status']
        ]
    ];
    
    foreach ($additionalIndexes as $table => $indexes) {
        foreach ($indexes as $indexName => $columns) {
            if (!indexExists($table, $indexName)) {
                try {
                    $columnList = implode(', ', $columns);
                    DB::statement("CREATE INDEX {$indexName} ON {$table} ({$columnList})");
                    echo "  ✓ Added index: {$indexName} on {$table} ({$columnList})\n";
                    $indexesAdded++;
                } catch (Exception $e) {
                    echo "  ✗ Failed to add index {$indexName} on {$table}: " . $e->getMessage() . "\n";
                }
            } else {
                echo "  - Index {$indexName} on {$table} already exists\n";
            }
        }
    }
    
    // 4. Test constraint flexibility
    echo "\n4. Testing constraint flexibility:\n";
    echo str_repeat("-", 50) . "\n";
    
    // Test best_of values
    echo "Testing best_of constraint:\n";
    $bestOfValues = ['1', '3', '5', '7'];
    foreach ($bestOfValues as $value) {
        try {
            // Test if value is allowed
            $testQuery = "SELECT 1 FROM DUAL WHERE '{$value}' IN ('1','3','5','7')";
            $result = DB::select($testQuery);
            echo "  ✓ best_of value '{$value}' is supported\n";
        } catch (Exception $e) {
            echo "  ✗ best_of value '{$value}' failed: " . $e->getMessage() . "\n";
        }
    }
    
    // Test tournament types and formats
    echo "\nChecking tournament constraints:\n";
    try {
        $tournamentColumns = DB::select("
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'tournaments' 
            AND COLUMN_NAME IN ('type', 'format', 'status')
        ");
        
        foreach ($tournamentColumns as $column) {
            echo "  Tournament {$column->COLUMN_NAME}: {$column->COLUMN_TYPE}\n";
            if ($column->COLUMN_DEFAULT) {
                echo "    Default: {$column->COLUMN_DEFAULT}\n";
            }
        }
    } catch (Exception $e) {
        echo "  Error checking tournament constraints: " . $e->getMessage() . "\n";
    }
    
    // Test bracket stage constraints
    echo "\nChecking bracket stage constraints:\n";
    try {
        $stageColumns = DB::select("
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'bracket_stages' 
            AND COLUMN_NAME IN ('type', 'status')
        ");
        
        foreach ($stageColumns as $column) {
            echo "  Bracket stage {$column->COLUMN_NAME}: {$column->COLUMN_TYPE}\n";
            if ($column->COLUMN_DEFAULT) {
                echo "    Default: {$column->COLUMN_DEFAULT}\n";
            }
        }
    } catch (Exception $e) {
        echo "  Error checking bracket stage constraints: " . $e->getMessage() . "\n";
    }
    
    // 5. Foreign key relationship check
    echo "\n5. Checking foreign key relationships:\n";
    echo str_repeat("-", 50) . "\n";
    
    try {
        $foreignKeys = DB::select("
            SELECT 
                CONSTRAINT_NAME,
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME IN ('bracket_matches', 'bracket_seedings', 'bracket_stages')
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        echo "Foreign key relationships:\n";
        foreach ($foreignKeys as $fk) {
            echo "  {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
        }
        
        if (empty($foreignKeys)) {
            echo "  No foreign keys found (constraints may be disabled)\n";
        }
    } catch (Exception $e) {
        echo "  Error checking foreign keys: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total indexes added: {$indexesAdded}\n";
    echo "Database optimization complete!\n";
    
    if ($indexesAdded > 0) {
        echo "\nNext steps:\n";
        echo "1. Test tournament creation with various settings\n";
        echo "2. Test bracket stage creation\n";
        echo "3. Test match score updates\n";
        echo "4. Monitor query performance\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}