<?php

namespace App\Services\TournamentFormats;

use Illuminate\Support\Facades\Log;

class SingleEliminationService
{
    /**
     * Generate single elimination bracket
     *
     * @param array $teams Array of team objects with id, name, seed
     * @param string $bestOfFormat (BO1, BO3, BO5, etc.)
     * @return array Bracket structure with matches and rounds
     */
    public function generateBracket(array $teams, string $bestOfFormat = 'BO3'): array
    {
        Log::info('Generating Single Elimination bracket', [
            'team_count' => count($teams),
            'best_of_format' => $bestOfFormat
        ]);

        // Validate input
        if (count($teams) < 2) {
            throw new \InvalidArgumentException('At least 2 teams required for single elimination');
        }

        // Sort teams by seed (1 = best seed)
        usort($teams, function($a, $b) {
            return ($a['seed'] ?? 999) <=> ($b['seed'] ?? 999);
        });

        // Calculate bracket size (next power of 2)
        $bracketSize = $this->getNextPowerOfTwo(count($teams));
        $totalRounds = log($bracketSize, 2);

        // Generate first round with proper seeding and byes
        $matches = $this->generateFirstRound($teams, $bracketSize, $bestOfFormat);

        // Generate subsequent rounds
        for ($round = 2; $round <= $totalRounds; $round++) {
            $matches = array_merge($matches, $this->generateRound($round, $totalRounds, $bestOfFormat, count($matches)));
        }

        return [
            'format' => 'single_elimination',
            'bracket_size' => $bracketSize,
            'total_rounds' => $totalRounds,
            'matches' => $matches,
            'teams' => $teams,
            'best_of_format' => $bestOfFormat
        ];
    }

    /**
     * Generate first round with proper seeding
     */
    private function generateFirstRound(array $teams, int $bracketSize, string $bestOfFormat): array
    {
        $matches = [];
        $matchId = 1;

        // Create seeding pairs for single elimination
        $seedPairs = $this->generateSeedPairs($bracketSize);

        foreach ($seedPairs as $pair) {
            $team1 = $this->getTeamBySeed($teams, $pair[0]);
            $team2 = $this->getTeamBySeed($teams, $pair[1]);

            $matches[] = [
                'id' => "SE_R1_M{$matchId}",
                'round' => 1,
                'round_name' => $this->getRoundName(1, log($bracketSize, 2)),
                'position' => $matchId,
                'team1' => $team1,
                'team2' => $team2,
                'winner' => null,
                'loser' => null,
                'status' => ($team1 && $team2) ? 'pending' : 'bye',
                'best_of_format' => $bestOfFormat,
                'scores' => [],
                'next_match_id' => "SE_R2_M" . ceil($matchId / 2),
                'bracket_type' => 'winner',
                'created_at' => now(),
                'updated_at' => now()
            ];

            $matchId++;
        }

        return $matches;
    }

