<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LiquipediaScraper;
use App\Services\EloRatingService;
use Illuminate\Support\Facades\Log;

class ScrapeLiquipedia extends Command
{
    protected $signature = 'scrape:liquipedia 
                            {--tournament= : Specific tournament to scrape}
                            {--update-elo : Recalculate ELO ratings after scraping}
                            {--dry-run : Run without saving to database}';
    
    protected $description = 'Scrape Marvel Rivals tournament data from Liquipedia';
    
    private $scraper;
    private $eloService;
    
    public function __construct(LiquipediaScraper $scraper, EloRatingService $eloService)
    {
        parent::__construct();
        $this->scraper = $scraper;
        $this->eloService = $eloService;
    }
    
    public function handle()
    {
        $this->info('Starting Liquipedia scraping...');
        
        $tournament = $this->option('tournament');
        $updateElo = $this->option('update-elo');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('Running in dry-run mode - no data will be saved');
        }
        
        try {
            if ($tournament) {
                $this->info("Scraping specific tournament: {$tournament}");
                // Implement single tournament scraping
            } else {
                $this->info('Scraping all tournaments...');
                $results = $this->scraper->scrapeAllTournaments();
                
                $this->displayResults($results);
            }
            
            if ($updateElo && !$dryRun) {
                $this->info('Recalculating ELO ratings...');
                $this->eloService->recalculateAllRatings();
                $this->info('ELO ratings updated successfully!');
            }
            
        } catch (\Exception $e) {
            $this->error('Error during scraping: ' . $e->getMessage());
            Log::error('Liquipedia scraping error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
        
        $this->info('Scraping completed successfully!');
        return 0;
    }
    
    private function displayResults($results)
    {
        foreach ($results as $tournamentKey => $data) {
            $this->info("\n=== {$tournamentKey} ===");
            
            if (isset($data['error'])) {
                $this->error("Error: {$data['error']}");
                continue;
            }
            
            if (isset($data['event'])) {
                $this->info("Event: {$data['event']->name}");
                $this->info("Prize Pool: $" . number_format($data['event']->prize_pool));
                $this->info("Date: {$data['event']->start_date} to {$data['event']->end_date}");
            }
            
            if (isset($data['teams'])) {
                $this->info("Teams: " . count($data['teams']));
                $this->table(
                    ['Team', 'Country', 'Players'],
                    collect($data['teams'])->map(function ($team) {
                        return [
                            $team['name'],
                            $team['country'] ?? 'N/A',
                            isset($team['players']) ? count($team['players']) : 0
                        ];
                    })
                );
            }
            
            if (isset($data['matches'])) {
                $this->info("Matches: " . count($data['matches']));
            }
            
            if (isset($data['standings']) && count($data['standings']) > 0) {
                $this->info("\nFinal Standings:");
                $this->table(
                    ['Position', 'Team', 'Prize'],
                    collect($data['standings'])->take(8)->map(function ($standing) {
                        return [
                            $standing->position,
                            $standing->team->name ?? 'N/A',
                            '$' . number_format($standing->prize_money)
                        ];
                    })
                );
            }
        }
    }
}