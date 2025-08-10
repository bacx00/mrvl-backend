<?php

namespace App\Listeners;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\BracketMatch;
use App\Services\EnhancedTournamentCacheService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class TournamentCacheInvalidationListener
{
    protected $cacheService;

    /**
     * Create the event listener.
     */
    public function __construct(EnhancedTournamentCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        // Tournament model events
        $events->listen('eloquent.created: App\Models\Tournament', [$this, 'handleTournamentCreated']);
        $events->listen('eloquent.updated: App\Models\Tournament', [$this, 'handleTournamentUpdated']);
        $events->listen('eloquent.deleted: App\Models\Tournament', [$this, 'handleTournamentDeleted']);

        // Tournament registration events
        $events->listen('eloquent.created: App\Models\TournamentRegistration', [$this, 'handleRegistrationCreated']);
        $events->listen('eloquent.updated: App\Models\TournamentRegistration', [$this, 'handleRegistrationUpdated']);
        $events->listen('eloquent.deleted: App\Models\TournamentRegistration', [$this, 'handleRegistrationDeleted']);

        // Tournament phase events
        $events->listen('eloquent.updated: App\Models\TournamentPhase', [$this, 'handlePhaseUpdated']);

        // Match events
        $events->listen('eloquent.updated: App\Models\BracketMatch', [$this, 'handleMatchUpdated']);

        // Custom tournament events
        $events->listen('tournament.started', [$this, 'handleTournamentStarted']);
        $events->listen('tournament.completed', [$this, 'handleTournamentCompleted']);
        $events->listen('tournament.phase.changed', [$this, 'handlePhaseChanged']);
        $events->listen('tournament.bracket.updated', [$this, 'handleBracketUpdated']);
    }

    /**
     * Handle tournament creation
     */
    public function handleTournamentCreated(Tournament $tournament): void
    {
        try {
            // Invalidate tournament lists as there's a new tournament
            $this->cacheService->invalidateTournamentListCaches();
            
            // Warm cache for the new tournament
            $this->cacheService->warmTournamentCaches($tournament);

            Log::info('Tournament cache invalidated for new tournament', [
                'tournament_id' => $tournament->id
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament creation cache invalidation failed: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
        }
    }

    /**
     * Handle tournament updates
     */
    public function handleTournamentUpdated(Tournament $tournament): void
    {
        try {
            $changes = $tournament->getDirty();
            
            // Invalidate all caches for this tournament
            $this->cacheService->invalidateAllTournamentCaches($tournament);
            
            // If status changed, also invalidate list caches
            if (isset($changes['status']) || isset($changes['registration_open'])) {
                $this->cacheService->invalidateTournamentListCaches();
            }
            
            // Re-warm critical caches
            $this->cacheService->warmTournamentCaches($tournament);

            Log::info('Tournament cache invalidated for tournament update', [
                'tournament_id' => $tournament->id,
                'changes' => array_keys($changes)
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament update cache invalidation failed: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
        }
    }

    /**
     * Handle tournament deletion
     */
    public function handleTournamentDeleted(Tournament $tournament): void
    {
        try {
            // Invalidate all caches for this tournament
            $this->cacheService->invalidateAllTournamentCaches($tournament);
            
            // Invalidate tournament lists
            $this->cacheService->invalidateTournamentListCaches();

            Log::info('Tournament cache invalidated for tournament deletion', [
                'tournament_id' => $tournament->id
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament deletion cache invalidation failed: ' . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
        }
    }

    /**
     * Handle tournament registration creation
     */
    public function handleRegistrationCreated(TournamentRegistration $registration): void
    {
        try {
            $registration->load('tournament');
            $tournament = $registration->tournament;

            // Invalidate tournament-specific caches
            $this->invalidateTournamentSpecificCaches($tournament);
            
            // Invalidate list caches as team count changed
            $this->cacheService->invalidateTournamentListCaches();

        } catch (\Exception $e) {
            Log::error('Registration creation cache invalidation failed: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);
        }
    }

    /**
     * Handle tournament registration updates
     */
    public function handleRegistrationUpdated(TournamentRegistration $registration): void
    {
        try {
            $registration->load('tournament');
            $tournament = $registration->tournament;

            // Invalidate registration-related caches
            $this->invalidateRegistrationCaches($tournament);
            
            // If status changed from pending to approved, invalidate more caches
            $changes = $registration->getDirty();
            if (isset($changes['status']) && $changes['status'] === 'approved') {
                $this->cacheService->invalidateTournamentListCaches();
            }

        } catch (\Exception $e) {
            Log::error('Registration update cache invalidation failed: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);
        }
    }

    /**
     * Handle tournament registration deletion
     */
    public function handleRegistrationDeleted(TournamentRegistration $registration): void
    {
        try {
            $tournament = $registration->tournament;

            // Invalidate tournament-specific caches
            $this->invalidateTournamentSpecificCaches($tournament);
            
            // Invalidate list caches as team count changed
            $this->cacheService->invalidateTournamentListCaches();

        } catch (\Exception $e) {
            Log::error('Registration deletion cache invalidation failed: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);
        }
    }

    /**
     * Handle tournament phase updates
     */
    public function handlePhaseUpdated($phase): void
    {
        try {
            $tournament = $phase->tournament;

            // Invalidate phase and tournament caches
            $this->cacheService->invalidateAllTournamentCaches($tournament);

        } catch (\Exception $e) {
            Log::error('Phase update cache invalidation failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle match updates
     */
    public function handleMatchUpdated(BracketMatch $match): void
    {
        try {
            $tournament = $match->tournament;

            // Invalidate tournament statistics and bracket caches
            $this->invalidateMatchRelatedCaches($tournament);

        } catch (\Exception $e) {
            Log::error('Match update cache invalidation failed: ' . $e->getMessage(), [
                'match_id' => $match->id
            ]);
        }
    }

    /**
     * Handle custom tournament events
     */
    public function handleTournamentStarted($event): void
    {
        try {
            $tournament = $event->tournament;
            
            // Invalidate all tournament caches
            $this->cacheService->invalidateAllTournamentCaches($tournament);
            
            // Warm important caches for ongoing tournament
            $this->cacheService->warmTournamentCaches($tournament);

        } catch (\Exception $e) {
            Log::error('Tournament started cache invalidation failed: ' . $e->getMessage());
        }
    }

    public function handleTournamentCompleted($event): void
    {
        try {
            $tournament = $event->tournament;
            
            // Invalidate all tournament caches
            $this->cacheService->invalidateAllTournamentCaches($tournament);

        } catch (\Exception $e) {
            Log::error('Tournament completed cache invalidation failed: ' . $e->getMessage());
        }
    }

    public function handlePhaseChanged($event): void
    {
        try {
            $tournament = $event->tournament;
            
            // Invalidate tournament caches
            $this->cacheService->invalidateAllTournamentCaches($tournament);

        } catch (\Exception $e) {
            Log::error('Phase changed cache invalidation failed: ' . $e->getMessage());
        }
    }

    public function handleBracketUpdated($event): void
    {
        try {
            $tournament = $event->tournament;
            
            // Invalidate bracket-related caches
            $this->invalidateBracketCaches($tournament);

        } catch (\Exception $e) {
            Log::error('Bracket updated cache invalidation failed: ' . $e->getMessage());
        }
    }

    // Helper methods

    private function invalidateTournamentSpecificCaches(Tournament $tournament): void
    {
        $keysToInvalidate = [
            'tournament:details:' . $tournament->id,
            'tournament:stats:' . $tournament->id,
            'tournament:registrations:' . $tournament->id,
            'tournament:phases:' . $tournament->id,
        ];

        foreach ($keysToInvalidate as $key) {
            cache()->forget($key);
        }
    }

    private function invalidateRegistrationCaches(Tournament $tournament): void
    {
        $keysToInvalidate = [
            'tournament:registrations:' . $tournament->id,
            'tournament:details:' . $tournament->id,
            'tournament:stats:' . $tournament->id,
        ];

        foreach ($keysToInvalidate as $key) {
            cache()->forget($key);
        }
    }

    private function invalidateMatchRelatedCaches(Tournament $tournament): void
    {
        $keysToInvalidate = [
            'tournament:stats:' . $tournament->id,
            'tournament:bracket:' . $tournament->id,
            'tournament:analytics:' . $tournament->id,
        ];

        foreach ($keysToInvalidate as $key) {
            cache()->forget($key);
        }
    }

    private function invalidateBracketCaches(Tournament $tournament): void
    {
        $keysToInvalidate = [
            'tournament:bracket:' . $tournament->id,
            'tournament:stats:' . $tournament->id,
        ];

        foreach ($keysToInvalidate as $key) {
            cache()->forget($key);
        }
    }
}