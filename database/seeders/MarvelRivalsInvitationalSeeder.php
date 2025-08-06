<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tournament;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\BracketPosition;
use App\Models\Team;

class MarvelRivalsInvitationalSeeder extends Seeder
{
    public function run()
    {
        // Create the tournament
        $tournament = Tournament::create([
            'name' => 'Marvel Rivals Invitational 2025: North America',
            'slug' => 'marvel-rivals-invitational-2025-na',
            'type' => 'swiss_double_elim',
            'status' => 'completed',
            'description' => 'Official Marvel Rivals tournament featuring top North American teams',
            'region' => 'NA',
            'prize_pool' => 100000.00,
            'team_count' => 8,
            'start_date' => '2025-01-15 18:00:00',
            'end_date' => '2025-01-19 22:00:00',
            'settings' => [
                'swiss_rounds' => 3,
                'upper_bracket_teams' => 4,
                'lower_bracket_teams' => 4,
                'grand_final_format' => 'bo7'
            ]
        ]);

        // Get teams (first 8 NA teams)
        $teams = Team::where('region', 'NA')->limit(8)->get();
        if ($teams->count() < 8) {
            $this->command->error('Not enough NA teams found. Please run team seeder first.');
            return;
        }

        // Add teams to tournament with Swiss results
        $swissResults = [
            ['team' => '100 Thieves', 'wins' => 3, 'losses' => 0, 'score' => 3.0],
            ['team' => 'Sentinels', 'wins' => 2, 'losses' => 1, 'score' => 2.0],
            ['team' => 'ENVY', 'wins' => 2, 'losses' => 1, 'score' => 2.0],
            ['team' => 'Team Liquid', 'wins' => 2, 'losses' => 1, 'score' => 2.0],
            ['team' => 'Cloud9', 'wins' => 1, 'losses' => 2, 'score' => 1.0],
            ['team' => 'TSM', 'wins' => 1, 'losses' => 2, 'score' => 1.0],
            ['team' => 'NRG', 'wins' => 1, 'losses' => 2, 'score' => 1.0],
            ['team' => 'FaZe Clan', 'wins' => 0, 'losses' => 3, 'score' => 0.0]
        ];

        $attachedTeams = [];
        foreach ($swissResults as $index => $result) {
            $team = $teams->where('name', $result['team'])->first() ?: $teams[$index];
            
            // Skip if already attached
            if (in_array($team->id, $attachedTeams)) {
                continue;
            }
            
            $tournament->teams()->attach($team->id, [
                'seed' => $index + 1,
                'swiss_wins' => $result['wins'],
                'swiss_losses' => $result['losses'],
                'swiss_score' => $result['score'],
                'status' => 'active',
                'registered_at' => now()->subDays(7)
            ]);
            
            $attachedTeams[] = $team->id;
        }

        // Create bracket stages
        $swissStage = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Swiss Stage',
            'type' => 'swiss',
            'stage_order' => 1,
            'status' => 'completed'
        ]);

        $upperBracket = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Upper Bracket',
            'type' => 'upper_bracket',
            'stage_order' => 2,
            'status' => 'completed'
        ]);

        $lowerBracket = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Lower Bracket',
            'type' => 'lower_bracket',
            'stage_order' => 3,
            'status' => 'completed'
        ]);

        $grandFinal = BracketStage::create([
            'tournament_id' => $tournament->id,
            'name' => 'Grand Final',
            'type' => 'grand_final',
            'stage_order' => 4,
            'status' => 'completed'
        ]);

        // Get teams for bracket seeding
        $upperTeams = $teams->take(4); // Top 4 from Swiss
        $lowerTeams = $teams->skip(4)->take(4); // Bottom 4 from Swiss

        // Create Upper Bracket matches
        $this->createUpperBracketMatches($tournament, $upperBracket, $upperTeams);
        
        // Create Lower Bracket matches
        $this->createLowerBracketMatches($tournament, $lowerBracket, $lowerTeams, $upperTeams);
        
        // Create Grand Final
        $this->createGrandFinal($tournament, $grandFinal, $upperTeams->first(), $upperTeams->skip(1)->first());
        
        $this->command->info('Marvel Rivals Invitational 2025 tournament created successfully!');
    }

    private function createUpperBracketMatches($tournament, $stage, $teams)
    {
        $team1 = $teams[0]; // 100 Thieves (seed 1)
        $team2 = $teams[1]; // Sentinels (seed 2)
        $team3 = $teams[2]; // ENVY (seed 3)
        $team4 = $teams[3]; // Team Liquid (seed 4)

        // Upper Bracket Semifinals
        $ubSf1 = BracketMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_stage_id' => $stage->id,
            'match_id' => 'UB-SF1',
            'round_name' => 'Upper Bracket Semifinals',
            'round_number' => 1,
            'match_number' => 1,
            'team1_id' => $team1->id,
            'team2_id' => $team4->id,
            'team1_source' => 'seed_1',
            'team2_source' => 'seed_4',
            'team1_score' => 3,
            'team2_score' => 1,
            'winner_id' => $team1->id,
            'loser_id' => $team4->id,
            'status' => 'completed',
            'best_of' => 5,
            'scheduled_at' => '2025-01-17 19:00:00',
            'completed_at' => '2025-01-17 21:30:00',
            'winner_advances_to' => 'UB-F',
            'loser_advances_to' => 'LB-SF1'
        ]);

        $ubSf2 = BracketMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_stage_id' => $stage->id,
            'match_id' => 'UB-SF2',
            'round_name' => 'Upper Bracket Semifinals',
            'round_number' => 1,
            'match_number' => 2,
            'team1_id' => $team2->id,
            'team2_id' => $team3->id,
            'team1_source' => 'seed_2',
            'team2_source' => 'seed_3',
            'team1_score' => 3,
            'team2_score' => 2,
            'winner_id' => $team2->id,
            'loser_id' => $team3->id,
            'status' => 'completed',
            'best_of' => 5,
            'scheduled_at' => '2025-01-17 22:00:00',
            'completed_at' => '2025-01-18 00:45:00',
            'winner_advances_to' => 'UB-F',
            'loser_advances_to' => 'LB-SF2'
        ]);

        // Upper Bracket Final
        $ubFinal = BracketMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_stage_id' => $stage->id,
            'match_id' => 'UB-F',
            'round_name' => 'Upper Bracket Final',
            'round_number' => 2,
            'match_number' => 1,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_source' => 'winner_of_UB-SF1',
            'team2_source' => 'winner_of_UB-SF2',
            'team1_score' => 3,
            'team2_score' => 1,
            'winner_id' => $team1->id,
            'loser_id' => $team2->id,
            'status' => 'completed',
            'best_of' => 5,
            'scheduled_at' => '2025-01-18 19:00:00',
            'completed_at' => '2025-01-18 21:15:00',
            'winner_advances_to' => 'GF',
            'loser_advances_to' => 'LB-F'
        ]);

        // Create positions for visual layout
        BracketPosition::create([
            'bracket_match_id' => $ubSf1->id,
            'bracket_stage_id' => $stage->id,
            'column_position' => 1,
            'row_position' => 1,
            'tier' => 1
        ]);

        BracketPosition::create([
            'bracket_match_id' => $ubSf2->id,
            'bracket_stage_id' => $stage->id,
            'column_position' => 1,
            'row_position' => 3,
            'tier' => 1
        ]);

        BracketPosition::create([
            'bracket_match_id' => $ubFinal->id,
            'bracket_stage_id' => $stage->id,
            'column_position' => 2,
            'row_position' => 2,
            'tier' => 2
        ]);
    }

    private function createLowerBracketMatches($tournament, $stage, $lowerTeams, $upperTeams)
    {
        $team5 = $lowerTeams[0]; // Cloud9 (seed 5)
        $team6 = $lowerTeams[1]; // TSM (seed 6)
        $team7 = $lowerTeams[2]; // NRG (seed 7)
        $team8 = $lowerTeams[3]; // FaZe Clan (seed 8)

        // Lower Bracket Round 1
        $lbR1_1 = BracketMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_stage_id' => $stage->id,
            'match_id' => 'LB-R1-1',
            'round_name' => 'Lower Bracket Round 1',
            'round_number' => 1,
            'match_number' => 1,
            'team1_id' => $team5->id,
            'team2_id' => $team8->id,
            'team1_source' => 'seed_5',
            'team2_source' => 'seed_8',
            'team1_score' => 3,
            'team2_score' => 0,
            'winner_id' => $team5->id,
            'loser_id' => $team8->id,
            'status' => 'completed',
            'best_of' => 5,
            'winner_advances_to' => 'LB-R2-1'
        ]);

        $lbR1_2 = BracketMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_stage_id' => $stage->id,
            'match_id' => 'LB-R1-2',
            'round_name' => 'Lower Bracket Round 1',
            'round_number' => 1,
            'match_number' => 2,
            'team1_id' => $team6->id,
            'team2_id' => $team7->id,
            'team1_source' => 'seed_6',
            'team2_source' => 'seed_7',
            'team1_score' => 3,
            'team2_score' => 2,
            'winner_id' => $team6->id,
            'loser_id' => $team7->id,
            'status' => 'completed',
            'best_of' => 5,
            'winner_advances_to' => 'LB-R2-2'
        ]);

        // Additional Lower Bracket rounds would continue here...
        // This is a simplified version showing the structure
    }

    private function createGrandFinal($tournament, $stage, $team1, $team2)
    {
        BracketMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_stage_id' => $stage->id,
            'match_id' => 'GF',
            'round_name' => 'Grand Final',
            'round_number' => 1,
            'match_number' => 1,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_source' => 'winner_of_UB-F',
            'team2_source' => 'winner_of_LB-F',
            'team1_score' => 4,
            'team2_score' => 2,
            'winner_id' => $team1->id,
            'loser_id' => $team2->id,
            'status' => 'completed',
            'best_of' => 7,
            'scheduled_at' => '2025-01-19 20:00:00',
            'completed_at' => '2025-01-19 23:30:00',
            'vods' => [
                'main_stream' => 'https://twitch.tv/marvelrivals',
                'youtube' => 'https://youtube.com/watch?v=example'
            ]
        ]);
    }
}