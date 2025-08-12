<?php
/**
 * Query Performance Analyzer for CRUD Testing
 * Analyzes query performance, identifies bottlenecks, and provides optimization recommendations
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Facades\Schema;

class QueryPerformanceAnalyzer 
{
    private $dbConnection;
    private $logFile;
    private $performanceData = [];
    private $queryAnalysis = [];
    private $slowQueryThreshold = 100; // ms
    private $sessionId;

    public function __construct($slowQueryThreshold = 100)
    {
        $this->sessionId = 'perf_' . date('Y_m_d_H_i_s') . '_' . uniqid();
        $this->slowQueryThreshold = $slowQueryThreshold;
        $this->logFile = __DIR__ . "/query_performance_{$this->sessionId}.log";
        
        $this->initializeDatabase();
        $this->setupPerformanceTracking();
        $this->log("Query Performance Analyzer initialized");
        $this->log("Slow query threshold: {$this->slowQueryThreshold}ms");
    }

    private function initializeDatabase()
    {
        // Load Laravel configuration
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        
        $this->dbConnection = DB::connection();
        $this->log("Database connection established: " . config('database.default'));
    }

    private function setupPerformanceTracking()
    {
        // Enable query logging with performance tracking
        DB::listen(function ($query) {
            $this->analyzeQuery($query);
        });
        
        $this->log("Performance tracking enabled");
    }

    public function startAnalysis()
    {
        $this->log("Starting query performance analysis...");
        
        // Analyze current database schema for optimization opportunities
        $this->analyzeSchema();
        
        // Check for missing indexes
        $this->identifyMissingIndexes();
        
        // Analyze existing queries
        $this->analyzeExistingQueries();
        
        return $this;
    }

    private function analyzeQuery($query)
    {
        $analysis = [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
            'timestamp' => microtime(true),
            'issues' => $this->identifyQueryIssues($query),
            'complexity' => $this->calculateQueryComplexity($query),
            'optimization_score' => $this->calculateOptimizationScore($query)
        ];
        
        $this->queryAnalysis[] = $analysis;
        
        // Log slow queries immediately
        if ($query->time > $this->slowQueryThreshold) {
            $this->logSlowQuery($analysis);
        }
        
        // Analyze for common performance issues
        $this->detectPerformancePatterns($analysis);
    }

    private function identifyQueryIssues($query)
    {
        $issues = [];
        $sql = strtolower($query->sql);
        
        // SELECT * queries
        if (preg_match('/select\s+\*\s+from/i', $sql)) {
            $issues[] = [
                'type' => 'SELECT_ASTERISK',
                'severity' => 'MEDIUM',
                'description' => 'Using SELECT * instead of specific columns',
                'recommendation' => 'Specify only the columns you need'
            ];
        }
        
        // Missing WHERE clauses
        if (preg_match('/select\s+.*\s+from\s+\w+/i', $sql) && 
            !preg_match('/where|limit|offset/i', $sql)) {
            $issues[] = [
                'type' => 'MISSING_WHERE',
                'severity' => 'HIGH',
                'description' => 'SELECT query without WHERE clause or LIMIT',
                'recommendation' => 'Add WHERE clause or LIMIT to prevent full table scans'
            ];
        }
        
        // Potential N+1 queries
        if (preg_match('/select\s+.*\s+from\s+\w+\s+where\s+\w+_id\s*=\s*\?/i', $sql)) {
            $issues[] = [
                'type' => 'POTENTIAL_N_PLUS_1',
                'severity' => 'HIGH',
                'description' => 'Potential N+1 query pattern detected',
                'recommendation' => 'Consider using eager loading or JOIN queries'
            ];
        }
        
        // LIKE queries without proper indexing
        if (preg_match('/like\s+[\'"]%.*%[\'\"]/i', $sql)) {
            $issues[] = [
                'type' => 'WILDCARD_LIKE',
                'severity' => 'MEDIUM',
                'description' => 'LIKE query with leading wildcard',
                'recommendation' => 'Consider full-text search or redesign query pattern'
            ];
        }
        
        // Subqueries that could be JOINs
        if (preg_match_all('/\(\s*select/i', $sql) > 1) {
            $issues[] = [
                'type' => 'COMPLEX_SUBQUERY',
                'severity' => 'MEDIUM',
                'description' => 'Multiple subqueries detected',
                'recommendation' => 'Consider converting subqueries to JOINs for better performance'
            ];
        }
        
        // ORDER BY without LIMIT
        if (preg_match('/order\s+by/i', $sql) && !preg_match('/limit/i', $sql)) {
            $issues[] = [
                'type' => 'ORDER_WITHOUT_LIMIT',
                'severity' => 'LOW',
                'description' => 'ORDER BY without LIMIT clause',
                'recommendation' => 'Add LIMIT clause if you don\'t need all sorted results'
            ];
        }
        
        return $issues;
    }

    private function calculateQueryComplexity($query)
    {
        $sql = strtolower($query->sql);
        $complexity = 0;
        
        // Count JOINs
        $complexity += substr_count($sql, 'join') * 2;
        
        // Count subqueries
        $complexity += substr_count($sql, '(select') * 3;
        
        // Count WHERE conditions
        $complexity += substr_count($sql, 'and') + substr_count($sql, 'or');
        
        // Count aggregations
        $complexity += substr_count($sql, 'group by') * 2;
        $complexity += substr_count($sql, 'having') * 2;
        
        // Count ORDER BY
        $complexity += substr_count($sql, 'order by');
        
        return min($complexity, 10); // Cap at 10
    }

    private function calculateOptimizationScore($query)
    {
        $score = 100;
        $issues = $this->identifyQueryIssues($query);
        
        foreach ($issues as $issue) {
            switch ($issue['severity']) {
                case 'HIGH':
                    $score -= 20;
                    break;
                case 'MEDIUM':
                    $score -= 10;
                    break;
                case 'LOW':
                    $score -= 5;
                    break;
            }
        }
        
        // Factor in execution time
        if ($query->time > $this->slowQueryThreshold * 2) {
            $score -= 30;
        } elseif ($query->time > $this->slowQueryThreshold) {
            $score -= 15;
        }
        
        return max($score, 0);
    }

    private function logSlowQuery($analysis)
    {
        $this->log("SLOW QUERY DETECTED", 'WARNING');
        $this->log("Execution time: {$analysis['time']}ms");
        $this->log("SQL: {$analysis['sql']}");
        $this->log("Complexity: {$analysis['complexity']}/10");
        $this->log("Optimization score: {$analysis['optimization_score']}/100");
        
        if (!empty($analysis['issues'])) {
            $this->log("Issues found:");
            foreach ($analysis['issues'] as $issue) {
                $this->log("  - [{$issue['severity']}] {$issue['description']}");
                $this->log("    Recommendation: {$issue['recommendation']}");
            }
        }
        $this->log("---");
    }

    private function detectPerformancePatterns($analysis)
    {
        // Track patterns for bulk analysis
        if (!isset($this->performanceData['query_patterns'])) {
            $this->performanceData['query_patterns'] = [];
        }
        
        $pattern = $this->extractQueryPattern($analysis['sql']);
        if (!isset($this->performanceData['query_patterns'][$pattern])) {
            $this->performanceData['query_patterns'][$pattern] = [
                'count' => 0,
                'total_time' => 0,
                'max_time' => 0,
                'min_time' => PHP_INT_MAX,
                'issues' => []
            ];
        }
        
        $this->performanceData['query_patterns'][$pattern]['count']++;
        $this->performanceData['query_patterns'][$pattern]['total_time'] += $analysis['time'];
        $this->performanceData['query_patterns'][$pattern]['max_time'] = max(
            $this->performanceData['query_patterns'][$pattern]['max_time'], 
            $analysis['time']
        );
        $this->performanceData['query_patterns'][$pattern]['min_time'] = min(
            $this->performanceData['query_patterns'][$pattern]['min_time'], 
            $analysis['time']
        );
        
        // Aggregate issues
        foreach ($analysis['issues'] as $issue) {
            if (!in_array($issue['type'], $this->performanceData['query_patterns'][$pattern]['issues'])) {
                $this->performanceData['query_patterns'][$pattern]['issues'][] = $issue['type'];
            }
        }
    }

    private function extractQueryPattern($sql)
    {
        // Normalize SQL to identify patterns
        $pattern = strtolower($sql);
        
        // Replace specific values with placeholders
        $pattern = preg_replace('/\d+/', '?', $pattern);
        $pattern = preg_replace('/\'[^\']*\'/', '?', $pattern);
        $pattern = preg_replace('/\"[^\"]*\"/', '?', $pattern);
        $pattern = preg_replace('/\s+/', ' ', $pattern);
        
        return trim($pattern);
    }

    private function analyzeSchema()
    {
        $this->log("=== Schema Analysis ===");
        
        try {
            $tables = ['teams', 'players', 'mentions', 'player_team_histories', 'match_player_stats'];
            
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    $this->analyzeTable($table);
                } else {
                    $this->log("WARNING: Table '{$table}' does not exist", 'WARNING');
                }
            }
            
        } catch (Exception $e) {
            $this->log("ERROR analyzing schema: " . $e->getMessage(), 'ERROR');
        }
    }

    private function analyzeTable($tableName)
    {
        $this->log("Analyzing table: {$tableName}");
        
        try {
            // Get table size and row count
            $rowCount = DB::table($tableName)->count();
            $this->log("  Rows: {$rowCount}");
            
            // Analyze columns
            $columns = Schema::getColumnListing($tableName);
            $this->log("  Columns: " . count($columns));
            
            // Check for potential indexing opportunities
            $this->analyzeTableIndexing($tableName, $columns);
            
            // Analyze data distribution
            $this->analyzeDataDistribution($tableName);
            
        } catch (Exception $e) {
            $this->log("  ERROR: " . $e->getMessage(), 'ERROR');
        }
    }

    private function analyzeTableIndexing($tableName, $columns)
    {
        // Common columns that should be indexed
        $indexCandidates = [
            'id', 'team_id', 'player_id', 'user_id', 'mentioned_id', 
            'created_at', 'updated_at', 'status', 'type'
        ];
        
        $missingIndexes = [];
        foreach ($indexCandidates as $candidate) {
            if (in_array($candidate, $columns)) {
                // This is a simplified check - in real implementation, 
                // you'd query the information_schema or use SHOW INDEX
                $this->log("  Index candidate: {$candidate}");
            }
        }
    }

    private function analyzeDataDistribution($tableName)
    {
        try {
            // Analyze NULL values in key columns
            if ($tableName === 'players') {
                $nullTeamIds = DB::table($tableName)->whereNull('team_id')->count();
                $totalPlayers = DB::table($tableName)->count();
                $nullPercentage = $totalPlayers > 0 ? ($nullTeamIds / $totalPlayers) * 100 : 0;
                
                $this->log("  Players without teams: {$nullTeamIds} ({$nullPercentage}%)");
                
                if ($nullPercentage > 50) {
                    $this->log("  WARNING: High percentage of players without teams", 'WARNING');
                }
            }
            
            if ($tableName === 'mentions') {
                $typeDistribution = DB::table($tableName)
                    ->select('mentioned_type', DB::raw('COUNT(*) as count'))
                    ->groupBy('mentioned_type')
                    ->get();
                
                $this->log("  Mention type distribution:");
                foreach ($typeDistribution as $dist) {
                    $this->log("    {$dist->mentioned_type}: {$dist->count}");
                }
            }
            
        } catch (Exception $e) {
            $this->log("  ERROR analyzing data distribution: " . $e->getMessage(), 'ERROR');
        }
    }

    private function identifyMissingIndexes()
    {
        $this->log("=== Missing Index Analysis ===");
        
        $indexRecommendations = [
            'teams' => [
                ['columns' => ['name'], 'reason' => 'Frequent lookups by team name'],
                ['columns' => ['region'], 'reason' => 'Regional filtering'],
                ['columns' => ['created_at'], 'reason' => 'Temporal queries']
            ],
            'players' => [
                ['columns' => ['team_id'], 'reason' => 'Foreign key relationship'],
                ['columns' => ['name'], 'reason' => 'Player name searches'],
                ['columns' => ['region'], 'reason' => 'Regional filtering'],
                ['columns' => ['team_id', 'created_at'], 'reason' => 'Team roster history']
            ],
            'mentions' => [
                ['columns' => ['mentioned_type', 'mentioned_id'], 'reason' => 'Polymorphic relationship'],
                ['columns' => ['mentioned_by'], 'reason' => 'User mention history'],
                ['columns' => ['created_at'], 'reason' => 'Recent mentions'],
                ['columns' => ['is_active'], 'reason' => 'Active mention filtering']
            ],
            'player_team_histories' => [
                ['columns' => ['player_id'], 'reason' => 'Player history lookups'],
                ['columns' => ['from_team_id'], 'reason' => 'Team departure tracking'],
                ['columns' => ['to_team_id'], 'reason' => 'Team arrival tracking'],
                ['columns' => ['change_date'], 'reason' => 'Temporal analysis']
            ]
        ];
        
        foreach ($indexRecommendations as $table => $indexes) {
            if (Schema::hasTable($table)) {
                $this->log("Recommended indexes for {$table}:");
                foreach ($indexes as $index) {
                    $columns = implode(', ', $index['columns']);
                    $this->log("  - ({$columns}) - {$index['reason']}");
                }
            }
        }
    }

    private function analyzeExistingQueries()
    {
        $this->log("=== Common Query Pattern Analysis ===");
        
        // Simulate common queries to analyze their performance
        $commonQueries = [
            'teams' => [
                'SELECT * FROM teams WHERE id = ?',
                'SELECT * FROM teams WHERE name LIKE ?',
                'SELECT * FROM teams ORDER BY created_at DESC LIMIT 20',
                'SELECT COUNT(*) FROM teams WHERE region = ?'
            ],
            'players' => [
                'SELECT * FROM players WHERE team_id = ?',
                'SELECT * FROM players WHERE name LIKE ?',
                'SELECT p.*, t.name as team_name FROM players p LEFT JOIN teams t ON p.team_id = t.id',
                'SELECT COUNT(*) FROM players WHERE team_id IS NULL'
            ],
            'mentions' => [
                'SELECT * FROM mentions WHERE mentioned_type = ? AND mentioned_id = ?',
                'SELECT * FROM mentions WHERE mentioned_by = ? ORDER BY created_at DESC',
                'SELECT COUNT(*) FROM mentions WHERE mentioned_type = ? AND is_active = 1'
            ]
        ];
        
        foreach ($commonQueries as $table => $queries) {
            $this->log("Common {$table} queries:");
            foreach ($queries as $query) {
                $analysis = $this->analyzeQueryPattern($query);
                $this->log("  Pattern: {$query}");
                $this->log("    Complexity: {$analysis['complexity']}/10");
                $this->log("    Issues: " . count($analysis['issues']));
            }
        }
    }

    private function analyzeQueryPattern($sql)
    {
        // Create a mock query object for analysis
        $mockQuery = (object) [
            'sql' => $sql,
            'bindings' => [],
            'time' => 0
        ];
        
        return [
            'issues' => $this->identifyQueryIssues($mockQuery),
            'complexity' => $this->calculateQueryComplexity($mockQuery)
        ];
    }

    public function generateOptimizationReport()
    {
        $this->log("=== Optimization Report ===");
        
        $report = [
            'session_id' => $this->sessionId,
            'analysis_timestamp' => date('Y-m-d H:i:s'),
            'total_queries_analyzed' => count($this->queryAnalysis),
            'slow_queries' => array_filter($this->queryAnalysis, function($q) {
                return $q['time'] > $this->slowQueryThreshold;
            }),
            'query_patterns' => $this->performanceData['query_patterns'] ?? [],
            'recommendations' => $this->generateRecommendations(),
            'performance_summary' => $this->generatePerformanceSummary()
        ];
        
        // Calculate statistics
        if (!empty($this->queryAnalysis)) {
            $times = array_column($this->queryAnalysis, 'time');
            $report['performance_statistics'] = [
                'average_query_time' => round(array_sum($times) / count($times), 2),
                'max_query_time' => max($times),
                'min_query_time' => min($times),
                'slow_query_count' => count($report['slow_queries']),
                'slow_query_percentage' => round((count($report['slow_queries']) / count($this->queryAnalysis)) * 100, 2)
            ];
        }
        
        // Save report
        $reportFile = __DIR__ . "/optimization_report_{$this->sessionId}.json";
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->log("Optimization report generated: {$reportFile}");
        $this->displayReportSummary($report);
        
        return $report;
    }

    private function generateRecommendations()
    {
        $recommendations = [];
        
        // Analyze all issues found
        $allIssues = [];
        foreach ($this->queryAnalysis as $analysis) {
            foreach ($analysis['issues'] as $issue) {
                $type = $issue['type'];
                if (!isset($allIssues[$type])) {
                    $allIssues[$type] = [
                        'count' => 0,
                        'severity' => $issue['severity'],
                        'description' => $issue['description'],
                        'recommendation' => $issue['recommendation']
                    ];
                }
                $allIssues[$type]['count']++;
            }
        }
        
        // Generate recommendations based on frequency and severity
        foreach ($allIssues as $type => $data) {
            if ($data['count'] > 1) { // Only recommend if issue appears multiple times
                $recommendations[] = [
                    'priority' => $this->calculateRecommendationPriority($data),
                    'type' => $type,
                    'occurrences' => $data['count'],
                    'severity' => $data['severity'],
                    'description' => $data['description'],
                    'recommendation' => $data['recommendation']
                ];
            }
        }
        
        // Sort by priority
        usort($recommendations, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        return $recommendations;
    }

    private function calculateRecommendationPriority($issueData)
    {
        $priority = $issueData['count'];
        
        switch ($issueData['severity']) {
            case 'HIGH':
                $priority *= 3;
                break;
            case 'MEDIUM':
                $priority *= 2;
                break;
            case 'LOW':
                $priority *= 1;
                break;
        }
        
        return $priority;
    }

    private function generatePerformanceSummary()
    {
        if (empty($this->queryAnalysis)) {
            return ['status' => 'No queries analyzed'];
        }
        
        $totalQueries = count($this->queryAnalysis);
        $slowQueries = array_filter($this->queryAnalysis, function($q) {
            return $q['time'] > $this->slowQueryThreshold;
        });
        
        $avgOptimizationScore = array_sum(array_column($this->queryAnalysis, 'optimization_score')) / $totalQueries;
        
        $status = 'EXCELLENT';
        if ($avgOptimizationScore < 90) $status = 'GOOD';
        if ($avgOptimizationScore < 80) $status = 'FAIR';
        if ($avgOptimizationScore < 70) $status = 'POOR';
        if ($avgOptimizationScore < 60) $status = 'CRITICAL';
        
        return [
            'status' => $status,
            'average_optimization_score' => round($avgOptimizationScore, 2),
            'total_queries' => $totalQueries,
            'slow_queries' => count($slowQueries),
            'performance_rating' => $this->calculatePerformanceRating($avgOptimizationScore, count($slowQueries), $totalQueries)
        ];
    }

    private function calculatePerformanceRating($avgScore, $slowQueries, $totalQueries)
    {
        $slowQueryRate = $totalQueries > 0 ? ($slowQueries / $totalQueries) * 100 : 0;
        
        if ($avgScore >= 90 && $slowQueryRate < 5) return 'A+';
        if ($avgScore >= 85 && $slowQueryRate < 10) return 'A';
        if ($avgScore >= 80 && $slowQueryRate < 15) return 'B+';
        if ($avgScore >= 75 && $slowQueryRate < 20) return 'B';
        if ($avgScore >= 70 && $slowQueryRate < 25) return 'C+';
        if ($avgScore >= 65 && $slowQueryRate < 30) return 'C';
        if ($avgScore >= 60 && $slowQueryRate < 40) return 'D+';
        
        return 'F';
    }

    private function displayReportSummary($report)
    {
        $this->log("=== PERFORMANCE SUMMARY ===");
        
        if (isset($report['performance_statistics'])) {
            $stats = $report['performance_statistics'];
            $this->log("Total queries analyzed: {$report['total_queries_analyzed']}");
            $this->log("Average query time: {$stats['average_query_time']}ms");
            $this->log("Slow queries: {$stats['slow_query_count']} ({$stats['slow_query_percentage']}%)");
        }
        
        if (isset($report['performance_summary'])) {
            $summary = $report['performance_summary'];
            $this->log("Performance status: {$summary['status']}");
            $this->log("Average optimization score: {$summary['average_optimization_score']}/100");
            $this->log("Performance rating: {$summary['performance_rating']}");
        }
        
        if (!empty($report['recommendations'])) {
            $this->log("Top recommendations:");
            foreach (array_slice($report['recommendations'], 0, 5) as $rec) {
                $this->log("  - [{$rec['severity']}] {$rec['description']} (occurs {$rec['occurrences']} times)");
            }
        }
    }

    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        echo $logEntry;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function getLogFile()
    {
        return $this->logFile;
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }
}

// CLI Usage
if (php_sapi_name() === 'cli') {
    echo "Query Performance Analyzer\n";
    echo "Usage: php query_performance_analyzer.php [threshold_ms]\n";
    echo "  threshold_ms: Slow query threshold in milliseconds (default: 100)\n\n";
    
    $threshold = isset($argv[1]) ? (int)$argv[1] : 100;
    
    $analyzer = new QueryPerformanceAnalyzer($threshold);
    $analyzer->startAnalysis();
    
    echo "Analysis started. Execute some queries and then press Enter to generate report...\n";
    readline();
    
    $analyzer->generateOptimizationReport();
    
    echo "\nAnalysis complete. Check the log files for detailed results.\n";
}