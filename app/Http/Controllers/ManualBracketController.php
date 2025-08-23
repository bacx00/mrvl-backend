<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Event;
use App\Models\Team;
use App\Models\BracketMatch;
use App\Models\BracketStage;
use App\Models\BracketSeeding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManualBracketController extends Controller
{
    /**
     * Marvel Rivals Tournament Formats based on official competitive rules
     */
    const MARVEL_RIVALS_FORMATS = [
        'play_in' => [
            'name' => 'Play-in Stage (GSL Bracket)',
            'description' => '4 teams compete in GSL format, top 2 advance',
            'team_count' => 4,
            'format' => 'gsl',
            'best_of' => 3
        ],
        'open_qualifier' => [
            'name' => 'Open Qualifier',
            'description' => 'Single elimination BO1, top teams advance',
            'team_count' => [8, 16, 32, 64, 128],
            'format' => 'single_elimination',
            'best_of' => 1
        ],
        'closed_qualifier' => [
            'name' => 'Closed Qualifier',
            'description' => 'Double elimination bracket',
            'team_count' => [8, 16],
            'format' => 'double_elimination',
            'best_of' => 3
        ],
        'main_stage' => [
            'name' => 'Main Stage',
            'description' => '8 teams in double elimination bracket',
            'team_count' => 8,
            'format' => 'double_elimination',
            'best_of' => 3
        ],
        'championship' => [
            'name' => 'Championship Finals',
            'description' => 'Double elimination with BO5 grand finals',
            'team_count' => [4, 8],
            'format' => 'double_elimination',
            'best_of' => 5
        ],
        'custom' => [
            'name' => 'Custom Tournament',
            'description' => 'Manually configure bracket settings',
            'team_count' => 'flexible',
            'format' => 'flexible',
            'best_of' => 'flexible'
        ]
    ];

    /**
     * Marvel Rivals Game Modes for matches
     */
    const GAME_MODES = [
        'domination' => 'Domination',
        'convoy' => 'Convoy', 
        'convergence' => 'Convergence'
    ];

    /**
     * Get available tournament formats
     */
    public function getFormats()
    {
        return response()->json([
            'formats' => self::MARVEL_RIVALS_FORMATS,
            'game_modes' => self::GAME_MODES,
            'success' => true
        ]);
    }

    /**
     * Create a manual bracket with selected teams and format
     */
    public function createManualBracket(Request $request, $tournamentId)
    {
        $request->validate([
            'format_key' => 'required|string',
            'team_ids' => 'required|array|min:2',
            'team_ids.*' => 'exists:teams,id',
            'best_of' => 'integer|in:1,3,5,7',
            'bracket_type' => 'string|in:single_elimination,double_elimination,gsl,round_robin',
            'name' => 'required|string',
            'start_date' => 'date'
        ]);

        DB::beginTransaction();
        
        try {
            // Get tournament or event
            $tournament = Tournament::findOrFail($tournamentId);
            
            // Get selected format
            $formatKey = $request->format_key;
            $format = self::MARVEL_RIVALS_FORMATS[$formatKey] ?? self::MARVEL_RIVALS_FORMATS['custom'];
            
            // Override with custom settings if provided
            $bracketType = $request->bracket_type ?? $format['format'];
            $bestOf = $request->best_of ?? $format['best_of'];
            
            // Create bracket stage
            $stage = BracketStage::create([
                'tournament_id' => $tournament->id,
                'name' => $request->name,
                'type' => $bracketType,
                'stage_order' => BracketStage::where('tournament_id', $tournament->id)->max('stage_order') + 1 ?? 1,
                'status' => 'pending',
                'max_teams' => count($request->team_ids),
                'format_key' => $formatKey,
                'settings' => [
                    'best_of' => $bestOf,
                    'game_modes' => self::GAME_MODES,
                    'manual_bracket' => true
                ]
            ]);

            // Seed teams manually in the order provided
            $teams = Team::whereIn('id', $request->team_ids)->get();
            $seed = 1;
            foreach ($request->team_ids as $teamId) {
                BracketSeeding::create([
                    'tournament_id' => $tournament->id,
                    'bracket_stage_id' => $stage->id,
                    'team_id' => $teamId,
                    'seed' => $seed++,
                    'seeding_method' => 'manual',
                    'seeded_at' => now()
                ]);
            }

            // Generate initial bracket matches based on format
            $matches = $this->generateInitialMatches($stage, $teams, $bracketType, $bestOf);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Manual bracket created successfully',
                'stage' => $stage->load('seedings.team'),
                'matches' => $matches,
                'bracket_id' => $stage->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate initial matches based on bracket type
     */
    private function generateInitialMatches($stage, $teams, $bracketType, $bestOf)
    {
        $matches = collect();
        $teamCount = $teams->count();

        switch ($bracketType) {
            case 'single_elimination':
                $matches = $this->generateSingleEliminationMatches($stage, $teams, $bestOf);
                break;
                
            case 'double_elimination':
                $matches = $this->generateDoubleEliminationMatches($stage, $teams, $bestOf);
                break;
                
            case 'gsl':
                $matches = $this->generateGSLMatches($stage, $teams, $bestOf);
                break;
                
            case 'round_robin':
                $matches = $this->generateRoundRobinMatches($stage, $teams, $bestOf);
                break;
        }

        return $matches;
    }

    /**
     * Generate single elimination matches
     */
    private function generateSingleEliminationMatches($stage, $teams, $bestOf)
    {
        $matches = collect();
        $teamArray = $teams->values()->toArray();
        $rounds = ceil(log($teams->count(), 2));
        
        // Generate first round matches
        $matchNumber = 1;
        $firstRoundMatches = $teams->count() / 2;
        
        for ($i = 0; $i < $firstRoundMatches; $i++) {
            $team1Index = $i;
            $team2Index = $teams->count() - 1 - $i;
            
            $match = BracketMatch::create([
                'match_id' => "M{$stage->id}-R1-{$matchNumber}",
                'tournament_id' => $stage->tournament_id,
                'bracket_stage_id' => $stage->id,
                'round_name' => $this->getRoundName(1, $rounds),
                'round_number' => 1,
                'match_number' => $matchNumber++,
                'team1_id' => $teamArray[$team1Index]['id'],
                'team2_id' => $teamArray[$team2Index]['id'],
                'status' => 'pending',
                'best_of' => $bestOf,
                'scheduled_at' => Carbon::now()->addHours(1),
                'team1_score' => 0,
                'team2_score' => 0
            ]);
            
            $matches->push($match);
        }

        // Generate placeholder matches for subsequent rounds
        $previousRoundMatches = $matches;
        for ($round = 2; $round <= $rounds; $round++) {
            $roundMatches = collect();
            $matchesInRound = pow(2, $rounds - $round);
            $matchNumber = 1;
            
            for ($i = 0; $i < $matchesInRound; $i++) {
                $match = BracketMatch::create([
                    'match_id' => "M{$stage->id}-R{$round}-{$matchNumber}",
                    'tournament_id' => $stage->tournament_id,
                    'bracket_stage_id' => $stage->id,
                    'round_name' => $this->getRoundName($round, $rounds),
                    'round_number' => $round,
                    'match_number' => $matchNumber++,
                    'team1_id' => null,
                    'team2_id' => null,
                    'team1_source' => "Winner of M{$stage->id}-R" . ($round - 1) . "-" . ($i * 2 + 1),
                    'team2_source' => "Winner of M{$stage->id}-R" . ($round - 1) . "-" . ($i * 2 + 2),
                    'status' => 'pending',
                    'best_of' => $round == $rounds ? min($bestOf + 2, 7) : $bestOf, // Finals can be higher BO
                    'scheduled_at' => Carbon::now()->addHours($round * 2),
                    'team1_score' => 0,
                    'team2_score' => 0
                ]);
                
                // Link previous round matches to advance to this match
                if (isset($previousRoundMatches[$i * 2])) {
                    $previousRoundMatches[$i * 2]->update(['winner_advances_to' => $match->match_id]);
                }
                if (isset($previousRoundMatches[$i * 2 + 1])) {
                    $previousRoundMatches[$i * 2 + 1]->update(['winner_advances_to' => $match->match_id]);
                }
                
                $roundMatches->push($match);
            }
            
            $matches = $matches->merge($roundMatches);
            $previousRoundMatches = $roundMatches;
        }

        return $matches;
    }

    /**
     * Generate double elimination matches
     */
    private function generateDoubleEliminationMatches($stage, $teams, $bestOf)
    {
        $matches = collect();
        
        // Generate upper bracket
        $upperMatches = $this->generateSingleEliminationMatches($stage, $teams, $bestOf);
        
        // Mark as upper bracket
        foreach ($upperMatches as $match) {
            $match->update(['round_name' => 'Upper ' . $match->round_name]);
        }
        
        // Generate lower bracket structure (simplified for now)
        // In a real implementation, this would create the full lower bracket
        // with loser progression paths
        
        $matches = $matches->merge($upperMatches);
        
        return $matches;
    }

    /**
     * Generate GSL bracket matches (4 teams)
     */
    private function generateGSLMatches($stage, $teams, $bestOf)
    {
        $matches = collect();
        $teamArray = $teams->values()->toArray();
        
        if ($teams->count() != 4) {
            throw new \Exception('GSL bracket requires exactly 4 teams');
        }
        
        // Opening matches
        $match1 = BracketMatch::create([
            'match_id' => "M{$stage->id}-GSL-A",
            'tournament_id' => $stage->tournament_id,
            'bracket_stage_id' => $stage->id,
            'round_name' => 'Opening Match A',
            'round_number' => 1,
            'match_number' => 1,
            'team1_id' => $teamArray[0]['id'],
            'team2_id' => $teamArray[3]['id'],
            'status' => 'pending',
            'best_of' => $bestOf,
            'scheduled_at' => Carbon::now()->addHours(1),
            'team1_score' => 0,
            'team2_score' => 0
        ]);
        
        $match2 = BracketMatch::create([
            'match_id' => "M{$stage->id}-GSL-B",
            'tournament_id' => $stage->tournament_id,
            'bracket_stage_id' => $stage->id,
            'round_name' => 'Opening Match B',
            'round_number' => 1,
            'match_number' => 2,
            'team1_id' => $teamArray[1]['id'],
            'team2_id' => $teamArray[2]['id'],
            'status' => 'pending',
            'best_of' => $bestOf,
            'scheduled_at' => Carbon::now()->addHours(1)->addMinutes(30),
            'team1_score' => 0,
            'team2_score' => 0
        ]);
        
        // Winners match
        $winnersMatch = BracketMatch::create([
            'match_id' => "M{$stage->id}-GSL-W",
            'tournament_id' => $stage->tournament_id,
            'bracket_stage_id' => $stage->id,
            'round_name' => 'Winners Match',
            'round_number' => 2,
            'match_number' => 3,
            'team1_id' => null,
            'team2_id' => null,
            'team1_source' => 'Winner of Opening A',
            'team2_source' => 'Winner of Opening B',
            'status' => 'pending',
            'best_of' => $bestOf,
            'scheduled_at' => Carbon::now()->addHours(3),
            'team1_score' => 0,
            'team2_score' => 0
        ]);
        
        // Losers match
        $losersMatch = BracketMatch::create([
            'match_id' => "M{$stage->id}-GSL-L",
            'tournament_id' => $stage->tournament_id,
            'bracket_stage_id' => $stage->id,
            'round_name' => 'Elimination Match',
            'round_number' => 2,
            'match_number' => 4,
            'team1_id' => null,
            'team2_id' => null,
            'team1_source' => 'Loser of Opening A',
            'team2_source' => 'Loser of Opening B',
            'status' => 'pending',
            'best_of' => $bestOf,
            'scheduled_at' => Carbon::now()->addHours(3)->addMinutes(30),
            'team1_score' => 0,
            'team2_score' => 0
        ]);
        
        // Decider match
        $deciderMatch = BracketMatch::create([
            'match_id' => "M{$stage->id}-GSL-D",
            'tournament_id' => $stage->tournament_id,
            'bracket_stage_id' => $stage->id,
            'round_name' => 'Decider Match',
            'round_number' => 3,
            'match_number' => 5,
            'team1_id' => null,
            'team2_id' => null,
            'team1_source' => 'Loser of Winners Match',
            'team2_source' => 'Winner of Elimination Match',
            'status' => 'pending',
            'best_of' => $bestOf,
            'scheduled_at' => Carbon::now()->addHours(5),
            'team1_score' => 0,
            'team2_score' => 0
        ]);
        
        // Set advancement paths
        $match1->update(['winner_advances_to' => $winnersMatch->match_id, 'loser_advances_to' => $losersMatch->match_id]);
        $match2->update(['winner_advances_to' => $winnersMatch->match_id, 'loser_advances_to' => $losersMatch->match_id]);
        $winnersMatch->update(['loser_advances_to' => $deciderMatch->match_id]);
        $losersMatch->update(['winner_advances_to' => $deciderMatch->match_id]);
        
        $matches->push($match1, $match2, $winnersMatch, $losersMatch, $deciderMatch);
        
        return $matches;
    }

    /**
     * Generate round robin matches
     */
    private function generateRoundRobinMatches($stage, $teams, $bestOf)
    {
        $matches = collect();
        $teamArray = $teams->values()->toArray();
        $matchNumber = 1;
        
        // Generate all matchups
        for ($i = 0; $i < $teams->count(); $i++) {
            for ($j = $i + 1; $j < $teams->count(); $j++) {
                $match = BracketMatch::create([
                    'match_id' => "M{$stage->id}-RR-{$matchNumber}",
                    'tournament_id' => $stage->tournament_id,
                    'bracket_stage_id' => $stage->id,
                    'round_name' => 'Round Robin',
                    'round_number' => 1,
                    'match_number' => $matchNumber++,
                    'team1_id' => $teamArray[$i]['id'],
                    'team2_id' => $teamArray[$j]['id'],
                    'status' => 'pending',
                    'best_of' => $bestOf,
                    'scheduled_at' => Carbon::now()->addHours($matchNumber),
                    'team1_score' => 0,
                    'team2_score' => 0
                ]);
                
                $matches->push($match);
            }
        }
        
        return $matches;
    }

    /**
     * Update match score and progress bracket
     */
    public function updateMatchScore(Request $request, $matchId)
    {
        $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'game_details' => 'array',
            'game_details.*.mode' => 'string|in:domination,convoy,convergence',
            'game_details.*.winner_id' => 'integer',
            'complete_match' => 'boolean'
        ]);

        DB::beginTransaction();
        
        try {
            $match = BracketMatch::findOrFail($matchId);
            
            // Update scores
            $match->team1_score = $request->team1_score;
            $match->team2_score = $request->team2_score;
            
            // Check if match should be completed
            $requiredWins = ceil($match->best_of / 2);
            $shouldComplete = $request->complete_match || 
                             $match->team1_score >= $requiredWins || 
                             $match->team2_score >= $requiredWins;
            
            if ($shouldComplete) {
                // Determine winner
                $winnerId = $match->team1_score > $match->team2_score ? 
                           $match->team1_id : $match->team2_id;
                $loserId = $winnerId == $match->team1_id ? 
                          $match->team2_id : $match->team1_id;
                
                $match->winner_id = $winnerId;
                $match->loser_id = $loserId;
                $match->status = 'completed';
                $match->completed_at = now();
                
                // Advance teams to next matches
                $this->advanceTeams($match);
            }
            
            $match->save();
            
            // Store game details if provided
            if ($request->has('game_details')) {
                $match->update(['game_details' => $request->game_details]);
            }
            
            DB::commit();
            
            // Get updated bracket state
            $bracket = $this->getBracketState($match->bracket_stage_id);
            
            return response()->json([
                'success' => true,
                'message' => $shouldComplete ? 'Match completed and teams advanced' : 'Score updated',
                'match' => $match->fresh(['team1', 'team2', 'winner', 'loser']),
                'bracket' => $bracket
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Advance teams to next matches based on bracket progression
     */
    private function advanceTeams($match)
    {
        // Advance winner if there's a next match
        if ($match->winner_advances_to) {
            $nextMatch = BracketMatch::where('match_id', $match->winner_advances_to)->first();
            if ($nextMatch) {
                if (!$nextMatch->team1_id) {
                    $nextMatch->team1_id = $match->winner_id;
                    $nextMatch->team1_source = "Winner of {$match->round_name} Match {$match->match_number}";
                } elseif (!$nextMatch->team2_id) {
                    $nextMatch->team2_id = $match->winner_id;
                    $nextMatch->team2_source = "Winner of {$match->round_name} Match {$match->match_number}";
                }
                $nextMatch->save();
            }
        }
        
        // Advance loser if there's a loser bracket (double elimination)
        if ($match->loser_advances_to) {
            $loserMatch = BracketMatch::where('match_id', $match->loser_advances_to)->first();
            if ($loserMatch) {
                if (!$loserMatch->team1_id) {
                    $loserMatch->team1_id = $match->loser_id;
                    $loserMatch->team1_source = "Loser of {$match->round_name} Match {$match->match_number}";
                } elseif (!$loserMatch->team2_id) {
                    $loserMatch->team2_id = $match->loser_id;
                    $loserMatch->team2_source = "Loser of {$match->round_name} Match {$match->match_number}";
                }
                $loserMatch->save();
            }
        }
    }

    /**
     * Get current bracket state
     */
    public function getBracketState($stageId)
    {
        $stage = BracketStage::with(['seedings.team'])->findOrFail($stageId);
        
        $matches = BracketMatch::where('bracket_stage_id', $stageId)
                               ->with(['team1', 'team2', 'winner', 'loser'])
                               ->orderBy('round_number')
                               ->orderBy('match_number')
                               ->get();
        
        // Group matches by round
        $rounds = [];
        foreach ($matches as $match) {
            $rounds[$match->round_number][] = $match;
        }
        
        return [
            'stage' => $stage,
            'rounds' => $rounds,
            'matches' => $matches,
            'completed_matches' => $matches->where('status', 'completed')->count(),
            'total_matches' => $matches->count(),
            'champion' => $this->determineChampion($matches)
        ];
    }

    /**
     * Determine champion if bracket is complete
     */
    private function determineChampion($matches)
    {
        $finalRound = $matches->max('round_number');
        $finalMatches = $matches->where('round_number', $finalRound);
        
        if ($finalMatches->count() == 1 && $finalMatches->first()->status == 'completed') {
            return Team::find($finalMatches->first()->winner_id);
        }
        
        return null;
    }

    /**
     * Get round name based on round number and total rounds
     */
    private function getRoundName($round, $totalRounds)
    {
        $roundsFromEnd = $totalRounds - $round;
        
        return match($roundsFromEnd) {
            0 => 'Grand Final',
            1 => 'Semifinals',
            2 => 'Quarterfinals',
            3 => 'Round of 16',
            4 => 'Round of 32',
            5 => 'Round of 64',
            default => "Round {$round}"
        };
    }

    /**
     * Get bracket view data for frontend
     */
    public function getBracket($stageId)
    {
        $bracket = $this->getBracketState($stageId);
        
        return response()->json([
            'success' => true,
            'bracket' => $bracket,
            'formats' => self::MARVEL_RIVALS_FORMATS,
            'game_modes' => self::GAME_MODES
        ]);
    }

    /**
     * Reset a bracket (admin only)
     */
    public function resetBracket($stageId)
    {
        DB::beginTransaction();
        
        try {
            // Delete all matches for this stage
            BracketMatch::where('bracket_stage_id', $stageId)->delete();
            
            // Reset stage
            $stage = BracketStage::findOrFail($stageId);
            $stage->update(['status' => 'pending']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Bracket reset successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset bracket: ' . $e->getMessage()
            ], 500);
        }
    }
}