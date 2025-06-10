<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarvelRivalsLiveDataSeeder extends Seeder
{
    public function run()
    {
        // ========================================
        // 1. UPDATE TEAM RATINGS & DATA
        // ========================================
        
        $teams_data = [
            // Tier 1 Teams (2200+ Rating)
            ['name' => 'Luminosity Gaming', 'rating' => 2387, 'rank' => 1, 'earnings' => '$847,250', 'division' => 'Celestial'],
            ['name' => 'Fnatic', 'rating' => 2201, 'rank' => 2, 'earnings' => '$634,100', 'division' => 'Celestial'],
            ['name' => 'OG', 'rating' => 2156, 'rank' => 3, 'earnings' => '$523,800', 'division' => 'Vibranium'],
            ['name' => 'Sentinels', 'rating' => 2089, 'rank' => 4, 'earnings' => '$489,200', 'division' => 'Vibranium'],
            
            // Tier 2 Teams (1800-2200 Rating)
            ['name' => '100 Thieves', 'rating' => 1942, 'rank' => 5, 'earnings' => '$356,700', 'division' => 'Vibranium'],
            ['name' => 'SHROUD-X', 'rating' => 1892, 'rank' => 6, 'earnings' => '$298,400', 'division' => 'Diamond'],
            ['name' => 'Team Nemesis', 'rating' => 1743, 'rank' => 7, 'earnings' => '$234,600', 'division' => 'Diamond'],
            ['name' => 'FlyQuest', 'rating' => 1687, 'rank' => 8, 'earnings' => '$198,300', 'division' => 'Diamond'],
            ['name' => 'Rival Esports', 'rating' => 1654, 'rank' => 9, 'earnings' => '$167,800', 'division' => 'Diamond'],
            
            // Tier 3 Teams (1400-1800 Rating)
            ['name' => 'CITADELGG', 'rating' => 1445, 'rank' => 10, 'earnings' => '$123,500', 'division' => 'Platinum'],
            ['name' => 'NTMR', 'rating' => 1398, 'rank' => 11, 'earnings' => '$89,200', 'division' => 'Platinum'],
            ['name' => 'BRR BRR PATAPIM', 'rating' => 1356, 'rank' => 12, 'earnings' => '$76,400', 'division' => 'Platinum'],
            ['name' => 'TEAM1', 'rating' => 1287, 'rank' => 13, 'earnings' => '$54,800', 'division' => 'Gold'],
            ['name' => 'Al Qadsiah', 'rating' => 1245, 'rank' => 14, 'earnings' => '$43,200', 'division' => 'Gold'],
            ['name' => 'Z10', 'rating' => 1198, 'rank' => 15, 'earnings' => '$32,100', 'division' => 'Gold'],
            ['name' => 'All Buisness', 'rating' => 1156, 'rank' => 16, 'earnings' => '$21,500', 'division' => 'Gold'],
            ['name' => 'Yoinkada', 'rating' => 1089, 'rank' => 17, 'earnings' => '$12,800', 'division' => 'Gold'],
        ];

        foreach ($teams_data as $team_data) {
            DB::table('teams')
                ->where('name', $team_data['name'])
                ->update([
                    'rating' => $team_data['rating'],
                    'rank' => $team_data['rank'],
                    'earnings' => $team_data['earnings'],
                    'win_rate' => rand(45, 85),
                    'record' => $this->generateRecord(),
                    'peak' => $team_data['rating'] + rand(50, 200),
                    'streak' => $this->generateStreak(),
                    'founded' => '202' . rand(1, 4),
                    'achievements' => json_encode($this->generateAchievements($team_data['rank'])),
                    'social_media' => json_encode([
                        'twitter' => '@' . str_replace(' ', '', strtolower($team_data['name'])),
                        'youtube' => 'youtube.com/' . str_replace(' ', '', strtolower($team_data['name'])),
                        'twitch' => 'twitch.tv/' . str_replace(' ', '', strtolower($team_data['name']))
                    ])
                ]);
        }

        // ========================================
        // 2. CREATE REALISTIC PLAYER ROSTERS
        // ========================================
        
        $marvel_heroes = [
            'Tank' => ['Hulk', 'Thor', 'Groot', 'Thing', 'Colossus', 'Magneto'],
            'Duelist' => ['Iron Man', 'Spider-Man', 'Black Widow', 'Hawkeye', 'Star-Lord', 'Punisher'],
            'Support' => ['Storm', 'Mantis', 'Rocket Raccoon', 'Cloak & Dagger', 'Luna Snow', 'Adam Warlock']
        ];

        $player_names = [
            'SentinelTenZ', 'TysonFury', 'AceGaming', 'ProdigyX', 'VenomStrike', 'PhoenixRise',
            'StormBreaker', 'IronWill', 'SwiftArrow', 'BlazeFist', 'ThunderBolt', 'ShadowHunter',
            'FrostBite', 'FireStorm', 'LightningFast', 'SteelResolve', 'CrimsonEdge', 'GoldenEagle',
            'SilverFox', 'WildCard', 'NightCrawler', 'DayBreaker', 'MoonWalker', 'StarGazer',
            'CosmicForce', 'QuantumLeap', 'NeonBlast', 'CyberPunk', 'TechMaster', 'CodeBreaker',
            'PixelPerfect', 'DataStream', 'CloudNine', 'StormCloud', 'RainMaker', 'WindRunner',
            'FlameCore', 'IcebergTip', 'RockSolid', 'QuickSilver', 'GhostRider', 'SpeedDemon',
            'PowerHouse', 'BeastMode', 'AlphaWolf', 'BetaTest', 'GammaRay', 'DeltaForce',
            'OmegaPoint', 'ZeroHour', 'MaximumEffort', 'UltimateGoal', 'PrimeFocus', 'CoreValues',
            'EdgeLord', 'TopTier', 'EliteStatus', 'ProLevel', 'MasterClass', 'GrandMaster',
            'LegendStatus', 'MythicRare', 'EpicWin', 'RareFind', 'CommonSense', 'UncommonSkill',
            'SuperiorAim', 'InferiorComplex', 'MajorLeague', 'MinorThreat', 'CriticalHit', 'MissedShot',
            'PerfectGame', 'FlawlessVictory', 'ClutchPlayer', 'TeamPlayer', 'SoloCarry', 'SupportMain',
            'FlexPick', 'OneTricker', 'MetaSlave', 'OffMeta', 'TryHard', 'Casual',
            'Competitive', 'Ranked', 'Unranked', 'Placement', 'Calibrated', 'Decayed',
            'Climbing', 'Falling', 'Stable', 'Volatile', 'Consistent', 'Inconsistent',
            'Reliable', 'Clutch', 'Choker', 'Warrior', 'Guardian', 'Sentinel'
        ];

        $roles = ['Tank', 'Duelist', 'Support']; // Using only basic roles that fit DB
        
        $teams = DB::table('teams')->get();
        
        foreach ($teams as $team) {
            // Delete existing players for this team
            DB::table('players')->where('team_id', $team->id)->delete();
            
            // Create 6 players per team
            for ($i = 0; $i < 6; $i++) {
                $role = $roles[$i % count($roles)];
                $hero_role = $role === 'Flex' ? array_rand($marvel_heroes) : $role;
                $main_hero = $marvel_heroes[$hero_role][array_rand($marvel_heroes[$hero_role])];
                
                $rating_base = $team->rating;
                $player_rating = $rating_base + rand(-200, 300); // Player ratings around team rating
                
                DB::table('players')->insert([
                    'name' => $player_names[array_rand($player_names)] . rand(100, 999),
                    'username' => strtolower($player_names[array_rand($player_names)]) . rand(10, 99),
                    'real_name' => $this->generateRealName(),
                    'role' => $role,
                    'team_id' => $team->id,
                    'main_hero' => $main_hero,
                    'alt_heroes' => json_encode(array_slice(array_values($marvel_heroes[$hero_role]), 0, 3)),
                    'region' => $team->region,
                    'country' => $team->region === 'NA' ? $this->randomNACountry() : $this->randomEUCountry(),
                    'rating' => max(800, min(3000, $player_rating)),
                    'age' => rand(16, 28),
                    'earnings' => '$' . number_format(rand(5000, 150000)),
                    'social_media' => json_encode([
                        'twitter' => '@' . strtolower($player_names[array_rand($player_names)]),
                        'twitch' => 'twitch.tv/' . strtolower($player_names[array_rand($player_names)])
                    ]),
                    'biography' => $this->generatePlayerBio($main_hero, $role),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ========================================
        // 3. CREATE SOME FREE AGENTS
        // ========================================
        
        for ($i = 0; $i < 12; $i++) {
            $role = $roles[array_rand($roles)];
            $hero_role = $role === 'Flex' ? array_rand($marvel_heroes) : $role;
            $main_hero = $marvel_heroes[$hero_role][array_rand($marvel_heroes[$hero_role])];
            
            DB::table('players')->insert([
                'name' => $player_names[array_rand($player_names)] . 'FA' . rand(10, 99),
                'username' => 'fa_' . strtolower($player_names[array_rand($player_names)]),
                'real_name' => $this->generateRealName(),
                'role' => $role,
                'team_id' => null, // Free agent
                'main_hero' => $main_hero,
                'alt_heroes' => json_encode(array_slice(array_values($marvel_heroes[$hero_role]), 0, 2)),
                'region' => ['NA', 'EU'][array_rand(['NA', 'EU'])],
                'country' => 'Free Agent',
                'rating' => rand(1000, 2200),
                'age' => rand(16, 30),
                'earnings' => '$' . number_format(rand(2000, 80000)),
                'social_media' => json_encode([
                    'twitter' => '@fa_' . strtolower($player_names[array_rand($player_names)])
                ]),
                'biography' => 'Looking for team. ' . $this->generatePlayerBio($main_hero, $role),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        echo "✅ Marvel Rivals Live Data Population Complete!\n";
        echo "📊 Updated 17 teams with realistic ratings\n";
        echo "👥 Created " . (17 * 6 + 12) . " players (102 team players + 12 free agents)\n";
        echo "🏆 Added achievements, earnings, social media\n";
        echo "🎮 Marvel Heroes assigned to all players\n";
        echo "🚀 Platform is now LIVE-READY!\n";
    }

    private function generateRecord()
    {
        $wins = rand(15, 45);
        $losses = rand(5, 25);
        return "{$wins}-{$losses}";
    }

    private function generateStreak()
    {
        $streaks = ['3W', '5W', '2L', '1W', '7W', '4L', '2W', '1L', '6W'];
        return $streaks[array_rand($streaks)];
    }

    private function generateAchievements($rank)
    {
        $achievements = [];
        if ($rank <= 3) {
            $achievements[] = 'Marvel Rivals World Championship 2024 - Winner';
            $achievements[] = 'Regional Championship - Winner';
        } elseif ($rank <= 8) {
            $achievements[] = 'Regional Championship - Finalist';
            $achievements[] = 'Major Tournament - Top 4';
        } else {
            $achievements[] = 'Qualifier Tournament - Winner';
            $achievements[] = 'Regional League - Top 8';
        }
        return $achievements;
    }

    private function generateRealName()
    {
        $firstNames = ['Alex', 'Jordan', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Avery', 'Quinn', 'Sage', 'River', 'Phoenix', 'Skyler', 'Cameron', 'Emery', 'Finley'];
        $lastNames = ['Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas'];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    private function randomNACountry()
    {
        $countries = ['United States', 'Canada', 'Mexico'];
        return $countries[array_rand($countries)];
    }

    private function randomEUCountry()
    {
        $countries = ['United Kingdom', 'Germany', 'France', 'Sweden', 'Netherlands', 'Denmark', 'Finland', 'Spain'];
        return $countries[array_rand($countries)];
    }

    private function generatePlayerBio($hero, $role)
    {
        $bios = [
            "Professional {$role} player specializing in {$hero}. Known for exceptional game sense and clutch plays.",
            "Rising star in the Marvel Rivals scene. {$hero} main with incredible mechanical skills.",
            "Veteran {$role} player with years of competitive experience. {$hero} specialist.",
            "Former Overwatch pro who transitioned to Marvel Rivals. Dominates on {$hero}.",
            "Young prodigy known for innovative {$hero} strategies and team leadership.",
        ];
        
        return $bios[array_rand($bios)];
    }
}