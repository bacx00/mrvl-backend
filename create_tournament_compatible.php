<?php

/**
 * Compatible Tournament Creation Script
 * Works with existing database schema using 'type' instead of 'format'
 */

require_once 'vendor/autoload.php';

// Laravel bootstrap
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tournament;
use App\Models\Team;
use App\Models\User;
use App\Models\BracketStage;
use App\Models\BracketMatch;

class CompatibleTournamentCreator
{
    protected $organizer;
    protected $teams;
    
    public function __construct()
    {
        $this->loadTestData();
    }
    
    protected function loadTestData()
    {
        // Get or create organizer
        $this->organizer = User::firstOrCreate(
            ['email' => 'tournament.organizer@marvel-rivals.com'],
            [
                'name' => 'Tournament Organizer',
                'password' => bcrypt('tournament123'),
                'role' => 'admin'
            ]
        );
        
        // Get existing teams
        $this->teams = Team::take(16)->get();
        
        if ($this->teams->count() < 8) {
            $this->createSampleTeams();
        }
    }
    
    protected function createSampleTeams()
    {
        echo "Creating sample teams...\n";
        
        $sampleTeams = [
            ['name' => 'Phoenix Squadron', 'rating' => 2100],
            ['name' => 'Quantum Force', 'rating' => 2050],
            ['name' => 'Crimson Tide', 'rating' => 2000],
            ['name' => 'Azure Storm', 'rating' => 1950],
            ['name' => 'Golden Eagles', 'rating' => 1900],
            ['name' => 'Thunder Wolves', 'rating' => 1850],
            ['name' => 'Lightning Strike', 'rating' => 1800],
            ['name' => 'Shadow Legion', 'rating' => 1750],
            ['name' => 'Frost Giants', 'rating' => 1700],
            ['name' => 'Fire Dragons', 'rating' => 1650],
            ['name' => 'Wind Runners', 'rating' => 1600],
            ['name' => 'Earth Shakers', 'rating' => 1550],
            ['name' => 'Void Hunters', 'rating' => 1500],
            ['name' => 'Star Guardians', 'rating' => 1450],
            ['name' => 'Neon Knights', 'rating' => 1400],
            ['name' => 'Cyber Warriors', 'rating' => 1350]
        ];
        
        foreach ($sampleTeams as $index => $teamData) {
            Team::create([
                'name' => $teamData['name'],
                'tag' => strtoupper(substr(str_replace(' ', '', $teamData['name']), 0, 4)),
                'region' => ['na', 'eu', 'apac', 'sa'][rand(0, 3)],
                'country' => 'US',
                'logo' => 'default-team-logo.png',
                'rating' => $teamData['rating'],
                'wins' => rand(15, 45),
                'losses' => rand(5, 25),
                'status' => 'active'
            ]);
        }
        
        $this->teams = Team::latest()->take(16)->get();
        echo "Created " . count($sampleTeams) . " teams.\n";
    }
    
