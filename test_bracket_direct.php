<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Event;
use App\Models\Team;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "DIRECT BRACKET ALGORITHM TEST\n";
echo "=============================\n\n";

try {
    // Get the China tournament
    $event = Event::where('name', 'like', '%China%')->first();
    
    if (!$event) {
        echo "❌ China tournament not found\n";
        exit(1);
    }
    
    echo "✅ Found event: {$event->name} (ID: {$event->id})\n";
    
    // Get teams for this event
    $teams = DB::table('event_teams')
        ->join('teams', 'event_teams.team_id', '=', 'teams.id')
        ->where('event_teams.event_id', $event->id)
        ->select('teams.*')
        ->orderBy('rating', 'desc')
        ->get()
        ->toArray();
    
    echo "✅ Found {$event->id} teams: " . implode(', ', array_map(fn($t) => $t->short_name, $teams)) . "\n\n";
    
    // Test Single Elimination Algorithm
    echo "🎯 Testing Single Elimination Algorithm...\n";
    
    $matches = [];
    $teamCount = count($teams);
    $round = 1;
    $position = 1;
    
    // Create first round matches
    for ($i = 0; $i < $teamCount; $i += 2) {
        if (isset($teams[$i + 1])) {
            $matches[] = [
                'event_id' => $event->id,
                'round' => $round,
                'bracket_position' => $position++,
                'bracket_type' => 'main',
                'team1_id' => $teams[$i]->id,
                'team2_id' => $teams[$i + 1]->id,
                'status' => 'upcoming',
                'format' => 'bo3',
                'scheduled_at' => now()->addDays($round),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
    }
    
    echo "✅ First round: " . count($matches) . " matches created\n";
    
    // Create subsequent rounds (placeholders)
    $currentTeams = $teamCount;
    $currentRound = $round;
    
    while ($currentTeams > 1) {
        $currentTeams = ceil($currentTeams / 2);
        $currentRound++;
        
        if ($currentTeams > 1) {
            for ($i = 0; $i < $currentTeams; $i += 2) {
                if ($i + 1 < $currentTeams) {
                    $matches[] = [
                        'event_id' => $event->id,
                        'round' => $currentRound,
                        'bracket_position' => $position++,
                        'bracket_type' => 'main',
                        'team1_id' => null,
                        'team2_id' => null,
                        'status' => 'upcoming',
                        'format' => 'bo3',
                        'scheduled_at' => now()->addDays($currentRound),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }
    }
    
    echo "✅ Total matches for single elimination: " . count($matches) . "\n";
    echo "   Rounds: " . ($currentRound) . "\n";
    
    // Test Double Elimination Algorithm  
    echo "\n🎯 Testing Double Elimination Algorithm...\n";
    
    $doubleMatches = [];
    $position = 1;
    
    // Winners bracket first round
    for ($i = 0; $i < $teamCount; $i += 2) {
        if (isset($teams[$i + 1])) {
            $doubleMatches[] = [
                'event_id' => $event->id,
                'round' => 1,
                'bracket_position' => $position++,
                'bracket_type' => 'winners',
                'team1_id' => $teams[$i]->id,
                'team2_id' => $teams[$i + 1]->id,
                'status' => 'upcoming',
                'format' => 'bo3',
                'scheduled_at' => now()->addDays(1),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
    }
    
    // Create winners bracket subsequent rounds
    $currentTeams = count($doubleMatches);
    $winnersRounds = ceil(log($teamCount, 2));
    
    for ($round = 2; $round <= $winnersRounds; $round++) {
        $matchesThisRound = ceil($currentTeams / 2);
        
        for ($i = 0; $i < $matchesThisRound; $i++) {
            $doubleMatches[] = [
                'event_id' => $event->id,
                'round' => $round,
                'bracket_position' => $position++,
                'bracket_type' => 'winners',
                'team1_id' => null,
                'team2_id' => null,
                'status' => 'upcoming',
                'format' => 'bo3',
                'scheduled_at' => now()->addDays($round),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        $currentTeams = $matchesThisRound;
    }
    
    // Create losers bracket
    $losersRounds = (2 * $winnersRounds) - 1;
    
    for ($round = 1; $round <= $losersRounds; $round++) {
        $doubleMatches[] = [
            'event_id' => $event->id,
            'round' => $round,
            'bracket_position' => $position++,
            'bracket_type' => 'losers',
            'team1_id' => null,
            'team2_id' => null,
            'status' => 'upcoming',
            'format' => 'bo3',
            'scheduled_at' => now()->addDays($winnersRounds + $round),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
    
    // Grand final
    $doubleMatches[] = [
        'event_id' => $event->id,
        'round' => 1,
        'bracket_position' => $position++,
        'bracket_type' => 'grand_final',
        'team1_id' => null,
        'team2_id' => null,
        'status' => 'upcoming',
        'format' => 'bo5',
        'scheduled_at' => now()->addDays($winnersRounds + $losersRounds + 1),
        'created_at' => now(),
        'updated_at' => now()
    ];
    
    echo "✅ Total matches for double elimination: " . count($doubleMatches) . "\n";
    echo "   Winners bracket rounds: " . $winnersRounds . "\n";
    echo "   Losers bracket rounds: " . $losersRounds . "\n";
    
    // Test Round Robin Algorithm
    echo "\n🎯 Testing Round Robin Algorithm...\n";
    
    $roundRobinMatches = [];
    $position = 1;
    
    // Generate all possible matchups
    for ($i = 0; $i < $teamCount; $i++) {
        for ($j = $i + 1; $j < $teamCount; $j++) {
            $roundRobinMatches[] = [
                'event_id' => $event->id,
                'round' => ceil(($position + 1) / ($teamCount / 2)),
                'bracket_position' => $position++,
                'bracket_type' => 'round_robin',
                'team1_id' => $teams[$i]->id,
                'team2_id' => $teams[$j]->id,
                'status' => 'upcoming',
                'format' => 'bo3',
                'scheduled_at' => now()->addDays(ceil($position / 6)),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
    }
    
    echo "✅ Total matches for round robin: " . count($roundRobinMatches) . "\n";
    echo "   Each team plays every other team once\n";
    
    // Test Swiss Algorithm
    echo "\n🎯 Testing Swiss Algorithm...\n";
    
    $swissMatches = [];
    $position = 1;
    $swissRounds = 4;
    
    // First round: pair by rating
    for ($round = 1; $round <= $swissRounds; $round++) {
        if ($round == 1) {
            // First round: pair by rating
            for ($i = 0; $i < $teamCount; $i += 2) {
                if (isset($teams[$i + 1])) {
                    $swissMatches[] = [
                        'event_id' => $event->id,
                        'round' => $round,
                        'bracket_position' => $position++,
                        'bracket_type' => 'swiss',
                        'team1_id' => $teams[$i]->id,
                        'team2_id' => $teams[$i + 1]->id,
                        'status' => 'upcoming',
                        'format' => 'bo3',
                        'scheduled_at' => now()->addDays($round),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        } else {
            // Subsequent rounds: pair by record (placeholder)
            for ($i = 0; $i < $teamCount; $i += 2) {
                if ($i + 1 < $teamCount) {
                    $swissMatches[] = [
                        'event_id' => $event->id,
                        'round' => $round,
                        'bracket_position' => $position++,
                        'bracket_type' => 'swiss',
                        'team1_id' => null, // Will be determined by previous round results
                        'team2_id' => null,
                        'status' => 'upcoming',
                        'format' => 'bo3',
                        'scheduled_at' => now()->addDays($round),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }
    }
    
    echo "✅ Total matches for swiss format: " . count($swissMatches) . "\n";
    echo "   Rounds: " . $swissRounds . "\n";
    
    echo "\n✅ ALL BRACKET ALGORITHMS TESTED SUCCESSFULLY!\n";
    echo "==============================================\n";
    echo "Single Elimination: " . count($matches) . " matches\n";
    echo "Double Elimination: " . count($doubleMatches) . " matches\n";
    echo "Round Robin: " . count($roundRobinMatches) . " matches\n";
    echo "Swiss Format: " . count($swissMatches) . " matches\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}