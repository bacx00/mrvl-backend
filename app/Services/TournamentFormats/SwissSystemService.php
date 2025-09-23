<?php

namespace App\Services\TournamentFormats;

use Illuminate\Support\Facades\Log;

class SwissSystemService
{
    /**
     * Generate Swiss System tournament
     *
     * @param array $teams Array of team objects with id, name, seed
     * @param int $rounds Number of rounds to play
     * @param string $bestOfFormat (BO1, BO3, BO5, etc.)
     * @return array Tournament structure with dynamic round generation
     */
    public function generateTournament(array $teams, int $rounds = null, string $bestOfFormat = 'BO1'): array
    {
        Log::info('Generating Swiss System tournament', [
            'team_count' => count($teams),
            'rounds' => $rounds,
            'best_of_format' => $bestOfFormat
        ]);

        // Validate input
        if (count($teams) < 4) {
            throw new \InvalidArgumentException('At least 4 teams required for Swiss System');
        }

        // Calculate recommended rounds if not provided
        if ($rounds === null) {
            $rounds = $this->calculateRecommendedRounds(count($teams));
        }

        // Initialize player records
        $playerRecords = $this->initializePlayerRecords($teams);

        // Generate first round
        $firstRoundMatches = $this->generateFirstRound($teams, $bestOfFormat);

        return [
            'format' => 'swiss_system',
            'total_rounds' => $rounds,
            'current_round' => 1,
            'team_count' => count($teams),
            'player_records' => $playerRecords,
            'rounds' => [
                1 => [
                    'round' => 1,
                    'matches' => $firstRoundMatches,
                    'status' => 'pending'
                ]
            ],
            'pairings_history' => [],
            'standings' => [],
            'best_of_format' => $bestOfFormat,
            'tiebreaker_system' => 'buchholz'
        ];
    }

    /**
     * Generate first round with random or seeded pairings
     */
    private function generateFirstRound(array $teams, string $bestOfFormat): array
    {
        $matches = [];
        $shuffledTeams = $teams;

        // Sort by seed for first round
        usort($shuffledTeams, function($a, $b) {
            return ($a['seed'] ?? 999) <=> ($b['seed'] ?? 999);
        });

        $matchId = 1;

        // Pair teams: 1 vs (n/2+1), 2 vs (n/2+2), etc.
        $halfPoint = count($shuffledTeams) / 2;

        for ($i = 0; $i < $halfPoint; $i++) {
            $team1 = $shuffledTeams[$i];
            $team2 = $shuffledTeams[$i + $halfPoint];

            $matches[] = [
                'id' => "SW_R1_M{$matchId}",
                'round' => 1,
                'position' => $matchId,
                'team1' => $team1,
                'team2' => $team2,
                'winner' => null,
                'loser' => null,
                'result' => null, // 1-0, 0-1, or 0.5-0.5 for draws
                'status' => 'pending',
                'best_of_format' => $bestOfFormat,
                'scores' => [],
                'created_at' => now(),
                'updated_at' => now()
            ];

            $matchId++;
        }

        return $matches;
    }

    /**
     * Generate next round based on current standings
     */
    public function generateNextRound(array &$tournament): array
    {
        $currentRound = $tournament['current_round'];
        $nextRound = $currentRound + 1;

        Log::info('Generating Swiss round', [
            'current_round' => $currentRound,
            'next_round' => $nextRound
        ]);

        if ($nextRound > $tournament['total_rounds']) {
            throw new \InvalidArgumentException('Tournament is already complete');
        }

        // Update standings and records
        $this->updateStandings($tournament);

        // Generate pairings for next round
        $pairings = $this->generateSwissPairings($tournament);

        // Create matches
        $matches = [];
        $matchId = 1;

        foreach ($pairings as $pairing) {
            $matches[] = [
                'id' => "SW_R{$nextRound}_M{$matchId}",
                'round' => $nextRound,
                'position' => $matchId,
                'team1' => $pairing['team1'],
                'team2' => $pairing['team2'],
                'winner' => null,
                'loser' => null,
                'result' => null,
                'status' => 'pending',
                'best_of_format' => $tournament['best_of_format'],
                'scores' => [],
                'created_at' => now(),
                'updated_at' => now()
            ];

            $matchId++;
        }

        // Add round to tournament
        $tournament['rounds'][$nextRound] = [
            'round' => $nextRound,
            'matches' => $matches,
            'status' => 'pending'
        ];

        $tournament['current_round'] = $nextRound;

        return $tournament;
    }

