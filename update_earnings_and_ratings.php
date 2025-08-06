<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "Updating earnings and ratings for teams and players...\n\n";

DB::beginTransaction();

try {
    // 1. Update team earnings based on their players' earnings
    echo "1. Calculating team earnings from player earnings...\n";
    
    $teams = Team::where('status', 'active')->get();
    
    foreach ($teams as $team) {
        $totalPlayerEarnings = Player::where('team_id', $team->id)
            ->where('status', 'active')
            ->sum('earnings');
        
        // Add some base team earnings
        $teamEarnings = $totalPlayerEarnings + rand(5000, 50000);
        
        $team->update(['earnings' => $teamEarnings]);
        echo "   - {$team->name}: $" . number_format($teamEarnings) . "\n";
    }
    
    // 2. Update team ratings based on player ratings
    echo "\n2. Calculating team ratings from player ratings...\n";
    
    foreach ($teams as $team) {
        $avgPlayerRating = Player::where('team_id', $team->id)
            ->where('status', 'active')
            ->avg('rating');
        
        if ($avgPlayerRating) {
            $teamRating = round($avgPlayerRating);
            $team->update([
                'rating' => $teamRating,
                'peak' => max($team->peak ?? 0, $teamRating)
            ]);
            echo "   - {$team->name}: {$teamRating} (peak: " . ($team->peak ?? 0) . ")\n";
        }
    }
    
    // 3. Update player earnings with some variety
    echo "\n3. Updating player earnings with realistic values...\n";
    
    $players = Player::where('status', 'active')->get();
    
    foreach ($players as $player) {
        // Base earnings on rating
        $rating = $player->rating ?? 1500;
        
        if ($rating >= 2300) {
            $baseEarnings = rand(50000, 150000);
        } elseif ($rating >= 2000) {
            $baseEarnings = rand(25000, 75000);
        } elseif ($rating >= 1700) {
            $baseEarnings = rand(10000, 40000);
        } else {
            $baseEarnings = rand(2000, 15000);
        }
        
        $player->update(['earnings' => $baseEarnings]);
        
        if ($player->earnings != $baseEarnings) {
            echo "   - {$player->username}: $" . number_format($baseEarnings) . "\n";
        }
    }
    
    // 4. Add some match-based rating updates (simulate recent performance)
    echo "\n4. Simulating recent performance rating changes...\n";
    
    $topPlayers = Player::where('status', 'active')
        ->orderBy('rating', 'desc')
        ->limit(20)
        ->get();
    
    foreach ($topPlayers as $player) {
        $ratingChange = rand(-50, 100); // Can lose or gain rating
        $newRating = max(1000, min(3000, $player->rating + $ratingChange));
        
        $player->update(['rating' => $newRating]);
        echo "   - {$player->username}: {$player->rating} → {$newRating} (" . ($ratingChange >= 0 ? '+' : '') . "{$ratingChange})\n";
    }
    
    // 5. Update team rankings based on new ratings
    echo "\n5. Updating team rankings...\n";
    
    $rankedTeams = Team::where('status', 'active')
        ->orderBy('rating', 'desc')
        ->get();
    
    foreach ($rankedTeams as $index => $team) {
        $team->update(['rank' => $index + 1]);
    }
    
    echo "   - Updated rankings for " . $rankedTeams->count() . " teams\n";
    
    DB::commit();
    echo "\n✅ Successfully updated earnings and ratings!\n";
    
    // Summary
    $teamStats = [
        'total_teams' => Team::where('status', 'active')->count(),
        'avg_team_rating' => round(Team::where('status', 'active')->avg('rating')),
        'total_team_earnings' => Team::where('status', 'active')->sum('earnings'),
    ];
    
    $playerStats = [
        'total_players' => Player::where('status', 'active')->count(),
        'avg_player_rating' => round(Player::where('status', 'active')->avg('rating')),
        'total_player_earnings' => Player::where('status', 'active')->sum('earnings'),
    ];
    
    echo "\n=== SUMMARY ===\n";
    echo "Teams: {$teamStats['total_teams']} | Avg Rating: {$teamStats['avg_team_rating']} | Total Earnings: $" . number_format($teamStats['total_team_earnings']) . "\n";
    echo "Players: {$playerStats['total_players']} | Avg Rating: {$playerStats['avg_player_rating']} | Total Earnings: $" . number_format($playerStats['total_player_earnings']) . "\n";
    
} catch (\Exception $e) {
    DB::rollback();
    echo "\n❌ Error updating earnings and ratings: " . $e->getMessage() . "\n";
}