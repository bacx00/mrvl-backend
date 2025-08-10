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
use App\Models\Team;
use App\Models\ChatMessage;
use App\Services\TournamentProgressionService;
use Carbon\Carbon;

class TournamentMatchController extends Controller
{
    protected $progressionService;

    public function __construct(TournamentProgressionService $progressionService)
    {
        $this->progressionService = $progressionService;
    }

    /**
     * Display tournament matches
     */
    public function index(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $query = BracketMatch::where('tournament_id', $tournament->id)
                                ->with(['team1:id,name,short_name,logo', 'team2:id,name,short_name,logo', 'phase:id,name,phase_type']);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('phase_id')) {
                $query->where('tournament_phase_id', $request->phase_id);
            }

            if ($request->has('round')) {
                $query->where('round', $request->round);
            }

            if ($request->has('scheduled_from')) {
                $query->where('scheduled_at', '>=', $request->scheduled_from);
            }

            if ($request->has('scheduled_to')) {
                $query->where('scheduled_at', '<=', $request->scheduled_to);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'scheduled_at');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            $allowedSorts = ['scheduled_at', 'round', 'match_number', 'status', 'created_at'];
            
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            $perPage = min($request->get('per_page', 20), 100);
            $matches = $query->paginate($perPage);

            $matches->getCollection()->transform(function ($match) {
                return $this->formatMatchData($match);
            });

            return response()->json([
                'success' => true,
                'data' => $matches,
                'tournament' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'status' => $tournament->status,
                    'current_phase' => $tournament->current_phase
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament matches index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament matches',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created tournament match
     */
    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tournament_phase_id' => 'required|exists:tournament_phases,id',
                'tournament_bracket_id' => 'nullable|exists:tournament_brackets,id',
                'team1_id' => 'required|exists:teams,id',
                'team2_id' => 'required|exists:teams,id|different:team1_id',
                'match_identifier' => 'nullable|string|max:50',
                'round' => 'required|integer|min:1',
                'match_number' => 'required|integer|min:1',
                'match_format' => 'nullable|in:' . implode(',', array_keys(Tournament::MATCH_FORMATS)),
                'scheduled_at' => 'nullable|date',
                'map_data' => 'nullable|array',
                'referee_id' => 'nullable|exists:users,id',
                'stream_url' => 'nullable|url',
                'broadcast_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify phase belongs to tournament
            $phase = TournamentPhase::find($request->tournament_phase_id);
            if ($phase->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phase does not belong to this tournament'
                ], 422);
            }

            DB::beginTransaction();

            $match = new BracketMatch();
            $match->tournament_id = $tournament->id;
            $match->tournament_phase_id = $request->tournament_phase_id;
            $match->tournament_bracket_id = $request->tournament_bracket_id;
            $match->team1_id = $request->team1_id;
            $match->team2_id = $request->team2_id;
            $match->match_identifier = $request->match_identifier ?? 
                                      "R{$request->round}M{$request->match_number}";
            $match->round = $request->round;
            $match->match_number = $request->match_number;
            $match->match_format = $request->match_format ?? 'bo3';
            $match->scheduled_at = $request->scheduled_at ?? now()->addHours(1);
            $match->map_data = $request->map_data ?? [];
            $match->referee_id = $request->referee_id;
            $match->stream_url = $request->stream_url;
            $match->broadcast_data = $request->broadcast_data ?? [];
            $match->status = 'pending';
            
            $match->save();

