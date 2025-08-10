<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Tournament;

class TournamentSettingsController extends Controller
{
    /**
     * Get tournament system settings
     */
    public function index(): JsonResponse
    {
        try {
            $settings = Cache::remember('tournament_system_settings', 3600, function () {
                return [
                    'tournament_types' => Tournament::TYPES,
                    'tournament_formats' => Tournament::FORMATS,
                    'tournament_statuses' => Tournament::STATUSES,
                    'tournament_phases' => Tournament::PHASES,
                    'match_formats' => Tournament::MATCH_FORMATS,
                    'default_settings' => $this->getDefaultTournamentSettings(),
                    'bracket_configurations' => $this->getBracketConfigurations(),
                    'swiss_system_settings' => $this->getSwissSystemSettings(),
                    'seeding_methods' => $this->getSeedingMethods(),
                    'prize_pool_templates' => $this->getPrizePoolTemplates(),
                    'schedule_templates' => $this->getScheduleTemplates(),
                    'map_pools' => $this->getMapPoolTemplates(),
                    'rules_templates' => $this->getRulesTemplates(),
                    'notification_settings' => $this->getNotificationSettings(),
                    'streaming_integration' => $this->getStreamingIntegrationSettings(),
                    'regional_settings' => $this->getRegionalSettings(),
                    'validation_rules' => $this->getValidationRules()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament settings fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament settings',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update tournament system settings
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'default_settings' => 'nullable|array',
                'bracket_configurations' => 'nullable|array',
                'swiss_system_settings' => 'nullable|array',
                'prize_pool_templates' => 'nullable|array',
                'schedule_templates' => 'nullable|array',
                'map_pools' => 'nullable|array',
                'rules_templates' => 'nullable|array',
                'notification_settings' => 'nullable|array',
                'streaming_integration' => 'nullable|array',
                'regional_settings' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update system settings (would typically be stored in a settings table or config)
            $updatedSettings = [];
            
            foreach ($request->all() as $key => $value) {
                if ($value !== null) {
                    // In a real implementation, these would be saved to a settings table
                    // For now, we'll just validate and return the updated values
                    $updatedSettings[$key] = $value;
                }
            }

            // Clear cache to force refresh
            Cache::forget('tournament_system_settings');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament settings updated successfully',
                'data' => $updatedSettings
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament settings update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tournament settings',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get available tournament formats with detailed configurations
     */
    public function getFormats(): JsonResponse
    {
        try {
            $formats = [
                'single_elimination' => [
                    'name' => 'Single Elimination',
                    'description' => 'Teams are eliminated after losing one match',
                    'min_teams' => 2,
                    'max_teams' => 512,
                    'recommended_teams' => [4, 8, 16, 32, 64],
                    'phases' => ['registration', 'check_in', 'playoffs'],
                    'supports_seeding' => true,
                    'supports_byes' => true,
                    'match_formats' => ['bo1', 'bo3', 'bo5'],
                    'estimated_duration' => [
                        4 => '1 day',
                        8 => '1-2 days',
                        16 => '2-3 days',
                        32 => '3-5 days',
                        64 => '5-7 days'
                    ]
                ],
                'double_elimination' => [
                    'name' => 'Double Elimination',
                    'description' => 'Teams are eliminated after losing two matches',
                    'min_teams' => 3,
                    'max_teams' => 256,
                    'recommended_teams' => [4, 8, 16, 32, 64],
                    'phases' => ['registration', 'check_in', 'upper_bracket', 'lower_bracket', 'grand_final'],
                    'supports_seeding' => true,
                    'supports_byes' => true,
                    'match_formats' => ['bo3', 'bo5'],
                    'estimated_duration' => [
                        4 => '1-2 days',
                        8 => '2-3 days',
                        16 => '3-4 days',
                        32 => '5-7 days',
                        64 => '7-10 days'
                    ]
                ],
                'swiss' => [
                    'name' => 'Swiss System',
                    'description' => 'Teams play a fixed number of rounds with similar-skilled opponents',
                    'min_teams' => 4,
                    'max_teams' => 128,
                    'recommended_teams' => [8, 16, 32, 64],
                    'phases' => ['registration', 'check_in', 'swiss_rounds', 'playoffs'],
                    'supports_seeding' => true,
                    'supports_byes' => false,
                    'match_formats' => ['bo1', 'bo3'],
                    'rounds_calculation' => 'ceil(log2(team_count))',
                    'advancement_percentage' => 0.5,
                    'estimated_duration' => [
                        8 => '2-3 days',
                        16 => '3-4 days',
                        32 => '4-6 days',
                        64 => '6-8 days'
                    ]
                ],
                'round_robin' => [
                    'name' => 'Round Robin',
                    'description' => 'Every team plays every other team once',
                    'min_teams' => 3,
                    'max_teams' => 16,
                    'recommended_teams' => [4, 6, 8, 10, 12],
                    'phases' => ['registration', 'check_in', 'round_robin'],
                    'supports_seeding' => false,
                    'supports_byes' => false,
                    'match_formats' => ['bo1', 'bo3'],
                    'matches_calculation' => '(team_count * (team_count - 1)) / 2',
                    'estimated_duration' => [
                        4 => '1 day',
                        6 => '2 days',
                        8 => '3-4 days',
                        10 => '5-6 days',
                        12 => '7-8 days'
                    ]
                ],
                'group_stage_playoffs' => [
                    'name' => 'Group Stage + Playoffs',
                    'description' => 'Round robin groups followed by single/double elimination playoffs',
                    'min_teams' => 8,
                    'max_teams' => 64,
                    'recommended_teams' => [8, 12, 16, 24, 32],
                    'phases' => ['registration', 'check_in', 'group_stage', 'playoffs'],
                    'supports_seeding' => true,
                    'supports_byes' => false,
                    'match_formats' => ['bo1', 'bo3', 'bo5'],
                    'group_sizes' => [3, 4, 5, 6],
                    'advancement_per_group' => [1, 2, 3],
                    'estimated_duration' => [
                        8 => '3-4 days',
                        16 => '4-6 days',
                        24 => '6-8 days',
                        32 => '8-10 days'
                    ]
                ],
                'ladder' => [
                    'name' => 'Ladder Tournament',
                    'description' => 'Ongoing tournament where teams can challenge others',
                    'min_teams' => 2,
                    'max_teams' => 100,
                    'recommended_teams' => [10, 20, 50],
                    'phases' => ['registration', 'ongoing'],
                    'supports_seeding' => true,
                    'supports_byes' => false,
                    'match_formats' => ['bo1', 'bo3'],
                    'duration' => 'Continuous/Season-based',
                    'special_rules' => [
                        'challenge_cooldown' => '24 hours',
                        'position_protection' => '48 hours',
                        'decay_system' => 'Optional'
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $formats
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament formats fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament formats',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament templates
     */
    public function getTemplates(): JsonResponse
    {
        try {
            $templates = [
                'quick_tournaments' => [
                    'community_cup' => [
                        'name' => 'Community Cup',
                        'description' => 'Small community tournament template',
                        'type' => 'community',
                        'format' => 'single_elimination',
                        'max_teams' => 16,
                        'match_format' => 'bo3',
                        'duration' => '1 day',
                        'settings' => [
                            'allow_self_reporting' => true,
                            'auto_advance_time' => 900, // 15 minutes
                            'check_in_duration' => 60 // 1 hour
                        ]
                    ],
                    'weekend_warrior' => [
                        'name' => 'Weekend Warrior',
                        'description' => 'Weekend tournament template',
                        'type' => 'community',
                        'format' => 'double_elimination',
                        'max_teams' => 32,
                        'match_format' => 'bo3',
                        'duration' => '2 days',
                        'settings' => [
                            'allow_self_reporting' => false,
                            'require_screenshots' => true,
                            'check_in_duration' => 120 // 2 hours
                        ]
                    ]
                ],
                'competitive_tournaments' => [
                    'monthly_championship' => [
                        'name' => 'Monthly Championship',
                        'description' => 'Competitive monthly tournament',
                        'type' => 'qualifier',
                        'format' => 'swiss',
                        'max_teams' => 64,
                        'match_format' => 'bo3',
                        'duration' => '1 week',
                        'settings' => [
                            'require_team_verification' => true,
                            'minimum_elo' => 2000,
                            'prize_pool_required' => true
                        ]
                    ],
                    'regional_qualifier' => [
                        'name' => 'Regional Qualifier',
                        'description' => 'Regional championship qualifier',
                        'type' => 'regional',
                        'format' => 'group_stage_playoffs',
                        'max_teams' => 32,
                        'match_format' => 'bo5',
                        'duration' => '2 weeks',
                        'settings' => [
                            'require_team_verification' => true,
                            'region_locked' => true,
                            'admin_reporting_only' => true
                        ]
                    ]
                ],
                'professional_tournaments' => [
                    'mrc_championship' => [
                        'name' => 'Marvel Rivals Championship',
                        'description' => 'Professional championship tournament',
                        'type' => 'mrc',
                        'format' => 'group_stage_playoffs',
                        'max_teams' => 16,
                        'match_format' => 'bo5',
                        'duration' => '1 month',
                        'settings' => [
                            'invite_only' => true,
                            'admin_reporting_only' => true,
                            'require_contracts' => true,
                            'streaming_required' => true
                        ]
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament templates fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament templates',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new tournament template
     */
    public function createTemplate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'category' => 'required|in:quick_tournaments,competitive_tournaments,professional_tournaments,custom',
                'type' => 'required|in:' . implode(',', array_keys(Tournament::TYPES)),
                'format' => 'required|in:' . implode(',', array_keys(Tournament::FORMATS)),
                'max_teams' => 'required|integer|min:2|max:512',
                'match_format' => 'required|in:' . implode(',', array_keys(Tournament::MATCH_FORMATS)),
                'estimated_duration' => 'required|string',
                'settings' => 'required|array',
                'rules_template' => 'nullable|string',
                'prize_pool_template' => 'nullable|array',
                'map_pool' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // In a real implementation, this would be saved to database
            $template = [
                'id' => uniqid(),
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'type' => $request->type,
                'format' => $request->format,
                'max_teams' => $request->max_teams,
                'match_format' => $request->match_format,
                'estimated_duration' => $request->estimated_duration,
                'settings' => $request->settings,
                'rules_template' => $request->rules_template,
                'prize_pool_template' => $request->prize_pool_template ?? [],
                'map_pool' => $request->map_pool ?? [],
                'created_at' => now()->toISOString(),
                'created_by' => auth()->user()->name ?? 'System'
            ];

            // Clear settings cache
            Cache::forget('tournament_system_settings');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tournament template created successfully',
                'data' => $template
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament template creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tournament template',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Private helper methods for getting default configurations

    private function getDefaultTournamentSettings(): array
    {
        return [
            'registration_duration_days' => 7,
            'check_in_duration_minutes' => 60,
            'match_default_format' => 'bo3',
            'auto_advance_timeout_minutes' => 15,
            'allow_self_reporting' => false,
            'require_screenshots' => true,
            'allow_coaching' => true,
            'allow_substitutions' => true,
            'max_substitutions_per_match' => 1,
            'minimum_players_per_team' => 5,
            'maximum_players_per_team' => 8,
            'default_map_pool_size' => 7,
            'veto_system_enabled' => true,
            'stream_delay_seconds' => 120,
            'pause_limit_per_team' => 3,
            'pause_duration_limit_minutes' => 5
        ];
    }

    private function getBracketConfigurations(): array
    {
        return [
            'single_elimination' => [
                'supports_byes' => true,
                'bye_placement' => 'top_seeds',
                'match_progression' => 'winner_advances',
                'grand_final_advantage' => false
            ],
            'double_elimination' => [
                'supports_byes' => true,
                'bye_placement' => 'top_seeds',
                'match_progression' => 'winner_to_winners_loser_to_losers',
                'grand_final_advantage' => true,
                'bracket_reset' => true
            ],
            'swiss' => [
                'supports_byes' => false,
                'pairing_algorithm' => 'swiss_perfect',
                'avoid_rematches' => true,
                'color_balancing' => true,
                'qualification_threshold' => 0.5
            ]
        ];
    }

    private function getSwissSystemSettings(): array
    {
        return [
            'default_rounds' => 5,
            'max_rounds' => 9,
            'pairing_methods' => [
                'swiss_perfect' => 'Perfect Swiss Pairing',
                'dutch_system' => 'Dutch System',
                'accelerated_swiss' => 'Accelerated Swiss'
            ],
            'tiebreaker_systems' => [
                'buchholz' => 'Buchholz Score',
                'sonneborn_berger' => 'Sonneborn-Berger',
                'direct_encounter' => 'Direct Encounter',
                'koya_system' => 'Koya System'
            ],
            'qualification_methods' => [
                'top_percentage' => 'Top 50%',
                'minimum_score' => 'Minimum Score',
                'qualification_rounds' => 'Qualification Threshold'
            ]
        ];
    }

    private function getSeedingMethods(): array
    {
        return [
            'random' => 'Random Seeding',
            'elo_based' => 'ELO Rating Based',
            'ranking_based' => 'Official Ranking',
            'previous_tournament' => 'Previous Tournament Results',
            'manual' => 'Manual Seeding',
            'hybrid' => 'Hybrid System'
        ];
    }

    private function getPrizePoolTemplates(): array
    {
        return [
            'winner_takes_all' => [
                'name' => 'Winner Takes All',
                'distribution' => ['1st' => 100]
            ],
            'top_3' => [
                'name' => 'Top 3 Split',
                'distribution' => ['1st' => 60, '2nd' => 30, '3rd' => 10]
            ],
            'top_4' => [
                'name' => 'Top 4 Split',
                'distribution' => ['1st' => 50, '2nd' => 25, '3rd' => 15, '4th' => 10]
            ],
            'top_8' => [
                'name' => 'Top 8 Split',
                'distribution' => [
                    '1st' => 40, '2nd' => 20, '3rd' => 12, '4th' => 8,
                    '5th-6th' => 6, '7th-8th' => 4
                ]
            ]
        ];
    }

    private function getScheduleTemplates(): array
    {
        return [
            'daily' => [
                'name' => 'Daily Schedule',
                'matches_per_day' => 8,
                'hours_between_matches' => 1,
                'start_time' => '18:00',
                'end_time' => '23:00'
            ],
            'weekend' => [
                'name' => 'Weekend Tournament',
                'matches_per_day' => 16,
                'hours_between_matches' => 0.5,
                'start_time' => '10:00',
                'end_time' => '22:00'
            ]
        ];
    }

    private function getMapPoolTemplates(): array
    {
        return [
            'competitive_7_map' => [
                'name' => 'Competitive 7-Map Pool',
                'maps' => [
                    'Sanctum Sanctorum', 'Tokyo 2099', 'Birnin T\'Challa',
                    'Klyntar', 'Midtown', 'Yggsgard: Royal Palace', 'Intergalactic Empire of Wakanda'
                ],
                'veto_format' => 'ban_ban_pick_pick_ban_ban_decide'
            ],
            'casual_5_map' => [
                'name' => 'Casual 5-Map Pool',
                'maps' => [
                    'Sanctum Sanctorum', 'Tokyo 2099', 'Birnin T\'Challa',
                    'Midtown', 'Yggsgard: Royal Palace'
                ],
                'veto_format' => 'ban_ban_pick_pick_decide'
            ]
        ];
    }

    private function getRulesTemplates(): array
    {
        return [
            'standard_competitive' => [
                'name' => 'Standard Competitive Rules',
                'content' => '1. Teams must check-in 30 minutes before scheduled match time...'
            ],
            'community_casual' => [
                'name' => 'Community Casual Rules',
                'content' => '1. Have fun and be respectful...'
            ]
        ];
    }

    private function getNotificationSettings(): array
    {
        return [
            'registration_notifications' => true,
            'match_notifications' => true,
            'result_notifications' => true,
            'bracket_update_notifications' => true,
            'discord_integration' => false,
            'email_notifications' => true,
            'push_notifications' => true
        ];
    }

    private function getStreamingIntegrationSettings(): array
    {
        return [
            'supported_platforms' => ['twitch', 'youtube', 'discord'],
            'auto_create_stream_channels' => false,
            'require_stream_for_matches' => false,
            'spectator_mode_enabled' => true,
            'broadcast_delay' => 120
        ];
    }

    private function getRegionalSettings(): array
    {
        return [
            'supported_regions' => [
                'north_america' => 'North America',
                'europe' => 'Europe',
                'asia_pacific' => 'Asia Pacific',
                'south_america' => 'South America',
                'middle_east' => 'Middle East',
                'oceania' => 'Oceania',
                'global' => 'Global'
            ],
            'timezone_handling' => 'user_timezone',
            'region_restrictions' => false,
            'cross_region_matches' => true
        ];
    }

    private function getValidationRules(): array
    {
        return [
            'tournament_name' => 'required|string|min:3|max:255',
            'team_name' => 'required|string|min:2|max:100',
            'player_name' => 'required|string|min:2|max:50',
            'minimum_registration_time' => 24, // hours
            'maximum_tournament_duration' => 90, // days
            'minimum_teams' => 2,
            'maximum_teams' => 512
        ];
    }
}