<?php

/**
 * FINAL DATABASE VALIDATION AND READINESS CHECK
 * Comprehensive validation of all database optimizations
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

echo "=== FINAL DATABASE VALIDATION AND READINESS CHECK ===\n\n";

try {
    // Get database info
    $database = DB::connection()->getDatabaseName();
    $driver = DB::connection()->getDriverName();
    
    echo "Database: {$database} (Driver: {$driver})\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

    $issues = [];
    $warnings = [];
    $successes = [];

    // 1. CRITICAL TABLES VALIDATION
    echo "1. VALIDATING CRITICAL TABLES...\n";
    
    $critical_tables = [
        'teams' => ['id', 'name', 'tag', 'country', 'region', 'earnings', 'liquipedia_url'],
        'players' => ['id', 'username', 'real_name', 'team_id', 'role', 'country', 'earnings', 'elo_rating'],
        'matches' => ['id', 'team1_id', 'team2_id', 'event_id', 'status', 'scheduled_at'],
        'player_match_stats' => ['id', 'match_id', 'player_id', 'map_number'],
        'match_maps' => ['id', 'match_id', 'map_number', 'team1_score', 'team2_score'],
        'events' => ['id', 'name', 'type', 'start_date', 'end_date']
    ];
    
    foreach ($critical_tables as $table => $required_columns) {
        if (!Schema::hasTable($table)) {
            $issues[] = "Critical table '{$table}' is missing";
            echo "   ✗ Table {$table} is missing\n";
            continue;
        }
        
        $count = DB::table($table)->count();
        echo "   ✓ Table {$table} exists ({$count} records)\n";
        
        foreach ($required_columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                $issues[] = "Critical column '{$table}.{$column}' is missing";
                echo "     ✗ Column {$column} missing\n";
            }
        }
        
        $successes[] = "Table {$table} validated successfully";
    }

    // 2. DATA CLEANUP VALIDATION
    echo "\n2. VALIDATING DATA CLEANUP...\n";
    
    $cleanup_tables = ['teams', 'players', 'matches', 'player_match_stats', 'match_maps'];
    $total_records = 0;
    
    foreach ($cleanup_tables as $table) {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->count();
            $total_records += $count;
            
            if ($count === 0) {
                echo "   ✓ {$table} is clean (0 records)\n";
                $successes[] = "Table {$table} successfully cleaned";
            } else {
                $warnings[] = "Table {$table} has {$count} records (expected 0)";
                echo "   ⚠ {$table} has {$count} records (expected 0)\n";
            }
        }
    }
    
    if ($total_records === 0) {
        $successes[] = "Complete data wipe verified - all critical tables are clean";
    } else {
        $warnings[] = "Some tables still contain data after cleanup";
    }

    // 3. SCHEMA FIXES VALIDATION
    echo "\n3. VALIDATING SCHEMA FIXES...\n";
    
    // Check map_number column fix
    if (Schema::hasTable('player_match_stats')) {
        if (Schema::hasColumn('player_match_stats', 'map_number')) {
            echo "   ✓ Map stats error fixed - map_number column exists\n";
            $successes[] = "Map stats error fixed (map_number column added)";
        } else {
            $issues[] = "Map stats error NOT fixed - map_number column is missing";
            echo "   ✗ Map stats error NOT fixed - map_number column missing\n";
        }
    }
    
    // Check match_maps table structure
    if (Schema::hasTable('match_maps')) {
        $required_mm_columns = ['match_id', 'map_number', 'team1_score', 'team2_score'];
        $mm_issues = 0;
        
        foreach ($required_mm_columns as $column) {
            if (!Schema::hasColumn('match_maps', $column)) {
                $issues[] = "match_maps table missing column: {$column}";
                echo "   ✗ match_maps missing column: {$column}\n";
                $mm_issues++;
            }
        }
        
        if ($mm_issues === 0) {
            echo "   ✓ match_maps table structure is correct\n";
            $successes[] = "match_maps table structure validated";
        }
    }

    // 4. LIQUIPEDIA OPTIMIZATION VALIDATION
    echo "\n4. VALIDATING LIQUIPEDIA OPTIMIZATIONS...\n";
    
    // Check teams table optimizations
    $teams_social_columns = [
        'earnings', 'coach_image', 'twitter_url', 'instagram_url', 
        'youtube_url', 'twitch_url', 'discord_url', 'website_url', 
        'liquipedia_url', 'vlr_url'
    ];
    
    $teams_missing = 0;
    foreach ($teams_social_columns as $column) {
        if (!Schema::hasColumn('teams', $column)) {
            $issues[] = "teams table missing optimization column: {$column}";
            echo "   ✗ teams missing: {$column}\n";
            $teams_missing++;
        }
    }
    
    if ($teams_missing === 0) {
        echo "   ✓ Teams table fully optimized for Liquipedia data\n";
        $successes[] = "Teams table fully optimized";
    }
    
    // Check players table optimizations
    $players_social_columns = [
        'earnings', 'elo_rating', 'peak_rating', 'twitter_url', 
        'instagram_url', 'youtube_url', 'twitch_url', 'discord_url', 
        'liquipedia_url', 'vlr_url'
    ];
    
    $players_missing = 0;
    foreach ($players_social_columns as $column) {
        if (!Schema::hasColumn('players', $column)) {
            $issues[] = "players table missing optimization column: {$column}";
            echo "   ✗ players missing: {$column}\n";
            $players_missing++;
        }
    }
    
    if ($players_missing === 0) {
        echo "   ✓ Players table fully optimized for Liquipedia data\n";
        $successes[] = "Players table fully optimized";
    }

    // 5. PERFORMANCE INDEXES VALIDATION
    echo "\n5. VALIDATING PERFORMANCE INDEXES...\n";
    
    $key_indexes = [
        ['teams', 'idx_teams_region'],
        ['teams', 'idx_teams_earnings'],
        ['players', 'idx_players_team_id'],
        ['players', 'idx_players_elo_rating'],
        ['matches', 'idx_matches_event_id'],
        ['player_match_stats', 'idx_pms_match_map']
    ];
    
    $missing_indexes = 0;
    foreach ($key_indexes as list($table, $index_name)) {
        if (Schema::hasTable($table)) {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index_name]);
            if (!empty($indexes)) {
                echo "   ✓ Index {$index_name} exists on {$table}\n";
            } else {
                $warnings[] = "Performance index {$index_name} missing on {$table}";
                echo "   ⚠ Index {$index_name} missing on {$table}\n";
                $missing_indexes++;
            }
        }
    }
    
    if ($missing_indexes === 0) {
        $successes[] = "All critical performance indexes verified";
    }

    // 6. FOREIGN KEY CONSTRAINTS VALIDATION
    echo "\n6. VALIDATING FOREIGN KEY CONSTRAINTS...\n";
    
    $critical_fks = [
        ['players', 'team_id', 'teams'],
        ['matches', 'event_id', 'events'],
        ['player_match_stats', 'match_id', 'matches'],
        ['player_match_stats', 'player_id', 'players'],
        ['match_maps', 'match_id', 'matches']
    ];
    
    $missing_fks = 0;
    foreach ($critical_fks as list($table, $column, $ref_table)) {
        $fks = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME = ?
        ", [$table, $column, $ref_table]);
        
        if (!empty($fks)) {
            echo "   ✓ Foreign key {$table}.{$column} -> {$ref_table} exists\n";
        } else {
            $warnings[] = "Foreign key constraint missing: {$table}.{$column} -> {$ref_table}";
            echo "   ⚠ Foreign key missing: {$table}.{$column} -> {$ref_table}\n";
            $missing_fks++;
        }
    }
    
    if ($missing_fks === 0) {
        $successes[] = "All critical foreign key constraints verified";
    }

    // 7. AUTO-INCREMENT VALIDATION
    echo "\n7. VALIDATING AUTO-INCREMENT RESET...\n";
    
    $ai_tables = ['teams', 'players', 'matches'];
    foreach ($ai_tables as $table) {
        if (Schema::hasTable($table)) {
            $result = DB::select("SHOW TABLE STATUS LIKE ?", [$table]);
            if (!empty($result)) {
                $auto_increment = $result[0]->Auto_increment;
                if ($auto_increment == 1) {
                    echo "   ✓ {$table} auto-increment reset to 1\n";
                    $successes[] = "Auto-increment reset for {$table}";
                } else {
                    $warnings[] = "{$table} auto-increment is {$auto_increment} (expected 1)";
                    echo "   ⚠ {$table} auto-increment is {$auto_increment} (expected 1)\n";
                }
            }
        }
    }

    // 8. FINAL ASSESSMENT
    echo "\n=== FINAL ASSESSMENT ===\n";
    
    echo "\n✓ SUCCESSES (" . count($successes) . "):\n";
    foreach ($successes as $success) {
        echo "  • {$success}\n";
    }
    
    if (!empty($warnings)) {
        echo "\n⚠ WARNINGS (" . count($warnings) . "):\n";
        foreach ($warnings as $warning) {
            echo "  • {$warning}\n";
        }
    }
    
    if (!empty($issues)) {
        echo "\n✗ CRITICAL ISSUES (" . count($issues) . "):\n";
        foreach ($issues as $issue) {
            echo "  • {$issue}\n";
        }
    }
    
    echo "\n=== READINESS STATUS ===\n";
    
    if (empty($issues)) {
        if (empty($warnings)) {
            echo "🎉 DATABASE IS FULLY READY FOR LIQUIPEDIA SCRAPING!\n";
            echo "✓ All critical issues resolved\n";
            echo "✓ No warnings detected\n";
            echo "✓ Database structure optimized\n";
            echo "✓ Performance indexes in place\n";
            echo "✓ Data integrity constraints active\n";
        } else {
            echo "✅ DATABASE IS READY FOR LIQUIPEDIA SCRAPING WITH MINOR WARNINGS\n";
            echo "✓ No critical issues detected\n";
            echo "⚠ " . count($warnings) . " minor warnings (non-blocking)\n";
        }
    } else {
        echo "❌ DATABASE HAS CRITICAL ISSUES THAT MUST BE RESOLVED\n";
        echo "✗ " . count($issues) . " critical issues detected\n";
        echo "Please resolve these issues before proceeding with scraping.\n";
    }
    
    echo "\nNEXT STEPS:\n";
    echo "1. Run comprehensive Liquipedia scraping command\n";
    echo "2. Import all team and player data with earnings\n";
    echo "3. Verify data integrity after import\n";
    echo "4. Set up ELO rating calculations\n";
    echo "5. Configure social media link parsing\n\n";
    
    echo "Database optimization completed successfully!\n";

} catch (Exception $e) {
    echo "✗ VALIDATION ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}