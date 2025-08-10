<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\TournamentRegistration;
use App\Models\BracketMatch;
use App\Models\Team;
use App\Services\TournamentService;
use App\Services\TournamentBroadcastService;
use App\Services\SwissSystemService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminTournamentController extends Controller
{
    protected $tournamentService;
    protected $broadcastService;
    protected $swissService;

    public function __construct(
        TournamentService $tournamentService,
        TournamentBroadcastService $broadcastService,
        SwissSystemService $swissService
    ) {
        $this->tournamentService = $tournamentService;
        $this->broadcastService = $broadcastService;
        $this->swissService = $swissService;
        $this->middleware(['auth:api', 'admin']);
    }

    /**
     * Get admin dashboard data for tournaments
     */
    public function getDashboard(): JsonResponse
    {
        try {
            $stats = [
                'overview' => [
                    'total_tournaments' => Tournament::count(),
                    'active_tournaments' => Tournament::whereIn('status', ['registration_open', 'ongoing'])->count(),
                    'completed_tournaments' => Tournament::where('status', 'completed')->count(),
                    'draft_tournaments' => Tournament::where('status', 'draft')->count(),
                ],
                'recent_activity' => [
                    'new_tournaments_this_week' => Tournament::where('created_at', '>=', now()->subWeek())->count(),
                    'tournaments_started_today' => Tournament::where('started_at', '>=', now()->startOfDay())->count(),
                    'tournaments_completed_today' => Tournament::where('completed_at', '>=', now()->startOfDay())->count(),
                ],
                'registrations' => [
                    'pending_approvals' => TournamentRegistration::where('status', 'pending')->count(),
                    'total_registrations_today' => TournamentRegistration::where('created_at', '>=', now()->startOfDay())->count(),
                ],
                'matches' => [
                    'matches_pending' => BracketMatch::where('status', 'pending')->count(),
                    'matches_ongoing' => BracketMatch::where('status', 'ongoing')->count(),
                    'matches_needing_review' => BracketMatch::where('status', 'disputed')->count(),
                ]
            ];

            $recentTournaments = Tournament::with(['organizer:id,name'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($tournament) {
                    return [
                        'id' => $tournament->id,
                        'name' => $tournament->name,
                        'type' => $tournament->type,
                        'format' => $tournament->format,
                        'status' => $tournament->status,
                        'teams_registered' => $tournament->current_team_count,
                        'max_teams' => $tournament->max_teams,
                        'organizer' => $tournament->organizer->name ?? 'Unknown',
                        'created_at' => $tournament->created_at->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'recent_tournaments' => $recentTournaments
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin tournament dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament management overview
     */
    public function getManagementOverview(Tournament $tournament): JsonResponse
    {
        try {
            $tournament->load(['phases', 'registrations.team', 'organizer:id,name']);
            
            $data = [
                'tournament' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'type' => $tournament->type,
                    'format' => $tournament->format,
                    'status' => $tournament->status,
                    'current_phase' => $tournament->current_phase,
                    'organizer' => $tournament->organizer->name ?? 'Unknown',
                    'prize_pool' => $tournament->prize_pool,
                    'entry_fee' => $tournament->entry_fee,
                ],
                'statistics' => $this->tournamentService->getTournamentStatistics($tournament),
                'phases' => $tournament->phases->map(function ($phase) {
                    return [
                        'id' => $phase->id,
                        'name' => $phase->name,
                        'type' => $phase->type,
                        'status' => $phase->status,
                        'order' => $phase->order,
                        'started_at' => $phase->started_at?->toISOString(),
                        'completed_at' => $phase->completed_at?->toISOString(),
                    ];
                }),
                'registrations' => [
                    'total' => $tournament->registrations->count(),
                    'pending' => $tournament->registrations->where('status', 'pending')->count(),
                    'approved' => $tournament->registrations->where('status', 'approved')->count(),
                    'rejected' => $tournament->registrations->where('status', 'rejected')->count(),
                ],
                'matches' => [
                    'total' => $tournament->bracketMatches()->count(),
                    'completed' => $tournament->bracketMatches()->where('status', 'completed')->count(),
                    'pending' => $tournament->bracketMatches()->where('status', 'pending')->count(),
                    'ongoing' => $tournament->bracketMatches()->where('status', 'ongoing')->count(),
                    'disputed' => $tournament->bracketMatches()->where('status', 'disputed')->count(),
                ],
                'recent_activity' => $this->getRecentTournamentActivity($tournament),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament management overview error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tournament overview',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Force start tournament (Admin override)
     */
    public function forceStartTournament(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'override_reason' => 'required|string|max:500',
                'minimum_teams_override' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($tournament->status === 'ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is already started'
                ], 422);
            }

            DB::beginTransaction();

            $success = $this->tournamentService->startTournament($tournament);

            if (!$success && $request->minimum_teams_override) {
                // Force start even with insufficient teams
                $tournament->update([
                    'status' => 'ongoing',
                    'started_at' => now()
                ]);
                $success = true;
            }

            if ($success) {
                // Log admin override
                Log::info('Admin force started tournament', [
                    'tournament_id' => $tournament->id,
                    'admin_id' => auth()->id(),
                    'reason' => $request->override_reason,
                    'minimum_teams_override' => $request->minimum_teams_override ?? false
                ]);

                // Send system message
                $this->broadcastService->sendSystemMessage(
                    $tournament->id,
                    "Tournament has been started by an administrator. Reason: {$request->override_reason}"
                );

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Tournament started successfully'
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to start tournament'
                ], 422);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Force start tournament error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Force complete tournament (Admin override)
     */
    public function forceCompleteTournament(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'override_reason' => 'required|string|max:500',
                'final_results' => 'nullable|array',
                'final_results.*.team_id' => 'required_with:final_results|exists:teams,id',
                'final_results.*.placement' => 'required_with:final_results|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($tournament->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is already completed'
                ], 422);
            }

            DB::beginTransaction();

            $results = $request->final_results ?? [];
            $success = $this->tournamentService->completeTournament($tournament, $results);

            if ($success) {
                // Log admin override
                Log::info('Admin force completed tournament', [
                    'tournament_id' => $tournament->id,
                    'admin_id' => auth()->id(),
                    'reason' => $request->override_reason,
                    'custom_results' => !empty($results)
                ]);

                // Send system message
                $this->broadcastService->sendSystemMessage(
                    $tournament->id,
                    "Tournament has been completed by an administrator. Reason: {$request->override_reason}"
                );

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Tournament completed successfully'
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to complete tournament'
                ], 422);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Force complete tournament error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete tournament',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Manage tournament registrations
     */
    public function manageRegistrations(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 20), 100);
            $offset = $request->get('offset', 0);
            $status = $request->get('status', 'all');

            $query = $tournament->registrations()->with(['team:id,name,short_name,logo']);

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $registrations = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($registration) {
                    return [
                        'id' => $registration->id,
                        'team' => [
                            'id' => $registration->team->id,
                            'name' => $registration->team->name,
                            'short_name' => $registration->team->short_name,
                            'logo' => $registration->team->logo,
                        ],
                        'status' => $registration->status,
                        'check_in_status' => $registration->check_in_status,
                        'registered_at' => $registration->created_at->toISOString(),
                        'checked_in_at' => $registration->checked_in_at?->toISOString(),
                        'approved_at' => $registration->approved_at?->toISOString(),
                    ];
                });

            $stats = [
                'total' => $tournament->registrations()->count(),
                'pending' => $tournament->registrations()->where('status', 'pending')->count(),
                'approved' => $tournament->registrations()->where('status', 'approved')->count(),
                'rejected' => $tournament->registrations()->where('status', 'rejected')->count(),
                'checked_in' => $tournament->registrations()->where('check_in_status', 'checked_in')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'registrations' => $registrations,
                    'statistics' => $stats,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => $registrations->count() === $limit
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Manage registrations error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load registrations',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk approve/reject registrations
     */
    public function bulkManageRegistrations(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,reject',
                'registration_ids' => 'required|array|min:1',
                'registration_ids.*' => 'integer|exists:tournament_registrations,id',
                'reason' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $registrations = TournamentRegistration::whereIn('id', $request->registration_ids)
                ->where('tournament_id', $tournament->id)
                ->with('team')
                ->get();

            $processed = 0;

            foreach ($registrations as $registration) {
                if ($registration->status === 'pending') {
                    $registration->update([
                        'status' => $request->action === 'approve' ? 'approved' : 'rejected',
                        'approved_at' => $request->action === 'approve' ? now() : null,
                        'rejected_at' => $request->action === 'reject' ? now() : null,
                        'admin_notes' => $request->reason,
                    ]);

                    // Broadcast registration update
                    $this->broadcastService->broadcastRegistrationUpdate(
                        $tournament,
                        $registration->team,
                        $registration,
                        "team_{$request->action}d",
                        ['admin_action' => true, 'reason' => $request->reason]
                    );

                    $processed++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$processed} registrations {$request->action}d successfully",
                'data' => ['processed_count' => $processed]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk manage registrations error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process registrations',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Manage tournament phases
     */
    public function managePhases(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:start_phase,complete_phase,skip_phase',
                'phase_id' => 'required|exists:tournament_phases,id',
                'reason' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $phase = TournamentPhase::where('id', $request->phase_id)
                ->where('tournament_id', $tournament->id)
                ->first();

            if (!$phase) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase not found'
                ], 404);
            }

            DB::beginTransaction();

            $success = match($request->action) {
                'start_phase' => $this->startPhase($phase),
                'complete_phase' => $this->completePhase($phase),
                'skip_phase' => $this->skipPhase($phase),
            };

            if ($success) {
                // Log admin action
                Log::info("Admin {$request->action} for tournament phase", [
                    'tournament_id' => $tournament->id,
                    'phase_id' => $phase->id,
                    'admin_id' => auth()->id(),
                    'reason' => $request->reason
                ]);

                // Broadcast phase update
                $this->broadcastService->broadcastPhaseStarted($tournament, $phase);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Phase action completed successfully'
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to perform phase action'
                ], 422);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manage phases error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to manage phase',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get disputed matches for review
     */
    public function getDisputedMatches(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 20), 100);
            $offset = $request->get('offset', 0);

            $disputedMatches = BracketMatch::where('status', 'disputed')
                ->with(['tournament:id,name', 'team1:id,name,short_name', 'team2:id,name,short_name'])
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($match) {
                    return [
                        'id' => $match->id,
                        'tournament' => [
                            'id' => $match->tournament->id,
                            'name' => $match->tournament->name,
                        ],
                        'team1' => [
                            'id' => $match->team1?->id,
                            'name' => $match->team1?->name,
                            'score' => $match->team1_score,
                        ],
                        'team2' => [
                            'id' => $match->team2?->id,
                            'name' => $match->team2?->name,
                            'score' => $match->team2_score,
                        ],
                        'round' => $match->round,
                        'dispute_reason' => $match->dispute_reason,
                        'disputed_at' => $match->disputed_at?->toISOString(),
                        'evidence_files' => $match->evidence_files ?? [],
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $disputedMatches,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => $disputedMatches->count() === $limit
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get disputed matches error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load disputed matches',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Resolve match dispute
     */
    public function resolveDispute(Request $request, BracketMatch $match): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'resolution' => 'required|in:approve_scores,override_scores,rematch',
                'team1_score' => 'required_if:resolution,override_scores|integer|min:0',
                'team2_score' => 'required_if:resolution,override_scores|integer|min:0',
                'admin_notes' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($match->status !== 'disputed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match is not disputed'
                ], 422);
            }

            DB::beginTransaction();

            $previousState = [
                'team1_score' => $match->team1_score,
                'team2_score' => $match->team2_score,
                'status' => $match->status
            ];

            match($request->resolution) {
                'approve_scores' => $this->approveMatchScores($match, $request->admin_notes),
                'override_scores' => $this->overrideMatchScores($match, $request->team1_score, $request->team2_score, $request->admin_notes),
                'rematch' => $this->orderRematch($match, $request->admin_notes),
            };

            // Broadcast match update
            $this->broadcastService->broadcastMatchUpdate(
                $match,
                'dispute_resolved',
                $previousState,
                [
                    'resolution' => $request->resolution,
                    'admin_id' => auth()->id(),
                    'admin_notes' => $request->admin_notes
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dispute resolved successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Resolve dispute error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve dispute',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Private helper methods

    private function getRecentTournamentActivity(Tournament $tournament): array
    {
        $activity = [];

        // Recent registrations
        $recentRegistrations = $tournament->registrations()
            ->with('team:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($reg) {
                return [
                    'type' => 'registration',
                    'message' => "{$reg->team->name} registered",
                    'timestamp' => $reg->created_at->toISOString(),
                ];
            });

        // Recent matches
        $recentMatches = $tournament->bracketMatches()
            ->with(['team1:id,name', 'team2:id,name'])
            ->where('status', 'completed')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($match) {
                $winner = $match->getWinner();
                return [
                    'type' => 'match_completed',
                    'message' => $winner ? "{$winner->name} defeated " . ($match->team1_id === $winner->id ? $match->team2->name : $match->team1->name) : 'Match completed',
                    'timestamp' => $match->completed_at?->toISOString() ?? $match->updated_at->toISOString(),
                ];
            });

        return $recentRegistrations->concat($recentMatches)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->toArray();
    }

    private function startPhase(TournamentPhase $phase): bool
    {
        if ($phase->status !== 'pending') {
            return false;
        }

        $phase->update([
            'status' => 'active',
            'started_at' => now()
        ]);

        return true;
    }

    private function completePhase(TournamentPhase $phase): bool
    {
        if ($phase->status !== 'active') {
            return false;
        }

        $phase->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        return true;
    }

    private function skipPhase(TournamentPhase $phase): bool
    {
        $phase->update([
            'status' => 'skipped',
            'completed_at' => now()
        ]);

        return true;
    }

    private function approveMatchScores(BracketMatch $match, string $adminNotes): void
    {
        $match->update([
            'status' => 'completed',
            'admin_notes' => $adminNotes,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'completed_at' => now()
        ]);
    }

    private function overrideMatchScores(BracketMatch $match, int $team1Score, int $team2Score, string $adminNotes): void
    {
        $match->update([
            'team1_score' => $team1Score,
            'team2_score' => $team2Score,
            'status' => 'completed',
            'admin_notes' => $adminNotes,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'completed_at' => now()
        ]);
    }

    private function orderRematch(BracketMatch $match, string $adminNotes): void
    {
        $match->update([
            'status' => 'pending',
            'team1_score' => 0,
            'team2_score' => 0,
            'admin_notes' => $adminNotes,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'started_at' => null,
            'completed_at' => null
        ]);
    }
}