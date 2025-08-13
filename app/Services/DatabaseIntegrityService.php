<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

/**
 * Database Integrity Service for MRVL Platform
 * 
 * Ensures data consistency and integrity across the tournament system:
 * - Foreign key constraint validation
 * - Data consistency checks
 * - Orphaned record detection and cleanup
 * - Referential integrity enforcement
 * - Data validation and sanitization
 * - Automated integrity monitoring
 */
class DatabaseIntegrityService
{
    /**
     * Comprehensive database integrity check
     */
    public function performIntegrityCheck(): array
    {
        Log::info('Starting comprehensive database integrity check');
        
        $results = [
            'timestamp' => now()->toISOString(),
            'checks' => [],
            'issues_found' => 0,
            'critical_issues' => 0,
            'warnings' => 0,
            'recommendations' => []
        ];

        // Perform all integrity checks
        $checks = [
            'foreign_key_constraints' => $this->checkForeignKeyConstraints(),
            'orphaned_records' => $this->findOrphanedRecords(),
            'data_consistency' => $this->checkDataConsistency(),
            'tournament_integrity' => $this->checkTournamentIntegrity(),
            'team_player_consistency' => $this->checkTeamPlayerConsistency(),
            'match_data_integrity' => $this->checkMatchDataIntegrity(),
            'elo_rating_consistency' => $this->checkEloRatingConsistency(),
            'duplicate_detection' => $this->detectDuplicateRecords(),
            'data_validation' => $this->validateCriticalData()
        ];

        foreach ($checks as $checkName => $checkResult) {
            $results['checks'][$checkName] = $checkResult;
            $results['issues_found'] += $checkResult['issues_count'] ?? 0;
            $results['critical_issues'] += $checkResult['critical_count'] ?? 0;
            $results['warnings'] += $checkResult['warning_count'] ?? 0;
            
            if (!empty($checkResult['recommendations'])) {
                $results['recommendations'] = array_merge(
                    $results['recommendations'], 
                    $checkResult['recommendations']
                );
            }
        }

        $results['status'] = $this->getOverallStatus($results);
        
        Log::info('Database integrity check completed', [
            'issues_found' => $results['issues_found'],
            'critical_issues' => $results['critical_issues'],
            'status' => $results['status']
        ]);

        return $results;
    }

