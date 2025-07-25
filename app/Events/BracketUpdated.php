<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BracketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $eventId;
    public $matchId;
    public $updateType;
    public $updateData;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($eventId, $matchId = null, string $updateType = 'match-updated', array $updateData = [])
    {
        $this->eventId = $eventId;
        $this->matchId = $matchId;
        $this->updateType = $updateType;
        $this->updateData = $updateData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('event.' . $this->eventId);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'bracket.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'event_id' => $this->eventId,
            'match_id' => $this->matchId,
            'type' => $this->updateType,
            'timestamp' => now()->toISOString(),
            'data' => $this->updateData
        ];
    }
}