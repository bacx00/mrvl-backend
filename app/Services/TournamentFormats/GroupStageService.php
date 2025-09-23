<?php

namespace App\Services\TournamentFormats;

use Illuminate\Support\Facades\Log;

class GroupStageService
{
    /**
     * Generate Group Stage tournament
     *
     * @param array $teams Array of team objects with id, name, seed
     * @param int $groupCount Number of groups
     * @param int $advanceCount Teams advancing from each group
     * @param string $bestOfFormat (BO1, BO3, BO5, etc.)
     * @param string $playoffFormat Format for playoff stage
     * @param bool $gslFormat Use GSL (Korean) format for groups
     * @return array Tournament structure with groups and playoff bracket
     */
    public function generateTournament(
        array $teams,
        int $groupCount = 4,
        int $advanceCount = 2,
        string $bestOfFormat = 'BO1',
        string $playoffFormat = 'single_elimination',
        bool $gslFormat = false
    ): array {
        Log::info('Generating Group Stage tournament', [
            'team_count' => count($teams),
            'group_count' => $groupCount,
            'advance_count' => $advanceCount,
            'best_of_format' => $bestOfFormat,
            'playoff_format' => $playoffFormat,
            'gsl_format' => $gslFormat
        ]);

        // Validate input
        if (count($teams) < $groupCount * 2) {
            throw new \InvalidArgumentException('Not enough teams for the specified group count');
        }

        if ($advanceCount < 1) {
            throw new \InvalidArgumentException('At least 1 team must advance from each group');
        }

        // Sort teams by seed
        usort($teams, function($a, $b) {
            return ($a['seed'] ?? 999) <=> ($b['seed'] ?? 999);
        });

        // Distribute teams into groups
        $groups = $this->distributeTeamsIntoGroups($teams, $groupCount);

        // Generate group stage matches
        $groupStages = [];
        foreach ($groups as $groupIndex => $groupTeams) {
            $groupStages[$groupIndex] = $this->generateGroupMatches(
                $groupTeams,
                $groupIndex,
                $bestOfFormat,
                $gslFormat
            );
        }

        // Prepare playoff bracket structure (will be populated after group stage)
        $playoffBracket = $this->preparePlayoffBracket($groupCount, $advanceCount, $playoffFormat);

        return [
            'format' => 'group_stage',
            'group_count' => $groupCount,
            'teams_per_group' => count($groups[0]),
            'advance_count' => $advanceCount,
            'gsl_format' => $gslFormat,
            'current_stage' => 'groups',
            'group_stages' => $groupStages,
            'playoff_bracket' => $playoffBracket,
            'advancing_teams' => [],
            'best_of_format' => $bestOfFormat,
            'playoff_format' => $playoffFormat,
            'teams' => $teams
        ];
    }

    /**
     * Distribute teams into groups using snake seeding
     */
    private function distributeTeamsIntoGroups(array $teams, int $groupCount): array
    {
        $groups = array_fill(0, $groupCount, []);
        $teamIndex = 0;

        // Snake distribution to balance groups
        while ($teamIndex < count($teams)) {
            // Forward pass (0 to groupCount-1)
            for ($group = 0; $group < $groupCount && $teamIndex < count($teams); $group++) {
                $groups[$group][] = $teams[$teamIndex++];
            }

            // Backward pass (groupCount-1 to 0)
            for ($group = $groupCount - 1; $group >= 0 && $teamIndex < count($teams); $group--) {
                $groups[$group][] = $teams[$teamIndex++];
            }
        }

        return $groups;
    }

    /**
     * Generate matches for a single group
     */
    private function generateGroupMatches(array $teams, int $groupIndex, string $bestOfFormat, bool $gslFormat): array
    {
        $groupName = chr(65 + $groupIndex); // A, B, C, D, etc.

        if ($gslFormat && count($teams) === 4) {
            return $this->generateGSLMatches($teams, $groupIndex, $groupName, $bestOfFormat);
        } else {
            return $this->generateRoundRobinMatches($teams, $groupIndex, $groupName, $bestOfFormat);
        }
    }

