<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::orderBy('date', 'asc')->get();
        return response()->json($events);
    }

    public function show(Event $event)
    {
        return response()->json($event);
    }
}