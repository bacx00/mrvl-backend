<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\MatchMap;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OptimizedMatchController extends Controller
{
    /**
     * Get match data with optimized queries
     */
    public function show($matchId)
    {
        try {
            $match = MatchModel::with([
                'team1:id,name,slug,logo',
                'team2:id,name,slug,logo',
                'winner:id,name,slug',
                'event:id,name,slug',
                'maps' => function($query) {
                    $query->orderBy('map_number');
                }
            ])
            ->findOrFail($matchId);

            return response()->json([
                'success' => true,
                'data' => [
                    'match' => $match,
                    'series_score' => [
                        'team1' => $match->maps_won_team1 ?? $match->team1_score,
                        'team2' => $match->maps_won_team2 ?? $match->team2_score
                    ],
                    'maps' => $match->maps,
                    'current_map' => $match->getCurrentLiveMap() ?? $match->getNextUpcomingMap(),
                    'format_details' => $match->getFormatDetailsAttribute()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update match and map scores with proper validation
     */
    public function updateMatchScore(Request $request, $matchId)
    {
        $validator = Validator::make($request->all(), [
            'series_score_team1' => 'required|integer|min:0|max:9',
            'series_score_team2' => 'required|integer|min:0|max:9',
            'current_map' => 'nullable|integer|min:1|max:9',
            'maps' => 'nullable|array',
            'maps.*.map_number' => 'required|integer|min:1',
            'maps.*.status' => 'required|in:upcoming,live,completed,paused',
            'maps.*.team1_score' => 'nullable|integer|min:0',
            'maps.*.team2_score' => 'nullable|integer|min:0',
            'maps.*.team1_rounds' => 'nullable|integer|min:0',
            'maps.*.team2_rounds' => 'nullable|integer|min:0',
            'maps.*.winner_id' => 'nullable|exists:teams,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();

            $match = MatchModel::findOrFail($matchId);

            // Validate series scores don't exceed format limits
            $formatDetails = $match->getFormatDetailsAttribute();
            $maxScore = $formatDetails['win_condition'];
            
            if ($request->series_score_team1 > $maxScore || $request->series_score_team2 > $maxScore) {
                return response()->json([
                    'success' => false,
                    'message' => "Series score cannot exceed {$maxScore} for {$match->format} format"
                ], 400);
            }

            // Update match scores
            $match->update([
                'team1_score' => $request->series_score_team1,
                'team2_score' => $request->series_score_team2,
                'maps_won_team1' => $request->series_score_team1,
                'maps_won_team2' => $request->series_score_team2,
                'current_map_number' => $request->current_map ?? $match->current_map_number,
                'current_map_status' => $this->determineMapStatus($request->maps ?? [])
            ]);

            // Update individual map data
            if ($request->has('maps')) {
                foreach ($request->maps as $mapData) {
                    $map = MatchMap::firstOrCreate([
                        'match_id' => $matchId,
                        'map_number' => $mapData['map_number']
                    ]);

                    $updateData = [
                        'status' => $mapData['status'],
                    ];

                    if (isset($mapData['team1_score'])) {
                        $updateData['team1_score'] = $mapData['team1_score'];
                    }
                    if (isset($mapData['team2_score'])) {
                        $updateData['team2_score'] = $mapData['team2_score'];
                    }
                    if (isset($mapData['team1_rounds'])) {
                        $updateData['team1_rounds'] = $mapData['team1_rounds'];
                    }
                    if (isset($mapData['team2_rounds'])) {
                        $updateData['team2_rounds'] = $mapData['team2_rounds'];
                    }
                    if (isset($mapData['winner_id'])) {
                        $updateData['winner_id'] = $mapData['winner_id'];
                    }

                    // Set timestamps based on status
                    if ($mapData['status'] === 'live' && $map->status !== 'live') {
                        $updateData['started_at'] = now();
                    }
                    if ($mapData['status'] === 'completed' && $map->status !== 'completed') {
                        $updateData['ended_at'] = now();
                        if ($map->started_at) {
                            $updateData['duration_seconds'] = now()->diffInSeconds($map->started_at);
                        }
                    }

                    $map->update($updateData);
                }
            }

            // Check if match should be completed
            if ($request->series_score_team1 >= $maxScore || $request->series_score_team2 >= $maxScore) {
                $winnerId = $request->series_score_team1 > $request->series_score_team2 
                    ? $match->team1_id 
                    : $match->team2_id;
                
                $match->update([
                    'status' => 'completed',
                    'winner_id' => $winnerId,
                    'ended_at' => now()
                ]);
            }

            DB::commit();

            // Reload match with updated data
            $match = $match->fresh(['team1', 'team2', 'winner', 'maps']);

            return response()->json([
                'success' => true,
                'message' => 'Match scores updated successfully',
                'data' => [
                    'match' => $match,
                    'series_score' => [
                        'team1' => $match->team1_score,
                        'team2' => $match->team2_score
                    ],
                    'maps' => $match->maps->sortBy('map_number')->values(),
                    'updated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match scores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update single map score
     */
    public function updateMapScore(Request $request, $matchId, $mapNumber)
    {
        $validator = Validator::make($request->all(), [
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'team1_rounds' => 'nullable|integer|min:0',
            'team2_rounds' => 'nullable|integer|min:0',
            'status' => 'required|in:upcoming,live,completed,paused',
            'winner_id' => 'nullable|exists:teams,id',
            'game_mode' => 'nullable|in:Domination,Convoy,Convergence'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $match = MatchModel::findOrFail($matchId);
            $map = MatchMap::where('match_id', $matchId)
                          ->where('map_number', $mapNumber)
                          ->firstOrFail();

            // Update map scores
            $map->update([
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'team1_rounds' => $request->team1_rounds ?? $map->team1_rounds,
                'team2_rounds' => $request->team2_rounds ?? $map->team2_rounds,
                'status' => $request->status,
                'winner_id' => $request->winner_id,
                'game_mode' => $request->game_mode ?? $map->game_mode
            ]);

            // Update match series score if map is completed
            if ($request->status === 'completed') {
                $match->updateSeriesScore();
            }

            return response()->json([
                'success' => true,
                'message' => 'Map score updated successfully',
                'data' => [
                    'map' => $map->fresh(),
                    'match_series_score' => [
                        'team1' => $match->fresh()->team1_score,
                        'team2' => $match->fresh()->team2_score
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update map score: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live match data for real-time updates
     */
    public function getLiveData($matchId)
    {
        try {
            $match = MatchModel::with(['team1:id,name,slug', 'team2:id,name,slug', 'maps'])
                              ->findOrFail($matchId);

            $currentMap = $match->getCurrentLiveMap();
            $nextMap = $match->getNextUpcomingMap();

            return response()->json([
                'success' => true,
                'data' => [
                    'match_id' => $match->id,
                    'status' => $match->status,
                    'series_score' => [
                        'team1' => $match->team1_score,
                        'team2' => $match->team2_score
                    ],
                    'current_map' => $currentMap ? [
                        'map_number' => $currentMap->map_number,
                        'map_name' => $currentMap->map_name,
                        'game_mode' => $currentMap->game_mode,
                        'status' => $currentMap->status,
                        'scores' => [
                            'team1' => $currentMap->team1_score,
                            'team2' => $currentMap->team2_score,
                            'team1_rounds' => $currentMap->team1_rounds,
                            'team2_rounds' => $currentMap->team2_rounds
                        ]
                    ] : null,
                    'next_map' => $nextMap ? [
                        'map_number' => $nextMap->map_number,
                        'map_name' => $nextMap->map_name,
                        'game_mode' => $nextMap->game_mode
                    ] : null,
                    'maps' => $match->maps ? $match->maps->map(function($map) {
                        return [
                            'map_number' => $map->map_number,
                            'map_name' => $map->map_name,
                            'status' => $map->status,
                            'team1_score' => $map->team1_score,
                            'team2_score' => $map->team2_score,
                            'winner_id' => $map->winner_id
                        ];
                    }) : [],
                    'last_updated' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get live data: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Determine current map status from maps data
     */
    private function determineMapStatus(array $maps): string
    {
        $liveMap = collect($maps)->firstWhere('status', 'live');
        if ($liveMap) return 'live';

        $upcomingMap = collect($maps)->firstWhere('status', 'upcoming');
        if ($upcomingMap) return 'upcoming';

        return 'completed';
    }
}