<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EnhancedEloRatingService
{
    // Base K-factor for ELO calculations
    const BASE_K_FACTOR = 32;
    
    // K-factor adjustments based on different conditions
    const K_FACTORS = [
        'new_team' => 40,          // Teams with less than 10 matches
        'experienced_team' => 24,   // Teams with 50+ matches
        'tournament_match' => 40,   // Tournament matches are weighted higher
        'regular_match' => 32,      // Regular season matches
        'high_tier_event' => 48,    // S-tier events
        'medium_tier_event' => 36,  // A-tier events
        'low_tier_event' => 28,     // B/C-tier events
    ];
    
    // Rating boundaries for different skill divisions
    const RATING_DIVISIONS = [
        'One Above All' => 2500,
        'Eternity' => 2300,
        'Celestial' => 2100,
        'Grandmaster' => 1900,
        'Diamond' => 1700,
        'Platinum' => 1500,
        'Gold' => 1300,
        'Silver' => 1100,
        'Bronze' => 0,
    ];

    /**
     * Calculate ELO rating changes for both teams after a match
     */
    public function updateMatchRatings($matchId, $team1Id, $team2Id, $team1Score, $team2Score, $eventTier = 'regular')
    {
        DB::beginTransaction();
        
        try {
            // Get current team ratings and match history
            $team1 = $this->getTeamWithStats($team1Id);
            $team2 = $this->getTeamWithStats($team2Id);
            
            // Calculate expected scores (probability of winning)
            $expectedScore1 = $this->calculateExpectedScore($team1->elo_rating, $team2->elo_rating);
            $expectedScore2 = 1 - $expectedScore1;
            
            // Determine actual scores (1 for win, 0 for loss, 0.5 for tie)
            $actualScore1 = $team1Score > $team2Score ? 1 : ($team1Score == $team2Score ? 0.5 : 0);
            $actualScore2 = 1 - $actualScore1;
            
            // Calculate K-factors for both teams
            $kFactor1 = $this->calculateKFactor($team1, $eventTier);
            $kFactor2 = $this->calculateKFactor($team2, $eventTier);
            
            // Calculate map differential bonus (extra points for dominant wins)
            $mapDifferentialBonus = $this->calculateMapDifferentialBonus($team1Score, $team2Score);
            
            // Calculate rating changes
            $ratingChange1 = round($kFactor1 * ($actualScore1 - $expectedScore1)) + 
                           ($actualScore1 > 0.5 ? $mapDifferentialBonus : -$mapDifferentialBonus);
            $ratingChange2 = round($kFactor2 * ($actualScore2 - $expectedScore2)) + 
                           ($actualScore2 > 0.5 ? $mapDifferentialBonus : -$mapDifferentialBonus);
            
            // Apply rating changes with bounds checking
            $newRating1 = max(0, $team1->elo_rating + $ratingChange1);
            $newRating2 = max(0, $team2->elo_rating + $ratingChange2);
            
            // Update team ratings and statistics
            $this->updateTeamRating($team1Id, $newRating1, $ratingChange1, $matchId, $actualScore1 > 0.5 ? 'match_win' : 'match_loss');
            $this->updateTeamRating($team2Id, $newRating2, $ratingChange2, $matchId, $actualScore2 > 0.5 ? 'match_win' : 'match_loss');
            
            // Update match results cache
            $this->updateMatchResultsCache($matchId, $team1Id, $team1->elo_rating, $newRating1, $ratingChange1, $actualScore1 > 0.5 ? 'win' : 'loss', $team1Score, $team2Score);
            $this->updateMatchResultsCache($matchId, $team2Id, $team2->elo_rating, $newRating2, $ratingChange2, $actualScore2 > 0.5 ? 'win' : 'loss', $team2Score, $team1Score);
            
            DB::commit();
            
            return [
                'team1' => [
                    'old_rating' => $team1->elo_rating,
                    'new_rating' => $newRating1,
                    'change' => $ratingChange1,
                    'expected_score' => $expectedScore1,
                    'actual_score' => $actualScore1
                ],
                'team2' => [
                    'old_rating' => $team2->elo_rating,
                    'new_rating' => $newRating2,
                    'change' => $ratingChange2,
                    'expected_score' => $expectedScore2,
                    'actual_score' => $actualScore2
                ]
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            throw new \Exception('Failed to update ELO ratings: ' . $e->getMessage());
        }
    }

    /**
     * Update player ELO ratings based on match performance
     */
    public function updatePlayerRatings($matchId, $playerId, $performanceRating, $teamResult)
    {
        DB::beginTransaction();
        
        try {
            $player = $this->getPlayerWithStats($playerId);
            
            // Base rating change from team result
            $baseChange = $teamResult === 'win' ? 15 : -15;
            
            // Performance modifier (-10 to +10 based on individual performance)
            $performanceModifier = $this->calculatePerformanceModifier($performanceRating);
            
            // Role-based modifiers (supports get slight bonus for team-oriented play)
            $roleModifier = $this->getRoleModifier($player->role, $performanceRating);
            
            // Calculate total rating change
            $totalChange = $baseChange + $performanceModifier + $roleModifier;
            
            // Apply K-factor based on player experience
            $kFactor = $this->calculatePlayerKFactor($player);
            $finalChange = round($totalChange * ($kFactor / 32));
            
            // Update player rating
            $newRating = max(0, $player->elo_rating + $finalChange);
            $this->updatePlayerRating($playerId, $newRating, $finalChange, $matchId, $teamResult === 'win' ? 'match_win' : 'match_loss');
            
            DB::commit();
            
            return [
                'old_rating' => $player->elo_rating,
                'new_rating' => $newRating,
                'change' => $finalChange,
                'performance_bonus' => $performanceModifier,
                'role_bonus' => $roleModifier
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            throw new \Exception('Failed to update player ELO rating: ' . $e->getMessage());
        }
    }

    /**
     * Calculate expected score using ELO formula
     */
    private function calculateExpectedScore($rating1, $rating2)
    {
        return 1 / (1 + pow(10, ($rating2 - $rating1) / 400));
    }

    /**
     * Calculate K-factor based on team experience and event tier
     */
    private function calculateKFactor($team, $eventTier)
    {
        $baseKFactor = self::K_FACTORS['regular_match'];
        
        // Adjust for team experience
        if ($team->matches_played < 10) {
            $baseKFactor = self::K_FACTORS['new_team'];
        } elseif ($team->matches_played > 50) {
            $baseKFactor = self::K_FACTORS['experienced_team'];
        }
        
        // Adjust for event tier
        switch ($eventTier) {
            case 'S':
                return self::K_FACTORS['high_tier_event'];
            case 'A':
                return self::K_FACTORS['medium_tier_event'];
            case 'B':
            case 'C':
                return self::K_FACTORS['low_tier_event'];
            default:
                return $baseKFactor;
        }
    }

    /**
     * Calculate bonus/penalty based on map differential
     */
    private function calculateMapDifferentialBonus($score1, $score2)
    {
        $differential = abs($score1 - $score2);
        
        // Bonus points for dominant victories
        if ($differential >= 3) {
            return 3; // 3-0 or similar dominant wins
        } elseif ($differential == 2) {
            return 1; // 3-1 wins
        }
        
        return 0; // Close matches (3-2) get no bonus
    }

    /**
     * Calculate performance modifier for players
     */
    private function calculatePerformanceModifier($performanceRating)
    {
        // Performance rating typically ranges from 0.1 to 2.5
        // Convert to modifier range of -10 to +10
        if ($performanceRating >= 1.5) {
            return 8;   // Exceptional performance
        } elseif ($performanceRating >= 1.25) {
            return 5;   // Good performance
        } elseif ($performanceRating >= 1.0) {
            return 2;   // Average performance
        } elseif ($performanceRating >= 0.75) {
            return -2;  // Below average
        } else {
            return -8;  // Poor performance
        }
    }

    /**
     * Get role-based modifier for player ratings
     */
    private function getRoleModifier($role, $performanceRating)
    {
        // Support players get slight bonus for enabling team performance
        if ($role === 'Strategist' && $performanceRating >= 1.0) {
            return 2;
        }
        
        // Tank players get bonus for damage absorbed and space created
        if ($role === 'Vanguard' && $performanceRating >= 1.0) {
            return 1;
        }
        
        return 0; // DPS players get no role bonus (their impact is already reflected in eliminations)
    }

    /**
     * Calculate K-factor for individual players
     */
    private function calculatePlayerKFactor($player)
    {
        if ($player->total_matches < 10) {
            return 40; // High volatility for new players
        } elseif ($player->total_matches > 100) {
            return 24; // Lower volatility for experienced players
        }
        
        return 32; // Standard K-factor
    }

    /**
     * Get team with comprehensive statistics
     */
    private function getTeamWithStats($teamId)
    {
        return DB::table('teams')
            ->where('id', $teamId)
            ->select([
                'id', 'name', 'elo_rating', 'peak_elo', 'matches_played',
                'wins', 'losses', 'maps_won', 'maps_lost'
            ])
            ->first();
    }

    /**
     * Get player with comprehensive statistics
     */
    private function getPlayerWithStats($playerId)
    {
        return DB::table('players')
            ->where('id', $playerId)
            ->select([
                'id', 'username', 'role', 'elo_rating', 'peak_elo', 'total_matches',
                'total_wins', 'total_eliminations', 'total_deaths', 'total_assists'
            ])
            ->first();
    }

    /**
     * Update team rating and maintain history
     */
    private function updateTeamRating($teamId, $newRating, $ratingChange, $matchId, $changeReason)
    {
        $oldRating = DB::table('teams')->where('id', $teamId)->value('elo_rating');
        $currentPeakElo = DB::table('teams')->where('id', $teamId)->value('peak_elo') ?? 0;
        
        // Update team record
        DB::table('teams')
            ->where('id', $teamId)
            ->update([
                'elo_rating' => $newRating,
                'rating' => $newRating, // Keep both for compatibility
                'peak_elo' => max($currentPeakElo, $newRating),
                'elo_changes' => DB::raw('elo_changes + ' . $ratingChange),
                'last_elo_update' => now(),
                'updated_at' => now()
            ]);
        
        // Record ELO history
        DB::table('elo_history')->insert([
            'ratable_type' => 'App\\Models\\Team',
            'ratable_id' => $teamId,
            'rating_before' => $oldRating,
            'rating_after' => $newRating,
            'rating_change' => $ratingChange,
            'match_id' => $matchId,
            'change_reason' => $changeReason,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Update player rating and maintain history
     */
    private function updatePlayerRating($playerId, $newRating, $ratingChange, $matchId, $changeReason)
    {
        $oldRating = DB::table('players')->where('id', $playerId)->value('elo_rating');
        $currentPeakElo = DB::table('players')->where('id', $playerId)->value('peak_elo') ?? 0;
        
        // Update player record
        DB::table('players')
            ->where('id', $playerId)
            ->update([
                'elo_rating' => $newRating,
                'rating' => $newRating, // Keep both for compatibility
                'peak_elo' => max($currentPeakElo, $newRating),
                'peak_rating' => max($currentPeakElo, $newRating), // Legacy field
                'elo_changes' => DB::raw('elo_changes + ' . $ratingChange),
                'last_elo_update' => now(),
                'updated_at' => now()
            ]);
        
        // Record ELO history
        DB::table('elo_history')->insert([
            'ratable_type' => 'App\\Models\\Player',
            'ratable_id' => $playerId,
            'rating_before' => $oldRating,
            'rating_after' => $newRating,
            'rating_change' => $ratingChange,
            'match_id' => $matchId,
            'change_reason' => $changeReason,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Update match results cache for fast queries
     */
    private function updateMatchResultsCache($matchId, $teamId, $eloBefore, $eloAfter, $eloChange, $result, $teamScore, $opponentScore)
    {
        // Get match date
        $matchDate = DB::table('matches')->where('id', $matchId)->value('scheduled_at');
        
        DB::table('match_results_cache')->insert([
            'match_id' => $matchId,
            'team_id' => $teamId,
            'result' => $result,
            'team_score' => $teamScore,
            'opponent_score' => $opponentScore,
            'map_differential' => $teamScore - $opponentScore,
            'elo_before' => $eloBefore,
            'elo_after' => $eloAfter,
            'elo_change' => $eloChange,
            'match_date' => $matchDate,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Award earnings to team or player
     */
    public function awardEarnings($entityType, $entityId, $amount, $currency = 'USD', $type = 'tournament_prize', $source = null, $matchId = null, $description = null)
    {
        DB::beginTransaction();
        
        try {
            // Get current balance
            $table = $entityType === 'team' ? 'teams' : 'players';
            $currentBalance = DB::table($table)->where('id', $entityId)->value('earnings_amount') ?? 0;
            $newBalance = $currentBalance + $amount;
            
            // Update balance
            DB::table($table)
                ->where('id', $entityId)
                ->update([
                    'earnings_amount' => $newBalance,
                    'earnings_currency' => $currency,
                    'earnings' => $this->formatEarnings($newBalance, $currency), // Legacy string field
                    'updated_at' => now()
                ]);
            
            // Record earnings history
            DB::table('earnings_history')->insert([
                'earnable_type' => $entityType === 'team' ? 'App\\Models\\Team' : 'App\\Models\\Player',
                'earnable_id' => $entityId,
                'amount' => $amount,
                'currency' => $currency,
                'type' => $type,
                'source' => $source,
                'match_id' => $matchId,
                'description' => $description,
                'balance_before' => $currentBalance,
                'balance_after' => $newBalance,
                'awarded_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::commit();
            
            return [
                'previous_balance' => $currentBalance,
                'amount_awarded' => $amount,
                'new_balance' => $newBalance,
                'currency' => $currency
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            throw new \Exception('Failed to award earnings: ' . $e->getMessage());
        }
    }

    /**
     * Format earnings for display
     */
    private function formatEarnings($amount, $currency = 'USD')
    {
        $formatted = number_format($amount, 2);
        
        switch ($currency) {
            case 'USD':
                return '$' . $formatted;
            case 'EUR':
                return '€' . $formatted;
            case 'GBP':
                return '£' . $formatted;
            default:
                return $currency . ' ' . $formatted;
        }
    }

    /**
     * Get division name by ELO rating
     */
    public function getDivisionByRating($rating)
    {
        foreach (self::RATING_DIVISIONS as $division => $threshold) {
            if ($rating >= $threshold) {
                return $division;
            }
        }
        return 'Bronze';
    }

    /**
     * Apply inactivity decay to teams/players who haven't played recently
     */
    public function applyInactivityDecay()
    {
        DB::beginTransaction();
        
        try {
            $decayThreshold = now()->subDays(30); // 30 days of inactivity
            $decayAmount = 25; // Points lost per month of inactivity
            
            // Apply decay to teams
            $inactiveTeams = DB::table('teams')
                ->where('last_elo_update', '<', $decayThreshold)
                ->where('elo_rating', '>', 1000) // Don't decay below starting rating
                ->get();
            
            foreach ($inactiveTeams as $team) {
                $newRating = max(1000, $team->elo_rating - $decayAmount);
                $this->updateTeamRating($team->id, $newRating, -$decayAmount, null, 'inactivity_decay');
            }
            
            // Apply decay to players
            $inactivePlayers = DB::table('players')
                ->where('last_elo_update', '<', $decayThreshold)
                ->where('elo_rating', '>', 1000)
                ->get();
            
            foreach ($inactivePlayers as $player) {
                $newRating = max(1000, $player->elo_rating - $decayAmount);
                $this->updatePlayerRating($player->id, $newRating, -$decayAmount, null, 'inactivity_decay');
            }
            
            DB::commit();
            
            return [
                'teams_decayed' => count($inactiveTeams),
                'players_decayed' => count($inactivePlayers),
                'decay_amount' => $decayAmount
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            throw new \Exception('Failed to apply inactivity decay: ' . $e->getMessage());
        }
    }
}