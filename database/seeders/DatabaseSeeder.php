<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ForumSeeder::class,
            TeamSeeder::class,
            PlayerSeeder::class,
            MatchSeeder::class,
            EventSeeder::class,
        ]);
    }
}