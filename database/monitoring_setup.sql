-- =====================================================
-- MARVEL RIVALS TOURNAMENT DATABASE MONITORING SETUP
-- =====================================================
-- 
-- Comprehensive monitoring setup for tournament database operations
-- Includes performance schema configuration, slow query logging,
-- and automated maintenance procedures
--
-- Run this after applying the main optimization migration

-- 1. ENABLE PERFORMANCE SCHEMA (if not already enabled)
-- Note: Requires server restart if changing from OFF to ON
-- SET GLOBAL performance_schema = ON;

-- 2. CONFIGURE SLOW QUERY LOG
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1.0;
SET GLOBAL log_queries_not_using_indexes = 'ON';
SET GLOBAL min_examined_row_limit = 1000;

-- 3. CONFIGURE GENERAL QUERY LOG (for debugging, disable in production)
-- SET GLOBAL general_log = 'ON';
-- SET GLOBAL general_log_file = '/var/log/mysql/general.log';

-- 4. CREATE MONITORING TABLES
-- =============================

-- Table to store database performance history
CREATE TABLE IF NOT EXISTS `db_performance_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `metric_name` VARCHAR(100) NOT NULL,
    `metric_value` DECIMAL(15,4) NOT NULL,
    `metric_unit` VARCHAR(20) DEFAULT NULL,
    `context` JSON DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_timestamp_metric` (`timestamp`, `metric_name`),
    KEY `idx_metric_name` (`metric_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table to track query performance patterns
CREATE TABLE IF NOT EXISTS `query_performance_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `query_hash` VARCHAR(64) NOT NULL,
    `query_type` VARCHAR(50) NOT NULL,
    `table_names` VARCHAR(500) DEFAULT NULL,
    `execution_time_ms` DECIMAL(10,3) NOT NULL,
    `rows_examined` BIGINT DEFAULT NULL,
    `rows_sent` BIGINT DEFAULT NULL,
    `index_usage` JSON DEFAULT NULL,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_query_hash` (`query_hash`),
    KEY `idx_execution_time` (`execution_time_ms`),
    KEY `idx_executed_at` (`executed_at`),
    KEY `idx_query_type` (`query_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for tracking tournament-specific metrics
CREATE TABLE IF NOT EXISTS `tournament_performance_metrics` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `measurement_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `total_matches` INT DEFAULT 0,
    `active_matches` INT DEFAULT 0,
    `avg_query_time_ms` DECIMAL(8,3) DEFAULT 0,
    `cache_hit_ratio` DECIMAL(5,2) DEFAULT 0,
    `concurrent_users` INT DEFAULT 0,
    `db_connections` INT DEFAULT 0,
    `memory_usage_mb` DECIMAL(10,2) DEFAULT 0,
    `additional_metrics` JSON DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_event_measurement` (`event_id`, `measurement_time`),
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. CREATE MONITORING VIEWS
-- ===========================

-- View for current database performance overview
CREATE OR REPLACE VIEW `v_db_performance_overview` AS
SELECT 
    -- Connection statistics
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Threads_connected') as current_connections,
    (SELECT @@max_connections) as max_connections,
    ROUND((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Threads_connected') / @@max_connections * 100, 2) as connection_usage_pct,
    
    -- Buffer pool statistics
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_total') as buffer_pool_total_pages,
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_free') as buffer_pool_free_pages,
    ROUND((1 - (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_free') / 
               (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_total')) * 100, 2) as buffer_pool_usage_pct,
    
    -- Query cache statistics (if enabled)
    ROUND(((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests') - 
           (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads')) / 
           (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests') * 100, 2) as buffer_pool_hit_ratio,
    
    -- Table sizes for tournament tables
    (SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
     FROM information_schema.tables 
     WHERE table_schema = DATABASE() 
     AND table_name IN ('bracket_matches', 'bracket_games', 'bracket_standings', 'teams', 'players', 'events')) as tournament_tables_size_mb,
    
    NOW() as measurement_time;

-- View for tournament activity summary
CREATE OR REPLACE VIEW `v_tournament_activity_summary` AS
SELECT 
    e.id as event_id,
    e.name as event_name,
    e.status as event_status,
    COUNT(DISTINCT bm.id) as total_matches,
    COUNT(DISTINCT CASE WHEN bm.status IN ('live', 'ongoing') THEN bm.id END) as live_matches,
    COUNT(DISTINCT CASE WHEN bm.status = 'completed' THEN bm.id END) as completed_matches,
    COUNT(DISTINCT CASE WHEN bm.status = 'pending' THEN bm.id END) as pending_matches,
    COUNT(DISTINCT COALESCE(bm.team1_id, bm.team2_id)) as participating_teams,
    ROUND(AVG(CASE WHEN bm.status = 'completed' THEN bm.team1_score + bm.team2_score END), 2) as avg_games_per_match,
    MAX(bm.updated_at) as last_activity
FROM events e
LEFT JOIN bracket_matches bm ON e.id = bm.event_id
WHERE e.start_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY e.id, e.name, e.status
ORDER BY last_activity DESC;

-- View for identifying slow performing tournaments
CREATE OR REPLACE VIEW `v_slow_tournament_queries` AS
SELECT 
    bm.event_id,
    e.name as event_name,
    COUNT(*) as query_count,
    AVG(execution_time_ms) as avg_execution_time_ms,
    MAX(execution_time_ms) as max_execution_time_ms,
    COUNT(DISTINCT bm.id) as unique_matches
FROM query_performance_log qpl
JOIN bracket_matches bm ON FIND_IN_SET('bracket_matches', qpl.table_names)
JOIN events e ON bm.event_id = e.id
WHERE qpl.executed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
AND qpl.execution_time_ms > 500  -- Queries slower than 500ms
GROUP BY bm.event_id, e.name
HAVING avg_execution_time_ms > 1000  -- Average > 1 second
ORDER BY avg_execution_time_ms DESC;

-- 6. CREATE STORED PROCEDURES FOR AUTOMATED MONITORING
-- =====================================================

DELIMITER //

-- Procedure to collect and store performance metrics
CREATE PROCEDURE `CollectPerformanceMetrics`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @sqlstate = RETURNED_SQLSTATE, @errno = MYSQL_ERRNO, @text = MESSAGE_TEXT;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Insert connection metrics
    INSERT INTO db_performance_history (metric_name, metric_value, metric_unit)
    SELECT 'connection_usage_pct', connection_usage_pct, 'percentage'
    FROM v_db_performance_overview;
    
    -- Insert buffer pool metrics
    INSERT INTO db_performance_history (metric_name, metric_value, metric_unit)
    SELECT 'buffer_pool_usage_pct', buffer_pool_usage_pct, 'percentage'
    FROM v_db_performance_overview;
    
    INSERT INTO db_performance_history (metric_name, metric_value, metric_unit)
    SELECT 'buffer_pool_hit_ratio', buffer_pool_hit_ratio, 'percentage'
    FROM v_db_performance_overview;
    
    -- Insert table size metrics
    INSERT INTO db_performance_history (metric_name, metric_value, metric_unit)
    SELECT 'tournament_tables_size_mb', tournament_tables_size_mb, 'megabytes'
    FROM v_db_performance_overview;
    
    -- Collect tournament-specific metrics
    INSERT INTO tournament_performance_metrics (
        event_id, total_matches, active_matches, concurrent_users
    )
    SELECT 
        event_id,
        total_matches,
        live_matches,
        0  -- concurrent_users would need application-level tracking
    FROM v_tournament_activity_summary
    WHERE event_status IN ('active', 'live')
    ON DUPLICATE KEY UPDATE
        total_matches = VALUES(total_matches),
        active_matches = VALUES(active_matches),
        measurement_time = NOW();
    
    COMMIT;
END //

-- Procedure to clean old monitoring data
CREATE PROCEDURE `CleanOldMonitoringData`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @sqlstate = RETURNED_SQLSTATE, @errno = MYSQL_ERRNO, @text = MESSAGE_TEXT;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Keep performance history for 30 days
    DELETE FROM db_performance_history 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Keep query performance log for 7 days
    DELETE FROM query_performance_log 
    WHERE executed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Keep tournament metrics for 90 days
    DELETE FROM tournament_performance_metrics 
    WHERE measurement_time < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    COMMIT;
    
    -- Optimize tables after cleanup
    OPTIMIZE TABLE db_performance_history, query_performance_log, tournament_performance_metrics;
END //

-- Procedure to generate performance report
CREATE PROCEDURE `GeneratePerformanceReport`(IN days_back INT DEFAULT 7)
BEGIN
    -- Performance trends over specified period
    SELECT 
        DATE(timestamp) as report_date,
        metric_name,
        ROUND(AVG(metric_value), 2) as avg_value,
        ROUND(MIN(metric_value), 2) as min_value,
        ROUND(MAX(metric_value), 2) as max_value,
        metric_unit
    FROM db_performance_history
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL days_back DAY)
    GROUP BY DATE(timestamp), metric_name, metric_unit
    ORDER BY report_date DESC, metric_name;
    
    -- Tournament activity summary
    SELECT 
        'Tournament Activity Summary' as section,
        COUNT(DISTINCT event_id) as active_tournaments,
        SUM(total_matches) as total_matches,
        SUM(active_matches) as current_live_matches,
        ROUND(AVG(avg_games_per_match), 2) as avg_games_per_match
    FROM v_tournament_activity_summary
    WHERE last_activity >= DATE_SUB(NOW(), INTERVAL days_back DAY);
    
    -- Slow query summary
    SELECT 
        'Slow Query Summary' as section,
        COUNT(*) as total_slow_queries,
        ROUND(AVG(execution_time_ms), 2) as avg_execution_time_ms,
        ROUND(MAX(execution_time_ms), 2) as max_execution_time_ms
    FROM query_performance_log
    WHERE executed_at >= DATE_SUB(NOW(), INTERVAL days_back DAY)
    AND execution_time_ms > 1000;
END //

DELIMITER ;

-- 7. CREATE EVENTS FOR AUTOMATED MONITORING
-- ==========================================

-- Enable event scheduler if not already enabled
SET GLOBAL event_scheduler = ON;

-- Event to collect performance metrics every 5 minutes
CREATE EVENT IF NOT EXISTS `evt_collect_performance_metrics`
ON SCHEDULE EVERY 5 MINUTE
STARTS CURRENT_TIMESTAMP
DO
    CALL CollectPerformanceMetrics();

-- Event to clean old monitoring data weekly
CREATE EVENT IF NOT EXISTS `evt_clean_monitoring_data`
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP + INTERVAL 1 DAY  -- Run tomorrow at same time
DO
    CALL CleanOldMonitoringData();

-- 8. USEFUL MONITORING QUERIES
-- =============================

-- Current database performance overview
-- SELECT * FROM v_db_performance_overview;

-- Tournament activity in last 24 hours
-- SELECT * FROM v_tournament_activity_summary WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Recent slow queries
-- SELECT * FROM v_slow_tournament_queries;

-- Performance trends for last 7 days
-- CALL GeneratePerformanceReport(7);

-- Table sizes and growth
/*
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
    table_rows,
    ROUND((data_length / 1024 / 1024), 2) AS data_mb,
    ROUND((index_length / 1024 / 1024), 2) AS index_mb
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name IN ('bracket_matches', 'bracket_games', 'bracket_standings', 'teams', 'players', 'events', 'matches')
ORDER BY (data_length + index_length) DESC;
*/

-- Index usage analysis
/*
SELECT 
    s.table_name,
    s.index_name,
    s.cardinality,
    CASE 
        WHEN s.cardinality = 0 THEN 'Unused or Empty'
        WHEN s.cardinality < 100 THEN 'Low Cardinality'
        WHEN s.cardinality < 1000 THEN 'Medium Cardinality'
        ELSE 'High Cardinality'
    END as index_quality,
    t.table_rows
FROM information_schema.statistics s
JOIN information_schema.tables t ON s.table_name = t.table_name AND s.table_schema = t.table_schema
WHERE s.table_schema = DATABASE()
AND s.table_name IN ('bracket_matches', 'bracket_games', 'teams', 'players')
ORDER BY s.table_name, s.cardinality DESC;
*/

-- 9. ALERT CONDITIONS (for external monitoring)
-- ==============================================

-- These queries can be used with monitoring tools like Nagios, Zabbix, or Prometheus

-- High connection usage alert (> 80%)
-- SELECT CASE WHEN connection_usage_pct > 80 THEN 'CRITICAL' ELSE 'OK' END as status, connection_usage_pct FROM v_db_performance_overview;

-- Low buffer pool hit ratio alert (< 95%)
-- SELECT CASE WHEN buffer_pool_hit_ratio < 95 THEN 'WARNING' ELSE 'OK' END as status, buffer_pool_hit_ratio FROM v_db_performance_overview;

-- Recent slow queries alert
-- SELECT CASE WHEN COUNT(*) > 10 THEN 'WARNING' ELSE 'OK' END as status, COUNT(*) as slow_query_count FROM query_performance_log WHERE executed_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND execution_time_ms > 2000;

-- Large table size alert (> 1GB)
-- SELECT CASE WHEN tournament_tables_size_mb > 1024 THEN 'INFO' ELSE 'OK' END as status, tournament_tables_size_mb FROM v_db_performance_overview;

-- =====================================================
-- MONITORING SETUP COMPLETE
-- =====================================================
-- 
-- This monitoring setup provides:
-- 1. Automated performance metric collection
-- 2. Historical performance tracking
-- 3. Tournament-specific monitoring
-- 4. Automated cleanup of old data
-- 5. Ready-to-use monitoring queries
-- 6. Alert conditions for external tools
--
-- To verify the setup is working:
-- 1. Check that events are enabled: SHOW EVENTS;
-- 2. Monitor metric collection: SELECT COUNT(*) FROM db_performance_history;
-- 3. View current performance: SELECT * FROM v_db_performance_overview;
-- 4. Generate a report: CALL GeneratePerformanceReport(1);
-- =====================================================