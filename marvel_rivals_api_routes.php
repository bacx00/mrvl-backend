<?php

// COMPLETE MARVEL RIVALS API ROUTES
// Add these to your routes/api.php file

// ==========================================
// ENHANCED MATCH MANAGEMENT
// ==========================================

// Create match with advanced options
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/matches', function (Request $request) {
    try {
        $matchId = DB::table('matches')->insertGetId([
            'team1_id' => $request->team1_id,
            'team2_id' => $request->team2_id,
            'event_id' => $request->event_id,
            'scheduled_at' => $request->scheduled_at,
            'format' => $request->format ?? 'BO3',
            'status' => $request->status ?? 'scheduled',
            'viewers' => 0,
            'peak_viewers' => 0,
            'team1_score' => 0,
            'team2_score' => 0,
            'current_map' => 1,
            'maps_data' => json_encode([]),
            'maps' => json_encode([]),
            'broadcast' => json_encode([
                'platform' => $request->broadcast_platform ?? 'Twitch',
                'channel' => $request->broadcast_channel ?? 'MarvelRivals_Official',
                'language' => $request->broadcast_language ?? 'en'
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'match_id' => $matchId,
            'message' => 'Match created successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Enhanced match update with live scoring
Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/matches/{id}', function (Request $request, $id) {
    try {
        $updateData = [];
        
        // Standard match fields
        if ($request->has('status')) $updateData['status'] = $request->status;
        if ($request->has('team1_score')) $updateData['team1_score'] = $request->team1_score;
        if ($request->has('team2_score')) $updateData['team2_score'] = $request->team2_score;
        if ($request->has('viewers')) $updateData['viewers'] = $request->viewers;
        if ($request->has('peak_viewers')) $updateData['peak_viewers'] = $request->peak_viewers;
        if ($request->has('current_map')) $updateData['current_map'] = $request->current_map;
        if ($request->has('started_at')) $updateData['started_at'] = $request->started_at;
        if ($request->has('completed_at')) $updateData['completed_at'] = $request->completed_at;
        
        // Complex JSON fields
        if ($request->has('maps_data')) {
            $updateData['maps_data'] = is_string($request->maps_data) 
                ? $request->maps_data 
                : json_encode($request->maps_data);
        }
        
        if ($request->has('maps')) {
            $updateData['maps'] = is_string($request->maps) 
                ? $request->maps 
                : json_encode($request->maps);
        }

        $updateData['updated_at'] = now();

        $affected = DB::table('matches')
            ->where('id', $id)
            ->update($updateData);

        if ($affected === 0) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        // Return updated match data
        $match = DB::table('matches')
            ->leftJoin('teams as t1', 'matches.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'matches.team2_id', '=', 't2.id')
            ->select(
                'matches.*',
                't1.name as team1_name',
                't2.name as team2_name'
            )
            ->where('matches.id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Match updated successfully',
            'match' => $match
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Live scoring system - granular player updates
Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/matches/{id}/live-scoring', function (Request $request, $id) {
    try {
        $match = DB::table('matches')->where('id', $id)->first();
        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        $mapsData = json_decode($match->maps_data, true) ?? [];
        $mapNumber = $request->map_number ?? 1;
        $playerUpdates = $request->player_updates ?? [];

        // Find the map to update
        $mapIndex = -1;
        foreach ($mapsData as $index => $map) {
            if ($map['map_number'] == $mapNumber) {
                $mapIndex = $index;
                break;
            }
        }

        if ($mapIndex === -1) {
            return response()->json(['success' => false, 'message' => 'Map not found'], 404);
        }

        // Update player stats
        foreach ($playerUpdates as $playerUpdate) {
            $playerId = $playerUpdate['player_id'];
            
            // Update in team1_composition
            if (isset($mapsData[$mapIndex]['team1_composition'])) {
                foreach ($mapsData[$mapIndex]['team1_composition'] as &$player) {
                    if ($player['player_id'] == $playerId) {
                        foreach ($playerUpdate as $stat => $value) {
                            if ($stat !== 'player_id') {
                                $player[$stat] = $value;
                            }
                        }
                        break;
                    }
                }
            }

            // Update in team2_composition
            if (isset($mapsData[$mapIndex]['team2_composition'])) {
                foreach ($mapsData[$mapIndex]['team2_composition'] as &$player) {
                    if ($player['player_id'] == $playerId) {
                        foreach ($playerUpdate as $stat => $value) {
                            if ($stat !== 'player_id') {
                                $player[$stat] = $value;
                            }
                        }
                        break;
                    }
                }
            }
        }

        // Update map scores if provided
        if ($request->has('team1_score')) {
            $mapsData[$mapIndex]['team1_score'] = $request->team1_score;
        }
        if ($request->has('team2_score')) {
            $mapsData[$mapIndex]['team2_score'] = $request->team2_score;
        }

        // Update match
        $updateData = [
            'maps_data' => json_encode($mapsData),
            'updated_at' => now()
        ];

        if ($request->has('viewers')) $updateData['viewers'] = $request->viewers;
        if ($request->has('peak_viewers')) $updateData['peak_viewers'] = $request->peak_viewers;

        DB::table('matches')->where('id', $id)->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Live scoring updated successfully',
            'updated_map' => $mapsData[$mapIndex]
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ==========================================
// TEAM & PLAYER MANAGEMENT
// ==========================================

// Create team
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/teams', function (Request $request) {
    try {
        $teamId = DB::table('teams')->insertGetId([
            'name' => $request->name,
            'country' => $request->country ?? 'US',
            'logo' => $request->logo,
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'team_id' => $teamId,
            'message' => 'Team created successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Create player
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/players', function (Request $request) {
    try {
        $playerId = DB::table('players')->insertGetId([
            'name' => $request->name,
            'team_id' => $request->team_id,
            'role' => $request->role,
            'country' => $request->country ?? 'US',
            'nationality' => $request->country ?? 'US',
            'team_country' => $request->country ?? 'US',
            'age' => $request->age,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'player_id' => $playerId,
            'message' => 'Player created successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ==========================================
// EVENT MANAGEMENT
// ==========================================

// Create event/tournament
Route::middleware(['auth:sanctum', 'role:admin'])->post('/admin/events', function (Request $request) {
    try {
        $eventId = DB::table('events')->insertGetId([
            'name' => $request->name,
            'type' => $request->type ?? 'tournament',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'location' => $request->location,
            'prize_pool' => $request->prize_pool ?? 0,
            'description' => $request->description,
            'status' => $request->status ?? 'upcoming',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'event_id' => $eventId,
            'message' => 'Event created successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ==========================================
// REAL-TIME MATCH DATA
// ==========================================

// Get complete match data with all details
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/matches/{id}/complete', function ($id) {
    try {
        $match = DB::table('matches')
            ->leftJoin('teams as t1', 'matches.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'matches.team2_id', '=', 't2.id')
            ->leftJoin('events', 'matches.event_id', '=', 'events.id')
            ->select(
                'matches.*',
                't1.name as team1_name', 't1.logo as team1_logo', 't1.country as team1_country',
                't2.name as team2_name', 't2.logo as team2_logo', 't2.country as team2_country',
                'events.name as event_name', 'events.type as event_type'
            )
            ->where('matches.id', $id)
            ->first();

        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        // Parse JSON fields
        $match->maps_data = json_decode($match->maps_data, true) ?? [];
        $match->maps = json_decode($match->maps, true) ?? [];
        $match->broadcast = json_decode($match->broadcast, true) ?? [];

        // Add player details to compositions
        foreach ($match->maps_data as &$map) {
            if (isset($map['team1_composition'])) {
                foreach ($map['team1_composition'] as &$player) {
                    $playerDetails = DB::table('players')
                        ->where('id', $player['player_id'])
                        ->first();
                    if ($playerDetails) {
                        $player['country'] = $playerDetails->country ?? 'US';
                        $player['nationality'] = $playerDetails->nationality ?? 'US';
                    }
                }
            }

            if (isset($map['team2_composition'])) {
                foreach ($map['team2_composition'] as &$player) {
                    $playerDetails = DB::table('players')
                        ->where('id', $player['player_id'])
                        ->first();
                    if ($playerDetails) {
                        $player['country'] = $playerDetails->country ?? 'US';
                        $player['nationality'] = $playerDetails->nationality ?? 'US';
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'match' => $match
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Get live match status (for real-time updates)
Route::get('/api/matches/{id}/live', function ($id) {
    try {
        $match = DB::table('matches')
            ->select('id', 'status', 'team1_score', 'team2_score', 'viewers', 'current_map', 'updated_at')
            ->where('id', $id)
            ->first();

        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Match not found'], 404);
        }

        return response()->json([
            'success' => true,
            'live_data' => [
                'status' => $match->status,
                'team1_score' => $match->team1_score,
                'team2_score' => $match->team2_score,
                'viewers' => $match->viewers,
                'current_map' => $match->current_map,
                'last_update' => $match->updated_at
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ==========================================
// ADVANCED SEARCH & FILTERING
// ==========================================

// Advanced match search
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/matches/search', function (Request $request) {
    try {
        $query = DB::table('matches')
            ->leftJoin('teams as t1', 'matches.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'matches.team2_id', '=', 't2.id')
            ->leftJoin('events', 'matches.event_id', '=', 'events.id')
            ->select(
                'matches.*',
                't1.name as team1_name',
                't2.name as team2_name',
                'events.name as event_name'
            );

        // Apply filters
        if ($request->has('team1_id')) {
            $query->where('matches.team1_id', $request->team1_id);
        }

        if ($request->has('team2_id')) {
            $query->where('matches.team2_id', $request->team2_id);
        }

        if ($request->has('event_id')) {
            $query->where('matches.event_id', $request->event_id);
        }

        if ($request->has('status')) {
            $query->where('matches.status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->where('matches.scheduled_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('matches.scheduled_at', '<=', $request->end_date);
        }

        if ($request->has('format')) {
            $query->where('matches.format', $request->format);
        }

        // Search by team name
        if ($request->has('team_name')) {
            $teamName = $request->team_name;
            $query->where(function($q) use ($teamName) {
                $q->where('t1.name', 'LIKE', "%{$teamName}%")
                  ->orWhere('t2.name', 'LIKE', "%{$teamName}%");
            });
        }

        $matches = $query->orderBy('matches.scheduled_at', 'desc')
            ->limit($request->limit ?? 50)
            ->get();

        return response()->json([
            'success' => true,
            'matches' => $matches,
            'total' => $matches->count()
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ==========================================
// BULK OPERATIONS
// ==========================================

// Bulk update matches
Route::middleware(['auth:sanctum', 'role:admin'])->put('/admin/matches/bulk-update', function (Request $request) {
    try {
        $matchIds = $request->match_ids ?? [];
        $updateData = $request->update_data ?? [];

        if (empty($matchIds) || empty($updateData)) {
            return response()->json(['success' => false, 'message' => 'Match IDs and update data required'], 400);
        }

        $updateData['updated_at'] = now();

        $affected = DB::table('matches')
            ->whereIn('id', $matchIds)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Updated {$affected} matches",
            'affected_matches' => $affected
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// ==========================================
// HERO & GAME DATA
// ==========================================

// Get Marvel Rivals heroes data
Route::get('/api/game-data/heroes', function () {
    $heroes = [
        // Vanguard (Tanks)
        ['name' => 'Doctor Strange', 'role' => 'Vanguard', 'type' => 'Tank', 'image' => '/heroes/doctor-strange.webp'],
        ['name' => 'Groot', 'role' => 'Vanguard', 'type' => 'Tank', 'image' => '/heroes/groot.webp'],
        ['name' => 'Hulk', 'role' => 'Vanguard', 'type' => 'Tank', 'image' => '/heroes/hulk.webp'],
        ['name' => 'Magneto', 'role' => 'Vanguard', 'type' => 'Tank', 'image' => '/heroes/magneto.webp'],
        ['name' => 'Peni Parker', 'role' => 'Vanguard', 'type' => 'Tank', 'image' => '/heroes/peni-parker.webp'],
        ['name' => 'Thor', 'role' => 'Vanguard', 'type' => 'Tank', 'image' => '/heroes/thor.webp'],
        ['name' => 'Venom', 'role' => 'Vanguard', 'type' => 'Tank', 'image' => '/heroes/venom.webp'],
        ['name' => 'Captain America', 'role' => 'Vanguard', 'type' => 'Tank', 'image' => '/heroes/captain-america.webp'],

        // Duelist (DPS)
        ['name' => 'Black Panther', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/black-panther.webp'],
        ['name' => 'Hawkeye', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/hawkeye.webp'],
        ['name' => 'Hela', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/hela.webp'],
        ['name' => 'Iron Man', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/iron-man.webp'],
        ['name' => 'Magik', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/magik.webp'],
        ['name' => 'Namor', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/namor.webp'],
        ['name' => 'Psylocke', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/psylocke.webp'],
        ['name' => 'Punisher', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/punisher.webp'],
        ['name' => 'Scarlet Witch', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/scarlet-witch.webp'],
        ['name' => 'Spider-Man', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/spider-man.webp'],
        ['name' => 'Star-Lord', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/star-lord.webp'],
        ['name' => 'Storm', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/storm.webp'],
        ['name' => 'Winter Soldier', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/winter-soldier.webp'],
        ['name' => 'Wolverine', 'role' => 'Duelist', 'type' => 'DPS', 'image' => '/heroes/wolverine.webp'],

        // Strategist (Support)
        ['name' => 'Adam Warlock', 'role' => 'Strategist', 'type' => 'Support', 'image' => '/heroes/adam-warlock.webp'],
        ['name' => 'Cloak & Dagger', 'role' => 'Strategist', 'type' => 'Support', 'image' => '/heroes/cloak-dagger.webp'],
        ['name' => 'Jeff the Land Shark', 'role' => 'Strategist', 'type' => 'Support', 'image' => '/heroes/jeff.webp'],
        ['name' => 'Loki', 'role' => 'Strategist', 'type' => 'Support', 'image' => '/heroes/loki.webp'],
        ['name' => 'Luna Snow', 'role' => 'Strategist', 'type' => 'Support', 'image' => '/heroes/luna-snow.webp'],
        ['name' => 'Mantis', 'role' => 'Strategist', 'type' => 'Support', 'image' => '/heroes/mantis.webp'],
        ['name' => 'Rocket Raccoon', 'role' => 'Strategist', 'type' => 'Support', 'image' => '/heroes/rocket-raccoon.webp']
    ];

    return response()->json(['success' => true, 'heroes' => $heroes]);
});

// Get Marvel Rivals maps data
Route::get('/api/game-data/maps', function () {
    $maps = [
        ['name' => 'Asgard: Royal Palace', 'modes' => ['Domination', 'Convergence'], 'image' => '/maps/asgard-royal-palace.jpg'],
        ['name' => 'Birnin Zana: Golden City', 'modes' => ['Escort', 'Convergence'], 'image' => '/maps/birnin-zana.jpg'],
        ['name' => 'Klyntar: Symbiote Research Station', 'modes' => ['Convoy', 'Domination'], 'image' => '/maps/klyntar.jpg'],
        ['name' => 'Midtown: Times Square', 'modes' => ['Domination', 'Escort'], 'image' => '/maps/midtown.jpg'],
        ['name' => 'Moon Base: Artiluna-1', 'modes' => ['Convoy', 'Convergence'], 'image' => '/maps/moon-base.jpg'],
        ['name' => 'Sanctum Sanctorum', 'modes' => ['Domination', 'Escort'], 'image' => '/maps/sanctum.jpg'],
        ['name' => 'Throne Room of Asgard', 'modes' => ['Convergence', 'Convoy'], 'image' => '/maps/throne-room.jpg'],
        ['name' => 'Tokyo 2099: Spider Islands', 'modes' => ['Escort', 'Domination'], 'image' => '/maps/tokyo-2099.jpg'],
        ['name' => 'Wakanda', 'modes' => ['Convoy', 'Convergence'], 'image' => '/maps/wakanda.jpg'],
        ['name' => 'Yggsgard: Seed of the World Tree', 'modes' => ['Domination', 'Escort'], 'image' => '/maps/yggsgard.jpg']
    ];

    return response()->json(['success' => true, 'maps' => $maps]);
});

?>