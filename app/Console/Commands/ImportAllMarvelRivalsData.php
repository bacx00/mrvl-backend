<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ComprehensiveMarvelRivalsDataService;

class ImportAllMarvelRivalsData extends Command
{
    protected $signature = 'marvel:import-all 
                            {--fresh : Clear existing data before import}
                            {--regions= : Comma-separated list of regions to import (NA,EU,ASIA,CN,OCE,SA,MENA)}';
    
    protected $description = 'Import comprehensive Marvel Rivals teams and players data from all sources';

    public function handle()
    {
        $this->info('Starting comprehensive Marvel Rivals data import...');
        
        if ($this->option('fresh')) {
            $this->warn('Clearing existing data...');
            
            if ($this->confirm('This will delete all existing teams and players. Are you sure?')) {
                \DB::statement('SET FOREIGN_KEY_CHECKS=0');
                \App\Models\Player::truncate();
                \App\Models\EventStanding::truncate();
                \App\Models\GameMatch::truncate();
                \DB::table('event_teams')->truncate();
                \App\Models\Team::truncate();
                \DB::statement('SET FOREIGN_KEY_CHECKS=1');
                $this->info('Existing data cleared.');
            } else {
                return;
            }
        }
        
        $service = new ComprehensiveMarvelRivalsDataService();
        $service->importAllMarvelRivalsData();
        
        $this->info('Import completed successfully!');
        $this->info('Total teams: ' . \App\Models\Team::count());
        $this->info('Total players: ' . \App\Models\Player::count());
        
        // Show distribution
        $this->table(
            ['Region', 'Teams', 'Players'],
            \App\Models\Team::selectRaw('region, count(*) as team_count')
                ->groupBy('region')
                ->get()
                ->map(function ($row) {
                    $playerCount = \App\Models\Player::where('region', $row->region)->count();
                    return [
                        $row->region,
                        $row->team_count,
                        $playerCount
                    ];
                })
        );
    }
}