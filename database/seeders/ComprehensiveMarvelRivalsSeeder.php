<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Player;

class ComprehensiveMarvelRivalsSeeder extends Seeder
{
    public function run()
    {
        // Disable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Clear existing data
        Player::truncate();
        Team::truncate();
        
        // Re-enable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        echo "Creating comprehensive Marvel Rivals teams and players...\n";

        // Team 1: 100 Thieves
        $team1 = Team::create([
            'name' => '100 Thieves',
            'short_name' => '100T',
            'logo' => 'https://liquipedia.net/commons/2/26/100_Thieves_lightmode.png',
            'region' => 'Americas',
            'country' => 'US',
            'flag' => 'https://flagcdn.com/16x12/us.png',
            'platform' => 'PC',
            'game' => 'Marvel Rivals',
            'division' => 'Professional',
            'rating' => 1850,
            'rank' => 3,
            'win_rate' => 0.68,
            'points' => 85,
            'record' => '12-5',
            'peak' => 1920,
            'streak' => 3,
            'founded' => '2017-04-01',
            'captain' => 'delenaa',
            'coach' => 'Zikz',
            'website' => 'https://100thieves.com',
            'earnings' => 75000.0,
            'social_media' => json_encode([
                'twitter' => 'https://twitter.com/100thieves',
                'instagram' => 'https://instagram.com/100thieves',
                'website' => 'https://100thieves.com'
            ]),
            'achievements' => json_encode([
                'Tournament Wins' => 3,
                'Top 3 Finishes' => 8,
                'Prize Money' => '$75,000'
            ]),
            'recent_form' => json_encode(['W', 'W', 'L', 'W', 'W']),
            'player_count' => 6
        ]);

        $team1_players = [
            ['name' => 'delenaa', 'real_name' => 'John Delena', 'role' => 'duelist', 'country' => 'US', 'age' => 23, 'rating' => 1880.5],
            ['name' => 'hxrvey', 'real_name' => 'Harvey Chen', 'role' => 'duelist', 'country' => 'CA', 'age' => 21, 'rating' => 1840.2],
            ['name' => 'SJP', 'real_name' => 'Samuel Peterson', 'role' => 'vanguard', 'country' => 'US', 'age' => 25, 'rating' => 1820.8],
            ['name' => 'TTK', 'real_name' => 'Tyler Kim', 'role' => 'vanguard', 'country' => 'US', 'age' => 22, 'rating' => 1830.1],
            ['name' => 'Terra', 'real_name' => 'Marcus Davis', 'role' => 'strategist', 'country' => 'US', 'age' => 26, 'rating' => 1860.9],
            ['name' => 'Vinnie', 'real_name' => 'Vincent Rodriguez', 'role' => 'strategist', 'country' => 'US', 'age' => 24, 'rating' => 1845.3]
        ];

        foreach ($team1_players as $playerData) {
            Player::create([
                'name' => $playerData['name'],
                'username' => $playerData['name'],
                'real_name' => $playerData['real_name'],
                'team_id' => $team1->id,
                'role' => $playerData['role'],
                'main_hero' => $this->getMainHeroForRole($playerData['role']),
                'alt_heroes' => json_encode($this->getAltHeroes($playerData['role'])),
                'region' => 'Americas',
                'country' => $playerData['country'],
                'country_code' => $playerData['country'],
                'rank' => 0,
                'rating' => $playerData['rating'],
                'elo_rating' => $playerData['rating'],
                'peak_elo' => $playerData['rating'] + rand(20, 50),
                'age' => $playerData['age'],
                'earnings' => 12500.0,
                'total_earnings' => 12500.0,
                'social_media' => json_encode([
                    'twitter' => 'https://twitter.com/' . strtolower($playerData['name']),
                    'twitch' => 'https://twitch.tv/' . strtolower($playerData['name'])
                ]),
                'twitter' => 'https://twitter.com/' . strtolower($playerData['name']),
                'twitch' => 'https://twitch.tv/' . strtolower($playerData['name']),
                'biography' => 'Professional Marvel Rivals player competing for 100 Thieves.',
                'past_teams' => json_encode([]),
                'total_matches' => rand(80, 120),
                'tournaments_played' => rand(10, 25),
                'hero_pool' => json_encode($this->getHeroPool($playerData['role'])),
                'most_played_hero' => $this->getMainHeroForRole($playerData['role']),
                'hero_statistics' => json_encode([
                    'favorite_hero' => $this->getMainHeroForRole($playerData['role']),
                    'playtime_hours' => rand(800, 1500),
                    'avg_kda' => $this->getKDAForRole($playerData['role'])
                ]),
                'overall_kda' => $this->getKDAForRole($playerData['role']),
                'average_damage_per_match' => $this->getDamageForRole($playerData['role']),
                'current_win_streak' => rand(0, 5),
                'longest_win_streak' => rand(3, 12)
            ]);
        }

        echo "Created team: 100 Thieves with 6 players\n";

        // Team 2: Sentinels
        $team2 = Team::create([
            'name' => 'Sentinels',
            'short_name' => 'SEN',
            'logo' => 'https://liquipedia.net/commons/2/2a/Sentinels_lightmode.png',
            'region' => 'Americas',
            'country' => 'US',
            'flag' => 'https://flagcdn.com/16x12/us.png',
            'platform' => 'PC',
            'game' => 'Marvel Rivals',
            'division' => 'Professional',
            'rating' => 1920,
            'rank' => 1,
            'win_rate' => 0.78,
            'points' => 95,
            'record' => '15-2',
            'peak' => 1950,
            'streak' => 5,
            'founded' => '2018-02-15',
            'captain' => 'TenZ',
            'coach' => 'Kaplan',
            'website' => 'https://sentinels.gg',
            'earnings' => 125000.0,
            'social_media' => json_encode([
                'twitter' => 'https://twitter.com/sentinels',
                'instagram' => 'https://instagram.com/sentinels',
                'website' => 'https://sentinels.gg'
            ]),
            'achievements' => json_encode([
                'Tournament Wins' => 5,
                'Top 3 Finishes' => 12,
                'Prize Money' => '$125,000'
            ]),
            'recent_form' => json_encode(['W', 'W', 'W', 'W', 'W']),
            'player_count' => 6
        ]);

        $team2_players = [
            ['name' => 'TenZ', 'real_name' => 'Tyson Ngo', 'role' => 'duelist', 'country' => 'CA', 'age' => 23, 'rating' => 1950.8],
            ['name' => 'zekken', 'real_name' => 'Zachary Patrone', 'role' => 'duelist', 'country' => 'US', 'age' => 21, 'rating' => 1920.5],
            ['name' => 'johnqt', 'real_name' => 'John Larsen', 'role' => 'vanguard', 'country' => 'US', 'age' => 25, 'rating' => 1890.2],
            ['name' => 'Sacy', 'real_name' => 'Gustavo Rossi', 'role' => 'vanguard', 'country' => 'BR', 'age' => 27, 'rating' => 1885.9],
            ['name' => 'pancada', 'real_name' => 'Bryan Luna', 'role' => 'strategist', 'country' => 'BR', 'age' => 22, 'rating' => 1900.3],
            ['name' => 'zellsis', 'real_name' => 'Jordan Montemurro', 'role' => 'strategist', 'country' => 'US', 'age' => 26, 'rating' => 1875.1]
        ];

        foreach ($team2_players as $playerData) {
            Player::create([
                'name' => $playerData['name'],
                'username' => $playerData['name'],
                'real_name' => $playerData['real_name'],
                'team_id' => $team2->id,
                'role' => $playerData['role'],
                'main_hero' => $this->getMainHeroForRole($playerData['role']),
                'alt_heroes' => json_encode($this->getAltHeroes($playerData['role'])),
                'region' => 'Americas',
                'country' => $playerData['country'],
                'rank' => 0,
                'rating' => $playerData['rating'],
                'age' => $playerData['age'],
                'earnings' => 20833.0,
                'social_media' => json_encode([
                    'twitter' => 'https://twitter.com/' . strtolower($playerData['name']),
                    'twitch' => 'https://twitch.tv/' . strtolower($playerData['name'])
                ]),
                'biography' => 'Professional Marvel Rivals player competing for Sentinels.',
                'past_teams' => json_encode([]),
                'total_matches' => rand(90, 140),
                'total_wins' => rand(60, 110),
                'total_maps_played' => rand(220, 350),
                'avg_rating' => round($playerData['rating'] / 10, 2),
                'avg_combat_score' => $this->getCombatScoreForRole($playerData['role']) + 15, // Sentinels boost
                'avg_kda' => $this->getKDAForRole($playerData['role']) + 0.1,
                'avg_damage_per_round' => $this->getDamageForRole($playerData['role']) + 10,
                'avg_kast' => rand(75, 90) / 100,
                'avg_kills_per_round' => $this->getKillsForRole($playerData['role']) + 0.05,
                'avg_assists_per_round' => $this->getAssistsForRole($playerData['role']) + 0.03,
                'avg_first_kills_per_round' => $this->getFirstKillsForRole($playerData['role']) + 0.02,
                'avg_first_deaths_per_round' => rand(3, 8) / 100,
                'hero_pool' => json_encode($this->getHeroPool($playerData['role'])),
                'career_stats' => json_encode([
                    'favorite_hero' => $this->getMainHeroForRole($playerData['role']),
                    'playtime_hours' => rand(1000, 1800),
                    'tournaments_played' => rand(15, 30)
                ]),
                'achievements' => json_encode([
                    'MVP Awards' => rand(2, 6),
                    'Tournament Wins' => rand(2, 5),
                    'Ace Rounds' => rand(5, 15)
                ])
            ]);
        }

        echo "Created team: Sentinels with 6 players\n";

        // Add more teams...
        $this->createAdditionalTeams();

        echo "\n=== SEEDING COMPLETED ===\n";
        echo "Total teams created: " . Team::count() . "\n";
        echo "Total players created: " . Player::count() . "\n";
    }

