<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BracketProgressionService
{
    /**
     * Process match completion and advance winners
     */
    public function processMatchCompletion($match, $scoreData)
    {
        try {
            DB::beginTransaction();

            $winnerId = $this->determineWinner($match, $scoreData);
            $loserId = $this->determineLoser($match, $scoreData);

            switch ($match->bracket_type) {
                case 'main':
                case 'upper':
                    $this->processSingleEliminationAdvancement($match, $winnerId);
                    
                    // If double elimination, move loser to lower bracket
                    if ($match->bracket_type === 'upper') {
                        $this->moveLoserToLowerBracket($match, $loserId);
                    }
                    break;

                case 'lower':
                    $this->processLowerBracketAdvancement($match, $winnerId);
                    // Loser is eliminated in lower bracket
                    break;

                case 'swiss':
                    $this->processSwissCompletion($match, $winnerId, $loserId);
                    break;

                case 'round_robin':
                    $this->processRoundRobinCompletion($match, $winnerId, $loserId);
                    break;

                case 'grand_final':
                    $this->processGrandFinalCompletion($match, $winnerId, $loserId, $scoreData);
                    break;

                case 'bracket_reset':
                    $this->processBracketResetCompletion($match, $winnerId);
                    break;
            }

            // Update tournament standings
            $this->updateTournamentStandings($match->event_id);

            // Check if tournament is complete
            $this->checkTournamentCompletion($match->event_id);

            DB::commit();

            return [
                'success' => true,
                'winner_id' => $winnerId,
                'advancement_processed' => true
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing match completion: ' . $e->getMessage(), [
                'match_id' => $match->id,
                'score_data' => $scoreData
            ]);
            throw $e;
        }
    }

    /**
     * Determine match winner
     */
    private function determineWinner($match, $scoreData)
    {
        // Handle forfeit
        if ($scoreData['forfeit'] ?? false) {
            return $scoreData['winner_by_forfeit'] == 1 ? $match->team1_id : $match->team2_id;
        }

        // Normal scoring
        if ($scoreData['team1_score'] > $scoreData['team2_score']) {
            return $match->team1_id;
        } elseif ($scoreData['team2_score'] > $scoreData['team1_score']) {
            return $match->team2_id;
        }

        return null; // Draw
    }

    /**
     * Determine match loser
     */
    private function determineLoser($match, $scoreData)
    {
        $winnerId = $this->determineWinner($match, $scoreData);
        
        if (!$winnerId) return null;
        
        return $winnerId == $match->team1_id ? $match->team2_id : $match->team1_id;
    }

    /**
     * Process single elimination advancement
     */
    private function processSingleEliminationAdvancement($match, $winnerId)
    {
        if (!$winnerId) return;

        // Find next match in the bracket
        $nextMatch = $this->findNextMatch($match);
        
        if ($nextMatch) {
            $this->advanceTeamToMatch($winnerId, $nextMatch, $match);
        }
    }

    /**
     * Move loser to lower bracket in double elimination
     */
    private function moveLoserToLowerBracket($match, $loserId)
    {
        if (!$loserId) return;

        $lowerBracketMatch = $this->findLowerBracketDestination($match);
        
        if ($lowerBracketMatch) {
            $this->advanceTeamToMatch($loserId, $lowerBracketMatch, $match);
        }
    }

    /**
     * Process lower bracket advancement
     */
    private function processLowerBracketAdvancement($match, $winnerId)
    {
        if (!$winnerId) return;

        $nextMatch = $this->findNextLowerBracketMatch($match);
        
        if ($nextMatch) {
            $this->advanceTeamToMatch($winnerId, $nextMatch, $match);
        } else {
            // Check if winner advances to grand final
            $grandFinal = $this->findGrandFinal($match->event_id);
            if ($grandFinal) {
                $this->advanceTeamToMatch($winnerId, $grandFinal, $match);
            }
        }
    }

    /**
     * Find the next match in bracket progression
     */
    private function findNextMatch($match)
    {
        $nextRound = $match->round + 1;
        $nextPosition = ceil($match->bracket_position / 2);

        return DB::table('matches')
            ->where('event_id', $match->event_id)
            ->where('bracket_type', $match->bracket_type)
            ->where('round', $nextRound)
            ->where('bracket_position', $nextPosition)
            ->first();
    }

    /**
     * Find lower bracket destination for eliminated team
     */
    private function findLowerBracketDestination($upperMatch)
    {
        // Complex mapping for double elimination lower bracket
        $upperRound = $upperMatch->round;
        $upperPosition = $upperMatch->bracket_position;

        if ($upperRound == 1) {
            // Round 1 losers go to lower bracket round 1
            $lowerRound = 1;
            $lowerPosition = ceil($upperPosition / 2);
        } else {
            // Subsequent round losers go to even-numbered lower bracket rounds
            $lowerRound = ($upperRound - 1) * 2;
            $lowerPosition = $upperPosition;
        }

        return DB::table('matches')
            ->where('event_id', $upperMatch->event_id)
            ->where('bracket_type', 'lower')
            ->where('round', $lowerRound)
            ->where('bracket_position', $lowerPosition)
            ->first();
    }

    /**
     * Find next lower bracket match
     */
    private function findNextLowerBracketMatch($match)
    {
        // Lower bracket progression logic
        $currentRound = $match->round;
        
        if ($currentRound % 2 == 1) {
            // Odd rounds advance to next odd round
            $nextRound = $currentRound + 2;
            $nextPosition = ceil($match->bracket_position / 2);
        } else {
            // Even rounds advance to next odd round
            $nextRound = $currentRound + 1;
            $nextPosition = $match->bracket_position;
        }

        return DB::table('matches')
            ->where('event_id', $match->event_id)
            ->where('bracket_type', 'lower')
            ->where('round', $nextRound)
            ->where('bracket_position', $nextPosition)
            ->first();
    }

    /**
     * Advance team to next match
     */
    private function advanceTeamToMatch($teamId, $targetMatch, $sourceMatch)
    {
        // Determine which slot the team should fill
        $updateField = $this->determineTeamSlot($targetMatch, $sourceMatch);
        
        $updateData = [
            $updateField => $teamId,
            'updated_at' => now()
        ];

        // Update match status if both teams are now assigned
        if ($updateField === 'team1_id' && $targetMatch->team2_id) {
            $updateData['status'] = 'scheduled';
        } elseif ($updateField === 'team2_id' && $targetMatch->team1_id) {
            $updateData['status'] = 'scheduled';
        }

        DB::table('matches')
            ->where('id', $targetMatch->id)
            ->update($updateData);

        Log::info('Team advanced to next match', [
            'team_id' => $teamId,
            'source_match' => $sourceMatch->id,
            'target_match' => $targetMatch->id,
            'slot' => $updateField
        ]);
    }

    /**
     * Determine which team slot to fill in target match
     */
    private function determineTeamSlot($targetMatch, $sourceMatch)
    {
        // For single elimination: alternating pattern
        if ($sourceMatch->bracket_type === 'main' || $sourceMatch->bracket_type === 'upper') {
            return ($sourceMatch->bracket_position % 2 === 1) ? 'team1_id' : 'team2_id';
        }

        // For lower bracket: more complex logic based on round structure
        if ($sourceMatch->bracket_type === 'lower') {
            return $this->determineLowerBracketSlot($targetMatch, $sourceMatch);
        }

        // Default to first available slot
        return !$targetMatch->team1_id ? 'team1_id' : 'team2_id';
    }

    /**
     * Determine team slot for lower bracket advancement
     */
    private function determineLowerBracketSlot($targetMatch, $sourceMatch)
    {
        // Complex logic for double elimination lower bracket
        if ($sourceMatch->round % 2 == 1) {
            // From odd rounds
            return ($sourceMatch->bracket_position % 2 === 1) ? 'team1_id' : 'team2_id';
        } else {
            // From even rounds (drop-down rounds)
            return !$targetMatch->team1_id ? 'team1_id' : 'team2_id';
        }
    }

    /**
     * Process Swiss format completion
     */
    private function processSwissCompletion($match, $winnerId, $loserId)
    {
        // Swiss system doesn't have direct advancement
        // Results are used for next round pairings
        Log::info('Swiss match completed', [
            'match_id' => $match->id,
            'winner_id' => $winnerId,
            'round' => $match->round
        ]);
    }

    /**
     * Process round-robin completion
     */
    private function processRoundRobinCompletion($match, $winnerId, $loserId)
    {
        // Round-robin doesn't have advancement
        // Results contribute to final standings
        Log::info('Round-robin match completed', [
            'match_id' => $match->id,
            'winner_id' => $winnerId
        ]);
    }

    /**
     * Process grand final completion
     */
    private function processGrandFinalCompletion($match, $winnerId, $loserId, $scoreData)
    {
        // Check if bracket reset is needed
        $upperBracketTeam = $this->getUpperBracketFinalWinner($match->event_id);
        $lowerBracketWinner = $loserId == $upperBracketTeam ? $winnerId : $loserId;

        // If lower bracket team wins, trigger bracket reset (if enabled)
        if ($winnerId == $lowerBracketWinner) {
            $bracketReset = $this->findBracketReset($match->event_id);
            if ($bracketReset) {
                $this->triggerBracketReset($bracketReset, $upperBracketTeam, $lowerBracketWinner);
                return;
            }
        }

        // Tournament complete
        $this->completeTournament($match->event_id, $winnerId, $loserId);
    }

    /**
     * Process bracket reset completion
     */
    private function processBracketResetCompletion($match, $winnerId)
    {
        // Tournament complete after bracket reset
        $loserId = $winnerId == $match->team1_id ? $match->team2_id : $match->team1_id;
        $this->completeTournament($match->event_id, $winnerId, $loserId);
    }

    /**
     * Trigger bracket reset
     */
    private function triggerBracketReset($bracketResetMatch, $team1Id, $team2Id)
    {
        DB::table('matches')
            ->where('id', $bracketResetMatch->id)
            ->update([
                'team1_id' => $team1Id,
                'team2_id' => $team2Id,
                'status' => 'scheduled',
                'updated_at' => now()
            ]);

        Log::info('Bracket reset triggered', [
            'match_id' => $bracketResetMatch->id,
            'team1_id' => $team1Id,
            'team2_id' => $team2Id
        ]);
    }

    /**
     * Find grand final match
     */
    private function findGrandFinal($eventId)
    {
        return DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'grand_final')
            ->first();
    }

    /**
     * Find bracket reset match
     */
    private function findBracketReset($eventId)
    {
        return DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'bracket_reset')
            ->first();
    }

    /**
     * Get upper bracket final winner
     */
    private function getUpperBracketFinalWinner($eventId)
    {
        $upperFinal = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'upper')
            ->where('status', 'completed')
            ->orderBy('round', 'desc')
            ->first();

        if ($upperFinal) {
            return $upperFinal->team1_score > $upperFinal->team2_score 
                ? $upperFinal->team1_id 
                : $upperFinal->team2_id;
        }

        return null;
    }

    /**
     * Update tournament standings
     */
    private function updateTournamentStandings($eventId)
    {
        $event = DB::table('events')->where('id', $eventId)->first();
        if (!$event) return;

        // Clear existing standings
        DB::table('event_standings')->where('event_id', $eventId)->delete();

        // Calculate new standings based on format
        $standings = $this->calculateStandings($event);
        
        // Insert new standings
        $position = 1;
        foreach ($standings as $standing) {
            DB::table('event_standings')->insert([
                'event_id' => $eventId,
                'team_id' => $standing['team_id'],
                'position' => $position++,
                'points' => $standing['points'] ?? 0,
                'matches_played' => $standing['matches_played'] ?? 0,
                'matches_won' => $standing['wins'] ?? 0,
                'matches_lost' => $standing['losses'] ?? 0,
                'maps_won' => $standing['map_wins'] ?? 0,
                'maps_lost' => $standing['map_losses'] ?? 0,
                'map_differential' => ($standing['map_wins'] ?? 0) - ($standing['map_losses'] ?? 0),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Calculate standings based on tournament format
     */
    private function calculateStandings($event)
    {
        switch ($event->format) {
            case 'single_elimination':
            case 'double_elimination':
                return $this->calculateEliminationStandings($event);
            case 'round_robin':
                return $this->calculateRoundRobinStandings($event);
            case 'swiss':
                return $this->calculateSwissStandings($event);
            default:
                return [];
        }
    }

    /**
     * Calculate elimination tournament standings
     */
    private function calculateEliminationStandings($event)
    {
        $standings = [];
        $matches = DB::table('matches')
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->orderBy('round', 'desc')
            ->get();

        // Teams are ranked by how far they progressed
        foreach ($matches as $match) {
            if ($match->team1_score !== $match->team2_score) {
                $winnerId = $match->team1_score > $match->team2_score ? $match->team1_id : $match->team2_id;
                $loserId = $match->team1_score > $match->team2_score ? $match->team2_id : $match->team1_id;
                
                // Winner of final gets 1st place
                if ($match->bracket_type === 'grand_final' || 
                    ($match->bracket_type === 'main' && $this->isFinalMatch($match, $event->id))) {
                    $standings[1] = ['team_id' => $winnerId, 'points' => 0];
                    $standings[2] = ['team_id' => $loserId, 'points' => 0];
                } else {
                    // Position losers based on round eliminated
                    $position = $this->calculateEliminationPosition($match, $event->format);
                    if (!isset($standings[$position])) {
                        $standings[$position] = ['team_id' => $loserId, 'points' => 0];
                    }
                }
            }
        }

        // Convert to indexed array and sort by position
        ksort($standings);
        return array_values($standings);
    }

    /**
     * Check if tournament is complete
     */
    private function checkTournamentCompletion($eventId)
    {
        $event = DB::table('events')->where('id', $eventId)->first();
        if (!$event) return;

        $isComplete = false;

        switch ($event->format) {
            case 'single_elimination':
                $isComplete = $this->isSingleEliminationComplete($eventId);
                break;
            case 'double_elimination':
                $isComplete = $this->isDoubleEliminationComplete($eventId);
                break;
            case 'round_robin':
                $isComplete = $this->isRoundRobinComplete($eventId);
                break;
            case 'swiss':
                $isComplete = $this->isSwissComplete($eventId);
                break;
        }

        if ($isComplete) {
            DB::table('events')
                ->where('id', $eventId)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now()
                ]);

            Log::info('Tournament completed', ['event_id' => $eventId]);
        }
    }

    /**
     * Check if single elimination is complete
     */
    private function isSingleEliminationComplete($eventId)
    {
        $finalMatch = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'main')
            ->orderBy('round', 'desc')
            ->orderBy('bracket_position')
            ->first();

        return $finalMatch && $finalMatch->status === 'completed';
    }

    /**
     * Check if double elimination is complete
     */
    private function isDoubleEliminationComplete($eventId)
    {
        // Check if grand final is complete
        $grandFinal = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'grand_final')
            ->where('status', 'completed')
            ->first();

        if (!$grandFinal) return false;

        // Check if bracket reset is needed and complete
        $bracketReset = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'bracket_reset')
            ->first();

        if ($bracketReset) {
            return $bracketReset->status === 'completed' || $bracketReset->status === 'pending';
        }

        return true;
    }

    /**
     * Complete tournament
     */
    private function completeTournament($eventId, $winnerId, $runnerId = null)
    {
        // Update final positions
        if ($winnerId) {
            DB::table('event_standings')
                ->where('event_id', $eventId)
                ->where('team_id', $winnerId)
                ->update(['position' => 1]);
        }

        if ($runnerId) {
            DB::table('event_standings')
                ->where('event_id', $eventId)
                ->where('team_id', $runnerId)
                ->update(['position' => 2]);
        }

        Log::info('Tournament completed', [
            'event_id' => $eventId,
            'winner_id' => $winnerId,
            'runner_up_id' => $runnerId
        ]);
    }

    // Additional helper methods...
    private function isFinalMatch($match, $eventId) { return false; } // Placeholder
    private function calculateEliminationPosition($match, $format) { return 3; } // Placeholder
    private function calculateRoundRobinStandings($event) { return []; } // Placeholder
    private function isRoundRobinComplete($eventId) { return false; } // Placeholder
    private function isSwissComplete($eventId) { return false; } // Placeholder
}