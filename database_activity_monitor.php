<?php
/**
 * Database Activity Monitor for Team/Player CRUD Testing
 * Monitors database operations, constraint violations, and performance during testing
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class DatabaseActivityMonitor 
{
    private $dbConnection;
    private $logFile;
    private $startTime;
    private $queryLog = [];
    private $constraintViolations = [];
    private $performanceMetrics = [];
    private $testSessionId;

    public function __construct($logFileName = null)
    {
        $this->testSessionId = 'test_' . date('Y_m_d_H_i_s') . '_' . uniqid();
        $this->logFile = $logFileName ?: __DIR__ . "/test_monitoring_{$this->testSessionId}.log";
        $this->startTime = microtime(true);
        
        $this->initializeDatabase();
        $this->setupQueryLogging();
        $this->log("=== Database Activity Monitor Started ===");
        $this->log("Session ID: {$this->testSessionId}");
        $this->log("Timestamp: " . date('Y-m-d H:i:s'));
    }

    private function initializeDatabase()
    {
        // Load Laravel configuration
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        
        $this->dbConnection = DB::connection();
        $this->log("Database connection established: " . config('database.default'));
    }

    private function setupQueryLogging()
    {
        DB::listen(function ($query) {
            $this->logQuery($query);
            $this->analyzeQueryPerformance($query);
        });
    }

    public function startMonitoring()
    {
        $this->log("Starting comprehensive database monitoring...");
        
        // Get initial database state
        $this->captureInitialState();
        
        // Enable performance monitoring
        $this->enablePerformanceMonitoring();
        
        $this->log("Monitoring active. Waiting for CRUD operations...");
        
        return $this;
    }

    private function captureInitialState()
    {
        $this->log("=== Initial Database State ===");
        
        try {
            // Count existing records
            $teamCount = DB::table('teams')->count();
            $playerCount = DB::table('players')->count();
            $mentionCount = DB::table('mentions')->count();
            $historyCount = DB::table('player_team_histories')->count();
            
            $this->log("Teams: {$teamCount}");
            $this->log("Players: {$playerCount}");
            $this->log("Mentions: {$mentionCount}");
            $this->log("Team Histories: {$historyCount}");
            
            // Check foreign key constraints
            $this->validateForeignKeyConstraints();
            
            // Check for orphaned records
            $this->checkForOrphanedRecords();
            
        } catch (Exception $e) {
            $this->log("ERROR capturing initial state: " . $e->getMessage(), 'ERROR');
        }
    }

    private function enablePerformanceMonitoring()
    {
        $this->log("=== Performance Monitoring Enabled ===");
        
        // Monitor slow queries (>100ms)
        $this->performanceMetrics['slow_query_threshold'] = 100;
        $this->performanceMetrics['queries_executed'] = 0;
        $this->performanceMetrics['slow_queries'] = 0;
        $this->performanceMetrics['total_execution_time'] = 0;
    }

    public function monitorTeamOperation($operation, $teamData, $teamId = null)
    {
        $this->log("=== TEAM OPERATION: {$operation} ===");
        $startTime = microtime(true);
        
        try {
            switch ($operation) {
                case 'CREATE':
                    $result = $this->monitorTeamCreate($teamData);
                    break;
                case 'UPDATE':
                    $result = $this->monitorTeamUpdate($teamId, $teamData);
                    break;
                case 'DELETE':
                    $result = $this->monitorTeamDelete($teamId);
                    break;
                case 'READ':
                    $result = $this->monitorTeamRead($teamId);
                    break;
                default:
                    throw new InvalidArgumentException("Unknown operation: {$operation}");
            }
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            $this->log("Operation completed in {$executionTime}ms");
            
            // Validate referential integrity after operation
            $this->validateReferentialIntegrity();
            
            return $result;
            
        } catch (QueryException $e) {
            $this->handleConstraintViolation($operation, $e);
            throw $e;
        } catch (Exception $e) {
            $this->log("ERROR in {$operation}: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    public function monitorPlayerOperation($operation, $playerData, $playerId = null)
    {
        $this->log("=== PLAYER OPERATION: {$operation} ===");
        $startTime = microtime(true);
        
        try {
            switch ($operation) {
                case 'CREATE':
                    $result = $this->monitorPlayerCreate($playerData);
                    break;
                case 'UPDATE':
                    $result = $this->monitorPlayerUpdate($playerId, $playerData);
                    break;
                case 'DELETE':
                    $result = $this->monitorPlayerDelete($playerId);
                    break;
                case 'READ':
                    $result = $this->monitorPlayerRead($playerId);
                    break;
                default:
                    throw new InvalidArgumentException("Unknown operation: {$operation}");
            }
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            $this->log("Operation completed in {$executionTime}ms");
            
            // Validate referential integrity after operation
            $this->validateReferentialIntegrity();
            
            return $result;
            
        } catch (QueryException $e) {
            $this->handleConstraintViolation($operation, $e);
            throw $e;
        } catch (Exception $e) {
            $this->log("ERROR in {$operation}: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    private function monitorTeamCreate($teamData)
    {
        $this->log("Creating team with data: " . json_encode($teamData, JSON_PRETTY_PRINT));
        
        // Validate required fields
        $requiredFields = ['name'];
        foreach ($requiredFields as $field) {
            if (!isset($teamData[$field]) || empty($teamData[$field])) {
                throw new InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
        
        // Check for duplicate team names
        $existingTeam = DB::table('teams')->where('name', $teamData['name'])->first();
        if ($existingTeam) {
            $this->log("WARNING: Team with name '{$teamData['name']}' already exists (ID: {$existingTeam->id})", 'WARNING');
        }
        
        $teamId = DB::table('teams')->insertGetId($teamData);
        $this->log("Team created with ID: {$teamId}");
        
        return $teamId;
    }

    private function monitorTeamUpdate($teamId, $teamData)
    {
        $this->log("Updating team ID {$teamId} with data: " . json_encode($teamData, JSON_PRETTY_PRINT));
        
        // Check if team exists
        $existingTeam = DB::table('teams')->where('id', $teamId)->first();
        if (!$existingTeam) {
            throw new InvalidArgumentException("Team with ID {$teamId} does not exist");
        }
        
        $affected = DB::table('teams')->where('id', $teamId)->update($teamData);
        $this->log("Team update affected {$affected} rows");
        
        return $affected;
    }

    private function monitorTeamDelete($teamId)
    {
        $this->log("Deleting team ID {$teamId}");
        
        // Check for dependent players
        $dependentPlayers = DB::table('players')->where('team_id', $teamId)->get();
        if ($dependentPlayers->count() > 0) {
            $this->log("WARNING: Team has {$dependentPlayers->count()} dependent players", 'WARNING');
            foreach ($dependentPlayers as $player) {
                $this->log("  - Player: {$player->name} (ID: {$player->id})");
            }
        }
        
        // Check for mentions
        $mentions = DB::table('mentions')
            ->where('mentioned_type', 'App\\Models\\Team')
            ->where('mentioned_id', $teamId)
            ->get();
        if ($mentions->count() > 0) {
            $this->log("WARNING: Team has {$mentions->count()} mentions", 'WARNING');
        }
        
        $affected = DB::table('teams')->where('id', $teamId)->delete();
        $this->log("Team deletion affected {$affected} rows");
        
        return $affected;
    }

    private function monitorTeamRead($teamId)
    {
        $this->log("Reading team ID {$teamId}");
        
        $team = DB::table('teams')->where('id', $teamId)->first();
        if (!$team) {
            $this->log("WARNING: Team with ID {$teamId} not found", 'WARNING');
            return null;
        }
        
        $this->log("Team found: {$team->name}");
        return $team;
    }

    private function monitorPlayerCreate($playerData)
    {
        $this->log("Creating player with data: " . json_encode($playerData, JSON_PRETTY_PRINT));
        
        // Validate required fields
        $requiredFields = ['name'];
        foreach ($requiredFields as $field) {
            if (!isset($playerData[$field]) || empty($playerData[$field])) {
                throw new InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
        
        // Validate team_id if provided
        if (isset($playerData['team_id']) && $playerData['team_id']) {
            $team = DB::table('teams')->where('id', $playerData['team_id'])->first();
            if (!$team) {
                throw new InvalidArgumentException("Team with ID {$playerData['team_id']} does not exist");
            }
            $this->log("Player will be assigned to team: {$team->name}");
        }
        
        $playerId = DB::table('players')->insertGetId($playerData);
        $this->log("Player created with ID: {$playerId}");
        
        return $playerId;
    }

    private function monitorPlayerUpdate($playerId, $playerData)
    {
        $this->log("Updating player ID {$playerId} with data: " . json_encode($playerData, JSON_PRETTY_PRINT));
        
        // Check if player exists
        $existingPlayer = DB::table('players')->where('id', $playerId)->first();
        if (!$existingPlayer) {
            throw new InvalidArgumentException("Player with ID {$playerId} does not exist");
        }
        
        // Track team changes for history
        if (isset($playerData['team_id']) && $playerData['team_id'] != $existingPlayer->team_id) {
            $this->log("Team change detected: {$existingPlayer->team_id} -> {$playerData['team_id']}");
            
            // Validate new team exists
            if ($playerData['team_id']) {
                $newTeam = DB::table('teams')->where('id', $playerData['team_id'])->first();
                if (!$newTeam) {
                    throw new InvalidArgumentException("New team with ID {$playerData['team_id']} does not exist");
                }
                $this->log("Player moving to team: {$newTeam->name}");
            }
        }
        
        $affected = DB::table('players')->where('id', $playerId)->update($playerData);
        $this->log("Player update affected {$affected} rows");
        
        return $affected;
    }

    private function monitorPlayerDelete($playerId)
    {
        $this->log("Deleting player ID {$playerId}");
        
        // Check for mentions
        $mentions = DB::table('mentions')
            ->where('mentioned_type', 'App\\Models\\Player')
            ->where('mentioned_id', $playerId)
            ->get();
        if ($mentions->count() > 0) {
            $this->log("WARNING: Player has {$mentions->count()} mentions", 'WARNING');
        }
        
        // Check for match stats
        $matchStats = DB::table('match_player_stats')->where('player_id', $playerId)->count();
        if ($matchStats > 0) {
            $this->log("WARNING: Player has {$matchStats} match statistics records", 'WARNING');
        }
        
        $affected = DB::table('players')->where('id', $playerId)->delete();
        $this->log("Player deletion affected {$affected} rows");
        
        return $affected;
    }

    private function monitorPlayerRead($playerId)
    {
        $this->log("Reading player ID {$playerId}");
        
        $player = DB::table('players')->where('id', $playerId)->first();
        if (!$player) {
            $this->log("WARNING: Player with ID {$playerId} not found", 'WARNING');
            return null;
        }
        
        $this->log("Player found: {$player->name}");
        return $player;
    }

    private function validateForeignKeyConstraints()
    {
        $this->log("=== Validating Foreign Key Constraints ===");
        
        try {
            // Check players without valid teams
            $orphanedPlayers = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->whereNotNull('p.team_id')
                ->whereNull('t.id')
                ->select('p.id', 'p.name', 'p.team_id')
                ->get();
            
            if ($orphanedPlayers->count() > 0) {
                $this->log("CONSTRAINT VIOLATION: Found {$orphanedPlayers->count()} players with invalid team_id", 'ERROR');
                foreach ($orphanedPlayers as $player) {
                    $this->log("  - Player: {$player->name} (ID: {$player->id}) -> invalid team_id: {$player->team_id}");
                }
            } else {
                $this->log("✓ All player-team relationships are valid");
            }
            
            // Check mentions with invalid referenced entities
            $orphanedMentions = DB::table('mentions as m')
                ->leftJoin('teams as t', function($join) {
                    $join->on('m.mentioned_id', '=', 't.id')
                         ->where('m.mentioned_type', '=', 'App\\Models\\Team');
                })
                ->leftJoin('players as p', function($join) {
                    $join->on('m.mentioned_id', '=', 'p.id')
                         ->where('m.mentioned_type', '=', 'App\\Models\\Player');
                })
                ->whereNull('t.id')
                ->whereNull('p.id')
                ->select('m.id', 'm.mentioned_type', 'm.mentioned_id')
                ->get();
            
            if ($orphanedMentions->count() > 0) {
                $this->log("CONSTRAINT VIOLATION: Found {$orphanedMentions->count()} mentions with invalid references", 'ERROR');
                foreach ($orphanedMentions as $mention) {
                    $this->log("  - Mention ID: {$mention->id} -> {$mention->mentioned_type}:{$mention->mentioned_id}");
                }
            } else {
                $this->log("✓ All mention relationships are valid");
            }
            
        } catch (Exception $e) {
            $this->log("ERROR validating constraints: " . $e->getMessage(), 'ERROR');
        }
    }

    private function checkForOrphanedRecords()
    {
        $this->log("=== Checking for Orphaned Records ===");
        
        try {
            // Check for team history records with invalid references
            $orphanedHistory = DB::table('player_team_histories as h')
                ->leftJoin('players as p', 'h.player_id', '=', 'p.id')
                ->leftJoin('teams as ft', 'h.from_team_id', '=', 'ft.id')
                ->leftJoin('teams as tt', 'h.to_team_id', '=', 'tt.id')
                ->where(function($query) {
                    $query->whereNull('p.id')
                          ->orWhere(function($q) {
                              $q->whereNotNull('h.from_team_id')->whereNull('ft.id');
                          })
                          ->orWhere(function($q) {
                              $q->whereNotNull('h.to_team_id')->whereNull('tt.id');
                          });
                })
                ->select('h.id', 'h.player_id', 'h.from_team_id', 'h.to_team_id')
                ->get();
            
            if ($orphanedHistory->count() > 0) {
                $this->log("ORPHANED RECORDS: Found {$orphanedHistory->count()} invalid team history records", 'WARNING');
                foreach ($orphanedHistory as $history) {
                    $this->log("  - History ID: {$history->id} -> Player:{$history->player_id}, From:{$history->from_team_id}, To:{$history->to_team_id}");
                }
            } else {
                $this->log("✓ No orphaned team history records found");
            }
            
        } catch (Exception $e) {
            $this->log("ERROR checking orphaned records: " . $e->getMessage(), 'ERROR');
        }
    }

    private function validateReferentialIntegrity()
    {
        $this->validateForeignKeyConstraints();
        $this->checkForOrphanedRecords();
    }

    private function logQuery($query)
    {
        $this->queryLog[] = [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
            'timestamp' => microtime(true)
        ];
        
        $this->performanceMetrics['queries_executed']++;
        $this->performanceMetrics['total_execution_time'] += $query->time;
        
        // Log slow queries
        if ($query->time > $this->performanceMetrics['slow_query_threshold']) {
            $this->performanceMetrics['slow_queries']++;
            $this->log("SLOW QUERY ({$query->time}ms): {$query->sql}", 'WARNING');
        }
    }

    private function analyzeQueryPerformance($query)
    {
        // Analyze common performance issues
        $sql = strtolower($query->sql);
        
        // Check for potential issues
        if (strpos($sql, 'select *') !== false) {
            $this->log("PERFORMANCE WARNING: SELECT * query detected", 'WARNING');
        }
        
        if (strpos($sql, 'where') === false && strpos($sql, 'limit') === false && strpos($sql, 'select') !== false) {
            $this->log("PERFORMANCE WARNING: SELECT query without WHERE clause or LIMIT", 'WARNING');
        }
        
        if (strpos($sql, 'n+1') !== false) {
            $this->log("PERFORMANCE WARNING: Potential N+1 query detected", 'WARNING');
        }
    }

    private function handleConstraintViolation($operation, $exception)
    {
        $violation = [
            'operation' => $operation,
            'error' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->constraintViolations[] = $violation;
        $this->log("CONSTRAINT VIOLATION in {$operation}: " . $exception->getMessage(), 'ERROR');
    }

    public function generatePerformanceReport()
    {
        $this->log("=== PERFORMANCE REPORT ===");
        
        $totalTime = microtime(true) - $this->startTime;
        $avgQueryTime = $this->performanceMetrics['queries_executed'] > 0 
            ? $this->performanceMetrics['total_execution_time'] / $this->performanceMetrics['queries_executed']
            : 0;
        
        $report = [
            'session_id' => $this->testSessionId,
            'total_monitoring_time' => round($totalTime, 2),
            'queries_executed' => $this->performanceMetrics['queries_executed'],
            'slow_queries' => $this->performanceMetrics['slow_queries'],
            'total_query_time' => round($this->performanceMetrics['total_execution_time'], 2),
            'average_query_time' => round($avgQueryTime, 2),
            'constraint_violations' => count($this->constraintViolations),
            'violations' => $this->constraintViolations
        ];
        
        $this->log("Total monitoring time: {$report['total_monitoring_time']}s");
        $this->log("Queries executed: {$report['queries_executed']}");
        $this->log("Slow queries: {$report['slow_queries']}");
        $this->log("Average query time: {$report['average_query_time']}ms");
        $this->log("Constraint violations: {$report['constraint_violations']}");
        
        // Save detailed report
        $reportFile = __DIR__ . "/performance_report_{$this->testSessionId}.json";
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        $this->log("Detailed report saved to: {$reportFile}");
        
        return $report;
    }

    public function getTestData()
    {
        $this->log("=== Gathering Test Data ===");
        
        $testData = [
            'teams' => DB::table('teams')
                ->where('name', 'like', 'TEST_%')
                ->orWhere('name', 'like', '%_TEST_%')
                ->orWhere('slug', 'like', 'test-%')
                ->get(),
            'players' => DB::table('players')
                ->where('name', 'like', 'TEST_%')
                ->orWhere('name', 'like', '%_TEST_%')
                ->orWhere('username', 'like', 'test_%')
                ->get(),
            'mentions' => DB::table('mentions')
                ->where('content', 'like', '%TEST%')
                ->get(),
            'histories' => DB::table('player_team_histories')
                ->whereIn('player_id', function($query) {
                    $query->select('id')->from('players')
                          ->where('name', 'like', 'TEST_%')
                          ->orWhere('name', 'like', '%_TEST_%');
                })
                ->get()
        ];
        
        $this->log("Found test data:");
        $this->log("- Teams: " . $testData['teams']->count());
        $this->log("- Players: " . $testData['players']->count());
        $this->log("- Mentions: " . $testData['mentions']->count());
        $this->log("- Histories: " . $testData['histories']->count());
        
        return $testData;
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
        return $this->testSessionId;
    }

    public function __destruct()
    {
        $this->log("=== Database Activity Monitor Stopped ===");
        $this->generatePerformanceReport();
    }
}

// CLI Usage
if (php_sapi_name() === 'cli') {
    echo "Database Activity Monitor\n";
    echo "Usage: php database_activity_monitor.php [action] [parameters]\n";
    echo "Actions:\n";
    echo "  start           - Start monitoring (interactive mode)\n";
    echo "  test-team       - Test team CRUD operations\n";
    echo "  test-player     - Test player CRUD operations\n";
    echo "  validate        - Validate database integrity\n";
    echo "  report          - Generate performance report\n\n";
    
    $action = $argv[1] ?? 'start';
    $monitor = new DatabaseActivityMonitor();
    
    switch ($action) {
        case 'start':
            $monitor->startMonitoring();
            echo "Monitor started. Press Ctrl+C to stop.\n";
            while (true) {
                sleep(1);
            }
            break;
            
        case 'test-team':
            $monitor->startMonitoring();
            
            // Test team operations
            $testTeam = [
                'name' => 'TEST_TEAM_' . uniqid(),
                'short_name' => 'TT' . substr(uniqid(), -3),
                'region' => 'Test Region',
                'country' => 'Test Country'
            ];
            
            $teamId = $monitor->monitorTeamOperation('CREATE', $testTeam);
            $monitor->monitorTeamOperation('READ', [], $teamId);
            $monitor->monitorTeamOperation('UPDATE', ['region' => 'Updated Region'], $teamId);
            $monitor->monitorTeamOperation('DELETE', [], $teamId);
            break;
            
        case 'test-player':
            $monitor->startMonitoring();
            
            // Test player operations
            $testPlayer = [
                'name' => 'TEST_PLAYER_' . uniqid(),
                'username' => 'test_user_' . uniqid(),
                'region' => 'Test Region',
                'country' => 'Test Country'
            ];
            
            $playerId = $monitor->monitorPlayerOperation('CREATE', $testPlayer);
            $monitor->monitorPlayerOperation('READ', [], $playerId);
            $monitor->monitorPlayerOperation('UPDATE', ['region' => 'Updated Region'], $playerId);
            $monitor->monitorPlayerOperation('DELETE', [], $playerId);
            break;
            
        case 'validate':
            $monitor->startMonitoring();
            break;
            
        case 'report':
            $monitor->generatePerformanceReport();
            break;
            
        default:
            echo "Unknown action: {$action}\n";
            exit(1);
    }
}