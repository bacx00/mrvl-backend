<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchMapEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $mapNumber;
    public $winnerId;
    public $matchCompleted;

    public function __construct($matchId, $mapNumber, $winnerId, $matchCompleted)
    {
        $this->matchId = $matchId;
        $this->mapNumber = $mapNumber;
        $this->winnerId = $winnerId;
        $this->matchCompleted = $matchCompleted;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'map.ended';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'map_number' => $this->mapNumber,
            'winner_id' => $this->winnerId,
            'match_completed' => $this->matchCompleted,
            'timestamp' => now()->toISOString()
        ];
    }
}