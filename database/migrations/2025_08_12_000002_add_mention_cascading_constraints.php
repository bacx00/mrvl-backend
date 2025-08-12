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
        // Create stored procedure for mention cleanup when content is deleted
        DB::unprepared("
            CREATE PROCEDURE CleanupMentionsForContent(
                IN content_type VARCHAR(255),
                IN content_id BIGINT
            )
            BEGIN
                DECLARE done INT DEFAULT FALSE;
                DECLARE mention_id BIGINT;
                DECLARE mentioned_type_val VARCHAR(255);
                DECLARE mentioned_id_val BIGINT;
                
                DECLARE cur CURSOR FOR 
                    SELECT id, mentioned_type, mentioned_id 
                    FROM mentions 
                    WHERE mentionable_type = content_type 
                    AND mentionable_id = content_id 
                    AND is_active = 1;
                
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
                
                OPEN cur;
                
                read_loop: LOOP
                    FETCH cur INTO mention_id, mentioned_type_val, mentioned_id_val;
                    
                    IF done THEN
                        LEAVE read_loop;
                    END IF;
                    
                    -- Update mention counts before deleting
                    CASE mentioned_type_val
                        WHEN 'App\\\\Models\\\\User' THEN
                            UPDATE users 
                            SET mention_count = GREATEST(mention_count - 1, 0)
                            WHERE id = mentioned_id_val;
                        
                        WHEN 'App\\\\Models\\\\Team' THEN
                            UPDATE teams 
                            SET mention_count = GREATEST(mention_count - 1, 0)
                            WHERE id = mentioned_id_val;
                        
                        WHEN 'App\\\\Models\\\\Player' THEN
                            UPDATE players 
                            SET mention_count = GREATEST(mention_count - 1, 0)
                            WHERE id = mentioned_id_val;
                    END CASE;
                END LOOP;
                
                CLOSE cur;
                
                -- Delete all mentions for this content
                DELETE FROM mentions 
                WHERE mentionable_type = content_type 
                AND mentionable_id = content_id;
            END
        ");

        // Create procedure to cleanup mentions when a user/team/player is deleted
        DB::unprepared("
            CREATE PROCEDURE CleanupMentionsForEntity(
                IN entity_type VARCHAR(255),
                IN entity_id BIGINT
            )
            BEGIN
                -- Delete mentions of this entity
                DELETE FROM mentions 
                WHERE mentioned_type = entity_type 
                AND mentioned_id = entity_id;
                
                -- Set mentioned_by to NULL for mentions created by this user (if it's a user being deleted)
                IF entity_type = 'App\\\\Models\\\\User' THEN
                    UPDATE mentions 
                    SET mentioned_by = NULL 
                    WHERE mentioned_by = entity_id;
                END IF;
            END
        ");

        // Create integrity check procedures
        DB::unprepared("
            CREATE PROCEDURE ValidateMentionIntegrity()
            BEGIN
                DECLARE integrity_issues INT DEFAULT 0;
                
                -- Check for orphaned mentions (content doesn't exist)
                SELECT COUNT(*) INTO integrity_issues FROM (
                    SELECT m.id FROM mentions m
                    LEFT JOIN news n ON m.mentionable_type = 'news' AND m.mentionable_id = n.id
                    LEFT JOIN forum_threads ft ON m.mentionable_type = 'forum_thread' AND m.mentionable_id = ft.id
                    LEFT JOIN posts p ON m.mentionable_type = 'forum_post' AND m.mentionable_id = p.id
                    LEFT JOIN matches ma ON m.mentionable_type = 'match' AND m.mentionable_id = ma.id
                    LEFT JOIN news_comments nc ON m.mentionable_type = 'news_comment' AND m.mentionable_id = nc.id
                    WHERE (m.mentionable_type = 'news' AND n.id IS NULL)
                       OR (m.mentionable_type = 'forum_thread' AND ft.id IS NULL)
                       OR (m.mentionable_type = 'forum_post' AND p.id IS NULL)
                       OR (m.mentionable_type = 'match' AND ma.id IS NULL)
                       OR (m.mentionable_type = 'news_comment' AND nc.id IS NULL)
                ) as orphaned_content;
                
                -- Check for mentions of non-existent entities
                SELECT COUNT(*) + integrity_issues INTO integrity_issues FROM (
                    SELECT m.id FROM mentions m
                    LEFT JOIN users u ON m.mentioned_type = 'App\\\\Models\\\\User' AND m.mentioned_id = u.id
                    LEFT JOIN teams t ON m.mentioned_type = 'App\\\\Models\\\\Team' AND m.mentioned_id = t.id
                    LEFT JOIN players pl ON m.mentioned_type = 'App\\\\Models\\\\Player' AND m.mentioned_id = pl.id
                    WHERE (m.mentioned_type = 'App\\\\Models\\\\User' AND u.id IS NULL)
                       OR (m.mentioned_type = 'App\\\\Models\\\\Team' AND t.id IS NULL)
                       OR (m.mentioned_type = 'App\\\\Models\\\\Player' AND pl.id IS NULL)
                ) as orphaned_entities;
                
                SELECT integrity_issues as issues_found;
            END
        ");

        // Create procedure to fix mention count discrepancies
        DB::unprepared("
            CREATE PROCEDURE RecalculateMentionCounts()
            BEGIN
                -- Recalculate user mention counts
                UPDATE users u 
                SET mention_count = (
                    SELECT COALESCE(COUNT(*), 0)
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
                );
                
                -- Recalculate team mention counts
                UPDATE teams t 
                SET mention_count = (
                    SELECT COALESCE(COUNT(*), 0)
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
                );
                
                -- Recalculate player mention counts
                UPDATE players p 
                SET mention_count = (
                    SELECT COALESCE(COUNT(*), 0)
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
                );
            END
        ");

        // Create automated cleanup procedure for orphaned mentions
        DB::unprepared("
            CREATE PROCEDURE CleanupOrphanedMentions()
            BEGIN
                -- Remove mentions pointing to deleted content
                DELETE m FROM mentions m
                LEFT JOIN news n ON m.mentionable_type = 'news' AND m.mentionable_id = n.id
                WHERE m.mentionable_type = 'news' AND n.id IS NULL;
                
                DELETE m FROM mentions m
                LEFT JOIN forum_threads ft ON m.mentionable_type = 'forum_thread' AND m.mentionable_id = ft.id
                WHERE m.mentionable_type = 'forum_thread' AND ft.id IS NULL;
                
                DELETE m FROM mentions m
                LEFT JOIN posts p ON m.mentionable_type = 'forum_post' AND m.mentionable_id = p.id
                WHERE m.mentionable_type = 'forum_post' AND p.id IS NULL;
                
                DELETE m FROM mentions m
                LEFT JOIN matches ma ON m.mentionable_type = 'match' AND m.mentionable_id = ma.id
                WHERE m.mentionable_type = 'match' AND ma.id IS NULL;
                
                DELETE m FROM mentions m
                LEFT JOIN news_comments nc ON m.mentionable_type = 'news_comment' AND m.mentionable_id = nc.id
                WHERE m.mentionable_type = 'news_comment' AND nc.id IS NULL;
                
                -- Remove mentions of deleted entities
                DELETE m FROM mentions m
                LEFT JOIN users u ON m.mentioned_type = 'App\\\\Models\\\\User' AND m.mentioned_id = u.id
                WHERE m.mentioned_type = 'App\\\\Models\\\\User' AND u.id IS NULL;
                
                DELETE m FROM mentions m
                LEFT JOIN teams t ON m.mentioned_type = 'App\\\\Models\\\\Team' AND m.mentioned_id = t.id
                WHERE m.mentioned_type = 'App\\\\Models\\\\Team' AND t.id IS NULL;
                
                DELETE m FROM mentions m
                LEFT JOIN players pl ON m.mentioned_type = 'App\\\\Models\\\\Player' AND m.mentioned_id = pl.id
                WHERE m.mentioned_type = 'App\\\\Models\\\\Player' AND pl.id IS NULL;
                
                -- Recalculate mention counts after cleanup
                CALL RecalculateMentionCounts();
            END
        ");

        // Create performance monitoring view
        DB::unprepared("
            CREATE VIEW mention_performance_stats AS
            SELECT 
                'users' as entity_type,
                COUNT(*) as total_entities,
                SUM(mention_count) as total_mentions,
                AVG(mention_count) as avg_mentions_per_entity,
                MAX(mention_count) as max_mentions,
                COUNT(CASE WHEN mention_count > 0 THEN 1 END) as entities_with_mentions
            FROM users
            UNION ALL
            SELECT 
                'teams' as entity_type,
                COUNT(*) as total_entities,
                SUM(mention_count) as total_mentions,
                AVG(mention_count) as avg_mentions_per_entity,
                MAX(mention_count) as max_mentions,
                COUNT(CASE WHEN mention_count > 0 THEN 1 END) as entities_with_mentions
            FROM teams
            UNION ALL
            SELECT 
                'players' as entity_type,
                COUNT(*) as total_entities,
                SUM(mention_count) as total_mentions,
                AVG(mention_count) as avg_mentions_per_entity,
                MAX(mention_count) as max_mentions,
                COUNT(CASE WHEN mention_count > 0 THEN 1 END) as entities_with_mentions
            FROM players
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop stored procedures and views
        DB::unprepared('DROP PROCEDURE IF EXISTS CleanupMentionsForContent');
        DB::unprepared('DROP PROCEDURE IF EXISTS CleanupMentionsForEntity');
        DB::unprepared('DROP PROCEDURE IF EXISTS ValidateMentionIntegrity');
        DB::unprepared('DROP PROCEDURE IF EXISTS RecalculateMentionCounts');
        DB::unprepared('DROP PROCEDURE IF EXISTS CleanupOrphanedMentions');
        DB::unprepared('DROP VIEW IF EXISTS mention_performance_stats');
    }
};