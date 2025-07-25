<?php

// Test Live Scoring Endpoints

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\MvrlMatch;
use App\Models\Team;
use App\Models\Player;

// Get admin user
$admin = User::where('role', 'admin')->first();
if (!$admin) {
    $admin = User::first();
    $admin->role = 'admin';
    $admin->save();
}

echo "🔐 Testing as admin user: {$admin->name} (ID: {$admin->id})\n\n";

// Get test match
$match = MvrlMatch::with(['team1', 'team2'])->first();
if (!$match) {
    echo "❌ No matches found. Creating test match...\n";
    
    // Get two teams
    $team1 = Team::first();
    $team2 = Team::where('id', '!=', $team1->id)->first();
    
    $match = MvrlMatch::create([
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'scheduled_at' => now(),
        'format' => 'BO3',
        'status' => 'live',
        'team1_score' => 0,
        'team2_score' => 0,
        'current_map_number' => 1,
        'maps_data' => json_encode([
            ['map_name' => 'Tokyo 2099', 'mode' => 'Domination', 'team1_score' => 0, 'team2_score' => 0],
            ['map_name' => 'Klyntar', 'mode' => 'Convoy', 'team1_score' => 0, 'team2_score' => 0],
            ['map_name' => 'Wakanda', 'mode' => 'Convergence', 'team1_score' => 0, 'team2_score' => 0]
        ])
    ]);
}

echo "🎮 Testing with match: {$match->team1->name} vs {$match->team2->name} (ID: {$match->id})\n\n";

// Test endpoints
$tests = [
    [
        'name' => 'Update Live Scoring',
        'method' => 'POST',
        'endpoint' => "/api/admin/matches/{$match->id}/live-scoring",
        'data' => [
            'status' => 'live',
            'current_map' => 1,
            'series_score' => ['team1' => 1, 'team2' => 0],
            'current_map_data' => [
                'name' => 'Tokyo 2099',
                'mode' => 'Domination',
                'team1Score' => 75,
                'team2Score' => 50,
                'status' => 'ongoing'
            ],
            'hero_selections' => [
                ['player_id' => 1, 'hero' => 'Spider-Man', 'team' => 1],
                ['player_id' => 2, 'hero' => 'Iron Man', 'team' => 1],
                ['player_id' => 7, 'hero' => 'Venom', 'team' => 2],
                ['player_id' => 8, 'hero' => 'Doctor Strange', 'team' => 2]
            ],
            'player_stats' => [
                '1' => ['eliminations' => 15, 'deaths' => 3, 'assists' => 8],
                '2' => ['eliminations' => 12, 'deaths' => 5, 'assists' => 10],
                '7' => ['eliminations' => 10, 'deaths' => 7, 'assists' => 5],
                '8' => ['eliminations' => 8, 'deaths' => 4, 'assists' => 12]
            ]
        ]
    ],
    [
        'name' => 'Update Match Action - Pause',
        'method' => 'POST',
        'endpoint' => "/api/admin/matches/{$match->id}/action",
        'data' => [
            'action' => 'pause'
        ]
    ],
    [
        'name' => 'Update Match Action - Resume',
        'method' => 'POST',
        'endpoint' => "/api/admin/matches/{$match->id}/action",
        'data' => [
            'action' => 'resume'
        ]
    ],
    [
        'name' => 'Get Match Details',
        'method' => 'GET',
        'endpoint' => "/api/matches/{$match->id}",
        'data' => null
    ],
    [
        'name' => 'Get Live Matches',
        'method' => 'GET',
        'endpoint' => "/api/matches?status=live",
        'data' => null
    ]
];

// Run tests
foreach ($tests as $test) {
    echo "📋 Testing: {$test['name']}\n";
    echo "   Endpoint: {$test['method']} {$test['endpoint']}\n";
    
    try {
        // Create request
        $request = \Illuminate\Http\Request::create(
            $test['endpoint'],
            $test['method'],
            $test['data'] ?: [],
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken]
        );
        
        // Handle request
        $response = $kernel->handle($request);
        $content = json_decode($response->getContent(), true);
        
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            echo "   ✅ Success (Status: {$response->getStatusCode()})\n";
            if (isset($content['message'])) {
                echo "   Message: {$content['message']}\n";
            }
            if (isset($content['data'])) {
                echo "   Data: " . json_encode($content['data'], JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "   ❌ Failed (Status: {$response->getStatusCode()})\n";
            echo "   Response: " . json_encode($content, JSON_PRETTY_PRINT) . "\n";
        }
        
    } catch (\Exception $e) {
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Test WebSocket event broadcasting
echo "🔔 Testing WebSocket Event Broadcasting...\n";
try {
    // Trigger a live update event
    event(new \App\Events\LiveMatchUpdate($match, 'test_update', [
        'test' => true,
        'timestamp' => now()->toISOString()
    ]));
    echo "   ✅ LiveMatchUpdate event dispatched successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Error dispatching event: " . $e->getMessage() . "\n";
}

echo "\n🎯 Live Scoring Endpoint Tests Complete!\n";