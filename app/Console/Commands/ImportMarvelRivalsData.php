<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\Team;
use App\Models\Player;
use App\Models\GameMatch;
use App\Models\EventStanding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ImportMarvelRivalsData extends Command
{
    protected $signature = 'import:marvel-rivals 
                            {--fresh : Clear existing data before import}
                            {--test : Run in test mode without saving}';
    
    protected $description = 'Import comprehensive Marvel Rivals tournament data';
    
    // Comprehensive tournament data
    private $tournaments = [
        'north_america_invitational' => [
            'name' => 'Marvel Rivals Invitational 2025: North America',
            'region' => 'North America',
            'tier' => 'A',
            'prize_pool' => 100000,
            'start_date' => '2025-03-14',
            'end_date' => '2025-03-23',
            'type' => 'invitational',
            'format' => 'double_elimination',
            'organizer' => 'NetEase',
            'teams' => [
                [
                    'name' => 'NRG Esports',
                    'country' => 'United States',
                    'logo' => '/teams/nrg-logo.png',
                    'twitter' => 'https://twitter.com/NRGgg',
                    'players' => [
                        ['ign' => 'Sinatraa', 'real_name' => 'Jay Won', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'zombs', 'real_name' => 'Jared Gitlin', 'role' => 'strategist', 'country' => 'United States'],
                        ['ign' => 'dapr', 'real_name' => 'Michael Gulino', 'role' => 'vanguard', 'country' => 'United States'],
                        ['ign' => 'TenZ', 'real_name' => 'Tyson Ngo', 'role' => 'duelist', 'country' => 'Canada'],
                        ['ign' => 'Shahzam', 'real_name' => 'Shahzeeb Khan', 'role' => 'flex', 'country' => 'United States'],
                        ['ign' => 'sick', 'real_name' => 'Hunter Mims', 'role' => 'substitute', 'country' => 'United States']
                    ],
                    'placement' => 1,
                    'prize' => 40000
                ],
                [
                    'name' => 'Sentinels',
                    'country' => 'United States',
                    'logo' => '/teams/sentinels-logo.png',
                    'twitter' => 'https://twitter.com/Sentinels',
                    'players' => [
                        ['ign' => 'Asuna', 'real_name' => 'Peter Mazuryk', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'bang', 'real_name' => 'Sean Bezerra', 'role' => 'strategist', 'country' => 'United States'],
                        ['ign' => 'derrek', 'real_name' => 'Derrek Ha', 'role' => 'vanguard', 'country' => 'United States'],
                        ['ign' => 'stellar', 'real_name' => 'Brenden McGrath', 'role' => 'flex', 'country' => 'United States'],
                        ['ign' => 'Will', 'real_name' => 'William Cheng', 'role' => 'duelist', 'country' => 'United States']
                    ],
                    'placement' => 2,
                    'prize' => 20000
                ],
                [
                    'name' => 'Cloud9',
                    'country' => 'United States',
                    'logo' => '/teams/cloud9-logo.png',
                    'twitter' => 'https://twitter.com/Cloud9',
                    'players' => [
                        ['ign' => 'leaf', 'real_name' => 'Nathan Orf', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'xeppaa', 'real_name' => 'Erick Bach', 'role' => 'flex', 'country' => 'United States'],
                        ['ign' => 'vanity', 'real_name' => 'Anthony Malaspina', 'role' => 'vanguard', 'country' => 'United States'],
                        ['ign' => 'Zellsis', 'real_name' => 'Jordan Montemurro', 'role' => 'strategist', 'country' => 'United States'],
                        ['ign' => 'yay', 'real_name' => 'Jaccob Whiteaker', 'role' => 'duelist', 'country' => 'United States']
                    ],
                    'placement' => 3,
                    'prize' => 15000
                ],
                [
                    'name' => '100 Thieves',
                    'country' => 'United States',
                    'logo' => '/teams/100t-logo.png',
                    'twitter' => 'https://twitter.com/100Thieves',
                    'players' => [
                        ['ign' => 'Cryo', 'real_name' => 'Dylan Panfilio', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'Derrek', 'real_name' => 'Derrek Ha', 'role' => 'flex', 'country' => 'United States'],
                        ['ign' => 'Asuna', 'real_name' => 'Peter Mazuryk', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'bang', 'real_name' => 'Sean Bezerra', 'role' => 'strategist', 'country' => 'United States'],
                        ['ign' => 'stellar', 'real_name' => 'Brenden McGrath', 'role' => 'vanguard', 'country' => 'United States']
                    ],
                    'placement' => 4,
                    'prize' => 10000
                ],
                [
                    'name' => 'Evil Geniuses',
                    'country' => 'United States',
                    'logo' => '/teams/eg-logo.png',
                    'twitter' => 'https://twitter.com/EvilGeniuses',
                    'players' => [
                        ['ign' => 'jawgemo', 'real_name' => 'Alexander Mor', 'role' => 'duelist', 'country' => 'Cambodia'],
                        ['ign' => 'Boostio', 'real_name' => 'Kelden Pupello', 'role' => 'vanguard', 'country' => 'United States'],
                        ['ign' => 'C0M', 'real_name' => 'Corbin Lee', 'role' => 'strategist', 'country' => 'United States'],
                        ['ign' => 'Demon1', 'real_name' => 'Max Mazanov', 'role' => 'duelist', 'country' => 'Russia'],
                        ['ign' => 'Ethan', 'real_name' => 'Ethan Arnold', 'role' => 'flex', 'country' => 'United States']
                    ],
                    'placement' => 5,
                    'prize' => 7500
                ],
                [
                    'name' => 'FaZe Clan',
                    'country' => 'United States',
                    'logo' => '/teams/faze-logo.png',
                    'twitter' => 'https://twitter.com/FaZeClan',
                    'players' => [
                        ['ign' => 'babybay', 'real_name' => 'Andrej Francisty', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'supamen', 'real_name' => 'Phat Le', 'role' => 'vanguard', 'country' => 'United States'],
                        ['ign' => 'dicey', 'real_name' => 'Quan Tran', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'JonahP', 'real_name' => 'Jonah Pulice', 'role' => 'strategist', 'country' => 'United States'],
                        ['ign' => 'flyuh', 'real_name' => 'Xavier Carlson', 'role' => 'flex', 'country' => 'United States']
                    ],
                    'placement' => 6,
                    'prize' => 5000
                ],
                [
                    'name' => 'TSM',
                    'country' => 'United States',
                    'logo' => '/teams/tsm-logo.png',
                    'twitter' => 'https://twitter.com/TSM',
                    'players' => [
                        ['ign' => 'Subroza', 'real_name' => 'Yassine Taoufik', 'role' => 'flex', 'country' => 'Morocco'],
                        ['ign' => 'gMd', 'real_name' => 'Anthony Guimond', 'role' => 'vanguard', 'country' => 'Canada'],
                        ['ign' => 'seven', 'real_name' => 'Johann Hernandez', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'sym', 'real_name' => 'Xavier Bentley', 'role' => 'strategist', 'country' => 'United States'],
                        ['ign' => 'Rossy', 'real_name' => 'Daniel Abedrabbo', 'role' => 'duelist', 'country' => 'Canada']
                    ],
                    'placement' => 7,
                    'prize' => 2500
                ],
                [
                    'name' => 'Luminosity',
                    'country' => 'United States',
                    'logo' => '/teams/lg-logo.png',
                    'twitter' => 'https://twitter.com/Luminosity',
                    'players' => [
                        ['ign' => 'TiGG', 'real_name' => 'Tanner Spanu', 'role' => 'duelist', 'country' => 'Canada'],
                        ['ign' => 'moose', 'real_name' => 'Kaleb Jayne', 'role' => 'vanguard', 'country' => 'United States'],
                        ['ign' => 'dazzLe', 'real_name' => 'Del Olmo', 'role' => 'strategist', 'country' => 'United States'],
                        ['ign' => 'bdog', 'real_name' => 'Brandon Sanders', 'role' => 'flex', 'country' => 'United States'],
                        ['ign' => 'mada', 'real_name' => 'Adam Pampuch', 'role' => 'duelist', 'country' => 'United States']
                    ],
                    'placement' => 8,
                    'prize' => 0
                ]
            ]
        ],
        'emea_ignite' => [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - EMEA',
            'region' => 'Europe',
            'tier' => 'A',
            'prize_pool' => 250000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament',
            'format' => 'swiss',
            'organizer' => 'NetEase',
            'teams' => [
                [
                    'name' => 'Fnatic',
                    'country' => 'United Kingdom',
                    'logo' => '/teams/fnatic-logo.png',
                    'twitter' => 'https://twitter.com/FNATIC',
                    'players' => [
                        ['ign' => 'Boaster', 'real_name' => 'Jake Howlett', 'role' => 'vanguard', 'country' => 'United Kingdom'],
                        ['ign' => 'Derke', 'real_name' => 'Nikita Sirmitev', 'role' => 'duelist', 'country' => 'Finland'],
                        ['ign' => 'Leo', 'real_name' => 'Leo Jannesson', 'role' => 'strategist', 'country' => 'Sweden'],
                        ['ign' => 'Chronicle', 'real_name' => 'Timofey Khromov', 'role' => 'flex', 'country' => 'Russia'],
                        ['ign' => 'Alfajer', 'real_name' => 'Emir Beder', 'role' => 'duelist', 'country' => 'Turkey']
                    ],
                    'placement' => 1,
                    'prize' => 100000
                ],
                [
                    'name' => 'Team Liquid',
                    'country' => 'Netherlands',
                    'logo' => '/teams/liquid-logo.png',
                    'twitter' => 'https://twitter.com/TeamLiquid',
                    'players' => [
                        ['ign' => 'nAts', 'real_name' => 'Ayaz Akhmetshin', 'role' => 'strategist', 'country' => 'Russia'],
                        ['ign' => 'Redgar', 'real_name' => 'Igor Vlasov', 'role' => 'vanguard', 'country' => 'Russia'],
                        ['ign' => 'Sayf', 'real_name' => 'Saif Jibraeel', 'role' => 'duelist', 'country' => 'Sweden'],
                        ['ign' => 'Jamppi', 'real_name' => 'Elias Olkkonen', 'role' => 'flex', 'country' => 'Finland'],
                        ['ign' => 'soulcas', 'real_name' => 'Dom Sulcas', 'role' => 'duelist', 'country' => 'United Kingdom']
                    ],
                    'placement' => 2,
                    'prize' => 50000
                ],
                [
                    'name' => 'NAVI',
                    'country' => 'Ukraine',
                    'logo' => '/teams/navi-logo.png',
                    'twitter' => 'https://twitter.com/natusvincere',
                    'players' => [
                        ['ign' => 'ANGE1', 'real_name' => 'Kyrylo Karasov', 'role' => 'vanguard', 'country' => 'Ukraine'],
                        ['ign' => 'Shao', 'real_name' => 'Andrey Kiprsky', 'role' => 'strategist', 'country' => 'Russia'],
                        ['ign' => 'SUYGETSU', 'real_name' => 'Dmitry Ilyushin', 'role' => 'flex', 'country' => 'Russia'],
                        ['ign' => 'cNed', 'real_name' => 'Mehmet Yağız İpek', 'role' => 'duelist', 'country' => 'Turkey'],
                        ['ign' => 'ardiis', 'real_name' => 'Ardis Svarenieks', 'role' => 'duelist', 'country' => 'Latvia']
                    ],
                    'placement' => 3,
                    'prize' => 35000
                ],
                [
                    'name' => 'Team Vitality',
                    'country' => 'France',
                    'logo' => '/teams/vitality-logo.png',
                    'twitter' => 'https://twitter.com/TeamVitality',
                    'players' => [
                        ['ign' => 'bonecold', 'real_name' => 'Santeri Sassi', 'role' => 'vanguard', 'country' => 'Finland'],
                        ['ign' => 'Kicks', 'real_name' => 'Kimmie Laasner', 'role' => 'strategist', 'country' => 'Estonia'],
                        ['ign' => 'Twisten', 'real_name' => 'Karel Ašenbrener', 'role' => 'duelist', 'country' => 'Czech Republic'],
                        ['ign' => 'Destrian', 'real_name' => 'Óscar Muñoz', 'role' => 'flex', 'country' => 'Spain'],
                        ['ign' => 'MOLSI', 'real_name' => 'Moe Lester', 'role' => 'duelist', 'country' => 'Belgium']
                    ],
                    'placement' => 4,
                    'prize' => 25000
                ],
                // Add more EMEA teams...
                [
                    'name' => 'G2 Esports',
                    'country' => 'Germany',
                    'logo' => '/teams/g2-logo.png',
                    'twitter' => 'https://twitter.com/G2esports',
                    'players' => [
                        ['ign' => 'mixwell', 'real_name' => 'Óscar Cañellas', 'role' => 'flex', 'country' => 'Spain'],
                        ['ign' => 'AvovA', 'real_name' => 'Auni Chahade', 'role' => 'vanguard', 'country' => 'Germany'],
                        ['ign' => 'nukkye', 'real_name' => 'Žygimantas Chmieliauskas', 'role' => 'duelist', 'country' => 'Lithuania'],
                        ['ign' => 'Meddo', 'real_name' => 'Enzo Mestari', 'role' => 'strategist', 'country' => 'France'],
                        ['ign' => 'hoody', 'real_name' => 'Aaro Peltokangas', 'role' => 'duelist', 'country' => 'Finland']
                    ],
                    'placement' => 5,
                    'prize' => 15000
                ]
            ]
        ],
        'asia_ignite' => [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Asia',
            'region' => 'Asia',
            'tier' => 'A',
            'prize_pool' => 100000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament',
            'format' => 'double_elimination',
            'organizer' => 'NetEase',
            'teams' => [
                [
                    'name' => 'DRX',
                    'country' => 'South Korea',
                    'logo' => '/teams/drx-logo.png',
                    'twitter' => 'https://twitter.com/DRX_VS',
                    'players' => [
                        ['ign' => 'stax', 'real_name' => 'Kim Gu-taek', 'role' => 'vanguard', 'country' => 'South Korea'],
                        ['ign' => 'Rb', 'real_name' => 'Goo Sang-min', 'role' => 'flex', 'country' => 'South Korea'],
                        ['ign' => 'Zest', 'real_name' => 'Kim Ki-seok', 'role' => 'strategist', 'country' => 'South Korea'],
                        ['ign' => 'BuZz', 'real_name' => 'Yu Byung-chul', 'role' => 'duelist', 'country' => 'South Korea'],
                        ['ign' => 'MaKo', 'real_name' => 'Kim Myeong-gwan', 'role' => 'strategist', 'country' => 'South Korea']
                    ],
                    'placement' => 1,
                    'prize' => 40000
                ],
                [
                    'name' => 'Paper Rex',
                    'country' => 'Singapore',
                    'logo' => '/teams/prx-logo.png',
                    'twitter' => 'https://twitter.com/pprxteam',
                    'players' => [
                        ['ign' => 'Benkai', 'real_name' => 'Benedict Tan', 'role' => 'vanguard', 'country' => 'Singapore'],
                        ['ign' => 'mindfreak', 'real_name' => 'Aaron Leonhart', 'role' => 'strategist', 'country' => 'Indonesia'],
                        ['ign' => 'f0rsakeN', 'real_name' => 'Jason Susanto', 'role' => 'flex', 'country' => 'Indonesia'],
                        ['ign' => 'Jinggg', 'real_name' => 'Wang Jing Jie', 'role' => 'duelist', 'country' => 'Singapore'],
                        ['ign' => 'something', 'real_name' => 'Ilya Petrov', 'role' => 'duelist', 'country' => 'Russia']
                    ],
                    'placement' => 2,
                    'prize' => 20000
                ],
                [
                    'name' => 'Gen.G',
                    'country' => 'South Korea',
                    'logo' => '/teams/geng-logo.png',
                    'twitter' => 'https://twitter.com/GenG',
                    'players' => [
                        ['ign' => 'Meteor', 'real_name' => 'Kim Tae-oh', 'role' => 'duelist', 'country' => 'South Korea'],
                        ['ign' => 'ts', 'real_name' => 'Son Tung', 'role' => 'vanguard', 'country' => 'South Korea'],
                        ['ign' => 'Texture', 'real_name' => 'Kim Na-ra', 'role' => 'duelist', 'country' => 'South Korea'],
                        ['ign' => 'Karon', 'real_name' => 'Kim Won-tae', 'role' => 'strategist', 'country' => 'South Korea'],
                        ['ign' => 'Munchkin', 'real_name' => 'Byeon Sang-beom', 'role' => 'flex', 'country' => 'South Korea']
                    ],
                    'placement' => 3,
                    'prize' => 15000
                ],
                [
                    'name' => 'T1',
                    'country' => 'South Korea',
                    'logo' => '/teams/t1-logo.png',
                    'twitter' => 'https://twitter.com/T1',
                    'players' => [
                        ['ign' => 'xccurate', 'real_name' => 'Kevin Susanto', 'role' => 'duelist', 'country' => 'Indonesia'],
                        ['ign' => 'Carpe', 'real_name' => 'Lee Jae-hyeok', 'role' => 'flex', 'country' => 'South Korea'],
                        ['ign' => 'iZu', 'real_name' => 'Ham Woo-ju', 'role' => 'duelist', 'country' => 'South Korea'],
                        ['ign' => 'Sylvan', 'real_name' => 'Ko Young-sub', 'role' => 'strategist', 'country' => 'South Korea'],
                        ['ign' => 'intro', 'real_name' => 'Park Jong-beom', 'role' => 'vanguard', 'country' => 'South Korea']
                    ],
                    'placement' => 4,
                    'prize' => 10000
                ]
            ]
        ],
        'americas_ignite' => [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Americas',
            'region' => 'Americas',
            'tier' => 'A',
            'prize_pool' => 250000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament',
            'format' => 'swiss',
            'organizer' => 'NetEase',
            'teams' => [
                [
                    'name' => 'LOUD',
                    'country' => 'Brazil',
                    'logo' => '/teams/loud-logo.png',
                    'twitter' => 'https://twitter.com/LOUDgg',
                    'players' => [
                        ['ign' => 'saadhak', 'real_name' => 'Matias Delipetro', 'role' => 'vanguard', 'country' => 'Argentina'],
                        ['ign' => 'Less', 'real_name' => 'Felipe Basso', 'role' => 'strategist', 'country' => 'Brazil'],
                        ['ign' => 'cauanzin', 'real_name' => 'Cauan Pereira', 'role' => 'flex', 'country' => 'Brazil'],
                        ['ign' => 'tuyz', 'real_name' => 'Arthur Vieira', 'role' => 'strategist', 'country' => 'Brazil'],
                        ['ign' => 'qck', 'real_name' => 'Gabriel Lima', 'role' => 'duelist', 'country' => 'Brazil']
                    ],
                    'placement' => 1,
                    'prize' => 100000
                ],
                [
                    'name' => 'Leviatán',
                    'country' => 'Chile',
                    'logo' => '/teams/leviatan-logo.png',
                    'twitter' => 'https://twitter.com/LeviatanGG',
                    'players' => [
                        ['ign' => 'Shyy', 'real_name' => 'Fabian Usnayo', 'role' => 'flex', 'country' => 'Chile'],
                        ['ign' => 'kiNgg', 'real_name' => 'Francisco Aravena', 'role' => 'vanguard', 'country' => 'Chile'],
                        ['ign' => 'Mazino', 'real_name' => 'Roberto Rivas', 'role' => 'flex', 'country' => 'Chile'],
                        ['ign' => 'tex', 'real_name' => 'Ian Botsch', 'role' => 'duelist', 'country' => 'United States'],
                        ['ign' => 'C0M', 'real_name' => 'Corbin Lee', 'role' => 'strategist', 'country' => 'United States']
                    ],
                    'placement' => 2,
                    'prize' => 50000
                ],
                [
                    'name' => 'KRÜ Esports',
                    'country' => 'Argentina',
                    'logo' => '/teams/kru-logo.png',
                    'twitter' => 'https://twitter.com/KRUesports',
                    'players' => [
                        ['ign' => 'Klaus', 'real_name' => 'Nicolas Ferrari', 'role' => 'vanguard', 'country' => 'Argentina'],
                        ['ign' => 'Melser', 'real_name' => 'Marco Amaro', 'role' => 'strategist', 'country' => 'Chile'],
                        ['ign' => 'shyy', 'real_name' => 'Fabian Usnayo', 'role' => 'flex', 'country' => 'Chile'],
                        ['ign' => 'heat', 'real_name' => 'Olavo Marcelo', 'role' => 'duelist', 'country' => 'Brazil'],
                        ['ign' => 'mta', 'real_name' => 'Nicolás Sayavedra', 'role' => 'duelist', 'country' => 'Argentina']
                    ],
                    'placement' => 3,
                    'prize' => 35000
                ],
                [
                    'name' => 'MIBR',
                    'country' => 'Brazil',
                    'logo' => '/teams/mibr-logo.png',
                    'twitter' => 'https://twitter.com/mibr',
                    'players' => [
                        ['ign' => 'artzin', 'real_name' => 'Arthur Araujo', 'role' => 'vanguard', 'country' => 'Brazil'],
                        ['ign' => 'jzz', 'real_name' => 'João Pedro', 'role' => 'strategist', 'country' => 'Brazil'],
                        ['ign' => 'frz', 'real_name' => 'Leandro Gomes', 'role' => 'flex', 'country' => 'Brazil'],
                        ['ign' => 'pANcada', 'real_name' => 'Bryan Luna', 'role' => 'strategist', 'country' => 'Brazil'],
                        ['ign' => 'dgzin', 'real_name' => 'Douglas Silva', 'role' => 'duelist', 'country' => 'Brazil']
                    ],
                    'placement' => 4,
                    'prize' => 25000
                ]
            ]
        ],
        'oceania_ignite' => [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Oceania',
            'region' => 'Oceania',
            'tier' => 'A',
            'prize_pool' => 75000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-22',
            'type' => 'tournament',
            'format' => 'double_elimination',
            'organizer' => 'NetEase',
            'teams' => [
                [
                    'name' => 'ORDER',
                    'country' => 'Australia',
                    'logo' => '/teams/order-logo.png',
                    'twitter' => 'https://twitter.com/ORDERgg',
                    'players' => [
                        ['ign' => 'Maple', 'real_name' => 'Luke Maple', 'role' => 'vanguard', 'country' => 'Australia'],
                        ['ign' => 'pz', 'real_name' => 'Park Jun', 'role' => 'strategist', 'country' => 'South Korea'],
                        ['ign' => 'Autumn', 'real_name' => 'Autumn Smith', 'role' => 'flex', 'country' => 'Australia'],
                        ['ign' => 'Wronski', 'real_name' => 'William Wronski', 'role' => 'duelist', 'country' => 'Australia'],
                        ['ign' => 'Rdeew', 'real_name' => 'Richard Deew', 'role' => 'duelist', 'country' => 'New Zealand']
                    ],
                    'placement' => 1,
                    'prize' => 30000
                ],
                [
                    'name' => 'Chiefs ESC',
                    'country' => 'Australia',
                    'logo' => '/teams/chiefs-logo.png',
                    'twitter' => 'https://twitter.com/ChiefsESC',
                    'players' => [
                        ['ign' => 'Denz', 'real_name' => 'Dennis Denz', 'role' => 'vanguard', 'country' => 'Australia'],
                        ['ign' => 'Bob', 'real_name' => 'Robert Bob', 'role' => 'strategist', 'country' => 'Australia'],
                        ['ign' => 'minimiseGG', 'real_name' => 'Jay Min', 'role' => 'flex', 'country' => 'Australia'],
                        ['ign' => 'DeLb', 'real_name' => 'Delan B', 'role' => 'duelist', 'country' => 'Australia'],
                        ['ign' => 'J0LT', 'real_name' => 'Jolt Johnson', 'role' => 'duelist', 'country' => 'Australia']
                    ],
                    'placement' => 2,
                    'prize' => 20000
                ],
                [
                    'name' => 'Bonkers',
                    'country' => 'Australia',
                    'logo' => '/teams/bonkers-logo.png',
                    'twitter' => 'https://twitter.com/BonkersGG',
                    'players' => [
                        ['ign' => 'Swerl', 'real_name' => 'Swerl Anderson', 'role' => 'vanguard', 'country' => 'Australia'],
                        ['ign' => 'lechuga', 'real_name' => 'Lee Chuga', 'role' => 'strategist', 'country' => 'Australia'],
                        ['ign' => 'Nozz', 'real_name' => 'Nathan Oz', 'role' => 'flex', 'country' => 'Australia'],
                        ['ign' => 'RiLey', 'real_name' => 'Riley Smith', 'role' => 'duelist', 'country' => 'New Zealand'],
                        ['ign' => 'Bird', 'real_name' => 'James Bird', 'role' => 'duelist', 'country' => 'Australia']
                    ],
                    'placement' => 3,
                    'prize' => 15000
                ],
                [
                    'name' => 'Mindfreak',
                    'country' => 'Australia',
                    'logo' => '/teams/mindfreak-logo.png',
                    'twitter' => 'https://twitter.com/Mindfreak',
                    'players' => [
                        ['ign' => 'Guzzy', 'real_name' => 'Gus Guzman', 'role' => 'vanguard', 'country' => 'Australia'],
                        ['ign' => 'pl1xx', 'real_name' => 'Felix Plix', 'role' => 'strategist', 'country' => 'Australia'],
                        ['ign' => 'Shiro', 'real_name' => 'Shiro Yamada', 'role' => 'flex', 'country' => 'Japan'],
                        ['ign' => 'SiGN', 'real_name' => 'Sign Johnson', 'role' => 'duelist', 'country' => 'Australia'],
                        ['ign' => 'Zayt', 'real_name' => 'Zayt Williams', 'role' => 'duelist', 'country' => 'Australia']
                    ],
                    'placement' => 4,
                    'prize' => 10000
                ]
            ]
        ]
    ];
    
    public function handle()
    {
        $fresh = $this->option('fresh');
        $test = $this->option('test');
        
        if ($fresh && !$test) {
            $this->warn('Fresh mode will delete existing data!');
            if (!$this->confirm('Are you sure you want to proceed?')) {
                return 0;
            }
            
            $this->clearExistingData();
        }
        
        $this->info('Starting Marvel Rivals data import...');
        
        DB::beginTransaction();
        
        try {
            $totalTeams = 0;
            $totalPlayers = 0;
            $totalEvents = 0;
            
            foreach ($this->tournaments as $key => $tournament) {
                $this->info("\nImporting {$tournament['name']}...");
                
                // Create event
                $event = $this->createEvent($tournament);
                $totalEvents++;
                
                // Import teams and players
                foreach ($tournament['teams'] as $teamData) {
                    $team = $this->createTeam($teamData);
                    $totalTeams++;
                    
                    // Attach team to event with placement
                    $event->teams()->attach($team->id, [
                        'seed' => $teamData['placement'],
                        'registered_at' => now()
                    ]);
                    
                    // Create standing
                    EventStanding::create([
                        'event_id' => $event->id,
                        'team_id' => $team->id,
                        'position' => $teamData['placement'],
                        'prize_money' => $teamData['prize']
                    ]);
                    
                    // Import players
                    foreach ($teamData['players'] as $playerData) {
                        $this->createPlayer($playerData, $team);
                        $totalPlayers++;
                    }
                }
                
                $this->info("✓ Imported {$tournament['name']}");
            }
            
            if (!$test) {
                DB::commit();
                $this->info("\n✅ Import completed successfully!");
            } else {
                DB::rollBack();
                $this->info("\n✅ Test run completed (no data saved)");
            }
            
            $this->info("Total events: {$totalEvents}");
            $this->info("Total teams: {$totalTeams}");
            $this->info("Total players: {$totalPlayers}");
            $this->info("Total prize pool: $" . number_format(775000));
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Import failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function clearExistingData()
    {
        $this->info('Clearing existing Marvel Rivals data...');
        
        // Clear in correct order to respect foreign keys
        EventStanding::query()->delete();
        GameMatch::query()->delete();
        
        // Clear players based on team names (since game column might not exist)
        $marvelTeamIds = Team::whereIn('name', [
            'NRG Esports', 'Sentinels', 'Cloud9', '100 Thieves', 'Evil Geniuses',
            'FaZe Clan', 'TSM', 'Luminosity', 'Fnatic', 'Team Liquid', 'NAVI',
            'Team Vitality', 'G2 Esports', 'DRX', 'Paper Rex', 'Gen.G', 'T1',
            'LOUD', 'Leviatán', 'KRÜ Esports', 'MIBR', 'ORDER', 'Chiefs ESC',
            'Bonkers', 'Mindfreak'
        ])->pluck('id');
        
        Player::whereIn('team_id', $marvelTeamIds)->delete();
        Team::whereIn('id', $marvelTeamIds)->delete();
        
        // Clear events by name pattern
        Event::where('name', 'like', '%Marvel Rivals%')->delete();
    }
    
    private function createEvent($data)
    {
        return Event::create([
            'name' => $data['name'],
            'description' => "{$data['name']} is an online {$data['region']} Marvel Rivals tournament organized by {$data['organizer']}.",
            'region' => $data['region'],
            'tier' => $data['tier'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'prize_pool' => $data['prize_pool'],
            'format' => $data['format'],
            'type' => $data['type'],
            'status' => 'completed'
        ]);
    }
    
    private function createTeam($data)
    {
        $attributes = [
            'country' => $data['country'],
            'region' => $this->getRegionFromCountry($data['country']),
            'logo' => $data['logo'],
            'status' => 'active',
            'elo_rating' => 1500
        ];
        
        // Add social media if column exists
        if (Schema::hasColumn('teams', 'social_media')) {
            $attributes['social_media'] = json_encode([
                'twitter' => $data['twitter'] ?? null
            ]);
        }
        
        return Team::updateOrCreate(
            ['name' => $data['name']],
            $attributes
        );
    }
    
    private function createPlayer($data, Team $team)
    {
        $attributes = [
            'real_name' => $data['real_name'],
            'role' => $data['role'],
            'country' => $data['country'],
            'country_flag' => $this->getCountryFlag($data['country']),
            'team_id' => $team->id,
            'status' => 'active'
        ];
        
        // Map ign to username which is the actual column name
        return Player::updateOrCreate(
            ['username' => $data['ign']],
            $attributes
        );
    }
    
    private function getRegionFromCountry($country)
    {
        $regions = [
            'United States' => 'North America',
            'Canada' => 'North America',
            'Mexico' => 'North America',
            'United Kingdom' => 'Europe',
            'France' => 'Europe',
            'Germany' => 'Europe',
            'Spain' => 'Europe',
            'Italy' => 'Europe',
            'Netherlands' => 'Europe',
            'Sweden' => 'Europe',
            'Denmark' => 'Europe',
            'Norway' => 'Europe',
            'Finland' => 'Europe',
            'Poland' => 'Europe',
            'Russia' => 'Europe',
            'Ukraine' => 'Europe',
            'Turkey' => 'Europe',
            'Czech Republic' => 'Europe',
            'Belgium' => 'Europe',
            'Estonia' => 'Europe',
            'Latvia' => 'Europe',
            'Lithuania' => 'Europe',
            'South Korea' => 'Asia',
            'Japan' => 'Asia',
            'China' => 'Asia',
            'Taiwan' => 'Asia',
            'Hong Kong' => 'Asia',
            'Singapore' => 'Asia',
            'Malaysia' => 'Asia',
            'Thailand' => 'Asia',
            'Philippines' => 'Asia',
            'Indonesia' => 'Asia',
            'Vietnam' => 'Asia',
            'India' => 'Asia',
            'Cambodia' => 'Asia',
            'Australia' => 'Oceania',
            'New Zealand' => 'Oceania',
            'Brazil' => 'South America',
            'Argentina' => 'South America',
            'Chile' => 'South America',
            'Colombia' => 'South America',
            'Peru' => 'South America',
            'Morocco' => 'Africa'
        ];
        
        return $regions[$country] ?? 'International';
    }
    
    private function getCountryFlag($country)
    {
        $flags = [
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
            'Mexico' => '🇲🇽',
            'United Kingdom' => '🇬🇧',
            'France' => '🇫🇷',
            'Germany' => '🇩🇪',
            'Spain' => '🇪🇸',
            'Italy' => '🇮🇹',
            'Netherlands' => '🇳🇱',
            'Sweden' => '🇸🇪',
            'Denmark' => '🇩🇰',
            'Norway' => '🇳🇴',
            'Finland' => '🇫🇮',
            'Poland' => '🇵🇱',
            'Russia' => '🇷🇺',
            'Ukraine' => '🇺🇦',
            'Turkey' => '🇹🇷',
            'Czech Republic' => '🇨🇿',
            'Belgium' => '🇧🇪',
            'Estonia' => '🇪🇪',
            'Latvia' => '🇱🇻',
            'Lithuania' => '🇱🇹',
            'South Korea' => '🇰🇷',
            'Japan' => '🇯🇵',
            'China' => '🇨🇳',
            'Taiwan' => '🇹🇼',
            'Hong Kong' => '🇭🇰',
            'Singapore' => '🇸🇬',
            'Malaysia' => '🇲🇾',
            'Thailand' => '🇹🇭',
            'Philippines' => '🇵🇭',
            'Indonesia' => '🇮🇩',
            'Vietnam' => '🇻🇳',
            'India' => '🇮🇳',
            'Cambodia' => '🇰🇭',
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Chile' => '🇨🇱',
            'Colombia' => '🇨🇴',
            'Peru' => '🇵🇪',
            'Morocco' => '🇲🇦'
        ];
        
        return $flags[$country] ?? null;
    }
}