<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Tournament Query Monitoring Service
 * 
 * Provides comprehensive monitoring for tournament database operations:
 * - Query performance tracking
 * - Index usage analysis
 * - Slow query detection
 * - Real-time performance metrics
 * - Automated alerting for performance issues
 */
class TournamentQueryMonitoringService
{
    private const SLOW_QUERY_THRESHOLD = 1.0; // 1 second
    private const ALERT_THRESHOLD_CPU = 80.0; // 80% CPU usage
    private const MONITORING_CACHE_TTL = 60; // 1 minute

    /**
     * Monitor critical tournament queries
     */
    public function monitorTournamentQueries(): array
    {
        $metrics = [
            'timestamp' => now(),
            'query_performance' => $this->measureQueryPerformance(),
            'index_effectiveness' => $this->analyzeIndexEffectiveness(),
            'connection_health' => $this->checkConnectionHealth(),
            'cache_performance' => $this->analyzeCachePerformance(),
            'alerts' => []
        ];

        // Generate alerts based on metrics
        $metrics['alerts'] = $this->generatePerformanceAlerts($metrics);

        // Store metrics for trending
        $this->storeMetricsHistory($metrics);

        return $metrics;
    }

    /**
     * Measure performance of critical tournament queries
     */
    private function measureQueryPerformance(): array
    {
        $queries = [
            'bracket_complete' => [
                'query' => "
                    SELECT bm.*, t1.name as team1_name, t2.name as team2_name
                    FROM bracket_matches bm
                    LEFT JOIN teams t1 ON bm.team1_id = t1.id
                    LEFT JOIN teams t2 ON bm.team2_id = t2.id
                    ORDER BY bm.round_number, bm.match_number
                    LIMIT 50
                ",
                'description' => 'Complete bracket retrieval'
            ],
            'live_matches' => [
                'query' => "
                    SELECT bm.*, t1.name as team1_name, t2.name as team2_name
                    FROM bracket_matches bm
                    LEFT JOIN teams t1 ON bm.team1_id = t1.id
                    LEFT JOIN teams t2 ON bm.team2_id = t2.id
                    WHERE bm.status IN ('live', 'ongoing')
                ",
                'description' => 'Live matches retrieval'
            ],
            'tournament_standings' => [
                'query' => "
                    SELECT t.id, t.name, 
                           COUNT(CASE WHEN bm.winner_id = t.id THEN 1 END) as wins,
                           COUNT(CASE WHEN bm.status = 'completed' AND bm.winner_id != t.id 
                                      AND (bm.team1_id = t.id OR bm.team2_id = t.id) THEN 1 END) as losses
                    FROM teams t
                    LEFT JOIN bracket_matches bm ON (t.id = bm.team1_id OR t.id = bm.team2_id)
                    WHERE EXISTS (SELECT 1 FROM bracket_matches bm2 WHERE bm2.team1_id = t.id OR bm2.team2_id = t.id)
                    GROUP BY t.id, t.name
                    ORDER BY wins DESC
                    LIMIT 20
                ",
                'description' => 'Tournament standings calculation'
            ]
        ];

        $results = [];

        foreach ($queries as $queryName => $queryData) {
            $startTime = microtime(true);
            
            try {
                $result = DB::select($queryData['query']);
                
                $endTime = microtime(true);
                $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

                $results[$queryName] = [
                    'description' => $queryData['description'],
                    'execution_time_ms' => round($executionTime, 2),
                    'rows_returned' => count($result),
                    'is_slow' => $executionTime > (self::SLOW_QUERY_THRESHOLD * 1000)
                ];

            } catch (\Exception $e) {
                $results[$queryName] = [
                    'description' => $queryData['description'],
                    'error' => $e->getMessage(),
                    'execution_time_ms' => null
                ];
            }
        }

        return $results;
    }

