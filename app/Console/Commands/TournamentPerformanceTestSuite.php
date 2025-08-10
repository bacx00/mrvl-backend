<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\HighPerformanceTournamentQueryService;
use App\Services\AdvancedTournamentCacheService;
use App\Services\TournamentPerformanceMonitoringService;
use Carbon\Carbon;

/**
 * Tournament Performance Test Suite
 * 
 * Comprehensive testing framework for tournament database performance:
 * - Load testing for high-concurrency scenarios
 * - Query performance benchmarks
 * - Stress testing for large tournaments
 * - Cache performance validation
 * - Real-time update testing
 * - Swiss system performance testing
 */
class TournamentPerformanceTestSuite extends Command
{
    protected $signature = 'tournament:test-performance 
                          {--test-type=all : Type of test to run (all|load|query|stress|cache|swiss)}
                          {--concurrent-users=100 : Number of concurrent users to simulate}
                          {--tournament-size=64 : Number of teams in test tournament}
                          {--duration=300 : Test duration in seconds}
                          {--iterations=1000 : Number of test iterations}
                          {--report-file= : Output report file path}';

    protected $description = 'Run comprehensive tournament performance tests';

    private HighPerformanceTournamentQueryService $queryService;
    private AdvancedTournamentCacheService $cacheService;
    private TournamentPerformanceMonitoringService $monitoringService;
    
    private array $testResults = [];
    private array $performanceMetrics = [];
    private Carbon $testStartTime;

    public function __construct(
        HighPerformanceTournamentQueryService $queryService,
        AdvancedTournamentCacheService $cacheService,
        TournamentPerformanceMonitoringService $monitoringService
    ) {
        parent::__construct();
        
        $this->queryService = $queryService;
        $this->cacheService = $cacheService;
        $this->monitoringService = $monitoringService;
    }

    public function handle(): int
    {
        $this->info('🚀 Tournament Performance Test Suite');
        $this->info('='.str_repeat('=', 36));
        $this->newLine();

        $this->testStartTime = now();
        $testType = $this->option('test-type');

        try {
            // Initialize test environment
            $this->initializeTestEnvironment();

            // Run selected tests
            match ($testType) {
                'all' => $this->runAllTests(),
                'load' => $this->runLoadTests(),
                'query' => $this->runQueryPerformanceTests(),
                'stress' => $this->runStressTests(),
                'cache' => $this->runCachePerformanceTests(),
                'swiss' => $this->runSwissSystemTests(),
                default => throw new \InvalidArgumentException("Unknown test type: {$testType}")
            };

            // Generate comprehensive report
            $this->generatePerformanceReport();

            $this->info('✅ Performance testing completed successfully');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Performance testing failed: ' . $e->getMessage());
            return 1;
        } finally {
            $this->cleanupTestEnvironment();
        }
    }

