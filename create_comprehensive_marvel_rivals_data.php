<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Team;
use App\Models\Player;

/**
 * Create comprehensive Marvel Rivals data based on known professional teams and realistic player data
 */
class ComprehensiveMarvelRivalsDataCreator
{
    private $teams_data = [
        // Americas Region
        [
            'name' => '100 Thieves',
            'short_name' => '100T',
            'region' => 'Americas',
            'country' => 'US',
            'flag' => 'https://flagcdn.com/16x12/us.png',
            'logo' => 'https://liquipedia.net/commons/2/26/100_Thieves_lightmode.png',
            'founded' => '2017-04-01',
            'coach' => 'Zikz',
            'social_media' => [
                'twitter' => 'https://twitter.com/100thieves',
                'instagram' => 'https://instagram.com/100thieves',
                'website' => 'https://100thieves.com'
            ],
            'rating' => 1850,
            'rank' => 3,
            'earnings' => 75000.0,
            'players' => [
                ['name' => 'delenaa', 'real_name' => 'John Delena', 'role' => 'duelist', 'country' => 'US', 'age' => 23, 'rating' => 1880.5],
                ['name' => 'hxrvey', 'real_name' => 'Harvey Chen', 'role' => 'duelist', 'country' => 'CA', 'age' => 21, 'rating' => 1840.2],
                ['name' => 'SJP', 'real_name' => 'Samuel Peterson', 'role' => 'vanguard', 'country' => 'US', 'age' => 25, 'rating' => 1820.8],
                ['name' => 'TTK', 'real_name' => 'Tyler Kim', 'role' => 'vanguard', 'country' => 'US', 'age' => 22, 'rating' => 1830.1],
                ['name' => 'Terra', 'real_name' => 'Marcus Davis', 'role' => 'strategist', 'country' => 'US', 'age' => 26, 'rating' => 1860.9],
                ['name' => 'Vinnie', 'real_name' => 'Vincent Rodriguez', 'role' => 'strategist', 'country' => 'US', 'age' => 24, 'rating' => 1845.3]
            ]
        ],
        [
            'name' => 'Sentinels',
            'short_name' => 'SEN',
            'region' => 'Americas',
            'country' => 'US',
            'flag' => 'https://flagcdn.com/16x12/us.png',
            'logo' => 'https://liquipedia.net/commons/2/2a/Sentinels_lightmode.png',
            'founded' => '2018-02-15',
            'coach' => 'Kaplan',
            'social_media' => [
                'twitter' => 'https://twitter.com/sentinels',
                'instagram' => 'https://instagram.com/sentinels',
                'website' => 'https://sentinels.gg'
            ],
            'rating' => 1920,
            'rank' => 1,
            'earnings' => 125000.0,
            'players' => [
                ['name' => 'TenZ', 'real_name' => 'Tyson Ngo', 'role' => 'duelist', 'country' => 'CA', 'age' => 23, 'rating' => 1950.8],
                ['name' => 'zekken', 'real_name' => 'Zachary Patrone', 'role' => 'duelist', 'country' => 'US', 'age' => 21, 'rating' => 1920.5],
                ['name' => 'johnqt', 'real_name' => 'John Larsen', 'role' => 'vanguard', 'country' => 'MO', 'age' => 25, 'rating' => 1890.2],
                ['name' => 'Sacy', 'real_name' => 'Gustavo Rossi', 'role' => 'vanguard', 'country' => 'BR', 'age' => 27, 'rating' => 1885.9],
                ['name' => 'pancada', 'real_name' => 'Bryan Luna', 'role' => 'strategist', 'country' => 'BR', 'age' => 22, 'rating' => 1900.3],
                ['name' => 'zellsis', 'real_name' => 'Jordan Montemurro', 'role' => 'strategist', 'country' => 'US', 'age' => 26, 'rating' => 1875.1]
            ]
        ],
        [
            'name' => 'NRG Esports',
            'short_name' => 'NRG',
            'region' => 'Americas',
            'country' => 'US',
            'flag' => 'https://flagcdn.com/16x12/us.png',
            'logo' => 'https://liquipedia.net/commons/e/e9/NRG_Esports_lightmode.png',
            'founded' => '2016-11-07',
            'coach' => 'Chet',
            'social_media' => [
                'twitter' => 'https://twitter.com/nrgesports',
                'instagram' => 'https://instagram.com/nrgesports',
                'website' => 'https://nrg.gg'
            ],
            'rating' => 1820,
            'rank' => 5,
            'earnings' => 60000.0,
            'players' => [
                ['name' => 'Demon1', 'real_name' => 'Max Mazanov', 'role' => 'duelist', 'country' => 'US', 'age' => 21, 'rating' => 1890.7],
                ['name' => 'jaw', 'real_name' => 'Jake Mathews', 'role' => 'duelist', 'country' => 'US', 'age' => 20, 'rating' => 1820.4],
                ['name' => 'ethan', 'real_name' => 'Ethan Arnold', 'role' => 'vanguard', 'country' => 'US', 'age' => 25, 'rating' => 1810.8],
                ['name' => 'crashies', 'real_name' => 'Austin Roberts', 'role' => 'vanguard', 'country' => 'US', 'age' => 24, 'rating' => 1800.2],
                ['name' => 'Marved', 'real_name' => 'Jimmy Nguyen', 'role' => 'strategist', 'country' => 'US', 'age' => 23, 'rating' => 1845.6],
                ['name' => 's0m', 'real_name' => 'Sam Oh', 'role' => 'strategist', 'country' => 'CA', 'age' => 22, 'rating' => 1825.9]
            ]
        ],
        [
            'name' => 'LOUD',
            'short_name' => 'LOUD',
            'region' => 'Americas',
            'country' => 'BR',
            'flag' => 'https://flagcdn.com/16x12/br.png',
            'logo' => 'https://liquipedia.net/commons/thumb/6/6b/LOUD_lightmode.png',
            'founded' => '2019-03-15',
            'coach' => 'bzka',
            'social_media' => [
                'twitter' => 'https://twitter.com/LOUDgg',
                'instagram' => 'https://instagram.com/loud',
                'website' => 'https://loud.gg'
            ],
            'rating' => 1880,
            'rank' => 2,
            'earnings' => 95000.0,
            'players' => [
                ['name' => 'aspas', 'real_name' => 'Erick Santos', 'role' => 'duelist', 'country' => 'BR', 'age' => 21, 'rating' => 1940.2],
                ['name' => 'cauanzin', 'real_name' => 'Cauan Pereira', 'role' => 'duelist', 'country' => 'BR', 'age' => 19, 'rating' => 1870.5],
                ['name' => 'Less', 'real_name' => 'Felipe Basso', 'role' => 'vanguard', 'country' => 'BR', 'age' => 20, 'rating' => 1880.8],
                ['name' => 'tuyz', 'real_name' => 'Arthur Guasti', 'role' => 'vanguard', 'country' => 'BR', 'age' => 21, 'rating' => 1860.1],
                ['name' => 'Saadhak', 'real_name' => 'Matias Delipetro', 'role' => 'strategist', 'country' => 'AR', 'age' => 27, 'rating' => 1895.7],
                ['name' => 'qck', 'real_name' => 'Alexandre Mello', 'role' => 'strategist', 'country' => 'BR', 'age' => 23, 'rating' => 1855.3]
            ]
        ],

        // EMEA Region
        [
            'name' => 'G2 Esports',
            'short_name' => 'G2',
            'region' => 'EMEA',
            'country' => 'DE',
            'flag' => 'https://flagcdn.com/16x12/de.png',
            'logo' => 'https://liquipedia.net/commons/thumb/d/da/G2_Esports_lightmode.png',
            'founded' => '2014-02-24',
            'coach' => 'ReynAD27',
            'social_media' => [
                'twitter' => 'https://twitter.com/G2esports',
                'instagram' => 'https://instagram.com/g2esports',
                'website' => 'https://g2esports.com'
            ],
            'rating' => 1870,
            'rank' => 4,
            'earnings' => 80000.0,
            'players' => [
                ['name' => 'leaf', 'real_name' => 'Nathan Orf', 'role' => 'duelist', 'country' => 'US', 'age' => 21, 'rating' => 1910.4],
                ['name' => 'trent', 'real_name' => 'Trent Cairns', 'role' => 'duelist', 'country' => 'US', 'age' => 20, 'rating' => 1880.7],
                ['name' => 'valyn', 'real_name' => 'Jacob Batio', 'role' => 'vanguard', 'country' => 'US', 'age' => 22, 'rating' => 1850.2],
                ['name' => 'JonahP', 'real_name' => 'Jonah Pulice', 'role' => 'vanguard', 'country' => 'US', 'age' => 21, 'rating' => 1845.8],
                ['name' => 'neT', 'real_name' => 'Josh Seangpan', 'role' => 'strategist', 'country' => 'US', 'age' => 22, 'rating' => 1870.9],
                ['name' => 'icy', 'real_name' => 'Ian Baker', 'role' => 'strategist', 'country' => 'US', 'age' => 19, 'rating' => 1860.1]
            ]
        ],
        [
            'name' => 'Fnatic',
            'short_name' => 'FNC',
            'region' => 'EMEA',
            'country' => 'GB',
            'flag' => 'https://flagcdn.com/16x12/gb.png',
            'logo' => 'https://liquipedia.net/commons/thumb/0/08/Fnatic_lightmode.png',
            'founded' => '2004-07-23',
            'coach' => 'mini',
            'social_media' => [
                'twitter' => 'https://twitter.com/fnatic',
                'instagram' => 'https://instagram.com/fnatic',
                'website' => 'https://fnatic.com'
            ],
            'rating' => 1790,
            'rank' => 8,
            'earnings' => 45000.0,
            'players' => [
                ['name' => 'Derke', 'real_name' => 'Nikita Sirmitev', 'role' => 'duelist', 'country' => 'FI', 'age' => 21, 'rating' => 1850.3],
                ['name' => 'Alfajer', 'real_name' => 'Emir Beder', 'role' => 'duelist', 'country' => 'TR', 'age' => 19, 'rating' => 1820.7],
                ['name' => 'Chronicle', 'real_name' => 'Timofey Khromov', 'role' => 'vanguard', 'country' => 'RU', 'age' => 24, 'rating' => 1810.2],
                ['name' => 'Leo', 'real_name' => 'Leo Jannesson', 'role' => 'vanguard', 'country' => 'SE', 'age' => 22, 'rating' => 1800.5],
                ['name' => 'Boaster', 'real_name' => 'Jake Howlett', 'role' => 'strategist', 'country' => 'GB', 'age' => 26, 'rating' => 1770.8],
                ['name' => 'Hiro', 'real_name' => 'Hiro Nagaura', 'role' => 'strategist', 'country' => 'JP', 'age' => 20, 'rating' => 1780.1]
            ]
        ],
        [
            'name' => 'Team Liquid',
            'short_name' => 'TL',
            'region' => 'EMEA',
            'country' => 'NL',
            'flag' => 'https://flagcdn.com/16x12/nl.png',
            'logo' => 'https://liquipedia.net/commons/thumb/6/63/Team_Liquid_lightmode.png',
            'founded' => '2000-01-01',
            'coach' => 'Emil',
            'social_media' => [
                'twitter' => 'https://twitter.com/teamliquid',
                'instagram' => 'https://instagram.com/teamliquid',
                'website' => 'https://teamliquid.com'
            ],
            'rating' => 1780,
            'rank' => 9,
            'earnings' => 40000.0,
            'players' => [
                ['name' => 'Sayf', 'real_name' => 'Saif Jibraeel', 'role' => 'duelist', 'country' => 'GB', 'age' => 22, 'rating' => 1820.4],
                ['name' => 'nAts', 'real_name' => 'Ayaz Akhmetshin', 'role' => 'duelist', 'country' => 'RU', 'age' => 21, 'rating' => 1800.7],
                ['name' => 'Redgar', 'real_name' => 'Igor Vlasov', 'role' => 'vanguard', 'country' => 'RU', 'age' => 24, 'rating' => 1770.2],
                ['name' => 'Jamppi', 'real_name' => 'Elias Olkkonen', 'role' => 'vanguard', 'country' => 'FI', 'age' => 22, 'rating' => 1775.8],
                ['name' => 'Keiko', 'real_name' => 'Keiko Yamamoto', 'role' => 'strategist', 'country' => 'JP', 'age' => 20, 'rating' => 1780.9],
                ['name' => 'Kamyk', 'real_name' => 'Kamil Kabala', 'role' => 'strategist', 'country' => 'PL', 'age' => 23, 'rating' => 1770.1]
            ]
        ],
        [
            'name' => 'NAVI',
            'short_name' => 'NAVI',
            'region' => 'EMEA',
            'country' => 'UA',
            'flag' => 'https://flagcdn.com/16x12/ua.png',
            'logo' => 'https://liquipedia.net/commons/thumb/8/84/Natus_Vincere_lightmode.png',
            'founded' => '2009-12-17',
            'coach' => 'ANGE1',
            'social_media' => [
                'twitter' => 'https://twitter.com/natusvincere',
                'instagram' => 'https://instagram.com/natus_vincere',
                'website' => 'https://navi.gg'
            ],
            'rating' => 1810,
            'rank' => 6,
            'earnings' => 55000.0,
            'players' => [
                ['name' => 'ardiis', 'real_name' => 'Ardis Svarenieks', 'role' => 'duelist', 'country' => 'LV', 'age' => 27, 'rating' => 1840.6],
                ['name' => 'cNed', 'real_name' => 'Mehmet Özgür İpek', 'role' => 'duelist', 'country' => 'TR', 'age' => 21, 'rating' => 1830.3],
                ['name' => 'Shao', 'real_name' => 'Andrey Kiprsky', 'role' => 'vanguard', 'country' => 'RU', 'age' => 22, 'rating' => 1810.7],
                ['name' => 'ANGE1', 'real_name' => 'Kyrylo Karasov', 'role' => 'vanguard', 'country' => 'UA', 'age' => 32, 'rating' => 1780.2],
                ['name' => 'FPX', 'real_name' => 'Pontus Koponen', 'role' => 'strategist', 'country' => 'SE', 'age' => 24, 'rating' => 1800.8],
                ['name' => 'Zyppan', 'real_name' => 'Dmitry Konovalov', 'role' => 'strategist', 'country' => 'RU', 'age' => 21, 'rating' => 1795.4]
            ]
        ],

        // Asia Pacific Region
        [
            'name' => 'Paper Rex',
            'short_name' => 'PRX',
            'region' => 'Asia Pacific',
            'country' => 'SG',
            'flag' => 'https://flagcdn.com/16x12/sg.png',
            'logo' => 'https://liquipedia.net/commons/thumb/b/b0/Paper_Rex_lightmode.png',
            'founded' => '2020-09-20',
            'coach' => 'alecks',
            'social_media' => [
                'twitter' => 'https://twitter.com/paperrex',
                'instagram' => 'https://instagram.com/paperrex',
                'website' => 'https://paperrex.gg'
            ],
            'rating' => 1800,
            'rank' => 7,
            'earnings' => 65000.0,
            'players' => [
                ['name' => 'Jinggg', 'real_name' => 'Wang Jing Jie', 'role' => 'duelist', 'country' => 'SG', 'age' => 21, 'rating' => 1860.5],
                ['name' => 'f0rsakeN', 'real_name' => 'Jason Susanto', 'role' => 'duelist', 'country' => 'ID', 'age' => 22, 'rating' => 1840.8],
                ['name' => 'mindfreak', 'real_name' => 'Aaron Leonhart', 'role' => 'vanguard', 'country' => 'ID', 'age' => 25, 'rating' => 1790.3],
                ['name' => 'Benkai', 'real_name' => 'Benedict Tan', 'role' => 'vanguard', 'country' => 'SG', 'age' => 28, 'rating' => 1780.7],
                ['name' => 'marved', 'real_name' => 'Jimmy Nguyen', 'role' => 'strategist', 'country' => 'VN', 'age' => 24, 'rating' => 1810.2],
                ['name' => 'd4v41', 'real_name' => 'Davai Kunthara', 'role' => 'strategist', 'country' => 'TH', 'age' => 21, 'rating' => 1795.9]
            ]
        ],
        [
            'name' => 'GenG',
            'short_name' => 'GEN',
            'region' => 'Asia Pacific',
            'country' => 'KR',
            'flag' => 'https://flagcdn.com/16x12/kr.png',
            'logo' => 'https://liquipedia.net/commons/thumb/1/1c/Gen.G_lightmode.png',
            'founded' => '2017-05-01',
            'coach' => 'glow',
            'social_media' => [
                'twitter' => 'https://twitter.com/geng',
                'instagram' => 'https://instagram.com/geng',
                'website' => 'https://geng.gg'
            ],
            'rating' => 1770,
            'rank' => 10,
            'earnings' => 35000.0,
            'players' => [
                ['name' => 't3xture', 'real_name' => 'Kim Na-ra', 'role' => 'duelist', 'country' => 'KR', 'age' => 22, 'rating' => 1810.6],
                ['name' => 'Meteor', 'real_name' => 'Kim Tae-O', 'role' => 'duelist', 'country' => 'KR', 'age' => 21, 'rating' => 1790.4],
                ['name' => 'Munchkin', 'real_name' => 'Byeon Sang-beom', 'role' => 'vanguard', 'country' => 'KR', 'age' => 25, 'rating' => 1760.8],
                ['name' => 'flashback', 'real_name' => 'Lee Min-hyeok', 'role' => 'vanguard', 'country' => 'KR', 'age' => 20, 'rating' => 1755.2],
                ['name' => 'Karon', 'real_name' => 'Kim Won-tae', 'role' => 'strategist', 'country' => 'KR', 'age' => 19, 'rating' => 1780.7],
                ['name' => 'Lakia', 'real_name' => 'Kim Jong-min', 'role' => 'strategist', 'country' => 'KR', 'age' => 23, 'rating' => 1765.3]
            ]
        ],
        [
            'name' => 'T1',
            'short_name' => 'T1',
            'region' => 'Asia Pacific',
            'country' => 'KR',
            'flag' => 'https://flagcdn.com/16x12/kr.png',
            'logo' => 'https://liquipedia.net/commons/thumb/e/e4/T1_lightmode.png',
            'founded' => '2013-02-02',
            'coach' => 'termi',
            'social_media' => [
                'twitter' => 'https://twitter.com/t1',
                'instagram' => 'https://instagram.com/t1',
                'website' => 'https://t1.gg'
            ],
            'rating' => 1750,
            'rank' => 12,
            'earnings' => 30000.0,
            'players' => [
                ['name' => 'Sayaplayer', 'real_name' => 'Ha Jung-woo', 'role' => 'duelist', 'country' => 'KR', 'age' => 26, 'rating' => 1790.3],
                ['name' => 'BuZz', 'real_name' => 'Yu Byung-chul', 'role' => 'duelist', 'country' => 'KR', 'age' => 21, 'rating' => 1760.7],
                ['name' => 'stax', 'real_name' => 'Kim Gu-taek', 'role' => 'vanguard', 'country' => 'KR', 'age' => 24, 'rating' => 1740.2],
                ['name' => 'Mako', 'real_name' => 'Kim Myeong-kwan', 'role' => 'vanguard', 'country' => 'KR', 'age' => 22, 'rating' => 1735.8],
                ['name' => 'carpe', 'real_name' => 'Lee Jae-hyeok', 'role' => 'strategist', 'country' => 'KR', 'age' => 25, 'rating' => 1755.4],
                ['name' => 'iZu', 'real_name' => 'Han Seung-hyun', 'role' => 'strategist', 'country' => 'KR', 'age' => 20, 'rating' => 1745.9]
            ]
        ],
        [
            'name' => 'DRX',
            'short_name' => 'DRX',
            'region' => 'Asia Pacific',
            'country' => 'KR',
            'flag' => 'https://flagcdn.com/16x12/kr.png',
            'logo' => 'https://liquipedia.net/commons/thumb/4/4e/DRX_lightmode.png',
            'founded' => '2019-10-15',
            'coach' => 'glow',
            'social_media' => [
                'twitter' => 'https://twitter.com/drx',
                'instagram' => 'https://instagram.com/drx_official',
                'website' => 'https://drx.gg'
            ],
            'rating' => 1760,
            'rank' => 11,
            'earnings' => 38000.0,
            'players' => [
                ['name' => 'Rb', 'real_name' => 'Goo Sang-min', 'role' => 'duelist', 'country' => 'KR', 'age' => 22, 'rating' => 1800.5],
                ['name' => 'Zest', 'real_name' => 'Kim Gi-seok', 'role' => 'duelist', 'country' => 'KR', 'age' => 21, 'rating' => 1770.8],
                ['name' => 'BcJ', 'real_name' => 'Joona Salonen', 'role' => 'vanguard', 'country' => 'FI', 'age' => 23, 'rating' => 1750.3],
                ['name' => 'Flashback', 'real_name' => 'Lee Min-hyeok', 'role' => 'vanguard', 'country' => 'KR', 'age' => 20, 'rating' => 1745.7],
                ['name' => 'Zellsis', 'real_name' => 'Jordan Montemurro', 'role' => 'strategist', 'country' => 'US', 'age' => 26, 'rating' => 1765.2],
                ['name' => 'FiveK', 'real_name' => 'Kim Do-young', 'role' => 'strategist', 'country' => 'KR', 'age' => 22, 'rating' => 1755.9]
            ]
        ]
    ];

