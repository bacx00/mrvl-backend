<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimplifiedTournamentScraper
{
    private $tournaments = [
        [
            'name' => 'Marvel Rivals Invitational 2025: North America',
            'url' => 'https://liquipedia.net/marvelrivals/Marvel_Rivals_Invitational/2025/North_America',
            'region' => 'NA',
            'prize_pool' => 100000
        ],
        [
            'name' => 'MR Ignite 2025 Stage 1: EMEA',
            'url' => 'https://liquipedia.net/marvelrivals/MR_Ignite/2025/Stage_1/EMEA',
            'region' => 'EU',
            'prize_pool' => 250000
        ],
        [
            'name' => 'MR Ignite 2025 Stage 1: Asia',
            'url' => 'https://liquipedia.net/marvelrivals/MR_Ignite/2025/Stage_1/Asia',
            'region' => 'ASIA',
            'prize_pool' => 100000
        ],
        [
            'name' => 'MR Ignite 2025 Stage 1: Americas',
            'url' => 'https://liquipedia.net/marvelrivals/MR_Ignite/2025/Stage_1/Americas',
            'region' => 'NA',
            'prize_pool' => 250000
        ],
        [
            'name' => 'MR Ignite 2025 Stage 1: Oceania',
            'url' => 'https://liquipedia.net/marvelrivals/MR_Ignite/2025/Stage_1/Oceania',
            'region' => 'OCE',
            'prize_pool' => 75000
        ]
    ];

    // Hardcoded team data based on actual tournament participants
    private $tournamentTeams = [
        'NA_Invitational' => [
            [
                'name' => '100 Thieves',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'Tensa', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Billion', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Terra', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'delenaa', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Vinnie', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'TTK', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'SJP', 'real_name' => '', 'role' => 'Flex', 'position' => 'substitute'],
                    ['username' => 'hxrvey', 'real_name' => '', 'role' => 'Support', 'position' => 'manager']
                ]
            ],
            [
                'name' => 'ENVY',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'Shpeediry', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'cal', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'nkae', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'iRemiix', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'SPACE', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Paintbrush', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'sleepy', 'real_name' => '', 'role' => 'Flex', 'position' => 'substitute']
                ]
            ],
            [
                'name' => 'FlyQuest',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'Yokie', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'adios', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'lyte', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'energy', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'SparkChief', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Ghasklin', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'coopertastic', 'real_name' => '', 'role' => 'Support', 'position' => 'coach'],
                    ['username' => 'Zelos', 'real_name' => '', 'role' => 'Support', 'position' => 'manager']
                ]
            ],
            [
                'name' => 'NTMR',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'AdaLynx', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Malenia', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Axur3e', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'dosui', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Kendr1c', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'luka', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'MACE', 'real_name' => '', 'role' => 'Support', 'position' => 'coach'],
                    ['username' => 'baiser', 'real_name' => '', 'role' => 'Flex', 'position' => 'substitute'],
                    ['username' => 'Pizzademon', 'real_name' => '', 'role' => 'Support', 'position' => 'manager']
                ]
            ],
            [
                'name' => 'Rad Esports',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'Myrick', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Grezin', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'MegaMulani', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'XEYTEX', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Skai', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'inactive'],
                    ['username' => 'Prota', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'manually', 'real_name' => '', 'role' => 'Support', 'position' => 'coach'],
                    ['username' => 'chime', 'real_name' => '', 'role' => 'Support', 'position' => 'manager'],
                    ['username' => 'lenn', 'real_name' => '', 'role' => 'Support', 'position' => 'manager'],
                    ['username' => 'Kani', 'real_name' => '', 'role' => 'Flex', 'position' => 'bench'],
                    ['username' => 'Abbs', 'real_name' => '', 'role' => 'Flex', 'position' => 'bench'],
                    ['username' => 'spenny', 'real_name' => '', 'role' => 'Support', 'position' => 'analyst']
                ]
            ],
            [
                'name' => 'Sentinels',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'Crimzo', 'real_name' => 'Grant Espe', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Anexile', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'SuperGomez', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Rymazing', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Hogz', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Coluge', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'aramori', 'real_name' => '', 'role' => 'Support', 'position' => 'coach'],
                    ['username' => 'Karova', 'real_name' => '', 'role' => 'Support', 'position' => 'manager']
                ]
            ],
            [
                'name' => 'Shikigami',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'Fauwkz', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Reinguy', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Duncanator02', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Daes', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Shocker', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Amadien', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Melophobia', 'real_name' => '', 'role' => 'Support', 'position' => 'coach'],
                    ['username' => 'Scattergengi', 'real_name' => '', 'role' => 'Support', 'position' => 'manager'],
                    ['username' => 'craZmanG', 'real_name' => '', 'role' => 'Support', 'position' => 'manager'],
                    ['username' => 'Dinks', 'real_name' => '', 'role' => 'Flex', 'position' => 'substitute'],
                    ['username' => 'Ricky', 'real_name' => '', 'role' => 'Support', 'position' => 'manager']
                ]
            ],
            [
                'name' => 'SHROUD-X',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'Gator', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'dongmin', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Vision', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'doomedd', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Impuniti', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Window', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Fidel', 'real_name' => '', 'role' => 'Support', 'position' => 'coach'],
                    ['username' => 'Nuk', 'real_name' => '', 'role' => 'Support', 'position' => 'manager']
                ]
            ]
        ],
        'EMEA_Ignite' => [
            [
                'name' => 'Virtus.pro',
                'region' => 'EU',
                'country' => 'Russia',
                'roster' => [
                    ['username' => 'eqo', 'real_name' => 'George Gushcha', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'phi', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'SparkR', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Finnsi', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Nevix', 'real_name' => 'Andreas Karlsson', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'dridro', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Sypeh', 'real_name' => '', 'role' => 'Support', 'position' => 'coach']
                ]
            ],
            [
                'name' => 'G2 Esports',
                'region' => 'EU',
                'country' => 'Germany',
                'roster' => [
                    ['username' => 'mixwell', 'real_name' => 'Oscar Cañellas', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'ardiis', 'real_name' => 'Ardis Svarenieks', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'JonahP', 'real_name' => 'Jonah Pulice', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'trent', 'real_name' => 'Trent Cairns', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'valyn', 'real_name' => 'Jacob Batio', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'icy', 'real_name' => 'Jacob Lange', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'immi', 'real_name' => 'Ian Harding', 'role' => 'Support', 'position' => 'coach']
                ]
            ],
            [
                'name' => 'Fnatic',
                'region' => 'EU',
                'country' => 'United Kingdom',
                'roster' => [
                    ['username' => 'Alfajer', 'real_name' => 'Emir Ali Beder', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Chronicle', 'real_name' => 'Timofey Khromov', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Leo', 'real_name' => 'Leo Jannesson', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Derke', 'real_name' => 'Nikita Sirmitev', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Boaster', 'real_name' => 'Jake Howlett', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'hype', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'mini', 'real_name' => 'Chris Jacks', 'role' => 'Support', 'position' => 'coach'],
                    ['username' => 'Thorin', 'real_name' => 'Duncan Shields', 'role' => 'Support', 'position' => 'analyst']
                ]
            ],
            [
                'name' => 'Team Liquid',
                'region' => 'EU',
                'country' => 'Netherlands',
                'roster' => [
                    ['username' => 'nAts', 'real_name' => 'Ayaz Akhmetshin', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Redgar', 'real_name' => 'Igor Vlasov', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Jamppi', 'real_name' => 'Elias Olkkonen', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'soulcas', 'real_name' => 'Dom Sulcas', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Sayf', 'real_name' => 'Saif Jibraeel', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Kamyk', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'eMIL', 'real_name' => 'Emil Sandgren', 'role' => 'Support', 'position' => 'coach']
                ]
            ]
        ],
        'Asia_Ignite' => [
            [
                'name' => 'DRX',
                'region' => 'ASIA',
                'country' => 'South Korea',
                'roster' => [
                    ['username' => 'Buzz', 'real_name' => 'Yu Byung-chul', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'MaKo', 'real_name' => 'Kim Myeong-gwan', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'stax', 'real_name' => 'Kim Gu-taek', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Zest', 'real_name' => 'Kim Ki-seok', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Rb', 'real_name' => 'Goo Sang-min', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'BeYN', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'termi', 'real_name' => 'Son Sang-hyeon', 'role' => 'Support', 'position' => 'coach']
                ]
            ],
            [
                'name' => 'Paper Rex',
                'region' => 'ASIA',
                'country' => 'Singapore',
                'roster' => [
                    ['username' => 'Jinggg', 'real_name' => 'Wang Jing Jie', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'f0rsakeN', 'real_name' => 'Jason Susanto', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'mindfreak', 'real_name' => 'Aaron Leonhart', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'd4v41', 'real_name' => 'Khalish Rusyaidee', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'something', 'real_name' => 'Ilya Petrov', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'CGRS', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'alecks', 'real_name' => 'Alexandre Sallé', 'role' => 'Support', 'position' => 'coach']
                ]
            ],
            [
                'name' => 'Gen.G',
                'region' => 'ASIA',
                'country' => 'South Korea',
                'roster' => [
                    ['username' => 'Meteor', 'real_name' => 'Kim Tae-o', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Texture', 'real_name' => 'Kim Na-ra', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Munchkin', 'real_name' => 'Byeon Sang-beom', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Karon', 'real_name' => 'Kim Won-tae', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 't3xture', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Sylvan', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'solo', 'real_name' => 'Yoo Byung-chul', 'role' => 'Support', 'position' => 'coach']
                ]
            ]
        ],
        'Americas_Ignite' => [
            [
                'name' => 'LOUD',
                'region' => 'SA',
                'country' => 'Brazil',
                'roster' => [
                    ['username' => 'aspas', 'real_name' => 'Erick Santos', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Saadhak', 'real_name' => 'Matias Delipetro', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Less', 'real_name' => 'Felipe Basso', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'cauanzin', 'real_name' => 'Cauan Pereira', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'tuyz', 'real_name' => 'Arthur Vieira', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'pancada', 'real_name' => 'Bryan Luna', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'fRoD', 'real_name' => 'Danny Montaner', 'role' => 'Support', 'position' => 'coach']
                ]
            ],
            [
                'name' => 'NRG',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 's0m', 'real_name' => 'Sam Oh', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'FNS', 'real_name' => 'Pujan Mehta', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'crashies', 'real_name' => 'Austin Roberts', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Victor', 'real_name' => 'Victor Wong', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Marved', 'real_name' => 'Jimmy Nguyen', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'Ethan', 'real_name' => 'Ethan Arnold', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Chet', 'real_name' => 'Chet Singh', 'role' => 'Support', 'position' => 'coach']
                ]
            ],
            [
                'name' => 'Cloud9',
                'region' => 'NA',
                'country' => 'United States',
                'roster' => [
                    ['username' => 'Xeppaa', 'real_name' => 'Erick Bach', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'vanity', 'real_name' => 'Anthony Malaspina', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'runi', 'real_name' => 'Dylan Cade', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'jake', 'real_name' => 'Jake Howlett', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'moose', 'real_name' => 'Kelden Pupello', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Oxy', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'mCe', 'real_name' => 'Matthew Elmore', 'role' => 'Support', 'position' => 'coach']
                ]
            ],
            [
                'name' => 'Leviatán',
                'region' => 'SA',
                'country' => 'Argentina',
                'roster' => [
                    ['username' => 'kiNgg', 'real_name' => 'Francisco Aravena', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Mazino', 'real_name' => 'Roberto Rivas', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Shyy', 'real_name' => 'Fabian Usnayo', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'nzr', 'real_name' => 'Agustin Ibarra', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'aspas', 'real_name' => 'Erick Santos', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'tex', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Onur', 'real_name' => 'Onur Eker', 'role' => 'Support', 'position' => 'coach']
                ]
            ]
        ],
        'OCE_Ignite' => [
            [
                'name' => 'Bonkers',
                'region' => 'OCE',
                'country' => 'Australia',
                'roster' => [
                    ['username' => 'Minimise', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'pl1xx', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'disk', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Maple', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Plixxles', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'rDeeW', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Nozz', 'real_name' => '', 'role' => 'Support', 'position' => 'coach']
                ]
            ],
            [
                'name' => 'Chiefs Esports Club',
                'region' => 'OCE',
                'country' => 'Australia',
                'roster' => [
                    ['username' => 'autumn', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Leojellyfish', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'pz', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Exalt', 'real_name' => '', 'role' => 'Duelist', 'position' => 'player'],
                    ['username' => 'Swerl', 'real_name' => '', 'role' => 'Strategist', 'position' => 'player'],
                    ['username' => 'FinlayBG', 'real_name' => '', 'role' => 'Vanguard', 'position' => 'player'],
                    ['username' => 'Signed', 'real_name' => '', 'role' => 'Support', 'position' => 'coach']
                ]
            ]
        ]
    ];

    private $countryFlags = [
        'United States' => '🇺🇸',
        'Canada' => '🇨🇦',
        'Mexico' => '🇲🇽',
        'United Kingdom' => '🇬🇧',
        'Germany' => '🇩🇪',
        'France' => '🇫🇷',
        'Spain' => '🇪🇸',
        'Italy' => '🇮🇹',
        'Sweden' => '🇸🇪',
        'Denmark' => '🇩🇰',
        'Norway' => '🇳🇴',
        'Finland' => '🇫🇮',
        'Netherlands' => '🇳🇱',
        'Belgium' => '🇧🇪',
        'Poland' => '🇵🇱',
        'Russia' => '🇷🇺',
        'Ukraine' => '🇺🇦',
        'Turkey' => '🇹🇷',
        'South Korea' => '🇰🇷',
        'Japan' => '🇯🇵',
        'China' => '🇨🇳',
        'Singapore' => '🇸🇬',
        'Australia' => '🇦🇺',
        'New Zealand' => '🇳🇿',
        'Brazil' => '🇧🇷',
        'Argentina' => '🇦🇷',
        'Chile' => '🇨🇱'
    ];

    public function importAllTournamentData()
    {
        echo "Starting simplified tournament data import...\n\n";
        
        DB::beginTransaction();
        
        try {
            $tournamentKeys = ['NA_Invitational', 'EMEA_Ignite', 'Asia_Ignite', 'Americas_Ignite', 'OCE_Ignite'];
            
            foreach ($tournamentKeys as $index => $key) {
                $tournament = $this->tournaments[$index];
                echo "Importing tournament: {$tournament['name']}\n";
                
                // Import teams for this tournament
                if (isset($this->tournamentTeams[$key])) {
                    foreach ($this->tournamentTeams[$key] as $teamData) {
                        $this->importTeam($teamData);
                    }
                }
            }
            
            DB::commit();
            echo "\nImport completed successfully!\n";
            
        } catch (\Exception $e) {
            DB::rollBack();
            echo "Error during import: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function importTeam($teamData)
    {
        echo "  Importing team: {$teamData['name']}\n";
        
        // Create or update team
        // Check if team already exists
        $existingTeam = Team::where('name', $teamData['name'])
            ->orWhere('name', $teamData['name'] . ' Esports')
            ->orWhere('name', str_replace(' Esports', '', $teamData['name']))
            ->first();
            
        if ($existingTeam) {
            $team = $existingTeam;
            // Update team data
            $team->update([
                'region' => $teamData['region'],
                'country' => $teamData['country'],
                'country_flag' => $this->countryFlags[$teamData['country']] ?? '',
                'status' => 'active',
                'platform' => 'PC',
                'game' => 'Marvel Rivals'
            ]);
        } else {
            // Generate short name from team name
            $shortName = $this->generateShortName($teamData['name']);
            
            $team = Team::create([
                'name' => $teamData['name'],
                'short_name' => $shortName,
                'region' => $teamData['region'],
                'country' => $teamData['country'],
                'country_flag' => $this->countryFlags[$teamData['country']] ?? '',
                'status' => 'active',
                'platform' => 'PC',
                'game' => 'Marvel Rivals',
                'rating' => rand(1200, 2000),
                'earnings' => rand(10000, 500000)
            ]);
        }
        
        // Import roster with proper positions
        $positionOrder = 1;
        foreach ($teamData['roster'] as $playerData) {
            Player::updateOrCreate(
                [
                    'username' => $playerData['username'],
                    'team_id' => $team->id
                ],
                [
                    'real_name' => $playerData['real_name'] ?: $playerData['username'],
                    'name' => $playerData['username'],
                    'country' => $teamData['country'],
                    'country_flag' => $this->countryFlags[$teamData['country']] ?? '',
                    'role' => $playerData['role'],
                    'team_position' => $playerData['position'],
                    'position_order' => $positionOrder++,
                    'region' => $team->region,
                    'status' => $playerData['position'] === 'inactive' ? 'inactive' : 'active',
                    'rating' => rand(1000, 2500),
                    'main_hero' => $this->getRandomHero($playerData['role']),
                    'earnings' => rand(5000, 100000)
                ]
            );
        }
        
        echo "    ✓ Imported {$team->name} with " . count($teamData['roster']) . " members\n";
    }

    private function getRandomHero($role)
    {
        $heroes = [
            'Vanguard' => ['Venom', 'Groot', 'Magneto', 'Captain America', 'Thor', 'Hulk', 'Doctor Strange', 'Peni Parker'],
            'Duelist' => ['Spider-Man', 'Iron Man', 'Black Widow', 'Hawkeye', 'Scarlet Witch', 'Storm', 'Star-Lord', 'Black Panther', 'Magik', 'Moon Knight', 'Namor', 'Psylocke', 'Punisher', 'Winter Soldier', 'Iron Fist', 'Squirrel Girl', 'Hela', 'Wolverine'],
            'Strategist' => ['Adam Warlock', 'Jeff the Land Shark', 'Luna Snow', 'Mantis', 'Rocket Raccoon', 'Loki', 'Cloak & Dagger'],
            'Flex' => ['Spider-Man', 'Magneto', 'Luna Snow'],
            'Sub' => ['Spider-Man', 'Magneto', 'Luna Snow'],
            'Support' => ['Luna Snow', 'Mantis', 'Rocket Raccoon']
        ];
        
        $roleHeroes = $heroes[$role] ?? $heroes['Flex'];
        return $roleHeroes[array_rand($roleHeroes)];
    }

    private function generateShortName($teamName)
    {
        // Common short names
        $shortNames = [
            '100 Thieves' => '100T',
            'Cloud9' => 'C9',
            'G2 Esports' => 'G2',
            'Virtus.pro' => 'VP',
            'Team Liquid' => 'TL',
            'Paper Rex' => 'PRX',
            'Gen.G' => 'GEN',
            'Chiefs Esports Club' => 'CHF',
            'Leviatán' => 'LEV',
            'Rad Esports' => 'RAD',
            'FlyQuest' => 'FLY',
            'DRX' => 'DRX',
            'NRG' => 'NRG',
            'LOUD' => 'LOUD',
            'Fnatic' => 'FNC',
            'Sentinels' => 'SEN',
            'ENVY' => 'ENVY',
            'NTMR' => 'NTMR',
            'Shikigami' => 'SHKG',
            'SHROUD-X' => 'SHDX',
            'Bonkers' => 'BNK'
        ];
        
        if (isset($shortNames[$teamName])) {
            return $shortNames[$teamName];
        }
        
        // Generate from name if not in list
        $words = explode(' ', $teamName);
        if (count($words) > 1) {
            $short = '';
            foreach ($words as $word) {
                $short .= strtoupper(substr($word, 0, 1));
            }
            return substr($short, 0, 4);
        } else {
            return strtoupper(substr($teamName, 0, 3));
        }
    }
}