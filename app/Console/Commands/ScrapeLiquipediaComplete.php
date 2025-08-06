<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ComprehensiveLiquipediaScraper;
use Illuminate\Support\Facades\Log;

class ScrapeLiquipediaComplete extends Command
{
    protected $signature = 'liquipedia:scrape-complete 
                            {--tournament= : Specific tournament to scrape}
                            {--update-elo : Update ELO ratings after import}
                            {--dry-run : Run without saving to database}
                            {--force : Force update even if data exists}';

    protected $description = 'Comprehensive scraping of all Marvel Rivals tournaments from Liquipedia with complete data extraction';

    private $scraper;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting comprehensive Liquipedia scraping for Marvel Rivals tournaments...');
        
        $scraper = new ComprehensiveLiquipediaScraper();
        
        $tournament = $this->option('tournament');
        $updateElo = $this->option('update-elo');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        if ($tournament) {
            $this->info("Scraping specific tournament: {$tournament}");
            $results = $scraper->scrapeSingleTournament($tournament, $updateElo, $dryRun, $force);
        } else {
            $this->info("Scraping all tournaments...");
            $results = $scraper->scrapeAllTournaments($updateElo, $dryRun, $force);
        }
        
        // Display results summary
        $this->displayResults($results);
        
        $this->info('Scraping completed successfully!');
        
        return 0;
    }
    
    private function displayResults($results)
    {
        $this->info("\n=== SCRAPING RESULTS SUMMARY ===\n");
        
        $totalEvents = 0;
        $totalTeams = 0;
        $totalPlayers = 0;
        $totalMatches = 0;
        $totalPrizePool = 0;
        
        foreach ($results as $tournamentKey => $data) {
            if (isset($data['error'])) {
                $this->error("Tournament: {$tournamentKey} - ERROR: {$data['error']}");
                continue;
            }
            
            $this->info("Tournament: {$data['event']->name}");
            $this->line("  - Region: {$data['event']->region}");
            $this->line("  - Prize Pool: $" . number_format($data['event']->prize_pool));
            $this->line("  - Teams: {$data['stats']['total_teams']}");
            $this->line("  - Matches: {$data['stats']['total_matches']}");
            $this->line("  - Players: {$data['stats']['total_players']}");
            
            $totalEvents++;
            $totalTeams += $data['stats']['total_teams'];
            $totalPlayers += $data['stats']['total_players'];
            $totalMatches += $data['stats']['total_matches'];
            $totalPrizePool += $data['event']->prize_pool;
        }
        
        $this->info("\n=== TOTAL STATISTICS ===");
        $this->info("Events Imported: {$totalEvents}");
        $this->info("Total Teams: {$totalTeams}");
        $this->info("Total Players: {$totalPlayers}");
        $this->info("Total Matches: {$totalMatches}");
        $this->info("Total Prize Pool: $" . number_format($totalPrizePool));
    }
}