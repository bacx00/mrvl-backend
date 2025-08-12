<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $playerId;
    public $playerData;
    public $updatedFields;

    /**
     * Create a new event instance.
     */
    public function __construct($playerId, $playerData, $updatedFields = [])
    {
        $this->playerId = $playerId;
        $this->playerData = $playerData;
        $this->updatedFields = $updatedFields;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('player_updates'),
            new Channel("player.{$this->playerId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'player_updated',
            'player_id' => $this->playerId,
            'player_data' => $this->playerData,
            'updated_fields' => $this->updatedFields,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'player.updated';
    }
}