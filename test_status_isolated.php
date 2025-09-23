<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MvrlMatch;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "🧪 Testing status creation in isolation...\n\n";

// Test 1: Direct model creation
echo "1. Testing direct model creation:\n";
$directMatch = MvrlMatch::create([
    'team1_id' => 32,
    'team2_id' => 4,
    'scheduled_at' => Carbon::now()->addDay(),
    'format' => 'BO3',
    'status' => 'completed',
    'team1_score' => 2,
    'team2_score' => 1
]);

echo "   Created match ID: {$directMatch->id}\n";
echo "   Status after creation: {$directMatch->status}\n";
echo "   Status from fresh() call: {$directMatch->fresh()->status}\n\n";

// Test 2: Using mass assignment with more data
echo "2. Testing with full match data:\n";
$fullMatch = MvrlMatch::create([
    'team1_id' => 32,
    'team2_id' => 4,
    'event_id' => null,
    'scheduled_at' => Carbon::now()->addDay(),
    'format' => 'BO7',
    'status' => 'completed',
    'team1_score' => 4,
    'team2_score' => 2,
    'maps_data' => json_encode([
        [
            'map_number' => 1,
            'map_name' => 'Test Map 1',
            'mode' => 'Domination',
            'team1_score' => 1,
            'team2_score' => 0,
            'status' => 'completed'
        ]
    ])
]);

echo "   Created match ID: {$fullMatch->id}\n";
echo "   Status after creation: {$fullMatch->status}\n";
echo "   Status from fresh() call: {$fullMatch->fresh()->status}\n\n";

// Test 3: Direct DB insertion
echo "3. Testing direct DB insertion:\n";
$dbResult = DB::table('matches')->insert([
    'team1_id' => 32,
    'team2_id' => 4,
    'scheduled_at' => Carbon::now()->addDay(),
    'format' => 'BO3',
    'status' => 'completed',
    'team1_score' => 2,
    'team2_score' => 1,
    'created_at' => now(),
    'updated_at' => now()
]);

$lastId = DB::getPdo()->lastInsertId();
$dbMatch = DB::table('matches')->where('id', $lastId)->first();

echo "   Inserted match ID: {$lastId}\n";
echo "   Status from DB: {$dbMatch->status}\n\n";

// Clean up
echo "🧹 Cleaning up test matches...\n";
MvrlMatch::whereIn('id', [$directMatch->id, $fullMatch->id, $lastId])->delete();

echo "✅ Test complete!\n";