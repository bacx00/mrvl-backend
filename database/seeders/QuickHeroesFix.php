<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuickHeroesFix extends Seeder
{
    public function run(): void
    {
        // Check current count
        $currentCount = DB::table('marvel_heroes')->count();
        $this->command->info("Current heroes: {$currentCount}");
        
        // Get existing names
        $existing = DB::table('marvel_heroes')->pluck('name')->toArray();
        
        // Check what roles exist in the table to match ENUM
        $existingRoles = DB::table('marvel_heroes')->distinct()->pluck('role')->toArray();
        $this->command->info("Existing roles: " . implode(', ', $existingRoles));
        
        // Simple heroes to add (using exact role names from database)
        $heroesToAdd = [];
        
        // Use the EXACT role names that already exist in the database
        $roleMapping = [];
        foreach ($existingRoles as $role) {
            $roleMapping[strtolower($role)] = $role;
        }
        
        // Add missing heroes with proper role names
        $newHeroes = [
            ['name' => 'Wolverine', 'role_key' => 'vanguard'],
            ['name' => 'Invisible Woman', 'role_key' => 'vanguard'],
            ['name' => 'Moon Knight', 'role_key' => 'duelist'],
            ['name' => 'Deadpool', 'role_key' => 'duelist'],
            ['name' => 'Cyclops', 'role_key' => 'duelist'],
            ['name' => 'Daredevil', 'role_key' => 'duelist'],
            ['name' => 'Gambit', 'role_key' => 'duelist'],
            ['name' => 'Ghost Rider', 'role_key' => 'duelist'],
            ['name' => 'Professor X', 'role_key' => 'strategist'],
            ['name' => 'Shuri', 'role_key' => 'strategist'],
        ];
        
        foreach ($newHeroes as $hero) {
            if (!in_array($hero['name'], $existing)) {
                // Find matching role from existing roles
                $roleToUse = null;
                foreach ($existingRoles as $existingRole) {
                    if (stripos($existingRole, $hero['role_key']) !== false || 
                        stripos($hero['role_key'], strtolower($existingRole)) !== false) {
                        $roleToUse = $existingRole;
                        break;
                    }
                }
                
                if (!$roleToUse) {
                    // Default to first existing role
                    $roleToUse = $existingRoles[0] ?? 'Duelist';
                }
                
                $heroesToAdd[] = [
                    'name' => $hero['name'],
                    'role' => $roleToUse,
                    'abilities' => 'Special Marvel abilities',
                    'image' => null,
                    'description' => 'Marvel Rivals hero',
                    'difficulty' => 'Medium',
                    'stats' => json_encode(['damage' => 100, 'health' => 100, 'mobility' => 100]),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        
        if (!empty($heroesToAdd)) {
            try {
                DB::table('marvel_heroes')->insert($heroesToAdd);
                $this->command->info('✅ Successfully added ' . count($heroesToAdd) . ' heroes');
                
                foreach ($heroesToAdd as $hero) {
                    $this->command->line("Added: {$hero['name']} ({$hero['role']})");
                }
            } catch (\Exception $e) {
                $this->command->error('Error adding heroes: ' . $e->getMessage());
                
                // Try adding one by one to identify the problematic field
                foreach ($heroesToAdd as $hero) {
                    try {
                        DB::table('marvel_heroes')->insert([$hero]);
                        $this->command->info("✅ Added: {$hero['name']}");
                    } catch (\Exception $e2) {
                        $this->command->error("❌ Failed to add {$hero['name']}: " . $e2->getMessage());
                    }
                }
            }
        }
        
        // Final count
        $finalCount = DB::table('marvel_heroes')->count();
        $this->command->info("Final heroes count: {$finalCount}");
        
        if ($finalCount >= 39) {
            $this->command->info('🎉 TARGET ACHIEVED: 39+ heroes!');
        } else {
            $this->command->warn("Still missing: " . (39 - $finalCount) . " heroes");
        }
    }
}