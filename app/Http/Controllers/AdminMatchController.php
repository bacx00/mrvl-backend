<?php

namespace App\Http\Controllers;

use App\Models\MvrlMatch;
use App\Events\MatchMapUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMatchController extends Controller
{
    /**
     * POST /api/admin/matches
     * Create a new match.
     */
    public function store(Request $request): JsonResponse
    {
        \Log::info('Match creation request data:', $request->all());
        
        $validatedData = $request->validate([
            'team1_id' => 'required|exists:teams,id',
            'team2_id' => 'required|exists:teams,id|different:team1_id',
            'event_id' => 'nullable|exists:events,id',
            'scheduled_at' => 'required|date',
            'format' => 'required|in:BO1,BO3,BO5,BO7,BO9',
            'status' => 'required|in:upcoming,live,completed',
            'maps' => 'required|array|min:1',
            'maps.*.map_name' => 'required|string',
            'maps.*.mode' => 'required|string',
            'maps.*.team1_score' => 'nullable|integer|min:0',
            'maps.*.team2_score' => 'nullable|integer|min:0',
            'maps.*.winner_id' => 'nullable|exists:teams,id',
            'maps.*.team1_composition' => 'nullable|array',
            'maps.*.team1_composition.*.player_id' => 'nullable|integer',
            'maps.*.team1_composition.*.hero' => 'nullable|string',
            'maps.*.team2_composition' => 'nullable|array',
            'maps.*.team2_composition.*.player_id' => 'nullable|integer',
            'maps.*.team2_composition.*.hero' => 'nullable|string',
            'stream_urls' => 'nullable|array',
            'stream_urls.*' => 'nullable|url',
            'betting_urls' => 'nullable|array',
            'betting_urls.*' => 'nullable|url',
            'vod_urls' => 'nullable|array',
            'vod_urls.*' => 'nullable|url',
            'allow_past_date' => 'boolean'
        ]);

        // Calculate final scores from maps
        $team1Score = 0;
        $team2Score = 0;
        foreach ($validatedData['maps'] as &$map) {
            // Determine winner based on scores if not already set
            if (!isset($map['winner_id']) && isset($map['team1_score']) && isset($map['team2_score'])) {
                if ($map['team1_score'] > $map['team2_score']) {
                    $map['winner_id'] = $validatedData['team1_id'];
                } elseif ($map['team2_score'] > $map['team1_score']) {
                    $map['winner_id'] = $validatedData['team2_id'];
                }
            }
            
            // Count wins
            if (isset($map['winner_id'])) {
                if ($map['winner_id'] == $validatedData['team1_id']) {
                    $team1Score++;
                } elseif ($map['winner_id'] == $validatedData['team2_id']) {
                    $team2Score++;
                }
            }
        }

        $matchData = [
            'team1_id' => $validatedData['team1_id'],
            'team2_id' => $validatedData['team2_id'],
            'event_id' => $validatedData['event_id'] ?? null,
            'scheduled_at' => $validatedData['scheduled_at'],
            'format' => $validatedData['format'],
            'status' => $validatedData['status'],
            'team1_score' => $team1Score,
            'team2_score' => $team2Score,
            'series_score_team1' => $team1Score,
            'series_score_team2' => $team2Score,
            'maps_data' => $validatedData['maps'],
            'stream_urls' => array_filter($validatedData['stream_urls'] ?? []),
            'betting_urls' => array_filter($validatedData['betting_urls'] ?? []),
            'vod_urls' => array_filter($validatedData['vod_urls'] ?? []),
            'current_map_number' => 1,
            'viewers' => 0,
            'allow_past_date' => $validatedData['allow_past_date'] ?? false
        ];

        $match = new MvrlMatch($matchData);
        $match->save();

        return response()->json([
            'data' => ['id' => $match->id],
            'success' => true,
            'message' => 'Match created successfully'
        ], 201);
    }

    /**
     * PUT /api/admin/matches/{match}
     * Update an existing match.
     */
    public function update(Request $request, MvrlMatch $match): JsonResponse
    {
        $validatedData = $request->validate([
            'team1_id' => 'sometimes|required|exists:teams,id',
            'team2_id' => 'sometimes|required|exists:teams,id|different:team1_id',
            'event_id' => 'nullable|exists:events,id',
            'scheduled_at' => 'sometimes|required|date',
            'format' => 'sometimes|required|in:BO1,BO3,BO5,BO7,BO9',
            'status' => 'sometimes|required|in:upcoming,live,completed',
            'maps' => 'sometimes|array|min:1',
            'maps.*.map_name' => 'required|string',
            'maps.*.mode' => 'required|string',
            'maps.*.team1_score' => 'nullable|integer|min:0',
            'maps.*.team2_score' => 'nullable|integer|min:0',
            'maps.*.winner_id' => 'nullable|exists:teams,id',
            'maps.*.team1_composition' => 'nullable|array',
            'maps.*.team1_composition.*.player_id' => 'nullable|integer',
            'maps.*.team1_composition.*.hero' => 'nullable|string',
            'maps.*.team2_composition' => 'nullable|array',
            'maps.*.team2_composition.*.player_id' => 'nullable|integer',
            'maps.*.team2_composition.*.hero' => 'nullable|string',
            'stream_urls' => 'nullable|array',
            'stream_urls.*' => 'nullable|url',
            'betting_urls' => 'nullable|array',
            'betting_urls.*' => 'nullable|url',
            'vod_urls' => 'nullable|array',
            'vod_urls.*' => 'nullable|url',
            'allow_past_date' => 'boolean'
        ]);

        // Calculate final scores from maps if provided
        if (isset($validatedData['maps'])) {
            $team1Score = 0;
            $team2Score = 0;
            $team1Id = $validatedData['team1_id'] ?? $match->team1_id;
            $team2Id = $validatedData['team2_id'] ?? $match->team2_id;
            
            foreach ($validatedData['maps'] as &$map) {
                // Determine winner based on scores if not already set
                if (!isset($map['winner_id']) && isset($map['team1_score']) && isset($map['team2_score'])) {
                    if ($map['team1_score'] > $map['team2_score']) {
                        $map['winner_id'] = $team1Id;
                    } elseif ($map['team2_score'] > $map['team1_score']) {
                        $map['winner_id'] = $team2Id;
                    }
                }
                
                // Count wins
                if (isset($map['winner_id'])) {
                    if ($map['winner_id'] == $team1Id) {
                        $team1Score++;
                    } elseif ($map['winner_id'] == $team2Id) {
                        $team2Score++;
                    }
                }
            }
            $validatedData['team1_score'] = $team1Score;
            $validatedData['team2_score'] = $team2Score;
            $validatedData['series_score_team1'] = $team1Score;
            $validatedData['series_score_team2'] = $team2Score;
            $validatedData['maps_data'] = $validatedData['maps'];
            unset($validatedData['maps']);
        }

        // Handle URL arrays
        if (isset($validatedData['stream_urls'])) {
            $validatedData['stream_urls'] = array_filter($validatedData['stream_urls']);
        }
        if (isset($validatedData['betting_urls'])) {
            $validatedData['betting_urls'] = array_filter($validatedData['betting_urls']);
        }
        if (isset($validatedData['vod_urls'])) {
            $validatedData['vod_urls'] = array_filter($validatedData['vod_urls']);
        }

        $match->update($validatedData);
        
        // Refresh the match to ensure we have the latest data
        $match->refresh();

        return response()->json([
            'data' => ['id' => $match->id],
            'success' => true,
            'message' => 'Match updated successfully'
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/admin/matches/{match}
     * Delete a match.
     */
    public function destroy($matchId): JsonResponse
    {
        $match = MvrlMatch::findOrFail($matchId);
        $match->delete();
        return response()->json(['message' => 'Match deleted successfully'], Response::HTTP_OK);
    }

    /**
     * POST /api/admin/matches/{matchId}/live-scoring
     * Update live scoring data for a match.
     */
    public function updateLiveScoring(Request $request, $matchId): JsonResponse
    {
        $match = MvrlMatch::findOrFail($matchId);

        $validatedData = $request->validate([
            'timer' => 'nullable|string',
            'status' => 'nullable|in:upcoming,live,completed,paused',
            'current_map' => 'nullable|integer|min:1',
            'current_map_data' => 'nullable|array',
            'current_map_data.name' => 'nullable|string',
            'current_map_data.mode' => 'nullable|string',
            'current_map_data.team1Score' => 'nullable|integer|min:0',
            'current_map_data.team2Score' => 'nullable|integer|min:0',
            'current_map_data.status' => 'nullable|string',
            'player_stats' => 'nullable|array',
            'hero_selections' => 'nullable|array',
            'hero_selections.*.player_id' => 'nullable|integer',
            'hero_selections.*.hero' => 'nullable|string',
            'hero_selections.*.team' => 'nullable|integer|in:1,2',
            'series_score' => 'nullable|array',
            'series_score.team1' => 'nullable|integer|min:0',
            'series_score.team2' => 'nullable|integer|min:0',
            'map_scores' => 'nullable|array',
            'map_scores.*.map_number' => 'nullable|integer|min:1',
            'map_scores.*.team1_score' => 'nullable|integer|min:0',
            'map_scores.*.team2_score' => 'nullable|integer|min:0',
            'map_scores.*.winner_id' => 'nullable|exists:teams,id'
        ]);

        // Update match status if provided
        if (isset($validatedData['status'])) {
            $match->status = $validatedData['status'];
        }

        // Update current map number
        if (isset($validatedData['current_map'])) {
            $match->current_map_number = $validatedData['current_map'];
        }

        // Update series scores
        if (isset($validatedData['series_score'])) {
            $match->team1_score = $validatedData['series_score']['team1'];
            $match->team2_score = $validatedData['series_score']['team2'];
            $match->series_score_team1 = $validatedData['series_score']['team1'];
            $match->series_score_team2 = $validatedData['series_score']['team2'];
        }

        // Update current map data
        if (isset($validatedData['current_map_data'])) {
            $maps = $match->maps_data ?? [];
            $currentMapIndex = ($match->current_map_number ?? 1) - 1;
            
            if (!isset($maps[$currentMapIndex])) {
                $maps[$currentMapIndex] = [];
            }
            
            $maps[$currentMapIndex] = array_merge($maps[$currentMapIndex], [
                'map_name' => $validatedData['current_map_data']['name'] ?? $maps[$currentMapIndex]['map_name'] ?? '',
                'mode' => $validatedData['current_map_data']['mode'] ?? $maps[$currentMapIndex]['mode'] ?? '',
                'team1_score' => $validatedData['current_map_data']['team1Score'] ?? $validatedData['current_map_data']['team1_score'] ?? 0,
                'team2_score' => $validatedData['current_map_data']['team2Score'] ?? $validatedData['current_map_data']['team2_score'] ?? 0,
                'status' => $validatedData['current_map_data']['status'] ?? 'ongoing'
            ]);

            // Determine map winner
            if ($maps[$currentMapIndex]['team1_score'] >= 100 || $maps[$currentMapIndex]['team2_score'] >= 100) {
                if ($maps[$currentMapIndex]['team1_score'] > $maps[$currentMapIndex]['team2_score']) {
                    $maps[$currentMapIndex]['winner_id'] = $match->team1_id;
                } else {
                    $maps[$currentMapIndex]['winner_id'] = $match->team2_id;
                }
                $maps[$currentMapIndex]['status'] = 'completed';
            }

            $match->maps_data = $maps;
        }

        // Update player stats
        if (isset($validatedData['player_stats'])) {
            $match->player_stats = $validatedData['player_stats'];
        }
        
        // Update hero selections
        if (isset($validatedData['hero_selections'])) {
            $currentStats = $match->player_stats ?? [];
            
            foreach ($validatedData['hero_selections'] as $selection) {
                $playerId = $selection['player_id'];
                if (!isset($currentStats[$playerId])) {
                    $currentStats[$playerId] = [];
                }
                $currentStats[$playerId]['hero'] = $selection['hero'];
                $currentStats[$playerId]['team'] = $selection['team'];
                $currentStats[$playerId]['last_hero_change'] = now()->toISOString();
            }
            
            $match->player_stats = $currentStats;
        }
        
        // Update map scores directly if provided
        if (isset($validatedData['map_scores'])) {
            $maps = $match->maps_data ?? [];
            
            foreach ($validatedData['map_scores'] as $mapScore) {
                $mapIndex = ($mapScore['map_number'] ?? 1) - 1;
                if (!isset($maps[$mapIndex])) {
                    $maps[$mapIndex] = [];
                }
                
                $maps[$mapIndex]['team1_score'] = $mapScore['team1_score'] ?? $maps[$mapIndex]['team1_score'] ?? 0;
                $maps[$mapIndex]['team2_score'] = $mapScore['team2_score'] ?? $maps[$mapIndex]['team2_score'] ?? 0;
                
                if (isset($mapScore['winner_id'])) {
                    $maps[$mapIndex]['winner_id'] = $mapScore['winner_id'];
                    $maps[$mapIndex]['status'] = 'completed';
                }
            }
            
            $match->maps_data = $maps;
            
            // Recalculate series scores
            $team1Total = 0;
            $team2Total = 0;
            foreach ($maps as $map) {
                if (isset($map['winner_id'])) {
                    if ($map['winner_id'] == $match->team1_id) {
                        $team1Total++;
                    } elseif ($map['winner_id'] == $match->team2_id) {
                        $team2Total++;
                    }
                }
            }
            
            $match->team1_score = $team1Total;
            $match->team2_score = $team2Total;
            $match->series_score_team1 = $team1Total;
            $match->series_score_team2 = $team2Total;
        }

        // Update timer - store as array
        if (isset($validatedData['timer'])) {
            $match->match_timer = [
                'time' => $validatedData['timer'],
                'status' => $validatedData['status'] === 'live' ? 'running' : 'stopped'
            ];
        }

        $match->save();

        // Broadcast the update event with specific update type
        $updateType = 'general';
        if (isset($validatedData['hero_selections'])) {
            $updateType = 'hero_update';
        } elseif (isset($validatedData['map_scores']) || isset($validatedData['current_map_data'])) {
            $updateType = 'score_update';
        } elseif (isset($validatedData['player_stats'])) {
            $updateType = 'stats_update';
        }
        
        broadcast(new \App\Events\LiveMatchUpdate($match, $updateType, $validatedData));
        broadcast(new \App\Events\MatchMapUpdated($match)); // Keep for backward compatibility
        
        // Broadcast bracket update if match is part of an event
        if ($match->event_id) {
            broadcast(new \App\Events\BracketUpdated(
                $match->event_id,
                $match->id,
                'match-updated',
                [
                    'match_status' => $match->status,
                    'scores' => [
                        'team1' => $match->team1_score,
                        'team2' => $match->team2_score
                    ]
                ]
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Live scoring updated successfully',
            'data' => [
                'match_id' => $match->id,
                'status' => $match->status,
                'current_map' => $match->current_map_number,
                'series_score' => [
                    'team1' => $match->team1_score,
                    'team2' => $match->team2_score
                ]
            ]
        ]);
    }

    /**
     * POST /api/admin/matches/{matchId}/control
     * Control match state (start, pause, resume, complete).
     */
    public function controlMatch(Request $request, $matchId): JsonResponse
    {
        $match = MvrlMatch::findOrFail($matchId);

        $validatedData = $request->validate([
            'action' => 'required|in:start,pause,resume,complete,restart'
        ]);

        switch ($validatedData['action']) {
            case 'start':
                $match->status = 'live';
                $match->started_at = now();
                broadcast(new \App\Events\MatchStarted($match));
                break;
            
            case 'pause':
                $match->status = 'paused';
                broadcast(new \App\Events\MatchPaused($match));
                break;
            
            case 'resume':
                $match->status = 'live';
                broadcast(new \App\Events\MatchResumed($match));
                break;
            
            case 'complete':
                $match->status = 'completed';
                $match->ended_at = now();
                
                // Determine winner
                if ($match->team1_score > $match->team2_score) {
                    $match->winner_id = $match->team1_id;
                } elseif ($match->team2_score > $match->team1_score) {
                    $match->winner_id = $match->team2_id;
                }
                
                // Broadcast match completed event with correct parameters
                broadcast(new \App\Events\MatchMapEnded(
                    $match->id,
                    $match->current_map_number ?? 1,
                    $match->winner_id,
                    true // match completed
                ));
                break;
            
            case 'restart':
                $match->status = 'upcoming';
                $match->team1_score = 0;
                $match->team2_score = 0;
                $match->series_score_team1 = 0;
                $match->series_score_team2 = 0;
                $match->current_map_number = 1;
                $match->winner_id = null;
                $match->started_at = null;
                $match->ended_at = null;
                
                // Reset maps data
                $maps = $match->maps_data ?? [];
                foreach ($maps as &$map) {
                    $map['team1_score'] = 0;
                    $map['team2_score'] = 0;
                    $map['winner_id'] = null;
                    $map['status'] = 'upcoming';
                }
                $match->maps_data = $maps;
                break;
        }

        $match->save();

        return response()->json([
            'success' => true,
            'message' => "Match {$validatedData['action']} successfully",
            'data' => [
                'match_id' => $match->id,
                'status' => $match->status,
                'action' => $validatedData['action']
            ]
        ]);
    }
}