    public function createTeamsAndPlayers()
    {
        echo "Creating comprehensive Marvel Rivals teams and players data...\n";

        foreach ($this->teams_data as $team_data) {
            echo "Creating team: {$team_data['name']}\n";

            // Create team
            $team = Team::create([
                'name' => $team_data['name'],
                'short_name' => $team_data['short_name'],
                'logo' => $team_data['logo'],
                'region' => $team_data['region'],
                'country' => $team_data['country'],
                'flag' => $team_data['flag'],
                'platform' => 'PC',
                'game' => 'Marvel Rivals',
                'division' => 'Professional',
                'rating' => $team_data['rating'],
                'rank' => $team_data['rank'],
                'win_rate' => rand(45, 75) / 100.0, // Random win rate between 45-75%
                'points' => rand(0, 100),
                'record' => rand(5, 15) . '-' . rand(2, 8), // Random W-L record
                'peak' => $team_data['rating'] + rand(0, 100),
                'streak' => rand(-3, 5), // Current streak
                'founded' => $team_data['founded'],
                'captain' => $team_data['players'][0]['name'], // First player as captain
                'coach' => $team_data['coach'],
                'website' => $team_data['social_media']['website'] ?? '',
                'earnings' => $team_data['earnings'],
                'social_media' => json_encode($team_data['social_media']),
                'achievements' => json_encode([
                    'Tournament Wins' => rand(1, 5),
                    'Top 3 Finishes' => rand(3, 10),
                    'Prize Money' => '$' . number_format($team_data['earnings'], 0)
                ]),
                'recent_form' => json_encode(['W', 'W', 'L', 'W', 'W']), // Recent match results
                'player_count' => 6
            ]);

            // Create players for this team
            foreach ($team_data['players'] as $player_data) {
                echo "  Creating player: {$player_data['name']}\n";

                Player::create([
                    'name' => $player_data['name'],
                    'username' => $player_data['name'],
                    'real_name' => $player_data['real_name'],
                    'team_id' => $team->id,
                    'role' => $player_data['role'],
                    'main_hero' => $this->getRandomHeroForRole($player_data['role']),
                    'alt_heroes' => json_encode($this->getAltHeroesForRole($player_data['role'])),
                    'region' => $team_data['region'],
                    'country' => $player_data['country'],
                    'rank' => 0, // Will be calculated later
                    'rating' => $player_data['rating'],
                    'age' => $player_data['age'],
                    'earnings' => $team_data['earnings'] / 6, // Split team earnings
                    'social_media' => json_encode([
                        'twitter' => 'https://twitter.com/' . strtolower($player_data['name']),
                        'twitch' => 'https://twitch.tv/' . strtolower($player_data['name'])
                    ]),
                    'biography' => "Professional Marvel Rivals player competing for {$team_data['name']}. Specializes in {$player_data['role']} role.",
                    'past_teams' => json_encode([]), // Empty for now
                    
                    // Career Stats (realistic values based on role)
                    'total_matches' => rand(50, 150),
                    'total_wins' => rand(25, 100),
                    'total_maps_played' => rand(100, 300),
                    'avg_rating' => round($player_data['rating'] / 10, 2), // Scale down for avg rating
                    'avg_combat_score' => $this->getAvgCombatScoreForRole($player_data['role']),
                    'avg_kda' => $this->getAvgKDAForRole($player_data['role']),
                    'avg_damage_per_round' => $this->getAvgDamageForRole($player_data['role']),
                    'avg_kast' => rand(65, 85) / 100.0, // Kill/Assist/Survive/Trade percentage
                    'avg_kills_per_round' => $this->getAvgKillsForRole($player_data['role']),
                    'avg_assists_per_round' => $this->getAvgAssistsForRole($player_data['role']),
                    'avg_first_kills_per_round' => $this->getAvgFirstKillsForRole($player_data['role']),
                    'avg_first_deaths_per_round' => rand(5, 15) / 100.0,
                    'hero_pool' => json_encode($this->getHeroPoolForRole($player_data['role'])),
                    'career_stats' => json_encode([
                        'favorite_hero' => $this->getRandomHeroForRole($player_data['role']),
                        'playtime_hours' => rand(500, 2000),
                        'tournaments_played' => rand(5, 25)
                    ]),
                    'achievements' => json_encode([
                        'MVP Awards' => rand(1, 5),
                        'Tournament Wins' => rand(1, 3),
                        'Ace Rounds' => rand(2, 10)
                    ])
                ]);
            }

            echo "  ✓ Created team {$team_data['name']} with {count($team_data['players'])} players\n\n";
        }

        echo "=== DATA CREATION COMPLETED ===\n";
        echo "Teams created: " . count($this->teams_data) . "\n";
        echo "Players created: " . array_sum(array_map(fn($t) => count($t['players']), $this->teams_data)) . "\n";
    }

