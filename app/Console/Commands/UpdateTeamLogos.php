<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateTeamLogos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:update-logos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update team logo paths in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting team logo path update...');

        // Get all teams from database
        $teams = DB::table('teams')->get();
        $updated = 0;

        foreach($teams as $team) {
            // Skip if logo is already a proper path
            if (strpos($team->logo, '/') === 0 || strpos($team->logo, 'http') === 0) {
                continue;
            }
            
            // Update logo path to include proper prefix
            $logoPath = "/images/teams/{$team->logo}";
            
            // Update the team with correct logo path
            DB::table('teams')
                ->where('id', $team->id)
                ->update([
                    'logo' => $logoPath,
                    'updated_at' => now()
                ]);
            
            $this->line("Updated {$team->name} logo: {$logoPath}");
            $updated++;
        }

        $this->info("Successfully updated {$updated} teams with proper logo paths!");
        return 0;
    }
}