    /**
     * Generate Swiss pairings based on current standings
     */
    private function generateSwissPairings(array $tournament): array
    {
        $standings = $tournament['standings'];
        $pairingsHistory = $tournament['pairings_history'];
        $pairings = [];

        // Group players by score
        $scoreGroups = [];
        foreach ($standings as $player) {
            $score = $player['points'];
            if (!isset($scoreGroups[$score])) {
                $scoreGroups[$score] = [];
            }
            $scoreGroups[$score][] = $player;
        }

        // Sort score groups descending
        krsort($scoreGroups);

        $unpaired = [];
        foreach ($scoreGroups as $score => $players) {
            $unpaired = array_merge($unpaired, $players);
        }

        // Pair players using Swiss pairing algorithm
        while (count($unpaired) >= 2) {
            $player1 = array_shift($unpaired);
            $opponent = $this->findBestOpponent($player1, $unpaired, $pairingsHistory);

            if ($opponent === null) {
                // No valid opponent found, try with next player
                array_unshift($unpaired, $player1);
                if (count($unpaired) < 2) {
                    break;
                }
                continue;
            }

            // Remove opponent from unpaired list
            $opponentIndex = array_search($opponent, $unpaired);
            if ($opponentIndex !== false) {
                unset($unpaired[$opponentIndex]);
                $unpaired = array_values($unpaired);
            }

            $pairings[] = [
                'team1' => $this->getTeamById($tournament['player_records'], $player1['team_id']),
                'team2' => $this->getTeamById($tournament['player_records'], $opponent['team_id'])
            ];

            // Record pairing
            $pairingKey = $this->generatePairingKey($player1['team_id'], $opponent['team_id']);
            $tournament['pairings_history'][] = $pairingKey;
        }

        // Handle bye if odd number of players
        if (count($unpaired) === 1) {
            $byePlayer = $unpaired[0];
            $this->awardBye($tournament, $byePlayer['team_id']);
        }

        return $pairings;
    }

    /**
     * Find best opponent for a player
     */
    private function findBestOpponent(array $player, array $candidates, array $pairingsHistory): ?array
    {
        // Sort candidates by score (closest score first)
        usort($candidates, function($a, $b) use ($player) {
            $scoreDiffA = abs($a['points'] - $player['points']);
            $scoreDiffB = abs($b['points'] - $player['points']);
            return $scoreDiffA <=> $scoreDiffB;
        });

        // Find first valid opponent (not played before)
        foreach ($candidates as $candidate) {
            $pairingKey = $this->generatePairingKey($player['team_id'], $candidate['team_id']);
            if (!in_array($pairingKey, $pairingsHistory)) {
                return $candidate;
            }
        }

        // If no unpaired opponent found, return closest score (allow rematch)
        return $candidates[0] ?? null;
    }