    private function createAdditionalTeams()
    {
        // Team 3: G2 Esports (EMEA)
        $team3 = Team::create([
            'name' => 'G2 Esports',
            'short_name' => 'G2',
            'logo' => 'https://liquipedia.net/commons/thumb/d/da/G2_Esports_lightmode.png',
            'region' => 'EMEA',
            'country' => 'DE',
            'flag' => 'https://flagcdn.com/16x12/de.png',
            'platform' => 'PC',
            'game' => 'Marvel Rivals',
            'division' => 'Professional',
            'rating' => 1870,
            'rank' => 2,
            'win_rate' => 0.72,
            'points' => 82,
            'record' => '11-4',
            'peak' => 1920,
            'streak' => 2,
            'founded' => '2014-02-24',
            'captain' => 'leaf',
            'coach' => 'ReynAD27',
            'website' => 'https://g2esports.com',
            'earnings' => 80000.0,
            'social_media' => json_encode([
                'twitter' => 'https://twitter.com/G2esports',
                'instagram' => 'https://instagram.com/g2esports',
                'website' => 'https://g2esports.com'
            ]),
            'achievements' => json_encode([
                'Tournament Wins' => 2,
                'Top 3 Finishes' => 9,
                'Prize Money' => '$80,000'
            ]),
            'recent_form' => json_encode(['W', 'L', 'W', 'W', 'W']),
            'player_count' => 6
        ]);

        $team3_players = [
            ['name' => 'leaf', 'real_name' => 'Nathan Orf', 'role' => 'duelist', 'country' => 'US', 'age' => 21, 'rating' => 1910.4],
            ['name' => 'trent', 'real_name' => 'Trent Cairns', 'role' => 'duelist', 'country' => 'US', 'age' => 20, 'rating' => 1880.7],
            ['name' => 'valyn', 'real_name' => 'Jacob Batio', 'role' => 'vanguard', 'country' => 'US', 'age' => 22, 'rating' => 1850.2],
            ['name' => 'JonahP', 'real_name' => 'Jonah Pulice', 'role' => 'vanguard', 'country' => 'US', 'age' => 21, 'rating' => 1845.8],
            ['name' => 'neT', 'real_name' => 'Josh Seangpan', 'role' => 'strategist', 'country' => 'US', 'age' => 22, 'rating' => 1870.9],
            ['name' => 'icy', 'real_name' => 'Ian Baker', 'role' => 'strategist', 'country' => 'US', 'age' => 19, 'rating' => 1860.1]
        ];

        foreach ($team3_players as $playerData) {
            Player::create([
                'name' => $playerData['name'],
                'username' => $playerData['name'],
                'real_name' => $playerData['real_name'],
                'team_id' => $team3->id,
                'role' => $playerData['role'],
                'main_hero' => $this->getMainHeroForRole($playerData['role']),
                'alt_heroes' => json_encode($this->getAltHeroes($playerData['role'])),
                'region' => 'EMEA',
                'country' => $playerData['country'],
                'rank' => 0,
                'rating' => $playerData['rating'],
                'age' => $playerData['age'],
                'earnings' => 13333.0,
                'social_media' => json_encode([
                    'twitter' => 'https://twitter.com/' . strtolower($playerData['name']),
                    'twitch' => 'https://twitch.tv/' . strtolower($playerData['name'])
                ]),
                'biography' => 'Professional Marvel Rivals player competing for G2 Esports.',
                'past_teams' => json_encode([]),
                'total_matches' => rand(70, 120),
                'total_wins' => rand(45, 85),
                'total_maps_played' => rand(180, 280),
                'avg_rating' => round($playerData['rating'] / 10, 2),
                'avg_combat_score' => $this->getCombatScoreForRole($playerData['role']) + 5,
                'avg_kda' => $this->getKDAForRole($playerData['role']),
                'avg_damage_per_round' => $this->getDamageForRole($playerData['role']),
                'avg_kast' => rand(72, 85) / 100,
                'avg_kills_per_round' => $this->getKillsForRole($playerData['role']),
                'avg_assists_per_round' => $this->getAssistsForRole($playerData['role']),
                'avg_first_kills_per_round' => $this->getFirstKillsForRole($playerData['role']),
                'avg_first_deaths_per_round' => rand(6, 11) / 100,
                'hero_pool' => json_encode($this->getHeroPool($playerData['role'])),
                'career_stats' => json_encode([
                    'favorite_hero' => $this->getMainHeroForRole($playerData['role']),
                    'playtime_hours' => rand(900, 1600),
                    'tournaments_played' => rand(12, 28)
                ]),
                'achievements' => json_encode([
                    'MVP Awards' => rand(1, 4),
                    'Tournament Wins' => rand(1, 3),
                    'Ace Rounds' => rand(4, 11)
                ])
            ]);
        }

        echo "Created team: G2 Esports with 6 players\n";

        // Team 4: Paper Rex (Asia Pacific)
        $team4 = Team::create([
            'name' => 'Paper Rex',
            'short_name' => 'PRX',
            'logo' => 'https://liquipedia.net/commons/thumb/b/b0/Paper_Rex_lightmode.png',
            'region' => 'Asia Pacific',
            'country' => 'SG',
            'flag' => 'https://flagcdn.com/16x12/sg.png',
            'platform' => 'PC',
            'game' => 'Marvel Rivals',
            'division' => 'Professional',
            'rating' => 1800,
            'rank' => 4,
            'win_rate' => 0.65,
            'points' => 75,
            'record' => '10-5',
            'peak' => 1850,
            'streak' => 1,
            'founded' => '2020-09-20',
            'captain' => 'Jinggg',
            'coach' => 'alecks',
            'website' => 'https://paperrex.gg',
            'earnings' => 65000.0,
            'social_media' => json_encode([
                'twitter' => 'https://twitter.com/paperrex',
                'instagram' => 'https://instagram.com/paperrex',
                'website' => 'https://paperrex.gg'
            ]),
            'achievements' => json_encode([
                'Tournament Wins' => 2,
                'Top 3 Finishes' => 6,
                'Prize Money' => '$65,000'
            ]),
            'recent_form' => json_encode(['W', 'W', 'L', 'W', 'L']),
            'player_count' => 6
        ]);

        $team4_players = [
            ['name' => 'Jinggg', 'real_name' => 'Wang Jing Jie', 'role' => 'duelist', 'country' => 'SG', 'age' => 21, 'rating' => 1860.5],
            ['name' => 'f0rsakeN', 'real_name' => 'Jason Susanto', 'role' => 'duelist', 'country' => 'ID', 'age' => 22, 'rating' => 1840.8],
            ['name' => 'mindfreak', 'real_name' => 'Aaron Leonhart', 'role' => 'vanguard', 'country' => 'ID', 'age' => 25, 'rating' => 1790.3],
            ['name' => 'Benkai', 'real_name' => 'Benedict Tan', 'role' => 'vanguard', 'country' => 'SG', 'age' => 28, 'rating' => 1780.7],
            ['name' => 'marved', 'real_name' => 'Jimmy Nguyen', 'role' => 'strategist', 'country' => 'VN', 'age' => 24, 'rating' => 1810.2],
            ['name' => 'd4v41', 'real_name' => 'Davai Kunthara', 'role' => 'strategist', 'country' => 'TH', 'age' => 21, 'rating' => 1795.9]
        ];

        foreach ($team4_players as $playerData) {
            Player::create([
                'name' => $playerData['name'],
                'username' => $playerData['name'],
                'real_name' => $playerData['real_name'],
                'team_id' => $team4->id,
                'role' => $playerData['role'],
                'main_hero' => $this->getMainHeroForRole($playerData['role']),
                'alt_heroes' => json_encode($this->getAltHeroes($playerData['role'])),
                'region' => 'Asia Pacific',
                'country' => $playerData['country'],
                'rank' => 0,
                'rating' => $playerData['rating'],
                'age' => $playerData['age'],
                'earnings' => 10833.0,
                'social_media' => json_encode([
                    'twitter' => 'https://twitter.com/' . strtolower($playerData['name']),
                    'twitch' => 'https://twitch.tv/' . strtolower($playerData['name'])
                ]),
                'biography' => 'Professional Marvel Rivals player competing for Paper Rex.',
                'past_teams' => json_encode([]),
                'total_matches' => rand(60, 100),
                'total_wins' => rand(35, 65),
                'total_maps_played' => rand(150, 250),
                'avg_rating' => round($playerData['rating'] / 10, 2),
                'avg_combat_score' => $this->getCombatScoreForRole($playerData['role']),
                'avg_kda' => $this->getKDAForRole($playerData['role']),
                'avg_damage_per_round' => $this->getDamageForRole($playerData['role']),
                'avg_kast' => rand(68, 82) / 100,
                'avg_kills_per_round' => $this->getKillsForRole($playerData['role']),
                'avg_assists_per_round' => $this->getAssistsForRole($playerData['role']),
                'avg_first_kills_per_round' => $this->getFirstKillsForRole($playerData['role']),
                'avg_first_deaths_per_round' => rand(7, 13) / 100,
                'hero_pool' => json_encode($this->getHeroPool($playerData['role'])),
                'career_stats' => json_encode([
                    'favorite_hero' => $this->getMainHeroForRole($playerData['role']),
                    'playtime_hours' => rand(800, 1400),
                    'tournaments_played' => rand(10, 22)
                ]),
                'achievements' => json_encode([
                    'MVP Awards' => rand(1, 3),
                    'Tournament Wins' => rand(1, 2),
                    'Ace Rounds' => rand(3, 9)
                ])
            ]);
        }

        echo "Created team: Paper Rex with 6 players\n";
    }

