<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\BracketGame;
use App\Models\BracketSeeding;
use App\Models\BracketStanding;
use App\Services\BracketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

class BracketController extends Controller
{
    public function show($eventId)
    {
        try {
            // Use cache to improve performance
            $cacheKey = "bracket_data_{$eventId}";
            $cacheTimeout = 30; // 30 seconds cache for live tournaments

            $event = cache()->remember("event_{$eventId}", $cacheTimeout, function() use ($eventId) {
                return DB::table('events')->where('id', $eventId)->first();
            });

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Get bracket structure based on event format with caching
            $bracket = cache()->remember($cacheKey, $cacheTimeout, function() use ($eventId, $event) {
                return $this->generateBracket($eventId, $event->format);
            });

            // Get metadata efficiently in a single query batch
            $metadata = cache()->remember("bracket_metadata_{$eventId}", $cacheTimeout, function() use ($eventId, $event) {
                $teamsCount = DB::table('event_teams')->where('event_id', $eventId)->count();
                $matchStats = DB::table('matches')
                    ->where('event_id', $eventId)
                    ->selectRaw('
                        COUNT(*) as total_matches,
                        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_matches,
                        MIN(CASE WHEN status = "scheduled" THEN round ELSE NULL END) as current_round
                    ')
                    ->first();

                return [
                    'total_rounds' => $this->calculateTotalRounds($eventId, $event->format),
                    'teams_count' => $teamsCount,
                    'matches_completed' => $matchStats->completed_matches ?? 0,
                    'total_matches' => $matchStats->total_matches ?? 0,
                    'current_round' => $matchStats->current_round ?? 1,
                    'completion_percentage' => $matchStats->total_matches > 0 
                        ? round(($matchStats->completed_matches / $matchStats->total_matches) * 100, 1) 
                        : 0
                ];
            });

            return response()->json([
                'data' => [
                    'event_id' => $eventId,
                    'event_name' => $event->name,
                    'format' => $event->format,
                    'status' => $event->status,
                    'bracket' => $bracket,
                    'metadata' => $metadata,
                    'last_updated' => now()->toISOString()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            \Log::error('Bracket show error', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generate(Request $request, $eventId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'format' => 'required|in:single_elimination,double_elimination,round_robin,swiss',
            'seeding_method' => 'required|in:random,rating,manual',
            'randomize_seeds' => 'boolean'
        ]);

        try {
            $event = DB::table('events')->where('id', $eventId)->first();
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }

            // Get participating teams
            $teams = $this->getEventTeams($eventId);
            if (count($teams) < 2) {
                return response()->json(['success' => false, 'message' => 'Need at least 2 teams to generate bracket'], 400);
            }

            // Clear existing matches
            DB::table('matches')->where('event_id', $eventId)->delete();

            // Apply seeding
            $seededTeams = $this->applySeedingMethod($teams, $request->seeding_method, $request->randomize_seeds);

            // Generate bracket based on format
            $matches = $this->createBracketMatches($eventId, $seededTeams, $request->format);

            // Save matches to database
            foreach ($matches as $match) {
                DB::table('matches')->insert($match);
            }

            // Update event status
            DB::table('events')->where('id', $eventId)->update([
                'status' => 'ongoing',
                'format' => $request->format,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bracket generated successfully',
                'data' => [
                    'matches_created' => count($matches),
                    'format' => $request->format,
                    'teams_count' => count($seededTeams)
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateMatch(Request $request, $matchId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'status' => 'required|in:completed,cancelled',
            'maps_data' => 'nullable|array'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            // Update match
            DB::table('matches')->where('id', $matchId)->update([
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'status' => $request->status,
                'maps_data' => $request->maps_data ? json_encode($request->maps_data) : null,
                'completed_at' => $request->status === 'completed' ? now() : null,
                'updated_at' => now()
            ]);

            // If match completed, advance winner and check for bracket progression
            if ($request->status === 'completed') {
                $this->processMatchCompletion($matchId, $request->team1_score, $request->team2_score);
            }

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating match: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for bracket generation

    private function generateBracket($eventId, $format)
    {
        switch ($format) {
            case 'single_elimination':
                return $this->generateSingleEliminationBracket($eventId);
            case 'double_elimination':
                return $this->generateDoubleEliminationBracket($eventId);
            case 'round_robin':
                return $this->generateRoundRobinBracket($eventId);
            case 'swiss':
                return $this->generateSwissBracket($eventId);
            default:
                return $this->generateSingleEliminationBracket($eventId);
        }
    }

    private function generateSingleEliminationBracket($eventId)
    {
        // First check if bracket_data exists in events table
        $event = DB::table('events')->where('id', $eventId)->first();
        if ($event && $event->bracket_data) {
            $bracketData = json_decode($event->bracket_data, true);
            
            // Return the bracket data in the expected format
            $bracket = [
                'type' => $bracketData['format'] ?? 'single_elimination',
                'rounds' => [],
                'matches' => []
            ];
            
            // Process rounds from bracket_data
            if (isset($bracketData['rounds']) && is_array($bracketData['rounds'])) {
                foreach ($bracketData['rounds'] as $roundIndex => $round) {
                    $roundData = [
                        'round_number' => $round['id'] ?? ($roundIndex + 1),
                        'round_name' => $round['name'] ?? 'Round ' . ($roundIndex + 1),
                        'matches' => []
                    ];
                    
                    if (isset($round['matches']) && is_array($round['matches'])) {
                        foreach ($round['matches'] as $match) {
                            $matchData = [
                                'id' => $match['id'] ?? null,
                                'position' => $match['matchNumber'] ?? 0,
                                'team1' => $match['team1'] ?? null,
                                'team2' => $match['team2'] ?? null,
                                'score1' => $match['score1'] ?? 0,
                                'score2' => $match['score2'] ?? 0,
                                'status' => $match['status'] ?? 'pending',
                                'bestOf' => $match['bestOf'] ?? 3,
                                'winner' => $match['winner'] ?? null
                            ];
                            $roundData['matches'][] = $matchData;
                            $bracket['matches'][] = $matchData;
                        }
                    }
                    
                    $bracket['rounds'][] = $roundData;
                }
            }
            
            return $bracket;
        }
        
        // Fallback to bracket_matches table if no bracket_data
        $matches = DB::table('bracket_matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->select([
                'm.*',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo'
            ])
            ->orderBy('m.round_number')
            ->orderBy('m.match_number')
            ->get();

        // If no matches found in bracket_matches, try regular matches table
        if ($matches->isEmpty()) {
            $matches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.event_id', $eventId)
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo'
                ])
                ->orderBy('m.round')
                ->orderBy('m.bracket_position')
                ->get();
        }

        // Group by rounds
        $bracket = [
            'type' => 'single_elimination',
            'rounds' => [],
            'matches' => []
        ];

        foreach ($matches as $match) {
            $roundName = isset($match->round_name) ? $match->round_name : $this->getRoundName($match->round ?? 1, $this->getEventTeamCount($eventId));
            $roundNumber = $match->round_number ?? $match->round ?? 1;
            
            if (!isset($bracket['rounds'][$roundName])) {
                $bracket['rounds'][$roundName] = [
                    'round_number' => $roundNumber,
                    'matches' => []
                ];
            }

            $matchData = [
                'id' => $match->id,
                'match_id' => $match->match_id ?? $match->id,
                'position' => $match->match_number ?? $match->bracket_position ?? 1,
                'team1' => [
                    'id' => $match->team1_id,
                    'name' => $match->team1_name,
                    'short_name' => $match->team1_short,
                    'logo' => $match->team1_logo,
                    'score' => $match->team1_score,
                    'seed' => $this->getTeamSeed($eventId, $match->team1_id)
                ],
                'team2' => [
                    'id' => $match->team2_id,
                    'name' => $match->team2_name,
                    'short_name' => $match->team2_short,
                    'logo' => $match->team2_logo,
                    'score' => $match->team2_score,
                    'seed' => $this->getTeamSeed($eventId, $match->team2_id)
                ],
                'status' => $match->status,
                'best_of' => $match->best_of ?? 3,
                'winner_id' => $match->winner_id ?? null
            ];
            
            $bracket['rounds'][$roundName]['matches'][] = $matchData;
            $bracket['matches'][] = $matchData;
        }

        // Convert rounds to indexed array
        $bracket['rounds'] = array_values($bracket['rounds']);

        return $bracket;
    }

    private function generateDoubleEliminationBracket($eventId)
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
                'team1' => [
                    'id' => $match->team1_id,
                    'name' => $match->team1_name,
                    'short_name' => $match->team1_short,
                    'logo' => $match->team1_logo,
                    'score' => $match->team1_score,
                    'seed' => $this->getTeamSeed($eventId, $match->team1_id)
                ],
                'team2' => [
                    'id' => $match->team2_id,
                    'name' => $match->team2_name,
                    'short_name' => $match->team2_short,
                    'logo' => $match->team2_logo,
                    'score' => $match->team2_score,
                    'seed' => $this->getTeamSeed($eventId, $match->team2_id)
                ],
                'status' => $match->status,
                'scheduled_at' => $match->scheduled_at,
                'completed_at' => $match->completed_at,
                'winner_id' => $this->getMatchWinner($match),
                'stream_url' => $match->stream_url,
                'maps_data' => $match->maps_data ? json_decode($match->maps_data, true) : null
            ];

            if ($match->bracket_type === 'grand_final') {
                $bracket['grand_final'] = $matchData;
            } elseif ($match->bracket_type === 'upper') {
                $roundName = 'Upper Round ' . $match->round;
                if (!isset($bracket['upper_bracket'][$roundName])) {
                    $bracket['upper_bracket'][$roundName] = [
                        'round_number' => $match->round,
                        'matches' => []
                    ];
                }
                $bracket['upper_bracket'][$roundName]['matches'][] = $matchData;
            } else { // lower bracket
                $roundName = 'Lower Round ' . $match->round;
                if (!isset($bracket['lower_bracket'][$roundName])) {
                    $bracket['lower_bracket'][$roundName] = [
                        'round_number' => $match->round,
                        'matches' => []
                    ];
                }
                $bracket['lower_bracket'][$roundName]['matches'][] = $matchData;
            }
        }

        return $bracket;
    }

    private function generateRoundRobinBracket($eventId)
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
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();

        // Calculate standings
        $standings = $this->calculateRoundRobinStandings($eventId);

        return [
            'type' => 'round_robin',
            'matches' => $matches->map(function($match) use ($eventId) {
                return [
                    'id' => $match->id,
                    'round' => $match->round,
                    'team1' => [
                        'id' => $match->team1_id,
                        'name' => $match->team1_name,
                        'short_name' => $match->team1_short,
                        'logo' => $match->team1_logo,
                        'score' => $match->team1_score
                    ],
                    'team2' => [
                        'id' => $match->team2_id,
                        'name' => $match->team2_name,
                        'short_name' => $match->team2_short,
                        'logo' => $match->team2_logo,
                        'score' => $match->team2_score
                    ],
                    'status' => $match->status,
                    'scheduled_at' => $match->scheduled_at,
                    'completed_at' => $match->completed_at,
                    'winner_id' => $this->getMatchWinner($match)
                ];
            }),
            'standings' => $standings
        ];
    }

    private function generateSwissBracket($eventId)
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
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();

        // Group by rounds and calculate standings
        $rounds = [];
        foreach ($matches as $match) {
            $rounds[$match->round][] = [
                'id' => $match->id,
                'team1' => [
                    'id' => $match->team1_id,
                    'name' => $match->team1_name,
                    'short_name' => $match->team1_short,
                    'logo' => $match->team1_logo,
                    'score' => $match->team1_score
                ],
                'team2' => [
                    'id' => $match->team2_id,
                    'name' => $match->team2_name,
                    'short_name' => $match->team2_short,
                    'logo' => $match->team2_logo,
                    'score' => $match->team2_score
                ],
                'status' => $match->status,
                'scheduled_at' => $match->scheduled_at,
                'completed_at' => $match->completed_at,
                'winner_id' => $this->getMatchWinner($match)
            ];
        }

        return [
            'type' => 'swiss',
            'rounds' => $rounds,
            'standings' => $this->calculateSwissStandings($eventId),
            'current_round' => $this->getCurrentRound($eventId),
            'total_rounds' => $this->calculateSwissRounds($this->getEventTeamCount($eventId))
        ];
    }

    private function createBracketMatches($eventId, $teams, $format)
    {
        switch ($format) {
            case 'single_elimination':
                return $this->createSingleEliminationMatches($eventId, $teams);
            case 'double_elimination':
                return $this->createDoubleEliminationMatches($eventId, $teams);
            case 'round_robin':
                return $this->createRoundRobinMatches($eventId, $teams);
            case 'swiss':
                return $this->createSwissMatches($eventId, $teams);
            default:
                return $this->createSingleEliminationMatches($eventId, $teams);
        }
    }

    private function createSingleEliminationMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
        $rounds = $this->safeCalculateRounds($teamCount);
        
        // First round matches
        $round = 1;
        $position = 1;
        
        for ($i = 0; $i < $teamCount; $i += 2) {
            if (isset($teams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'main',
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$i + 1]['id'],
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }
        
        // Create placeholder matches for subsequent rounds
        $currentMatches = count($matches);
        for ($r = 2; $r <= $rounds; $r++) {
            $matchesInRound = $currentMatches / 2;
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'main',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $currentMatches = $matchesInRound;
        }
        
        return $matches;
    }

    private function createDoubleEliminationMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
        
        // Upper bracket (same as single elimination)
        $upperMatches = $this->createSingleEliminationMatches($eventId, $teams);
        foreach ($upperMatches as &$match) {
            $match['bracket_type'] = 'upper';
        }
        $matches = array_merge($matches, $upperMatches);
        
        // Lower bracket (more complex, depends on upper bracket structure)
        $lowerBracketMatches = $this->createLowerBracketMatches($eventId, $teamCount);
        $matches = array_merge($matches, $lowerBracketMatches);
        
        // Grand final
        $matches[] = [
            'event_id' => $eventId,
            'round' => 1,
            'bracket_position' => 1,
            'bracket_type' => 'grand_final',
            'team1_id' => null,
            'team2_id' => null,
            'status' => 'pending',
            'format' => 'bo5',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        return $matches;
    }

    private function createRoundRobinMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
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
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
                
                // Distribute matches across rounds
                if ($position > ($teamCount / 2)) {
                    $round++;
                    $position = 1;
                }
            }
        }
        
