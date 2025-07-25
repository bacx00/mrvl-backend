<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $data;

    public function __construct($matchId, $data = null)
    {
        $this->matchId = $matchId;
        $this->data = $data;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'match.updated';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'data' => $this->data,
            'timestamp' => now()->toISOString()
        ];
    }
}