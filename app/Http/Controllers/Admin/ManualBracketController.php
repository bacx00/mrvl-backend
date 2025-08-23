<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class ManualBracketController extends Controller
{
    /**
     * Create a manual bracket stage for an event
     */
    public function createBracketStage(Request $request, $eventId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'stage_type' => 'required|in:single_elimination,double_elimination,round_robin,swiss,groups',
                'round_count' => 'required|integer|min:1|max:10',
                'team_count' => 'required|integer|min:2|max:256',
                'settings' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $stage = BracketStage::create([
                'event_id' => $eventId,
                'name' => $request->name,
                'stage_type' => $request->stage_type,
                'round_count' => $request->round_count,
                'team_count' => $request->team_count,
                'settings' => $request->settings ?? []
            ]);

            DB::commit();

            Log::info('Manual bracket stage created', [
                'event_id' => $eventId,
                'stage_id' => $stage->id,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bracket stage created successfully',
                'data' => $stage
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating bracket stage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bracket stage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a manual match in a bracket
     */
    public function createMatch(Request $request, $eventId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);
            
            $validator = Validator::make($request->all(), [
                'stage_id' => 'nullable|exists:bracket_stages,id',
                'round_number' => 'required|integer|min:1|max:10',
                'match_number' => 'required|integer|min:1',
                'team1_id' => 'nullable|exists:teams,id',
                'team2_id' => 'nullable|exists:teams,id',
                'best_of' => 'required|in:1,3,5,7,9',
                'status' => 'nullable|in:pending,live,completed',
                'scheduled_at' => 'nullable|date',
                'position' => 'nullable|integer',
                'next_match_id' => 'nullable|exists:bracket_matches,id',
                'previous_match1_id' => 'nullable|exists:bracket_matches,id',
                'previous_match2_id' => 'nullable|exists:bracket_matches,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $matchData = [
                'event_id' => $eventId,
                'bracket_stage_id' => $request->stage_id,
                'round_number' => $request->round_number,
                'match_number' => $request->match_number,
                'team1_id' => $request->team1_id,
                'team2_id' => $request->team2_id,
                'best_of' => $request->best_of,
                'status' => $request->status ?? 'pending',
                'scheduled_at' => $request->scheduled_at,
                'position' => $request->position,
                'next_match_id' => $request->next_match_id,
                'previous_match1_id' => $request->previous_match1_id,
                'previous_match2_id' => $request->previous_match2_id,
                'team1_score' => 0,
                'team2_score' => 0,
                'match_id' => 'E' . $eventId . '_R' . $request->round_number . '_M' . $request->match_number,
                'tournament_id' => null
            ];

            $match = BracketMatch::create($matchData);

            DB::commit();

            // Clear cache
            Cache::forget('event_bracket_' . $eventId);

            Log::info('Manual match created', [
                'event_id' => $eventId,
                'match_id' => $match->id,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Match created successfully',
                'data' => $match->load(['team1', 'team2'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating match: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a match manually
     */
    public function updateMatch(Request $request, $eventId, $matchId): JsonResponse
    {
        try {
            $match = BracketMatch::where('event_id', $eventId)
                                 ->findOrFail($matchId);
            
            $validator = Validator::make($request->all(), [
                'team1_id' => 'nullable|exists:teams,id',
                'team2_id' => 'nullable|exists:teams,id',
                'team1_score' => 'nullable|integer|min:0',
                'team2_score' => 'nullable|integer|min:0',
                'winner_id' => 'nullable|exists:teams,id',
                'best_of' => 'nullable|in:1,3,5,7,9',
                'status' => 'nullable|in:pending,live,completed',
                'scheduled_at' => 'nullable|date',
                'match_details' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update match
            $match->update($request->only([
                'team1_id', 'team2_id', 'team1_score', 'team2_score',
                'winner_id', 'best_of', 'status', 'scheduled_at', 'match_details'
            ]));

            // If match is completed and has a next match, advance winner
            if ($request->status === 'completed' && $request->winner_id && $match->next_match_id) {
                $this->advanceWinner($match, $request->winner_id);
            }

            DB::commit();

            // Clear cache
            Cache::forget('event_bracket_' . $eventId);

            Log::info('Match updated manually', [
                'event_id' => $eventId,
                'match_id' => $matchId,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully',
                'data' => $match->fresh()->load(['team1', 'team2', 'winner'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating match: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set match scores manually
     */
    public function setMatchScores(Request $request, $eventId, $matchId): JsonResponse
    {
        try {
            $match = BracketMatch::where('event_id', $eventId)
                                 ->findOrFail($matchId);
            
            $validator = Validator::make($request->all(), [
                'team1_score' => 'required|integer|min:0',
                'team2_score' => 'required|integer|min:0',
                'game_scores' => 'nullable|array',
                'complete_match' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $match->team1_score = $request->team1_score;
            $match->team2_score = $request->team2_score;

            // Store game scores if provided
            if ($request->has('game_scores')) {
                $details = $match->match_details ?? [];
                $details['game_scores'] = $request->game_scores;
                $match->match_details = $details;
            }

            // Determine winner if completing match
            if ($request->boolean('complete_match')) {
                if ($match->team1_score > $match->team2_score) {
                    $match->winner_id = $match->team1_id;
                } elseif ($match->team2_score > $match->team1_score) {
                    $match->winner_id = $match->team2_id;
                }
                $match->status = 'completed';

                // Advance winner to next match if applicable
                if ($match->winner_id && $match->next_match_id) {
                    $this->advanceWinner($match, $match->winner_id);
                }
            }

            $match->save();

            DB::commit();

            // Clear cache
            Cache::forget('event_bracket_' . $eventId);

            Log::info('Match scores set manually', [
                'event_id' => $eventId,
                'match_id' => $matchId,
                'scores' => [$request->team1_score, $request->team2_score],
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Match scores updated successfully',
                'data' => $match->fresh()->load(['team1', 'team2', 'winner'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error setting match scores: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set match scores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a match
     */
    public function deleteMatch($eventId, $matchId): JsonResponse
    {
        try {
            $match = BracketMatch::where('event_id', $eventId)
                                 ->findOrFail($matchId);

            DB::beginTransaction();

            // Reset next matches that depend on this match
            BracketMatch::where('previous_match1_id', $matchId)
                       ->orWhere('previous_match2_id', $matchId)
                       ->update([
                           'team1_id' => null,
                           'team2_id' => null
                       ]);

            $match->delete();

            DB::commit();

            // Clear cache
            Cache::forget('event_bracket_' . $eventId);

            Log::info('Match deleted', [
                'event_id' => $eventId,
                'match_id' => $matchId,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Match deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting match: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all matches for an event
     */
    public function getMatches($eventId): JsonResponse
    {
        try {
            $matches = BracketMatch::where('event_id', $eventId)
                                  ->with(['team1', 'team2', 'winner', 'stage'])
                                  ->orderBy('round_number')
                                  ->orderBy('match_number')
                                  ->get();

            return response()->json([
                'success' => true,
                'data' => $matches
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching matches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create matches
     */
    public function bulkCreateMatches(Request $request, $eventId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);
            
            $validator = Validator::make($request->all(), [
                'matches' => 'required|array',
                'matches.*.round_number' => 'required|integer|min:1',
                'matches.*.match_number' => 'required|integer|min:1',
                'matches.*.team1_id' => 'nullable|exists:teams,id',
                'matches.*.team2_id' => 'nullable|exists:teams,id',
                'matches.*.best_of' => 'required|in:1,3,5,7,9',
                'matches.*.scheduled_at' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $createdMatches = [];
            foreach ($request->matches as $index => $matchData) {
                $matchData['event_id'] = $eventId;
                $matchData['status'] = $matchData['status'] ?? 'pending';
                $matchData['team1_score'] = $matchData['team1_score'] ?? 0;
                $matchData['team2_score'] = $matchData['team2_score'] ?? 0;
                $matchData['bracket_stage_id'] = $matchData['stage_id'] ?? null;
                $matchData['match_id'] = $matchData['match_id'] ?? 'E' . $eventId . '_R' . ($matchData['round_number'] ?? 1) . '_M' . ($matchData['match_number'] ?? ($index + 1));
                $matchData['tournament_id'] = null;
                
                // Remove stage_id if it exists (we use bracket_stage_id)
                unset($matchData['stage_id']);
                
                $match = BracketMatch::create($matchData);
                $createdMatches[] = $match;
            }

            DB::commit();

            // Clear cache
            Cache::forget('event_bracket_' . $eventId);

            Log::info('Bulk matches created', [
                'event_id' => $eventId,
                'count' => count($createdMatches),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => count($createdMatches) . ' matches created successfully',
                'data' => $createdMatches
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating bulk matches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset bracket for an event
     */
    public function resetBracket($eventId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete all matches and stages
            BracketMatch::where('event_id', $eventId)->delete();
            BracketStage::where('event_id', $eventId)->delete();

            DB::commit();

            // Clear cache
            Cache::forget('event_bracket_' . $eventId);

            Log::info('Bracket reset', [
                'event_id' => $eventId,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bracket reset successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error resetting bracket: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Advance winner to next match
     */
    private function advanceWinner($match, $winnerId)
    {
        $nextMatch = BracketMatch::find($match->next_match_id);
        if (!$nextMatch) return;

        // Determine which slot in next match
        if ($nextMatch->previous_match1_id == $match->id) {
            $nextMatch->team1_id = $winnerId;
        } elseif ($nextMatch->previous_match2_id == $match->id) {
            $nextMatch->team2_id = $winnerId;
        } else {
            // Position-based advancement
            if ($match->position % 2 == 1) {
                $nextMatch->team1_id = $winnerId;
            } else {
                $nextMatch->team2_id = $winnerId;
            }
        }

        $nextMatch->save();
    }
}