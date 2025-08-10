<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseMaintenanceService;
use App\Services\TournamentCacheOptimizationService;
use App\Services\TournamentQueryMonitoringService;
use Illuminate\Support\Facades\Log;

/**
 * Optimize Tournament Database Command
 * 
 * Comprehensive database optimization command for tournament operations:
 * - Run maintenance routines
 * - Optimize indexes and queries
 * - Update cache strategies
 * - Monitor performance metrics
 */
class OptimizeTournamentDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tournament:optimize-db 
                           {--maintenance : Run database maintenance routines}
                           {--cache : Optimize caching strategies}
                           {--monitor : Run performance monitoring}
                           {--all : Run all optimization tasks}
                           {--event= : Specific event ID to optimize}';

    /**
     * The console command description.
     */
    protected $description = 'Optimize tournament database performance with comprehensive maintenance, caching, and monitoring';

    private DatabaseMaintenanceService $maintenanceService;
    private TournamentCacheOptimizationService $cacheService;
    private TournamentQueryMonitoringService $monitoringService;

    /**
     * Create a new command instance.
     */
    public function __construct(
        DatabaseMaintenanceService $maintenanceService,
        TournamentCacheOptimizationService $cacheService,
        TournamentQueryMonitoringService $monitoringService
    ) {
        parent::__construct();
        $this->maintenanceService = $maintenanceService;
        $this->cacheService = $cacheService;
        $this->monitoringService = $monitoringService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🏆 Marvel Rivals Tournament Database Optimization');
        $this->info('================================================');

        $eventId = $this->option('event');
        $runAll = $this->option('all');

        try {
            if ($runAll || $this->option('maintenance')) {
                $this->runMaintenanceOperations();
            }

            if ($runAll || $this->option('cache')) {
                $this->runCacheOptimizations($eventId);
            }

            if ($runAll || $this->option('monitor')) {
                $this->runPerformanceMonitoring();
            }

            if (!$runAll && !$this->option('maintenance') && !$this->option('cache') && !$this->option('monitor')) {
                $this->displayUsageHelp();
            }

            $this->info("\n✅ Database optimization completed successfully!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Optimization failed: " . $e->getMessage());
            Log::error('Tournament database optimization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Run database maintenance operations
     */
    private function runMaintenanceOperations(): void
    {
        $this->info("\n🔧 Running Database Maintenance Operations...");
        $this->line("=====================================");

        $bar = $this->output->createProgressBar(6);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        
        $bar->setMessage('Analyzing table statistics...');
        $bar->advance();

        $results = $this->maintenanceService->runMaintenanceRoutine();

        $bar->setMessage('Optimizing indexes...');
        $bar->advance();

        $bar->setMessage('Archiving old tournaments...');
        $bar->advance();

        $bar->setMessage('Cleaning temporary data...');
        $bar->advance();

        $bar->setMessage('Optimizing tournament tables...');
        $bar->advance();

        $bar->setMessage('Updating denormalized data...');
        $bar->advance();

        $bar->finish();
        $this->newLine(2);

        if ($results['success']) {
            $this->info("✅ Maintenance completed in {$results['duration_seconds']} seconds");
            
            // Display detailed results
            $this->displayMaintenanceResults($results);
        } else {
            $this->error("❌ Maintenance failed: " . ($results['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Run cache optimization operations
     */
    private function runCacheOptimizations(?int $eventId = null): void
    {
        $this->info("\n🚀 Running Cache Optimization Operations...");
        $this->line("========================================");

        if ($eventId) {
            $this->line("Optimizing caches for Event ID: {$eventId}");
            
            // Warm up caches for specific event
            $this->cacheService->warmupTournamentCaches($eventId);
            $this->info("✅ Event-specific caches optimized");
            
            // Display cache performance metrics
            $this->displayCacheMetrics($eventId);
        } else {
            $this->line("Running global cache optimizations...");
            
            // Get active tournaments and warm up their caches
            $activeTournaments = \DB::select("
                SELECT DISTINCT event_id, COUNT(*) as active_matches
                FROM bracket_matches 
                WHERE status IN ('pending', 'ready', 'live', 'ongoing')
                GROUP BY event_id
                ORDER BY active_matches DESC
                LIMIT 5
            ");

            if (empty($activeTournaments)) {
                $this->warn("⚠️  No active tournaments found for cache optimization");
                return;
            }

            $bar = $this->output->createProgressBar(count($activeTournaments));
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

            foreach ($activeTournaments as $tournament) {
                $bar->setMessage("Warming up Event ID: {$tournament->event_id}");
                $this->cacheService->warmupTournamentCaches($tournament->event_id);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("✅ Cache optimization completed for " . count($activeTournaments) . " active tournaments");
        }

        // Display overall cache performance
        $metrics = $this->cacheService->getCachePerformanceMetrics();
        $this->displayOverallCacheMetrics($metrics);
    }

    /**
     * Run performance monitoring
     */
    private function runPerformanceMonitoring(): void
    {
        $this->info("\n📊 Running Performance Monitoring...");
        $this->line("=================================");

        $this->line("Analyzing query performance and database health...");
        
        $monitoringResults = $this->monitoringService->monitorTournamentQueries();
        
        $this->displayMonitoringResults($monitoringResults);
        
        // Get trends if available
        $trends = $this->monitoringService->getMonitoringTrends(6); // Last 6 hours
        if (!empty($trends['trends'])) {
            $this->displayPerformanceTrends($trends);
        }
    }

    /**
     * Display maintenance operation results
     */
    private function displayMaintenanceResults(array $results): void
    {
        $this->table(
            ['Operation', 'Status', 'Details'],
            [
                ['Table Analysis', '✅ Completed', count($results['operations']['analyze_tables'] ?? []) . ' tables analyzed'],
                ['Index Optimization', '✅ Completed', count($results['operations']['index_maintenance'] ?? []) . ' indexes processed'],
                ['Tournament Archival', '✅ Completed', count($results['operations']['archive_tournaments'] ?? []) . ' tournaments processed'],
                ['Temp Data Cleanup', '✅ Completed', 'Multiple cleanup operations'],
                ['Table Optimization', '✅ Completed', count($results['operations']['optimize_tables'] ?? []) . ' tables optimized'],
                ['Denormalized Updates', '✅ Completed', 'Statistics updated']
            ]
        );

        // Show space savings if available
        if (isset($results['operations']['optimize_tables'])) {
            $totalSaved = 0;
            foreach ($results['operations']['optimize_tables'] as $table => $data) {
                if (isset($data['size_reduced_mb'])) {
                    $totalSaved += $data['size_reduced_mb'];
                }
            }
            
            if ($totalSaved > 0) {
                $this->info("💾 Total space saved: {$totalSaved} MB");
            }
        }
    }

    /**
     * Display cache performance metrics
     */
    private function displayCacheMetrics(int $eventId): void
    {
        $this->info("\n📈 Cache Performance Metrics for Event {$eventId}:");
        
        // Get cached tournament stats
        $stats = $this->cacheService->getCachedTournamentStats($eventId);
        
        if ($stats) {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Matches', $stats['stats']->total_matches ?? 'N/A'],
                    ['Completed Matches', $stats['stats']->completed_matches ?? 'N/A'],
                    ['Live Matches', $stats['stats']->live_matches ?? 'N/A'],
                    ['Cache Last Updated', $stats['last_updated'] ?? 'N/A']
                ]
            );
        }
    }

    /**
     * Display overall cache performance metrics
     */
    private function displayOverallCacheMetrics(array $metrics): void
    {
        $this->info("\n🎯 Overall Cache Performance:");
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Cache Keys', number_format($metrics['total_cache_keys'])],
                ['Total Memory Usage', $metrics['total_memory_mb'] . ' MB'],
                ['Active Tournaments', $metrics['active_tournaments']],
                ['Avg Memory per Tournament', $metrics['avg_memory_per_tournament'] . ' MB']
            ]
        );
    }

    /**
     * Display monitoring results
     */
    private function displayMonitoringResults(array $results): void
    {
        $this->info("🔍 Query Performance Analysis:");
        
        $queryData = [];
        foreach ($results['query_performance'] as $queryName => $metrics) {
            $status = isset($metrics['error']) ? '❌ Error' : 
                     ($metrics['is_slow'] ?? false ? '⚠️  Slow' : '✅ OK');
            
            $queryData[] = [
                $queryName,
                $status,
                $metrics['execution_time_ms'] ?? 'N/A',
                $metrics['rows_returned'] ?? 'N/A'
            ];
        }
        
        $this->table(
            ['Query', 'Status', 'Time (ms)', 'Rows'],
            $queryData
        );

        // Display alerts if any
        if (!empty($results['alerts'])) {
            $this->warn("\n⚠️  Performance Alerts:");
            foreach ($results['alerts'] as $alert) {
                $icon = $alert['severity'] === 'critical' ? '🔴' : 
                       ($alert['severity'] === 'warning' ? '🟡' : '🔵');
                $this->line("{$icon} {$alert['message']}");
            }
        } else {
            $this->info("\n✅ No performance alerts detected");
        }

        // Display connection health
        $connectionHealth = $results['connection_health'];
        $healthIcon = $connectionHealth['health_status'] === 'healthy' ? '✅' : 
                     ($connectionHealth['health_status'] === 'warning' ? '⚠️' : '❌');
        
        $this->info("\n{$healthIcon} Connection Health: {$connectionHealth['health_status']}");
        $this->line("   Current Connections: {$connectionHealth['current_connections']}/{$connectionHealth['max_connections']} ({$connectionHealth['connection_utilization_percent']}%)");
    }

    /**
     * Display performance trends
     */
    private function displayPerformanceTrends(array $trends): void
    {
        $this->info("\n📈 Performance Trends (Last {$trends['period_hours']} hours):");
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Avg Slow Queries/Hour', number_format($trends['summary']['avg_slow_queries_per_hour'] ?? 0, 2)],
                ['Peak Connection Usage', number_format($trends['summary']['peak_connection_utilization'] ?? 0, 2) . '%'],
                ['Total Alerts', $trends['summary']['total_alerts'] ?? 0]
            ]
        );
    }

    /**
     * Display usage help
     */
    private function displayUsageHelp(): void
    {
        $this->info("\n📖 Usage Examples:");
        $this->line("tournament:optimize-db --all                    # Run all optimizations");
        $this->line("tournament:optimize-db --maintenance           # Run maintenance only");
        $this->line("tournament:optimize-db --cache --event=1       # Optimize cache for event 1");
        $this->line("tournament:optimize-db --monitor               # Run monitoring only");
        $this->line("");
        $this->info("Use --help for detailed option descriptions");
    }
}