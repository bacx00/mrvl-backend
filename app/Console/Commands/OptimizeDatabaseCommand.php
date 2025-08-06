<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedEloRatingService;
use App\Services\DatabaseOptimizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizeDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:optimize 
                            {--migrate : Run migrations first}
                            {--fix-data : Fix existing data issues}
                            {--update-ratings : Recalculate all ELO ratings}
                            {--clear-cache : Clear all cached data}
                            {--full : Run complete optimization}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize database structure, fix data issues, and improve performance';

    protected $eloService;
    protected $optimizationService;

    public function __construct(EnhancedEloRatingService $eloService, DatabaseOptimizationService $optimizationService)
    {
        parent::__construct();
        $this->eloService = $eloService;
        $this->optimizationService = $optimizationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Database Optimization...');
        
        try {
            // Run migrations if requested
            if ($this->option('migrate') || $this->option('full')) {
                $this->runMigrations();
            }
            
            // Fix existing data issues
            if ($this->option('fix-data') || $this->option('full')) {
                $this->fixDataIssues();
            }
            
            // Update ELO ratings
            if ($this->option('update-ratings') || $this->option('full')) {
                $this->updateEloRatings();
            }
            
            // Clear cache
            if ($this->option('clear-cache') || $this->option('full')) {
                $this->clearCache();
            }
            
            // Run optimization service
            if ($this->option('full')) {
                $this->runFullOptimization();
            }
            
            $this->info('✅ Database optimization completed successfully!');
            
        } catch (\Exception $e) {
            $this->error('❌ Database optimization failed: ' . $e->getMessage());
            Log::error('Database optimization failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * Run database migrations
     */
    protected function runMigrations()
    {
        $this->info('📊 Running database migrations...');
        
        $this->call('migrate', [
            '--force' => true
        ]);
        
        $this->info('✅ Migrations completed');
    }

    /**
     * Fix existing data issues
     */
    protected function fixDataIssues()
    {
        $this->info('🔧 Fixing existing data issues...');
        
        DB::beginTransaction();
        
        try {
            // Fix earnings data
            $this->fixEarningsData();
            
            // Fix ELO ratings
            $this->initializeEloRatings();
            
            // Fix team and player statistics
            $this->fixStatistics();
            
            // Fix missing flags and metadata
            $this->fixMetadata();
            
            DB::commit();
            $this->info('✅ Data issues fixed');
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Fix earnings data types and values
     */
    protected function fixEarningsData()
    {
        $this->info('💰 Fixing earnings data...');
        
        // Convert string earnings to proper decimal values for teams
        $teams = DB::table('teams')->whereNotNull('earnings')->get();
        $teamsUpdated = 0;
        
        foreach ($teams as $team) {
            $earningsAmount = $this->parseEarningsString($team->earnings);
            
            DB::table('teams')
                ->where('id', $team->id)
                ->update([
                    'earnings_amount' => $earningsAmount,
                    'earnings_currency' => 'USD' // Default to USD, can be updated later
                ]);
                
            $teamsUpdated++;
        }
        
        // Convert string earnings for players
        $players = DB::table('players')->whereNotNull('earnings')->get();
        $playersUpdated = 0;
        
        foreach ($players as $player) {
            $earningsAmount = $this->parseEarningsString($player->earnings);
            
            DB::table('players')
                ->where('id', $player->id)
                ->update([
                    'earnings_amount' => $earningsAmount,
                    'earnings_currency' => 'USD'
                ]);
                
            $playersUpdated++;
        }
        
        $this->info("Updated earnings for {$teamsUpdated} teams and {$playersUpdated} players");
    }

    /**
     * Initialize ELO ratings for teams and players
     */
    protected function initializeEloRatings()
    {
        $this->info('⚡ Initializing ELO ratings...');
        
        // Initialize team ELO ratings based on current ratings
        DB::statement('
            UPDATE teams 
            SET elo_rating = CASE 
                WHEN rating > 0 THEN rating 
                ELSE 1000 
            END,
            peak_elo = CASE 
                WHEN peak > 0 THEN peak
                WHEN rating > 0 THEN rating
                ELSE 1000 
            END,
            elo_changes = 0,
            last_elo_update = NOW()
            WHERE elo_rating IS NULL OR elo_rating = 0
        ');
        
        // Initialize player ELO ratings
        DB::statement('
            UPDATE players 
            SET elo_rating = CASE 
                WHEN rating > 0 THEN rating 
                ELSE 1000 
            END,
            peak_elo = CASE 
                WHEN peak_rating > 0 THEN peak_rating
                WHEN rating > 0 THEN rating
                ELSE 1000 
            END,
            elo_changes = 0,
            last_elo_update = NOW()
            WHERE elo_rating IS NULL OR elo_rating = 0
        ');
        
        $this->info('ELO ratings initialized');
    }

    /**
     * Fix team and player statistics
     */
    protected function fixStatistics()
    {
        $this->info('📈 Fixing statistics...');
        
        // Calculate matches_played for teams based on actual matches
        DB::statement('
            UPDATE teams 
            SET matches_played = (
                SELECT COUNT(*) 
                FROM matches 
                WHERE (team1_id = teams.id OR team2_id = teams.id) 
                AND status = "completed"
            )
            WHERE matches_played = 0
        ');
        
        // Calculate wins and losses for teams
        DB::statement('
            UPDATE teams 
            SET wins = (
                SELECT COUNT(*) 
                FROM matches 
                WHERE ((team1_id = teams.id AND team1_score > team2_score) 
                    OR (team2_id = teams.id AND team2_score > team1_score))
                AND status = "completed"
            ),
            losses = (
                SELECT COUNT(*) 
                FROM matches 
                WHERE ((team1_id = teams.id AND team1_score < team2_score) 
                    OR (team2_id = teams.id AND team2_score < team1_score))
                AND status = "completed"
            )
        ');
        
        // Calculate total matches for players
        DB::statement('
            UPDATE players 
            SET total_matches = (
                SELECT COUNT(DISTINCT mps.match_id)
                FROM match_player_stats mps
                WHERE mps.player_id = players.id
            )
            WHERE total_matches = 0
        ');
        
        // Calculate player elimination stats - handle database differences
        try {
            // Check if match_player_stats table exists and has data
            $statsCount = DB::table('match_player_stats')->count();
            
            if ($statsCount > 0) {
                // Use Eloquent to handle database abstraction
                $players = DB::table('players')->get();
                
                foreach ($players as $player) {
                    $stats = DB::table('match_player_stats')
                        ->where('player_id', $player->id)
                        ->selectRaw('
                            SUM(kills) as total_kills,
                            SUM(deaths) as total_deaths,
                            SUM(assists) as total_assists,
                            COUNT(DISTINCT match_id) as match_count
                        ')
                        ->first();
                    
                    if ($stats && $stats->match_count > 0) {
                        DB::table('players')
                            ->where('id', $player->id)
                            ->update([
                                'total_eliminations' => $stats->total_kills ?? 0,
                                'total_deaths' => $stats->total_deaths ?? 0,
                                'total_assists' => $stats->total_assists ?? 0,
                                'total_matches' => $stats->match_count,
                                'overall_kda' => $stats->total_deaths > 0 
                                    ? round(($stats->total_kills + $stats->total_assists) / $stats->total_deaths, 2)
                                    : 0
                            ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn('Could not update player stats: ' . $e->getMessage());
        }
        
        $this->info('Statistics updated');
    }

    /**
     * Fix missing metadata like flags and hero names
     */
    protected function fixMetadata()
    {
        $this->info('🏆 Fixing metadata...');
        
        // Update missing country flags for teams
        $teamsWithoutFlags = DB::table('teams')
            ->whereNull('flag')
            ->whereNotNull('country')
            ->get();
        
        foreach ($teamsWithoutFlags as $team) {
            $flag = $this->getCountryFlag($team->country);
            DB::table('teams')
                ->where('id', $team->id)
                ->update(['flag' => $flag]);
        }
        
        // Set default status for players if missing
        DB::table('players')
            ->whereNull('status')
            ->update(['status' => 'active']);
        
        // Set default game for teams if missing
        DB::table('teams')
            ->whereNull('game')
            ->update(['game' => 'Marvel Rivals']);
            
        // Set default platform if missing
        DB::table('teams')
            ->whereNull('platform')
            ->update(['platform' => 'PC']);
        
        $this->info('Metadata fixed');
    }

    /**
     * Update ELO ratings based on match history
     */
    protected function updateEloRatings()
    {
        $this->info('🎯 Recalculating ELO ratings from match history...');
        
        // Get all completed matches in chronological order
        $matches = DB::table('matches')
            ->where('status', 'completed')
            ->whereNotNull('team1_id')
            ->whereNotNull('team2_id')
            ->whereNotNull('team1_score')
            ->whereNotNull('team2_score')
            ->orderBy('scheduled_at', 'asc')
            ->get();
        
        $this->info("Processing {$matches->count()} matches for ELO calculation...");
        
        $processed = 0;
        $errors = 0;
        
        foreach ($matches as $match) {
            try {
                // Get event tier for proper K-factor calculation
                $eventTier = DB::table('events')
                    ->where('id', $match->event_id)
                    ->value('tier') ?? 'regular';
                
                $this->eloService->updateMatchRatings(
                    $match->id,
                    $match->team1_id,
                    $match->team2_id,
                    $match->team1_score,
                    $match->team2_score,
                    $eventTier
                );
                
                $processed++;
                
                if ($processed % 100 == 0) {
                    $this->info("Processed {$processed} matches...");
                }
                
            } catch (\Exception $e) {
                $errors++;
                Log::warning("Failed to update ELO for match {$match->id}: " . $e->getMessage());
                
                if ($errors > 10) {
                    $this->warn("Too many errors encountered, stopping ELO recalculation");
                    break;
                }
            }
        }
        
        $this->info("ELO calculation completed: {$processed} matches processed, {$errors} errors");
    }

    /**
     * Clear all cached data
     */
    protected function clearCache()
    {
        $this->info('🧹 Clearing cache...');
        
        $this->optimizationService->clearCache();
        
        $this->info('Cache cleared');
    }

    /**
     * Run full optimization
     */
    protected function runFullOptimization()
    {
        $this->info('🔥 Running full database optimization...');
        
        $result = $this->optimizationService->optimizeDatabase();
        
        if ($result['status'] === 'success') {
            $this->info('✅ Full optimization completed');
        } else {
            $this->error('❌ Optimization failed: ' . $result['message']);
        }
    }

    /**
     * Parse earnings string to decimal value
     */
    protected function parseEarningsString($earningsString)
    {
        if (empty($earningsString) || $earningsString === '$0' || $earningsString === '0') {
            return 0.00;
        }
        
        // Remove currency symbols and commas
        $cleaned = preg_replace('/[^\d.]/', '', $earningsString);
        
        // Convert to float
        $amount = floatval($cleaned);
        
        return $amount;
    }

    /**
     * Get country flag emoji
     */
    protected function getCountryFlag($country)
    {
        $flags = [
            'United States' => '🇺🇸', 'USA' => '🇺🇸', 'US' => '🇺🇸',
            'Canada' => '🇨🇦', 'CA' => '🇨🇦',
            'United Kingdom' => '🇬🇧', 'UK' => '🇬🇧', 'GB' => '🇬🇧',
            'France' => '🇫🇷', 'FR' => '🇫🇷',
            'Germany' => '🇩🇪', 'DE' => '🇩🇪',
            'Brazil' => '🇧🇷', 'BR' => '🇧🇷',
            'South Korea' => '🇰🇷', 'KR' => '🇰🇷',
            'Japan' => '🇯🇵', 'JP' => '🇯🇵',
            'China' => '🇨🇳', 'CN' => '🇨🇳',
            'Australia' => '🇦🇺', 'AU' => '🇦🇺',
        ];
        
        return $flags[$country] ?? '🌍';
    }
}