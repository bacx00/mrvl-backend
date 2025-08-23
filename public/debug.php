<?php
/**
 * Simple debug script to check Laravel bootstrap
 */

try {
    echo "🔍 Laravel Debug Script\n";
    echo "=====================\n\n";
    
    // Check if Laravel can bootstrap
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "✅ Autoloader loaded\n";
    
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "✅ Laravel app bootstrapped\n";
    
    // Test database connection
    $app->make(\Illuminate\Contracts\Http\Kernel::class);
    echo "✅ HTTP Kernel created\n";
    
    // Try to load a model
    $teamModel = new \App\Models\Team();
    echo "✅ Team model loaded\n";
    
    // Try a simple database query
    $teamCount = \App\Models\Team::count();
    echo "✅ Database query successful - Teams: $teamCount\n";
    
    // Test controller instantiation
    $controller = new \App\Http\Controllers\Admin\AdminTeamController();
    echo "✅ AdminTeamController instantiated\n";
    
    echo "\n🎉 All basic checks passed!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (\Error $e) {
    echo "💥 Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}