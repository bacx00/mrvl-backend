<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Team;
use App\Models\MatchModel;
use App\Models\Bracket;
use App\Models\EventStanding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarvelRivalsTournamentController extends Controller
{
    // Marvel Rivals specific tournament formats and rules
    private const MARVEL_RIVALS_MAPS = [
        'asgard_throne_room', 'tokyo_2099_afternoon', 'tokyo_2099_evening', 
        'wakanda_palace', 'sanctum_sanctorum', 'klyntar', 'imperial_palace',
        'royal_palace', 'midgard_city', 'symbiote_study'
    ];

    private const GAME_MODES = [
        'convoy' => ['duration' => 8, 'overtime' => 2],
        'domination' => ['duration' => 10, 'overtime' => 2],
        'convergence' => ['duration' => 12, 'overtime' => 3]
    ];

    private const TOURNAMENT_FORMATS = [
        'ignite_stage' => [
            'name' => 'Marvel Rivals Ignite Stage',
            'format' => 'swiss_to_double_elimination',
            'swiss_rounds' => 5,
            'qualification_threshold' => 3, // 3 wins to qualify
            'elimination_threshold' => 3,   // 3 losses to eliminate
            'playoff_teams' => 8,
            'map_pool_size' => 7,
            'match_format' => 'bo3'
        ],
        'invitational' => [
            'name' => 'Marvel Rivals Invitational',
            'format' => 'swiss_to_single_elimination',
            'swiss_rounds' => 3,
            'playoff_teams' => 4,
            'map_pool_size' => 5,
            'match_format' => 'bo3'
        ],
        'championship' => [
            'name' => 'Marvel Rivals Championship',
            'format' => 'double_elimination',
            'map_pool_size' => 7,
            'match_format' => 'bo5',
            'grand_final_format' => 'bo7'
        ]
    ];

    public function generateMarvelRivalsBracket(Request $request, $eventId)
    {
        $request->validate([
            'tournament_format' => 'required|in:ignite_stage,invitational,championship',
            'seeding_method' => 'in:rating,random,manual',
            'map_pool' => 'array|min:5|max:7'
        ]);

        try {
            $event = Event::findOrFail($eventId);
            $this->authorize('update', $event);

            $tournamentFormat = self::TOURNAMENT_FORMATS[$request->tournament_format];
            $teams = $event->teams()->with('players')->get();

            if ($teams->count() < 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least 4 teams required for Marvel Rivals tournament'
                ], 400);
            }

            // Clear existing bracket data
            DB::transaction(function() use ($event, $teams, $tournamentFormat, $request) {
                // Clear existing matches and brackets
                $event->matches()->delete();
                $event->brackets()->delete();
                $event->standings()->delete();

                // Apply seeding
                $seededTeams = $this->applyMarvelRivalsSeeding($teams, $request->seeding_method ?? 'rating');

                // Create tournament structure based on format
                switch ($tournamentFormat['format']) {
                    case 'swiss_to_double_elimination':
                        $this->createIgniteStageFormat($event, $seededTeams, $tournamentFormat);
                        break;
                    case 'swiss_to_single_elimination':
                        $this->createInvitationalFormat($event, $seededTeams, $tournamentFormat);
                        break;
                    case 'double_elimination':
                        $this->createChampionshipFormat($event, $seededTeams, $tournamentFormat);
                        break;
                }

                // Initialize standings
                $this->initializeEventStandings($event, $seededTeams);

                // Update event metadata
                $event->update([
                    'bracket_data' => [
                        'tournament_format' => $request->tournament_format,
                        'format_details' => $tournamentFormat,
                        'map_pool' => $request->map_pool ?? array_slice(self::MARVEL_RIVALS_MAPS, 0, 7),
                        'seeding_method' => $request->seeding_method ?? 'rating',
                        'teams_count' => $teams->count()
                    ],
                    'status' => 'ongoing',
                    'current_round' => 1
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Marvel Rivals tournament bracket generated successfully',
                'data' => [
                    'event_id' => $event->id,
                    'tournament_format' => $request->tournament_format,
                    'teams_count' => $teams->count(),
                    'bracket_url' => "/events/{$event->slug}/bracket"
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    private function createIgniteStageFormat($event, $teams, $format)
    {
        // Stage 1: Swiss System (5 rounds)
        $this->createSwissStage($event, $teams, $format['swiss_rounds']);
        
        // Stage 2: Double Elimination Playoffs (will be created after Swiss completion)
        $this->createPlayoffBracketStructure($event, $format['playoff_teams'], 'double_elimination');
    }

    private function createInvitationalFormat($event, $teams, $format)
    {
        // Stage 1: Swiss System (3 rounds)
        $this->createSwissStage($event, $teams, $format['swiss_rounds']);
        
        // Stage 2: Single Elimination Playoffs
        $this->createPlayoffBracketStructure($event, $format['playoff_teams'], 'single_elimination');
    }

    private function createChampionshipFormat($event, $teams, $format)
    {
        // Direct Double Elimination
        $this->createDoubleEliminationBracket($event, $teams, $format);
    }

    private function createSwissStage($event, $teams, $rounds)
    {
        // Create Swiss bracket structure
        for ($round = 1; $round <= $rounds; $round++) {
            $bracket = Bracket::create([
                'event_id' => $event->id,
                'bracket_type' => 'main',
                'round' => $round,
                'position' => 1,
                'round_name' => "Swiss Round {$round}",
                'bracket_data' => [
                    'stage' => 'swiss',
                    'round_type' => 'swiss_round',
                    'max_matches' => floor($teams->count() / 2)
                ]
            ]);
        }

        // Create first round Swiss matches
        $this->createSwissRoundMatches($event, $teams, 1);
    }

    private function createSwissRoundMatches($event, $teams, $round)
    {
        $teamsArray = $teams->toArray();
        
        if ($round === 1) {
            // First round: pair teams based on seeding (1v2, 3v4, etc.)
            shuffle($teamsArray); // Random first round for Swiss
        } else {
            // Subsequent rounds: pair teams with similar records
            $standings = $this->getSwissStandings($event);
            $teamsArray = $this->pairTeamsByStandings($standings);
        }

        $matches = [];
        for ($i = 0; $i < count($teamsArray); $i += 2) {
            if (isset($teamsArray[$i + 1])) {
                $match = MatchModel::create([
                    'event_id' => $event->id,
                    'team1_id' => $teamsArray[$i]['id'],
                    'team2_id' => $teamsArray[$i + 1]['id'],
                    'round' => $round,
                    'bracket_position' => floor($i / 2) + 1,
                    'status' => $round === 1 ? 'scheduled' : 'pending',
                    'format' => 'bo3',
                    'scheduled_at' => now()->addDays($round - 1),
                    'maps_data' => $this->generateMapPool($event)
                ]);

                $matches[] = $match;
            }
        }

        return $matches;
    }

    private function createDoubleEliminationBracket($event, $teams, $format)
    {
        $teamCount = $teams->count();
        $rounds = ceil(log($teamCount, 2));
        
        // Create upper bracket
        for ($round = 1; $round <= $rounds; $round++) {
            $matchesInRound = $teamCount / pow(2, $round);
            $roundName = $this->getDoubleElimRoundName($round, $rounds, 'upper');
            
            Bracket::create([
                'event_id' => $event->id,
                'bracket_type' => 'upper',
                'round' => $round,
                'position' => 1,
                'round_name' => $roundName,
                'bracket_data' => [
                    'stage' => 'upper_bracket',
                    'matches_count' => $matchesInRound,
                    'advancement' => 'winner_advances'
                ]
            ]);
        }

        // Create lower bracket (more complex structure)
        $this->createLowerBracketStructure($event, $teamCount, $rounds);

        // Create upper bracket first round matches
        $this->createUpperBracketMatches($event, $teams, $format);
    }

    private function createUpperBracketMatches($event, $teams, $format)
    {
        $teamsArray = $teams->toArray();
        $matchFormat = $format['match_format'] ?? 'bo3';

        for ($i = 0; $i < count($teamsArray); $i += 2) {
            if (isset($teamsArray[$i + 1])) {
                MatchModel::create([
                    'event_id' => $event->id,
                    'team1_id' => $teamsArray[$i]['id'],
                    'team2_id' => $teamsArray[$i + 1]['id'],
                    'round' => 1,
                    'bracket_position' => floor($i / 2) + 1,
                    'bracket_type' => 'upper',
                    'status' => 'scheduled',
                    'format' => $matchFormat,
                    'scheduled_at' => now(),
                    'maps_data' => $this->generateMapPool($event)
                ]);
            }
        }
    }

    private function generateMapPool($event)
    {
        $bracketData = $event->bracket_data ?? [];
        $mapPool = $bracketData['map_pool'] ?? array_slice(self::MARVEL_RIVALS_MAPS, 0, 7);
        
        return [
            'map_pool' => $mapPool,
            'map_picks' => [],
            'map_bans' => [],
            'remaining_maps' => $mapPool
        ];
    }

    private function applyMarvelRivalsSeeding($teams, $method)
    {
        switch ($method) {
            case 'rating':
                return $teams->sortByDesc('rating')->values();
            case 'random':
                return $teams->shuffle();
            case 'manual':
                return $teams->sortBy('pivot.seed')->values();
            default:
                return $teams->sortByDesc('rating')->values();
        }
    }

    private function initializeEventStandings($event, $teams)
    {
        foreach ($teams as $index => $team) {
            EventStanding::create([
                'event_id' => $event->id,
                'team_id' => $team->id,
                'position' => $index + 1,
                'wins' => 0,
                'losses' => 0,
                'maps_won' => 0,
                'maps_lost' => 0,
                'status' => 'active',
                'match_history' => []
            ]);
        }
    }

    public function completeSwissRound(Request $request, $eventId, $round)
    {
        $request->validate([
            'matches' => 'required|array',
            'matches.*.match_id' => 'required|exists:matches,id',
            'matches.*.team1_score' => 'required|integer|min:0',
            'matches.*.team2_score' => 'required|integer|min:0'
        ]);

        try {
            $event = Event::findOrFail($eventId);
            $this->authorize('update', $event);

            DB::transaction(function() use ($event, $request, $round) {
                // Update match results
                foreach ($request->matches as $matchData) {
                    $match = MatchModel::findOrFail($matchData['match_id']);
                    $match->update([
                        'team1_score' => $matchData['team1_score'],
                        'team2_score' => $matchData['team2_score'],
                        'status' => 'completed'
                    ]);

                    // Update standings
                    $this->updateSwissStandings($match);
                }

                // Check if Swiss stage is complete
                $bracketData = $event->bracket_data;
                $swissRounds = $bracketData['format_details']['swiss_rounds'];
                
                if ($round >= $swissRounds) {
                    // Swiss stage complete, advance teams to playoffs
                    $this->advanceToPlayoffs($event);
                } else {
                    // Create next Swiss round
                    $teams = $event->teams;
                    $this->createSwissRoundMatches($event, $teams, $round + 1);
                    $event->update(['current_round' => $round + 1]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Swiss round completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing Swiss round: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updateSwissStandings($match)
    {
        $winner = $match->team1_score > $match->team2_score ? $match->team1_id : $match->team2_id;
        $loser = $winner === $match->team1_id ? $match->team2_id : $match->team1_id;

        // Update winner standing
        $winnerStanding = EventStanding::where('event_id', $match->event_id)
                                      ->where('team_id', $winner)
                                      ->first();
        $winnerStanding->updateStats(true, $match->team1_score, $match->team2_score);

        // Update loser standing
        $loserStanding = EventStanding::where('event_id', $match->event_id)
                                     ->where('team_id', $loser)
                                     ->first();
        $loserStanding->updateStats(false, $match->team2_score, $match->team1_score);
    }

    private function getDoubleElimRoundName($round, $totalRounds, $bracket)
    {
        if ($bracket === 'upper') {
            if ($round === $totalRounds) return 'Upper Final';
            if ($round === $totalRounds - 1) return 'Upper Semifinal';
            if ($round === $totalRounds - 2) return 'Upper Quarterfinal';
            return "Upper Round {$round}";
        } else {
            // Lower bracket naming is more complex
            return "Lower Round {$round}";
        }
    }

    public function getBracketVisualization($eventId)
    {
        try {
            $event = Event::with(['brackets', 'matches.team1', 'matches.team2', 'standings.team'])
                          ->findOrFail($eventId);

            $bracketData = $event->bracket_data ?? [];
            $format = $bracketData['tournament_format'] ?? 'single_elimination';

            switch ($format) {
                case 'ignite_stage':
                case 'invitational':
                    return $this->getSwissPlayoffVisualization($event);
                case 'championship':
                    return $this->getDoubleEliminationVisualization($event);
                default:
                    return $this->getSingleEliminationVisualization($event);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getSwissPlayoffVisualization($event)
    {
        $matches = $event->matches()->with(['team1', 'team2'])->get();
        $standings = $event->standings()->with('team')->orderBy('wins', 'desc')->get();

        // Separate Swiss and Playoff matches
        $swissMatches = $matches->where('bracket_type', null)->groupBy('round');
        $playoffMatches = $matches->where('bracket_type', '!=', null)->groupBy('bracket_type');

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event,
                'format' => 'swiss_to_playoffs',
                'swiss_stage' => [
                    'rounds' => $swissMatches,
                    'current_round' => $event->current_round,
                    'standings' => $standings
                ],
                'playoff_stage' => [
                    'matches' => $playoffMatches,
                    'qualified_teams' => $standings->where('status', 'qualified')
                ]
            ]
        ]);
    }

    // Additional helper methods for Swiss standings, playoff advancement, etc.
    private function getSwissStandings($event)
    {
        return $event->standings()
                    ->with('team')
                    ->orderByDesc('wins')
                    ->orderByDesc('maps_won')
                    ->orderBy('maps_lost')
                    ->get();
    }

    private function pairTeamsByStandings($standings)
    {
        // Group teams by record, then pair within groups
        $grouped = $standings->groupBy(function($standing) {
            return $standing->wins . '-' . $standing->losses;
        });

        $pairs = [];
        foreach ($grouped as $record => $teams) {
            $teamArray = $teams->shuffle()->toArray();
            for ($i = 0; $i < count($teamArray); $i += 2) {
                if (isset($teamArray[$i + 1])) {
                    $pairs[] = $teamArray[$i];
                    $pairs[] = $teamArray[$i + 1];
                }
            }
        }

        return $pairs;
    }
}