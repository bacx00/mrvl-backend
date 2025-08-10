<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class MarvelRivalsDataImporter
{
    private $teamsFile = 'liquipedia_comprehensive_57_teams_generated.json';
    private $playersFile = 'liquipedia_comprehensive_358_players_generated.json';
    
    private $importedTeams = [];
    private $importedPlayers = [];
    private $teamIdMap = [];
    
    public function __construct()
    {
        echo "Marvel Rivals Comprehensive Data Importer\n";
        echo "=========================================\n\n";
    }

    public function import()
    {
        $this->clearExistingData();
        $this->importTeams();
        $this->importPlayers();
        $this->updatePlayerTeamRelationships();
        $this->generateSummaryReport();
    }

    private function clearExistingData()
    {
        echo "🧹 Clearing existing data...\n";
        
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('player_team_history')->truncate();
            DB::table('players')->truncate();
            DB::table('teams')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            echo "✅ Existing data cleared successfully\n\n";
        } catch (Exception $e) {
            echo "❌ Error clearing data: " . $e->getMessage() . "\n\n";
        }
    }

    private function importTeams()
    {
        echo "🏆 Importing teams from {$this->teamsFile}...\n";
        
        if (!file_exists($this->teamsFile)) {
            echo "❌ Teams file not found: {$this->teamsFile}\n";
            return;
        }

        $teamsData = json_decode(file_get_contents($this->teamsFile), true);
        
        if (!$teamsData) {
            echo "❌ Invalid JSON in teams file\n";
            return;
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($teamsData as $teamData) {
            try {
                $teamRecord = $this->prepareTeamRecord($teamData);
                
                $team = DB::table('teams')->updateOrInsert(
                    ['short_name' => $teamRecord['short_name']],
                    $teamRecord
                );
                
                // Get the team ID for mapping
                $teamId = DB::table('teams')->where('short_name', $teamRecord['short_name'])->value('id');
                $this->teamIdMap[$teamData['short_name']] = $teamId;
                $this->teamIdMap[$teamData['name']] = $teamId;
                
                $this->importedTeams[] = $teamRecord['name'];
                $successCount++;
                
                if ($successCount % 10 == 0) {
                    echo "   - Imported {$successCount} teams...\n";
                }
                
            } catch (Exception $e) {
                $errorCount++;
                echo "❌ Error importing team {$teamData['name']}: " . $e->getMessage() . "\n";
            }
        }

        echo "✅ Teams import completed: {$successCount} successful, {$errorCount} errors\n\n";
    }

    private function prepareTeamRecord($teamData)
    {
        // Map the JSON data to database columns
        $record = [
            'name' => $teamData['name'],
            'short_name' => $teamData['short_name'],
            'logo' => $teamData['logo_url'] ?? null,
            'region' => $this->normalizeRegion($teamData['region'] ?? ''),
            'platform' => 'PC',
            'country' => $teamData['country'] ?? null,
            'country_code' => $teamData['country_code'] ?? null,
            'rating' => $teamData['rating'] ?? 1200,
            'rank' => $teamData['rank'] ?? 0,
            'elo_rating' => $teamData['rating'] ?? 1200,
            'peak_elo' => $teamData['rating'] ?? 1200,
            'earnings' => $teamData['total_earnings'] ?? 0,
            'founded' => $teamData['founded'] ?? null,
            'founded_date' => $this->parseDate($teamData['founded'] ?? null),
            'status' => $teamData['status'] ?? 'Active',
            'website' => $teamData['website'] ?? null,
            'player_count' => count($teamData['roster'] ?? []),
            'wins' => 0,
            'losses' => 0,
            'matches_played' => 0,
            'win_rate' => 0.0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // Handle social media
        if (isset($teamData['social_media']) && is_array($teamData['social_media'])) {
            $socialMedia = $teamData['social_media'];
            $record['social_media'] = json_encode($socialMedia);
            
            // Also store individual social media columns
            $record['twitter'] = $socialMedia['twitter'] ?? null;
            $record['instagram'] = $socialMedia['instagram'] ?? null;
            $record['youtube'] = $socialMedia['youtube'] ?? null;
            $record['twitch'] = $socialMedia['twitch'] ?? null;
            $record['facebook'] = $socialMedia['facebook'] ?? null;
            $record['discord'] = $socialMedia['discord'] ?? null;
            $record['tiktok'] = $socialMedia['tiktok'] ?? null;
        }

        return $record;
    }

    private function importPlayers()
    {
        echo "👥 Importing players from {$this->playersFile}...\n";
        
        if (!file_exists($this->playersFile)) {
            echo "❌ Players file not found: {$this->playersFile}\n";
            return;
        }

        $playersData = json_decode(file_get_contents($this->playersFile), true);
        
        if (!$playersData) {
            echo "❌ Invalid JSON in players file\n";
            return;
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($playersData as $playerData) {
            try {
                $playerRecord = $this->preparePlayerRecord($playerData);
                
                $player = DB::table('players')->updateOrInsert(
                    ['username' => $playerRecord['username']],
                    $playerRecord
                );
                
                $this->importedPlayers[] = $playerRecord['username'];
                $successCount++;
                
                if ($successCount % 50 == 0) {
                    echo "   - Imported {$successCount} players...\n";
                }
                
            } catch (Exception $e) {
                $errorCount++;
                echo "❌ Error importing player {$playerData['username']}: " . $e->getMessage() . "\n";
            }
        }

        echo "✅ Players import completed: {$successCount} successful, {$errorCount} errors\n\n";
    }

    private function preparePlayerRecord($playerData)
    {
        // Find team ID
        $teamId = null;
        if (isset($playerData['current_team']) && !empty($playerData['current_team'])) {
            $currentTeam = $playerData['current_team'];
            $teamName = $currentTeam['name'] ?? $currentTeam['short_name'] ?? null;
            if ($teamName && isset($this->teamIdMap[$teamName])) {
                $teamId = $this->teamIdMap[$teamName];
            }
        }

        $record = [
            'username' => $playerData['username'],
            'real_name' => $playerData['real_name'] ?? null,
            'name' => $playerData['real_name'] ?? $playerData['username'],
            'team_id' => $teamId,
            'role' => $this->normalizeRole($playerData['main_role'] ?? ($playerData['roles'][0] ?? 'Duelist')),
            'team_position' => 'player',
            'region' => $this->getPlayerRegion($playerData),
            'age' => $playerData['age'] ?? null,
            'birth_date' => $this->parseDate($playerData['birth_date'] ?? null),
            'country' => $playerData['country'] ?? null,
            'country_code' => $playerData['country_code'] ?? null,
            'nationality' => $playerData['nationality'] ?? null,
            'rating' => $playerData['current_rating'] ?? 1200,
            'peak_rating' => $playerData['peak_rating'] ?? ($playerData['current_rating'] ?? 1200),
            'elo_rating' => $playerData['current_rating'] ?? 1200,
            'peak_elo' => $playerData['peak_rating'] ?? ($playerData['current_rating'] ?? 1200),
            'earnings' => $playerData['total_earnings'] ?? 0,
            'total_earnings' => $playerData['total_earnings'] ?? 0,
            'main_hero' => $playerData['signature_hero'] ?? ($playerData['main_heroes'][0] ?? 'Spider-Man'),
            'biography' => $playerData['biography'] ?? null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // Handle hero preferences
        if (isset($playerData['main_heroes']) && is_array($playerData['main_heroes'])) {
            $record['alt_heroes'] = json_encode($playerData['main_heroes']);
            $record['hero_preferences'] = json_encode([
                'main' => $playerData['main_heroes'],
                'secondary' => $playerData['secondary_heroes'] ?? []
            ]);
        }

        // Handle social media
        if (isset($playerData['social_media']) && is_array($playerData['social_media'])) {
            $socialMedia = $playerData['social_media'];
            $record['social_media'] = json_encode($socialMedia);
            
            $record['twitter'] = $socialMedia['twitter'] ?? null;
            $record['instagram'] = $socialMedia['instagram'] ?? null;
            $record['youtube'] = $socialMedia['youtube'] ?? null;
            $record['twitch'] = $socialMedia['twitch'] ?? null;
            $record['facebook'] = $socialMedia['facebook'] ?? null;
            $record['discord'] = $socialMedia['discord'] ?? null;
            $record['tiktok'] = $socialMedia['tiktok'] ?? null;
        }

        // Handle career highlights and achievements
        if (isset($playerData['career_highlights'])) {
            $record['event_placements'] = json_encode($playerData['career_highlights']);
        }

        // Handle past teams
        if (isset($playerData['team_history']) && is_array($playerData['team_history'])) {
            $record['past_teams'] = json_encode($playerData['team_history']);
        }

        return $record;
    }

    private function updatePlayerTeamRelationships()
    {
        echo "🔗 Creating player-team history records...\n";
        
        $playersFile = json_decode(file_get_contents($this->playersFile), true);
        $historyCount = 0;
        
        foreach ($playersFile as $playerData) {
            try {
                $playerId = DB::table('players')->where('username', $playerData['username'])->value('id');
                
                if (!$playerId) continue;
                
                // Create current team record
                if (isset($playerData['current_team']) && !empty($playerData['current_team'])) {
                    $currentTeam = $playerData['current_team'];
                    $teamName = $currentTeam['name'] ?? $currentTeam['short_name'] ?? null;
                    
                    if ($teamName && isset($this->teamIdMap[$teamName])) {
                        $joinDate = $this->parseDate($currentTeam['join_date'] ?? null) ?: Carbon::now()->subMonths(6);
                        
                        DB::table('player_team_history')->updateOrInsert([
                            'player_id' => $playerId,
                            'to_team_id' => $this->teamIdMap[$teamName],
                            'change_date' => $joinDate,
                        ], [
                            'change_type' => 'joined',
                            'is_official' => true,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                        
                        $historyCount++;
                    }
                }
                
                // Create past team records if available
                if (isset($playerData['team_history']) && is_array($playerData['team_history'])) {
                    foreach ($playerData['team_history'] as $historyEntry) {
                        if (isset($historyEntry['team_name']) && isset($this->teamIdMap[$historyEntry['team_name']])) {
                            $joinDate = $this->parseDate($historyEntry['join_date'] ?? null) ?: Carbon::now()->subYear();
                            $leaveDate = $this->parseDate($historyEntry['leave_date'] ?? null);
                            
                            DB::table('player_team_history')->updateOrInsert([
                                'player_id' => $playerId,
                                'to_team_id' => $this->teamIdMap[$historyEntry['team_name']],
                                'change_date' => $joinDate,
                            ], [
                                'change_type' => 'joined',
                                'is_official' => true,
                                'notes' => $historyEntry['notes'] ?? null,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            
                            if ($leaveDate) {
                                DB::table('player_team_history')->insert([
                                    'player_id' => $playerId,
                                    'from_team_id' => $this->teamIdMap[$historyEntry['team_name']],
                                    'change_date' => $leaveDate,
                                    'change_type' => 'left',
                                    'is_official' => true,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now(),
                                ]);
                            }
                            
                            $historyCount++;
                        }
                    }
                }
                
            } catch (Exception $e) {
                echo "❌ Error creating history for player {$playerData['username']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✅ Created {$historyCount} team history records\n\n";
    }

    private function generateSummaryReport()
    {
        echo "📊 IMPORT SUMMARY REPORT\n";
        echo "========================\n\n";
        
        $teamCount = DB::table('teams')->count();
        $playerCount = DB::table('players')->count();
        $historyCount = DB::table('player_team_history')->count();
        $playersWithTeams = DB::table('players')->whereNotNull('team_id')->count();
        
        echo "Teams imported: {$teamCount}\n";
        echo "Players imported: {$playerCount}\n";
        echo "Players with teams: {$playersWithTeams}\n";
        echo "Team history records: {$historyCount}\n\n";
        
        echo "📈 DATABASE STATISTICS\n";
        echo "======================\n";
        
        // Team statistics
        $regionStats = DB::table('teams')
            ->select('region', DB::raw('COUNT(*) as count'))
            ->groupBy('region')
            ->orderBy('count', 'desc')
            ->get();
            
        echo "Teams by region:\n";
        foreach ($regionStats as $stat) {
            echo "  - {$stat->region}: {$stat->count} teams\n";
        }
        
        // Player statistics
        $roleStats = DB::table('players')
            ->select('role', DB::raw('COUNT(*) as count'))
            ->groupBy('role')
            ->orderBy('count', 'desc')
            ->get();
            
        echo "\nPlayers by role:\n";
        foreach ($roleStats as $stat) {
            echo "  - {$stat->role}: {$stat->count} players\n";
        }
        
        echo "\n✅ Import completed successfully!\n";
        
        // Generate a detailed log file
        $this->generateDetailedLog();
    }

    private function generateDetailedLog()
    {
        $logData = [
            'import_date' => Carbon::now()->toISOString(),
            'teams_imported' => count($this->importedTeams),
            'players_imported' => count($this->importedPlayers),
            'team_names' => $this->importedTeams,
            'player_usernames' => $this->importedPlayers,
            'database_counts' => [
                'teams' => DB::table('teams')->count(),
                'players' => DB::table('players')->count(),
                'player_team_history' => DB::table('player_team_history')->count(),
            ]
        ];
        
        file_put_contents('marvel_rivals_import_log.json', json_encode($logData, JSON_PRETTY_PRINT));
        echo "📝 Detailed log saved to: marvel_rivals_import_log.json\n";
    }

    // Helper functions
    private function normalizeRegion($region)
    {
        $regionMap = [
            'North America' => 'NA',
            'Europe' => 'EU',
            'Asia' => 'ASIA',
            'China' => 'CN',
            'Korea' => 'KR',
            'Japan' => 'JP',
            'South America' => 'SA',
            'Oceania' => 'OCE',
        ];
        
        return $regionMap[$region] ?? $region;
    }

    private function normalizeRole($role)
    {
        $roleMap = [
            'DPS' => 'Duelist',
            'Damage' => 'Duelist', 
            'Support' => 'Strategist',
            'Healer' => 'Strategist',
            'Tank' => 'Vanguard',
            'Flex' => 'Flex',
        ];
        
        return $roleMap[$role] ?? $role;
    }

    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }
        
        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    private function getPlayerRegion($playerData)
    {
        // Map country to region
        $countryToRegionMap = [
            'United States' => 'NA',
            'Canada' => 'NA', 
            'Mexico' => 'NA',
            'Brazil' => 'SA',
            'Argentina' => 'SA',
            'Chile' => 'SA',
            'United Kingdom' => 'EU',
            'France' => 'EU',
            'Germany' => 'EU',
            'Spain' => 'EU',
            'Netherlands' => 'EU',
            'Sweden' => 'EU',
            'Denmark' => 'EU',
            'Norway' => 'EU',
            'Finland' => 'EU',
            'Russia' => 'EU',
            'Poland' => 'EU',
            'Turkey' => 'EU',
            'China' => 'CN',
            'South Korea' => 'KR',
            'Korea' => 'KR',
            'Japan' => 'JP',
            'Thailand' => 'ASIA',
            'Singapore' => 'ASIA',
            'Malaysia' => 'ASIA',
            'Philippines' => 'ASIA',
            'Indonesia' => 'ASIA',
            'Vietnam' => 'ASIA',
            'India' => 'ASIA',
            'Australia' => 'OCE',
            'New Zealand' => 'OCE',
        ];
        
        $country = $playerData['country'] ?? '';
        return $countryToRegionMap[$country] ?? 'NA';
    }
}

// Execute the import
try {
    $importer = new MarvelRivalsDataImporter();
    $importer->import();
} catch (Exception $e) {
    echo "❌ Fatal error during import: " . $e->getMessage() . "\n";
    exit(1);
}