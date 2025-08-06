<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExhaustiveLiquipediaScraper;

class ScrapeAllLiquipedia extends Command
{
    protected $signature = 'liquipedia:scrape-all 
                            {--fresh : Clear existing data before import}
                            {--team= : Scrape specific team only}';
    
    protected $description = 'Exhaustively scrape all Marvel Rivals teams and players from Liquipedia';

    public function handle()
    {
        $this->info('Starting exhaustive Liquipedia scraping...');
        
        if ($this->option('fresh')) {
            $this->warn('Clearing existing data...');
            
            \DB::statement('SET FOREIGN_KEY_CHECKS=0');
            \App\Models\Player::truncate();
            \App\Models\Team::truncate();
            \DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->info('Existing data cleared.');
        }
        
        $scraper = new ExhaustiveLiquipediaScraper();
        
        try {
            $scraper->scrapeAllTeamsAndPlayers();
            
            $this->info('Scraping completed successfully!');
            $this->info('Total teams: ' . \App\Models\Team::count());
            $this->info('Total players: ' . \App\Models\Player::count());
            
            // Show teams with social media
            $this->info("\nTeams with social media:");
            \App\Models\Team::whereNotNull('twitter')
                ->orWhereNotNull('instagram')
                ->orWhereNotNull('youtube')
                ->get(['name', 'twitter', 'instagram', 'youtube'])
                ->each(function($team) {
                    $socials = [];
                    if ($team->twitter) $socials[] = 'Twitter';
                    if ($team->instagram) $socials[] = 'Instagram';
                    if ($team->youtube) $socials[] = 'YouTube';
                    $this->line("  {$team->name}: " . implode(', ', $socials));
                });
                
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}