<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Tournament;
use App\Models\BracketMatch;
use App\Services\TournamentLiveUpdateService;
use App\Services\SwissSystemService;

class TournamentLiveController extends Controller
{
    protected $liveUpdateService;
    protected $swissService;

    public function __construct(
        TournamentLiveUpdateService $liveUpdateService,
        SwissSystemService $swissService
    ) {
        $this->liveUpdateService = $liveUpdateService;
        $this->swissService = $swissService;
    }

    /**
     * Get all live tournaments data
     */
    public function getLiveTournaments(): JsonResponse
    {
        try {
            $data = $this->liveUpdateService->getLiveTournamentData();
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'broadcast_channels' => [
                    'tournaments.live',
                    'matches.live'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Live tournaments fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live tournaments',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get live matches across all tournaments
     */
    public function getLiveMatches(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 20), 100);
            
            $matches = BracketMatch::whereIn('status', ['ongoing', 'pending'])
                ->with([
                    'tournament:id,name,slug,type,format,region',
                    'team1:id,name,short_name,logo,region',
                    'team2:id,name,short_name,logo,region',
                    'tournamentPhase:id,name,phase_type,match_format',
                    'tournamentBracket:id,name,bracket_type'
                ])
                ->when($request->has('tournament_type'), function($query) use ($request) {
                    $query->whereHas('tournament', function($q) use ($request) {
                        $q->where('type', $request->tournament_type);
                    });
                })
                ->when($request->has('region'), function($query) use ($request) {
                    $query->whereHas('tournament', function($q) use ($request) {
                        $q->where('region', $request->region);
                    });
                })
                ->orderBy('scheduled_at')
                ->limit($limit)
                ->get();

            $transformedMatches = $matches->map(function($match) {
                return [
                    'id' => $match->id,
                    'match_identifier' => $match->match_identifier,
                    'tournament' => $match->tournament,
                    'phase' => $match->tournamentPhase,
                    'bracket' => $match->tournamentBracket,
                    'team1' => $match->team1,
                    'team2' => $match->team2,
                    'team1_score' => $match->team1_score,
                    'team2_score' => $match->team2_score,
                    'status' => $match->status,
                    'round' => $match->round,
                    'match_number' => $match->match_number,
                    'match_format' => $match->match_format,
                    'scheduled_at' => $match->scheduled_at?->toISOString(),
                    'started_at' => $match->started_at?->toISOString(),
                    'stream_url' => $match->stream_url,
                    'is_featured' => $match->tournament->featured || $match->tournament->type === 'mrc',
                    'broadcast_channel' => "match.{$match->id}"
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $transformedMatches,
                    'total_count' => $matches->count(),
                    'last_updated' => now()->toISOString()
                ],
                'broadcast_info' => [
                    'channels' => [
                        'matches.live',
                        'tournaments.live'
                    ],
                    'events' => [
                        'tournament.match.updated',
                        'tournament.match.started',
                        'tournament.match.completed'
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Live matches fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live matches',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get real-time tournament statistics
     */
    public function getTournamentLiveStats(Tournament $tournament): JsonResponse
    {
        try {
            $cacheKey = "tournament_{$tournament->id}_live_stats";
            
            $stats = Cache::remember($cacheKey, 60, function() use ($tournament) {
                return [
                    'tournament_info' => [
                        'id' => $tournament->id,
                        'name' => $tournament->name,
                        'status' => $tournament->status,
                        'current_phase' => $tournament->current_phase,
                        'progress_percentage' => $tournament->getProgressPercentage(),
                        'team_count' => $tournament->current_team_count,
                        'format' => $tournament->format,
                        'type' => $tournament->type
                    ],
                    'match_stats' => [
                        'total_matches' => $tournament->matches()->count(),
                        'completed_matches' => $tournament->matches()->where('status', 'completed')->count(),
                        'ongoing_matches' => $tournament->matches()->where('status', 'ongoing')->count(),
                        'pending_matches' => $tournament->matches()->where('status', 'pending')->count()
                    ],
                    'phase_info' => $tournament->phases()->where('is_active', true)->first(),
                    'registration_stats' => $tournament->registrations()->selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = "checked_in" THEN 1 ELSE 0 END) as checked_in,
                        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending
                    ')->first(),
                    'swiss_stats' => $tournament->format === 'swiss' ? 
                        $this->swissService->getSwissStatistics($tournament) : null,
                    'last_updated' => now()->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'broadcast_channel' => "tournament.{$tournament->id}",
                'refresh_interval' => 30 // seconds
            ]);
            
        } catch (\Exception $e) {
            Log::error('Tournament live stats error: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament statistics',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get live Swiss standings with real-time updates
     */
    public function getLiveSwissStandings(Tournament $tournament): JsonResponse
    {
        try {
            if ($tournament->format !== 'swiss') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tournament is not using Swiss format'
                ], 422);
            }

            $standings = $tournament->swiss_standings->map(function($team) {
                return [
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'short_name' => $team->short_name,
                        'logo' => $team->logo,
                        'region' => $team->region
                    ],
                    'wins' => $team->pivot->swiss_wins,
                    'losses' => $team->pivot->swiss_losses,
                    'score' => $team->pivot->swiss_score,
                    'buchholz' => $team->pivot->swiss_buchholz,
                    'status' => $team->pivot->status,
                    'seed' => $team->pivot->seed
                ];
            });

            $swissStats = $this->swissService->getSwissStatistics($tournament);

            return response()->json([
                'success' => true,
                'data' => [
                    'standings' => $standings,
                    'swiss_stats' => $swissStats,
                    'qualification_info' => [
                        'wins_to_qualify' => $tournament->qualification_settings['swiss_wins_required'] ?? 3,
                        'losses_to_eliminate' => $tournament->qualification_settings['swiss_losses_eliminated'] ?? 3,
                        'qualified_count' => $standings->where('status', 'qualified')->count(),
                        'eliminated_count' => $standings->where('status', 'eliminated')->count()
                    ],
                    'last_updated' => now()->toISOString()
                ],
                'broadcast_channel' => "tournament.{$tournament->id}.swiss",
                'events' => [
                    'swiss_round_generated',
                    'swiss_match_completed',
                    'swiss_standings_updated'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Live Swiss standings error: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Swiss standings',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament bracket with live updates
     */
    public function getLiveBracket(Tournament $tournament): JsonResponse
    {
        try {
            $brackets = $tournament->brackets()
                ->with([
                    'matches' => function($query) {
                        $query->with(['team1:id,name,short_name,logo', 'team2:id,name,short_name,logo'])
                              ->orderBy('round')
                              ->orderBy('match_number');
                    }
                ])
                ->ordered()
                ->get();

            $bracketData = $brackets->map(function($bracket) {
                return [
                    'id' => $bracket->id,
                    'name' => $bracket->name,
                    'bracket_type' => $bracket->bracket_type,
                    'bracket_format' => $bracket->bracket_format,
                    'status' => $bracket->status,
                    'round_count' => $bracket->round_count,
                    'current_round' => $bracket->current_round,
                    'progress_percentage' => $bracket->progress_percentage,
                    'team_count' => $bracket->team_count,
                    'matches' => $bracket->matches->map(function($match) {
                        return [
                            'id' => $match->id,
                            'identifier' => $match->match_identifier,
                            'round' => $match->round,
                            'match_number' => $match->match_number,
                            'status' => $match->status,
                            'team1' => $match->team1,
                            'team2' => $match->team2,
                            'team1_score' => $match->team1_score,
                            'team2_score' => $match->team2_score,
                            'match_format' => $match->match_format,
                            'scheduled_at' => $match->scheduled_at?->toISOString(),
                            'completed_at' => $match->completed_at?->toISOString(),
                            'is_walkover' => $match->is_walkover,
                            'stream_url' => $match->stream_url,
                            'broadcast_channel' => "match.{$match->id}"
                        ];
                    }),
                    'structure' => $bracket->bracket_data
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tournament' => [
                        'id' => $tournament->id,
                        'name' => $tournament->name,
                        'format' => $tournament->format,
                        'current_phase' => $tournament->current_phase
                    ],
                    'brackets' => $bracketData,
                    'visualization_config' => [
                        'show_seeds' => true,
                        'show_scores' => true,
                        'show_logos' => true,
                        'theme' => 'dark',
                        'real_time' => true
                    ],
                    'last_updated' => now()->toISOString()
                ],
                'broadcast_channels' => [
                    "tournament.{$tournament->id}",
                    "tournament.{$tournament->id}.bracket"
                ],
                'events' => [
                    'tournament.match.updated',
                    'tournament.bracket.updated',
                    'tournament.phase.changed'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Live bracket fetch error: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live bracket',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Subscribe to tournament live updates
     */
    public function subscribe(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $userId = auth()->id();
            $subscriptionType = $request->get('type', 'general'); // general, matches, phases, registrations
            
            // Store subscription in cache or database
            $cacheKey = "tournament_subscriptions_{$tournament->id}_{$subscriptionType}";
            $subscribers = Cache::get($cacheKey, []);
            
            if (!in_array($userId, $subscribers)) {
                $subscribers[] = $userId;
                Cache::put($cacheKey, $subscribers, 3600); // 1 hour
            }
            
            $channels = $this->getSubscriptionChannels($tournament, $subscriptionType);
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully subscribed to tournament updates',
                'subscription' => [
                    'tournament_id' => $tournament->id,
                    'type' => $subscriptionType,
                    'channels' => $channels,
                    'user_id' => $userId
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Tournament subscription error: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe to tournament updates',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Unsubscribe from tournament live updates
     */
    public function unsubscribe(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $userId = auth()->id();
            $subscriptionType = $request->get('type', 'general');
            
            // Remove subscription from cache
            $cacheKey = "tournament_subscriptions_{$tournament->id}_{$subscriptionType}";
            $subscribers = Cache::get($cacheKey, []);
            
            $subscribers = array_filter($subscribers, function($id) use ($userId) {
                return $id !== $userId;
            });
            
            Cache::put($cacheKey, array_values($subscribers), 3600);
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully unsubscribed from tournament updates'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Tournament unsubscription error: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to unsubscribe from tournament updates',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get subscription channels for a tournament
     */
    private function getSubscriptionChannels(Tournament $tournament, string $type): array
    {
        $baseChannels = ["tournament.{$tournament->id}"];
        
        switch ($type) {
            case 'matches':
                return array_merge($baseChannels, [
                    "tournament.{$tournament->id}.matches",
                    'matches.live'
                ]);
                
            case 'phases':
                return array_merge($baseChannels, [
                    "tournament.{$tournament->id}.phases"
                ]);
                
            case 'registrations':
                return array_merge($baseChannels, [
                    "tournament.{$tournament->id}.registrations"
                ]);
                
            case 'swiss':
                return array_merge($baseChannels, [
                    "tournament.{$tournament->id}.swiss"
                ]);
                
            default:
                return array_merge($baseChannels, [
                    'tournaments.live'
                ]);
        }
    }

    /**
     * Get tournament activity feed
     */
    public function getActivityFeed(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 20), 100);
            $since = $request->get('since'); // ISO timestamp
            
            $activities = collect();
            
            // Recent match completions
            $recentMatches = $tournament->matches()
                ->where('status', 'completed')
                ->when($since, function($query) use ($since) {
                    $query->where('completed_at', '>', $since);
                })
                ->with(['team1:id,name,short_name,logo', 'team2:id,name,short_name,logo'])
                ->orderBy('completed_at', 'desc')
                ->limit($limit / 2)
                ->get();

            foreach ($recentMatches as $match) {
                $activities->push([
                    'type' => 'match_completed',
                    'timestamp' => $match->completed_at,
                    'data' => [
                        'match_id' => $match->id,
                        'identifier' => $match->match_identifier,
                        'team1' => $match->team1,
                        'team2' => $match->team2,
                        'team1_score' => $match->team1_score,
                        'team2_score' => $match->team2_score,
                        'round' => $match->round
                    ]
                ]);
            }
            
            // Recent registrations
            $recentRegistrations = $tournament->registrations()
                ->where('status', 'approved')
                ->when($since, function($query) use ($since) {
                    $query->where('approved_at', '>', $since);
                })
                ->with(['team:id,name,short_name,logo'])
                ->orderBy('approved_at', 'desc')
                ->limit($limit / 2)
                ->get();

            foreach ($recentRegistrations as $registration) {
                $activities->push([
                    'type' => 'team_registered',
                    'timestamp' => $registration->approved_at,
                    'data' => [
                        'team' => $registration->team
                    ]
                ]);
            }
            
            // Sort by timestamp and limit
            $activities = $activities->sortByDesc('timestamp')->take($limit)->values();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => $activities,
                    'count' => $activities->count(),
                    'last_updated' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Tournament activity feed error: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tournament activity',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }
}