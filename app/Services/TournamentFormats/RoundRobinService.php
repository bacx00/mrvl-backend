<?php

namespace App\Services\TournamentFormats;

use Illuminate\Support\Facades\Log;

class RoundRobinService
{
    /**
     * Generate Round Robin tournament
     *
     * @param array $teams Array of team objects with id, name, seed
     * @param string $bestOfFormat (BO1, BO3, BO5, etc.)
     * @param bool $doubleRoundRobin Play each pairing twice
     * @return array Tournament structure with all matches pre-generated
     */
    public function generateTournament(array $teams, string $bestOfFormat = 'BO1', bool $doubleRoundRobin = false): array
    {
        Log::info('Generating Round Robin tournament', [
            'team_count' => count($teams),
            'best_of_format' => $bestOfFormat,
            'double_round_robin' => $doubleRoundRobin
        ]);

        // Validate input
        if (count($teams) < 3) {
            throw new \InvalidArgumentException('At least 3 teams required for Round Robin');
        }

        $teamCount = count($teams);
        $totalRounds = $doubleRoundRobin ? ($teamCount - 1) * 2 : ($teamCount - 1);
        $matchesPerRound = $teamCount % 2 === 0 ? $teamCount / 2 : ($teamCount - 1) / 2;

        // Initialize player records
        $playerRecords = $this->initializePlayerRecords($teams);

        // Generate all matches
        $rounds = $this->generateAllRounds($teams, $bestOfFormat, $doubleRoundRobin);

        return [
            'format' => 'round_robin',
            'double_round_robin' => $doubleRoundRobin,
            'total_rounds' => $totalRounds,
            'current_round' => 1,
            'team_count' => $teamCount,
            'matches_per_round' => $matchesPerRound,
            'player_records' => $playerRecords,
            'rounds' => $rounds,
            'standings' => [],
            'head_to_head' => [],
            'best_of_format' => $bestOfFormat
        ];
    }

    /**
     * Generate all rounds using round-robin algorithm
     */
    private function generateAllRounds(array $teams, string $bestOfFormat, bool $doubleRoundRobin): array
    {
        $rounds = [];
        $teamCount = count($teams);

        // Add dummy team for odd number of teams
        $workingTeams = $teams;
        if ($teamCount % 2 !== 0) {
            $workingTeams[] = ['id' => 'bye', 'name' => 'BYE', 'bye' => true];
        }

        $numTeams = count($workingTeams);
        $totalRounds = $doubleRoundRobin ? ($numTeams - 1) * 2 : ($numTeams - 1);

        // Generate first leg
        for ($round = 1; $round <= $numTeams - 1; $round++) {
            $rounds[$round] = [
                'round' => $round,
                'round_name' => "Round {$round}",
                'matches' => $this->generateRoundMatches($workingTeams, $round, $bestOfFormat),
                'status' => 'pending'
            ];
        }

        // Generate second leg if double round robin
        if ($doubleRoundRobin) {
            for ($round = 1; $round <= $numTeams - 1; $round++) {
                $secondLegRound = $round + ($numTeams - 1);
                $rounds[$secondLegRound] = [
                    'round' => $secondLegRound,
                    'round_name' => "Round {$secondLegRound}",
                    'matches' => $this->generateRoundMatches($workingTeams, $round, $bestOfFormat, true),
                    'status' => 'pending'
                ];
            }
        }

        return $rounds;
    }

    /**
     * Generate matches for a specific round using circle method
     */
    private function generateRoundMatches(array $teams, int $round, string $bestOfFormat, bool $reverseHome = false): array
    {
        $matches = [];
        $numTeams = count($teams);
        $matchId = 1;

        // Implement circle method for round-robin scheduling
        $pairings = $this->generateRoundPairings($teams, $round, $reverseHome);

        foreach ($pairings as $pairing) {
            $team1 = $pairing['team1'];
            $team2 = $pairing['team2'];

            // Skip if either team is a bye
            if (isset($team1['bye']) || isset($team2['bye'])) {
                continue;
            }

            $matches[] = [
                'id' => "RR_R{$round}_M{$matchId}",
                'round' => $round,
                'position' => $matchId,
                'team1' => $team1,
                'team2' => $team2,
                'winner' => null,
                'loser' => null,
                'draw' => false,
                'result' => null, // Will store detailed result
                'status' => 'pending',
                'best_of_format' => $bestOfFormat,
                'scores' => [],
                'head_to_head_key' => $this->generateHeadToHeadKey($team1['id'], $team2['id']),
                'created_at' => now(),
                'updated_at' => now()
            ];

            $matchId++;
        }

        return $matches;
    }