    public function createTournament($type, $teamCount = 8)
    {
        echo "Creating {$type} tournament with {$teamCount} teams...\n";
        
        $teams = $this->teams->take($teamCount);
        
        // Map our comprehensive types to database enum values
        $dbType = $this->mapToDbType($type);
        
        try {
            $tournament = Tournament::create([
                'name' => $this->getTournamentName($type),
                'slug' => 'tournament-' . $type . '-' . time(),
                'type' => $dbType,
                'status' => 'upcoming',
                'description' => "Marvel Rivals {$type} tournament featuring competitive teams",
                'region' => 'global',
                'prize_pool' => $this->getPrizePool($type),
                'team_count' => $teamCount,
                'start_date' => now()->addDays(2),
                'end_date' => now()->addDays(5),
                'settings' => $this->getSettings($type, $teamCount)
            ]);
            
            echo "✅ Tournament created: {$tournament->name} (ID: {$tournament->id})\n";
            
            // Register teams
            foreach ($teams as $index => $team) {
                $tournament->teams()->attach($team->id, [
                    'seed' => $index + 1,
                    'status' => 'registered',
                    'registered_at' => now()
                ]);
            }
            
            echo "✅ Registered {$teams->count()} teams\n";
            
            // Create bracket structure
            $this->createBracketStructure($tournament, $type, $teams);
            
            // Create initial matches
            $this->createInitialMatches($tournament, $type, $teams);
            
            return $tournament;
            
        } catch (\Exception $e) {
            echo "❌ Error creating tournament: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    protected function mapToDbType($type)
    {
        $mapping = [
            'single_elimination' => 'single_elimination',
            'double_elimination' => 'double_elimination',
            'swiss' => 'swiss',
            'round_robin' => 'round_robin',
            'swiss_double' => 'swiss_double_elim'
        ];
        
        return $mapping[$type] ?? 'single_elimination';
    }
    
    protected function getTournamentName($type)
    {
        $names = [
            'single_elimination' => 'Marvel Rivals Spring Championship',
            'double_elimination' => 'Marvel Rivals Major Tournament',
            'swiss' => 'Marvel Rivals Swiss Open',
            'round_robin' => 'Marvel Rivals Round Robin Cup',
            'swiss_double' => 'Marvel Rivals Swiss-Double Elimination'
        ];
        
        return $names[$type] ?? 'Marvel Rivals Tournament';
    }
    
    protected function getPrizePool($type)
    {
        $pools = [
            'single_elimination' => 25000,
            'double_elimination' => 50000,
            'swiss' => 35000,
            'round_robin' => 15000,
            'swiss_double' => 40000
        ];
        
        return $pools[$type] ?? 25000;
    }
    
    protected function getSettings($type, $teamCount)
    {
        return [
            'tournament_format' => $type,
            'team_count' => $teamCount,
            'bracket_size' => $this->getNextPowerOfTwo($teamCount),
            'seeding_method' => 'rating_based',
            'match_format' => [
                'group_stage' => 'bo3',
                'playoffs' => 'bo3',
                'finals' => 'bo5'
            ],
            'maps' => [
                'Klyntar', 'Birnin T\'Challa', 'Sanctum Sanctorum',
                'Stark Tower', 'Midtown', 'Asgard', 'Tokyo 2099'
            ]
        ];
    }
    
    protected function createBracketStructure($tournament, $type, $teams)
    {
        $teamCount = $teams->count();
        
        switch ($type) {
            case 'single_elimination':
                $this->createSingleEliminationStages($tournament, $teamCount);
                break;
                
            case 'double_elimination':
                $this->createDoubleEliminationStages($tournament, $teamCount);
                break;
                
            case 'swiss':
                $this->createSwissStages($tournament, $teamCount);
                break;
                
            case 'round_robin':
                $this->createRoundRobinStages($tournament, $teamCount);
                break;
                
            case 'swiss_double':
                $this->createSwissDoubleStages($tournament, $teamCount);
                break;
        }
    }
    
    protected function createSingleEliminationStages($tournament, $teamCount)
    {
        $rounds = ceil(log($teamCount, 2));
        
        $stage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Main Bracket',
            'type' => 'single_elimination',
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $rounds,
            'settings' => ['bracket_size' => $this->getNextPowerOfTwo($teamCount)]
        ]);
        
        echo "✅ Created single elimination stage with {$rounds} rounds\n";
        return $stage;
    }
    
