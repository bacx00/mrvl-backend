<?php

namespace App\Http\Controllers;

use App\Models\MvrlMatch;
use Illuminate\Http\Request;

class SimpleLiveController extends Controller
{
    /**
     * Get live match data - SIMPLE VERSION
     */
    public function getLiveData($matchId)
    {
        $match = MvrlMatch::with(['team1', 'team2'])->findOrFail($matchId);
        
        // Get maps data - model handles JSON casting
        $mapsData = $match->maps_data ?? [];
        
        // Get player stats - model handles JSON casting
        $playerStats = $match->player_stats ?? [];
        
        // Simple response structure
        return response()->json([
            'id' => $match->id,
            'status' => $match->status,
            'team1_score' => $match->team1_score ?? 0,
            'team2_score' => $match->team2_score ?? 0,
            'series_score_team1' => $match->series_score_team1 ?? 0,
            'series_score_team2' => $match->series_score_team2 ?? 0,
            'current_map' => $match->current_map_number ?? 1,
            'maps' => $mapsData,
            'player_stats' => $playerStats
        ]);
    }
    
    /**
     * Update live match data - SIMPLE VERSION
     */
    public function updateLiveData(Request $request, $matchId)
    {
        $match = MvrlMatch::findOrFail($matchId);
        
        // Update scores - handle separately for map scores and series scores
        // Only update if value is different from current to prevent resetting
        if ($request->has('team1_score') && $request->team1_score !== null) {
            $match->team1_score = $request->team1_score;
        }
        
        if ($request->has('team2_score') && $request->team2_score !== null) {
            $match->team2_score = $request->team2_score;
        }
        
        // Update series scores separately (maps won)
        if ($request->has('series_score_team1') && $request->series_score_team1 !== null) {
            $match->series_score_team1 = $request->series_score_team1;
        }
        
        if ($request->has('series_score_team2') && $request->series_score_team2 !== null) {
            $match->series_score_team2 = $request->series_score_team2;
        }
        
        
        // Update current map
        if ($request->has('current_map')) {
            $match->current_map_number = $request->current_map;
        }
        
        // Update maps data
        if ($request->has('maps')) {
            $match->maps_data = $request->maps;
        }
        
        // Update player stats
        if ($request->has('player_stats')) {
            $match->player_stats = $request->player_stats;
        }
        
        // Update status
        if ($request->has('status')) {
            $match->status = $request->status;
        }
        
        $match->save();
        
        return response()->json(['success' => true]);
    }
}