<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GroupStageService
{
    public function generateBracket($eventId, $teams)
    {
        $teamCount = count($teams);
        if ($teamCount < 4) {
            throw new \Exception('Need at least 4 teams for group stage');
        }

        // Determine optimal group configuration
        $groupConfig = $this->calculateGroupConfiguration($teamCount);
        $groups = $this->distributeTeamsIntoGroups($teams, $groupConfig);

        $matches = [];
        $globalPosition = 1;

        foreach ($groups as $groupIndex => $groupTeams) {
            $groupMatches = $this->generateGroupMatches($eventId, $groupTeams, $groupIndex + 1);

            // Update positions to be globally unique
            foreach ($groupMatches as &$match) {
                $match['bracket_position'] = $globalPosition++;
            }

            $matches = array_merge($matches, $groupMatches);
        }

        return $matches;
    }

    private function calculateGroupConfiguration($teamCount)
    {
        // Common group configurations for esports
        if ($teamCount <= 8) {
            return ['groups' => 2, 'teams_per_group' => $teamCount / 2];
        } elseif ($teamCount <= 16) {
            return ['groups' => 4, 'teams_per_group' => 4];
        } elseif ($teamCount <= 24) {
            return ['groups' => 6, 'teams_per_group' => 4];
        } else {
            return ['groups' => 8, 'teams_per_group' => 4];
        }
    }

    private function distributeTeamsIntoGroups($teams, $groupConfig)
    {
        $groups = array_fill(0, $groupConfig['groups'], []);

        // Snake draft distribution for balanced groups
        $teamIndex = 0;
        $direction = 1;

        foreach ($teams as $team) {
            $groups[$teamIndex][] = $team;

            $teamIndex += $direction;

            // Reverse direction at group boundaries
            if ($teamIndex >= $groupConfig['groups'] || $teamIndex < 0) {
                $direction *= -1;
                $teamIndex += $direction;
            }
        }

        return $groups;
    }

    private function generateGroupMatches($eventId, $groupTeams, $groupNumber)
    {
        $matches = [];
        $teamCount = count($groupTeams);
        $round = 1;
        $position = 1;

        // Round robin within group
        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'group_stage',
                    'group_number' => $groupNumber,
                    'team1_id' => $groupTeams[$i]['id'],
                    'team2_id' => $groupTeams[$j]['id'],
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'round_name' => "Group {$groupNumber} - Round {$round}",
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;

                // Distribute matches across rounds
                if ($position > 2) { // Max 2 matches per round in group
                    $round++;
                    $position = 1;
                }
            }
        }

        return $matches;
    }

    public function calculateGroupStandings($eventId, $groupNumber = null)
    {
        $query = DB::table('event_teams')
            ->join('teams', 'event_teams.team_id', '=', 'teams.id')
            ->where('event_teams.event_id', $eventId);

        if ($groupNumber) {
            // Get teams only from specific group
            $teamIds = DB::table('matches')
                ->where('event_id', $eventId)
                ->where('bracket_type', 'group_stage')
                ->where('group_number', $groupNumber)
                ->selectRaw('team1_id as team_id')
                ->union(
                    DB::table('matches')
                        ->where('event_id', $eventId)
                        ->where('bracket_type', 'group_stage')
                        ->where('group_number', $groupNumber)
                        ->selectRaw('team2_id as team_id')
                )
                ->distinct()
                ->pluck('team_id');

            $query->whereIn('teams.id', $teamIds);
        }

        $teams = $query->select('teams.id', 'teams.name', 'teams.short_name', 'teams.logo')->get();

        $standings = [];
        foreach ($teams as $team) {
            $groupNum = $this->getTeamGroup($eventId, $team->id);

            $standings[$team->id] = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'team_short_name' => $team->short_name,
                'team_logo' => $team->logo,
                'group_number' => $groupNum,
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

        $matchQuery = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'group_stage')
            ->where('status', 'completed');

        if ($groupNumber) {
            $matchQuery->where('group_number', $groupNumber);
        }

        $matches = $matchQuery->get();

        foreach ($matches as $match) {
            if (!isset($standings[$match->team1_id]) || !isset($standings[$match->team2_id])) {
                continue;
            }

            $standings[$match->team1_id]['matches_played']++;
            $standings[$match->team2_id]['matches_played']++;

            // Map scores
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

            // Match results
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

        // Sort standings by group, then by performance
        usort($standings, function($a, $b) {
            if ($a['group_number'] != $b['group_number']) {
                return $a['group_number'] - $b['group_number'];
            }
            if ($a['points'] != $b['points']) return $b['points'] - $a['points'];
            if ($a['map_diff'] != $b['map_diff']) return $b['map_diff'] - $a['map_diff'];
            return $b['round_diff'] - $a['round_diff'];
        });

        return array_values($standings);
    }

    private function getTeamGroup($eventId, $teamId)
    {
        $match = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'group_stage')
            ->where(function($query) use ($teamId) {
                $query->where('team1_id', $teamId)->orWhere('team2_id', $teamId);
            })
            ->first();

        return $match ? $match->group_number : 1;
    }

    public function getBracketStructure($eventId)
    {
        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->where('m.bracket_type', 'group_stage')
            ->select([
                'm.*',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo'
            ])
            ->orderBy('m.group_number')
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();

        $groups = [];
        $allStandings = $this->calculateGroupStandings($eventId);

        // Group standings by group number
        $standingsByGroup = [];
        foreach ($allStandings as $standing) {
            $groupNum = $standing['group_number'];
            if (!isset($standingsByGroup[$groupNum])) {
                $standingsByGroup[$groupNum] = [];
            }
            $standingsByGroup[$groupNum][] = $standing;
        }

        foreach ($matches as $match) {
            $groupNum = $match->group_number;

            if (!isset($groups[$groupNum])) {
                $groups[$groupNum] = [
                    'group_number' => $groupNum,
                    'group_name' => 'Group ' . chr(64 + $groupNum), // A, B, C, etc.
                    'matches' => [],
                    'standings' => $standingsByGroup[$groupNum] ?? []
                ];
            }

            $matchData = [
                'id' => $match->id,
                'position' => $match->bracket_position,
                'round' => $match->round,
                'team1' => $this->formatTeamData($match, 'team1'),
                'team2' => $this->formatTeamData($match, 'team2'),
                'status' => $match->status,
                'format' => $match->format,
                'scheduled_at' => $match->scheduled_at,
                'completed_at' => $match->completed_at,
                'winner_id' => $this->getWinnerId($match)
            ];

            $groups[$groupNum]['matches'][] = $matchData;
        }

        return [
            'type' => 'group_stage',
            'groups' => array_values($groups),
            'overall_standings' => $allStandings,
            'advancement_rules' => $this->getAdvancementRules($eventId)
        ];
    }

    private function getAdvancementRules($eventId)
    {
        // Default advancement rules - can be customized per tournament
        return [
            'teams_advance_per_group' => 2,
            'advancement_criteria' => [
                'points',
                'map_difference',
                'round_difference',
                'head_to_head'
            ],
            'description' => 'Top 2 teams from each group advance to playoffs'
        ];
    }

    public function getAdvancingTeams($eventId, $teamsPerGroup = 2)
    {
        $allGroups = $this->getGroupNumbers($eventId);
        $advancingTeams = [];

        foreach ($allGroups as $groupNumber) {
            $groupStandings = $this->calculateGroupStandings($eventId, $groupNumber);
            $groupAdvancers = array_slice($groupStandings, 0, $teamsPerGroup);

            foreach ($groupAdvancers as $team) {
                $advancingTeams[] = [
                    'team_id' => $team['team_id'],
                    'team_name' => $team['team_name'],
                    'group_number' => $groupNumber,
                    'group_position' => array_search($team, $groupAdvancers) + 1,
                    'points' => $team['points'],
                    'map_diff' => $team['map_diff']
                ];
            }
        }

        return $advancingTeams;
    }

    private function getGroupNumbers($eventId)
    {
        return DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'group_stage')
            ->distinct()
            ->orderBy('group_number')
            ->pluck('group_number')
            ->toArray();
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

    public function isGroupComplete($eventId, $groupNumber)
    {
        $totalMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'group_stage')
            ->where('group_number', $groupNumber)
            ->count();

        $completedMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'group_stage')
            ->where('group_number', $groupNumber)
            ->where('status', 'completed')
            ->count();

        return $totalMatches > 0 && $totalMatches === $completedMatches;
    }

    public function isAllGroupsComplete($eventId)
    {
        $allGroups = $this->getGroupNumbers($eventId);

        foreach ($allGroups as $groupNumber) {
            if (!$this->isGroupComplete($eventId, $groupNumber)) {
                return false;
            }
        }

        return true;
    }
}