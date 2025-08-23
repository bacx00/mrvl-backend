<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\MvrlMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerMatchHistoryController extends Controller
{
    /**
     * Get player profile with statistics
     */
    public function getPlayerProfile($playerId)
    {
        $player = Player::with('team')->find($playerId);
        
        if (!$player) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        // Calculate overall statistics
        $stats = $this->calculatePlayerStats($playerId);
        
        // Calculate player rank based on ELO rating
        $playerRank = \DB::table('players')
            ->where('elo_rating', '>', $player->elo_rating ?? 1000)
            ->count() + 1;
        
        return response()->json([
            'data' => [
                'id' => $player->id,
                'username' => $player->username,
                'name' => $player->name,
                'real_name' => $player->real_name,
                'country' => $player->country,
                'team' => $player->team,
                'current_team' => $player->team, // Add current_team for frontend compatibility
                'team_id' => $player->team_id,
                'role' => $player->role,
                'main_hero' => $player->main_hero,
                'avatar' => $player->avatar,
                'region' => $player->region,
                'rating' => $player->rating,
                'elo_rating' => $player->elo_rating,
                'rank' => $playerRank,
                'age' => $player->age,
                'status' => $player->status,
                'earnings' => $player->earnings,
                'total_earnings' => $player->earnings,
                'total_matches' => $stats['total_matches'],
                'win_rate' => $stats['win_rate'],
                'overall_kda' => $stats['overall_kda'],
                'hero_stats' => $stats['hero_stats'],
                'social_media' => is_string($player->social_media) ? json_decode($player->social_media, true) : ($player->social_media ?? []),
                'twitter' => $player->twitter,
                'twitch' => $player->twitch,
                'instagram' => $player->instagram,
                'youtube' => $player->youtube,
                'discord' => $player->discord,
                'tiktok' => $player->tiktok
            ]
        ]);
    }

    /**
     * Get player match history with pagination and hero performance
     */
    public function getPlayerMatches($playerId, Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = 10;
        
        // Get matches where this player participated (check all possible field names)
        $matches = MvrlMatch::with(['team1', 'team2', 'event'])
            ->where(function($query) use ($playerId) {
                $query->whereJsonContains('maps_data', ['team1_composition' => [['player_id' => $playerId]]])
                      ->orWhereJsonContains('maps_data', ['team2_composition' => [['player_id' => $playerId]]])
                      ->orWhereJsonContains('maps_data', ['team1_composition' => [['id' => $playerId]]])
                      ->orWhereJsonContains('maps_data', ['team2_composition' => [['id' => $playerId]]])
                      ->orWhereJsonContains('maps_data', ['team1_players' => [['player_id' => $playerId]]])
                      ->orWhereJsonContains('maps_data', ['team2_players' => [['player_id' => $playerId]]])
                      ->orWhereJsonContains('maps_data', ['team1_players' => [['id' => $playerId]]])
                      ->orWhereJsonContains('maps_data', ['team2_players' => [['id' => $playerId]]]);
            })
            ->orderBy('scheduled_at', 'desc')
            ->paginate($perPage);

        // Transform matches with player-specific data
        $transformedMatches = $matches->map(function($match) use ($playerId) {
            return $this->transformMatchForPlayer($match, $playerId);
        });

        return response()->json([
            'matches' => $transformedMatches,
            'current_page' => $matches->currentPage(),
            'total_pages' => $matches->lastPage(),
            'total' => $matches->total()
        ]);
    }

    /**
     * Transform match data for specific player view
     */
    private function transformMatchForPlayer($match, $playerId)
    {
        $playerMaps = [];
        $totalEliminations = 0;
        $totalDeaths = 0;
        $totalAssists = 0;
        $totalDamage = 0;
        $totalHealing = 0;
        $totalBlocked = 0;
        $playerWon = false;
        
        // Process each map to extract player-specific data
        if ($match->maps_data) {
            foreach ($match->maps_data as $map) {
                $mapData = $this->extractPlayerMapData($map, $playerId);
                if ($mapData) {
                    $playerMaps[] = $mapData;
                    $totalEliminations += $mapData['eliminations'];
                    $totalDeaths += $mapData['deaths'];
                    $totalAssists += $mapData['assists'];
                    $totalDamage += $mapData['damage'];
                    $totalHealing += $mapData['healing'];
                    $totalBlocked += $mapData['damage_blocked'];
                    
                    // Determine if player won
                    if ($mapData['player_team'] === 'team1') {
                        $playerWon = $match->team1_score > $match->team2_score;
                    } else {
                        $playerWon = $match->team2_score > $match->team1_score;
                    }
                }
            }
        }

        return [
            'id' => $match->id,
            'event' => $match->event,
            'team1' => $match->team1,
            'team2' => $match->team2,
            'team1_score' => $match->team1_score,
            'team2_score' => $match->team2_score,
            'status' => $match->status,
            'format' => $match->format,
            'played_at' => $match->scheduled_at,
            'player_won' => $playerWon,
            'player_maps' => $playerMaps,
            'total_eliminations' => $totalEliminations,
            'total_deaths' => $totalDeaths,
            'total_assists' => $totalAssists,
            'total_damage' => $totalDamage,
            'total_healing' => $totalHealing,
            'total_blocked' => $totalBlocked
        ];
    }

    /**
     * Extract player-specific data from a map
     */
    private function extractPlayerMapData($map, $playerId)
    {
        // Check team1 players (handle both field names)
        $team1Players = $map['team1_players'] ?? $map['team1_composition'] ?? [];
        if (!empty($team1Players)) {
            foreach ($team1Players as $player) {
                $playerIdField = $player['id'] ?? $player['player_id'] ?? null;
                if ($playerIdField == $playerId) {
                    return [
                        'map_name' => $map['map_name'] ?? 'Unknown Map',
                        'winner' => $map['winner'] ?? null,
                        'player_team' => 'team1',
                        'hero' => $player['hero'] ?? 'Unknown',
                        'eliminations' => $player['eliminations'] ?? 0,
                        'deaths' => $player['deaths'] ?? 0,
                        'assists' => $player['assists'] ?? 0,
                        'damage' => $player['damage'] ?? 0,
                        'healing' => $player['healing'] ?? 0,
                        'damage_blocked' => $player['damage_blocked'] ?? 0,
                        'ultimate_charge' => $player['ultimate_charge'] ?? 0
                    ];
                }
            }
        }
        
        // Check team2 players (handle both field names)
        $team2Players = $map['team2_players'] ?? $map['team2_composition'] ?? [];
        if (!empty($team2Players)) {
            foreach ($team2Players as $player) {
                $playerIdField = $player['id'] ?? $player['player_id'] ?? null;
                if ($playerIdField == $playerId) {
                    return [
                        'map_name' => $map['map_name'] ?? 'Unknown Map',
                        'winner' => $map['winner'] ?? null,
                        'player_team' => 'team2',
                        'hero' => $player['hero'] ?? 'Unknown',
                        'eliminations' => $player['eliminations'] ?? 0,
                        'deaths' => $player['deaths'] ?? 0,
                        'assists' => $player['assists'] ?? 0,
                        'damage' => $player['damage'] ?? 0,
                        'healing' => $player['healing'] ?? 0,
                        'damage_blocked' => $player['damage_blocked'] ?? 0,
                        'ultimate_charge' => $player['ultimate_charge'] ?? 0
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Calculate overall player statistics
     */
    private function calculatePlayerStats($playerId)
    {
        // Get all matches with this player (check all possible field names)  
        $matches = MvrlMatch::where(function($query) use ($playerId) {
            $query->whereJsonContains('maps_data', ['team1_composition' => [['player_id' => $playerId]]])
                  ->orWhereJsonContains('maps_data', ['team2_composition' => [['player_id' => $playerId]]])
                  ->orWhereJsonContains('maps_data', ['team1_composition' => [['id' => $playerId]]])
                  ->orWhereJsonContains('maps_data', ['team2_composition' => [['id' => $playerId]]])
                  ->orWhereJsonContains('maps_data', ['team1_players' => [['player_id' => $playerId]]])
                  ->orWhereJsonContains('maps_data', ['team2_players' => [['player_id' => $playerId]]])
                  ->orWhereJsonContains('maps_data', ['team1_players' => [['id' => $playerId]]])
                  ->orWhereJsonContains('maps_data', ['team2_players' => [['id' => $playerId]]]);
        })->get();

        $totalMatches = 0;
        $wins = 0;
        $heroStats = [];
        $totalEliminations = 0;
        $totalDeaths = 0;
        $totalAssists = 0;

        foreach ($matches as $match) {
            if ($match->status === 'completed') {
                $totalMatches++;
                
                // Check if player won
                $playerTeam = null;
                if ($match->maps_data) {
                    foreach ($match->maps_data as $map) {
                        // Find which team the player is on (check both field names)
                        $team1Players = $map['team1_players'] ?? $map['team1_composition'] ?? [];
                        if (!empty($team1Players)) {
                            foreach ($team1Players as $player) {
                                $playerIdField = $player['id'] ?? $player['player_id'] ?? null;
                                if ($playerIdField == $playerId) {
                                    $playerTeam = 'team1';
                                    
                                    // Aggregate hero stats
                                    $hero = $player['hero'] ?? 'Unknown';
                                    if (!isset($heroStats[$hero])) {
                                        $heroStats[$hero] = [
                                            'hero' => $hero,
                                            'matches_played' => 0,
                                            'wins' => 0,
                                            'total_eliminations' => 0,
                                            'total_deaths' => 0,
                                            'total_assists' => 0,
                                            'total_damage' => 0
                                        ];
                                    }
                                    
                                    $heroStats[$hero]['matches_played']++;
                                    $heroStats[$hero]['total_eliminations'] += $player['eliminations'] ?? 0;
                                    $heroStats[$hero]['total_deaths'] += $player['deaths'] ?? 0;
                                    $heroStats[$hero]['total_assists'] += $player['assists'] ?? 0;
                                    $heroStats[$hero]['total_damage'] += $player['damage'] ?? 0;
                                    
                                    $totalEliminations += $player['eliminations'] ?? 0;
                                    $totalDeaths += $player['deaths'] ?? 0;
                                    $totalAssists += $player['assists'] ?? 0;
                                    
                                    if ($map['winner'] === 'team1') {
                                        $heroStats[$hero]['wins']++;
                                    }
                                    break;
                                }
                            }
                        }
                        
                        $team2Players = $map['team2_players'] ?? $map['team2_composition'] ?? [];
                        if (!$playerTeam && !empty($team2Players)) {
                            foreach ($team2Players as $player) {
                                $playerIdField = $player['id'] ?? $player['player_id'] ?? null;
                                if ($playerIdField == $playerId) {
                                    $playerTeam = 'team2';
                                    
                                    // Aggregate hero stats
                                    $hero = $player['hero'] ?? 'Unknown';
                                    if (!isset($heroStats[$hero])) {
                                        $heroStats[$hero] = [
                                            'hero' => $hero,
                                            'matches_played' => 0,
                                            'wins' => 0,
                                            'total_eliminations' => 0,
                                            'total_deaths' => 0,
                                            'total_assists' => 0,
                                            'total_damage' => 0
                                        ];
                                    }
                                    
                                    $heroStats[$hero]['matches_played']++;
                                    $heroStats[$hero]['total_eliminations'] += $player['eliminations'] ?? 0;
                                    $heroStats[$hero]['total_deaths'] += $player['deaths'] ?? 0;
                                    $heroStats[$hero]['total_assists'] += $player['assists'] ?? 0;
                                    $heroStats[$hero]['total_damage'] += $player['damage'] ?? 0;
                                    
                                    $totalEliminations += $player['eliminations'] ?? 0;
                                    $totalDeaths += $player['deaths'] ?? 0;
                                    $totalAssists += $player['assists'] ?? 0;
                                    
                                    if ($map['winner'] === 'team2') {
                                        $heroStats[$hero]['wins']++;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Count match win
                if ($playerTeam === 'team1' && $match->team1_score > $match->team2_score) {
                    $wins++;
                } elseif ($playerTeam === 'team2' && $match->team2_score > $match->team1_score) {
                    $wins++;
                }
            }
        }

        // Calculate aggregated stats for each hero
        $finalHeroStats = [];
        foreach ($heroStats as $hero => $stats) {
            $matches = $stats['matches_played'];
            if ($matches > 0) {
                $finalHeroStats[] = [
                    'hero' => $hero,
                    'matches_played' => $matches,
                    'win_rate' => round(($stats['wins'] / $matches) * 100, 1),
                    'avg_eliminations' => round($stats['total_eliminations'] / $matches, 1),
                    'avg_deaths' => round($stats['total_deaths'] / $matches, 1),
                    'avg_assists' => round($stats['total_assists'] / $matches, 1),
                    'kda' => $stats['total_deaths'] > 0 
                        ? round(($stats['total_eliminations'] + $stats['total_assists']) / $stats['total_deaths'], 2)
                        : round($stats['total_eliminations'] + $stats['total_assists'], 2),
                    'avg_damage' => round($stats['total_damage'] / $matches)
                ];
            }
        }

        $overallKDA = $totalDeaths > 0 
            ? round(($totalEliminations + $totalAssists) / $totalDeaths, 2)
            : round($totalEliminations + $totalAssists, 2);

        return [
            'total_matches' => $totalMatches,
            'win_rate' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0,
            'overall_kda' => $overallKDA,
            'hero_stats' => $finalHeroStats
        ];
    }

    /**
     * Get specific match data for live scoring simulation verification
     */
    public function getMatchPlayerStats($matchId)
    {
        $match = MvrlMatch::find($matchId);
        
        if (!$match) {
            return response()->json(['error' => 'Match not found'], 404);
        }

        $playerStats = [];
        
        // Extract all player statistics from the match
        if ($match->maps_data) {
            foreach ($match->maps_data as $mapIndex => $map) {
                $mapName = $map['map_name'] ?? "Map " . ($mapIndex + 1);
                
                // Process team1 players (handle both field names)
                $team1Players = $map['team1_players'] ?? $map['team1_composition'] ?? [];
                if (!empty($team1Players)) {
                    foreach ($team1Players as $player) {
                        $playerId = $player['id'] ?? $player['player_id'] ?? 0;
                        $playerName = $player['name'] ?? $player['player_name'] ?? 'Unknown';
                        if (!isset($playerStats[$playerId])) {
                            $playerStats[$playerId] = [
                                'player_id' => $playerId,
                                'player_name' => $player['name'] ?? 'Unknown',
                                'team' => 'team1',
                                'maps' => []
                            ];
                        }
                        
                        $playerStats[$playerId]['maps'][] = [
                            'map' => $mapName,
                            'hero' => $player['hero'] ?? 'Unknown',
                            'eliminations' => $player['eliminations'] ?? 0,
                            'deaths' => $player['deaths'] ?? 0,
                            'assists' => $player['assists'] ?? 0,
                            'damage' => $player['damage'] ?? 0,
                            'healing' => $player['healing'] ?? 0,
                            'damage_blocked' => $player['damage_blocked'] ?? 0,
                            'ultimate_charge' => $player['ultimate_charge'] ?? 0,
                            'kda' => $player['deaths'] > 0 
                                ? round((($player['eliminations'] ?? 0) + ($player['assists'] ?? 0)) / $player['deaths'], 2)
                                : ($player['eliminations'] ?? 0) + ($player['assists'] ?? 0)
                        ];
                    }
                }
                
                // Process team2 players (handle both field names)
                $team2Players = $map['team2_players'] ?? $map['team2_composition'] ?? [];
                if (!empty($team2Players)) {
                    foreach ($team2Players as $player) {
                        $playerId = $player['id'] ?? $player['player_id'] ?? 0;
                        $playerName = $player['name'] ?? $player['player_name'] ?? 'Unknown';
                        if (!isset($playerStats[$playerId])) {
                            $playerStats[$playerId] = [
                                'player_id' => $playerId,
                                'player_name' => $player['name'] ?? 'Unknown',
                                'team' => 'team2',
                                'maps' => []
                            ];
                        }
                        
                        $playerStats[$playerId]['maps'][] = [
                            'map' => $mapName,
                            'hero' => $player['hero'] ?? 'Unknown',
                            'eliminations' => $player['eliminations'] ?? 0,
                            'deaths' => $player['deaths'] ?? 0,
                            'assists' => $player['assists'] ?? 0,
                            'damage' => $player['damage'] ?? 0,
                            'healing' => $player['healing'] ?? 0,
                            'damage_blocked' => $player['damage_blocked'] ?? 0,
                            'ultimate_charge' => $player['ultimate_charge'] ?? 0,
                            'kda' => $player['deaths'] > 0 
                                ? round((($player['eliminations'] ?? 0) + ($player['assists'] ?? 0)) / $player['deaths'], 2)
                                : ($player['eliminations'] ?? 0) + ($player['assists'] ?? 0)
                        ];
                    }
                }
            }
        }

        return response()->json([
            'match_id' => $match->id,
            'status' => $match->status,
            'team1_score' => $match->team1_score,
            'team2_score' => $match->team2_score,
            'format' => $match->format,
            'player_stats' => array_values($playerStats),
            'live_scoring_summary' => [
                'total_maps' => count($match->maps_data ?? []),
                'overtime' => $match->overtime ?? false,
                'match_duration' => $match->match_duration ?? '22:11',
                'mvp' => $this->calculateMVP($playerStats)
            ]
        ]);
    }

    /**
     * Calculate MVP based on performance
     */
    private function calculateMVP($playerStats)
    {
        $topScore = 0;
        $mvp = null;
        
        foreach ($playerStats as $player) {
            $totalScore = 0;
            foreach ($player['maps'] as $map) {
                // Calculate performance score
                $score = ($map['eliminations'] * 100) + 
                        ($map['assists'] * 50) - 
                        ($map['deaths'] * 25) + 
                        ($map['damage'] / 100) + 
                        ($map['healing'] / 100) + 
                        ($map['damage_blocked'] / 100);
                $totalScore += $score;
            }
            
            if ($totalScore > $topScore) {
                $topScore = $totalScore;
                $mvp = [
                    'player_name' => $player['player_name'],
                    'player_id' => $player['player_id'],
                    'performance_score' => round($topScore)
                ];
            }
        }
        
        return $mvp;
    }
}