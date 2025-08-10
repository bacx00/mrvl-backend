<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\TournamentRegistration;
use App\Models\TournamentBracket;
use App\Models\Team;
use App\Models\User;
use App\Services\BracketGenerationService;
use App\Services\SwissSystemService;
use App\Services\TournamentProgressionService;
use Carbon\Carbon;

class TournamentController extends Controller
{
    protected $bracketService;
    protected $swissService;
    protected $progressionService;

    public function __construct(
        BracketGenerationService $bracketService,
        SwissSystemService $swissService,
        TournamentProgressionService $progressionService
    ) {
        $this->bracketService = $bracketService;
        $this->swissService = $swissService;
        $this->progressionService = $progressionService;
    }

    /**
     * Display a listing of tournaments with advanced filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Tournament::with(['organizer:id,name,email', 'teams:id,name,short_name,logo,region'])
                              ->withCount(['teams', 'registrations']);

            // Apply filters
            if ($request->has('type') && $request->type !== 'all') {
                $query->byType($request->type);
            }

            if ($request->has('format') && $request->format !== 'all') {
                $query->byFormat($request->format);
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->byStatus($request->status);
            }

            if ($request->has('region') && $request->region !== 'all') {
                $query->byRegion($request->region);
            }

            if ($request->has('featured') && $request->featured === 'true') {
                $query->featured();
            }

            if ($request->has('public') && $request->public !== 'all') {
                $query->public($request->public === 'true');
            }

            // Date range filters
            if ($request->has('date_from')) {
                $query->where('start_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('end_date', '<=', $request->date_to);
            }

            // Prize pool range
            if ($request->has('min_prize_pool')) {
                $query->where('prize_pool', '>=', $request->min_prize_pool);
            }

            if ($request->has('max_prize_pool')) {
                $query->where('prize_pool', '<=', $request->max_prize_pool);
            }

            // Search by name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSorts = [
                'name', 'start_date', 'end_date', 'prize_pool', 'team_count',
                'views', 'created_at', 'teams_count', 'registrations_count'
            ];

            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $tournaments = $query->paginate($perPage);

            // Add computed fields
            $tournaments->getCollection()->transform(function ($tournament) {
                return $this->transformTournamentForApi($tournament);
            });

            return response()->json([
                'success' => true,
                'data' => $tournaments,
                'filters' => $this->getAvailableFilters(),
                'meta' => [
                    'total_tournaments' => Tournament::count(),
                    'ongoing_tournaments' => Tournament::ongoing()->count(),
                    'upcoming_tournaments' => Tournament::upcoming()->count(),
                    'completed_tournaments' => Tournament::completed()->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournaments',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created tournament
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|in:' . implode(',', array_keys(Tournament::TYPES)),
                'format' => 'required|in:' . implode(',', array_keys(Tournament::FORMATS)),
                'region' => 'required|string|max:100',
                'description' => 'nullable|string',
                'prize_pool' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'max_teams' => 'required|integer|min:2|max:512',
                'min_teams' => 'required|integer|min:2',
                'start_date' => 'required|date|after:now',
                'end_date' => 'required|date|after:start_date',
                'registration_start' => 'nullable|date|before:start_date',
                'registration_end' => 'nullable|date|after:registration_start|before:start_date',
                'check_in_start' => 'nullable|date|after:registration_end',
                'check_in_end' => 'nullable|date|after:check_in_start|before:start_date',
                'timezone' => 'nullable|string|max:50',
                'rules' => 'nullable|array',
                'settings' => 'nullable|array',
                'qualification_settings' => 'nullable|array',
                'map_pool' => 'nullable|array',
                'match_format_settings' => 'nullable|array',
                'stream_urls' => 'nullable|array',
                'social_links' => 'nullable|array',
                'contact_info' => 'nullable|array',
                'featured' => 'nullable|boolean',
                'public' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate min_teams <= max_teams
            if ($request->min_teams > $request->max_teams) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum teams cannot exceed maximum teams'
                ], 422);
            }

            DB::beginTransaction();

            $tournament = new Tournament();
            $tournament->fill($request->validated());
            $tournament->slug = $this->generateUniqueSlug($request->name);
            $tournament->organizer_id = Auth::id();
            
            // Set default registration dates if not provided
            if (!$request->registration_start) {
                $tournament->registration_start = now();
            }
            
            if (!$request->registration_end) {
                $tournament->registration_end = Carbon::parse($request->start_date)->subDays(1);
            }

            // Set default match format settings
            if (!$request->match_format_settings) {
                $tournament->match_format_settings = [
                    'default' => 'bo3',
                    'qualifiers' => 'bo1',
                    'playoffs' => 'bo3',
                    'semifinals' => 'bo5',
                    'finals' => 'bo7'
                ];
            }

            $tournament->save();

            // Create initial phases based on tournament format
            $this->createInitialPhases($tournament);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament created successfully',
                'data' => $this->transformTournamentForApi($tournament->load(['organizer', 'phases']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified tournament
     */
    public function show(Tournament $tournament): JsonResponse
    {
        try {
            $tournament->load([
                'organizer:id,name,email',
                'phases' => function($query) {
                    $query->ordered()->with(['matches:id,tournament_phase_id,team1_id,team2_id,status,team1_score,team2_score']);
                },
                'teams:id,name,short_name,logo,region',
                'registrations' => function($query) {
                    $query->with(['team:id,name,short_name,logo', 'user:id,name,email']);
                },
                'brackets' => function($query) {
                    $query->ordered()->with(['matches:id,tournament_bracket_id,team1_id,team2_id,status,round,match_number']);
                }
            ])
            ->withCount(['teams', 'registrations']);

            $tournament->incrementViews();

            $tournamentData = $this->transformTournamentForApi($tournament);
            
            // Add detailed statistics
            $tournamentData['statistics'] = [
                'total_matches' => $tournament->matches()->count(),
                'completed_matches' => $tournament->matches()->where('status', 'completed')->count(),
                'ongoing_matches' => $tournament->matches()->where('status', 'ongoing')->count(),
                'pending_registrations' => $tournament->registrations()->where('status', 'pending')->count(),
                'checked_in_teams' => $tournament->teams()->wherePivot('status', 'checked_in')->count(),
                'prize_distribution' => $tournament->prize_distribution ?? [],
                'recent_activity' => $this->getRecentTournamentActivity($tournament)
            ];

            // Add bracket visualization data
            if ($tournament->brackets->isNotEmpty()) {
                $tournamentData['bracket_visualization'] = $this->getBracketVisualizationData($tournament);
            }

            return response()->json([
                'success' => true,
                'data' => $tournamentData
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified tournament
     */
    public function update(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            // Check if user can edit this tournament
            if (!$this->canEditTournament($tournament)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to edit this tournament'
                ], 403);
            }

            // Validate that tournament can be edited
            if (!$tournament->canEdit()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament cannot be edited in its current state'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'type' => 'sometimes|in:' . implode(',', array_keys(Tournament::TYPES)),
                'format' => 'sometimes|in:' . implode(',', array_keys(Tournament::FORMATS)),
                'region' => 'sometimes|string|max:100',
                'description' => 'nullable|string',
                'prize_pool' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'max_teams' => 'sometimes|integer|min:2|max:512',
                'min_teams' => 'sometimes|integer|min:2',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date',
                'registration_start' => 'nullable|date',
                'registration_end' => 'nullable|date',
                'check_in_start' => 'nullable|date',
                'check_in_end' => 'nullable|date',
                'timezone' => 'nullable|string|max:50',
                'rules' => 'nullable|array',
                'settings' => 'nullable|array',
                'qualification_settings' => 'nullable|array',
                'map_pool' => 'nullable|array',
                'match_format_settings' => 'nullable|array',
                'stream_urls' => 'nullable|array',
                'social_links' => 'nullable|array',
                'contact_info' => 'nullable|array',
                'featured' => 'nullable|boolean',
                'public' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $tournament->fill($request->validated());
            
            // Update slug if name changed
            if ($request->has('name') && $request->name !== $tournament->getOriginal('name')) {
                $tournament->slug = $this->generateUniqueSlug($request->name, $tournament->id);
            }

            $tournament->save();

            // Update phases if format changed
            if ($request->has('format') && $request->format !== $tournament->getOriginal('format')) {
                $this->updateTournamentPhases($tournament);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament updated successfully',
                'data' => $this->transformTournamentForApi($tournament->load(['organizer', 'phases']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified tournament
     */
    public function destroy(Tournament $tournament): JsonResponse
    {
        try {
            // Check permissions
            if (!$this->canDeleteTournament($tournament)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this tournament'
                ], 403);
            }

            // Validate that tournament can be deleted
            if (!$tournament->canDelete()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament cannot be deleted - it has registered teams or has already started'
                ], 422);
            }

            DB::beginTransaction();

            $tournamentName = $tournament->name;
            $tournament->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Tournament '{$tournamentName}' deleted successfully"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament deletion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Register a team for the tournament
     */
    public function registerTeam(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'team_id' => 'required|exists:teams,id',
                'registration_data' => 'nullable|array',
                'emergency_contact' => 'nullable|array',
                'special_requirements' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $registration = TournamentRegistration::createRegistration(
                $tournament->id,
                $request->team_id,
                Auth::id(),
                $request->registration_data ?? []
            );

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to register team - registration may be closed or team already registered'
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team registered successfully',
                'data' => [
                    'registration' => $registration->load(['team', 'user']),
                    'tournament_info' => $registration->getRegistrationSummary()
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Team registration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to register team',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check in a team for the tournament
     */
    public function checkInTeam(Request $request, Tournament $tournament): JsonResponse
    {
        try {
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

            $registration = TournamentRegistration::where('tournament_id', $tournament->id)
                                                  ->where('team_id', $request->team_id)
                                                  ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is not registered for this tournament'
                ], 404);
            }

            if (!$registration->checkIn()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team cannot check in - check-in may not be open or team already checked in'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Team checked in successfully',
                'data' => $registration->getRegistrationSummary()
            ]);

        } catch (\Exception $e) {
            Log::error('Team check-in error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check in team',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Start the tournament
     */
    public function startTournament(Tournament $tournament): JsonResponse
    {
        try {
            // Check permissions
            if (!$this->canManageTournament($tournament)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to start this tournament'
                ], 403);
            }

            if (!$tournament->startTournament()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament cannot be started - insufficient teams or already started'
                ], 422);
            }

            // Generate initial brackets
            $this->bracketService->generateTournamentBrackets($tournament);

            return response()->json([
                'success' => true,
                'message' => 'Tournament started successfully',
                'data' => $this->transformTournamentForApi($tournament->load(['phases', 'brackets']))
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament start error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generate tournament bracket
     */
    public function generateBracket(Tournament $tournament): JsonResponse
    {
        try {
            if (!$this->canManageTournament($tournament)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to generate brackets for this tournament'
                ], 403);
            }

            $brackets = $this->bracketService->generateTournamentBrackets($tournament);

            if (!$brackets) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate bracket - insufficient teams or invalid format'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tournament bracket generated successfully',
                'data' => [
                    'tournament' => $this->transformTournamentForApi($tournament->load(['brackets', 'phases'])),
                    'brackets' => $brackets->load(['matches'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bracket generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate bracket',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament standings
     */
    public function standings(Tournament $tournament): JsonResponse
    {
        try {
            $standings = [];
            
            switch ($tournament->format) {
                case 'swiss':
                    $standings = $tournament->swiss_standings->map(function($team) {
                        return [
                            'team' => $team->only(['id', 'name', 'short_name', 'logo', 'region']),
                            'wins' => $team->pivot->swiss_wins,
                            'losses' => $team->pivot->swiss_losses,
                            'score' => $team->pivot->swiss_score,
                            'buchholz' => $team->pivot->swiss_buchholz,
                            'seed' => $team->pivot->seed,
                            'status' => $team->pivot->status
                        ];
                    });
                    break;

                case 'round_robin':
                    $standings = $this->calculateRoundRobinStandings($tournament);
                    break;

                case 'group_stage_playoffs':
                    $standings = $this->calculateGroupStageStandings($tournament);
                    break;

                default:
                    $standings = $this->calculateEliminationStandings($tournament);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'tournament_id' => $tournament->id,
                    'tournament_name' => $tournament->name,
                    'format' => $tournament->format,
                    'current_phase' => $tournament->current_phase,
                    'standings' => $standings,
                    'last_updated' => now()->toDateTimeString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament standings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament standings',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament bracket data for visualization
     */
    public function bracket(Tournament $tournament): JsonResponse
    {
        try {
            $brackets = $tournament->brackets()
                                  ->with([
                                      'matches' => function($query) {
                                          $query->with(['team1:id,name,short_name,logo', 'team2:id,name,short_name,logo'])
                                                ->orderBy('round')
                                                ->orderBy('match_number');
                                      }
                                  ])
                                  ->ordered()
                                  ->get();

            $bracketData = [];
            
            foreach ($brackets as $bracket) {
                $bracketData[] = [
                    'id' => $bracket->id,
                    'name' => $bracket->name,
                    'type' => $bracket->bracket_type,
                    'format' => $bracket->bracket_format,
                    'status' => $bracket->status,
                    'round_count' => $bracket->round_count,
                    'current_round' => $bracket->current_round,
                    'progress_percentage' => $bracket->progress_percentage,
                    'matches' => $bracket->matches->map(function($match) {
                        return [
                            'id' => $match->id,
                            'identifier' => $match->match_identifier,
                            'round' => $match->round,
                            'match_number' => $match->match_number,
                            'status' => $match->status,
                            'team1' => $match->team1,
                            'team2' => $match->team2,
                            'team1_score' => $match->team1_score,
                            'team2_score' => $match->team2_score,
                            'scheduled_at' => $match->scheduled_at,
                            'is_walkover' => $match->is_walkover
                        ];
                    }),
                    'structure' => $bracket->bracket_data
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'tournament_id' => $tournament->id,
                    'tournament_name' => $tournament->name,
                    'format' => $tournament->format,
                    'current_phase' => $tournament->current_phase,
                    'brackets' => $bracketData,
                    'visualization_config' => $this->getBracketVisualizationConfig($tournament)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament bracket error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament bracket',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Cancel tournament
     */
    public function cancelTournament(Tournament $tournament): JsonResponse
    {
        try {
            if ($tournament->hasEnded()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel completed tournament'
                ], 422);
            }

            DB::beginTransaction();

            $tournament->status = 'cancelled';
            $tournament->save();

            // Cancel all pending matches
            BracketMatch::where('tournament_id', $tournament->id)
                       ->where('status', 'pending')
                       ->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament cancelled successfully',
                'data' => $this->transformTournamentForApi($tournament)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament cancellation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Disqualify team from tournament
     */
    public function disqualifyTeam(Request $request, Tournament $tournament, Team $team): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
                'forfeit_matches' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify team is in tournament
            $teamInTournament = $tournament->teams()->where('teams.id', $team->id)->exists();
            if (!$teamInTournament) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is not part of this tournament'
                ], 404);
            }

            DB::beginTransaction();

            // Update team status in tournament
            $tournament->teams()->updateExistingPivot($team->id, [
                'status' => 'disqualified'
            ]);

            // Handle active matches
            if ($request->forfeit_matches ?? true) {
                $activeMatches = BracketMatch::where('tournament_id', $tournament->id)
                                           ->whereIn('status', ['pending', 'ongoing'])
                                           ->where(function($query) use ($team) {
                                               $query->where('team1_id', $team->id)
                                                     ->orWhere('team2_id', $team->id);
                                           })
                                           ->get();

                foreach ($activeMatches as $match) {
                    // Award walkover to opponent
                    $winnerTeamId = $match->team1_id === $team->id ? $match->team2_id : $match->team1_id;
                    
                    $match->team1_score = $winnerTeamId === $match->team1_id ? 1 : 0;
                    $match->team2_score = $winnerTeamId === $match->team2_id ? 1 : 0;
                    $match->status = 'completed';
                    $match->completed_at = now();
                    $match->is_walkover = true;
                    $match->walkover_reason = "Team disqualified: " . $request->reason;
                    $match->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team disqualified successfully',
                'data' => [
                    'team_id' => $team->id,
                    'reason' => $request->reason,
                    'forfeited_matches' => $activeMatches ?? []
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Team disqualification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to disqualify team',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Reinstate disqualified team
     */
    public function reinstateTeam(Request $request, Tournament $tournament, Team $team): JsonResponse
    {
        try {
            // Verify team is disqualified in tournament
            $teamPivot = $tournament->teams()->where('teams.id', $team->id)->first();
            if (!$teamPivot || $teamPivot->pivot->status !== 'disqualified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is not disqualified in this tournament'
                ], 404);
            }

            DB::beginTransaction();

            // Update team status
            $tournament->teams()->updateExistingPivot($team->id, [
                'status' => 'checked_in'
            ]);

            // Note: Forfeited matches remain as is (walkovers stand)

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team reinstated successfully',
                'data' => [
                    'team_id' => $team->id,
                    'new_status' => 'checked_in'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Team reinstatement error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reinstate team',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update team seeding in tournament
     */
    public function updateTeamSeed(Request $request, Tournament $tournament, Team $team): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'seed' => 'required|integer|min:1|max:' . $tournament->max_teams
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify team is in tournament
            $teamInTournament = $tournament->teams()->where('teams.id', $team->id)->exists();
            if (!$teamInTournament) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is not part of this tournament'
                ], 404);
            }

            // Check if seed is already taken
            $seedTaken = $tournament->teams()
                                   ->where('teams.id', '!=', $team->id)
                                   ->wherePivot('seed', $request->seed)
                                   ->exists();

            if ($seedTaken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seed number is already taken by another team'
                ], 422);
            }

            DB::beginTransaction();

            $tournament->teams()->updateExistingPivot($team->id, [
                'seed' => $request->seed
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team seed updated successfully',
                'data' => [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'new_seed' => $request->seed
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Team seed update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update team seed',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Follow tournament
     */
    public function followTournament(Tournament $tournament): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if already following
            if ($user->followedTournaments()->where('tournament_id', $tournament->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already following this tournament'
                ], 422);
            }

            $user->followedTournaments()->attach($tournament->id, [
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tournament followed successfully',
                'data' => [
                    'tournament_id' => $tournament->id,
                    'following' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament follow error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to follow tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Unfollow tournament
     */
    public function unfollowTournament(Tournament $tournament): JsonResponse
    {
        try {
            $user = Auth::user();

            $user->followedTournaments()->detach($tournament->id);

            return response()->json([
                'success' => true,
                'message' => 'Tournament unfollowed successfully',
                'data' => [
                    'tournament_id' => $tournament->id,
                    'following' => false
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament unfollow error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to unfollow tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get followed tournaments for user
     */
    public function getFollowedTournaments(): JsonResponse
    {
        try {
            $user = Auth::user();

            $tournaments = $user->followedTournaments()
                               ->withPivot('created_at')
                               ->orderBy('pivot_created_at', 'desc')
                               ->get();

            $tournaments->transform(function ($tournament) {
                return $this->transformTournamentForApi($tournament);
            });

            return response()->json([
                'success' => true,
                'data' => $tournaments
            ]);

        } catch (\Exception $e) {
            Log::error('Followed tournaments error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get followed tournaments',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Helper Methods

    private function generateUniqueSlug(string $name, int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (Tournament::where('slug', $slug)
                         ->when($excludeId, fn($query) => $query->where('id', '!=', $excludeId))
                         ->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    private function createInitialPhases(Tournament $tournament): void
    {
        $phases = [];

        switch ($tournament->format) {
            case 'swiss':
                $phases = [
                    ['name' => 'Swiss Rounds', 'phase_type' => 'swiss_rounds', 'phase_order' => 1],
                    ['name' => 'Playoffs', 'phase_type' => 'playoffs', 'phase_order' => 2]
                ];
                break;

            case 'double_elimination':
                $phases = [
                    ['name' => 'Upper Bracket', 'phase_type' => 'upper_bracket', 'phase_order' => 1],
                    ['name' => 'Lower Bracket', 'phase_type' => 'lower_bracket', 'phase_order' => 2],
                    ['name' => 'Grand Final', 'phase_type' => 'grand_final', 'phase_order' => 3]
                ];
                break;

            case 'group_stage_playoffs':
                $phases = [
                    ['name' => 'Group Stage', 'phase_type' => 'group_stage', 'phase_order' => 1],
                    ['name' => 'Playoffs', 'phase_type' => 'playoffs', 'phase_order' => 2]
                ];
                break;

            default: // single_elimination
                $phases = [
                    ['name' => 'Elimination Bracket', 'phase_type' => 'playoffs', 'phase_order' => 1]
                ];
        }

        foreach ($phases as $phaseData) {
            TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => $phaseData['name'],
                'slug' => Str::slug($phaseData['name']),
                'phase_type' => $phaseData['phase_type'],
                'phase_order' => $phaseData['phase_order'],
                'match_format' => $tournament->match_format_settings['default'] ?? 'bo3'
            ]);
        }
    }

    private function updateTournamentPhases(Tournament $tournament): void
    {
        // Delete existing phases that don't have matches
        $tournament->phases()
                  ->whereDoesntHave('matches')
                  ->delete();

        // Create new phases for the updated format
        $this->createInitialPhases($tournament);
    }

    private function transformTournamentForApi(Tournament $tournament): array
    {
        $data = $tournament->toArray();
        
        // Add computed fields
        $data['formatted_prize_pool'] = $tournament->formatted_prize_pool;
        $data['registration_open'] = $tournament->registration_open;
        $data['check_in_open'] = $tournament->check_in_open;
        $data['current_team_count'] = $tournament->current_team_count;
        $data['checked_in_teams_count'] = $tournament->checked_in_teams_count;
        $data['progress_percentage'] = $tournament->getProgressPercentage();
        $data['duration_in_days'] = $tournament->getDurationInDays();
        $data['time_until_start'] = $tournament->getTimeUntilStart();
        $data['time_until_end'] = $tournament->getTimeUntilEnd();
        $data['is_live'] = $tournament->isLive();
        $data['has_started'] = $tournament->hasStarted();
        $data['has_ended'] = $tournament->hasEnded();
        $data['can_edit'] = $tournament->canEdit();
        $data['can_delete'] = $tournament->canDelete();

        return $data;
    }

    private function getAvailableFilters(): array
    {
        return [
            'types' => Tournament::TYPES,
            'formats' => Tournament::FORMATS,
            'statuses' => Tournament::STATUSES,
            'regions' => Tournament::select('region')
                                  ->distinct()
                                  ->whereNotNull('region')
                                  ->orderBy('region')
                                  ->pluck('region')
        ];
    }

    private function getRecentTournamentActivity(Tournament $tournament): array
    {
        $activities = [];

        // Recent registrations
        $recentRegistrations = $tournament->registrations()
                                         ->with(['team:id,name', 'user:id,name'])
                                         ->orderBy('created_at', 'desc')
                                         ->limit(5)
                                         ->get();

        foreach ($recentRegistrations as $registration) {
            $activities[] = [
                'type' => 'registration',
                'message' => "{$registration->team->name} registered for the tournament",
                'timestamp' => $registration->created_at,
                'data' => ['team' => $registration->team, 'user' => $registration->user]
            ];
        }

        // Recent match completions
        $recentMatches = $tournament->matches()
                                   ->with(['team1:id,name', 'team2:id,name'])
                                   ->where('status', 'completed')
                                   ->orderBy('updated_at', 'desc')
                                   ->limit(5)
                                   ->get();

        foreach ($recentMatches as $match) {
            $activities[] = [
                'type' => 'match_completed',
                'message' => "Match completed: {$match->team1->name} vs {$match->team2->name}",
                'timestamp' => $match->updated_at,
                'data' => ['match' => $match]
            ];
        }

        // Sort by timestamp
        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($activities, 0, 10);
    }

    private function getBracketVisualizationData(Tournament $tournament): array
    {
        // This would return data structure suitable for bracket visualization
        // Implementation depends on your frontend bracket visualization library
        return [
            'type' => $tournament->format,
            'rounds' => [],
            'connections' => [],
            'teams' => $tournament->teams->map(fn($team) => $team->only(['id', 'name', 'logo'])),
            'config' => $this->getBracketVisualizationConfig($tournament)
        ];
    }

    private function getBracketVisualizationConfig(Tournament $tournament): array
    {
        return [
            'show_seeds' => true,
            'show_scores' => true,
            'show_logos' => true,
            'theme' => 'dark',
            'match_format' => $tournament->match_format_settings['default'] ?? 'bo3'
        ];
    }

    private function calculateRoundRobinStandings(Tournament $tournament): array
    {
        // Implementation for round robin standings calculation
        return [];
    }

    private function calculateGroupStageStandings(Tournament $tournament): array
    {
        // Implementation for group stage standings calculation
        return [];
    }

    private function calculateEliminationStandings(Tournament $tournament): array
    {
        // Implementation for elimination bracket standings
        return [];
    }

    private function canEditTournament(Tournament $tournament): bool
    {
        return Auth::id() === $tournament->organizer_id || 
               Auth::user()->hasRole(['admin', 'tournament_admin']);
    }

    private function canDeleteTournament(Tournament $tournament): bool
    {
        return Auth::id() === $tournament->organizer_id || 
               Auth::user()->hasRole(['admin']);
    }

    private function canManageTournament(Tournament $tournament): bool
    {
        return Auth::id() === $tournament->organizer_id || 
               Auth::user()->hasRole(['admin', 'tournament_admin', 'moderator']);
    }
}