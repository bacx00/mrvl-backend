<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;

class TeamSeeder extends Seeder
{
    public function run()
    {
        Team::create(['name' => 'Team Alpha', 'points' => 0]);
        Team::create(['name' => 'Team Beta', 'points' => 0]);
    }
}