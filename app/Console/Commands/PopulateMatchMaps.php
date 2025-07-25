<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateMatchMaps extends Command
{
    protected $signature = 'populate:match-maps';
    protected $description = 'Populate match_maps table from existing matches maps_data';

    public function handle()
    {
        $this->info('Starting to populate match_maps table...');
        
        // Get all matches with maps_data
        $matches = DB::table('matches')
            ->whereNotNull('maps_data')
            ->where('maps_data', '!=', '')
            ->get();
            
        $this->info("Found {$matches->count()} matches with map data");
        
        $totalMapsCreated = 0;
        
        foreach ($matches as $match) {
            try {
                $mapsData = json_decode($match->maps_data, true);
                
                if (!is_array($mapsData)) {
                    $this->warn("Invalid maps_data JSON for match {$match->id}");
                    continue;
                }
                
                foreach ($mapsData as $mapData) {
                    // Extract map information
                    $mapNumber = $mapData['map_number'] ?? 1;
                    $mapName = $mapData['map_name'] ?? 'Unknown Map';
                    $mode = $mapData['mode'] ?? 'Unknown';
                    $status = $mapData['status'] ?? 'completed';
                    $team1Score = $mapData['team1_score'] ?? 0;
                    $team2Score = $mapData['team2_score'] ?? 0;
                    $winnerId = $mapData['winner_id'] ?? null;
                    
                    // Check if this map record already exists
                    $existingMap = DB::table('match_maps')
                        ->where('match_id', $match->id)
                        ->where('map_number', $mapNumber)
                        ->first();
                        
                    if ($existingMap) {
                        $this->warn("Map already exists for match {$match->id}, map {$mapNumber}");
                        continue;
                    }
                    
                    // Insert the map record
                    DB::table('match_maps')->insert([
                        'match_id' => $match->id,
                        'map_number' => $mapNumber,
                        'map_name' => $mapName,
                        'game_mode' => $mode,
                        'status' => $status,
                        'team1_score' => $team1Score,
                        'team2_score' => $team2Score,
                        'team1_rounds' => 0, // Not available in current data
                        'team2_rounds' => 0,
                        'winner_id' => $winnerId,
                        'started_at' => null, // Not available in current data
                        'ended_at' => null,
                        'duration_seconds' => null,
                        'overtime' => false,
                        'overtime_duration' => null,
                        'checkpoints_reached' => null,
                        'objectives_captured' => null,
                        'additional_stats' => json_encode($mapData), // Store original data
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $totalMapsCreated++;
                    $this->line("Created map record: Match {$match->id}, Map {$mapNumber} - {$mapName}");
                }
                
            } catch (\Exception $e) {
                $this->error("Error processing match {$match->id}: " . $e->getMessage());
            }
        }
        
        $this->info("Successfully created {$totalMapsCreated} match map records!");
        return 0;
    }
}