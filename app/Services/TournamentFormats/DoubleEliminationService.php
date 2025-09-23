<?php

namespace App\Services\TournamentFormats;

use Illuminate\Support\Facades\Log;

class DoubleEliminationService
{
    /**
     * Generate double elimination bracket
     *
     * @param array $teams Array of team objects with id, name, seed
     * @param string $bestOfFormat (BO1, BO3, BO5, etc.)
     * @return array Bracket structure with upper and lower brackets
     */
    public function generateBracket(array $teams, string $bestOfFormat = 'BO3'): array
    {
        Log::info('Generating Double Elimination bracket', [
            'team_count' => count($teams),
            'best_of_format' => $bestOfFormat
        ]);

        // Validate input
        if (count($teams) < 2) {
            throw new \InvalidArgumentException('At least 2 teams required for double elimination');
        }

        // Sort teams by seed
        usort($teams, function($a, $b) {
            return ($a['seed'] ?? 999) <=> ($b['seed'] ?? 999);
        });

        $bracketSize = $this->getNextPowerOfTwo(count($teams));
        $upperRounds = log($bracketSize, 2);
        $lowerRounds = ($upperRounds * 2) - 1;

        // Generate upper bracket
        $upperBracket = $this->generateUpperBracket($teams, $bracketSize, $bestOfFormat);

        // Generate lower bracket
        $lowerBracket = $this->generateLowerBracket($bracketSize, $bestOfFormat);

        // Generate grand finals
        $grandFinals = $this->generateGrandFinals($bestOfFormat);

        return [
            'format' => 'double_elimination',
            'bracket_size' => $bracketSize,
            'upper_rounds' => $upperRounds,
            'lower_rounds' => $lowerRounds,
            'upper_bracket' => $upperBracket,
            'lower_bracket' => $lowerBracket,
            'grand_finals' => $grandFinals,
            'teams' => $teams,
            'best_of_format' => $bestOfFormat
        ];
    }

