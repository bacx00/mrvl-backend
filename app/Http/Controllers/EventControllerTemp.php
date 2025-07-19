<?php
namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventControllerTemp extends Controller
{
    public function index(Request $request)
    {
        try {
            // Use only columns that exist in the basic events table
            $query = DB::table('events');

            // Filter by status
            if ($request->status && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Search functionality
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'LIKE', "%{$request->search}%")
                      ->orWhere('description', 'LIKE', "%{$request->search}%");
                });
            }

            // Sort by upcoming events
            $query->orderBy('start_date', 'asc');

            $events = $query->get();

            // Format events with available data
            $eventsData = collect($events)->map(function($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => null, // Not available
                    'description' => $event->description,
                    'logo' => $event->logo ?? null, // Use logo field
                    'banner' => null, // Not available
                    'type' => $event->type,
                    'status' => $event->status,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'prize_pool' => $event->prize_pool ?? 0,
                    'currency' => $event->currency ?? 'USD',
                    'max_teams' => $event->max_teams ?? 0,
                    'current_teams' => 0, // Not tracked in basic table
                    'location' => null, // Not available
                    'region' => $event->region ?? 'INTL',
                    'format' => $event->format,
                    'organizer' => null, // Not tracked here
                    'registration_open' => false, // Calculate from dates
                    'stream_viewers' => 0, // Not tracked
                    'teams' => [], // No teams relationship in basic table
                    'matches' => [] // No matches relationship in basic table
                ];
            });

            return response()->json([
                'data' => $eventsData,
                'status' => 'success'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Failed to fetch events'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $event = DB::table('events')->where('id', $id)->first();
            
            if (!$event) {
                return response()->json([
                    'message' => 'Event not found'
                ], 404);
            }

            // Format single event with available data
            $eventData = [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => null,
                'description' => $event->description,
                'logo' => $event->logo,
                'banner' => null,
                'type' => $event->type,
                'status' => $event->status,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'prize_pool' => intval(str_replace(['$', ',', 'K', 'M'], ['', '', '000', '000000'], $event->prize_pool ?? '0')),
                'currency' => $event->currency ?? 'USD',
                'max_teams' => $event->max_teams,
                'current_teams' => 0,
                'location' => $event->location ?? null,
                'region' => $event->region ?? 'International',
                'format' => $event->format,
                'organizer' => $event->organizer ?? null,
                'registration_open' => $event->registration_open ?? false,
                'stream_viewers' => $event->stream_viewers ?? 0,
                'teams' => [],
                'matches' => [],
                'bracket' => null
            ];

            return response()->json([
                'data' => $eventData,
                'status' => 'success'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Failed to fetch event details'
            ], 500);
        }
    }
}