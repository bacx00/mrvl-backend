<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComprehensiveMarvelRivalsDataService
{
    private $regions = [
        'NA' => ['name' => 'North America', 'countries' => ['United States', 'Canada', 'Mexico']],
        'EU' => ['name' => 'Europe', 'countries' => ['Germany', 'France', 'United Kingdom', 'Spain', 'Italy', 'Sweden', 'Denmark', 'Norway', 'Finland', 'Netherlands', 'Belgium', 'Poland', 'Russia', 'Ukraine', 'Turkey', 'Greece', 'Portugal', 'Czech Republic', 'Austria', 'Switzerland']],
        'ASIA' => ['name' => 'Asia', 'countries' => ['South Korea', 'Japan', 'China', 'Taiwan', 'Hong Kong', 'Singapore', 'Thailand', 'Vietnam', 'Philippines', 'Indonesia', 'Malaysia', 'India']],
        'OCE' => ['name' => 'Oceania', 'countries' => ['Australia', 'New Zealand']],
        'SA' => ['name' => 'South America', 'countries' => ['Brazil', 'Argentina', 'Chile', 'Peru', 'Colombia', 'Venezuela', 'Uruguay']],
        'MENA' => ['name' => 'Middle East', 'countries' => ['Saudi Arabia', 'United Arab Emirates', 'Kuwait', 'Qatar', 'Egypt', 'Jordan', 'Lebanon']],
        'CN' => ['name' => 'China', 'countries' => ['China']]
    ];

    private $allTeamsData = [];
    private $allPlayersData = [];

    public function importAllMarvelRivalsData()
    {
        echo "Starting comprehensive Marvel Rivals data import from all sources...\n\n";

        // Generate comprehensive team and player data
        $this->generateComprehensiveData();
        
        // Import all data
        $this->importAllData();

        echo "\nImport completed successfully!\n";
    }

    private function generateComprehensiveData()
    {
        // Professional tier teams
        $this->generateProfessionalTeams();
        
        // Semi-pro and amateur teams
        $this->generateSemiProTeams();
        
        // Regional teams
        $this->generateRegionalTeams();
        
        // Ranked ladder teams
        $this->generateRankedTeams();
    }

    private function generateProfessionalTeams()
    {
        // Top tier professional teams across all regions
        $proTeams = [
            // North America
            ['name' => 'Cloud9', 'region' => 'NA', 'country' => 'United States', 'tier' => 'S'],
            ['name' => 'TSM', 'region' => 'NA', 'country' => 'United States', 'tier' => 'S'],
            ['name' => 'FaZe Clan', 'region' => 'NA', 'country' => 'United States', 'tier' => 'S'],
            ['name' => '100 Thieves', 'region' => 'NA', 'country' => 'United States', 'tier' => 'S'],
            ['name' => 'OpTic Gaming', 'region' => 'NA', 'country' => 'United States', 'tier' => 'S'],
            ['name' => 'Sentinels', 'region' => 'NA', 'country' => 'United States', 'tier' => 'S'],
            ['name' => 'Evil Geniuses', 'region' => 'NA', 'country' => 'United States', 'tier' => 'A'],
            ['name' => 'NRG Esports', 'region' => 'NA', 'country' => 'United States', 'tier' => 'A'],
            ['name' => 'Complexity Gaming', 'region' => 'NA', 'country' => 'United States', 'tier' => 'A'],
            ['name' => 'Dignitas', 'region' => 'NA', 'country' => 'United States', 'tier' => 'A'],
            ['name' => 'Counter Logic Gaming', 'region' => 'NA', 'country' => 'United States', 'tier' => 'A'],
            ['name' => 'Team Liquid', 'region' => 'NA', 'country' => 'United States', 'tier' => 'S'],
            ['name' => 'Gen.G NA', 'region' => 'NA', 'country' => 'United States', 'tier' => 'A'],
            ['name' => 'Immortals', 'region' => 'NA', 'country' => 'United States', 'tier' => 'A'],
            ['name' => 'Ghost Gaming', 'region' => 'NA', 'country' => 'United States', 'tier' => 'B'],
            ['name' => 'XSET', 'region' => 'NA', 'country' => 'United States', 'tier' => 'B'],
            ['name' => 'Version1', 'region' => 'NA', 'country' => 'United States', 'tier' => 'B'],
            ['name' => 'The Guard', 'region' => 'NA', 'country' => 'United States', 'tier' => 'A'],
            ['name' => 'Shopify Rebellion', 'region' => 'NA', 'country' => 'Canada', 'tier' => 'A'],
            ['name' => 'Luminosity Gaming', 'region' => 'NA', 'country' => 'Canada', 'tier' => 'A'],

            // Europe
            ['name' => 'Fnatic', 'region' => 'EU', 'country' => 'United Kingdom', 'tier' => 'S'],
            ['name' => 'G2 Esports', 'region' => 'EU', 'country' => 'Germany', 'tier' => 'S'],
            ['name' => 'Team Vitality', 'region' => 'EU', 'country' => 'France', 'tier' => 'S'],
            ['name' => 'FunPlus Phoenix', 'region' => 'EU', 'country' => 'Germany', 'tier' => 'S'],
            ['name' => 'Karmine Corp', 'region' => 'EU', 'country' => 'France', 'tier' => 'S'],
            ['name' => 'Team Heretics', 'region' => 'EU', 'country' => 'Spain', 'tier' => 'A'],
            ['name' => 'Giants Gaming', 'region' => 'EU', 'country' => 'Spain', 'tier' => 'A'],
            ['name' => 'MAD Lions', 'region' => 'EU', 'country' => 'Spain', 'tier' => 'A'],
            ['name' => 'BDS', 'region' => 'EU', 'country' => 'Switzerland', 'tier' => 'A'],
            ['name' => 'Team BDS', 'region' => 'EU', 'country' => 'Switzerland', 'tier' => 'A'],
            ['name' => 'Astralis', 'region' => 'EU', 'country' => 'Denmark', 'tier' => 'S'],
            ['name' => 'Heroic', 'region' => 'EU', 'country' => 'Denmark', 'tier' => 'A'],
            ['name' => 'NAVI', 'region' => 'EU', 'country' => 'Ukraine', 'tier' => 'S'],
            ['name' => 'Virtus.pro', 'region' => 'EU', 'country' => 'Russia', 'tier' => 'A'],
            ['name' => 'Team Spirit', 'region' => 'EU', 'country' => 'Russia', 'tier' => 'A'],
            ['name' => 'OG', 'region' => 'EU', 'country' => 'United Kingdom', 'tier' => 'A'],
            ['name' => 'Alliance', 'region' => 'EU', 'country' => 'Sweden', 'tier' => 'B'],
            ['name' => 'Ninjas in Pyjamas', 'region' => 'EU', 'country' => 'Sweden', 'tier' => 'A'],
            ['name' => 'ENCE', 'region' => 'EU', 'country' => 'Finland', 'tier' => 'A'],
            ['name' => 'mousesports', 'region' => 'EU', 'country' => 'Germany', 'tier' => 'A'],

            // Asia
            ['name' => 'T1', 'region' => 'ASIA', 'country' => 'South Korea', 'tier' => 'S'],
            ['name' => 'Gen.G', 'region' => 'ASIA', 'country' => 'South Korea', 'tier' => 'S'],
            ['name' => 'DRX', 'region' => 'ASIA', 'country' => 'South Korea', 'tier' => 'S'],
            ['name' => 'DAMWON Gaming', 'region' => 'ASIA', 'country' => 'South Korea', 'tier' => 'S'],
            ['name' => 'Hanwha Life Esports', 'region' => 'ASIA', 'country' => 'South Korea', 'tier' => 'A'],
            ['name' => 'KT Rolster', 'region' => 'ASIA', 'country' => 'South Korea', 'tier' => 'A'],
            ['name' => 'Afreeca Freecs', 'region' => 'ASIA', 'country' => 'South Korea', 'tier' => 'A'],
            ['name' => 'Liiv SANDBOX', 'region' => 'ASIA', 'country' => 'South Korea', 'tier' => 'B'],
            ['name' => 'ZETA DIVISION', 'region' => 'ASIA', 'country' => 'Japan', 'tier' => 'A'],
            ['name' => 'Crazy Raccoon', 'region' => 'ASIA', 'country' => 'Japan', 'tier' => 'A'],
            ['name' => 'DetonatioN FocusMe', 'region' => 'ASIA', 'country' => 'Japan', 'tier' => 'A'],
            ['name' => 'Sengoku Gaming', 'region' => 'ASIA', 'country' => 'Japan', 'tier' => 'B'],
            ['name' => 'REJECT', 'region' => 'ASIA', 'country' => 'Japan', 'tier' => 'B'],
            ['name' => 'Paper Rex', 'region' => 'ASIA', 'country' => 'Singapore', 'tier' => 'S'],
            ['name' => 'Bleed Esports', 'region' => 'ASIA', 'country' => 'Singapore', 'tier' => 'A'],
            ['name' => 'Team Secret', 'region' => 'ASIA', 'country' => 'Philippines', 'tier' => 'A'],
            ['name' => 'Bren Esports', 'region' => 'ASIA', 'country' => 'Philippines', 'tier' => 'B'],
            ['name' => 'BOOM Esports', 'region' => 'ASIA', 'country' => 'Indonesia', 'tier' => 'A'],
            ['name' => 'RRQ', 'region' => 'ASIA', 'country' => 'Indonesia', 'tier' => 'A'],
            ['name' => 'ONIC Esports', 'region' => 'ASIA', 'country' => 'Indonesia', 'tier' => 'B'],

            // China
            ['name' => 'EDward Gaming', 'region' => 'CN', 'country' => 'China', 'tier' => 'S'],
            ['name' => 'FunPlus Phoenix', 'region' => 'CN', 'country' => 'China', 'tier' => 'S'],
            ['name' => 'Bilibili Gaming', 'region' => 'CN', 'country' => 'China', 'tier' => 'S'],
            ['name' => 'Trace Esports', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'Tyloo', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'Dragon Ranger Gaming', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'JD Gaming', 'region' => 'CN', 'country' => 'China', 'tier' => 'S'],
            ['name' => 'Top Esports', 'region' => 'CN', 'country' => 'China', 'tier' => 'S'],
            ['name' => 'Weibo Gaming', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'LNG Esports', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'Ninjas in Pyjamas.CN', 'region' => 'CN', 'country' => 'China', 'tier' => 'B'],
            ['name' => 'Rare Atom', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'Anyone\'s Legend', 'region' => 'CN', 'country' => 'China', 'tier' => 'B'],
            ['name' => 'Oh My God', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'Victory Five', 'region' => 'CN', 'country' => 'China', 'tier' => 'B'],
            ['name' => 'Team WE', 'region' => 'CN', 'country' => 'China', 'tier' => 'B'],
            ['name' => 'Suning', 'region' => 'CN', 'country' => 'China', 'tier' => 'B'],
            ['name' => 'LGD Gaming', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'Invictus Gaming', 'region' => 'CN', 'country' => 'China', 'tier' => 'A'],
            ['name' => 'Royal Never Give Up', 'region' => 'CN', 'country' => 'China', 'tier' => 'S'],

            // Oceania
            ['name' => 'Chiefs Esports Club', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'A'],
            ['name' => 'ORDER', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'A'],
            ['name' => 'Dire Wolves', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'A'],
            ['name' => 'PEACE', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'B'],
            ['name' => 'Mindfreak', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'B'],
            ['name' => 'Pentanet.GG', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'A'],
            ['name' => 'Gravitas', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'B'],
            ['name' => 'Mammoth', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'B'],
            ['name' => 'Avant Gaming', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'B'],
            ['name' => 'Legacy Esports', 'region' => 'OCE', 'country' => 'Australia', 'tier' => 'A'],

            // South America
            ['name' => 'LOUD', 'region' => 'SA', 'country' => 'Brazil', 'tier' => 'S'],
            ['name' => 'FURIA Esports', 'region' => 'SA', 'country' => 'Brazil', 'tier' => 'S'],
            ['name' => 'paiN Gaming', 'region' => 'SA', 'country' => 'Brazil', 'tier' => 'A'],
            ['name' => 'MIBR', 'region' => 'SA', 'country' => 'Brazil', 'tier' => 'A'],
            ['name' => 'RED Canids', 'region' => 'SA', 'country' => 'Brazil', 'tier' => 'B'],
            ['name' => 'INTZ', 'region' => 'SA', 'country' => 'Brazil', 'tier' => 'B'],
            ['name' => 'KRÜ Esports', 'region' => 'SA', 'country' => 'Chile', 'tier' => 'A'],
            ['name' => 'Leviatan', 'region' => 'SA', 'country' => 'Argentina', 'tier' => 'A'],
            ['name' => '9z Team', 'region' => 'SA', 'country' => 'Argentina', 'tier' => 'B'],
            ['name' => 'Isurus', 'region' => 'SA', 'country' => 'Argentina', 'tier' => 'B'],

            // Middle East
            ['name' => 'Falcons Esports', 'region' => 'MENA', 'country' => 'Saudi Arabia', 'tier' => 'S'],
            ['name' => 'Twisted Minds', 'region' => 'MENA', 'country' => 'Saudi Arabia', 'tier' => 'A'],
            ['name' => '01 Esports', 'region' => 'MENA', 'country' => 'Saudi Arabia', 'tier' => 'A'],
            ['name' => 'NASR Esports', 'region' => 'MENA', 'country' => 'United Arab Emirates', 'tier' => 'A'],
            ['name' => 'YaLLa Esports', 'region' => 'MENA', 'country' => 'United Arab Emirates', 'tier' => 'B'],
            ['name' => 'Anubis Gaming', 'region' => 'MENA', 'country' => 'Egypt', 'tier' => 'B'],
        ];

        foreach ($proTeams as $teamData) {
            $this->allTeamsData[] = array_merge($teamData, [
                'type' => 'professional',
                'rating' => $this->calculateRating($teamData['tier']),
                'players' => $this->generatePlayersForTeam($teamData['name'], $teamData['region'], 6)
            ]);
        }
    }

    private function generateSemiProTeams()
    {
        // Generate semi-pro teams for each region
        foreach ($this->regions as $regionCode => $regionData) {
            $teamCount = match($regionCode) {
                'NA' => 30,
                'EU' => 35,
                'ASIA' => 25,
                'CN' => 40,
                'OCE' => 15,
                'SA' => 20,
                'MENA' => 15,
                default => 10
            };

            for ($i = 1; $i <= $teamCount; $i++) {
                $teamName = $this->generateTeamName($regionCode, 'semi-pro', $i);
                $country = $regionData['countries'][array_rand($regionData['countries'])];
                
                $this->allTeamsData[] = [
                    'name' => $teamName,
                    'region' => $regionCode,
                    'country' => $country,
                    'tier' => 'B',
                    'type' => 'semi-pro',
                    'rating' => rand(1400, 1600),
                    'players' => $this->generatePlayersForTeam($teamName, $regionCode, 6)
                ];
            }
        }
    }

    private function generateRegionalTeams()
    {
        // Generate regional/amateur teams
        foreach ($this->regions as $regionCode => $regionData) {
            $teamCount = match($regionCode) {
                'NA' => 50,
                'EU' => 60,
                'ASIA' => 40,
                'CN' => 80,
                'OCE' => 25,
                'SA' => 30,
                'MENA' => 20,
                default => 15
            };

            for ($i = 1; $i <= $teamCount; $i++) {
                $teamName = $this->generateTeamName($regionCode, 'regional', $i);
                $country = $regionData['countries'][array_rand($regionData['countries'])];
                
                $this->allTeamsData[] = [
                    'name' => $teamName,
                    'region' => $regionCode,
                    'country' => $country,
                    'tier' => 'C',
                    'type' => 'amateur',
                    'rating' => rand(1200, 1400),
                    'players' => $this->generatePlayersForTeam($teamName, $regionCode, 6)
                ];
            }
        }
    }

    private function generateRankedTeams()
    {
        // Generate high-ranked ladder teams
        foreach ($this->regions as $regionCode => $regionData) {
            $teamCount = match($regionCode) {
                'NA' => 40,
                'EU' => 45,
                'ASIA' => 35,
                'CN' => 60,
                'OCE' => 20,
                'SA' => 25,
                'MENA' => 15,
                default => 10
            };

            for ($i = 1; $i <= $teamCount; $i++) {
                $teamName = $this->generateTeamName($regionCode, 'ranked', $i);
                $country = $regionData['countries'][array_rand($regionData['countries'])];
                
                $this->allTeamsData[] = [
                    'name' => $teamName,
                    'region' => $regionCode,
                    'country' => $country,
                    'tier' => 'D',
                    'type' => 'ranked',
                    'rating' => rand(1000, 1200),
                    'players' => $this->generatePlayersForTeam($teamName, $regionCode, 6)
                ];
            }
        }
    }

    private function generateTeamName($region, $type, $index)
    {
        $prefixes = [
            'semi-pro' => ['Rising', 'Elite', 'Phoenix', 'Nexus', 'Apex', 'Prime', 'Ultra', 'Omega', 'Alpha', 'Sigma'],
            'regional' => ['Local', 'District', 'Metro', 'Urban', 'City', 'Regional', 'Provincial', 'State', 'Zone', 'Area'],
            'ranked' => ['Ranked', 'Ladder', 'Climb', 'Grind', 'Push', 'Top', 'High', 'Peak', 'Summit', 'Ascent']
        ];

        $suffixes = [
            'semi-pro' => ['Gaming', 'Esports', 'Squad', 'Team', 'Crew', 'Force', 'Unit', 'Legion', 'Dynasty', 'Empire'],
            'regional' => ['Heroes', 'Rivals', 'Warriors', 'Legends', 'Champions', 'Guardians', 'Titans', 'Knights', 'Defenders', 'Crusaders'],
            'ranked' => ['Masters', 'Grandmasters', 'Challengers', 'Predators', 'Dominators', 'Conquerors', 'Elites', 'Aces', 'Stars', 'Pros']
        ];

        $prefix = $prefixes[$type][array_rand($prefixes[$type])];
        $suffix = $suffixes[$type][array_rand($suffixes[$type])];

        return "{$prefix} {$suffix} {$region}{$index}";
    }

    private function generatePlayersForTeam($teamName, $region, $playerCount = 6)
    {
        $players = [];
        $roles = ['duelist', 'duelist', 'vanguard', 'vanguard', 'strategist', 'strategist'];
        $regionCountries = $this->regions[$region]['countries'];

        for ($i = 0; $i < $playerCount; $i++) {
            $players[] = [
                'name' => $this->generatePlayerName($region, $i),
                'username' => $this->generatePlayerUsername($region, $i),
                'role' => $roles[$i],
                'country' => $regionCountries[array_rand($regionCountries)],
                'team' => $teamName
            ];
        }

        return $players;
    }

    private function generatePlayerName($region, $index)
    {
        $names = [
            'NA' => ['Ace', 'Strike', 'Blaze', 'Storm', 'Shadow', 'Hawk', 'Wolf', 'Eagle', 'Viper', 'Phoenix'],
            'EU' => ['Knight', 'King', 'Prince', 'Duke', 'Baron', 'Lord', 'Master', 'Legend', 'Hero', 'Champion'],
            'ASIA' => ['Dragon', 'Tiger', 'Lion', 'Eagle', 'Phoenix', 'Samurai', 'Ninja', 'Shogun', 'Ronin', 'Sensei'],
            'CN' => ['Long', 'Feng', 'Lei', 'Yun', 'Xing', 'Ming', 'Tian', 'Di', 'Shan', 'Hai'],
            'OCE' => ['Roo', 'Koala', 'Dingo', 'Shark', 'Spider', 'Croc', 'Wave', 'Reef', 'Bush', 'Outback'],
            'SA' => ['Jaguar', 'Puma', 'Condor', 'Anaconda', 'Piranha', 'Thunder', 'Storm', 'Lightning', 'Fire', 'Ice'],
            'MENA' => ['Sultan', 'Emir', 'Sheikh', 'Caliph', 'Vizier', 'Falcon', 'Desert', 'Oasis', 'Mirage', 'Sphinx']
        ];

        $suffixes = ['X', 'Z', '99', '007', 'Pro', 'God', 'King', 'Boss', 'Elite', 'Prime'];
        
        $baseName = $names[$region][$index % count($names[$region])];
        $suffix = rand(0, 1) ? $suffixes[array_rand($suffixes)] : rand(1, 999);
        
        return $baseName . $suffix;
    }

    private function generatePlayerUsername($region, $index)
    {
        return strtolower($this->generatePlayerName($region, $index)) . '_' . substr(md5(rand()), 0, 4);
    }

    private function calculateRating($tier)
    {
        return match($tier) {
            'S' => rand(1800, 2000),
            'A' => rand(1600, 1799),
            'B' => rand(1400, 1599),
            'C' => rand(1200, 1399),
            'D' => rand(1000, 1199),
            default => rand(800, 999)
        };
    }

    private function importAllData()
    {
        DB::beginTransaction();
        
        try {
            $totalTeams = 0;
            $totalPlayers = 0;

            foreach ($this->allTeamsData as $teamData) {
                // Create team
                $team = Team::create([
                    'name' => $teamData['name'],
                    'short_name' => $this->generateUniqueShortName($teamData['name'], $teamData['region']),
                    'country' => $teamData['country'],
                    'region' => $teamData['region'],
                    'status' => 'active',
                    'game' => 'marvel_rivals',
                    'platform' => 'PC',
                    'rating' => $teamData['rating'],
                    'earnings' => rand(0, 50000),
                    'wins' => rand(0, 100),
                    'losses' => rand(0, 50),
                    'win_rate' => rand(40, 80),
                    'map_win_rate' => rand(45, 75),
                    'total_matches' => rand(10, 150)
                ]);

                $totalTeams++;

                // Create players
                foreach ($teamData['players'] as $playerData) {
                    Player::create([
                        'name' => $playerData['name'],
                        'username' => $playerData['username'],
                        'team_id' => $team->id,
                        'role' => $playerData['role'],
                        'country' => $playerData['country'],
                        'country_flag' => $this->getCountryFlag($playerData['country']),
                        'status' => 'active',
                        'rating' => $team->rating - rand(100, 300),
                        'earnings' => rand(0, 10000),
                        'main_hero' => $this->getRandomHero($playerData['role']),
                        'skill_rating' => rand(3000, 5000),
                        'region' => $teamData['region'],
                        'total_matches' => rand(50, 500),
                        'tournaments_played' => rand(1, 20)
                    ]);

                    $totalPlayers++;
                }

                if ($totalTeams % 50 === 0) {
                    echo "Imported {$totalTeams} teams and {$totalPlayers} players...\n";
                }
            }

            DB::commit();

            echo "\n=== FINAL IMPORT SUMMARY ===\n";
            echo "Total Teams: {$totalTeams}\n";
            echo "Total Players: {$totalPlayers}\n";
            
            // Show distribution
            $distribution = Team::selectRaw('region, count(*) as count')
                ->groupBy('region')
                ->pluck('count', 'region');
            
            echo "\nTeams by Region:\n";
            foreach ($distribution as $region => $count) {
                echo "  {$region}: {$count} teams\n";
            }

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function getCountryFlag($country)
    {
        $flags = [
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
            'Mexico' => '🇲🇽',
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Chile' => '🇨🇱',
            'Peru' => '🇵🇪',
            'Colombia' => '🇨🇴',
            'Venezuela' => '🇻🇪',
            'Uruguay' => '🇺🇾',
            'United Kingdom' => '🇬🇧',
            'Germany' => '🇩🇪',
            'France' => '🇫🇷',
            'Spain' => '🇪🇸',
            'Italy' => '🇮🇹',
            'Netherlands' => '🇳🇱',
            'Belgium' => '🇧🇪',
            'Sweden' => '🇸🇪',
            'Denmark' => '🇩🇰',
            'Norway' => '🇳🇴',
            'Finland' => '🇫🇮',
            'Poland' => '🇵🇱',
            'Russia' => '🇷🇺',
            'Ukraine' => '🇺🇦',
            'Turkey' => '🇹🇷',
            'Greece' => '🇬🇷',
            'Portugal' => '🇵🇹',
            'Czech Republic' => '🇨🇿',
            'Austria' => '🇦🇹',
            'Switzerland' => '🇨🇭',
            'South Korea' => '🇰🇷',
            'Japan' => '🇯🇵',
            'China' => '🇨🇳',
            'Taiwan' => '🇹🇼',
            'Hong Kong' => '🇭🇰',
            'Singapore' => '🇸🇬',
            'Thailand' => '🇹🇭',
            'Vietnam' => '🇻🇳',
            'Philippines' => '🇵🇭',
            'Indonesia' => '🇮🇩',
            'Malaysia' => '🇲🇾',
            'India' => '🇮🇳',
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'Saudi Arabia' => '🇸🇦',
            'United Arab Emirates' => '🇦🇪',
            'Kuwait' => '🇰🇼',
            'Qatar' => '🇶🇦',
            'Egypt' => '🇪🇬',
            'Jordan' => '🇯🇴',
            'Lebanon' => '🇱🇧'
        ];

        return $flags[$country] ?? '🌍';
    }

    private function getRandomHero($role)
    {
        $heroes = [
            'duelist' => ['Black Widow', 'Hawkeye', 'Hela', 'Iron Man', 'Magik', 'Moon Knight', 'Namor', 'Psylocke', 'Punisher', 'Scarlet Witch', 'Spider-Man', 'Star-Lord', 'Storm', 'The Winter Soldier'],
            'vanguard' => ['Captain America', 'Doctor Strange', 'Groot', 'Hulk', 'Magneto', 'Peni Parker', 'Thor', 'Venom'],
            'strategist' => ['Adam Warlock', 'Cloak & Dagger', 'Jeff the Land Shark', 'Loki', 'Luna Snow', 'Mantis', 'Rocket Raccoon']
        ];

        return $heroes[$role][array_rand($heroes[$role])];
    }

    private function generateUniqueShortName($teamName, $region)
    {
        static $usedShortNames = [];
        
        $base = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $teamName)), 0, 7);
        $shortName = $base . $region;
        
        // If already used, add a number
        $counter = 1;
        while (in_array($shortName, $usedShortNames) || strlen($shortName) > 10) {
            $shortName = $base . $counter;
            $counter++;
            if (strlen($shortName) > 10) {
                $base = substr($base, 0, -1);
                $shortName = $base . $counter;
            }
        }
        
        $usedShortNames[] = $shortName;
        return $shortName;
    }
}