<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Player;
use App\Models\Team;
use App\Models\PlayerTeamHistory;
use Carbon\Carbon;

class PlayersWithHistorySeeder extends Seeder
{
    public function run()
    {
        // 100 Thieves Players
        $team100T = Team::where('name', '100 Thieves')->first();
        
        if ($team100T) {
            $players100T = [
                [
                    'username' => 'delenna',
                    'real_name' => 'Anthony Rosa',
                    'role' => 'Duelist',
                    'main_hero' => 'Spider-Man',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Team Mutants', 'period' => '2024-2025', 'role' => 'Duelist'],
                        ['team' => 'Overwatch Team', 'period' => '2023-2024', 'role' => 'DPS']
                    ],
                    'rating' => 4800,
                    'social_media' => ['twitter' => 'https://twitter.com/delenna_ow']
                ],
                [
                    'username' => 'hxrvey',
                    'real_name' => 'Harvey Scattergood',
                    'role' => 'Vanguard',
                    'main_hero' => 'Venom',
                    'country' => 'Canada',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Team Mutants', 'period' => '2024-2025', 'role' => 'Tank'],
                        ['team' => 'Contenders Team', 'period' => '2023', 'role' => 'Tank']
                    ],
                    'rating' => 4750,
                    'social_media' => ['twitter' => 'https://twitter.com/hxrvey_ow']
                ],
                [
                    'username' => 'SJP',
                    'real_name' => 'James Hudson',
                    'role' => 'Strategist',
                    'main_hero' => 'Mantis',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Team Mutants', 'period' => '2024-2025', 'role' => 'Support'],
                        ['team' => 'University Team', 'period' => '2022-2023', 'role' => 'Support']
                    ],
                    'rating' => 4700,
                    'social_media' => ['twitter' => 'https://twitter.com/sjp_gaming']
                ],
                [
                    'username' => 'Terra',
                    'real_name' => 'Marschal Weaver',
                    'role' => 'Vanguard',
                    'main_hero' => 'Magneto',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Team Mutants', 'period' => '2024-2025', 'role' => 'Tank']
                    ],
                    'rating' => 4720,
                    'social_media' => ['twitter' => 'https://twitter.com/terra_rivals']
                ],
                [
                    'username' => 'TTK',
                    'real_name' => 'Eric Arraiga',
                    'role' => 'Duelist',
                    'main_hero' => 'Iron Man',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Team Mutants', 'period' => '2024-2025', 'role' => 'DPS']
                    ],
                    'rating' => 4780,
                    'social_media' => ['twitter' => 'https://twitter.com/ttk_gaming']
                ],
                [
                    'username' => 'Vinnie',
                    'real_name' => 'Vincent Scaratine',
                    'role' => 'Strategist',
                    'main_hero' => 'Luna Snow',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Team Mutants', 'period' => '2024-2025', 'role' => 'Support']
                    ],
                    'rating' => 4690,
                    'social_media' => ['twitter' => 'https://twitter.com/vinnie_mr']
                ]
            ];

            foreach ($players100T as $playerData) {
                $player = Player::create([
                    'username' => $playerData['username'],
                    'real_name' => $playerData['real_name'],
                    'role' => $playerData['role'],
                    'main_hero' => $playerData['main_hero'],
                    'country' => $playerData['country'],
                    'region' => $playerData['region'],
                    'team_id' => $team100T->id,
                    'past_teams' => $playerData['past_teams'],
                    'rating' => $playerData['rating'],
                    'social_media' => $playerData['social_media'],
                    'status' => 'active'
                ]);

                // Create team history for Team Mutants -> 100 Thieves
                PlayerTeamHistory::create([
                    'player_id' => $player->id,
                    'from_team_id' => null, // Could create Team Mutants if needed
                    'to_team_id' => $team100T->id,
                    'change_date' => Carbon::parse('2025-03-01'),
                    'change_type' => 'transferred',
                    'is_official' => true
                ]);
            }
        }

        // Sentinels Players
        $teamSentinels = Team::where('name', 'Sentinels')->first();
        
        if ($teamSentinels) {
            $playersSentinels = [
                [
                    'username' => 'teki',
                    'real_name' => 'Unknown',
                    'role' => 'Vanguard',
                    'main_hero' => 'Doctor Strange',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Unsigned', 'period' => '2024-2025', 'role' => 'Tank']
                    ],
                    'rating' => 4650,
                    'joined_date' => '2025-04-23'
                ],
                [
                    'username' => 'SuperGomez',
                    'real_name' => 'Anthony Gomez',
                    'role' => 'Duelist',
                    'main_hero' => 'Star-Lord',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Overwatch Contenders', 'period' => '2023-2024', 'role' => 'DPS'],
                        ['team' => 'Content Creator', 'period' => '2024', 'role' => 'Streamer']
                    ],
                    'rating' => 4700,
                    'social_media' => ['twitter' => 'https://twitter.com/supergomez'],
                    'joined_date' => '2025-03-03'
                ],
                [
                    'username' => 'Rymazing',
                    'real_name' => 'Ryan Bishop',
                    'role' => 'Duelist',
                    'main_hero' => 'Psylocke',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Overwatch League', 'period' => '2022-2023', 'role' => 'DPS'],
                        ['team' => 'Free Agent', 'period' => '2024', 'role' => 'DPS']
                    ],
                    'rating' => 4720,
                    'social_media' => ['twitter' => 'https://twitter.com/rymazing'],
                    'joined_date' => '2025-03-03'
                ],
                [
                    'username' => 'aramori',
                    'real_name' => 'Chassidy Kaye',
                    'role' => 'Strategist',
                    'main_hero' => 'Rocket Raccoon',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'Overwatch Contenders', 'period' => '2023-2024', 'role' => 'Support']
                    ],
                    'rating' => 4680,
                    'social_media' => ['twitter' => 'https://twitter.com/aramori'],
                    'joined_date' => '2025-03-03'
                ],
                [
                    'username' => 'Karova',
                    'real_name' => 'Mark Kvashin',
                    'role' => 'Strategist',
                    'main_hero' => 'Jeff the Land Shark',
                    'country' => 'Russia',
                    'region' => 'NA',
                    'past_teams' => [
                        ['team' => 'EU Contenders', 'period' => '2022-2023', 'role' => 'Support'],
                        ['team' => 'NA Team', 'period' => '2024', 'role' => 'Support']
                    ],
                    'rating' => 4690,
                    'social_media' => ['twitter' => 'https://twitter.com/karova_ow'],
                    'joined_date' => '2025-03-03'
                ],
                [
                    'username' => 'nectar',
                    'real_name' => 'Unknown',
                    'role' => 'Sub',
                    'main_hero' => 'Hela',
                    'country' => 'United States',
                    'region' => 'NA',
                    'past_teams' => [],
                    'rating' => 4500,
                    'joined_date' => '2025-05-17'
                ]
            ];

            foreach ($playersSentinels as $playerData) {
                $player = Player::create([
                    'username' => $playerData['username'],
                    'real_name' => $playerData['real_name'],
                    'role' => $playerData['role'],
                    'main_hero' => $playerData['main_hero'],
                    'country' => $playerData['country'],
                    'region' => $playerData['region'],
                    'team_id' => $teamSentinels->id,
                    'past_teams' => $playerData['past_teams'],
                    'rating' => $playerData['rating'],
                    'social_media' => $playerData['social_media'] ?? [],
                    'status' => 'active'
                ]);

                PlayerTeamHistory::create([
                    'player_id' => $player->id,
                    'from_team_id' => null,
                    'to_team_id' => $teamSentinels->id,
                    'change_date' => Carbon::parse($playerData['joined_date']),
                    'change_type' => 'joined',
                    'is_official' => true
                ]);
            }
        }

        // Former Sentinels Players (now on other teams or free agents)
        $formerPlayers = [
            [
                'username' => 'Coluge',
                'real_name' => 'Colin Arai',
                'role' => 'Vanguard',
                'main_hero' => 'Groot',
                'country' => 'Canada',
                'region' => 'NA',
                'past_teams' => [
                    ['team' => 'Sentinels', 'period' => '2025-03 to 2025-04', 'role' => 'Tank'],
                    ['team' => 'Overwatch League', 'period' => '2022-2024', 'role' => 'Tank']
                ],
                'current_team' => 'ENVY',
                'rating' => 4730,
                'left_sentinels' => '2025-04-11'
            ],
            [
                'username' => 'Hogz',
                'real_name' => 'Zairek Poll',
                'role' => 'Vanguard',
                'main_hero' => 'Captain America',
                'country' => 'United States',
                'region' => 'NA',
                'past_teams' => [
                    ['team' => 'Sentinels', 'period' => '2025-03 to 2025-04', 'role' => 'Tank'],
                    ['team' => 'Tier 2 Teams', 'period' => '2023-2024', 'role' => 'Tank']
                ],
                'current_team' => null,
                'rating' => 4600,
                'left_sentinels' => '2025-04-16'
            ]
        ];

        // Add former players to their current teams or as free agents
        $teamENVY = Team::where('name', 'ENVY')->first();
        
        foreach ($formerPlayers as $playerData) {
            $currentTeamId = null;
            if ($playerData['current_team'] && $playerData['current_team'] === 'ENVY' && $teamENVY) {
                $currentTeamId = $teamENVY->id;
            }

            $player = Player::create([
                'username' => $playerData['username'],
                'real_name' => $playerData['real_name'],
                'role' => $playerData['role'],
                'main_hero' => $playerData['main_hero'],
                'country' => $playerData['country'],
                'region' => $playerData['region'],
                'team_id' => $currentTeamId,
                'past_teams' => $playerData['past_teams'],
                'rating' => $playerData['rating'],
                'status' => $currentTeamId ? 'active' : 'inactive'
            ]);

            // Create history for Sentinels -> Current Team/Free Agency
            if ($teamSentinels) {
                PlayerTeamHistory::create([
                    'player_id' => $player->id,
                    'from_team_id' => $teamSentinels->id,
                    'to_team_id' => $currentTeamId,
                    'change_date' => Carbon::parse($playerData['left_sentinels']),
                    'change_type' => $currentTeamId ? 'transferred' : 'left',
                    'is_official' => true
                ]);
            }
        }

        // Virtus.pro Players (Former TeamCats)
        $teamVP = Team::where('name', 'Virtus.pro')->first();
        
        if ($teamVP) {
            $playersVP = [
                [
                    'username' => 'SparkR',
                    'real_name' => 'William Andersson',
                    'role' => 'Duelist',
                    'main_hero' => 'Hawkeye',
                    'country' => 'Sweden',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'TeamCats', 'period' => '2024-2025', 'role' => 'DPS'],
                        ['team' => 'Overwatch Contenders EU', 'period' => '2023-2024', 'role' => 'DPS']
                    ],
                    'rating' => 4850,
                    'social_media' => ['twitter' => 'https://twitter.com/sparkr_ow']
                ],
                [
                    'username' => 'phi',
                    'real_name' => 'Philip Handke',
                    'role' => 'Duelist',
                    'main_hero' => 'Scarlet Witch',
                    'country' => 'Germany',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'TeamCats', 'period' => '2024-2025', 'role' => 'DPS'],
                        ['team' => 'EU Teams', 'period' => '2022-2024', 'role' => 'DPS']
                    ],
                    'rating' => 4820,
                    'social_media' => ['twitter' => 'https://twitter.com/phi_ow']
                ],
                [
                    'username' => 'Sypeh',
                    'real_name' => 'Mikkel Klein',
                    'role' => 'Strategist',
                    'main_hero' => 'Loki',
                    'country' => 'Denmark',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'TeamCats', 'period' => '2024-2025', 'role' => 'Support'],
                        ['team' => 'Nordic Teams', 'period' => '2023-2024', 'role' => 'Support']
                    ],
                    'rating' => 4780,
                    'social_media' => ['twitter' => 'https://twitter.com/sypeh']
                ],
                [
                    'username' => 'dridro',
                    'real_name' => 'Arthur Szanto',
                    'role' => 'Strategist',
                    'main_hero' => 'Adam Warlock',
                    'country' => 'Hungary',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'TeamCats', 'period' => '2024-2025', 'role' => 'Support']
                    ],
                    'rating' => 4760,
                    'social_media' => ['twitter' => 'https://twitter.com/dridro_ow']
                ],
                [
                    'username' => 'Nevix',
                    'real_name' => 'Andreas Karlsson',
                    'role' => 'Vanguard',
                    'main_hero' => 'Thor',
                    'country' => 'Sweden',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'TeamCats', 'period' => '2024-2025', 'role' => 'Tank'],
                        ['team' => 'San Francisco Shock', 'period' => '2018-2019', 'role' => 'Flex'],
                        ['team' => 'Misfits', 'period' => '2016-2017', 'role' => 'DPS']
                    ],
                    'rating' => 4790,
                    'social_media' => ['twitter' => 'https://twitter.com/nevixow']
                ],
                [
                    'username' => 'Finnsi',
                    'real_name' => 'Finnbjörn Jónasson',
                    'role' => 'Vanguard',
                    'main_hero' => 'Doctor Strange',
                    'country' => 'Iceland',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'Overwatch Teams', 'period' => '2022-2024', 'role' => 'Tank']
                    ],
                    'rating' => 4810,
                    'social_media' => ['twitter' => 'https://twitter.com/finnsi_ow']
                ]
            ];

            foreach ($playersVP as $playerData) {
                $player = Player::create([
                    'username' => $playerData['username'],
                    'real_name' => $playerData['real_name'],
                    'role' => $playerData['role'],
                    'main_hero' => $playerData['main_hero'],
                    'country' => $playerData['country'],
                    'region' => $playerData['region'],
                    'team_id' => $teamVP->id,
                    'past_teams' => $playerData['past_teams'],
                    'rating' => $playerData['rating'],
                    'social_media' => $playerData['social_media'],
                    'status' => 'active'
                ]);

                PlayerTeamHistory::create([
                    'player_id' => $player->id,
                    'from_team_id' => null, // TeamCats
                    'to_team_id' => $teamVP->id,
                    'change_date' => Carbon::parse('2025-02-19'),
                    'change_type' => 'transferred',
                    'is_official' => true
                ]);
            }
        }

        // OG Players
        $teamOG = Team::where('name', 'OG')->first();
        
        if ($teamOG) {
            $playersOG = [
                [
                    'username' => 'Snayz',
                    'real_name' => 'Unknown',
                    'role' => 'Vanguard',
                    'main_hero' => 'Hulk',
                    'country' => 'France',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'EU Contenders', 'period' => '2023-2024', 'role' => 'Tank']
                    ],
                    'rating' => 4700
                ],
                [
                    'username' => 'Nzo',
                    'real_name' => 'Unknown',
                    'role' => 'Vanguard',
                    'main_hero' => 'Peni Parker',
                    'country' => 'France',
                    'region' => 'EU',
                    'past_teams' => [],
                    'rating' => 4680
                ],
                [
                    'username' => 'Etsu',
                    'real_name' => 'Théo Clement',
                    'role' => 'Duelist',
                    'main_hero' => 'Black Panther',
                    'country' => 'France',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'French Teams', 'period' => '2023-2024', 'role' => 'DPS']
                    ],
                    'rating' => 4720,
                    'social_media' => ['twitter' => 'https://twitter.com/etsu_ow']
                ],
                [
                    'username' => 'Tanuki',
                    'real_name' => 'Unknown',
                    'role' => 'Duelist',
                    'main_hero' => 'Winter Soldier',
                    'country' => 'Germany',
                    'region' => 'EU',
                    'past_teams' => [],
                    'rating' => 4710
                ],
                [
                    'username' => 'Alx',
                    'real_name' => 'Aleks Suchev',
                    'role' => 'Strategist',
                    'main_hero' => 'Cloak & Dagger',
                    'country' => 'Russia',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'CIS Teams', 'period' => '2022-2024', 'role' => 'Support']
                    ],
                    'rating' => 4690
                ],
                [
                    'username' => 'Ken',
                    'real_name' => 'Leander Aspestrand',
                    'role' => 'Strategist',
                    'main_hero' => 'Invisible Woman',
                    'country' => 'Norway',
                    'region' => 'EU',
                    'past_teams' => [
                        ['team' => 'Nordic Teams', 'period' => '2023-2024', 'role' => 'Support']
                    ],
                    'rating' => 4700
                ]
            ];

            foreach ($playersOG as $playerData) {
                $player = Player::create([
                    'username' => $playerData['username'],
                    'real_name' => $playerData['real_name'],
                    'role' => $playerData['role'],
                    'main_hero' => $playerData['main_hero'],
                    'country' => $playerData['country'],
                    'region' => $playerData['region'],
                    'team_id' => $teamOG->id,
                    'past_teams' => $playerData['past_teams'],
                    'rating' => $playerData['rating'],
                    'social_media' => $playerData['social_media'] ?? [],
                    'status' => 'active'
                ]);

                PlayerTeamHistory::create([
                    'player_id' => $player->id,
                    'from_team_id' => null,
                    'to_team_id' => $teamOG->id,
                    'change_date' => Carbon::parse('2025-02-01'),
                    'change_type' => 'joined',
                    'is_official' => true
                ]);
            }
        }

        echo "Players with histories seeded successfully!\n";
    }
}