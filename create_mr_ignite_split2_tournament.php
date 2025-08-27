<?php

/**
 * Marvel Rivals Ignite 2025 Stage 2 Americas Open Qualifier Split 2
 * Tournament Creation Script
 * 
 * This script creates the comprehensive tournament structure for the
 * Marvel Rivals Ignite 2025 Stage 2 Americas Open Qualifier Split 2
 * featuring 98 teams including the qualified teams from Split 1.
 * 
 * Tournament Structure:
 * - Swiss Rounds (5 rounds) to narrow 98 teams to Top 16
 * - Single Elimination Bracket (Top 16 -> Champion)
 * - 8 spots advance to Stage 2 Closed Qualifier
 * 
 * Features:
 * - Proper error handling and validation
 * - Database transaction safety
 * - Comprehensive bracket generation
 * - Real qualified teams from Split 1
 * - Placeholder teams for Open Qualifier spots
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\User;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\TournamentRegistration;

// Initialize Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class MarvelRivalsIgniteSplit2Creator
{
    private $organizer;
    private $tournament;
    private $parentTournament;
    private $qualifiedTeams = [];
    private $openQualifierTeams = [];
    private $allTeams = [];
    
    // Qualified teams from Split 1
    private $split1QualifiedTeams = [
        [
            'name' => 'Team Nemesis',
            'short_name' => 'NEM',
            'region' => 'NA',
            'country' => 'United States',
            'country_code' => 'US',
            'logo' => '/teams/team-nemesis-logo.png',
            'rating' => 1850,
            'rank' => 1,
            'qualified_placement' => 1
        ],
        [
            'name' => 'DarkZero',
            'short_name' => 'DZ',
            'region' => 'NA',
            'country' => 'United States',
            'country_code' => 'US',
            'logo' => '/teams/darkzero-logo.png',
            'rating' => 1820,
            'rank' => 2,
            'qualified_placement' => 2
        ],
        [
            'name' => 'FYR Strays',
            'short_name' => 'FYR',
            'region' => 'NA',
            'country' => 'United States',
            'country_code' => 'US',
            'logo' => '/teams/fyr-strays-logo.png',
            'rating' => 1800,
            'rank' => 3,
            'qualified_placement' => 3
        ],
        [
            'name' => 'Busy At Work',
            'short_name' => 'BAW',
            'region' => 'NA',
            'country' => 'United States',
            'country_code' => 'US',
            'logo' => '/teams/busy-at-work-logo.png',
            'rating' => 1785,
            'rank' => 4,
            'qualified_placement' => 4
        ],
        [
            'name' => 'Dreamland',
            'short_name' => 'DL',
            'region' => 'NA',
            'country' => 'United States',
            'country_code' => 'US',
            'logo' => '/teams/dreamland-logo.png',
            'rating' => 1770,
            'rank' => 5,
            'qualified_placement' => 5
        ],
        [
            'name' => 'Solaris',
            'short_name' => 'SOL',
            'region' => 'NA',
            'country' => 'United States',
            'country_code' => 'US',
            'logo' => '/teams/solaris-logo.png',
            'rating' => 1755,
            'rank' => 6,
            'qualified_placement' => 6
        ],
        [
            'name' => 'AILANIWIND',
            'short_name' => 'ALW',
            'region' => 'NA',
            'country' => 'United States',
            'country_code' => 'US',
            'logo' => '/teams/ailaniwind-logo.png',
            'rating' => 1740,
            'rank' => 7,
            'qualified_placement' => 7
        ]
    ];

    public function __construct()
    {
        $this->initializeDatabase();
        $this->createOrganizer();
        echo "🚀 Marvel Rivals Ignite Split 2 Tournament Creator Initialized\n";
    }

    private function initializeDatabase()
    {
        echo "📊 Checking database connection...\n";
        
        try {
            DB::connection()->getPdo();
            echo "✅ Database connected successfully\n";
        } catch (Exception $e) {
            echo "❌ Database connection failed: {$e->getMessage()}\n";
            exit(1);
        }
    }

    private function createOrganizer()
    {
        echo "👤 Creating tournament organizer...\n";
        
        $this->organizer = User::firstOrCreate(
            ['email' => 'marvel-rivals-ignite@netease.com'],
            [
                'name' => 'Marvel Rivals Ignite Tournament Admin',
                'password' => bcrypt('mr_ignite_2025_secure'),
                'role' => 'admin'
            ]
        );
        
        echo "✅ Organizer created: {$this->organizer->name}\n";
    }

    /**
     * Get the system user for tournament registrations
     * Falls back to organizer if system user not found
     */
    private function getSystemUser()
    {
        // Try to get system user from settings
        $systemUserId = DB::table('settings')
            ->where('key', 'tournament_system_user_id')
            ->value('value');
            
        if ($systemUserId) {
            $systemUser = User::find($systemUserId);
            if ($systemUser) {
                return $systemUser;
            }
        }
        
        // Fallback to tournament organizer
        return $this->organizer;
    }

    public function createParentTournament()
    {
        echo "🏆 Creating Marvel Rivals Ignite 2025 Stage 2 Americas parent tournament...\n";
        
        $startDate = now()->addDays(14); // Tournament starts in 2 weeks
        $endDate = $startDate->copy()->addDays(7); // Week-long tournament
        
        $this->parentTournament = Tournament::firstOrCreate(
            ['slug' => 'mr-ignite-2025-stage-2-americas'],
            [
                'name' => 'Marvel Rivals Ignite 2025 Stage 2 Americas',
            'slug' => 'mr-ignite-2025-stage-2-americas',
            'type' => 'ignite',
            'format' => 'group_stage_playoffs',
            'status' => 'registration_open',
            'description' => 'The second stage of Marvel Rivals Ignite 2025 Americas featuring Open Qualifiers and Closed Qualifiers leading to the main event. Top teams will advance to compete for championship glory and substantial prize pools.',
            'region' => 'NA',
            'prize_pool' => 150000.00,
            'currency' => 'USD',
            'max_teams' => 200, // Total across all qualifiers
            'min_teams' => 64,
            'team_count' => 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'registration_start' => now(),
            'registration_end' => $startDate->copy()->subDays(3),
            'check_in_start' => $startDate->copy()->subHours(2),
            'check_in_end' => $startDate->copy()->subMinutes(30),
            'timezone' => 'America/New_York',
            'organizer_id' => $this->organizer->id,
            'logo' => '/events/mr-ignite-2025-logo.jpg',
            'banner' => '/events/mr-ignite-stage-2-americas-banner.jpg',
            'featured' => true,
            'public' => true,
            'current_phase' => 'registration',
            'settings' => [
                'allow_substitutions' => true,
                'max_substitutions_per_match' => 1,
                'technical_pause_limit' => 3,
                'disconnection_rules' => 'standard_ignite',
                'anti_cheat_required' => true,
                'streaming_required' => false,
                'replay_required' => true
            ],
            'rules' => [
                'match_format' => 'Swiss: Best of 1, Bracket: Best of 3, Finals: Best of 5',
                'map_selection' => 'Tournament officials select maps for Swiss, teams pick/ban for bracket',
                'roster_lock' => '48 hours before qualifier start',
                'substitute_deadline' => '1 hour before match',
                'forfeit_time' => '10 minutes after scheduled start',
                'technical_issues' => 'Matches may be paused for up to 5 minutes total',
                'conduct' => 'All players must follow Marvel Rivals Community Guidelines and Ignite Rules',
                'streaming' => 'Main bracket matches will be streamed officially'
            ],
            'qualification_settings' => [
                'open_qualifier_spots' => 8,
                'closed_qualifier_spots' => 8,
                'direct_invites' => 0,
                'total_advancing' => 16
            ],
            'map_pool' => [
                'convoy',
                'tokyo_2099_convoy',
                'tokyo_2099_control',
                'klyntar',
                'midtown_convoy',
                'midtown_control',
                'intergalactic_empire_of_wakanda',
                'wakanda_control'
            ],
            'match_format_settings' => [
                'swiss_round' => 'bo1',
                'bracket_round_1' => 'bo3',
                'bracket_quarterfinals' => 'bo3',
                'bracket_semifinals' => 'bo3',
                'bracket_finals' => 'bo5',
                'default' => 'bo1'
            ],
            'stream_urls' => [
                'primary' => 'https://twitch.tv/marvelrivals_official',
                'secondary' => 'https://youtube.com/marvelrivalsesports'
            ],
            'social_links' => [
                'twitter' => 'https://twitter.com/MarvelRivals',
                'discord' => 'https://discord.gg/marvelrivals'
            ],
                'contact_info' => [
                    'admin_email' => 'ignite@marvelrivals.com',
                    'technical_support' => 'tech-support@marvelrivals.com'
                ]
            ]
        );

        echo "✅ Parent tournament created: {$this->parentTournament->name}\n";
        return $this->parentTournament;
    }

    public function createSplit2Qualifier()
    {
        echo "🏆 Creating Open Qualifier Split 2 tournament...\n";
        
        $startDate = $this->parentTournament->start_date->copy()->addDays(1);
        $endDate = $startDate->copy()->addDays(3);
        
        $this->tournament = Tournament::firstOrCreate(
            ['slug' => 'mr-ignite-2025-stage-2-americas-oq-split-2'],
            [
                'name' => 'Marvel Rivals Ignite 2025 Stage 2 Americas - Open Qualifier Split 2',
            'slug' => 'mr-ignite-2025-stage-2-americas-oq-split-2',
            'type' => 'qualifier',
            'format' => 'swiss',
            'status' => 'registration_open',
            'description' => 'Open Qualifier Split 2 for Marvel Rivals Ignite 2025 Stage 2 Americas. 98 teams compete through Swiss rounds followed by single elimination bracket. Top 8 teams advance to the Closed Qualifier. Features qualified teams from Split 1 and open registration slots.',
            'region' => 'NA',
            'prize_pool' => 15000.00,
            'currency' => 'USD',
            'max_teams' => 98,
            'min_teams' => 64,
            'team_count' => 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'registration_start' => now(),
            'registration_end' => $startDate->copy()->subDays(1),
            'check_in_start' => $startDate->copy()->subHours(1),
            'check_in_end' => $startDate->copy()->subMinutes(15),
            'timezone' => 'America/New_York',
            'organizer_id' => $this->organizer->id,
            'logo' => '/events/mr-ignite-oq-split-2-logo.jpg',
            'banner' => '/events/mr-ignite-oq-split-2-banner.jpg',
            'featured' => true,
            'public' => true,
            'current_phase' => 'registration',
            'settings' => [
                'parent_tournament_id' => $this->parentTournament->id,
                'qualifier_type' => 'open_qualifier_split_2',
                'swiss_rounds' => 5,
                'swiss_advancement' => 16,
                'bracket_advancement' => 8,
                'allow_substitutions' => true,
                'max_substitutions_per_match' => 1,
                'technical_pause_limit' => 3,
                'anti_cheat_required' => true,
                'replay_required' => true
            ],
            'rules' => [
                'swiss_format' => 'Best of 1 matches, 5 rounds',
                'swiss_pairing' => 'Swiss system pairing based on match record',
                'swiss_advancement' => 'Top 16 teams advance to Single Elimination bracket',
                'bracket_format' => 'Single Elimination, Best of 3 until Finals (Best of 5)',
                'advancement' => 'Top 8 teams advance to Closed Qualifier',
                'map_selection_swiss' => 'Pre-determined by tournament officials',
                'map_selection_bracket' => 'Standard pick/ban system',
                'roster_lock' => '24 hours before tournament start',
                'forfeit_time' => '10 minutes after scheduled start'
            ],
            'qualification_settings' => [
                'swiss_rounds' => 5,
                'swiss_wins_required' => 3,
                'swiss_losses_eliminated' => 3,
                'bracket_teams' => 16,
                'advancement_spots' => 8,
                'prize_distribution' => [
                    1 => 5000,
                    2 => 3000,
                    3 => 2000,
                    4 => 1500,
                    '5-8' => 1000
                ]
            ],
            'map_pool' => [
                'convoy',
                'tokyo_2099_convoy',
                'klyntar',
                'midtown_convoy',
                'intergalactic_empire_of_wakanda',
                'tokyo_2099_control',
                'midtown_control',
                'wakanda_control'
            ],
            'match_format_settings' => [
                'swiss_round_1' => 'bo1',
                'swiss_round_2' => 'bo1',
                'swiss_round_3' => 'bo1',
                'swiss_round_4' => 'bo1',
                'swiss_round_5' => 'bo1',
                'bracket_round_1' => 'bo3',
                'bracket_quarterfinals' => 'bo3',
                'bracket_semifinals' => 'bo3',
                'bracket_finals' => 'bo5',
                'default' => 'bo1'
            ],
            'phase_data' => [
                'phase_1' => [
                    'name' => 'Swiss Rounds',
                    'type' => 'swiss',
                    'rounds' => 5,
                    'teams' => 98,
                    'advancement' => 16,
                    'format' => 'bo1'
                ],
                'phase_2' => [
                    'name' => 'Single Elimination Bracket',
                    'type' => 'single_elimination',
                    'teams' => 16,
                    'advancement' => 8,
                    'format' => 'bo3'
                ]
                ]
            ]
        );

        echo "✅ Split 2 Qualifier created: {$this->tournament->name}\n";
        echo "💰 Prize Pool: {$this->tournament->formatted_prize_pool}\n";
        return $this->tournament;
    }

    public function createOrFindTeams()
    {
        echo "👥 Creating/finding qualified teams and open qualifier teams...\n";
        
        // Create qualified teams from Split 1
        foreach ($this->split1QualifiedTeams as $teamData) {
            $team = Team::firstOrCreate(
                ['name' => $teamData['name']],
                [
                    'short_name' => $teamData['short_name'],
                    'slug' => strtolower(str_replace([' ', '.'], ['-', ''], $teamData['name'])),
                    'logo' => $teamData['logo'],
                    'region' => $teamData['region'],
                    'platform' => 'PC',
                    'game' => 'Marvel Rivals',
                    'country' => $teamData['country'],
                    'country_code' => $teamData['country_code'],
                    'flag' => "/flags/{$teamData['country_code']}.png",
                    'rating' => $teamData['rating'],
                    'rank' => $teamData['rank'],
                    'elo_rating' => $teamData['rating'],
                    'peak_elo' => $teamData['rating'] + rand(50, 100),
                    'status' => 'active',
                    'founded' => '2024',
                    'player_count' => 6,
                    'achievements' => [
                        'Marvel Rivals Ignite 2025 Stage 2 Americas OQ Split 1 Qualified',
                        "Placed #{$teamData['qualified_placement']} in Split 1"
                    ],
                    'earnings' => rand(5000, 25000),
                    'wins' => rand(20, 40),
                    'losses' => rand(8, 18),
                    'maps_won' => rand(50, 90),
                    'maps_lost' => rand(25, 55),
                    'win_rate' => round(rand(65, 85), 1),
                    'map_win_rate' => round(rand(60, 80), 1)
                ]
            );
            
            $this->qualifiedTeams[] = $team;
            echo "  ✅ Qualified team: {$team->name} (Split 1 #{$teamData['qualified_placement']})\n";
        }

        // Generate placeholder teams for open qualifier spots (91 teams needed: 98 - 7 qualified)
        echo "🌐 Generating open qualifier placeholder teams...\n";
        
        $placeholderTeamNames = [
            'Phantom Squad', 'Digital Warriors', 'Cyber Knights', 'Storm Breakers', 'Nova Elite',
            'Thunder Strike', 'Shadow Runners', 'Apex Hunters', 'Vortex Gaming', 'Fusion Force',
            'Crimson Tide', 'Iron Wolves', 'Sky Raiders', 'Neon Pulse', 'Crystal Guards',
            'Flame Legion', 'Frost Bite', 'Lightning Bolts', 'Wind Walkers', 'Earth Shakers',
            'Fire Storm', 'Ice Breakers', 'Thunder Birds', 'Storm Chasers', 'Wave Riders',
            'Star Guardians', 'Cosmic Crew', 'Galactic Force', 'Solar Flare', 'Lunar Eclipse',
            'Meteor Strike', 'Comet Tail', 'Aurora Borealis', 'Supernova', 'Black Hole',
            'Quantum Leap', 'Nexus Point', 'Zero Hour', 'Final Stand', 'Last Resort',
            'First Strike', 'Second Wind', 'Third Eye', 'Fourth Wall', 'Fifth Element',
            'Sixth Sense', 'Seven Sins', 'Eighth Wonder', 'Ninth Circle', 'Perfect Ten',
            'Alpha Squad', 'Beta Team', 'Gamma Force', 'Delta Wing', 'Echo Unit',
            'Foxtrot Five', 'Golf Company', 'Hotel Squad', 'India Force', 'Juliet Team',
            'Kilo Unit', 'Lima Squad', 'Mike Force', 'November Team', 'Oscar Unit',
            'Papa Squad', 'Quebec Force', 'Romeo Team', 'Sierra Unit', 'Tango Squad',
            'Uniform Team', 'Victor Force', 'Whiskey Unit', 'X-ray Squad', 'Yankee Team',
            'Zulu Force', 'Code Red', 'Blue Steel', 'Green Machine', 'Yellow Submarine',
            'Purple Rain', 'Orange Crush', 'Pink Panthers', 'Brown Bears', 'White Wolves',
            'Black Cats', 'Silver Bullets', 'Gold Rush', 'Diamond Dogs', 'Ruby Raiders',
            'Emerald Elite', 'Sapphire Strike', 'Platinum Plus', 'Titanium Tactics', 'Carbon Copy',
            'Neon Knights', 'Pixel Pirates', 'Byte Bandits', 'Code Crushers', 'Data Destroyers',
            'Hack Attack', 'Virus Hunters', 'Firewall Force', 'Encryption Elite', 'Debug Squad'
        ];

        $regions = ['NA', 'NA', 'NA', 'NA', 'CA']; // Mostly NA with some Canada
        $countries = ['United States', 'United States', 'United States', 'Canada', 'United States'];
        $countryCodes = ['US', 'US', 'US', 'CA', 'US'];

        for ($i = 0; $i < 91; $i++) {
            $regionIndex = $i % count($regions);
            $baseTeamName = $placeholderTeamNames[$i % count($placeholderTeamNames)];
            $teamName = $baseTeamName . ' ' . ($i + 1); // Add unique number to each team
            
            $team = Team::firstOrCreate(
                ['name' => $teamName],
                [
                    'short_name' => substr(strtoupper(str_replace(' ', '', $baseTeamName)), 0, 3) . ($i + 1),
                    'slug' => strtolower(str_replace(' ', '-', $teamName)),
                    'logo' => "/teams/placeholder-team-" . ($i + 1) . ".png",
                    'region' => $regions[$regionIndex],
                    'platform' => 'PC',
                    'game' => 'Marvel Rivals',
                    'country' => $countries[$regionIndex],
                    'country_code' => $countryCodes[$regionIndex],
                    'flag' => "/flags/{$countryCodes[$regionIndex]}.png",
                    'rating' => rand(1200, 1700),
                    'rank' => rand(50, 500),
                    'elo_rating' => rand(1200, 1700),
                    'peak_elo' => rand(1300, 1800),
                    'status' => 'active',
                    'founded' => '2024',
                    'player_count' => 6,
                    'achievements' => ['Marvel Rivals Open Qualifier Participant'],
                    'earnings' => rand(0, 5000),
                    'wins' => rand(5, 25),
                    'losses' => rand(5, 30),
                    'maps_won' => rand(15, 60),
                    'maps_lost' => rand(20, 70),
                    'win_rate' => round(rand(35, 70), 1),
                    'map_win_rate' => round(rand(30, 65), 1)
                ]
            );
            
            $this->openQualifierTeams[] = $team;
            
            if (($i + 1) % 10 == 0) {
                echo "  📝 Generated " . ($i + 1) . " open qualifier teams...\n";
            }
        }

        $this->allTeams = array_merge($this->qualifiedTeams, $this->openQualifierTeams);
        echo "✅ Total teams created: " . count($this->allTeams) . " (7 qualified + 91 open slots)\n";
        
        return $this->allTeams;
    }

    public function registerTeams()
    {
        echo "📝 Registering all teams for the tournament...\n";
        
        // Ensure organizer exists
        if (!$this->organizer) {
            throw new Exception("Tournament organizer not found. Cannot register teams without a valid organizer.");
        }
        
        DB::beginTransaction();
        
        try {
            foreach ($this->allTeams as $index => $team) {
                // Skip if team is already registered for this tournament
                $existingRegistration = $this->tournament->teams()->where('team_id', $team->id)->exists();
                if ($existingRegistration) {
                    echo "  ⏭️  Team {$team->name} already registered, skipping...\n";
                    continue;
                }
                
                // Determine seed based on team type
                $isQualified = in_array($team, $this->qualifiedTeams);
                $seed = $isQualified ? array_search($team, $this->qualifiedTeams) + 1 : count($this->qualifiedTeams) + array_search($team, $this->openQualifierTeams) + 1;
                
                // Register team
                $this->tournament->teams()->attach($team->id, [
                    'seed' => $seed,
                    'status' => 'active',
                    'registered_at' => now()->subDays(rand(1, 14)),
                    'swiss_wins' => 0,
                    'swiss_losses' => 0,
                    'swiss_score' => 0.0,
                    'swiss_buchholz' => 0.0
                ]);
                
                // Check if registration record already exists
                $existingTournamentRegistration = TournamentRegistration::where('tournament_id', $this->tournament->id)
                    ->where('team_id', $team->id)
                    ->exists();
                    
                if (!$existingTournamentRegistration) {
                    // Create registration record with proper user_id handling
                    $registrationUser = $this->getSystemUser();
                    TournamentRegistration::create([
                        'tournament_id' => $this->tournament->id,
                        'team_id' => $team->id,
                        'user_id' => $registrationUser->id, // Use system user or organizer as the registering user
                        'status' => 'approved',
                        'registered_at' => now()->subDays(rand(1, 14)),
                        'approved_at' => now()->subDays(rand(0, 10)),
                        'registration_data' => [
                            'qualified_from' => $isQualified ? 'split_1' : 'open_registration',
                            'contact_email' => strtolower($team->short_name) . '@team.gg',
                            'roster_submitted' => true,
                            'roster_locked' => false,
                            'substitutes' => rand(0, 2),
                            'captain_discord' => $team->short_name . 'Captain#' . rand(1000, 9999),
                            'special_notes' => $isQualified ? 'Qualified from Split 1' : 'Open Qualifier Registration',
                            'registered_by' => 'Tournament Organizer (System Registration)'
                        ]
                    ]);
                }
            }
            
            // Update tournament team count
            $this->tournament->update(['team_count' => count($this->allTeams)]);
            
            DB::commit();
            echo "✅ All " . count($this->allTeams) . " teams registered successfully\n";
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        return true;
    }

    public function createBracketStructure()
    {
        echo "🏗️ Creating tournament bracket structure...\n";
        
        DB::beginTransaction();
        
        try {
            // Check if bracket stages already exist
            $existingStages = BracketStage::where('tournament_id', $this->tournament->id)->count();
            if ($existingStages > 0) {
                echo "  ⏭️  Bracket stages already exist ({$existingStages} stages), using existing structure...\n";
                $stages = BracketStage::where('tournament_id', $this->tournament->id)->get();
                DB::commit();
                return [
                    'swiss' => $stages->where('type', 'swiss')->first(),
                    'bracket' => $stages->where('type', 'single_elimination')->first()
                ];
            }
            
            // Swiss Rounds Stage (98 teams -> 16 teams)
            $swissStage = BracketStage::create([
                'tournament_id' => $this->tournament->id,
                'name' => 'Swiss Rounds',
                'type' => 'swiss',
                'stage_order' => 1,
                'status' => 'pending',
                'max_teams' => 98,
                'total_rounds' => 5,
                'current_round' => 1,
                'settings' => [
                    'format' => 'bo1',
                    'rounds' => 5,
                    'advancement_wins' => 3,
                    'elimination_losses' => 3,
                    'pairing_system' => 'swiss',
                    'tiebreakers' => ['buchholz', 'head_to_head', 'seed']
                ]
            ]);

            // Single Elimination Bracket (16 teams -> 8 advance)
            $bracketStage = BracketStage::create([
                'tournament_id' => $this->tournament->id,
                'name' => 'Single Elimination Bracket',
                'type' => 'single_elimination',
                'stage_order' => 2,
                'status' => 'pending',
                'max_teams' => 16,
                'total_rounds' => 4,
                'current_round' => 1,
                'settings' => [
                    'format' => 'bo3',
                    'finals_format' => 'bo5',
                    'advancement_teams' => 8,
                    'elimination_type' => 'single',
                    'seeding_from' => 'swiss_standings'
                ]
            ]);

            echo "✅ Created bracket stages: Swiss Rounds & Single Elimination\n";
            
            DB::commit();
            return [
                'swiss' => $swissStage,
                'bracket' => $bracketStage
            ];
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createSwissMatches($bracketStages)
    {
        echo "⚔️ Creating Swiss Round matches (placeholder structure)...\n";
        
        $swissStage = $bracketStages['swiss'];
        $matches = [];
        $teamCount = 98;
        
        // Check if Swiss matches already exist
        $existingSwissMatches = BracketMatch::where('tournament_id', $this->tournament->id)
            ->where('round_name', 'LIKE', 'Swiss%')
            ->count();
        if ($existingSwissMatches > 0) {
            echo "  ⏭️  Swiss matches already exist ({$existingSwissMatches} matches), skipping creation...\n";
            return BracketMatch::where('tournament_id', $this->tournament->id)
                ->where('round_name', 'LIKE', 'Swiss%')
                ->get()
                ->toArray();
        }
        
        DB::beginTransaction();
        
        try {
            // Create Swiss Round matches (5 rounds, ~49 matches per round)
            for ($round = 1; $round <= 5; $round++) {
                echo "  📅 Creating Swiss Round {$round} matches...\n";
                
                $matchesInRound = $teamCount / 2; // 49 matches per round
                $scheduleTime = $this->tournament->start_date->copy()->addHours(($round - 1) * 4);
                
                for ($matchNum = 1; $matchNum <= $matchesInRound; $matchNum++) {
                    $match = BracketMatch::create([
                        'match_id' => "SW-R{$round}-M{$matchNum}",
                        'tournament_id' => $this->tournament->id,
                        'bracket_stage_id' => $swissStage->id,
                        'round_name' => "Swiss Round {$round}",
                        'round_number' => $round,
                        'match_number' => $matchNum,
                        'team1_source' => 'Swiss Pairing Algorithm',
                        'team2_source' => 'Swiss Pairing Algorithm',
                        'status' => 'pending', // Start all matches as pending
                        'best_of' => '1',
                        'scheduled_at' => $scheduleTime->copy()->addMinutes(($matchNum - 1) * 2),
                        'winner_advances_to' => $round < 5 ? "Swiss Round " . ($round + 1) : "Single Elimination Bracket",
                        'settings' => [
                            'map' => $this->tournament->map_pool[($matchNum - 1) % count($this->tournament->map_pool)],
                            'pairing_method' => 'swiss_system',
                            'round_type' => 'swiss'
                        ]
                    ]);
                    
                    $matches[] = $match;
                }
            }
            
            echo "✅ Created " . count($matches) . " Swiss Round matches\n";
            
            DB::commit();
            return $matches;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createBracketMatches($bracketStages)
    {
        echo "⚔️ Creating Single Elimination bracket matches...\n";
        
        $bracketStage = $bracketStages['bracket'];
        $matches = [];
        
        // Check if bracket matches already exist
        $existingBracketMatches = BracketMatch::where('tournament_id', $this->tournament->id)
            ->where('round_name', 'NOT LIKE', 'Swiss%')
            ->count();
        if ($existingBracketMatches > 0) {
            echo "  ⏭️  Bracket matches already exist ({$existingBracketMatches} matches), skipping creation...\n";
            return BracketMatch::where('tournament_id', $this->tournament->id)
                ->where('round_name', 'NOT LIKE', 'Swiss%')
                ->get()
                ->toArray();
        }
        
        DB::beginTransaction();
        
        try {
            $startTime = $this->tournament->start_date->copy()->addDays(2); // Day 3 of tournament
            
            // Round 1: 16 teams -> 8 teams (8 matches)
            echo "  🏆 Creating Round 1 matches (Round of 16)...\n";
            for ($i = 1; $i <= 8; $i++) {
                $match = BracketMatch::create([
                    'match_id' => "BR-R1-M{$i}",
                    'tournament_id' => $this->tournament->id,
                    'bracket_stage_id' => $bracketStage->id,
                    'round_name' => 'Round of 16',
                    'round_number' => 1,
                    'match_number' => $i,
                    'team1_source' => "Swiss Standing #{$i}",
                    'team2_source' => "Swiss Standing #" . (17 - $i),
                    'status' => 'pending',
                    'best_of' => '3',
                    'scheduled_at' => $startTime->copy()->addHours(($i - 1) * 2),
                    'winner_advances_to' => "BR-R2-M" . ceil($i / 2),
                    'settings' => [
                        'format' => 'bo3',
                        'map_selection' => 'pick_ban',
                        'advancement' => true
                    ]
                ]);
                
                $matches[] = $match;
            }

            // Round 2: 8 teams -> 4 teams (4 matches) - Quarterfinals
            echo "  🏆 Creating Quarterfinal matches...\n";
            for ($i = 1; $i <= 4; $i++) {
                $match = BracketMatch::create([
                    'match_id' => "BR-R2-M{$i}",
                    'tournament_id' => $this->tournament->id,
                    'bracket_stage_id' => $bracketStage->id,
                    'round_name' => 'Quarterfinals',
                    'round_number' => 2,
                    'match_number' => $i,
                    'team1_source' => "Winner of BR-R1-M" . (($i - 1) * 2 + 1),
                    'team2_source' => "Winner of BR-R1-M" . (($i - 1) * 2 + 2),
                    'status' => 'pending',
                    'best_of' => '3',
                    'scheduled_at' => $startTime->copy()->addDay()->addHours(($i - 1) * 2),
                    'winner_advances_to' => "BR-R3-M" . ceil($i / 2),
                    'settings' => [
                        'format' => 'bo3',
                        'advancement' => true
                    ]
                ]);
                
                $matches[] = $match;
            }

            // Round 3: 4 teams -> 2 teams (2 matches) - Semifinals
            echo "  🏆 Creating Semifinal matches...\n";
            for ($i = 1; $i <= 2; $i++) {
                $match = BracketMatch::create([
                    'match_id' => "BR-R3-M{$i}",
                    'tournament_id' => $this->tournament->id,
                    'bracket_stage_id' => $bracketStage->id,
                    'round_name' => 'Semifinals',
                    'round_number' => 3,
                    'match_number' => $i,
                    'team1_source' => "Winner of BR-R2-M" . (($i - 1) * 2 + 1),
                    'team2_source' => "Winner of BR-R2-M" . (($i - 1) * 2 + 2),
                    'status' => 'pending',
                    'best_of' => '3',
                    'scheduled_at' => $startTime->copy()->addDays(2)->addHours($i * 2),
                    'winner_advances_to' => $i == 1 ? "BR-R4-M1" : "BR-R4-M1",
                    'loser_advances_to' => $i == 1 ? "3rd_place" : "3rd_place",
                    'settings' => [
                        'format' => 'bo3',
                        'advancement' => $i <= 2 // Top 2 advance
                    ]
                ]);
                
                $matches[] = $match;
            }

            // Round 4: Finals (1 match)
            echo "  🏆 Creating Finals match...\n";
            $match = BracketMatch::create([
                'match_id' => "BR-R4-M1",
                'tournament_id' => $this->tournament->id,
                'bracket_stage_id' => $bracketStage->id,
                'round_name' => 'Finals',
                'round_number' => 4,
                'match_number' => 1,
                'team1_source' => "Winner of BR-R3-M1",
                'team2_source' => "Winner of BR-R3-M2",
                'status' => 'pending',
                'best_of' => '5',
                'scheduled_at' => $startTime->copy()->addDays(3),
                'settings' => [
                    'format' => 'bo5',
                    'championship_match' => true,
                    'advancement' => true
                ]
            ]);
            
            $matches[] = $match;

            echo "✅ Created " . count($matches) . " bracket matches\n";
            
            DB::commit();
            return $matches;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function generateReport()
    {
        echo "\n🎯 TOURNAMENT CREATION REPORT\n";
        echo "==========================================\n\n";
        
        echo "🏆 TOURNAMENT DETAILS:\n";
        echo "Name: {$this->tournament->name}\n";
        echo "Parent: {$this->parentTournament->name}\n";
        echo "Type: Open Qualifier (Split 2)\n";
        echo "Format: Swiss Rounds → Single Elimination\n"; 
        echo "Prize Pool: {$this->tournament->formatted_prize_pool}\n";
        echo "Teams: {$this->tournament->team_count}/{$this->tournament->max_teams}\n";
        echo "Status: {$this->tournament->status}\n";
        echo "Region: {$this->tournament->region}\n\n";

        echo "📅 SCHEDULE:\n";
        echo "Registration: {$this->tournament->registration_start->format('M j, Y H:i')} - {$this->tournament->registration_end->format('M j, Y H:i')} ET\n";
        echo "Tournament: {$this->tournament->start_date->format('M j, Y H:i')} - {$this->tournament->end_date->format('M j, Y H:i')} ET\n";
        echo "Check-in: {$this->tournament->check_in_start->format('M j, Y H:i')} - {$this->tournament->check_in_end->format('M j, Y H:i')} ET\n\n";
        
        echo "🎖️ QUALIFIED TEAMS FROM SPLIT 1:\n";
        foreach ($this->qualifiedTeams as $team) {
            $registration = $this->tournament->teams()->where('team_id', $team->id)->first();
            echo sprintf("  #%2d %-20s (%s) - Rating: %d - Split 1 Qualified\n", 
                $registration->pivot->seed,
                $team->name,
                $team->region,
                $team->rating
            );
        }
        echo "\n";
        
        echo "📊 TOURNAMENT STRUCTURE:\n";
        $swissMatches = BracketMatch::where('tournament_id', $this->tournament->id)
            ->where('round_name', 'LIKE', 'Swiss%')->count();
        $bracketMatches = BracketMatch::where('tournament_id', $this->tournament->id)
            ->where('round_name', 'NOT LIKE', 'Swiss%')->count();
        
        echo "Swiss Rounds: 5 rounds, ~49 matches per round\n";
        echo "Single Elimination: 15 matches (8+4+2+1)\n";
        echo "Total Matches: " . ($swissMatches + $bracketMatches) . "\n";
        echo "Advancement: Top 8 teams qualify for Closed Qualifier\n\n";

        echo "🎮 FORMAT DETAILS:\n";
        echo "Swiss Format: Best of 1, 5 rounds\n";
        echo "Swiss Advancement: 3+ wins to advance to bracket\n";
        echo "Bracket Format: Single Elimination\n";
        echo "Bracket Matches: Best of 3 (Finals: Best of 5)\n";
        echo "Final Spots: Top 8 advance to Closed Qualifier\n\n";

        echo "💰 PRIZE DISTRIBUTION:\n";
        foreach ($this->tournament->qualification_settings['prize_distribution'] as $place => $prize) {
            echo "  {$place}: \${$prize}\n";
        }
        echo "\n";

        echo "✅ TOURNAMENT CREATED SUCCESSFULLY!\n";
        echo "Tournament ID: {$this->tournament->id}\n";
        echo "Parent Tournament ID: {$this->parentTournament->id}\n";
        echo "Access via API: /api/tournaments/{$this->tournament->id}\n";
        echo "==========================================\n\n";
    }

    public function validateTournament()
    {
        echo "🔍 Validating tournament structure...\n";
        
        $errors = [];
        $warnings = [];
        
        // Check team count
        if ($this->tournament->team_count != 98) {
            $errors[] = "Expected 98 teams, found {$this->tournament->team_count}";
        }
        
        // Check qualified teams
        if (count($this->qualifiedTeams) != 7) {
            $errors[] = "Expected 7 qualified teams, found " . count($this->qualifiedTeams);
        }
        
        // Check bracket stages
        $stages = BracketStage::where('tournament_id', $this->tournament->id)->count();
        if ($stages != 2) {
            $errors[] = "Expected 2 bracket stages, found {$stages}";
        }
        
        // Check match count
        $totalMatches = BracketMatch::where('tournament_id', $this->tournament->id)->count();
        $expectedMatches = (5 * 49) + 15; // Swiss + Bracket matches
        if ($totalMatches != $expectedMatches) {
            $warnings[] = "Expected ~{$expectedMatches} matches, found {$totalMatches}";
        }
        
        // Display results
        if (empty($errors) && empty($warnings)) {
            echo "✅ Tournament validation passed!\n";
            return true;
        }
        
        if (!empty($errors)) {
            echo "❌ Tournament validation failed:\n";
            foreach ($errors as $error) {
                echo "  - {$error}\n";
            }
        }
        
        if (!empty($warnings)) {
            echo "⚠️  Tournament validation warnings:\n";
            foreach ($warnings as $warning) {
                echo "  - {$warning}\n";
            }
        }
        
        return empty($errors);
    }

    public function run()
    {
        try {
            echo "🚀 Starting Marvel Rivals Ignite Split 2 Tournament Creation...\n\n";
            
            $parentTournament = $this->createParentTournament();
            $tournament = $this->createSplit2Qualifier();
            $teams = $this->createOrFindTeams(); 
            $this->registerTeams();
            $bracketStages = $this->createBracketStructure();
            $swissMatches = $this->createSwissMatches($bracketStages);
            $bracketMatches = $this->createBracketMatches($bracketStages);
            
            $this->generateReport();
            $isValid = $this->validateTournament();
            
            if ($isValid) {
                echo "🎉 TOURNAMENT CREATION COMPLETED SUCCESSFULLY!\n";
                echo "🏆 Marvel Rivals Ignite 2025 Stage 2 Americas - Open Qualifier Split 2 is ready!\n";
            } else {
                echo "⚠️  Tournament created with validation issues - please review\n";
            }
            
            return [
                'parent_tournament' => $parentTournament,
                'tournament' => $tournament,
                'teams' => $teams,
                'swiss_matches' => $swissMatches,
                'bracket_matches' => $bracketMatches,
                'success' => true,
                'valid' => $isValid
            ];
            
        } catch (Exception $e) {
            echo "❌ Error creating tournament: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";
            
            // Rollback any partial changes
            DB::rollBack();
            
            return [
                'success' => false, 
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
}

// Execute the tournament creation
echo "================================================\n";
echo "🎮 MARVEL RIVALS IGNITE SPLIT 2 CREATOR v1.0\n"; 
echo "================================================\n\n";

$creator = new MarvelRivalsIgniteSplit2Creator();
$result = $creator->run();

if ($result['success']) {
    echo "\n✅ SUCCESS: Marvel Rivals Ignite Split 2 Tournament created successfully!\n";
    echo "🌟 Tournament Features:\n";
    echo "  • 98 teams total (7 qualified from Split 1 + 91 open slots)\n";  
    echo "  • Swiss Rounds format (5 rounds, Bo1 matches)\n";
    echo "  • Single Elimination bracket (Top 16 -> Top 8 advance)\n";
    echo "  • \$15,000 USD prize pool\n";
    echo "  • Top 8 teams advance to Closed Qualifier\n";
    echo "  • Qualified teams: Team Nemesis, DarkZero, FYR Strays, Busy At Work, Dreamland, Solaris, AILANIWIND\n";
    echo "  • Professional Marvel Rivals Ignite tournament rules\n";
    echo "  • Comprehensive bracket structure with proper progression\n\n";
    
    if (isset($result['valid']) && !$result['valid']) {
        echo "⚠️  Note: Tournament created but has validation warnings - review recommended\n";
    }
    
    echo "🚀 Ready for Marvel Rivals Ignite competition!\n";
} else {
    echo "\n❌ FAILURE: Tournament creation failed\n";
    echo "Error: {$result['error']}\n";
    if (isset($result['trace'])) {
        echo "\nStack trace:\n{$result['trace']}\n";
    }
}