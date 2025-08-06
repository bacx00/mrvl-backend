<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\Match;
use App\Models\Team;
use App\Models\Bracket;
use App\Models\EventStanding;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('events as e')
                ->leftJoin('users as u', 'e.organizer_id', '=', 'u.id')
                ->select([
                    'e.*',
                    'u.name as organizer_name',
                    'u.avatar as organizer_avatar'
                ]);

            // Filter by status
            if ($request->status && $request->status !== 'all') {
                $query->where('e.status', $request->status);
            }

            // Filter by type
            if ($request->type && $request->type !== 'all') {
                $query->where('e.type', $request->type);
            }

            // Filter by region
            if ($request->region && $request->region !== 'all') {
                $query->where('e.region', $request->region);
            }

            // Search functionality
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('e.name', 'LIKE', "%{$request->search}%")
                      ->orWhere('e.description', 'LIKE', "%{$request->search}%");
                });
            }

            // Sort options
            $sortBy = $request->get('sort', 'upcoming');
            switch ($sortBy) {
                case 'prize_pool':
                    $query->orderBy('e.prize_pool', 'desc');
                    break;
                case 'participants':
                    $query->orderBy('e.max_teams', 'desc');
                    break;
                case 'recent':
                    $query->orderBy('e.end_date', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('e.start_date', 'asc');
                    break;
                default: // upcoming
                    $query->where('e.start_date', '>=', now())
                          ->orderBy('e.start_date', 'asc');
            }

            $events = $query->paginate(12);

            // Add additional data for each event with VLR.gg-style formatting
            $eventsData = collect($events->items())->map(function($event) {
                $organizer = $this->getUserWithFlairs($event->organizer_id);
                $teams = $this->getEventTeamsPrivate($event->id);
                
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'logo' => $event->logo,
                    'organizer' => $organizer,
                    'details' => [
                        'type' => $event->type,
                        'format' => $event->format,
                        'region' => $event->region,
                        'game_mode' => $event->game_mode,
                        'prize_pool' => $event->prize_pool,
                        'currency' => $event->currency ?? 'USD'
                    ],
                    'schedule' => [
                        'start_date' => $event->start_date,
                        'end_date' => $event->end_date,
                        'registration_start' => $event->registration_start,
                        'registration_end' => $event->registration_end,
                        'timezone' => $event->timezone ?? 'UTC'
                    ],
                    'participation' => [
                        'max_teams' => $event->max_teams,
                        'current_teams' => count($teams),
                        'registration_open' => $this->isRegistrationOpen($event),
                        'teams' => $teams
                    ],
                    'status' => $event->status,
                    'meta' => [
                        'featured' => (bool)$event->featured,
                        'public' => (bool)$event->public,
                        'created_at' => $event->created_at,
                        'updated_at' => $event->updated_at
                    ],
                    'stats' => [
                        'views' => $event->views ?? 0,
                        'matches_count' => $this->getEventMatchCount($event->id),
                        'completed_matches' => $this->getEventCompletedMatchCount($event->id)
                    ]
                ];
            });

            return response()->json([
                'data' => $eventsData,
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching events: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($slug)
    {
        try {
            // Get event by slug
            $event = DB::table('events as e')
                ->leftJoin('users as u', 'e.organizer_id', '=', 'u.id')
                ->where('e.slug', $slug)
                ->select([
                    'e.*',
                    'u.name as organizer_name',
                    'u.avatar as organizer_avatar'
                ])
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Get all teams participating in this event
            $teams = $this->getEventTeamsDetailed($event->id);

            // Get all matches for this event
            $matches = $this->getEventMatches($event->id);

            // Get bracket structure if applicable
            $bracket = $this->getEventBracket($event->id);

            // Get event standings/rankings
            $standings = $this->getEventStandings($event->id);

            // Increment view count
            DB::table('events')->where('id', $event->id)->increment('views');

            $eventData = [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'description' => $event->description,
                'logo' => $event->logo,
                'organizer' => $this->getUserWithFlairs($event->organizer_id),
                'details' => [
                    'type' => $event->type,
                    'format' => $event->format,
                    'region' => $event->region,
                    'game_mode' => $event->game_mode,
                    'prize_pool' => $event->prize_pool,
                    'currency' => $event->currency ?? 'USD',
                    'prize_distribution' => $event->prize_distribution ? json_decode($event->prize_distribution, true) : null,
                    'rules' => $event->rules
                ],
                'schedule' => [
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'registration_start' => $event->registration_start,
                    'registration_end' => $event->registration_end,
                    'timezone' => $event->timezone ?? 'UTC'
                ],
                'participation' => [
                    'max_teams' => $event->max_teams,
                    'current_teams' => count($teams),
                    'registration_open' => $this->isRegistrationOpen($event),
                    'registration_requirements' => $event->registration_requirements ? json_decode($event->registration_requirements, true) : null
                ],
                'status' => $event->status,
                'meta' => [
                    'featured' => (bool)$event->featured,
                    'public' => (bool)$event->public,
                    'created_at' => $event->created_at,
                    'updated_at' => $event->updated_at
                ],
                'stats' => [
                    'views' => $event->views ?? 0,
                    'matches_count' => count($matches),
                    'completed_matches' => collect($matches)->where('status', 'completed')->count()
                ],
                'teams' => $teams,
                'matches' => $matches,
                'bracket' => $bracket,
                'standings' => $standings,
                'streams' => $event->streams ? json_decode($event->streams, true) : [],
                'social_links' => $event->social_links ? json_decode($event->social_links, true) : []
            ];

            return response()->json([
                'data' => $eventData,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Check if user is authenticated and has admin role
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please provide a valid Bearer token.'
            ], 401);
        }
        
        // Check if user has admin role
        if (!$user->hasRole(['admin', 'super_admin']) && !$user->hasPermissionTo('manage-events')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create events. Admin role required.'
            ], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'type' => 'required|in:championship,tournament,scrim,qualifier,regional,international,invitational,community,friendly,practice,exhibition',
            'tier' => 'nullable|in:S,A,B,C',
            'format' => 'required|in:single_elimination,double_elimination,round_robin,swiss,group_stage,bo1,bo3,bo5',
            'region' => 'required|string|max:50',
            'game_mode' => 'required|string|max:50',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'registration_start' => 'nullable|date|before:start_date',
            'registration_end' => 'nullable|date|before:start_date',
            'max_teams' => 'required|integer|min:2|max:256',
            'prize_pool' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'prize_distribution' => 'nullable|array',
            'logo' => 'nullable|string',
            'rules' => 'nullable|string',
            'registration_requirements' => 'nullable|array',
            'streams' => 'nullable|array',
            'social_links' => 'nullable|array',
            'timezone' => 'nullable|string|max:50',
            'featured' => 'boolean',
            'public' => 'boolean'
        ]);

        try {
            $slug = Str::slug($request->name);
            
            // Ensure unique slug
            $counter = 1;
            $originalSlug = $slug;
            while (DB::table('events')->where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $event = Event::create([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'type' => $request->type,
                'tier' => $request->tier ?? 'B',
                'format' => $request->format,
                'region' => $request->region,
                'game_mode' => $request->game_mode,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'registration_start' => $request->registration_start,
                'registration_end' => $request->registration_end,
                'max_teams' => $request->max_teams,
                'prize_pool' => $request->prize_pool,
                'currency' => $request->currency ?? 'USD',
                'prize_distribution' => $request->prize_distribution,
                'logo' => $request->logo,
                'rules' => $request->rules,
                'registration_requirements' => $request->registration_requirements,
                'streams' => $request->streams,
                'social_links' => $request->social_links,
                'timezone' => $request->timezone ?? 'UTC',
                'organizer_id' => Auth::id(),
                'status' => 'upcoming',
                'featured' => $request->featured ?? false,
                'public' => $request->public ?? true
            ]);

            return response()->json([
                'data' => [
                    'id' => $event->id,
                    'slug' => $event->slug,
                    'name' => $event->name,
                    'status' => $event->status
                ],
                'success' => true,
                'message' => 'Event created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function registerTeam(Request $request, $eventId)
    {
        $this->authorize('register-team');
        
        $request->validate([
            'team_id' => 'required|exists:teams,id'
        ]);

        try {
            $event = DB::table('events')->where('id', $eventId)->first();
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }

            // Check if registration is open
            if (!$this->isRegistrationOpen($event)) {
                return response()->json(['success' => false, 'message' => 'Registration is closed'], 400);
            }

            // Check if team is already registered
            $existingRegistration = DB::table('event_teams')
                ->where('event_id', $eventId)
                ->where('team_id', $request->team_id)
                ->first();

            if ($existingRegistration) {
                return response()->json(['success' => false, 'message' => 'Team already registered'], 400);
            }

            // Check if event is full
            $currentTeamCount = DB::table('event_teams')->where('event_id', $eventId)->count();
            if ($currentTeamCount >= $event->max_teams) {
                return response()->json(['success' => false, 'message' => 'Event is full'], 400);
            }

            // Register team
            DB::table('event_teams')->insert([
                'event_id' => $eventId,
                'team_id' => $request->team_id,
                'registered_at' => now(),
                'status' => 'confirmed',
                'seed' => $currentTeamCount + 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team registered successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error registering team: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for VLR.gg-style event management

    private function getEventTeamsPrivate($eventId)
    {
        return DB::table('event_teams as et')
            ->leftJoin('teams as t', 'et.team_id', '=', 't.id')
            ->where('et.event_id', $eventId)
            ->select(['t.id', 't.name', 't.short_name', 't.logo', 't.region', 'et.seed', 'et.status'])
            ->orderBy('et.seed')
            ->get()
            ->toArray();
    }

    private function getEventTeamsDetailed($eventId)
    {
        return DB::table('event_teams as et')
            ->leftJoin('teams as t', 'et.team_id', '=', 't.id')
            ->where('et.event_id', $eventId)
            ->select([
                't.id', 't.name', 't.short_name', 't.logo', 't.region', 't.rating',
                'et.seed', 'et.status', 'et.registered_at'
            ])
            ->orderBy('et.seed')
            ->get()
            ->map(function($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $team->logo,
                    'region' => $team->region,
                    'rating' => $team->rating,
                    'seed' => $team->seed,
                    'status' => $team->status,
                    'registered_at' => $team->registered_at,
                    'roster' => $this->getTeamRoster($team->id)
                ];
            })
            ->toArray();
    }

    private function getTeamRoster($teamId)
    {
        return DB::table('players')
            ->where('team_id', $teamId)
            ->where('status', 'active')
            ->select(['id', 'username', 'real_name', 'role', 'avatar', 'main_hero'])
            ->get()
            ->toArray();
    }

    private function getEventMatches($eventId)
    {
        return DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->select([
                'm.id', 'm.round', 'm.bracket_position', 'm.status', 'm.format',
                'm.team1_score', 'm.team2_score', 'm.scheduled_at', 'm.completed_at',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                'm.maps_data', 'm.stream_url'
            ])
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get()
            ->toArray();
    }

    private function getEventBracket($eventId)
    {
        $matches = $this->getEventMatches($eventId);
        
        // Group matches by rounds for bracket visualization
        $bracket = [];
        foreach ($matches as $match) {
            $bracket[$match->round][] = [
                'id' => $match->id,
                'position' => $match->bracket_position,
                'team1' => [
                    'name' => isset($match->team1_name) ? $match->team1_name : null,
                    'short_name' => isset($match->team1_short) ? $match->team1_short : null,
                    'logo' => isset($match->team1_logo) ? $match->team1_logo : null,
                    'score' => isset($match->team1_score) ? $match->team1_score : null
                ],
                'team2' => [
                    'name' => isset($match->team2_name) ? $match->team2_name : null,
                    'short_name' => isset($match->team2_short) ? $match->team2_short : null,
                    'logo' => isset($match->team2_logo) ? $match->team2_logo : null,
                    'score' => isset($match->team2_score) ? $match->team2_score : null
                ],
                'status' => isset($match->status) ? $match->status : 'pending',
                'scheduled_at' => isset($match->scheduled_at) ? $match->scheduled_at : null,
                'stream_url' => isset($match->stream_url) ? $match->stream_url : null
            ];
        }
        
        return $bracket;
    }

    public function updateBySlug(Request $request, $slug)
    {
        try {
            $event = Event::where('slug', $slug)->firstOrFail();
            $this->authorize('update', $event);

            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|min:20',
                'type' => 'sometimes|in:championship,tournament,scrim,qualifier,regional,international,invitational,community,friendly,practice,exhibition',
                'tier' => 'sometimes|in:S,A,B,C',
                'format' => 'sometimes|in:single_elimination,double_elimination,round_robin,swiss,group_stage,bo1,bo3,bo5',
                'region' => 'sometimes|string|max:50',
                'game_mode' => 'sometimes|string|max:50',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date',
                'registration_start' => 'nullable|date',
                'registration_end' => 'nullable|date',
                'max_teams' => 'sometimes|integer|min:2|max:256',
                'prize_pool' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'prize_distribution' => 'nullable|array',
                'rules' => 'nullable|string',
                'registration_requirements' => 'nullable|array',
                'streams' => 'nullable|array',
                'social_links' => 'nullable|array',
                'timezone' => 'nullable|string|max:50',
                'featured' => 'sometimes|boolean',
                'public' => 'sometimes|boolean',
                'status' => 'sometimes|in:upcoming,ongoing,completed,cancelled'
            ]);

            // Update slug if name changed
            if (isset($validatedData['name']) && $validatedData['name'] !== $event->name) {
                $newSlug = Str::slug($validatedData['name']);
                $counter = 1;
                $originalSlug = $newSlug;
                while (Event::where('slug', $newSlug)->where('id', '!=', $event->id)->exists()) {
                    $newSlug = $originalSlug . '-' . $counter;
                    $counter++;
                }
                $validatedData['slug'] = $newSlug;
            }

            $event->update($validatedData);

            return response()->json([
                'data' => $event->fresh(),
                'success' => true,
                'message' => 'Event updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyBySlug($slug)
    {
        try {
            $event = Event::where('slug', $slug)->firstOrFail();
            $this->authorize('delete', $event);

            // Check if event can be deleted (no ongoing matches)
            if ($event->status === 'ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete ongoing event'
                ], 400);
            }

            // Delete related data
            $event->matches()->delete();
            $event->brackets()->delete();
            $event->standings()->delete();
            $event->teams()->detach();
            
            // Delete event images if they exist
            if ($event->logo) {
                $logoPath = str_replace(url('storage/'), '', $event->logo);
                Storage::disk('public')->delete($logoPath);
            }

            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addTeamToEventByRequest(Request $request, $eventId)
    {
        $request->validate([
            'team_id' => 'required|exists:teams,id',
            'seed' => 'nullable|integer|min:1'
        ]);

        try {
            $event = Event::findOrFail($eventId);
            $this->authorize('update', $event);

            // Check if event can accept more teams
            if (!$event->canRegisterTeam()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event registration is closed or full'
                ], 400);
            }

            // Check if team is already registered
            if ($event->teams()->where('team_id', $request->team_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is already registered for this event'
                ], 400);
            }

            // Add team to event
            $event->teams()->attach($request->team_id, [
                'seed' => $request->seed,
                'status' => 'confirmed',
                'registered_at' => now(),
                'registration_data' => $request->only(['notes', 'contact_info'])
            ]);

            // Initialize standing for the team
            EventStanding::create([
                'event_id' => $event->id,
                'team_id' => $request->team_id,
                'position' => $event->teams()->count(),
                'wins' => 0,
                'losses' => 0,
                'maps_won' => 0,
                'maps_lost' => 0,
                'status' => 'active'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team added to event successfully',
                'data' => [
                    'team_count' => $event->teams()->count(),
                    'max_teams' => $event->max_teams
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding team: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeTeamFromEventByRequest($eventId, $teamId)
    {
        try {
            $event = Event::findOrFail($eventId);
            $this->authorize('update', $event);

            // Check if event has started
            if ($event->status === 'ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove team from ongoing event'
                ], 400);
            }

            // Remove team from event
            $event->teams()->detach($teamId);
            
            // Remove standing
            EventStanding::where('event_id', $eventId)
                         ->where('team_id', $teamId)
                         ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Team removed from event successfully',
                'data' => [
                    'team_count' => $event->teams()->count(),
                    'max_teams' => $event->max_teams
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin method to add teams manually to events
     */
    public function adminAddTeamToEvent(Request $request, $eventId)
    {
        $request->validate([
            'team_id' => 'required|exists:teams,id',
            'seed' => 'nullable|integer|min:1'
        ]);

        try {
            // Check if event exists
            $event = DB::table('events')->where('id', $eventId)->first();
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Check if team is already registered
            $existingRegistration = DB::table('event_teams')
                ->where('event_id', $eventId)
                ->where('team_id', $request->team_id)
                ->exists();

            if ($existingRegistration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is already registered for this event'
                ], 400);
            }

            // Get current team count
            $currentTeamCount = DB::table('event_teams')
                ->where('event_id', $eventId)
                ->count();

            // Add team to event
            DB::table('event_teams')->insert([
                'event_id' => $eventId,
                'team_id' => $request->team_id,
                'seed' => $request->seed ?? ($currentTeamCount + 1),
                'status' => 'confirmed',
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create initial standing
            DB::table('event_standings')->insert([
                'event_id' => $eventId,
                'team_id' => $request->team_id,
                'position' => $currentTeamCount + 1,
                'wins' => 0,
                'losses' => 0,
                'maps_won' => 0,
                'maps_lost' => 0,
                'map_differential' => 0,
                'points' => 0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team added to event successfully',
                'data' => [
                    'team_count' => $currentTeamCount + 1,
                    'max_teams' => $event->max_teams
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin method to remove teams from events
     */
    public function adminRemoveTeamFromEvent($eventId, $teamId)
    {
        try {
            // Check if event exists
            $event = DB::table('events')->where('id', $eventId)->first();
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Remove team from event
            DB::table('event_teams')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->delete();
            
            // Remove standing
            DB::table('event_standings')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->delete();

            // Get updated team count
            $currentTeamCount = DB::table('event_teams')
                ->where('event_id', $eventId)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Team removed from event successfully',
                'data' => [
                    'team_count' => $currentTeamCount,
                    'max_teams' => $event->max_teams
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing team: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEventStandingsAPI($eventId)
    {
        try {
            $event = Event::findOrFail($eventId);
            
            $standings = EventStanding::where('event_id', $eventId)
                                    ->with(['team' => function($query) {
                                        $query->select('id', 'name', 'short_name', 'logo', 'rating');
                                    }])
                                    ->orderByDesc('wins')
                                    ->orderByDesc('maps_won')
                                    ->orderBy('maps_lost')
                                    ->get()
                                    ->map(function($standing, $index) {
                                        // Check if team exists before processing
                                        if (!$standing->team) {
                                            return null;
                                        }
                                        
                                        $standing->position = $index + 1;
                                        $standing->save();
                                        return [
                                            'position' => $standing->position,
                                            'team' => $standing->team,
                                            'wins' => $standing->wins,
                                            'losses' => $standing->losses,
                                            'maps_won' => $standing->maps_won,
                                            'maps_lost' => $standing->maps_lost,
                                            'map_differential' => $standing->map_differential,
                                            'win_rate' => $standing->win_rate,
                                            'status' => $standing->status,
                                            'prize_won' => $standing->formatted_prize
                                        ];
                                    })
                                    ->filter();

            return response()->json([
                'data' => $standings,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching standings: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getEventStandings($eventId)
    {
        return EventStanding::where('event_id', $eventId)
            ->with(['team' => function($query) {
                $query->select('id', 'name', 'short_name', 'logo');
            }])
            ->orderBy('position')
            ->get()
            ->map(function($standing) {
                // Check if team exists before accessing its properties
                if (!$standing->team) {
                    return null;
                }
                
                return [
                    'id' => $standing->team->id,
                    'name' => $standing->team->name,
                    'short_name' => $standing->team->short_name,
                    'logo' => $standing->team->logo,
                    'position' => $standing->position,
                    'wins' => $standing->wins,
                    'losses' => $standing->losses,
                    'maps_won' => $standing->maps_won,
                    'maps_lost' => $standing->maps_lost,
                    'prize_won' => $standing->prize_won
                ];
            })
            ->filter() // Remove null entries
            ->toArray();
    }

    private function getEventMatchCount($eventId)
    {
        return DB::table('matches')->where('event_id', $eventId)->count();
    }

    private function getEventCompletedMatchCount($eventId)
    {
        return DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->count();
    }

    private function isRegistrationOpen($event)
    {
        $now = now();
        
        if ($event->registration_start && $now < $event->registration_start) {
            return false;
        }
        
        if ($event->registration_end && $now > $event->registration_end) {
            return false;
        }
        
        if ($now > $event->start_date) {
            return false;
        }
        
        return $event->status === 'upcoming';
    }

    // Helper method to get user with flairs (VLR.gg style) - reused from other controllers
    // Temporary direct event creation method (bypasses authorization)
    public function createEventDirect(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string|min:20',
                'type' => 'required|in:championship,tournament,scrim,qualifier,regional,international,invitational,community,friendly,practice,exhibition',
                'tier' => 'nullable|in:S,A,B,C',
                'format' => 'required|in:single_elimination,double_elimination,round_robin,swiss,group_stage,bo1,bo3,bo5',
                'region' => 'required|string|max:50',
                'game_mode' => 'required|string|max:50',
                'start_date' => 'required|date|after:now',
                'end_date' => 'required|date|after:start_date',
                'max_teams' => 'required|integer|min:2|max:256',
                'prize_pool' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'featured' => 'boolean',
                'public' => 'boolean'
            ]);

            $eventData = $request->only([
                'name', 'description', 'type', 'tier', 'format', 'region', 'game_mode',
                'start_date', 'end_date', 'registration_start', 'registration_end',
                'max_teams', 'prize_pool', 'currency', 'featured', 'public'
            ]);

            $eventData['slug'] = Str::slug($request->name);
            $eventData['organizer_id'] = Auth::id();
            $eventData['status'] = 'upcoming';
            $eventData['created_at'] = now();
            $eventData['updated_at'] = now();

            $eventId = DB::table('events')->insertGetId($eventData);

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => [
                    'id' => $eventId,
                    'name' => $eventData['name'],
                    'slug' => $eventData['slug']
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create event: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getUserWithFlairs($userId)
    {
        $user = DB::table('users as u')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('u.id', $userId)
            ->select([
                'u.id', 'u.name', 'u.avatar', 'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ])
            ->first();

        if (!$user) {
            return null;
        }

        $flairs = [];
        
        // Add hero flair if enabled
        if ($user->show_hero_flair && $user->hero_flair) {
            $flairs['hero'] = [
                'type' => 'hero',
                'name' => $user->hero_flair,
                'image' => "/images/heroes/" . str_replace([' ', '&'], ['-', 'and'], strtolower($user->hero_flair)) . ".png",
                'fallback_text' => $user->hero_flair
            ];
        }
        
        // Add team flair if enabled - check all properties exist
        if ($user->show_team_flair && isset($user->team_name) && $user->team_name) {
            $flairs['team'] = [
                'type' => 'team',
                'name' => $user->team_name,
                'short_name' => isset($user->team_short) ? $user->team_short : '',
                'image' => isset($user->team_logo) ? $user->team_logo : null,
                'fallback_text' => isset($user->team_short) ? $user->team_short : ''
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => isset($user->avatar) ? $user->avatar : null,
            'flairs' => $flairs
        ];
    }

    public function getEventTypes()
    {
        return response()->json([
            'data' => [
                'championship',
                'tournament',
                'scrim',
                'qualifier',
                'regional',
                'international',
                'invitational',
                'community',
                'friendly',
                'practice',
                'exhibition'
            ],
            'success' => true
        ]);
    }

    public function getEventFormats()
    {
        return response()->json([
            'data' => [
                'single_elimination',
                'double_elimination',
                'round_robin',
                'swiss',
                'group_stage',
                'bo1',
                'bo3',
                'bo5'
            ],
            'success' => true
        ]);
    }

    // Admin Routes
    public function getAllEvents(Request $request)
    {
        $this->authorize('manage-events');
        
        try {
            $query = DB::table('events as e')
                ->leftJoin('users as u', 'e.organizer_id', '=', 'u.id')
                ->select([
                    'e.*',
                    'u.name as organizer_name'
                ]);

            // Filters
            if ($request->status && $request->status !== 'all') {
                $query->where('e.status', $request->status);
            }

            if ($request->type && $request->type !== 'all') {
                $query->where('e.type', $request->type);
            }

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('e.name', 'LIKE', "%{$request->search}%")
                      ->orWhere('e.description', 'LIKE', "%{$request->search}%");
                });
            }

            $events = $query->orderBy('e.created_at', 'desc')->paginate(20);

            return response()->json([
                'data' => $events->items(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching admin events: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEventAdmin($eventId)
    {
        $this->authorize('manage-events');
        
        try {
            $event = DB::table('events')->where('id', $eventId)->first();
            
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Return complete event data for admin editing
            return response()->json([
                'data' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'type' => $event->type,
                    'format' => $event->format,
                    'region' => $event->region,
                    'game_mode' => $event->game_mode,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'registration_start' => $event->registration_start,
                    'registration_end' => $event->registration_end,
                    'max_teams' => $event->max_teams,
                    'prize_pool' => $event->prize_pool,
                    'currency' => $event->currency,
                    'prize_distribution' => $event->prize_distribution ? json_decode($event->prize_distribution, true) : null,
                    'logo' => $event->logo,
                    'rules' => $event->rules,
                    'registration_requirements' => $event->registration_requirements ? json_decode($event->registration_requirements, true) : null,
                    'streams' => $event->streams ? json_decode($event->streams, true) : null,
                    'social_links' => $event->social_links ? json_decode($event->social_links, true) : null,
                    'timezone' => $event->timezone,
                    'status' => $event->status,
                    'featured' => (bool)$event->featured,
                    'public' => (bool)$event->public,
                    'organizer_id' => $event->organizer_id
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $eventId)
    {
        // Check if user is authenticated and has admin role
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please provide a valid Bearer token.'
            ], 401);
        }
        
        // Check if user has admin role
        if (!$user->hasRole(['admin', 'super_admin']) && !$user->hasPermissionTo('manage-events')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update events. Admin role required.'
            ], 403);
        }
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|min:10',
            'type' => 'sometimes|in:championship,tournament,scrim,qualifier,regional,international,invitational,community,friendly,practice,exhibition',
            'format' => 'sometimes|in:single_elimination,double_elimination,round_robin,swiss,group_stage,bo1,bo3,bo5',
            'region' => 'sometimes|string|max:50',
            'game_mode' => 'sometimes|string|max:50',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'registration_start' => 'nullable|date',
            'registration_end' => 'nullable|date',
            'max_teams' => 'sometimes|integer|min:2|max:256',
            'prize_pool' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'prize_distribution' => 'nullable|array',
            'logo' => 'nullable|url',
            'rules' => 'nullable|string',
            'registration_requirements' => 'nullable|array',
            'streams' => 'nullable|array',
            'social_links' => 'nullable|array',
            'timezone' => 'nullable|string|max:50',
            'status' => 'sometimes|in:upcoming,ongoing,completed,cancelled',
            'featured' => 'boolean',
            'public' => 'boolean'
        ]);

        try {
            $updateData = [];
            
            // Only update fields that are present in the request
            $fields = [
                'name', 'description', 'type', 'format', 'region', 'game_mode',
                'start_date', 'end_date', 'registration_start', 'registration_end',
                'max_teams', 'prize_pool', 'currency', 'logo', 'rules',
                'timezone', 'status', 'featured', 'public'
            ];
            
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }
            
            // Handle JSON fields
            if ($request->has('prize_distribution')) {
                $updateData['prize_distribution'] = json_encode($request->prize_distribution);
            }
            if ($request->has('registration_requirements')) {
                $updateData['registration_requirements'] = json_encode($request->registration_requirements);
            }
            if ($request->has('streams')) {
                $updateData['streams'] = json_encode($request->streams);
            }
            if ($request->has('social_links')) {
                $updateData['social_links'] = json_encode($request->social_links);
            }
            
            // Update slug if name changed
            if ($request->has('name')) {
                $slug = Str::slug($request->name);
                $existingSlug = DB::table('events')
                    ->where('slug', $slug)
                    ->where('id', '!=', $eventId)
                    ->exists();
                    
                if (!$existingSlug) {
                    $updateData['slug'] = $slug;
                }
            }
            
            $updateData['updated_at'] = now();
            
            DB::table('events')
                ->where('id', $eventId)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($eventId)
    {
        $this->authorize('manage-events');
        
        try {
            // Check if event has matches
            $hasMatches = DB::table('matches')->where('event_id', $eventId)->exists();
            
            if ($hasMatches) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete event with matches. Use force delete instead.'
                ], 400);
            }
            
            // Delete event teams first
            DB::table('event_teams')->where('event_id', $eventId)->delete();
            
            // Delete event
            DB::table('events')->where('id', $eventId)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forceDestroy($eventId)
    {
        $this->authorize('manage-events');
        
        try {
            // Delete all related data
            DB::table('event_teams')->where('event_id', $eventId)->delete();
            DB::table('matches')->where('event_id', $eventId)->delete();
            DB::table('brackets')->where('event_id', $eventId)->delete();
            
            // Delete event
            DB::table('events')->where('id', $eventId)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Event and all related data deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error force deleting event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEventStatus(Request $request, $eventId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'status' => 'required|in:upcoming,ongoing,completed,cancelled'
        ]);
        
        try {
            DB::table('events')
                ->where('id', $eventId)
                ->update([
                    'status' => $request->status,
                    'updated_at' => now()
                ]);
                
            return response()->json([
                'success' => true,
                'message' => 'Event status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating event status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEventTeams($eventId)
    {
        // This method is called from the admin interface
        try {
            $teams = DB::table('event_teams as et')
                ->leftJoin('teams as t', 'et.team_id', '=', 't.id')
                ->where('et.event_id', $eventId)
                ->select([
                    'et.id as registration_id',
                    't.id', 't.name', 't.short_name', 't.logo', 't.region',
                    'et.seed', 'et.status', 'et.registered_at', 'et.placement',
                    'et.prize_money', 'et.points'
                ])
                ->orderBy('et.seed')
                ->get();
                
            return response()->json([
                'data' => $teams,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching event teams: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEventTeamsAdmin($eventId)
    {
        $this->authorize('manage-events');
        
        try {
            $teams = DB::table('event_teams as et')
                ->leftJoin('teams as t', 'et.team_id', '=', 't.id')
                ->where('et.event_id', $eventId)
                ->select([
                    'et.id as registration_id',
                    't.id', 't.name', 't.short_name', 't.logo', 't.region',
                    'et.seed', 'et.status', 'et.registered_at', 'et.placement',
                    'et.prize_money', 'et.points'
                ])
                ->orderBy('et.seed')
                ->get();
                
            return response()->json([
                'data' => $teams,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching event teams: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addTeamToEvent($eventId, $teamId)
    {
        $this->authorize('manage-events');
        
        try {
            // Check if team already registered
            $exists = DB::table('event_teams')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team already registered for this event'
                ], 400);
            }
            
            // Get next seed number
            $maxSeed = DB::table('event_teams')
                ->where('event_id', $eventId)
                ->max('seed') ?? 0;
            
            DB::table('event_teams')->insert([
                'event_id' => $eventId,
                'team_id' => $teamId,
                'seed' => $maxSeed + 1,
                'status' => 'confirmed',
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Team added to event successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding team to event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeTeamFromEvent($eventId, $teamId)
    {
        $this->authorize('manage-events');
        
        try {
            DB::table('event_teams')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->delete();
                
            return response()->json([
                'success' => true,
                'message' => 'Team removed from event successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing team from event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateTeamSeed(Request $request, $eventId, $teamId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'seed' => 'required|integer|min:1'
        ]);
        
        try {
            DB::table('event_teams')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->update([
                    'seed' => $request->seed,
                    'updated_at' => now()
                ]);
                
            return response()->json([
                'success' => true,
                'message' => 'Team seed updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating team seed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveTeamRegistration($eventId, $teamId)
    {
        $this->authorize('moderate-events');
        
        try {
            DB::table('event_teams')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->update([
                    'status' => 'confirmed',
                    'updated_at' => now()
                ]);
                
            return response()->json([
                'success' => true,
                'message' => 'Team registration approved'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving team registration: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectTeamRegistration($eventId, $teamId)
    {
        $this->authorize('moderate-events');
        
        try {
            DB::table('event_teams')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => now()
                ]);
                
            return response()->json([
                'success' => true,
                'message' => 'Team registration rejected'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting team registration: ' . $e->getMessage()
            ], 500);
        }
    }
}