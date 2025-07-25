<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarvelRivalsChampionshipSeederV2 extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            // Get or create a default organizer
            $organizer = \App\Models\User::first();
            if (!$organizer) {
                throw new \Exception('No users found in database. Please create at least one user first.');
            }
            
            // Create all events
            $this->createAllEvents($organizer);
            
            // Create all teams and players
            $this->createAllTeamsAndPlayers();
        });
        
        echo "Successfully created all Marvel Rivals Championship data!\n";
    }

    private function createAllEvents($organizer)
    {
        $events = [
            [
                'name' => 'Marvel Rivals Invitational 2025: North America',
                'slug' => 'marvel-rivals-invitational-2025-north-america',
                'description' => 'An online North American Marvel Rivals Showmatch organized by NetEase featuring 8 teams competing for $100,000 USD.',
                'region' => 'NA',
                'prize_pool' => 100000,
                'max_teams' => 8,
                'start_date' => '2025-03-14',
                'end_date' => '2025-03-23',
            ],
            [
                'name' => 'Marvel Rivals Ignite 2025 Stage 1: EMEA',
                'slug' => 'marvel-rivals-ignite-2025-stage-1-emea',
                'description' => 'An online European Marvel Rivals tournament organized by NetEase featuring 16 teams competing for $250,000 USD.',
                'region' => 'EU',
                'prize_pool' => 250000,
                'max_teams' => 16,
                'start_date' => '2025-06-12',
                'end_date' => '2025-06-29',
            ],
            [
                'name' => 'Marvel Rivals Ignite 2025 Stage 1: Asia',
                'slug' => 'marvel-rivals-ignite-2025-stage-1-asia',
                'description' => 'An online Asian Marvel Rivals tournament organized by NetEase featuring 12 teams competing for $100,000 USD.',
                'region' => 'APAC',
                'prize_pool' => 100000,
                'max_teams' => 12,
                'start_date' => '2025-06-12',
                'end_date' => '2025-06-29',
            ],
            [
                'name' => 'Marvel Rivals Ignite 2025 Stage 1: Americas',
                'slug' => 'marvel-rivals-ignite-2025-stage-1-americas',
                'description' => 'An online Americas Marvel Rivals tournament organized by NetEase featuring 16 teams competing for $250,000 USD.',
                'region' => 'NA',
                'prize_pool' => 250000,
                'max_teams' => 16,
                'start_date' => '2025-06-12',
                'end_date' => '2025-06-29',
            ],
            [
                'name' => 'Marvel Rivals Ignite 2025 Stage 1: Oceania',
                'slug' => 'marvel-rivals-ignite-2025-stage-1-oceania',
                'description' => 'An online Oceanian Marvel Rivals tournament organized by NetEase featuring 8 teams competing for $75,000 USD.',
                'region' => 'OCE',
                'prize_pool' => 75000,
                'max_teams' => 8,
                'start_date' => '2025-06-12',
                'end_date' => '2025-06-22',
            ],
        ];

        foreach ($events as $eventData) {
            Event::firstOrCreate(
                ['name' => $eventData['name']],
                array_merge($eventData, [
                    'tier' => 'A',
                    'status' => 'completed',
                    'format' => 'group_stage',
                    'type' => 'tournament',
                    'game_mode' => 'Marvel Rivals',
                    'organizer_id' => $organizer->id,
                ])
            );
        }
    }

    private function createAllTeamsAndPlayers()
    {
        // NA Invitational Teams
        $naTeams = [
            ['100 Thieves', '100T', 'United States', [
                ['Billion', 'Flex', 'United States'],
                ['Terra', 'Duelist', 'Canada'],
                ['delenaa', 'Duelist', 'United States'],
                ['Vinnie', 'Vanguard', 'United States'],
                ['TTK', 'Vanguard', 'United States'],
                ['SJP', 'Strategist', 'United States'],
                ['hxrvey', 'Strategist', 'United Kingdom'],
            ]],
            ['FlyQuest', 'FQ', 'United States', [
                ['Yokie', 'Flex', 'United States'],
                ['adios', 'Duelist', 'United States'],
                ['lyte', 'Duelist', 'United States'],
                ['energy', 'Duelist', 'United States'],
                ['SparkChief', 'Vanguard', 'Mexico'],
                ['Ghasklin', 'Vanguard', 'United Kingdom'],
                ['coopertastic', 'Strategist', 'United States'],
                ['Zelos', 'Strategist', 'Canada'],
            ]],
            ['Sentinels', 'SEN', 'United States', [
                ['Crimzo', 'Strategist', 'Canada'],
                ['Anexile', 'Flex', 'Canada'],
                ['SuperGomez', 'Duelist', 'Colombia'],
                ['Rymazing', 'Duelist', 'United States'],
                ['Hogz', 'Vanguard', 'Canada'],
                ['Coluge', 'Vanguard', 'United States'],
                ['aramori', 'Strategist', 'Canada'],
                ['Karova', 'Strategist', 'United States'],
            ]],
            ['ENVY', 'ENVY', 'United States', [
                ['Shpeediry', 'Duelist', 'United States'],
                ['cal', 'Duelist', 'Canada'],
                ['nkae', 'Duelist', 'Canada'],
                ['iRemiix', 'Vanguard', 'Puerto Rico'],
                ['SPACE', 'Vanguard', 'United States'],
                ['Paintbrush', 'Strategist', 'United States'],
                ['sleepy', 'Strategist', 'United States'],
            ]],
            ['Shikigami', 'SKG', 'United States', []],
            ['NTMR', 'NTMR', 'United States', []],
            ['SHROUD-X', 'SHROUD', 'United States', []],
            ['Rad Esports', 'RAD', 'United States', [
                ['XEYTEX', 'Duelist', 'United States'],
                ['Prota', 'Strategist', 'United States'],
            ]],
        ];

        // EMEA Teams
        $emeaTeams = [
            ['Brr Brr Patapim', 'BBP', 'Europe', [
                ['Salah', 'Duelist', 'United Kingdom'],
                ['Romanonico', 'Duelist', 'France'],
                ['Tanuki', 'Duelist', 'Netherlands'],
                ['Pokey', 'Duelist', 'Norway'],
                ['Nzo', 'Vanguard', 'France'],
                ['Polly', 'Vanguard', 'Norway'],
                ['Alx', 'Strategist', 'Bulgaria'],
                ['Ken', 'Strategist', 'Norway'],
            ]],
            ['Rad EU', 'RADEU', 'Europe', [
                ['Skyza', 'Flex', 'United States'],
                ['Sestroyed', 'Duelist', 'Lithuania'],
                ['Meliø', 'Duelist', 'Denmark'],
                ['Naga', 'Duelist', 'Denmark'],
                ['Raajaro', 'Vanguard', 'Finland'],
                ['TrqstMe', 'Vanguard', 'Germany'],
                ['Lv1Crook', 'Strategist', 'Hungary'],
                ['Fate', 'Strategist', 'United Kingdom'],
            ]],
            ['Virtus.pro', 'VP', 'Russia', []],
            ['Zero Tenacity', 'ZT', 'Europe', [
                ['SmashNezz', 'Duelist', 'Denmark'],
                ['Knuten', 'Duelist', 'Denmark'],
                ['ducky1', 'Vanguard', 'United Kingdom'],
                ['Lugia', 'Vanguard', 'United Kingdom'],
                ['Wyni', 'Strategist', 'Spain'],
                ['Oasis', 'Strategist', 'Sweden'],
            ]],
            ['Team Peps', 'PEPS', 'Europe', []],
            ['L9', 'L9', 'Europe', []],
            ['All Business', 'AB', 'Europe', []],
            ['Insomnia', 'INSM', 'Europe', []],
            ['Schmungus', 'SCH', 'Europe', []],
            ['Yoinkanda', 'YOINK', 'Europe', []],
            ['Al Qadsiah', 'AQ', 'Europe', []],
            ['OG Seed', 'OGS', 'Europe', []],
            ['BloodKariudo', 'BK', 'Europe', []],
            ['DUSTY', 'DUSTY', 'Europe', []],
            ['FYR Strays', 'FYR', 'Europe', []],
            ['ZERO.PERCENT', 'ZERO', 'Europe', []],
        ];

        // Asia Teams
        $asiaTeams = [
            ['REJECT', 'RC', 'South Korea', [
                ['finale', 'Duelist', 'South Korea'],
                ['GARGOYLE', 'Duelist', 'South Korea'],
                ['piggy', 'Vanguard', 'South Korea'],
                ['Gnome', 'Vanguard', 'South Korea'],
                ['MOKA', 'Strategist', 'South Korea'],
                ['DDobi', 'Strategist', 'South Korea'],
            ]],
            ['Gen.G Esports', 'GEN', 'South Korea', [
                ['Xzi', 'Duelist', 'South Korea'],
                ['Brownie', 'Duelist', 'South Korea'],
                ['KAIDIA', 'Duelist', 'South Korea'],
                ['CHOPPA', 'Vanguard', 'South Korea'],
                ['FUNFUN', 'Vanguard', 'South Korea'],
                ['Dotori', 'Strategist', 'South Korea'],
                ['SNAKE', 'Strategist', 'South Korea'],
            ]],
            ['Crazy Raccoon', 'CR', 'Japan', [
                ['VITAL', 'Duelist', 'South Korea'],
                ['Hayan', 'Duelist', 'South Korea'],
                ['RIPASUKO', 'Vanguard', 'Japan'],
                ['JT3', 'Vanguard', 'Japan'],
                ['SeungHoon', 'Strategist', 'South Korea'],
                ['Rebirth', 'Strategist', 'South Korea'],
            ]],
            ['XOXO01', 'XOXO', 'Taiwan', [
                ['Bobok1ng', 'Duelist', 'Taiwan'],
                ['Hope', 'Duelist', 'China'],
                ['Errmo', 'Vanguard', 'Taiwan'],
                ['MaoLi', 'Vanguard', 'China'],
                ['CASSIUS', 'Strategist', 'Taiwan'],
                ['CQB', 'Strategist', 'China'],
            ]],
            ['O2 Blast', 'O2B', 'South Korea', [
                ['re yi', 'Duelist', 'South Korea'],
                ['Roco', 'Duelist', 'South Korea'],
                ['Onse', 'Vanguard', 'South Korea'],
                ['Welsh Corgi', 'Vanguard', 'South Korea'],
                ['Felix', 'Strategist', 'South Korea'],
                ['Solmin', 'Strategist', 'South Korea'],
            ]],
            ['AssembleFire', 'AF', 'Thailand', [
                ['KingdomGod', 'Duelist', 'Thailand'],
                ['SlowestSoldier', 'Duelist', 'Thailand'],
                ['ZEROONE', 'Vanguard', 'Thailand'],
                ['หมาเฟีย', 'Vanguard', 'Thailand'],
                ['Xenoz', 'Strategist', 'Thailand'],
                ['ชบาเเก้ว', 'Strategist', 'Thailand'],
            ]],
            ['AlenTiar', 'ALT', 'Thailand', [
                ['N1nym', 'Duelist', 'Thailand'],
                ['RealJeff OTP', 'Duelist', 'Thailand'],
                ['Cartiace', 'Vanguard', 'Thailand'],
                ['MAXKEN', 'Vanguard', 'Thailand'],
                ['THE Deep', 'Strategist', 'Thailand'],
                ['Midstar', 'Strategist', 'Thailand'],
            ]],
            ['MVNEsport', 'MVN', 'Vietnam', []],
            ['Onyx Esports', 'ONYX', 'Singapore', []],
            ['VARREL', 'VRL', 'Japan', []],
            ['ALPHA PLUS', 'AP', 'Vietnam', []],
            ['SCARZ', 'SZ', 'Japan', []],
        ];

        // Americas Teams (reusing some NA teams)
        $americasTeams = [
            ['Ego Death', 'EGO', 'United States', [
                ['Self', 'Duelist', 'United States'],
                ['XEYTEX', 'Duelist', 'United States'],
                ['Somble', 'Vanguard', 'United States'],
                ['soko', 'Vanguard', 'United States'],
                ['far', 'Strategist', 'United States'],
                ['Momentum', 'Strategist', 'United States'],
            ]],
            ['tekixd', 'TKD', 'United States', [
                ['Avery', 'Duelist', 'Canada'],
                ['TAP', 'Duelist', 'Netherlands'],
                ['blur', 'Vanguard', 'Wales'],
                ['Brute', 'Vanguard', 'United Kingdom'],
                ['Woofles', 'Strategist', 'United States'],
                ['aad', 'Strategist', 'United States'],
            ]],
            ['FlyQuest RED', 'FQR', 'United States', []],
            ['Legends', 'LGD', 'United States', []],
            ['NRG', 'NRG', 'United States', []],
            ['Cloud9', 'C9', 'United States', []],
            ['Evil Geniuses', 'EG', 'United States', []],
            ['Version1', 'V1', 'United States', []],
            ['Luminosity Gaming', 'LG', 'United States', []],
            ['TSM', 'TSM', 'United States', []],
            ['FURIA', 'FURIA', 'Brazil', []],
            ['LOUD', 'LOUD', 'Brazil', []],
        ];

        // Oceania Teams
        $oceaniaTeams = [
            ['Ground Zero Gaming', 'GZG', 'Australia', [
                ['FMCL', 'Duelist', 'New Zealand'],
                ['SIX', 'Duelist', 'Botswana'],
                ['duep', 'Vanguard', 'Australia'],
                ['Zenstarry', 'Vanguard', 'Australia'],
                ['KINGBOB7', 'Strategist', 'Australia'],
                ['Mattyaf', 'Strategist', 'France'],
            ]],
            ['The Vicious', 'VIC', 'Australia', [
                ['Revzi', 'Duelist', 'New Zealand'],
                ['rib', 'Duelist', 'Bangladesh'],
                ['Adam', 'Vanguard', 'Australia'],
                ['lumi', 'Vanguard', 'Singapore'],
                ['atlas', 'Strategist', 'Australia'],
                ['asher', 'Strategist', 'Australia'],
            ]],
            ['Kanga Esports', 'KNG', 'Australia', [
                ['Daxu', 'Duelist', 'Australia'],
                ['Kronicx', 'Duelist', 'Australia'],
                ['Donald', 'Vanguard', 'Australia'],
                ['Tekzy', 'Vanguard', 'Australia'],
                ['furikakae', 'Strategist', 'Singapore'],
                ['SkittlesOCE', 'Strategist', 'Australia'],
            ]],
            ['Bethany', 'BTH', 'Australia', [
                ['azii', 'Duelist', 'Australia'],
                ['leam', 'Duelist', 'Australia'],
                ['Jag', 'Vanguard', 'Australia'],
                ['soupie7', 'Vanguard', 'Australia'],
                ['bubblecuh', 'Strategist', 'Australia'],
                ['oinkk', 'Strategist', 'Australia'],
            ]],
            ['Quetzal', 'QTZ', 'Australia', []],
            ['Zavier Hope', 'ZH', 'Australia', []],
            ['Pig Team', 'PIG', 'Australia', []],
            ['Gappped', 'GAP', 'Australia', []],
        ];

        // Process all teams
        $allTeams = [
            'NA' => $naTeams,
            'EU' => $emeaTeams,
            'APAC' => $asiaTeams,
            'NA2' => $americasTeams,
            'OCE' => $oceaniaTeams,
        ];

        foreach ($allTeams as $region => $teams) {
            foreach ($teams as $teamData) {
                $teamName = $teamData[0];
                $shortName = $teamData[1];
                $country = $teamData[2];
                $players = $teamData[3];
                
                // Create team
                $team = Team::firstOrCreate(
                    ['name' => $teamName],
                    [
                        'short_name' => $shortName,
                        'region' => $this->mapRegion($region),
                        'country' => $country,
                        'platform' => 'PC',
                        'game' => 'Marvel Rivals',
                        'social_media' => []
                    ]
                );
                
                // Create players
                foreach ($players as $playerData) {
                    $this->createPlayer($playerData[0], $playerData[1], $playerData[2], $team->id);
                }
            }
        }
    }

    private function mapRegion($region)
    {
        // Keep region codes short
        $regionMap = [
            'NA' => 'NA',
            'NA2' => 'NA',
            'EU' => 'EU',
            'APAC' => 'APAC',
            'OCE' => 'OCE',
        ];
        
        return $regionMap[$region] ?? $region;
    }

    private function createPlayer($name, $role, $country, $teamId)
    {
        // Map roles to default heroes
        $defaultHeroes = [
            'Duelist' => 'Spider-Man',
            'Vanguard' => 'Hulk',
            'Strategist' => 'Mantis',
            'Tank' => 'Hulk',
            'Support' => 'Mantis',
            'DPS' => 'Spider-Man',
            'Flex' => 'Iron Man'
        ];
        
        // Map countries to regions
        $regionMap = [
            'United States' => 'NA',
            'Canada' => 'NA',
            'Mexico' => 'NA',
            'Puerto Rico' => 'NA',
            'Brazil' => 'SA',
            'Colombia' => 'SA',
            'United Kingdom' => 'EU',
            'France' => 'EU',
            'Germany' => 'EU',
            'Spain' => 'EU',
            'Netherlands' => 'EU',
            'Denmark' => 'EU',
            'Norway' => 'EU',
            'Sweden' => 'EU',
            'Finland' => 'EU',
            'Bulgaria' => 'EU',
            'Lithuania' => 'EU',
            'Hungary' => 'EU',
            'Russia' => 'EU',
            'Wales' => 'EU',
            'South Korea' => 'APAC',
            'Japan' => 'APAC',
            'China' => 'CN',
            'Taiwan' => 'APAC',
            'Thailand' => 'APAC',
            'Vietnam' => 'APAC',
            'Singapore' => 'APAC',
            'Australia' => 'OCE',
            'New Zealand' => 'OCE',
            'Bangladesh' => 'APAC',
            'Botswana' => 'MENA'
        ];
        
        return Player::firstOrCreate(
            [
                'name' => $name,
                'team_id' => $teamId
            ],
            [
                'username' => strtolower(str_replace(' ', '', $name)),
                'role' => $role,
                'country' => $country,
                'region' => $regionMap[$country] ?? 'INTL',
                'main_hero' => $defaultHeroes[$role] ?? 'Spider-Man',
                'social_media' => [],
                'earnings' => 0
            ]
        );
    }
}