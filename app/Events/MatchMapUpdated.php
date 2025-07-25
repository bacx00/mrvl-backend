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

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(MvrlMatch $match)
    {
        $this->match = $match;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('match.' . $this->match->id);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'map.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $mapsData = $this->match->maps_data ?? [];
        $playerStats = $this->match->player_stats ?? [];
        $timerData = $this->match->match_timer ?? null;
        
        return [
            'match_id' => $this->match->id,
            'status' => $this->match->status,
            'current_map' => $this->match->current_map_number,
            'series_score' => [
                'team1' => $this->match->team1_score,
                'team2' => $this->match->team2_score
            ],
            'maps_data' => $mapsData,
            'player_stats' => $playerStats,
            'timer' => $timerData,
            'team1_score' => $this->match->team1_score,
            'team2_score' => $this->match->team2_score,
            'format' => $this->match->format,
            'stream_urls' => $this->match->stream_urls ?? [],
            'betting_urls' => $this->match->betting_urls ?? [],
            'vod_urls' => $this->match->vod_urls ?? [],
            'updated_at' => $this->match->updated_at->toISOString()
        ];
    }
}