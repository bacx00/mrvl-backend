<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Creating sample match data for testing team and player statistics...\n";

try {
    // Get some teams to create matches between
    $teams = DB::table('teams')->limit(10)->get();
    
    if ($teams->count() < 4) {
        echo "Need at least 4 teams to create matches. Found: " . $teams->count() . "\n";
        exit(1);
    }

    // Get an event to associate matches with
    $event = DB::table('events')->first();
    if (!$event) {
        // Create a test event
        $eventId = DB::table('events')->insertGetId([
            'name' => 'Marvel Rivals Test Championship',
            'type' => 'tournament',
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(1),
            'prize_pool' => '$100,000',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "Created test event with ID: $eventId\n";
    } else {
        $eventId = $event->id;
        echo "Using existing event: {$event->name} (ID: $eventId)\n";
    }

    // Create 20 completed matches with realistic scores
    $matchesCreated = 0;
    $teamsArray = $teams->toArray();
    
    for ($i = 0; $i < 20; $i++) {
        // Pick two random teams
        $team1 = $teamsArray[array_rand($teamsArray)];
        $team2 = $teamsArray[array_rand($teamsArray)];
        
        // Ensure teams are different
        while ($team1->id === $team2->id) {
            $team2 = $teamsArray[array_rand($teamsArray)];
        }

        // Generate realistic scores (Best of 3 format)
        $team1Score = rand(0, 3);
        $team2Score = rand(0, 3);
        
        // Ensure one team wins
        if ($team1Score === $team2Score) {
            if (rand(0, 1)) {
                $team1Score = 3;
                $team2Score = rand(0, 2);
            } else {
                $team2Score = 3;
                $team1Score = rand(0, 2);
            }
        }

        $winnerId = $team1Score > $team2Score ? $team1->id : $team2->id;

        $matchId = DB::table('matches')->insertGetId([
            'event_id' => $eventId,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => $team1Score,
            'team2_score' => $team2Score,
            'winner_id' => $winnerId,
            'status' => 'completed',
            'format' => 'bo3',
            'scheduled_at' => now()->subDays(rand(1, 30)),
            'started_at' => now()->subDays(rand(1, 30)),
            'ended_at' => now()->subDays(rand(1, 30)),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        echo "Created match $matchId: {$team1->name} ($team1Score) vs {$team2->name} ($team2Score)\n";
        $matchesCreated++;

        // Create map data for this match
        for ($map = 1; $map <= max($team1Score + $team2Score, 3); $map++) {
            $mapWinner = null;
            if ($map <= $team1Score) {
                $mapWinner = $team1->id;
            } elseif ($map <= $team1Score + $team2Score) {
                $mapWinner = $team2->id;
            }

            if ($mapWinner) {
                DB::table('match_maps')->insert([
                    'match_id' => $matchId,
                    'map_number' => $map,
                    'map_name' => ['Midtown', 'Convoy', 'Tokyo 2099', 'Klyntar', 'Intergalactic Empire of Wakanda'][rand(0, 4)],
                    'game_mode' => ['Escort', 'Control', 'Hybrid'][rand(0, 2)],
                    'status' => 'completed',
                    'winner_id' => $mapWinner,
                    'team1_score' => $mapWinner === $team1->id ? 1 : 0,
                    'team2_score' => $mapWinner === $team2->id ? 1 : 0,
                    'duration_seconds' => rand(300, 800),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Create player stats for this match
        $team1Players = DB::table('players')->where('team_id', $team1->id)->get();
        $team2Players = DB::table('players')->where('team_id', $team2->id)->get();

        foreach ([$team1Players, $team2Players] as $teamPlayers) {
            foreach ($teamPlayers as $player) {
                // Generate realistic Marvel Rivals stats
                $eliminations = rand(8, 35);
                $deaths = rand(3, 15);
                $assists = rand(5, 25);
                $damageDealt = rand(3000, 15000);
                $damageTaken = rand(2000, 8000);
                $healingDone = $player->role === 'strategist' ? rand(5000, 12000) : rand(0, 2000);
                $damageBlocked = $player->role === 'vanguard' ? rand(3000, 10000) : rand(0, 1000);

                DB::table('match_player_stats')->insert([
                    'match_id' => $matchId,
                    'player_id' => $player->id,
                    'team_id' => $player->team_id,
                    'hero' => ['Iron Man', 'Spider-Man', 'Doctor Strange', 'Hulk', 'Thor', 'Black Widow'][rand(0, 5)],
                    'eliminations' => $eliminations,
                    'deaths' => $deaths,
                    'assists' => $assists,
                    'kda_ratio' => $deaths > 0 ? round(($eliminations + $assists) / $deaths, 2) : $eliminations + $assists,
                    'damage_dealt' => $damageDealt,
                    'damage_taken' => $damageTaken,
                    'healing_done' => $healingDone,
                    'damage_blocked' => $damageBlocked,
                    'mvp_score' => rand(50, 100),
                    'time_played' => rand(600, 1200),
                    'objective_time' => rand(60, 300),
                    'ultimates_used' => rand(2, 8),
                    'is_mvp' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }

    echo "\n=== Sample Match Data Creation Complete ===\n";
    echo "Created $matchesCreated completed matches\n";
    
    // Now update team statistics based on the matches
    echo "\nUpdating team statistics based on match results...\n";
    
    foreach ($teams as $team) {
        $matchStats = DB::select("
            SELECT 
                COUNT(*) as total_matches,
                SUM(CASE WHEN winner_id = ? THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN winner_id != ? THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN team1_id = ? THEN team1_score ELSE team2_score END) as maps_won,
                SUM(CASE WHEN team1_id = ? THEN team2_score ELSE team1_score END) as maps_lost
            FROM matches 
            WHERE (team1_id = ? OR team2_id = ?) AND status = 'completed'
        ", [$team->id, $team->id, $team->id, $team->id, $team->id, $team->id]);

        $stats = $matchStats[0];
        $winRate = $stats->total_matches > 0 ? round(($stats->wins / $stats->total_matches) * 100, 1) : 0;
        $record = $stats->wins . '-' . $stats->losses;

        DB::table('teams')->where('id', $team->id)->update([
            'wins' => $stats->wins,
            'losses' => $stats->losses,
            'win_rate' => $winRate,
            'record' => $record,
            'maps_won' => $stats->maps_won,
            'maps_lost' => $stats->maps_lost,
            'updated_at' => now()
        ]);

        echo "Updated {$team->name}: {$stats->wins}W-{$stats->losses}L ({$winRate}% win rate)\n";
    }

    echo "\n✅ Successfully created sample match data and updated team statistics!\n";
    echo "Teams and players should now show proper match history and statistics.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}