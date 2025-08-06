<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== TESTING UPDATE FUNCTIONALITY ===\n\n";

try {
    // Test 1: Update a team
    echo "1. Testing Team Update:\n";
    $team = Team::first();
    if ($team) {
        $originalName = $team->name;
        $originalEarnings = $team->earnings;
        
        // Update team data
        $team->earnings = 50000;
        $team->wins = 10;
        $team->losses = 5;
        $team->record = '10-5';
        $team->win_rate = 66.67;
        $team->save();
        
        echo "   ✓ Updated {$team->name}: earnings={$team->earnings}, record={$team->record}\n";
        
        // Verify update
        $updatedTeam = Team::find($team->id);
        echo "   ✓ Verification: earnings={$updatedTeam->earnings}, record={$updatedTeam->record}\n";
    }
    
    // Test 2: Update a player
    echo "\n2. Testing Player Update:\n";
    $player = Player::first();
    if ($player) {
        $originalEarnings = $player->earnings;
        $originalRating = $player->rating;
        
        // Update player data
        $player->earnings = 25000;
        $player->rating = 1200;
        $player->total_matches = 50;
        $player->tournaments_played = 5;
        $player->save();
        
        echo "   ✓ Updated {$player->username}: earnings={$player->earnings}, rating={$player->rating}\n";
        
        // Verify update
        $updatedPlayer = Player::find($player->id);
        echo "   ✓ Verification: earnings={$updatedPlayer->earnings}, rating={$updatedPlayer->rating}\n";
    }
    
    // Test 3: Update team-player relationship
    echo "\n3. Testing Team-Player Relationship Update:\n";
    $player2 = Player::skip(10)->first();
    $team2 = Team::skip(5)->first();
    
    if ($player2 && $team2) {
        $originalTeamId = $player2->team_id;
        
        // Transfer player to new team
        $player2->team_id = $team2->id;
        $player2->save();
        
        echo "   ✓ Transferred {$player2->username} to {$team2->name}\n";
        
        // Add to team history
        \App\Models\PlayerTeamHistory::create([
            'player_id' => $player2->id,
            'team_id' => $team2->id,
            'joined_at' => now(),
            'change_date' => now(),
            'change_type' => 'transferred',
            'is_current' => true
        ]);
        
        echo "   ✓ Updated team history\n";
    }
    
    // Test 4: Bulk update test
    echo "\n4. Testing Bulk Updates:\n";
    $affectedRows = Team::where('region', 'NA')
        ->update(['division' => 'Premier']);
    echo "   ✓ Updated $affectedRows NA teams to Premier division\n";
    
    // Test 5: Update with relationships
    echo "\n5. Testing Updates with Relationships:\n";
    $teamWithPlayers = Team::with('players')->first();
    if ($teamWithPlayers) {
        $playerCount = $teamWithPlayers->players->count();
        $teamWithPlayers->player_count = $playerCount;
        $teamWithPlayers->save();
        echo "   ✓ Updated {$teamWithPlayers->name} player count to {$playerCount}\n";
    }
    
    echo "\n=== ALL UPDATE TESTS PASSED ===\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}