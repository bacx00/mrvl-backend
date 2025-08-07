<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsTestSeeder extends Seeder
{
    public function run()
    {
        // Add some Marvel Rivals maps if they don't exist
        $maps = [
            ['name' => 'Asgard Throne Room', 'game_mode' => 'Domination', 'is_competitive' => true],
            ['name' => 'Wakanda Palace', 'game_mode' => 'Convoy', 'is_competitive' => true],
            ['name' => 'Sanctum Sanctorum', 'game_mode' => 'Convergence', 'is_competitive' => true],
            ['name' => 'Tokyo 2099', 'game_mode' => 'Domination', 'is_competitive' => true],
            ['name' => 'Klyntar', 'game_mode' => 'Convoy', 'is_competitive' => true]
        ];

        foreach ($maps as $map) {
            DB::table('marvel_rivals_maps')->updateOrInsert(
                ['name' => $map['name']],
                array_merge($map, [
                    'status' => 'active',
                    'description' => 'Marvel Rivals competitive map',
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        // Add some sample match maps data if we have matches
        $matches = DB::table('matches')->get();
        $validTeamIds = DB::table('teams')->pluck('id')->toArray();
        
        if ($matches->count() > 0 && !empty($validTeamIds)) {
            $mapNames = ['Asgard Throne Room', 'Wakanda Palace', 'Sanctum Sanctorum', 'Tokyo 2099', 'Klyntar'];
            
            foreach ($matches as $match) {
                // Update match with valid team IDs if needed
                $team1Id = in_array($match->team1_id, $validTeamIds) ? $match->team1_id : $validTeamIds[0];
                $team2Id = in_array($match->team2_id, $validTeamIds) ? $match->team2_id : $validTeamIds[1] ?? $validTeamIds[0];
                
                // Update the match with valid team IDs
                DB::table('matches')->where('id', $match->id)->update([
                    'team1_id' => $team1Id,
                    'team2_id' => $team2Id
                ]);
                
                // Add 1-3 maps per match
                $numMaps = rand(1, 3);
                
                for ($i = 1; $i <= $numMaps; $i++) {
                    $mapName = $mapNames[array_rand($mapNames)];
                    $team1Score = rand(0, 3);
                    $team2Score = rand(0, 3);
                    $winner = $team1Score > $team2Score ? $team1Id : $team2Id;
                    
                    $gameMode = ['Domination', 'Convoy', 'Convergence'][array_rand(['Domination', 'Convoy', 'Convergence'])];
                    
                    DB::table('match_maps')->updateOrInsert([
                        'match_id' => $match->id,
                        'map_number' => $i
                    ], [
                        'map_name' => $mapName,
                        'game_mode' => $gameMode,
                        'team1_score' => $team1Score,
                        'team2_score' => $team2Score,
                        'winner_id' => $winner,
                        'status' => 'completed',
                        'started_at' => Carbon::parse($match->scheduled_at ?: now())->addMinutes($i * 20),
                        'ended_at' => Carbon::parse($match->scheduled_at ?: now())->addMinutes($i * 20 + 15),
                        'duration_seconds' => rand(600, 1200), // 10-20 minutes in seconds
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        // Add some sample match player stats
        $players = DB::table('players')->take(20)->get();
        $heroes = DB::table('marvel_rivals_heroes')->take(10)->get();
        
        if ($matches->count() > 0 && $players->count() > 0 && $heroes->count() > 0) {
            foreach ($matches as $match) {
                // Select 6-12 players per match
                $matchPlayers = $players->random(rand(6, 12));
                
                foreach ($matchPlayers as $player) {
                    $hero = $heroes->random();
                    $eliminations = rand(5, 25);
                    $deaths = rand(2, 15);
                    $assists = rand(3, 20);
                    
                    DB::table('match_player_stats')->updateOrInsert([
                        'match_id' => $match->id,
                        'player_id' => $player->id
                    ], [
                        'team_id' => $player->team_id ?: $team1Id,
                        'hero' => $hero->name,
                        'eliminations' => $eliminations,
                        'deaths' => $deaths,
                        'assists' => $assists,
                        'damage_dealt' => rand(3000, 8000),
                        'damage_taken' => rand(1000, 4000),
                        'healing_done' => $hero->role === 'Strategist' ? rand(2000, 5000) : rand(0, 500),
                        'healing_received' => rand(500, 2000),
                        'damage_blocked' => $hero->role === 'Vanguard' ? rand(1000, 3000) : rand(0, 500),
                        'ultimates_used' => rand(1, 4),
                        'time_played' => rand(600, 1200), // seconds
                        'objective_time' => rand(30, 300), // seconds
                        'kda_ratio' => round(($eliminations + $assists) / max($deaths, 1), 2),
                        'mvp_score' => rand(50, 100),
                        'is_mvp' => false,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        $this->command->info('Analytics test data seeded successfully!');
        $this->command->info('Maps: ' . DB::table('marvel_rivals_maps')->count());
        $this->command->info('Match Maps: ' . DB::table('match_maps')->count());
        $this->command->info('Match Player Stats: ' . DB::table('match_player_stats')->count());
    }
}