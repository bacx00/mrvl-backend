<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Event;
use App\Models\MatchModel;
use App\Models\MatchMap;
use App\Models\MatchPlayerStat;
use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🎮 Marvel Rivals Match Data Population Script\n";
echo "===========================================\n\n";

// Marvel Rivals Hero Data with Roles
$marvelRivalsHeroes = [
    // Vanguards (Tanks)
    'Captain America' => [
        'role' => 'Vanguard', 
        'image' => 'captain-america-headbig.webp',
        'abilities' => ['Shield Throw', 'Freedom Charge', 'Living Legend']
    ],
    'Hulk' => [
        'role' => 'Vanguard', 
        'image' => 'hulk-headbig.webp',
        'abilities' => ['Hulk Smash', 'Thunderclap', 'World Breaker']
    ],
    'Magneto' => [
        'role' => 'Vanguard', 
        'image' => 'magneto-headbig.webp',
        'abilities' => ['Metallic Sphere', 'Magnetic Grip', 'Meteor M']
    ],
    'Doctor Strange' => [
        'role' => 'Vanguard', 
        'image' => 'doctor-strange-headbig.webp',
        'abilities' => ['Shield of Seraphim', 'Eye of Agamotto', 'Astral Projection']
    ],
    'Groot' => [
        'role' => 'Vanguard', 
        'image' => 'groot-headbig.webp',
        'abilities' => ['Ironbark Wall', 'Spore Explosion', 'Strangling Prison']
    ],
    'The Thing' => [
        'role' => 'Vanguard', 
        'image' => 'the-thing-headbig.webp',
        'abilities' => ['Rock Smash', 'Boulder Throw', 'Clobbering Time']
    ],
    'Venom' => [
        'role' => 'Vanguard', 
        'image' => 'venom-headbig.webp',
        'abilities' => ['Symbiote Swing', 'Cellular Acceleration', 'We Are Venom']
    ],

    // Duelists (DPS)
    'Spider-Man' => [
        'role' => 'Duelist', 
        'image' => 'spider-man-headbig.webp',
        'abilities' => ['Web Swing', 'Spider Sense', 'Maximum Spider']
    ],
    'Iron Man' => [
        'role' => 'Duelist', 
        'image' => 'iron-man-headbig.webp',
        'abilities' => ['Repulsor Ray', 'Unibeam', 'Hulkbuster']
    ],
    'Black Widow' => [
        'role' => 'Duelist', 
        'image' => 'black-widow-headbig.webp',
        'abilities' => ['Widow\'s Bite', 'Grappling Hook', 'Red Guardian']
    ],
    'Scarlet Witch' => [
        'role' => 'Duelist', 
        'image' => 'scarlet-witch-headbig.webp',
        'abilities' => ['Chaos Magic', 'Reality Warp', 'House of M']
    ],
    'Hawkeye' => [
        'role' => 'Duelist', 
        'image' => 'hawkeye-headbig.webp',
        'abilities' => ['Piercing Arrow', 'Explosive Arrow', 'Hunter\'s Sight']
    ],
    'Storm' => [
        'role' => 'Duelist', 
        'image' => 'storm-headbig.webp',
        'abilities' => ['Lightning Strike', 'Wind Gust', 'Omega-Level Storm']
    ],
    'Wolverine' => [
        'role' => 'Duelist', 
        'image' => 'wolverine-headbig.webp',
        'abilities' => ['Berserker Barrage', 'Adamantium Claws', 'Weapon X']
    ],
    'Winter Soldier' => [
        'role' => 'Duelist', 
        'image' => 'winter-soldier-headbig.webp',
        'abilities' => ['Bionic Hook', 'Cluster Grenade', 'Kraken Impact']
    ],
    'Star-Lord' => [
        'role' => 'Duelist', 
        'image' => 'star-lord-headbig.webp',
        'abilities' => ['Element Gun', 'Rocket Boost', 'Galactic Star-Lord']
    ],
    'Psylocke' => [
        'role' => 'Duelist', 
        'image' => 'psylocke-headbig.webp',
        'abilities' => ['Psi-Blade', 'Shadow Step', 'Psychic Katana']
    ],
    'Punisher' => [
        'role' => 'Duelist', 
        'image' => 'punisher-headbig.webp',
        'abilities' => ['Smoke Grenade', 'Tactical Roll', 'Total Punishment']
    ],
    'Moon Knight' => [
        'role' => 'Duelist', 
        'image' => 'moon-knight-headbig.webp',
        'abilities' => ['Crescent Dart', 'Ankh Portal', 'Hand of Khonshu']
    ],
    'Namor' => [
        'role' => 'Duelist', 
        'image' => 'namor-headbig.webp',
        'abilities' => ['Trident Dive', 'Tidal Wave', 'Imperius Rex']
    ],
    'Magik' => [
        'role' => 'Duelist', 
        'image' => 'magik-headbig.webp',
        'abilities' => ['Soul Sword', 'Stepping Disc', 'Darkchild']
    ],
    'Black Panther' => [
        'role' => 'Duelist', 
        'image' => 'black-panther-headbig.webp',
        'abilities' => ['Vibranium Spear', 'Pounce', 'King of Wakanda']
    ],
    'Human Torch' => [
        'role' => 'Duelist', 
        'image' => 'human-torch-headbig.webp',
        'abilities' => ['Flame On', 'Fire Ball', 'Supernova']
    ],
    'Iron Fist' => [
        'role' => 'Duelist', 
        'image' => 'iron-fist-headbig.webp',
        'abilities' => ['Chi Burst', 'Dragon Kick', 'Immortal Dragon']
    ],

    // Strategists (Support)
    'Mantis' => [
        'role' => 'Strategist', 
        'image' => 'mantis-headbig.webp',
        'abilities' => ['Life Orb', 'Spore Sleep', 'Soul Resurgence']
    ],
    'Luna Snow' => [
        'role' => 'Strategist', 
        'image' => 'luna-snow-headbig.webp',
        'abilities' => ['Ice Healing', 'Frost Lock', 'Absolute Zero']
    ],
    'Adam Warlock' => [
        'role' => 'Strategist', 
        'image' => 'adam-warlock-headbig.webp',
        'abilities' => ['Quantum Magic', 'Soul Bond', 'Karmic Revival']
    ],
    'Invisible Woman' => [
        'role' => 'Strategist', 
        'image' => 'invisible-woman-headbig.webp',
        'abilities' => ['Force Field', 'Invisibility', 'Fantastic Protection']
    ],
    'Rocket Raccoon' => [
        'role' => 'Strategist', 
        'image' => 'rocket-raccoon-headbig.webp',
        'abilities' => ['Battle Enhancement', 'C4 Bomb', 'Guardian\'s Fate']
    ],
    'Jeff the Land Shark' => [
        'role' => 'Strategist', 
        'image' => 'jeff-the-land-shark-headbig.webp',
        'abilities' => ['Heal Bubble', 'Joyful Splash', 'It\'s Jeff!']
    ],
    'Loki' => [
        'role' => 'Strategist', 
        'image' => 'loki-headbig.webp',
        'abilities' => ['Mystical Spear', 'Illusion', 'God of Mischief']
    ],
    'Cloak and Dagger' => [
        'role' => 'Strategist', 
        'image' => 'cloak-and-dagger-headbig.webp',
        'abilities' => ['Light Daggers', 'Dark Portal', 'Divine Providence']
    ]
];