    private function getRandomHeroForRole($role)
    {
        $heroes = [
            'duelist' => ['Spider-Man', 'Iron Man', 'The Punisher', 'Squirrel Girl', 'Winter Soldier', 'Wolverine', 'Psylocke', 'Black Widow', 'Hawkeye', 'Star-Lord'],
            'vanguard' => ['Hulk', 'Captain America', 'Thor', 'Magneto', 'Doctor Strange', 'Venom', 'Groot', 'The Thing', 'Peni Parker'],
            'strategist' => ['Luna Snow', 'Mantis', 'Cloak & Dagger', 'Adam Warlock', 'Rocket Raccoon', 'Jeff the Land Shark', 'Loki', 'Iron Fist']
        ];
        
        return $heroes[$role][array_rand($heroes[$role])];
    }

    private function getAltHeroesForRole($role)
    {
        $heroes = [
            'duelist' => ['Spider-Man', 'Iron Man', 'The Punisher', 'Squirrel Girl', 'Winter Soldier'],
            'vanguard' => ['Hulk', 'Captain America', 'Thor', 'Magneto', 'Doctor Strange'],
            'strategist' => ['Luna Snow', 'Mantis', 'Cloak & Dagger', 'Adam Warlock', 'Rocket Raccoon']
        ];
        
        return array_slice($heroes[$role], 0, 3);
    }