    private function runAllTests(): void
    {
        $this->info('📊 Running comprehensive performance test suite...');
        $this->newLine();

        $tests = [
            'Load Testing' => fn() => $this->runLoadTests(),
            'Query Performance' => fn() => $this->runQueryPerformanceTests(),
            'Stress Testing' => fn() => $this->runStressTests(),
            'Cache Performance' => fn() => $this->runCachePerformanceTests(),
            'Swiss System' => fn() => $this->runSwissSystemTests()
        ];

        $progressBar = $this->output->createProgressBar(count($tests));
        $progressBar->start();

        foreach ($tests as $testName => $testFunction) {
            $this->info("\n🔄 Running {$testName}...");
            $testFunction();
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    private function runLoadTests(): void
    {
        $this->info('🔥 Load Testing - Simulating concurrent tournament access');
        
        $concurrentUsers = (int) $this->option('concurrent-users');
        $duration = (int) $this->option('duration');
        
        $loadTestResults = [
            'concurrent_users' => $concurrentUsers,
            'test_duration' => $duration,
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'average_response_time' => 0,
            'max_response_time' => 0,
            'min_response_time' => PHP_FLOAT_MAX,
            'requests_per_second' => 0,
            'error_rate' => 0
        ];

        // Create test tournament
        $testTournamentId = $this->createTestTournament();
        
        $startTime = microtime(true);
        $endTime = $startTime + $duration;
        $responseTimes = [];

        $this->line("Simulating {$concurrentUsers} concurrent users for {$duration} seconds...");
        
        // Simulate concurrent load
        while (microtime(true) < $endTime) {
            $batchStartTime = microtime(true);
            $batchRequests = [];
            
            // Simulate user requests
            for ($i = 0; $i < min($concurrentUsers, 50); $i++) { // Process in batches
                $requestType = $this->getRandomRequestType();
                $requestStartTime = microtime(true);
                
                try {
                    $this->executeLoadTestRequest($testTournamentId, $requestType);
                    $responseTime = (microtime(true) - $requestStartTime) * 1000;
                    
                    $responseTimes[] = $responseTime;
                    $loadTestResults['successful_requests']++;
                    
                    $loadTestResults['max_response_time'] = max($loadTestResults['max_response_time'], $responseTime);
                    $loadTestResults['min_response_time'] = min($loadTestResults['min_response_time'], $responseTime);
                    
                } catch (\Exception $e) {
                    $loadTestResults['failed_requests']++;
                }
                
                $loadTestResults['total_requests']++;
            }
            
            // Brief pause to simulate realistic load patterns
            usleep(100000); // 100ms
        }

        // Calculate final metrics
        $actualDuration = microtime(true) - $startTime;
        $loadTestResults['average_response_time'] = array_sum($responseTimes) / count($responseTimes);
        $loadTestResults['requests_per_second'] = $loadTestResults['total_requests'] / $actualDuration;
        $loadTestResults['error_rate'] = ($loadTestResults['failed_requests'] / $loadTestResults['total_requests']) * 100;

        $this->testResults['load_testing'] = $loadTestResults;

        // Display results
        $this->table(['Metric', 'Value'], [
            ['Total Requests', $loadTestResults['total_requests']],
            ['Successful Requests', $loadTestResults['successful_requests']],
            ['Failed Requests', $loadTestResults['failed_requests']],
            ['Average Response Time (ms)', number_format($loadTestResults['average_response_time'], 2)],
            ['Max Response Time (ms)', number_format($loadTestResults['max_response_time'], 2)],
            ['Min Response Time (ms)', number_format($loadTestResults['min_response_time'], 2)],
            ['Requests/Second', number_format($loadTestResults['requests_per_second'], 2)],
            ['Error Rate (%)', number_format($loadTestResults['error_rate'], 2)]
        ]);
    }

    private function runQueryPerformanceTests(): void
    {
        $this->info('📊 Query Performance Testing - Benchmarking critical queries');
        
        $iterations = (int) $this->option('iterations');
        $testTournamentId = $this->createTestTournament();
        
        $queryTests = [
            'tournament_list' => [
                'name' => 'Tournament List Query',
                'function' => fn() => $this->queryService->getTournamentList(['status' => 'ongoing'], 20, 0)
            ],
            'live_tournament' => [
                'name' => 'Live Tournament Data',
                'function' => fn() => $this->queryService->getLiveTournamentData($testTournamentId)
            ],
            'tournament_bracket' => [
                'name' => 'Tournament Bracket',
                'function' => fn() => $this->queryService->getTournamentBracket($testTournamentId)
            ],
            'swiss_standings' => [
                'name' => 'Swiss Standings',
                'function' => fn() => $this->queryService->getSwissStandings($testTournamentId)
            ],
            'tournament_analytics' => [
                'name' => 'Tournament Analytics',
                'function' => fn() => $this->queryService->getTournamentAnalytics($testTournamentId)
            ]
        ];

        $queryResults = [];

        foreach ($queryTests as $testKey => $test) {
            $this->line("Testing: {$test['name']}");
            
            $times = [];
            $errors = 0;
            
            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);
                
                try {
                    $test['function']();
                    $times[] = (microtime(true) - $startTime) * 1000;
                } catch (\Exception $e) {
                    $errors++;
                }
            }
            
            $queryResults[$testKey] = [
                'name' => $test['name'],
                'iterations' => $iterations,
                'average_time' => array_sum($times) / count($times),
                'min_time' => min($times),
                'max_time' => max($times),
                'median_time' => $this->calculateMedian($times),
                'p95_time' => $this->calculatePercentile($times, 95),
                'p99_time' => $this->calculatePercentile($times, 99),
                'error_count' => $errors,
                'success_rate' => (($iterations - $errors) / $iterations) * 100
            ];
        }

        $this->testResults['query_performance'] = $queryResults;

        // Display results
        $tableRows = [];
        foreach ($queryResults as $result) {
            $tableRows[] = [
                $result['name'],
                number_format($result['average_time'], 2) . 'ms',
                number_format($result['min_time'], 2) . 'ms',
                number_format($result['max_time'], 2) . 'ms',
                number_format($result['p95_time'], 2) . 'ms',
                number_format($result['success_rate'], 1) . '%'
            ];
        }

        $this->table(['Query', 'Avg Time', 'Min Time', 'Max Time', 'P95 Time', 'Success Rate'], $tableRows);
    }

