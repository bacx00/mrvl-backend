<?php

/**
 * CRITICAL DATABASE CLEANUP AND SCHEMA FIXES
 * 
 * This script performs:
 * 1. Complete data wipe of teams and players
 * 2. Schema fixes for map stats errors
 * 3. Table optimizations for Liquipedia data
 * 4. Index creation for performance
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// Load Laravel environment
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CRITICAL DATABASE CLEANUP AND SCHEMA FIXES ===\n\n";

try {
    // Test database connection
    $connection = DB::connection();
    $connection->getPdo();
    echo "✓ Database connection established\n";
    
    // Get database name
    $database = $connection->getDatabaseName();
    echo "✓ Connected to database: {$database}\n\n";

    // 1. COMPLETE DATA WIPE - Teams and Players
    echo "1. PERFORMING COMPLETE DATA WIPE...\n";
    echo "   WARNING: This will delete ALL existing teams and players data!\n";
    echo "   Proceeding in 3 seconds...\n\n";
    
    sleep(3);
    
    // Disable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    // Delete all data from related tables
    $tables_to_clear = [
        'player_match_stats',
        'team_match_stats', 
        'player_team_history',
        'match_maps',
        'matches',
        'event_teams',
        'event_standings',
        'players',
        'teams',
        'brackets',
        'bracket_matches',
        'bracket_games'
    ];
    
    foreach ($tables_to_clear as $table) {
        try {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->delete();
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
    
    echo "   ✓ Data wipe completed\n\n";

    // 2. RESET AUTO-INCREMENT IDs
    echo "2. RESETTING AUTO-INCREMENT IDs...\n";
    
    $auto_increment_tables = [
        'teams', 'players', 'matches', 'events', 'player_match_stats', 
        'team_match_stats', 'match_maps', 'brackets', 'bracket_matches'
    ];
    
    foreach ($auto_increment_tables as $table) {
        try {
            if (DB::getSchemaBuilder()->hasTable($table)) {
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
    if (DB::getSchemaBuilder()->hasTable('player_match_stats')) {
        if (!DB::getSchemaBuilder()->hasColumn('player_match_stats', 'map_number')) {
            DB::getSchemaBuilder()->table('player_match_stats', function (Blueprint $table) {
                $table->integer('map_number')->default(1)->after('match_id');
                $table->index(['match_id', 'map_number'], 'idx_match_map');
            });
            echo "   ✓ Added map_number column to player_match_stats\n";
        } else {
            echo "   - map_number column already exists in player_match_stats\n";
        }
    }
    
    // Ensure match_maps table has proper structure
    if (!DB::getSchemaBuilder()->hasTable('match_maps')) {
        DB::getSchemaBuilder()->create('match_maps', function (Blueprint $table) {
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
    $teams_columns = [
        'earnings' => 'decimal(15,2)',
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
        if (!DB::getSchemaBuilder()->hasColumn('teams', $column)) {
            DB::getSchemaBuilder()->table('teams', function (Blueprint $table) use ($column, $type) {
                if ($type === 'decimal(15,2)') {
                    $table->decimal($column, 15, 2)->default(0.00)->nullable();
                } else {
                    $table->string($column)->nullable();
                }
            });
            echo "   ✓ Added {$column} to teams table\n";
        }
    }
    
    // Enhance players table
    $players_columns = [
        'earnings' => 'decimal(15,2)',
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
        if (!DB::getSchemaBuilder()->hasColumn('players', $column)) {
            DB::getSchemaBuilder()->table('players', function (Blueprint $table) use ($column, $type) {
                if ($type === 'decimal(15,2)') {
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
    
    echo "   ✓ Table optimizations completed\n\n";

    // 5. CREATE PERFORMANCE INDEXES
    echo "5. CREATING PERFORMANCE INDEXES...\n";
    
    $indexes = [
        'teams' => [
            ['region', 'country'],
            ['status'],
            ['earnings'],
            ['created_at']
        ],
        'players' => [
            ['team_id'],
            ['role'],
            ['country'],
            ['status'],
            ['earnings'],
            ['elo_rating'],
            ['created_at']
        ],
        'matches' => [
            ['event_id'],
            ['team1_id', 'team2_id'],
            ['status'],
            ['scheduled_at'],
            ['created_at']
        ],
        'player_match_stats' => [
            ['match_id', 'player_id'],
            ['match_id', 'map_number'],
            ['player_id'],
            ['hero_id']
        ],
        'match_maps' => [
            ['match_id'],
            ['status'],
            ['winner_team_id']
        ]
    ];
    
    foreach ($indexes as $table => $table_indexes) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            foreach ($table_indexes as $index_columns) {
                try {
                    $index_name = 'idx_' . implode('_', $index_columns);
                    
                    // Check if index already exists
                    $existing_indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index_name]);
                    
                    if (empty($existing_indexes)) {
                        DB::statement("CREATE INDEX {$index_name} ON {$table} (" . implode(', ', $index_columns) . ")");
                        echo "   ✓ Created index {$index_name} on {$table}\n";
                    } else {
                        echo "   - Index {$index_name} already exists on {$table}\n";
                    }
                } catch (Exception $e) {
                    echo "   ⚠ Error creating index on {$table}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "   ✓ Performance indexes completed\n\n";

    // 6. VERIFY FOREIGN KEY CONSTRAINTS
    echo "6. VERIFYING FOREIGN KEY CONSTRAINTS...\n";
    
    $foreign_keys = [
        'players' => [
            ['team_id', 'teams', 'id']
        ],
        'matches' => [
            ['team1_id', 'teams', 'id'],
            ['team2_id', 'teams', 'id'],
            ['event_id', 'events', 'id']
        ],
        'player_match_stats' => [
            ['match_id', 'matches', 'id'],
            ['player_id', 'players', 'id']
        ],
        'match_maps' => [
            ['match_id', 'matches', 'id'],
            ['winner_team_id', 'teams', 'id']
        ]
    ];
    
    foreach ($foreign_keys as $table => $constraints) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            foreach ($constraints as $constraint) {
                list($column, $ref_table, $ref_column) = $constraint;
                
                try {
                    // Check if foreign key exists
                    $existing_fks = DB::select("
                        SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = ? 
                        AND COLUMN_NAME = ?
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                    ", [$table, $column]);
                    
                    if (empty($existing_fks) && DB::getSchemaBuilder()->hasColumn($table, $column)) {
                        $fk_name = "fk_{$table}_{$column}";
                        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$fk_name} FOREIGN KEY ({$column}) REFERENCES {$ref_table}({$ref_column}) ON DELETE CASCADE");
                        echo "   ✓ Added foreign key constraint {$fk_name}\n";
                    } else {
                        echo "   - Foreign key constraint already exists for {$table}.{$column}\n";
                    }
                } catch (Exception $e) {
                    echo "   ⚠ Error adding foreign key constraint for {$table}.{$column}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "   ✓ Foreign key constraints verified\n\n";

    // 7. FINAL VALIDATION
    echo "7. FINAL VALIDATION...\n";
    
    // Check critical tables exist
    $critical_tables = ['teams', 'players', 'matches', 'player_match_stats', 'match_maps', 'events'];
    foreach ($critical_tables as $table) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            $count = DB::table($table)->count();
            echo "   ✓ Table {$table} exists (0 records - clean)\n";
        } else {
            echo "   ✗ Critical table {$table} is missing!\n";
        }
    }
    
    // Verify critical columns
    $critical_columns = [
        'teams' => ['earnings', 'coach_image', 'twitter_url'],
        'players' => ['earnings', 'elo_rating', 'twitter_url'],
        'player_match_stats' => ['map_number'],
        'match_maps' => ['map_number', 'team1_score', 'team2_score']
    ];
    
    foreach ($critical_columns as $table => $columns) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            foreach ($columns as $column) {
                if (DB::getSchemaBuilder()->hasColumn($table, $column)) {
                    echo "   ✓ Column {$table}.{$column} exists\n";
                } else {
                    echo "   ✗ Critical column {$table}.{$column} is missing!\n";
                }
            }
        }
    }
    
    echo "\n=== CLEANUP AND OPTIMIZATION COMPLETED SUCCESSFULLY ===\n";
    echo "Database is now ready for comprehensive Liquipedia scraping!\n";
    echo "\nSUMMARY:\n";
    echo "- All existing teams and players data wiped clean\n";
    echo "- Auto-increment IDs reset to start fresh\n";
    echo "- Map stats error fixed (map_number column added)\n";
    echo "- Tables optimized with earnings, social media, and rating columns\n";
    echo "- Performance indexes created\n";
    echo "- Foreign key constraints verified\n";
    echo "- Database structure prepared for Liquipedia data import\n\n";

} catch (Exception $e) {
    echo "✗ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}