<?php
/**
 * Quick fix script to update peak_rating to current rating where peak_rating is 0
 * This ensures that peak_rating is always at least equal to current rating
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Initialize Laravel application context
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Starting peak rating fix...\n";

try {
    // Update players where peak_rating is null or less than current rating
    $playersUpdated = DB::table('players')
        ->where(function($query) {
            $query->whereNull('peak_rating')
                  ->orWhereColumn('peak_rating', '<', 'rating');
        })
        ->update([
            'peak_rating' => DB::raw('rating'),
            'updated_at' => now()
        ]);
    
    echo "Updated peak_rating for {$playersUpdated} players\n";
    
    // Update teams where peak_elo is null or less than current elo_rating
    $teamsUpdated = DB::table('teams')
        ->where(function($query) {
            $query->whereNull('peak_elo')
                  ->orWhereColumn('peak_elo', '<', 'elo_rating');
        })
        ->update([
            'peak_elo' => DB::raw('COALESCE(elo_rating, rating, 1000)'),
            'updated_at' => now()
        ]);
    
    echo "Updated peak_elo for {$teamsUpdated} teams\n";
    
    // Clear ranking caches
    DB::statement("DELETE FROM cache WHERE `key` LIKE '%ranking%'");
    echo "Cleared ranking caches\n";
    
    echo "Peak rating fix completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}