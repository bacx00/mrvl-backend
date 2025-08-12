<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\MatchModel;
use Illuminate\Support\Facades\DB;

try {
    // Find our Marvel Rivals tournament
    $tournament = Event::where('name', 'LIKE', '%Marvel Rivals Invitational%')
        ->orderBy('id', 'desc')
        ->first();
    
    if (!$tournament) {
        echo "❌ Tournament not found.\n";
        exit(1);
    }
    
    echo "✅ Found tournament: {$tournament->name} (ID: {$tournament->id})\n";
    
    // Get registered teams
    $teams = DB::table('event_teams')
        ->where('event_id', $tournament->id)
        ->orderBy('seed')
        ->get();
    
    if ($teams->isEmpty()) {
        echo "❌ No teams registered.\n";
        exit(1);
    }
    
    echo "📋 Found {$teams->count()} registered teams\n";
    
    // Clear existing matches for this tournament
    MatchModel::where('event_id', $tournament->id)->delete();
    
    // Generate Upper Bracket matches
    echo "\n📊 Generating Tournament Matches...\n";
    
    // Round 1 (Upper Bracket) - 8 matches
    $matchups = [
        [1, 16], [8, 9], [4, 13], [5, 12],
        [2, 15], [7, 10], [3, 14], [6, 11]
    ];
    
    $matchNumber = 1;
    foreach ($matchups as $index => $seeds) {
        $team1 = $teams->where('seed', $seeds[0])->first();
        $team2 = $teams->where('seed', $seeds[1])->first();
        
        if ($team1 && $team2) {
            MatchModel::create([
                'event_id' => $tournament->id,
                'team1_id' => $team1->team_id,
                'team2_id' => $team2->team_id,
                'scheduled_at' => now()->addDays(7)->addHours($index * 2),
                'status' => 'scheduled',
                'round' => 'Round 1',
                'best_of' => 3,
                'bracket_position' => $matchNumber,
                'stage' => 'Upper Bracket'
            ]);
            
            // Get team names for display
            $team1Name = DB::table('teams')->where('id', $team1->team_id)->value('name') ?? 'TBD';
            $team2Name = DB::table('teams')->where('id', $team2->team_id)->value('name') ?? 'TBD';
            
            echo "  Match {$matchNumber}: {$team1Name} vs {$team2Name}\n";
            $matchNumber++;
        }
    }
    
    // Generate placeholder matches for subsequent rounds
    echo "\n📊 Generating remaining bracket structure...\n";
    
    // Upper Bracket Round 2 (4 matches)
    for ($i = 1; $i <= 4; $i++) {
        MatchModel::create([
            'event_id' => $tournament->id,
            'scheduled_at' => now()->addDays(8)->addHours($i * 3),
            'status' => 'scheduled',
            'round' => 'Round 2',
            'best_of' => 3,
            'bracket_position' => $matchNumber++,
            'stage' => 'Upper Bracket'
        ]);
    }
    echo "  ✅ Upper Bracket Round 2: 4 matches\n";
    
    // Upper Bracket Semifinals (2 matches)
    for ($i = 1; $i <= 2; $i++) {
        MatchModel::create([
            'event_id' => $tournament->id,
            'scheduled_at' => now()->addDays(9)->addHours($i * 4),
            'status' => 'scheduled',
            'round' => 'Semifinals',
            'best_of' => 3,
            'bracket_position' => $matchNumber++,
            'stage' => 'Upper Bracket'
        ]);
    }
    echo "  ✅ Upper Bracket Semifinals: 2 matches\n";
    
    // Upper Bracket Final (1 match)
    MatchModel::create([
        'event_id' => $tournament->id,
        'scheduled_at' => now()->addDays(10)->addHours(6),
        'status' => 'scheduled',
        'round' => 'Upper Final',
        'best_of' => 5,
        'bracket_position' => $matchNumber++,
        'stage' => 'Upper Bracket'
    ]);
    echo "  ✅ Upper Bracket Final: 1 match\n";
    
    // Lower Bracket matches
    echo "\n📊 Generating Lower Bracket...\n";
    
    // Lower Round 1 (4 matches)
    for ($i = 1; $i <= 4; $i++) {
        MatchModel::create([
            'event_id' => $tournament->id,
            'scheduled_at' => now()->addDays(8)->addHours($i * 2),
            'status' => 'scheduled',
            'round' => 'Lower Round 1',
            'best_of' => 3,
            'bracket_position' => $matchNumber++,
            'stage' => 'Lower Bracket'
        ]);
    }
    echo "  ✅ Lower Bracket Round 1: 4 matches\n";
    
    // Lower subsequent rounds
    $lowerRounds = [
        ['name' => 'Lower Round 2', 'matches' => 4, 'day' => 9],
        ['name' => 'Lower Round 3', 'matches' => 2, 'day' => 10],
        ['name' => 'Lower Round 4', 'matches' => 2, 'day' => 11],
        ['name' => 'Lower Semifinals', 'matches' => 1, 'day' => 12],
        ['name' => 'Lower Final', 'matches' => 1, 'day' => 13]
    ];
    
    foreach ($lowerRounds as $roundData) {
        for ($i = 1; $i <= $roundData['matches']; $i++) {
            MatchModel::create([
                'event_id' => $tournament->id,
                'scheduled_at' => now()->addDays($roundData['day'])->addHours($i * 3),
                'status' => 'scheduled',
                'round' => $roundData['name'],
                'best_of' => 3,
                'bracket_position' => $matchNumber++,
                'stage' => 'Lower Bracket'
            ]);
        }
        echo "  ✅ {$roundData['name']}: {$roundData['matches']} matches\n";
    }
    
    // Grand Final
    MatchModel::create([
        'event_id' => $tournament->id,
        'scheduled_at' => now()->addDays(14)->addHours(18),
        'status' => 'scheduled',
        'round' => 'Grand Final',
        'best_of' => 5,
        'bracket_position' => $matchNumber++,
        'stage' => 'Grand Final'
    ]);
    echo "  ✅ Grand Final: 1 match (Bo5)\n";
    
    // Update tournament status
    $tournament->update(['status' => 'ongoing']);
    
    // Summary
    $totalMatches = MatchModel::where('event_id', $tournament->id)->count();
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "🎉 BRACKET GENERATION COMPLETE!\n";
    echo str_repeat('=', 50) . "\n";
    echo "Tournament: {$tournament->name}\n";
    echo "Bracket Type: Double Elimination\n";
    echo "Teams: 16\n";
    echo "Total Matches: {$totalMatches}\n";
    echo "\n✅ Tournament is ready to begin!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}