    /**
     * Generate upper bracket (winners bracket)
     */
    private function generateUpperBracket(array $teams, int $bracketSize, string $bestOfFormat): array
    {
        $matches = [];
        $matchId = 1;
        $totalRounds = log($bracketSize, 2);

        // Generate seeding pairs
        $seedPairs = $this->generateSeedPairs($bracketSize);

        // First round of upper bracket
        foreach ($seedPairs as $pair) {
            $team1 = $this->getTeamBySeed($teams, $pair[0]);
            $team2 = $this->getTeamBySeed($teams, $pair[1]);

            $matches[] = [
                'id' => "UB_R1_M{$matchId}",
                'round' => 1,
                'round_name' => "Upper Round 1",
                'position' => $matchId,
                'team1' => $team1,
                'team2' => $team2,
                'winner' => null,
                'loser' => null,
                'status' => ($team1 && $team2) ? 'pending' : 'bye',
                'best_of_format' => $bestOfFormat,
                'scores' => [],
                'next_match_id' => "UB_R2_M" . ceil($matchId / 2),
                'loser_next_match_id' => $this->getLowerBracketDropPosition(1, $matchId),
                'bracket_type' => 'upper',
                'created_at' => now(),
                'updated_at' => now()
            ];

            $matchId++;
        }

        // Subsequent upper bracket rounds
        for ($round = 2; $round <= $totalRounds; $round++) {
            $matchesInRound = $bracketSize / pow(2, $round);

            for ($i = 1; $i <= $matchesInRound; $i++) {
                $nextMatchId = null;
                $loserNextMatchId = null;

                if ($round < $totalRounds) {
                    $nextMatchId = "UB_R" . ($round + 1) . "_M" . ceil($i / 2);
                    $loserNextMatchId = $this->getLowerBracketDropPosition($round, $i);
                } else {
                    // Upper bracket finals
                    $nextMatchId = "GF_M1";
                    $loserNextMatchId = "LB_FINAL_M1";
                }

                $matches[] = [
                    'id' => "UB_R{$round}_M{$i}",
                    'round' => $round,
                    'round_name' => ($round === $totalRounds) ? "Upper Finals" : "Upper Round {$round}",
                    'position' => $i,
                    'team1' => null,
                    'team2' => null,
                    'winner' => null,
                    'loser' => null,
                    'status' => 'waiting',
                    'best_of_format' => $bestOfFormat,
                    'scores' => [],
                    'next_match_id' => $nextMatchId,
                    'loser_next_match_id' => $loserNextMatchId,
                    'bracket_type' => 'upper',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        return $matches;
    }

    /**
     * Generate lower bracket (losers bracket)
     */
    private function generateLowerBracket(int $bracketSize, string $bestOfFormat): array
    {
        $matches = [];
        $upperRounds = log($bracketSize, 2);
        $lowerRounds = ($upperRounds * 2) - 1;

        $matchId = 1;

        for ($round = 1; $round <= $lowerRounds; $round++) {
            $matchesInRound = $this->getLowerBracketMatchCount($round, $bracketSize);

            for ($i = 1; $i <= $matchesInRound; $i++) {
                $nextMatchId = null;

                if ($round < $lowerRounds) {
                    if ($round % 2 === 1) {
                        // Odd rounds: winners advance to next round
                        $nextMatchId = "LB_R" . ($round + 1) . "_M" . ceil($i / 2);
                    } else {
                        // Even rounds: winners advance to next round
                        $nextMatchId = "LB_R" . ($round + 1) . "_M{$i}";
                    }
                } else {
                    // Lower bracket finals
                    $nextMatchId = "GF_M1";
                }

                $roundName = ($round === $lowerRounds) ? "Lower Finals" : "Lower Round {$round}";

                $matches[] = [
                    'id' => "LB_R{$round}_M{$i}",
                    'round' => $round,
                    'round_name' => $roundName,
                    'position' => $i,
                    'team1' => null,
                    'team2' => null,
                    'winner' => null,
                    'loser' => null,
                    'status' => 'waiting',
                    'best_of_format' => $bestOfFormat,
                    'scores' => [],
                    'next_match_id' => $nextMatchId,
                    'bracket_type' => 'lower',
                    'elimination' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $matchId++;
            }
        }

        return $matches;
    }

    /**
     * Generate grand finals matches
     */
    private function generateGrandFinals(string $bestOfFormat): array
    {
        return [
            [
                'id' => "GF_M1",
                'round' => 1,
                'round_name' => "Grand Finals",
                'position' => 1,
                'team1' => null, // Upper bracket winner
                'team2' => null, // Lower bracket winner
                'winner' => null,
                'loser' => null,
                'status' => 'waiting',
                'best_of_format' => $bestOfFormat,
                'scores' => [],
                'next_match_id' => "GF_M2", // Bracket reset if lower bracket team wins
                'bracket_type' => 'grand_finals',
                'requires_reset' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => "GF_M2",
                'round' => 2,
                'round_name' => "Grand Finals Reset",
                'position' => 1,
                'team1' => null, // Will be set if bracket reset needed
                'team2' => null, // Will be set if bracket reset needed
                'winner' => null,
                'loser' => null,
                'status' => 'conditional', // Only played if needed
                'best_of_format' => $bestOfFormat,
                'scores' => [],
                'next_match_id' => null,
                'bracket_type' => 'grand_finals_reset',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
    }

    /**
     * Calculate where losers drop in lower bracket
     */
    private function getLowerBracketDropPosition(int $upperRound, int $matchPosition): string
    {
        if ($upperRound === 1) {
            // First round losers go to round 1 of lower bracket
            return "LB_R1_M{$matchPosition}";
        }

        // Calculate lower bracket round for upper bracket losers
        $lowerRound = ($upperRound - 1) * 2;
        $lowerPosition = ceil($matchPosition / 2);

        return "LB_R{$lowerRound}_M{$lowerPosition}";
    }

    /**
     * Get number of matches in lower bracket round
     */
    private function getLowerBracketMatchCount(int $round, int $bracketSize): int
    {
        if ($round === 1) {
            return $bracketSize / 2; // All first round losers
        }

        $upperRounds = log($bracketSize, 2);

        if ($round % 2 === 1) {
            // Odd rounds: only upper bracket losers
            $upperRoundDropping = ceil($round / 2) + 1;
            return $bracketSize / pow(2, $upperRoundDropping);
        } else {
            // Even rounds: survivors from previous lower round
            return $this->getLowerBracketMatchCount($round - 1, $bracketSize) / 2;
        }
    }

    /**
     * Generate seeding pairs
     */
    private function generateSeedPairs(int $bracketSize): array
    {
        $pairs = [];
        $seeds = range(1, $bracketSize);

        for ($i = 0; $i < $bracketSize / 2; $i++) {
            $pairs[] = [$seeds[$i], $seeds[$bracketSize - 1 - $i]];
        }

        return $pairs;
    }

    /**
     * Get team by seed
     */
    private function getTeamBySeed(array $teams, int $seed): ?array
    {
        foreach ($teams as $team) {
            if (($team['seed'] ?? 999) === $seed) {
                return $team;
            }
        }
        return null;
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
     * Advance winner in upper bracket
     */
    public function advanceUpperBracketWinner(array &$bracket, string $matchId, array $winner, array $loser): array
    {
        Log::info('Advancing winner in Upper Bracket', [
            'match_id' => $matchId,
            'winner' => $winner['name'] ?? 'Unknown',
            'loser' => $loser['name'] ?? 'Unknown'
        ]);

        // Update the match
        $this->updateMatch($bracket['upper_bracket'], $matchId, $winner, $loser);

        // Find the match to get advancement info
        $match = $this->findMatch($bracket['upper_bracket'], $matchId);

        if ($match) {
            // Advance winner to next upper bracket match or grand finals
            if ($match['next_match_id']) {
                if (strpos($match['next_match_id'], 'GF_') === 0) {
                    // Advance to grand finals
                    $this->advanceToGrandFinals($bracket, $match['next_match_id'], $winner, 'upper');
                } else {
                    // Advance to next upper bracket match
                    $this->advanceToNextMatch($bracket['upper_bracket'], $match['next_match_id'], $winner, $matchId);
                }
            }

            // Drop loser to lower bracket
            if ($match['loser_next_match_id']) {
                if (strpos($match['loser_next_match_id'], 'LB_') === 0) {
                    $this->advanceToNextMatch($bracket['lower_bracket'], $match['loser_next_match_id'], $loser, $matchId);
                }
            }
        }

        return $bracket;
    }

    /**
     * Advance winner in lower bracket
     */
    public function advanceLowerBracketWinner(array &$bracket, string $matchId, array $winner): array
    {
        Log::info('Advancing winner in Lower Bracket', [
            'match_id' => $matchId,
            'winner' => $winner['name'] ?? 'Unknown'
        ]);

        // Update the match (loser is eliminated)
        $this->updateMatch($bracket['lower_bracket'], $matchId, $winner, null);

        // Find the match
        $match = $this->findMatch($bracket['lower_bracket'], $matchId);

        if ($match && $match['next_match_id']) {
            if (strpos($match['next_match_id'], 'GF_') === 0) {
                // Advance to grand finals
                $this->advanceToGrandFinals($bracket, $match['next_match_id'], $winner, 'lower');
            } else {
                // Advance to next lower bracket match
                $this->advanceToNextMatch($bracket['lower_bracket'], $match['next_match_id'], $winner, $matchId);
            }
        }

        return $bracket;
    }

    /**
     * Handle grand finals advancement
     */
    public function advanceGrandFinalsWinner(array &$bracket, string $matchId, array $winner): array
    {
        Log::info('Advancing winner in Grand Finals', [
            'match_id' => $matchId,
            'winner' => $winner['name'] ?? 'Unknown'
        ]);

        // Update grand finals match
        $this->updateMatch($bracket['grand_finals'], $matchId, $winner, null);

        $match = $this->findMatch($bracket['grand_finals'], $matchId);

        // Check if bracket reset is needed
        if ($matchId === 'GF_M1' && $match) {
            $upperBracketTeam = $this->getUpperBracketWinner($bracket);
            $lowerBracketTeam = $this->getLowerBracketWinner($bracket);

            // If lower bracket team won, trigger bracket reset
            if ($winner['id'] === $lowerBracketTeam['id']) {
                $this->triggerBracketReset($bracket, $upperBracketTeam, $lowerBracketTeam);
            }
            // If upper bracket team won, tournament is over (they maintain advantage)
        }

        return $bracket;
    }

    /**
     * Trigger bracket reset for grand finals
     */
    private function triggerBracketReset(array &$bracket, array $upperTeam, array $lowerTeam): void
    {
        $resetMatch = &$bracket['grand_finals'][1]; // GF_M2

        $resetMatch['team1'] = $upperTeam;
        $resetMatch['team2'] = $lowerTeam;
        $resetMatch['status'] = 'pending';
        $resetMatch['updated_at'] = now();

        Log::info('Bracket reset triggered', [
            'upper_team' => $upperTeam['name'] ?? 'Unknown',
            'lower_team' => $lowerTeam['name'] ?? 'Unknown'
        ]);
    }

    /**
     * Helper methods
     */
    private function updateMatch(array &$matches, string $matchId, array $winner, ?array $loser): void
    {
        foreach ($matches as &$match) {
            if ($match['id'] === $matchId) {
                $match['winner'] = $winner;
                $match['loser'] = $loser;
                $match['status'] = 'completed';
                $match['updated_at'] = now();
                break;
            }
        }
    }

    private function findMatch(array $matches, string $matchId): ?array
    {
        foreach ($matches as $match) {
            if ($match['id'] === $matchId) {
                return $match;
            }
        }
        return null;
    }

    private function advanceToNextMatch(array &$matches, string $nextMatchId, array $team, string $sourceMatchId): void
    {
        foreach ($matches as &$match) {
            if ($match['id'] === $nextMatchId) {
                $sourcePosition = intval(explode('_M', $sourceMatchId)[1] ?? 1);

                if ($sourcePosition % 2 === 1) {
                    $match['team1'] = $team;
                } else {
                    $match['team2'] = $team;
                }

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

    private function advanceToGrandFinals(array &$bracket, string $grandFinalsId, array $team, string $source): void
    {
        $grandFinalsMatch = &$bracket['grand_finals'][0]; // GF_M1

        if ($source === 'upper') {
            $grandFinalsMatch['team1'] = $team;
        } else {
            $grandFinalsMatch['team2'] = $team;
        }

        if ($grandFinalsMatch['team1'] && $grandFinalsMatch['team2']) {
            $grandFinalsMatch['status'] = 'pending';
        }

        $grandFinalsMatch['updated_at'] = now();
    }

    private function getUpperBracketWinner(array $bracket): ?array
    {
        $upperFinals = end($bracket['upper_bracket']);
        return $upperFinals['winner'] ?? null;
    }

    private function getLowerBracketWinner(array $bracket): ?array
    {
        $lowerFinals = end($bracket['lower_bracket']);
        return $lowerFinals['winner'] ?? null;
    }

    /**
     * Get bracket standings
     */
    public function getStandings(array $bracket): array
    {
        $standings = [];

        // Champion
        $grandFinalsWinner = $this->getGrandFinalsWinner($bracket);
        if ($grandFinalsWinner) {
            $standings['1st'] = [$grandFinalsWinner];
        }

        // Runner-up
        $grandFinalsLoser = $this->getGrandFinalsLoser($bracket);
        if ($grandFinalsLoser) {
            $standings['2nd'] = [$grandFinalsLoser];
        }

        // Lower bracket eliminations by round
        foreach ($bracket['lower_bracket'] as $match) {
            if ($match['status'] === 'completed' && $match['loser']) {
                $placement = $this->getLowerBracketPlacement($match['round'], count($bracket['teams']));
                if (!isset($standings[$placement])) {
                    $standings[$placement] = [];
                }
                $standings[$placement][] = $match['loser'];
            }
        }

        return $standings;
    }

    private function getGrandFinalsWinner(array $bracket): ?array
    {
        foreach ($bracket['grand_finals'] as $match) {
            if ($match['status'] === 'completed' && $match['winner']) {
                return $match['winner'];
            }
        }
        return null;
    }

    private function getGrandFinalsLoser(array $bracket): ?array
    {
        foreach ($bracket['grand_finals'] as $match) {
            if ($match['status'] === 'completed' && $match['loser']) {
                return $match['loser'];
            }
        }
        return null;
    }

    private function getLowerBracketPlacement(int $round, int $teamCount): string
    {
        // Calculate placement based on elimination round in lower bracket
        $bracketSize = $this->getNextPowerOfTwo($teamCount);
        $maxRounds = ($bracketSize / 2) - 1;
        $roundsFromEnd = $maxRounds - $round + 1;

        switch ($roundsFromEnd) {
            case 0:
                return '2nd'; // Lower finals loser
            case 1:
                return '3rd-4th';
            case 2:
                return '5th-8th';
            case 3:
                return '9th-16th';
            default:
                $start = pow(2, $round - 1) + 1;
                $end = pow(2, $round);
                return "{$start}th-{$end}th";
        }
    }

    /**
     * Validate double elimination bracket
     */
    public function validateBracket(array $bracket): array
    {
        $errors = [];

        // Check required sections
        $requiredSections = ['upper_bracket', 'lower_bracket', 'grand_finals'];
        foreach ($requiredSections as $section) {
            if (!isset($bracket[$section]) || !is_array($bracket[$section])) {
                $errors[] = "Missing {$section} section";
            }
        }

        // Validate each section
        if (isset($bracket['upper_bracket'])) {
            $errors = array_merge($errors, $this->validateBracketSection($bracket['upper_bracket'], 'upper'));
        }

        if (isset($bracket['lower_bracket'])) {
            $errors = array_merge($errors, $this->validateBracketSection($bracket['lower_bracket'], 'lower'));
        }

        if (isset($bracket['grand_finals'])) {
            $errors = array_merge($errors, $this->validateBracketSection($bracket['grand_finals'], 'grand_finals'));
        }

        return $errors;
    }

    private function validateBracketSection(array $matches, string $sectionName): array
    {
        $errors = [];

        foreach ($matches as $match) {
            if (!isset($match['id'])) {
                $errors[] = "{$sectionName}: Match missing ID";
                continue;
            }

            $requiredFields = ['round', 'position', 'status', 'bracket_type'];
            foreach ($requiredFields as $field) {
                if (!isset($match[$field])) {
                    $errors[] = "{$sectionName}: Match {$match['id']} missing {$field}";
                }
            }
        }

        return $errors;
    }
}