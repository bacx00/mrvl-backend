<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// ==========================================
// 6. COMPLETE LIVE MATCH DATA RETRIEVAL
// ==========================================

Route::get('/matches/{id}/live-scoreboard', function (Request $request, $id) {
    try {
        // Get main match data
        $match = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'm.*',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo', 't1.country as team1_country',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo', 't2.country as team2_country',
                'e.name as event_name', 'e.type as event_type'
            ])
            ->where('m.id', $id)
            ->first();

        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        // Get all rounds data
        $rounds = DB::table('match_rounds')
            ->where('match_id', $id)
            ->orderBy('round_number')
            ->get();

        // Get current round details
        $currentRound = $rounds->where('round_number', $match->current_round)->first();

        // Get active timers
        $activeTimers = DB::table('competitive_timers')
            ->where('match_id', $id)
            ->where('status', '!=', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get player statistics for current round
        $playerStats = [];
        if ($currentRound) {
            $playerStats = DB::table('player_match_stats as pms')
                ->leftJoin('players as p', 'pms.player_id', '=', 'p.id')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->select([
                    'pms.*',
                    'p.name as player_name', 'p.username as player_username', 'p.team_id',
                    't.name as team_name', 't.short_name as team_short'
                ])
                ->where('pms.match_id', $id)
                ->where('pms.round_id', $currentRound->id)
                ->get()
                ->groupBy('team_id');
        }

        // Get recent live events
        $recentEvents = DB::table('live_events as le')
            ->leftJoin('players as p', 'le.player_id', '=', 'p.id')
            ->leftJoin('players as tp', 'le.target_player_id', '=', 'tp.id')
            ->select([
                'le.*',
                'p.name as player_name', 'p.username as player_username',
                'tp.name as target_player_name', 'tp.username as target_username'
            ])
            ->where('le.match_id', $id)
            ->orderBy('le.event_timestamp', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'match' => $match,
                'current_round' => $currentRound,
                'all_rounds' => $rounds,
                'active_timers' => $activeTimers,
                'player_statistics' => $playerStats,
                'recent_events' => $recentEvents,
                'live_data' => [
                    'status' => $match->status,
                    'current_map' => $match->current_map,
                    'current_mode' => $match->current_mode,
                    'series_score' => [$match->team1_score, $match->team2_score],
                    'format' => $match->match_format,
                    'viewers' => $match->viewers ?? 0
                ]
            ],
            'cache_control' => [
                'max_age' => 5, // 5 seconds cache for live data
                'last_updated' => $match->updated_at
            ]
        ], 200, [
            'Cache-Control' => 'public, max-age=5',
            'Last-Modified' => gmdate('D, d M Y H:i:s \G\M\T', strtotime($match->updated_at))
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching live scoreboard: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// 7. COMPREHENSIVE ADMIN LIVE DASHBOARD
// ==========================================

Route::middleware(['auth:sanctum', 'role:admin|moderator'])->get('/admin/matches/{id}/live-control', function (Request $request, $id) {
    try {
        // Get complete match control data
        $match = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'm.*',
                't1.name as team1_name', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.logo as team2_logo',
                'e.name as event_name'
            ])
            ->where('m.id', $id)
            ->first();

        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        // Get team rosters with player details
        $team1Players = DB::table('players')
            ->where('team_id', $match->team1_id)
            ->select(['id', 'name', 'username', 'role', 'main_hero', 'rating'])
            ->get();

        $team2Players = DB::table('players')
            ->where('team_id', $match->team2_id)
            ->select(['id', 'name', 'username', 'role', 'main_hero', 'rating'])
            ->get();

        // Get all rounds with compositions
        $rounds = DB::table('match_rounds')
            ->where('match_id', $id)
            ->orderBy('round_number')
            ->get()
            ->map(function ($round) {
                $round->team1_composition = json_decode($round->team1_composition, true) ?? [];
                $round->team2_composition = json_decode($round->team2_composition, true) ?? [];
                return $round;
            });

        // Get competitive settings
        $competitiveSettings = json_decode($match->competitive_settings, true) ?? [];
        $preparationPhase = json_decode($match->preparation_phase, true) ?? [];

        // Get all timers for this match
        $timers = DB::table('competitive_timers')
            ->where('match_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get available heroes grouped by role
        $heroes = DB::table('marvel_heroes')
            ->where('available_in_competitive', true)
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->groupBy('role');

        // Get available maps and modes
        $maps = DB::table('marvel_maps')
            ->where('available_in_ranked', true)
            ->get();

        $gameModes = DB::table('game_modes')
            ->where('available_in_competitive', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'match_info' => $match,
                'team_rosters' => [
                    'team1' => $team1Players,
                    'team2' => $team2Players
                ],
                'rounds' => $rounds,
                'competitive_settings' => $competitiveSettings,
                'preparation_phase' => $preparationPhase,
                'timers' => $timers,
                'game_data' => [
                    'heroes' => $heroes,
                    'maps' => $maps,
                    'game_modes' => $gameModes
                ],
                'control_capabilities' => [
                    'can_modify_compositions' => true,
                    'can_control_timers' => true,
                    'can_update_scores' => true,
                    'can_manage_rounds' => true,
                    'can_pause_resume' => true
                ]
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching admin control data: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// 8. BULK PLAYER STATISTICS UPDATE
// ==========================================

Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/admin/matches/{matchId}/bulk-player-stats', function (Request $request, $matchId) {
    try {
        $validator = Validator::make($request->all(), [
            'round_number' => 'nullable|integer|min:1',
            'player_stats' => 'required|array',
            'player_stats.*.player_id' => 'required|exists:players,id',
            'player_stats.*.eliminations' => 'nullable|integer|min:0',
            'player_stats.*.deaths' => 'nullable|integer|min:0',
            'player_stats.*.assists' => 'nullable|integer|min:0',
            'player_stats.*.damage' => 'nullable|integer|min:0',
            'player_stats.*.healing' => 'nullable|integer|min:0',
            'player_stats.*.damage_blocked' => 'nullable|integer|min:0',
            'player_stats.*.ultimate_usage' => 'nullable|integer|min:0',
            'player_stats.*.objective_time' => 'nullable|integer|min:0',
            'player_stats.*.hero_played' => 'nullable|string',
            'player_stats.*.role_played' => 'nullable|in:Vanguard,Duelist,Strategist'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        
        $match = DB::table('matches')->where('id', $matchId)->first();
        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        $roundNumber = $validated['round_number'] ?? $match->current_round;
        $round = DB::table('match_rounds')
            ->where('match_id', $matchId)
            ->where('round_number', $roundNumber)
            ->first();

        if (!$round) {
            return response()->json(['success' => false, 'message' => 'Round not found'], 404);
        }

        DB::beginTransaction();

        $updatedPlayers = [];
        foreach ($validated['player_stats'] as $playerData) {
            $playerId = $playerData['player_id'];
            unset($playerData['player_id']);

            // Filter out null values
            $updateData = array_filter($playerData, function($value) {
                return $value !== null;
            });
            $updateData['updated_at'] = now();

            // Update or create player stats
            $existingStats = DB::table('player_match_stats')
                ->where('player_id', $playerId)
                ->where('match_id', $matchId)
                ->where('round_id', $round->id)
                ->first();

            if ($existingStats) {
                DB::table('player_match_stats')
                    ->where('id', $existingStats->id)
                    ->update($updateData);
            } else {
                $insertData = array_merge([
                    'player_id' => $playerId,
                    'match_id' => $matchId,
                    'round_id' => $round->id,
                    'current_map' => $round->map_name,
                    'created_at' => now()
                ], $updateData);
                
                DB::table('player_match_stats')->insert($insertData);
            }

            $updatedPlayers[] = $playerId;
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Bulk player statistics updated successfully',
            'data' => [
                'players_updated' => count($updatedPlayers),
                'round_number' => $roundNumber,
                'match_id' => $matchId
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Bulk stats update error: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// 9. MATCH HISTORY AND PROFILE INTEGRATION
// ==========================================

Route::get('/api/teams/{teamId}/match-history', function (Request $request, $teamId) {
    try {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        // Get team match history
        $matchHistory = DB::table('match_history as mh')
            ->leftJoin('matches as m', 'mh.match_id', '=', 'm.id')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'mh.*',
                'm.scheduled_at', 'm.match_format', 'm.team1_score', 'm.team2_score',
                't1.name as team1_name', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.logo as team2_logo',
                'e.name as event_name', 'e.type as event_type'
            ])
            ->where('mh.team_id', $teamId)
            ->whereNull('mh.player_id') // Team records only
            ->orderBy('m.scheduled_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        // Get team statistics
        $teamStats = [
            'total_matches' => DB::table('match_history')
                ->where('team_id', $teamId)
                ->whereNull('player_id')
                ->count(),
            'wins' => DB::table('match_history')
                ->where('team_id', $teamId)
                ->whereNull('player_id')
                ->where('result', 'win')
                ->count(),
            'losses' => DB::table('match_history')
                ->where('team_id', $teamId)
                ->whereNull('player_id')
                ->where('result', 'loss')
                ->count()
        ];

        $teamStats['win_rate'] = $teamStats['total_matches'] > 0 
            ? round(($teamStats['wins'] / $teamStats['total_matches']) * 100, 2) 
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'match_history' => $matchHistory,
                'team_statistics' => $teamStats,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_matches' => $teamStats['total_matches']
                ]
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching team history: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/api/players/{playerId}/match-history', function (Request $request, $playerId) {
    try {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        // Get player match history
        $matchHistory = DB::table('match_history as mh')
            ->leftJoin('matches as m', 'mh.match_id', '=', 'm.id')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'mh.*',
                'm.scheduled_at', 'm.match_format', 'm.team1_score', 'm.team2_score',
                't1.name as team1_name', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.logo as team2_logo',
                'e.name as event_name', 'e.type as event_type'
            ])
            ->where('mh.player_id', $playerId)
            ->orderBy('m.scheduled_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($match) {
                $match->performance_data = json_decode($match->performance_data, true);
                $match->achievements = json_decode($match->achievements, true);
                return $match;
            });

        // Get player career statistics
        $playerStats = [
            'total_matches' => DB::table('match_history')
                ->where('player_id', $playerId)
                ->count(),
            'wins' => DB::table('match_history')
                ->where('player_id', $playerId)
                ->where('result', 'win')
                ->count(),
            'losses' => DB::table('match_history')
                ->where('player_id', $playerId)
                ->where('result', 'loss')
                ->count(),
            'mvp_awards' => DB::table('match_history')
                ->where('player_id', $playerId)
                ->where('mvp', true)
                ->count(),
            'average_performance_rating' => DB::table('match_history')
                ->where('player_id', $playerId)
                ->avg('performance_rating')
        ];

        $playerStats['win_rate'] = $playerStats['total_matches'] > 0 
            ? round(($playerStats['wins'] / $playerStats['total_matches']) * 100, 2) 
            : 0;

        // Get hero usage statistics
        $heroStats = DB::table('player_match_stats')
            ->select('hero_played', DB::raw('COUNT(*) as matches_played'))
            ->where('player_id', $playerId)
            ->whereNotNull('hero_played')
            ->groupBy('hero_played')
            ->orderBy('matches_played', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'match_history' => $matchHistory,
                'career_statistics' => $playerStats,
                'favorite_heroes' => $heroStats,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_matches' => $playerStats['total_matches']
                ]
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching player history: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// 10. REAL-TIME MATCH STATUS UPDATES
// ==========================================

Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/api/admin/matches/{id}/status', function (Request $request, $id) {
    try {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:upcoming,live,paused,completed,cancelled',
            'reason' => 'nullable|string|max:255',
            'update_timers' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        
        $match = DB::table('matches')->where('id', $id)->first();
        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        DB::beginTransaction();

        // Update match status
        $updateData = [
            'status' => $validated['status'],
            'updated_at' => now()
        ];

        DB::table('matches')->where('id', $id)->update($updateData);

        // Handle timer updates based on status
        if ($validated['update_timers'] ?? true) {
            switch ($validated['status']) {
                case 'live':
                    // Resume any paused timers
                    DB::table('competitive_timers')
                        ->where('match_id', $id)
                        ->where('status', 'paused')
                        ->update([
                            'status' => 'running',
                            'paused_at' => null,
                            'updated_at' => now()
                        ]);
                    break;

                case 'paused':
                    // Pause all running timers
                    DB::table('competitive_timers')
                        ->where('match_id', $id)
                        ->where('status', 'running')
                        ->update([
                            'status' => 'paused',
                            'paused_at' => now(),
                            'updated_at' => now()
                        ]);
                    break;

                case 'completed':
                    // Complete all timers
                    DB::table('competitive_timers')
                        ->where('match_id', $id)
                        ->whereIn('status', ['running', 'paused'])
                        ->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'updated_at' => now()
                        ]);
                    break;

                case 'cancelled':
                    // Cancel all timers
                    DB::table('competitive_timers')
                        ->where('match_id', $id)
                        ->whereIn('status', ['running', 'paused'])
                        ->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'updated_at' => now()
                        ]);
                    break;
            }
        }

        // Log status change event
        DB::table('live_events')->insert([
            'match_id' => $id,
            'event_type' => 'status_change',
            'event_data' => json_encode([
                'previous_status' => $match->status,
                'new_status' => $validated['status'],
                'reason' => $validated['reason'] ?? null
            ]),
            'event_timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Match status updated successfully',
            'data' => [
                'previous_status' => $match->status,
                'new_status' => $validated['status'],
                'timestamp' => now()->toISOString()
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Status update error: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// 11. LIVE VIEWER COUNT MANAGEMENT
// ==========================================

Route::post('/api/matches/{id}/viewers/update', function (Request $request, $id) {
    try {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:increment,decrement,set',
            'count' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        
        $match = DB::table('matches')->where('id', $id)->first();
        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        $currentViewers = $match->viewers ?? 0;
        $newViewerCount = $currentViewers;

        switch ($validated['action']) {
            case 'increment':
                $newViewerCount = $currentViewers + 1;
                break;
            case 'decrement':
                $newViewerCount = max(0, $currentViewers - 1);
                break;
            case 'set':
                $newViewerCount = $validated['count'] ?? 0;
                break;
        }

        DB::table('matches')->where('id', $id)->update([
            'viewers' => $newViewerCount,
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'previous_count' => $currentViewers,
                'new_count' => $newViewerCount,
                'action' => $validated['action']
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Viewer update error: ' . $e->getMessage()
        ], 500);
    }
});