// Marvel Rivals Maps
$marvelRivalsMaps = [
    [
        'name' => 'Tokyo 2099: Shin-Shibuya',
        'mode' => 'Convoy',
        'description' => 'Escort the payload through futuristic Tokyo'
    ],
    [
        'name' => 'Yggsgard: Royal Palace',
        'mode' => 'Domination',
        'description' => 'Control strategic points in Asgard\'s royal palace'
    ],
    [
        'name' => 'New York 2099: Sanctum Sanctorum',
        'mode' => 'Convergence',
        'description' => 'Capture and escort the artifact through Doctor Strange\'s sanctum'
    ],
    [
        'name' => 'Wakanda: Birnin Zana',
        'mode' => 'Convoy',
        'description' => 'Push through the vibranium-powered capital of Wakanda'
    ],
    [
        'name' => 'Klyntar',
        'mode' => 'Domination',
        'description' => 'Battle for control on the symbiote homeworld'
    ],
    [
        'name' => 'Midtown',
        'mode' => 'Convergence',
        'description' => 'Urban battlefield in the heart of New York City'
    ]
];

function createEventWithLogo()
{
    echo "📅 Creating/Finding Event with Logo...\n";
    
    // Try to find existing event with logo, or create new one
    $event = Event::where('logo', '!=', null)->first();
    
    if (!$event) {
        $event = Event::create([
            'name' => 'Marvel Rivals Championship 2025',
            'slug' => 'marvel-rivals-championship-2025',
            'description' => 'The premier Marvel Rivals competitive tournament featuring the best teams from around the world.',
            'logo' => 'events/logos/marvel-rivals-championship.png',
            'banner' => 'events/banners/marvel-rivals-championship-banner.png',
            'type' => 'championship',
            'tier' => 'S',
            'format' => 'double_elimination',
            'region' => 'INTL',
            'game_mode' => 'Competitive',
            'status' => 'ongoing',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(10),
            'registration_start' => now()->subDays(30),
            'registration_end' => now()->subDays(7),
            'timezone' => 'UTC',
            'max_teams' => 32,
            'organizer_id' => 1,
            'prize_pool' => 500000.00,
            'currency' => 'USD',
            'prize_distribution' => [
                '1st' => 200000,
                '2nd' => 150000,
                '3rd' => 100000,
                '4th' => 50000
            ],
            'featured' => true,
            'public' => true,
            'current_round' => 3,
            'total_rounds' => 8,
            'streams' => [
                'twitch' => 'https://twitch.tv/marvelrivals',
                'youtube' => 'https://youtube.com/marvelrivals'
            ],
            'social_links' => [
                'twitter' => 'https://twitter.com/marvelrivals',
                'discord' => 'https://discord.gg/marvelrivals'
            ]
        ]);
        echo "✅ Created new event: {$event->name}\n";
    } else {
        echo "✅ Found existing event: {$event->name}\n";
    }
    
    return $event;
}

