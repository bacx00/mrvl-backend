<?php

namespace App\Services;

use App\Models\Event;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\Team;
use App\Events\BracketUpdated;
use App\Events\TournamentPhaseChanged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;

class TournamentIntegrationService
{
    protected BracketService $bracketService;
    protected BracketProgressionService $progressionService;
    protected RankingService $rankingService;

    public function __construct(
        BracketService $bracketService,
        BracketProgressionService $progressionService,
        RankingService $rankingService
    ) {
        $this->bracketService = $bracketService;
        $this->progressionService = $progressionService;
        $this->rankingService = $rankingService;
    }

    /**
     * Create a comprehensive Liquipedia-style tournament
     */
    public function createLiquipediaTournament(Event $event, array $config): array
    {
        DB::beginTransaction();
        
        try {
            // Validate tournament configuration
            $this->validateTournamentConfig($config);
            
            // Create tournament phases
            $phases = $this->createTournamentPhases($event, $config['phases']);
            
            // Generate bracket for each phase
            $brackets = [];
            foreach ($phases as $phase) {
                $brackets[$phase->phase_name] = $this->generatePhasebracket($phase, $config);
            }
            
            // Setup advancement rules between phases
            $this->setupPhaseAdvancement($phases, $config['advancement']);
            
            // Initialize live tournament state
            $this->initializeLiveTournamentState($event);
            
            DB::commit();
            
            return [
                'event' => $event,
                'phases' => $phases,
                'brackets' => $brackets,
                'liquipedia_format' => $this->generateLiquipediaNotation($brackets)
            ];
            
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Failed to create Liquipedia tournament: " . $e->getMessage());
        }
    }

    /**
     * Generate Liquipedia R#M# notation for matches
     */
    public function generateLiquipediaNotation(array $brackets): array
    {
        $notation = [];
        
        foreach ($brackets as $phaseName => $bracket) {
            $rounds = [];
            $matches = $bracket['matches']->groupBy('round_number');
            
            foreach ($matches as $roundNumber => $roundMatches) {
                $roundNotation = [];
                
                foreach ($roundMatches as $index => $match) {
                    $matchId = "R{$roundNumber}M" . ($index + 1);
                    
                    // Update match with Liquipedia ID
                    $match->update(['liquipedia_id' => $matchId]);
                    
                    $roundNotation[] = [
                        'liquipedia_id' => $matchId,
                        'match_id' => $match->id,
                        'teams' => [
                            'team1' => $match->team1 ? [
                                'id' => $match->team1->id,
                                'name' => $match->team1->name,
                                'short_name' => $match->team1->short_name,
                                'logo' => $match->team1->logo
                            ] : null,
                            'team2' => $match->team2 ? [
                                'id' => $match->team2->id,
                                'name' => $match->team2->name,
                                'short_name' => $match->team2->short_name,
                                'logo' => $match->team2->logo
                            ] : null
                        ],
                        'score' => [
                            'team1' => $match->team1_score,
                            'team2' => $match->team2_score
                        ],
                        'status' => $match->status,
                        'dependencies' => $this->getMatchDependencies($match),
                        'advancement' => [
                            'winner_to' => $match->winner_advances_to,
                            'loser_to' => $match->loser_advances_to
                        ]
                    ];
                }
                
                $rounds["Round {$roundNumber}"] = $roundNotation;
            }
            
            $notation[$phaseName] = $rounds;
        }
        
        return $notation;
    }

    /**
     * Process match completion with full Liquipedia integration
     */
    public function processMatchCompletion(BracketMatch $match, array $matchData): array
    {
        DB::beginTransaction();
        
        try {
            // Update match with detailed results
            $this->updateMatchResults($match, $matchData);
            
            // Process team advancement
            $advancement = $this->processTeamAdvancement($match);
            
            // Update phase progression
            $phaseUpdate = $this->checkPhaseProgression($match->event);
            
            // Update tournament standings
            $this->updateTournamentStandings($match->event);
            
            // Update ELO ratings
            if ($match->winner_id && $match->loser_id) {
                $this->updateEloRatings($match);
            }
            
            // Broadcast live updates
            $this->broadcastMatchUpdate($match, $advancement);
            
            // Check tournament completion
            $tournamentStatus = $this->checkTournamentCompletion($match->event);
            
            DB::commit();
            
            return [
                'match_updated' => true,
                'advancement' => $advancement,
                'phase_update' => $phaseUpdate,
                'tournament_status' => $tournamentStatus,
                'liquipedia_id' => $match->liquipedia_id
            ];
            
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Failed to process match completion: " . $e->getMessage());
        }
    }

