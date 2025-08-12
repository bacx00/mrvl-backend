<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentPhase;
use App\Models\BracketMatch;
use App\Models\BracketStage;
use App\Models\BracketPosition;
use App\Models\BracketSeeding;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Comprehensive Tournament Generation Service
 * 
 * Following Liquipedia Marvel Rivals tournament structure and conventions:
 * - Single Elimination (Standard bracket)
 * - Double Elimination (Upper/Lower bracket structure)  
 * - Swiss System (Point-based rounds)
 * - Round Robin (All-vs-all format)
 * - Group Stage + Playoffs (GSL format)
 * - GSL Format (Groups into single elimination)
 * 
 * Implements proper seeding algorithms, bracket progression logic,
 * and match advancement rules for competitive integrity.
 */
class ComprehensiveTournamentGenerator
{
    protected $seedingService;
    protected $bracketService;
    
    public function __construct(
        SeedingService $seedingService = null,
        BracketGenerationService $bracketService = null
    ) {
        $this->seedingService = $seedingService;
        $this->bracketService = $bracketService;
    }

    /**
     * Create a complete tournament with all bracket structures
     * Following Liquipedia naming conventions and formats
     */
    public function createCompleteTournament(array $config): Tournament
    {
        try {
            DB::beginTransaction();

            // Create the tournament
            $tournament = $this->createBaseTournament($config);
            
            // Generate seeding based on tournament type
            $this->generateTournamentSeeding($tournament, $config['teams'] ?? []);
            
            // Create bracket structure based on format
            $this->createBracketStructure($tournament, $config);
            
            // Set up tournament phases and progression
            $this->setupTournamentPhases($tournament, $config);
            
            // Generate initial matches
            $this->generateInitialMatches($tournament);
            
            DB::commit();
            
            Log::info('Complete tournament generated', [
                'tournament_id' => $tournament->id,
                'format' => $tournament->format,
                'team_count' => count($config['teams'] ?? [])
            ]);
            
            return $tournament;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create base tournament structure
     */
    protected function createBaseTournament(array $config): Tournament
    {
        $tournament = Tournament::create([
            'name' => $config['name'],
            'slug' => $config['slug'] ?? \Str::slug($config['name']),
            'type' => $config['type'] ?? 'tournament',
            'format' => $config['format'],
            'status' => 'draft',
            'description' => $config['description'] ?? null,
            'region' => $config['region'] ?? 'global',
            'prize_pool' => $config['prize_pool'] ?? 0,
            'currency' => $config['currency'] ?? 'USD',
            'team_count' => count($config['teams'] ?? []),
            'max_teams' => $config['max_teams'] ?? count($config['teams'] ?? []),
            'min_teams' => $config['min_teams'] ?? 2,
            'start_date' => Carbon::parse($config['start_date'] ?? now()->addDay()),
            'end_date' => Carbon::parse($config['end_date'] ?? now()->addDays(3)),
            'registration_start' => Carbon::parse($config['registration_start'] ?? now()),
            'registration_end' => Carbon::parse($config['registration_end'] ?? now()->addHours(12)),
            'timezone' => $config['timezone'] ?? 'UTC',
            'organizer_id' => $config['organizer_id'] ?? 1,
            'settings' => $config['settings'] ?? [],
            'rules' => $config['rules'] ?? [],
            'qualification_settings' => $this->getDefaultQualificationSettings($config['format']),
            'match_format_settings' => $config['match_formats'] ?? $this->getDefaultMatchFormats($config['format']),
            'map_pool' => $config['map_pool'] ?? $this->getDefaultMapPool(),
            'current_phase' => 'registration',
            'featured' => $config['featured'] ?? false,
            'public' => $config['public'] ?? true
        ]);

        return $tournament;
    }

    /**
     * Generate tournament seeding using various algorithms
     */
    protected function generateTournamentSeeding(Tournament $tournament, array $teams): void
    {
        $seededTeams = $this->applySeedingAlgorithm($teams, $tournament->format);
        
        // Register teams with seeding
        foreach ($seededTeams as $index => $teamData) {
            $team = is_array($teamData) ? Team::find($teamData['id']) : Team::find($teamData);
            
            if ($team) {
                $tournament->teams()->attach($team->id, [
                    'seed' => $index + 1,
                    'status' => 'registered',
                    'registered_at' => now(),
                    'swiss_wins' => 0,
                    'swiss_losses' => 0,
                    'swiss_score' => 0,
                    'swiss_buchholz' => 0,
                    'bracket_position' => $index + 1,
                    'elimination_round' => null,
                    'placement' => null,
                    'points_earned' => 0
                ]);
            }
        }
        
        // Store seeding data
        $tournament->seeding_data = $seededTeams;
        $tournament->save();
    }

    /**
     * Apply appropriate seeding algorithm based on tournament format
     */
    protected function applySeedingAlgorithm(array $teams, string $format): array
    {
        switch ($format) {
            case 'single_elimination':
            case 'double_elimination':
                return $this->standardTournamentSeeding($teams);
                
            case 'swiss':
                return $this->swissSystemSeeding($teams);
                
            case 'round_robin':
                return $this->roundRobinSeeding($teams);
                
            case 'group_stage_playoffs':
            case 'gsl':
                return $this->groupStageSeeding($teams);
                
            default:
                return $this->randomSeeding($teams);
        }
    }

    /**
     * Standard tournament seeding (1 vs lowest, 2 vs second-lowest, etc.)
     */
    protected function standardTournamentSeeding(array $teams): array
    {
        // Sort by ranking/rating if available
        usort($teams, function($a, $b) {
            $aRating = is_array($a) ? ($a['rating'] ?? 1000) : 1000;
            $bRating = is_array($b) ? ($b['rating'] ?? 1000) : 1000;
            return $bRating <=> $aRating; // Higher rating first
        });
        
        // Apply standard tournament seeding pattern
        $seeded = [];
        $count = count($teams);
        
        // Power of 2 adjustment for perfect bracket
        $bracketSize = $this->getNextPowerOfTwo($count);
        
        for ($i = 0; $i < $count; $i++) {
            $seeded[] = $teams[$i];
        }
        
        // Fill remaining spots with byes if needed
        for ($i = $count; $i < $bracketSize; $i++) {
            $seeded[] = ['id' => null, 'name' => 'BYE', 'is_bye' => true];
        }
        
        return $seeded;
    }

    /**
     * Swiss System seeding (random for first round)
     */
    protected function swissSystemSeeding(array $teams): array
    {
        // Swiss starts with random pairing, but we can seed by rating
        $seeded = $teams;
        shuffle($seeded);
        return $seeded;
    }

    /**
     * Round Robin seeding (balanced distribution)
     */
    protected function roundRobinSeeding(array $teams): array
    {
        // Alternate high and low rated teams for balanced schedule
        usort($teams, function($a, $b) {
            $aRating = is_array($a) ? ($a['rating'] ?? 1000) : 1000;
            $bRating = is_array($b) ? ($b['rating'] ?? 1000) : 1000;
            return $bRating <=> $aRating;
        });
        
        return $teams;
    }

    /**
     * Group stage seeding (distribute strong teams across groups)
     */
    protected function groupStageSeeding(array $teams): array
    {
        // Sort by strength and distribute evenly
        usort($teams, function($a, $b) {
            $aRating = is_array($a) ? ($a['rating'] ?? 1000) : 1000;
            $bRating = is_array($b) ? ($b['rating'] ?? 1000) : 1000;
            return $bRating <=> $aRating;
        });
        
        return $teams;
    }

    /**
     * Random seeding fallback
     */
    protected function randomSeeding(array $teams): array
    {
        shuffle($teams);
        return $teams;
    }

    /**
     * Create bracket structure based on tournament format
     */
    protected function createBracketStructure(Tournament $tournament, array $config): void
    {
        switch ($tournament->format) {
            case 'single_elimination':
                $this->createSingleEliminationStructure($tournament);
                break;
                
            case 'double_elimination':
                $this->createDoubleEliminationStructure($tournament);
                break;
                
            case 'swiss':
                $this->createSwissSystemStructure($tournament);
                break;
                
            case 'round_robin':
                $this->createRoundRobinStructure($tournament);
                break;
                
            case 'group_stage_playoffs':
                $this->createGroupStagePlayoffsStructure($tournament, $config);
                break;
                
            case 'gsl':
                $this->createGSLStructure($tournament, $config);
                break;
                
            default:
                throw new \Exception("Unsupported tournament format: {$tournament->format}");
        }
    }

    /**
     * Create Single Elimination bracket structure
     */
    protected function createSingleEliminationStructure(Tournament $tournament): void
    {
        $teamCount = $tournament->team_count;
        $rounds = ceil(log($teamCount, 2));
        
        // Create main bracket stage
        $stage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Main Bracket',
            'type' => 'single_elimination',
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $rounds,
            'settings' => [
                'elimination_type' => 'single',
                'bracket_size' => $this->getNextPowerOfTwo($teamCount)
            ]
        ]);

        // Create bracket positions
        $this->createEliminationBracketPositions($stage, $teamCount, 'single');
        
        $tournament->bracket_data = [
            'type' => 'single_elimination',
            'stages' => [$stage->id],
            'total_rounds' => $rounds,
            'bracket_size' => $this->getNextPowerOfTwo($teamCount)
        ];
        $tournament->save();
    }

