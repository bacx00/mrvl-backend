<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\BracketMatch;
use App\Services\TournamentProgressionService;
use App\Services\BracketGenerationService;
use App\Services\SwissSystemService;
use Carbon\Carbon;

class TournamentProgressionController extends Controller
{
    protected $progressionService;
    protected $bracketService;
    protected $swissService;

    public function __construct(
        TournamentProgressionService $progressionService,
        BracketGenerationService $bracketService,
        SwissSystemService $swissService
    ) {
        $this->progressionService = $progressionService;
        $this->bracketService = $bracketService;
        $this->swissService = $swissService;
    }

    /**
     * Get tournament progression status
     */
    public function getProgressionStatus(Tournament $tournament): JsonResponse
    {
        try {
            $status = $this->progressionService->getTournamentProgressionStatus($tournament);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament progression status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tournament progression status',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Advance tournament to next phase
     */
    public function advanceToNextPhase(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            if ($tournament->status !== 'ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament must be ongoing to advance phases'
                ], 422);
            }

            $currentPhase = $tournament->phases()
                                      ->where('is_active', true)
                                      ->where('status', 'active')
                                      ->first();

            if (!$currentPhase) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active phase found to advance from'
                ], 422);
            }

            if (!$currentPhase->canAdvanceToNextPhase()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current phase cannot be advanced yet',
                    'details' => [
                        'phase_complete' => $currentPhase->isComplete(),
                        'advancement_count' => $currentPhase->advancement_count,
                        'advancing_teams_count' => $currentPhase->getAdvancingTeams()->count()
                    ]
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'force' => 'nullable|boolean',
                'skip_validation' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $result = $this->progressionService->advanceToNextPhase(
                $tournament, 
                $currentPhase, 
                $request->force ?? false,
                $request->skip_validation ?? false
            );

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'details' => $result['details'] ?? null
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament advanced to next phase successfully',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament phase advancement error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to advance tournament phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Complete tournament
     */
    public function completeTournament(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            if ($tournament->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is already completed'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'force' => 'nullable|boolean',
                'final_results' => 'nullable|array',
                'prize_distribution' => 'nullable|array',
                'tournament_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if tournament can be completed
            $canComplete = $this->progressionService->canCompleteTournament($tournament);
            
            if (!$canComplete && !$request->force) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament cannot be completed yet',
                    'details' => $this->progressionService->getTournamentProgressionStatus($tournament),
                    'force_required' => true
                ], 422);
            }

            DB::beginTransaction();

            $result = $this->progressionService->completeTournament(
                $tournament,
                $request->final_results ?? [],
                $request->prize_distribution ?? [],
                $request->tournament_notes
            );

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'details' => $result['details'] ?? null
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament completed successfully',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament completion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Reset tournament phase
     */
    public function resetPhase(Request $request, Tournament $tournament, TournamentPhase $phase): JsonResponse
    {
        try {
            // Verify phase belongs to tournament
            if ($phase->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase does not belong to this tournament'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'reset_type' => 'required|in:bracket,matches,results,complete',
                'preserve_seeding' => 'nullable|boolean',
                'reset_reason' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($phase->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase is not started and cannot be reset'
                ], 422);
            }

            DB::beginTransaction();

            $result = $this->progressionService->resetTournamentPhase(
                $phase,
                $request->reset_type,
                $request->preserve_seeding ?? false,
                $request->reset_reason
            );

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament phase reset successfully',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament phase reset error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset tournament phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate tournament standings
     */
    public function calculateStandings(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $standingsType = $request->get('type', 'overall');
            $phaseId = $request->get('phase_id');

            $standings = $this->progressionService->calculateTournamentStandings(
                $tournament,
                $standingsType,
                $phaseId
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => $standingsType,
                    'phase_id' => $phaseId,
                    'standings' => $standings,
                    'calculated_at' => now()->toISOString(),
                    'tournament' => [
                        'id' => $tournament->id,
                        'name' => $tournament->name,
                        'status' => $tournament->status,
                        'current_phase' => $tournament->current_phase
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament standings calculation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate tournament standings',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament bracket progression
     */
    public function getBracketProgression(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $phaseId = $request->get('phase_id');
            $bracketId = $request->get('bracket_id');

            $progression = $this->progressionService->getTournamentBracketProgression(
                $tournament,
                $phaseId,
                $bracketId
            );

            return response()->json([
                'success' => true,
                'data' => $progression
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament bracket progression error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get bracket progression',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Simulate tournament progression
     */
    public function simulateProgression(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'phases_to_simulate' => 'nullable|integer|min:1|max:10',
                'completion_percentage' => 'nullable|numeric|min:0|max:100',
                'include_random_upsets' => 'nullable|boolean',
                'preset_results' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // This is a simulation - don't actually modify the database
            $simulation = $this->progressionService->simulateTournamentProgression(
                $tournament,
                $request->phases_to_simulate ?? 1,
                $request->completion_percentage ?? 100,
                $request->include_random_upsets ?? false,
                $request->preset_results ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Tournament progression simulated successfully',
                'data' => $simulation
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament progression simulation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate tournament progression',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get team progression through tournament
     */
    public function getTeamProgression(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $teamId = $request->get('team_id');
            
            if (!$teamId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team ID is required'
                ], 422);
            }

            // Verify team is in tournament
            $teamInTournament = $tournament->teams()->where('teams.id', $teamId)->exists();
            
            if (!$teamInTournament) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is not part of this tournament'
                ], 404);
            }

            $progression = $this->progressionService->getTeamTournamentProgression($tournament, $teamId);

            return response()->json([
                'success' => true,
                'data' => $progression
            ]);

        } catch (\Exception $e) {
            Log::error('Team tournament progression error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get team progression',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generate next round matches
     */
    public function generateNextRound(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'phase_id' => 'required|exists:tournament_phases,id',
                'bracket_id' => 'nullable|exists:tournament_brackets,id',
                'round_type' => 'nullable|in:swiss,elimination,group',
                'schedule_matches' => 'nullable|boolean',
                'match_interval' => 'nullable|integer|min:30|max:1440' // minutes
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $phase = TournamentPhase::find($request->phase_id);
            
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
                    'message' => 'Phase must be active to generate next round'
                ], 422);
            }

            DB::beginTransaction();

            $result = $this->progressionService->generateNextRound(
                $phase,
                $request->bracket_id,
                $request->round_type ?? $phase->phase_type,
                $request->schedule_matches ?? true,
                $request->match_interval ?? 60
            );

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Next round generated successfully',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Next round generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate next round',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update tournament seeding
     */
    public function updateSeeding(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'seeding_data' => 'required|array',
                'seeding_data.*.team_id' => 'required|exists:teams,id',
                'seeding_data.*.seed' => 'required|integer|min:1',
                'phase_id' => 'nullable|exists:tournament_phases,id',
                'apply_to_all_phases' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate all teams are in tournament
            $teamIds = collect($request->seeding_data)->pluck('team_id');
            $tournamentTeamIds = $tournament->teams()->pluck('teams.id');
            
            $invalidTeams = $teamIds->diff($tournamentTeamIds);
            if ($invalidTeams->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some teams are not part of this tournament',
                    'invalid_team_ids' => $invalidTeams->values()
                ], 422);
            }

            DB::beginTransaction();

            $result = $this->progressionService->updateTournamentSeeding(
                $tournament,
                $request->seeding_data,
                $request->phase_id,
                $request->apply_to_all_phases ?? false
            );

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament seeding updated successfully',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament seeding update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tournament seeding',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament timeline
     */
    public function getTournamentTimeline(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $includeDetails = $request->get('include_details', false);
            $phaseId = $request->get('phase_id');

            $timeline = $this->progressionService->getTournamentTimeline(
                $tournament,
                $includeDetails,
                $phaseId
            );

            return response()->json([
                'success' => true,
                'data' => $timeline
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament timeline error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tournament timeline',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Validate tournament integrity
     */
    public function validateTournamentIntegrity(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $fixIssues = $request->get('fix_issues', false);

            $validation = $this->progressionService->validateTournamentIntegrity(
                $tournament,
                $fixIssues
            );

            return response()->json([
                'success' => true,
                'data' => $validation
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament integrity validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate tournament integrity',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }
}