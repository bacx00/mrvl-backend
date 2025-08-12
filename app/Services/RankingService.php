<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Team;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RankingService
{
    protected $eloService;
    
    public function __construct(EloRatingService $eloService)
    {
        $this->eloService = $eloService;
    }

    /**
     * Update rankings after a match completion
     */
    public function updateRatingsFromMatch(GameMatch $match): array
    {
        try {
            if (!$match->isCompleted() || !$match->winner_id) {
                return ['success' => false, 'message' => 'Match not completed or no winner'];
            }

            DB::beginTransaction();

            $changes = [];

            // Update team ratings if both teams exist
            if ($match->team1_id && $match->team2_id) {
                $teamChanges = $this->updateTeamRatingsFromMatch($match);
                $changes['teams'] = $teamChanges;
            }

            // Update player ratings
            $playerChanges = $this->updatePlayerRatingsFromMatch($match);
            $changes['players'] = $playerChanges;

            // Clear relevant caches
            $this->clearRankingCaches($changes);

            DB::commit();

            Log::info('Rankings updated from match', [
                'match_id' => $match->id,
                'teams_updated' => count($changes['teams'] ?? []),
                'players_updated' => count($changes['players'] ?? [])
            ]);

            return ['success' => true, 'changes' => $changes];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update rankings from match', [
                'match_id' => $match->id,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update team ratings from match result
     */
    protected function updateTeamRatingsFromMatch(GameMatch $match): array
    {
        $team1 = Team::find($match->team1_id);
        $team2 = Team::find($match->team2_id);
        
        if (!$team1 || !$team2) {
            return [];
        }

        $rating1 = $team1->rating ?? 1000;
        $rating2 = $team2->rating ?? 1000;
        
        $isTeam1Winner = $match->winner_id === $team1->id;
        $kFactor = $this->calculateKFactor($match);

        // Calculate new ratings using ELO service
        $newRatings = $this->eloService->calculateNewRatings(
            $rating1,
            $rating2,
            $isTeam1Winner ? 1 : 0,
            $kFactor
        );

        $changes = [];

        // Update team 1
        if ($newRatings['team1_new_rating'] !== $rating1) {
            $team1->update(['rating' => $newRatings['team1_new_rating']]);
            $this->recordTeamRatingHistory($team1->id, $rating1, $newRatings['team1_new_rating'], $match->id);
            $changes[$team1->id] = [
                'old_rating' => $rating1,
                'new_rating' => $newRatings['team1_new_rating'],
                'change' => $newRatings['team1_new_rating'] - $rating1
            ];
        }

        // Update team 2
        if ($newRatings['team2_new_rating'] !== $rating2) {
            $team2->update(['rating' => $newRatings['team2_new_rating']]);
            $this->recordTeamRatingHistory($team2->id, $rating2, $newRatings['team2_new_rating'], $match->id);
            $changes[$team2->id] = [
                'old_rating' => $rating2,
                'new_rating' => $newRatings['team2_new_rating'],
                'change' => $newRatings['team2_new_rating'] - $rating2
            ];
        }

        return $changes;
    }

    /**
     * Update player ratings from match result
     */
    protected function updatePlayerRatingsFromMatch(GameMatch $match): array
    {
        $changes = [];
        
        // Get players from both teams
        $team1Players = Player::where('team_id', $match->team1_id)->get();
        $team2Players = Player::where('team_id', $match->team2_id)->get();
        
        $isTeam1Winner = $match->winner_id === $match->team1_id;

        // Update team 1 players
        foreach ($team1Players as $player) {
            $change = $this->updatePlayerRating($player, $isTeam1Winner, $match->id);
            if ($change) {
                $changes[$player->id] = $change;
            }
        }

        // Update team 2 players
        foreach ($team2Players as $player) {
            $change = $this->updatePlayerRating($player, !$isTeam1Winner, $match->id);
            if ($change) {
                $changes[$player->id] = $change;
            }
        }

        return $changes;
    }

    /**
     * Update individual player rating
     */
    protected function updatePlayerRating(Player $player, bool $won, int $matchId): ?array
    {
        $oldRating = $player->rating ?? 1000;
        
        // Simple player rating update (can be enhanced)
        $ratingChange = $won ? 15 : -12; // Winners gain more than losers lose
        
        // Apply performance modifiers based on role and other factors
        $ratingChange = $this->applyPlayerModifiers($player, $ratingChange, $won);
        
        $newRating = max(0, $oldRating + $ratingChange);
        
        if ($newRating !== $oldRating) {
            // Update peak rating if necessary
            $peakRating = max($player->peak_rating ?? 0, $newRating);
            
            $player->update([
                'rating' => $newRating,
                'peak_rating' => $peakRating
            ]);
            
            $this->recordPlayerRatingHistory($player->id, $oldRating, $newRating, $matchId);
            
            return [
                'old_rating' => $oldRating,
                'new_rating' => $newRating,
                'change' => $ratingChange
            ];
        }

        return null;
    }

    /**
     * Apply modifiers to player rating changes
     */
    protected function applyPlayerModifiers(Player $player, int $baseChange, bool $won): int
    {
        $modifier = 1.0;
        
        // Role-based modifiers
        switch ($player->role) {
            case 'Strategist':
                $modifier *= $won ? 1.1 : 0.9; // Strategists get more credit for wins
                break;
            case 'Vanguard':
                $modifier *= 1.0; // Neutral
                break;
            case 'Duelist':
                $modifier *= $won ? 1.05 : 0.95; // Slight bonus for carry performance
                break;
        }
        
        // Rating bracket modifiers
        $currentRating = $player->rating ?? 1000;
        if ($currentRating >= 3700) { // Celestial+
            $modifier *= 0.8; // Slower changes at high ratings
        } elseif ($currentRating <= 700) { // Gold and below
            $modifier *= 1.2; // Faster progression at low ratings
        }
        
        return round($baseChange * $modifier);
    }

    /**
     * Record player rating history
     */
    protected function recordPlayerRatingHistory(int $playerId, int $oldRating, int $newRating, int $matchId): void
    {
        DB::table('player_rating_history')->insert([
            'player_id' => $playerId,
            'old_rating' => $oldRating,
            'new_rating' => $newRating,
            'rating_change' => $newRating - $oldRating,
            'match_id' => $matchId,
            'reason' => 'match_result',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Record team rating history
     */
    protected function recordTeamRatingHistory(int $teamId, int $oldRating, int $newRating, int $matchId): void
    {
        DB::table('team_rating_history')->insert([
            'team_id' => $teamId,
            'old_rating' => $oldRating,
            'new_rating' => $newRating,
            'rating_change' => $newRating - $oldRating,
            'match_id' => $matchId,
            'reason' => 'match_result',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Calculate K-factor based on match importance
     */
    protected function calculateKFactor(GameMatch $match): int
    {
        // Base K-factor
        $kFactor = 24;
        
        // Adjust based on tournament tier if available
        if ($match->event_id && $match->event) {
            $kFactor = match($match->event->tier ?? 'B') {
                'S' => 40, // Premier tournaments
                'A' => 32, // Major tournaments  
                'B' => 24, // Standard tournaments
                'C' => 16, // Minor tournaments
                default => 24
            };
        }
        
        return $kFactor;
    }

    /**
     * Get player ranking history
     */
    public function getPlayerRankingHistory(int $playerId, int $days = 30): array
    {
        return DB::table('player_rating_history')
            ->where('player_id', $playerId)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->created_at,
                    'rating' => $record->new_rating,
                    'change' => $record->rating_change,
                    'reason' => $record->reason
                ];
            })
            ->toArray();
    }

    /**
     * Get team ranking history
     */
    public function getTeamRankingHistory(int $teamId, int $days = 30): array
    {
        return DB::table('team_rating_history')
            ->where('team_id', $teamId)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->created_at,
                    'rating' => $record->new_rating,
                    'change' => $record->rating_change,
                    'reason' => $record->reason
                ];
            })
            ->toArray();
    }

    /**
     * Clear ranking-related caches
     */
    protected function clearRankingCaches(array $changes): void
    {
        $cachePatterns = [
            'player_rankings_*',
            'team_rankings_*',
            'leaderboard_*',
            'ranking_stats',
            'rank_distribution'
        ];

        foreach ($cachePatterns as $pattern) {
            Cache::flush($pattern);
        }

        // Clear specific region caches if we know which regions were affected
        if (isset($changes['players'])) {
            foreach ($changes['players'] as $playerId => $change) {
                $player = Player::find($playerId);
                if ($player && $player->region) {
                    Cache::forget("rankings_region_{$player->region}");
                }
            }
        }
    }

    /**
     * Create daily rating snapshots for historical leaderboards
     */
    public function createDailySnapshots(): void
    {
        $date = now()->toDateString();
        
        // Create player snapshots
        $players = Player::orderBy('rating', 'desc')->get();
        foreach ($players as $index => $player) {
            DB::table('rating_snapshots')->updateOrInsert([
                'type' => 'player',
                'entity_id' => $player->id,
                'period' => 'daily',
                'snapshot_date' => $date
            ], [
                'rating' => $player->rating,
                'global_rank' => $index + 1,
                'region_rank' => $this->getPlayerRegionRank($player),
                'region' => $player->region,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Create team snapshots
        $teams = Team::orderBy('rating', 'desc')->get();
        foreach ($teams as $index => $team) {
            DB::table('rating_snapshots')->updateOrInsert([
                'type' => 'team',
                'entity_id' => $team->id,
                'period' => 'daily',
                'snapshot_date' => $date
            ], [
                'rating' => $team->rating,
                'global_rank' => $index + 1,
                'region_rank' => $this->getTeamRegionRank($team),
                'region' => $team->region,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        Log::info('Daily rating snapshots created', [
            'date' => $date,
            'players' => $players->count(),
            'teams' => $teams->count()
        ]);
    }

    /**
     * Get player's region rank
     */
    protected function getPlayerRegionRank(Player $player): int
    {
        return Player::where('region', $player->region)
            ->where('rating', '>', $player->rating)
            ->count() + 1;
    }

    /**
     * Get team's region rank
     */
    protected function getTeamRegionRank(Team $team): int
    {
        return Team::where('region', $team->region)
            ->where('rating', '>', $team->rating)
            ->count() + 1;
    }

    /**
     * Get leaderboard with historical data
     */
    public function getLeaderboard(string $type = 'players', int $limit = 50, string $period = 'current'): array
    {
        try {
            if ($period === 'current') {
                return $this->getCurrentLeaderboard($type, $limit);
            } else {
                return $this->getHistoricalLeaderboard($type, $limit, $period);
            }
        } catch (\Exception $e) {
            Log::error('Leaderboard fetch failed', [
                'type' => $type,
                'limit' => $limit,
                'period' => $period,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get current leaderboard
     */
    protected function getCurrentLeaderboard(string $type, int $limit): array
    {
        if ($type === 'players') {
            return Player::with('team')
                ->orderBy('rating', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        } else {
            return Team::orderBy('rating', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        }
    }

    /**
     * Get historical leaderboard from snapshots
     */
    protected function getHistoricalLeaderboard(string $type, int $limit, string $period): array
    {
        $date = match($period) {
            'yesterday' => now()->subDay()->toDateString(),
            'week_ago' => now()->subWeek()->toDateString(),
            'month_ago' => now()->subMonth()->toDateString(),
            default => now()->toDateString()
        };

        return DB::table('rating_snapshots')
            ->where('type', $type)
            ->where('period', 'daily')
            ->where('snapshot_date', $date)
            ->orderBy('global_rank')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}