<?php

namespace App\Jobs;

use App\Models\BracketMatch;
use App\Services\TournamentIntegrationService;
use App\Services\EloRatingService;
use App\Events\BracketUpdated;
use App\Events\TournamentPhaseChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessMatchCompletion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180; // 3 minutes timeout
    public $tries = 3;

    protected BracketMatch $match;
    protected array $matchData;

    public function __construct(BracketMatch $match, array $matchData = [])
    {
        $this->match = $match;
        $this->matchData = $matchData;
        
        // Use high priority queue for S-tier tournaments
        if ($this->isHighPriorityTournament()) {
            $this->onQueue('high-priority');
        } else {
            $this->onQueue('matches');
        }
    }

    public function handle(
        TournamentIntegrationService $tournamentService,
        EloRatingService $eloService
    ): void {
        try {
            Log::info('Processing match completion', [
                'match_id' => $this->match->id,
                'match_identifier' => $this->match->match_id,
                'liquipedia_id' => $this->match->liquipedia_id,
                'winner_id' => $this->match->winner_id,
                'tournament_id' => $this->match->tournament_id,
                'event_id' => $this->match->event_id
            ]);

            // Process tournament advancement
            $advancement = $tournamentService->processMatchCompletion($this->match, $this->matchData);

            // Update ELO ratings if both teams exist
            if ($this->match->team1_id && $this->match->team2_id && $this->match->winner_id) {
                $this->updateEloRatings($eloService);
            }

            // Check if phase transition occurred
            $phaseTransition = $this->checkPhaseTransition($tournamentService);

            // Update tournament standings
            $this->updateTournamentStandings($tournamentService);

            // Generate next matches if needed (Swiss system)
            $nextMatches = $this->generateNextMatches($tournamentService);

            // Cache results for live updates
            $this->cacheUpdateResults($advancement, $phaseTransition, $nextMatches);

            // Broadcast updates
            $this->broadcastUpdates($advancement, $phaseTransition);

            Log::info('Match completion processed successfully', [
                'match_id' => $this->match->id,
                'advancement_processed' => !empty($advancement),
                'phase_transition' => !empty($phaseTransition),
                'next_matches_generated' => count($nextMatches),
                'execution_time' => microtime(true) - LARAVEL_START
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process match completion', [
                'match_id' => $this->match->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    protected function updateEloRatings(EloRatingService $eloService): void
    {
        try {
            $team1 = $this->match->team1;
            $team2 = $this->match->team2;
            
            if (!$team1 || !$team2) return;

            $isTeam1Winner = $this->match->winner_id === $team1->id;
            $kFactor = $this->calculateKFactor();

            // Calculate new ratings
            $ratings = $eloService->calculateNewRatings(
                $team1->rating ?? 1000,
                $team2->rating ?? 1000,
                $isTeam1Winner ? 1 : 0, // 1 for team1 win, 0 for team2 win
                $kFactor
            );

            // Update team ratings
            $team1->update(['rating' => $ratings['team1_new_rating']]);
            $team2->update(['rating' => $ratings['team2_new_rating']]);

            // Log rating changes
            Log::info('ELO ratings updated', [
                'match_id' => $this->match->id,
                'team1_id' => $team1->id,
                'team1_old_rating' => $team1->rating,
                'team1_new_rating' => $ratings['team1_new_rating'],
                'team1_change' => $ratings['team1_new_rating'] - ($team1->rating ?? 1000),
                'team2_id' => $team2->id,
                'team2_old_rating' => $team2->rating,
                'team2_new_rating' => $ratings['team2_new_rating'],
                'team2_change' => $ratings['team2_new_rating'] - ($team2->rating ?? 1000),
                'k_factor' => $kFactor
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to update ELO ratings', [
                'match_id' => $this->match->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function calculateKFactor(): int
    {
        // Adjust K-factor based on tournament importance
        if ($this->match->event_id) {
            $event = $this->match->event;
            return match($event->tier ?? 'B') {
                'S' => 40, // Premier tournaments
                'A' => 32, // Major tournaments  
                'B' => 24, // Standard tournaments
                'C' => 16, // Minor tournaments
                default => 24
            };
        }
        
        return 24; // Default K-factor
    }

    protected function checkPhaseTransition(TournamentIntegrationService $service): array
    {
        if ($this->match->event_id) {
            return $service->checkPhaseProgression($this->match->event);
        }
        
        return [];
    }

    protected function updateTournamentStandings(TournamentIntegrationService $service): void
    {
        if ($this->match->event_id) {
            $service->updateTournamentStandings($this->match->event);
        }
    }

    protected function generateNextMatches(TournamentIntegrationService $service): array
    {
        // For Swiss system, check if next round should be generated
        if ($this->match->bracketStage && $this->match->bracketStage->type === 'swiss') {
            return $this->generateNextSwissRound($service);
        }

        return [];
    }

    protected function generateNextSwissRound(TournamentIntegrationService $service): array
    {
        $stage = $this->match->bracketStage;
        
        // Check if all matches in current round are completed
        $currentRoundMatches = $stage->matches()
            ->where('round_number', $this->match->round_number)
            ->get();
        
        $completedMatches = $currentRoundMatches->where('status', 'completed');
        
        // If all matches in round are completed, generate next round
        if ($currentRoundMatches->count() === $completedMatches->count()) {
            $nextRound = $this->match->round_number + 1;
            
            // Don't generate if we've reached the maximum rounds
            if ($nextRound <= ($stage->total_rounds ?? 0)) {
                try {
                    $nextMatches = $service->generateSwissRound($stage, $nextRound);
                    
                    Log::info('Next Swiss round generated automatically', [
                        'stage_id' => $stage->id,
                        'round_number' => $nextRound,
                        'matches_created' => $nextMatches->count()
                    ]);
                    
                    return $nextMatches->toArray();
                } catch (\Exception $e) {
                    Log::warning('Failed to generate next Swiss round', [
                        'stage_id' => $stage->id,
                        'round_number' => $nextRound,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return [];
    }

    protected function cacheUpdateResults(array $advancement, array $phaseTransition, array $nextMatches): void
    {
        $cacheData = [
            'type' => 'match_completed',
            'timestamp' => now()->toISOString(),
            'data' => [
                'match_id' => $this->match->id,
                'match_identifier' => $this->match->match_id,
                'liquipedia_id' => $this->match->liquipedia_id,
                'winner' => [
                    'id' => $this->match->winner_id,
                    'name' => $this->match->winner->name ?? null
                ],
                'score' => [
                    'team1' => $this->match->team1_score,
                    'team2' => $this->match->team2_score
                ],
                'advancement' => $advancement,
                'phase_transition' => $phaseTransition,
                'next_matches' => count($nextMatches)
            ]
        ];

        // Cache for tournament-level SSE
        if ($this->match->event_id) {
            $cacheKey = "live_update_event_{$this->match->event_id}_match_completed";
            Cache::put($cacheKey, $cacheData, 300);
        }
        
        if ($this->match->tournament_id) {
            $cacheKey = "live_update_tournament_{$this->match->tournament_id}_match_completed";
            Cache::put($cacheKey, $cacheData, 300);
        }

        // Cache for bracket-level updates
        if ($this->match->bracket_stage_id) {
            $cacheKey = "live_update_bracket_{$this->match->bracket_stage_id}_match_completed";
            Cache::put($cacheKey, $cacheData, 300);
        }
    }

    protected function broadcastUpdates(array $advancement, array $phaseTransition): void
    {
        // Broadcast bracket update
        event(new BracketUpdated($this->match, $advancement));

        // Broadcast phase transition if it occurred
        if (!empty($phaseTransition)) {
            $tournament = $this->match->event ?? $this->match->tournament;
            if ($tournament) {
                event(new TournamentPhaseChanged($tournament, $phaseTransition, 'match_completion'));
            }
        }
    }

    protected function isHighPriorityTournament(): bool
    {
        if ($this->match->event_id && $this->match->event) {
            return $this->match->event->tier === 'S' || $this->match->event->featured;
        }
        
        return false;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Match completion processing failed permanently', [
            'match_id' => $this->match->id,
            'attempts' => $this->attempts,
            'exception' => $exception->getMessage()
        ]);

        // Cache failure notification for admin
        $cacheKey = "match_completion_failed_{$this->match->id}";
        Cache::put($cacheKey, [
            'status' => 'failed',
            'failed_at' => now(),
            'error' => $exception->getMessage(),
            'match_id' => $this->match->id,
            'requires_manual_intervention' => true
        ], 86400); // 24 hours
    }
}