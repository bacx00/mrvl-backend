<?php

namespace App\Http\Controllers;

use App\Models\Team;

class RankingController extends Controller
{
    public function index()
    {
        $teams = Team::orderBy('points', 'desc')->orderBy('name')->get();
        return response()->json($teams);
    }
}