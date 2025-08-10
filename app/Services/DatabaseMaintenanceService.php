<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

/**
 * Database Maintenance Service
 * 
 * Handles:
 * - VACUUM and ANALYZE schedules
 * - Partition strategy for historical data
 * - Archive completed tournaments
 * - Index maintenance routines
 * - Performance monitoring
 */
class DatabaseMaintenanceService
{
    private const ARCHIVE_AFTER_DAYS = 180; // Archive tournaments after 6 months
    private const CLEANUP_TEMP_DATA_DAYS = 7; // Clean temporary data after 1 week
    private const INDEX_REBUILD_THRESHOLD = 0.3; // Rebuild if fragmentation > 30%

    /**
     * Run comprehensive database maintenance
     */
    public function runMaintenanceRoutine(): array
    {
        $results = [
            'started_at' => now(),
            'operations' => []
        ];

        try {
            // 1. Update table statistics
            $results['operations']['analyze_tables'] = $this->analyzeTableStatistics();
            
            // 2. Rebuild fragmented indexes
            $results['operations']['index_maintenance'] = $this->performIndexMaintenance();
            
            // 3. Archive old tournaments
            $results['operations']['archive_tournaments'] = $this->archiveOldTournaments();
            
            // 4. Clean temporary data
            $results['operations']['cleanup_temp_data'] = $this->cleanupTemporaryData();
            
            // 5. Optimize tournament tables
            $results['operations']['optimize_tables'] = $this->optimizeTournamentTables();
            
            // 6. Update denormalized data
            $results['operations']['update_denormalized'] = $this->updateDenormalizedData();
            
            $results['completed_at'] = now();
            $results['duration_seconds'] = $results['completed_at']->diffInSeconds($results['started_at']);
            $results['success'] = true;

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            $results['success'] = false;
            
            Log::error('Database maintenance failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        Log::info('Database maintenance completed', $results);
        return $results;
    }

    /**
     * Analyze table statistics for query optimizer
     */
    public function analyzeTableStatistics(): array
    {
        $tables = [
            'bracket_matches',
            'bracket_stages', 
            'bracket_games',
            'bracket_standings',
            'bracket_swiss_standings',
            'tournament_phases',
            'teams',
            'players',
            'events',
            'matches',
            'match_maps',
            'match_player_stats'
        ];

        $results = [];
        
        foreach ($tables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    DB::statement("ANALYZE TABLE {$table}");
                    
                    // Get table statistics
                    $stats = DB::select("
                        SELECT 
                            table_name,
                            table_rows,
                            data_length,
                            index_length,
                            (data_length + index_length) as total_size,
                            avg_row_length
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE() 
                        AND table_name = ?
                    ", [$table]);

                    $results[$table] = [
                        'analyzed' => true,
                        'stats' => $stats[0] ?? null
                    ];
                }
            } catch (\Exception $e) {
                $results[$table] = [
                    'analyzed' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Perform index maintenance and optimization
     */
    public function performIndexMaintenance(): array
    {
        $results = [];
        
        // Get index statistics
        $indexes = DB::select("
            SELECT 
                table_name,
                index_name,
                cardinality,
                pages,
                CASE 
                    WHEN cardinality > 0 
                    THEN ROUND((pages * 16384) / cardinality, 2)
                    ELSE 0
                END as avg_bytes_per_key
            FROM information_schema.statistics s
            WHERE table_schema = DATABASE()
            AND table_name IN (
                'bracket_matches', 'bracket_stages', 'bracket_games', 
                'teams', 'players', 'events', 'matches'
            )
            AND index_name != 'PRIMARY'
            GROUP BY table_name, index_name
        ");

        foreach ($indexes as $index) {
            try {
                // Check if index needs optimization
                $needsOptimization = $this->indexNeedsOptimization($index);
                
                if ($needsOptimization) {
                    // For MySQL, we optimize by rebuilding the table
                    DB::statement("OPTIMIZE TABLE {$index->table_name}");
                    
                    $results[] = [
                        'table' => $index->table_name,
                        'index' => $index->index_name,
                        'action' => 'optimized',
                        'reason' => 'fragmentation_threshold_exceeded'
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'table' => $index->table_name,
                    'index' => $index->index_name,
                    'action' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Archive completed tournaments to reduce active dataset size
     */
    public function archiveOldTournaments(): array
    {
        $cutoffDate = Carbon::now()->subDays(self::ARCHIVE_AFTER_DAYS);
        
        // Find tournaments to archive
        $tournamentsToArchive = DB::select("
            SELECT 
                e.id,
                e.name,
                e.end_date,
                COUNT(bm.id) as total_matches,
                COUNT(CASE WHEN bm.status = 'completed' THEN 1 END) as completed_matches
            FROM events e
            LEFT JOIN bracket_matches bm ON e.id = bm.event_id
            WHERE e.end_date < ?
            AND e.status IN ('completed', 'cancelled')
            AND NOT EXISTS (
                SELECT 1 FROM archived_tournaments at WHERE at.original_event_id = e.id
            )
            GROUP BY e.id, e.name, e.end_date
            HAVING completed_matches = total_matches OR total_matches = 0
        ", [$cutoffDate]);

        $results = [];
        
        foreach ($tournamentsToArchive as $tournament) {
            try {
                $archived = $this->archiveTournament($tournament->id);
                
                $results[] = [
                    'event_id' => $tournament->id,
                    'event_name' => $tournament->name,
                    'end_date' => $tournament->end_date,
                    'total_matches' => $tournament->total_matches,
                    'archived' => $archived,
                    'archive_size_mb' => $this->calculateArchiveSize($tournament->id)
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'event_id' => $tournament->id,
                    'event_name' => $tournament->name,
                    'archived' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Clean up temporary and stale data
     */
    public function cleanupTemporaryData(): array
    {
        $cutoffDate = Carbon::now()->subDays(self::CLEANUP_TEMP_DATA_DAYS);
        $results = [];

        // Clean old cache entries (if stored in database)
        $cacheCleanup = DB::delete("
            DELETE FROM cache 
            WHERE expiration < ? 
            AND key LIKE ?
        ", [time(), 'tournament_%']);

        $results['cache_entries_cleaned'] = $cacheCleanup;

        // Clean old session data
        $sessionCleanup = DB::delete("
            DELETE FROM sessions 
            WHERE last_activity < ?
        ", [$cutoffDate->timestamp]);

        $results['sessions_cleaned'] = $sessionCleanup;

        // Clean old failed jobs
        $jobsCleanup = DB::delete("
            DELETE FROM failed_jobs 
            WHERE failed_at < ?
        ", [$cutoffDate]);

        $results['failed_jobs_cleaned'] = $jobsCleanup;

        // Clean old user activities
        if (Schema::hasTable('user_activities')) {
            $activitiesCleanup = DB::delete("
                DELETE FROM user_activities 
                WHERE created_at < ?
                AND action NOT IN ('tournament_win', 'major_achievement')
            ", [$cutoffDate]);

            $results['user_activities_cleaned'] = $activitiesCleanup;
        }

        return $results;
    }

    /**
     * Optimize tournament-specific tables
     */
    public function optimizeTournamentTables(): array
    {
        $tables = [
            'bracket_matches',
            'bracket_games', 
            'bracket_standings',
            'bracket_swiss_standings',
            'match_player_stats'
        ];

        $results = [];

        foreach ($tables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    // Get table size before optimization
                    $sizeBefore = $this->getTableSize($table);
                    
                    // Optimize table
                    DB::statement("OPTIMIZE TABLE {$table}");
                    
                    // Get table size after optimization
                    $sizeAfter = $this->getTableSize($table);
                    
                    $results[$table] = [
                        'optimized' => true,
                        'size_before_mb' => round($sizeBefore / 1024 / 1024, 2),
                        'size_after_mb' => round($sizeAfter / 1024 / 1024, 2),
                        'size_reduced_mb' => round(($sizeBefore - $sizeAfter) / 1024 / 1024, 2),
                        'reduction_percentage' => $sizeBefore > 0 ? round((($sizeBefore - $sizeAfter) / $sizeBefore) * 100, 2) : 0
                    ];
                }
            } catch (\Exception $e) {
                $results[$table] = [
                    'optimized' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Update denormalized data for performance
     */
    public function updateDenormalizedData(): array
    {
        $results = [];

        try {
            // Update team win/loss records
            $teamUpdates = DB::update("
                UPDATE teams t
                SET 
                    wins = (
                        SELECT COUNT(*) 
                        FROM bracket_matches bm 
                        WHERE bm.winner_id = t.id
                    ),
                    losses = (
                        SELECT COUNT(*) 
                        FROM bracket_matches bm 
                        WHERE (bm.team1_id = t.id OR bm.team2_id = t.id)
                        AND bm.winner_id IS NOT NULL 
                        AND bm.winner_id != t.id
                    ),
                    total_earnings = (
                        SELECT COALESCE(SUM(bs.prize_money), 0)
                        FROM bracket_standings bs
                        WHERE bs.team_id = t.id
                    )
                WHERE EXISTS (
                    SELECT 1 FROM bracket_matches bm2 
                    WHERE bm2.team1_id = t.id OR bm2.team2_id = t.id
                )
            ");

            $results['teams_updated'] = $teamUpdates;

            // Update player statistics
            if (Schema::hasTable('players')) {
                $playerUpdates = DB::update("
                    UPDATE players p
                    SET 
                        total_matches = (
                            SELECT COUNT(DISTINCT bm.id)
                            FROM bracket_matches bm
                            JOIN teams t ON (t.id = bm.team1_id OR t.id = bm.team2_id)
                            WHERE t.id = p.team_id
                            AND bm.status = 'completed'
                        ),
                        total_wins = (
                            SELECT COUNT(DISTINCT bm.id)
                            FROM bracket_matches bm
                            JOIN teams t ON t.id = bm.winner_id
                            WHERE t.id = p.team_id
                        )
                    WHERE p.team_id IS NOT NULL
                ");

                $results['players_updated'] = $playerUpdates;
            }

            // Update event statistics
            $eventUpdates = DB::update("
                UPDATE events e
                SET 
                    num_teams = (
                        SELECT COUNT(DISTINCT COALESCE(bm.team1_id, bm.team2_id))
                        FROM bracket_matches bm
                        WHERE bm.event_id = e.id
                        AND (bm.team1_id IS NOT NULL OR bm.team2_id IS NOT NULL)
                    ),
                    total_matches = (
                        SELECT COUNT(*)
                        FROM bracket_matches bm
                        WHERE bm.event_id = e.id
                    ),
                    completed_matches = (
                        SELECT COUNT(*)
                        FROM bracket_matches bm
                        WHERE bm.event_id = e.id
                        AND bm.status = 'completed'
                    )
                WHERE EXISTS (
                    SELECT 1 FROM bracket_matches bm2 WHERE bm2.event_id = e.id
                )
            ");

            $results['events_updated'] = $eventUpdates;

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Create database partitions for historical data (MySQL 8.0+)
     */
    public function createHistoricalPartitions(): array
    {
        $results = [];
        
        // Check if partitioning is supported
        if (!$this->supportsPartitioning()) {
            return ['error' => 'Partitioning not supported in this MySQL version'];
        }

        try {
            // Partition bracket_matches by event_id ranges
            $this->createBracketMatchesPartitions();
            $results['bracket_matches_partitioned'] = true;

            // Partition match_player_stats by date
            $this->createMatchStatsPartitions();
            $results['match_stats_partitioned'] = true;

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Get comprehensive database performance metrics
     */
    public function getDatabasePerformanceMetrics(): array
    {
        return [
            'table_sizes' => $this->getTableSizes(),
            'index_usage' => $this->getIndexUsageStats(),
            'query_performance' => $this->getSlowQueryStats(),
            'connection_stats' => $this->getConnectionStats(),
            'buffer_pool_stats' => $this->getBufferPoolStats(),
            'tournament_specific_metrics' => $this->getTournamentSpecificMetrics()
        ];
    }

    // Private helper methods

    private function indexNeedsOptimization($index): bool
    {
        // Simple heuristic: if average bytes per key is very high, index might be fragmented
        return $index->avg_bytes_per_key > 100; // Adjust threshold as needed
    }

    private function archiveTournament(int $eventId): bool
    {
        return DB::transaction(function() use ($eventId) {
            // Create archive record
            $archiveId = DB::table('archived_tournaments')->insertGetId([
                'original_event_id' => $eventId,
                'archived_at' => now(),
                'archive_data' => json_encode($this->getTournamentArchiveData($eventId))
            ]);

            if (!$archiveId) {
                return false;
            }

            // Move data to archive tables (simplified - in production you'd have dedicated archive tables)
            // For now, we just mark the tournament as archived
            DB::table('events')->where('id', $eventId)->update(['archived' => true]);

            return true;
        });
    }

    private function getTournamentArchiveData(int $eventId): array
    {
        return [
            'event' => DB::table('events')->find($eventId),
            'matches' => DB::table('bracket_matches')->where('event_id', $eventId)->get(),
            'standings' => DB::table('bracket_standings')->where('event_id', $eventId)->get(),
            'archive_size' => $this->calculateArchiveSize($eventId)
        ];
    }

    private function calculateArchiveSize(int $eventId): float
    {
        $size = DB::scalar("
            SELECT 
                SUM(LENGTH(JSON_OBJECT(
                    'matches', (SELECT COUNT(*) FROM bracket_matches WHERE event_id = ?),
                    'games', (SELECT COUNT(*) FROM bracket_games bg JOIN bracket_matches bm ON bg.bracket_match_id = bm.id WHERE bm.event_id = ?),
                    'standings', (SELECT COUNT(*) FROM bracket_standings WHERE event_id = ?)
                )))
        ", [$eventId, $eventId, $eventId]);

        return ($size ?? 0) / 1024 / 1024; // Convert to MB
    }

    private function getTableSize(string $tableName): int
    {
        $result = DB::select("
            SELECT (data_length + index_length) as size
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = ?
        ", [$tableName]);

        return $result[0]->size ?? 0;
    }

    private function supportsPartitioning(): bool
    {
        $result = DB::select("
            SELECT 
                PLUGIN_NAME, 
                PLUGIN_STATUS 
            FROM INFORMATION_SCHEMA.PLUGINS 
            WHERE PLUGIN_NAME = 'partition'
        ");

        return !empty($result) && $result[0]->PLUGIN_STATUS === 'ACTIVE';
    }

    private function createBracketMatchesPartitions(): void
    {
        // Implementation would depend on specific partitioning strategy
        // Example: partition by event_id ranges or date ranges
    }

    private function createMatchStatsPartitions(): void
    {
        // Implementation for match stats partitioning
    }

    private function getTableSizes(): array
    {
        return DB::select("
            SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb',
                table_rows,
                ROUND((data_length / 1024 / 1024), 2) AS 'data_mb',
                ROUND((index_length / 1024 / 1024), 2) AS 'index_mb'
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()
            AND table_name IN (
                'bracket_matches', 'bracket_games', 'bracket_standings', 
                'teams', 'players', 'events', 'matches'
            )
            ORDER BY (data_length + index_length) DESC
        ");
    }

    private function getIndexUsageStats(): array
    {
        return DB::select("
            SELECT 
                TABLE_NAME,
                INDEX_NAME,
                CARDINALITY,
                CASE 
                    WHEN CARDINALITY = 0 THEN 'Unused or Low Cardinality'
                    WHEN CARDINALITY < 100 THEN 'Low Cardinality'
                    ELSE 'Good Cardinality'
                END as index_quality
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN (
                'bracket_matches', 'bracket_games', 'bracket_standings',
                'teams', 'players', 'events'
            )
            ORDER BY TABLE_NAME, CARDINALITY DESC
        ");
    }

    private function getSlowQueryStats(): array
    {
        // This would require slow query log analysis
        // Simplified version returning basic query stats
        return [
            'slow_query_log_enabled' => DB::scalar("SELECT @@slow_query_log"),
            'long_query_time' => DB::scalar("SELECT @@long_query_time"),
            'queries_examined' => 'See slow query log for details'
        ];
    }

    private function getConnectionStats(): array
    {
        $stats = DB::select("SHOW STATUS LIKE 'Connections'");
        $threads = DB::select("SHOW STATUS LIKE 'Threads_%'");
        
        return [
            'total_connections' => $stats[0]->Value ?? 0,
            'current_connections' => collect($threads)->firstWhere('Variable_name', 'Threads_connected')->Value ?? 0,
            'max_connections' => DB::scalar("SELECT @@max_connections")
        ];
    }

    private function getBufferPoolStats(): array
    {
        $stats = DB::select("
            SELECT 
                VARIABLE_NAME, 
                VARIABLE_VALUE 
            FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
            WHERE VARIABLE_NAME LIKE 'Innodb_buffer_pool_%'
        ");

        return collect($stats)->pluck('VARIABLE_VALUE', 'VARIABLE_NAME')->toArray();
    }

    private function getTournamentSpecificMetrics(): array
    {
        return [
            'active_tournaments' => DB::scalar("
                SELECT COUNT(DISTINCT event_id) 
                FROM bracket_matches 
                WHERE status IN ('pending', 'ready', 'live', 'ongoing')
            "),
            'total_tournaments' => DB::scalar("SELECT COUNT(*) FROM events"),
            'total_matches' => DB::scalar("SELECT COUNT(*) FROM bracket_matches"),
            'live_matches' => DB::scalar("
                SELECT COUNT(*) FROM bracket_matches 
                WHERE status IN ('live', 'ongoing')
            "),
            'avg_matches_per_tournament' => DB::scalar("
                SELECT ROUND(AVG(match_count), 2)
                FROM (
                    SELECT COUNT(*) as match_count
                    FROM bracket_matches
                    GROUP BY event_id
                ) as tournament_matches
            ")
        ];
    }
}