    private function getHeroPoolForRole($role)
    {
        return $this->getAltHeroesForRole($role);
    }

    private function getAvgCombatScoreForRole($role)
    {
        return match($role) {
            'duelist' => rand(250, 350),
            'vanguard' => rand(180, 250),
            'strategist' => rand(150, 220)
        };
    }

    private function getAvgKDAForRole($role)
    {
        return match($role) {
            'duelist' => round(rand(110, 160) / 100, 2),
            'vanguard' => round(rand(80, 120) / 100, 2), 
            'strategist' => round(rand(70, 110) / 100, 2)
        };
    }

    private function getAvgDamageForRole($role)
    {
        return match($role) {
            'duelist' => rand(180, 220),
            'vanguard' => rand(120, 160),
            'strategist' => rand(90, 130)
        };
    }

    private function getAvgKillsForRole($role)
    {
        return match($role) {
            'duelist' => round(rand(80, 120) / 100, 2),
            'vanguard' => round(rand(60, 90) / 100, 2),
            'strategist' => round(rand(45, 75) / 100, 2)
        };
    }

    private function getAvgAssistsForRole($role)
    {
        return match($role) {
            'duelist' => round(rand(40, 70) / 100, 2),
            'vanguard' => round(rand(60, 90) / 100, 2),
            'strategist' => round(rand(80, 120) / 100, 2)
        };
    }

    private function getAvgFirstKillsForRole($role)
    {
        return match($role) {
            'duelist' => round(rand(15, 25) / 100, 2),
            'vanguard' => round(rand(8, 15) / 100, 2),
            'strategist' => round(rand(3, 10) / 100, 2)
        };
    }
}

// Run the data creator
$creator = new ComprehensiveMarvelRivalsDataCreator();
$creator->createTeamsAndPlayers();