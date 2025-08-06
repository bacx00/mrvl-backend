<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Start the match manually
$match = App\Models\MvrlMatch::find(237);
if ($match) {
    echo "Starting match manually...\n";
    
    // Update match status to live
    $match->status = 'live';
    $match->started_at = now();
    
    // Update first map to live
    $mapsData = $match->maps_data;
    $mapsData[0]['status'] = 'live';
    $mapsData[0]['started_at'] = now();
    $match->maps_data = $mapsData;
    $match->current_map_number = 1;
    
    $match->save();
    
    echo "Match started successfully!\n";
    echo "Status: " . $match->status . "\n";
    echo "Current Map: " . $match->current_map_number . "\n";
    echo "Map 1 Status: " . $match->maps_data[0]['status'] . "\n";
} else {
    echo "Match not found!\n";
}