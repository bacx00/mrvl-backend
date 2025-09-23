<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class EventStatusController extends Controller
{
    /**
     * Update event status and handle live/featured events
     */
    public function updateStatus(Request $request, $eventId)
    {
        $request->validate([
            'status' => 'required|in:upcoming,ongoing,live,completed,cancelled,scheduled',
            'featured' => 'boolean'
        ]);

        DB::beginTransaction();

        try {
            $event = Event::findOrFail($eventId);

            // Update status
            $oldStatus = $event->status;
            $event->status = $request->status;

            // Handle automatic status-based updates
            if ($request->status === 'live' || $request->status === 'ongoing') {
                // Set as live event
                $event->status = 'ongoing'; // Use 'ongoing' in DB, display as 'live' in frontend

                // If featured is requested, unfeatured other events first
                if ($request->has('featured') && $request->featured) {
                    // Only one event can be featured as live at a time
                    Event::where('featured', true)
                         ->where('status', 'ongoing')
                         ->where('id', '!=', $eventId)
                         ->update(['featured' => false]);
                }
            }

            // Update featured status if provided
            if ($request->has('featured')) {
                $event->featured = $request->featured;
            }

            // Auto-update dates based on status
            if ($request->status === 'ongoing' && $oldStatus === 'upcoming') {
                if (!$event->start_date || $event->start_date > now()) {
                    $event->start_date = now();
                }
            } elseif ($request->status === 'completed') {
                if (!$event->end_date || $event->end_date > now()) {
                    $event->end_date = now();
                }
            }

            $event->save();

            // Clear event caches
            Cache::forget('events_live');
            Cache::forget('events_featured');
            Cache::forget('homepage_data');

            // Broadcast event update if needed
            if ($request->status === 'ongoing' || $request->status === 'live') {
                broadcast(new \App\Events\TournamentUpdated($event))->toOthers();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Event status updated successfully',
                'event' => $event,
                'changes' => [
                    'old_status' => $oldStatus,
                    'new_status' => $event->status,
                    'featured' => $event->featured
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update event status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set featured live event for homepage display
     */
    public function setFeaturedLive(Request $request, $eventId)
    {
        DB::beginTransaction();

        try {
            // Find the event to feature
            $event = Event::findOrFail($eventId);

            // Verify it's a live/ongoing event
            if (!in_array($event->status, ['ongoing', 'live'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only live or ongoing events can be featured as live'
                ], 400);
            }

            // Remove featured status from all other live events
            Event::where('featured', true)
                 ->whereIn('status', ['ongoing', 'live'])
                 ->where('id', '!=', $eventId)
                 ->update(['featured' => false]);

            // Set this event as featured
            $event->featured = true;
            $event->status = 'ongoing'; // Ensure it's set to ongoing (displayed as live)
            $event->save();

            // Clear caches
            Cache::forget('events_live');
            Cache::forget('events_featured');
            Cache::forget('homepage_data');

            // Broadcast update
            broadcast(new \App\Events\TournamentUpdated($event))->toOthers();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Event set as featured live event',
                'event' => $event
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set featured event: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all live/featured events
     */
    public function getLiveEvents()
    {
        try {
            // Get featured live event first
            $featuredLive = Event::where('featured', true)
                                ->whereIn('status', ['ongoing', 'live'])
                                ->first();

            // Get all other live events
            $liveEvents = Event::whereIn('status', ['ongoing', 'live'])
                              ->where(function($q) use ($featuredLive) {
                                  if ($featuredLive) {
                                      $q->where('id', '!=', $featuredLive->id);
                                  }
                              })
                              ->orderBy('start_date', 'desc')
                              ->get();

            return response()->json([
                'success' => true,
                'featured_live' => $featuredLive,
                'live_events' => $liveEvents,
                'total_live' => $liveEvents->count() + ($featuredLive ? 1 : 0)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get live events: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch update multiple event statuses
     */
    public function batchUpdateStatus(Request $request)
    {
        $request->validate([
            'events' => 'required|array',
            'events.*.id' => 'required|exists:events,id',
            'events.*.status' => 'required|in:upcoming,ongoing,live,completed,cancelled,scheduled',
            'events.*.featured' => 'boolean'
        ]);

        DB::beginTransaction();

        try {
            $updated = [];

            foreach ($request->events as $eventData) {
                $event = Event::find($eventData['id']);

                if ($event) {
                    $event->status = $eventData['status'];

                    if (isset($eventData['featured'])) {
                        $event->featured = $eventData['featured'];
                    }

                    $event->save();
                    $updated[] = $event;
                }
            }

            // Clear caches
            Cache::forget('events_live');
            Cache::forget('events_featured');
            Cache::forget('homepage_data');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($updated) . ' events updated',
                'updated_events' => $updated
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update events: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-update event statuses based on dates
     */
    public function autoUpdateStatuses()
    {
        DB::beginTransaction();

        try {
            $now = Carbon::now();
            $updated = 0;

            // Update upcoming events that should now be ongoing
            $startingEvents = Event::where('status', 'upcoming')
                                  ->where('start_date', '<=', $now)
                                  ->where('end_date', '>=', $now)
                                  ->get();

            foreach ($startingEvents as $event) {
                $event->status = 'ongoing';
                $event->save();
                $updated++;
            }

            // Update ongoing events that should now be completed
            $endingEvents = Event::where('status', 'ongoing')
                               ->where('end_date', '<', $now)
                               ->get();

            foreach ($endingEvents as $event) {
                $event->status = 'completed';
                $event->save();
                $updated++;
            }

            // Clear caches if any updates were made
            if ($updated > 0) {
                Cache::forget('events_live');
                Cache::forget('events_featured');
                Cache::forget('homepage_data');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $updated . ' events auto-updated',
                'starting_events' => $startingEvents->count(),
                'ending_events' => $endingEvents->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to auto-update statuses: ' . $e->getMessage()
            ], 500);
        }
    }
}