    /**
     * Generate pairings for a round using circle method
     */
    private function generateRoundPairings(array $teams, int $round, bool $reverseHome = false): array
    {
        $numTeams = count($teams);
        $pairings = [];

        // Circle method: fix one team, rotate others
        $fixedTeam = $teams[0];
        $rotatingTeams = array_slice($teams, 1);

        // Rotate teams based on round
        $rotationAmount = ($round - 1) % ($numTeams - 1);
        for ($i = 0; $i < $rotationAmount; $i++) {
            $first = array_shift($rotatingTeams);
            array_push($rotatingTeams, $first);
        }

        // Create pairings
        $half = count($rotatingTeams) / 2;

        // Fixed team plays against middle team
        $opponentIndex = intval($half) - 1;
        if ($opponentIndex >= 0 && $opponentIndex < count($rotatingTeams)) {
            $opponent = $rotatingTeams[$opponentIndex];

            $pairings[] = [
                'team1' => $reverseHome ? $opponent : $fixedTeam,
                'team2' => $reverseHome ? $fixedTeam : $opponent
            ];
        }

        // Pair remaining teams
        for ($i = 0; $i < intval($half) - 1; $i++) {
            $team1 = $rotatingTeams[$i];
            $team2 = $rotatingTeams[count($rotatingTeams) - 1 - $i];

            $pairings[] = [
                'team1' => $reverseHome ? $team2 : $team1,
                'team2' => $reverseHome ? $team1 : $team2
            ];
        }

        return $pairings;
    }

    /**
     * Update match result
     */
    public function updateMatchResult(array &$tournament, string $matchId, array $winner, ?array $loser = null, bool $isDraw = false): array
    {
        Log::info('Updating Round Robin match result', [
            'match_id' => $matchId,
            'winner' => $winner['name'] ?? 'Unknown',
            'is_draw' => $isDraw
        ]);

        // Find and update the match
        $matchFound = false;
        foreach ($tournament['rounds'] as $roundNum => &$round) {
            foreach ($round['matches'] as &$match) {
                if ($match['id'] === $matchId) {
                    $match['winner'] = $isDraw ? null : $winner;
                    $match['loser'] = $isDraw ? null : $loser;
                    $match['draw'] = $isDraw;
                    $match['status'] = 'completed';
                    $match['updated_at'] = now();

                    // Update player records
                    if ($isDraw) {
                        $this->updatePlayerRecord($tournament, $match['team1']['id'], 0, 0, 1);
                        $this->updatePlayerRecord($tournament, $match['team2']['id'], 0, 0, 1);
                    } else {
                        $this->updatePlayerRecord($tournament, $winner['id'], 1, 0, 0);
                        $this->updatePlayerRecord($tournament, $loser['id'], 0, 1, 0);
                    }

                    // Update head-to-head records
                    $this->updateHeadToHead($tournament, $match);

                    $matchFound = true;
                    break 2;
                }
            }
        }

        if (!$matchFound) {
            throw new \InvalidArgumentException("Match {$matchId} not found");
        }

        // Update standings
        $this->updateStandings($tournament);

        return $tournament;
    }

    /**
     * Update player record
     */
    private function updatePlayerRecord(array &$tournament, int $teamId, int $wins, int $losses, int $draws): void
    {
        foreach ($tournament['player_records'] as &$record) {
            if ($record['team_id'] === $teamId) {
                $record['wins'] += $wins;
                $record['losses'] += $losses;
                $record['draws'] += $draws;
                $record['matches_played']++;
                $record['points'] = ($record['wins'] * 3) + ($record['draws'] * 1); // 3 points for win, 1 for draw
                break;
            }
        }
    }