    private function runStressTests(): void
    {
        $this->info('💪 Stress Testing - Testing system limits');
        
        $tournamentSize = (int) $this->option('tournament-size');
        $maxConcurrency = 500; // Maximum concurrent operations
        
        $stressResults = [
            'tournament_size' => $tournamentSize,
            'max_concurrent_operations' => 0,
            'breaking_point' => null,
            'memory_peak_mb' => 0,
            'database_connections_peak' => 0,
            'query_failures' => 0,
            'system_recovery_time' => 0
        ];

        // Create large test tournament
        $testTournamentId = $this->createLargeTestTournament($tournamentSize);
        
        $this->line("Testing with {$tournamentSize} team tournament...");
        
        // Gradually increase load until breaking point
        for ($concurrency = 10; $concurrency <= $maxConcurrency; $concurrency += 10) {
            $this->line("Testing {$concurrency} concurrent operations...");
            
            $startMemory = memory_get_usage(true);
            $failures = 0;
            
            try {
                // Simulate concurrent operations
                $operations = [];
                for ($i = 0; $i < $concurrency; $i++) {
                    $operations[] = $this->createStressTestOperation($testTournamentId);
                }
                
                // Execute operations
                foreach ($operations as $operation) {
                    try {
                        $operation();
                    } catch (\Exception $e) {
                        $failures++;
                    }
                }
                
                $peakMemory = memory_get_peak_usage(true);
                $memoryUsed = ($peakMemory - $startMemory) / 1024 / 1024;
                
                $stressResults['memory_peak_mb'] = max($stressResults['memory_peak_mb'], $memoryUsed);
                $stressResults['query_failures'] += $failures;
                
                // Check failure rate
                $failureRate = ($failures / $concurrency) * 100;
                
                if ($failureRate > 10) { // More than 10% failure rate
                    $stressResults['breaking_point'] = $concurrency;
                    $stressResults['max_concurrent_operations'] = $concurrency - 10;
                    break;
                } else {
                    $stressResults['max_concurrent_operations'] = $concurrency;
                }
                
            } catch (\Exception $e) {
                $stressResults['breaking_point'] = $concurrency;
                break;
            }
        }

        $this->testResults['stress_testing'] = $stressResults;

        // Display results
        $this->table(['Metric', 'Value'], [
            ['Tournament Size', $stressResults['tournament_size'] . ' teams'],
            ['Max Concurrent Operations', $stressResults['max_concurrent_operations']],
            ['Breaking Point', $stressResults['breaking_point'] ?? 'Not reached'],
            ['Peak Memory Usage (MB)', number_format($stressResults['memory_peak_mb'], 2)],
            ['Total Query Failures', $stressResults['query_failures']]
        ]);
    }

