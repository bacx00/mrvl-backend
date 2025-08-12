<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Checking Users Table Schema\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // Get table schema
    $columns = DB::select("SHOW COLUMNS FROM users");
    
    echo "Users Table Columns:\n";
    echo "-" . str_repeat("-", 80) . "\n";
    printf("%-20s %-20s %-10s %-10s %-10s %-20s\n", 
           "Field", "Type", "Null", "Key", "Default", "Extra");
    echo "-" . str_repeat("-", 80) . "\n";
    
    foreach ($columns as $column) {
        printf("%-20s %-20s %-10s %-10s %-10s %-20s\n", 
               $column->Field, 
               $column->Type, 
               $column->Null, 
               $column->Key, 
               $column->Default ?: 'NULL', 
               $column->Extra ?: '-');
    }
    
    echo "\n\n";
    
    // Check for enum values specifically
    $statusColumn = collect($columns)->firstWhere('Field', 'status');
    if ($statusColumn) {
        echo "Status Column Details:\n";
        echo "Type: " . $statusColumn->Type . "\n";
        echo "Null: " . $statusColumn->Null . "\n";
        echo "Default: " . ($statusColumn->Default ?: 'NULL') . "\n\n";
        
        // Extract enum values if it's an enum
        if (strpos($statusColumn->Type, 'enum') === 0) {
            preg_match_all("/'([^']+)'/", $statusColumn->Type, $matches);
            echo "Allowed Status Values: " . implode(', ', $matches[1]) . "\n\n";
        }
    }
    
    $roleColumn = collect($columns)->firstWhere('Field', 'role');
    if ($roleColumn) {
        echo "Role Column Details:\n";
        echo "Type: " . $roleColumn->Type . "\n";
        echo "Null: " . $roleColumn->Null . "\n";
        echo "Default: " . ($roleColumn->Default ?: 'NULL') . "\n\n";
        
        // Extract enum values if it's an enum
        if (strpos($roleColumn->Type, 'enum') === 0) {
            preg_match_all("/'([^']+)'/", $roleColumn->Type, $matches);
            echo "Allowed Role Values: " . implode(', ', $matches[1]) . "\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}