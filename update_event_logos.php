<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';

// Boot the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Update events without logos
    $updated = DB::table('events')
        ->where(function($query) {
            $query->whereNull('logo')
                  ->orWhere('logo', '')
                  ->orWhere('logo', 'null');
        })
        ->update(['logo' => '/events/championship.jpg']);
    
    echo "Updated {$updated} events with default logos\n";
    
    // Count events with logos
    $withLogos = DB::table('events')
        ->whereNotNull('logo')
        ->where('logo', '!=', '')
        ->where('logo', '!=', 'null')
        ->count();
    
    echo "Events with logos: {$withLogos}\n";
    
    // Show some events
    $events = DB::table('events')->select('id', 'name', 'logo')->limit(5)->get();
    echo "\nSample events:\n";
    foreach ($events as $event) {
        echo "- {$event->name}: {$event->logo}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}