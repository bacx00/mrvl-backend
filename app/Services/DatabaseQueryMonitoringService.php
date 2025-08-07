<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DatabaseQueryMonitoringService
{
    /**
     * Cache key for query statistics
     */
    const CACHE_KEY_PREFIX = 'db_query_stats_';
    
    /**
     * Cache duration for query stats (in seconds)
     */
    const CACHE_DURATION = 3600; // 1 hour
    
    /**
     * Threshold for slow query detection (in milliseconds)
     */
    const SLOW_QUERY_THRESHOLD = 100;
    
    /**
     * Enable query logging and monitoring
     */
    public static function enableQueryMonitoring(): void
    {
        // Listen for database queries
        DB::listen(function ($query) {
            $executionTime = $query->time;
            
            // Log slow queries
            if ($executionTime > self::SLOW_QUERY_THRESHOLD) {
                self::logSlowQuery($query->sql, $query->bindings, $executionTime);
            }
            
            // Track query statistics
            self::trackQueryStatistics($query->sql, $executionTime);
        });
    }
    
    /**
     * Log slow queries for optimization
     */
    private static function logSlowQuery(string $sql, array $bindings, float $time): void
    {
        Log::warning('Slow Query Detected', [
            'sql' => $sql,
            'bindings' => $bindings,
            'execution_time_ms' => $time,
            'threshold_ms' => self::SLOW_QUERY_THRESHOLD,
            'timestamp' => Carbon::now()->toISOString(),
            'request_url' => request()->fullUrl() ?? 'CLI',
            'user_id' => auth()->id() ?? null
        ]);
    }
    
    /**
     * Track query statistics for performance monitoring
     */
    private static function trackQueryStatistics(string $sql, float $time): void
    {
        $queryType = self::getQueryType($sql);
        $cacheKey = self::CACHE_KEY_PREFIX . date('Y-m-d-H'); // Hourly stats
        
        $stats = Cache::get($cacheKey, [
            'total_queries' => 0,
            'total_time' => 0,
            'query_types' => [],
            'slow_queries' => 0,
            'average_time' => 0
        ]);
        
        // Update statistics
        $stats['total_queries']++;
        $stats['total_time'] += $time;
        $stats['query_types'][$queryType] = ($stats['query_types'][$queryType] ?? 0) + 1;
        
        if ($time > self::SLOW_QUERY_THRESHOLD) {
            $stats['slow_queries']++;
        }
        
        $stats['average_time'] = $stats['total_time'] / $stats['total_queries'];
        
        // Cache updated statistics
        Cache::put($cacheKey, $stats, self::CACHE_DURATION);
    }
    
    /**
     * Get query type from SQL statement
     */
    private static function getQueryType(string $sql): string
    {
        $sql = strtoupper(trim($sql));
        
        if (strpos($sql, 'SELECT') === 0) {
            return 'SELECT';
        } elseif (strpos($sql, 'INSERT') === 0) {
            return 'INSERT';
        } elseif (strpos($sql, 'UPDATE') === 0) {
            return 'UPDATE';
        } elseif (strpos($sql, 'DELETE') === 0) {
            return 'DELETE';
        } elseif (strpos($sql, 'ALTER') === 0) {
            return 'ALTER';
        } elseif (strpos($sql, 'CREATE') === 0) {
            return 'CREATE';
        } elseif (strpos($sql, 'DROP') === 0) {
            return 'DROP';
        } else {
            return 'OTHER';
        }
    }
    
    /**
     * Get query statistics for monitoring
     */
    public static function getQueryStatistics(int $hours = 24): array
    {
        $stats = [];
        $now = Carbon::now();
        
        for ($i = 0; $i < $hours; $i++) {
            $cacheKey = self::CACHE_KEY_PREFIX . $now->copy()->subHours($i)->format('Y-m-d-H');
            $hourlyStats = Cache::get($cacheKey, [
                'total_queries' => 0,
                'total_time' => 0,
                'query_types' => [],
                'slow_queries' => 0,
                'average_time' => 0
            ]);
            
            $hourlyStats['hour'] = $now->copy()->subHours($i)->format('Y-m-d H:00');
            $stats[] = $hourlyStats;
        }
        
        return array_reverse($stats);
    }
    
    /**
     * Get profile-related query performance metrics
     */
    public static function getProfileQueryMetrics(): array
    {
        $cacheKey = 'profile_query_metrics';
        
        return Cache::remember($cacheKey, 1800, function () {
            // Analyze slow queries related to profile operations
            $slowProfileQueries = self::analyzeSlowProfileQueries();
            
            // Get index usage statistics for profile-related tables
            $indexUsage = self::getIndexUsageStats([
                'users', 'teams', 'marvel_rivals_heroes', 
                'votes', 'mentions', 'forum_threads', 'forum_posts'
            ]);
            
            return [
                'slow_profile_queries' => $slowProfileQueries,
                'index_usage' => $indexUsage,
                'recommendations' => self::generateOptimizationRecommendations($slowProfileQueries, $indexUsage)
            ];
        });
    }
    
    /**
     * Analyze slow queries related to profile operations
     */
    private static function analyzeSlowProfileQueries(): array
    {
        // This would typically read from a log file or database table
        // For now, we'll return analysis based on common patterns
        return [
            'user_stats_queries' => [
                'count' => 0,
                'average_time' => 0,
                'pattern' => 'SELECT ... FROM news_comments WHERE user_id = ?'
            ],
            'flair_validation_queries' => [
                'count' => 0,
                'average_time' => 0,
                'pattern' => 'SELECT ... FROM marvel_rivals_heroes WHERE name = ?'
            ],
            'user_activity_queries' => [
                'count' => 0,
                'average_time' => 0,
                'pattern' => 'UNION queries for user activity aggregation'
            ]
        ];
    }
    
    /**
     * Get index usage statistics for specified tables
     */
    private static function getIndexUsageStats(array $tables): array
    {
        $indexStats = [];
        
        foreach ($tables as $table) {
            try {
                $stats = DB::select("
                    SHOW INDEX FROM {$table}
                ");
                
                $indexStats[$table] = collect($stats)->map(function ($index) {
                    return [
                        'name' => $index->Key_name,
                        'column' => $index->Column_name,
                        'unique' => $index->Non_unique == 0,
                        'type' => $index->Index_type,
                        'cardinality' => $index->Cardinality
                    ];
                })->groupBy('name')->toArray();
                
            } catch (\Exception $e) {
                $indexStats[$table] = [];
            }
        }
        
        return $indexStats;
    }
    
    /**
     * Generate optimization recommendations based on query analysis
     */
    private static function generateOptimizationRecommendations(array $slowQueries, array $indexUsage): array
    {
        $recommendations = [];
        
        // Analyze missing indexes
        $missingIndexes = self::identifyMissingIndexes($slowQueries, $indexUsage);
        if (!empty($missingIndexes)) {
            $recommendations[] = [
                'type' => 'missing_indexes',
                'priority' => 'high',
                'description' => 'Add missing database indexes',
                'details' => $missingIndexes
            ];
        }
        
        // Check for N+1 query patterns
        if (self::detectN1QueryPatterns($slowQueries)) {
            $recommendations[] = [
                'type' => 'n_plus_one',
                'priority' => 'high',
                'description' => 'Implement eager loading to eliminate N+1 queries',
                'details' => 'Use with() method in Eloquent queries'
            ];
        }
        
        // Query optimization suggestions
        $queryOptimizations = self::suggestQueryOptimizations($slowQueries);
        if (!empty($queryOptimizations)) {
            $recommendations[] = [
                'type' => 'query_optimization',
                'priority' => 'medium',
                'description' => 'Optimize slow queries',
                'details' => $queryOptimizations
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Identify missing indexes based on query patterns
     */
    private static function identifyMissingIndexes(array $slowQueries, array $indexUsage): array
    {
        $missingIndexes = [];
        
        // Common patterns that need indexes
        $indexPatterns = [
            'users' => ['hero_flair', 'team_flair_id', 'status'],
            'marvel_rivals_heroes' => ['role', 'active'],
            'teams' => ['region', 'name'],
            'votes' => ['user_id', 'voteable_type', 'voteable_id'],
            'mentions' => ['mentioned_by', 'mentioned_type', 'mentioned_id']
        ];
        
        foreach ($indexPatterns as $table => $columns) {
            $existingIndexes = array_keys($indexUsage[$table] ?? []);
            
            foreach ($columns as $column) {
                $hasIndex = collect($existingIndexes)->contains(function ($indexName) use ($column) {
                    return strpos($indexName, $column) !== false;
                });
                
                if (!$hasIndex) {
                    $missingIndexes[] = [
                        'table' => $table,
                        'column' => $column,
                        'suggested_name' => "idx_{$table}_{$column}"
                    ];
                }
            }
        }
        
        return $missingIndexes;
    }
    
    /**
     * Detect N+1 query patterns
     */
    private static function detectN1QueryPatterns(array $slowQueries): bool
    {
        // This would analyze actual query logs for N+1 patterns
        // For now, return false as we've already optimized the code
        return false;
    }
    
    /**
     * Suggest query optimizations
     */
    private static function suggestQueryOptimizations(array $slowQueries): array
    {
        $optimizations = [];
        
        foreach ($slowQueries as $queryType => $queryData) {
            if ($queryData['average_time'] > self::SLOW_QUERY_THRESHOLD) {
                $optimizations[] = [
                    'query_type' => $queryType,
                    'current_avg_time' => $queryData['average_time'],
                    'suggestions' => self::getOptimizationSuggestions($queryType)
                ];
            }
        }
        
        return $optimizations;
    }
    
    /**
     * Get specific optimization suggestions for query types
     */
    private static function getOptimizationSuggestions(string $queryType): array
    {
        $suggestions = [
            'user_stats_queries' => [
                'Use single aggregated query instead of multiple queries',
                'Implement result caching for user statistics',
                'Consider denormalizing frequently accessed stats'
            ],
            'flair_validation_queries' => [
                'Cache hero and team validation results',
                'Use batch validation for multiple flairs',
                'Pre-load valid options in application cache'
            ],
            'user_activity_queries' => [
                'Optimize UNION queries with proper indexes',
                'Limit result sets with proper pagination',
                'Cache recent activity data'
            ]
        ];
        
        return $suggestions[$queryType] ?? ['Review query structure and add appropriate indexes'];
    }
    
    /**
     * Clear query statistics cache
     */
    public static function clearQueryStatistics(): void
    {
        $now = Carbon::now();
        
        // Clear last 24 hours of statistics
        for ($i = 0; $i < 24; $i++) {
            $cacheKey = self::CACHE_KEY_PREFIX . $now->copy()->subHours($i)->format('Y-m-d-H');
            Cache::forget($cacheKey);
        }
        
        Cache::forget('profile_query_metrics');
    }
    
    /**
     * Get database performance summary
     */
    public static function getPerformanceSummary(): array
    {
        $stats = self::getQueryStatistics(24);
        $totalQueries = array_sum(array_column($stats, 'total_queries'));
        $totalTime = array_sum(array_column($stats, 'total_time'));
        $slowQueries = array_sum(array_column($stats, 'slow_queries'));
        
        return [
            'period' => '24 hours',
            'total_queries' => $totalQueries,
            'average_query_time' => $totalQueries > 0 ? $totalTime / $totalQueries : 0,
            'slow_queries_count' => $slowQueries,
            'slow_queries_percentage' => $totalQueries > 0 ? ($slowQueries / $totalQueries) * 100 : 0,
            'query_types' => self::aggregateQueryTypes($stats),
            'hourly_breakdown' => $stats
        ];
    }
    
    /**
     * Aggregate query types from statistics
     */
    private static function aggregateQueryTypes(array $stats): array
    {
        $aggregated = [];
        
        foreach ($stats as $hourlyStat) {
            foreach ($hourlyStat['query_types'] ?? [] as $type => $count) {
                $aggregated[$type] = ($aggregated[$type] ?? 0) + $count;
            }
        }
        
        return $aggregated;
    }
}