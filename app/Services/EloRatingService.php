<?php

namespace App\Services;

use App\Models\Team;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EloRatingService
{
    private $kFactor = 32; // K-factor for ELO calculation
    private $initialRating = 1000; // Starting ELO rating (Marvel Rivals default)
    
    // Marvel Rivals rank thresholds
    const RANK_THRESHOLDS = [
        'one_above_all' => 5000,
        'eternity' => 4600,
        'celestial' => 3700,
        'grandmaster' => 2800,
        'diamond' => 1900,
        'platinum' => 1000,
        'gold' => 700,
        'silver' => 400,
        'bronze' => 0
    ];
    
    public function calculateMatchElo(GameMatch $match)
    {
        if (!$match->winner_id || $match->status !== 'completed') {
            return;
        }
        
        $team1 = Team::find($match->team1_id);
        $team2 = Team::find($match->team2_id);
        
        if (!$team1 || !$team2) {
            return;
        }
        
        // Get current ratings
        $rating1 = $team1->elo_rating ?? $this->initialRating;
        $rating2 = $team2->elo_rating ?? $this->initialRating;
        
        // Calculate expected scores
        $expected1 = $this->calculateExpectedScore($rating1, $rating2);
        $expected2 = 1 - $expected1;
        
        // Actual scores (1 for win, 0 for loss)
        $actual1 = $match->winner_id == $team1->id ? 1 : 0;
        $actual2 = $match->winner_id == $team2->id ? 1 : 0;
        
        // Calculate new ratings
        $newRating1 = $rating1 + $this->kFactor * ($actual1 - $expected1);
        $newRating2 = $rating2 + $this->kFactor * ($actual2 - $expected2);
        
        // Apply score difference modifier (optional - makes blowouts worth more)
        $scoreDiff = abs($match->team1_score - $match->team2_score);
        $modifier = 1 + ($scoreDiff * 0.1); // 10% bonus per map difference
        
        if ($match->winner_id == $team1->id) {
            $newRating1 = $rating1 + ($this->kFactor * ($actual1 - $expected1) * $modifier);
            $newRating2 = $rating2 + ($this->kFactor * ($actual2 - $expected2) * $modifier);
        } else {
            $newRating1 = $rating1 + ($this->kFactor * ($actual1 - $expected1) * $modifier);
            $newRating2 = $rating2 + ($this->kFactor * ($actual2 - $expected2) * $modifier);
        }
        
        // Update team ratings
        $team1->update(['elo_rating' => round($newRating1)]);
        $team2->update(['elo_rating' => round($newRating2)]);
        
        // Store rating history
        $this->storeRatingHistory($team1, $rating1, $newRating1, $match);
        $this->storeRatingHistory($team2, $rating2, $newRating2, $match);
        
        // Update player ratings
        $this->updatePlayerRatings($match);
    }
    
    private function calculateExpectedScore($rating1, $rating2)
    {
        return 1 / (1 + pow(10, ($rating2 - $rating1) / 400));
    }
    
    private function storeRatingHistory($team, $oldRating, $newRating, $match)
    {
        DB::table('team_elo_history')->insert([
            'team_id' => $team->id,
            'match_id' => $match->id,
            'old_rating' => round($oldRating),
            'new_rating' => round($newRating),
            'rating_change' => round($newRating - $oldRating),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    public function recalculateAllRatings()
    {
        // Reset all team ratings
        Team::query()->update(['elo_rating' => $this->initialRating]);
        
        // Clear rating history
        DB::table('team_elo_history')->truncate();
        
        // Get all completed matches ordered by date
        $matches = GameMatch::where('status', 'completed')
            ->whereNotNull('winner_id')
            ->orderBy('match_date', 'asc')
            ->get();
        
        // Recalculate ratings for each match
        foreach ($matches as $match) {
            $this->calculateMatchElo($match);
        }
        
        Log::info("Recalculated ELO ratings for {$matches->count()} matches");
    }
    
    public function updatePlayerRatings(GameMatch $match)
    {
        // Get players from both teams
        $team1Players = Player::where('team_id', $match->team1_id)->get();
        $team2Players = Player::where('team_id', $match->team2_id)->get();
        
        $winningTeamId = $match->winner_id;
        
        // Update player ratings based on match outcome
        foreach ($team1Players as $player) {
            $this->updatePlayerElo($player, $match->team1_id == $winningTeamId);
        }
        
        foreach ($team2Players as $player) {
            $this->updatePlayerElo($player, $match->team2_id == $winningTeamId);
        }
    }
    
    private function updatePlayerElo($player, $won)
    {
        $currentRating = $player->elo_rating ?? $this->initialRating;
        
        // Simplified player rating update
        if ($won) {
            $newRating = $currentRating + 10; // Winners gain 10 points
        } else {
            $newRating = $currentRating - 10; // Losers lose 10 points
        }
        
        // Minimum rating floor
        $newRating = max(1000, $newRating);
        
        $player->update(['elo_rating' => round($newRating)]);
    }
    
    public function getTeamRankings($limit = null)
    {
        $query = Team::where('status', 'active')
            ->where('game', 'marvel_rivals')
            ->orderBy('elo_rating', 'desc');
        
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }
    
    public function getPlayerRankingsByRole($role, $limit = 50)
    {
        return Player::where('role', $role)
            ->where('status', 'active')
            ->whereHas('team', function ($query) {
                $query->where('game', 'marvel_rivals');
            })
            ->orderBy('elo_rating', 'desc')
            ->limit($limit)
            ->get();
    }
    
    public function getRegionalRankings($region)
    {
        return Team::where('region', $region)
            ->where('status', 'active')
            ->where('game', 'marvel_rivals')
            ->orderBy('elo_rating', 'desc')
            ->get();
    }
    
    public function predictMatchOutcome($team1Id, $team2Id)
    {
        $team1 = Team::find($team1Id);
        $team2 = Team::find($team2Id);
        
        if (!$team1 || !$team2) {
            return null;
        }
        
        $rating1 = $team1->elo_rating ?? $this->initialRating;
        $rating2 = $team2->elo_rating ?? $this->initialRating;
        
        $probability1 = $this->calculateExpectedScore($rating1, $rating2);
        $probability2 = 1 - $probability1;
        
        return [
            'team1' => [
                'name' => $team1->name,
                'rating' => $rating1,
                'win_probability' => round($probability1 * 100, 1)
            ],
            'team2' => [
                'name' => $team2->name,
                'rating' => $rating2,
                'win_probability' => round($probability2 * 100, 1)
            ],
            'favorite' => $probability1 > $probability2 ? $team1->name : $team2->name,
            'underdog' => $probability1 > $probability2 ? $team2->name : $team1->name
        ];
    }
    
    /**
     * Get Marvel Rivals rank based on rating
     */
    public function getRankByRating($rating)
    {
        foreach (self::RANK_THRESHOLDS as $rank => $threshold) {
            if ($rating >= $threshold) {
                return $rank;
            }
        }
        return 'bronze';
    }
    
    /**
     * Get rank division (I, II, III) based on rating within rank
     */
    public function getDivisionByRating($rating)
    {
        $rank = $this->getRankByRating($rating);
        
        // Special ranks with no divisions
        if (in_array($rank, ['one_above_all', 'eternity'])) {
            return null;
        }
        
        $rankData = $this->getRankData($rank);
        if (!$rankData) {
            return 'III';
        }
        
        $min = $rankData['min'];
        $max = $rankData['max'];
        $range = $max - $min;
        $divisionSize = $range / 3;
        
        $position = $rating - $min;
        
        if ($position < $divisionSize) return 'III';
        if ($position < $divisionSize * 2) return 'II';
        return 'I';
    }
    
    /**
     * Apply Marvel Rivals season reset (9 divisions down)
     */
    public function applySeasonReset($currentRating)
    {
        return max(0, $currentRating - 900); // 9 divisions = 900 points
    }
    
    /**
     * Get rank distribution for Marvel Rivals
     */
    public function getRankDistribution()
    {
        $distribution = [];
        
        foreach (array_reverse(self::RANK_THRESHOLDS, true) as $rank => $threshold) {
            $nextThreshold = $this->getNextRankThreshold($rank);
            
            if ($nextThreshold) {
                $count = DB::table('players')
                    ->whereBetween('elo_rating', [$threshold, $nextThreshold - 1])
                    ->count();
            } else {
                $count = DB::table('players')
                    ->where('elo_rating', '>=', $threshold)
                    ->count();
            }
            
            $distribution[] = [
                'rank' => $rank,
                'name' => ucfirst(str_replace('_', ' ', $rank)),
                'count' => $count,
                'threshold' => $threshold
            ];
        }
        
        return $distribution;
    }
    
    /**
     * Get Marvel Rivals specific features based on rank
     */
    public function getMarvelRivalsFeatures($rating)
    {
        return [
            'hero_bans_unlocked' => $rating >= 700, // Gold III+
            'chrono_shield_available' => $rating <= 1000, // Gold rank and below
            'rank_decay_eligible' => $rating >= 4600, // Eternity/One Above All
            'team_restrictions' => $this->getTeamRestrictions($rating)
        ];
    }
    
    /**
     * Get team restrictions based on rating
     */
    public function getTeamRestrictions($rating)
    {
        if ($rating <= 1000) { // Gold and below
            return 'Can team with anyone';
        }
        
        if ($rating >= 1000 && $rating < 4600) { // Gold I to Celestial
            return 'Within 3 divisions';
        }
        
        if ($rating >= 4600) { // Eternity/One Above All
            return 'Solo/Duo only, Celestial II+ within 200 points';
        }
        
        return 'Standard restrictions';
    }
    
    /**
     * Bulk season reset for all players
     */
    public function performBulkSeasonReset()
    {
        $updated = 0;
        
        DB::table('players')
            ->where('elo_rating', '>', 0)
            ->chunkById(100, function ($players) use (&$updated) {
                foreach ($players as $player) {
                    $newRating = $this->applySeasonReset($player->elo_rating);
                    if ($newRating !== $player->elo_rating) {
                        DB::table('players')
                            ->where('id', $player->id)
                            ->update([
                                'elo_rating' => $newRating,
                                'peak_rating' => max($player->peak_rating ?? 0, $player->elo_rating),
                                'updated_at' => now()
                            ]);
                        $updated++;
                    }
                }
            });
        
        return $updated;
    }
    
    /**
     * Get comprehensive ranking statistics
     */
    public function getComprehensiveStats()
    {
        return [
            'total_players' => DB::table('players')->count(),
            'total_teams' => DB::table('teams')->count(),
            'average_rating' => round(DB::table('players')->avg('elo_rating') ?? $this->initialRating),
            'highest_rating' => DB::table('players')->max('elo_rating') ?? $this->initialRating,
            'one_above_all_count' => DB::table('players')->where('elo_rating', '>=', 5000)->count(),
            'eternity_count' => DB::table('players')->whereBetween('elo_rating', [4600, 4999])->count(),
            'celestial_plus_count' => DB::table('players')->where('elo_rating', '>=', 3700)->count(),
            'hero_bans_unlocked' => DB::table('players')->where('elo_rating', '>=', 700)->count(),
            'chrono_shield_eligible' => DB::table('players')->where('elo_rating', '<=', 1000)->count(),
            'rank_distribution' => $this->getRankDistribution()
        ];
    }
    
    // Private helper methods
    
    private function getRankData($rank)
    {
        $ranks = [
            'celestial' => ['min' => 3700, 'max' => 4600],
            'grandmaster' => ['min' => 2800, 'max' => 3700],
            'diamond' => ['min' => 1900, 'max' => 2800],
            'platinum' => ['min' => 1000, 'max' => 1900],
            'gold' => ['min' => 700, 'max' => 1000],
            'silver' => ['min' => 400, 'max' => 700],
            'bronze' => ['min' => 0, 'max' => 400]
        ];
        
        return $ranks[$rank] ?? null;
    }
    
    private function getNextRankThreshold($rank)
    {
        $ranks = array_keys(self::RANK_THRESHOLDS);
        $currentIndex = array_search($rank, $ranks);
        
        if ($currentIndex !== false && isset($ranks[$currentIndex + 1])) {
            return self::RANK_THRESHOLDS[$ranks[$currentIndex + 1]];
        }
        
        return null;
    }
}