<?php

namespace App\Http\Controllers;

use App\Models\MvrlMatch;
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
            'allow_past_date' => 'boolean'
        ]);

        // Calculate final scores from maps
        $team1Score = 0;
        $team2Score = 0;
        foreach ($validatedData['maps'] as $map) {
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
            'maps_data' => json_encode($validatedData['maps']),
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
            'score1' => 'nullable|integer|min:0',
            'score2' => 'nullable|integer|min:0',
            'date' => 'sometimes|required|date',
            'format' => 'sometimes|required|string',
            'status' => 'sometimes|required|in:upcoming,live,completed',
            'event_name' => 'nullable|string',
        ]);

        $match->update($validatedData);

        return response()->json($match, Response::HTTP_OK);
    }

    /**
     * DELETE /api/admin/matches/{match}
     * Delete a match.
     */
    public function destroy(MvrlMatch $match): JsonResponse
    {
        $match->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
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
            'series_score' => 'nullable|array',
            'series_score.team1' => 'nullable|integer|min:0',
            'series_score.team2' => 'nullable|integer|min:0'
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
            $maps = json_decode($match->maps_data, true) ?? [];
            $currentMapIndex = ($match->current_map_number ?? 1) - 1;
            
            if (!isset($maps[$currentMapIndex])) {
                $maps[$currentMapIndex] = [];
            }
            
            $maps[$currentMapIndex] = array_merge($maps[$currentMapIndex], [
                'map_name' => $validatedData['current_map_data']['name'] ?? $maps[$currentMapIndex]['map_name'] ?? '',
                'mode' => $validatedData['current_map_data']['mode'] ?? $maps[$currentMapIndex]['mode'] ?? '',
                'team1_score' => $validatedData['current_map_data']['team1Score'] ?? 0,
                'team2_score' => $validatedData['current_map_data']['team2Score'] ?? 0,
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

            $match->maps_data = json_encode($maps);
        }

        // Update player stats
        if (isset($validatedData['player_stats'])) {
            $match->player_stats = json_encode($validatedData['player_stats']);
        }

        // Update timer - store as JSON
        if (isset($validatedData['timer'])) {
            $match->match_timer = json_encode([
                'time' => $validatedData['timer'],
                'status' => $validatedData['status'] === 'live' ? 'running' : 'stopped'
            ]);
        }

        $match->save();

        // Note: Broadcasting removed - using simple API polling instead

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
                // Note: Broadcasting removed - using simple API polling
                break;
            
            case 'pause':
                $match->status = 'paused';
                // Note: Broadcasting removed - using simple API polling
                break;
            
            case 'resume':
                $match->status = 'live';
                // Note: Broadcasting removed - using simple API polling
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
                
                // Note: Broadcasting removed - using simple API polling
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
                $maps = json_decode($match->maps_data, true) ?? [];
                foreach ($maps as &$map) {
                    $map['team1_score'] = 0;
                    $map['team2_score'] = 0;
                    $map['winner_id'] = null;
                    $map['status'] = 'upcoming';
                }
                $match->maps_data = json_encode($maps);
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
