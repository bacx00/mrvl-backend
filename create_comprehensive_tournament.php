<?php

/**
 * Comprehensive Tournament Creation Script
 * 
 * Creates a complete tournament system with all bracket formats:
 * - Single Elimination
 * - Double Elimination  
 * - Swiss System
 * - Round Robin
 * - Group Stage + Playoffs
 * - GSL Format
 * 
 * Following Liquipedia Marvel Rivals tournament structure and naming conventions
 */

require_once 'vendor/autoload.php';

use App\Services\ComprehensiveTournamentGenerator;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Log;

// Laravel bootstrap
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class TournamentCreationScript
{
    protected $generator;
    protected $teams;
    protected $organizer;
    
    public function __construct()
    {
        $this->generator = app(ComprehensiveTournamentGenerator::class);
        $this->loadTestData();
    }
    
    /**
     * Load test data for tournament creation
     */
    protected function loadTestData(): void
    {
        // Get or create organizer
        $this->organizer = User::firstOrCreate(
            ['email' => 'tournament.admin@marvel-rivals.com'],
            [
                'name' => 'Tournament Administrator',
                'role' => 'admin',
                'password' => bcrypt('tournament123')
            ]
        );
        
        // Get existing teams or create sample teams
        $this->teams = Team::take(32)->get();
        
        if ($this->teams->count() < 16) {
            echo "Not enough teams in database. Creating sample teams...\n";
            $this->createSampleTeams();
        }
    }
    
    /**
     * Create sample teams for testing
     */
    protected function createSampleTeams(): void
    {
        $sampleTeams = [
            ['name' => 'Team Dynasty', 'region' => 'na', 'rating' => 2500],
            ['name' => 'Phoenix Rising', 'region' => 'na', 'rating' => 2450],
            ['name' => 'Quantum Guardians', 'region' => 'na', 'rating' => 2400],
            ['name' => 'Crimson Legends', 'region' => 'na', 'rating' => 2350],
            ['name' => 'Azure Warriors', 'region' => 'eu', 'rating' => 2300],
            ['name' => 'Golden Phoenix', 'region' => 'eu', 'rating' => 2250],
            ['name' => 'Storm Breakers', 'region' => 'eu', 'rating' => 2200],
            ['name' => 'Thunder Hawks', 'region' => 'eu', 'rating' => 2150],
            ['name' => 'Dragon Force', 'region' => 'apac', 'rating' => 2100],
            ['name' => 'Neon Strike', 'region' => 'apac', 'rating' => 2050],
            ['name' => 'Mystic Wolves', 'region' => 'apac', 'rating' => 2000],
            ['name' => 'Shadow Legion', 'region' => 'apac', 'rating' => 1950],
            ['name' => 'Frost Giants', 'region' => 'sa', 'rating' => 1900],
            ['name' => 'Void Hunters', 'region' => 'sa', 'rating' => 1850],
            ['name' => 'Stellar Knights', 'region' => 'sa', 'rating' => 1800],
            ['name' => 'Cyber Phantoms', 'region' => 'sa', 'rating' => 1750],
            ['name' => 'Titan Forge', 'region' => 'na', 'rating' => 1700],
            ['name' => 'Echo Squadron', 'region' => 'eu', 'rating' => 1650],
            ['name' => 'Vortex Gaming', 'region' => 'apac', 'rating' => 1600],
            ['name' => 'Nexus Elite', 'region' => 'sa', 'rating' => 1550],
            ['name' => 'Apex Predators', 'region' => 'na', 'rating' => 1500],
            ['name' => 'Lightning Bolts', 'region' => 'eu', 'rating' => 1450],
            ['name' => 'Fire Storm', 'region' => 'apac', 'rating' => 1400],
            ['name' => 'Ice Breakers', 'region' => 'sa', 'rating' => 1350],
            ['name' => 'Wind Runners', 'region' => 'na', 'rating' => 1300],
            ['name' => 'Earth Shakers', 'region' => 'eu', 'rating' => 1250],
            ['name' => 'Wave Masters', 'region' => 'apac', 'rating' => 1200],
            ['name' => 'Sky Walkers', 'region' => 'sa', 'rating' => 1150],
            ['name' => 'Dream Team', 'region' => 'na', 'rating' => 1100],
            ['name' => 'Flash Force', 'region' => 'eu', 'rating' => 1050],
            ['name' => 'Omega Squad', 'region' => 'apac', 'rating' => 1000],
            ['name' => 'Alpha Strike', 'region' => 'global', 'rating' => 950]
        ];
        
        foreach ($sampleTeams as $teamData) {
            Team::create([
                'name' => $teamData['name'],
                'tag' => strtoupper(substr($teamData['name'], 0, 3)),
                'region' => $teamData['region'],
                'country' => 'US', // Default
                'logo' => 'default-team-logo.png',
                'rating' => $teamData['rating'],
                'wins' => rand(10, 50),
                'losses' => rand(5, 30),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        $this->teams = Team::all();
        echo "Created " . count($sampleTeams) . " sample teams.\n";
    }
    
    /**
     * Create all tournament formats for testing
     */
    public function createAllTournamentFormats(): void
    {
        echo "=== Marvel Rivals Tournament Creation Script ===\n\n";
        
        $formats = [
            'single_elimination' => 'Single Elimination Championship',
            'double_elimination' => 'Double Elimination Major',
            'swiss' => 'Swiss System Open',
            'round_robin' => 'Round Robin Invitational',
            'group_stage_playoffs' => 'Group Stage Championship',
            'gsl' => 'GSL Format Tournament'
        ];
        
        foreach ($formats as $format => $name) {
            echo "Creating {$name} ({$format})...\n";
            $tournament = $this->createTournament($format, $name);
            
            if ($tournament) {
                echo "✅ Tournament created successfully: {$tournament->name} (ID: {$tournament->id})\n";
                $this->displayTournamentInfo($tournament);
            } else {
                echo "❌ Failed to create tournament: {$name}\n";
            }
            echo "\n";
        }
    }
    
    /**
     * Create a single tournament with specific format
     */
    public function createTournament(string $format, string $name): ?Tournament
    {
        try {
            $teams = $this->getTeamsForFormat($format);
            
            $config = [
                'name' => $name,
                'slug' => \Str::slug($name),
                'format' => $format,
                'type' => 'tournament',
                'description' => "Official Marvel Rivals {$name} featuring top competitive teams",
                'region' => 'global',
                'prize_pool' => $this->getPrizePoolForFormat($format),
                'currency' => 'USD',
                'max_teams' => count($teams),
                'min_teams' => $this->getMinTeamsForFormat($format),
                'start_date' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'end_date' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'registration_start' => now()->format('Y-m-d H:i:s'),
                'registration_end' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'timezone' => 'UTC',
                'organizer_id' => $this->organizer->id,
                'teams' => $teams,
                'settings' => $this->getFormatSettings($format),
                'match_formats' => $this->getMatchFormats($format),
                'map_pool' => $this->getMapPool(),
                'rules' => $this->getTournamentRules($format),
                'featured' => true,
                'public' => true
            ];
            
            // Add format-specific settings
            if ($format === 'group_stage_playoffs') {
                $config['group_size'] = 4;
            } elseif ($format === 'gsl') {
                $config['gsl_group_size'] = 4;
            }
            
            return $this->generator->createCompleteTournament($config);
            
        } catch (\Exception $e) {
            echo "Error creating tournament: " . $e->getMessage() . "\n";
            Log::error('Tournament creation failed', [
                'format' => $format,
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get teams for specific format
     */
    protected function getTeamsForFormat(string $format): array
    {
        $teamCounts = [
            'single_elimination' => 16,
            'double_elimination' => 16,
            'swiss' => 24,
            'round_robin' => 8,
            'group_stage_playoffs' => 16,
            'gsl' => 16
        ];
        
        $count = $teamCounts[$format] ?? 16;
        $teams = $this->teams->take($count);
        
        return $teams->map(function ($team, $index) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'rating' => $team->rating ?? 1000,
                'seed' => $index + 1
            ];
        })->toArray();
    }
    
    /**
     * Get prize pool based on format
     */
    protected function getPrizePoolForFormat(string $format): int
    {
        $prizePools = [
            'single_elimination' => 50000,
            'double_elimination' => 100000,
            'swiss' => 75000,
            'round_robin' => 25000,
            'group_stage_playoffs' => 80000,
            'gsl' => 60000
        ];
        
        return $prizePools[$format] ?? 50000;
    }
    
    /**
     * Get minimum teams for format
     */
    protected function getMinTeamsForFormat(string $format): int
    {
        $minTeams = [
            'single_elimination' => 4,
            'double_elimination' => 4,
            'swiss' => 8,
            'round_robin' => 4,
            'group_stage_playoffs' => 8,
            'gsl' => 8
        ];
        
        return $minTeams[$format] ?? 4;
    }
    
    /**
     * Get format-specific settings
     */
    protected function getFormatSettings(string $format): array
    {
        $settings = [
            'single_elimination' => [
                'bracket_size' => 16,
                'seeding_method' => 'rating_based'
            ],
            'double_elimination' => [
                'bracket_size' => 16,
                'bracket_reset' => true,
                'seeding_method' => 'rating_based'
            ],
            'swiss' => [
                'rounds' => 5,
                'wins_to_qualify' => 3,
                'losses_to_eliminate' => 3,
                'qualified_count' => 8,
                'buchholz_tiebreaker' => true
            ],
            'round_robin' => [
                'points_for_win' => 3,
                'points_for_tie' => 1,
                'points_for_loss' => 0,
                'tiebreaker_rules' => ['head_to_head', 'map_differential']
            ],
            'group_stage_playoffs' => [
                'group_size' => 4,
                'teams_advance_per_group' => 2,
                'tiebreaker_rules' => ['head_to_head', 'map_differential', 'round_differential']
            ],
            'gsl' => [
                'group_size' => 4,
                'winners_bracket_format' => 'single_elimination',
                'losers_bracket_format' => 'single_elimination',
                'advancement_per_group' => 2
            ]
        ];
        
        return $settings[$format] ?? [];
    }
    
    /**
     * Get match formats for tournament type
     */
    protected function getMatchFormats(string $format): array
    {
        return [
            'group_stage' => 'bo3',
            'swiss' => 'bo1',
            'playoffs' => 'bo3',
            'semifinals' => 'bo5',
            'finals' => 'bo5',
            'grand_final' => 'bo5',
            'default' => 'bo3'
        ];
    }
    
    /**
     * Get Marvel Rivals map pool
     */
    protected function getMapPool(): array
    {
        return [
            'Klyntar',
            'Birnin T\'Challa',
            'Sanctum Sanctorum',
            'Stark Tower',
            'Midtown',
            'Asgard',
            'Tokyo 2099',
            'Intergalactic Empire of Wakanda',
            'Yggsgard: Seed of Memory',
            'Yggsgard: Path of Exile'
        ];
    }
    
    /**
     * Get tournament rules
     */
    protected function getTournamentRules(string $format): array
    {
        return [
            'general' => [
                'All matches must be played on official Marvel Rivals servers',
                'Teams must have 6 registered players (5 main + 1 substitute)',
                'No external coaching during matches',
                'Hero duplicates are not allowed within the same team'
            ],
            'format_specific' => $this->getFormatSpecificRules($format),
            'penalties' => [
                'Late arrival: 10 minute grace period, then forfeit',
                'Unsportsmanlike conduct: Warning, then disqualification',
                'Cheating: Immediate disqualification and ban'
            ]
        ];
    }
    
    /**
     * Get format-specific rules
     */
    protected function getFormatSpecificRules(string $format): array
    {
        $rules = [
            'single_elimination' => [
                'Single elimination - one loss eliminates the team',
                'Higher seed chooses map pick order'
            ],
            'double_elimination' => [
                'Teams have two lives - elimination after two losses',
                'Grand Final bracket reset if lower bracket team wins first match',
                'Upper bracket team starts grand final with 1 map advantage (if applicable)'
            ],
            'swiss' => [
                'Teams paired based on current record',
                'No team plays the same opponent twice',
                'Qualifying based on win count and tiebreakers'
            ],
            'round_robin' => [
                'Every team plays every other team once',
                'Points awarded: 3 for win, 1 for tie, 0 for loss',
                'Tiebreakers: head-to-head, then map differential'
            ],
            'group_stage_playoffs' => [
                'Round robin within groups',
                'Top 2 from each group advance to playoffs',
                'Group seeding affects playoff bracket position'
            ],
            'gsl' => [
                'Dual elimination within groups',
                'Winners and losers brackets in each group',
                'Top 2 from each group advance to final bracket'
            ]
        ];
        
        return $rules[$format] ?? [];
    }
    
    /**
     * Display tournament information
     */
    protected function displayTournamentInfo(Tournament $tournament): void
    {
        echo "   Format: {$tournament->format}\n";
        echo "   Teams: {$tournament->team_count}/{$tournament->max_teams}\n";
        echo "   Prize Pool: $" . number_format($tournament->prize_pool) . " {$tournament->currency}\n";
        echo "   Status: {$tournament->status}\n";
        echo "   Stages: " . $tournament->bracketStages()->count() . "\n";
        echo "   Matches: " . $tournament->matches()->count() . "\n";
        
        if ($tournament->bracket_data) {
            $bracketData = $tournament->bracket_data;
            if (isset($bracketData['type'])) {
                echo "   Bracket Type: {$bracketData['type']}\n";
            }
            if (isset($bracketData['total_rounds'])) {
                echo "   Total Rounds: {$bracketData['total_rounds']}\n";
            }
        }
    }
    
    /**
     * Create a specific tournament format for testing
     */
    public function createSpecificTournament(string $format): ?Tournament
    {
        $names = [
            'single_elimination' => 'Marvel Rivals Spring Championship',
            'double_elimination' => 'Marvel Rivals Major Tournament',
            'swiss' => 'Marvel Rivals Open Series',
            'round_robin' => 'Marvel Rivals Round Robin Cup',
            'group_stage_playoffs' => 'Marvel Rivals World Championship',
            'gsl' => 'Marvel Rivals GSL Season 1'
        ];
        
        $name = $names[$format] ?? 'Marvel Rivals Tournament';
        
        return $this->createTournament($format, $name);
    }
    
    /**
     * List all tournaments
     */
    public function listTournaments(): void
    {
        $tournaments = Tournament::with(['bracketStages', 'teams'])->latest()->take(20)->get();
        
        echo "=== Recent Tournaments ===\n\n";
        
        foreach ($tournaments as $tournament) {
            echo "ID: {$tournament->id} | {$tournament->name}\n";
            echo "   Format: {$tournament->format} | Status: {$tournament->status}\n";
            echo "   Teams: {$tournament->team_count} | Prize: $" . number_format($tournament->prize_pool) . "\n";
            echo "   Created: {$tournament->created_at->format('Y-m-d H:i:s')}\n\n";
        }
    }
}

// Main execution
try {
    $script = new TournamentCreationScript();
    
    // Check command line arguments
    $arguments = $argv ?? [];
    
    if (isset($arguments[1])) {
        switch ($arguments[1]) {
            case 'all':
                $script->createAllTournamentFormats();
                break;
            case 'list':
                $script->listTournaments();
                break;
            case 'single':
                $tournament = $script->createSpecificTournament('single_elimination');
                echo $tournament ? "Single elimination tournament created!\n" : "Failed to create tournament\n";
                break;
            case 'double':
                $tournament = $script->createSpecificTournament('double_elimination');
                echo $tournament ? "Double elimination tournament created!\n" : "Failed to create tournament\n";
                break;
            case 'swiss':
                $tournament = $script->createSpecificTournament('swiss');
                echo $tournament ? "Swiss system tournament created!\n" : "Failed to create tournament\n";
                break;
            case 'robin':
                $tournament = $script->createSpecificTournament('round_robin');
                echo $tournament ? "Round robin tournament created!\n" : "Failed to create tournament\n";
                break;
            case 'groups':
                $tournament = $script->createSpecificTournament('group_stage_playoffs');
                echo $tournament ? "Group stage + playoffs tournament created!\n" : "Failed to create tournament\n";
                break;
            case 'gsl':
                $tournament = $script->createSpecificTournament('gsl');
                echo $tournament ? "GSL format tournament created!\n" : "Failed to create tournament\n";
                break;
            default:
                echo "Usage: php create_comprehensive_tournament.php [all|list|single|double|swiss|robin|groups|gsl]\n";
                break;
        }
    } else {
        // Default behavior - create all formats
        $script->createAllTournamentFormats();
    }
    
    echo "\n=== Tournament Creation Complete ===\n";
    
} catch (\Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}