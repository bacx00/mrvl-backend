<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RoundRobinService
{
    public function generateBracket($eventId, $teams)
    {
        $teamCount = count($teams);
        if ($teamCount < 3) {
            throw new \Exception('Need at least 3 teams for round robin');
        }

        $matches = [];
        $round = 1;
        $position = 1;

        // Every team plays every other team once
        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'round_robin',
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$j]['id'],
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'round_name' => 'Round ' . $round,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;

                // Distribute matches across rounds for better scheduling
                if ($position > ceil($teamCount / 2)) {
                    $round++;
                    $position = 1;
                }
            }
        }

        return $matches;
    }

    public function calculateStandings($eventId)
    {
        $teams = DB::table('event_teams')
            ->join('teams', 'event_teams.team_id', '=', 'teams.id')
            ->where('event_teams.event_id', $eventId)
            ->select('teams.id', 'teams.name', 'teams.short_name', 'teams.logo')
            ->get();

        $standings = [];
        foreach ($teams as $team) {
            $standings[$team->id] = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'team_short_name' => $team->short_name,
                'team_logo' => $team->logo,
                'matches_played' => 0,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'map_wins' => 0,
                'map_losses' => 0,
                'map_diff' => 0,
                'round_wins' => 0,
                'round_losses' => 0,
                'round_diff' => 0,
                'points' => 0,
                'win_percentage' => 0.0
            ];
        }

        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'round_robin')
            ->where('status', 'completed')
            ->get();

        foreach ($matches as $match) {
            if (!isset($standings[$match->team1_id]) || !isset($standings[$match->team2_id])) {
                continue;
            }

            $standings[$match->team1_id]['matches_played']++;
            $standings[$match->team2_id]['matches_played']++;

            // Map/game scores
            $standings[$match->team1_id]['map_wins'] += $match->team1_score;
            $standings[$match->team1_id]['map_losses'] += $match->team2_score;
            $standings[$match->team2_id]['map_wins'] += $match->team2_score;
            $standings[$match->team2_id]['map_losses'] += $match->team1_score;

            // Process maps_data for round statistics
            if ($match->maps_data) {
                $mapsData = json_decode($match->maps_data, true);
                foreach ($mapsData as $map) {
                    if (isset($map['team1_rounds']) && isset($map['team2_rounds'])) {
                        $standings[$match->team1_id]['round_wins'] += $map['team1_rounds'];
                        $standings[$match->team1_id]['round_losses'] += $map['team2_rounds'];
                        $standings[$match->team2_id]['round_wins'] += $map['team2_rounds'];
                        $standings[$match->team2_id]['round_losses'] += $map['team1_rounds'];
                    }
                }
            }

            // Match results (round robin uses 3-1-0 point system)
            if ($match->team1_score > $match->team2_score) {
                $standings[$match->team1_id]['wins']++;
                $standings[$match->team1_id]['points'] += 3;
                $standings[$match->team2_id]['losses']++;
            } elseif ($match->team2_score > $match->team1_score) {
                $standings[$match->team2_id]['wins']++;
                $standings[$match->team2_id]['points'] += 3;
                $standings[$match->team1_id]['losses']++;
            } else {
                $standings[$match->team1_id]['draws']++;
                $standings[$match->team1_id]['points'] += 1;
                $standings[$match->team2_id]['draws']++;
                $standings[$match->team2_id]['points'] += 1;
            }
        }

        // Calculate differentials and percentages
        foreach ($standings as &$standing) {
            $standing['map_diff'] = $standing['map_wins'] - $standing['map_losses'];
            $standing['round_diff'] = $standing['round_wins'] - $standing['round_losses'];

            if ($standing['matches_played'] > 0) {
                $standing['win_percentage'] = round(($standing['wins'] / $standing['matches_played']) * 100, 1);
            }
        }

        // Sort standings: points, map difference, round difference, head-to-head
        usort($standings, function($a, $b) {
            if ($a['points'] != $b['points']) return $b['points'] - $a['points'];
            if ($a['map_diff'] != $b['map_diff']) return $b['map_diff'] - $a['map_diff'];
            return $b['round_diff'] - $a['round_diff'];
        });

        return array_values($standings);
    }

    public function getBracketStructure($eventId)
    {
        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->where('m.bracket_type', 'round_robin')
            ->select([
                'm.*',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo'
            ])
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();

        $rounds = [];
        foreach ($matches as $match) {
            $roundData = [
                'id' => $match->id,
                'position' => $match->bracket_position,
                'team1' => $this->formatTeamData($match, 'team1'),
                'team2' => $this->formatTeamData($match, 'team2'),
                'status' => $match->status,
                'format' => $match->format,
                'scheduled_at' => $match->scheduled_at,
                'completed_at' => $match->completed_at,
                'winner_id' => $this->getWinnerId($match)
            ];

            $rounds[$match->round][] = $roundData;
        }

        return [
            'type' => 'round_robin',
            'rounds' => $rounds,
            'standings' => $this->calculateStandings($eventId),
            'matches' => $matches->map(function($match) {
                return [
                    'id' => $match->id,
                    'team1' => $this->formatTeamData($match, 'team1'),
                    'team2' => $this->formatTeamData($match, 'team2'),
                    'status' => $match->status,
                    'format' => $match->format,
                    'round' => $match->round,
                    'winner_id' => $this->getWinnerId($match)
                ];
            })->toArray()
        ];
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

    public function getHeadToHeadRecord($eventId, $team1Id, $team2Id)
    {
        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'round_robin')
            ->where('status', 'completed')
            ->where(function($query) use ($team1Id, $team2Id) {
                $query->where(function($q) use ($team1Id, $team2Id) {
                    $q->where('team1_id', $team1Id)->where('team2_id', $team2Id);
                })->orWhere(function($q) use ($team1Id, $team2Id) {
                    $q->where('team1_id', $team2Id)->where('team2_id', $team1Id);
                });
            })
            ->get();

        $team1Wins = 0;
        $team2Wins = 0;
        $draws = 0;

        foreach ($matches as $match) {
            if ($match->team1_id == $team1Id) {
                if ($match->team1_score > $match->team2_score) {
                    $team1Wins++;
                } elseif ($match->team2_score > $match->team1_score) {
                    $team2Wins++;
                } else {
                    $draws++;
                }
            } else {
                if ($match->team2_score > $match->team1_score) {
                    $team1Wins++;
                } elseif ($match->team1_score > $match->team2_score) {
                    $team2Wins++;
                } else {
                    $draws++;
                }
            }
        }

        return [
            'team1_wins' => $team1Wins,
            'team2_wins' => $team2Wins,
            'draws' => $draws,
            'total_matches' => $matches->count()
        ];
    }

    public function isComplete($eventId)
    {
        $totalMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'round_robin')
            ->count();

        $completedMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'round_robin')
            ->where('status', 'completed')
            ->count();

        return $totalMatches > 0 && $totalMatches === $completedMatches;
    }
}