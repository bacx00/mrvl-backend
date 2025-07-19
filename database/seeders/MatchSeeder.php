<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Team;
use App\Models\Player;

class MatchSeeder extends Seeder
{
    public function run()
    {
        $teams = Team::all();
        if ($teams->count() < 2) {
            return;
        }
        $team1 = $teams[0];
        $team2 = $teams[1];

        // Create matches using DB query since we're not using Eloquent models
        DB::table('matches')->insert([
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => 2,
            'team2_score' => 1,
            'scheduled_at' => now(),
            'status' => 'completed',
            'format' => 'BO3',
            'maps_data' => json_encode([
                ['map' => 'Convoy', 'team1_score' => 13, 'team2_score' => 11, 'winner' => 'team1'],
                ['map' => 'Klyntar', 'team1_score' => 8, 'team2_score' => 13, 'winner' => 'team2'],
                ['map' => 'Midtown', 'team1_score' => 13, 'team2_score' => 7, 'winner' => 'team1']
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create another match
        DB::table('matches')->insert([
            'team1_id' => $team2->id,
            'team2_id' => $team1->id,
            'team1_score' => 0,
            'team2_score' => 3,
            'scheduled_at' => now()->subDays(2),
            'status' => 'completed',
            'format' => 'BO5',
            'maps_data' => json_encode([
                ['map' => 'Convoy', 'team1_score' => 8, 'team2_score' => 13, 'winner' => 'team2'],
                ['map' => 'Klyntar', 'team1_score' => 11, 'team2_score' => 13, 'winner' => 'team2'],
                ['map' => 'Midtown', 'team1_score' => 9, 'team2_score' => 13, 'winner' => 'team2']
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        echo "Created sample matches successfully!\n";
    }
}