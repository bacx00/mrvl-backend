<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;

// Initialize Laravel if needed
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "=== CHECKING BRACKET_MATCHES TABLE SCHEMA ===\n\n";
    
    // Get table schema
    $tableInfo = DB::select("DESCRIBE bracket_matches");
    
    echo "Current bracket_matches table structure:\n";
    echo str_pad("Field", 20) . str_pad("Type", 25) . str_pad("Null", 6) . str_pad("Key", 6) . str_pad("Default", 15) . "Extra\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($tableInfo as $column) {
        echo str_pad($column->Field, 20) . 
             str_pad($column->Type, 25) . 
             str_pad($column->Null, 6) . 
             str_pad($column->Key, 6) . 
             str_pad($column->Default ?? 'NULL', 15) . 
             $column->Extra . "\n";
    }
    
    echo "\n=== CHECKING BEST_OF COLUMN CONSTRAINT ===\n\n";
    
    // Check specific constraint for best_of column
    $constraintInfo = DB::select("
        SELECT COLUMN_TYPE, COLUMN_DEFAULT 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'bracket_matches' 
        AND COLUMN_NAME = 'best_of'
    ");
    
    if (!empty($constraintInfo)) {
        $constraint = $constraintInfo[0];
        echo "best_of column type: " . $constraint->COLUMN_TYPE . "\n";
        echo "best_of default value: " . ($constraint->COLUMN_DEFAULT ?? 'NULL') . "\n";
        
        // Parse enum values
        if (strpos($constraint->COLUMN_TYPE, 'enum') !== false) {
            preg_match("/enum\((.+)\)/", $constraint->COLUMN_TYPE, $matches);
            if (isset($matches[1])) {
                $enumValues = str_replace("'", "", $matches[1]);
                echo "Allowed values: " . $enumValues . "\n";
                
                // Check if 5 and 7 are supported
                $allowedValues = explode(',', $enumValues);
                $hasAllValues = in_array('1', $allowedValues) && in_array('3', $allowedValues) && 
                               in_array('5', $allowedValues) && in_array('7', $allowedValues);
                
                echo "Supports all required values (1,3,5,7): " . ($hasAllValues ? "YES" : "NO") . "\n";
                
                if (!$hasAllValues) {
                    echo "\nMISSING VALUES:\n";
                    $requiredValues = ['1', '3', '5', '7'];
                    foreach ($requiredValues as $val) {
                        if (!in_array($val, $allowedValues)) {
                            echo "- Missing: $val\n";
                        }
                    }
                }
            }
        }
    }
    
    echo "\n=== CHECKING INDEXES ===\n\n";
    
    // Check existing indexes
    $indexes = DB::select("SHOW INDEX FROM bracket_matches");
    
    echo "Current indexes on bracket_matches:\n";
    foreach ($indexes as $index) {
        echo "- " . $index->Key_name . " (" . $index->Column_name . ")\n";
    }
    
    echo "\n=== CHECKING BRACKET_SEEDINGS TABLE ===\n\n";
    
    // Check if bracket_seedings table exists
    $seedings_exists = DB::select("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'bracket_seedings'
    ")[0]->count > 0;
    
    if ($seedings_exists) {
        echo "bracket_seedings table exists\n";
        
        $seedingIndexes = DB::select("SHOW INDEX FROM bracket_seedings");
        echo "Current indexes on bracket_seedings:\n";
        foreach ($seedingIndexes as $index) {
            echo "- " . $index->Key_name . " (" . $index->Column_name . ")\n";
        }
    } else {
        echo "bracket_seedings table does NOT exist\n";
    }
    
    echo "\n=== TESTING CONSTRAINT ===\n\n";
    
    // Test if we can insert/update with values 5 and 7
    echo "Testing best_of constraint with value 5...\n";
    try {
        DB::statement("SET foreign_key_checks = 0");
        $testResult = DB::select("
            SELECT 1 as test 
            WHERE '5' IN (
                SELECT SUBSTRING(COLUMN_TYPE, 6, LENGTH(COLUMN_TYPE) - 6) 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = 'bracket_matches' 
                AND COLUMN_NAME = 'best_of'
            )
        ");
        DB::statement("SET foreign_key_checks = 1");
        
        echo "Value 5 test result: " . (empty($testResult) ? "NOT SUPPORTED" : "SUPPORTED") . "\n";
    } catch (Exception $e) {
        echo "Error testing constraint: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}