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
use App\Models\Team;
use App\Services\SwissSystemService;

class SwissController extends Controller
{
    protected $swissService;

    public function __construct(SwissSystemService $swissService)
    {
        $this->swissService = $swissService;
    }

    /**
     * Get Swiss tournament standings
     */
    public function getStandings(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            if ($tournament->format !== 'swiss') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is not using Swiss system format'
                ], 422);
            }

            $phaseId = $request->get('phase_id');
            $includeTiebreakers = $request->get('include_tiebreakers', true);
            $includeOpponentData = $request->get('include_opponents', false);

            $standings = $this->swissService->calculateSwissStandings(
                $tournament,
                $phaseId,
                $includeTiebreakers,
                $includeOpponentData
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'tournament' => [
                        'id' => $tournament->id,
                        'name' => $tournament->name,
                        'format' => $tournament->format,
                        'current_phase' => $tournament->current_phase
                    ],
                    'standings' => $standings,
                    'metadata' => [
                        'total_teams' => count($standings),
                        'rounds_completed' => $this->getCompletedRoundsCount($tournament, $phaseId),
                        'total_rounds_planned' => $this->getTotalRoundsCount($tournament, $phaseId),
                        'qualification_threshold' => $this->getQualificationThreshold($tournament),
                        'eliminated_teams' => $this->getEliminatedTeamsCount($tournament),
                        'qualified_teams' => $this->getQualifiedTeamsCount($tournament),
                        'calculated_at' => now()->toISOString()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Swiss standings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Swiss standings',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get Swiss tournament statistics
     */
    public function getStats(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            if ($tournament->format !== 'swiss') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is not using Swiss system format'
                ], 422);
            }

            $stats = [
                'tournament_info' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'total_teams' => $tournament->current_team_count,
                    'rounds_planned' => $this->getTotalRoundsCount($tournament),
                    'rounds_completed' => $this->getCompletedRoundsCount($tournament),
                    'current_round' => $this->getCurrentRound($tournament),
                    'qualification_threshold' => $this->getQualificationThreshold($tournament)
                ],
                'performance_metrics' => [
                    'qualified_teams' => $this->getQualifiedTeamsCount($tournament),
                    'eliminated_teams' => $this->getEliminatedTeamsCount($tournament),
                    'active_teams' => $this->getActiveTeamsCount($tournament),
                    'perfect_records' => $this->getPerfectRecordsCount($tournament),
                    'winless_teams' => $this->getWinlessTeamsCount($tournament)
                ],
                'match_statistics' => [
                    'total_matches' => $this->getTotalMatchesCount($tournament),
                    'completed_matches' => $this->getCompletedMatchesCount($tournament),
                    'pending_matches' => $this->getPendingMatchesCount($tournament),
                    'walkover_count' => $this->getWalkoverCount($tournament),
                    'average_match_duration' => $this->getAverageMatchDuration($tournament)
                ],
                'score_analysis' => [
                    'score_distribution' => $this->getScoreDistribution($tournament),
                    'buchholz_analysis' => $this->getBuchholzAnalysis($tournament),
                    'upset_count' => $this->getUpsetCount($tournament)
                ],
                'pairing_statistics' => [
                    'repeat_pairings' => $this->getRepeatPairingsCount($tournament),
                    'cross_region_matches' => $this->getCrossRegionMatchesCount($tournament),
                    'color_balance' => $this->getColorBalanceStats($tournament)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Swiss stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Swiss statistics',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get pairings for a specific round
     */
    public function getRoundPairings(Tournament $tournament, int $round, Request $request): JsonResponse
    {
        try {
            if ($tournament->format !== 'swiss') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is not using Swiss system format'
                ], 422);
            }

            $phaseId = $request->get('phase_id');
            $includeHistory = $request->get('include_history', false);

            $pairings = $this->swissService->getRoundPairings($tournament, $round, $phaseId, $includeHistory);

            if ($pairings === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Round not found or pairings not generated yet'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'round' => $round,
                    'tournament_id' => $tournament->id,
                    'phase_id' => $phaseId,
                    'pairings' => $pairings,
                    'round_info' => [
                        'total_matches' => count($pairings),
                        'completed_matches' => collect($pairings)->where('status', 'completed')->count(),
                        'pending_matches' => collect($pairings)->where('status', 'pending')->count(),
                        'ongoing_matches' => collect($pairings)->where('status', 'ongoing')->count()
                    ],
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Swiss round pairings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get round pairings',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generate next Swiss round (Admin)
     */
    public function generateNextRound(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            if ($tournament->format !== 'swiss') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is not using Swiss system format'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'phase_id' => 'nullable|exists:tournament_phases,id',
                'round_number' => 'nullable|integer|min:1|max:15',
                'pairing_method' => 'nullable|in:swiss_perfect,dutch_system,accelerated',
                'avoid_rematches' => 'nullable|boolean',
                'color_balancing' => 'nullable|boolean',
                'custom_pairings' => 'nullable|array',
                'schedule_matches' => 'nullable|boolean',
                'match_start_time' => 'nullable|date',
                'match_interval_minutes' => 'nullable|integer|min:15|max:480'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate tournament state
            if ($tournament->status !== 'ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament must be ongoing to generate next round'
                ], 422);
            }

            $currentRound = $this->getCurrentRound($tournament);
            $nextRound = $request->round_number ?? ($currentRound + 1);

            // Check if previous round is complete
            if ($nextRound > 1 && !$this->isRoundComplete($tournament, $nextRound - 1)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Previous round must be completed before generating next round'
                ], 422);
            }

            DB::beginTransaction();

            $pairings = $this->swissService->generateNextRound(
                $tournament,
                $nextRound,
                [
                    'phase_id' => $request->phase_id,
                    'pairing_method' => $request->pairing_method ?? 'swiss_perfect',
                    'avoid_rematches' => $request->avoid_rematches ?? true,
                    'color_balancing' => $request->color_balancing ?? true,
                    'custom_pairings' => $request->custom_pairings ?? [],
                    'schedule_matches' => $request->schedule_matches ?? true,
                    'match_start_time' => $request->match_start_time,
                    'match_interval_minutes' => $request->match_interval_minutes ?? 60
                ]
            );

            if (!$pairings['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $pairings['message'],
                    'details' => $pairings['details'] ?? null
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Swiss round {$nextRound} generated successfully",
                'data' => [
                    'round' => $nextRound,
                    'pairings' => $pairings['pairings'],
                    'matches_created' => count($pairings['pairings']),
                    'tournament_status' => $tournament->fresh()->status
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Swiss round generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate next Swiss round',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Complete Swiss system phase (Admin)
     */
    public function completeSwiss(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            if ($tournament->format !== 'swiss') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is not using Swiss system format'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'phase_id' => 'nullable|exists:tournament_phases,id',
                'force_completion' => 'nullable|boolean',
                'final_standings_override' => 'nullable|array',
                'qualification_override' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if Swiss phase can be completed
            $canComplete = $this->swissService->canCompleteSwissPhase($tournament, $request->phase_id);
            
            if (!$canComplete && !$request->force_completion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Swiss phase cannot be completed yet',
                    'details' => [
                        'pending_matches' => $this->getPendingMatchesCount($tournament),
                        'current_round' => $this->getCurrentRound($tournament),
                        'total_rounds' => $this->getTotalRoundsCount($tournament)
                    ],
                    'force_required' => true
                ], 422);
            }

            DB::beginTransaction();

            $result = $this->swissService->completeSwissPhase(
                $tournament,
                $request->phase_id,
                $request->final_standings_override ?? [],
                $request->qualification_override ?? []
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
                'message' => 'Swiss system phase completed successfully',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Swiss completion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete Swiss system phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update round pairings (Admin)
     */
    public function updatePairings(Request $request, Tournament $tournament, int $round): JsonResponse
    {
        try {
            if ($tournament->format !== 'swiss') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is not using Swiss system format'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'pairings' => 'required|array',
                'pairings.*.match_id' => 'required|exists:bracket_matches,id',
                'pairings.*.team1_id' => 'required|exists:teams,id',
                'pairings.*.team2_id' => 'required|exists:teams,id|different:pairings.*.team1_id',
                'pairings.*.scheduled_at' => 'nullable|date',
                'regenerate_bracket' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if round exists and hasn't started
            $roundMatches = BracketMatch::where('tournament_id', $tournament->id)
                                      ->where('round', $round)
                                      ->get();

            if ($roundMatches->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Round not found'
                ], 404);
            }

            if ($roundMatches->where('status', '!=', 'pending')->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update pairings for round with started or completed matches'
                ], 422);
            }

            DB::beginTransaction();

            $result = $this->swissService->updateRoundPairings(
                $tournament,
                $round,
                $request->pairings,
                $request->regenerate_bracket ?? false
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
                'message' => 'Round pairings updated successfully',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Swiss pairing update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update round pairings',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Private helper methods

    private function getCompletedRoundsCount(Tournament $tournament, ?int $phaseId = null): int
    {
        $query = BracketMatch::where('tournament_id', $tournament->id)
                            ->where('status', 'completed');
                            
        if ($phaseId) {
            $query->where('tournament_phase_id', $phaseId);
        }

        return $query->distinct('round')->count('round');
    }

    private function getTotalRoundsCount(Tournament $tournament, ?int $phaseId = null): int
    {
        $settings = $tournament->qualification_settings ?? [];
        return $settings['swiss_rounds'] ?? ceil(log($tournament->current_team_count, 2));
    }

    private function getCurrentRound(Tournament $tournament): int
    {
        return BracketMatch::where('tournament_id', $tournament->id)
                          ->max('round') ?? 0;
    }

    private function getQualificationThreshold(Tournament $tournament): array
    {
        $settings = $tournament->qualification_settings ?? [];
        return [
            'wins_required' => $settings['swiss_wins_required'] ?? 3,
            'losses_eliminated' => $settings['swiss_losses_eliminated'] ?? 3,
            'qualification_percentage' => $settings['qualification_percentage'] ?? 50
        ];
    }

    private function getQualifiedTeamsCount(Tournament $tournament): int
    {
        $threshold = $this->getQualificationThreshold($tournament);
        return $tournament->teams()
                         ->wherePivot('swiss_wins', '>=', $threshold['wins_required'])
                         ->count();
    }

    private function getEliminatedTeamsCount(Tournament $tournament): int
    {
        $threshold = $this->getQualificationThreshold($tournament);
        return $tournament->teams()
                         ->wherePivot('swiss_losses', '>=', $threshold['losses_eliminated'])
                         ->count();
    }

    private function getActiveTeamsCount(Tournament $tournament): int
    {
        $threshold = $this->getQualificationThreshold($tournament);
        return $tournament->teams()
                         ->wherePivot('swiss_wins', '<', $threshold['wins_required'])
                         ->wherePivot('swiss_losses', '<', $threshold['losses_eliminated'])
                         ->count();
    }

    private function getPerfectRecordsCount(Tournament $tournament): int
    {
        $currentRound = $this->getCurrentRound($tournament);
        return $tournament->teams()
                         ->wherePivot('swiss_wins', $currentRound)
                         ->wherePivot('swiss_losses', 0)
                         ->count();
    }

    private function getWinlessTeamsCount(Tournament $tournament): int
    {
        return $tournament->teams()
                         ->wherePivot('swiss_wins', 0)
                         ->count();
    }

    private function getTotalMatchesCount(Tournament $tournament): int
    {
        return BracketMatch::where('tournament_id', $tournament->id)->count();
    }

    private function getCompletedMatchesCount(Tournament $tournament): int
    {
        return BracketMatch::where('tournament_id', $tournament->id)
                          ->where('status', 'completed')
                          ->count();
    }

    private function getPendingMatchesCount(Tournament $tournament): int
    {
        return BracketMatch::where('tournament_id', $tournament->id)
                          ->where('status', 'pending')
                          ->count();
    }

    private function getWalkoverCount(Tournament $tournament): int
    {
        return BracketMatch::where('tournament_id', $tournament->id)
                          ->where('is_walkover', true)
                          ->count();
    }

    private function getAverageMatchDuration(Tournament $tournament): ?string
    {
        $matches = BracketMatch::where('tournament_id', $tournament->id)
                              ->where('status', 'completed')
                              ->whereNotNull('started_at')
                              ->whereNotNull('completed_at')
                              ->get();

        if ($matches->isEmpty()) return null;

        $totalMinutes = $matches->sum(function ($match) {
            return $match->started_at->diffInMinutes($match->completed_at);
        });

        $averageMinutes = $totalMinutes / $matches->count();
        return gmdate('H:i', $averageMinutes * 60);
    }

    private function getScoreDistribution(Tournament $tournament): array
    {
        return BracketMatch::where('tournament_id', $tournament->id)
                          ->where('status', 'completed')
                          ->get()
                          ->groupBy(function ($match) {
                              return $match->team1_score . '-' . $match->team2_score;
                          })
                          ->map->count()
                          ->toArray();
    }

    private function getBuchholzAnalysis(Tournament $tournament): array
    {
        $teams = $tournament->teams;
        return [
            'average_buchholz' => $teams->avg('pivot_swiss_buchholz'),
            'highest_buchholz' => $teams->max('pivot_swiss_buchholz'),
            'lowest_buchholz' => $teams->min('pivot_swiss_buchholz')
        ];
    }

    private function getUpsetCount(Tournament $tournament): int
    {
        // Would calculate based on seeding differences
        return 0;
    }

    private function getRepeatPairingsCount(Tournament $tournament): int
    {
        // Would check for teams playing each other multiple times
        return 0;
    }

    private function getCrossRegionMatchesCount(Tournament $tournament): int
    {
        // Would count matches between teams from different regions
        return 0;
    }

    private function getColorBalanceStats(Tournament $tournament): array
    {
        // Would analyze home/away balance for teams
        return ['balanced_percentage' => 95];
    }

    private function isRoundComplete(Tournament $tournament, int $round): bool
    {
        $roundMatches = BracketMatch::where('tournament_id', $tournament->id)
                                   ->where('round', $round)
                                   ->get();

        return $roundMatches->isNotEmpty() && 
               $roundMatches->where('status', '!=', 'completed')->isEmpty();
    }
}