<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompleteGameModesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing game modes to avoid duplicates
        $existingModes = DB::table('game_modes')->pluck('name')->toArray();
        
        // Complete Marvel Rivals Game Modes
        $allGameModes = [
            [
                'name' => 'Domination',
                'description' => 'Control strategic points across the battlefield to secure victory for your team.',
                'objective' => 'Capture and hold control points',
                'timer_config' => json_encode([
                    'preparation_time' => 45,
                    'round_time' => 180,
                    'max_rounds' => 3,
                    'overtime_duration' => 120,
                    'grace_period_ms' => 500,
                    'between_rounds' => 15
                ])
            ],
            [
                'name' => 'Convoy',
                'description' => 'Coordinate your team to move multiple objectives simultaneously across the map.',
                'objective' => 'Move convoy vehicles to endpoints',
                'timer_config' => json_encode([
                    'preparation_time' => 60,
                    'attack_time' => 480,
                    'defense_time' => 480,
                    'overtime_duration' => 180,
                    'grace_period_ms' => 500,
                    'swap_time' => 30
                ])
            ],
            [
                'name' => 'Convergence',
                'description' => 'Compete for control of a central zone that shifts dynamically throughout the match.',
                'objective' => 'Control the shifting convergence zone',
                'timer_config' => json_encode([
                    'preparation_time' => 45,
                    'capture_time' => 300,
                    'escort_time' => 300,
                    'overtime_duration' => 150,
                    'grace_period_ms' => 500,
                    'phase_transition' => 20
                ])
            ],
            [
                'name' => 'Conquest',
                'description' => 'Team deathmatch mode where first team to reach elimination target wins.',
                'objective' => 'Reach target eliminations before enemy team',
                'timer_config' => json_encode([
                    'preparation_time' => 30,
                    'match_time' => 420,
                    'target_eliminations' => 50,
                    'overtime_enabled' => false,
                    'sudden_death_at' => 45
                ])
            ],
            [
                'name' => 'Doom Match',
                'description' => 'Intense elimination-based gameplay with special victory conditions.',
                'objective' => 'Eliminate opponents and control map resources',
                'timer_config' => json_encode([
                    'preparation_time' => 60,
                    'match_time' => 600,
                    'elimination_target' => 75,
                    'overtime_duration' => 180,
                    'resource_spawns' => true
                ])
            ]
        ];

        // Add missing game modes
        $modesToAdd = [];
        foreach ($allGameModes as $mode) {
            if (!in_array($mode['name'], $existingModes)) {
                $modesToAdd[] = array_merge($mode, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        if (!empty($modesToAdd)) {
            DB::table('game_modes')->insert($modesToAdd);
            $this->command->info('Added ' . count($modesToAdd) . ' missing game modes');
            
            foreach ($modesToAdd as $mode) {
                $this->command->line("Added: {$mode['name']}");
            }
        } else {
            $this->command->info('All game modes already exist');
        }

        // Verify final count
        $finalCount = DB::table('game_modes')->count();
        $this->command->info("Total game modes in database: {$finalCount}");
        
        if ($finalCount >= 5) {
            $this->command->info('✅ Marvel Rivals game modes complete!');
        } else {
            $this->command->warn("⚠️ Expected 5 game modes, found {$finalCount}");
        }
    }
}