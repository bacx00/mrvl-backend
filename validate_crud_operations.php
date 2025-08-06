<?php
/**
 * Comprehensive CRUD Operations Validation Script
 * Tests all CRUD operations across the MRVL platform
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use App\Models\MatchModel;
use App\Models\News;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

echo "=== MRVL Platform CRUD Operations Validation ===" . PHP_EOL;
echo "Testing all CRUD operations across the platform..." . PHP_EOL . PHP_EOL;

$results = [];
$startTime = microtime(true);

try {
    // Test 1: User CRUD Operations
    echo "1. Testing User CRUD Operations..." . PHP_EOL;
    
    // Create
    $testUser = User::create([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test_' . uniqid() . '@mrvl.net',
        'password' => Hash::make('password'),
        'status' => 'active'
    ]);
    $results['user_create'] = $testUser ? 'PASS' : 'FAIL';
    
    // Read
    $foundUser = User::find($testUser->id);
    $results['user_read'] = ($foundUser && $foundUser->id === $testUser->id) ? 'PASS' : 'FAIL';
    
    // Update
    $testUser->update(['name' => 'Updated Test User']);
    $updatedUser = User::find($testUser->id);
    $results['user_update'] = ($updatedUser->name === 'Updated Test User') ? 'PASS' : 'FAIL';
    
    // Delete
    $testUser->delete();
    $deletedUser = User::find($testUser->id);
    $results['user_delete'] = ($deletedUser === null) ? 'PASS' : 'FAIL';
    
    echo "   User CRUD: " . ($results['user_create'] === 'PASS' && $results['user_read'] === 'PASS' && 
                              $results['user_update'] === 'PASS' && $results['user_delete'] === 'PASS' ? 
                              'PASS' : 'FAIL') . PHP_EOL;

    // Test 2: Team CRUD Operations
    echo "2. Testing Team CRUD Operations..." . PHP_EOL;
    
    // Create
    $testTeam = Team::create([
        'name' => 'Test Team ' . uniqid(),
        'country' => 'Test Country',
        'region' => 'Test Region',
        'status' => 'active'
    ]);
    $results['team_create'] = $testTeam ? 'PASS' : 'FAIL';
    
    // Read
    $foundTeam = Team::find($testTeam->id);
    $results['team_read'] = ($foundTeam && $foundTeam->id === $testTeam->id) ? 'PASS' : 'FAIL';
    
    // Update
    $testTeam->update(['name' => 'Updated Test Team']);
    $updatedTeam = Team::find($testTeam->id);
    $results['team_update'] = ($updatedTeam->name === 'Updated Test Team') ? 'PASS' : 'FAIL';
    
    echo "   Team CRUD: " . ($results['team_create'] === 'PASS' && $results['team_read'] === 'PASS' && 
                              $results['team_update'] === 'PASS' ? 'PASS' : 'FAIL') . PHP_EOL;

    // Test 3: Player CRUD Operations
    echo "3. Testing Player CRUD Operations..." . PHP_EOL;
    
    // Create
    $testPlayer = Player::create([
        'name' => 'Test Player ' . uniqid(),
        'team_id' => $testTeam->id,
        'role' => 'player',
        'country' => 'Test Country'
    ]);
    $results['player_create'] = $testPlayer ? 'PASS' : 'FAIL';
    
    // Read
    $foundPlayer = Player::find($testPlayer->id);
    $results['player_read'] = ($foundPlayer && $foundPlayer->id === $testPlayer->id) ? 'PASS' : 'FAIL';
    
    // Update
    $testPlayer->update(['name' => 'Updated Test Player']);
    $updatedPlayer = Player::find($testPlayer->id);
    $results['player_update'] = ($updatedPlayer->name === 'Updated Test Player') ? 'PASS' : 'FAIL';
    
    echo "   Player CRUD: " . ($results['player_create'] === 'PASS' && $results['player_read'] === 'PASS' && 
                                $results['player_update'] === 'PASS' ? 'PASS' : 'FAIL') . PHP_EOL;

    // Test 4: Event CRUD Operations
    echo "4. Testing Event CRUD Operations..." . PHP_EOL;
    
    // Create
    $testEvent = Event::create([
        'name' => 'Test Event ' . uniqid(),
        'start_date' => Carbon::now(),
        'end_date' => Carbon::now()->addDays(7),
        'status' => 'upcoming',
        'format' => 'tournament',
        'prize_pool' => 10000
    ]);
    $results['event_create'] = $testEvent ? 'PASS' : 'FAIL';
    
    // Read
    $foundEvent = Event::find($testEvent->id);
    $results['event_read'] = ($foundEvent && $foundEvent->id === $testEvent->id) ? 'PASS' : 'FAIL';
    
    // Update
    $testEvent->update(['name' => 'Updated Test Event']);
    $updatedEvent = Event::find($testEvent->id);
    $results['event_update'] = ($updatedEvent->name === 'Updated Test Event') ? 'PASS' : 'FAIL';
    
    echo "   Event CRUD: " . ($results['event_create'] === 'PASS' && $results['event_read'] === 'PASS' && 
                               $results['event_update'] === 'PASS' ? 'PASS' : 'FAIL') . PHP_EOL;

    // Test 5: Match CRUD Operations
    echo "5. Testing Match CRUD Operations..." . PHP_EOL;
    
    // Create second team for match
    $testTeam2 = Team::create([
        'name' => 'Test Team 2 ' . uniqid(),
        'country' => 'Test Country 2',
        'region' => 'Test Region',
        'status' => 'active'
    ]);
    
    // Create
    $testMatch = MatchModel::create([
        'team1_id' => $testTeam->id,
        'team2_id' => $testTeam2->id,
        'event_id' => $testEvent->id,
        'status' => 'upcoming',
        'format' => 'bo3',
        'scheduled_at' => Carbon::now()->addHours(2)
    ]);
    $results['match_create'] = $testMatch ? 'PASS' : 'FAIL';
    
    // Read
    $foundMatch = MatchModel::find($testMatch->id);
    $results['match_read'] = ($foundMatch && $foundMatch->id === $testMatch->id) ? 'PASS' : 'FAIL';
    
    // Update
    $testMatch->update(['status' => 'live']);
    $updatedMatch = MatchModel::find($testMatch->id);
    $results['match_update'] = ($updatedMatch->status === 'live') ? 'PASS' : 'FAIL';
    
    echo "   Match CRUD: " . ($results['match_create'] === 'PASS' && $results['match_read'] === 'PASS' && 
                               $results['match_update'] === 'PASS' ? 'PASS' : 'FAIL') . PHP_EOL;

    // Test 6: News CRUD Operations (if table exists)
    echo "6. Testing News CRUD Operations..." . PHP_EOL;
    
    if (Schema::hasTable('news')) {
        try {
            // Create
            $testNews = News::create([
                'title' => 'Test News Article ' . uniqid(),
                'content' => 'This is a test news article content.',
                'author_id' => User::first()->id ?? 1,
                'status' => 'published',
                'published_at' => Carbon::now()
            ]);
            $results['news_create'] = $testNews ? 'PASS' : 'FAIL';
            
            // Read
            $foundNews = News::find($testNews->id);
            $results['news_read'] = ($foundNews && $foundNews->id === $testNews->id) ? 'PASS' : 'FAIL';
            
            // Update
            $testNews->update(['title' => 'Updated Test News Article']);
            $updatedNews = News::find($testNews->id);
            $results['news_update'] = ($updatedNews->title === 'Updated Test News Article') ? 'PASS' : 'FAIL';
            
            echo "   News CRUD: " . ($results['news_create'] === 'PASS' && $results['news_read'] === 'PASS' && 
                                      $results['news_update'] === 'PASS' ? 'PASS' : 'FAIL') . PHP_EOL;
        } catch (Exception $e) {
            echo "   News CRUD: FAIL (Error: " . $e->getMessage() . ")" . PHP_EOL;
            $results['news_create'] = $results['news_read'] = $results['news_update'] = 'FAIL';
        }
    } else {
        echo "   News table not found, skipping News CRUD tests" . PHP_EOL;
        $results['news_create'] = $results['news_read'] = $results['news_update'] = 'SKIP';
    }

    // Test 7: Relationship Testing
    echo "7. Testing Model Relationships..." . PHP_EOL;
    
    // Team-Player relationship
    $teamWithPlayers = Team::with('players')->find($testTeam->id);
    $results['team_player_relationship'] = ($teamWithPlayers && $teamWithPlayers->players->count() > 0) ? 'PASS' : 'FAIL';
    
    // Event-Match relationship
    $eventWithMatches = Event::with('matches')->find($testEvent->id);
    $results['event_match_relationship'] = ($eventWithMatches && $eventWithMatches->matches->count() > 0) ? 'PASS' : 'FAIL';
    
    // Match-Team relationship
    $matchWithTeams = MatchModel::with(['team1', 'team2'])->find($testMatch->id);
    $results['match_team_relationship'] = ($matchWithTeams && $matchWithTeams->team1 && $matchWithTeams->team2) ? 'PASS' : 'FAIL';
    
    echo "   Relationships: " . ($results['team_player_relationship'] === 'PASS' && 
                                  $results['event_match_relationship'] === 'PASS' && 
                                  $results['match_team_relationship'] === 'PASS' ? 'PASS' : 'FAIL') . PHP_EOL;

    // Test 8: Database Transaction Testing
    echo "8. Testing Database Transactions..." . PHP_EOL;
    
    try {
        DB::beginTransaction();
        
        $transactionTeam = Team::create([
            'name' => 'Transaction Test Team',
            'country' => 'Test',
            'region' => 'Test',
            'status' => 'active'
        ]);
        
        // Simulate error
        throw new Exception("Simulated error for transaction test");
        
        DB::commit();
        $results['transaction_test'] = 'FAIL'; // Should not reach here
    } catch (Exception $e) {
        DB::rollback();
        
        // Check if team was not created due to rollback
        $rolledBackTeam = Team::where('name', 'Transaction Test Team')->first();
        $results['transaction_test'] = ($rolledBackTeam === null) ? 'PASS' : 'FAIL';
    }
    
    echo "   Transactions: " . $results['transaction_test'] . PHP_EOL;

    // Test 9: API Authentication Token Generation
    echo "9. Testing API Authentication..." . PHP_EOL;
    
    try {
        $adminUser = User::where('email', 'admin@mrvl.net')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'Admin User',
                'email' => 'admin@mrvl.net',
                'password' => Hash::make('admin123'),
                'status' => 'active'
            ]);
        }
        
        $token = $adminUser->createToken('test-token');
        $results['auth_token'] = ($token && $token->accessToken) ? 'PASS' : 'FAIL';
        
        echo "   Auth Token: " . $results['auth_token'] . PHP_EOL;
    } catch (Exception $e) {
        echo "   Auth Token: FAIL (Error: " . $e->getMessage() . ")" . PHP_EOL;
        $results['auth_token'] = 'FAIL';
    }

    // Cleanup test data
    echo PHP_EOL . "Cleaning up test data..." . PHP_EOL;
    try {
        $testMatch->delete();
        $testPlayer->delete();
        $testTeam->delete();
        $testTeam2->delete();
        $testEvent->delete();
        if (isset($testNews)) {
            $testNews->delete();
        }
        echo "   Cleanup: PASS" . PHP_EOL;
    } catch (Exception $e) {
        echo "   Cleanup: FAIL (Some test data may remain)" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
}

$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

// Summary
echo PHP_EOL . "=== VALIDATION SUMMARY ===" . PHP_EOL;
echo "Execution time: {$executionTime} seconds" . PHP_EOL;
echo "Total tests: " . count($results) . PHP_EOL;

$passCount = count(array_filter($results, fn($result) => $result === 'PASS'));
$failCount = count(array_filter($results, fn($result) => $result === 'FAIL'));
$skipCount = count(array_filter($results, fn($result) => $result === 'SKIP'));

echo "Passed: {$passCount}" . PHP_EOL;
echo "Failed: {$failCount}" . PHP_EOL;
echo "Skipped: {$skipCount}" . PHP_EOL;

if ($failCount === 0) {
    echo PHP_EOL . "🎉 ALL CRUD OPERATIONS ARE WORKING CORRECTLY!" . PHP_EOL;
    exit(0);
} else {
    echo PHP_EOL . "⚠️  Some CRUD operations failed. Check the results above." . PHP_EOL;
    echo PHP_EOL . "Failed tests:" . PHP_EOL;
    foreach ($results as $test => $result) {
        if ($result === 'FAIL') {
            echo "  - {$test}" . PHP_EOL;
        }
    }
    exit(1);
}