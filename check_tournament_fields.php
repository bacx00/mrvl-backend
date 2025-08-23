<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== CHECKING TOURNAMENTS TABLE STRUCTURE ===\n\n";
    
    $columns = DB::select('DESCRIBE tournaments');
    
    echo "Required fields (NOT NULL with no default):\n";
    echo str_repeat("-", 50) . "\n";
    
    $requiredFields = [];
    
    foreach ($columns as $col) {
        if ($col->Null === 'NO' && $col->Default === null && $col->Extra !== 'auto_increment') {
            echo "- {$col->Field} ({$col->Type})\n";
            $requiredFields[] = $col->Field;
        }
    }
    
    echo "\nAll fields with types and defaults:\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($columns as $col) {
        $defaultVal = $col->Default === null ? 'NULL' : $col->Default;
        $nullable = $col->Null === 'YES' ? 'NULL' : 'NOT NULL';
        echo "{$col->Field}: {$col->Type} {$nullable} DEFAULT {$defaultVal}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}