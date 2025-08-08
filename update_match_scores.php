<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function updateOverallScore($matchId) {
    $match = DB::table('matches')->where('id', $matchId)->first();
    $mapsData = json_decode($match->maps_data, true);
    
    $team1Score = 0;
    $team2Score = 0;
    
    echo "Processing match $matchId:\n";
    echo "Original scores: Team1={$match->team1_score}, Team2={$match->team2_score}\n";
    echo "Maps data:\n";
    
    foreach ($mapsData as $i => $map) {
        $team1MapScore = $map['team1_score'] ?? 0;
        $team2MapScore = $map['team2_score'] ?? 0;
        echo "  Map " . ($i+1) . ": $team1MapScore-$team2MapScore (status: {$map['status']})\n";
        
        // FIXED: Count maps that are completed OR have actual scores (not 0-0)
        $hasScores = ($team1MapScore != $team2MapScore) && ($team1MapScore > 0 || $team2MapScore > 0);
        
        if ($map['status'] === 'completed' || $hasScores) {
            if ($team1MapScore > $team2MapScore) {
                $team1Score++;
                echo "    -> Team 1 wins this map\n";
            } elseif ($team2MapScore > $team1MapScore) {
                $team2Score++;
                echo "    -> Team 2 wins this map\n";
            }
        }
    }
    
    echo "Calculated series score: Team1=$team1Score, Team2=$team2Score\n";
    
    DB::table('matches')->where('id', $matchId)->update([
        'team1_score' => $team1Score,
        'team2_score' => $team2Score
    ]);
    
    echo "✅ Updated match $matchId scores successfully!\n\n";
}

// Update match 1
updateOverallScore(1);