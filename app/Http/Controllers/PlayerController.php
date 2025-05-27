<?php
namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        $query = Player::with(['team']);

        if ($request->role && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('username', 'LIKE', "%{$request->search}%")
                  ->orWhere('real_name', 'LIKE', "%{$request->search}%");
            });
        }

        $players = $query->orderBy('rating', 'desc')->get();

        return response()->json([
            'data' => $players,
            'total' => $players->count(),
            'success' => true
        ]);
    }

    public function show(Player $player)
    {
        $player->load(['team']);
        return response()->json(['data' => $player, 'success' => true]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:players',
            'real_name' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
            'role' => 'required|in:Duelist,Tank,Support,Controller',
            'main_hero' => 'required|string',
            'alt_heroes' => 'nullable|array',
            'region' => 'required|string|max:10',
            'country' => 'required|string',
            'rating' => 'nullable|numeric|min:0',
            'age' => 'nullable|integer|min:13|max:50',
            'social_media' => 'nullable|array',
            'biography' => 'nullable|string'
        ]);

        $player = Player::create($validated);

        return response()->json([
            'data' => $player->load('team'),
            'success' => true,
            'message' => 'Player created successfully'
        ], 201);
    }

    public function update(Request $request, Player $player)
    {
        $validated = $request->validate([
            'username' => 'sometimes|string|max:255|unique:players,username,' . $player->id,
            'real_name' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
            'role' => 'sometimes|in:Duelist,Tank,Support,Controller',
            'main_hero' => 'sometimes|string',
            'alt_heroes' => 'nullable|array',
            'region' => 'sometimes|string|max:10',
            'country' => 'sometimes|string',
            'rating' => 'nullable|numeric|min:0',
            'age' => 'nullable|integer|min:13|max:50',
            'social_media' => 'nullable|array',
            'biography' => 'nullable|string'
        ]);

        $player->update($validated);

        return response()->json([
            'data' => $player->fresh()->load('team'),
            'success' => true,
            'message' => 'Player updated successfully'
        ]);
    }

    public function destroy(Player $player)
    {
        $player->delete();
        return response()->json([
            'success' => true,
            'message' => 'Player deleted successfully'
        ]);
    }
}
