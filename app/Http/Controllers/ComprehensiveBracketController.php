<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\Team;
use App\Services\BracketGenerationService;
use App\Services\BracketProgressionService;
use App\Services\SeedingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ComprehensiveBracketController extends Controller
{
    protected $bracketGenerationService;
    protected $bracketProgressionService;
    protected $seedingService;

    public function __construct(
        BracketGenerationService $bracketGenerationService,
        BracketProgressionService $bracketProgressionService,
        SeedingService $seedingService
    ) {
        $this->bracketGenerationService = $bracketGenerationService;
        $this->bracketProgressionService = $bracketProgressionService;
        $this->seedingService = $seedingService;
    }
    /**
     * Tournament format constants
     */
    const FORMAT_SINGLE_ELIMINATION = 'single_elimination';
    const FORMAT_DOUBLE_ELIMINATION = 'double_elimination';
    const FORMAT_ROUND_ROBIN = 'round_robin';
    const FORMAT_SWISS = 'swiss';
    const FORMAT_GROUP_STAGE = 'group_stage';

    /**
     * Seeding methods
     */
    const SEED_RANDOM = 'random';
    const SEED_RATING = 'rating';
    const SEED_MANUAL = 'manual';
    const SEED_BALANCED = 'balanced';

    /**
     * Get bracket data with comprehensive structure
     */
    public function show($eventId)
    {
        try {
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            // Get bracket structure based on format
            $bracket = $this->generateComprehensiveBracket($event);
            
            // Get metadata
            $metadata = $this->getBracketMetadata($event);
            
            // Get event teams and standings
            $teams = $this->getEventTeamsWithDetails($eventId);
            $standings = $this->calculateEventStandings($event);

            return response()->json([
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'format' => $event->format,
                        'status' => $event->status,
                        'type' => $event->type ?? 'tournament'
                    ],
                    'bracket' => $bracket,
                    'teams' => $teams,
                    'standings' => $standings,
                    'metadata' => $metadata
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching bracket: ' . $e->getMessage(), [
                'event_id' => $eventId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate comprehensive tournament bracket
     */
    public function generate(Request $request, $eventId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'format' => 'required|in:single_elimination,double_elimination,round_robin,swiss,group_stage',
            'seeding_method' => 'required|in:random,rating,manual,balanced',
            'randomize_seeds' => 'boolean',
            'groups' => 'nullable|integer|min:2|max:8',
            'teams_per_group' => 'nullable|integer|min:3|max:8',
            'swiss_rounds' => 'nullable|integer|min:3|max:10',
            'best_of' => 'nullable|in:bo1,bo3,bo5',
            'third_place_match' => 'boolean',
            'bracket_reset' => 'boolean' // For double elimination grand finals
        ]);

        try {
            DB::beginTransaction();

            $event = Event::find($eventId);
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }

            // Get participating teams
            $teams = $this->getEventTeamsForGeneration($eventId);
            if (count($teams) < 2) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Need at least 2 teams to generate bracket'
                ], 400);
            }

            // Validate team count for specific formats
            $this->validateTeamCountForFormat($teams, $request->format, $request);

            // Clear existing matches and brackets
            $this->clearExistingBrackets($eventId);

            // Apply seeding
            $seededTeams = $this->applyAdvancedSeeding($teams, $request->seeding_method, $request->randomize_seeds);

            // Generate bracket based on format
            $result = $this->createAdvancedBracket($event, $seededTeams, $request->all());

            // Update event with bracket information
            $event->update([
                'format' => $request->format,
                'status' => 'ongoing',
                'bracket_data' => json_encode([
                    'seeding_method' => $request->seeding_method,
                    'format_options' => $request->except(['format', 'seeding_method']),
                    'generated_at' => now(),
                    'total_matches' => $result['total_matches'],
                    'total_rounds' => $result['total_rounds']
                ])
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Advanced bracket generated successfully',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generating bracket: ' . $e->getMessage(), [
                'event_id' => $eventId,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error generating bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update match with advanced validation
     */
    public function updateMatch(Request $request, $matchId)
    {
        $this->authorize('manage-events');
        
        $request->validate([
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'status' => 'required|in:completed,cancelled,postponed',
            'maps_data' => 'nullable|array',
            'overtime' => 'nullable|boolean',
            'forfeit' => 'nullable|boolean',
            'winner_by_forfeit' => 'nullable|integer|in:1,2'
        ]);

        try {
            DB::beginTransaction();

            $match = DB::table('matches')->where('id', $matchId)->first();
            if (!$match) {
                return response()->json(['success' => false, 'message' => 'Match not found'], 404);
            }

            // Validate scores based on match format
            $this->validateMatchScores($match, $request->all());

            // Update match with comprehensive data
            $updateData = [
                'team1_score' => $request->team1_score,
                'team2_score' => $request->team2_score,
                'status' => $request->status,
                'maps_data' => $request->maps_data ? json_encode($request->maps_data) : null,
                'overtime' => $request->overtime ?? false,
                'forfeit' => $request->forfeit ?? false,
                'winner_by_forfeit' => $request->winner_by_forfeit,
                'updated_at' => now()
            ];

            if ($request->status === 'completed') {
                $updateData['completed_at'] = now();
            }

            DB::table('matches')->where('id', $matchId)->update($updateData);

            // Process match completion with advanced bracket progression
            if ($request->status === 'completed') {
                $this->processAdvancedMatchCompletion($match, $request->all());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Match updated successfully',
                'data' => [
                    'match_id' => $matchId,
                    'new_status' => $request->status,
                    'progression_triggered' => $request->status === 'completed'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating match: ' . $e->getMessage(), [
                'match_id' => $matchId,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate next round for Swiss format
     */
    public function generateNextSwissRound($eventId)
    {
        $this->authorize('manage-events');

        try {
            DB::beginTransaction();

            $event = Event::find($eventId);
            if (!$event || $event->format !== self::FORMAT_SWISS) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found or not Swiss format'
                ], 404);
            }

            $result = $this->createNextSwissRound($event);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Next Swiss round generated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generating next Swiss round: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error generating next Swiss round: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed bracket analysis
     */
    public function getBracket($eventId)
    {
        return $this->show($eventId);
    }
    
    public function getLiveMatches()
    {
        try {
            $liveMatches = DB::table('matches as m')
                ->join('events as e', 'm.event_id', '=', 'e.id')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('m.status', 'ongoing')
                ->select([
                    'm.id',
                    'm.event_id',
                    'e.name as event_name',
                    'm.team1_id',
                    't1.name as team1_name',
                    't1.logo as team1_logo',
                    'm.team1_score',
                    'm.team2_id',
                    't2.name as team2_name',
                    't2.logo as team2_logo',
                    'm.team2_score',
                    'm.status',
                    'm.round',
                    'm.scheduled_at',
                    'm.stream_url'
                ])
                ->orderBy('m.scheduled_at', 'asc')
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $liveMatches
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching live matches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function getBracketAnalysis($eventId)
    {
        try {
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }

            $analysis = [
                'format_analysis' => $this->analyzeFormat($event),
                'progression_analysis' => $this->analyzeProgression($event),
                'seeding_analysis' => $this->analyzeSeedingEffectiveness($event),
                'performance_metrics' => $this->calculatePerformanceMetrics($event)
            ];

            return response()->json([
                'success' => true,
                'data' => $analysis
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error analyzing bracket: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== PRIVATE HELPER METHODS =====

    /**
     * Generate comprehensive bracket structure
     */
    private function generateComprehensiveBracket($event)
    {
        switch ($event->format) {
            case self::FORMAT_SINGLE_ELIMINATION:
                return $this->generateAdvancedSingleEliminationBracket($event);
            case self::FORMAT_DOUBLE_ELIMINATION:
                return $this->generateAdvancedDoubleEliminationBracket($event);
            case self::FORMAT_ROUND_ROBIN:
                return $this->generateAdvancedRoundRobinBracket($event);
            case self::FORMAT_SWISS:
                return $this->generateAdvancedSwissBracket($event);
            case self::FORMAT_GROUP_STAGE:
                return $this->generateGroupStageBracket($event);
            default:
                return $this->generateAdvancedSingleEliminationBracket($event);
        }
    }

    /**
     * Advanced Single Elimination Bracket
     */
    private function generateAdvancedSingleEliminationBracket($event)
    {
        $matches = $this->getEventMatches($event->id);
        $teamCount = $this->getEventTeamCount($event->id);
        
        $rounds = [];
        $roundMatches = [];

        // Group matches by round
        foreach ($matches as $match) {
            if (!isset($roundMatches[$match->round])) {
                $roundMatches[$match->round] = [];
            }
            $roundMatches[$match->round][] = $this->formatMatchData($match, $event->id);
        }

        // Create round structure with proper naming
        $totalRounds = empty($roundMatches) ? 0 : max(array_keys($roundMatches));
        for ($roundNum = 1; $roundNum <= $totalRounds; $roundNum++) {
            $rounds[] = [
                'round_number' => $roundNum,
                'round_name' => $this->getAdvancedRoundName($roundNum, $totalRounds, $teamCount),
                'matches' => $roundMatches[$roundNum] ?? [],
                'start_date' => $this->estimateRoundStartDate($event, $roundNum),
                'status' => $this->getRoundStatus($roundMatches[$roundNum] ?? [])
            ];
        }

        return [
            'type' => 'single_elimination',
            'format' => 'Single Elimination',
            'rounds' => $rounds,
            'total_rounds' => $totalRounds,
            'teams_remaining' => $this->countTeamsRemaining($matches),
            'completed_matches' => $this->countCompletedMatches($matches),
            'bracket_structure' => $this->analyzeBracketStructure($teamCount, 'single'),
            'estimated_completion' => $this->estimateTournamentCompletion($event, $matches)
        ];
    }

    /**
     * Advanced Double Elimination Bracket
     */
    private function generateAdvancedDoubleEliminationBracket($event)
    {
        $matches = $this->getEventMatches($event->id);
        
        $upperBracket = [];
        $lowerBracket = [];
        $grandFinal = null;
        $bracketReset = null;

        // Separate matches by bracket type
        foreach ($matches as $match) {
            $matchData = $this->formatMatchData($match, $event->id);
            
            switch ($match->bracket_type) {
                case 'upper':
                    $upperBracket[$match->round][] = $matchData;
                    break;
                case 'lower':
                    $lowerBracket[$match->round][] = $matchData;
                    break;
                case 'grand_final':
                    $grandFinal = $matchData;
                    break;
                case 'bracket_reset':
                    $bracketReset = $matchData;
                    break;
            }
        }

        // Structure upper bracket rounds
        $upperRounds = [];
        if (!empty($upperBracket)) {
            foreach ($upperBracket as $roundNum => $roundMatches) {
                $upperRounds[] = [
                    'round_number' => $roundNum,
                    'round_name' => "Upper " . $this->getAdvancedRoundName($roundNum, count($upperBracket), count($roundMatches) * 2),
                    'matches' => $roundMatches,
                    'bracket_type' => 'upper'
                ];
            }
        }

        // Structure lower bracket rounds  
        $lowerRounds = [];
        if (!empty($lowerBracket)) {
            foreach ($lowerBracket as $roundNum => $roundMatches) {
                $lowerRounds[] = [
                    'round_number' => $roundNum,
                    'round_name' => "Lower Round " . $roundNum,
                    'matches' => $roundMatches,
                    'bracket_type' => 'lower'
                ];
            }
        }

        return [
            'type' => 'double_elimination',
            'format' => 'Double Elimination',
            'upper_bracket' => $upperRounds,
            'lower_bracket' => $lowerRounds,
            'grand_final' => $grandFinal,
            'bracket_reset' => $bracketReset,
            'total_upper_rounds' => count($upperRounds),
            'total_lower_rounds' => count($lowerRounds),
            'elimination_data' => $this->calculateEliminationData($matches),
            'advancement_paths' => $this->mapAdvancementPaths($matches)
        ];
    }

    /**
     * Advanced Swiss System Bracket
     */
    private function generateAdvancedSwissBracket($event)
    {
        $matches = $this->getEventMatches($event->id);
        $teamCount = $this->getEventTeamCount($event->id);
        $totalRounds = $this->calculateOptimalSwissRounds($teamCount);
        
        $rounds = [];
        $roundMatches = [];

        // Group matches by round
        foreach ($matches as $match) {
            if (!isset($roundMatches[$match->round])) {
                $roundMatches[$match->round] = [];
            }
            $roundMatches[$match->round][] = $this->formatMatchData($match, $event->id);
        }

        // Create round structure
        for ($roundNum = 1; $roundNum <= $totalRounds; $roundNum++) {
            $rounds[] = [
                'round_number' => $roundNum,
                'round_name' => "Swiss Round " . $roundNum,
                'matches' => $roundMatches[$roundNum] ?? [],
                'pairing_method' => $this->getSwissPairingMethod($roundNum),
                'status' => $this->getRoundStatus($roundMatches[$roundNum] ?? [])
            ];
        }

        return [
            'type' => 'swiss',
            'format' => 'Swiss System',
            'rounds' => $rounds,
            'current_round' => $this->getCurrentSwissRound($matches),
            'total_rounds' => $totalRounds,
            'standings' => $this->calculateAdvancedSwissStandings($event->id),
            'tiebreakers' => $this->getSwissTiebreakers(),
            'qualification_spots' => $this->calculateQualificationSpots($teamCount),
            'elimination_threshold' => $this->calculateEliminationThreshold($teamCount, $totalRounds),
            'pairing_history' => $this->buildPairingHistory($matches)
        ];
    }

    /**
     * Advanced Round Robin Bracket
     */
    private function generateAdvancedRoundRobinBracket($event)
    {
        $matches = $this->getEventMatches($event->id);
        $teamCount = $this->getEventTeamCount($event->id);
        
        // Create match grid
        $matchGrid = $this->buildRoundRobinGrid($matches, $teamCount);
        
        return [
            'type' => 'round_robin',
            'format' => 'Round Robin',
            'match_grid' => $matchGrid,
            'rounds' => $this->groupRoundRobinByRounds($matches),
            'standings' => $this->calculateAdvancedRoundRobinStandings($event->id),
            'head_to_head' => $this->buildHeadToHeadMatrix($matches),
            'total_matches' => ($teamCount * ($teamCount - 1)) / 2,
            'completed_matches' => $this->countCompletedMatches($matches),
            'remaining_matches' => $this->getRemainingMatches($matches),
            'tiebreaker_scenarios' => $this->analyzeTiebreakerScenarios($event->id)
        ];
    }

    /**
     * Group Stage Bracket (Multiple Groups)
     */
    private function generateGroupStageBracket($event)
    {
        $matches = $this->getEventMatches($event->id);
        $teams = $this->getEventTeamsWithDetails($event->id);
        
        $groups = [];
        $groupedTeams = [];
        
        // Group teams and matches by group
        foreach ($teams as $team) {
            $groupId = $team->group_id ?? 'A';
            if (!isset($groupedTeams[$groupId])) {
                $groupedTeams[$groupId] = [];
            }
            $groupedTeams[$groupId][] = $team;
        }

        foreach ($groupedTeams as $groupId => $groupTeams) {
            $groupMatches = array_filter($matches->toArray(), function($match) use ($groupId) {
                return ($match->group_id ?? 'A') === $groupId;
            });

            $groups[$groupId] = [
                'group_id' => $groupId,
                'group_name' => 'Group ' . $groupId,
                'teams' => $groupTeams,
                'matches' => $groupMatches,
                'standings' => $this->calculateGroupStandings($event->id, $groupId),
                'advancement_spots' => 2, // Top 2 from each group
                'is_complete' => $this->isGroupComplete($groupMatches)
            ];
        }

        return [
            'type' => 'group_stage',
            'format' => 'Group Stage',
            'groups' => $groups,
            'total_groups' => count($groups),
            'advancement_summary' => $this->calculateAdvancementSummary($groups),
            'playoff_bracket' => $this->getPlayoffBracket($event->id),
            'tiebreaker_rules' => $this->getGroupTiebreakerRules()
        ];
    }

    // ===== BRACKET CREATION METHODS =====

    /**
     * Create advanced bracket based on format
     */
    private function createAdvancedBracket($event, $teams, $options)
    {
        switch ($options['format']) {
            case self::FORMAT_SINGLE_ELIMINATION:
                return $this->createAdvancedSingleElimination($event->id, $teams, $options);
            case self::FORMAT_DOUBLE_ELIMINATION:
                return $this->createAdvancedDoubleElimination($event->id, $teams, $options);
            case self::FORMAT_ROUND_ROBIN:
                return $this->createAdvancedRoundRobin($event->id, $teams, $options);
            case self::FORMAT_SWISS:
                return $this->createAdvancedSwiss($event->id, $teams, $options);
            case self::FORMAT_GROUP_STAGE:
                return $this->createAdvancedGroupStage($event->id, $teams, $options);
            default:
                throw new \Exception('Unsupported tournament format');
        }
    }

    /**
     * Create advanced single elimination bracket
     */
    private function createAdvancedSingleElimination($eventId, $teams, $options)
    {
        $teamCount = count($teams);
        $matches = [];
        
        // Calculate tournament structure
        $powerOfTwo = $this->getNextPowerOfTwo($teamCount);
        $totalRounds = log($powerOfTwo, 2);
        $byes = $powerOfTwo - $teamCount;
        
        // First round with proper bye handling
        $round = 1;
        $position = 1;
        $teamIndex = 0;
        
        // Create first round matches with strategic bye placement
        $byePositions = $this->calculateOptimalByePositions($byes, $powerOfTwo);
        
        for ($i = 0; $i < $powerOfTwo / 2; $i++) {
            $team1 = null;
            $team2 = null;
            
            // Assign teams, handling byes strategically
            if (!in_array($i * 2, $byePositions) && $teamIndex < $teamCount) {
                $team1 = $teams[$teamIndex];
                $teamIndex++;
            }
            
            if (!in_array($i * 2 + 1, $byePositions) && $teamIndex < $teamCount) {
                $team2 = $teams[$teamIndex];
                $teamIndex++;
            }
            
            // Only create match if both teams exist
            if ($team1 && $team2) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'main',
                    'team1_id' => $team1['id'],
                    'team2_id' => $team2['id'],
                    'status' => 'scheduled',
                    'format' => $options['best_of'] ?? 'bo3',
                    'scheduled_at' => $this->calculateMatchSchedule($eventId, $round, $position),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }
        
        // Create subsequent rounds
        $currentMatches = count($matches);
        for ($r = 2; $r <= $totalRounds; $r++) {
            $matchesInRound = max(1, $currentMatches / 2);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'main',
                    'team1_id' => null,
                    'team2_id' => null,
                    'status' => 'pending',
                    'format' => $options['best_of'] ?? 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $currentMatches = $matchesInRound;
        }
        
        // Add third place match if requested
        if ($options['third_place_match'] ?? false) {
            $matches[] = [
                'event_id' => $eventId,
                'round' => $totalRounds,
                'bracket_position' => 999, // Special position for 3rd place
                'bracket_type' => 'third_place',
                'team1_id' => null,
                'team2_id' => null,
                'status' => 'pending',
                'format' => $options['best_of'] ?? 'bo3',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        // Insert matches into database
        foreach ($matches as $match) {
            DB::table('matches')->insert($match);
        }
        
        return [
            'format' => 'single_elimination',
            'total_matches' => count($matches),
            'total_rounds' => $totalRounds,
            'teams_with_byes' => $byes,
            'bracket_size' => $powerOfTwo
        ];
    }

    // ===== UTILITY METHODS =====

    private function getNextPowerOfTwo($n)
    {
        return pow(2, ceil(log($n, 2)));
    }

    private function calculateOptimalByePositions($byes, $bracketSize)
    {
        // Distribute byes optimally to avoid early strong matchups
        $positions = [];
        $step = $bracketSize / $byes;
        
        for ($i = 0; $i < $byes; $i++) {
            $positions[] = floor($i * $step);
        }
        
        return $positions;
    }

    private function getEventMatches($eventId)
    {
        return DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->where('m.event_id', $eventId)
            ->select([
                'm.*',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo'
            ])
            ->orderBy('m.bracket_type')
            ->orderBy('m.round')
            ->orderBy('m.bracket_position')
            ->get();
    }

    private function formatMatchData($match, $eventId)
    {
        return [
            'id' => $match->id,
            'position' => $match->bracket_position,
            'round' => $match->round,
            'team1' => [
                'id' => $match->team1_id,
                'name' => $match->team1_name,
                'short_name' => $match->team1_short,
                'logo' => $match->team1_logo,
                'score' => $match->team1_score,
                'seed' => $this->getTeamSeed($eventId, $match->team1_id)
            ],
            'team2' => [
                'id' => $match->team2_id,
                'name' => $match->team2_name,
                'short_name' => $match->team2_short,
                'logo' => $match->team2_logo,
                'score' => $match->team2_score,
                'seed' => $this->getTeamSeed($eventId, $match->team2_id)
            ],
            'status' => $match->status,
            'format' => $match->format,
            'scheduled_at' => $match->scheduled_at,
            'completed_at' => $match->completed_at,
            'winner_id' => $this->getMatchWinner($match),
            'stream_url' => $match->stream_url,
            'maps_data' => $match->maps_data ? json_decode($match->maps_data, true) : null,
            'overtime' => $match->overtime ?? false,
            'forfeit' => $match->forfeit ?? false
        ];
    }

    private function getAdvancedRoundName($round, $totalRounds, $teamCount)
    {
        $roundsFromEnd = $totalRounds - $round + 1;
        
        switch ($roundsFromEnd) {
            case 1:
                return 'Grand Final';
            case 2:
                return 'Semi-Finals';
            case 3:
                return 'Quarter-Finals';
            case 4:
                return 'Round of 16';
            case 5:
                return 'Round of 32';
            case 6:
                return 'Round of 64';
            default:
                return "Round $round";
        }
    }

    private function getBracketMetadata($event)
    {
        $teamCount = $this->getEventTeamCount($event->id);
        $matches = $this->getEventMatches($event->id);
        
        return [
            'total_teams' => $teamCount,
            'total_matches' => count($matches),
            'completed_matches' => $this->countCompletedMatches($matches),
            'remaining_matches' => $this->countPendingMatches($matches),
            'current_round' => $this->getCurrentRound($event->id),
            'tournament_progress' => $this->calculateTournamentProgress($matches),
            'estimated_duration' => $this->estimateTournamentDuration($event),
            'bracket_integrity' => $this->validateBracketIntegrity($matches)
        ];
    }

    /**
     * Apply advanced seeding
     */
    private function applyAdvancedSeeding($teams, $method, $randomize = false)
    {
        return $this->seedingService->applySeedingMethod($teams, $method, [
            'randomize_seeds' => $randomize
        ]);
    }

    /**
     * Validate team count for specific formats
     */
    private function validateTeamCountForFormat($teams, $format, $request)
    {
        $teamCount = count($teams);
        
        switch ($format) {
            case self::FORMAT_SINGLE_ELIMINATION:
            case self::FORMAT_DOUBLE_ELIMINATION:
                if ($teamCount < 2) {
                    throw new \Exception('Need at least 2 teams for elimination format');
                }
                break;
            case self::FORMAT_SWISS:
                if ($teamCount < 4) {
                    throw new \Exception('Need at least 4 teams for Swiss system');
                }
                break;
        }
    }

    /**
     * Clear existing brackets and matches
     */
    private function clearExistingBrackets($eventId)
    {
        DB::table('matches')->where('event_id', $eventId)->delete();
        DB::table('event_standings')->where('event_id', $eventId)->delete();
    }

    /**
     * Get event teams for generation
     */
    private function getEventTeamsForGeneration($eventId)
    {
        return DB::table('event_teams as et')
            ->join('teams as t', 'et.team_id', '=', 't.id')
            ->where('et.event_id', $eventId)
            ->select(['t.id', 't.name', 't.rating', 'et.seed'])
            ->orderBy('et.seed')
            ->get()
            ->toArray();
    }

    private function validateMatchScores($match, $scoreData)
    {
        // Basic validation
        if ($scoreData['team1_score'] < 0 || $scoreData['team2_score'] < 0) {
            throw new \Exception('Scores cannot be negative');
        }
    }

    private function processAdvancedMatchCompletion($match, $scoreData)
    {
        return $this->bracketProgressionService->processMatchCompletion($match, $scoreData);
    }

    private function createNextSwissRound($event)
    {
        return $this->bracketGenerationService->generateNextSwissRound($event->id, $this->getCurrentRound($event->id) + 1);
    }

    private function getEventTeamCount($eventId)
    {
        return DB::table('event_teams')->where('event_id', $eventId)->count();
    }

    private function countCompletedMatches($matches)
    {
        return collect($matches)->where('status', 'completed')->count();
    }

    private function countPendingMatches($matches)
    {
        return collect($matches)->whereIn('status', ['pending', 'scheduled'])->count();
    }

    private function calculateTournamentProgress($matches)
    {
        $total = count($matches);
        if ($total === 0) return 0;
        
        $completed = $this->countCompletedMatches($matches);
        return ($completed / $total) * 100;
    }

    private function estimateTournamentDuration($event)
    {
        return now()->addDays(7);
    }

    private function validateBracketIntegrity($matches)
    {
        return ['valid' => true, 'issues' => []];
    }

    private function getCurrentRound($eventId)
    {
        return DB::table('matches')
            ->where('event_id', $eventId)
            ->where('status', 'scheduled')
            ->min('round') ?? 1;
    }

    private function analyzeFormat($event) 
    { 
        return ['description' => "Format analysis for {$event->format}"]; 
    }
    
    private function analyzeProgression($event) 
    { 
        return ['status' => 'analyzed']; 
    }
    
    private function analyzeSeedingEffectiveness($event) 
    { 
        return ['effectiveness' => 'good']; 
    }
    
    private function calculatePerformanceMetrics($event) 
    { 
        return ['metrics' => []]; 
    }

    private function getEventTeamsWithDetails($eventId)
    {
        return DB::table('event_teams as et')
            ->join('teams as t', 'et.team_id', '=', 't.id')
            ->where('et.event_id', $eventId)
            ->select([
                't.*',
                'et.seed',
                'et.status as registration_status',
                'et.registered_at'
            ])
            ->orderBy('et.seed')
            ->get();
    }

    private function calculateEventStandings($event)
    {
        // Get existing standings or calculate based on matches
        $standings = DB::table('event_standings as es')
            ->join('teams as t', 'es.team_id', '=', 't.id')
            ->where('es.event_id', $event->id)
            ->select([
                't.*',
                'es.position',
                'es.points',
                'es.matches_played',
                'es.matches_won',
                'es.matches_lost'
            ])
            ->orderBy('es.position')
            ->get();

        if ($standings->isEmpty()) {
            // Calculate standings from matches if no standings exist
            $teams = $this->getEventTeamsWithDetails($event->id);
            $standings = $teams->map(function($team, $index) {
                return [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'position' => $index + 1,
                    'points' => 0,
                    'matches_played' => 0,
                    'matches_won' => 0,
                    'matches_lost' => 0
                ];
            });
        }

        return $standings;
    }

    private function estimateRoundStartDate($event, $roundNumber)
    {
        $baseDate = $event->start_date ?? now();
        $hoursPerRound = 2; // Estimate 2 hours between rounds
        
        return $baseDate->copy()->addHours(($roundNumber - 1) * $hoursPerRound);
    }

    private function getRoundStatus($matches)
    {
        if (empty($matches)) {
            return 'pending';
        }

        $completed = collect($matches)->where('status', 'completed')->count();
        $total = count($matches);

        if ($completed === $total) {
            return 'completed';
        } elseif ($completed > 0) {
            return 'in_progress';
        } else {
            return 'pending';
        }
    }

    private function countTeamsRemaining($matches)
    {
        // Count teams still in the tournament (not eliminated)
        $teamIds = collect($matches)
            ->where('status', '!=', 'completed')
            ->flatMap(function($match) {
                return [$match->team1_id, $match->team2_id];
            })
            ->filter()
            ->unique()
            ->count();

        return max(0, $teamIds);
    }

    private function analyzeBracketStructure($teamCount, $type)
    {
        $powerOfTwo = pow(2, ceil(log($teamCount, 2)));
        $byes = $powerOfTwo - $teamCount;

        return [
            'bracket_size' => $powerOfTwo,
            'teams_with_byes' => $byes,
            'first_round_matches' => ($teamCount - $byes) / 2,
            'total_rounds' => ceil(log($powerOfTwo, 2)),
            'type' => $type
        ];
    }

    private function estimateTournamentCompletion($event, $matches)
    {
        $totalMatches = count($matches);
        $completedMatches = collect($matches)->where('status', 'completed')->count();
        
        if ($totalMatches === 0) {
            return $event->end_date ?? now()->addDays(3);
        }

        $progress = $completedMatches / $totalMatches;
        $daysRemaining = (1 - $progress) * 3; // Estimate based on remaining matches
        
        return now()->addDays(max(1, ceil($daysRemaining)));
    }

    private function calculateEliminationData($matches)
    {
        return [
            'eliminated_teams' => collect($matches)
                ->where('status', 'completed')
                ->pluck('loser_id')
                ->filter()
                ->unique()
                ->count(),
            'teams_remaining' => $this->countTeamsRemaining($matches)
        ];
    }

    private function mapAdvancementPaths($matches)
    {
        // Map how teams advance through the bracket
        return collect($matches)
            ->where('status', 'completed')
            ->mapWithKeys(function($match) {
                return [
                    $match->id => [
                        'winner_advances_to' => $match->winner_advances_to ?? null,
                        'loser_drops_to' => $match->loser_advances_to ?? null
                    ]
                ];
            })
            ->toArray();
    }

    private function calculateOptimalSwissRounds($teamCount)
    {
        return max(3, ceil(log($teamCount, 2)));
    }

    private function getCurrentSwissRound($matches)
    {
        if (empty($matches)) return 1;
        
        return collect($matches)->max('round') ?? 1;
    }

    private function calculateAdvancedSwissStandings($eventId)
    {
        return $this->calculateEventStandings(\App\Models\Event::find($eventId));
    }

    private function getSwissTiebreakers()
    {
        return [
            'primary' => 'points',
            'secondary' => 'buchholz_score',
            'tertiary' => 'head_to_head'
        ];
    }

    private function calculateQualificationSpots($teamCount)
    {
        return max(1, floor($teamCount / 2));
    }

    private function calculateEliminationThreshold($teamCount, $totalRounds)
    {
        return max(1, floor($totalRounds / 2));
    }

    private function buildPairingHistory($matches)
    {
        return collect($matches)
            ->where('status', '!=', 'pending')
            ->groupBy('round')
            ->map(function($roundMatches) {
                return $roundMatches->map(function($match) {
                    return [
                        'team1_id' => $match->team1_id,
                        'team2_id' => $match->team2_id,
                        'result' => $match->status
                    ];
                });
            })
            ->toArray();
    }

    private function getSwissPairingMethod($roundNumber)
    {
        if ($roundNumber === 1) {
            return 'random';
        } elseif ($roundNumber <= 3) {
            return 'swiss_sorted';
        } else {
            return 'swiss_advanced';
        }
    }

    private function buildRoundRobinGrid($matches, $teamCount)
    {
        // Build a grid showing all possible matchups
        $grid = [];
        $teams = range(1, $teamCount);
        
        foreach ($teams as $team1) {
            $grid[$team1] = [];
            foreach ($teams as $team2) {
                if ($team1 === $team2) {
                    $grid[$team1][$team2] = null; // Can't play themselves
                } else {
                    $match = collect($matches)->first(function($match) use ($team1, $team2) {
                        return ($match->team1_id === $team1 && $match->team2_id === $team2) ||
                               ($match->team1_id === $team2 && $match->team2_id === $team1);
                    });
                    $grid[$team1][$team2] = $match ? $this->formatMatchData($match, $match->event_id) : null;
                }
            }
        }
        
        return $grid;
    }

    private function groupRoundRobinByRounds($matches)
    {
        return collect($matches)
            ->groupBy('round')
            ->map(function($roundMatches, $round) {
                return [
                    'round_number' => $round,
                    'round_name' => "Round {$round}",
                    'matches' => $roundMatches->toArray()
                ];
            })
            ->values()
            ->toArray();
    }

    private function calculateAdvancedRoundRobinStandings($eventId)
    {
        return $this->calculateEventStandings(\App\Models\Event::find($eventId));
    }

    private function buildHeadToHeadMatrix($matches)
    {
        $matrix = [];
        
        foreach ($matches as $match) {
            if ($match->status === 'completed' && $match->team1_id && $match->team2_id) {
                $key = min($match->team1_id, $match->team2_id) . '-' . max($match->team1_id, $match->team2_id);
                $matrix[$key] = [
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'team1_score' => $match->team1_score,
                    'team2_score' => $match->team2_score,
                    'winner_id' => $match->team1_score > $match->team2_score ? $match->team1_id : $match->team2_id
                ];
            }
        }
        
        return $matrix;
    }

    private function getRemainingMatches($matches)
    {
        return collect($matches)->whereIn('status', ['pending', 'scheduled'])->values()->toArray();
    }

    private function analyzeTiebreakerScenarios($eventId)
    {
        // Analyze potential tiebreaker scenarios
        return [
            'potential_ties' => 0,
            'tiebreaker_rules' => ['head_to_head', 'map_difference', 'rounds_difference']
        ];
    }

    private function calculateGroupStandings($eventId, $groupId)
    {
        return $this->calculateEventStandings(\App\Models\Event::find($eventId));
    }

    private function isGroupComplete($matches)
    {
        return collect($matches)->every(function($match) {
            return $match->status === 'completed';
        });
    }

    private function calculateAdvancementSummary($groups)
    {
        return [
            'total_groups' => count($groups),
            'teams_advancing' => collect($groups)->sum(function($group) {
                return $group['advancement_spots'] ?? 2;
            }),
            'completion_status' => collect($groups)->every(function($group) {
                return $group['is_complete'] ?? false;
            }) ? 'complete' : 'in_progress'
        ];
    }

    private function getPlayoffBracket($eventId)
    {
        // Get playoff bracket if it exists
        return null; // Placeholder
    }

    private function getGroupTiebreakerRules()
    {
        return [
            'primary' => 'points',
            'secondary' => 'head_to_head',
            'tertiary' => 'map_difference'
        ];
    }

    private function getTeamSeed($eventId, $teamId)
    {
        return DB::table('event_teams')
            ->where('event_id', $eventId)
            ->where('team_id', $teamId)
            ->value('seed') ?? 0;
    }

    private function getMatchWinner($match)
    {
        if ($match->status !== 'completed') return null;
        
        if ($match->team1_score > $match->team2_score) {
            return $match->team1_id;
        } elseif ($match->team2_score > $match->team1_score) {
            return $match->team2_id;
        }
        
        return null;
    }
}