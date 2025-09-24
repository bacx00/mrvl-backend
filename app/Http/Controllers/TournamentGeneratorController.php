<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CompleteTournamentGeneratorService;
use App\Helpers\MatchFormatHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentGeneratorController extends Controller
{
    protected $generatorService;

    public function __construct(CompleteTournamentGeneratorService $generatorService)
    {
        $this->generatorService = $generatorService;
    }

    /**
     * Generate complete tournament structure with all stages and matches
     * POST /api/admin/events/{eventId}/generate-tournament
     */
    public function generateTournament(Request $request, $eventId)
    {
        $request->validate([
            'format' => 'required|string',
            'team_count' => 'integer|min:4|max:128'
        ]);

        try {
            $result = $this->generatorService->generateCompleteTournament(
                $eventId,
                $request->format,
                [
                    'team_count' => $request->team_count ?? 32,
                    'tier' => $request->tier ?? 'B',
                    'region' => $request->region ?? 'international'
                ]
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tournament: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tournament structure for an event
     * GET /api/events/{eventId}/tournament-structure
     */
    public function getTournamentStructure($eventId)
    {
        try {
            // Get event with stages
            $event = DB::table('events')
                ->where('id', $eventId)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Get all stages
            $stages = DB::table('bracket_stages')
                ->where('event_id', $eventId)
                ->orderBy('stage_order')
                ->get()
                ->map(function ($stage) {
                    $stage->settings = json_decode($stage->settings);

                    // Get matches for this stage
                    $stage->matches = DB::table('matches')
                        ->where('stage_id', $stage->id)
                        ->orderBy('round_number')
                        ->orderBy('match_number')
                        ->get()
                        ->map(function ($match) {
                            // Get team names if assigned
                            if ($match->team1_id) {
                                $team1 = DB::table('teams')->where('id', $match->team1_id)->first();
                                $match->team1_name = $team1 ? $team1->name : null;
                                $match->team1_logo = $team1 ? $team1->logo : null;
                            }
                            if ($match->team2_id) {
                                $team2 = DB::table('teams')->where('id', $match->team2_id)->first();
                                $match->team2_name = $team2 ? $team2->name : null;
                                $match->team2_logo = $team2 ? $team2->logo : null;
                            }
                            return $match;
                        });

                    // Calculate stage progress
                    $totalMatches = $stage->matches->count();
                    $completedMatches = $stage->matches->where('status', 'completed')->count();
                    $stage->progress = $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100) : 0;

                    return $stage;
                });

            // Get tournament structure from event
            $structure = json_decode($event->tournament_structure);

            return response()->json([
                'success' => true,
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'format' => $event->format,
                    'status' => $event->status
                ],
                'stages' => $stages,
                'structure' => $structure,
                'stats' => [
                    'total_stages' => $stages->count(),
                    'total_matches' => DB::table('matches')->where('event_id', $eventId)->count(),
                    'completed_matches' => DB::table('matches')->where('event_id', $eventId)->where('status', 'completed')->count(),
                    'live_matches' => DB::table('matches')->where('event_id', $eventId)->where('status', 'live')->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tournament structure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a specific match
     * PUT /api/admin/matches/{matchId}
     */
    public function updateMatch(Request $request, $matchId)
    {
        $request->validate([
            'team1_id' => 'nullable|integer',
            'team2_id' => 'nullable|integer',
            'team1_score' => 'nullable|integer|min:0|max:9',
            'team2_score' => 'nullable|integer|min:0|max:9',
            'status' => 'nullable|in:pending,live,completed',
            'match_format' => 'nullable|string|in:Bo1,Bo2,Bo3,Bo4,Bo5,Bo6,Bo7,Bo8,Bo9',
            'scheduled_at' => 'nullable|date'
        ]);

        try {
            $match = DB::table('matches')->where('id', $matchId)->first();

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            $updateData = array_filter([
                'team1_id' => $request->team1_id,
                'team2_id' => $request->team2_id,
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'status' => $request->status,
                'match_format' => $request->match_format,
                'scheduled_at' => $request->scheduled_at,
                'updated_at' => now()
            ], function ($value) {
                return $value !== null;
            });

            DB::table('matches')
                ->where('id', $matchId)
                ->update($updateData);

            // Validate scores against format if both are provided
            if ($request->team1_score !== null && $request->team2_score !== null) {
                $format = $request->match_format ?? $match->match_format ?? 'Bo3';
                $validation = MatchFormatHelper::validateScores($format, $request->team1_score, $request->team2_score);

                if (!$validation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $validation['message']
                    ], 422);
                }

                // Auto-complete match if scores indicate completion
                if ($validation['complete'] && $request->status !== 'completed') {
                    $updateData['status'] = 'completed';
                }
            }

            // Check if match completed and advance winners if needed
            if (($updateData['status'] ?? $match->status) === 'completed' &&
                isset($updateData['team1_score']) && isset($updateData['team2_score'])) {
                $this->handleMatchCompletion($match, $updateData['team1_score'], $updateData['team2_score']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update matches (assign teams, update scores)
     * PUT /api/admin/events/{eventId}/matches/bulk
     */
    public function bulkUpdateMatches(Request $request, $eventId)
    {
        $request->validate([
            'matches' => 'required|array',
            'matches.*.id' => 'required|integer',
            'matches.*.team1_id' => 'nullable|integer',
            'matches.*.team2_id' => 'nullable|integer',
            'matches.*.team1_score' => 'nullable|integer|min:0',
            'matches.*.team2_score' => 'nullable|integer|min:0',
            'matches.*.status' => 'nullable|in:pending,live,completed',
            'matches.*.match_format' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->matches as $matchData) {
                $matchId = $matchData['id'];
                unset($matchData['id']);

                $matchData['updated_at'] = now();

                DB::table('matches')
                    ->where('id', $matchId)
                    ->where('event_id', $eventId)
                    ->update($matchData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Matches updated successfully',
                'updated_count' => count($request->matches)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update matches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match formats (Bo1-Bo9)
     * GET /api/match-formats
     */
    public function getMatchFormats()
    {
        return response()->json([
            'success' => true,
            'formats' => MatchFormatHelper::getAllFormats()
        ]);
    }

    /**
     * Get available tournament formats
     * GET /api/tournament-formats
     */
    public function getAvailableFormats()
    {
        $formats = [
            [
                'id' => 'mrc_championship',
                'name' => 'MRC Championship',
                'description' => 'Official Marvel Rivals Championship format',
                'stages' => 3,
                'recommended_teams' => 128
            ],
            [
                'id' => 'ignite_circuit',
                'name' => 'Ignite Circuit',
                'description' => 'Marvel Rivals Ignite format with groups',
                'stages' => 2,
                'recommended_teams' => 16
            ],
            [
                'id' => 'invitational',
                'name' => 'Invitational',
                'description' => 'Direct invite double elimination',
                'stages' => 1,
                'recommended_teams' => 8
            ],
            [
                'id' => 'single_elimination',
                'name' => 'Single Elimination',
                'description' => 'Standard knockout tournament',
                'stages' => 1,
                'recommended_teams' => 32
            ],
            [
                'id' => 'double_elimination',
                'name' => 'Double Elimination',
                'description' => 'Upper and lower bracket system',
                'stages' => 1,
                'recommended_teams' => 16
            ],
            [
                'id' => 'swiss_system',
                'name' => 'Swiss System',
                'description' => 'Performance-based pairing',
                'stages' => 1,
                'recommended_teams' => 32
            ],
            [
                'id' => 'round_robin',
                'name' => 'Round Robin',
                'description' => 'Every team plays every team',
                'stages' => 1,
                'recommended_teams' => 8
            ],
            [
                'id' => 'gsl_groups',
                'name' => 'GSL Groups',
                'description' => 'GSL group format to playoffs',
                'stages' => 2,
                'recommended_teams' => 16
            ]
        ];

        return response()->json([
            'success' => true,
            'formats' => $formats
        ]);
    }

    private function handleMatchCompletion($match, $team1Score, $team2Score)
    {
        // Determine winner
        $winnerId = $team1Score > $team2Score ? $match->team1_id : $match->team2_id;
        $loserId = $team1Score > $team2Score ? $match->team2_id : $match->team1_id;

        // Handle advancement based on bracket type
        if ($match->bracket_type === 'upper') {
            // Winner advances in upper bracket
            // Loser drops to lower bracket
            $this->advanceToNextMatch($match, $winnerId, 'upper');
            $this->dropToLowerBracket($match, $loserId);
        } else if ($match->bracket_type === 'lower') {
            // Winner advances in lower bracket
            // Loser is eliminated
            $this->advanceToNextMatch($match, $winnerId, 'lower');
        } else {
            // Single elimination - winner advances
            $this->advanceToNextMatch($match, $winnerId, 'single');
        }
    }

    private function advanceToNextMatch($match, $teamId, $bracketType)
    {
        // Logic to find next match and assign team
        // This depends on the tournament structure
    }

    private function dropToLowerBracket($match, $teamId)
    {
        // Logic to drop team to appropriate lower bracket match
    }
}