function getRandomHero($role = null)
{
    global $marvelRivalsHeroes;
    
    if ($role) {
        $heroesInRole = array_filter($marvelRivalsHeroes, function($hero) use ($role) {
            return $hero['role'] === $role;
        });
        $heroNames = array_keys($heroesInRole);
        
        // If no heroes found for the role, fall back to all heroes
        if (empty($heroNames)) {
            $heroNames = array_keys($marvelRivalsHeroes);
        }
    } else {
        $heroNames = array_keys($marvelRivalsHeroes);
    }
    
    if (empty($heroNames)) {
        throw new Exception("No heroes available");
    }
    
    return $heroNames[array_rand($heroNames)];
}

function generateRealisticStats($hero, $mapMode, $isWinner = false)
{
    global $marvelRivalsHeroes;
    $heroData = $marvelRivalsHeroes[$hero];
    
    // Base stats influenced by role and whether team won
    $multiplier = $isWinner ? 1.2 : 0.8;
    
    switch ($heroData['role']) {
        case 'Vanguard': // Tanks
            $eliminations = rand(8, 18) * $multiplier;
            $deaths = rand(3, 12);
            $assists = rand(12, 25) * $multiplier;
            $damage_dealt = rand(8000, 15000) * $multiplier;
            $damage_taken = rand(15000, 30000);
            $healing_done = rand(0, 2000);
            $damage_blocked = rand(8000, 20000) * $multiplier;
            $objective_time = rand(180, 350);
            break;
            
        case 'Duelist': // DPS
            $eliminations = rand(15, 35) * $multiplier;
            $deaths = rand(5, 15);
            $assists = rand(5, 15);
            $damage_dealt = rand(12000, 25000) * $multiplier;
            $damage_taken = rand(5000, 12000);
            $healing_done = rand(0, 1000);
            $damage_blocked = rand(0, 2000);
            $objective_time = rand(60, 180);
            break;
            
        case 'Strategist': // Support
            $eliminations = rand(3, 12) * $multiplier;
            $deaths = rand(2, 8);
            $assists = rand(15, 30) * $multiplier;
            $damage_dealt = rand(3000, 8000) * $multiplier;
            $damage_taken = rand(3000, 8000);
            $healing_done = rand(8000, 18000) * $multiplier;
            $damage_blocked = rand(0, 3000);
            $objective_time = rand(120, 250);
            break;
            
        default:
            // Fallback for any unrecognized role
            $eliminations = rand(10, 20) * $multiplier;
            $deaths = rand(4, 10);
            $assists = rand(8, 18);
            $damage_dealt = rand(8000, 15000) * $multiplier;
            $damage_taken = rand(6000, 12000);
            $healing_done = rand(1000, 5000);
            $damage_blocked = rand(1000, 5000);
            $objective_time = rand(100, 200);
    }
    
    // Calculate derived stats
    $kda = $deaths > 0 ? round(($eliminations + $assists) / $deaths, 2) : ($eliminations + $assists);
    $time_played = rand(300, 900); // 5-15 minutes
    $shots_fired = rand(200, 800);
    $shots_hit = rand(80, min($shots_fired, 400));
    $accuracy = $shots_fired > 0 ? round(($shots_hit / $shots_fired) * 100, 2) : 0;
    
    return [
        'hero_played' => $hero,
        'hero_role' => $heroData['role'],
        'time_played_seconds' => $time_played,
        'eliminations' => $eliminations,
        'assists' => $assists,
        'deaths' => $deaths,
        'kda' => $kda,
        'damage' => $damage_dealt,
        'damage_taken' => $damage_taken,
        'healing' => $healing_done,
        'damage_blocked' => $damage_blocked,
        'objective_time' => $objective_time,
        'ultimates_earned' => rand(2, 6),
        'ultimates_used' => rand(1, 5),
        'ultimate_eliminations' => rand(0, 8),
        'shots_fired' => $shots_fired,
        'shots_hit' => $shots_hit,
        'critical_hits' => rand(5, min($shots_hit, 50)),
        'accuracy_percentage' => $accuracy,
        'best_killstreak' => rand(3, 12),
        'solo_kills' => rand(2, 8),
        'environmental_kills' => rand(0, 2),
        'final_blows' => rand(8, min($eliminations, 20)),
        'melee_final_blows' => rand(0, 3),
        'hero_specific_stats' => [
            'ability_1_usage' => rand(10, 30),
            'ability_2_usage' => rand(8, 25),
            'ultimate_damage' => rand(1000, 5000)
        ]
    ];
}

