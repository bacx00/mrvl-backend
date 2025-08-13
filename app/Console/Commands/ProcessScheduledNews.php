<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\News;
use App\Services\NewsOptimizationService;
use Illuminate\Support\Facades\Log;

class ProcessScheduledNews extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'news:process-scheduled 
                            {--dry-run : Show what would be published without actually publishing}
                            {--limit=10 : Maximum number of articles to process}';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled news articles and publish them automatically';

    protected $newsOptimizationService;

    public function __construct(NewsOptimizationService $newsOptimizationService)
    {
        parent::__construct();
        $this->newsOptimizationService = $newsOptimizationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Processing scheduled news articles...');
        
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No articles will be actually published');
        }
        
        try {
            // Find articles ready to be published
            $scheduledArticles = News::readyToPublish()
                ->with('author')
                ->orderBy('scheduled_at')
                ->limit($limit)
                ->get();
            
            if ($scheduledArticles->isEmpty()) {
                $this->info('✅ No scheduled articles ready for publication');
                return Command::SUCCESS;
            }
            
            $this->info("📝 Found {$scheduledArticles->count()} article(s) ready for publication:");
            
            $publishedCount = 0;
            
            foreach ($scheduledArticles as $article) {
                $this->line("  • {$article->title} (ID: {$article->id})");
                $authorName = $article->author ? $article->author->name : 'Unknown';
                $this->line("    Author: {$authorName}");
                $scheduledTime = $article->scheduled_at ? $article->scheduled_at->format('Y-m-d H:i:s') : 'Not set';
                $this->line("    Scheduled: {$scheduledTime}");
                
                if (!$isDryRun) {
                    if ($article->publishScheduledArticle()) {
                        $this->info("    ✅ Published successfully!");
                        $publishedCount++;
                        
                        // Log the publication
                        Log::info('Scheduled news article published', [
                            'article_id' => $article->id,
                            'title' => $article->title,
                            'scheduled_at' => $article->scheduled_at,
                            'published_at' => $article->fresh()->published_at
                        ]);
                    } else {
                        $this->error("    ❌ Failed to publish");
                        Log::error('Failed to publish scheduled article', [
                            'article_id' => $article->id,
                            'title' => $article->title
                        ]);
                    }
                } else {
                    $this->comment("    📋 Would be published now");
                }
                
                $this->line('');
            }
            
            if (!$isDryRun) {
                $this->info("🎉 Successfully published {$publishedCount} article(s)");
                
                // Run optimization after publishing
                if ($publishedCount > 0) {
                    $this->info('🔧 Running post-publication optimizations...');
                    $optimizationResult = $this->newsOptimizationService->optimizeNewsLoadingPerformance();
                    
                    if ($optimizationResult['status'] === 'success') {
                        $this->info('✅ Optimizations completed');
                    } else {
                        $this->warn('⚠️ Some optimizations failed');
                    }
                }
            } else {
                $this->info("📊 DRY RUN: Would have published {$scheduledArticles->count()} article(s)");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Error processing scheduled news: {$e->getMessage()}");
            Log::error('ProcessScheduledNews command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}