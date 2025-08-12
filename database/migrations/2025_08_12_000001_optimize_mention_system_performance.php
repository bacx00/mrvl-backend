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
        // First, add mention_count columns to related tables
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mention_count')) {
                $table->unsignedInteger('mention_count')->default(0);
                $table->timestamp('last_mentioned_at')->nullable();
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'mention_count')) {
                $table->unsignedInteger('mention_count')->default(0);
                $table->timestamp('last_mentioned_at')->nullable();
            }
        });

        Schema::table('players', function (Blueprint $table) {
            if (!Schema::hasColumn('players', 'mention_count')) {
                $table->unsignedInteger('mention_count')->default(0);
                $table->timestamp('last_mentioned_at')->nullable();
            }
        });

        // Optimize mentions table with additional indexes
        Schema::table('mentions', function (Blueprint $table) {
            // Drop existing duplicate indexes if they exist
            try {
                $table->dropIndex(['mentioned_type', 'mentioned_id']);
            } catch (Exception $e) {
                // Ignore if index doesn't exist
            }
            
            try {
                $table->dropIndex(['mentionable_type', 'mentionable_id']);
            } catch (Exception $e) {
                // Ignore if index doesn't exist
            }

            // Create optimized composite indexes for common query patterns
            $table->index(['mentioned_type', 'mentioned_id', 'is_active'], 'idx_mentions_entity_active');
            $table->index(['mentionable_type', 'mentionable_id', 'is_active'], 'idx_mentions_content_active');
            
            // Index for recent mentions with pagination
            $table->index(['mentioned_type', 'mentioned_id', 'mentioned_at'], 'idx_mentions_entity_time');
            $table->index(['mentioned_by', 'mentioned_at'], 'idx_mentions_author_time');
            
            // Index for mention counts by type
            $table->index(['mentioned_type', 'is_active', 'mentioned_at'], 'idx_mentions_type_stats');
            
            // Index for content cleanup operations
            $table->index(['mentionable_type', 'mentionable_id', 'mentioned_at'], 'idx_mentions_content_cleanup');
            
            // Index for user activity tracking
            $table->index(['mentioned_by', 'is_active', 'created_at'], 'idx_mentions_user_activity');
        });

        // Add proper foreign key constraints with cascading deletes
        Schema::table('mentions', function (Blueprint $table) {
            // Drop existing foreign key if it exists
            try {
                $table->dropForeign(['mentioned_by']);
            } catch (Exception $e) {
                // Ignore if constraint doesn't exist
            }
            
            // Re-add with proper cascading
            $table->foreign('mentioned_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->name('fk_mentions_mentioned_by');
        });

        // Initialize mention counts for existing data
        $this->updateMentionCounts();

        // Create triggers for maintaining mention counts
        $this->createMentionTriggers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS tr_mentions_insert_count');
        DB::unprepared('DROP TRIGGER IF EXISTS tr_mentions_update_count');
        DB::unprepared('DROP TRIGGER IF EXISTS tr_mentions_delete_count');

        // Remove mention_count columns
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'mention_count')) {
                $table->dropColumn(['mention_count', 'last_mentioned_at']);
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            if (Schema::hasColumn('teams', 'mention_count')) {
                $table->dropColumn(['mention_count', 'last_mentioned_at']);
            }
        });

        Schema::table('players', function (Blueprint $table) {
            if (Schema::hasColumn('players', 'mention_count')) {
                $table->dropColumn(['mention_count', 'last_mentioned_at']);
            }
        });

        // Drop optimized indexes
        Schema::table('mentions', function (Blueprint $table) {
            $table->dropIndex('idx_mentions_entity_active');
            $table->dropIndex('idx_mentions_content_active');
            $table->dropIndex('idx_mentions_entity_time');
            $table->dropIndex('idx_mentions_author_time');
            $table->dropIndex('idx_mentions_type_stats');
            $table->dropIndex('idx_mentions_content_cleanup');
            $table->dropIndex('idx_mentions_user_activity');
        });
    }

    /**
     * Update mention counts for existing data
     */
    private function updateMentionCounts(): void
    {
        // Update user mention counts
        DB::statement("
            UPDATE users u 
            SET mention_count = (
                SELECT COUNT(*) 
                FROM mentions m 
                WHERE m.mentioned_type = 'App\\\\Models\\\\User' 
                AND m.mentioned_id = u.id 
                AND m.is_active = 1
            ),
            last_mentioned_at = (
                SELECT MAX(m.mentioned_at) 
                FROM mentions m 
                WHERE m.mentioned_type = 'App\\\\Models\\\\User' 
                AND m.mentioned_id = u.id 
                AND m.is_active = 1
            )
        ");

        // Update team mention counts
        DB::statement("
            UPDATE teams t 
            SET mention_count = (
                SELECT COUNT(*) 
                FROM mentions m 
                WHERE m.mentioned_type = 'App\\\\Models\\\\Team' 
                AND m.mentioned_id = t.id 
                AND m.is_active = 1
            ),
            last_mentioned_at = (
                SELECT MAX(m.mentioned_at) 
                FROM mentions m 
                WHERE m.mentioned_type = 'App\\\\Models\\\\Team' 
                AND m.mentioned_id = t.id 
                AND m.is_active = 1
            )
        ");

        // Update player mention counts
        DB::statement("
            UPDATE players p 
            SET mention_count = (
                SELECT COUNT(*) 
                FROM mentions m 
                WHERE m.mentioned_type = 'App\\\\Models\\\\Player' 
                AND m.mentioned_id = p.id 
                AND m.is_active = 1
            ),
            last_mentioned_at = (
                SELECT MAX(m.mentioned_at) 
                FROM mentions m 
                WHERE m.mentioned_type = 'App\\\\Models\\\\Player' 
                AND m.mentioned_id = p.id 
                AND m.is_active = 1
            )
        ");
    }

    /**
     * Create database triggers for maintaining mention counts
     */
    private function createMentionTriggers(): void
    {
        // Trigger for INSERT operations
        DB::unprepared("
            CREATE TRIGGER tr_mentions_insert_count 
            AFTER INSERT ON mentions 
            FOR EACH ROW 
            BEGIN
                IF NEW.is_active = 1 THEN
                    CASE NEW.mentioned_type
                        WHEN 'App\\\\Models\\\\User' THEN
                            UPDATE users 
                            SET mention_count = mention_count + 1,
                                last_mentioned_at = NEW.mentioned_at
                            WHERE id = NEW.mentioned_id;
                        
                        WHEN 'App\\\\Models\\\\Team' THEN
                            UPDATE teams 
                            SET mention_count = mention_count + 1,
                                last_mentioned_at = NEW.mentioned_at
                            WHERE id = NEW.mentioned_id;
                        
                        WHEN 'App\\\\Models\\\\Player' THEN
                            UPDATE players 
                            SET mention_count = mention_count + 1,
                                last_mentioned_at = NEW.mentioned_at
                            WHERE id = NEW.mentioned_id;
                    END CASE;
                END IF;
            END
        ");

        // Trigger for UPDATE operations
        DB::unprepared("
            CREATE TRIGGER tr_mentions_update_count 
            AFTER UPDATE ON mentions 
            FOR EACH ROW 
            BEGIN
                -- Handle activation/deactivation
                IF OLD.is_active != NEW.is_active THEN
                    IF NEW.is_active = 1 THEN
                        -- Activated mention
                        CASE NEW.mentioned_type
                            WHEN 'App\\\\Models\\\\User' THEN
                                UPDATE users 
                                SET mention_count = mention_count + 1,
                                    last_mentioned_at = NEW.mentioned_at
                                WHERE id = NEW.mentioned_id;
                            
                            WHEN 'App\\\\Models\\\\Team' THEN
                                UPDATE teams 
                                SET mention_count = mention_count + 1,
                                    last_mentioned_at = NEW.mentioned_at
                                WHERE id = NEW.mentioned_id;
                            
                            WHEN 'App\\\\Models\\\\Player' THEN
                                UPDATE players 
                                SET mention_count = mention_count + 1,
                                    last_mentioned_at = NEW.mentioned_at
                                WHERE id = NEW.mentioned_id;
                        END CASE;
                    ELSE
                        -- Deactivated mention
                        CASE OLD.mentioned_type
                            WHEN 'App\\\\Models\\\\User' THEN
                                UPDATE users 
                                SET mention_count = GREATEST(mention_count - 1, 0)
                                WHERE id = OLD.mentioned_id;
                            
                            WHEN 'App\\\\Models\\\\Team' THEN
                                UPDATE teams 
                                SET mention_count = GREATEST(mention_count - 1, 0)
                                WHERE id = OLD.mentioned_id;
                            
                            WHEN 'App\\\\Models\\\\Player' THEN
                                UPDATE players 
                                SET mention_count = GREATEST(mention_count - 1, 0)
                                WHERE id = OLD.mentioned_id;
                        END CASE;
                    END IF;
                END IF;
            END
        ");

        // Trigger for DELETE operations
        DB::unprepared("
            CREATE TRIGGER tr_mentions_delete_count 
            AFTER DELETE ON mentions 
            FOR EACH ROW 
            BEGIN
                IF OLD.is_active = 1 THEN
                    CASE OLD.mentioned_type
                        WHEN 'App\\\\Models\\\\User' THEN
                            UPDATE users 
                            SET mention_count = GREATEST(mention_count - 1, 0)
                            WHERE id = OLD.mentioned_id;
                        
                        WHEN 'App\\\\Models\\\\Team' THEN
                            UPDATE teams 
                            SET mention_count = GREATEST(mention_count - 1, 0)
                            WHERE id = OLD.mentioned_id;
                        
                        WHEN 'App\\\\Models\\\\Player' THEN
                            UPDATE players 
                            SET mention_count = GREATEST(mention_count - 1, 0)
                            WHERE id = OLD.mentioned_id;
                    END CASE;
                END IF;
            END
        ");
    }
};