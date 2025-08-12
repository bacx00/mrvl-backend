<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use Illuminate\Support\Facades\DB;

try {
    // Find our Marvel Rivals tournament
    $tournament = Event::where('name', 'LIKE', '%Marvel Rivals Invitational%')->first();
    
    if (!$tournament) {
        echo "❌ Tournament not found. Creating new one...\n";
        
        // Get or create an organizer
        $organizer = \App\Models\User::where('email', 'admin@marvelrivals.com')->first();
        if (!$organizer) {
            $organizer = \App\Models\User::create([
                'name' => 'Marvel Rivals Esports',
                'email' => 'admin@marvelrivals.com',
                'password' => bcrypt('MRVLAdmin2025!'),
                'role' => 'admin'
            ]);
        }
        
        // Create the tournament
        $tournament = Event::create([
            'name' => 'Marvel Rivals Invitational 2025',
            'slug' => 'marvel-rivals-invitational-2025',
            'description' => 'The premier global Marvel Rivals championship featuring top teams competing for $250,000',
            'type' => 'championship',
            'status' => 'ongoing',
            'prize_pool' => 250000,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(14),
            'region' => 'International',
            'organizer_id' => $organizer->id,
            'organizer' => 'Marvel Rivals Esports',
            'stream_url' => 'https://twitch.tv/marvelrivals',
            'tier' => 'S'
        ]);
        
        echo "✅ Tournament created: ID {$tournament->id}\n";
    } else {
        echo "✅ Found tournament: {$tournament->name} (ID: {$tournament->id})\n";
    }
    
    // Get registered teams
    $teams = DB::table('event_teams')
        ->where('event_id', $tournament->id)
        ->orderBy('seed')
        ->get();
    
    if ($teams->isEmpty()) {
        echo "❌ No teams registered. Please run team registration first.\n";
        exit(1);
    }
    
    echo "📋 Found {$teams->count()} registered teams\n";
    
    // Create bracket structure for double elimination
    $bracket = DB::table('brackets')->where('event_id', $tournament->id)->first();
    
    if (!$bracket) {
        $bracketId = DB::table('brackets')->insertGetId([
            'event_id' => $tournament->id,
            'bracket_type' => 'double_elimination',
            'round' => 1,
            'position' => 1,
            'round_name' => 'Main Bracket',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $bracket = (object)['id' => $bracketId, 'round_name' => 'Main Bracket'];
    }
    
    echo "🏆 Bracket created/found: {$bracket->round_name}\n";
    
    // Generate Upper Bracket matches
    echo "\n📊 Generating Upper Bracket...\n";
    
    // Round 1 (Upper Bracket) - 8 matches
    $round1Matches = [];
    $matchups = [
        [1, 16], [8, 9], [4, 13], [5, 12],
        [2, 15], [7, 10], [3, 14], [6, 11]
    ];
    
    foreach ($matchups as $index => $seeds) {
        $team1 = $teams->where('seed', $seeds[0])->first();
        $team2 = $teams->where('seed', $seeds[1])->first();
        
        if ($team1 && $team2) {
            $matchId = DB::table('bracket_matches')->insertGetId([
                'bracket_id' => $bracket->id,
                'round' => 1,
                'match_number' => $index + 1,
                'team1_id' => $team1->team_id,
                'team2_id' => $team2->team_id,
                'status' => 'scheduled',
                'best_of' => 3,
                'stage' => 'upper',
                'scheduled_at' => now()->addDays(7)->addHours($index * 2),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $round1Matches[] = (object)['id' => $matchId, 'match_number' => $index + 1];
            
            // Get team names for display
            $team1Name = DB::table('teams')->where('id', $team1->team_id)->value('name') ?? 'TBD';
            $team2Name = DB::table('teams')->where('id', $team2->team_id)->value('name') ?? 'TBD';
            
            echo "  Match " . ($index + 1) . ": {$team1Name} vs {$team2Name}\n";
        }
    }
    
    // Generate placeholder matches for subsequent rounds
    echo "\n📊 Generating bracket structure...\n";
    
    // Upper Bracket Round 2 (4 matches)
    for ($i = 1; $i <= 4; $i++) {
        DB::table('bracket_matches')->insert([
            'bracket_id' => $bracket->id,
            'round' => 2,
            'match_number' => 8 + $i,
            'status' => 'pending',
            'best_of' => 3,
            'stage' => 'upper',
            'scheduled_at' => now()->addDays(8)->addHours($i * 3),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    echo "  ✅ Upper Bracket Round 2: 4 matches\n";
    
    // Upper Bracket Semifinals (2 matches)
    for ($i = 1; $i <= 2; $i++) {
        DB::table('bracket_matches')->insert([
            'bracket_id' => $bracket->id,
            'round' => 3,
            'match_number' => 12 + $i,
            'status' => 'pending',
            'best_of' => 3,
            'stage' => 'upper',
            'scheduled_at' => now()->addDays(9)->addHours($i * 4)
        ]);
    }
    echo "  ✅ Upper Bracket Semifinals: 2 matches\n";
    
    // Upper Bracket Final (1 match)
    DB::table('bracket_matches')->insert([
        'bracket_id' => $bracket->id,
        'round' => 4,
        'match_number' => 15,
        'status' => 'pending',
        'best_of' => 5,
        'stage' => 'upper',
        'scheduled_at' => now()->addDays(10)->addHours(6)
    ]);
    echo "  ✅ Upper Bracket Final: 1 match\n";
    
    // Lower Bracket matches
    echo "\n📊 Generating Lower Bracket...\n";
    
    // Lower Round 1 (8 losers from upper R1)
    for ($i = 1; $i <= 4; $i++) {
        DB::table('bracket_matches')->insert([
            'bracket_id' => $bracket->id,
            'round' => 1,
            'match_number' => 15 + $i,
            'status' => 'pending',
            'best_of' => 3,
            'stage' => 'lower',
            'scheduled_at' => now()->addDays(8)->addHours($i * 2)
        ]);
    }
    echo "  ✅ Lower Bracket Round 1: 4 matches\n";
    
    // Continue with lower bracket rounds...
    $lowerRounds = [
        ['round' => 2, 'matches' => 4, 'day' => 9],
        ['round' => 3, 'matches' => 2, 'day' => 10],
        ['round' => 4, 'matches' => 2, 'day' => 11],
        ['round' => 5, 'matches' => 1, 'day' => 12],
        ['round' => 6, 'matches' => 1, 'day' => 13]
    ];
    
    $matchCounter = 20;
    foreach ($lowerRounds as $roundData) {
        for ($i = 1; $i <= $roundData['matches']; $i++) {
            DB::table('bracket_matches')->insert([
                'bracket_id' => $bracket->id,
                'round' => $roundData['round'],
                'match_number' => $matchCounter++,
                'status' => 'pending',
                'best_of' => 3,
                'stage' => 'lower',
                'scheduled_at' => now()->addDays($roundData['day'])->addHours($i * 3)
            ]);
        }
        echo "  ✅ Lower Bracket Round {$roundData['round']}: {$roundData['matches']} matches\n";
    }
    
    // Grand Final
    DB::table('bracket_matches')->insert([
        'bracket_id' => $bracket->id,
        'round' => 7,
        'match_number' => 30,
        'status' => 'pending',
        'best_of' => 5,
        'stage' => 'grand_final',
        'scheduled_at' => now()->addDays(14)->addHours(18)
    ]);
    echo "  ✅ Grand Final: 1 match (Bo5)\n";
    
    // Update tournament status
    $tournament->update(['status' => 'ongoing']);
    
    // Summary
    $totalMatches = BracketMatch::where('bracket_id', $bracket->id)->count();
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "🎉 BRACKET GENERATION COMPLETE!\n";
    echo str_repeat('=', 50) . "\n";
    echo "Tournament: {$tournament->name}\n";
    echo "Bracket Type: Double Elimination\n";
    echo "Teams: 16\n";
    echo "Total Matches: {$totalMatches}\n";
    echo "Upper Bracket: 15 matches\n";
    echo "Lower Bracket: 14 matches\n";
    echo "Grand Final: 1 match\n";
    echo "\n✅ Tournament is ready to begin!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}