    private function getMainHeroForRole($role)
    {
        return match($role) {
            'duelist' => ['Spider-Man', 'Iron Man', 'The Punisher', 'Winter Soldier'][rand(0, 3)],
            'vanguard' => ['Hulk', 'Captain America', 'Thor', 'Magneto'][rand(0, 3)],
            'strategist' => ['Luna Snow', 'Mantis', 'Cloak & Dagger', 'Adam Warlock'][rand(0, 3)]
        };
    }

    private function getAltHeroes($role)
    {
        return match($role) {
            'duelist' => ['Spider-Man', 'Iron Man', 'The Punisher'],
            'vanguard' => ['Hulk', 'Captain America', 'Thor'],
            'strategist' => ['Luna Snow', 'Mantis', 'Cloak & Dagger']
        };
    }

    private function getHeroPool($role)
    {
        return match($role) {
            'duelist' => ['Spider-Man', 'Iron Man', 'The Punisher', 'Winter Soldier', 'Wolverine'],
            'vanguard' => ['Hulk', 'Captain America', 'Thor', 'Magneto', 'Doctor Strange'],
            'strategist' => ['Luna Snow', 'Mantis', 'Cloak & Dagger', 'Adam Warlock', 'Rocket Raccoon']
        };
    }

    private function getCombatScoreForRole($role)
    {
        return match($role) {
            'duelist' => rand(280, 340),
            'vanguard' => rand(200, 260),
            'strategist' => rand(160, 220)
        };
    }