            $match->load(['team1', 'team2', 'phase', 'referee:id,name,email']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament match created successfully',
                'data' => $this->formatMatchData($match)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tournament match',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified tournament match
     */
    public function update(Request $request, Tournament $tournament, BracketMatch $match): JsonResponse
    {
        try {
            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'team1_id' => 'sometimes|exists:teams,id',
                'team2_id' => 'sometimes|exists:teams,id|different:team1_id',
                'scheduled_at' => 'nullable|date',
                'match_format' => 'nullable|in:' . implode(',', array_keys(Tournament::MATCH_FORMATS)),
                'map_data' => 'nullable|array',
                'veto_data' => 'nullable|array',
                'referee_id' => 'nullable|exists:users,id',
                'stream_url' => 'nullable|url',
                'broadcast_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if match can be updated
            if ($match->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update completed match'
                ], 422);
            }

            DB::beginTransaction();

            $match->fill($request->only([
                'team1_id', 'team2_id', 'scheduled_at', 'match_format',
                'map_data', 'veto_data', 'referee_id', 'stream_url', 'broadcast_data'
            ]));

            $match->save();

            $match->load(['team1', 'team2', 'phase', 'referee:id,name,email']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament match updated successfully',
                'data' => $this->formatMatchData($match)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tournament match',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Complete a tournament match
     */
    public function completeMatch(Request $request, Tournament $tournament, BracketMatch $match): JsonResponse
    {
        try {
            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            if ($match->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match is already completed'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'team1_score' => 'required|integer|min:0|max:10',
                'team2_score' => 'required|integer|min:0|max:10',
                'map_results' => 'nullable|array',
                'statistics' => 'nullable|array',
                'is_walkover' => 'nullable|boolean',
                'walkover_reason' => 'nullable|string|required_if:is_walkover,true'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate scores
            if ($request->team1_score === $request->team2_score) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match cannot end in a tie'
                ], 422);
            }

            DB::beginTransaction();

            $match->team1_score = $request->team1_score;
            $match->team2_score = $request->team2_score;
            $match->status = 'completed';
            $match->completed_at = now();
            
            if ($request->has('map_results')) {
                $mapData = $match->map_data ?? [];
                $mapData['results'] = $request->map_results;
                $match->map_data = $mapData;
            }

            if ($request->has('statistics')) {
                $match->statistics = $request->statistics;
            }

            if ($request->is_walkover) {
                $match->is_walkover = true;
                $match->walkover_reason = $request->walkover_reason;
            }

            $match->save();

            // Progress tournament bracket
            $winnerTeamId = $match->team1_score > $match->team2_score ? 
                          $match->team1_id : $match->team2_id;
            $loserTeamId = $winnerTeamId === $match->team1_id ? 
                         $match->team2_id : $match->team1_id;

            $this->progressionService->processMatchResult($match, $winnerTeamId, $loserTeamId);

            $match->load(['team1', 'team2', 'phase', 'referee:id,name,email']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament match completed successfully',
                'data' => $this->formatMatchData($match)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match completion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete tournament match',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Set walkover for a tournament match
     */
    public function setWalkover(Request $request, Tournament $tournament, BracketMatch $match): JsonResponse
    {
        try {
            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            if ($match->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot set walkover for completed match'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'winner_team_id' => 'required|in:' . $match->team1_id . ',' . $match->team2_id,
                'reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $winnerTeamId = $request->winner_team_id;
            $loserTeamId = $winnerTeamId === $match->team1_id ? $match->team2_id : $match->team1_id;

            // Set walkover scores (typically 1-0 for walkover)
            $match->team1_score = $winnerTeamId === $match->team1_id ? 1 : 0;
            $match->team2_score = $winnerTeamId === $match->team2_id ? 1 : 0;
            $match->status = 'completed';
            $match->completed_at = now();
            $match->is_walkover = true;
            $match->walkover_reason = $request->reason;

            $match->save();

            // Progress tournament bracket
            $this->progressionService->processMatchResult($match, $winnerTeamId, $loserTeamId);

            $match->load(['team1', 'team2', 'phase', 'referee:id,name,email']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Walkover set successfully',
                'data' => $this->formatMatchData($match)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match walkover error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set walkover',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Report score by participant (for self-reporting tournaments)
     */
    public function reportScore(Request $request, Tournament $tournament, BracketMatch $match): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            // Verify user is part of one of the teams in the match
            $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
            $matchTeamIds = [$match->team1_id, $match->team2_id];
            
            if (empty(array_intersect($userTeamIds, $matchTeamIds))) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to report scores for this match'
                ], 403);
            }

            if ($match->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match is already completed'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'team1_score' => 'required|integer|min:0|max:10',
                'team2_score' => 'required|integer|min:0|max:10',
                'map_results' => 'nullable|array',
                'screenshots' => 'nullable|array',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate scores
            if ($request->team1_score === $request->team2_score) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match cannot end in a tie'
                ], 422);
            }

            DB::beginTransaction();

            // Store reported score (pending verification if tournament requires it)
            $reportData = [
                'reported_by' => $user->id,
                'reported_at' => now()->toDateTimeString(),
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'map_results' => $request->map_results ?? [],
                'screenshots' => $request->screenshots ?? [],
                'notes' => $request->notes,
                'needs_verification' => true
            ];

            // Check if both teams have reported the same score
            $existingReports = $match->broadcast_data['score_reports'] ?? [];
            $otherTeamReport = null;

            foreach ($existingReports as $report) {
                if (!in_array($report['reported_by'], $userTeamIds)) {
                    $otherTeamReport = $report;
                    break;
                }
            }

            $existingReports[] = $reportData;
            
            $broadcastData = $match->broadcast_data ?? [];
            $broadcastData['score_reports'] = $existingReports;

            // Auto-complete if scores match or tournament allows self-reporting
            $autoComplete = false;
            if ($otherTeamReport && 
                $otherTeamReport['team1_score'] === $request->team1_score &&
                $otherTeamReport['team2_score'] === $request->team2_score) {
                $autoComplete = true;
            } elseif ($tournament->settings['allow_self_reporting'] ?? false) {
                $autoComplete = true;
            }

            if ($autoComplete) {
                $match->team1_score = $request->team1_score;
                $match->team2_score = $request->team2_score;
                $match->status = 'completed';
                $match->completed_at = now();
                
                if ($request->has('map_results')) {
                    $mapData = $match->map_data ?? [];
                    $mapData['results'] = $request->map_results;
                    $match->map_data = $mapData;
                }

                // Progress tournament bracket
                $winnerTeamId = $match->team1_score > $match->team2_score ? 
                              $match->team1_id : $match->team2_id;
                $loserTeamId = $winnerTeamId === $match->team1_id ? 
                             $match->team2_id : $match->team1_id;

                $this->progressionService->processMatchResult($match, $winnerTeamId, $loserTeamId);
                
                $broadcastData['auto_completed'] = true;
                $message = 'Score reported and match completed successfully';
            } else {
                $match->status = 'score_reported';
                $message = 'Score reported successfully. Awaiting verification.';
            }

            $match->broadcast_data = $broadcastData;
            $match->save();

            $match->load(['team1', 'team2', 'phase']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $this->formatMatchData($match),
                'auto_completed' => $autoComplete
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match score report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to report score',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Dispute a match result
     */
    public function disputeResult(Request $request, Tournament $tournament, BracketMatch $match): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            // Verify user is part of one of the teams in the match
            $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
            $matchTeamIds = [$match->team1_id, $match->team2_id];
            
            if (empty(array_intersect($userTeamIds, $matchTeamIds))) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to dispute this match'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000',
                'evidence' => 'nullable|array',
                'requested_action' => 'required|in:rematch,score_change,walkover_reversal'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $disputeData = [
                'disputed_by' => $user->id,
                'disputed_at' => now()->toDateTimeString(),
                'reason' => $request->reason,
                'evidence' => $request->evidence ?? [],
                'requested_action' => $request->requested_action,
                'status' => 'pending'
            ];

            $broadcastData = $match->broadcast_data ?? [];
            $broadcastData['disputes'] = $broadcastData['disputes'] ?? [];
            $broadcastData['disputes'][] = $disputeData;

            $match->broadcast_data = $broadcastData;
            $match->status = 'disputed';
            $match->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Match result disputed successfully. Awaiting admin review.',
                'data' => $this->formatMatchData($match)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match dispute error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to dispute match result',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Resolve a dispute (Admin)
     */
    public function resolveDispute(Request $request, Tournament $tournament, BracketMatch $match): JsonResponse
    {
        try {
            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            if ($match->status !== 'disputed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Match is not in disputed status'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'dispute_id' => 'required|integer',
                'resolution' => 'required|in:accept,reject',
                'admin_notes' => 'nullable|string|max:1000',
                'new_team1_score' => 'nullable|integer|min:0|max:10|required_if:resolution,accept',
                'new_team2_score' => 'nullable|integer|min:0|max:10|required_if:resolution,accept'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $broadcastData = $match->broadcast_data ?? [];
            $disputes = $broadcastData['disputes'] ?? [];
            
            // Find and resolve the specific dispute
            $disputeFound = false;
            foreach ($disputes as $index => &$dispute) {
                if ($index === $request->dispute_id) {
                    $dispute['resolved_by'] = Auth::id();
                    $dispute['resolved_at'] = now()->toDateTimeString();
                    $dispute['resolution'] = $request->resolution;
                    $dispute['admin_notes'] = $request->admin_notes;
                    $dispute['status'] = 'resolved';
                    $disputeFound = true;
                    break;
                }
            }

            if (!$disputeFound) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute not found'
                ], 404);
            }

            if ($request->resolution === 'accept') {
                // Update match score
                $match->team1_score = $request->new_team1_score;
                $match->team2_score = $request->new_team2_score;
                
                // Re-process bracket progression if needed
                $winnerTeamId = $match->team1_score > $match->team2_score ? 
                              $match->team1_id : $match->team2_id;
                $loserTeamId = $winnerTeamId === $match->team1_id ? 
                             $match->team2_id : $match->team1_id;

                $this->progressionService->processMatchResult($match, $winnerTeamId, $loserTeamId);
            }

            $broadcastData['disputes'] = $disputes;
            $match->broadcast_data = $broadcastData;
            $match->status = 'completed';
            $match->save();

            $match->load(['team1', 'team2', 'phase']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dispute resolved successfully',
                'data' => $this->formatMatchData($match)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match dispute resolution error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve dispute',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Submit screenshot for match
     */
    public function submitScreenshot(Request $request, Tournament $tournament, BracketMatch $match): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            // Verify user is part of one of the teams in the match
            $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
            $matchTeamIds = [$match->team1_id, $match->team2_id];
            
            if (empty(array_intersect($userTeamIds, $matchTeamIds))) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to submit screenshots for this match'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'screenshot' => 'required|image|max:5120', // 5MB max
                'description' => 'nullable|string|max:500',
                'map_number' => 'nullable|integer|min:1|max:10'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Store screenshot
            $screenshot = $request->file('screenshot');
            $filename = 'tournament_' . $tournament->id . '_match_' . $match->id . '_' . 
                       time() . '_' . $user->id . '.' . $screenshot->getClientOriginalExtension();
            
            $path = $screenshot->storeAs('tournament_screenshots', $filename, 'public');

            $screenshotData = [
                'uploaded_by' => $user->id,
                'uploaded_at' => now()->toDateTimeString(),
                'filename' => $filename,
                'path' => $path,
                'description' => $request->description,
                'map_number' => $request->map_number,
                'url' => asset('storage/' . $path)
            ];

            $broadcastData = $match->broadcast_data ?? [];
            $broadcastData['screenshots'] = $broadcastData['screenshots'] ?? [];
            $broadcastData['screenshots'][] = $screenshotData;

            $match->broadcast_data = $broadcastData;
            $match->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Screenshot submitted successfully',
                'data' => $screenshotData
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match screenshot submission error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit screenshot',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get match chat messages
     */
    public function getMatchChat(Tournament $tournament, BracketMatch $match, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            // Verify user is part of one of the teams in the match or is admin/referee
            $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
            $matchTeamIds = [$match->team1_id, $match->team2_id];
            $isAuthorized = !empty(array_intersect($userTeamIds, $matchTeamIds)) ||
                           $user->hasRole(['admin', 'moderator']) ||
                           $match->referee_id === $user->id;
            
            if (!$isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this match chat'
                ], 403);
            }

            $query = ChatMessage::where('context_type', 'tournament_match')
                               ->where('context_id', $match->id)
                               ->with(['user:id,name,avatar']);

            $perPage = min($request->get('per_page', 50), 100);
            $messages = $query->orderBy('created_at', 'desc')
                             ->paginate($perPage);

            $messages->getCollection()->transform(function ($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'user' => $message->user,
                    'created_at' => $message->created_at->toISOString(),
                    'is_system' => $message->is_system ?? false
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament match chat fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch match chat',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send match chat message
     */
    public function sendChatMessage(Request $request, Tournament $tournament, BracketMatch $match): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify match belongs to tournament
            if ($match->tournament_id !== $tournament->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match does not belong to this tournament'
                ], 404);
            }

            // Verify user is part of one of the teams in the match or is admin/referee
            $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
            $matchTeamIds = [$match->team1_id, $match->team2_id];
            $isAuthorized = !empty(array_intersect($userTeamIds, $matchTeamIds)) ||
                           $user->hasRole(['admin', 'moderator']) ||
                           $match->referee_id === $user->id;
            
            if (!$isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to send messages in this match chat'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $chatMessage = ChatMessage::create([
                'user_id' => $user->id,
                'context_type' => 'tournament_match',
                'context_id' => $match->id,
                'message' => $request->message,
                'is_system' => false
            ]);

            $chatMessage->load(['user:id,name,avatar']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Chat message sent successfully',
                'data' => [
                    'id' => $chatMessage->id,
                    'message' => $chatMessage->message,
                    'user' => $chatMessage->user,
                    'created_at' => $chatMessage->created_at->toISOString(),
                    'is_system' => false
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament match chat send error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send chat message',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Format match data for API response
     */
    private function formatMatchData(BracketMatch $match): array
    {
        return [
            'id' => $match->id,
            'tournament_id' => $match->tournament_id,
            'tournament_phase_id' => $match->tournament_phase_id,
            'tournament_bracket_id' => $match->tournament_bracket_id,
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
            'map_data' => $match->map_data,
            'veto_data' => $match->veto_data,
            'is_walkover' => $match->is_walkover,
            'walkover_reason' => $match->walkover_reason,
            'stream_url' => $match->stream_url,
            'broadcast_data' => $match->broadcast_data,
            'statistics' => $match->statistics,
            'phase' => $match->phase ? [
                'id' => $match->phase->id,
                'name' => $match->phase->name,
                'phase_type' => $match->phase->phase_type
            ] : null,
            'referee' => $match->referee ? [
                'id' => $match->referee->id,
                'name' => $match->referee->name,
                'email' => $match->referee->email
            ] : null,
            'created_at' => $match->created_at->toISOString(),
            'updated_at' => $match->updated_at->toISOString()
        ];
    }
}