    /**
     * Generate GSL format matches (Korean tournament format)
     */
    private function generateGSLMatches(array $teams, int $groupIndex, string $groupName, string $bestOfFormat): array
    {
        // GSL Format for 4 teams:
        // Initial matches: 1v2, 3v4
        // Winner's match: W(1v2) vs W(3v4) -> Winner advances
        // Loser's match: L(1v2) vs L(3v4) -> Loser eliminated
        // Final match: L(Winner's) vs W(Loser's) -> Winner advances

        $matches = [];
        $playerRecords = $this->initializePlayerRecords($teams);

        // Initial matches
        $matches[] = [
            'id' => "GS_G{$groupName}_IM1",
            'group' => $groupName,
            'group_index' => $groupIndex,
            'round' => 1,
            'round_name' => 'Initial Match 1',
            'position' => 1,
            'team1' => $teams[0], // Seed 1
            'team2' => $teams[1], // Seed 2
            'winner' => null,
            'loser' => null,
            'status' => 'pending',
            'best_of_format' => $bestOfFormat,
            'scores' => [],
            'match_type' => 'initial',
            'elimination' => false,
            'advancement' => false,
            'created_at' => now(),
            'updated_at' => now()
        ];

        $matches[] = [
            'id' => "GS_G{$groupName}_IM2",
            'group' => $groupName,
            'group_index' => $groupIndex,
            'round' => 1,
            'round_name' => 'Initial Match 2',
            'position' => 2,
            'team1' => $teams[2], // Seed 3
            'team2' => $teams[3], // Seed 4
            'winner' => null,
            'loser' => null,
            'status' => 'pending',
            'best_of_format' => $bestOfFormat,
            'scores' => [],
            'match_type' => 'initial',
            'elimination' => false,
            'advancement' => false,
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Winner's match
        $matches[] = [
            'id' => "GS_G{$groupName}_WM",
            'group' => $groupName,
            'group_index' => $groupIndex,
            'round' => 2,
            'round_name' => "Winner's Match",
            'position' => 1,
            'team1' => null, // Winner of IM1
            'team2' => null, // Winner of IM2
            'winner' => null,
            'loser' => null,
            'status' => 'waiting',
            'best_of_format' => $bestOfFormat,
            'scores' => [],
            'match_type' => 'winners',
            'elimination' => false,
            'advancement' => true, // Winner advances directly
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Loser's match
        $matches[] = [
            'id' => "GS_G{$groupName}_LM",
            'group' => $groupName,
            'group_index' => $groupIndex,
            'round' => 2,
            'round_name' => "Loser's Match",
            'position' => 2,
            'team1' => null, // Loser of IM1
            'team2' => null, // Loser of IM2
            'winner' => null,
            'loser' => null,
            'status' => 'waiting',
            'best_of_format' => $bestOfFormat,
            'scores' => [],
            'match_type' => 'losers',
            'elimination' => true, // Loser is eliminated
            'advancement' => false,
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Final match
        $matches[] = [
            'id' => "GS_G{$groupName}_FM",
            'group' => $groupName,
            'group_index' => $groupIndex,
            'round' => 3,
            'round_name' => 'Final Match',
            'position' => 1,
            'team1' => null, // Loser of Winner's Match
            'team2' => null, // Winner of Loser's Match
            'winner' => null,
            'loser' => null,
            'status' => 'waiting',
            'best_of_format' => $bestOfFormat,
            'scores' => [],
            'match_type' => 'final',
            'elimination' => true, // Loser is eliminated
            'advancement' => true, // Winner advances
            'created_at' => now(),
            'updated_at' => now()
        ];

        return [
            'format' => 'gsl',
            'group_name' => $groupName,
            'group_index' => $groupIndex,
            'teams' => $teams,
            'matches' => $matches,
            'player_records' => $playerRecords,
            'standings' => [],
            'advancing_teams' => [],
            'eliminated_teams' => [],
            'status' => 'pending'
        ];
    }

    /**
     * Generate round robin matches for a group
     */
    private function generateRoundRobinMatches(array $teams, int $groupIndex, string $groupName, string $bestOfFormat): array
    {
        $matches = [];
        $matchId = 1;

        // Generate all possible pairings
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $matches[] = [
                    'id' => "GS_G{$groupName}_M{$matchId}",
                    'group' => $groupName,
                    'group_index' => $groupIndex,
                    'round' => $this->calculateRoundRobinRound($matchId, count($teams)),
                    'round_name' => "Round " . $this->calculateRoundRobinRound($matchId, count($teams)),
                    'position' => $matchId,
                    'team1' => $teams[$i],
                    'team2' => $teams[$j],
                    'winner' => null,
                    'loser' => null,
                    'draw' => false,
                    'status' => 'pending',
                    'best_of_format' => $bestOfFormat,
                    'scores' => [],
                    'match_type' => 'round_robin',
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $matchId++;
            }
        }

        return [
            'format' => 'round_robin',
            'group_name' => $groupName,
            'group_index' => $groupIndex,
            'teams' => $teams,
            'matches' => $matches,
            'player_records' => $this->initializePlayerRecords($teams),
            'standings' => [],
            'status' => 'pending'
        ];
    }

    /**
     * Calculate round for round robin match
     */
    private function calculateRoundRobinRound(int $matchId, int $teamCount): int
    {
        $matchesPerRound = intval($teamCount / 2);
        return ceil($matchId / $matchesPerRound);
    }

    /**
     * Prepare playoff bracket structure
     */
    private function preparePlayoffBracket(int $groupCount, int $advanceCount, string $format): array
    {
        $totalAdvancingTeams = $groupCount * $advanceCount;

        return [
            'format' => $format,
            'total_teams' => $totalAdvancingTeams,
            'seeding_method' => 'group_placement', // 1st place vs 2nd place, etc.
            'bracket' => null, // Will be generated after group stage
            'status' => 'waiting'
        ];
    }

    /**
     * Update match result for group stage
     */
    public function updateGroupMatchResult(array &$tournament, string $matchId, array $winner, ?array $loser = null, bool $isDraw = false): array
    {
        Log::info('Updating Group Stage match result', [
            'match_id' => $matchId,
            'winner' => $winner['name'] ?? 'Unknown',
            'is_draw' => $isDraw
        ]);

        // Find the group and match
        $groupIndex = $this->extractGroupFromMatchId($matchId);
        $group = &$tournament['group_stages'][$groupIndex];

        // Update the match
        foreach ($group['matches'] as &$match) {
            if ($match['id'] === $matchId) {
                $match['winner'] = $isDraw ? null : $winner;
                $match['loser'] = $isDraw ? null : $loser;
                $match['draw'] = $isDraw;
                $match['status'] = 'completed';
                $match['updated_at'] = now();

                // Update player records
                if ($group['format'] === 'round_robin') {
                    $this->updateRoundRobinRecord($group, $match, $isDraw);
                } else {
                    $this->updateGSLRecord($group, $match);
                }

                // Advance teams in subsequent matches for GSL
                if ($group['format'] === 'gsl') {
                    $this->advanceGSLTeams($group, $match);
                }

                break;
            }
        }

        // Update group standings
        $this->updateGroupStandings($group);

        // Check if group is complete
        if ($this->isGroupComplete($group)) {
            $this->finalizeGroup($tournament, $groupIndex);
        }

        return $tournament;
    }

    /**
     * Update round robin record
     */
    private function updateRoundRobinRecord(array &$group, array $match, bool $isDraw): void
    {
        foreach ($group['player_records'] as &$record) {
            if ($record['team_id'] === $match['team1']['id']) {
                $record['matches_played']++;
                if ($isDraw) {
                    $record['draws']++;
                    $record['points'] += 1;
                } elseif ($match['winner']['id'] === $match['team1']['id']) {
                    $record['wins']++;
                    $record['points'] += 3;
                } else {
                    $record['losses']++;
                }
            } elseif ($record['team_id'] === $match['team2']['id']) {
                $record['matches_played']++;
                if ($isDraw) {
                    $record['draws']++;
                    $record['points'] += 1;
                } elseif ($match['winner']['id'] === $match['team2']['id']) {
                    $record['wins']++;
                    $record['points'] += 3;
                } else {
                    $record['losses']++;
                }
            }
        }
    }

    /**
     * Update GSL record
     */
    private function updateGSLRecord(array &$group, array $match): void
    {
        // GSL uses match wins rather than points
        foreach ($group['player_records'] as &$record) {
            if ($record['team_id'] === $match['winner']['id']) {
                $record['wins']++;
                $record['matches_played']++;
            } elseif ($record['team_id'] === $match['loser']['id']) {
                $record['losses']++;
                $record['matches_played']++;
            }
        }
    }

    /**
     * Advance teams in GSL format
     */
    private function advanceGSLTeams(array &$group, array $completedMatch): void
    {
        $matchType = $completedMatch['match_type'];

        foreach ($group['matches'] as &$match) {
            if ($match['status'] === 'waiting') {
                switch ($matchType) {
                    case 'initial':
                        if ($completedMatch['id'] === "GS_G{$group['group_name']}_IM1") {
                            // Advance to winner's and loser's matches
                            $this->setGSLTeamSlot($group, 'WM', 'team1', $completedMatch['winner']);
                            $this->setGSLTeamSlot($group, 'LM', 'team1', $completedMatch['loser']);
                        } elseif ($completedMatch['id'] === "GS_G{$group['group_name']}_IM2") {
                            $this->setGSLTeamSlot($group, 'WM', 'team2', $completedMatch['winner']);
                            $this->setGSLTeamSlot($group, 'LM', 'team2', $completedMatch['loser']);
                        }
                        break;

                    case 'winners':
                        // Winner advances directly, loser goes to final match
                        $group['advancing_teams'][] = $completedMatch['winner'];
                        $this->setGSLTeamSlot($group, 'FM', 'team1', $completedMatch['loser']);
                        break;

                    case 'losers':
                        // Winner goes to final match, loser is eliminated
                        $this->setGSLTeamSlot($group, 'FM', 'team2', $completedMatch['winner']);
                        $group['eliminated_teams'][] = $completedMatch['loser'];
                        break;

                    case 'final':
                        // Winner advances, loser is eliminated
                        $group['advancing_teams'][] = $completedMatch['winner'];
                        $group['eliminated_teams'][] = $completedMatch['loser'];
                        break;
                }
            }
        }

        // Update match statuses
        foreach ($group['matches'] as &$match) {
            if ($match['status'] === 'waiting' && $match['team1'] && $match['team2']) {
                $match['status'] = 'pending';
            }
        }
    }

    /**
     * Set team slot in GSL match
     */
    private function setGSLTeamSlot(array &$group, string $matchSuffix, string $slot, array $team): void
    {
        foreach ($group['matches'] as &$match) {
            if (str_ends_with($match['id'], $matchSuffix)) {
                $match[$slot] = $team;
                break;
            }
        }
    }

    /**
     * Update group standings
     */
    private function updateGroupStandings(array &$group): void
    {
        if ($group['format'] === 'gsl') {
            // GSL standings are based on advancement/elimination
            $standings = [];

            // Add advancing teams
            foreach ($group['advancing_teams'] as $index => $team) {
                $standings[] = [
                    'rank' => $index + 1,
                    'team' => $team,
                    'status' => 'advanced'
                ];
            }

            // Add remaining teams
            foreach ($group['teams'] as $team) {
                $isAdvanced = collect($group['advancing_teams'])->contains('id', $team['id']);
                $isEliminated = collect($group['eliminated_teams'])->contains('id', $team['id']);

                if (!$isAdvanced && !$isEliminated) {
                    $standings[] = [
                        'rank' => count($standings) + 1,
                        'team' => $team,
                        'status' => 'pending'
                    ];
                }
            }

            // Add eliminated teams
            foreach ($group['eliminated_teams'] as $team) {
                $standings[] = [
                    'rank' => count($standings) + 1,
                    'team' => $team,
                    'status' => 'eliminated'
                ];
            }

            $group['standings'] = $standings;
        } else {
            // Round robin standings
            $standings = $group['player_records'];

            usort($standings, function($a, $b) {
                if ($a['points'] !== $b['points']) {
                    return $b['points'] <=> $a['points'];
                }

                if ($a['wins'] !== $b['wins']) {
                    return $b['wins'] <=> $a['wins'];
                }

                return ($a['seed'] ?? 999) <=> ($b['seed'] ?? 999);
            });

            foreach ($standings as $index => &$standing) {
                $standing['rank'] = $index + 1;
            }

            $group['standings'] = $standings;
        }
    }

    /**
     * Check if group is complete
     */
    private function isGroupComplete(array $group): bool
    {
        foreach ($group['matches'] as $match) {
            if ($match['status'] !== 'completed') {
                return false;
            }
        }
        return true;
    }

    /**
     * Finalize group and determine advancing teams
     */
    private function finalizeGroup(array &$tournament, int $groupIndex): void
    {
        $group = &$tournament['group_stages'][$groupIndex];
        $group['status'] = 'completed';

        if ($group['format'] === 'round_robin') {
            // Take top N teams from standings
            $advancingTeams = array_slice($group['standings'], 0, $tournament['advance_count']);

            foreach ($advancingTeams as $standing) {
                $tournament['advancing_teams'][] = [
                    'team' => $standing['team'],
                    'group' => $group['group_name'],
                    'group_rank' => $standing['rank'],
                    'points' => $standing['points']
                ];
            }
        }

        Log::info('Group finalized', [
            'group' => $group['group_name'],
            'advancing_teams' => count($group['advancing_teams'] ?? [])
        ]);

        // Check if all groups are complete
        $this->checkPlayoffReadiness($tournament);
    }

    /**
     * Check if playoff stage can begin
     */
    private function checkPlayoffReadiness(array &$tournament): void
    {
        $allGroupsComplete = true;

        foreach ($tournament['group_stages'] as $group) {
            if ($group['status'] !== 'completed') {
                $allGroupsComplete = false;
                break;
            }
        }

        if ($allGroupsComplete) {
            $this->generatePlayoffBracket($tournament);
        }
    }

    /**
     * Generate playoff bracket from advancing teams
     */
    private function generatePlayoffBracket(array &$tournament): void
    {
        Log::info('Generating playoff bracket');

        $advancingTeams = $tournament['advancing_teams'];

        // Sort by group rank and points
        usort($advancingTeams, function($a, $b) {
            if ($a['group_rank'] !== $b['group_rank']) {
                return $a['group_rank'] <=> $b['group_rank'];
            }
            return $b['points'] <=> $a['points'];
        });

        // Generate bracket based on format
        $playoffFormat = $tournament['playoff_format'];

        switch ($playoffFormat) {
            case 'single_elimination':
                $singleElimService = new SingleEliminationService();
                $teams = array_map(function($item) { return $item['team']; }, $advancingTeams);
                $bracket = $singleElimService->generateBracket($teams, $tournament['best_of_format']);
                break;

            case 'double_elimination':
                $doubleElimService = new DoubleEliminationService();
                $teams = array_map(function($item) { return $item['team']; }, $advancingTeams);
                $bracket = $doubleElimService->generateBracket($teams, $tournament['best_of_format']);
                break;

            default:
                throw new \InvalidArgumentException("Unsupported playoff format: {$playoffFormat}");
        }

        $tournament['playoff_bracket'] = $bracket;
        $tournament['current_stage'] = 'playoffs';

        Log::info('Playoff bracket generated', [
            'format' => $playoffFormat,
            'teams' => count($advancingTeams)
        ]);
    }

    /**
     * Helper methods
     */
    private function initializePlayerRecords(array $teams): array
    {
        $records = [];

        foreach ($teams as $team) {
            $records[] = [
                'team_id' => $team['id'],
                'team' => $team,
                'points' => 0,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'matches_played' => 0,
                'seed' => $team['seed'] ?? 999
            ];
        }

        return $records;
    }

    private function extractGroupFromMatchId(string $matchId): int
    {
        // Extract group letter from match ID (e.g., "GS_GA_M1" -> "A")
        preg_match('/GS_G([A-Z])_/', $matchId, $matches);
        $groupLetter = $matches[1] ?? 'A';
        return ord($groupLetter) - ord('A');
    }

    /**
     * Get tournament progress
     */
    public function getTournamentProgress(array $tournament): array
    {
        $groupProgress = [];
        $totalMatches = 0;
        $completedMatches = 0;

        foreach ($tournament['group_stages'] as $group) {
            $groupTotal = count($group['matches']);
            $groupCompleted = 0;

            foreach ($group['matches'] as $match) {
                if ($match['status'] === 'completed') {
                    $groupCompleted++;
                }
            }

            $groupProgress[] = [
                'group' => $group['group_name'],
                'completed' => $groupCompleted,
                'total' => $groupTotal,
                'percentage' => $groupTotal > 0 ? ($groupCompleted / $groupTotal) * 100 : 0
            ];

            $totalMatches += $groupTotal;
            $completedMatches += $groupCompleted;
        }

        return [
            'current_stage' => $tournament['current_stage'],
            'group_progress' => $groupProgress,
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'completion_percentage' => $totalMatches > 0 ? ($completedMatches / $totalMatches) * 100 : 0
        ];
    }

    /**
     * Validate group stage tournament
     */
    public function validateTournament(array $tournament): array
    {
        $errors = [];

        // Check required fields
        $requiredFields = ['format', 'group_count', 'advance_count', 'group_stages'];
        foreach ($requiredFields as $field) {
            if (!isset($tournament[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate format
        if (isset($tournament['format']) && $tournament['format'] !== 'group_stage') {
            $errors[] = "Invalid format: {$tournament['format']}";
        }

        // Validate groups
        if (isset($tournament['group_stages'])) {
            foreach ($tournament['group_stages'] as $groupIndex => $group) {
                if (!isset($group['matches']) || !is_array($group['matches'])) {
                    $errors[] = "Group {$groupIndex} missing matches";
                }

                if (!isset($group['format'])) {
                    $errors[] = "Group {$groupIndex} missing format";
                }
            }
        }

        return $errors;
    }
}