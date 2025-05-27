<?php
namespace App\Http\Controllers;

use App\Models\Match;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $query = Match::with(['team1', 'team2', 'event']);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $matches = $query->orderBy('scheduled_at', 'desc')->get();

        return response()->json([
            'data' => $matches,
            'total' => $matches->count(),
            'success' => true
        ]);
    }

    public function show(Match $match)
    {
        $match->load(['team1.players', 'team2.players', 'event']);
        return response()->json(['data' => $match, 'success' => true]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'team1_id' => 'required|exists:teams,id',
            'team2_id' => 'required|exists:teams,id|different:team1_id',
            'event_id' => 'nullable|exists:events,id',
            'scheduled_at' => 'required|date|after:now',
            'format' => 'required|in:BO1,BO3,BO5',
            'stream_url' => 'nullable|url'
        ]);

        $match = Match::create($validated);

        return response()->json([
            'data' => $match->load(['team1', 'team2', 'event']),
            'success' => true,
            'message' => 'Match created successfully'
        ], 201);
    }

    public function update(Request $request, Match $match)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:upcoming,live,completed',
            'team1_score' => 'nullable|integer|min:0',
            'team2_score' => 'nullable|integer|min:0',
            'current_map' => 'nullable|string',
            'viewers' => 'nullable|integer|min:0',
            'stream_url' => 'nullable|url'
        ]);

        $match->update($validated);

        return response()->json([
            'data' => $match->fresh()->load(['team1', 'team2', 'event']),
            'success' => true,
            'message' => 'Match updated successfully'
        ]);
    }

    public function destroy(Match $match)
    {
        $match->delete();
        return response()->json([
            'success' => true,
            'message' => 'Match deleted successfully'
        ]);
    }

    public function live()
    {
        $liveMatches = Match::with(['team1', 'team2', 'event'])
                           ->where('status', 'live')
                           ->get();

        return response()->json([
            'data' => $liveMatches,
            'total' => $liveMatches->count(),
            'success' => true
        ]);
    }
}
