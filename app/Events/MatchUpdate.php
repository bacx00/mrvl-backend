<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $eventType;
    public $data;

    /**
     * Create a new event instance.
     *
     * @param int $matchId
     * @param string $eventType
     * @param array $data
     */
    public function __construct($matchId, $eventType, $data)
    {
        $this->matchId = $matchId;
        $this->eventType = $eventType;
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('match.' . $this->matchId),
            new Channel('matches'),  // Global matches channel
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return $this->eventType;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->matchId,
            'event_type' => $this->eventType,
            'data' => $this->data,
            'timestamp' => now()->toISOString()
        ];
    }
}