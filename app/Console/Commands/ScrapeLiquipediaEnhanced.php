<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedLiquipediaScraper;
use Illuminate\Support\Facades\Log;

class ScrapeLiquipediaEnhanced extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liquipedia:scrape-enhanced 
                            {--tournament= : Specific tournament key to scrape}
                            {--no-elo : Skip ELO rating updates}
                            {--dry-run : Run without saving to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enhanced scraping of Marvel Rivals tournaments from Liquipedia with complete data';

    /**
     * The scraper service
     *
     * @var EnhancedLiquipediaScraper
     */
    protected $scraper;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(EnhancedLiquipediaScraper $scraper)
    {
        parent::__construct();
        $this->scraper = $scraper;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting enhanced Liquipedia scraping for Marvel Rivals tournaments...');
        
        $tournament = $this->option('tournament');
        $updateElo = !$this->option('no-elo');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be saved to database');
        }
        
        try {
            if ($tournament) {
                $this->info("Scraping specific tournament: {$tournament}");
                $tournaments = $this->scraper->getTournamentConfig();
                
                if (!isset($tournaments[$tournament])) {
                    $this->error("Tournament key '{$tournament}' not found!");
                    $this->info("Available tournaments: " . implode(', ', array_keys($tournaments)));
                    return 1;
                }
                
                $results = [$tournament => $this->scraper->scrapeTournament($tournaments[$tournament])];
            } else {
                $this->info("Scraping all configured tournaments...");
                $results = $this->scraper->scrapeAllTournaments($updateElo);
            }
            
            // Display results summary
            $this->displayResults($results);
            
            $this->info('Enhanced scraping completed successfully!');
            
            // Show data verification commands
            $this->info("\nTo verify the imported data, run:");
            $this->line("php artisan tinker");
            $this->line(">>> App\Models\Event::count()");
            $this->line(">>> App\Models\Team::count()");
            $this->line(">>> App\Models\Player::count()");
            $this->line(">>> App\Models\GameMatch::count()");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Error during scraping: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            Log::error('Enhanced Liquipedia scraping failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Display the scraping results
     *
     * @param array $results
     * @return void
     */
    protected function displayResults($results)
    {
        $totalEvents = 0;
        $totalTeams = 0;
        $totalPlayers = 0;
        $totalMatches = 0;
        $totalPrizePool = 0;
        
        foreach ($results as $key => $result) {
            if (isset($result['error'])) {
                $this->error("❌ {$key}: " . $result['error']);
                continue;
            }
            
            $this->info("\n📋 Tournament: " . ($result['event']->name ?? $key));
            
            if (isset($result['event'])) {
                $totalEvents++;
                $totalPrizePool += $result['event']->prize_pool;
                
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Region', $result['event']->region],
                        ['Dates', $result['event']->start_date . ' to ' . $result['event']->end_date],
                        ['Prize Pool', '$' . number_format($result['event']->prize_pool)],
                        ['Status', $result['event']->status],
                        ['Tier', $result['event']->tier]
                    ]
                );
            }
            
            if (isset($result['stats'])) {
                $totalTeams += $result['stats']['total_teams'];
                $totalMatches += $result['stats']['total_matches'];
                
                $this->info("Teams: " . $result['stats']['total_teams']);
                $this->info("Matches: " . $result['stats']['total_matches']);
            }
            
            if (isset($result['teams'])) {
                $playerCount = 0;
                foreach ($result['teams'] as $team) {
                    if (isset($team['roster'])) {
                        $playerCount += count($team['roster']);
                    }
                }
                $totalPlayers += $playerCount;
                $this->info("Players: " . $playerCount);
            }
            
            // Show top 3 standings if available
            if (isset($result['standings']) && count($result['standings']) > 0) {
                $this->info("\n🏆 Top 3 Teams:");
                $topTeams = array_slice($result['standings'], 0, 3);
                foreach ($topTeams as $standing) {
                    $team = \App\Models\Team::find($standing->team_id);
                    if ($team) {
                        $this->line(sprintf(
                            "%d. %s - $%s",
                            $standing->position,
                            $team->name,
                            number_format($standing->prize_money)
                        ));
                    }
                }
            }
        }
        
        // Overall summary
        $this->info("\n=== OVERALL SUMMARY ===");
        $this->table(
            ['Metric', 'Total'],
            [
                ['Events', $totalEvents],
                ['Teams', $totalTeams],
                ['Players', $totalPlayers],
                ['Matches', $totalMatches],
                ['Total Prize Pool', '$' . number_format($totalPrizePool)]
            ]
        );
        
        // Data quality check
        $this->info("\n=== DATA QUALITY CHECK ===");
        
        // Check for teams without players
        $teamsWithoutPlayers = \App\Models\Team::doesntHave('players')->count();
        if ($teamsWithoutPlayers > 0) {
            $this->warn("⚠️  Teams without players: {$teamsWithoutPlayers}");
        } else {
            $this->info("✅ All teams have players");
        }
        
        // Check for matches without results
        $incompleteMatches = \App\Models\GameMatch::where('status', 'completed')
            ->where(function($query) {
                $query->where('team1_score', 0)
                    ->where('team2_score', 0);
            })->count();
            
        if ($incompleteMatches > 0) {
            $this->warn("⚠️  Completed matches without scores: {$incompleteMatches}");
        } else {
            $this->info("✅ All completed matches have scores");
        }
        
        // Check for players without roles
        $playersWithoutRoles = \App\Models\Player::whereNull('role')
            ->orWhere('role', '')->count();
            
        if ($playersWithoutRoles > 0) {
            $this->warn("⚠️  Players without roles: {$playersWithoutRoles}");
        } else {
            $this->info("✅ All players have roles assigned");
        }
        
        // Check social media links
        $teamsWithSocial = \App\Models\Team::whereNotNull('social_media')
            ->where('social_media', '!=', '[]')
            ->count();
        $playersWithSocial = \App\Models\Player::whereNotNull('social_media')
            ->where('social_media', '!=', '[]')
            ->count();
            
        $this->info("📱 Teams with social media: {$teamsWithSocial}");
        $this->info("📱 Players with social media: {$playersWithSocial}");
        
        // ELO ratings
        $avgTeamElo = \App\Models\Team::avg('rating');
        $this->info("📊 Average team ELO rating: " . round($avgTeamElo));
    }
}