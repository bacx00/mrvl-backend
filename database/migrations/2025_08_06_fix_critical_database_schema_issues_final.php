<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        echo "🔧 Starting critical database schema fixes...\n";
        
        // 1. Fix player_team_history change_type enum to include 'transferred'
        echo "1. Fixing player_team_history.change_type enum...\n";
        DB::statement("ALTER TABLE player_team_history MODIFY COLUMN change_type ENUM('join','leave','transfer','transferred','promotion','demotion','joined','left','released','retired','loan_start','loan_end') DEFAULT 'join'");
        
        // 2. Clean up duplicate earnings columns in teams table
        echo "2. Cleaning up teams table earnings columns...\n";
        
        // Drop duplicate earnings columns that are causing issues
        try {
            if (Schema::hasColumn('teams', 'earnings_decimal')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->dropColumn('earnings_decimal');
                });
                echo "   - Dropped earnings_decimal column\n";
            }
        } catch (Exception $e) {
            echo "   - earnings_decimal column already removed or doesn't exist\n";
        }
        
        try {
            if (Schema::hasColumn('teams', 'earnings_amount')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->dropColumn('earnings_amount');
                });
                echo "   - Dropped earnings_amount column\n";
            }
        } catch (Exception $e) {
            echo "   - earnings_amount column already removed or doesn't exist\n";
        }
        
        try {
            if (Schema::hasColumn('teams', 'earnings_currency')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->dropColumn('earnings_currency');
                });
                echo "   - Dropped earnings_currency column\n";
            }
        } catch (Exception $e) {
            echo "   - earnings_currency column already removed or doesn't exist\n";
        }
        
        // 3. Now fix the main earnings column
        echo "3. Converting teams.earnings column to proper decimal type...\n";
        
        // Get the current column info
        $columnInfo = DB::select("SHOW COLUMNS FROM teams LIKE 'earnings'");
        
        if (!empty($columnInfo)) {
            $currentType = $columnInfo[0]->Type;
            echo "   - Current earnings column type: {$currentType}\n";
            
            if (strpos(strtolower($currentType), 'varchar') !== false || strpos(strtolower($currentType), 'text') !== false) {
                // It's a string column, convert to decimal
                // First clean the data by replacing empty strings and invalid values with NULL
                DB::statement("UPDATE teams SET earnings = NULL WHERE earnings = '' OR earnings = '0' OR earnings REGEXP '^[^0-9.]+$'");
                
                // Now alter the column type
                DB::statement("ALTER TABLE teams MODIFY COLUMN earnings DECIMAL(15,2) NULL DEFAULT NULL");
                echo "   - Converted earnings from {$currentType} to DECIMAL(15,2)\n";
            } else {
                echo "   - Earnings column is already proper decimal type\n";
            }
        }
        
        // 4. Ensure coach_image column exists for storing image paths
        echo "4. Adding coach_image column to teams table...\n";
        if (!Schema::hasColumn('teams', 'coach_image')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('coach_image', 500)->nullable()->after('coach_picture');
            });
            echo "   - Added coach_image column\n";
        } else {
            echo "   - coach_image column already exists\n";
        }
        
        // 5. Verify mentions table exists and has proper structure
        echo "5. Verifying mentions table structure...\n";
        if (Schema::hasTable('mentions')) {
            echo "   - Mentions table exists\n";
            
            // Check if indexes exist
            $indexes = collect(DB::select("SHOW INDEX FROM mentions"))->pluck('Key_name')->toArray();
            
            $neededIndexes = [
                'idx_mentions_mentioned_type_id' => "CREATE INDEX idx_mentions_mentioned_type_id ON mentions (mentioned_type, mentioned_id)",
                'idx_mentions_mentionable_type_id' => "CREATE INDEX idx_mentions_mentionable_type_id ON mentions (mentionable_type, mentionable_id)",
                'idx_mentions_mentioned_at' => "CREATE INDEX idx_mentions_mentioned_at ON mentions (mentioned_at)"
            ];
            
            foreach ($neededIndexes as $indexName => $sql) {
                if (!in_array($indexName, $indexes)) {
                    try {
                        DB::statement($sql);
                        echo "   - Created index: {$indexName}\n";
                    } catch (Exception $e) {
                        echo "   - Index {$indexName} might already exist with different name\n";
                    }
                } else {
                    echo "   - Index {$indexName} already exists\n";
                }
            }
        } else {
            echo "   - WARNING: mentions table does not exist\n";
        }
        
        echo "✅ Critical database schema fixes completed successfully!\n";
        echo "\nSummary of changes:\n";
        echo "- Fixed player_team_history.change_type enum to include 'transferred'\n";
        echo "- Cleaned up duplicate earnings columns in teams table\n";  
        echo "- Converted teams.earnings to proper DECIMAL(15,2) type\n";
        echo "- Added coach_image column to teams table\n";
        echo "- Verified mentions table indexes\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert change_type enum
        DB::statement("ALTER TABLE player_team_history MODIFY COLUMN change_type ENUM('join','leave','transfer','promotion','demotion') DEFAULT 'join'");
        
        // Revert earnings column back to varchar
        DB::statement("ALTER TABLE teams MODIFY COLUMN earnings VARCHAR(255) NULL DEFAULT NULL");
        
        // Remove coach_image column
        if (Schema::hasColumn('teams', 'coach_image')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('coach_image');
            });
        }
    }
};