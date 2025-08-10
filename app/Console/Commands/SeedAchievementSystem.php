<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\AchievementSystemSeeder;

class SeedAchievementSystem extends Command
{
    protected $signature = 'achievement:seed {--force : Force seeding even in production}';
    protected $description = 'Seed the achievement system with initial data';

    public function handle()
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('⚠️  This command is disabled in production. Use --force to override.');
            return 1;
        }

        $this->info('🌱 Seeding Achievement System...');

        try {
            $seeder = new AchievementSystemSeeder();
            $seeder->run();

            $this->info('✅ Achievement System seeded successfully!');
            $this->line('');
            $this->info('Created:');
            $this->line('  • Initial achievements for user engagement');
            $this->line('  • Sample challenges for community building');
            $this->line('  • Leaderboards for competition and recognition');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Failed to seed achievement system: ' . $e->getMessage());
            return 1;
        }
    }
}