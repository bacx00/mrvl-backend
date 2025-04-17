<?php

namespace App\Http\Controllers;

use App\Models\Match;

class MatchController extends Controller
{
    public function index()
    {
        $matches = Match::with(['team1', 'team2'])
                        ->orderBy('date', 'desc')->get();
        return response()->json($matches);
    }

    public function show(Match $match)
    {
        $match->load(['team1.players', 'team2.players', 'players']);
        return response()->json($match);
    }
}