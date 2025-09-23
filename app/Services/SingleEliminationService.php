<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SingleEliminationService
{
    public function generateBracket($eventId, $teams)
    {
        $teamCount = count($teams);
        if ($teamCount < 2) {
            throw new \Exception('Need at least 2 teams for single elimination');
        }

        // Calculate rounds (Round of 32, Round of 16, Quarter-Finals, Semi-Finals, Final)
        $totalRounds = ceil(log($teamCount, 2));
        $matches = [];

        // First round - pair teams by seeding (1 vs last, 2 vs second-last, etc.)
        $seededTeams = $this->seedTeams($teams);
        $round = 1;
        $position = 1;

        for ($i = 0; $i < $teamCount; $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'main',
                    'team1_id' => $seededTeams[$i]['id'],
                    'team2_id' => $seededTeams[$i + 1]['id'],
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'round_name' => $this->getRoundName($round, $totalRounds),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }

        // Create subsequent round placeholders
        $currentMatches = count($matches);
        for ($r = 2; $r <= $totalRounds; $r++) {
            $matchesInRound = intval($currentMatches / 2);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'main',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => $r == $totalRounds ? 'bo5' : 'bo3', // Final is Bo5
                    'round_name' => $this->getRoundName($r, $totalRounds),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $currentMatches = $matchesInRound;
        }

        return $matches;
    }

    public function advanceWinner($match, $winnerId)
    {
        $nextRound = $match->round + 1;
        $nextPosition = ceil($match->bracket_position / 2);

        $nextMatch = DB::table('matches')
            ->where('event_id', $match->event_id)
            ->where('round', $nextRound)
            ->where('bracket_position', $nextPosition)
            ->where('bracket_type', 'main')
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

    private function seedTeams($teams)
    {
        // Standard seeding: 1 vs 16, 2 vs 15, 3 vs 14, etc.
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

    private function getRoundName($round, $totalRounds)
    {
        $roundsFromEnd = $totalRounds - $round + 1;

        switch ($roundsFromEnd) {
            case 1:
                return 'Grand Final';
            case 2:
                return 'Semi-Finals';
            case 3:
                return 'Quarter-Finals';
            case 4:
                return 'Round of 16';
            case 5:
                return 'Round of 32';
            case 6:
                return 'Round of 64';
            default:
                return "Round $round";
        }
    }

    public function getBracketStructure($eventId)
    {
        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->where('m.bracket_type', 'main')
            ->select([
                'm.*',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo'
            ])
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();

        $bracket = [
            'type' => 'single_elimination',
            'rounds' => [],
            'matches' => []
        ];

        foreach ($matches as $match) {
            $roundData = [
                'id' => $match->id,
                'position' => $match->bracket_position,
                'team1' => $this->formatTeamData($match, 'team1'),
                'team2' => $this->formatTeamData($match, 'team2'),
                'status' => $match->status,
                'format' => $match->format,
                'round_name' => $match->round_name,
                'winner_id' => $this->getWinnerId($match)
            ];

            if (!isset($bracket['rounds'][$match->round_name])) {
                $bracket['rounds'][$match->round_name] = [
                    'round_number' => $match->round,
                    'matches' => []
                ];
            }

            $bracket['rounds'][$match->round_name]['matches'][] = $roundData;
            $bracket['matches'][] = $roundData;
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