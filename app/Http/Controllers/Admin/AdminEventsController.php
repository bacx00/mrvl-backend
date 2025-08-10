<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\User;
use App\Models\BracketMatch;
use App\Models\TournamentRegistration;
use App\Services\BracketGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdminEventsController extends Controller
{
    protected $bracketService;

    public function __construct(BracketGenerationService $bracketService = null)
    {
        $this->bracketService = $bracketService;
    }

    /**
     * Display a paginated list of all events with advanced filtering and search
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');
            $status = $request->get('status');
            $type = $request->get('type');
            $tier = $request->get('tier');
            $region = $request->get('region');
            $format = $request->get('format');
            $featured = $request->get('featured');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $query = Event::with(['organizer:id,name,email', 'teams:id,name,logo'])
                ->withCount(['teams', 'matches']);

            // Apply filters
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($type) {
                $query->where('type', $type);
            }

            if ($tier) {
                $query->where('tier', $tier);
            }

            if ($region) {
                $query->where('region', $region);
            }

            if ($format) {
                $query->where('format', $format);
            }

            if ($featured !== null) {
                $query->where('featured', $featured === 'true');
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            $events = $query->paginate($perPage);

            // Add computed fields
            $events->getCollection()->transform(function ($event) {
                $event->is_registration_open = $event->registration_open;
                $event->can_register_teams = $event->canRegisterTeam();
                $event->progress_percentage = $event->progress_percentage;
                $event->formatted_prize_pool = $event->formatted_prize_pool;
                return $event;
            });

            return response()->json([
                'success' => true,
                'data' => $events,
                'stats' => $this->getEventStats(),
                'filters' => [
                    'types' => Event::TYPES,
                    'tiers' => Event::TIERS,
                    'formats' => Event::FORMATS,
                    'statuses' => Event::STATUSES,
                    'regions' => $this->getAvailableRegions()
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
     * Show detailed event information for admin management
     */
    public function show($id): JsonResponse
    {
        try {
            $event = Event::with([
                'organizer:id,name,email',
                'teams:id,name,short_name,logo,region,country,rating',
                'matches:id,event_id,team1_id,team2_id,status,scheduled_at,completed_at,team1_score,team2_score',
                'brackets:id,event_id,type,stage,bracket_data,created_at',
                'standings:id,event_id,team_id,position,points,wins,losses'
            ])->findOrFail($id);

            // Add computed fields
            $event->is_registration_open = $event->registration_open;
            $event->can_register_teams = $event->canRegisterTeam();
            $event->progress_percentage = $event->progress_percentage;
            $event->formatted_prize_pool = $event->formatted_prize_pool;
            $event->has_started = $event->hasStarted();
            $event->has_ended = $event->hasEnded();
            $event->is_live = $event->isLive();

            // Add team statistics
            $event->team_stats = [
                'registered' => $event->teams()->wherePivot('status', 'registered')->count(),
                'checked_in' => $event->teams()->wherePivot('status', 'checked_in')->count(),
                'disqualified' => $event->teams()->wherePivot('status', 'disqualified')->count(),
                'total' => $event->teams_count
            ];

            // Add match statistics
            $event->match_stats = [
                'total' => $event->matches_count,
                'completed' => $event->matches()->where('status', 'completed')->count(),
                'ongoing' => $event->matches()->where('status', 'ongoing')->count(),
                'scheduled' => $event->matches()->where('status', 'scheduled')->count(),
                'cancelled' => $event->matches()->where('status', 'cancelled')->count()
            ];

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
     * Create a new event with comprehensive validation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:events,name',
                'description' => 'required|string',
                'type' => 'required|string|in:' . implode(',', array_keys(Event::TYPES)),
                'tier' => 'required|string|in:' . implode(',', array_keys(Event::TIERS)),
                'format' => 'required|string|in:' . implode(',', array_keys(Event::FORMATS)),
                'region' => 'required|string',
                'game_mode' => 'nullable|string',
                'start_date' => 'required|date|after:now',
                'end_date' => 'required|date|after:start_date',
                'registration_start' => 'required|date|before:start_date',
                'registration_end' => 'required|date|after:registration_start|before:start_date',
                'timezone' => 'required|string',
                'max_teams' => 'required|integer|min:2|max:1024',
                'prize_pool' => 'nullable|numeric|min:0',
                'currency' => 'required|string|size:3',
                'prize_distribution' => 'nullable|array',
                'organizer_id' => 'nullable|exists:users,id',
                'rules' => 'nullable|string',
                'registration_requirements' => 'nullable|array',
                'streams' => 'nullable|array',
                'social_links' => 'nullable|array',
                'featured' => 'nullable|boolean',
                'public' => 'nullable|boolean',
                'logo' => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:5120',
                'banner' => 'nullable|string',
                'sponsors' => 'nullable|array',
                'partners' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $eventData = $validator->validated();
            $eventData['slug'] = $this->generateUniqueSlug($eventData['name']);
            $eventData['organizer_id'] = $eventData['organizer_id'] ?? auth()->id();
            $eventData['status'] = 'upcoming';

            // Handle logo file upload
            if ($request->hasFile('logo')) {
                $logoPath = $this->handleLogoUpload($request->file('logo'));
                $eventData['logo'] = $logoPath;
            }

            // Validate prize distribution if provided
            if (isset($eventData['prize_distribution']) && $eventData['prize_pool']) {
                $totalDistribution = array_sum($eventData['prize_distribution']);
                if ($totalDistribution != 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Prize distribution must add up to 100%'
                    ], 422);
                }
            }

            $event = Event::create($eventData);

            DB::commit();

            $event->load(['organizer:id,name,email']);

            Log::info('Event created successfully', ['event_id' => $event->id, 'admin_id' => auth()->id()]);

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
     * Update an existing event with validation
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $event = Event::findOrFail($id);

            // Check if event can be edited
            if ($event->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit a completed event'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|unique:events,name,' . $id,
                'description' => 'sometimes|string',
                'type' => 'sometimes|string|in:' . implode(',', array_keys(Event::TYPES)),
                'tier' => 'sometimes|string|in:' . implode(',', array_keys(Event::TIERS)),
                'format' => 'sometimes|string|in:' . implode(',', array_keys(Event::FORMATS)),
                'region' => 'sometimes|string',
                'game_mode' => 'nullable|string',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date',
                'registration_start' => 'sometimes|date',
                'registration_end' => 'sometimes|date|after:registration_start',
                'timezone' => 'sometimes|string',
                'max_teams' => 'sometimes|integer|min:2|max:1024',
                'prize_pool' => 'nullable|numeric|min:0',
                'currency' => 'sometimes|string|size:3',
                'prize_distribution' => 'nullable|array',
                'organizer_id' => 'nullable|exists:users,id',
                'rules' => 'nullable|string',
                'registration_requirements' => 'nullable|array',
                'streams' => 'nullable|array',
                'social_links' => 'nullable|array',
                'featured' => 'nullable|boolean',
                'public' => 'nullable|boolean',
                'logo' => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:5120',
                'banner' => 'nullable|string',
                'sponsors' => 'nullable|array',
                'partners' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $eventData = $validator->validated();

            // Update slug if name changed
            if (isset($eventData['name']) && $eventData['name'] !== $event->name) {
                $eventData['slug'] = $this->generateUniqueSlug($eventData['name']);
            }

            // Handle logo file upload
            if ($request->hasFile('logo')) {
                $logoPath = $this->handleLogoUpload($request->file('logo'));
                $eventData['logo'] = $logoPath;
            }

            // Validate prize distribution if provided
            if (isset($eventData['prize_distribution']) && isset($eventData['prize_pool'])) {
                $totalDistribution = array_sum($eventData['prize_distribution']);
                if ($totalDistribution != 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Prize distribution must add up to 100%'
                    ], 422);
                }
            }

            $event->update($eventData);

            DB::commit();

            $event->load(['organizer:id,name,email']);

            Log::info('Event updated successfully', ['event_id' => $event->id, 'admin_id' => auth()->id()]);

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
     * Delete an event with validation
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
                ], 422);
            }

            if ($event->teams_count > 0 && $event->status !== 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete event with registered teams unless cancelled'
                ], 422);
            }

            DB::beginTransaction();

            // Remove team associations
            $event->teams()->detach();

            // Delete related matches
            $event->matches()->delete();

            // Delete brackets
            $event->brackets()->delete();

            // Delete standings
            $event->standings()->delete();

            // Delete the event
            $event->delete();

            DB::commit();

            Log::info('Event deleted successfully', ['event_id' => $id, 'admin_id' => auth()->id()]);

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
     * Update event status with validation
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:' . implode(',', array_keys(Event::STATUSES)),
                'reason' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::findOrFail($id);
            $oldStatus = $event->status;
            $newStatus = $request->status;

            // Validate status transitions
            $validTransitions = $this->getValidStatusTransitions($oldStatus);
            if (!in_array($newStatus, $validTransitions)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot transition from {$oldStatus} to {$newStatus}"
                ], 422);
            }

            DB::beginTransaction();

            $event->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);

            // Handle status-specific logic
            switch ($newStatus) {
                case 'ongoing':
                    if ($event->teams_count < 2) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot start event with less than 2 teams'
                        ], 422);
                    }
                    break;

                case 'completed':
                    // Generate final standings if not exists
                    $this->generateFinalStandings($event);
                    break;

                case 'cancelled':
                    // Handle cancellation logic
                    $this->handleEventCancellation($event);
                    break;
            }

            DB::commit();

            Log::info('Event status updated', [
                'event_id' => $event->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Event status updated to {$newStatus}",
                'data' => $event->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating event status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update event status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all teams registered for an event
     */
    public function getEventTeams($id): JsonResponse
    {
        try {
            $event = Event::findOrFail($id);

            $teams = $event->teams()
                ->withPivot(['seed', 'status', 'registered_at', 'registration_data'])
                ->orderBy('pivot_seed')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'event' => $event->only(['id', 'name', 'status', 'max_teams']),
                    'teams' => $teams,
                    'stats' => [
                        'total' => $teams->count(),
                        'registered' => $teams->where('pivot.status', 'registered')->count(),
                        'checked_in' => $teams->where('pivot.status', 'checked_in')->count(),
                        'disqualified' => $teams->where('pivot.status', 'disqualified')->count(),
                        'remaining_slots' => $event->max_teams - $teams->count()
                    ]
                ]
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
     * Add team to event
     */
    public function addTeamToEvent(Request $request, $eventId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'team_id' => 'required|exists:teams,id',
                'seed' => 'nullable|integer|min:1',
                'status' => 'nullable|string|in:registered,checked_in,disqualified',
                'registration_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::findOrFail($eventId);
            $team = Team::findOrFail($request->team_id);

            // Check if team is already registered
            if ($event->teams()->where('team_id', $team->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is already registered for this event'
                ], 422);
            }

            // Check if event has space
            if ($event->current_team_count >= $event->max_teams) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event is full'
                ], 422);
            }

            DB::beginTransaction();

            $seed = $request->seed ?? ($event->current_team_count + 1);
            $status = $request->status ?? 'registered';

            $event->teams()->attach($team->id, [
                'seed' => $seed,
                'status' => $status,
                'registered_at' => now(),
                'registration_data' => $request->registration_data ?? []
            ]);

            DB::commit();

            Log::info('Team added to event', [
                'event_id' => $event->id,
                'team_id' => $team->id,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team added to event successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding team to event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add team to event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove team from event
     */
    public function removeTeamFromEvent($eventId, $teamId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);
            $team = Team::findOrFail($teamId);

            if (!$event->teams()->where('team_id', $teamId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is not registered for this event'
                ], 422);
            }

            // Check if event has started
            if ($event->status === 'ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove team from ongoing event'
                ], 422);
            }

            DB::beginTransaction();

            $event->teams()->detach($teamId);

            // Remove team from any matches
            $event->matches()
                ->where(function($query) use ($teamId) {
                    $query->where('team1_id', $teamId)
                          ->orWhere('team2_id', $teamId);
                })
                ->delete();

            DB::commit();

            Log::info('Team removed from event', [
                'event_id' => $event->id,
                'team_id' => $teamId,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team removed from event successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error removing team from event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove team from event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update team seed in event
     */
    public function updateTeamSeed(Request $request, $eventId, $teamId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'seed' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::findOrFail($eventId);

            if (!$event->teams()->where('team_id', $teamId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is not registered for this event'
                ], 422);
            }

            $event->teams()->updateExistingPivot($teamId, [
                'seed' => $request->seed
            ]);

            Log::info('Team seed updated', [
                'event_id' => $event->id,
                'team_id' => $teamId,
                'seed' => $request->seed,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team seed updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating team seed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update team seed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate bracket for event
     */
    public function generateBracket(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|string|in:' . implode(',', array_keys(Event::FORMATS)),
                'seeding_method' => 'nullable|string|in:rating,manual,random',
                'shuffle_seeds' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::with('teams')->findOrFail($id);

            if ($event->teams->count() < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Need at least 2 teams to generate bracket'
                ], 422);
            }

            DB::beginTransaction();

            // Generate bracket using the bracket service
            if (!$this->bracketService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bracket generation service is not available'
                ], 503);
            }

            $bracketData = $this->bracketService->generateBracket(
                $event->teams->pluck('id')->toArray(),
                $request->format,
                $request->seeding_method ?? 'rating',
                $request->shuffle_seeds ?? false
            );

            $event->update([
                'format' => $request->format,
                'bracket_data' => $bracketData,
                'total_rounds' => $event->calculateTotalRounds(),
                'current_round' => 1
            ]);

            // Create matches based on bracket
            $this->createMatchesFromBracket($event, $bracketData);

            DB::commit();

            Log::info('Bracket generated for event', [
                'event_id' => $event->id,
                'format' => $request->format,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bracket generated successfully',
                'data' => [
                    'bracket_data' => $bracketData,
                    'total_rounds' => $event->total_rounds,
                    'matches_created' => $event->matches()->count()
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
                    'progress_percentage' => $event->progress_percentage,
                    'prize_pool' => $event->formatted_prize_pool,
                    'views' => $event->views
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
                    'total_views' => $event->views,
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
                            if ($event->status !== 'ongoing' && $event->teams_count === 0) {
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
     * Get comprehensive event analytics dashboard
     */
    public function getAnalyticsDashboard(): JsonResponse
    {
        try {
            $stats = [
                'overview' => $this->getEventStats(),
                'recent_events' => Event::orderBy('created_at', 'desc')->limit(5)->get(),
                'upcoming_events' => Event::upcoming()->limit(5)->get(),
                'ongoing_events' => Event::ongoing()->limit(5)->get(),
                'top_events_by_teams' => Event::withCount('teams')
                    ->orderBy('teams_count', 'desc')
                    ->limit(5)
                    ->get(),
                'events_by_region' => Event::select('region', DB::raw('count(*) as count'))
                    ->groupBy('region')
                    ->get(),
                'events_by_format' => Event::select('format', DB::raw('count(*) as count'))
                    ->groupBy('format')
                    ->get(),
                'monthly_events' => Event::select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('COUNT(*) as count')
                    )
                    ->whereYear('created_at', now()->year)
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'desc')
                    ->orderBy('month', 'desc')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching analytics dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper Methods
     */

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (Event::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function getEventStats(): array
    {
        return [
            'total' => Event::count(),
            'upcoming' => Event::upcoming()->count(),
            'ongoing' => Event::ongoing()->count(),
            'completed' => Event::completed()->count(),
            'featured' => Event::featured()->count(),
            'total_teams_registered' => DB::table('event_teams')->count(),
            'total_matches_played' => DB::table('matches')->where('status', 'completed')->count(),
            'total_prize_pool' => Event::sum('prize_pool')
        ];
    }

    private function getAvailableRegions(): array
    {
        return Event::distinct()->pluck('region')->filter()->values()->toArray();
    }

    private function getValidStatusTransitions(string $currentStatus): array
    {
        $transitions = [
            'upcoming' => ['ongoing', 'cancelled'],
            'ongoing' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => ['upcoming'] // Allow reactivation
        ];

        return $transitions[$currentStatus] ?? [];
    }

    private function generateFinalStandings(Event $event): void
    {
        // Implementation for generating final standings
        // This would depend on the tournament format and structure
    }

    private function handleEventCancellation(Event $event): void
    {
        // Handle event cancellation logic
        // Cancel all ongoing matches
        $event->matches()->where('status', 'scheduled')->update(['status' => 'cancelled']);
    }

    private function createMatchesFromBracket(Event $event, array $bracketData): void
    {
        // Implementation for creating matches from bracket data
        // This would depend on the specific bracket format structure
    }

    /**
     * Handle logo file upload for events
     */
    private function handleLogoUpload($file): string
    {
        try {
            $extension = $file->getClientOriginalExtension();
            $filename = 'event_' . time() . '_' . Str::random(10) . '.' . $extension;
            $directory = 'events/logos';
            
            // Store the file
            $path = $file->storeAs($directory, $filename, 'public');
            
            // Return the storage URL
            return '/storage/' . $path;
        } catch (\Exception $e) {
            throw new \Exception('Failed to upload logo: ' . $e->getMessage());
        }
    }
}