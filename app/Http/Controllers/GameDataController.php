<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameDataController extends Controller
{
    public function getMaps(Request $request)
    {
        try {
            $competitive = $request->get('competitive', false);
            
            $maps = [
                // Domination Maps
                [
                    'id' => 1,
                    'name' => 'Hellfire Gala: Krakoa',
                    'mode' => 'Domination',
                    'competitive' => true,
                    'season' => 'Season 2',
                    'status' => 'active',
                    'description' => 'Best of 3 rounds, 30 seconds preparation before Mission Area unlock',
                    'image' => '/images/maps/hellfire-gala-krakoa.jpg'
                ],
                [
                    'id' => 2,
                    'name' => 'Yggsgard: Royal Palace',
                    'mode' => 'Domination',
                    'competitive' => false,
                    'season' => 'Launch',
                    'status' => 'removed',
                    'removal_season' => 'Season 2',
                    'description' => 'Removed from competitive rotation in Season 2',
                    'image' => '/images/maps/yggsgard-royal-palace.jpg'
                ],
                [
                    'id' => 3,
                    'name' => 'Hydra Charteris Base: Hell\'s Heaven',
                    'mode' => 'Domination',
                    'competitive' => true,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => 'Classic domination map with strategic control points',
                    'image' => '/images/maps/hydra-charteris-base.jpg'
                ],
                [
                    'id' => 4,
                    'name' => 'Intergalactic Empire of Wakanda: Birnin T\'Challa',
                    'mode' => 'Domination',
                    'competitive' => true,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => 'Wakandan technology meets strategic gameplay',
                    'image' => '/images/maps/wakanda-birnin-tchalla.jpg'
                ],
                
                // Convoy Maps
                [
                    'id' => 5,
                    'name' => 'Empire of Eternal Night: Central Park',
                    'mode' => 'Convoy',
                    'competitive' => true,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => '2 rounds in Competitive, teams swap attack/defend',
                    'image' => '/images/maps/central-park.jpg'
                ],
                [
                    'id' => 6,
                    'name' => 'Tokyo 2099: Spider-Islands',
                    'mode' => 'Convoy',
                    'competitive' => false,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => 'Futuristic Tokyo with web-slinging opportunities',
                    'image' => '/images/maps/tokyo-spider-islands.jpg'
                ],
                [
                    'id' => 7,
                    'name' => 'Yggsgard: Yggdrasill Path',
                    'mode' => 'Convoy',
                    'competitive' => false,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => 'Mystical Asgardian pathway with escort objectives',
                    'image' => '/images/maps/yggsgard-yggdrasill-path.jpg'
                ],
                [
                    'id' => 8,
                    'name' => 'Empire of Eternal Night: Midtown',
                    'mode' => 'Convoy',
                    'competitive' => false,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => 'Urban battlefield in the heart of the city',
                    'image' => '/images/maps/midtown.jpg'
                ],
                
                // Convergence Maps
                [
                    'id' => 9,
                    'name' => 'Empire of Eternal Night: Sanctum Sanctorum',
                    'mode' => 'Convergence',
                    'competitive' => false,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => 'Phase 1: Capture point, Phase 2: Escort payload',
                    'image' => '/images/maps/sanctum-sanctorum.jpg'
                ],
                [
                    'id' => 10,
                    'name' => 'Tokyo 2099: Shin-Shibuya',
                    'mode' => 'Convergence',
                    'competitive' => false,
                    'season' => 'Launch',
                    'status' => 'removed',
                    'removal_season' => 'Season 2',
                    'description' => 'Removed from rotation in Season 2',
                    'image' => '/images/maps/tokyo-shin-shibuya.jpg'
                ],
                [
                    'id' => 11,
                    'name' => 'Klyntar: Symbiotic Surface',
                    'mode' => 'Convergence',
                    'competitive' => false,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => 'Alien symbiote homeworld with unique mechanics',
                    'image' => '/images/maps/klyntar-symbiotic-surface.jpg'
                ],
                [
                    'id' => 12,
                    'name' => 'Intergalactic Empire of Wakanda: Hall of Djalia',
                    'mode' => 'Convergence',
                    'competitive' => false,
                    'season' => 'Launch',
                    'status' => 'active',
                    'description' => 'Sacred Wakandan halls with convergence objectives',
                    'image' => '/images/maps/wakanda-hall-djalia.jpg'
                ]
            ];

            // Filter maps based on request
            if ($competitive) {
                $maps = array_filter($maps, function($map) {
                    return $map['competitive'] === true && $map['status'] === 'active';
                });
            }

            return response()->json([
                'data' => array_values($maps),
                'summary' => [
                    'total_maps' => 15,
                    'competitive_maps' => 4,
                    'by_mode' => [
                        'Domination' => 4,
                        'Convoy' => 4,
                        'Convergence' => 4
                    ],
                    'season_2_changes' => [
                        'added' => ['Hellfire Gala: Krakoa'],
                        'removed' => ['Yggsgard: Royal Palace', 'Tokyo 2099: Shin-Shibuya']
                    ]
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching maps: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getGameModes()
    {
        return response()->json([
            'data' => [
                [
                    'name' => 'Domination',
                    'description' => 'Capture and hold control points',
                    'format' => 'Best of 3 rounds',
                    'preparation_time' => '30 seconds before Mission Area unlock',
                    'capture_rate' => '1% progress per 1.2 seconds when controlled',
                    'overtime' => 'Triggered when contested near round end',
                    'competitive' => true
                ],
                [
                    'name' => 'Convoy',
                    'description' => 'Escort payload through checkpoints',
                    'format' => '2 rounds in Competitive (teams swap attack/defend)',
                    'preparation_time' => '30 seconds defender preparation',
                    'checkpoints' => '2 checkpoints + final destination',
                    'payload_speed' => [
                        'base' => '1.0 m/s',
                        '1_attacker' => '1.67 m/s',
                        '2_attackers' => '1.93 m/s',
                        '3_attackers' => '2.26 m/s (maximum)'
                    ],
                    'competitive' => true
                ],
                [
                    'name' => 'Convergence',
                    'description' => 'Two-phase objective mode',
                    'phase_1' => 'Capture point (30 second defender prep)',
                    'phase_2' => 'Escort payload if Phase 1 successful',
                    'format' => '2 rounds in Competitive (teams swap sides)',
                    'minimum_progress' => '33% capture required to set benchmark',
                    'competitive' => false
                ]
            ],
            'success' => true
        ]);
    }

    public function getHeroRoster()
    {
        return response()->json([
            'data' => [
                'vanguards' => [
                    'count' => 12,
                    'heroes' => [
                        'Captain America', 'Doctor Strange', 'Groot', 'Hulk', 
                        'Magneto', 'Peni Parker', 'Thor', 'Venom',
                        'Emma Frost', 'Bruce Banner', 'Mr. Fantastic'
                    ],
                    'new_season_2' => ['Emma Frost'],
                    'role_description' => 'Tank heroes who protect the team and control space'
                ],
                'duelists' => [
                    'count' => 19,
                    'heroes' => [
                        'Black Panther', 'Black Widow', 'Hawkeye', 'Hela', 'Iron Man',
                        'Magik', 'Moon Knight', 'Namor', 'Psylocke', 'Punisher',
                        'Scarlet Witch', 'Spider-Man', 'Star-Lord', 'Storm', 'Wolverine',
                        'Winter Soldier', 'Iron Fist', 'Squirrel Girl'
                    ],
                    'role_description' => 'Damage dealers who eliminate enemies'
                ],
                'strategists' => [
                    'count' => 8,
                    'heroes' => [
                        'Adam Warlock', 'Cloak & Dagger', 'Jeff the Land Shark',
                        'Loki', 'Luna Snow', 'Mantis', 'Rocket Raccoon'
                    ],
                    'role_description' => 'Support heroes who heal and buff allies'
                ]
            ],
            'summary' => [
                'total_heroes' => 39,
                'season_2_additions' => 1,
                'optimal_composition' => '2-2-2 (2 Vanguard, 2 Duelist, 2 Strategist)',
                'current_meta' => 'Three-healer strategy popular',
                'win_rate_bonus' => '2-2-2 composition has 33% higher win rate'
            ],
            'success' => true
        ]);
    }

    public function getRankingInfo()
    {
        return response()->json([
            'data' => [
                'rank_structure' => [
                    'total_ranks' => 23,
                    'ranks' => [
                        ['name' => 'Bronze', 'divisions' => 3, 'range' => '0-400'],
                        ['name' => 'Silver', 'divisions' => 3, 'range' => '400-700'],
                        ['name' => 'Gold', 'divisions' => 3, 'range' => '700-1000'],
                        ['name' => 'Platinum', 'divisions' => 3, 'range' => '1000-1900'],
                        ['name' => 'Diamond', 'divisions' => 3, 'range' => '1900-2800'],
                        ['name' => 'Grandmaster', 'divisions' => 3, 'range' => '2800-3700'],
                        ['name' => 'Celestial', 'divisions' => 3, 'range' => '3700-4600'],
                        ['name' => 'Eternity', 'divisions' => 0, 'range' => '4600-5000', 'note' => 'Point-based'],
                        ['name' => 'One Above All', 'divisions' => 0, 'range' => '5000+', 'note' => 'Top 500 players only']
                    ]
                ],
                'point_system' => [
                    'points_per_division' => 100,
                    'total_points_per_rank' => 300,
                    'starting_rank' => 'Bronze III',
                    'minimum_level' => 15
                ],
                'key_thresholds' => [
                    'hero_bans' => 'Gold III+ (700+ rating)',
                    'pick_ban' => 'Diamond III+ (was Diamond III, now Gold III in Season 2)',
                    'chrono_shield' => 'Gold rank and below',
                    'rank_decay' => 'Eternity and One Above All only'
                ],
                'season_reset' => [
                    'amount' => '9 divisions down',
                    'examples' => [
                        'Diamond I → Silver I',
                        'Gold III → Bronze III'
                    ]
                ],
                'team_restrictions' => [
                    'gold_and_below' => 'Can team with anyone',
                    'gold_to_celestial' => 'Within 3 divisions',
                    'eternity_oaa' => 'Solo/Duo only, Celestial II+ within 200 points'
                ]
            ],
            'success' => true
        ]);
    }

    public function getTournamentFormats()
    {
        return response()->json([
            'data' => [
                'official_tournaments' => [
                    'marvel_rivals_championship' => [
                        'name' => 'Marvel Rivals Championship',
                        'structure' => [
                            'open_qualifiers' => 'BO1 format',
                            'closed_qualifiers' => 'BO1 format',
                            'double_elimination_rounds_1_9' => 'BO3',
                            'double_elimination_rounds_10_14' => 'BO5',
                            'lower_bracket_finals' => 'BO7',
                            'grand_finals' => 'BO7'
                        ]
                    ],
                    'marvel_rivals_invitational' => [
                        'name' => 'Marvel Rivals Invitational',
                        'structure' => [
                            'upper_bracket' => 'BO3',
                            'lower_bracket' => 'BO5',
                            'grand_finals' => 'BO7'
                        ]
                    ],
                    'ignite_2025_series' => [
                        'name' => 'Ignite 2025 Series',
                        'structure' => [
                            'open_qualifiers' => 'BO1',
                            'group_stage' => 'BO3',
                            'playoffs' => 'BO5',
                            'finals' => 'BO7'
                        ],
                        'prize_pool' => '$3,050,000 total'
                    ]
                ],
                'custom_formats' => ['BO1', 'BO3', 'BO5', 'BO7', 'BO9'],
                'prize_pools' => [
                    'ignite_2025_total' => '$3,050,000',
                    'global_finals' => '$1,000,000',
                    'mid_season_finals' => '$500,000',
                    'regional_championships' => '$14,500 per region'
                ]
            ],
            'success' => true
        ]);
    }

    public function getMatchTimers()
    {
        return response()->json([
            'data' => [
                'game_duration' => [
                    'average_match' => '8-12 minutes',
                    'with_hero_selection' => '12-20 minutes total',
                    'overtime_scenarios' => 'Can extend to 30+ minutes',
                    'competitive_bo5' => '40-60 minutes total'
                ],
                'preparation_times' => [
                    'hero_selection' => '~60 seconds',
                    'defender_setup' => '30 seconds (Convoy/Convergence)',
                    'mission_area_unlock' => '30 seconds (Domination)',
                    'between_rounds' => '~30 seconds'
                ],
                'mode_specific' => [
                    'domination' => [
                        'mission_area_unlock' => '30 seconds preparation',
                        'capture_rate' => '1% progress per 1.2 seconds',
                        'total_capture_time' => '120 seconds (100% × 1.2s)',
                        'format' => 'Best of 3 rounds',
                        'overtime' => 'Triggered at 99% if contested'
                    ],
                    'convoy' => [
                        'defender_setup' => '30 seconds preparation',
                        'checkpoint_1_bonus' => '+1 minute 30 seconds',
                        'checkpoint_2_bonus' => '+1 minute 30 seconds',
                        'overtime_bonus' => 'Team with more time gets +2 minutes, less time gets +1 minute'
                    ],
                    'convergence' => [
                        'defender_setup' => '30-40 seconds preparation (map dependent)',
                        'phase_1' => 'Capture Mission Area (33%, 66%, 100% checkpoints)',
                        'phase_2' => 'Escort payload (1 checkpoint + finish)',
                        'checkpoint_bonus' => '+1 minute 30 seconds',
                        'minimum_progress' => '33% capture required to set benchmark'
                    ]
                ],
                'vehicle_speeds' => [
                    'base_speed' => '1.0 m/s (automatic movement)',
                    '1_attacker' => '1.67 m/s',
                    '2_attackers' => '1.93 m/s',
                    '3_attackers' => '2.26 m/s (maximum speed)'
                ]
            ],
            'success' => true
        ]);
    }

    public function getCurrentMeta()
    {
        return response()->json([
            'data' => [
                'season' => 'Season 2.5',
                'last_updated' => 'July 2025',
                'top_picks' => [
                    'vanguard' => ['Emma Frost', 'Doctor Strange', 'Venom'],
                    'duelist' => ['Hela', 'Hawkeye', 'Spider-Man'],
                    'strategist' => ['Luna Snow', 'Mantis', 'Cloak & Dagger']
                ],
                'team_composition' => [
                    'standard' => '2-2-2 (2 Vanguard, 2 Duelist, 2 Strategist)',
                    'current_meta' => 'Three-healer strategy popular',
                    'win_rate_boost' => '2-2-2 composition has 33% higher win rate'
                ],
                'competitive_features' => [
                    'hero_bans' => [
                        'activation' => 'Gold III and above',
                        'format' => '2 heroes banned per team simultaneously',
                        'selection' => 'Random from submitted bans'
                    ],
                    'team_up_abilities' => [
                        'total_active' => 20,
                        'examples' => [
                            'Ragnarok Rebirth (Hela/Thor/Loki)',
                            'Gamma Charge (Hulk/Doctor Strange/Iron Man)'
                        ]
                    ]
                ],
                'season_2_rewards' => [
                    'gold_3_plus' => 'Golden Ultron skin',
                    'grandmaster_3_plus' => 'Grandmaster Crest of Honor',
                    'celestial_3_plus' => 'Celestial Crest of Honor',
                    'eternity_plus' => 'Eternity & One Above All Crest of Honor'
                ]
            ],
            'success' => true
        ]);
    }

    public function getTechnicalSpecs()
    {
        return response()->json([
            'data' => [
                'platforms' => [
                    'pc' => ['Steam', 'Epic Games'],
                    'console' => ['PlayStation 5', 'Xbox Series X/S'],
                    'cross_play' => 'Casual modes only',
                    'competitive' => 'Platform-restricted (PC vs PC, Console vs Console)'
                ],
                'anti_cheat' => [
                    'system' => 'Proprietary anti-cheat',
                    'detection_time' => 'Average 7 matches',
                    'penalties' => 'Permanent bans for major violations'
                ],
                'server_regions' => [
                    'americas' => 'UTC-5',
                    'emea' => 'UTC+2',
                    'asia' => 'UTC+9',
                    'china' => 'Separate region',
                    'oceania' => 'UTC+10'
                ],
                'arcade_modes' => [
                    'doom_match' => [
                        'goal' => '16 eliminations or highest score when timer expires',
                        'players' => '8-12 players (FFA)',
                        'winners' => 'Top 50% of players'
                    ],
                    'conquest' => [
                        'goal' => '50 points or highest score when timer expires',
                        'point_collection' => 'Chromovium drops from eliminations',
                        'tiebreaker' => 'Additional sudden-death round for 1 point'
                    ]
                ]
            ],
            'success' => true
        ]);
    }

    public function getCompleteGameData()
    {
        return response()->json([
            'data' => [
                'version' => 'Season 2.5',
                'last_updated' => '2025-07-13',
                'maps' => $this->getMaps(new Request())->getData()->data,
                'heroes' => $this->getHeroRoster()->getData()->data,
                'ranking' => $this->getRankingInfo()->getData()->data,
                'tournaments' => $this->getTournamentFormats()->getData()->data,
                'meta' => $this->getCurrentMeta()->getData()->data,
                'technical' => $this->getTechnicalSpecs()->getData()->data
            ],
            'success' => true
        ]);
    }
}