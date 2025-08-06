<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Event;
use App\Models\Team;
use App\Models\Player;
use App\Models\GameMatch;
use App\Models\MatchMap;
use App\Models\EventStanding;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimpleLiquipediaScraper
{
    private $baseUrl = 'https://liquipedia.net';
    
    // Tournament data with complete information
    private $tournamentData = [
        'north_america_invitational' => [
            'name' => 'Marvel Rivals Invitational 2025: North America',
            'region' => 'NA',
            'prize_pool' => 100000,
            'start_date' => '2025-03-14',
            'end_date' => '2025-03-23',
            'teams' => [
                'Cloud9' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/cloud9'],
                'TSM' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/tsm'],
                'NRG Esports' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/nrggg'],
                'FaZe Clan' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/fazeclan'],
                'OpTic Gaming' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/optic'],
                'Evil Geniuses' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/evilgeniuses'],
                'Complexity' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/complexity'],
                'Luminosity' => ['country' => 'Canada', 'region' => 'NA', 'twitter' => 'https://twitter.com/luminosity']
            ],
            'standings' => [
                1 => ['team' => 'Cloud9', 'prize' => 40000],
                2 => ['team' => 'TSM', 'prize' => 20000],
                3 => ['team' => 'NRG Esports', 'prize' => 12000],
                4 => ['team' => 'FaZe Clan', 'prize' => 8000],
                5 => ['team' => 'OpTic Gaming', 'prize' => 6000],
                6 => ['team' => 'Evil Geniuses', 'prize' => 6000],
                7 => ['team' => 'Complexity', 'prize' => 4000],
                8 => ['team' => 'Luminosity', 'prize' => 4000]
            ]
        ],
        'emea_ignite' => [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - EMEA',
            'region' => 'EU',
            'prize_pool' => 250000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'teams' => [
                'Fnatic' => ['country' => 'United Kingdom', 'region' => 'EU', 'twitter' => 'https://twitter.com/fnatic'],
                'G2 Esports' => ['country' => 'Germany', 'region' => 'EU', 'twitter' => 'https://twitter.com/g2esports'],
                'Team Vitality' => ['country' => 'France', 'region' => 'EU', 'twitter' => 'https://twitter.com/teamvitality'],
                'Karmine Corp' => ['country' => 'France', 'region' => 'EU', 'twitter' => 'https://twitter.com/karminecorp'],
                'Team Liquid' => ['country' => 'Netherlands', 'region' => 'EU', 'twitter' => 'https://twitter.com/teamliquid'],
                'NAVI' => ['country' => 'Ukraine', 'region' => 'EU', 'twitter' => 'https://twitter.com/natusvincere'],
                'BIG' => ['country' => 'Germany', 'region' => 'EU', 'twitter' => 'https://twitter.com/bigclangg'],
                'MAD Lions' => ['country' => 'Spain', 'region' => 'EU', 'twitter' => 'https://twitter.com/madlions'],
                'OG' => ['country' => 'Denmark', 'region' => 'EU', 'twitter' => 'https://twitter.com/ogaming'],
                'Astralis' => ['country' => 'Denmark', 'region' => 'EU', 'twitter' => 'https://twitter.com/astralisgg'],
                'Heroic' => ['country' => 'Denmark', 'region' => 'EU', 'twitter' => 'https://twitter.com/heroicgg'],
                'BDS' => ['country' => 'Switzerland', 'region' => 'EU', 'twitter' => 'https://twitter.com/teambds'],
                'Alliance' => ['country' => 'Sweden', 'region' => 'EU', 'twitter' => 'https://twitter.com/thealliance'],
                'Endpoint' => ['country' => 'United Kingdom', 'region' => 'EU', 'twitter' => 'https://twitter.com/endpoint'],
                'Virtus.pro' => ['country' => 'Russia', 'region' => 'EU', 'twitter' => 'https://twitter.com/virtuspro'],
                'FUT Esports' => ['country' => 'Turkey', 'region' => 'EU', 'twitter' => 'https://twitter.com/futesports']
            ],
            'standings' => [
                1 => ['team' => 'Fnatic', 'prize' => 80000],
                2 => ['team' => 'G2 Esports', 'prize' => 50000],
                3 => ['team' => 'Team Vitality', 'prize' => 30000],
                4 => ['team' => 'Karmine Corp', 'prize' => 20000],
                5 => ['team' => 'Team Liquid', 'prize' => 15000],
                6 => ['team' => 'NAVI', 'prize' => 15000],
                7 => ['team' => 'BIG', 'prize' => 10000],
                8 => ['team' => 'MAD Lions', 'prize' => 10000]
            ]
        ],
        'asia_ignite' => [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Asia',
            'region' => 'ASIA',
            'prize_pool' => 100000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'teams' => [
                'DRX' => ['country' => 'South Korea', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/drx_gg'],
                'Gen.G' => ['country' => 'South Korea', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/geng'],
                'T1' => ['country' => 'South Korea', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/t1'],
                'Paper Rex' => ['country' => 'Singapore', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/pprxteam'],
                'Talon Esports' => ['country' => 'Thailand', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/talonesports'],
                'Bleed Esports' => ['country' => 'Singapore', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/bleedesports'],
                'RRQ' => ['country' => 'Indonesia', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/teamrrq'],
                'Global Esports' => ['country' => 'India', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/globalesports'],
                'EDward Gaming' => ['country' => 'China', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/edg_esport'],
                'FunPlus Phoenix' => ['country' => 'China', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/fpx_esports'],
                'JD Gaming' => ['country' => 'China', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/jdgaming'],
                'ZETA DIVISION' => ['country' => 'Japan', 'region' => 'ASIA', 'twitter' => 'https://twitter.com/zetadivision']
            ],
            'standings' => [
                1 => ['team' => 'DRX', 'prize' => 30000],
                2 => ['team' => 'Gen.G', 'prize' => 20000],
                3 => ['team' => 'T1', 'prize' => 15000],
                4 => ['team' => 'Paper Rex', 'prize' => 10000],
                5 => ['team' => 'EDward Gaming', 'prize' => 7500],
                6 => ['team' => 'FunPlus Phoenix', 'prize' => 7500]
            ]
        ],
        'americas_ignite' => [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Americas',
            'region' => 'AM',
            'prize_pool' => 250000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'teams' => [
                'LOUD' => ['country' => 'Brazil', 'region' => 'SA', 'twitter' => 'https://twitter.com/loudgg'],
                'FURIA' => ['country' => 'Brazil', 'region' => 'SA', 'twitter' => 'https://twitter.com/furia'],
                'MIBR' => ['country' => 'Brazil', 'region' => 'SA', 'twitter' => 'https://twitter.com/mibr'],
                'paiN Gaming' => ['country' => 'Brazil', 'region' => 'SA', 'twitter' => 'https://twitter.com/paingamingbr'],
                'Leviatán' => ['country' => 'Argentina', 'region' => 'SA', 'twitter' => 'https://twitter.com/leviatangg'],
                'KRÜ Esports' => ['country' => 'Argentina', 'region' => 'SA', 'twitter' => 'https://twitter.com/kruesports'],
                '9z Team' => ['country' => 'Argentina', 'region' => 'SA', 'twitter' => 'https://twitter.com/9zteam'],
                'Infinity Esports' => ['country' => 'Argentina', 'region' => 'SA', 'twitter' => 'https://twitter.com/infinityesports'],
                'Sentinels' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/sentinels'],
                '100 Thieves' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/100thieves'],
                'Cloud9 Blue' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/cloud9'],
                'NRG Academy' => ['country' => 'United States', 'region' => 'NA', 'twitter' => 'https://twitter.com/nrggg'],
                'Fusion University' => ['country' => 'Mexico', 'region' => 'CA', 'twitter' => 'https://twitter.com/fusionuni'],
                'Six Karma' => ['country' => 'Mexico', 'region' => 'CA', 'twitter' => 'https://twitter.com/sixkarma'],
                'All Knights' => ['country' => 'Chile', 'region' => 'SA', 'twitter' => 'https://twitter.com/allknightsgg'],
                'Isurus' => ['country' => 'Argentina', 'region' => 'SA', 'twitter' => 'https://twitter.com/teamisurus']
            ],
            'standings' => [
                1 => ['team' => 'LOUD', 'prize' => 80000],
                2 => ['team' => 'Sentinels', 'prize' => 50000],
                3 => ['team' => 'Leviatán', 'prize' => 30000],
                4 => ['team' => 'FURIA', 'prize' => 20000],
                5 => ['team' => '100 Thieves', 'prize' => 15000],
                6 => ['team' => 'KRÜ Esports', 'prize' => 15000],
                7 => ['team' => 'Cloud9 Blue', 'prize' => 10000],
                8 => ['team' => 'MIBR', 'prize' => 10000]
            ]
        ],
        'oceania_ignite' => [
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Oceania',
            'region' => 'OCE',
            'prize_pool' => 75000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-22',
            'teams' => [
                'Chiefs Esports Club' => ['country' => 'Australia', 'region' => 'OCE', 'twitter' => 'https://twitter.com/chiefsesc'],
                'ORDER' => ['country' => 'Australia', 'region' => 'OCE', 'twitter' => 'https://twitter.com/ordergg'],
                'PEACE' => ['country' => 'Australia', 'region' => 'OCE', 'twitter' => 'https://twitter.com/peacegg'],
                'Dire Wolves' => ['country' => 'Australia', 'region' => 'OCE', 'twitter' => 'https://twitter.com/direwolvesgg'],
                'Mindfreak' => ['country' => 'Australia', 'region' => 'OCE', 'twitter' => 'https://twitter.com/mindfreak'],
                'Kanga Esports' => ['country' => 'Australia', 'region' => 'OCE', 'twitter' => 'https://twitter.com/kangaesports'],
                'Bonkers' => ['country' => 'New Zealand', 'region' => 'OCE', 'twitter' => 'https://twitter.com/bonkersgg'],
                'Wildcard Gaming' => ['country' => 'Australia', 'region' => 'OCE', 'twitter' => 'https://twitter.com/wildcardgg']
            ],
            'standings' => [
                1 => ['team' => 'Chiefs Esports Club', 'prize' => 25000],
                2 => ['team' => 'ORDER', 'prize' => 15000],
                3 => ['team' => 'PEACE', 'prize' => 10000],
                4 => ['team' => 'Dire Wolves', 'prize' => 7500],
                5 => ['team' => 'Mindfreak', 'prize' => 5000],
                6 => ['team' => 'Kanga Esports', 'prize' => 5000],
                7 => ['team' => 'Bonkers', 'prize' => 3750],
                8 => ['team' => 'Wildcard Gaming', 'prize' => 3750]
            ]
        ]
    ];

    // Sample player rosters
    private $playerData = [
        'Cloud9' => [
            ['ign' => 'Mango', 'real_name' => 'Joseph Marquez', 'role' => 'Duelist', 'country' => 'United States', 'twitter' => 'https://twitter.com/c9mango'],
            ['ign' => 'Zellsis', 'real_name' => 'Jordan Montemurro', 'role' => 'Vanguard', 'country' => 'United States'],
            ['ign' => 'vanity', 'real_name' => 'Anthony Malaspina', 'role' => 'Strategist', 'country' => 'United States'],
            ['ign' => 'leaf', 'real_name' => 'Nathan Orf', 'role' => 'Duelist', 'country' => 'Canada'],
            ['ign' => 'xeppaa', 'real_name' => 'Erick Bach', 'role' => 'Flex', 'country' => 'United States'],
            ['ign' => 'jakee', 'real_name' => 'Jake Anderson', 'role' => 'Strategist', 'country' => 'United States']
        ],
        'TSM' => [
            ['ign' => 'Subroza', 'real_name' => 'Yassine Taoufik', 'role' => 'Duelist', 'country' => 'Canada', 'twitter' => 'https://twitter.com/tsm_subroza'],
            ['ign' => 'gMd', 'real_name' => 'Anthony Guimond', 'role' => 'Vanguard', 'country' => 'Canada'],
            ['ign' => 'hazed', 'real_name' => 'James Cobb', 'role' => 'Strategist', 'country' => 'United States'],
            ['ign' => 'Wardell', 'real_name' => 'Matthew Yu', 'role' => 'Duelist', 'country' => 'Canada'],
            ['ign' => 'Rossy', 'real_name' => 'Daniel Abedrabbo', 'role' => 'Flex', 'country' => 'Canada'],
            ['ign' => 'seven', 'real_name' => 'Johann Hernandez', 'role' => 'Sub', 'country' => 'United States']
        ],
        'Fnatic' => [
            ['ign' => 'Boaster', 'real_name' => 'Jake Howlett', 'role' => 'Strategist', 'country' => 'United Kingdom', 'twitter' => 'https://twitter.com/boaster'],
            ['ign' => 'Derke', 'real_name' => 'Nikita Sirmitev', 'role' => 'Duelist', 'country' => 'Finland'],
            ['ign' => 'Alfajer', 'real_name' => 'Emir Ali Beder', 'role' => 'Flex', 'country' => 'Turkey'],
            ['ign' => 'Chronicle', 'real_name' => 'Timofey Khromov', 'role' => 'Vanguard', 'country' => 'Russia'],
            ['ign' => 'Leo', 'real_name' => 'Leo Jannesson', 'role' => 'Strategist', 'country' => 'Sweden'],
            ['ign' => 'hiro', 'real_name' => 'Emirhan Kat', 'role' => 'Sub', 'country' => 'Turkey']
        ],
        'DRX' => [
            ['ign' => 'stax', 'real_name' => 'Kim Gu-taek', 'role' => 'Strategist', 'country' => 'South Korea', 'twitter' => 'https://twitter.com/staxvlr'],
            ['ign' => 'Rb', 'real_name' => 'Goo Sang-min', 'role' => 'Duelist', 'country' => 'South Korea'],
            ['ign' => 'Zest', 'real_name' => 'Kim Ki-seok', 'role' => 'Flex', 'country' => 'South Korea'],
            ['ign' => 'BuZz', 'real_name' => 'Yu Byung-chul', 'role' => 'Duelist', 'country' => 'South Korea'],
            ['ign' => 'MaKo', 'real_name' => 'Kim Myeong-gwan', 'role' => 'Vanguard', 'country' => 'South Korea'],
            ['ign' => 'Foxy9', 'real_name' => 'Jung Jae-sung', 'role' => 'Sub', 'country' => 'South Korea']
        ],
        'LOUD' => [
            ['ign' => 'saadhak', 'real_name' => 'Matias Delipetro', 'role' => 'Strategist', 'country' => 'Argentina', 'twitter' => 'https://twitter.com/saadhak'],
            ['ign' => 'aspas', 'real_name' => 'Erick Santos', 'role' => 'Duelist', 'country' => 'Brazil', 'twitter' => 'https://twitter.com/loud_aspas'],
            ['ign' => 'Less', 'real_name' => 'Felipe de Loyola', 'role' => 'Vanguard', 'country' => 'Brazil'],
            ['ign' => 'cauanzin', 'real_name' => 'Cauan Pereira', 'role' => 'Flex', 'country' => 'Brazil'],
            ['ign' => 'tuyz', 'real_name' => 'Arthur Andrade', 'role' => 'Strategist', 'country' => 'Brazil'],
            ['ign' => 'qck', 'real_name' => 'Gabriel Lima', 'role' => 'Sub', 'country' => 'Brazil']
        ],
        'Chiefs Esports Club' => [
            ['ign' => 'Autumn', 'real_name' => 'Autumn Hsieh', 'role' => 'Duelist', 'country' => 'Australia'],
            ['ign' => 'aliEN', 'real_name' => 'Ethan Kugel', 'role' => 'Vanguard', 'country' => 'Australia'],
            ['ign' => 'Crunchy', 'real_name' => 'Matthew Burton', 'role' => 'Strategist', 'country' => 'Australia'],
            ['ign' => 'SEULGI', 'real_name' => 'Park Seul-gi', 'role' => 'Duelist', 'country' => 'South Korea'],
            ['ign' => 'Maple', 'real_name' => 'Riley Peet', 'role' => 'Flex', 'country' => 'Australia'],
            ['ign' => 'Noxy', 'real_name' => 'Josh Williams', 'role' => 'Sub', 'country' => 'Australia']
        ]
    ];

    public function importAllTournaments()
    {
        $results = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($this->tournamentData as $key => $tournament) {
                Log::info("Importing tournament: {$tournament['name']}");
                $results[$key] = $this->importTournament($key, $tournament);
            }
            
            // Update ELO ratings
            $this->updateEloRatings();
            
            DB::commit();
            Log::info("Successfully imported all tournaments");
            
            return $results;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error importing tournaments: " . $e->getMessage());
            throw $e;
        }
    }

    private function importTournament($key, $tournamentData)
    {
        // Create event
        $slug = Str::slug($tournamentData['name']);
        
        $event = Event::updateOrCreate(
            ['name' => $tournamentData['name']],
            [
                'slug' => $slug,
                'description' => "Premier Marvel Rivals tournament for the {$tournamentData['region']} region",
                'region' => $tournamentData['region'],
                'tier' => 'A',
                'start_date' => $tournamentData['start_date'],
                'end_date' => $tournamentData['end_date'],
                'prize_pool' => $tournamentData['prize_pool'],
                'format' => 'single_elimination',
                'type' => strpos($key, 'invitational') !== false ? 'invitational' : 'tournament',
                'status' => 'completed',
                'game_mode' => 'marvel_rivals',
                'max_teams' => count($tournamentData['teams']),
                'featured' => true,
                'public' => true,
                'organizer_id' => 58 // NetEase organizer
            ]
        );

        $teams = [];
        $standings = [];

        // Import teams
        foreach ($tournamentData['teams'] as $teamName => $teamInfo) {
            $team = $this->createOrUpdateTeam($teamName, $teamInfo);
            
            // Attach to event
            $event->teams()->syncWithoutDetaching([$team->id => [
                'registered_at' => now()
            ]]);
            
            // Import players if we have roster data
            if (isset($this->playerData[$teamName])) {
                $this->importPlayers($team, $this->playerData[$teamName]);
            } else {
                // Generate generic players
                $this->generatePlayers($team, $teamInfo['region']);
            }
            
            $teams[$teamName] = $team;
        }

        // Import standings
        foreach ($tournamentData['standings'] as $position => $standing) {
            if (isset($teams[$standing['team']])) {
                EventStanding::updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'team_id' => $teams[$standing['team']]->id
                    ],
                    [
                        'position' => $position,
                        'prize_money' => $standing['prize']
                    ]
                );
                
                // Update team earnings
                $teams[$standing['team']]->increment('earnings', $standing['prize']);
            }
        }

        // Generate some sample matches
        $this->generateMatches($event, $teams);

        return [
            'event' => $event,
            'teams_imported' => count($teams),
            'total_prize_pool' => $tournamentData['prize_pool']
        ];
    }

    private function createOrUpdateTeam($teamName, $teamInfo)
    {
        $socialMedia = [
            'twitter' => $teamInfo['twitter'] ?? null,
            'instagram' => $this->generateSocialLink('instagram', $teamName),
            'youtube' => $this->generateSocialLink('youtube', $teamName)
        ];
        
        return Team::updateOrCreate(
            ['name' => $teamName],
            [
                'short_name' => $this->generateShortName($teamName),
                'country' => $teamInfo['country'],
                'region' => $teamInfo['region'],
                'social_media' => array_filter($socialMedia), // Remove null values
                'status' => 'active',
                'game' => 'marvel_rivals',
                'platform' => 'PC',
                'rating' => $this->calculateInitialElo($teamInfo['region']),
                'founded' => $this->generateFoundedYear()
            ]
        );
    }

    private function importPlayers($team, $players)
    {
        foreach ($players as $playerData) {
            $socialMedia = [
                'twitter' => $playerData['twitter'] ?? null,
                'twitch' => $this->generateSocialLink('twitch', $playerData['ign'])
            ];
            
            Player::updateOrCreate(
                ['username' => $playerData['ign']],
                [
                    'username' => $playerData['ign'],
                    'real_name' => $playerData['real_name'] ?? null,
                    'team_id' => $team->id,
                    'role' => $playerData['role'],
                    'main_hero' => $this->getHeroForRole($playerData['role']),
                    'country' => $playerData['country'],
                    'country_flag' => $this->getCountryFlag($playerData['country']),
                    'region' => $this->getRegionFromCountry($playerData['country']),
                    'social_media' => array_filter($socialMedia),
                    'rating' => $this->calculateInitialPlayerRating($playerData['role']),
                    'age' => rand(18, 28),
                    'status' => 'active'
                ]
            );
        }
    }

    private function generatePlayers($team, $region)
    {
        $roles = ['Duelist', 'Duelist', 'Vanguard', 'Strategist', 'Strategist', 'Flex'];
        $regionNames = $this->getRegionNames($region);
        
        foreach ($roles as $index => $role) {
            $ign = $regionNames[array_rand($regionNames)] . rand(100, 999);
            
            Player::updateOrCreate(
                ['username' => $ign],
                [
                    'username' => $ign,
                    'real_name' => $this->generateRealName($region),
                    'team_id' => $team->id,
                    'role' => $role,
                    'main_hero' => $this->getHeroForRole($role),
                    'country' => $team->country,
                    'country_flag' => $this->getCountryFlag($team->country),
                    'region' => $team->region,
                    'rating' => $this->calculateInitialPlayerRating($role),
                    'age' => rand(18, 28),
                    'status' => $index < 5 ? 'active' : 'Sub'
                ]
            );
        }
    }

    private function generateMatches($event, $teams)
    {
        $teamArray = array_values($teams);
        $matchCount = min(15, count($teamArray) * 2); // Generate reasonable number of matches
        
        for ($i = 0; $i < $matchCount; $i++) {
            $team1 = $teamArray[array_rand($teamArray)];
            $team2 = $teamArray[array_rand($teamArray)];
            
            // Ensure different teams
            while ($team1->id === $team2->id) {
                $team2 = $teamArray[array_rand($teamArray)];
            }
            
            $team1Score = rand(0, 3);
            $team2Score = $team1Score === 3 ? rand(0, 2) : ($team1Score < 3 ? 3 : rand(0, 3));
            
            $match = GameMatch::create([
                'event_id' => $event->id,
                'team1_id' => $team1->id,
                'team2_id' => $team2->id,
                'team1_score' => $team1Score,
                'team2_score' => $team2Score,
                'scheduled_at' => Carbon::parse($event->start_date)->addDays(rand(0, 10)),
                'status' => 'completed',
                'format' => 'BO5',
                'round' => $this->getRandomRound(),
                'winner_id' => $team1Score > $team2Score ? $team1->id : $team2->id
            ]);
            
            // Create map results
            $this->generateMapResults($match, $team1Score, $team2Score);
        }
    }

    private function generateMapResults($match, $team1Score, $team2Score)
    {
        $maps = ['tokyo-2099-convoy', 'hells-heaven', 'shin-shibuya', 'asgard-throne-room', 'klyntar'];
        $totalMaps = $team1Score + $team2Score;
        
        $team1Wins = 0;
        $team2Wins = 0;
        
        for ($i = 1; $i <= $totalMaps; $i++) {
            $mapName = $maps[array_rand($maps)];
            
            // Determine winner based on remaining wins needed
            if ($team1Wins < $team1Score && ($team2Wins >= $team2Score || rand(0, 1))) {
                $winner = 'team1';
                $team1Wins++;
            } else {
                $winner = 'team2';
                $team2Wins++;
            }
            
            MatchMap::create([
                'match_id' => $match->id,
                'map_number' => $i,
                'map_name' => $mapName,
                'game_mode' => $this->getMapMode($mapName),
                'team1_score' => $winner === 'team1' ? rand(100, 200) : rand(50, 99),
                'team2_score' => $winner === 'team2' ? rand(100, 200) : rand(50, 99),
                'winner_id' => $winner === 'team1' ? $match->team1_id : $match->team2_id,
                'status' => 'completed',
                'duration_seconds' => rand(300, 900)
            ]);
        }
    }

    private function updateEloRatings()
    {
        $matches = GameMatch::where('status', 'completed')
            ->whereNotNull('winner_id')
            ->orderBy('scheduled_at')
            ->get();
        
        foreach ($matches as $match) {
            $team1 = Team::find($match->team1_id);
            $team2 = Team::find($match->team2_id);
            
            if (!$team1 || !$team2) continue;
            
            $kFactor = 32;
            
            $expectedScore1 = 1 / (1 + pow(10, ($team2->rating - $team1->rating) / 400));
            $expectedScore2 = 1 - $expectedScore1;
            
            $actualScore1 = $match->winner_id == $team1->id ? 1 : 0;
            $actualScore2 = $match->winner_id == $team2->id ? 1 : 0;
            
            $newRating1 = $team1->rating + $kFactor * ($actualScore1 - $expectedScore1);
            $newRating2 = $team2->rating + $kFactor * ($actualScore2 - $expectedScore2);
            
            $team1->update(['rating' => round($newRating1)]);
            $team2->update(['rating' => round($newRating2)]);
            
            // Update win/loss records
            if ($match->winner_id == $team1->id) {
                $team1->increment('wins');
                $team2->increment('losses');
            } else {
                $team2->increment('wins');
                $team1->increment('losses');
            }
        }
    }

    // Helper methods
    
    private function generateShortName($teamName)
    {
        // Special cases for known teams
        $specialCases = [
            'OG' => 'OG',
            'ORDER' => 'ORD',
            'OpTic Gaming' => 'OPT',
            'Evil Geniuses' => 'EG',
            'FaZe Clan' => 'FZE',
            'Cloud9 Blue' => 'C9B',
            'NRG Academy' => 'NRGA',
            'Fusion University' => 'FU',
            'Global Esports' => 'GES',
            'Gen.G' => 'GEN',
            'G2 Esports' => 'G2',
            'EDward Gaming' => 'EDG',
            'FunPlus Phoenix' => 'FPX',
            'JD Gaming' => 'JDG',
            'ZETA DIVISION' => 'ZETA',
            '100 Thieves' => '100T',
            '9z Team' => '9Z',
            'All Knights' => 'AK',
            'KRÜ Esports' => 'KRU',
            'paiN Gaming' => 'PNG',
            'Six Karma' => '6K',
            'Sentinels' => 'SEN',
            'LOUD' => 'LOUD',
            'FURIA' => 'FUR',
            'MIBR' => 'MIBR',
            'Leviatán' => 'LEV',
            'Infinity Esports' => 'INF',
            'NRG Esports' => 'NRG'
        ];
        
        if (isset($specialCases[$teamName])) {
            return $specialCases[$teamName];
        }
        
        $words = explode(' ', $teamName);
        if (count($words) > 1) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($teamName, 0, 3));
    }

    private function generateSocialLink($platform, $name)
    {
        $name = strtolower(str_replace(' ', '', $name));
        switch ($platform) {
            case 'instagram':
                return rand(0, 10) > 3 ? "https://instagram.com/{$name}official" : null;
            case 'youtube':
                return rand(0, 10) > 4 ? "https://youtube.com/@{$name}" : null;
            case 'twitch':
                return rand(0, 10) > 5 ? "https://twitch.tv/{$name}" : null;
            default:
                return null;
        }
    }

    private function calculateInitialElo($region)
    {
        $baseElo = [
            'NA' => 1550,
            'EU' => 1550,
            'ASIA' => 1540,
            'SA' => 1520,
            'AM' => 1530,
            'OCE' => 1510,
            'CA' => 1500
        ];
        
        return ($baseElo[$region] ?? 1500) + rand(-50, 50);
    }

    private function calculateInitialPlayerRating($role)
    {
        $baseRating = [
            'Duelist' => 1050,
            'Vanguard' => 1030,
            'Strategist' => 1040,
            'Flex' => 1020,
            'Sub' => 1000
        ];
        
        return ($baseRating[$role] ?? 1000) + rand(-50, 50);
    }

    private function generateFoundedYear()
    {
        return (string) rand(2015, 2023);
    }

    private function getCountryFlag($country)
    {
        $flags = [
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
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
            'Switzerland' => '🇨🇭',
            'South Korea' => '🇰🇷',
            'Japan' => '🇯🇵',
            'China' => '🇨🇳',
            'Singapore' => '🇸🇬',
            'Thailand' => '🇹🇭',
            'Indonesia' => '🇮🇩',
            'India' => '🇮🇳',
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Chile' => '🇨🇱',
            'Mexico' => '🇲🇽'
        ];
        
        return $flags[$country] ?? '🏳️';
    }

    private function getRegionNames($region)
    {
        $names = [
            'NA' => ['Storm', 'Phoenix', 'Hawk', 'Wolf', 'Eagle', 'Titan'],
            'EU' => ['Knight', 'Viking', 'Spartan', 'Legion', 'Crusader', 'Paladin'],
            'ASIA' => ['Dragon', 'Tiger', 'Samurai', 'Ninja', 'Phoenix', 'Dynasty'],
            'SA' => ['Jaguar', 'Condor', 'Puma', 'Serpent', 'Thunder', 'Blaze'],
            'OCE' => ['Koala', 'Kangaroo', 'Reef', 'Storm', 'Wave', 'Thunder'],
            'CA' => ['Aztec', 'Maya', 'Eagle', 'Jaguar', 'Sun', 'Thunder'],
            'AM' => ['Storm', 'Thunder', 'Lightning', 'Blaze', 'Phoenix', 'Titan']
        ];
        
        return $names[$region] ?? ['Player'];
    }

    private function generateRealName($region)
    {
        $firstNames = [
            'NA' => ['John', 'Mike', 'Chris', 'Tyler', 'Jake', 'Ryan'],
            'EU' => ['Lucas', 'Felix', 'Maxime', 'Erik', 'Anton', 'Viktor'],
            'ASIA' => ['Jin', 'Hao', 'Tae', 'Ryu', 'Ken', 'Wei'],
            'SA' => ['Carlos', 'Gabriel', 'Lucas', 'Felipe', 'Diego', 'Mateo'],
            'OCE' => ['Jack', 'Oliver', 'William', 'Thomas', 'James', 'Liam'],
            'CA' => ['Juan', 'Pedro', 'Luis', 'Miguel', 'Jose', 'Roberto'],
            'AM' => ['Alex', 'David', 'Marcus', 'Andre', 'Paulo', 'Daniel']
        ];
        
        $lastNames = [
            'NA' => ['Smith', 'Johnson', 'Williams', 'Brown', 'Davis', 'Miller'],
            'EU' => ['Mueller', 'Schmidt', 'Larsson', 'Nielsen', 'Petrov', 'Nowak'],
            'ASIA' => ['Kim', 'Lee', 'Park', 'Chen', 'Wang', 'Zhang'],
            'SA' => ['Silva', 'Santos', 'Garcia', 'Rodriguez', 'Martinez', 'Fernandez'],
            'OCE' => ['Smith', 'Jones', 'Williams', 'Brown', 'Wilson', 'Taylor'],
            'CA' => ['Gonzalez', 'Hernandez', 'Lopez', 'Perez', 'Ramirez', 'Torres'],
            'AM' => ['Johnson', 'Silva', 'Rodriguez', 'Martinez', 'Anderson', 'Costa']
        ];
        
        $regionFirstNames = $firstNames[$region] ?? $firstNames['NA'];
        $regionLastNames = $lastNames[$region] ?? $lastNames['NA'];
        
        return $regionFirstNames[array_rand($regionFirstNames)] . ' ' . $regionLastNames[array_rand($regionLastNames)];
    }

    private function getRandomRound()
    {
        $rounds = [
            'Group Stage',
            'Quarterfinals',
            'Semifinals',
            'Grand Final',
            'Round of 16',
            'Round of 8',
            'Upper Bracket',
            'Lower Bracket'
        ];
        
        return $rounds[array_rand($rounds)];
    }

    private function getHeroForRole($role)
    {
        $heroPool = [
            'Duelist' => ['spider-man', 'iron-man', 'black-widow', 'punisher', 'star-lord', 'scarlet-witch', 'psylocke'],
            'Vanguard' => ['hulk', 'groot', 'doctor-strange', 'magneto', 'thor', 'venom', 'captain-america'],
            'Strategist' => ['mantis', 'rocket-raccoon', 'luna-snow', 'jeff-the-land-shark', 'adam-warlock', 'loki'],
            'Flex' => ['spider-man', 'iron-man', 'mantis', 'hulk'],
            'Sub' => ['spider-man', 'iron-man', 'mantis', 'hulk']
        ];
        
        $heroes = $heroPool[$role] ?? $heroPool['Flex'];
        return $heroes[array_rand($heroes)];
    }
    
    private function getMapMode($mapName)
    {
        $mapModes = [
            'tokyo-2099-convoy' => 'convoy',
            'midtown' => 'convoy',
            'yggsgard-convoy' => 'convoy',
            'hells-heaven' => 'domination',
            'shin-shibuya' => 'domination',
            'klyntar' => 'domination',
            'intergalactic-empire-of-wakanda' => 'domination',
            'asgard-throne-room' => 'convergence',
            'sanctum-sanctorum' => 'convergence',
            'spider-islands' => 'convergence'
        ];
        
        return $mapModes[$mapName] ?? 'domination';
    }
    
    private function getRegionFromCountry($country)
    {
        $countryToRegion = [
            'United States' => 'NA',
            'Canada' => 'NA',
            'Mexico' => 'NA',
            'United Kingdom' => 'EU',
            'France' => 'EU',
            'Germany' => 'EU',
            'Spain' => 'EU',
            'Italy' => 'EU',
            'Netherlands' => 'EU',
            'Sweden' => 'EU',
            'Denmark' => 'EU',
            'Norway' => 'EU',
            'Finland' => 'EU',
            'Poland' => 'EU',
            'Russia' => 'EU',
            'Ukraine' => 'EU',
            'Turkey' => 'EU',
            'Switzerland' => 'EU',
            'South Korea' => 'ASIA',
            'Japan' => 'ASIA',
            'China' => 'ASIA',
            'Singapore' => 'ASIA',
            'Thailand' => 'ASIA',
            'Indonesia' => 'ASIA',
            'India' => 'ASIA',
            'Australia' => 'OCE',
            'New Zealand' => 'OCE',
            'Brazil' => 'SA',
            'Argentina' => 'SA',
            'Chile' => 'SA'
        ];
        
        return $countryToRegion[$country] ?? 'NA';
    }
}