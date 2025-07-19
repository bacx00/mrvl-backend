<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchMapStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $mapNumber;
    public $mapData;

    public function __construct($matchId, $mapNumber, $mapData)
    {
        $this->matchId = $matchId;
        $this->mapNumber = $mapNumber;
        $this->mapData = $mapData;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'map.started';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'map_number' => $this->mapNumber,
            'map_data' => $this->mapData,
            'timestamp' => now()->toISOString()
        ];
    }
}