    /**
     * Create Double Elimination bracket structure  
     */
    protected function createDoubleEliminationStructure(Tournament $tournament): void
    {
        $teamCount = $tournament->team_count;
        $upperRounds = ceil(log($teamCount, 2));
        $lowerRounds = ($upperRounds * 2) - 1;
        
        // Create Upper Bracket stage
        $upperStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Upper Bracket',
            'type' => 'upper_bracket',
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $upperRounds,
            'settings' => [
                'elimination_type' => 'upper',
                'bracket_size' => $this->getNextPowerOfTwo($teamCount)
            ]
        ]);

        // Create Lower Bracket stage
        $lowerStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Lower Bracket',
            'type' => 'lower_bracket',
            'stage_order' => 2,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $lowerRounds,
            'settings' => [
                'elimination_type' => 'lower',
                'feeds_from' => $upperStage->id
            ]
        ]);

        // Create Grand Final stage
        $grandFinalStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Grand Final',
            'type' => 'grand_final',
            'stage_order' => 3,
            'status' => 'pending',
            'max_teams' => 2,
            'current_round' => 1,
            'total_rounds' => 1,
            'settings' => [
                'bo_format' => 'bo5',
                'bracket_reset' => true
            ]
        ]);

        // Create bracket positions
        $this->createEliminationBracketPositions($upperStage, $teamCount, 'double_upper');
        $this->createEliminationBracketPositions($lowerStage, $teamCount, 'double_lower');

        $tournament->bracket_data = [
            'type' => 'double_elimination',
            'stages' => [$upperStage->id, $lowerStage->id, $grandFinalStage->id],
            'upper_rounds' => $upperRounds,
            'lower_rounds' => $lowerRounds,
            'bracket_size' => $this->getNextPowerOfTwo($teamCount)
        ];
        $tournament->save();
    }

    /**
     * Create Swiss System structure
     */
    protected function createSwissSystemStructure(Tournament $tournament): void
    {
        $teamCount = $tournament->team_count;
        $rounds = $this->calculateSwissRounds($teamCount);
        
        // Create Swiss stage
        $stage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Swiss Rounds',
            'type' => 'swiss',
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $rounds,
            'settings' => [
                'swiss_rounds' => $rounds,
                'wins_to_qualify' => ceil($rounds * 0.6),
                'losses_to_eliminate' => floor($rounds * 0.6),
                'buchholz_tiebreaker' => true
            ]
        ]);

        // Create potential playoff stage
        $playoffStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Swiss Playoffs',
            'type' => 'single_elimination',
            'stage_order' => 2,
            'status' => 'pending',
            'max_teams' => 8, // Top 8 from Swiss
            'current_round' => 1,
            'total_rounds' => 3,
            'settings' => [
                'qualified_from' => $stage->id
            ]
        ]);

        $tournament->bracket_data = [
            'type' => 'swiss',
            'stages' => [$stage->id, $playoffStage->id],
            'swiss_rounds' => $rounds,
            'playoff_size' => 8
        ];
        $tournament->save();
    }

    /**
     * Create Round Robin structure
     */
    protected function createRoundRobinStructure(Tournament $tournament): void
    {
        $teamCount = $tournament->team_count;
        $rounds = $teamCount - 1;
        $totalMatches = ($teamCount * ($teamCount - 1)) / 2;
        
        // Create Round Robin stage
        $stage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Round Robin',
            'type' => 'round_robin',
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $rounds,
            'settings' => [
                'total_matches' => $totalMatches,
                'points_for_win' => 3,
                'points_for_tie' => 1,
                'points_for_loss' => 0
            ]
        ]);

        $tournament->bracket_data = [
            'type' => 'round_robin',
            'stages' => [$stage->id],
            'total_rounds' => $rounds,
            'total_matches' => $totalMatches
        ];
        $tournament->save();
    }

    /**
     * Create Group Stage + Playoffs structure
     */
    protected function createGroupStagePlayoffsStructure(Tournament $tournament, array $config): void
    {
        $teamCount = $tournament->team_count;
        $groupSize = $config['group_size'] ?? 4;
        $groupCount = ceil($teamCount / $groupSize);
        $playoffSize = $groupCount * 2; // Top 2 from each group
        
        $stages = [];
        
        // Create group stages
        for ($i = 0; $i < $groupCount; $i++) {
            $groupLetter = chr(65 + $i); // A, B, C, D...
            
            $stage = BracketStage::create([
                'tournament_id' => $tournament->id,
                'name' => "Group {$groupLetter}",
                'type' => 'group_stage',
                'stage_order' => $i + 1,
                'status' => 'pending',
                'max_teams' => $groupSize,
                'current_round' => 1,
                'total_rounds' => $groupSize - 1,
                'settings' => [
                    'group_id' => $groupLetter,
                    'advancement_count' => 2,
                    'points_for_win' => 3,
                    'points_for_tie' => 1
                ]
            ]);
            
            $stages[] = $stage->id;
        }
        
        // Create playoff stage
        $playoffStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Playoffs',
            'type' => 'single_elimination',
            'stage_order' => $groupCount + 1,
            'status' => 'pending',
            'max_teams' => $playoffSize,
            'current_round' => 1,
            'total_rounds' => ceil(log($playoffSize, 2)),
            'settings' => [
                'qualified_from_groups' => true,
                'advancement_per_group' => 2
            ]
        ]);
        
        $stages[] = $playoffStage->id;

        $tournament->bracket_data = [
            'type' => 'group_stage_playoffs',
            'stages' => $stages,
            'group_count' => $groupCount,
            'group_size' => $groupSize,
            'playoff_size' => $playoffSize
        ];
        $tournament->save();
    }

    /**
     * Create GSL (Groups + Single Elimination) structure
     */
    protected function createGSLStructure(Tournament $tournament, array $config): void
    {
        $teamCount = $tournament->team_count;
        $groupSize = $config['gsl_group_size'] ?? 4;
        $groupCount = ceil($teamCount / $groupSize);
        
        $stages = [];
        
        // Create GSL group stages (unique format: dual-elimination groups)
        for ($i = 0; $i < $groupCount; $i++) {
            $groupLetter = chr(65 + $i);
            
            // Winners bracket within group
            $winnerStage = BracketStage::create([
                'tournament_id' => $tournament->id,
                'name' => "Group {$groupLetter} Winners",
                'type' => 'gsl_winners',
                'stage_order' => ($i * 2) + 1,
                'status' => 'pending',
                'max_teams' => $groupSize,
                'current_round' => 1,
                'total_rounds' => 2,
                'settings' => [
                    'group_id' => $groupLetter,
                    'advancement_count' => 1
                ]
            ]);
            
            // Losers bracket within group  
            $loserStage = BracketStage::create([
                'tournament_id' => $tournament->id,
                'name' => "Group {$groupLetter} Losers",
                'type' => 'gsl_losers',
                'stage_order' => ($i * 2) + 2,
                'status' => 'pending',
                'max_teams' => $groupSize,
                'current_round' => 1,
                'total_rounds' => 2,
                'settings' => [
                    'group_id' => $groupLetter,
                    'advancement_count' => 1,
                    'feeds_from' => $winnerStage->id
                ]
            ]);
            
            $stages[] = $winnerStage->id;
            $stages[] = $loserStage->id;
        }
        
        // Final bracket (qualified teams)
        $finalStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'GSL Finals',
            'type' => 'single_elimination',
            'stage_order' => ($groupCount * 2) + 1,
            'status' => 'pending',
            'max_teams' => $groupCount * 2, // 2 from each group
            'current_round' => 1,
            'total_rounds' => ceil(log($groupCount * 2, 2)),
            'settings' => [
                'qualified_from_gsl' => true
            ]
        ]);
        
        $stages[] = $finalStage->id;

        $tournament->bracket_data = [
            'type' => 'gsl',
            'stages' => $stages,
            'group_count' => $groupCount,
            'group_size' => $groupSize,
            'final_size' => $groupCount * 2
        ];
        $tournament->save();
    }

    /**
     * Create bracket positions for elimination formats
     */
    protected function createEliminationBracketPositions(BracketStage $stage, int $teamCount, string $type): void
    {
        $bracketSize = $this->getNextPowerOfTwo($teamCount);
        $rounds = $stage->total_rounds;
        
        for ($round = 1; $round <= $rounds; $round++) {
            $matchesInRound = $bracketSize / pow(2, $round);
            
            for ($position = 1; $position <= $matchesInRound; $position++) {
                BracketPosition::create([
                    'bracket_stage_id' => $stage->id,
                    'round' => $round,
                    'position' => $position,
                    'team_id' => null,
                    'status' => 'empty',
                    'position_type' => $this->getPositionType($round, $rounds),
                    'advancement_rule' => $this->getAdvancementRule($type, $round, $rounds)
                ]);
            }
        }
    }

    /**
     * Setup tournament phases and progression rules
     */
    protected function setupTournamentPhases(Tournament $tournament, array $config): void
    {
        $phases = $this->getPhaseStructure($tournament->format);
        
        foreach ($phases as $index => $phaseData) {
            TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => $phaseData['name'],
                'slug' => \Str::slug($phaseData['name']),
                'phase_type' => $phaseData['type'],
                'phase_order' => $index + 1,
                'status' => $index === 0 ? 'pending' : 'not_started',
                'settings' => $phaseData['settings'] ?? [],
                'progression_rule' => $phaseData['progression'] ?? 'automatic'
            ]);
        }
    }

    /**
     * Generate initial matches based on tournament format
     */
    protected function generateInitialMatches(Tournament $tournament): void
    {
        switch ($tournament->format) {
            case 'single_elimination':
                $this->generateSingleEliminationMatches($tournament);
                break;
                
            case 'double_elimination':
                $this->generateDoubleEliminationMatches($tournament);
                break;
                
            case 'swiss':
                $this->generateSwissFirstRound($tournament);
                break;
                
            case 'round_robin':
                $this->generateRoundRobinMatches($tournament);
                break;
                
            case 'group_stage_playoffs':
                $this->generateGroupStageMatches($tournament);
                break;
                
            case 'gsl':
                $this->generateGSLMatches($tournament);
                break;
        }
    }

    /**
     * Generate Single Elimination matches
     */
    protected function generateSingleEliminationMatches(Tournament $tournament): void
    {
        $stage = $tournament->bracketStages()->where('type', 'single_elimination')->first();
        if (!$stage) return;

        $teams = $tournament->teams()->orderBy('pivot_seed')->get();
        $seededTeams = $this->seedTeamsForBracket($teams->toArray());
        
        // Create first round matches
        for ($i = 0; $i < count($seededTeams); $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                BracketMatch::create([
                    'tournament_id' => $tournament->id,
                    'bracket_stage_id' => $stage->id,
                    'round_number' => 1,
                    'match_number' => floor($i / 2) + 1,
                    'team1_id' => $seededTeams[$i]['id'],
                    'team2_id' => $seededTeams[$i + 1]['id'],
                    'status' => 'pending',
                    'match_format' => $tournament->match_format_settings['playoffs'] ?? 'bo3',
                    'scheduled_at' => $this->calculateMatchTime($tournament, 1, floor($i / 2) + 1)
                ]);
            }
        }
    }

    /**
     * Generate Double Elimination matches
     */
    protected function generateDoubleEliminationMatches(Tournament $tournament): void
    {
        $upperStage = $tournament->bracketStages()->where('type', 'upper_bracket')->first();
        if (!$upperStage) return;

        $teams = $tournament->teams()->orderBy('pivot_seed')->get();
        $seededTeams = $this->seedTeamsForBracket($teams->toArray());
        
        // Create upper bracket first round
        for ($i = 0; $i < count($seededTeams); $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                BracketMatch::create([
                    'tournament_id' => $tournament->id,
                    'bracket_stage_id' => $upperStage->id,
                    'round_number' => 1,
                    'match_number' => floor($i / 2) + 1,
                    'team1_id' => $seededTeams[$i]['id'],
                    'team2_id' => $seededTeams[$i + 1]['id'],
                    'status' => 'pending',
                    'match_format' => $tournament->match_format_settings['playoffs'] ?? 'bo3',
                    'scheduled_at' => $this->calculateMatchTime($tournament, 1, floor($i / 2) + 1),
                    'progression_rules' => [
                        'winner_advances_to' => 'upper_bracket_round_2',
                        'loser_drops_to' => 'lower_bracket_round_1'
                    ]
                ]);
            }
        }
    }

    /**
     * Generate Swiss System first round
     */
    protected function generateSwissFirstRound(Tournament $tournament): void
    {
        $stage = $tournament->bracketStages()->where('type', 'swiss')->first();
        if (!$stage) return;

        $teams = $tournament->teams()->get()->toArray();
        shuffle($teams); // Random first round pairing
        
        for ($i = 0; $i < count($teams); $i += 2) {
            if (isset($teams[$i + 1])) {
                BracketMatch::create([
                    'tournament_id' => $tournament->id,
                    'bracket_stage_id' => $stage->id,
                    'round_number' => 1,
                    'match_number' => floor($i / 2) + 1,
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$i + 1]['id'],
                    'status' => 'pending',
                    'match_format' => $tournament->match_format_settings['swiss'] ?? 'bo1',
                    'scheduled_at' => $this->calculateMatchTime($tournament, 1, floor($i / 2) + 1),
                    'swiss_round' => 1
                ]);
            }
        }
    }

    /**
     * Generate Round Robin matches
     */
    protected function generateRoundRobinMatches(Tournament $tournament): void
    {
        $stage = $tournament->bracketStages()->where('type', 'round_robin')->first();
        if (!$stage) return;

        $teams = $tournament->teams()->get()->toArray();
        $matchNumber = 1;
        
        // Generate all possible pairings
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $round = $this->calculateRoundRobinRound($i, $j, count($teams));
                
                BracketMatch::create([
                    'tournament_id' => $tournament->id,
                    'bracket_stage_id' => $stage->id,
                    'round_number' => $round,
                    'match_number' => $matchNumber,
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$j]['id'],
                    'status' => 'pending',
                    'match_format' => $tournament->match_format_settings['group_stage'] ?? 'bo3',
                    'scheduled_at' => $this->calculateMatchTime($tournament, $round, $matchNumber)
                ]);
                $matchNumber++;
            }
        }
    }

    /**
     * Generate Group Stage matches
     */
    protected function generateGroupStageMatches(Tournament $tournament): void
    {
        $groupStages = $tournament->bracketStages()->where('type', 'group_stage')->get();
        $teams = $tournament->teams()->orderBy('pivot_seed')->get();
        $groupSize = 4; // Default group size
        
        foreach ($groupStages as $index => $stage) {
            $groupTeams = $teams->slice($index * $groupSize, $groupSize);
            $matchNumber = 1;
            
            // Generate round robin within group
            foreach ($groupTeams as $i => $team1) {
                foreach ($groupTeams->slice($i + 1) as $j => $team2) {
                    BracketMatch::create([
                        'tournament_id' => $tournament->id,
                        'bracket_stage_id' => $stage->id,
                        'round_number' => $i + $j + 1,
                        'match_number' => $matchNumber,
                        'team1_id' => $team1->id,
                        'team2_id' => $team2->id,
                        'status' => 'pending',
                        'match_format' => $tournament->match_format_settings['group_stage'] ?? 'bo3',
                        'scheduled_at' => $this->calculateMatchTime($tournament, $i + $j + 1, $matchNumber)
                    ]);
                    $matchNumber++;
                }
            }
        }
    }

    /**
     * Generate GSL matches
     */
    protected function generateGSLMatches(Tournament $tournament): void
    {
        $winnerStages = $tournament->bracketStages()->where('type', 'gsl_winners')->get();
        $teams = $tournament->teams()->orderBy('pivot_seed')->get();
        $groupSize = 4;
        
        foreach ($winnerStages as $index => $stage) {
            $groupTeams = $teams->slice($index * $groupSize, $groupSize)->toArray();
            
            // Initial winners bracket matches (1v2, 3v4)
            BracketMatch::create([
                'tournament_id' => $tournament->id,
                'bracket_stage_id' => $stage->id,
                'round_number' => 1,
                'match_number' => 1,
                'team1_id' => $groupTeams[0]['id'],
                'team2_id' => $groupTeams[1]['id'],
                'status' => 'pending',
                'match_format' => $tournament->match_format_settings['group_stage'] ?? 'bo3',
                'scheduled_at' => $this->calculateMatchTime($tournament, 1, 1)
            ]);
            
            BracketMatch::create([
                'tournament_id' => $tournament->id,
                'bracket_stage_id' => $stage->id,
                'round_number' => 1,
                'match_number' => 2,
                'team1_id' => $groupTeams[2]['id'],
                'team2_id' => $groupTeams[3]['id'],
                'status' => 'pending',
                'match_format' => $tournament->match_format_settings['group_stage'] ?? 'bo3',
                'scheduled_at' => $this->calculateMatchTime($tournament, 1, 2)
            ]);
        }
    }

    // Helper Methods

    protected function getNextPowerOfTwo(int $number): int
    {
        return pow(2, ceil(log($number, 2)));
    }

    protected function calculateSwissRounds(int $teamCount): int
    {
        return max(3, ceil(log($teamCount, 2)));
    }

    protected function getDefaultQualificationSettings(string $format): array
    {
        return match($format) {
            'swiss' => [
                'swiss_rounds' => 5,
                'swiss_wins_required' => 3,
                'swiss_losses_eliminated' => 3,
                'qualified_count' => 8
            ],
            'group_stage_playoffs', 'gsl' => [
                'group_size' => 4,
                'teams_advance_per_group' => 2,
                'tiebreaker_rules' => ['head_to_head', 'map_differential', 'round_differential']
            ],
            default => []
        };
    }

    protected function getDefaultMatchFormats(string $format): array
    {
        return match($format) {
            'swiss' => [
                'swiss' => 'bo1',
                'playoffs' => 'bo3'
            ],
            'group_stage_playoffs', 'gsl' => [
                'group_stage' => 'bo3',
                'playoffs' => 'bo3',
                'finals' => 'bo5'
            ],
            default => [
                'default' => 'bo3',
                'finals' => 'bo5'
            ]
        };
    }

    protected function getDefaultMapPool(): array
    {
        return [
            'Klyntar',
            'Birnin T\'Challa',
            'Sanctum Sanctorum',
            'Stark Tower',
            'Midtown',
            'Asgard',
            'Tokyo 2099',
            'Intergalactic Empire of Wakanda',
            'Yggsgard: Seed of Memory',
            'Yggsgard: Path of Exile'
        ];
    }

    protected function getPhaseStructure(string $format): array
    {
        return match($format) {
            'single_elimination' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Playoffs', 'type' => 'playoffs']
            ],
            'double_elimination' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Upper Bracket', 'type' => 'upper_bracket'],
                ['name' => 'Lower Bracket', 'type' => 'lower_bracket'],
                ['name' => 'Grand Final', 'type' => 'grand_final']
            ],
            'swiss' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Swiss Rounds', 'type' => 'swiss_rounds'],
                ['name' => 'Playoffs', 'type' => 'playoffs']
            ],
            'round_robin' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Round Robin', 'type' => 'round_robin']
            ],
            'group_stage_playoffs' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Group Stage', 'type' => 'group_stage'],
                ['name' => 'Playoffs', 'type' => 'playoffs']
            ],
            'gsl' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'GSL Groups', 'type' => 'gsl_groups'],
                ['name' => 'Finals', 'type' => 'gsl_finals']
            ],
            default => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Competition', 'type' => 'competition']
            ]
        };
    }

    protected function seedTeamsForBracket(array $teams): array
    {
        // Standard bracket seeding pattern
        $seeded = [];
        $count = count($teams);
        
        for ($i = 0; $i < $count; $i++) {
            $seeded[] = $teams[$i];
        }
        
        return $seeded;
    }

    protected function calculateMatchTime(Tournament $tournament, int $round, int $matchNumber): Carbon
    {
        $baseTime = $tournament->start_date ?? now();
        $roundDelay = ($round - 1) * 4; // 4 hours between rounds
        $matchDelay = ($matchNumber - 1) * 1; // 1 hour between matches
        
        return $baseTime->copy()->addHours($roundDelay + $matchDelay);
    }

    protected function calculateRoundRobinRound(int $i, int $j, int $teamCount): int
    {
        return 1 + (($i + $j) % ($teamCount - 1));
    }

    protected function getPositionType(int $round, int $totalRounds): string
    {
        if ($round === 1) return 'first_round';
        if ($round === $totalRounds) return 'final';
        if ($round === $totalRounds - 1) return 'semifinal';
        if ($round === $totalRounds - 2) return 'quarterfinal';
        return 'intermediate';
    }

    protected function getAdvancementRule(string $type, int $round, int $totalRounds): array
    {
        return [
            'win_advances' => $round < $totalRounds,
            'loss_eliminates' => $type === 'single',
            'loss_drops_to_lower' => $type === 'double_upper'
        ];
    }
}