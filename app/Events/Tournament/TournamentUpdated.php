<?php

namespace App\Events\Tournament;

use App\Models\Tournament;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $updateType;
    public $changes;
    public $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(Tournament $tournament, string $updateType, array $changes = [], array $metadata = [])
    {
        $this->tournament = $tournament;
        $this->updateType = $updateType;
        $this->changes = $changes;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tournament.' . $this->tournament->id),
            new Channel('tournaments.public'), // For public tournament lists
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'tournament_name' => $this->tournament->name,
            'tournament_status' => $this->tournament->status,
            'current_phase' => $this->tournament->current_phase,
            'update_type' => $this->updateType,
            'changes' => $this->changes,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'tournament.updated';
    }
}