<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchKillEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $killData;

    public function __construct($matchId, $killData)
    {
        $this->matchId = $matchId;
        $this->killData = $killData;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'kill.event';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'kill_data' => $this->killData,
            'timestamp' => now()->toISOString()
        ];
    }
}