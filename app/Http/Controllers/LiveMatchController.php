<?php

namespace App\Http\Controllers;

use App\Models\MvrlMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiveMatchController extends Controller
{
    /**
     * Start a match (transition from upcoming to live)
     */
    public function startMatch(Request $request, $matchId)
    {
        $this->authorize('manage-matches');
        
        try {
            $match = MvrlMatch::find($matchId);
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }
            
            if ($match->status !== 'upcoming') {
                return response()->json(['success' => false, 'message' => 'Match is not in upcoming status'], 400);
            }
            
            // Update match status
            $match->status = 'live';
            $match->actual_start_time = now();
            
            // Update first map to live
            $mapsData = $match->maps_data;
            if (!empty($mapsData[0])) {
                $mapsData[0]['status'] = 'live';
                $mapsData[0]['started_at'] = now();
                $match->maps_data = $mapsData;
            }
            
            $match->current_map_number = 1;
            $match->save();
            
            // Log to timeline
            $this->logToTimeline($matchId, 'MATCH_START', [
                'status' => 'live',
                'currentMap' => 1
            ]);
            
            // Broadcast event
            $this->broadcastUpdate($matchId, 'MATCH_START', [
                'matchId' => $matchId,
                'status' => 'live',
                'currentMap' => 1,
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Match started successfully',
                'data' => [
                    'status' => 'live',
                    'currentMap' => 1
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting match: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Pause a live match
     */
    public function pauseMatch(Request $request, $matchId)
    {
        $this->authorize('manage-matches');
        
        try {
            $match = MvrlMatch::find($matchId);
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }
            
            if ($match->status !== 'live') {
                return response()->json(['success' => false, 'message' => 'Match is not live'], 400);
            }
            
            $match->status = 'paused';
            $match->save();
            
            $this->broadcastUpdate($matchId, 'MATCH_PAUSE', [
                'matchId' => $matchId,
                'status' => 'paused',
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Match paused successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error pausing match: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Resume a paused match
     */
    public function resumeMatch(Request $request, $matchId)
    {
        $this->authorize('manage-matches');
        
        try {
            $match = MvrlMatch::find($matchId);
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }
            
            if ($match->status !== 'paused') {
                return response()->json(['success' => false, 'message' => 'Match is not paused'], 400);
            }
            
            $match->status = 'live';
            $match->save();
            
            $this->broadcastUpdate($matchId, 'MATCH_RESUME', [
                'matchId' => $matchId,
                'status' => 'live',
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Match resumed successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resuming match: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update live score
     */
    public function updateScore(Request $request, $matchId)
    {
        $this->authorize('manage-matches');
        
        $request->validate([
            'map_number' => 'required|integer|min:1',
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0'
        ]);
        
        try {
            $match = MvrlMatch::find($matchId);
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }
            
            if (!in_array($match->status, ['live', 'paused'])) {
                return response()->json(['success' => false, 'message' => 'Match is not live'], 400);
            }
            
            $mapsData = $match->maps_data;
            $mapIndex = $request->map_number - 1;
            
            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Invalid map number'], 400);
            }
            
            // Update scores
            $mapsData[$mapIndex]['team1_score'] = $request->team1_score;
            $mapsData[$mapIndex]['team2_score'] = $request->team2_score;
            
            $match->maps_data = $mapsData;
            $match->save();
            
            // Log to timeline
            $this->logToTimeline($matchId, 'SCORE_UPDATE', [
                'mapNumber' => $request->map_number,
                'team1Score' => $request->team1_score,
                'team2Score' => $request->team2_score
            ]);
            
            // Broadcast update
            $this->broadcastUpdate($matchId, 'SCORE_UPDATE', [
                'matchId' => $matchId,
                'mapNumber' => $request->map_number,
                'team1Score' => $request->team1_score,
                'team2Score' => $request->team2_score,
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Score updated successfully',
                'data' => [
                    'mapNumber' => $request->map_number,
                    'team1Score' => $request->team1_score,
                    'team2Score' => $request->team2_score
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating score: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Complete a map
     */
    public function completeMap(Request $request, $matchId)
    {
        $this->authorize('manage-matches');
        
        $request->validate([
            'map_number' => 'required|integer|min:1',
            'winner_id' => 'required|exists:teams,id'
        ]);
        
        try {
            $match = MvrlMatch::find($matchId);
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }
            
            $mapsData = $match->maps_data;
            $mapIndex = $request->map_number - 1;
            
            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Invalid map number'], 400);
            }
            
            // Complete current map
            $mapsData[$mapIndex]['status'] = 'completed';
            $mapsData[$mapIndex]['winner_id'] = $request->winner_id;
            $mapsData[$mapIndex]['completed_at'] = now();
            
            // Calculate duration
            if (isset($mapsData[$mapIndex]['started_at'])) {
                $started = new \DateTime($mapsData[$mapIndex]['started_at']);
                $completed = new \DateTime();
                $duration = $completed->diff($started);
                $mapsData[$mapIndex]['duration'] = $duration->format('%H:%I:%S');
            }
            
            // Update series score
            $team1Wins = 0;
            $team2Wins = 0;
            
            foreach ($mapsData as $map) {
                if ($map['status'] === 'completed' && isset($map['winner_id'])) {
                    if ($map['winner_id'] == $match->team1_id) {
                        $team1Wins++;
                    } elseif ($map['winner_id'] == $match->team2_id) {
                        $team2Wins++;
                    }
                }
            }
            
            $match->series_score_team1 = $team1Wins;
            $match->series_score_team2 = $team2Wins;
            
            // Check if match is complete
            $format = $match->format;
            $winsNeeded = $format === 'BO5' ? 3 : 2;
            
            $matchComplete = ($team1Wins >= $winsNeeded || $team2Wins >= $winsNeeded);
            
            if ($matchComplete) {
                // Match is complete
                $match->status = 'completed';
                $match->winner_id = $team1Wins > $team2Wins ? $match->team1_id : $match->team2_id;
                $match->team1_score = $team1Wins;
                $match->team2_score = $team2Wins;
            } else {
                // Move to next map
                $nextMapIndex = $mapIndex + 1;
                if (isset($mapsData[$nextMapIndex])) {
                    $mapsData[$nextMapIndex]['status'] = 'live';
                    $mapsData[$nextMapIndex]['started_at'] = now();
                    $match->current_map_number = $nextMapIndex + 1;
                }
            }
            
            $match->maps_data = $mapsData;
            $match->save();
            
            // Log to timeline
            $this->logToTimeline($matchId, 'MAP_COMPLETE', [
                'mapNumber' => $request->map_number,
                'winnerId' => $request->winner_id,
                'seriesScore' => ['team1' => $team1Wins, 'team2' => $team2Wins],
                'matchComplete' => $matchComplete
            ]);
            
            // Broadcast update
            $this->broadcastUpdate($matchId, 'MAP_COMPLETE', [
                'matchId' => $matchId,
                'mapNumber' => $request->map_number,
                'winnerId' => $request->winner_id,
                'seriesScore' => ['team1' => $team1Wins, 'team2' => $team2Wins],
                'matchComplete' => $matchComplete,
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => $matchComplete ? 'Match completed!' : 'Map completed successfully',
                'data' => [
                    'mapNumber' => $request->map_number,
                    'winnerId' => $request->winner_id,
                    'seriesScore' => ['team1' => $team1Wins, 'team2' => $team2Wins],
                    'matchComplete' => $matchComplete,
                    'nextMap' => $matchComplete ? null : ($nextMapIndex + 1)
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing map: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update player stats
     */
    public function updatePlayerStats(Request $request, $matchId)
    {
        $this->authorize('manage-matches');
        
        $request->validate([
            'map_number' => 'required|integer|min:1',
            'team_number' => 'required|integer|in:1,2',
            'player_index' => 'required|integer|min:0|max:5',
            'stats' => 'required|array'
        ]);
        
        try {
            $match = MvrlMatch::find($matchId);
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }
            
            $mapsData = $match->maps_data;
            $mapIndex = $request->map_number - 1;
            
            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Invalid map number'], 400);
            }
            
            $compositionKey = "team{$request->team_number}_composition";
            if (!isset($mapsData[$mapIndex][$compositionKey][$request->player_index])) {
                return response()->json(['success' => false, 'message' => 'Invalid player index'], 400);
            }
            
            // Update player stats
            $mapsData[$mapIndex][$compositionKey][$request->player_index]['stats'] = array_merge(
                $mapsData[$mapIndex][$compositionKey][$request->player_index]['stats'] ?? [],
                $request->stats
            );
            
            $match->maps_data = $mapsData;
            $match->save();
            
            // Log to timeline
            $this->logToTimeline($matchId, 'STAT_UPDATE', [
                'mapNumber' => $request->map_number,
                'teamNumber' => $request->team_number,
                'playerIndex' => $request->player_index,
                'stats' => $request->stats
            ]);
            
            // Broadcast update
            $this->broadcastUpdate($matchId, 'STAT_UPDATE', [
                'matchId' => $matchId,
                'mapNumber' => $request->map_number,
                'teamNumber' => $request->team_number,
                'playerIndex' => $request->player_index,
                'stats' => $request->stats,
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Player stats updated successfully',
                'data' => [
                    'playerName' => $mapsData[$mapIndex][$compositionKey][$request->player_index]['name'],
                    'stats' => $request->stats
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating player stats: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Change hero mid-match
     */
    public function changeHero(Request $request, $matchId)
    {
        $this->authorize('manage-matches');
        
        $request->validate([
            'map_number' => 'required|integer|min:1',
            'team_number' => 'required|integer|in:1,2',
            'player_index' => 'required|integer|min:0|max:5',
            'new_hero' => 'required|string',
            'new_role' => 'required|string|in:Duelist,Strategist,Vanguard'
        ]);
        
        try {
            $match = MvrlMatch::find($matchId);
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }
            
            $mapsData = $match->maps_data;
            $mapIndex = $request->map_number - 1;
            
            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Invalid map number'], 400);
            }
            
            $compositionKey = "team{$request->team_number}_composition";
            if (!isset($mapsData[$mapIndex][$compositionKey][$request->player_index])) {
                return response()->json(['success' => false, 'message' => 'Invalid player index'], 400);
            }
            
            $player = &$mapsData[$mapIndex][$compositionKey][$request->player_index];
            $oldHero = $player['hero'];
            
            // Update hero
            $player['hero'] = $request->new_hero;
            $player['role'] = $request->new_role;
            
            $match->maps_data = $mapsData;
            $match->save();
            
            // Log to timeline
            $this->logToTimeline($matchId, 'HERO_CHANGE', [
                'mapNumber' => $request->map_number,
                'teamNumber' => $request->team_number,
                'playerIndex' => $request->player_index,
                'oldHero' => $oldHero,
                'newHero' => $request->new_hero,
                'role' => $request->new_role
            ]);
            
            // Broadcast update
            $this->broadcastUpdate($matchId, 'HERO_CHANGE', [
                'matchId' => $matchId,
                'mapNumber' => $request->map_number,
                'teamNumber' => $request->team_number,
                'playerIndex' => $request->player_index,
                'playerName' => $player['name'],
                'oldHero' => $oldHero,
                'newHero' => $request->new_hero,
                'role' => $request->new_role,
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Hero changed successfully',
                'data' => [
                    'playerName' => $player['name'],
                    'oldHero' => $oldHero,
                    'newHero' => $request->new_hero,
                    'role' => $request->new_role
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error changing hero: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get match timeline
     */
    public function getTimeline($matchId)
    {
        try {
            $timeline = DB::table('match_timeline')
                ->where('match_id', $matchId)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function($event) {
                    $event->event_data = json_decode($event->event_data);
                    return $event;
                });
            
            return response()->json([
                'success' => true,
                'data' => $timeline
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching timeline: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Log event to timeline
     */
    private function logToTimeline($matchId, $eventType, $eventData)
    {
        DB::table('match_timeline')->insert([
            'match_id' => $matchId,
            'event_type' => $eventType,
            'event_data' => json_encode($eventData),
            'created_at' => now()
        ]);
    }
    
    /**
     * Broadcast update for real-time sync
     */
    private function broadcastUpdate($matchId, $updateType, $data)
    {
        // Dispatch custom event for cross-tab synchronization
        $eventData = array_merge(['updateType' => $updateType], $data);
        
        // This will be picked up by the frontend localStorage listener
        // In production, you'd also broadcast via Pusher/WebSocket here
    }
}