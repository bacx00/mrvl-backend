<?php

namespace App\Listeners;

use App\Models\Tournament;
use App\Models\BracketMatch;
use App\Models\TournamentRegistration;
use App\Services\TournamentBroadcastService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class TournamentEventListener
{
    protected $broadcastService;

    /**
     * Create the event listener.
     */
    public function __construct(TournamentBroadcastService $broadcastService)
    {
        $this->broadcastService = $broadcastService;
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        // Model events
        $events->listen('eloquent.updated: App\Models\Tournament', [$this, 'handleTournamentUpdated']);
        $events->listen('eloquent.updated: App\Models\BracketMatch', [$this, 'handleMatchUpdated']);
        $events->listen('eloquent.created: App\Models\TournamentRegistration', [$this, 'handleRegistrationCreated']);
        $events->listen('eloquent.updated: App\Models\TournamentRegistration', [$this, 'handleRegistrationUpdated']);

        // Custom events
        $events->listen('tournament.started', [$this, 'handleTournamentStarted']);
        $events->listen('tournament.completed', [$this, 'handleTournamentCompleted']);
        $events->listen('tournament.phase.started', [$this, 'handlePhaseStarted']);
        $events->listen('tournament.round.started', [$this, 'handleRoundStarted']);
        $events->listen('tournament.match.started', [$this, 'handleMatchStarted']);
        $events->listen('tournament.match.score_updated', [$this, 'handleMatchScoreUpdated']);
        $events->listen('tournament.bracket.updated', [$this, 'handleBracketUpdated']);
    }

    /**
     * Handle tournament model updates
     */
    public function handleTournamentUpdated(Tournament $tournament): void
    {
        try {
            $changes = $tournament->getDirty();
            $updateType = $this->determineTournamentUpdateType($changes);
            
            if ($updateType) {
                $this->broadcastService->broadcastTournamentUpdate(
                    $tournament,
                    $updateType,
                    $changes,
                    $this->getTournamentUpdateMetadata($tournament, $changes)
                );
            }
        } catch (\Exception $e) {
            Log::error('Tournament event listener error: ' . $e->getMessage());
        }
    }

    /**
     * Handle match model updates
     */
    public function handleMatchUpdated(BracketMatch $match): void
    {
        try {
            $changes = $match->getDirty();
            $updateType = $this->determineMatchUpdateType($changes);
            
            if ($updateType) {
                $previousState = $this->getMatchPreviousState($match, $changes);
                
                $this->broadcastService->broadcastMatchUpdate(
                    $match,
                    $updateType,
                    $previousState,
                    $this->getMatchUpdateMetadata($match, $changes)
                );

                // Handle live score updates separately
                if ($this->isLiveScoreUpdate($changes)) {
                    $scoreData = $this->extractScoreData($match, $changes);
                    $this->broadcastService->broadcastLiveScore(
                        $match,
                        $scoreData,
                        'score_updated',
                        ['real_time' => true]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Match event listener error: ' . $e->getMessage());
        }
    }

    /**
     * Handle registration created
     */
    public function handleRegistrationCreated(TournamentRegistration $registration): void
    {
        try {
            $registration->load(['tournament', 'team']);
            
            $this->broadcastService->broadcastTeamRegistered(
                $registration->tournament,
                $registration->team,
                $registration
            );
        } catch (\Exception $e) {
            Log::error('Registration created event listener error: ' . $e->getMessage());
        }
    }

    /**
     * Handle registration updated
     */
    public function handleRegistrationUpdated(TournamentRegistration $registration): void
    {
        try {
            $changes = $registration->getDirty();
            
            if (isset($changes['check_in_status']) && $changes['check_in_status'] === 'checked_in') {
                $registration->load(['tournament', 'team']);
                
                $this->broadcastService->broadcastTeamCheckedIn(
                    $registration->tournament,
                    $registration->team,
                    $registration
                );
            }
        } catch (\Exception $e) {
            Log::error('Registration updated event listener error: ' . $e->getMessage());
        }
    }

    /**
     * Handle custom tournament events
     */
    public function handleTournamentStarted($event): void
    {
        $this->broadcastService->broadcastTournamentStarted($event->tournament);
    }

    public function handleTournamentCompleted($event): void
    {
        $this->broadcastService->broadcastTournamentCompleted(
            $event->tournament,
            $event->results ?? []
        );
    }

    public function handlePhaseStarted($event): void
    {
        $this->broadcastService->broadcastPhaseStarted(
            $event->tournament,
            $event->phase
        );
    }

    public function handleRoundStarted($event): void
    {
        $this->broadcastService->broadcastRoundStarted(
            $event->tournament,
            $event->round,
            $event->matches ?? []
        );
    }

    public function handleMatchStarted($event): void
    {
        $this->broadcastService->broadcastMatchStarted($event->match);
    }

    public function handleMatchScoreUpdated($event): void
    {
        $this->broadcastService->broadcastLiveScore(
            $event->match,
            $event->scoreData,
            'live_score_update',
            $event->metadata ?? []
        );
    }

    public function handleBracketUpdated($event): void
    {
        $this->broadcastService->broadcastBracketUpdate(
            $event->tournament,
            $event->phase ?? null,
            $event->updateType,
            $event->bracketData ?? [],
            $event->metadata ?? []
        );
    }

    // Helper methods

    private function determineTournamentUpdateType(array $changes): ?string
    {
        if (isset($changes['status'])) {
            return match($changes['status']) {
                'ongoing' => 'tournament_started',
                'completed' => 'tournament_completed',
                'cancelled' => 'tournament_cancelled',
                default => 'status_changed'
            };
        }

        if (isset($changes['current_phase'])) {
            return 'phase_changed';
        }

        if (isset($changes['registration_open']) && !$changes['registration_open']) {
            return 'registration_closed';
        }

        if (isset($changes['check_in_open']) && $changes['check_in_open']) {
            return 'check_in_opened';
        }

        return 'tournament_updated';
    }

    private function determineMatchUpdateType(array $changes): ?string
    {
        if (isset($changes['status'])) {
            return match($changes['status']) {
                'ongoing' => 'match_started',
                'completed' => 'match_completed',
                'cancelled' => 'match_cancelled',
                default => 'status_changed'
            };
        }

        if (isset($changes['team1_score']) || isset($changes['team2_score'])) {
            return 'score_updated';
        }

        if (isset($changes['scheduled_at'])) {
            return 'schedule_updated';
        }

        return 'match_updated';
    }

    private function isLiveScoreUpdate(array $changes): bool
    {
        return isset($changes['team1_score']) || isset($changes['team2_score']);
    }

    private function extractScoreData(BracketMatch $match, array $changes): array
    {
        return [
            'team1_score' => $match->team1_score,
            'team2_score' => $match->team2_score,
            'score_change' => [
                'team1_previous' => $match->getOriginal('team1_score'),
                'team2_previous' => $match->getOriginal('team2_score'),
                'team1_current' => $match->team1_score,
                'team2_current' => $match->team2_score,
            ]
        ];
    }

    private function getMatchPreviousState(BracketMatch $match, array $changes): array
    {
        $previousState = [];
        
        foreach ($changes as $field => $newValue) {
            $previousState[$field] = $match->getOriginal($field);
        }

        return $previousState;
    }

    private function getTournamentUpdateMetadata(Tournament $tournament, array $changes): array
    {
        return [
            'tournament_name' => $tournament->name,
            'tournament_type' => $tournament->type,
            'tournament_format' => $tournament->format,
            'current_teams' => $tournament->current_team_count,
            'max_teams' => $tournament->max_teams,
            'changes_made' => array_keys($changes),
        ];
    }

    private function getMatchUpdateMetadata(BracketMatch $match, array $changes): array
    {
        return [
            'tournament_id' => $match->tournament_id,
            'round' => $match->round,
            'match_number' => $match->match_number,
            'team1_name' => $match->team1?->name,
            'team2_name' => $match->team2?->name,
            'changes_made' => array_keys($changes),
        ];
    }
}