        return $matches;
    }

    private function createSwissMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
        $totalRounds = $this->calculateSwissRounds($teamCount);
        
        // First round: random pairing
        shuffle($teams);
        $round = 1;
        $position = 1;
        
        for ($i = 0; $i < $teamCount; $i += 2) {
            if (isset($teams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'swiss',
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$i + 1]['id'],
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }
        
        // Subsequent rounds will be created dynamically based on results
        return $matches;
    }

    // Helper methods

    private function getEventTeams($eventId)
    {
        return DB::table('event_teams as et')
            ->leftJoin('teams as t', 'et.team_id', '=', 't.id')
            ->where('et.event_id', $eventId)
            ->select(['t.id', 't.name', 't.short_name', 't.logo', 't.rating', 'et.seed'])
            ->orderBy('et.seed')
            ->get()
            ->toArray();
    }

    private function applySeedingMethod($teams, $method, $randomize = false)
    {
        switch ($method) {
            case 'rating':
                usort($teams, function($a, $b) {
                    return $b->rating <=> $a->rating;
                });
                break;
            case 'random':
                shuffle($teams);
                break;
            case 'manual':
                // Teams are already in seed order
                break;
        }
        
        if ($randomize) {
            shuffle($teams);
        }
        
        return $teams;
    }

    private function getEventTeamCount($eventId)
    {
        return DB::table('event_teams')->where('event_id', $eventId)->count();
    }

    private function getCompletedMatchesCount($eventId)
    {
        return DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->count();
    }

    private function getCurrentRound($eventId)
    {
        return DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'scheduled')
            ->min('round') ?? 1;
    }

    private function calculateTotalRounds($eventId, $format)
    {
        $teamCount = $this->getEventTeamCount($eventId);
        
        // Handle edge cases where there are no teams or only one team
        if ($teamCount <= 1) {
            return 0;
        }
        
        switch ($format) {
            case 'single_elimination':
                return $this->safeCalculateRounds($teamCount);
            case 'double_elimination':
                return $this->safeCalculateRounds($teamCount) * 2;
            case 'round_robin':
                return max(0, $teamCount - 1);
            case 'swiss':
                return $this->calculateSwissRounds($teamCount);
            default:
                return $this->safeCalculateRounds($teamCount);
        }
    }

    private function calculateSwissRounds($teamCount)
    {
        return $this->safeCalculateRounds($teamCount);
    }

    /**
     * Safely calculate the number of rounds needed for a tournament
     * Prevents Inf/NaN issues when team count is 0 or 1
     */
    private function safeCalculateRounds($teamCount)
    {
        // Handle edge cases where there are no teams or only one team
        if ($teamCount <= 1) {
            return 0;
        }
        
        return ceil(log($teamCount, 2));
    }

    private function getTeamSeed($eventId, $teamId)
    {
        return DB::table('event_teams')
            ->where('event_id', $eventId)
            ->where('team_id', $teamId)
            ->value('seed');
    }

    private function getMatchWinner($match)
    {
        if ($match->status !== 'completed') {
            return null;
        }
        
        if ($match->team1_score > $match->team2_score) {
            return $match->team1_id;
        } elseif ($match->team2_score > $match->team1_score) {
            return $match->team2_id;
        }
        
        return null; // Draw
    }

    private function getRoundName($round, $teamCount)
    {
        $totalRounds = $this->safeCalculateRounds($teamCount);
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
            default:
                return "Round $round";
        }
    }

    private function processMatchCompletion($matchId, $team1Score, $team2Score)
    {
        try {
            DB::beginTransaction();
            
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                throw new \Exception('Match not found');
            }
            
            // Determine winner and loser
            if ($team1Score > $team2Score) {
                $winnerId = $match->team1_id;
                $loserId = $match->team2_id;
            } elseif ($team2Score > $team1Score) {
                $winnerId = $match->team2_id;
                $loserId = $match->team1_id;
            } else {
                // Handle draw/tie scenarios
                $winnerId = null;
                $loserId = null;
            }
            
            // Clear any existing cache for this event
            cache()->forget("bracket_data_{$match->event_id}");
            cache()->forget("bracket_metadata_{$match->event_id}");
            cache()->forget("event_{$match->event_id}");
            
            // Advance winner to next round (implementation depends on bracket type)
            if ($winnerId) {
                $this->advanceWinnerToNextRound($match, $winnerId);
            }
            
            // For double elimination, move loser to lower bracket
            if ($match->bracket_type === 'upper' && $loserId) {
                $this->moveLoserToLowerBracket($match, $loserId);
            }
            
            // Update event standings
            $this->updateEventStandings($match->event_id);
            
            // Check if tournament is complete
            $this->checkTournamentCompletion($match->event_id);
            
            DB::commit();
            
            // Log the match completion for audit trail
            \Log::info('Match completed', [
                'match_id' => $matchId,
                'event_id' => $match->event_id,
                'winner_id' => $winnerId,
                'score' => "{$team1Score}-{$team2Score}"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Match completion error', [
                'match_id' => $matchId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function advanceWinnerToNextRound($match, $winnerId)
    {
        // Find next match in bracket
        $nextRound = $match->round + 1;
        $nextPosition = ceil($match->bracket_position / 2);
        
        $nextMatch = DB::table('matches')
            ->where('event_id', $match->event_id)
            ->where('round', $nextRound)
            ->where('bracket_position', $nextPosition)
            ->where('bracket_type', $match->bracket_type)
            ->first();
            
        if ($nextMatch) {
            // Determine if winner goes to team1 or team2 slot
            $teamSlot = ($match->bracket_position % 2 === 1) ? 'team1_id' : 'team2_id';
            
            DB::table('matches')
                ->where('id', $nextMatch->id)
                ->update([
                    $teamSlot => $winnerId,
                    'status' => $nextMatch->team1_id && $nextMatch->team2_id ? 'scheduled' : 'pending',
                    'updated_at' => now()
                ]);
        }
    }

    private function moveLoserToLowerBracket($match, $loserId)
    {
        if ($match->bracket_type !== 'upper') {
            return; // Only move losers from upper bracket
        }
        
        $upperRound = $match->round;
        $upperPosition = $match->bracket_position;
        
        // Calculate lower bracket round and position
        if ($upperRound == 1) {
            // First round losers go to lower bracket round 1
            $lowerRound = 1;
            $lowerPosition = ceil($upperPosition / 2);
        } else {
            // Other round losers go to even-numbered lower bracket rounds
            $lowerRound = ($upperRound - 1) * 2;
            $lowerPosition = $upperPosition;
        }
        
        // Find the corresponding lower bracket match
        $lowerMatch = DB::table('matches')
            ->where('event_id', $match->event_id)
            ->where('bracket_type', 'lower')
            ->where('round', $lowerRound)
            ->where('bracket_position', $lowerPosition)
            ->first();
            
        if ($lowerMatch) {
            // Assign loser to appropriate slot
            if (!$lowerMatch->team1_id) {
                DB::table('matches')
                    ->where('id', $lowerMatch->id)
                    ->update([
                        'team1_id' => $loserId,
                        'status' => ($lowerMatch->team2_id) ? 'scheduled' : 'pending',
                        'updated_at' => now()
                    ]);
            } else if (!$lowerMatch->team2_id) {
                DB::table('matches')
                    ->where('id', $lowerMatch->id)
                    ->update([
                        'team2_id' => $loserId,
                        'status' => 'scheduled',
                        'updated_at' => now()
                    ]);
            }
        }
    }

    private function createLowerBracketMatches($eventId, $teamCount)
    {
        $matches = [];
        $upperRounds = $this->safeCalculateRounds($teamCount);
        
        // Lower bracket has 2 * (upper rounds - 1) rounds
        $lowerRounds = ($upperRounds - 1) * 2;
        $position = 1;
        
        // First lower bracket round receives losers from upper bracket round 1
        $firstRoundMatches = ceil($teamCount / 4);
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
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        // Create remaining lower bracket rounds
        $currentMatches = $firstRoundMatches;
        for ($round = 2; $round <= $lowerRounds; $round++) {
            // Every even round receives losers from upper bracket
            if ($round % 2 == 0) {
                // These are "drop-down" matches
                $matchesInRound = $currentMatches;
            } else {
                // These are advancement matches
                $matchesInRound = ceil($currentMatches / 2);
            }
            
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $m,
                    'bracket_type' => 'lower',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            $currentMatches = $matchesInRound;
        }
        
        return $matches;
    }

    private function calculateRoundRobinStandings($eventId)
    {
        // Get all teams in the event
        $teams = DB::table('event_teams')
            ->join('teams', 'event_teams.team_id', '=', 'teams.id')
            ->where('event_teams.event_id', $eventId)
            ->select('teams.id', 'teams.name', 'teams.short_name', 'teams.logo')
            ->get();
            
        // Initialize standings
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
                'map_wins' => 0,
                'map_losses' => 0,
                'map_diff' => 0,
                'round_wins' => 0,
                'round_losses' => 0,
                'round_diff' => 0,
                'points' => 0
            ];
        }
        
        // Get all completed matches
        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->get();
            
        foreach ($matches as $match) {
            if (!isset($standings[$match->team1_id]) || !isset($standings[$match->team2_id])) {
                continue;
            }
            
            $standings[$match->team1_id]['matches_played']++;
            $standings[$match->team2_id]['matches_played']++;
            
            // Determine winner
            if ($match->team1_score > $match->team2_score) {
                $standings[$match->team1_id]['wins']++;
                $standings[$match->team1_id]['points'] += 3;
                $standings[$match->team2_id]['losses']++;
            } elseif ($match->team2_score > $match->team1_score) {
                $standings[$match->team2_id]['wins']++;
                $standings[$match->team2_id]['points'] += 3;
                $standings[$match->team1_id]['losses']++;
            }
            
            // Map scores
            $standings[$match->team1_id]['map_wins'] += $match->team1_score;
            $standings[$match->team1_id]['map_losses'] += $match->team2_score;
            $standings[$match->team2_id]['map_wins'] += $match->team2_score;
            $standings[$match->team2_id]['map_losses'] += $match->team1_score;
            
            // Calculate round statistics from maps_data
            if ($match->maps_data) {
                $mapsData = json_decode($match->maps_data, true);
                foreach ($mapsData as $map) {
                    if (isset($map['team1_score']) && isset($map['team2_score'])) {
                        $standings[$match->team1_id]['round_wins'] += $map['team1_score'];
                        $standings[$match->team1_id]['round_losses'] += $map['team2_score'];
                        $standings[$match->team2_id]['round_wins'] += $map['team2_score'];
                        $standings[$match->team2_id]['round_losses'] += $map['team1_score'];
                    }
                }
            }
        }
        
        // Calculate differentials
        foreach ($standings as &$standing) {
            $standing['map_diff'] = $standing['map_wins'] - $standing['map_losses'];
            $standing['round_diff'] = $standing['round_wins'] - $standing['round_losses'];
        }
        
        // Sort standings by: points, map diff, round diff
        usort($standings, function($a, $b) {
            if ($a['points'] != $b['points']) return $b['points'] - $a['points'];
            if ($a['map_diff'] != $b['map_diff']) return $b['map_diff'] - $a['map_diff'];
            return $b['round_diff'] - $a['round_diff'];
        });
        
        return array_values($standings);
    }

    private function calculateSwissStandings($eventId)
    {
        // Similar to round robin but with Buchholz tiebreaker
        $standings = $this->calculateRoundRobinStandings($eventId);
        
        // Calculate Buchholz scores (sum of opponents' scores)
        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->get();
            
        $buchholzScores = [];
        foreach ($standings as &$standing) {
            $buchholzScore = 0;
            $opponentCount = 0;
            
            foreach ($matches as $match) {
                $opponentId = null;
                if ($match->team1_id == $standing['team_id']) {
                    $opponentId = $match->team2_id;
                } elseif ($match->team2_id == $standing['team_id']) {
                    $opponentId = $match->team1_id;
                }
                
                if ($opponentId) {
                    // Find opponent's points
                    foreach ($standings as $oppStanding) {
                        if ($oppStanding['team_id'] == $opponentId) {
                            $buchholzScore += $oppStanding['points'];
                            $opponentCount++;
                            break;
                        }
                    }
                }
            }
            
            $standing['buchholz_score'] = $buchholzScore;
            $standing['opponents_faced'] = $opponentCount;
        }
        
        // Sort by: points, buchholz score, map diff, round diff
        usort($standings, function($a, $b) {
            if ($a['points'] != $b['points']) return $b['points'] - $a['points'];
            if ($a['buchholz_score'] != $b['buchholz_score']) return $b['buchholz_score'] - $a['buchholz_score'];
            if ($a['map_diff'] != $b['map_diff']) return $b['map_diff'] - $a['map_diff'];
            return $b['round_diff'] - $a['round_diff'];
        });
        
        return array_values($standings);
    }

    private function updateEventStandings($eventId)
    {
        $event = DB::table('events')->where('id', $eventId)->first();
        if (!$event) return;
        
        $standings = [];
        
        switch ($event->format) {
            case 'single_elimination':
            case 'double_elimination':
                // For elimination tournaments, standings based on how far teams progressed
                $standings = $this->calculateEliminationStandings($eventId, $event->format);
                break;
                
            case 'round_robin':
                $standings = $this->calculateRoundRobinStandings($eventId);
                break;
                
            case 'swiss':
                $standings = $this->calculateSwissStandings($eventId);
                break;
        }
        
        // Update event_standings table
        DB::table('event_standings')->where('event_id', $eventId)->delete();
        
        $position = 1;
        foreach ($standings as $standing) {
            DB::table('event_standings')->insert([
                'event_id' => $eventId,
                'team_id' => $standing['team_id'] ?? $standing,
                'position' => $position++,
                'points' => $standing['points'] ?? 0,
                'matches_played' => $standing['matches_played'] ?? 0,
                'matches_won' => $standing['wins'] ?? 0,
                'matches_lost' => $standing['losses'] ?? 0,
                'maps_won' => $standing['map_wins'] ?? 0,
                'maps_lost' => $standing['map_losses'] ?? 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    private function calculateEliminationStandings($eventId, $format)
    {
        $standings = [];
        $positions = [];
        
        // Get all matches ordered by round descending
        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->orderBy('round', 'desc')
            ->orderBy('bracket_type', 'desc')
            ->get();
            
        foreach ($matches as $match) {
            // Teams that lost get positioned based on the round they lost in
            if ($match->status == 'completed' && $match->team1_score != $match->team2_score) {
                $loserId = $match->team1_score > $match->team2_score ? $match->team2_id : $match->team1_id;
                $winnerId = $match->team1_score > $match->team2_score ? $match->team1_id : $match->team2_id;
                
                // Position loser if not already positioned
                if ($loserId && !isset($positions[$loserId])) {
                    $position = $this->getEliminationPosition($match->round, $match->bracket_type, false);
                    $positions[$loserId] = $position;
                }
                
                // Winner of grand final gets 1st place
                if ($match->bracket_type == 'grand_final' && !isset($positions[$winnerId])) {
                    $positions[$winnerId] = 1;
                }
            }
        }
        
        // Sort teams by position
        asort($positions);
        
        // Get team details
        foreach ($positions as $teamId => $position) {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if ($team) {
                $standings[] = [
                    'team_id' => $teamId,
                    'team_name' => $team->name,
                    'team_short_name' => $team->short_name,
                    'team_logo' => $team->logo,
                    'position' => $position,
                    'points' => 0,
                    'matches_played' => 0,
                    'wins' => 0,
                    'losses' => 0
                ];
            }
        }
        
        return $standings;
    }
    
    private function getEliminationPosition($round, $bracketType, $isWinner)
    {
        // Position calculation based on round and bracket type
        if ($bracketType == 'grand_final') {
            return $isWinner ? 1 : 2;
        }
        
        if ($bracketType == 'lower') {
            // Lower bracket positions
            $lowerPositions = [
                1 => 7, 2 => 5, 3 => 5, 4 => 4, 5 => 4, 6 => 3
            ];
            return $lowerPositions[$round] ?? 8;
        }
        
        // Upper bracket positions
        $upperPositions = [
            1 => 8, 2 => 4, 3 => 3, 4 => 2
        ];
        return $upperPositions[$round] ?? 9;
    }

    /**
     * Check if tournament is complete and update status
     */
    private function checkTournamentCompletion($eventId)
    {
        $event = DB::table('events')->where('id', $eventId)->first();
        if (!$event || $event->status === 'completed') {
            return;
        }

        // Check if all matches are completed
        $pendingMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->whereIn('status', ['scheduled', 'live'])
            ->count();

        if ($pendingMatches === 0) {
            // Tournament is complete
            DB::table('events')
                ->where('id', $eventId)
                ->update([
                    'status' => 'completed',
                    'ended_at' => now(),
                    'updated_at' => now()
                ]);

            \Log::info('Tournament completed', ['event_id' => $eventId]);

            // Clear caches
            cache()->forget("event_{$eventId}");
            cache()->forget("bracket_data_{$eventId}");
            cache()->forget("bracket_metadata_{$eventId}");
        }
    }

    /**
     * Validate bracket integrity and fix common issues
     */
    public function validateBracket($eventId)
    {
        try {
            $issues = [];
            
            // Check for orphaned matches
            $orphanedMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.event_id', $eventId)
                ->whereNull('t1.id')
                ->orWhereNull('t2.id')
                ->count();

            if ($orphanedMatches > 0) {
                $issues[] = "Found {$orphanedMatches} matches with invalid team references";
            }

            // Check for bracket inconsistencies
            $event = DB::table('events')->where('id', $eventId)->first();
            if ($event) {
                $totalMatches = DB::table('matches')->where('event_id', $eventId)->count();
                $teamCount = $this->getEventTeamCount($eventId);
                $expectedMatches = $this->calculateExpectedMatches($teamCount, $event->format);

                if ($totalMatches !== $expectedMatches) {
                    $issues[] = "Expected {$expectedMatches} matches but found {$totalMatches}";
                }
            }

            return response()->json([
                'success' => true,
                'issues' => $issues,
                'is_valid' => count($issues) === 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error validating bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate expected number of matches for a tournament format
     */
    private function calculateExpectedMatches($teamCount, $format)
    {
        switch ($format) {
            case 'single_elimination':
                return max(0, $teamCount - 1);
            case 'double_elimination':
                return max(0, ($teamCount - 1) * 2);
            case 'round_robin':
                return max(0, ($teamCount * ($teamCount - 1)) / 2);
            case 'swiss':
                $rounds = $this->calculateSwissRounds($teamCount);
                return ($teamCount / 2) * $rounds;
            default:
                return 0;
        }
    }
}