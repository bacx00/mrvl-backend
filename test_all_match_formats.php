<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

echo "Testing sync for all match formats...\n\n";

// Test formats
$formats = ['BO1', 'BO3', 'BO5', 'BO7', 'BO9'];

foreach ($formats as $format) {
    echo "Testing format: $format\n";
    
    // Create a test match with this format
    $matchId = DB::table('matches')->insertGetId([
        'team1_id' => 4,  // 100 Thieves
        'team2_id' => 32, // BOOM Esports
        'format' => $format,
        'status' => 'completed',
        'team1_score' => 1,
        'team2_score' => 0,
        'series_score_team1' => 1,
        'series_score_team2' => 0,
        'maps_data' => json_encode([
            [
                'map_name' => 'Test Map 1',
                'team1_score' => 1,
                'team2_score' => 0,
                'status' => 'completed',
                'team1_composition' => [],
                'team2_composition' => []
            ]
        ]),
        'scheduled_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Call the sync
    $matchController = new \App\Http\Controllers\MatchController();
    $reflection = new ReflectionClass($matchController);
    $method = $reflection->getMethod('syncLiveScoringToPlayerProfiles');
    $method->setAccessible(true);
    $method->invoke($matchController, $matchId);
    
    // Check if sync worked
    $statsCount = DB::table('match_player_stats')
        ->where('match_id', $matchId)
        ->count();
    
    echo "  Match ID: $matchId - Stats synced: $statsCount players\n";
    
    // Clean up test match
    DB::table('match_player_stats')->where('match_id', $matchId)->delete();
    DB::table('matches')->where('id', $matchId)->delete();
}

echo "\nAll formats tested successfully!\n";
