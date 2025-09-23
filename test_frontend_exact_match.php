<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AdminMatchesController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "🧪 Testing exact frontend request to AdminMatchesController...\n\n";

// Simulate authentication (assuming user ID 1 exists)
$user = \App\Models\User::find(1);
if ($user) {
    Auth::login($user);
    echo "✅ Authenticated as user: {$user->name}\n";
} else {
    echo "❌ Could not find user for authentication\n";
    exit(1);
}

// Create exact request data that the frontend sends
$requestData = [
    'team1_id' => 32,
    'team2_id' => 4,
    'event_id' => null,
    'status' => 'completed',  // ← THE CRITICAL FIELD
    'format' => 'BO7',
    'scheduled_at' => '2025-09-23T10:00:00.000Z',
    'team1_score' => 4,
    'team2_score' => 2,
    'stream_urls' => [],
    'betting_urls' => [],
    'vod_urls' => [],
    'round' => null,
    'bracket_position' => null,
    'allow_past_date' => false,
    'maps' => [
        [
            'map_name' => 'Test Map 1',
            'mode' => 'Domination',
            'team1_score' => 1,
            'team2_score' => 0,
            'winner_id' => 32
        ],
        [
            'map_name' => 'Test Map 2',
            'mode' => 'Convoy',
            'team1_score' => 1,
            'team2_score' => 0,
            'winner_id' => 32
        ],
        [
            'map_name' => 'Test Map 3',
            'mode' => 'Domination',
            'team1_score' => 1,
            'team2_score' => 0,
            'winner_id' => 32
        ]
    ]
];

echo "Request data status: " . $requestData['status'] . "\n";

// Create Request object
$request = new Request();
$request->replace($requestData);

echo "Request object status: " . $request->status . "\n";
echo "Request has status: " . ($request->has('status') ? 'YES' : 'NO') . "\n";
echo "Request filled status: " . ($request->filled('status') ? 'YES' : 'NO') . "\n\n";

try {
    $controller = new AdminMatchesController();

    echo "📝 Calling AdminMatchesController::store()...\n";
    $response = $controller->store($request);

    $responseData = $response->getData(true);

    echo "\n📊 Response Analysis:\n";
    echo "Success: " . ($responseData['success'] ? 'YES' : 'NO') . "\n";

    if ($responseData['success']) {
        $match = $responseData['data'];
        echo "Match ID: " . $match['id'] . "\n";
        echo "Status in response: " . $match['status'] . "\n";

        // Check what's actually in the database
        $dbMatch = DB::table('matches')->where('id', $match['id'])->first();
        echo "Status in database: " . $dbMatch->status . "\n";

        // Clean up
        DB::table('matches')->where('id', $match['id'])->delete();
        DB::table('match_maps')->where('match_id', $match['id'])->delete();

        echo "\n✅ Test completed - match cleaned up\n";
    } else {
        echo "❌ Request failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
        if (isset($responseData['errors'])) {
            print_r($responseData['errors']);
        }
    }

} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Test finished\n";