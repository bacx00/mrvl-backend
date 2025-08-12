<?php
/**
 * Database Integrity Validator
 * Comprehensive validation of database integrity, constraints, and consistency
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Facades\Schema;

class DatabaseIntegrityValidator 
{
    private $dbConnection;
    private $logFile;
    private $sessionId;
    private $validationResults = [
        'foreign_key_violations' => [],
        'orphaned_records' => [],
        'data_inconsistencies' => [],
        'constraint_violations' => [],
        'schema_issues' => [],
        'performance_issues' => []
    ];
    private $validationRules = [
        'teams' => [
            'required_fields' => ['name'],
            'unique_fields' => ['name'],
            'valid_values' => []
        ],
        'players' => [
            'required_fields' => ['name'],
            'foreign_keys' => ['team_id' => 'teams.id'],
            'valid_values' => []
        ],
        'mentions' => [
            'required_fields' => ['mentioned_type', 'mentioned_id'],
            'polymorphic_relations' => [
                'mentioned_type' => [
                    'App\\Models\\Team' => 'teams.id',
                    'App\\Models\\Player' => 'players.id'
                ]
            ]
        ],
        'player_team_histories' => [
            'required_fields' => ['player_id'],
            'foreign_keys' => [
                'player_id' => 'players.id',
                'from_team_id' => 'teams.id',
                'to_team_id' => 'teams.id'
            ]
        ]
    ];

    public function __construct()
    {
        $this->sessionId = 'validation_' . date('Y_m_d_H_i_s') . '_' . uniqid();
        $this->logFile = __DIR__ . "/integrity_validation_{$this->sessionId}.log";
        
        $this->initializeDatabase();
        $this->log("Database Integrity Validator initialized");
    }

    private function initializeDatabase()
    {
        // Load Laravel configuration
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        
        $this->dbConnection = DB::connection();
        $this->log("Database connection established: " . config('database.default'));
    }

    public function runFullValidation()
    {
        $this->log("=== Starting Full Database Integrity Validation ===");
        
        // Schema validation
        $this->validateSchema();
        
        // Foreign key validation
        $this->validateForeignKeys();
        
        // Orphaned records validation
        $this->validateOrphanedRecords();
        
        // Data consistency validation
        $this->validateDataConsistency();
        
        // Constraint validation
        $this->validateConstraints();
        
        // Performance validation
        $this->validatePerformanceIssues();
        
        // Generate final report
        return $this->generateValidationReport();
    }

    private function validateSchema()
    {
        $this->log("=== Schema Validation ===");
        
        $expectedTables = ['teams', 'players', 'mentions', 'player_team_histories', 'match_player_stats'];
        
        foreach ($expectedTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->addSchemaIssue("Missing table: {$table}", 'CRITICAL');
            } else {
                $this->log("✓ Table exists: {$table}");
                $this->validateTableSchema($table);
            }
        }
    }

    private function validateTableSchema($tableName)
    {
        try {
            $columns = Schema::getColumnListing($tableName);
            
            if (isset($this->validationRules[$tableName]['required_fields'])) {
                foreach ($this->validationRules[$tableName]['required_fields'] as $requiredField) {
                    if (!in_array($requiredField, $columns)) {
                        $this->addSchemaIssue("Missing required column '{$requiredField}' in table '{$tableName}'", 'HIGH');
                    }
                }
            }
            
            // Check for common indexing columns
            $indexCandidates = ['id', 'created_at', 'updated_at'];
            foreach ($indexCandidates as $candidate) {
                if (in_array($candidate, $columns)) {
                    $this->log("  Index candidate found: {$tableName}.{$candidate}");
                }
            }
            
        } catch (Exception $e) {
            $this->addSchemaIssue("Error validating schema for table '{$tableName}': " . $e->getMessage(), 'CRITICAL');
        }
    }

    private function validateForeignKeys()
    {
        $this->log("=== Foreign Key Validation ===");
        
        foreach ($this->validationRules as $tableName => $rules) {
            if (isset($rules['foreign_keys'])) {
                $this->validateTableForeignKeys($tableName, $rules['foreign_keys']);
            }
            
            if (isset($rules['polymorphic_relations'])) {
                $this->validatePolymorphicRelations($tableName, $rules['polymorphic_relations']);
            }
        }
    }

    private function validateTableForeignKeys($tableName, $foreignKeys)
    {
        $this->log("Validating foreign keys for table: {$tableName}");
        
        if (!Schema::hasTable($tableName)) {
            return;
        }
        
        foreach ($foreignKeys as $foreignKeyColumn => $referenceTable) {
            list($refTable, $refColumn) = explode('.', $referenceTable);
            
            $violations = DB::table("{$tableName} as t")
                ->leftJoin("{$refTable} as r", "t.{$foreignKeyColumn}", '=', "r.{$refColumn}")
                ->whereNotNull("t.{$foreignKeyColumn}")
                ->whereNull("r.{$refColumn}")
                ->select("t.id", "t.{$foreignKeyColumn}")
                ->get();
            
            if ($violations->count() > 0) {
                $this->log("Foreign key violations found: {$tableName}.{$foreignKeyColumn} -> {$referenceTable}");
                foreach ($violations as $violation) {
                    $this->addForeignKeyViolation(
                        $tableName,
                        $violation->id,
                        $foreignKeyColumn,
                        $violation->$foreignKeyColumn,
                        $referenceTable
                    );
                }
            } else {
                $this->log("✓ No violations: {$tableName}.{$foreignKeyColumn} -> {$referenceTable}");
            }
        }
    }

    private function validatePolymorphicRelations($tableName, $polymorphicRelations)
    {
        $this->log("Validating polymorphic relations for table: {$tableName}");
        
        if (!Schema::hasTable($tableName)) {
            return;
        }
        
        foreach ($polymorphicRelations as $typeColumn => $relations) {
            foreach ($relations as $modelType => $referenceTable) {
                list($refTable, $refColumn) = explode('.', $referenceTable);
                
                $violations = DB::table("{$tableName} as t")
                    ->leftJoin("{$refTable} as r", 't.mentioned_id', '=', "r.{$refColumn}")
                    ->where("t.{$typeColumn}", $modelType)
                    ->whereNull("r.{$refColumn}")
                    ->select('t.id', 't.mentioned_id', "t.{$typeColumn}")
                    ->get();
                
                if ($violations->count() > 0) {
                    $this->log("Polymorphic relation violations found: {$tableName} -> {$modelType}");
                    foreach ($violations as $violation) {
                        $this->addForeignKeyViolation(
                            $tableName,
                            $violation->id,
                            'mentioned_id',
                            $violation->mentioned_id,
                            "{$modelType} -> {$referenceTable}"
                        );
                    }
                } else {
                    $this->log("✓ No violations: {$tableName} -> {$modelType}");
                }
            }
        }
    }

    private function validateOrphanedRecords()
    {
        $this->log("=== Orphaned Records Validation ===");
        
        // Check for players without teams (where team_id is not null but team doesn't exist)
        $this->checkOrphanedPlayers();
        
        // Check for team histories without valid players or teams
        $this->checkOrphanedTeamHistories();
        
        // Check for match stats without valid players
        $this->checkOrphanedMatchStats();
        
        // Check for mentions without valid referenced entities
        $this->checkOrphanedMentions();
    }

    private function checkOrphanedPlayers()
    {
        if (!Schema::hasTable('players') || !Schema::hasTable('teams')) {
            return;
        }
        
        $orphaned = DB::table('players as p')
            ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
            ->whereNotNull('p.team_id')
            ->whereNull('t.id')
            ->select('p.id', 'p.name', 'p.team_id')
            ->get();
        
        if ($orphaned->count() > 0) {
            $this->log("Found {$orphaned->count()} orphaned players");
            foreach ($orphaned as $player) {
                $this->addOrphanedRecord('players', $player->id, "Player '{$player->name}' references non-existent team {$player->team_id}");
            }
        } else {
            $this->log("✓ No orphaned players found");
        }
    }

    private function checkOrphanedTeamHistories()
    {
        if (!Schema::hasTable('player_team_histories')) {
            return;
        }
        
        // Check for histories with invalid player references
        $orphanedByPlayer = DB::table('player_team_histories as h')
            ->leftJoin('players as p', 'h.player_id', '=', 'p.id')
            ->whereNull('p.id')
            ->select('h.id', 'h.player_id')
            ->get();
        
        foreach ($orphanedByPlayer as $history) {
            $this->addOrphanedRecord('player_team_histories', $history->id, "History references non-existent player {$history->player_id}");
        }
        
        // Check for histories with invalid team references
        if (Schema::hasTable('teams')) {
            $orphanedByTeam = DB::table('player_team_histories as h')
                ->leftJoin('teams as ft', 'h.from_team_id', '=', 'ft.id')
                ->leftJoin('teams as tt', 'h.to_team_id', '=', 'tt.id')
                ->where(function($query) {
                    $query->where(function($q) {
                        $q->whereNotNull('h.from_team_id')->whereNull('ft.id');
                    })->orWhere(function($q) {
                        $q->whereNotNull('h.to_team_id')->whereNull('tt.id');
                    });
                })
                ->select('h.id', 'h.from_team_id', 'h.to_team_id')
                ->get();
            
            foreach ($orphanedByTeam as $history) {
                $this->addOrphanedRecord('player_team_histories', $history->id, "History references non-existent team(s)");
            }
        }
        
        $totalOrphaned = $orphanedByPlayer->count() + ($orphanedByTeam ?? collect())->count();
        if ($totalOrphaned == 0) {
            $this->log("✓ No orphaned team histories found");
        } else {
            $this->log("Found {$totalOrphaned} orphaned team history records");
        }
    }

    private function checkOrphanedMatchStats()
    {
        if (!Schema::hasTable('match_player_stats') || !Schema::hasTable('players')) {
            return;
        }
        
        $orphaned = DB::table('match_player_stats as s')
            ->leftJoin('players as p', 's.player_id', '=', 'p.id')
            ->whereNull('p.id')
            ->select('s.id', 's.player_id')
            ->get();
        
        if ($orphaned->count() > 0) {
            $this->log("Found {$orphaned->count()} orphaned match statistics");
            foreach ($orphaned as $stat) {
                $this->addOrphanedRecord('match_player_stats', $stat->id, "Match stat references non-existent player {$stat->player_id}");
            }
        } else {
            $this->log("✓ No orphaned match statistics found");
        }
    }

    private function checkOrphanedMentions()
    {
        if (!Schema::hasTable('mentions')) {
            return;
        }
        
        // Check team mentions
        if (Schema::hasTable('teams')) {
            $orphanedTeamMentions = DB::table('mentions as m')
                ->leftJoin('teams as t', 'm.mentioned_id', '=', 't.id')
                ->where('m.mentioned_type', 'App\\Models\\Team')
                ->whereNull('t.id')
                ->select('m.id', 'm.mentioned_id')
                ->get();
            
            foreach ($orphanedTeamMentions as $mention) {
                $this->addOrphanedRecord('mentions', $mention->id, "Mention references non-existent team {$mention->mentioned_id}");
            }
        }
        
        // Check player mentions
        if (Schema::hasTable('players')) {
            $orphanedPlayerMentions = DB::table('mentions as m')
                ->leftJoin('players as p', 'm.mentioned_id', '=', 'p.id')
                ->where('m.mentioned_type', 'App\\Models\\Player')
                ->whereNull('p.id')
                ->select('m.id', 'm.mentioned_id')
                ->get();
            
            foreach ($orphanedPlayerMentions as $mention) {
                $this->addOrphanedRecord('mentions', $mention->id, "Mention references non-existent player {$mention->mentioned_id}");
            }
        }
        
        $totalOrphaned = ($orphanedTeamMentions ?? collect())->count() + ($orphanedPlayerMentions ?? collect())->count();
        if ($totalOrphaned == 0) {
            $this->log("✓ No orphaned mentions found");
        } else {
            $this->log("Found {$totalOrphaned} orphaned mentions");
        }
    }

    private function validateDataConsistency()
    {
        $this->log("=== Data Consistency Validation ===");
        
        // Check for duplicate team names
        $this->checkDuplicateTeamNames();
        
        // Check for players assigned to multiple teams
        $this->checkPlayerTeamConsistency();
        
        // Check for inconsistent mention counts
        $this->checkMentionCountConsistency();
        
        // Check for data format inconsistencies
        $this->checkDataFormatConsistency();
    }

    private function checkDuplicateTeamNames()
    {
        if (!Schema::hasTable('teams')) {
            return;
        }
        
        $duplicates = DB::table('teams')
            ->select('name', DB::raw('COUNT(*) as count'))
            ->groupBy('name')
            ->having('count', '>', 1)
            ->get();
        
        if ($duplicates->count() > 0) {
            $this->log("Found duplicate team names:");
            foreach ($duplicates as $duplicate) {
                $this->addDataInconsistency('teams', "Duplicate team name: '{$duplicate->name}' ({$duplicate->count} occurrences)");
                $this->log("  - '{$duplicate->name}' appears {$duplicate->count} times");
            }
        } else {
            $this->log("✓ No duplicate team names found");
        }
    }

    private function checkPlayerTeamConsistency()
    {
        if (!Schema::hasTable('players') || !Schema::hasTable('player_team_histories')) {
            return;
        }
        
        // Check if current team assignment matches latest history entry
        $inconsistencies = DB::table('players as p')
            ->leftJoin('player_team_histories as h', function($join) {
                $join->on('p.id', '=', 'h.player_id')
                     ->whereRaw('h.change_date = (SELECT MAX(change_date) FROM player_team_histories WHERE player_id = p.id)');
            })
            ->where(function($query) {
                $query->whereNotNull('p.team_id')
                      ->whereNotNull('h.to_team_id')
                      ->whereColumn('p.team_id', '!=', 'h.to_team_id');
            })
            ->orWhere(function($query) {
                $query->whereNull('p.team_id')
                      ->whereNotNull('h.to_team_id');
            })
            ->orWhere(function($query) {
                $query->whereNotNull('p.team_id')
                      ->whereNull('h.to_team_id');
            })
            ->select('p.id', 'p.name', 'p.team_id', 'h.to_team_id', 'h.change_date')
            ->get();
        
        if ($inconsistencies->count() > 0) {
            $this->log("Found player-team assignment inconsistencies:");
            foreach ($inconsistencies as $inconsistency) {
                $message = "Player '{$inconsistency->name}' current team ({$inconsistency->team_id}) doesn't match latest history ({$inconsistency->to_team_id})";
                $this->addDataInconsistency('players', $message);
                $this->log("  - {$message}");
            }
        } else {
            $this->log("✓ Player team assignments are consistent with history");
        }
    }

    private function checkMentionCountConsistency()
    {
        if (!Schema::hasTable('mentions')) {
            return;
        }
        
        // Check team mention counts if denormalized columns exist
        if (Schema::hasTable('teams') && Schema::hasColumn('teams', 'mention_count')) {
            $teamInconsistencies = DB::table('teams as t')
                ->leftJoin(DB::raw('(SELECT mentioned_id, COUNT(*) as actual_count FROM mentions WHERE mentioned_type = "App\\Models\\Team" AND is_active = 1 GROUP BY mentioned_id) as m'), 't.id', '=', 'm.mentioned_id')
                ->whereRaw('COALESCE(t.mention_count, 0) != COALESCE(m.actual_count, 0)')
                ->select('t.id', 't.name', 't.mention_count', 'm.actual_count')
                ->get();
            
            foreach ($teamInconsistencies as $inconsistency) {
                $message = "Team '{$inconsistency->name}' mention count mismatch: stored={$inconsistency->mention_count}, actual={$inconsistency->actual_count}";
                $this->addDataInconsistency('teams', $message);
            }
            
            if ($teamInconsistencies->count() == 0) {
                $this->log("✓ Team mention counts are consistent");
            } else {
                $this->log("Found {$teamInconsistencies->count()} team mention count inconsistencies");
            }
        }
        
        // Check player mention counts if denormalized columns exist
        if (Schema::hasTable('players') && Schema::hasColumn('players', 'mention_count')) {
            $playerInconsistencies = DB::table('players as p')
                ->leftJoin(DB::raw('(SELECT mentioned_id, COUNT(*) as actual_count FROM mentions WHERE mentioned_type = "App\\Models\\Player" AND is_active = 1 GROUP BY mentioned_id) as m'), 'p.id', '=', 'm.mentioned_id')
                ->whereRaw('COALESCE(p.mention_count, 0) != COALESCE(m.actual_count, 0)')
                ->select('p.id', 'p.name', 'p.mention_count', 'm.actual_count')
                ->get();
            
            foreach ($playerInconsistencies as $inconsistency) {
                $message = "Player '{$inconsistency->name}' mention count mismatch: stored={$inconsistency->mention_count}, actual={$inconsistency->actual_count}";
                $this->addDataInconsistency('players', $message);
            }
            
            if ($playerInconsistencies->count() == 0) {
                $this->log("✓ Player mention counts are consistent");
            } else {
                $this->log("Found {$playerInconsistencies->count()} player mention count inconsistencies");
            }
        }
    }

    private function checkDataFormatConsistency()
    {
        $this->log("Checking data format consistency...");
        
        // Check for null values in required fields
        foreach ($this->validationRules as $tableName => $rules) {
            if (!isset($rules['required_fields']) || !Schema::hasTable($tableName)) {
                continue;
            }
            
            foreach ($rules['required_fields'] as $field) {
                $nullCount = DB::table($tableName)->whereNull($field)->count();
                if ($nullCount > 0) {
                    $this->addDataInconsistency($tableName, "Found {$nullCount} null values in required field '{$field}'");
                }
            }
        }
    }

    private function validateConstraints()
    {
        $this->log("=== Constraint Validation ===");
        
        // Check unique constraints
        $this->checkUniqueConstraints();
        
        // Check data type constraints
        $this->checkDataTypeConstraints();
        
        // Check business logic constraints
        $this->checkBusinessLogicConstraints();
    }

    private function checkUniqueConstraints()
    {
        foreach ($this->validationRules as $tableName => $rules) {
            if (!isset($rules['unique_fields']) || !Schema::hasTable($tableName)) {
                continue;
            }
            
            foreach ($rules['unique_fields'] as $field) {
                $duplicates = DB::table($tableName)
                    ->select($field, DB::raw('COUNT(*) as count'))
                    ->whereNotNull($field)
                    ->groupBy($field)
                    ->having('count', '>', 1)
                    ->get();
                
                if ($duplicates->count() > 0) {
                    foreach ($duplicates as $duplicate) {
                        $this->addConstraintViolation($tableName, "Unique constraint violation: '{$field}' value '{$duplicate->$field}' appears {$duplicate->count} times");
                    }
                } else {
                    $this->log("✓ Unique constraint valid for {$tableName}.{$field}");
                }
            }
        }
    }

    private function checkDataTypeConstraints()
    {
        // Check for invalid email formats (if email fields exist)
        if (Schema::hasTable('players') && Schema::hasColumn('players', 'email')) {
            $invalidEmails = DB::table('players')
                ->whereNotNull('email')
                ->whereRaw("email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'")
                ->count();
            
            if ($invalidEmails > 0) {
                $this->addConstraintViolation('players', "Found {$invalidEmails} invalid email formats");
            }
        }
        
        // Check for negative values in fields that should be positive
        $positiveFields = [
            'teams' => ['rating', 'rank', 'wins', 'losses'],
            'players' => ['age', 'rating', 'wins', 'losses']
        ];
        
        foreach ($positiveFields as $tableName => $fields) {
            if (!Schema::hasTable($tableName)) continue;
            
            foreach ($fields as $field) {
                if (Schema::hasColumn($tableName, $field)) {
                    $negativeCount = DB::table($tableName)
                        ->where($field, '<', 0)
                        ->count();
                    
                    if ($negativeCount > 0) {
                        $this->addConstraintViolation($tableName, "Found {$negativeCount} negative values in field '{$field}'");
                    }
                }
            }
        }
    }

    private function checkBusinessLogicConstraints()
    {
        // Check that team history dates are logical
        if (Schema::hasTable('player_team_histories')) {
            $futureHistories = DB::table('player_team_histories')
                ->where('change_date', '>', now())
                ->count();
            
            if ($futureHistories > 0) {
                $this->addConstraintViolation('player_team_histories', "Found {$futureHistories} team changes with future dates");
            }
        }
        
        // Check that mention timestamps are reasonable
        if (Schema::hasTable('mentions')) {
            $futureMentions = DB::table('mentions')
                ->where('mentioned_at', '>', now())
                ->count();
            
            if ($futureMentions > 0) {
                $this->addConstraintViolation('mentions', "Found {$futureMentions} mentions with future timestamps");
            }
        }
    }

    private function validatePerformanceIssues()
    {
        $this->log("=== Performance Issues Validation ===");
        
        // Check for tables without primary keys
        $this->checkPrimaryKeys();
        
        // Check for large tables without indexes on foreign keys
        $this->checkMissingIndexes();
        
        // Check for tables with excessive row counts
        $this->checkTableSizes();
    }

    private function checkPrimaryKeys()
    {
        $tables = ['teams', 'players', 'mentions', 'player_team_histories', 'match_player_stats'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $columns = Schema::getColumnListing($table);
                if (!in_array('id', $columns)) {
                    $this->addPerformanceIssue($table, "Table missing primary key 'id' column");
                } else {
                    $this->log("✓ Primary key exists for table: {$table}");
                }
            }
        }
    }

    private function checkMissingIndexes()
    {
        $indexRecommendations = [
            'players' => ['team_id'],
            'mentions' => ['mentioned_type', 'mentioned_id', 'mentioned_by'],
            'player_team_histories' => ['player_id', 'from_team_id', 'to_team_id'],
            'match_player_stats' => ['player_id', 'match_id']
        ];
        
        foreach ($indexRecommendations as $table => $columns) {
            if (Schema::hasTable($table)) {
                $rowCount = DB::table($table)->count();
                if ($rowCount > 1000) { // Only flag for tables with significant data
                    foreach ($columns as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $this->addPerformanceIssue($table, "Large table ({$rowCount} rows) may benefit from index on '{$column}'");
                        }
                    }
                }
            }
        }
    }

    private function checkTableSizes()
    {
        $tables = ['teams', 'players', 'mentions', 'player_team_histories', 'match_player_stats'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->log("Table {$table}: {$count} rows");
                
                if ($count > 100000) {
                    $this->addPerformanceIssue($table, "Large table with {$count} rows may require optimization");
                }
                
                if ($count == 0) {
                    $this->addPerformanceIssue($table, "Empty table - may indicate setup issues");
                }
            }
        }
    }

    // Helper methods to add validation issues
    private function addSchemaIssue($message, $severity)
    {
        $this->validationResults['schema_issues'][] = [
            'message' => $message,
            'severity' => $severity,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->log("SCHEMA [{$severity}]: {$message}", 'WARNING');
    }

    private function addForeignKeyViolation($table, $recordId, $column, $value, $reference)
    {
        $this->validationResults['foreign_key_violations'][] = [
            'table' => $table,
            'record_id' => $recordId,
            'column' => $column,
            'value' => $value,
            'reference' => $reference,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function addOrphanedRecord($table, $recordId, $description)
    {
        $this->validationResults['orphaned_records'][] = [
            'table' => $table,
            'record_id' => $recordId,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function addDataInconsistency($table, $description)
    {
        $this->validationResults['data_inconsistencies'][] = [
            'table' => $table,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function addConstraintViolation($table, $description)
    {
        $this->validationResults['constraint_violations'][] = [
            'table' => $table,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function addPerformanceIssue($table, $description)
    {
        $this->validationResults['performance_issues'][] = [
            'table' => $table,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function generateValidationReport()
    {
        $this->log("=== Generating Validation Report ===");
        
        $totalIssues = 0;
        foreach ($this->validationResults as $category => $issues) {
            $totalIssues += count($issues);
        }
        
        $report = [
            'session_id' => $this->sessionId,
            'timestamp' => date('Y-m-d H:i:s'),
            'total_issues' => $totalIssues,
            'validation_results' => $this->validationResults,
            'summary' => $this->generateSummary(),
            'recommendations' => $this->generateRecommendations()
        ];
        
        // Save report
        $reportFile = __DIR__ . "/integrity_report_{$this->sessionId}.json";
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->logSummary($report);
        $this->log("Validation report saved: {$reportFile}");
        
        return $report;
    }

    private function generateSummary()
    {
        $summary = [];
        
        foreach ($this->validationResults as $category => $issues) {
            $count = count($issues);
            $summary[$category] = $count;
            
            if ($count > 0) {
                $severity = $this->determineCategorySeverity($category, $issues);
                $summary[$category . '_severity'] = $severity;
            }
        }
        
        $totalIssues = array_sum(array_filter($summary, 'is_numeric'));
        $summary['overall_status'] = $this->determineOverallStatus($totalIssues);
        
        return $summary;
    }

    private function determineCategorySeverity($category, $issues)
    {
        if (in_array($category, ['foreign_key_violations', 'orphaned_records'])) {
            return 'HIGH';
        }
        
        if (in_array($category, ['data_inconsistencies', 'constraint_violations'])) {
            return 'MEDIUM';
        }
        
        return 'LOW';
    }

    private function determineOverallStatus($totalIssues)
    {
        if ($totalIssues == 0) return 'EXCELLENT';
        if ($totalIssues <= 5) return 'GOOD';
        if ($totalIssues <= 15) return 'FAIR';
        if ($totalIssues <= 30) return 'POOR';
        return 'CRITICAL';
    }

    private function generateRecommendations()
    {
        $recommendations = [];
        
        if (!empty($this->validationResults['foreign_key_violations'])) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Foreign Key Violations',
                'action' => 'Fix foreign key violations by either updating references or removing orphaned records',
                'count' => count($this->validationResults['foreign_key_violations'])
            ];
        }
        
        if (!empty($this->validationResults['orphaned_records'])) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Orphaned Records',
                'action' => 'Remove orphaned records or fix their references',
                'count' => count($this->validationResults['orphaned_records'])
            ];
        }
        
        if (!empty($this->validationResults['data_inconsistencies'])) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Data Inconsistencies',
                'action' => 'Resolve data inconsistencies and implement data validation',
                'count' => count($this->validationResults['data_inconsistencies'])
            ];
        }
        
        if (!empty($this->validationResults['performance_issues'])) {
            $recommendations[] = [
                'priority' => 'LOW',
                'category' => 'Performance Issues',
                'action' => 'Add indexes and optimize query performance',
                'count' => count($this->validationResults['performance_issues'])
            ];
        }
        
        return $recommendations;
    }

    private function logSummary($report)
    {
        $this->log("=== VALIDATION SUMMARY ===");
        $this->log("Overall Status: {$report['summary']['overall_status']}");
        $this->log("Total Issues: {$report['total_issues']}");
        
        foreach ($this->validationResults as $category => $issues) {
            $count = count($issues);
            if ($count > 0) {
                $category_name = ucwords(str_replace('_', ' ', $category));
                $this->log("  {$category_name}: {$count}");
            }
        }
        
        if (!empty($report['recommendations'])) {
            $this->log("Top Recommendations:");
            foreach ($report['recommendations'] as $rec) {
                $this->log("  - [{$rec['priority']}] {$rec['action']} ({$rec['count']} issues)");
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

    public function getValidationResults()
    {
        return $this->validationResults;
    }
}

// CLI Usage
if (php_sapi_name() === 'cli') {
    echo "Database Integrity Validator\n";
    echo "Usage: php database_integrity_validator.php\n\n";
    
    $validator = new DatabaseIntegrityValidator();
    $report = $validator->runFullValidation();
    
    echo "\nValidation complete. Check the log file for detailed results: " . $validator->getLogFile() . "\n";
    echo "Overall Status: " . $report['summary']['overall_status'] . "\n";
    echo "Total Issues: " . $report['total_issues'] . "\n";
}