<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Team;
use App\Models\MatchModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SimpleBracketController extends Controller
{
    /**
     * Get bracket for an event
     */
    public function show($eventId)
    {
        try {
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Get all matches for this event with teams
            $matches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.event_id', $eventId)
                ->select([
                    'm.*',
                    't1.id as team1_id',
                    't1.name as team1_name',
                    't1.short_name as team1_short',
                    't1.logo as team1_logo',
                    't2.id as team2_id', 
                    't2.name as team2_name',
                    't2.short_name as team2_short',
                    't2.logo as team2_logo'
                ])
                ->orderBy('m.round')
                ->orderBy('m.bracket_position')
                ->get();

            // Build bracket structure
            $bracket = $this->buildBracketStructure($matches, $event->format ?? 'single_elimination');

            return response()->json([
                'success' => true,
                'data' => [
                    'event_id' => $eventId,
                    'event_name' => $event->name,
                    'format' => $event->format ?? 'single_elimination',
                    'bracket' => $bracket,
                    'teams_count' => $this->getEventTeamCount($eventId),
                    'status' => $event->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bracket fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bracket'
            ], 500);
        }
    }

    /**
     * Generate bracket for an event
     */
    public function generate(Request $request, $eventId)
    {
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !$user->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin or moderator access required.'
            ], 403);
        }
        
        $request->validate([
            'format' => 'sometimes|in:single_elimination,double_elimination,swiss,round_robin',
            'seeding_type' => 'sometimes|in:random,rating,manual',
            'match_format' => 'sometimes|in:bo1,bo3,bo5,bo7',
            'finals_format' => 'sometimes|in:bo3,bo5,bo7'
        ]);

        try {
            DB::beginTransaction();

            $event = Event::find($eventId);
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }

            // Get participating teams
            $teams = $this->getEventTeams($eventId);
            $teamCount = count($teams);
            
            if ($teamCount < 2) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Need at least 2 teams to generate bracket'
                ], 400);
            }
            
            // Validate team count for bracket format
            if ($teamCount > 64) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum 64 teams supported for bracket generation'
                ], 400);
            }

            // Clear existing matches
            DB::table('matches')->where('event_id', $eventId)->delete();

            // Apply seeding
            $seedingType = $request->seeding_type ?? 'rating';
            $seededTeams = $this->seedTeams($teams, $seedingType);

            // Generate matches based on format
            $format = $request->format ?? $event->format ?? 'single_elimination';
            $matchFormat = $request->match_format ?? 'bo3';
            $finalsFormat = $request->finals_format ?? 'bo5';
            $matches = $this->createMatches($eventId, $seededTeams, $format, $matchFormat, $finalsFormat);

            // Insert matches
            DB::table('matches')->insert($matches);

            // Update event
            DB::table('events')->where('id', $eventId)->update([
                'status' => 'ongoing',
                'format' => $format,
                'current_round' => 1,
                'updated_at' => now()
            ]);

            DB::commit();

            // Return the generated bracket
            $generatedMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.event_id', $eventId)
                ->select([
                    'm.*',
                    't1.name as team1_name',
                    't1.short_name as team1_short',
                    't1.logo as team1_logo',
                    't2.name as team2_name',
                    't2.short_name as team2_short',
                    't2.logo as team2_logo'
                ])
                ->orderBy('m.round')
                ->orderBy('m.bracket_position')
                ->get();

            $bracket = $this->buildBracketStructure($generatedMatches, $format);

            return response()->json([
                'success' => true,
                'message' => 'Bracket generated successfully',
                'data' => [
                    'bracket' => $bracket,
                    'matches_created' => count($matches),
                    'format' => $format
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bracket generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update match result
     */
    public function updateMatch(Request $request, $matchId)
    {
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !$user->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin or moderator access required.'
            ], 403);
        }
        
        $request->validate([
            'team1_score' => 'required|integer|min:0|max:99',
            'team2_score' => 'required|integer|min:0|max:99',
            'status' => 'required|in:upcoming,live,completed'
        ]);

        try {
            DB::beginTransaction();

            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            // Update match
            DB::table('matches')->where('id', $matchId)->update([
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'status' => $request->status,
                'completed_at' => $request->status === 'completed' ? now() : null,
                'updated_at' => now()
            ]);

            // If completed, advance winner
            if ($request->status === 'completed' && $request->team1_score !== $request->team2_score) {
                $winnerId = $request->team1_score > $request->team2_score ? $match->team1_id : $match->team2_id;
                $this->advanceWinner($match, $winnerId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Match update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating match'
            ], 500);
        }
    }

    /**
     * Delete/reset bracket
     */
    public function delete($eventId)
    {
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !$user->hasAnyRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin or moderator access required.'
            ], 403);
        }
        
        try {
            DB::beginTransaction();

            // Delete all matches
            DB::table('matches')->where('event_id', $eventId)->delete();
            
            // Reset event status
            DB::table('events')->where('id', $eventId)->update([
                'status' => 'upcoming',
                'current_round' => 0,
                'updated_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bracket deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting bracket'
            ], 500);
        }
    }

    /**
     * Build bracket structure from matches
     */
    private function buildBracketStructure($matches, $format)
    {
        $rounds = [];
        
        foreach ($matches as $match) {
            $round = $match->round;
            
            if (!isset($rounds[$round])) {
                $rounds[$round] = [
                    'round' => $round,
                    'name' => $this->getRoundName($round, count($matches)),
                    'matches' => []
                ];
            }
            
            $rounds[$round]['matches'][] = [
                'id' => $match->id,
                'position' => $match->bracket_position,
                'team1' => $match->team1_id ? [
                    'id' => $match->team1_id,
                    'name' => $match->team1_name,
                    'short_name' => $match->team1_short,
                    'logo' => $match->team1_logo,
                    'score' => $match->team1_score
                ] : null,
                'team2' => $match->team2_id ? [
                    'id' => $match->team2_id,
                    'name' => $match->team2_name,
                    'short_name' => $match->team2_short,
                    'logo' => $match->team2_logo,
                    'score' => $match->team2_score
                ] : null,
                'status' => $match->status,
                'scheduled_at' => $match->scheduled_at,
                'winner_id' => $this->getWinnerId($match)
            ];
        }
        
        // Sort rounds and convert to array
        ksort($rounds);
        return array_values($rounds);
    }

    /**
     * Get event teams
     */
    private function getEventTeams($eventId)
    {
        return DB::table('event_teams as et')
            ->join('teams as t', 'et.team_id', '=', 't.id')
            ->where('et.event_id', $eventId)
            ->select(['t.id', 't.name', 't.short_name', 't.logo', 't.rating', 'et.seed'])
            ->orderBy('et.seed')
            ->get()
            ->all(); // Keep as objects, don't convert to array
    }

    /**
     * Seed teams based on method
     */
    private function seedTeams($teams, $method)
    {
        // Ensure we have objects, not arrays
        if (!empty($teams) && is_array($teams[0])) {
            $teams = collect($teams)->map(function($team) {
                return (object) $team;
            })->all();
        }
        
        switch ($method) {
            case 'rating':
                usort($teams, function($a, $b) {
                    $ratingA = $a->rating ?? 1000;
                    $ratingB = $b->rating ?? 1000;
                    return $ratingB <=> $ratingA;
                });
                break;
            case 'random':
                shuffle($teams);
                break;
            case 'manual':
                // Keep existing seed order
                break;
        }
        
        // Apply proper tournament seeding pattern
        return $this->applyTournamentSeeding($teams);
    }

    /**
     * Create matches for bracket
     */
    private function createMatches($eventId, $teams, $format, $matchFormat = 'bo3', $finalsFormat = 'bo5')
    {
        $matches = [];
        $teamCount = count($teams);
        
        if ($teamCount < 2) {
            return $matches;
        }
        
        $rounds = ceil(log($teamCount, 2));
        $bracketSize = pow(2, $rounds);
        
        // Calculate first round matches and byes
        $firstRoundMatches = floor($teamCount / 2);
        $byeTeams = $teamCount % 2; // Teams that get byes (should be 0 or 1)
        
        // For perfect bracket alignment, we need to handle byes differently
        $teamsInFirstRound = $teamCount - ($bracketSize - $teamCount);
        $firstRoundPairs = floor($teamsInFirstRound / 2);
        
        $position = 1;
        $teamIndex = 0;
        
        // Create first round matches
        for ($i = 0; $i < $firstRoundPairs; $i++) {
            if (isset($teams[$teamIndex]) && isset($teams[$teamIndex + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => 1,
                    'bracket_position' => $position,
                    'bracket_type' => 'main',
                    'team1_id' => $teams[$teamIndex]->id,
                    'team2_id' => $teams[$teamIndex + 1]->id,
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'upcoming',
                    'format' => $matchFormat,
                    'scheduled_at' => now()->addMinutes(30 + ($position * 10)),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $teamIndex += 2;
                $position++;
            }
        }
        
        // Handle remaining teams that get byes (advance to next round without playing)
        // In a proper bracket, bye teams don't need explicit matches
        
        // Calculate teams advancing to each subsequent round
        $teamsInRound = $firstRoundPairs + ($teamCount - ($firstRoundPairs * 2)); // Winners + bye teams
        
        // Create subsequent round placeholder matches
        for ($r = 2; $r <= $rounds; $r++) {
            $matchesInThisRound = floor($teamsInRound / 2);
            
            for ($m = 1; $m <= $matchesInThisRound; $m++) {
                $minutesDelay = 30 + (($r - 1) * 60) + ($m * 15);
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'main',
                    'team1_id' => null,
                    'team2_id' => null,
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'status' => 'upcoming',
                    'format' => $r === $rounds ? $finalsFormat : $matchFormat,
                    'scheduled_at' => now()->addMinutes($minutesDelay),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            $teamsInRound = $matchesInThisRound;
        }
        
        return $matches;
    }

    /**
     * Advance winner to next match
     */
    private function advanceWinner($match, $winnerId)
    {
        $nextRound = $match->round + 1;
        $nextPosition = ceil($match->bracket_position / 2);
        
        $nextMatch = DB::table('matches')
            ->where('event_id', $match->event_id)
            ->where('round', $nextRound)
            ->where('bracket_position', $nextPosition)
            ->first();
            
        if ($nextMatch) {
            // Determine if winner goes to team1 or team2 slot
            $teamSlot = ($match->bracket_position % 2 === 1) ? 'team1_id' : 'team2_id';
            
            $updateData = [
                $teamSlot => $winnerId,
                'updated_at' => now()
            ];
            
            // If both teams are now assigned, mark as upcoming
            if (($teamSlot === 'team1_id' && $nextMatch->team2_id) || 
                ($teamSlot === 'team2_id' && $nextMatch->team1_id)) {
                $updateData['status'] = 'upcoming';
            }
            
            DB::table('matches')
                ->where('id', $nextMatch->id)
                ->update($updateData);
                
            // Log the advancement for debugging
            Log::info("Winner advanced", [
                'from_match' => $match->id,
                'to_match' => $nextMatch->id,
                'winner_id' => $winnerId,
                'slot' => $teamSlot
            ]);
        } else {
            // This might be the final match - update event winner
            $event = DB::table('events')->where('id', $match->event_id)->first();
            if ($event) {
                $totalRounds = ceil(log($this->getEventTeamCount($match->event_id), 2));
                if ($match->round >= $totalRounds) {
                    DB::table('events')
                        ->where('id', $match->event_id)
                        ->update([
                            'winner_id' => $winnerId,
                            'status' => 'completed',
                            'updated_at' => now()
                        ]);
                }
            }
        }
    }

    /**
     * Get round name
     */
    private function getRoundName($round, $totalMatches)
    {
        // Handle non-numeric rounds
        if (!is_numeric($round)) {
            return ucwords(str_replace('_', ' ', $round));
        }
        
        $roundNum = (int) $round;
        $totalRounds = ceil(log($totalMatches * 2, 2));
        $roundsFromEnd = $totalRounds - $roundNum + 1;
        
        switch ($roundsFromEnd) {
            case 1:
                return 'Grand Final';
            case 2:
                return 'Semi-Finals';
            case 3:
                return 'Quarter-Finals';
            case 4:
                return 'Round of 16';
            default:
                return "Round $roundNum";
        }
    }

    /**
     * Get winner ID from match
     */
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

    /**
     * Get event team count
     */
    private function getEventTeamCount($eventId)
    {
        return DB::table('event_teams')->where('event_id', $eventId)->count();
    }
    
    /**
     * Apply proper tournament seeding pattern (1v8, 2v7, 3v6, 4v5, etc.)
     */
    private function applyTournamentSeeding($teams)
    {
        $teamCount = count($teams);
        if ($teamCount < 2) {
            return $teams;
        }
        
        // For proper tournament seeding, we need a power of 2
        $bracketSize = pow(2, ceil(log($teamCount, 2)));
        
        // Create seeded bracket positions
        $seededPositions = $this->generateSeedingOrder($bracketSize);
        
        // Apply seedings
        $seededTeams = [];
        for ($i = 0; $i < $teamCount; $i++) {
            $position = $seededPositions[$i] ?? ($i + 1);
            $seededTeams[$position - 1] = $teams[$i];
        }
        
        // Fill empty positions with nulls and then filter
        ksort($seededTeams);
        return array_values(array_filter($seededTeams));
    }
    
    /**
     * Generate proper seeding order for tournament brackets
     * This ensures 1v8, 2v7, 3v6, 4v5 pattern in quarterfinals
     */
    private function generateSeedingOrder($bracketSize)
    {
        if ($bracketSize <= 2) {
            return [1, 2];
        }
        
        // Recursive seeding pattern
        $previousRound = $this->generateSeedingOrder($bracketSize / 2);
        $currentRound = [];
        
        foreach ($previousRound as $seed) {
            $currentRound[] = $seed;
            $currentRound[] = $bracketSize + 1 - $seed;
        }
        
        return $currentRound;
    }
}