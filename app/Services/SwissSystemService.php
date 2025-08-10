<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\BracketMatch;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SwissSystemService
{
    /**
     * Generate next Swiss round pairings
     */
    public function generateNextSwissRound(Tournament $tournament): ?Collection
    {
        try {
            $swissBracket = $tournament->brackets()
                                     ->where('bracket_type', 'swiss_system')
                                     ->first();

            if (!$swissBracket) {
                throw new \Exception('No Swiss bracket found for tournament');
            }

            // Check if current round is complete
            if (!$this->isCurrentRoundComplete($swissBracket)) {
                throw new \Exception('Current round is not yet complete');
            }

            $nextRound = $swissBracket->current_round + 1;

            if ($nextRound > $swissBracket->round_count) {
                return $this->completeSwissPhase($tournament, $swissBracket);
            }

            // Get active teams (not eliminated)
            $activeTeams = $this->getActiveSwissTeams($tournament);

            if ($activeTeams->count() < 2) {
                throw new \Exception('Insufficient active teams for next round');
            }

            // Generate pairings using Swiss pairing algorithm
            $pairings = $this->generateSwissPairings($tournament, $activeTeams, $nextRound);

            // Create matches for next round
            $matches = $this->createSwissMatches($swissBracket, $pairings, $nextRound);

            // Update bracket current round
            $swissBracket->current_round = $nextRound;
            $swissBracket->save();

            // Update tournament scores after round completion
            $this->updateSwissStandings($tournament);

            return $matches;

        } catch (\Exception $e) {
            Log::error('Swiss round generation failed: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
            return null;
        }
    }

    /**
     * Generate Swiss pairings using advanced algorithm
     */
    private function generateSwissPairings(Tournament $tournament, Collection $teams, int $round): array
    {
        // Sort teams by current standing (score, then buchholz, then wins)
        $standings = $this->calculateCurrentStandings($tournament, $teams);
        
        $pairings = [];
        $paired = [];
        
        foreach ($standings as $team) {
            if (in_array($team['team_id'], $paired)) continue;
            
            // Find best opponent for this team
            $opponent = $this->findBestSwissOpponent($team, $standings, $paired, $tournament);
            
            if ($opponent) {
                $pairings[] = [
                    'team1' => $team,
                    'team2' => $opponent,
                    'round' => $round,
                    'pairing_score' => $this->calculatePairingScore($team, $opponent, $tournament)
                ];
                
                $paired[] = $team['team_id'];
                $paired[] = $opponent['team_id'];
            }
        }

        // Handle odd team if exists
        if (count($paired) < $teams->count()) {
            $unpairedTeam = $standings->first(function($team) use ($paired) {
                return !in_array($team['team_id'], $paired);
            });
            
            if ($unpairedTeam) {
                // Give bye to lowest-scored unpaired team
                $pairings[] = [
                    'team1' => $unpairedTeam,
                    'team2' => null, // Bye
                    'round' => $round,
                    'is_bye' => true
                ];
            }
        }

        return $pairings;
    }

    /**
     * Find best opponent for a team using Swiss pairing rules
     */
    private function findBestSwissOpponent(array $team, Collection $standings, array $paired, Tournament $tournament): ?array
    {
        $candidates = [];
        
        foreach ($standings as $potential) {
            if ($potential['team_id'] === $team['team_id'] || in_array($potential['team_id'], $paired)) {
                continue;
            }
            
            // Check if teams have played before
            if ($this->haveTeamsPlayed($team['team_id'], $potential['team_id'], $tournament)) {
                continue;
            }
            
            // Calculate pairing quality score
            $pairingScore = $this->calculatePairingScore($team, $potential, $tournament);
            
            $candidates[] = [
                'team' => $potential,
                'pairing_score' => $pairingScore,
                'score_difference' => abs($team['score'] - $potential['score'])
            ];
        }
        
        if (empty($candidates)) {
            // If no unplayed opponents, allow repeat matchup with best score match
            foreach ($standings as $potential) {
                if ($potential['team_id'] === $team['team_id'] || in_array($potential['team_id'], $paired)) {
                    continue;
                }
                
                $candidates[] = [
                    'team' => $potential,
                    'pairing_score' => $this->calculatePairingScore($team, $potential, $tournament) - 100, // Penalty for repeat
                    'score_difference' => abs($team['score'] - $potential['score'])
                ];
            }
        }
        
        if (empty($candidates)) return null;
        
        // Sort by pairing score (higher is better), then by smaller score difference
        usort($candidates, function($a, $b) {
            if ($a['pairing_score'] === $b['pairing_score']) {
                return $a['score_difference'] - $b['score_difference'];
            }
            return $b['pairing_score'] - $a['pairing_score'];
        });
        
        return $candidates[0]['team'];
    }

    /**
     * Calculate pairing quality score
     */
    private function calculatePairingScore(array $team1, array $team2, Tournament $tournament): int
    {
        $score = 0;
        
        // Prefer teams with similar scores
        $scoreDiff = abs($team1['score'] - $team2['score']);
        $score += max(0, 100 - ($scoreDiff * 20));
        
        // Prefer teams with similar win rates
        $winRateDiff = abs($team1['win_rate'] - $team2['win_rate']);
        $score += max(0, 50 - ($winRateDiff * 100));
        
        // Bonus for teams that haven't played
        if (!$this->haveTeamsPlayed($team1['team_id'], $team2['team_id'], $tournament)) {
            $score += 200;
        }
        
        // Consider color balance (alternating sides in previous games)
        $colorBalance = $this->calculateColorBalance($team1['team_id'], $team2['team_id'], $tournament);
        $score += $colorBalance * 10;
        
        return $score;
    }

    /**
     * Check if two teams have played against each other
     */
    private function haveTeamsPlayed(int $team1Id, int $team2Id, Tournament $tournament): bool
    {
        return BracketMatch::where('tournament_id', $tournament->id)
                          ->where(function($query) use ($team1Id, $team2Id) {
                              $query->where([
                                  ['team1_id', '=', $team1Id],
                                  ['team2_id', '=', $team2Id]
                              ])->orWhere([
                                  ['team1_id', '=', $team2Id],
                                  ['team2_id', '=', $team1Id]
                              ]);
                          })
                          ->exists();
    }

    /**
     * Calculate color balance for fair side selection
     */
    private function calculateColorBalance(int $team1Id, int $team2Id, Tournament $tournament): int
    {
        // In Marvel Rivals, this could represent map pick priority or side selection
        $team1Sides = $this->getTeamSideHistory($team1Id, $tournament);
        $team2Sides = $this->getTeamSideHistory($team2Id, $tournament);
        
        $team1Balance = $team1Sides['first_pick'] - $team1Sides['second_pick'];
        $team2Balance = $team2Sides['first_pick'] - $team2Sides['second_pick'];
        
        // Return balance score (positive if pairing helps balance)
        return abs($team1Balance) + abs($team2Balance) - abs($team1Balance + $team2Balance);
    }

    /**
     * Get team's side selection history
     */
    private function getTeamSideHistory(int $teamId, Tournament $tournament): array
    {
        $matches = BracketMatch::where('tournament_id', $tournament->id)
                              ->where(function($query) use ($teamId) {
                                  $query->where('team1_id', $teamId)
                                        ->orWhere('team2_id', $teamId);
                              })
                              ->where('status', 'completed')
                              ->get();

        $firstPick = 0;
        $secondPick = 0;

        foreach ($matches as $match) {
            if ($match->team1_id === $teamId) {
                $firstPick++; // Team1 typically gets first pick
            } else {
                $secondPick++; // Team2 gets second pick
            }
        }

        return [
            'first_pick' => $firstPick,
            'second_pick' => $secondPick
        ];
    }

    /**
     * Create Swiss matches for a round
     */
    private function createSwissMatches(TournamentBracket $bracket, array $pairings, int $round): Collection
    {
        $matches = collect();
        $matchNumber = 1;

        foreach ($pairings as $pairing) {
            $matchId = "SW_R{$round}M{$matchNumber}";
            
            $match = BracketMatch::create([
                'tournament_id' => $bracket->tournament_id,
                'tournament_phase_id' => $bracket->tournament_phase_id,
                'tournament_bracket_id' => $bracket->id,
                'match_identifier' => $matchId,
                'round' => $round,
                'match_number' => $matchNumber,
                'team1_id' => $pairing['team1']['team_id'],
                'team2_id' => $pairing['team2']['team_id'] ?? null,
                'status' => $pairing['team2'] ? 'pending' : 'completed', // Bye is auto-completed
                'match_format' => $bracket->match_settings['format'] ?? 'bo1',
                'is_walkover' => isset($pairing['is_bye']) && $pairing['is_bye'],
                'walkover_reason' => isset($pairing['is_bye']) ? 'Bye' : null,
                'team1_score' => isset($pairing['is_bye']) ? 1 : 0,
                'team2_score' => 0,
                'scheduled_at' => $this->calculateSwissMatchSchedule($bracket, $round, $matchNumber)
            ]);

            // If it's a bye, immediately update team's Swiss record
            if (isset($pairing['is_bye']) && $pairing['is_bye']) {
                $this->updateTeamSwissRecord($bracket->tournament_id, $pairing['team1']['team_id'], true);
            }

            $matches->push($match);
            $matchNumber++;
        }

        // Update bracket data with new round
        $bracketData = $bracket->bracket_data ?? [];
        $bracketData["round_{$round}"] = [
            'round' => $round,
            'matches' => $pairings,
            'generated_at' => now()->toDateTimeString()
        ];
        
        $bracket->bracket_data = $bracketData;
        $bracket->save();

        return $matches;
    }

    /**
     * Calculate current Swiss standings
     */
    private function calculateCurrentStandings(Tournament $tournament, Collection $teams): Collection
    {
        $standings = collect();

        foreach ($teams as $team) {
            $teamData = $tournament->teams()
                                  ->where('teams.id', $team->id)
                                  ->first();

            $wins = $teamData->pivot->swiss_wins ?? 0;
            $losses = $teamData->pivot->swiss_losses ?? 0;
            $score = $teamData->pivot->swiss_score ?? 0;
            $buchholz = $this->calculateBuchholzScore($tournament, $team);

            $standings->push([
                'team_id' => $team->id,
                'team' => $team,
                'wins' => $wins,
                'losses' => $losses,
                'score' => $score,
                'buchholz' => $buchholz,
                'win_rate' => $wins + $losses > 0 ? $wins / ($wins + $losses) : 0,
                'matches_played' => $wins + $losses
            ]);
        }

        return $standings->sortByDesc('score')
                        ->sortByDesc('buchholz')
                        ->sortByDesc('wins')
                        ->sortBy('losses')
                        ->values();
    }

    /**
     * Calculate Buchholz score (sum of opponents' scores)
     */
    private function calculateBuchholzScore(Tournament $tournament, Team $team): float
    {
        $opponents = $this->getTeamOpponents($tournament, $team);
        $buchholzScore = 0;

        foreach ($opponents as $opponent) {
            $opponentData = $tournament->teams()
                                      ->where('teams.id', $opponent->id)
                                      ->first();
            
            $buchholzScore += $opponentData->pivot->swiss_score ?? 0;
        }

        return $buchholzScore;
    }

    /**
     * Get all opponents a team has faced
     */
    private function getTeamOpponents(Tournament $tournament, Team $team): Collection
    {
        $matches = BracketMatch::where('tournament_id', $tournament->id)
                              ->where(function($query) use ($team) {
                                  $query->where('team1_id', $team->id)
                                        ->orWhere('team2_id', $team->id);
                              })
                              ->where('status', 'completed')
                              ->get();

        $opponentIds = $matches->map(function($match) use ($team) {
            return $match->team1_id === $team->id ? $match->team2_id : $match->team1_id;
        })->filter()->unique();

        return Team::whereIn('id', $opponentIds)->get();
    }

    /**
     * Get active Swiss teams (not eliminated)
     */
    private function getActiveSwissTeams(Tournament $tournament): Collection
    {
        $settings = $tournament->qualification_settings ?? [];
        $maxLosses = $settings['swiss_losses_eliminated'] ?? 3;

        return $tournament->teams()
                         ->wherePivot('swiss_losses', '<', $maxLosses)
                         ->wherePivot('status', '!=', 'disqualified')
                         ->get();
    }

    /**
     * Check if current round is complete
     */
    private function isCurrentRoundComplete(TournamentBracket $bracket): bool
    {
        $currentRound = $bracket->current_round;
        
        $incompleteMatches = BracketMatch::where('tournament_bracket_id', $bracket->id)
                                       ->where('round', $currentRound)
                                       ->whereNotIn('status', ['completed', 'cancelled'])
                                       ->count();

        return $incompleteMatches === 0;
    }

    /**
     * Update Swiss standings after round completion
     */
    public function updateSwissStandings(Tournament $tournament): void
    {
        $swissBracket = $tournament->brackets()
                                  ->where('bracket_type', 'swiss_system')
                                  ->first();

        if (!$swissBracket) return;

        foreach ($tournament->teams as $team) {
            $this->updateTeamSwissRecord($tournament->id, $team->id);
        }

        // Update Buchholz scores after all basic scores are updated
        foreach ($tournament->teams as $team) {
            $buchholz = $this->calculateBuchholzScore($tournament, $team);
            
            $tournament->teams()->updateExistingPivot($team->id, [
                'swiss_buchholz' => $buchholz
            ]);
        }
    }

    /**
     * Update individual team's Swiss record
     */
    private function updateTeamSwissRecord(int $tournamentId, int $teamId, bool $isBye = false): void
    {
        $matches = BracketMatch::where('tournament_id', $tournamentId)
                              ->where(function($query) use ($teamId) {
                                  $query->where('team1_id', $teamId)
                                        ->orWhere('team2_id', $teamId);
                              })
                              ->where('status', 'completed')
                              ->get();

        $wins = 0;
        $losses = 0;

        foreach ($matches as $match) {
            if ($match->is_walkover && $isBye) {
                $wins++; // Bye counts as win
                continue;
            }

            if ($match->team1_id === $teamId) {
                if ($match->team1_score > $match->team2_score) {
                    $wins++;
                } else {
                    $losses++;
                }
            } else {
                if ($match->team2_score > $match->team1_score) {
                    $wins++;
                } else {
                    $losses++;
                }
            }
        }

        $score = $wins * 3; // 3 points per win in Swiss

        DB::table('tournament_teams')
          ->where('tournament_id', $tournamentId)
          ->where('team_id', $teamId)
          ->update([
              'swiss_wins' => $wins,
              'swiss_losses' => $losses,
              'swiss_score' => $score,
              'updated_at' => now()
          ]);
    }

    /**
     * Complete Swiss phase and determine qualifiers
     */
    private function completeSwissPhase(Tournament $tournament, TournamentBracket $bracket): Collection
    {
        $settings = $tournament->qualification_settings ?? [];
        $winsRequired = $settings['swiss_wins_required'] ?? 3;
        $lossesEliminated = $settings['swiss_losses_eliminated'] ?? 3;

        // Update final standings
        $this->updateSwissStandings($tournament);

        // Get qualified and eliminated teams
        $qualifiedTeams = $tournament->teams()
                                    ->wherePivot('swiss_wins', '>=', $winsRequired)
                                    ->orderByDesc('pivot_swiss_score')
                                    ->orderByDesc('pivot_swiss_buchholz')
                                    ->get();

        $eliminatedTeams = $tournament->teams()
                                     ->wherePivot('swiss_losses', '>=', $lossesEliminated)
                                     ->get();

        // Update team statuses
        foreach ($qualifiedTeams as $team) {
            $tournament->teams()->updateExistingPivot($team->id, [
                'status' => 'advanced'
            ]);
        }

        foreach ($eliminatedTeams as $team) {
            $tournament->teams()->updateExistingPivot($team->id, [
                'status' => 'eliminated'
            ]);
        }

        // Complete Swiss phase
        $phase = $bracket->phase;
        if ($phase) {
            $phase->completePhase([
                'qualified_teams' => $qualifiedTeams->pluck('id')->toArray(),
                'eliminated_teams' => $eliminatedTeams->pluck('id')->toArray(),
                'final_standings' => $this->getFinalSwissStandings($tournament)
            ]);
        }

        // Mark bracket as completed
        $bracket->complete();

        // Progress to next phase (playoffs)
        $this->progressToPlayoffs($tournament, $qualifiedTeams);

        return $qualifiedTeams;
    }

    /**
     * Get final Swiss standings
     */
    private function getFinalSwissStandings(Tournament $tournament): array
    {
        return $tournament->teams()
                         ->orderByDesc('pivot_swiss_score')
                         ->orderByDesc('pivot_swiss_buchholz')
                         ->orderByDesc('pivot_swiss_wins')
                         ->orderBy('pivot_swiss_losses')
                         ->get()
                         ->map(function($team, $index) {
                             return [
                                 'placement' => $index + 1,
                                 'team_id' => $team->id,
                                 'team_name' => $team->name,
                                 'wins' => $team->pivot->swiss_wins,
                                 'losses' => $team->pivot->swiss_losses,
                                 'score' => $team->pivot->swiss_score,
                                 'buchholz' => $team->pivot->swiss_buchholz,
                                 'status' => $team->pivot->status
                             ];
                         })
                         ->toArray();
    }

    /**
     * Progress tournament to playoffs phase
     */
    private function progressToPlayoffs(Tournament $tournament, Collection $qualifiedTeams): void
    {
        // Check if playoffs phase exists
        $playoffsPhase = $tournament->phases()
                                   ->where('phase_type', 'playoffs')
                                   ->first();

        if ($playoffsPhase && $qualifiedTeams->count() >= 2) {
            $playoffsPhase->startPhase();
            
            // Update tournament current phase
            $tournament->current_phase = 'playoffs';
            $tournament->save();

            // Generate playoffs bracket with qualified teams
            $bracketService = app(BracketGenerationService::class);
            $bracketService->generateTournamentBrackets($tournament);
        }
    }

    /**
     * Calculate match schedule for Swiss round
     */
    private function calculateSwissMatchSchedule(TournamentBracket $bracket, int $round, int $matchNumber): \Carbon\Carbon
    {
        $tournament = $bracket->tournament;
        $baseTime = $tournament->start_date ?? now();
        
        // Swiss rounds are typically played with breaks between them
        $roundDelay = ($round - 1) * 4; // 4 hours between Swiss rounds
        $matchDelay = ($matchNumber - 1) * 0.5; // 30 minutes between concurrent matches
        
        return $baseTime->copy()->addHours($roundDelay + $matchDelay);
    }

    /**
     * Handle Swiss match completion
     */
    public function handleSwissMatchCompletion(BracketMatch $match): void
    {
        if ($match->status !== 'completed') return;
        
        $tournament = $match->tournament;
        
        // Update team records
        if ($match->team1_id) {
            $this->updateTeamSwissRecord($tournament->id, $match->team1_id);
        }
        
        if ($match->team2_id) {
            $this->updateTeamSwissRecord($tournament->id, $match->team2_id);
        }
        
        // Check if round is complete
        $bracket = $match->tournamentBracket;
        if ($this->isCurrentRoundComplete($bracket)) {
            
            // Update Buchholz scores
            $this->updateSwissStandings($tournament);
            
            // Check for early eliminations/qualifications
            $this->checkEarlySwissResolutions($tournament);
            
            // Auto-generate next round if not final round
            if ($bracket->current_round < $bracket->round_count) {
                $activeTeams = $this->getActiveSwissTeams($tournament);
                
                if ($activeTeams->count() >= 2) {
                    // Generate next round after a delay (could be scheduled job)
                    $this->generateNextSwissRound($tournament);
                }
            } else {
                // Complete Swiss phase
                $this->completeSwissPhase($tournament, $bracket);
            }
        }
    }

    /**
     * Check for early Swiss eliminations/qualifications
     */
    private function checkEarlySwissResolutions(Tournament $tournament): void
    {
        $settings = $tournament->qualification_settings ?? [];
        $winsRequired = $settings['swiss_wins_required'] ?? 3;
        $lossesEliminated = $settings['swiss_losses_eliminated'] ?? 3;

        // Check for early qualifications
        $earlyQualified = $tournament->teams()
                                    ->wherePivot('swiss_wins', '>=', $winsRequired)
                                    ->wherePivot('status', 'checked_in')
                                    ->get();

        foreach ($earlyQualified as $team) {
            $tournament->teams()->updateExistingPivot($team->id, [
                'status' => 'qualified'
            ]);
            
            Log::info("Team {$team->name} qualified early in Swiss", [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'wins' => $team->pivot->swiss_wins
            ]);
        }

        // Check for early eliminations
        $earlyEliminated = $tournament->teams()
                                     ->wherePivot('swiss_losses', '>=', $lossesEliminated)
                                     ->wherePivot('status', 'checked_in')
                                     ->get();

        foreach ($earlyEliminated as $team) {
            $tournament->teams()->updateExistingPivot($team->id, [
                'status' => 'eliminated'
            ]);
            
            Log::info("Team {$team->name} eliminated early in Swiss", [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'losses' => $team->pivot->swiss_losses
            ]);
        }
    }

    /**
     * Get Swiss tournament statistics
     */
    public function getSwissStatistics(Tournament $tournament): array
    {
        $bracket = $tournament->brackets()
                             ->where('bracket_type', 'swiss_system')
                             ->first();

        if (!$bracket) return [];

        $settings = $tournament->qualification_settings ?? [];
        $totalTeams = $tournament->teams()->count();
        $activeTeams = $this->getActiveSwissTeams($tournament)->count();
        
        $qualified = $tournament->teams()
                               ->whereIn('pivot_status', ['qualified', 'advanced'])
                               ->count();
        
        $eliminated = $tournament->teams()
                                ->whereIn('pivot_status', ['eliminated'])
                                ->count();

        $roundsCompleted = $bracket->current_round - 1;
        $totalRounds = $bracket->round_count;

        return [
            'total_teams' => $totalTeams,
            'active_teams' => $activeTeams,
            'qualified_teams' => $qualified,
            'eliminated_teams' => $eliminated,
            'rounds_completed' => $roundsCompleted,
            'total_rounds' => $totalRounds,
            'progress_percentage' => round(($roundsCompleted / $totalRounds) * 100, 1),
            'current_round' => $bracket->current_round,
            'wins_to_qualify' => $settings['swiss_wins_required'] ?? 3,
            'losses_to_eliminate' => $settings['swiss_losses_eliminated'] ?? 3,
            'matches_completed' => BracketMatch::where('tournament_bracket_id', $bracket->id)
                                             ->where('status', 'completed')
                                             ->count(),
            'matches_remaining' => BracketMatch::where('tournament_bracket_id', $bracket->id)
                                             ->whereNotIn('status', ['completed', 'cancelled'])
                                             ->count()
        ];
    }
}