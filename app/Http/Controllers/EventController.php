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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Helpers\ImageHelper;

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
                    'logo' => ImageHelper::getEventImage($event->logo, $event->name, 'logo'),
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

    public function show($slugOrId)
    {
        try {
            // Get event by slug or ID
            $query = DB::table('events as e')
                ->leftJoin('users as u', 'e.organizer_id', '=', 'u.id')
                ->select([
                    'e.*',
                    'u.name as organizer_name',
                    'u.avatar as organizer_avatar'
                ]);
                
            // Check if the parameter is numeric (ID) or string (slug)
            if (is_numeric($slugOrId)) {
                $query->where('e.id', $slugOrId);
            } else {
                $query->where('e.slug', $slugOrId);
            }
            
            $event = $query->first();

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
        if (!($user->hasRole('admin') || $user->hasRole('super_admin')) && !$user->hasPermissionTo('manage-events')) {
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
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
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

            // Handle logo upload if provided
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $this->handleEventLogoUpload($request->file('logo'), null);
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
                'logo' => $logoPath,
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
                    'status' => $event->status,
                    'logo' => $event->logo
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
                    'logo' => ImageHelper::getTeamLogo($team->logo, $team->name),
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
        
        if (empty($matches)) {
            return [];
        }

        // Get event details for bracket type determination
        $event = DB::table('events')->where('id', $eventId)->first();
        if (!$event) {
            return [];
        }

        // Group matches by rounds for bracket visualization with Liquipedia structure
        $roundsData = [];
        $teamCount = $this->getEventTeamsPrivate($eventId)->count();

        foreach ($matches as $match) {
            $roundName = $this->getRoundName($match->round, $teamCount);
            
            if (!isset($roundsData[$match->round])) {
                $roundsData[$match->round] = [
                    'name' => $roundName,
                    'matches' => []
                ];
            }

            $roundsData[$match->round]['matches'][] = [
                'id' => $match->id,
                'position' => $match->bracket_position,
                'team1' => [
                    'id' => $match->team1_id,
                    'name' => $match->team1_name ?? null,
                    'short_name' => $match->team1_short ?? null,
                    'logo' => $match->team1_logo ?? null,
                    'score' => $match->team1_score ?? null,
                    'seed' => $this->getTeamSeed($eventId, $match->team1_id)
                ],
                'team2' => [
                    'id' => $match->team2_id,
                    'name' => $match->team2_name ?? null,
                    'short_name' => $match->team2_short ?? null,
                    'logo' => $match->team2_logo ?? null,
                    'score' => $match->team2_score ?? null,
                    'seed' => $this->getTeamSeed($eventId, $match->team2_id)
                ],
                'status' => $match->status ?? 'pending',
                'scheduled_at' => $match->scheduled_at ?? null,
                'stream_url' => $match->stream_url ?? null,
                'format' => $match->format ?? 'bo3',
                'winner_id' => $this->getMatchWinner($match),
                'finished' => ($match->status ?? 'pending') === 'completed'
            ];
        }

        // Sort rounds and convert to indexed array format expected by LiquipediaBracket
        ksort($roundsData);
        $rounds = array_values($roundsData);

        return [
            'type' => $event->format ?? 'single_elimination',
            'rounds' => $rounds,
            'metadata' => [
                'total_rounds' => count($rounds),
                'teams_count' => $teamCount,
                'completed_matches' => collect($matches)->where('status', 'completed')->count()
            ]
        ];
    }

    private function getRoundName($round, $teamCount)
    {
        if ($teamCount <= 1) {
            return "Round $round";
        }

        $totalRounds = ceil(log($teamCount, 2));
        $roundsFromEnd = $totalRounds - $round + 1;
        
        switch ($roundsFromEnd) {
            case 1:
                return 'Final';
            case 2:
                return 'Semifinals';
            case 3:
                return 'Quarterfinals';
            case 4:
                return 'Round of 16';
            case 5:
                return 'Round of 32';
            default:
                return "Round $round";
        }
    }

    private function getTeamSeed($eventId, $teamId)
    {
        if (!$teamId) return null;
        
        return DB::table('event_teams')
            ->where('event_id', $eventId)
            ->where('team_id', $teamId)
            ->value('seed');
    }

    private function getMatchWinner($match)
    {
        if (($match->status ?? 'pending') !== 'completed') {
            return null;
        }
        
        $score1 = $match->team1_score ?? 0;
        $score2 = $match->team2_score ?? 0;
        
        if ($score1 > $score2) {
            return $match->team1_id;
        } elseif ($score2 > $score1) {
            return $match->team2_id;
        }
        
        return null; // Draw
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

    /**
     * Handle event logo upload
     */
    private function handleEventLogoUpload($file, $eventId)
    {
        try {
            // Simple file storage without complex processing
            $extension = $file->getClientOriginalExtension();
            $filename = 'event_' . ($eventId ?? 'new') . '_' . time() . '_' . Str::random(10) . '.' . $extension;
            $directory = 'events/logos';
            
            // Ensure directory exists
            $fullDirectory = storage_path('app/public/' . $directory);
            if (!is_dir($fullDirectory)) {
                mkdir($fullDirectory, 0775, true);
            }
            
            $finalPath = $directory . '/' . $filename;
            $destinationPath = storage_path('app/public/' . $finalPath);
            
            // Move uploaded file
            if (!move_uploaded_file($file->path(), $destinationPath)) {
                throw new \Exception('Failed to move uploaded file');
            }
            
            // Set proper permissions
            chmod($destinationPath, 0644);
            
            // Return storage path
            return '/storage/' . $finalPath;
        } catch (\Exception $e) {
            throw new \Exception('Failed to upload logo: ' . $e->getMessage());
        }
    }

    // Admin Routes
    public function getAllEvents(Request $request)
    {
        // Check if user is authenticated and has admin role
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }
        
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

            // Process events data to include proper logo formatting
            $eventsData = collect($events->items())->map(function($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'logo' => $event->logo, // Keep original logo path
                    'type' => $event->type,
                    'tier' => $event->tier,
                    'format' => $event->format,
                    'region' => $event->region,
                    'game_mode' => $event->game_mode,
                    'status' => $event->status,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'max_teams' => $event->max_teams,
                    'prize_pool' => $event->prize_pool,
                    'currency' => $event->currency,
                    'featured' => (bool)$event->featured,
                    'public' => (bool)$event->public,
                    'organizer_name' => $event->organizer_name,
                    'created_at' => $event->created_at,
                    'updated_at' => $event->updated_at
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
                'message' => 'Error fetching admin events: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEventAdmin($eventId)
    {
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
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
        if (!($user->hasRole('admin') || $user->hasRole('super_admin')) && !$user->hasPermissionTo('manage-events')) {
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
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
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
            
            // Handle logo upload if provided
            if ($request->hasFile('logo')) {
                $logoPath = $this->handleEventLogoUpload($request->file('logo'), $eventId);
                $updateData['logo'] = $logoPath;
            }
            
            // Only update fields that are present in the request
            $fields = [
                'name', 'description', 'type', 'format', 'region', 'game_mode',
                'start_date', 'end_date', 'registration_start', 'registration_end',
                'max_teams', 'prize_pool', 'currency', 'rules',
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
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        try {
            // Check if force delete is requested via query parameter
            $forceDelete = request()->query('force', false);
            
            if ($forceDelete) {
                // Force delete - delete all related data first
                DB::table('event_teams')->where('event_id', $eventId)->delete();
                DB::table('matches')->where('event_id', $eventId)->delete();
                
                // Delete bracket-related data (check if tables exist)
                $tableNames = ['bracket_games', 'bracket_matches', 'bracket_positions', 'bracket_seedings', 'bracket_stages', 'bracket_standings', 'tournament_brackets'];
                foreach ($tableNames as $tableName) {
                    try {
                        if (Schema::hasTable($tableName)) {
                            DB::table($tableName)->where('event_id', $eventId)->delete();
                        }
                    } catch (\Exception $e) {
                        // Table might not have event_id column, skip
                        continue;
                    }
                }
                
                // Delete event
                DB::table('events')->where('id', $eventId)->delete();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Event force deleted successfully'
                ]);
            }
            
            // Check if event has matches (for regular delete)
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
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        try {
            // Delete all related data
            DB::table('event_teams')->where('event_id', $eventId)->delete();
            DB::table('matches')->where('event_id', $eventId)->delete();
            
            // Delete bracket-related data (check if tables exist)
            $tableNames = ['bracket_games', 'bracket_matches', 'bracket_positions', 'bracket_seedings', 'bracket_stages', 'bracket_standings', 'tournament_brackets'];
            foreach ($tableNames as $tableName) {
                try {
                    if (Schema::hasTable($tableName)) {
                        DB::table($tableName)->where('event_id', $eventId)->delete();
                    }
                } catch (\Exception $e) {
                    // Table might not have event_id column, skip
                    continue;
                }
            }
            
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
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
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
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
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
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
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
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
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
        // Check if user is admin or moderator
        $user = auth('api')->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
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

    public function generateBracket(Request $request, $eventId)
    {
        try {
            // Get event
            $event = DB::table('events')->where('id', $eventId)->first();
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Get participating teams
            $teams = DB::table('event_teams as et')
                ->join('teams as t', 'et.team_id', '=', 't.id')
                ->where('et.event_id', $eventId)
                ->select([
                    't.id', 't.name', 't.short_name', 't.logo', 
                    't.rating', 'et.seed'
                ])
                ->orderBy('et.seed')
                ->get()
                ->toArray();

            if (count($teams) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Need at least 2 teams to generate bracket'
                ], 400);
            }

            // Clear existing matches for this event
            DB::table('matches')->where('event_id', $eventId)->delete();

            // Generate bracket based on event format
            $format = $event->format ?? 'single_elimination';
            $matches = $this->createBracketMatches($eventId, $teams, $format);

            // Insert matches
            if (!empty($matches)) {
                DB::table('matches')->insert($matches);
            }

            // Update event status
            DB::table('events')->where('id', $eventId)->update([
                'status' => 'ongoing',
                'current_round' => 1,
                'total_rounds' => $this->calculateTotalRounds(count($teams), $format),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bracket generated successfully',
                'data' => [
                    'matches_created' => count($matches),
                    'format' => $format,
                    'teams_count' => count($teams),
                    'total_rounds' => $this->calculateTotalRounds(count($teams), $format)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    private function createBracketMatches($eventId, $teams, $format)
    {
        switch ($format) {
            case 'single_elimination':
                return $this->createSingleEliminationMatches($eventId, $teams);
            case 'double_elimination':
                return $this->createDoubleEliminationMatches($eventId, $teams);
            case 'round_robin':
                return $this->createRoundRobinMatches($eventId, $teams);
            case 'swiss':
                return $this->createSwissMatches($eventId, $teams);
            default:
                return $this->createSingleEliminationMatches($eventId, $teams);
        }
    }

    private function createSingleEliminationMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
        $totalRounds = $this->calculateTotalRounds($teamCount, 'single_elimination');
        
        // Seed teams properly
        $seededTeams = $this->seedTeamsForElimination($teams);
        
        // First round matches
        $round = 1;
        $position = 1;
        
        for ($i = 0; $i < $teamCount; $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'main',
                    'team1_id' => $seededTeams[$i]->id,
                    'team2_id' => $seededTeams[$i + 1]->id,
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            } else {
                // Odd team gets a bye
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'main',
                    'team1_id' => $seededTeams[$i]->id,
                    'team2_id' => null,
                    'status' => 'completed',
                    'format' => 'bo3',
                    'team1_score' => 1,
                    'team2_score' => 0,
                    'completed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }
        
        // Create placeholder matches for subsequent rounds
        $currentMatches = ceil($teamCount / 2);
        for ($r = 2; $r <= $totalRounds; $r++) {
            $matchesInRound = ceil($currentMatches / 2);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'main',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => 'bo3',
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $currentMatches = $matchesInRound;
        }
        
        return $matches;
    }

    private function seedTeamsForElimination($teams)
    {
        // If teams already have seeds, sort by seed
        if (!empty($teams) && isset($teams[0]->seed) && $teams[0]->seed) {
            usort($teams, function($a, $b) {
                return ($a->seed ?? 999) <=> ($b->seed ?? 999);
            });
            return $teams;
        }
        
        // Otherwise, use standard tournament seeding based on rating
        usort($teams, function($a, $b) {
            return ($b->rating ?? 1000) <=> ($a->rating ?? 1000);
        });
        
        // Apply tournament seeding (1 vs lowest, 2 vs second-lowest, etc.)
        $seeded = [];
        $count = count($teams);
        
        // Standard tournament bracket seeding
        for ($i = 0; $i < $count; $i++) {
            if ($i % 2 === 0) {
                $seeded[] = $teams[$i / 2];
            } else {
                $seeded[] = $teams[$count - 1 - floor($i / 2)];
            }
        }
        
        return $seeded;
    }

    private function calculateTotalRounds($teamCount, $format)
    {
        if ($teamCount <= 1) {
            return 0;
        }
        
        switch ($format) {
            case 'single_elimination':
                return ceil(log($teamCount, 2));
            case 'double_elimination':
                return ceil(log($teamCount, 2)) * 2 - 1;
            case 'round_robin':
                return max(0, $teamCount - 1);
            case 'swiss':
                return ceil(log($teamCount, 2));
            default:
                return ceil(log($teamCount, 2));
        }
    }

    // Placeholder methods for other formats - can be expanded later
    private function createDoubleEliminationMatches($eventId, $teams)
    {
        // For now, fall back to single elimination
        return $this->createSingleEliminationMatches($eventId, $teams);
    }

    private function createRoundRobinMatches($eventId, $teams)
    {
        $matches = [];
        $teamCount = count($teams);
        $round = 1;
        $position = 1;
        
        // Every team plays every other team once
        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'round_robin',
                    'team1_id' => $teams[$i]->id,
                    'team2_id' => $teams[$j]->id,
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
                
                // Distribute matches across rounds (simple distribution)
                if ($position > ceil($teamCount / 2)) {
                    $round++;
                    $position = 1;
                }
            }
        }
        
        return $matches;
    }

    private function createSwissMatches($eventId, $teams)
    {
        // For now, create first round only
        $matches = [];
        $teamCount = count($teams);
        $seededTeams = $this->seedTeamsForElimination($teams);
        
        // First round: pair top half vs bottom half
        $round = 1;
        $position = 1;
        
        for ($i = 0; $i < $teamCount; $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'swiss',
                    'team1_id' => $seededTeams[$i]->id,
                    'team2_id' => $seededTeams[$i + 1]->id,
                    'status' => 'scheduled',
                    'format' => 'bo3',
                    'team1_score' => 0,
                    'team2_score' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }
        
        return $matches;
    }

    public function updateMatchScore(Request $request, $eventId, $matchId)
    {
        $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled'
        ]);

        try {
            // Check if match belongs to the event
            $match = DB::table('matches')
                ->where('id', $matchId)
                ->where('event_id', $eventId)
                ->first();

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Update match scores and status
            $updateData = [
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'updated_at' => now()
            ];

            // Auto-set status to completed if scores are provided
            if ($request->team1_score > 0 || $request->team2_score > 0) {
                $updateData['status'] = 'completed';
                $updateData['completed_at'] = now();
                
                // Process match completion and advance winners
                $this->processMatchCompletion($matchId, $request->team1_score, $request->team2_score);
            }

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            DB::table('matches')->where('id', $matchId)->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Match score updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating match score: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processMatchCompletion($matchId, $team1Score, $team2Score)
    {
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) return;

            $winnerId = $team1Score > $team2Score ? $match->team1_id : 
                       ($team2Score > $team1Score ? $match->team2_id : null);
            
            if (!$winnerId) return; // Draw case

            // Advance winner to next round for elimination formats
            if (in_array($match->bracket_type, ['main', 'upper', 'lower'])) {
                $this->advanceWinnerToNextRound($match, $winnerId);
            }

            // Update event standings
            $this->updateEventStandings($match->event_id);

        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log("Error processing match completion: " . $e->getMessage());
        }
    }

    private function advanceWinnerToNextRound($match, $winnerId)
    {
        try {
            // Find next match in bracket
            $nextRound = $match->round + 1;
            $nextPosition = ceil($match->bracket_position / 2);
            
            $nextMatch = DB::table('matches')
                ->where('event_id', $match->event_id)
                ->where('round', $nextRound)
                ->where('bracket_position', $nextPosition)
                ->where('bracket_type', $match->bracket_type)
                ->first();
                
            if ($nextMatch) {
                // Determine if winner goes to team1 or team2 slot
                $teamSlot = ($match->bracket_position % 2 === 1) ? 'team1_id' : 'team2_id';
                
                $updateData = [
                    $teamSlot => $winnerId,
                    'updated_at' => now()
                ];

                // If both teams are now assigned, mark as scheduled
                if ($teamSlot === 'team1_id' && $nextMatch->team2_id) {
                    $updateData['status'] = 'scheduled';
                } elseif ($teamSlot === 'team2_id' && $nextMatch->team1_id) {
                    $updateData['status'] = 'scheduled';
                }
                
                DB::table('matches')->where('id', $nextMatch->id)->update($updateData);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log("Error advancing winner: " . $e->getMessage());
        }
    }

    private function updateEventStandings($eventId)
    {
        try {
            // Basic standings update - can be enhanced later
            $event = DB::table('events')->where('id', $eventId)->first();
            if (!$event) return;

            // For now, just update the event's updated_at timestamp
            DB::table('events')->where('id', $eventId)->update(['updated_at' => now()]);

        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log("Error updating event standings: " . $e->getMessage());
        }
    }
}