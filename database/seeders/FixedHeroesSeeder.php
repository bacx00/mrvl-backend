<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixedHeroesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check actual table structure
        $columns = Schema::getColumnListing('marvel_heroes');
        $this->command->info('Marvel Heroes table columns: ' . implode(', ', $columns));
        
        // Get current heroes to avoid duplicates
        $existingHeroes = DB::table('marvel_heroes')->pluck('name')->toArray();
        $this->command->info('Current heroes count: ' . count($existingHeroes));
        
        // Missing heroes (simplified structure to match existing table)
        $missingHeroes = [
            // Vanguard (add 2 missing)
            ['name' => 'Wolverine', 'role' => 'Vanguard'],
            ['name' => 'Invisible Woman', 'role' => 'Vanguard'],

            // Duelist (add 6 missing)
            ['name' => 'Moon Knight', 'role' => 'Duelist'],
            ['name' => 'Deadpool', 'role' => 'Duelist'],
            ['name' => 'Cyclops', 'role' => 'Duelist'],
            ['name' => 'Daredevil', 'role' => 'Duelist'],
            ['name' => 'Gambit', 'role' => 'Duelist'],
            ['name' => 'Ghost Rider', 'role' => 'Duelist'],

            // Strategist (add 2 missing)
            ['name' => 'Professor X', 'role' => 'Strategist'],
            ['name' => 'Shuri', 'role' => 'Strategist'],
        ];

        // Add only heroes that don't exist and match table structure
        $heroesToAdd = [];
        foreach ($missingHeroes as $hero) {
            if (!in_array($hero['name'], $existingHeroes)) {
                $heroData = [
                    'name' => $hero['name'],
                    'role' => $hero['role'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                // Add additional columns if they exist
                if (in_array('type', $columns)) {
                    $heroData['type'] = $hero['role'] === 'Strategist' ? 'Support' : ($hero['role'] === 'Vanguard' ? 'Tank' : 'DPS');
                }
                
                if (in_array('abilities', $columns)) {
                    $abilities = [
                        'Wolverine' => 'Claws, Regeneration',
                        'Invisible Woman' => 'Force fields, Invisibility',
                        'Moon Knight' => 'Crescent darts, Lunar power',
                        'Deadpool' => 'Dual katanas, Regeneration',
                        'Cyclops' => 'Optic blasts, Leadership',
                        'Daredevil' => 'Enhanced senses, Martial arts',
                        'Gambit' => 'Kinetic cards, Staff',
                        'Ghost Rider' => 'Hellfire, Chain whip',
                        'Professor X' => 'Mind control, Team coordination',
                        'Shuri' => 'Technology, Vibranium weapons',
                    ];
                    $heroData['abilities'] = $abilities[$hero['name']] ?? 'Special abilities';
                }
                
                $heroesToAdd[] = $heroData;
            }
        }

        if (!empty($heroesToAdd)) {
            DB::table('marvel_heroes')->insert($heroesToAdd);
            $this->command->info('✅ Added ' . count($heroesToAdd) . ' missing heroes');
            
            foreach ($heroesToAdd as $hero) {
                $this->command->line("Added: {$hero['name']} ({$hero['role']})");
            }
        } else {
            $this->command->info('All heroes already exist');
        }

        // Verify final count
        $finalCount = DB::table('marvel_heroes')->count();
        $this->command->info("Final heroes count: {$finalCount}");
        
        if ($finalCount >= 39) {
            $this->command->info('🎉 Marvel Rivals heroes roster complete!');
        } else {
            $this->command->warn("Current: {$finalCount}/39 heroes");
        }
    }
}