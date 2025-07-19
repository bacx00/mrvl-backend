<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchObjectiveUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $mapNumber;
    public $objectiveData;

    public function __construct($matchId, $mapNumber, $objectiveData)
    {
        $this->matchId = $matchId;
        $this->mapNumber = $mapNumber;
        $this->objectiveData = $objectiveData;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'objective.update';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'map_number' => $this->mapNumber,
            'objective_data' => $this->objectiveData,
            'timestamp' => now()->toISOString()
        ];
    }
}