    /**
     * Generate subsequent rounds
     */
    private function generateRound(int $round, int $totalRounds, string $bestOfFormat, int $previousMatchCount): array
    {
        $matches = [];
        $matchesInRound = $previousMatchCount / 2;

        for ($i = 1; $i <= $matchesInRound; $i++) {
            $nextMatchId = $round < $totalRounds ? "SE_R" . ($round + 1) . "_M" . ceil($i / 2) : null;

            $matches[] = [
                'id' => "SE_R{$round}_M{$i}",
                'round' => $round,
                'round_name' => $this->getRoundName($round, $totalRounds),
                'position' => $i,
                'team1' => null, // Will be filled by winner advancement
                'team2' => null, // Will be filled by winner advancement
                'winner' => null,
                'loser' => null,
                'status' => 'waiting',
                'best_of_format' => $bestOfFormat,
                'scores' => [],
                'next_match_id' => $nextMatchId,
                'bracket_type' => 'winner',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        return $matches;
    }

    /**
     * Generate proper seeding pairs for single elimination
     */
    private function generateSeedPairs(int $bracketSize): array
    {
        $pairs = [];
        $seeds = range(1, $bracketSize);

        // Classic tournament seeding: 1 vs last, 2 vs second-last, etc.
        for ($i = 0; $i < $bracketSize / 2; $i++) {
            $pairs[] = [$seeds[$i], $seeds[$bracketSize - 1 - $i]];
        }

        return $pairs;
    }

    /**
     * Get team by seed number
     */
    private function getTeamBySeed(array $teams, int $seed): ?array
    {
        foreach ($teams as $team) {
            if (($team['seed'] ?? 999) === $seed) {
                return $team;
            }
        }
        return null; // Bye
    }

    /**
     * Get next power of 2
     */
    private function getNextPowerOfTwo(int $number): int
    {
        $power = 1;
        while ($power < $number) {
            $power *= 2;
        }
        return $power;
    }

    /**
     * Get round name based on position
     */
    private function getRoundName(int $round, int $totalRounds): string
    {
        $roundsFromEnd = $totalRounds - $round + 1;

        switch ($roundsFromEnd) {
            case 1:
                return 'Finals';
            case 2:
                return 'Semifinals';
            case 3:
                return 'Quarterfinals';
            case 4:
                return 'Round of 16';
            case 5:
                return 'Round of 32';
            case 6:
                return 'Round of 64';
            default:
                return "Round {$round}";
        }
    }

    /**
     * Advance winner to next round
     */
    public function advanceWinner(array &$bracket, string $matchId, array $winner): array
    {
        Log::info('Advancing winner in Single Elimination', [
            'match_id' => $matchId,
            'winner' => $winner['name'] ?? 'Unknown'
        ]);

        // Find the match
        $matchIndex = null;
        foreach ($bracket['matches'] as $index => $match) {
            if ($match['id'] === $matchId) {
                $matchIndex = $index;
                break;
            }
        }

        if ($matchIndex === null) {
            throw new \InvalidArgumentException("Match {$matchId} not found");
        }

        $match = &$bracket['matches'][$matchIndex];
        $match['winner'] = $winner;
        $match['status'] = 'completed';
        $match['updated_at'] = now();

        // Set loser
        if ($match['team1'] && $match['team2']) {
            $match['loser'] = ($match['team1']['id'] === $winner['id']) ? $match['team2'] : $match['team1'];
        }

        // Advance to next match if it exists
        if ($match['next_match_id']) {
            $this->advanceToNextMatch($bracket, $match['next_match_id'], $winner, $matchId);
        }

        return $bracket;
    }

    /**
     * Advance team to next match
     */
    private function advanceToNextMatch(array &$bracket, string $nextMatchId, array $winner, string $sourceMatchId): void
    {
        foreach ($bracket['matches'] as &$match) {
            if ($match['id'] === $nextMatchId) {
                // Determine which slot to fill based on source match position
                $sourcePosition = intval(explode('_M', $sourceMatchId)[1]);

                if ($sourcePosition % 2 === 1) {
                    // Odd position goes to team1
                    $match['team1'] = $winner;
                } else {
                    // Even position goes to team2
                    $match['team2'] = $winner;
                }

                // Update match status
                if ($match['team1'] && $match['team2']) {
                    $match['status'] = 'pending';
                } elseif ($match['team1'] || $match['team2']) {
                    $match['status'] = 'waiting';
                }

                $match['updated_at'] = now();
                break;
            }
        }
    }

    /**
     * Get bracket standings/results
     */
    public function getStandings(array $bracket): array
    {
        $standings = [];
        $totalRounds = $bracket['total_rounds'];

        foreach ($bracket['matches'] as $match) {
            if ($match['status'] === 'completed') {
                // Winner placement
                if ($match['winner']) {
                    $placement = $this->getPlacementByRound($match['round'], $totalRounds, true);
                    if (!isset($standings[$placement])) {
                        $standings[$placement] = [];
                    }
                    if (!in_array($match['winner'], $standings[$placement])) {
                        $standings[$placement][] = $match['winner'];
                    }
                }

                // Loser placement
                if ($match['loser']) {
                    $placement = $this->getPlacementByRound($match['round'], $totalRounds, false);
                    if (!isset($standings[$placement])) {
                        $standings[$placement] = [];
                    }
                    if (!in_array($match['loser'], $standings[$placement])) {
                        $standings[$placement][] = $match['loser'];
                    }
                }
            }
        }

        // Sort by placement
        ksort($standings);

        return $standings;
    }

    /**
     * Get placement based on elimination round
     */
    private function getPlacementByRound(int $round, int $totalRounds, bool $isWinner): string
    {
        if ($isWinner && $round === $totalRounds) {
            return '1st'; // Champion
        }

        $roundsFromEnd = $totalRounds - $round + 1;

        switch ($roundsFromEnd) {
            case 1:
                return '2nd'; // Finals loser
            case 2:
                return '3rd-4th'; // Semifinals losers
            case 3:
                return '5th-8th'; // Quarterfinals losers
            case 4:
                return '9th-16th'; // Round of 16 losers
            case 5:
                return '17th-32nd'; // Round of 32 losers
            default:
                $start = pow(2, $round) + 1;
                $end = pow(2, $round + 1);
                return "{$start}th-{$end}th";
        }
    }

    /**
     * Validate bracket integrity
     */
    public function validateBracket(array $bracket): array
    {
        $errors = [];

        // Check required fields
        if (!isset($bracket['matches']) || !is_array($bracket['matches'])) {
            $errors[] = 'Bracket must have matches array';
            return $errors;
        }

        // Check each match
        foreach ($bracket['matches'] as $match) {
            if (!isset($match['id'])) {
                $errors[] = "Match missing ID";
                continue;
            }

            // Check match structure
            $requiredFields = ['round', 'position', 'status', 'best_of_format'];
            foreach ($requiredFields as $field) {
                if (!isset($match[$field])) {
                    $errors[] = "Match {$match['id']} missing {$field}";
                }
            }

            // Validate status transitions
            if (isset($match['status'])) {
                $validStatuses = ['pending', 'waiting', 'bye', 'completed'];
                if (!in_array($match['status'], $validStatuses)) {
                    $errors[] = "Match {$match['id']} has invalid status: {$match['status']}";
                }
            }

            // Check winner/loser consistency
            if ($match['status'] === 'completed') {
                if (!$match['winner']) {
                    $errors[] = "Completed match {$match['id']} must have a winner";
                }
            }
        }

        return $errors;
    }

    /**
     * Reset bracket to initial state
     */
    public function resetBracket(array &$bracket): array
    {
        foreach ($bracket['matches'] as &$match) {
            if ($match['round'] > 1) {
                $match['team1'] = null;
                $match['team2'] = null;
                $match['status'] = 'waiting';
            } else {
                // First round matches keep their teams but reset results
                $match['status'] = ($match['team1'] && $match['team2']) ? 'pending' : 'bye';
            }

            $match['winner'] = null;
            $match['loser'] = null;
            $match['scores'] = [];
            $match['updated_at'] = now();
        }

        return $bracket;
    }

    /**
     * Get next matches ready to be played
     */
    public function getNextMatches(array $bracket): array
    {
        $nextMatches = [];

        foreach ($bracket['matches'] as $match) {
            if ($match['status'] === 'pending') {
                $nextMatches[] = $match;
            }
        }

        // Sort by round and position
        usort($nextMatches, function($a, $b) {
            if ($a['round'] === $b['round']) {
                return $a['position'] <=> $b['position'];
            }
            return $a['round'] <=> $b['round'];
        });

        return $nextMatches;
    }
}