    /**
     * Update match result and player records
     */
    public function updateMatchResult(array &$tournament, string $matchId, array $winner, ?array $loser = null, bool $isDraw = false): array
    {
        Log::info('Updating Swiss match result', [
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
                    $match['result'] = $isDraw ? '0.5-0.5' : '1-0';
                    $match['status'] = 'completed';
                    $match['updated_at'] = now();

                    // Update player records
                    if ($isDraw) {
                        $this->updatePlayerRecord($tournament, $match['team1']['id'], 0.5, 0.5);
                        $this->updatePlayerRecord($tournament, $match['team2']['id'], 0.5, 0.5);
                    } else {
                        $this->updatePlayerRecord($tournament, $winner['id'], 1, 0);
                        $this->updatePlayerRecord($tournament, $loser['id'], 0, 1);
                    }

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
    private function updatePlayerRecord(array &$tournament, int $teamId, float $points, float $losses): void
    {
        foreach ($tournament['player_records'] as &$record) {
            if ($record['team_id'] === $teamId) {
                $record['points'] += $points;
                $record['matches_played']++;
                if ($points === 1) {
                    $record['wins']++;
                } elseif ($points === 0.5) {
                    $record['draws']++;
                } else {
                    $record['losses']++;
                }
                break;
            }
        }
    }

    /**
     * Award bye to a player
     */
    private function awardBye(array &$tournament, int $teamId): void
    {
        $this->updatePlayerRecord($tournament, $teamId, 1, 0); // Bye = 1 point

        Log::info('Bye awarded', ['team_id' => $teamId]);
    }

    /**
     * Update tournament standings with tiebreakers
     */
    private function updateStandings(array &$tournament): void
    {
        $standings = [];

        // Calculate basic standings
        foreach ($tournament['player_records'] as $record) {
            $standings[] = $record;
        }

        // Calculate tiebreakers
        foreach ($standings as &$standing) {
            $standing['tiebreakers'] = $this->calculateTiebreakers($tournament, $standing['team_id']);
        }

        // Sort by points, then tiebreakers
        usort($standings, function($a, $b) {
            // Primary: Points
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            // Secondary: Buchholz (opponent score sum)
            if ($a['tiebreakers']['buchholz'] !== $b['tiebreakers']['buchholz']) {
                return $b['tiebreakers']['buchholz'] <=> $a['tiebreakers']['buchholz'];
            }

            // Tertiary: Sonneborn-Berger
            if ($a['tiebreakers']['sonneborn_berger'] !== $b['tiebreakers']['sonneborn_berger']) {
                return $b['tiebreakers']['sonneborn_berger'] <=> $a['tiebreakers']['sonneborn_berger'];
            }

            // Final: Initial seed
            return ($a['seed'] ?? 999) <=> ($b['seed'] ?? 999);
        });

        // Add rank
        foreach ($standings as $index => &$standing) {
            $standing['rank'] = $index + 1;
        }

        $tournament['standings'] = $standings;
    }

    /**
     * Calculate tiebreaker scores
     */
    private function calculateTiebreakers(array $tournament, int $teamId): array
    {
        $opponentIds = $this->getOpponentIds($tournament, $teamId);
        $buchholz = 0; // Sum of opponent scores
        $sonnebornBerger = 0; // Weighted score based on results

        foreach ($opponentIds as $opponentResult) {
            $opponentRecord = $this->getPlayerRecord($tournament, $opponentResult['opponent_id']);

            if ($opponentRecord) {
                $buchholz += $opponentRecord['points'];

                // Sonneborn-Berger: full points for wins against strong opponents
                if ($opponentResult['result'] === 1) {
                    $sonnebornBerger += $opponentRecord['points'];
                } elseif ($opponentResult['result'] === 0.5) {
                    $sonnebornBerger += $opponentRecord['points'] * 0.5;
                }
            }
        }

        return [
            'buchholz' => $buchholz,
            'sonneborn_berger' => $sonnebornBerger,
            'opponents_count' => count($opponentIds)
        ];
    }

    /**
     * Get opponent IDs and results for a team
     */
    private function getOpponentIds(array $tournament, int $teamId): array
    {
        $opponents = [];

        foreach ($tournament['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                if ($match['status'] === 'completed') {
                    $opponentId = null;
                    $result = null;

                    if ($match['team1']['id'] === $teamId) {
                        $opponentId = $match['team2']['id'];
                        if ($match['result'] === '1-0') {
                            $result = ($match['winner']['id'] === $teamId) ? 1 : 0;
                        } elseif ($match['result'] === '0.5-0.5') {
                            $result = 0.5;
                        }
                    } elseif ($match['team2']['id'] === $teamId) {
                        $opponentId = $match['team1']['id'];
                        if ($match['result'] === '1-0') {
                            $result = ($match['winner']['id'] === $teamId) ? 1 : 0;
                        } elseif ($match['result'] === '0.5-0.5') {
                            $result = 0.5;
                        }
                    }

                    if ($opponentId !== null) {
                        $opponents[] = [
                            'opponent_id' => $opponentId,
                            'result' => $result
                        ];
                    }
                }
            }
        }

        return $opponents;
    }

    /**
     * Helper methods
     */
    private function calculateRecommendedRounds(int $teamCount): int
    {
        // Swiss system typically uses log2(n) + 1 rounds
        return min(ceil(log($teamCount, 2)) + 1, $teamCount - 1);
    }

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
                'seed' => $team['seed'] ?? 999,
                'rank' => null
            ];
        }