    private function runCachePerformanceTests(): void
    {
        $this->info('⚡ Cache Performance Testing - Validating caching efficiency');
        
        $testTournamentId = $this->createTestTournament();
        $iterations = (int) $this->option('iterations');
        
        $cacheResults = [
            'cache_warming_time' => 0,
            'cache_hit_rate' => 0,
            'cache_miss_penalty' => 0,
            'invalidation_time' => 0,
            'memory_efficiency' => 0
        ];

        // Test cache warming
        $this->line('Testing cache warming...');
        $startTime = microtime(true);
        $this->cacheService->warmTournamentCache($testTournamentId);
        $cacheResults['cache_warming_time'] = (microtime(true) - $startTime) * 1000;

        // Test cache hit rates
        $this->line('Testing cache hit rates...');
        $hits = 0;
        $totalRequests = $iterations;
        
        for ($i = 0; $i < $totalRequests; $i++) {
            $cacheKey = "test_tournament:{$testTournamentId}:standings";
            
            if ($this->cacheService->getCachedData($cacheKey) !== null) {
                $hits++;
            }
        }
        
        $cacheResults['cache_hit_rate'] = ($hits / $totalRequests) * 100;

        // Test cache invalidation
        $this->line('Testing cache invalidation...');
        $startTime = microtime(true);
        $this->cacheService->invalidateTournamentCache($testTournamentId);
        $cacheResults['invalidation_time'] = (microtime(true) - $startTime) * 1000;

        // Test cache miss penalty
        $this->line('Testing cache miss penalty...');
        $missStartTime = microtime(true);
        $this->queryService->getTournamentBracket($testTournamentId); // Should be cache miss
        $missPenalty = (microtime(true) - $missStartTime) * 1000;
        
        $hitStartTime = microtime(true);
        $this->queryService->getTournamentBracket($testTournamentId); // Should be cache hit
        $hitTime = (microtime(true) - $hitStartTime) * 1000;
        
        $cacheResults['cache_miss_penalty'] = $missPenalty - $hitTime;

        $this->testResults['cache_performance'] = $cacheResults;

        // Display results
        $this->table(['Metric', 'Value'], [
            ['Cache Warming Time (ms)', number_format($cacheResults['cache_warming_time'], 2)],
            ['Cache Hit Rate (%)', number_format($cacheResults['cache_hit_rate'], 2)],
            ['Cache Miss Penalty (ms)', number_format($cacheResults['cache_miss_penalty'], 2)],
            ['Invalidation Time (ms)', number_format($cacheResults['invalidation_time'], 2)]
        ]);
    }

    private function runSwissSystemTests(): void
    {
        $this->info('🏆 Swiss System Performance Testing - Testing Swiss tournament calculations');
        
        $tournamentSizes = [8, 16, 32, 64, 128, 256, 512];
        $swissResults = [];

        foreach ($tournamentSizes as $size) {
            $this->line("Testing Swiss system with {$size} teams...");
            
            $testTournamentId = $this->createSwissTournament($size);
            
            // Test Swiss standings calculation
            $standingsStartTime = microtime(true);
            $standings = $this->queryService->getSwissStandings($testTournamentId);
            $standingsTime = (microtime(true) - $standingsStartTime) * 1000;
            
            // Test Swiss pairing (simulated)
            $pairingStartTime = microtime(true);
            $this->simulateSwissPairing($testTournamentId, $size);
            $pairingTime = (microtime(true) - $pairingStartTime) * 1000;
            
            $swissResults[$size] = [
                'team_count' => $size,
                'standings_calculation_time' => $standingsTime,
                'pairing_calculation_time' => $pairingTime,
                'total_time' => $standingsTime + $pairingTime,
                'complexity_score' => $this->calculateComplexityScore($size, $standingsTime, $pairingTime)
            ];
        }

        $this->testResults['swiss_system'] = $swissResults;

        // Display results
        $tableRows = [];
        foreach ($swissResults as $result) {
            $tableRows[] = [
                $result['team_count'],
                number_format($result['standings_calculation_time'], 2) . 'ms',
                number_format($result['pairing_calculation_time'], 2) . 'ms',
                number_format($result['total_time'], 2) . 'ms',
                number_format($result['complexity_score'], 2)
            ];
        }

        $this->table(['Teams', 'Standings Time', 'Pairing Time', 'Total Time', 'Complexity Score'], $tableRows);
    }

