<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Team;
use App\Models\MatchModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Events\BracketUpdated;
use App\Events\MatchUpdated;

class EnhancedBracketController extends Controller
{
    /**
     * Get complete bracket data for an event
     */
    public function getBracket($eventId)
    {
        $event = Event::with(['teams'])->findOrFail($eventId);
        
        // Check if this is a Swiss + Double Elimination format
        $isSwissDoubleElim = in_array($event->format, ['swiss_double_elim', 'swiss_double_elimination']);
        
        $bracketData = Cache::remember("bracket_data_{$eventId}", 60, function() use ($event, $isSwissDoubleElim) {
            if ($isSwissDoubleElim) {
                return $this->getSwissDoubleEliminationBracket($event);
            }
            
            return $this->getStandardBracket($event);
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'format' => $event->format,
                    'status' => $event->status,
                    'teams_count' => $event->teams()->count(),
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                ],
                'bracket' => $bracketData,
                'metadata' => $this->getBracketMetadata($event)
            ]
        ]);
    }

    /**
     * Get Swiss + Double Elimination bracket data
     */
    private function getSwissDoubleEliminationBracket($event)
    {
        return [
            'type' => 'swiss_double_elimination',
            'stages' => [
                'swiss' => $this->getSwissStage($event),
                'playoffs' => [
                    'upper_bracket' => $this->getUpperBracket($event),
                    'lower_bracket' => $this->getLowerBracket($event),
                    'grand_final' => $this->getGrandFinal($event)
                ]
            ]
        ];
    }

    /**
     * Get Swiss stage data
     */
    private function getSwissStage($event)
    {
        // Get Swiss standings
        $standings = DB::table('swiss_standings')
            ->join('teams', 'swiss_standings.team_id', '=', 'teams.id')
            ->where('swiss_standings.event_id', $event->id)
            ->select([
                'teams.id',
                'teams.name',
                'teams.short_name',
                'teams.logo',
                'teams.country',
                'swiss_standings.*'
            ])
            ->orderByDesc('swiss_standings.wins')
            ->orderByDesc('swiss_standings.swiss_score')
            ->orderByDesc('swiss_standings.buchholz_score')
            ->orderByDesc('swiss_standings.round_difference')
            ->get();

        // Get Swiss matches grouped by round
        $swissMatches = DB::table('matches')
            ->where('event_id', $event->id)
            ->where('stage_type', 'swiss')
            ->orderBy('swiss_round')
            ->orderBy('id')
            ->get()
            ->groupBy('swiss_round');

        $rounds = [];
        foreach ($swissMatches as $roundNumber => $matches) {
            $rounds[] = [
                'round' => $roundNumber,
                'name' => "Swiss Round {$roundNumber}",
                'matches' => $this->formatMatches($matches)
            ];
        }

        return [
            'format' => 'swiss',
            'standings' => $standings->map(function($team, $index) {
                $team->position = $index + 1;
                $team->qualified = $team->qualified_to_upper || $team->qualified_to_lower;
                $team->qualification_type = $team->qualified_to_upper ? 'upper' : ($team->qualified_to_lower ? 'lower' : null);
                return $team;
            }),
            'rounds' => $rounds,
            'total_rounds' => 3, // Standard Swiss is 3 rounds for 8 teams
            'qualification_rules' => [
                'upper_bracket' => 'Top 4 teams',
                'lower_bracket' => 'Bottom 4 teams'
            ]
        ];
    }

    /**
     * Get Upper Bracket data
     */
    private function getUpperBracket($event)
    {
        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $event->id)
            ->where('m.stage_type', 'upper_bracket')
            ->select([
                'm.*',
                't1.name as team1_name',
                't1.short_name as team1_short',
                't1.logo as team1_logo',
                't1.country as team1_country',
                't2.name as team2_name',
                't2.short_name as team2_short',
                't2.logo as team2_logo',
                't2.country as team2_country'
            ])
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();

        return $this->formatBracketRounds($matches, 'upper');
    }

    /**
     * Get Lower Bracket data
     */
    private function getLowerBracket($event)
    {
        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $event->id)
            ->where('m.stage_type', 'lower_bracket')
            ->select([
                'm.*',
                't1.name as team1_name',
                't1.short_name as team1_short',
                't1.logo as team1_logo',
                't1.country as team1_country',
                't2.name as team2_name',
                't2.short_name as team2_short',
                't2.logo as team2_logo',
                't2.country as team2_country'
            ])
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();

        return $this->formatBracketRounds($matches, 'lower');
    }

    /**
     * Get Grand Final data
     */
    private function getGrandFinal($event)
    {
        $match = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $event->id)
            ->where('m.stage_type', 'grand_final')
            ->select([
                'm.*',
                't1.name as team1_name',
                't1.short_name as team1_short',
                't1.logo as team1_logo',
                't1.country as team1_country',
                't2.name as team2_name',
                't2.short_name as team2_short',
                't2.logo as team2_logo',
                't2.country as team2_country'
            ])
            ->first();

        if (!$match) {
            return null;
        }

        return $this->formatMatch($match);
    }

    /**
     * Generate Swiss + Double Elimination bracket
     */
    public function generateSwissDoubleElimination(Request $request, $eventId)
    {
        $this->authorize('manage-events');

        $request->validate([
            'teams' => 'required|array|min:8|max:16',
            'swiss_rounds' => 'integer|min:3|max:5',
            'upper_bracket_bo' => 'integer|in:3,5,7',
            'lower_bracket_bo' => 'integer|in:3,5,7',
            'grand_final_bo' => 'integer|in:5,7,9'
        ]);

        DB::beginTransaction();
        
        try {
            $event = Event::findOrFail($eventId);
            
            // Clear existing matches
            DB::table('matches')->where('event_id', $eventId)->delete();
            DB::table('swiss_standings')->where('event_id', $eventId)->delete();
            DB::table('bracket_progression')->where('event_id', $eventId)->delete();
            
            // Initialize Swiss standings
            foreach ($request->teams as $teamId) {
                DB::table('swiss_standings')->insert([
                    'event_id' => $eventId,
                    'team_id' => $teamId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                DB::table('bracket_progression')->insert([
                    'event_id' => $eventId,
                    'team_id' => $teamId,
                    'stage' => 'swiss',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            // Generate Swiss Round 1 matches
            $this->generateSwissRound($eventId, 1, $request->teams);
            
            // Create placeholder matches for playoffs
            $this->createPlayoffPlaceholders($eventId, [
                'upper_bo' => $request->upper_bracket_bo ?? 5,
                'lower_bo' => $request->lower_bracket_bo ?? 5,
                'grand_final_bo' => $request->grand_final_bo ?? 7
            ]);
            
            // Update event
            $event->update([
                'format' => 'swiss_double_elimination',
                'status' => 'ongoing',
                'current_round' => 1
            ]);
            
            DB::commit();
            
            // Clear cache
            Cache::forget("bracket_data_{$eventId}");
            
            // Broadcast update
            broadcast(new BracketUpdated($event))->toOthers();
            
            return response()->json([
                'success' => true,
                'message' => 'Swiss + Double Elimination bracket generated successfully',
                'data' => [
                    'swiss_rounds' => $request->swiss_rounds ?? 3,
                    'teams_count' => count($request->teams),
                    'format' => 'swiss_double_elimination'
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error generating bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Swiss round matches
     */
    private function generateSwissRound($eventId, $round, $teamIds = null)
    {
        if ($round === 1 && $teamIds) {
            // First round: Random pairing
            $shuffled = collect($teamIds)->shuffle();
            $matches = [];
            
            for ($i = 0; $i < count($shuffled); $i += 2) {
                if (isset($shuffled[$i + 1])) {
                    $matches[] = [
                        'event_id' => $eventId,
                        'team1_id' => $shuffled[$i],
                        'team2_id' => $shuffled[$i + 1],
                        'stage_type' => 'swiss',
                        'swiss_round' => $round,
                        'round' => $round,
                        'bracket_position' => ($i / 2) + 1,
                        'best_of' => 3,
                        'format' => 'BO3',
                        'status' => 'upcoming',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
            
            DB::table('matches')->insert($matches);
        } else {
            // Subsequent rounds: Swiss pairing based on standings
            $standings = DB::table('swiss_standings')
                ->where('event_id', $eventId)
                ->orderByDesc('wins')
                ->orderByDesc('swiss_score')
                ->orderByDesc('buchholz_score')
                ->get();
            
            $paired = [];
            $matches = [];
            $position = 1;
            
            foreach ($standings as $team) {
                if (in_array($team->team_id, $paired)) continue;
                
                // Find best opponent with same record who hasn't played this team
                $opponents = json_decode($team->opponents_faced ?? '[]', true);
                
                foreach ($standings as $opponent) {
                    if ($opponent->team_id === $team->team_id) continue;
                    if (in_array($opponent->team_id, $paired)) continue;
                    if (in_array($opponent->team_id, $opponents)) continue;
                    if ($opponent->wins === $team->wins) {
                        // Found a match
                        $matches[] = [
                            'event_id' => $eventId,
                            'team1_id' => $team->team_id,
                            'team2_id' => $opponent->team_id,
                            'stage_type' => 'swiss',
                            'swiss_round' => $round,
                            'round' => $round,
                            'bracket_position' => $position++,
                            'best_of' => 3,
                            'format' => 'BO3',
                            'status' => 'upcoming',
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                        
                        $paired[] = $team->team_id;
                        $paired[] = $opponent->team_id;
                        break;
                    }
                }
            }
            
            DB::table('matches')->insert($matches);
        }
    }

    /**
     * Create playoff placeholder matches
     */
    private function createPlayoffPlaceholders($eventId, $settings)
    {
        $matches = [];
        
        // Upper Bracket Semifinals (2 matches)
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'upper_bracket',
            'round' => 1,
            'bracket_position' => 1,
            'match_number' => 'UB-SF1',
            'round_name' => 'Upper Bracket Semifinals',
            'team1_source' => 'swiss_1st',
            'team2_source' => 'swiss_4th',
            'best_of' => $settings['upper_bo'],
            'format' => "BO{$settings['upper_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'UB-F',
            'loser_advances_to' => 'LB-SF',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'upper_bracket',
            'round' => 1,
            'bracket_position' => 2,
            'match_number' => 'UB-SF2',
            'round_name' => 'Upper Bracket Semifinals',
            'team1_source' => 'swiss_2nd',
            'team2_source' => 'swiss_3rd',
            'best_of' => $settings['upper_bo'],
            'format' => "BO{$settings['upper_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'UB-F',
            'loser_advances_to' => 'LB-SF',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        // Upper Bracket Final
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'upper_bracket',
            'round' => 2,
            'bracket_position' => 1,
            'match_number' => 'UB-F',
            'round_name' => 'Upper Bracket Final',
            'team1_source' => 'winner_of_UB-SF1',
            'team2_source' => 'winner_of_UB-SF2',
            'best_of' => $settings['upper_bo'],
            'format' => "BO{$settings['upper_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'GF',
            'loser_advances_to' => 'LB-F',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        // Lower Bracket Round 1 (2 matches)
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'lower_bracket',
            'round' => 1,
            'bracket_position' => 1,
            'match_number' => 'LB-R1-1',
            'round_name' => 'Lower Bracket Round 1',
            'team1_source' => 'swiss_5th',
            'team2_source' => 'swiss_8th',
            'best_of' => $settings['lower_bo'],
            'format' => "BO{$settings['lower_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'LB-QF1',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'lower_bracket',
            'round' => 1,
            'bracket_position' => 2,
            'match_number' => 'LB-R1-2',
            'round_name' => 'Lower Bracket Round 1',
            'team1_source' => 'swiss_6th',
            'team2_source' => 'swiss_7th',
            'best_of' => $settings['lower_bo'],
            'format' => "BO{$settings['lower_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'LB-QF2',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        // Lower Bracket Quarterfinals (2 matches)
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'lower_bracket',
            'round' => 2,
            'bracket_position' => 1,
            'match_number' => 'LB-QF1',
            'round_name' => 'Lower Bracket Quarterfinals',
            'team1_source' => 'winner_of_LB-R1-1',
            'team2_source' => 'loser_of_UB-SF2',
            'best_of' => $settings['lower_bo'],
            'format' => "BO{$settings['lower_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'LB-SF',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'lower_bracket',
            'round' => 2,
            'bracket_position' => 2,
            'match_number' => 'LB-QF2',
            'round_name' => 'Lower Bracket Quarterfinals',
            'team1_source' => 'winner_of_LB-R1-2',
            'team2_source' => 'loser_of_UB-SF1',
            'best_of' => $settings['lower_bo'],
            'format' => "BO{$settings['lower_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'LB-SF',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        // Lower Bracket Semifinal
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'lower_bracket',
            'round' => 3,
            'bracket_position' => 1,
            'match_number' => 'LB-SF',
            'round_name' => 'Lower Bracket Semifinal',
            'team1_source' => 'winner_of_LB-QF1',
            'team2_source' => 'winner_of_LB-QF2',
            'best_of' => $settings['lower_bo'],
            'format' => "BO{$settings['lower_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'LB-F',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        // Lower Bracket Final
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'lower_bracket',
            'round' => 4,
            'bracket_position' => 1,
            'match_number' => 'LB-F',
            'round_name' => 'Lower Bracket Final',
            'team1_source' => 'winner_of_LB-SF',
            'team2_source' => 'loser_of_UB-F',
            'best_of' => $settings['lower_bo'],
            'format' => "BO{$settings['lower_bo']}",
            'status' => 'upcoming',
            'winner_advances_to' => 'GF',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        // Grand Final
        $matches[] = [
            'event_id' => $eventId,
            'stage_type' => 'grand_final',
            'round' => 1,
            'bracket_position' => 1,
            'match_number' => 'GF',
            'round_name' => 'Grand Final',
            'team1_source' => 'winner_of_UB-F',
            'team2_source' => 'winner_of_LB-F',
            'best_of' => $settings['grand_final_bo'],
            'format' => "BO{$settings['grand_final_bo']}",
            'status' => 'upcoming',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        // Insert all matches
        foreach ($matches as $match) {
            DB::table('matches')->insert($match);
        }
    }

    /**
     * Update Swiss match result and calculate standings
     */
    public function updateSwissMatch(Request $request, $matchId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'team1_score' => 'required|integer|min:0|max:2',
            'team2_score' => 'required|integer|min:0|max:2',
            'maps' => 'array',
            'status' => 'required|in:live,completed'
        ]);
        
        DB::beginTransaction();
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match || $match->stage_type !== 'swiss') {
                throw new \Exception('Invalid Swiss match');
            }
            
            // Update match
            DB::table('matches')->where('id', $matchId)->update([
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'status' => $request->status,
                'maps_data' => json_encode($request->maps ?? []),
                'completed_at' => $request->status === 'completed' ? now() : null,
                'updated_at' => now()
            ]);
            
            if ($request->status === 'completed') {
                // Update Swiss standings
                $this->updateSwissStandings($match, $request->team1_score, $request->team2_score);
                
                // Check if round is complete
                $roundComplete = $this->isSwissRoundComplete($match->event_id, $match->swiss_round);
                
                if ($roundComplete) {
                    // Calculate Buchholz scores
                    $this->calculateBuchholzScores($match->event_id);
                    
                    // Check if Swiss stage is complete
                    if ($match->swiss_round >= 3) {
                        // Swiss complete, advance teams to playoffs
                        $this->advanceTeamsToPlayoffs($match->event_id);
                    } else {
                        // Generate next Swiss round
                        $this->generateSwissRound($match->event_id, $match->swiss_round + 1);
                    }
                }
            }
            
            DB::commit();
            
            // Clear cache
            Cache::forget("bracket_data_{$match->event_id}");
            
            // Broadcast update
            broadcast(new MatchUpdated($match))->toOthers();
            
            return response()->json([
                'success' => true,
                'message' => 'Swiss match updated successfully',
                'round_complete' => $roundComplete ?? false
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Swiss standings after match completion
     */
    private function updateSwissStandings($match, $team1Score, $team2Score)
    {
        // Ensure scores are integers to prevent SQL injection
        $team1Score = (int) $team1Score;
        $team2Score = (int) $team2Score;
        
        $winner = $team1Score > $team2Score ? $match->team1_id : $match->team2_id;
        $loser = $team1Score > $team2Score ? $match->team2_id : $match->team1_id;
        
        // Update winner
        $winnerStanding = DB::table('swiss_standings')
            ->where('event_id', $match->event_id)
            ->where('team_id', $winner)
            ->first();
            
        $winnerOpponents = json_decode($winnerStanding->opponents_faced ?? '[]', true);
        $winnerOpponents[] = $loser;
        
        DB::table('swiss_standings')
            ->where('event_id', $match->event_id)
            ->where('team_id', $winner)
            ->update([
                'wins' => DB::raw('wins + 1'),
                'map_wins' => DB::raw("map_wins + {$team1Score}"),
                'map_losses' => DB::raw("map_losses + {$team2Score}"),
                'swiss_score' => DB::raw('swiss_score + 1'),
                'round_difference' => DB::raw("round_difference + " . ($team1Score - $team2Score)),
                'opponents_faced' => json_encode($winnerOpponents),
                'updated_at' => now()
            ]);
            
        // Update loser
        $loserStanding = DB::table('swiss_standings')
            ->where('event_id', $match->event_id)
            ->where('team_id', $loser)
            ->first();
            
        $loserOpponents = json_decode($loserStanding->opponents_faced ?? '[]', true);
        $loserOpponents[] = $winner;
        
        DB::table('swiss_standings')
            ->where('event_id', $match->event_id)
            ->where('team_id', $loser)
            ->update([
                'losses' => DB::raw('losses + 1'),
                'map_wins' => DB::raw("map_wins + {$team2Score}"),
                'map_losses' => DB::raw("map_losses + {$team1Score}"),
                'round_difference' => DB::raw("round_difference + " . ($team2Score - $team1Score)),
                'opponents_faced' => json_encode($loserOpponents),
                'updated_at' => now()
            ]);
    }

    /**
     * Check if Swiss round is complete
     */
    private function isSwissRoundComplete($eventId, $round)
    {
        $incomplete = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('stage_type', 'swiss')
            ->where('swiss_round', $round)
            ->where('status', '!=', 'completed')
            ->count();
            
        return $incomplete === 0;
    }

    /**
     * Calculate Buchholz scores for Swiss standings
     */
    private function calculateBuchholzScores($eventId)
    {
        $standings = DB::table('swiss_standings')->where('event_id', $eventId)->get();
        
        foreach ($standings as $team) {
            $opponents = json_decode($team->opponents_faced ?? '[]', true);
            $buchholzScore = 0;
            
            foreach ($opponents as $opponentId) {
                $opponentStanding = DB::table('swiss_standings')
                    ->where('event_id', $eventId)
                    ->where('team_id', $opponentId)
                    ->first();
                    
                if ($opponentStanding) {
                    $buchholzScore += $opponentStanding->swiss_score;
                }
            }
            
            DB::table('swiss_standings')
                ->where('id', $team->id)
                ->update(['buchholz_score' => $buchholzScore]);
        }
    }

    /**
     * Advance teams from Swiss to playoffs
     */
    private function advanceTeamsToPlayoffs($eventId)
    {
        // Get final Swiss standings
        $standings = DB::table('swiss_standings')
            ->where('event_id', $eventId)
            ->orderByDesc('wins')
            ->orderByDesc('swiss_score')
            ->orderByDesc('buchholz_score')
            ->orderByDesc('round_difference')
            ->get();
        
        // Update rankings
        foreach ($standings as $index => $team) {
            DB::table('swiss_standings')
                ->where('id', $team->id)
                ->update([
                    'ranking' => $index + 1,
                    'qualified_to_upper' => $index < 4,
                    'qualified_to_lower' => $index >= 4
                ]);
        }
        
        // Assign teams to playoff matches
        $this->assignPlayoffTeams($eventId, $standings);
    }

    /**
     * Assign teams to playoff matches based on Swiss results
     */
    private function assignPlayoffTeams($eventId, $standings)
    {
        $teamsByRank = [];
        foreach ($standings as $index => $team) {
            $teamsByRank[$index + 1] = $team->team_id;
        }
        
        // Upper Bracket assignments
        DB::table('matches')
            ->where('event_id', $eventId)
            ->where('match_number', 'UB-SF1')
            ->update([
                'team1_id' => $teamsByRank[1] ?? null,
                'team2_id' => $teamsByRank[4] ?? null
            ]);
            
        DB::table('matches')
            ->where('event_id', $eventId)
            ->where('match_number', 'UB-SF2')
            ->update([
                'team1_id' => $teamsByRank[2] ?? null,
                'team2_id' => $teamsByRank[3] ?? null
            ]);
            
        // Lower Bracket assignments
        DB::table('matches')
            ->where('event_id', $eventId)
            ->where('match_number', 'LB-R1-1')
            ->update([
                'team1_id' => $teamsByRank[5] ?? null,
                'team2_id' => $teamsByRank[8] ?? null
            ]);
            
        DB::table('matches')
            ->where('event_id', $eventId)
            ->where('match_number', 'LB-R1-2')
            ->update([
                'team1_id' => $teamsByRank[6] ?? null,
                'team2_id' => $teamsByRank[7] ?? null
            ]);
            
        // Update bracket progression
        foreach ($teamsByRank as $rank => $teamId) {
            DB::table('bracket_progression')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->update([
                    'stage' => $rank <= 4 ? 'upper_bracket' : 'lower_bracket',
                    'current_position' => $rank <= 4 ? "UB-SF" : "LB-R1"
                ]);
        }
    }

    /**
     * Update playoff match and handle bracket progression
     */
    public function updatePlayoffMatch(Request $request, $matchId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'status' => 'required|in:upcoming,live,completed',
            'maps' => 'array'
        ]);
        
        DB::beginTransaction();
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                throw new \Exception('Match not found');
            }
            
            // Update match
            DB::table('matches')->where('id', $matchId)->update([
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'status' => $request->status,
                'maps_data' => json_encode($request->maps ?? []),
                'completed_at' => $request->status === 'completed' ? now() : null,
                'updated_at' => now()
            ]);
            
            if ($request->status === 'completed') {
                $winnerId = $request->team1_score > $request->team2_score ? $match->team1_id : $match->team2_id;
                $loserId = $request->team1_score > $request->team2_score ? $match->team2_id : $match->team1_id;
                
                // Update match winner
                DB::table('matches')->where('id', $matchId)->update(['winner_id' => $winnerId]);
                
                // Handle bracket progression
                $this->handleBracketProgression($match, $winnerId, $loserId);
                
                // Update bracket progression tracking
                $this->updateBracketProgression($match, $winnerId, $loserId);
            }
            
            DB::commit();
            
            // Clear cache
            Cache::forget("bracket_data_{$match->event_id}");
            
            // Broadcast update
            broadcast(new MatchUpdated($match))->toOthers();
            
            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle bracket progression after match completion
     */
    private function handleBracketProgression($match, $winnerId, $loserId)
    {
        // Advance winner
        if ($match->winner_advances_to) {
            $nextMatch = DB::table('matches')
                ->where('event_id', $match->event_id)
                ->where('match_number', $match->winner_advances_to)
                ->first();
                
            if ($nextMatch) {
                // Determine slot based on match source
                if ($nextMatch->team1_source === "winner_of_{$match->match_number}") {
                    DB::table('matches')->where('id', $nextMatch->id)->update(['team1_id' => $winnerId]);
                } elseif ($nextMatch->team2_source === "winner_of_{$match->match_number}") {
                    DB::table('matches')->where('id', $nextMatch->id)->update(['team2_id' => $winnerId]);
                }
            }
        }
        
        // Advance loser (for upper bracket matches)
        if ($match->loser_advances_to) {
            $nextMatch = DB::table('matches')
                ->where('event_id', $match->event_id)
                ->where('match_number', $match->loser_advances_to)
                ->first();
                
            if ($nextMatch) {
                // Determine slot based on match source
                if ($nextMatch->team1_source === "loser_of_{$match->match_number}") {
                    DB::table('matches')->where('id', $nextMatch->id)->update(['team1_id' => $loserId]);
                } elseif ($nextMatch->team2_source === "loser_of_{$match->match_number}") {
                    DB::table('matches')->where('id', $nextMatch->id)->update(['team2_id' => $loserId]);
                }
            }
        }
    }

    /**
     * Update bracket progression tracking
     */
    private function updateBracketProgression($match, $winnerId, $loserId)
    {
        // Ensure match scores are integers to prevent SQL injection
        $team1Score = (int) $match->team1_score;
        $team2Score = (int) $match->team2_score;
        // Update winner progression
        $winnerProgression = DB::table('bracket_progression')
            ->where('event_id', $match->event_id)
            ->where('team_id', $winnerId)
            ->first();
            
        if ($winnerProgression) {
            $pathHistory = json_decode($winnerProgression->path_history ?? '[]', true);
            $pathHistory[] = $match->id;
            
            $updates = [
                'matches_played' => DB::raw('matches_played + 1'),
                'matches_won' => DB::raw('matches_won + 1'),
                'maps_played' => DB::raw("maps_played + {$team1Score} + {$team2Score}"),
                'maps_won' => DB::raw("maps_won + " . ($winnerId === $match->team1_id ? $team1Score : $team2Score)),
                'path_history' => json_encode($pathHistory),
                'current_position' => $match->winner_advances_to,
                'updated_at' => now()
            ];
            
            // Check if this was the grand final
            if ($match->stage_type === 'grand_final') {
                $updates['final_placement'] = 1;
                $updates['stage'] = 'champion';
            }
            
            DB::table('bracket_progression')
                ->where('id', $winnerProgression->id)
                ->update($updates);
        }
        
        // Update loser progression
        $loserProgression = DB::table('bracket_progression')
            ->where('event_id', $match->event_id)
            ->where('team_id', $loserId)
            ->first();
            
        if ($loserProgression) {
            $pathHistory = json_decode($loserProgression->path_history ?? '[]', true);
            $pathHistory[] = $match->id;
            
            $updates = [
                'matches_played' => DB::raw('matches_played + 1'),
                'maps_played' => DB::raw("maps_played + {$team1Score} + {$team2Score}"),
                'maps_won' => DB::raw("maps_won + " . ($loserId === $match->team1_id ? $team1Score : $team2Score)),
                'path_history' => json_encode($pathHistory),
                'updated_at' => now()
            ];
            
            // Determine placement based on elimination round
            if (!$match->loser_advances_to) {
                $updates['stage'] = 'eliminated';
                $updates['elimination_round'] = $match->round_name;
                $updates['final_placement'] = $this->calculatePlacement($match);
            } else {
                $updates['current_position'] = $match->loser_advances_to;
                $updates['stage'] = 'lower_bracket';
            }
            
            DB::table('bracket_progression')
                ->where('id', $loserProgression->id)
                ->update($updates);
        }
    }

    /**
     * Calculate team placement based on elimination round
     */
    private function calculatePlacement($match)
    {
        $placements = [
            'Grand Final' => 2,
            'Lower Bracket Final' => 3,
            'Lower Bracket Semifinal' => 4,
            'Upper Bracket Final' => 3,
            'Lower Bracket Quarterfinals' => 5,
            'Upper Bracket Semifinals' => 5,
            'Lower Bracket Round 1' => 7
        ];
        
        return $placements[$match->round_name] ?? 9;
    }

    /**
     * Format matches for API response
     */
    private function formatMatches($matches)
    {
        return $matches->map(function($match) {
            return $this->formatMatch($match);
        });
    }

    /**
     * Format single match for API response
     */
    private function formatMatch($match)
    {
        return [
            'id' => $match->id,
            'match_number' => $match->match_number ?? null,
            'round_name' => $match->round_name ?? null,
            'team1' => [
                'id' => $match->team1_id,
                'name' => $match->team1_name ?? 'TBD',
                'short_name' => $match->team1_short ?? null,
                'logo' => $match->team1_logo ?? null,
                'country' => $match->team1_country ?? null,
                'score' => $match->team1_score,
                'source' => $match->team1_source ?? null
            ],
            'team2' => [
                'id' => $match->team2_id,
                'name' => $match->team2_name ?? 'TBD',
                'short_name' => $match->team2_short ?? null,
                'logo' => $match->team2_logo ?? null,
                'country' => $match->team2_country ?? null,
                'score' => $match->team2_score,
                'source' => $match->team2_source ?? null
            ],
            'status' => $match->status,
            'best_of' => $match->best_of ?? 3,
            'format' => $match->format ?? 'BO3',
            'scheduled_at' => $match->scheduled_at,
            'started_at' => $match->started_at ?? null,
            'completed_at' => $match->completed_at ?? null,
            'winner_id' => $match->winner_id ?? null,
            'winner_advances_to' => $match->winner_advances_to ?? null,
            'loser_advances_to' => $match->loser_advances_to ?? null,
            'stream_url' => $match->stream_url ?? null,
            'vod_url' => $match->vod_url ?? null,
            'maps_data' => $match->maps_data ? json_decode($match->maps_data, true) : null
        ];
    }

    /**
     * Format bracket rounds for API response
     */
    private function formatBracketRounds($matches, $bracketType)
    {
        $rounds = [];
        $groupedMatches = $matches->groupBy('round');
        
        foreach ($groupedMatches as $roundNumber => $roundMatches) {
            $roundName = $this->getRoundName($bracketType, $roundNumber, count($groupedMatches));
            
            $rounds[] = [
                'round' => $roundNumber,
                'name' => $roundName,
                'matches' => $this->formatMatches($roundMatches)
            ];
        }
        
        return $rounds;
    }

    /**
     * Get round name based on bracket type and round number
     */
    private function getRoundName($bracketType, $roundNumber, $totalRounds)
    {
        if ($bracketType === 'upper') {
            $names = [
                1 => 'Upper Bracket Semifinals',
                2 => 'Upper Bracket Final'
            ];
        } else {
            $names = [
                1 => 'Lower Bracket Round 1',
                2 => 'Lower Bracket Quarterfinals',
                3 => 'Lower Bracket Semifinal',
                4 => 'Lower Bracket Final'
            ];
        }
        
        return $names[$roundNumber] ?? "Round {$roundNumber}";
    }

    /**
     * Get bracket metadata
     */
    private function getBracketMetadata($event)
    {
        $totalMatches = DB::table('matches')->where('event_id', $event->id)->count();
        $completedMatches = DB::table('matches')
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->count();
            
        $currentStage = 'swiss';
        if ($completedMatches > 0) {
            $latestMatch = DB::table('matches')
                ->where('event_id', $event->id)
                ->where('status', 'completed')
                ->orderByDesc('completed_at')
                ->first();
                
            if ($latestMatch) {
                $currentStage = $latestMatch->stage_type ?? 'swiss';
            }
        }
        
        return [
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'remaining_matches' => $totalMatches - $completedMatches,
            'current_stage' => $currentStage,
            'progress_percentage' => $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100, 1) : 0
        ];
    }

    /**
     * Get standard bracket (non-Swiss)
     */
    private function getStandardBracket($event)
    {
        // Use existing BracketController logic for standard formats
        $bracketController = app(BracketController::class);
        return $bracketController->show($event->id);
    }
    
    /**
     * Get Swiss standings for an event
     */
    public function getSwissStandings($eventId)
    {
        $event = Event::findOrFail($eventId);
        
        $standings = DB::table('swiss_standings')
            ->join('teams', 'swiss_standings.team_id', '=', 'teams.id')
            ->where('swiss_standings.event_id', $eventId)
            ->select([
                'teams.id',
                'teams.name',
                'teams.short_name',
                'teams.logo',
                'teams.country',
                'swiss_standings.*'
            ])
            ->orderByDesc('swiss_standings.wins')
            ->orderByDesc('swiss_standings.swiss_score')
            ->orderByDesc('swiss_standings.buchholz_score')
            ->orderByDesc('swiss_standings.round_difference')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name
                ],
                'standings' => $standings->map(function($team, $index) {
                    $team->position = $index + 1;
                    $team->qualified = $team->qualified_to_upper || $team->qualified_to_lower;
                    $team->qualification_type = $team->qualified_to_upper ? 'upper' : ($team->qualified_to_lower ? 'lower' : null);
                    return $team;
                })
            ]
        ]);
    }
    
    /**
     * Get bracket progression for teams
     */
    public function getBracketProgression($eventId)
    {
        $event = Event::findOrFail($eventId);
        
        $progression = DB::table('bracket_progression')
            ->join('teams', 'bracket_progression.team_id', '=', 'teams.id')
            ->where('bracket_progression.event_id', $eventId)
            ->select([
                'teams.id',
                'teams.name',
                'teams.short_name',
                'teams.logo',
                'teams.country',
                'bracket_progression.*'
            ])
            ->orderBy('bracket_progression.final_placement')
            ->orderByDesc('bracket_progression.matches_won')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name
                ],
                'progression' => $progression->map(function($team) {
                    $team->path_history = json_decode($team->path_history ?? '[]', true);
                    return $team;
                })
            ]
        ]);
    }
    
    /**
     * Get bracket status and next matches
     */
    public function getBracketStatus($eventId)
    {
        $event = Event::findOrFail($eventId);
        
        $currentStage = 'swiss';
        $swissComplete = false;
        $playoffsStarted = false;
        
        // Check Swiss completion
        $swissMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('stage_type', 'swiss')
            ->get();
            
        if ($swissMatches->count() > 0) {
            $swissComplete = $swissMatches->where('status', '!=', 'completed')->count() === 0;
        }
        
        // Check playoffs status
        $playoffMatches = DB::table('matches')
            ->where('event_id', $eventId)
            ->whereIn('stage_type', ['upper_bracket', 'lower_bracket', 'grand_final'])
            ->get();
            
        if ($playoffMatches->count() > 0) {
            $playoffsStarted = $playoffMatches->where('status', '!=', 'upcoming')->count() > 0;
            if ($playoffsStarted) {
                $currentStage = 'playoffs';
            }
        }
        
        // Get next matches
        $nextMatches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->where('m.status', 'upcoming')
            ->select([
                'm.*',
                't1.name as team1_name',
                't2.name as team2_name'
            ])
            ->orderBy('m.scheduled_at')
            ->limit(5)
            ->get();
            
        // Get live matches
        $liveMatches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->where('m.status', 'live')
            ->select([
                'm.*',
                't1.name as team1_name',
                't2.name as team2_name'
            ])
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'format' => $event->format,
                    'status' => $event->status
                ],
                'bracket_status' => [
                    'current_stage' => $currentStage,
                    'swiss_complete' => $swissComplete,
                    'playoffs_started' => $playoffsStarted,
                    'total_matches' => $swissMatches->count() + $playoffMatches->count(),
                    'completed_matches' => $swissMatches->where('status', 'completed')->count() + $playoffMatches->where('status', 'completed')->count()
                ],
                'live_matches' => $liveMatches,
                'next_matches' => $nextMatches
            ]
        ]);
    }
    
    /**
     * Manually advance teams from Swiss to playoffs
     */
    public function advanceFromSwiss(Request $request, $eventId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'force' => 'boolean'
        ]);
        
        $event = Event::findOrFail($eventId);
        
        // Check if Swiss is complete
        $swissIncomplete = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('stage_type', 'swiss')
            ->where('status', '!=', 'completed')
            ->count();
            
        if ($swissIncomplete > 0 && !$request->force) {
            return response()->json([
                'success' => false,
                'message' => 'Swiss stage is not complete. Use force=true to advance anyway.'
            ], 400);
        }
        
        DB::beginTransaction();
        
        try {
            $this->advanceTeamsToPlayoffs($eventId);
            
            DB::commit();
            
            // Clear cache
            Cache::forget("bracket_data_{$eventId}");
            
            return response()->json([
                'success' => true,
                'message' => 'Teams advanced to playoffs successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error advancing teams: ' . $e->getMessage()
            ], 500);
        }
    }
}