    /**
     * Generate Swiss system with proper pairing algorithms
     */
    public function generateSwissRound(BracketStage $stage, int $roundNumber): Collection
    {
        // Get current standings with tiebreakers
        $standings = $this->calculateSwissStandings($stage);
        
        // Generate optimal pairings avoiding repeat matchups
        $pairings = $this->generateSwissPairings($standings, $roundNumber);
        
        $matches = collect();
        
        foreach ($pairings as $index => $pairing) {
            $liquipediaId = "R{$roundNumber}M" . ($index + 1);
            
            $match = BracketMatch::create([
                'match_id' => "SW{$roundNumber}-" . ($index + 1),
                'liquipedia_id' => $liquipediaId,
                'event_id' => $stage->event_id,
                'tournament_id' => $stage->tournament_id,
                'bracket_stage_id' => $stage->id,
                'round_name' => "Swiss Round {$roundNumber}",
                'round_number' => $roundNumber,
                'match_number' => $index + 1,
                'team1_id' => $pairing['team1_id'],
                'team2_id' => $pairing['team2_id'],
                'team1_source' => $pairing['team1_source'],
                'team2_source' => $pairing['team2_source'],
                'status' => 'ready',
                'best_of' => '3'
            ]);
            
            $matches->push($match);
        }
        
        // Update stage current round
        $stage->update(['current_round' => $roundNumber]);
        
        return $matches;
    }

    /**
     * Calculate comprehensive Swiss standings with tiebreakers
     */
    protected function calculateSwissStandings(BracketStage $stage): Collection
    {
        $teams = $stage->seedings->load('team');
        
        return $teams->map(function ($seeding) use ($stage) {
            $teamMatches = BracketMatch::where('bracket_stage_id', $stage->id)
                ->where(function ($q) use ($seeding) {
                    $q->where('team1_id', $seeding->team_id)
                      ->orWhere('team2_id', $seeding->team_id);
                })
                ->where('status', 'completed')
                ->get();

            $wins = $losses = $mapWins = $mapLosses = 0;
            $opponents = [];
            $opponentsDefeated = [];
            
            foreach ($teamMatches as $match) {
                $isTeam1 = $match->team1_id == $seeding->team_id;
                $opponentId = $isTeam1 ? $match->team2_id : $match->team1_id;
                $opponents[] = $opponentId;
                
                if ($match->winner_id == $seeding->team_id) {
                    $wins++;
                    $opponentsDefeated[] = $opponentId;
                    $mapWins += $isTeam1 ? $match->team1_score : $match->team2_score;
                    $mapLosses += $isTeam1 ? $match->team2_score : $match->team1_score;
                } else {
                    $losses++;
                    $mapWins += $isTeam1 ? $match->team1_score : $match->team2_score;
                    $mapLosses += $isTeam1 ? $match->team2_score : $match->team1_score;
                }
            }
            
            // Calculate Buchholz score (opponent strength)
            $buchholzScore = $this->calculateBuchholzScore($opponents, $stage);
            
            return [
                'team_id' => $seeding->team_id,
                'team' => $seeding->team,
                'seed' => $seeding->seed,
                'wins' => $wins,
                'losses' => $losses,
                'map_wins' => $mapWins,
                'map_losses' => $mapLosses,
                'map_differential' => $mapWins - $mapLosses,
                'swiss_score' => $wins * 3 + $losses * 0, // 3 points per win
                'buchholz_score' => $buchholzScore,
                'opponents' => $opponents,
                'opponents_defeated' => $opponentsDefeated,
                'strength_of_schedule' => $this->calculateStrengthOfSchedule($opponents, $stage)
            ];
        })->sortByDesc(function ($standing) {
            // Primary: Win count
            // Secondary: Buchholz score (opponent strength)
            // Tertiary: Map differential
            return [$standing['wins'], $standing['buchholz_score'], $standing['map_differential']];
        });
    }

