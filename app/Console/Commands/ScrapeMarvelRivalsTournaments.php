<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarvelRivalsTournamentScraper;

class ScrapeMarvelRivalsTournaments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mrvl:scrape-tournaments 
                            {--tournament= : Specific tournament to scrape}
                            {--dry-run : Run without saving to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape Marvel Rivals tournament data from Liquipedia';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Marvel Rivals tournament data scraping...');
        
        try {
            $scraper = new MarvelRivalsTournamentScraper();
            
            if ($this->option('tournament')) {
                $this->info('Scraping specific tournament: ' . $this->option('tournament'));
                // TODO: Implement single tournament scraping
            } else {
                $scraper->scrapeAllTournaments();
            }
            
            $this->info('Scraping completed successfully!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error during scraping: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}