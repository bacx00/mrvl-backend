<?php

namespace App\Events;

use App\Models\MvrlMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchMapUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;
    public $updateData;

    public function __construct(MvrlMatch $match, array $updateData = [])
    {
        $this->match = $match;
        $this->updateData = $updateData;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->match->id);
    }

    public function broadcastAs()
    {
        return 'match.map.updated';
    }

    public function broadcastWith()
    {
        $maps = json_decode($this->match->maps_data, true) ?? [];
        $currentMapIndex = ($this->match->current_map_number ?? 1) - 1;
        $currentMap = $maps[$currentMapIndex] ?? null;

        return [
            'match_id' => $this->match->id,
            'status' => $this->match->status,
            'current_map_number' => $this->match->current_map_number,
            'series_score' => [
                'team1' => $this->match->team1_score,
                'team2' => $this->match->team2_score
            ],
            'current_map' => $currentMap,
            'maps' => $maps,
            'timestamp' => now()->toISOString(),
            'update_data' => $this->updateData
        ];
    }
}