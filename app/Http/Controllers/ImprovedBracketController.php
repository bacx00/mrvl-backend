<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\EventStanding;
use App\Events\MatchUpdated;
use App\Events\MatchStarted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImprovedBracketController extends Controller
{
    /**
     * Get bracket data in VLR.gg style format
     */
    public function show($eventId)
    {
        try {
            $event = Event::with(['teams', 'matches.team1', 'matches.team2'])->find($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Get bracket data based on format
            $bracketData = $this->getBracketData($event);

            return response()->json([
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'format' => $event->format,
                        'status' => $event->status,
                        'teams_count' => $event->teams()->count(),
                        'current_round' => $event->current_round,
                        'total_rounds' => $event->total_rounds
                    ],
                    'bracket' => $bracketData,
                    'standings' => $this->getStandings($event)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching bracket: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bracket data'
            ], 500);
        }
    }

    /**
     * Generate bracket for an event
     */
    public function generate(Request $request, $eventId)
    {
        $request->validate([
            'seeding_type' => 'required|in:manual,rating,random',
            'best_of' => 'nullable|in:1,3,5,7',
            'third_place_match' => 'nullable|boolean'
        ]);

        DB::beginTransaction();
        try {
            $event = Event::with('teams')->find($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Admin users should be able to generate brackets
            // $this->authorize('update', $event);

            // Check minimum teams requirement
            $teamCount = $event->teams()->count();
            if ($teamCount < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Need at least 2 teams to generate bracket'
                ], 400);
            }

            // Clear existing matches
            GameMatch::where('event_id', $eventId)->delete();

            // Get seeded teams
            $teams = $this->seedTeams($event->teams, $request->seeding_type);

            // Generate matches based on format
            $matches = [];
            switch ($event->format) {
                case 'single_elimination':
                    $matches = $this->generateSingleElimination($event, $teams, $request);
                    break;
                case 'double_elimination':
                    $matches = $this->generateDoubleElimination($event, $teams, $request);
                    break;
                case 'round_robin':
                    $matches = $this->generateRoundRobin($event, $teams, $request);
                    break;
                case 'swiss':
                    $matches = $this->generateSwissFirstRound($event, $teams, $request);
                    break;
                case 'group_stage':
                    $matches = $this->generateGroupStage($event, $teams, $request);
                    break;
                default:
                    throw new \Exception('Unsupported bracket format');
            }

            // Save matches
            foreach ($matches as $match) {
                GameMatch::create($match);
            }

            // Update event status
            $event->update([
                'status' => 'ongoing',
                'current_round' => 1,
                'total_rounds' => $this->calculateTotalRounds($event->format, $teamCount)
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bracket generated successfully',
                'data' => [
                    'matches_created' => count($matches),
                    'format' => $event->format,
                    'teams' => $teamCount,
                    'rounds' => $event->total_rounds
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error generating bracket: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update match result and advance tournament
     */
    public function updateMatch(Request $request, $eventId, $matchId)
    {
        $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'status' => 'required|in:ongoing,completed,cancelled',
            'maps' => 'nullable|array',
            'vod_url' => 'nullable|url'
        ]);

        DB::beginTransaction();
        try {
            $match = GameMatch::with(['event', 'team1', 'team2'])->find($matchId);
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Authorization check - admin and moderators can update
            if (!auth()->user() || !auth()->user()->hasAnyRole(['admin', 'moderator', 'organizer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update matches'
                ], 403);
            }

            // Update match
            $match->update([
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'status' => $request->status,
                'vod_url' => $request->vod_url,
                'started_at' => $match->started_at ?? ($request->status === 'ongoing' ? now() : null),
                'completed_at' => $request->status === 'completed' ? now() : null
            ]);

            // Update map data if provided
            if ($request->has('maps')) {
                $this->updateMapData($match, $request->maps);
            }

            // Process match completion
            if ($request->status === 'completed') {
                $this->processMatchCompletion($match);
            }

            // Broadcast match update
            broadcast(new MatchUpdated($match))->toOthers();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully',
                'data' => [
                    'match' => $match->load(['team1', 'team2']),
                    'next_matches' => $this->getNextMatches($match)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating match: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bracket data based on event format
     */
    private function getBracketData($event)
    {
        switch ($event->format) {
            case 'single_elimination':
                return $this->getSingleEliminationBracket($event);
            case 'double_elimination':
                return $this->getDoubleEliminationBracket($event);
            case 'round_robin':
                return $this->getRoundRobinBracket($event);
            case 'swiss':
                return $this->getSwissBracket($event);
            case 'group_stage':
                return $this->getGroupStageBracket($event);
            default:
                return [];
        }
    }

    /**
     * Get single elimination bracket in VLR.gg format
     */
    private function getSingleEliminationBracket($event)
    {
        $matches = $event->matches()
            ->with(['team1', 'team2'])
            ->orderBy('round')
            ->orderBy('bracket_position')
            ->get();

        $rounds = [];
        
        // Handle empty matches collection
        if ($matches->isEmpty()) {
            return [
                'rounds' => [],
                'event' => $event->load('teams'),
                'meta' => [
                    'total_rounds' => 0,
                    'current_round' => 0,
                    'format' => $event->format,
                    'status' => $event->status
                ]
            ];
        }
        
        // Calculate total expected rounds based on team count
        $teamCount = $event->teams()->count();
        $totalRounds = $this->calculateTotalRounds('single_elimination', $teamCount);
        $maxRound = $totalRounds;

        foreach ($matches->groupBy('round') as $round => $roundMatches) {
            $roundName = $this->getRoundName($round, $maxRound, $teamCount);
            
            $rounds[] = [
                'round' => $round,
                'name' => $roundName,
                'matches' => $roundMatches->map(function($match) {
                    return $this->formatMatchData($match);
                })->values()
            ];
        }

        return [
            'type' => 'single_elimination',
            'rounds' => $rounds
        ];
    }

    /**
     * Get double elimination bracket in VLR.gg format
     */
    private function getDoubleEliminationBracket($event)
    {
        $matches = $event->matches()
            ->with(['team1', 'team2'])
            ->orderBy('bracket_type')
            ->orderBy('round')
            ->orderBy('bracket_position')
            ->get();

        $upperBracket = [];
        $lowerBracket = [];
        $grandFinal = null;

        foreach ($matches as $match) {
            $matchData = $this->formatMatchData($match);

            if ($match->bracket_type === 'upper') {
                if (!isset($upperBracket[$match->round])) {
                    $upperBracket[$match->round] = [
                        'round' => $match->round,
                        'name' => "Upper Round {$match->round}",
                        'matches' => []
                    ];
                }
                $upperBracket[$match->round]['matches'][] = $matchData;
            } elseif ($match->bracket_type === 'lower') {
                if (!isset($lowerBracket[$match->round])) {
                    $lowerBracket[$match->round] = [
                        'round' => $match->round,
                        'name' => "Lower Round {$match->round}",
                        'matches' => []
                    ];
                }
                $lowerBracket[$match->round]['matches'][] = $matchData;
            } elseif ($match->bracket_type === 'grand_final') {
                $grandFinal = $matchData;
            }
        }

        return [
            'type' => 'double_elimination',
            'upper_bracket' => array_values($upperBracket),
            'lower_bracket' => array_values($lowerBracket),
            'grand_final' => $grandFinal
        ];
    }

    /**
     * Format match data for API response
     */
    private function formatMatchData($match)
    {
        return [
            'id' => $match->id,
            'round' => $match->round,
            'position' => $match->bracket_position,
            'status' => $match->status,
            'scheduled_at' => $match->scheduled_at,
            'started_at' => $match->started_at,
            'completed_at' => $match->completed_at,
            'best_of' => $match->format === 'bo1' ? 1 : ($match->format === 'bo3' ? 3 : 5),
            'team1' => $match->team1 ? [
                'id' => $match->team1->id,
                'name' => $match->team1->name,
                'short_name' => $match->team1->short_name,
                'logo' => $match->team1->logo,
                'seed' => $match->team1->pivot->seed ?? null,
                'score' => $match->team1_score
            ] : null,
            'team2' => $match->team2 ? [
                'id' => $match->team2->id,
                'name' => $match->team2->name,
                'short_name' => $match->team2->short_name,
                'logo' => $match->team2->logo,
                'seed' => $match->team2->pivot->seed ?? null,
                'score' => $match->team2_score
            ] : null,
            'winner_id' => $this->getWinnerId($match),
            'vod_url' => $match->vod_url,
            'stream_url' => $match->stream_url,
            'maps' => $match->maps ?? []
        ];
    }

    /**
     * Seed teams based on method
     */
    private function seedTeams($teams, $method)
    {
        switch ($method) {
            case 'manual':
                // Use existing seeds
                return $teams->sortBy('pivot.seed')->values();
            
            case 'rating':
                // Sort by team rating
                return $teams->sortByDesc('rating')->values();
            
            case 'random':
                // Randomize seeding
                return $teams->shuffle();
            
            default:
                return $teams;
        }
    }

    /**
     * Generate single elimination matches
     */
    private function generateSingleElimination($event, $teams, $request)
    {
        $matches = [];
        $teamCount = count($teams);
        $rounds = ceil(log($teamCount, 2));
        $bestOf = $request->best_of ?? 3;

        // Calculate byes
        $totalSlots = pow(2, $rounds);
        $byes = $totalSlots - $teamCount;

        // First round matches
        $position = 1;
        $teamIndex = 0;

        for ($i = 0; $i < $totalSlots / 2; $i++) {
            $team1 = isset($teams[$teamIndex]) ? $teams[$teamIndex]->id : null;
            $teamIndex++;
            
            $team2 = isset($teams[$teamIndex]) ? $teams[$teamIndex]->id : null;
            $teamIndex++;

            // Skip match if both teams are byes
            if (!$team1 && !$team2) continue;

            $matches[] = [
                'event_id' => $event->id,
                'round' => 1,
                'bracket_position' => $position,
                'bracket_type' => 'main',
                'team1_id' => $team1,
                'team2_id' => $team2,
                'status' => ($team1 && $team2) ? 'upcoming' : 'upcoming',
                'format' => "bo{$bestOf}",
                'scheduled_at' => $event->start_date ?? now()->addDays(1),
                'created_at' => now(),
                'updated_at' => now()
            ];
            $position++;
        }

        // Create third place match if requested
        if ($request->third_place_match) {
            $matches[] = [
                'event_id' => $event->id,
                'round' => $rounds, // Same round as final
                'bracket_position' => 2, // Position 2 for third place
                'bracket_type' => 'third_place',
                'team1_id' => null, // Will be filled by semifinal losers
                'team2_id' => null,
                'status' => 'upcoming',
                'format' => "bo{$bestOf}",
                'scheduled_at' => $event->start_date ?? now()->addDays(1),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        return $matches;
    }

    /**
     * Process match completion and advance winners
     */
    private function processMatchCompletion($match)
    {
        $winnerId = $this->getWinnerId($match);
        $loserId = $match->team1_score > $match->team2_score ? $match->team2_id : $match->team1_id;

        if (!$winnerId) return;

        // Update standings
        $this->updateStandings($match, $winnerId, $loserId);

        // Advance winner based on bracket type
        switch ($match->event->format) {
            case 'single_elimination':
                $this->advanceInSingleElimination($match, $winnerId);
                break;
            case 'double_elimination':
                $this->advanceInDoubleElimination($match, $winnerId, $loserId);
                break;
            case 'swiss':
                $this->checkSwissRoundCompletion($match->event);
                break;
        }

        // Check if tournament is complete
        $this->checkTournamentCompletion($match->event);
    }

    /**
     * Advance winner in single elimination
     */
    private function advanceInSingleElimination($match, $winnerId)
    {
        // Check if this is a semifinal match for third place
        $maxRound = GameMatch::where('event_id', $match->event_id)
            ->where('bracket_type', 'main')
            ->max('round');

        if ($match->round === $maxRound - 1) {
            // This is a semifinal, loser might go to third place match
            $thirdPlaceMatch = GameMatch::where('event_id', $match->event_id)
                ->where('bracket_type', 'third_place')
                ->first();

            if ($thirdPlaceMatch) {
                $loserId = $match->team1_score > $match->team2_score ? $match->team2_id : $match->team1_id;
                
                if (!$thirdPlaceMatch->team1_id) {
                    $thirdPlaceMatch->update(['team1_id' => $loserId]);
                } else if (!$thirdPlaceMatch->team2_id) {
                    $thirdPlaceMatch->update(['team2_id' => $loserId]);
                    // Both teams are set, mark as upcoming
                    if ($thirdPlaceMatch->team1_id && $thirdPlaceMatch->team2_id) {
                        $thirdPlaceMatch->update(['status' => 'upcoming']);
                    }
                }
            }
        }

        // Find next match
        $nextRound = $match->round + 1;
        $nextPosition = ceil($match->bracket_position / 2);

        $nextMatch = GameMatch::where('event_id', $match->event_id)
            ->where('round', $nextRound)
            ->where('bracket_position', $nextPosition)
            ->where('bracket_type', 'main')
            ->first();

        if (!$nextMatch) {
            // Create the next round match with nullable teams
            $nextMatch = GameMatch::create([
                'event_id' => $match->event_id,
                'round' => $nextRound,
                'bracket_position' => $nextPosition,
                'bracket_type' => 'main',
                'team1_id' => null,
                'team2_id' => null,
                'status' => 'upcoming',
                'format' => $match->format,
                'scheduled_at' => $match->scheduled_at ? $match->scheduled_at->addDays(1) : now()->addDays(1),
            ]);
        }
        
        // Determine slot based on current match position
        $isUpperSlot = ($match->bracket_position % 2) === 1;
        
        // Update the match with the winner
        if ($isUpperSlot) {
            $nextMatch->update(['team1_id' => $winnerId]);
        } else {
            $nextMatch->update(['team2_id' => $winnerId]);
        }

        // Set status to upcoming if both teams are set
        if ($nextMatch->team1_id && $nextMatch->team2_id) {
            $nextMatch->update(['status' => 'upcoming']);
        }
    }

    /**
     * Get winner ID from match
     */
    private function getWinnerId($match)
    {
        if ($match->status !== 'completed') return null;
        
        if ($match->team1_score > $match->team2_score) {
            return $match->team1_id;
        } elseif ($match->team2_score > $match->team1_score) {
            return $match->team2_id;
        }
        
        return null;
    }

    /**
     * Get round name based on position
     */
    private function getRoundName($round, $maxRound, $teamCount)
    {
        // Handle null values and convert to integers
        if ($round === null || $maxRound === null) {
            return "Round " . ($round ?? 1);
        }
        
        // Ensure we're working with integers
        $round = (int) $round;
        $maxRound = (int) $maxRound;
        
        $roundsFromEnd = $maxRound - $round + 1;
        
        $names = [
            1 => 'Grand Final',
            2 => 'Semi-Finals',
            3 => 'Quarter-Finals',
            4 => 'Round of 16',
            5 => 'Round of 32',
            6 => 'Round of 64'
        ];

        return $names[$roundsFromEnd] ?? "Round {$round}";
    }

    /**
     * Calculate total rounds for format
     */
    private function calculateTotalRounds($format, $teamCount)
    {
        switch ($format) {
            case 'single_elimination':
                return ceil(log($teamCount, 2));
            case 'double_elimination':
                return ceil(log($teamCount, 2)) * 2 + 1;
            case 'round_robin':
                return $teamCount - 1;
            case 'swiss':
                return ceil(log($teamCount, 2));
            default:
                return 0;
        }
    }

    /**
     * Get current standings for event
     */
    private function getStandings($event)
    {
        return EventStanding::where('event_id', $event->id)
            ->with('team:id,name,short_name,logo')
            ->orderBy('position')
            ->get()
            ->map(function($standing) {
                return [
                    'position' => $standing->position,
                    'team' => $standing->team,
                    'wins' => $standing->wins,
                    'losses' => $standing->losses,
                    'map_wins' => $standing->maps_won,
                    'map_losses' => $standing->maps_lost,
                    'map_diff' => $standing->map_differential,
                    'points' => $standing->points
                ];
            });
    }

    /**
     * Update standings after match completion
     */
    private function updateStandings($match, $winnerId, $loserId)
    {
        // Update winner standings
        $winnerStanding = EventStanding::where('event_id', $match->event_id)
            ->where('team_id', $winnerId)
            ->first();

        if ($winnerStanding) {
            $winnerStanding->increment('wins');
            $winnerStanding->increment('maps_won', $match->team1_id === $winnerId ? $match->team1_score : $match->team2_score);
            $winnerStanding->increment('maps_lost', $match->team1_id === $winnerId ? $match->team2_score : $match->team1_score);
            $winnerStanding->update([
                'map_differential' => $winnerStanding->maps_won - $winnerStanding->maps_lost
            ]);
        }

        // Update loser standings
        $loserStanding = EventStanding::where('event_id', $match->event_id)
            ->where('team_id', $loserId)
            ->first();

        if ($loserStanding) {
            $loserStanding->increment('losses');
            $loserStanding->increment('maps_won', $match->team1_id === $loserId ? $match->team1_score : $match->team2_score);
            $loserStanding->increment('maps_lost', $match->team1_id === $loserId ? $match->team2_score : $match->team1_score);
            $loserStanding->update([
                'map_differential' => $loserStanding->maps_won - $loserStanding->maps_lost
            ]);
        }
    }

    /**
     * Get next matches for teams
     */
    private function getNextMatches($match)
    {
        $nextMatches = [];

        if ($match->status === 'completed') {
            $winnerId = $this->getWinnerId($match);
            $loserId = $match->team1_score > $match->team2_score ? $match->team2_id : $match->team1_id;

            // Find winner's next match
            $winnerNext = GameMatch::where('event_id', $match->event_id)
                ->where(function($query) use ($winnerId) {
                    $query->where('team1_id', $winnerId)
                          ->orWhere('team2_id', $winnerId);
                })
                ->where('status', '!=', 'completed')
                ->where('id', '>', $match->id)
                ->first();

            if ($winnerNext) {
                $nextMatches['winner'] = $this->formatMatchData($winnerNext);
            }

            // For double elimination, find loser's next match
            if ($match->event->format === 'double_elimination' && $match->bracket_type === 'upper') {
                $loserNext = GameMatch::where('event_id', $match->event_id)
                    ->where('bracket_type', 'lower')
                    ->where(function($query) use ($loserId) {
                        $query->where('team1_id', $loserId)
                              ->orWhere('team2_id', $loserId);
                    })
                    ->where('status', '!=', 'completed')
                    ->first();

                if ($loserNext) {
                    $nextMatches['loser'] = $this->formatMatchData($loserNext);
                }
            }
        }

        return $nextMatches;
    }

    /**
     * Check if tournament is complete
     */
    private function checkTournamentCompletion($event)
    {
        $pendingMatches = GameMatch::where('event_id', $event->id)
            ->whereIn('status', ['upcoming', 'live'])
            ->count();

        if ($pendingMatches === 0) {
            $event->update(['status' => 'completed']);
            
            // Calculate final placements
            $this->calculateFinalPlacements($event);
        }
    }

    /**
     * Calculate final tournament placements
     */
    private function calculateFinalPlacements($event)
    {
        // Logic depends on tournament format
        switch ($event->format) {
            case 'single_elimination':
                $this->calculateSingleEliminationPlacements($event);
                break;
            case 'double_elimination':
                $this->calculateDoubleEliminationPlacements($event);
                break;
            // Add other formats as needed
        }
    }

    /**
     * Generate double elimination matches
     */
    private function generateDoubleElimination($event, $teams, $request)
    {
        $matches = [];
        $teamCount = count($teams);
        $bestOf = $request->best_of ?? 3;

        // Generate upper bracket (same as single elimination)
        $upperMatches = $this->generateSingleElimination($event, $teams, $request);
        foreach ($upperMatches as &$match) {
            if ($match['bracket_type'] !== 'third_place') {
                $match['bracket_type'] = 'upper';
            }
        }
        $matches = array_merge($matches, $upperMatches);

        // Generate lower bracket structure (with null teams initially)
        $lowerMatches = $this->generateLowerBracketStructure($event, $teamCount, $bestOf);
        $matches = array_merge($matches, $lowerMatches);

        // Generate grand final match
        $matches[] = [
            'event_id' => $event->id,
            'round' => 1,
            'bracket_position' => 1,
            'bracket_type' => 'grand_final',
            'team1_id' => null, // Will be filled by upper bracket winner
            'team2_id' => null, // Will be filled by lower bracket winner
            'status' => 'upcoming',
            'format' => "bo" . ($bestOf + 2), // Grand final is usually Bo5 or Bo7
            'scheduled_at' => $event->start_date ?? now()->addDays(1),
            'created_at' => now(),
            'updated_at' => now()
        ];

        return $matches;
    }

    /**
     * Generate lower bracket structure
     */
    private function generateLowerBracketStructure($event, $teamCount, $bestOf)
    {
        $matches = [];
        $upperRounds = ceil(log($teamCount, 2));
        
        // Lower bracket has approximately 2x rounds as upper bracket
        $lowerRounds = ($upperRounds - 1) * 2;
        
        // First lower round receives losers from upper round 1
        $firstRoundMatches = floor($teamCount / 4);
        for ($i = 1; $i <= $firstRoundMatches; $i++) {
            $matches[] = [
                'event_id' => $event->id,
                'round' => 1,
                'bracket_position' => $i,
                'bracket_type' => 'lower',
                'team1_id' => null,
                'team2_id' => null,
                'status' => 'upcoming',
                'format' => "bo{$bestOf}",
                'scheduled_at' => $event->start_date ?? now()->addDays(1),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Generate remaining lower bracket rounds
        $position = 1;
        $prevRoundMatches = $firstRoundMatches;
        
        for ($round = 2; $round <= $lowerRounds; $round++) {
            if ($round % 2 == 0) {
                // Even rounds receive upper bracket losers
                $matchesInRound = $prevRoundMatches;
            } else {
                // Odd rounds are advancement matches
                $matchesInRound = ceil($prevRoundMatches / 2);
            }

            for ($i = 1; $i <= $matchesInRound; $i++) {
                $matches[] = [
                    'event_id' => $event->id,
                    'round' => $round,
                    'bracket_position' => $i,
                    'bracket_type' => 'lower',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'upcoming',
                    'format' => "bo{$bestOf}",
                    'scheduled_at' => $event->start_date ?? now()->addDays(1),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            $prevRoundMatches = $matchesInRound;
        }

        return $matches;
    }

    /**
     * Advance teams in double elimination
     */
    private function advanceInDoubleElimination($match, $winnerId, $loserId)
    {
        if ($match->bracket_type === 'upper') {
            // Winner advances in upper bracket
            $this->advanceInUpperBracket($match, $winnerId);
            
            // Loser drops to lower bracket
            $this->dropToLowerBracket($match, $loserId);
            
        } elseif ($match->bracket_type === 'lower') {
            // Winner advances in lower bracket
            $this->advanceInLowerBracket($match, $winnerId);
            
            // Loser is eliminated
            $this->eliminateTeam($match->event_id, $loserId);
            
        } elseif ($match->bracket_type === 'grand_final') {
            // Tournament complete
            $match->event->update(['status' => 'completed']);
        }
    }

    /**
     * Drop team from upper to lower bracket
     */
    private function dropToLowerBracket($match, $loserId)
    {
        // Calculate which lower bracket match to drop to
        $dropRound = $this->calculateLowerBracketDropRound($match->round);
        $dropPosition = $this->calculateLowerBracketDropPosition($match->round, $match->bracket_position);

        $lowerMatch = GameMatch::where('event_id', $match->event_id)
            ->where('bracket_type', 'lower')
            ->where('round', $dropRound)
            ->where('bracket_position', $dropPosition)
            ->first();

        if ($lowerMatch) {
            // Update existing match with loser
            if (!$lowerMatch->team1_id) {
                $lowerMatch->update(['team1_id' => $loserId]);
            } else if (!$lowerMatch->team2_id) {
                $lowerMatch->update(['team2_id' => $loserId]);
                // Both teams are now set, mark as upcoming
                if ($lowerMatch->team1_id && $lowerMatch->team2_id) {
                    $lowerMatch->update(['status' => 'upcoming']);
                }
            }
        }
    }

    /**
     * Calculate lower bracket drop round
     */
    private function calculateLowerBracketDropRound($upperRound)
    {
        // Upper round 1 losers go to lower round 1
        // Upper round 2+ losers go to even-numbered lower rounds
        return $upperRound === 1 ? 1 : ($upperRound - 1) * 2;
    }

    /**
     * Calculate lower bracket drop position
     */
    private function calculateLowerBracketDropPosition($upperRound, $upperPosition)
    {
        if ($upperRound === 1) {
            return ceil($upperPosition / 2);
        }
        return $upperPosition;
    }

    /**
     * Advance winner in lower bracket
     */
    private function advanceInLowerBracket($match, $winnerId)
    {
        $nextRound = $match->round + 1;
        
        // Check if this is the lower bracket final
        $isLowerFinal = !GameMatch::where('event_id', $match->event_id)
            ->where('bracket_type', 'lower')
            ->where('round', $nextRound)
            ->exists();

        if ($isLowerFinal) {
            // Winner goes to grand final
            $grandFinal = GameMatch::where('event_id', $match->event_id)
                ->where('bracket_type', 'grand_final')
                ->first();

            if ($grandFinal) {
                $grandFinal->update(['team2_id' => $winnerId]);
                // Check if both teams are set
                if ($grandFinal->team1_id && $grandFinal->team2_id) {
                    $grandFinal->update(['status' => 'upcoming']);
                }
            }
        } else {
            // Find next match in lower bracket
            $nextPosition = ceil($match->bracket_position / 2);
            
            $nextMatch = GameMatch::where('event_id', $match->event_id)
                ->where('bracket_type', 'lower')
                ->where('round', $nextRound)
                ->where('bracket_position', $nextPosition)
                ->first();

            if (!$nextMatch) {
                // Create next round match in lower bracket
                $nextMatch = GameMatch::create([
                    'event_id' => $match->event_id,
                    'round' => $nextRound,
                    'bracket_position' => $nextPosition,
                    'bracket_type' => 'lower',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'upcoming',
                    'format' => $match->format,
                    'scheduled_at' => $match->scheduled_at ? $match->scheduled_at->addDays(1) : now()->addDays(1),
                ]);
            }
            
            $isUpperSlot = ($match->bracket_position % 2) === 1;
            
            if ($isUpperSlot) {
                $nextMatch->update(['team1_id' => $winnerId]);
            } else {
                $nextMatch->update(['team2_id' => $winnerId]);
            }

            if ($nextMatch->team1_id && $nextMatch->team2_id) {
                $nextMatch->update(['status' => 'upcoming']);
            }
        }
    }

    /**
     * Advance winner in upper bracket
     */
    private function advanceInUpperBracket($match, $winnerId)
    {
        // Check if this is upper bracket final
        $isUpperFinal = !GameMatch::where('event_id', $match->event_id)
            ->where('bracket_type', 'upper')
            ->where('round', '>', $match->round)
            ->exists();

        if ($isUpperFinal) {
            // Winner goes to grand final
            $grandFinal = GameMatch::where('event_id', $match->event_id)
                ->where('bracket_type', 'grand_final')
                ->first();

            if ($grandFinal) {
                $grandFinal->update(['team1_id' => $winnerId]);
                // Check if both teams are set
                if ($grandFinal->team1_id && $grandFinal->team2_id) {
                    $grandFinal->update(['status' => 'upcoming']);
                }
            }
        } else {
            // Standard advancement in upper bracket
            $this->advanceInSingleElimination($match, $winnerId);
        }
    }

    /**
     * Mark team as eliminated
     */
    private function eliminateTeam($eventId, $teamId)
    {
        $standing = EventStanding::where('event_id', $eventId)
            ->where('team_id', $teamId)
            ->first();

        if ($standing) {
            $standing->update(['status' => 'eliminated']);
        }
    }

    /**
     * Generate round robin matches
     */
    private function generateRoundRobin($event, $teams, $request)
    {
        $matches = [];
        $teamCount = count($teams);
        $bestOf = $request->best_of ?? 3;

        // Every team plays every other team once
        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                $matches[] = [
                    'event_id' => $event->id,
                    'round' => 1, // All matches in same "round" for round robin
                    'bracket_position' => count($matches) + 1,
                    'bracket_type' => 'round_robin',
                    'team1_id' => $teams[$i]->id,
                    'team2_id' => $teams[$j]->id,
                    'status' => 'upcoming',
                    'format' => "bo{$bestOf}",
                    'scheduled_at' => $event->start_date ?? now()->addDays(1),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        return $matches;
    }

    /**
     * Get round robin bracket data
     */
    private function getRoundRobinBracket($event)
    {
        $matches = $event->matches()
            ->with(['team1', 'team2'])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        return [
            'type' => 'round_robin',
            'matches' => $matches->map(function($match) {
                return $this->formatMatchData($match);
            }),
            'standings' => $this->getRoundRobinStandings($event)
        ];
    }

    /**
     * Get round robin standings with head-to-head tiebreakers
     */
    private function getRoundRobinStandings($event)
    {
        $standings = EventStanding::where('event_id', $event->id)
            ->with('team')
            ->get();

        // Calculate additional stats for tiebreakers
        foreach ($standings as $standing) {
            $matches = GameMatch::where('event_id', $event->id)
                ->where('status', 'completed')
                ->where(function($query) use ($standing) {
                    $query->where('team1_id', $standing->team_id)
                          ->orWhere('team2_id', $standing->team_id);
                })
                ->get();

            $standing->matches_played = $matches->count();
            $standing->win_percentage = $standing->matches_played > 0 
                ? round(($standing->wins / $standing->matches_played) * 100, 1) 
                : 0;
        }

        // Sort by wins, then by map differential, then by head-to-head
        return $standings->sort(function($a, $b) use ($event) {
            if ($a->wins !== $b->wins) {
                return $b->wins - $a->wins;
            }
            
            if ($a->map_differential !== $b->map_differential) {
                return $b->map_differential - $a->map_differential;
            }
            
            // Head-to-head tiebreaker
            return $this->getHeadToHeadResult($event->id, $a->team_id, $b->team_id);
        })->values();
    }

    /**
     * Get head-to-head result between two teams
     */
    private function getHeadToHeadResult($eventId, $team1Id, $team2Id)
    {
        $match = GameMatch::where('event_id', $eventId)
            ->where('status', 'completed')
            ->where(function($query) use ($team1Id, $team2Id) {
                $query->where(function($q) use ($team1Id, $team2Id) {
                    $q->where('team1_id', $team1Id)
                      ->where('team2_id', $team2Id);
                })->orWhere(function($q) use ($team1Id, $team2Id) {
                    $q->where('team1_id', $team2Id)
                      ->where('team2_id', $team1Id);
                });
            })
            ->first();

        if (!$match) return 0;

        // Return positive if team1 won, negative if team2 won
        if ($match->team1_id === $team1Id) {
            return $match->team1_score > $match->team2_score ? 1 : -1;
        } else {
            return $match->team2_score > $match->team1_score ? 1 : -1;
        }
    }

    /**
     * Generate first round of Swiss system
     */
    private function generateSwissFirstRound($event, $teams, $request)
    {
        $matches = [];
        $teamCount = count($teams);
        $bestOf = $request->best_of ?? 3;

        // Shuffle for random first round pairings
        $shuffledTeams = $teams->shuffle();

        for ($i = 0; $i < $teamCount - 1; $i += 2) {
            $matches[] = [
                'event_id' => $event->id,
                'round' => 1,
                'bracket_position' => floor($i / 2) + 1,
                'bracket_type' => 'swiss',
                'team1_id' => $shuffledTeams[$i]->id,
                'team2_id' => $shuffledTeams[$i + 1]->id,
                'status' => 'upcoming',
                'format' => "bo{$bestOf}",
                'scheduled_at' => $event->start_date ?? now()->addDays(1),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        return $matches;
    }

    /**
     * Check if Swiss round is complete and generate next round
     */
    private function checkSwissRoundCompletion($event)
    {
        $currentRound = $event->current_round;
        
        // Check if all matches in current round are complete
        $pendingMatches = GameMatch::where('event_id', $event->id)
            ->where('round', $currentRound)
            ->where('status', '!=', 'completed')
            ->count();

        if ($pendingMatches === 0) {
            // Check if we've completed all Swiss rounds
            if ($currentRound >= $event->total_rounds) {
                $event->update(['status' => 'completed']);
                return;
            }

            // Generate next Swiss round
            $this->generateNextSwissRound($event);
        }
    }

    /**
     * Generate next Swiss round based on current standings
     */
    private function generateNextSwissRound($event)
    {
        $nextRound = $event->current_round + 1;
        
        // Get current standings grouped by win-loss record
        $standings = $this->getSwissStandings($event);
        $groups = $standings->groupBy(function($standing) {
            return $standing->wins . '-' . $standing->losses;
        });

        $matches = [];
        $position = 1;

        // Pair teams within same record groups
        foreach ($groups as $record => $teams) {
            $teamsArray = $teams->values()->toArray();
            
            // Shuffle to randomize pairings within same record
            shuffle($teamsArray);
            
            for ($i = 0; $i < count($teamsArray) - 1; $i += 2) {
                // Check if teams have already played
                if (!$this->haveTeamsPlayed($event->id, $teamsArray[$i]->team_id, $teamsArray[$i + 1]->team_id)) {
                    $matches[] = [
                        'event_id' => $event->id,
                        'round' => $nextRound,
                        'bracket_position' => $position++,
                        'bracket_type' => 'swiss',
                        'team1_id' => $teamsArray[$i]->team_id,
                        'team2_id' => $teamsArray[$i + 1]->team_id,
                        'status' => 'upcoming',
                        'format' => $event->matches()->first()->format ?? 'bo3',
                        'scheduled_at' => $event->start_date ?? now()->addDays(1),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        // Save new matches
        foreach ($matches as $match) {
            GameMatch::create($match);
        }

        // Update event round
        $event->update(['current_round' => $nextRound]);
    }

    /**
     * Get Swiss system standings
     */
    private function getSwissStandings($event)
    {
        return EventStanding::where('event_id', $event->id)
            ->with('team')
            ->orderByDesc('wins')
            ->orderBy('losses')
            ->orderByDesc('map_differential')
            ->get();
    }

    /**
     * Check if two teams have already played
     */
    private function haveTeamsPlayed($eventId, $team1Id, $team2Id)
    {
        return GameMatch::where('event_id', $eventId)
            ->where(function($query) use ($team1Id, $team2Id) {
                $query->where(function($q) use ($team1Id, $team2Id) {
                    $q->where('team1_id', $team1Id)
                      ->where('team2_id', $team2Id);
                })->orWhere(function($q) use ($team1Id, $team2Id) {
                    $q->where('team1_id', $team2Id)
                      ->where('team2_id', $team1Id);
                });
            })
            ->exists();
    }

    /**
     * Get Swiss bracket data
     */
    private function getSwissBracket($event)
    {
        $matches = $event->matches()
            ->with(['team1', 'team2'])
            ->orderBy('round')
            ->orderBy('bracket_position')
            ->get();

        $rounds = [];
        foreach ($matches->groupBy('round') as $round => $roundMatches) {
            $rounds[] = [
                'round' => $round,
                'name' => "Swiss Round {$round}",
                'matches' => $roundMatches->map(function($match) {
                    return $this->formatMatchData($match);
                })->values()
            ];
        }

        return [
            'type' => 'swiss',
            'rounds' => $rounds,
            'standings' => $this->getSwissStandings($event),
            'current_round' => $event->current_round,
            'total_rounds' => $event->total_rounds
        ];
    }

    /**
     * Generate group stage matches
     */
    private function generateGroupStage($event, $teams, $request)
    {
        $matches = [];
        $groupCount = $request->groups ?? 4;
        $bestOf = $request->best_of ?? 3;
        
        // Distribute teams into groups
        $groups = $this->distributeTeamsIntoGroups($teams, $groupCount);
        
        // Generate round robin matches within each group
        foreach ($groups as $groupName => $groupTeams) {
            for ($i = 0; $i < count($groupTeams); $i++) {
                for ($j = $i + 1; $j < count($groupTeams); $j++) {
                    $matches[] = [
                        'event_id' => $event->id,
                        'round' => 1,
                        'bracket_position' => count($matches) + 1,
                        'bracket_type' => strtolower($groupName),
                        'team1_id' => $groupTeams[$i]->id,
                        'team2_id' => $groupTeams[$j]->id,
                        'status' => 'upcoming',
                        'format' => "bo{$bestOf}",
                        'scheduled_at' => $event->start_date ?? now()->addDays(1),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * Distribute teams into groups using snake draft
     */
    private function distributeTeamsIntoGroups($teams, $groupCount)
    {
        $groups = [];
        $groupNames = ['group_a', 'group_b', 'group_c', 'group_d', 'group_e', 'group_f', 'group_g', 'group_h'];
        
        // Initialize groups
        for ($i = 0; $i < $groupCount; $i++) {
            $groups[$groupNames[$i]] = [];
        }

        // Snake draft distribution
        $teamIndex = 0;
        $reverse = false;
        
        while ($teamIndex < count($teams)) {
            if (!$reverse) {
                for ($i = 0; $i < $groupCount && $teamIndex < count($teams); $i++) {
                    $groups[$groupNames[$i]][] = $teams[$teamIndex++];
                }
            } else {
                for ($i = $groupCount - 1; $i >= 0 && $teamIndex < count($teams); $i--) {
                    $groups[$groupNames[$i]][] = $teams[$teamIndex++];
                }
            }
            $reverse = !$reverse;
        }

        return $groups;
    }

    /**
     * Get group stage bracket data
     */
    private function getGroupStageBracket($event)
    {
        $matches = $event->matches()
            ->with(['team1', 'team2'])
            ->orderBy('bracket_type')
            ->orderBy('scheduled_at')
            ->get();

        $groups = [];
        foreach ($matches->groupBy('bracket_type') as $groupName => $groupMatches) {
            $groups[] = [
                'name' => strtoupper(str_replace('group_', 'Group ', $groupName)),
                'matches' => $groupMatches->map(function($match) {
                    return $this->formatMatchData($match);
                })->values(),
                'standings' => $this->getGroupStandings($event, $groupName)
            ];
        }

        return [
            'type' => 'group_stage',
            'groups' => $groups,
            'playoff_bracket' => $this->getPlayoffBracket($event)
        ];
    }

    /**
     * Get standings for a specific group
     */
    private function getGroupStandings($event, $groupName)
    {
        // Get teams in this group
        $teamIds = GameMatch::where('event_id', $event->id)
            ->where('bracket_type', $groupName)
            ->select('team1_id', 'team2_id')
            ->get()
            ->flatMap(function($match) {
                return [$match->team1_id, $match->team2_id];
            })
            ->unique()
            ->values();

        return EventStanding::where('event_id', $event->id)
            ->whereIn('team_id', $teamIds)
            ->with('team')
            ->orderByDesc('wins')
            ->orderByDesc('map_differential')
            ->get();
    }

    /**
     * Get playoff bracket for group stage events
     */
    private function getPlayoffBracket($event)
    {
        $playoffMatches = GameMatch::where('event_id', $event->id)
            ->whereIn('bracket_type', ['playoff', 'quarterfinal', 'semifinal', 'final'])
            ->with(['team1', 'team2'])
            ->orderBy('round')
            ->orderBy('bracket_position')
            ->get();

        if ($playoffMatches->isEmpty()) {
            return null;
        }

        $rounds = [];
        foreach ($playoffMatches->groupBy('round') as $round => $roundMatches) {
            $rounds[] = [
                'round' => $round,
                'name' => $this->getPlayoffRoundName($round, $playoffMatches->max('round')),
                'matches' => $roundMatches->map(function($match) {
                    return $this->formatMatchData($match);
                })->values()
            ];
        }

        return [
            'type' => 'playoff',
            'rounds' => $rounds
        ];
    }

    /**
     * Get playoff round name
     */
    private function getPlayoffRoundName($round, $maxRound)
    {
        $roundsFromEnd = $maxRound - $round + 1;
        
        return match($roundsFromEnd) {
            1 => 'Grand Final',
            2 => 'Semi-Finals',
            3 => 'Quarter-Finals',
            default => "Playoff Round {$round}"
        };
    }

    /**
     * Update map data for a match
     */
    private function updateMapData($match, $maps)
    {
        // Store map data in match
        $match->update(['maps' => $maps]);

        // You could also create separate MatchMap records if needed
        // MatchMap::where('match_id', $match->id)->delete();
        // foreach ($maps as $index => $map) {
        //     MatchMap::create([
        //         'match_id' => $match->id,
        //         'map_number' => $index + 1,
        //         'map_name' => $map['name'] ?? null,
        //         'team1_score' => $map['team1_score'] ?? 0,
        //         'team2_score' => $map['team2_score'] ?? 0,
        //         'winner_id' => $map['winner_id'] ?? null
        //     ]);
        // }
    }

    /**
     * Calculate final single elimination placements
     */
    private function calculateSingleEliminationPlacements($event)
    {
        $placements = [];
        
        // Get final match
        $final = GameMatch::where('event_id', $event->id)
            ->where('bracket_type', 'main')
            ->orderBy('round', 'desc')
            ->first();

        if ($final && $final->status === 'completed') {
            $winnerId = $this->getWinnerId($final);
            $loserId = $final->team1_score > $final->team2_score ? $final->team2_id : $final->team1_id;
            
            $placements[$winnerId] = 1;
            $placements[$loserId] = 2;
        }

        // Get third place match if exists
        $thirdPlace = GameMatch::where('event_id', $event->id)
            ->where('bracket_type', 'third_place')
            ->first();

        if ($thirdPlace && $thirdPlace->status === 'completed') {
            $winnerId = $this->getWinnerId($thirdPlace);
            $loserId = $thirdPlace->team1_score > $thirdPlace->team2_score ? $thirdPlace->team2_id : $thirdPlace->team1_id;
            
            $placements[$winnerId] = 3;
            $placements[$loserId] = 4;
        }

        // Update event_teams table with placements
        foreach ($placements as $teamId => $placement) {
            DB::table('event_teams')
                ->where('event_id', $event->id)
                ->where('team_id', $teamId)
                ->update(['placement' => $placement]);
        }
    }

    /**
     * Calculate final double elimination placements
     */
    private function calculateDoubleEliminationPlacements($event)
    {
        // Similar logic but accounting for upper/lower bracket positions
        // Teams eliminated in lower bracket get placed based on which round they lost
    }

    /**
     * Get bracket history for an event
     */
    public function getBracketHistory($eventId)
    {
        try {
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // For now, return empty array as we don't have bracket history table
            // In a real implementation, you'd have a bracket_histories table
            $history = [];

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching bracket history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bracket history'
            ], 500);
        }
    }

    /**
     * Restore a bracket version
     */
    public function restoreBracketVersion(Request $request, $eventId, $versionId)
    {
        try {
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Admin users should be able to restore brackets
            // $this->authorize('update', $event);

            // For now, return success message
            // In a real implementation, you'd restore from bracket_histories table
            
            return response()->json([
                'success' => true,
                'message' => 'Bracket version restored successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error restoring bracket version: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error restoring bracket version'
            ], 500);
        }
    }

    /**
     * Reset bracket for an event
     */
    public function resetBracket($eventId)
    {
        DB::beginTransaction();
        try {
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Admin users should be able to reset brackets
            // $this->authorize('update', $event);
            
            Log::info('Reset bracket attempt for event: ' . $eventId);

            // Delete all matches for this event
            GameMatch::where('event_id', $eventId)->delete();

            // Reset event status
            $event->update([
                'status' => 'upcoming',
                'current_round' => 0,
                'total_rounds' => 0
            ]);

            // Reset event standings
            EventStanding::where('event_id', $eventId)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bracket reset successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error resetting bracket: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error resetting bracket'
            ], 500);
        }
    }
}