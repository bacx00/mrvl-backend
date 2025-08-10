<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AchievementService;

class AchievementSystemMaintenance extends Command
{
    protected $signature = 'achievement:maintenance 
                          {--check-streaks : Check and break expired streaks}
                          {--update-leaderboards : Update all leaderboard rankings}
                          {--cleanup-notifications : Clean up expired notifications}
                          {--all : Run all maintenance tasks}';

    protected $description = 'Run maintenance tasks for the achievement system';

    protected AchievementService $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        parent::__construct();
        $this->achievementService = $achievementService;
    }

    public function handle()
    {
        $this->info('🏆 Starting Achievement System Maintenance...');

        $runAll = $this->option('all');

        if ($runAll || $this->option('check-streaks')) {
            $this->checkStreaks();
        }

        if ($runAll || $this->option('update-leaderboards')) {
            $this->updateLeaderboards();
        }

        if ($runAll || $this->option('cleanup-notifications')) {
            $this->cleanupNotifications();
        }

        if (!$runAll && !$this->option('check-streaks') && !$this->option('update-leaderboards') && !$this->option('cleanup-notifications')) {
            $this->warn('No maintenance tasks specified. Use --all or specify individual tasks.');
            return 1;
        }

        $this->info('✅ Achievement System Maintenance completed!');
        return 0;
    }

    private function checkStreaks()
    {
        $this->info('⚡ Checking expired streaks...');
        
        $startTime = microtime(true);
        $this->achievementService->checkExpiredStreaks();
        $duration = round(microtime(true) - $startTime, 2);
        
        $this->info("   ✓ Streak check completed in {$duration}s");
    }

    private function updateLeaderboards()
    {
        $this->info('📊 Updating leaderboards...');
        
        $startTime = microtime(true);
        $this->achievementService->updateLeaderboards();
        $duration = round(microtime(true) - $startTime, 2);
        
        $this->info("   ✓ Leaderboards updated in {$duration}s");
    }

    private function cleanupNotifications()
    {
        $this->info('🧹 Cleaning up expired notifications...');
        
        $startTime = microtime(true);
        $this->achievementService->cleanupExpiredNotifications();
        $duration = round(microtime(true) - $startTime, 2);
        
        $this->info("   ✓ Notification cleanup completed in {$duration}s");
    }
}