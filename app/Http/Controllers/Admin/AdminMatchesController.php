<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MvrlMatch;
use App\Models\MatchMap;
use App\Models\MatchPlayerStat;
use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AdminMatchesController extends Controller
{
    /**
     * Display a paginated listing of matches with advanced filtering and search.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MvrlMatch::with(['team1', 'team2', 'event'])
                ->select([
                    'id', 'team1_id', 'team2_id', 'event_id', 'scheduled_at',
                    'status', 'format', 'team1_score', 'team2_score',
                    'winner_id', 'started_at', 'ended_at', 'created_at'
                ]);

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->whereHas('team1', function ($teamQuery) use ($search) {
                        $teamQuery->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('team2', function ($teamQuery) use ($search) {
                        $teamQuery->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('event', function ($eventQuery) use ($search) {
                        $eventQuery->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhere('id', 'LIKE', "%{$search}%");
                });
            }

            // Status filter - map frontend status to backend
            if ($request->filled('status')) {
                $status = $request->input('status');
                // Map 'scheduled' from frontend to 'upcoming' in database
                if ($status === 'scheduled') {
                    $query->where('status', 'upcoming');
                } elseif (in_array($status, ['upcoming', 'live', 'completed'])) {
                    $query->where('status', $status);
                }
            }

            // Format filter
            if ($request->filled('format')) {
                $format = $request->input('format');
                if (in_array($format, ['BO1', 'BO3', 'BO5', 'BO7', 'BO9'])) {
                    $query->where('format', $format);
                }
            }

            // Event filter
            if ($request->filled('event_id')) {
                $query->where('event_id', $request->input('event_id'));
            }

            // Date range filter
            if ($request->filled('date_from')) {
                $query->whereDate('scheduled_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('scheduled_at', '<=', $request->input('date_to'));
            }

            // Team filter
            if ($request->filled('team_id')) {
                $teamId = $request->input('team_id');
                $query->where(function ($q) use ($teamId) {
                    $q->where('team1_id', $teamId)->orWhere('team2_id', $teamId);
                });
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'scheduled_at');
            $sortOrder = $request->input('sort_order', 'desc');
            
            $allowedSorts = ['id', 'scheduled_at', 'status', 'format', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            $perPage = min($request->input('per_page', 25), 100);
            $matches = $query->paginate($perPage);

            // Add additional data for each match and flatten the structure
            $matches->getCollection()->transform(function ($match) {
                // Flatten team and event data for frontend compatibility
                $transformedMatch = [
                    'id' => $match->id,
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'team1_name' => $match->team1 ? $match->team1->name : 'TBD',
                    'team2_name' => $match->team2 ? $match->team2->name : 'TBD',
                    'team1_logo' => $match->team1 ? $match->team1->logo : null,
                    'team2_logo' => $match->team2 ? $match->team2->logo : null,
                    'team1_short' => $match->team1 ? $match->team1->short_name : null,
                    'team2_short' => $match->team2 ? $match->team2->short_name : null,
                    'team1_region' => $match->team1 ? $match->team1->region : null,
                    'team2_region' => $match->team2 ? $match->team2->region : null,
                    'team1_score' => $match->team1_score ?? 0,
                    'team2_score' => $match->team2_score ?? 0,
                    'event_id' => $match->event_id,
                    'event_name' => $match->event ? $match->event->name : 'No Event',
                    'event_logo' => $match->event ? $match->event->logo : null,
                    'status' => $match->status === 'upcoming' ? 'scheduled' : ($match->status ?? 'scheduled'),
                    'format' => $match->format,
                    'scheduled_at' => $match->scheduled_at,
                    'started_at' => $match->started_at,
                    'ended_at' => $match->ended_at,
                    'created_at' => $match->created_at,
                    'winner_id' => $match->winner_id,
                    'live_viewers' => $match->viewers ?? 0,
                    'has_vods' => !empty($match->vod_urls),
                    'has_streams' => !empty($match->stream_urls),
                    'duration_minutes' => $match->started_at && $match->ended_at 
                        ? $match->started_at->diffInMinutes($match->ended_at) 
                        : null,
                    // Include nested objects for components that might need them
                    'team1' => $match->team1,
                    'team2' => $match->team2,
                    'event' => $match->event
                ];
                
                return (object) $transformedMatch;
            });

            return response()->json([
                'success' => true,
                'data' => $matches->items(),
                'pagination' => [
                    'current_page' => $matches->currentPage(),
                    'per_page' => $matches->perPage(),
                    'total' => $matches->total(),
                    'last_page' => $matches->lastPage(),
                    'from' => $matches->firstItem(),
                    'to' => $matches->lastItem()
                ],
                'filters' => [
                    'available_statuses' => ['upcoming', 'live', 'completed'],
                    'available_formats' => ['BO1', 'BO3', 'BO5', 'BO7', 'BO9'],
                    'total_matches' => MvrlMatch::count(),
                    'live_matches' => MvrlMatch::where('status', 'live')->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created match.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team1_id' => 'required|exists:teams,id',
            'team2_id' => 'required|exists:teams,id|different:team1_id',
            'event_id' => 'nullable|exists:events,id',
            'scheduled_at' => 'required|date|after:now',
            'format' => 'required|in:BO1,BO3,BO5,BO7,BO9',
            'status' => 'sometimes|in:upcoming,scheduled',
            'maps' => 'required|array|min:1|max:9',
            'maps.*.map_name' => 'required|string|max:100',
            'maps.*.mode' => 'required|string|max:50',
            'stream_urls' => 'sometimes|array',
            'stream_urls.*' => 'url',
            'betting_urls' => 'sometimes|array',
            'betting_urls.*' => 'url',
            'vod_urls' => 'sometimes|array',
            'vod_urls.*' => 'url',
            'allow_past_date' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Validate best-of format matches map count
            $formatMapCount = [
                'BO1' => 1,
                'BO3' => 3,
                'BO5' => 5,
                'BO7' => 7,
                'BO9' => 9
            ];

            $requiredMaps = $formatMapCount[$request->format];
            if (count($request->maps) > $requiredMaps) {
                return response()->json([
                    'success' => false,
                    'message' => "Format {$request->format} cannot have more than {$requiredMaps} maps"
                ], 422);
            }

            // Create match
            $matchData = [
                'team1_id' => $request->team1_id,
                'team2_id' => $request->team2_id,
                'event_id' => $request->event_id,
                'scheduled_at' => $request->scheduled_at,
                'format' => $request->format,
                'status' => ($request->status === 'scheduled' ? 'upcoming' : $request->status) ?? 'upcoming',
                'team1_score' => 0,
                'team2_score' => 0,
                'series_score_team1' => 0,
                'series_score_team2' => 0,
                'current_map_number' => 1,
                'viewers' => 0,
                'stream_urls' => json_encode($request->stream_urls ?? []),
                'betting_urls' => json_encode($request->betting_urls ?? []),
                'vod_urls' => json_encode($request->vod_urls ?? []),
                'created_by' => auth()->id()
            ];

            $match = MvrlMatch::create($matchData);

            // Create match maps
            foreach ($request->maps as $index => $mapData) {
                MatchMap::create([
                    'match_id' => $match->id,
                    'map_number' => $index + 1,
                    'map_name' => $mapData['map_name'],
                    'game_mode' => $mapData['mode'],
                    'status' => 'upcoming',
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'team1_rounds' => 0,
                    'team2_rounds' => 0
                ]);
            }

            DB::commit();

            $match->load(['team1', 'team2', 'event']);

            // Flatten the match data for frontend compatibility
            $flattenedMatch = [
                'id' => $match->id,
                'team1_id' => $match->team1_id,
                'team2_id' => $match->team2_id,
                'team1_name' => $match->team1 ? $match->team1->name : 'TBD',
                'team2_name' => $match->team2 ? $match->team2->name : 'TBD',
                'team1_logo' => $match->team1 ? $match->team1->logo : null,
                'team2_logo' => $match->team2 ? $match->team2->logo : null,
                'team1_score' => $match->team1_score ?? 0,
                'team2_score' => $match->team2_score ?? 0,
                'event_id' => $match->event_id,
                'event_name' => $match->event ? $match->event->name : 'No Event',
                'event_logo' => $match->event ? $match->event->logo : null,
                'status' => $match->status ?? 'scheduled',
                'format' => $match->format,
                'scheduled_at' => $match->scheduled_at,
                'started_at' => $match->started_at,
                'ended_at' => $match->ended_at,
                'winner_id' => $match->winner_id,
                // Include nested objects for components that might need them
                'team1' => $match->team1,
                'team2' => $match->team2,
                'event' => $match->event
            ];

            return response()->json([
                'success' => true,
                'message' => 'Match created successfully',
                'data' => $flattenedMatch
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified match with detailed information.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $match = MvrlMatch::with([
                'team1:id,name,logo,country',
                'team2:id,name,logo,country',
                'event:id,name,tier,start_date,end_date',
                'players:id,handle,real_name'
            ])->find($id);

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Get match maps
            $maps = MatchMap::where('match_id', $match->id)
                ->orderBy('map_number')
                ->get();

            // Get player statistics
            $playerStats = MatchPlayerStat::with('player:id,handle,real_name')
                ->where('match_id', $match->id)
                ->get();

            // Parse JSON fields
            $match->stream_urls = json_decode($match->stream_urls, true) ?? [];
            $match->betting_urls = json_decode($match->betting_urls, true) ?? [];
            $match->vod_urls = json_decode($match->vod_urls, true) ?? [];
            $match->hero_data = json_decode($match->hero_data, true) ?? [];
            $match->player_stats = json_decode($match->player_stats, true) ?? [];

            // Add calculated fields
            $match->duration_minutes = $match->started_at && $match->ended_at 
                ? $match->started_at->diffInMinutes($match->ended_at) 
                : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'match' => $match,
                    'maps' => $maps,
                    'player_stats' => $playerStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified match.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $match = MvrlMatch::find($id);
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'team1_id' => 'sometimes|exists:teams,id',
            'team2_id' => 'sometimes|exists:teams,id|different:team1_id',
            'event_id' => 'sometimes|nullable|exists:events,id',
            'scheduled_at' => 'sometimes|date',
            'format' => 'sometimes|in:BO1,BO3,BO5,BO7,BO9',
            'status' => 'sometimes|in:upcoming,scheduled,live,completed',
            'team1_score' => 'sometimes|integer|min:0',
            'team2_score' => 'sometimes|integer|min:0',
            'winner_id' => 'sometimes|nullable|exists:teams,id',
            'stream_urls' => 'sometimes|array',
            'stream_urls.*' => 'url',
            'betting_urls' => 'sometimes|array',
            'betting_urls.*' => 'url',
            'vod_urls' => 'sometimes|array',
            'vod_urls.*' => 'url',
            'viewers' => 'sometimes|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only([
                'team1_id', 'team2_id', 'event_id', 'scheduled_at', 'format',
                'status', 'team1_score', 'team2_score', 'winner_id', 'viewers'
            ]);

            // Map 'scheduled' from frontend to 'upcoming' for database
            if (isset($updateData['status']) && $updateData['status'] === 'scheduled') {
                $updateData['status'] = 'upcoming';
            }

            // Handle JSON fields
            if ($request->has('stream_urls')) {
                $updateData['stream_urls'] = json_encode($request->stream_urls);
            }
            if ($request->has('betting_urls')) {
                $updateData['betting_urls'] = json_encode($request->betting_urls);
            }
            if ($request->has('vod_urls')) {
                $updateData['vod_urls'] = json_encode($request->vod_urls);
            }

            // Update series scores if individual scores are provided
            if ($request->has('team1_score')) {
                $updateData['series_score_team1'] = $request->team1_score;
            }
            if ($request->has('team2_score')) {
                $updateData['series_score_team2'] = $request->team2_score;
            }

            // Auto-determine winner if scores are provided
            if ($request->has('team1_score') && $request->has('team2_score')) {
                if ($request->team1_score > $request->team2_score) {
                    $updateData['winner_id'] = $match->team1_id;
                } elseif ($request->team2_score > $request->team1_score) {
                    $updateData['winner_id'] = $match->team2_id;
                } else {
                    $updateData['winner_id'] = null;
                }
            }

            // Handle status transitions
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'live':
                        if (!$match->started_at) {
                            $updateData['started_at'] = now();
                        }
                        break;
                    case 'completed':
                        if (!$match->ended_at) {
                            $updateData['ended_at'] = now();
                        }
                        break;
                }
            }

            $match->update($updateData);

            DB::commit();

            $match->load(['team1', 'team2', 'event']);

            // Flatten the match data for frontend compatibility
            $flattenedMatch = [
                'id' => $match->id,
                'team1_id' => $match->team1_id,
                'team2_id' => $match->team2_id,
                'team1_name' => $match->team1 ? $match->team1->name : 'TBD',
                'team2_name' => $match->team2 ? $match->team2->name : 'TBD',
                'team1_logo' => $match->team1 ? $match->team1->logo : null,
                'team2_logo' => $match->team2 ? $match->team2->logo : null,
                'team1_score' => $match->team1_score ?? 0,
                'team2_score' => $match->team2_score ?? 0,
                'event_id' => $match->event_id,
                'event_name' => $match->event ? $match->event->name : 'No Event',
                'event_logo' => $match->event ? $match->event->logo : null,
                'status' => $match->status ?? 'scheduled',
                'format' => $match->format,
                'scheduled_at' => $match->scheduled_at,
                'started_at' => $match->started_at,
                'ended_at' => $match->ended_at,
                'winner_id' => $match->winner_id,
                // Include nested objects for components that might need them
                'team1' => $match->team1,
                'team2' => $match->team2,
                'event' => $match->event
            ];

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully',
                'data' => $flattenedMatch
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified match from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $match = MvrlMatch::find($id);
            
            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            DB::beginTransaction();

            // Delete related data
            MatchMap::where('match_id', $match->id)->delete();
            MatchPlayerStat::where('match_id', $match->id)->delete();
            
            // Delete the match
            $match->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Match deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reschedule a match to a new date/time.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $match = MvrlMatch::find($id);
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date',
            'reason' => 'sometimes|string|max:500',
            'notify_teams' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldScheduledAt = $match->scheduled_at;
            
            $match->update([
                'scheduled_at' => $request->scheduled_at,
                'status' => 'upcoming' // Reset to upcoming if rescheduled
            ]);

            // Log the reschedule action
            activity()
                ->performedOn($match)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_scheduled_at' => $oldScheduledAt,
                    'new_scheduled_at' => $request->scheduled_at,
                    'reason' => $request->reason ?? 'No reason provided'
                ])
                ->log('match_rescheduled');

            return response()->json([
                'success' => true,
                'message' => 'Match rescheduled successfully',
                'data' => [
                    'match_id' => $match->id,
                    'old_scheduled_at' => $oldScheduledAt,
                    'new_scheduled_at' => $match->scheduled_at,
                    'reason' => $request->reason
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update live scoring data for a match.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateLiveScoring(Request $request, int $id): JsonResponse
    {
        $match = MvrlMatch::find($id);
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:upcoming,scheduled,live,completed',
            'current_map' => 'sometimes|integer|min:1',
            'series_score' => 'sometimes|array',
            'series_score.team1' => 'sometimes|integer|min:0',
            'series_score.team2' => 'sometimes|integer|min:0',
            'map_data' => 'sometimes|array',
            'map_data.*.map_number' => 'required_with:map_data|integer|min:1',
            'map_data.*.team1_score' => 'sometimes|integer|min:0',
            'map_data.*.team2_score' => 'sometimes|integer|min:0',
            'map_data.*.team1_rounds' => 'sometimes|integer|min:0',
            'map_data.*.team2_rounds' => 'sometimes|integer|min:0',
            'map_data.*.status' => 'sometimes|in:upcoming,live,completed',
            'player_stats' => 'sometimes|array',
            'hero_data' => 'sometimes|array',
            'viewers' => 'sometimes|integer|min:0',
            'timer_data' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = [];

            // Update match status
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
                
                if ($request->status === 'live' && !$match->started_at) {
                    $updateData['started_at'] = now();
                } elseif ($request->status === 'completed' && !$match->ended_at) {
                    $updateData['ended_at'] = now();
                }
            }

            // Update current map
            if ($request->has('current_map')) {
                $updateData['current_map_number'] = $request->current_map;
            }

            // Update series scores
            if ($request->has('series_score')) {
                if (isset($request->series_score['team1'])) {
                    $updateData['team1_score'] = $request->series_score['team1'];
                    $updateData['series_score_team1'] = $request->series_score['team1'];
                }
                if (isset($request->series_score['team2'])) {
                    $updateData['team2_score'] = $request->series_score['team2'];
                    $updateData['series_score_team2'] = $request->series_score['team2'];
                }
                
                // Determine winner
                if (isset($request->series_score['team1']) && isset($request->series_score['team2'])) {
                    if ($request->series_score['team1'] > $request->series_score['team2']) {
                        $updateData['winner_id'] = $match->team1_id;
                    } elseif ($request->series_score['team2'] > $request->series_score['team1']) {
                        $updateData['winner_id'] = $match->team2_id;
                    }
                }
            }

            // Update player stats
            if ($request->has('player_stats')) {
                $updateData['player_stats'] = json_encode($request->player_stats);
            }

            // Update hero data
            if ($request->has('hero_data')) {
                $updateData['hero_data'] = json_encode($request->hero_data);
            }

            // Update viewers
            if ($request->has('viewers')) {
                $updateData['viewers'] = $request->viewers;
            }

            // Update timer data
            if ($request->has('timer_data')) {
                $updateData['match_timer'] = json_encode($request->timer_data);
            }

            // Update match
            if (!empty($updateData)) {
                $match->update($updateData);
            }

            // Update map data
            if ($request->has('map_data')) {
                foreach ($request->map_data as $mapUpdate) {
                    $map = MatchMap::where('match_id', $match->id)
                        ->where('map_number', $mapUpdate['map_number'])
                        ->first();
                    
                    if ($map) {
                        $mapUpdateData = [];
                        
                        if (isset($mapUpdate['team1_score'])) {
                            $mapUpdateData['team1_score'] = $mapUpdate['team1_score'];
                        }
                        if (isset($mapUpdate['team2_score'])) {
                            $mapUpdateData['team2_score'] = $mapUpdate['team2_score'];
                        }
                        if (isset($mapUpdate['team1_rounds'])) {
                            $mapUpdateData['team1_rounds'] = $mapUpdate['team1_rounds'];
                        }
                        if (isset($mapUpdate['team2_rounds'])) {
                            $mapUpdateData['team2_rounds'] = $mapUpdate['team2_rounds'];
                        }
                        if (isset($mapUpdate['status'])) {
                            $mapUpdateData['status'] = $mapUpdate['status'];
                        }

                        // Determine map winner
                        if (isset($mapUpdate['team1_score']) && isset($mapUpdate['team2_score'])) {
                            if ($mapUpdate['team1_score'] > $mapUpdate['team2_score']) {
                                $mapUpdateData['winner_id'] = $match->team1_id;
                            } elseif ($mapUpdate['team2_score'] > $mapUpdate['team1_score']) {
                                $mapUpdateData['winner_id'] = $match->team2_id;
                            }
                        }

                        $map->update($mapUpdateData);
                    }
                }
            }

            DB::commit();

            $match->refresh();
            $match->load(['team1', 'team2', 'event']);

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
                    ],
                    'winner_id' => $match->winner_id,
                    'viewers' => $match->viewers
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update live scoring',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Control match state (start, pause, resume, complete, restart).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function controlMatch(Request $request, int $id): JsonResponse
    {
        $match = MvrlMatch::find($id);
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:start,complete,restart',
            'reason' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = [];
            $action = $request->action;

            switch ($action) {
                case 'start':
                    if ($match->status !== 'upcoming') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Match must be in upcoming status to start'
                        ], 422);
                    }
                    $updateData = [
                        'status' => 'live',
                        'started_at' => now()
                    ];
                    break;

                case 'complete':
                    if ($match->status !== 'live') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only live matches can be completed'
                        ], 422);
                    }
                    
                    $updateData = [
                        'status' => 'completed',
                        'ended_at' => now()
                    ];

                    // Auto-determine winner based on current scores
                    if ($match->team1_score > $match->team2_score) {
                        $updateData['winner_id'] = $match->team1_id;
                    } elseif ($match->team2_score > $match->team1_score) {
                        $updateData['winner_id'] = $match->team2_id;
                    }

                    // Mark all incomplete maps as completed
                    MatchMap::where('match_id', $match->id)
                        ->where('status', '!=', 'completed')
                        ->update(['status' => 'completed']);
                    break;

                case 'restart':
                    $updateData = [
                        'status' => 'upcoming',
                        'team1_score' => 0,
                        'team2_score' => 0,
                        'series_score_team1' => 0,
                        'series_score_team2' => 0,
                        'current_map_number' => 1,
                        'winner_id' => null,
                        'started_at' => null,
                        'ended_at' => null,
                        'player_stats' => null,
                        'hero_data' => null,
                        'viewers' => 0
                    ];

                    // Reset all maps
                    MatchMap::where('match_id', $match->id)->update([
                        'status' => 'upcoming',
                        'team1_score' => 0,
                        'team2_score' => 0,
                        'team1_rounds' => 0,
                        'team2_rounds' => 0,
                        'winner_id' => null,
                        'started_at' => null,
                        'ended_at' => null,
                        'duration_seconds' => null
                    ]);

                    // Delete player stats
                    MatchPlayerStat::where('match_id', $match->id)->delete();
                    break;

            }

            $match->update($updateData);

            // Log the action
            activity()
                ->performedOn($match)
                ->causedBy(auth()->user())
                ->withProperties([
                    'action' => $action,
                    'reason' => $request->reason ?? "No reason provided"
                ])
                ->log("match_{$action}");

            DB::commit();

            $match->refresh();

            return response()->json([
                'success' => true,
                'message' => "Match {$action} successfully",
                'data' => [
                    'match_id' => $match->id,
                    'status' => $match->status,
                    'action' => $action,
                    'started_at' => $match->started_at,
                    'ended_at' => $match->ended_at
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => "Failed to {$action} match",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manage team assignments for a match.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function manageTeams(Request $request, int $id): JsonResponse
    {
        $match = MvrlMatch::find($id);
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:swap,replace_team1,replace_team2',
            'team1_id' => 'sometimes|exists:teams,id',
            'team2_id' => 'sometimes|exists:teams,id',
            'reason' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($match->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify teams for completed matches'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $oldTeam1 = $match->team1_id;
            $oldTeam2 = $match->team2_id;
            $action = $request->action;

            switch ($action) {
                case 'swap':
                    $match->update([
                        'team1_id' => $oldTeam2,
                        'team2_id' => $oldTeam1,
                        'team1_score' => $match->team2_score,
                        'team2_score' => $match->team1_score,
                        'series_score_team1' => $match->series_score_team2,
                        'series_score_team2' => $match->series_score_team1
                    ]);
                    break;

                case 'replace_team1':
                    if (!$request->has('team1_id')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'team1_id is required for replace_team1 action'
                        ], 422);
                    }
                    if ($request->team1_id === $match->team2_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'New team1 cannot be the same as team2'
                        ], 422);
                    }
                    $match->update(['team1_id' => $request->team1_id]);
                    break;

                case 'replace_team2':
                    if (!$request->has('team2_id')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'team2_id is required for replace_team2 action'
                        ], 422);
                    }
                    if ($request->team2_id === $match->team1_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'New team2 cannot be the same as team1'
                        ], 422);
                    }
                    $match->update(['team2_id' => $request->team2_id]);
                    break;
            }

            // Update winner_id if it was one of the replaced teams
            if ($match->winner_id) {
                if ($action === 'swap') {
                    if ($match->winner_id === $oldTeam1) {
                        $match->update(['winner_id' => $match->team2_id]);
                    } elseif ($match->winner_id === $oldTeam2) {
                        $match->update(['winner_id' => $match->team1_id]);
                    }
                } elseif ($action === 'replace_team1' && $match->winner_id === $oldTeam1) {
                    $match->update(['winner_id' => $request->team1_id]);
                } elseif ($action === 'replace_team2' && $match->winner_id === $oldTeam2) {
                    $match->update(['winner_id' => $request->team2_id]);
                }
            }

            // Log the team change
            activity()
                ->performedOn($match)
                ->causedBy(auth()->user())
                ->withProperties([
                    'action' => $action,
                    'old_team1_id' => $oldTeam1,
                    'old_team2_id' => $oldTeam2,
                    'new_team1_id' => $match->team1_id,
                    'new_team2_id' => $match->team2_id,
                    'reason' => $request->reason ?? "No reason provided"
                ])
                ->log('match_teams_changed');

            DB::commit();

            $match->refresh();
            $match->load(['team1', 'team2', 'event']);

            return response()->json([
                'success' => true,
                'message' => 'Team assignment updated successfully',
                'data' => $match
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update team assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update match maps configuration.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateMaps(Request $request, int $id): JsonResponse
    {
        $match = MvrlMatch::find($id);
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'maps' => 'required|array|min:1|max:9',
            'maps.*.map_number' => 'required|integer|min:1',
            'maps.*.map_name' => 'required|string|max:100',
            'maps.*.game_mode' => 'required|string|max:50',
            'maps.*.status' => 'sometimes|in:upcoming,live,completed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($match->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify maps for completed matches'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Validate format vs map count
            $formatMapCount = [
                'BO1' => 1, 'BO3' => 3, 'BO5' => 5, 'BO7' => 7, 'BO9' => 9
            ];
            $maxMaps = $formatMapCount[$match->format] ?? 9;
            
            if (count($request->maps) > $maxMaps) {
                return response()->json([
                    'success' => false,
                    'message' => "Format {$match->format} cannot have more than {$maxMaps} maps"
                ], 422);
            }

            // Delete existing maps
            MatchMap::where('match_id', $match->id)->delete();

            // Create new maps
            foreach ($request->maps as $mapData) {
                MatchMap::create([
                    'match_id' => $match->id,
                    'map_number' => $mapData['map_number'],
                    'map_name' => $mapData['map_name'],
                    'game_mode' => $mapData['game_mode'],
                    'status' => $mapData['status'] ?? 'upcoming',
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'team1_rounds' => 0,
                    'team2_rounds' => 0
                ]);
            }

            DB::commit();

            $maps = MatchMap::where('match_id', $match->id)
                ->orderBy('map_number')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Match maps updated successfully',
                'data' => [
                    'match_id' => $match->id,
                    'maps' => $maps
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match maps',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update player statistics for a match.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePlayerStats(Request $request, int $id): JsonResponse
    {
        $match = MvrlMatch::find($id);
        
        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Match not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'player_stats' => 'required|array',
            'player_stats.*.player_id' => 'required|exists:players,id',
            'player_stats.*.team_id' => 'required|exists:teams,id',
            'player_stats.*.map_id' => 'sometimes|exists:match_maps,id',
            'player_stats.*.hero' => 'required|string|max:50',
            'player_stats.*.eliminations' => 'sometimes|integer|min:0',
            'player_stats.*.deaths' => 'sometimes|integer|min:0',
            'player_stats.*.assists' => 'sometimes|integer|min:0',
            'player_stats.*.damage_dealt' => 'sometimes|integer|min:0',
            'player_stats.*.healing_done' => 'sometimes|integer|min:0',
            'player_stats.*.time_played_seconds' => 'sometimes|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->player_stats as $statData) {
                // Verify player belongs to one of the match teams
                if (!in_array($statData['team_id'], [$match->team1_id, $match->team2_id])) {
                    continue;
                }

                $existingStat = MatchPlayerStat::where('match_id', $match->id)
                    ->where('player_id', $statData['player_id'])
                    ->where('map_id', $statData['map_id'] ?? null)
                    ->first();

                $statUpdateData = array_merge($statData, [
                    'match_id' => $match->id
                ]);

                // Calculate KDA
                if (isset($statData['eliminations']) && isset($statData['deaths']) && isset($statData['assists'])) {
                    $kills = $statData['eliminations'];
                    $deaths = $statData['deaths'];
                    $assists = $statData['assists'];
                    $statUpdateData['kda'] = $deaths > 0 ? round(($kills + $assists) / $deaths, 2) : $kills + $assists;
                }

                if ($existingStat) {
                    $existingStat->update($statUpdateData);
                } else {
                    MatchPlayerStat::create($statUpdateData);
                }
            }

            // Update match player_stats JSON field for backward compatibility
            $allStats = MatchPlayerStat::where('match_id', $match->id)->get();
            $match->update(['player_stats' => json_encode($allStats->toArray())]);

            DB::commit();

            $playerStats = MatchPlayerStat::with('player:id,handle,real_name')
                ->where('match_id', $match->id)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Player statistics updated successfully',
                'data' => [
                    'match_id' => $match->id,
                    'player_stats' => $playerStats
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update player statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform bulk operations on multiple matches.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkOperation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:delete,reschedule,change_status,change_format',
            'match_ids' => 'required|array|min:1|max:100',
            'match_ids.*' => 'exists:matches,id',
            'data' => 'sometimes|array',
            'data.scheduled_at' => 'sometimes|date',
            'data.status' => 'sometimes|in:upcoming,live,completed',
            'data.format' => 'sometimes|in:BO1,BO3,BO5,BO7,BO9'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $matchIds = $request->match_ids;
            $action = $request->action;
            $data = $request->data ?? [];
            
            $successCount = 0;
            $errors = [];

            foreach ($matchIds as $matchId) {
                try {
                    $match = MvrlMatch::find($matchId);
                    if (!$match) {
                        $errors[] = "Match {$matchId} not found";
                        continue;
                    }

                    switch ($action) {
                        case 'delete':
                            // Delete related data
                            MatchMap::where('match_id', $match->id)->delete();
                            MatchPlayerStat::where('match_id', $match->id)->delete();
                            $match->delete();
                            break;


                        case 'reschedule':
                            if (!isset($data['scheduled_at'])) {
                                $errors[] = "Match {$matchId}: scheduled_at is required for reschedule";
                                continue 2;
                            }
                            $match->update([
                                'scheduled_at' => $data['scheduled_at'],
                                'status' => 'upcoming'
                            ]);
                            break;

                        case 'change_status':
                            if (!isset($data['status'])) {
                                $errors[] = "Match {$matchId}: status is required for change_status";
                                continue 2;
                            }
                            
                            $updateData = ['status' => $data['status']];
                            
                            if ($data['status'] === 'live' && !$match->started_at) {
                                $updateData['started_at'] = now();
                            } elseif ($data['status'] === 'completed' && !$match->ended_at) {
                                $updateData['ended_at'] = now();
                            }
                            
                            $match->update($updateData);
                            break;

                        case 'change_format':
                            if (!isset($data['format'])) {
                                $errors[] = "Match {$matchId}: format is required for change_format";
                                continue 2;
                            }
                            $match->update(['format' => $data['format']]);
                            break;
                    }

                    $successCount++;

                } catch (\Exception $e) {
                    $errors[] = "Match {$matchId}: " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk operation completed successfully",
                'data' => [
                    'action' => $action,
                    'processed' => count($matchIds),
                    'successful' => $successCount,
                    'failed' => count($errors),
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match statistics and overview.
     *
     * @return JsonResponse
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_matches' => MvrlMatch::count(),
                'by_status' => [
                    'upcoming' => MvrlMatch::where('status', 'upcoming')->count(),
                    'live' => MvrlMatch::where('status', 'live')->count(),
                    'completed' => MvrlMatch::where('status', 'completed')->count(),
                ],
                'by_format' => [
                    'BO1' => MvrlMatch::where('format', 'BO1')->count(),
                    'BO3' => MvrlMatch::where('format', 'BO3')->count(),
                    'BO5' => MvrlMatch::where('format', 'BO5')->count(),
                    'BO7' => MvrlMatch::where('format', 'BO7')->count(),
                    'BO9' => MvrlMatch::where('format', 'BO9')->count()
                ],
                'recent_activity' => [
                    'last_24h' => MvrlMatch::where('created_at', '>=', now()->subDay())->count(),
                    'last_7d' => MvrlMatch::where('created_at', '>=', now()->subWeek())->count(),
                    'last_30d' => MvrlMatch::where('created_at', '>=', now()->subMonth())->count()
                ],
                'average_match_duration' => MvrlMatch::whereNotNull('started_at')
                    ->whereNotNull('ended_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) as avg_duration')
                    ->value('avg_duration'),
                'total_viewers' => MvrlMatch::sum('viewers')
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live matches currently in progress.
     *
     * @return JsonResponse
     */
    public function getLiveMatches(): JsonResponse
    {
        try {
            $liveMatches = MvrlMatch::with(['team1:id,name,logo', 'team2:id,name,logo', 'event:id,name'])
                ->where('status', 'live')
                ->select([
                    'id', 'team1_id', 'team2_id', 'event_id', 'format',
                    'team1_score', 'team2_score', 'current_map_number',
                    'viewers', 'started_at'
                ])
                ->orderBy('started_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'live_matches' => $liveMatches,
                    'count' => $liveMatches->count(),
                    'total_viewers' => $liveMatches->sum('viewers')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve live matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export match data for reporting or backup purposes.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function exportMatches(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'sometimes|in:json,csv',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
            'status' => 'sometimes|in:upcoming,scheduled,live,completed',
            'event_id' => 'sometimes|exists:events,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = MvrlMatch::with(['team1', 'team2', 'event'])
                ->select([
                    'id', 'team1_id', 'team2_id', 'event_id', 'scheduled_at',
                    'status', 'format', 'team1_score', 'team2_score',
                    'winner_id', 'started_at', 'ended_at', 'viewers'
                ]);

            // Apply filters
            if ($request->filled('date_from')) {
                $query->whereDate('scheduled_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('scheduled_at', '<=', $request->date_to);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('event_id')) {
                $query->where('event_id', $request->event_id);
            }

            $matches = $query->orderBy('scheduled_at', 'desc')->get();

            $exportData = $matches->map(function ($match) {
                return [
                    'id' => $match->id,
                    'team1_name' => $match->team1->name ?? 'Unknown',
                    'team2_name' => $match->team2->name ?? 'Unknown',
                    'event_name' => $match->event->name ?? 'No Event',
                    'scheduled_at' => $match->scheduled_at?->toISOString(),
                    'status' => $match->status,
                    'format' => $match->format,
                    'team1_score' => $match->team1_score,
                    'team2_score' => $match->team2_score,
                    'winner' => $match->winner_id === $match->team1_id ? $match->team1->name : 
                               ($match->winner_id === $match->team2_id ? $match->team2->name : null),
                    'started_at' => $match->started_at?->toISOString(),
                    'ended_at' => $match->ended_at?->toISOString(),
                    'viewers' => $match->viewers
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $exportData,
                    'total_count' => $exportData->count(),
                    'export_timestamp' => now()->toISOString(),
                    'filters_applied' => $request->only(['date_from', 'date_to', 'status', 'event_id'])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}