    protected function createDoubleEliminationStages($tournament, $teamCount)
    {
        $upperRounds = ceil(log($teamCount, 2));
        $lowerRounds = ($upperRounds * 2) - 1;
        
        // Upper Bracket
        $upperStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Upper Bracket',
            'type' => 'upper_bracket',
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $upperRounds,
            'settings' => ['bracket_type' => 'upper']
        ]);
        
        // Lower Bracket
        $lowerStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Lower Bracket',
            'type' => 'lower_bracket',
            'stage_order' => 2,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $lowerRounds,
            'settings' => ['bracket_type' => 'lower', 'feeds_from' => $upperStage->id]
        ]);
        
        // Grand Final
        $finalStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Grand Final',
            'type' => 'grand_final',
            'stage_order' => 3,
            'status' => 'pending',
            'max_teams' => 2,
            'current_round' => 1,
            'total_rounds' => 1,
            'settings' => ['bracket_reset' => true]
        ]);
        
        echo "✅ Created double elimination stages (Upper: {$upperRounds}, Lower: {$lowerRounds} rounds)\n";
        return [$upperStage, $lowerStage, $finalStage];
    }
    
    protected function createSwissStages($tournament, $teamCount)
    {
        $rounds = max(4, ceil(log($teamCount, 2)));
        
        $stage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Swiss Rounds',
            'type' => 'swiss',
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $rounds,
            'settings' => [
                'swiss_rounds' => $rounds,
                'wins_to_qualify' => ceil($rounds * 0.6),
                'losses_to_eliminate' => floor($rounds * 0.6)
            ]
        ]);
        
        echo "✅ Created Swiss stage with {$rounds} rounds\n";
        return $stage;
    }
    
    protected function createRoundRobinStages($tournament, $teamCount)
    {
        $rounds = $teamCount - 1;
        
        $stage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Round Robin',
            'type' => 'round_robin',
            'stage_order' => 1,
            'status' => 'pending',
            'max_teams' => $teamCount,
            'current_round' => 1,
            'total_rounds' => $rounds,
            'settings' => [
                'total_matches' => ($teamCount * ($teamCount - 1)) / 2,
                'points_system' => '3-1-0'
            ]
        ]);
        
        echo "✅ Created Round Robin stage with {$rounds} rounds\n";
        return $stage;
    }
    
    protected function createSwissDoubleStages($tournament, $teamCount)
    {
        // Swiss stage first
        $swissStage = $this->createSwissStages($tournament, $teamCount);
        $swissStage->update(['name' => 'Swiss Stage']);
        
        // Then elimination stage for top qualifiers
        $playoffStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Playoff Stage',
            'type' => 'single_elimination',
            'stage_order' => 2,
            'status' => 'pending',
            'max_teams' => 8, // Top 8 from Swiss
            'current_round' => 1,
            'total_rounds' => 3,
            'settings' => ['qualified_from_swiss' => true]
        ]);
        
        echo "✅ Created Swiss-Double elimination format\n";
        return [$swissStage, $playoffStage];
    }
    
    protected function createInitialMatches($tournament, $type, $teams)
    {
        $stage = $tournament->bracketStages()->first();
        
        if (!$stage) {
            echo "❌ No bracket stage found for match creation\n";
            return;
        }
        
        $teamsList = $teams->toArray();
        $matchNumber = 1;
        
        // Create first round matches based on type
        switch ($type) {
            case 'single_elimination':
            case 'double_elimination':
                $this->createEliminationMatches($stage, $teamsList, $matchNumber);
                break;
                
            case 'swiss':
                $this->createSwissMatches($stage, $teamsList, $matchNumber);
                break;
                
            case 'round_robin':
                $this->createRoundRobinMatches($stage, $teamsList);
                break;
                
            case 'swiss_double':
                $this->createSwissMatches($stage, $teamsList, $matchNumber);
                break;
        }
    }
    
    protected function createEliminationMatches($stage, $teams, $matchNumber)
    {
        // Pair teams for first round (1v8, 2v7, 3v6, 4v5 for 8 teams)
        $seededTeams = $this->seedTeams($teams);
        
        for ($i = 0; $i < count($seededTeams); $i += 2) {
            if (isset($seededTeams[$i + 1])) {
                BracketMatch::create([
                    'tournament_id' => $stage->tournament_id,
                    'bracket_stage_id' => $stage->id,
                    'round_number' => 1,
                    'match_number' => $matchNumber,
                    'team1_id' => $seededTeams[$i]['id'],
                    'team2_id' => $seededTeams[$i + 1]['id'],
                    'status' => 'pending',
                    'match_format' => 'bo3',
                    'scheduled_at' => now()->addDays(1)->addHours($matchNumber * 2)
                ]);
                
                echo "  Match {$matchNumber}: {$seededTeams[$i]['name']} vs {$seededTeams[$i + 1]['name']}\n";
                $matchNumber++;
            }
        }
    }
    
    protected function createSwissMatches($stage, $teams, $matchNumber)
    {
        // Random pairing for first Swiss round
        shuffle($teams);
        
        for ($i = 0; $i < count($teams); $i += 2) {
            if (isset($teams[$i + 1])) {
                BracketMatch::create([
                    'tournament_id' => $stage->tournament_id,
                    'bracket_stage_id' => $stage->id,
                    'round_number' => 1,
                    'match_number' => $matchNumber,
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$i + 1]['id'],
                    'status' => 'pending',
                    'match_format' => 'bo1',
                    'scheduled_at' => now()->addDays(1)->addHours($matchNumber)
                ]);
                
                echo "  Swiss R1 M{$matchNumber}: {$teams[$i]['name']} vs {$teams[$i + 1]['name']}\n";
                $matchNumber++;
            }
        }
    }
    
    protected function createRoundRobinMatches($stage, $teams)
    {
        $matchNumber = 1;
        
        // Create all possible pairings
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $round = $this->calculateRoundRobinRound($i, $j, count($teams));
                
                BracketMatch::create([
                    'tournament_id' => $stage->tournament_id,
                    'bracket_stage_id' => $stage->id,
                    'round_number' => $round,
                    'match_number' => $matchNumber,
                    'team1_id' => $teams[$i]['id'],
                    'team2_id' => $teams[$j]['id'],
                    'status' => 'pending',
                    'match_format' => 'bo3',
                    'scheduled_at' => now()->addDays(1)->addHours($matchNumber * 1.5)
                ]);
                
                echo "  RR M{$matchNumber}: {$teams[$i]['name']} vs {$teams[$j]['name']}\n";
                $matchNumber++;
            }
        }
    }
    
    protected function seedTeams($teams)
    {
        // Sort by rating (highest first)
        usort($teams, function($a, $b) {
            return ($b['rating'] ?? 1000) <=> ($a['rating'] ?? 1000);
        });
        
        return $teams;
    }
    
    protected function getNextPowerOfTwo($n)
    {
        return pow(2, ceil(log($n, 2)));
    }
    
    protected function calculateRoundRobinRound($i, $j, $teamCount)
    {
        return 1 + (($i + $j) % ($teamCount - 1));
    }
    
    public function displayTournamentInfo($tournament)
    {
        echo "\n=== Tournament Summary ===\n";
        echo "Name: {$tournament->name}\n";
        echo "Type: {$tournament->type}\n";
        echo "Teams: {$tournament->team_count}\n";
        echo "Prize Pool: $" . number_format($tournament->prize_pool) . "\n";
        echo "Status: {$tournament->status}\n";
        echo "Stages: " . $tournament->bracketStages()->count() . "\n";
        echo "Matches: " . $tournament->matches()->count() . "\n";
        echo "Start Date: {$tournament->start_date->format('Y-m-d H:i:s')}\n";
        echo "=========================\n\n";
    }
    
    public function createAllFormats()
    {
        echo "=== Creating All Tournament Formats ===\n\n";
        
        $formats = [
            'single_elimination' => 8,
            'double_elimination' => 8,
            'swiss' => 12,
            'round_robin' => 6,
            'swiss_double' => 16
        ];
        
        foreach ($formats as $format => $teamCount) {
            $tournament = $this->createTournament($format, $teamCount);
            
            if ($tournament) {
                $this->displayTournamentInfo($tournament);
            } else {
                echo "Failed to create {$format} tournament\n\n";
            }
        }
    }
}

// Execute the script
try {
    $creator = new CompatibleTournamentCreator();
    
    $action = $argv[1] ?? 'all';
    
    switch ($action) {
        case 'all':
            $creator->createAllFormats();
            break;
            
        case 'single':
            $tournament = $creator->createTournament('single_elimination', 8);
            if ($tournament) $creator->displayTournamentInfo($tournament);
            break;
            
        case 'double':
            $tournament = $creator->createTournament('double_elimination', 8);
            if ($tournament) $creator->displayTournamentInfo($tournament);
            break;
            
        case 'swiss':
            $tournament = $creator->createTournament('swiss', 12);
            if ($tournament) $creator->displayTournamentInfo($tournament);
            break;
            
        case 'robin':
            $tournament = $creator->createTournament('round_robin', 6);
            if ($tournament) $creator->displayTournamentInfo($tournament);
            break;
            
        case 'hybrid':
            $tournament = $creator->createTournament('swiss_double', 16);
            if ($tournament) $creator->displayTournamentInfo($tournament);
            break;
            
        default:
            echo "Usage: php create_tournament_compatible.php [all|single|double|swiss|robin|hybrid]\n";
            break;
    }
    
    echo "Tournament creation process completed!\n";
    
} catch (\Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}