function generateAggregatedStats($hero, $rounds, $isWinner = false)
{
    global $marvelRivalsHeroes;
    $heroData = $marvelRivalsHeroes[$hero];
    
    // Generate stats for each round and aggregate them
    $totalStats = [
        'eliminations' => 0,
        'assists' => 0,
        'deaths' => 0,
        'damage' => 0,
        'damage_taken' => 0,
        'healing' => 0,
        'damage_blocked' => 0,
        'objective_time' => 0,
        'ultimates_earned' => 0,
        'ultimates_used' => 0,
        'ultimate_eliminations' => 0,
        'shots_fired' => 0,
        'shots_hit' => 0,
        'critical_hits' => 0,
        'best_killstreak' => 0,
        'solo_kills' => 0,
        'environmental_kills' => 0,
        'final_blows' => 0,
        'melee_final_blows' => 0,
        'time_played_seconds' => 0
    ];
    
    // Aggregate stats across all rounds
    foreach ($rounds as $round) {
        $roundStats = generateRealisticStats($hero, $round->game_mode, $isWinner);
        
        $totalStats['eliminations'] += $roundStats['eliminations'];
        $totalStats['assists'] += $roundStats['assists'];
        $totalStats['deaths'] += $roundStats['deaths'];
        $totalStats['damage'] += $roundStats['damage'];
        $totalStats['damage_taken'] += $roundStats['damage_taken'];
        $totalStats['healing'] += $roundStats['healing'];
        $totalStats['damage_blocked'] += $roundStats['damage_blocked'];
        $totalStats['objective_time'] += $roundStats['objective_time'];
        $totalStats['ultimates_earned'] += $roundStats['ultimates_earned'];
        $totalStats['ultimates_used'] += $roundStats['ultimates_used'];
        $totalStats['ultimate_eliminations'] += $roundStats['ultimate_eliminations'];
        $totalStats['shots_fired'] += $roundStats['shots_fired'];
        $totalStats['shots_hit'] += $roundStats['shots_hit'];
        $totalStats['critical_hits'] += $roundStats['critical_hits'];
        $totalStats['best_killstreak'] = max($totalStats['best_killstreak'], $roundStats['best_killstreak']);
        $totalStats['solo_kills'] += $roundStats['solo_kills'];
        $totalStats['environmental_kills'] += $roundStats['environmental_kills'];
        $totalStats['final_blows'] += $roundStats['final_blows'];
        $totalStats['melee_final_blows'] += $roundStats['melee_final_blows'];
        $totalStats['time_played_seconds'] += $roundStats['time_played_seconds'];
    }
    
    // Calculate derived stats
    $kda = $totalStats['deaths'] > 0 ? 
        round(($totalStats['eliminations'] + $totalStats['assists']) / $totalStats['deaths'], 2) : 
        ($totalStats['eliminations'] + $totalStats['assists']);
    
    $accuracy = $totalStats['shots_fired'] > 0 ? 
        round(($totalStats['shots_hit'] / $totalStats['shots_fired']) * 100, 2) : 0;
    
    return [
        'hero_played' => $hero,
        'hero_role' => $heroData['role'],
        'time_played_seconds' => $totalStats['time_played_seconds'],
        'eliminations' => $totalStats['eliminations'],
        'assists' => $totalStats['assists'],
        'deaths' => $totalStats['deaths'],
        'kda' => $kda,
        'damage' => $totalStats['damage'],
        'damage_taken' => $totalStats['damage_taken'],
        'healing' => $totalStats['healing'],
        'damage_blocked' => $totalStats['damage_blocked'],
        'objective_time' => $totalStats['objective_time'],
        'ultimates_earned' => $totalStats['ultimates_earned'],
        'ultimates_used' => $totalStats['ultimates_used'],
        'ultimate_eliminations' => $totalStats['ultimate_eliminations'],
        'shots_fired' => $totalStats['shots_fired'],
        'shots_hit' => $totalStats['shots_hit'],
        'critical_hits' => $totalStats['critical_hits'],
        'accuracy_percentage' => $accuracy,
        'best_killstreak' => $totalStats['best_killstreak'],
        'solo_kills' => $totalStats['solo_kills'],
        'environmental_kills' => $totalStats['environmental_kills'],
        'final_blows' => $totalStats['final_blows'],
        'melee_final_blows' => $totalStats['melee_final_blows'],
        'hero_specific_stats' => [
            'ability_1_usage' => rand(20, 60),
            'ability_2_usage' => rand(15, 50),
            'ultimate_damage' => rand(3000, 15000),
            'rounds_played' => count($rounds)
        ]
    ];
}

