<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class MarvelRivalsComprehensiveDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Marvel Rivals Comprehensive Data Seeding...');
        
        // Check if data files exist
        $teamsFile = base_path('liquipedia_comprehensive_57_teams_generated.json');
        $playersFile = base_path('liquipedia_comprehensive_358_players_generated.json');
        
        if (!file_exists($teamsFile)) {
            $this->command->error("❌ Teams file not found: {$teamsFile}");
            return;
        }
        
        if (!file_exists($playersFile)) {
            $this->command->error("❌ Players file not found: {$playersFile}");
            return;
        }
        
        $this->seedTeams($teamsFile);
        $this->seedPlayers($playersFile);
        $this->createPlayerTeamHistories($playersFile);
        
        $this->command->info('✅ Marvel Rivals data seeding completed successfully!');
    }
    
    private function seedTeams($teamsFile)
    {
        $this->command->info('🏆 Seeding teams...');
        
        $teamsData = json_decode(file_get_contents($teamsFile), true);
        $teams = [];
        
        foreach ($teamsData as $teamData) {
            $teams[] = [
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
                'social_media' => isset($teamData['social_media']) ? json_encode($teamData['social_media']) : null,
                'twitter' => $teamData['social_media']['twitter'] ?? null,
                'instagram' => $teamData['social_media']['instagram'] ?? null,
                'youtube' => $teamData['social_media']['youtube'] ?? null,
                'twitch' => $teamData['social_media']['twitch'] ?? null,
                'facebook' => $teamData['social_media']['facebook'] ?? null,
                'discord' => $teamData['social_media']['discord'] ?? null,
                'tiktok' => $teamData['social_media']['tiktok'] ?? null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
        
        // Clear existing teams (handle foreign key constraints)
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('player_team_history')->truncate();
            DB::table('players')->truncate();
            DB::table('teams')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Exception $e) {
            $this->command->warn("Could not truncate tables (likely due to constraints): " . $e->getMessage());
        }
        
        // Insert teams in batches
        collect($teams)->chunk(10)->each(function ($chunk) {
            DB::table('teams')->insert($chunk->toArray());
        });
        
        $this->command->info("✅ Seeded " . count($teams) . " teams");
    }
    
    private function seedPlayers($playersFile)
    {
        $this->command->info('👥 Seeding players...');
        
        $playersData = json_decode(file_get_contents($playersFile), true);
        $players = [];
        
        // Create team mapping for relationships
        $teamIdMap = DB::table('teams')->pluck('id', 'name')->toArray() + 
                     DB::table('teams')->pluck('id', 'short_name')->toArray();
        
        foreach ($playersData as $playerData) {
            // Find team ID
            $teamId = null;
            if (isset($playerData['current_team']) && !empty($playerData['current_team'])) {
                $currentTeam = $playerData['current_team'];
                $teamName = $currentTeam['name'] ?? $currentTeam['short_name'] ?? null;
                if ($teamName && isset($teamIdMap[$teamName])) {
                    $teamId = $teamIdMap[$teamName];
                }
            }
            
            $players[] = [
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
                'alt_heroes' => isset($playerData['main_heroes']) ? json_encode($playerData['main_heroes']) : null,
                'hero_preferences' => isset($playerData['main_heroes']) ? json_encode([
                    'main' => $playerData['main_heroes'],
                    'secondary' => $playerData['secondary_heroes'] ?? []
                ]) : null,
                'biography' => $playerData['biography'] ?? null,
                'social_media' => isset($playerData['social_media']) ? json_encode($playerData['social_media']) : null,
                'twitter' => $playerData['social_media']['twitter'] ?? null,
                'instagram' => $playerData['social_media']['instagram'] ?? null,
                'youtube' => $playerData['social_media']['youtube'] ?? null,
                'twitch' => $playerData['social_media']['twitch'] ?? null,
                'facebook' => $playerData['social_media']['facebook'] ?? null,
                'discord' => $playerData['social_media']['discord'] ?? null,
                'tiktok' => $playerData['social_media']['tiktok'] ?? null,
                'event_placements' => isset($playerData['career_highlights']) ? json_encode($playerData['career_highlights']) : null,
                'past_teams' => isset($playerData['team_history']) ? json_encode($playerData['team_history']) : null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
        
        // Players are already cleared above with teams
        
        // Insert players in batches
        collect($players)->chunk(50)->each(function ($chunk) {
            DB::table('players')->insert($chunk->toArray());
        });
        
        $this->command->info("✅ Seeded " . count($players) . " players");
    }
    
    private function createPlayerTeamHistories($playersFile)
    {
        $this->command->info('🔗 Creating player-team histories...');
        
        $playersData = json_decode(file_get_contents($playersFile), true);
        $histories = [];
        
        // Get ID mappings
        $playerIdMap = DB::table('players')->pluck('id', 'username')->toArray();
        $teamIdMap = DB::table('teams')->pluck('id', 'name')->toArray() + 
                     DB::table('teams')->pluck('id', 'short_name')->toArray();
        
        foreach ($playersData as $playerData) {
            $playerId = $playerIdMap[$playerData['username']] ?? null;
            if (!$playerId) continue;
            
            // Create current team history record
            if (isset($playerData['current_team']) && !empty($playerData['current_team'])) {
                $currentTeam = $playerData['current_team'];
                $teamName = $currentTeam['name'] ?? $currentTeam['short_name'] ?? null;
                
                if ($teamName && isset($teamIdMap[$teamName])) {
                    $joinDate = $this->parseDate($currentTeam['join_date'] ?? null) ?: Carbon::now()->subMonths(6);
                    
                    $histories[] = [
                        'player_id' => $playerId,
                        'to_team_id' => $teamIdMap[$teamName],
                        'change_date' => $joinDate,
                        'change_type' => 'joined',
                        'is_official' => true,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }
            
            // Create past team history records if available
            if (isset($playerData['team_history']) && is_array($playerData['team_history'])) {
                foreach ($playerData['team_history'] as $historyEntry) {
                    if (isset($historyEntry['team_name']) && isset($teamIdMap[$historyEntry['team_name']])) {
                        $joinDate = $this->parseDate($historyEntry['join_date'] ?? null) ?: Carbon::now()->subYear();
                        
                        $histories[] = [
                            'player_id' => $playerId,
                            'to_team_id' => $teamIdMap[$historyEntry['team_name']],
                            'change_date' => $joinDate,
                            'change_type' => 'joined',
                            'is_official' => true,
                            'notes' => $historyEntry['notes'] ?? null,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                        
                        // Add leave record if available
                        $leaveDate = $this->parseDate($historyEntry['leave_date'] ?? null);
                        if ($leaveDate) {
                            $histories[] = [
                                'player_id' => $playerId,
                                'from_team_id' => $teamIdMap[$historyEntry['team_name']],
                                'change_date' => $leaveDate,
                                'change_type' => 'left',
                                'is_official' => true,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ];
                        }
                    }
                }
            }
        }
        
        // Histories are already cleared above with teams
        
        // Insert histories in batches
        if (!empty($histories)) {
            collect($histories)->chunk(100)->each(function ($chunk) {
                DB::table('player_team_history')->insert($chunk->toArray());
            });
        }
        
        $this->command->info("✅ Created " . count($histories) . " team history records");
    }
    
    // Helper methods
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
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
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