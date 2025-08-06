<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\BracketGame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentBracketController extends Controller
{
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
}