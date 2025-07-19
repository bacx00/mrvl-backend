<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Player;

class PlayerSeeder extends Seeder
{
    public function run()
    {
        $teams = Team::all();
        $playersData = [
            'Team Alpha' => ['Alice', 'Aaron', 'Ava', 'Albert', 'Amy'],
            'Team Beta'  => ['Bob', 'Bella', 'Ben', 'Bianca', 'Bill']
        ];
        foreach ($teams as $team) {
            if (isset($playersData[$team->name])) {
                foreach ($playersData[$team->name] as $playerName) {
                    Player::create([
                        'name'    => $playerName,
                        'team_id' => $team->id
                    ]);
                }
            }
        }
    }
}