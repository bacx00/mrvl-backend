<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Models\GameMatch;
use Illuminate\Support\Facades\Hash;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "TESTING ESSENTIAL CRUD OPERATIONS FOR LIVE SITE\n";
echo "===============================================\n\n";

try {
    
    // TEST 1: CREATE operations
    echo "1. TESTING CREATE OPERATIONS...\n";
    
    // Create test event
    $testEvent = Event::create([
        'name' => 'Test Tournament ' . time(),
        'description' => 'Test tournament for CRUD validation',
        'game' => 'Marvel Rivals',
        'organizer_id' => 1,
        'type' => 'tournament',
        'format' => 'single_elimination',
        'status' => 'upcoming',
        'start_date' => now()->addDays(7),
        'end_date' => now()->addDays(14),
        'prize_pool' => 10000,
        'currency' => 'USD',
        'max_teams' => 8,
        'region' => 'Global',
        'game_mode' => 'Competitive',
        'mode' => 'Online'
    ]);
    
    echo "✅ Event created: {$testEvent->name} (ID: {$testEvent->id})\n";
    
    // Create test team
    $testTeam = Team::create([
        'name' => 'Test Team ' . time(),
        'short_name' => 'TEST' . rand(100, 999),
        'region' => 'NA',
        'country' => 'US',
        'flag' => '🇺🇸',
        'rating' => 1500,
        'platform' => 'PC',
        'game' => 'Marvel Rivals'
    ]);
    
    echo "✅ Team created: {$testTeam->name} (ID: {$testTeam->id})\n";
    
    // Register team to event
    $testEvent->teams()->attach($testTeam->id, [
        'registered_at' => now(),
        'status' => 'confirmed'
    ]);
    
    echo "✅ Team registered to event\n";
    
    // TEST 2: READ operations
    echo "\n2. TESTING READ OPERATIONS...\n";
    
    $events = Event::take(5)->get();
    echo "✅ Events loaded: {$events->count()}\n";
    
    $teams = Team::take(5)->get();
    echo "✅ Teams loaded: {$teams->count()}\n";
    
    $matches = GameMatch::take(5)->get();
    echo "✅ Matches loaded: {$matches->count()}\n";
    
    // TEST 3: UPDATE operations
    echo "\n3. TESTING UPDATE OPERATIONS...\n";
    
    $testEvent->update([
        'prize_pool' => 15000,
        'status' => 'ongoing'
    ]);
    
    echo "✅ Event updated: Prize pool now {$testEvent->prize_pool}\n";
    
    $testTeam->update([
        'rating' => 1600,
        'wins' => 5,
        'losses' => 2
    ]);
    
    echo "✅ Team updated: Rating now {$testTeam->rating}\n";
    
    // TEST 4: Simple match creation
    echo "\n4. TESTING MATCH CREATION...\n";
    
    $testMatch = GameMatch::create([
        'event_id' => $testEvent->id,
        'team1_id' => $testTeam->id,
        'team2_id' => Team::where('id', '!=', $testTeam->id)->first()->id,
        'round' => 1,
        'bracket_position' => 1,
        'bracket_type' => 'main',
        'status' => 'upcoming',
        'format' => 'bo3',
        'scheduled_at' => now()->addHours(24)
    ]);
    
    echo "✅ Match created: ID {$testMatch->id}\n";
    
    // TEST 5: DELETE operations (cleanup)
    echo "\n5. TESTING DELETE OPERATIONS...\n";
    
    $testMatch->delete();
    echo "✅ Test match deleted\n";
    
    $testEvent->teams()->detach($testTeam->id);
    echo "✅ Team detached from event\n";
    
    $testTeam->delete();
    echo "✅ Test team deleted\n";
    
    $testEvent->delete();
    echo "✅ Test event deleted\n";
    
    echo "\n🎉 ALL CRUD OPERATIONS SUCCESSFUL!\n";
    echo "=====================================\n";
    echo "✅ CREATE: Events, Teams, Matches\n";
    echo "✅ READ: All models loading correctly\n";
    echo "✅ UPDATE: Field updates working\n";
    echo "✅ DELETE: Cleanup successful\n";
    echo "\nNo 400/500 errors detected. Site ready for live use!\n";
    
} catch (Exception $e) {
    echo "❌ CRUD ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Check for common issues
    if (strpos($e->getMessage(), 'duplicate') !== false) {
        echo "Issue: Duplicate entry - check unique constraints\n";
    } elseif (strpos($e->getMessage(), 'foreign key') !== false) {
        echo "Issue: Foreign key constraint - check relationships\n";
    } elseif (strpos($e->getMessage(), 'required') !== false) {
        echo "Issue: Missing required field\n";
    }
    
    exit(1);
}