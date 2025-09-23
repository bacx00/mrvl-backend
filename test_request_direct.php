<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing request processing directly...\n\n";

// Simulate the exact request data
$requestData = [
    'team1_id' => 32,
    'team2_id' => 4,
    'event_id' => null,
    'status' => 'completed',  // ← THE CRITICAL FIELD
    'format' => 'BO7',
    'scheduled_at' => '2025-09-23T10:00:00.000Z',
    'team1_score' => 4,
    'team2_score' => 2,
    'maps' => [
        [
            'map_name' => 'Test Map 1',
            'mode' => 'Domination',
            'team1_score' => 1,
            'team2_score' => 0
        ],
        [
            'map_name' => 'Test Map 2',
            'mode' => 'Convoy',
            'team1_score' => 1,
            'team2_score' => 0
        ]
    ]
];

echo "🔍 Request Analysis:\n";
echo "Status in request data: " . $requestData['status'] . "\n";

// Test Laravel's Request object behavior
$request = new \Illuminate\Http\Request();
$request->replace($requestData);

echo "Status via \$request->status: " . $request->status . "\n";
echo "Status via \$request->input('status'): " . $request->input('status') . "\n";
echo "Status via \$request->get('status'): " . $request->get('status') . "\n";
echo "Has status: " . ($request->has('status') ? 'YES' : 'NO') . "\n";
echo "Filled status: " . ($request->filled('status') ? 'YES' : 'NO') . "\n";

// Test the status fallback logic from the controller
$status = $request->status ?? 'upcoming';
echo "Status with fallback (request->status ?? 'upcoming'): " . $status . "\n";

$statusInput = $request->input('status') ?? 'upcoming';
echo "Status with input fallback (request->input('status') ?? 'upcoming'): " . $statusInput . "\n";

// Test the exact matchData construction logic from AdminMatchesController
$matchData = [
    'team1_id' => $request->team1_id,
    'team2_id' => $request->team2_id,
    'event_id' => $request->event_id,
    'scheduled_at' => $request->scheduled_at,
    'format' => $request->format,
    'status' => $request->status ?? 'upcoming', // This is the exact line from the controller
];

echo "\n📊 MatchData Analysis:\n";
echo "Final status in matchData: " . $matchData['status'] . "\n";

echo "\n✅ Direct request test completed\n";