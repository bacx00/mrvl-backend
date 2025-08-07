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
        // Add soft deletes and audit fields to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
            
            if (!Schema::hasColumn('users', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('created_at');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('users', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('updated_at');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Add data validation constraints to users table
        DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_hero_flair_length CHECK (CHAR_LENGTH(hero_flair) <= 100)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_email_format CHECK (email REGEXP "^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$")');
        DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_status_values CHECK (status IN ("active", "inactive", "banned"))');

        // Add soft deletes to teams table if needed
        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                if (!Schema::hasColumn('teams', 'deleted_at')) {
                    $table->softDeletes();
                }
                
                if (!Schema::hasColumn('teams', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('created_at');
                    $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                }
                
                if (!Schema::hasColumn('teams', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('updated_at');
                    $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                }
            });

            // Add validation constraints for teams
            DB::statement('ALTER TABLE teams ADD CONSTRAINT chk_teams_name_length CHECK (CHAR_LENGTH(name) >= 2 AND CHAR_LENGTH(name) <= 100)');
            DB::statement('ALTER TABLE teams ADD CONSTRAINT chk_teams_short_name_length CHECK (CHAR_LENGTH(short_name) >= 1 AND CHAR_LENGTH(short_name) <= 10)');
            DB::statement('ALTER TABLE teams ADD CONSTRAINT chk_teams_rating_range CHECK (rating >= 0 AND rating <= 5000)');
        }

        // Add soft deletes to marvel_rivals_heroes table
        if (Schema::hasTable('marvel_rivals_heroes')) {
            Schema::table('marvel_rivals_heroes', function (Blueprint $table) {
                if (!Schema::hasColumn('marvel_rivals_heroes', 'deleted_at')) {
                    $table->softDeletes();
                }
                
                if (!Schema::hasColumn('marvel_rivals_heroes', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('created_at');
                    $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                }
                
                if (!Schema::hasColumn('marvel_rivals_heroes', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('updated_at');
                    $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                }
            });

            // Add validation constraints for heroes
            DB::statement('ALTER TABLE marvel_rivals_heroes ADD CONSTRAINT chk_heroes_name_length CHECK (CHAR_LENGTH(name) >= 2 AND CHAR_LENGTH(name) <= 100)');
            DB::statement('ALTER TABLE marvel_rivals_heroes ADD CONSTRAINT chk_heroes_role_values CHECK (role IN ("Vanguard", "Duelist", "Strategist"))');
        }

        // Add referential integrity constraints if missing
        if (!$this->foreignKeyExists('users', 'users_hero_flair_foreign')) {
            // Note: We can't create a direct foreign key to hero name since it's a string field
            // Instead, we'll add a trigger-based validation or handle it in application logic
        }

        // Add cascading update/delete rules for team_flair_id if not already present
        $this->updateForeignKeyConstraint(
            'users', 
            'users_team_flair_id_foreign',
            'team_flair_id',
            'teams',
            'id',
            'SET NULL', // On delete set null
            'CASCADE'   // On update cascade
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove constraints
        try {
            DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_hero_flair_length');
            DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_email_format');
            DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_status_values');
        } catch (\Exception $e) {
            // Constraints may not exist
        }

        if (Schema::hasTable('teams')) {
            try {
                DB::statement('ALTER TABLE teams DROP CONSTRAINT chk_teams_name_length');
                DB::statement('ALTER TABLE teams DROP CONSTRAINT chk_teams_short_name_length');
                DB::statement('ALTER TABLE teams DROP CONSTRAINT chk_teams_rating_range');
            } catch (\Exception $e) {
                // Constraints may not exist
            }
        }

        if (Schema::hasTable('marvel_rivals_heroes')) {
            try {
                DB::statement('ALTER TABLE marvel_rivals_heroes DROP CONSTRAINT chk_heroes_name_length');
                DB::statement('ALTER TABLE marvel_rivals_heroes DROP CONSTRAINT chk_heroes_role_values');
            } catch (\Exception $e) {
                // Constraints may not exist
            }
        }

        // Remove audit fields and soft deletes
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('users', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                if (Schema::hasColumn('teams', 'created_by')) {
                    $table->dropForeign(['created_by']);
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn('teams', 'updated_by')) {
                    $table->dropForeign(['updated_by']);
                    $table->dropColumn('updated_by');
                }
                if (Schema::hasColumn('teams', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('marvel_rivals_heroes')) {
            Schema::table('marvel_rivals_heroes', function (Blueprint $table) {
                if (Schema::hasColumn('marvel_rivals_heroes', 'created_by')) {
                    $table->dropForeign(['created_by']);
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn('marvel_rivals_heroes', 'updated_by')) {
                    $table->dropForeign(['updated_by']);
                    $table->dropColumn('updated_by');
                }
                if (Schema::hasColumn('marvel_rivals_heroes', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }

    /**
     * Check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        try {
            $result = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
            ", [$table, $constraintName]);
            
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update foreign key constraint with cascade options
     */
    private function updateForeignKeyConstraint(
        string $table, 
        string $constraintName, 
        string $column, 
        string $referencedTable, 
        string $referencedColumn,
        string $onDelete = 'RESTRICT',
        string $onUpdate = 'RESTRICT'
    ): void {
        try {
            // Check if constraint exists and needs updating
            $constraint = DB::selectOne("
                SELECT 
                    rc.DELETE_RULE,
                    rc.UPDATE_RULE
                FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
                AND rc.CONSTRAINT_NAME = ?
            ", [$constraintName]);

            if ($constraint && (
                $constraint->DELETE_RULE !== $onDelete || 
                $constraint->UPDATE_RULE !== $onUpdate
            )) {
                // Drop existing constraint
                DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraintName}");
                
                // Add new constraint with proper cascade rules
                DB::statement("
                    ALTER TABLE {$table} 
                    ADD CONSTRAINT {$constraintName} 
                    FOREIGN KEY ({$column}) 
                    REFERENCES {$referencedTable}({$referencedColumn}) 
                    ON DELETE {$onDelete} 
                    ON UPDATE {$onUpdate}
                ");
            }
        } catch (\Exception $e) {
            // Constraint may not exist or may already be correct
        }
    }
};