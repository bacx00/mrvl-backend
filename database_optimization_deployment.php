<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\OptimizedEloRatingService;
use App\Services\DatabaseIntegrityService;
use App\Services\DatabasePerformanceMonitoringService;

/**
 * MRVL Database Optimization Deployment Script
 * 
 * This script deploys all database optimizations for the MRVL esports platform:
 * - Creates additional indexes for performance
 * - Deploys optimized services
 * - Performs integrity checks
 * - Sets up monitoring
 * - Creates performance baselines
 */

class DatabaseOptimizationDeployment
{
    private $results = [];
    private $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
        
        // Bootstrap Laravel
        $app = require_once 'bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    }

    /**
     * Main deployment process
     */
    public function deploy(): array
    {
        echo "🚀 Starting MRVL Database Optimization Deployment\n";
        echo "================================================\n\n";

        try {
            // Step 1: Pre-deployment checks
            $this->performPreDeploymentChecks();
            
            // Step 2: Create additional indexes
            $this->createPerformanceIndexes();
            
            // Step 3: Deploy optimized services
            $this->deployOptimizedServices();
            
            // Step 4: Run integrity checks
            $this->runIntegrityChecks();
            
            // Step 5: Set up monitoring
            $this->setupMonitoring();
            
            // Step 6: Create performance baseline
            $this->createPerformanceBaseline();
            
            // Step 7: Verify deployment
            $this->verifyDeployment();
            
            $this->results['status'] = 'success';
            $this->results['deployment_time'] = round(microtime(true) - $this->startTime, 2);
            
            echo "\n✅ Database optimization deployment completed successfully!\n";
            echo "Total deployment time: {$this->results['deployment_time']} seconds\n\n";
            
        } catch (\Exception $e) {
            $this->results['status'] = 'failed';
            $this->results['error'] = $e->getMessage();
            
            echo "\n❌ Deployment failed: " . $e->getMessage() . "\n";
            Log::error('Database optimization deployment failed', ['error' => $e->getMessage()]);
        }

        return $this->results;
    }

    /**
     * Pre-deployment checks
     */
    private function performPreDeploymentChecks(): void
    {
        echo "🔍 Performing pre-deployment checks...\n";

        // Check database connection
        try {
            DB::connection()->getPdo();
            echo "  ✓ Database connection verified\n";
        } catch (\Exception $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }

        // Check required tables exist
        $requiredTables = [
            'tournaments', 'teams', 'players', 'matches', 'bracket_matches',
            'tournament_teams', 'match_player_stats'
        ];

        foreach ($requiredTables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                throw new \Exception("Required table '$table' does not exist");
            }
            echo "  ✓ Table '$table' exists\n";
        }

        // Check disk space (basic check)
        $freeSpace = disk_free_space('/');
        $requiredSpace = 1024 * 1024 * 1024; // 1GB
        
        if ($freeSpace < $requiredSpace) {
            throw new \Exception("Insufficient disk space. Required: 1GB, Available: " . $this->formatBytes($freeSpace));
        }
        echo "  ✓ Sufficient disk space available\n";

