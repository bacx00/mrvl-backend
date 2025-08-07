<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Mention;
use App\Models\MatchModel;
use App\Models\MvrlMatch;
use App\Events\MatchHeroUpdated;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.*',
                    't1.id as team1_id', 't1.name as team1_name', 't1.short_name as team1_short',
                    't1.logo as team1_logo', 't1.region as team1_region', 't1.rating as team1_rating',
                    't2.id as team2_id', 't2.name as team2_name', 't2.short_name as team2_short', 
                    't2.logo as team2_logo', 't2.region as team2_region', 't2.rating as team2_rating',
                    'e.id as event_id', 'e.name as event_name', 'e.type as event_type',
                    'e.logo as event_logo', 'e.region as event_region'
                ]);

            // Status filter
            if ($request->status && $request->status !== 'all') {
                $query->where('m.status', $request->status);
            }

            // Date filter
            if ($request->date) {
                $query->whereDate('m.scheduled_at', $request->date);
            }

            // Event filter
            if ($request->event_id) {
                $query->where('m.event_id', $request->event_id);
            }

            $matches = $query->orderBy('m.scheduled_at', 'desc')->paginate(20);

            // Transform matches with VLR.gg style
            $formattedMatches = collect($matches->items())->map(function($match) {
                return $this->formatMatchData($match);
            });

            // If no matches, return empty array
            if ($formattedMatches->isEmpty()) {
                $formattedMatches = collect([]);
            }

            return response()->json([
                'data' => $formattedMatches,
                'pagination' => [
                    'current_page' => $matches->currentPage(),
                    'last_page' => $matches->lastPage(),
                    'per_page' => $matches->perPage(),
                    'total' => $matches->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            \Log::error('MatchController@index error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching matches: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function show($matchId)
    {
        try {
            $match = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->where('m.id', $matchId)
                ->select([
                    'm.*',
                    't1.id as team1_id', 't1.name as team1_name', 't1.short_name as team1_short',
                    't1.logo as team1_logo', 't1.region as team1_region', 't1.rating as team1_rating',
                    't2.id as team2_id', 't2.name as team2_name', 't2.short_name as team2_short', 
                    't2.logo as team2_logo', 't2.region as team2_region', 't2.rating as team2_rating',
                    'e.id as event_id', 'e.name as event_name', 'e.type as event_type',
                    'e.logo as event_logo', 'e.format as event_format'
                ])
                ->first();

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Get complete match data including players, stats, maps
            $matchData = $this->getCompleteMatchData($match);

            // Get comments with user flairs and avatars
            $comments = $this->getMatchComments($matchId);

            // Increment view count
            DB::table('matches')->where('id', $matchId)->increment('viewers');

            // Return data in the format expected by frontend
            return response()->json(array_merge(
                ['success' => true],
                ['data' => $matchData],
                $matchData,
                ['comments' => $comments]
            ));

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching match: ' . $e->getMessage()
            ], 500);
        }
    }

    public function live(Request $request)
    {
        try {
            $liveMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->where('m.status', 'live')
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't1.region as team1_region', 't1.rating as team1_rating',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    't2.region as team2_region', 't2.rating as team2_rating',
                    'e.name as event_name', 'e.logo as event_logo'
                ])
                ->orderBy('m.viewers', 'desc')
                ->limit(20)
                ->get();

            $formattedMatches = $liveMatches->map(function($match) {
                return $this->formatMatchData($match, true);
            });

            return response()->json([
                'data' => $formattedMatches,
                'total' => $formattedMatches->count(),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching live matches: ' . $e->getMessage()
            ], 500);
        }
    }

    // Admin/Moderator Functions
    public function store(Request $request)
    {
        // Temporarily disable authorization check
        // $this->authorize('create-matches');
        
        $request->validate([
            'team1_id' => 'required|exists:teams,id',
            'team2_id' => 'required|exists:teams,id|different:team1_id',
            'event_id' => 'nullable|exists:events,id',
            'scheduled_at' => 'required|date',
            'format' => 'required|in:BO1,BO3,BO5,BO7,BO9',
            'maps_data' => 'required|array|min:1',
            'maps_data.*.map_name' => 'required|string',
            'maps_data.*.mode' => 'required|string',
            
            // Multiple URLs support
            'stream_urls' => 'nullable|array',
            'stream_urls.*' => 'url',
            'betting_urls' => 'nullable|array',
            'betting_urls.*' => 'url',
            'vod_urls' => 'nullable|array',
            'vod_urls.*' => 'url',
            
            // Tournament context
            'round' => 'nullable|string',
            'bracket_position' => 'nullable|string',
            
            // Administrative
            'allow_past_date' => 'boolean'
        ]);

        try {
            // Validate date for past matches
            if (!$request->allow_past_date && now()->gt($request->scheduled_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot schedule matches in the past unless specifically allowed'
                ], 400);
            }

            // Create match
            $matchId = DB::table('matches')->insertGetId([
                'team1_id' => $request->team1_id,
                'team2_id' => $request->team2_id,
                'event_id' => $request->event_id,
                'scheduled_at' => \Carbon\Carbon::parse($request->scheduled_at)->format('Y-m-d H:i:s'),
                'format' => $request->format,
                'status' => 'upcoming',
                'team1_score' => 0,
                'team2_score' => 0,
                'series_score_team1' => 0,
                'series_score_team2' => 0,
                'current_map_number' => 1,
                'viewers' => 0,
                
                // Multiple URLs support
                'stream_urls' => json_encode($request->stream_urls ?? []),
                'betting_urls' => json_encode($request->betting_urls ?? []),
                'vod_urls' => json_encode($request->vod_urls ?? []),
                
                // Tournament context
                'round' => $request->round ?: 'Regular Season',
                'bracket_position' => $request->bracket_position ?: 1,
                
                // Match data - pass team IDs to initialize with player compositions
                'maps_data' => json_encode($this->initializeMapsData($request->maps_data, $request->format, $request->team1_id, $request->team2_id)),
                'hero_data' => json_encode($this->initializeHeroData($request->format)),
                'live_data' => json_encode($this->initializeLiveData()),
                'player_stats' => json_encode($this->initializePlayerStats($request->team1_id, $request->team2_id)),
                'match_timer' => json_encode(['current_time' => 0, 'phase' => 'preparation']),
                'overtime' => false,
                
                // Administrative
                'created_by' => Auth::id(),
                'allow_past_date' => $request->allow_past_date ?? false,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'data' => ['id' => $matchId],
                'success' => true,
                'message' => 'Match created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating match: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        $request->validate([
            'team1_id' => 'sometimes|exists:teams,id',
            'team2_id' => 'sometimes|exists:teams,id',
            'event_id' => 'nullable|exists:events,id',
            'scheduled_at' => 'sometimes|date',
            'format' => 'sometimes|in:bo1,bo3,bo5,bo7,bo9',
            'maps' => 'sometimes|array|min:1',
            'stream_url' => 'nullable|url',
            'round' => 'nullable|string',
            'bracket_position' => 'nullable|string'
        ]);

        try {
            $updateData = [];
            
            // Only update provided fields
            $fields = ['team1_id', 'team2_id', 'event_id', 'scheduled_at', 'format', 
                      'stream_url', 'round', 'bracket_position'];
            
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }
            
            // Update maps data if provided
            if ($request->has('maps_data')) {
                $updateData['maps_data'] = json_encode($this->initializeMapsData($request->maps_data));
            }
            
            $updateData['updated_at'] = now();
            
            DB::table('matches')
                ->where('id', $matchId)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating match: ' . $e->getMessage()
            ], 500);
        }
    }

    // Live Scoring Functions for Admin/Moderator
    public function setMatchLive(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('moderate-matches');
        }
        
        try {
            DB::table('matches')
                ->where('id', $matchId)
                ->update([
                    'status' => 'live',
                    'started_at' => now(),
                    'updated_at' => now()
                ]);

            // Initialize first map timer
            $this->startMapTimer($matchId, 1);

            return response()->json([
                'success' => true,
                'message' => 'Match is now live'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error setting match live: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateLiveData(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('moderate-matches');
        }
        
        $request->validate([
            'type' => 'required|in:score,timer,map_transition,player_stats,hero_update',
            'data' => 'required|array'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $liveData = json_decode($match->live_data, true);
            $mapsData = json_decode($match->maps_data, true);

            switch ($request->type) {
                case 'score':
                    // Update map score
                    $mapIndex = $request->data['map_index'] ?? ($match->current_map - 1);
                    $mapsData[$mapIndex]['team1_score'] = $request->data['team1_score'];
                    $mapsData[$mapIndex]['team2_score'] = $request->data['team2_score'];
                    $mapsData[$mapIndex]['status'] = $request->data['map_status'] ?? 'ongoing';
                    
                    // Check if map completed
                    if ($request->data['map_status'] === 'completed') {
                        $this->handleMapCompletion($matchId, $mapIndex, $mapsData);
                    }
                    break;

                case 'timer':
                    // Update map timer
                    $liveData['current_map_time'] = $request->data['time'];
                    $liveData['timer_status'] = $request->data['status'] ?? 'running';
                    break;

                case 'map_transition':
                    // Transition to next map
                    $nextMap = $match->current_map + 1;
                    DB::table('matches')->where('id', $matchId)->update([
                        'current_map' => $nextMap
                    ]);
                    $this->startMapTimer($matchId, $nextMap);
                    break;

                case 'player_stats':
                    // Update player stats for current map
                    $this->updatePlayerStats($matchId, $request->data);
                    break;

                case 'hero_update':
                    // Update hero selections for current map
                    $mapIndex = $match->current_map - 1;
                    $mapsData[$mapIndex]['team1_heroes'] = $request->data['team1_heroes'] ?? [];
                    $mapsData[$mapIndex]['team2_heroes'] = $request->data['team2_heroes'] ?? [];
                    break;
            }

            // Update match data
            DB::table('matches')->where('id', $matchId)->update([
                'live_data' => json_encode($liveData),
                'maps_data' => json_encode($mapsData),
                'updated_at' => now()
            ]);

            // Calculate and update overall match score
            $this->updateOverallScore($matchId);

            return response()->json([
                'success' => true,
                'message' => 'Live data updated successfully',
                'data' => [
                    'live_data' => $liveData,
                    'maps_data' => $mapsData
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating live data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateMatchScore(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('moderate-matches');
        }
        
        $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0'
        ]);

        try {
            DB::table('matches')
                ->where('id', $matchId)
                ->update([
                    'team1_score' => $request->team1_score,
                    'team2_score' => $request->team2_score,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Match score updated'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating match score: ' . $e->getMessage()
            ], 500);
        }
    }

    // Pause functionality removed - matches can only be live, upcoming, or completed

    public function restartMatch(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('moderate-matches');
        }
        
        $request->validate([
            'reason' => 'required|string',
            'reset_scores' => 'boolean',
            'reset_stats' => 'boolean'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Reset match data based on options
            $updateData = [
                'status' => 'upcoming',
                'started_at' => null,
                'updated_at' => now()
            ];

            if ($request->reset_scores) {
                $updateData['team1_score'] = 0;
                $updateData['team2_score'] = 0;
                $updateData['series_score_team1'] = 0;
                $updateData['series_score_team2'] = 0;
                $updateData['current_map_number'] = 1;
                
                // Reset maps data
                $mapsData = json_decode($match->maps_data, true) ?? [];
                foreach ($mapsData as &$map) {
                    $map['status'] = 'not_played';
                    $map['team1_score'] = 0;
                    $map['team2_score'] = 0;
                    $map['winner_id'] = null;
                    $map['started_at'] = null;
                    $map['completed_at'] = null;
                }
                $updateData['maps_data'] = json_encode($mapsData);
            }

            if ($request->reset_stats) {
                // Delete existing player stats
                DB::table('player_match_stats')->where('match_id', $matchId)->delete();
                
                // Reset player stats JSON
                $updateData['player_stats'] = json_encode($this->initializePlayerStats($match->team1_id, $match->team2_id));
            }

            // Reset live data
            $updateData['live_data'] = json_encode($this->initializeLiveData());

            DB::table('matches')->where('id', $matchId)->update($updateData);

            // Log restart
            DB::table('match_logs')->insert([
                'match_id' => $matchId,
                'action' => 'restart',
                'reason' => $request->reason,
                'performed_by' => Auth::id(),
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Match restarted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restarting match: ' . $e->getMessage()
            ], 500);
        }
    }

    public function startMatch(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('moderate-matches');
        }
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            if ($match->status !== 'upcoming') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match can only be started from upcoming status'
                ], 400);
            }

            DB::table('matches')->where('id', $matchId)->update([
                'status' => 'live',
                'started_at' => now(),
                'actual_start_time' => now(),
                'current_map_number' => 1,
                'timer_running' => true,
                'updated_at' => now()
            ]);

            // Initialize first map
            $mapsData = json_decode($match->maps_data, true) ?? [];
            if (isset($mapsData[0])) {
                $mapsData[0]['status'] = 'ongoing';
                $mapsData[0]['started_at'] = now();
                
                DB::table('matches')->where('id', $matchId)->update([
                    'maps_data' => json_encode($mapsData)
                ]);
            }

            // Broadcast match started event
            event(new \App\Events\MatchStarted($matchId));

            return response()->json([
                'success' => true,
                'message' => 'Match started successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting match: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteMatch(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Delete related data
            DB::table('player_match_stats')->where('match_id', $matchId)->delete();
            DB::table('match_comments')->where('match_id', $matchId)->delete();
            DB::table('match_logs')->where('match_id', $matchId)->delete();
            
            // Delete the match
            DB::table('matches')->where('id', $matchId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Match deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting match: ' . $e->getMessage()
            ], 500);
        }
    }

    public function completeMatch(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('moderate-matches');
        }
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            
            // Determine winner
            $winner = null;
            if ($match->team1_score > $match->team2_score) {
                $winner = $match->team1_id;
            } elseif ($match->team2_score > $match->team1_score) {
                $winner = $match->team2_id;
            }

            DB::table('matches')
                ->where('id', $matchId)
                ->update([
                    'status' => 'completed',
                    'winner_id' => $winner,
                    'completed_at' => now(),
                    'updated_at' => now()
                ]);

            // Update player hero stats
            $this->updatePlayerHeroStatsForMatch($matchId);

            // Update team ratings if applicable
            if ($winner) {
                $this->updateTeamRatings($match->team1_id, $match->team2_id, $winner);
            }

            // Progress tournament bracket if applicable
            if ($match->event_id && $match->round && $winner) {
                $this->progressTournamentBracket($matchId, $winner);
            }

            return response()->json([
                'success' => true,
                'message' => 'Match completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing match: ' . $e->getMessage()
            ], 500);
        }
    }

    // Admin CRUD Functions
    public function getAllMatches(Request $request)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        try {
            $query = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.*',
                    't1.name as team1_name', 't2.name as team2_name',
                    'e.name as event_name'
                ]);

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('t1.name', 'LIKE', "%{$request->search}%")
                      ->orWhere('t2.name', 'LIKE', "%{$request->search}%")
                      ->orWhere('e.name', 'LIKE', "%{$request->search}%");
                });
            }

            $matches = $query->orderBy('m.scheduled_at', 'desc')->paginate(20);

            return response()->json([
                'data' => $matches->items(),
                'pagination' => [
                    'current_page' => $matches->currentPage(),
                    'last_page' => $matches->lastPage(),
                    'per_page' => $matches->perPage(),
                    'total' => $matches->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching matches: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getMatchAdmin($matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Return complete match data for admin editing
            return response()->json([
                'data' => [
                    'id' => $match->id,
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'event_id' => $match->event_id,
                    'scheduled_at' => $match->scheduled_at,
                    'format' => $match->format,
                    'status' => $match->status,
                    'team1_score' => $match->team1_score,
                    'team2_score' => $match->team2_score,
                    'current_map' => $match->current_map,
                    'stream_url' => $match->stream_url,
                    'round' => $match->round,
                    'bracket_position' => $match->bracket_position,
                    'maps_data' => json_decode($match->maps_data, true),
                    'live_data' => json_decode($match->live_data, true)
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching match: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        try {
            // Delete related data first
            DB::table('player_match_stats')->where('match_id', $matchId)->delete();
            DB::table('match_comments')->where('match_id', $matchId)->delete();
            
            // Delete match
            DB::table('matches')->where('id', $matchId)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Match deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match timeline data
     */
    public function getMatchTimeline($matchId)
    {
        try {
            $match = MvrlMatch::with(['team1', 'team2'])->findOrFail($matchId);
            
            $timeline = [];
            
            // Parse maps data to create timeline events
            $mapsData = json_decode($match->maps_data, true) ?? [];
            
            foreach ($mapsData as $index => $map) {
                if ($map['status'] === 'completed' && isset($map['winner_id'])) {
                    $winnerTeam = $map['winner_id'] == $match->team1_id ? $match->team1 : $match->team2;
                    $timeline[] = [
                        'type' => 'map_end',
                        'map_number' => $index + 1,
                        'map_name' => $map['map_name'] ?? 'Map ' . ($index + 1),
                        'winner' => $winnerTeam->name,
                        'score' => $map['team1_score'] . '-' . $map['team2_score'],
                        'time' => $map['completed_at'] ?? null
                    ];
                }
            }
            
            // Add match start event
            if ($match->started_at) {
                array_unshift($timeline, [
                    'type' => 'match_start',
                    'time' => $match->started_at
                ]);
            }
            
            // Add match end event
            if ($match->status === 'completed' && $match->completed_at) {
                $timeline[] = [
                    'type' => 'match_end',
                    'winner' => $match->winner_id == $match->team1_id ? $match->team1->name : $match->team2->name,
                    'final_score' => $match->team1_score . '-' . $match->team2_score,
                    'time' => $match->completed_at
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'match_id' => $match->id,
                    'timeline' => $timeline
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching match timeline: ' . $e->getMessage()
            ], 500);
        }
    }

    // Temporary direct match creation method (bypasses authorization)
    public function createMatchDirect(Request $request)
    {
        try {
            $request->validate([
                'team1_id' => 'required|integer|exists:teams,id',
                'team2_id' => 'required|integer|exists:teams,id|different:team1_id',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'format' => 'required|in:BO1,BO3,BO5,BO7,BO9',
                'status' => 'required|in:upcoming,live,completed,cancelled,postponed',
                'scheduled_at' => 'nullable|date',
                'event_id' => 'nullable|integer|exists:events,id'
            ]);

            $matchData = [
                'team1_id' => $request->team1_id,
                'team2_id' => $request->team2_id,
                'title' => $request->title ?: 'Match',
                'description' => $request->description,
                'format' => $request->format,
                'status' => $request->status,
                'scheduled_at' => $request->scheduled_at ? \Carbon\Carbon::parse($request->scheduled_at)->format('Y-m-d H:i:s') : now(),
                'event_id' => $request->event_id,
                'team1_score' => 0,
                'team2_score' => 0,
                'current_map' => 1,
                'round' => 'Regular Season',
                'bracket_position' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ];

            $matchId = DB::table('matches')->insertGetId($matchData);

            return response()->json([
                'success' => true,
                'message' => 'Match created successfully',
                'data' => [
                    'id' => $matchId,
                    'title' => $matchData['title'],
                    'format' => $matchData['format'],
                    'status' => $matchData['status']
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create match: ' . $e->getMessage()
            ], 500);
        }
    }

    // Live Control Method for comprehensive match updates
    public function liveControl(Request $request, $matchId)
    {
        try {
            $action = $request->input('action', 'update_all');
            
            if ($action === 'update_scores' || $action === 'update_all') {
                // Handle score updates with immediate response
                $data = [
                    'team1_score' => $request->input('team1_score') ?? $request->input('team_scores.team1'),
                    'team2_score' => $request->input('team2_score') ?? $request->input('team_scores.team2'),
                    'current_map' => $request->input('current_map'),
                    'status' => $request->input('status', 'live'),
                    'match_timer' => $request->input('timer') ?? $request->input('match_timer', '00:00'),
                    'updated_at' => now()
                ];

                // Update live data with current scores for immediate sync
                $liveData = [
                    'timer' => $data['match_timer'],
                    'current_map_score' => [
                        'team1' => $data['team1_score'],
                        'team2' => $data['team2_score']
                    ],
                    'current_map_index' => $request->input('current_map_index', 0),
                    'last_update' => now(),
                    'action' => $action
                ];
                $data['live_data'] = json_encode($liveData);

                DB::table('matches')->where('id', $matchId)->update($data);

                // Broadcast update
                $this->broadcastLiveUpdate($matchId, $data);

                return response()->json([
                    'success' => true,
                    'message' => 'Match scores updated successfully',
                    'data' => $data,
                    'live_data' => $liveData
                ]);
            }

            if ($action === 'update_composition' || $action === 'update_all') {
                // Handle player composition and stats updates
                $playerStats = $request->input('player_stats', []);
                $mapNumber = $request->input('map_number', 1);

                // Get current maps data
                $match = DB::table('matches')->where('id', $matchId)->first();
                $mapsData = $match->maps_data ? json_decode($match->maps_data, true) : [];

                // Ensure maps data structure exists
                if (empty($mapsData)) {
                    $mapsData = [
                        [
                            'map_number' => 1,
                            'map_name' => 'Tokyo 2099: Shibuya Sky',
                            'mode' => 'Domination',
                            'team1_score' => 0,
                            'team2_score' => 0,
                            'team1_composition' => [],
                            'team2_composition' => []
                        ]
                    ];
                }

                // Update player stats in maps data
                if (!empty($playerStats)) {
                    $mapIndex = $mapNumber - 1;
                    if (isset($mapsData[$mapIndex])) {
                        foreach ($playerStats as $player) {
                            $playerData = [
                                'player_id' => $player['player_id'],
                                'hero' => $player['hero'] ?? 'Captain America',
                                'eliminations' => $player['eliminations'] ?? 0,
                                'deaths' => $player['deaths'] ?? 0,
                                'assists' => $player['assists'] ?? 0,
                                'damage' => $player['damage'] ?? 0,
                                'healing' => $player['healing'] ?? 0,
                                'blocked' => $player['blocked'] ?? 0
                            ];

                            // Determine team based on player or use team field
                            $team = isset($player['team']) ? $player['team'] : 'team1';
                            $compositionKey = $team === 'team1' ? 'team1_composition' : 'team2_composition';

                            if (!isset($mapsData[$mapIndex][$compositionKey])) {
                                $mapsData[$mapIndex][$compositionKey] = [];
                            }

                            // Find and update existing player or add new
                            $playerFound = false;
                            foreach ($mapsData[$mapIndex][$compositionKey] as &$existingPlayer) {
                                if ($existingPlayer['player_id'] === $player['player_id']) {
                                    $existingPlayer = array_merge($existingPlayer, $playerData);
                                    $playerFound = true;
                                    break;
                                }
                            }

                            if (!$playerFound) {
                                $mapsData[$mapIndex][$compositionKey][] = $playerData;
                            }
                        }
                    }
                }

                // Update maps data in database
                DB::table('matches')->where('id', $matchId)->update([
                    'maps_data' => json_encode($mapsData),
                    'updated_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Player composition updated successfully',
                    'maps_data' => $mapsData
                ]);
            }

            if ($action === 'update_timer') {
                // Handle timer updates
                $timer = $request->input('timer', '00:00');
                $currentMapIndex = $request->input('current_map_index', 0);

                $liveData = [
                    'timer' => $timer,
                    'current_map_index' => $currentMapIndex,
                    'timer_updated' => now(),
                    'action' => 'timer_update'
                ];

                DB::table('matches')->where('id', $matchId)->update([
                    'live_data' => json_encode($liveData),
                    'updated_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Timer updated successfully',
                    'timer' => $timer
                ]);
            }

            // Legacy support for update_all
            if ($action === 'update_all') {
                // Update all match data including player stats
                $data = [
                    'team1_score' => $request->input('team1_score'),
                    'team2_score' => $request->input('team2_score'),
                    'current_map' => $request->input('current_map'),
                    'status' => $request->input('status', 'live'),
                    'match_timer' => $request->input('match_timer', '00:00'),
                    'updated_at' => now()
                ];

                // Update maps data with player stats
                if ($request->has('maps_data')) {
                    $mapsData = $request->input('maps_data');
                    
                    // Ensure player stats are properly included
                    foreach ($mapsData as &$map) {
                        if (isset($map['team1Composition'])) {
                            foreach ($map['team1Composition'] as &$player) {
                                // Ensure all stats fields exist
                                $player['eliminations'] = $player['eliminations'] ?? 0;
                                $player['deaths'] = $player['deaths'] ?? 0;
                                $player['assists'] = $player['assists'] ?? 0;
                                $player['damage'] = $player['damage'] ?? 0;
                                $player['healing'] = $player['healing'] ?? 0;
                                $player['damage_blocked'] = $player['damage_blocked'] ?? 0;
                                $player['kd_ratio'] = $player['deaths'] > 0 ? 
                                    round($player['eliminations'] / $player['deaths'], 2) : 
                                    $player['eliminations'];
                            }
                        }
                        
                        if (isset($map['team2Composition'])) {
                            foreach ($map['team2Composition'] as &$player) {
                                // Ensure all stats fields exist
                                $player['eliminations'] = $player['eliminations'] ?? 0;
                                $player['deaths'] = $player['deaths'] ?? 0;
                                $player['assists'] = $player['assists'] ?? 0;
                                $player['damage'] = $player['damage'] ?? 0;
                                $player['healing'] = $player['healing'] ?? 0;
                                $player['damage_blocked'] = $player['damage_blocked'] ?? 0;
                                $player['kd_ratio'] = $player['deaths'] > 0 ? 
                                    round($player['eliminations'] / $player['deaths'], 2) : 
                                    $player['eliminations'];
                            }
                        }
                    }
                    
                    $data['maps_data'] = json_encode($mapsData);
                }

                // Update live data
                $liveData = [
                    'timer' => $request->input('match_timer', '00:00'),
                    'current_map_score' => [
                        'team1' => $mapsData[$request->input('current_map') - 1]['team1Score'] ?? 0,
                        'team2' => $mapsData[$request->input('current_map') - 1]['team2Score'] ?? 0
                    ],
                    'last_update' => now()
                ];
                $data['live_data'] = json_encode($liveData);

                DB::table('matches')->where('id', $matchId)->update($data);

                // Broadcast live update via websocket/pusher
                $this->broadcastLiveUpdate($matchId, $data);

                return response()->json([
                    'success' => true,
                    'message' => 'Match data updated successfully',
                    'data' => $data
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating match: ' . $e->getMessage()
            ], 500);
        }
    }

    // Live Scoreboard endpoint for frontend live scoring synchronization
    public function liveScoreboard($matchId)
    {
        try {
            $match = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->where('m.id', $matchId)
                ->select(
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo', 
                    't1.region as team1_region', 't1.rating as team1_rating', 't1.flag as team1_flag',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    't2.region as team2_region', 't2.rating as team2_rating', 't2.flag as team2_flag',
                    'e.name as event_name', 'e.type as event_type', 'e.logo as event_logo'
                )
                ->first();

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Get team players with complete data
            $team1Players = $this->getTeamPlayersForLiveScoring($match->team1_id);
            $team2Players = $this->getTeamPlayersForLiveScoring($match->team2_id);

            // Parse live data
            $liveData = $match->live_data ? json_decode($match->live_data, true) : [];
            $mapsData = $match->maps_data ? json_decode($match->maps_data, true) : [];

            // Prepare response data optimized for live scoring
            $responseData = [
                'match' => [
                    'id' => $match->id,
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'team1_score' => $match->team1_score ?? 0,
                    'team2_score' => $match->team2_score ?? 0,
                    'status' => $match->status,
                    'format' => $match->format,
                    'current_map' => $match->current_map ?? 'Tokyo 2099: Shibuya Sky',
                    'current_mode' => $liveData['current_mode'] ?? 'Domination',
                    'game_mode' => $liveData['game_mode'] ?? 'Domination',
                    'match_timer' => $liveData['timer'] ?? '00:00',
                    'timer' => $liveData['timer'] ?? '00:00',
                    'current_map_index' => ($liveData['current_map_index'] ?? 1) - 1,
                    'current_map_number' => $liveData['current_map_index'] ?? 1,
                    'maps_data' => json_encode($mapsData),
                    'viewers' => $match->viewers ?? 0,
                    'last_updated' => $match->updated_at
                ],
                'teams' => [
                    'team1' => [
                        'id' => $match->team1_id,
                        'name' => $match->team1_name ?? 'Team 1',
                        'short_name' => $match->team1_short,
                        'logo' => $match->team1_logo,
                        'region' => $match->team1_region,
                        'flag' => $match->team1_flag,
                        'players' => $team1Players
                    ],
                    'team2' => [
                        'id' => $match->team2_id,
                        'name' => $match->team2_name ?? 'Team 2',
                        'short_name' => $match->team2_short,
                        'logo' => $match->team2_logo,
                        'region' => $match->team2_region,
                        'flag' => $match->team2_flag,
                        'players' => $team2Players
                    ]
                ],
                'live_data' => $liveData,
                'event' => [
                    'name' => $match->event_name,
                    'type' => $match->event_type,
                    'logo' => $match->event_logo
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'message' => 'Live scoreboard data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving live scoreboard: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper method to get country flag URL
    private function getCountryFlag($countryCode)
    {
        if (!$countryCode) return null;
        
        $code = strtolower($countryCode);
        
        // Map common country codes to flag image URLs
        $flagMap = [
            'us' => 'https://flagcdn.com/16x12/us.png',
            'ca' => 'https://flagcdn.com/16x12/ca.png',
            'gb' => 'https://flagcdn.com/16x12/gb.png',
            'uk' => 'https://flagcdn.com/16x12/gb.png',
            'de' => 'https://flagcdn.com/16x12/de.png',
            'fr' => 'https://flagcdn.com/16x12/fr.png',
            'kr' => 'https://flagcdn.com/16x12/kr.png',
            'jp' => 'https://flagcdn.com/16x12/jp.png',
            'cn' => 'https://flagcdn.com/16x12/cn.png',
            'br' => 'https://flagcdn.com/16x12/br.png',
            'au' => 'https://flagcdn.com/16x12/au.png',
            'se' => 'https://flagcdn.com/16x12/se.png',
            'no' => 'https://flagcdn.com/16x12/no.png',
            'dk' => 'https://flagcdn.com/16x12/dk.png',
            'fi' => 'https://flagcdn.com/16x12/fi.png',
            'nl' => 'https://flagcdn.com/16x12/nl.png',
            'es' => 'https://flagcdn.com/16x12/es.png',
            'it' => 'https://flagcdn.com/16x12/it.png',
            'ru' => 'https://flagcdn.com/16x12/ru.png',
            'pl' => 'https://flagcdn.com/16x12/pl.png',
            'mx' => 'https://flagcdn.com/16x12/mx.png',
            'ar' => 'https://flagcdn.com/16x12/ar.png',
        ];

        return $flagMap[$code] ?? "https://flagcdn.com/16x12/{$code}.png";
    }

    // Helper method to get team players optimized for live scoring
    private function getTeamPlayersForLiveScoring($teamId)
    {
        if (!$teamId) return [];

        return DB::table('players')
            ->where('team_id', $teamId)
            ->where('status', 'active')
            ->select(
                'id',
                'username',
                'real_name',
                'role',
                'avatar',
                'country',
                'nationality',
                'main_hero',
                'age'
            )
            ->limit(6)
            ->get()
            ->map(function($player) {
                return [
                    'id' => $player->id,
                    'player_id' => $player->id,
                    'name' => $player->username,
                    'player_name' => $player->username,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'hero' => $player->main_hero ?? 'Captain America',
                    'main_hero' => $player->main_hero,
                    'current_hero' => $player->main_hero,
                    'role' => $player->role ?? 'Vanguard',
                    'country' => $player->country ?? 'US',
                    'nationality' => $player->nationality ?? $player->country ?? 'US',
                    'flag' => $this->getCountryFlag($player->country ?? 'US'),
                    'avatar' => $player->avatar,
                    'age' => $player->age,
                    // Initialize stats for live scoring
                    'eliminations' => 0,
                    'deaths' => 0,
                    'assists' => 0,
                    'damage' => 0,
                    'healing' => 0,
                    'damage_blocked' => 0,
                    'ultimate_usage' => 0,
                    'objective_time' => 0
                ];
            })
            ->toArray();
    }

    // Broadcast live update to frontend via Pusher/WebSocket
    private function broadcastLiveUpdate($matchId, $data)
    {
        try {
            // Broadcast to Pusher channel
            if (config('broadcasting.default') === 'pusher') {
                broadcast(new \App\Events\MatchUpdated($matchId, $data))->toOthers();
            }
            
            // Log the update for debugging
            \Log::info('Live match update broadcasted', [
                'match_id' => $matchId,
                'team1_score' => $data['team1_score'] ?? null,
                'team2_score' => $data['team2_score'] ?? null,
                'current_map' => $data['current_map'] ?? null
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast live update: ' . $e->getMessage());
        }
    }

    // Helper Methods
    private function formatMatchData($match, $isLive = false)
    {
        $mapsData = $match->maps_data ? json_decode($match->maps_data, true) : [];
        $liveData = $match->live_data ? json_decode($match->live_data, true) : [];
        
        // Get team players
        $team1Players = [];
        $team2Players = [];
        
        if ($match->team1_id) {
            $team1Players = DB::table('players')
                ->where('team_id', $match->team1_id)
                ->where('status', 'active')
                ->select('id', 'username as name', 'real_name', 'role', 'avatar', 'country', 'nationality', 'main_hero as hero')
                ->limit(6)
                ->get()
                ->toArray();
        }
        
        if ($match->team2_id) {
            $team2Players = DB::table('players')
                ->where('team_id', $match->team2_id)
                ->where('status', 'active')
                ->select('id', 'username as name', 'real_name', 'role', 'avatar', 'country', 'nationality', 'main_hero as hero')
                ->limit(6)
                ->get()
                ->toArray();
        }

        return [
            'id' => $match->id,
            'team1' => [
                'id' => $match->team1_id,
                'name' => $match->team1_name,
                'short_name' => $match->team1_short,
                'logo' => $match->team1_logo,
                'region' => $match->team1_region,
                'rating' => $match->team1_rating ?? 1000,
                'flag' => $match->team1_flag ?? null,
                'players' => $team1Players
            ],
            'team2' => [
                'id' => $match->team2_id,
                'name' => $match->team2_name,
                'short_name' => $match->team2_short,
                'logo' => $match->team2_logo,
                'region' => $match->team2_region,
                'rating' => $match->team2_rating ?? 1000,
                'flag' => $match->team2_flag ?? null,
                'players' => $team2Players
            ],
            'event' => [
                'id' => $match->event_id,
                'name' => $match->event_name ?? 'Regular Match',
                'type' => $match->event_type ?? 'Tournament',
                'logo' => $match->event_logo ?? null
            ],
            'match_info' => [
                'scheduled_at' => $match->scheduled_at,
                'status' => $match->status,
                'format' => $match->format,
                'round' => $match->round,
                'current_map' => $match->current_map_number ?? 1,
                'viewers' => $match->viewers ?? 0
            ],
            'score' => [
                'team1' => $match->team1_score ?? 0,
                'team2' => $match->team2_score ?? 0,
                'maps' => $mapsData
            ],
            'broadcast' => [
                'stream' => $match->stream_url ?? null,
                'streams' => json_decode($match->stream_urls ?? '[]', true),
                'betting' => json_decode($match->betting_urls ?? '[]', true),
                'vods' => json_decode($match->vod_urls ?? '[]', true),
                'viewers' => $match->viewers ?? 0
            ],
            'live_data' => $isLive ? $liveData : null,
            'player_stats' => json_decode($match->player_stats ?? '{}', true),
            'format' => $match->format,
            'status' => $match->status,
            'current_map' => $match->current_map_number ?? 1,
            'maps_data' => $mapsData,
            'match_timer' => is_string($match->match_timer) ? json_decode($match->match_timer, true) : $match->match_timer,
            'timer' => $this->extractTimerValue($match->match_timer)
        ];
    }

    private function getCompleteMatchData($match)
    {
        $matchData = $this->formatMatchData($match);
        
        // Get team rosters with stats
        $matchData['team1']['roster'] = $this->getTeamRosterWithStats($match->team1_id, $match->id);
        $matchData['team2']['roster'] = $this->getTeamRosterWithStats($match->team2_id, $match->id);
        
        // Get detailed map data - ENSURE CONSISTENCY WITH FRONTEND
        $mapsData = json_decode($match->maps_data, true) ?? [];
        foreach ($mapsData as $index => &$map) {
            $map['index'] = $index + 1;
            $map['player_stats'] = $this->getMapPlayerStats($match->id, $index + 1);
            
            // ENSURE CONSISTENT TEAM COMPOSITION STRUCTURE
            if (isset($map['team1_composition'])) {
                $map['team1_composition'] = array_map(function($player) {
                    return [
                        'player_id' => $player['player_id'] ?? $player['id'] ?? null,
                        'player_name' => $player['player_name'] ?? $player['name'] ?? $player['username'] ?? 'Unknown Player',
                        'name' => $player['player_name'] ?? $player['name'] ?? $player['username'] ?? 'Unknown Player',
                        'username' => $player['username'] ?? $player['player_name'] ?? $player['name'] ?? 'Unknown Player',
                        'hero' => $player['hero'] ?? 'Captain America',
                        'role' => $player['role'] ?? 'Vanguard',
                        'country' => $player['country'] ?? $player['nationality'] ?? 'US',
                        'nationality' => $player['country'] ?? $player['nationality'] ?? 'US',
                        // Stats structure
                        'eliminations' => $player['eliminations'] ?? 0,
                        'deaths' => $player['deaths'] ?? 0,
                        'assists' => $player['assists'] ?? 0,
                        'damage' => $player['damage'] ?? 0,
                        'healing' => $player['healing'] ?? 0,
                        'damage_blocked' => $player['damage_blocked'] ?? 0,
                        'ultimate_usage' => $player['ultimate_usage'] ?? 0,
                        'objective_time' => $player['objective_time'] ?? 0
                    ];
                }, $map['team1_composition']);
            }
            
            if (isset($map['team2_composition'])) {
                $map['team2_composition'] = array_map(function($player) {
                    return [
                        'player_id' => $player['player_id'] ?? $player['id'] ?? null,
                        'player_name' => $player['player_name'] ?? $player['name'] ?? $player['username'] ?? 'Unknown Player',
                        'name' => $player['player_name'] ?? $player['name'] ?? $player['username'] ?? 'Unknown Player',
                        'username' => $player['username'] ?? $player['player_name'] ?? $player['name'] ?? 'Unknown Player',
                        'hero' => $player['hero'] ?? 'Captain America',
                        'role' => $player['role'] ?? 'Vanguard',
                        'country' => $player['country'] ?? $player['nationality'] ?? 'US',
                        'nationality' => $player['country'] ?? $player['nationality'] ?? 'US',
                        // Stats structure
                        'eliminations' => $player['eliminations'] ?? 0,
                        'deaths' => $player['deaths'] ?? 0,
                        'assists' => $player['assists'] ?? 0,
                        'damage' => $player['damage'] ?? 0,
                        'healing' => $player['healing'] ?? 0,
                        'damage_blocked' => $player['damage_blocked'] ?? 0,
                        'ultimate_usage' => $player['ultimate_usage'] ?? 0,
                        'objective_time' => $player['objective_time'] ?? 0
                    ];
                }, $map['team2_composition']);
            }
        }
        
        // PROVIDE BOTH FORMATS FOR FULL COMPATIBILITY
        $matchData['maps_data'] = $mapsData;  // For LiveScoring
        $matchData['maps_detailed'] = $mapsData;  // For MatchDetail
        
        // Get match timeline
        $matchData['timeline'] = $this->getMatchTimelineEvents($match->id);
        
        // ENHANCED: Add complete match structure for perfect synchronization
        $matchData['schedule'] = [
            'scheduled_at' => $match->scheduled_at,
            'actual_start_time' => $match->actual_start_time,
            'actual_end_time' => $match->actual_end_time,
            'timezone' => 'UTC'
        ];
        
        $matchData['urls'] = [
            'streams' => json_decode($match->stream_urls ?? '[]', true),
            'betting' => json_decode($match->betting_urls ?? '[]', true),
            'vods' => json_decode($match->vod_urls ?? '[]', true)
        ];
        
        $matchData['tournament'] = [
            'round' => $match->round,
            'bracket_position' => $match->bracket_position,
            'bracket_type' => $match->bracket_type ?? 'main'
        ];
        
        $matchData['scores'] = [
            'series' => [
                'team1' => $match->team1_score ?? 0,
                'team2' => $match->team2_score ?? 0
            ],
            'maps' => $mapsData
        ];
        
        $matchData['live_data'] = [
            'current_map' => $match->current_map_number ?? 1,
            'timer' => $match->live_timer ?? '00:00',
            'timer_running' => $match->timer_running ?? false,
            'overtime' => $match->overtime ?? false,
            'viewers' => $match->viewers ?? 0,
            'hero_picks' => json_decode($match->hero_data ?? '{}', true),
            'live_updates' => json_decode($match->live_data ?? '{}', true),
            'preparation_phase' => $match->is_preparation_phase ?? false,
            'preparation_timer' => $match->preparation_timer ?? 45
        ];
        
        $matchData['player_stats'] = json_decode($match->player_stats ?? '{}', true);
        
        // Add required fields for frontend compatibility
        $matchData['teams'] = [
            'team1' => $matchData['team1'],
            'team2' => $matchData['team2']
        ];
        
        // Ensure status is set
        $matchData['status'] = $match->status ?? 'upcoming';
        
        // Ensure format is set
        $matchData['format'] = $match->format ?? 'BO3';
        
        return $matchData;
    }

    private function getTeamRosterWithStats($teamId, $matchId)
    {
        return DB::table('players as p')
            ->leftJoin('player_match_stats as mps', function($join) use ($matchId) {
                $join->on('p.id', '=', 'mps.player_id')
                     ->where('mps.match_id', '=', $matchId);
            })
            ->where('p.team_id', $teamId)
            ->where('p.status', 'active')
            ->select([
                'p.id', 'p.username', 'p.real_name', 'p.role', 'p.avatar',
                'p.main_hero', 'p.country', 'p.nationality', 'p.age',
                DB::raw('COALESCE(SUM(mps.eliminations), 0) as total_kills'),
                DB::raw('COALESCE(SUM(mps.deaths), 0) as total_deaths'),
                DB::raw('COALESCE(SUM(mps.assists), 0) as total_assists'),
                DB::raw('COALESCE(AVG(mps.damage), 0) as avg_damage'),
                DB::raw('COALESCE(AVG(mps.healing), 0) as avg_healing'),
                DB::raw('COALESCE(AVG(mps.damage_blocked), 0) as avg_damage_blocked'),
                DB::raw('COALESCE(AVG(mps.ultimate_usage), 0) as avg_ultimate_usage'),
                DB::raw('COALESCE(AVG(mps.objective_time), 0) as avg_objective_time')
            ])
            ->groupBy('p.id', 'p.username', 'p.real_name', 'p.role', 'p.avatar', 'p.main_hero', 'p.country', 'p.nationality', 'p.age')
            ->get()
            ->map(function($player) {
                return [
                    'id' => $player->id,
                    'player_id' => $player->id,  // For consistency
                    'username' => $player->username,
                    'player_name' => $player->username,  // For consistency
                    'name' => $player->real_name ?: $player->username,  // For consistency
                    'real_name' => $player->real_name,
                    'role' => $player->role,
                    'avatar' => $player->avatar,
                    'hero' => $player->main_hero,  // For consistency
                    'main_hero' => $player->main_hero,
                    'country' => $player->country,  // For consistency
                    'nationality' => $player->country,  // For consistency
                    'country_flag' => $this->getCountryFlag($player->country),
                    'flag' => $this->getCountryFlag($player->country),  // For consistency
                    'age' => $player->age,
                    'stats' => [
                        'kills' => (int)$player->total_kills,
                        'eliminations' => (int)$player->total_kills,  // For consistency
                        'deaths' => (int)$player->total_deaths,
                        'assists' => (int)$player->total_assists,
                        'kda' => $player->total_deaths > 0 ? 
                            round(($player->total_kills + $player->total_assists) / $player->total_deaths, 2) : 
                            ($player->total_kills + $player->total_assists),
                        'kd_ratio' => $player->total_deaths > 0 ? 
                            round($player->total_kills / $player->total_deaths, 2) : 
                            $player->total_kills,
                        'avg_damage' => round($player->avg_damage),
                        'damage' => round($player->avg_damage),  // For consistency
                        'avg_healing' => round($player->avg_healing),
                        'healing' => round($player->avg_healing),  // For consistency
                        'damage_blocked' => round($player->avg_damage_blocked),
                        'ultimate_usage' => round($player->avg_ultimate_usage),
                        'objective_time' => round($player->avg_objective_time)
                    ]
                ];
            })
            ->toArray();
    }

    private function getMapPlayerStats($matchId, $mapNumber)
    {
        // First get the round_id from match_rounds table
        $mapRound = DB::table('match_rounds')
            ->where('match_id', $matchId)
            ->where('round_number', $mapNumber)
            ->first();
            
        if (!$mapRound) {
            return [];
        }
        
        return DB::table('player_match_stats as mps')
            ->leftJoin('players as p', 'mps.player_id', '=', 'p.id')
            ->where('mps.match_id', $matchId)
            ->where('mps.round_id', $mapRound->id)
            ->select([
                'p.id', 'p.username', 'p.team_id',
                'mps.hero_played', 'mps.eliminations as kills', 'mps.deaths', 'mps.assists',
                'mps.damage', 'mps.healing', 'mps.damage_blocked',
                'mps.ultimate_usage', 'mps.final_blows', 'mps.objective_time'
            ])
            ->get()
            ->groupBy('team_id')
            ->toArray();
    }

    private function getMatchTimelineEvents($matchId)
    {
        return DB::table('match_events')
            ->where('match_id', $matchId)
            ->orderBy('timestamp')
            ->get()
            ->map(function($event) {
                return [
                    'time' => $event->timestamp ?? null,
                    'type' => $event->event_type ?? $event->type ?? 'unknown',
                    'description' => $event->description ?? '',
                    'team_id' => $event->team_id ?? null,
                    'player_id' => $event->player_id ?? null
                ];
            })
            ->toArray();
    }

    private function initializeMapsData($maps, $format = 'BO3', $team1Id = null, $team2Id = null)
    {
        // Handle null maps input
        if (!$maps || !is_array($maps)) {
            $maps = [];
        }
        
        $formatMapping = [
            'BO1' => 1, 'BO3' => 3, 'BO5' => 5, 'BO7' => 7, 'BO9' => 9
        ];
        
        $maxMaps = $formatMapping[$format] ?? 3;
        
        // Get team rosters if team IDs are provided
        $team1Roster = [];
        $team2Roster = [];
        
        if ($team1Id && $team2Id) {
            $team1Players = DB::table('players')->where('team_id', $team1Id)->where('status', 'active')->get();
            $team2Players = DB::table('players')->where('team_id', $team2Id)->where('status', 'active')->get();
            
            // Format team 1 roster
            foreach ($team1Players as $player) {
                $team1Roster[] = [
                    'player_id' => $player->id,
                    'player_name' => $player->username ?? $player->real_name,
                    'name' => $player->username ?? $player->real_name,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'hero' => $player->main_hero ?? 'Captain America',
                    'role' => $player->role ?? 'Duelist',
                    'country' => $player->country ?? 'US',
                    'nationality' => $player->country ?? 'US',
                    'country_flag' => $this->getCountryFlag($player->country ?? 'US'),
                    'flag' => $this->getCountryFlag($player->country ?? 'US'),
                    'avatar' => $player->avatar,
                    'stats' => [
                        'kills' => 0,
                        'eliminations' => 0,
                        'deaths' => 0,
                        'assists' => 0,
                        'kda' => 0,
                        'kd_ratio' => 0,
                        'avg_damage' => 0,
                        'damage' => 0,
                        'avg_healing' => 0,
                        'healing' => 0,
                        'damage_blocked' => 0,
                        'ultimate_usage' => 0,
                        'objective_time' => 0
                    ]
                ];
            }
            
            // Format team 2 roster
            foreach ($team2Players as $player) {
                $team2Roster[] = [
                    'player_id' => $player->id,
                    'player_name' => $player->username ?? $player->real_name,
                    'name' => $player->username ?? $player->real_name,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'hero' => $player->main_hero ?? 'Captain America',
                    'role' => $player->role ?? 'Duelist',
                    'country' => $player->country ?? 'US',
                    'nationality' => $player->country ?? 'US',
                    'country_flag' => $this->getCountryFlag($player->country ?? 'US'),
                    'flag' => $this->getCountryFlag($player->country ?? 'US'),
                    'avatar' => $player->avatar,
                    'stats' => [
                        'kills' => 0,
                        'eliminations' => 0,
                        'deaths' => 0,
                        'assists' => 0,
                        'kda' => 0,
                        'kd_ratio' => 0,
                        'avg_damage' => 0,
                        'damage' => 0,
                        'avg_healing' => 0,
                        'healing' => 0,
                        'damage_blocked' => 0,
                        'ultimate_usage' => 0,
                        'objective_time' => 0
                    ]
                ];
            }
        }
        
        return array_map(function($map, $index) use ($team1Roster, $team2Roster) {
            return [
                'map_number' => $index + 1,
                'map_name' => $map['map_name'] ?? 'Tokyo 2099: Shibuya Sky',
                'mode' => $map['mode'] ?? $map['game_mode'] ?? 'Convoy',
                'game_mode' => $map['mode'] ?? $map['game_mode'] ?? 'Convoy', // Keep for backward compatibility
                'status' => $map['status'] ?? ($index === 0 ? 'upcoming' : 'not_played'),
                'team1_score' => $map['team1_score'] ?? 0,
                'team2_score' => $map['team2_score'] ?? 0,
                'team1_heroes' => $map['team1_heroes'] ?? [],
                'team2_heroes' => $map['team2_heroes'] ?? [],
                'team1_composition' => !empty($map['team1_composition']) ? $map['team1_composition'] : $team1Roster,
                'team2_composition' => !empty($map['team2_composition']) ? $map['team2_composition'] : $team2Roster,
                'duration' => $map['duration'] ?? '00:00',
                'started_at' => $map['started_at'] ?? null,
                'completed_at' => $map['completed_at'] ?? null,
                'winner_id' => $map['winner_id'] ?? null,
                'overtime' => $map['overtime'] ?? false
            ];
        }, array_slice($maps, 0, $maxMaps), array_keys(array_slice($maps, 0, $maxMaps)));
    }

    private function initializeLiveData()
    {
        return [
            'current_map_time' => '00:00',
            'timer_status' => 'stopped',
            'last_update' => now(),
            'updates' => []
        ];
    }

    private function startMapTimer($matchId, $mapNumber)
    {
        $match = DB::table('matches')->where('id', $matchId)->first();
        $mapsData = json_decode($match->maps_data, true);
        
        if (isset($mapsData[$mapNumber - 1])) {
            $mapsData[$mapNumber - 1]['started_at'] = now();
            $mapsData[$mapNumber - 1]['status'] = 'ongoing';
            
            DB::table('matches')->where('id', $matchId)->update([
                'maps_data' => json_encode($mapsData),
                'current_map' => $mapNumber
            ]);
        }
    }

    private function handleMapCompletion($matchId, $mapIndex, &$mapsData)
    {
        $mapsData[$mapIndex]['completed_at'] = now();
        $mapsData[$mapIndex]['status'] = 'completed';
        
        // Calculate map duration
        if ($mapsData[$mapIndex]['started_at']) {
            $start = \Carbon\Carbon::parse($mapsData[$mapIndex]['started_at']);
            $end = now();
            $duration = $end->diff($start);
            $mapsData[$mapIndex]['duration'] = sprintf('%02d:%02d', $duration->i, $duration->s);
        }
    }

    private function updateOverallScore($matchId)
    {
        $match = DB::table('matches')->where('id', $matchId)->first();
        $mapsData = json_decode($match->maps_data, true);
        
        $team1Score = 0;
        $team2Score = 0;
        
        foreach ($mapsData as $map) {
            if ($map['status'] === 'completed') {
                if ($map['team1_score'] > $map['team2_score']) {
                    $team1Score++;
                } elseif ($map['team2_score'] > $map['team1_score']) {
                    $team2Score++;
                }
            }
        }
        
        DB::table('matches')->where('id', $matchId)->update([
            'team1_score' => $team1Score,
            'team2_score' => $team2Score
        ]);
    }

    private function updatePlayerStats($matchId, $data)
    {
        // Get the map_id from match_rounds table
        $mapNumber = $data['map_number'] ?? 1;
        $mapRound = DB::table('match_rounds')
            ->where('match_id', $matchId)
            ->where('map_number', $mapNumber)
            ->first();
            
        if (!$mapRound) {
            return; // Map round doesn't exist yet
        }
        
        foreach ($data['players'] as $playerStats) {
            DB::table('player_match_stats')->updateOrInsert(
                [
                    'match_id' => $matchId,
                    'player_id' => $playerStats['player_id'],
                    'round_id' => $mapRound->id
                ],
                [
                    'hero_played' => $playerStats['hero_played'] ?? null,
                    'eliminations' => $playerStats['kills'] ?? 0,
                    'deaths' => $playerStats['deaths'] ?? 0,
                    'assists' => $playerStats['assists'] ?? 0,
                    'damage' => $playerStats['damage_dealt'] ?? 0,
                    'healing' => $playerStats['healing_done'] ?? 0,
                    'damage_blocked' => $playerStats['damage_blocked'] ?? 0,
                    'ultimate_usage' => $playerStats['ultimates_used'] ?? 0,
                    'final_blows' => $playerStats['final_blows'] ?? 0,
                    'objective_time' => $playerStats['objective_time'] ?? 0,
                    'accuracy_percentage' => $playerStats['accuracy_percentage'] ?? 0,
                    'critical_hits' => $playerStats['critical_hits'] ?? 0,
                    'environmental_kills' => $playerStats['environmental_kills'] ?? 0,
                    'team_damage_amplified' => $playerStats['team_damage_amplified'] ?? 0,
                    'cc_time_applied' => $playerStats['cc_time_applied'] ?? 0,
                    'hero_playtime_seconds' => $playerStats['hero_playtime_seconds'] ?? 0,
                    'role_played' => $playerStats['role_played'] ?? null,
                    'hero_switches' => isset($playerStats['hero_switches']) ? json_encode($playerStats['hero_switches']) : null,
                    'current_map' => $mapRound->map_name,
                    'updated_at' => now()
                ]
            );
        }
    }

    public function bulkUpdateStats(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('moderate-matches');
        }
        
        $request->validate([
            'map_number' => 'required|integer|min:1',
            'players' => 'required|array|min:1',
            'players.*.player_id' => 'required|exists:players,id',
            'players.*.hero_played' => 'nullable|string|max:255',
            'players.*.eliminations' => 'nullable|integer|min:0',
            'players.*.deaths' => 'nullable|integer|min:0',
            'players.*.assists' => 'nullable|integer|min:0',
            'players.*.damage' => 'nullable|integer|min:0',
            'players.*.healing' => 'nullable|integer|min:0',
            'players.*.damage_blocked' => 'nullable|integer|min:0',
            'players.*.final_blows' => 'nullable|integer|min:0',
            'players.*.environmental_kills' => 'nullable|integer|min:0',
            'players.*.accuracy_percentage' => 'nullable|numeric|min:0|max:100',
            'players.*.critical_hits' => 'nullable|integer|min:0',
            'players.*.ultimate_usage' => 'nullable|integer|min:0',
            'players.*.objective_time' => 'nullable|integer|min:0',
            'players.*.team_damage_amplified' => 'nullable|integer|min:0',
            'players.*.cc_time_applied' => 'nullable|integer|min:0',
            'players.*.hero_playtime_seconds' => 'nullable|integer|min:0',
            'players.*.role_played' => 'nullable|in:Vanguard,Duelist,Strategist',
            'players.*.hero_switches' => 'nullable|array'
        ]);

        try {
            // Check if match exists
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Get or create the round/map
            $mapNumber = $request->map_number;
            $mapRound = DB::table('match_rounds')
                ->where('match_id', $matchId)
                ->where('round_number', $mapNumber)
                ->first();
                
            if (!$mapRound) {
                // Create the round if it doesn't exist
                $mapsData = json_decode($match->maps_data, true);
                $mapIndex = $mapNumber - 1;
                
                if (!isset($mapsData[$mapIndex])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid map number for this match'
                    ], 400);
                }
                
                $roundId = DB::table('match_rounds')->insertGetId([
                    'match_id' => $matchId,
                    'round_number' => $mapNumber,
                    'map_name' => $mapsData[$mapIndex]['map_name'] ?? null,
                    'game_mode' => $mapsData[$mapIndex]['game_mode'] ?? 'Domination',
                    'status' => $mapsData[$mapIndex]['status'] ?? 'upcoming',
                    'team1_score' => $mapsData[$mapIndex]['team1_score'] ?? 0,
                    'team2_score' => $mapsData[$mapIndex]['team2_score'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $mapRound = (object)['id' => $roundId, 'map_name' => $mapsData[$mapIndex]['map_name'] ?? null];
            }

            // Get players by team for validation
            $team1Players = DB::table('players')->where('team_id', $match->team1_id)->pluck('id')->toArray();
            $team2Players = DB::table('players')->where('team_id', $match->team2_id)->pluck('id')->toArray();
            $allValidPlayers = array_merge($team1Players, $team2Players);

            // Prepare bulk update data
            $statsToUpdate = [];
            $timestamp = now();

            foreach ($request->players as $playerStat) {
                // Validate player belongs to one of the match teams
                if (!in_array($playerStat['player_id'], $allValidPlayers)) {
                    continue; // Skip invalid players
                }

                // Determine which team the player belongs to
                $teamId = in_array($playerStat['player_id'], $team1Players) ? $match->team1_id : $match->team2_id;

                $statsToUpdate[] = [
                    'match_id' => $matchId,
                    'player_id' => $playerStat['player_id'],
                    'round_id' => $mapRound->id,
                    'hero_played' => $playerStat['hero_played'] ?? null,
                    'eliminations' => $playerStat['eliminations'] ?? 0,
                    'deaths' => $playerStat['deaths'] ?? 0,
                    'assists' => $playerStat['assists'] ?? 0,
                    'damage' => $playerStat['damage'] ?? 0,
                    'healing' => $playerStat['healing'] ?? 0,
                    'damage_blocked' => $playerStat['damage_blocked'] ?? 0,
                    'final_blows' => $playerStat['final_blows'] ?? 0,
                    'environmental_kills' => $playerStat['environmental_kills'] ?? 0,
                    'accuracy_percentage' => $playerStat['accuracy_percentage'] ?? 0,
                    'critical_hits' => $playerStat['critical_hits'] ?? 0,
                    'ultimate_usage' => $playerStat['ultimate_usage'] ?? 0,
                    'objective_time' => $playerStat['objective_time'] ?? 0,
                    'team_damage_amplified' => $playerStat['team_damage_amplified'] ?? 0,
                    'cc_time_applied' => $playerStat['cc_time_applied'] ?? 0,
                    'hero_playtime_seconds' => $playerStat['hero_playtime_seconds'] ?? 0,
                    'role_played' => $playerStat['role_played'] ?? null,
                    'hero_switches' => isset($playerStat['hero_switches']) ? json_encode($playerStat['hero_switches']) : null,
                    'current_map' => $mapRound->map_name,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ];
            }

            // Perform bulk upsert
            if (!empty($statsToUpdate)) {
                DB::transaction(function () use ($statsToUpdate, $matchId, $mapRound) {
                    // Delete existing stats for this match/round combination
                    $playerIds = array_column($statsToUpdate, 'player_id');
                    DB::table('player_match_stats')
                        ->where('match_id', $matchId)
                        ->where('round_id', $mapRound->id)
                        ->whereIn('player_id', $playerIds)
                        ->delete();

                    // Insert new stats
                    DB::table('player_match_stats')->insert($statsToUpdate);
                });

                // Update match's last updated timestamp
                DB::table('matches')->where('id', $matchId)->update(['updated_at' => $timestamp]);

                return response()->json([
                    'success' => true,
                    'message' => 'Player stats bulk updated successfully',
                    'data' => [
                        'match_id' => $matchId,
                        'map_number' => $mapNumber,
                        'players_updated' => count($statsToUpdate)
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid player stats to update'
                ], 400);
            }

        } catch (\Exception $e) {
            \Log::error('MatchController@bulkUpdateStats error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Error updating player stats: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updateTeamRatings($team1Id, $team2Id, $winnerId)
    {
        // ELO calculation based on match result
        $team1 = DB::table('teams')->where('id', $team1Id)->first();
        $team2 = DB::table('teams')->where('id', $team2Id)->first();
        
        if (!$team1 || !$team2) {
            return;
        }
        
        $k = 32; // K-factor for rating volatility
        $expectedScore1 = 1 / (1 + pow(10, ($team2->rating - $team1->rating) / 400));
        $expectedScore2 = 1 - $expectedScore1;
        
        $actualScore1 = $winnerId == $team1Id ? 1 : 0;
        $actualScore2 = $winnerId == $team2Id ? 1 : 0;
        
        $newRating1 = round($team1->rating + $k * ($actualScore1 - $expectedScore1));
        $newRating2 = round($team2->rating + $k * ($actualScore2 - $expectedScore2));
        
        // Update team 1 stats
        $wins1 = $team1->wins ?? 0;
        $losses1 = $team1->losses ?? 0;
        if ($winnerId == $team1Id) {
            $wins1++;
        } else {
            $losses1++;
        }
        $winRate1 = $wins1 + $losses1 > 0 ? round(($wins1 / ($wins1 + $losses1)) * 100, 2) : 0;
        
        // Update team 2 stats
        $wins2 = $team2->wins ?? 0;
        $losses2 = $team2->losses ?? 0;
        if ($winnerId == $team2Id) {
            $wins2++;
        } else {
            $losses2++;
        }
        $winRate2 = $wins2 + $losses2 > 0 ? round(($wins2 / ($wins2 + $losses2)) * 100, 2) : 0;
        
        // Update team 1
        DB::table('teams')->where('id', $team1Id)->update([
            'rating' => $newRating1,
            'peak' => max($newRating1, $team1->peak ?? 0),
            'wins' => $wins1,
            'losses' => $losses1,
            'win_rate' => $winRate1,
            'record' => $wins1 . '-' . $losses1,
            'last_match' => now(),
            'updated_at' => now()
        ]);
        
        // Update team 2
        DB::table('teams')->where('id', $team2Id)->update([
            'rating' => $newRating2,
            'peak' => max($newRating2, $team2->peak ?? 0),
            'wins' => $wins2,
            'losses' => $losses2,
            'win_rate' => $winRate2,
            'record' => $wins2 . '-' . $losses2,
            'last_match' => now(),
            'updated_at' => now()
        ]);
        
        // Update all team rankings based on new ratings
        $this->updateAllTeamRanks();
    }
    
    private function updateAllTeamRanks()
    {
        // Get all teams sorted by rating
        $teams = DB::table('teams')
            ->orderBy('rating', 'desc')
            ->get();
        
        // Update ranks
        foreach ($teams as $index => $team) {
            DB::table('teams')
                ->where('id', $team->id)
                ->update(['rank' => $index + 1]);
        }
    }

    // Comments System
    public function storeComment(Request $request, $matchId)
    {
        $request->validate([
            'content' => 'required|string|min:3|max:1000',
            'parent_id' => 'nullable|exists:match_comments,id'
        ]);

        try {
            $commentId = DB::table('match_comments')->insertGetId([
                'match_id' => $matchId,
                'user_id' => Auth::id(),
                'parent_id' => $request->parent_id,
                'content' => $request->content,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Process mentions using the new system
            $this->processMentions($request->content, $matchId, $commentId);

            return response()->json([
                'data' => ['id' => $commentId],
                'success' => true,
                'message' => 'Comment posted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error posting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getMatchComments($matchId)
    {
        $comments = DB::table('match_comments as mc')
            ->leftJoin('users as u', 'mc.user_id', '=', 'u.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('mc.match_id', $matchId)
            ->whereNull('mc.parent_id')
            ->select([
                'mc.*',
                'u.name as user_name', 'u.avatar', 'u.hero_flair',
                'u.show_hero_flair', 'u.show_team_flair',
                't.name as team_flair_name', 't.short_name as team_flair_short',
                't.logo as team_flair_logo'
            ])
            ->orderBy('mc.created_at', 'desc')
            ->get();

        return $comments->map(function($comment) use ($matchId) {
            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'user' => $this->formatUserWithFlairs($comment),
                'votes' => $this->getCommentVotes($comment->id),
                'replies' => $this->getCommentReplies($comment->id, $matchId),
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at
            ];
        })->toArray();
    }

    private function getCommentReplies($parentId, $matchId)
    {
        $replies = DB::table('match_comments as mc')
            ->leftJoin('users as u', 'mc.user_id', '=', 'u.id')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('mc.match_id', $matchId)
            ->where('mc.parent_id', $parentId)
            ->select([
                'mc.*',
                'u.name as user_name', 'u.avatar', 'u.hero_flair',
                'u.show_hero_flair', 'u.show_team_flair',
                't.name as team_flair_name', 't.short_name as team_flair_short',
                't.logo as team_flair_logo'
            ])
            ->orderBy('mc.created_at', 'asc')
            ->get();

        return $replies->map(function($reply) use ($matchId) {
            return [
                'id' => $reply->id,
                'content' => $reply->content,
                'user' => $this->formatUserWithFlairs($reply),
                'votes' => $this->getCommentVotes($reply->id),
                'replies' => $this->getCommentReplies($reply->id, $matchId),
                'created_at' => $reply->created_at,
                'updated_at' => $reply->updated_at
            ];
        })->toArray();
    }

    private function formatUserWithFlairs($userData)
    {
        $user = [
            'id' => $userData->user_id,
            'name' => $userData->user_name,
            'avatar' => $userData->avatar
        ];

        // Add hero flair if enabled
        if ($userData->show_hero_flair && $userData->hero_flair) {
            $user['hero_flair'] = [
                'name' => $userData->hero_flair,
                'icon' => "/images/heroes/" . str_replace([' ', '&'], ['-', 'and'], strtolower($userData->hero_flair)) . ".png"
            ];
        }

        // Add team flair if enabled  
        if ($userData->show_team_flair && $userData->team_flair_name) {
            $user['team_flair'] = [
                'name' => $userData->team_flair_name,
                'short_name' => $userData->team_flair_short,
                'logo' => $userData->team_flair_logo
            ];
        }

        return $user;
    }

    private function getCommentVotes($commentId)
    {
        $upvotes = DB::table('comment_votes')
            ->where('comment_id', $commentId)
            ->where('comment_type', 'match')
            ->where('vote_type', 'upvote')
            ->count();
            
        $downvotes = DB::table('comment_votes')
            ->where('comment_id', $commentId)
            ->where('comment_type', 'match')
            ->where('vote_type', 'downvote')
            ->count();
            
        return [
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'total' => $upvotes - $downvotes
        ];
    }

    private function extractMentions($content)
    {
        $mentions = [];
        
        // Extract @username mentions
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $userMatches);
        if (!empty($userMatches[1])) {
            $mentions['users'] = $userMatches[1];
        }
        
        // Extract @team:teamname mentions
        preg_match_all('/@team:([a-zA-Z0-9_\-]+)/', $content, $teamMatches);
        if (!empty($teamMatches[1])) {
            $mentions['teams'] = $teamMatches[1];
        }
        
        // Extract @player:playername mentions
        preg_match_all('/@player:([a-zA-Z0-9_\-]+)/', $content, $playerMatches);
        if (!empty($playerMatches[1])) {
            $mentions['players'] = $playerMatches[1];
        }
        
        return $mentions;
    }

    private function processMentions($content, $matchId, $commentId = null)
    {
        $mentions = $this->extractMentionsNew($content);
        
        foreach ($mentions as $mention) {
            try {
                // Determine the mentionable type and ID
                $mentionableType = $commentId ? 'match_comment' : 'match';
                $mentionableId = $commentId ?: $matchId;
                
                // Create mention record using the new structure
                Mention::updateOrCreate([
                    'mentionable_type' => $mentionableType,
                    'mentionable_id' => $mentionableId,
                    'mentioned_type' => $mention['type'],
                    'mentioned_id' => $mention['id'],
                    'mention_text' => $mention['mention_text'] ?? "@{$mention['name']}"
                ], [
                    'mentioned_by' => Auth::id(),
                    'mentioned_at' => now(),
                    'is_active' => true,
                    'context' => $this->extractMentionContext($content, $mention['mention_text'] ?? "@{$mention['name']}")
                ]);
            } catch (\Exception $e) {
                \Log::error('Error processing mention: ' . $e->getMessage());
            }
        }
    }

    private function extractMentionsNew($content)
    {
        $mentions = [];
        
        // Extract @username mentions (users)
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $userMatches, PREG_OFFSET_CAPTURE);
        foreach ($userMatches[1] as $match) {
            $username = $match[0];
            $position = $userMatches[0][array_search($match, $userMatches[1])][1];
            
            $user = DB::table('users')->where('name', $username)->first();
            if ($user) {
                $mentionText = "@{$username}";
                $mentions[] = [
                    'type' => 'user',
                    'id' => $user->id,
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => $mentionText,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText)
                ];
            }
        }

        // Extract @team:teamname mentions
        preg_match_all('/@team:([a-zA-Z0-9_]+)/', $content, $teamMatches, PREG_OFFSET_CAPTURE);
        foreach ($teamMatches[1] as $match) {
            $teamName = $match[0];
            $position = $teamMatches[0][array_search($match, $teamMatches[1])][1];
            
            $team = DB::table('teams')->where('short_name', $teamName)->first();
            if ($team) {
                $mentionText = "@team:{$teamName}";
                $mentions[] = [
                    'type' => 'team',
                    'id' => $team->id,
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => $mentionText,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText)
                ];
            }
        }

        // Extract @player:playername mentions
        preg_match_all('/@player:([a-zA-Z0-9_]+)/', $content, $playerMatches, PREG_OFFSET_CAPTURE);
        foreach ($playerMatches[1] as $match) {
            $playerName = $match[0];
            $position = $playerMatches[0][array_search($match, $playerMatches[1])][1];
            
            $player = DB::table('players')->where('username', $playerName)->first();
            if ($player) {
                $mentionText = "@player:{$playerName}";
                $mentions[] = [
                    'type' => 'player',
                    'id' => $player->id,
                    'name' => $player->username,
                    'display_name' => $player->real_name ?? $player->username,
                    'mention_text' => $mentionText,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText)
                ];
            }
        }

        return $mentions;
    }

    private function extractMentionContext($content, $mentionText)
    {
        $position = strpos($content, $mentionText);
        if ($position === false) {
            return null;
        }

        // Extract 50 characters before and after the mention for context
        $contextLength = 50;
        $start = max(0, $position - $contextLength);
        $end = min(strlen($content), $position + strlen($mentionText) + $contextLength);
        
        $context = substr($content, $start, $end - $start);
        
        // Clean up context (remove excessive whitespace, etc.)
        $context = preg_replace('/\s+/', ' ', trim($context));
        
        return $context;
    }

    public function voteComment(Request $request, $commentId)
    {
        $this->authorize('vote');
        
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        try {
            $userId = Auth::id();
            $voteType = $request->vote_type;

            // Get the comment to ensure it exists
            $comment = DB::table('match_comments')->where('id', $commentId)->first();
            if (!$comment) {
                return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
            }

            // Check for existing vote
            $existingVote = DB::table('comment_votes')
                ->where('comment_id', $commentId)
                ->where('comment_type', 'match')
                ->where('user_id', $userId)
                ->first();

            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Remove vote if same type
                    DB::table('comment_votes')->where('id', $existingVote->id)->delete();
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote removed',
                        'action' => 'removed'
                    ]);
                } else {
                    // Update vote if different type
                    DB::table('comment_votes')
                        ->where('id', $existingVote->id)
                        ->update(['vote_type' => $voteType, 'updated_at' => now()]);
                }
            } else {
                // Create new vote
                DB::table('comment_votes')->insert([
                    'comment_id' => $commentId,
                    'comment_type' => 'match',
                    'user_id' => $userId,
                    'vote_type' => $voteType,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Vote recorded',
                'action' => 'voted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording vote: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===================================
    // COMPREHENSIVE MATCH SYSTEM HELPERS
    // ===================================

    private function initializeHeroData($format)
    {
        $formatMapping = [
            'BO1' => 1, 'BO3' => 3, 'BO5' => 5, 'BO7' => 7, 'BO9' => 9
        ];
        
        $maxMaps = $formatMapping[$format] ?? 3;
        $heroData = [];

        for ($i = 0; $i < $maxMaps; $i++) {
            $heroData[] = [
                'map_number' => $i + 1,
                'team1_heroes' => [],
                'team2_heroes' => [],
                'bans' => [
                    'team1_bans' => [],
                    'team2_bans' => []
                ],
                'pick_order' => [],
                'draft_completed' => false
            ];
        }

        return $heroData;
    }

    private function initializePlayerStats($team1Id, $team2Id)
    {
        // Get players for both teams with full details
        $team1Players = DB::table('players')->where('team_id', $team1Id)->where('status', 'active')->get();
        $team2Players = DB::table('players')->where('team_id', $team2Id)->where('status', 'active')->get();
        
        $playerStats = [];

        foreach ($team1Players as $player) {
            $playerStats[$player->id] = [
                'player_id' => $player->id,
                'team_id' => $team1Id,
                'name' => $player->username ?? $player->real_name,
                'username' => $player->username,
                'real_name' => $player->real_name,
                'role' => $player->role,
                'main_hero' => $player->main_hero ?? 'Captain America',
                'avatar' => $player->avatar,
                'country' => $player->country ?? 'US',
                'nationality' => $player->country ?? 'US',
                'flag' => $this->getCountryFlag($player->country ?? 'US'),
                'maps_played' => 0,
                'total_stats' => [
                    'eliminations' => 0,
                    'deaths' => 0,
                    'assists' => 0,
                    'damage' => 0,
                    'healing' => 0,
                    'damage_blocked' => 0,
                    'ultimate_usage' => 0,
                    'objective_time' => 0,
                    'kd_ratio' => 0.0
                ],
                'map_stats' => []
            ];
        }

        foreach ($team2Players as $player) {
            $playerStats[$player->id] = [
                'player_id' => $player->id,
                'team_id' => $team2Id,
                'name' => $player->username ?? $player->real_name,
                'username' => $player->username,
                'real_name' => $player->real_name,
                'role' => $player->role,
                'main_hero' => $player->main_hero ?? 'Captain America',
                'avatar' => $player->avatar,
                'country' => $player->country ?? 'US',
                'nationality' => $player->country ?? 'US',
                'flag' => $this->getCountryFlag($player->country ?? 'US'),
                'maps_played' => 0,
                'total_stats' => [
                    'eliminations' => 0,
                    'deaths' => 0,
                    'assists' => 0,
                    'damage' => 0,
                    'healing' => 0,
                    'damage_blocked' => 0,
                    'ultimate_usage' => 0,
                    'objective_time' => 0,
                    'kd_ratio' => 0.0
                ],
                'map_stats' => []
            ];
        }

        return $playerStats;
    }



    // Live match update methods
    public function updateLiveScore(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        $request->validate([
            'map_number' => 'required|integer|min:1',
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'winner_id' => 'nullable|exists:teams,id'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $mapsData = json_decode($match->maps_data, true);
            $mapIndex = $request->map_number - 1;

            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Invalid map number'], 400);
            }

            // Update map score
            $mapsData[$mapIndex]['team1_score'] = $request->team1_score;
            $mapsData[$mapIndex]['team2_score'] = $request->team2_score;
            $mapsData[$mapIndex]['status'] = 'live';

            if ($request->winner_id) {
                $mapsData[$mapIndex]['winner_id'] = $request->winner_id;
                $mapsData[$mapIndex]['status'] = 'completed';
                $mapsData[$mapIndex]['completed_at'] = now();

                // Update series score
                $seriesScore1 = $match->series_score_team1;
                $seriesScore2 = $match->series_score_team2;
                
                if ($request->winner_id == $match->team1_id) {
                    $seriesScore1++;
                } else {
                    $seriesScore2++;
                }

                DB::table('matches')->where('id', $matchId)->update([
                    'series_score_team1' => $seriesScore1,
                    'series_score_team2' => $seriesScore2
                ]);
            }

            DB::table('matches')->where('id', $matchId)->update([
                'maps_data' => json_encode($mapsData),
                'current_map_number' => $request->map_number,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Live score updated',
                'data' => [
                    'map_score' => [
                        'team1' => $request->team1_score,
                        'team2' => $request->team2_score
                    ],
                    'series_score' => [
                        'team1' => $seriesScore1 ?? $match->series_score_team1,
                        'team2' => $seriesScore2 ?? $match->series_score_team2
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating live score: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateLiveTimer(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        $request->validate([
            'minutes' => 'required|integer|min:0|max:60',
            'seconds' => 'required|integer|min:0|max:59',
            'phase' => 'required|in:preparation,action,overtime,completed'
        ]);

        try {
            $timerData = [
                'minutes' => $request->minutes,
                'seconds' => $request->seconds,
                'phase' => $request->phase,
                'last_updated' => now()->toISOString()
            ];

            DB::table('matches')->where('id', $matchId)->update([
                'match_timer' => json_encode($timerData),
                'overtime' => $request->phase === 'overtime',
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Timer updated',
                'data' => $timerData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating timer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateHeroSelection(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        $request->validate([
            'map_number' => 'required|integer|min:1',
            'team_id' => 'required|exists:teams,id',
            'player_id' => 'required|exists:players,id',
            'hero_name' => 'required|string',
            'action' => 'required|in:pick,ban'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $heroData = json_decode($match->hero_data, true);
            $mapIndex = $request->map_number - 1;

            if (!isset($heroData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Invalid map number'], 400);
            }

            $teamKey = $request->team_id == $match->team1_id ? 'team1' : 'team2';

            if ($request->action === 'pick') {
                $heroData[$mapIndex][$teamKey . '_heroes'][] = [
                    'player_id' => $request->player_id,
                    'hero_name' => $request->hero_name,
                    'picked_at' => now()->toISOString()
                ];
            } else {
                $heroData[$mapIndex]['bans'][$teamKey . '_bans'][] = [
                    'hero_name' => $request->hero_name,
                    'banned_at' => now()->toISOString()
                ];
            }

            DB::table('matches')->where('id', $matchId)->update([
                'hero_data' => json_encode($heroData),
                'updated_at' => now()
            ]);

            // Broadcast the hero update event for real-time updates
            broadcast(new MatchHeroUpdated(
                $matchId,
                $request->map_number,
                $request->team_id,
                $request->player_id,
                $request->hero_name,
                $request->action,
                $heroData[$mapIndex]
            ));

            return response()->json([
                'success' => true,
                'message' => ucfirst($request->action) . ' recorded',
                'data' => $heroData[$mapIndex]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating hero selection: ' . $e->getMessage()
            ], 500);
        }
    }


    private function getTeamData($teamId)
    {
        $team = DB::table('teams')->where('id', $teamId)->first();
        if (!$team) return null;

        $players = DB::table('players')->where('team_id', $teamId)->get();

        return [
            'id' => $team->id,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo' => $team->logo,
            'country_flag' => $team->country_flag ?: $this->getCountryFlag($team->country),
            'region' => $team->region,
            'rating' => $team->rating,
            'players' => $players->map(function($player) {
                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'role' => $player->role,
                    'country' => $player->country,
                    'hero_pool' => $player->hero_pool ? explode(',', $player->hero_pool) : []
                ];
            })->toArray()
        ];
    }

    private function getEventData($eventId)
    {
        return DB::table('events')->where('id', $eventId)->first();
    }

    // New methods for enhanced live scoring

    public function transitionToNextMap(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('moderate-matches');
        }
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match || $match->status !== 'live') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match must be live to transition maps'
                ], 400);
            }

            $mapsData = json_decode($match->maps_data, true) ?? [];
            $currentMapIndex = $match->current_map_number - 1;
            
            // Complete current map
            if (isset($mapsData[$currentMapIndex])) {
                $mapsData[$currentMapIndex]['status'] = 'completed';
                $mapsData[$currentMapIndex]['completed_at'] = now();
                
                // Determine map winner
                if ($mapsData[$currentMapIndex]['team1_score'] > $mapsData[$currentMapIndex]['team2_score']) {
                    $mapsData[$currentMapIndex]['winner_id'] = $match->team1_id;
                } else {
                    $mapsData[$currentMapIndex]['winner_id'] = $match->team2_id;
                }
            }
            
            // Check if there's a next map
            $nextMapIndex = $currentMapIndex + 1;
            if ($nextMapIndex >= count($mapsData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No more maps available. Consider completing the match.'
                ], 400);
            }
            
            // Initialize next map
            $mapsData[$nextMapIndex]['status'] = 'upcoming';
            $mapsData[$nextMapIndex]['started_at'] = null;
            
            DB::table('matches')->where('id', $matchId)->update([
                'current_map_number' => $nextMapIndex + 1,
                'maps_data' => json_encode($mapsData),
                'is_preparation_phase' => true,
                'preparation_timer' => 45,
                'updated_at' => now()
            ]);
            
            // Broadcast map transition event
            event(new \App\Events\MatchMapTransition($matchId, $nextMapIndex + 1));
            
            return response()->json([
                'success' => true,
                'message' => 'Transitioned to next map',
                'data' => [
                    'current_map' => $nextMapIndex + 1,
                    'preparation_phase' => true
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error transitioning maps: ' . $e->getMessage()
            ], 500);
        }
    }

    public function startMap(Request $request, $matchId, $mapNumber)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $mapsData = json_decode($match->maps_data, true);
            $mapIndex = $mapNumber - 1;

            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Invalid map number'], 400);
            }

            // Update map status
            $mapsData[$mapIndex]['status'] = 'live';
            $mapsData[$mapIndex]['started_at'] = now()->toISOString();

            // Create match_rounds entry if needed
            $roundExists = DB::table('match_rounds')
                ->where('match_id', $matchId)
                ->where('round_number', $mapNumber)
                ->exists();

            if (!$roundExists) {
                DB::table('match_rounds')->insert([
                    'match_id' => $matchId,
                    'round_number' => $mapNumber,
                    'map_name' => $mapsData[$mapIndex]['map_name'],
                    'game_mode' => $mapsData[$mapIndex]['game_mode'],
                    'status' => 'live',
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::table('matches')->where('id', $matchId)->update([
                'maps_data' => json_encode($mapsData),
                'current_map_number' => $mapNumber,
                'status' => 'live',
                'updated_at' => now()
            ]);

            // Broadcast map start event
            event(new \App\Events\MatchMapStarted($matchId, $mapNumber, $mapsData[$mapIndex]));

            return response()->json([
                'success' => true,
                'message' => 'Map started',
                'data' => $mapsData[$mapIndex]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting map: ' . $e->getMessage()
            ], 500);
        }
    }

    public function endMap(Request $request, $matchId, $mapNumber)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        $request->validate([
            'winner_id' => 'required|exists:teams,id'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $mapsData = json_decode($match->maps_data, true);
            $mapIndex = $mapNumber - 1;

            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Invalid map number'], 400);
            }

            // Update map status
            $mapsData[$mapIndex]['status'] = 'completed';
            $mapsData[$mapIndex]['completed_at'] = now()->toISOString();
            $mapsData[$mapIndex]['winner_id'] = $request->winner_id;

            // Calculate duration
            if (isset($mapsData[$mapIndex]['started_at'])) {
                $start = \Carbon\Carbon::parse($mapsData[$mapIndex]['started_at']);
                $duration = $start->diffInSeconds(now());
                $mapsData[$mapIndex]['duration'] = gmdate('H:i:s', $duration);
            }

            // Update series score
            $seriesScore1 = $match->series_score_team1;
            $seriesScore2 = $match->series_score_team2;
            
            if ($request->winner_id == $match->team1_id) {
                $seriesScore1++;
            } else {
                $seriesScore2++;
            }

            // Check if match is complete
            $format = $match->format;
            $requiredWins = [
                'BO1' => 1, 'BO3' => 2, 'BO5' => 3, 'BO7' => 4, 'BO9' => 5
            ][$format] ?? 2;

            $matchCompleted = $seriesScore1 >= $requiredWins || $seriesScore2 >= $requiredWins;

            // Update match_rounds
            DB::table('match_rounds')
                ->where('match_id', $matchId)
                ->where('round_number', $mapNumber)
                ->update([
                    'status' => 'completed',
                    'winner_id' => $request->winner_id,
                    'team1_score' => $mapsData[$mapIndex]['team1_score'],
                    'team2_score' => $mapsData[$mapIndex]['team2_score'],
                    'updated_at' => now()
                ]);

            DB::table('matches')->where('id', $matchId)->update([
                'maps_data' => json_encode($mapsData),
                'series_score_team1' => $seriesScore1,
                'series_score_team2' => $seriesScore2,
                'status' => $matchCompleted ? 'completed' : 'live',
                'winner_id' => $matchCompleted ? ($seriesScore1 > $seriesScore2 ? $match->team1_id : $match->team2_id) : null,
                'completed_at' => $matchCompleted ? now() : null,
                'updated_at' => now()
            ]);

            // Broadcast map end event
            event(new \App\Events\MatchMapEnded($matchId, $mapNumber, $request->winner_id, $matchCompleted));

            // Update player hero stats if match is completed
            if ($matchCompleted) {
                $this->updatePlayerHeroStatsForMatch($matchId);
                
                // Progress tournament bracket if applicable
                $winner = $seriesScore1 > $seriesScore2 ? $match->team1_id : $match->team2_id;
                if ($match->event_id && $match->round && $winner) {
                    $this->progressTournamentBracket($matchId, $winner);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Map ended',
                'data' => [
                    'map' => $mapsData[$mapIndex],
                    'series_score' => [
                        'team1' => $seriesScore1,
                        'team2' => $seriesScore2
                    ],
                    'match_completed' => $matchCompleted
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error ending map: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addKillEvent(Request $request, $matchId)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        $request->validate([
            'map_number' => 'required|integer|min:1',
            'killer_id' => 'required|exists:players,id',
            'victim_id' => 'required|exists:players,id',
            'hero_killer' => 'required|string',
            'hero_victim' => 'required|string',
            'weapon' => 'nullable|string',
            'headshot' => 'boolean',
            'ability_used' => 'nullable|string',
            'timestamp' => 'required|string'
        ]);

        try {
            // Create kill event
            $eventId = DB::table('match_events')->insertGetId([
                'match_id' => $matchId,
                'map_number' => $request->map_number,
                'event_type' => 'kill',
                'player_id' => $request->killer_id,
                'target_player_id' => $request->victim_id,
                'description' => json_encode([
                    'killer' => [
                        'id' => $request->killer_id,
                        'hero' => $request->hero_killer
                    ],
                    'victim' => [
                        'id' => $request->victim_id,
                        'hero' => $request->hero_victim
                    ],
                    'weapon' => $request->weapon,
                    'headshot' => $request->headshot ?? false,
                    'ability_used' => $request->ability_used
                ]),
                'timestamp' => $request->timestamp,
                'game_time_seconds' => $request->game_time ?? 0,
                'created_at' => now()
            ]);

            // Broadcast kill event for live feed
            event(new \App\Events\MatchKillEvent($matchId, $request->all()));

            return response()->json([
                'success' => true,
                'message' => 'Kill event added',
                'data' => ['event_id' => $eventId]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding kill event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateObjective(Request $request, $matchId, $mapNumber)
    {
        // Skip authorization for admin routes since they're already protected by admin middleware
        if (!request()->is('api/admin/*')) {
            $this->authorize('manage-matches');
        }
        
        $request->validate([
            'objective_type' => 'required|in:capture,payload,hybrid',
            'progress' => 'required|numeric|min:0|max:100',
            'capturing_team' => 'nullable|exists:teams,id',
            'checkpoint_reached' => 'nullable|integer',
            'time_remaining' => 'nullable|integer'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $liveData = json_decode($match->live_data, true);
            
            // Update objective data
            $liveData['objectives'][$mapNumber] = [
                'type' => $request->objective_type,
                'progress' => $request->progress,
                'capturing_team' => $request->capturing_team,
                'checkpoint' => $request->checkpoint_reached,
                'time_remaining' => $request->time_remaining,
                'last_updated' => now()->toISOString()
            ];

            DB::table('matches')->where('id', $matchId)->update([
                'live_data' => json_encode($liveData),
                'updated_at' => now()
            ]);

            // Broadcast objective update
            event(new \App\Events\MatchObjectiveUpdate($matchId, $mapNumber, $liveData['objectives'][$mapNumber]));

            return response()->json([
                'success' => true,
                'message' => 'Objective updated',
                'data' => $liveData['objectives'][$mapNumber]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating objective: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getHeadToHead($team1Id, $team2Id)
    {
        try {
            // Get recent matches between the two teams
            $matches = DB::table('matches')
                ->where(function($query) use ($team1Id, $team2Id) {
                    $query->where('team1_id', $team1Id)->where('team2_id', $team2Id);
                })
                ->orWhere(function($query) use ($team1Id, $team2Id) {
                    $query->where('team1_id', $team2Id)->where('team2_id', $team1Id);
                })
                ->where('status', 'completed')
                ->orderBy('scheduled_at', 'desc')
                ->limit(10)
                ->get();

            // Calculate statistics
            $team1Wins = 0;
            $team2Wins = 0;
            $totalMaps = 0;
            $team1MapWins = 0;
            $team2MapWins = 0;

            foreach ($matches as $match) {
                if ($match->winner_id == $team1Id) {
                    $team1Wins++;
                } elseif ($match->winner_id == $team2Id) {
                    $team2Wins++;
                }

                // Count map wins
                $mapsData = json_decode($match->maps_data, true);
                if ($mapsData) {
                    foreach ($mapsData as $map) {
                        if (isset($map['winner_id'])) {
                            $totalMaps++;
                            if ($map['winner_id'] == $team1Id) {
                                $team1MapWins++;
                            } elseif ($map['winner_id'] == $team2Id) {
                                $team2MapWins++;
                            }
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_matches' => $matches->count(),
                    'team1_wins' => $team1Wins,
                    'team2_wins' => $team2Wins,
                    'team1_map_wins' => $team1MapWins,
                    'team2_map_wins' => $team2MapWins,
                    'total_maps' => $totalMaps,
                    'recent_matches' => $matches->take(5)->map(function($match) {
                        return [
                            'id' => $match->id,
                            'date' => $match->scheduled_at,
                            'winner_id' => $match->winner_id,
                            'score' => [
                                'team1' => $match->team1_score,
                                'team2' => $match->team2_score
                            ],
                            'format' => $match->format
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching head-to-head data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getComments($matchId)
    {
        try {
            $comments = DB::table('match_comments')
                ->join('users', 'match_comments.user_id', '=', 'users.id')
                ->where('match_comments.match_id', $matchId)
                ->where('match_comments.parent_id', null)
                ->select(
                    'match_comments.*',
                    'users.name as user_name',
                    'users.avatar',
                    'users.hero_flair',
                    'users.team_flair_id'
                )
                ->orderBy('match_comments.created_at', 'desc')
                ->get();

            // Get replies for each comment
            foreach ($comments as $comment) {
                $comment->replies = DB::table('match_comments')
                    ->join('users', 'match_comments.user_id', '=', 'users.id')
                    ->where('match_comments.parent_id', $comment->id)
                    ->select(
                        'match_comments.*',
                        'users.name as user_name',
                        'users.avatar',
                        'users.hero_flair',
                        'users.team_flair_id'
                    )
                    ->orderBy('match_comments.created_at', 'asc')
                    ->get();
                
                // Format timestamps
                $comment->created_at_formatted = Carbon::parse($comment->created_at)->diffForHumans();
                foreach ($comment->replies as $reply) {
                    $reply->created_at_formatted = Carbon::parse($reply->created_at)->diffForHumans();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $comments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching comments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update player hero stats after match completion
     */
    private function updatePlayerHeroStatsForMatch($matchId)
    {
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) return;

            $mapsData = json_decode($match->maps_data, true) ?? [];

            // Process each map
            foreach ($mapsData as $mapIndex => $map) {
                if (!isset($map['team1_composition']) || !isset($map['team2_composition'])) continue;

                // Process team 1 players
                foreach ($map['team1_composition'] as $playerData) {
                    if (isset($playerData['player_id']) && isset($playerData['hero'])) {
                        $this->updatePlayerHeroStat(
                            $playerData['player_id'],
                            $playerData['hero'],
                            $playerData,
                            $map['winner_id'] == $match->team1_id
                        );
                    }
                }

                // Process team 2 players
                foreach ($map['team2_composition'] as $playerData) {
                    if (isset($playerData['player_id']) && isset($playerData['hero'])) {
                        $this->updatePlayerHeroStat(
                            $playerData['player_id'],
                            $playerData['hero'],
                            $playerData,
                            $map['winner_id'] == $match->team2_id
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error updating player hero stats: ' . $e->getMessage());
        }
    }

    /**
     * Update individual player hero stat
     */
    private function updatePlayerHeroStat($playerId, $heroName, $playerData, $won)
    {
        try {
            // Get existing stats or create new
            $existingStats = DB::table('player_hero_stats')
                ->where('player_id', $playerId)
                ->where('hero_name', $heroName)
                ->first();

            $stats = [
                'matches_played' => ($existingStats->matches_played ?? 0) + 1,
                'wins' => ($existingStats->wins ?? 0) + ($won ? 1 : 0),
                'losses' => ($existingStats->losses ?? 0) + ($won ? 0 : 1),
                'total_eliminations' => ($existingStats->total_eliminations ?? 0) + ($playerData['stats']['eliminations'] ?? 0),
                'total_deaths' => ($existingStats->total_deaths ?? 0) + ($playerData['stats']['deaths'] ?? 0),
                'total_assists' => ($existingStats->total_assists ?? 0) + ($playerData['stats']['assists'] ?? 0),
                'total_damage' => ($existingStats->total_damage ?? 0) + ($playerData['stats']['damage'] ?? 0),
                'total_healing' => ($existingStats->total_healing ?? 0) + ($playerData['stats']['healing'] ?? 0),
                'total_blocked' => ($existingStats->total_blocked ?? 0) + ($playerData['stats']['blocked'] ?? 0),
                'total_performance_rating' => ($existingStats->total_performance_rating ?? 0) + ($playerData['stats']['performance_rating'] ?? 1.0),
                'updated_at' => now()
            ];

            // Calculate averages
            $stats['win_rate'] = $stats['matches_played'] > 0 ? round(($stats['wins'] / $stats['matches_played']) * 100, 2) : 0;
            $stats['avg_eliminations'] = $stats['matches_played'] > 0 ? round($stats['total_eliminations'] / $stats['matches_played'], 2) : 0;
            $stats['avg_deaths'] = $stats['matches_played'] > 0 ? round($stats['total_deaths'] / $stats['matches_played'], 2) : 0;
            $stats['avg_assists'] = $stats['matches_played'] > 0 ? round($stats['total_assists'] / $stats['matches_played'], 2) : 0;
            $stats['avg_damage'] = $stats['matches_played'] > 0 ? round($stats['total_damage'] / $stats['matches_played'], 0) : 0;
            $stats['avg_healing'] = $stats['matches_played'] > 0 ? round($stats['total_healing'] / $stats['matches_played'], 0) : 0;
            $stats['avg_blocked'] = $stats['matches_played'] > 0 ? round($stats['total_blocked'] / $stats['matches_played'], 0) : 0;
            $stats['avg_performance_rating'] = $stats['matches_played'] > 0 ? round($stats['total_performance_rating'] / $stats['matches_played'], 2) : 0;

            if ($existingStats) {
                DB::table('player_hero_stats')
                    ->where('player_id', $playerId)
                    ->where('hero_name', $heroName)
                    ->update($stats);
            } else {
                $stats['player_id'] = $playerId;
                $stats['hero_name'] = $heroName;
                $stats['created_at'] = now();
                DB::table('player_hero_stats')->insert($stats);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating individual player hero stat: ' . $e->getMessage());
        }
    }

    /**
     * Progress tournament bracket when a match is completed
     */
    private function progressTournamentBracket($matchId, $winnerId)
    {
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match || !$match->event_id || !$match->round) return;

            // Get bracket structure for the event
            $bracketData = DB::table('events')
                ->where('id', $match->event_id)
                ->value('bracket_data');
            
            if (!$bracketData) return;
            
            $bracket = json_decode($bracketData, true);
            if (!$bracket || !isset($bracket['matches'])) return;

            // Find the next match in the bracket
            $currentRound = $match->round;
            $bracketPosition = $match->bracket_position;
            
            // Calculate next round and position
            $nextRound = null;
            $nextPosition = null;
            
            // Common tournament progressions
            $roundProgressions = [
                'round_of_32' => 'round_of_16',
                'round_of_16' => 'quarterfinals',
                'quarterfinals' => 'semifinals',
                'semifinals' => 'finals',
                'lower_round_1' => 'lower_round_2',
                'lower_round_2' => 'lower_round_3',
                'lower_round_3' => 'lower_round_4',
                'lower_round_4' => 'lower_finals',
                'lower_finals' => 'grand_finals'
            ];
            
            $nextRound = $roundProgressions[$currentRound] ?? null;
            
            if ($nextRound) {
                // Calculate next bracket position (winner advances to match at position / 2)
                $nextPosition = ceil($bracketPosition / 2);
                
                // Find or create the next match
                $nextMatch = DB::table('matches')
                    ->where('event_id', $match->event_id)
                    ->where('round', $nextRound)
                    ->where('bracket_position', $nextPosition)
                    ->first();
                
                if ($nextMatch) {
                    // Determine which team slot to fill
                    $isUpperBracketSlot = ($bracketPosition % 2) == 1;
                    $teamSlot = $isUpperBracketSlot ? 'team1_id' : 'team2_id';
                    
                    // Update the next match with the winner
                    DB::table('matches')
                        ->where('id', $nextMatch->id)
                        ->update([
                            $teamSlot => $winnerId,
                            'updated_at' => now()
                        ]);
                    
                    // Broadcast bracket update through match update event
                    event(new \App\Events\MatchUpdated($nextMatch->id, 'bracket_progression'));
                    
                    \Log::info("Tournament progression: Match $matchId winner (Team $winnerId) advances to $nextRound position $nextPosition");
                }
            }
            
            // Handle grand finals reset if applicable
            if ($currentRound === 'grand_finals' && isset($bracket['type']) && $bracket['type'] === 'double_elimination') {
                // Check if this was the first grand finals match
                $grandFinalsMatches = DB::table('matches')
                    ->where('event_id', $match->event_id)
                    ->where('round', 'grand_finals')
                    ->count();
                
                if ($grandFinalsMatches === 1) {
                    // Check if lower bracket team won (would need a reset)
                    $lowerBracketTeam = DB::table('matches')
                        ->where('event_id', $match->event_id)
                        ->where('round', 'lower_finals')
                        ->where('status', 'completed')
                        ->value('winner_id');
                    
                    if ($lowerBracketTeam && $winnerId == $lowerBracketTeam) {
                        // Create grand finals reset match
                        DB::table('matches')->insert([
                            'event_id' => $match->event_id,
                            'team1_id' => $match->team1_id,
                            'team2_id' => $match->team2_id,
                            'round' => 'grand_finals_reset',
                            'bracket_position' => 1,
                            'format' => $match->format,
                            'status' => 'upcoming',
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        
                        \Log::info("Grand finals reset created for event {$match->event_id}");
                    }
                }
            }
            
        } catch (\Exception $e) {
            \Log::error('Error progressing tournament bracket: ' . $e->getMessage());
        }
    }
    
    /**
     * Extract timer value from match_timer JSON field
     */
    private function extractTimerValue($matchTimer)
    {
        if (!$matchTimer) {
            return '00:00';
        }
        
        if (is_string($matchTimer)) {
            $decoded = json_decode($matchTimer, true);
            if ($decoded && isset($decoded['time'])) {
                return $decoded['time'];
            }
        } elseif (is_array($matchTimer) && isset($matchTimer['time'])) {
            return $matchTimer['time'];
        }
        
        return '00:00';
    }

    /**
     * Server-sent events endpoint for real-time match updates
     */
    public function liveStream(Request $request, $id)
    {
        try {
            // Set headers for Server-Sent Events
            $headers = [
                'Cache-Control' => 'no-cache',
                'Content-Type' => 'text/event-stream',
                'Connection' => 'keep-alive',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Headers' => 'Cache-Control'
            ];

            $response = response()->stream(function() use ($id) {
                // Send initial connection event
                echo "event: connected\n";
                echo "data: " . json_encode(['message' => 'Connected to match ' . $id, 'timestamp' => now()->toISOString()]) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();

                // Keep the connection alive and send periodic updates
                $lastUpdate = 0;
                $maxDuration = 300; // 5 minutes max connection time
                $startTime = time();

                while (connection_aborted() == 0 && (time() - $startTime) < $maxDuration) {
                    try {
                        // Get current match data
                        $match = DB::table('matches')->where('id', $id)->first();
                        
                        if ($match && $match->status === 'live') {
                            $currentUpdate = strtotime($match->updated_at);
                            
                            // Send update if match data has changed
                            if ($currentUpdate > $lastUpdate) {
                                // Get detailed match data
                                $fullMatch = $this->show(new Request(), $id);
                                $matchData = $fullMatch->getData();
                                
                                // Send score update
                                echo "event: score-update\n";
                                echo "data: " . json_encode([
                                    'team1_score' => $match->team1_score,
                                    'team2_score' => $match->team2_score,
                                    'status' => $match->status,
                                    'timestamp' => now()->toISOString()
                                ]) . "\n\n";
                                
                                // Send status update if needed
                                echo "event: status-update\n";
                                echo "data: " . json_encode([
                                    'status' => $match->status,
                                    'timestamp' => now()->toISOString()
                                ]) . "\n\n";
                                
                                $lastUpdate = $currentUpdate;
                                if (ob_get_level()) ob_flush();
                                flush();
                            }
                        }
                    } catch (\Exception $e) {
                        // Log error but continue connection
                        \Log::error('SSE Error: ' . $e->getMessage());
                    }
                    
                    // Sleep for 1 second before next check
                    sleep(1);
                }
                
                // Send close event
                echo "event: close\n";
                echo "data: " . json_encode(['message' => 'Connection closed', 'timestamp' => now()->toISOString()]) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                
            }, 200, $headers);

            return $response;
            
        } catch (\Exception $e) {
            \Log::error('Live stream error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to establish live stream',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Live Score Update Endpoint - Real-time match score synchronization
     * POST /api/matches/{id}/live-update
     */
    public function liveUpdate(Request $request, $matchId)
    {
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            $validated = $request->validate([
                'team1_score' => 'sometimes|integer|min:0',
                'team2_score' => 'sometimes|integer|min:0',
                'current_map' => 'sometimes|integer|min:1',
                'status' => 'sometimes|string|in:upcoming,live,completed,cancelled,postponed',
                'timer' => 'sometimes|string',
                'viewers' => 'sometimes|integer|min:0',
                'map_scores' => 'sometimes|array'
            ]);

            // Update match fields
            $updateData = [];
            if (isset($validated['team1_score'])) {
                $updateData['team1_score'] = $validated['team1_score'];
            }
            if (isset($validated['team2_score'])) {
                $updateData['team2_score'] = $validated['team2_score'];
            }
            if (isset($validated['current_map'])) {
                $updateData['current_map_number'] = $validated['current_map'];
            }
            if (isset($validated['status'])) {
                $updateData['status'] = $validated['status'];
            }
            if (isset($validated['timer'])) {
                $updateData['live_timer'] = $validated['timer'];
            }
            if (isset($validated['viewers'])) {
                $updateData['viewers'] = $validated['viewers'];
            }

            // Handle map scores update
            if (isset($validated['map_scores'])) {
                $mapsData = json_decode($match->maps_data, true) ?? [];
                foreach ($validated['map_scores'] as $mapUpdate) {
                    if (isset($mapUpdate['map_number'])) {
                        $mapIndex = $mapUpdate['map_number'] - 1;
                        if (isset($mapsData[$mapIndex])) {
                            if (isset($mapUpdate['team1_score'])) {
                                $mapsData[$mapIndex]['team1_score'] = $mapUpdate['team1_score'];
                            }
                            if (isset($mapUpdate['team2_score'])) {
                                $mapsData[$mapIndex]['team2_score'] = $mapUpdate['team2_score'];
                            }
                            if (isset($mapUpdate['winner_id'])) {
                                $mapsData[$mapIndex]['winner_id'] = $mapUpdate['winner_id'];
                            }
                            if (isset($mapUpdate['status'])) {
                                $mapsData[$mapIndex]['status'] = $mapUpdate['status'];
                            }
                        }
                    }
                }
                $updateData['maps_data'] = json_encode($mapsData);
            }

            $updateData['updated_at'] = now();

            // Update database
            DB::table('matches')->where('id', $matchId)->update($updateData);

            // Get updated match data
            $updatedMatch = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.id', $matchId)
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short',
                    't2.name as team2_name', 't2.short_name as team2_short'
                ])
                ->first();

            // Broadcast live update via WebSocket/Pusher
            $broadcastData = [
                'match_id' => $matchId,
                'team1_score' => $updatedMatch->team1_score,
                'team2_score' => $updatedMatch->team2_score,
                'team1_name' => $updatedMatch->team1_name,
                'team1_short' => $updatedMatch->team1_short,
                'team2_name' => $updatedMatch->team2_name,
                'team2_short' => $updatedMatch->team2_short,
                'status' => $updatedMatch->status,
                'current_map' => $updatedMatch->current_map_number,
                'timer' => $updatedMatch->live_timer,
                'viewers' => $updatedMatch->viewers,
                'maps_data' => json_decode($updatedMatch->maps_data, true),
                'timestamp' => now()->toISOString()
            ];

            // Broadcast to match-specific channel
            $this->broadcastMatchUpdate($matchId, 'live-score-update', $broadcastData);

            return response()->json([
                'success' => true,
                'message' => 'Live score updated successfully',
                'data' => $broadcastData
            ]);

        } catch (\Exception $e) {
            \Log::error('Live update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update live score: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Player Statistics Update Endpoint
     * POST /api/matches/{id}/player-stats
     */
    public function playerStatsUpdate(Request $request, $matchId)
    {
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            $validated = $request->validate([
                'map_number' => 'required|integer|min:1',
                'team' => 'required|string|in:team1,team2',
                'player_stats' => 'required|array',
                'player_stats.*.player_id' => 'required|integer',
                'player_stats.*.eliminations' => 'sometimes|integer|min:0',
                'player_stats.*.deaths' => 'sometimes|integer|min:0',
                'player_stats.*.assists' => 'sometimes|integer|min:0',
                'player_stats.*.damage' => 'sometimes|integer|min:0',
                'player_stats.*.healing' => 'sometimes|integer|min:0',
                'player_stats.*.damage_blocked' => 'sometimes|integer|min:0',
                'player_stats.*.ultimate_usage' => 'sometimes|integer|min:0',
                'player_stats.*.objective_time' => 'sometimes|integer|min:0'
            ]);

            // Update maps data with player stats
            $mapsData = json_decode($match->maps_data, true) ?? [];
            $mapIndex = $validated['map_number'] - 1;
            $compositionKey = $validated['team'] . '_composition';

            if (isset($mapsData[$mapIndex][$compositionKey])) {
                foreach ($validated['player_stats'] as $playerStat) {
                    // Find player in composition and update stats
                    foreach ($mapsData[$mapIndex][$compositionKey] as &$player) {
                        if ($player['player_id'] == $playerStat['player_id']) {
                            // Update all provided stats
                            foreach ($playerStat as $stat => $value) {
                                if ($stat !== 'player_id') {
                                    $player[$stat] = $value;
                                }
                            }
                            break;
                        }
                    }
                }
            }

            // Save updated maps data
            DB::table('matches')->where('id', $matchId)->update([
                'maps_data' => json_encode($mapsData),
                'updated_at' => now()
            ]);

            // Broadcast player stats update
            $broadcastData = [
                'match_id' => $matchId,
                'map_number' => $validated['map_number'],
                'team' => $validated['team'],
                'player_stats' => $validated['player_stats'],
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastMatchUpdate($matchId, 'player-stats-update', $broadcastData);

            return response()->json([
                'success' => true,
                'message' => 'Player stats updated successfully',
                'data' => $broadcastData
            ]);

        } catch (\Exception $e) {
            \Log::error('Player stats update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update player stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hero Selection Update Endpoint
     * POST /api/matches/{id}/hero-update
     */
    public function heroUpdate(Request $request, $matchId)
    {
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            $validated = $request->validate([
                'map_number' => 'required|integer|min:1',
                'team' => 'required|string|in:team1,team2',
                'player_id' => 'required|integer',
                'hero' => 'required|string',
                'role' => 'sometimes|string'
            ]);

            // Update hero selection in maps data
            $mapsData = json_decode($match->maps_data, true) ?? [];
            $mapIndex = $validated['map_number'] - 1;
            $compositionKey = $validated['team'] . '_composition';

            if (isset($mapsData[$mapIndex][$compositionKey])) {
                foreach ($mapsData[$mapIndex][$compositionKey] as &$player) {
                    if ($player['player_id'] == $validated['player_id']) {
                        $player['hero'] = $validated['hero'];
                        if (isset($validated['role'])) {
                            $player['role'] = $validated['role'];
                        }
                        break;
                    }
                }
            }

            // Save updated maps data
            DB::table('matches')->where('id', $matchId)->update([
                'maps_data' => json_encode($mapsData),
                'updated_at' => now()
            ]);

            // Fire hero updated event
            event(new MatchHeroUpdated($matchId, $validated));

            // Broadcast hero change
            $broadcastData = [
                'match_id' => $matchId,
                'map_number' => $validated['map_number'],
                'team' => $validated['team'],
                'player_id' => $validated['player_id'],
                'hero' => $validated['hero'],
                'role' => $validated['role'] ?? null,
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastMatchUpdate($matchId, 'hero-update', $broadcastData);

            return response()->json([
                'success' => true,
                'message' => 'Hero selection updated successfully',
                'data' => $broadcastData
            ]);

        } catch (\Exception $e) {
            \Log::error('Hero update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update hero selection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map Result Update Endpoint
     * POST /api/matches/{id}/map-result
     */
    public function mapResult(Request $request, $matchId)
    {
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            $validated = $request->validate([
                'map_number' => 'required|integer|min:1',
                'team1_score' => 'required|integer|min:0',
                'team2_score' => 'required|integer|min:0',
                'winner_id' => 'sometimes|integer',
                'duration' => 'sometimes|string',
                'overtime' => 'sometimes|boolean',
                'status' => 'sometimes|string|in:upcoming,live,completed'
            ]);

            // Update map result in maps data
            $mapsData = json_decode($match->maps_data, true) ?? [];
            $mapIndex = $validated['map_number'] - 1;

            if (isset($mapsData[$mapIndex])) {
                $mapsData[$mapIndex]['team1_score'] = $validated['team1_score'];
                $mapsData[$mapIndex]['team2_score'] = $validated['team2_score'];
                $mapsData[$mapIndex]['status'] = $validated['status'] ?? 'completed';
                
                if (isset($validated['winner_id'])) {
                    $mapsData[$mapIndex]['winner_id'] = $validated['winner_id'];
                }
                if (isset($validated['duration'])) {
                    $mapsData[$mapIndex]['duration'] = $validated['duration'];
                }
                if (isset($validated['overtime'])) {
                    $mapsData[$mapIndex]['overtime'] = $validated['overtime'];
                }
            }

            // Calculate overall match score
            $team1_wins = 0;
            $team2_wins = 0;
            foreach ($mapsData as $map) {
                if (isset($map['winner_id'])) {
                    if ($map['winner_id'] == $match->team1_id) {
                        $team1_wins++;
                    } elseif ($map['winner_id'] == $match->team2_id) {
                        $team2_wins++;
                    }
                }
            }

            // Update match with new data
            $updateData = [
                'maps_data' => json_encode($mapsData),
                'team1_score' => $team1_wins,
                'team2_score' => $team2_wins,
                'updated_at' => now()
            ];

            // Check if match is completed
            $format = $match->format ?? 'BO3';
            $mapsToWin = match($format) {
                'BO1' => 1,
                'BO3' => 2,
                'BO5' => 3,
                'BO7' => 4,
                'BO9' => 5,
                default => 2
            };

            if ($team1_wins >= $mapsToWin || $team2_wins >= $mapsToWin) {
                $updateData['status'] = 'completed';
                $updateData['actual_end_time'] = now();
                $updateData['winner_id'] = $team1_wins > $team2_wins ? $match->team1_id : $match->team2_id;
            }

            DB::table('matches')->where('id', $matchId)->update($updateData);

            // Broadcast map result and overall score update
            $broadcastData = [
                'match_id' => $matchId,
                'map_number' => $validated['map_number'],
                'map_result' => $mapsData[$mapIndex],
                'overall_score' => [
                    'team1' => $team1_wins,
                    'team2' => $team2_wins
                ],
                'match_status' => $updateData['status'] ?? $match->status,
                'winner_id' => $updateData['winner_id'] ?? null,
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastMatchUpdate($matchId, 'map-result-update', $broadcastData);

            return response()->json([
                'success' => true,
                'message' => 'Map result updated successfully',
                'data' => $broadcastData
            ]);

        } catch (\Exception $e) {
            \Log::error('Map result update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update map result: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * WebSocket/Pusher Broadcasting Helper
     */
    private function broadcastMatchUpdate($matchId, $eventType, $data)
    {
        try {
            // If Pusher is configured, broadcast via Pusher
            if (config('broadcasting.connections.pusher.key')) {
                broadcast(new \App\Events\MatchUpdate($matchId, $eventType, $data));
            }
            
            // Log the broadcast for debugging
            \Log::info("Broadcasting match update: {$eventType} for match {$matchId}", $data);
            
        } catch (\Exception $e) {
            \Log::error("Failed to broadcast match update: " . $e->getMessage());
        }
    }

    // ===================================================================
    // SIMPLE REAL-TIME SCORING SYNCHRONIZATION SYSTEM - API CALLS ONLY
    // ===================================================================

    /**
     * Update overall and map scores
     * POST /api/matches/{id}/update-score
     */
    public function updateScore(Request $request, $matchId)
    {
        try {
            $request->validate([
                'team1_score' => 'required|integer|min:0',
                'team2_score' => 'required|integer|min:0',
                'map_scores' => 'sometimes|array',
                'map_scores.*.map_number' => 'integer|min:1',
                'map_scores.*.team1_score' => 'integer|min:0',
                'map_scores.*.team2_score' => 'integer|min:0',
                'map_scores.*.winner_id' => 'nullable|integer',
                'current_map' => 'sometimes|integer|min:1'
            ]);

            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            // Update overall match scores
            DB::table('matches')->where('id', $matchId)->update([
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'current_map' => $request->get('current_map', 1),
                'updated_at' => now()
            ]);

            // Update individual map scores if provided
            if ($request->has('map_scores')) {
                $mapsData = json_decode($match->maps_data, true) ?? [];
                
                foreach ($request->map_scores as $mapScore) {
                    $mapIndex = $mapScore['map_number'] - 1;
                    if (isset($mapsData[$mapIndex])) {
                        $mapsData[$mapIndex]['team1_score'] = $mapScore['team1_score'];
                        $mapsData[$mapIndex]['team2_score'] = $mapScore['team2_score'];
                        $mapsData[$mapIndex]['winner_id'] = $mapScore['winner_id'] ?? null;
                        $mapsData[$mapIndex]['status'] = $mapScore['winner_id'] ? 'completed' : 'upcoming';
                    }
                }

                DB::table('matches')->where('id', $matchId)->update([
                    'maps_data' => json_encode($mapsData),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Match scores updated successfully',
                'data' => [
                    'match_id' => $matchId,
                    'team1_score' => $request->team1_score,
                    'team2_score' => $request->team2_score,
                    'current_map' => $request->get('current_map', 1),
                    'updated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Update score error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to update scores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update player K/D/A stats
     * POST /api/matches/{id}/update-player-stats
     */
    public function updatePlayerStatsSimple(Request $request, $matchId)
    {
        try {
            $request->validate([
                'map_number' => 'required|integer|min:1',
                'team' => 'required|in:team1,team2',
                'player_stats' => 'required|array',
                'player_stats.*.player_id' => 'required|integer',
                'player_stats.*.eliminations' => 'sometimes|integer|min:0',
                'player_stats.*.deaths' => 'sometimes|integer|min:0',
                'player_stats.*.assists' => 'sometimes|integer|min:0',
                'player_stats.*.damage' => 'sometimes|integer|min:0',
                'player_stats.*.healing' => 'sometimes|integer|min:0'
            ]);

            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $mapsData = json_decode($match->maps_data, true) ?? [];
            $mapIndex = $request->map_number - 1;

            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Map not found'], 404);
            }

            // Update player stats in the specific map
            $teamKey = $request->team . '_composition';
            if (!isset($mapsData[$mapIndex][$teamKey])) {
                $mapsData[$mapIndex][$teamKey] = [];
            }

            foreach ($request->player_stats as $playerStat) {
                $playerIndex = collect($mapsData[$mapIndex][$teamKey])
                    ->search(fn($p) => $p['player_id'] == $playerStat['player_id']);

                if ($playerIndex !== false) {
                    // Update existing player stats
                    $mapsData[$mapIndex][$teamKey][$playerIndex] = array_merge(
                        $mapsData[$mapIndex][$teamKey][$playerIndex],
                        array_filter($playerStat, fn($key) => $key !== 'player_id', ARRAY_FILTER_USE_KEY)
                    );
                }
            }

            // Save updated maps data
            DB::table('matches')->where('id', $matchId)->update([
                'maps_data' => json_encode($mapsData),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Player stats updated successfully',
                'data' => [
                    'match_id' => $matchId,
                    'map_number' => $request->map_number,
                    'team' => $request->team,
                    'updated_players' => count($request->player_stats),
                    'updated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Update player stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to update player stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update hero selections
     * POST /api/matches/{id}/update-heroes
     */
    public function updateHeroes(Request $request, $matchId)
    {
        try {
            $request->validate([
                'map_number' => 'required|integer|min:1',
                'team' => 'required|in:team1,team2',
                'hero_selections' => 'required|array',
                'hero_selections.*.player_id' => 'required|integer',
                'hero_selections.*.hero' => 'required|string'
            ]);

            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $mapsData = json_decode($match->maps_data, true) ?? [];
            $mapIndex = $request->map_number - 1;

            if (!isset($mapsData[$mapIndex])) {
                return response()->json(['success' => false, 'message' => 'Map not found'], 404);
            }

            // Update hero selections for the specific team and map
            $teamKey = $request->team . '_composition';
            if (!isset($mapsData[$mapIndex][$teamKey])) {
                $mapsData[$mapIndex][$teamKey] = [];
            }

            foreach ($request->hero_selections as $heroSelection) {
                $playerIndex = collect($mapsData[$mapIndex][$teamKey])
                    ->search(fn($p) => $p['player_id'] == $heroSelection['player_id']);

                if ($playerIndex !== false) {
                    $mapsData[$mapIndex][$teamKey][$playerIndex]['hero'] = $heroSelection['hero'];
                }
            }

            // Save updated maps data
            DB::table('matches')->where('id', $matchId)->update([
                'maps_data' => json_encode($mapsData),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hero selections updated successfully',
                'data' => [
                    'match_id' => $matchId,
                    'map_number' => $request->map_number,
                    'team' => $request->team,
                    'updated_heroes' => count($request->hero_selections),
                    'updated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Update heroes error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to update hero selections: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all live match data
     * GET /api/matches/{id}/live-data
     */
    public function getLiveData($matchId)
    {
        try {
            $match = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->where('m.id', $matchId)
                ->select([
                    'm.*',
                    't1.id as team1_id', 't1.name as team1_name', 't1.short_name as team1_short',
                    't1.logo as team1_logo', 't1.region as team1_region',
                    't2.id as team2_id', 't2.name as team2_name', 't2.short_name as team2_short',
                    't2.logo as team2_logo', 't2.region as team2_region',
                    'e.id as event_id', 'e.name as event_name', 'e.type as event_type'
                ])
                ->first();

            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            // Parse maps data
            $mapsData = json_decode($match->maps_data, true) ?? [];
            
            // Calculate match progress
            $totalMaps = count($mapsData);
            $completedMaps = collect($mapsData)->where('status', 'completed')->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'match_id' => $match->id,
                    'status' => $match->status,
                    'format' => $match->format,
                    'current_map' => $match->current_map ?? 1,
                    'team1' => [
                        'id' => $match->team1_id,
                        'name' => $match->team1_name,
                        'short_name' => $match->team1_short,
                        'logo' => $match->team1_logo,
                        'region' => $match->team1_region,
                        'score' => $match->team1_score
                    ],
                    'team2' => [
                        'id' => $match->team2_id,
                        'name' => $match->team2_name,
                        'short_name' => $match->team2_short,
                        'logo' => $match->team2_logo,
                        'region' => $match->team2_region,
                        'score' => $match->team2_score
                    ],
                    'event' => $match->event_id ? [
                        'id' => $match->event_id,
                        'name' => $match->event_name,
                        'type' => $match->event_type
                    ] : null,
                    'maps' => $mapsData,
                    'progress' => [
                        'total_maps' => $totalMaps,
                        'completed_maps' => $completedMaps,
                        'percentage' => $totalMaps > 0 ? round(($completedMaps / $totalMaps) * 100, 1) : 0
                    ],
                    'last_updated' => $match->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Get live data error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to get live data: ' . $e->getMessage()
            ], 500);
        }
    }

}
