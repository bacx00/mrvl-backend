<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
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
        $this->authorize('manage-matches');
        
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
        $this->authorize('moderate-matches');
        
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
        $this->authorize('moderate-matches');
        
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
            $mapsData = $match->maps_data ?? [];

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
                    $this->updatePlayerStatsInternal($matchId, $request->data);
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
        $this->authorize('moderate-matches');
        
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

    public function pauseMatch(Request $request, $matchId)
    {
        $this->authorize('moderate-matches');
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match || $match->status !== 'live') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match must be live to pause'
                ], 400);
            }

            // Update match status and save current state
            $liveData = json_decode($match->live_data, true) ?? [];
            $liveData['paused_at'] = now();
            $liveData['timer_paused'] = true;
            $liveData['timer_status'] = 'paused';

            DB::table('matches')->where('id', $matchId)->update([
                'status' => 'paused',
                'live_data' => json_encode($liveData),
                'updated_at' => now()
            ]);

            // Broadcast pause event
            event(new \App\Events\MatchPaused($matchId));

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

    public function resumeMatch(Request $request, $matchId)
    {
        $this->authorize('moderate-matches');
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match || $match->status !== 'paused') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match must be paused to resume'
                ], 400);
            }

            // Update match status and restore timer
            $liveData = json_decode($match->live_data, true) ?? [];
            $liveData['resumed_at'] = now();
            $liveData['timer_paused'] = false;
            $liveData['timer_status'] = 'running';

            DB::table('matches')->where('id', $matchId)->update([
                'status' => 'live',
                'live_data' => json_encode($liveData),
                'updated_at' => now()
            ]);

            // Broadcast resume event
            event(new \App\Events\MatchResumed($matchId));

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

    public function restartMatch(Request $request, $matchId)
    {
        $this->authorize('moderate-matches');
        
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
                $mapsData = $match->maps_data ?? [];
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
        $this->authorize('moderate-matches');
        
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
            $mapsData = $match->maps_data ?? [];
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
        $this->authorize('manage-matches');
        
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
        $this->authorize('moderate-matches');
        
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
        $this->authorize('manage-matches');
        
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
        $this->authorize('manage-matches');
        
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
                    'maps_data' => $match->maps_data ?? [],
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
        $this->authorize('manage-matches');
        
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
            // First try to find match in mvrl_matches table
            try {
                $match = \App\Models\MvrlMatch::with(['team1', 'team2'])->findOrFail($matchId);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                // Fallback to regular matches table
                $match = DB::table('matches')->where('id', $matchId)->first();
                if (!$match) {
                    // Return empty timeline if no match found
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'timeline' => [],
                            'match_id' => $matchId,
                            'message' => 'No timeline data available for this match'
                        ]
                    ]);
                }
            }
            
            $timeline = [];
            
            // Parse maps data to create timeline events
            $mapsData = [];
            if (is_object($match) && isset($match->maps_data)) {
                $mapsData = $match->maps_data ?? [];
            } elseif (is_array($match) && isset($match['maps_data'])) {
                $mapsData = json_decode($match['maps_data'] ?? '[]', true) ?? [];
            }
            
            foreach ($mapsData as $index => $map) {
                // Check if map is completed (handle missing status field)
                $mapStatus = $map['status'] ?? 'upcoming';
                if ($mapStatus === 'completed' && isset($map['winner_id'])) {
                    $winnerTeam = $map['winner_id'] == $match->team1_id ? $match->team1 : $match->team2;
                    $timeline[] = [
                        'type' => 'map_end',
                        'map_number' => $index + 1,
                        'map_name' => $map['map_name'] ?? 'Map ' . ($index + 1),
                        'winner' => $winnerTeam->name,
                        'score' => ($map['team1_score'] ?? 0) . '-' . ($map['team2_score'] ?? 0),
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
                    
                    $data['maps_data'] = $mapsData;
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
        // Handle both string JSON (from DB query) and array (from Eloquent model)
        $mapsData = is_string($match->maps_data) ? json_decode($match->maps_data, true) ?? [] : ($match->maps_data ?? []);
        $liveData = $match->live_data ? json_decode($match->live_data, true) : [];
        
        // Get team players
        $team1Players = [];
        $team2Players = [];
        
        if ($match->team1_id) {
            $team1Players = DB::table('players')
                ->where('team_id', $match->team1_id)
                ->where('status', 'active')
                ->select('id', 'username', 'username as name', 'username as player_name', 'real_name', 'role', 'avatar', 'country', 'nationality', 'main_hero as hero')
                ->orderBy(DB::raw("CASE 
                    WHEN role = 'DPS' THEN 1 
                    WHEN role = 'Tank' THEN 2 
                    WHEN role = 'Support' THEN 3 
                    ELSE 4 END"))
                ->orderBy('username')
                ->limit(6)
                ->get()
                ->toArray();
        }
        
        if ($match->team2_id) {
            $team2Players = DB::table('players')
                ->where('team_id', $match->team2_id)
                ->where('status', 'active')
                ->select('id', 'username', 'username as name', 'username as player_name', 'real_name', 'role', 'avatar', 'country', 'nationality', 'main_hero as hero')
                ->orderBy(DB::raw("CASE 
                    WHEN role = 'DPS' THEN 1 
                    WHEN role = 'Tank' THEN 2 
                    WHEN role = 'Support' THEN 3 
                    ELSE 4 END"))
                ->orderBy('username')
                ->limit(6)
                ->get()
                ->toArray();
        }

        return [
            'id' => $match->id,
            'team1' => $match->team1_id ? [
                'id' => $match->team1_id,
                'name' => $match->team1_name ?: 'TBD',
                'short_name' => $match->team1_short ?: ($match->team1_name ? substr($match->team1_name, 0, 3) : 'TBD'),
                'logo' => $match->team1_logo,
                'region' => $match->team1_region,
                'rating' => $match->team1_rating ?? 1000,
                'flag' => $match->team1_flag ?? null,
                'players' => $team1Players
            ] : null,
            'team2' => $match->team2_id ? [
                'id' => $match->team2_id,
                'name' => $match->team2_name ?: 'TBD',
                'short_name' => $match->team2_short ?: ($match->team2_name ? substr($match->team2_name, 0, 3) : 'TBD'),
                'logo' => $match->team2_logo,
                'region' => $match->team2_region,
                'rating' => $match->team2_rating ?? 1000,
                'flag' => $match->team2_flag ?? null,
                'players' => $team2Players
            ] : null,
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
                'streams' => is_string($match->stream_urls) ? json_decode($match->stream_urls, true) ?? [] : ($match->stream_urls ?? []),
                'betting' => is_string($match->betting_urls) ? json_decode($match->betting_urls, true) ?? [] : ($match->betting_urls ?? []),
                'vods' => is_string($match->vod_urls) ? json_decode($match->vod_urls, true) ?? [] : ($match->vod_urls ?? []),
                'viewers' => $match->viewers ?? 0
            ],
            // Add URL fields directly for frontend compatibility
            'stream_urls' => is_string($match->stream_urls) ? json_decode($match->stream_urls, true) ?? [] : ($match->stream_urls ?? []),
            'betting_urls' => is_string($match->betting_urls) ? json_decode($match->betting_urls, true) ?? [] : ($match->betting_urls ?? []),
            'vod_urls' => is_string($match->vod_urls) ? json_decode($match->vod_urls, true) ?? [] : ($match->vod_urls ?? []),
            'live_data' => $isLive ? $liveData : null,
            'player_stats' => is_string($match->player_stats) ? json_decode($match->player_stats, true) ?? [] : ($match->player_stats ?? []),
            'format' => $match->format,
            'status' => $match->status,
            'current_map' => $match->current_map_number ?? 1,
            'maps_data' => $mapsData,
            'match_timer' => is_string($match->match_timer) ? json_decode($match->match_timer, true) ?? [] : ($match->match_timer ?? []),
            'timer' => $this->extractTimerValue(is_string($match->match_timer) ? json_decode($match->match_timer, true) ?? [] : ($match->match_timer ?? [])),
            // Add scores at root level for frontend compatibility
            'team1_score' => $match->team1_score ?? 0,
            'team2_score' => $match->team2_score ?? 0
        ];
    }

    private function getCompleteMatchData($match)
    {
        $matchData = $this->formatMatchData($match);
        
        // Get team rosters with stats
        $matchData['team1']['roster'] = $this->getTeamRosterWithStats($match->team1_id, $match->id);
        $matchData['team2']['roster'] = $this->getTeamRosterWithStats($match->team2_id, $match->id);
        
        // Add players array for live scoring compatibility
        $matchData['team1']['players'] = $matchData['team1']['roster'];
        $matchData['team2']['players'] = $matchData['team2']['roster'];
        
        // Get detailed map data - ENSURE CONSISTENCY WITH FRONTEND
        // Handle both string JSON (from DB query) and array (from Eloquent model)
        $mapsData = is_string($match->maps_data) ? json_decode($match->maps_data, true) ?? [] : ($match->maps_data ?? []);
        foreach ($mapsData as $index => &$map) {
            $map['index'] = $index + 1;
            $map['player_stats'] = $this->getMapPlayerStats($match->id, $index + 1);
            
            // For completed matches, preserve historical map compositions
            // For live/upcoming matches, use current roster data if compositions are empty
            if ($match->status === 'completed') {
                // Keep existing compositions for historical accuracy
                $map['team1_composition'] = $map['team1_composition'] ?? [];
                $map['team2_composition'] = $map['team2_composition'] ?? [];
            } else {
                // For live/upcoming matches, use roster data if no compositions exist
                $map['team1_composition'] = $map['team1_composition'] ?? ($matchData['team1']['roster'] ?? []);
                $map['team2_composition'] = $map['team2_composition'] ?? ($matchData['team2']['roster'] ?? []);
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
            'streams' => is_string($match->stream_urls) ? json_decode($match->stream_urls, true) ?? [] : ($match->stream_urls ?? []),
            'betting' => is_string($match->betting_urls) ? json_decode($match->betting_urls, true) ?? [] : ($match->betting_urls ?? []),
            'vods' => is_string($match->vod_urls) ? json_decode($match->vod_urls, true) ?? [] : ($match->vod_urls ?? [])
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
        
        $matchData['player_stats'] = is_string($match->player_stats) ? json_decode($match->player_stats, true) ?? [] : ($match->player_stats ?? []);
        
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
            ->orderBy('p.id') // Keep consistent player order by ID
            ->limit(6) // LIMIT TO 6 PLAYERS PER TEAM FOR MARVEL RIVALS 6v6 FORMAT
            ->get()
            ->map(function($player) {
                return [
                    'id' => $player->id,
                    'player_id' => $player->id,  // For consistency
                    'username' => $player->username,
                    'player_name' => $player->username,  // For consistency
                    'name' => $player->username,  // Always use username for display name
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
        $mapsData = $match->maps_data ?? [];
        
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
        $mapsData = $match->maps_data ?? [];
        
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

    private function updatePlayerStatsInternal($matchId, $data)
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
        $this->authorize('moderate-matches');
        
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
                $mapsData = $match->maps_data ?? [];
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
            'parent_id' => 'nullable|exists:match_comments,id',
            'mentions' => 'nullable|array'
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

            // Process mentions from frontend data
            if ($request->has('mentions') && is_array($request->mentions)) {
                $this->processMentionsFromData($request->mentions, $matchId, $commentId);
            }
            // Also extract mentions from content as fallback
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

        $userId = Auth::id();
        
        return $comments->map(function($comment) use ($matchId, $userId) {
            // Get user's vote for this comment
            $userVote = null;
            if ($userId) {
                $vote = DB::table('comment_votes')
                    ->where('comment_id', $comment->id)
                    ->where('comment_type', 'match')
                    ->where('user_id', $userId)
                    ->first();
                
                if ($vote) {
                    $userVote = $vote->vote_type;
                }
            }
            
            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'user' => $this->formatUserWithFlairs($comment),
                'votes' => $this->getCommentVotes($comment->id),
                'user_vote' => $userVote,
                'mentions' => $this->extractMentionsNew($comment->content),
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

        $userId = Auth::id();
        
        return $replies->map(function($reply) use ($matchId, $userId) {
            // Get user's vote for this reply
            $userVote = null;
            if ($userId) {
                $vote = DB::table('comment_votes')
                    ->where('comment_id', $reply->id)
                    ->where('comment_type', 'match')
                    ->where('user_id', $userId)
                    ->first();
                
                if ($vote) {
                    $userVote = $vote->vote_type;
                }
            }
            
            return [
                'id' => $reply->id,
                'content' => $reply->content,
                'user' => $this->formatUserWithFlairs($reply),
                'votes' => $this->getCommentVotes($reply->id),
                'user_vote' => $userVote,
                'mentions' => $this->extractMentionsNew($reply->content),
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
            // Store mention in database for notifications
            try {
                // Extract context for the mention
                $context = $this->extractMentionContext($content, $mention['mention_text']);
                
                // Check if mention already exists to avoid duplicate constraint violation
                $existingMention = DB::table('mentions')
                    ->where('mentionable_type', $commentId ? 'match_comment' : 'match')
                    ->where('mentionable_id', $commentId ?: $matchId)
                    ->where('mentioned_type', $mention['type'])
                    ->where('mentioned_id', $mention['id'])
                    ->where('mention_text', $mention['mention_text'])
                    ->first();
                if (!$existingMention) {
                    DB::table('mentions')->insert([
                        'mentionable_type' => $commentId ? 'match_comment' : 'match',
                        'mentionable_id' => $commentId ?: $matchId,
                        'mentioned_type' => $mention['type'],
                        'mentioned_id' => $mention['id'],
                        'mention_text' => $mention['mention_text'],
                        'position_start' => $mention['position_start'] ?? null,
                        'position_end' => $mention['position_end'] ?? null,
                        'context' => $context,
                        'mentioned_by' => Auth::id(),
                        'mentioned_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the request
                \Log::error('Failed to save mention: ' . $e->getMessage());
            }
        }
    }

    private function extractMentionsNew($content)
    {
        $mentions = [];
        
        // Extract @username mentions with positions (handles both @username and @display name formats)
        preg_match_all('/@([a-zA-Z0-9_\s]+)(?=\s|$|[,.!?\'"])/u', $content, $userMatches, PREG_OFFSET_CAPTURE);
        foreach ($userMatches[0] as $index => $match) {
            $mentionText = $match[0];
            $position = $match[1];
            $username = trim($userMatches[1][$index][0]);
            
            // First, try to find a player by username or real_name
            $player = DB::table('players')
                ->where('real_name', $username)
                ->orWhere('username', $username)
                ->first();
                
            if ($player) {
                // Found a player mention
                $mentions[] = [
                    'type' => 'player',
                    'id' => $player->id,
                    'name' => $player->username,
                    'display_name' => $player->real_name ?? $player->username,
                    'avatar' => $player->avatar ? asset('storage/' . ltrim($player->avatar, '/')) : null,
                    'avatar_url' => $player->avatar ? asset('storage/' . ltrim($player->avatar, '/')) : null,
                    'mention_text' => $mentionText,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText)
                ];
            } else {
                // Try to find a user by name
                $user = DB::table('users')
                    ->where('name', $username)
                    ->first();
                
                if ($user) {
                    // Found a user mention
                    $avatarUrl = null;
                    if ($user->avatar) {
                        // Handle hero images differently - they're in public/images/heroes/
                        if (strpos($user->avatar, '/images/heroes/') !== false) {
                            $avatarUrl = asset(ltrim($user->avatar, '/'));
                        } else {
                            $avatarUrl = asset('storage/' . ltrim($user->avatar, '/'));
                        }
                    }
                    
                    $mentions[] = [
                        'type' => 'user',
                        'id' => $user->id,
                        'name' => $user->name,
                        'display_name' => $user->name,
                        'avatar' => $avatarUrl,
                        'avatar_url' => $avatarUrl,
                        'mention_text' => $mentionText,
                        'position_start' => $position,
                        'position_end' => $position + strlen($mentionText)
                    ];
                }
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
                    'logo' => $team->logo ? asset('storage/' . ltrim($team->logo, '/')) : null,
                    'logo_url' => $team->logo ? asset('storage/' . ltrim($team->logo, '/')) : null,
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
                    'avatar' => $player->avatar ? asset('storage/' . ltrim($player->avatar, '/')) : null,
                    'avatar_url' => $player->avatar ? asset('storage/' . ltrim($player->avatar, '/')) : null,
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
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote changed',
                        'action' => 'changed'
                    ]);
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
                
                return response()->json([
                    'success' => true,
                    'message' => 'Vote recorded',
                    'action' => 'voted'
                ]);
            }

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

    private function getCountryFlag($countryCode)
    {
        // Map full country names to country codes
        $countryNameToCode = [
            'United States' => 'US',
            'USA' => 'US',
            'Canada' => 'CA',
            'United Kingdom' => 'GB',
            'UK' => 'GB',
            'France' => 'FR',
            'Germany' => 'DE',
            'Spain' => 'ES',
            'Italy' => 'IT',
            'Japan' => 'JP',
            'South Korea' => 'KR',
            'Korea' => 'KR',
            'China' => 'CN',
            'Brazil' => 'BR',
            'Mexico' => 'MX',
            'Australia' => 'AU',
            'New Zealand' => 'NZ',
            'Sweden' => 'SE',
            'Norway' => 'NO',
            'Denmark' => 'DK',
            'Finland' => 'FI',
            'Netherlands' => 'NL',
            'Belgium' => 'BE',
            'Poland' => 'PL',
            'Russia' => 'RU',
            'Ukraine' => 'UA',
            'India' => 'IN',
            'Argentina' => 'AR',
            'Chile' => 'CL',
            'Colombia' => 'CO',
            'Peru' => 'PE',
            'Venezuela' => 'VE',
            'South Africa' => 'ZA',
            'Egypt' => 'EG',
            'Saudi Arabia' => 'SA',
            'UAE' => 'AE',
            'Israel' => 'IL',
            'Turkey' => 'TR',
            'Greece' => 'GR',
            'Portugal' => 'PT',
            'Ireland' => 'IE',
            'Austria' => 'AT',
            'Switzerland' => 'CH',
            'Czech Republic' => 'CZ',
            'Hungary' => 'HU',
            'Romania' => 'RO',
            'Bulgaria' => 'BG',
            'Croatia' => 'HR',
            'Serbia' => 'RS',
        ];

        // Convert full country name to code if necessary
        if (strlen($countryCode) > 2) {
            $countryCode = $countryNameToCode[$countryCode] ?? $countryCode;
        }

        // Map country codes to flag URLs or emoji
        $flags = [
            'US' => '🇺🇸',
            'CA' => '🇨🇦',
            'GB' => '🇬🇧',
            'UK' => '🇬🇧',
            'FR' => '🇫🇷',
            'DE' => '🇩🇪',
            'ES' => '🇪🇸',
            'IT' => '🇮🇹',
            'JP' => '🇯🇵',
            'KR' => '🇰🇷',
            'CN' => '🇨🇳',
            'BR' => '🇧🇷',
            'MX' => '🇲🇽',
            'AU' => '🇦🇺',
            'NZ' => '🇳🇿',
            'SE' => '🇸🇪',
            'NO' => '🇳🇴',
            'DK' => '🇩🇰',
            'FI' => '🇫🇮',
            'NL' => '🇳🇱',
            'BE' => '🇧🇪',
            'PL' => '🇵🇱',
            'RU' => '🇷🇺',
            'UA' => '🇺🇦',
            'IN' => '🇮🇳',
            'AR' => '🇦🇷',
            'CL' => '🇨🇱',
            'CO' => '🇨🇴',
            'PE' => '🇵🇪',
            'VE' => '🇻🇪',
            'ZA' => '🇿🇦',
            'EG' => '🇪🇬',
            'SA' => '🇸🇦',
            'AE' => '🇦🇪',
            'IL' => '🇮🇱',
            'TR' => '🇹🇷',
            'GR' => '🇬🇷',
            'PT' => '🇵🇹',
            'IE' => '🇮🇪',
            'AT' => '🇦🇹',
            'CH' => '🇨🇭',
            'CZ' => '🇨🇿',
            'HU' => '🇭🇺',
            'RO' => '🇷🇴',
            'BG' => '🇧🇬',
            'HR' => '🇭🇷',
            'RS' => '🇷🇸',
            'SK' => '🇸🇰',
            'SI' => '🇸🇮',
            'LT' => '🇱🇹',
            'LV' => '🇱🇻',
            'EE' => '🇪🇪',
            'IS' => '🇮🇸',
            'MT' => '🇲🇹',
            'CY' => '🇨🇾',
            'LU' => '🇱🇺',
            'SG' => '🇸🇬',
            'MY' => '🇲🇾',
            'TH' => '🇹🇭',
            'ID' => '🇮🇩',
            'PH' => '🇵🇭',
            'VN' => '🇻🇳',
            'HK' => '🇭🇰',
            'TW' => '🇹🇼',
            'NP' => '🇳🇵',
            'PK' => '🇵🇰',
            'BD' => '🇧🇩',
            'LK' => '🇱🇰',
            'MM' => '🇲🇲',
            'KH' => '🇰🇭',
            'LA' => '🇱🇦',
            'MN' => '🇲🇳',
            'KZ' => '🇰🇿',
            'UZ' => '🇺🇿',
            'TM' => '🇹🇲',
            'KG' => '🇰🇬',
            'TJ' => '🇹🇯',
            'AF' => '🇦🇫',
            'IQ' => '🇮🇶',
            'IR' => '🇮🇷',
            'SY' => '🇸🇾',
            'LB' => '🇱🇧',
            'JO' => '🇯🇴',
            'PS' => '🇵🇸',
            'YE' => '🇾🇪',
            'OM' => '🇴🇲',
            'KW' => '🇰🇼',
            'QA' => '🇶🇦',
            'BH' => '🇧🇭',
            'MA' => '🇲🇦',
            'DZ' => '🇩🇿',
            'TN' => '🇹🇳',
            'LY' => '🇱🇾',
            'SD' => '🇸🇩',
            'ET' => '🇪🇹',
            'KE' => '🇰🇪',
            'UG' => '🇺🇬',
            'TZ' => '🇹🇿',
            'RW' => '🇷🇼',
            'BI' => '🇧🇮',
            'MW' => '🇲🇼',
            'MZ' => '🇲🇿',
            'ZM' => '🇿🇲',
            'ZW' => '🇿🇼',
            'BW' => '🇧🇼',
            'NA' => '🇳🇦',
            'AO' => '🇦🇴',
            'CD' => '🇨🇩',
            'CG' => '🇨🇬',
            'CM' => '🇨🇲',
            'GA' => '🇬🇦',
            'GQ' => '🇬🇶',
            'CF' => '🇨🇫',
            'TD' => '🇹🇩',
            'NG' => '🇳🇬',
            'GH' => '🇬🇭',
            'CI' => '🇨🇮',
            'BF' => '🇧🇫',
            'ML' => '🇲🇱',
            'NE' => '🇳🇪',
            'SN' => '🇸🇳',
            'GM' => '🇬🇲',
            'GW' => '🇬🇼',
            'GN' => '🇬🇳',
            'SL' => '🇸🇱',
            'LR' => '🇱🇷',
            'TG' => '🇹🇬',
            'BJ' => '🇧🇯',
            'MR' => '🇲🇷',
            'CV' => '🇨🇻',
            'SO' => '🇸🇴',
            'DJ' => '🇩🇯',
            'ER' => '🇪🇷',
            'SS' => '🇸🇸',
            'SZ' => '🇸🇿',
            'LS' => '🇱🇸',
            'MG' => '🇲🇬',
            'KM' => '🇰🇲',
            'MU' => '🇲🇺',
            'SC' => '🇸🇨',
            'RE' => '🇷🇪',
            'YT' => '🇾🇹',
            'MV' => '🇲🇻',
            'BT' => '🇧🇹',
            'TL' => '🇹🇱',
            'BN' => '🇧🇳',
            'PG' => '🇵🇬',
            'SB' => '🇸🇧',
            'VU' => '🇻🇺',
            'FJ' => '🇫🇯',
            'NC' => '🇳🇨',
            'PF' => '🇵🇫',
            'GU' => '🇬🇺',
            'WS' => '🇼🇸',
            'KI' => '🇰🇮',
            'TO' => '🇹🇴',
            'TV' => '🇹🇻',
            'NR' => '🇳🇷',
            'PW' => '🇵🇼',
            'MH' => '🇲🇭',
            'FM' => '🇫🇲',
            'JM' => '🇯🇲',
            'CU' => '🇨🇺',
            'HT' => '🇭🇹',
            'DO' => '🇩🇴',
            'PR' => '🇵🇷',
            'TT' => '🇹🇹',
            'BB' => '🇧🇧',
            'AG' => '🇦🇬',
            'DM' => '🇩🇲',
            'GD' => '🇬🇩',
            'KN' => '🇰🇳',
            'LC' => '🇱🇨',
            'VC' => '🇻🇨',
            'BS' => '🇧🇸',
            'BZ' => '🇧🇿',
            'GT' => '🇬🇹',
            'SV' => '🇸🇻',
            'HN' => '🇭🇳',
            'NI' => '🇳🇮',
            'CR' => '🇨🇷',
            'PA' => '🇵🇦',
            'EC' => '🇪🇨',
            'BO' => '🇧🇴',
            'PY' => '🇵🇾',
            'UY' => '🇺🇾',
            'GY' => '🇬🇾',
            'SR' => '🇸🇷',
            'GF' => '🇬🇫',
            'FK' => '🇫🇰'
        ];
        
        return $flags[strtoupper($countryCode)] ?? '🏳️';
    }


    // Live match update methods
    public function updateLiveScore(Request $request, $matchId)
    {
        $this->authorize('manage-matches');
        
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

            $mapsData = $match->maps_data ?? [];
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
        $this->authorize('manage-matches');
        
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
                'match_timer' => $timerData,
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
        $this->authorize('manage-matches');
        
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
        $this->authorize('moderate-matches');
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match || $match->status !== 'live') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match must be live to transition maps'
                ], 400);
            }

            $mapsData = $match->maps_data ?? [];
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
        $this->authorize('manage-matches');
        
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $mapsData = $match->maps_data ?? [];
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
        $this->authorize('manage-matches');
        
        $request->validate([
            'winner_id' => 'required|exists:teams,id'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            $mapsData = $match->maps_data ?? [];
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
        $this->authorize('manage-matches');
        
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
        $this->authorize('manage-matches');
        
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
                // Handle both string JSON (from DB query) and array (from Eloquent model)
                $mapsData = is_string($match->maps_data) ? json_decode($match->maps_data, true) ?? [] : ($match->maps_data ?? []);
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

    private function getMentionsForComment($commentId)
    {
        $mentions = DB::table('mentions')
            ->where('mentionable_type', 'match_comment')
            ->where('mentionable_id', $commentId)
            ->get();
        
        $formattedMentions = [];
        foreach ($mentions as $mention) {
            $mentionData = [
                'type' => $mention->mentioned_type,
                'id' => $mention->mentioned_id,
                'mention_text' => $mention->mention_text,
                'position_start' => $mention->position_start,
                'position_end' => $mention->position_end
            ];
            
            // Get display name based on type
            switch ($mention->mentioned_type) {
                case 'player':
                    $player = DB::table('players')->where('id', $mention->mentioned_id)->first();
                    if ($player) {
                        $mentionData['name'] = $player->username;
                        $mentionData['display_name'] = $player->real_name ?? $player->username;
                    }
                    break;
                case 'team':
                    $team = DB::table('teams')->where('id', $mention->mentioned_id)->first();
                    if ($team) {
                        $mentionData['name'] = $team->name;
                        $mentionData['display_name'] = $team->name;
                    }
                    break;
                case 'user':
                    $user = DB::table('users')->where('id', $mention->mentioned_id)->first();
                    if ($user) {
                        $mentionData['name'] = $user->name;
                        $mentionData['display_name'] = $user->name;
                    }
                    break;
            }
            
            $formattedMentions[] = $mentionData;
        }
        
        return $formattedMentions;
    }

    public function getComments($matchId)
    {
        try {
            // Use the private getMatchComments method that includes user votes
            $comments = $this->getMatchComments($matchId);

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

            // Handle both string JSON (from DB query) and array (from Eloquent model)
            $mapsData = is_string($match->maps_data) ? json_decode($match->maps_data, true) ?? [] : ($match->maps_data ?? []);

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
        
        if (is_array($matchTimer)) {
            if (isset($matchTimer['time'])) {
                return $matchTimer['time'];
            } elseif (isset($matchTimer['current_time'])) {
                return $matchTimer['current_time'];
            }
        }
        
        return '00:00';
    }

    private function processMentionsFromData($mentions, $matchId, $commentId = null)
    {
        foreach ($mentions as $mention) {
            try {
                // Get the full content to find the mention position
                if ($commentId) {
                    $content = DB::table('match_comments')->where('id', $commentId)->value('content');
                } else {
                    $match = DB::table('matches')->where('id', $matchId)->first();
                    $content = '';
                }
                
                // Find the mention text in content
                $mentionText = '@' . ($mention['display_name'] ?? $mention['name']);
                $position = strpos($content, $mentionText);
                
                if ($position === false) {
                    // Try alternative formats
                    $mentionText = '@' . $mention['name'];
                    $position = strpos($content, $mentionText);
                }
                
                // Extract context for the mention
                $context = $this->extractMentionContext($content, $mentionText);
                
                // Check if mention already exists to avoid duplicate constraint violation
                $existingMention = DB::table('mentions')
                    ->where('mentionable_type', $commentId ? 'match_comment' : 'match')
                    ->where('mentionable_id', $commentId ?: $matchId)
                    ->where('mentioned_type', $mention['type'])
                    ->where('mentioned_id', $mention['id'])
                    ->where('mention_text', $mentionText)
                    ->first();

                if (!$existingMention) {
                    DB::table('mentions')->insert([
                        'mentionable_type' => $commentId ? 'match_comment' : 'match',
                        'mentionable_id' => $commentId ?: $matchId,
                        'mentioned_type' => $mention['type'],
                        'mentioned_id' => $mention['id'],
                        'mention_text' => $mentionText,
                        'position_start' => $position !== false ? $position : null,
                        'position_end' => $position !== false ? $position + strlen($mentionText) : null,
                        'context' => $context,
                        'mentioned_by' => Auth::id(),
                        'mentioned_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but continue processing other mentions
                \Log::error('Error processing mention: ' . $e->getMessage());
            }
        }
    }

    public function updateComment(Request $request, $commentId)
    {
        try {
            $request->validate([
                'content' => 'required|string|max:2000',
                'mentions' => 'array'
            ]);

            $comment = DB::table('match_comments')->where('id', $commentId)->first();

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            // Check if user owns the comment or is admin/moderator
            $user = Auth::user();
            if (!$user || ($comment->user_id !== $user->id && !in_array($user->role, ['admin', 'moderator']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to edit this comment'
                ], 403);
            }

            // Update the comment
            DB::table('match_comments')
                ->where('id', $commentId)
                ->update([
                    'content' => $request->content,
                    'edited_at' => now(),
                    'updated_at' => now()
                ]);

            // Process mentions if provided
            if ($request->has('mentions') && is_array($request->mentions)) {
                // Delete existing mentions for this comment
                DB::table('mentions')
                    ->where('mentionable_type', 'match_comment')
                    ->where('mentionable_id', $commentId)
                    ->delete();

                // Add new mentions
                $this->processMentionsFromData($request->mentions, $comment->match_id, $commentId);
            }

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating match comment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment'
            ], 500);
        }
    }

    public function destroyComment($commentId)
    {
        try {
            $comment = DB::table('match_comments')->where('id', $commentId)->first();

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            // Check if user owns the comment or is admin/moderator
            $user = Auth::user();
            if (!$user || ($comment->user_id !== $user->id && !in_array($user->role, ['admin', 'moderator']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this comment'
                ], 403);
            }

            // Delete associated mentions
            DB::table('mentions')
                ->where('mentionable_type', 'match_comment')
                ->where('mentionable_id', $commentId)
                ->delete();

            // Delete associated votes
            DB::table('match_votes')
                ->where('comment_id', $commentId)
                ->delete();

            // Delete the comment (this will also delete replies due to foreign key cascade)
            DB::table('match_comments')->where('id', $commentId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error deleting match comment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment'
            ], 500);
        }
    }

    public function getUserPredictions(Request $request)
    {
        try {
            $user = $request->user();
            $predictions = DB::table('match_predictions as mp')
                ->join('matches as m', 'mp.match_id', '=', 'm.id')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->where('mp.user_id', $user->id)
                ->select([
                    'mp.*',
                    'm.status as match_status',
                    'm.scheduled_at',
                    'm.team1_score',
                    'm.team2_score',
                    't1.name as team1_name',
                    't1.logo as team1_logo',
                    't2.name as team2_name',
                    't2.logo as team2_logo',
                    'e.name as event_name'
                ])
                ->orderBy('m.scheduled_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $predictions->items(),
                'meta' => [
                    'current_page' => $predictions->currentPage(),
                    'last_page' => $predictions->lastPage(),
                    'per_page' => $predictions->perPage(),
                    'total' => $predictions->total()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching user predictions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch predictions'
            ], 500);
        }
    }

    public function storePrediction(Request $request, $matchId = null)
    {
        try {
            // If matchId not in URL, get from request body
            if (!$matchId) {
                $request->validate([
                    'match_id' => 'required|integer'
                ]);
                $matchId = $request->input('match_id');
            }
            
            $validated = $request->validate([
                'predicted_winner' => 'required|in:team1,team2',
                'confidence' => 'required|integer|min:1|max:100',
                'predicted_score' => 'nullable|array',
                'predicted_score.team1' => 'nullable|integer|min:0',
                'predicted_score.team2' => 'nullable|integer|min:0'
            ]);

            $user = $request->user();
            
            // Check if match exists and hasn't started
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
                    'message' => 'Cannot predict on matches that have already started'
                ], 400);
            }

            // Check if user already has a prediction
            $existing = DB::table('match_predictions')
                ->where('user_id', $user->id)
                ->where('match_id', $matchId)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already made a prediction for this match'
                ], 400);
            }

            $predictionId = DB::table('match_predictions')->insertGetId([
                'user_id' => $user->id,
                'match_id' => $matchId,
                'predicted_winner' => $validated['predicted_winner'],
                'confidence' => $validated['confidence'],
                'predicted_score_team1' => $validated['predicted_score']['team1'] ?? null,
                'predicted_score_team2' => $validated['predicted_score']['team2'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Prediction created successfully',
                'data' => ['id' => $predictionId]
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error storing prediction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create prediction'
            ], 500);
        }
    }

    public function updatePrediction(Request $request, $predictionId)
    {
        try {
            $validated = $request->validate([
                'predicted_winner' => 'required|in:team1,team2',
                'confidence' => 'required|integer|min:1|max:100',
                'predicted_score' => 'nullable|array',
                'predicted_score.team1' => 'nullable|integer|min:0',
                'predicted_score.team2' => 'nullable|integer|min:0'
            ]);

            $user = $request->user();
            
            // Check if prediction exists and belongs to user
            $prediction = DB::table('match_predictions')
                ->where('id', $predictionId)
                ->where('user_id', $user->id)
                ->first();

            if (!$prediction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prediction not found'
                ], 404);
            }

            // Check if match hasn't started
            $match = DB::table('matches')->where('id', $prediction->match_id)->first();
            if ($match->status !== 'upcoming') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update predictions for matches that have already started'
                ], 400);
            }

            DB::table('match_predictions')
                ->where('id', $predictionId)
                ->update([
                    'predicted_winner' => $validated['predicted_winner'],
                    'confidence' => $validated['confidence'],
                    'predicted_score_team1' => $validated['predicted_score']['team1'] ?? null,
                    'predicted_score_team2' => $validated['predicted_score']['team2'] ?? null,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Prediction updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating prediction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update prediction'
            ], 500);
        }
    }

    public function destroyPrediction(Request $request, $predictionId)
    {
        try {
            $user = $request->user();
            
            // Check if prediction exists and belongs to user
            $prediction = DB::table('match_predictions')
                ->where('id', $predictionId)
                ->where('user_id', $user->id)
                ->first();

            if (!$prediction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prediction not found'
                ], 404);
            }

            // Check if match hasn't started
            $match = DB::table('matches')->where('id', $prediction->match_id)->first();
            if ($match->status !== 'upcoming') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete predictions for matches that have already started'
                ], 400);
            }

            DB::table('match_predictions')->where('id', $predictionId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Prediction deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error deleting prediction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete prediction'
            ], 500);
        }
    }
    
    /**
     * Live Scoring System Endpoints
     */
    
    public function getLiveScoreboard($matchId)
    {
        try {
            // Get match with teams and players
            $match = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->where('m.id', $matchId)
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    'e.name as event_name', 'e.type as event_type'
                ])
                ->first();

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Get team rosters
            $team1_roster = DB::table('players')
                ->where('team_id', $match->team1_id)
                ->select(['id', 'name', 'username', 'avatar', 'role', 'main_hero', 'country', 'status'])
                ->get();

            $team2_roster = DB::table('players')
                ->where('team_id', $match->team2_id)
                ->select(['id', 'name', 'username', 'avatar', 'role', 'main_hero', 'country', 'status'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'match' => $match,
                    'team1_roster' => $team1_roster,
                    'team2_roster' => $team2_roster
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live scoreboard'
            ], 500);
        }
    }
    
    public function updateLiveControl(Request $request, $matchId)
    {
        try {
            $validated = $request->validate([
                'action' => 'required|in:start,pause,resume,stop,reset,update_score',
                'team1_score' => 'sometimes|integer|min:0',
                'team2_score' => 'sometimes|integer|min:0',
                'current_map' => 'sometimes|string',
                'maps_data' => 'sometimes|string',
                'status' => 'sometimes|in:upcoming,live,paused,completed,cancelled'
            ]);

            $updateData = [];
            
            switch ($validated['action']) {
                case 'start':
                    $updateData['status'] = 'live';
                    $updateData['started_at'] = now();
                    break;
                case 'pause':
                    $updateData['status'] = 'paused';
                    break;
                case 'resume':
                    $updateData['status'] = 'live';
                    break;
                case 'stop':
                    $updateData['status'] = 'completed';
                    $updateData['completed_at'] = now();
                    break;
                case 'reset':
                    $updateData['status'] = 'upcoming';
                    $updateData['team1_score'] = 0;
                    $updateData['team2_score'] = 0;
                    $updateData['started_at'] = null;
                    $updateData['completed_at'] = null;
                    break;
                case 'update_score':
                    if (isset($validated['team1_score'])) {
                        $updateData['team1_score'] = $validated['team1_score'];
                    }
                    if (isset($validated['team2_score'])) {
                        $updateData['team2_score'] = $validated['team2_score'];
                    }
                    break;
            }

            // Add optional fields
            if (isset($validated['current_map'])) {
                $updateData['current_map'] = $validated['current_map'];
            }
            if (isset($validated['maps_data'])) {
                $updateData['maps_data'] = $validated['maps_data'];
            }
            if (isset($validated['status'])) {
                $updateData['status'] = $validated['status'];
            }

            $updateData['updated_at'] = now();

            DB::table('matches')->where('id', $matchId)->update($updateData);

            // Get updated match
            $match = DB::table('matches')->where('id', $matchId)->first();

            // Cache updated match data for immediate polling retrieval
            $this->cacheMatchUpdate($matchId, $validated['action'], $updateData);

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully',
                'data' => $match
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match'
            ], 500);
        }
    }
    
    public function updateBulkPlayerStats(Request $request, $matchId)
    {
        try {
            $validated = $request->validate([
                'player_stats' => 'required|array',
                'player_stats.*.player_id' => 'required|integer',
                'player_stats.*.hero' => 'sometimes|string',
                'player_stats.*.eliminations' => 'sometimes|integer|min:0',
                'player_stats.*.deaths' => 'sometimes|integer|min:0',
                'player_stats.*.assists' => 'sometimes|integer|min:0',
                'player_stats.*.damage' => 'sometimes|integer|min:0',
                'player_stats.*.healing' => 'sometimes|integer|min:0',
                'player_stats.*.damage_blocked' => 'sometimes|integer|min:0',
                'player_stats.*.ultimate_usage' => 'sometimes|integer|min:0',
                'player_stats.*.objective_time' => 'sometimes|integer|min:0'
            ]);

            foreach ($validated['player_stats'] as $playerStat) {
                $playerId = $playerStat['player_id'];
                unset($playerStat['player_id']);
                
                // Update or create player stats for this match
                DB::table('match_player_stats')->updateOrInsert(
                    ['match_id' => $matchId, 'player_id' => $playerId],
                    array_merge($playerStat, ['updated_at' => now()])
                );
            }

            // Cache bulk player stats update for immediate polling retrieval
            $this->cacheMatchUpdate($matchId, 'bulk_player_stats', [
                'player_stats' => $validated['player_stats']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Player stats updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update player stats'
            ], 500);
        }
    }
    
    public function updatePlayerStats(Request $request, $matchId, $playerId)
    {
        try {
            $validated = $request->validate([
                'hero' => 'sometimes|string',
                'eliminations' => 'sometimes|integer|min:0',
                'deaths' => 'sometimes|integer|min:0',
                'assists' => 'sometimes|integer|min:0',
                'damage' => 'sometimes|integer|min:0',
                'healing' => 'sometimes|integer|min:0',
                'damage_blocked' => 'sometimes|integer|min:0',
                'ultimate_usage' => 'sometimes|integer|min:0',
                'objective_time' => 'sometimes|integer|min:0'
            ]);

            DB::table('match_player_stats')->updateOrInsert(
                ['match_id' => $matchId, 'player_id' => $playerId],
                array_merge($validated, ['updated_at' => now()])
            );

            // Cache individual player stats update for immediate polling retrieval
            $this->cacheMatchUpdate($matchId, 'player_stats', [
                'player_id' => $playerId,
                'stats' => $validated
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Player stats updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update player stats'
            ], 500);
        }
    }
    
    public function simpleLiveScoring(Request $request, $matchId)
    {
        try {
            $validated = $request->validate([
                'team1_score' => 'sometimes|integer|min:0',
                'team2_score' => 'sometimes|integer|min:0',
                'status' => 'sometimes|in:upcoming,live,paused,completed',
                'current_map' => 'sometimes|string',
                'winner_id' => 'sometimes|integer|nullable'
            ]);

            $updateData = array_filter($validated);
            $updateData['updated_at'] = now();

            DB::table('matches')->where('id', $matchId)->update($updateData);

            $match = DB::table('matches')->where('id', $matchId)->first();

            // Cache simple scoring update for immediate polling retrieval
            $this->cacheMatchUpdate($matchId, 'simple_scoring', $validated);

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully',
                'data' => $match
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match'
            ], 500);
        }
    }

    /**
     * 🔥 IMMEDIATE LIVE UPDATE SYSTEM (Zero Delay)
     * 
     * This method triggers immediate updates to all connected clients
     * via Server-Sent Events (SSE) and database triggers
     */
    private function cacheMatchUpdate($matchId, $updateType, $updateData = [])
    {
        try {
            // Get fresh match data for immediate broadcast
            $match = \App\Models\MvrlMatch::where('id', $matchId)->first();
            if (!$match) {
                return;
            }

            // Build complete update payload
            $mapsData = is_string($match->maps_data) ? json_decode($match->maps_data, true) : ($match->maps_data ?? []);
            $playerStats = $this->getLatestPlayerStats($matchId);
            $heroSelections = $this->getLatestHeroSelections($matchId);
            
            $updatePayload = [
                'match_id' => $matchId,
                'update_type' => $updateType,
                'timestamp' => now()->timestamp,
                'iso_timestamp' => now()->toISOString(),
                'specific_update' => $updateData,
                
                // Complete match state for immediate synchronization
                'match_data' => [
                    'status' => $match->status,
                    'current_map' => $match->current_map_number ?? 1,
                    'series_score' => [
                        'team1' => $match->team1_score ?? 0,
                        'team2' => $match->team2_score ?? 0
                    ],
                    'current_map_scores' => $this->getCurrentMapScores($match, $mapsData),
                    'all_maps' => $mapsData,
                    'player_stats' => $playerStats,
                    'hero_selections' => $heroSelections,
                    'format' => $match->format ?? 'BO3',
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'timer' => is_string($match->match_timer) ? json_decode($match->match_timer, true) : ($match->match_timer ?? null),
                    'live_data' => is_string($match->live_data) ? json_decode($match->live_data, true) : ($match->live_data ?? [])
                ]
            ];
            
            // 1. Cache for immediate retrieval
            \Cache::put("match_update_{$matchId}", $updatePayload, 300);
            \Cache::put("match_last_update_{$matchId}", now()->timestamp, 300);
            
            // 2. Store in database for SSE streaming
            DB::table('live_match_updates')->insert([
                'match_id' => $matchId,
                'update_type' => $updateType,
                'update_data' => json_encode($updatePayload),
                'created_at' => now(),
                'processed' => false
            ]);
            
            // 3. Trigger immediate notification to SSE clients
            $this->notifySSEClients($matchId, $updatePayload);

            \Log::info("⚡ Immediate match update triggered", [
                'match_id' => $matchId,
                'update_type' => $updateType,
                'timestamp' => $updatePayload['timestamp']
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to trigger immediate match update", [
                'match_id' => $matchId,
                'update_type' => $updateType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 🚀 SERVER-SENT EVENTS STREAM - Zero delay live updates
     * 
     * Frontend connects to this endpoint and receives instant updates
     * Usage: const eventSource = new EventSource('/api/admin/matches/{matchId}/stream');
     */
    public function streamLiveUpdates(Request $request, $matchId)
    {
        return response()->stream(function () use ($matchId) {
            // Set headers for SSE
            echo "data: " . json_encode([
                'type' => 'connection',
                'message' => 'Connected to match ' . $matchId,
                'timestamp' => now()->toISOString()
            ]) . "\n\n";
            
            ob_flush();
            flush();
            
            $lastProcessed = 0;
            
            // Keep connection alive and check for updates
            while (true) {
                try {
                    // Check for new updates in database
                    $updates = DB::table('live_match_updates')
                        ->where('match_id', $matchId)
                        ->where('id', '>', $lastProcessed)
                        ->where('processed', false)
                        ->orderBy('id')
                        ->limit(10)
                        ->get();
                    
                    foreach ($updates as $update) {
                        // Send update to client
                        echo "data: " . $update->update_data . "\n\n";
                        
                        // Mark as processed
                        DB::table('live_match_updates')
                            ->where('id', $update->id)
                            ->update(['processed' => true]);
                        
                        $lastProcessed = $update->id;
                        
                        ob_flush();
                        flush();
                    }
                    
                    // Check if client disconnected
                    if (connection_aborted()) {
                        break;
                    }
                    
                    // Short sleep to prevent excessive CPU usage
                    usleep(100000); // 0.1 seconds
                    
                } catch (\Exception $e) {
                    \Log::error("SSE stream error: " . $e->getMessage());
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Trigger immediate notification to SSE clients
     */
    private function notifySSEClients($matchId, $updatePayload)
    {
        // This would trigger the SSE stream to send data immediately
        // The actual notification happens via the database insert above
        // which the SSE stream monitors in real-time
    }

    /**
     * 🚀 OPTIMIZED LIVE UPDATES ENDPOINT FOR POLLING
     * 
     * Returns only the data that has changed since the last poll
     * Frontend calls this every 2-3 seconds to get live updates
     */
    public function getLiveUpdates(Request $request, $matchId)
    {
        try {
            $lastPoll = $request->get('last_poll', 0); // Client's last poll timestamp
            $lastUpdate = \Cache::get("match_last_update_{$matchId}", 0);
            
            // If no updates since last poll, return minimal response
            if ($lastUpdate <= $lastPoll) {
                return response()->json([
                    'success' => true,
                    'has_updates' => false,
                    'last_update' => $lastUpdate,
                    'timestamp' => now()->timestamp
                ]);
            }
            
            // Get the cached update info
            $updateInfo = \Cache::get("match_update_{$matchId}");
            
            // Get comprehensive match data
            $match = \App\Models\MvrlMatch::where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['error' => 'Match not found'], 404);
            }

            // Build live update response
            $mapsData = is_string($match->maps_data) ? json_decode($match->maps_data, true) : ($match->maps_data ?? []);
            $playerStats = $this->getLatestPlayerStats($matchId);
            $heroSelections = $this->getLatestHeroSelections($matchId);
            
            return response()->json([
                'success' => true,
                'has_updates' => true,
                'last_update' => $lastUpdate,
                'timestamp' => now()->timestamp,
                'update_info' => $updateInfo,
                
                // Complete match state for synchronization
                'match_data' => [
                    'match_id' => $matchId,
                    'status' => $match->status,
                    'current_map' => $match->current_map_number ?? 1,
                    
                    // Series scores
                    'series_score' => [
                        'team1' => $match->team1_score ?? 0,
                        'team2' => $match->team2_score ?? 0
                    ],
                    
                    // Current map scores
                    'current_map_scores' => $this->getCurrentMapScores($match, $mapsData),
                    
                    // All maps data
                    'all_maps' => $mapsData,
                    
                    // Player data
                    'player_stats' => $playerStats,
                    'hero_selections' => $heroSelections,
                    
                    // Match metadata
                    'format' => $match->format ?? 'BO3',
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'event_id' => $match->event_id,
                    
                    // Timer/Live data
                    'timer' => is_string($match->match_timer) ? json_decode($match->match_timer, true) : ($match->match_timer ?? null),
                    'live_data' => is_string($match->live_data) ? json_decode($match->live_data, true) : ($match->live_data ?? [])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get live updates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🚀 OPTIMISTIC UPDATE ENDPOINT - For immediate frontend updates
     * 
     * This endpoint accepts update data and immediately returns success,
     * then processes the actual database update asynchronously
     */
    public function optimisticUpdate(Request $request, $matchId)
    {
        try {
            $updateData = $request->all();
            $updateType = $updateData['update_type'] ?? 'optimistic';
            
            // Immediately return success to frontend for instant UI update
            $response = response()->json([
                'success' => true,
                'message' => 'Update received',
                'timestamp' => now()->toISOString(),
                'optimistic' => true
            ]);
            
            // Process actual update asynchronously (after response is sent)
            register_shutdown_function(function () use ($matchId, $updateType, $updateData) {
                try {
                    // Process the actual database updates based on update type
                    switch ($updateType) {
                        case 'score_update':
                            if (isset($updateData['team1_score']) || isset($updateData['team2_score'])) {
                                $updateFields = [];
                                if (isset($updateData['team1_score'])) {
                                    $updateFields['team1_score'] = $updateData['team1_score'];
                                }
                                if (isset($updateData['team2_score'])) {
                                    $updateFields['team2_score'] = $updateData['team2_score'];
                                }
                                if (!empty($updateFields)) {
                                    $updateFields['updated_at'] = now();
                                    DB::table('matches')->where('id', $matchId)->update($updateFields);
                                }
                            }
                            break;
                            
                        case 'player_stats':
                            if (isset($updateData['player_id']) && isset($updateData['stats'])) {
                                DB::table('match_player_stats')->updateOrInsert(
                                    ['match_id' => $matchId, 'player_id' => $updateData['player_id']],
                                    array_merge($updateData['stats'], ['updated_at' => now()])
                                );
                            }
                            break;
                            
                        case 'match_control':
                            if (isset($updateData['status'])) {
                                $updateFields = ['status' => $updateData['status'], 'updated_at' => now()];
                                if ($updateData['status'] === 'live' && !isset($updateData['no_start_time'])) {
                                    $updateFields['started_at'] = now();
                                }
                                DB::table('matches')->where('id', $matchId)->update($updateFields);
                            }
                            break;
                    }
                    
                    // Trigger the live update system after database is updated
                    $this->cacheMatchUpdate($matchId, $updateType, $updateData);
                    
                } catch (\Exception $e) {
                    \Log::error("Optimistic update processing failed", [
                        'match_id' => $matchId,
                        'update_type' => $updateType,
                        'error' => $e->getMessage()
                    ]);
                }
            });
            
            return $response;
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to process optimistic update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current map scores from match data
     */
    private function getCurrentMapScores($match, $mapsData)
    {
        $currentMapIndex = ($match->current_map_number ?? 1) - 1;
        
        if (isset($mapsData[$currentMapIndex])) {
            return [
                'map_number' => $match->current_map_number,
                'team1_score' => $mapsData[$currentMapIndex]['team1_score'] ?? 0,
                'team2_score' => $mapsData[$currentMapIndex]['team2_score'] ?? 0,
                'status' => $mapsData[$currentMapIndex]['status'] ?? 'ongoing',
                'map_name' => $mapsData[$currentMapIndex]['map_name'] ?? null
            ];
        }
        
        return null;
    }

    /**
     * Get latest player statistics for the match
     */
    private function getLatestPlayerStats($matchId)
    {
        try {
            $stats = DB::table('match_player_stats')
                ->where('match_id', $matchId)
                ->get();
            
            $playerStats = [];
            foreach ($stats as $stat) {
                $playerStats[$stat->player_id] = [
                    'hero' => $stat->hero ?? null,
                    'eliminations' => $stat->eliminations ?? 0,
                    'deaths' => $stat->deaths ?? 0,
                    'assists' => $stat->assists ?? 0,
                    'damage' => $stat->damage ?? 0,
                    'healing' => $stat->healing ?? 0,
                    'damage_blocked' => $stat->damage_blocked ?? 0,
                    'ultimate_usage' => $stat->ultimate_usage ?? 0,
                    'objective_time' => $stat->objective_time ?? 0
                ];
            }
            
            return $playerStats;
        } catch (\Exception $e) {
            \Log::error("Failed to get player stats for match $matchId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get latest hero selections from player stats
     */
    private function getLatestHeroSelections($matchId)
    {
        try {
            $heroSelections = [];
            $stats = DB::table('match_player_stats')
                ->select('player_id', 'hero')
                ->where('match_id', $matchId)
                ->whereNotNull('hero')
                ->get();
            
            foreach ($stats as $stat) {
                $heroSelections[] = [
                    'player_id' => $stat->player_id,
                    'hero' => $stat->hero
                ];
            }
            
            return $heroSelections;
        } catch (\Exception $e) {
            \Log::error("Failed to get hero selections for match $matchId: " . $e->getMessage());
            return [];
        }
    }
}
