<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{User, Team, Player, Event, Match, ForumThread};
use Spatie\Permission\Models\Role;

class MRVLProductionSeeder extends Seeder
{
    public function run()
    {
        // Create Roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'moderator']);
        Role::firstOrCreate(['name' => 'user']);

        // Create Admin User
        $admin = User::firstOrCreate([
            'email' => 'jhonny@ar-mediia.com'
        ], [
            'name' => 'Johnny Rodriguez',
            'password' => 'password123',
            'status' => 'active'
        ]);
        $admin->assignRole(['admin', 'moderator', 'user']);

        // Create Teams
        $teams = [
            [
                'name' => 'Team Stark Industries',
                'short_name' => 'STARK',
                'logo' => '🔥',
                'region' => 'NA',
                'country' => 'United States',
                'flag' => '🇺🇸',
                'rating' => 2458,
                'rank' => 1,
                'win_rate' => 92.3,
                'points' => 2458,
                'record' => '32-3',
                'peak' => 2500,
                'streak' => 'W5',
                'founded' => '2024',
                'captain' => 'IronMan_Tony',
                'coach' => 'Nick Fury',
                'website' => 'stark.gg',
                'earnings' => '$450,000',
                'social_media' => [
                    'twitter' => '@StarkEsports',
                    'twitch' => 'stark_gaming',
                    'youtube' => 'StarkIndustriesGaming'
                ],
                'achievements' => [
                    'Marvel Rivals Championship 2024',
                    'NA Regional Winners'
                ]
            ],
            [
                'name' => 'Wakanda Protectors',
                'short_name' => 'WAKANDA',
                'logo' => '⚡',
                'region' => 'NA',
                'country' => 'United States',
                'flag' => '🇺🇸',
                'rating' => 2387,
                'rank' => 2,
                'win_rate' => 89.1,
                'points' => 2387,
                'record' => '29-4',
                'peak' => 2450,
                'streak' => 'W3',
                'founded' => '2024',
                'captain' => 'BlackPanther_T',
                'coach' => 'Shuri',
                'website' => 'wakanda.gg',
                'earnings' => '$325,000',
                'social_media' => [
                    'twitter' => '@WakandaEsports',
                    'twitch' => 'wakanda_forever',
                    'youtube' => 'WakandaProtectorsGaming'
                ],
                'achievements' => [
                    'Marvel Rivals Finals Runner-up',
                    'Perfect Season Record'
                ]
            ],
            [
                'name' => 'S.H.I.E.L.D. Tactical',
                'short_name' => 'SHIELD',
                'logo' => '🛡️',
                'region' => 'NA',
                'country' => 'United States',
                'flag' => '🇺🇸',
                'rating' => 2201,
                'rank' => 3,
                'win_rate' => 78.5,
                'points' => 2201,
                'record' => '26-7',
                'peak' => 2300,
                'streak' => 'W2',
                'founded' => '2024',
                'captain' => 'Agent_Coulson',
                'coach' => 'Maria Hill',
                'website' => 'shield.gg',
                'earnings' => '$180,000',
                'social_media' => [
                    'twitter' => '@ShieldEsports',
                    'twitch' => 'shield_gaming'
                ],
                'achievements' => ['Regional Qualifier Winner']
            ],
            [
                'name' => 'X-Force Elite',
                'short_name' => 'XFORCE',
                'logo' => '⚔️',
                'region' => 'EU',
                'country' => 'United Kingdom',
                'flag' => '🇬🇧',
                'rating' => 2156,
                'rank' => 4,
                'win_rate' => 75.2,
                'points' => 2156,
                'record' => '24-8',
                'peak' => 2200,
                'streak' => 'L1',
                'founded' => '2024',
                'captain' => 'Wolverine_Logan',
                'coach' => 'Professor_X',
                'website' => 'xforce.gg',
                'earnings' => '$145,000',
                'social_media' => [
                    'twitter' => '@XForceEsports',
                    'twitch' => 'xforce_gaming'
                ],
                'achievements' => ['EU Regional Champions']
            ],
            [
                'name' => 'Asgard Warriors',
                'short_name' => 'ASGARD',
                'logo' => '⚡',
                'region' => 'EU',
                'country' => 'Norway',
                'flag' => '🇳🇴',
                'rating' => 2089,
                'rank' => 5,
                'win_rate' => 72.8,
                'points' => 2089,
                'record' => '22-10',
                'peak' => 2150,
                'streak' => 'W1',
                'founded' => '2024',
                'captain' => 'Thor_Odinson',
                'coach' => 'Odin',
                'website' => 'asgard.gg',
                'earnings' => '$98,000',
                'social_media' => [
                    'twitter' => '@AsgardEsports',
                    'twitch' => 'asgard_warriors'
                ],
                'achievements' => ['Nordic Championship 2024']
            ]
        ];

        foreach ($teams as $teamData) {
            Team::firstOrCreate(['short_name' => $teamData['short_name']], $teamData);
        }

        // Create Players
        $starkTeam = Team::where('short_name', 'STARK')->first();
        $wakandaTeam = Team::where('short_name', 'WAKANDA')->first();
        $shieldTeam = Team::where('short_name', 'SHIELD')->first();
        $xforceTeam = Team::where('short_name', 'XFORCE')->first();
        $asgardTeam = Team::where('short_name', 'ASGARD')->first();

        $players = [
            // Team Stark Industries
            [
                'username' => 'IronMan_Tony',
                'real_name' => 'Tony Stark',
                'team_id' => $starkTeam->id,
                'role' => 'Duelist',
                'main_hero' => 'Iron Man',
                'alt_heroes' => ['Spider-Man', 'Thor'],
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 2945.2,
                'age' => 25,
                'earnings' => '$125,000',
                'social_media' => ['twitter' => '@TonyStark_IM', 'twitch' => 'ironman_tony']
            ],
            [
                'username' => 'SpiderMan_Peter',
                'real_name' => 'Peter Parker',
                'team_id' => $starkTeam->id,
                'role' => 'Tank',
                'main_hero' => 'Spider-Man',
                'alt_heroes' => ['Hulk', 'Captain America'],
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 2823.7,
                'age' => 21,
                'earnings' => '$95,000'
            ],
            [
                'username' => 'DrStrange_Stephen',
                'real_name' => 'Stephen Strange',
                'team_id' => $starkTeam->id,
                'role' => 'Support',
                'main_hero' => 'Doctor Strange',
                'alt_heroes' => ['Mantis', 'Luna Snow'],
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 2756.3,
                'age' => 28,
                'earnings' => '$87,000'
            ],

            // Wakanda Protectors
            [
                'username' => 'BlackPanther_T',
                'real_name' => 'T\'Challa',
                'team_id' => $wakandaTeam->id,
                'role' => 'Duelist',
                'main_hero' => 'Black Panther',
                'alt_heroes' => ['Storm', 'Hulk'],
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 2892.7,
                'age' => 28,
                'earnings' => '$98,000'
            ],
            [
                'username' => 'Storm_Ororo',
                'real_name' => 'Ororo Munroe',
                'team_id' => $wakandaTeam->id,
                'role' => 'Controller',
                'main_hero' => 'Storm',
                'alt_heroes' => ['Scarlet Witch', 'Magneto'],
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 2734.1,
                'age' => 26,
                'earnings' => '$82,000'
            ],
            [
                'username' => 'Shuri_Princess',
                'real_name' => 'Shuri',
                'team_id' => $wakandaTeam->id,
                'role' => 'Support',
                'main_hero' => 'Luna Snow',
                'alt_heroes' => ['Rocket Raccoon', 'Mantis'],
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 2689.5,
                'age' => 22,
                'earnings' => '$75,000'
            ],

            // S.H.I.E.L.D. Tactical
            [
                'username' => 'Agent_Coulson',
                'real_name' => 'Phil Coulson',
                'team_id' => $shieldTeam->id,
                'role' => 'Support',
                'main_hero' => 'Mantis',
                'alt_heroes' => ['Luna Snow', 'Rocket Raccoon'],
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 2678.3,
                'age' => 32,
                'earnings' => '$65,000'
            ],
            [
                'username' => 'CaptainAmerica_Steve',
                'real_name' => 'Steve Rogers',
                'team_id' => $shieldTeam->id,
                'role' => 'Tank',
                'main_hero' => 'Captain America',
                'alt_heroes' => ['Hulk', 'Doctor Strange'],
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 2645.8,
                'age' => 30,
                'earnings' => '$58,000'
            ],

            // X-Force Elite
            [
                'username' => 'Wolverine_Logan',
                'real_name' => 'James Howlett',
                'team_id' => $xforceTeam->id,
                'role' => 'Duelist',
                'main_hero' => 'Wolverine',
                'alt_heroes' => ['Black Panther', 'Spider-Man'],
                'region' => 'EU',
                'country' => 'United Kingdom',
                'rating' => 2789.4,
                'age' => 29,
                'earnings' => '$78,000'
            ],
            [
                'username' => 'Deadpool_Wade',
                'real_name' => 'Wade Wilson',
                'team_id' => $xforceTeam->id,
                'role' => 'Duelist',
                'main_hero' => 'Deadpool',
                'alt_heroes' => ['Iron Man', 'Spider-Man'],
                'region' => 'EU',
                'country' => 'United Kingdom',
                'rating' => 2712.6,
                'age' => 27,
                'earnings' => '$69,000'
            ],

            // Asgard Warriors
            [
                'username' => 'Thor_Odinson',
                'real_name' => 'Thor',
                'team_id' => $asgardTeam->id,
                'role' => 'Tank',
                'main_hero' => 'Thor',
                'alt_heroes' => ['Hulk', 'Captain America'],
                'region' => 'EU',
                'country' => 'Norway',
                'rating' => 2698.1,
                'age' => 26,
                'earnings' => '$61,000'
            ],
            [
                'username' => 'Loki_Laufeyson',
                'real_name' => 'Loki',
                'team_id' => $asgardTeam->id,
                'role' => 'Controller',
                'main_hero' => 'Loki',
                'alt_heroes' => ['Scarlet Witch', 'Doctor Strange'],
                'region' => 'EU',
                'country' => 'Norway',
                'rating' => 2634.7,
                'age' => 25,
                'earnings' => '$55,000'
            ]
        ];

        foreach ($players as $playerData) {
            Player::firstOrCreate(['username' => $playerData['username']], $playerData);
        }

        // Create Events
        $events = [
            [
                'name' => 'Marvel Rivals World Championship 2025',
                'type' => 'International',
                'status' => 'upcoming',
                'start_date' => '2025-03-15',
                'end_date' => '2025-03-22',
                'prize_pool' => '$1,000,000',
                'team_count' => 32,
                'location' => 'Los Angeles, CA',
                'organizer' => 'Marvel Esports',
                'format' => 'Double Elimination',
                'description' => 'The ultimate Marvel Rivals championship featuring the world\'s best teams competing for the largest prize pool in the game\'s history.',
                'registration_open' => true,
                'stream_viewers' => 0
            ],
            [
                'name' => 'NA Regional Championship',
                'type' => 'Regional',
                'status' => 'live',
                'start_date' => '2025-01-20',
                'end_date' => '2025-01-25',
                'prize_pool' => '$250,000',
                'team_count' => 16,
                'location' => 'Online',
                'organizer' => 'Marvel Rivals League',
                'format' => 'Swiss + Playoffs',
                'description' => 'North American teams battle for regional supremacy and qualification to the World Championship.',
                'registration_open' => false,
                'stream_viewers' => 45720
            ],
            [
                'name' => 'EU Regional Championship',
                'type' => 'Regional',
                'status' => 'upcoming',
                'start_date' => '2025-02-10',
                'end_date' => '2025-02-15',
                'prize_pool' => '$250,000',
                'team_count' => 16,
                'location' => 'Berlin, Germany',
                'organizer' => 'Marvel Rivals League',
                'format' => 'Swiss + Playoffs',
                'description' => 'European teams compete for regional dominance and World Championship qualification.',
                'registration_open' => true,
                'stream_viewers' => 0
            ],
            [
                'name' => 'Community Cup #1',
                'type' => 'Community',
                'status' => 'completed',
                'start_date' => '2024-12-15',
                'end_date' => '2024-12-18',
                'prize_pool' => '$50,000',
                'team_count' => 64,
                'location' => 'Online',
                'organizer' => 'MRVL Community',
                'format' => 'Single Elimination',
                'description' => 'Open tournament for all teams to compete and showcase their skills.',
                'registration_open' => false,
                'stream_viewers' => 0
            ]
        ];

        foreach ($events as $eventData) {
            Event::firstOrCreate(['name' => $eventData['name']], $eventData);
        }

        // Create Matches
        $worldChampionship = Event::where('name', 'Marvel Rivals World Championship 2025')->first();
        $naRegional = Event::where('name', 'NA Regional Championship')->first();
        $euRegional = Event::where('name', 'EU Regional Championship')->first();

        $matches = [
            // Live Match - NA Regional
            [
                'team1_id' => $starkTeam->id,
                'team2_id' => $wakandaTeam->id,
                'event_id' => $naRegional->id,
                'scheduled_at' => now()->addHours(2),
                'status' => 'live',
                'format' => 'BO5',
                'team1_score' => 2,
                'team2_score' => 1,
                'viewers' => 45720,
                'stream_url' => 'https://twitch.tv/mrvl_championship',
                'current_map' => 'Bifrost Arena',
                'maps_data' => [
                    ['name' => 'Asgard Throne Room', 'team1Score' => 2, 'team2Score' => 1, 'status' => 'completed'],
                    ['name' => 'Helicarrier Command', 'team1Score' => 1, 'team2Score' => 2, 'status' => 'completed'],
                    ['name' => 'Sanctum Sanctorum', 'team1Score' => 2, 'team2Score' => 0, 'status' => 'completed'],
                    ['name' => 'Bifrost Arena', 'team1Score' => 1, 'team2Score' => 1, 'status' => 'live'],
                    ['name' => 'Wakanda Royal Palace', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming']
                ]
            ],
            // Upcoming Match - NA Regional
            [
                'team1_id' => $shieldTeam->id,
                'team2_id' => $wakandaTeam->id,
                'event_id' => $naRegional->id,
                'scheduled_at' => now()->addHours(4),
                'status' => 'upcoming',
                'format' => 'BO3',
                'team1_score' => 0,
                'team2_score' => 0,
                'viewers' => 0,
                'stream_url' => 'https://twitch.tv/mrvl_championship'
            ],
            // Upcoming Match - EU Regional
            [
                'team1_id' => $xforceTeam->id,
                'team2_id' => $asgardTeam->id,
                'event_id' => $euRegional->id,
                'scheduled_at' => now()->addDays(2),
                'status' => 'upcoming',
                'format' => 'BO3',
                'team1_score' => 0,
                'team2_score' => 0,
                'viewers' => 0,
                'stream_url' => 'https://twitch.tv/mrvl_eu'
            ],
            // Completed Match
            [
                'team1_id' => $starkTeam->id,
                'team2_id' => $shieldTeam->id,
                'event_id' => $naRegional->id,
                'scheduled_at' => now()->subHours(6),
                'status' => 'completed',
                'format' => 'BO3',
                'team1_score' => 2,
                'team2_score' => 0,
                'viewers' => 0,
                'maps_data' => [
                    ['name' => 'Asgard Throne Room', 'team1Score' => 2, 'team2Score' => 1, 'status' => 'completed'],
                    ['name' => 'Helicarrier Command', 'team1Score' => 2, 'team2Score' => 0, 'status' => 'completed']
                ]
            ]
        ];

        foreach ($matches as $matchData) {
            Match::create($matchData);
        }

        // Create Forum Threads
        $threads = [
            [
                'title' => 'MRVL World Championship 2025 - Discussion & Predictions',
                'content' => 'The biggest Marvel Rivals tournament is coming! $1M prize pool, 32 teams from around the world. Who do you think will take the crown? Team Stark Industries looking unstoppable but Wakanda Protectors are right behind them. Share your predictions!',
                'user_id' => $admin->id,
                'category' => 'Events',
                'replies' => 127,
                'views' => 3245,
                'pinned' => true,
                'last_reply_at' => now()->subMinutes(5)
            ],
            [
                'title' => 'LIVE: Team Stark vs Wakanda Protectors - Match Discussion',
                'content' => 'Epic BO5 happening right now! Currently 2-1 in favor of Stark Industries. That play by IronMan_Tony on Bifrost Arena was insane! Come discuss the live action.',
                'user_id' => $admin->id,
                'category' => 'Matches',
                'replies' => 89,
                'views' => 1876,
                'pinned' => false,
                'last_reply_at' => now()->subMinutes(2)
            ],
            [
                'title' => 'Hero Tier List Discussion - Current Meta Analysis',
                'content' => 'What do you think about the current meta? Iron Man and Black Panther seem to dominate the Duelist role. Thor is still strong as Tank. Support role is pretty balanced between Mantis, Luna Snow, and Rocket Raccoon.',
                'user_id' => $admin->id,
                'category' => 'Strategy',
                'replies' => 56,
                'views' => 892,
                'pinned' => false,
                'last_reply_at' => now()->subHours(3)
            ],
            [
                'title' => 'Welcome to MRVL - Introduce Yourself!',
                'content' => 'New to the MRVL community? Introduce yourself here! Tell us about your favorite team, player, and hero. We\'re excited to have you as part of the Marvel Rivals competitive scene!',
                'user_id' => $admin->id,
                'category' => 'General',
                'replies' => 234,
                'views' => 5634,
                'pinned' => true,
                'last_reply_at' => now()->subHours(1)
            ],
            [
                'title' => 'Player Transfer Rumors - Winter 2025',
                'content' => 'Hearing rumors about potential player transfers before the World Championship. Anyone have insider info? Some big names might be switching teams!',
                'user_id' => $admin->id,
                'category' => 'Teams',
                'replies' => 73,
                'views' => 1456,
                'pinned' => false,
                'last_reply_at' => now()->subHours(8)
            ]
        ];

        foreach ($threads as $threadData) {
            ForumThread::create($threadData);
        }

        $this->command->info('✅ MRVL Production Seeder completed successfully!');
        $this->command->info('📊 Created:');
        $this->command->info('   → 1 Admin user (jhonny@ar-mediia.com / password123)');
        $this->command->info('   → 5 Professional teams with full data');
        $this->command->info('   → 12 Players with realistic stats');
        $this->command->info('   → 4 Major events (World Championship, Regional tournaments)');
        $this->command->info('   → 4 Matches (1 live, 2 upcoming, 1 completed)');
        $this->command->info('   → 5 Forum threads with community engagement');
        $this->command->info('🚀 Ready for production deployment!');
    }
}