<?php
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/**
 * Fix hero synchronization for matches
 * Properly rebuilds maps_data from match_player_stats table
 * Supports multiple heroes per player per map
 */

echo "🎮 Fixing Match Hero Synchronization\n";
echo "=====================================\n\n";

// Function to rebuild maps_data for a specific match
function rebuildMatchMapsData($matchId) {
    echo "Processing match $matchId...\n";
    
    // Get the match
    $match = DB::table('matches')->where('id', $matchId)->first();
    if (!$match) {
        echo "  ❌ Match $matchId not found\n";
        return false;
    }
    
    // Get existing maps_data to preserve map names and scores
    $existingMapsData = json_decode($match->maps_data, true) ?? [];
    
    // Get all player stats for this match
    $playerStats = DB::table('match_player_stats')
        ->where('match_id', $matchId)
        ->orderBy('map_number')
        ->orderBy('player_id')
        ->orderBy('eliminations', 'desc') // Primary hero first
        ->get();
    
    echo "  Found " . $playerStats->count() . " player stat entries\n";
    
    // Group stats by map and player
    $statsByMap = [];
    foreach ($playerStats as $stat) {
        $mapNum = $stat->map_number ?? 1;
        $playerId = $stat->player_id;
        
        if (!isset($statsByMap[$mapNum])) {
            $statsByMap[$mapNum] = [];
        }
        
        if (!isset($statsByMap[$mapNum][$playerId])) {
            $statsByMap[$mapNum][$playerId] = [
                'player_id' => $playerId,
                'heroes' => []
            ];
        }
        
        // Add this hero's stats
        $statsByMap[$mapNum][$playerId]['heroes'][] = [
            'hero' => $stat->hero,
            'eliminations' => (int)$stat->eliminations,
            'deaths' => (int)$stat->deaths,
            'assists' => (int)$stat->assists,
            'damage' => (int)$stat->damage_dealt,
            'healing' => (int)$stat->healing_done,
            'damage_blocked' => (int)$stat->damage_blocked,
            'kda_ratio' => $stat->kda_ratio,
            'time_played' => $stat->time_played ?? 0
        ];
    }
    
    // Rebuild maps_data with proper hero data
    $newMapsData = [];
    $maxMapNum = max(array_keys($statsByMap) + [3]); // Default to at least 3 maps
    
    for ($mapNum = 1; $mapNum <= $maxMapNum; $mapNum++) {
        // Start with existing map data
        $mapData = $existingMapsData[$mapNum - 1] ?? [
            'map_name' => "Map $mapNum",
            'map_mode' => 'Unknown',
            'team1_score' => 0,
            'team2_score' => 0
        ];
        
        // Rebuild team compositions with aggregated stats per player
        $mapData['team1_composition'] = [];
        $mapData['team2_composition'] = [];
        
        // Get player's team assignment
        $team1Players = DB::table('players')
            ->where('team_id', $match->team1_id)
            ->pluck('id')
            ->toArray();
            
        $team2Players = DB::table('players')
            ->where('team_id', $match->team2_id)
            ->pluck('id')
            ->toArray();
        
        // Process players for this map
        if (isset($statsByMap[$mapNum])) {
            foreach ($statsByMap[$mapNum] as $playerId => $playerData) {
                // Get player info
                $player = DB::table('players')->where('id', $playerId)->first();
                if (!$player) continue;
                
                // Determine primary hero (most eliminations or first played)
                $primaryHero = $playerData['heroes'][0] ?? null;
                if (!$primaryHero) continue;
                
                // Calculate aggregated stats across all heroes for this player on this map
                $totalElims = array_sum(array_column($playerData['heroes'], 'eliminations'));
                $totalDeaths = array_sum(array_column($playerData['heroes'], 'deaths'));
                $totalAssists = array_sum(array_column($playerData['heroes'], 'assists'));
                $totalDamage = array_sum(array_column($playerData['heroes'], 'damage'));
                $totalHealing = array_sum(array_column($playerData['heroes'], 'healing'));
                $totalBlocked = array_sum(array_column($playerData['heroes'], 'damage_blocked'));
                
                // Build player entry with primary hero shown but all heroes tracked
                $playerEntry = [
                    'player_id' => (int)$playerId,
                    'player_name' => $player->username ?? $player->name,
                    'username' => $player->username ?? $player->name,
                    'name' => $player->name ?? $player->username,
                    'country' => $player->country ?? 'Unknown',
                    'nationality' => $player->nationality ?? $player->country ?? 'Unknown',
                    // Primary hero displayed
                    'hero' => $primaryHero['hero'],
                    'role' => getRoleForHero($primaryHero['hero']),
                    // Aggregated stats
                    'eliminations' => $totalElims,
                    'deaths' => $totalDeaths,
                    'assists' => $totalAssists,
                    'damage' => $totalDamage,
                    'healing' => $totalHealing,
                    'damage_blocked' => $totalBlocked,
                    'kda_ratio' => $totalDeaths > 0 ? 
                        number_format(($totalElims + $totalAssists) / $totalDeaths, 2) : 
                        number_format($totalElims + $totalAssists, 2),
                    // Track all heroes played
                    'heroes_played' => $playerData['heroes'],
                    'hero_count' => count($playerData['heroes'])
                ];
                
                // Assign to correct team
                if (in_array($playerId, $team1Players)) {
                    $mapData['team1_composition'][] = $playerEntry;
                } elseif (in_array($playerId, $team2Players)) {
                    $mapData['team2_composition'][] = $playerEntry;
                } else {
                    // Try to determine by existing composition
                    $existingTeam1 = $existingMapsData[$mapNum - 1]['team1_composition'] ?? [];
                    $inTeam1 = false;
                    foreach ($existingTeam1 as $p) {
                        if ($p['player_id'] == $playerId) {
                            $inTeam1 = true;
                            break;
                        }
                    }
                    
                    if ($inTeam1) {
                        $mapData['team1_composition'][] = $playerEntry;
                    } else {
                        $mapData['team2_composition'][] = $playerEntry;
                    }
                }
            }
        }
        
        // Preserve any players from existing data who don't have stats yet
        $processedPlayerIds = array_keys($statsByMap[$mapNum] ?? []);
        
        // Add existing team1 players not in stats
        foreach (($existingMapsData[$mapNum - 1]['team1_composition'] ?? []) as $existingPlayer) {
            if (!in_array($existingPlayer['player_id'], $processedPlayerIds)) {
                $mapData['team1_composition'][] = $existingPlayer;
            }
        }
        
        // Add existing team2 players not in stats
        foreach (($existingMapsData[$mapNum - 1]['team2_composition'] ?? []) as $existingPlayer) {
            if (!in_array($existingPlayer['player_id'], $processedPlayerIds)) {
                $mapData['team2_composition'][] = $existingPlayer;
            }
        }
        
        $newMapsData[] = $mapData;
    }
    
    // Update the match with new maps_data
    DB::table('matches')->where('id', $matchId)->update([
        'maps_data' => json_encode($newMapsData),
        'updated_at' => now()
    ]);
    
    echo "  ✅ Updated match $matchId with proper hero data\n";
    
    // Debug: Show what we fixed for match 7
    if ($matchId == 7) {
        echo "\n  📊 Match 7 Debug Info:\n";
        foreach ($newMapsData as $idx => $map) {
            $mapNum = $idx + 1;
            echo "    Map $mapNum: " . ($map['map_name'] ?? 'Unknown') . "\n";
            
            // Show player 405's data
            foreach ($map['team1_composition'] ?? [] as $player) {
                if ($player['player_id'] == 405) {
                    echo "      Player 405 (delenaa):\n";
                    echo "        Primary Hero: {$player['hero']}\n";
                    echo "        Total Stats: {$player['eliminations']} elims, {$player['deaths']} deaths\n";
                    if (isset($player['heroes_played'])) {
                        echo "        Heroes Played:\n";
                        foreach ($player['heroes_played'] as $hero) {
                            echo "          - {$hero['hero']}: {$hero['eliminations']}/{$hero['deaths']}/{$hero['assists']}\n";
                        }
                    }
                }
            }
        }
    }
    
    return true;
}

// Helper function to get role for a hero
function getRoleForHero($hero) {
    $heroRoles = [
        'Hela' => 'Duelist',
        'Iron Man' => 'Duelist',
        'Spider-Man' => 'Duelist',
        'Rocket Raccoon' => 'Strategist',
        'Mantis' => 'Strategist',
        'Luna Snow' => 'Strategist',
        'Groot' => 'Vanguard',
        'Venom' => 'Vanguard',
        'Magneto' => 'Vanguard'
        // Add more as needed
    ];
    
    return $heroRoles[$hero] ?? 'Duelist';
}

// Process specific match or all matches
if (isset($argv[1])) {
    // Process specific match
    $matchId = (int)$argv[1];
    rebuildMatchMapsData($matchId);
} else {
    // Process all completed matches
    $matches = DB::table('matches')
        ->where('status', 'completed')
        ->pluck('id');
    
    echo "Found " . $matches->count() . " completed matches to process\n\n";
    
    foreach ($matches as $matchId) {
        rebuildMatchMapsData($matchId);
    }
}

echo "\n✅ Hero synchronization complete!\n";