<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Http\Controllers\BracketController;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "SIMPLE BRACKET GENERATION TEST\n";
echo "==============================\n\n";

try {
    // Get the China tournament
    $event = Event::where('name', 'like', '%China%')->first();
    
    if (!$event) {
        echo "❌ China tournament not found\n";
        exit(1);
    }
    
    echo "✅ Found event: {$event->name} (ID: {$event->id})\n";
    
    // Get teams for this event
    $teams = DB::table('event_teams')
        ->join('teams', 'event_teams.team_id', '=', 'teams.id')
        ->where('event_teams.event_id', $event->id)
        ->select('teams.*')
        ->get();
    
    echo "✅ Found {$teams->count()} teams registered\n";
    
    // Create bracket controller instance
    $controller = new BracketController();
    
    // Test single elimination bracket generation
    echo "\n🎯 Testing Single Elimination Bracket...\n";
    
    $request = new Request([
        'format' => 'single_elimination',
        'seeding_type' => 'rating'
    ]);
    
    $result = $controller->generate($request, $event->id);
    $response = $result->getData(true);
    
    if ($response['success']) {
        echo "✅ Single elimination bracket generated successfully\n";
        echo "   Matches created: " . count($response['data']['matches'] ?? []) . "\n";
    } else {
        echo "❌ Single elimination failed: " . $response['message'] . "\n";
    }
    
    // Test double elimination
    echo "\n🎯 Testing Double Elimination Bracket...\n";
    
    $request = new Request([
        'format' => 'double_elimination',
        'seeding_type' => 'rating'
    ]);
    
    $result = $controller->generate($request, $event->id);
    $response = $result->getData(true);
    
    if ($response['success']) {
        echo "✅ Double elimination bracket generated successfully\n";
        echo "   Matches created: " . count($response['data']['matches'] ?? []) . "\n";
    } else {
        echo "❌ Double elimination failed: " . $response['message'] . "\n";
    }
    
    // Test Swiss format
    echo "\n🎯 Testing Swiss Format Bracket...\n";
    
    $request = new Request([
        'format' => 'swiss',
        'seeding_type' => 'rating',
        'swiss_rounds' => 4
    ]);
    
    $result = $controller->generate($request, $event->id);
    $response = $result->getData(true);
    
    if ($response['success']) {
        echo "✅ Swiss format bracket generated successfully\n";
        echo "   Matches created: " . count($response['data']['matches'] ?? []) . "\n";
    } else {
        echo "❌ Swiss format failed: " . $response['message'] . "\n";
    }
    
    // Test Round Robin
    echo "\n🎯 Testing Round Robin Bracket...\n";
    
    $request = new Request([
        'format' => 'round_robin',
        'seeding_type' => 'rating'
    ]);
    
    $result = $controller->generate($request, $event->id);
    $response = $result->getData(true);
    
    if ($response['success']) {
        echo "✅ Round robin bracket generated successfully\n";
        echo "   Matches created: " . count($response['data']['matches'] ?? []) . "\n";
    } else {
        echo "❌ Round robin failed: " . $response['message'] . "\n";
    }
    
    echo "\n🎯 BRACKET GENERATION TEST COMPLETE\n";
    echo "===================================\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}