    /**
     * Analyze index effectiveness
     */
    private function analyzeIndexEffectiveness(): array
    {
        try {
            // Get index usage statistics
            $indexStats = DB::select("
                SELECT 
                    s.table_name,
                    s.index_name,
                    s.cardinality,
                    t.table_rows,
                    CASE 
                        WHEN t.table_rows > 0 AND s.cardinality > 0
                        THEN ROUND((s.cardinality / t.table_rows) * 100, 2)
                        ELSE 0
                    END as selectivity_percentage
                FROM information_schema.statistics s
                JOIN information_schema.tables t ON s.table_name = t.table_name AND s.table_schema = t.table_schema
                WHERE s.table_schema = DATABASE()
                AND s.table_name IN ('bracket_matches', 'bracket_games', 'teams', 'players', 'events')
                AND s.index_name != 'PRIMARY'
                ORDER BY s.table_name, selectivity_percentage DESC
            ");

            // Identify potentially unused indexes
            $unusedIndexes = array_filter($indexStats, function($index) {
                return $index->cardinality == 0 || $index->selectivity_percentage < 1;
            });

            return [
                'total_indexes' => count($indexStats),
                'potentially_unused' => count($unusedIndexes),
                'index_statistics' => $indexStats,
                'unused_indexes' => $unusedIndexes
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'total_indexes' => 0,
                'potentially_unused' => 0
            ];
        }
    }

    /**
     * Check database connection health
     */
    private function checkConnectionHealth(): array
    {
        try {
            $maxConnections = DB::scalar("SELECT @@max_connections");
            $currentConnections = DB::scalar("SELECT @@global.threads_connected");
            
            $connectionUtilization = ($currentConnections / $maxConnections) * 100;

            return [
                'max_connections' => $maxConnections,
                'current_connections' => $currentConnections,
                'connection_utilization_percent' => round($connectionUtilization, 2),
                'health_status' => $this->evaluateConnectionHealth($connectionUtilization)
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'health_status' => 'unknown'
            ];
        }
    }

    /**
     * Analyze cache performance
     */
    private function analyzeCachePerformance(): array
    {
        try {
            // Get basic buffer pool info
            $bufferPoolSize = DB::scalar("SELECT @@innodb_buffer_pool_size");
            
            return [
                'buffer_pool_size_mb' => round($bufferPoolSize / 1024 / 1024, 2),
                'cache_health' => 'monitoring_enabled'
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'cache_health' => 'unknown'
            ];
        }
    }

    /**
     * Generate performance alerts
     */
    private function generatePerformanceAlerts(array $metrics): array
    {
        $alerts = [];

        // Check for slow queries
        foreach ($metrics['query_performance'] as $queryName => $queryMetrics) {
            if (isset($queryMetrics['is_slow']) && $queryMetrics['is_slow']) {
                $alerts[] = [
                    'type' => 'slow_query',
                    'severity' => 'warning',
                    'message' => "Slow query detected: {$queryName} took {$queryMetrics['execution_time_ms']}ms",
                    'query_name' => $queryName,
                    'execution_time' => $queryMetrics['execution_time_ms']
                ];
            }
        }

        // Check connection health
        $connectionHealth = $metrics['connection_health'];
        if (isset($connectionHealth['connection_utilization_percent']) && 
            $connectionHealth['connection_utilization_percent'] > self::ALERT_THRESHOLD_CPU) {
            $alerts[] = [
                'type' => 'high_connection_usage',
                'severity' => 'critical',
                'message' => "High connection utilization: {$connectionHealth['connection_utilization_percent']}%",
                'utilization' => $connectionHealth['connection_utilization_percent']
            ];
        }

        // Check for potentially unused indexes
        $indexHealth = $metrics['index_effectiveness'];
        if (isset($indexHealth['potentially_unused']) && $indexHealth['potentially_unused'] > 0) {
            $alerts[] = [
                'type' => 'unused_indexes',
                'severity' => 'info',
                'message' => "Found {$indexHealth['potentially_unused']} potentially unused indexes",
                'unused_count' => $indexHealth['potentially_unused']
            ];
        }

        return $alerts;
    }

    /**
     * Store metrics history for trending analysis
     */
    private function storeMetricsHistory(array $metrics): void
    {
        $historyKey = 'db_monitoring_history_' . now()->format('Y_m_d_H');
        
        $currentHistory = Cache::get($historyKey, []);
        $currentHistory[] = [
            'timestamp' => $metrics['timestamp'],
            'summary' => [
                'slow_queries' => count(array_filter($metrics['query_performance'], function($q) {
                    return isset($q['is_slow']) && $q['is_slow'];
                })),
                'connection_utilization' => $metrics['connection_health']['connection_utilization_percent'] ?? 0,
                'alerts_count' => count($metrics['alerts'])
            ]
        ];

        // Keep only last 60 entries (1 hour if collected every minute)
        if (count($currentHistory) > 60) {
            $currentHistory = array_slice($currentHistory, -60);
        }

        Cache::put($historyKey, $currentHistory, 3600); // Store for 1 hour
    }

    /**
     * Get monitoring trends
     */
    public function getMonitoringTrends(int $hours = 24): array
    {
        $trends = [];
        
        for ($i = 0; $i < $hours; $i++) {
            $historyKey = 'db_monitoring_history_' . now()->subHours($i)->format('Y_m_d_H');
            $hourData = Cache::get($historyKey, []);
            
            if (!empty($hourData)) {
                $trends[now()->subHours($i)->format('H:00')] = [
                    'avg_slow_queries' => collect($hourData)->avg('summary.slow_queries'),
                    'avg_connection_utilization' => collect($hourData)->avg('summary.connection_utilization'),
                    'total_alerts' => collect($hourData)->sum('summary.alerts_count'),
                    'data_points' => count($hourData)
                ];
            }
        }

        return [
            'period_hours' => $hours,
            'trends' => $trends,
            'summary' => [
                'avg_slow_queries_per_hour' => collect($trends)->avg('avg_slow_queries'),
                'peak_connection_utilization' => collect($trends)->max('avg_connection_utilization'),
                'total_alerts' => collect($trends)->sum('total_alerts')
            ]
        ];
    }

    private function evaluateConnectionHealth(float $utilization): string
    {
        if ($utilization > 90) return 'critical';
        if ($utilization > 75) return 'warning';
        return 'healthy';
    }
}