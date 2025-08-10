<?php

namespace App\Events\Tournament;

use App\Models\BracketMatch;
use App\Models\Tournament;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;
    public $tournament;
    public $updateType;
    public $previousState;
    public $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(BracketMatch $match, string $updateType, array $previousState = [], array $metadata = [])
    {
        $this->match = $match->load(['team1', 'team2', 'tournament']);
        $this->tournament = $this->match->tournament;
        $this->updateType = $updateType;
        $this->previousState = $previousState;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tournament.' . $this->tournament->id),
            new Channel('tournament.' . $this->tournament->id . '.matches'),
            new Channel('match.' . $this->match->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'tournament_id' => $this->tournament->id,
            'round' => $this->match->round,
            'match_number' => $this->match->match_number,
            'status' => $this->match->status,
            'team1' => [
                'id' => $this->match->team1?->id,
                'name' => $this->match->team1?->name,
                'short_name' => $this->match->team1?->short_name,
                'score' => $this->match->team1_score,
            ],
            'team2' => [
                'id' => $this->match->team2?->id,
                'name' => $this->match->team2?->name,
                'short_name' => $this->match->team2?->short_name,
                'score' => $this->match->team2_score,
            ],
            'scheduled_at' => $this->match->scheduled_at?->toISOString(),
            'started_at' => $this->match->started_at?->toISOString(),
            'completed_at' => $this->match->completed_at?->toISOString(),
            'update_type' => $this->updateType,
            'previous_state' => $this->previousState,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'match.updated';
    }
}