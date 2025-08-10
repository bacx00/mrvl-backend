<?php

namespace App\Events;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentPhaseChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $phase;
    public $previousPhase;
    public $eventData;

    /**
     * Create a new event instance.
     */
    public function __construct(Tournament $tournament, TournamentPhase $phase, TournamentPhase $previousPhase = null, array $eventData = [])
    {
        $this->tournament = $tournament;
        $this->phase = $phase;
        $this->previousPhase = $previousPhase;
        $this->eventData = $eventData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("tournament.{$this->tournament->id}"),
            new Channel("tournament.{$this->tournament->id}.phases"),
            new Channel('tournaments.live'),
            new Channel("tournament-type.{$this->tournament->type}"),
            new Channel("tournament-region.{$this->tournament->region}")
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'phase_changed',
            'tournament' => [
                'id' => $this->tournament->id,
                'name' => $this->tournament->name,
                'slug' => $this->tournament->slug,
                'type' => $this->tournament->type,
                'format' => $this->tournament->format,
                'status' => $this->tournament->status,
                'current_phase' => $this->tournament->current_phase,
                'region' => $this->tournament->region,
                'prize_pool' => $this->tournament->formatted_prize_pool,
                'team_count' => $this->tournament->current_team_count
            ],
            'phase' => [
                'id' => $this->phase->id,
                'name' => $this->phase->name,
                'phase_type' => $this->phase->phase_type,
                'status' => $this->phase->status,
                'phase_order' => $this->phase->phase_order,
                'start_date' => $this->phase->start_date?->toISOString(),
                'team_count' => $this->phase->team_count,
                'match_format' => $this->phase->match_format
            ],
            'previous_phase' => $this->previousPhase ? [
                'id' => $this->previousPhase->id,
                'name' => $this->previousPhase->name,
                'phase_type' => $this->previousPhase->phase_type,
                'status' => $this->previousPhase->status
            ] : null,
            'event_data' => $this->eventData,
            'timestamp' => now()->toISOString(),
            'progress_percentage' => $this->tournament->getProgressPercentage()
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'tournament.phase.changed';
    }
}