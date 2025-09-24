<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompleteTournamentGeneratorService
{
    /**
     * Generate complete tournament structure with all stages and matches
     * Everything is created upfront - users just need to fill in teams and scores
     */
    public function generateCompleteTournament($eventId, $format, $config = [])
    {
        DB::beginTransaction();

        try {
            // Clear existing tournament data
            $this->clearExistingTournamentData($eventId);

            // Get format configuration
            $formatConfig = $this->getFormatConfiguration($format);

            // Generate all stages with all matches
            $stages = [];
            foreach ($formatConfig['stages'] as $index => $stageConfig) {
                $stage = $this->generateStage(
                    $eventId,
                    $stageConfig,
                    $index + 1,
                    $config['team_count'] ?? 32
                );
                $stages[] = $stage;
            }

            // Save tournament structure to database
            $this->saveTournamentStructure($eventId, $format, $stages, $config);

            DB::commit();

            return [
                'success' => true,
                'format' => $format,
                'stages' => $stages,
                'total_matches' => $this->countTotalMatches($stages),
                'message' => 'Tournament structure created. Add teams and scores to begin.'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function clearExistingTournamentData($eventId)
    {
        DB::table('bracket_stages')->where('event_id', $eventId)->delete();
        DB::table('matches')->where('event_id', $eventId)->delete();
    }

    private function getFormatConfiguration($format)
    {
        $formats = [
            'mrc_championship' => [
                'name' => 'MRC Championship',
                'stages' => [
                    [
                        'id' => 'open_qualifiers',
                        'name' => 'Open Qualifiers',
                        'type' => 'swiss',
                        'rounds' => 5,
                        'match_format' => 'Bo3',
                        'teams_advance' => 32
                    ],
                    [
                        'id' => 'closed_qualifiers',
                        'name' => 'Closed Qualifiers',
                        'type' => 'single_elimination',
                        'teams' => 32,
                        'match_format' => 'Bo3',
                        'teams_advance' => 8
                    ],
                    [
                        'id' => 'playoffs',
                        'name' => 'Playoffs',
                        'type' => 'double_elimination',
                        'teams' => 8,
                        'upper_format' => 'Bo5',
                        'lower_format' => 'Bo5',
                        'grand_final_format' => 'Bo7'
                    ]
                ]
            ],
            'ignite_circuit' => [
                'name' => 'Ignite Circuit',
                'stages' => [
                    [
                        'id' => 'group_stage',
                        'name' => 'Group Stage',
                        'type' => 'groups_double_elimination',
                        'groups' => 2,
                        'teams_per_group' => 8,
                        'match_format' => 'Bo3',
                        'lower_final_format' => 'Bo5',
                        'grand_final_format' => 'Bo5',
                        'teams_advance_per_group' => 4
                    ],
                    [
                        'id' => 'playoffs',
                        'name' => 'Playoffs',
                        'type' => 'double_elimination',
                        'teams' => 8,
                        'upper_format' => 'Bo5',
                        'lower_format' => 'Bo5',
                        'grand_final_format' => 'Bo7'
                    ]
                ]
            ],
            'invitational' => [
                'name' => 'Invitational',
                'stages' => [
                    [
                        'id' => 'main_event',
                        'name' => 'Main Event',
                        'type' => 'double_elimination',
                        'teams' => 8,
                        'upper_format' => 'Bo5',
                        'lower_format' => 'Bo5',
                        'grand_final_format' => 'Bo7'
                    ]
                ]
            ],
            'single_elimination' => [
                'name' => 'Single Elimination',
                'stages' => [
                    [
                        'id' => 'bracket',
                        'name' => 'Main Bracket',
                        'type' => 'single_elimination',
                        'match_format' => 'Bo3',
                        'semifinal_format' => 'Bo5',
                        'final_format' => 'Bo5',
                        'third_place_match' => true
                    ]
                ]
            ],
            'double_elimination' => [
                'name' => 'Double Elimination',
                'stages' => [
                    [
                        'id' => 'bracket',
                        'name' => 'Double Elimination Bracket',
                        'type' => 'double_elimination',
                        'upper_format' => 'Bo3',
                        'lower_format' => 'Bo3',
                        'grand_final_format' => 'Bo5',
                        'bracket_reset' => true
                    ]
                ]
            ],
            'swiss_system' => [
                'name' => 'Swiss System',
                'stages' => [
                    [
                        'id' => 'swiss',
                        'name' => 'Swiss Rounds',
                        'type' => 'swiss',
                        'rounds' => 5,
                        'match_format' => 'Bo3'
                    ]
                ]
            ],
            'round_robin' => [
                'name' => 'Round Robin',
                'stages' => [
                    [
                        'id' => 'round_robin',
                        'name' => 'Round Robin',
                        'type' => 'round_robin',
                        'match_format' => 'Bo3'
                    ]
                ]
            ],
            'gsl_groups' => [
                'name' => 'GSL Groups',
                'stages' => [
                    [
                        'id' => 'groups',
                        'name' => 'GSL Group Stage',
                        'type' => 'gsl',
                        'groups' => 4,
                        'teams_per_group' => 4,
                        'match_format' => 'Bo3',
                        'teams_advance_per_group' => 2
                    ],
                    [
                        'id' => 'playoffs',
                        'name' => 'Playoffs',
                        'type' => 'single_elimination',
                        'teams' => 8,
                        'match_format' => 'Bo5',
                        'final_format' => 'Bo7'
                    ]
                ]
            ]
        ];

        return $formats[$format] ?? $formats['single_elimination'];
    }

    private function generateStage($eventId, $stageConfig, $stageOrder, $totalTeams)
    {
        $stageId = DB::table('bracket_stages')->insertGetId([
            'event_id' => $eventId,
            'name' => $stageConfig['name'],
            'type' => $stageConfig['type'],
            'stage_order' => $stageOrder,
            'status' => $stageOrder === 1 ? 'ready' : 'pending',
            'settings' => json_encode($stageConfig),
            'max_teams' => $stageConfig['teams'] ?? $totalTeams,
            'current_round' => 0,
            'total_rounds' => $this->calculateTotalRounds($stageConfig),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Generate all matches for this stage
        $matches = $this->generateStageMatches($eventId, $stageId, $stageConfig);

        return [
            'id' => $stageId,
            'name' => $stageConfig['name'],
            'type' => $stageConfig['type'],
            'order' => $stageOrder,
            'settings' => $stageConfig,
            'matches' => $matches,
            'total_matches' => count($matches)
        ];
    }

    private function generateStageMatches($eventId, $stageId, $config)
    {
        switch ($config['type']) {
            case 'single_elimination':
                return $this->generateSingleEliminationMatches($eventId, $stageId, $config);

            case 'double_elimination':
                return $this->generateDoubleEliminationMatches($eventId, $stageId, $config);

            case 'swiss':
                return $this->generateSwissMatches($eventId, $stageId, $config);

            case 'round_robin':
                return $this->generateRoundRobinMatches($eventId, $stageId, $config);

            case 'groups_double_elimination':
                return $this->generateGroupsDoubleEliminationMatches($eventId, $stageId, $config);

            case 'gsl':
                return $this->generateGSLGroupMatches($eventId, $stageId, $config);

            default:
                return [];
        }
    }

    private function generateSingleEliminationMatches($eventId, $stageId, $config)
    {
        $matches = [];
        $teams = $config['teams'] ?? 32;
        $rounds = ceil(log($teams, 2));

        // Round names
        $roundNames = $this->getRoundNames($rounds);

        // Generate all rounds
        for ($round = 1; $round <= $rounds; $round++) {
            $matchesInRound = pow(2, $rounds - $round);

            for ($match = 1; $match <= $matchesInRound; $match++) {
                // Determine match format based on round
                $format = 'Bo3'; // Default
                if ($round === $rounds) {
                    $format = $config['final_format'] ?? 'Bo5';
                } else if ($round === $rounds - 1) {
                    $format = $config['semifinal_format'] ?? 'Bo5';
                } else {
                    $format = $config['match_format'] ?? 'Bo3';
                }

                $matchId = DB::table('matches')->insertGetId([
                    'event_id' => $eventId,
                    'stage_id' => $stageId,
                    'round_number' => $round,
                    'round_name' => $roundNames[$round],
                    'match_number' => $match,
                    'team1_id' => null,
                    'team2_id' => null,
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'pending',
                    'best_of' => $this->getBoNumber($format),
                    'match_format' => $format,
                    'scheduled_at' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $matches[] = [
                    'id' => $matchId,
                    'round' => $round,
                    'round_name' => $roundNames[$round],
                    'match' => $match,
                    'format' => $format,
                    'team1' => null,
                    'team2' => null
                ];
            }
        }

        // Add third place match if configured
        if ($config['third_place_match'] ?? false) {
            $matchId = DB::table('matches')->insertGetId([
                'event_id' => $eventId,
                'stage_id' => $stageId,
                'round_number' => $rounds,
                'round_name' => 'Third Place Match',
                'match_number' => 99,
                'team1_id' => null,
                'team2_id' => null,
                'team1_score' => 0,
                'team2_score' => 0,
                'status' => 'pending',
                'best_of' => $this->getBoNumber($config['third_place_format'] ?? 'Bo3'),
                'match_format' => $config['third_place_format'] ?? 'Bo3',
                'is_third_place' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $matches[] = [
                'id' => $matchId,
                'round' => $rounds,
                'round_name' => 'Third Place Match',
                'format' => $config['third_place_format'] ?? 'Bo3',
                'is_third_place' => true
            ];
        }

        return $matches;
    }

    private function generateDoubleEliminationMatches($eventId, $stageId, $config)
    {
        $matches = [];
        $teams = $config['teams'] ?? 8;
        $upperRounds = ceil(log($teams, 2));

        // UPPER BRACKET
        for ($round = 1; $round <= $upperRounds; $round++) {
            $matchesInRound = pow(2, $upperRounds - $round);
            $roundName = $this->getUpperBracketRoundName($round, $upperRounds);

            for ($match = 1; $match <= $matchesInRound; $match++) {
                $matchId = DB::table('matches')->insertGetId([
                    'event_id' => $eventId,
                    'stage_id' => $stageId,
                    'bracket_type' => 'upper',
                    'round_number' => $round,
                    'round_name' => $roundName,
                    'match_number' => $match,
                    'team1_id' => null,
                    'team2_id' => null,
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'pending',
                    'best_of' => $this->getBoNumber($config['upper_format'] ?? 'Bo3'),
                    'match_format' => $config['upper_format'] ?? 'Bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $matches[] = [
                    'id' => $matchId,
                    'bracket' => 'upper',
                    'round' => $round,
                    'round_name' => $roundName,
                    'match' => $match,
                    'format' => $config['upper_format'] ?? 'Bo3'
                ];
            }
        }

        // LOWER BRACKET
        $lowerRounds = ($upperRounds - 1) * 2;
        for ($round = 1; $round <= $lowerRounds; $round++) {
            $isDropRound = $round % 2 === 1;
            $effectiveRound = ceil($round / 2);
            $matchesInRound = pow(2, $upperRounds - $effectiveRound - ($isDropRound ? 0 : 1));
            $roundName = $this->getLowerBracketRoundName($round, $lowerRounds);

            for ($match = 1; $match <= $matchesInRound; $match++) {
                $matchId = DB::table('matches')->insertGetId([
                    'event_id' => $eventId,
                    'stage_id' => $stageId,
                    'bracket_type' => 'lower',
                    'round_number' => $round,
                    'round_name' => $roundName,
                    'match_number' => $match,
                    'team1_id' => null,
                    'team2_id' => null,
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'pending',
                    'best_of' => $this->getBoNumber($config['lower_format'] ?? 'Bo3'),
                    'match_format' => $config['lower_format'] ?? 'Bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $matches[] = [
                    'id' => $matchId,
                    'bracket' => 'lower',
                    'round' => $round,
                    'round_name' => $roundName,
                    'match' => $match,
                    'format' => $config['lower_format'] ?? 'Bo3'
                ];
            }
        }

        // GRAND FINAL
        $matchId = DB::table('matches')->insertGetId([
            'event_id' => $eventId,
            'stage_id' => $stageId,
            'bracket_type' => 'grand_final',
            'round_number' => 1,
            'round_name' => 'Grand Final',
            'match_number' => 1,
            'team1_id' => null,
            'team2_id' => null,
            'team1_score' => 0,
            'team2_score' => 0,
            'status' => 'pending',
            'best_of' => $this->getBoNumber($config['grand_final_format'] ?? 'Bo7'),
            'match_format' => $config['grand_final_format'] ?? 'Bo7',
            'is_grand_final' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $matches[] = [
            'id' => $matchId,
            'bracket' => 'grand_final',
            'round_name' => 'Grand Final',
            'format' => $config['grand_final_format'] ?? 'Bo7'
        ];

        // BRACKET RESET (if enabled)
        if ($config['bracket_reset'] ?? false) {
            $matchId = DB::table('matches')->insertGetId([
                'event_id' => $eventId,
                'stage_id' => $stageId,
                'bracket_type' => 'grand_final',
                'round_number' => 2,
                'round_name' => 'Grand Final (Bracket Reset)',
                'match_number' => 2,
                'team1_id' => null,
                'team2_id' => null,
                'team1_score' => 0,
                'team2_score' => 0,
                'status' => 'pending',
                'best_of' => $this->getBoNumber($config['grand_final_format'] ?? 'Bo7'),
                'match_format' => $config['grand_final_format'] ?? 'Bo7',
                'is_bracket_reset' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $matches[] = [
                'id' => $matchId,
                'bracket' => 'grand_final',
                'round_name' => 'Grand Final (Bracket Reset)',
                'format' => $config['grand_final_format'] ?? 'Bo7',
                'is_bracket_reset' => true
            ];
        }

        return $matches;
    }

    private function generateSwissMatches($eventId, $stageId, $config)
    {
        $matches = [];
        $teams = $config['teams'] ?? 32;
        $rounds = $config['rounds'] ?? ceil(log($teams, 2));

        for ($round = 1; $round <= $rounds; $round++) {
            $matchesInRound = floor($teams / 2);

            for ($match = 1; $match <= $matchesInRound; $match++) {
                $matchId = DB::table('matches')->insertGetId([
                    'event_id' => $eventId,
                    'stage_id' => $stageId,
                    'round_number' => $round,
                    'round_name' => "Swiss Round $round",
                    'match_number' => $match,
                    'team1_id' => null,
                    'team2_id' => null,
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'pending',
                    'best_of' => $this->getBoNumber($config['match_format'] ?? 'Bo3'),
                    'match_format' => $config['match_format'] ?? 'Bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $matches[] = [
                    'id' => $matchId,
                    'round' => $round,
                    'round_name' => "Swiss Round $round",
                    'match' => $match,
                    'format' => $config['match_format'] ?? 'Bo3'
                ];
            }
        }

        return $matches;
    }

    private function generateRoundRobinMatches($eventId, $stageId, $config)
    {
        $matches = [];
        $teams = $config['teams'] ?? 8;
        $matchNumber = 1;

        // Generate all possible matchups
        for ($i = 1; $i <= $teams; $i++) {
            for ($j = $i + 1; $j <= $teams; $j++) {
                $matchId = DB::table('matches')->insertGetId([
                    'event_id' => $eventId,
                    'stage_id' => $stageId,
                    'round_number' => 1,
                    'round_name' => 'Round Robin',
                    'match_number' => $matchNumber++,
                    'team1_id' => null,
                    'team2_id' => null,
                    'team1_placeholder' => "Team $i",
                    'team2_placeholder' => "Team $j",
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'pending',
                    'best_of' => $this->getBoNumber($config['match_format'] ?? 'Bo3'),
                    'match_format' => $config['match_format'] ?? 'Bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $matches[] = [
                    'id' => $matchId,
                    'match' => $matchNumber - 1,
                    'team1_placeholder' => "Team $i",
                    'team2_placeholder' => "Team $j",
                    'format' => $config['match_format'] ?? 'Bo3'
                ];
            }
        }

        return $matches;
    }

    private function generateGroupsDoubleEliminationMatches($eventId, $stageId, $config)
    {
        $matches = [];
        $groups = $config['groups'] ?? 2;
        $teamsPerGroup = $config['teams_per_group'] ?? 8;

        for ($g = 1; $g <= $groups; $g++) {
            $groupName = chr(64 + $g); // A, B, C, etc.

            // Generate double elimination bracket for each group
            $groupConfig = [
                'teams' => $teamsPerGroup,
                'upper_format' => $config['match_format'] ?? 'Bo3',
                'lower_format' => $config['match_format'] ?? 'Bo3',
                'grand_final_format' => $config['grand_final_format'] ?? 'Bo5'
            ];

            $groupMatches = $this->generateDoubleEliminationMatches($eventId, $stageId, $groupConfig);

            // Update group matches with group identifier
            foreach ($groupMatches as &$match) {
                $match['group'] = $groupName;
                DB::table('matches')
                    ->where('id', $match['id'])
                    ->update(['group_name' => "Group $groupName"]);
            }

            $matches = array_merge($matches, $groupMatches);
        }

        return $matches;
    }

    private function generateGSLGroupMatches($eventId, $stageId, $config)
    {
        $matches = [];
        $groups = $config['groups'] ?? 4;

        for ($g = 1; $g <= $groups; $g++) {
            $groupName = chr(64 + $g);

            // GSL Format: 5 matches per group
            $gslMatches = [
                ['name' => 'Opening Match 1', 'team1' => 1, 'team2' => 4],
                ['name' => 'Opening Match 2', 'team1' => 2, 'team2' => 3],
                ['name' => 'Winners Match', 'team1' => 'W1', 'team2' => 'W2'],
                ['name' => 'Losers Match', 'team1' => 'L1', 'team2' => 'L2'],
                ['name' => 'Decider Match', 'team1' => 'LW', 'team2' => 'WL']
            ];

            foreach ($gslMatches as $index => $gslMatch) {
                $matchId = DB::table('matches')->insertGetId([
                    'event_id' => $eventId,
                    'stage_id' => $stageId,
                    'group_name' => "Group $groupName",
                    'round_number' => $index < 2 ? 1 : ($index < 4 ? 2 : 3),
                    'round_name' => $gslMatch['name'],
                    'match_number' => $index + 1,
                    'team1_id' => null,
                    'team2_id' => null,
                    'team1_placeholder' => is_string($gslMatch['team1']) ? $gslMatch['team1'] : "Seed {$gslMatch['team1']}",
                    'team2_placeholder' => is_string($gslMatch['team2']) ? $gslMatch['team2'] : "Seed {$gslMatch['team2']}",
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'pending',
                    'best_of' => $this->getBoNumber($config['match_format'] ?? 'Bo3'),
                    'match_format' => $config['match_format'] ?? 'Bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $matches[] = [
                    'id' => $matchId,
                    'group' => $groupName,
                    'round_name' => $gslMatch['name'],
                    'format' => $config['match_format'] ?? 'Bo3'
                ];
            }
        }

        return $matches;
    }

    private function calculateTotalRounds($config)
    {
        switch ($config['type']) {
            case 'single_elimination':
                return ceil(log(($config['teams'] ?? 32), 2));
            case 'double_elimination':
                return (ceil(log(($config['teams'] ?? 8), 2)) - 1) * 2 + 1;
            case 'swiss':
                return $config['rounds'] ?? 5;
            case 'round_robin':
                return ($config['teams'] ?? 8) - 1;
            case 'gsl':
                return 3;
            default:
                return 1;
        }
    }

    private function getRoundNames($totalRounds)
    {
        $names = [];
        for ($i = 1; $i <= $totalRounds; $i++) {
            $remaining = $totalRounds - $i;
            switch ($remaining) {
                case 0:
                    $names[$i] = 'Final';
                    break;
                case 1:
                    $names[$i] = 'Semifinals';
                    break;
                case 2:
                    $names[$i] = 'Quarterfinals';
                    break;
                case 3:
                    $names[$i] = 'Round of 16';
                    break;
                case 4:
                    $names[$i] = 'Round of 32';
                    break;
                case 5:
                    $names[$i] = 'Round of 64';
                    break;
                case 6:
                    $names[$i] = 'Round of 128';
                    break;
                default:
                    $names[$i] = "Round $i";
            }
        }
        return $names;
    }

    private function getUpperBracketRoundName($round, $totalRounds)
    {
        $remaining = $totalRounds - $round;
        switch ($remaining) {
            case 0:
                return 'Upper Final';
            case 1:
                return 'Upper Semifinals';
            case 2:
                return 'Upper Quarterfinals';
            default:
                return "Upper Round $round";
        }
    }

    private function getLowerBracketRoundName($round, $totalRounds)
    {
        if ($round === $totalRounds) {
            return 'Lower Final';
        } else if ($round === $totalRounds - 1) {
            return 'Lower Semifinals';
        } else {
            return "Lower Round $round";
        }
    }

    private function getBoNumber($format)
    {
        $formats = [
            'Bo1' => 1,
            'Bo2' => 2,
            'Bo3' => 3,
            'Bo4' => 4,
            'Bo5' => 5,
            'Bo6' => 6,
            'Bo7' => 7,
            'Bo8' => 8,
            'Bo9' => 9
        ];
        return $formats[$format] ?? 3;
    }

    public static function getWinsNeeded($format)
    {
        $winsNeeded = [
            'Bo1' => 1,
            'Bo2' => 2,  // Must win both
            'Bo3' => 2,
            'Bo4' => 3,  // Rare format
            'Bo5' => 3,
            'Bo6' => 4,  // Rare format
            'Bo7' => 4,
            'Bo8' => 5,  // Rare format
            'Bo9' => 5
        ];
        return $winsNeeded[$format] ?? ceil(intval(str_replace('Bo', '', $format)) / 2);
    }

    private function saveTournamentStructure($eventId, $format, $stages, $config)
    {
        // Update event with tournament details
        DB::table('events')->where('id', $eventId)->update([
            'format' => $format,
            'tournament_structure' => json_encode([
                'format' => $format,
                'stages' => $stages,
                'config' => $config,
                'generated_at' => now()
            ]),
            'status' => 'setup',
            'updated_at' => now()
        ]);
    }

    private function countTotalMatches($stages)
    {
        $total = 0;
        foreach ($stages as $stage) {
            $total += $stage['total_matches'];
        }
        return $total;
    }
}