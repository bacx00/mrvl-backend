<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoringService
{
    /**
     * Monitor and log performance metrics
     */
    public function captureMetrics()
    {
        $metrics = [
            'timestamp' => now(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'api' => $this->getApiMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'load' => $this->getSystemLoad()
        ];
        
        // Store metrics for analysis
        $this->storeMetrics($metrics);
        
        // Check for performance issues
        $this->checkPerformanceThresholds($metrics);
        
        return $metrics;
    }
    
    /**
     * Get database performance metrics
     */
    private function getDatabaseMetrics()
    {
        $metrics = [];
        
        try {
            // Query execution time statistics
            $slowQueries = DB::select("
                SELECT COUNT(*) as count, AVG(execution_time) as avg_time, MAX(execution_time) as max_time
                FROM query_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                AND execution_time > 100
            ");
            
            $metrics['slow_queries'] = $slowQueries[0] ?? null;
            
            // Connection pool status
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $metrics['active_connections'] = $connections[0]->Value ?? 0;
            
            // Table statistics
            $tableStats = DB::select("
                SELECT 
                    table_name,
                    table_rows,
                    data_length + index_length as size_bytes
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY size_bytes DESC
                LIMIT 5
            ");
            
            $metrics['largest_tables'] = $tableStats;
            
            // Index usage
            $indexStats = DB::select("
                SELECT 
                    table_name,
                    index_name,
                    cardinality
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND cardinality > 0
                ORDER BY cardinality DESC
                LIMIT 10
            ");
            
            $metrics['index_usage'] = $indexStats;
            
        } catch (\Exception $e) {
            Log::error('Failed to get database metrics: ' . $e->getMessage());
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get cache performance metrics
     */
    private function getCacheMetrics()
    {
        $cacheService = app(EnhancedCacheService::class);
        return $cacheService->getStatistics();
    }
    
    /**
     * Get API response time metrics
     */
    private function getApiMetrics()
    {
        return Cache::remember('api_metrics', 60, function() {
            $endpoints = [
                '/api/tournaments' => [],
                '/api/teams' => [],
                '/api/players' => [],
                '/api/matches' => [],
                '/api/news' => []
            ];
            
            foreach ($endpoints as $endpoint => &$metrics) {
                $logs = DB::table('api_logs')
                    ->where('endpoint', $endpoint)
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->select(DB::raw('
                        COUNT(*) as requests,
                        AVG(response_time) as avg_response_time,
                        MAX(response_time) as max_response_time,
                        MIN(response_time) as min_response_time,
                        SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors
                    '))
                    ->first();
                
                $metrics = $logs ? (array)$logs : [
                    'requests' => 0,
                    'avg_response_time' => 0,
                    'max_response_time' => 0,
                    'min_response_time' => 0,
                    'errors' => 0
                ];
            }
            
            return $endpoints;
        });
    }
    
    /**
     * Get memory usage metrics
     */
    private function getMemoryMetrics()
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'usage_percentage' => round((memory_get_usage(true) / $this->parseMemoryLimit()) * 100, 2)
        ];
    }
    
    /**
     * Get system load metrics
     */
    private function getSystemLoad()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1_min' => $load[0] ?? 0,
                '5_min' => $load[1] ?? 0,
                '15_min' => $load[2] ?? 0
            ];
        }
        
        return null;
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit()
    {
        $limit = ini_get('memory_limit');
        
        if ($limit == -1) {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int)$limit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }
    
    /**
     * Store metrics for historical analysis
     */
    private function storeMetrics($metrics)
    {
        try {
            DB::table('performance_metrics')->insert([
                'metrics' => json_encode($metrics),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Clean up old metrics (keep last 7 days)
            DB::table('performance_metrics')
                ->where('created_at', '<', now()->subDays(7))
                ->delete();
                
        } catch (\Exception $e) {
            Log::error('Failed to store performance metrics: ' . $e->getMessage());
        }
    }
    
    /**
     * Check performance thresholds and alert if needed
     */
    private function checkPerformanceThresholds($metrics)
    {
        $alerts = [];
        
        // Check database connections
        if (isset($metrics['database']['active_connections']) && 
            $metrics['database']['active_connections'] > 100) {
            $alerts[] = 'High database connection count: ' . $metrics['database']['active_connections'];
        }
        
        // Check memory usage
        if (isset($metrics['memory']['usage_percentage']) && 
            $metrics['memory']['usage_percentage'] > 80) {
            $alerts[] = 'High memory usage: ' . $metrics['memory']['usage_percentage'] . '%';
        }
        
        // Check system load
        if (isset($metrics['load']['1_min']) && 
            $metrics['load']['1_min'] > 4) {
            $alerts[] = 'High system load: ' . $metrics['load']['1_min'];
        }
        
        // Check cache hit rate
        if (isset($metrics['cache']['redis_stats']['hit_rate']) && 
            $metrics['cache']['redis_stats']['hit_rate'] < 80) {
            $alerts[] = 'Low cache hit rate: ' . $metrics['cache']['redis_stats']['hit_rate'] . '%';
        }
        
        // Log alerts
        if (!empty($alerts)) {
            foreach ($alerts as $alert) {
                Log::warning('Performance Alert: ' . $alert);
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get performance report
     */
    public function getPerformanceReport($period = 'last_hour')
    {
        $startTime = $this->getStartTime($period);
        
        $metrics = DB::table('performance_metrics')
            ->where('created_at', '>=', $startTime)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($metric) {
                return json_decode($metric->metrics, true);
            });
        
        if ($metrics->isEmpty()) {
            return [
                'period' => $period,
                'message' => 'No metrics available for this period'
            ];
        }
        
        return [
            'period' => $period,
            'start_time' => $startTime,
            'end_time' => now(),
            'summary' => $this->generateSummary($metrics),
            'recommendations' => $this->generateRecommendations($metrics),
            'detailed_metrics' => $metrics->take(10)
        ];
    }
    
    /**
     * Get start time based on period
     */
    private function getStartTime($period)
    {
        switch ($period) {
            case 'last_15_min':
                return now()->subMinutes(15);
            case 'last_hour':
                return now()->subHour();
            case 'last_day':
                return now()->subDay();
            case 'last_week':
                return now()->subWeek();
            default:
                return now()->subHour();
        }
    }
    
    /**
     * Generate performance summary
     */
    private function generateSummary($metrics)
    {
        $summary = [
            'total_samples' => $metrics->count(),
            'avg_memory_usage' => 0,
            'avg_db_connections' => 0,
            'avg_system_load' => 0,
            'cache_hit_rate' => 0,
            'api_response_times' => []
        ];
        
        $memorySum = 0;
        $dbConnSum = 0;
        $loadSum = 0;
        $cacheHitSum = 0;
        $validSamples = 0;
        
        foreach ($metrics as $metric) {
            if (isset($metric['memory']['usage_percentage'])) {
                $memorySum += $metric['memory']['usage_percentage'];
            }
            
            if (isset($metric['database']['active_connections'])) {
                $dbConnSum += $metric['database']['active_connections'];
            }
            
            if (isset($metric['load']['1_min'])) {
                $loadSum += $metric['load']['1_min'];
            }
            
            if (isset($metric['cache']['redis_stats']['hit_rate'])) {
                $cacheHitSum += $metric['cache']['redis_stats']['hit_rate'];
                $validSamples++;
            }
        }
        
        $count = $metrics->count();
        
        $summary['avg_memory_usage'] = $count > 0 ? round($memorySum / $count, 2) : 0;
        $summary['avg_db_connections'] = $count > 0 ? round($dbConnSum / $count, 2) : 0;
        $summary['avg_system_load'] = $count > 0 ? round($loadSum / $count, 2) : 0;
        $summary['cache_hit_rate'] = $validSamples > 0 ? round($cacheHitSum / $validSamples, 2) : 0;
        
        return $summary;
    }
    
    /**
     * Generate performance recommendations
     */
    private function generateRecommendations($metrics)
    {
        $recommendations = [];
        $summary = $this->generateSummary($metrics);
        
        // Memory recommendations
        if ($summary['avg_memory_usage'] > 70) {
            $recommendations[] = [
                'type' => 'memory',
                'severity' => 'high',
                'message' => 'Consider increasing PHP memory limit or optimizing memory usage',
                'action' => 'Review memory-intensive operations and implement pagination'
            ];
        }
        
        // Database recommendations
        if ($summary['avg_db_connections'] > 50) {
            $recommendations[] = [
                'type' => 'database',
                'severity' => 'medium',
                'message' => 'High database connection count detected',
                'action' => 'Consider implementing connection pooling or optimizing queries'
            ];
        }
        
        // Cache recommendations
        if ($summary['cache_hit_rate'] < 80) {
            $recommendations[] = [
                'type' => 'cache',
                'severity' => 'medium',
                'message' => 'Low cache hit rate detected',
                'action' => 'Review cache keys and TTL settings, consider cache warming strategies'
            ];
        }
        
        // System load recommendations
        if ($summary['avg_system_load'] > 2) {
            $recommendations[] = [
                'type' => 'system',
                'severity' => 'high',
                'message' => 'High system load detected',
                'action' => 'Consider scaling resources or optimizing resource-intensive operations'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Run performance optimization
     */
    public function runOptimization()
    {
        $results = [];
        
        // Optimize database
        $results['database'] = app(DatabaseOptimizationService::class)->optimizeDatabase();
        
        // Optimize cache
        $results['cache'] = app(EnhancedCacheService::class)->optimize();
        
        // Clean up old data
        $results['cleanup'] = $this->cleanupOldData();
        
        // Warm up critical caches
        $results['warmup'] = app(EnhancedCacheService::class)->warmUp();
        
        Log::info('Performance optimization completed', $results);
        
        return $results;
    }
    
    /**
     * Clean up old data
     */
    private function cleanupOldData()
    {
        try {
            // Clean old API logs
            $apiLogs = DB::table('api_logs')
                ->where('created_at', '<', now()->subDays(30))
                ->delete();
            
            // Clean old performance metrics
            $perfMetrics = DB::table('performance_metrics')
                ->where('created_at', '<', now()->subDays(7))
                ->delete();
            
            // Clean old cache entries
            Cache::flush();
            
            return [
                'api_logs_deleted' => $apiLogs,
                'performance_metrics_deleted' => $perfMetrics,
                'cache_flushed' => true
            ];
            
        } catch (\Exception $e) {
            Log::error('Cleanup failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}