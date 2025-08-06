<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Event;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Generating China Tournament Bracket...\n";
echo "=====================================\n\n";

try {
    // Get the China tournament
    $event = Event::find(1);
    
    if (!$event) {
        echo "❌ China tournament not found\n";
        exit(1);
    }
    
    echo "✅ Found event: {$event->name}\n";
    
    // Get teams
    $teams = DB::table('event_teams')
        ->join('teams', 'event_teams.team_id', '=', 'teams.id')
        ->where('event_teams.event_id', $event->id)
        ->select('teams.*')
        ->orderBy('teams.rating', 'desc')
        ->get();
    
    echo "✅ Found {$teams->count()} teams\n\n";
    
    // Clear any existing matches for this event
    GameMatch::where('event_id', $event->id)->delete();
    echo "✅ Cleared existing matches\n";
    
    // Generate Round Robin for Group Stage (2 groups of 6)
    $groupA = $teams->slice(0, 6);
    $groupB = $teams->slice(6, 6);
    
    echo "📋 Group A: " . $groupA->pluck('short_name')->implode(', ') . "\n";
    echo "📋 Group B: " . $groupB->pluck('short_name')->implode(', ') . "\n\n";
    
    $matches = [];
    $position = 1;
    
    // Group A matches
    echo "Generating Group A matches...\n";
    foreach ($groupA as $i => $team1) {
        foreach ($groupA->slice($i + 1) as $team2) {
            $matches[] = [
                'event_id' => $event->id,
                'round' => 1,
                'bracket_position' => $position++,
                'bracket_type' => 'group_a',
                'team1_id' => $team1->id,
                'team2_id' => $team2->id,
                'status' => 'upcoming',
                'format' => 'bo3',
                'scheduled_at' => now()->addDays(1),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
    }
    
    // Group B matches
    echo "Generating Group B matches...\n";
    foreach ($groupB as $i => $team1) {
        foreach ($groupB->slice($i + 1) as $team2) {
            $matches[] = [
                'event_id' => $event->id,
                'round' => 1,
                'bracket_position' => $position++,
                'bracket_type' => 'group_b',
                'team1_id' => $team1->id,
                'team2_id' => $team2->id,
                'status' => 'upcoming',
                'format' => 'bo3',
                'scheduled_at' => now()->addDays(1),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
    }
    
    // Insert group stage matches
    DB::table('matches')->insert($matches);
    echo "✅ Created " . count($matches) . " group stage matches\n\n";
    
    // Create placeholder matches for playoffs (top 8 advance)
    $playoffMatches = [];
    
    // Quarterfinals (4 matches)
    for ($i = 0; $i < 4; $i++) {
        $playoffMatches[] = [
            'event_id' => $event->id,
            'round' => 2,
            'bracket_position' => $position++,
            'bracket_type' => 'upper',
            'team1_id' => null, // Will be filled after group stage
            'team2_id' => null,
            'status' => 'upcoming',
            'format' => 'bo3',
            'scheduled_at' => now()->addDays(3),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
    
    // Semifinals (2 matches)
    for ($i = 0; $i < 2; $i++) {
        $playoffMatches[] = [
            'event_id' => $event->id,
            'round' => 3,
            'bracket_position' => $position++,
            'bracket_type' => 'upper',
            'team1_id' => null,
            'team2_id' => null,
            'status' => 'upcoming',
            'format' => 'bo3',
            'scheduled_at' => now()->addDays(4),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
    
    // Grand Final
    $playoffMatches[] = [
        'event_id' => $event->id,
        'round' => 4,
        'bracket_position' => $position++,
        'bracket_type' => 'grand_final',
        'team1_id' => null,
        'team2_id' => null,
        'status' => 'upcoming',
        'format' => 'bo5',
        'scheduled_at' => now()->addDays(5),
        'created_at' => now(),
        'updated_at' => now()
    ];
    
    // Insert playoff matches
    DB::table('matches')->insert($playoffMatches);
    echo "✅ Created " . count($playoffMatches) . " playoff placeholder matches\n\n";
    
    // Update event status
    $event->update(['status' => 'upcoming']);
    
    $totalMatches = count($matches) + count($playoffMatches);
    
    echo "🏆 BRACKET GENERATION COMPLETE!\n";
    echo "===============================\n";
    echo "Event: {$event->name}\n";
    echo "Total matches: {$totalMatches}\n";
    echo "Group stage: " . count($matches) . " matches\n";
    echo "Playoffs: " . count($playoffMatches) . " matches\n";
    echo "\nTournament ready for August 10th!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}