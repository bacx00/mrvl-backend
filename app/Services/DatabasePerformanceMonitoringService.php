<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

/**
 * Database Performance Monitoring Service for MRVL Platform
 * 
 * Comprehensive database performance monitoring and alerting:
 * - Query performance analysis
 * - Slow query detection and logging
 * - Database resource monitoring
 * - Connection pool optimization
 * - Real-time performance metrics
 * - Automated alerting system
 * - Performance trend analysis
 * - Query optimization recommendations
 */
class DatabasePerformanceMonitoringService
{
    private $redis;
    private $cachePrefix = 'mrvl_db_monitor_';
    private $slowQueryThreshold = 1000; // milliseconds
    private $criticalQueryThreshold = 5000; // milliseconds

    public function __construct()
    {
        $this->redis = Redis::connection();
    }

    /**
     * Comprehensive database performance analysis
     */
    public function performPerformanceAnalysis(): array
    {
        Log::info('Starting comprehensive database performance analysis');

        $analysis = [
            'timestamp' => now()->toISOString(),
            'database_status' => $this->getDatabaseStatus(),
            'query_performance' => $this->analyzeQueryPerformance(),
            'slow_queries' => $this->getSlowQueries(),
            'connection_metrics' => $this->getConnectionMetrics(),
            'table_statistics' => $this->getTableStatistics(),
            'index_analysis' => $this->analyzeIndexUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'cache_performance' => $this->getCachePerformance(),
            'recommendations' => []
        ];

        // Generate recommendations based on analysis
        $analysis['recommendations'] = $this->generateRecommendations($analysis);
        $analysis['overall_score'] = $this->calculatePerformanceScore($analysis);

        // Store metrics for trending
        $this->storePerformanceMetrics($analysis);

        Log::info('Database performance analysis completed', [
            'overall_score' => $analysis['overall_score'],
            'slow_queries_count' => count($analysis['slow_queries']),
            'recommendations_count' => count($analysis['recommendations'])
        ]);

        return $analysis;
    }

