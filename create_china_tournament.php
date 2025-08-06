<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// China region teams for the tournament
$chinaTeams = [
    // Group A
    ['name' => 'Nova Esports', 'short_name' => 'Nova', 'region' => 'CN', 'rating' => 2150],
    ['name' => 'OUG Esports', 'short_name' => 'OUG', 'region' => 'CN', 'rating' => 2100],
    ['name' => 'EHOME', 'short_name' => 'EHOME', 'region' => 'CN', 'rating' => 2080],
    ['name' => 'FL Esports', 'short_name' => 'FL', 'region' => 'CN', 'rating' => 2050],
    ['name' => 'LGD Gaming', 'short_name' => 'LGD', 'region' => 'CN', 'rating' => 2030],
    ['name' => 'Rare Atom', 'short_name' => 'RA', 'region' => 'CN', 'rating' => 2000],
    
    // Group B  
    ['name' => 'FunPlus Phoenix', 'short_name' => 'FPX', 'region' => 'CN', 'rating' => 2120],
    ['name' => 'Wolves Esports', 'short_name' => 'WLV', 'region' => 'CN', 'rating' => 2090],
    ['name' => 'JD Gaming', 'short_name' => 'JDG', 'region' => 'CN', 'rating' => 2070],
    ['name' => 'Bilibili Gaming', 'short_name' => 'BLG', 'region' => 'CN', 'rating' => 2040],
    ['name' => 'OMG', 'short_name' => 'OMG', 'region' => 'CN', 'rating' => 2020],
    ['name' => 'Thunder Talk Gaming', 'short_name' => 'TTG', 'region' => 'CN', 'rating' => 1990],
];

DB::beginTransaction();

try {
    echo "Creating China Tournament Replica...\n";
    
    // Get or create teams
    $teamIds = [];
    foreach ($chinaTeams as $teamData) {
        // Try to find team by name or short_name
        $team = Team::where('name', $teamData['name'])
                    ->orWhere('short_name', $teamData['short_name'])
                    ->first();
                    
        if (!$team) {
            // Create new team with unique short_name
            $shortName = $teamData['short_name'];
            $counter = 1;
            while (Team::where('short_name', $shortName)->exists()) {
                $shortName = $teamData['short_name'] . $counter;
                $counter++;
            }
            
            $team = Team::create([
                'name' => $teamData['name'],
                'short_name' => $shortName,
                'region' => $teamData['region'],
                'rating' => $teamData['rating'],
                'platform' => 'PC',
                'country' => 'CN',
                'flag' => '🇨🇳',
                'game' => 'Marvel Rivals'
            ]);
        } else {
            // Update existing team's rating and region
            $team->update([
                'region' => $teamData['region'],
                'rating' => $teamData['rating'],
                'country' => 'CN',
                'flag' => '🇨🇳'
            ]);
        }
        
        $teamIds[] = $team->id;
        echo "Team ready: {$team->name} (ID: {$team->id})\n";
    }
    
    // Create the tournament event
    $organizer = User::where('role', 'admin')->first() ?? User::first();
    
    $event = Event::create([
        'name' => 'Marvel Rivals Ignite 2025 - Stage 1 China',
        'slug' => 'marvel-rivals-ignite-2025-stage-1-china-' . time(),
        'description' => 'Marvel Rivals Ignite 2025 Stage 1 China Regional Championship. 12 teams compete in 2 groups of 6 (round robin) with top 8 advancing to double elimination playoffs.',
        'game' => 'Marvel Rivals',
        'organizer_id' => $organizer->id,
        'type' => 'championship',
        'format' => 'group_stage', // Will use group_stage for main format
        'tier' => 'S',
        'region' => 'CN',
        'game_mode' => 'Competitive',
        'mode' => 'Online',
        'prize_pool' => 100000,
        'currency' => 'USD',
        'start_date' => '2025-08-10',
        'end_date' => '2025-08-17',
        'status' => 'upcoming',
        'venue' => 'Shanghai Esports Arena',
        'country' => 'China',
        'city' => 'Shanghai',
        'stream_url' => 'https://www.bilibili.com/marvel-rivals-ignite',
        'registration_open' => false,
        'team_size' => 6,
        'max_teams' => 12,
        'current_teams' => 12,
        'match_format' => 'bo3', // Default, will escalate to bo5 and bo7
        'has_bracket' => true,
        'current_round' => 1,
        'total_rounds' => 7, // 5 group rounds + 2-3 playoff rounds
        'rules' => json_encode([
            'stage1' => [
                'format' => 'round_robin',
                'groups' => 2,
                'teams_per_group' => 6,
                'matches' => 'bo3',
                'advancement' => 'top_4_each_group'
            ],
            'stage2' => [
                'format' => 'double_elimination',
                'teams' => 8,
                'upper_bracket' => 'bo3',
                'lower_bracket' => 'bo3',
                'upper_finals' => 'bo5',
                'lower_finals' => 'bo5',
                'grand_finals' => 'bo7'
            ]
        ])
    ]);
    
    echo "Tournament created: {$event->name} (ID: {$event->id})\n";
    
    // Register teams to the event
    foreach ($teamIds as $teamId) {
        DB::table('event_teams')->insert([
            'event_id' => $event->id,
            'team_id' => $teamId,
            'registered_at' => now(),
            'seed' => null, // Will be determined by rating
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    echo "All 12 teams registered to tournament\n";
    
    // Create Stage 1: Group Stage Structure
    echo "\nSetting up Stage 1: Group Stage (Round Robin)...\n";
    
    // Group A teams (top 6 by rating)
    $groupA = array_slice($teamIds, 0, 6);
    // Group B teams (next 6 by rating)
    $groupB = array_slice($teamIds, 6, 6);
    
    // Initialize standings for all teams (without group_name since column doesn't exist)
    $position = 1;
    foreach ($teamIds as $teamId) {
        DB::table('event_standings')->insert([
            'event_id' => $event->id,
            'team_id' => $teamId,
            'position' => $position,
            'points' => 0,
            'matches_played' => 0,
            'wins' => 0,
            'losses' => 0,
            'maps_won' => 0,
            'maps_lost' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $position++;
    }
    
    DB::commit();
    
    echo "\n✅ China Tournament Successfully Created!\n";
    echo "================================\n";
    echo "Event ID: {$event->id}\n";
    echo "Event Name: {$event->name}\n";
    echo "Start Date: August 10, 2025\n";
    echo "Format: Groups (Round Robin) → Playoffs (Double Elimination)\n";
    echo "Teams: 12 registered\n";
    echo "Group A: " . implode(', ', array_map(fn($id) => Team::find($id)->short_name, $groupA)) . "\n";
    echo "Group B: " . implode(', ', array_map(fn($id) => Team::find($id)->short_name, $groupB)) . "\n";
    echo "\nNext Step: Generate brackets for Stage 1 (Round Robin groups)\n";
    echo "Then after group stage: Generate Stage 2 brackets (Double Elimination playoffs)\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error creating tournament: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}