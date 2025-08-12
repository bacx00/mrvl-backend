<?php
/**
 * Simple database connection test
 */

try {
    // Test if we can access the Laravel environment
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    
    echo "✓ Laravel environment loaded successfully\n";
    
    // Test database connection
    $config = config('database.default');
    echo "✓ Database connection type: {$config}\n";
    
    // Test if we can access database
    $pdo = DB::connection()->getPdo();
    echo "✓ Database connection established\n";
    
    // Test basic query
    $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
    echo "✓ Found " . count($tables) . " tables in database\n";
    
    // List tables
    foreach ($tables as $table) {
        echo "  - {$table->name}\n";
    }
    
    echo "\nDatabase connection test completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}