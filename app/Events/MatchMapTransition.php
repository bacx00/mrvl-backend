<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchMapTransition implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $mapNumber;

    public function __construct($matchId, $mapNumber)
    {
        $this->matchId = $matchId;
        $this->mapNumber = $mapNumber;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'match.map.transition';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'map_number' => $this->mapNumber,
            'preparation_phase' => true,
            'timestamp' => now()->toISOString()
        ];
    }
}