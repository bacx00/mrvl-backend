<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';

// Boot the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Check matches and their events
    $matches = DB::table('matches as m')
        ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
        ->select('m.id', 'm.event_id', 'e.name as event_name', 'e.logo as event_logo')
        ->limit(10)
        ->get();
    
    echo "Current matches and events:\n";
    foreach ($matches as $match) {
        echo "- Match {$match->id}: Event {$match->event_id} ({$match->event_name}) - Logo: {$match->event_logo}\n";
    }
    
    // Get an event with a logo to use as default
    $defaultEvent = DB::table('events')
        ->whereNotNull('logo')
        ->where('logo', '!=', '')
        ->first();
    
    if ($defaultEvent) {
        echo "\nUsing default event: {$defaultEvent->name} (ID: {$defaultEvent->id})\n";
        
        // Update matches without events to use the default event
        $updated = DB::table('matches')
            ->whereNull('event_id')
            ->orWhere('event_id', 0)
            ->update(['event_id' => $defaultEvent->id]);
        
        echo "Updated {$updated} matches to use default event\n";
    }
    
    // Also ensure all events have logos
    $eventsWithoutLogos = DB::table('events')
        ->where(function($query) {
            $query->whereNull('logo')
                  ->orWhere('logo', '')
                  ->orWhere('logo', 'null');
        })
        ->update(['logo' => '/events/mrvl-invitational.jpg']);
    
    echo "Updated {$eventsWithoutLogos} events to have logos\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}