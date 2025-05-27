<?php
namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::withCount('matches');

        if ($request->type && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $events = $query->orderBy('start_date', 'desc')->get();

        return response()->json([
            'data' => $events,
            'total' => $events->count(),
            'success' => true
        ]);
    }

    public function show(Event $event)
    {
        $event->load(['matches.team1', 'matches.team2']);
        return response()->json(['data' => $event, 'success' => true]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:International,Regional,Qualifier,Community',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'prize_pool' => 'nullable|string',
            'team_count' => 'nullable|integer|min:2|max:256',
            'location' => 'nullable|string',
            'organizer' => 'nullable|string',
            'description' => 'nullable|string',
            'registration_open' => 'boolean'
        ]);

        $event = Event::create($validated);

        return response()->json([
            'data' => $event,
            'success' => true,
            'message' => 'Event created successfully'
        ], 201);
    }

    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:upcoming,live,completed',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'prize_pool' => 'nullable|string',
            'team_count' => 'nullable|integer|min:2|max:256',
            'description' => 'nullable|string',
            'registration_open' => 'boolean'
        ]);

        $event->update($validated);

        return response()->json([
            'data' => $event->fresh(),
            'success' => true,
            'message' => 'Event updated successfully'
        ]);
    }

    public function destroy(Event $event)
    {
        $event->delete();
        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }
}
