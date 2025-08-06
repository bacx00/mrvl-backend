<?php

namespace App\Http\Controllers;

use App\Models\{Team, Player, Event, News};
use App\Models\MatchModel as Match;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SimpleController extends Controller
{
    // ===================================
    // TEAMS CRUD - Simple & Fast
    // ===================================
    
    public function getTeams(): JsonResponse
    {
        $teams = Team::with('players')->get();
        return response()->json(['data' => $teams]);
    }
    
    public function createTeam(Request $request): JsonResponse
    {
        $team = Team::create($request->all());
        return response()->json(['data' => $team], 201);
    }
    
    public function updateTeam(Request $request, $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        $team->update($request->all());
        return response()->json(['data' => $team]);
    }
    
    public function deleteTeam($id): JsonResponse
    {
        Team::findOrFail($id)->delete();
        return response()->json(['message' => 'Team deleted']);
    }
    
    // ===================================
    // PLAYERS CRUD - Simple & Fast
    // ===================================
    
    public function getPlayers(): JsonResponse
    {
        $players = Player::with('team')->get();
        return response()->json(['data' => $players]);
    }
    
    public function createPlayer(Request $request): JsonResponse
    {
        $player = Player::create($request->all());
        return response()->json(['data' => $player], 201);
    }
    
    public function updatePlayer(Request $request, $id): JsonResponse
    {
        $player = Player::findOrFail($id);
        $player->update($request->all());
        return response()->json(['data' => $player]);
    }
    
    public function deletePlayer($id): JsonResponse
    {
        Player::findOrFail($id)->delete();
        return response()->json(['message' => 'Player deleted']);
    }
    
    // ===================================
    // MATCHES CRUD - Simple & Fast
    // ===================================
    
    public function getMatches(): JsonResponse
    {
        $matches = Match::with(['team1', 'team2', 'event'])->get();
        return response()->json(['data' => $matches]);
    }
    
    public function createMatch(Request $request): JsonResponse
    {
        $match = Match::create($request->all());
        return response()->json(['data' => $match], 201);
    }
    
    public function updateMatch(Request $request, $id): JsonResponse
    {
        $match = Match::findOrFail($id);
        $match->update($request->all());
        return response()->json(['data' => $match]);
    }
    
    public function deleteMatch($id): JsonResponse
    {
        Match::findOrFail($id)->delete();
        return response()->json(['message' => 'Match deleted']);
    }
}