<?php
namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $query = Team::with(['players']);

        if ($request->region && $request->region !== 'all') {
            $query->where('region', $request->region);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('short_name', 'LIKE', "%{$request->search}%");
            });
        }

        $teams = $query->orderBy('rating', 'desc')->get();

        return response()->json([
            'data' => $teams,
            'total' => $teams->count(),
            'success' => true
        ]);
    }

    public function show(Team $team)
    {
        $team->load(['players']);
        return response()->json(['data' => $team, 'success' => true]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:10|unique:teams',
            'logo' => 'nullable|string',
            'region' => 'required|string|max:10',
            'country' => 'required|string',
            'rating' => 'nullable|integer|min:0',
            'social_media' => 'nullable|array',
            'achievements' => 'nullable|array'
        ]);

        $team = Team::create($validated);

        return response()->json([
            'data' => $team,
            'success' => true,
            'message' => 'Team created successfully'
        ], 201);
    }

    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'short_name' => 'sometimes|string|max:10|unique:teams,short_name,' . $team->id,
            'logo' => 'nullable|string',
            'region' => 'sometimes|string|max:10',
            'country' => 'sometimes|string',
            'rating' => 'nullable|integer|min:0',
            'social_media' => 'nullable|array',
            'achievements' => 'nullable|array'
        ]);

        $team->update($validated);

        return response()->json([
            'data' => $team->fresh(),
            'success' => true,
            'message' => 'Team updated successfully'
        ]);
    }

    public function destroy(Team $team)
    {
        $team->delete();
        return response()->json([
            'success' => true,
            'message' => 'Team deleted successfully'
        ]);
    }

    public function rankings(Request $request)
    {
        $region = $request->get('region', 'all');
        
        $query = Team::with(['players']);
        
        if ($region !== 'all') {
            $query->where('region', $region);
        }
        
        $teams = $query->orderBy('rating', 'desc')->get();

        return response()->json([
            'data' => $teams,
            'total' => $teams->count(),
            'success' => true
        ]);
    }
}
