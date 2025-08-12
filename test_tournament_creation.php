<?php

/**
 * Simple Tournament Creation Test
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

echo "=== Tournament System Test ===\n\n";

try {
    // Get or create test user
    $organizer = User::firstOrCreate(
        ['email' => 'test.organizer@example.com'],
        [
            'name' => 'Test Organizer',
            'password' => bcrypt('test123'),
            'role' => 'admin'
        ]
    );
    
    echo "✅ Organizer ready: {$organizer->name}\n";
    
    // Get some teams
    $teams = Team::take(8)->get();
    echo "✅ Found {$teams->count()} teams\n";
    
    if ($teams->count() < 4) {
        echo "❌ Need at least 4 teams. Creating sample teams...\n";
        
        for ($i = 1; $i <= 8; $i++) {
            Team::create([
                'name' => "Test Team {$i}",
                'tag' => "T{$i}",
                'region' => 'global',
                'country' => 'US',
                'rating' => 1000 + ($i * 100),
                'wins' => rand(5, 25),
                'losses' => rand(2, 15)
            ]);
        }
        
        $teams = Team::latest()->take(8)->get();
        echo "✅ Created 8 test teams\n";
    }
    
    // Create a simple tournament
    $tournament = Tournament::create([
        'name' => 'Test Tournament',
        'slug' => 'test-tournament-' . time(),
        'type' => 'tournament',
        'format' => 'single_elimination',
        'status' => 'draft',
        'description' => 'A test tournament to verify the system works',
        'region' => 'global',
        'prize_pool' => 10000,
        'currency' => 'USD',
        'team_count' => $teams->count(),
        'max_teams' => 16,
        'min_teams' => 4,
        'start_date' => now()->addDays(1),
        'end_date' => now()->addDays(3),
        'registration_start' => now(),
        'registration_end' => now()->addHours(12),
        'timezone' => 'UTC',
        'organizer_id' => $organizer->id,
        'settings' => ['bracket_type' => 'single_elimination'],
        'rules' => ['Standard tournament rules'],
        'current_phase' => 'registration',
        'featured' => true,
        'public' => true
    ]);
    
    echo "✅ Tournament created: {$tournament->name} (ID: {$tournament->id})\n";
    
    // Register teams
    foreach ($teams as $index => $team) {
        $tournament->teams()->attach($team->id, [
            'seed' => $index + 1,
            'status' => 'registered',
            'registered_at' => now(),
            'swiss_wins' => 0,
            'swiss_losses' => 0,
            'swiss_score' => 0
        ]);
    }
    
    echo "✅ Registered {$teams->count()} teams\n";
    
    // Create a bracket stage
    $stage = BracketStage::create([
        'tournament_id' => $tournament->id,
        'name' => 'Main Bracket',
        'type' => 'single_elimination',
        'stage_order' => 1,
        'status' => 'pending',
        'max_teams' => $teams->count(),
        'current_round' => 1,
        'total_rounds' => ceil(log($teams->count(), 2)),
        'settings' => ['bracket_size' => 8]
    ]);
    
    echo "✅ Created bracket stage: {$stage->name}\n";
    
    // Create first round matches
    $teamsList = $teams->toArray();
    $matchNumber = 1;
    
    for ($i = 0; $i < count($teamsList); $i += 2) {
        if (isset($teamsList[$i + 1])) {
            $match = BracketMatch::create([
                'tournament_id' => $tournament->id,
                'bracket_stage_id' => $stage->id,
                'round_number' => 1,
                'match_number' => $matchNumber,
                'team1_id' => $teamsList[$i]['id'],
                'team2_id' => $teamsList[$i + 1]['id'],
                'status' => 'pending',
                'match_format' => 'bo3',
                'scheduled_at' => now()->addHours($matchNumber * 2)
            ]);
            
            echo "✅ Created match {$matchNumber}: {$teamsList[$i]['name']} vs {$teamsList[$i + 1]['name']}\n";
            $matchNumber++;
        }
    }
    
    // Display tournament info
    echo "\n=== Tournament Summary ===\n";
    echo "Name: {$tournament->name}\n";
    echo "Format: {$tournament->format}\n";
    echo "Teams: {$tournament->team_count}\n";
    echo "Prize Pool: $" . number_format($tournament->prize_pool) . "\n";
    echo "Status: {$tournament->status}\n";
    echo "Stages: " . $tournament->bracketStages()->count() . "\n";
    echo "Matches: " . $tournament->matches()->count() . "\n";
    
    echo "\n=== Test Successful ===\n";
    echo "Tournament ID: {$tournament->id}\n";
    echo "All components working correctly!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}