    /**
     * Update head-to-head records
     */
    private function updateHeadToHead(array &$tournament, array $match): void
    {
        $h2hKey = $match['head_to_head_key'];

        if (!isset($tournament['head_to_head'][$h2hKey])) {
            $tournament['head_to_head'][$h2hKey] = [
                'team1_id' => $match['team1']['id'],
                'team2_id' => $match['team2']['id'],
                'team1_wins' => 0,
                'team2_wins' => 0,
                'draws' => 0,
                'matches' => []
            ];
        }

        $h2h = &$tournament['head_to_head'][$h2hKey];

        // Update head-to-head record
        if ($match['draw']) {
            $h2h['draws']++;
        } elseif ($match['winner']['id'] === $match['team1']['id']) {
            $h2h['team1_wins']++;
        } else {
            $h2h['team2_wins']++;
        }

        // Add match to history
        $h2h['matches'][] = [
            'match_id' => $match['id'],
            'round' => $match['round'],
            'winner_id' => $match['winner']['id'] ?? null,
            'draw' => $match['draw'],
            'played_at' => $match['updated_at']
        ];
    }

    /**
     * Update tournament standings
     */
    private function updateStandings(array &$tournament): void
    {
        $standings = $tournament['player_records'];

        // Sort by points, then by tiebreakers
        usort($standings, function($a, $b) {
            // Primary: Points
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            // Secondary: Goal difference (if available)
            $gdA = ($a['goals_for'] ?? 0) - ($a['goals_against'] ?? 0);
            $gdB = ($b['goals_for'] ?? 0) - ($b['goals_against'] ?? 0);
            if ($gdA !== $gdB) {
                return $gdB <=> $gdA;
            }

            // Tertiary: Goals for
            if (($a['goals_for'] ?? 0) !== ($b['goals_for'] ?? 0)) {
                return ($b['goals_for'] ?? 0) <=> ($a['goals_for'] ?? 0);
            }

            // Quaternary: Head-to-head
            $h2h = $this->getHeadToHeadComparison($tournament, $a['team_id'], $b['team_id']);
            if ($h2h !== 0) {
                return $h2h;
            }

            // Final: Wins
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }

            // Last resort: Initial seed
            return ($a['seed'] ?? 999) <=> ($b['seed'] ?? 999);
        });

        // Add rankings
        $previousPoints = null;
        $previousRank = 0;
        foreach ($standings as $index => &$standing) {
            if ($standing['points'] !== $previousPoints) {
                $standing['rank'] = $index + 1;
                $previousRank = $index + 1;
            } else {
                $standing['rank'] = $previousRank;
            }
            $previousPoints = $standing['points'];
        }

