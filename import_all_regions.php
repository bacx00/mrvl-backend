<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerTeamHistory;
use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AllRegionsImporter
{
    private $tournaments = [
        // EMEA - MR Ignite 2025 Stage 1
        [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - EMEA',
            'region' => 'EMEA',
            'teams' => [
                [
                    'name' => 'Fnatic',
                    'coach' => 'Mini',
                    'players' => [
                        ['username' => 'Boaster', 'real_name' => 'Jake Howlett', 'country' => 'GB', 'role' => 'Tank'],
                        ['username' => 'Derke', 'real_name' => 'Nikita Sirmitev', 'country' => 'FI', 'role' => 'Duelist'],
                        ['username' => 'Alfajer', 'real_name' => 'Emir Ali Beder', 'country' => 'TR', 'role' => 'Duelist'],
                        ['username' => 'Chronicle', 'real_name' => 'Timofey Khromov', 'country' => 'RU', 'role' => 'Tank'],
                        ['username' => 'Leo', 'real_name' => 'Leo Jannesson', 'country' => 'SE', 'role' => 'Support'],
                        ['username' => 'Hiro', 'real_name' => 'Emirhan Kat', 'country' => 'TR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Team Liquid',
                    'coach' => 'sliggy',
                    'players' => [
                        ['username' => 'nAts', 'real_name' => 'Ayaz Akhmetshin', 'country' => 'RU', 'role' => 'Tank'],
                        ['username' => 'Jamppi', 'real_name' => 'Elias Olkkonen', 'country' => 'FI', 'role' => 'Duelist'],
                        ['username' => 'Sayf', 'real_name' => 'Saif Jibraeel', 'country' => 'SE', 'role' => 'Duelist'],
                        ['username' => 'Enzo', 'real_name' => 'Enzo Mestari', 'country' => 'FR', 'role' => 'Tank'],
                        ['username' => 'Mistic', 'real_name' => 'James Orfila', 'country' => 'GB', 'role' => 'Support'],
                        ['username' => 'Keiko', 'real_name' => 'Georgio Sanassy', 'country' => 'FR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'NAVI',
                    'coach' => 'ANGE1',
                    'players' => [
                        ['username' => 'Shao', 'real_name' => 'Andrey Kiprsky', 'country' => 'RU', 'role' => 'Support'],
                        ['username' => 'SUYGETSU', 'real_name' => 'Dmitry Ilyushin', 'country' => 'RU', 'role' => 'Support'],
                        ['username' => 'ardiis', 'real_name' => 'Ardis Svarenieks', 'country' => 'LV', 'role' => 'Duelist'],
                        ['username' => 'cNed', 'real_name' => 'Mehmet Yağız İpek', 'country' => 'TR', 'role' => 'Duelist'],
                        ['username' => 'Ruxic', 'real_name' => 'Uğur Güç', 'country' => 'TR', 'role' => 'Tank'],
                        ['username' => 'Patitek', 'real_name' => 'Patryk Fabrowski', 'country' => 'PL', 'role' => 'Tank']
                    ]
                ],
                [
                    'name' => 'Team Vitality',
                    'coach' => 'Salah',
                    'players' => [
                        ['username' => 'Kicks', 'real_name' => 'Adam Adamou', 'country' => 'GB', 'role' => 'Tank'],
                        ['username' => 'trexx', 'real_name' => 'Nikita Cherednichenko', 'country' => 'RU', 'role' => 'Duelist'],
                        ['username' => 'Sayonara', 'real_name' => 'Samir Meguetounif', 'country' => 'FR', 'role' => 'Duelist'],
                        ['username' => 'runneR', 'real_name' => 'Robin Hereibi', 'country' => 'FR', 'role' => 'Tank'],
                        ['username' => 'Cender', 'real_name' => 'Jokūbas Labutis', 'country' => 'LT', 'role' => 'Support'],
                        ['username' => 'Less', 'real_name' => 'Ričardas Lukaševičius', 'country' => 'LT', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Karmine Corp',
                    'coach' => 'ENGH',
                    'players' => [
                        ['username' => 'ScreaM', 'real_name' => 'Adil Benrlitom', 'country' => 'BE', 'role' => 'Duelist'],
                        ['username' => 'Nivera', 'real_name' => 'Nabil Benrlitom', 'country' => 'BE', 'role' => 'Duelist'],
                        ['username' => 'sh1n', 'real_name' => 'Rayan Biassi', 'country' => 'FR', 'role' => 'Tank'],
                        ['username' => 'ZE1SH', 'real_name' => 'Muhammed Yazıcı', 'country' => 'TR', 'role' => 'Tank'],
                        ['username' => 'tomaszy', 'real_name' => 'Tomasz Kołodziejczyk', 'country' => 'PL', 'role' => 'Support'],
                        ['username' => 'marteen', 'real_name' => 'Martin Pátek', 'country' => 'CZ', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Giants Gaming',
                    'coach' => 'pipsoN',
                    'players' => [
                        ['username' => 'hoody', 'real_name' => 'Aaro Peltokangas', 'country' => 'FI', 'role' => 'Tank'],
                        ['username' => 'Fit1nho', 'real_name' => 'Adolfo Gallego', 'country' => 'ES', 'role' => 'Duelist'],
                        ['username' => 'nukkye', 'real_name' => 'Žygimantas Chmieliauskas', 'country' => 'LT', 'role' => 'Duelist'],
                        ['username' => 'Cloud', 'real_name' => 'Kirill Nehozhin', 'country' => 'RU', 'role' => 'Tank'],
                        ['username' => 'rhyme', 'real_name' => 'Emir Muminovic', 'country' => 'BA', 'role' => 'Support'],
                        ['username' => 'Jesse', 'real_name' => 'Jesse Virtue', 'country' => 'NL', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'FUT Esports',
                    'coach' => 'gob b',
                    'players' => [
                        ['username' => 'qRaxs', 'real_name' => 'Doğukan Balaban', 'country' => 'TR', 'role' => 'Duelist'],
                        ['username' => 'AtaKaptan', 'real_name' => 'Ata Tan', 'country' => 'TR', 'role' => 'Duelist'],
                        ['username' => 'yetujey', 'real_name' => 'Eray Budak', 'country' => 'TR', 'role' => 'Tank'],
                        ['username' => 'MrFaliN', 'real_name' => 'Furkan Yeğen', 'country' => 'TR', 'role' => 'Tank'],
                        ['username' => 'DeepMans', 'real_name' => 'Batuhan Aydın', 'country' => 'TR', 'role' => 'Support'],
                        ['username' => 'RieNs', 'real_name' => 'Enes Ecirli', 'country' => 'TR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Team BDS',
                    'coach' => 'XTQZZZ',
                    'players' => [
                        ['username' => 'Shax', 'real_name' => 'Johannes Kivisild', 'country' => 'EE', 'role' => 'Duelist'],
                        ['username' => 'ALIVE', 'real_name' => 'Gilad Hakim', 'country' => 'IL', 'role' => 'Duelist'],
                        ['username' => 'Fisker', 'real_name' => 'Johannes Vuorinen', 'country' => 'FI', 'role' => 'Tank'],
                        ['username' => 'Reazy', 'real_name' => 'David García', 'country' => 'ES', 'role' => 'Tank'],
                        ['username' => 'Egoist', 'real_name' => 'Ivan Katušić', 'country' => 'HR', 'role' => 'Support'],
                        ['username' => 'tixx', 'real_name' => 'Nicolas Martin', 'country' => 'FR', 'role' => 'Support']
                    ]
                ]
            ]
        ],
        // Asia - MR Ignite 2025 Stage 1
        [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Asia',
            'region' => 'ASIA',
            'teams' => [
                [
                    'name' => 'DRX',
                    'coach' => 'termi',
                    'players' => [
                        ['username' => 'stax', 'real_name' => 'Kim Gu-taek', 'country' => 'KR', 'role' => 'Tank'],
                        ['username' => 'Rb', 'real_name' => 'Goo Sang-min', 'country' => 'KR', 'role' => 'Tank'],
                        ['username' => 'BuZz', 'real_name' => 'Yu Byung-chul', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'Foxy9', 'real_name' => 'Lee Jae-wook', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'MaKo', 'real_name' => 'Kim Myeong-gwan', 'country' => 'KR', 'role' => 'Support'],
                        ['username' => 'BeYN', 'real_name' => 'Son Geon-woo', 'country' => 'KR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'T1',
                    'coach' => 'Autumn',
                    'players' => [
                        ['username' => 'Carpe', 'real_name' => 'Lee Jae-hyeok', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'Sayaplayer', 'real_name' => 'Ha Jung-woo', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'xccurate', 'real_name' => 'Kevin Susanto', 'country' => 'ID', 'role' => 'Tank'],
                        ['username' => 'Meteor', 'real_name' => 'Kim Tae-oh', 'country' => 'KR', 'role' => 'Tank'],
                        ['username' => 'iZu', 'real_name' => 'Ham Woo-ju', 'country' => 'KR', 'role' => 'Support'],
                        ['username' => 'BaN', 'real_name' => 'Kang Jae-won', 'country' => 'KR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Gen.G',
                    'coach' => 'bail',
                    'players' => [
                        ['username' => 'Karon', 'real_name' => 'Kim Won-tae', 'country' => 'KR', 'role' => 'Tank'],
                        ['username' => 't3xture', 'real_name' => 'Kim Na-ra', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'Munchkin', 'real_name' => 'Byeon Sang-beom', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'Meteor', 'real_name' => 'Kim Tae-oh', 'country' => 'KR', 'role' => 'Tank'],
                        ['username' => 'Foxy9', 'real_name' => 'Lee Jae-wook', 'country' => 'KR', 'role' => 'Support'],
                        ['username' => 'yoman', 'real_name' => 'Jung Ho-jin', 'country' => 'KR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Paper Rex',
                    'coach' => 'alecks',
                    'players' => [
                        ['username' => 'Jinggg', 'real_name' => 'Wang Jing Jie', 'country' => 'SG', 'role' => 'Duelist'],
                        ['username' => 'f0rsakeN', 'real_name' => 'Jason Susanto', 'country' => 'ID', 'role' => 'Duelist'],
                        ['username' => 'mindfreak', 'real_name' => 'Aaron Leonhart', 'country' => 'ID', 'role' => 'Tank'],
                        ['username' => 'd4v41', 'real_name' => 'Khalish Rusyaidee', 'country' => 'MY', 'role' => 'Tank'],
                        ['username' => 'something', 'real_name' => 'Ilya Petrov', 'country' => 'RU', 'role' => 'Support'],
                        ['username' => 'cgrs', 'real_name' => 'Nopphon Laungmal', 'country' => 'TH', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'EDward Gaming',
                    'coach' => 'AfteR',
                    'players' => [
                        ['username' => 'ZmjjKK', 'real_name' => 'Zheng Yongkang', 'country' => 'CN', 'role' => 'Duelist'],
                        ['username' => 'Life', 'real_name' => 'Wang Zhihao', 'country' => 'CN', 'role' => 'Duelist'],
                        ['username' => 'Haodong', 'real_name' => 'Guo Haodong', 'country' => 'CN', 'role' => 'Tank'],
                        ['username' => 'nobody', 'real_name' => 'Wang Senxu', 'country' => 'CN', 'role' => 'Tank'],
                        ['username' => 'Chichoo', 'real_name' => 'Tu Xincheng', 'country' => 'CN', 'role' => 'Support'],
                        ['username' => 'Smoggy', 'real_name' => 'Zhang Zhao', 'country' => 'CN', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Zeta Division',
                    'coach' => 'JUNiOR',
                    'players' => [
                        ['username' => 'Laz', 'real_name' => 'Koji Ushida', 'country' => 'JP', 'role' => 'Tank'],
                        ['username' => 'crow', 'real_name' => 'Maruoka crow', 'country' => 'JP', 'role' => 'Tank'],
                        ['username' => 'Dep', 'real_name' => 'Yuma Hashimoto', 'country' => 'JP', 'role' => 'Duelist'],
                        ['username' => 'hiroronn', 'real_name' => 'Hiroki Yanagisawa', 'country' => 'JP', 'role' => 'Duelist'],
                        ['username' => 'SugarZ3ro', 'real_name' => 'Shota Watanabe', 'country' => 'JP', 'role' => 'Support'],
                        ['username' => 'TENNN', 'real_name' => 'Tenta Asai', 'country' => 'JP', 'role' => 'Support']
                    ]
                ]
            ]
        ],
        // Americas - MR Ignite 2025 Stage 1
        [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Americas',
            'region' => 'AMERICAS',
            'teams' => [
                [
                    'name' => 'LOUD',
                    'coach' => 'bzkA',
                    'players' => [
                        ['username' => 'aspas', 'real_name' => 'Erick Santos', 'country' => 'BR', 'role' => 'Duelist'],
                        ['username' => 'Less', 'real_name' => 'Felipe de Loyola', 'country' => 'BR', 'role' => 'Support'],
                        ['username' => 'cauanzin', 'real_name' => 'Cauan Pereira', 'country' => 'BR', 'role' => 'Tank'],
                        ['username' => 'tuyz', 'real_name' => 'Arthur Vieira', 'country' => 'BR', 'role' => 'Tank'],
                        ['username' => 'pANcada', 'real_name' => 'Bryan Luna', 'country' => 'BR', 'role' => 'Support'],
                        ['username' => 'dgzin', 'real_name' => 'Douglas Silva', 'country' => 'BR', 'role' => 'Duelist']
                    ]
                ],
                [
                    'name' => 'Sentinels',
                    'coach' => 'kaplan',
                    'players' => [
                        ['username' => 'TenZ', 'real_name' => 'Tyson Ngo', 'country' => 'CA', 'role' => 'Duelist'],
                        ['username' => 'zekken', 'real_name' => 'Zachary Patrone', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'johnqt', 'real_name' => 'John Quiñones', 'country' => 'MX', 'role' => 'Tank'],
                        ['username' => 'Zellsis', 'real_name' => 'Jordan Montemurro', 'country' => 'US', 'role' => 'Tank'],
                        ['username' => 'Sacy', 'real_name' => 'Gustavo Rossi', 'country' => 'BR', 'role' => 'Support'],
                        ['username' => 'bang', 'real_name' => 'Sean Bezerra', 'country' => 'US', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'FURIA',
                    'coach' => 'carlao',
                    'players' => [
                        ['username' => 'mwzera', 'real_name' => 'Leonardo Serrati', 'country' => 'BR', 'role' => 'Duelist'],
                        ['username' => 'havoc', 'real_name' => 'Ilan Eloy', 'country' => 'BR', 'role' => 'Duelist'],
                        ['username' => 'khalil', 'real_name' => 'Khalil Schmidt', 'country' => 'BR', 'role' => 'Tank'],
                        ['username' => 'liazzi', 'real_name' => 'Gabriel Gomes', 'country' => 'BR', 'role' => 'Tank'],
                        ['username' => 'nzr', 'real_name' => 'Agustin Ibarra', 'country' => 'AR', 'role' => 'Support'],
                        ['username' => 'heat', 'real_name' => 'João Cortez', 'country' => 'BR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Leviatán',
                    'coach' => 'onur',
                    'players' => [
                        ['username' => 'kiNgg', 'real_name' => 'Francisco Aravena', 'country' => 'CL', 'role' => 'Tank'],
                        ['username' => 'Mazino', 'real_name' => 'Roberto Rivas', 'country' => 'CL', 'role' => 'Tank'],
                        ['username' => 'aspas', 'real_name' => 'Erick Santos', 'country' => 'BR', 'role' => 'Duelist'],
                        ['username' => 'tex', 'real_name' => 'Ian Botsch', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'C0M', 'real_name' => 'Corbin Lee', 'country' => 'US', 'role' => 'Support'],
                        ['username' => 'nataNk', 'real_name' => 'Juan Pablo López', 'country' => 'AR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'KRÜ Esports',
                    'coach' => 'atom',
                    'players' => [
                        ['username' => 'keznit', 'real_name' => 'Angelo Mori', 'country' => 'CL', 'role' => 'Duelist'],
                        ['username' => 'shyy', 'real_name' => 'Fabian Usnayo', 'country' => 'CL', 'role' => 'Duelist'],
                        ['username' => 'Klaus', 'real_name' => 'Nicolas Ferrari', 'country' => 'AR', 'role' => 'Tank'],
                        ['username' => 'mta', 'real_name' => 'Nicolas Gonzalez', 'country' => 'AR', 'role' => 'Tank'],
                        ['username' => 'Melser', 'real_name' => 'Marco Amaro', 'country' => 'CL', 'role' => 'Support'],
                        ['username' => 'nzr', 'real_name' => 'Agustin Ibarra', 'country' => 'AR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'MIBR',
                    'coach' => 'fRoD',
                    'players' => [
                        ['username' => 'artzin', 'real_name' => 'Arthur Dias', 'country' => 'BR', 'role' => 'Duelist'],
                        ['username' => 'cortezia', 'real_name' => 'Gabriel Cortez', 'country' => 'BR', 'role' => 'Duelist'],
                        ['username' => 'jzz', 'real_name' => 'João Pedro', 'country' => 'BR', 'role' => 'Tank'],
                        ['username' => 'frz', 'real_name' => 'Leandro Gomes', 'country' => 'BR', 'role' => 'Tank'],
                        ['username' => 'mazin', 'real_name' => 'Matheus Araújo', 'country' => 'BR', 'role' => 'Support'],
                        ['username' => 'kon4n', 'real_name' => 'Vitor Hugo', 'country' => 'BR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => '100 Thieves',
                    'coach' => 'Mikes',
                    'players' => [
                        ['username' => 'Cryo', 'real_name' => 'Dylan Cade', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'eeiu', 'real_name' => 'Daniel Vucenovic', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'Boostio', 'real_name' => 'Kelden Pupello', 'country' => 'US', 'role' => 'Tank'],
                        ['username' => 'Asuna', 'real_name' => 'Peter Mazuryk', 'country' => 'US', 'role' => 'Tank'],
                        ['username' => 'bang', 'real_name' => 'Sean Bezerra', 'country' => 'US', 'role' => 'Support'],
                        ['username' => 'zander', 'real_name' => 'Alexander Dituri', 'country' => 'US', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'G2 Esports',
                    'coach' => 'JoshRT',
                    'players' => [
                        ['username' => 'icy', 'real_name' => 'Jacob Lange', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'leaf', 'real_name' => 'Nathan Orf', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'JonahP', 'real_name' => 'Jonah Pulice', 'country' => 'US', 'role' => 'Tank'],
                        ['username' => 'trent', 'real_name' => 'Trent Cairns', 'country' => 'US', 'role' => 'Tank'],
                        ['username' => 'valyn', 'real_name' => 'Jacob Batio', 'country' => 'US', 'role' => 'Support'],
                        ['username' => 'Zellsis', 'real_name' => 'Jordan Montemurro', 'country' => 'US', 'role' => 'Support']
                    ]
                ]
            ]
        ],
        // Oceania - MR Ignite 2025 Stage 1
        [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Oceania',
            'region' => 'OCE',
            'teams' => [
                [
                    'name' => 'Chiefs Esports Club',
                    'coach' => 'Swarez',
                    'players' => [
                        ['username' => 'aliikai', 'real_name' => 'Liam Fitz-Halpin', 'country' => 'AU', 'role' => 'Duelist'],
                        ['username' => 'autumn', 'real_name' => 'Seth French', 'country' => 'AU', 'role' => 'Duelist'],
                        ['username' => 'LONS', 'real_name' => 'Luke Sinclair', 'country' => 'AU', 'role' => 'Tank'],
                        ['username' => 'Pzza', 'real_name' => 'Aaron Macca', 'country' => 'AU', 'role' => 'Tank'],
                        ['username' => 'Rdeew', 'real_name' => 'Reed Wattley', 'country' => 'AU', 'role' => 'Support'],
                        ['username' => 'Maple', 'real_name' => 'Riley Liddle', 'country' => 'AU', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Mindfreak',
                    'coach' => 'Nozz',
                    'players' => [
                        ['username' => 'pl1xx', 'real_name' => 'Zavier Long', 'country' => 'AU', 'role' => 'Duelist'],
                        ['username' => 'minimise', 'real_name' => 'Mitchell Shaw', 'country' => 'AU', 'role' => 'Duelist'],
                        ['username' => 'WRONSKI', 'real_name' => 'Ricky Wronski', 'country' => 'AU', 'role' => 'Tank'],
                        ['username' => 'sScary', 'real_name' => 'Jason Susanto', 'country' => 'ID', 'role' => 'Tank'],
                        ['username' => 'Crunchy', 'real_name' => 'Jake van Haaren', 'country' => 'AU', 'role' => 'Support'],
                        ['username' => 'Guac', 'real_name' => 'Xavier Pham', 'country' => 'AU', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'ORDER',
                    'coach' => 'MC',
                    'players' => [
                        ['username' => 'Texta', 'real_name' => 'Matthew O\'Rourke', 'country' => 'NZ', 'role' => 'Duelist'],
                        ['username' => 'disk', 'real_name' => 'Tim Taylor', 'country' => 'AU', 'role' => 'Duelist'],
                        ['username' => 'tank', 'real_name' => 'Travis Regan', 'country' => 'AU', 'role' => 'Tank'],
                        ['username' => 'Patiphan', 'real_name' => 'Patiphan Chaiwong', 'country' => 'TH', 'role' => 'Tank'],
                        ['username' => 'Lucid', 'real_name' => 'Jake Foster', 'country' => 'AU', 'role' => 'Support'],
                        ['username' => 'Win98', 'real_name' => 'Jay Won', 'country' => 'AU', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Bonkers',
                    'coach' => 'Feath5r',
                    'players' => [
                        ['username' => 'Shiba', 'real_name' => 'Luke Stewart', 'country' => 'AU', 'role' => 'Duelist'],
                        ['username' => 'SliX', 'real_name' => 'Ethan Li', 'country' => 'AU', 'role' => 'Duelist'],
                        ['username' => 'leachy', 'real_name' => 'Lachlan Cooke', 'country' => 'AU', 'role' => 'Tank'],
                        ['username' => 'Krystal', 'real_name' => 'Ben Stokes', 'country' => 'AU', 'role' => 'Tank'],
                        ['username' => 'Mocking', 'real_name' => 'Tom Kersten', 'country' => 'AU', 'role' => 'Support'],
                        ['username' => 'Vulkan', 'real_name' => 'Felix Rankin', 'country' => 'AU', 'role' => 'Support']
                    ]
                ]
            ]
        ]
    ];

    public function import()
    {
        DB::beginTransaction();

        try {
            $totalTeams = 0;
            $totalPlayers = 0;

            foreach ($this->tournaments as $tournament) {
                echo "\nImporting tournament: {$tournament['name']}\n";
                echo "Region: {$tournament['region']}\n";
                echo "Teams: " . count($tournament['teams']) . "\n";
                echo str_repeat("=", 50) . "\n";

                foreach ($tournament['teams'] as $teamData) {
                    // Create team
                    $shortName = $this->generateShortName($teamData['name']);
                    $team = Team::create([
                        'name' => $teamData['name'],
                        'short_name' => $shortName,
                        'slug' => \Illuminate\Support\Str::slug($teamData['name']),
                        'region' => $tournament['region'],
                        'country' => $this->getTeamCountry($teamData['players']),
                        'country_code' => $this->getTeamCountry($teamData['players']),
                        'flag' => $this->getTeamCountry($teamData['players']),
                        'country_flag' => $this->getTeamCountry($teamData['players']),
                        'status' => 'active',
                        'wins' => 0,
                        'losses' => 0,
                        'rating' => 1000,
                        'elo_rating' => 1000,
                        'coach' => $teamData['coach'],
                        'platform' => 'PC',
                        'game' => 'marvel_rivals',
                        'division' => 'Professional',
                        'player_count' => 6,
                        'ranking' => 0,
                        'rank' => 0,
                        'win_rate' => 0,
                        'map_win_rate' => 0,
                        'points' => 0,
                        'record' => '0-0',
                        'tournaments_won' => 0,
                        'peak' => 1000,
                        'streak' => 0,
                        'earnings' => 0,
                        'founded' => null,
                        'captain' => null,
                        'manager' => null
                    ]);

                    echo "\n✓ Created team: {$team->name}\n";
                    $totalTeams++;

                    // Create players
                    foreach ($teamData['players'] as $playerData) {
                        $player = Player::create([
                            'username' => $playerData['username'],
                            'name' => $playerData['username'],
                            'real_name' => $playerData['real_name'],
                            'country' => $playerData['country'],
                            'country_code' => $playerData['country'],
                            'country_flag' => $playerData['country'],
                            'team_id' => $team->id,
                            'role' => $playerData['role'],
                            'status' => 'active',
                            'earnings' => 0,
                            'rating' => 1000,
                            'rank' => 0,
                            'peak_rating' => 1000,
                            'region' => $tournament['region'],
                            'age' => null,
                            'total_matches' => 0,
                            'tournaments_played' => 0,
                            'main_hero' => $this->getMainHeroForRole($playerData['role']),
                            'skill_rating' => 0,
                            'position_order' => 0
                        ]);

                        // Create player team history
                        PlayerTeamHistory::create([
                            'player_id' => $player->id,
                            'team_id' => $team->id,
                            'joined_at' => now(),
                            'change_date' => now(),
                            'change_type' => 'joined',
                            'is_current' => true
                        ]);

                        echo "  - {$player->username} ({$player->real_name}) - {$player->role} [{$player->country}]\n";
                        $totalPlayers++;
                    }
                }
            }

            DB::commit();
            
            echo "\n" . str_repeat("=", 60) . "\n";
            echo "IMPORT COMPLETED SUCCESSFULLY!\n";
            echo str_repeat("=", 60) . "\n";
            echo "Total teams imported: $totalTeams\n";
            echo "Total players imported: $totalPlayers\n";
            echo "\nBreakdown by region:\n";
            
            // Show breakdown by region
            $regions = Team::select('region', DB::raw('COUNT(*) as count'))
                ->groupBy('region')
                ->get();
                
            foreach ($regions as $region) {
                $playerCount = Player::where('region', $region->region)->count();
                echo "- {$region->region}: {$region->count} teams, $playerCount players\n";
            }

        } catch (\Exception $e) {
            DB::rollBack();
            echo "\nError: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function getTeamCountry($players)
    {
        // Determine team country based on majority of players
        $countries = array_column($players, 'country');
        $countryCount = array_count_values($countries);
        arsort($countryCount);
        return array_key_first($countryCount) ?? 'WORLD';
    }

    private function generateShortName($teamName)
    {
        // Generate short name from team name
        $shortNames = [
            // NA
            'Luminosity Gaming' => 'LG',
            'NRG Esports' => 'NRG',
            'TSM' => 'TSM',
            'Oxygen Esports' => 'OXG',
            'Cloud9' => 'C9',
            'Evil Geniuses' => 'EG',
            'FaZe Clan' => 'FAZE',
            'Toronto Defiant' => 'TD',
            // EMEA
            'Fnatic' => 'FNC',
            'Team Liquid' => 'TL',
            'NAVI' => 'NAVI',
            'Team Vitality' => 'VIT',
            'Karmine Corp' => 'KC',
            'Giants Gaming' => 'GIA',
            'FUT Esports' => 'FUT',
            'Team BDS' => 'BDS',
            // Asia
            'DRX' => 'DRX',
            'T1' => 'T1',
            'Gen.G' => 'GEN',
            'Paper Rex' => 'PRX',
            'EDward Gaming' => 'EDG',
            'Zeta Division' => 'ZETA',
            // Americas
            'LOUD' => 'LOUD',
            'Sentinels' => 'SEN',
            'FURIA' => 'FUR',
            'Leviatán' => 'LEV',
            'KRÜ Esports' => 'KRU',
            'MIBR' => 'MIBR',
            '100 Thieves' => '100T',
            'G2 Esports' => 'G2',
            // OCE
            'Chiefs Esports Club' => 'CHF',
            'Mindfreak' => 'MF',
            'ORDER' => 'ORD',
            'Bonkers' => 'BKR'
        ];

        if (isset($shortNames[$teamName])) {
            return $shortNames[$teamName];
        }

        // Generate from first letters
        $words = explode(' ', $teamName);
        $short = '';
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $short .= strtoupper(substr($word, 0, 1));
            }
        }
        return $short ?: strtoupper(substr($teamName, 0, 3));
    }

    private function getMainHeroForRole($role)
    {
        $heroMap = [
            'Duelist' => 'spider-man',
            'Tank' => 'hulk',
            'Support' => 'luna-snow'
        ];

        return $heroMap[$role] ?? 'spider-man';
    }
}

// Run the importer
$importer = new AllRegionsImporter();
$importer->import();