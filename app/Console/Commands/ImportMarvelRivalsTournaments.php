<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SimpleLiquipediaScraper;

class ImportMarvelRivalsTournaments extends Command
{
    protected $signature = 'marvel:import-tournaments {--fresh : Clear existing data before import}';
    protected $description = 'Import Marvel Rivals tournament data with complete information';

    protected $scraper;

    public function __construct(SimpleLiquipediaScraper $scraper)
    {
        parent::__construct();
        $this->scraper = $scraper;
    }

    public function handle()
    {
        $this->info('Marvel Rivals Tournament Data Import');
        $this->info('=====================================');
        
        if ($this->option('fresh')) {
            $this->warn('Clearing existing tournament data...');
            
            \App\Models\EventStanding::truncate();
            \App\Models\MatchMap::truncate();
            \App\Models\GameMatch::truncate();
            \App\Models\Player::truncate();
            \App\Models\Team::truncate();
            \App\Models\Event::truncate();
            
            $this->info('✓ Existing data cleared');
        }
        
        $this->info('Starting tournament import...');
        
        try {
            $results = $this->scraper->importAllTournaments();
            
            $this->info("\n✓ Import completed successfully!\n");
            
            // Display summary
            $totalTeams = 0;
            $totalPrize = 0;
            
            foreach ($results as $key => $result) {
                $this->info("Tournament: " . $result['event']->name);
                $this->line("  - Teams: " . $result['teams_imported']);
                $this->line("  - Prize Pool: $" . number_format($result['total_prize_pool']));
                
                $totalTeams += $result['teams_imported'];
                $totalPrize += $result['total_prize_pool'];
            }
            
            $this->info("\n=== IMPORT SUMMARY ===");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Events', \App\Models\Event::count()],
                    ['Total Teams', \App\Models\Team::count()],
                    ['Total Players', \App\Models\Player::count()],
                    ['Total Matches', \App\Models\GameMatch::count()],
                    ['Total Prize Pool', '$' . number_format($totalPrize)],
                    ['Teams with Social Media', \App\Models\Team::whereNotNull('twitter')->count()],
                    ['Average Team ELO', round(\App\Models\Team::avg('rating'))]
                ]
            );
            
            $this->info("\n=== REGIONAL DISTRIBUTION ===");
            $regionalData = \App\Models\Team::selectRaw('region, COUNT(*) as count')
                ->groupBy('region')
                ->get()
                ->map(function($item) {
                    return [$item->region, $item->count];
                })
                ->toArray();
            
            $this->table(['Region', 'Teams'], $regionalData);
            
            $this->info("\n✓ All tournament data has been imported successfully!");
            $this->info("You can now access the data through the API endpoints.");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Error during import: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}