    private function getKDAForRole($role)
    {
        return match($role) {
            'duelist' => round(rand(130, 170) / 100, 2),
            'vanguard' => round(rand(90, 130) / 100, 2),
            'strategist' => round(rand(80, 120) / 100, 2)
        };
    }

    private function getDamageForRole($role)
    {
        return match($role) {
            'duelist' => rand(190, 230),
            'vanguard' => rand(140, 180),
            'strategist' => rand(110, 150)
        };
    }

    private function getKillsForRole($role)
    {
        return match($role) {
            'duelist' => round(rand(85, 115) / 100, 2),
            'vanguard' => round(rand(65, 95) / 100, 2),
            'strategist' => round(rand(50, 80) / 100, 2)
        };
    }

    private function getAssistsForRole($role)
    {
        return match($role) {
            'duelist' => round(rand(45, 75) / 100, 2),
            'vanguard' => round(rand(65, 95) / 100, 2),
            'strategist' => round(rand(85, 125) / 100, 2)
        };
    }

    private function getFirstKillsForRole($role)
    {
        return match($role) {
            'duelist' => round(rand(18, 28) / 100, 2),
            'vanguard' => round(rand(10, 18) / 100, 2),
            'strategist' => round(rand(5, 12) / 100, 2)
        };
    }
}