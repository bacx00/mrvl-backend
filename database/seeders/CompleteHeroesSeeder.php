<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompleteHeroesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get current heroes to avoid duplicates
        $existingHeroes = DB::table('marvel_heroes')->pluck('name')->toArray();
        
        // Complete Marvel Rivals Heroes List (39 total)
        $allHeroes = [
            // Vanguard (10 total)
            ['name' => 'Captain America', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Shield throw, Leadership'],
            ['name' => 'Doctor Strange', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Mystical shields, Portals'],
            ['name' => 'Groot', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Wall creation, Healing'],
            ['name' => 'Hulk', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Rage, Thunder clap'],
            ['name' => 'Magneto', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Metal manipulation, Barriers'],
            ['name' => 'Peni Parker', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Mech suit, Web barriers'],
            ['name' => 'Thor', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Mjolnir, Lightning'],
            ['name' => 'Venom', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Symbiote powers, Tendrils'],
            ['name' => 'Wolverine', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Claws, Regeneration'],
            ['name' => 'Invisible Woman', 'role' => 'Vanguard', 'type' => 'Tank', 'abilities' => 'Force fields, Invisibility'],

            // Duelist (20 total)
            ['name' => 'Black Panther', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Vibranium claws, Stealth'],
            ['name' => 'Hawkeye', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Precision arrows, Marksman'],
            ['name' => 'Hela', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Necroswords, Death magic'],
            ['name' => 'Iron Man', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Repulsors, Flight'],
            ['name' => 'Magik', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Soulsword, Teleportation'],
            ['name' => 'Namor', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Trident, Water control'],
            ['name' => 'Psylocke', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Psychic blades, Telepathy'],
            ['name' => 'Punisher', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Heavy weapons, Tactics'],
            ['name' => 'Scarlet Witch', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Chaos magic, Reality warping'],
            ['name' => 'Spider-Man', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Web shooting, Wall crawling'],
            ['name' => 'Star-Lord', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Element guns, Jet boots'],
            ['name' => 'Storm', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Weather control, Lightning'],
            ['name' => 'Winter Soldier', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Sniper rifle, Metal arm'],
            ['name' => 'Moon Knight', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Crescent darts, Lunar power'],
            ['name' => 'Deadpool', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Dual katanas, Regeneration'],
            ['name' => 'Cyclops', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Optic blasts, Leadership'],
            ['name' => 'Daredevil', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Enhanced senses, Martial arts'],
            ['name' => 'Gambit', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Kinetic cards, Staff'],
            ['name' => 'Ghost Rider', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Hellfire, Chain whip'],
            ['name' => 'Falcon', 'role' => 'Duelist', 'type' => 'DPS', 'abilities' => 'Flight, Redwing drone'],

            // Strategist (9 total)
            ['name' => 'Adam Warlock', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Resurrection, Soul gem'],
            ['name' => 'Cloak & Dagger', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Light/Dark powers, Teleportation'],
            ['name' => 'Jeff the Land Shark', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Healing, Mobility'],
            ['name' => 'Loki', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Illusions, Trickery'],
            ['name' => 'Luna Snow', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Ice healing, Buffs'],
            ['name' => 'Mantis', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Sleep inducement, Healing'],
            ['name' => 'Rocket Raccoon', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Tech support, Damage boost'],
            ['name' => 'Professor X', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Mind control, Team coordination'],
            ['name' => 'Shuri', 'role' => 'Strategist', 'type' => 'Support', 'abilities' => 'Technology, Vibranium weapons'],
        ];

        // Add missing heroes
        $heroesToAdd = [];
        foreach ($allHeroes as $hero) {
            if (!in_array($hero['name'], $existingHeroes)) {
                $heroesToAdd[] = array_merge($hero, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        if (!empty($heroesToAdd)) {
            DB::table('marvel_heroes')->insert($heroesToAdd);
            $this->command->info('Added ' . count($heroesToAdd) . ' missing heroes');
            
            foreach ($heroesToAdd as $hero) {
                $this->command->line("Added: {$hero['name']} ({$hero['role']})");
            }
        } else {
            $this->command->info('All 39 heroes already exist');
        }

        // Verify final count
        $finalCount = DB::table('marvel_heroes')->count();
        $this->command->info("Total heroes in database: {$finalCount}");
        
        if ($finalCount === 39) {
            $this->command->info('✅ Marvel Rivals heroes roster complete!');
        } else {
            $this->command->warn("⚠️ Expected 39 heroes, found {$finalCount}");
        }
    }
}