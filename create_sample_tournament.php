<?php

use App\Models\Tournament;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\Team;
use Carbon\Carbon;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Create tournament
    $tournament = Tournament::create([
        'name' => 'Marvel Rivals Championship 2025',
        'slug' => 'marvel-rivals-championship-2025',
        'type' => 'double_elimination',
        'status' => 'ongoing',
        'description' => 'The premier Marvel Rivals tournament featuring top teams from around the world.',
        'region' => 'Global',
        'prize_pool' => 500000,
        'team_count' => 8,
        'start_date' => Carbon::now()->subDays(2),
        'end_date' => Carbon::now()->addDays(5),
        'settings' => [
            'format' => [
                'upper_bracket' => 'bo5',
                'lower_bracket' => 'bo5',
                'grand_final' => 'bo7'
            ]
        ]
    ]);
    
    echo "Tournament created: {$tournament->name}\n";
    
    // Get 8 teams
    $teams = Team::limit(8)->get();
    if ($teams->count() < 8) {
        echo "Not enough teams found. Need at least 8 teams.\n";
        exit(1);
    }
    
    // Add teams to tournament
    foreach ($teams as $index => $team) {
        $tournament->teams()->attach($team->id, [
            'seed' => $index + 1,
            'status' => 'active',
            'registered_at' => Carbon::now()->subDays(7)
        ]);
    }
    
    echo "Added {$teams->count()} teams to tournament\n";
    
    // Create bracket stages
    $upperBracket = BracketStage::create([
        'tournament_id' => $tournament->id,
        'name' => 'Upper Bracket',
        'type' => 'upper_bracket',
        'stage_order' => 1,
        'status' => 'ongoing'
    ]);
    
    $lowerBracket = BracketStage::create([
        'tournament_id' => $tournament->id,
        'name' => 'Lower Bracket',
        'type' => 'lower_bracket',
        'stage_order' => 2,
        'status' => 'pending'
    ]);
    
    $grandFinal = BracketStage::create([
        'tournament_id' => $tournament->id,
        'name' => 'Grand Final',
        'type' => 'grand_final',
        'stage_order' => 3,
        'status' => 'pending'
    ]);
    
    echo "Created bracket stages\n";
    
    // Create Upper Bracket Quarterfinals
    $ubQf1 = BracketMatch::create([
        'tournament_id' => $tournament->id,
        'bracket_stage_id' => $upperBracket->id,
        'match_id' => 'UB-QF1',
        'round_name' => 'Upper Bracket Quarterfinals',
        'round_number' => 1,
        'match_number' => 1,
        'team1_id' => $teams[0]->id, // Seed 1
        'team2_id' => $teams[7]->id, // Seed 8
        'team1_source' => 'seed_1',
        'team2_source' => 'seed_8',
        'status' => 'completed',
        'best_of' => '5',
        'team1_score' => 3,
        'team2_score' => 1,
        'winner_id' => $teams[0]->id,
        'loser_id' => $teams[7]->id,
        'winner_advances_to' => 'UB-SF1',
        'loser_advances_to' => 'LB-R1-M1',
        'scheduled_at' => Carbon::now()->subDays(2),
        'completed_at' => Carbon::now()->subDays(2)->addHours(2)
    ]);
    
    $ubQf2 = BracketMatch::create([
        'tournament_id' => $tournament->id,
        'bracket_stage_id' => $upperBracket->id,
        'match_id' => 'UB-QF2',
        'round_name' => 'Upper Bracket Quarterfinals',
        'round_number' => 1,
        'match_number' => 2,
        'team1_id' => $teams[3]->id, // Seed 4
        'team2_id' => $teams[4]->id, // Seed 5
        'team1_source' => 'seed_4',
        'team2_source' => 'seed_5',
        'status' => 'completed',
        'best_of' => '5',
        'team1_score' => 3,
        'team2_score' => 2,
        'winner_id' => $teams[3]->id,
        'loser_id' => $teams[4]->id,
        'winner_advances_to' => 'UB-SF1',
        'loser_advances_to' => 'LB-R1-M1',
        'scheduled_at' => Carbon::now()->subDays(2)->addHours(3),
        'completed_at' => Carbon::now()->subDays(2)->addHours(5)
    ]);
    
    $ubQf3 = BracketMatch::create([
        'tournament_id' => $tournament->id,
        'bracket_stage_id' => $upperBracket->id,
        'match_id' => 'UB-QF3',
        'round_name' => 'Upper Bracket Quarterfinals',
        'round_number' => 1,
        'match_number' => 3,
        'team1_id' => $teams[1]->id, // Seed 2
        'team2_id' => $teams[6]->id, // Seed 7
        'team1_source' => 'seed_2',
        'team2_source' => 'seed_7',
        'status' => 'pending',
        'best_of' => '5',
        'winner_advances_to' => 'UB-SF2',
        'loser_advances_to' => 'LB-R1-M2',
        'scheduled_at' => Carbon::now()->addHours(2)
    ]);
    
    $ubQf4 = BracketMatch::create([
        'tournament_id' => $tournament->id,
        'bracket_stage_id' => $upperBracket->id,
        'match_id' => 'UB-QF4',
        'round_name' => 'Upper Bracket Quarterfinals',
        'round_number' => 1,
        'match_number' => 4,
        'team1_id' => $teams[2]->id, // Seed 3
        'team2_id' => $teams[5]->id, // Seed 6
        'team1_source' => 'seed_3',
        'team2_source' => 'seed_6',
        'status' => 'pending',
        'best_of' => '5',
        'winner_advances_to' => 'UB-SF2',
        'loser_advances_to' => 'LB-R1-M2',
        'scheduled_at' => Carbon::now()->addHours(5)
    ]);
    
    // Create Upper Bracket Semifinals
    $ubSf1 = BracketMatch::create([
        'tournament_id' => $tournament->id,
        'bracket_stage_id' => $upperBracket->id,
        'match_id' => 'UB-SF1',
        'round_name' => 'Upper Bracket Semifinals',
        'round_number' => 2,
        'match_number' => 1,
        'team1_id' => $teams[0]->id,
        'team2_id' => $teams[3]->id,
        'team1_source' => 'winner_of_UB-QF1',
        'team2_source' => 'winner_of_UB-QF2',
        'status' => 'pending',
        'best_of' => '5',
        'winner_advances_to' => 'UB-F',
        'loser_advances_to' => 'LB-R2-M1',
        'scheduled_at' => Carbon::now()->addDays(1)
    ]);
    
    $ubSf2 = BracketMatch::create([
        'tournament_id' => $tournament->id,
        'bracket_stage_id' => $upperBracket->id,
        'match_id' => 'UB-SF2',
        'round_name' => 'Upper Bracket Semifinals',
        'round_number' => 2,
        'match_number' => 2,
        'team1_source' => 'winner_of_UB-QF3',
        'team2_source' => 'winner_of_UB-QF4',
        'status' => 'pending',
        'best_of' => '5',
        'winner_advances_to' => 'UB-F',
        'loser_advances_to' => 'LB-R2-M2',
        'scheduled_at' => Carbon::now()->addDays(1)->addHours(4)
    ]);
    
    // Create Upper Bracket Final
    $ubF = BracketMatch::create([
        'tournament_id' => $tournament->id,
        'bracket_stage_id' => $upperBracket->id,
        'match_id' => 'UB-F',
        'round_name' => 'Upper Bracket Final',
        'round_number' => 3,
        'match_number' => 1,
        'team1_source' => 'winner_of_UB-SF1',
        'team2_source' => 'winner_of_UB-SF2',
        'status' => 'pending',
        'best_of' => '5',
        'winner_advances_to' => 'GF',
        'loser_advances_to' => 'LB-F',
        'scheduled_at' => Carbon::now()->addDays(3)
    ]);
    
    // Create Lower Bracket Round 1
    $lbR1M1 = BracketMatch::create([
        'tournament_id' => $tournament->id,
        'bracket_stage_id' => $lowerBracket->id,
        'match_id' => 'LB-R1-M1',
        'round_name' => 'Lower Bracket Round 1',
        'round_number' => 1,
        'match_number' => 1,
        'team1_id' => $teams[7]->id,
        'team2_id' => $teams[4]->id,
        'team1_source' => 'loser_of_UB-QF1',
        'team2_source' => 'loser_of_UB-QF2',
        'status' => 'pending',
        'best_of' => '5',
        'winner_advances_to' => 'LB-R2-M1',
        'scheduled_at' => Carbon::now()->addDays(1)->addHours(2)
    ]);
    
    echo "Created all bracket matches\n";
    echo "Tournament setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}