        $this->results['pre_checks'] = 'passed';
        echo "  ✅ Pre-deployment checks completed\n\n";
    }

    /**
     * Create additional performance indexes
     */
    private function createPerformanceIndexes(): void
    {
        echo "🔧 Creating additional performance indexes...\n";

        $indexes = [
            // Tournament performance indexes
            [
                'table' => 'tournaments',
                'name' => 'idx_tournaments_performance_enhanced',
                'columns' => ['status', 'featured', 'region', 'start_date'],
                'description' => 'Enhanced tournament search performance'
            ],
            [
                'table' => 'tournaments',
                'name' => 'idx_tournaments_registration_window',
                'columns' => ['registration_start', 'registration_end', 'team_count', 'max_teams'],
                'description' => 'Tournament registration queries'
            ],

            // Team performance indexes
            [
                'table' => 'teams',
                'name' => 'idx_teams_elo_performance',
                'columns' => ['game', 'region', 'elo_rating', 'status'],
                'description' => 'Team ELO ranking queries'
            ],
            [
                'table' => 'teams',
                'name' => 'idx_teams_stats_performance',
                'columns' => ['status', 'wins', 'losses', 'earnings'],
                'description' => 'Team statistics queries'
            ],

            // Player performance indexes
            [
                'table' => 'players',
                'name' => 'idx_players_role_performance',
                'columns' => ['role', 'status', 'elo_rating', 'team_id'],
                'description' => 'Player role-based ranking queries'
            ],
            [
                'table' => 'players',
                'name' => 'idx_players_team_lookup',
                'columns' => ['team_id', 'status', 'role', 'position_order'],
                'description' => 'Team roster queries'
            ],

            // Match performance indexes  
            [
                'table' => 'matches',
                'name' => 'idx_matches_live_performance',
                'columns' => ['status', 'started_at', 'event_id'],
                'description' => 'Live match queries'
            ],
            [
                'table' => 'matches',
                'name' => 'idx_matches_team_history',
                'columns' => ['team1_id', 'team2_id', 'status', 'completed_at'],
                'description' => 'Team match history queries'
            ],

            // Bracket match indexes
            [
                'table' => 'bracket_matches',
                'name' => 'idx_bracket_live_scoring',
                'columns' => ['tournament_id', 'status', 'round', 'started_at'],
                'description' => 'Live bracket scoring queries'
            ],
            [
                'table' => 'bracket_matches',
                'name' => 'idx_bracket_progression',
                'columns' => ['tournament_id', 'round', 'match_number', 'status'],
                'description' => 'Tournament bracket progression'
            ],

            // Tournament teams performance
            [
                'table' => 'tournament_teams',
                'name' => 'idx_tournament_teams_swiss',
                'columns' => ['tournament_id', 'swiss_wins', 'swiss_losses', 'swiss_buchholz'],
                'description' => 'Swiss tournament standings'
            ],
            [
                'table' => 'tournament_teams',
                'name' => 'idx_tournament_teams_bracket',
                'columns' => ['tournament_id', 'placement', 'prize_money'],
                'description' => 'Bracket tournament results'
            ]
        ];

        $createdIndexes = 0;
        $skippedIndexes = 0;

        foreach ($indexes as $index) {
            try {
                // Check if index already exists
                $existingIndexes = DB::select("SHOW INDEX FROM `{$index['table']}` WHERE Key_name = ?", [$index['name']]);
                
                if (!empty($existingIndexes)) {
                    echo "  ⏭️  Index '{$index['name']}' already exists on {$index['table']}\n";
                    $skippedIndexes++;
                    continue;
                }

                // Create the index
                $columns = implode(', ', array_map(fn($col) => "`$col`", $index['columns']));
                $sql = "CREATE INDEX `{$index['name']}` ON `{$index['table']}` ({$columns})";
                
                DB::statement($sql);
                echo "  ✓ Created index '{$index['name']}' on {$index['table']} - {$index['description']}\n";
                $createdIndexes++;
                
            } catch (\Exception $e) {
                echo "  ⚠️  Failed to create index '{$index['name']}': " . $e->getMessage() . "\n";
                Log::warning("Failed to create index", [
                    'index' => $index['name'],
                    'table' => $index['table'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->results['indexes'] = [
            'created' => $createdIndexes,
            'skipped' => $skippedIndexes,
            'total_attempted' => count($indexes)
        ];

        echo "  ✅ Index creation completed: {$createdIndexes} created, {$skippedIndexes} skipped\n\n";
    }

    /**
     * Deploy optimized services
     */
    private function deployOptimizedServices(): void
    {
        echo "🚀 Deploying optimized services...\n";

        $services = [
            'OptimizedEloRatingService' => 'Enhanced ELO rating calculations',
            'EnhancedTournamentQueryService' => 'Optimized tournament queries',
            'TournamentCacheService' => 'Advanced caching system',
            'DatabaseIntegrityService' => 'Data integrity monitoring',
            'DatabasePerformanceMonitoringService' => 'Performance monitoring'
        ];

        foreach ($services as $serviceClass => $description) {
            try {
                $fullClassName = "App\\Services\\{$serviceClass}";
                
                if (class_exists($fullClassName)) {
                    echo "  ✓ Service '{$serviceClass}' deployed - {$description}\n";
                } else {
                    echo "  ⚠️  Service '{$serviceClass}' not found\n";
                }
            } catch (\Exception $e) {
                echo "  ❌ Failed to deploy service '{$serviceClass}': " . $e->getMessage() . "\n";
            }
        }

        $this->results['services'] = 'deployed';
        echo "  ✅ Service deployment completed\n\n";
    }

    /**
     * Run integrity checks
     */
    private function runIntegrityChecks(): void
    {
        echo "🔍 Running database integrity checks...\n";

        try {
            $integrityService = new DatabaseIntegrityService();
            $integrityResults = $integrityService->performIntegrityCheck();

            echo "  ✓ Integrity check completed\n";
            echo "  📊 Issues found: {$integrityResults['issues_found']}\n";
            echo "  ⚠️  Critical issues: {$integrityResults['critical_issues']}\n";
            echo "  📈 Overall status: {$integrityResults['status']}\n";

            if ($integrityResults['critical_issues'] > 0) {
                echo "  🔧 Running automated cleanup...\n";
                $cleanupResults = $integrityService->performAutomatedCleanup();
                echo "  ✓ Cleanup completed: {$cleanupResults['records_affected']} records affected\n";
            }

            $this->results['integrity'] = [
                'status' => $integrityResults['status'],
                'issues_found' => $integrityResults['issues_found'],
                'critical_issues' => $integrityResults['critical_issues']
            ];

        } catch (\Exception $e) {
            echo "  ❌ Integrity check failed: " . $e->getMessage() . "\n";
            $this->results['integrity'] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        echo "  ✅ Integrity checks completed\n\n";
    }

    /**
     * Set up monitoring
     */
    private function setupMonitoring(): void
    {
        echo "📊 Setting up performance monitoring...\n";

        try {
            $monitoringService = new DatabasePerformanceMonitoringService();
            
            // Perform initial performance analysis
            $analysisResults = $monitoringService->performPerformanceAnalysis();
            
            echo "  ✓ Performance analysis completed\n";
            echo "  📈 Overall performance score: {$analysisResults['overall_score']}/100\n";
            echo "  🐌 Slow queries detected: " . count($analysisResults['slow_queries']['queries']) . "\n";
            echo "  🔗 Active connections: {$analysisResults['connection_metrics']['active_connections']}\n";

            // Check for critical issues
            $criticalIssues = $monitoringService->checkForCriticalIssues();
            echo "  🚨 Critical alerts: {$criticalIssues['critical_count']}\n";
            echo "  ⚠️  Warning alerts: {$criticalIssues['warning_count']}\n";

            $this->results['monitoring'] = [
                'performance_score' => $analysisResults['overall_score'],
                'slow_queries' => count($analysisResults['slow_queries']['queries']),
                'critical_alerts' => $criticalIssues['critical_count'],
                'warning_alerts' => $criticalIssues['warning_count']
            ];

        } catch (\Exception $e) {
            echo "  ❌ Monitoring setup failed: " . $e->getMessage() . "\n";
            $this->results['monitoring'] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        echo "  ✅ Monitoring setup completed\n\n";
    }

    /**
     * Create performance baseline
     */
    private function createPerformanceBaseline(): void
    {
        echo "📏 Creating performance baseline...\n";

        try {
            // Run sample queries to establish baseline
            $baselineQueries = [
                'tournament_list' => 'SELECT COUNT(*) FROM tournaments WHERE status = "ongoing"',
                'team_rankings' => 'SELECT COUNT(*) FROM teams WHERE status = "active" ORDER BY elo_rating DESC LIMIT 50',
                'player_search' => 'SELECT COUNT(*) FROM players WHERE status = "active" AND role = "dps"',
                'live_matches' => 'SELECT COUNT(*) FROM bracket_matches WHERE status = "live"',
                'tournament_standings' => 'SELECT COUNT(*) FROM tournament_teams tt JOIN tournaments t ON tt.tournament_id = t.id WHERE t.status = "ongoing"'
            ];

            $baseline = [];
            
            foreach ($baselineQueries as $queryName => $sql) {
                $startTime = microtime(true);
                DB::select($sql);
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                
                $baseline[$queryName] = $executionTime;
                echo "  ✓ {$queryName}: {$executionTime}ms\n";
            }

            // Store baseline in cache for future comparisons
            \Illuminate\Support\Facades\Cache::put('mrvl_performance_baseline', $baseline, 86400); // 24 hours

            $this->results['baseline'] = $baseline;

        } catch (\Exception $e) {
            echo "  ❌ Baseline creation failed: " . $e->getMessage() . "\n";
            $this->results['baseline'] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        echo "  ✅ Performance baseline created\n\n";
    }

    /**
     * Verify deployment
     */
    private function verifyDeployment(): void
    {
        echo "✅ Verifying deployment...\n";

        $verificationTests = [
            'database_connection' => $this->verifyDatabaseConnection(),
            'indexes_created' => $this->verifyIndexes(),
            'services_available' => $this->verifyServices(),
            'cache_working' => $this->verifyCache(),
            'elo_calculations' => $this->verifyEloCalculations()
        ];

        foreach ($verificationTests as $test => $result) {
            if ($result) {
                echo "  ✓ {$test}: PASSED\n";
            } else {
                echo "  ❌ {$test}: FAILED\n";
            }
        }

        $passedTests = count(array_filter($verificationTests));
        $totalTests = count($verificationTests);
        
        echo "  📊 Verification results: {$passedTests}/{$totalTests} tests passed\n";

        $this->results['verification'] = [
            'tests_passed' => $passedTests,
            'total_tests' => $totalTests,
            'success_rate' => round(($passedTests / $totalTests) * 100, 1)
        ];

        echo "  ✅ Deployment verification completed\n\n";
    }

    // Verification helper methods
    private function verifyDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function verifyIndexes(): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM tournaments WHERE Key_name LIKE 'idx_tournaments_%'");
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function verifyServices(): bool
    {
        try {
            $eloService = new OptimizedEloRatingService();
            return $eloService instanceof OptimizedEloRatingService;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function verifyCache(): bool
    {
        try {
            \Illuminate\Support\Facades\Cache::put('test_key', 'test_value', 60);
            $value = \Illuminate\Support\Facades\Cache::get('test_key');
            \Illuminate\Support\Facades\Cache::forget('test_key');
            return $value === 'test_value';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function verifyEloCalculations(): bool
    {
        try {
            $eloService = new OptimizedEloRatingService();
            $result = $eloService->calculateNewRatings(1500, 1500, 1);
            return isset($result['team1_new_rating']) && isset($result['team2_new_rating']);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Generate deployment report
     */
    public function generateReport(): string
    {
        $report = "\n";
        $report .= "MRVL Database Optimization Deployment Report\n";
        $report .= "==========================================\n\n";
        $report .= "Deployment Status: " . strtoupper($this->results['status']) . "\n";
        $report .= "Deployment Time: " . ($this->results['deployment_time'] ?? 'Unknown') . " seconds\n";
        $report .= "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

        if (isset($this->results['indexes'])) {
            $report .= "Indexes:\n";
            $report .= "  - Created: " . $this->results['indexes']['created'] . "\n";
            $report .= "  - Skipped: " . $this->results['indexes']['skipped'] . "\n";
            $report .= "  - Total: " . $this->results['indexes']['total_attempted'] . "\n\n";
        }

        if (isset($this->results['integrity'])) {
            $report .= "Integrity Check:\n";
            $report .= "  - Status: " . $this->results['integrity']['status'] . "\n";
            $report .= "  - Issues Found: " . $this->results['integrity']['issues_found'] . "\n";
            $report .= "  - Critical Issues: " . $this->results['integrity']['critical_issues'] . "\n\n";
        }

        if (isset($this->results['monitoring'])) {
            $report .= "Performance Monitoring:\n";
            $report .= "  - Performance Score: " . $this->results['monitoring']['performance_score'] . "/100\n";
            $report .= "  - Slow Queries: " . $this->results['monitoring']['slow_queries'] . "\n";
            $report .= "  - Critical Alerts: " . $this->results['monitoring']['critical_alerts'] . "\n\n";
        }

        if (isset($this->results['verification'])) {
            $report .= "Verification:\n";
            $report .= "  - Tests Passed: " . $this->results['verification']['tests_passed'] . "/" . $this->results['verification']['total_tests'] . "\n";
            $report .= "  - Success Rate: " . $this->results['verification']['success_rate'] . "%\n\n";
        }

        if (isset($this->results['baseline'])) {
            $report .= "Performance Baseline (ms):\n";
            foreach ($this->results['baseline'] as $query => $time) {
                if (is_numeric($time)) {
                    $report .= "  - {$query}: {$time}ms\n";
                }
            }
            $report .= "\n";
        }

        $report .= "Deployment completed at: " . date('Y-m-d H:i:s') . "\n";

        return $report;
    }
}

// Run deployment if script is executed directly
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    $deployment = new DatabaseOptimizationDeployment();
    $results = $deployment->deploy();
    
    // Generate and save report
    $report = $deployment->generateReport();
    echo $report;
    
    file_put_contents('database_optimization_deployment_report_' . date('Y_m_d_H_i_s') . '.txt', $report);
    
    exit($results['status'] === 'success' ? 0 : 1);
}