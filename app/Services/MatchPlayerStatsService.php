<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MatchPlayerStatsService
{
    /**
     * Sync player stats from match maps_data to match_player_stats table
     * This ensures ALL players appear with their heroes and stats (even if 0)
     */
    public function syncMatchStats($matchId)
    {
        try {
            $match = DB::table('matches')->where('id', $matchId)->first();
            
            if (!$match) {
                Log::warning("Match {$matchId} not found for stats sync");
                return false;
            }
            
            // Parse maps_data
            $mapsData = is_string($match->maps_data) ? json_decode($match->maps_data, true) : $match->maps_data;
            if (!$mapsData || !is_array($mapsData)) {
                Log::warning("No valid maps_data for match {$matchId}");
                return false;
            }
            
            // Delete existing stats for this match to avoid duplicates
            DB::table('match_player_stats')->where('match_id', $matchId)->delete();
            
            $statsToInsert = [];
            $processedPlayers = [];
            
            // Process each map
            foreach ($mapsData as $mapIndex => $map) {
                $mapNumber = $mapIndex + 1;
                
                // Process team1 composition
                if (isset($map['team1_composition']) && is_array($map['team1_composition'])) {
                    foreach ($map['team1_composition'] as $player) {
                        if (!isset($player['player_id']) || !$player['player_id']) continue;
                        
                        $playerId = $player['player_id'];
                        $playerKey = $playerId . '_' . $mapNumber . '_' . ($player['hero'] ?? 'Unknown');
                        
                        // Skip if we've already processed this player/map/hero combination
                        if (isset($processedPlayers[$playerKey])) continue;
                        $processedPlayers[$playerKey] = true;
                        
                        // Add main hero stats
                        $statsToInsert[] = $this->createStatRecord(
                            $matchId,
                            $playerId,
                            $match->team1_id,
                            $player['hero'] ?? 'Spider-Man', // Default to Spider-Man if no hero
                            $player,
                            $mapNumber
                        );
                        
                        // Process hero changes if any
                        if (isset($player['hero_changes']) && is_array($player['hero_changes'])) {
                            foreach ($player['hero_changes'] as $change) {
                                $changeKey = $playerId . '_' . $mapNumber . '_' . ($change['hero'] ?? 'Unknown');
                                if (!isset($processedPlayers[$changeKey])) {
                                    $processedPlayers[$changeKey] = true;
                                    $statsToInsert[] = $this->createStatRecord(
                                        $matchId,
                                        $playerId,
                                        $match->team1_id,
                                        $change['hero'] ?? 'Spider-Man',
                                        $change,
                                        $mapNumber
                                    );
                                }
                            }
                        }
                    }
                }
                
                // Process team2 composition
                if (isset($map['team2_composition']) && is_array($map['team2_composition'])) {
                    foreach ($map['team2_composition'] as $player) {
                        if (!isset($player['player_id']) || !$player['player_id']) continue;
                        
                        $playerId = $player['player_id'];
                        $playerKey = $playerId . '_' . $mapNumber . '_' . ($player['hero'] ?? 'Unknown');
                        
                        // Skip if we've already processed this player/map/hero combination
                        if (isset($processedPlayers[$playerKey])) continue;
                        $processedPlayers[$playerKey] = true;
                        
                        // Add main hero stats
                        $statsToInsert[] = $this->createStatRecord(
                            $matchId,
                            $playerId,
                            $match->team2_id,
                            $player['hero'] ?? 'Spider-Man', // Default to Spider-Man if no hero
                            $player,
                            $mapNumber
                        );
                        
                        // Process hero changes if any
                        if (isset($player['hero_changes']) && is_array($player['hero_changes'])) {
                            foreach ($player['hero_changes'] as $change) {
                                $changeKey = $playerId . '_' . $mapNumber . '_' . ($change['hero'] ?? 'Unknown');
                                if (!isset($processedPlayers[$changeKey])) {
                                    $processedPlayers[$changeKey] = true;
                                    $statsToInsert[] = $this->createStatRecord(
                                        $matchId,
                                        $playerId,
                                        $match->team2_id,
                                        $change['hero'] ?? 'Spider-Man',
                                        $change,
                                        $mapNumber
                                    );
                                }
                            }
                        }
                    }
                }
            }
            
            // Insert all stats
            if (!empty($statsToInsert)) {
                DB::table('match_player_stats')->insert($statsToInsert);
                Log::info("Synced " . count($statsToInsert) . " player stat records for match {$matchId}");
                
                // Clear related caches
                $this->clearRelatedCaches($statsToInsert);
                
                return true;
            }
            
            Log::warning("No player stats to sync for match {$matchId}");
            return false;
            
        } catch (\Exception $e) {
            Log::error("Error syncing match stats for match {$matchId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a stat record with proper defaults
     */
    private function createStatRecord($matchId, $playerId, $teamId, $hero, $playerData, $mapNumber)
    {
        $eliminations = $playerData['eliminations'] ?? 0;
        $deaths = $playerData['deaths'] ?? 0;
        $assists = $playerData['assists'] ?? 0;
        
        return [
            'match_id' => $matchId,
            'player_id' => $playerId,
            'team_id' => $teamId,
            'hero' => $hero,
            'eliminations' => $eliminations,
            'assists' => $assists,
            'deaths' => $deaths,
            'damage_dealt' => $playerData['damage'] ?? 0,
            'damage_taken' => $playerData['damage_taken'] ?? 0,
            'healing_done' => $playerData['healing'] ?? 0,
            'healing_received' => $playerData['healing_received'] ?? 0,
            'damage_blocked' => $playerData['damage_blocked'] ?? 0,
            'ultimates_used' => $playerData['ultimate_usage'] ?? 0,
            'time_played' => $playerData['objective_time'] ?? 0,
            'objective_time' => $playerData['objective_time'] ?? 0,
            'kda_ratio' => $deaths > 0 ? 
                round(($eliminations + $assists) / $deaths, 2) : 
                ($eliminations + $assists),
            'mvp_score' => $this->calculateMvpScore($playerData),
            'is_mvp' => 0, // Will be set later in batch
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
    
    /**
     * Calculate MVP score for a player
     */
    private function calculateMvpScore($playerData)
    {
        $eliminations = $playerData['eliminations'] ?? 0;
        $assists = $playerData['assists'] ?? 0;
        $deaths = max($playerData['deaths'] ?? 1, 1); // Avoid division by zero
        $damage = ($playerData['damage'] ?? 0) / 1000; // Scale damage
        $healing = ($playerData['healing'] ?? 0) / 1000; // Scale healing
        $objectiveTime = ($playerData['objective_time'] ?? 0) / 10; // Scale objective time
        
        // MVP score formula
        $score = ($eliminations * 2) + $assists + ($damage * 0.5) + ($healing * 0.3) + $objectiveTime - ($deaths * 0.5);
        
        return round(max($score, 0), 2);
    }
    
    /**
     * Clear caches related to affected players
     */
    private function clearRelatedCaches($stats)
    {
        $playerIds = array_unique(array_column($stats, 'player_id'));
        
        foreach ($playerIds as $playerId) {
            // Clear player-specific caches
            Cache::forget("player_detail_{$playerId}");
            Cache::forget("player_stats_{$playerId}");
            Cache::forget("player_match_history_{$playerId}");
            
            // Use tags if available in your cache configuration
            try {
                Cache::tags(['player', "player_{$playerId}"])->flush();
            } catch (\Exception $e) {
                // Tags might not be supported with all cache drivers
            }
        }
        
        Log::info("Cleared caches for " . count($playerIds) . " players");
    }
    
    /**
     * Sync stats for all matches (bulk operation)
     */
    public function syncAllMatchStats()
    {
        $matches = DB::table('matches')
            ->where('status', 'completed')
            ->pluck('id');
            
        $synced = 0;
        foreach ($matches as $matchId) {
            if ($this->syncMatchStats($matchId)) {
                $synced++;
            }
        }
        
        Log::info("Synced stats for {$synced} out of " . $matches->count() . " matches");
        return $synced;
    }
}