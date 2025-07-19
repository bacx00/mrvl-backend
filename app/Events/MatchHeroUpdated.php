<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchHeroUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchId;
    public $mapNumber;
    public $teamId;
    public $playerId;
    public $heroName;
    public $action;
    public $heroData;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($matchId, $mapNumber, $teamId, $playerId, $heroName, $action, $heroData)
    {
        $this->matchId = $matchId;
        $this->mapNumber = $mapNumber;
        $this->teamId = $teamId;
        $this->playerId = $playerId;
        $this->heroName = $heroName;
        $this->action = $action;
        $this->heroData = $heroData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'hero.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'map_number' => $this->mapNumber,
            'team_id' => $this->teamId,
            'player_id' => $this->playerId,
            'hero_name' => $this->heroName,
            'action' => $this->action,
            'hero_data' => $this->heroData,
            'timestamp' => now()->toISOString()
        ];
    }
}