    /**
     * Get database server status
     */
    private function getDatabaseStatus(): array
    {
        try {
            $status = [];
            
            // Get MySQL status variables
            $variables = DB::select('SHOW STATUS');
            foreach ($variables as $var) {
                $status[$var->Variable_name] = $var->Value;
            }

            // Get MySQL configuration variables
            $configVars = DB::select('SHOW VARIABLES LIKE "%innodb%" OR Variable_name LIKE "%query_cache%" OR Variable_name LIKE "%tmp%"');
            $config = [];
            foreach ($configVars as $var) {
                $config[$var->Variable_name] = $var->Value;
            }

            return [
                'version' => DB::select('SELECT VERSION() as version')[0]->version,
                'uptime' => $this->formatUptime($status['Uptime'] ?? 0),
                'connections' => [
                    'current' => (int) ($status['Threads_connected'] ?? 0),
                    'max_used' => (int) ($status['Max_used_connections'] ?? 0),
                    'max_allowed' => (int) ($config['max_connections'] ?? 0),
                    'total_created' => (int) ($status['Connections'] ?? 0),
                    'aborted' => (int) ($status['Aborted_connects'] ?? 0)
                ],
                'queries' => [
                    'total' => (int) ($status['Queries'] ?? 0),
                    'per_second' => round(($status['Queries'] ?? 0) / max(1, $status['Uptime'] ?? 1), 2),
                    'slow_queries' => (int) ($status['Slow_queries'] ?? 0),
                    'slow_query_percentage' => round((($status['Slow_queries'] ?? 0) / max(1, $status['Queries'] ?? 1)) * 100, 2)
                ],
                'innodb' => [
                    'buffer_pool_size' => $this->formatBytes($config['innodb_buffer_pool_size'] ?? 0),
                    'buffer_pool_pages_total' => (int) ($status['Innodb_buffer_pool_pages_total'] ?? 0),
                    'buffer_pool_pages_free' => (int) ($status['Innodb_buffer_pool_pages_free'] ?? 0),
                    'buffer_pool_hit_rate' => $this->calculateBufferPoolHitRate($status),
                    'log_waits' => (int) ($status['Innodb_log_waits'] ?? 0),
                    'rows_read' => (int) ($status['Innodb_rows_read'] ?? 0),
                    'rows_inserted' => (int) ($status['Innodb_rows_inserted'] ?? 0),
                    'rows_updated' => (int) ($status['Innodb_rows_updated'] ?? 0),
                    'rows_deleted' => (int) ($status['Innodb_rows_deleted'] ?? 0)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get database status: ' . $e->getMessage());
            return ['error' => 'Unable to retrieve database status'];
        }
    }

    /**
     * Analyze query performance
     */
    private function analyzeQueryPerformance(): array
    {
        try {
            // Get query performance statistics from performance_schema if available
            $queryStats = [];
            
            try {
                $performanceSchemaQueries = DB::select("
                    SELECT 
                        DIGEST_TEXT as query_pattern,
                        COUNT_STAR as execution_count,
                        ROUND(AVG_TIMER_WAIT / 1000000000, 3) as avg_execution_time,
                        ROUND(MAX_TIMER_WAIT / 1000000000, 3) as max_execution_time,
                        ROUND(SUM_ROWS_EXAMINED / COUNT_STAR, 0) as avg_rows_examined,
                        ROUND(SUM_ROWS_SENT / COUNT_STAR, 0) as avg_rows_sent
                    FROM performance_schema.events_statements_summary_by_digest
                    WHERE DIGEST_TEXT IS NOT NULL
                    ORDER BY AVG_TIMER_WAIT DESC
                    LIMIT 10
                ");

                foreach ($performanceSchemaQueries as $query) {
                    $queryStats[] = [
                        'query_pattern' => $this->truncateQuery($query->query_pattern),
                        'execution_count' => $query->execution_count,
                        'avg_execution_time' => $query->avg_execution_time,
                        'max_execution_time' => $query->max_execution_time,
                        'avg_rows_examined' => $query->avg_rows_examined,
                        'avg_rows_sent' => $query->avg_rows_sent,
                        'efficiency_ratio' => $query->avg_rows_examined > 0 ? 
                            round($query->avg_rows_sent / $query->avg_rows_examined, 4) : 1
                    ];
                }
            } catch (\Exception $e) {
                // Performance schema might not be available
                Log::info('Performance schema not available for query analysis');
            }

            return [
                'top_slow_queries' => $queryStats,
                'analysis_available' => !empty($queryStats),
                'total_analyzed' => count($queryStats)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to analyze query performance: ' . $e->getMessage());
            return ['error' => 'Unable to analyze query performance'];
        }
    }

    /**
     * Get slow queries from logs
     */
    private function getSlowQueries(): array
    {
        try {
            // Try to get slow queries from slow query log table
            $slowQueries = [];
            
            try {
                $slowQueryData = DB::select("
                    SELECT 
                        sql_text,
                        query_time,
                        rows_examined,
                        rows_sent,
                        start_time
                    FROM mysql.slow_log
                    WHERE start_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ORDER BY query_time DESC
                    LIMIT 20
                ");

                foreach ($slowQueryData as $query) {
                    $slowQueries[] = [
                        'sql_text' => $this->truncateQuery($query->sql_text),
                        'query_time' => $query->query_time,
                        'rows_examined' => $query->rows_examined,
                        'rows_sent' => $query->rows_sent,
                        'start_time' => $query->start_time,
                        'severity' => $query->query_time > $this->criticalQueryThreshold ? 'critical' : 'warning'
                    ];
                }
            } catch (\Exception $e) {
                // Slow query log table might not be available
                Log::info('Slow query log table not accessible');
            }

            return [
                'queries' => $slowQueries,
                'total_count' => count($slowQueries),
                'critical_count' => count(array_filter($slowQueries, fn($q) => $q['severity'] === 'critical'))
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get slow queries: ' . $e->getMessage());
            return ['error' => 'Unable to retrieve slow queries'];
        }
    }

    /**
     * Get connection metrics
     */
    private function getConnectionMetrics(): array
    {
        try {
            $processlist = DB::select('SHOW PROCESSLIST');
            
            $connections = [];
            $stateCount = [];
            $commandCount = [];
            $totalTime = 0;

            foreach ($processlist as $process) {
                $connections[] = [
                    'id' => $process->Id,
                    'user' => $process->User,
                    'host' => $process->Host,
                    'db' => $process->db,
                    'command' => $process->Command,
                    'time' => $process->Time,
                    'state' => $process->State ?? 'Unknown'
                ];

                $state = $process->State ?? 'Unknown';
                $command = $process->Command;
                
                $stateCount[$state] = ($stateCount[$state] ?? 0) + 1;
                $commandCount[$command] = ($commandCount[$command] ?? 0) + 1;
                $totalTime += $process->Time;
            }

            return [
                'active_connections' => count($connections),
                'average_connection_time' => count($connections) > 0 ? round($totalTime / count($connections), 2) : 0,
                'states' => $stateCount,
                'commands' => $commandCount,
                'top_long_running' => array_slice(
                    array_filter($connections, fn($c) => $c['time'] > 60),
                    0, 5
                )
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get connection metrics: ' . $e->getMessage());
            return ['error' => 'Unable to retrieve connection metrics'];
        }
    }

    /**
     * Get table statistics
     */
    private function getTableStatistics(): array
    {
        try {
            $tableStats = DB::select("
                SELECT 
                    TABLE_NAME,
                    TABLE_ROWS,
                    ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_size_mb,
                    ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_size_mb,
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as total_size_mb,
                    AUTO_INCREMENT,
                    TABLE_COLLATION,
                    ENGINE
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                LIMIT 20
            ");

            $totalSize = 0;
            $totalRows = 0;
            $tables = [];

            foreach ($tableStats as $table) {
                $tables[] = [
                    'name' => $table->TABLE_NAME,
                    'rows' => $table->TABLE_ROWS,
                    'data_size_mb' => $table->data_size_mb,
                    'index_size_mb' => $table->index_size_mb,
                    'total_size_mb' => $table->total_size_mb,
                    'auto_increment' => $table->AUTO_INCREMENT,
                    'engine' => $table->ENGINE,
                    'collation' => $table->TABLE_COLLATION
                ];

                $totalSize += $table->total_size_mb;
                $totalRows += $table->TABLE_ROWS;
            }

            return [
                'total_tables' => count($tables),
                'total_size_mb' => round($totalSize, 2),
                'total_rows' => $totalRows,
                'tables' => $tables,
                'largest_tables' => array_slice($tables, 0, 5)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get table statistics: ' . $e->getMessage());
            return ['error' => 'Unable to retrieve table statistics'];
        }
    }

    /**
     * Analyze index usage
     */
    private function analyzeIndexUsage(): array
    {
        try {
            // Get index statistics
            $indexStats = DB::select("
                SELECT 
                    s.TABLE_NAME,
                    s.INDEX_NAME,
                    s.COLUMN_NAME,
                    s.CARDINALITY,
                    s.INDEX_TYPE,
                    CASE WHEN s.NON_UNIQUE = 0 THEN 'UNIQUE' ELSE 'NON-UNIQUE' END as uniqueness
                FROM information_schema.STATISTICS s
                WHERE s.TABLE_SCHEMA = DATABASE()
                ORDER BY s.TABLE_NAME, s.INDEX_NAME, s.SEQ_IN_INDEX
            ");

            // Check for unused indexes (if performance_schema is available)
            $unusedIndexes = [];
            try {
                $unusedIndexes = DB::select("
                    SELECT 
                        object_schema,
                        object_name,
                        index_name
                    FROM performance_schema.table_io_waits_summary_by_index_usage
                    WHERE object_schema = DATABASE()
                    AND index_name IS NOT NULL
                    AND index_name != 'PRIMARY'
                    AND count_star = 0
                    LIMIT 10
                ");
            } catch (\Exception $e) {
                // Performance schema might not be available
            }

            // Group indexes by table
            $indexesByTable = [];
            foreach ($indexStats as $index) {
                $indexesByTable[$index->TABLE_NAME][] = [
                    'name' => $index->INDEX_NAME,
                    'column' => $index->COLUMN_NAME,
                    'cardinality' => $index->CARDINALITY,
                    'type' => $index->INDEX_TYPE,
                    'uniqueness' => $index->uniqueness
                ];
            }

            return [
                'total_indexes' => count($indexStats),
                'indexes_by_table' => $indexesByTable,
                'unused_indexes' => $unusedIndexes,
                'tables_without_indexes' => $this->findTablesWithoutIndexes(),
                'duplicate_indexes' => $this->findDuplicateIndexes($indexesByTable)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to analyze index usage: ' . $e->getMessage());
            return ['error' => 'Unable to analyze index usage'];
        }
    }

    /**
     * Get memory usage statistics
     */
    private function getMemoryUsage(): array
    {
        try {
            $memoryStats = [];
            
            // Get InnoDB buffer pool usage
            $bufferPoolStats = DB::select("
                SELECT 
                    ROUND(
                        (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='Innodb_buffer_pool_pages_total') * 
                        (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_VARIABLES WHERE VARIABLE_NAME='innodb_page_size') / 1024 / 1024
                    ) as buffer_pool_size_mb,
                    ROUND(
                        (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='Innodb_buffer_pool_pages_free') * 
                        (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_VARIABLES WHERE VARIABLE_NAME='innodb_page_size') / 1024 / 1024
                    ) as buffer_pool_free_mb
            ");

            $bufferPoolTotal = $bufferPoolStats[0]->buffer_pool_size_mb ?? 0;
            $bufferPoolFree = $bufferPoolStats[0]->buffer_pool_free_mb ?? 0;
            $bufferPoolUsed = $bufferPoolTotal - $bufferPoolFree;

            return [
                'buffer_pool' => [
                    'total_mb' => $bufferPoolTotal,
                    'used_mb' => $bufferPoolUsed,
                    'free_mb' => $bufferPoolFree,
                    'usage_percentage' => $bufferPoolTotal > 0 ? round(($bufferPoolUsed / $bufferPoolTotal) * 100, 2) : 0
                ],
                'query_cache' => $this->getQueryCacheStats(),
                'temporary_tables' => $this->getTemporaryTableStats()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get memory usage: ' . $e->getMessage());
            return ['error' => 'Unable to retrieve memory usage'];
        }
    }

    /**
     * Get cache performance metrics
     */
    private function getCachePerformance(): array
    {
        try {
            // Redis cache metrics
            $redisInfo = $this->redis->info();
            $redisMemory = $redisInfo['used_memory_human'] ?? 'unknown';
            $redisConnections = $redisInfo['connected_clients'] ?? 0;
            $redisHits = $redisInfo['keyspace_hits'] ?? 0;
            $redisMisses = $redisInfo['keyspace_misses'] ?? 0;
            $totalRequests = $redisHits + $redisMisses;
            $hitRate = $totalRequests > 0 ? round(($redisHits / $totalRequests) * 100, 2) : 0;

            // Application cache metrics
            $cacheKeys = Cache::getRedis()->keys('mrvl_*');
            $cacheKeyCount = count($cacheKeys);

            return [
                'redis' => [
                    'memory_usage' => $redisMemory,
                    'connected_clients' => $redisConnections,
                    'keyspace_hits' => $redisHits,
                    'keyspace_misses' => $redisMisses,
                    'hit_rate_percentage' => $hitRate
                ],
                'application_cache' => [
                    'total_keys' => $cacheKeyCount,
                    'tournament_keys' => count(Cache::getRedis()->keys('mrvl_tournament_*')),
                    'query_keys' => count(Cache::getRedis()->keys('mrvl_query_*')),
                    'elo_keys' => count(Cache::getRedis()->keys('elo_*'))
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cache performance: ' . $e->getMessage());
            return ['error' => 'Unable to retrieve cache performance'];
        }
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Database status recommendations
        if (isset($analysis['database_status']['queries']['slow_query_percentage'])) {
            $slowQueryPercentage = $analysis['database_status']['queries']['slow_query_percentage'];
            if ($slowQueryPercentage > 5) {
                $recommendations[] = [
                    'type' => 'slow_queries',
                    'priority' => 'high',
                    'message' => "High percentage of slow queries ({$slowQueryPercentage}%). Consider query optimization."
                ];
            }
        }

        // Connection recommendations
        if (isset($analysis['connection_metrics']['active_connections'])) {
            $activeConnections = $analysis['connection_metrics']['active_connections'];
            if ($activeConnections > 50) {
                $recommendations[] = [
                    'type' => 'connections',
                    'priority' => 'medium',
                    'message' => "High number of active connections ({$activeConnections}). Consider connection pooling."
                ];
            }
        }

        // Memory recommendations
        if (isset($analysis['memory_usage']['buffer_pool']['usage_percentage'])) {
            $bufferPoolUsage = $analysis['memory_usage']['buffer_pool']['usage_percentage'];
            if ($bufferPoolUsage > 90) {
                $recommendations[] = [
                    'type' => 'memory',
                    'priority' => 'high',
                    'message' => "InnoDB buffer pool usage is very high ({$bufferPoolUsage}%). Consider increasing buffer pool size."
                ];
            }
        }

        // Cache recommendations
        if (isset($analysis['cache_performance']['redis']['hit_rate_percentage'])) {
            $hitRate = $analysis['cache_performance']['redis']['hit_rate_percentage'];
            if ($hitRate < 80) {
                $recommendations[] = [
                    'type' => 'cache',
                    'priority' => 'medium',
                    'message' => "Redis cache hit rate is low ({$hitRate}%). Review cache strategies."
                ];
            }
        }

        // Table size recommendations
        if (isset($analysis['table_statistics']['largest_tables'])) {
            foreach ($analysis['table_statistics']['largest_tables'] as $table) {
                if ($table['total_size_mb'] > 1000) { // Tables larger than 1GB
                    $recommendations[] = [
                        'type' => 'table_size',
                        'priority' => 'medium',
                        'message' => "Table {$table['name']} is very large ({$table['total_size_mb']}MB). Consider archiving or partitioning."
                    ];
                }
            }
        }

        // Index recommendations
        if (isset($analysis['index_analysis']['unused_indexes']) && !empty($analysis['index_analysis']['unused_indexes'])) {
            $unusedCount = count($analysis['index_analysis']['unused_indexes']);
            $recommendations[] = [
                'type' => 'indexes',
                'priority' => 'low',
                'message' => "Found {$unusedCount} unused indexes. Consider removing them to improve write performance."
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate overall performance score
     */
    private function calculatePerformanceScore(array $analysis): int
    {
        $score = 100;

        // Deduct points for issues
        if (isset($analysis['database_status']['queries']['slow_query_percentage'])) {
            $slowQueryPercentage = $analysis['database_status']['queries']['slow_query_percentage'];
            $score -= min(20, $slowQueryPercentage * 2);
        }

        if (isset($analysis['slow_queries']['critical_count'])) {
            $score -= $analysis['slow_queries']['critical_count'] * 5;
        }

        if (isset($analysis['memory_usage']['buffer_pool']['usage_percentage'])) {
            $bufferPoolUsage = $analysis['memory_usage']['buffer_pool']['usage_percentage'];
            if ($bufferPoolUsage > 95) {
                $score -= 15;
            } elseif ($bufferPoolUsage > 85) {
                $score -= 10;
            }
        }

        if (isset($analysis['cache_performance']['redis']['hit_rate_percentage'])) {
            $hitRate = $analysis['cache_performance']['redis']['hit_rate_percentage'];
            if ($hitRate < 70) {
                $score -= 15;
            } elseif ($hitRate < 80) {
                $score -= 10;
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * Store performance metrics for trending
     */
    private function storePerformanceMetrics(array $analysis): void
    {
        $metrics = [
            'timestamp' => time(),
            'overall_score' => $analysis['overall_score'],
            'slow_queries_count' => $analysis['slow_queries']['total_count'] ?? 0,
            'active_connections' => $analysis['connection_metrics']['active_connections'] ?? 0,
            'buffer_pool_usage' => $analysis['memory_usage']['buffer_pool']['usage_percentage'] ?? 0,
            'cache_hit_rate' => $analysis['cache_performance']['redis']['hit_rate_percentage'] ?? 0
        ];

        // Store in Redis with 7 days retention
        $this->redis->zadd($this->cachePrefix . 'metrics_timeline', $metrics['timestamp'], json_encode($metrics));
        $this->redis->expire($this->cachePrefix . 'metrics_timeline', 604800); // 7 days
    }

    /**
     * Get performance trends
     */
    public function getPerformanceTrends(int $hours = 24): array
    {
        $since = time() - ($hours * 3600);
        $metrics = $this->redis->zrangebyscore(
            $this->cachePrefix . 'metrics_timeline',
            $since,
            '+inf',
            ['withscores' => true]
        );

        $trends = [];
        foreach ($metrics as $metric => $timestamp) {
            $data = json_decode($metric, true);
            $trends[] = $data;
        }

        return [
            'period_hours' => $hours,
            'data_points' => count($trends),
            'metrics' => $trends,
            'summary' => $this->calculateTrendSummary($trends)
        ];
    }

    /**
     * Helper methods
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return "{$days}d {$hours}h {$minutes}m";
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    private function calculateBufferPoolHitRate(array $status): float
    {
        $reads = $status['Innodb_buffer_pool_reads'] ?? 0;
        $readRequests = $status['Innodb_buffer_pool_read_requests'] ?? 0;
        
        if ($readRequests == 0) return 100;
        
        return round((1 - ($reads / $readRequests)) * 100, 2);
    }

    private function truncateQuery(string $query, int $length = 100): string
    {
        return strlen($query) > $length ? substr($query, 0, $length) . '...' : $query;
    }

    private function getQueryCacheStats(): array
    {
        try {
            $stats = DB::select("SHOW STATUS LIKE 'Qcache%'");
            $qcacheStats = [];
            foreach ($stats as $stat) {
                $qcacheStats[$stat->Variable_name] = $stat->Value;
            }
            
            $hitRate = 0;
            if (isset($qcacheStats['Qcache_hits']) && isset($qcacheStats['Qcache_queries_in_cache'])) {
                $hits = $qcacheStats['Qcache_hits'];
                $queries = $qcacheStats['Qcache_queries_in_cache'];
                $hitRate = $queries > 0 ? round(($hits / $queries) * 100, 2) : 0;
            }
            
            return [
                'hit_rate_percentage' => $hitRate,
                'free_memory' => $this->formatBytes($qcacheStats['Qcache_free_memory'] ?? 0),
                'queries_in_cache' => $qcacheStats['Qcache_queries_in_cache'] ?? 0
            ];
        } catch (\Exception $e) {
            return ['error' => 'Query cache stats not available'];
        }
    }

    private function getTemporaryTableStats(): array
    {
        try {
            $stats = DB::select("SHOW STATUS LIKE '%tmp%'");
            $tmpStats = [];
            foreach ($stats as $stat) {
                $tmpStats[$stat->Variable_name] = $stat->Value;
            }
            
            return [
                'created_tmp_tables' => $tmpStats['Created_tmp_tables'] ?? 0,
                'created_tmp_disk_tables' => $tmpStats['Created_tmp_disk_tables'] ?? 0,
                'disk_percentage' => isset($tmpStats['Created_tmp_tables']) && $tmpStats['Created_tmp_tables'] > 0 ?
                    round(($tmpStats['Created_tmp_disk_tables'] / $tmpStats['Created_tmp_tables']) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            return ['error' => 'Temporary table stats not available'];
        }
    }

    private function findTablesWithoutIndexes(): array
    {
        try {
            return DB::select("
                SELECT TABLE_NAME
                FROM information_schema.TABLES t
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_TYPE = 'BASE TABLE'
                AND NOT EXISTS (
                    SELECT 1 FROM information_schema.STATISTICS s
                    WHERE s.TABLE_SCHEMA = t.TABLE_SCHEMA
                    AND s.TABLE_NAME = t.TABLE_NAME
                    AND s.INDEX_NAME != 'PRIMARY'
                )
            ");
        } catch (\Exception $e) {
            return [];
        }
    }

    private function findDuplicateIndexes(array $indexesByTable): array
    {
        $duplicates = [];
        // Implementation for finding duplicate indexes would go here
        return $duplicates;
    }

    private function calculateTrendSummary(array $trends): array
    {
        if (empty($trends)) {
            return ['error' => 'No trend data available'];
        }

        $latest = end($trends);
        $earliest = reset($trends);

        return [
            'performance_change' => $latest['overall_score'] - $earliest['overall_score'],
            'avg_score' => round(array_sum(array_column($trends, 'overall_score')) / count($trends), 1),
            'max_score' => max(array_column($trends, 'overall_score')),
            'min_score' => min(array_column($trends, 'overall_score'))
        ];
    }

    /**
     * Real-time alerting for critical issues
     */
    public function checkForCriticalIssues(): array
    {
        $alerts = [];
        
        try {
            // Check for long-running queries
            $longRunningQueries = DB::select("
                SELECT Id, User, Host, db, Command, Time, State
                FROM information_schema.PROCESSLIST
                WHERE Command != 'Sleep' AND Time > 300
                LIMIT 5
            ");

            if (!empty($longRunningQueries)) {
                $alerts[] = [
                    'type' => 'long_running_queries',
                    'severity' => 'critical',
                    'count' => count($longRunningQueries),
                    'message' => 'Found ' . count($longRunningQueries) . ' long-running queries (>5 minutes)'
                ];
            }

            // Check connection count
            $connectionCount = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'")[0]->Value ?? 0;
            
            if ($connectionCount > ($maxConnections * 0.8)) {
                $alerts[] = [
                    'type' => 'high_connections',
                    'severity' => 'warning',
                    'current' => $connectionCount,
                    'max' => $maxConnections,
                    'message' => "High connection usage: {$connectionCount}/{$maxConnections}"
                ];
            }

            // Check for deadlocks
            $deadlocks = DB::select("SHOW STATUS LIKE 'Innodb_deadlocks'")[0]->Value ?? 0;
            if ($deadlocks > 0) {
                $alerts[] = [
                    'type' => 'deadlocks',
                    'severity' => 'warning',
                    'count' => $deadlocks,
                    'message' => "Detected {$deadlocks} deadlocks"
                ];
            }

        } catch (\Exception $e) {
            $alerts[] = [
                'type' => 'monitoring_error',
                'severity' => 'critical',
                'message' => 'Unable to check for critical issues: ' . $e->getMessage()
            ];
        }

        return [
            'timestamp' => now()->toISOString(),
            'alerts' => $alerts,
            'critical_count' => count(array_filter($alerts, fn($a) => $a['severity'] === 'critical')),
            'warning_count' => count(array_filter($alerts, fn($a) => $a['severity'] === 'warning'))
        ];
    }
}