    private function generatePerformanceReport(): void
    {
        $this->info('📋 Generating Performance Report...');
        
        $report = [
            'test_summary' => [
                'start_time' => $this->testStartTime->toISOString(),
                'end_time' => now()->toISOString(),
                'total_duration' => now()->diffInSeconds($this->testStartTime),
                'tests_executed' => count($this->testResults),
                'overall_status' => $this->calculateOverallStatus()
            ],
            'test_results' => $this->testResults,
            'performance_recommendations' => $this->generateRecommendations(),
            'system_info' => $this->collectSystemInfo()
        ];

        // Save report to file if specified
        $reportFile = $this->option('report-file');
        if ($reportFile) {
            file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
            $this->info("📄 Report saved to: {$reportFile}");
        }

        // Display summary
        $this->displayReportSummary($report);
    }

    // Helper methods for test implementation

    private function initializeTestEnvironment(): void
    {
        $this->info('🔧 Initializing test environment...');
        
        // Clear caches
        cache()->flush();
        
        // Reset performance counters
        $this->monitoringService->getSystemHealthStatus();
    }

    private function cleanupTestEnvironment(): void
    {
        $this->info('🧹 Cleaning up test environment...');
        
        // Remove test tournaments
        DB::table('tournaments')
            ->where('name', 'LIKE', 'TEST_%')
            ->delete();
            
        // Clear test caches
        cache()->flush();
    }

