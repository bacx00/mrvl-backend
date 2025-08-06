<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MarvelRivalsInvitational2025Seeder extends Seeder
{
    /**
     * Seed the Marvel Rivals Invitational 2025 event with Swiss + Double Elimination format
     */
    public function run()
    {
        // Check if event already exists
        $existingEvent = DB::table('events')->where('slug', 'marvel-rivals-invitational-2025-na')->first();
        if ($existingEvent) {
            echo "Event already exists, clearing old data...\n";
            
            // Delete existing matches
            DB::table('matches')->where('event_id', $existingEvent->id)->delete();
            
            // Delete swiss standings
            DB::table('swiss_standings')->where('event_id', $existingEvent->id)->delete();
            
            // Delete bracket progression
            DB::table('bracket_progression')->where('event_id', $existingEvent->id)->delete();
            
            // Delete event teams
            DB::table('event_teams')->where('event_id', $existingEvent->id)->delete();
            
            // Delete the event
            DB::table('events')->where('id', $existingEvent->id)->delete();
        }
        
        // Create the event
        $eventId = DB::table('events')->insertGetId([
            'name' => 'Marvel Rivals Invitational 2025: North America',
            'slug' => 'marvel-rivals-invitational-2025-na',
            'description' => 'An online North American Marvel Rivals Showmatch organized by NetEase. This A-Tier Showmatch features 8 teams competing for a $100,000 USD prize pool.',
            'start_date' => Carbon::create(2025, 3, 14),
            'end_date' => Carbon::create(2025, 3, 23),
            'format' => 'double_elimination',
            'tournament_format' => 'marvel_rivals_invitational',
            'tournament_series' => 'marvel_rivals_invitational',
            'phases' => json_encode(['swiss_stage', 'playoffs']),
            'current_phase' => 'registration',
            'mr_tournament_stage' => 'registration_open',
            'status' => 'upcoming',
            'type' => 'invitational',
            'tier' => 'A',
            'prize_pool' => 100000,
            'currency' => 'USD',
            'region' => 'North America',
            'mr_region' => 'americas',
            'platform_category' => 'cross_platform',
            'game_mode' => 'Marvel Rivals',
            'max_teams' => 8,
            'swiss_rounds' => 3,
            'has_lower_bracket' => true,
            'has_consolation_final' => false,
            'final_format' => 'bo7',
            'mr_match_format' => 'bo5',
            'organizer_id' => 1, // Assuming admin user has ID 1
            'featured' => true,
            'public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create or get teams
        $teams = [
            ['name' => 'Cloud9 Marvel', 'short_name' => 'C9M', 'country' => 'US', 'region' => 'NA', 'rating' => 2100],
            ['name' => 'OpTic Marvel', 'short_name' => 'OGM', 'country' => 'US', 'region' => 'NA', 'rating' => 2050],
            ['name' => 'Team Liquid Marvel', 'short_name' => 'TLM', 'country' => 'US', 'region' => 'NA', 'rating' => 2075],
            ['name' => 'FaZe Marvel', 'short_name' => 'FZM', 'country' => 'US', 'region' => 'NA', 'rating' => 2025],
            ['name' => 'NRG Marvel', 'short_name' => 'NRGM', 'country' => 'US', 'region' => 'NA', 'rating' => 2000],
            ['name' => 'Sentinels Marvel', 'short_name' => 'SENM', 'country' => 'US', 'region' => 'NA', 'rating' => 2125],
            ['name' => '100T Marvel', 'short_name' => '100TM', 'country' => 'US', 'region' => 'NA', 'rating' => 1975],
            ['name' => 'TSM Marvel', 'short_name' => 'TSMM', 'country' => 'US', 'region' => 'NA', 'rating' => 1950]
        ];

        $teamIds = [];
        foreach ($teams as $index => $teamData) {
            // Check if team exists
            $existingTeam = DB::table('teams')->where('name', $teamData['name'])->first();
            
            if ($existingTeam) {
                $teamId = $existingTeam->id;
            } else {
                $teamId = DB::table('teams')->insertGetId(array_merge($teamData, [
                    'slug' => strtolower(str_replace(' ', '-', $teamData['name'])),
                    'flag' => $teamData['country'],
                    'country_code' => $teamData['country'],
                    'platform' => 'PC',
                    'game' => 'Marvel Rivals',
                    'elo_rating' => $teamData['rating'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
            
            $teamIds[] = $teamId;
            
            // Add team to event
            DB::table('event_teams')->insert([
                'event_id' => $eventId,
                'team_id' => $teamId,
                'seed' => $index + 1,
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create players for each team (5 players + 1 coach)
        $roles = ['Duelist', 'Vanguard', 'Strategist', 'Duelist', 'Strategist'];
        $playerNames = [
            'C9M' => ['Xeppaa', 'vanity', 'leaf', 'jakee', 'runi'],
            'OGM' => ['yay', 'FNS', 'crashies', 'Victor', 'Marved'],
            'TLM' => ['ScreaM', 'Jamppi', 'soulcas', 'dimasick', 'Nivera'],
            'FZM' => ['babybay', 'dicey', 'supamen', 'poised', 'flyuh'],
            'NRGM' => ['s0m', 'ardiis', 'Ethan', 'tex', 'hazed'],
            'SENM' => ['TenZ', 'zekken', 'sacy', 'pancada', 'johnqt'],
            '100TM' => ['Asuna', 'bang', 'stellar', 'derrek', 'Cryo'],
            'TSMM' => ['Subroza', 'gMd', 'hazed', 'corey', 'WARDELL']
        ];

        foreach ($teams as $index => $team) {
            $teamId = $teamIds[$index];
            $shortName = $team['short_name'];
            
            if (isset($playerNames[$shortName])) {
                foreach ($playerNames[$shortName] as $playerIndex => $playerName) {
                    DB::table('players')->insertOrIgnore([
                        'name' => $playerName,
                        'username' => strtolower($playerName),
                        'team_id' => $teamId,
                        'role' => $roles[$playerIndex],
                        'country' => $team['country'],
                        'country_code' => $team['country'],
                        'region' => $team['region'],
                        'position_order' => $playerIndex + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Initialize Swiss standings
        foreach ($teamIds as $teamId) {
            DB::table('swiss_standings')->insert([
                'event_id' => $eventId,
                'team_id' => $teamId,
                'wins' => 0,
                'losses' => 0,
                'map_wins' => 0,
                'map_losses' => 0,
                'swiss_score' => 0,
                'buchholz_score' => 0,
                'round_difference' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::table('bracket_progression')->insert([
                'event_id' => $eventId,
                'team_id' => $teamId,
                'stage' => 'swiss',
                'current_position' => 'swiss_r1',
                'matches_played' => 0,
                'matches_won' => 0,
                'maps_played' => 0,
                'maps_won' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create Swiss Round 1 matches
        $swissR1Matches = [
            ['team1' => 0, 'team2' => 7, 'scheduled' => Carbon::create(2025, 3, 14, 10, 0)], // C9 vs TSM
            ['team1' => 1, 'team2' => 6, 'scheduled' => Carbon::create(2025, 3, 14, 13, 0)], // OG vs 100T
            ['team1' => 2, 'team2' => 5, 'scheduled' => Carbon::create(2025, 3, 14, 16, 0)], // TL vs SEN
            ['team1' => 3, 'team2' => 4, 'scheduled' => Carbon::create(2025, 3, 14, 19, 0)], // FaZe vs NRG
        ];

        foreach ($swissR1Matches as $index => $matchData) {
            DB::table('matches')->insert([
                'event_id' => $eventId,
                'team1_id' => $teamIds[$matchData['team1']],
                'team2_id' => $teamIds[$matchData['team2']],
                'stage_type' => 'swiss',
                'swiss_round' => 1,
                'round' => 1,
                'best_of' => 3,
                'maps_required_to_win' => 2,
                'format' => 'bo3',
                'status' => 'upcoming',
                'scheduled_at' => $matchData['scheduled'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create placeholder playoff matches
        $this->createPlayoffPlaceholders($eventId);

        echo "Marvel Rivals Invitational 2025 seeded successfully!\n";
    }

    private function createPlayoffPlaceholders($eventId)
    {
        $matches = [
            // Upper Bracket Semifinals
            [
                'stage_type' => 'upper_bracket',
                'round' => 1,
                'bracket_position' => 1,
                'match_number' => 'UB-SF1',
                'round_name' => 'Upper Bracket Semifinals',
                'team1_source' => 'swiss_1st',
                'team2_source' => 'swiss_4th',
                'best_of' => 5,
                'winner_advances_to' => 'UB-F',
                'loser_advances_to' => 'LB-QF2',
                'scheduled_at' => Carbon::create(2025, 3, 19, 10, 0),
            ],
            [
                'stage_type' => 'upper_bracket',
                'round' => 1,
                'bracket_position' => 2,
                'match_number' => 'UB-SF2',
                'round_name' => 'Upper Bracket Semifinals',
                'team1_source' => 'swiss_2nd',
                'team2_source' => 'swiss_3rd',
                'best_of' => 5,
                'winner_advances_to' => 'UB-F',
                'loser_advances_to' => 'LB-QF1',
                'scheduled_at' => Carbon::create(2025, 3, 19, 14, 0),
            ],
            // Upper Bracket Final
            [
                'stage_type' => 'upper_bracket',
                'round' => 2,
                'bracket_position' => 1,
                'match_number' => 'UB-F',
                'round_name' => 'Upper Bracket Final',
                'team1_source' => 'winner_of_UB-SF1',
                'team2_source' => 'winner_of_UB-SF2',
                'best_of' => 5,
                'winner_advances_to' => 'GF',
                'loser_advances_to' => 'LB-F',
                'scheduled_at' => Carbon::create(2025, 3, 21, 14, 0),
            ],
            // Lower Bracket Round 1
            [
                'stage_type' => 'lower_bracket',
                'round' => 1,
                'bracket_position' => 1,
                'match_number' => 'LB-R1-1',
                'round_name' => 'Lower Bracket Round 1',
                'team1_source' => 'swiss_5th',
                'team2_source' => 'swiss_8th',
                'best_of' => 5,
                'winner_advances_to' => 'LB-QF1',
                'scheduled_at' => Carbon::create(2025, 3, 20, 10, 0),
            ],
            [
                'stage_type' => 'lower_bracket',
                'round' => 1,
                'bracket_position' => 2,
                'match_number' => 'LB-R1-2',
                'round_name' => 'Lower Bracket Round 1',
                'team1_source' => 'swiss_6th',
                'team2_source' => 'swiss_7th',
                'best_of' => 5,
                'winner_advances_to' => 'LB-QF2',
                'scheduled_at' => Carbon::create(2025, 3, 20, 14, 0),
            ],
            // Lower Bracket Quarterfinals
            [
                'stage_type' => 'lower_bracket',
                'round' => 2,
                'bracket_position' => 1,
                'match_number' => 'LB-QF1',
                'round_name' => 'Lower Bracket Quarterfinals',
                'team1_source' => 'winner_of_LB-R1-1',
                'team2_source' => 'loser_of_UB-SF2',
                'best_of' => 5,
                'winner_advances_to' => 'LB-SF',
                'scheduled_at' => Carbon::create(2025, 3, 21, 10, 0),
            ],
            [
                'stage_type' => 'lower_bracket',
                'round' => 2,
                'bracket_position' => 2,
                'match_number' => 'LB-QF2',
                'round_name' => 'Lower Bracket Quarterfinals',
                'team1_source' => 'winner_of_LB-R1-2',
                'team2_source' => 'loser_of_UB-SF1',
                'best_of' => 5,
                'winner_advances_to' => 'LB-SF',
                'scheduled_at' => Carbon::create(2025, 3, 21, 18, 0),
            ],
            // Lower Bracket Semifinal
            [
                'stage_type' => 'lower_bracket',
                'round' => 3,
                'bracket_position' => 1,
                'match_number' => 'LB-SF',
                'round_name' => 'Lower Bracket Semifinal',
                'team1_source' => 'winner_of_LB-QF1',
                'team2_source' => 'winner_of_LB-QF2',
                'best_of' => 5,
                'winner_advances_to' => 'LB-F',
                'scheduled_at' => Carbon::create(2025, 3, 22, 10, 0),
            ],
            // Lower Bracket Final
            [
                'stage_type' => 'lower_bracket',
                'round' => 4,
                'bracket_position' => 1,
                'match_number' => 'LB-F',
                'round_name' => 'Lower Bracket Final',
                'team1_source' => 'winner_of_LB-SF',
                'team2_source' => 'loser_of_UB-F',
                'best_of' => 5,
                'winner_advances_to' => 'GF',
                'scheduled_at' => Carbon::create(2025, 3, 22, 14, 0),
            ],
            // Grand Final
            [
                'stage_type' => 'grand_final',
                'round' => 1,
                'bracket_position' => 1,
                'match_number' => 'GF',
                'round_name' => 'Grand Final',
                'team1_source' => 'winner_of_UB-F',
                'team2_source' => 'winner_of_LB-F',
                'best_of' => 7,
                'scheduled_at' => Carbon::create(2025, 3, 23, 14, 0),
            ],
        ];

        foreach ($matches as $match) {
            $matchData = [
                'event_id' => $eventId,
                'stage_type' => $match['stage_type'],
                'round' => $match['round'],
                'best_of' => $match['best_of'],
                'maps_required_to_win' => ceil($match['best_of'] / 2),
                'format' => 'bo' . $match['best_of'],
                'status' => 'upcoming',
                'scheduled_at' => $match['scheduled_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            // Add team source info for bracket progression
            if (isset($match['team1_source'])) {
                $matchData['team1_source'] = $match['team1_source'];
            }
            if (isset($match['team2_source'])) {
                $matchData['team2_source'] = $match['team2_source'];
            }
            if (isset($match['winner_advances_to'])) {
                $matchData['winner_advances_to'] = $match['winner_advances_to'];
            }
            if (isset($match['loser_advances_to'])) {
                $matchData['loser_advances_to'] = $match['loser_advances_to'];
            }
            
            DB::table('matches')->insert($matchData);
        }
    }
}