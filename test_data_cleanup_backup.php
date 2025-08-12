<?php
/**
 * Safe Test Data Cleanup Script
 * Safely removes test data while preserving referential integrity and production data
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

class TestDataCleaner 
{
    private $dbConnection;
    private $logFile;
    private $dryRun = true;
    private $sessionId;
    private $testPatterns = [
        'teams' => [
            'name' => ['TEST_%', '%_TEST_%', 'test_%', '%_test_%'],
            'slug' => ['test-%', '%test%'],
            'short_name' => ['TT%', 'TST%']
        ],
        'players' => [
            'name' => ['TEST_%', '%_TEST_%', 'test_%', '%_test_%'],
            'username' => ['test_%', '%_test_%', 'testuser%'],
            'real_name' => ['Test %', 'TEST %']
        ],
        'mentions' => [
            'content' => ['%TEST%', '%test%']
        ]
    ];
    private $cleanupStats = [
        'teams_identified' => 0,
        'players_identified' => 0,
        'mentions_identified' => 0,
        'histories_identified' => 0,
        'teams_deleted' => 0,
        'players_deleted' => 0,
        'mentions_deleted' => 0,
        'histories_deleted' => 0,
        'errors' => []
    ];

    public function __construct($dryRun = true)
    {
        $this->sessionId = 'cleanup_' . date('Y_m_d_H_i_s') . '_' . uniqid();
        $this->dryRun = $dryRun;
        $this->logFile = __DIR__ . "/test_cleanup_{$this->sessionId}.log";
        
        $this->initializeDatabase();
        $this->log("Test Data Cleanup initialized");
        $this->log("Mode: " . ($this->dryRun ? 'DRY RUN (no changes will be made)' : 'LIVE CLEANUP'));
    }

    private function initializeDatabase()
    {
        // Load Laravel configuration
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        
        $this->dbConnection = DB::connection();
        $this->log("Database connection established: " . config('database.default'));
    }

    public function identifyTestData()
    {
        $this->log("=== Identifying Test Data ===");
        
        $testData = [
            'teams' => $this->identifyTestTeams(),
            'players' => $this->identifyTestPlayers(),
            'mentions' => $this->identifyTestMentions(),
            'histories' => []
        ];
        
        // Identify related team histories
        if (!empty($testData['players'])) {
            $testData['histories'] = $this->identifyRelatedHistories($testData['players']);
        }
        
        $this->updateStats($testData);
        $this->logIdentificationSummary();
        
        return $testData;
    }

    private function identifyTestTeams()
    {
        $this->log("Identifying test teams...");
        $testTeams = collect();
        
        foreach ($this->testPatterns['teams'] as $column => $patterns) {
            foreach ($patterns as $pattern) {
                $teams = DB::table('teams')
                    ->where($column, 'like', $pattern)
                    ->get();
                
                $testTeams = $testTeams->merge($teams);
                
                if ($teams->count() > 0) {
                    $this->log("Found {$teams->count()} teams matching pattern '{$pattern}' in column '{$column}'");
                }
            }
        }
        
        // Remove duplicates by ID
        $testTeams = $testTeams->unique('id');
        
        $this->log("Total test teams identified: " . $testTeams->count());
        foreach ($testTeams as $team) {
            $this->log("  - ID: {$team->id}, Name: '{$team->name}', Slug: '{$team->slug}'");
        }
        
        return $testTeams->toArray();
    }

    private function identifyTestPlayers()
    {
        $this->log("Identifying test players...");
        $testPlayers = collect();
        
        foreach ($this->testPatterns['players'] as $column => $patterns) {
            foreach ($patterns as $pattern) {
                $players = DB::table('players')
                    ->where($column, 'like', $pattern)
                    ->get();
                
                $testPlayers = $testPlayers->merge($players);
                
                if ($players->count() > 0) {
                    $this->log("Found {$players->count()} players matching pattern '{$pattern}' in column '{$column}'");
                }
            }
        }
        
        // Remove duplicates by ID
        $testPlayers = $testPlayers->unique('id');
        
        $this->log("Total test players identified: " . $testPlayers->count());
        foreach ($testPlayers as $player) {
            $teamInfo = $player->team_id ? " (Team ID: {$player->team_id})" : " (No team)";
            $this->log("  - ID: {$player->id}, Name: '{$player->name}'{$teamInfo}");
        }
        
        return $testPlayers->toArray();
    }

    private function identifyTestMentions()
    {
        $this->log("Identifying test mentions...");
        $testMentions = collect();
        
        foreach ($this->testPatterns['mentions'] as $column => $patterns) {
            foreach ($patterns as $pattern) {
                $mentions = DB::table('mentions')
                    ->where($column, 'like', $pattern)
                    ->get();
                
                $testMentions = $testMentions->merge($mentions);
                
                if ($mentions->count() > 0) {
                    $this->log("Found {$mentions->count()} mentions matching pattern '{$pattern}' in column '{$column}'");
                }
            }
        }
        
        // Remove duplicates by ID
        $testMentions = $testMentions->unique('id');
        
        $this->log("Total test mentions identified: " . $testMentions->count());
        foreach ($testMentions as $mention) {
            $this->log("  - ID: {$mention->id}, Type: {$mention->mentioned_type}, Target ID: {$mention->mentioned_id}");
        }
        
        return $testMentions->toArray();
    }

    private function identifyRelatedHistories($testPlayers)
    {
        if (empty($testPlayers)) {
            return [];
        }
        
        $this->log("Identifying related team histories...");
        $playerIds = array_column($testPlayers, 'id');
        
        $histories = DB::table('player_team_histories')
            ->whereIn('player_id', $playerIds)
            ->get();
        
        $this->log("Found {$histories->count()} team history records for test players");
        foreach ($histories as $history) {
            $this->log("  - ID: {$history->id}, Player: {$history->player_id}, From: {$history->from_team_id}, To: {$history->to_team_id}");
        }
        
        return $histories->toArray();
    }

    private function updateStats($testData)
    {
        $this->cleanupStats['teams_identified'] = count($testData['teams']);
        $this->cleanupStats['players_identified'] = count($testData['players']);
        $this->cleanupStats['mentions_identified'] = count($testData['mentions']);
        $this->cleanupStats['histories_identified'] = count($testData['histories']);
    }

    private function logIdentificationSummary()
    {
        $this->log("=== Identification Summary ===");
        $this->log("Teams identified: {$this->cleanupStats['teams_identified']}");
        $this->log("Players identified: {$this->cleanupStats['players_identified']}");
        $this->log("Mentions identified: {$this->cleanupStats['mentions_identified']}");
        $this->log("Team histories identified: {$this->cleanupStats['histories_identified']}");
    }

    public function performCleanup($testData = null)
    {
        if ($testData === null) {
            $testData = $this->identifyTestData();
        }
        
        $this->log("=== Starting Cleanup Process ===");
        
        if ($this->dryRun) {
            $this->log("DRY RUN MODE - No actual deletions will be performed");
            $this->simulateCleanup($testData);
        } else {
            $this->executeCleanup($testData);
        }
        
        $this->generateCleanupReport();
    }

    private function simulateCleanup($testData)
    {
        $this->log("=== Simulating Cleanup ===");
        
        // Simulate deletion order (maintaining referential integrity)
        $this->log("Would delete in the following order:");
        
        // 1. Team histories
        if (!empty($testData['histories'])) {
            $this->log("1. Delete {count($testData['histories'])} team history records");
        }
        
        // 2. Mentions
        if (!empty($testData['mentions'])) {
            $this->log("2. Delete {count($testData['mentions'])} mention records");
        }
        
        // 3. Players (after checking for remaining dependencies)
        if (!empty($testData['players'])) {
            $dependencies = $this->checkPlayerDependencies($testData['players']);
            $this->log("3. Delete {count($testData['players'])} players");
            if (!empty($dependencies)) {
                $this->log("   WARNING: Players have dependencies that would be affected:");
                foreach ($dependencies as $dep) {
                    $this->log("     - {$dep}");
                }
            }
        }
        
        // 4. Teams (after checking for remaining dependencies)
        if (!empty($testData['teams'])) {
            $dependencies = $this->checkTeamDependencies($testData['teams']);
            $this->log("4. Delete {count($testData['teams'])} teams");
            if (!empty($dependencies)) {
                $this->log("   WARNING: Teams have dependencies that would be affected:");
                foreach ($dependencies as $dep) {
                    $this->log("     - {$dep}");
                }
            }
        }
    }

    private function executeCleanup($testData)
    {
        $this->log("=== Executing Cleanup ===");
        
        try {
            DB::beginTransaction();
            
            // Delete in order to maintain referential integrity
            
            // 1. Delete team histories
            if (!empty($testData['histories'])) {
                $this->deleteTeamHistories($testData['histories']);
            }
            
            // 2. Delete mentions
            if (!empty($testData['mentions'])) {
                $this->deleteMentions($testData['mentions']);
            }
            
            // 3. Delete players
            if (!empty($testData['players'])) {
                $this->deletePlayers($testData['players']);
            }
            
            // 4. Delete teams
            if (!empty($testData['teams'])) {
                $this->deleteTeams($testData['teams']);
            }
            
            DB::commit();
            $this->log("Cleanup completed successfully");
            
        } catch (Exception $e) {
            DB::rollBack();
            $error = "Cleanup failed: " . $e->getMessage();
            $this->log($error, 'ERROR');
            $this->cleanupStats['errors'][] = $error;
            throw $e;
        }
    }

    private function deleteTeamHistories($histories)
    {
        $this->log("Deleting team history records...");
        $historyIds = array_column($histories, 'id');
        
        $deleted = DB::table('player_team_histories')
            ->whereIn('id', $historyIds)
            ->delete();
        
        $this->cleanupStats['histories_deleted'] = $deleted;
        $this->log("Deleted {$deleted} team history records");
    }

    private function deleteMentions($mentions)
    {
        $this->log("Deleting mention records...");
        $mentionIds = array_column($mentions, 'id');
        
        $deleted = DB::table('mentions')
            ->whereIn('id', $mentionIds)
            ->delete();
        
        $this->cleanupStats['mentions_deleted'] = $deleted;
        $this->log("Deleted {$deleted} mention records");
    }

    private function deletePlayers($players)
    {
        $this->log("Deleting player records...");
        
        foreach ($players as $player) {
            try {
                // Check for any remaining dependencies
                $dependencies = $this->checkSinglePlayerDependencies($player['id']);
                
                if (!empty($dependencies)) {
                    $this->log("WARNING: Player {$player['id']} has remaining dependencies:", 'WARNING');
                    foreach ($dependencies as $dep) {
                        $this->log("  - {$dep}", 'WARNING');
                    }
                }
                
                // Delete the player
                $deleted = DB::table('players')->where('id', $player['id'])->delete();
                
                if ($deleted > 0) {
                    $this->cleanupStats['players_deleted']++;
                    $this->log("Deleted player: {$player['name']} (ID: {$player['id']})");
                } else {
                    $this->log("Failed to delete player: {$player['name']} (ID: {$player['id']})", 'WARNING');
                }
                
            } catch (QueryException $e) {
                $error = "Failed to delete player {$player['id']}: " . $e->getMessage();
                $this->log($error, 'ERROR');
                $this->cleanupStats['errors'][] = $error;
            }
        }
    }

    private function deleteTeams($teams)
    {
        $this->log("Deleting team records...");
        
        foreach ($teams as $team) {
            try {
                // Check for any remaining dependencies
                $dependencies = $this->checkSingleTeamDependencies($team['id']);
                
                if (!empty($dependencies)) {
                    $this->log("WARNING: Team {$team['id']} has remaining dependencies:", 'WARNING');
                    foreach ($dependencies as $dep) {
                        $this->log("  - {$dep}", 'WARNING');
                    }
                }
                
                // Delete the team
                $deleted = DB::table('teams')->where('id', $team['id'])->delete();
                
                if ($deleted > 0) {
                    $this->cleanupStats['teams_deleted']++;
                    $this->log("Deleted team: {$team['name']} (ID: {$team['id']})");
                } else {
                    $this->log("Failed to delete team: {$team['name']} (ID: {$team['id']})", 'WARNING');
                }
                
            } catch (QueryException $e) {
                $error = "Failed to delete team {$team['id']}: " . $e->getMessage();
                $this->log($error, 'ERROR');
                $this->cleanupStats['errors'][] = $error;
            }
        }
    }

    private function checkPlayerDependencies($players)
    {
        $dependencies = [];
        $playerIds = array_column($players, 'id');
        
        // Check match stats
        $matchStats = DB::table('match_player_stats')
            ->whereIn('player_id', $playerIds)
            ->count();
        
        if ($matchStats > 0) {
            $dependencies[] = "{$matchStats} match statistics records";
        }
        
        return $dependencies;
    }

    private function checkTeamDependencies($teams)
    {
        $dependencies = [];
        $teamIds = array_column($teams, 'id');
        
        // Check for non-test players
        $nonTestPlayers = DB::table('players')
            ->whereIn('team_id', $teamIds)
            ->where(function($query) {
                foreach ($this->testPatterns['players'] as $column => $patterns) {
                    foreach ($patterns as $pattern) {
                        $query->where($column, 'not like', $pattern);
                    }
                }
            })
            ->count();
        
        if ($nonTestPlayers > 0) {
            $dependencies[] = "{$nonTestPlayers} non-test players";
        }
        
        // Check matches
        $matches = DB::table('matches')
            ->where(function($query) use ($teamIds) {
                $query->whereIn('team1_id', $teamIds)
                      ->orWhereIn('team2_id', $teamIds);
            })
            ->count();
        
        if ($matches > 0) {
            $dependencies[] = "{$matches} match records";
        }
        
        return $dependencies;
    }

    private function checkSinglePlayerDependencies($playerId)
    {
        $dependencies = [];
        
        // Check match stats
        $matchStats = DB::table('match_player_stats')
            ->where('player_id', $playerId)
            ->count();
        
        if ($matchStats > 0) {
            $dependencies[] = "{$matchStats} match statistics";
        }
        
        return $dependencies;
    }

    private function checkSingleTeamDependencies($teamId)
    {
        $dependencies = [];
        
        // Check for non-test players
        $nonTestPlayers = DB::table('players')
            ->where('team_id', $teamId)
            ->where(function($query) {
                foreach ($this->testPatterns['players'] as $column => $patterns) {
                    foreach ($patterns as $pattern) {
                        $query->where($column, 'not like', $pattern);
                    }
                }
            })
            ->count();
        
        if ($nonTestPlayers > 0) {
            $dependencies[] = "{$nonTestPlayers} non-test players";
        }
        
        return $dependencies;
    }

    public function validateCleanup()
    {
        $this->log("=== Validating Cleanup ===");
        
        // Re-run identification to see what's left
        $remainingData = $this->identifyTestData();
        
        $issues = [];
        
        if (count($remainingData['teams']) > 0) {
            $issues[] = count($remainingData['teams']) . " test teams remain";
        }
        
        if (count($remainingData['players']) > 0) {
            $issues[] = count($remainingData['players']) . " test players remain";
        }
        
        if (count($remainingData['mentions']) > 0) {
            $issues[] = count($remainingData['mentions']) . " test mentions remain";
        }
        
        if (count($remainingData['histories']) > 0) {
            $issues[] = count($remainingData['histories']) . " test histories remain";
        }
        
        if (empty($issues)) {
            $this->log("✓ Cleanup validation successful - no test data remains");
        } else {
            $this->log("⚠ Cleanup validation issues found:");
            foreach ($issues as $issue) {
                $this->log("  - {$issue}");
            }
        }
        
        // Check referential integrity
        $this->validateReferentialIntegrity();
        
        return empty($issues);
    }

    private function validateReferentialIntegrity()
    {
        $this->log("Validating referential integrity...");
        
        try {
            // Check for orphaned players
            $orphanedPlayers = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->whereNotNull('p.team_id')
                ->whereNull('t.id')
                ->count();
            
            if ($orphanedPlayers > 0) {
                $this->log("⚠ Found {$orphanedPlayers} orphaned players", 'WARNING');
            } else {
                $this->log("✓ No orphaned players found");
            }
            
            // Check for orphaned mentions
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
                ->count();
            
            if ($orphanedMentions > 0) {
                $this->log("⚠ Found {$orphanedMentions} orphaned mentions", 'WARNING');
            } else {
                $this->log("✓ No orphaned mentions found");
            }
            
        } catch (Exception $e) {
            $this->log("ERROR validating referential integrity: " . $e->getMessage(), 'ERROR');
        }
    }

    private function generateCleanupReport()
    {
        $this->log("=== Cleanup Report ===");
        
        $report = [
            'session_id' => $this->sessionId,
            'timestamp' => date('Y-m-d H:i:s'),
            'mode' => $this->dryRun ? 'dry_run' : 'live_cleanup',
            'statistics' => $this->cleanupStats
        ];
        
        // Log summary
        $this->log("Cleanup Statistics:");
        $this->log("  Teams identified: {$this->cleanupStats['teams_identified']}");
        $this->log("  Teams deleted: {$this->cleanupStats['teams_deleted']}");
        $this->log("  Players identified: {$this->cleanupStats['players_identified']}");
        $this->log("  Players deleted: {$this->cleanupStats['players_deleted']}");
        $this->log("  Mentions identified: {$this->cleanupStats['mentions_identified']}");
        $this->log("  Mentions deleted: {$this->cleanupStats['mentions_deleted']}");
        $this->log("  Histories identified: {$this->cleanupStats['histories_identified']}");
        $this->log("  Histories deleted: {$this->cleanupStats['histories_deleted']}");
        $this->log("  Errors: " . count($this->cleanupStats['errors']));
        
        if (!empty($this->cleanupStats['errors'])) {
            $this->log("Errors encountered:");
            foreach ($this->cleanupStats['errors'] as $error) {
                $this->log("  - {$error}");
            }
        }
        
        // Save report
        $reportFile = __DIR__ . "/cleanup_report_{$this->sessionId}.json";
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->log("Cleanup report saved: {$reportFile}");
        
        return $report;
    }

    public function setDryRun($dryRun)
    {
        $this->dryRun = $dryRun;
        $this->log("Mode changed to: " . ($this->dryRun ? 'DRY RUN' : 'LIVE CLEANUP'));
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

    public function getCleanupStats()
    {
        return $this->cleanupStats;
    }
}

// CLI Usage
if (php_sapi_name() === 'cli') {
    echo "Test Data Cleanup Script\n";
    echo "Usage: php test_data_cleanup.php [action] [--live]\n";
    echo "Actions:\n";
    echo "  identify    - Identify test data (default)\n";
    echo "  cleanup     - Clean up test data\n";
    echo "  validate    - Validate cleanup results\n";
    echo "Options:\n";
    echo "  --live      - Execute actual cleanup (default is dry run)\n\n";
    
    $action = $argv[1] ?? 'identify';
    $live = in_array('--live', $argv);
    
    $cleaner = new TestDataCleaner(!$live);
    
    switch ($action) {
        case 'identify':
            $cleaner->identifyTestData();
            break;
            
        case 'cleanup':
            if (!$live) {
                echo "Running in DRY RUN mode. Use --live to execute actual cleanup.\n";
            } else {
                echo "WARNING: This will permanently delete test data. Continue? (y/N): ";
                $confirm = trim(fgets(STDIN));
                if (strtolower($confirm) !== 'y') {
                    echo "Cleanup cancelled.\n";
                    exit(0);
                }
            }
            $cleaner->performCleanup();
            break;
            
        case 'validate':
            $cleaner->validateCleanup();
            break;
            
        default:
            echo "Unknown action: {$action}\n";
            exit(1);
    }
    
    echo "\nCheck the log file for detailed results: " . $cleaner->getLogFile() . "\n";
}