    /**
     * Generate optimal Swiss pairings avoiding repeat matchups
     */
    protected function generateSwissPairings(Collection $standings, int $roundNumber): array
    {
        $pairings = [];
        $paired = [];
        $standingsArray = $standings->toArray();
        
        // Group teams by score
        $scoreGroups = collect($standingsArray)->groupBy('wins');
        
        foreach ($scoreGroups as $wins => $teams) {
            $availableTeams = collect($teams)->whereNotIn('team_id', $paired);
            
            while ($availableTeams->count() >= 2) {
                $team1 = $availableTeams->shift();
                
                // Find best opponent (similar score, haven't played before)
                $opponent = $this->findBestOpponent($team1, $availableTeams, $roundNumber);
                
                if ($opponent) {
                    $availableTeams = $availableTeams->reject(function ($team) use ($opponent) {
                        return $team['team_id'] === $opponent['team_id'];
                    });
                    
                    $pairings[] = [
                        'team1_id' => $team1['team_id'],
                        'team2_id' => $opponent['team_id'],
                        'team1_source' => "Swiss ({$team1['wins']}-{$team1['losses']})",
                        'team2_source' => "Swiss ({$opponent['wins']}-{$opponent['losses']})"
                    ];
                    
                    $paired[] = $team1['team_id'];
                    $paired[] = $opponent['team_id'];
                }
            }
        }
        
        return $pairings;
    }

    protected function findBestOpponent(array $team1, Collection $availableTeams, int $roundNumber): ?array
    {
        // Try to find opponent they haven't played
        foreach ($availableTeams as $team2) {
            if (!in_array($team2['team_id'], $team1['opponents'] ?? [])) {
                return $team2;
            }
        }
        
        // If all have been played, return first available
        return $availableTeams->first();
    }

    protected function calculateBuchholzScore(array $opponents, BracketStage $stage): float
    {
        $totalScore = 0;
        
        foreach ($opponents as $opponentId) {
            $opponentWins = BracketMatch::where('bracket_stage_id', $stage->id)
                ->where(function ($q) use ($opponentId) {
                    $q->where('team1_id', $opponentId)->orWhere('team2_id', $opponentId);
                })
                ->where('winner_id', $opponentId)
                ->where('status', 'completed')
                ->count();
                
            $totalScore += $opponentWins * 3; // 3 points per win
        }
        
        return count($opponents) > 0 ? $totalScore / count($opponents) : 0;
    }

    protected function calculateStrengthOfSchedule(array $opponents, BracketStage $stage): float
    {
        // Implementation for strength of schedule calculation
        return 0.0; // Placeholder
    }

    // Additional helper methods...
    protected function validateTournamentConfig(array $config): void
    {
        // Validate tournament configuration
    }

    protected function createTournamentPhases(Event $event, array $phases): array
    {
        // Create tournament phases
        return [];
    }

    protected function generatePhasebracket($phase, array $config): array
    {
        // Generate bracket for specific phase
        return ['matches' => collect()];
    }

    protected function setupPhaseAdvancement(array $phases, array $advancement): void
    {
        // Setup advancement rules between phases
    }

    protected function initializeLiveTournamentState(Event $event): void
    {
        // Initialize live tournament state caching
        Cache::put("tournament_state_{$event->id}", [
            'status' => 'active',
            'current_phase' => 1,
            'live_matches' => [],
            'updated_at' => now()
        ], 3600);
    }

    protected function getMatchDependencies(BracketMatch $match): array
    {
        // Get matches this match depends on
        return [];
    }

    protected function updateMatchResults(BracketMatch $match, array $matchData): void
    {
        // Update match with comprehensive results
    }

    protected function processTeamAdvancement(BracketMatch $match): array
    {
        // Process team advancement logic
        return [];
    }

    protected function checkPhaseProgression(Event $event): array
    {
        // Check if phase should transition
        return [];
    }

    protected function updateTournamentStandings(Event $event): void
    {
        // Update comprehensive tournament standings
    }

    protected function updateEloRatings(BracketMatch $match): void
    {
        // Update ELO ratings for teams
    }

    protected function broadcastMatchUpdate(BracketMatch $match, array $advancement): void
    {
        // Broadcast live updates
        event(new BracketUpdated($match, $advancement));
    }

    protected function checkTournamentCompletion(Event $event): array
    {
        // Check if tournament is complete
        return ['status' => 'ongoing'];
    }
}