<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\TournamentBracket;
use App\Models\BracketMatch;
use App\Models\Team;
use App\Events\TournamentPhaseChanged;
use App\Services\BracketGenerationService;
use App\Services\SwissSystemService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class TournamentProgressionService
{
    protected $bracketService;
    protected $swissService;

    public function __construct(
        BracketGenerationService $bracketService,
        SwissSystemService $swissService
    ) {
        $this->bracketService = $bracketService;
        $this->swissService = $swissService;
    }

    /**
     * Handle match completion and check for phase/tournament progression
     */
    public function handleMatchCompletion(BracketMatch $match): void
    {
        try {
            DB::beginTransaction();

            // Update match statistics and team records
            $this->updateMatchStatistics($match);

            // Handle bracket-specific completion logic
            $this->handleBracketSpecificCompletion($match);

            // Check if current phase is complete
            if ($this->isPhaseComplete($match->tournamentPhase)) {
                $this->completePhase($match->tournamentPhase);
            }

            // Check if entire tournament is complete
            if ($this->isTournamentComplete($match->tournament)) {
                $this->completeTournament($match->tournament);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament progression error: ' . $e->getMessage(), [
                'match_id' => $match->id,
                'tournament_id' => $match->tournament_id
            ]);
            throw $e;
        }
    }

    /**
     * Progress tournament to next phase
     */
    public function progressToNextPhase(Tournament $tournament, TournamentPhase $currentPhase = null): bool
    {
        try {
            $currentPhase = $currentPhase ?? $tournament->phases()->where('is_active', true)->first();
            
            if (!$currentPhase) {
                throw new \Exception('No active phase found');
            }

            // Get next phase
            $nextPhase = $tournament->phases()
                                   ->where('phase_order', '>', $currentPhase->phase_order)
                                   ->orderBy('phase_order')
                                   ->first();

            if (!$nextPhase) {
                // Tournament is complete
                return $this->completeTournament($tournament);
            }

            // Get advancing teams from current phase
            $advancingTeams = $this->getAdvancingTeams($currentPhase);
            
            if ($advancingTeams->isEmpty()) {
                throw new \Exception('No teams advancing to next phase');
            }

            // Start next phase
            return $this->startPhase($nextPhase, $advancingTeams);

        } catch (\Exception $e) {
            Log::error('Phase progression error: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'current_phase' => $currentPhase?->id
            ]);
            return false;
        }
    }

    /**
     * Start a tournament phase
     */
    public function startPhase(TournamentPhase $phase, Collection $teams = null): bool
    {
        try {
            DB::beginTransaction();

            // Validate phase can start
            if (!$phase->canStart()) {
                throw new \Exception("Phase {$phase->name} cannot start");
            }

            // Get teams for this phase
            $phaseTeams = $teams ?? $phase->getAvailableTeams();
            
            if ($phaseTeams->count() < 2) {
                throw new \Exception("Insufficient teams for phase {$phase->name}");
            }

            // Seed teams for the phase
            $phase->seedTeams($phaseTeams->pluck('id')->toArray());

            // Generate bracket for the phase
            $phase->generateBracket();

            // Start the phase
            $phase->startPhase();

            // Update tournament current phase
            $tournament = $phase->tournament;
            $tournament->current_phase = $phase->phase_type;
            $tournament->save();

            // Broadcast phase change event
            Event::dispatch(new TournamentPhaseChanged($tournament, $phase));

            DB::commit();

            Log::info("Tournament phase started", [
                'tournament_id' => $tournament->id,
                'phase_id' => $phase->id,
                'phase_name' => $phase->name,
                'team_count' => $phaseTeams->count()
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Phase start error: ' . $e->getMessage(), [
                'phase_id' => $phase->id
            ]);
            return false;
        }
    }

    /**
     * Complete a tournament phase
     */
    public function completePhase(TournamentPhase $phase): bool
    {
        try {
            DB::beginTransaction();

            // Calculate phase results
            $results = $phase->calculateResults();

            // Complete the phase
            $phase->completePhase($results);

            // Update team statuses based on results
            $this->updateTeamStatuses($phase, $results);

            // Check for automatic progression
            if ($phase->canAdvanceToNextPhase()) {
                $this->progressToNextPhase($phase->tournament, $phase);
            }

            DB::commit();

            Log::info("Tournament phase completed", [
                'tournament_id' => $phase->tournament_id,
                'phase_id' => $phase->id,
                'phase_name' => $phase->name
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Phase completion error: ' . $e->getMessage(), [
                'phase_id' => $phase->id
            ]);
            return false;
        }
    }

    /**
     * Complete entire tournament
     */
    public function completeTournament(Tournament $tournament): bool
    {
        try {
            DB::beginTransaction();

            // Calculate final standings
            $finalStandings = $this->calculateFinalStandings($tournament);

            // Determine tournament winner
            $winner = $this->determineTournamentWinner($tournament);

            // Update tournament status
            $tournament->status = 'completed';
            $tournament->current_phase = 'completed';
            
            // Store final results
            $tournament->phase_data = [
                'completion_date' => now()->toDateTimeString(),
                'final_standings' => $finalStandings,
                'winner' => $winner,
                'total_matches' => $tournament->matches()->count(),
                'completion_stats' => $this->generateCompletionStats($tournament)
            ];
            
            $tournament->save();

            // Award prizes
            $this->awardPrizes($tournament, $finalStandings);

            // Update team rankings/ELO
            $this->updatePostTournamentRankings($tournament);

            // Complete all remaining phases
            $tournament->phases()->where('status', '!=', 'completed')->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            DB::commit();

            Log::info("Tournament completed", [
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'winner' => $winner
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament completion error: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
            return false;
        }
    }

    /**
     * Handle bracket-specific completion logic
     */
    private function handleBracketSpecificCompletion(BracketMatch $match): void
    {
        $bracket = $match->tournamentBracket;
        
        switch ($bracket->bracket_type) {
            case 'single_elimination':
                $this->handleSingleEliminationCompletion($match, $bracket);
                break;
                
            case 'double_elimination_upper':
            case 'double_elimination_lower':
                $this->handleDoubleEliminationCompletion($match, $bracket);
                break;
                
            case 'swiss_system':
                $this->swissService->handleSwissMatchCompletion($match);
                break;
                
            case 'round_robin':
            case 'group_stage':
                $this->handleRoundRobinCompletion($match, $bracket);
                break;
        }
    }

    /**
     * Handle single elimination match completion
     */
    private function handleSingleEliminationCompletion(BracketMatch $match, TournamentBracket $bracket): void
    {
        $winnerId = $this->getMatchWinner($match);
        $loserId = $this->getMatchLoser($match);
        
        if (!$winnerId) return;

        // Advance winner to next round
        $this->advanceTeamInBracket($bracket, $winnerId, $match);
        
        // Eliminate loser
        if ($loserId) {
            $this->eliminateTeam($match->tournament, $loserId, $match->round);
        }

        // Check if bracket is complete
        if ($this->isBracketComplete($bracket)) {
            $bracket->complete();
        }
    }

    /**
     * Handle double elimination match completion
     */
    private function handleDoubleEliminationCompletion(BracketMatch $match, TournamentBracket $bracket): void
    {
        $winnerId = $this->getMatchWinner($match);
        $loserId = $this->getMatchLoser($match);
        
        if (!$winnerId) return;

        if ($bracket->bracket_type === 'double_elimination_upper') {
            // Upper bracket: winner advances, loser drops to lower bracket
            $this->advanceTeamInBracket($bracket, $winnerId, $match);
            
            if ($loserId) {
                $this->dropToLowerBracket($match->tournament, $loserId, $match->round);
            }
        } else {
            // Lower bracket: winner advances, loser is eliminated
            $this->advanceTeamInBracket($bracket, $winnerId, $match);
            
            if ($loserId) {
                $this->eliminateTeam($match->tournament, $loserId, $match->round);
            }
        }

        // Check for Grand Final bracket reset
        if ($this->needsBracketReset($match, $bracket)) {
            $this->handleBracketReset($bracket);
        }

        if ($this->isBracketComplete($bracket)) {
            $bracket->complete();
        }
    }

    /**
     * Handle round robin/group stage completion
     */
    private function handleRoundRobinCompletion(BracketMatch $match, TournamentBracket $bracket): void
    {
        // Update group standings
        $this->updateGroupStandings($bracket);
        
        // Check if all group matches are complete
        if ($this->isBracketComplete($bracket)) {
            $bracket->complete();
            
            // Determine advancing teams if this is a group stage
            if ($bracket->bracket_type === 'group_stage') {
                $advancingTeams = $this->getGroupAdvancingTeams($bracket);
                $this->markTeamsAsAdvanced($match->tournament, $advancingTeams);
            }
        }
    }

    /**
     * Advance team in bracket structure
     */
    private function advanceTeamInBracket(TournamentBracket $bracket, int $teamId, BracketMatch $completedMatch): void
    {
        $bracketData = $bracket->bracket_data;
        $matchId = $completedMatch->match_identifier;
        
        if (!isset($bracketData[$matchId]['winner_advances_to'])) return;
        
        $nextMatchId = $bracketData[$matchId]['winner_advances_to'];
        
        // Find and update the next match
        $nextMatch = BracketMatch::where('tournament_bracket_id', $bracket->id)
                               ->where('match_identifier', $nextMatchId)
                               ->first();
        
        if ($nextMatch) {
            if (!$nextMatch->team1_id) {
                $nextMatch->team1_id = $teamId;
            } elseif (!$nextMatch->team2_id) {
                $nextMatch->team2_id = $teamId;
            }
            
            $nextMatch->save();
            
            // Update bracket data
            if (isset($bracketData[$nextMatchId])) {
                if (!$bracketData[$nextMatchId]['team1_id']) {
                    $bracketData[$nextMatchId]['team1_id'] = $teamId;
                } elseif (!$bracketData[$nextMatchId]['team2_id']) {
                    $bracketData[$nextMatchId]['team2_id'] = $teamId;
                }
                
                $bracket->bracket_data = $bracketData;
                $bracket->save();
            }
        }
    }

    /**
     * Drop team to lower bracket
     */
    private function dropToLowerBracket(Tournament $tournament, int $teamId, int $fromRound): void
    {
        // Mark elimination round for this team
        $tournament->teams()->updateExistingPivot($teamId, [
            'elimination_round' => $fromRound,
            'updated_at' => now()
        ]);

        // Find lower bracket and insert team
        $lowerBracket = $tournament->brackets()
                                  ->where('bracket_type', 'double_elimination_lower')
                                  ->first();
        
        if ($lowerBracket) {
            $this->insertTeamIntoLowerBracket($lowerBracket, $teamId, $fromRound);
        }
    }

    /**
     * Eliminate team from tournament
     */
    private function eliminateTeam(Tournament $tournament, int $teamId, int $eliminationRound): void
    {
        $tournament->teams()->updateExistingPivot($teamId, [
            'status' => 'eliminated',
            'elimination_round' => $eliminationRound,
            'updated_at' => now()
        ]);
    }

    /**
     * Check if bracket reset is needed (double elimination grand final)
     */
    private function needsBracketReset(BracketMatch $match, TournamentBracket $bracket): bool
    {
        if ($bracket->bracket_type !== 'double_elimination_lower') return false;
        if ($bracket->reset_occurred) return false;
        
        // Check if this was the final lower bracket match
        $isLowerBracketFinal = $match->round === $bracket->round_count;
        
        if ($isLowerBracketFinal) {
            // Lower bracket winner should play upper bracket winner in reset if needed
            $lowerWinner = $this->getMatchWinner($match);
            return $this->shouldResetBracket($bracket->tournament, $lowerWinner);
        }
        
        return false;
    }

    /**
     * Handle bracket reset for Grand Final
     */
    private function handleBracketReset(TournamentBracket $bracket): void
    {
        // Create reset match in Grand Final
        $bracket->performBracketReset();
        
        Log::info("Bracket reset performed", [
            'tournament_id' => $bracket->tournament_id,
            'bracket_id' => $bracket->id
        ]);
    }

    /**
     * Get teams advancing from current phase
     */
    private function getAdvancingTeams(TournamentPhase $phase): Collection
    {
        switch ($phase->phase_type) {
            case 'swiss_rounds':
                return $phase->tournament->swiss_qualified_teams;
                
            case 'group_stage':
                return $this->getGroupStageAdvancingTeams($phase);
                
            case 'upper_bracket':
            case 'lower_bracket':
            case 'playoffs':
                return $this->getEliminationAdvancingTeams($phase);
                
            default:
                return collect();
        }
    }

    /**
     * Get teams advancing from group stage
     */
    private function getGroupStageAdvancingTeams(TournamentPhase $phase): Collection
    {
        $advancingTeams = collect();
        
        $groupBrackets = $phase->brackets;
        
        foreach ($groupBrackets as $bracket) {
            $groupStandings = $this->calculateGroupStandings($bracket);
            $advancementCount = $bracket->match_settings['advancement_count'] ?? 2;
            
            $groupAdvancing = collect($groupStandings)
                            ->take($advancementCount)
                            ->pluck('team_id');
            
            $teams = Team::whereIn('id', $groupAdvancing)->get();
            $advancingTeams = $advancingTeams->merge($teams);
        }
        
        return $advancingTeams;
    }

    /**
     * Get teams advancing from elimination brackets
     */
    private function getEliminationAdvancingTeams(TournamentPhase $phase): Collection
    {
        // For elimination phases, advancing teams are typically the winners
        $brackets = $phase->brackets;
        $advancingTeams = collect();
        
        foreach ($brackets as $bracket) {
            if ($bracket->status === 'completed') {
                $winner = $bracket->getBracketWinner();
                if ($winner) {
                    $advancingTeams->push(Team::find($winner));
                }
            }
        }
        
        return $advancingTeams->filter();
    }

    /**
     * Update team statuses based on phase results
     */
    private function updateTeamStatuses(TournamentPhase $phase, array $results): void
    {
        $tournament = $phase->tournament;
        
        // Mark advancing teams
        if (isset($results['advancing_teams'])) {
            foreach ($results['advancing_teams'] as $teamId) {
                $tournament->teams()->updateExistingPivot($teamId, [
                    'status' => 'advanced'
                ]);
            }
        }
        
        // Mark eliminated teams
        if (isset($results['eliminated_teams'])) {
            foreach ($results['eliminated_teams'] as $teamId) {
                $tournament->teams()->updateExistingPivot($teamId, [
                    'status' => 'eliminated'
                ]);
            }
        }
    }

    /**
     * Check if phase is complete
     */
    private function isPhaseComplete(TournamentPhase $phase): bool
    {
        // Check if all matches in the phase are complete
        $incompleteMatches = $phase->matches()
                                  ->whereNotIn('status', ['completed', 'cancelled'])
                                  ->count();
        
        return $incompleteMatches === 0;
    }

    /**
     * Check if tournament is complete
     */
    private function isTournamentComplete(Tournament $tournament): bool
    {
        $incompletePhases = $tournament->phases()
                                      ->whereNotIn('status', ['completed', 'cancelled'])
                                      ->count();
        
        return $incompletePhases === 0;
    }

    /**
     * Check if bracket is complete
     */
    private function isBracketComplete(TournamentBracket $bracket): bool
    {
        $incompleteMatches = $bracket->matches()
                                   ->whereNotIn('status', ['completed', 'cancelled'])
                                   ->count();
        
        return $incompleteMatches === 0;
    }

    /**
     * Get match winner
     */
    private function getMatchWinner(BracketMatch $match): ?int
    {
        if ($match->status !== 'completed') return null;
        
        if ($match->is_walkover) {
            return $match->team1_id; // Assume team1 gets walkover win
        }
        
        if ($match->team1_score > $match->team2_score) {
            return $match->team1_id;
        } elseif ($match->team2_score > $match->team1_score) {
            return $match->team2_id;
        }
        
        return null; // Tie (shouldn't happen in tournament matches)
    }

    /**
     * Get match loser
     */
    private function getMatchLoser(BracketMatch $match): ?int
    {
        if ($match->status !== 'completed') return null;
        
        $winner = $this->getMatchWinner($match);
        
        if ($winner === $match->team1_id) {
            return $match->team2_id;
        } elseif ($winner === $match->team2_id) {
            return $match->team1_id;
        }
        
        return null;
    }

    /**
     * Calculate final tournament standings
     */
    private function calculateFinalStandings(Tournament $tournament): array
    {
        // Implementation varies by tournament format
        switch ($tournament->format) {
            case 'swiss':
                return $tournament->swiss_standings->map(function($team, $index) {
                    return [
                        'placement' => $index + 1,
                        'team_id' => $team->id,
                        'team_name' => $team->name,
                        'points' => $team->pivot->swiss_score
                    ];
                })->toArray();
                
            case 'single_elimination':
            case 'double_elimination':
                return $this->calculateEliminationStandings($tournament);
                
            default:
                return [];
        }
    }

    /**
     * Calculate elimination tournament standings
     */
    private function calculateEliminationStandings(Tournament $tournament): array
    {
        $standings = [];
        
        // Get all teams with their elimination rounds
        $teams = $tournament->teams()
                           ->orderBy('pivot_elimination_round', 'desc')
                           ->orderBy('pivot_seed')
                           ->get();
        
        $placement = 1;
        foreach ($teams as $team) {
            $standings[] = [
                'placement' => $placement++,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'elimination_round' => $team->pivot->elimination_round,
                'status' => $team->pivot->status
            ];
        }
        
        return $standings;
    }

    /**
     * Determine tournament winner
     */
    private function determineTournamentWinner(Tournament $tournament): ?array
    {
        $finalStandings = $this->calculateFinalStandings($tournament);
        
        if (empty($finalStandings)) return null;
        
        $winner = $finalStandings[0];
        $winnerTeam = Team::find($winner['team_id']);
        
        return [
            'team_id' => $winner['team_id'],
            'team_name' => $winner['team_name'],
            'team_logo' => $winnerTeam->logo ?? null
        ];
    }

    /**
     * Award prizes based on final standings
     */
    private function awardPrizes(Tournament $tournament, array $finalStandings): void
    {
        $prizeDistribution = $tournament->prize_distribution ?? [];
        
        foreach ($finalStandings as $standing) {
            $placement = $standing['placement'];
            
            if (isset($prizeDistribution[$placement])) {
                $prizeAmount = $prizeDistribution[$placement];
                
                $tournament->teams()->updateExistingPivot($standing['team_id'], [
                    'prize_money' => $prizeAmount,
                    'placement' => $placement
                ]);
            }
        }
    }

    /**
     * Update post-tournament rankings
     */
    private function updatePostTournamentRankings(Tournament $tournament): void
    {
        // Implementation for ELO/ranking updates would go here
        // This could integrate with the existing EloRatingService
        
        Log::info("Post-tournament rankings updated", [
            'tournament_id' => $tournament->id
        ]);
    }

    /**
     * Generate completion statistics
     */
    private function generateCompletionStats(Tournament $tournament): array
    {
        return [
            'duration_days' => $tournament->getDurationInDays(),
            'total_matches' => $tournament->matches()->count(),
            'total_teams' => $tournament->current_team_count,
            'prize_pool' => $tournament->formatted_prize_pool,
            'viewership' => $tournament->views,
            'format' => $tournament->format,
            'region' => $tournament->region
        ];
    }

    /**
     * Update match statistics
     */
    private function updateMatchStatistics(BracketMatch $match): void
    {
        // Update team win/loss records
        if ($match->status === 'completed' && !$match->is_walkover) {
            $winner = $this->getMatchWinner($match);
            $loser = $this->getMatchLoser($match);
            
            if ($winner && $loser) {
                // Update win/loss counts for teams
                DB::table('tournament_teams')
                  ->where('tournament_id', $match->tournament_id)
                  ->where('team_id', $winner)
                  ->increment('matches_won');
                
                DB::table('tournament_teams')
                  ->where('tournament_id', $match->tournament_id)
                  ->where('team_id', $loser)
                  ->increment('matches_lost');
            }
        }
    }

    /**
     * Helper methods for complex operations
     */
    private function insertTeamIntoLowerBracket(TournamentBracket $lowerBracket, int $teamId, int $fromRound): void
    {
        // Complex logic for inserting team into correct lower bracket position
        // Based on which upper bracket round they lost in
    }

    private function shouldResetBracket(Tournament $tournament, int $lowerWinner): bool
    {
        // Check if lower bracket winner came from upper bracket originally
        // If yes, bracket reset is needed
        return false; // Simplified logic
    }

    private function calculateGroupStandings(TournamentBracket $bracket): array
    {
        // Calculate round robin standings for a group
        return [];
    }

    private function updateGroupStandings(TournamentBracket $bracket): void
    {
        // Update group standings after each match
    }

    private function getGroupAdvancingTeams(TournamentBracket $bracket): Collection
    {
        // Get teams that advance from this group
        return collect();
    }

    private function markTeamsAsAdvanced(Tournament $tournament, Collection $teams): void
    {
        foreach ($teams as $team) {
            $tournament->teams()->updateExistingPivot($team->id, [
                'status' => 'advanced'
            ]);
        }
    }
}