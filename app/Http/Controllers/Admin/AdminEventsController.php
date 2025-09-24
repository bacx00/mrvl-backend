<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Team;
use App\Models\BracketMatch;
use App\Models\BracketStage;
use App\Models\Tournament;
use App\Services\BracketService;
use App\Services\BracketGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminEventsController extends Controller
{
    protected $bracketService;
    protected $bracketGenerationService;

    public function __construct(BracketService $bracketService, BracketGenerationService $bracketGenerationService)
    {
        $this->bracketService = $bracketService;
        $this->bracketGenerationService = $bracketGenerationService;
    }

    /**
     * Display a listing of events
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Event::query();

            // Apply filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhere('slug', 'LIKE', "%{$search}%");
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('featured')) {
                $query->where('featured', $request->boolean('featured'));
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $events = $query->paginate($perPage);

            // Add computed fields
            $events->through(function ($event) {
                $event->teams_count = $event->teams()->count();
                $event->matches_count = $event->matches()->count();
                $event->progress_percentage = $this->calculateEventProgress($event);
                $event->formatted_prize_pool = $this->formatPrizePool($event);
                return $event;
            });

            return response()->json([
                'success' => true,
                'data' => $events->items(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created event
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Prepare validation rules
            $rules = [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'nullable|in:tournament,league,qualifier,showmatch,championship,scrim,regional,international,invitational,community,friendly,practice,exhibition',
                'format' => 'nullable|in:single_elimination,double_elimination,round_robin,swiss,group_stage,bo1,bo3,bo5',
                'status' => 'nullable|in:upcoming,live,completed,cancelled',
                'prize_pool' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'registration_start' => 'nullable|date',
                'registration_end' => 'nullable|date',
                'max_teams' => 'nullable|integer|min:2|max:256',
                'region' => 'nullable|string',
                'game_mode' => 'nullable|string',
                'timezone' => 'nullable|string',
                'tier' => 'nullable|in:S,A,B,C,D',
                'featured' => 'nullable|boolean',
                'public' => 'nullable|boolean',
                'rules' => 'nullable|string',
                'prize_distribution' => 'nullable|json',
                'registration_requirements' => 'nullable|json',
                'streams' => 'nullable|json',
                'social_links' => 'nullable|json'
            ];

            // Check if logo is a file or string
            if ($request->hasFile('logo')) {
                $rules['logo'] = 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120';
            } else {
                $rules['logo'] = 'nullable|string';
            }

            // Generate slug from name if not provided
            if (!$request->has('slug')) {
                $request->merge(['slug' => Str::slug($request->name)]);
            }
            $rules['slug'] = 'required|string|max:255|unique:events';

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Prepare event data
            $eventData = $request->except(['logo', 'prize_distribution', 'registration_requirements', 'streams', 'social_links']);
            
            // Set defaults for required fields
            $eventData['type'] = $eventData['type'] ?? 'tournament';
            $eventData['format'] = $eventData['format'] ?? 'single_elimination';
            $eventData['status'] = $eventData['status'] ?? 'upcoming';
            $eventData['max_teams'] = $eventData['max_teams'] ?? 16;
            
            // Handle logo file upload
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                $logoPath = $logoFile->store('events/logos', 'public');
                $eventData['logo'] = '/storage/' . $logoPath;
            } elseif ($request->has('logo') && !empty($request->logo)) {
                $eventData['logo'] = $request->logo;
            }

            // Parse JSON fields if they come as strings from FormData
            $jsonFields = ['prize_distribution', 'registration_requirements', 'streams', 'social_links'];
            $details = [];
            
            foreach ($jsonFields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    if (is_string($value) && !empty($value)) {
                        try {
                            $details[$field] = json_decode($value, true);
                        } catch (\Exception $e) {
                            // If JSON parsing fails, treat as empty
                            $details[$field] = [];
                        }
                    } elseif (is_array($value)) {
                        $details[$field] = $value;
                    }
                }
            }
            
            // Save details if any were set
            if (!empty($details)) {
                $eventData['details'] = $details;
            }

            // Convert featured and public from "1"/"0" strings to booleans
            if ($request->has('featured')) {
                $eventData['featured'] = filter_var($request->featured, FILTER_VALIDATE_BOOLEAN);
            }
            if ($request->has('public')) {
                $eventData['public'] = filter_var($request->public, FILTER_VALIDATE_BOOLEAN);
            }

            // Create event
            $event = Event::create($eventData);

            // Create associated tournament if needed
            if ($request->type === 'tournament') {
                Tournament::create([
                    'event_id' => $event->id,
                    'name' => $event->name,
                    'format' => $event->format,
                    'status' => $event->status,
                    'max_teams' => $event->max_teams,
                    'prize_pool' => $event->prize_pool,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date
                ]);
            }

            DB::commit();

            Log::info('Event created', ['event_id' => $event->id, 'admin_id' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => $event
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified event
     */
    public function show($id): JsonResponse
    {
        try {
            $event = Event::with(['teams', 'matches', 'organizer'])->findOrFail($id);

            // Add computed fields
            $event->teams_count = $event->teams->count();
            $event->matches_count = $event->matches->count();
            $event->progress_percentage = $this->calculateEventProgress($event);
            $event->formatted_prize_pool = $this->formatPrizePool($event);

            return response()->json([
                'success' => true,
                'data' => $event
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified event
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $event = Event::findOrFail($id);

            // Handle both file upload and string logo
            $rules = [
                'name' => 'string|max:255',
                'description' => 'nullable|string',
                'type' => 'nullable|in:tournament,league,qualifier,showmatch,championship,scrim,regional,international,invitational,community,friendly,practice,exhibition',
                'format' => 'nullable|in:single_elimination,double_elimination,round_robin,swiss,group_stage,bo1,bo3,bo5',
                'status' => 'nullable|in:upcoming,live,completed,cancelled',
                'prize_pool' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'registration_start' => 'nullable|date',
                'registration_end' => 'nullable|date',
                'max_teams' => 'nullable|integer|min:2|max:256',
                'region' => 'nullable|string',
                'game_mode' => 'nullable|string',
                'timezone' => 'nullable|string',
                'tier' => 'nullable|in:S,A,B,C,D',
                'featured' => 'nullable|boolean',
                'public' => 'nullable|boolean',
                'rules' => 'nullable|string',
                'prize_distribution' => 'nullable|json',
                'registration_requirements' => 'nullable|json',
                'streams' => 'nullable|json',
                'social_links' => 'nullable|json',
                'bracket_data' => 'nullable|array'
            ];

            // Check if logo is a file or string
            if ($request->hasFile('logo')) {
                $rules['logo'] = 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120';
            } else {
                $rules['logo'] = 'nullable|string';
            }

            // If no slug provided, generate one from name
            if (!$request->has('slug') && $request->has('name')) {
                $request->merge(['slug' => \Str::slug($request->name)]);
            }
            
            // Add slug validation only if provided
            if ($request->has('slug')) {
                $rules['slug'] = 'string|max:255|unique:events,slug,' . $id;
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Prepare event data
            $eventData = $request->except(['logo', 'prize_distribution', 'registration_requirements', 'streams', 'social_links']);
            
            // Handle logo file upload
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                $logoPath = $logoFile->store('events/logos', 'public');
                $eventData['logo'] = '/storage/' . $logoPath;
            } elseif ($request->has('logo') && !empty($request->logo)) {
                // Keep existing logo if it's a string URL
                $eventData['logo'] = $request->logo;
            }

            // Parse JSON fields if they come as strings from FormData
            $jsonFields = ['prize_distribution', 'registration_requirements', 'streams', 'social_links'];
            $details = $event->details ?? [];
            
            foreach ($jsonFields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    if (is_string($value) && !empty($value)) {
                        try {
                            $details[$field] = json_decode($value, true);
                        } catch (\Exception $e) {
                            // If JSON parsing fails, treat as empty
                            $details[$field] = [];
                        }
                    } elseif (is_array($value)) {
                        $details[$field] = $value;
                    }
                }
            }
            
            // Save details if any were set
            if (!empty($details)) {
                $eventData['details'] = $details;
            }

            // Convert featured and public from "1"/"0" strings to booleans
            if ($request->has('featured')) {
                $eventData['featured'] = filter_var($request->featured, FILTER_VALIDATE_BOOLEAN);
            }
            if ($request->has('public')) {
                $eventData['public'] = filter_var($request->public, FILTER_VALIDATE_BOOLEAN);
            }

            // Update the event
            $event->update($eventData);

            // Clear cache for this event
            Cache::forget('event_' . $id);
            Cache::forget('event_bracket_' . $id);

            DB::commit();

            Log::info('Event updated', ['event_id' => $event->id, 'admin_id' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'data' => $event
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified event
     */
    public function destroy($id): JsonResponse
    {
        try {
            $event = Event::findOrFail($id);

            // Check if event can be deleted
            if ($event->status === 'ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an ongoing event'
                ], 400);
            }

            if ($event->teams()->count() > 0 || $event->matches()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete event with associated teams or matches'
                ], 400);
            }

            DB::beginTransaction();

            // Delete associated bracket data
            BracketMatch::where('event_id', $id)->delete();
            BracketStage::where('event_id', $id)->delete();

            // Delete the event
            $event->delete();

            // Clear cache
            Cache::forget('event_' . $id);
            Cache::forget('event_bracket_' . $id);

            DB::commit();

            Log::info('Event deleted', ['event_id' => $id, 'admin_id' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teams for an event
     */
    public function getEventTeams($eventId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);
            
            $teams = $event->teams()
                ->with(['players'])
                ->get()
                ->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'short_name' => $team->short_name,
                        'logo' => $team->logo,
                        'region' => $team->region,
                        'rating' => $team->rating,
                        'seed' => $team->pivot->seed ?? null,
                        'registered_at' => $team->pivot->created_at ?? null,
                        'players_count' => $team->players->count(),
                        'captain' => $team->captain // captain is a string field, not a relationship
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $teams
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching event teams: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch event teams',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a team to an event (route alias)
     */
    public function addTeamToEvent(Request $request, $eventId): JsonResponse
    {
        return $this->addTeam($request, $eventId);
    }

    /**
     * Add a team to an event
     */
    public function addTeam(Request $request, $eventId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);
            
            $validator = Validator::make($request->all(), [
                'team_id' => 'required|exists:teams,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if team is already in the event
            if ($event->teams()->where('team_id', $request->team_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is already registered for this event'
                ], 400);
            }

            // Check max teams limit
            if ($event->teams()->count() >= $event->max_teams) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event has reached maximum team capacity'
                ], 400);
            }

            // Add team to event
            $event->teams()->attach($request->team_id, [
                'status' => 'confirmed',
                'seed' => $event->teams()->count() + 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Clear cache
            Cache::forget('event_' . $eventId);

            Log::info('Team added to event', [
                'event_id' => $eventId,
                'team_id' => $request->team_id,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team added successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding team to event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add team to event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a team from an event (route alias)
     */
    public function removeTeamFromEvent($eventId, $teamId): JsonResponse
    {
        return $this->removeTeam($eventId, $teamId);
    }

    /**
     * Remove a team from an event
     */
    public function removeTeam($eventId, $teamId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);

            // Check if team is in the event
            if (!$event->teams()->where('team_id', $teamId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is not registered for this event'
                ], 404);
            }

            // Check if team has played matches
            $hasMatches = BracketMatch::where('event_id', $eventId)
                ->where(function ($q) use ($teamId) {
                    $q->where('team1_id', $teamId)
                      ->orWhere('team2_id', $teamId);
                })
                ->where('status', '!=', 'pending')
                ->exists();

            if ($hasMatches) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove team that has played matches'
                ], 400);
            }

            // Remove team from event
            $event->teams()->detach($teamId);

            // Clear cache
            Cache::forget('event_' . $eventId);

            Log::info('Team removed from event', [
                'event_id' => $eventId,
                'team_id' => $teamId,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing team from event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove team from event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate bracket for an event
     */
    public function generateBracket(Request $request, $eventId): JsonResponse
    {
        try {
            $event = Event::with('teams')->findOrFail($eventId);

            // Validate request
            $validator = Validator::make($request->all(), [
                'format' => 'required|in:single_elimination,double_elimination,round_robin,swiss',
                'seeding_method' => 'nullable|in:random,rating,manual',
                'shuffle_seeds' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if event has teams
            if ($event->teams->count() < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Need at least 2 teams to generate bracket'
                ], 400);
            }

            // Check if bracket already exists
            $existingBracket = BracketStage::where('event_id', $eventId)->exists();
            if ($existingBracket && !$request->boolean('force')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bracket already exists. Use force=true to regenerate.'
                ], 400);
            }

            DB::beginTransaction();

            // Clear existing bracket if force regeneration
            if ($existingBracket && $request->boolean('force')) {
                BracketMatch::where('event_id', $eventId)->delete();
                BracketStage::where('event_id', $eventId)->delete();
            }

            // Generate the bracket
            $bracketData = $this->bracketGenerationService->generateBracket(
                $event,
                $request->format ?? $event->format,
                $request->seeding_method ?? 'rating',
                $request->boolean('shuffle_seeds', false)
            );

            // Update event status
            $event->update(['status' => 'ongoing']);

            // Clear cache
            Cache::forget('event_' . $eventId);
            Cache::forget('event_bracket_' . $eventId);

            DB::commit();

            Log::info('Bracket generated', [
                'event_id' => $eventId,
                'format' => $request->format,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bracket generated successfully',
                'data' => [
                    'event_id' => $eventId,
                    'format' => $request->format ?? $event->format,
                    'teams_count' => $event->teams->count(),
                    'bracket_data' => $bracketData
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generating bracket: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear/Reset bracket for an event
     */
    public function clearBracket(Request $request, $eventId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);

            // Allow force clear for manual bracket management
            $forceClear = $request->boolean('force', true); // Default to true for manual management

            if (!$forceClear) {
                // Only check for ongoing matches if not forcing
                $hasOngoingMatches = BracketMatch::where('event_id', $eventId)
                    ->where('status', 'ongoing')
                    ->exists();

                if ($hasOngoingMatches) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot clear bracket with ongoing matches. Use force=true to override.'
                    ], 400);
                }
            }

            DB::beginTransaction();

            // Delete all bracket data
            BracketMatch::where('event_id', $eventId)->delete();
            BracketStage::where('event_id', $eventId)->delete();

            // Reset event status if needed
            if ($event->status === 'ongoing' && $event->start_date > now()) {
                $event->update(['status' => 'upcoming']);
            }

            // Clear cache
            Cache::forget('event_' . $eventId);
            Cache::forget('event_bracket_' . $eventId);

            DB::commit();

            Log::info('Bracket cleared', [
                'event_id' => $eventId,
                'forced' => $forceClear,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bracket cleared successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error clearing bracket: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update bracket match score
     */
    public function updateBracketMatchScore(Request $request, $matchId): JsonResponse
    {
        try {
            $request->validate([
                'team1_score' => 'required|integer|min:0|max:3',
                'team2_score' => 'required|integer|min:0|max:3'
            ]);
            
            $match = BracketMatch::findOrFail($matchId);
            
            DB::beginTransaction();
            
            // Update scores
            $match->team1_score = $request->team1_score;
            $match->team2_score = $request->team2_score;
            
            // Determine winner if match is complete (best of 3)
            if ($request->team1_score >= 2 || $request->team2_score >= 2) {
                $match->status = 'completed';
                $match->winner_id = $request->team1_score > $request->team2_score 
                    ? $match->team1_id 
                    : $match->team2_id;
                $match->loser_id = $request->team1_score > $request->team2_score 
                    ? $match->team2_id 
                    : $match->team1_id;
                $match->completed_at = now();
                
                // Progress winner to next match if configured
                if ($match->winner_advances_to) {
                    $this->progressWinnerToNextMatch($match);
                }
                
                // Progress loser to lower bracket if configured (double elimination)
                if ($match->loser_advances_to) {
                    $this->progressLoserToLowerBracket($match);
                }
            } else {
                // Match is ongoing
                $match->status = 'ongoing';
                if (!$match->started_at) {
                    $match->started_at = now();
                }
            }
            
            $match->save();
            
            // Clear cache
            Cache::forget('event_bracket_' . $match->event_id);
            Cache::forget('bracket_match_' . $matchId);
            
            // Broadcast update for real-time sync
            broadcast(new \App\Events\BracketUpdated($match->event_id, $match));
            
            DB::commit();
            
            Log::info('Bracket match score updated', [
                'match_id' => $matchId,
                'scores' => [$request->team1_score, $request->team2_score],
                'admin_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Match score updated successfully',
                'data' => $match->fresh(['team1', 'team2'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating bracket match score: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match score',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Progress winner to next match
     */
    private function progressWinnerToNextMatch($match)
    {
        $nextMatch = BracketMatch::find($match->winner_advances_to);
        if ($nextMatch) {
            // Determine which slot (team1 or team2) based on match position
            if ($nextMatch->team1_source === "Winner of Match {$match->id}") {
                $nextMatch->team1_id = $match->winner_id;
            } else {
                $nextMatch->team2_id = $match->winner_id;
            }
            
            // If both teams are set, mark as ready
            if ($nextMatch->team1_id && $nextMatch->team2_id) {
                $nextMatch->status = 'ready';
            }
            
            $nextMatch->save();
        }
    }

    /**
     * Progress loser to lower bracket (for double elimination)
     */
    private function progressLoserToLowerBracket($match)
    {
        $lowerBracketMatch = BracketMatch::find($match->loser_advances_to);
        if ($lowerBracketMatch) {
            // Determine which slot based on match position
            if ($lowerBracketMatch->team1_source === "Loser of Match {$match->id}") {
                $lowerBracketMatch->team1_id = $match->loser_id;
            } else {
                $lowerBracketMatch->team2_id = $match->loser_id;
            }
            
            // If both teams are set, mark as ready
            if ($lowerBracketMatch->team1_id && $lowerBracketMatch->team2_id) {
                $lowerBracketMatch->status = 'ready';
            }
            
            $lowerBracketMatch->save();
        }
    }

    /**
     * Get bracket data for an event
     */
    public function getBracket($eventId): JsonResponse
    {
        try {
            // Try to get from cache first
            $cacheKey = 'event_bracket_' . $eventId;
            $cachedBracket = Cache::get($cacheKey);
            
            if ($cachedBracket) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedBracket,
                    'cached' => true
                ]);
            }
            
            $event = Event::findOrFail($eventId);
            
            // Get all bracket stages
            $bracketStages = BracketStage::where('event_id', $eventId)
                ->orderBy('order')
                ->get();
            
            // Get all bracket matches with teams
            $matches = BracketMatch::where('event_id', $eventId)
                ->with(['team1', 'team2'])
                ->get();
            
            // Build bracket structure
            $bracketData = [
                'type' => $event->format,
                'status' => $event->status,
                'rounds' => [],
                'matches' => []
            ];
            
            // Group matches by round
            foreach ($bracketStages as $stage) {
                $stageMatches = $matches->where('bracket_stage_id', $stage->id)->map(function ($match) {
                    return [
                        'id' => $match->id,
                        'match_id' => $match->match_id,
                        'round_name' => $match->round_name,
                        'round_number' => $match->round_number,
                        'position' => $match->match_number,
                        'team1' => $match->team1 ? [
                            'id' => $match->team1->id,
                            'name' => $match->team1->name,
                            'short_name' => $match->team1->short_name,
                            'logo' => $match->team1->logo,
                            'score' => $match->team1_score,
                            'seed' => $match->team1_seed
                        ] : null,
                        'team2' => $match->team2 ? [
                            'id' => $match->team2->id,
                            'name' => $match->team2->name,
                            'short_name' => $match->team2->short_name,
                            'logo' => $match->team2->logo,
                            'score' => $match->team2_score,
                            'seed' => $match->team2_seed
                        ] : null,
                        'team1_score' => $match->team1_score,
                        'team2_score' => $match->team2_score,
                        'winner_id' => $match->winner_id,
                        'loser_id' => $match->loser_id,
                        'status' => $match->status,
                        'best_of' => $match->best_of,
                        'team1_source' => $match->team1_source,
                        'team2_source' => $match->team2_source,
                        'winner_advances_to' => $match->winner_advances_to,
                        'loser_advances_to' => $match->loser_advances_to
                    ];
                });
                
                // Organize by round name
                $roundName = $stage->name;
                if (!isset($bracketData['rounds'][$roundName])) {
                    $bracketData['rounds'][$roundName] = [
                        'round_number' => $stage->order,
                        'matches' => []
                    ];
                }
                $bracketData['rounds'][$roundName]['matches'] = $stageMatches->values()->toArray();
                
                // Also add to flat matches array
                $bracketData['matches'] = array_merge($bracketData['matches'], $stageMatches->toArray());
            }
            
            // Cache the result for 1 minute
            Cache::put($cacheKey, $bracketData, 60);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'event_id' => $eventId,
                    'event_name' => $event->name,
                    'format' => $event->format,
                    'status' => $event->status,
                    'bracket' => $bracketData
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching bracket: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bracket data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get event statistics and analytics
     */
    public function getEventStatistics($id): JsonResponse
    {
        try {
            $event = Event::with(['teams', 'matches'])->findOrFail($id);

            $stats = [
                'overview' => [
                    'total_teams' => $event->teams->count(),
                    'total_matches' => $event->matches->count(),
                    'completed_matches' => $event->matches->where('status', 'completed')->count(),
                    'ongoing_matches' => $event->matches->where('status', 'ongoing')->count(),
                    'progress_percentage' => $this->calculateEventProgress($event),
                    'prize_pool' => $this->formatPrizePool($event),
                    'views' => $event->views ?? 0
                ],
                'team_distribution' => [
                    'by_region' => $event->teams->groupBy('region')->map->count(),
                    'by_status' => $event->teams->groupBy('pivot.status')->map->count(),
                ],
                'match_statistics' => [
                    'by_status' => $event->matches->groupBy('status')->map->count(),
                    'average_duration' => $event->matches()
                        ->whereNotNull('completed_at')
                        ->whereNotNull('started_at')
                        ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, started_at, completed_at)')),
                    'total_maps_played' => $event->matches()
                        ->whereNotNull('team1_score')
                        ->whereNotNull('team2_score')
                        ->sum(DB::raw('team1_score + team2_score'))
                ],
                'engagement' => [
                    'total_views' => $event->views ?? 0,
                    'peak_concurrent_viewers' => 0, // TODO: Implement if tracking
                    'unique_viewers' => 0, // TODO: Implement if tracking
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching event statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch event statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations for events
     */
    public function bulkOperation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'operation' => 'required|string|in:delete,update_status,feature,unfeature,archive',
                'event_ids' => 'required|array|min:1',
                'event_ids.*' => 'exists:events,id',
                'data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $operation = $request->operation;
            $eventIds = $request->event_ids;
            $data = $request->data ?? [];

            DB::beginTransaction();

            $results = [
                'success_count' => 0,
                'failed_count' => 0,
                'errors' => []
            ];

            foreach ($eventIds as $eventId) {
                try {
                    $event = Event::find($eventId);
                    if (!$event) {
                        $results['failed_count']++;
                        $results['errors'][] = "Event {$eventId} not found";
                        continue;
                    }

                    switch ($operation) {
                        case 'delete':
                            if ($event->status !== 'ongoing' && $event->teams()->count() === 0) {
                                $event->delete();
                                $results['success_count']++;
                            } else {
                                $results['failed_count']++;
                                $results['errors'][] = "Cannot delete event {$eventId} - ongoing or has teams";
                            }
                            break;

                        case 'update_status':
                            if (isset($data['status'])) {
                                $event->update(['status' => $data['status']]);
                                $results['success_count']++;
                            }
                            break;

                        case 'feature':
                            $event->update(['featured' => true]);
                            $results['success_count']++;
                            break;

                        case 'unfeature':
                            $event->update(['featured' => false]);
                            $results['success_count']++;
                            break;

                        case 'archive':
                            $event->update(['status' => 'completed']);
                            $results['success_count']++;
                            break;
                    }

                } catch (\Exception $e) {
                    $results['failed_count']++;
                    $results['errors'][] = "Error processing event {$eventId}: " . $e->getMessage();
                }
            }

            DB::commit();

            Log::info('Bulk operation completed', [
                'operation' => $operation,
                'results' => $results,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bulk {$operation} completed",
                'data' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk operation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Calculate event progress percentage
     */
    private function calculateEventProgress($event): int
    {
        $totalMatches = $event->matches()->count();
        if ($totalMatches === 0) return 0;
        
        $completedMatches = $event->matches()->where('status', 'completed')->count();
        return round(($completedMatches / $totalMatches) * 100);
    }

    /**
     * Helper: Format prize pool with currency
     */
    private function formatPrizePool($event): string
    {
        if (!$event->prize_pool) return 'TBD';
        
        $currency = $event->currency ?? 'USD';
        return number_format($event->prize_pool, 0) . ' ' . $currency;
    }
}