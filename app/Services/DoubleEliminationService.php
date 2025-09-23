<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DoubleEliminationService
{
    public function generateBracket($eventId, $teams)
    {
        $teamCount = count($teams);
        if ($teamCount < 2) {
            throw new \Exception('Need at least 2 teams for double elimination');
        }

        $matches = [];

        // Generate upper bracket (same as single elimination)
        $upperMatches = $this->generateUpperBracket($eventId, $teams);
        $matches = array_merge($matches, $upperMatches);

        // Generate lower bracket
        $lowerMatches = $this->generateLowerBracket($eventId, $teamCount);
        $matches = array_merge($matches, $lowerMatches);

        // Generate grand final
        $grandFinal = $this->generateGrandFinal($eventId);
        $matches[] = $grandFinal;

        return $matches;
    }

    private function generateUpperBracket($eventId, $teams)
    {
        $teamCount = count($teams);
        $upperRounds = ceil(log($teamCount, 2));
        $matches = [];

        // Seed teams for optimal bracket distribution
        $seededTeams = $this->seedTeams($teams);

        // First round matches
        $round = 1;
        $position = 1;

        for ($i = 0; $i < $teamCount; $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'upper',
                    'team1_id' => $seededTeams[$i]['id'],
                    'team2_id' => $seededTeams[$i + 1]['id'],
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'round_name' => 'Upper Round ' . $round,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }

        // Generate upper bracket advancement matches
        $currentMatches = count($matches);
        for ($r = 2; $r <= $upperRounds; $r++) {
            $matchesInRound = intval($currentMatches / 2);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $format = ($r == $upperRounds) ? 'bo5' : 'bo3'; // Upper final is Bo5

                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'upper',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => $format,
                    'round_name' => $this->getUpperRoundName($r, $upperRounds),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $currentMatches = $matchesInRound;
        }

        return $matches;
    }

    private function generateLowerBracket($eventId, $teamCount)
    {
        $matches = [];
        $upperRounds = ceil(log($teamCount, 2));

        // Lower bracket has (upper_rounds - 1) * 2 rounds
        $lowerRounds = ($upperRounds - 1) * 2;

        // First lower bracket round (receives losers from upper round 1)
        $firstRoundMatches = intval($teamCount / 4);
        for ($i = 1; $i <= $firstRoundMatches; $i++) {
            $matches[] = [
                'event_id' => $eventId,
                'round' => 1,
                'bracket_position' => $i,
                'bracket_type' => 'lower',
                'team1_id' => null,
                'team2_id' => null,
                'status' => 'pending',
                'format' => 'bo3',
                'round_name' => 'Lower Round 1',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Generate remaining lower bracket rounds
        $currentMatches = $firstRoundMatches;
        for ($round = 2; $round <= $lowerRounds; $round++) {
            // Even rounds receive losers from upper bracket
            if ($round % 2 == 0) {
                $matchesInRound = $currentMatches;
            } else {
                $matchesInRound = intval($currentMatches / 2);
            }

            $format = ($round == $lowerRounds) ? 'bo5' : 'bo3'; // Lower final is Bo5

            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $m,
                    'bracket_type' => 'lower',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => $format,
                    'round_name' => $this->getLowerRoundName($round, $lowerRounds),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            $currentMatches = $matchesInRound;
        }

        return $matches;
    }

    private function generateGrandFinal($eventId)
    {
        return [
            'event_id' => $eventId,
            'round' => 1,
            'bracket_position' => 1,
            'bracket_type' => 'grand_final',
            'team1_id' => null, // Winner of upper bracket
            'team2_id' => null, // Winner of lower bracket
            'status' => 'pending',
            'format' => 'bo7', // Grand Final is Bo7 like Marvel Rivals Championship
            'round_name' => 'Grand Final',
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    public function advanceWinner($match, $winnerId)
    {
        if ($match->bracket_type == 'upper') {
            $this->advanceUpperBracketWinner($match, $winnerId);
        } elseif ($match->bracket_type == 'lower') {
            $this->advanceLowerBracketWinner($match, $winnerId);
        }
    }

    public function moveLoserToLowerBracket($match, $loserId)
    {
        if ($match->bracket_type !== 'upper') {
            return;
        }

        $upperRound = $match->round;
        $upperPosition = $match->bracket_position;

        // Calculate target lower bracket position
        if ($upperRound == 1) {
            // First round losers go to lower bracket round 1
            $lowerRound = 1;
            $lowerPosition = ceil($upperPosition / 2);
        } else {
            // Later round losers go to even-numbered lower bracket rounds
            $lowerRound = ($upperRound - 1) * 2;
            $lowerPosition = $upperPosition;
        }

        $this->assignTeamToLowerBracket($match->event_id, $lowerRound, $lowerPosition, $loserId);
    }

    private function advanceUpperBracketWinner($match, $winnerId)
    {
        $upperRounds = $this->getUpperRounds($match->event_id);

        if ($match->round == $upperRounds) {
            // Upper bracket winner goes to grand final
            DB::table('matches')
                ->where('event_id', $match->event_id)
                ->where('bracket_type', 'grand_final')
                ->update([
                    'team1_id' => $winnerId,
                    'status' => 'pending',
                    'updated_at' => now()
                ]);
        } else {
            // Advance to next upper bracket round
            $nextRound = $match->round + 1;
            $nextPosition = ceil($match->bracket_position / 2);

            $nextMatch = DB::table('matches')
                ->where('event_id', $match->event_id)
                ->where('bracket_type', 'upper')
                ->where('round', $nextRound)
                ->where('bracket_position', $nextPosition)
                ->first();

            if ($nextMatch) {
                $teamSlot = ($match->bracket_position % 2 === 1) ? 'team1_id' : 'team2_id';
                DB::table('matches')
                    ->where('id', $nextMatch->id)
                    ->update([
                        $teamSlot => $winnerId,
                        'status' => ($nextMatch->team1_id && $nextMatch->team2_id) ? 'scheduled' : 'pending',
                        'updated_at' => now()
                    ]);
            }
        }
    }

    private function advanceLowerBracketWinner($match, $winnerId)
    {
        $lowerRounds = $this->getLowerRounds($match->event_id);

        if ($match->round == $lowerRounds) {
            // Lower bracket winner goes to grand final
            DB::table('matches')
                ->where('event_id', $match->event_id)
                ->where('bracket_type', 'grand_final')
                ->update([
                    'team2_id' => $winnerId,
                    'status' => 'scheduled',
                    'updated_at' => now()
                ]);
        } else {
            // Advance to next lower bracket round
            $nextRound = $match->round + 1;
            $nextPosition = ($match->round % 2 == 1) ? ceil($match->bracket_position / 2) : $match->bracket_position;

            $this->assignTeamToLowerBracket($match->event_id, $nextRound, $nextPosition, $winnerId);
        }
    }

    private function assignTeamToLowerBracket($eventId, $round, $position, $teamId)
    {
        $lowerMatch = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'lower')
            ->where('round', $round)
            ->where('bracket_position', $position)
            ->first();

        if ($lowerMatch) {
            if (!$lowerMatch->team1_id) {
                DB::table('matches')
                    ->where('id', $lowerMatch->id)
                    ->update([
                        'team1_id' => $teamId,
                        'status' => $lowerMatch->team2_id ? 'scheduled' : 'pending',
                        'updated_at' => now()
                    ]);
            } elseif (!$lowerMatch->team2_id) {
                DB::table('matches')
                    ->where('id', $lowerMatch->id)
                    ->update([
                        'team2_id' => $teamId,
                        'status' => 'scheduled',
                        'updated_at' => now()
                    ]);
            }
        }
    }

    private function seedTeams($teams)
    {
        // Standard bracket seeding for double elimination
        $seeded = [];
        $teamCount = count($teams);

        for ($i = 0; $i < $teamCount; $i += 2) {
            $seeded[] = $teams[$i];
            if (isset($teams[$teamCount - 1 - $i])) {
                $seeded[] = $teams[$teamCount - 1 - $i];
            }
        }

        return $seeded;
    }

    private function getUpperRoundName($round, $totalRounds)
    {
        $roundsFromEnd = $totalRounds - $round + 1;

        switch ($roundsFromEnd) {
            case 1:
                return 'Upper Final';
            case 2:
                return 'Upper Semi-Finals';
            default:
                return "Upper Round $round";
        }
    }

    private function getLowerRoundName($round, $totalRounds)
    {
        if ($round == $totalRounds) {
            return 'Lower Final';
        } elseif ($round == $totalRounds - 1) {
            return 'Lower Semi-Final';
        } else {
            return "Lower Round $round";
        }
    }

    private function getUpperRounds($eventId)
    {
        $teamCount = DB::table('event_teams')->where('event_id', $eventId)->count();
        return ceil(log($teamCount, 2));
    }

    private function getLowerRounds($eventId)
    {
        $upperRounds = $this->getUpperRounds($eventId);
        return ($upperRounds - 1) * 2;
    }

    public function getBracketStructure($eventId)
    {
        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->select([
                'm.*',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo'
            ])
            ->orderBy('m.bracket_type')
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();

        $bracket = [
            'type' => 'double_elimination',
            'upper_bracket' => [],
            'lower_bracket' => [],
            'grand_final' => null
        ];

        foreach ($matches as $match) {
            $matchData = [
                'id' => $match->id,
                'position' => $match->bracket_position,
                'team1' => $this->formatTeamData($match, 'team1'),
                'team2' => $this->formatTeamData($match, 'team2'),
                'status' => $match->status,
                'format' => $match->format,
                'round_name' => $match->round_name,
                'winner_id' => $this->getWinnerId($match)
            ];

            if ($match->bracket_type === 'grand_final') {
                $bracket['grand_final'] = $matchData;
            } elseif ($match->bracket_type === 'upper') {
                $bracket['upper_bracket'][] = $matchData;
            } else {
                $bracket['lower_bracket'][] = $matchData;
            }
        }

        return $bracket;
    }

    private function formatTeamData($match, $prefix)
    {
        return [
            'id' => $match->{$prefix . '_id'},
            'name' => $match->{$prefix . '_name'},
            'short_name' => $match->{$prefix . '_short'},
            'logo' => $match->{$prefix . '_logo'},
            'score' => $match->{$prefix . '_score'},
        ];
    }

    private function getWinnerId($match)
    {
        if ($match->status !== 'completed') {
            return null;
        }

        if ($match->team1_score > $match->team2_score) {
            return $match->team1_id;
        } elseif ($match->team2_score > $match->team1_score) {
            return $match->team2_id;
        }

        return null;
    }
}