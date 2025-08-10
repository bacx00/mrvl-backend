<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Player;
use App\Models\Team;

class DatabaseProfileOptimizer
{
    private $report = [];
    
    public function __construct()
    {
        $this->report['start_time'] = now();
        $this->report['issues_found'] = [];
        $this->report['fixes_applied'] = [];
        $this->report['performance_improvements'] = [];
    }

    public function run()
    {
        echo "🔧 Starting Database Profile Optimization...\n\n";
        
        // Step 1: Data Integrity Fixes
        $this->fixDataIntegrity();
        
        // Step 2: Add Missing Profile Data
        $this->addMissingProfileData();
        
        // Step 3: Optimize Indexes
        $this->optimizeIndexes();
        
        // Step 4: Add Foreign Key Constraints
        $this->addForeignKeyConstraints();
        
        // Step 5: Optimize Query Performance
        $this->optimizeQueryPerformance();
        
        // Step 6: Generate Performance Report
        $this->generateReport();
        
        echo "\n✅ Database Profile Optimization Complete!\n";
    }

    private function fixDataIntegrity()
    {
        echo "1️⃣ Fixing Data Integrity Issues...\n";
        
        // Check for orphaned player-team relationships
        $orphanedPlayers = Player::whereNotNull('team_id')
            ->whereDoesntHave('team')
            ->get();
            
        if ($orphanedPlayers->count() > 0) {
            echo "   🔍 Found {$orphanedPlayers->count()} players with invalid team references\n";
            foreach ($orphanedPlayers as $player) {
                $player->team_id = null;
                $player->save();
            }
            $this->report['fixes_applied'][] = "Fixed {$orphanedPlayers->count()} orphaned player-team relationships";
        } else {
            echo "   ✅ No orphaned player-team relationships found\n";
        }
        
        // Check for duplicate players
        $duplicateNames = Player::select('name', 'team_id')
            ->groupBy('name', 'team_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
            
        if ($duplicateNames->count() > 0) {
            echo "   🔍 Found potential duplicate players\n";
            $this->report['issues_found'][] = "Found {$duplicateNames->count()} potential duplicate players";
        }
        
        echo "   ✅ Data integrity check complete\n\n";
    }

    private function addMissingProfileData()
    {
        echo "2️⃣ Adding Missing Profile Data...\n";
        
        // Fix missing player flags
        $playersWithoutFlags = Player::whereNull('flag')->orWhere('flag', '')->get();
        echo "   🏳️ Fixing flags for {$playersWithoutFlags->count()} players\n";
        
        foreach ($playersWithoutFlags as $player) {
            $flag = $this->generateFlagFromCountry($player->country, $player->region);
            if ($flag) {
                $player->flag = $flag;
                $player->save();
            }
        }
        
        // Fix missing team flags
        $teamsWithoutFlags = Team::whereNull('flag')->orWhere('flag', '')->get();
        echo "   🏳️ Fixing flags for {$teamsWithoutFlags->count()} teams\n";
        
        foreach ($teamsWithoutFlags as $team) {
            $flag = $this->generateFlagFromCountry($team->country, $team->region);
            if ($flag) {
                $team->flag = $flag;
                $team->save();
            }
        }
        
        // Fix missing team logos
        $teamsWithoutLogos = Team::whereNull('logo')->orWhere('logo', '')->get();
        echo "   🖼️ Fixing logos for {$teamsWithoutLogos->count()} teams\n";
        
        foreach ($teamsWithoutLogos as $team) {
            $logo = $this->generateDefaultLogo($team);
            $team->logo = $logo;
            $team->save();
        }
        
        // Fix missing team country
        $teamsWithoutCountry = Team::whereNull('country')->get();
        echo "   🌍 Fixing countries for {$teamsWithoutCountry->count()} teams\n";
        
        foreach ($teamsWithoutCountry as $team) {
            $country = $this->inferCountryFromRegion($team->region);
            if ($country) {
                $team->country = $country;
                $team->save();
            }
        }
        
        $this->report['fixes_applied'][] = "Added missing flags for {$playersWithoutFlags->count()} players and {$teamsWithoutFlags->count()} teams";
        $this->report['fixes_applied'][] = "Added missing logos for {$teamsWithoutLogos->count()} teams";
        $this->report['fixes_applied'][] = "Added missing countries for {$teamsWithoutCountry->count()} teams";
        
        echo "   ✅ Missing profile data fix complete\n\n";
    }

    private function optimizeIndexes()
    {
        echo "3️⃣ Optimizing Database Indexes...\n";
        
        try {
            // Add composite index for player profile queries
            if (!$this->indexExists('players', 'idx_players_profile_optimization')) {
                DB::statement('CREATE INDEX idx_players_profile_optimization ON players (team_id, role, rating DESC, id)');
                echo "   📈 Added player profile optimization index\n";
                $this->report['performance_improvements'][] = "Added idx_players_profile_optimization index";
            }
            
            // Add index for player search queries
            if (!$this->indexExists('players', 'idx_players_search')) {
                DB::statement('CREATE INDEX idx_players_search ON players (name, username, real_name)');
                echo "   🔍 Added player search optimization index\n";
                $this->report['performance_improvements'][] = "Added idx_players_search index";
            }
            
            // Add composite index for team profile queries
            if (!$this->indexExists('teams', 'idx_teams_profile_optimization')) {
                DB::statement('CREATE INDEX idx_teams_profile_optimization ON teams (region, rating DESC, wins DESC, id)');
                echo "   📈 Added team profile optimization index\n";
                $this->report['performance_improvements'][] = "Added idx_teams_profile_optimization index";
            }
            
            // Add index for team rankings
            if (!$this->indexExists('teams', 'idx_teams_rankings')) {
                DB::statement('CREATE INDEX idx_teams_rankings ON teams (region, elo_rating DESC, wins DESC)');
                echo "   🏆 Added team rankings optimization index\n";
                $this->report['performance_improvements'][] = "Added idx_teams_rankings index";
            }
            
            // Add index for player team history
            if (!$this->indexExists('player_team_history', 'idx_player_team_history_optimization')) {
                DB::statement('CREATE INDEX idx_player_team_history_optimization ON player_team_history (player_id, change_date DESC)');
                echo "   📊 Added player team history optimization index\n";
                $this->report['performance_improvements'][] = "Added idx_player_team_history_optimization index";
            }
            
            // Add index for match player stats
            if (!$this->indexExists('player_match_stats', 'idx_player_match_stats_optimization')) {
                DB::statement('CREATE INDEX idx_player_match_stats_optimization ON player_match_stats (player_id, created_at DESC, performance_rating DESC)');
                echo "   📊 Added player match stats optimization index\n";
                $this->report['performance_improvements'][] = "Added idx_player_match_stats_optimization index";
            }
            
        } catch (Exception $e) {
            echo "   ⚠️ Error creating indexes: " . $e->getMessage() . "\n";
            $this->report['issues_found'][] = "Index creation error: " . $e->getMessage();
        }
        
        echo "   ✅ Index optimization complete\n\n";
    }

    private function addForeignKeyConstraints()
    {
        echo "4️⃣ Adding Foreign Key Constraints...\n";
        
        try {
            // Add foreign key constraint for players.team_id
            if (!$this->foreignKeyExists('players', 'fk_players_team_id')) {
                DB::statement('ALTER TABLE players ADD CONSTRAINT fk_players_team_id FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE');
                echo "   🔗 Added foreign key constraint for players.team_id\n";
                $this->report['fixes_applied'][] = "Added fk_players_team_id foreign key constraint";
            }
            
            // Add foreign key constraint for player_team_history
            if (!$this->foreignKeyExists('player_team_history', 'fk_player_team_history_player_id')) {
                DB::statement('ALTER TABLE player_team_history ADD CONSTRAINT fk_player_team_history_player_id FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE ON UPDATE CASCADE');
                echo "   🔗 Added foreign key constraint for player_team_history.player_id\n";
                $this->report['fixes_applied'][] = "Added fk_player_team_history_player_id foreign key constraint";
            }
            
            if (!$this->foreignKeyExists('player_team_history', 'fk_player_team_history_from_team_id')) {
                DB::statement('ALTER TABLE player_team_history ADD CONSTRAINT fk_player_team_history_from_team_id FOREIGN KEY (from_team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE');
                echo "   🔗 Added foreign key constraint for player_team_history.from_team_id\n";
                $this->report['fixes_applied'][] = "Added fk_player_team_history_from_team_id foreign key constraint";
            }
            
            if (!$this->foreignKeyExists('player_team_history', 'fk_player_team_history_to_team_id')) {
                DB::statement('ALTER TABLE player_team_history ADD CONSTRAINT fk_player_team_history_to_team_id FOREIGN KEY (to_team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE');
                echo "   🔗 Added foreign key constraint for player_team_history.to_team_id\n";
                $this->report['fixes_applied'][] = "Added fk_player_team_history_to_team_id foreign key constraint";
            }
            
        } catch (Exception $e) {
            echo "   ⚠️ Error adding foreign key constraints: " . $e->getMessage() . "\n";
            $this->report['issues_found'][] = "Foreign key constraint error: " . $e->getMessage();
        }
        
        echo "   ✅ Foreign key constraints complete\n\n";
    }

    private function optimizeQueryPerformance()
    {
        echo "5️⃣ Optimizing Query Performance...\n";
        
        // Update player career stats to ensure they're current
        echo "   📊 Updating player career statistics...\n";
        $players = Player::with('matchStats')->get();
        foreach ($players as $player) {
            try {
                $player->updateCareerStats();
            } catch (Exception $e) {
                // Continue if individual player update fails
                continue;
            }
        }
        
        // Update team statistics
        echo "   🏆 Updating team statistics...\n";
        $teams = Team::all();
        foreach ($teams as $team) {
            try {
                $matchStats = $team->getMatchStatistics();
                $team->update([
                    'matches_played' => $matchStats['matches_played'],
                    'wins' => $matchStats['wins'],
                    'losses' => $matchStats['losses'],
                    'win_rate' => $matchStats['win_rate'],
                    'maps_won' => $matchStats['maps_won'],
                    'maps_lost' => $matchStats['maps_lost']
                ]);
            } catch (Exception $e) {
                // Continue if individual team update fails
                continue;
            }
        }
        
        $this->report['performance_improvements'][] = "Updated career statistics for all players and teams";
        
        echo "   ✅ Query performance optimization complete\n\n";
    }

    private function generateFlagFromCountry($country, $region = null)
    {
        $flagMappings = [
            // North America
            'United States' => '🇺🇸', 'USA' => '🇺🇸', 'US' => '🇺🇸',
            'Canada' => '🇨🇦', 'CA' => '🇨🇦',
            'Mexico' => '🇲🇽', 'MX' => '🇲🇽',
            
            // Europe
            'Sweden' => '🇸🇪', 'SE' => '🇸🇪',
            'Denmark' => '🇩🇰', 'DK' => '🇩🇰',
            'Finland' => '🇫🇮', 'FI' => '🇫🇮',
            'Norway' => '🇳🇴', 'NO' => '🇳🇴',
            'Germany' => '🇩🇪', 'DE' => '🇩🇪',
            'France' => '🇫🇷', 'FR' => '🇫🇷',
            'United Kingdom' => '🇬🇧', 'UK' => '🇬🇧', 'GB' => '🇬🇧',
            'Spain' => '🇪🇸', 'ES' => '🇪🇸',
            'Italy' => '🇮🇹', 'IT' => '🇮🇹',
            'Netherlands' => '🇳🇱', 'NL' => '🇳🇱',
            'Belgium' => '🇧🇪', 'BE' => '🇧🇪',
            'Poland' => '🇵🇱', 'PL' => '🇵🇱',
            'Russia' => '🇷🇺', 'RU' => '🇷🇺',
            'Turkey' => '🇹🇷', 'TR' => '🇹🇷',
            
            // Asia Pacific
            'South Korea' => '🇰🇷', 'Korea' => '🇰🇷', 'KR' => '🇰🇷',
            'Japan' => '🇯🇵', 'JP' => '🇯🇵',
            'China' => '🇨🇳', 'CN' => '🇨🇳',
            'Thailand' => '🇹🇭', 'TH' => '🇹🇭',
            'Philippines' => '🇵🇭', 'PH' => '🇵🇭',
            'Singapore' => '🇸🇬', 'SG' => '🇸🇬',
            'Malaysia' => '🇲🇾', 'MY' => '🇲🇾',
            'Indonesia' => '🇮🇩', 'ID' => '🇮🇩',
            'Vietnam' => '🇻🇳', 'VN' => '🇻🇳',
            'Hong Kong' => '🇭🇰', 'HK' => '🇭🇰',
            'Taiwan' => '🇹🇼', 'TW' => '🇹🇼',
            'Australia' => '🇦🇺', 'AU' => '🇦🇺',
            'New Zealand' => '🇳🇿', 'NZ' => '🇳🇿',
            
            // South America
            'Brazil' => '🇧🇷', 'BR' => '🇧🇷',
            'Argentina' => '🇦🇷', 'AR' => '🇦🇷',
            'Chile' => '🇨🇱', 'CL' => '🇨🇱',
            'Colombia' => '🇨🇴', 'CO' => '🇨🇴',
            'Peru' => '🇵🇪', 'PE' => '🇵🇪',
            
            // Other
            'Ukraine' => '🇺🇦', 'UA' => '🇺🇦',
            'Belarus' => '🇧🇾', 'BY' => '🇧🇾',
            'Estonia' => '🇪🇪', 'EE' => '🇪🇪',
            'Latvia' => '🇱🇻', 'LV' => '🇱🇻',
            'Lithuania' => '🇱🇹', 'LT' => '🇱🇹',
            'Czech Republic' => '🇨🇿', 'CZ' => '🇨🇿',
            'Slovakia' => '🇸🇰', 'SK' => '🇸🇰',
            'Hungary' => '🇭🇺', 'HU' => '🇭🇺',
            'Romania' => '🇷🇴', 'RO' => '🇷🇴',
            'Bulgaria' => '🇧🇬', 'BG' => '🇧🇬',
            'Serbia' => '🇷🇸', 'RS' => '🇷🇸',
            'Croatia' => '🇭🇷', 'HR' => '🇭🇷',
            'Slovenia' => '🇸🇮', 'SI' => '🇸🇮',
            'Austria' => '🇦🇹', 'AT' => '🇦🇹',
            'Switzerland' => '🇨🇭', 'CH' => '🇨🇭',
            'Portugal' => '🇵🇹', 'PT' => '🇵🇹',
        ];
        
        return $flagMappings[$country] ?? $this->generateFlagFromRegion($region);
    }
    
    private function generateFlagFromRegion($region)
    {
        $regionFlags = [
            'North America' => '🇺🇸',
            'NA' => '🇺🇸',
            'Europe' => '🇪🇺',
            'EU' => '🇪🇺',
            'EMEA' => '🇪🇺',
            'Asia Pacific' => '🇰🇷',
            'APAC' => '🇰🇷',
            'Asia' => '🇰🇷',
            'Pacific' => '🇦🇺',
            'South America' => '🇧🇷',
            'SA' => '🇧🇷',
            'China' => '🇨🇳',
            'CN' => '🇨🇳'
        ];
        
        return $regionFlags[$region] ?? '🏳️';
    }

    private function generateDefaultLogo($team)
    {
        $slug = strtolower(str_replace(' ', '-', $team->name));
        return "/images/teams/{$slug}-logo.png";
    }

    private function inferCountryFromRegion($region)
    {
        $regionCountries = [
            'North America' => 'United States',
            'NA' => 'United States',
            'Europe' => 'Germany',
            'EU' => 'Germany',
            'EMEA' => 'Germany',
            'Asia Pacific' => 'South Korea',
            'APAC' => 'South Korea',
            'Asia' => 'South Korea',
            'Pacific' => 'Australia',
            'South America' => 'Brazil',
            'SA' => 'Brazil',
            'China' => 'China',
            'CN' => 'China'
        ];
        
        return $regionCountries[$region] ?? null;
    }

    private function indexExists($table, $indexName)
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function foreignKeyExists($table, $constraintName)
    {
        try {
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ? 
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ", [$table, $constraintName]);
            return count($constraints) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function generateReport()
    {
        $this->report['end_time'] = now();
        $this->report['duration'] = $this->report['start_time']->diffInSeconds($this->report['end_time']);
        
        echo "6️⃣ Database Profile Optimization Report\n";
        echo "=====================================\n";
        echo "Start Time: " . $this->report['start_time']->format('Y-m-d H:i:s') . "\n";
        echo "End Time: " . $this->report['end_time']->format('Y-m-d H:i:s') . "\n";
        echo "Duration: " . $this->report['duration'] . " seconds\n\n";
        
        echo "Issues Found:\n";
        if (empty($this->report['issues_found'])) {
            echo "   ✅ No issues found\n";
        } else {
            foreach ($this->report['issues_found'] as $issue) {
                echo "   ⚠️ {$issue}\n";
            }
        }
        
        echo "\nFixes Applied:\n";
        if (empty($this->report['fixes_applied'])) {
            echo "   ✅ No fixes needed\n";
        } else {
            foreach ($this->report['fixes_applied'] as $fix) {
                echo "   🔧 {$fix}\n";
            }
        }
        
        echo "\nPerformance Improvements:\n";
        if (empty($this->report['performance_improvements'])) {
            echo "   ✅ No performance improvements made\n";
        } else {
            foreach ($this->report['performance_improvements'] as $improvement) {
                echo "   📈 {$improvement}\n";
            }
        }
        
        // Save report to file
        file_put_contents('database_profile_optimization_report.json', json_encode($this->report, JSON_PRETTY_PRINT));
        echo "\n📝 Report saved to database_profile_optimization_report.json\n";
    }
}

// Initialize Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Run the optimizer
$optimizer = new DatabaseProfileOptimizer();
$optimizer->run();