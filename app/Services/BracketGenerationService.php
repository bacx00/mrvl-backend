<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BracketGenerationService
{
    /**
     * Generate comprehensive double elimination bracket
     */
    public function createDoubleEliminationBracket($eventId, $teams, $options = [])
    {
        $teamCount = count($teams);
        $matches = [];
        $matchId = 0;

        // Calculate bracket structure
        $upperRounds = ceil(log($teamCount, 2));
        $lowerRounds = ($upperRounds - 1) * 2;

        // Create upper bracket (same as single elimination initially)
        $upperMatches = $this->createUpperBracket($eventId, $teams, $upperRounds, $options);
        $matches = array_merge($matches, $upperMatches);

        // Create lower bracket structure
        $lowerMatches = $this->createLowerBracket($eventId, $teamCount, $lowerRounds, $options);
        $matches = array_merge($matches, $lowerMatches);

        // Create grand final
        $grandFinal = $this->createGrandFinal($eventId, $options);
        $matches[] = $grandFinal;

        // Create bracket reset if enabled
        if ($options['bracket_reset'] ?? true) {
            $bracketReset = $this->createBracketReset($eventId, $options);
            $matches[] = $bracketReset;
        }

        return [
            'matches' => $matches,
            'upper_rounds' => $upperRounds,
            'lower_rounds' => $lowerRounds,
            'total_matches' => count($matches),
            'structure' => 'double_elimination'
        ];
    }

    /**
     * Create upper bracket matches
     */
    private function createUpperBracket($eventId, $teams, $rounds, $options)
    {
        $matches = [];
        $teamCount = count($teams);
        $round = 1;
        $position = 1;

        // First round matches
        for ($i = 0; $i < $teamCount; $i += 2) {
            if (isset($teams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $position,
                    'bracket_type' => 'upper',
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$i + 1]['id'],
                    'status' => 'scheduled',
                    'format' => $options['best_of'] ?? 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $position++;
            }
        }

        // Subsequent upper bracket rounds
        $currentMatches = count($matches);
        for ($r = 2; $r <= $rounds; $r++) {
            $matchesInRound = max(1, $currentMatches / 2);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $r,
                    'bracket_position' => $m,
                    'bracket_type' => 'upper',
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

        return $matches;
    }

    /**
     * Create lower bracket matches
     */
    private function createLowerBracket($eventId, $teamCount, $lowerRounds, $options)
    {
        $matches = [];
        
        // Lower bracket structure is more complex
        // Round 1: Losers from upper bracket round 1
        $firstRoundMatches = floor($teamCount / 4);
        
        for ($i = 1; $i <= $firstRoundMatches; $i++) {
            $matches[] = [
                'event_id' => $eventId,
                'round' => 1,
                'bracket_position' => $i,
                'bracket_type' => 'lower',
                'team1_id' => null, // Will be filled by losers
                'team2_id' => null,
                'status' => 'pending',
                'format' => $options['best_of'] ?? 'bo3',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Subsequent lower bracket rounds
        $currentMatches = $firstRoundMatches;
        for ($round = 2; $round <= $lowerRounds; $round++) {
            if ($round % 2 == 0) {
                // Even rounds: Merge with upper bracket losers
                $matchesInRound = $currentMatches;
            } else {
                // Odd rounds: Winners advance
                $matchesInRound = ceil($currentMatches / 2);
            }

            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $m,
                    'bracket_type' => 'lower',
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

        return $matches;
    }

    /**
     * Create grand final match
     */
    private function createGrandFinal($eventId, $options)
    {
        return [
            'event_id' => $eventId,
            'round' => 1,
            'bracket_position' => 1,
            'bracket_type' => 'grand_final',
            'team1_id' => null, // Upper bracket winner
            'team2_id' => null, // Lower bracket winner
            'status' => 'pending',
            'format' => $options['best_of'] ?? 'bo5',
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Create bracket reset match
     */
    private function createBracketReset($eventId, $options)
    {
        return [
            'event_id' => $eventId,
            'round' => 2,
            'bracket_position' => 1,
            'bracket_type' => 'bracket_reset',
            'team1_id' => null,
            'team2_id' => null,
            'status' => 'pending',
            'format' => $options['best_of'] ?? 'bo5',
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Generate Swiss system pairings using advanced algorithms
     */
    public function createSwissBracket($eventId, $teams, $options = [])
    {
        $teamCount = count($teams);
        $totalRounds = $options['swiss_rounds'] ?? $this->calculateOptimalSwissRounds($teamCount);
        $matches = [];

        // First round: Random pairing or seeded pairing
        if ($options['seeding_method'] === 'rating') {
            $matches = array_merge($matches, $this->createSeededFirstRound($eventId, $teams, $options));
        } else {
            $matches = array_merge($matches, $this->createRandomFirstRound($eventId, $teams, $options));
        }

        return [
            'matches' => $matches,
            'total_rounds' => $totalRounds,
            'pairing_algorithm' => 'swiss_system',
            'structure' => 'swiss'
        ];
    }

    /**
     * Create seeded first round for Swiss
     */
    private function createSeededFirstRound($eventId, $teams, $options)
    {
        $matches = [];
        $teamCount = count($teams);
        
        // Pair 1st with (n/2+1)th, 2nd with (n/2+2)th, etc.
        $midPoint = $teamCount / 2;
        
        for ($i = 0; $i < $midPoint; $i++) {
            if (isset($teams[$i]) && isset($teams[$i + $midPoint])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => 1,
                    'bracket_position' => $i + 1,
                    'bracket_type' => 'swiss',
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$i + $midPoint]['id'],
                    'status' => 'scheduled',
                    'format' => $options['best_of'] ?? 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        return $matches;
    }

    /**
     * Create random first round for Swiss
     */
    private function createRandomFirstRound($eventId, $teams, $options)
    {
        $matches = [];
        $shuffledTeams = $teams;
        shuffle($shuffledTeams);
        
        for ($i = 0; $i < count($shuffledTeams); $i += 2) {
            if (isset($shuffledTeams[$i + 1])) {
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => 1,
                    'bracket_position' => ($i / 2) + 1,
                    'bracket_type' => 'swiss',
                    'team1_id' => $shuffledTeams[$i]['id'],
                    'team2_id' => $shuffledTeams[$i + 1]['id'],
                    'status' => 'scheduled',
                    'format' => $options['best_of'] ?? 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        return $matches;
    }

    /**
     * Generate next Swiss round using proper pairing algorithms
     */
    public function generateNextSwissRound($eventId, $round)
    {
        // Get current standings
        $standings = $this->calculateSwissStandings($eventId);
        
        // Get pairing history to avoid repeat matchups
        $pairingHistory = $this->getPairingHistory($eventId);
        
        // Use Swiss system pairing algorithm
        $pairings = $this->calculateSwissPairings($standings, $pairingHistory);
        
        $matches = [];
        $position = 1;
        
        foreach ($pairings as $pairing) {
            $matches[] = [
                'event_id' => $eventId,
                'round' => $round,
                'bracket_position' => $position,
                'bracket_type' => 'swiss',
                'team1_id' => $pairing['team1_id'],
                'team2_id' => $pairing['team2_id'],
                'status' => 'scheduled',
                'format' => 'bo3',
                'created_at' => now(),
                'updated_at' => now()
            ];
            $position++;
        }

        // Insert matches
        foreach ($matches as $match) {
            DB::table('matches')->insert($match);
        }

        return [
            'matches_created' => count($matches),
            'round' => $round,
            'pairings' => $pairings
        ];
    }

    /**
     * Calculate Swiss system pairings
     */
    private function calculateSwissPairings($standings, $pairingHistory)
    {
        $pairings = [];
        $availableTeams = $standings;
        
        // Sort teams by score (wins), then by tiebreakers
        usort($availableTeams, function($a, $b) {
            if ($a['points'] != $b['points']) {
                return $b['points'] - $a['points'];
            }
            return $b['buchholz_score'] - $a['buchholz_score'];
        });
        
        $paired = [];
        
        // Pair teams with similar scores, avoiding repeat matchups
        for ($i = 0; $i < count($availableTeams); $i++) {
            if (in_array($availableTeams[$i]['team_id'], $paired)) continue;
            
            $team1 = $availableTeams[$i];
            $opponent = null;
            
            // Find best opponent
            for ($j = $i + 1; $j < count($availableTeams); $j++) {
                if (in_array($availableTeams[$j]['team_id'], $paired)) continue;
                
                $team2 = $availableTeams[$j];
                
                // Check if teams have played before
                if (!$this->haveTeamsPlayed($team1['team_id'], $team2['team_id'], $pairingHistory)) {
                    $opponent = $team2;
                    break;
                }
            }
            
            // If no opponent found without repeat, take closest available
            if (!$opponent) {
                for ($j = $i + 1; $j < count($availableTeams); $j++) {
                    if (!in_array($availableTeams[$j]['team_id'], $paired)) {
                        $opponent = $availableTeams[$j];
                        break;
                    }
                }
            }
            
            if ($opponent) {
                $pairings[] = [
                    'team1_id' => $team1['team_id'],
                    'team2_id' => $opponent['team_id'],
                    'team1_score' => $team1['points'],
                    'team2_score' => $opponent['points']
                ];
                
                $paired[] = $team1['team_id'];
                $paired[] = $opponent['team_id'];
            }
        }
        
        return $pairings;
    }

    /**
     * Check if two teams have played before
     */
    private function haveTeamsPlayed($team1Id, $team2Id, $pairingHistory)
    {
        foreach ($pairingHistory as $match) {
            if (($match['team1_id'] == $team1Id && $match['team2_id'] == $team2Id) ||
                ($match['team1_id'] == $team2Id && $match['team2_id'] == $team1Id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get pairing history for Swiss tournament
     */
    private function getPairingHistory($eventId)
    {
        return DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'swiss')
            ->whereNotNull('team1_id')
            ->whereNotNull('team2_id')
            ->select('team1_id', 'team2_id')
            ->get()
            ->toArray();
    }

    /**
     * Calculate Swiss standings with advanced tiebreakers
     */
    private function calculateSwissStandings($eventId)
    {
        $teams = DB::table('event_teams')
            ->join('teams', 'event_teams.team_id', '=', 'teams.id')
            ->where('event_teams.event_id', $eventId)
            ->select('teams.id as team_id', 'teams.name', 'teams.rating')
            ->get();

        $standings = [];
        foreach ($teams as $team) {
            $standings[] = [
                'team_id' => $team->team_id,
                'team_name' => $team->name,
                'matches_played' => 0,
                'points' => 0,
                'map_wins' => 0,
                'map_losses' => 0,
                'buchholz_score' => 0,
                'opponents_rating_sum' => 0
            ];
        }

        // Calculate results from completed matches
        $matches = DB::table('matches')
            ->where('event_id', $eventId)
            ->where('bracket_type', 'swiss')
            ->where('status', 'completed')
            ->get();

        foreach ($matches as $match) {
            foreach ($standings as &$standing) {
                if ($standing['team_id'] == $match->team1_id || $standing['team_id'] == $match->team2_id) {
                    $standing['matches_played']++;
                    
                    if ($standing['team_id'] == $match->team1_id) {
                        $standing['map_wins'] += $match->team1_score;
                        $standing['map_losses'] += $match->team2_score;
                        if ($match->team1_score > $match->team2_score) {
                            $standing['points'] += 3; // Win
                        } elseif ($match->team1_score == $match->team2_score) {
                            $standing['points'] += 1; // Draw
                        }
                    } else {
                        $standing['map_wins'] += $match->team2_score;
                        $standing['map_losses'] += $match->team1_score;
                        if ($match->team2_score > $match->team1_score) {
                            $standing['points'] += 3; // Win
                        } elseif ($match->team1_score == $match->team2_score) {
                            $standing['points'] += 1; // Draw
                        }
                    }
                }
            }
        }

        // Calculate Buchholz scores (opponents' total points)
        foreach ($standings as &$standing) {
            $opponentIds = [];
            foreach ($matches as $match) {
                if ($match->team1_id == $standing['team_id']) {
                    $opponentIds[] = $match->team2_id;
                } elseif ($match->team2_id == $standing['team_id']) {
                    $opponentIds[] = $match->team1_id;
                }
            }
            
            foreach ($opponentIds as $oppId) {
                foreach ($standings as $oppStanding) {
                    if ($oppStanding['team_id'] == $oppId) {
                        $standing['buchholz_score'] += $oppStanding['points'];
                        break;
                    }
                }
            }
        }

        return $standings;
    }

    /**
     * Calculate optimal number of Swiss rounds
     */
    private function calculateOptimalSwissRounds($teamCount)
    {
        // Standard formula: ceil(log2(teams))
        return ceil(log($teamCount, 2));
    }

    /**
     * Create comprehensive round-robin bracket
     */
    public function createRoundRobinBracket($eventId, $teams, $options = [])
    {
        $matches = [];
        $teamCount = count($teams);
        
        // Every team plays every other team once
        $matchNumber = 1;
        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                // Calculate round using round-robin scheduling algorithm
                $round = $this->calculateRoundRobinRound($i, $j, $teamCount);
                
                $matches[] = [
                    'event_id' => $eventId,
                    'round' => $round,
                    'bracket_position' => $matchNumber,
                    'bracket_type' => 'round_robin',
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$j]['id'],
                    'status' => 'scheduled',
                    'format' => $options['best_of'] ?? 'bo3',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $matchNumber++;
            }
        }

        return [
            'matches' => $matches,
            'total_matches' => count($matches),
            'total_rounds' => $teamCount - 1,
            'structure' => 'round_robin'
        ];
    }

    /**
     * Calculate which round a match should be in for round-robin
     */
    private function calculateRoundRobinRound($team1Index, $team2Index, $teamCount)
    {
        // Use circle method for optimal round-robin scheduling
        if ($teamCount % 2 == 0) {
            return $this->evenTeamRoundRobin($team1Index, $team2Index, $teamCount);
        } else {
            return $this->oddTeamRoundRobin($team1Index, $team2Index, $teamCount);
        }
    }

    private function evenTeamRoundRobin($i, $j, $n)
    {
        // Implementation of circle method for even number of teams
        return (($i + $j) % ($n - 1)) + 1;
    }

    private function oddTeamRoundRobin($i, $j, $n)
    {
        // Implementation of circle method for odd number of teams
        return (($i + $j) % $n) + 1;
    }
}