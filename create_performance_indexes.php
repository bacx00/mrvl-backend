<?php

/**
 * CREATE PERFORMANCE INDEXES
 * Proper MySQL index creation without IF NOT EXISTS
 */

// Change to the Laravel directory
chdir('/var/www/mrvl-backend');

// Include Laravel bootstrap
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== CREATING PERFORMANCE INDEXES ===\n\n";

try {
    // Helper function to check if index exists
    function indexExists($table, $indexName) {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return !empty($indexes);
    }
    
    // Define indexes to create
    $indexes_to_create = [
        ['table' => 'teams', 'column' => 'region', 'name' => 'idx_teams_region'],
        ['table' => 'teams', 'column' => 'country', 'name' => 'idx_teams_country'],
        ['table' => 'teams', 'column' => 'status', 'name' => 'idx_teams_status'],
        ['table' => 'teams', 'column' => 'earnings', 'name' => 'idx_teams_earnings'],
        ['table' => 'players', 'column' => 'team_id', 'name' => 'idx_players_team_id'],
        ['table' => 'players', 'column' => 'role', 'name' => 'idx_players_role'],
        ['table' => 'players', 'column' => 'country', 'name' => 'idx_players_country'],
        ['table' => 'players', 'column' => 'status', 'name' => 'idx_players_status'],
        ['table' => 'players', 'column' => 'earnings', 'name' => 'idx_players_earnings'],
        ['table' => 'players', 'column' => 'elo_rating', 'name' => 'idx_players_elo_rating'],
        ['table' => 'matches', 'column' => 'event_id', 'name' => 'idx_matches_event_id'],
        ['table' => 'matches', 'column' => 'status', 'name' => 'idx_matches_status'],
        ['table' => 'matches', 'column' => 'scheduled_at', 'name' => 'idx_matches_scheduled_at'],
        ['table' => 'player_match_stats', 'column' => 'match_id', 'name' => 'idx_pms_match_id'],
        ['table' => 'player_match_stats', 'column' => 'player_id', 'name' => 'idx_pms_player_id'],
        ['table' => 'player_match_stats', 'column' => 'hero_id', 'name' => 'idx_pms_hero_id'],
        ['table' => 'match_maps', 'column' => 'match_id', 'name' => 'idx_mm_match_id'],
        ['table' => 'match_maps', 'column' => 'status', 'name' => 'idx_mm_status'],
        ['table' => 'match_maps', 'column' => 'winner_team_id', 'name' => 'idx_mm_winner'],
    ];
    
    // Composite indexes
    $composite_indexes = [
        ['table' => 'player_match_stats', 'columns' => 'match_id, map_number', 'name' => 'idx_pms_match_map'],
        ['table' => 'player_match_stats', 'columns' => 'player_id, match_id', 'name' => 'idx_pms_player_match'],
        ['table' => 'match_maps', 'columns' => 'match_id, map_number', 'name' => 'idx_mm_match_map'],
    ];
    
    $created_count = 0;
    $skipped_count = 0;
    
    // Create single column indexes
    foreach ($indexes_to_create as $index) {
        $table = $index['table'];
        $column = $index['column'];
        $name = $index['name'];
        
        if (!Schema::hasTable($table)) {
            echo "   - Table {$table} does not exist, skipping\n";
            continue;
        }
        
        if (!Schema::hasColumn($table, $column)) {
            echo "   - Column {$table}.{$column} does not exist, skipping\n";
            continue;
        }
        
        if (indexExists($table, $name)) {
            echo "   - Index {$name} already exists on {$table}\n";
            $skipped_count++;
            continue;
        }
        
        try {
            DB::statement("CREATE INDEX {$name} ON {$table} ({$column})");
            echo "   ✓ Created index {$name} on {$table}.{$column}\n";
            $created_count++;
        } catch (Exception $e) {
            echo "   ✗ Failed to create index {$name}: " . $e->getMessage() . "\n";
        }
    }
    
    // Create composite indexes
    foreach ($composite_indexes as $index) {
        $table = $index['table'];
        $columns = $index['columns'];
        $name = $index['name'];
        
        if (!Schema::hasTable($table)) {
            echo "   - Table {$table} does not exist, skipping composite index\n";
            continue;
        }
        
        if (indexExists($table, $name)) {
            echo "   - Composite index {$name} already exists on {$table}\n";
            $skipped_count++;
            continue;
        }
        
        try {
            DB::statement("CREATE INDEX {$name} ON {$table} ({$columns})");
            echo "   ✓ Created composite index {$name} on {$table} ({$columns})\n";
            $created_count++;
        } catch (Exception $e) {
            echo "   ✗ Failed to create composite index {$name}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== INDEX CREATION COMPLETED ===\n";
    echo "Created: {$created_count} indexes\n";
    echo "Skipped: {$skipped_count} indexes (already existed)\n\n";
    
    // Verify some key indexes
    echo "=== VERIFYING KEY INDEXES ===\n";
    $key_indexes = [
        ['teams', 'idx_teams_region'],
        ['players', 'idx_players_team_id'],
        ['matches', 'idx_matches_event_id'],
        ['player_match_stats', 'idx_pms_match_map']
    ];
    
    foreach ($key_indexes as list($table, $indexName)) {
        if (Schema::hasTable($table)) {
            if (indexExists($table, $indexName)) {
                echo "   ✓ Verified index {$indexName} exists on {$table}\n";
            } else {
                echo "   ✗ Index {$indexName} missing on {$table}\n";
            }
        }
    }
    
    echo "\nPerformance indexes setup completed!\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}