    /**
     * Check foreign key constraints
     */
    private function checkForeignKeyConstraints(): array
    {
        $issues = [];
        $criticalCount = 0;

        // Tournament-related foreign keys
        $foreignKeyChecks = [
            [
                'table' => 'tournament_teams',
                'foreign_key' => 'tournament_id',
                'references_table' => 'tournaments',
                'references_column' => 'id'
            ],
            [
                'table' => 'tournament_teams',
                'foreign_key' => 'team_id',
                'references_table' => 'teams',
                'references_column' => 'id'
            ],
            [
                'table' => 'bracket_matches',
                'foreign_key' => 'tournament_id',
                'references_table' => 'tournaments',
                'references_column' => 'id'
            ],
            [
                'table' => 'bracket_matches',
                'foreign_key' => 'team1_id',
                'references_table' => 'teams',
                'references_column' => 'id'
            ],
            [
                'table' => 'bracket_matches',
                'foreign_key' => 'team2_id',
                'references_table' => 'teams',
                'references_column' => 'id'
            ],
            [
                'table' => 'players',
                'foreign_key' => 'team_id',
                'references_table' => 'teams',
                'references_column' => 'id'
            ],
            [
                'table' => 'matches',
                'foreign_key' => 'team1_id',
                'references_table' => 'teams',
                'references_column' => 'id'
            ],
            [
                'table' => 'matches',
                'foreign_key' => 'team2_id',
                'references_table' => 'teams',
                'references_column' => 'id'
            ]
        ];

        foreach ($foreignKeyChecks as $check) {
            if (!Schema::hasTable($check['table']) || !Schema::hasTable($check['references_table'])) {
                continue;
            }

            $violatingRecords = DB::select("
                SELECT COUNT(*) as count 
                FROM `{$check['table']}` t1 
                LEFT JOIN `{$check['references_table']}` t2 ON t1.{$check['foreign_key']} = t2.{$check['references_column']}
                WHERE t1.{$check['foreign_key']} IS NOT NULL 
                AND t2.{$check['references_column']} IS NULL
            ");

            $count = $violatingRecords[0]->count ?? 0;
            
            if ($count > 0) {
                $issues[] = [
                    'type' => 'foreign_key_violation',
                    'severity' => 'critical',
                    'table' => $check['table'],
                    'foreign_key' => $check['foreign_key'],
                    'references' => $check['references_table'] . '.' . $check['references_column'],
                    'violating_records' => $count,
                    'description' => "Found {$count} records in {$check['table']} with invalid {$check['foreign_key']} references"
                ];
                $criticalCount++;
            }
        }

        return [
            'status' => empty($issues) ? 'passed' : 'failed',
            'issues' => $issues,
            'issues_count' => count($issues),
            'critical_count' => $criticalCount,
            'recommendations' => $criticalCount > 0 ? [
                'Run data cleanup procedures to fix foreign key violations',
                'Add proper foreign key constraints to prevent future violations'
            ] : []
        ];
    }

    /**
     * Find orphaned records
     */
    private function findOrphanedRecords(): array
    {
        $issues = [];
        $warningCount = 0;

        // Check for orphaned tournament teams
        if (Schema::hasTable('tournament_teams')) {
            $orphanedTournamentTeams = DB::select("
                SELECT COUNT(*) as count 
                FROM tournament_teams tt
                LEFT JOIN tournaments t ON tt.tournament_id = t.id
                LEFT JOIN teams tm ON tt.team_id = tm.id
                WHERE t.id IS NULL OR tm.id IS NULL
            ");

            $count = $orphanedTournamentTeams[0]->count ?? 0;
            if ($count > 0) {
                $issues[] = [
                    'type' => 'orphaned_records',
                    'severity' => 'warning',
                    'table' => 'tournament_teams',
                    'count' => $count,
                    'description' => "Found {$count} orphaned tournament team records"
                ];
                $warningCount++;
            }
        }

        // Check for orphaned match player stats
        if (Schema::hasTable('match_player_stats')) {
            $orphanedPlayerStats = DB::select("
                SELECT COUNT(*) as count 
                FROM match_player_stats mps
                LEFT JOIN players p ON mps.player_id = p.id
                LEFT JOIN matches m ON mps.match_id = m.id
                WHERE p.id IS NULL OR m.id IS NULL
            ");

            $count = $orphanedPlayerStats[0]->count ?? 0;
            if ($count > 0) {
                $issues[] = [
                    'type' => 'orphaned_records',
                    'severity' => 'warning',
                    'table' => 'match_player_stats',
                    'count' => $count,
                    'description' => "Found {$count} orphaned match player stat records"
                ];
                $warningCount++;
            }
        }

        // Check for players without teams (excluding free agents)
        $playersWithoutTeams = DB::select("
            SELECT COUNT(*) as count 
            FROM players p
            LEFT JOIN teams t ON p.team_id = t.id
            WHERE p.team_id IS NOT NULL AND t.id IS NULL
        ");

        $count = $playersWithoutTeams[0]->count ?? 0;
        if ($count > 0) {
            $issues[] = [
                'type' => 'orphaned_records',
                'severity' => 'warning',
                'table' => 'players',
                'count' => $count,
                'description' => "Found {$count} players with invalid team references"
            ];
            $warningCount++;
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
            'issues_count' => count($issues),
            'warning_count' => $warningCount,
            'recommendations' => $warningCount > 0 ? [
                'Clean up orphaned records to improve database performance',
                'Implement cascading deletes for related records'
            ] : []
        ];
    }

    /**
     * Check data consistency
     */
    private function checkDataConsistency(): array
    {
        $issues = [];
        $warningCount = 0;

        // Check tournament team count consistency
        $tournamentCountIssues = DB::select("
            SELECT t.id, t.name, t.team_count, COUNT(tt.team_id) as actual_count
            FROM tournaments t
            LEFT JOIN tournament_teams tt ON t.id = tt.tournament_id AND tt.status != 'disqualified'
            GROUP BY t.id, t.name, t.team_count
            HAVING t.team_count != COUNT(tt.team_id)
            LIMIT 10
        ");

        if (!empty($tournamentCountIssues)) {
            foreach ($tournamentCountIssues as $issue) {
                $issues[] = [
                    'type' => 'data_inconsistency',
                    'severity' => 'warning',
                    'table' => 'tournaments',
                    'tournament_id' => $issue->id,
                    'tournament_name' => $issue->name,
                    'recorded_count' => $issue->team_count,
                    'actual_count' => $issue->actual_count,
                    'description' => "Tournament team count mismatch for '{$issue->name}'"
                ];
                $warningCount++;
            }
        }

        // Check team win/loss consistency
        $teamStatsIssues = DB::select("
            SELECT t.id, t.name, t.wins, t.losses, 
                   COALESCE(match_wins.wins, 0) as actual_wins,
                   COALESCE(match_losses.losses, 0) as actual_losses
            FROM teams t
            LEFT JOIN (
                SELECT 
                    CASE WHEN team1_id = winner_id THEN team1_id ELSE team2_id END as team_id,
                    COUNT(*) as wins
                FROM matches 
                WHERE status = 'completed' AND winner_id IS NOT NULL
                GROUP BY team_id
            ) match_wins ON t.id = match_wins.team_id
            LEFT JOIN (
                SELECT 
                    CASE WHEN team1_id != winner_id THEN team1_id ELSE team2_id END as team_id,
                    COUNT(*) as losses
                FROM matches 
                WHERE status = 'completed' AND winner_id IS NOT NULL
                GROUP BY team_id
            ) match_losses ON t.id = match_losses.team_id
            WHERE (t.wins != COALESCE(match_wins.wins, 0) OR t.losses != COALESCE(match_losses.losses, 0))
            LIMIT 10
        ");

        if (!empty($teamStatsIssues)) {
            foreach ($teamStatsIssues as $issue) {
                $issues[] = [
                    'type' => 'stats_inconsistency',
                    'severity' => 'warning',
                    'table' => 'teams',
                    'team_id' => $issue->id,
                    'team_name' => $issue->name,
                    'recorded_wins' => $issue->wins,
                    'actual_wins' => $issue->actual_wins,
                    'recorded_losses' => $issue->losses,
                    'actual_losses' => $issue->actual_losses,
                    'description' => "Team win/loss record inconsistency for '{$issue->name}'"
                ];
                $warningCount++;
            }
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
            'issues_count' => count($issues),
            'warning_count' => $warningCount,
            'recommendations' => $warningCount > 0 ? [
                'Update team statistics to match actual match results',
                'Implement automated statistics calculation triggers'
            ] : []
        ];
    }

    /**
     * Check tournament integrity
     */
    private function checkTournamentIntegrity(): array
    {
        $issues = [];
        $warningCount = 0;

        // Check for tournaments with invalid date ranges
        $dateIssues = DB::select("
            SELECT id, name, start_date, end_date
            FROM tournaments
            WHERE start_date > end_date
            LIMIT 10
        ");

        foreach ($dateIssues as $issue) {
            $issues[] = [
                'type' => 'invalid_date_range',
                'severity' => 'warning',
                'tournament_id' => $issue->id,
                'tournament_name' => $issue->name,
                'start_date' => $issue->start_date,
                'end_date' => $issue->end_date,
                'description' => "Tournament '{$issue->name}' has start date after end date"
            ];
            $warningCount++;
        }

        // Check for tournaments with team count exceeding max_teams
        $teamCountIssues = DB::select("
            SELECT t.id, t.name, t.team_count, t.max_teams
            FROM tournaments t
            WHERE t.team_count > t.max_teams
            LIMIT 10
        ");

        foreach ($teamCountIssues as $issue) {
            $issues[] = [
                'type' => 'team_count_exceeded',
                'severity' => 'warning',
                'tournament_id' => $issue->id,
                'tournament_name' => $issue->name,
                'team_count' => $issue->team_count,
                'max_teams' => $issue->max_teams,
                'description' => "Tournament '{$issue->name}' has more teams than maximum allowed"
            ];
            $warningCount++;
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
            'issues_count' => count($issues),
            'warning_count' => $warningCount,
            'recommendations' => $warningCount > 0 ? [
                'Fix tournament date ranges and team count limits',
                'Add database constraints to prevent invalid tournament data'
            ] : []
        ];
    }

    /**
     * Check team-player consistency
     */
    private function checkTeamPlayerConsistency(): array
    {
        $issues = [];
        $warningCount = 0;

        // Check for teams without players
        $teamsWithoutPlayers = DB::select("
            SELECT t.id, t.name, COUNT(p.id) as player_count
            FROM teams t
            LEFT JOIN players p ON t.id = p.team_id AND p.status = 'active'
            WHERE t.status = 'active'
            GROUP BY t.id, t.name
            HAVING COUNT(p.id) = 0
            LIMIT 10
        ");

        foreach ($teamsWithoutPlayers as $team) {
            $issues[] = [
                'type' => 'team_without_players',
                'severity' => 'warning',
                'team_id' => $team->id,
                'team_name' => $team->name,
                'description' => "Active team '{$team->name}' has no active players"
            ];
            $warningCount++;
        }

        // Check for duplicate player positions within teams
        $duplicatePositions = DB::select("
            SELECT team_id, role, COUNT(*) as count
            FROM players
            WHERE team_id IS NOT NULL AND status = 'active'
            GROUP BY team_id, role
            HAVING COUNT(*) > 2
            LIMIT 10
        ");

        foreach ($duplicatePositions as $duplicate) {
            $team = DB::table('teams')->where('id', $duplicate->team_id)->first();
            $issues[] = [
                'type' => 'duplicate_player_roles',
                'severity' => 'warning',
                'team_id' => $duplicate->team_id,
                'team_name' => $team->name ?? 'Unknown',
                'role' => $duplicate->role,
                'player_count' => $duplicate->count,
                'description' => "Team has {$duplicate->count} players in {$duplicate->role} role"
            ];
            $warningCount++;
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
            'issues_count' => count($issues),
            'warning_count' => $warningCount,
            'recommendations' => $warningCount > 0 ? [
                'Ensure active teams have active players',
                'Review team rosters for proper role distribution'
            ] : []
        ];
    }

    /**
     * Check match data integrity
     */
    private function checkMatchDataIntegrity(): array
    {
        $issues = [];
        $warningCount = 0;

        // Check for matches with invalid scores
        $invalidScores = DB::select("
            SELECT id, team1_score, team2_score, winner_id
            FROM matches
            WHERE status = 'completed' 
            AND (
                (team1_score IS NULL OR team2_score IS NULL)
                OR (team1_score = team2_score AND winner_id IS NOT NULL)
                OR (team1_score > team2_score AND winner_id != team1_id)
                OR (team2_score > team1_score AND winner_id != team2_id)
            )
            LIMIT 10
        ");

        foreach ($invalidScores as $match) {
            $issues[] = [
                'type' => 'invalid_match_score',
                'severity' => 'warning',
                'match_id' => $match->id,
                'team1_score' => $match->team1_score,
                'team2_score' => $match->team2_score,
                'winner_id' => $match->winner_id,
                'description' => "Match {$match->id} has inconsistent score and winner data"
            ];
            $warningCount++;
        }

        // Check for matches with invalid timestamps
        $invalidTimestamps = DB::select("
            SELECT id, scheduled_at, started_at, completed_at
            FROM matches
            WHERE (started_at IS NOT NULL AND scheduled_at IS NOT NULL AND started_at < scheduled_at)
            OR (completed_at IS NOT NULL AND started_at IS NOT NULL AND completed_at < started_at)
            LIMIT 10
        ");

        foreach ($invalidTimestamps as $match) {
            $issues[] = [
                'type' => 'invalid_match_timestamps',
                'severity' => 'warning',
                'match_id' => $match->id,
                'scheduled_at' => $match->scheduled_at,
                'started_at' => $match->started_at,
                'completed_at' => $match->completed_at,
                'description' => "Match {$match->id} has invalid timestamp sequence"
            ];
            $warningCount++;
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
            'issues_count' => count($issues),
            'warning_count' => $warningCount,
            'recommendations' => $warningCount > 0 ? [
                'Fix match scores and winner assignments',
                'Ensure proper match timestamp sequencing'
            ] : []
        ];
    }

    /**
     * Check ELO rating consistency
     */
    private function checkEloRatingConsistency(): array
    {
        $issues = [];
        $warningCount = 0;

        // Check for players with ELO ratings outside reasonable bounds
        $invalidEloPlayers = DB::select("
            SELECT id, name, elo_rating
            FROM players
            WHERE elo_rating < 0 OR elo_rating > 10000
            LIMIT 10
        ");

        foreach ($invalidEloPlayers as $player) {
            $issues[] = [
                'type' => 'invalid_elo_rating',
                'severity' => 'warning',
                'player_id' => $player->id,
                'player_name' => $player->name,
                'elo_rating' => $player->elo_rating,
                'description' => "Player '{$player->name}' has unrealistic ELO rating: {$player->elo_rating}"
            ];
            $warningCount++;
        }

        // Check for teams with ELO ratings outside reasonable bounds
        $invalidEloTeams = DB::select("
            SELECT id, name, elo_rating
            FROM teams
            WHERE elo_rating < 0 OR elo_rating > 10000
            LIMIT 10
        ");

        foreach ($invalidEloTeams as $team) {
            $issues[] = [
                'type' => 'invalid_elo_rating',
                'severity' => 'warning',
                'team_id' => $team->id,
                'team_name' => $team->name,
                'elo_rating' => $team->elo_rating,
                'description' => "Team '{$team->name}' has unrealistic ELO rating: {$team->elo_rating}"
            ];
            $warningCount++;
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
            'issues_count' => count($issues),
            'warning_count' => $warningCount,
            'recommendations' => $warningCount > 0 ? [
                'Reset ELO ratings to reasonable bounds',
                'Recalculate ELO ratings from match history'
            ] : []
        ];
    }

    /**
     * Detect duplicate records
     */
    private function detectDuplicateRecords(): array
    {
        $issues = [];
        $warningCount = 0;

        // Check for duplicate team names
        $duplicateTeams = DB::select("
            SELECT name, COUNT(*) as count
            FROM teams
            GROUP BY name
            HAVING COUNT(*) > 1
            LIMIT 10
        ");

        foreach ($duplicateTeams as $duplicate) {
            $issues[] = [
                'type' => 'duplicate_team_names',
                'severity' => 'warning',
                'team_name' => $duplicate->name,
                'count' => $duplicate->count,
                'description' => "Found {$duplicate->count} teams with name '{$duplicate->name}'"
            ];
            $warningCount++;
        }

        // Check for duplicate player names within same team
        $duplicatePlayers = DB::select("
            SELECT team_id, name, COUNT(*) as count
            FROM players
            WHERE team_id IS NOT NULL
            GROUP BY team_id, name
            HAVING COUNT(*) > 1
            LIMIT 10
        ");

        foreach ($duplicatePlayers as $duplicate) {
            $team = DB::table('teams')->where('id', $duplicate->team_id)->first();
            $issues[] = [
                'type' => 'duplicate_player_names',
                'severity' => 'warning',
                'team_id' => $duplicate->team_id,
                'team_name' => $team->name ?? 'Unknown',
                'player_name' => $duplicate->name,
                'count' => $duplicate->count,
                'description' => "Team '{$team->name}' has {$duplicate->count} players named '{$duplicate->name}'"
            ];
            $warningCount++;
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
            'issues_count' => count($issues),
            'warning_count' => $warningCount,
            'recommendations' => $warningCount > 0 ? [
                'Review and consolidate duplicate records',
                'Implement unique constraints where appropriate'
            ] : []
        ];
    }

    /**
     * Validate critical data
     */
    private function validateCriticalData(): array
    {
        $issues = [];
        $warningCount = 0;

        // Check for missing required fields
        $missingRequiredFields = [
            ['table' => 'tournaments', 'field' => 'name', 'condition' => 'name IS NULL OR name = ""'],
            ['table' => 'teams', 'field' => 'name', 'condition' => 'name IS NULL OR name = ""'],
            ['table' => 'players', 'field' => 'name', 'condition' => 'name IS NULL OR name = ""'],
            ['table' => 'tournaments', 'field' => 'status', 'condition' => 'status IS NULL OR status = ""'],
            ['table' => 'teams', 'field' => 'region', 'condition' => 'region IS NULL OR region = ""']
        ];

        foreach ($missingRequiredFields as $check) {
            if (!Schema::hasTable($check['table'])) {
                continue;
            }

            $count = DB::select("SELECT COUNT(*) as count FROM `{$check['table']}` WHERE {$check['condition']}")[0]->count ?? 0;
            
            if ($count > 0) {
                $issues[] = [
                    'type' => 'missing_required_field',
                    'severity' => 'warning',
                    'table' => $check['table'],
                    'field' => $check['field'],
                    'count' => $count,
                    'description' => "Found {$count} records in {$check['table']} with missing {$check['field']}"
                ];
                $warningCount++;
            }
        }

        return [
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
            'issues_count' => count($issues),
            'warning_count' => $warningCount,
            'recommendations' => $warningCount > 0 ? [
                'Fill in missing required fields',
                'Add NOT NULL constraints to prevent missing data'
            ] : []
        ];
    }

    /**
     * Get overall status based on check results
     */
    private function getOverallStatus(array $results): string
    {
        if ($results['critical_issues'] > 0) {
            return 'critical';
        }
        
        if ($results['issues_found'] > 10) {
            return 'poor';
        }
        
        if ($results['issues_found'] > 0) {
            return 'warning';
        }
        
        return 'excellent';
    }

    /**
     * Automated cleanup of safe-to-fix issues
     */
    public function performAutomatedCleanup(): array
    {
        Log::info('Starting automated database cleanup');
        
        $results = [
            'timestamp' => now()->toISOString(),
            'actions_performed' => [],
            'records_affected' => 0
        ];

        try {
            DB::beginTransaction();

            // Clean up orphaned tournament team records
            $orphanedTournamentTeams = DB::delete("
                DELETE tt FROM tournament_teams tt
                LEFT JOIN tournaments t ON tt.tournament_id = t.id
                LEFT JOIN teams tm ON tt.team_id = tm.id
                WHERE t.id IS NULL OR tm.id IS NULL
            ");

            if ($orphanedTournamentTeams > 0) {
                $results['actions_performed'][] = "Cleaned up {$orphanedTournamentTeams} orphaned tournament team records";
                $results['records_affected'] += $orphanedTournamentTeams;
            }

            // Clean up orphaned match player stats
            if (Schema::hasTable('match_player_stats')) {
                $orphanedPlayerStats = DB::delete("
                    DELETE mps FROM match_player_stats mps
                    LEFT JOIN players p ON mps.player_id = p.id
                    LEFT JOIN matches m ON mps.match_id = m.id
                    WHERE p.id IS NULL OR m.id IS NULL
                ");

                if ($orphanedPlayerStats > 0) {
                    $results['actions_performed'][] = "Cleaned up {$orphanedPlayerStats} orphaned match player stat records";
                    $results['records_affected'] += $orphanedPlayerStats;
                }
            }

            // Update tournament team counts
            $updatedTournaments = DB::update("
                UPDATE tournaments t
                SET team_count = (
                    SELECT COUNT(*)
                    FROM tournament_teams tt
                    WHERE tt.tournament_id = t.id AND tt.status != 'disqualified'
                )
            ");

            if ($updatedTournaments > 0) {
                $results['actions_performed'][] = "Updated team counts for {$updatedTournaments} tournaments";
                $results['records_affected'] += $updatedTournaments;
            }

            DB::commit();
            
            $results['status'] = 'success';
            Log::info('Automated database cleanup completed successfully', $results);

        } catch (\Exception $e) {
            DB::rollBack();
            $results['status'] = 'failed';
            $results['error'] = $e->getMessage();
            Log::error('Automated database cleanup failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Add missing database constraints
     */
    public function addMissingConstraints(): array
    {
        Log::info('Adding missing database constraints');
        
        $results = [
            'timestamp' => now()->toISOString(),
            'constraints_added' => [],
            'errors' => []
        ];

        $constraints = [
            // Tournament constraints
            "ALTER TABLE tournaments ADD CONSTRAINT chk_tournament_dates CHECK (start_date <= end_date)",
            "ALTER TABLE tournaments ADD CONSTRAINT chk_team_counts CHECK (team_count <= max_teams)",
            "ALTER TABLE tournaments ADD CONSTRAINT chk_max_teams_positive CHECK (max_teams > 0)",
            
            // Team constraints
            "ALTER TABLE teams ADD CONSTRAINT chk_team_elo_bounds CHECK (elo_rating BETWEEN 0 AND 10000)",
            "ALTER TABLE teams ADD CONSTRAINT chk_team_wins_losses CHECK (wins >= 0 AND losses >= 0)",
            
            // Player constraints
            "ALTER TABLE players ADD CONSTRAINT chk_player_elo_bounds CHECK (elo_rating BETWEEN 0 AND 10000)",
            
            // Match constraints
            "ALTER TABLE matches ADD CONSTRAINT chk_match_scores CHECK (team1_score >= 0 AND team2_score >= 0)",
            "ALTER TABLE bracket_matches ADD CONSTRAINT chk_bracket_scores CHECK (team1_score >= 0 AND team2_score >= 0)"
        ];

        foreach ($constraints as $constraint) {
            try {
                DB::statement($constraint);
                $results['constraints_added'][] = $constraint;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'constraint' => $constraint,
                    'error' => $e->getMessage()
                ];
            }
        }

        $results['status'] = empty($results['errors']) ? 'success' : 'partial';
        
        Log::info('Database constraints addition completed', [
            'added' => count($results['constraints_added']),
            'errors' => count($results['errors'])
        ]);

        return $results;
    }
}