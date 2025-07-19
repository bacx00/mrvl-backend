<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchPaused implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $reason;

    public function __construct($matchId, $reason = null)
    {
        $this->matchId = $matchId;
        $this->reason = $reason;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'match.paused';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'reason' => $this->reason,
            'timestamp' => now()->toISOString()
        ];
    }
}