<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Event;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\BracketGame;
use App\Services\TournamentIntegrationService;
use App\Services\BracketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class TournamentBracketController extends Controller
{
    protected TournamentIntegrationService $tournamentService;
    protected BracketService $bracketService;

    public function __construct(
        TournamentIntegrationService $tournamentService,
        BracketService $bracketService
    ) {
        $this->tournamentService = $tournamentService;
        $this->bracketService = $bracketService;
    }

    /**
     * Get all tournaments with basic info
     */
    public function index(Request $request)
    {
        $query = Tournament::query()
            ->withCount(['teams', 'matches'])
            ->with(['teams' => function ($q) {
                $q->orderByPivot('seed');
            }]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        $tournaments = $query->orderBy('start_date', 'desc')->paginate(10);

        return response()->json($tournaments);
    }

    /**
     * Get single tournament with full bracket data
     */
    public function show($slug)
    {
        $tournament = Tournament::where('slug', $slug)
            ->with([
                'teams' => function ($q) {
                    $q->orderByPivot('swiss_score', 'desc')
                      ->orderByPivot('swiss_wins', 'desc');
                },
                'bracketStages' => function ($q) {
                    $q->orderBy('stage_order');
                },
                'bracketStages.matches' => function ($q) {
                    $q->orderBy('round_number')
                      ->orderBy('match_number')
                      ->with(['team1', 'team2', 'winner', 'loser', 'position']);
                },
                'bracketStages.matches.games' => function ($q) {
                    $q->orderBy('game_number');
                }
            ])
            ->firstOrFail();

        // Add bracket visualization data
        $bracketData = $this->generateBracketVisualization($tournament);

        return response()->json([
            'tournament' => $tournament,
            'bracket_visualization' => $bracketData
        ]);
    }

    /**
     * Get Swiss stage standings
     */
    public function swissStandings($tournamentSlug)
    {
        $tournament = Tournament::where('slug', $tournamentSlug)->firstOrFail();
        
        $standings = $tournament->teams()
            ->select('teams.*')
            ->selectRaw('tournament_teams.swiss_wins as wins')
            ->selectRaw('tournament_teams.swiss_losses as losses')
            ->selectRaw('tournament_teams.swiss_score as score')
            ->selectRaw('tournament_teams.seed as seed')
            ->orderByDesc('tournament_teams.swiss_score')
            ->orderByDesc('tournament_teams.swiss_wins')
            ->orderBy('tournament_teams.swiss_losses')
            ->orderBy('tournament_teams.seed')
            ->get();

        return response()->json([
            'tournament' => $tournament->name,
            'standings' => $standings
        ]);
    }

    /**
     * Get specific stage bracket
     */
    public function stageBracket($tournamentSlug, $stageType)
    {
        $tournament = Tournament::where('slug', $tournamentSlug)->firstOrFail();
        
        $stage = BracketStage::where('tournament_id', $tournament->id)
            ->where('type', $stageType)
            ->with([
                'matches' => function ($q) {
                    $q->orderBy('round_number')
                      ->orderBy('match_number')
                      ->with(['team1', 'team2', 'winner', 'loser', 'games', 'position']);
                }
            ])
            ->firstOrFail();

        $bracketStructure = $this->generateStageStructure($stage);

        return response()->json([
            'tournament' => $tournament->name,
            'stage' => $stage,
            'bracket_structure' => $bracketStructure
        ]);
    }

    /**
     * Get specific match details
     */
    public function matchDetails($matchId)
    {
        $match = BracketMatch::where('match_id', $matchId)
            ->with([
                'tournament',
                'bracketStage',
                'team1.players',
                'team2.players',
                'winner',
                'loser',
                'games' => function ($q) {
                    $q->orderBy('game_number');
                }
            ])
            ->firstOrFail();

        return response()->json($match);
    }

    /**
     * Update match result
     */
    public function updateMatch(Request $request, $matchId)
    {
        $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'status' => 'required|in:scheduled,live,completed,cancelled',
            'games' => 'array',
            'games.*.team1_score' => 'integer|min:0',
            'games.*.team2_score' => 'integer|min:0',
            'games.*.map_name' => 'string',
            'games.*.winner_id' => 'exists:teams,id'
        ]);

        DB::beginTransaction();
        
        try {
            $match = BracketMatch::where('match_id', $matchId)->firstOrFail();
            
            // Update match scores
            $match->update([
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'status' => $request->status
            ]);

            // If completed, determine winner and advance teams
            if ($request->status === 'completed') {
                $winnerId = $request->team1_score > $request->team2_score 
                    ? $match->team1_id 
                    : $match->team2_id;
                
                $loserId = $winnerId === $match->team1_id 
                    ? $match->team2_id 
                    : $match->team1_id;

                $match->completeMatch($winnerId, $loserId);
            }

            // Update game results if provided
            if ($request->has('games')) {
                foreach ($request->games as $gameNumber => $gameData) {
                    $game = BracketGame::firstOrCreate(
                        [
                            'bracket_match_id' => $match->id,
                            'game_number' => $gameNumber + 1
                        ],
                        [
                            'team1_id' => $match->team1_id,
                            'team2_id' => $match->team2_id
                        ]
                    );

                    $game->update($gameData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'match' => $match->fresh(['games', 'team1', 'team2', 'winner', 'loser'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate bracket visualization data
     */
    private function generateBracketVisualization($tournament)
    {
        $visualization = [];

        foreach ($tournament->bracketStages as $stage) {
            $stageData = [
                'stage_name' => $stage->name,
                'stage_type' => $stage->type,
                'rounds' => []
            ];

            $matchesByRound = $stage->matches->groupBy('round_number');

            foreach ($matchesByRound as $roundNumber => $matches) {
                $roundData = [
                    'round_number' => $roundNumber,
                    'round_name' => $matches->first()->round_name ?? "Round $roundNumber",
                    'matches' => []
                ];

                foreach ($matches as $match) {
                    $matchData = [
                        'match_id' => $match->match_id,
                        'position' => $match->position ? [
                            'column' => $match->position->column_position,
                            'row' => $match->position->row_position,
                            'tier' => $match->position->tier
                        ] : null,
                        'team1' => $match->team1 ? [
                            'id' => $match->team1->id,
                            'name' => $match->team1->name,
                            'logo' => $match->team1->logo
                        ] : ['name' => $match->team1_source ?? 'TBD'],
                        'team2' => $match->team2 ? [
                            'id' => $match->team2->id,
                            'name' => $match->team2->name,
                            'logo' => $match->team2->logo
                        ] : ['name' => $match->team2_source ?? 'TBD'],
                        'score' => "{$match->team1_score} - {$match->team2_score}",
                        'status' => $match->status,
                        'winner_id' => $match->winner_id,
                        'advances_to' => [
                            'winner' => $match->winner_advances_to,
                            'loser' => $match->loser_advances_to
                        ]
                    ];

                    $roundData['matches'][] = $matchData;
                }

                $stageData['rounds'][] = $roundData;
            }

            $visualization[] = $stageData;
        }

        return $visualization;
    }

    /**
     * Generate stage bracket structure
     */
    private function generateStageStructure($stage)
    {
        $structure = [
            'type' => $stage->type,
            'rounds' => []
        ];

        $maxRound = $stage->matches->max('round_number');

        for ($round = 1; $round <= $maxRound; $round++) {
            $roundMatches = $stage->matches->where('round_number', $round);
            
            $structure['rounds'][$round] = [
                'name' => $roundMatches->first()->round_name ?? "Round $round",
                'matches' => $roundMatches->map(function ($match) {
                    return [
                        'match_id' => $match->match_id,
                        'team1' => $match->team1->name ?? $match->team1_source ?? 'TBD',
                        'team2' => $match->team2->name ?? $match->team2_source ?? 'TBD',
                        'score' => $match->score_display,
                        'status' => $match->status,
                        'position' => $match->position
                    ];
                })->values()
            ];
        }

        return $structure;
    }

    /**
     * Get tournament bracket with Liquipedia notation (for Events)
     * 
     * @api GET /api/tournaments/events/{eventId}/bracket
     */
    public function getEventBracket(int $eventId): JsonResponse
    {
        try {
            $cacheKey = "event_bracket_{$eventId}";
            
            $bracket = Cache::remember($cacheKey, 300, function () use ($eventId) {
                $event = Event::with(['brackets.matches.team1', 'brackets.matches.team2'])->findOrFail($eventId);
                
                return [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'format' => $event->format,
                        'status' => $event->status,
                        'current_round' => $event->current_round,
                        'total_rounds' => $event->total_rounds
                    ],
                    'brackets' => $this->formatBracketsForFrontend($event->brackets),
                    'liquipedia_notation' => $this->generateLiquipediaNotationForEvent($event),
                    'standings' => $this->getEventStandings($event),
                    'live_matches' => $this->getLiveEventMatches($event),
                    'metadata' => [
                        'teams_count' => $event->teams()->count(),
                        'completed_matches' => $this->getEventCompletedMatchesCount($event),
                        'total_matches' => $this->getEventTotalMatchesCount($event),
                        'prize_pool' => $event->prize_pool,
                        'currency' => $event->currency
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $bracket
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch event bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update match score with Liquipedia integration
     * 
     * @api PUT /api/tournaments/events/{eventId}/match/{matchId}/score
     */
    public function updateEventMatchScore(Request $request, int $eventId, int $matchId): JsonResponse
    {
        $this->authorize('manage-tournaments');

        $validator = Validator::make($request->all(), [
            'team1_score' => 'required|integer|min:0|max:7',
            'team2_score' => 'required|integer|min:0|max:7',
            'status' => 'sometimes|string|in:pending,ready,live,completed,forfeit,cancelled',
            'games' => 'sometimes|array',
            'games.*.game_number' => 'required|integer|min:1',
            'games.*.map_name' => 'sometimes|string|max:100',
            'games.*.team1_score' => 'required|integer|min:0',
            'games.*.team2_score' => 'required|integer|min:0',
            'games.*.winner_id' => 'sometimes|integer|exists:teams,id',
            'notes' => 'sometimes|string|max:1000',
            'forfeit' => 'sometimes|boolean',
            'forfeit_team_id' => 'required_if:forfeit,true|integer|exists:teams,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $match = BracketMatch::where('event_id', $eventId)->findOrFail($matchId);
            
            // Update match with detailed results
            $updateData = [
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'notes' => $request->notes ?? $match->notes
            ];

            // Handle forfeit
            if ($request->forfeit && $request->forfeit_team_id) {
                $updateData['status'] = 'forfeit';
                $updateData['winner_id'] = $request->forfeit_team_id == $match->team1_id ? $match->team2_id : $match->team1_id;
                $updateData['loser_id'] = $request->forfeit_team_id;
                $updateData['completed_at'] = now();
            } else if ($request->status) {
                $updateData['status'] = $request->status;
                
                if ($request->status === 'completed') {
                    // Determine winner
                    if ($request->team1_score > $request->team2_score) {
                        $updateData['winner_id'] = $match->team1_id;
                        $updateData['loser_id'] = $match->team2_id;
                    } else if ($request->team2_score > $request->team1_score) {
                        $updateData['winner_id'] = $match->team2_id;
                        $updateData['loser_id'] = $match->team1_id;
                    }
                    $updateData['completed_at'] = now();
                } else if ($request->status === 'live' && !$match->started_at) {
                    $updateData['started_at'] = now();
                }
            }

            $match->update($updateData);

            // Update individual games if provided
            if ($request->games) {
                foreach ($request->games as $gameData) {
                    $match->games()->updateOrCreate(
                        ['game_number' => $gameData['game_number']],
                        array_filter([
                            'map_name' => $gameData['map_name'] ?? null,
                            'team1_score' => $gameData['team1_score'],
                            'team2_score' => $gameData['team2_score'],
                            'winner_id' => $gameData['winner_id'] ?? null,
                            'ended_at' => isset($gameData['winner_id']) ? now() : null
                        ])
                    );
                }
            }

            // Process match completion with tournament integration
            if ($match->status === 'completed' && $match->winner_id) {
                $advancement = $this->tournamentService->processMatchCompletion($match, $request->all());
                
                // Clear related caches
                Cache::forget("event_bracket_{$eventId}");
                Cache::forget("live_matches_{$eventId}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Match updated and advancement processed',
                    'data' => [
                        'match' => $match->fresh(['team1', 'team2', 'winner', 'loser', 'games']),
                        'advancement' => $advancement,
                        'liquipedia_id' => $match->liquipedia_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Match score updated successfully',
                'data' => $match->fresh(['team1', 'team2', 'games'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate next Swiss round for event
     * 
     * @api POST /api/tournaments/events/{eventId}/bracket/swiss/next-round
     */
    public function generateNextEventSwissRound(Request $request, int $eventId): JsonResponse
    {
        $this->authorize('manage-tournaments');

        $validator = Validator::make($request->all(), [
            'stage_id' => 'required|integer|exists:bracket_stages,id',
            'round_number' => 'required|integer|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $stage = BracketStage::where('event_id', $eventId)
                ->where('type', 'swiss')
                ->findOrFail($request->stage_id);

            $matches = $this->tournamentService->generateSwissRound($stage, $request->round_number);

            Cache::forget("event_bracket_{$eventId}");

            return response()->json([
                'success' => true,
                'message' => 'Swiss round generated successfully',
                'data' => [
                    'round_number' => $request->round_number,
                    'matches_created' => $matches->count(),
                    'matches' => $this->formatMatchesForFrontend($matches),
                    'liquipedia_notation' => $this->generateRoundLiquipediaNotation($matches, $request->round_number)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Swiss round: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live tournament state for event
     * 
     * @api GET /api/tournaments/events/{eventId}/live-state
     */
    public function getEventLiveState(int $eventId): JsonResponse
    {
        try {
            $cacheKey = "event_live_state_{$eventId}";
            
            $liveState = Cache::remember($cacheKey, 30, function () use ($eventId) {
                $event = Event::findOrFail($eventId);
                
                $liveMatches = BracketMatch::with(['team1', 'team2', 'bracketStage'])
                    ->where('event_id', $eventId)
                    ->where('status', 'live')
                    ->get();

                $upcomingMatches = BracketMatch::with(['team1', 'team2', 'bracketStage'])
                    ->where('event_id', $eventId)
                    ->where('status', 'ready')
                    ->whereNotNull('team1_id')
                    ->whereNotNull('team2_id')
                    ->orderBy('scheduled_at')
                    ->limit(5)
                    ->get();

                return [
                    'event_status' => $event->status,
                    'current_round' => $event->current_round,
                    'total_rounds' => $event->total_rounds,
                    'live_matches' => $this->formatMatchesForFrontend($liveMatches),
                    'upcoming_matches' => $this->formatMatchesForFrontend($upcomingMatches),
                    'recent_results' => $this->getRecentEventResults($event),
                    'standings' => $this->getEventStandings($event),
                    'statistics' => [
                        'total_matches' => $this->getEventTotalMatchesCount($event),
                        'completed_matches' => $this->getEventCompletedMatchesCount($event),
                        'completion_percentage' => $this->getEventCompletionPercentage($event)
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $liveState
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live state: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for formatting and data processing
    
    protected function formatBracketsForFrontend($brackets)
    {
        return $brackets->map(function ($bracket) {
            return [
                'id' => $bracket->id,
                'name' => $bracket->name,
                'type' => $bracket->type,
                'status' => $bracket->status,
                'current_round' => $bracket->current_round,
                'total_rounds' => $bracket->total_rounds,
                'matches' => $this->formatMatchesForFrontend($bracket->matches ?? collect())
            ];
        });
    }

    protected function formatMatchesForFrontend($matches)
    {
        return $matches->map(function ($match) {
            return [
                'id' => $match->id,
                'match_id' => $match->match_id,
                'liquipedia_id' => $match->liquipedia_id,
                'round_name' => $match->round_name,
                'round_number' => $match->round_number,
                'match_number' => $match->match_number,
                'teams' => [
                    'team1' => $this->formatTeamData($match->team1),
                    'team2' => $this->formatTeamData($match->team2)
                ],
                'sources' => [
                    'team1_source' => $match->team1_source,
                    'team2_source' => $match->team2_source
                ],
                'score' => [
                    'team1' => $match->team1_score,
                    'team2' => $match->team2_score,
                    'best_of' => $match->best_of
                ],
                'status' => $match->status,
                'scheduled_at' => $match->scheduled_at,
                'started_at' => $match->started_at,
                'completed_at' => $match->completed_at,
                'winner_id' => $match->winner_id,
                'progression' => [
                    'winner_advances_to' => $match->winner_advances_to,
                    'loser_advances_to' => $match->loser_advances_to
                ]
            ];
        });
    }

    protected function formatTeamData($team)
    {
        if (!$team) return null;

        return [
            'id' => $team->id,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo' => $team->logo,
            'region' => $team->region,
            'rating' => $team->rating ?? 1000
        ];
    }

    protected function generateLiquipediaNotationForEvent(Event $event): array
    {
        $notation = [];
        $brackets = $event->brackets ?? collect();
        
        foreach ($brackets as $bracket) {
            $matches = $bracket->matches ?? collect();
            $rounds = [];
            $matchesByRound = $matches->groupBy('round_number');
            
            foreach ($matchesByRound as $roundNumber => $roundMatches) {
                $roundNotation = [];
                
                foreach ($roundMatches as $index => $match) {
                    $matchId = $match->liquipedia_id ?: "R{$roundNumber}M" . ($index + 1);
                    
                    $roundNotation[] = [
                        'liquipedia_id' => $matchId,
                        'match_id' => $match->match_id,
                        'teams' => [
                            'team1' => $this->formatTeamData($match->team1),
                            'team2' => $this->formatTeamData($match->team2)
                        ],
                        'score' => [
                            'team1' => $match->team1_score,
                            'team2' => $match->team2_score
                        ],
                        'status' => $match->status,
                        'advancement' => [
                            'winner_to' => $match->winner_advances_to,
                            'loser_to' => $match->loser_advances_to
                        ]
                    ];
                }
                
                $rounds["Round {$roundNumber}"] = $roundNotation;
            }
            
            $notation[$bracket->name] = $rounds;
        }
        
        return $notation;
    }

    protected function generateRoundLiquipediaNotation($matches, int $roundNumber): array
    {
        return $matches->map(function ($match, $index) use ($roundNumber) {
            return [
                'liquipedia_id' => "R{$roundNumber}M" . ($index + 1),
                'match_id' => $match->match_id,
                'teams' => [
                    'team1' => $this->formatTeamData($match->team1),
                    'team2' => $this->formatTeamData($match->team2)
                ],
                'sources' => [
                    'team1_source' => $match->team1_source,
                    'team2_source' => $match->team2_source
                ]
            ];
        })->toArray();
    }

    protected function getEventStandings(Event $event): array
    {
        // Implementation would calculate current event standings
        return [];
    }

    protected function getLiveEventMatches(Event $event): array
    {
        return BracketMatch::with(['team1', 'team2'])
            ->where('event_id', $event->id)
            ->where('status', 'live')
            ->get()
            ->toArray();
    }

    protected function getEventCompletedMatchesCount(Event $event): int
    {
        return BracketMatch::where('event_id', $event->id)
            ->where('status', 'completed')
            ->count();
    }

    protected function getEventTotalMatchesCount(Event $event): int
    {
        return BracketMatch::where('event_id', $event->id)->count();
    }

    protected function getEventCompletionPercentage(Event $event): float
    {
        $total = $this->getEventTotalMatchesCount($event);
        $completed = $this->getEventCompletedMatchesCount($event);
        
        return $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
    }

    protected function getRecentEventResults(Event $event): array
    {
        return BracketMatch::with(['team1', 'team2', 'winner'])
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($match) {
                return [
                    'match_id' => $match->match_id,
                    'liquipedia_id' => $match->liquipedia_id,
                    'teams' => [
                        'team1' => $this->formatTeamData($match->team1),
                        'team2' => $this->formatTeamData($match->team2)
                    ],
                    'score' => [
                        'team1' => $match->team1_score,
                        'team2' => $match->team2_score
                    ],
                    'winner' => $this->formatTeamData($match->winner),
                    'completed_at' => $match->completed_at
                ];
            })
            ->toArray();
    }
}