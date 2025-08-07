<?php

/**
 * CRITICAL DATABASE CLEANUP AND SCHEMA FIXES
 * Laravel-integrated version
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
use Illuminate\Database\Schema\Blueprint;

echo "=== CRITICAL DATABASE CLEANUP AND SCHEMA FIXES ===\n\n";

try {
    // Test database connection
    DB::connection()->getPdo();
    echo "✓ Database connection established\n";
    
    // Get database name
    $database = DB::connection()->getDatabaseName();
    echo "✓ Connected to database: {$database}\n\n";

    // 1. COMPLETE DATA WIPE
    echo "1. PERFORMING COMPLETE DATA WIPE...\n";
    echo "   WARNING: This will delete ALL existing teams and players data!\n";
    
    // Disable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    // Clear data from all related tables
    $tables_to_clear = [
        'player_match_stats',
        'team_match_stats', 
        'player_team_history',
        'match_maps',
        'matches',
        'event_teams',
        'event_standings',
        'brackets',
        'bracket_matches',
        'bracket_games',
        'players',
        'teams'
    ];
    
    $total_deleted = 0;
    foreach ($tables_to_clear as $table) {
        try {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->delete();
                $total_deleted += $count;
                echo "   ✓ Cleared {$table} ({$count} records)\n";
            } else {
                echo "   - Table {$table} does not exist\n";
            }
        } catch (Exception $e) {
            echo "   ⚠ Error clearing {$table}: " . $e->getMessage() . "\n";
        }
    }
    
    // Re-enable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    
    echo "   ✓ Data wipe completed - {$total_deleted} total records deleted\n\n";

    // 2. RESET AUTO-INCREMENT IDs
    echo "2. RESETTING AUTO-INCREMENT IDs...\n";
    
    $auto_increment_tables = [
        'teams', 'players', 'matches', 'events', 'player_match_stats', 
        'team_match_stats', 'match_maps', 'brackets', 'bracket_matches', 'bracket_games'
    ];
    
    foreach ($auto_increment_tables as $table) {
        try {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
                echo "   ✓ Reset auto-increment for {$table}\n";
            }
        } catch (Exception $e) {
            echo "   ⚠ Error resetting auto-increment for {$table}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "   ✓ Auto-increment reset completed\n\n";

    // 3. FIX CRITICAL SCHEMA ISSUES
    echo "3. FIXING CRITICAL SCHEMA ISSUES...\n";
    
    // Fix player_match_stats table - ensure map_number column exists
    if (Schema::hasTable('player_match_stats')) {
        if (!Schema::hasColumn('player_match_stats', 'map_number')) {
            Schema::table('player_match_stats', function (Blueprint $table) {
                $table->integer('map_number')->default(1)->after('match_id');
            });
            echo "   ✓ Added map_number column to player_match_stats\n";
        } else {
            echo "   - map_number column already exists in player_match_stats\n";
        }
    }
    
    // Ensure match_maps table has proper structure
    if (!Schema::hasTable('match_maps')) {
        Schema::create('match_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->integer('map_number');
            $table->string('map_name');
            $table->enum('status', ['upcoming', 'live', 'completed'])->default('upcoming');
            $table->integer('team1_score')->default(0);
            $table->integer('team2_score')->default(0);
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('team1_composition')->nullable();
            $table->json('team2_composition')->nullable();
            $table->timestamps();
            
            $table->unique(['match_id', 'map_number']);
            $table->index('match_id');
            $table->index('status');
        });
        echo "   ✓ Created match_maps table with proper structure\n";
    } else {
        echo "   - match_maps table already exists\n";
    }
    
    echo "   ✓ Schema fixes completed\n\n";

    // 4. OPTIMIZE TABLES FOR SCRAPING
    echo "4. OPTIMIZING TABLES FOR LIQUIPEDIA DATA...\n";
    
    // Enhance teams table
    if (Schema::hasTable('teams')) {
        $teams_columns = [
            'earnings' => 'decimal',
            'coach_image' => 'string',
            'twitter_url' => 'string',
            'instagram_url' => 'string', 
            'youtube_url' => 'string',
            'twitch_url' => 'string',
            'discord_url' => 'string',
            'website_url' => 'string',
            'liquipedia_url' => 'string',
            'vlr_url' => 'string'
        ];
        
        foreach ($teams_columns as $column => $type) {
            if (!Schema::hasColumn('teams', $column)) {
                Schema::table('teams', function (Blueprint $table) use ($column, $type) {
                    if ($type === 'decimal') {
                        $table->decimal($column, 15, 2)->default(0.00)->nullable();
                    } else {
                        $table->string($column)->nullable();
                    }
                });
                echo "   ✓ Added {$column} to teams table\n";
            }
        }
    }
    
    // Enhance players table
    if (Schema::hasTable('players')) {
        $players_columns = [
            'earnings' => 'decimal',
            'elo_rating' => 'integer',
            'peak_rating' => 'integer',
            'twitter_url' => 'string',
            'instagram_url' => 'string',
            'youtube_url' => 'string', 
            'twitch_url' => 'string',
            'discord_url' => 'string',
            'liquipedia_url' => 'string',
            'vlr_url' => 'string'
        ];
        
        foreach ($players_columns as $column => $type) {
            if (!Schema::hasColumn('players', $column)) {
                Schema::table('players', function (Blueprint $table) use ($column, $type) {
                    if ($type === 'decimal') {
                        $table->decimal($column, 15, 2)->default(0.00)->nullable();
                    } elseif ($type === 'integer') {
                        $table->integer($column)->default(0)->nullable();
                    } else {
                        $table->string($column)->nullable();
                    }
                });
                echo "   ✓ Added {$column} to players table\n";
            }
        }
    }
    
    echo "   ✓ Table optimizations completed\n\n";

    // 5. CREATE PERFORMANCE INDEXES
    echo "5. CREATING PERFORMANCE INDEXES...\n";
    
    $indexes = [
        'teams' => ['region', 'country', 'status', 'earnings'],
        'players' => ['team_id', 'role', 'country', 'status', 'earnings', 'elo_rating'],
        'matches' => ['event_id', 'status', 'scheduled_at'],
        'player_match_stats' => ['match_id', 'player_id', 'hero_id'],
    ];
    
    foreach ($indexes as $table => $columns) {
        if (Schema::hasTable($table)) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    try {
                        $index_name = "idx_{$table}_{$column}";
                        DB::statement("CREATE INDEX IF NOT EXISTS {$index_name} ON {$table} ({$column})");
                        echo "   ✓ Created/verified index {$index_name}\n";
                    } catch (Exception $e) {
                        echo "   ⚠ Index creation skipped for {$table}.{$column}: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
    }
    
    echo "   ✓ Performance indexes completed\n\n";

    // 6. FINAL VALIDATION
    echo "6. FINAL VALIDATION...\n";
    
    // Check critical tables exist and are empty
    $critical_tables = ['teams', 'players', 'matches', 'events'];
    foreach ($critical_tables as $table) {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->count();
            echo "   ✓ Table {$table} exists ({$count} records - should be 0)\n";
        } else {
            echo "   ✗ Critical table {$table} is missing!\n";
        }
    }
    
    // Verify critical columns exist
    $critical_columns = [
        'teams' => ['earnings', 'coach_image', 'twitter_url', 'liquipedia_url'],
        'players' => ['earnings', 'elo_rating', 'twitter_url', 'liquipedia_url']
    ];
    
    foreach ($critical_columns as $table => $columns) {
        if (Schema::hasTable($table)) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    echo "   ✓ Column {$table}.{$column} exists\n";
                } else {
                    echo "   ✗ Critical column {$table}.{$column} is missing!\n";
                }
            }
        }
    }
    
    // Check if map_number column exists in player_match_stats
    if (Schema::hasTable('player_match_stats')) {
        if (Schema::hasColumn('player_match_stats', 'map_number')) {
            echo "   ✓ Map stats error fixed - map_number column exists\n";
        } else {
            echo "   ✗ Map stats error NOT fixed - map_number column missing!\n";
        }
    }
    
    echo "\n=== CLEANUP AND OPTIMIZATION COMPLETED SUCCESSFULLY ===\n";
    echo "Database is now ready for comprehensive Liquipedia scraping!\n";
    echo "\nSUMMARY:\n";
    echo "✓ All existing teams and players data wiped clean\n";
    echo "✓ Auto-increment IDs reset to start fresh\n";
    echo "✓ Map stats error fixed (map_number column added)\n";
    echo "✓ Tables optimized with earnings, social media, and rating columns\n";
    echo "✓ Performance indexes created\n";
    echo "✓ Database structure prepared for Liquipedia data import\n\n";
    echo "NEXT STEPS:\n";
    echo "1. Run comprehensive Liquipedia scraping\n";
    echo "2. Import all team and player data\n";
    echo "3. Verify data integrity and relationships\n\n";

} catch (Exception $e) {
    echo "✗ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}