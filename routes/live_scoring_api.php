<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// ==========================================
// MARVEL RIVALS PROFESSIONAL LIVE SCORING SYSTEM
// Complete API Routes for Real-Time Competition
// ==========================================

// ==========================================
// 1. FRESH MATCH CREATION WITH FULL SPECIFICATIONS
// ==========================================

Route::middleware(['auth:sanctum', 'role:admin|moderator'])->post('/admin/matches/create-competitive', function (Request $request) {
    try {
        $validator = Validator::make($request->all(), [
            'team1_id' => 'required|exists:teams,id',
            'team2_id' => 'required|exists:teams,id|different:team1_id',
            'event_id' => 'nullable|exists:events,id',
            'match_format' => 'required|in:BO1,BO3,BO5',
            'map_pool' => 'required|array|min:1',
            'map_pool.*.map_name' => 'required|string',
            'map_pool.*.game_mode' => 'required|in:Domination,Convoy,Convergence,Conquest,Doom Match',
            'competitive_settings' => 'nullable|array',
            'scheduled_at' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        
        DB::beginTransaction();

        // Create main match record
        $matchData = [
            'team1_id' => $validated['team1_id'],
            'team2_id' => $validated['team2_id'], 
            'event_id' => $validated['event_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? now(),
            'status' => 'upcoming',
            'match_format' => $validated['match_format'],
            'format' => $validated['match_format'], // Legacy compatibility
            'current_round' => 1,
            'current_map' => $validated['map_pool'][0]['map_name'],
            'current_mode' => $validated['map_pool'][0]['game_mode'],
            'team1_score' => 0,
            'team2_score' => 0,
            'viewers' => 0,
            'series_completed' => false,
            'competitive_settings' => json_encode($validated['competitive_settings'] ?? [
                'preparation_time' => 45,
                'tactical_pauses_per_team' => 2,
                'pause_duration' => 120,
                'overtime_enabled' => true,
                'hero_selection_time' => 30
            ]),
            'preparation_phase' => json_encode([
                'duration' => 45,
                'hero_selection_time' => 30,
                'strategy_discussion_time' => 15
            ])
        ];

        $matchId = DB::table('matches')->insertGetId(array_merge($matchData, [
            'created_at' => now(),
            'updated_at' => now()
        ]));

        // Create round records for BO3/BO5
        $maxRounds = $validated['match_format'] === 'BO1' ? 1 : 
                    ($validated['match_format'] === 'BO3' ? 3 : 5);

        for ($i = 1; $i <= $maxRounds; $i++) {
            $mapIndex = ($i - 1) % count($validated['map_pool']);
            $mapData = $validated['map_pool'][$mapIndex];
            
            DB::table('match_rounds')->insert([
                'match_id' => $matchId,
                'round_number' => $i,
                'map_name' => $mapData['map_name'],
                'game_mode' => $mapData['game_mode'],
                'status' => $i === 1 ? 'upcoming' : 'upcoming',
                'team1_score' => 0,
                'team2_score' => 0,
                'round_duration' => 0,
                'overtime_used' => false,
                'team1_composition' => json_encode([]),
                'team2_composition' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        DB::commit();

        // Return complete match data
        $match = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->select([
                'm.*',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                'e.name as event_name'
            ])
            ->where('m.id', $matchId)
            ->first();

        $rounds = DB::table('match_rounds')->where('match_id', $matchId)->get();

        return response()->json([
            'success' => true,
            'message' => 'Competitive match created successfully',
            'data' => [
                'match' => $match,
                'rounds' => $rounds,
                'competitive_ready' => true
            ]
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error creating match: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// 2. REAL-TIME TIMER MANAGEMENT SYSTEM
// ==========================================

Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/api/admin/matches/{id}/timer/{action}', function (Request $request, $id, $action) {
    try {
        $match = DB::table('matches')->where('id', $id)->first();
        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        $currentRound = DB::table('match_rounds')
            ->where('match_id', $id)
            ->where('round_number', $match->current_round)
            ->first();

        DB::beginTransaction();

        switch ($action) {
            case 'start-preparation':
                $validator = Validator::make($request->all(), [
                    'duration_seconds' => 'nullable|integer|min:15|max:300',
                    'phase' => 'nullable|in:hero_selection,tactical_break,map_transition'
                ]);
                
                if ($validator->fails()) {
                    return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
                }

                $duration = $request->input('duration_seconds', 45);
                $phase = $request->input('phase', 'hero_selection');

                $timerId = DB::table('competitive_timers')->insertGetId([
                    'match_id' => $id,
                    'round_id' => $currentRound ? $currentRound->id : null,
                    'timer_type' => 'preparation',
                    'duration_seconds' => $duration,
                    'remaining_seconds' => $duration,
                    'status' => 'running',
                    'timer_config' => json_encode(['phase' => $phase]),
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                DB::table('matches')->where('id', $id)->update([
                    'status' => 'live',
                    'preparation_phase' => json_encode([
                        'active' => true,
                        'phase' => $phase,
                        'duration' => $duration,
                        'timer_id' => $timerId
                    ]),
                    'updated_at' => now()
                ]);

                $response = [
                    'timer_started' => true,
                    'type' => 'preparation',
                    'phase' => $phase,
                    'duration' => $duration,
                    'timer_id' => $timerId
                ];
                break;

            case 'start-match':
                $validator = Validator::make($request->all(), [
                    'duration_seconds' => 'nullable|integer|min:300|max:1200'
                ]);

                if ($validator->fails()) {
                    return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
                }

                // Get game mode specific timer
                $gameMode = $currentRound ? $currentRound->game_mode : 'Domination';
                $defaultDurations = [
                    'Domination' => 600,
                    'Convoy' => 480,
                    'Convergence' => 600,
                    'Conquest' => 420,
                    'Doom Match' => 600
                ];

                $duration = $request->input('duration_seconds', $defaultDurations[$gameMode] ?? 600);

                $timerId = DB::table('competitive_timers')->insertGetId([
                    'match_id' => $id,
                    'round_id' => $currentRound ? $currentRound->id : null,
                    'timer_type' => 'match',
                    'duration_seconds' => $duration,
                    'remaining_seconds' => $duration,
                    'status' => 'running',
                    'timer_config' => json_encode(['game_mode' => $gameMode]),
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                DB::table('matches')->where('id', $id)->update([
                    'status' => 'live',
                    'updated_at' => now()
                ]);

                if ($currentRound) {
                    DB::table('match_rounds')->where('id', $currentRound->id)->update([
                        'status' => 'live',
                        'started_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $response = [
                    'match_timer_started' => true,
                    'game_mode' => $gameMode,
                    'duration' => $duration,
                    'timer_id' => $timerId
                ];
                break;

            case 'pause':
                DB::table('competitive_timers')
                    ->where('match_id', $id)
                    ->where('status', 'running')
                    ->update([
                        'status' => 'paused',
                        'paused_at' => now(),
                        'updated_at' => now()
                    ]);

                DB::table('matches')->where('id', $id)->update([
                    'status' => 'paused',
                    'updated_at' => now()
                ]);

                $response = ['match_paused' => true];
                break;

            case 'resume':
                DB::table('competitive_timers')
                    ->where('match_id', $id)
                    ->where('status', 'paused')
                    ->update([
                        'status' => 'running',
                        'paused_at' => null,
                        'updated_at' => now()
                    ]);

                DB::table('matches')->where('id', $id)->update([
                    'status' => 'live',
                    'updated_at' => now()
                ]);

                $response = ['match_resumed' => true];
                break;

            case 'overtime':
                $gracePeriod = $request->input('grace_period_ms', 500);
                $extendedDuration = $request->input('extended_duration', 180);

                $timerId = DB::table('competitive_timers')->insertGetId([
                    'match_id' => $id,
                    'round_id' => $currentRound ? $currentRound->id : null,
                    'timer_type' => 'overtime',
                    'duration_seconds' => $extendedDuration,
                    'remaining_seconds' => $extendedDuration,
                    'status' => 'running',
                    'timer_config' => json_encode([
                        'grace_period_ms' => $gracePeriod,
                        'trigger' => 'objective_contested'
                    ]),
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                if ($currentRound) {
                    DB::table('match_rounds')->where('id', $currentRound->id)->update([
                        'overtime_used' => true,
                        'updated_at' => now()
                    ]);
                }

                $response = [
                    'overtime_started' => true,
                    'grace_period_ms' => $gracePeriod,
                    'duration' => $extendedDuration,
                    'timer_id' => $timerId
                ];
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Invalid timer action'], 400);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'data' => $response,
            'timestamp' => now()->toISOString()
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Timer error: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// 3. 6V6 HERO COMPOSITION MANAGEMENT
// ==========================================

Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/api/admin/matches/{id}/team-composition', function (Request $request, $id) {
    try {
        $validator = Validator::make($request->all(), [
            'round_number' => 'required|integer|min:1',
            'team1_composition' => 'nullable|array|max:6',
            'team1_composition.*.player_id' => 'required|exists:players,id',
            'team1_composition.*.hero' => 'required|string',
            'team1_composition.*.role' => 'required|in:Vanguard,Duelist,Strategist',
            'team2_composition' => 'nullable|array|max:6',
            'team2_composition.*.player_id' => 'required|exists:players,id',
            'team2_composition.*.hero' => 'required|string',
            'team2_composition.*.role' => 'required|in:Vanguard,Duelist,Strategist'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        // Validate 6v6 composition rules
        foreach (['team1_composition', 'team2_composition'] as $teamKey) {
            if (isset($validated[$teamKey]) && count($validated[$teamKey]) > 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum 6 players per team (6v6 format)'
                ], 422);
            }
        }

        // Update round composition
        $updateData = [];
        if (isset($validated['team1_composition'])) {
            $updateData['team1_composition'] = json_encode($validated['team1_composition']);
        }
        if (isset($validated['team2_composition'])) {
            $updateData['team2_composition'] = json_encode($validated['team2_composition']);
        }
        $updateData['updated_at'] = now();

        $affected = DB::table('match_rounds')
            ->where('match_id', $id)
            ->where('round_number', $validated['round_number'])
            ->update($updateData);

        if ($affected === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Round not found'
            ], 404);
        }

        // Create/update player stats records for this round
        $round = DB::table('match_rounds')
            ->where('match_id', $id)
            ->where('round_number', $validated['round_number'])
            ->first();

        foreach (['team1_composition', 'team2_composition'] as $teamKey) {
            if (isset($validated[$teamKey])) {
                foreach ($validated[$teamKey] as $playerData) {
                    DB::table('player_match_stats')->updateOrInsert(
                        [
                            'player_id' => $playerData['player_id'],
                            'match_id' => $id,
                            'round_id' => $round->id
                        ],
                        [
                            'hero_played' => $playerData['hero'],
                            'role_played' => $playerData['role'],
                            'current_map' => $round->map_name,
                            'updated_at' => now()
                        ]
                    );
                }
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Team compositions updated successfully',
            'data' => [
                'round_number' => $validated['round_number'],
                'compositions_updated' => array_keys(array_intersect_key($validated, array_flip(['team1_composition', 'team2_composition'])))
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Composition update error: ' . $e->getMessage()
        ], 500);
    }
});

// ==========================================
// 4. ROUND TRANSITION AND MAP MANAGEMENT
// ==========================================

Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/api/admin/matches/{id}/round-transition', function (Request $request, $id) {
    try {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:complete_round,start_next_round,complete_match',
            'winner_team_id' => 'nullable|exists:teams,id',
            'round_scores' => 'nullable|array',
            'round_scores.team1' => 'nullable|integer|min:0',
            'round_scores.team2' => 'nullable|integer|min:0'
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

        switch ($validated['action']) {
            case 'complete_round':
                $currentRound = DB::table('match_rounds')
                    ->where('match_id', $id)
                    ->where('round_number', $match->current_round)
                    ->first();

                if (!$currentRound) {
                    return response()->json(['success' => false, 'message' => 'Current round not found'], 404);
                }

                // Complete current round
                $roundUpdateData = [
                    'status' => 'completed',
                    'completed_at' => now(),
                    'winner_team_id' => $validated['winner_team_id'],
                    'updated_at' => now()
                ];

                if (isset($validated['round_scores'])) {
                    $roundUpdateData['team1_score'] = $validated['round_scores']['team1'] ?? 0;
                    $roundUpdateData['team2_score'] = $validated['round_scores']['team2'] ?? 0;
                }

                DB::table('match_rounds')->where('id', $currentRound->id)->update($roundUpdateData);

                // Update match scores (series wins)
                $team1Wins = DB::table('match_rounds')
                    ->where('match_id', $id)
                    ->where('winner_team_id', $match->team1_id)
                    ->count();

                $team2Wins = DB::table('match_rounds')
                    ->where('match_id', $id)
                    ->where('winner_team_id', $match->team2_id)
                    ->count();

                DB::table('matches')->where('id', $id)->update([
                    'team1_score' => $team1Wins,
                    'team2_score' => $team2Wins,
                    'updated_at' => now()
                ]);

                $response = [
                    'round_completed' => true,
                    'round_number' => $currentRound->round_number,
                    'winner_team_id' => $validated['winner_team_id'],
                    'series_score' => [$team1Wins, $team2Wins]
                ];
                break;

            case 'start_next_round':
                $nextRoundNumber = $match->current_round + 1;
                $nextRound = DB::table('match_rounds')
                    ->where('match_id', $id)
                    ->where('round_number', $nextRoundNumber)
                    ->first();

                if (!$nextRound) {
                    return response()->json(['success' => false, 'message' => 'No next round available'], 404);
                }

                // Update match to next round
                DB::table('matches')->where('id', $id)->update([
                    'current_round' => $nextRoundNumber,
                    'current_map' => $nextRound->map_name,
                    'current_mode' => $nextRound->game_mode,
                    'status' => 'live',
                    'updated_at' => now()
                ]);

                // Set next round as upcoming
                DB::table('match_rounds')->where('id', $nextRound->id)->update([
                    'status' => 'upcoming',
                    'updated_at' => now()
                ]);

                $response = [
                    'next_round_started' => true,
                    'round_number' => $nextRoundNumber,
                    'map_name' => $nextRound->map_name,
                    'game_mode' => $nextRound->game_mode
                ];
                break;

            case 'complete_match':
                // Check series winner
                $team1Wins = DB::table('match_rounds')
                    ->where('match_id', $id)
                    ->where('winner_team_id', $match->team1_id)
                    ->count();

                $team2Wins = DB::table('match_rounds')
                    ->where('match_id', $id)
                    ->where('winner_team_id', $match->team2_id)
                    ->count();

                $seriesWinnerId = $team1Wins > $team2Wins ? $match->team1_id : $match->team2_id;

                DB::table('matches')->where('id', $id)->update([
                    'status' => 'completed',
                    'series_completed' => true,
                    'series_winner_id' => $seriesWinnerId,
                    'team1_score' => $team1Wins,
                    'team2_score' => $team2Wins,
                    'updated_at' => now()
                ]);

                // Archive match history
                $this->archiveMatchToHistory($id, $seriesWinnerId);

                $response = [
                    'match_completed' => true,
                    'series_winner_id' => $seriesWinnerId,
                    'final_score' => [$team1Wins, $team2Wins],
                    'archived_to_history' => true
                ];
                break;
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'data' => $response
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Round transition error: ' . $e->getMessage()
        ], 500);
    }
});

// Helper function for match history archival
function archiveMatchToHistory($matchId, $winnerId) {
    $match = DB::table('matches')->where('id', $matchId)->first();
    $rounds = DB::table('match_rounds')->where('match_id', $matchId)->get();
    
    // Archive team results
    foreach ([$match->team1_id, $match->team2_id] as $teamId) {
        $result = $teamId === $winnerId ? 'win' : 'loss';
        
        DB::table('match_history')->insert([
            'match_id' => $matchId,
            'team_id' => $teamId,
            'result' => $result,
            'performance_data' => json_encode([
                'rounds_won' => DB::table('match_rounds')
                    ->where('match_id', $matchId)
                    ->where('winner_team_id', $teamId)
                    ->count(),
                'total_rounds' => $rounds->count()
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    // Archive player statistics
    $playerStats = DB::table('player_match_stats')->where('match_id', $matchId)->get();
    foreach ($playerStats as $stats) {
        $player = DB::table('players')->where('id', $stats->player_id)->first();
        $result = $player->team_id === $winnerId ? 'win' : 'loss';
        
        // Calculate performance rating
        $performanceRating = ($stats->eliminations * 1.0) + ($stats->assists * 0.5) - ($stats->deaths * 0.5) + 
                           ($stats->damage / 100) + ($stats->healing / 50) + ($stats->objective_time / 10);
        
        DB::table('match_history')->insert([
            'match_id' => $matchId,
            'team_id' => $player->team_id,
            'player_id' => $stats->player_id,
            'result' => $result,
            'performance_data' => json_encode([
                'eliminations' => $stats->eliminations,
                'deaths' => $stats->deaths,
                'assists' => $stats->assists,
                'damage' => $stats->damage,
                'healing' => $stats->healing,
                'hero_played' => $stats->hero_played,
                'role_played' => $stats->role_played
            ]),
            'performance_rating' => $performanceRating,
            'mvp' => false, // Will be calculated separately
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}

// ==========================================
// 5. REAL-TIME PLAYER STATISTICS UPDATE
// ==========================================

Route::middleware(['auth:sanctum', 'role:admin|moderator'])->put('/api/admin/matches/{matchId}/player/{playerId}/stats', function (Request $request, $matchId, $playerId) {
    try {
        $validator = Validator::make($request->all(), [
            'round_number' => 'nullable|integer|min:1',
            'eliminations' => 'nullable|integer|min:0',
            'deaths' => 'nullable|integer|min:0',
            'assists' => 'nullable|integer|min:0',
            'damage' => 'nullable|integer|min:0',
            'healing' => 'nullable|integer|min:0',
            'damage_blocked' => 'nullable|integer|min:0',
            'ultimate_usage' => 'nullable|integer|min:0',
            'objective_time' => 'nullable|integer|min:0',
            'final_blows' => 'nullable|integer|min:0',
            'environmental_kills' => 'nullable|integer|min:0',
            'accuracy_percentage' => 'nullable|numeric|min:0|max:100',
            'critical_hits' => 'nullable|integer|min:0',
            'hero_played' => 'nullable|string',
            'role_played' => 'nullable|in:Vanguard,Duelist,Strategist'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        
        // Get current match and round info
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

        // Prepare update data
        $updateData = array_filter($validated, function($value) {
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
            $statsId = $existingStats->id;
        } else {
            $insertData = array_merge([
                'player_id' => $playerId,
                'match_id' => $matchId,
                'round_id' => $round->id,
                'current_map' => $round->map_name,
                'created_at' => now()
            ], $updateData);
            
            $statsId = DB::table('player_match_stats')->insertGetId($insertData);
        }

        // Create live event for significant actions
        if (isset($validated['eliminations']) || isset($validated['deaths'])) {
            DB::table('live_events')->insert([
                'match_id' => $matchId,
                'round_id' => $round->id,
                'event_type' => isset($validated['eliminations']) ? 'elimination' : 'death',
                'player_id' => $playerId,
                'hero_involved' => $validated['hero_played'] ?? null,
                'event_data' => json_encode($validated),
                'event_timestamp' => now(),
                'match_time_seconds' => 0, // Would be calculated from timer
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        DB::commit();

        // Return updated stats
        $updatedStats = DB::table('player_match_stats as pms')
            ->leftJoin('players as p', 'pms.player_id', '=', 'p.id')
            ->select([
                'pms.*',
                'p.name as player_name', 'p.username as player_username'
            ])
            ->where('pms.id', $statsId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Player statistics updated successfully',
            'data' => $updatedStats
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Stats update error: ' . $e->getMessage()
        ], 500);
    }
});