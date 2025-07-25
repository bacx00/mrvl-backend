<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "\n=== ADDING MARVEL RIVALS TOURNAMENT DATA ===\n\n";

DB::transaction(function () {
    // Get or create a default organizer
    $organizer = \App\Models\User::first();
    if (!$organizer) {
        throw new \Exception('No users found in database. Please create at least one user first.');
    }
    
    // Create Events
    echo "Creating events...\n";
    $events = [];
    
    $eventData = [
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

    foreach ($eventData as $data) {
        $event = Event::updateOrCreate(
            ['slug' => $data['slug']],
            array_merge($data, [
                'tier' => 'A',
                'status' => 'completed',
                'format' => 'group_stage',
                'type' => 'tournament',
                'game_mode' => 'Marvel Rivals',
                'organizer_id' => $organizer->id,
            ])
        );
        $events[$data['slug']] = $event;
        echo "✓ Created event: {$event->name}\n";
    }
    
    // Team data with players
    $teamsData = [
        // NA Invitational Teams
        ['100 Thieves', '100T', 'NA', 'United States', [
            ['Billion', 'Flex', 'United States'],
            ['Terra', 'Duelist', 'Canada'],
            ['delenaa', 'Duelist', 'United States'],
            ['Vinnie', 'Vanguard', 'United States'],
            ['TTK', 'Vanguard', 'United States'],
            ['SJP', 'Strategist', 'United States'],
            ['hxrvey', 'Strategist', 'United Kingdom'],
        ]],
        ['FlyQuest', 'FQ', 'NA', 'United States', [
            ['Yokie', 'Flex', 'United States'],
            ['adios', 'Duelist', 'United States'],
            ['lyte', 'Duelist', 'United States'],
            ['energy', 'Duelist', 'United States'],
            ['SparkChief', 'Vanguard', 'Mexico'],
            ['Ghasklin', 'Vanguard', 'United Kingdom'],
            ['coopertastic', 'Strategist', 'United States'],
            ['Zelos', 'Strategist', 'Canada'],
        ]],
        ['ENVY', 'ENVY', 'NA', 'United States', [
            ['Shpeediry', 'Duelist', 'United States'],
            ['cal', 'Duelist', 'Canada'],
            ['nkae', 'Duelist', 'Canada'],
            ['iRemiix', 'Vanguard', 'Puerto Rico'],
            ['SPACE', 'Vanguard', 'United States'],
            ['Paintbrush', 'Strategist', 'United States'],
            ['sleepy', 'Strategist', 'United States'],
        ]],
        ['Shikigami', 'SKG', 'NA', 'United States', []],
        ['NTMR', 'NTMR', 'NA', 'United States', []],
        ['SHROUD-X', 'SHROUD', 'NA', 'United States', []],
        ['Rad Esports', 'RAD', 'NA', 'United States', [
            ['XEYTEX_RAD', 'Duelist', 'United States'],  // Added suffix to avoid duplicate
            ['Prota', 'Strategist', 'United States'],
        ]],
        
        // EMEA Teams
        ['Brr Brr Patapim', 'BBP', 'EU', 'Europe', [
            ['Salah', 'Duelist', 'United Kingdom'],
            ['Romanonico', 'Duelist', 'France'],
            ['Tanuki', 'Duelist', 'Netherlands'],
            ['Pokey', 'Duelist', 'Norway'],
            ['Nzo', 'Vanguard', 'France'],
            ['Polly', 'Vanguard', 'Norway'],
            ['Alx', 'Strategist', 'Bulgaria'],
            ['Ken', 'Strategist', 'Norway'],
        ]],
        ['Rad EU', 'RADEU', 'EU', 'Europe', [
            ['Skyza', 'Flex', 'United States'],
            ['Sestroyed', 'Duelist', 'Lithuania'],
            ['Meliø', 'Duelist', 'Denmark'],
            ['Naga', 'Duelist', 'Denmark'],
            ['Raajaro', 'Vanguard', 'Finland'],
            ['TrqstMe', 'Vanguard', 'Germany'],
            ['Lv1Crook', 'Strategist', 'Hungary'],
            ['Fate', 'Strategist', 'United Kingdom'],
        ]],
        ['Zero Tenacity', 'ZT', 'EU', 'Europe', [
            ['SmashNezz', 'Duelist', 'Denmark'],
            ['Knuten', 'Duelist', 'Denmark'],
            ['ducky1', 'Vanguard', 'United Kingdom'],
            ['Lugia', 'Vanguard', 'United Kingdom'],
            ['Wyni', 'Strategist', 'Spain'],
            ['Oasis', 'Strategist', 'Sweden'],
        ]],
        ['Team Peps', 'PEPS', 'EU', 'Europe', []],
        ['L9', 'L9', 'EU', 'Europe', []],
        ['All Business', 'AB', 'EU', 'Europe', []],
        ['Insomnia', 'INSM', 'EU', 'Europe', []],
        
        // Asia Teams
        ['REJECT', 'RC', 'APAC', 'South Korea', [
            ['finale', 'Duelist', 'South Korea'],
            ['GARGOYLE', 'Duelist', 'South Korea'],
            ['piggy', 'Vanguard', 'South Korea'],
            ['Gnome', 'Vanguard', 'South Korea'],
            ['MOKA', 'Strategist', 'South Korea'],
            ['DDobi', 'Strategist', 'South Korea'],
        ]],
        ['Gen.G Esports', 'GENG', 'APAC', 'South Korea', [
            ['Xzi', 'Duelist', 'South Korea'],
            ['Brownie', 'Duelist', 'South Korea'],
            ['KAIDIA', 'Duelist', 'South Korea'],
            ['CHOPPA', 'Vanguard', 'South Korea'],
            ['FUNFUN', 'Vanguard', 'South Korea'],
            ['Dotori', 'Strategist', 'South Korea'],
            ['SNAKE', 'Strategist', 'South Korea'],
        ]],
        ['Crazy Raccoon', 'CR', 'APAC', 'Japan', [
            ['VITAL', 'Duelist', 'South Korea'],
            ['Hayan', 'Duelist', 'South Korea'],
            ['RIPASUKO', 'Vanguard', 'Japan'],
            ['JT3', 'Vanguard', 'Japan'],
            ['SeungHoon', 'Strategist', 'South Korea'],
            ['Rebirth', 'Strategist', 'South Korea'],
        ]],
        ['XOXO01', 'XOXO', 'APAC', 'Taiwan', [
            ['Bobok1ng', 'Duelist', 'Taiwan'],
            ['Hope', 'Duelist', 'China'],
            ['Errmo', 'Vanguard', 'Taiwan'],
            ['MaoLi', 'Vanguard', 'China'],
            ['CASSIUS', 'Strategist', 'Taiwan'],
            ['CQB', 'Strategist', 'China'],
        ]],
        ['O2 Blast', 'O2B', 'APAC', 'South Korea', [
            ['re yi', 'Duelist', 'South Korea'],
            ['Roco', 'Duelist', 'South Korea'],
            ['Onse', 'Vanguard', 'South Korea'],
            ['Welsh Corgi', 'Vanguard', 'South Korea'],
            ['Felix', 'Strategist', 'South Korea'],
            ['Solmin', 'Strategist', 'South Korea'],
        ]],
        ['AssembleFire', 'AF', 'APAC', 'Thailand', [
            ['KingdomGod', 'Duelist', 'Thailand'],
            ['SlowestSoldier', 'Duelist', 'Thailand'],
            ['ZEROONE', 'Vanguard', 'Thailand'],
            ['หมาเฟีย', 'Vanguard', 'Thailand'],
            ['Xenoz', 'Strategist', 'Thailand'],
            ['ชบาเเก้ว', 'Strategist', 'Thailand'],
        ]],
        
        // Americas Teams (additional)
        ['Ego Death', 'EGO', 'NA', 'United States', [
            ['Self', 'Duelist', 'United States'],
            ['XEYTEX_EGO', 'Duelist', 'United States'],  // Added suffix
            ['Somble', 'Vanguard', 'United States'],
            ['soko', 'Vanguard', 'United States'],
            ['far', 'Strategist', 'United States'],
            ['Momentum', 'Strategist', 'United States'],
        ]],
        ['tekixd', 'TKD', 'NA', 'United States', [
            ['Avery', 'Duelist', 'Canada'],
            ['TAP', 'Duelist', 'Netherlands'],
            ['blur', 'Vanguard', 'Wales'],
            ['Brute', 'Vanguard', 'United Kingdom'],
            ['Woofles', 'Strategist', 'United States'],
            ['aad', 'Strategist', 'United States'],
        ]],
        
        // Oceania Teams
        ['Ground Zero Gaming', 'GZG', 'OCE', 'Australia', [
            ['FMCL', 'Duelist', 'New Zealand'],
            ['SIX', 'Duelist', 'Botswana'],
            ['duep', 'Vanguard', 'Australia'],
            ['Zenstarry', 'Vanguard', 'Australia'],
            ['KINGBOB7', 'Strategist', 'Australia'],
            ['Mattyaf', 'Strategist', 'France'],
        ]],
        ['The Vicious', 'VIC', 'OCE', 'Australia', [
            ['Revzi', 'Duelist', 'New Zealand'],
            ['rib', 'Duelist', 'Bangladesh'],
            ['Adam', 'Vanguard', 'Australia'],
            ['lumi', 'Vanguard', 'Singapore'],
            ['atlas', 'Strategist', 'Australia'],
            ['asher', 'Strategist', 'Australia'],
        ]],
        ['Kanga Esports', 'KNG', 'OCE', 'Australia', [
            ['Daxu', 'Duelist', 'Australia'],
            ['Kronicx', 'Duelist', 'Australia'],
            ['Donald', 'Vanguard', 'Australia'],
            ['Tekzy', 'Vanguard', 'Australia'],
            ['furikakae', 'Strategist', 'Singapore'],
            ['SkittlesOCE', 'Strategist', 'Australia'],
        ]],
        ['Bethany', 'BTH', 'OCE', 'Australia', [
            ['azii', 'Duelist', 'Australia'],
            ['leam', 'Duelist', 'Australia'],
            ['Jag', 'Vanguard', 'Australia'],
            ['soupie7', 'Vanguard', 'Australia'],
            ['bubblecuh', 'Strategist', 'Australia'],
            ['oinkk', 'Strategist', 'Australia'],
        ]],
    ];
    
    echo "\nCreating teams and players...\n";
    $teamCount = 0;
    $playerCount = 0;
    
    foreach ($teamsData as $teamData) {
        $teamName = $teamData[0];
        $shortName = $teamData[1];
        $region = $teamData[2];
        $country = $teamData[3];
        $players = $teamData[4];
        
        // Create or update team
        $team = Team::updateOrCreate(
            ['short_name' => $shortName],
            [
                'name' => $teamName,
                'region' => $region,
                'country' => $country,
                'platform' => 'PC',
                'game' => 'Marvel Rivals',
                'social_media' => []
            ]
        );
        $teamCount++;
        echo "✓ Created/Updated team: {$team->name} ({$team->short_name})\n";
        
        // Create players
        foreach ($players as $playerData) {
            $playerName = $playerData[0];
            $role = $playerData[1];
            $playerCountry = $playerData[2];
            
            // Generate unique username
            $username = strtolower(str_replace(' ', '', $playerName));
            $baseUsername = $username;
            $counter = 1;
            
            // Check if username exists and make it unique
            while (Player::where('username', $username)->where('team_id', '!=', $team->id)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }
            
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
                'Botswana' => 'MENA',
                'Europe' => 'EU'
            ];
            
            $player = Player::updateOrCreate(
                [
                    'name' => $playerName,
                    'team_id' => $team->id
                ],
                [
                    'username' => $username,
                    'role' => $role,
                    'country' => $playerCountry,
                    'region' => $regionMap[$playerCountry] ?? 'INTL',
                    'main_hero' => $defaultHeroes[$role] ?? 'Spider-Man',
                    'social_media' => [],
                    'earnings' => 0
                ]
            );
            $playerCount++;
            echo "  + Added player: {$player->name} ({$player->role})\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "✓ Events created: " . count($events) . "\n";
    echo "✓ Teams created/updated: {$teamCount}\n";
    echo "✓ Players created/updated: {$playerCount}\n";
    
    // Link teams to events with placements and prize money
    echo "\nLinking teams to events...\n";
    
    // NA Invitational placements
    $naEvent = $events['marvel-rivals-invitational-2025-north-america'];
    $naPlacements = [
        '100T' => ['placement' => 1, 'prize_money' => 40000],
        'FQ' => ['placement' => 2, 'prize_money' => 20000],
        'SEN' => ['placement' => 3, 'prize_money' => 12000],
        'ENVY' => ['placement' => 4, 'prize_money' => 8000],
        'SHROUD' => ['placement' => 5, 'prize_money' => 5000],
        'NTMR' => ['placement' => 6, 'prize_money' => 5000],
        'SKG' => ['placement' => 7, 'prize_money' => 5000],
        'RAD' => ['placement' => 8, 'prize_money' => 5000],
    ];
    
    foreach ($naPlacements as $shortName => $data) {
        $team = Team::where('short_name', $shortName)->first();
        if ($team) {
            $naEvent->teams()->syncWithoutDetaching([
                $team->id => array_merge($data, [
                    'status' => 'confirmed',
                    'registered_at' => now()
                ])
            ]);
        }
    }
    
    echo "\n=== COMPLETE ===\n";
    echo "All Marvel Rivals tournament data has been successfully added!\n\n";
});

// Run verification
require_once __DIR__ . '/verify_tournament_data.php';