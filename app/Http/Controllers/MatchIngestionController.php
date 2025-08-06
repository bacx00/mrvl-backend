<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MatchIngestionController extends Controller
{
    /**
     * Ingest match reports from JSON POST requests
     * Handles bulk match data ingestion with comprehensive validation
     */
    public function ingestMatchReport(Request $request)
    {
        try {
            // Validate the incoming request structure
            $validator = Validator::make($request->all(), [
                'matches' => 'required|array|min:1|max:100', // Limit to 100 matches per request
                'matches.*.id' => 'nullable|string|max:255',
                'matches.*.event_id' => 'nullable|integer|exists:events,id',
                'matches.*.event_name' => 'nullable|string|max:255',
                'matches.*.team1_name' => 'required|string|max:255',
                'matches.*.team2_name' => 'required|string|max:255',
                'matches.*.team1_score' => 'nullable|integer|min:0|max:999',
                'matches.*.team2_score' => 'nullable|integer|min:0|max:999',
                'matches.*.status' => 'required|string|in:upcoming,live,completed,cancelled,postponed',
                'matches.*.scheduled_at' => 'nullable|date',
                'matches.*.started_at' => 'nullable|date',
                'matches.*.completed_at' => 'nullable|date',
                'matches.*.format' => 'nullable|string|in:bo1,bo3,bo5,bo7',
                'matches.*.maps' => 'nullable|array|max:7',
                'matches.*.maps.*.name' => 'required_with:matches.*.maps|string|max:255',
                'matches.*.maps.*.team1_score' => 'nullable|integer|min:0',
                'matches.*.maps.*.team2_score' => 'nullable|integer|min:0',
                'matches.*.maps.*.winner' => 'nullable|string|in:team1,team2,draw',
                'matches.*.players' => 'nullable|array',
                'matches.*.players.*.name' => 'required_with:matches.*.players|string|max:255',
                'matches.*.players.*.team' => 'required_with:matches.*.players|string|in:team1,team2',
                'matches.*.players.*.hero' => 'nullable|string|max:255',
                'matches.*.players.*.stats' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'request_id' => $this->generateRequestId()
                ], 422);
            }

            $matches = $request->input('matches');
            $processed = [];
            $errors = [];
            $requestId = $this->generateRequestId();

            DB::beginTransaction();

            foreach ($matches as $index => $matchData) {
                try {
                    $match = $this->processMatch($matchData, $requestId);
                    $processed[] = [
                        'index' => $index,
                        'id' => $match->id,
                        'external_id' => $matchData['id'] ?? null,
                        'status' => 'success'
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'external_id' => $matchData['id'] ?? null,
                        'error' => $e->getMessage(),
                        'status' => 'failed'
                    ];
                    
                    Log::warning("Match ingestion error for index {$index}", [
                        'error' => $e->getMessage(),
                        'match_data' => $matchData,
                        'request_id' => $requestId
                    ]);
                }
            }

            // Commit transaction if we have at least one successful match
            if (count($processed) > 0) {
                DB::commit();
                
                Log::info("Match ingestion completed", [
                    'request_id' => $requestId,
                    'processed' => count($processed),
                    'errors' => count($errors),
                    'total_matches' => count($matches)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Match ingestion completed',
                    'request_id' => $requestId,
                    'summary' => [
                        'total_matches' => count($matches),
                        'processed' => count($processed),
                        'errors' => count($errors)
                    ],
                    'processed_matches' => $processed,
                    'errors' => $errors
                ], 200);
            } else {
                DB::rollback();
                
                return response()->json([
                    'success' => false,
                    'message' => 'No matches could be processed',
                    'request_id' => $requestId,
                    'errors' => $errors
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Match ingestion fatal error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error during match ingestion',
                'error' => app()->environment('local') ? $e->getMessage() : 'An error occurred',
                'request_id' => $this->generateRequestId()
            ], 500);
        }
    }

    /**
     * Process individual match data
     */
    private function processMatch(array $matchData, string $requestId): MatchModel
    {
        // Find or create teams
        $team1 = $this->findOrCreateTeam($matchData['team1_name']);
        $team2 = $this->findOrCreateTeam($matchData['team2_name']);

        // Find or create event if provided
        $event = null;
        if (!empty($matchData['event_id'])) {
            $event = Event::find($matchData['event_id']);
        } elseif (!empty($matchData['event_name'])) {
            $event = Event::firstOrCreate(
                ['name' => $matchData['event_name']],
                [
                    'name' => $matchData['event_name'],
                    'start_date' => $matchData['scheduled_at'] ?? now(),
                    'end_date' => $matchData['completed_at'] ?? now()->addDays(1),
                    'status' => 'ongoing',
                    'format' => 'tournament',
                    'prize_pool' => 0
                ]
            );
        }

        // Create or update match
        $matchAttributes = [
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'event_id' => $event?->id,
            'team1_score' => $matchData['team1_score'] ?? 0,
            'team2_score' => $matchData['team2_score'] ?? 0,
            'status' => $matchData['status'],
            'format' => $matchData['format'] ?? 'bo3',
            'scheduled_at' => $matchData['scheduled_at'] ? Carbon::parse($matchData['scheduled_at']) : null,
            'started_at' => $matchData['started_at'] ? Carbon::parse($matchData['started_at']) : null,
            'completed_at' => $matchData['completed_at'] ? Carbon::parse($matchData['completed_at']) : null,
            'external_id' => $matchData['id'] ?? null,
            'ingestion_request_id' => $requestId
        ];

        // Find existing match by external_id or create new one
        $match = null;
        if (!empty($matchData['id'])) {
            $match = MatchModel::where('external_id', $matchData['id'])->first();
        }

        if ($match) {
            $match->update($matchAttributes);
        } else {
            $match = MatchModel::create($matchAttributes);
        }

        // Process maps if provided
        if (!empty($matchData['maps']) && is_array($matchData['maps'])) {
            $this->processMaps($match, $matchData['maps']);
        }

        // Process player stats if provided
        if (!empty($matchData['players']) && is_array($matchData['players'])) {
            $this->processPlayerStats($match, $matchData['players'], $team1, $team2);
        }

        return $match;
    }

    /**
     * Find or create team by name
     */
    private function findOrCreateTeam(string $teamName): Team
    {
        return Team::firstOrCreate(
            ['name' => $teamName],
            [
                'name' => $teamName,
                'country' => 'Unknown',
                'region' => 'Unknown',
                'status' => 'active'
            ]
        );
    }

    /**
     * Process map data for a match
     */
    private function processMaps(MatchModel $match, array $maps): void
    {
        // Clear existing maps for this match
        $match->maps()->delete();

        foreach ($maps as $index => $mapData) {
            $match->maps()->create([
                'map_name' => $mapData['name'],
                'map_number' => $index + 1,
                'team1_score' => $mapData['team1_score'] ?? 0,
                'team2_score' => $mapData['team2_score'] ?? 0,
                'winner' => $mapData['winner'] ?? null,
                'status' => 'completed'
            ]);
        }
    }

    /**
     * Process player statistics
     */
    private function processPlayerStats(MatchModel $match, array $players, Team $team1, Team $team2): void
    {
        // Clear existing player stats for this match
        $match->playerStats()->delete();

        foreach ($players as $playerData) {
            // Determine which team the player is on
            $teamId = $playerData['team'] === 'team1' ? $team1->id : $team2->id;
            
            // Find or create player
            $player = Player::firstOrCreate(
                ['name' => $playerData['name']],
                [
                    'name' => $playerData['name'],
                    'team_id' => $teamId,
                    'role' => 'player',
                    'country' => 'Unknown'
                ]
            );

            // Create player stats
            $statsData = array_merge([
                'match_id' => $match->id,
                'player_id' => $player->id,
                'team_id' => $teamId,
                'hero' => $playerData['hero'] ?? null,
            ], $playerData['stats'] ?? []);

            $match->playerStats()->create($statsData);
        }
    }

    /**
     * Generate unique request ID for tracking
     */
    private function generateRequestId(): string
    {
        return 'ing_' . date('Ymd_His') . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }

    /**
     * Get ingestion status by request ID
     */
    public function getIngestionStatus(Request $request, string $requestId)
    {
        try {
            $matches = MatchModel::where('ingestion_request_id', $requestId)->get();
            
            if ($matches->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No matches found for this request ID',
                    'request_id' => $requestId
                ], 404);
            }

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'matches' => $matches->map(function ($match) {
                    return [
                        'id' => $match->id,
                        'external_id' => $match->external_id,
                        'teams' => [
                            'team1' => $match->team1->name ?? 'Unknown',
                            'team2' => $match->team2->name ?? 'Unknown'
                        ],
                        'score' => "{$match->team1_score}-{$match->team2_score}",
                        'status' => $match->status,
                        'created_at' => $match->created_at->toISOString(),
                        'updated_at' => $match->updated_at->toISOString()
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching ingestion status", [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching ingestion status',
                'error' => app()->environment('local') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Health check endpoint for the ingestion service
     */
    public function healthCheck()
    {
        try {
            // Check database connectivity
            DB::connection()->getPdo();
            
            // Check if required tables exist
            $requiredTables = ['matches', 'teams', 'players', 'events'];
            $missingTables = [];
            
            foreach ($requiredTables as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    $missingTables[] = $table;
                }
            }
            
            if (!empty($missingTables)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database schema issues detected',
                    'missing_tables' => $missingTables
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ingestion service is healthy',
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ingestion service health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}