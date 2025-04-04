<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Match;
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

        // Create a match between Team1 and Team2
        $match = Match::create([
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'score1'   => 16,
            'score2'   => 10,
            'date'     => now()
        ]);

        // Attach players with random stats
        $playersTeam1 = Player::where('team_id', $team1->id)->get();
        $playersTeam2 = Player::where('team_id', $team2->id)->get();
        foreach ($playersTeam1 as $player) {
            $match->players()->attach($player->id, [
                'kills'   => rand(5, 20),
                'deaths'  => rand(5, 20),
                'assists' => rand(0, 10)
            ]);
        }
        foreach ($playersTeam2 as $player) {
            $match->players()->attach($player->id, [
                'kills'   => rand(5, 20),
                'deaths'  => rand(5, 20),
                'assists' => rand(0, 10)
            ]);
        }

        // Assign points to the winning team
        if ($match->score1 > $match->score2) {
            $team1->points += 3;
            $team1->save();
        } elseif ($match->score2 > $match->score1) {
            $team2->points += 3;
            $team2->save();
        } else {
            // In case of a tie, both teams get 1 point
            $team1->points += 1;
            $team2->points += 1;
            $team1->save();
            $team2->save();
        }
    }
}