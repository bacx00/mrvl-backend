<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "Starting fast database cleanup...\n";
    
    // Disable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    
    // Truncate tables
    DB::table('player_team_histories')->truncate();
    DB::table('players')->truncate();
    DB::table('teams')->truncate();
    
    // Re-enable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    
    echo "Database cleanup completed successfully!\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}