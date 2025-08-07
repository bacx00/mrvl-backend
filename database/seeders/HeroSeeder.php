<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HeroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $heroes = [
            ['name' => 'Spider-Man', 'slug' => 'spider-man', 'role' => 'Duelist'],
            ['name' => 'Iron Man', 'slug' => 'iron-man', 'role' => 'Duelist'],
            ['name' => 'Captain America', 'slug' => 'captain-america', 'role' => 'Vanguard'],
            ['name' => 'Thor', 'slug' => 'thor', 'role' => 'Vanguard'],
            ['name' => 'Hulk', 'slug' => 'hulk', 'role' => 'Vanguard'],
            ['name' => 'Black Widow', 'slug' => 'black-widow', 'role' => 'Duelist'],
            ['name' => 'Hawkeye', 'slug' => 'hawkeye', 'role' => 'Duelist'],
            ['name' => 'Doctor Strange', 'slug' => 'doctor-strange', 'role' => 'Strategist'],
            ['name' => 'Scarlet Witch', 'slug' => 'scarlet-witch', 'role' => 'Duelist'],
            ['name' => 'Loki', 'slug' => 'loki', 'role' => 'Strategist'],
            ['name' => 'Venom', 'slug' => 'venom', 'role' => 'Vanguard'],
            ['name' => 'Magneto', 'slug' => 'magneto', 'role' => 'Vanguard'],
            ['name' => 'Storm', 'slug' => 'storm', 'role' => 'Duelist'],
            ['name' => 'Wolverine', 'slug' => 'wolverine', 'role' => 'Duelist'],
            ['name' => 'Groot', 'slug' => 'groot', 'role' => 'Vanguard'],
            ['name' => 'Rocket Raccoon', 'slug' => 'rocket-raccoon', 'role' => 'Strategist'],
            ['name' => 'Star-Lord', 'slug' => 'star-lord', 'role' => 'Duelist'],
            ['name' => 'Mantis', 'slug' => 'mantis', 'role' => 'Strategist'],
            ['name' => 'Adam Warlock', 'slug' => 'adam-warlock', 'role' => 'Strategist'],
            ['name' => 'Luna Snow', 'slug' => 'luna-snow', 'role' => 'Strategist'],
            ['name' => 'Jeff the Land Shark', 'slug' => 'jeff-the-land-shark', 'role' => 'Vanguard'],
            ['name' => 'Cloak & Dagger', 'slug' => 'cloak-dagger', 'role' => 'Duelist'],
            ['name' => 'Emma Frost', 'slug' => 'emma-frost', 'role' => 'Strategist'],
            ['name' => 'Bruce Banner', 'slug' => 'bruce-banner', 'role' => 'Vanguard'],
            ['name' => 'Mr. Fantastic', 'slug' => 'mister-fantastic', 'role' => 'Strategist'],
            ['name' => 'Black Panther', 'slug' => 'black-panther', 'role' => 'Duelist'],
            ['name' => 'Hela', 'slug' => 'hela', 'role' => 'Duelist'],
            ['name' => 'Magik', 'slug' => 'magik', 'role' => 'Duelist'],
            ['name' => 'Moon Knight', 'slug' => 'moon-knight', 'role' => 'Duelist'],
            ['name' => 'Namor', 'slug' => 'namor', 'role' => 'Duelist'],
            ['name' => 'Psylocke', 'slug' => 'psylocke', 'role' => 'Duelist'],
            ['name' => 'The Punisher', 'slug' => 'the-punisher', 'role' => 'Duelist'],
            ['name' => 'Winter Soldier', 'slug' => 'winter-soldier', 'role' => 'Duelist'],
            ['name' => 'Iron Fist', 'slug' => 'iron-fist', 'role' => 'Duelist'],
            ['name' => 'Squirrel Girl', 'slug' => 'squirrel-girl', 'role' => 'Strategist'],
            ['name' => 'Peni Parker', 'slug' => 'peni-parker', 'role' => 'Vanguard'],
            ['name' => 'The Thing', 'slug' => 'the-thing', 'role' => 'Vanguard'],
            ['name' => 'Human Torch', 'slug' => 'human-torch', 'role' => 'Duelist'],
            ['name' => 'Invisible Woman', 'slug' => 'invisible-woman', 'role' => 'Strategist']
        ];

        foreach ($heroes as $hero) {
            \DB::table('marvel_rivals_heroes')->updateOrInsert(
                ['slug' => $hero['slug']],
                array_merge($hero, [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'active' => true,
                    'season_added' => 'Launch',
                    'is_new' => false
                ])
            );
        }
        
        $this->command->info('Populated ' . count($heroes) . ' Marvel Rivals heroes');
    }
}
