<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\BracketMatch;
use App\Services\BracketGenerationService;
use App\Services\TournamentProgressionService;
use Carbon\Carbon;

class TournamentPhaseController extends Controller
{
    protected $bracketService;
    protected $progressionService;

    public function __construct(
        BracketGenerationService $bracketService,
        TournamentProgressionService $progressionService
    ) {
        $this->bracketService = $bracketService;
        $this->progressionService = $progressionService;
    }

    /**
     * Display tournament phases
     */
    public function index(Tournament $tournament): JsonResponse
    {
        try {
            $phases = $tournament->phases()
                                ->withCount(['matches'])
                                ->orderBy('phase_order')
                                ->get();

            $phases->transform(function ($phase) {
                return [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'slug' => $phase->slug,
                    'phase_type' => $phase->phase_type,
                    'formatted_name' => $phase->formatted_name,
                    'phase_order' => $phase->phase_order,
                    'status' => $phase->status,
                    'is_active' => $phase->is_active,
                    'is_current_phase' => $phase->is_current_phase,
                    'description' => $phase->description,
                    'start_date' => $phase->start_date?->toISOString(),
                    'end_date' => $phase->end_date?->toISOString(),
                    'completed_at' => $phase->completed_at?->toISOString(),
                    'duration' => $phase->duration,
                    'progress_percentage' => $phase->progress_percentage,
                    'team_count' => $phase->team_count,
                    'advancement_count' => $phase->advancement_count,
                    'elimination_count' => $phase->elimination_count,
                    'match_format' => $phase->match_format,
                    'seeding_method' => $phase->seeding_method,
                    'matches_count' => $phase->matches_count,
                    'settings' => $phase->settings,
                    'map_pool' => $phase->map_pool,
                    'can_start' => $phase->canStart(),
                    'has_required_teams' => $phase->hasRequiredTeams(),
                    'is_previous_phase_completed' => $phase->isPreviousPhaseCompleted(),
                    'is_complete' => $phase->isComplete(),
                    'can_advance_to_next_phase' => $phase->canAdvanceToNextPhase()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $phases,
                'tournament' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'current_phase' => $tournament->current_phase,
                    'status' => $tournament->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament phases index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament phases',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created tournament phase
     */
    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phase_type' => 'required|in:' . implode(',', array_keys(TournamentPhase::PHASE_TYPES)),
                'phase_order' => 'nullable|integer|min:1',
                'description' => 'nullable|string',
                'start_date' => 'nullable|date|after:now',
                'end_date' => 'nullable|date|after:start_date',
                'team_count' => 'nullable|integer|min:0',
                'advancement_count' => 'nullable|integer|min:0',
                'elimination_count' => 'nullable|integer|min:0',
                'match_format' => 'nullable|in:' . implode(',', array_keys(Tournament::MATCH_FORMATS)),
                'seeding_method' => 'nullable|in:' . implode(',', array_keys(TournamentPhase::SEEDING_METHODS)),
                'settings' => 'nullable|array',
                'map_pool' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Get next phase order if not specified
            $phaseOrder = $request->phase_order ?? 
                         ($tournament->phases()->max('phase_order') ?? 0) + 1;

            $phase = new TournamentPhase();
            $phase->tournament_id = $tournament->id;
            $phase->name = $request->name;
            $phase->slug = \Str::slug($request->name);
            $phase->phase_type = $request->phase_type;
            $phase->phase_order = $phaseOrder;
            $phase->description = $request->description;
            $phase->start_date = $request->start_date;
            $phase->end_date = $request->end_date;
            $phase->team_count = $request->team_count ?? 0;
            $phase->advancement_count = $request->advancement_count ?? 0;
            $phase->elimination_count = $request->elimination_count ?? 0;
            $phase->match_format = $request->match_format ?? 'bo3';
            $phase->seeding_method = $request->seeding_method ?? 'random';
            $phase->settings = $request->settings ?? [];
            $phase->map_pool = $request->map_pool ?? [];
            
            $phase->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament phase created successfully',
                'data' => $this->formatPhaseData($phase)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament phase creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tournament phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified tournament phase
     */
    public function show(Tournament $tournament, TournamentPhase $phase): JsonResponse
    {
        try {
            // Verify phase belongs to tournament
            if ($phase->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase does not belong to this tournament'
                ], 404);
            }

            $phase->load(['matches.team1:id,name,short_name,logo', 'matches.team2:id,name,short_name,logo']);

            $data = $this->formatPhaseData($phase);
            $data['matches'] = $phase->matches->map(function ($match) {
                return [
                    'id' => $match->id,
                    'match_identifier' => $match->match_identifier,
                    'round' => $match->round,
                    'match_number' => $match->match_number,
                    'team1' => $match->team1,
                    'team2' => $match->team2,
                    'team1_score' => $match->team1_score,
                    'team2_score' => $match->team2_score,
                    'status' => $match->status,
                    'match_format' => $match->match_format,
                    'scheduled_at' => $match->scheduled_at?->toISOString(),
                    'started_at' => $match->started_at?->toISOString(),
                    'completed_at' => $match->completed_at?->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament phase show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified tournament phase
     */
    public function update(Request $request, Tournament $tournament, TournamentPhase $phase): JsonResponse
    {
        try {
            // Verify phase belongs to tournament
            if ($phase->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase does not belong to this tournament'
                ], 404);
            }

            // Check if phase can be updated
            if ($phase->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update completed phase'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'team_count' => 'nullable|integer|min:0',
                'advancement_count' => 'nullable|integer|min:0',
                'elimination_count' => 'nullable|integer|min:0',
                'match_format' => 'nullable|in:' . implode(',', array_keys(Tournament::MATCH_FORMATS)),
                'seeding_method' => 'nullable|in:' . implode(',', array_keys(TournamentPhase::SEEDING_METHODS)),
                'settings' => 'nullable|array',
                'map_pool' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $phase->fill($request->only([
                'name', 'description', 'start_date', 'end_date', 'team_count',
                'advancement_count', 'elimination_count', 'match_format',
                'seeding_method', 'settings', 'map_pool'
            ]));

            if ($request->has('name')) {
                $phase->slug = \Str::slug($request->name);
            }

            $phase->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament phase updated successfully',
                'data' => $this->formatPhaseData($phase)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament phase update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tournament phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Start a tournament phase
     */
    public function startPhase(Request $request, Tournament $tournament, TournamentPhase $phase): JsonResponse
    {
        try {
            // Verify phase belongs to tournament
            if ($phase->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase does not belong to this tournament'
                ], 404);
            }

            if (!$phase->canStart()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase cannot be started at this time',
                    'details' => [
                        'current_status' => $phase->status,
                        'has_required_teams' => $phase->hasRequiredTeams(),
                        'is_previous_phase_completed' => $phase->isPreviousPhaseCompleted()
                    ]
                ], 422);
            }

            DB::beginTransaction();

            // Generate bracket if needed
            if (!$phase->bracket_data) {
                $bracketGenerated = $phase->generateBracket();
                if (!$bracketGenerated) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to generate bracket for phase'
                    ], 500);
                }
            }

            // Seed teams if needed
            if ($phase->seeding_method !== 'manual') {
                $phase->seedTeams();
            }

            // Start the phase
            $started = $phase->startPhase();
            
            if (!$started) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to start tournament phase'
                ], 500);
            }

            // Update tournament current phase
            $tournament->current_phase = $phase->phase_type;
            $tournament->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament phase started successfully',
                'data' => $this->formatPhaseData($phase)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament phase start error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start tournament phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Complete a tournament phase
     */
    public function completePhase(Request $request, Tournament $tournament, TournamentPhase $phase): JsonResponse
    {
        try {
            // Verify phase belongs to tournament
            if ($phase->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase does not belong to this tournament'
                ], 404);
            }

            if ($phase->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active phases can be completed'
                ], 422);
            }

            // Check if all matches are completed (optional force parameter)
            if (!$request->force && !$phase->isComplete()) {
                $remainingMatches = $phase->getRemainingMatches()->count();
                return response()->json([
                    'success' => false,
                    'message' => "Phase has {$remainingMatches} remaining matches",
                    'force_required' => true
                ], 422);
            }

            DB::beginTransaction();

            // Calculate results
            $results = $phase->calculateResults();

            // Complete the phase
            $completed = $phase->completePhase($results);

            if (!$completed) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to complete tournament phase'
                ], 500);
            }

            // Progress tournament if this was the last phase
            if ($phase->canAdvanceToNextPhase()) {
                $this->progressionService->advanceToNextPhase($tournament, $phase);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament phase completed successfully',
                'data' => $this->formatPhaseData($phase),
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament phase completion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete tournament phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified tournament phase
     */
    public function destroy(Tournament $tournament, TournamentPhase $phase): JsonResponse
    {
        try {
            // Verify phase belongs to tournament
            if ($phase->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase does not belong to this tournament'
                ], 404);
            }

            // Check if phase can be deleted
            if ($phase->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete active phase'
                ], 422);
            }

            if ($phase->matches()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete phase with associated matches'
                ], 422);
            }

            DB::beginTransaction();

            $phase->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament phase deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament phase deletion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tournament phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get matches for a specific phase
     */
    public function getMatches(Tournament $tournament, TournamentPhase $phase, Request $request): JsonResponse
    {
        try {
            // Verify phase belongs to tournament
            if ($phase->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase does not belong to this tournament'
                ], 404);
            }

            $query = $phase->matches()
                          ->with(['team1:id,name,short_name,logo', 'team2:id,name,short_name,logo']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('round')) {
                $query->where('round', $request->round);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'scheduled_at');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            if (in_array($sortBy, ['scheduled_at', 'round', 'match_number', 'status'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            $perPage = min($request->get('per_page', 20), 100);
            $matches = $query->paginate($perPage);

            $matches->getCollection()->transform(function ($match) {
                return [
                    'id' => $match->id,
                    'match_identifier' => $match->match_identifier,
                    'round' => $match->round,
                    'match_number' => $match->match_number,
                    'team1' => $match->team1,
                    'team2' => $match->team2,
                    'team1_score' => $match->team1_score,
                    'team2_score' => $match->team2_score,
                    'status' => $match->status,
                    'match_format' => $match->match_format,
                    'scheduled_at' => $match->scheduled_at?->toISOString(),
                    'started_at' => $match->started_at?->toISOString(),
                    'completed_at' => $match->completed_at?->toISOString(),
                    'is_walkover' => $match->is_walkover,
                    'stream_url' => $match->stream_url,
                    'map_data' => $match->map_data
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $matches,
                'phase' => [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'phase_type' => $phase->phase_type,
                    'status' => $phase->status,
                    'progress_percentage' => $phase->progress_percentage
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament phase matches error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch phase matches',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Format phase data for API response
     */
    private function formatPhaseData(TournamentPhase $phase): array
    {
        return [
            'id' => $phase->id,
            'tournament_id' => $phase->tournament_id,
            'name' => $phase->name,
            'slug' => $phase->slug,
            'phase_type' => $phase->phase_type,
            'formatted_name' => $phase->formatted_name,
            'phase_order' => $phase->phase_order,
            'status' => $phase->status,
            'is_active' => $phase->is_active,
            'is_current_phase' => $phase->is_current_phase,
            'description' => $phase->description,
            'start_date' => $phase->start_date?->toISOString(),
            'end_date' => $phase->end_date?->toISOString(),
            'completed_at' => $phase->completed_at?->toISOString(),
            'duration' => $phase->duration,
            'progress_percentage' => $phase->progress_percentage,
            'team_count' => $phase->team_count,
            'advancement_count' => $phase->advancement_count,
            'elimination_count' => $phase->elimination_count,
            'match_format' => $phase->match_format,
            'seeding_method' => $phase->seeding_method,
            'settings' => $phase->settings,
            'map_pool' => $phase->map_pool,
            'bracket_data' => $phase->bracket_data,
            'results_data' => $phase->results_data,
            'can_start' => $phase->canStart(),
            'has_required_teams' => $phase->hasRequiredTeams(),
            'is_previous_phase_completed' => $phase->isPreviousPhaseCompleted(),
            'is_complete' => $phase->isComplete(),
            'can_advance_to_next_phase' => $phase->canAdvanceToNextPhase(),
            'created_at' => $phase->created_at->toISOString(),
            'updated_at' => $phase->updated_at->toISOString()
        ];
    }
}