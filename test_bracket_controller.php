<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\BracketController;
use Illuminate\Http\Request;

echo "Testing BracketController directly...\n\n";

try {
    // Create test event
    $firstUser = DB::table('users')->first();
    $organizerId = $firstUser ? $firstUser->id : 1;
    
    $eventId = DB::table('events')->insertGetId([
        'name' => 'Bracket Test Tournament',
        'slug' => 'bracket-test-tournament-' . time(),
        'description' => 'Testing bracket generation',
        'type' => 'tournament',
        'format' => 'single_elimination',
        'region' => 'GLOBAL',
        'game_mode' => 'team_vs_team',
        'organizer_id' => $organizerId,
        'start_date' => now()->addDays(1),
        'end_date' => now()->addDays(3),
        'max_teams' => 8,
        'status' => 'upcoming',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "✅ Created test event with ID: $eventId\n";
    
    // Get test teams and add them to tournament
    $teams = DB::table('teams')->take(8)->get();
    echo "✅ Found " . count($teams) . " teams for testing\n";
    
    foreach ($teams as $team) {
        DB::table('tournament_participants')->insert([
            'event_id' => $eventId,
            'team_id' => $team->id,
            'status' => 'confirmed',
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    echo "✅ Added teams to tournament\n";
    
    // Test bracket generation
    $controller = new BracketController();
    $request = new Request();
    $request->merge(['format' => 'single_elimination']);
    
    echo "🏆 Testing single elimination bracket generation...\n";
    $response = $controller->generate($request, $eventId);
    
    if ($response->getStatusCode() === 200) {
        echo "✅ Single elimination bracket generated successfully\n";
        
        // Check matches created
        $matches = DB::table('matches')->where('event_id', $eventId)->get();
        echo "✅ Created " . count($matches) . " matches\n";
        
        // Test double elimination
        DB::table('matches')->where('event_id', $eventId)->delete();
        DB::table('events')->where('id', $eventId)->update(['format' => 'double_elimination']);
        
        echo "🏆 Testing double elimination bracket generation...\n";
        $request->merge(['format' => 'double_elimination']);
        $response = $controller->generate($request, $eventId);
        
        if ($response->getStatusCode() === 200) {
            echo "✅ Double elimination bracket generated successfully\n";
            
            $matches = DB::table('matches')->where('event_id', $eventId)->get();
            echo "✅ Created " . count($matches) . " matches\n";
            
            $upperMatches = DB::table('matches')->where('event_id', $eventId)->where('bracket_type', 'upper')->count();
            $lowerMatches = DB::table('matches')->where('event_id', $eventId)->where('bracket_type', 'lower')->count();
            
            echo "  - Upper bracket: $upperMatches matches\n";
            echo "  - Lower bracket: $lowerMatches matches\n";
        } else {
            echo "❌ Double elimination failed: " . $response->getContent() . "\n";
        }
        
        // Test Swiss system
        DB::table('matches')->where('event_id', $eventId)->delete();
        DB::table('events')->where('id', $eventId)->update(['format' => 'swiss']);
        
        echo "🏆 Testing Swiss system bracket generation...\n";
        $request->merge(['format' => 'swiss']);
        $response = $controller->generate($request, $eventId);
        
        if ($response->getStatusCode() === 200) {
            echo "✅ Swiss system bracket generated successfully\n";
            
            $matches = DB::table('matches')->where('event_id', $eventId)->get();
            echo "✅ Created " . count($matches) . " matches\n";
        } else {
            echo "❌ Swiss system failed: " . $response->getContent() . "\n";
        }
        
    } else {
        echo "❌ Single elimination failed: " . $response->getContent() . "\n";
    }
    
    // Clean up
    DB::table('matches')->where('event_id', $eventId)->delete();
    DB::table('tournament_participants')->where('event_id', $eventId)->delete();
    DB::table('events')->where('id', $eventId)->delete();
    
    echo "\n✅ All bracket systems tested successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error testing bracket controller: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}