function createMatchRounds($match, $roundCount = 3)
{
    global $marvelRivalsMaps;
    
    echo "🗺️  Creating {$roundCount} match rounds...\n";
    
    $selectedMaps = array_rand($marvelRivalsMaps, min($roundCount, count($marvelRivalsMaps)));
    if (!is_array($selectedMaps)) {
        $selectedMaps = [$selectedMaps];
    }
    
    $rounds = [];
    $team1MapWins = 0;
    $team2MapWins = 0;
    
    for ($i = 0; $i < $roundCount; $i++) {
        $mapData = $marvelRivalsMaps[$selectedMaps[$i % count($selectedMaps)]];
        
        // Determine map winner (should align with overall match winner)
        $needsWin1 = $team1MapWins < $match->team1_score;
        $needsWin2 = $team2MapWins < $match->team2_score;
        
        if ($needsWin1 && !$needsWin2) {
            $mapWinner = $match->team1_id;
            $team1Score = $mapData['mode'] === 'Domination' ? 2 : 100;
            $team2Score = $mapData['mode'] === 'Domination' ? 1 : rand(70, 99);
            $team1MapWins++;
        } elseif ($needsWin2 && !$needsWin1) {
            $mapWinner = $match->team2_id;
            $team1Score = $mapData['mode'] === 'Domination' ? 1 : rand(70, 99);
            $team2Score = $mapData['mode'] === 'Domination' ? 2 : 100;
            $team2MapWins++;
        } else {
            // Random winner
            $mapWinner = rand(0, 1) ? $match->team1_id : $match->team2_id;
            if ($mapWinner === $match->team1_id) {
                $team1Score = $mapData['mode'] === 'Domination' ? 2 : 100;
                $team2Score = $mapData['mode'] === 'Domination' ? rand(0, 1) : rand(70, 99);
                $team1MapWins++;
            } else {
                $team1Score = $mapData['mode'] === 'Domination' ? rand(0, 1) : rand(70, 99);
                $team2Score = $mapData['mode'] === 'Domination' ? 2 : 100;
                $team2MapWins++;
            }
        }
        
        $round = DB::table('match_rounds')->insertGetId([
            'match_id' => $match->id,
            'round_number' => $i + 1,
            'map_name' => $mapData['name'],
            'game_mode' => $mapData['mode'],
            'status' => 'completed',
            'team1_score' => $team1Score,
            'team2_score' => $team2Score,
            'round_duration' => rand(600, 1080), // 10-18 minutes
            'overtime_used' => rand(0, 1) ? true : false,
            'started_at' => now()->subHours(2)->addMinutes($i * 20),
            'completed_at' => now()->subHours(2)->addMinutes($i * 20 + rand(10, 18)),
            'winner_team_id' => $mapWinner,
            'team1_composition' => json_encode([]),
            'team2_composition' => json_encode([]),
            'objective_progress' => json_encode([
                'description' => $mapData['description'],
                'checkpoints' => $mapData['mode'] === 'Convoy' ? rand(2, 4) : null,
                'control_percentage' => $mapData['mode'] === 'Domination' ? [
                    'team1' => $team1Score > $team2Score ? rand(55, 75) : rand(25, 45),
                    'team2' => $team2Score > $team1Score ? rand(55, 75) : rand(25, 45)
                ] : null
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $rounds[] = (object)[
            'id' => $round,
            'round_number' => $i + 1,
            'map_name' => $mapData['name'],
            'game_mode' => $mapData['mode'],
            'winner_team_id' => $mapWinner
        ];
        
        echo "  ✅ Round " . ($i + 1) . ": {$mapData['name']} ({$mapData['mode']}) - Winner: " . 
             ($mapWinner === $match->team1_id ? 'Team 1' : 'Team 2') . "\n";
    }
    
    return $rounds;
}

function createPlayerStats($match, $rounds)
{
    echo "📊 Creating player statistics...\n";
    
    // Get team rosters
    $team1Players = Player::where('team_id', $match->team1_id)->limit(6)->get();
    $team2Players = Player::where('team_id', $match->team2_id)->limit(6)->get();
    
    if ($team1Players->count() === 0) {
        echo "⚠️  No players found for Team 1 (ID: {$match->team1_id}), creating placeholder players...\n";
        $team1Players = collect();
        for ($i = 1; $i <= 6; $i++) {
            $player = Player::create([
                'username' => "Team1Player{$i}",
                'real_name' => "Player {$i}",
                'team_id' => $match->team1_id,
                'role' => ['Vanguard', 'Duelist', 'Duelist', 'Strategist', 'Strategist', 'Flex'][($i-1) % 6],
                'country' => 'US',
                'region' => 'NA'
            ]);
            $team1Players->push($player);
        }
    }
    
    if ($team2Players->count() === 0) {
        echo "⚠️  No players found for Team 2 (ID: {$match->team2_id}), creating placeholder players...\n";
        $team2Players = collect();
        for ($i = 1; $i <= 6; $i++) {
            $player = Player::create([
                'username' => "Team2Player{$i}",
                'real_name' => "Player {$i}",
                'team_id' => $match->team2_id,
                'role' => ['Vanguard', 'Duelist', 'Duelist', 'Strategist', 'Strategist', 'Flex'][($i-1) % 6],
                'country' => 'US',
                'region' => 'NA'
            ]);
            $team2Players->push($player);
        }
    }
    
    $winnerIsTeam1 = $match->winner_id === $match->team1_id;
    
    echo "  📋 Creating aggregated match stats for players...\n";
    
    // Create aggregated stats for Team 1 players (one record per player per match)
    foreach ($team1Players as $index => $player) {
        $hero = getRandomHero($player->role !== 'Flex' ? $player->role : null);
        
        // Generate aggregated stats across all rounds/maps
        $aggregatedStats = generateAggregatedStats($hero, $rounds, $winnerIsTeam1);
        
        MatchPlayerStat::create(array_merge($aggregatedStats, [
            'match_id' => $match->id,
            'round_id' => $rounds[0]->id, // Use first round ID as reference
            'player_id' => $player->id,
            'team_id' => $match->team1_id,
            'player_of_the_match' => $index === 0 && $winnerIsTeam1, // First player gets MVP if team won
            'current_map' => count($rounds), // Total maps played
        ]));
    }
    
    // Create aggregated stats for Team 2 players
    foreach ($team2Players as $index => $player) {
        $hero = getRandomHero($player->role !== 'Flex' ? $player->role : null);
        
        // Generate aggregated stats across all rounds/maps
        $aggregatedStats = generateAggregatedStats($hero, $rounds, !$winnerIsTeam1);
        
        MatchPlayerStat::create(array_merge($aggregatedStats, [
            'match_id' => $match->id,
            'round_id' => $rounds[0]->id, // Use first round ID as reference
            'player_id' => $player->id,
            'team_id' => $match->team2_id,
            'player_of_the_match' => $index === 0 && !$winnerIsTeam1, // First player gets MVP if team won
            'current_map' => count($rounds), // Total maps played
        ]));
    }
    
    echo "    ✅ Created aggregated stats for " . ($team1Players->count() + $team2Players->count()) . " players\n";
}

function updateExistingMatch($event)
{
    echo "🔄 Finding and updating existing match...\n";
    
    // Get the first match to update
    $match = MatchModel::first();
    
    if (!$match) {
        echo "❌ No existing match found. Creating a new one...\n";
        
        // Get two random teams
        $teams = Team::limit(2)->get();
        if ($teams->count() < 2) {
            throw new Exception("Not enough teams available. Need at least 2 teams.");
        }
        
        $match = MatchModel::create([
            'team1_id' => $teams[0]->id,
            'team2_id' => $teams[1]->id,
            'event_id' => $event->id,
            'format' => 'BO3',
            'status' => 'completed',
            'scheduled_at' => now()->subHours(3),
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subMinutes(30),
            'team1_score' => 2,
            'team2_score' => 1,
            'winner_id' => $teams[0]->id,
            'round' => 'Quarterfinals',
            'featured' => true,
            'stream_urls' => [
                'twitch' => 'https://twitch.tv/marvelrivals',
                'youtube' => 'https://youtube.com/marvelrivals'
            ],
            'viewers' => rand(15000, 50000)
        ]);
        
        echo "✅ Created new match: Team {$teams[0]->name} vs {$teams[1]->name}\n";
    } else {
        // Update existing match
        $match->update([
            'event_id' => $event->id,
            'status' => 'completed',
            'team1_score' => 2,
            'team2_score' => 1,
            'winner_id' => $match->team1_id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subMinutes(30),
            'featured' => true,
            'stream_urls' => [
                'twitch' => 'https://twitch.tv/marvelrivals',
                'youtube' => 'https://youtube.com/marvelrivals'
            ],
            'viewers' => rand(15000, 50000)
        ]);
        
        echo "✅ Updated existing match ID: {$match->id}\n";
        
        // Clean up any existing rounds and stats
        MatchPlayerStat::where('match_id', $match->id)->delete();
        DB::table('match_rounds')->where('match_id', $match->id)->delete();
        echo "🧹 Cleaned up existing match data\n";
    }
    
    return $match;
}

// Main execution
try {
    echo "🚀 Starting match data population...\n\n";
    
    // Step 1: Create/find event with logo
    $event = createEventWithLogo();
    
    // Step 2: Update existing match or create new one
    $match = updateExistingMatch($event);
    
    // Step 3: Create match rounds
    $rounds = createMatchRounds($match, 3);
    
    // Step 4: Create player statistics
    createPlayerStats($match, $rounds);
    
    // Step 5: Count created stats
    $statsCount = MatchPlayerStat::where('match_id', $match->id)->count();
    echo "✅ Player stats created: {$statsCount} records\n";
    
    echo "\n🎉 SUCCESS! Match data population completed!\n";
    echo "=====================================\n";
    echo "📋 Summary:\n";
    echo "  - Event: {$event->name}\n";
    echo "  - Match ID: {$match->id}\n";
    echo "  - Teams: {$match->team1->name} vs {$match->team2->name}\n";
    echo "  - Score: {$match->team1_score} - {$match->team2_score}\n";
    echo "  - Winner: {$match->winner->name}\n";
    echo "  - Rounds: " . count($rounds) . "\n";
    echo "  - Player Stats: {$statsCount}\n";
    echo "  - Heroes Used: " . count(array_keys($marvelRivalsHeroes)) . " available\n\n";
    
    echo "🔗 You can now view this data through your API endpoints:\n";
    echo "  - Match Details: GET /api/matches/{$match->id}\n";
    echo "  - Match Stats: GET /api/matches/{$match->id}/stats\n";
    echo "  - Event Details: GET /api/events/{$event->id}\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✨ Script completed successfully!\n";