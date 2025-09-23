<?php

namespace App\Http\Controllers;

use App\Models\MvrlMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveUpdateController extends Controller
{
    /**
     * Stream live updates for a tournament using Server-Sent Events (SSE)
     * 
     * @api GET /api/tournaments/events/{eventId}/live-stream
     */
    public function streamEvent($eventId)
    {
        $event = \App\Models\Event::findOrFail($eventId);
        
        // Set headers for SSE
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true'
        ];
        
        return response()->stream(function () use ($event, $eventId) {
            // Send initial connection message
            echo "event: connected\n";
            echo "data: " . json_encode([
                'status' => 'connected', 
                'event_id' => $event->id,
                'event_name' => $event->name,
                'channels' => ["event.{$eventId}", "tournament.updates"]
            ]) . "\n\n";
            flush();
            
            // Keep connection alive and check for updates
            $lastUpdate = microtime(true);
            $lastCheck = microtime(true);
            
            while (true) {
                // Check if client is still connected
                if (connection_aborted()) {
                    break;
                }
                
                // Check for updates more frequently - every 50ms for immediate response
                if ((microtime(true) - $lastCheck) >= 0.05) {
                    $lastCheck = microtime(true);
                    
                    // Check multiple cache keys for different update types
                    $updateTypes = [
                        'bracket_updated', 
                        'match_completed', 
                        'phase_changed', 
                        'standings_updated',
                        'swiss_round_generated',
                        'tournament_status'
                    ];
                    $foundUpdate = false;
                    
                    foreach ($updateTypes as $updateType) {
                        $updateKey = "live_update_event_{$eventId}_{$updateType}";
                        $update = cache()->pull($updateKey); // Pull removes it atomically
                        
                        if ($update) {
                            // Send the update to client immediately
                            $eventType = str_replace('_', '-', $update['type'] ?? $updateType);
                            echo "event: {$eventType}\n";
                            echo "data: " . json_encode($update['data'] ?? $update) . "\n\n";
                            flush();
                            
                            $lastUpdate = microtime(true);
                            $foundUpdate = true;
                            
                            \Illuminate\Support\Facades\Log::info("SSE: Sent {$eventType} update for event {$eventId}");
                        }
                    }
                }
                
                // Send heartbeat every 30 seconds
                if ((microtime(true) - $lastUpdate) > 30) {
                    echo ": heartbeat\n\n";
                    flush();
                    $lastUpdate = microtime(true);
                }
                
                // Sleep for a very short time - 10ms for near real-time response
                usleep(10000); // 0.01 seconds
            }
        }, 200, $headers);
    }

    /**
     * Stream live updates for a match using Server-Sent Events (SSE)
     */
    public function stream($matchId)
    {
        $match = MvrlMatch::findOrFail($matchId);
        
        // Set headers for SSE
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true'
        ];
        
        return response()->stream(function () use ($match, $matchId) {
            // Send initial connection message
            echo "event: connected\n";
            echo "data: " . json_encode(['status' => 'connected', 'match_id' => $match->id]) . "\n\n";
            flush();
            
            // Keep connection alive and check for updates
            $lastUpdate = microtime(true);
            $lastCheck = microtime(true);
            
            while (true) {
                // Check if client is still connected
                if (connection_aborted()) {
                    break;
                }
                
                // Check for updates more frequently - every 50ms for immediate response
                if ((microtime(true) - $lastCheck) >= 0.05) {
                    $lastCheck = microtime(true);
                    
                    // Check multiple cache keys for different update types
                    $updateTypes = ['score', 'hero', 'stats', 'map', 'status', 'general'];
                    $foundUpdate = false;
                    
                    foreach ($updateTypes as $updateType) {
                        $updateKey = "live_update_match_{$matchId}_{$updateType}";
                        $update = cache()->pull($updateKey); // Pull removes it atomically
                        
                        if ($update) {
                            // Send the update to client immediately
                            $eventType = str_replace('_', '-', $update['type']);
                            echo "event: {$eventType}\n";
                            echo "data: " . json_encode($update['data']) . "\n\n";
                            flush();
                            
                            $lastUpdate = microtime(true);
                            $foundUpdate = true;
                            
                            Log::info("SSE: Sent {$eventType} update for match {$matchId}");
                        }
                    }
                    
                    // Also check the general update key for backward compatibility
                    if (!$foundUpdate) {
                        $updateKey = "live_update_match_{$matchId}";
                        $update = cache()->pull($updateKey);
                        
                        if ($update) {
                            $eventType = str_replace('_', '-', $update['type']);
                            echo "event: {$eventType}\n";
                            echo "data: " . json_encode($update['data']) . "\n\n";
                            flush();
                            
                            $lastUpdate = microtime(true);
                        }
                    }
                }
                
                // Send heartbeat every 30 seconds
                if ((microtime(true) - $lastUpdate) > 30) {
                    echo ": heartbeat\n\n";
                    flush();
                    $lastUpdate = microtime(true);
                }
                
                // Sleep for a very short time - 10ms for near real-time response
                usleep(10000); // 0.01 seconds
            }
        }, 200, $headers);
    }
    
    /**
     * Receive live update from admin and broadcast to all connected clients
     */
    public function update(Request $request, $matchId)
    {
        $match = MvrlMatch::findOrFail($matchId);
        
        // More flexible validation to accept frontend data structure
        $validated = $request->validate([
            'type' => 'required|string|in:score-update,hero-update,stats-update,map-update,status-update',
            'data' => 'required|array',
            'timestamp' => 'required'
        ]);
        
        try {
            // Log incoming data for debugging
            Log::info('Live update received', [
                'match_id' => $matchId,
                'type' => $validated['type'],
                'data' => $validated['data']
            ]);
            
            switch ($validated['type']) {
                case 'score-update':
                    $this->handleScoreUpdate($match, $validated['data']);
                    break;
                    
                case 'hero-update':
                    $this->handleHeroUpdate($match, $validated['data']);
                    break;
                    
                case 'stats-update':
                    $this->handleStatsUpdate($match, $validated['data']);
                    break;
                    
                case 'map-update':
                    $this->handleMapUpdate($match, $validated['data']);
                    break;
                    
                case 'status-update':
                    $this->handleStatusUpdate($match, $validated['data']);
                    break;
            }
            
            // Broadcast the update to all connected SSE clients
            $this->broadcastUpdate($match->id, $validated['type'], $validated['data']);
            
            return response()->json([
                'success' => true,
                'message' => 'Update processed and broadcast successfully',
                'type' => $validated['type']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Live update error: ' . $e->getMessage(), [
                'match_id' => $matchId,
                'type' => $validated['type'] ?? 'unknown',
                'data' => $validated['data'] ?? [],
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process update: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get current match status (polling fallback)
     */
    public function status($matchId)
    {
        $match = MvrlMatch::with(['team1', 'team2'])
            ->findOrFail($matchId);
        
        // Get current map data from the maps_data JSON field - handle JSON string from database
        $mapsData = $match->maps_data;
        if (is_string($mapsData)) {
            $mapsData = json_decode($mapsData, true) ?? [];
        } else {
            $mapsData = $mapsData ?? [];
        }
        $currentMapIndex = ($match->current_map_number ?? 1) - 1;
        $currentMap = isset($mapsData[$currentMapIndex]) ? $mapsData[$currentMapIndex] : null;
        
        return response()->json([
            'status' => $match->status,
            'series_score_team1' => $match->series_score_team1,
            'series_score_team2' => $match->series_score_team2,
            'current_map' => $match->current_map_number ?? 1,
            'scores' => [
                'team1' => $currentMap ? ($currentMap['team1']['score'] ?? 0) : 0,
                'team2' => $currentMap ? ($currentMap['team2']['score'] ?? 0) : 0
            ],
            'player_stats' => $match->player_stats ?? [],
            'last_updated' => $match->updated_at->toIso8601String()
        ]);
    }
    
    private function handleScoreUpdate($match, $data)
    {
        // Handle map switching if isMapSwitch flag is present
        if (isset($data['isMapSwitch']) && $data['isMapSwitch'] === true && isset($data['current_map'])) {
            \Log::info('Map switch detected in LiveUpdateController', [
                'match_id' => $match->id,
                'current_map' => $data['current_map'],
                'isMapSwitch' => $data['isMapSwitch']
            ]);
            
            // Update current map in database
            $match->current_map = $data['current_map'];
            $match->current_map_number = $data['current_map'];
            $match->save();
            
            \Log::info('Map switch saved', [
                'match_id' => $match->id,
                'current_map' => $match->current_map,
                'current_map_number' => $match->current_map_number
            ]);
        }
        
        $mapIndex = isset($data['map_index']) ? $data['map_index'] : max(0, ($match->current_map_number ?? 1) - 1);
        
        // Get existing maps data - handle JSON string from database
        $mapsData = $match->maps_data;
        if (is_string($mapsData)) {
            $mapsData = json_decode($mapsData, true) ?? [];
        } else {
            $mapsData = $mapsData ?? [];
        }
        
        // Ensure we have a map at this index
        if (!isset($mapsData[$mapIndex])) {
            $mapsData[$mapIndex] = [
                'name' => $data['map_name'] ?? 'Map ' . ($mapIndex + 1),
                'mode' => $data['game_mode'] ?? 'Unknown',
                'team1_score' => 0,
                'team2_score' => 0,
                'team1_composition' => [],
                'team2_composition' => [],
                'status' => 'in_progress'
            ];
        }
        
        // Update scores - handle both old and new structure
        if (isset($data['team1_score'])) {
            $mapsData[$mapIndex]['team1_score'] = $data['team1_score'];
            // Also update old structure if it exists
            if (isset($mapsData[$mapIndex]['team1'])) {
                $mapsData[$mapIndex]['team1']['score'] = $data['team1_score'];
            }
        }
        if (isset($data['team2_score'])) {
            $mapsData[$mapIndex]['team2_score'] = $data['team2_score'];
            // Also update old structure if it exists
            if (isset($mapsData[$mapIndex]['team2'])) {
                $mapsData[$mapIndex]['team2']['score'] = $data['team2_score'];
            }
        }
        
        // Save updated maps data
        $match->maps_data = $mapsData;
        $match->save();
        
        // Update series score if needed
        $this->updateSeriesScore($match);
        
        // Include comprehensive data in the broadcast for immediate frontend update
        $data['match_id'] = $match->id;
        $data['map_number'] = $mapIndex + 1;
        $data['series_score'] = [
            'team1' => $match->series_score_team1,
            'team2' => $match->series_score_team2
        ];
    }
    
    private function handleHeroUpdate($match, $data)
    {
        $mapIndex = isset($data['map_index']) ? $data['map_index'] : max(0, ($match->current_map_number ?? 1) - 1);
        $playerId = $data['player_id'] ?? null;
        
        if (!$playerId) {
            Log::warning('Hero update without player_id', $data);
            return;
        }
        
        // Get existing maps data - handle JSON string from database
        $mapsData = $match->maps_data;
        if (is_string($mapsData)) {
            $mapsData = json_decode($mapsData, true) ?? [];
        } else {
            $mapsData = $mapsData ?? [];
        }
        
        // Ensure we have a map at this index with proper structure
        if (!isset($mapsData[$mapIndex])) {
            // Initialize with team rosters if compositions are empty
            $team1Composition = [];
            $team2Composition = [];
            
            // Auto-populate with team players if no existing data
            if ($match->team1 && $match->team1->players) {
                foreach ($match->team1->players as $player) {
                    $team1Composition[] = [
                        'player_id' => $player->id,
                        'name' => $player->name,
                        'hero' => 'Unknown',
                        'role' => $player->role ?? 'Unknown',
                        'eliminations' => 0,
                        'deaths' => 0,
                        'assists' => 0,
                        'damage' => 0,
                        'healing' => 0,
                        'damage_blocked' => 0,
                        'country' => $player->country ?? ''
                    ];
                }
            }
            
            if ($match->team2 && $match->team2->players) {
                foreach ($match->team2->players as $player) {
                    $team2Composition[] = [
                        'player_id' => $player->id,
                        'name' => $player->name,
                        'hero' => 'Unknown',
                        'role' => $player->role ?? 'Unknown',
                        'eliminations' => 0,
                        'deaths' => 0,
                        'assists' => 0,
                        'damage' => 0,
                        'healing' => 0,
                        'damage_blocked' => 0,
                        'country' => $player->country ?? ''
                    ];
                }
            }
            
            $mapsData[$mapIndex] = [
                'name' => 'Map ' . ($mapIndex + 1),
                'mode' => 'Unknown',
                'team1_score' => 0,
                'team2_score' => 0,
                'team1_composition' => $team1Composition,
                'team2_composition' => $team2Composition,
                'status' => 'in_progress'
            ];
        }
        
        // Handle both old and new data structures
        $teamKey = $data['team'] == 1 ? 'team1' : 'team2';
        $compositionKey = $teamKey . '_composition';
        
        // Check if we're using the old structure (team1/team2 with players) or new (team1_composition/team2_composition)
        $playersArray = null;
        if (isset($mapsData[$mapIndex][$compositionKey])) {
            // New structure
            $playersArray = &$mapsData[$mapIndex][$compositionKey];
        } elseif (isset($mapsData[$mapIndex][$teamKey]['players'])) {
            // Old structure
            $playersArray = &$mapsData[$mapIndex][$teamKey]['players'];
        } else {
            // Initialize with new structure
            $mapsData[$mapIndex][$compositionKey] = [];
            $playersArray = &$mapsData[$mapIndex][$compositionKey];
        }
        
        // Find and update the player in the team
        $playerFound = false;
        foreach ($playersArray as &$player) {
                if ($player['player_id'] == $playerId) {
                    $player['hero'] = $data['hero'];
                    $player['role'] = $data['role'] ?? 'Unknown';

                    // CRITICAL: Update heroes_played and hero_changes arrays if provided
                    if (isset($data['heroes_played'])) {
                        $player['heroes_played'] = $data['heroes_played'];
                        \Log::info('Setting heroes_played for player ' . $playerId, ['heroes_played' => $data['heroes_played']]);
                    } else {
                        \Log::info('No heroes_played data provided for player ' . $playerId);
                    }
                    if (isset($data['hero_changes'])) {
                        $player['hero_changes'] = $data['hero_changes'];
                        \Log::info('Setting hero_changes for player ' . $playerId, ['hero_changes' => $data['hero_changes']]);
                    } else {
                        \Log::info('No hero_changes data provided for player ' . $playerId);
                    }

                    $playerFound = true;
                    break;
                }
            }
            
        // If player not found, add them
        if (!$playerFound) {
            $playersArray[] = [
                'player_id' => $playerId,
                'name' => $data['player_name'] ?? 'Unknown',
                'hero' => $data['hero'],
                'role' => $data['role'] ?? 'Unknown',
                'eliminations' => 0,
                'deaths' => 0,
                'assists' => 0,
                'damage' => 0,
                'healing' => 0,
                'damage_blocked' => 0,
                'country' => '',
                'heroes_played' => $data['heroes_played'] ?? [],
                'hero_changes' => $data['hero_changes'] ?? []
            ];
        }
            
        // Save updated maps data
        $match->maps_data = $mapsData;
        $match->save();
        
        // Include match ID in broadcast data
        $data['match_id'] = $match->id;
        $data['map_number'] = $mapIndex + 1;
    }
    
    private function handleStatsUpdate($match, $data)
    {
        $mapIndex = isset($data['map_index']) ? $data['map_index'] : max(0, ($match->current_map_number ?? 1) - 1);
        $playerId = $data['player_id'] ?? null;
        
        if (!$playerId) {
            Log::warning('Stats update without player_id', $data);
            return;
        }
        
        // Get existing maps data - handle JSON string from database
        $mapsData = $match->maps_data;
        if (is_string($mapsData)) {
            $mapsData = json_decode($mapsData, true) ?? [];
        } else {
            $mapsData = $mapsData ?? [];
        }
        
        // Ensure we have a map at this index with proper structure
        if (!isset($mapsData[$mapIndex])) {
            // Initialize with team rosters if compositions are empty
            $team1Composition = [];
            $team2Composition = [];
            
            // Auto-populate with team players if no existing data
            if ($match->team1 && $match->team1->players) {
                foreach ($match->team1->players as $player) {
                    $team1Composition[] = [
                        'player_id' => $player->id,
                        'name' => $player->name,
                        'hero' => 'Unknown',
                        'role' => $player->role ?? 'Unknown',
                        'eliminations' => 0,
                        'deaths' => 0,
                        'assists' => 0,
                        'damage' => 0,
                        'healing' => 0,
                        'damage_blocked' => 0,
                        'country' => $player->country ?? ''
                    ];
                }
            }
            
            if ($match->team2 && $match->team2->players) {
                foreach ($match->team2->players as $player) {
                    $team2Composition[] = [
                        'player_id' => $player->id,
                        'name' => $player->name,
                        'hero' => 'Unknown',
                        'role' => $player->role ?? 'Unknown',
                        'eliminations' => 0,
                        'deaths' => 0,
                        'assists' => 0,
                        'damage' => 0,
                        'healing' => 0,
                        'damage_blocked' => 0,
                        'country' => $player->country ?? ''
                    ];
                }
            }
            
            $mapsData[$mapIndex] = [
                'name' => 'Map ' . ($mapIndex + 1),
                'mode' => 'Unknown',
                'team1_score' => 0,
                'team2_score' => 0,
                'team1_composition' => $team1Composition,
                'team2_composition' => $team2Composition,
                'status' => 'in_progress'
            ];
        }
        
        // Handle both old and new data structures
        $teamKey = $data['team'] == 1 ? 'team1' : 'team2';
        $compositionKey = $teamKey . '_composition';
        $statType = $data['stat_type'] ?? null;
        $value = $data['value'] ?? 0;
        
        // Check if we're using the old structure (team1/team2 with players) or new (team1_composition/team2_composition)
        $playersArray = null;
        if (isset($mapsData[$mapIndex][$compositionKey])) {
            // New structure
            $playersArray = &$mapsData[$mapIndex][$compositionKey];
        } elseif (isset($mapsData[$mapIndex][$teamKey]['players'])) {
            // Old structure
            $playersArray = &$mapsData[$mapIndex][$teamKey]['players'];
        } else {
            // Initialize with new structure
            $mapsData[$mapIndex][$compositionKey] = [];
            $playersArray = &$mapsData[$mapIndex][$compositionKey];
        }
        
        // Find and update the player in the team
        $playerFound = false;
        foreach ($playersArray as &$player) {
            if ($player['player_id'] == $playerId) {
                // Map stat types to the correct field names (new structure uses different names)
                $statMap = [
                    'kills' => 'eliminations',
                    'deaths' => 'deaths',
                    'assists' => 'assists',
                    'damage' => 'damage',
                    'healing' => 'healing',
                    'mitigated' => 'damage_blocked',
                    'eliminations' => 'eliminations'
                ];
                
                if ($statType && isset($statMap[$statType])) {
                    $player[$statMap[$statType]] = $value;
                }
                
                // Include all current stats in broadcast data
                $data['current_stats'] = [
                    'kills' => $player['eliminations'] ?? 0,
                    'deaths' => $player['deaths'] ?? 0,
                    'assists' => $player['assists'] ?? 0,
                    'damage' => $player['damage'] ?? 0,
                    'healing' => $player['healing'] ?? 0,
                    'mitigated' => $player['damage_blocked'] ?? 0
                ];
                
                $playerFound = true;
                break;
            }
        }
        
        // If player not found, add them with the stat
        if (!$playerFound) {
            $newPlayer = [
                'player_id' => $playerId,
                'name' => $data['player_name'] ?? 'Unknown',
                'hero' => $data['hero'] ?? 'Unknown',
                'role' => $data['role'] ?? 'Unknown',
                'eliminations' => 0,
                'deaths' => 0,
                'assists' => 0,
                'damage' => 0,
                'healing' => 0,
                'damage_blocked' => 0,
                'country' => ''
            ];
            
            // Map stat types to the correct field names
            $statMap = [
                'kills' => 'eliminations',
                'deaths' => 'deaths',
                'assists' => 'assists',
                'damage' => 'damage',
                'healing' => 'healing',
                'mitigated' => 'damage_blocked',
                'eliminations' => 'eliminations'
            ];
            
            if ($statType && isset($statMap[$statType])) {
                $newPlayer[$statMap[$statType]] = $value;
            }
            
            $playersArray[] = $newPlayer;
            
            // Include all current stats in broadcast data
            $data['current_stats'] = [
                'kills' => $newPlayer['eliminations'],
                'deaths' => $newPlayer['deaths'],
                'assists' => $newPlayer['assists'],
                'damage' => $newPlayer['damage'],
                'healing' => $newPlayer['healing'],
                'mitigated' => $newPlayer['damage_blocked']
            ];
        }
        
        // Save updated maps data
        $match->maps_data = $mapsData;
        $match->save();
        
        // Include match ID in broadcast data
        $data['match_id'] = $match->id;
        $data['map_number'] = $mapIndex + 1;
    }
    
    private function handleMapUpdate($match, $data)
    {
        if (isset($data['current_map'])) {
            $match->update(['current_map_number' => $data['current_map']]);
        }
    }
    
    private function handleStatusUpdate($match, $data)
    {
        if (isset($data['status'])) {
            $match->update(['status' => $data['status']]);
        }
    }
    
    private function isMapCompleted($mapData)
    {
        // Check if map has a winner based on scores
        // Handle both old and new structure
        $team1Score = $mapData['team1_score'] ?? ($mapData['team1']['score'] ?? 0);
        $team2Score = $mapData['team2_score'] ?? ($mapData['team2']['score'] ?? 0);
        
        // Map is completed when one team reaches winning score (2 for Marvel Rivals)
        return $team1Score >= 2 || $team2Score >= 2;
    }
    
    private function updateSeriesScore($match)
    {
        // Get existing maps data - handle JSON string from database
        $mapsData = $match->maps_data;
        if (is_string($mapsData)) {
            $mapsData = json_decode($mapsData, true) ?? [];
        } else {
            $mapsData = $mapsData ?? [];
        }
        $team1Wins = 0;
        $team2Wins = 0;
        
        foreach ($mapsData as $mapData) {
            if ($this->isMapCompleted($mapData)) {
                // Handle both old and new structure
                $team1Score = $mapData['team1_score'] ?? ($mapData['team1']['score'] ?? 0);
                $team2Score = $mapData['team2_score'] ?? ($mapData['team2']['score'] ?? 0);
                
                if ($team1Score > $team2Score) {
                    $team1Wins++;
                } elseif ($team2Score > $team1Score) {
                    $team2Wins++;
                }
            }
        }
        
        $match->update([
            'series_score_team1' => $team1Wins,
            'series_score_team2' => $team2Wins
        ]);
    }
    
    public function broadcastUpdate($matchId, $type, $data)
    {
        $update = [
            'type' => $type,
            'data' => $data,
            'timestamp' => now()->toIso8601String()
        ];
        
        // Map update types to cache key suffixes
        $typeMap = [
            'score-update' => 'score',
            'hero-update' => 'hero',
            'stats-update' => 'stats',
            'map-update' => 'map',
            'status-update' => 'status'
        ];
        
        $typeSuffix = $typeMap[$type] ?? 'general';
        
        // Store update in type-specific cache key for immediate pickup
        $specificKey = "live_update_match_{$matchId}_{$typeSuffix}";
        cache()->put($specificKey, $update, 10);
        
        // Also store in general key for backward compatibility
        $generalKey = "live_update_match_{$matchId}";
        cache()->put($generalKey, $update, 10);
        
        // Log the broadcast
        Log::info("Broadcasting {$type} update for match {$matchId}", [
            'specific_key' => $specificKey,
            'data' => $data
        ]);
        
        // Store broadcast event in cache instead of Redis pub/sub
        // This allows SSE clients to pick up the update
        $broadcastKey = "match.{$matchId}.broadcast." . time() . '.' . uniqid();
        cache()->put($broadcastKey, $update, 5); // Keep for 5 minutes
    }
}