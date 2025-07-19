<?php

namespace App\Http\Controllers;

use App\Models\MvrlMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MvrlMatchController extends Controller
{
    /**
     * GET /api/matches
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $page    = max((int) $request->query('page', 1), 1);

        $matches = MvrlMatch::with([
                'team1:id,name,slug,logo',
                'team2:id,name,slug,logo',
            ])
            ->select([
                'id',
                'team1_id',
                'team2_id',
                'score1',
                'score2',
                'date',
                'format',
                'status',
            ])
            ->orderByDesc('date')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($matches, Response::HTTP_OK);
    }

    /**
     * GET /api/matches/{match}
     * Full match sheet with teams & players.
     */
    public function show(MvrlMatch $match): JsonResponse
    {
        $match->load([
            'team1:id,name,slug,logo',
            'team1.players:id,team_id,name,handle,photo',
            'team2:id,name,slug,logo',
            'team2.players:id,team_id,name,handle,photo',
            'players:id',
        ]);

        return response()->json($match, Response::HTTP_OK);
    }
}
