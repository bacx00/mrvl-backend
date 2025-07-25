<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class MarvelRivalsCompleteResetSeeder extends Seeder
{
    public function run()
    {
        echo "=== MARVEL RIVALS COMPLETE DATA RESET AND IMPORT ===\n\n";
        
        DB::transaction(function () {
            // Step 1: Clean up existing data
            echo "Cleaning up existing data...\n";
            
            // Delete all players first (due to foreign key constraints)
            Player::query()->delete();
            echo "- Deleted all players\n";
            
            // Delete all teams
            Team::query()->delete();
            echo "- Deleted all teams\n";
            
            // Delete all events
            Event::query()->delete();
            echo "- Deleted all events\n";
            
            // Step 2: Create events
            echo "\nCreating events...\n";
            $organizer = \App\Models\User::first();
            if (!$organizer) {
                throw new \Exception('No users found. Please create at least one user first.');
            }
            
            $events = $this->createEvents($organizer);
            
            // Step 3: Create all teams and players
            echo "\nCreating teams and players...\n";
            $this->createAllTeamsAndPlayers($events);
            
            echo "\n=== IMPORT COMPLETE ===\n";
        });
    }

    private function createEvents($organizer)
    {
        $eventsData = [
            'na_invitational' => [
                'name' => 'Marvel Rivals Invitational 2025: North America',
                'slug' => 'marvel-rivals-invitational-2025-north-america',
                'description' => 'An online North American Marvel Rivals Showmatch organized by NetEase featuring 8 teams competing for $100,000 USD.',
                'region' => 'NA',
                'prize_pool' => 100000,
                'max_teams' => 8,
                'start_date' => '2025-03-14',
                'end_date' => '2025-03-23',
            ],
            'emea_ignite' => [
                'name' => 'Marvel Rivals Ignite 2025 Stage 1: EMEA',
                'slug' => 'marvel-rivals-ignite-2025-stage-1-emea',
                'description' => 'An online European Marvel Rivals tournament organized by NetEase featuring 16 teams competing for $250,000 USD.',
                'region' => 'EU',
                'prize_pool' => 250000,
                'max_teams' => 16,
                'start_date' => '2025-06-12',
                'end_date' => '2025-06-29',
            ],
            'asia_ignite' => [
                'name' => 'Marvel Rivals Ignite 2025 Stage 1: Asia',
                'slug' => 'marvel-rivals-ignite-2025-stage-1-asia',
                'description' => 'An online Asian Marvel Rivals tournament organized by NetEase featuring 12 teams competing for $100,000 USD.',
                'region' => 'APAC',
                'prize_pool' => 100000,
                'max_teams' => 12,
                'start_date' => '2025-06-12',
                'end_date' => '2025-06-29',
            ],
            'americas_ignite' => [
                'name' => 'Marvel Rivals Ignite 2025 Stage 1: Americas',
                'slug' => 'marvel-rivals-ignite-2025-stage-1-americas',
                'description' => 'An online Americas Marvel Rivals tournament organized by NetEase featuring 16 teams competing for $250,000 USD.',
                'region' => 'NA',
                'prize_pool' => 250000,
                'max_teams' => 16,
                'start_date' => '2025-06-12',
                'end_date' => '2025-06-29',
            ],
            'oceania_ignite' => [
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

        $events = [];
        foreach ($eventsData as $key => $eventData) {
            $event = Event::create(array_merge($eventData, [
                'tier' => 'A',
                'status' => 'completed',
                'format' => 'group_stage',
                'type' => 'tournament',
                'game_mode' => 'Marvel Rivals',
                'organizer_id' => $organizer->id,
            ]));
            $events[$key] = $event;
            echo "- Created event: {$event->name}\n";
        }
        
        return $events;
    }

    private function createAllTeamsAndPlayers($events)
    {
        // Counter for unique usernames
        $usernameCounter = [];
        
        // North America Invitational Teams and Players
        $naInvitationalData = [
            // 1st Place - $40,000
            ['100 Thieves', '100T', 'United States', 'NA', 1, 40000, [
                ['Billion', 'Flex', 'United States'],
                ['Terra', 'Duelist', 'Canada'],
                ['delenaa', 'Duelist', 'United States'],
                ['Vinnie', 'Vanguard', 'United States'],
                ['TTK', 'Vanguard', 'United States'],
                ['SJP', 'Strategist', 'United States'],
                ['hxrvey', 'Strategist', 'United Kingdom'],
            ]],
            // 2nd Place - $20,000
            ['FlyQuest', 'FQ', 'United States', 'NA', 2, 20000, [
                ['Yokie', 'Flex', 'United States'],
                ['adios', 'Duelist', 'United States'],
                ['lyte', 'Duelist', 'United States'],
                ['energy', 'Duelist', 'United States'],
                ['SparkChief', 'Vanguard', 'Mexico'],
                ['Ghasklin', 'Vanguard', 'United Kingdom'],
                ['coopertastic', 'Strategist', 'United States'],
                ['Zelos', 'Strategist', 'Canada'],
            ]],
            // 3rd Place - $12,000
            ['Sentinels', 'SEN', 'United States', 'NA', 3, 12000, [
                ['Crimzo', 'Strategist', 'Canada'],
                ['Anexile', 'Flex', 'Canada'],
                ['SuperGomez', 'Duelist', 'Colombia'],
                ['Rymazing', 'Duelist', 'United States'],
                ['Hogz', 'Vanguard', 'Canada'],
                ['Coluge', 'Vanguard', 'United States'],
                ['aramori', 'Strategist', 'Canada'],
                ['Karova', 'Strategist', 'United States'],
            ]],
            // 4th Place - $8,000
            ['ENVY', 'ENVY', 'United States', 'NA', 4, 8000, [
                ['Shpeediry', 'Duelist', 'United States'],
                ['cal', 'Duelist', 'Canada'],
                ['nkae', 'Duelist', 'Canada'],
                ['iRemiix', 'Vanguard', 'Puerto Rico'],
                ['SPACE', 'Vanguard', 'United States'],
                ['Paintbrush', 'Strategist', 'United States'],
                ['sleepy', 'Strategist', 'United States'],
            ]],
            // 5th-8th Place - $5,000 each
            ['Shikigami', 'SKG', 'United States', 'NA', 5, 5000, []],
            ['NTMR', 'NTMR', 'United States', 'NA', 6, 5000, []],
            ['SHROUD-X', 'SHROUD', 'United States', 'NA', 7, 5000, [
                ['Vision', 'Duelist', 'United States'],
                ['doomed', 'Duelist', 'United States'],
                ['Impuniti', 'Vanguard', 'United States'],
                ['dongmin', 'Vanguard', 'United States'],
                ['Fidel', 'Strategist', 'United States'],
                ['Nuk', 'Strategist', 'United States'],
            ]],
            ['Rad Esports', 'RAD', 'United States', 'NA', 8, 5000, [
                ['XEYTEX', 'Duelist', 'United States'],
                ['Prota', 'Strategist', 'United States'],
            ]],
        ];

        // EMEA Teams and Players
        $emeaData = [
            // 1st Place - $70,000
            ['Brr Brr Patapim', 'BBP', 'Europe', 'EU', 1, 70000, [
                ['Salah', 'Duelist', 'United Kingdom'],
                ['Romanonico', 'Duelist', 'France'],
                ['Tanuki', 'Duelist', 'Netherlands'],
                ['Pokey', 'Duelist', 'Norway'],
                ['Nzo', 'Vanguard', 'France'],
                ['Polly', 'Vanguard', 'Norway'],
                ['Alx', 'Strategist', 'Bulgaria'],
                ['Ken', 'Strategist', 'Norway'],
            ]],
            // 2nd Place - $35,000
            ['Rad EU', 'RADEU', 'Europe', 'EU', 2, 35000, [
                ['Skyza', 'Flex', 'United States'],
                ['Sestroyed', 'Duelist', 'Lithuania'],
                ['Meliø', 'Duelist', 'Denmark'],
                ['Naga', 'Duelist', 'Denmark'],
                ['Raajaro', 'Vanguard', 'Finland'],
                ['TrqstMe', 'Vanguard', 'Germany'],
                ['Lv1Crook', 'Strategist', 'Hungary'],
                ['Fate', 'Strategist', 'United Kingdom'],
            ]],
            // 3rd Place - $25,000
            ['Virtus.pro', 'VP', 'Russia', 'EU', 3, 25000, []],
            // 4th Place - $20,000
            ['Zero Tenacity', 'ZT', 'Europe', 'EU', 4, 20000, [
                ['SmashNezz', 'Duelist', 'Denmark'],
                ['Knuten', 'Duelist', 'Denmark'],
                ['ducky1', 'Vanguard', 'United Kingdom'],
                ['Lugia', 'Vanguard', 'United Kingdom'],
                ['Wyni', 'Strategist', 'Spain'],
                ['Oasis', 'Strategist', 'Sweden'],
            ]],
            // 5th-6th Place - $15,000
            ['Team Peps', 'PEPS', 'Europe', 'EU', 5, 15000, []],
            ['L9', 'L9', 'Europe', 'EU', 6, 15000, []],
            // 7th-8th Place - $10,000
            ['All Business', 'AB', 'Europe', 'EU', 7, 10000, []],
            ['Insomnia', 'INSM', 'Europe', 'EU', 8, 10000, []],
            // 9th-12th Place - $7,500
            ['Schmungus', 'SCH', 'Europe', 'EU', 9, 7500, []],
            ['Yoinkanda', 'YOINK', 'Europe', 'EU', 10, 7500, []],
            ['Al Qadsiah', 'AQ', 'Saudi Arabia', 'EU', 11, 7500, []],
            ['OG Seed', 'OGS', 'Europe', 'EU', 12, 7500, []],
            // 13th-16th Place - $5,000
            ['BloodKariudo', 'BK', 'Europe', 'EU', 13, 5000, []],
            ['DUSTY', 'DUSTY', 'Europe', 'EU', 14, 5000, []],
            ['FYR Strays', 'FYR', 'Europe', 'EU', 15, 5000, []],
            ['ZERO.PERCENT', 'ZERO', 'Europe', 'EU', 16, 5000, []],
        ];

        // Asia Teams and Players
        $asiaData = [
            // 1st Place - $32,000
            ['REJECT', 'RC', 'South Korea', 'APAC', 1, 32000, [
                ['finale', 'Duelist', 'South Korea'],
                ['GARGOYLE', 'Duelist', 'South Korea'],
                ['piggy', 'Vanguard', 'South Korea'],
                ['Gnome', 'Vanguard', 'South Korea'],
                ['MOKA', 'Strategist', 'South Korea'],
                ['DDobi', 'Strategist', 'South Korea'],
            ]],
            // 2nd Place - $16,000
            ['Gen.G Esports', 'GEN', 'South Korea', 'APAC', 2, 16000, [
                ['Xzi', 'Duelist', 'South Korea'],
                ['Brownie', 'Duelist', 'South Korea'],
                ['KAIDIA', 'Duelist', 'South Korea'],
                ['CHOPPA', 'Vanguard', 'South Korea'],
                ['FUNFUN', 'Vanguard', 'South Korea'],
                ['Dotori', 'Strategist', 'South Korea'],
                ['SNAKE', 'Strategist', 'South Korea'],
            ]],
            // 3rd Place - $12,000
            ['Crazy Raccoon', 'CR', 'Japan', 'APAC', 3, 12000, [
                ['VITAL', 'Duelist', 'South Korea'],
                ['Hayan', 'Duelist', 'South Korea'],
                ['RIPASUKO', 'Vanguard', 'Japan'],
                ['JT3', 'Vanguard', 'Japan'],
                ['SeungHoon', 'Strategist', 'South Korea'],
                ['Rebirth', 'Strategist', 'South Korea'],
            ]],
            // 4th Place - $10,000
            ['XOXO01', 'XOXO', 'Taiwan', 'APAC', 4, 10000, [
                ['Bobok1ng', 'Duelist', 'Taiwan'],
                ['Hope', 'Duelist', 'China'],
                ['Errmo', 'Vanguard', 'Taiwan'],
                ['MaoLi', 'Vanguard', 'China'],
                ['CASSIUS', 'Strategist', 'Taiwan'],
                ['CQB', 'Strategist', 'China'],
            ]],
            // 5th-6th Place - $6,000
            ['O2 Blast', 'O2B', 'South Korea', 'APAC', 5, 6000, [
                ['re yi', 'Duelist', 'South Korea'],
                ['Roco', 'Duelist', 'South Korea'],
                ['Onse', 'Vanguard', 'South Korea'],
                ['Welsh Corgi', 'Vanguard', 'South Korea'],
                ['Felix', 'Strategist', 'South Korea'],
                ['Solmin', 'Strategist', 'South Korea'],
            ]],
            ['AssembleFire', 'AF', 'Thailand', 'APAC', 6, 6000, [
                ['KingdomGod', 'Duelist', 'Thailand'],
                ['SlowestSoldier', 'Duelist', 'Thailand'],
                ['ZEROONE', 'Vanguard', 'Thailand'],
                ['หมาเฟีย', 'Vanguard', 'Thailand'],
                ['Xenoz', 'Strategist', 'Thailand'],
                ['ชบาเเก้ว', 'Strategist', 'Thailand'],
            ]],
            // 7th-8th Place - $4,000
            ['AlenTiar', 'ALT', 'Thailand', 'APAC', 7, 4000, [
                ['N1nym', 'Duelist', 'Thailand'],
                ['RealJeff OTP', 'Duelist', 'Thailand'],
                ['Cartiace', 'Vanguard', 'Thailand'],
                ['MAXKEN', 'Vanguard', 'Thailand'],
                ['THE Deep', 'Strategist', 'Thailand'],
                ['Midstar', 'Strategist', 'Thailand'],
            ]],
            ['MVNEsport', 'MVN', 'Vietnam', 'APAC', 8, 4000, [
                ['ChuTiger', 'Duelist', 'Vietnam'],
                ['B1adeSha', 'Duelist', 'Vietnam'],
            ]],
            // 9th-12th Place - $2,000
            ['Onyx Esports', 'ONYX', 'Singapore', 'APAC', 9, 2000, []],
            ['VARREL', 'VRL', 'Japan', 'APAC', 10, 2000, []],
            ['ALPHA PLUS', 'AP', 'Vietnam', 'APAC', 11, 2000, []],
            ['SCARZ', 'SZ', 'Japan', 'APAC', 12, 2000, []],
        ];

        // Americas Teams and Players (some teams duplicate from NA)
        $americasData = [
            // 1st Place - $70,000
            ['Sentinels Americas', 'SENAM', 'United States', 'NA', 1, 70000, []],
            // 2nd Place - $35,000
            ['100 Thieves Americas', '100TAM', 'United States', 'NA', 2, 35000, []],
            // 3rd Place - $25,000
            ['ENVY Americas', 'ENVYAM', 'United States', 'NA', 3, 25000, []],
            // 4th Place - $20,000
            ['SHROUD-X Americas', 'SHRAM', 'United States', 'NA', 4, 20000, [
                ['Vision', 'Duelist', 'United States'],
                ['doomed', 'Duelist', 'United States'],
                ['Impuniti', 'Vanguard', 'United States'],
                ['dongmin', 'Vanguard', 'United States'],
                ['Fidel', 'Strategist', 'United States'],
                ['Nuk', 'Strategist', 'United States'],
            ]],
            // 5th-6th Place - $15,000
            ['Ego Death', 'EGO', 'United States', 'NA', 5, 15000, [
                ['Self', 'Duelist', 'United States'],
                ['XEYTEX', 'Duelist', 'United States'],
                ['Somble', 'Vanguard', 'United States'],
                ['soko', 'Vanguard', 'United States'],
                ['far', 'Strategist', 'United States'],
                ['Momentum', 'Strategist', 'United States'],
            ]],
            ['tekixd', 'TKD', 'United States', 'NA', 6, 15000, [
                ['Avery', 'Duelist', 'Canada'],
                ['TAP', 'Duelist', 'Netherlands'],
                ['blur', 'Vanguard', 'Wales'],
                ['Brute', 'Vanguard', 'United Kingdom'],
                ['Woofles', 'Strategist', 'United States'],
                ['aad', 'Strategist', 'United States'],
            ]],
            // 7th-8th Place - $10,000
            ['FlyQuest RED', 'FQR', 'United States', 'NA', 7, 10000, []],
            ['Legends', 'LGD', 'United States', 'NA', 8, 10000, []],
            // 9th-12th Place - $7,500
            ['NRG', 'NRG', 'United States', 'NA', 9, 7500, []],
            ['Cloud9', 'C9', 'United States', 'NA', 10, 7500, []],
            ['Evil Geniuses', 'EG', 'United States', 'NA', 11, 7500, []],
            ['Version1', 'V1', 'United States', 'NA', 12, 7500, []],
            // 13th-16th Place - $5,000
            ['Luminosity Gaming', 'LG', 'United States', 'NA', 13, 5000, []],
            ['TSM', 'TSM', 'United States', 'NA', 14, 5000, []],
            ['FURIA', 'FURIA', 'Brazil', 'SA', 15, 5000, []],
            ['LOUD', 'LOUD', 'Brazil', 'SA', 16, 5000, []],
        ];

        // Oceania Teams and Players
        $oceaniaData = [
            // 1st Place - $30,000
            ['Ground Zero Gaming', 'GZG', 'Australia', 'OCE', 1, 30000, [
                ['FMCL', 'Duelist', 'New Zealand'],
                ['SIX', 'Duelist', 'Botswana'],
                ['duep', 'Vanguard', 'Australia'],
                ['Zenstarry', 'Vanguard', 'Australia'],
                ['KINGBOB7', 'Strategist', 'Australia'],
                ['Mattyaf', 'Strategist', 'France'],
            ]],
            // 2nd Place - $15,000
            ['The Vicious', 'VIC', 'Australia', 'OCE', 2, 15000, [
                ['Revzi', 'Duelist', 'New Zealand'],
                ['rib', 'Duelist', 'Bangladesh'],
                ['Adam', 'Vanguard', 'Australia'],
                ['lumi', 'Vanguard', 'Singapore'],
                ['atlas', 'Strategist', 'Australia'],
                ['asher', 'Strategist', 'Australia'],
            ]],
            // 3rd Place - $9,000
            ['Kanga Esports', 'KNG', 'Australia', 'OCE', 3, 9000, [
                ['Daxu', 'Duelist', 'Australia'],
                ['Kronicx', 'Duelist', 'Australia'],
                ['Donald', 'Vanguard', 'Australia'],
                ['Tekzy', 'Vanguard', 'Australia'],
                ['furikakae', 'Strategist', 'Singapore'],
                ['SkittlesOCE', 'Strategist', 'Australia'],
            ]],
            // 4th Place - $6,000
            ['Bethany', 'BTH', 'Australia', 'OCE', 4, 6000, [
                ['azii', 'Duelist', 'Australia'],
                ['leam', 'Duelist', 'Australia'],
                ['Jag', 'Vanguard', 'Australia'],
                ['soupie7', 'Vanguard', 'Australia'],
                ['bubblecuh', 'Strategist', 'Australia'],
                ['oinkk', 'Strategist', 'Australia'],
            ]],
            // 5th-6th Place - $4,500
            ['Quetzal', 'QTZ', 'Australia', 'OCE', 5, 4500, []],
            ['Zavier Hope', 'ZH', 'Australia', 'OCE', 6, 4500, []],
            // 7th-8th Place - $3,000
            ['Pig Team', 'PIG', 'Australia', 'OCE', 7, 3000, []],
            ['Gappped', 'GAP', 'Australia', 'OCE', 8, 3000, []],
        ];

        // Process all tournament data
        $allTournamentData = [
            'na_invitational' => $naInvitationalData,
            'emea_ignite' => $emeaData,
            'asia_ignite' => $asiaData,
            'americas_ignite' => $americasData,
            'oceania_ignite' => $oceaniaData,
        ];

        $teamCount = 0;
        $playerCount = 0;

        foreach ($allTournamentData as $eventKey => $tournamentTeams) {
            $event = $events[$eventKey];
            echo "\nProcessing {$event->name}:\n";
            
            foreach ($tournamentTeams as $teamData) {
                [$teamName, $shortName, $country, $region, $placement, $prizeMoney, $players] = $teamData;
                
                // Create team
                $team = Team::create([
                    'name' => $teamName,
                    'short_name' => $shortName,
                    'region' => $region,
                    'country' => $country,
                    'platform' => 'PC',
                    'game' => 'Marvel Rivals',
                    'social_media' => [],
                    'earnings' => $prizeMoney
                ]);
                
                $teamCount++;
                echo "  - Created team: {$team->name} ({$team->short_name})\n";
                
                // Attach team to event with placement and prize money
                $event->teams()->attach($team->id, [
                    'placement' => $placement,
                    'prize_money' => $prizeMoney,
                    'status' => 'confirmed',
                    'registered_at' => now()
                ]);
                
                // Create players
                foreach ($players as $playerData) {
                    [$playerName, $role, $playerCountry] = $playerData;
                    
                    // Generate unique username
                    $baseUsername = strtolower(str_replace(' ', '', $playerName));
                    $username = $baseUsername;
                    
                    if (isset($usernameCounter[$baseUsername])) {
                        $usernameCounter[$baseUsername]++;
                        $username = $baseUsername . $usernameCounter[$baseUsername];
                    } else {
                        $usernameCounter[$baseUsername] = 0;
                    }
                    
                    $player = $this->createPlayer($playerName, $username, $role, $playerCountry, $team->id);
                    $playerCount++;
                }
            }
        }
        
        echo "\n\nTotal teams created: {$teamCount}\n";
        echo "Total players created: {$playerCount}\n";
    }

    private function createPlayer($name, $username, $role, $country, $teamId)
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
            'Saudi Arabia' => 'MENA',
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
            'Botswana' => 'MENA',
            'Europe' => 'EU',
        ];
        
        return Player::create([
            'name' => $name,
            'username' => $username,
            'team_id' => $teamId,
            'role' => $role,
            'country' => $country,
            'region' => $regionMap[$country] ?? 'INTL',
            'main_hero' => $defaultHeroes[$role] ?? 'Spider-Man',
            'social_media' => [],
            'earnings' => 0
        ]);
    }
}