        return $records;
    }

    private function generatePairingKey(int $team1Id, int $team2Id): string
    {
        $ids = [$team1Id, $team2Id];
        sort($ids);
        return implode('-', $ids);
    }

    private function getTeamById(array $playerRecords, int $teamId): ?array
    {
        foreach ($playerRecords as $record) {
            if ($record['team_id'] === $teamId) {
                return $record['team'];
            }
        }
        return null;
    }

    private function getPlayerRecord(array $tournament, int $teamId): ?array
    {
        foreach ($tournament['player_records'] as $record) {
            if ($record['team_id'] === $teamId) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Check if tournament is complete
     */
    public function isTournamentComplete(array $tournament): bool
    {
        return $tournament['current_round'] >= $tournament['total_rounds'] &&
               $this->isRoundComplete($tournament, $tournament['current_round']);
    }

    /**
     * Check if a round is complete
     */
    public function isRoundComplete(array $tournament, int $round): bool
    {
        if (!isset($tournament['rounds'][$round])) {
            return false;
        }

        foreach ($tournament['rounds'][$round]['matches'] as $match) {
            if ($match['status'] !== 'completed') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get final standings
     */
    public function getFinalStandings(array $tournament): array
    {
        if (!$this->isTournamentComplete($tournament)) {
            throw new \InvalidArgumentException('Tournament is not complete');
        }

        return $tournament['standings'];
    }

    /**
     * Validate Swiss tournament
     */
    public function validateTournament(array $tournament): array
    {
        $errors = [];

        // Check required fields
        $requiredFields = ['format', 'total_rounds', 'current_round', 'player_records', 'rounds'];
        foreach ($requiredFields as $field) {
            if (!isset($tournament[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate format
        if (isset($tournament['format']) && $tournament['format'] !== 'swiss_system') {
            $errors[] = "Invalid format: {$tournament['format']}";
        }

        // Validate rounds
        if (isset($tournament['rounds'])) {
            foreach ($tournament['rounds'] as $roundNum => $round) {
                if (!isset($round['matches']) || !is_array($round['matches'])) {
                    $errors[] = "Round {$roundNum} missing matches";
                    continue;
                }

                foreach ($round['matches'] as $match) {
                    if (!isset($match['id'])) {
                        $errors[] = "Match in round {$roundNum} missing ID";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Get tournament statistics
     */
    public function getTournamentStats(array $tournament): array
    {
        $stats = [
            'total_teams' => count($tournament['player_records']),
            'rounds_played' => 0,
            'matches_played' => 0,
            'matches_remaining' => 0,
            'completion_percentage' => 0
        ];

        $totalMatches = 0;
        $completedMatches = 0;

        foreach ($tournament['rounds'] as $round) {
            if (isset($round['matches'])) {
                $roundComplete = true;
                foreach ($round['matches'] as $match) {
                    $totalMatches++;
                    if ($match['status'] === 'completed') {
                        $completedMatches++;
                    } else {
                        $roundComplete = false;
                    }
                }

                if ($roundComplete) {
                    $stats['rounds_played']++;
                }
            }
        }

        $stats['matches_played'] = $completedMatches;
        $stats['matches_remaining'] = $totalMatches - $completedMatches;
        $stats['completion_percentage'] = $totalMatches > 0 ? ($completedMatches / $totalMatches) * 100 : 0;

        return $stats;
    }
}