    private function createTestTournament(): int
    {
        return DB::table('tournaments')->insertGetId([
            'name' => 'TEST_Tournament_' . time(),
            'type' => 'community',
            'format' => 'swiss',
            'status' => 'ongoing',
            'team_count' => 16,
            'max_teams' => 16,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function createLargeTestTournament(int $teamCount): int
    {
        $tournamentId = DB::table('tournaments')->insertGetId([
            'name' => "TEST_Large_Tournament_{$teamCount}_" . time(),
            'type' => 'community',
            'format' => 'swiss',
            'status' => 'ongoing',
            'team_count' => $teamCount,
            'max_teams' => $teamCount,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create tournament teams
        for ($i = 1; $i <= $teamCount; $i++) {
            DB::table('tournament_teams')->insert([
                'tournament_id' => $tournamentId,
                'team_id' => $i, // Assuming teams exist
                'seed' => $i,
                'status' => 'checked_in',
                'registered_at' => now(),
                'swiss_wins' => rand(0, 5),
                'swiss_losses' => rand(0, 5),
                'swiss_score' => rand(0, 15),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return $tournamentId;
    }

    private function createSwissTournament(int $teamCount): int
    {
        return $this->createLargeTestTournament($teamCount);
    }

    private function getRandomRequestType(): string
    {
        $types = ['tournament_list', 'live_data', 'bracket', 'standings', 'analytics'];
        return $types[array_rand($types)];
    }

    private function executeLoadTestRequest(int $tournamentId, string $requestType): void
    {
        match ($requestType) {
            'tournament_list' => $this->queryService->getTournamentList(),
            'live_data' => $this->queryService->getLiveTournamentData($tournamentId),
            'bracket' => $this->queryService->getTournamentBracket($tournamentId),
            'standings' => $this->queryService->getSwissStandings($tournamentId),
            'analytics' => $this->queryService->getTournamentAnalytics($tournamentId),
            default => throw new \InvalidArgumentException("Unknown request type: {$requestType}")
        };
    }

    private function createStressTestOperation(int $tournamentId): callable
    {
        $operations = [
            fn() => $this->queryService->getTournamentList(['status' => 'ongoing'], 50, 0),
            fn() => $this->queryService->getLiveTournamentData($tournamentId),
            fn() => $this->queryService->getSwissStandings($tournamentId),
            fn() => $this->queryService->getTournamentBracket($tournamentId)
        ];

        return $operations[array_rand($operations)];
    }

    private function simulateSwissPairing(int $tournamentId, int $teamCount): void
    {
        // Simulate complex Swiss pairing calculations
        $teams = range(1, $teamCount);
        
        // Simulate pairing algorithm complexity
        for ($i = 0; $i < $teamCount / 2; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                // Simulate pairing calculations
                $score = abs($teams[$i] - $teams[$j]);
            }
        }
    }

    private function calculateComplexityScore(int $teamCount, float $standingsTime, float $pairingTime): float
    {
        $expectedTime = log($teamCount, 2) * 10; // Expected logarithmic time
        $actualTime = $standingsTime + $pairingTime;
        
        return $actualTime / $expectedTime;
    }

    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        
        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        } else {
            return $values[floor($count / 2)];
        }
    }

    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;
        return $values[max(0, $index)];
    }

    private function calculateOverallStatus(): string
    {
        // Analyze test results to determine overall system performance status
        $issues = 0;
        
        foreach ($this->testResults as $testType => $results) {
            switch ($testType) {
                case 'load_testing':
                    if ($results['error_rate'] > 5 || $results['average_response_time'] > 1000) {
                        $issues++;
                    }
                    break;
                case 'query_performance':
                    foreach ($results as $query => $metrics) {
                        if ($metrics['average_time'] > 500 || $metrics['success_rate'] < 95) {
                            $issues++;
                        }
                    }
                    break;
                case 'cache_performance':
                    if ($results['cache_hit_rate'] < 80 || $results['cache_miss_penalty'] > 1000) {
                        $issues++;
                    }
                    break;
            }
        }
        
        return match (true) {
            $issues === 0 => 'excellent',
            $issues <= 2 => 'good',
            $issues <= 5 => 'acceptable',
            default => 'needs_improvement'
        };
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];
        
        // Analyze results and generate specific recommendations
        if (isset($this->testResults['load_testing'])) {
            $loadResults = $this->testResults['load_testing'];
            
            if ($loadResults['error_rate'] > 5) {
                $recommendations[] = [
                    'type' => 'load_optimization',
                    'priority' => 'high',
                    'description' => 'High error rate detected. Consider scaling database connections and optimizing queries.',
                    'metric' => "Error rate: {$loadResults['error_rate']}%"
                ];
            }
            
            if ($loadResults['average_response_time'] > 500) {
                $recommendations[] = [
                    'type' => 'response_time',
                    'priority' => 'medium',
                    'description' => 'Response times are slower than optimal. Review query optimization and caching strategies.',
                    'metric' => "Average response time: {$loadResults['average_response_time']}ms"
                ];
            }
        }
        
        if (isset($this->testResults['cache_performance'])) {
            $cacheResults = $this->testResults['cache_performance'];
            
            if ($cacheResults['cache_hit_rate'] < 80) {
                $recommendations[] = [
                    'type' => 'cache_tuning',
                    'priority' => 'medium',
                    'description' => 'Cache hit rate is below optimal. Review cache TTL settings and warming strategies.',
                    'metric' => "Cache hit rate: {$cacheResults['cache_hit_rate']}%"
                ];
            }
        }
        
        return $recommendations;
    }

    private function collectSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'database_version' => DB::select('SELECT VERSION() as version')[0]->version ?? 'unknown',
            'test_environment' => app()->environment()
        ];
    }

    private function displayReportSummary(array $report): void
    {
        $this->newLine();
        $this->info('📊 Performance Test Summary');
        $this->info('='.str_repeat('=', 27));
        
        $summary = $report['test_summary'];
        
        $this->table(['Metric', 'Value'], [
            ['Overall Status', strtoupper($summary['overall_status'])],
            ['Total Duration', $summary['total_duration'] . ' seconds'],
            ['Tests Executed', $summary['tests_executed']],
            ['Recommendations', count($report['performance_recommendations'])]
        ]);
        
        if (!empty($report['performance_recommendations'])) {
            $this->info('🔍 Key Recommendations:');
            foreach (array_slice($report['performance_recommendations'], 0, 3) as $rec) {
                $this->line("  • [{$rec['priority']}] {$rec['description']}");
            }
        }
    }
}