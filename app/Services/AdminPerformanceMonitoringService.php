<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminPerformanceMonitoringService
{
    const SLOW_QUERY_THRESHOLD_MS = 100;
    const CACHE_TTL = 300; // 5 minutes

    /**
     * Monitor and log query performance
     */
    public function monitorQuery($queryName, callable $queryFunction, $logSlowQueries = true)
    {
        $startTime = microtime(true);
        
        try {
            $result = $queryFunction();
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Log slow queries
            if ($logSlowQueries && $executionTime > self::SLOW_QUERY_THRESHOLD_MS) {
                Log::warning('Slow admin query detected', [
                    'query_name' => $queryName,
                    'execution_time_ms' => round($executionTime, 2),
                    'threshold_ms' => self::SLOW_QUERY_THRESHOLD_MS
                ]);
            }
            
            // Store performance metrics
            $this->recordQueryPerformance($queryName, $executionTime);
            
            return [
                'result' => $result,
                'execution_time' => $executionTime,
                'success' => true
            ];
            
        } catch (\Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            Log::error('Admin query failed', [
                'query_name' => $queryName,
                'execution_time_ms' => round($executionTime, 2),
                'error' => $e->getMessage()
            ]);
            
            return [
                'result' => null,
                'execution_time' => $executionTime,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Record query performance metrics in cache
     */
    private function recordQueryPerformance($queryName, $executionTime)
    {
        $cacheKey = 'admin_query_performance_' . date('Y-m-d-H');
        $metrics = Cache::get($cacheKey, []);
        
        if (!isset($metrics[$queryName])) {
            $metrics[$queryName] = [
                'count' => 0,
                'total_time' => 0,
                'max_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'slow_queries' => 0
            ];
        }
        
        $metrics[$queryName]['count']++;
        $metrics[$queryName]['total_time'] += $executionTime;
        $metrics[$queryName]['max_time'] = max($metrics[$queryName]['max_time'], $executionTime);
        $metrics[$queryName]['min_time'] = min($metrics[$queryName]['min_time'], $executionTime);
        
        if ($executionTime > self::SLOW_QUERY_THRESHOLD_MS) {
            $metrics[$queryName]['slow_queries']++;
        }
        
        Cache::put($cacheKey, $metrics, self::CACHE_TTL);
    }

    /**
     * Get query performance statistics
     */
    public function getQueryPerformanceStats($hours = 24)
    {
        $stats = [];
        
        for ($i = 0; $i < $hours; $i++) {
            $hour = date('Y-m-d-H', strtotime("-{$i} hours"));
            $cacheKey = 'admin_query_performance_' . $hour;
            $hourMetrics = Cache::get($cacheKey, []);
            
            foreach ($hourMetrics as $queryName => $metrics) {
                if (!isset($stats[$queryName])) {
                    $stats[$queryName] = [
                        'count' => 0,
                        'total_time' => 0,
                        'max_time' => 0,
                        'min_time' => PHP_FLOAT_MAX,
                        'slow_queries' => 0
                    ];
                }
                
                $stats[$queryName]['count'] += $metrics['count'];
                $stats[$queryName]['total_time'] += $metrics['total_time'];
                $stats[$queryName]['max_time'] = max($stats[$queryName]['max_time'], $metrics['max_time']);
                $stats[$queryName]['min_time'] = min($stats[$queryName]['min_time'], $metrics['min_time']);
                $stats[$queryName]['slow_queries'] += $metrics['slow_queries'];
            }
        }
        
        // Calculate averages
        foreach ($stats as $queryName => &$metrics) {
            if ($metrics['count'] > 0) {
                $metrics['avg_time'] = $metrics['total_time'] / $metrics['count'];
                $metrics['slow_query_percentage'] = ($metrics['slow_queries'] / $metrics['count']) * 100;
            }
            
            if ($metrics['min_time'] === PHP_FLOAT_MAX) {
                $metrics['min_time'] = 0;
            }
        }
        
        return $stats;
    }

    /**
     * Monitor database connection pool
     */
    public function monitorConnectionPool()
    {
        try {
            $connections = DB::select("SHOW STATUS WHERE Variable_name LIKE 'Threads_%' OR Variable_name LIKE 'Connections'");
            
            $metrics = [];
            foreach ($connections as $connection) {
                $metrics[$connection->Variable_name] = $connection->Value;
            }
            
            return [
                'active_connections' => $metrics['Threads_connected'] ?? 0,
                'total_connections' => $metrics['Connections'] ?? 0,
                'cached_threads' => $metrics['Threads_cached'] ?? 0,
                'running_threads' => $metrics['Threads_running'] ?? 0,
                'success' => true
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to monitor database connections', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'active_connections' => 'unknown',
                'total_connections' => 'unknown',
                'cached_threads' => 'unknown',
                'running_threads' => 'unknown',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Monitor cache hit rates
     */
    public function monitorCachePerformance()
    {
        $cacheKey = 'admin_cache_stats_' . date('Y-m-d-H');
        $stats = Cache::get($cacheKey, [
            'hits' => 0,
            'misses' => 0,
            'total_requests' => 0
        ]);
        
        $hitRate = $stats['total_requests'] > 0 
            ? ($stats['hits'] / $stats['total_requests']) * 100 
            : 0;
        
        return [
            'cache_hits' => $stats['hits'],
            'cache_misses' => $stats['misses'],
            'total_requests' => $stats['total_requests'],
            'hit_rate_percentage' => round($hitRate, 2),
            'cache_driver' => config('cache.default'),
            'success' => true
        ];
    }

    /**
     * Record cache hit/miss
     */
    public function recordCacheHit($hit = true)
    {
        $cacheKey = 'admin_cache_stats_' . date('Y-m-d-H');
        $stats = Cache::get($cacheKey, [
            'hits' => 0,
            'misses' => 0,
            'total_requests' => 0
        ]);
        
        $stats['total_requests']++;
        if ($hit) {
            $stats['hits']++;
        } else {
            $stats['misses']++;
        }
        
        Cache::put($cacheKey, $stats, self::CACHE_TTL);
    }

    /**
     * Monitor table sizes and growth
     */
    public function monitorTableSizes()
    {
        try {
            $dbName = config('database.connections.mysql.database');
            
            $tables = DB::select("
                SELECT 
                    table_name,
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size
                FROM information_schema.tables 
                WHERE table_schema = ?
                AND table_name IN ('players', 'teams', 'matches', 'user_activities', 'news', 'forum_threads')
                ORDER BY total_size DESC
            ", [$dbName]);
            
            $formattedTables = collect($tables)->map(function($table) {
                return [
                    'table_name' => $table->table_name,
                    'row_count' => number_format($table->table_rows),
                    'data_size' => $this->formatBytes($table->data_length),
                    'index_size' => $this->formatBytes($table->index_length),
                    'total_size' => $this->formatBytes($table->total_size),
                    'data_size_bytes' => $table->data_length,
                    'index_size_bytes' => $table->index_length,
                    'total_size_bytes' => $table->total_size
                ];
            });
            
            return [
                'tables' => $formattedTables,
                'total_database_size' => $this->formatBytes($formattedTables->sum('total_size_bytes')),
                'success' => true
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to monitor table sizes', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'tables' => [],
                'total_database_size' => 'unknown',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Monitor slow query log
     */
    public function getSlowQueryLog($limit = 10)
    {
        try {
            // Enable slow query log analysis if available
            $slowQueries = DB::select("
                SELECT 
                    start_time,
                    query_time,
                    lock_time,
                    rows_sent,
                    rows_examined,
                    sql_text
                FROM mysql.slow_log 
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY query_time DESC 
                LIMIT ?
            ", [$limit]);
            
            return [
                'slow_queries' => collect($slowQueries)->map(function($query) {
                    return [
                        'start_time' => $query->start_time,
                        'query_time' => $query->query_time,
                        'lock_time' => $query->lock_time,
                        'rows_sent' => $query->rows_sent,
                        'rows_examined' => $query->rows_examined,
                        'sql_text' => substr($query->sql_text, 0, 200) . '...'
                    ];
                }),
                'success' => true
            ];
            
        } catch (\Exception $e) {
            // Slow query log might not be enabled or accessible
            return [
                'slow_queries' => [],
                'success' => false,
                'message' => 'Slow query log not available or not enabled'
            ];
        }
    }

    /**
     * Generate comprehensive performance report
     */
    public function generatePerformanceReport()
    {
        return [
            'query_performance' => $this->getQueryPerformanceStats(),
            'connection_pool' => $this->monitorConnectionPool(),
            'cache_performance' => $this->monitorCachePerformance(),
            'table_sizes' => $this->monitorTableSizes(),
            'slow_queries' => $this->getSlowQueryLog(),
            'recommendations' => $this->generateRecommendations(),
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations()
    {
        $recommendations = [];
        $queryStats = $this->getQueryPerformanceStats();
        $cacheStats = $this->monitorCachePerformance();
        
        // Query performance recommendations
        foreach ($queryStats as $queryName => $stats) {
            if ($stats['avg_time'] > self::SLOW_QUERY_THRESHOLD_MS) {
                $recommendations[] = [
                    'type' => 'warning',
                    'category' => 'query_performance',
                    'message' => "Query '{$queryName}' has slow average execution time ({$stats['avg_time']}ms)",
                    'suggestion' => 'Consider adding indexes or optimizing the query structure'
                ];
            }
            
            if ($stats['slow_query_percentage'] > 20) {
                $recommendations[] = [
                    'type' => 'error',
                    'category' => 'query_performance',
                    'message' => "Query '{$queryName}' has high slow query rate ({$stats['slow_query_percentage']}%)",
                    'suggestion' => 'Urgent optimization needed - review indexes and query structure'
                ];
            }
        }
        
        // Cache performance recommendations
        if ($cacheStats['hit_rate_percentage'] < 80) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'cache_performance',
                'message' => "Cache hit rate is low ({$cacheStats['hit_rate_percentage']}%)",
                'suggestion' => 'Consider increasing cache TTL or reviewing cache key strategies'
            ];
        }
        
        // Add default recommendation if no issues found
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'category' => 'overall',
                'message' => 'Database performance is optimal',
                'suggestion' => 'Continue monitoring for optimal performance'
            ];
        }
        
        return $recommendations;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Clear performance monitoring cache
     */
    public function clearPerformanceCache()
    {
        $patterns = [
            'admin_query_performance_*',
            'admin_cache_stats_*'
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
        
        Log::info('Performance monitoring cache cleared');
    }
}