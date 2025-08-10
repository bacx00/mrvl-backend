<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\BracketMatch;
use App\Models\TournamentRegistration;
use App\Events\TournamentPhaseChanged;
use App\Events\TournamentMatchUpdated;
use App\Events\TournamentRegistrationUpdated;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TournamentLiveUpdateService
{
    /**
     * Broadcast tournament match update
     */
    public function broadcastMatchUpdate(BracketMatch $match, string $updateType = 'updated', array $eventData = []): void
    {
        try {
            $tournament = $match->tournament;
            
            // Broadcast to all relevant channels
            event(new TournamentMatchUpdated($tournament, $match, $updateType, $eventData));
            
            // Update cached live matches
            $this->updateLiveMatchesCache();
            
            // Check if this match completion triggers phase progression
            if ($updateType === 'completed') {
                $this->checkPhaseProgression($tournament, $match);
            }
            
            Log::info("Tournament match update broadcasted", [
                'tournament_id' => $tournament->id,
                'match_id' => $match->id,
                'update_type' => $updateType
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to broadcast tournament match update: " . $e->getMessage(), [
                'match_id' => $match->id,
                'update_type' => $updateType
            ]);
        }
    }

    /**
     * Broadcast tournament phase change
     */
    public function broadcastPhaseChange(Tournament $tournament, TournamentPhase $newPhase, TournamentPhase $previousPhase = null, array $eventData = []): void
    {
        try {
            event(new TournamentPhaseChanged($tournament, $newPhase, $previousPhase, $eventData));
            
            // Update tournament live status cache
            $this->updateTournamentCache($tournament);
            
            // Notify all tournament followers
            $this->notifyTournamentFollowers($tournament, 'phase_changed', [
                'new_phase' => $newPhase->name,
                'previous_phase' => $previousPhase?->name
            ]);
            
            Log::info("Tournament phase change broadcasted", [
                'tournament_id' => $tournament->id,
                'new_phase' => $newPhase->phase_type,
                'previous_phase' => $previousPhase?->phase_type
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to broadcast tournament phase change: " . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'phase_id' => $newPhase->id
            ]);
        }
    }

    /**
     * Broadcast tournament registration update
     */
    public function broadcastRegistrationUpdate(TournamentRegistration $registration, string $updateType = 'updated', array $eventData = []): void
    {
        try {
            $tournament = $registration->tournament;
            
            event(new TournamentRegistrationUpdated($tournament, $registration, $updateType, $eventData));
            
            // Update tournament registration stats cache
            $this->updateRegistrationStatsCache($tournament);
            
            Log::info("Tournament registration update broadcasted", [
                'tournament_id' => $tournament->id,
                'registration_id' => $registration->id,
                'update_type' => $updateType
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to broadcast tournament registration update: " . $e->getMessage(), [
                'registration_id' => $registration->id,
                'update_type' => $updateType
            ]);
        }
    }

    /**
     * Broadcast Swiss round generation
     */
    public function broadcastSwissRoundGenerated(Tournament $tournament, int $round, array $pairings): void
    {
        try {
            $channels = [
                "tournament.{$tournament->id}",
                "tournament.{$tournament->id}.swiss",
                'tournaments.live'
            ];

            $data = [
                'event' => 'swiss_round_generated',
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'round' => $round,
                'pairings_count' => count($pairings),
                'pairings' => array_map(function($pairing) {
                    return [
                        'team1' => $pairing['team1'] ? [
                            'id' => $pairing['team1']['team_id'],
                            'name' => $pairing['team1']['team']['name'],
                            'score' => $pairing['team1']['score'],
                            'wins' => $pairing['team1']['wins'],
                            'losses' => $pairing['team1']['losses']
                        ] : null,
                        'team2' => $pairing['team2'] ? [
                            'id' => $pairing['team2']['team_id'],
                            'name' => $pairing['team2']['team']['name'],
                            'score' => $pairing['team2']['score'],
                            'wins' => $pairing['team2']['wins'],
                            'losses' => $pairing['team2']['losses']
                        ] : null,
                        'is_bye' => $pairing['is_bye'] ?? false
                    ];
                }, $pairings),
                'timestamp' => now()->toISOString()
            ];

            foreach ($channels as $channel) {
                Broadcast::channel($channel)->with($data);
            }
            
            Log::info("Swiss round generation broadcasted", [
                'tournament_id' => $tournament->id,
                'round' => $round,
                'pairings_count' => count($pairings)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to broadcast Swiss round generation: " . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'round' => $round
            ]);
        }
    }

    /**
     * Broadcast tournament completion
     */
    public function broadcastTournamentCompleted(Tournament $tournament, array $finalStandings, array $winner = null): void
    {
        try {
            $channels = [
                "tournament.{$tournament->id}",
                'tournaments.live',
                "tournament-type.{$tournament->type}",
                "tournament-region.{$tournament->region}"
            ];

            $data = [
                'event' => 'tournament_completed',
                'tournament' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'slug' => $tournament->slug,
                    'type' => $tournament->type,
                    'format' => $tournament->format,
                    'region' => $tournament->region,
                    'prize_pool' => $tournament->formatted_prize_pool,
                    'team_count' => $tournament->current_team_count,
                    'duration_days' => $tournament->getDurationInDays()
                ],
                'winner' => $winner,
                'final_standings' => array_slice($finalStandings, 0, 8), // Top 8 for broadcast
                'completion_stats' => [
                    'total_matches' => $tournament->matches()->count(),
                    'total_phases' => $tournament->phases()->count(),
                    'participants' => $tournament->current_team_count,
                    'duration' => $tournament->getDurationInDays() . ' days'
                ],
                'timestamp' => now()->toISOString()
            ];

            foreach ($channels as $channel) {
                Broadcast::channel($channel)->with($data);
            }
            
            // Special celebration broadcast for major tournaments
            if (in_array($tournament->type, ['mrc', 'mri', 'international'])) {
                $this->broadcastMajorTournamentCompletion($tournament, $winner);
            }
            
            Log::info("Tournament completion broadcasted", [
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'winner' => $winner
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to broadcast tournament completion: " . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
        }
    }

    /**
     * Get live tournament data for broadcasting
     */
    public function getLiveTournamentData(): array
    {
        try {
            $cacheKey = 'live_tournaments_data';
            
            return Cache::remember($cacheKey, 30, function () {
                $liveTournaments = Tournament::ongoing()
                    ->with(['teams:id,name,short_name,logo', 'phases' => function($query) {
                        $query->where('is_active', true);
                    }])
                    ->get();

                $data = [];
                foreach ($liveTournaments as $tournament) {
                    $activePhase = $tournament->phases->first();
                    
                    $data[] = [
                        'id' => $tournament->id,
                        'name' => $tournament->name,
                        'slug' => $tournament->slug,
                        'type' => $tournament->type,
                        'format' => $tournament->format,
                        'region' => $tournament->region,
                        'current_phase' => $tournament->current_phase,
                        'active_phase' => $activePhase ? [
                            'id' => $activePhase->id,
                            'name' => $activePhase->name,
                            'phase_type' => $activePhase->phase_type
                        ] : null,
                        'team_count' => $tournament->current_team_count,
                        'prize_pool' => $tournament->formatted_prize_pool,
                        'progress_percentage' => $tournament->getProgressPercentage(),
                        'live_matches_count' => $tournament->matches()
                            ->whereIn('status', ['ongoing', 'pending'])
                            ->count(),
                        'viewers' => $this->getTournamentViewers($tournament->id)
                    ];
                }

                return [
                    'tournaments' => $data,
                    'total_live' => count($data),
                    'last_updated' => now()->toISOString()
                ];
            });
            
        } catch (\Exception $e) {
            Log::error("Failed to get live tournament data: " . $e->getMessage());
            return [
                'tournaments' => [],
                'total_live' => 0,
                'last_updated' => now()->toISOString(),
                'error' => 'Failed to load live data'
            ];
        }
    }

    /**
     * Update live matches cache
     */
    private function updateLiveMatchesCache(): void
    {
        $cacheKey = 'live_matches_data';
        
        Cache::put($cacheKey, function () {
            return BracketMatch::whereIn('status', ['ongoing', 'pending'])
                ->with(['tournament:id,name,slug', 'team1:id,name,short_name,logo', 'team2:id,name,short_name,logo'])
                ->orderBy('scheduled_at')
                ->limit(50)
                ->get()
                ->map(function($match) {
                    return [
                        'id' => $match->id,
                        'tournament' => $match->tournament,
                        'team1' => $match->team1,
                        'team2' => $match->team2,
                        'status' => $match->status,
                        'scheduled_at' => $match->scheduled_at?->toISOString(),
                        'round' => $match->round,
                        'match_format' => $match->match_format
                    ];
                });
        }, 60); // Cache for 1 minute
    }

    /**
     * Update tournament cache
     */
    private function updateTournamentCache(Tournament $tournament): void
    {
        $cacheKey = "tournament_{$tournament->id}_live_data";
        
        Cache::put($cacheKey, [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'status' => $tournament->status,
            'current_phase' => $tournament->current_phase,
            'progress_percentage' => $tournament->getProgressPercentage(),
            'team_count' => $tournament->current_team_count,
            'live_matches' => $tournament->matches()
                ->whereIn('status', ['ongoing', 'pending'])
                ->count(),
            'last_updated' => now()->toISOString()
        ], 300); // Cache for 5 minutes
    }

    /**
     * Update registration stats cache
     */
    private function updateRegistrationStatsCache(Tournament $tournament): void
    {
        $cacheKey = "tournament_{$tournament->id}_registration_stats";
        
        $stats = TournamentRegistration::getRegistrationStats($tournament->id);
        Cache::put($cacheKey, $stats, 60); // Cache for 1 minute
    }

    /**
     * Check if match completion triggers phase progression
     */
    private function checkPhaseProgression(Tournament $tournament, BracketMatch $match): void
    {
        $phase = $match->tournamentPhase;
        
        if ($phase && $phase->isComplete()) {
            // Trigger phase progression service
            $progressionService = app(\App\Services\TournamentProgressionService::class);
            $progressionService->handleMatchCompletion($match);
        }
    }

    /**
     * Notify tournament followers
     */
    private function notifyTournamentFollowers(Tournament $tournament, string $eventType, array $data = []): void
    {
        // This would integrate with a notification system
        // For now, just broadcast to user channels
        $followers = $this->getTournamentFollowers($tournament->id);
        
        foreach ($followers as $userId) {
            Broadcast::channel("user.{$userId}.notifications")->with([
                'type' => 'tournament_update',
                'event_type' => $eventType,
                'tournament' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name
                ],
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);
        }
    }

    /**
     * Broadcast major tournament completion with special effects
     */
    private function broadcastMajorTournamentCompletion(Tournament $tournament, array $winner = null): void
    {
        Broadcast::channel('major.tournaments')->with([
            'event' => 'major_tournament_completed',
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'type' => $tournament->type,
                'prize_pool' => $tournament->formatted_prize_pool
            ],
            'winner' => $winner,
            'celebration' => true,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get tournament viewers count (placeholder for analytics integration)
     */
    private function getTournamentViewers(int $tournamentId): int
    {
        return Cache::get("tournament_{$tournamentId}_viewers", 0);
    }

    /**
     * Get tournament followers (placeholder for user system integration)
     */
    private function getTournamentFollowers(int $tournamentId): array
    {
        return Cache::get("tournament_{$tournamentId}_followers", []);
    }
}