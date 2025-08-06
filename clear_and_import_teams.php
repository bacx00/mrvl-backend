<?php

require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerTeamHistory;

$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '172.30.240.1',
    'database' => 'mrvl_database',
    'username' => 'mrvl_user',
    'password' => 'SecurePassword123!',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "Starting database cleanup...\n";
    
    // Disable foreign key checks
    Capsule::statement('SET FOREIGN_KEY_CHECKS = 0');
    
    // Clear all player team history
    PlayerTeamHistory::truncate();
    echo "Cleared player team history\n";
    
    // Clear all players
    Player::truncate();
    echo "Cleared all players\n";
    
    // Clear all teams
    Team::truncate();
    echo "Cleared all teams\n";
    
    // Re-enable foreign key checks
    Capsule::statement('SET FOREIGN_KEY_CHECKS = 1');
    
    echo "\nDatabase cleanup completed successfully!\n";
    echo "Ready to import new data from Liquipedia\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}