        $tournament['standings'] = $standings;
    }

    /**
     * Get head-to-head comparison between two teams
     */
    private function getHeadToHeadComparison(array $tournament, int $team1Id, int $team2Id): int
    {
        $h2hKey = $this->generateHeadToHeadKey($team1Id, $team2Id);

        if (!isset($tournament['head_to_head'][$h2hKey])) {
            return 0; // No head-to-head data
        }

        $h2h = $tournament['head_to_head'][$h2hKey];

        if ($h2h['team1_id'] === $team1Id) {
            return $h2h['team1_wins'] <=> $h2h['team2_wins'];
        } else {
            return $h2h['team2_wins'] <=> $h2h['team1_wins'];
        }
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
                'goals_for' => 0,
                'goals_against' => 0,
                'seed' => $team['seed'] ?? 999,
                'rank' => null
            ];
        }

        return $records;
    }

    private function generateHeadToHeadKey(int $team1Id, int $team2Id): string
    {
        $ids = [$team1Id, $team2Id];
        sort($ids);
        return implode('-', $ids);
    }

    /**
     * Check if tournament is complete
     */
    public function isTournamentComplete(array $tournament): bool
    {
        foreach ($tournament['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                if ($match['status'] !== 'completed') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Get matches for a specific round
     */
    public function getRoundMatches(array $tournament, int $round): array
    {
        return $tournament['rounds'][$round]['matches'] ?? [];
    }

    /**
     * Get next pending matches
     */
    public function getNextMatches(array $tournament, int $limit = null): array
    {
        $nextMatches = [];

        foreach ($tournament['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                if ($match['status'] === 'pending') {
                    $nextMatches[] = $match;
                    if ($limit && count($nextMatches) >= $limit) {
                        return $nextMatches;
                    }
                }
            }
        }

        return $nextMatches;
    }

    /**
     * Get tournament progress
     */
    public function getTournamentProgress(array $tournament): array
    {
        $totalMatches = 0;
        $completedMatches = 0;

        foreach ($tournament['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                $totalMatches++;
                if ($match['status'] === 'completed') {
                    $completedMatches++;
                }
            }
        }

        return [
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'remaining_matches' => $totalMatches - $completedMatches,
            'completion_percentage' => $totalMatches > 0 ? ($completedMatches / $totalMatches) * 100 : 0,
            'is_complete' => $completedMatches === $totalMatches
        ];
    }

    /**
     * Get detailed standings with additional stats
     */
    public function getDetailedStandings(array $tournament): array
    {
        $standings = $tournament['standings'];

        foreach ($standings as &$standing) {
            // Calculate additional stats
            $standing['points_per_game'] = $standing['matches_played'] > 0
                ? round($standing['points'] / $standing['matches_played'], 2)
                : 0;

            $standing['win_percentage'] = $standing['matches_played'] > 0
                ? round(($standing['wins'] / $standing['matches_played']) * 100, 1)
                : 0;

            $standing['goal_difference'] = $standing['goals_for'] - $standing['goals_against'];

            // Add form (last 5 results)
            $standing['form'] = $this->getTeamForm($tournament, $standing['team_id'], 5);
        }

        return $standings;
    }

    /**
     * Get team's recent form
     */
    private function getTeamForm(array $tournament, int $teamId, int $matchCount = 5): array
    {
        $results = [];

        // Collect all completed matches for this team
        foreach ($tournament['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                if ($match['status'] === 'completed' &&
                    ($match['team1']['id'] === $teamId || $match['team2']['id'] === $teamId)) {

                    if ($match['draw']) {
                        $results[] = 'D';
                    } elseif ($match['winner']['id'] === $teamId) {
                        $results[] = 'W';
                    } else {
                        $results[] = 'L';
                    }
                }
            }
        }

        // Return last N results
        return array_slice($results, -$matchCount);
    }

    /**
     * Validate round robin tournament
     */
    public function validateTournament(array $tournament): array
    {
        $errors = [];

        // Check required fields
        $requiredFields = ['format', 'total_rounds', 'team_count', 'player_records', 'rounds'];
        foreach ($requiredFields as $field) {
            if (!isset($tournament[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate format
        if (isset($tournament['format']) && $tournament['format'] !== 'round_robin') {
            $errors[] = "Invalid format: {$tournament['format']}";
        }

        // Check that all teams play each other
        if (isset($tournament['team_count']) && isset($tournament['rounds'])) {
            $expectedRounds = $tournament['double_round_robin'] ?
                ($tournament['team_count'] - 1) * 2 :
                ($tournament['team_count'] - 1);

            if (count($tournament['rounds']) !== $expectedRounds) {
                $errors[] = "Expected {$expectedRounds} rounds, found " . count($tournament['rounds']);
            }
        }

        return $errors;
    }

    /**
     * Generate tournament schedule with dates
     */
    public function generateSchedule(array $tournament, \DateTime $startDate, int $daysPerRound = 7): array
    {
        $schedule = [];
        $currentDate = clone $startDate;

        foreach ($tournament['rounds'] as $roundNum => $round) {
            $schedule[$roundNum] = [
                'round' => $roundNum,
                'date' => $currentDate->format('Y-m-d'),
                'matches' => $round['matches']
            ];

            $currentDate->add(new \DateInterval("P{$daysPerRound}D"));
        }

        return $schedule;
    }
}